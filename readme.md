# KP Database

A WordPress plugin that replaces the core WordPress database interaction layer (`wpdb`) with the modern, fluent KPT Database library built on PDO.

## Description

This plugin seamlessly integrates the KPT Database library into WordPress, replacing all `wpdb` database interactions with a more modern, secure, and feature-rich PDO-based solution while maintaining full backward compatibility with WordPress core and plugins.

## Features

- **Drop-in Replacement**: Seamlessly replaces WordPress `wpdb` class
- **Full Backward Compatibility**: Works with all WordPress core functions and most plugins
- **PDO-Based**: Modern PDO implementation with prepared statements
- **Enhanced Security**: Built-in SQL injection protection
- **Better Error Handling**: Comprehensive error logging and handling
- **Performance**: Optimized database operations
- **WordPress Coding Standards**: Fully compliant with WordPress coding standards
- **Debug Logging**: Integrated with WordPress debug logging system

## Requirements

- WordPress 6.7 or higher
- PHP 8.2 or higher
- PDO extension with MySQL driver
- MySQL 5.7+ or MariaDB 10.2+

## Installation

### Via Composer (Recommended)
```bash
composer require kevinpirnie/wp-kpt-database
```

### Manual Installation

1. Download the plugin
2. Extract the plugin files to `/wp-content/plugins/wp-kpt-database/`
3. Run `composer install` in the plugin directory
4. Activate the plugin through the 'Plugins' menu in WordPress

## Configuration

The plugin automatically uses your WordPress database configuration from `wp-config.php`. No additional configuration is required.

### Debug Logging

To enable debug logging, add these lines to your `wp-config.php`:
```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
```

Debug and error logs will be written to `wp-content/debug.log`.

## Usage

Once activated, the plugin automatically replaces all WordPress database interactions. No code changes are required in your themes or plugins.

### Compatibility

The plugin maintains full compatibility with:

- WordPress core functions (`get_posts`, `wp_insert_post`, etc.)
- Plugin database operations
- Theme database queries
- WP-CLI commands
- Custom database queries using `$wpdb`

### Example Usage

All standard WordPress database operations work as expected:
```php
global $wpdb;

// Standard WordPress queries work without modification
$results = $wpdb->get_results( "SELECT * FROM {$wpdb->posts} WHERE post_status = 'publish'" );

$wpdb->insert(
    $wpdb->prefix . 'my_table',
    array(
        'column1' => 'value1',
        'column2' => 'value2'
    ),
    array( '%s', '%s' )
);

$wpdb->update(
    $wpdb->prefix . 'my_table',
    array( 'column1' => 'new_value' ),
    array( 'id' => 1 ),
    array( '%s' ),
    array( '%d' )
);
```

## Supported wpdb Methods

All standard `wpdb` methods are fully supported:

- `query()` - Execute a SQL query
- `get_var()` - Retrieve one variable from the database
- `get_row()` - Retrieve one row from the database
- `get_col()` - Retrieve one column from the database
- `get_results()` - Retrieve an entire SQL result set
- `insert()` - Insert a row into a table
- `update()` - Update a row in a table
- `delete()` - Delete a row from a table
- `replace()` - Replace a row in a table
- `prepare()` - Prepare a SQL query for safe execution

## Performance Considerations

The KPT Database library provides several performance benefits:

- Efficient prepared statement caching
- Optimized parameter binding
- Better memory management
- Reduced query overhead

## Troubleshooting

### Plugin Won't Activate

**Error: "This plugin requires PHP 8.2 or higher"**
- Upgrade your PHP version to 8.2 or later

**Error: "This plugin requires WordPress 6.7 or higher"**
- Update WordPress to version 6.7 or later

**Error: "This plugin requires the KPT Database library"**
- Run `composer install` in the plugin directory

### Database Connection Issues

If you experience database connection issues:

1. Verify your `wp-config.php` database credentials
2. Check that your database server is running
3. Ensure PDO MySQL extension is installed
4. Check debug logs in `wp-content/debug.log`

### Plugin Compatibility Issues

If you experience issues with a specific plugin:

1. Enable WordPress debug logging
2. Check `wp-content/debug.log` for errors
3. Report the issue with details about the conflicting plugin

## Development

### Running Tests
```bash
composer install
composer test
```

### Code Standards
```bash
composer phpcs
composer phpcbf
```

### Static Analysis
```bash
composer phpstan
```

## Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

### Development Guidelines

- Follow WordPress Coding Standards
- Maintain PHP 8.2+ compatibility
- Add tests for new features
- Update documentation as needed
- Ensure backward compatibility with WordPress core

## Security

- All queries use prepared statements
- Automatic parameter type detection
- SQL injection protection
- Input validation and sanitization

If you discover a security vulnerability, please email me@kpirnie.com.

## Known Limitations

- Custom wpdb extensions may require additional compatibility work
- Some advanced wpdb features may have different behavior
- Direct PDO handle access is not available through wpdb

## FAQ

**Q: Will this break my existing site?**
A: No. The plugin is designed to be a drop-in replacement with full backward compatibility.

**Q: Do I need to modify my theme or plugins?**
A: No. All existing code continues to work without modification.

**Q: Can I disable the plugin easily?**
A: Yes. Simply deactivate the plugin and WordPress will revert to using the standard wpdb class.

**Q: Does this work with multisite?**
A: Yes. The plugin supports WordPress multisite installations.

**Q: Will this improve my site's performance?**
A: The plugin provides a modern database layer with potential performance benefits, though results may vary depending on your specific use case.

**Q: Is this compatible with caching plugins?**
A: Yes. The plugin works with all major caching solutions.

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Credits

Built on top of the [KPT Database](https://github.com/kpirnie/kp-database) library.

## Author

**Kevin Pirnie**
- Email: me@kpirnie.com
- GitHub: [@kpirnie](https://github.com/kpirnie)

## Support

- [GitHub Issues](https://github.com/kpirnie/wp-kpt-database/issues)
- [Documentation](https://github.com/kpirnie/wp-kpt-database)

## Links

- [Plugin Homepage](https://github.com/kpirnie/wp-kpt-database)
- [KPT Database Library](https://github.com/kpirnie/kp-database)
- [WordPress Plugin Guidelines](https://developer.wordpress.org/plugins/)