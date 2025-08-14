<?php
/**
 * Database Migration Core Handler
 *
 * Handles database export/import functionality with intelligent URL/path replacement
 * and critical WordPress serialized data handling.
 *
 * @package WPMigrate
 * @subpackage Core
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WP_Migrate_Core_Database
 *
 * Core database migration functionality
 */
class WP_Migrate_Core_Database {
    
    /**
     * Database connection
     *
     * @var wpdb
     */
    private $wpdb;
    
    /**
     * Migration settings
     *
     * @var array
     */
    private $settings;
    
    /**
     * Progress callback
     *
     * @var callable
     */
    private $progress_callback;
    
    /**
     * Constructor
     *
     * @param array $settings Migration settings
     */
    public function __construct($settings = array()) {
        global $wpdb;
        $this->wpdb = $wpdb;
        
        $this->settings = wp_parse_args($settings, array(
            'chunk_size' => 1000,
            'timeout' => 300,
            'find_replace' => array(),
            'exclude_tables' => array(),
            'include_tables' => array(),
            'dry_run' => false,
        ));
    }
    
    /**
     * Set progress callback
     *
     * @param callable $callback Progress callback function
     */
    public function set_progress_callback($callback) {
        $this->progress_callback = $callback;
    }
    
    /**
     * Report progress
     *
     * @param int $percentage Progress percentage
     * @param string $message Progress message
     */
    private function report_progress($percentage, $message) {
        if (is_callable($this->progress_callback)) {
            call_user_func($this->progress_callback, $percentage, $message);
        }
    }
    
    /**
     * Export database to SQL file
     *
     * @param string $file_path Output file path
     * @param array $options Export options
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function export_database($file_path, $options = array()) {
        $options = wp_parse_args($options, array(
            'include_drop_table' => true,
            'include_create_table' => true,
            'include_data' => true,
            'add_locks' => true,
            'extended_insert' => true,
        ));
        
        try {
            $this->report_progress(0, __('Starting database export...', 'wp-migrate'));
            
            // Get tables to export
            $tables = $this->get_tables_to_export();
            if (empty($tables)) {
                return new WP_Error('no_tables', __('No tables to export', 'wp-migrate'));
            }
            
            // Open output file
            $handle = fopen($file_path, 'w');
            if (!$handle) {
                return new WP_Error('file_error', __('Cannot create export file', 'wp-migrate'));
            }
            
            // Write header
            $this->write_sql_header($handle);
            
            $progress_per_table = 90 / count($tables);
            $current_progress = 5;
            
            // Export each table
            foreach ($tables as $index => $table) {
                $this->report_progress($current_progress, sprintf(__('Exporting table: %s', 'wp-migrate'), $table));
                
                $result = $this->export_table($handle, $table, $options);
                if (is_wp_error($result)) {
                    fclose($handle);
                    return $result;
                }
                
                $current_progress += $progress_per_table;
            }
            
            // Write footer
            $this->write_sql_footer($handle);
            fclose($handle);
            
            $this->report_progress(100, __('Database export completed successfully', 'wp-migrate'));
            
            return true;
            
        } catch (Exception $e) {
            if (isset($handle)) {
                fclose($handle);
            }
            return new WP_Error('export_error', $e->getMessage());
        }
    }
    
    /**
     * Import database from SQL file
     *
     * @param string $file_path SQL file path
     * @param array $options Import options
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function import_database($file_path, $options = array()) {
        $options = wp_parse_args($options, array(
            'create_backup' => true,
            'replace_urls' => array(),
            'handle_serialized' => true,
        ));
        
        try {
            $this->report_progress(0, __('Starting database import...', 'wp-migrate'));
            
            // Validate file
            if (!file_exists($file_path) || !is_readable($file_path)) {
                return new WP_Error('file_error', __('SQL file not found or not readable', 'wp-migrate'));
            }
            
            // Create backup if requested
            if ($options['create_backup']) {
                $this->report_progress(10, __('Creating backup...', 'wp-migrate'));
                $backup_result = $this->create_backup();
                if (is_wp_error($backup_result)) {
                    return $backup_result;
                }
            }
            
            // Read and execute SQL file
            $this->report_progress(20, __('Reading SQL file...', 'wp-migrate'));
            $sql_content = file_get_contents($file_path);
            
            if (empty($sql_content)) {
                return new WP_Error('file_error', __('SQL file is empty', 'wp-migrate'));
            }
            
            // Process URL replacements if needed
            if (!empty($options['replace_urls']) && $options['handle_serialized']) {
                $this->report_progress(30, __('Processing URL replacements...', 'wp-migrate'));
                $sql_content = $this->process_url_replacements($sql_content, $options['replace_urls']);
            }
            
            // Execute SQL statements
            $this->report_progress(50, __('Executing SQL statements...', 'wp-migrate'));
            $result = $this->execute_sql_statements($sql_content);
            
            if (is_wp_error($result)) {
                return $result;
            }
            
            // Clear caches
            $this->report_progress(90, __('Clearing caches...', 'wp-migrate'));
            $this->clear_caches();
            
            $this->report_progress(100, __('Database import completed successfully', 'wp-migrate'));
            
            return true;
            
        } catch (Exception $e) {
            return new WP_Error('import_error', $e->getMessage());
        }
    }
    
    /**
     * Process URL replacements with serialized data handling
     *
     * @param string $sql_content SQL content
     * @param array $replacements URL replacements
     * @return string Processed SQL content
     */
    private function process_url_replacements($sql_content, $replacements) {
        foreach ($replacements as $from => $to) {
            // Handle regular text replacements
            $sql_content = str_replace($from, $to, $sql_content);
            
            // Handle serialized data replacements
            $sql_content = $this->replace_serialized_data($sql_content, $from, $to);
        }
        
        return $sql_content;
    }
    
