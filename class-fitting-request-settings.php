<?php
/**
 * Temporary settings class for Module 1
 *
 * @package FittingRequestSystem
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Fitting_Request_Settings {
    
    /**
     * Get settings
     */
    public static function get_settings() {
        $defaults = array(
            'enable_email_notifications' => true,
            'enable_whatsapp_notifications' => false,
            'max_garages_per_request' => 10,
            'request_timeout_hours' => 24,
            'enable_quote_system' => true,
            'enable_feedback_system' => true,
            'admin_email' => get_option('admin_email'),
            'default_emirate' => 'Dubai'
        );
        
        return wp_parse_args(get_option('fitting_request_settings', array()), $defaults);
    }
    
    /**
     * Update settings
     */
    public static function update_settings($settings) {
        return update_option('fitting_request_settings', $settings);
    }
    
    /**
     * Get specific setting
     */
    public static function get_setting($key, $default = null) {
        $settings = self::get_settings();
        return isset($settings[$key]) ? $settings[$key] : $default;
    }
}