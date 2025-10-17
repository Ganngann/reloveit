<?php
/**
 * Plugin Name:       WP Occasion AI
 * Plugin URI:        https://example.com/
 * Description:       Utilise l'IA (Gemini) pour identifier des objets dans une image et créer des fiches produits dans WooCommerce.
 * Version:           1.0.0
 * Author:            Jules
 * Author URI:        https://example.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-occasion-ai
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

define( 'WP_OCCASION_AI_VERSION', '1.0.0' );

// Include the main plugin class.
require_once plugin_dir_path( __FILE__ ) . 'includes/Plugin.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_wp_occasion_ai() {
    return \WPOccasionAI\Plugin::instance();
}
run_wp_occasion_ai();