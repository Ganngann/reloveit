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

    private $settings;

    /**
     * Admin constructor.
     */
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'settings_init' ] );
        add_filter( 'plugin_action_links_' . plugin_basename( RELOVIT_PLUGIN_FILE ), [ $this, 'add_settings_link' ] );

        // Load settings with defaults.
        $this->settings = get_option( 'relovit_settings', [] );
    }

    /**
     * Get a setting value, falling back to default if not set.
     */
    private function get_setting( $key ) {
        return isset( $this->settings[ $key ] ) ? $this->settings[ $key ] : Settings::get_defaults()[ $key ];
    }

    /**
     * Add a settings link to the plugin page.
     */
    public function add_settings_link( $links ) {
        $settings_link = '<a href="' . admin_url( 'options-general.php?page=relovit' ) . '">' . __( 'Settings', 'relovit' ) . '</a>';
        array_unshift( $links, $settings_link );
        return $links;
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
        register_setting( 'relovit_settings', 'relovit_settings', [ $this, 'sanitize_settings' ] );

        // General Section
        add_settings_section( 'relovit_general_section', __( 'General Settings', 'relovit' ), null, 'relovit_settings' );
        $this->add_field( 'gemini_api_key', __( 'Gemini API Key', 'relovit' ), 'render_text_field', 'relovit_general_section', [ 'size' => 50 ] );
        $this->add_field( 'language', __( 'Language', 'relovit' ), 'render_text_field', 'relovit_general_section', [ 'size' => 20, 'description' => __( 'e.g., "français", "english"', 'relovit' ) ] );
        $this->add_field( 'store_context', __( 'Store Context', 'relovit' ), 'render_textarea_field', 'relovit_general_section', [ 'rows' => 4, 'description' => __( 'A brief description of your store to give context to the AI.', 'relovit' ) ] );

        // AI Customization Section
        add_settings_section( 'relovit_ai_section', __( 'AI Customization', 'relovit' ), null, 'relovit_settings' );
        $this->add_field( 'price_range', __( 'Price Strategy', 'relovit' ), 'render_select_field', 'relovit_ai_section', [ 'options' => Settings::get_price_ranges(), 'description' => __( 'Guide the AI on how to position the price.', 'relovit' ) ] );
        $this->add_field( 'desc_tone', __( 'Description Tone', 'relovit' ), 'render_text_field', 'relovit_ai_section', [ 'description' => __( 'e.g., "détaillée, honnête et commerciale"', 'relovit' ) ] );
        $this->add_field( 'desc_keywords', __( 'Description Keywords', 'relovit' ), 'render_textarea_field', 'relovit_ai_section', [ 'rows' => 2, 'description' => __( 'e.g., "insistant sur son état et sa valeur"', 'relovit' ) ] );
        $this->add_field( 'taxonomy_seo_focus', __( 'Tags/Categories Focus', 'relovit' ), 'render_text_field', 'relovit_ai_section', [ 'description' => __( 'e.g., "pertinents pour le SEO"', 'relovit' ) ] );
        $this->add_field( 'image_bg_style', __( 'Generated Image Background', 'relovit' ), 'render_text_field', 'relovit_ai_section', [ 'description' => __( 'e.g., "sur un fond de studio blanc et propre"', 'relovit' ) ] );
    }

    /**
     * Helper to add a settings field.
     */
    private function add_field( $id, $title, $callback, $section, $args = [] ) {
        add_settings_field(
            $id, // Use the key directly as ID
            $title,
            [ $this, $callback ],
            'relovit_settings',
            $section,
            array_merge( [ 'id' => $id ], $args )
        );
    }

    /**
     * Render a text input field.
     */
    public function render_text_field( $args ) {
        $id = $args['id'];
        $value = esc_attr( $this->get_setting( $id ) );
        $size = isset( $args['size'] ) ? $args['size'] : 40;
        echo "<input type='text' name='relovit_settings[{$id}]' value='{$value}' class='regular-text'>";
        if ( ! empty( $args['description'] ) ) {
            echo "<p class='description'>" . esc_html( $args['description'] ) . "</p>";
        }
    }

    /**
     * Render a textarea field.
     */
    public function render_textarea_field( $args ) {
        $id = $args['id'];
        $value = esc_textarea( $this->get_setting( $id ) );
        $rows = isset( $args['rows'] ) ? $args['rows'] : 4;
        echo "<textarea name='relovit_settings[{$id}]' rows='{$rows}' class='large-text'>{$value}</textarea>";
        if ( ! empty( $args['description'] ) ) {
            echo "<p class='description'>" . esc_html( $args['description'] ) . "</p>";
        }
    }

    /**
     * Render a select dropdown field.
     */
    public function render_select_field( $args ) {
        $id = $args['id'];
        $current_value = $this->get_setting( $id );
        $options = $args['options'];

        echo "<select name='relovit_settings[{$id}]'>";
        foreach ( $options as $value => $label ) {
            echo "<option value='" . esc_attr( $value ) . "' " . selected( $current_value, $value, false ) . ">" . esc_html( $label ) . "</option>";
        }
        echo "</select>";

        if ( ! empty( $args['description'] ) ) {
            echo "<p class='description'>" . esc_html( $args['description'] ) . "</p>";
        }
    }

    /**
     * Sanitize settings.
     */
    public function sanitize_settings( $input ) {
        $new_input = [];
        $defaults = Settings::get_defaults();

        foreach ( $defaults as $key => $value ) {
            if ( ! isset( $input[ $key ] ) ) {
                continue;
            }

            if ( $key === 'price_range' ) {
                // Ensure the value is one of the allowed keys.
                $new_input[ $key ] = array_key_exists( $input[ $key ], Settings::get_price_ranges() ) ? $input[ $key ] : $defaults['price_range'];
            } elseif ( strpos( $key, 'desc_' ) === 0 || $key === 'store_context' ) {
                $new_input[ $key ] = sanitize_textarea_field( $input[ $key ] );
            } else {
                $new_input[ $key ] = sanitize_text_field( $input[ $key ] );
            }
        }

        return $new_input;
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