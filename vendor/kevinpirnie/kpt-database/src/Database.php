<?php

/**
 * This is our database class
 *
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 *
 */

// throw it under my namespace
namespace KPT;

// if the class is not already in userspace
if (! class_exists('Database')) {

    /**
     * Class Database
     *
     * Database Class
     *
     * @since 8.4
     * @access public
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Library
     *
     * @property protected $db_handle: The database handle used throughout the class
     * @property protected $current_query: The current query being built
     * @property protected $query_params: Parameters for the current query
     * @property protected $fetch_mode: The fetch mode for the current query
     *
     */
    class Database
    {
        // hold the database handle object
        protected ?\PDO $db_handle = null;

        // query builder properties
        protected string $current_query = '';
        protected array $query_params = [];
        protected int $fetch_mode = \PDO::FETCH_OBJ;
        protected bool $fetch_single = false;

        // optimization properties
        protected object $db_settings;
        protected bool $is_connected = false;
        protected string $driver = 'mysql';

        /**
         * __construct
         *
         * Initialize the database connection
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         *
         * @param object $db_settings Database configuration settings (required)
         * @return void
         * @throws \InvalidArgumentException When $db_settings is null or invalid
         */
        public function __construct(object $db_settings)
        {
            // validate settings first
            self::validateSettings($db_settings);

            $this->db_settings = $db_settings;
            $this->driver = $db_settings->driver ?? 'mysql';

            // Apply driver defaults
            $this->applyDriverDefaults();

            // Lazy connection - only connect when needed for performance
            Logger::debug("Database Constructor Completed Successfully");
        }

        /**
         * applyDriverDefaults - Apply database-specific defaults
         *
         * @return void
         */
        protected function applyDriverDefaults(): void
        {
            $defaults = match ($this->driver) {
                'mysql' => ['charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'port' => 3306],
                'pgsql' => ['charset' => 'UTF8', 'port' => 5432],
                'sqlsrv' => ['charset' => 'UTF-8', 'port' => 1433],
                'oci' => ['charset' => 'AL32UTF8', 'port' => 1521],
                'sqlite' => [],
                default => []
            };

            foreach ($defaults as $key => $value) {
                if (!isset($this->db_settings->$key)) {
                    $this->db_settings->$key = $value;
                }
            }
        }

        /**
         * connect - Lazy connection with optimized setup
         *
         * @return void
         * @throws \Exception
         */
        protected function connect(): void
        {
            if ($this->is_connected) {
                return;
            }

            try {
                // Build DSN based on driver
                $dsn = $this->buildDsn();

                // Create PDO with optimized attributes
                $this->db_handle = new \PDO(
                    $dsn,
                    $this->db_settings->username ?? '',
                    $this->db_settings->password ?? '',
                    $this->getOptimizedAttributes()
                );

                // Driver-specific configuration
                $this->configureDriver();

                $this->is_connected = true;

                Logger::debug("Database Connection Established");
            } catch (\Exception $e) {
                Logger::error("Database Connection Failed", ['message' => $e->getMessage()]);
                throw $e;
            }
        }

        /**
         * buildDsn - Build DSN string for different database drivers
         *
         * @return string
         */
        protected function buildDsn(): string
        {
            $s = $this->db_settings;

            return match ($this->driver) {
                'mysql' => sprintf(
                    "mysql:host=%s;port=%d;dbname=%s;charset=%s",
                    $s->server ?? $s->host ?? 'localhost',
                    $s->port ?? 3306,
                    $s->schema ?? $s->database ?? '',
                    $s->charset ?? 'utf8mb4'
                ),

                'pgsql' => sprintf(
                    "pgsql:host=%s;port=%d;dbname=%s",
                    $s->server ?? $s->host ?? 'localhost',
                    $s->port ?? 5432,
                    $s->schema ?? $s->database ?? ''
                ),

                'sqlsrv' => sprintf(
                    "sqlsrv:Server=%s,%d;Database=%s",
                    $s->server ?? $s->host ?? 'localhost',
                    $s->port ?? 1433,
                    $s->schema ?? $s->database ?? ''
                ),

                'sqlite' => sprintf(
                    "sqlite:%s",
                    $s->path ?? $s->database ?? ':memory:'
                ),

                'oci' => sprintf(
                    "oci:dbname=//%s:%d/%s;charset=%s",
                    $s->server ?? $s->host ?? 'localhost',
                    $s->port ?? 1521,
                    $s->schema ?? $s->database ?? '',
                    $s->charset ?? 'AL32UTF8'
                ),

                default => throw new \RuntimeException("Unsupported database driver: {$this->driver}")
            };
        }

        /**
         * getOptimizedAttributes - Get optimized PDO attributes
         *
         * @return array
         */
        protected function getOptimizedAttributes(): array
        {
            $base = [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_OBJ,
                \PDO::ATTR_EMULATE_PREPARES => false,
                \PDO::ATTR_CASE => \PDO::CASE_NATURAL,
            ];

            $driver_attrs = match ($this->driver) {
                'mysql' => [
                    \PDO::ATTR_PERSISTENT => true,
                    \PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
                    \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$this->db_settings->charset}",
                ],
                'pgsql' => [\PDO::ATTR_PERSISTENT => true],
                'sqlsrv' => [\PDO::SQLSRV_ATTR_ENCODING => \PDO::SQLSRV_ENCODING_UTF8],
                'sqlite' => [\PDO::ATTR_TIMEOUT => 5],
                default => []
            };

            return array_merge($base, $driver_attrs);
        }

        /**
         * configureDriver - Post-connection driver configuration
         *
         * @return void
         */
        protected function configureDriver(): void
        {
            if (!$this->db_handle) {
                return;
            }

            switch ($this->driver) {
                case 'mysql':
                    $charset = $this->db_settings->charset ?? 'utf8mb4';
                    $collation = $this->db_settings->collation ?? 'utf8mb4_unicode_ci';
                    $this->db_handle->exec(
                        "SET NAMES $charset COLLATE $collation, 
                        CHARACTER SET $charset, 
                        collation_connection = $collation"
                    );
                    break;

                case 'pgsql':
                    $charset = $this->db_settings->charset ?? 'UTF8';
                    $this->db_handle->exec("SET NAMES '$charset'");
                    break;

                case 'sqlite':
                    $this->db_handle->exec("PRAGMA synchronous = NORMAL");
                    $this->db_handle->exec("PRAGMA journal_mode = WAL");
                    $this->db_handle->exec("PRAGMA temp_store = MEMORY");
                    break;
            }
        }

        /**
         * configure
         *
         * Static method to create a configured Database instance
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         *
         * @param array|object $config Database configuration (array or object)
         * @return self Returns configured Database instance
         * @throws \InvalidArgumentException When configuration is invalid
         */
        public static function configure(array|object $config): self
        {
            // if array is passed, convert to object
            if (is_array($config)) {
                $config = (object) $config;
            }

            // validate settings before attempting to create instance
            self::validateSettings($config);

            // debug logging
            Logger::debug("Database Configure Settings Validated Successfully");

            // create and return new instance (validation already done)
            return new self($config);
        }

        /**
         * validateSettings
         *
         * Validate database configuration settings
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         *
         * @param object $db_settings Database configuration settings to validate
         * @return void
         * @throws \InvalidArgumentException When settings are invalid
         */
        private static function validateSettings(object $db_settings): void
        {
            // validate that db_settings is provided
            if ($db_settings === null) {
                Logger::error("Database Validation Failed - No database settings provided");
                throw new \InvalidArgumentException('Database settings are required.');
            }

            // Get driver
            $driver = $db_settings->driver ?? 'mysql';

            // Driver-specific validation
            $required_properties = match ($driver) {
                'sqlite' => [],
                default => ['server', 'schema', 'username', 'password']
            };

            // validate required properties exist
            foreach ($required_properties as $property) {
                if (!property_exists($db_settings, $property)) {
                    Logger::error("Database Validation Failed - Missing required property", [
                        'missing_property' => $property,
                        'provided_properties' => array_keys(get_object_vars($db_settings))
                    ]);

                    throw new \InvalidArgumentException("Database settings missing required property: {$property}");
                }
            }
        }

        /**
         * __destruct
         *
         * Clean up the database connection
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         *
         * @return void
         */
        public function __destruct()
        {

            // try to clean up
            try {
                // reset
                $this->reset();

                // close the connection
                $this->db_handle = null;

                // clear em our
                unset($this->db_handle);

                // debug logging
                Logger::debug("Database Destructor Completed Successfully");

            // whoopsie...
            } catch (\Exception $e) {
                // error logging
                Logger::error("Database Destructor Error", [
                    'message' => $e -> getMessage(),
                ]);
            }
        }

        /**
         * query
         *
         * Set the query to be executed
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         *
         * @param string $query The SQL query to prepare
         * @return self Returns self for method chaining
         */
        public function query(string $query): self
        {

            // reset the query builder state inline for performance
            $this->current_query = $query;
            $this->query_params = [];
            $this->fetch_mode = \PDO::FETCH_OBJ;
            $this->fetch_single = false;

            // debug logging
            Logger::debug("Database Query Stored Successfully", []);

            // return self for chaining
            return $this;
        }

        /**
         * bind
         *
         * Bind parameters for the current query
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         *
         * @param array|mixed $params Parameters to bind (array or single value)
         * @return self Returns self for method chaining
         */
        public function bind(mixed $params): self
        {

            // if single value passed, wrap in array
            if (! is_array($params)) {
                $params = [ $params ];
            }

            // store the parameters
            $this->query_params = $params;

            // debug logging
            Logger::debug("Database Parameters Bound Successfully", [
                'param_count' => count($this -> query_params),
                'param_types' => array_map('gettype', $this -> query_params)
            ]);

            // return self for chaining
            return $this;
        }

        /**
         * single
         *
         * Set fetch mode to return single record
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         *
         * @return self Returns self for method chaining
         */
        public function single(): self
        {

            // set fetch single flag
            $this -> fetch_single = true;

            // return self for chaining
            return $this;
        }

        /**
         * many
         *
         * Set fetch mode to return multiple records (default)
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         *
         * @return self Returns self for method chaining
         */
        public function many(): self
        {

            // set fetch single flag
            $this -> fetch_single = false;

            // return self for chaining
            return $this;
        }

        /**
         * asArray
         *
         * Set fetch mode to return arrays instead of objects
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         *
         * @return self Returns self for method chaining
         */
        public function asArray(): self
        {

            // set fetch mode to array
            $this -> fetch_mode = \PDO::FETCH_ASSOC;

            // return self for chaining
            return $this;
        }

        /**
         * asObject
         *
         * Set fetch mode to return objects (default)
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         *
         * @return self Returns self for method chaining
         */
        public function asObject(): self
        {

            // set fetch mode to object
            $this -> fetch_mode = \PDO::FETCH_OBJ;

            // return self for chaining
            return $this;
        }

        /**
         * fetch
         *
         * Execute SELECT query and fetch results
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         *
         * @param ?int $limit Optional limit for number of records
         * @return mixed Returns query results (object/array/bool)
         */
        public function fetch(?int $limit = null): mixed
        {

            // validate we have a query
            if (empty($this -> current_query)) {
                // error logging
                Logger::error("Database Fetch Failed - No Query Set");

                throw new \RuntimeException('No query has been set. Call query() first.');
            }

            // Ensure connection
            if (!$this->is_connected) {
                $this->connect();
            }

            // if limit is provided, determine fetch mode
            if ($limit === 1) {
                // set the single property
                $this -> fetch_single = true;

                // debug logging
                Logger::debug("Database Fetch Mode Auto-Set to Single (limit=1)");

            // otherwise
            } elseif ($limit > 1) {
                // set it false
                $this -> fetch_single = false;

                // debug logging
                Logger::debug("Database Fetch Mode Auto-Set to Many", [
                    'limit' => $limit
                ]);
            }

            // try to execute the query
            try {
                // prepare the statement
                $stmt = $this -> db_handle -> prepare($this -> current_query);

                // bind parameters if we have any
                $this -> bindParams($stmt, $this -> query_params);

                // execute the query
                if (! $stmt -> execute()) {
                    // error logging
                    Logger::error("Database Query Execution Failed");

                    return false;
                }

                // fetch based on mode - optimized
                $result = $this->fetch_single
                    ? $stmt->fetch($this->fetch_mode)
                    : $stmt->fetchAll($this->fetch_mode);

                // close the cursor
                $stmt->closeCursor();

                // debug logging
                Logger::debug($this->fetch_single ? "Database Single Record Fetched" :
                    "Database Multiple Records Fetched", [
                    'has_result' => !empty($result),
                    'result_count' => is_array($result) ? count($result) : 0
                ]);

                // return the result
                return !empty($result) ? $result : false;

            // whoopsie...
            } catch (\Exception $e) {
                // error logging
                Logger::error("Database Fetch Error", [
                    'message' => $e -> getMessage(),
                ]);

                throw $e;
            }
        }

        /**
         * execute
         *
         * Execute non-SELECT queries (INSERT, UPDATE, DELETE)
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         *
         * @return mixed Returns last insert ID for INSERT, affected rows for UPDATE/DELETE, or false on failure
         */
        public function execute(): mixed
        {

            // validate we have a query
            if (empty($this -> current_query)) {
                // error logging
                Logger::error("Database Execute Failed - No Query Set");

                // throw an exception
                throw new \RuntimeException('No query has been set. Call query() first.');
            }

            // Ensure connection
            if (!$this->is_connected) {
                $this->connect();
            }

            // try to execute the query
            try {
                // prepare the statement
                $stmt = $this -> db_handle -> prepare($this -> current_query);

                // debug logging
                Logger::debug("Database Statement Prepared for Execute");

                // bind parameters if we have any
                $this -> bindParams($stmt, $this -> query_params);

                // execute the query
                $success = $stmt -> execute();

                // it was not successful, log an error and return false
                if (! $success) {
                    // error logging
                    Logger::error("Database Execute Failed", []);
                    return false;
                }

                // debug logging
                Logger::debug("Database Query Executed Successfully");

                // determine return value based on query type
                $query_type = strtoupper(substr(trim($this -> current_query), 0, 6));

                // figure out what kind of query are we running for the return value
                switch ($query_type) {
                    case 'INSERT':
                        // return last insert ID for inserts
                        $id = $this -> db_handle -> lastInsertId();
                        $result = $id ?: true;

                        // debug logging
                        Logger::debug("Database INSERT Executed", []);

                        return $result;

                    case 'UPDATE':
                    case 'DELETE':
                        // return affected rows for updates/deletes
                        $affected_rows = $stmt -> rowCount();

                        // debug logging
                        Logger::debug("Database {$query_type} Executed", []);

                        return $affected_rows;

                    default:
                        // debug logging
                        Logger::debug("Database {$query_type} Executed", []);

                        // return success for other queries
                        return $success;
                }

            // whoopsie...
            } catch (\Exception $e) {
                // error logging
                Logger::error("Database Execute Error", [
                    'message' => $e -> getMessage(),
                ]);

                throw $e;
            }
        }

        /**
         * getLastId
         *
         * Get the last inserted ID
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         *
         * @return string|false Returns the last insert ID or false
         */
        public function getLastId(): string|false
        {

            // try to get the last insert ID
            try {
                // return the last id
                return $this -> db_handle -> lastInsertId() ?? 0;

            // whoopsie...
            } catch (\Exception $e) {
                // error logging
                Logger::error("Database Get Last ID Error", [
                    'message' => $e -> getMessage()
                ]);

                return false;
            }
        }

        /**
         * transaction
         *
         * Begin a database transaction
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         *
         * @return bool Returns true if transaction started successfully
         */
        public function transaction(): bool
        {

            // Ensure connection
            if (!$this->is_connected) {
                $this->connect();
            }

            // try to begin transaction
            try {
                // begin the transaction
                return $this -> db_handle -> beginTransaction();

            // whoopsie...
            } catch (\Exception $e) {
                // error logging
                Logger::error("Database Transaction Start Error", [
                    'message' => $e -> getMessage()
                ]);

                return false;
            }
        }

        /**
         * commit
         *
         * Commit the current transaction
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         *
         * @return bool Returns true if transaction committed successfully
         */
        public function commit(): bool
        {

            // try to commit transaction
            try {
                // commit the transaction
                return $this -> db_handle -> commit();

            // whoopsie...
            } catch (\Exception $e) {
                // error logging
                Logger::error("Database Transaction Commit Error", [
                    'message' => $e -> getMessage()
                ]);

                return false;
            }
        }

        /**
         * rollback
         *
         * Roll back the current transaction
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         *
         * @return bool Returns true if transaction rolled back successfully
         */
        public function rollback(): bool
        {

            // try to rollback transaction
            try {
                // rollback the transaction
                return $this -> db_handle -> rollBack();

            // whoopsie...
            } catch (\Exception $e) {
                // error logging
                Logger::error("Database Transaction Rollback Error", [
                    'message' => $e -> getMessage()
                ]);

                return false;
            }
        }

        /**
         * Check if currently in a transaction
         *
         * @return bool
         */
        public function inTransaction(): bool
        {
            try {
                return $this->db_handle->inTransaction();
            } catch (\Exception $e) {
                Logger::error("Database inTransaction Check Error", [
                    'message' => $e->getMessage()
                ]);
                return false;
            }
        }

        /**
         * reset
         *
         * Reset the query builder state
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         *
         * @return self Returns self for method chaining
         */
        public function reset(): self
        {

            // reset all query builder properties
            $this -> current_query = '';
            $this -> query_params = [];
            $this -> fetch_mode = \PDO::FETCH_OBJ;
            $this -> fetch_single = false;

            // debug logging
            Logger::debug("Database Reset Completed");

            // return self for chaining
            return $this;
        }

        /**
         * bindParams
         *
         * Bind parameters to a prepared statement with appropriate data types
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         *
         * @param PDOStatement $stmt The prepared statement to bind parameters to
         * @param array $params The parameters to bind
         * @return void
         */
        private function bindParams(\PDOStatement $stmt, array $params = []): void
        {
            // if we don't have any parameters just return
            if (empty($params)) {
                Logger::debug("Database Bind Params - No Parameters to Bind");
                return;
            }

            // try to bind parameters
            try {
                // loop over the parameters - optimized binding
                foreach ($params as $i => $param) {
                    // Optimized type detection using match
                    $paramType = match (true) {
                        is_bool($param) => \PDO::PARAM_BOOL,
                        is_int($param) => \PDO::PARAM_INT,
                        is_null($param) => \PDO::PARAM_NULL,
                        default => \PDO::PARAM_STR
                    };

                    // bind the parameter and value
                    $stmt->bindValue($i + 1, $param, $paramType);

                    // debug logging
                    Logger::debug("Database Parameter Bound", [
                        'index' => $i + 1,
                        'param_type' => gettype($param),
                        'pdo_type' => $paramType,
                    ]);
                }

                // debug logging
                Logger::debug("Database Bind Params Completed Successfully", [
                    'total_bound' => count($params)
                ]);

            // whoopsie...
            } catch (\Exception $e) {
                // error logging
                Logger::error("Database Bind Params Error", [
                    'message' => $e->getMessage(),
                ]);

                throw $e;
            }
        }

        /**
         * raw
         *
         * Execute a raw query without the query builder
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         *
         * @param string $query The SQL query to execute
         * @param array $params Optional parameters to bind
         * @return mixed Returns query results or false on failure
         */
        public function raw(string $query, array $params = []): mixed
        {

            // Ensure connection
            if (!$this->is_connected) {
                $this->connect();
            }

            // try to execute the raw query
            try {
                // prepare the statement
                $stmt = $this -> db_handle -> prepare($query);

                // bind parameters if we have any
                $this -> bindParams($stmt, $params);

                // execute the query, if it fails, log an error and return false
                if (! $stmt -> execute()) {
                    // error logging
                    Logger::error("Database Raw Query Execution Failed", []);
                    return false;
                }

                // determine query type
                $query_type = strtoupper(substr(trim($query), 0, 6));

                // handle SELECT queries
                if ($query_type === 'SELECT') {
                    $results = $stmt -> fetchAll(\PDO::FETCH_OBJ);
                    $stmt -> closeCursor();

                    // debug logging
                    Logger::debug("Database Raw SELECT Results", [
                        'has_results' => ! empty($results),
                        'result_count' => is_array($results) ? count($results) : 0
                    ]);

                    // return the results
                    return ! empty($results) ? $results : false;
                }

                // handle INSERT queries
                if ($query_type === 'INSERT') {
                    $id = $this -> db_handle -> lastInsertId();
                    $result = $id ?: true;

                    // debug logging
                    Logger::debug("Database Raw INSERT Results", []);

                    // return the result
                    return $result;
                }

                // handle UPDATE/DELETE queries
                if (in_array($query_type, ['UPDATE', 'DELETE'])) {
                    $affected_rows = $stmt -> rowCount();

                    // debug logging
                    Logger::debug("Database Raw {$query_type} Results", []);

                    // return the result
                    return $affected_rows;
                }

                // return true for other successful queries
                return true;

            // whoopsie...
            } catch (\Exception $e) {
                // error logging
                Logger::error("Database Raw Query Error", [
                    'message' => $e -> getMessage(),
                ]);

                // throw the exception
                throw $e;
            }
        }
    }

}
