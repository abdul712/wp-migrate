<?php

/**
 * WP Migrate Media Synchronization
 * Handles media library synchronization between WordPress sites
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Migrate_Media {
    
    private $upload_dir;
    private $connections;
    
    public function __construct() {
        $this->upload_dir = wp_upload_dir();
        $this->connections = new WP_Migrate_Connections();
    }
    
    /**
     * Sync media library with remote site
     */
    public function syncMediaLibrary($connection_id, $options = array()) {
        $default_options = array(
            'direction' => 'pull', // 'pull', 'push', or 'sync'
            'date_from' => null,
            'date_to' => null,
            'file_types' => array(), // Empty means all types
            'max_file_size' => 0, // 0 means no limit
            'overwrite_existing' => false,
            'verify_checksums' => true,
            'create_thumbnails' => true
        );
        
        $options = wp_parse_args($options, $default_options);
        
        try {
            $connection = $this->connections->getConnection($connection_id);
            if (!$connection) {
                throw new Exception('Connection not found');
            }
            
            $this->logMessage("Starting media sync with {$connection->name}", 'info');
            
            $result = array(
                'success' => false,
                'transferred_files' => 0,
                'skipped_files' => 0,
                'errors' => array(),
                'total_size' => 0
            );
            
            switch ($options['direction']) {
                case 'pull':
                    $result = $this->pullMediaFromRemote($connection, $options);
                    break;
                case 'push':
                    $result = $this->pushMediaToRemote($connection, $options);
                    break;
                case 'sync':
                    $result = $this->syncBidirectional($connection, $options);
                    break;
                default:
                    throw new Exception('Invalid sync direction');
            }
            
            $this->logMessage("Media sync completed. Transferred: {$result['transferred_files']}, Skipped: {$result['skipped_files']}", 'success');
            
            return $result;
            
        } catch (Exception $e) {
            $this->logMessage('Media sync failed: ' . $e->getMessage(), 'error');
            return array(
                'success' => false,
                'error' => $e->getMessage(),
                'transferred_files' => 0,
                'skipped_files' => 0,
                'errors' => array($e->getMessage())
            );
        }
    }
    
    /**
     * Pull media files from remote site
     */
    private function pullMediaFromRemote($connection, $options) {
        // Get remote media list
        $remote_files = $this->getRemoteMediaList($connection, $options);
        
        if (!$remote_files['success']) {
            throw new Exception('Failed to get remote media list: ' . $remote_files['error']);
        }
        
        $result = array(
            'success' => true,
            'transferred_files' => 0,
            'skipped_files' => 0,
            'errors' => array(),
            'total_size' => 0
        );
        
        foreach ($remote_files['files'] as $remote_file) {
            try {
                $local_path = $this->upload_dir['basedir'] . '/' . $remote_file['relative_path'];
                
                // Check if file should be skipped
                if ($this->shouldSkipFile($local_path, $remote_file, $options)) {
                    $result['skipped_files']++;
                    continue;
                }
                
                // Create directory if needed
                $local_dir = dirname($local_path);
                if (!is_dir($local_dir)) {
                    wp_mkdir_p($local_dir);
                }
                
                // Download file
                $download_result = $this->downloadRemoteFile($connection, $remote_file['url'], $local_path);
                
                if ($download_result['success']) {
                    // Verify checksum if enabled
                    if ($options['verify_checksums'] && isset($remote_file['checksum'])) {
                        $local_checksum = md5_file($local_path);
                        if ($local_checksum !== $remote_file['checksum']) {
                            unlink($local_path);
                            throw new Exception('Checksum verification failed for: ' . $remote_file['relative_path']);
                        }
                    }
                    
                    // Add to WordPress media library
                    $this->addToMediaLibrary($local_path, $remote_file);
                    
                    $result['transferred_files']++;
                    $result['total_size'] += $remote_file['size'];
                    
                    $this->logMessage("Downloaded: {$remote_file['relative_path']}", 'debug');
                } else {
                    $result['errors'][] = "Failed to download: {$remote_file['relative_path']} - {$download_result['error']}";
                }
                
            } catch (Exception $e) {
                $result['errors'][] = $e->getMessage();
            }
        }
        
        return $result;
    }
    
    /**
     * Push media files to remote site
     */
    private function pushMediaToRemote($connection, $options) {
        // Get local media list
        $local_files = $this->getLocalMediaList($options);
        
        $result = array(
            'success' => true,
            'transferred_files' => 0,
            'skipped_files' => 0,
            'errors' => array(),
            'total_size' => 0
        );
        
        foreach ($local_files as $local_file) {
            try {
                // Upload file to remote site
                $upload_result = $this->uploadFileToRemote($connection, $local_file, $options);
                
                if ($upload_result['success']) {
                    $result['transferred_files']++;
                    $result['total_size'] += $local_file['size'];
                    
                    $this->logMessage("Uploaded: {$local_file['relative_path']}", 'debug');
                } else {
                    if ($upload_result['skipped']) {
                        $result['skipped_files']++;
                    } else {
                        $result['errors'][] = "Failed to upload: {$local_file['relative_path']} - {$upload_result['error']}";
                    }
                }
                
            } catch (Exception $e) {
                $result['errors'][] = $e->getMessage();
            }
        }
        
        return $result;
    }
    
    /**
     * Bidirectional sync
     */
    private function syncBidirectional($connection, $options) {
        // This would implement a more complex bidirectional sync
        // For now, we'll do a simple pull followed by push
        
        $pull_options = $options;
        $pull_options['direction'] = 'pull';
        $pull_result = $this->pullMediaFromRemote($connection, $pull_options);
        
        $push_options = $options;
        $push_options['direction'] = 'push';
        $push_result = $this->pushMediaToRemote($connection, $push_options);
        
        return array(
            'success' => $pull_result['success'] && $push_result['success'],
            'transferred_files' => $pull_result['transferred_files'] + $push_result['transferred_files'],
            'skipped_files' => $pull_result['skipped_files'] + $push_result['skipped_files'],
            'errors' => array_merge($pull_result['errors'], $push_result['errors']),
            'total_size' => $pull_result['total_size'] + $push_result['total_size'],
            'pull_result' => $pull_result,
            'push_result' => $push_result
        );
    }
    
    /**
     * Get remote media list via API
     */
    private function getRemoteMediaList($connection, $options) {
        $api_url = rtrim($connection->url, '/') . '/wp-json/wp-migrate/v1/media/list';
        
        $args = array(
            'method' => 'POST',
            'timeout' => 300,
            'headers' => array(
                'Authorization' => 'Bearer ' . $connection->api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($options)
        );
        
        $response = wp_remote_post($api_url, $args);
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error' => $response->get_error_message()
            );
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        return $data;
    }
    
    /**
     * Get local media list
     */
    private function getLocalMediaList($options) {
        $files = array();
        
        // Query WordPress media library
        $query_args = array(
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'posts_per_page' => -1,
        );
        
        // Add date filters
        if (!empty($options['date_from']) || !empty($options['date_to'])) {
            $query_args['date_query'] = array();
            
            if (!empty($options['date_from'])) {
                $query_args['date_query']['after'] = $options['date_from'];
            }
            
            if (!empty($options['date_to'])) {
                $query_args['date_query']['before'] = $options['date_to'];
            }
        }
        
        // Add file type filters
        if (!empty($options['file_types'])) {
            $query_args['post_mime_type'] = $options['file_types'];
        }
        
        $attachments = get_posts($query_args);
        
        foreach ($attachments as $attachment) {
            $file_path = get_attached_file($attachment->ID);
            
            if (!file_exists($file_path)) {
                continue;
            }
            
            $file_size = filesize($file_path);
            
            // Check file size limit
            if ($options['max_file_size'] > 0 && $file_size > $options['max_file_size']) {
                continue;
            }
            
            $relative_path = str_replace($this->upload_dir['basedir'] . '/', '', $file_path);
            
            $files[] = array(
                'id' => $attachment->ID,
                'path' => $file_path,
                'relative_path' => $relative_path,
                'url' => wp_get_attachment_url($attachment->ID),
                'size' => $file_size,
                'mime_type' => $attachment->post_mime_type,
                'title' => $attachment->post_title,
                'alt_text' => get_post_meta($attachment->ID, '_wp_attachment_image_alt', true),
                'description' => $attachment->post_content,
                'caption' => $attachment->post_excerpt,
                'checksum' => md5_file($file_path),
                'modified' => $attachment->post_modified
            );
        }
        
        return $files;
    }
    
    /**
     * Check if file should be skipped
     */
    private function shouldSkipFile($local_path, $remote_file, $options) {
        // File doesn't exist locally
        if (!file_exists($local_path)) {
            return false;
        }
        
        // Don't overwrite existing files if option is disabled
        if (!$options['overwrite_existing']) {
            return true;
        }
        
        // Check if local file is newer
        $local_mtime = filemtime($local_path);
        $remote_mtime = strtotime($remote_file['modified']);
        
        if ($local_mtime > $remote_mtime) {
            return true;
        }
        
        // Check if files are identical (same checksum)
        if (isset($remote_file['checksum'])) {
            $local_checksum = md5_file($local_path);
            if ($local_checksum === $remote_file['checksum']) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Download remote file
     */
    private function downloadRemoteFile($connection, $file_url, $local_path) {
        try {
            $args = array(
                'timeout' => 300,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $connection->api_key
                )
            );
            
            $response = wp_remote_get($file_url, $args);
            
            if (is_wp_error($response)) {
                throw new Exception($response->get_error_message());
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code !== 200) {
                throw new Exception('HTTP ' . $response_code);
            }
            
            $file_content = wp_remote_retrieve_body($response);
            
            if (file_put_contents($local_path, $file_content) === false) {
                throw new Exception('Failed to save file locally');
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
     * Upload file to remote site
     */
    private function uploadFileToRemote($connection, $local_file, $options) {
        try {
            $api_url = rtrim($connection->url, '/') . '/wp-json/wp-migrate/v1/media/upload';
            
            // Check if file already exists on remote
            $exists_result = $this->checkRemoteFileExists($connection, $local_file);
            
            if ($exists_result['exists'] && !$options['overwrite_existing']) {
                return array(
                    'success' => true,
                    'skipped' => true,
                    'message' => 'File already exists on remote'
                );
            }
            
            $args = array(
                'method' => 'POST',
                'timeout' => 300,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $connection->api_key
                ),
                'body' => array(
                    'file' => curl_file_create($local_file['path'], $local_file['mime_type'], basename($local_file['path'])),
                    'metadata' => json_encode(array(
                        'title' => $local_file['title'],
                        'alt_text' => $local_file['alt_text'],
                        'description' => $local_file['description'],
                        'caption' => $local_file['caption'],
                        'relative_path' => $local_file['relative_path']
                    ))
                )
            );
            
            $response = wp_remote_post($api_url, $args);
            
            if (is_wp_error($response)) {
                throw new Exception($response->get_error_message());
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if (!$data['success']) {
                throw new Exception($data['error']);
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
     * Check if file exists on remote site
     */
    private function checkRemoteFileExists($connection, $local_file) {
        $api_url = rtrim($connection->url, '/') . '/wp-json/wp-migrate/v1/media/exists';
        
        $args = array(
            'method' => 'POST',
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Bearer ' . $connection->api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'relative_path' => $local_file['relative_path'],
                'checksum' => $local_file['checksum']
            ))
        );
        
        $response = wp_remote_post($api_url, $args);
        
        if (is_wp_error($response)) {
            return array('exists' => false);
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        return array(
            'exists' => isset($data['exists']) ? $data['exists'] : false,
            'same_checksum' => isset($data['same_checksum']) ? $data['same_checksum'] : false
        );
    }
    
    /**
     * Add file to WordPress media library
     */
    private function addToMediaLibrary($file_path, $remote_file) {
        // Check if attachment already exists
        $existing_id = $this->getAttachmentIdByPath($file_path);
        
        if ($existing_id) {
            // Update existing attachment metadata
            $this->updateAttachmentMetadata($existing_id, $remote_file);
            return $existing_id;
        }
        
        // Create new attachment
        $file_type = wp_check_filetype(basename($file_path), null);
        
        $attachment_data = array(
            'guid' => $this->upload_dir['url'] . '/' . $remote_file['relative_path'],
            'post_mime_type' => $file_type['type'],
            'post_title' => $remote_file['title'] ?: sanitize_file_name(basename($file_path)),
            'post_content' => $remote_file['description'] ?: '',
            'post_excerpt' => $remote_file['caption'] ?: '',
            'post_status' => 'inherit'
        );
        
        $attachment_id = wp_insert_attachment($attachment_data, $file_path);
        
        if (!is_wp_error($attachment_id)) {
            // Set alt text
            if (!empty($remote_file['alt_text'])) {
                update_post_meta($attachment_id, '_wp_attachment_image_alt', $remote_file['alt_text']);
            }
            
            // Generate attachment metadata
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            $attachment_metadata = wp_generate_attachment_metadata($attachment_id, $file_path);
            wp_update_attachment_metadata($attachment_id, $attachment_metadata);
        }
        
        return $attachment_id;
    }
    
    /**
     * Get attachment ID by file path
     */
    private function getAttachmentIdByPath($file_path) {
        global $wpdb;
        
        $relative_path = str_replace($this->upload_dir['basedir'] . '/', '', $file_path);
        
        $attachment_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} 
                WHERE meta_key = '_wp_attached_file' AND meta_value = %s",
                $relative_path
            )
        );
        
        return $attachment_id;
    }
    
    /**
     * Update attachment metadata
     */
    private function updateAttachmentMetadata($attachment_id, $remote_file) {
        if (!empty($remote_file['title'])) {
            wp_update_post(array(
                'ID' => $attachment_id,
                'post_title' => $remote_file['title']
            ));
        }
        
        if (!empty($remote_file['alt_text'])) {
            update_post_meta($attachment_id, '_wp_attachment_image_alt', $remote_file['alt_text']);
        }
        
        if (!empty($remote_file['description'])) {
            wp_update_post(array(
                'ID' => $attachment_id,
                'post_content' => $remote_file['description']
            ));
        }
        
        if (!empty($remote_file['caption'])) {
            wp_update_post(array(
                'ID' => $attachment_id,
                'post_excerpt' => $remote_file['caption']
            ));
        }
    }
    
    /**
     * Get media sync statistics
     */
    public function getMediaStats() {
        global $wpdb;
        
        $stats = array();
        
        // Total media files
        $stats['total_files'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment'"
        );
        
        // Total media size
        $attachments = get_posts(array(
            'post_type' => 'attachment',
            'posts_per_page' => -1,
            'fields' => 'ids'
        ));
        
        $total_size = 0;
        foreach ($attachments as $attachment_id) {
            $file_path = get_attached_file($attachment_id);
            if (file_exists($file_path)) {
                $total_size += filesize($file_path);
            }
        }
        
        $stats['total_size'] = $total_size;
        $stats['total_size_formatted'] = size_format($total_size);
        
        // File types breakdown
        $file_types = $wpdb->get_results(
            "SELECT post_mime_type, COUNT(*) as count 
            FROM {$wpdb->posts} 
            WHERE post_type = 'attachment' 
            GROUP BY post_mime_type 
            ORDER BY count DESC"
        );
        
        $stats['file_types'] = $file_types;
        
        return $stats;
    }
    
    /**
     * Log message
     */
    private function logMessage($message, $level = 'info') {
        if (get_option('wp_migrate_enable_logging', true)) {
            error_log('[WP Migrate Media] ' . strtoupper($level) . ': ' . $message);
        }
    }
}