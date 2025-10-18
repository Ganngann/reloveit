<?php
/**
 * Plugin Name:       Relovit
 * Plugin URI:        https://gann.be/
 * Description:       Utilise l'IA (Gemini) pour identifier des objets dans une image et créer des fiches produits dans WooCommerce.
 * Version:           1.0.3
 * Author:            Morgan Schaefer
 * Author URI:        https://gann.be/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       relovit
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

define( 'RELOVIT_VERSION', '1.0.3' );
define( 'RELOVIT_PLUGIN_FILE', __FILE__ );

// Include the main plugin class.
require_once plugin_dir_path( __FILE__ ) . 'includes/Plugin.php';


/**
 * The code that runs during plugin activation.
 * This action is only scheduled once.
 */
function relovit_activate() {
    // Register the custom endpoints.
    add_rewrite_endpoint( 'relovit-settings', EP_PAGES );
    add_rewrite_endpoint( 'relovit-products', EP_PAGES );
    // Flush the rewrite rules.
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'relovit_activate' );

/**
 * The code that runs during plugin deactivation.
 */
function relovit_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'relovit_deactivate' );


/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_relovit() {
    return \Relovit\Plugin::instance();
}

run_relovit();