<?php

/**
 * WP Migrate Admin Interface
 * Main admin interface for WordPress dashboard integration
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Migrate_Admin {
    
    private $page_hook;
    
    public function __construct() {
        add_action('admin_menu', array($this, 'addAdminMenu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueueScripts'));
        add_action('wp_ajax_wp_migrate_test_connection', array($this, 'ajaxTestConnection'));
        add_action('wp_ajax_wp_migrate_start_migration', array($this, 'ajaxStartMigration'));
        add_action('wp_ajax_wp_migrate_get_migration_status', array($this, 'ajaxGetMigrationStatus'));
        add_action('admin_init', array($this, 'handleFormSubmissions'));
    }
    
    /**
     * Add admin menu
     */
    public function addAdminMenu() {
        // Main menu page
        $this->page_hook = add_menu_page(
            __('WP Migrate', 'wp-migrate'),
            __('WP Migrate', 'wp-migrate'),
            'manage_options',
            'wp-migrate',
            array($this, 'renderMainPage'),
            'dashicons-migrate',
            30
        );
        
        // Dashboard submenu
        add_submenu_page(
            'wp-migrate',
            __('Migration Dashboard', 'wp-migrate'),
            __('Dashboard', 'wp-migrate'),
            'manage_options',
            'wp-migrate',
            array($this, 'renderMainPage')
        );
        
        // Migration Wizard submenu
        add_submenu_page(
            'wp-migrate',
            __('Migration Wizard', 'wp-migrate'),
            __('Migration Wizard', 'wp-migrate'),
            'manage_options',
            'wp-migrate-wizard',
            array($this, 'renderWizardPage')
        );
        
        // Connections submenu
        add_submenu_page(
            'wp-migrate',
            __('Connections', 'wp-migrate'),
            __('Connections', 'wp-migrate'),
            'manage_options',
            'wp-migrate-connections',
            array($this, 'renderConnectionsPage')
        );
        
        // Backups submenu
        add_submenu_page(
            'wp-migrate',
            __('Backups', 'wp-migrate'),
            __('Backups', 'wp-migrate'),
            'manage_options',
            'wp-migrate-backups',
            array($this, 'renderBackupsPage')
        );
        
        // History submenu
        add_submenu_page(
            'wp-migrate',
            __('Migration History', 'wp-migrate'),
            __('History', 'wp-migrate'),
            'manage_options',
            'wp-migrate-history',
            array($this, 'renderHistoryPage')
        );
        
        // Settings submenu
        add_submenu_page(
            'wp-migrate',
            __('Settings', 'wp-migrate'),
            __('Settings', 'wp-migrate'),
            'manage_options',
            'wp-migrate-settings',
            array($this, 'renderSettingsPage')
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueueScripts($hook) {
        if (strpos($hook, 'wp-migrate') === false) {
            return;
        }
        
        // Enqueue WordPress media uploader
        wp_enqueue_media();
        
        // Enqueue admin CSS
        wp_enqueue_style(
            'wp-migrate-admin',
            WP_MIGRATE_PLUGIN_URL . 'src/assets/css/admin.css',
            array(),
            WP_MIGRATE_VERSION
        );
        
        // Enqueue admin JavaScript
        wp_enqueue_script(
            'wp-migrate-admin',
            WP_MIGRATE_PLUGIN_URL . 'src/assets/js/admin.js',
            array('jquery', 'wp-util'),
            WP_MIGRATE_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('wp-migrate-admin', 'wpMigrateAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wp_migrate_nonce'),
            'strings' => array(
                'confirmDelete' => __('Are you sure you want to delete this item?', 'wp-migrate'),
                'connectionTesting' => __('Testing connection...', 'wp-migrate'),
                'migrationStarting' => __('Starting migration...', 'wp-migrate'),
                'migrationInProgress' => __('Migration in progress...', 'wp-migrate'),
                'migrationCompleted' => __('Migration completed!', 'wp-migrate'),
                'migrationFailed' => __('Migration failed!', 'wp-migrate'),
            )
        ));
    }
    
    /**
     * Handle form submissions
     */
    public function handleFormSubmissions() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        if (isset($_POST['wp_migrate_action']) && wp_verify_nonce($_POST['wp_migrate_nonce'], 'wp_migrate_action')) {
            $action = sanitize_text_field($_POST['wp_migrate_action']);
            
            switch ($action) {
                case 'create_connection':
                    $this->handleCreateConnection();
                    break;
                case 'update_connection':
                    $this->handleUpdateConnection();
                    break;
                case 'delete_connection':
                    $this->handleDeleteConnection();
                    break;
                case 'create_backup':
                    $this->handleCreateBackup();
                    break;
                case 'delete_backup':
                    $this->handleDeleteBackup();
                    break;
                case 'update_settings':
                    $this->handleUpdateSettings();
                    break;
            }
        }
    }
    
    /**
     * Render main dashboard page
     */
    public function renderMainPage() {
        $connections = new WP_Migrate_Connections();
        $backup_manager = new WP_Migrate_Backup();
        
        $connection_stats = $connections->getConnectionStatusSummary();
        $recent_backups = $backup_manager->getBackups(5);
        $recent_migrations = $this->getRecentMigrations(5);
        
        include WP_MIGRATE_PLUGIN_DIR . 'templates/admin/dashboard.php';
    }
    
    /**
     * Render migration wizard page
     */
    public function renderWizardPage() {
        $wizard = new WP_Migrate_Wizard();
        $wizard->render();
    }
    
    /**
     * Render connections page
     */
    public function renderConnectionsPage() {
        $connections = new WP_Migrate_Connections();
        $all_connections = $connections->getConnections();
        
        include WP_MIGRATE_PLUGIN_DIR . 'templates/admin/connections.php';
    }
    
    /**
     * Render backups page
     */
    public function renderBackupsPage() {
        $backup_manager = new WP_Migrate_Backup();
        $backups = $backup_manager->getBackups();
        
        include WP_MIGRATE_PLUGIN_DIR . 'templates/admin/backups.php';
    }
    
    /**
     * Render history page
     */
    public function renderHistoryPage() {
        $history = new WP_Migrate_History();
        $migrations = $history->getMigrationHistory();
        
        include WP_MIGRATE_PLUGIN_DIR . 'templates/admin/history.php';
    }
    
    /**
     * Render settings page
     */
    public function renderSettingsPage() {
        $settings = new WP_Migrate_Settings();
        $current_settings = $settings->getAllSettings();
        
        include WP_MIGRATE_PLUGIN_DIR . 'templates/admin/settings.php';
    }
    
    /**
     * Handle create connection
     */
    private function handleCreateConnection() {
        $connections = new WP_Migrate_Connections();
        
        $data = array(
            'name' => sanitize_text_field($_POST['connection_name']),
            'url' => esc_url_raw($_POST['connection_url']),
            'api_key' => sanitize_text_field($_POST['api_key']),
            'description' => sanitize_textarea_field($_POST['description']),
        );
        
        $result = $connections->createConnection($data);
        
        if ($result['success']) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success"><p>' . __('Connection created successfully!', 'wp-migrate') . '</p></div>';
            });
        } else {
            add_action('admin_notices', function() use ($result) {
                echo '<div class="notice notice-error"><p>' . esc_html($result['error']) . '</p></div>';
            });
        }
    }
    
    /**
     * Handle update connection
     */
    private function handleUpdateConnection() {
        $connections = new WP_Migrate_Connections();
        
        $connection_id = intval($_POST['connection_id']);
        $data = array(
            'name' => sanitize_text_field($_POST['connection_name']),
            'url' => esc_url_raw($_POST['connection_url']),
            'description' => sanitize_textarea_field($_POST['description']),
        );
        
        if (!empty($_POST['api_key'])) {
            $data['api_key'] = sanitize_text_field($_POST['api_key']);
        }
        
        $result = $connections->updateConnection($connection_id, $data);
        
        if ($result['success']) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success"><p>' . __('Connection updated successfully!', 'wp-migrate') . '</p></div>';
            });
        } else {
            add_action('admin_notices', function() use ($result) {
                echo '<div class="notice notice-error"><p>' . esc_html($result['error']) . '</p></div>';
            });
        }
    }
    
    /**
     * Handle delete connection
     */
    private function handleDeleteConnection() {
        $connections = new WP_Migrate_Connections();
        $connection_id = intval($_POST['connection_id']);
        
        $result = $connections->deleteConnection($connection_id);
        
        if ($result['success']) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success"><p>' . __('Connection deleted successfully!', 'wp-migrate') . '</p></div>';
            });
        } else {
            add_action('admin_notices', function() use ($result) {
                echo '<div class="notice notice-error"><p>' . esc_html($result['error']) . '</p></div>';
            });
        }
    }
    
    /**
     * Handle create backup
     */
    private function handleCreateBackup() {
        $backup_manager = new WP_Migrate_Backup();
        
        $name = sanitize_text_field($_POST['backup_name']);
        $description = sanitize_textarea_field($_POST['backup_description']);
        $type = sanitize_text_field($_POST['backup_type']);
        
        if ($type === 'full') {
            $result = $backup_manager->createFullBackup($name, $description);
        } elseif ($type === 'database') {
            $result = $backup_manager->createDatabaseBackup(null);
        } elseif ($type === 'files') {
            $result = $backup_manager->createFilesBackup(null);
        } else {
            $result = array('success' => false, 'error' => 'Invalid backup type');
        }
        
        if ($result['success']) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success"><p>' . __('Backup created successfully!', 'wp-migrate') . '</p></div>';
            });
        } else {
            add_action('admin_notices', function() use ($result) {
                echo '<div class="notice notice-error"><p>' . esc_html($result['error']) . '</p></div>';
            });
        }
    }
    
    /**
     * Handle delete backup
     */
    private function handleDeleteBackup() {
        $backup_manager = new WP_Migrate_Backup();
        $backup_id = intval($_POST['backup_id']);
        
        $result = $backup_manager->deleteBackup($backup_id);
        
        if ($result['success']) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success"><p>' . __('Backup deleted successfully!', 'wp-migrate') . '</p></div>';
            });
        } else {
            add_action('admin_notices', function() use ($result) {
                echo '<div class="notice notice-error"><p>' . esc_html($result['error']) . '</p></div>';
            });
        }
    }
    
    /**
     * Handle update settings
     */
    private function handleUpdateSettings() {
        $settings = array(
            'wp_migrate_max_execution_time' => intval($_POST['max_execution_time']),
            'wp_migrate_memory_limit' => sanitize_text_field($_POST['memory_limit']),
            'wp_migrate_chunk_size' => intval($_POST['chunk_size']),
            'wp_migrate_backup_retention' => intval($_POST['backup_retention']),
            'wp_migrate_enable_logging' => isset($_POST['enable_logging']) ? 1 : 0,
            'wp_migrate_log_level' => sanitize_text_field($_POST['log_level']),
            'wp_migrate_secure_connections' => isset($_POST['secure_connections']) ? 1 : 0,
        );
        
        foreach ($settings as $option => $value) {
            update_option($option, $value);
        }
        
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success"><p>' . __('Settings updated successfully!', 'wp-migrate') . '</p></div>';
        });
    }
    
    /**
     * AJAX: Test connection
     */
    public function ajaxTestConnection() {
        check_ajax_referer('wp_migrate_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $connection_id = intval($_POST['connection_id']);
        $connections = new WP_Migrate_Connections();
        $result = $connections->testConnection($connection_id);
        
        wp_send_json($result);
    }
    
    /**
     * AJAX: Start migration
     */
    public function ajaxStartMigration() {
        check_ajax_referer('wp_migrate_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $migration_type = sanitize_text_field($_POST['migration_type']);
        $connection_id = intval($_POST['connection_id']);
        $options = isset($_POST['options']) ? $_POST['options'] : array();
        
        // Start migration in background
        $migration_id = $this->startBackgroundMigration($migration_type, $connection_id, $options);
        
        wp_send_json(array(
            'success' => true,
            'migration_id' => $migration_id
        ));
    }
    
    /**
     * AJAX: Get migration status
     */
    public function ajaxGetMigrationStatus() {
        check_ajax_referer('wp_migrate_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $migration_id = intval($_POST['migration_id']);
        $status = $this->getMigrationStatus($migration_id);
        
        wp_send_json($status);
    }
    
    /**
     * Start background migration
     */
    private function startBackgroundMigration($type, $connection_id, $options) {
        global $wpdb;
        
        // Record migration start
        $result = $wpdb->insert(
            $wpdb->prefix . 'wp_migrate_migrations',
            array(
                'site_from' => home_url(),
                'site_to' => $connection_id,
                'migration_type' => $type,
                'status' => 'pending',
                'started_at' => current_time('mysql'),
                'settings' => json_encode($options)
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        $migration_id = $wpdb->insert_id;
        
        // Schedule background process
        wp_schedule_single_event(time() + 1, 'wp_migrate_process_migration', array($migration_id));
        
        return $migration_id;
    }
    
    /**
     * Get migration status
     */
    private function getMigrationStatus($migration_id) {
        global $wpdb;
        
        $migration = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}wp_migrate_migrations WHERE id = %d",
                $migration_id
            )
        );
        
        if (!$migration) {
            return array('success' => false, 'error' => 'Migration not found');
        }
        
        return array(
            'success' => true,
            'status' => $migration->status,
            'progress' => $this->calculateMigrationProgress($migration),
            'message' => $migration->error_message ?: 'Migration ' . $migration->status
        );
    }
    
    /**
     * Calculate migration progress
     */
    private function calculateMigrationProgress($migration) {
        switch ($migration->status) {
            case 'pending':
                return 0;
            case 'in_progress':
                return 50; // Could be more sophisticated based on actual progress
            case 'completed':
                return 100;
            case 'failed':
                return 0;
            default:
                return 0;
        }
    }
    
    /**
     * Get recent migrations
     */
    private function getRecentMigrations($limit = 10) {
        global $wpdb;
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}wp_migrate_migrations 
                ORDER BY started_at DESC LIMIT %d",
                $limit
            )
        );
    }
}

// Initialize admin interface
if (is_admin()) {
    new WP_Migrate_Admin();
}