# WP Migrate - Claude Development Guide

## Project Overview

WP Migrate is a comprehensive WordPress migration tool that automates database, media, and file synchronization between WordPress environments. This document provides essential context for Claude to effectively assist with development, maintenance, and feature implementation.

## Critical Architecture Principles

### 1. WordPress Serialized Data Integrity

**MOST IMPORTANT**: WordPress extensively uses PHP serialization for storing complex data (widgets, customizer settings, meta values). Standard find/replace operations **WILL CORRUPT** this data.

**Always use `WP_Migrate_Core_Serialization::safe_replace()`** for any string replacements in database content.

```php
// WRONG - Will corrupt serialized data
$content = str_replace('old-site.com', 'new-site.com', $content);

// CORRECT - Safe for serialized data
$content = WP_Migrate_Core_Serialization::safe_replace($content, [
    'old-site.com' => 'new-site.com'
]);
```

### 2. Security-First Approach

- All remote communications must use encrypted channels
- Implement proper WordPress capability checks
- Sanitize and validate all user inputs
- Use nonces for CSRF protection
- Never log sensitive data (passwords, API keys)

### 3. WordPress Standards Compliance

- Follow WordPress Coding Standards (WPCS) strictly
- Use WordPress APIs and hooks appropriately
- Maintain compatibility with WordPress 5.0+
- Support multisite installations
- Implement proper internationalization

## Core Components

### Database Migration (`src/core/database.php`)

Handles export/import with these critical features:

- **Chunked Processing**: Process large databases in manageable chunks
- **Progress Tracking**: Real-time progress reporting
- **URL Replacement**: Safe find/replace with serialization awareness
- **Backup Creation**: Automatic backups before destructive operations
- **Error Recovery**: Transaction rollback and recovery mechanisms

Key methods:
- `export_database()`: Export with optional URL replacements
- `import_database()`: Import with backup creation
- `replace_serialized_data()`: Safe serialized data replacement

### Serialization Handler (`src/core/serialization.php`)

**Critical component** for WordPress data integrity:

- `safe_replace()`: Primary method for safe string replacement
- `is_serialized()`: Detect serialized data
- `process_option()`: Handle WordPress option values
- `process_post_content()`: Process post content including Gutenberg blocks
- `validate_integrity()`: Verify data integrity after processing

### Admin Interface (`src/admin/admin.php`)

WordPress admin integration with:

- Migration wizard workflow
- Connection management
- Progress monitoring
- Settings management
- History tracking

### WP-CLI Integration (`src/cli/cli.php`)

Full CLI support for automation:

- Database operations (export, import, push, pull)
- Media synchronization
- Backup management
- Connection testing

## Development Guidelines

### When Adding New Features

1. **Database Operations**: Always use the serialization handler for content processing
2. **Security**: Implement capability checks and input validation
3. **Testing**: Write comprehensive tests, especially for serialized data handling
4. **Documentation**: Update both code comments and user documentation
5. **WordPress Standards**: Follow WPCS and WordPress best practices

### Common Patterns

#### Safe Database Content Processing
```php
// For any content that might contain serialized data
$processed_content = WP_Migrate_Core_Serialization::safe_replace(
    $original_content,
    $url_replacements
);
```

#### Progress Reporting
```php
// In long-running operations
$this->report_progress(50, __('Halfway complete...', 'wp-migrate'));
```

#### Error Handling
```php
// Always return WP_Error for failures
if ($error_condition) {
    return new WP_Error('error_code', __('Error message', 'wp-migrate'));
}
```

#### AJAX Responses
```php
// Success
wp_send_json_success(array('message' => __('Success', 'wp-migrate')));

// Error
wp_send_json_error(array('message' => __('Error', 'wp-migrate')));
```

### Testing Strategy

- **Unit Tests**: Test individual methods, especially serialization handling
- **Integration Tests**: Test WordPress integration and database operations
- **CLI Tests**: Test all WP-CLI commands
- **Large Dataset Tests**: Test performance with substantial data

### Performance Considerations

- Use chunked processing for large datasets
- Implement proper timeout handling
- Monitor memory usage
- Provide progress feedback for long operations

## File Structure

