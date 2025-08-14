# WP Migrate

A comprehensive WordPress migration tool that enables seamless database, media, and file synchronization between WordPress environments.

## Features

- **Database Migration**: Export/import with intelligent URL/path replacement
- **Serialized Data Handling**: Safe processing of WordPress serialized data
- **Media Library Sync**: Transfer media files between WordPress installations
- **Theme & Plugin Files**: Sync themes and plugins across environments
- **WordPress Multisite Support**: Handle complex multisite migrations
- **WP-CLI Integration**: Full command-line interface for automation
- **Backup & Restore**: Automatic backups before destructive operations
- **Security**: Encrypted data transmission and proper authentication

## Requirements

- WordPress 5.0+
- PHP 7.4+
- MySQL 5.6+
- Required PHP extensions: mysqli, json, zip, curl
- Node.js 16+ (for development)
- Composer (for development)

## Installation

### From Release Package

1. Download the latest release from GitHub
2. Upload to your WordPress plugins directory
3. Activate the plugin in WordPress admin
4. Configure your first connection

### Development Installation

1. Clone the repository:
```bash
git clone https://github.com/abdul712/wp-migrate.git
cd wp-migrate
```

2. Install dependencies:
```bash
composer install
npm install
```

3. Build assets:
```bash
npm run build
```

4. Activate the plugin in WordPress

## Quick Start

### 1. Add a Connection

In WordPress admin, go to **WP Migrate > Connections** and add a new connection to your target site.

### 2. Start a Migration

Go to **WP Migrate > Migration** and follow the wizard:
- Select migration type (Database, Files, or Full)
- Choose your target connection
- Configure URL replacements
- Review and execute

### 3. WP-CLI Usage

```bash
# Export database
wp migrate export --file=backup.sql

# Import database with URL replacement
wp migrate import backup.sql --find=old-site.com --replace=new-site.com

# Push database to production
wp migrate push production --find=staging.com --replace=production.com

# Pull database from production
wp migrate pull production --find=production.com --replace=local.dev

# Sync media files
wp migrate media production push

# Create backup
wp migrate backup --name=before-migration
```

## Configuration

### Plugin Settings

Access settings at **WP Migrate > Settings**:

- **Backup Settings**: Automatic backup creation and retention
- **Performance**: Chunk size and timeout settings
- **Security**: SSL verification and encryption options
- **Logging**: Debug and error logging configuration

### Environment Variables

You can configure some settings via environment variables:

```bash
WP_MIGRATE_CHUNK_SIZE=1000
WP_MIGRATE_TIMEOUT=300
WP_MIGRATE_ENABLE_LOGGING=true
WP_MIGRATE_LOG_LEVEL=info
```

## Security

WP Migrate implements several security measures:

- **Encrypted Communication**: All data transfers use SSL/TLS encryption
- **API Authentication**: Secure API key-based authentication between sites
- **Capability Checks**: Proper WordPress capability verification
- **Nonce Verification**: CSRF protection for all operations
- **Input Sanitization**: All user inputs are properly sanitized

## WordPress Serialized Data

One of the most critical features of WP Migrate is its ability to safely handle WordPress serialized data during URL/path replacements. Standard find/replace operations can corrupt serialized data, but WP Migrate:

- Detects PHP serialized data automatically
- Safely unserializes, processes, and re-serializes data
- Maintains data integrity for widgets, customizer settings, and meta values
- Handles nested and complex serialized structures

## Architecture

### Core Components

- **Database Handler** (`src/core/database.php`): Export/import functionality
- **Serialization Handler** (`src/core/serialization.php`): Safe serialized data processing
- **Admin Interface** (`src/admin/admin.php`): WordPress admin integration
- **CLI Commands** (`src/cli/cli.php`): WP-CLI integration
- **API Endpoints** (`src/api/`): REST API for remote operations

### Database Schema

The plugin creates several tables:

- `wp_migrate_history`: Migration operation history
- `wp_migrate_connections`: Remote site connections
- `wp_migrate_backups`: Backup file metadata

