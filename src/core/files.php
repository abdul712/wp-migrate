<?php

/**
 * WP Migrate File Transfer Manager
 * Handles theme, plugin, and custom file transfers
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Migrate_Files {
    
    private $connections;
    
    public function __construct() {
        $this->connections = new WP_Migrate_Connections();
    }
    
    /**
     * Transfer themes between sites
     */
    public function transferThemes($connection_id, $options = array()) {
        $default_options = array(
            'direction' => 'pull', // 'pull' or 'push'
            'themes' => array(), // Empty means all themes
            'exclude_themes' => array(),
            'overwrite_existing' => false,
            'activate_theme' => false,
            'backup_before_transfer' => true
        );
        
        $options = wp_parse_args($options, $default_options);
        
        try {
            $connection = $this->connections->getConnection($connection_id);
            if (!$connection) {
                throw new Exception('Connection not found');
            }
            
            $this->logMessage("Starting theme transfer with {$connection->name}", 'info');
            
            if ($options['direction'] === 'pull') {
                $result = $this->pullThemesFromRemote($connection, $options);
            } else {
                $result = $this->pushThemesToRemote($connection, $options);
            }
            
            $this->logMessage("Theme transfer completed", 'success');
            return $result;
            
        } catch (Exception $e) {
            $this->logMessage('Theme transfer failed: ' . $e->getMessage(), 'error');
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Transfer plugins between sites
     */
    public function transferPlugins($connection_id, $options = array()) {
        $default_options = array(
            'direction' => 'pull', // 'pull' or 'push'
            'plugins' => array(), // Empty means all plugins
            'exclude_plugins' => array(),
            'overwrite_existing' => false,
            'activate_plugins' => false,
            'backup_before_transfer' => true
        );
        
        $options = wp_parse_args($options, $default_options);
        
        try {
            $connection = $this->connections->getConnection($connection_id);
            if (!$connection) {
                throw new Exception('Connection not found');
            }
            
            $this->logMessage("Starting plugin transfer with {$connection->name}", 'info');
            
            if ($options['direction'] === 'pull') {
                $result = $this->pullPluginsFromRemote($connection, $options);
            } else {
                $result = $this->pushPluginsToRemote($connection, $options);
            }
            
            $this->logMessage("Plugin transfer completed", 'success');
            return $result;
            
        } catch (Exception $e) {
            $this->logMessage('Plugin transfer failed: ' . $e->getMessage(), 'error');
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Transfer custom files/directories
     */
    public function transferCustomFiles($connection_id, $file_paths, $options = array()) {
        $default_options = array(
            'direction' => 'pull',
            'overwrite_existing' => false,
            'create_backup' => true,
            'preserve_permissions' => true
        );
        
        $options = wp_parse_args($options, $default_options);
        
        try {
            $connection = $this->connections->getConnection($connection_id);
            if (!$connection) {
                throw new Exception('Connection not found');
            }
            
            $result = array(
                'success' => true,
                'transferred_files' => 0,
                'skipped_files' => 0,
                'errors' => array()
            );
            
            foreach ($file_paths as $file_path) {
                try {
                    if ($options['direction'] === 'pull') {
                        $transfer_result = $this->pullFileFromRemote($connection, $file_path, $options);
                    } else {
                        $transfer_result = $this->pushFileToRemote($connection, $file_path, $options);
                    }
                    
                    if ($transfer_result['success']) {
                        $result['transferred_files']++;
                    } else {
                        if ($transfer_result['skipped']) {
                            $result['skipped_files']++;
                        } else {
                            $result['errors'][] = $transfer_result['error'];
                        }
                    }
                    
                } catch (Exception $e) {
                    $result['errors'][] = $e->getMessage();
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
     * Pull themes from remote site
     */
    private function pullThemesFromRemote($connection, $options) {
        // Get remote theme list
        $remote_themes = $this->getRemoteThemeList($connection);
        
        if (!$remote_themes['success']) {
            throw new Exception('Failed to get remote theme list: ' . $remote_themes['error']);
        }
        
        $themes_to_transfer = $this->filterThemesToTransfer($remote_themes['themes'], $options);
        
        $result = array(
            'success' => true,
            'transferred_themes' => 0,
            'skipped_themes' => 0,
            'errors' => array(),
            'activated_theme' => null
        );
        
        foreach ($themes_to_transfer as $theme) {
            try {
                // Create backup if requested
                if ($options['backup_before_transfer']) {
                    $this->createThemeBackup($theme['slug']);
                }
                
                // Download and extract theme
                $download_result = $this->downloadAndExtractTheme($connection, $theme, $options);
                
                if ($download_result['success']) {
                    $result['transferred_themes']++;
                    
                    // Activate theme if requested
                    if ($options['activate_theme'] && $theme['slug'] === $options['activate_theme']) {
                        switch_theme($theme['slug']);
                        $result['activated_theme'] = $theme['slug'];
                    }
                    
                    $this->logMessage("Downloaded theme: {$theme['slug']}", 'debug');
                } else {
                    if ($download_result['skipped']) {
                        $result['skipped_themes']++;
                    } else {
                        $result['errors'][] = "Failed to download theme {$theme['slug']}: {$download_result['error']}";
                    }
                }
                
            } catch (Exception $e) {
                $result['errors'][] = $e->getMessage();
            }
        }
        
        return $result;
    }
    
    /**
     * Push themes to remote site
     */
    private function pushThemesToRemote($connection, $options) {
        $local_themes = $this->getLocalThemeList();
        $themes_to_transfer = $this->filterThemesToTransfer($local_themes, $options);
        
        $result = array(
            'success' => true,
            'transferred_themes' => 0,
            'skipped_themes' => 0,
            'errors' => array()
        );
        
        foreach ($themes_to_transfer as $theme) {
            try {
                $upload_result = $this->uploadThemeToRemote($connection, $theme, $options);
                
                if ($upload_result['success']) {
                    $result['transferred_themes']++;
                    $this->logMessage("Uploaded theme: {$theme['slug']}", 'debug');
                } else {
                    if ($upload_result['skipped']) {
                        $result['skipped_themes']++;
                    } else {
                        $result['errors'][] = "Failed to upload theme {$theme['slug']}: {$upload_result['error']}";
                    }
                }
                
            } catch (Exception $e) {
                $result['errors'][] = $e->getMessage();
            }
        }
        
        return $result;
    }
    
    /**
     * Pull plugins from remote site
     */
    private function pullPluginsFromRemote($connection, $options) {
        // Get remote plugin list
        $remote_plugins = $this->getRemotePluginList($connection);
        
        if (!$remote_plugins['success']) {
            throw new Exception('Failed to get remote plugin list: ' . $remote_plugins['error']);
        }
        
        $plugins_to_transfer = $this->filterPluginsToTransfer($remote_plugins['plugins'], $options);
        
        $result = array(
            'success' => true,
            'transferred_plugins' => 0,
            'skipped_plugins' => 0,
            'errors' => array(),
            'activated_plugins' => array()
        );
        
        foreach ($plugins_to_transfer as $plugin) {
            try {
                // Create backup if requested
                if ($options['backup_before_transfer']) {
                    $this->createPluginBackup($plugin['slug']);
                }
                
                // Download and extract plugin
                $download_result = $this->downloadAndExtractPlugin($connection, $plugin, $options);
                
                if ($download_result['success']) {
                    $result['transferred_plugins']++;
                    
                    // Activate plugin if requested
                    if ($options['activate_plugins'] && $plugin['was_active']) {
                        $activation_result = activate_plugin($plugin['main_file']);
                        if (!is_wp_error($activation_result)) {
                            $result['activated_plugins'][] = $plugin['slug'];
                        }
                    }
                    
                    $this->logMessage("Downloaded plugin: {$plugin['slug']}", 'debug');
                } else {
                    if ($download_result['skipped']) {
                        $result['skipped_plugins']++;
                    } else {
                        $result['errors'][] = "Failed to download plugin {$plugin['slug']}: {$download_result['error']}";
                    }
                }
                
            } catch (Exception $e) {
                $result['errors'][] = $e->getMessage();
            }
        }
        
        return $result;
    }
    
    /**
     * Push plugins to remote site
     */
    private function pushPluginsToRemote($connection, $options) {
        $local_plugins = $this->getLocalPluginList();
        $plugins_to_transfer = $this->filterPluginsToTransfer($local_plugins, $options);
        
        $result = array(
            'success' => true,
            'transferred_plugins' => 0,
            'skipped_plugins' => 0,
            'errors' => array()
        );
        
        foreach ($plugins_to_transfer as $plugin) {
            try {
                $upload_result = $this->uploadPluginToRemote($connection, $plugin, $options);
                
                if ($upload_result['success']) {
                    $result['transferred_plugins']++;
                    $this->logMessage("Uploaded plugin: {$plugin['slug']}", 'debug');
                } else {
                    if ($upload_result['skipped']) {
                        $result['skipped_plugins']++;
                    } else {
                        $result['errors'][] = "Failed to upload plugin {$plugin['slug']}: {$upload_result['error']}";
                    }
                }
                
            } catch (Exception $e) {
                $result['errors'][] = $e->getMessage();
            }
        }
        
        return $result;
    }
    
    /**
     * Get remote theme list
     */
    private function getRemoteThemeList($connection) {
        $api_url = rtrim($connection->url, '/') . '/wp-json/wp-migrate/v1/themes/list';
        
        $response = $this->makeRemoteRequest($connection, $api_url, 'GET');
        return json_decode(wp_remote_retrieve_body($response), true);
    }
    
    /**
     * Get remote plugin list
     */
    private function getRemotePluginList($connection) {
        $api_url = rtrim($connection->url, '/') . '/wp-json/wp-migrate/v1/plugins/list';
        
        $response = $this->makeRemoteRequest($connection, $api_url, 'GET');
        return json_decode(wp_remote_retrieve_body($response), true);
    }
    
    /**
     * Get local theme list
     */
    private function getLocalThemeList() {
        $themes = wp_get_themes();
        $theme_list = array();
        
        foreach ($themes as $slug => $theme) {
            $theme_list[] = array(
                'slug' => $slug,
                'name' => $theme->get('Name'),
                'version' => $theme->get('Version'),
                'description' => $theme->get('Description'),
                'author' => $theme->get('Author'),
                'path' => $theme->get_stylesheet_directory(),
                'is_active' => (get_stylesheet() === $slug),
                'checksum' => $this->calculateDirectoryChecksum($theme->get_stylesheet_directory())
            );
        }
        
        return $theme_list;
    }
    
    /**
     * Get local plugin list
     */
    private function getLocalPluginList() {
        $plugins = get_plugins();
        $active_plugins = get_option('active_plugins', array());
        $plugin_list = array();
        
        foreach ($plugins as $main_file => $plugin_data) {
            $slug = dirname($main_file);
            if ($slug === '.') {
                $slug = basename($main_file, '.php');
            }
            
            $plugin_list[] = array(
                'slug' => $slug,
                'main_file' => $main_file,
                'name' => $plugin_data['Name'],
                'version' => $plugin_data['Version'],
                'description' => $plugin_data['Description'],
                'author' => $plugin_data['Author'],
                'path' => WP_PLUGIN_DIR . '/' . dirname($main_file),
                'is_active' => in_array($main_file, $active_plugins),
                'was_active' => in_array($main_file, $active_plugins),
                'checksum' => $this->calculateDirectoryChecksum(WP_PLUGIN_DIR . '/' . dirname($main_file))
            );
        }
        
        return $plugin_list;
    }
    
    /**
     * Filter themes to transfer
     */
    private function filterThemesToTransfer($themes, $options) {
        $filtered = array();
        
        foreach ($themes as $theme) {
            // Skip if in exclude list
            if (in_array($theme['slug'], $options['exclude_themes'])) {
                continue;
            }
            
            // Include only specified themes if list provided
            if (!empty($options['themes']) && !in_array($theme['slug'], $options['themes'])) {
                continue;
            }
            
            $filtered[] = $theme;
        }
        
        return $filtered;
    }
    
    /**
     * Filter plugins to transfer
     */
    private function filterPluginsToTransfer($plugins, $options) {
        $filtered = array();
        
        foreach ($plugins as $plugin) {
            // Skip if in exclude list
            if (in_array($plugin['slug'], $options['exclude_plugins'])) {
                continue;
            }
            
            // Include only specified plugins if list provided
            if (!empty($options['plugins']) && !in_array($plugin['slug'], $options['plugins'])) {
                continue;
            }
            
            // Skip wp-migrate plugin itself
            if ($plugin['slug'] === 'wp-migrate') {
                continue;
            }
            
            $filtered[] = $plugin;
        }
        
        return $filtered;
    }
    
    /**
     * Download and extract theme
     */
    private function downloadAndExtractTheme($connection, $theme, $options) {
        try {
            $local_theme_dir = get_theme_root() . '/' . $theme['slug'];
            
            // Check if theme exists locally
            if (is_dir($local_theme_dir) && !$options['overwrite_existing']) {
                return array(
                    'success' => true,
                    'skipped' => true,
                    'message' => 'Theme already exists locally'
                );
            }
            
            // Download theme archive
            $api_url = rtrim($connection->url, '/') . '/wp-json/wp-migrate/v1/themes/download/' . $theme['slug'];
            $response = $this->makeRemoteRequest($connection, $api_url, 'GET');
            
            if (is_wp_error($response)) {
                throw new Exception($response->get_error_message());
            }
            
            $theme_zip = wp_remote_retrieve_body($response);
            
            // Save to temporary file
            $temp_file = tempnam(sys_get_temp_dir(), 'wp_migrate_theme_');
            file_put_contents($temp_file, $theme_zip);
            
            // Extract theme
            $extract_result = $this->extractArchive($temp_file, get_theme_root());
            
            // Clean up temporary file
            unlink($temp_file);
            
            if (!$extract_result['success']) {
                throw new Exception('Failed to extract theme: ' . $extract_result['error']);
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
     * Download and extract plugin
     */
    private function downloadAndExtractPlugin($connection, $plugin, $options) {
        try {
            $local_plugin_dir = WP_PLUGIN_DIR . '/' . $plugin['slug'];
            
            // Check if plugin exists locally
            if (is_dir($local_plugin_dir) && !$options['overwrite_existing']) {
                return array(
                    'success' => true,
                    'skipped' => true,
                    'message' => 'Plugin already exists locally'
                );
            }
            
            // Download plugin archive
            $api_url = rtrim($connection->url, '/') . '/wp-json/wp-migrate/v1/plugins/download/' . $plugin['slug'];
            $response = $this->makeRemoteRequest($connection, $api_url, 'GET');
            
            if (is_wp_error($response)) {
                throw new Exception($response->get_error_message());
            }
            
            $plugin_zip = wp_remote_retrieve_body($response);
            
            // Save to temporary file
            $temp_file = tempnam(sys_get_temp_dir(), 'wp_migrate_plugin_');
            file_put_contents($temp_file, $plugin_zip);
            
            // Extract plugin
            $extract_result = $this->extractArchive($temp_file, WP_PLUGIN_DIR);
            
            // Clean up temporary file
            unlink($temp_file);
            
            if (!$extract_result['success']) {
                throw new Exception('Failed to extract plugin: ' . $extract_result['error']);
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
     * Extract archive (ZIP or TAR)
     */
    private function extractArchive($archive_path, $destination) {
        try {
            $file_info = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($file_info, $archive_path);
            finfo_close($file_info);
            
            if (strpos($mime_type, 'zip') !== false) {
                return $this->extractZip($archive_path, $destination);
            } elseif (strpos($mime_type, 'gzip') !== false || strpos($mime_type, 'tar') !== false) {
                return $this->extractTar($archive_path, $destination);
            } else {
                throw new Exception('Unsupported archive format: ' . $mime_type);
            }
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Extract ZIP archive
     */
    private function extractZip($zip_path, $destination) {
        if (!class_exists('ZipArchive')) {
            return array(
                'success' => false,
                'error' => 'ZipArchive class not available'
            );
        }
        
        $zip = new ZipArchive();
        $result = $zip->open($zip_path);
        
        if ($result !== TRUE) {
            return array(
                'success' => false,
                'error' => 'Failed to open ZIP archive'
            );
        }
        
        $extracted = $zip->extractTo($destination);
        $zip->close();
        
        return array(
            'success' => $extracted,
            'error' => $extracted ? null : 'ZIP extraction failed'
        );
    }
    
    /**
     * Extract TAR archive
     */
    private function extractTar($tar_path, $destination) {
        if (!function_exists('exec')) {
            return array(
                'success' => false,
                'error' => 'Exec function not available'
            );
        }
        
        $command = 'tar -xf ' . escapeshellarg($tar_path) . ' -C ' . escapeshellarg($destination);
        exec($command, $output, $return_var);
        
        return array(
            'success' => $return_var === 0,
            'error' => $return_var === 0 ? null : 'TAR extraction failed'
        );
    }
    
    /**
     * Calculate directory checksum
     */
    private function calculateDirectoryChecksum($directory) {
        if (!is_dir($directory)) {
            return null;
        }
        
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        $hash_context = hash_init('md5');
        
        foreach ($files as $file) {
            if ($file->isFile()) {
                hash_update_file($hash_context, $file->getRealPath());
            }
        }
        
        return hash_final($hash_context);
    }
    
    /**
     * Make remote API request
     */
    private function makeRemoteRequest($connection, $url, $method = 'GET', $body = null) {
        $args = array(
            'method' => $method,
            'timeout' => 300,
            'headers' => array(
                'Authorization' => 'Bearer ' . $connection->api_key
            )
        );
        
        if ($body) {
            $args['body'] = $body;
        }
        
        return wp_remote_request($url, $args);
    }
    
    /**
     * Create theme backup
     */
    private function createThemeBackup($theme_slug) {
        $theme_dir = get_theme_root() . '/' . $theme_slug;
        if (!is_dir($theme_dir)) {
            return;
        }
        
        $backup_manager = new WP_Migrate_Backup();
        $backup_file = $backup_manager->backup_dir . 'theme_' . $theme_slug . '_' . date('Y-m-d_H-i-s') . '.zip';
        
        // Create ZIP backup of theme
        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if ($zip->open($backup_file, ZipArchive::CREATE) === TRUE) {
                $files = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($theme_dir),
                    RecursiveIteratorIterator::LEAVES_ONLY
                );
                
                foreach ($files as $file) {
                    if (!$file->isDir()) {
                        $filePath = $file->getRealPath();
                        $relativePath = substr($filePath, strlen($theme_dir) + 1);
                        $zip->addFile($filePath, $relativePath);
                    }
                }
                
                $zip->close();
            }
        }
    }
    
    /**
     * Create plugin backup
     */
    private function createPluginBackup($plugin_slug) {
        $plugin_dir = WP_PLUGIN_DIR . '/' . $plugin_slug;
        if (!is_dir($plugin_dir)) {
            return;
        }
        
        $backup_manager = new WP_Migrate_Backup();
        $backup_file = $backup_manager->backup_dir . 'plugin_' . $plugin_slug . '_' . date('Y-m-d_H-i-s') . '.zip';
        
        // Create ZIP backup of plugin
        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if ($zip->open($backup_file, ZipArchive::CREATE) === TRUE) {
                $files = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($plugin_dir),
                    RecursiveIteratorIterator::LEAVES_ONLY
                );
                
                foreach ($files as $file) {
                    if (!$file->isDir()) {
                        $filePath = $file->getRealPath();
                        $relativePath = substr($filePath, strlen($plugin_dir) + 1);
                        $zip->addFile($filePath, $relativePath);
                    }
                }
                
                $zip->close();
            }
        }
    }
    
    /**
     * Log message
     */
    private function logMessage($message, $level = 'info') {
        if (get_option('wp_migrate_enable_logging', true)) {
            error_log('[WP Migrate Files] ' . strtoupper($level) . ': ' . $message);
        }
    }
}