    /**
     * Replace URLs in serialized data safely
     *
     * Critical function to prevent WordPress serialized data corruption
     *
     * @param string $content SQL content
     * @param string $from URL to replace
     * @param string $to Replacement URL
     * @return string Processed content
     */
    private function replace_serialized_data($content, $from, $to) {
        // Pattern to match serialized data
        $pattern = '/s:(\d+):"([^"]*' . preg_quote($from, '/') . '[^"]*)"/';
        
        return preg_replace_callback($pattern, function($matches) use ($from, $to) {
            $original_string = $matches[2];
            $new_string = str_replace($from, $to, $original_string);
            $new_length = strlen($new_string);
            
            return 's:' . $new_length . ':"' . $new_string . '"';
        }, $content);
    }
    
    /**
     * Get tables to export based on settings
     *
     * @return array Array of table names
     */
    private function get_tables_to_export() {
        $all_tables = $this->wpdb->get_col("SHOW TABLES");
        
        // Filter tables based on include/exclude settings
        if (!empty($this->settings['include_tables'])) {
            return array_intersect($all_tables, $this->settings['include_tables']);
        }
        
        if (!empty($this->settings['exclude_tables'])) {
            return array_diff($all_tables, $this->settings['exclude_tables']);
        }
        
        return $all_tables;
    }
    
    /**
     * Export single table
     *
     * @param resource $handle File handle
     * @param string $table Table name
     * @param array $options Export options
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    private function export_table($handle, $table, $options) {
        try {
            // Write table structure
            if ($options['include_drop_table']) {
                fwrite($handle, "\nDROP TABLE IF EXISTS `{$table}`;\n");
            }
            
            if ($options['include_create_table']) {
                $create_table = $this->wpdb->get_row("SHOW CREATE TABLE `{$table}`", ARRAY_N);
                if ($create_table) {
                    fwrite($handle, "\n" . $create_table[1] . ";\n\n");
                }
            }
            
            // Export table data
            if ($options['include_data']) {
                $this->export_table_data($handle, $table, $options);
            }
            
            return true;
            
        } catch (Exception $e) {
            return new WP_Error('table_export_error', sprintf(__('Error exporting table %s: %s', 'wp-migrate'), $table, $e->getMessage()));
        }
    }
    
    /**
     * Export table data in chunks
     *
     * @param resource $handle File handle
     * @param string $table Table name
     * @param array $options Export options
     */
    private function export_table_data($handle, $table, $options) {
        $chunk_size = $this->settings['chunk_size'];
        $offset = 0;
        
        if ($options['add_locks']) {
            fwrite($handle, "LOCK TABLES `{$table}` WRITE;\n");
        }
        
        do {
            $rows = $this->wpdb->get_results(
                $this->wpdb->prepare(
                    "SELECT * FROM `{$table}` LIMIT %d OFFSET %d",
                    $chunk_size,
                    $offset
                ),
                ARRAY_A
            );
            
            if (!empty($rows)) {
                $this->write_insert_statements($handle, $table, $rows, $options);
                $offset += $chunk_size;
            }
            
        } while (!empty($rows));
        
        if ($options['add_locks']) {
            fwrite($handle, "UNLOCK TABLES;\n\n");
        }
    }
    
    /**
     * Write INSERT statements
     *
     * @param resource $handle File handle
     * @param string $table Table name
     * @param array $rows Table rows
     * @param array $options Export options
     */
    private function write_insert_statements($handle, $table, $rows, $options) {
        if (empty($rows)) {
            return;
        }
        
        $columns = array_keys($rows[0]);
        $columns_str = '`' . implode('`, `', $columns) . '`';
        
        if ($options['extended_insert']) {
            // Multiple rows in single INSERT
            fwrite($handle, "INSERT INTO `{$table}` ({$columns_str}) VALUES\n");
            
            $values_array = array();
            foreach ($rows as $row) {
                $values = array();
                foreach ($row as $value) {
                    if (is_null($value)) {
                        $values[] = 'NULL';
                    } else {
                        $values[] = "'" . $this->wpdb->_real_escape($value) . "'";
                    }
                }
                $values_array[] = '(' . implode(', ', $values) . ')';
            }
            
            fwrite($handle, implode(",\n", $values_array) . ";\n");
            
        } else {
            // Single row per INSERT
            foreach ($rows as $row) {
                $values = array();
                foreach ($row as $value) {
                    if (is_null($value)) {
                        $values[] = 'NULL';
                    } else {
                        $values[] = "'" . $this->wpdb->_real_escape($value) . "'";
                    }
                }
                
                fwrite($handle, "INSERT INTO `{$table}` ({$columns_str}) VALUES (" . implode(', ', $values) . ");\n");
            }
        }
    }
    
