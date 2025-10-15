<?php
/**
 * Plugin Name: WP KPT Database
 * Plugin URI: https://github.com/kpirnie/wp-kpt-database
 * Description: Replaces WordPress database interaction with KPT Database library
 * Version: 1.0.0
 * Requires at least: 6.7
 * Requires PHP: 8.2
 * Author: Kevin Pirnie
 * Author URI: https://kpirnie.com
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 * Text Domain: wp-kpt-database
 * Domain Path: /languages
 *
 * @package WP_KPT_Database
 */

namespace KPT\WordPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'WP_KPT_DB_VERSION', '1.0.0' );
define( 'WP_KPT_DB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_KPT_DB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WP_KPT_DB_PLUGIN_FILE', __FILE__ );

// Require Composer autoloader.
if ( file_exists( WP_KPT_DB_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once WP_KPT_DB_PLUGIN_DIR . 'vendor/autoload.php';
}

// Initialize the plugin.
function wp_kpt_database_init() {
	Plugin::get_instance();
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\wp_kpt_database_init', 1 );

// Activation hook.
register_activation_hook( __FILE__, array( __NAMESPACE__ . '\\Plugin', 'activate' ) );

// Deactivation hook.
register_deactivation_hook( __FILE__, array( __NAMESPACE__ . '\\Plugin', 'deactivate' ) );