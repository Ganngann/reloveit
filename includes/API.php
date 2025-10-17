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

        $product_manager = new Product_Manager();
        $created_count   = $product_manager->create_draft_products( $items, $image_id );

        // Delete the transient.
        delete_transient( 'relovit_image_id_' . get_current_user_id() );

        if ( $created_count > 0 ) {
            $message = sprintf( _n( '%s product draft created.', '%s product drafts created.', $created_count, 'relovit' ), $created_count );
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
}