## Development

### Setup Development Environment

1. Install dependencies:
```bash
composer install
npm install
```

2. Start development build:
```bash
npm run dev
```

3. Run tests:
```bash
composer test
npm test
```

4. Code quality checks:
```bash
composer lint
npm run lint
```

### Testing

The plugin includes comprehensive tests:

- **Unit Tests**: Test individual components
- **Integration Tests**: Test WordPress integration
- **CLI Tests**: Test WP-CLI commands

Run all tests:
```bash
composer test
```

Run specific test suites:
```bash
# Unit tests only
composer test:unit

# Integration tests only  
composer test:integration
```

### Code Standards

The project follows WordPress coding standards:

- PHP: WordPress Coding Standards (WPCS)
- JavaScript: ESLint with WordPress configuration
- CSS: Stylelint with WordPress configuration

## API Reference

### WP-CLI Commands

```bash
# Database operations
wp migrate export [--file=<file>] [--tables=<tables>] [--find=<string>] [--replace=<string>]
wp migrate import <file> [--find=<string>] [--replace=<string>] [--skip-backup] [--yes]
wp migrate push <connection> [--find=<string>] [--replace=<string>] [--yes]
wp migrate pull <connection> [--find=<string>] [--replace=<string>] [--yes]

# Media operations
wp migrate media <connection> <direction> [--delete] [--dry-run]

# Backup operations
wp migrate backup [--name=<name>] [--compress]

# Connection management
wp migrate connections [--format=<format>]
wp migrate test <connection>

# History
wp migrate history [--limit=<number>] [--format=<format>]
```

### REST API Endpoints

```
POST /wp-json/wp-migrate/v1/export
POST /wp-json/wp-migrate/v1/import
POST /wp-json/wp-migrate/v1/test-connection
GET  /wp-json/wp-migrate/v1/status
```

## Troubleshooting

### Common Issues

**Migration timeouts**
- Increase PHP max_execution_time
- Reduce chunk size in settings
- Use WP-CLI for large migrations

**Memory issues**
- Increase PHP memory_limit
- Enable chunked processing
- Use smaller batch sizes

**SSL certificate errors**
- Disable SSL verification in settings (not recommended for production)
- Ensure valid SSL certificates on target sites

**Permission errors**
- Verify file system permissions
- Check WordPress capability requirements
- Ensure proper API key configuration

### Debug Mode

Enable debug logging in settings to get detailed information about migration operations:

1. Go to **WP Migrate > Settings**
2. Enable "Debug Logging"
3. Set log level to "Debug"
4. Check logs in **WP Migrate > History**

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests for new functionality
5. Ensure all tests pass
6. Submit a pull request

### Development Workflow

1. **Planning**: Discuss major changes in GitHub issues
2. **Development**: Follow WordPress coding standards
3. **Testing**: Write tests for new features
4. **Documentation**: Update documentation as needed
5. **Review**: Submit pull request for code review

## License

This project is licensed under the GPL v2 or later. See the [LICENSE](LICENSE) file for details.

## Support

- **Documentation**: See the `/docs` directory
- **Issues**: Report bugs on [GitHub Issues](https://github.com/abdul712/wp-migrate/issues)
- **Discussions**: Join discussions on [GitHub Discussions](https://github.com/abdul712/wp-migrate/discussions)

## Changelog

### 1.0.0 (Initial Release)

- Database migration with URL/path replacement
- Safe WordPress serialized data handling
- WordPress admin interface
- WP-CLI integration
- Backup and restore functionality
- Connection management
- Migration history tracking
- Comprehensive test suite

## Credits

Developed by [Abdul Rahim](https://github.com/abdul712) with contributions from the WordPress community.

## Roadmap

- [ ] Media file synchronization
- [ ] Theme and plugin file transfers  
- [ ] WordPress multisite support
- [ ] Advanced backup scheduling
- [ ] Integration with popular hosting providers
- [ ] Performance optimizations for large sites
- [ ] Real-time migration monitoring
- [ ] Migration templates and profiles