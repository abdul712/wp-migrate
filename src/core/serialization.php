<?php

/**
 * WP Migrate Serialization Handler
 * Critical component for handling WordPress serialized data during migrations
 * This prevents data corruption that occurs with standard find/replace operations
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Migrate_Serialization {
    
    /**
     * Process serialized data with find/replace operations
     */
    public function processSerializedData($content, $find_replace) {
        if (empty($find_replace) || !is_array($find_replace)) {
            return $content;
        }
        
        // Process each find/replace pair
        foreach ($find_replace as $find => $replace) {
            $content = $this->replaceSerializedData($content, $find, $replace);
        }
        
        return $content;
    }
    
    /**
     * Process individual cell value for serialized data
     */
    public function processCellValue($value, $find_replace) {
        if (empty($find_replace) || !is_array($find_replace)) {
            return $value;
        }
        
        // Check if value contains serialized data
        if ($this->isSerialized($value)) {
            return $this->processSerializedValue($value, $find_replace);
        }
        
        // Regular find/replace for non-serialized data
        foreach ($find_replace as $find => $replace) {
            $value = str_replace($find, $replace, $value);
        }
        
        return $value;
    }
    
    /**
     * Replace URLs and paths in serialized data while maintaining integrity
     */
    private function replaceSerializedData($content, $find, $replace) {
        // Use regex to find serialized data patterns
        $pattern = '/s:(\d+):"([^"]*' . preg_quote($find, '/') . '[^"]*)"/';
        
        return preg_replace_callback($pattern, function($matches) use ($find, $replace) {
            $original_length = (int)$matches[1];
            $original_string = $matches[2];
            $new_string = str_replace($find, $replace, $original_string);
            $new_length = strlen($new_string);
            
            return 's:' . $new_length . ':"' . $new_string . '"';
        }, $content);
    }
    
    /**
     * Process serialized value safely
     */
    private function processSerializedValue($serialized_value, $find_replace) {
        // Try to unserialize
        $data = @unserialize($serialized_value);
        
        if ($data === false) {
            // If unserialize fails, treat as regular string
            foreach ($find_replace as $find => $replace) {
                $serialized_value = str_replace($find, $replace, $serialized_value);
            }
            return $serialized_value;
        }
        
        // Recursively process the unserialized data
        $processed_data = $this->processSerializedArray($data, $find_replace);
        
        // Serialize back
        return serialize($processed_data);
    }
    
    /**
     * Recursively process arrays and objects in serialized data
     */
    private function processSerializedArray($data, $find_replace) {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                // Process array keys
                $new_key = $key;
                if (is_string($key)) {
                    foreach ($find_replace as $find => $replace) {
                        $new_key = str_replace($find, $replace, $new_key);
                    }
                }
                
                // Process array values recursively
                $new_value = $this->processSerializedArray($value, $find_replace);
                
                // Update array with new key/value
                if ($new_key !== $key) {
                    unset($data[$key]);
                }
                $data[$new_key] = $new_value;
            }
        } elseif (is_object($data)) {
            foreach (get_object_vars($data) as $key => $value) {
                // Process object properties
                $new_value = $this->processSerializedArray($value, $find_replace);
                $data->$key = $new_value;
                
                // Process property names if needed
                $new_key = $key;
                foreach ($find_replace as $find => $replace) {
                    $new_key = str_replace($find, $replace, $new_key);
                }
                
                if ($new_key !== $key) {
                    $data->$new_key = $data->$key;
                    unset($data->$key);
                }
            }
        } elseif (is_string($data)) {
            // Process string values
            foreach ($find_replace as $find => $replace) {
                $data = str_replace($find, $replace, $data);
            }
        }
        
        return $data;
    }
    
    /**
     * Check if a value is serialized
     */
    private function isSerialized($data) {
        // Don't attempt to unserialize data that isn't a string
        if (!is_string($data)) {
            return false;
        }
        
        $data = trim($data);
        
        // Empty strings are not serialized
        if (empty($data)) {
            return false;
        }
        
        // Serialized false, return true. Serialized booleans are tricky.
        if ('b:0;' === $data) {
            return true;
        }
        
        // Check for serialized data patterns
        $length = strlen($data);
        $end = '';
        
        switch ($data[0]) {
            case 's':
                if ('"' !== $data[$length - 2]) {
                    return false;
                }
                break;
            case 'b':
            case 'i':
            case 'd':
                $end .= ';';
                break;
            case 'a':
            case 'O':
                $end .= '}';
                break;
            case 'N':
                return 'N;' === $data;
            default:
                return false;
        }
        
        if (';' !== $data[1] && ':' !== $data[1]) {
            return false;
        }
        
        if ($end && substr($data, -1) !== $end) {
            return false;
        }
        
        // Test unserialize to be sure
        return (@unserialize($data) !== false);
    }
    
    /**
     * Process WordPress widget data
     */
    public function processWidgetData($widget_data, $find_replace) {
        if (!$this->isSerialized($widget_data)) {
            return $widget_data;
        }
        
        $data = unserialize($widget_data);
        
        if (!is_array($data)) {
            return $widget_data;
        }
        
        // Process each widget instance
        foreach ($data as $widget_id => &$widget_instance) {
            if (is_array($widget_instance)) {
                $widget_instance = $this->processSerializedArray($widget_instance, $find_replace);
            }
        }
        
        return serialize($data);
    }
    
    /**
     * Process WordPress customizer data
     */
    public function processCustomizerData($customizer_data, $find_replace) {
        if (!$this->isSerialized($customizer_data)) {
            return $customizer_data;
        }
        
        $data = unserialize($customizer_data);
        
        if (!is_array($data)) {
            return $customizer_data;
        }
        
        // Process customizer settings
        $data = $this->processSerializedArray($data, $find_replace);
        
        return serialize($data);
    }
    
    /**
     * Process WordPress theme mod data
     */
    public function processThemeModData($theme_mod_data, $find_replace) {
        if (!$this->isSerialized($theme_mod_data)) {
            return $theme_mod_data;
        }
        
        $data = unserialize($theme_mod_data);
        
        if (!is_array($data)) {
            return $theme_mod_data;
        }
        
        // Process theme modifications
        foreach ($data as $mod_name => &$mod_value) {
            if (is_string($mod_value)) {
                foreach ($find_replace as $find => $replace) {
                    $mod_value = str_replace($find, $replace, $mod_value);
                }
            } elseif (is_array($mod_value) || is_object($mod_value)) {
                $mod_value = $this->processSerializedArray($mod_value, $find_replace);
            }
        }
        
        return serialize($data);
    }
    
    /**
     * Process WordPress menu data
     */
    public function processMenuData($menu_data, $find_replace) {
        if (!is_array($menu_data)) {
            return $menu_data;
        }
        
        foreach ($menu_data as &$menu_item) {
            if (isset($menu_item['url'])) {
                foreach ($find_replace as $find => $replace) {
                    $menu_item['url'] = str_replace($find, $replace, $menu_item['url']);
                }
            }
            
            if (isset($menu_item['meta']) && is_array($menu_item['meta'])) {
                foreach ($menu_item['meta'] as &$meta_value) {
                    if (is_string($meta_value)) {
                        foreach ($find_replace as $find => $replace) {
                            $meta_value = str_replace($find, $replace, $meta_value);
                        }
                    }
                }
            }
        }
        
        return $menu_data;
    }
    
    /**
     * Validate serialized data integrity
     */
    public function validateSerializedData($data) {
        if (!$this->isSerialized($data)) {
            return true;
        }
        
        $unserialized = @unserialize($data);
        return ($unserialized !== false);
    }
    
    /**
     * Repair corrupted serialized data
     */
    public function repairSerializedData($data) {
        if (!is_string($data) || empty($data)) {
            return $data;
        }
        
        // Try to unserialize first
        if (@unserialize($data) !== false) {
            return $data; // Data is already valid
        }
        
        // Common serialization fixes
        $fixes = array(
            // Fix incorrect string lengths
            '/s:(\d+):"([^"]*?)";/' => function($matches) {
                $actual_length = strlen($matches[2]);
                return 's:' . $actual_length . ':"' . $matches[2] . '";';
            },
            
            // Fix array counts
            '/a:(\d+):\{([^}]*)\}/' => function($matches) {
                $content = $matches[2];
                // Count semicolons to estimate array size
                $count = substr_count($content, ';') / 2;
                return 'a:' . intval($count) . ':{' . $content . '}';
            }
        );
        
        foreach ($fixes as $pattern => $replacement) {
            if (is_callable($replacement)) {
                $data = preg_replace_callback($pattern, $replacement, $data);
            } else {
                $data = preg_replace($pattern, $replacement, $data);
            }
        }
        
        // Final validation
        if (@unserialize($data) !== false) {
            return $data;
        }
        
        // If still invalid, return original data
        return $data;
    }
    
    /**
     * Get serialized data statistics
     */
    public function getSerializationStats($content) {
        $stats = array(
            'total_serialized' => 0,
            'valid_serialized' => 0,
            'invalid_serialized' => 0,
            'serialized_types' => array()
        );
        
        // Find all potential serialized data
        preg_match_all('/[aObdisN]:[^;]*;/', $content, $matches);
        
        foreach ($matches[0] as $serialized) {
            $stats['total_serialized']++;
            
            if ($this->validateSerializedData($serialized)) {
                $stats['valid_serialized']++;
                $type = $serialized[0];
                if (!isset($stats['serialized_types'][$type])) {
                    $stats['serialized_types'][$type] = 0;
                }
                $stats['serialized_types'][$type]++;
            } else {
                $stats['invalid_serialized']++;
            }
        }
        
        return $stats;
    }
}