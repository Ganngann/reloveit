<?php
/**
 * Frontend handler class.
 *
 * @package Relovit
 */

namespace Relovit;

/**
 * Class Frontend
 *
 * @package Relovit
 */
class Frontend {

    /**
     * Frontend constructor.
     */
    public function __construct() {
        add_action( 'init', [ $this, 'register_shortcodes' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
    }

    /**
     * Register shortcodes.
     */
    public function register_shortcodes() {
        add_shortcode( 'relovit_upload_form', [ $this, 'render_upload_form' ] );
    }

    /**
     * Render the upload form.
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public function render_upload_form( $atts ) {
        ob_start();
        ?>
        <div id="relovit-app">
            <h2>Vendez vos objets en un clin d'œil</h2>
            <p>Téléversez une photo de vos objets et laissez notre IA faire le reste !</p>
            <form id="relovit-upload-form" enctype="multipart/form-data">
                <input type="file" id="relovit-image-upload" name="relovit_image" accept="image/*" capture="environment" required>
                <button type="submit">Identifier les objets</button>
            </form>
            <div id="relovit-results"></div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Enqueue scripts and styles.
     */
    public function enqueue_scripts() {
        global $post;
        if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'relovit_upload_form' ) ) {
            wp_enqueue_script(
                'relovit-upload-form',
                RELOVIT_PLUGIN_URL . 'assets/js/upload-form.js',
                [ 'jquery' ],
                RELOVIT_VERSION,
                true
            );

            wp_localize_script(
                'relovit-upload-form',
                'relovit_ajax',
                [
                    'identify_url' => rest_url( 'relovit/v1/identify-objects' ),
                    'create_url'   => rest_url( 'relovit/v1/create-products' ),
                    'nonce'        => wp_create_nonce( 'wp_rest' ),
                ]
            );
        }
    }
}