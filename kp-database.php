<?php
/**
 * Plugin Name: KP Database
 * Plugin URI: https://github.com/kpirnie/wp-kpt-database
 * Description: Replaces WordPress database interaction with KPT Database library
 * Version: 0.1.66
 * Requires at least: 6.7
 * Requires PHP: 8.2
 * Author: Kevin Pirnie
 * Author URI: https://kpirnie.com
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 * Text Domain: kp-db
 * Domain Path: /languages
 *
 * @package KP_Database
 */

namespace KPT\WordPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'KP_DB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'KP_DB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'KP_DB_PLUGIN_FILE', __FILE__ );

// Require Composer autoloader.
if ( file_exists( KP_DB_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once KP_DB_PLUGIN_DIR . 'vendor/autoload.php';
}

// Initialize the plugin.
function kp_database_init() {
	
	// Initialize logger based on WP_DEBUG
    new \KPT\Logger( 
        defined( 'WP_DEBUG' ) && WP_DEBUG, 
        defined( 'WP_DEBUG' ) && WP_DEBUG 
    );

	Plugin::get_instance();
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\kp_database_init', 1 );

// Activation hook.
register_activation_hook( __FILE__, array( __NAMESPACE__ . '\\Plugin', 'activate' ) );

// Deactivation hook.
register_deactivation_hook( __FILE__, array( __NAMESPACE__ . '\\Plugin', 'deactivate' ) );