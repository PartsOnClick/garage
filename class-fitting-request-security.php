<?php
/**
 * Security handler class
 *
 * @package FittingRequestSystem
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Fitting_Request_Security {
    
    /**
     * Rate limit configuration
     *
     * @var array
     */
    private static $rate_limits = array(
        'form_submission' => array('limit' => 5, 'window' => 300), // 5 per 5 minutes
        'status_check' => array('limit' => 20, 'window' => 300),   // 20 per 5 minutes
        'quote_submission' => array('limit' => 10, 'window' => 600) // 10 per 10 minutes
    );
    
    /**
     * Database instance
     *
     * @var Fitting_Request_Database
     */
    private $database;
    
    /**
     * Error handler instance
     *
     * @var Fitting_Request_Error_Handler
     */
    private $error_handler;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->database = new Fitting_Request_Database();
        $this->error_handler = Fitting_Request_Error_Handler::get_instance();
        $this->init();
    }
    
    /**
     * Initialize security measures
     */
    private function init() {
        // Add security headers
        add_action('wp_headers', array($this, 'add_security_headers'));
        
        // Add honeypot field to forms
        add_action('wp_footer', array($this, 'add_honeypot_css'));
        
        // Block suspicious requests
        add_action('init', array($this, 'block_suspicious_requests'), 1);
    }
    
    /**
     * Add security headers
     */
    public function add_security_headers($headers) {
        $headers['X-Content-Type-Options'] = 'nosniff';
        $headers['X-Frame-Options'] = 'SAMEORIGIN';
        $headers['X-XSS-Protection'] = '1; mode=block';
        $headers['Referrer-Policy'] = 'strict-origin-when-cross-origin';
        
        return $headers;
    }
    
    /**
     * Add honeypot CSS to hide honeypot fields
     */
    public function add_honeypot_css() {
        echo '<style>.fitting-honeypot{position:absolute!important;left:-9999px!important;}</style>';
    }
    
    /**
     * Block suspicious requests
     */
    public function block_suspicious_requests() {
        $ip = $this->get_client_ip();
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Block known bad user agents
        $bad_user_agents = array(
            'sqlmap',
            'nikto',
            'netsparker',
            'acunetix',
            'nessus'
        );
        
        foreach ($bad_user_agents as $bad_agent) {
            if (stripos($user_agent, $bad_agent) !== false) {
                $this->error_handler->log_security_incident(
                    'malicious_user_agent',
                    "Blocked request with suspicious user agent: {$user_agent}",
                    array('ip' => $ip, 'user_agent' => $user_agent)
                );
                
                wp_die('Access denied', 'Security Error', array('response' => 403));
            }
        }
        
        // Block requests with suspicious patterns
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        $suspicious_patterns = array(
            '/\.\./i',
            '/union.*select/i',
            '/script.*alert/i',
            '/<script/i',
            '/javascript:/i'
        );
        
        foreach ($suspicious_patterns as $pattern) {
            if (preg_match($pattern, $request_uri)) {
                $this->error_handler->log_security_incident(
                    'suspicious_request_pattern',
                    "Blocked request with suspicious pattern in URI: {$request_uri}",
                    array('ip' => $ip, 'pattern' => $pattern)
                );
                
                wp_die('Access denied', 'Security Error', array('response' => 403));
            }
        }
    }
    
    /**
     * Check rate limit
     */
    public static function check_rate_limit($identifier, $action) {
        if (!isset(self::$rate_limits[$action])) {
            return true;
        }
        
        $limit = self::$rate_limits[$action]['limit'];
        $window = self::$rate_limits[$action]['window'];
        
        $database = new Fitting_Request_Database();
        
        return $database->check_rate_limit($identifier, $action, $limit, $window);
    }
    
    /**
     * Generate secure token
     */
    public static function generate_token($data, $expiry_hours = 24) {
        $timestamp = time();
        $expires = $timestamp + ($expiry_hours * HOUR_IN_SECONDS);
        
        $token_data = array(
            'data' => $data,
            'timestamp' => $timestamp,
            'expires' => $expires,
            'nonce' => wp_create_nonce('fitting_request_token_' . $timestamp)
        );
        
        $token_string = base64_encode(wp_json_encode($token_data));
        $signature = hash_hmac('sha256', $token_string, wp_salt() . SECURE_AUTH_SALT);
        
        return $token_string . '.' . $signature;
    }
    
    /**
     * Validate secure token
     */
    public static function validate_token($token, $expected_data = null) {
        if (empty($token)) {
            return false;
        }
        
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return false;
        }
        
        list($token_string, $signature) = $parts;
        
        // Verify signature
        $expected_signature = hash_hmac('sha256', $token_string, wp_salt() . SECURE_AUTH_SALT);
        if (!hash_equals($expected_signature, $signature)) {
            return false;
        }
        
        // Decode token data
        $token_data = json_decode(base64_decode($token_string), true);
        if (!$token_data) {
            return false;
        }
        
        // Check expiration
        if (time() > $token_data['expires']) {
            return false;
        }
        
        // Verify nonce
        if (!wp_verify_nonce($token_data['nonce'], 'fitting_request_token_' . $token_data['timestamp'])) {
            return false;
        }
        
        // Check expected data if provided
        if ($expected_data !== null && $token_data['data'] !== $expected_data) {
            return false;
        }
        
        return $token_data['data'];
    }
    
    /**
     * Sanitize and validate email
     */
    public static function sanitize_email($email) {
        $email = sanitize_email($email);
        
        if (!is_email($email)) {
            return false;
        }
        
        // Additional email validation
        $domain = substr(strrchr($email, "@"), 1);
        
        // Block temporary email domains
        $temp_domains = array(
            '10minutemail.com',
            'guerrillamail.com',
            'mailinator.com',
            'tempmail.org'
        );
        
        if (in_array($domain, $temp_domains)) {
            return false;
        }
        
        return $email;
    }
    
    /**
     * Sanitize and validate phone number
     */
    public static function sanitize_phone($phone) {
        // Remove all non-numeric characters except +
        $phone = preg_replace('/[^+\d]/', '', $phone);
        
        // Validate UAE phone number format
        if (preg_match('/^\+971[0-9]{8,9}$/', $phone)) {
            return $phone;
        }
        
        // Try to format local numbers
        if (preg_match('/^(50|51|52|54|55|56|58)[0-9]{7}$/', $phone)) {
            return '+971' . $phone;
        }
        
        return false;
    }
    
    /**
     * Generate honeypot field
     */
    public static function generate_honeypot_field() {
        $field_name = 'website_url_' . wp_create_nonce('honeypot');
        
        return '<input type="text" name="' . $field_name . '" value="" class="fitting-honeypot" tabindex="-1" autocomplete="off">';
    }
    
    /**
     * Check honeypot field
     */
    public static function check_honeypot($post_data) {
        foreach ($post_data as $key => $value) {
            if (strpos($key, 'website_url_') === 0 && !empty($value)) {
                return false; // Bot detected
            }
        }
        
        return true; // Passed honeypot test
    }
    
    /**
     * Encrypt sensitive data
     */
    public static function encrypt_data($data) {
        $key = self::get_encryption_key();
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
        
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Decrypt sensitive data
     */
    public static function decrypt_data($encrypted_data) {
        $key = self::get_encryption_key();
        $data = base64_decode($encrypted_data);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }
    
    /**
     * Get or generate encryption key
     */
    private static function get_encryption_key() {
        $key = get_option('fitting_request_encryption_key');
        
        if (!$key) {
            $key = base64_encode(random_bytes(32));
            update_option('fitting_request_encryption_key', $key, false);
        }
        
        return base64_decode($key);
    }
    
    /**
     * Validate CSRF token
     */
    public static function validate_csrf($token, $action = 'fitting_request_nonce') {
        return wp_verify_nonce($token, $action);
    }
    
    /**
     * Generate CSRF token
     */
    public static function generate_csrf($action = 'fitting_request_nonce') {
        return wp_create_nonce($action);
    }
    
    /**
     * Sanitize input data
     */
    public static function sanitize_input($data, $type = 'text') {
        switch ($type) {
            case 'email':
                return self::sanitize_email($data);
                
            case 'phone':
                return self::sanitize_phone($data);
                
            case 'int':
                return intval($data);
                
            case 'float':
                return floatval($data);
                
            case 'text':
            default:
                return sanitize_text_field($data);
        }
    }
    
    /**
     * Log failed login attempt
     */
    public function log_failed_login($username) {
        $ip = $this->get_client_ip();
        
        $this->error_handler->log_security_incident(
            'failed_login_attempt',
            "Failed login attempt for username: {$username}",
            array(
                'username' => $username,
                'ip' => $ip,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
            )
        );
        
        // Implement temporary lockout after multiple failed attempts
        $failed_attempts = get_transient('fitting_failed_login_' . $ip);
        $failed_attempts = $failed_attempts ? $failed_attempts + 1 : 1;
        
        set_transient('fitting_failed_login_' . $ip, $failed_attempts, 15 * MINUTE_IN_SECONDS);
        
        if ($failed_attempts >= 5) {
            $this->error_handler->log_security_incident(
                'ip_lockout',
                "IP address locked out after {$failed_attempts} failed login attempts",
                array('ip' => $ip)
            );
        }
    }
    
    /**
     * Check if IP is locked out
     */
    public function is_ip_locked_out($ip = null) {
        if (!$ip) {
            $ip = $this->get_client_ip();
        }
        
        $failed_attempts = get_transient('fitting_failed_login_' . $ip);
        return $failed_attempts >= 5;
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
     * Generate secure random string
     */
    public static function generate_random_string($length = 32) {
        return bin2hex(random_bytes($length / 2));
    }
    
    /**
     * Hash password securely
     */
    public static function hash_password($password) {
        return wp_hash_password($password);
    }
    
    /**
     * Verify password hash
     */
    public static function verify_password($password, $hash) {
        return wp_check_password($password, $hash);
    }
}