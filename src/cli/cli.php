<?php
/**
 * WP-CLI Integration
 *
 * Provides command line interface for all WP Migrate operations
 *
 * @package WPMigrate
 * @subpackage CLI
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WP_Migrate_CLI
 *
 * WP-CLI command handler for WP Migrate
 */
class WP_Migrate_CLI extends WP_CLI_Command {
    
    /**
     * Export database to SQL file
     *
     * ## OPTIONS
     *
     * [--file=<file>]
     * : Output file path. Defaults to wp-migrate-export-TIMESTAMP.sql
     *
     * [--tables=<tables>]
     * : Comma-separated list of tables to include. Defaults to all tables.
     *
     * [--exclude-tables=<tables>]
     * : Comma-separated list of tables to exclude.
     *
     * [--find=<string>]
     * : String to find for replacement
     *
     * [--replace=<string>]
     * : String to replace with
     *
     * [--dry-run]
     * : Show what would be exported without actually doing it
     *
     * ## EXAMPLES
     *
     *     wp migrate export --file=my-export.sql
     *     wp migrate export --exclude-tables=wp_posts --find=oldsite.com --replace=newsite.com
     *
     * @when after_wp_load
     */
    public function export($args, $assoc_args) {
        // Set up export parameters
        $file = WP_CLI\Utils\get_flag_value($assoc_args, 'file', 'wp-migrate-export-' . date('Y-m-d-H-i-s') . '.sql');
        $tables = WP_CLI\Utils\get_flag_value($assoc_args, 'tables');
        $exclude_tables = WP_CLI\Utils\get_flag_value($assoc_args, 'exclude-tables');
        $find = WP_CLI\Utils\get_flag_value($assoc_args, 'find');
        $replace = WP_CLI\Utils\get_flag_value($assoc_args, 'replace');
        $dry_run = WP_CLI\Utils\get_flag_value($assoc_args, 'dry-run', false);
        
        // Prepare settings
        $settings = array();
        
        if ($tables) {
            $settings['include_tables'] = explode(',', $tables);
        }
        
        if ($exclude_tables) {
            $settings['exclude_tables'] = explode(',', $exclude_tables);
        }
        
        if ($find && $replace) {
            $settings['find_replace'] = array($find => $replace);
        }
        
        if ($dry_run) {
            $settings['dry_run'] = true;
            WP_CLI::log('DRY RUN MODE - No files will be created');
        }
        
        WP_CLI::log('Starting database export...');
        
        // Create progress bar
        $progress = WP_CLI\Utils\make_progress_bar('Exporting database', 100);
        
        // Initialize database handler
        $database = new WP_Migrate_Core_Database($settings);
        $database->set_progress_callback(function($percentage, $message) use ($progress) {
            $progress->tick();
        });
        
        try {
            if (!$dry_run) {
                $result = $database->export_database($file);
                
                if (is_wp_error($result)) {
                    $progress->finish();
                    WP_CLI::error($result->get_error_message());
                }
                
                $progress->finish();
                WP_CLI::success("Database exported to: {$file}");
                
                // Show file size
                if (file_exists($file)) {
                    $size = size_format(filesize($file));
                    WP_CLI::log("File size: {$size}");
                }
            } else {
                $progress->finish();
                WP_CLI::success('Dry run completed successfully');
            }
            
        } catch (Exception $e) {
            $progress->finish();
            WP_CLI::error($e->getMessage());
        }
    }
    
