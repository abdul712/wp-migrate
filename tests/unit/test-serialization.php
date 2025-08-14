<?php
/**
 * Serialization Handler Tests
 *
 * @package WPMigrate
 * @subpackage Tests
 */

/**
 * Class Test_WP_Migrate_Serialization
 *
 * Test serialized data handling functionality
 */
class Test_WP_Migrate_Serialization extends WP_Migrate_Test_Case {
    
    /**
     * Test is_serialized detection
     */
    public function test_is_serialized() {
        // Test serialized data
        $this->assertTrue(WP_Migrate_Core_Serialization::is_serialized(serialize('test')));
        $this->assertTrue(WP_Migrate_Core_Serialization::is_serialized(serialize(array('key' => 'value'))));
        $this->assertTrue(WP_Migrate_Core_Serialization::is_serialized(serialize((object) array('prop' => 'value'))));
        $this->assertTrue(WP_Migrate_Core_Serialization::is_serialized('N;')); // null
        
        // Test non-serialized data
        $this->assertFalse(WP_Migrate_Core_Serialization::is_serialized('regular string'));
        $this->assertFalse(WP_Migrate_Core_Serialization::is_serialized('123'));
        $this->assertFalse(WP_Migrate_Core_Serialization::is_serialized(''));
        $this->assertFalse(WP_Migrate_Core_Serialization::is_serialized('s:4:"test')); // incomplete
    }
    
    /**
     * Test safe replacement in regular strings
     */
    public function test_safe_replace_string() {
        $replacements = array(
            'old-site.com' => 'new-site.com',
            '/old/path' => '/new/path',
        );
        
        $input = 'Visit us at https://old-site.com/old/path for more info';
        $expected = 'Visit us at https://new-site.com/new/path for more info';
        
        $result = WP_Migrate_Core_Serialization::safe_replace($input, $replacements);
        
        $this->assertEquals($expected, $result);
    }
    
    /**
     * Test safe replacement in arrays
     */
    public function test_safe_replace_array() {
        $replacements = array(
            'old-site.com' => 'new-site.com',
        );
        
        $input = array(
            'url' => 'https://old-site.com',
            'nested' => array(
                'link' => 'https://old-site.com/page',
            ),
        );
        
        $expected = array(
            'url' => 'https://new-site.com',
            'nested' => array(
                'link' => 'https://new-site.com/page',
            ),
        );
        
        $result = WP_Migrate_Core_Serialization::safe_replace($input, $replacements);
        
        $this->assertEquals($expected, $result);
    }
    
    /**
     * Test safe replacement in serialized strings
     */
    public function test_safe_replace_serialized_string() {
        $replacements = array(
            'old-site.com' => 'new-site.com',
        );
        
        $original_data = array(
            'url' => 'https://old-site.com',
            'description' => 'Visit old-site.com for more info',
        );
        
        $serialized_input = serialize($original_data);
        $result = WP_Migrate_Core_Serialization::safe_replace($serialized_input, $replacements);
        
        // Should still be serialized
        $this->assertTrue(WP_Migrate_Core_Serialization::is_serialized($result));
        
        // When unserialized, should have replacements
        $unserialized_result = unserialize($result);
        $this->assertEquals('https://new-site.com', $unserialized_result['url']);
        $this->assertEquals('Visit new-site.com for more info', $unserialized_result['description']);
    }
    
    /**
     * Test safe replacement in complex nested serialized data
     */
    public function test_safe_replace_nested_serialized() {
        $replacements = array(
            'old-site.com' => 'new-site.com',
        );
        
        $complex_data = array(
            'widgets' => array(
                'text-1' => array(
                    'title' => 'Welcome',
                    'content' => serialize(array(
                        'text' => 'Visit https://old-site.com',
                        'links' => array('https://old-site.com/page1', 'https://old-site.com/page2'),
                    )),
                ),
            ),
            'options' => serialize(array(
                'site_url' => 'https://old-site.com',
                'admin_email' => 'admin@old-site.com',
            )),
        );
        
        $serialized_input = serialize($complex_data);
        $result = WP_Migrate_Core_Serialization::safe_replace($serialized_input, $replacements);
        
        // Should still be serialized
        $this->assertTrue(WP_Migrate_Core_Serialization::is_serialized($result));
        
        // Verify nested replacements
        $unserialized_result = unserialize($result);
        $widget_content = unserialize($unserialized_result['widgets']['text-1']['content']);
        $options = unserialize($unserialized_result['options']);
        
        $this->assertEquals('Visit https://new-site.com', $widget_content['text']);
        $this->assertEquals('https://new-site.com', $options['site_url']);
        $this->assertEquals('admin@new-site.com', $options['admin_email']);
    }
    
    /**
     * Test WordPress option processing
     */
    public function test_process_option() {
        $replacements = array(
            'old-site.com' => 'new-site.com',
        );
        
        // Test widget option (typically serialized)
        $widget_data = serialize(array(
            'text-1' => array(
                'title' => 'My Widget',
                'text' => 'Visit https://old-site.com',
            ),
        ));
        
        $result = WP_Migrate_Core_Serialization::process_option('widget_text', $widget_data, $replacements);
        $unserialized = unserialize($result);
        
        $this->assertEquals('Visit https://new-site.com', $unserialized['text-1']['text']);
        
        // Test regular option
        $regular_option = 'https://old-site.com';
        $result = WP_Migrate_Core_Serialization::process_option('site_url', $regular_option, $replacements);
        
        $this->assertEquals('https://new-site.com', $result);
    }
    
