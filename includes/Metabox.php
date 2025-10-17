<?php
/**
 * Metabox handler class.
 *
 * @package Relovit
 */

namespace Relovit;

/**
 * Class Metabox
 *
 * @package Relovit
 */
class Metabox {

    /**
     * Metabox constructor.
     */
    public function __construct() {
        add_action( 'add_meta_boxes', [ $this, 'add_relovit_metabox' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
    }

    /**
     * Add the metabox to the product edit screen.
     */
    public function add_relovit_metabox() {
        global $post;
        if ( get_post_meta( $post->ID, '_relovit_product', true ) ) {
            add_meta_box(
                'relovit_enrichment',
                __( 'Relovit AI Enrichment', 'relovit' ),
                [ $this, 'render_metabox_content' ],
                'product',
                'normal',
                'high'
            );
        }
    }

    /**
     * Render the content of the metabox.
     *
     * @param \WP_Post $post The post object.
     */
    public function render_metabox_content( $post ) {
        ?>
        <div id="relovit-enrichment-app">
            <p><?php _e( 'Upload up to 3 additional images of the product to generate a description and price with AI.', 'relovit' ); ?></p>
            <form id="relovit-enrichment-form">
                <input type="hidden" name="product_id" value="<?php echo esc_attr( $post->ID ); ?>">
                <p>
                    <label for="relovit_images"><?php _e( 'Additional Images:', 'relovit' ); ?></label>
                    <input type="file" id="relovit-images" name="relovit_images[]" accept="image/*" multiple max="3">
                </p>
                <button type="button" id="relovit-enrich-button" class="button button-primary"><?php _e( 'Enrich with AI', 'relovit' ); ?></button>
            </form>
            <div id="relovit-enrichment-results" style="margin-top: 15px;"></div>
        </div>
        <?php
    }

    /**
     * Enqueue scripts and styles for the metabox.
     *
     * @param string $hook The current admin page.
     */
    public function enqueue_scripts( $hook ) {
        global $post;
        if ( 'post.php' === $hook && 'product' === $post->post_type && get_post_meta( $post->ID, '_relovit_product', true ) ) {
            wp_enqueue_script(
                'relovit-enrichment-form',
                RELOVIT_PLUGIN_URL . 'assets/js/enrichment-form.js',
                [ 'jquery' ],
                RELOVIT_VERSION,
                true
            );

            wp_localize_script(
                'relovit-enrichment-form',
                'relovit_ajax',
                [
                    'enrich_url' => rest_url( 'relovit/v1/enrich-product' ),
                    'nonce'      => wp_create_nonce( 'wp_rest' ),
                ]
            );
        }
    }
}