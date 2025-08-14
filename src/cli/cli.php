<?php

/**
 * WP Migrate WP-CLI Integration
 * Complete command-line interface for all migration operations
 */

if (!defined('WP_CLI') || !WP_CLI) {
    return;
}

class WP_Migrate_CLI {
    
    /**
     * Export database to SQL file
     *
     * ## OPTIONS
     *
     * [--file=<file>]
     * : Output file path. Default: auto-generated filename
     *
     * [--tables=<tables>]
     * : Comma-separated list of tables to export. Default: all tables
     *
     * [--exclude=<tables>]
     * : Comma-separated list of tables to exclude
     *
     * [--find=<find>]
     * : String to find and replace (use with --replace)
     *
     * [--replace=<replace>]
     * : Replacement string (use with --find)
     *
     * ## EXAMPLES
     *
     *     wp migrate db export
     *     wp migrate db export --file=/path/to/backup.sql
     *     wp migrate db export --find=https://production.com --replace=https://staging.com
     *
     * @subcommand db export
     */
    public function db_export($args, $assoc_args) {
        $database = new WP_Migrate_Database();
        
        $options = array(
            'backup_file' => isset($assoc_args['file']) ? $assoc_args['file'] : '',
            'tables' => isset($assoc_args['tables']) ? explode(',', $assoc_args['tables']) : array(),
            'exclude_tables' => isset($assoc_args['exclude']) ? explode(',', $assoc_args['exclude']) : array(),
            'find_replace' => array()
        );
        
        // Add find/replace if specified
        if (isset($assoc_args['find']) && isset($assoc_args['replace'])) {
            $options['find_replace'][$assoc_args['find']] = $assoc_args['replace'];
        }
        
        WP_CLI::log('Starting database export...');
        
        $result = $database->exportDatabase($options);
        
        if ($result['success']) {
            WP_CLI::success("Database exported to: {$result['file']}");
            WP_CLI::log("File size: " . size_format($result['size']));
            WP_CLI::log("Tables exported: {$result['tables']}");
        } else {
            WP_CLI::error($result['error']);
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
     * [--find=<find>]
     * : String to find and replace (use with --replace)
     *
     * [--replace=<replace>]
     * : Replacement string (use with --find)
     *
     * [--backup]
     * : Create backup before import
     *
     * [--yes]
     * : Skip confirmation prompt
     *
     * ## EXAMPLES
     *
     *     wp migrate db import backup.sql
     *     wp migrate db import backup.sql --find=https://staging.com --replace=https://production.com --backup
     *
     * @subcommand db import
     */
    public function db_import($args, $assoc_args) {
        if (empty($args[0])) {
            WP_CLI::error('Please specify the SQL file to import.');
        }
        
        $file = $args[0];
        
        if (!file_exists($file)) {
            WP_CLI::error("File not found: {$file}");
        }
        
        // Confirmation prompt unless --yes is specified
        if (!isset($assoc_args['yes'])) {
            WP_CLI::confirm('This will replace your current database. Are you sure?');
        }
        
        $database = new WP_Migrate_Database();
        
        $options = array(
            'backup_current' => isset($assoc_args['backup']),
            'find_replace' => array()
        );
        
        // Add find/replace if specified
        if (isset($assoc_args['find']) && isset($assoc_args['replace'])) {
            $options['find_replace'][$assoc_args['find']] = $assoc_args['replace'];
        }
        
        WP_CLI::log('Starting database import...');
        
        $result = $database->importDatabase($file, $options);
        
        if ($result['success']) {
            WP_CLI::success('Database import completed successfully');
            if (isset($result['backup_file'])) {
                WP_CLI::log("Backup created: {$result['backup_file']}");
            }
        } else {
            WP_CLI::error($result['error']);
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
     * [--find=<find>]
     * : String to find and replace (use with --replace)
     *
     * [--replace=<replace>]
     * : Replacement string (use with --find)
     *
     * [--yes]
     * : Skip confirmation prompt
     *
     * ## EXAMPLES
     *
     *     wp migrate db push production
     *     wp migrate db push staging --find=https://local.dev --replace=https://staging.com
     *
     * @subcommand db push
     */
    public function db_push($args, $assoc_args) {
        if (empty($args[0])) {
            WP_CLI::error('Please specify the connection name or ID.');
        }
        
        $connection_id = $this->getConnectionId($args[0]);
        
        if (!$connection_id) {
            WP_CLI::error("Connection not found: {$args[0]}");
        }
        
        // Confirmation prompt unless --yes is specified
        if (!isset($assoc_args['yes'])) {
            WP_CLI::confirm('This will replace the remote database. Are you sure?');
        }
        
        $database = new WP_Migrate_Database();
        
        $options = array(
            'find_replace' => array()
        );
        
        // Add find/replace if specified
        if (isset($assoc_args['find']) && isset($assoc_args['replace'])) {
            $options['find_replace'][$assoc_args['find']] = $assoc_args['replace'];
        }
        
        WP_CLI::log('Pushing database to remote site...');
        
        $result = $database->pushDatabase($connection_id, $options);
        
        if ($result['success']) {
            WP_CLI::success('Database push completed successfully');
        } else {
            WP_CLI::error($result['error']);
        }
    }
    
    /**
     * Pull database from remote site
     *
     * ## OPTIONS
     *
     * <connection>
     * : Connection name or ID
     *
     * [--find=<find>]
     * : String to find and replace (use with --replace)
     *
     * [--replace=<replace>]
     * : Replacement string (use with --find)
     *
     * [--backup]
     * : Create backup before import
     *
     * [--yes]
     * : Skip confirmation prompt
     *
     * ## EXAMPLES
     *
     *     wp migrate db pull production
     *     wp migrate db pull staging --find=https://staging.com --replace=https://local.dev --backup
     *
     * @subcommand db pull
     */
    public function db_pull($args, $assoc_args) {
        if (empty($args[0])) {
            WP_CLI::error('Please specify the connection name or ID.');
        }
        
        $connection_id = $this->getConnectionId($args[0]);
        
        if (!$connection_id) {
            WP_CLI::error("Connection not found: {$args[0]}");
        }
        
        // Confirmation prompt unless --yes is specified
        if (!isset($assoc_args['yes'])) {
            WP_CLI::confirm('This will replace your current database. Are you sure?');
        }
        
        $database = new WP_Migrate_Database();
        
        $options = array(
            'backup_current' => isset($assoc_args['backup']),
            'find_replace' => array()
        );
        
        // Add find/replace if specified
        if (isset($assoc_args['find']) && isset($assoc_args['replace'])) {
            $options['find_replace'][$assoc_args['find']] = $assoc_args['replace'];
        }
        
        WP_CLI::log('Pulling database from remote site...');
        
        $result = $database->pullDatabase($connection_id, $options);
        
        if ($result['success']) {
            WP_CLI::success('Database pull completed successfully');
        } else {
            WP_CLI::error($result['error']);
        }
    }
    
    /**
     * Synchronize media files
     *
     * ## OPTIONS
     *
     * <connection>
     * : Connection name or ID
     *
     * [--direction=<direction>]
     * : Sync direction: pull, push, or sync
     * ---
     * default: pull
     * options:
     *   - pull
     *   - push
     *   - sync
     * ---
     *
     * [--from=<date>]
     * : Only sync files modified after this date (YYYY-MM-DD)
     *
     * [--to=<date>]
     * : Only sync files modified before this date (YYYY-MM-DD)
     *
     * [--overwrite]
     * : Overwrite existing files
     *
     * ## EXAMPLES
     *
     *     wp migrate media sync production --direction=pull
     *     wp migrate media sync staging --direction=push --from=2023-01-01 --overwrite
     *
     * @subcommand media sync
     */
    public function media_sync($args, $assoc_args) {
        if (empty($args[0])) {
            WP_CLI::error('Please specify the connection name or ID.');
        }
        
        $connection_id = $this->getConnectionId($args[0]);
        
        if (!$connection_id) {
            WP_CLI::error("Connection not found: {$args[0]}");
        }
        
        $media = new WP_Migrate_Media();
        
        $options = array(
            'direction' => isset($assoc_args['direction']) ? $assoc_args['direction'] : 'pull',
            'date_from' => isset($assoc_args['from']) ? $assoc_args['from'] : null,
            'date_to' => isset($assoc_args['to']) ? $assoc_args['to'] : null,
            'overwrite_existing' => isset($assoc_args['overwrite'])
        );
        
        WP_CLI::log("Starting media sync ({$options['direction']})...");
        
        $result = $media->syncMediaLibrary($connection_id, $options);
        
        if ($result['success']) {
            WP_CLI::success('Media sync completed successfully');
            WP_CLI::log("Transferred files: {$result['transferred_files']}");
            WP_CLI::log("Skipped files: {$result['skipped_files']}");
            WP_CLI::log("Total size: " . size_format($result['total_size']));
            
            if (!empty($result['errors'])) {
                WP_CLI::warning('Some errors occurred:');
                foreach ($result['errors'] as $error) {
                    WP_CLI::log("  - {$error}");
                }
            }
        } else {
            WP_CLI::error($result['error']);
        }
    }
    
    /**
     * Create backup
     *
     * ## OPTIONS
     *
     * [--type=<type>]
     * : Backup type: full, database, or files
     * ---
     * default: full
     * options:
     *   - full
     *   - database
     *   - files
     * ---
     *
     * [--name=<name>]
     * : Custom backup name
     *
     * [--description=<description>]
     * : Backup description
     *
     * ## EXAMPLES
     *
     *     wp migrate backup create
     *     wp migrate backup create --type=database --name=pre-migration-backup
     *     wp migrate backup create --type=files --description="Before plugin update"
     *
     * @subcommand backup create
     */
    public function backup_create($args, $assoc_args) {
        $backup_manager = new WP_Migrate_Backup();
        
        $type = isset($assoc_args['type']) ? $assoc_args['type'] : 'full';
        $name = isset($assoc_args['name']) ? $assoc_args['name'] : null;
        $description = isset($assoc_args['description']) ? $assoc_args['description'] : null;
        
        WP_CLI::log("Creating {$type} backup...");
        
        switch ($type) {
            case 'full':
                $result = $backup_manager->createFullBackup($name, $description);
                break;
            case 'database':
                $result = $backup_manager->createDatabaseBackup();
                break;
            case 'files':
                $result = $backup_manager->createFilesBackup();
                break;
            default:
                WP_CLI::error("Invalid backup type: {$type}");
        }
        
        if ($result['success']) {
            WP_CLI::success('Backup created successfully');
            WP_CLI::log("Backup ID: {$result['backup_id']}");
            WP_CLI::log("Name: {$result['name']}");
            WP_CLI::log("Size: " . size_format($result['size']));
        } else {
            WP_CLI::error($result['error']);
        }
    }
    
    /**
     * List backups
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Output format
     * ---
     * default: table
     * options:
     *   - table
     *   - csv
     *   - json
     * ---
     *
     * ## EXAMPLES
     *
     *     wp migrate backup list
     *     wp migrate backup list --format=csv
     *
     * @subcommand backup list
     */
    public function backup_list($args, $assoc_args) {
        $backup_manager = new WP_Migrate_Backup();
        $backups = $backup_manager->getBackups();
        
        if (empty($backups)) {
            WP_CLI::log('No backups found.');
            return;
        }
        
        $items = array();
        foreach ($backups as $backup) {
            $items[] = array(
                'ID' => $backup->id,
                'Name' => $backup->name,
                'Type' => $backup->backup_type,
                'Size' => size_format($backup->file_size),
                'Created' => $backup->created_at
            );
        }
        
        $format = isset($assoc_args['format']) ? $assoc_args['format'] : 'table';
        WP_CLI\Utils\format_items($format, $items, array('ID', 'Name', 'Type', 'Size', 'Created'));
    }
    
    /**
     * Restore from backup
     *
     * ## OPTIONS
     *
     * <backup-id>
     * : Backup ID to restore from
     *
     * [--database]
     * : Restore database only
     *
     * [--files]
     * : Restore files only
     *
     * [--yes]
     * : Skip confirmation prompt
     *
     * ## EXAMPLES
     *
     *     wp migrate backup restore 123
     *     wp migrate backup restore 123 --database --yes
     *
     * @subcommand backup restore
     */
    public function backup_restore($args, $assoc_args) {
        if (empty($args[0])) {
            WP_CLI::error('Please specify the backup ID.');
        }
        
        $backup_id = intval($args[0]);
        
        // Confirmation prompt unless --yes is specified
        if (!isset($assoc_args['yes'])) {
            WP_CLI::confirm('This will restore from backup and may overwrite current data. Are you sure?');
        }
        
        $backup_manager = new WP_Migrate_Backup();
        
        $options = array(
            'restore_database' => !isset($assoc_args['files']),
            'restore_files' => !isset($assoc_args['database'])
        );
        
        WP_CLI::log('Starting restore from backup...');
        
        $result = $backup_manager->restoreFromBackup($backup_id, $options);
        
        if ($result['success']) {
            WP_CLI::success('Restore completed successfully');
            WP_CLI::log("Database restored: " . ($result['results']['database_restored'] ? 'Yes' : 'No'));
            WP_CLI::log("Files restored: " . ($result['results']['files_restored'] ? 'Yes' : 'No'));
        } else {
            WP_CLI::error($result['error']);
        }
    }
    
    /**
     * Manage connections
     *
     * ## OPTIONS
     *
     * <action>
     * : Action to perform: list, add, test, delete
     *
     * [<name>]
     * : Connection name (required for add, test, delete)
     *
     * [--url=<url>]
     * : Site URL (required for add)
     *
     * [--api-key=<api-key>]
     * : API key (required for add)
     *
     * [--description=<description>]
     * : Connection description (optional for add)
     *
     * ## EXAMPLES
     *
     *     wp migrate connection list
     *     wp migrate connection add production --url=https://production.com --api-key=abc123
     *     wp migrate connection test production
     *     wp migrate connection delete staging
     *
     * @subcommand connection
     */
    public function connection($args, $assoc_args) {
        if (empty($args[0])) {
            WP_CLI::error('Please specify an action: list, add, test, delete');
        }
        
        $action = $args[0];
        $connections = new WP_Migrate_Connections();
        
        switch ($action) {
            case 'list':
                $all_connections = $connections->getConnections();
                
                if (empty($all_connections)) {
                    WP_CLI::log('No connections found.');
                    return;
                }
                
                $items = array();
                foreach ($all_connections as $connection) {
                    $items[] = array(
                        'ID' => $connection->id,
                        'Name' => $connection->name,
                        'URL' => $connection->url,
                        'Status' => $connection->test_status ?: 'Not tested',
                        'Last Tested' => $connection->last_tested_at ?: 'Never'
                    );
                }
                
                WP_CLI\Utils\format_items('table', $items, array('ID', 'Name', 'URL', 'Status', 'Last Tested'));
                break;
                
            case 'add':
                if (empty($args[1])) {
                    WP_CLI::error('Please specify the connection name.');
                }
                
                $data = array(
                    'name' => $args[1],
                    'url' => isset($assoc_args['url']) ? $assoc_args['url'] : '',
                    'api_key' => isset($assoc_args['api-key']) ? $assoc_args['api-key'] : '',
                    'description' => isset($assoc_args['description']) ? $assoc_args['description'] : ''
                );
                
                if (empty($data['url']) || empty($data['api_key'])) {
                    WP_CLI::error('URL and API key are required.');
                }
                
                $result = $connections->createConnection($data);
                
                if ($result['success']) {
                    WP_CLI::success("Connection '{$args[1]}' created successfully");
                    
                    if (isset($result['test_result'])) {
                        if ($result['test_result']['success']) {
                            WP_CLI::log('Connection test: PASSED');
                        } else {
                            WP_CLI::warning('Connection test: FAILED - ' . $result['test_result']['error']);
                        }
                    }
                } else {
                    WP_CLI::error($result['error']);
                }
                break;
                
            case 'test':
                if (empty($args[1])) {
                    WP_CLI::error('Please specify the connection name.');
                }
                
                $connection_id = $this->getConnectionId($args[1]);
                
                if (!$connection_id) {
                    WP_CLI::error("Connection not found: {$args[1]}");
                }
                
                WP_CLI::log('Testing connection...');
                
                $result = $connections->testConnection($connection_id);
                
                if ($result['success']) {
                    WP_CLI::success('Connection test passed');
                    if (isset($result['remote_info'])) {
                        WP_CLI::log('Remote site info: ' . print_r($result['remote_info'], true));
                    }
                } else {
                    WP_CLI::error('Connection test failed: ' . $result['error']);
                }
                break;
                
            case 'delete':
                if (empty($args[1])) {
                    WP_CLI::error('Please specify the connection name.');
                }
                
                $connection_id = $this->getConnectionId($args[1]);
                
                if (!$connection_id) {
                    WP_CLI::error("Connection not found: {$args[1]}");
                }
                
                $result = $connections->deleteConnection($connection_id);
                
                if ($result['success']) {
                    WP_CLI::success("Connection '{$args[1]}' deleted successfully");
                } else {
                    WP_CLI::error($result['error']);
                }
                break;
                
            default:
                WP_CLI::error("Unknown action: {$action}");
        }
    }
    
    /**
     * Get migration status and statistics
     *
     * ## EXAMPLES
     *
     *     wp migrate status
     *
     */
    public function status($args, $assoc_args) {
        // Database stats
        global $wpdb;
        $db_size = $wpdb->get_var("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 1) AS 'DB Size in MB' FROM information_schema.tables WHERE table_schema = '{$wpdb->dbname}'");
        
        // Media stats
        $media = new WP_Migrate_Media();
        $media_stats = $media->getMediaStats();
        
        // Connection stats
        $connections = new WP_Migrate_Connections();
        $connection_stats = $connections->getConnectionStatusSummary();
        
        // Backup stats
        $backup_manager = new WP_Migrate_Backup();
        $recent_backups = $backup_manager->getBackups(5);
        
        WP_CLI::log('=== WP Migrate Status ===');
        WP_CLI::log('');
        WP_CLI::log('Database:');
        WP_CLI::log("  Size: {$db_size} MB");
        WP_CLI::log('');
        WP_CLI::log('Media Library:');
        WP_CLI::log("  Files: {$media_stats['total_files']}");
        WP_CLI::log("  Size: {$media_stats['total_size_formatted']}");
        WP_CLI::log('');
        WP_CLI::log('Connections:');
        WP_CLI::log("  Total: {$connection_stats['total']}");
        WP_CLI::log("  Active: {$connection_stats['active']}");
        WP_CLI::log("  Recently tested: {$connection_stats['tested_recently']}");
        WP_CLI::log('');
        WP_CLI::log('Backups:');
        WP_CLI::log("  Recent backups: " . count($recent_backups));
        
        if (!empty($recent_backups)) {
            WP_CLI::log("  Latest: {$recent_backups[0]->name} ({$recent_backups[0]->created_at})");
        }
    }
    
    /**
     * Get connection ID by name or return ID if numeric
     */
    private function getConnectionId($name_or_id) {
        if (is_numeric($name_or_id)) {
            return intval($name_or_id);
        }
        
        global $wpdb;
        
        $connection_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}wp_migrate_connections WHERE name = %s",
                $name_or_id
            )
        );
        
        return $connection_id ? intval($connection_id) : null;
    }
}

// Register WP-CLI commands
WP_CLI::add_command('migrate', 'WP_Migrate_CLI');