<?php

/**
 * WP Migrate Connection Manager
 * Handles secure connections between WordPress sites
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Migrate_Connections {
    
    private $wpdb;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
    }
    
    /**
     * Create a new connection
     */
    public function createConnection($data) {
        $default_data = array(
            'name' => '',
            'url' => '',
            'api_key' => '',
            'description' => '',
            'is_active' => 1
        );
        
        $data = wp_parse_args($data, $default_data);
        
        // Validate required fields
        if (empty($data['name']) || empty($data['url']) || empty($data['api_key'])) {
            return array(
                'success' => false,
                'error' => 'Name, URL, and API key are required'
            );
        }
        
        // Validate URL format
        if (!filter_var($data['url'], FILTER_VALIDATE_URL)) {
            return array(
                'success' => false,
                'error' => 'Invalid URL format'
            );
        }
        
        // Check if connection name already exists
        $existing = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->wpdb->prefix}wp_migrate_connections WHERE name = %s",
                $data['name']
            )
        );
        
        if ($existing > 0) {
            return array(
                'success' => false,
                'error' => 'Connection name already exists'
            );
        }
        
        // Generate secure API key if not provided
        if (empty($data['api_key'])) {
            $data['api_key'] = $this->generateApiKey();
        }
        
        // Insert connection
        $result = $this->wpdb->insert(
            $this->wpdb->prefix . 'wp_migrate_connections',
            array(
                'name' => sanitize_text_field($data['name']),
                'url' => esc_url_raw($data['url']),
                'api_key' => $this->encryptApiKey($data['api_key']),
                'description' => sanitize_textarea_field($data['description']),
                'is_active' => intval($data['is_active']),
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%d', '%s', '%s')
        );
        
        if ($result === false) {
            return array(
                'success' => false,
                'error' => 'Failed to create connection: ' . $this->wpdb->last_error
            );
        }
        
        $connection_id = $this->wpdb->insert_id;
        
        // Test the connection
        $test_result = $this->testConnection($connection_id);
        
        return array(
            'success' => true,
            'connection_id' => $connection_id,
            'test_result' => $test_result
        );
    }
    
    /**
     * Update connection
     */
    public function updateConnection($connection_id, $data) {
        $connection = $this->getConnection($connection_id);
        if (!$connection) {
            return array(
                'success' => false,
                'error' => 'Connection not found'
            );
        }
        
        $update_data = array();
        $update_format = array();
        
        if (isset($data['name'])) {
            $update_data['name'] = sanitize_text_field($data['name']);
            $update_format[] = '%s';
        }
        
        if (isset($data['url'])) {
            if (!filter_var($data['url'], FILTER_VALIDATE_URL)) {
                return array(
                    'success' => false,
                    'error' => 'Invalid URL format'
                );
            }
            $update_data['url'] = esc_url_raw($data['url']);
            $update_format[] = '%s';
        }
        
        if (isset($data['api_key'])) {
            $update_data['api_key'] = $this->encryptApiKey($data['api_key']);
            $update_format[] = '%s';
        }
        
        if (isset($data['description'])) {
            $update_data['description'] = sanitize_textarea_field($data['description']);
            $update_format[] = '%s';
        }
        
        if (isset($data['is_active'])) {
            $update_data['is_active'] = intval($data['is_active']);
            $update_format[] = '%d';
        }
        
        $update_data['updated_at'] = current_time('mysql');
        $update_format[] = '%s';
        
        $result = $this->wpdb->update(
            $this->wpdb->prefix . 'wp_migrate_connections',
            $update_data,
            array('id' => $connection_id),
            $update_format,
            array('%d')
        );
        
        if ($result === false) {
            return array(
                'success' => false,
                'error' => 'Failed to update connection: ' . $this->wpdb->last_error
            );
        }
        
        return array(
            'success' => true,
            'updated' => $result > 0
        );
    }
    
    /**
     * Delete connection
     */
    public function deleteConnection($connection_id) {
        $result = $this->wpdb->delete(
            $this->wpdb->prefix . 'wp_migrate_connections',
            array('id' => $connection_id),
            array('%d')
        );
        
        return array(
            'success' => $result !== false,
            'deleted' => $result > 0
        );
    }
    
    /**
     * Get connection by ID
     */
    public function getConnection($connection_id) {
        $connection = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->wpdb->prefix}wp_migrate_connections WHERE id = %d",
                $connection_id
            )
        );
        
        if ($connection) {
            $connection->api_key = $this->decryptApiKey($connection->api_key);
        }
        
        return $connection;
    }
    
    /**
     * Get all connections
     */
    public function getConnections($active_only = false) {
        $where_clause = $active_only ? 'WHERE is_active = 1' : '';
        
        $connections = $this->wpdb->get_results(
            "SELECT * FROM {$this->wpdb->prefix}wp_migrate_connections {$where_clause} ORDER BY name ASC"
        );
        
        foreach ($connections as $connection) {
            $connection->api_key = $this->decryptApiKey($connection->api_key);
        }
        
        return $connections;
    }
    
    /**
     * Test connection to remote site
     */
    public function testConnection($connection_id) {
        $connection = $this->getConnection($connection_id);
        if (!$connection) {
            return array(
                'success' => false,
                'error' => 'Connection not found'
            );
        }
        
        try {
            $api_url = rtrim($connection->url, '/') . '/wp-json/wp-migrate/v1/test';
            
            $args = array(
                'method' => 'GET',
                'timeout' => 30,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $connection->api_key,
                    'User-Agent' => 'WP Migrate/' . WP_MIGRATE_VERSION
                ),
                'sslverify' => get_option('wp_migrate_secure_connections', true)
            );
            
            $response = wp_remote_get($api_url, $args);
            
            if (is_wp_error($response)) {
                throw new Exception('HTTP request failed: ' . $response->get_error_message());
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            
            if ($response_code !== 200) {
                throw new Exception('HTTP ' . $response_code . ': ' . $body);
            }
            
            $data = json_decode($body, true);
            
            if (!$data || !isset($data['success'])) {
                throw new Exception('Invalid response format');
            }
            
            if (!$data['success']) {
                throw new Exception(isset($data['error']) ? $data['error'] : 'Unknown error');
            }
            
            // Update connection test status
            $this->updateConnectionTestStatus($connection_id, 'success', null);
            
            return array(
                'success' => true,
                'message' => 'Connection successful',
                'remote_info' => isset($data['info']) ? $data['info'] : null
            );
            
        } catch (Exception $e) {
            // Update connection test status
            $this->updateConnectionTestStatus($connection_id, 'failed', $e->getMessage());
            
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Update connection test status
     */
    private function updateConnectionTestStatus($connection_id, $status, $error_message) {
        $this->wpdb->update(
            $this->wpdb->prefix . 'wp_migrate_connections',
            array(
                'last_tested_at' => current_time('mysql'),
                'test_status' => $status
            ),
            array('id' => $connection_id),
            array('%s', '%s'),
            array('%d')
        );
    }
    
    /**
     * Generate secure API key
     */
    private function generateApiKey($length = 64) {
        return bin2hex(random_bytes($length / 2));
    }
    
    /**
     * Encrypt API key for storage
     */
    private function encryptApiKey($api_key) {
        if (!function_exists('openssl_encrypt')) {
            return base64_encode($api_key); // Fallback to base64
        }
        
        $key = $this->getEncryptionKey();
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        $encrypted = openssl_encrypt($api_key, 'aes-256-cbc', $key, 0, $iv);
        
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Decrypt API key
     */
    private function decryptApiKey($encrypted_api_key) {
        if (!function_exists('openssl_decrypt')) {
            return base64_decode($encrypted_api_key); // Fallback from base64
        }
        
        $data = base64_decode($encrypted_api_key);
        $key = $this->getEncryptionKey();
        $iv_length = openssl_cipher_iv_length('aes-256-cbc');
        $iv = substr($data, 0, $iv_length);
        $encrypted = substr($data, $iv_length);
        
        return openssl_decrypt($encrypted, 'aes-256-cbc', $key, 0, $iv);
    }
    
    /**
     * Get encryption key
     */
    private function getEncryptionKey() {
        $key = get_option('wp_migrate_encryption_key');
        
        if (!$key) {
            $key = bin2hex(random_bytes(32));
            update_option('wp_migrate_encryption_key', $key);
        }
        
        return hash('sha256', $key . SECURE_AUTH_KEY);
    }
    
    /**
     * Get connection status summary
     */
    public function getConnectionStatusSummary() {
        $total = $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->wpdb->prefix}wp_migrate_connections"
        );
        
        $active = $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->wpdb->prefix}wp_migrate_connections WHERE is_active = 1"
        );
        
        $tested_recently = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->wpdb->prefix}wp_migrate_connections 
                WHERE last_tested_at > %s AND test_status = 'success'",
                date('Y-m-d H:i:s', strtotime('-1 hour'))
            )
        );
        
        return array(
            'total' => intval($total),
            'active' => intval($active),
            'tested_recently' => intval($tested_recently)
        );
    }
    
    /**
     * Bulk test all connections
     */
    public function testAllConnections() {
        $connections = $this->getConnections(true);
        $results = array();
        
        foreach ($connections as $connection) {
            $results[$connection->id] = $this->testConnection($connection->id);
        }
        
        return $results;
    }
}