```
wp-migrate/
├── wp-migrate.php              # Main plugin file
├── src/
│   ├── core/
│   │   ├── database.php        # Database migration core
│   │   └── serialization.php   # Serialized data handler
│   ├── admin/
│   │   └── admin.php           # WordPress admin interface
│   ├── cli/
│   │   └── cli.php             # WP-CLI integration
│   ├── api/
│   │   └── api.php             # REST API endpoints
│   └── assets/
│       ├── js/                 # JavaScript files
│       ├── scss/               # SCSS source files
│       └── css/                # Compiled CSS
├── tests/
│   ├── unit/                   # Unit tests
│   ├── integration/            # Integration tests
│   └── helpers/                # Test helper classes
├── build/                      # Compiled assets
├── languages/                  # Translation files
├── composer.json               # PHP dependencies
├── package.json                # Node.js dependencies
├── phpunit.xml                 # PHPUnit configuration
├── webpack.config.js           # Build configuration
└── .phpcs.xml                  # Code standards configuration
```

## Database Schema

### Migration History (`wp_migrate_history`)
- Tracks all migration operations
- Stores operation metadata and logs
- Enables migration history and auditing

### Connections (`wp_migrate_connections`)
- Stores remote site connection details
- Encrypted API keys and configuration
- Connection status and last used timestamps

### Backups (`wp_migrate_backups`)
- Backup file metadata
- Automatic cleanup based on retention policies
- File integrity verification

## WordPress-Specific Challenges

### 1. Serialized Data Corruption
The biggest risk in WordPress migrations. Always use the serialization handler.

### 2. Large Dataset Handling
WordPress sites can have multi-gigabyte databases. Implement:
- Chunked processing
- Memory-efficient operations
- Progress reporting
- Timeout handling

### 3. Multisite Complexity
WordPress multisite adds complexity:
- Network-wide vs. site-specific operations
- Domain mapping considerations
- User role and permission handling

### 4. Plugin/Theme Compatibility
Ensure compatibility with:
- Popular caching plugins
- Security plugins
- Backup plugins
- Page builders

## Security Considerations

### API Security
- Use WordPress nonces for CSRF protection
- Implement proper capability checks
- Validate and sanitize all inputs
- Use secure communication channels

### Data Protection
- Never log sensitive information
- Encrypt data in transit
- Secure temporary file handling
- Proper file permission management

## Common Pitfalls to Avoid

1. **Using standard str_replace on database content** - Will corrupt serialized data
2. **Not implementing progress reporting** - Users get frustrated with long operations
3. **Ignoring memory limits** - Large sites require chunked processing
4. **Not creating backups** - Always backup before destructive operations
5. **Poor error handling** - Provide meaningful error messages
6. **Not testing with real data** - Test with actual WordPress sites, not just dummy data

## Debugging and Troubleshooting

### Enable Debug Mode
```php
// In wp-config.php
define('WP_MIGRATE_DEBUG', true);
```

### Common Debug Scenarios
- Serialized data corruption: Check integrity validation
- Memory issues: Reduce chunk size
- Timeout issues: Increase timeout or use CLI
- Permission errors: Check file/directory permissions

## Development Commands

```bash
# Install dependencies
composer install
npm install

# Development build (with watching)
npm run dev

# Production build
npm run build

# Run tests
composer test
npm test

# Code quality
composer lint
composer lint:fix
npm run lint
npm run lint:fix

# Create plugin package
npm run zip
```

## Integration Points

### WordPress Hooks
- Use WordPress hooks appropriately
- Provide hooks for other developers
- Follow WordPress naming conventions

### Third-Party Integrations
- WP-CLI command integration
- Popular hosting provider APIs
- Backup service integrations
- Performance monitoring tools

## Future Development Areas

1. **Media Synchronization**: File transfer with integrity checking
2. **Advanced Backup Features**: Incremental backups, cloud storage
3. **Performance Optimization**: Parallel processing, caching
4. **Monitoring and Reporting**: Detailed operation analytics
5. **Template System**: Migration profiles and templates

## Support and Maintenance

### Regular Maintenance Tasks
- Update dependencies
- Test with latest WordPress versions
- Monitor performance with large datasets
- Review and update security measures

### Community Contributions
- Encourage community testing
- Maintain compatibility documentation
- Provide clear contribution guidelines
- Regular security audits

This guide ensures Claude understands the critical aspects of WP Migrate development, particularly the importance of serialized data integrity and WordPress best practices.