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

        // Handle the file upload using WordPress functions.
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $attachment_id = media_handle_upload( 'relovit_image', 0 );

        if ( is_wp_error( $attachment_id ) ) {
            return new \WP_Error( 'upload_error', $attachment_id->get_error_message(), [ 'status' => 500 ] );
        }

        $image_path = get_attached_file( $attachment_id );

        $gemini_api = new Gemini_API();
        $result     = $gemini_api->identify_objects( $image_path );

        if ( is_wp_error( $result ) ) {
            // Delete the attachment if the API call fails.
            wp_delete_attachment( $attachment_id, true );
            return $result;
        }

        // Store the attachment ID in a transient for the next step.
        set_transient( 'relovit_image_id_' . get_current_user_id(), $attachment_id, HOUR_IN_SECONDS );

        return new \WP_REST_Response( [ 'success' => true, 'data' => [ 'items' => $result ] ], 200 );
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

        $image_id = get_transient( 'relovit_image_id_' . get_current_user_id() );
        if ( ! $image_id ) {
            return new \WP_Error( 'no_image_id', 'Could not find the original image. Please try again.', [ 'status' => 400 ] );
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

        // Delete the transient.
        delete_transient( 'relovit_image_id_' . get_current_user_id() );

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

        if ( empty( $files['relovit_images'] ) ) {
            return new \WP_Error( 'no_images', 'No images were provided.', [ 'status' => 400 ] );
        }

        // Handle file uploads.
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $attachment_ids = [];
        $image_paths    = [];

        $attachment_ids = [];
        $image_paths    = [];
        $upload_overrides = [ 'test_form' => false ];

        foreach ( $files['relovit_images']['tmp_name'] as $key => $tmp_name ) {
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

        // Get the main product image as well.
        $main_image_id = get_post_thumbnail_id( $product_id );
        if ( $main_image_id ) {
            array_unshift( $image_paths, get_attached_file( $main_image_id ) );
        }

        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            return new \WP_Error( 'product_not_found', 'The specified product could not be found.', [ 'status' => 404 ] );
        }

        $product_name = $product->get_name();
        $gemini_api   = new Gemini_API();

        // Generate description.
        $description = $gemini_api->generate_description( $product_name, $image_paths );
        if ( is_wp_error( $description ) ) {
            return $description;
        }

        // Generate price.
        $price = $gemini_api->generate_price( $product_name, $image_paths );
        if ( is_wp_error( $price ) ) {
            return $price;
        }

        // Generate category.
        $category_slug = $gemini_api->generate_category( $product_name, $image_paths );
        if ( ! is_wp_error( $category_slug ) ) {
            $term = get_term_by( 'slug', $category_slug, 'product_cat' );
            if ( $term ) {
                $product->set_category_ids( [ $term->term_id ] );
            }
        }

        $generate_image = $request->get_param( 'relovit_generate_image' );

        if ( $generate_image && ! empty( $image_paths ) ) {
            $generated_image_data = $gemini_api->generate_image( $image_paths[0] );

            if ( ! is_wp_error( $generated_image_data ) ) {
                // Upload the generated image from base64 data.
                $upload = wp_upload_bits( 'generated-image.png', null, base64_decode( $generated_image_data ) );
                if ( ! $upload['error'] ) {
                    $attachment = [
                        'post_mime_type' => 'image/png',
                        'post_title'     => $product_name . ' - AI Generated',
                        'post_content'   => '',
                        'post_status'    => 'inherit',
                    ];
                    $new_attachment_id = wp_insert_attachment( $attachment, $upload['file'], $product_id );
                    if ( ! is_wp_error( $new_attachment_id ) ) {
                        require_once ABSPATH . 'wp-admin/includes/image.php';
                        $attachment_data = wp_generate_attachment_metadata( $new_attachment_id, $upload['file'] );
                        wp_update_attachment_metadata( $new_attachment_id, $attachment_data );
                        $product->set_image_id( $new_attachment_id );
                    }
                }
            }
        }

        // Update the product.
        $product->set_description( $description );
        $product->set_regular_price( floatval( $price ) );
        $product->set_gallery_image_ids( $attachment_ids );
        $product->set_status( 'pending' );
        $result = $product->save();

        if ( is_wp_error( $result ) || $result === 0 ) {
            return new \WP_Error( 'product_save_failed', __( 'Could not save the product after enrichment.', 'relovit' ), [ 'status' => 500 ] );
        }

        return new \WP_REST_Response( [ 'success' => true, 'data' => [ 'message' => __( 'Product enriched successfully!', 'relovit' ) ] ], 200 );
    }
}