<?php
/**
 * Temporary admin class for Module 1
 *
 * @package FittingRequestSystem
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Fitting_Request_Admin {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'init_settings'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_options_page(
            __('Fitting Request Settings', 'fitting-request-system'),
            __('Fitting Request', 'fitting-request-system'),
            'manage_options',
            'fitting-request-settings',
            array($this, 'settings_page')
        );
    }
    
    /**
     * Initialize settings
     */
    public function init_settings() {
        register_setting('fitting_request_settings', 'fitting_request_settings');
    }
    
    /**
     * Settings page
     */
    public function settings_page() {
        $settings = get_option('fitting_request_settings', array());
        $database = new Fitting_Request_Database();
        $health_status = $database->check_health();
        $stats = $database->get_statistics(7);
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="notice notice-info">
                <p><strong>Module 1 Status:</strong> ? Core Foundation Active</p>
                <p><strong>Next:</strong> Module 2 - Vehicle Data Integration</p>
            </div>
            
            <div class="card" style="max-width: 100%; margin-top: 20px;">
                <h2>System Status</h2>
                <table class="form-table">
                    <tr>
                        <th>Plugin Version</th>
                        <td><?php echo FITTING_REQUEST_VERSION; ?></td>
                    </tr>
                    <tr>
                        <th>Database Health</th>
                        <td>
                            <span class="dashicons dashicons-<?php echo $health_status['status'] === 'healthy' ? 'yes-alt' : 'warning'; ?>"></span>
                            <?php echo esc_html($health_status['message']); ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Database Tables</th>
                        <td>
                            <?php
                            $missing_tables = $database->check_tables();
                            if ($missing_tables === true) {
                                echo '<span class="dashicons dashicons-yes-alt"></span> All 6 tables created successfully';
                            } else {
                                echo '<span class="dashicons dashicons-warning"></span> Missing tables: ' . implode(', ', $missing_tables);
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th>WooCommerce</th>
                        <td>
                            <?php if (class_exists('WooCommerce')): ?>
                                <span class="dashicons dashicons-yes-alt"></span> Active (Version: <?php echo WC()->version; ?>)
                            <?php else: ?>
                                <span class="dashicons dashicons-warning"></span> Not active
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Vehicles Taxonomy</th>
                        <td>
                            <?php 
                            $vehicles_count = wp_count_terms('vehicles');
                            if (!is_wp_error($vehicles_count) && $vehicles_count > 0):
                            ?>
                                <span class="dashicons dashicons-yes-alt"></span> <?php echo $vehicles_count; ?> vehicles found
                            <?php else: ?>
                                <span class="dashicons dashicons-info"></span> No vehicles found or taxonomy doesn't exist
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="card" style="max-width: 100%; margin-top: 20px;">
                <h2>Statistics (Last 7 Days)</h2>
                <table class="form-table">
                    <tr>
                        <th>Total Requests</th>
                        <td><?php echo intval($stats['total_requests']); ?></td>
                    </tr>
                    <tr>
                        <th>Pending Requests</th>
                        <td><?php echo intval($stats['pending_requests']); ?></td>
                    </tr>
                    <tr>
                        <th>Completed Requests</th>
                        <td><?php echo intval($stats['completed_requests']); ?></td>
                    </tr>
                    <tr>
                        <th>Total Quotes</th>
                        <td><?php echo intval($stats['total_quotes']); ?></td>
                    </tr>
                </table>
            </div>
            
            <div class="card" style="max-width: 100%; margin-top: 20px;">
                <h2>Basic Settings</h2>
                <form method="post" action="options.php">
                    <?php settings_fields('fitting_request_settings'); ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Admin Email</th>
                            <td>
                                <input type="email" name="fitting_request_settings[admin_email]" 
                                       value="<?php echo esc_attr($settings['admin_email'] ?? get_option('admin_email')); ?>" 
                                       class="regular-text" />
                                <p class="description">Email address for system notifications</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Max Garages per Request</th>
                            <td>
                                <input type="number" name="fitting_request_settings[max_garages_per_request]" 
                                       value="<?php echo intval($settings['max_garages_per_request'] ?? 10); ?>" 
                                       min="1" max="50" />
                                <p class="description">Maximum number of garages to notify per request</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Request Timeout (hours)</th>
                            <td>
                                <input type="number" name="fitting_request_settings[request_timeout_hours]" 
                                       value="<?php echo intval($settings['request_timeout_hours'] ?? 24); ?>" 
                                       min="1" max="168" />
                                <p class="description">How long to keep requests active</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Enable Email Notifications</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="fitting_request_settings[enable_email_notifications]" 
                                           value="1" <?php checked($settings['enable_email_notifications'] ?? true); ?> />
                                    Send email notifications to garages
                                </label>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button(); ?>
                </form>
            </div>
            
            <div class="card" style="max-width: 100%; margin-top: 20px;">
                <h2>Development Tools</h2>
                <p><strong>Test Error Logging:</strong></p>
                <p>Add <code>?test_fitting_error=1</code> to any page URL to test error logging system.</p>
                
                <p><strong>Clear Cache:</strong></p>
                <a href="<?php echo wp_nonce_url(admin_url('options-general.php?page=fitting-request-settings&action=clear_cache'), 'clear_cache'); ?>" 
                   class="button">Clear Plugin Cache</a>
                
                <?php if (isset($_GET['action']) && $_GET['action'] === 'clear_cache' && wp_verify_nonce($_GET['_wpnonce'], 'clear_cache')): ?>
                    <?php 
                    $cache = new Fitting_Request_Cache();
                    $cache->clear_all_cache();
                    ?>
                    <div class="notice notice-success"><p>Cache cleared successfully!</p></div>
                <?php endif; ?>
            </div>
            
            <div class="card" style="max-width: 100%; margin-top: 20px;">
                <h2>Module Progress</h2>
                <ul>
                    <li>? <strong>Module 1:</strong> Core Foundation & Database Setup</li>
                    <li>?? <strong>Module 2:</strong> Vehicle Data Integration (Next)</li>
                    <li>? <strong>Module 3:</strong> Frontend Form System</li>
                    <li>? <strong>Module 4:</strong> Garage Registration System</li>
                    <li>? <strong>Module 5:</strong> Request Processing Engine</li>
                    <li>? <strong>Module 6:</strong> Email Notification System</li>
                    <li>? <strong>Module 7:</strong> WhatsApp Cloud API Integration</li>
                    <li>? <strong>Module 8:</strong> Quote Management System</li>
                    <li>? <strong>Module 9:</strong> Admin Dashboard & Analytics</li>
                    <li>? <strong>Module 10:</strong> Security & Performance</li>
                    <li>? <strong>Module 11:</strong> Status Portal & Customer Interface</li>
                    <li>? <strong>Module 12:</strong> Testing & Quality Assurance</li>
                </ul>
            </div>
        </div>
        
        <style>
        .card {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
            margin: 20px 0;
        }
        .card h2 {
            margin-top: 0;
        }
        .dashicons-yes-alt {
            color: #00a32a;
        }
        .dashicons-warning {
            color: #dba617;
        }
        .dashicons-info {
            color: #72aee6;
        }
        </style>
        <?php
    }
}