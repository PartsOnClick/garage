<?php
/**
 * Plugin Name: Fitting Request System
 * Plugin URI: https://partsonclick.ae
 * Description: WooCommerce Vehicle Parts Fitting Request System - Connect customers with garages for professional fitting services
 * Version: 3.0.0
 * Author: PartsOnClick
 * Author URI: https://partsonclick.ae
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: fitting-request-system
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.3
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('FITTING_REQUEST_VERSION', '3.0.0');
define('FITTING_REQUEST_PLUGIN_FILE', __FILE__);
define('FITTING_REQUEST_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FITTING_REQUEST_PLUGIN_URL', plugin_dir_url(__FILE__));
define('FITTING_REQUEST_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Check if WooCommerce is active
function fitting_request_check_woocommerce() {
    if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p><strong>Fitting Request System</strong> requires WooCommerce to be installed and active.</p></div>';
        });
        return false;
    }
    return true;
}

// Load core classes with error handling
function fitting_request_load_core_classes() {
    $core_files = array(
        'includes/class-fitting-request-database.php',
        'includes/class-fitting-request-error-handler.php',
        'includes/class-fitting-request-security.php',
        'includes/class-fitting-request-cache.php',
        'includes/class-fitting-request-validator.php',
        'includes/class-fitting-request-vehicle-data.php',
        'includes/class-fitting-request-system.php'
    );
    
    foreach ($core_files as $file) {
        $file_path = FITTING_REQUEST_PLUGIN_DIR . $file;
        if (file_exists($file_path)) {
            require_once $file_path;
        } else {
            // Log missing file error
            error_log("Fitting Request System: Missing file - " . $file_path);
            add_action('admin_notices', function() use ($file) {
                echo '<div class="notice notice-error"><p><strong>Fitting Request System:</strong> Missing required file: ' . esc_html($file) . '</p></div>';
            });
            return false;
        }
    }
    return true;
}

/**
 * Initialize the plugin safely
 */
function fitting_request_system_init() {
    try {
        // Check WooCommerce first
        if (!fitting_request_check_woocommerce()) {
            return;
        }
        
        // Load core classes
        if (!fitting_request_load_core_classes()) {
            return;
        }
        
        // Initialize main class if it exists
        if (class_exists('Fitting_Request_System')) {
            return Fitting_Request_System::get_instance();
        } else {
            throw new Exception('Main system class not found');
        }
        
    } catch (Exception $e) {
        error_log('Fitting Request System Init Error: ' . $e->getMessage());
        add_action('admin_notices', function() use ($e) {
            echo '<div class="notice notice-error"><p><strong>Fitting Request System Error:</strong> ' . esc_html($e->getMessage()) . '</p></div>';
        });
    }
}

// Initialize plugin after WordPress loads
add_action('plugins_loaded', 'fitting_request_system_init');

/**
 * Plugin activation hook with error handling
 */
function fitting_request_activate() {
    try {
        // Load required classes
        if (!fitting_request_load_core_classes()) {
            throw new Exception('Cannot load required classes for activation');
        }
        
        if (class_exists('Fitting_Request_System')) {
            Fitting_Request_System::activate();
        } else {
            throw new Exception('Main system class not available for activation');
        }
    } catch (Exception $e) {
        error_log('Fitting Request System Activation Error: ' . $e->getMessage());
        wp_die('Plugin activation failed: ' . $e->getMessage());
    }
}

/**
 * Plugin deactivation hook
 */
function fitting_request_deactivate() {
    try {
        if (class_exists('Fitting_Request_System')) {
            Fitting_Request_System::deactivate();
        }
    } catch (Exception $e) {
        error_log('Fitting Request System Deactivation Error: ' . $e->getMessage());
    }
}

/**
 * Plugin uninstall hook
 */
function fitting_request_uninstall() {
    try {
        if (class_exists('Fitting_Request_System')) {
            Fitting_Request_System::uninstall();
        }
    } catch (Exception $e) {
        error_log('Fitting Request System Uninstall Error: ' . $e->getMessage());
    }
}

// Register hooks
register_activation_hook(__FILE__, 'fitting_request_activate');
register_deactivation_hook(__FILE__, 'fitting_request_deactivate');
register_uninstall_hook(__FILE__, 'fitting_request_uninstall');

/**
 * Add settings link to plugin page
 */
add_filter('plugin_action_links_' . FITTING_REQUEST_PLUGIN_BASENAME, function($links) {
    $settings_link = '<a href="admin.php?page=fitting-request-settings">' . __('Settings', 'fitting-request-system') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
});

/**
 * Load plugin textdomain for translations
 */
add_action('plugins_loaded', function() {
    load_plugin_textdomain('fitting-request-system', false, dirname(FITTING_REQUEST_PLUGIN_BASENAME) . '/languages');
});

/**
 * Emergency deactivation function for debugging
 */
if (defined('WP_DEBUG') && WP_DEBUG) {
    add_action('wp_footer', function() {
        if (current_user_can('administrator') && isset($_GET['fitting_debug_deactivate'])) {
            deactivate_plugins(FITTING_REQUEST_PLUGIN_BASENAME);
            wp_redirect(admin_url('plugins.php?deactivate=true'));
            exit;
        }
    });
}

/**
 * Emergency debug function
 */
function fitting_request_debug_info() {
    if (!current_user_can('administrator')) {
        return;
    }
    
    $debug_info = array(
        'PHP Version' => PHP_VERSION,
        'WordPress Version' => get_bloginfo('version'),
        'WooCommerce Active' => class_exists('WooCommerce') ? 'Yes' : 'No',
        'Plugin Dir' => FITTING_REQUEST_PLUGIN_DIR,
        'Plugin URL' => FITTING_REQUEST_PLUGIN_URL,
        'Required Files' => array()
    );
    
    $required_files = array(
        'includes/class-fitting-request-database.php',
        'includes/class-fitting-request-error-handler.php',
        'includes/class-fitting-request-security.php',
        'includes/class-fitting-request-cache.php',
        'includes/class-fitting-request-validator.php',
        'includes/class-fitting-request-vehicle-data.php',
        'includes/class-fitting-request-system.php'
    );
    
    foreach ($required_files as $file) {
        $file_path = FITTING_REQUEST_PLUGIN_DIR . $file;
        $debug_info['Required Files'][$file] = file_exists($file_path) ? 'EXISTS' : 'MISSING';
    }
    
    return $debug_info;
}

// Add debug info to admin if there are issues
add_action('admin_footer', function() {
    if (current_user_can('administrator') && isset($_GET['fitting_debug'])) {
        echo '<script>console.log("Fitting Request Debug Info:", ' . json_encode(fitting_request_debug_info()) . ');</script>';
    }
});