<?php
/**
 * Vehicle Data Manager
 * Handles integration with existing vehicles taxonomy and provides car data
 *
 * @package FittingRequestSystem
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Fitting_Request_Vehicle_Data {
    
    /**
     * Cache group for vehicle data
     */
    const CACHE_GROUP = 'fitting_request_vehicles';
    
    /**
     * Cache expiration time (24 hours)
     */
    const CACHE_EXPIRATION = DAY_IN_SECONDS;
    
    /**
     * Initialize the vehicle data manager
     */
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_ajax_fitting_get_car_models', array($this, 'ajax_get_car_models'));
        add_action('wp_ajax_nopriv_fitting_get_car_models', array($this, 'ajax_get_car_models'));
        add_action('wp_ajax_fitting_search_vehicles', array($this, 'ajax_search_vehicles'));
        add_action('wp_ajax_nopriv_fitting_search_vehicles', array($this, 'ajax_search_vehicles'));
        add_action('wp_ajax_fitting_get_car_makes', array($this, 'ajax_get_car_makes'));
        add_action('wp_ajax_nopriv_fitting_get_car_makes', array($this, 'ajax_get_car_makes'));
        
        // Clear cache when taxonomy terms are updated
        add_action('created_vehicles', array($this, 'clear_vehicle_cache'));
        add_action('edited_vehicles', array($this, 'clear_vehicle_cache'));
        add_action('deleted_vehicles', array($this, 'clear_vehicle_cache'));
    }
    
    /**
     * Initialize vehicle data
     */
    public function init() {
        // Ensure vehicles taxonomy exists
        $this->check_vehicles_taxonomy();
        
        // Add custom fields to vehicles taxonomy if not exists
        $this->add_vehicle_meta_fields();
    }
    
    /**
     * Check if vehicles taxonomy exists, create if not
     */
    private function check_vehicles_taxonomy() {
        if (!taxonomy_exists('vehicles')) {
            // Register vehicles taxonomy
            register_taxonomy('vehicles', array('product'), array(
                'labels' => array(
                    'name' => __('Vehicles', 'fitting-request-system'),
                    'singular_name' => __('Vehicle', 'fitting-request-system'),
                    'menu_name' => __('Vehicles', 'fitting-request-system'),
                    'all_items' => __('All Vehicles', 'fitting-request-system'),
                    'edit_item' => __('Edit Vehicle', 'fitting-request-system'),
                    'view_item' => __('View Vehicle', 'fitting-request-system'),
                    'update_item' => __('Update Vehicle', 'fitting-request-system'),
                    'add_new_item' => __('Add New Vehicle', 'fitting-request-system'),
                    'new_item_name' => __('New Vehicle Name', 'fitting-request-system'),
                    'search_items' => __('Search Vehicles', 'fitting-request-system'),
                ),
                'public' => true,
                'hierarchical' => true,
                'show_ui' => true,
                'show_in_menu' => true,
                'show_in_nav_menus' => true,
                'show_tagcloud' => false,
                'show_admin_column' => true,
                'rewrite' => array('slug' => 'vehicle'),
                'capabilities' => array(
                    'manage_terms' => 'manage_product_terms',
                    'edit_terms' => 'edit_product_terms',
                    'delete_terms' => 'delete_product_terms',
                    'assign_terms' => 'assign_product_terms',
                ),
            ));
        }
    }
    
    /**
     * Add custom meta fields to vehicles taxonomy
     */
    private function add_vehicle_meta_fields() {
        add_action('vehicles_add_form_fields', array($this, 'add_vehicle_form_fields'));
        add_action('vehicles_edit_form_fields', array($this, 'edit_vehicle_form_fields'));
        add_action('edited_vehicles', array($this, 'save_vehicle_meta'), 10, 2);
        add_action('create_vehicles', array($this, 'save_vehicle_meta'), 10, 2);
    }
    
    /**
     * Add form fields for new vehicle terms
     */
    public function add_vehicle_form_fields() {
        ?>
        <div class="form-field">
            <label for="vehicle_make"><?php _e('Vehicle Make', 'fitting-request-system'); ?></label>
            <input type="text" name="vehicle_make" id="vehicle_make" value="" />
            <p class="description"><?php _e('Enter the vehicle make (e.g., Toyota, Honda)', 'fitting-request-system'); ?></p>
        </div>
        
        <div class="form-field">
            <label for="vehicle_model"><?php _e('Vehicle Model', 'fitting-request-system'); ?></label>
            <input type="text" name="vehicle_model" id="vehicle_model" value="" />
            <p class="description"><?php _e('Enter the vehicle model (e.g., Camry, Civic)', 'fitting-request-system'); ?></p>
        </div>
        
        <div class="form-field">
            <label for="vehicle_year_from"><?php _e('Year From', 'fitting-request-system'); ?></label>
            <input type="number" name="vehicle_year_from" id="vehicle_year_from" value="" min="1900" max="<?php echo date('Y') + 2; ?>" />
            <p class="description"><?php _e('Starting year for this vehicle model', 'fitting-request-system'); ?></p>
        </div>
        
        <div class="form-field">
            <label for="vehicle_year_to"><?php _e('Year To', 'fitting-request-system'); ?></label>
            <input type="number" name="vehicle_year_to" id="vehicle_year_to" value="" min="1900" max="<?php echo date('Y') + 2; ?>" />
            <p class="description"><?php _e('Ending year for this vehicle model (leave empty if still in production)', 'fitting-request-system'); ?></p>
        </div>
        
        <div class="form-field">
            <label for="vehicle_engine_type"><?php _e('Engine Type', 'fitting-request-system'); ?></label>
            <select name="vehicle_engine_type" id="vehicle_engine_type">
                <option value=""><?php _e('Select Engine Type', 'fitting-request-system'); ?></option>
                <option value="petrol">Petrol</option>
                <option value="diesel">Diesel</option>
                <option value="hybrid">Hybrid</option>
                <option value="electric">Electric</option>
                <option value="other">Other</option>
            </select>
        </div>
        <?php
    }
    
    /**
     * Edit form fields for existing vehicle terms
     */
    public function edit_vehicle_form_fields($term) {
        $vehicle_make = get_term_meta($term->term_id, 'vehicle_make', true);
        $vehicle_model = get_term_meta($term->term_id, 'vehicle_model', true);
        $vehicle_year_from = get_term_meta($term->term_id, 'vehicle_year_from', true);
        $vehicle_year_to = get_term_meta($term->term_id, 'vehicle_year_to', true);
        $vehicle_engine_type = get_term_meta($term->term_id, 'vehicle_engine_type', true);
        ?>
        <tr class="form-field">
            <th scope="row"><label for="vehicle_make"><?php _e('Vehicle Make', 'fitting-request-system'); ?></label></th>
            <td>
                <input type="text" name="vehicle_make" id="vehicle_make" value="<?php echo esc_attr($vehicle_make); ?>" />
                <p class="description"><?php _e('Enter the vehicle make (e.g., Toyota, Honda)', 'fitting-request-system'); ?></p>
            </td>
        </tr>
        
        <tr class="form-field">
            <th scope="row"><label for="vehicle_model"><?php _e('Vehicle Model', 'fitting-request-system'); ?></label></th>
            <td>
                <input type="text" name="vehicle_model" id="vehicle_model" value="<?php echo esc_attr($vehicle_model); ?>" />
                <p class="description"><?php _e('Enter the vehicle model (e.g., Camry, Civic)', 'fitting-request-system'); ?></p>
            </td>
        </tr>
        
        <tr class="form-field">
            <th scope="row"><label for="vehicle_year_from"><?php _e('Year From', 'fitting-request-system'); ?></label></th>
            <td>
                <input type="number" name="vehicle_year_from" id="vehicle_year_from" value="<?php echo esc_attr($vehicle_year_from); ?>" min="1900" max="<?php echo date('Y') + 2; ?>" />
                <p class="description"><?php _e('Starting year for this vehicle model', 'fitting-request-system'); ?></p>
            </td>
        </tr>
        
        <tr class="form-field">
            <th scope="row"><label for="vehicle_year_to"><?php _e('Year To', 'fitting-request-system'); ?></label></th>
            <td>
                <input type="number" name="vehicle_year_to" id="vehicle_year_to" value="<?php echo esc_attr($vehicle_year_to); ?>" min="1900" max="<?php echo date('Y') + 2; ?>" />
                <p class="description"><?php _e('Ending year for this vehicle model (leave empty if still in production)', 'fitting-request-system'); ?></p>
            </td>
        </tr>
        
        <tr class="form-field">
            <th scope="row"><label for="vehicle_engine_type"><?php _e('Engine Type', 'fitting-request-system'); ?></label></th>
            <td>
                <select name="vehicle_engine_type" id="vehicle_engine_type">
                    <option value=""><?php _e('Select Engine Type', 'fitting-request-system'); ?></option>
                    <option value="petrol" <?php selected($vehicle_engine_type, 'petrol'); ?>>Petrol</option>
                    <option value="diesel" <?php selected($vehicle_engine_type, 'diesel'); ?>>Diesel</option>
                    <option value="hybrid" <?php selected($vehicle_engine_type, 'hybrid'); ?>>Hybrid</option>
                    <option value="electric" <?php selected($vehicle_engine_type, 'electric'); ?>>Electric</option>
                    <option value="other" <?php selected($vehicle_engine_type, 'other'); ?>>Other</option>
                </select>
            </td>
        </tr>
        <?php
    }
    
    /**
     * Save vehicle meta data
     */
    public function save_vehicle_meta($term_id, $tt_id = null) {
        if (isset($_POST['vehicle_make'])) {
            update_term_meta($term_id, 'vehicle_make', sanitize_text_field($_POST['vehicle_make']));
        }
        
        if (isset($_POST['vehicle_model'])) {
            update_term_meta($term_id, 'vehicle_model', sanitize_text_field($_POST['vehicle_model']));
        }
        
        if (isset($_POST['vehicle_year_from'])) {
            update_term_meta($term_id, 'vehicle_year_from', intval($_POST['vehicle_year_from']));
        }
        
        if (isset($_POST['vehicle_year_to'])) {
            update_term_meta($term_id, 'vehicle_year_to', intval($_POST['vehicle_year_to']));
        }
        
        if (isset($_POST['vehicle_engine_type'])) {
            update_term_meta($term_id, 'vehicle_engine_type', sanitize_text_field($_POST['vehicle_engine_type']));
        }
        
        // Clear cache when vehicle data is updated
        $this->clear_vehicle_cache();
    }
    
    /**
     * Get all car makes from the vehicles taxonomy
     */
    public function get_car_makes() {
        $cache_key = 'fitting_request_car_makes';
        $makes = wp_cache_get($cache_key, self::CACHE_GROUP);
        
        if (false === $makes) {
            $makes = array();
            
            // Get all vehicle terms with meta
            $terms = get_terms(array(
                'taxonomy' => 'vehicles',
                'hide_empty' => false,
                'meta_query' => array(
                    array(
                        'key' => 'vehicle_make',
                        'value' => '',
                        'compare' => '!='
                    )
                )
            ));
            
            if (!is_wp_error($terms) && !empty($terms)) {
                $unique_makes = array();
                
                foreach ($terms as $term) {
                    $make = get_term_meta($term->term_id, 'vehicle_make', true);
                    if (!empty($make) && !in_array($make, $unique_makes)) {
                        $unique_makes[] = $make;
                    }
                }
                
                // Sort alphabetically
                sort($unique_makes);
                $makes = $unique_makes;
            }
            
            // Fallback to default makes if no data found
            if (empty($makes)) {
                $makes = $this->get_default_car_makes();
            }
            
            wp_cache_set($cache_key, $makes, self::CACHE_GROUP, self::CACHE_EXPIRATION);
        }
        
        return $makes;
    }
    
    /**
     * Get car models for a specific make
     */
    public function get_car_models($make) {
        $cache_key = 'fitting_request_models_' . sanitize_key($make);
        $models = wp_cache_get($cache_key, self::CACHE_GROUP);
        
        if (false === $models) {
            $models = array();
            
            // Get vehicle terms for specific make
            $terms = get_terms(array(
                'taxonomy' => 'vehicles',
                'hide_empty' => false,
                'meta_query' => array(
                    array(
                        'key' => 'vehicle_make',
                        'value' => $make,
                        'compare' => '='
                    ),
                    array(
                        'key' => 'vehicle_model',
                        'value' => '',
                        'compare' => '!='
                    )
                )
            ));
            
            if (!is_wp_error($terms) && !empty($terms)) {
                $unique_models = array();
                
                foreach ($terms as $term) {
                    $model = get_term_meta($term->term_id, 'vehicle_model', true);
                    $year_from = get_term_meta($term->term_id, 'vehicle_year_from', true);
                    $year_to = get_term_meta($term->term_id, 'vehicle_year_to', true);
                    
                    if (!empty($model)) {
                        $model_data = array(
                            'model' => $model,
                            'year_from' => $year_from,
                            'year_to' => $year_to ? $year_to : date('Y')
                        );
                        
                        // Use model name as key to avoid duplicates
                        $unique_models[$model] = $model_data;
                    }
                }
                
                // Sort by model name
                ksort($unique_models);
                $models = array_values($unique_models);
            }
            
            // Fallback to default models if no data found
            if (empty($models)) {
                $models = $this->get_default_car_models($make);
            }
            
            wp_cache_set($cache_key, $models, self::CACHE_GROUP, self::CACHE_EXPIRATION);
        }
        
        return $models;
    }
