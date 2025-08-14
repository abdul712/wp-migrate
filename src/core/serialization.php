<?php
/**
 * Serialized Data Handler
 *
 * Critical component for safe find/replace in WordPress serialized data.
 * Prevents data corruption during URL/path replacements.
 *
 * @package WPMigrate
 * @subpackage Core
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WP_Migrate_Core_Serialization
 *
 * Handles WordPress serialized data safely during migrations
 */
class WP_Migrate_Core_Serialization {
    
    /**
     * Safely replace strings in serialized data
     *
     * @param mixed $data The data to process
     * @param array $replacements Array of from => to replacements
     * @return mixed Processed data
     */
    public static function safe_replace($data, $replacements) {
        if (is_string($data) && self::is_serialized($data)) {
            return self::replace_serialized_string($data, $replacements);
        }
        
        if (is_array($data)) {
            return self::replace_array($data, $replacements);
        }
        
        if (is_object($data)) {
            return self::replace_object($data, $replacements);
        }
        
        if (is_string($data)) {
            return self::replace_string($data, $replacements);
        }
        
        return $data;
    }
    
    /**
     * Check if a string is serialized
     *
     * @param string $data Data to check
     * @return bool True if serialized
     */
    public static function is_serialized($data) {
        if (!is_string($data)) {
            return false;
        }
        
        $data = trim($data);
        
        if ('N;' === $data) {
            return true;
        }
        
        if (strlen($data) < 4) {
            return false;
        }
        
        if (':' !== $data[1]) {
            return false;
        }
        
        $lastc = substr($data, -1);
        if (';' !== $lastc && '}' !== $lastc) {
            return false;
        }
        
        $token = $data[0];
        switch ($token) {
            case 's':
                if ('"' !== substr($data, -2, 1)) {
                    return false;
                }
                // Fall through
            case 'a':
            case 'O':
                return (bool) preg_match("/^{$token}:[0-9]+:/s", $data);
            case 'b':
            case 'i':
            case 'd':
                $end = '';
                return (bool) preg_match("/^{$token}:[0-9.E+-]+;$/", $data);
        }
        
        return false;
    }
    
    /**
     * Replace strings in serialized string
     *
     * @param string $serialized_string Serialized string
     * @param array $replacements Replacements array
     * @return string Processed serialized string
     */
    private static function replace_serialized_string($serialized_string, $replacements) {
        try {
            $unserialized = unserialize($serialized_string);
            $processed = self::safe_replace($unserialized, $replacements);
            return serialize($processed);
        } catch (Exception $e) {
            // If unserialization fails, fallback to regex replacement
            return self::regex_replace_serialized($serialized_string, $replacements);
        }
    }
    
    /**
     * Regex-based replacement for serialized data (fallback)
     *
     * @param string $serialized_string Serialized string
     * @param array $replacements Replacements array
     * @return string Processed string
     */
    private static function regex_replace_serialized($serialized_string, $replacements) {
        foreach ($replacements as $from => $to) {
            // Handle serialized string length updates
            $pattern = '/s:(\d+):"([^"]*' . preg_quote($from, '/') . '[^"]*)"/';
            
            $serialized_string = preg_replace_callback($pattern, function($matches) use ($from, $to) {
                $original_string = $matches[2];
                $new_string = str_replace($from, $to, $original_string);
                $new_length = strlen($new_string);
                
                return 's:' . $new_length . ':"' . $new_string . '"';
            }, $serialized_string);
        }
        
        return $serialized_string;
    }
    
    /**
     * Replace strings in array
     *
     * @param array $array Array to process
     * @param array $replacements Replacements array
     * @return array Processed array
     */
    private static function replace_array($array, $replacements) {
        $result = array();
        
        foreach ($array as $key => $value) {
            $new_key = self::safe_replace($key, $replacements);
            $new_value = self::safe_replace($value, $replacements);
            $result[$new_key] = $new_value;
        }
        
        return $result;
    }
    
    /**
     * Replace strings in object
     *
     * @param object $object Object to process
     * @param array $replacements Replacements array
     * @return object Processed object
     */
    private static function replace_object($object, $replacements) {
        $class_name = get_class($object);
        
        // Convert to array, process, then convert back
        $array = (array) $object;
        $processed_array = self::replace_array($array, $replacements);
        
        // Create new object of the same class
        $new_object = new $class_name();
        foreach ($processed_array as $key => $value) {
            // Handle private/protected properties
            if (strpos($key, "\0") !== false) {
                $key_parts = explode("\0", $key);
                $property = end($key_parts);
            } else {
                $property = $key;
            }
            
            if (property_exists($new_object, $property)) {
                $new_object->$property = $value;
            }
        }
        
        return $new_object;
    }
    
    /**
     * Replace strings in regular string
     *
     * @param string $string String to process
     * @param array $replacements Replacements array
     * @return string Processed string
     */
    private static function replace_string($string, $replacements) {
        foreach ($replacements as $from => $to) {
            $string = str_replace($from, $to, $string);
        }
        return $string;
    }
    
    /**
     * Process WordPress option values
     *
     * @param string $option_name Option name
     * @param mixed $option_value Option value
     * @param array $replacements Replacements array
     * @return mixed Processed option value
     */
    public static function process_option($option_name, $option_value, $replacements) {
        // WordPress options that commonly contain serialized data
        $serialized_options = array(
            'widget_',
            'sidebars_widgets',
            'theme_mods_',
            'customizer_',
            '_transient_',
            '_site_transient_',
        );
        
        $is_serialized_option = false;
        foreach ($serialized_options as $prefix) {
            if (strpos($option_name, $prefix) === 0) {
                $is_serialized_option = true;
                break;
            }
        }
        
        if ($is_serialized_option || self::is_serialized($option_value)) {
            return self::safe_replace($option_value, $replacements);
        }
        
        return self::replace_string($option_value, $replacements);
    }
    