    /**
     * Test post content processing
     */
    public function test_process_post_content() {
        $replacements = array(
            'old-site.com' => 'new-site.com',
        );
        
        // Test content with Gutenberg blocks
        $content = '<!-- wp:paragraph {"align":"center"} -->
<p class="has-text-align-center">Visit us at https://old-site.com</p>
<!-- /wp:paragraph -->

<!-- wp:image {"id":123,"url":"https://old-site.com/image.jpg"} -->
<figure><img src="https://old-site.com/image.jpg" alt="Test" /></figure>
<!-- /wp:image -->';
        
        $result = WP_Migrate_Core_Serialization::process_post_content($content, $replacements);
        
        $this->assertStringContainsString('https://new-site.com', $result);
        $this->assertStringNotContainsString('https://old-site.com', $result);
    }
    
    /**
     * Test meta value processing
     */
    public function test_process_meta_value() {
        $replacements = array(
            'old-site.com' => 'new-site.com',
        );
        
        // Test serialized meta value
        $meta_value = serialize(array(
            'url' => 'https://old-site.com',
            'settings' => array(
                'api_endpoint' => 'https://old-site.com/api',
            ),
        ));
        
        $result = WP_Migrate_Core_Serialization::process_meta_value($meta_value, $replacements);
        $unserialized = unserialize($result);
        
        $this->assertEquals('https://new-site.com', $unserialized['url']);
        $this->assertEquals('https://new-site.com/api', $unserialized['settings']['api_endpoint']);
    }
    
    /**
     * Test data integrity validation
     */
    public function test_validate_integrity() {
        $original_data = serialize(array('key' => 'value'));
        $processed_data = serialize(array('key' => 'new_value'));
        $corrupted_data = 's:10:"invalid';
        
        // Valid serialized data
        $this->assertTrue(WP_Migrate_Core_Serialization::validate_integrity($original_data, $processed_data));
        
        // Corrupted data
        $this->assertFalse(WP_Migrate_Core_Serialization::validate_integrity($original_data, $corrupted_data));
        
        // Non-serialized data (should pass)
        $this->assertTrue(WP_Migrate_Core_Serialization::validate_integrity('string1', 'string2'));
    }
    
    /**
     * Test serialized data statistics
     */
    public function test_get_serialized_stats() {
        // Test serialized array
        $data = serialize(array('key' => 'value', 'nested' => array('deep' => 'value')));
        $stats = WP_Migrate_Core_Serialization::get_serialized_stats($data);
        
        $this->assertTrue($stats['is_serialized']);
        $this->assertEquals('array', $stats['type']);
        $this->assertGreaterThan(0, $stats['length']);
        $this->assertGreaterThan(0, $stats['depth']);
        $this->assertFalse($stats['contains_objects']);
        
        // Test serialized object
        $object = (object) array('prop' => 'value');
        $data = serialize($object);
        $stats = WP_Migrate_Core_Serialization::get_serialized_stats($data);
        
        $this->assertTrue($stats['is_serialized']);
        $this->assertEquals('object', $stats['type']);
        $this->assertTrue($stats['contains_objects']);
        
        // Test non-serialized data
        $stats = WP_Migrate_Core_Serialization::get_serialized_stats('regular string');
        
        $this->assertFalse($stats['is_serialized']);
        $this->assertNull($stats['type']);
    }
    
    /**
     * Test edge cases and malformed data
     */
    public function test_edge_cases() {
        $replacements = array('old' => 'new');
        
        // Empty string
        $result = WP_Migrate_Core_Serialization::safe_replace('', $replacements);
        $this->assertEquals('', $result);
        
        // Null value
        $result = WP_Migrate_Core_Serialization::safe_replace(null, $replacements);
        $this->assertNull($result);
        
        // Malformed serialized data (should fallback to regex)
        $malformed = 's:10:"test';
        $result = WP_Migrate_Core_Serialization::safe_replace($malformed, $replacements);
        $this->assertIsString($result);
        
        // Very large data
        $large_array = array_fill(0, 1000, 'old value old');
        $large_serialized = serialize($large_array);
        $result = WP_Migrate_Core_Serialization::safe_replace($large_serialized, $replacements);
        
        $this->assertTrue(WP_Migrate_Core_Serialization::is_serialized($result));
        $unserialized = unserialize($result);
        $this->assertEquals('new value new', $unserialized[0]);
    }
    
    /**
     * Test WordPress specific serialized data patterns
     */
    public function test_wordpress_specific_patterns() {
        $replacements = array(
            'http://old-site.com' => 'https://new-site.com',
        );
        
        // Test widget data
        $widget_data = serialize(array(
            'text-1' => array(
                'title' => 'My Widget',
                'text' => 'Visit <a href="http://old-site.com">our site</a>',
                'filter' => true,
            ),
            'text-2' => array(
                'title' => 'Another Widget',
                'text' => 'Image: <img src="http://old-site.com/image.jpg">',
            ),
        ));
        
        $result = WP_Migrate_Core_Serialization::safe_replace($widget_data, $replacements);
        $unserialized = unserialize($result);
        
        $this->assertStringContainsString('https://new-site.com', $unserialized['text-1']['text']);
        $this->assertStringContainsString('https://new-site.com/image.jpg', $unserialized['text-2']['text']);
        
        // Test customizer data
        $customizer_data = serialize(array(
            'custom_logo' => 123,
            'site_icon' => 456,
            'background_image' => 'http://old-site.com/bg.jpg',
            'header_image' => 'http://old-site.com/header.jpg',
        ));
        
        $result = WP_Migrate_Core_Serialization::safe_replace($customizer_data, $replacements);
        $unserialized = unserialize($result);
        
        $this->assertEquals('https://new-site.com/bg.jpg', $unserialized['background_image']);
        $this->assertEquals('https://new-site.com/header.jpg', $unserialized['header_image']);
    }
}