# KPT Database

A modern, fluent PHP database wrapper built on top of PDO, providing an elegant and secure way to interact with MySQL databases.

## Features

- **Fluent Interface**: Chain methods for readable and intuitive database operations
- **PSR-12 Compliant**: Follows PHP coding standards with camelCase method names
- **Prepared Statements**: Built-in protection against SQL injection
- **Flexible Fetching**: Return results as objects or arrays, single records or collections
- **Transaction Support**: Full transaction management with commit/rollback
- **Type-Safe Parameter Binding**: Automatic parameter type detection and binding
- **Raw Query Support**: Execute custom SQL when needed
- **Comprehensive Logging**: Debug and error logging throughout
- **Method Chaining**: Build complex queries with readable, chainable methods

## Requirements

- PHP 8.1 or higher
- PDO extension with MySQL driver
- MySQL 5.7+ or MariaDB 10.2+

## Installation

Install via Composer:

```bash
composer require kpt/database
```

## Configuration

The database class requires a settings object to be passed to the constructor and expects a `Logger` class to be available in the `KPT` namespace:

```php
$db_settings = (object) [
    'server' => 'localhost',
    'schema' => 'your_database',
    'username' => 'your_username', 
    'password' => 'your_password',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci'
];

$db = new Database($db_settings);
```

**Note**: You'll need to have a `KPT\Logger` class available with static `debug()` and `error()` methods for logging functionality.

## Basic Usage

### Initialization

```php
use KPT\Database;

$db_settings = (object) [
    'server' => 'localhost',
    'schema' => 'my_database',
    'username' => 'db_user',
    'password' => 'db_password',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci'
];

$db = new Database($db_settings);
```

### Select Operations

```php
// Fetch all users
$users = $db->query("SELECT * FROM users")->fetch();

// Fetch single user by ID
$user = $db->query("SELECT * FROM users WHERE id = ?")
           ->bind([123])
           ->single()
           ->fetch();

// Fetch as arrays instead of objects
$users = $db->query("SELECT * FROM users")
            ->asArray()
            ->fetch();

// Fetch with limit
$recent_users = $db->query("SELECT * FROM users ORDER BY created_at DESC")
                   ->fetch(10);
```

### Insert Operations

```php
// Insert new user
$user_id = $db->query("INSERT INTO users (name, email, created_at) VALUES (?, ?, NOW())")
              ->bind(['John Doe', 'john@example.com'])
              ->execute();

// The execute() method returns the last insert ID for INSERT queries
echo "New user ID: " . $user_id;
```

### Update Operations

```php
// Update user
$affected_rows = $db->query("UPDATE users SET name = ?, updated_at = NOW() WHERE id = ?")
                    ->bind(['Jane Doe', 123])
                    ->execute();

echo "Updated {$affected_rows} rows";
```

### Delete Operations

```php
// Delete user
$affected_rows = $db->query("DELETE FROM users WHERE id = ?")
                    ->bind([123])
                    ->execute();

echo "Deleted {$affected_rows} rows";
```

### Parameter Binding

```php
// Single parameter
$db->query("SELECT * FROM users WHERE id = ?")->bind(123);

// Multiple parameters
$db->query("SELECT * FROM users WHERE name = ? AND email = ?")
   ->bind(['John Doe', 'john@example.com']);

// Automatic type detection handles strings, integers, booleans, and nulls
$db->query("SELECT * FROM users WHERE active = ? AND age > ? AND name LIKE ?")
   ->bind([true, 25, '%John%']);
```

### Transactions

```php
// Start transaction
$db->transaction();

try {
    // Perform multiple operations
    $user_id = $db->query("INSERT INTO users (name, email) VALUES (?, ?)")
                  ->bind(['John Doe', 'john@example.com'])
                  ->execute();
    
    $db->query("INSERT INTO user_profiles (user_id, bio) VALUES (?, ?)")
       ->bind([$user_id, 'Software Developer'])
       ->execute();
    
    // Commit if all operations succeed
    $db->commit();
    
} catch (Exception $e) {
    // Rollback on any error
    $db->rollback();
    throw $e;
}
```

### Raw Queries

For complex queries that don't fit the builder pattern:

```php
// Raw SELECT
$results = $db->raw("
    SELECT u.*, p.bio 
    FROM users u 
    LEFT JOIN profiles p ON u.id = p.user_id 
    WHERE u.created_at > ?
", ['2023-01-01']);

// Raw INSERT with parameters
$insert_id = $db->raw("
    INSERT INTO complex_table (col1, col2, col3) 
    SELECT ?, ?, ? 
    FROM another_table 
    WHERE condition = ?
", ['value1', 'value2', 'value3', 'condition_value']);
```

## Method Reference

### Query Building

- `query(string $sql)` - Set the SQL query to execute
- `bind(mixed $params)` - Bind parameters (single value or array)
- `single()` - Set mode to fetch single record
- `many()` - Set mode to fetch multiple records (default)
- `asArray()` - Return results as associative arrays
- `asObject()` - Return results as objects (default)

### Execution

- `fetch(?int $limit = null)` - Execute SELECT queries and return results
- `execute()` - Execute INSERT/UPDATE/DELETE queries
- `raw(string $query, array $params = [])` - Execute raw SQL

### Transactions

- `transaction()` - Begin a transaction
- `commit()` - Commit the current transaction  
- `rollback()` - Roll back the current transaction

### Utilities

- `getLastId()` - Get the last inserted ID
- `reset()` - Reset the query builder state

## Method Chaining

All query building methods return `$this`, allowing for fluent chaining:

```php
$user = $db->query("SELECT * FROM users WHERE email = ?")
           ->bind('john@example.com')
           ->single()
           ->asArray()
           ->fetch();
```

## Error Handling

The class throws exceptions for database errors. Always wrap database operations in try-catch blocks:

```php
try {
    $result = $db->query("SELECT * FROM users")->fetch();
} catch (Exception $e) {
    // Handle database error
    error_log("Database error: " . $e->getMessage());
}
```

## Logging

The class includes comprehensive logging through a `LOG` class:

- Debug logs for successful operations
- Error logs for failures and exceptions
- Parameter binding information
- Query execution details

## Security

- **Prepared Statements**: All queries use prepared statements to prevent SQL injection
- **Parameter Type Detection**: Automatic binding with appropriate PDO parameter types
- **Input Validation**: Validates queries and parameters before execution

## Contributing

1. Fork the repository
2. Create a feature branch
3. Add tests for new functionality
4. Ensure all tests pass
5. Submit a pull request

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Author

**Kevin Pirnie** - [me@kpirnie.com](mailto:me@kpirnie.com)