/**
 * AJAX handler for getting car makes
 */
public function ajax_get_car_makes() {
    // Verify nonce
    if (!check_ajax_referer('fitting_request_nonce', 'nonce', false)) {
        wp_send_json_error(array('message' => __('Security check failed', 'fitting-request-system')));
    }
    
    try {
        $makes = $this->get_car_makes();
        
        wp_send_json_success(array(
            'makes' => $makes,
            'count' => count($makes)
        ));
    } catch (Exception $e) {
        error_log('Fitting Request - Failed to get car makes: ' . $e->getMessage());
        wp_send_json_error(array('message' => __('Failed to load car makes', 'fitting-request-system')));
    }
}

    
    /**
     * Search vehicles by make and model
     */
    public function search_vehicles($search_term, $limit = 20) {
        $cache_key = 'fitting_request_search_' . sanitize_key($search_term) . '_' . $limit;
        $results = wp_cache_get($cache_key, self::CACHE_GROUP);
        
        if (false === $results) {
            $results = array();
            
            // Search in both make and model
            $terms = get_terms(array(
                'taxonomy' => 'vehicles',
                'hide_empty' => false,
                'number' => $limit,
                'meta_query' => array(
                    'relation' => 'OR',
                    array(
                        'key' => 'vehicle_make',
                        'value' => $search_term,
                        'compare' => 'LIKE'
                    ),
                    array(
                        'key' => 'vehicle_model',
                        'value' => $search_term,
                        'compare' => 'LIKE'
                    )
                )
            ));
            
            if (!is_wp_error($terms) && !empty($terms)) {
                foreach ($terms as $term) {
                    $make = get_term_meta($term->term_id, 'vehicle_make', true);
                    $model = get_term_meta($term->term_id, 'vehicle_model', true);
                    $year_from = get_term_meta($term->term_id, 'vehicle_year_from', true);
                    $year_to = get_term_meta($term->term_id, 'vehicle_year_to', true);
                    
                    if (!empty($make) && !empty($model)) {
                        $results[] = array(
                            'term_id' => $term->term_id,
                            'make' => $make,
                            'model' => $model,
                            'year_from' => $year_from,
                            'year_to' => $year_to ? $year_to : date('Y'),
                            'display_name' => $make . ' ' . $model . ' (' . $year_from . '-' . ($year_to ? $year_to : 'Present') . ')'
                        );
                    }
                }
            }
            
            wp_cache_set($cache_key, $results, self::CACHE_GROUP, HOUR_IN_SECONDS);
        }
        
        return $results;
    }
    
    /**
     * AJAX handler for getting car models
     */
    public function ajax_get_car_models() {
        // Verify nonce
        if (!check_ajax_referer('fitting_request_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed', 'fitting-request-system')));
        }
        
        $make = sanitize_text_field($_POST['make']);
        
        if (empty($make)) {
            wp_send_json_error(array('message' => __('Car make is required', 'fitting-request-system')));
        }
        
        try {
            $models = $this->get_car_models($make);
            
            wp_send_json_success(array(
                'models' => $models,
                'count' => count($models)
            ));
        } catch (Exception $e) {
            error_log('Fitting Request - Failed to get car models: ' . $e->getMessage());
            wp_send_json_error(array('message' => __('Failed to load car models', 'fitting-request-system')));
        }
    }
    
    /**
     * AJAX handler for searching vehicles
     */
    public function ajax_search_vehicles() {
        // Verify nonce
        if (!check_ajax_referer('fitting_request_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed', 'fitting-request-system')));
        }
        
        $search_term = sanitize_text_field($_POST['search']);
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 20;
        
        if (empty($search_term) || strlen($search_term) < 2) {
            wp_send_json_error(array('message' => __('Search term must be at least 2 characters', 'fitting-request-system')));
        }
        
        try {
            $results = $this->search_vehicles($search_term, $limit);
            
            wp_send_json_success(array(
                'results' => $results,
                'count' => count($results)
            ));
        } catch (Exception $e) {
            error_log('Fitting Request - Failed to search vehicles: ' . $e->getMessage());
            wp_send_json_error(array('message' => __('Search failed', 'fitting-request-system')));
        }
    }
    
    /**
     * Clear vehicle cache
     */
    public function clear_vehicle_cache() {
        wp_cache_flush_group(self::CACHE_GROUP);
    }
    
    /**
     * Get default car makes (fallback)
     */
    private function get_default_car_makes() {
        return array(
            'Audi', 'BMW', 'Chevrolet', 'Ford', 'Honda', 'Hyundai', 'Infiniti',
            'Kia', 'Lexus', 'Mazda', 'Mercedes-Benz', 'Mitsubishi', 'Nissan',
            'Peugeot', 'Porsche', 'Renault', 'Subaru', 'Suzuki', 'Toyota',
            'Volkswagen', 'Volvo'
        );
    }
    
    /**
     * Get default car models for a make (fallback)
     */
    private function get_default_car_models($make) {
        $default_models = array(
            'Toyota' => array(
                array('model' => 'Camry', 'year_from' => 2012, 'year_to' => date('Y')),
                array('model' => 'Corolla', 'year_from' => 2014, 'year_to' => date('Y')),
                array('model' => 'RAV4', 'year_from' => 2013, 'year_to' => date('Y')),
                array('model' => 'Prius', 'year_from' => 2016, 'year_to' => date('Y')),
                array('model' => 'Highlander', 'year_from' => 2014, 'year_to' => date('Y')),
                array('model' => 'Land Cruiser', 'year_from' => 2008, 'year_to' => date('Y'))
            ),
            'Honda' => array(
                array('model' => 'Civic', 'year_from' => 2016, 'year_to' => date('Y')),
                array('model' => 'Accord', 'year_from' => 2018, 'year_to' => date('Y')),
                array('model' => 'CR-V', 'year_from' => 2017, 'year_to' => date('Y')),
                array('model' => 'Pilot', 'year_from' => 2016, 'year_to' => date('Y'))
            ),
            'BMW' => array(
                array('model' => '3 Series', 'year_from' => 2012, 'year_to' => date('Y')),
                array('model' => '5 Series', 'year_from' => 2017, 'year_to' => date('Y')),
                array('model' => 'X3', 'year_from' => 2018, 'year_to' => date('Y')),
                array('model' => 'X5', 'year_from' => 2014, 'year_to' => date('Y'))
            ),
            'Mercedes-Benz' => array(
                array('model' => 'C-Class', 'year_from' => 2015, 'year_to' => date('Y')),
                array('model' => 'E-Class', 'year_from' => 2017, 'year_to' => date('Y')),
                array('model' => 'GLC', 'year_from' => 2016, 'year_to' => date('Y')),
                array('model' => 'GLE', 'year_from' => 2020, 'year_to' => date('Y'))
            ),
            'Nissan' => array(
                array('model' => 'Altima', 'year_from' => 2013, 'year_to' => date('Y')),
                array('model' => 'Sentra', 'year_from' => 2013, 'year_to' => date('Y')),
                array('model' => 'Rogue', 'year_from' => 2014, 'year_to' => date('Y')),
                array('model' => 'Pathfinder', 'year_from' => 2013, 'year_to' => date('Y'))
            )
        );
        
        return isset($default_models[$make]) ? $default_models[$make] : array();
    }
    
    /**
     * Get vehicle compatibility for a product
     */
    public function get_product_vehicles($product_id) {
        $vehicle_terms = wp_get_post_terms($product_id, 'vehicles', array('fields' => 'all'));
        $vehicles = array();
        
        if (!is_wp_error($vehicle_terms) && !empty($vehicle_terms)) {
            foreach ($vehicle_terms as $term) {
                $make = get_term_meta($term->term_id, 'vehicle_make', true);
                $model = get_term_meta($term->term_id, 'vehicle_model', true);
                $year_from = get_term_meta($term->term_id, 'vehicle_year_from', true);
                $year_to = get_term_meta($term->term_id, 'vehicle_year_to', true);
                
                if (!empty($make) && !empty($model)) {
                    $vehicles[] = array(
                        'term_id' => $term->term_id,
                        'make' => $make,
                        'model' => $model,
                        'year_from' => $year_from,
                        'year_to' => $year_to ? $year_to : date('Y'),
                        'display_name' => $make . ' ' . $model . ' (' . $year_from . '-' . ($year_to ? $year_to : 'Present') . ')'
                    );
                }
            }
        }
        
        return $vehicles;
    }
    
    /**
     * Check if a product is compatible with a vehicle
     */
    public function is_product_compatible($product_id, $make, $model, $year = null) {
        $vehicles = $this->get_product_vehicles($product_id);
        
        foreach ($vehicles as $vehicle) {
            if (strtolower($vehicle['make']) === strtolower($make) && 
                strtolower($vehicle['model']) === strtolower($model)) {
                
                // Check year compatibility if provided
                if ($year) {
                    if ($year >= $vehicle['year_from'] && $year <= $vehicle['year_to']) {
                        return true;
                    }
                } else {
                    return true;
                }
            }
        }
        
        return false;
    }
}