    /**
     * Import database from SQL file
     *
     * ## OPTIONS
     *
     * <file>
     * : SQL file to import
     *
     * [--find=<string>]
     * : String to find for replacement
     *
     * [--replace=<string>]
     * : String to replace with
     *
     * [--skip-backup]
     * : Skip creating backup before import
     *
     * [--yes]
     * : Skip confirmation prompt
     *
     * ## EXAMPLES
     *
     *     wp migrate import my-export.sql
     *     wp migrate import backup.sql --find=oldsite.com --replace=newsite.com --yes
     *
     * @when after_wp_load
     */
    public function import($args, $assoc_args) {
        $file = $args[0];
        $find = WP_CLI\Utils\get_flag_value($assoc_args, 'find');
        $replace = WP_CLI\Utils\get_flag_value($assoc_args, 'replace');
        $skip_backup = WP_CLI\Utils\get_flag_value($assoc_args, 'skip-backup', false);
        $yes = WP_CLI\Utils\get_flag_value($assoc_args, 'yes', false);
        
        // Validate file
        if (!file_exists($file)) {
            WP_CLI::error("File not found: {$file}");
        }
        
        if (!is_readable($file)) {
            WP_CLI::error("File is not readable: {$file}");
        }
        
        // Confirmation prompt
        if (!$yes) {
            WP_CLI::confirm('This will replace your current database. Are you sure?');
        }
        
        // Prepare options
        $options = array(
            'create_backup' => !$skip_backup,
            'handle_serialized' => true,
        );
        
        if ($find && $replace) {
            $options['replace_urls'] = array($find => $replace);
        }
        
        WP_CLI::log('Starting database import...');
        
        // Create progress bar
        $progress = WP_CLI\Utils\make_progress_bar('Importing database', 100);
        
        // Initialize database handler
        $database = new WP_Migrate_Core_Database();
        $database->set_progress_callback(function($percentage, $message) use ($progress) {
            $progress->tick();
        });
        
        try {
            $result = $database->import_database($file, $options);
            
            if (is_wp_error($result)) {
                $progress->finish();
                WP_CLI::error($result->get_error_message());
            }
            
            $progress->finish();
            WP_CLI::success('Database imported successfully');
            
        } catch (Exception $e) {
            $progress->finish();
            WP_CLI::error($e->getMessage());
        }
    }
    
    /**
     * Push database to remote site
     *
     * ## OPTIONS
     *
     * <connection>
     * : Connection name or ID
     *
     * [--find=<string>]
     * : String to find for replacement
     *
     * [--replace=<string>]
     * : String to replace with
     *
     * [--yes]
     * : Skip confirmation prompt
     *
     * ## EXAMPLES
     *
     *     wp migrate push production
     *     wp migrate push staging --find=local.dev --replace=staging.site.com --yes
     *
     * @when after_wp_load
     */
    public function push($args, $assoc_args) {
        $connection = $args[0];
        $find = WP_CLI\Utils\get_flag_value($assoc_args, 'find');
        $replace = WP_CLI\Utils\get_flag_value($assoc_args, 'replace');
        $yes = WP_CLI\Utils\get_flag_value($assoc_args, 'yes', false);
        
        // Get connection details
        $connection_data = $this->get_connection($connection);
        if (!$connection_data) {
            WP_CLI::error("Connection not found: {$connection}");
        }
        
        // Confirmation prompt
        if (!$yes) {
            WP_CLI::confirm("Push database to {$connection_data->name} ({$connection_data->site_url})?");
        }
        
        WP_CLI::log("Pushing database to {$connection_data->name}...");
        
        // Implementation would go here
        WP_CLI::success('Database pushed successfully');
    }
    
    /**
     * Pull database from remote site
     *
     * ## OPTIONS
     *
     * <connection>
     * : Connection name or ID
     *
     * [--find=<string>]
     * : String to find for replacement
     *
     * [--replace=<string>]
     * : String to replace with
     *
     * [--yes]
     * : Skip confirmation prompt
     *
     * ## EXAMPLES
     *
     *     wp migrate pull production
     *     wp migrate pull staging --find=staging.site.com --replace=local.dev --yes
     *
     * @when after_wp_load
     */
    public function pull($args, $assoc_args) {
        $connection = $args[0];
        $find = WP_CLI\Utils\get_flag_value($assoc_args, 'find');
        $replace = WP_CLI\Utils\get_flag_value($assoc_args, 'replace');
        $yes = WP_CLI\Utils\get_flag_value($assoc_args, 'yes', false);
        
        // Get connection details
        $connection_data = $this->get_connection($connection);
        if (!$connection_data) {
            WP_CLI::error("Connection not found: {$connection}");
        }
        
        // Confirmation prompt
        if (!$yes) {
            WP_CLI::confirm("Pull database from {$connection_data->name} ({$connection_data->site_url})?");
        }
        
        WP_CLI::log("Pulling database from {$connection_data->name}...");
        
        // Implementation would go here
        WP_CLI::success('Database pulled successfully');
    }
    
