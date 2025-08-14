<?php

/**
 * WP Migrate API Authentication
 * Handles secure API authentication between WordPress sites
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Migrate_Authentication {
    
    /**
     * Authenticate API request
     */
    public function authenticateRequest($request) {
        // Check if authentication is enabled
        if (!get_option('wp_migrate_secure_connections', true)) {
            return true; // Allow all requests if security is disabled
        }
        
        // Get authorization header
        $auth_header = $request->get_header('authorization');
        
        if (empty($auth_header)) {
            return new WP_Error('no_auth', 'Authorization header is required', array('status' => 401));
        }
        
        // Parse Bearer token
        $token = $this->parseAuthorizationHeader($auth_header);
        
        if (!$token) {
            return new WP_Error('invalid_auth', 'Invalid authorization format', array('status' => 401));
        }
        
        // Validate token
        $validation_result = $this->validateToken($token);
        
        if (is_wp_error($validation_result)) {
            return $validation_result;
        }
        
        // Check capabilities
        if (!current_user_can('manage_options')) {
            return new WP_Error('insufficient_permissions', 'Insufficient permissions', array('status' => 403));
        }
        
        return true;
    }
    
    /**
     * Parse Authorization header
     */
    private function parseAuthorizationHeader($auth_header) {
        if (preg_match('/Bearer\s+(.+)/', $auth_header, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
    
    /**
     * Validate API token
     */
    private function validateToken($token) {
        global $wpdb;
        
        // Check if token exists in connections
        $connection = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}wp_migrate_connections WHERE api_key = %s AND is_active = 1",
                $this->hashToken($token)
            )
        );
        
        if (!$connection) {
            // Try to find token in stored API keys
            $stored_keys = get_option('wp_migrate_api_keys', array());
            
            foreach ($stored_keys as $key_data) {
                if ($this->verifyToken($token, $key_data['hash'])) {
                    // Token is valid
                    return true;
                }
            }
            
            return new WP_Error('invalid_token', 'Invalid API token', array('status' => 401));
        }
        
        // Update last used timestamp
        $wpdb->update(
            $wpdb->prefix . 'wp_migrate_connections',
            array('last_tested_at' => current_time('mysql')),
            array('id' => $connection->id),
            array('%s'),
            array('%d')
        );
        
        return true;
    }
    
    /**
     * Generate API key
     */
    public function generateApiKey($length = 64) {
        return bin2hex(random_bytes($length / 2));
    }
    
    /**
     * Hash token for storage
     */
    private function hashToken($token) {
        return hash('sha256', $token . SECURE_AUTH_SALT);
    }
    
    /**
     * Verify token against hash
     */
    private function verifyToken($token, $hash) {
        return hash_equals($hash, $this->hashToken($token));
    }
    
    /**
     * Create API key for site
     */
    public function createApiKey($name = '', $expires_at = null) {
        $api_key = $this->generateApiKey();
        $key_hash = $this->hashToken($api_key);
        
        $key_data = array(
            'name' => $name ?: 'API Key ' . date('Y-m-d H:i:s'),
            'hash' => $key_hash,
            'created_at' => current_time('mysql'),
            'expires_at' => $expires_at,
            'last_used' => null,
            'usage_count' => 0
        );
        
        // Store API key
        $stored_keys = get_option('wp_migrate_api_keys', array());
        $key_id = uniqid();
        $stored_keys[$key_id] = $key_data;
        update_option('wp_migrate_api_keys', $stored_keys);
        
        return array(
            'id' => $key_id,
            'api_key' => $api_key,
            'name' => $key_data['name'],
            'created_at' => $key_data['created_at']
        );
    }
    
    /**
     * Revoke API key
     */
    public function revokeApiKey($key_id) {
        $stored_keys = get_option('wp_migrate_api_keys', array());
        
        if (isset($stored_keys[$key_id])) {
            unset($stored_keys[$key_id]);
            update_option('wp_migrate_api_keys', $stored_keys);
            return true;
        }
        
        return false;
    }
    
    /**
     * Get all API keys
     */
    public function getApiKeys() {
        $stored_keys = get_option('wp_migrate_api_keys', array());
        $keys = array();
        
        foreach ($stored_keys as $key_id => $key_data) {
            $keys[] = array(
                'id' => $key_id,
                'name' => $key_data['name'],
                'created_at' => $key_data['created_at'],
                'expires_at' => $key_data['expires_at'],
                'last_used' => $key_data['last_used'],
                'usage_count' => $key_data['usage_count']
            );
        }
        
        return $keys;
    }
    
    /**
     * Update API key usage
     */
    public function updateKeyUsage($token) {
        $stored_keys = get_option('wp_migrate_api_keys', array());
        $token_hash = $this->hashToken($token);
        
        foreach ($stored_keys as $key_id => &$key_data) {
            if (hash_equals($key_data['hash'], $token_hash)) {
                $key_data['last_used'] = current_time('mysql');
                $key_data['usage_count']++;
                break;
            }
        }
        
        update_option('wp_migrate_api_keys', $stored_keys);
    }
    
    /**
     * Check if API key is expired
     */
    private function isKeyExpired($key_data) {
        if (!$key_data['expires_at']) {
            return false;
        }
        
        return strtotime($key_data['expires_at']) < time();
    }
    
    /**
     * Clean up expired API keys
     */
    public function cleanupExpiredKeys() {
        $stored_keys = get_option('wp_migrate_api_keys', array());
        $cleaned = 0;
        
        foreach ($stored_keys as $key_id => $key_data) {
            if ($this->isKeyExpired($key_data)) {
                unset($stored_keys[$key_id]);
                $cleaned++;
            }
        }
        
        if ($cleaned > 0) {
            update_option('wp_migrate_api_keys', $stored_keys);
        }
        
        return $cleaned;
    }
    
    /**
     * Generate secure nonce for operations
     */
    public function generateNonce($action, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        return wp_create_nonce($action . '_' . $user_id);
    }
    
    /**
     * Verify nonce
     */
    public function verifyNonce($nonce, $action, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        return wp_verify_nonce($nonce, $action . '_' . $user_id);
    }
    
    /**
     * Rate limiting check
     */
    public function checkRateLimit($identifier, $limit = 60, $window = 3600) {
        $transient_key = 'wp_migrate_rate_limit_' . md5($identifier);
        $requests = get_transient($transient_key);
        
        if ($requests === false) {
            $requests = array();
        }
        
        $current_time = time();
        
        // Remove old requests outside the window
        $requests = array_filter($requests, function($timestamp) use ($current_time, $window) {
            return ($current_time - $timestamp) < $window;
        });
        
        if (count($requests) >= $limit) {
            return new WP_Error('rate_limit_exceeded', 'Rate limit exceeded', array('status' => 429));
        }
        
        // Add current request
        $requests[] = $current_time;
        set_transient($transient_key, $requests, $window);
        
        return true;
    }
    
    /**
     * Log authentication attempt
     */
    public function logAuthAttempt($success, $token = '', $ip = '', $user_agent = '') {
        if (!get_option('wp_migrate_enable_logging', true)) {
            return;
        }
        
        $log_data = array(
            'timestamp' => current_time('mysql'),
            'success' => $success,
            'ip' => $ip ?: $_SERVER['REMOTE_ADDR'],
            'user_agent' => $user_agent ?: $_SERVER['HTTP_USER_AGENT'],
            'token_hash' => $token ? substr($this->hashToken($token), 0, 8) : ''
        );
        
        $log_entries = get_option('wp_migrate_auth_log', array());
        
        // Keep only last 100 entries
        if (count($log_entries) >= 100) {
            $log_entries = array_slice($log_entries, -99);
        }
        
        $log_entries[] = $log_data;
        update_option('wp_migrate_auth_log', $log_entries);
    }
    
    /**
     * Get authentication log
     */
    public function getAuthLog($limit = 50) {
        $log_entries = get_option('wp_migrate_auth_log', array());
        return array_slice(array_reverse($log_entries), 0, $limit);
    }
    
    /**
     * Check IP whitelist
     */
    public function checkIpWhitelist($ip) {
        $whitelist = get_option('wp_migrate_ip_whitelist', array());
        
        if (empty($whitelist)) {
            return true; // No whitelist means all IPs allowed
        }
        
        foreach ($whitelist as $allowed_ip) {
            if ($this->ipInRange($ip, $allowed_ip)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if IP is in range
     */
    private function ipInRange($ip, $range) {
        if (strpos($range, '/') === false) {
            // Single IP
            return $ip === $range;
        }
        
        // CIDR range
        list($subnet, $bits) = explode('/', $range);
        $ip_long = ip2long($ip);
        $subnet_long = ip2long($subnet);
        $mask = -1 << (32 - $bits);
        
        return ($ip_long & $mask) === ($subnet_long & $mask);
    }
}