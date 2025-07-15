<?php
/**
 * Input validator class
 *
 * @package FittingRequestSystem
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Fitting_Request_Validator {
    
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
        $this->error_handler = Fitting_Request_Error_Handler::get_instance();
    }
    
    /**
     * Validate fitting request data
     */
    public function validate_request($data) {
        $errors = array();
        $sanitized_data = array();
        
        // Check honeypot
        if (!Fitting_Request_Security::check_honeypot($data)) {
            $this->error_handler->log_security_incident(
                'honeypot_failed',
                'Bot detected via honeypot field',
                array('data' => $data)
            );
            
            return array(
                'is_valid' => false,
                'errors' => array('general' => __('Security check failed', 'fitting-request-system'))
            );
        }
        
        // Validate CSRF token
        if (!isset($data['nonce']) || !Fitting_Request_Security::validate_csrf($data['nonce'])) {
            $errors['nonce'] = __('Security check failed', 'fitting-request-system');
        }
        
        // Validate product ID
        if (empty($data['product_id']) || !is_numeric($data['product_id'])) {
            $errors['product_id'] = __('Invalid product selected', 'fitting-request-system');
        } else {
            $product_id = intval($data['product_id']);
            $product = wc_get_product($product_id);
            
            if (!$product || !$product->exists()) {
                $errors['product_id'] = __('Product not found', 'fitting-request-system');
            } else {
                $sanitized_data['product_id'] = $product_id;
            }
        }
        
        // Validate emirate
        if (empty($data['emirate'])) {
            $errors['emirate'] = __('Please select your emirate', 'fitting-request-system');
        } else {
            $emirate = sanitize_text_field($data['emirate']);
            $valid_emirates = array_keys(Fitting_Request_Cache::get_emirates());
            
            if (!in_array($emirate, $valid_emirates)) {
                $errors['emirate'] = __('Invalid emirate selected', 'fitting-request-system');
            } else {
                $sanitized_data['emirate'] = $emirate;
            }
        }
        
        // Validate car make
        if (empty($data['car_make'])) {
            $errors['car_make'] = __('Please select your car make', 'fitting-request-system');
        } else {
            $car_make = sanitize_text_field($data['car_make']);
            $valid_makes = Fitting_Request_Cache::get_car_makes();
            
            if (!in_array($car_make, $valid_makes)) {
                $errors['car_make'] = __('Invalid car make selected', 'fitting-request-system');
            } else {
                $sanitized_data['car_make'] = $car_make;
            }
        }
        
        // Validate car model
        if (empty($data['car_model'])) {
            $errors['car_model'] = __('Please select your car model', 'fitting-request-system');
        } else {
            $car_model = sanitize_text_field($data['car_model']);
            
            if (isset($sanitized_data['car_make'])) {
                $valid_models = Fitting_Request_Cache::get_car_models($sanitized_data['car_make']);
                
                if (!in_array($car_model, $valid_models)) {
                    $errors['car_model'] = __('Invalid car model selected', 'fitting-request-system');
                } else {
                    $sanitized_data['car_model'] = $car_model;
                }
            } else {
                $sanitized_data['car_model'] = $car_model;
            }
        }
        
        // Validate email
        if (empty($data['customer_email'])) {
            $errors['customer_email'] = __('Email address is required', 'fitting-request-system');
        } else {
            $email = Fitting_Request_Security::sanitize_email($data['customer_email']);
            
            if (!$email) {
                $errors['customer_email'] = __('Please enter a valid email address', 'fitting-request-system');
            } else {
                $sanitized_data['customer_email'] = $email;
            }
        }
        
        // Validate WhatsApp number
        if (empty($data['customer_whatsapp'])) {
            $errors['customer_whatsapp'] = __('WhatsApp number is required', 'fitting-request-system');
        } else {
            $whatsapp = Fitting_Request_Security::sanitize_phone($data['customer_whatsapp']);
            
            if (!$whatsapp) {
                $errors['customer_whatsapp'] = __('Please enter a valid UAE WhatsApp number', 'fitting-request-system');
            } else {
                $sanitized_data['customer_whatsapp'] = $whatsapp;
            }
        }
        
        // Log validation errors if any
        if (!empty($errors)) {
            foreach ($errors as $field => $error) {
                $this->error_handler->log_validation_error(
                    $field,
                    $data[$field] ?? 'missing',
                    'required_field',
                    array('all_errors' => $errors)
                );
            }
        }
        
        return array(
            'is_valid' => empty($errors),
            'errors' => $errors,
            'data' => $sanitized_data
        );
    }
    
    /**
     * Validate quote submission data
     */
    public function validate_quote($data) {
        $errors = array();
        $sanitized_data = array();
        
        // Validate token
        if (empty($data['token'])) {
            $errors['token'] = __('Invalid access token', 'fitting-request-system');
        } else {
            $token_data = Fitting_Request_Security::validate_token($data['token']);
            
            if (!$token_data) {
                $errors['token'] = __('Access token expired or invalid', 'fitting-request-system');
            } else {
                $sanitized_data['token_data'] = $token_data;
            }
        }
        
        // Validate request ID
        if (empty($data['request_id'])) {
            $errors['request_id'] = __('Request ID is required', 'fitting-request-system');
        } else {
            $sanitized_data['request_id'] = sanitize_text_field($data['request_id']);
        }
        
        // Validate garage ID
        if (empty($data['garage_id']) || !is_numeric($data['garage_id'])) {
            $errors['garage_id'] = __('Invalid garage ID', 'fitting-request-system');
        } else {
            $garage_id = intval($data['garage_id']);
            $garage = get_post($garage_id);
            
            if (!$garage || $garage->post_type !== 'garage') {
                $errors['garage_id'] = __('Garage not found', 'fitting-request-system');
            } else {
                $sanitized_data['garage_id'] = $garage_id;
            }
        }
        
        // Validate quote amount
        if (empty($data['quote_amount']) || !is_numeric($data['quote_amount'])) {
            $errors['quote_amount'] = __('Quote amount is required', 'fitting-request-system');
        } else {
            $amount = floatval($data['quote_amount']);
            
            if ($amount <= 0) {
                $errors['quote_amount'] = __('Quote amount must be greater than zero', 'fitting-request-system');
            } elseif ($amount > 99999.99) {
                $errors['quote_amount'] = __('Quote amount is too high', 'fitting-request-system');
            } else {
                $sanitized_data['quote_amount'] = $amount;
            }
        }
        
        // Validate estimated time (optional)
        if (!empty($data['estimated_time'])) {
            $estimated_time = sanitize_text_field($data['estimated_time']);
            
            if (strlen($estimated_time) > 50) {
                $errors['estimated_time'] = __('Estimated time is too long', 'fitting-request-system');
            } else {
                $sanitized_data['estimated_time'] = $estimated_time;
            }
        } else {
            $sanitized_data['estimated_time'] = '';
        }
        
        // Validate notes (optional)
        if (!empty($data['notes'])) {
            $notes = sanitize_textarea_field($data['notes']);
            
            if (strlen($notes) > 1000) {
                $errors['notes'] = __('Notes are too long (maximum 1000 characters)', 'fitting-request-system');
            } else {
                $sanitized_data['notes'] = $notes;
            }
        } else {
            $sanitized_data['notes'] = '';
        }
        
        return array(
            'is_valid' => empty($errors),
            'errors' => $errors,
            'data' => $sanitized_data
        );
    }
    
    /**
     * Validate garage registration data
     */
    public function validate_garage_registration($data) {
        $errors = array();
        $sanitized_data = array();
        
        // Check honeypot
        if (!Fitting_Request_Security::check_honeypot($data)) {
            $this->error_handler->log_security_incident(
                'garage_registration_honeypot_failed',
                'Bot detected during garage registration',
                array('data' => $data)
            );
            
            return array(
                'is_valid' => false,
                'errors' => array('general' => __('Security check failed', 'fitting-request-system'))
            );
        }
        
        // Validate garage name
        if (empty($data['garage_name'])) {
            $errors['garage_name'] = __('Garage name is required', 'fitting-request-system');
        } else {
            $garage_name = sanitize_text_field($data['garage_name']);
            
            if (strlen($garage_name) < 3) {
                $errors['garage_name'] = __('Garage name must be at least 3 characters', 'fitting-request-system');
            } elseif (strlen($garage_name) > 100) {
                $errors['garage_name'] = __('Garage name is too long', 'fitting-request-system');
            } else {
                $sanitized_data['garage_name'] = $garage_name;
            }
        }
        
        // Validate email
        if (empty($data['email'])) {
            $errors['email'] = __('Email address is required', 'fitting-request-system');
        } else {
            $email = Fitting_Request_Security::sanitize_email($data['email']);
            
            if (!$email) {
                $errors['email'] = __('Please enter a valid email address', 'fitting-request-system');
            } else {
                // Check if email already exists
                $existing = get_posts(array(
                    'post_type' => 'garage',
                    'meta_query' => array(
                        array(
                            'key' => 'email',
                            'value' => $email,
                            'compare' => '='
                        )
                    ),
                    'posts_per_page' => 1
                ));
                
                if ($existing) {
                    $errors['email'] = __('A garage with this email already exists', 'fitting-request-system');
                } else {
                    $sanitized_data['email'] = $email;
                }
            }
        }
        
        // Validate WhatsApp
        if (empty($data['whatsapp'])) {
            $errors['whatsapp'] = __('WhatsApp number is required', 'fitting-request-system');
        } else {
            $whatsapp = Fitting_Request_Security::sanitize_phone($data['whatsapp']);
            
            if (!$whatsapp) {
                $errors['whatsapp'] = __('Please enter a valid UAE WhatsApp number', 'fitting-request-system');
            } else {
                $sanitized_data['whatsapp'] = $whatsapp;
            }
        }
        
        // Validate emirate
        if (empty($data['emirate'])) {
            $errors['emirate'] = __('Please select your emirate', 'fitting-request-system');
        } else {
            $emirate = sanitize_text_field($data['emirate']);
            $valid_emirates = array_keys(Fitting_Request_Cache::get_emirates());
            
            if (!in_array($emirate, $valid_emirates)) {
                $errors['emirate'] = __('Invalid emirate selected', 'fitting-request-system');
            } else {
                $sanitized_data['emirate'] = $emirate;
            }
        }
        
        // Validate address
        if (empty($data['address'])) {
            $errors['address'] = __('Address is required', 'fitting-request-system');
        } else {
            $address = sanitize_textarea_field($data['address']);
            
            if (strlen($address) < 10) {
                $errors['address'] = __('Please provide a complete address', 'fitting-request-system');
            } elseif (strlen($address) > 500) {
                $errors['address'] = __('Address is too long', 'fitting-request-system');
            } else {
                $sanitized_data['address'] = $address;
            }
        }
        
        // Validate contact person (optional)
        if (!empty($data['contact_person'])) {
            $contact_person = sanitize_text_field($data['contact_person']);
            
            if (strlen($contact_person) > 100) {
                $errors['contact_person'] = __('Contact person name is too long', 'fitting-request-system');
            } else {
                $sanitized_data['contact_person'] = $contact_person;
            }
        } else {
            $sanitized_data['contact_person'] = '';
        }
        
        // Validate services (optional)
        if (!empty($data['services'])) {
            $services = sanitize_textarea_field($data['services']);
            
            if (strlen($services) > 1000) {
                $errors['services'] = __('Services description is too long', 'fitting-request-system');
            } else {
                $sanitized_data['services'] = $services;
            }
        } else {
            $sanitized_data['services'] = '';
        }
        
        return array(
            'is_valid' => empty($errors),
            'errors' => $errors,
            'data' => $sanitized_data
        );
    }
    
    /**
     * Validate feedback data
     */
    public function validate_feedback($data) {
        $errors = array();
        $sanitized_data = array();
        
        // Validate request ID
        if (empty($data['request_id'])) {
            $errors['request_id'] = __('Request ID is required', 'fitting-request-system');
        } else {
            $sanitized_data['request_id'] = sanitize_text_field($data['request_id']);
        }
        
        // Validate rating
        if (empty($data['rating']) || !is_numeric($data['rating'])) {
            $errors['rating'] = __('Please select a rating', 'fitting-request-system');
        } else {
            $rating = intval($data['rating']);
            
            if ($rating < 1 || $rating > 5) {
                $errors['rating'] = __('Rating must be between 1 and 5', 'fitting-request-system');
            } else {
                $sanitized_data['rating'] = $rating;
            }
        }
        
        // Validate comment (optional)
        if (!empty($data['comment'])) {
            $comment = sanitize_textarea_field($data['comment']);
            
            if (strlen($comment) > 1000) {
                $errors['comment'] = __('Comment is too long (maximum 1000 characters)', 'fitting-request-system');
            } else {
                $sanitized_data['comment'] = $comment;
            }
        } else {
            $sanitized_data['comment'] = '';
        }
        
        return array(
            'is_valid' => empty($errors),
            'errors' => $errors,
            'data' => $sanitized_data
        );
    }
    
    /**
     * Validate admin settings
     */
    public function validate_admin_settings($data) {
        $errors = array();
        $sanitized_data = array();
        
        // Validate email settings
        if (isset($data['admin_email'])) {
            $email = Fitting_Request_Security::sanitize_email($data['admin_email']);
            
            if (!$email) {
                $errors['admin_email'] = __('Please enter a valid admin email', 'fitting-request-system');
            } else {
                $sanitized_data['admin_email'] = $email;
            }
        }
        
        // Validate max garages per request
        if (isset($data['max_garages_per_request'])) {
            $max_garages = intval($data['max_garages_per_request']);
            
            if ($max_garages < 1 || $max_garages > 50) {
                $errors['max_garages_per_request'] = __('Max garages must be between 1 and 50', 'fitting-request-system');
            } else {
                $sanitized_data['max_garages_per_request'] = $max_garages;
            }
        }
        
        // Validate timeout hours
        if (isset($data['request_timeout_hours'])) {
            $timeout = intval($data['request_timeout_hours']);
            
            if ($timeout < 1 || $timeout > 168) { // Max 1 week
                $errors['request_timeout_hours'] = __('Timeout must be between 1 and 168 hours', 'fitting-request-system');
            } else {
                $sanitized_data['request_timeout_hours'] = $timeout;
            }
        }
        
        // Validate boolean settings
        $boolean_fields = array(
            'enable_email_notifications',
            'enable_whatsapp_notifications',
            'enable_quote_system',
            'enable_feedback_system'
        );
        
        foreach ($boolean_fields as $field) {
            if (isset($data[$field])) {
                $sanitized_data[$field] = (bool) $data[$field];
            }
        }
        
        return array(
            'is_valid' => empty($errors),
            'errors' => $errors,
            'data' => $sanitized_data
        );
    }
    
    /**
     * Validate file upload
     */
    public function validate_file_upload($file, $allowed_types = array('jpg', 'jpeg', 'png', 'pdf'), $max_size = 5242880) {
        $errors = array();
        
        if (empty($file['name'])) {
            return array('is_valid' => true, 'errors' => array()); // File is optional
        }
        
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = __('File upload error', 'fitting-request-system');
            return array('is_valid' => false, 'errors' => $errors);
        }
        
        // Validate file size
        if ($file['size'] > $max_size) {
            $errors[] = sprintf(__('File is too large. Maximum size is %s MB', 'fitting-request-system'), $max_size / 1048576);
        }
        
        // Validate file type
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($file_extension, $allowed_types)) {
            $errors[] = sprintf(__('Invalid file type. Allowed types: %s', 'fitting-request-system'), implode(', ', $allowed_types));
        }
        
        // Validate file mime type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        $allowed_mime_types = array(
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'pdf' => 'application/pdf'
        );
        
        if (!in_array($mime_type, $allowed_mime_types)) {
            $errors[] = __('Invalid file format detected', 'fitting-request-system');
        }
        
        return array(
            'is_valid' => empty($errors),
            'errors' => $errors
        );
    }
}