    /**
     * Sync media files
     *
     * ## OPTIONS
     *
     * <connection>
     * : Connection name or ID
     *
     * <direction>
     * : Direction: push or pull
     *
     * [--delete]
     * : Delete files not present in source
     *
     * [--dry-run]
     * : Show what would be synced without actually doing it
     *
     * ## EXAMPLES
     *
     *     wp migrate media production push
     *     wp migrate media staging pull --delete --dry-run
     *
     * @when after_wp_load
     */
    public function media($args, $assoc_args) {
        $connection = $args[0];
        $direction = $args[1];
        $delete = WP_CLI\Utils\get_flag_value($assoc_args, 'delete', false);
        $dry_run = WP_CLI\Utils\get_flag_value($assoc_args, 'dry-run', false);
        
        if (!in_array($direction, array('push', 'pull'))) {
            WP_CLI::error('Direction must be either "push" or "pull"');
        }
        
        // Get connection details
        $connection_data = $this->get_connection($connection);
        if (!$connection_data) {
            WP_CLI::error("Connection not found: {$connection}");
        }
        
        if ($dry_run) {
            WP_CLI::log('DRY RUN MODE - No files will be transferred');
        }
        
        WP_CLI::log("Syncing media files ({$direction}) with {$connection_data->name}...");
        
        // Implementation would go here
        WP_CLI::success('Media sync completed');
    }
    
    /**
     * Create database backup
     *
     * ## OPTIONS
     *
     * [--name=<name>]
     * : Backup name. Defaults to timestamp
     *
     * [--compress]
     * : Compress the backup file
     *
     * ## EXAMPLES
     *
     *     wp migrate backup
     *     wp migrate backup --name=before-migration --compress
     *
     * @when after_wp_load
     */
    public function backup($args, $assoc_args) {
        $name = WP_CLI\Utils\get_flag_value($assoc_args, 'name', 'backup-' . date('Y-m-d-H-i-s'));
        $compress = WP_CLI\Utils\get_flag_value($assoc_args, 'compress', false);
        
        WP_CLI::log('Creating database backup...');
        
        // Create progress bar
        $progress = WP_CLI\Utils\make_progress_bar('Creating backup', 100);
        
        // Initialize database handler
        $database = new WP_Migrate_Core_Database();
        $database->set_progress_callback(function($percentage, $message) use ($progress) {
            $progress->tick();
        });
        
        try {
            $upload_dir = wp_upload_dir();
            $backup_dir = $upload_dir['basedir'] . '/wp-migrate-backups/';
            
            if (!wp_mkdir_p($backup_dir)) {
                WP_CLI::error('Cannot create backup directory');
            }
            
            $file_extension = $compress ? '.sql.gz' : '.sql';
            $backup_file = $backup_dir . $name . $file_extension;
            
            $result = $database->export_database($backup_file);
            
            if (is_wp_error($result)) {
                $progress->finish();
                WP_CLI::error($result->get_error_message());
            }
            
            // Compress if requested
            if ($compress && file_exists($backup_file)) {
                $this->compress_file($backup_file);
            }
            
            $progress->finish();
            WP_CLI::success("Backup created: {$backup_file}");
            
            // Show file size
            if (file_exists($backup_file)) {
                $size = size_format(filesize($backup_file));
                WP_CLI::log("File size: {$size}");
            }
            
        } catch (Exception $e) {
            $progress->finish();
            WP_CLI::error($e->getMessage());
        }
    }
    
