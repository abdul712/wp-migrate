<?php

/**
 * WP Migrate Backup Manager
 * Handles backup creation, storage, and restoration
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Migrate_Backup {
    
    private $wpdb;
    private $backup_dir;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->initBackupDirectory();
    }
    
    /**
     * Initialize backup directory
     */
    private function initBackupDirectory() {
        $upload_dir = wp_upload_dir();
        $this->backup_dir = $upload_dir['basedir'] . '/wp-migrate/backups/';
        wp_mkdir_p($this->backup_dir);
        
        // Create .htaccess to protect backup files
        $htaccess_file = $this->backup_dir . '.htaccess';
        if (!file_exists($htaccess_file)) {
            file_put_contents($htaccess_file, "Deny from all\n");
        }
    }
    
    /**
     * Create full site backup
     */
    public function createFullBackup($name = null, $description = null) {
        try {
            if (empty($name)) {
                $name = 'full_backup_' . date('Y-m-d_H-i-s');
            }
            
            $backup_path = $this->backup_dir . $name . '/';
            wp_mkdir_p($backup_path);
            
            $this->logMessage("Starting full backup: {$name}", 'info');
            
            // Create database backup
            $db_result = $this->createDatabaseBackup($backup_path . 'database.sql');
            if (!$db_result['success']) {
                throw new Exception('Database backup failed: ' . $db_result['error']);
            }
            
            // Create files backup
            $files_result = $this->createFilesBackup($backup_path . 'files.tar.gz');
            if (!$files_result['success']) {
                throw new Exception('Files backup failed: ' . $files_result['error']);
            }
            
            // Create backup info file
            $backup_info = array(
                'name' => $name,
                'description' => $description,
                'type' => 'full',
                'created_at' => current_time('mysql'),
                'wp_version' => get_bloginfo('version'),
                'site_url' => home_url(),
                'admin_email' => get_option('admin_email'),
                'database_size' => $db_result['size'],
                'files_size' => $files_result['size'],
                'total_size' => $db_result['size'] + $files_result['size']
            );
            
            file_put_contents($backup_path . 'backup.json', json_encode($backup_info, JSON_PRETTY_PRINT));
            
            // Record in database
            $backup_id = $this->recordBackup($name, $backup_path, 'full', $backup_info['total_size'], $description);
            
            $this->logMessage("Full backup completed: {$name}", 'success');
            
            return array(
                'success' => true,
                'backup_id' => $backup_id,
                'name' => $name,
                'path' => $backup_path,
                'size' => $backup_info['total_size']
            );
            
        } catch (Exception $e) {
            $this->logMessage('Full backup failed: ' . $e->getMessage(), 'error');
            
            // Cleanup failed backup
            if (isset($backup_path) && is_dir($backup_path)) {
                $this->deleteDirectory($backup_path);
            }
            
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Create database-only backup
     */
    public function createDatabaseBackup($file_path = null, $options = array()) {
        try {
            if (empty($file_path)) {
                $file_path = $this->backup_dir . 'database_' . date('Y-m-d_H-i-s') . '.sql';
            }
            
            $database = new WP_Migrate_Database();
            $result = $database->exportDatabase(array_merge($options, array(
                'backup_file' => $file_path
            )));
            
            if ($result['success']) {
                // Record in database if this is a standalone backup
                if (strpos($file_path, $this->backup_dir) === 0) {
                    $name = basename($file_path, '.sql');
                    $this->recordBackup($name, $file_path, 'database', $result['size']);
                }
            }
            
            return $result;
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Create files backup
     */
    public function createFilesBackup($file_path = null, $include_paths = array(), $exclude_paths = array()) {
        try {
            if (empty($file_path)) {
                $file_path = $this->backup_dir . 'files_' . date('Y-m-d_H-i-s') . '.tar.gz';
            }
            
            // Default paths to include
            if (empty($include_paths)) {
                $include_paths = array(
                    ABSPATH . 'wp-content/themes/',
                    ABSPATH . 'wp-content/plugins/',
                    ABSPATH . 'wp-content/uploads/',
                    ABSPATH . 'wp-config.php'
                );
            }
            
            // Default paths to exclude
            $default_exclude = array(
                ABSPATH . 'wp-content/cache/',
                ABSPATH . 'wp-content/backups/',
                $this->backup_dir
            );
            $exclude_paths = array_merge($default_exclude, $exclude_paths);
            
            $this->logMessage('Starting files backup...', 'info');
            
            // Use tar command if available
            if (function_exists('exec') && $this->commandExists('tar')) {
                $result = $this->createTarBackup($file_path, $include_paths, $exclude_paths);
            } else {
                $result = $this->createZipBackup($file_path, $include_paths, $exclude_paths);
            }
            
            if ($result['success']) {
                $this->logMessage('Files backup completed', 'success');
                
                // Record in database if this is a standalone backup
                if (strpos($file_path, $this->backup_dir) === 0) {
                    $name = basename($file_path);
                    $name = preg_replace('/\.(tar\.gz|zip)$/', '', $name);
                    $this->recordBackup($name, $file_path, 'files', $result['size']);
                }
            }
            
            return $result;
            
        } catch (Exception $e) {
            $this->logMessage('Files backup failed: ' . $e->getMessage(), 'error');
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Create tar backup
     */
    private function createTarBackup($file_path, $include_paths, $exclude_paths) {
        $temp_file = tempnam(sys_get_temp_dir(), 'wp_migrate_files_');
        
        // Create include list
        $include_list = '';
        foreach ($include_paths as $path) {
            if (file_exists($path)) {
                $include_list .= escapeshellarg($path) . ' ';
            }
        }
        
        // Create exclude options
        $exclude_options = '';
        foreach ($exclude_paths as $path) {
            $exclude_options .= '--exclude=' . escapeshellarg($path) . ' ';
        }
        
        $command = "tar {$exclude_options} -czf " . escapeshellarg($file_path) . " {$include_list}";
        
        exec($command, $output, $return_var);
        
        if ($return_var !== 0) {
            throw new Exception('Tar command failed with code: ' . $return_var);
        }
        
        if (!file_exists($file_path)) {
            throw new Exception('Backup file was not created');
        }
        
        return array(
            'success' => true,
            'size' => filesize($file_path),
            'method' => 'tar'
        );
    }
    
    /**
     * Create ZIP backup
     */
    private function createZipBackup($file_path, $include_paths, $exclude_paths) {
        if (!class_exists('ZipArchive')) {
            throw new Exception('ZipArchive class not available');
        }
        
        $zip = new ZipArchive();
        if ($zip->open($file_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
            throw new Exception('Cannot create ZIP file: ' . $file_path);
        }
        
        foreach ($include_paths as $path) {
            if (is_file($path)) {
                $zip->addFile($path, basename($path));
            } elseif (is_dir($path)) {
                $this->addDirectoryToZip($zip, $path, $exclude_paths);
            }
        }
        
        $zip->close();
        
        return array(
            'success' => true,
            'size' => filesize($file_path),
            'method' => 'zip'
        );
    }
    
    /**
     * Add directory to ZIP recursively
     */
    private function addDirectoryToZip($zip, $dir_path, $exclude_paths, $base_path = null) {
        if ($base_path === null) {
            $base_path = dirname($dir_path);
        }
        
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir_path),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        foreach ($files as $file) {
            if (!$file->isFile()) {
                continue;
            }
            
            $file_path = $file->getRealPath();
            
            // Check if file should be excluded
            $should_exclude = false;
            foreach ($exclude_paths as $exclude_path) {
                if (strpos($file_path, $exclude_path) === 0) {
                    $should_exclude = true;
                    break;
                }
            }
            
            if (!$should_exclude) {
                $relative_path = substr($file_path, strlen($base_path) + 1);
                $zip->addFile($file_path, $relative_path);
            }
        }
    }
    
    /**
     * Restore from backup
     */
    public function restoreFromBackup($backup_id, $options = array()) {
        try {
            $backup = $this->getBackup($backup_id);
            if (!$backup) {
                throw new Exception('Backup not found');
            }
            
            $default_options = array(
                'restore_database' => true,
                'restore_files' => true,
                'create_backup_before_restore' => true
            );
            $options = wp_parse_args($options, $default_options);
            
            $this->logMessage("Starting restore from backup: {$backup->name}", 'info');
            
            // Create backup before restore
            if ($options['create_backup_before_restore']) {
                $pre_restore_backup = $this->createFullBackup('pre_restore_' . date('Y-m-d_H-i-s'), 'Automatic backup before restore');
                if (!$pre_restore_backup['success']) {
                    throw new Exception('Failed to create backup before restore');
                }
            }
            
            $results = array(
                'database_restored' => false,
                'files_restored' => false
            );
            
            // Restore database
            if ($options['restore_database'] && ($backup->backup_type === 'full' || $backup->backup_type === 'database')) {
                $db_file = $backup->backup_type === 'full' ? $backup->file_path . 'database.sql' : $backup->file_path;
                
                if (file_exists($db_file)) {
                    $database = new WP_Migrate_Database();
                    $db_result = $database->importDatabase($db_file);
                    
                    if (!$db_result['success']) {
                        throw new Exception('Database restore failed: ' . $db_result['error']);
                    }
                    
                    $results['database_restored'] = true;
                }
            }
            
            // Restore files
            if ($options['restore_files'] && ($backup->backup_type === 'full' || $backup->backup_type === 'files')) {
                $files_file = $backup->backup_type === 'full' ? $backup->file_path . 'files.tar.gz' : $backup->file_path;
                
                if (file_exists($files_file)) {
                    $files_result = $this->restoreFiles($files_file);
                    
                    if (!$files_result['success']) {
                        throw new Exception('Files restore failed: ' . $files_result['error']);
                    }
                    
                    $results['files_restored'] = true;
                }
            }
            
            $this->logMessage("Restore completed successfully", 'success');
            
            return array(
                'success' => true,
                'results' => $results,
                'pre_restore_backup' => isset($pre_restore_backup) ? $pre_restore_backup : null
            );
            
        } catch (Exception $e) {
            $this->logMessage('Restore failed: ' . $e->getMessage(), 'error');
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Restore files from backup
     */
    private function restoreFiles($files_backup_path) {
        try {
            $extract_dir = ABSPATH;
            
            // Determine file type and extract
            if (preg_match('/\.tar\.gz$/', $files_backup_path)) {
                if (!$this->commandExists('tar')) {
                    throw new Exception('Tar command not available for extraction');
                }
                
                $command = 'tar -xzf ' . escapeshellarg($files_backup_path) . ' -C ' . escapeshellarg($extract_dir);
                exec($command, $output, $return_var);
                
                if ($return_var !== 0) {
                    throw new Exception('Tar extraction failed with code: ' . $return_var);
                }
            } elseif (preg_match('/\.zip$/', $files_backup_path)) {
                if (!class_exists('ZipArchive')) {
                    throw new Exception('ZipArchive class not available for extraction');
                }
                
                $zip = new ZipArchive();
                if ($zip->open($files_backup_path) !== TRUE) {
                    throw new Exception('Cannot open ZIP file for extraction');
                }
                
                if (!$zip->extractTo($extract_dir)) {
                    throw new Exception('ZIP extraction failed');
                }
                
                $zip->close();
            } else {
                throw new Exception('Unsupported backup file format');
            }
            
            return array('success' => true);
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Delete backup
     */
    public function deleteBackup($backup_id) {
        try {
            $backup = $this->getBackup($backup_id);
            if (!$backup) {
                throw new Exception('Backup not found');
            }
            
            // Delete files
            if (is_dir($backup->file_path)) {
                $this->deleteDirectory($backup->file_path);
            } elseif (is_file($backup->file_path)) {
                unlink($backup->file_path);
            }
            
            // Remove from database
            $this->wpdb->delete(
                $this->wpdb->prefix . 'wp_migrate_backups',
                array('id' => $backup_id),
                array('%d')
            );
            
            return array('success' => true);
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Get backup by ID
     */
    public function getBackup($backup_id) {
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->wpdb->prefix}wp_migrate_backups WHERE id = %d",
                $backup_id
            )
        );
    }
    
    /**
     * Get all backups
     */
    public function getBackups($limit = 50) {
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->wpdb->prefix}wp_migrate_backups 
                ORDER BY created_at DESC LIMIT %d",
                $limit
            )
        );
    }
    
    /**
     * Clean up expired backups
     */
    public function cleanupExpiredBackups() {
        $retention_days = get_option('wp_migrate_backup_retention', 30);
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$retention_days} days"));
        
        $expired_backups = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->wpdb->prefix}wp_migrate_backups 
                WHERE (expires_at IS NOT NULL AND expires_at < %s) 
                OR (expires_at IS NULL AND created_at < %s)",
                current_time('mysql'),
                $cutoff_date
            )
        );
        
        $cleaned = 0;
        foreach ($expired_backups as $backup) {
            $result = $this->deleteBackup($backup->id);
            if ($result['success']) {
                $cleaned++;
            }
        }
        
        return $cleaned;
    }
    
    /**
     * Record backup in database
     */
    private function recordBackup($name, $file_path, $type, $file_size, $description = null) {
        $result = $this->wpdb->insert(
            $this->wpdb->prefix . 'wp_migrate_backups',
            array(
                'name' => $name,
                'file_path' => $file_path,
                'backup_type' => $type,
                'file_size' => $file_size,
                'created_at' => current_time('mysql'),
                'description' => $description
            ),
            array('%s', '%s', '%s', '%d', '%s', '%s')
        );
        
        return $result ? $this->wpdb->insert_id : false;
    }
    
    /**
     * Check if command exists
     */
    private function commandExists($command) {
        $return = null;
        exec("which {$command}", $output, $return);
        return $return === 0;
    }
    
    /**
     * Delete directory recursively
     */
    private function deleteDirectory($dir) {
        if (!is_dir($dir)) {
            return false;
        }
        
        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        
        return rmdir($dir);
    }
    
    /**
     * Log message
     */
    private function logMessage($message, $level = 'info') {
        if (get_option('wp_migrate_enable_logging', true)) {
            error_log('[WP Migrate Backup] ' . strtoupper($level) . ': ' . $message);
        }
    }
}