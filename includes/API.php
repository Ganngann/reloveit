<?php
/**
 * API Endpoints
 *
 * @package Relovit
 */

namespace Relovit;

use Relovit\Product_Manager;

/**
 * Class API
 *
 * @package Relovit
 */
class API {

    /**
     * API constructor.
     */
    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    /**
     * Register the routes for the objects of the controller.
     */
    public function register_routes() {
        register_rest_route(
            'relovit/v1',
            '/identify-objects',
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'identify_objects' ],
                'permission_callback' => [ $this, 'check_permissions' ],
            ]
        );

        register_rest_route(
            'relovit/v1',
            '/create-products',
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'create_products' ],
                'permission_callback' => [ $this, 'check_permissions' ],
            ]
        );

        register_rest_route(
            'relovit/v1',
            '/enrich-product',
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'enrich_product' ],
                'permission_callback' => [ $this, 'check_permissions' ],
            ]
        );
    }

    /**
     * Identify objects from an image.
     *
     * @param \WP_REST_Request $request Full data about the request.
     * @return \WP_REST_Response|\WP_Error
     */
    public function identify_objects( $request ) {
        $files = $request->get_file_params();

        if ( empty( $files['relovit_image'] ) ) {
            return new \WP_Error( 'no_image', 'No image was provided.', [ 'status' => 400 ] );
        }

        $file = $files['relovit_image'];

        // Since the JS sends a blob, we handle it with wp_upload_bits.
        // The file content is in 'tmp_name' for REST API file uploads.
        $upload = wp_upload_bits( $file['name'], null, file_get_contents( $file['tmp_name'] ) );

        if ( ! empty( $upload['error'] ) ) {
            return new \WP_Error( 'upload_error', $upload['error'], [ 'status' => 500 ] );
        }

        // Create the attachment.
        $attachment = [
            'post_mime_type' => $upload['type'],
            'post_title'     => sanitize_file_name( $upload['file'] ),
            'post_content'   => '',
            'post_status'    => 'inherit',
        ];

        $attachment_id = wp_insert_attachment( $attachment, $upload['file'] );

        if ( is_wp_error( $attachment_id ) ) {
            return new \WP_Error( 'attachment_error', $attachment_id->get_error_message(), [ 'status' => 500 ] );
        }

        // Generate attachment metadata.
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $attachment_data = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
        wp_update_attachment_metadata( $attachment_id, $attachment_data );

        $image_path = get_attached_file( $attachment_id );

        $gemini_api = new Gemini_API();
        $result     = $gemini_api->identify_objects( $image_path );

        if ( is_wp_error( $result ) ) {
            // Delete the attachment if the API call fails.
            wp_delete_attachment( $attachment_id, true );
            return $result;
        }

        // The attachment ID is now passed back to the client to be included in the next request.
        return new \WP_REST_Response( [ 'success' => true, 'data' => [ 'items' => $result, 'attachment_id' => $attachment_id ] ], 200 );
    }

    /**
     * Create draft products.
     *
     * @param \WP_REST_Request $request Full data about the request.
     * @return \WP_REST_Response|\WP_Error
     */
    public function create_products( $request ) {
        $items = $request->get_param( 'items' );
        if ( empty( $items ) ) {
            return new \WP_Error( 'no_items', 'No items were selected.', [ 'status' => 400 ] );
        }

        $image_id = $request->get_param( 'attachment_id' );
        if ( ! $image_id || ! is_numeric( $image_id ) ) {
            return new \WP_Error( 'no_image_id', 'Could not find the original image ID. Please try again.', [ 'status' => 400 ] );
        }
        // Verify the attachment exists and is an image.
        if ( ! wp_get_attachment_url( $image_id ) ) {
            return new \WP_Error( 'invalid_image_id', 'The provided image ID is not valid.', [ 'status' => 400 ] );
        }


        $product_manager  = new Product_Manager();
        $created_products = $product_manager->create_draft_products( $items, $image_id );

        // If only one product was created, attach the image to it.
        if ( count( $created_products ) === 1 ) {
            wp_update_post(
                [
                    'ID'          => $image_id,
                    'post_parent' => $created_products[0],
                ]
            );
        }

        if ( count( $created_products ) > 0 ) {
            $message = sprintf( _n( '%s product draft created.', '%s product drafts created.', count( $created_products ), 'relovit' ), count( $created_products ) );
            return new \WP_REST_Response( [ 'success' => true, 'data' => [ 'message' => $message ] ], 200 );
        }

        return new \WP_Error( 'create_failed', 'Could not create any product drafts.', [ 'status' => 500 ] );
    }

    /**
     * Check if the user has the required permissions.
     *
     * @return bool|\WP_Error
     */
    public function check_permissions() {
        if ( ! current_user_can( 'upload_files' ) ) {
            return new \WP_Error( 'rest_forbidden', __( 'Sorry, you are not allowed to upload files.', 'relovit' ), [ 'status' => 403 ] );
        }
        if ( ! current_user_can( 'edit_products' ) ) {
            return new \WP_Error( 'rest_forbidden', __( 'Sorry, you are not allowed to create products.', 'relovit' ), [ 'status' => 403 ] );
        }
        return true;
    }

    /**
     * Enrich a product with AI-generated content.
     *
     * @param \WP_REST_Request $request Full data about the request.
     * @return \WP_REST_Response|\WP_Error
     */
    public function enrich_product( $request ) {
        $product_id = $request->get_param( 'product_id' );
        $files      = $request->get_file_params();

        if ( empty( $product_id ) ) {
            return new \WP_Error( 'no_product_id', 'No product ID was provided.', [ 'status' => 400 ] );
        }

        $tasks = $request->get_param( 'relovit_tasks' ) ?: [];
        if ( empty( $tasks ) ) {
            return new \WP_Error( 'no_tasks', 'Please select at least one task to perform.', [ 'status' => 400 ] );
        }

        // Handle file uploads.
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $attachment_ids = [];
        $image_paths    = [];

        $attachment_ids = [];
        $image_paths    = [];

        if ( ! empty( $files['relovit_images'] ) ) {
            $upload_overrides = [ 'test_form' => false ];

            foreach ( $files['relovit_images']['tmp_name'] as $key => $tmp_name ) {
                if ( empty( $tmp_name ) ) {
                    continue;
                }
                $uploaded_file = [
                    'name'     => $files['relovit_images']['name'][ $key ],
                    'type'     => $files['relovit_images']['type'][ $key ],
                    'tmp_name' => $tmp_name,
                    'error'    => $files['relovit_images']['error'][ $key ],
                    'size'     => $files['relovit_images']['size'][ $key ],
                ];

                $movefile = wp_handle_upload( $uploaded_file, $upload_overrides );

                if ( $movefile && ! isset( $movefile['error'] ) ) {
                    $attachment = [
                        'guid'           => $movefile['url'],
                        'post_mime_type' => $movefile['type'],
                        'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $movefile['file'] ) ),
                        'post_content'   => '',
                        'post_status'    => 'inherit',
                    ];

                    $attachment_id = wp_insert_attachment( $attachment, $movefile['file'], $product_id );
                    require_once ABSPATH . 'wp-admin/includes/image.php';
                    $attachment_data = wp_generate_attachment_metadata( $attachment_id, $movefile['file'] );
                    wp_update_attachment_metadata( $attachment_id, $attachment_data );

                    $attachment_ids[] = $attachment_id;
                    $image_paths[]    = $movefile['file'];
                } else {
                    return new \WP_Error( 'upload_error', $movefile['error'], [ 'status' => 500 ] );
                }
            }
        }

        // Get the main product image as well.
        $main_image_id = get_post_thumbnail_id( $product_id );
        if ( $main_image_id ) {
            $image_paths[] = get_attached_file( $main_image_id );
        }

        // Get gallery images.
        $product = wc_get_product( $product_id );
        $gallery_image_ids = $product->get_gallery_image_ids();
        foreach ( $gallery_image_ids as $gallery_image_id ) {
            $image_paths[] = get_attached_file( $gallery_image_id );
        }
        $image_paths = array_unique( $image_paths );

        if ( empty( $image_paths ) ) {
            return new \WP_Error( 'no_images_found', 'No images found for this product. Please upload at least one.', [ 'status' => 400 ] );
        }

        if ( ! $product ) {
            return new \WP_Error( 'product_not_found', 'The specified product could not be found.', [ 'status' => 404 ] );
        }

        $product_name = $product->get_name();
        $gemini_api   = new Gemini_API();

        $tasks = $request->get_param( 'relovit_tasks' ) ?: [];

        if ( in_array( 'description', $tasks, true ) ) {
            $description = $gemini_api->generate_description( $product_name, $image_paths );
            if ( is_wp_error( $description ) ) {
                return $description;
            }
            $product->set_description( $description );
        }

        if ( in_array( 'price', $tasks, true ) ) {
            $price = $gemini_api->generate_price( $product_name, $image_paths );
            if ( is_wp_error( $price ) ) {
                return $price;
            }
            $product->set_regular_price( floatval( $price ) );
        }

        if ( in_array( 'category', $tasks, true ) ) {
            $category_slug = $gemini_api->generate_category( $product_name, $image_paths );
            if ( ! is_wp_error( $category_slug ) ) {
                $term = get_term_by( 'slug', $category_slug, 'product_cat' );
                if ( $term ) {
                    $product->set_category_ids( [ $term->term_id ] );
                }
            }
        }

        if ( in_array( 'image', $tasks, true ) && ! empty( $image_paths ) ) {
            $generated_image_data = $gemini_api->generate_image( $image_paths[0] );

            if ( is_wp_error( $generated_image_data ) ) {
                return $generated_image_data;
            }

            // Upload the generated image from base64 data.
            $upload = wp_upload_bits( 'generated-image.png', null, base64_decode( $generated_image_data ) );
            if ( ! empty( $upload['error'] ) ) {
                return new \WP_Error( 'image_gen_upload_failed', $upload['error'], [ 'status' => 500 ] );
            }

            $attachment = [
                'post_mime_type' => 'image/png',
                'post_title'     => $product_name . ' - AI Generated',
                'post_content'   => '',
                'post_status'    => 'inherit',
            ];
            $new_attachment_id = wp_insert_attachment( $attachment, $upload['file'], $product_id );
            if ( is_wp_error( $new_attachment_id ) ) {
                return $new_attachment_id;
            }

            require_once ABSPATH . 'wp-admin/includes/image.php';
            $attachment_data = wp_generate_attachment_metadata( $new_attachment_id, $upload['file'] );
            wp_update_attachment_metadata( $new_attachment_id, $attachment_data );
            $product->set_image_id( $new_attachment_id );
        }

        // Update the product.
        $existing_gallery_ids = $product->get_gallery_image_ids();
        $new_gallery_ids      = array_unique( array_merge( $existing_gallery_ids, $attachment_ids ) );
        $product->set_gallery_image_ids( $new_gallery_ids );
        $product->set_status( 'pending' );
        $result = $product->save();

        if ( is_wp_error( $result ) || $result === 0 ) {
            return new \WP_Error( 'product_save_failed', __( 'Could not save the product after enrichment.', 'relovit' ), [ 'status' => 500 ] );
        }

        return new \WP_REST_Response( [ 'success' => true, 'data' => [ 'message' => __( 'Product enriched successfully!', 'relovit' ) ] ], 200 );
    }
}