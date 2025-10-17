<?php
/**
 * Main plugin class.
 *
 * @package Relovit
 */

namespace Relovit;

/**
 * Class Plugin
 *
 * @package Relovit
 */
class Plugin {

    /**
     * The single instance of the class.
     *
     * @var Plugin|null
     */
    protected static $instance = null;

    /**
     * Main Plugin instance.
     *
     * Ensures only one instance of the plugin is loaded or can be loaded.
     *
     * @static
     * @return Plugin - Main instance.
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Plugin constructor.
     */
    public function __construct() {
        $this->define_constants();
        $this->includes();
        $this->init_hooks();
    }

    /**
     * Define constants.
     */
    private function define_constants() {
        define( 'RELOVIT_PLUGIN_DIR', plugin_dir_path( dirname( __FILE__ ) ) );
        define( 'RELOVIT_PLUGIN_URL', plugin_dir_url( dirname( __FILE__ ) ) );
    }

    /**
     * Include required files.
     */
    private function includes() {
        require_once RELOVIT_PLUGIN_DIR . 'includes/Frontend.php';
        require_once RELOVIT_PLUGIN_DIR . 'includes/API.php';
        require_once RELOVIT_PLUGIN_DIR . 'includes/Gemini_API.php';
        require_once RELOVIT_PLUGIN_DIR . 'includes/Product_Manager.php';

        if ( is_admin() ) {
            require_once RELOVIT_PLUGIN_DIR . 'includes/Admin.php';
        }
    }

    /**
     * Hook into actions and filters.
     */
    private function init_hooks() {
        new Frontend();
        new API();

        if ( is_admin() ) {
            new Admin();
        }
    }
}