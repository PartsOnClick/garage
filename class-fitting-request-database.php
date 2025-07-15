<?php
/**
 * Database manager class
 *
 * @package FittingRequestSystem
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Fitting_Request_Database {
    
    /**
     * Database version
     *
     * @var string
     */
    private $version = '1.0.0';
    
    /**
     * Table names
     *
     * @var array
     */
    private $tables = array();
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        
        // Initialize table names
        $this->tables = array(
            'requests' => $wpdb->prefix . 'fitting_requests',
            'quotes' => $wpdb->prefix . 'fitting_quotes',
            'status_log' => $wpdb->prefix . 'fitting_request_status_log',
            'notification_queue' => $wpdb->prefix . 'fitting_notification_queue',
            'error_logs' => $wpdb->prefix . 'fitting_error_logs',
            'rate_limits' => $wpdb->prefix . 'fitting_rate_limits'
        );
    }
    
    /**
     * Create all database tables
     */
    public function create_tables() {
        global $wpdb;
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Create fitting requests table
        $sql_requests = "CREATE TABLE {$this->tables['requests']} (
            id INT AUTO_INCREMENT PRIMARY KEY,
            request_id VARCHAR(64) UNIQUE NOT NULL,
            product_id INT NOT NULL,
            car_make VARCHAR(100) NOT NULL,
            car_model VARCHAR(100) NOT NULL,
            customer_email VARCHAR(255),
            customer_whatsapp VARCHAR(50),
            selected_emirate VARCHAR(50) NOT NULL,
            request_date DATETIME NOT NULL,
            garages_notified INT DEFAULT 0,
            status ENUM('pending', 'sent', 'quotes_received', 'completed', 'cancelled') DEFAULT 'pending',
            priority TINYINT DEFAULT 5,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            INDEX idx_request_id (request_id),
            INDEX idx_date_status (request_date, status),
            INDEX idx_emirate_status (selected_emirate, status),
            INDEX idx_product_date (product_id, request_date),
            INDEX idx_status_priority (status, priority),
            FULLTEXT KEY ft_search (car_make, car_model)
        ) $charset_collate;";
        
        dbDelta($sql_requests);
        
        // Create quotes table
        $sql_quotes = "CREATE TABLE {$this->tables['quotes']} (
            id INT AUTO_INCREMENT PRIMARY KEY,
            request_id VARCHAR(64) NOT NULL,
            garage_id INT NOT NULL,
            quote_amount DECIMAL(10,2) NOT NULL,
            estimated_time VARCHAR(50),
            notes TEXT,
            status ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
            submission_date DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            INDEX idx_request_id (request_id),
            INDEX idx_garage_id (garage_id),
            INDEX idx_status (status),
            INDEX idx_submission_date (submission_date),
            UNIQUE KEY unique_quote (request_id, garage_id)
        ) $charset_collate;";
        
        dbDelta($sql_quotes);
        
        // Create status log table
        $sql_status_log = "CREATE TABLE {$this->tables['status_log']} (
            id INT AUTO_INCREMENT PRIMARY KEY,
            request_id VARCHAR(64) NOT NULL,
            old_status VARCHAR(20),
            new_status VARCHAR(20) NOT NULL,
            changed_by INT,
            changed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            notes TEXT,
            
            INDEX idx_request_id (request_id),
            INDEX idx_changed_at (changed_at),
            INDEX idx_changed_by (changed_by)
        ) $charset_collate;";
        
        dbDelta($sql_status_log);
        
        // Create notification queue table
        $sql_notification_queue = "CREATE TABLE {$this->tables['notification_queue']} (
            id INT AUTO_INCREMENT PRIMARY KEY,
            request_id VARCHAR(64) NOT NULL,
            notification_type ENUM('email', 'whatsapp', 'sms') NOT NULL,
            recipients TEXT NOT NULL,
            priority TINYINT DEFAULT 5,
            status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
            attempts TINYINT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            scheduled_for DATETIME DEFAULT CURRENT_TIMESTAMP,
            completed_at DATETIME NULL,
            error_message TEXT,
            
            INDEX idx_status_priority (status, priority),
            INDEX idx_scheduled_for (scheduled_for),
            INDEX idx_request_id (request_id),
            INDEX idx_notification_type (notification_type)
        ) $charset_collate;";
        
        dbDelta($sql_notification_queue);
        
        // Create error logs table
        $sql_error_logs = "CREATE TABLE {$this->tables['error_logs']} (
            id INT AUTO_INCREMENT PRIMARY KEY,
            error_message TEXT NOT NULL,
            context TEXT,
            severity ENUM('info', 'warning', 'error', 'critical') DEFAULT 'error',
            user_id INT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
            stack_trace TEXT,
            resolved BOOLEAN DEFAULT FALSE,
            
            INDEX idx_timestamp (timestamp),
            INDEX idx_severity (severity),
            INDEX idx_user_id (user_id),
            INDEX idx_resolved (resolved)
        ) $charset_collate;";
        
        dbDelta($sql_error_logs);
        
        // Create rate limits table
        $sql_rate_limits = "CREATE TABLE {$this->tables['rate_limits']} (
            id INT AUTO_INCREMENT PRIMARY KEY,
            identifier VARCHAR(100) NOT NULL,
            action VARCHAR(50) NOT NULL,
            count INT DEFAULT 1,
            window_start DATETIME DEFAULT CURRENT_TIMESTAMP,
            
            UNIQUE KEY unique_rate_limit (identifier, action),
            INDEX idx_window_start (window_start)
        ) $charset_collate;";
        
        dbDelta($sql_rate_limits);
        
        // Update database version
        update_option('fitting_request_db_version', $this->version);
        
        // Log successful table creation
        error_log('Fitting Request System: Database tables created successfully');
    }
    
    /**
     * Check if tables exist and are up to date
     */
    public function check_tables() {
        global $wpdb;
        
        $missing_tables = array();
        
        foreach ($this->tables as $key => $table_name) {
            if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") != $table_name) {
                $missing_tables[] = $key;
            }
        }
        
        return empty($missing_tables) ? true : $missing_tables;
    }
    
    /**
     * Get table name
     */
    public function get_table($table_key) {
        return isset($this->tables[$table_key]) ? $this->tables[$table_key] : false;
    }
    
    /**
     * Insert request
     */
    public function insert_request($data) {
        global $wpdb;
        
        $defaults = array(
            'request_id' => '',
            'product_id' => 0,
            'car_make' => '',
            'car_model' => '',
            'customer_email' => '',
            'customer_whatsapp' => '',
            'selected_emirate' => '',
            'request_date' => current_time('mysql'),
            'status' => 'pending',
            'priority' => 5
        );
        
        $data = wp_parse_args($data, $defaults);
        
        $result = $wpdb->insert(
            $this->tables['requests'],
            $data,
            array('%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d')
        );
        
        if (false === $result) {
            throw new Exception('Failed to insert request: ' . $wpdb->last_error);
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Get request by ID
     */
    public function get_request($request_id) {
        global $wpdb;
        
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->tables['requests']} WHERE request_id = %s",
                $request_id
            )
        );
    }
    
    /**
     * Update request status
     */
    public function update_request_status($request_id, $new_status, $notes = '') {
        global $wpdb;
        
        // Get current status
        $current_request = $this->get_request($request_id);
        if (!$current_request) {
            throw new Exception('Request not found');
        }
        
        $old_status = $current_request->status;
        
        // Update status
        $result = $wpdb->update(
            $this->tables['requests'],
            array('status' => $new_status),
            array('request_id' => $request_id),
            array('%s'),
            array('%s')
        );
        
        if (false === $result) {
            throw new Exception('Failed to update request status: ' . $wpdb->last_error);
        }
        
        // Log status change
        $this->log_status_change($request_id, $old_status, $new_status, $notes);
        
        return true;
    }
    
    /**
     * Log status change
     */
    public function log_status_change($request_id, $old_status, $new_status, $notes = '') {
        global $wpdb;
        
        $wpdb->insert(
            $this->tables['status_log'],
            array(
                'request_id' => $request_id,
                'old_status' => $old_status,
                'new_status' => $new_status,
                'changed_by' => get_current_user_id(),
                'notes' => $notes
            ),
            array('%s', '%s', '%s', '%d', '%s')
        );
    }
    
    /**
     * Insert quote
     */
    public function insert_quote($data) {
        global $wpdb;
        
        $defaults = array(
            'request_id' => '',
            'garage_id' => 0,
            'quote_amount' => 0.00,
            'estimated_time' => '',
            'notes' => '',
            'status' => 'pending'
        );
        
        $data = wp_parse_args($data, $defaults);
        
        $result = $wpdb->insert(
            $this->tables['quotes'],
            $data,
            array('%s', '%d', '%f', '%s', '%s', '%s')
        );
        
        if (false === $result) {
            throw new Exception('Failed to insert quote: ' . $wpdb->last_error);
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Get quotes for request
     */
    public function get_quotes_for_request($request_id) {
        global $wpdb;
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT q.*, p.post_title as garage_name 
                FROM {$this->tables['quotes']} q
                LEFT JOIN {$wpdb->posts} p ON q.garage_id = p.ID
                WHERE q.request_id = %s
                ORDER BY q.submission_date ASC",
                $request_id
            )
        );
    }
    
    /**
     * Add to notification queue
     */
    public function add_to_notification_queue($request_id, $type, $recipients, $priority = 5) {
        global $wpdb;
        
        $recipients_json = is_array($recipients) ? wp_json_encode($recipients) : $recipients;
        
        return $wpdb->insert(
            $this->tables['notification_queue'],
            array(
                'request_id' => $request_id,
                'notification_type' => $type,
                'recipients' => $recipients_json,
                'priority' => $priority
            ),
            array('%s', '%s', '%s', '%d')
        );
    }
    
    /**
     * Get pending notifications
     */
    public function get_pending_notifications($limit = 10) {
        global $wpdb;
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->tables['notification_queue']} 
                WHERE status = 'pending' 
                AND scheduled_for <= %s 
                AND attempts < 3 
                ORDER BY priority DESC, created_at ASC 
                LIMIT %d",
                current_time('mysql'),
                $limit
            )
        );
    }
    
    /**
     * Update notification status
     */
    public function update_notification_status($id, $status, $error_message = '') {
        global $wpdb;
        
        $data = array('status' => $status);
        $format = array('%s');
        
        if ($status === 'completed') {
            $data['completed_at'] = current_time('mysql');
            $format[] = '%s';
        }
        
        if (!empty($error_message)) {
            $data['error_message'] = $error_message;
            $format[] = '%s';
        }
        
        // Increment attempts
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$this->tables['notification_queue']} 
                SET attempts = attempts + 1 
                WHERE id = %d",
                $id
            )
        );
        
        return $wpdb->update(
            $this->tables['notification_queue'],
            $data,
            array('id' => $id),
            $format,
            array('%d')
        );
    }
    
    /**
     * Log error
     */
    public function log_error($message, $context = array(), $severity = 'error') {
        global $wpdb;
        
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        $ip_address = $this->get_client_ip();
        
        return $wpdb->insert(
            $this->tables['error_logs'],
            array(
                'error_message' => $message,
                'context' => wp_json_encode($context),
                'severity' => $severity,
                'user_id' => get_current_user_id(),
                'ip_address' => $ip_address,
                'user_agent' => $user_agent,
                'stack_trace' => wp_debug_backtrace_summary()
            ),
            array('%s', '%s', '%s', '%d', '%s', '%s', '%s')
        );
    }
    
    /**
     * Check rate limit
     */
    public function check_rate_limit($identifier, $action, $limit, $window_seconds) {
        global $wpdb;
        
        // Clean up old records
        $window_start = date('Y-m-d H:i:s', time() - $window_seconds);
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->tables['rate_limits']} 
                WHERE window_start < %s",
                $window_start
            )
        );
        
        // Check current count
        $current_count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT count FROM {$this->tables['rate_limits']} 
                WHERE identifier = %s AND action = %s",
                $identifier,
                $action
            )
        );
        
        if ($current_count >= $limit) {
            return false;
        }
        
        // Update or insert count
        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$this->tables['rate_limits']} (identifier, action, count, window_start) 
                VALUES (%s, %s, 1, %s) 
                ON DUPLICATE KEY UPDATE count = count + 1",
                $identifier,
                $action,
                current_time('mysql')
            )
        );
        
        return true;
    }
    
    /**
     * Get statistics
     */
    public function get_statistics($days = 30) {
        global $wpdb;
        
        $date_from = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        return array(
            'total_requests' => $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$this->tables['requests']} 
                    WHERE created_at >= %s",
                    $date_from
                )
            ),
            'pending_requests' => $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$this->tables['requests']} 
                    WHERE status = 'pending' AND created_at >= %s",
                    $date_from
                )
            ),
            'completed_requests' => $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$this->tables['requests']} 
                    WHERE status = 'completed' AND created_at >= %s",
                    $date_from
                )
            ),
            'total_quotes' => $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$this->tables['quotes']} q
                    INNER JOIN {$this->tables['requests']} r ON q.request_id = r.request_id
                    WHERE r.created_at >= %s",
                    $date_from
                )
            ),
            'average_quotes_per_request' => $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT AVG(quote_count) FROM (
                        SELECT COUNT(*) as quote_count 
                        FROM {$this->tables['quotes']} q
                        INNER JOIN {$this->tables['requests']} r ON q.request_id = r.request_id
                        WHERE r.created_at >= %s
                        GROUP BY q.request_id
                    ) as quote_stats",
                    $date_from
                )
            )
        );
    }
    
    /**
     * Cleanup old records
     */
    public function cleanup_old_records() {
        global $wpdb;
        
        // Clean up old completed requests (6 months)
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->tables['requests']} 
                WHERE status = 'completed' 
                AND created_at < %s",
                date('Y-m-d H:i:s', strtotime('-6 months'))
            )
        );
        
        // Clean up old error logs (3 months, except critical)
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->tables['error_logs']} 
                WHERE timestamp < %s 
                AND severity != 'critical'",
                date('Y-m-d H:i:s', strtotime('-3 months'))
            )
        );
        
        // Clean up old rate limit records (1 day)
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->tables['rate_limits']} 
                WHERE window_start < %s",
                date('Y-m-d H:i:s', strtotime('-1 day'))
            )
        );
        
        // Clean up completed notifications (1 week)
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->tables['notification_queue']} 
                WHERE status = 'completed' 
                AND completed_at < %s",
                date('Y-m-d H:i:s', strtotime('-1 week'))
            )
        );
        
        // Optimize tables
        foreach ($this->tables as $table) {
            $wpdb->query("OPTIMIZE TABLE {$table}");
        }
        
        error_log('Fitting Request System: Database cleanup completed');
    }
    
    /**
     * Check database health
     */
    public function check_health() {
        global $wpdb;
        
        try {
            // Test database connection
            $result = $wpdb->get_var("SELECT 1");
            if ($result !== '1') {
                return array('status' => 'error', 'message' => 'Database connection failed');
            }
            
            // Check if tables exist
            $missing_tables = $this->check_tables();
            if ($missing_tables !== true) {
                return array(
                    'status' => 'error', 
                    'message' => 'Missing tables: ' . implode(', ', $missing_tables)
                );
            }
            
            // Check for stuck notifications
            $stuck_notifications = $wpdb->get_var(
                "SELECT COUNT(*) FROM {$this->tables['notification_queue']} 
                WHERE status = 'processing' 
                AND created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)"
            );
            
            if ($stuck_notifications > 0) {
                return array(
                    'status' => 'warning', 
                    'message' => "Found {$stuck_notifications} stuck notifications"
                );
            }
            
            return array('status' => 'healthy', 'message' => 'Database is healthy');
            
        } catch (Exception $e) {
            return array('status' => 'error', 'message' => $e->getMessage());
        }
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
}