    /**
     * Process WordPress post content
     *
     * @param string $content Post content
     * @param array $replacements Replacements array
     * @return string Processed content
     */
    public static function process_post_content($content, $replacements) {
        // Handle WordPress blocks (Gutenberg)
        if (strpos($content, '<!-- wp:') !== false) {
            $content = self::process_gutenberg_blocks($content, $replacements);
        }
        
        // Handle shortcodes
        $content = self::process_shortcodes($content, $replacements);
        
        // Regular string replacement
        return self::replace_string($content, $replacements);
    }
    
    /**
     * Process Gutenberg blocks
     *
     * @param string $content Content with blocks
     * @param array $replacements Replacements array
     * @return string Processed content
     */
    private static function process_gutenberg_blocks($content, $replacements) {
        // Pattern to match Gutenberg block comments
        $pattern = '/<!-- wp:([a-z\-\/]+)(\s+(\{.*?\}))?\s+-->/';
        
        return preg_replace_callback($pattern, function($matches) use ($replacements) {
            $block_name = $matches[1];
            $block_attributes = isset($matches[3]) ? $matches[3] : '';
            
            if (!empty($block_attributes)) {
                $decoded_attributes = json_decode($block_attributes, true);
                if ($decoded_attributes) {
                    $processed_attributes = self::safe_replace($decoded_attributes, $replacements);
                    $encoded_attributes = json_encode($processed_attributes);
                    return '<!-- wp:' . $block_name . ' ' . $encoded_attributes . ' -->';
                }
            }
            
            return $matches[0];
        }, $content);
    }
    
    /**
     * Process shortcodes
     *
     * @param string $content Content with shortcodes
     * @param array $replacements Replacements array
     * @return string Processed content
     */
    private static function process_shortcodes($content, $replacements) {
        // Basic shortcode pattern - could be enhanced
        $pattern = '/\[([a-zA-Z_-]+)([^\]]*)\]/';
        
        return preg_replace_callback($pattern, function($matches) use ($replacements) {
            $shortcode = $matches[0];
            
            foreach ($replacements as $from => $to) {
                $shortcode = str_replace($from, $to, $shortcode);
            }
            
            return $shortcode;
        }, $content);
    }
    
    /**
     * Process WordPress meta values
     *
     * @param mixed $meta_value Meta value
     * @param array $replacements Replacements array
     * @return mixed Processed meta value
     */
    public static function process_meta_value($meta_value, $replacements) {
        // Many WordPress meta values are serialized
        return self::safe_replace($meta_value, $replacements);
    }
    
    /**
     * Validate serialized data integrity
     *
     * @param string $original_data Original serialized data
     * @param string $processed_data Processed serialized data
     * @return bool True if integrity is maintained
     */
    public static function validate_integrity($original_data, $processed_data) {
        if (!self::is_serialized($original_data) || !self::is_serialized($processed_data)) {
            return true; // Not serialized, assume OK
        }
        
        try {
            $original_unserialized = unserialize($original_data);
            $processed_unserialized = unserialize($processed_data);
            
            // Check if both unserialize successfully
            return ($original_unserialized !== false && $processed_unserialized !== false);
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Get serialized data statistics
     *
     * @param string $data Data to analyze
     * @return array Statistics array
     */
    public static function get_serialized_stats($data) {
        $stats = array(
            'is_serialized' => false,
            'type' => null,
            'length' => 0,
            'depth' => 0,
            'contains_objects' => false,
        );
        
        if (!self::is_serialized($data)) {
            return $stats;
        }
        
        $stats['is_serialized'] = true;
        $stats['length'] = strlen($data);
        
        try {
            $unserialized = unserialize($data);
            $stats['type'] = gettype($unserialized);
            $stats['depth'] = self::calculate_depth($unserialized);
            $stats['contains_objects'] = self::contains_objects($unserialized);
        } catch (Exception $e) {
            // Could not unserialize
        }
        
        return $stats;
    }
    
    /**
     * Calculate data structure depth
     *
     * @param mixed $data Data to analyze
     * @param int $current_depth Current depth
     * @return int Maximum depth
     */
    private static function calculate_depth($data, $current_depth = 0) {
        if (!is_array($data) && !is_object($data)) {
            return $current_depth;
        }
        
        $max_depth = $current_depth;
        
        if (is_array($data)) {
            foreach ($data as $value) {
                $depth = self::calculate_depth($value, $current_depth + 1);
                $max_depth = max($max_depth, $depth);
            }
        } elseif (is_object($data)) {
            $properties = get_object_vars($data);
            foreach ($properties as $value) {
                $depth = self::calculate_depth($value, $current_depth + 1);
                $max_depth = max($max_depth, $depth);
            }
        }
        
        return $max_depth;
    }
    
    /**
     * Check if data contains objects
     *
     * @param mixed $data Data to check
     * @return bool True if contains objects
     */
    private static function contains_objects($data) {
        if (is_object($data)) {
            return true;
        }
        
        if (is_array($data)) {
            foreach ($data as $value) {
                if (self::contains_objects($value)) {
                    return true;
                }
            }
        }
        
        return false;
    }
}