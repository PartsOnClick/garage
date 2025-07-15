<?php
/**
 * Main plugin class
 *
 * @package FittingRequestSystem
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Fitting_Request_System {
    
    /**
     * Plugin instance
     *
     * @var Fitting_Request_System
     */
    private static $instance = null;
    
    /**
     * Plugin version
     *
     * @var string
     */
    public $version = '3.0.0';
    
    /**
     * Database manager instance
     *
     * @var Fitting_Request_Database
     */
    public $database;
    
    /**
     * Error handler instance
     *
     * @var Fitting_Request_Error_Handler
     */
    public $error_handler;
    
    /**
     * Get plugin instance
     *
     * @return Fitting_Request_System
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
        // Load dependencies
        $this->load_dependencies();
        
        // Initialize components
        $this->init_components();
        
        // Setup hooks
        $this->setup_hooks();
        
        // Load admin components
        if (is_admin()) {
            $this->init_admin();
        }
        
        // Load frontend components
        if (!is_admin()) {
            $this->init_frontend();
        }
    }
    
    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        // Core classes are already loaded by the main plugin file
        
        // Load admin classes
        if (is_admin()) {
            $admin_files = array(
                'admin/class-fitting-request-admin.php',
                'admin/class-fitting-request-settings.php'
            );
            
            foreach ($admin_files as $file) {
                $file_path = FITTING_REQUEST_PLUGIN_DIR . 'includes/' . $file;
                if (file_exists($file_path)) {
                    require_once $file_path;
                }
            }
        }
        
        // Load frontend classes
        if (!is_admin()) {
            $frontend_files = array(
                'frontend/class-fitting-request-frontend.php'
            );
            
            foreach ($frontend_files as $file) {
                $file_path = FITTING_REQUEST_PLUGIN_DIR . 'includes/' . $file;
                if (file_exists($file_path)) {
                    require_once $file_path;
                }
            }
        }
    }
    
    /**
     * Initialize components
     */
    private function init_components() {
        // Initialize error handler first
        $this->error_handler = new Fitting_Request_Error_Handler();
        
        // Initialize database
        $this->database = new Fitting_Request_Database();
        
        // Initialize other components
        new Fitting_Request_Security();
        new Fitting_Request_Cache();
        // Initialize vehicle data component
        new Fitting_Request_Vehicle_Data();
    }
    
    /**
     * Setup WordPress hooks
     */
    private function setup_hooks() {
        // Plugin activation/deactivation
        add_action('init', array($this, 'init_plugin'));
        add_action('wp_loaded', array($this, 'loaded'));
        
        // AJAX hooks for both logged in and non-logged in users
        add_action('wp_ajax_fitting_request_get_models', array($this, 'ajax_get_car_models'));
        add_action('wp_ajax_nopriv_fitting_request_get_models', array($this, 'ajax_get_car_models'));
        add_action('wp_ajax_fitting_request_submit', array($this, 'ajax_submit_request'));
        add_action('wp_ajax_nopriv_fitting_request_submit', array($this, 'ajax_submit_request'));
        
        // Cron hooks
        add_action('fitting_request_cleanup', array($this, 'cleanup_old_data'));
        add_action('fitting_request_health_check', array($this, 'run_health_check'));
        
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    /**
     * Initialize admin components
     */
    private function init_admin() {
        if (class_exists('Fitting_Request_Admin')) {
            new Fitting_Request_Admin();
        }
    }
    
    /**
     * Initialize frontend components
     */
    private function init_frontend() {
        if (class_exists('Fitting_Request_Frontend')) {
            new Fitting_Request_Frontend();
        }
    }
    
    /**
     * Plugin initialization
     */
    public function init_plugin() {
        // Schedule cron jobs
        $this->schedule_cron_jobs();
        
        // Set up capabilities
        $this->setup_capabilities();
    }
    
    /**
     * Plugin loaded callback
     */
    public function loaded() {
        do_action('fitting_request_loaded');
    }
    
    /**
     * Schedule cron jobs
     */
    private function schedule_cron_jobs() {
        if (!wp_next_scheduled('fitting_request_cleanup')) {
            wp_schedule_event(time(), 'daily', 'fitting_request_cleanup');
        }
        
        if (!wp_next_scheduled('fitting_request_health_check')) {
            wp_schedule_event(time(), 'hourly', 'fitting_request_health_check');
        }
    }
    
    /**
     * Setup user capabilities
     */
    private function setup_capabilities() {
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $admin_role->add_cap('manage_fitting_requests');
            $admin_role->add_cap('manage_garages');
            $admin_role->add_cap('view_fitting_analytics');
        }
        
        $shop_manager_role = get_role('shop_manager');
        if ($shop_manager_role) {
            $shop_manager_role->add_cap('manage_fitting_requests');
            $shop_manager_role->add_cap('view_fitting_analytics');
        }
    }
    
    /**
     * AJAX handler for getting car models
     */
    public function ajax_get_car_models() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'fitting_request_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'fitting-request-system')));
        }
        
        $make = sanitize_text_field($_POST['make']);
        
        if (empty($make)) {
            wp_send_json_error(array('message' => __('Car make is required', 'fitting-request-system')));
        }
        
        try {
            $models = $this->get_car_models_for_make($make);
            wp_send_json_success(array('models' => $models));
        } catch (Exception $e) {
            $this->error_handler->log_error('Failed to get car models: ' . $e->getMessage(), array('make' => $make));
            wp_send_json_error(array('message' => __('Failed to load car models', 'fitting-request-system')));
        }
    }
    
    /**
     * AJAX handler for submitting fitting request
     */
    public function ajax_submit_request() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'fitting_request_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'fitting-request-system')));
        }
        
        // Rate limiting check
        $ip_address = $this->get_client_ip();
        if (!Fitting_Request_Security::check_rate_limit($ip_address, 'form_submission')) {
            wp_send_json_error(array('message' => __('Too many requests. Please try again later.', 'fitting-request-system')));
        }
        
        // Validate and process request
        $validator = new Fitting_Request_Validator();
        $validation_result = $validator->validate_request($_POST);
        
        if (!$validation_result['is_valid']) {
            wp_send_json_error(array(
                'message' => __('Please fix the errors below', 'fitting-request-system'),
                'errors' => $validation_result['errors']
            ));
        }
        
        try {
            // Process the request (will be implemented in Module 5)
            $request_id = $this->process_fitting_request($validation_result['data']);
            
            wp_send_json_success(array(
                'message' => __('Your fitting request has been submitted successfully!', 'fitting-request-system'),
                'request_id' => $request_id
            ));
        } catch (Exception $e) {
            $this->error_handler->log_error('Failed to process fitting request: ' . $e->getMessage(), $_POST);
            wp_send_json_error(array('message' => __('Failed to submit request. Please try again.', 'fitting-request-system')));
        }
    }
    
    /**
     * Get car models for a specific make from existing taxonomy
     */
    private function get_car_models_for_make($make) {
        $cache_key = 'fitting_request_models_' . sanitize_key($make);
        $models = Fitting_Request_Cache::get($cache_key);
        
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
            
            // Cache for 1 hour
            Fitting_Request_Cache::set($cache_key, $models, HOUR_IN_SECONDS);
        }
        
        return $models;
    }
    
    /**
     * Process fitting request (placeholder for Module 5)
     */
    private function process_fitting_request($data) {
        // This will be implemented in Module 5
        return 'FR_' . uniqid() . '_' . time();
    }
    
    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_scripts() {
        if (is_product()) {
            wp_enqueue_script(
            'fitting-request-vehicle-data',
            FITTING_REQUEST_PLUGIN_URL . 'assets/js/vehicle-data.js',
            array('jquery'),
            $this->version . '.1',
            true
        );
        
        wp_enqueue_style(
            'fitting-request-vehicle-data',
            FITTING_REQUEST_PLUGIN_URL . 'assets/css/vehicle-data.css',
            array(),
            $this->version
        );
        
        // Update localization to include vehicle strings
        wp_localize_script('fitting-request-vehicle-data', 'fittingRequest', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('fitting_request_nonce'),
            'strings' => array(
                'loading' => __('Loading...', 'fitting-request-system'),
                'selectMake' => __('Select Car Make', 'fitting-request-system'),
                'selectModel' => __('Select Car Model', 'fitting-request-system'),
                'searching' => __('Searching...', 'fitting-request-system'),
                'noResults' => __('No vehicles found', 'fitting-request-system'),
                'vehicleSelected' => __('Vehicle selected', 'fitting-request-system'),
                'makeRequired' => __('Please select a car make', 'fitting-request-system'),
                'modelRequired' => __('Please select a car model', 'fitting-request-system'),
                'errorLoadingModels' => __('Error loading models', 'fitting-request-system')
            )
        ));
    }
}    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'fitting-request') !== false) {
            wp_enqueue_script(
                'fitting-request-admin',
                FITTING_REQUEST_PLUGIN_URL . 'assets/js/admin.js',
                array('jquery'),
                $this->version,
                true
            );
            
            wp_enqueue_style(
                'fitting-request-admin',
                FITTING_REQUEST_PLUGIN_URL . 'assets/css/admin.css',
                array(),
                $this->version
            );
        }
    }
    
    /**
     * Cleanup old data (cron job)
     */
    public function cleanup_old_data() {
        $this->database->cleanup_old_records();
    }
    
    /**
     * Run health check (cron job)
     */
    public function run_health_check() {
        // Health check implementation
        $health_status = array(
            'database' => $this->database->check_health(),
            'timestamp' => current_time('mysql')
        );
        
        update_option('fitting_request_health_status', $health_status);
    }
    
    /**
     * Plugin activation
     */
    public static function activate() {
        // Create database tables
        $database = new Fitting_Request_Database();
        $database->create_tables();
        
        // Set default options
        self::set_default_options();
        
        // Schedule cron jobs
        if (!wp_next_scheduled('fitting_request_cleanup')) {
            wp_schedule_event(time(), 'daily', 'fitting_request_cleanup');
        }
        
        if (!wp_next_scheduled('fitting_request_health_check')) {
            wp_schedule_event(time(), 'hourly', 'fitting_request_health_check');
        }
        
        // Set activation transient
        set_transient('fitting_request_activated', true, 30);
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public static function deactivate() {
        // Clear scheduled cron jobs
        wp_clear_scheduled_hook('fitting_request_cleanup');
        wp_clear_scheduled_hook('fitting_request_health_check');
        
        // Clear cache
        wp_cache_flush();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin uninstall
     */
    public static function uninstall() {
        if (!current_user_can('activate_plugins')) {
            return;
        }
        
        // Check if we should remove data
        if (get_option('fitting_request_remove_data_on_uninstall', false)) {
            global $wpdb;
            
            // Drop tables
            $tables = array(
                $wpdb->prefix . 'fitting_requests',
                $wpdb->prefix . 'fitting_quotes',
                $wpdb->prefix . 'fitting_request_status_log',
                $wpdb->prefix . 'fitting_notification_queue',
                $wpdb->prefix . 'fitting_error_logs',
                $wpdb->prefix . 'fitting_rate_limits'
            );
            
            foreach ($tables as $table) {
                $wpdb->query("DROP TABLE IF EXISTS {$table}");
            }
            
            // Remove options
            $options = array(
                'fitting_request_db_version',
                'fitting_request_settings',
                'fitting_request_whatsapp_settings',
                'fitting_request_email_settings',
                'fitting_request_health_status',
                'fitting_request_remove_data_on_uninstall'
            );
            
            foreach ($options as $option) {
                delete_option($option);
            }
            
            // Remove user capabilities
            $roles = array('administrator', 'shop_manager');
            foreach ($roles as $role_name) {
                $role = get_role($role_name);
                if ($role) {
                    $role->remove_cap('manage_fitting_requests');
                    $role->remove_cap('manage_garages');
                    $role->remove_cap('view_fitting_analytics');
                }
            }
        }
    }
    
    /**
     * Set default plugin options
     */
    private static function set_default_options() {
        $default_settings = array(
            'enable_email_notifications' => true,
            'enable_whatsapp_notifications' => false,
            'max_garages_per_request' => 10,
            'request_timeout_hours' => 24,
            'enable_quote_system' => true,
            'enable_feedback_system' => true,
            'admin_email' => get_option('admin_email'),
            'default_emirate' => 'Dubai'
        );
        
        add_option('fitting_request_settings', $default_settings);
        add_option('fitting_request_db_version', '1.0.0');
    }
}