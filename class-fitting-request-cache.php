<?php
/**
 * Cache manager class
 *
 * @package FittingRequestSystem
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Fitting_Request_Cache {
    
    /**
     * Cache group
     *
     * @var string
     */
    private static $cache_group = 'fitting_requests';
    
    /**
     * Default cache expiry (1 hour)
     *
     * @var int
     */
    private static $default_expiry = 3600;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init();
    }
    
    /**
     * Initialize cache system
     */
    private function init() {
        // Add cache invalidation hooks
        add_action('fitting_request_data_updated', array($this, 'invalidate_related_cache'));
        add_action('wp_insert_post', array($this, 'invalidate_garage_cache'));
        add_action('wp_update_post', array($this, 'invalidate_garage_cache'));
        add_action('wp_delete_post', array($this, 'invalidate_garage_cache'));
        
        // Clear cache on plugin deactivation
        add_action('deactivate_fitting-request-system/fitting-request-system.php', array($this, 'clear_all_cache'));
    }
    
    /**
     * Get cached data
     */
    public static function get($key) {
        return wp_cache_get($key, self::$cache_group);
    }
    
    /**
     * Set cache data
     */
    public static function set($key, $data, $expiry = null) {
        $expiry = $expiry ?? self::$default_expiry;
        return wp_cache_set($key, $data, self::$cache_group, $expiry);
    }
    
    /**
     * Delete cached data
     */
    public static function delete($key) {
        return wp_cache_delete($key, self::$cache_group);
    }
    
    /**
     * Clear all plugin cache
     */
    public function clear_all_cache() {
        wp_cache_flush_group(self::$cache_group);
        
        // Clear transients
        self::clear_transients();
    }
    
    /**
     * Get car makes with caching
     */
    public static function get_car_makes() {
        $cache_key = 'car_makes_list';
        $makes = self::get($cache_key);
        
        if (false === $makes) {
            $makes = array();
            
            // Get unique makes from vehicles taxonomy
            $terms = get_terms(array(
                'taxonomy' => 'vehicles',
                'hide_empty' => false
            ));
            
            if (!is_wp_error($terms) && !empty($terms)) {
                $unique_makes = array();
                
                foreach ($terms as $term) {
                    $term_make = get_term_meta($term->term_id, 'vehicle_make', true);
                    if (!empty($term_make) && !in_array($term_make, $unique_makes)) {
                        $unique_makes[] = $term_make;
                    }
                }
                
                sort($unique_makes);
                $makes = $unique_makes;
            }
            
            // Cache for 24 hours
            self::set($cache_key, $makes, DAY_IN_SECONDS);
        }
        
        return $makes;
    }
    
    /**
     * Get car models for a specific make with caching
     */
    public static function get_car_models($make) {
        $cache_key = 'car_models_' . sanitize_key($make);
        $models = self::get($cache_key);
        
        if (false === $models) {
            $models = array();
            
            // Get terms from existing vehicles taxonomy
            $terms = get_terms(array(
                'taxonomy' => 'vehicles',
                'hide_empty' => false,
                'meta_query' => array(
                    array(
                        'key' => 'vehicle_make',
                        'value' => $make,
                        'compare' => 'LIKE'
                    )
                )
            ));
            
            if (!is_wp_error($terms) && !empty($terms)) {
                $unique_models = array();
                
                foreach ($terms as $term) {
                    $term_model = get_term_meta($term->term_id, 'vehicle_model', true);
                    if (!empty($term_model) && !in_array($term_model, $unique_models)) {
                        $unique_models[] = $term_model;
                    }
                }
                
                sort($unique_models);
                $models = $unique_models;
            }
            
            // Cache for 6 hours
            self::set($cache_key, $models, 6 * HOUR_IN_SECONDS);
        }
        
        return $models;
    }
    
    /**
     * Get garages by emirate with caching
     */
    public static function get_garages_by_emirate($emirate) {
        $cache_key = 'garages_emirate_' . sanitize_key($emirate);
        $garages = self::get($cache_key);
        
        if (false === $garages) {
            $garages = get_posts(array(
                'post_type' => 'garage',
                'post_status' => 'publish',
                'numberposts' => -1,
                'meta_query' => array(
                    array(
                        'key' => 'emirate',
                        'value' => $emirate,
                        'compare' => '='
                    ),
                    array(
                        'key' => 'status',
                        'value' => 'active',
                        'compare' => '='
                    )
                )
            ));
            
            // Cache for 30 minutes
            self::set($cache_key, $garages, 30 * MINUTE_IN_SECONDS);
        }
        
        return $garages;
    }
    
    /**
     * Get emirates list with caching
     */
    public static function get_emirates() {
        $cache_key = 'emirates_list';
        $emirates = self::get($cache_key);
        
        if (false === $emirates) {
            $emirates = array(
                'Abu Dhabi' => __('Abu Dhabi', 'fitting-request-system'),
                'Dubai' => __('Dubai', 'fitting-request-system'),
                'Sharjah' => __('Sharjah', 'fitting-request-system'),
                'Ajman' => __('Ajman', 'fitting-request-system'),
                'Umm Al Quwain' => __('Umm Al Quwain', 'fitting-request-system'),
                'Ras Al Khaimah' => __('Ras Al Khaimah', 'fitting-request-system'),
                'Fujairah' => __('Fujairah', 'fitting-request-system')
            );
            
            // Cache for 1 week
            self::set($cache_key, $emirates, WEEK_IN_SECONDS);
        }
        
        return $emirates;
    }
    
    /**
     * Cache request statistics
     */
    public static function get_request_statistics($days = 30) {
        $cache_key = 'request_stats_' . $days . '_days';
        $stats = self::get($cache_key);
        
        if (false === $stats) {
            $database = new Fitting_Request_Database();
            $stats = $database->get_statistics($days);
            
            // Cache for 15 minutes
            self::set($cache_key, $stats, 15 * MINUTE_IN_SECONDS);
        }
        
        return $stats;
    }
    
    /**
     * Cache garage information
     */
    public static function get_garage_info($garage_id) {
        $cache_key = 'garage_info_' . $garage_id;
        $garage_info = self::get($cache_key);
        
        if (false === $garage_info) {
            $garage_post = get_post($garage_id);
            
            if ($garage_post && $garage_post->post_type === 'garage') {
                $garage_info = array(
                    'id' => $garage_id,
                    'name' => $garage_post->post_title,
                    'email' => get_post_meta($garage_id, 'email', true),
                    'whatsapp' => get_post_meta($garage_id, 'whatsapp', true),
                    'emirate' => get_post_meta($garage_id, 'emirate', true),
                    'address' => get_post_meta($garage_id, 'address', true),
                    'status' => get_post_meta($garage_id, 'status', true)
                );
            } else {
                $garage_info = null;
            }
            
            // Cache for 1 hour
            self::set($cache_key, $garage_info, HOUR_IN_SECONDS);
        }
        
        return $garage_info;
    }
    
    /**
     * Invalidate cache when data is updated
     */
    public function invalidate_related_cache($data_type = null) {
        switch ($data_type) {
            case 'vehicles':
                self::delete('car_makes_list');
                // Clear all model caches
                $this->clear_cache_by_pattern('car_models_*');
                break;
                
            case 'garages':
                // Clear all garage-related caches
                $this->clear_cache_by_pattern('garages_emirate_*');
                $this->clear_cache_by_pattern('garage_info_*');
                break;
                
            case 'requests':
                // Clear statistics cache
                $this->clear_cache_by_pattern('request_stats_*');
                break;
                
            default:
                // Clear all cache if type not specified
                $this->clear_all_cache();
                break;
        }
    }
    
    /**
     * Invalidate garage cache when posts are updated
     */
    public function invalidate_garage_cache($post_id) {
        $post = get_post($post_id);
        
        if ($post && $post->post_type === 'garage') {
            self::delete('garage_info_' . $post_id);
            
            $emirate = get_post_meta($post_id, 'emirate', true);
            if ($emirate) {
                self::delete('garages_emirate_' . sanitize_key($emirate));
            }
        }
    }
    
    /**
     * Clear cache by pattern (simple implementation)
     */
    private function clear_cache_by_pattern($pattern) {
        // WordPress doesn't provide pattern-based cache clearing
        // This is a simplified implementation
        $common_keys = array(
            'car_makes_list',
            'emirates_list'
        );
        
        // Clear common patterns
        if (strpos($pattern, 'car_models_') === 0) {
            $makes = self::get_car_makes();
            foreach ($makes as $make) {
                self::delete('car_models_' . sanitize_key($make));
            }
        }
        
        if (strpos($pattern, 'garages_emirate_') === 0) {
            $emirates = self::get_emirates();
            foreach (array_keys($emirates) as $emirate) {
                self::delete('garages_emirate_' . sanitize_key($emirate));
            }
        }
        
        if (strpos($pattern, 'garage_info_') === 0) {
            // This would require tracking all garage IDs
            // For now, we'll rely on individual invalidation
        }
        
        if (strpos($pattern, 'request_stats_') === 0) {
            $periods = array(7, 14, 30, 60, 90);
            foreach ($periods as $days) {
                self::delete('request_stats_' . $days . '_days');
            }
        }
    }
    
    /**
     * Clear all transients created by the plugin
     */
    private static function clear_transients() {
        global $wpdb;
        
        // Delete transients with our prefix
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
            WHERE option_name LIKE '_transient_fitting_request_%' 
            OR option_name LIKE '_transient_timeout_fitting_request_%'"
        );
    }
    
    /**
     * Get cache statistics
     */
    public static function get_cache_stats() {
        // This is a basic implementation as WordPress doesn't provide
        // detailed cache statistics by default
        return array(
            'cache_group' => self::$cache_group,
            'default_expiry' => self::$default_expiry,
            'cache_enabled' => wp_using_ext_object_cache(),
            'cache_type' => wp_using_ext_object_cache() ? 'external' : 'internal'
        );
    }
    
    /**
     * Warm up cache with frequently accessed data
     */
    public static function warm_up_cache() {
        // Pre-load frequently accessed data
        self::get_car_makes();
        self::get_emirates();
        
        // Pre-load garage data for each emirate
        $emirates = self::get_emirates();
        foreach (array_keys($emirates) as $emirate) {
            self::get_garages_by_emirate($emirate);
        }
        
        // Pre-load common statistics
        self::get_request_statistics(7);
        self::get_request_statistics(30);
    }
    
    /**
     * Schedule cache warming
     */
    public static function schedule_cache_warming() {
        if (!wp_next_scheduled('fitting_request_warm_cache')) {
            wp_schedule_event(time(), 'daily', 'fitting_request_warm_cache');
        }
    }
}