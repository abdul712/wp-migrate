<?php
/**
 * Base Test Case for WP Migrate Tests
 *
 * @package WPMigrate
 * @subpackage Tests
 */

/**
 * Class WP_Migrate_Test_Case
 *
 * Base test case with common functionality for WP Migrate tests
 */
class WP_Migrate_Test_Case extends WP_UnitTestCase {
    
    /**
     * Set up test case
     */
    public function setUp(): void {
        parent::setUp();
        
        // Clear any existing WP Migrate data
        $this->clean_wp_migrate_data();
        
        // Create test tables
        $this->create_test_tables();
    }
    
    /**
     * Tear down test case
     */
    public function tearDown(): void {
        // Clean up test data
        $this->clean_wp_migrate_data();
        
        parent::tearDown();
    }
    
    /**
     * Clean WP Migrate data
     */
    protected function clean_wp_migrate_data() {
        global $wpdb;
        
        $tables = array(
            $wpdb->prefix . 'wp_migrate_history',
            $wpdb->prefix . 'wp_migrate_connections',
            $wpdb->prefix . 'wp_migrate_backups',
        );
        
        foreach ($tables as $table) {
            $wpdb->query("DELETE FROM {$table}");
        }
        
        // Clean up options
        $options = array(
            'wp_migrate_enable_backups',
            'wp_migrate_backup_retention_days',
            'wp_migrate_chunk_size',
            'wp_migrate_timeout',
            'wp_migrate_enable_logging',
            'wp_migrate_log_level',
            'wp_migrate_enable_ssl_verify',
            'wp_migrate_max_file_size',
            'wp_migrate_db_version',
        );
        
        foreach ($options as $option) {
            delete_option($option);
        }
        
        // Clean up transients
        delete_transient('wp_migrate_admin_messages');
    }
    
    /**
     * Create test tables
     */
    protected function create_test_tables() {
        $plugin = WP_Migrate::get_instance();
        $plugin->activate();
    }
    
    /**
     * Create test connection
     *
     * @param array $args Connection arguments
     * @return int Connection ID
     */
    protected function create_test_connection($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'name' => 'Test Connection',
            'site_url' => 'https://example.com',
            'api_key' => 'test_api_key',
            'secret_key' => 'test_secret_key',
            'status' => 'active',
            'created_at' => current_time('mysql'),
            'metadata' => json_encode(array()),
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $table_name = $wpdb->prefix . 'wp_migrate_connections';
        $wpdb->insert($table_name, $args);
        
        return $wpdb->insert_id;
    }
    
    /**
     * Create test migration history entry
     *
     * @param array $args Migration arguments
     * @return int Migration ID
     */
    protected function create_test_migration($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'migration_type' => 'database',
            'source_site' => 'local',
            'target_site' => 'remote',
            'status' => 'completed',
            'started_at' => current_time('mysql'),
            'completed_at' => current_time('mysql'),
            'log_data' => '',
            'metadata' => json_encode(array()),
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $table_name = $wpdb->prefix . 'wp_migrate_history';
        $wpdb->insert($table_name, $args);
        
        return $wpdb->insert_id;
    }
    
    /**
     * Create test backup entry
     *
     * @param array $args Backup arguments
     * @return int Backup ID
     */
    protected function create_test_backup($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'backup_name' => 'test-backup',
            'backup_type' => 'database',
            'file_path' => '/tmp/test-backup.sql',
            'file_size' => 1024,
            'created_at' => current_time('mysql'),
            'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
            'metadata' => json_encode(array()),
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $table_name = $wpdb->prefix . 'wp_migrate_backups';
        $wpdb->insert($table_name, $args);
        
        return $wpdb->insert_id;
    }
    
    /**
     * Create temporary test file
     *
     * @param string $content File content
     * @param string $extension File extension
     * @return string File path
     */
    protected function create_temp_file($content = '', $extension = 'txt') {
        $temp_file = tempnam(sys_get_temp_dir(), 'wp_migrate_test_') . '.' . $extension;
        file_put_contents($temp_file, $content);
        return $temp_file;
    }
    
    /**
     * Assert file exists and has content
     *
     * @param string $file_path File path
     * @param string $expected_content Expected content (optional)
     */
    protected function assertFileExistsAndHasContent($file_path, $expected_content = null) {
        $this->assertFileExists($file_path);
        
        if ($expected_content !== null) {
            $actual_content = file_get_contents($file_path);
            $this->assertEquals($expected_content, $actual_content);
        }
    }
    
    /**
     * Assert database table exists
     *
     * @param string $table_name Table name
     */
    protected function assertTableExists($table_name) {
        global $wpdb;
        
        $table_exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
                DB_NAME,
                $table_name
            )
        );
        
        $this->assertEquals(1, $table_exists, "Table {$table_name} should exist");
    }
    
    /**
     * Assert option has expected value
     *
     * @param string $option_name Option name
     * @param mixed $expected_value Expected value
     */
    protected function assertOptionValue($option_name, $expected_value) {
        $actual_value = get_option($option_name);
        $this->assertEquals($expected_value, $actual_value);
    }
    
    /**
     * Mock WordPress database
     *
     * @return wpdb Mock database object
     */
    protected function mock_wpdb() {
        $mock = $this->createMock('wpdb');
        $mock->prefix = 'wp_';
        
        return $mock;
    }
    
    /**
     * Create test serialized data
     *
     * @return array Test data with serialized values
     */
    protected function create_test_serialized_data() {
        return array(
            'simple_string' => 'Hello World',
            'serialized_array' => serialize(array('key1' => 'value1', 'key2' => 'value2')),
            'serialized_object' => serialize((object) array('prop1' => 'value1', 'prop2' => 'value2')),
            'nested_serialized' => serialize(array(
                'level1' => array(
                    'level2' => array(
                        'url' => 'https://old-site.com/path',
                        'data' => serialize(array('nested' => 'value'))
                    )
                )
            )),
            'widget_data' => serialize(array(
                'text-1' => array(
                    'title' => 'Widget Title',
                    'text' => 'Visit us at https://old-site.com'
                )
            )),
        );
    }
}