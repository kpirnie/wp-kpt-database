<?php
/**
 * Plugin main class
 *
 * @package kp_Database
 */

namespace KPT\WordPress;

use KPT\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// make sure we're not loading this up multiple times...
if( ! class_exists( 'KPTDB_Plugin' ) ) {

	/**
	 * Main Plugin Class
	 */
	class KPTDB_Plugin {

		/**
		 * Plugin instance
		 *
		 * @var Plugin
		 */
		private static ?KPTDB_Plugin $instance = null;

		/**
		 * WPDB Replacement instance
		 *
		 * @var WPDB_Replacement
		 */
		private $wpdb_replacement = null;

		/**
		 * Get plugin instance
		 *
		 * @return Plugin
		 */
		public static function get_instance() : KPTDB_Plugin {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Constructor
		 */
		private function __construct() {
			$this->init_hooks();
		}

		/**
		 * Initialize hooks
		 */
		private function init_hooks() {
			add_action( 'init', array( $this, 'load_textdomain' ) );
			add_action( 'init', array( $this, 'replace_wpdb' ), 0 );
		}

		/**
		 * Load plugin textdomain
		 */
		public function load_textdomain() {
			load_plugin_textdomain(
				'kp-db',
				false,
				dirname( plugin_basename( KP_DB_PLUGIN_FILE ) ) . '/languages'
			);
		}

		/**
		 * Replace WordPress wpdb with KPT Database
		 */
		public function replace_wpdb() {
			// Don't replace if we're deactivating
			if ( defined( 'WP_UNINSTALL_PLUGIN' ) || 
				( isset( $_GET['action'] ) && $_GET['action'] === 'deactivate' ) ) {
				return;
			}

			global $wpdb;

			if ( ! $this->wpdb_replacement ) {
				$this->wpdb_replacement = new KPTDB_Replacement( $wpdb );
			}
		}

		/**
		 * Plugin activation
		 */
		public static function activate() {

			// Check PHP version.
			if ( version_compare( PHP_VERSION, '8.2', '<' ) ) {
				deactivate_plugins( plugin_basename( KP_DB_PLUGIN_FILE ) );
				wp_die(
					esc_html__( 'This plugin requires PHP 8.2 or higher.', 'kp-db' ),
					esc_html__( 'Plugin Activation Error', 'kp-db' ),
					array( 'back_link' => true )
				);
			}

			// Check WordPress version.
			global $wp_version;
			if ( version_compare( $wp_version, '6.7', '<' ) ) {
				deactivate_plugins( plugin_basename( KP_DB_PLUGIN_FILE ) );
				wp_die(
					esc_html__( 'This plugin requires WordPress 6.7 or higher.', 'kp-db' ),
					esc_html__( 'Plugin Activation Error', 'kp-db' ),
					array( 'back_link' => true )
				);
			}

			// Check if KPT Database library is available.
			if ( ! class_exists( 'KPT\\Database' ) ) {
				deactivate_plugins( plugin_basename( KP_DB_PLUGIN_FILE ) );
				wp_die(
					esc_html__( 'This plugin requires the KPT Database library. Please run composer install.', 'kp-db' ),
					esc_html__( 'Plugin Activation Error', 'kp-db' ),
					array( 'back_link' => true )
				);
			}

		}

		/**
		 * Plugin deactivation
		 */
		public static function deactivate() {
			// nothing needed for this...
		}
	}

}
