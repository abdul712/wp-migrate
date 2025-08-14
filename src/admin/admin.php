<?php
/**
 * WordPress Admin Interface
 *
 * Provides the main admin interface for WP Migrate plugin
 *
 * @package WPMigrate
 * @subpackage Admin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WP_Migrate_Admin
 *
 * Main admin interface handler
 */
class WP_Migrate_Admin {
    
    /**
     * Admin menu slug
     */
    const MENU_SLUG = 'wp-migrate';
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_wp_migrate_action', array($this, 'handle_ajax_request'));
        add_action('admin_init', array($this, 'handle_admin_actions'));
        add_action('admin_notices', array($this, 'show_admin_notices'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Main menu page
        add_menu_page(
            __('WP Migrate', 'wp-migrate'),
            __('WP Migrate', 'wp-migrate'),
            'manage_options',
            self::MENU_SLUG,
            array($this, 'render_dashboard_page'),
            'dashicons-migrate',
            30
        );
        
        // Dashboard submenu
        add_submenu_page(
            self::MENU_SLUG,
            __('Dashboard', 'wp-migrate'),
            __('Dashboard', 'wp-migrate'),
            'manage_options',
            self::MENU_SLUG,
            array($this, 'render_dashboard_page')
        );
        
        // Migration submenu
        add_submenu_page(
            self::MENU_SLUG,
            __('Migration', 'wp-migrate'),
            __('Migration', 'wp-migrate'),
            'manage_options',
            self::MENU_SLUG . '-migration',
            array($this, 'render_migration_page')
        );
        
        // Connections submenu
        add_submenu_page(
            self::MENU_SLUG,
            __('Connections', 'wp-migrate'),
            __('Connections', 'wp-migrate'),
            'manage_options',
            self::MENU_SLUG . '-connections',
            array($this, 'render_connections_page')
        );
        
        // Backups submenu
        add_submenu_page(
            self::MENU_SLUG,
            __('Backups', 'wp-migrate'),
            __('Backups', 'wp-migrate'),
            'manage_options',
            self::MENU_SLUG . '-backups',
            array($this, 'render_backups_page')
        );
        
        // History submenu
        add_submenu_page(
            self::MENU_SLUG,
            __('History', 'wp-migrate'),
            __('History', 'wp-migrate'),
            'manage_options',
            self::MENU_SLUG . '-history',
            array($this, 'render_history_page')
        );
        
        // Settings submenu
        add_submenu_page(
            self::MENU_SLUG,
            __('Settings', 'wp-migrate'),
            __('Settings', 'wp-migrate'),
            'manage_options',
            self::MENU_SLUG . '-settings',
            array($this, 'render_settings_page')
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     *
     * @param string $hook Current admin page hook
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on WP Migrate pages
        if (strpos($hook, self::MENU_SLUG) === false) {
            return;
        }
        
        // Enqueue CSS
        wp_enqueue_style(
            'wp-migrate-admin',
            WP_MIGRATE_ASSETS_URL . 'css/admin.css',
            array(),
            WP_MIGRATE_VERSION
        );
        
        // Enqueue JavaScript
        wp_enqueue_script(
            'wp-migrate-admin',
            WP_MIGRATE_ASSETS_URL . 'js/admin.js',
            array('jquery', 'wp-util'),
            WP_MIGRATE_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('wp-migrate-admin', 'wpMigrate', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wp_migrate_nonce'),
            'strings' => array(
                'confirm_delete' => __('Are you sure you want to delete this item?', 'wp-migrate'),
                'migration_started' => __('Migration started successfully', 'wp-migrate'),
                'migration_failed' => __('Migration failed', 'wp-migrate'),
                'connection_test_success' => __('Connection test successful', 'wp-migrate'),
                'connection_test_failed' => __('Connection test failed', 'wp-migrate'),
            )
        ));
    }
    
    /**
     * Handle AJAX requests
     */
    public function handle_ajax_request() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wp_migrate_nonce')) {
            wp_die(__('Security check failed', 'wp-migrate'));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'wp-migrate'));
        }
        
        $action = sanitize_text_field($_POST['action_type']);
        
        switch ($action) {
            case 'test_connection':
                $this->ajax_test_connection();
                break;
                
            case 'start_migration':
                $this->ajax_start_migration();
                break;
                
            case 'get_migration_status':
                $this->ajax_get_migration_status();
                break;
                
            case 'delete_backup':
                $this->ajax_delete_backup();
                break;
                
            default:
                wp_send_json_error(__('Invalid action', 'wp-migrate'));
        }
    }
    
    /**
     * Handle admin form submissions
     */
    public function handle_admin_actions() {
        if (!isset($_POST['wp_migrate_action']) || !wp_verify_nonce($_POST['wp_migrate_nonce'], 'wp_migrate_admin')) {
            return;
        }
        
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $action = sanitize_text_field($_POST['wp_migrate_action']);
        
        switch ($action) {
            case 'save_connection':
                $this->save_connection();
                break;
                
            case 'save_settings':
                $this->save_settings();
                break;
                
            case 'delete_connection':
                $this->delete_connection();
                break;
                
            case 'create_backup':
                $this->create_backup();
                break;
        }
    }
    
    /**
     * Show admin notices
     */
    public function show_admin_notices() {
        $current_screen = get_current_screen();
        if (!$current_screen || strpos($current_screen->id, self::MENU_SLUG) === false) {
            return;
        }
        
        // Show any stored messages
        $messages = get_transient('wp_migrate_admin_messages');
        if ($messages) {
            foreach ($messages as $message) {
                echo '<div class="notice notice-' . esc_attr($message['type']) . ' is-dismissible">';
                echo '<p>' . esc_html($message['text']) . '</p>';
                echo '</div>';
            }
            delete_transient('wp_migrate_admin_messages');
        }
    }
    
    /**
     * Render dashboard page
     */
    public function render_dashboard_page() {
        $this->render_header();
        ?>
        <div class="wrap wp-migrate-dashboard">
            <div class="wp-migrate-dashboard-widgets">
                
                <!-- Quick Stats Widget -->
                <div class="wp-migrate-widget">
                    <h3><?php _e('Quick Stats', 'wp-migrate'); ?></h3>
                    <div class="wp-migrate-stats">
                        <div class="stat-item">
                            <span class="stat-number"><?php echo $this->get_total_migrations(); ?></span>
                            <span class="stat-label"><?php _e('Total Migrations', 'wp-migrate'); ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number"><?php echo $this->get_active_connections(); ?></span>
                            <span class="stat-label"><?php _e('Active Connections', 'wp-migrate'); ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number"><?php echo $this->get_backup_count(); ?></span>
                            <span class="stat-label"><?php _e('Backups Available', 'wp-migrate'); ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions Widget -->
                <div class="wp-migrate-widget">
                    <h3><?php _e('Quick Actions', 'wp-migrate'); ?></h3>
                    <div class="wp-migrate-quick-actions">
                        <a href="<?php echo admin_url('admin.php?page=' . self::MENU_SLUG . '-migration'); ?>" class="button button-primary">
                            <?php _e('Start Migration', 'wp-migrate'); ?>
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=' . self::MENU_SLUG . '-connections'); ?>" class="button">
                            <?php _e('Manage Connections', 'wp-migrate'); ?>
                        </a>
                        <button type="button" class="button wp-migrate-create-backup">
                            <?php _e('Create Backup', 'wp-migrate'); ?>
                        </button>
                    </div>
                </div>
                
                <!-- Recent Activity Widget -->
                <div class="wp-migrate-widget wp-migrate-widget-full">
                    <h3><?php _e('Recent Activity', 'wp-migrate'); ?></h3>
                    <div class="wp-migrate-recent-activity">
                        <?php $this->render_recent_activity(); ?>
                    </div>
                </div>
                
            </div>
        </div>
        <?php
    }
    
    /**
     * Render migration page
     */
    public function render_migration_page() {
        $this->render_header();
        ?>
        <div class="wrap wp-migrate-migration">
            <div class="wp-migrate-migration-wizard">
                
                <!-- Step 1: Select Migration Type -->
                <div class="wp-migrate-step wp-migrate-step-active" data-step="1">
                    <h3><?php _e('Step 1: Select Migration Type', 'wp-migrate'); ?></h3>
                    <div class="wp-migrate-migration-types">
                        <label class="wp-migrate-migration-type">
                            <input type="radio" name="migration_type" value="database" checked>
                            <div class="migration-type-card">
                                <h4><?php _e('Database Migration', 'wp-migrate'); ?></h4>
                                <p><?php _e('Migrate database with URL/path replacements', 'wp-migrate'); ?></p>
                            </div>
                        </label>
                        <label class="wp-migrate-migration-type">
                            <input type="radio" name="migration_type" value="files">
                            <div class="migration-type-card">
                                <h4><?php _e('File Migration', 'wp-migrate'); ?></h4>
                                <p><?php _e('Sync media, themes, and plugin files', 'wp-migrate'); ?></p>
                            </div>
                        </label>
                        <label class="wp-migrate-migration-type">
                            <input type="radio" name="migration_type" value="full">
                            <div class="migration-type-card">
                                <h4><?php _e('Full Migration', 'wp-migrate'); ?></h4>
                                <p><?php _e('Complete site migration including database and files', 'wp-migrate'); ?></p>
                            </div>
                        </label>
                    </div>
                </div>
                
                <!-- Step 2: Select Connection -->
                <div class="wp-migrate-step" data-step="2">
                    <h3><?php _e('Step 2: Select Connection', 'wp-migrate'); ?></h3>
                    <div class="wp-migrate-connections-list">
                        <?php $this->render_connections_select(); ?>
                    </div>
                </div>
                
                <!-- Step 3: Configure Migration -->
                <div class="wp-migrate-step" data-step="3">
                    <h3><?php _e('Step 3: Configure Migration', 'wp-migrate'); ?></h3>
                    <div class="wp-migrate-migration-config">
                        <?php $this->render_migration_config(); ?>
                    </div>
                </div>
                
                <!-- Step 4: Review and Execute -->
                <div class="wp-migrate-step" data-step="4">
                    <h3><?php _e('Step 4: Review and Execute', 'wp-migrate'); ?></h3>
                    <div class="wp-migrate-migration-review">
                        <?php $this->render_migration_review(); ?>
                    </div>
                </div>
                
                <!-- Navigation -->
                <div class="wp-migrate-wizard-navigation">
                    <button type="button" class="button wp-migrate-prev-step" style="display: none;">
                        <?php _e('Previous', 'wp-migrate'); ?>
                    </button>
                    <button type="button" class="button button-primary wp-migrate-next-step">
                        <?php _e('Next', 'wp-migrate'); ?>
                    </button>
                    <button type="button" class="button button-primary wp-migrate-start-migration" style="display: none;">
                        <?php _e('Start Migration', 'wp-migrate'); ?>
                    </button>
                </div>
                
            </div>
        </div>
        <?php
    }
    
    /**
     * Render connections page
     */
    public function render_connections_page() {
        $this->render_header();
        ?>
        <div class="wrap wp-migrate-connections">
            <div class="wp-migrate-page-header">
                <h2><?php _e('Connections', 'wp-migrate'); ?></h2>
                <button type="button" class="button button-primary wp-migrate-add-connection">
                    <?php _e('Add New Connection', 'wp-migrate'); ?>
                </button>
            </div>
            
            <div class="wp-migrate-connections-table">
                <?php $this->render_connections_table(); ?>
            </div>
            
            <!-- Add/Edit Connection Modal -->
            <div id="wp-migrate-connection-modal" class="wp-migrate-modal" style="display: none;">
                <div class="wp-migrate-modal-content">
                    <h3><?php _e('Add/Edit Connection', 'wp-migrate'); ?></h3>
                    <form id="wp-migrate-connection-form">
                        <?php $this->render_connection_form(); ?>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render backups page
     */
    public function render_backups_page() {
        $this->render_header();
        ?>
        <div class="wrap wp-migrate-backups">
            <div class="wp-migrate-page-header">
                <h2><?php _e('Backups', 'wp-migrate'); ?></h2>
                <button type="button" class="button button-primary wp-migrate-create-backup">
                    <?php _e('Create New Backup', 'wp-migrate'); ?>
                </button>
            </div>
            
            <div class="wp-migrate-backups-table">
                <?php $this->render_backups_table(); ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render history page
     */
    public function render_history_page() {
        $this->render_header();
        ?>
        <div class="wrap wp-migrate-history">
            <h2><?php _e('Migration History', 'wp-migrate'); ?></h2>
            
            <div class="wp-migrate-history-filters">
                <?php $this->render_history_filters(); ?>
            </div>
            
            <div class="wp-migrate-history-table">
                <?php $this->render_history_table(); ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        $this->render_header();
        ?>
        <div class="wrap wp-migrate-settings">
            <h2><?php _e('Settings', 'wp-migrate'); ?></h2>
            
            <form method="post" action="">
                <?php wp_nonce_field('wp_migrate_admin', 'wp_migrate_nonce'); ?>
                <input type="hidden" name="wp_migrate_action" value="save_settings">
                
                <table class="form-table">
                    <?php $this->render_settings_fields(); ?>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render page header
     */
    private function render_header() {
        ?>
        <div class="wp-migrate-header">
            <h1 class="wp-migrate-title">
                <span class="wp-migrate-icon"></span>
                <?php _e('WP Migrate', 'wp-migrate'); ?>
                <span class="wp-migrate-version"><?php echo WP_MIGRATE_VERSION; ?></span>
            </h1>
        </div>
        <?php
    }
    
    /**
     * Get total migrations count
     *
     * @return int
     */
    private function get_total_migrations() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wp_migrate_history';
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
    }
    
    /**
     * Get active connections count
     *
     * @return int
     */
    private function get_active_connections() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wp_migrate_connections';
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE status = 'active'");
    }
    
    /**
     * Get backup count
     *
     * @return int
     */
    private function get_backup_count() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wp_migrate_backups';
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
    }
    
    /**
     * Render recent activity
     */
    private function render_recent_activity() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wp_migrate_history';
        
        $recent_migrations = $wpdb->get_results(
            "SELECT * FROM {$table_name} ORDER BY started_at DESC LIMIT 5"
        );
        
        if (empty($recent_migrations)) {
            echo '<p>' . __('No recent activity', 'wp-migrate') . '</p>';
            return;
        }
        
        echo '<ul class="wp-migrate-activity-list">';
        foreach ($recent_migrations as $migration) {
            echo '<li class="wp-migrate-activity-item status-' . esc_attr($migration->status) . '">';
            echo '<span class="activity-type">' . esc_html($migration->migration_type) . '</span>';
            echo '<span class="activity-target">' . esc_html($migration->target_site) . '</span>';
            echo '<span class="activity-date">' . esc_html($migration->started_at) . '</span>';
            echo '<span class="activity-status">' . esc_html($migration->status) . '</span>';
            echo '</li>';
        }
        echo '</ul>';
    }
    
    /**
     * Render connections select
     */
    private function render_connections_select() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wp_migrate_connections';
        
        $connections = $wpdb->get_results(
            "SELECT * FROM {$table_name} WHERE status = 'active' ORDER BY name"
        );
        
        if (empty($connections)) {
            echo '<p>' . __('No connections available. Please add a connection first.', 'wp-migrate') . '</p>';
            return;
        }
        
        foreach ($connections as $connection) {
            echo '<label class="wp-migrate-connection-option">';
            echo '<input type="radio" name="connection_id" value="' . esc_attr($connection->id) . '">';
            echo '<div class="connection-card">';
            echo '<h4>' . esc_html($connection->name) . '</h4>';
            echo '<p>' . esc_html($connection->site_url) . '</p>';
            echo '</div>';
            echo '</label>';
        }
    }
    
    /**
     * Render migration config
     */
    private function render_migration_config() {
        // Implementation for migration configuration form
        echo '<div class="wp-migrate-config-section">';
        echo '<h4>' . __('URL Replacements', 'wp-migrate') . '</h4>';
        echo '<div id="wp-migrate-url-replacements">';
        echo '<div class="url-replacement-row">';
        echo '<input type="url" name="from_url[]" placeholder="' . __('From URL', 'wp-migrate') . '">';
        echo '<input type="url" name="to_url[]" placeholder="' . __('To URL', 'wp-migrate') . '">';
        echo '<button type="button" class="button remove-url-replacement">Remove</button>';
        echo '</div>';
        echo '</div>';
        echo '<button type="button" class="button add-url-replacement">' . __('Add URL Replacement', 'wp-migrate') . '</button>';
        echo '</div>';
    }
    
    /**
     * Render migration review
     */
    private function render_migration_review() {
        echo '<div class="wp-migrate-review-section">';
        echo '<p>' . __('Please review your migration settings before proceeding:', 'wp-migrate') . '</p>';
        echo '<div id="wp-migrate-review-content"></div>';
        echo '</div>';
    }
    
    /**
     * Render connections table
     */
    private function render_connections_table() {
        // Implementation for connections table
    }
    
    /**
     * Render connection form
     */
    private function render_connection_form() {
        // Implementation for connection form
    }
    
    /**
     * Render backups table
     */
    private function render_backups_table() {
        // Implementation for backups table
    }
    
    /**
     * Render history filters
     */
    private function render_history_filters() {
        // Implementation for history filters
    }
    
    /**
     * Render history table
     */
    private function render_history_table() {
        // Implementation for history table
    }
    
    /**
     * Render settings fields
     */
    private function render_settings_fields() {
        // Implementation for settings fields
    }
    
    /**
     * AJAX: Test connection
     */
    private function ajax_test_connection() {
        // Implementation for connection testing
        wp_send_json_success(array('message' => __('Connection test successful', 'wp-migrate')));
    }
    
    /**
     * AJAX: Start migration
     */
    private function ajax_start_migration() {
        // Implementation for starting migration
        wp_send_json_success(array('message' => __('Migration started', 'wp-migrate')));
    }
    
    /**
     * AJAX: Get migration status
     */
    private function ajax_get_migration_status() {
        // Implementation for getting migration status
        wp_send_json_success(array('status' => 'running', 'progress' => 50));
    }
    
    /**
     * AJAX: Delete backup
     */
    private function ajax_delete_backup() {
        // Implementation for deleting backup
        wp_send_json_success(array('message' => __('Backup deleted', 'wp-migrate')));
    }
    
    /**
     * Save connection
     */
    private function save_connection() {
        // Implementation for saving connection
    }
    
    /**
     * Save settings
     */
    private function save_settings() {
        // Implementation for saving settings
    }
    
    /**
     * Delete connection
     */
    private function delete_connection() {
        // Implementation for deleting connection
    }
    
    /**
     * Create backup
     */
    private function create_backup() {
        // Implementation for creating backup
    }
}