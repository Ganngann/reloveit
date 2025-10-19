<?php
/**
 * Plugin Name:       Relovit
 * Plugin URI:        https://gann.be/
 * Description:       Utilise l'IA (Gemini) pour identifier des objets dans une image et créer des fiches produits dans WooCommerce.
 * Version:           1.3.3
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

define( 'RELOVIT_VERSION', '1.3.3' );
define( 'RELOVIT_PLUGIN_FILE', __FILE__ );

// Include the main plugin class.
require_once plugin_dir_path( __FILE__ ) . 'includes/Plugin.php';


/**
 * The code that runs during plugin activation.
 * This action is only scheduled once.
 */
function relovit_activate() {
    // Set a flag to flush rewrite rules on the next page load.
    add_option( 'relovit_flush_rewrite_rules', true );
}
register_activation_hook( __FILE__, 'relovit_activate' );

/**
 * Flush rewrite rules on plugin update.
 */
function relovit_flush_rewrite_rules_on_update() {
    $version = get_option( 'relovit_version', '1.0.0' );
    if ( version_compare( $version, RELOVIT_VERSION, '<' ) ) {
        add_option( 'relovit_flush_rewrite_rules', true );
        update_option( 'relovit_version', RELOVIT_VERSION );
    }
}
add_action( 'plugins_loaded', 'relovit_flush_rewrite_rules_on_update' );


/**
 * Flush rewrite rules if the flag is set.
 */
function relovit_maybe_flush_rewrite_rules() {
    if ( get_option( 'relovit_flush_rewrite_rules' ) ) {
        flush_rewrite_rules();
        delete_option( 'relovit_flush_rewrite_rules' );
    }
}
add_action( 'init', 'relovit_maybe_flush_rewrite_rules', 20 );

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