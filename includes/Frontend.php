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
        add_action( 'init', [ $this, 'init_hooks' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
    }

    /**
     * Initialize hooks.
     */
    public function init_hooks() {
        $this->register_shortcodes();

        // WooCommerce hooks for "My Account" page.
        add_filter( 'woocommerce_account_menu_items', [ $this, 'relovit_account_menu_items' ] );
        add_action( 'init', [ $this, 'relovit_add_my_account_endpoint' ] );
        add_filter( 'query_vars', [ $this, 'relovit_add_query_vars' ], 0 );
        add_action( 'woocommerce_account_relovit-settings_endpoint', [ $this, 'relovit_settings_content' ] );
        add_action( 'init', [ $this, 'relovit_save_settings' ] );
        add_action( 'init', [ $this, 'flush_rewrite_rules_on_load' ] );
    }

    /**
     * Save the settings from the "Relovit Settings" page.
     */
    public function relovit_save_settings() {
        if ( ! isset( $_POST['relovit_save_settings'] ) || ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'relovit_save_settings' ) ) {
            return;
        }

        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return;
        }

        if ( isset( $_POST['relovit_price_range'] ) ) {
            $price_range = sanitize_text_field( $_POST['relovit_price_range'] );
            // Optional: Add validation to ensure the value is one of the allowed options.
            update_user_meta( $user_id, 'relovit_price_range', $price_range );
        }
    }

    /**
     * Add "Relovit Settings" to the My Account menu.
     *
     * @param array $items Menu items.
     * @return array
     */
    public function relovit_account_menu_items( $items ) {
        $items['relovit-settings'] = __( 'Relovit Settings', 'relovit' );
        return $items;
    }

    /**
     * Add endpoint for the "Relovit Settings" page.
     */
    public function relovit_add_my_account_endpoint() {
        add_rewrite_endpoint( 'relovit-settings', EP_PAGES );
    }

    /**
     * Add the custom query var to the public query variables.
     *
     * @param array $vars The array of whitelisted query variables.
     * @return array
     */
    public function relovit_add_query_vars( $vars ) {
        $vars[] = 'relovit-settings';
        return $vars;
    }

    /**
     * Flush rewrite rules on plugin update to prevent 404 errors.
     * This runs once to avoid performance issues.
     */
    public function flush_rewrite_rules_on_load() {
        if ( get_option( 'relovit_flush_rewrite_rules_flag' ) ) {
            return;
        }
        flush_rewrite_rules();
        update_option( 'relovit_flush_rewrite_rules_flag', true );
    }

    /**
     * Display the content for the "Relovit Settings" page.
     */
    public function relovit_settings_content() {
        $user_id = get_current_user_id();
        $current_price_range = get_user_meta( $user_id, 'relovit_price_range', true );
        ?>
        <h3><?php esc_html_e( 'Relovit Settings', 'relovit' ); ?></h3>
        <form method="post">
            <?php wp_nonce_field( 'relovit_save_settings' ); ?>
            <p>
                <label for="relovit_price_range"><?php esc_html_e( 'Default Price Range', 'relovit' ); ?></label>
                <select id="relovit_price_range" name="relovit_price_range" class="woocommerce-Input woocommerce-Input--select">
                    <option value="Bon marché" <?php selected( $current_price_range, 'Bon marché' ); ?>><?php esc_html_e( 'Inexpensive', 'relovit' ); ?></option>
                    <option value="Moyen" <?php selected( $current_price_range, 'Moyen' ); ?>><?php esc_html_e( 'Average', 'relovit' ); ?></option>
                    <option value="Cher" <?php selected( $current_price_range, 'Cher' ); ?>><?php esc_html_e( 'Expensive', 'relovit' ); ?></option>
                </select>
            </p>
            <p>
                <button type="submit" class="woocommerce-Button button" name="relovit_save_settings" value="<?php esc_attr_e( 'Save changes', 'relovit' ); ?>"><?php esc_html_e( 'Save changes', 'relovit' ); ?></button>
            </p>
        </form>
        <?php
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
            <div class="relovit-upload-options">
                 <button type="button" id="relovit-capture-btn" class="button">Prendre une photo</button>
                 <button type="button" id="relovit-library-btn" class="button">Choisir dans la photothèque</button>
            </div>
            <form id="relovit-upload-form" enctype="multipart/form-data" style="display: none;">
                <input type="file" id="relovit-image-upload" name="relovit_image" accept="image/*">
            </form>
             <div id="relovit-image-preview-container" style="display:none; text-align: center; margin-top: 15px;">
                <img id="relovit-image-preview" src="" alt="Aperçu de l'image" style="max-width: 100%; max-height: 300px; border: 1px solid #ddd; padding: 5px;"/>
                <button type="submit" form="relovit-upload-form" id="relovit-submit-btn" class="button button-primary" style="margin-top: 10px;">Identifier les objets</button>
             </div>
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