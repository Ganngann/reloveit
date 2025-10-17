<?php
/**
 * Admin handler class.
 *
 * @package Relovit
 */

namespace Relovit;

/**
 * Class Admin
 *
 * @package Relovit
 */
class Admin {

    /**
     * Admin constructor.
     */
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'settings_init' ] );
    }

    /**
     * Add the admin menu page.
     */
    public function add_admin_menu() {
        add_options_page(
            'Relovit Settings',
            'Relovit',
            'manage_options',
            'relovit',
            [ $this, 'render_settings_page' ]
        );
    }

    /**
     * Initialize the settings.
     */
    public function settings_init() {
        register_setting( 'relovit_settings', 'relovit_gemini_api_key' );

        add_settings_section(
            'relovit_settings_section',
            __( 'API Settings', 'relovit' ),
            null,
            'relovit_settings'
        );

        add_settings_field(
            'relovit_gemini_api_key',
            __( 'Gemini API Key', 'relovit' ),
            [ $this, 'render_api_key_field' ],
            'relovit_settings',
            'relovit_settings_section'
        );
    }

    /**
     * Render the API key field.
     */
    public function render_api_key_field() {
        $api_key = get_option( 'relovit_gemini_api_key' );
        ?>
        <input type="text" name="relovit_gemini_api_key" value="<?php echo esc_attr( $api_key ); ?>" size="50">
        <?php
    }

    /**
     * Render the settings page.
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields( 'relovit_settings' );
                do_settings_sections( 'relovit_settings' );
                submit_button( __( 'Save Settings', 'relovit' ) );
                ?>
            </form>
        </div>
        <?php
    }
}