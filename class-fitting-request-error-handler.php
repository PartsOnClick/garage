<?php
/**
 * Error handler class
 *
 * @package FittingRequestSystem
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Fitting_Request_Error_Handler {
    
    /**
     * Instance
     *
     * @var Fitting_Request_Error_Handler
     */
    private static $instance = null;
    
    /**
     * Database instance
     *
     * @var Fitting_Request_Database
     */
    private $database;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->database = new Fitting_Request_Database();
        $this->init();
    }
    
    /**
     * Get instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Initialize error handler
     */
    private function init() {
        // Set up error handlers
        if (defined('WP_DEBUG') && WP_DEBUG) {
            set_error_handler(array($this, 'handle_php_error'));
            set_exception_handler(array($this, 'handle_exception'));
        }
        
        // Add shutdown handler for fatal errors
        register_shutdown_function(array($this, 'handle_fatal_error'));
    }
    
    /**
     * Log error to database
     */
    public function log_error($message, $context = array(), $severity = 'error') {
        try {
            // Log to database
            $this->database->log_error($message, $context, $severity);
            
            // Log to WordPress error log if debug is enabled
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("Fitting Request System [{$severity}]: {$message}");
                
                if (!empty($context)) {
                    error_log("Context: " . wp_json_encode($context));
                }
            }
            
            // Send alert for critical errors
            if ($severity === 'critical') {
                $this->send_critical_error_alert($message, $context);
            }
            
        } catch (Exception $e) {
            // Fallback to error_log if database logging fails
            error_log("Fitting Request System Error Logging Failed: " . $e->getMessage());
            error_log("Original Error: {$message}");
        }
    }
    
    /**
     * Handle PHP errors
     */
    public function handle_php_error($errno, $errstr, $errfile, $errline) {
        // Don't log if error reporting is turned off
        if (!(error_reporting() & $errno)) {
            return false;
        }
        
        $severity_map = array(
            E_ERROR => 'critical',
            E_WARNING => 'warning',
            E_PARSE => 'critical',
            E_NOTICE => 'info',
            E_CORE_ERROR => 'critical',
            E_CORE_WARNING => 'warning',
            E_COMPILE_ERROR => 'critical',
            E_COMPILE_WARNING => 'warning',
            E_USER_ERROR => 'error',
            E_USER_WARNING => 'warning',
            E_USER_NOTICE => 'info',
            E_STRICT => 'info',
            E_RECOVERABLE_ERROR => 'error',
            E_DEPRECATED => 'info',
            E_USER_DEPRECATED => 'info'
        );
        
        $severity = isset($severity_map[$errno]) ? $severity_map[$errno] : 'error';
        
        $this->log_error(
            $errstr,
            array(
                'file' => $errfile,
                'line' => $errline,
                'error_type' => $errno
            ),
            $severity
        );
        
        // Don't execute PHP internal error handler
        return true;
    }
    
    /**
     * Handle uncaught exceptions
     */
    public function handle_exception($exception) {
        $this->log_error(
            $exception->getMessage(),
            array(
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString()
            ),
            'critical'
        );
    }
    
    /**
     * Handle fatal errors
     */
    public function handle_fatal_error() {
        $error = error_get_last();
        
        if ($error !== null && in_array($error['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR))) {
            $this->log_error(
                $error['message'],
                array(
                    'file' => $error['file'],
                    'line' => $error['line'],
                    'type' => 'fatal_error'
                ),
                'critical'
            );
        }
    }
    
    /**
     * Log API failure with fallback
     */
    public function handle_api_failure($service, $error_message, $context = array(), $fallback_callback = null) {
        $this->log_error(
            "API failure for service: {$service} - {$error_message}",
            array_merge($context, array('service' => $service)),
            'warning'
        );
        
        // Execute fallback if provided
        if ($fallback_callback && is_callable($fallback_callback)) {
            try {
                return $fallback_callback();
            } catch (Exception $e) {
                $this->log_error(
                    "Fallback also failed for service: {$service} - " . $e->getMessage(),
                    array('service' => $service),
                    'error'
                );
                return false;
            }
        }
        
        return false;
    }
    
    /**
     * Log validation error
     */
    public function log_validation_error($field, $value, $rule, $context = array()) {
        $this->log_error(
            "Validation failed for field '{$field}' with value '{$value}' against rule '{$rule}'",
            array_merge($context, array(
                'field' => $field,
                'value' => $value,
                'rule' => $rule,
                'type' => 'validation_error'
            )),
            'warning'
        );
    }
    
    /**
     * Log security incident
     */
    public function log_security_incident($incident_type, $description, $context = array()) {
        $this->log_error(
            "Security incident: {$incident_type} - {$description}",
            array_merge($context, array(
                'incident_type' => $incident_type,
                'type' => 'security_incident'
            )),
            'critical'
        );
        
        // Send immediate alert for security incidents
        $this->send_security_alert($incident_type, $description, $context);
    }
    
    /**
     * Log performance issue
     */
    public function log_performance_issue($operation, $duration, $threshold = 2.0, $context = array()) {
        if ($duration > $threshold) {
            $this->log_error(
                "Performance issue: {$operation} took {$duration} seconds (threshold: {$threshold}s)",
                array_merge($context, array(
                    'operation' => $operation,
                    'duration' => $duration,
                    'threshold' => $threshold,
                    'type' => 'performance_issue'
                )),
                'warning'
            );
        }
    }
    
    /**
     * Get error statistics
     */
    public function get_error_statistics($days = 7) {
        global $wpdb;
        
        $table = $this->database->get_table('error_logs');
        $date_from = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        return array(
            'total_errors' => $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table} WHERE timestamp >= %s",
                    $date_from
                )
            ),
            'by_severity' => $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT severity, COUNT(*) as count 
                    FROM {$table} 
                    WHERE timestamp >= %s 
                    GROUP BY severity 
                    ORDER BY count DESC",
                    $date_from
                )
            ),
            'recent_critical' => $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT error_message, timestamp 
                    FROM {$table} 
                    WHERE severity = 'critical' AND timestamp >= %s 
                    ORDER BY timestamp DESC 
                    LIMIT 10",
                    $date_from
                )
            ),
            'error_trends' => $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT DATE(timestamp) as date, severity, COUNT(*) as count 
                    FROM {$table} 
                    WHERE timestamp >= %s 
                    GROUP BY DATE(timestamp), severity 
                    ORDER BY date DESC",
                    $date_from
                )
            )
        );
    }
    
    /**
     * Send critical error alert
     */
    private function send_critical_error_alert($message, $context = array()) {
        $admin_email = get_option('admin_email');
        $site_name = get_bloginfo('name');
        
        $subject = "[{$site_name}] Critical Error Alert - Fitting Request System";
        
        $body = "A critical error has occurred in the Fitting Request System:\n\n";
        $body .= "Error Message: {$message}\n";
        $body .= "Timestamp: " . current_time('mysql') . "\n";
        $body .= "URL: " . (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : 'N/A') . "\n";
        $body .= "User Agent: " . (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'N/A') . "\n";
        
        if (!empty($context)) {
            $body .= "\nContext:\n" . print_r($context, true);
        }
        
        $body .= "\nPlease check the admin dashboard for more details.";
        
        wp_mail($admin_email, $subject, $body);
    }
    
    /**
     * Send security alert
     */
    private function send_security_alert($incident_type, $description, $context = array()) {
        $admin_email = get_option('admin_email');
        $site_name = get_bloginfo('name');
        
        $subject = "[{$site_name}] SECURITY ALERT - {$incident_type}";
        
        $body = "A security incident has been detected:\n\n";
        $body .= "Incident Type: {$incident_type}\n";
        $body .= "Description: {$description}\n";
        $body .= "Timestamp: " . current_time('mysql') . "\n";
        $body .= "IP Address: " . $this->get_client_ip() . "\n";
        $body .= "User Agent: " . (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'N/A') . "\n";
        
        if (!empty($context)) {
            $body .= "\nContext:\n" . print_r($context, true);
        }
        
        $body .= "\nImmediate action may be required.";
        
        wp_mail($admin_email, $subject, $body);
    }
    
    /**
     * Clear resolved errors
     */
    public function mark_errors_resolved($error_ids) {
        global $wpdb;
        
        $table = $this->database->get_table('error_logs');
        $placeholders = implode(',', array_fill(0, count($error_ids), '%d'));
        
        return $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET resolved = 1 WHERE id IN ({$placeholders})",
                $error_ids
            )
        );
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
     * Debug helper for development
     */
    public function debug($message, $context = array()) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $this->log_error($message, $context, 'info');
        }
    }
    
    /**
     * Test error logging system
     */
    public function test_error_logging() {
        if (!current_user_can('manage_options')) {
            return false;
        }
        
        $test_errors = array(
            array('message' => 'Test info message', 'severity' => 'info'),
            array('message' => 'Test warning message', 'severity' => 'warning'),
            array('message' => 'Test error message', 'severity' => 'error')
        );
        
        foreach ($test_errors as $error) {
            $this->log_error($error['message'], array('test' => true), $error['severity']);
        }
        
        return true;
    }
}