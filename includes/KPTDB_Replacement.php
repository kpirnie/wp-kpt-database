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
if( ! class_exists( 'KPTDB_Replacement' ) ) {

	/**
	 * WPDB Replacement class
	 *
	 * This class extends wpdb and replaces core methods with KPT Database
	 */
	class KPTDB_Replacement extends \wpdb {

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
		 * Query cache storage
		 */
		private array $query_cache = [];

		/**
		 * Cache statistics
		 */
		private array $query_stats = [
			'total' => 0,
			'cached' => 0,
			'executed' => 0,
		];

		/**
		 * Track recent queries to prevent duplicates
		 */
		private array $recent_queries = [];
		
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

			// Initialize cache clearing hooks
    		$this -> init_cache_hooks( );

		}

		/**
		 * Initialize KPT Database
		 * 
		 * @return void Returns nothing
		 */
		private function init_kpt_database( ) : void {

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
		 * 
		 * @return void Returns nothing
		 */
		private function replace_global_wpdb( ) : void {
			global $wpdb;
			$wpdb = $this;
		}

		/**
		 * Checks the database connection and attempts to reconnect if needed.
		 *
		 * @param bool $allow_bail Optional. Whether to bail on error. Default true.
		 * @return bool True if the connection is alive or reconnection successful, false otherwise.
		 */
		public function check_connection( $allow_bail = true ) : bool {

			// Check if the connection is alive.
			if ( ! empty( $this -> dbh ) && $this -> dbh->raw( 'DO 1' ) !== false ) {
				return true;
			}

			$error_reporting = false;

			// Disable warnings, as we don't want to see a multitude of "unable to connect" messages.
			if ( WP_DEBUG ) {
				$error_reporting = error_reporting( );
				error_reporting( $error_reporting & ~E_WARNING );
			}

			// loop through the retries
			for ( $tries = 1; $tries <= $this -> reconnect_retries; $tries++ ) {
				
				// On the last try, re-enable warnings.
				if ( $this -> reconnect_retries === $tries && WP_DEBUG ) {
					error_reporting( $error_reporting );
				}
				if ( $this -> db_connect( false ) ) {
					if ( $error_reporting ) {
						error_reporting( $error_reporting );
					}
					return true;
				}
				sleep( 1 );
			}

			// If template_redirect has already happened, it's too late for wp_die()/dead_db().
			if ( did_action( 'template_redirect' ) ) {
				return false;
			}

			// just bail
			if ( ! $allow_bail ) {
				return false;
			}

			// load the translations
			wp_load_translations_early( );

			// setup the message to throw
			$message = '<h1>' . __( 'Error reconnecting to the database' ) . "</h1>\n";
			$message .= '<p>' . sprintf(
				/* translators: %s: Database host. */
				__( 'This means that the contact with the database server at %s was lost. This could mean your host&#8217;s database server is down.' ),
				'<code>' . htmlspecialchars( $this->dbhost, ENT_QUOTES ) . '</code>'
			) . "</p>\n";
			$message .= "<ul>\n";
			$message .= '<li>' . __( 'Are you sure the database server is running?' ) . "</li>\n";
			$message .= '<li>' . __( 'Are you sure the database server is not under particularly heavy load?' ) . "</li>\n";
			$message .= "</ul>\n";
			$message .= '<p>' . sprintf(
				/* translators: %s: Support forums URL. */
				__( 'If you are unsure what these terms mean you should probably contact your host. If you still need help you can always visit the <a href="%s">WordPress support forums</a>.' ),
				__( 'https://wordpress.org/support/forums/' )
			) . "</p>\n";

			// We weren't able to reconnect, so we better bail.
			$this -> bail( $message, 'db_connect_fail' );

			// Call dead_db() if bail didn't die, because this database is no more.
			dead_db( );

		}

		/**
		 * Retrieves the character set for the given table.
		 *
		 * @since 3.5.0
		 * @access protected
		 *
		 * @param string $table Table name.
		 * @return string|WP_Error Table character set, WP_Error object if it couldn't be found.
		 */
		protected function get_table_charset( $table ) : string|WP_Error {
			$tablekey = strtolower( $table );

			// Filters the table charset value before the DB is checked.
			$charset = apply_filters( 'pre_get_table_charset', null, $table );
			if ( null !== $charset ) {
				return $charset;
			}

			// now if we've actually go the value already
			if ( isset( $this -> table_charset[ $tablekey ] ) ) {
				return $this -> table_charset[ $tablekey ];
			}

			// hold em
			$charsets = array( );
			$columns  = array( );

			// get the table, along with the columns
			$table_parts = explode( '.', $table );
			$table       = '`' . implode( '`.`', $table_parts ) . '`';
			$results     = $this -> kpt_db -> query( "SHOW FULL COLUMNS FROM $table" )->fetch();
			if ( ! $results ) {
				return new WP_Error( 'wpdb_get_table_charset_failure', __( 'Could not retrieve table charset.' ) );
			}

			// loop the columns and hold them in the array above
			foreach ( $results as $column ) {
				$columns[ strtolower( $column -> Field ) ] = $column;
			}

			// hold the meta data
			$this -> col_meta[ $tablekey ] = $columns;

			// loop the columns
			foreach ( $columns as $column ) {

				// as long as the collation isn't empty... set it
				if ( ! empty( $column -> Collation ) ) {
					list( $charset ) = explode( '_', $column -> Collation );
					$charsets[ strtolower( $charset ) ] = true;
				}

				// hold the column type
				list( $type ) = explode( '(', $column -> Type );

				// A binary/blob means the whole query gets treated like this.
				if ( in_array( strtoupper( $type ), array( 'BINARY', 'VARBINARY', 'TINYBLOB', 'MEDIUMBLOB', 'BLOB', 'LONGBLOB' ), true ) ) {
					$this -> table_charset[ $tablekey ] = 'binary';
					return 'binary';
				}
			}

			// utf8mb3 is an alias for utf8.
			if ( isset( $charsets['utf8mb3'] ) ) {
				$charsets['utf8'] = true;
				unset( $charsets['utf8mb3'] );
			}

			// Check if we have more than one charset in play.
			$count = count( $charsets );
			if ( 1 === $count ) {
				$charset = key( $charsets );
			} elseif ( 0 === $count ) {

				// No charsets, assume this table can store whatever.
				$charset = false;
			} else {

				// More than one charset. Remove latin1 if present and recalculate.
				unset( $charsets['latin1'] );
				$count = count( $charsets );
				if ( 1 === $count ) {

					// Only one charset (besides latin1).
					$charset = key( $charsets );
				} elseif ( 2 === $count && isset( $charsets['utf8'], $charsets['utf8mb4'] ) ) {

					// Two charsets, but they're utf8 and utf8mb4, use utf8.
					$charset = 'utf8';
				} else {

					// Two mixed character sets. ascii.
					$charset = 'ascii';
				}
			}

			// hold the charset and return it
			$this -> table_charset[ $tablekey ] = $charset;
			return $charset;
		}

		/**
		 * Loads column metadata from the last query result.
		 *
		 * Retrieves field information for each column in the result set
		 * and stores it in the col_info property for later use.
		 *
		 * @since 3.5.0
		 * @access protected
		 *
		 * @return void Returns nothing
		 */
		protected function load_col_info( ) : void {
			
			// some checks
			if ( $this -> col_info ) {
				return;
			}
			if ( empty( $this -> last_query ) || ! $this -> result ) {
				return;
			}

			// Extract table name from query for more accurate column info
			$table = '';
			if ( preg_match( '/FROM\s+([^\s,)(]+)/i', $this -> last_query, $matches ) ) {
				$table = trim( $matches[1], '`' );
			}

			// we dont have a table here...
			if ( ! $table ) {
				return;
			}

			// Get full column information from the database
			$results = $this -> dbh -> query( "SHOW FULL COLUMNS FROM `{$table}`" ) -> fetch( );
			if ( ! $results ) {
				return;
			}

			$i = 0;
			foreach ( $results as $column ) {
				$this->col_info[ $i ] = (object) array(
					'name' => $column->Field,
					'table' => $table,
					'max_length' => $this->get_column_max_length( $column->Type ),
					'not_null' => $column->Null === 'NO' ? 1 : 0,
					'primary_key' => $column->Key === 'PRI' ? 1 : 0,
					'type' => $this->get_column_type( $column->Type ),
					'collation' => $column->Collation
				);
				$i++;
			}
		}

		/**
		 * Extracts max length from column type definition
		 * 
		 * @access private
		 * @param string $type Column type definition
		 * @return int Maximum length
		 */
		private function get_column_max_length( $type ) : int {
			if ( preg_match( '/\((\d+)\)/', $type, $matches ) ) {
				return (int) $matches[1];
			}
			return 0;
		}

		/**
		 * Extracts base type from column type definition
		 * 
		 * @access private
		 * @param string $type Column type definition
		 * @return string Base type
		 */
		private function get_column_type( $type ) {
			return preg_replace( '/\([^)]*\)/', '', $type );
		}

		/**
		 * Generate cache key for a query
		 */
		private function generate_cache_key( string $query ): string {
			return 'wpdb_' . md5( $query . serialize( $this -> query_params ?? [] ) );
		}

		/**
		 * Check if query is cacheable (frontend SELECT queries only)
		 */
		private function is_cacheable_query( string $query ): bool {
			
			// Only cache on frontend
			if ( is_admin( ) ) {
				return false;
			}
			
			// Only cache SELECT queries
			if ( ! preg_match( '/^SELECT/i', trim( $query ) ) ) {
				return false;
			}
			
			// Don't cache time-sensitive queries
			if ( preg_match( '/(RAND|NOW|FOUND_ROWS|SQL_CALC_FOUND_ROWS)\(\)/i', $query ) ) {
				return false;
			}
			
			return true;
		}

		/**
		 * Get cached query result
		 */
		private function get_cached_query( string $cache_key ): mixed {
			
			// Try object cache first (if available)
			if ( function_exists( 'wp_cache_get' ) ) {
				return wp_cache_get( $cache_key, 'wpdb_queries' );
			}
			
			// Fallback to internal cache
			return $this -> query_cache[ $cache_key ] ?? false;
		}

		/**
		 * Store query result in cache
		 */
		private function cache_query_result( string $query, mixed $result ): void {
		
			// generate the cache key
			$cache_key = $this -> generate_cache_key( $query );
			
			// hold the cachable data
			$cache_data = [
				'result' => $this -> last_result,
				'num_rows' => $this -> num_rows,
				'rows_affected' => $this -> rows_affected,
				'return_val' => $result,
			];
			
			// Store in object cache if available
			if ( function_exists( 'wp_cache_set' ) ) {
				wp_cache_set( $cache_key, $cache_data, 'wpdb_queries', 3600 ); // 1 hour TTL
			}
			
			// Store in internal cache (limit size)
			if ( count( $this -> query_cache ) > 100 ) {
				array_shift( $this -> query_cache ); // Remove oldest
			}
			$this -> query_cache[ $cache_key ] = $cache_data;
		}

		/**
		 * Performs a database query, using current database connection.
		 *
		 * @link https://developer.wordpress.org/reference/classes/wpdb/
		 *
		 * @param string $query Database query.
		 * @return int|bool Boolean true for CREATE, ALTER, TRUNCATE and DROP queries. Number of rows
		 *                  affected/selected for all other queries. Boolean false on error.
		 */
		public function query( $query ) : int|bool {
    
			// check if we're ready
			if ( ! $this -> ready ) {
				$this -> check_current_query = true;
				return false;
			}

			// apply the filter
			$query = apply_filters( 'query', $query );
			if ( ! $query ) {
				$this -> insert_id = 0;
				return false;
			}

			// INCREMENT STATS
			$this -> query_stats['total']++;

			// CHECK CACHE FIRST (frontend only)
			if ( $this -> is_cacheable_query( $query ) ) {
				$cache_key = $this -> generate_cache_key( $query );
				$cached = $this -> get_cached_query( $cache_key );
				
				// if we currently have a cached result... serve it up
				if ( $cached !== false ) {
					$this -> query_stats['cached']++;
					$this -> last_result = $cached['result'];
					$this -> num_rows = $cached['num_rows'];
					$this -> rows_affected = $cached['rows_affected'];
					Logger::debug( 'Query served from cache' );
					return $cached['return_val'];
				}
			}

			// check if it's a duplicate query
			if ( $this -> is_duplicate_query( $query ) ) {
				// return the number of rows
				return $this -> num_rows;
			}

			// flush out previous queries
			$this -> flush( );

			// If we're writing to the database, make sure the query will write safely
			if ( $this -> check_current_query && ! $this -> check_ascii( $query ) ) {
				$stripped_query = $this -> strip_invalid_text_from_query( $query );
				$this -> flush( );
				
				if ( $stripped_query !== $query ) {
					$this -> insert_id  = 0;
					$this -> last_query = $query;
					wp_load_translations_early( );
					$this -> last_error = __( 'WordPress database error: Could not perform query because it contains invalid data.' );
					return false;
				}
			}

			$this -> check_current_query = true;
			$this -> last_query = $query;

			// TRACK EXECUTION
			$this -> query_stats['executed']++;
			
			// now actually run the query
			$this -> _do_query( $query );

			// Database server has gone away, try to reconnect
			$mysql_errno = 0;
			if ( ! $this -> kpt_db ) {
				$mysql_errno = 2006;
			}
			if ( empty( $this -> kpt_db ) || 2006 === $mysql_errno ) {
				if ( $this -> check_connection() ) {
					$this -> _do_query( $query );
				} else {
					$this -> insert_id = 0;
					return false;
				}
			}

			$this -> last_error = '';

			if ( $this -> last_error ) {
				if ( $this -> insert_id && preg_match( '/^\s*(insert|replace)\s/i', $query ) ) {
					$this -> insert_id = 0;
				}
				$this -> print_error( );
				return false;
			}

			if ( preg_match( '/^\s*(create|alter|truncate|drop)\s/i', $query ) ) {
				$return_val = $this -> result;
			} elseif ( preg_match( '/^\s*(insert|delete|update|replace)\s/i', $query ) ) {
				$this -> rows_affected = $this -> kpt_db -> execute( ) ?: 0;

				if ( preg_match( '/^\s*(insert|replace)\s/i', $query ) ) {
					$this -> insert_id = $this -> kpt_db -> getLastId( ) ?: 0;
				}

				$return_val = $this -> rows_affected;
			} else {
				$result = $this -> kpt_db->query( $query ) -> fetch( );
				$num_rows = is_array( $result ) ? count( $result ) : 0;

				if ( $result ) {
					$this -> last_result = $result;
				}

				$this -> num_rows = $num_rows;
				$return_val = $num_rows;
			}

			// CACHE SUCCESSFUL SELECT QUERIES
			if ( $return_val !== false && $this -> is_cacheable_query( $query ) ) {
				$this -> cache_query_result( $query, $return_val );
			}

			return $return_val;
		}

		/**
		 * Internal function to perform the actual query or executions
		 
		 * @param string $query The query to run.
		 * 
		 * @return void Returns nothing
		 */
		private function _do_query( $query ) : void {

			// fire up the timer if we're logging the queries
			if ( defined( 'SAVEQUERIES' ) && SAVEQUERIES ) {
				$this -> timer_start( );
			}

			// as long as our handle isnt empty
			if ( ! empty( $this -> kpt_db ) ) {

				// trim the query and force it to upper-case
				$query_upper = strtoupper( trim( $query ) );
				
				// if we are running a select query, just fetch
				if ( str_starts_with( $query_upper, 'SELECT' ) ) {
					$this -> result = $this -> kpt_db -> query( $query ) -> fetch( );

				// otherwise execute
				} else {
					$this -> result = $this -> kpt_db -> query( $query ) -> execute( );
				}
			}

			// increment the number of queries
			++$this -> num_queries;

			// if we want to save the queries, we're going to log everything
			if ( defined( 'SAVEQUERIES' ) && SAVEQUERIES ) {
				$this -> log_query(
					$query,
					$this -> timer_stop( ),
					$this -> get_caller( ),
					$this -> time_start,
					array( )
				);
			}
		}

		/**
		 * Flush cached query results
		 * 
		 * @return void Returns nothing
		 */
		public function flush( ) : void {

			// Log cache flush
			Logger::debug( 'Flushing query cache' );
			
			// Clear stored query results
			$this -> last_result = array( );
			$this -> col_info = null;
			$this -> last_query = null;
			$this -> rows_affected = 0;
			$this -> num_rows = 0;
			
			// Reset KPT Database state
			if ( $this -> kpt_db ) {
				try {
					$this -> kpt_db -> reset( );
					Logger::debug( 'KPT Database reset successfully' );
				} catch ( \Exception $e ) {
					Logger::error( 'Failed to reset KPT Database', [ 'error' => $e->getMessage() ] );
				}
			}
			
			// Call parent flush for additional cleanup
			parent::flush( );
			
			// Log flush completion
			Logger::debug( 'Query cache flushed successfully' );
		}

		/**
		 * Close the current database connection
		 *
		 * @return bool True on success, false on failure
		 */
		public function close( ) {

			// Log connection close attempt
			Logger::debug( 'Attempting to close database connection' );
			
			try {
				// Reset KPT Database
				if ( $this -> kpt_db ) {
					$this -> kpt_db -> reset( );
					
					// Set to null to allow garbage collection and then remove it from suerspace
					$this -> kpt_db = null;
					unset( $this -> kpt_db );
					
					Logger::debug( 'KPT Database connection closed successfully' );
				}
				
				// Clear all stored data
				$this -> flush( );
				
				// Call parent close method for compatibility
				$result = parent::close( );
				
				// Log successful close
				Logger::debug( 'Database connection closed', [ 'result' => $result ] );
				
				return $result;
				
			} catch ( \Exception $e ) {
				// Log error
				Logger::error( 'Failed to close database connection', [ 
					'error' => $e -> getMessage() 
				] );
				
				return false;
			}
		}

		/**
		 * Initialize cache clearing hooks
		 * 
		 * @return void
		 */
		private function init_cache_hooks(): void {
			// Only add hooks if not in admin
			if ( is_admin() ) {
				return;
			}
			
			// Clear cache on post changes
			add_action( 'save_post', [ $this, 'clear_cache' ] );
			add_action( 'deleted_post', [ $this, 'clear_cache' ] );
			add_action( 'trash_post', [ $this, 'clear_cache' ] );
			add_action( 'untrash_post', [ $this, 'clear_cache' ] );
			
			// Clear cache on comment changes (affects comment counts)
			add_action( 'wp_insert_comment', [ $this, 'clear_cache' ] );
			add_action( 'wp_set_comment_status', [ $this, 'clear_cache' ] );
			
			// Clear cache on option changes
			add_action( 'updated_option', [ $this, 'clear_cache' ] );
			add_action( 'deleted_option', [ $this, 'clear_cache' ] );
			add_action( 'added_option', [ $this, 'clear_cache' ] );
			
			// Clear cache on term/taxonomy changes
			add_action( 'created_term', [ $this, 'clear_cache' ] );
			add_action( 'edited_term', [ $this, 'clear_cache' ] );
			add_action( 'delete_term', [ $this, 'clear_cache' ] );
			
			// Clear cache on user changes
			add_action( 'profile_update', [ $this, 'clear_cache' ] );
			add_action( 'user_register', [ $this, 'clear_cache' ] );
			add_action( 'deleted_user', [ $this, 'clear_cache' ] );
			
			// Clear cache on theme switch
			add_action( 'switch_theme', [ $this, 'clear_cache' ] );
			
			// Clear cache on plugin activation/deactivation
			add_action( 'activated_plugin', [ $this, 'clear_cache' ] );
			add_action( 'deactivated_plugin', [ $this, 'clear_cache' ] );
		}

		/**
		 * Clear the query cache
		 * 
		 * @return void
		 */
		public function clear_cache(): void {
			// Clear internal cache
			$this -> query_cache = [];
			
			// Clear object cache group if available
			if ( function_exists( 'wp_cache_flush_group' ) ) {
				wp_cache_flush_group( 'wpdb_queries' );
			} elseif ( function_exists( 'wp_cache_delete' ) ) {
				// If flush_group isn't available, we'll need to track keys
				// and delete them individually (less efficient)
				foreach ( array_keys( $this -> query_cache ) as $key ) {
					wp_cache_delete( $key, 'wpdb_queries' );
				}
			}
			
			// Log cache clear
			Logger::debug( 'Query cache cleared', [
				'cached_queries' => $this -> query_stats['cached'] ?? 0,
				'total_queries' => $this -> query_stats['total'] ?? 0,
			] );
		}

		/**
		 * Optimize autoloaded options
		 */
		public function optimize_autoload() : void {
			if ( is_admin() ) {
				return;
			}
			
			// Get all autoloaded options in one query
			$alloptions = wp_load_alloptions();
			
			// Pre-cache them individually for faster access
			foreach ( $alloptions as $option => $value ) {
				wp_cache_set( $option, $value, 'options' );
			}
			
			// Mark that we've preloaded
			wp_cache_set( 'alloptions_preloaded', true, 'options', 3600 );
			
			Logger::debug( 'Autoloaded options optimized', [
				'count' => count( $alloptions )
			] );
		}

		/**
		 * Check if query was recently executed
		 */
		private function is_duplicate_query( string $query ) : bool {
			if ( is_admin() ) {
				return false;
			}
			
			// Generate query signature
			$signature = md5( $query );
			
			// Check if we've run this exact query in the last 100 queries
			if ( isset( $this->recent_queries[ $signature ] ) ) {
				$last_run = $this->recent_queries[ $signature ];
				
				// If run within last second, it's likely a duplicate
				if ( time() - $last_run < 1 ) {
					Logger::debug( 'Duplicate query prevented', [
						'query_hash' => $signature
					] );
					return true;
				}
			}
			
			// Track this query
			$this->recent_queries[ $signature ] = time();
			
			// Keep only last 100 queries
			if ( count( $this->recent_queries ) > 100 ) {
				array_shift( $this->recent_queries );
			}
			
			return false;
		}
		
	}

}
