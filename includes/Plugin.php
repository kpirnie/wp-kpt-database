<?php
/**
 * Plugin main class
 *
 * @package WP_KPT_Database
 */

namespace KPT\WordPress;

use KPT\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main Plugin Class
 */
class Plugin {

	/**
	 * Plugin instance
	 *
	 * @var Plugin
	 */
	private static $instance = null;

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
	public static function get_instance() {
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
			'wp-kpt-database',
			false,
			dirname( plugin_basename( WP_KPT_DB_PLUGIN_FILE ) ) . '/languages'
		);
	}

	/**
	 * Replace WordPress wpdb with KPT Database
	 */
	public function replace_wpdb() {
		global $wpdb;

		if ( ! $this->wpdb_replacement ) {
			$this->wpdb_replacement = new WPDB_Replacement( $wpdb );
		}
	}

	/**
	 * Plugin activation
	 */
	public static function activate() {
		// Check PHP version.
		if ( version_compare( PHP_VERSION, '8.2', '<' ) ) {
			deactivate_plugins( plugin_basename( WP_KPT_DB_PLUGIN_FILE ) );
			wp_die(
				esc_html__( 'This plugin requires PHP 8.2 or higher.', 'wp-kpt-database' ),
				esc_html__( 'Plugin Activation Error', 'wp-kpt-database' ),
				array( 'back_link' => true )
			);
		}

		// Check WordPress version.
		global $wp_version;
		if ( version_compare( $wp_version, '6.7', '<' ) ) {
			deactivate_plugins( plugin_basename( WP_KPT_DB_PLUGIN_FILE ) );
			wp_die(
				esc_html__( 'This plugin requires WordPress 6.7 or higher.', 'wp-kpt-database' ),
				esc_html__( 'Plugin Activation Error', 'wp-kpt-database' ),
				array( 'back_link' => true )
			);
		}

		// Check if KPT Database library is available.
		if ( ! class_exists( 'KPT\\Database' ) ) {
			deactivate_plugins( plugin_basename( WP_KPT_DB_PLUGIN_FILE ) );
			wp_die(
				esc_html__( 'This plugin requires the KPT Database library. Please run composer install.', 'wp-kpt-database' ),
				esc_html__( 'Plugin Activation Error', 'wp-kpt-database' ),
				array( 'back_link' => true )
			);
		}

		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation
	 */
	public static function deactivate() {
		// Deactivation cleanup if needed
		if ( function_exists( 'flush_rewrite_rules' ) ) {
			flush_rewrite_rules();
		}
	}
}