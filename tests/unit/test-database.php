<?php
/**
 * Database Migration Tests
 *
 * @package WPMigrate
 * @subpackage Tests
 */

/**
 * Class Test_WP_Migrate_Database
 *
 * Test database migration functionality
 */
class Test_WP_Migrate_Database extends WP_Migrate_Test_Case {
    
    /**
     * Test database instance
     *
     * @var WP_Migrate_Core_Database
     */
    private $database;
    
    /**
     * Set up test case
     */
    public function setUp(): void {
        parent::setUp();
        
        $this->database = new WP_Migrate_Core_Database();
    }
    
    /**
     * Test database export
     */
    public function test_export_database() {
        $temp_file = $this->create_temp_file('', 'sql');
        
        $result = $this->database->export_database($temp_file);
        
        $this->assertTrue($result);
        $this->assertFileExists($temp_file);
        
        // Check file has content
        $content = file_get_contents($temp_file);
        $this->assertNotEmpty($content);
        $this->assertStringContainsString('WP Migrate Database Export', $content);
        
        // Clean up
        unlink($temp_file);
    }
    
    /**
     * Test database export with table filtering
     */
    public function test_export_database_with_table_filtering() {
        $settings = array(
            'include_tables' => array('wp_posts', 'wp_users'),
        );
        
        $database = new WP_Migrate_Core_Database($settings);
        $temp_file = $this->create_temp_file('', 'sql');
        
        $result = $database->export_database($temp_file);
        
        $this->assertTrue($result);
        $this->assertFileExists($temp_file);
        
        $content = file_get_contents($temp_file);
        $this->assertStringContainsString('wp_posts', $content);
        $this->assertStringContainsString('wp_users', $content);
        
        // Clean up
        unlink($temp_file);
    }
    
    /**
     * Test database import
     */
    public function test_import_database() {
        // Create test SQL content
        $sql_content = "-- Test SQL\nINSERT INTO wp_options (option_name, option_value) VALUES ('test_option', 'test_value');";
        $temp_file = $this->create_temp_file($sql_content, 'sql');
        
        $options = array(
            'create_backup' => false, // Skip backup for testing
        );
        
        $result = $this->database->import_database($temp_file, $options);
        
        $this->assertTrue($result);
        
        // Check if option was imported
        $this->assertEquals('test_value', get_option('test_option'));
        
        // Clean up
        delete_option('test_option');
        unlink($temp_file);
    }
    
    /**
     * Test URL replacement in database export
     */
    public function test_url_replacement() {
        // Create test data with URLs
        update_option('test_url_option', 'https://old-site.com/path');
        
        $settings = array(
            'find_replace' => array(
                'https://old-site.com' => 'https://new-site.com',
            ),
        );
        
        $database = new WP_Migrate_Core_Database($settings);
        $temp_file = $this->create_temp_file('', 'sql');
        
        $result = $database->export_database($temp_file);
        $this->assertTrue($result);
        
        $content = file_get_contents($temp_file);
        $this->assertStringContainsString('https://new-site.com/path', $content);
        $this->assertStringNotContainsString('https://old-site.com/path', $content);
        
        // Clean up
        delete_option('test_url_option');
        unlink($temp_file);
    }
    
    /**
     * Test dry run mode
     */
    public function test_dry_run_mode() {
        $settings = array(
            'dry_run' => true,
        );
        
        $database = new WP_Migrate_Core_Database($settings);
        $temp_file = tempnam(sys_get_temp_dir(), 'wp_migrate_test_') . '.sql';
        
        $result = $database->export_database($temp_file);
        
        $this->assertTrue($result);
        // File should not be created in dry run mode
        $this->assertFileDoesNotExist($temp_file);
    }
    
    /**
     * Test database size calculation
     */
    public function test_get_database_size() {
        $size = $this->database->get_database_size();
        
        $this->assertIsInt($size);
        $this->assertGreaterThan(0, $size);
    }
    
    /**
     * Test connection validation
     */
    public function test_validate_connection() {
        $connection_params = array(
            'host' => DB_HOST,
            'database' => DB_NAME,
            'username' => DB_USER,
            'password' => DB_PASSWORD,
        );
        
        $result = $this->database->validate_connection($connection_params);
        
        $this->assertTrue($result);
    }
    
    /**
     * Test invalid connection validation
     */
    public function test_validate_invalid_connection() {
        $connection_params = array(
            'host' => 'invalid_host',
            'database' => 'invalid_db',
            'username' => 'invalid_user',
            'password' => 'invalid_pass',
        );
        
        $result = $this->database->validate_connection($connection_params);
        
        $this->assertInstanceOf('WP_Error', $result);
    }
    
    /**
     * Test progress callback
     */
    public function test_progress_callback() {
        $progress_calls = array();
        
        $this->database->set_progress_callback(function($percentage, $message) use (&$progress_calls) {
            $progress_calls[] = array('percentage' => $percentage, 'message' => $message);
        });
        
        $temp_file = $this->create_temp_file('', 'sql');
        $this->database->export_database($temp_file);
        
        $this->assertNotEmpty($progress_calls);
        $this->assertArrayHasKey('percentage', $progress_calls[0]);
        $this->assertArrayHasKey('message', $progress_calls[0]);
        
        // Clean up
        unlink($temp_file);
    }
    
    /**
     * Test export with large dataset simulation
     */
    public function test_export_large_dataset() {
        // Create multiple test posts to simulate larger dataset
        $post_ids = array();
        for ($i = 0; $i < 50; $i++) {
            $post_ids[] = $this->factory->post->create(array(
                'post_title' => 'Test Post ' . $i,
                'post_content' => 'Content for test post ' . $i,
            ));
        }
        
        $settings = array(
            'chunk_size' => 10, // Small chunk size to test chunking
        );
        
        $database = new WP_Migrate_Core_Database($settings);
        $temp_file = $this->create_temp_file('', 'sql');
        
        $result = $database->export_database($temp_file);
        
        $this->assertTrue($result);
        $this->assertFileExists($temp_file);
        
        $content = file_get_contents($temp_file);
        $this->assertNotEmpty($content);
        
        // Clean up
        foreach ($post_ids as $post_id) {
            wp_delete_post($post_id, true);
        }
        unlink($temp_file);
    }
}