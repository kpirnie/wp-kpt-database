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
			if ( isset( $this -> $name ) ) {
				return $this -> $name;
			}
			return parent::__get( $name );
		}
		
		/**
		 * Magic method for setting inaccessible properties
		 *
		 * @param string $name Property name
		 * @param mixed $value Property value
		 */
		public function __set( $name, $value ) {
			$this -> $name = $value;
			parent::__set( $name, $value );
		}
		
		/**
		 * Magic method for checking if an inaccessible property is set
		 *
		 * @param string $name Property name
		 * @return bool True if property is set, false otherwise
		 */
		public function __isset( $name ) {
			return isset( $this -> $name ) || parent::__isset( $name );
		}
		
		/**
		 * Magic method for unsetting an inaccessible property
		 *
		 * @param string $name Property name
		 */
		public function __unset( $name ) {
			unset( $this -> $name );
			parent::__unset( $name );
		}
		
		/**
		 * Set the database character set
		 */
		public function init_charset( ) {

			// make sure we have our database functionality
			if ( $this -> kpt_db ) {

				// try to set the charset and collation
				try {
					$charset = $this -> charset ?: 'utf8mb4';
					$collate = $this -> collate ?: 'utf8mb4_unicode_ci';
					
					// set the charset and collation
					$this -> kpt_db -> raw( "SET NAMES {$charset} COLLATE {$collate}" );
					$this -> kpt_db -> raw( "SET CHARACTER SET {$charset}" );
				
				// whoops... trap the error and log it
				} catch ( \Exception $e ) {
					Logger::error( 'Failed to set charset', [ 'error' => $e -> getMessage() ] );
				}
			}

			// Call parent method for WordPress compatibility
			parent::init_charset( );
		}
		
		/**
		 * Set the connection's character set
		 *
		 * @param resource $dbh Database connection handle
		 * @param string|null $charset Optional character set
		 * @param string|null $collate Optional collation
		 */
		public function set_charset( $dbh, $charset = null, $collate = null ) {

			// Use provided charset or fall back to instance charset
			$charset = $charset ?: $this -> charset;
			$collate = $collate ?: $this -> collate;
			
			// Attempt to set charset using KPT Database
			if ( $this -> kpt_db ) {
				try {

					// Set the character set and collation
					$this -> kpt_db -> raw( "SET NAMES {$charset} COLLATE {$collate}" );

					// Log successful charset change
					Logger::debug( 'Charset set successfully', [ 'charset' => $charset, 'collate' => $collate ] );
				} catch ( \Exception $e ) {
					// Log error if charset setting fails
					Logger::error( 'Failed to set charset', [ 'error' => $e -> getMessage() ] );
				}
			}
			
			// Call parent method for WordPress compatibility
			parent::set_charset( $dbh, $charset, $collate );
		}
		
		/**
		 * Change the current SQL mode, and ensure its WordPress compatibility
		 *
		 * @param array $modes Optional array of SQL modes
		 */
		public function set_sql_mode( $modes = array( ) ) {
		
			// If no modes provided, use default WordPress-compatible modes
			if ( empty( $modes ) ) {
				$modes = array( );
			}
			
			// Attempt to set SQL mode using KPT Database
			if ( $this -> kpt_db ) {
				try {

					// Get current SQL mode if no modes specified
					if ( empty( $modes ) ) {
						$current_mode = $this -> kpt_db -> query( "SELECT @@SESSION.sql_mode" ) -> single( ) -> fetch( );
						
						// Log current mode retrieval
						Logger::debug( 'Retrieved current SQL mode', [ 'mode' => $current_mode ] );
					}
					
					// Convert modes array to comma-separated string
					$mode_string = is_array( $modes ) ? implode( ',', $modes ) : $modes;
					
					// Set the SQL mode
					$this -> kpt_db -> raw( "SET SESSION sql_mode = '{$mode_string}'" );
					
					// Log successful mode change
					Logger::debug( 'SQL mode set successfully', [ 'modes' => $mode_string ] );
				} catch ( \Exception $e ) {

					// Log error if SQL mode setting fails
					Logger::error( 'Failed to set SQL mode', [ 'error' => $e -> getMessage( ), 'modes' => $modes ] );
				}
			}
			
			// Call parent method for WordPress compatibility
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

			// Validate prefix format (alphanumeric and underscore only)
			if ( preg_match( '|[^a-z0-9_]|i', $prefix ) ) {

				// Log invalid prefix error
				Logger::error( 'Invalid table prefix format', [ 'prefix' => $prefix ] );
				return new \WP_Error( 'invalid_db_prefix', 'Invalid database prefix' );
			}
			
			// Store old prefix for return value
			$old_prefix = is_multisite() ? '' : $this->prefix;
			
			// Log prefix change attempt
			Logger::debug( 'Attempting to set table prefix', [ 
				'old_prefix' => $old_prefix, 
				'new_prefix' => $prefix,
				'set_table_names' => $set_table_names
			] );
			
			// Set the base prefix
			$this->base_prefix = $prefix;
			$this->prefix = $this->base_prefix;
			
			// Set table names if requested
			if ( $set_table_names ) {

				// Verify prefix exists in database
				try {
					$query = "SHOW TABLES LIKE ?";
					$like_pattern = $prefix . '%';
					
					$results = $this->kpt_db->query( $query )->bind( [ $like_pattern ] )->fetch();
					
					if ( ! $results ) {
						// Log warning if no tables found with this prefix
						Logger::warning( 'No tables found with specified prefix', [ 'prefix' => $prefix ] );
					}
					
					// Set table names
					$this->set_table_names();
					
					// Log success
					Logger::debug( 'Table prefix set successfully', [ 'new_prefix' => $this->prefix ] );
					
				} catch ( \Exception $e ) {
					// Log error
					Logger::error( 'Failed to set table prefix', [ 'error' => $e->getMessage() ] );
					return new \WP_Error( 'db_prefix_error', $e->getMessage() );
				}
			}
			
			return $old_prefix;
		}
		
		/**
		 * Set blog ID
		 *
		 * @param int $blog_id Blog ID
		 * @param int $network_id Optional network ID. Default 0
		 * @return int Previous blog ID
		 */
		public function set_blog_id( $blog_id, $network_id = 0 ) {

			// Store old blog ID for return value
			$old_blog_id = $this->blogid;
			
			// Validate blog ID is numeric
			if ( ! is_numeric( $blog_id ) ) {
				// Log invalid blog ID error
				Logger::error( 'Invalid blog ID provided', [ 'blog_id' => $blog_id ] );
				return $old_blog_id;
			}
			
			// Log blog ID change attempt
			Logger::debug( 'Attempting to set blog ID', [ 
				'old_blog_id' => $old_blog_id, 
				'new_blog_id' => $blog_id,
				'network_id' => $network_id
			] );
			
			// Set the blog ID
			$this->blogid = (int) $blog_id;
			
			// Set the network ID if provided
			if ( ! empty( $network_id ) ) {
				$this->siteid = (int) $network_id;
			}
			
			// Update the table prefix for this blog
			try {
				$this->prefix = $this->get_blog_prefix( $blog_id );
				
				// Log result of blog ID change
				Logger::debug( 'Blog ID set successfully', [ 
					'blog_id' => $this->blogid,
					'prefix' => $this->prefix
				] );
				
			} catch ( \Exception $e ) {
				// Log error
				Logger::error( 'Failed to update prefix for blog', [ 'error' => $e->getMessage() ] );
			}
			
			return $old_blog_id;
		}
		
		
		/**
		 * Get the blog prefix
		 *
		 * @param int|null $blog_id Optional blog ID. Default null
		 * @return string Blog prefix
		 */
		public function get_blog_prefix( $blog_id = null ) {

			// Use current blog ID if none provided
			if ( is_null( $blog_id ) ) {
				$blog_id = $this->blogid;
			}
			
			// Log blog prefix retrieval attempt
			Logger::debug( 'Getting blog prefix', [ 'blog_id' => $blog_id ] );
			
			// For single site or blog_id 0/1, return base prefix
			if ( ! is_multisite() || $blog_id == 0 || $blog_id == 1 ) {
				$prefix = $this->base_prefix;
			} else {
				// Query the database for the blog prefix in multisite
				try {
					$prefix = $this->base_prefix . $blog_id . '_';
					
					// Verify the blog exists by checking if tables exist
					$query = "SHOW TABLES LIKE ?";
					$like_pattern = $prefix . '%';
					
					$results = $this->kpt_db->query( $query )->bind( [ $like_pattern ] )->fetch();
					
					// If no tables found, fall back to base prefix
					if ( ! $results ) {
						$prefix = $this->base_prefix;
					}
					
				} catch ( \Exception $e ) {
					// Log error and fall back to parent method
					Logger::error( 'Failed to get blog prefix', [ 'error' => $e->getMessage() ] );
					return parent::get_blog_prefix( $blog_id );
				}
			}
			
			// Log retrieved prefix
			Logger::debug( 'Blog prefix retrieved', [ 
				'blog_id' => $blog_id,
				'prefix' => $prefix
			] );
			
			return $prefix;
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

			// Log table list retrieval attempt
			Logger::debug( 'Getting table list', [ 
				'scope' => $scope,
				'prefix' => $prefix,
				'blog_id' => $blog_id
			] );
			
			// Get the appropriate prefix
			$table_prefix = $prefix ? $this->get_blog_prefix( $blog_id ) : '';
			
			// Query database for tables using KPT Database
			try {
				// Get all tables from current database
				$query = "SHOW TABLES LIKE ?";
				$like_pattern = $table_prefix . '%';
				
				$results = $this->kpt_db->query( $query )->bind( [ $like_pattern ] )->fetch();
				
				// Extract table names from results
				$tables = array();
				if ( $results ) {
					foreach ( $results as $result ) {
						$table_array = (array) $result;
						$table_name = reset( $table_array );
						
						// Apply scope filtering
						if ( $scope === 'all' ) {
							$tables[] = $table_name;
						} elseif ( $scope === 'global' ) {
							if ( in_array( str_replace( $table_prefix, '', $table_name ), $this->global_tables ) ) {
								$tables[] = $table_name;
							}
						} elseif ( $scope === 'ms_global' ) {
							if ( in_array( str_replace( $table_prefix, '', $table_name ), $this->ms_global_tables ) ) {
								$tables[] = $table_name;
							}
						} elseif ( $scope === 'blog' ) {
							if ( ! in_array( str_replace( $table_prefix, '', $table_name ), array_merge( $this->global_tables, $this->ms_global_tables ) ) ) {
								$tables[] = $table_name;
							}
						}
					}
				}
				
				// Log retrieved table count
				Logger::debug( 'Table list retrieved', [ 
					'scope' => $scope,
					'table_count' => count( $tables )
				] );
				
				return $tables;
				
			} catch ( \Exception $e ) {
				// Log error and fall back to parent method
				Logger::error( 'Failed to retrieve table list', [ 'error' => $e->getMessage() ] );
				return parent::tables( $scope, $prefix, $blog_id );
			}
		}
		
		/**
		 * Select a database using the current or provided database connection
		 *
		 * @param string $db Database name
		 * @param resource|null $dbh Optional database connection handle
		 * @return bool True on success, false on failure
		 */
		public function select( $db, $dbh = null ) {

			// Validate database name
			if ( empty( $db ) ) {
				
				// Log invalid database name
				Logger::error( 'Empty database name provided to select()' );
				return false;
			}
			
			// Log database selection attempt
			Logger::debug( 'Attempting to select database', [ 'database' => $db ] );
			
			// Attempt to select database using KPT Database
			try {

				// Use the raw query method to execute USE statement
				$this -> kpt_db -> raw( "USE `{$db}`" );
				
				// Update the current database property
				$this->dbname = $db;
				
				// Log successful database selection
				Logger::debug( 'Database selected successfully', [ 'database' => $db ] );
				
				return true;
				
			} catch ( \Exception $e ) {
				// Log error
				Logger::error( 'Failed to select database', [ 
					'database' => $db,
					'error' => $e -> getMessage( ) 
				] );
				
				return false;
			}
		}
		
		/**
		 * Flush cached query results
		 */
		public function flush() {

			// Log cache flush
			Logger::debug( 'Flushing query cache' );
			
			// Clear stored query results
			$this -> last_result = array();
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
		 * Connect to and select database
		 *
		 * @param bool $allow_bail Optional. Allows the function to bail. Default true
		 * @return bool True on success, false on failure
		 */
		public function db_connect( $allow_bail = true ) {

			// Log connection attempt
			Logger::debug( 'db_connect called', [ 'allow_bail' => $allow_bail ] );
			
			// Check if KPT Database is already connected
			if ( $this -> kpt_db ) {
				try {
					// Test connection with a simple query
					$this -> kpt_db->raw( "SELECT 1" );
					
					// Connection is alive
					Logger::debug( 'KPT Database connection already established' );
					return true;
					
				} catch ( \Exception $e ) {
					// Connection failed, attempt to reconnect
					Logger::warning( 'Existing connection failed, attempting reconnect', [ 
						'error' => $e->getMessage() 
					] );
				}
			}
			
			// Attempt to initialize/reconnect KPT Database
			try {
				$this -> init_kpt_database();
				
				// Verify connection with test query
				$this -> kpt_db -> raw( "SELECT 1" );
				
				// Log successful connection
				Logger::debug( 'Database connection established successfully' );
				
				return true;
				
			} catch ( \Exception $e ) {
				// Log connection failure
				Logger::error( 'Database connection failed', [ 
					'error' => $e -> getMessage( ),
					'allow_bail' => $allow_bail
				] );
				
				// Bail if allowed
				if ( $allow_bail ) {
					wp_die( 
						esc_html( $e -> getMessage( ) ),
						esc_html__( 'Database Connection Error', 'kp-db' )
					);
				}
				
				return false;
			}
		}
		
		/**
		 * Perform a MySQL database query, using current database connection
		 *
		 * @param string $query Database query
		 * @return int|bool Number of rows affected/selected or false on error
		 */
		public function query( $query ) {

			// Validate query
			if ( empty( $query ) ) {
				Logger::error( 'query called with empty query string' );
				return false;
			}
			
			// Store the query for WordPress compatibility
			$this -> last_query = $query;
			$this -> last_error = '';
			
			// Log query attempt
			Logger::debug( 'Executing query', [ 'query' => substr( $query, 0, 100 ) ] );
			
			try {
				// Determine query type
				$query_type = strtoupper( substr( trim( $query ), 0, 6 ) );
				
				// Execute based on query type
				if ( $query_type === 'SELECT' || $query_type === 'SHOW' || $query_type === 'DESCRI' ) {
				
					// SELECT, SHOW, DESCRIBE queries
					$result = $this -> kpt_db -> query( $query ) -> fetch( );

					// CRITICAL: KPT Database returns false for empty results, convert to empty array
					if ( $result === false || $result === null ) {
						$result = [];
					}
					// Convert single object to array
					elseif ( is_object( $result ) && ! is_array( $result ) ) {
						$result = [ $result ];
					}
					// Ensure it's an array
					elseif ( ! is_array( $result ) ) {
						$result = [];
					}

					// Sanitize column names (id -> ID) for WordPress compatibility
					$this -> last_result = ( $this -> sanitize_column_names( $result ) ) ?: array( );
					$this -> num_rows = count( $this -> last_result );

					// Log successful select
					// DEBUG: Log what we actually got back
					Logger::debug( 'KPT Database fetch result [POST]', [ 
						'result_type' => gettype( $result ),
						'result_is_false' => $result === false,
						'result_is_array' => is_array( $result ),
						'result_count' => is_array( $result ) ? count( $result ) : 'N/A',
						'query_snippet' => substr( $query, 0, 200 )
					] );
					//exit;

					return $this -> num_rows;
					
				} else {

					// INSERT, UPDATE, DELETE, etc.
					$affected = $this -> kpt_db -> query( $query ) -> execute( );
					
					// Store affected rows
					$this->rows_affected = is_int( $affected ) ? $affected : 0;
					
					// Store last insert ID for INSERT queries
					if ( $query_type === 'INSERT' ) {
						$this -> insert_id = $this -> kpt_db->getLastId();
						Logger::debug( 'INSERT query executed', [ 
							'insert_id' => $this -> insert_id,
							'rows_affected' => $this -> rows_affected
						] );
					} else {
						Logger::debug( "{$query_type} query executed", [ 
							'rows_affected' => $this -> rows_affected
						] );
					}
					
					return $affected;
				}
				
			} catch ( \Exception $e ) {
				// Store error message
				$this->last_error = $e -> getMessage( );
				
				// Log error
				Logger::error( 'Query execution failed', [ 
					'error' => $e -> getMessage( ),
					'query' => substr( $query, 0, 200 )
				] );
				
				// Print error if show_errors is enabled
				if ( $this -> show_errors ) {
					$this -> print_error( $this -> last_error );
				}
				
				return false;
			}
		}

		/**
		 * Sanitize column names to match WordPress expectations
		 * Add both 'id' and 'ID' properties for maximum compatibility
		 * 
		 * @param array|object|false $result Query result from KPT Database
		 * @return array Sanitized result
		 */
		private function sanitize_column_names( $result ) {
			if ( $result === false ) {
				return [];
			}
			
			// Handle single object - convert to array
			if ( is_object( $result ) && ! is_array( $result ) ) {
				$result = [ $result ];
			}
			
			// If not array at this point, return empty
			if ( ! is_array( $result ) ) {
				return [];
			}
			
			// Process each row
			$sanitized = [];
			foreach ( $result as $row ) {
				if ( is_object( $row ) ) {
					// Check if it has 'id' but not 'ID'
					if ( isset( $row->id ) && ! isset( $row->ID ) ) {
						$row->ID = $row->id;
					}
					// Check if it has 'ID' but not 'id'
					elseif ( isset( $row->ID ) && ! isset( $row->id ) ) {
						$row->id = $row->ID;
					}
				}
				$sanitized[] = $row;
			}
			
			return $sanitized;
		}

		/**
		 * Internal function to perform the mysql_query() call
		 *
		 * @param string $query SQL query
		 * @return resource|bool Query result resource or false on failure
		 */
		public function _do_query( $query ) {

			// Log internal query execution
			Logger::debug( '_do_query called', [ 'query' => substr( $query, 0, 100 ) ] );
			
			// Validate query
			if ( empty( $query ) ) {
				Logger::error( '_do_query called with empty query' );
				return false;
			}
			
			try {
				// Determine query type
				$query_type = strtoupper( substr( trim( $query ), 0, 6 ) );
				
				// Execute using KPT Database
				if ( $query_type === 'SELECT' || $query_type === 'SHOW' || $query_type === 'DESCRI' ) {
					// For SELECT-type queries, return result object
					$result = $this->kpt_db->query( $query )->fetch();
					
					// Store in format compatible with wpdb
					$this->last_result = is_array( $result ) ? $result : ( $result ? [ $result ] : [] );
					
					// Return a mock result resource (true for success)
					return true;
					
				} else {
					// For INSERT/UPDATE/DELETE, execute and return success
					$affected = $this->kpt_db->query( $query )->execute();
					
					// Store affected rows and insert ID
					$this->rows_affected = is_int( $affected ) ? $affected : 0;
					
					if ( $query_type === 'INSERT' ) {
						$this->insert_id = $this->kpt_db->getLastId();
					}
					
					// Return true for success
					return $affected !== false;
				}
				
			} catch ( \Exception $e ) {
				// Log error
				Logger::error( '_do_query failed', [ 
					'error' => $e->getMessage(),
					'query' => substr( $query, 0, 200 )
				] );
				
				// Store error
				$this->last_error = $e->getMessage();
				
				return false;
			}
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

			// Validate inputs
			if ( empty( $table ) || empty( $data ) || ! is_array( $data ) ) {
				Logger::error( 'insert called with invalid parameters', [ 
					'table' => $table,
					'data_type' => gettype( $data )
				] );
				return false;
			}
			
			// Log insert attempt
			Logger::debug( 'Attempting INSERT', [ 
				'table' => $table,
				'columns' => array_keys( $data )
			] );
			
			try {
				// Build column and placeholder lists
				$columns = array_keys( $data );
				$values = array_values( $data );
				$placeholders = array_fill( 0, count( $values ), '?' );
				
				// Build INSERT query
				$query = sprintf(
					"INSERT INTO `%s` (`%s`) VALUES (%s)",
					$table,
					implode( '`, `', $columns ),
					implode( ', ', $placeholders )
				);
				
				// Execute query using KPT Database
				$result = $this->kpt_db->query( $query )->bind( $values )->execute();
				
				// Store last insert ID
				$this->insert_id = $this->kpt_db->getLastId();
				$this->rows_affected = is_int( $result ) ? 1 : 0;
				
				// Log success
				Logger::debug( 'INSERT successful', [ 
					'table' => $table,
					'insert_id' => $this->insert_id
				] );
				
				return $this->rows_affected;
				
			} catch ( \Exception $e ) {
				// Log error
				Logger::error( 'INSERT failed', [ 
					'table' => $table,
					'error' => $e->getMessage()
				] );
				
				$this->last_error = $e->getMessage();
				
				return false;
			}
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

			// Validate inputs
			if ( empty( $table ) || empty( $data ) || ! is_array( $data ) ) {
				Logger::error( 'replace called with invalid parameters', [ 
					'table' => $table,
					'data_type' => gettype( $data )
				] );
				return false;
			}
			
			// Log replace attempt
			Logger::debug( 'Attempting REPLACE', [ 
				'table' => $table,
				'columns' => array_keys( $data )
			] );
			
			try {
				// Build column and placeholder lists
				$columns = array_keys( $data );
				$values = array_values( $data );
				$placeholders = array_fill( 0, count( $values ), '?' );
				
				// Build REPLACE query
				$query = sprintf(
					"REPLACE INTO `%s` (`%s`) VALUES (%s)",
					$table,
					implode( '`, `', $columns ),
					implode( ', ', $placeholders )
				);
				
				// Execute query using KPT Database
				$result = $this->kpt_db->query( $query )->bind( $values )->execute();
				
				// Store affected rows (REPLACE returns rows affected)
				$this->rows_affected = is_int( $result ) ? $result : 0;
				
				// Store last insert ID if available
				$this->insert_id = $this->kpt_db->getLastId();
				
				// Log success
				Logger::debug( 'REPLACE successful', [ 
					'table' => $table,
					'rows_affected' => $this->rows_affected
				] );
				
				return $this->rows_affected;
				
			} catch ( \Exception $e ) {
				// Log error
				Logger::error( 'REPLACE failed', [ 
					'table' => $table,
					'error' => $e->getMessage()
				] );
				
				$this->last_error = $e->getMessage();
				
				return false;
			}
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

			// Validate inputs
			if ( empty( $table ) || empty( $data ) || ! is_array( $data ) || empty( $where ) || ! is_array( $where ) ) {
				Logger::error( 'update called with invalid parameters', [ 
					'table' => $table,
					'data_type' => gettype( $data ),
					'where_type' => gettype( $where )
				] );
				return false;
			}
			
			// Log update attempt
			Logger::debug( 'Attempting UPDATE', [ 
				'table' => $table,
				'columns' => array_keys( $data ),
				'where_columns' => array_keys( $where )
			] );
			
			try {
				// Build SET clause
				$set_parts = array();
				$set_values = array();
				foreach ( $data as $column => $value ) {
					$set_parts[] = "`{$column}` = ?";
					$set_values[] = $value;
				}
				
				// Build WHERE clause
				$where_parts = array();
				$where_values = array();
				foreach ( $where as $column => $value ) {
					$where_parts[] = "`{$column}` = ?";
					$where_values[] = $value;
				}
				
				// Combine all values
				$all_values = array_merge( $set_values, $where_values );
				
				// Build UPDATE query
				$query = sprintf(
					"UPDATE `%s` SET %s WHERE %s",
					$table,
					implode( ', ', $set_parts ),
					implode( ' AND ', $where_parts )
				);
				
				// Execute query using KPT Database
				$result = $this->kpt_db->query( $query )->bind( $all_values )->execute();
				
				// Store affected rows
				$this->rows_affected = is_int( $result ) ? $result : 0;
				
				// Log success
				Logger::debug( 'UPDATE successful', [ 
					'table' => $table,
					'rows_affected' => $this->rows_affected
				] );
				
				return $this->rows_affected;
				
			} catch ( \Exception $e ) {
				// Log error
				Logger::error( 'UPDATE failed', [ 
					'table' => $table,
					'error' => $e->getMessage()
				] );
				
				$this->last_error = $e->getMessage();
				
				return false;
			}
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

			// Validate inputs
			if ( empty( $table ) || empty( $where ) || ! is_array( $where ) ) {
				Logger::error( 'delete called with invalid parameters', [ 
					'table' => $table,
					'where_type' => gettype( $where )
				] );
				return false;
			}
			
			// Log delete attempt
			Logger::debug( 'Attempting DELETE', [ 
				'table' => $table,
				'where_columns' => array_keys( $where )
			] );
			
			try {
				// Build WHERE clause
				$where_parts = array();
				$where_values = array();
				foreach ( $where as $column => $value ) {
					$where_parts[] = "`{$column}` = ?";
					$where_values[] = $value;
				}
				
				// Build DELETE query
				$query = sprintf(
					"DELETE FROM `%s` WHERE %s",
					$table,
					implode( ' AND ', $where_parts )
				);
				
				// Execute query using KPT Database
				$result = $this->kpt_db->query( $query )->bind( $where_values )->execute();
				
				// Store affected rows
				$this->rows_affected = is_int( $result ) ? $result : 0;
				
				// Log success
				Logger::debug( 'DELETE successful', [ 
					'table' => $table,
					'rows_affected' => $this->rows_affected
				] );
				
				return $this->rows_affected;
				
			} catch ( \Exception $e ) {
				// Log error
				Logger::error( 'DELETE failed', [ 
					'table' => $table,
					'error' => $e->getMessage()
				] );
				
				$this->last_error = $e->getMessage();
				
				return false;
			}
		}
		
		/**
		 * Process a query before sending it to the database
		 *
		 * @param string $query Query to process
		 * @return string|WP_Error Processed query or WP_Error on failure
		 */
		public function pre_query( $query ) {

			// Log pre-query processing
			Logger::debug( 'pre_query called', [ 'query' => substr( $query, 0, 100 ) ] );
			
			// Validate query
			if ( empty( $query ) ) {
				Logger::error( 'pre_query called with empty query' );
				return new \WP_Error( 'empty_query', 'Query string is empty' );
			}
			
			try {
				// Strip invalid text if configured
				if ( $this->check_current_query ) {
					$query = $this->strip_invalid_text_from_query( $query );
				}
				
				// Check for field length issues
				$table = $this->get_table_from_query( $query );
				if ( $table ) {
					// Extract data from INSERT/UPDATE queries for validation
					$query_type = strtoupper( substr( trim( $query ), 0, 6 ) );
					
					if ( in_array( $query_type, array( 'INSERT', 'UPDATE', 'REPLAC' ) ) ) {
						// Let parent handle complex field validation
						$processed = parent::pre_query( $query );
						
						if ( is_wp_error( $processed ) ) {
							Logger::error( 'pre_query validation failed', [ 
								'error' => $processed->get_error_message() 
							] );
						}
						
						return $processed;
					}
				}
				
				// Log successful pre-query processing
				Logger::debug( 'pre_query processing complete' );
				
				return $query;
				
			} catch ( \Exception $e ) {
				// Log error
				Logger::error( 'pre_query failed', [ 
					'error' => $e->getMessage(),
					'query' => substr( $query, 0, 200 )
				] );
				
				return new \WP_Error( 'query_processing_error', $e->getMessage() );
			}
		}

		/**
		 * Get multiple rows from the database
		 *
		 * @param string|null $query SQL query
		 * @param string $output Optional. Output type. OBJECT, OBJECT_K, ARRAY_A, or ARRAY_N. Default OBJECT
		 * @return array|object|null Database query results or null on failure
		 */
		public function get_results( $query = null, $output = OBJECT ) {

			// If query provided, execute it first
			if ( $query ) {
				// Log query execution
				Logger::debug( 'get_results executing query', [ 
					'query' => substr( $query, 0, 100 ),
					'output' => $output
				] );
				
				$this->query( $query );
			}
			
			// If no results from last query, return null
			if ( ! $this->last_result ) {
				Logger::debug( 'get_results: no results available' );
				return array( );
			}
			
			// Log results retrieval
			Logger::debug( 'get_results returning results', [ 
				'num_rows' => count( $this->last_result ),
				'output_type' => $output
			] );
			
			// Convert results based on output type
			try {
				$results = array();
				
				foreach ( $this->last_result as $row ) {
					switch ( $output ) {
						case ARRAY_A:
							// Convert to associative array
							$results[] = (array) $row;
							break;
							
						case ARRAY_N:
							// Convert to numeric array
							$results[] = array_values( (array) $row );
							break;
							
						case OBJECT_K:
							// Use first column as key
							$row_array = (array) $row;
							$key = array_shift( $row_array );
							$results[ $key ] = (object) $row_array;
							break;
							
						case OBJECT:
						default:
							// Return as object (default)
							$results[] = $row;
							break;
					}
				}
				
				return $results;
				
			} catch ( \Exception $e ) {
				// Log error
				Logger::error( 'get_results failed to format output', [ 
					'error' => $e->getMessage(),
					'output' => $output
				] );
				
				return array( );
			}
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

			// If query provided, execute it first
			if ( $query ) {
				// Log query execution
				Logger::debug( 'get_row executing query', [ 
					'query' => substr( $query, 0, 100 ),
					'output' => $output,
					'offset' => $y
				] );
				
				$this->query( $query );
			}
			
			// If no results from last query, return null
			// Check for empty array explicitly, not just falsy check
			if ( empty( $this->last_result ) || ! is_array( $this->last_result ) ) {
				Logger::debug( 'get_row: no results available' );
				return null;
			}
			
			// Check if requested row exists
			if ( ! isset( $this->last_result[ $y ] ) ) {
				Logger::debug( 'get_row: requested row offset does not exist', [ 'offset' => $y ] );
				return null;
			}
			
			// Get the row at offset y
			$row = $this->last_result[ $y ];
			
			// Log row retrieval
			Logger::debug( 'get_row returning row', [ 
				'offset' => $y,
				'output_type' => $output
			] );
			
			// Convert row based on output type
			try {
				switch ( $output ) {
					case ARRAY_A:
						// Convert to associative array
						return (array) $row;
						
					case ARRAY_N:
						// Convert to numeric array
						return array_values( (array) $row );
						
					case OBJECT:
					default:
						// Return as object (default)
						return $row;
				}
				
			} catch ( \Exception $e ) {
				// Log error
				Logger::error( 'get_row failed to format output', [ 
					'error' => $e->getMessage(),
					'output' => $output
				] );
				
				return null;
			}
		}
		
		/**
		 * Get one column from the database
		 *
		 * @param string|null $query SQL query
		 * @param int $x Optional. Column offset. Default 0
		 * @return array Database query results
		 */
		public function get_col( $query = null, $x = 0 ) {

			// If query provided, execute it first
			if ( $query ) {
				// Log query execution
				Logger::debug( 'get_col executing query', [ 
					'query' => substr( $query, 0, 100 ),
					'column_offset' => $x
				] );
				
				$this->query( $query );
			}
			
			// If no results from last query, return empty array
			if ( empty( $this -> last_result ) || ! is_array( $this -> last_result ) ) {
				Logger::debug( 'get_col: no results available' );
				return array();
			}
			
			// Log column retrieval
			Logger::debug( 'get_col extracting column', [ 
				'num_rows' => count( $this->last_result ),
				'column_offset' => $x
			] );
			
			// Extract the specified column from all rows
			try {
				$column = array();
				
				foreach ( $this->last_result as $row ) {
					// Convert row to array
					$row_array = (array) $row;
					$values = array_values( $row_array );
					
					// Get the column at offset x if it exists
					if ( isset( $values[ $x ] ) ) {
						$column[] = $values[ $x ];
					}
				}
				
				// Log success
				Logger::debug( 'get_col returning column', [ 
					'column_count' => count( $column )
				] );
				
				return $column;
				
			} catch ( \Exception $e ) {
				// Log error
				Logger::error( 'get_col failed', [ 
					'error' => $e->getMessage(),
					'column_offset' => $x
				] );
				
				return array();
			}
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

			// If query provided, execute it first
			if ( $query ) {
				// Log query execution
				Logger::debug( 'get_var executing query', [ 
					'query' => substr( $query, 0, 100 ),
					'column_offset' => $x,
					'row_offset' => $y
				] );
				
				$this->query( $query );
			}
			
			// If no results from last query, return null
			if ( empty( $this->last_result ) || ! is_array( $this->last_result ) ) {
				Logger::debug( 'get_var: no results available' );
				return null;
			}
			
			// Check if requested row exists
			if ( ! isset( $this->last_result[ $y ] ) ) {
				Logger::debug( 'get_var: requested row offset does not exist', [ 'offset' => $y ] );
				return null;
			}
			
			// Get the row at offset y
			$row = $this->last_result[ $y ];
			
			// Convert row to array and get values
			$row_array = (array) $row;
			$values = array_values( $row_array );
			
			// Check if requested column exists
			if ( ! isset( $values[ $x ] ) ) {
				Logger::debug( 'get_var: requested column offset does not exist', [ 'offset' => $x ] );
				return null;
			}
			
			// Get the value
			$value = $values[ $x ];
			
			// Log variable retrieval
			Logger::debug( 'get_var returning value', [ 
				'column_offset' => $x,
				'row_offset' => $y,
				'value_type' => gettype( $value )
			] );
			
			return $value;
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
				if ( $this->kpt_db ) {
					$this->kpt_db->reset();
					
					// Set to null to allow garbage collection
					$this->kpt_db = null;
					
					Logger::debug( 'KPT Database connection closed successfully' );
				}
				
				// Clear all stored data
				$this->flush();
				
				// Call parent close method for compatibility
				$result = parent::close();
				
				// Log successful close
				Logger::debug( 'Database connection closed', [ 'result' => $result ] );
				
				return $result;
				
			} catch ( \Exception $e ) {
				// Log error
				Logger::error( 'Failed to close database connection', [ 
					'error' => $e->getMessage() 
				] );
				
				return false;
			}
		}

	}

}
