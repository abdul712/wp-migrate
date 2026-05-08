# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

WP Migrate is a comprehensive WordPress migration tool designed to automate database, media, and file synchronization between WordPress environments (local, staging, production). The project aims to eliminate manual migration processes and provide enterprise-grade migration capabilities for WordPress developers and agencies.

## Architecture and Structure

This is a WordPress plugin project structured around the following core architectural components:

### Core Migration Engine
- **Database Migration**: Intelligent database export/import with automatic URL/path replacement and WordPress serialized data handling
- **Media Synchronization**: Bi-directional media file sync with incremental updates and integrity verification
- **File Transfer System**: Theme and plugin file synchronization with version comparison and conflict resolution
- **Connection Management**: Secure site-to-site communication with SSL/TLS encryption and API key authentication

### WordPress Integration Layers
- **Plugin Architecture**: Standard WordPress plugin structure with activation hooks, database schema management, and admin interface integration
- **Multisite Support**: Complex multisite network migrations, subsite extraction, and single-site to multisite conversion
- **CLI Integration**: Full WP-CLI compatibility for automation, scripting, and CI/CD pipeline integration
- **Security Framework**: Backup creation, rollback capabilities, and secure data transmission protocols

### Development Phases
The project follows a 5-phase development approach:
1. **Phase 1 (MVP)**: Core database migration, URL/path replacement, serialized data handling
2. **Phase 2**: Media file sync, theme/plugin transfers, file integrity verification
3. **Phase 3**: Push/pull operations, connection management, CLI integration
4. **Phase 4**: Multisite support, advanced security, automation features
5. **Phase 5**: Ecosystem integrations, hosting provider partnerships, API development

## Development Context

### WordPress Requirements
- WordPress 5.0+ compatibility required
- PHP 7.4+ minimum requirement
- MySQL 5.6+ database support
- Must follow WordPress coding standards and security best practices

### Critical Implementation Considerations
- **Serialized Data Integrity**: WordPress uses PHP serialization extensively; any find/replace operations must preserve serialized data structure to prevent corruption
- **Large Dataset Handling**: Support for databases 1GB+ requires chunked processing, progressive loading, and memory-efficient operations
- **Cross-Environment URL Handling**: Automatic detection and replacement of site URLs, file paths, and domain-specific configurations
- **Backup and Recovery**: Mandatory backup creation before all destructive operations with rollback capabilities

### Security and Performance Requirements
- All data transfers must use encrypted connections
- File operations require proper permission handling and cleanup
- Large file processing needs timeout handling and resume capabilities
- Database operations must be transactional with proper error recovery

## GitHub Actions Integration

Two Claude Code workflows are configured:

### Automatic Code Review (`.github/workflows/claude-code-review.yml`)
- Triggers on all PRs for automated code review
- Focuses on WordPress best practices, security, and performance
- Provides feedback on code quality, potential bugs, and test coverage

### Interactive Claude (`github/workflows/claude.yml`)
- Activated by mentioning `@claude` in PR/issue comments
- Full repository access for development assistance
- Can run CI/CD operations when needed (uncomment `allowed_tools` in workflow)

## WordPress Plugin Development Patterns

When implementing features:
- Use WordPress hooks and filters appropriately
- Implement proper database table creation and management
- Follow WordPress admin interface conventions
- Ensure multisite compatibility from the start
- Implement proper error handling and logging
- Create backup mechanisms before destructive operations

## Key Technical Challenges

1. **Serialized Data Processing**: Requires deep understanding of PHP serialization and WordPress data structures
2. **Cross-Server Communication**: Secure API endpoints for site-to-site data transfer
3. **Large File Handling**: Efficient processing of media libraries and database exports
4. **Environment Detection**: Automatic identification of local vs staging vs production environments
5. **WordPress Multisite Complexity**: Network-wide operations and subsite isolation

## Development Environment Setup

### Prerequisites
- PHP 7.4+ with required extensions (mysqli, json, zip, curl)
- Composer for PHP dependency management
- Node.js 16+ and npm for build tools and asset compilation
- WordPress development environment (Local by Flywheel, XAMPP, or Docker)
- Git for version control

### Development Tools and Stack
- **Frontend Build**: Webpack/Gulp for asset compilation (CSS/JS minification, SCSS compilation)
- **PHP Tools**: Composer for autoloading, PHPUnit for testing, PHP_CodeSniffer for standards
- **WordPress Standards**: WordPress Coding Standards, WordPress Plugin Boilerplate structure
- **Database Tools**: WordPress database abstraction layer, MySQL/MariaDB
- **CLI Integration**: WP-CLI for command line operations and testing

### Project Structure (To Be Implemented)
```
wp-migrate/
├── src/                    # Core plugin source code
│   ├── admin/             # WordPress admin interface
│   ├── api/               # REST API endpoints
│   ├── core/              # Core migration engine
│   ├── cli/               # WP-CLI integration
│   └── assets/            # CSS/JS/images
├── tests/                 # PHPUnit and integration tests
├── build/                 # Build scripts and configurations
├── languages/             # Internationalization files
├── vendor/                # Composer dependencies
├── node_modules/          # npm dependencies
└── wp-migrate.php         # Main plugin file
```

### Build Commands (To Be Defined)
- `composer install` - Install PHP dependencies
- `npm install` - Install Node.js dependencies
- `npm run build` - Build production assets
- `npm run dev` - Build development assets with watch
- `composer test` - Run PHPUnit tests
- `composer lint` - Run PHP code standards check
- `wp migrate --help` - Test WP-CLI integration (when implemented)

### Context7 Integration
Use Context7 MCP server to fetch the latest documentation and code examples:
- WordPress Plugin Development: Use Context7 to get current WordPress plugin boilerplate and best practices
- WP-CLI Integration: Fetch latest WP-CLI command structure and examples
- Database Migration Patterns: Research WordPress database migration libraries and patterns
- Serialization Handling: Get PHP serialization/unserialization best practices
- WordPress REST API: Fetch current WordPress REST API documentation and security practices

Refer to the comprehensive PRD.md for detailed feature specifications, technical requirements, and development roadmap.