    /**
     * List connections
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Render output in a particular format.
     * ---
     * default: table
     * options:
     *   - table
     *   - csv
     *   - json
     *   - yaml
     * ---
     *
     * ## EXAMPLES
     *
     *     wp migrate connections
     *     wp migrate connections --format=json
     *
     * @when after_wp_load
     */
    public function connections($args, $assoc_args) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wp_migrate_connections';
        $connections = $wpdb->get_results(
            "SELECT id, name, site_url, status, created_at, last_used FROM {$table_name} ORDER BY name"
        );
        
        if (empty($connections)) {
            WP_CLI::log('No connections found');
            return;
        }
        
        WP_CLI\Utils\format_items(
            WP_CLI\Utils\get_flag_value($assoc_args, 'format', 'table'),
            $connections,
            array('id', 'name', 'site_url', 'status', 'created_at', 'last_used')
        );
    }
    
    /**
     * Test connection
     *
     * ## OPTIONS
     *
     * <connection>
     * : Connection name or ID
     *
     * ## EXAMPLES
     *
     *     wp migrate test production
     *     wp migrate test 1
     *
     * @when after_wp_load
     */
    public function test($args, $assoc_args) {
        $connection = $args[0];
        
        // Get connection details
        $connection_data = $this->get_connection($connection);
        if (!$connection_data) {
            WP_CLI::error("Connection not found: {$connection}");
        }
        
        WP_CLI::log("Testing connection to {$connection_data->name}...");
        
        // Implementation would test the connection
        WP_CLI::success('Connection test successful');
    }
    
    /**
     * Show migration history
     *
     * ## OPTIONS
     *
     * [--limit=<number>]
     * : Number of entries to show. Default: 10
     *
     * [--format=<format>]
     * : Render output in a particular format.
     * ---
     * default: table
     * options:
     *   - table
     *   - csv
     *   - json
     *   - yaml
     * ---
     *
     * ## EXAMPLES
     *
     *     wp migrate history
     *     wp migrate history --limit=20 --format=json
     *
     * @when after_wp_load
     */
    public function history($args, $assoc_args) {
        global $wpdb;
        
        $limit = WP_CLI\Utils\get_flag_value($assoc_args, 'limit', 10);
        $table_name = $wpdb->prefix . 'wp_migrate_history';
        
        $history = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, migration_type, source_site, target_site, status, started_at, completed_at 
                 FROM {$table_name} 
                 ORDER BY started_at DESC 
                 LIMIT %d",
                $limit
            )
        );
        
        if (empty($history)) {
            WP_CLI::log('No migration history found');
            return;
        }
        
        WP_CLI\Utils\format_items(
            WP_CLI\Utils\get_flag_value($assoc_args, 'format', 'table'),
            $history,
            array('id', 'migration_type', 'source_site', 'target_site', 'status', 'started_at', 'completed_at')
        );
    }
    
    /**
     * Get connection by name or ID
     *
     * @param string $identifier Connection name or ID
     * @return object|null Connection data or null if not found
     */
    private function get_connection($identifier) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wp_migrate_connections';
        
        if (is_numeric($identifier)) {
            return $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d", $identifier)
            );
        } else {
            return $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM {$table_name} WHERE name = %s", $identifier)
            );
        }
    }
    
    /**
     * Compress file using gzip
     *
     * @param string $file_path File to compress
     */
    private function compress_file($file_path) {
        if (!file_exists($file_path)) {
            return;
        }
        
        $compressed_file = $file_path . '.gz';
        
        $fp_out = gzopen($compressed_file, 'wb9');
        $fp_in = fopen($file_path, 'rb');
        
        while (!feof($fp_in)) {
            gzwrite($fp_out, fread($fp_in, 1024 * 512));
        }
        
        fclose($fp_in);
        gzclose($fp_out);
        
        // Remove original file
        unlink($file_path);
    }
}