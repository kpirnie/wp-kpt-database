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

## Changelog

### 1.0.0 (2024-01-15)
- Initial release
- Full wpdb replacement functionality
- WordPress 6.7+ compatibility
- PHP 8.2+ support

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

- [GitHub Issues](https://github.com/kpirnie/kpt-database-wordpress/issues)
- [Documentation](https://github.com/kpirnie/kpt-database-wordpress)

## Links

- [Plugin Homepage](https://github.com/kpirnie/kpt-database-wordpress)
- [KPT Database Library](https://github.com/kpirnie/kp-database)
- [WordPress Plugin Guidelines](https://developer.wordpress.org/plugins/)
