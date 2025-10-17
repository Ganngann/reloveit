<?php
/**
 * Frontend handler class.
 *
 * @package WPOccasionAI
 */

namespace WPOccasionAI;

/**
 * Class Frontend
 *
 * @package WPOccasionAI
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
        add_shortcode( 'wp_occasion_ai_upload_form', [ $this, 'render_upload_form' ] );
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
        <div id="wp-occasion-ai-app">
            <h2>Vendez vos objets en un clin d'œil</h2>
            <p>Téléversez une photo de vos objets et laissez notre IA faire le reste !</p>
            <form id="wp-occasion-ai-upload-form" enctype="multipart/form-data">
                <input type="file" id="wp-occasion-ai-image-upload" name="wp_occasion_ai_image" accept="image/*" required>
                <button type="submit">Identifier les objets</button>
            </form>
            <div id="wp-occasion-ai-results"></div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Enqueue scripts and styles.
     */
    public function enqueue_scripts() {
        global $post;
        if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'wp_occasion_ai_upload_form' ) ) {
            wp_enqueue_script(
                'wp-occasion-ai-upload-form',
                WP_OCCASION_AI_PLUGIN_URL . 'assets/js/upload-form.js',
                [ 'jquery' ],
                WP_OCCASION_AI_VERSION,
                true
            );
        }
    }
}