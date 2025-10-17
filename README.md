# WP Multisite Dashboard

Essential dashboard widgets for WordPress multisite administrators. This plugin provides a comprehensive set of monitoring and management tools specifically designed for WordPress multisite networks.

## Features

### Core Dashboard Widgets
- **Network Overview** - Complete network statistics and health monitoring
- **Quick Site Management** - Manage sites directly from the dashboard
- **Storage Usage** - Monitor disk space and performance metrics
- **Server Information** - Real-time server status and configuration
- **Quick Links** - Customizable shortcuts to frequently used admin pages
- **Version Information** - Track WordPress, PHP, and plugin versions across the network
- **Network News** - Custom news feed for network announcements
- **User Management** - Streamlined user administration tools
- **Contact Information** - Network administrator contact details with QR codes
- **Recent Network Activity** - Track recent changes across all sites
- **Todo List** - Network-wide task management

### Advanced Monitoring Tools
- **PHP Error Logs** - Real-time PHP error monitoring and analysis
- **404 Monitor** - Track and analyze 404 errors across the network
- **Performance Monitoring** - Server performance metrics and optimization suggestions

### Management Features
- **Widget Configuration** - Enable/disable widgets per your needs
- **System Widget Detection** - Automatically detect and manage third-party widgets
- **Import/Export Settings** - Backup and restore plugin configurations
- **Performance Optimization** - Built-in caching and optimization features

## Requirements

- WordPress Multisite Network
- PHP 7.4 or higher
- WordPress 5.0 or higher
- Network Administrator privileges

## Installation

1. Upload the plugin files to `/wp-content/plugins/wp-multisite-dashboard/`
2. Network activate the plugin through the 'Plugins' menu in WordPress Network Admin
3. Navigate to **Network Admin > Dashboard** to see the widgets
4. Configure widgets via **Network Admin > Settings > Multisite Dashboard**

## Configuration

### Widget Management
Access **Network Admin > Settings > Multisite Dashboard** to:
- Enable/disable individual widgets
- Configure widget-specific settings
- Manage system widget detection
- Import/export configurations

### Monitoring Setup
1. **PHP Error Logs**: Ensure `WP_DEBUG` and `WP_DEBUG_LOG` are enabled in wp-config.php
2. **404 Monitor**: Automatically tracks 404 errors once enabled
3. **Performance Monitor**: No additional setup required

### Recommended wp-config.php Settings
```php
// Enable debug logging for error monitoring
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);

// Optional: Increase memory limit for large networks
ini_set('memory_limit', '512M');
```

## Widget Overview

### Network Overview
- Total sites, users, and posts
- Network health indicators
- Recent activity summary
- Quick action buttons

### Storage Usage
- Disk space utilization
- Database size monitoring
- Upload directory analysis
- Performance recommendations

### Error Monitoring
- Real-time PHP error tracking
- Error categorization (Fatal, Warning, Notice)
- Error frequency analysis
- Quick error resolution links

### 404 Monitor
- Top 404 URLs tracking
- Referrer analysis
- Automated cleanup options
- Export capabilities

## Advanced Features

### Performance Optimization
- Intelligent caching system
- Database query optimization
- Lazy loading for large datasets
- Background processing for heavy operations

### Security Features
- Secure AJAX handling
- Nonce verification
- Input sanitization
- Rate limiting for monitoring features

### Extensibility
- Hook system for custom widgets
- Filter system for data modification
- Template override support
- Developer-friendly API

## Troubleshooting

### Common Issues

**Widgets not loading:**
- Check if multisite is properly configured
- Verify network admin permissions
- Clear browser cache and try again

**Error logs not showing:**
- Ensure `WP_DEBUG_LOG` is enabled
- Check file permissions on wp-content/debug.log
- Verify error_log path in PHP configuration

**404 monitoring not working:**
- Confirm the feature is enabled in settings
- Check database table creation
- Verify .htaccess rules aren't interfering

### Performance Optimization
- Enable object caching (Redis/Memcached)
- Use a CDN for static assets
- Optimize database tables regularly
- Monitor server resources

## System Requirements

### Minimum Requirements
- WordPress 5.0+
- PHP 7.4+
- MySQL 5.6+ or MariaDB 10.0+
- 128MB PHP memory limit

### Recommended Requirements
- WordPress 6.0+
- PHP 8.0+
- MySQL 8.0+ or MariaDB 10.4+
- 512MB PHP memory limit
- Object caching enabled

## Updates

The plugin includes an automatic update checker that connects to our update server. Updates are delivered seamlessly through the WordPress admin interface.

## Changelog

### Version 1.3
- Added PHP Error Log monitoring
- Added 404 Monitor with detailed analytics
- Improved performance with caching system
- Enhanced security with better AJAX handling
- Added import/export functionality
- Optimized database queries
- Improved mobile responsiveness

### Version 1.2
- Added system widget detection
- Improved user management tools
- Enhanced contact information widget
- Performance optimizations
- Bug fixes and stability improvements

### Version 1.1
- Added todo list widget
- Improved network overview
- Enhanced storage monitoring
- Added quick links customization

### Version 1.0
- Initial release
- Core dashboard widgets
- Basic network monitoring
- User management tools

## Support

For support, feature requests, or bug reports:
- Visit: [WPMultisite.com](https://wpmultisite.com)
- Email: support@wpmultisite.com
- Documentation: [Plugin Documentation](https://wpmultisite.com/docs/wp-multisite-dashboard)

## License

This plugin is licensed under the GPLv2+ license. See the LICENSE file for details.

## Credits

Developed by [WPMultisite.com](https://wpmultisite.com) - Specialists in WordPress Multisite solutions.

---

**Made with love for the WordPress Multisite community**
