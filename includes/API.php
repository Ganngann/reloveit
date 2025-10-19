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
     * Check if the user has the required permissions for creating products.
     *
     * @return bool|\WP_Error
     */
    public function check_creation_permissions() {
        if ( ! current_user_can( 'upload_files' ) ) {
            return new \WP_Error( 'rest_forbidden', __( 'Sorry, you are not allowed to upload files.', 'relovit' ), [ 'status' => 403 ] );
        }
        if ( ! current_user_can( 'edit_products' ) ) {
            return new \WP_Error( 'rest_forbidden', __( 'Sorry, you are not allowed to create products.', 'relovit' ), [ 'status' => 403 ] );
        }
        return true;
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
                'permission_callback' => [ $this, 'check_creation_permissions' ],
            ]
        );

        register_rest_route(
            'relovit/v1',
            '/create-products',
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'create_products' ],
                'permission_callback' => [ $this, 'check_creation_permissions' ],
            ]
        );

        register_rest_route(
            'relovit/v1',
            '/enrich-product',
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'enrich_product' ],
                'permission_callback' => [ $this, 'check_enrich_permission' ],
            ]
        );

        register_rest_route(
            'relovit/v1',
            '/products/(?P<id>\d+)',
            [
                'methods'             => \WP_REST_Server::DELETABLE,
                'callback'            => [ $this, 'delete_product' ],
                'permission_callback' => [ $this, 'check_product_permission' ],
                'args'                => [
                    'id' => [
                        'validate_callback' => function( $param, $request, $key ) {
                            return is_numeric( $param );
                        }
                    ],
                ],
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
     * Check if the user has the required permissions for a product.
     *
     * @param \WP_REST_Request $request
     * @return bool|\WP_Error
     */
    public function check_product_permission( $request ) {
        if ( ! is_user_logged_in() ) {
            return new \WP_Error( 'rest_not_logged_in', __( 'You are not currently logged in.', 'relovit' ), [ 'status' => 401 ] );
        }

        $product_id = $request->get_param( 'id' );

        if ( ! $product_id ) {
            return new \WP_Error( 'rest_product_invalid_id', __( 'Invalid product ID.', 'relovit' ), [ 'status' => 404 ] );
        }

        $product = get_post( $product_id );
        if ( ! $product || 'product' !== $product->post_type ) {
            return new \WP_Error( 'rest_product_invalid_id', __( 'Invalid product ID.', 'relovit' ), [ 'status' => 404 ] );
        }

        if ( get_current_user_id() != $product->post_author ) {
            return new \WP_Error( 'rest_forbidden_context', __( 'Sorry, you are not allowed to access this product.', 'relovit' ), [ 'status' => 403 ] );
        }

        return true;
    }

    /**
     * Check if the user has the required permissions for enriching a product.
     *
     * @param \WP_REST_Request $request
     * @return bool|\WP_Error
     */
    public function check_enrich_permission( $request ) {
        if ( ! is_user_logged_in() ) {
            return new \WP_Error( 'rest_not_logged_in', __( 'You are not currently logged in.', 'relovit' ), [ 'status' => 401 ] );
        }

        $product_id = $request->get_param('product_id');

        if ( ! $product_id ) {
            return new \WP_Error( 'rest_product_invalid_id', __( 'Invalid product ID.', 'relovit' ), [ 'status' => 404 ] );
        }

        $product = get_post( $product_id );
        if ( ! $product || 'product' !== $product->post_type ) {
            return new \WP_Error( 'rest_product_invalid_id', __( 'Invalid product ID.', 'relovit' ), [ 'status' => 404 ] );
        }

        if ( get_current_user_id() != $product->post_author ) {
            return new \WP_Error( 'rest_forbidden_context', __( 'Sorry, you are not allowed to access this product.', 'relovit' ), [ 'status' => 403 ] );
        }

        return true;
    }

     /**
     * Delete a product.
     *
     * @param \WP_REST_Request $request Full data about the request.
     * @return \WP_REST_Response|\WP_Error
     */
    public function delete_product( $request ) {
        $product_id = $request->get_param( 'id' );
        $product    = wc_get_product( $product_id );

        if ( ! $product ) {
            return new \WP_Error( 'product_not_found', 'The specified product could not be found.', [ 'status' => 404 ] );
        }

        // Use 'true' to bypass trash and permanently delete.
        $result = wp_delete_post( $product_id, true );

        if ( ! $result ) {
            return new \WP_Error( 'delete_failed', 'Could not delete the product.', [ 'status' => 500 ] );
        }

        return new \WP_REST_Response( [ 'success' => true, 'data' => [ 'message' => 'Product deleted successfully.' ] ], 200 );
    }

    public function enrich_product( $request ) {
        $product_id = $request->get_param( 'product_id' );
        $tasks      = $request->get_param( 'relovit_tasks' );

        if ( empty( $product_id ) ) {
            return new \WP_Error( 'no_product_id', 'No product ID was provided.', [ 'status' => 400 ] );
        }

        if ( empty( $tasks ) ) {
            return new \WP_Error( 'no_tasks', 'Please select at least one task to perform.', [ 'status' => 400 ] );
        }

        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            return new \WP_Error( 'product_not_found', 'The specified product could not be found.', [ 'status' => 404 ] );
        }


        // Handle image uploads
        if ( ! empty( $_FILES['relovit_main_image'] ) && ! empty( $_FILES['relovit_main_image']['name'] ) ) {
            require_once( ABSPATH . 'wp-admin/includes/image.php' );
            require_once( ABSPATH . 'wp-admin/includes/file.php' );
            require_once( ABSPATH . 'wp-admin/includes/media.php' );
            $attachment_id = media_handle_upload( 'relovit_main_image', $product_id );
            if ( ! is_wp_error( $attachment_id ) ) {
                $product->set_image_id( $attachment_id );
            }
        }
        if ( ! empty( $_FILES['relovit_gallery_images'] ) && ! empty( $_FILES['relovit_gallery_images']['name'][0] ) ) {
            require_once( ABSPATH . 'wp-admin/includes/image.php' );
            require_once( ABSPATH . 'wp-admin/includes/file.php' );
            require_once( ABSPATH . 'wp-admin/includes/media.php' );
            $files = $_FILES['relovit_gallery_images'];
            $gallery_ids = $product->get_gallery_image_ids();
            foreach ( $files['name'] as $key => $value ) {
                if ( $files['name'][ $key ] ) {
                    $file = array(
                        'name'     => $files['name'][ $key ],
                        'type'     => $files['type'][ $key ],
                        'tmp_name' => $files['tmp_name'][ $key ],
                        'error'    => $files['error'][ $key ],
                        'size'     => $files['size'][ $key ]
                    );
                    $_FILES = array( 'relovit_gallery_image' => $file );
                    $attachment_id = media_handle_upload( 'relovit_gallery_image', $product_id );
                    if ( ! is_wp_error( $attachment_id ) ) {
                        $gallery_ids[] = $attachment_id;
                    }
                }
            }
            $product->set_gallery_image_ids( $gallery_ids );
        }

        // Get all image paths associated with the product.
        $image_paths = [];
        $main_image_id = get_post_thumbnail_id( $product_id );
        if ( $main_image_id ) {
            $image_paths[] = get_attached_file( $main_image_id );
        }
        $gallery_image_ids = $product->get_gallery_image_ids();
        foreach ( $gallery_image_ids as $gallery_image_id ) {
            $image_paths[] = get_attached_file( $gallery_image_id );
        }
        $image_paths = array_unique( $image_paths );

        if ( empty( $image_paths ) ) {
            return new \WP_Error( 'no_images_found', 'No images found for this product. Please upload at least one.', [ 'status' => 400 ] );
        }

        $product_name = $product->get_name();
        $gemini_api   = new Gemini_API();

        if ( in_array( 'description', $tasks, true ) ) {
            $description = $gemini_api->generate_description( $product_name, $image_paths );
            if ( ! is_wp_error( $description ) ) {
                $product->set_description( $description );
            }
        }

        if ( in_array( 'price', $tasks, true ) ) {
            $user_id = get_current_user_id();
            $price_range = get_user_meta( $user_id, 'relovit_price_range', true );
            $price = $gemini_api->generate_price( $product_name, $image_paths, $price_range ?: 'Moyen' );
            if ( ! is_wp_error( $price ) ) {
                $product->set_regular_price( floatval( $price ) );
            }
        }

        if ( in_array( 'category', $tasks, true ) ) {
            $taxonomy_terms = $gemini_api->generate_taxonomy_terms( $product_name, $image_paths );
            if ( ! is_wp_error( $taxonomy_terms ) && ! empty( $taxonomy_terms ) ) {
                if ( ! empty( $taxonomy_terms['category'] ) ) {
                    $category_id = $this->find_or_create_term_path( $taxonomy_terms['category'], 'product_cat' );
                    if ( $category_id ) {
                        $product->set_category_ids( [ $category_id ] );
                    }
                }
                if ( ! empty( $taxonomy_terms['tags'] ) ) {
                    wp_set_post_terms( $product_id, $taxonomy_terms['tags'], 'product_tag', false );
                }
            }
        }

        if ( in_array( 'image', $tasks, true ) ) {
            $generated_image_data = $gemini_api->generate_image( $image_paths[0] );
            if ( ! is_wp_error( $generated_image_data ) ) {
                $upload = wp_upload_bits( 'generated-image.png', null, base64_decode( $generated_image_data ) );
                if ( empty( $upload['error'] ) ) {
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

        $product->set_status( 'pending' );
        $product->save();

        $tag_terms = wp_get_post_terms( $product->get_id(), 'product_tag', [ 'fields' => 'names' ] );

        $product_data = [
            'description' => $product->get_description(),
            'price'       => $product->get_regular_price(),
            'category_id' => $product->get_category_ids()[0] ?? null,
            'image_id'    => $product->get_image_id(),
            'tags'        => $tag_terms,
        ];

        return new \WP_REST_Response(
            [
                'success' => true,
                'data'    => [
                    'message' => __( 'Product enriched successfully!', 'relovit' ),
                    'product' => $product_data,
                ],
            ],
            200
        );
    }

    /**
     * Finds or creates a term and its parents, returning the final term's ID.
     *
     * @param array  $term_path An array of term names, from parent to child.
     * @param string $taxonomy  The taxonomy to use.
     * @return int|null The ID of the final term, or null on failure.
     */
    private function find_or_create_term_path( $term_path, $taxonomy ) {
        $parent_id = 0;
        $final_term_id = null;

        foreach ( $term_path as $term_name ) {
            $term_name = trim( $term_name );
            if ( empty( $term_name ) ) {
                continue;
            }

            // Look for the term with the correct parent.
            $term = get_term_by( 'name', $term_name, $taxonomy, OBJECT, 'raw', [ 'parent' => $parent_id ] );

            if ( ! $term ) {
                // If it doesn't exist, create it.
                $new_term = wp_insert_term( $term_name, $taxonomy, [ 'parent' => $parent_id ] );
                if ( is_wp_error( $new_term ) ) {
                    // Log error or handle it. For now, we stop.
                    return null;
                }
                $final_term_id = $new_term['term_id'];
            } else {
                $final_term_id = $term->term_id;
            }
            // The current term becomes the parent for the next iteration.
            $parent_id = $final_term_id;
        }

        return $final_term_id;
    }
}