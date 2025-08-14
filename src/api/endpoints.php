<?php

/**
 * WP Migrate REST API Endpoints
 * Handles communication between WordPress sites
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Migrate_API_Endpoints {
    
    public function __construct() {
        add_action('rest_api_init', array($this, 'registerRoutes'));
    }
    
    /**
     * Register REST API routes
     */
    public function registerRoutes() {
        $namespace = 'wp-migrate/v1';
        
        // Test connection endpoint
        register_rest_route($namespace, '/test', array(
            'methods' => 'GET',
            'callback' => array($this, 'testConnection'),
            'permission_callback' => array($this, 'checkPermissions')
        ));
        
        // Database export endpoint
        register_rest_route($namespace, '/export', array(
            'methods' => 'POST',
            'callback' => array($this, 'exportDatabase'),
            'permission_callback' => array($this, 'checkPermissions')
        ));
        
        // Database import endpoint
        register_rest_route($namespace, '/import', array(
            'methods' => 'POST',
            'callback' => array($this, 'importDatabase'),
            'permission_callback' => array($this, 'checkPermissions')
        ));
        
        // Media list endpoint
        register_rest_route($namespace, '/media/list', array(
            'methods' => 'POST',
            'callback' => array($this, 'getMediaList'),
            'permission_callback' => array($this, 'checkPermissions')
        ));
        
        // Media upload endpoint
        register_rest_route($namespace, '/media/upload', array(
            'methods' => 'POST',
            'callback' => array($this, 'uploadMedia'),
            'permission_callback' => array($this, 'checkPermissions')
        ));
        
        // Media exists check endpoint
        register_rest_route($namespace, '/media/exists', array(
            'methods' => 'POST',
            'callback' => array($this, 'checkMediaExists'),
            'permission_callback' => array($this, 'checkPermissions')
        ));
        
        // Theme list endpoint
        register_rest_route($namespace, '/themes/list', array(
            'methods' => 'GET',
            'callback' => array($this, 'getThemeList'),
            'permission_callback' => array($this, 'checkPermissions')
        ));
        
        // Theme download endpoint
        register_rest_route($namespace, '/themes/download/(?P<slug>[a-zA-Z0-9_-]+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'downloadTheme'),
            'permission_callback' => array($this, 'checkPermissions')
        ));
        
        // Plugin list endpoint
        register_rest_route($namespace, '/plugins/list', array(
            'methods' => 'GET',
            'callback' => array($this, 'getPluginList'),
            'permission_callback' => array($this, 'checkPermissions')
        ));
        
        // Plugin download endpoint
        register_rest_route($namespace, '/plugins/download/(?P<slug>[a-zA-Z0-9_-]+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'downloadPlugin'),
            'permission_callback' => array($this, 'checkPermissions')
        ));
        
        // Site info endpoint
        register_rest_route($namespace, '/info', array(
            'methods' => 'GET',
            'callback' => array($this, 'getSiteInfo'),
            'permission_callback' => array($this, 'checkPermissions')
        ));
    }
    
    /**
     * Check API permissions
     */
    public function checkPermissions($request) {
        $auth = new WP_Migrate_Authentication();
        return $auth->authenticateRequest($request);
    }
    
    /**
     * Test connection endpoint
     */
    public function testConnection($request) {
        return rest_ensure_response(array(
            'success' => true,
            'message' => 'Connection successful',
            'info' => array(
                'site_url' => home_url(),
                'wp_version' => get_bloginfo('version'),
                'php_version' => PHP_VERSION,
                'plugin_version' => WP_MIGRATE_VERSION,
                'timestamp' => current_time('mysql')
            )
        ));
    }
    
    /**
     * Export database endpoint
     */
    public function exportDatabase($request) {
        try {
            $params = $request->get_json_params();
            
            $database = new WP_Migrate_Database();
            $result = $database->exportDatabase($params);
            
            if ($result['success']) {
                // Return file URL for download
                $upload_dir = wp_upload_dir();
                $file_url = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $result['file']);
                
                return rest_ensure_response(array(
                    'success' => true,
                    'file_url' => $file_url,
                    'file_size' => $result['size'],
                    'tables' => $result['tables']
                ));
            } else {
                return new WP_Error('export_failed', $result['error'], array('status' => 500));
            }
            
        } catch (Exception $e) {
            return new WP_Error('export_error', $e->getMessage(), array('status' => 500));
        }
    }
    
    /**
     * Import database endpoint
     */
    public function importDatabase($request) {
        try {
            $files = $request->get_file_params();
            $params = $request->get_params();
            
            if (empty($files['file'])) {
                return new WP_Error('no_file', 'No SQL file provided', array('status' => 400));
            }
            
            $uploaded_file = $files['file'];
            
            // Move uploaded file to temp location
            $temp_file = wp_tempnam('wp_migrate_import_');
            move_uploaded_file($uploaded_file['tmp_name'], $temp_file);
            
            $options = isset($params['options']) ? json_decode($params['options'], true) : array();
            
            $database = new WP_Migrate_Database();
            $result = $database->importDatabase($temp_file, $options);
            
            // Clean up temp file
            unlink($temp_file);
            
            if ($result['success']) {
                return rest_ensure_response(array(
                    'success' => true,
                    'message' => 'Database import completed successfully'
                ));
            } else {
                return new WP_Error('import_failed', $result['error'], array('status' => 500));
            }
            
        } catch (Exception $e) {
            return new WP_Error('import_error', $e->getMessage(), array('status' => 500));
        }
    }
    
    /**
     * Get media list endpoint
     */
    public function getMediaList($request) {
        try {
            $params = $request->get_json_params();
            
            $media = new WP_Migrate_Media();
            $files = $media->getLocalMediaList($params);
            
            return rest_ensure_response(array(
                'success' => true,
                'files' => $files,
                'count' => count($files)
            ));
            
        } catch (Exception $e) {
            return new WP_Error('media_list_error', $e->getMessage(), array('status' => 500));
        }
    }
    
    /**
     * Upload media endpoint
     */
    public function uploadMedia($request) {
        try {
            $files = $request->get_file_params();
            $params = $request->get_params();
            
            if (empty($files['file'])) {
                return new WP_Error('no_file', 'No file provided', array('status' => 400));
            }
            
            $uploaded_file = $files['file'];
            $metadata = isset($params['metadata']) ? json_decode($params['metadata'], true) : array();
            
            // Handle file upload
            $upload_dir = wp_upload_dir();
            $relative_path = isset($metadata['relative_path']) ? $metadata['relative_path'] : basename($uploaded_file['name']);
            $target_path = $upload_dir['basedir'] . '/' . $relative_path;
            
            // Create directory if needed
            $target_dir = dirname($target_path);
            if (!is_dir($target_dir)) {
                wp_mkdir_p($target_dir);
            }
            
            // Move uploaded file
            if (!move_uploaded_file($uploaded_file['tmp_name'], $target_path)) {
                return new WP_Error('upload_failed', 'Failed to save uploaded file', array('status' => 500));
            }
            
            // Add to media library
            $media = new WP_Migrate_Media();
            $attachment_id = $media->addToMediaLibrary($target_path, $metadata);
            
            return rest_ensure_response(array(
                'success' => true,
                'attachment_id' => $attachment_id,
                'file_path' => $target_path
            ));
            
        } catch (Exception $e) {
            return new WP_Error('upload_error', $e->getMessage(), array('status' => 500));
        }
    }
    
    /**
     * Check if media exists endpoint
     */
    public function checkMediaExists($request) {
        try {
            $params = $request->get_json_params();
            
            if (empty($params['relative_path'])) {
                return new WP_Error('missing_path', 'Relative path is required', array('status' => 400));
            }
            
            $upload_dir = wp_upload_dir();
            $file_path = $upload_dir['basedir'] . '/' . $params['relative_path'];
            
            $exists = file_exists($file_path);
            $same_checksum = false;
            
            if ($exists && isset($params['checksum'])) {
                $local_checksum = md5_file($file_path);
                $same_checksum = ($local_checksum === $params['checksum']);
            }
            
            return rest_ensure_response(array(
                'success' => true,
                'exists' => $exists,
                'same_checksum' => $same_checksum
            ));
            
        } catch (Exception $e) {
            return new WP_Error('check_error', $e->getMessage(), array('status' => 500));
        }
    }
    
    /**
     * Get theme list endpoint
     */
    public function getThemeList($request) {
        try {
            $files = new WP_Migrate_Files();
            $themes = $files->getLocalThemeList();
            
            return rest_ensure_response(array(
                'success' => true,
                'themes' => $themes,
                'count' => count($themes)
            ));
            
        } catch (Exception $e) {
            return new WP_Error('theme_list_error', $e->getMessage(), array('status' => 500));
        }
    }
    
    /**
     * Download theme endpoint
     */
    public function downloadTheme($request) {
        try {
            $slug = $request['slug'];
            
            $theme = wp_get_theme($slug);
            if (!$theme->exists()) {
                return new WP_Error('theme_not_found', 'Theme not found', array('status' => 404));
            }
            
            $theme_dir = $theme->get_stylesheet_directory();
            $temp_file = $this->createArchive($theme_dir, 'theme_' . $slug);
            
            if (!$temp_file) {
                return new WP_Error('archive_failed', 'Failed to create theme archive', array('status' => 500));
            }
            
            // Stream file for download
            $this->streamFile($temp_file, $slug . '.zip');
            
        } catch (Exception $e) {
            return new WP_Error('download_error', $e->getMessage(), array('status' => 500));
        }
    }
    
    /**
     * Get plugin list endpoint
     */
    public function getPluginList($request) {
        try {
            $files = new WP_Migrate_Files();
            $plugins = $files->getLocalPluginList();
            
            return rest_ensure_response(array(
                'success' => true,
                'plugins' => $plugins,
                'count' => count($plugins)
            ));
            
        } catch (Exception $e) {
            return new WP_Error('plugin_list_error', $e->getMessage(), array('status' => 500));
        }
    }
    
    /**
     * Download plugin endpoint
     */
    public function downloadPlugin($request) {
        try {
            $slug = $request['slug'];
            
            $plugin_dir = WP_PLUGIN_DIR . '/' . $slug;
            if (!is_dir($plugin_dir)) {
                return new WP_Error('plugin_not_found', 'Plugin not found', array('status' => 404));
            }
            
            $temp_file = $this->createArchive($plugin_dir, 'plugin_' . $slug);
            
            if (!$temp_file) {
                return new WP_Error('archive_failed', 'Failed to create plugin archive', array('status' => 500));
            }
            
            // Stream file for download
            $this->streamFile($temp_file, $slug . '.zip');
            
        } catch (Exception $e) {
            return new WP_Error('download_error', $e->getMessage(), array('status' => 500));
        }
    }
    
    /**
     * Get site info endpoint
     */
    public function getSiteInfo($request) {
        global $wpdb;
        
        // Get database size
        $db_size = $wpdb->get_var("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 1) FROM information_schema.tables WHERE table_schema = '{$wpdb->dbname}'");
        
        // Get media stats
        $media = new WP_Migrate_Media();
        $media_stats = $media->getMediaStats();
        
        // Get theme and plugin counts
        $themes = wp_get_themes();
        $plugins = get_plugins();
        
        return rest_ensure_response(array(
            'success' => true,
            'info' => array(
                'site_url' => home_url(),
                'admin_url' => admin_url(),
                'wp_version' => get_bloginfo('version'),
                'php_version' => PHP_VERSION,
                'mysql_version' => $wpdb->db_version(),
                'plugin_version' => WP_MIGRATE_VERSION,
                'multisite' => is_multisite(),
                'timezone' => get_option('timezone_string'),
                'language' => get_locale(),
                'active_theme' => wp_get_theme()->get('Name'),
                'database_size_mb' => floatval($db_size),
                'media_files' => $media_stats['total_files'],
                'media_size_bytes' => $media_stats['total_size'],
                'theme_count' => count($themes),
                'plugin_count' => count($plugins),
                'active_plugins' => count(get_option('active_plugins', array())),
                'timestamp' => current_time('mysql')
            )
        ));
    }
    
    /**
     * Create archive of directory
     */
    private function createArchive($directory, $prefix) {
        if (!is_dir($directory)) {
            return false;
        }
        
        $temp_file = wp_tempnam($prefix);
        $zip_file = $temp_file . '.zip';
        
        if (!class_exists('ZipArchive')) {
            return false;
        }
        
        $zip = new ZipArchive();
        if ($zip->open($zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
            return false;
        }
        
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        foreach ($files as $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($directory) + 1);
                $zip->addFile($filePath, $relativePath);
            }
        }
        
        $zip->close();
        
        // Clean up temp file without extension
        if (file_exists($temp_file)) {
            unlink($temp_file);
        }
        
        return file_exists($zip_file) ? $zip_file : false;
    }
    
    /**
     * Stream file for download
     */
    private function streamFile($file_path, $filename) {
        if (!file_exists($file_path)) {
            return;
        }
        
        // Set headers for file download
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($file_path));
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Stream the file
        $handle = fopen($file_path, 'rb');
        if ($handle) {
            while (!feof($handle)) {
                echo fread($handle, 8192);
                flush();
            }
            fclose($handle);
        }
        
        // Clean up temp file
        unlink($file_path);
        
        exit();
    }
}

// Initialize API endpoints
new WP_Migrate_API_Endpoints();