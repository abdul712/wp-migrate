=== WP Migrate ===
Contributors: abdul712
Tags: migration, database, backup, sync, wordpress, multisite, cli
Requires at least: 5.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Comprehensive WordPress migration tool that enables seamless database, media, and file synchronization between WordPress environments.

== Description ==

WP Migrate is a powerful WordPress plugin designed to simplify and automate the complex process of migrating WordPress sites between different environments. Whether you're moving from development to staging, staging to production, or need to keep multiple environments synchronized, WP Migrate provides the tools you need.

= Key Features =

* **Database Migration**: Export and import WordPress databases with intelligent URL and path replacement
* **Serialized Data Handling**: Safely process WordPress serialized data without corruption
* **Media Library Sync**: Transfer media files between WordPress installations
* **Theme & Plugin Files**: Sync themes and plugins across environments
* **WordPress Multisite Support**: Handle complex multisite network migrations
* **WP-CLI Integration**: Full command-line interface for automation and scripting
* **Backup & Restore**: Automatic backup creation before destructive operations
* **Connection Management**: Securely manage connections to multiple WordPress environments
* **Migration History**: Track all migration operations with detailed logging
* **Progress Monitoring**: Real-time progress tracking for long-running operations

= WordPress Serialized Data Protection =

One of the most critical features of WP Migrate is its ability to safely handle WordPress serialized data during URL and path replacements. Standard find/replace operations can corrupt WordPress data like widgets, customizer settings, and custom fields. WP Migrate uses advanced algorithms to:

* Detect PHP serialized data automatically
* Safely unserialize, process, and re-serialize data
* Maintain data integrity for all WordPress data types
* Handle nested and complex serialized structures

= WP-CLI Integration =

WP Migrate provides comprehensive WP-CLI support for automation:

* Database export/import operations
* Push/pull operations between connected sites
* Media file synchronization
* Backup creation and management
* Connection testing and management
* Migration history review

= Security & Performance =

* Encrypted data transmission using SSL/TLS
* Secure API key-based authentication
* Proper WordPress capability checks
* CSRF protection with nonces
* Chunked processing for large datasets
* Memory-efficient operations
* Timeout handling for long operations

= Use Cases =

* **Development to Production**: Push your local changes to live sites
* **Content Sync**: Pull production content to development environments
* **Staging Workflows**: Maintain synchronized staging environments
* **Agency Management**: Manage multiple client sites efficiently
* **Backup & Recovery**: Create and restore database backups
* **Site Migrations**: Move sites between hosting providers
* **Multisite Management**: Handle complex WordPress multisite networks

== Installation ==

= Automatic Installation =

1. Log in to your WordPress admin panel
2. Navigate to Plugins > Add New
3. Search for "WP Migrate"
4. Click "Install Now" and then "Activate"

= Manual Installation =

1. Download the plugin ZIP file
2. Upload it to your WordPress plugins directory (`/wp-content/plugins/`)
3. Extract the ZIP file
4. Activate the plugin through the WordPress admin panel

= After Installation =

1. Go to **WP Migrate > Connections** to set up your first connection
2. Configure your migration settings in **WP Migrate > Settings**
3. Start your first migration using the **WP Migrate > Migration** wizard

== Frequently Asked Questions ==

= Is my data safe during migration? =

Yes! WP Migrate automatically creates backups before any destructive operations. Additionally, all data transfers use encrypted connections, and the plugin includes comprehensive error handling and rollback capabilities.

= Can I migrate large WordPress sites? =

Absolutely. WP Migrate is designed to handle large WordPress installations using chunked processing, memory-efficient operations, and progress tracking. For very large sites, we recommend using the WP-CLI interface.

= Does WP Migrate work with WordPress multisite? =

Yes, WP Migrate fully supports WordPress multisite networks. You can migrate entire networks or individual subsites.

= What about WordPress serialized data? =

WP Migrate includes advanced serialized data handling that prevents data corruption during URL/path replacements. This is crucial for maintaining the integrity of WordPress widgets, customizer settings, and custom fields.

= Can I automate migrations? =

Yes! WP Migrate provides comprehensive WP-CLI integration, allowing you to automate migrations using scripts, cron jobs, or deployment pipelines.

= Is there a limit on database size? =

No hard limits, but performance depends on your server configuration. WP Migrate uses chunked processing to handle large databases efficiently. For very large sites (multi-GB databases), use the WP-CLI interface for best performance.

= Can I test connections before migrating? =

Yes, WP Migrate includes connection testing functionality to verify connectivity and authentication before performing migrations.

= What happens if a migration fails? =

WP Migrate includes comprehensive error handling and logging. If a migration fails, you can review detailed logs to understand what happened. Automatic backups allow you to restore your site to its previous state.

= Can I schedule automatic migrations? =

While the plugin doesn't include built-in scheduling, you can use WP-CLI commands with cron jobs or other scheduling systems to automate migrations.

= Does WP Migrate work with hosting providers? =

WP Migrate works with any hosting provider that supports standard WordPress installations. Some hosting providers may have specific requirements or restrictions for database operations.

== Screenshots ==

1. **Dashboard**: Overview of migration statistics and quick actions
2. **Migration Wizard**: Step-by-step migration process
3. **Connection Management**: Manage connections to remote WordPress sites
4. **Migration History**: Track all migration operations with detailed logs
5. **Settings**: Configure backup, performance, and security settings
6. **WP-CLI Interface**: Command-line interface for automation

== Changelog ==

= 1.0.0 =
* Initial release
* Database migration with URL/path replacement
* Safe WordPress serialized data handling
* WordPress admin interface with migration wizard
* WP-CLI integration with full command set
* Connection management system
* Automatic backup creation
* Migration history tracking
* Comprehensive test suite
* Security features including encrypted transfers
* Performance optimizations for large datasets

== Upgrade Notice ==

= 1.0.0 =
Initial release of WP Migrate. No upgrade considerations.

== Technical Requirements ==

= Minimum Requirements =
* WordPress 5.0 or higher
* PHP 7.4 or higher
* MySQL 5.6 or higher
* Required PHP extensions: mysqli, json, zip, curl

= Recommended Configuration =
* PHP 8.0+ for better performance
* Increased PHP memory limit (512MB+) for large sites
* Higher PHP execution time limit for large migrations
* SSL certificates for secure data transfer

= Development Requirements =
* Node.js 16+ (for asset building)
* Composer (for dependency management)
* WP-CLI (for command-line functionality)

== Privacy and Data Handling ==

WP Migrate processes WordPress database content during migrations. The plugin:

* Does not send any data to external services
* Only transfers data between WordPress installations you explicitly configure
* Uses encrypted connections for all data transfers
* Does not store sensitive information in plain text
* Provides options to exclude sensitive data from migrations
* Automatically cleans up temporary files after operations

All data processing occurs on your servers or between servers you control. No data is sent to the plugin developers or third parties.

== Support ==

* **Documentation**: Comprehensive documentation available in the plugin directory
* **GitHub Issues**: Report bugs and request features on GitHub
* **WordPress Forums**: Community support via WordPress.org forums

== Contributing ==

WP Migrate is open source and welcomes contributions:

* **GitHub Repository**: https://github.com/abdul712/wp-migrate
* **Issue Reporting**: Use GitHub Issues for bug reports and feature requests
* **Pull Requests**: Follow the contribution guidelines in the repository
* **Testing**: Help test with different WordPress configurations

== Credits ==

Developed by Abdul Rahim with contributions from the WordPress community. Special thanks to all contributors, testers, and users who help improve the plugin.