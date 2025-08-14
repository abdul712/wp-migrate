<?php
/**
 * Plugin Name: WP Migrate
 * Plugin URI: https://github.com/abdul712/wp-migrate
 * Description: Comprehensive WordPress migration tool that enables seamless database, media, and file synchronization between WordPress environments.
 * Version: 1.0.0
 * Author: Abdul Rahim
 * Author URI: https://github.com/abdul712
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-migrate
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.6
 * Requires PHP: 7.4
 * Network: true
 *
 * @package WPMigrate
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

// Define directory constants
define('WP_MIGRATE_ADMIN_DIR', WP_MIGRATE_PLUGIN_DIR . 'src/admin/');
define('WP_MIGRATE_API_DIR', WP_MIGRATE_PLUGIN_DIR . 'src/api/');
define('WP_MIGRATE_CORE_DIR', WP_MIGRATE_PLUGIN_DIR . 'src/core/');
define('WP_MIGRATE_CLI_DIR', WP_MIGRATE_PLUGIN_DIR . 'src/cli/');
define('WP_MIGRATE_ASSETS_DIR', WP_MIGRATE_PLUGIN_DIR . 'src/assets/');
define('WP_MIGRATE_ASSETS_URL', WP_MIGRATE_PLUGIN_URL . 'src/assets/');

/**
 * Main WP Migrate class
 */
class WP_Migrate {
    
    /**
     * Single instance of the class
     *
     * @var WP_Migrate
     */
    private static $instance = null;
    
    /**
     * Get single instance of the class
     *
     * @return WP_Migrate
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init();
    }
    
    /**
     * Initialize the plugin
     */
    private function init() {
        // Load plugin textdomain
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        
        // Initialize plugin components
        add_action('init', array($this, 'init_components'));
        
        // Register activation and deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Load autoloader
        $this->load_autoloader();
        
        // Initialize admin interface if in admin
        if (is_admin()) {
            $this->init_admin();
        }
        
        // Initialize CLI commands if WP-CLI is available
        if (defined('WP_CLI') && WP_CLI) {
            $this->init_cli();
        }
        
        // Initialize API endpoints
        $this->init_api();
    }
    
    /**
     * Load plugin textdomain for translations
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'wp-migrate',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages/'
        );
    }
    
    /**
     * Load autoloader for plugin classes
     */
    private function load_autoloader() {
        // Include Composer autoloader if it exists
        if (file_exists(WP_MIGRATE_PLUGIN_DIR . 'vendor/autoload.php')) {
            require_once WP_MIGRATE_PLUGIN_DIR . 'vendor/autoload.php';
        }
        
        // Simple autoloader for plugin classes
        spl_autoload_register(function ($class) {
            $prefix = 'WP_Migrate_';
            $len = strlen($prefix);
            
            if (strncmp($prefix, $class, $len) !== 0) {
                return;
            }
            
            $relative_class = substr($class, $len);
            $file = WP_MIGRATE_PLUGIN_DIR . 'src/' . str_replace('_', '/', strtolower($relative_class)) . '.php';
            
            if (file_exists($file)) {
                require $file;
            }
        });
    }
    
    /**
     * Initialize plugin components
     */
    public function init_components() {
        // Core components will be loaded here
        do_action('wp_migrate_init');
    }
    
    /**
     * Initialize admin interface
     */
    private function init_admin() {
        // Admin interface will be initialized here
        if (class_exists('WP_Migrate_Admin')) {
            new WP_Migrate_Admin();
        }
    }
    
    /**
     * Initialize CLI commands
     */
    private function init_cli() {
        // WP-CLI commands will be registered here
        if (class_exists('WP_Migrate_CLI')) {
            WP_CLI::add_command('migrate', 'WP_Migrate_CLI');
        }
    }
    
    /**
     * Initialize API endpoints
     */
    private function init_api() {
        // REST API endpoints will be registered here
        add_action('rest_api_init', array($this, 'register_api_routes'));
    }
    
