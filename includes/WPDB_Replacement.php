<?php
/**
 * WPDB Replacement class
 *
 * @package kp_Database
 */

namespace KPT\WordPress;

use KPT\Database;
use KPT\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// make sure the class doesn't already exist
if( ! class_exists( 'WPDB_Replacement' ) ) {

	/**
	 * WPDB Replacement class
	 *
	 * This class extends wpdb and replaces core methods with KPT Database
	 */
	class WPDB_Replacement extends \wpdb {

		/**
		 * KPT Database instance
		 *
		 * @var Database
		 */
		private $kpt_db = null;

		/**
		 * Original wpdb instance
		 *
		 * @var \wpdb
		 */
		private $original_wpdb = null;
		
		/**
		 * Constructor
		 *
		 * @param \wpdb $wpdb WordPress database object.
		 */
		public function __construct( $wpdb ) {

			// hold the original
			$this -> original_wpdb = $wpdb;

			// Copy wpdb properties.
			foreach ( get_object_vars( $wpdb ) as $key => $value ) {
				$this -> $key = $value;
			}

			// Initialize KPT Database.
			$this -> init_kpt_database( );

			// Replace global wpdb.
			$this -> replace_global_wpdb( );
		}

		/**
		 * Initialize KPT Database
		 */
		private function init_kpt_database() {

			// Get the actual charset/collation from the existing wpdb connection
			$charset = $this -> original_wpdb -> charset;
			$collation = $this -> original_wpdb -> collate;
			
			// Fallback to constants if wpdb values are empty for both charset and collation
			if ( empty( $charset ) ) {
				$charset = defined( 'DB_CHARSET' ) && ! empty( DB_CHARSET ) ? DB_CHARSET : 'utf8mb4';
			}
			if ( empty( $collation ) ) {
				$collation = defined( 'DB_COLLATE' ) && ! empty( DB_COLLATE ) ? DB_COLLATE : '';
			}
			
			// setup the db settings
			$db_settings = ( object ) array(
				'server'    => DB_HOST,
				'schema'    => DB_NAME,
				'username'  => DB_USER,
				'password'  => DB_PASSWORD,
				'charset'   => $charset,
				'collation' => $collation,
			);

			// try to create the database instance
			try {
				$this -> kpt_db = new Database( $db_settings );
			
			// trap the error
			} catch ( \Exception $e ) {
				Logger::error( 'Failed to initialize KPT Database', array(
					'error' => $e -> getMessage(),
					'settings' => array(
						'server' => $db_settings->server,
						'schema' => $db_settings->schema,
						'charset' => $db_settings->charset,
						'collation' => $db_settings->collation,
					)
				) );
				
				// make sure we kill wordpress
				wp_die(
					esc_html( $e -> getMessage( ) ),
					esc_html__( 'Database Connection Error', 'kp-db' )
				);
			}
		}

		/**
		 * Replace global wpdb
		 */
		private function replace_global_wpdb( ) {
			global $wpdb;
			$wpdb = $this;
		}
				
		/**
		 * Magic method for getting inaccessible properties
		 *
		 * @param string $name Property name
		 * @return mixed Property value
		 */
		public function __get( $name ) {
			return parent::__get( $name );
		}
		
		/**
		 * Magic method for setting inaccessible properties
		 *
		 * @param string $name Property name
		 * @param mixed $value Property value
		 */
		public function __set( $name, $value ) {
			parent::__set( $name, $value );
		}
		
		/**
		 * Magic method for checking if an inaccessible property is set
		 *
		 * @param string $name Property name
		 * @return bool True if property is set, false otherwise
		 */
		public function __isset( $name ) {
			return parent::__isset( $name );
		}
		
		/**
		 * Magic method for unsetting an inaccessible property
		 *
		 * @param string $name Property name
		 */
		public function __unset( $name ) {
			parent::__unset( $name );
		}
		
		/**
		 * Set the database character set
		 */
		public function init_charset() {
			parent::init_charset();
		}
		
		/**
		 * Set the connection's character set
		 *
		 * @param resource $dbh Database connection handle
		 * @param string|null $charset Optional character set
		 * @param string|null $collate Optional collation
		 */
		public function set_charset( $dbh, $charset = null, $collate = null ) {
			parent::set_charset( $dbh, $charset, $collate );
		}
		
		/**
		 * Change the current SQL mode, and ensure its WordPress compatibility
		 *
		 * @param array $modes Optional array of SQL modes
		 */
		public function set_sql_mode( $modes = array() ) {
			parent::set_sql_mode( $modes );
		}
		
		/**
		 * Set the table prefix for the WordPress tables
		 *
		 * @param string $prefix Alphanumeric name for the new prefix
		 * @param bool $set_table_names Optional. Whether to set the table names. Default true
		 * @return string|WP_Error Old prefix or WP_Error on error
		 */
		public function set_prefix( $prefix, $set_table_names = true ) {
			return parent::set_prefix( $prefix, $set_table_names );
		}
		
		/**
		 * Set blog ID
		 *
		 * @param int $blog_id Blog ID
		 * @param int $network_id Optional network ID. Default 0
		 * @return int Previous blog ID
		 */
		public function set_blog_id( $blog_id, $network_id = 0 ) {
			return parent::set_blog_id( $blog_id, $network_id );
		}
		
		/**
		 * Get the blog prefix
		 *
		 * @param int|null $blog_id Optional blog ID. Default null
		 * @return string Blog prefix
		 */
		public function get_blog_prefix( $blog_id = null ) {
			return parent::get_blog_prefix( $blog_id );
		}
		
		/**
		 * Get a list of WordPress tables
		 *
		 * @param string $scope Optional scope. Default 'all'
		 * @param bool $prefix Optional. Whether to include table prefixes. Default true
		 * @param int $blog_id Optional blog ID. Default 0
		 * @return array List of tables
		 */
		public function tables( $scope = 'all', $prefix = true, $blog_id = 0 ) {
			return parent::tables( $scope, $prefix, $blog_id );
		}
		
		/**
		 * Select a database using the current or provided database connection
		 *
		 * @param string $db Database name
		 * @param resource|null $dbh Optional database connection handle
		 * @return bool True on success, false on failure
		 */
		public function select( $db, $dbh = null ) {
			return parent::select( $db, $dbh );
		}
		
		/**
		 * Weak escape using addslashes
		 *
		 * @param string|array $data Data to escape
		 * @return string|array Escaped data
		 */
		public function _weak_escape( $data ) {
			return parent::_weak_escape( $data );
		}
		
		/**
		 * Real escape using mysqli_real_escape_string or mysql_real_escape_string
		 *
		 * @param string $data Data to escape
		 * @return string Escaped data
		 */
		public function _real_escape( $data ) {
			return parent::_real_escape( $data );
		}
		
		/**
		 * Escape data for use in a MySQL query
		 *
		 * @param string|array $data Data to escape
		 * @return string|array Escaped data
		 */
		public function _escape( $data ) {
			return parent::_escape( $data );
		}
		
		/**
		 * Escape data for use in a MySQL query
		 *
		 * @param string|array $data Data to escape
		 * @return string|array Escaped data
		 */
		public function escape( $data ) {
			return parent::escape( $data );
		}
		
		/**
		 * Escape data by reference for use in a MySQL query
		 *
		 * @param string $data Data to escape
		 * @return string Escaped data
		 */
		public function escape_by_ref( &$data ) {
			return parent::escape_by_ref( $data );
		}
		
		/**
		 * Prepare a SQL query for safe execution
		 *
		 * @param string $query Query statement with sprintf()-like placeholders
		 * @param mixed ...$args Values to substitute into the query
		 * @return string|void Sanitized query string, if there is a query to prepare
		 */
		public function prepare( $query, ...$args ) {
			return parent::prepare( $query, ...$args );
		}
		
		/**
		 * First half of escaping for LIKE special characters % and _ before preparing for SQL
		 *
		 * @param string $text Text to escape
		 * @return string Escaped text
		 */
		public function esc_like( $text ) {
			return parent::esc_like( $text );
		}
		
		/**
		 * Print SQL/DB error
		 *
		 * @param string $str Error message
		 * @return void|false Void if error is displayed, false if not
		 */
		public function print_error( $str = '' ) {
			return parent::print_error( $str );
		}
		
		/**
		 * Enable or disable error displaying
		 *
		 * @param bool $show Whether to show errors. Default true
		 */
		public function show_errors( $show = true ) {
			parent::show_errors( $show );
		}
		
		/**
		 * Disable error displaying
		 */
		public function hide_errors() {
			parent::hide_errors();
		}
		
		/**
		 * Enable or disable error suppression
		 *
		 * @param bool $suppress Whether to suppress errors. Default true
		 * @return bool Previous suppress setting
		 */
		public function suppress_errors( $suppress = true ) {
			return parent::suppress_errors( $suppress );
		}
		
		/**
		 * Flush cached query results
		 */
		public function flush() {
			parent::flush();
		}
		
		/**
		 * Connect to and select database
		 *
		 * @param bool $allow_bail Optional. Allows the function to bail. Default true
		 * @return bool True on success, false on failure
		 */
		public function db_connect( $allow_bail = true ) {
			return parent::db_connect( $allow_bail );
		}
		
		/**
		 * Parse the DB_HOST setting to interpret it for mysqli_real_connect
		 *
		 * @param string $host Database host
		 * @return array|false Array containing host, port, socket, and is_ipv6, or false on failure
		 */
		public function parse_db_host( $host ) {
			return parent::parse_db_host( $host );
		}
		
		/**
		 * Check that the connection to the database is still up
		 *
		 * @param bool $allow_bail Optional. Allows the function to bail. Default true
		 * @return bool True if the connection is up, false otherwise
		 */
		public function check_connection( $allow_bail = true ) {
			return parent::check_connection( $allow_bail );
		}
		
		/**
		 * Perform a MySQL database query, using current database connection
		 *
		 * @param string $query Database query
		 * @return int|bool Number of rows affected/selected or false on error
		 */
		public function query( $query ) {
			return parent::query( $query );
		}
		
		/**
		 * Internal function to perform the mysql_query() call
		 *
		 * @param string $query SQL query
		 * @return resource|bool Query result resource or false on failure
		 */
		public function _do_query( $query ) {
			return parent::_do_query( $query );
		}
		
		/**
		 * Log query data
		 *
		 * @param string $query SQL query
		 * @param float $query_time Query execution time
		 * @param string $query_callstack Query callstack
		 * @param float $query_start Query start time
		 * @param array $query_result Query result
		 */
		public function log_query( $query, $query_time, $query_callstack, $query_start, $query_result ) {
			parent::log_query( $query, $query_time, $query_callstack, $query_start, $query_result );
		}
		
		/**
		 * Generate and return a placeholder escape string
		 *
		 * @return string Placeholder escape string
		 */
		public function placeholder_escape() {
			return parent::placeholder_escape();
		}
		
		/**
		 * Add placeholder escape strings to a query
		 *
		 * @param string $query Query to add placeholder escapes to
		 * @return string Query with placeholder escapes added
		 */
		public function add_placeholder_escape( $query ) {
			return parent::add_placeholder_escape( $query );
		}
		
		/**
		 * Remove placeholder escape strings from a query
		 *
		 * @param string $query Query to remove placeholder escapes from
		 * @return string Query with placeholder escapes removed
		 */
		public function remove_placeholder_escape( $query ) {
			return parent::remove_placeholder_escape( $query );
		}
		
		/**
		 * Insert a row into a table
		 *
		 * @param string $table Table name
		 * @param array $data Data to insert (in column => value pairs)
		 * @param array|string|null $format Optional. An array of formats or a format string. Default null
		 * @return int|false Number of rows affected or false on error
		 */
		public function insert( $table, $data, $format = null ) {
			return parent::insert( $table, $data, $format );
		}
		
		/**
		 * Replace a row in a table
		 *
		 * @param string $table Table name
		 * @param array $data Data to insert (in column => value pairs)
		 * @param array|string|null $format Optional. An array of formats or a format string. Default null
		 * @return int|false Number of rows affected or false on error
		 */
		public function replace( $table, $data, $format = null ) {
			return parent::replace( $table, $data, $format );
		}
		
		/**
		 * Helper function for insert and replace
		 *
		 * @param string $table Table name
		 * @param array $data Data to insert (in column => value pairs)
		 * @param array|string|null $format Optional. An array of formats or a format string. Default null
		 * @param string $type Optional. Type of operation (INSERT or REPLACE). Default 'INSERT'
		 * @return int|false Number of rows affected or false on error
		 */
		public function _insert_replace_helper( $table, $data, $format = null, $type = 'INSERT' ) {
			return parent::_insert_replace_helper( $table, $data, $format, $type );
		}
		
		/**
		 * Update a row in a table
		 *
		 * @param string $table Table name
		 * @param array $data Data to update (in column => value pairs)
		 * @param array $where A named array of WHERE clauses (in column => value pairs)
		 * @param array|string|null $format Optional. An array of formats or a format string. Default null
		 * @param array|string|null $where_format Optional. An array of formats or a format string. Default null
		 * @return int|false Number of rows affected or false on error
		 */
		public function update( $table, $data, $where, $format = null, $where_format = null ) {
			return parent::update( $table, $data, $where, $format, $where_format );
		}
		
		/**
		 * Delete a row in a table
		 *
		 * @param string $table Table name
		 * @param array $where A named array of WHERE clauses (in column => value pairs)
		 * @param array|string|null $where_format Optional. An array of formats or a format string. Default null
		 * @return int|false Number of rows affected or false on error
		 */
		public function delete( $table, $where, $where_format = null ) {
			return parent::delete( $table, $where, $where_format );
		}
		
		/**
		 * Process data for insertion/update based on table fields
		 *
		 * @param string $table Table name
		 * @param array $data Data to process
		 * @param array|string $format Format for data
		 * @return array|false Processed data or false on error
		 */
		public function process_fields( $table, $data, $format ) {
			return parent::process_fields( $table, $data, $format );
		}
		
		/**
		 * Process format for data
		 *
		 * @param array $data Data to format
		 * @param array|string $format Format for data
		 * @return array Processed formats
		 */
		public function process_field_formats( $data, $format ) {
			return parent::process_field_formats( $data, $format );
		}
		
		/**
		 * Process field charsets for data
		 *
		 * @param array $data Data to process
		 * @param string $table Table name
		 * @return array Processed data with charset information
		 */
		public function process_field_charsets( $data, $table ) {
			return parent::process_field_charsets( $data, $table );
		}
		
		/**
		 * Process field lengths for data
		 *
		 * @param array $data Data to process
		 * @param string $table Table name
		 * @return array|WP_Error Processed data or WP_Error on failure
		 */
		public function process_field_lengths( $data, $table ) {
			return parent::process_field_lengths( $data, $table );
		}
		
		/**
		 * Process a query before sending it to the database
		 *
		 * @param string $query Query to process
		 * @return string|WP_Error Processed query or WP_Error on failure
		 */
		public function pre_query( $query ) {
			return parent::pre_query( $query );
		}
		
		/**
		 * Check if a string is ASCII
		 *
		 * @param string $data String to check
		 * @return bool True if ASCII, false otherwise
		 */
		public function check_ascii( $data ) {
			return parent::check_ascii( $data );
		}
		
		/**
		 * Check if the table has a safe collation
		 *
		 * @param string $table Table name
		 * @return bool True if collation is safe, false otherwise
		 */
		public function check_safe_collation( $table ) {
			return parent::check_safe_collation( $table );
		}
		
		/**
		 * Strip invalid text from data
		 *
		 * @param array $data Data to strip
		 * @return array Stripped data
		 */
		public function strip_invalid_text( $data ) {
			return parent::strip_invalid_text( $data );
		}
		
		/**
		 * Strip invalid text from a query
		 *
		 * @param string $query Query to strip
		 * @return string Stripped query
		 */
		public function strip_invalid_text_from_query( $query ) {
			return parent::strip_invalid_text_from_query( $query );
		}
		
		/**
		 * Get the character set for a table
		 *
		 * @param string $table Table name
		 * @return string|WP_Error Character set or WP_Error on failure
		 */
		public function get_table_charset( $table ) {
			return parent::get_table_charset( $table );
		}
		
		/**
		 * Get the character set for a column
		 *
		 * @param string $table Table name
		 * @param string $column Column name
		 * @return string|false|WP_Error Character set or false/WP_Error on failure
		 */
		public function get_col_charset( $table, $column ) {
			return parent::get_col_charset( $table, $column );
		}
		
		/**
		 * Get the length of a column
		 *
		 * @param string $table Table name
		 * @param string $column Column name
		 * @return array|false|WP_Error Column length information or false/WP_Error on failure
		 */
		public function get_col_length( $table, $column ) {
			return parent::get_col_length( $table, $column );
		}
		
		/**
		 * Check the database version
		 *
		 * @return WP_Error|void WP_Error if version check fails
		 */
		public function check_database_version() {
			return parent::check_database_version();
		}
		
		/**
		 * Check if the database supports collation
		 *
		 * @return bool True if collation is supported, false otherwise
		 */
		public function supports_collation() {
			return parent::supports_collation();
		}
		
		/**
		 * Get the name of the caller function/method
		 *
		 * @return string|array Caller information
		 */
		public function get_caller() {
			return parent::get_caller();
		}
		
		/**
		 * Get the database version
		 *
		 * @return string|null Database version or null on failure
		 */
		public function db_version() {
			return parent::db_version();
		}
		
		/**
		 * Get database server info
		 *
		 * @return string Database server info
		 */
		public function db_server_info() {
			return parent::db_server_info();
		}
		
		/**
		 * Get multiple rows from the database
		 *
		 * @param string|null $query SQL query
		 * @param string $output Optional. Output type. OBJECT, OBJECT_K, ARRAY_A, or ARRAY_N. Default OBJECT
		 * @return array|object|null Database query results or null on failure
		 */
		public function get_results( $query = null, $output = OBJECT ) {
			return parent::get_results( $query, $output );
		}
		
		/**
		 * Get one row from the database
		 *
		 * @param string|null $query SQL query
		 * @param string $output Optional. Output type. OBJECT, ARRAY_A, or ARRAY_N. Default OBJECT
		 * @param int $y Optional. Row offset. Default 0
		 * @return array|object|null|void Database query result or null on failure
		 */
		public function get_row( $query = null, $output = OBJECT, $y = 0 ) {
			return parent::get_row( $query, $output, $y );
		}
		
		/**
		 * Get one column from the database
		 *
		 * @param string|null $query SQL query
		 * @param int $x Optional. Column offset. Default 0
		 * @return array Database query results
		 */
		public function get_col( $query = null, $x = 0 ) {
			return parent::get_col( $query, $x );
		}
		
		/**
		 * Get one variable from the database
		 *
		 * @param string|null $query SQL query
		 * @param int $x Optional. Column offset. Default 0
		 * @param int $y Optional. Row offset. Default 0
		 * @return string|null Database query result or null on failure
		 */
		public function get_var( $query = null, $x = 0, $y = 0 ) {
			return parent::get_var( $query, $x, $y );
		}
		
		/**
		 * Get column metadata
		 *
		 * @param string $info_type Optional. Type of info to retrieve. Default 'name'
		 * @param int $col_offset Optional. Column offset. Default -1
		 * @return mixed Column metadata
		 */
		public function get_col_info( $info_type = 'name', $col_offset = -1 ) {
			return parent::get_col_info( $info_type, $col_offset );
		}
		
		/**
		 * Start the timer for query timing
		 */
		public function timer_start() {
			parent::timer_start();
		}
		
		/**
		 * Stop the timer and return the elapsed time
		 *
		 * @return float Elapsed time in seconds
		 */
		public function timer_stop() {
			return parent::timer_stop();
		}
		
		/**
		 * Wraps errors in a nice header and footer and dies
		 *
		 * @param string $message Error message
		 * @param string $error_code Optional. Error code. Default '500'
		 * @return void|false Void if error is displayed, false if not
		 */
		public function bail( $message, $error_code = '500' ) {
			return parent::bail( $message, $error_code );
		}
		
		/**
		 * Close the current database connection
		 *
		 * @return bool True on success, false on failure
		 */
		public function close() {
			return parent::close();
		}
		
		/**
		 * Whether the database supports a particular capability
		 *
		 * @param string $db_cap Database capability to check
		 * @return bool True if capability is supported, false otherwise
		 */
		public function has_cap( $db_cap ) {
			return parent::has_cap( $db_cap );
		}
		
		/**
		 * Get the database character collate
		 *
		 * @return string Database character collate
		 */
		public function get_charset_collate() {
			return parent::get_charset_collate();
		}
		
		/**
		 * Determine the best charset and collation to use
		 *
		 * @param string $charset Character set
		 * @param string $collate Collation
		 * @return array Array with 'charset' and 'collate' keys
		 */
		public function determine_charset( $charset, $collate ) {
			return parent::determine_charset( $charset, $collate );
		}
		
		/**
		 * Get the name of the table from a query
		 *
		 * @param string $query SQL query
		 * @return string|false Table name or false on failure
		 */
		public function get_table_from_query( $query ) {
			return parent::get_table_from_query( $query );
		}
		
		/**
		 * Load column metadata
		 */
		public function load_col_info() {
			parent::load_col_info();
		}

	}

}
