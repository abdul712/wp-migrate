<?php
/**
 * Plugin Name: WP Migrate
 * Plugin URI: https://github.com/abdul712/wp-migrate
 * Description: A comprehensive WordPress migration tool that enables seamless database, media, and file synchronization between WordPress environments.
 * Version: 1.0.0
 * Author: Abdul Rahim
 * Author URI: https://github.com/abdul712
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-migrate
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: true
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WP_MIGRATE_VERSION', '1.0.0');
define('WP_MIGRATE_PLUGIN_FILE', __FILE__);
define('WP_MIGRATE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WP_MIGRATE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WP_MIGRATE_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main WP Migrate Plugin Class
 */
class WP_Migrate {
    
    /**
     * Single instance of the class
     */
    private static $instance = null;
    
    /**
     * Get singleton instance
     */
    public static function getInstance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->initHooks();
        $this->loadTextDomain();
        $this->loadComponents();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function initHooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        register_uninstall_hook(__FILE__, array('WP_Migrate', 'uninstall'));
        
        add_action('init', array($this, 'init'));
        add_action('admin_init', array($this, 'adminInit'));
        add_action('wp_loaded', array($this, 'wpLoaded'));
    }
    
    /**
     * Load text domain for translations
     */
    private function loadTextDomain() {
        add_action('plugins_loaded', function() {
            load_plugin_textdomain('wp-migrate', false, dirname(WP_MIGRATE_PLUGIN_BASENAME) . '/languages/');
        });
    }
    
    /**
     * Load plugin components
     */
    private function loadComponents() {
        $this->loadAutoloader();
        $this->loadCore();
        $this->loadAdmin();
        $this->loadCLI();
        $this->loadAPI();
    }
    
    /**
     * Load autoloader
     */
    private function loadAutoloader() {
        spl_autoload_register(function($class) {
            if (strpos($class, 'WP_Migrate_') === 0) {
                $class_file = str_replace('WP_Migrate_', '', $class);
                $class_file = str_replace('_', '-', strtolower($class_file));
                $file_path = WP_MIGRATE_PLUGIN_DIR . 'src/' . $class_file . '.php';
                
                if (file_exists($file_path)) {
                    require_once $file_path;
                }
            }
        });
    }
    
    /**
     * Load core components
     */
    private function loadCore() {
        require_once WP_MIGRATE_PLUGIN_DIR . 'src/core/database.php';
        require_once WP_MIGRATE_PLUGIN_DIR . 'src/core/serialization.php';
        require_once WP_MIGRATE_PLUGIN_DIR . 'src/core/connections.php';
        require_once WP_MIGRATE_PLUGIN_DIR . 'src/core/backup.php';
        require_once WP_MIGRATE_PLUGIN_DIR . 'src/core/media.php';
        require_once WP_MIGRATE_PLUGIN_DIR . 'src/core/files.php';
    }
    
    /**
     * Load admin components
     */
    private function loadAdmin() {
        if (is_admin()) {
            require_once WP_MIGRATE_PLUGIN_DIR . 'src/admin/admin.php';
            require_once WP_MIGRATE_PLUGIN_DIR . 'src/admin/wizard.php';
            require_once WP_MIGRATE_PLUGIN_DIR . 'src/admin/settings.php';
            require_once WP_MIGRATE_PLUGIN_DIR . 'src/admin/history.php';
        }
    }
    
    /**
     * Load WP-CLI components
     */
    private function loadCLI() {
        if (defined('WP_CLI') && WP_CLI) {
            require_once WP_MIGRATE_PLUGIN_DIR . 'src/cli/cli.php';
        }
    }
    
    /**
     * Load API components
     */
    private function loadAPI() {
        require_once WP_MIGRATE_PLUGIN_DIR . 'src/api/endpoints.php';
        require_once WP_MIGRATE_PLUGIN_DIR . 'src/api/authentication.php';
    }
    
    /**
     * Plugin initialization
     */
    public function init() {
        // Initialize components
        do_action('wp_migrate_init');
    }
    
    /**
     * Admin initialization
     */
    public function adminInit() {
        // Initialize admin components
        do_action('wp_migrate_admin_init');
    }
    
    /**
     * WordPress loaded
     */
    public function wpLoaded() {
        // All WordPress components loaded
        do_action('wp_migrate_wp_loaded');
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables
        $this->createTables();
        
        // Set default options
        $this->setDefaultOptions();
        
        // Schedule cleanup
        wp_schedule_event(time(), 'daily', 'wp_migrate_cleanup');
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        do_action('wp_migrate_activated');
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook('wp_migrate_cleanup');
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        do_action('wp_migrate_deactivated');
    }
    
    /**
     * Plugin uninstallation
     */
    public static function uninstall() {
        // Remove database tables
        self::dropTables();
        
        // Remove options
        self::removeOptions();
        
        // Clean up files
        self::cleanupFiles();
        
        do_action('wp_migrate_uninstalled');
    }
    
    /**
     * Create database tables
     */
    private function createTables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Migration history table
        $table_migrations = $wpdb->prefix . 'wp_migrate_migrations';
        $sql_migrations = "CREATE TABLE $table_migrations (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            site_from varchar(255) NOT NULL,
            site_to varchar(255) NOT NULL,
            migration_type varchar(50) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            started_at datetime NOT NULL,
            completed_at datetime NULL,
            file_path text NULL,
            backup_path text NULL,
            settings longtext NULL,
            error_message text NULL,
            PRIMARY KEY (id),
            KEY status (status),
            KEY migration_type (migration_type),
            KEY started_at (started_at)
        ) $charset_collate;";
        
        // Connections table
        $table_connections = $wpdb->prefix . 'wp_migrate_connections';
        $sql_connections = "CREATE TABLE $table_connections (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            url varchar(500) NOT NULL,
            api_key varchar(255) NOT NULL,
            description text NULL,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            last_tested_at datetime NULL,
            test_status varchar(20) NULL,
            PRIMARY KEY (id),
            UNIQUE KEY name (name),
            KEY is_active (is_active)
        ) $charset_collate;";
        
        // Backups table
        $table_backups = $wpdb->prefix . 'wp_migrate_backups';
        $sql_backups = "CREATE TABLE $table_backups (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            file_path text NOT NULL,
            backup_type varchar(50) NOT NULL,
            file_size bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            expires_at datetime NULL,
            description text NULL,
            PRIMARY KEY (id),
            KEY backup_type (backup_type),
            KEY created_at (created_at),
            KEY expires_at (expires_at)
        ) $charset_collate;";
        
        // Settings table
        $table_settings = $wpdb->prefix . 'wp_migrate_settings';
        $sql_settings = "CREATE TABLE $table_settings (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            settings longtext NOT NULL,
            is_default tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY name (name),
            KEY is_default (is_default)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_migrations);
        dbDelta($sql_connections);
        dbDelta($sql_backups);
        dbDelta($sql_settings);
        
        // Update database version
        update_option('wp_migrate_db_version', WP_MIGRATE_VERSION);
    }
    
    /**
     * Set default options
     */
    private function setDefaultOptions() {
        $default_options = array(
            'wp_migrate_max_execution_time' => 300,
            'wp_migrate_memory_limit' => '512M',
            'wp_migrate_chunk_size' => 1000,
            'wp_migrate_backup_retention' => 30,
            'wp_migrate_enable_logging' => true,
            'wp_migrate_log_level' => 'info',
            'wp_migrate_secure_connections' => true,
        );
        
        foreach ($default_options as $option => $value) {
            add_option($option, $value);
        }
    }
    
    /**
     * Drop database tables
     */
    private static function dropTables() {
        global $wpdb;
        
        $tables = array(
            $wpdb->prefix . 'wp_migrate_migrations',
            $wpdb->prefix . 'wp_migrate_connections',
            $wpdb->prefix . 'wp_migrate_backups',
            $wpdb->prefix . 'wp_migrate_settings'
        );
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
        
        delete_option('wp_migrate_db_version');
    }
    
    /**
     * Remove plugin options
     */
    private static function removeOptions() {
        $options = array(
            'wp_migrate_max_execution_time',
            'wp_migrate_memory_limit',
            'wp_migrate_chunk_size',
            'wp_migrate_backup_retention',
            'wp_migrate_enable_logging',
            'wp_migrate_log_level',
            'wp_migrate_secure_connections',
        );
        
        foreach ($options as $option) {
            delete_option($option);
        }
    }
    
    /**
     * Clean up plugin files
     */
    private static function cleanupFiles() {
        $upload_dir = wp_upload_dir();
        $wp_migrate_dir = $upload_dir['basedir'] . '/wp-migrate/';
        
        if (is_dir($wp_migrate_dir)) {
            self::deleteDirectory($wp_migrate_dir);
        }
    }
    
    /**
     * Recursively delete directory
     */
    private static function deleteDirectory($dir) {
        if (!is_dir($dir)) {
            return false;
        }
        
        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            is_dir($path) ? self::deleteDirectory($path) : unlink($path);
        }
        
        return rmdir($dir);
    }
}

// Initialize the plugin
WP_Migrate::getInstance();