    /**
     * Register REST API routes
     */
    public function register_api_routes() {
        // API routes will be registered here
        if (class_exists('WP_Migrate_API')) {
            $api = new WP_Migrate_API();
            $api->register_routes();
        }
    }
    
    /**
     * Plugin activation hook
     */
    public function activate() {
        // Create database tables
        $this->create_tables();
        
        // Set default options
        $this->set_default_options();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Schedule cleanup cron job
        if (!wp_next_scheduled('wp_migrate_cleanup')) {
            wp_schedule_event(time(), 'daily', 'wp_migrate_cleanup');
        }
    }
    
    /**
     * Plugin deactivation hook
     */
    public function deactivate() {
        // Clear scheduled cron jobs
        wp_clear_scheduled_hook('wp_migrate_cleanup');
        
        // Clean up temporary files
        $this->cleanup_temp_files();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Create database tables
     */
    private function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Migration history table
        $table_name = $wpdb->prefix . 'wp_migrate_history';
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            migration_type varchar(50) NOT NULL,
            source_site varchar(255) NOT NULL,
            target_site varchar(255) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            started_at datetime DEFAULT CURRENT_TIMESTAMP,
            completed_at datetime NULL,
            log_data longtext,
            metadata longtext,
            PRIMARY KEY (id),
            KEY status (status),
            KEY migration_type (migration_type)
        ) $charset_collate;";
        
        // Connections table
        $connections_table = $wpdb->prefix . 'wp_migrate_connections';
        $sql .= "CREATE TABLE $connections_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            site_url varchar(255) NOT NULL,
            api_key varchar(64) NOT NULL,
            secret_key varchar(64) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            last_used datetime NULL,
            metadata longtext,
            PRIMARY KEY (id),
            UNIQUE KEY name (name),
            KEY status (status)
        ) $charset_collate;";
        
        // Backups table
        $backups_table = $wpdb->prefix . 'wp_migrate_backups';
        $sql .= "CREATE TABLE $backups_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            backup_name varchar(100) NOT NULL,
            backup_type varchar(50) NOT NULL,
            file_path varchar(500) NOT NULL,
            file_size bigint(20) NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            expires_at datetime NULL,
            metadata longtext,
            PRIMARY KEY (id),
            KEY backup_type (backup_type),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Update database version
        update_option('wp_migrate_db_version', WP_MIGRATE_VERSION);
    }
    
    /**
     * Set default plugin options
     */
    private function set_default_options() {
        $default_options = array(
            'enable_backups' => true,
            'backup_retention_days' => 30,
            'chunk_size' => 1000,
            'timeout' => 300,
            'enable_logging' => true,
            'log_level' => 'info',
            'enable_ssl_verify' => true,
            'max_file_size' => 50 * 1024 * 1024, // 50MB
        );
        
        foreach ($default_options as $option => $value) {
            if (false === get_option("wp_migrate_{$option}")) {
                add_option("wp_migrate_{$option}", $value);
            }
        }
    }
    
    /**
     * Clean up temporary files
     */
    private function cleanup_temp_files() {
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/wp-migrate-temp/';
        
        if (is_dir($temp_dir)) {
            $files = glob($temp_dir . '*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($temp_dir);
        }
    }
    
    /**
     * Get plugin information
     *
     * @return array
     */
    public static function get_plugin_info() {
        return array(
            'name' => 'WP Migrate',
            'version' => WP_MIGRATE_VERSION,
            'file' => WP_MIGRATE_PLUGIN_FILE,
            'dir' => WP_MIGRATE_PLUGIN_DIR,
            'url' => WP_MIGRATE_PLUGIN_URL,
            'basename' => WP_MIGRATE_PLUGIN_BASENAME,
        );
    }
}

// Initialize the plugin
WP_Migrate::get_instance();

// Add cleanup cron job
add_action('wp_migrate_cleanup', function() {
    $plugin = WP_Migrate::get_instance();
    $plugin->cleanup_temp_files();
});