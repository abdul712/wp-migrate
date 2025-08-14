# WP Migrate

A comprehensive WordPress migration tool that enables seamless database, media, and file synchronization between WordPress environments.

[![WordPress Plugin](https://img.shields.io/badge/WordPress-5.0%2B-blue.svg)](https://wordpress.org/)
[![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL%20v2%2B-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

## 🚀 Features

### Core Migration Capabilities
- **Database Migration**: Export/import with intelligent URL/path replacement
- **WordPress Serialized Data Handling**: Safe processing of WordPress serialized content to prevent data corruption
- **Media Library Synchronization**: Push/pull media files with integrity verification
- **Theme & Plugin Transfers**: Complete file synchronization between sites
- **Backup & Restore**: Automated backups before all operations

### Advanced Features
- **Push/Pull Operations**: Direct site-to-site synchronization
- **WP-CLI Integration**: Complete command-line interface for automation
- **Connection Management**: Secure encrypted connections between sites
- **Migration Profiles**: Save and reuse migration configurations
- **WordPress Multisite Support**: Handle complex multisite networks
- **Progress Tracking**: Real-time migration status updates

### Security & Performance
- **Encrypted Communications**: SSL/TLS secured transfers
- **API Key Authentication**: Secure connection management
- **Chunked Processing**: Handle large databases efficiently
- **Memory Optimization**: Process large datasets without timeouts
- **Error Recovery**: Robust error handling and rollback capabilities

## 📦 Installation

### WordPress Admin
1. Download the plugin ZIP file
2. Go to **Plugins > Add New > Upload Plugin**
3. Choose the ZIP file and click **Install Now**
4. Activate the plugin

### WP-CLI
```bash
wp plugin install wp-migrate --activate
```

### Composer
```bash
composer require abdul712/wp-migrate
```

## 🛠️ Quick Start

### 1. Create a Connection
```bash
# Via WP-CLI
wp migrate connection add production --url=https://yoursite.com --api-key=your-api-key

# Or use the WordPress admin interface
```

### 2. Database Migration
```bash
# Export database
wp migrate db export --file=backup.sql

# Import database
wp migrate db import backup.sql --find=old-url.com --replace=new-url.com

# Push to remote site
wp migrate db push production --find=local.dev --replace=production.com

# Pull from remote site
wp migrate db pull production --find=production.com --replace=local.dev --backup
```

### 3. Media Synchronization
```bash
# Pull media from remote site
wp migrate media sync production --direction=pull

# Push media to remote site
wp migrate media sync production --direction=push --overwrite

# Bidirectional sync
wp migrate media sync production --direction=sync
```

### 4. Backup Management
```bash
# Create full backup
wp migrate backup create --type=full --name=before-migration

# List backups
wp migrate backup list

# Restore from backup
wp migrate backup restore 123 --yes
```

## 🎯 Use Cases

### Local to Production Deployment
```bash
# Create production backup
wp migrate backup create --name=pre-deployment

# Push database with URL replacement
wp migrate db push production --find=http://local.dev --replace=https://yoursite.com

# Push media files
wp migrate media sync production --direction=push --overwrite

# Push themes and plugins
wp migrate files sync production --direction=push --themes --plugins
```

### Staging Environment Setup
```bash
# Pull production database to staging
wp migrate db pull production --find=https://yoursite.com --replace=https://staging.yoursite.com --backup

# Pull media files
wp migrate media sync production --direction=pull

# Sync themes and plugins
wp migrate files sync production --direction=pull --themes --plugins
```

### Content Synchronization
```bash
# Sync only recent media files
wp migrate media sync production --direction=pull --from=2023-01-01

# Sync specific database tables
wp migrate db pull production --tables=wp_posts,wp_postmeta --backup
```

## 🔧 Configuration

### WordPress Admin Settings
- **Maximum Execution Time**: Set timeout limits for operations
- **Memory Limit**: Configure memory usage for large datasets
- **Chunk Size**: Adjust processing batch sizes
- **Backup Retention**: Set automatic cleanup policies
- **Logging**: Enable/disable operation logging
- **Security**: Configure SSL and API key settings

### Environment Variables
```bash
# Set in wp-config.php or environment
define('WP_MIGRATE_MAX_EXECUTION_TIME', 600);
define('WP_MIGRATE_MEMORY_LIMIT', '1024M');
define('WP_MIGRATE_CHUNK_SIZE', 5000);
define('WP_MIGRATE_ENABLE_LOGGING', true);
define('WP_MIGRATE_SECURE_CONNECTIONS', true);
```

### Connection Configuration
```json
{
  "name": "Production Site",
  "url": "https://yoursite.com",
  "api_key": "your-secure-api-key",
  "description": "Main production environment",
  "ssl_verify": true,
  "timeout": 300
}
```

## 📚 WordPress Admin Interface

### Migration Dashboard
- **Quick Stats**: Database size, media count, connection status
- **Recent Migrations**: History of all operations
- **Active Connections**: Manage remote site connections
- **System Status**: Health checks and diagnostics

### Migration Wizard
1. **Source Selection**: Choose data sources (database, media, files)
2. **Connection Setup**: Configure target site connection
3. **Migration Options**: URL replacement, backup settings
4. **Progress Monitoring**: Real-time status updates
5. **Completion Summary**: Results and next steps

### Connection Management
- **Add New Connections**: Secure site-to-site links
- **Test Connections**: Verify remote site accessibility
- **API Key Management**: Generate and rotate keys
- **Connection History**: Track usage and status

## 🔐 Security Features

### Encrypted Communications
- All data transfers use SSL/TLS encryption
- API keys are encrypted at rest
- Secure authentication for all operations

### Access Control
- WordPress capability checks
- API key authentication
- Rate limiting protection
- IP whitelisting support

### Data Protection
- Automatic backups before destructive operations
- Integrity verification with checksums
- Safe WordPress serialized data handling
- Error recovery and rollback capabilities

## 🧪 Testing

### Run Test Suite
```bash
# Install dependencies
composer install
npm install

# Run PHP tests
composer test

# Run JavaScript tests
npm test

# Run code quality checks
composer lint
npm run lint
```

### WordPress Integration Tests
```bash
# Set up WordPress test environment
bash bin/install-wp-tests.sh wp_migrate_test root '' localhost latest

# Run integration tests
phpunit --group=integration
```

## 📖 API Reference

### WordPress Hooks

#### Actions
```php
// Before migration starts
do_action('wp_migrate_before_migration', $migration_type, $options);

// After migration completes
do_action('wp_migrate_after_migration', $migration_type, $result);

// Before database export
do_action('wp_migrate_before_db_export', $options);

// After database import
do_action('wp_migrate_after_db_import', $result);
```

#### Filters
```php
// Modify export options
$options = apply_filters('wp_migrate_export_options', $options, $context);

// Modify find/replace pairs
$find_replace = apply_filters('wp_migrate_find_replace', $find_replace, $context);

// Modify file paths for sync
$file_paths = apply_filters('wp_migrate_sync_file_paths', $file_paths, $direction);
```

### REST API Endpoints

#### Database Operations
- `POST /wp-json/wp-migrate/v1/export` - Export database
- `POST /wp-json/wp-migrate/v1/import` - Import database
- `GET /wp-json/wp-migrate/v1/test` - Test connection

#### Media Operations
- `POST /wp-json/wp-migrate/v1/media/list` - Get media list
- `POST /wp-json/wp-migrate/v1/media/upload` - Upload media file
- `POST /wp-json/wp-migrate/v1/media/exists` - Check media exists

#### File Operations
- `GET /wp-json/wp-migrate/v1/themes/list` - Get theme list
- `GET /wp-json/wp-migrate/v1/themes/download/{slug}` - Download theme
- `GET /wp-json/wp-migrate/v1/plugins/list` - Get plugin list
- `GET /wp-json/wp-migrate/v1/plugins/download/{slug}` - Download plugin

## 🏗️ Development

### Project Structure
```
wp-migrate/
├── wp-migrate.php              # Main plugin file
├── src/
│   ├── core/                   # Core migration functionality
│   │   ├── database.php        # Database operations
│   │   ├── serialization.php   # WordPress serialized data handling
│   │   ├── connections.php     # Connection management
│   │   ├── backup.php          # Backup/restore functionality
│   │   ├── media.php           # Media synchronization
│   │   └── files.php           # File transfer operations
│   ├── admin/                  # WordPress admin interface
│   │   ├── admin.php           # Main admin controller
│   │   ├── wizard.php          # Migration wizard
│   │   └── settings.php        # Settings management
│   ├── cli/                    # WP-CLI integration
│   │   └── cli.php             # CLI commands
│   ├── api/                    # REST API endpoints
│   │   ├── endpoints.php       # API route handlers
│   │   └── authentication.php  # API authentication
│   └── assets/                 # Frontend assets
│       ├── js/                 # JavaScript files
│       └── css/                # Stylesheets
├── tests/                      # Test suites
├── build/                      # Built assets
└── docs/                       # Documentation
```

### Build Process
```bash
# Install dependencies
composer install
npm install

# Development build with watching
npm run dev

# Production build
npm run build

# Run all quality checks
composer build

# Create distribution package
npm run package
```

### Contributing
1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests for new functionality
5. Run the test suite
6. Submit a pull request

## 📋 Requirements

### Server Requirements
- **WordPress**: 5.0 or higher
- **PHP**: 7.4 or higher
- **MySQL**: 5.6 or higher
- **Extensions**: json, mysqli, zip, curl

### Recommended
- **OpenSSL**: For encrypted API key storage
- **ImageMagick/GD**: For image processing
- **WP-CLI**: For command-line operations
- **Sufficient Memory**: 512MB+ for large sites

## 🆘 Troubleshooting

### Common Issues

#### Large Database Timeouts
```bash
# Increase timeout and memory limits
wp config set WP_MIGRATE_MAX_EXECUTION_TIME 1800
wp config set WP_MIGRATE_MEMORY_LIMIT 2048M
wp config set WP_MIGRATE_CHUNK_SIZE 100
```

#### Connection Failures
```bash
# Test connection
wp migrate connection test production

# Check SSL certificate
wp migrate connection test production --no-ssl-verify

# Debug API communication
wp config set WP_DEBUG true
wp config set WP_MIGRATE_LOG_LEVEL debug
```

#### Serialized Data Corruption
The plugin automatically handles WordPress serialized data safely. If you encounter issues:

1. Enable debug logging: `wp config set WP_MIGRATE_ENABLE_LOGGING true`
2. Check logs for serialization errors
3. Use the built-in data repair tools
4. Contact support with specific error messages

### Getting Help

- **Documentation**: [Full documentation](https://github.com/abdul712/wp-migrate/wiki)
- **Issues**: [Report bugs](https://github.com/abdul712/wp-migrate/issues)
- **Support**: [Community support](https://wordpress.org/support/plugin/wp-migrate)

## 📝 Changelog

### Version 1.0.0
- Initial release
- Complete database migration functionality
- WordPress serialized data handling
- Media library synchronization
- Theme and plugin file transfers
- WP-CLI integration
- WordPress admin interface
- Backup and restore capabilities
- Connection management
- REST API endpoints
- Comprehensive testing suite

## 📜 License

This plugin is licensed under the [GPL v2 or later](https://www.gnu.org/licenses/gpl-2.0.html).

```
WP Migrate - WordPress Migration Tool
Copyright (C) 2024 Abdul Rahim

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
```

## 👨‍💻 Author

**Abdul Rahim**
- GitHub: [@abdul712](https://github.com/abdul712)
- Email: abdul712@users.noreply.github.com

## 🙏 Acknowledgments

- WordPress community for the amazing platform
- All contributors and testers
- Open source libraries and tools used in this project

---

**⚠️ Important Notice**: Always create backups before performing migrations. This plugin handles WordPress serialized data safely, but it's always recommended to test on staging environments first.