    /**
     * Write SQL header
     *
     * @param resource $handle File handle
     */
    private function write_sql_header($handle) {
        $header = "-- WP Migrate Database Export\n";
        $header .= "-- Generated on: " . date('Y-m-d H:i:s') . "\n";
        $header .= "-- WordPress Version: " . get_bloginfo('version') . "\n";
        $header .= "-- Plugin Version: " . WP_MIGRATE_VERSION . "\n\n";
        $header .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
        $header .= "SET AUTOCOMMIT = 0;\n";
        $header .= "START TRANSACTION;\n";
        $header .= "SET time_zone = \"+00:00\";\n\n";
        
        fwrite($handle, $header);
    }
    
    /**
     * Write SQL footer
     *
     * @param resource $handle File handle
     */
    private function write_sql_footer($handle) {
        $footer = "\nCOMMIT;\n";
        $footer .= "-- End of WP Migrate Database Export\n";
        
        fwrite($handle, $footer);
    }
    
    /**
     * Execute SQL statements from content
     *
     * @param string $sql_content SQL content
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    private function execute_sql_statements($sql_content) {
        // Split SQL content into individual statements
        $statements = $this->split_sql_statements($sql_content);
        
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (empty($statement)) {
                continue;
            }
            
            $result = $this->wpdb->query($statement);
            if ($result === false) {
                return new WP_Error('sql_error', sprintf(__('SQL Error: %s', 'wp-migrate'), $this->wpdb->last_error));
            }
        }
        
        return true;
    }
    
    /**
     * Split SQL content into individual statements
     *
     * @param string $sql_content SQL content
     * @return array Array of SQL statements
     */
    private function split_sql_statements($sql_content) {
        // Remove comments
        $sql_content = preg_replace('/--.*$/m', '', $sql_content);
        $sql_content = preg_replace('/\/\*.*?\*\//s', '', $sql_content);
        
        // Split by semicolon (basic approach - could be enhanced)
        $statements = explode(';', $sql_content);
        
        return array_filter($statements, function($statement) {
            return !empty(trim($statement));
        });
    }
    
    /**
     * Create database backup
     *
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    private function create_backup() {
        $upload_dir = wp_upload_dir();
        $backup_dir = $upload_dir['basedir'] . '/wp-migrate-backups/';
        
        if (!wp_mkdir_p($backup_dir)) {
            return new WP_Error('backup_error', __('Cannot create backup directory', 'wp-migrate'));
        }
        
        $backup_file = $backup_dir . 'backup-' . date('Y-m-d-H-i-s') . '.sql';
        
        return $this->export_database($backup_file);
    }
    
    /**
     * Clear WordPress caches
     */
    private function clear_caches() {
        // Clear object cache
        wp_cache_flush();
        
        // Clear rewrite rules
        flush_rewrite_rules();
        
        // Clear any additional caches if available
        if (function_exists('wp_cache_clear_cache')) {
            wp_cache_clear_cache();
        }
        
        if (function_exists('w3tc_flush_all')) {
            w3tc_flush_all();
        }
        
        if (function_exists('wp_rocket_clean_domain')) {
            wp_rocket_clean_domain();
        }
    }
    
    /**
     * Get database size
     *
     * @return int Database size in bytes
     */
    public function get_database_size() {
        $size = 0;
        $tables = $this->get_tables_to_export();
        
        foreach ($tables as $table) {
            $result = $this->wpdb->get_row(
                $this->wpdb->prepare(
                    "SELECT 
                        data_length + index_length AS size 
                    FROM information_schema.TABLES 
                    WHERE table_schema = %s 
                    AND table_name = %s",
                    DB_NAME,
                    $table
                )
            );
            
            if ($result) {
                $size += $result->size;
            }
        }
        
        return $size;
    }
    
    /**
     * Validate database connection
     *
     * @param array $connection_params Connection parameters
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function validate_connection($connection_params) {
        try {
            $test_db = new wpdb(
                $connection_params['username'],
                $connection_params['password'],
                $connection_params['database'],
                $connection_params['host']
            );
            
            if (!empty($test_db->last_error)) {
                return new WP_Error('connection_error', $test_db->last_error);
            }
            
            return true;
            
        } catch (Exception $e) {
            return new WP_Error('connection_error', $e->getMessage());
        }
    }
}