<?php

/**
 * WP Migrate Database Migration Core
 * Handles database export, import, and migration operations
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Migrate_Database {
    
    private $wpdb;
    private $serialization_handler;
    private $chunk_size;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->serialization_handler = new WP_Migrate_Serialization();
        $this->chunk_size = get_option('wp_migrate_chunk_size', 1000);
    }
    
    /**
     * Export database to SQL file
     */
    public function exportDatabase($options = array()) {
        $default_options = array(
            'tables' => array(),
            'exclude_tables' => array(),
            'find_replace' => array(),
            'backup_file' => '',
            'include_data' => true,
            'include_structure' => true,
        );
        
        $options = wp_parse_args($options, $default_options);
        
        try {
            // Create backup file if not specified
            if (empty($options['backup_file'])) {
                $upload_dir = wp_upload_dir();
                $backup_dir = $upload_dir['basedir'] . '/wp-migrate/exports/';
                wp_mkdir_p($backup_dir);
                $options['backup_file'] = $backup_dir . 'export_' . date('Y-m-d_H-i-s') . '.sql';
            }
            
            // Get tables to export
            $tables = $this->getTablesToExport($options['tables'], $options['exclude_tables']);
            
            // Start export
            $this->logMessage('Starting database export...', 'info');
            
            $sql_content = '';
            
            // Add MySQL settings
            $sql_content .= $this->getMySQLHeader();
            
            foreach ($tables as $table) {
                $this->logMessage("Exporting table: {$table}", 'info');
                
                // Export table structure
                if ($options['include_structure']) {
                    $sql_content .= $this->exportTableStructure($table);
                }
                
                // Export table data
                if ($options['include_data']) {
                    $sql_content .= $this->exportTableData($table, $options['find_replace']);
                }
            }
            
            // Add MySQL footer
            $sql_content .= $this->getMySQLFooter();
            
            // Write to file
            if (file_put_contents($options['backup_file'], $sql_content) !== false) {
                $this->logMessage("Database export completed: {$options['backup_file']}", 'success');
                return array(
                    'success' => true,
                    'file' => $options['backup_file'],
                    'size' => filesize($options['backup_file']),
                    'tables' => count($tables)
                );
            } else {
                throw new Exception('Failed to write export file');
            }
            
        } catch (Exception $e) {
            $this->logMessage('Database export failed: ' . $e->getMessage(), 'error');
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Import database from SQL file
     */
    public function importDatabase($sql_file, $options = array()) {
        $default_options = array(
            'find_replace' => array(),
            'backup_current' => true,
            'drop_existing' => false,
        );
        
        $options = wp_parse_args($options, $default_options);
        
        try {
            if (!file_exists($sql_file)) {
                throw new Exception('SQL file not found: ' . $sql_file);
            }
            
            // Create backup of current database before import
            if ($options['backup_current']) {
                $this->logMessage('Creating backup before import...', 'info');
                $backup_result = $this->exportDatabase();
                if (!$backup_result['success']) {
                    throw new Exception('Failed to create backup before import');
                }
            }
            
            $this->logMessage('Starting database import...', 'info');
            
            // Read and process SQL file
            $sql_content = file_get_contents($sql_file);
            
            // Apply find/replace operations including serialized data
            if (!empty($options['find_replace'])) {
                $sql_content = $this->serialization_handler->processSerializedData($sql_content, $options['find_replace']);
            }
            
            // Execute SQL in chunks
            $this->executeSQLFile($sql_content);
            
            $this->logMessage('Database import completed successfully', 'success');
            
            return array(
                'success' => true,
                'backup_file' => isset($backup_result) ? $backup_result['file'] : null
            );
            
        } catch (Exception $e) {
            $this->logMessage('Database import failed: ' . $e->getMessage(), 'error');
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Push database to remote site
     */
    public function pushDatabase($connection_id, $options = array()) {
        try {
            // Get connection details
            $connection = $this->getConnection($connection_id);
            if (!$connection) {
                throw new Exception('Connection not found');
            }
            
            // Export current database
            $this->logMessage('Exporting current database for push...', 'info');
            $export_result = $this->exportDatabase($options);
            
            if (!$export_result['success']) {
                throw new Exception('Failed to export database: ' . $export_result['error']);
            }
            
            // Send to remote site via API
            $this->logMessage('Pushing database to remote site...', 'info');
            $response = $this->sendDatabaseToRemote($connection, $export_result['file'], $options);
            
            if ($response['success']) {
                $this->logMessage('Database push completed successfully', 'success');
                // Clean up local export file
                unlink($export_result['file']);
            }
            
            return $response;
            
        } catch (Exception $e) {
            $this->logMessage('Database push failed: ' . $e->getMessage(), 'error');
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Pull database from remote site
     */
    public function pullDatabase($connection_id, $options = array()) {
        try {
            // Get connection details
            $connection = $this->getConnection($connection_id);
            if (!$connection) {
                throw new Exception('Connection not found');
            }
            
            // Request database export from remote site
            $this->logMessage('Requesting database from remote site...', 'info');
            $response = $this->requestDatabaseFromRemote($connection, $options);
            
            if (!$response['success']) {
                throw new Exception('Failed to get database from remote: ' . $response['error']);
            }
            
            // Download the database file
            $this->logMessage('Downloading database file...', 'info');
            $local_file = $this->downloadDatabaseFile($connection, $response['file_url']);
            
            // Import the database
            $this->logMessage('Importing downloaded database...', 'info');
            $import_result = $this->importDatabase($local_file, $options);
            
            // Clean up downloaded file
            unlink($local_file);
            
            return $import_result;
            
        } catch (Exception $e) {
            $this->logMessage('Database pull failed: ' . $e->getMessage(), 'error');
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Get tables to export
     */
    private function getTablesToExport($include_tables = array(), $exclude_tables = array()) {
        // Get all WordPress tables
        $tables = $this->wpdb->get_col("SHOW TABLES");
        
        // Filter by included tables if specified
        if (!empty($include_tables)) {
            $tables = array_intersect($tables, $include_tables);
        }
        
        // Remove excluded tables
        if (!empty($exclude_tables)) {
            $tables = array_diff($tables, $exclude_tables);
        }
        
        return $tables;
    }
    
    /**
     * Export table structure
     */
    private function exportTableStructure($table) {
        $sql = "\n--\n-- Table structure for table `{$table}`\n--\n\n";
        $sql .= "DROP TABLE IF EXISTS `{$table}`;\n";
        
        $create_table = $this->wpdb->get_row("SHOW CREATE TABLE `{$table}`", ARRAY_N);
        $sql .= $create_table[1] . ";\n\n";
        
        return $sql;
    }
    
    /**
     * Export table data with find/replace
     */
    private function exportTableData($table, $find_replace = array()) {
        $sql = "--\n-- Dumping data for table `{$table}`\n--\n\n";
        
        // Get total rows for chunking
        $total_rows = $this->wpdb->get_var("SELECT COUNT(*) FROM `{$table}`");
        
        if ($total_rows == 0) {
            return $sql;
        }
        
        $offset = 0;
        
        while ($offset < $total_rows) {
            $rows = $this->wpdb->get_results(
                $this->wpdb->prepare(
                    "SELECT * FROM `{$table}` LIMIT %d OFFSET %d",
                    $this->chunk_size,
                    $offset
                ),
                ARRAY_A
            );
            
            if (empty($rows)) {
                break;
            }
            
            $sql .= "INSERT INTO `{$table}` VALUES\n";
            $insert_values = array();
            
            foreach ($rows as $row) {
                $values = array();
                foreach ($row as $key => $value) {
                    if (is_null($value)) {
                        $values[] = 'NULL';
                    } else {
                        // Apply find/replace including serialized data
                        if (!empty($find_replace)) {
                            $value = $this->serialization_handler->processCellValue($value, $find_replace);
                        }
                        $values[] = "'" . $this->wpdb->_escape($value) . "'";
                    }
                }
                $insert_values[] = '(' . implode(',', $values) . ')';
            }
            
            $sql .= implode(",\n", $insert_values) . ";\n\n";
            $offset += $this->chunk_size;
        }
        
        return $sql;
    }
    
    /**
     * Get MySQL header
     */
    private function getMySQLHeader() {
        $header = "-- WP Migrate Database Export\n";
        $header .= "-- Generated on: " . date('Y-m-d H:i:s') . "\n";
        $header .= "-- WordPress Version: " . get_bloginfo('version') . "\n\n";
        $header .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
        $header .= "SET AUTOCOMMIT = 0;\n";
        $header .= "START TRANSACTION;\n";
        $header .= "SET time_zone = \"+00:00\";\n\n";
        
        return $header;
    }
    
    /**
     * Get MySQL footer
     */
    private function getMySQLFooter() {
        return "\nCOMMIT;\n";
    }
    
    /**
     * Execute SQL file content
     */
    private function executeSQLFile($sql_content) {
        // Split SQL into individual statements
        $statements = $this->splitSQLStatements($sql_content);
        
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (empty($statement) || strpos($statement, '--') === 0) {
                continue;
            }
            
            $result = $this->wpdb->query($statement);
            if ($result === false) {
                throw new Exception('SQL execution failed: ' . $this->wpdb->last_error);
            }
        }
    }
    
    /**
     * Split SQL content into individual statements
     */
    private function splitSQLStatements($sql) {
        $statements = array();
        $lines = explode("\n", $sql);
        $current_statement = '';
        $in_string = false;
        $string_char = '';
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Skip comments and empty lines
            if (empty($line) || strpos($line, '--') === 0) {
                continue;
            }
            
            $current_statement .= $line . "\n";
            
            // Check for end of statement
            if (substr($line, -1) === ';' && !$in_string) {
                $statements[] = $current_statement;
                $current_statement = '';
            }
        }
        
        // Add remaining statement if not empty
        if (!empty(trim($current_statement))) {
            $statements[] = $current_statement;
        }
        
        return $statements;
    }
    
    /**
     * Get connection details
     */
    private function getConnection($connection_id) {
        global $wpdb;
        
        $connection = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}wp_migrate_connections WHERE id = %d AND is_active = 1",
                $connection_id
            )
        );
        
        return $connection;
    }
    
    /**
     * Send database to remote site
     */
    private function sendDatabaseToRemote($connection, $file_path, $options) {
        // Implementation for sending database to remote site via API
        // This would use WordPress HTTP API to send the file
        
        $api_url = rtrim($connection->url, '/') . '/wp-json/wp-migrate/v1/import';
        
        $args = array(
            'method' => 'POST',
            'timeout' => 600,
            'headers' => array(
                'Authorization' => 'Bearer ' . $connection->api_key,
                'Content-Type' => 'multipart/form-data'
            ),
            'body' => array(
                'options' => json_encode($options),
                'file' => new CurlFile($file_path, 'application/sql', 'database.sql')
            )
        );
        
        $response = wp_remote_post($api_url, $args);
        
        if (is_wp_error($response)) {
            throw new Exception('HTTP request failed: ' . $response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        return $data;
    }
    
    /**
     * Request database from remote site
     */
    private function requestDatabaseFromRemote($connection, $options) {
        $api_url = rtrim($connection->url, '/') . '/wp-json/wp-migrate/v1/export';
        
        $args = array(
            'method' => 'POST',
            'timeout' => 600,
            'headers' => array(
                'Authorization' => 'Bearer ' . $connection->api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($options)
        );
        
        $response = wp_remote_post($api_url, $args);
        
        if (is_wp_error($response)) {
            throw new Exception('HTTP request failed: ' . $response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        return $data;
    }
    
    /**
     * Download database file from remote site
     */
    private function downloadDatabaseFile($connection, $file_url) {
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/wp-migrate/temp/';
        wp_mkdir_p($temp_dir);
        
        $local_file = $temp_dir . 'pulled_' . date('Y-m-d_H-i-s') . '.sql';
        
        $args = array(
            'timeout' => 600,
            'headers' => array(
                'Authorization' => 'Bearer ' . $connection->api_key
            )
        );
        
        $response = wp_remote_get($file_url, $args);
        
        if (is_wp_error($response)) {
            throw new Exception('Download failed: ' . $response->get_error_message());
        }
        
        $content = wp_remote_retrieve_body($response);
        
        if (file_put_contents($local_file, $content) === false) {
            throw new Exception('Failed to save downloaded file');
        }
        
        return $local_file;
    }
    
    /**
     * Log message
     */
    private function logMessage($message, $level = 'info') {
        if (get_option('wp_migrate_enable_logging', true)) {
            $log_level = get_option('wp_migrate_log_level', 'info');
            
            $levels = array('error' => 0, 'warning' => 1, 'info' => 2, 'debug' => 3);
            
            if ($levels[$level] <= $levels[$log_level]) {
                error_log('[WP Migrate] ' . strtoupper($level) . ': ' . $message);
            }
        }
    }
}