<?php
/**
 * WPDB Replacement class
 *
 * @package WP_KPT_Database
 */

namespace KPT\WordPress;

use KPT\Database;
use KPT\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
		$this->original_wpdb = $wpdb;

		// Copy wpdb properties.
		foreach ( get_object_vars( $wpdb ) as $key => $value ) {
			$this->$key = $value;
		}

		// Initialize KPT Database.
		$this->init_kpt_database();

		// Replace global wpdb.
		$this->replace_global_wpdb();
	}

	/**
	 * Initialize KPT Database
	 */
	private function init_kpt_database() {
		// Get charset and collation from WordPress config
		$charset = defined( 'DB_CHARSET' ) && ! empty( DB_CHARSET ) ? DB_CHARSET : 'utf8mb4';
		$collation = defined( 'DB_COLLATE' ) && ! empty( DB_COLLATE ) ? DB_COLLATE : '';
		
		// If no collation is set, use the appropriate default for the charset
		if ( empty( $collation ) ) {
			$collation = match ( $charset ) {
				'utf8mb4' => 'utf8mb4_unicode_ci',
				'utf8mb3' => 'utf8mb3_unicode_ci',
				'utf8' => 'utf8_unicode_ci',
				default => 'utf8mb4_unicode_ci',
			};
		}
		
		// Validate charset/collation compatibility
		$collation_prefix = strstr( $collation, '_', true );
		if ( $collation_prefix !== $charset && $collation_prefix !== false ) {
			// Use default collation for charset
			$collation = match ( $charset ) {
				'utf8mb4' => 'utf8mb4_unicode_ci',
				'utf8mb3' => 'utf8mb3_unicode_ci',
				'utf8' => 'utf8_unicode_ci',
				default => 'utf8mb4_unicode_ci',
			};
		}
		
		$db_settings = (object) array(
			'server'    => DB_HOST,
			'schema'    => DB_NAME,
			'username'  => DB_USER,
			'password'  => DB_PASSWORD,
			'charset'   => $charset,
			'collation' => $collation,
		);

		try {
			$this->kpt_db = new Database( $db_settings );
		} catch ( \Exception $e ) {
			Logger::error( 'Failed to initialize KPT Database', array(
				'error' => $e->getMessage(),
				'settings' => array(
					'server' => $db_settings->server,
					'schema' => $db_settings->schema,
					'charset' => $db_settings->charset,
					'collation' => $db_settings->collation,
				)
			) );
			
			wp_die(
				esc_html( $e->getMessage() ),
				esc_html__( 'Database Connection Error', 'wp-kpt-database' )
			);
		}
	}
	/**
	 * Replace global wpdb
	 */
	private function replace_global_wpdb() {
		global $wpdb;
		$wpdb = $this;
	}

	/**
	 * Perform database query
	 *
	 * @param string $query Database query.
	 * @return int|bool Number of rows affected/selected or false on error.
	 */
	public function query( $query ) {
		if ( ! $this->kpt_db ) {
			return $this->original_wpdb->query( $query );
		}

		// Validate query is not empty
		if ( empty( $query ) || ! is_string( $query ) ) {
			$this->last_error = 'Empty or invalid query';
			Logger::error( 'Empty or invalid query provided to WPDB_Replacement::query' );
			return false;
		}

		$this->flush();
		$this->func_call = "\$db->query(\"$query\")";
		$this->last_query = $query;

		// Log the query for debugging
		Logger::debug( 'WPDB_Replacement executing query', array( 'query' => $query ) );

		try {
			$query_type = $this->get_query_type( $query );

			switch ( $query_type ) {
				case 'SELECT':
					$this->result = $this->kpt_db->raw( $query );
					if ( false !== $this->result ) {
						$this->num_rows = is_array( $this->result ) ? count( $this->result ) : 0;
						return $this->num_rows;
					}
					return false;

				case 'INSERT':
				case 'REPLACE':
					$result = $this->kpt_db->raw( $query );
					if ( false !== $result ) {
						$this->insert_id = is_numeric( $result ) ? (int) $result : 0;
						$this->rows_affected = 1;
						return 1;
					}
					return false;

				case 'UPDATE':
				case 'DELETE':
					$result = $this->kpt_db->raw( $query );
					if ( false !== $result && is_numeric( $result ) ) {
						$this->rows_affected = (int) $result;
						return $this->rows_affected;
					}
					return false;

				default:
					$result = $this->kpt_db->raw( $query );
					return false !== $result ? true : false;
			}
		} catch ( \Exception $e ) {
			$this->last_error = $e->getMessage();
			Logger::error( 'WPDB_Replacement query error', array(
				'query' => $query,
				'error' => $e->getMessage(),
			) );
			return false;
		}
	}

	/**
	 * Retrieve one variable from the database
	 *
	 * @param string   $query Database query.
	 * @param int      $x     Column of value to return. Indexed from 0.
	 * @param int      $y     Row of value to return. Indexed from 0.
	 * @return mixed Database query result or null on failure.
	 */
	public function get_var( $query = null, $x = 0, $y = 0 ) {
		if ( ! $this->kpt_db ) {
			return $this->original_wpdb->get_var( $query, $x, $y );
		}

		$this->func_call = "\$db->get_var(\"$query\",$x,$y)";

		if ( $query ) {
			$this->query( $query );
		}

		if ( ! $this->result || ! is_array( $this->result ) || ! isset( $this->result[ $y ] ) ) {
			return null;
		}

		$row = $this->result[ $y ];
		$row_array = is_object( $row ) ? get_object_vars( $row ) : (array) $row;
		$values = array_values( $row_array );

		return isset( $values[ $x ] ) ? $values[ $x ] : null;
	}

	/**
	 * Retrieve one row from the database
	 *
	 * @param string $query  Database query.
	 * @param string $output Output type. OBJECT, ARRAY_A, or ARRAY_N.
	 * @param int    $y      Row to return. Indexed from 0.
	 * @return mixed Database query result in format specified by $output or null on failure.
	 */
	public function get_row( $query = null, $output = OBJECT, $y = 0 ) {
		if ( ! $this->kpt_db ) {
			return $this->original_wpdb->get_row( $query, $output, $y );
		}

		$this->func_call = "\$db->get_row(\"$query\",$output,$y)";

		if ( $query ) {
			$this->query( $query );
		}

		if ( ! $this->result || ! is_array( $this->result ) || ! isset( $this->result[ $y ] ) ) {
			return null;
		}

		$row = $this->result[ $y ];

		if ( ARRAY_N === $output ) {
			$row_array = is_object( $row ) ? get_object_vars( $row ) : (array) $row;
			return array_values( $row_array );
		} elseif ( ARRAY_A === $output ) {
			return is_object( $row ) ? get_object_vars( $row ) : (array) $row;
		} else {
			return is_object( $row ) ? $row : (object) $row;
		}
	}

	/**
	 * Retrieve one column from the database
	 *
	 * @param string $query Database query.
	 * @param int    $x     Column to return. Indexed from 0.
	 * @return array Database query result. Array indexed from 0 by SQL result row number.
	 */
	public function get_col( $query = null, $x = 0 ) {
		if ( ! $this->kpt_db ) {
			return $this->original_wpdb->get_col( $query, $x );
		}

		$this->func_call = "\$db->get_col(\"$query\",$x)";

		if ( $query ) {
			$this->query( $query );
		}

		$new_array = array();

		if ( ! $this->result || ! is_array( $this->result ) ) {
			return $new_array;
		}

		foreach ( $this->result as $row ) {
			$row_array = is_object( $row ) ? get_object_vars( $row ) : (array) $row;
			$values = array_values( $row_array );
			if ( isset( $values[ $x ] ) ) {
				$new_array[] = $values[ $x ];
			}
		}

		return $new_array;
	}

	/**
	 * Retrieve entire result set from the database
	 *
	 * @param string $query  Database query.
	 * @param string $output Output type. OBJECT, ARRAY_A, or ARRAY_N.
	 * @return mixed Database query results.
	 */
	public function get_results( $query = null, $output = OBJECT ) {
		if ( ! $this->kpt_db ) {
			return $this->original_wpdb->get_results( $query, $output );
		}

		$this->func_call = "\$db->get_results(\"$query\", $output)";

		if ( $query ) {
			$this->query( $query );
		}

		// Handle null or false results
		if ( ! $this->result ) {
			return array(); // Return empty array instead of null
		}

		if ( ! is_array( $this->result ) ) {
			return array();
		}

		$new_array = array();

		if ( ARRAY_N === $output || ARRAY_A === $output ) {
			foreach ( $this->result as $row ) {
				$row_array = is_object( $row ) ? get_object_vars( $row ) : (array) $row;
				if ( ARRAY_N === $output ) {
					$new_array[] = array_values( $row_array );
				} else {
					$new_array[] = $row_array;
				}
			}
			return $new_array;
		} elseif ( OBJECT_K === $output ) {
			// OBJECT_K uses first column as key
			foreach ( $this->result as $row ) {
				$row_object = is_object( $row ) ? $row : (object) $row;
				$vars = get_object_vars( $row_object );
				$key = array_shift( $vars );
				$new_array[ $key ] = $row_object;
			}
			return $new_array;
		} else {
			foreach ( $this->result as $row ) {
				$new_array[] = is_object( $row ) ? $row : (object) $row;
			}
			return $new_array;
		}
	}

	/**
	 * Prepare SQL query for safe execution
	 *
	 * @param string $query   Query statement with sprintf()-like placeholders.
	 * @param mixed  ...$args The array of variables to substitute into the query's placeholders.
	 * @return string|void Sanitized query string, if there is a query to prepare.
	 */
	public function prepare( $query, ...$args ) {
		return $this->original_wpdb->prepare( $query, ...$args );
	}

	/**
	 * Insert row into table
	 *
	 * @param string       $table  Table name.
	 * @param array        $data   Data to insert (column => value pairs).
	 * @param array|string $format Optional. Format for each value.
	 * @return int|false The number of rows inserted, or false on error.
	 */
	public function insert( $table, $data, $format = null ) {
		return $this->kpt_insert_replace_helper( $table, $data, $format, 'INSERT' );
	}

	/**
	 * Replace row in table
	 *
	 * @param string       $table  Table name.
	 * @param array        $data   Data to replace (column => value pairs).
	 * @param array|string $format Optional. Format for each value.
	 * @return int|false The number of rows affected, or false on error.
	 */
	public function replace( $table, $data, $format = null ) {
		return $this->kpt_insert_replace_helper( $table, $data, $format, 'REPLACE' );
	}

	/**
	 * Update row in table
	 *
	 * @param string       $table        Table name.
	 * @param array        $data         Data to update (column => value pairs).
	 * @param array        $where        WHERE clause (column => value pairs).
	 * @param array|string $format       Optional. Format for each value in $data.
	 * @param array|string $where_format Optional. Format for each value in $where.
	 * @return int|false The number of rows updated, or false on error.
	 */
	public function update( $table, $data, $where, $format = null, $where_format = null ) {
		if ( ! is_array( $data ) || ! is_array( $where ) ) {
			return false;
		}

		$data = $this->process_fields( $table, $data, $format );
		$where = $this->process_fields( $table, $where, $where_format );

		if ( false === $data || false === $where ) {
			return false;
		}

		$fields = array();
		$conditions = array();
		$values = array();

		foreach ( $data as $field => $value ) {
			$fields[] = "`$field` = ?";
			// Handle arrays/objects by serializing
			if ( is_array( $value ) || is_object( $value ) ) {
				$values[] = maybe_serialize( $value );
			} else {
				$values[] = $value;
			}
		}

		foreach ( $where as $field => $value ) {
			$conditions[] = "`$field` = ?";
			if ( is_array( $value ) || is_object( $value ) ) {
				$values[] = maybe_serialize( $value );
			} else {
				$values[] = $value;
			}
		}

		$sql = "UPDATE `$table` SET " . implode( ', ', $fields ) . ' WHERE ' . implode( ' AND ', $conditions );

		try {
			return $this->kpt_db->query( $sql )->bind( $values )->execute();
		} catch ( \Exception $e ) {
			$this->last_error = $e->getMessage();
			Logger::error( 'Update error', array(
				'table' => $table,
				'error' => $e->getMessage(),
			) );
			return false;
		}
	}

	/**
	 * Delete row from table
	 *
	 * @param string       $table        Table name.
	 * @param array        $where        WHERE clause (column => value pairs).
	 * @param array|string $where_format Optional. Format for each value in $where.
	 * @return int|false The number of rows deleted, or false on error.
	 */
	public function delete( $table, $where, $where_format = null ) {
		if ( ! is_array( $where ) ) {
			return false;
		}

		$where = $this->process_fields( $table, $where, $where_format );

		if ( false === $where ) {
			return false;
		}

		$conditions = array();
		$values = array();

		foreach ( $where as $field => $value ) {
			$conditions[] = "`$field` = ?";
			$values[] = $value;
		}

		$sql = "DELETE FROM `$table` WHERE " . implode( ' AND ', $conditions );

		try {
			return $this->kpt_db->query( $sql )->bind( $values )->execute();
		} catch ( \Exception $e ) {
			$this->last_error = $e->getMessage();
			return false;
		}
	}

	/**
	 * Helper function for insert and replace
	 *
	 * @param string       $table  Table name.
	 * @param array        $data   Data to insert/replace.
	 * @param array|string $format Optional. Format for each value.
	 * @param string       $type   Type of operation (INSERT or REPLACE).
	 * @return int|false The number of rows affected, or false on error.
	 */
	private function kpt_insert_replace_helper( $table, $data, $format, $type ) {
		if ( ! is_array( $data ) ) {
			return false;
		}

		$data = $this->process_fields( $table, $data, $format );

		if ( false === $data ) {
			return false;
		}

		$fields = array_keys( $data );
		$values = array();
		
		// Handle serialized data
		foreach ( $data as $value ) {
			if ( is_array( $value ) || is_object( $value ) ) {
				$values[] = maybe_serialize( $value );
			} else {
				$values[] = $value;
			}
		}
		
		$placeholders = array_fill( 0, count( $values ), '?' );

		$sql = "$type INTO `$table` (`" . implode( '`, `', $fields ) . '`) VALUES (' . implode( ', ', $placeholders ) . ')';

		try {
			$result = $this->kpt_db->query( $sql )->bind( $values )->execute();

			if ( false !== $result && 'INSERT' === $type ) {
				$this->insert_id = is_numeric( $result ) ? (int) $result : $this->kpt_db->getLastId();
			}

			return is_numeric( $result ) ? 1 : $result;
		} catch ( \Exception $e ) {
			$this->last_error = $e->getMessage();
			Logger::error( 'Insert/Replace error', array(
				'table' => $table,
				'type' => $type,
				'error' => $e->getMessage(),
			) );
			return false;
		}
	}

	/**
	 * Process fields for database operations
	 *
	 * @param string       $table  Table name.
	 * @param array        $data   Data to process.
	 * @param array|string $format Optional. Format for each value.
	 * @return array|false Processed data or false on error.
	 */
	protected function process_fields( $table, $data, $format ) {
		$data = $this->process_field_formats( $data, $format );
		if ( false === $data ) {
			return false;
		}

		$data = $this->process_field_charsets( $data, $table );
		if ( false === $data ) {
			return false;
		}

		return $data;
	}

	/**
	 * Get query type
	 *
	 * @param string $query SQL query.
	 * @return string Query type.
	 */
	private function get_query_type( $query ) {
		$query = trim( $query );
		$first_word = strtoupper( substr( $query, 0, strpos( $query . ' ', ' ' ) ) );

		switch ( $first_word ) {
			case 'SELECT':
			case 'SHOW':
			case 'DESCRIBE':
			case 'DESC':
			case 'EXPLAIN':
				return 'SELECT';
			case 'INSERT':
				return 'INSERT';
			case 'REPLACE':
				return 'REPLACE';
			case 'UPDATE':
				return 'UPDATE';
			case 'DELETE':
				return 'DELETE';
			default:
				return 'OTHER';
		}
	}

	/**
	 * Flush cached query results
	 */
	public function flush() {
		$this->last_result = array();
		$this->col_info = null;
		$this->last_query = null;
		$this->rows_affected = 0;
		$this->num_rows = 0;
		$this->last_error = '';

		if ( is_resource( $this->result ) ) {
			// phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysqli_free_result
			mysqli_free_result( $this->result );
		}
	}

	/**
	 * Retrieves the character set for the given table.
	 *
	 * @param string $table Table name.
	 * @return string|WP_Error The table character set, or a WP_Error object if the table doesn't exist.
	 */
	public function get_table_charset( $table ) {
		if ( method_exists( $this->original_wpdb, 'get_table_charset' ) ) {
			return $this->original_wpdb->get_table_charset( $table );
		}
		return 'utf8mb4';
	}

	/**
	 * Retrieves the character set for the given column.
	 *
	 * @param string $table  Table name.
	 * @param string $column Column name.
	 * @return string|false|WP_Error Column character set. False if the column has no character set. WP_Error object if there was an error.
	 */
	public function get_col_charset( $table, $column ) {
		if ( method_exists( $this->original_wpdb, 'get_col_charset' ) ) {
			return $this->original_wpdb->get_col_charset( $table, $column );
		}
		return 'utf8mb4';
	}

	/**
	 * Check if a table exists
	 *
	 * @param string $table Table name.
	 * @return bool True if table exists, false otherwise.
	 */
	public function table_exists( $table ) {
		$result = $this->get_var( $this->prepare( 'SHOW TABLES LIKE %s', $table ) );
		return ! empty( $result );
	}

	/**
	 * Strips any invalid characters from the query.
	 *
	 * @param string $query Query to convert.
	 * @return string Sanitized query.
	 */
	public function strip_invalid_text_from_query( $query ) {
		if ( method_exists( $this->original_wpdb, 'strip_invalid_text_from_query' ) ) {
			return $this->original_wpdb->strip_invalid_text_from_query( $query );
		}
		return $query;
	}

	/**
	 * Check if the connection is alive
	 *
	 * @param bool $allow_bail Optional. Allows the function to bail. Default true.
	 * @return bool True if connection is alive.
	 */
	public function check_connection( $allow_bail = true ) {
		if ( $this->kpt_db ) {
			return true;
		}
		
		// If connection is dead and we allow bail, try to reconnect
		if ( $allow_bail ) {
			$this->init_kpt_database();
			return $this->kpt_db !== null;
		}
		
		return false;
	}

	/**
	 * Get the database server info
	 *
	 * @return string Database server info.
	 */
	public function db_version() {
		if ( method_exists( $this->original_wpdb, 'db_version' ) ) {
			return $this->original_wpdb->db_version();
		}
		return 'Unknown';
	}

}