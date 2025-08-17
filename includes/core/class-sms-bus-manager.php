<?php
/**
 * Bus Manager Class
 *
 * @package SchoolManagementSystem
 * @subpackage Core
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class SMS_Bus_Manager
 * 
 * Handles bus information management, driver details, and vehicle tracking
 */
class SMS_Bus_Manager {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'init'));
    }

    /**
     * Initialize the bus manager
     */
    public function init() {
        // Hook into WordPress actions
        add_action('wp_ajax_sms_update_vehicle_details', array($this, 'ajax_update_vehicle_details'));
        add_action('wp_ajax_sms_update_driver_details', array($this, 'ajax_update_driver_details'));
        add_action('wp_ajax_sms_get_vehicle_maintenance_alerts', array($this, 'ajax_get_maintenance_alerts'));
        add_action('wp_ajax_sms_get_driver_license_alerts', array($this, 'ajax_get_license_alerts'));
        add_action('wp_ajax_sms_validate_vehicle_capacity', array($this, 'ajax_validate_vehicle_capacity'));
        
        // Schedule maintenance and license expiry checks
        add_action('sms_daily_maintenance_check', array($this, 'check_maintenance_alerts'));
        add_action('sms_daily_license_check', array($this, 'check_license_alerts'));
    }

    /**
     * Update vehicle details for a route
     *
     * @param int $route_id Route ID
     * @param array $vehicle_data Vehicle information
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function update_vehicle_details($route_id, $vehicle_data) {
        // Check if route exists
        if (!$this->route_exists($route_id)) {
            return new WP_Error(
                'route_not_found',
                __('Transport route not found.', 'school-management-system')
            );
        }

        // Validate vehicle data
        $validation_result = $this->validate_vehicle_data($vehicle_data);
        if (is_wp_error($validation_result)) {
            return $validation_result;
        }

        // Check for duplicate registration number
        if (!empty($vehicle_data['registration_number']) && 
            $this->registration_exists($vehicle_data['registration_number'], $route_id)) {
            return new WP_Error(
                'duplicate_registration',
                __('Vehicle registration number already exists.', 'school-management-system')
            );
        }

        // Sanitize and save vehicle data
        $sanitized_data = $this->sanitize_vehicle_data($vehicle_data);
        update_field('vehicle_details', $sanitized_data, $route_id);

        // Update route capacity if vehicle capacity is provided
        if (!empty($sanitized_data['capacity'])) {
            $this->update_route_capacity_from_vehicle($route_id, $sanitized_data['capacity']);
        }

        // Log the activity
        do_action('sms_vehicle_updated', $route_id, $sanitized_data);

        return true;
    }

    /**
     * Update driver details for a route
     *
     * @param int $route_id Route ID
     * @param array $driver_data Driver information
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function update_driver_details($route_id, $driver_data) {
        // Check if route exists
        if (!$this->route_exists($route_id)) {
            return new WP_Error(
                'route_not_found',
                __('Transport route not found.', 'school-management-system')
            );
        }

        // Validate driver data
        $validation_result = $this->validate_driver_data($driver_data);
        if (is_wp_error($validation_result)) {
            return $validation_result;
        }

        // Check for duplicate license number
        if (!empty($driver_data['license_number']) && 
            $this->license_exists($driver_data['license_number'], $route_id)) {
            return new WP_Error(
                'duplicate_license',
                __('Driver license number already exists.', 'school-management-system')
            );
        }

        // Sanitize and save driver data
        $sanitized_data = $this->sanitize_driver_data($driver_data);
        update_field('driver_details', $sanitized_data, $route_id);

        // Log the activity
        do_action('sms_driver_updated', $route_id, $sanitized_data);

        return true;
    }

    /**
     * Get vehicle details for a route
     *
     * @param int $route_id Route ID
     * @return array|WP_Error Vehicle details on success, WP_Error on failure
     */
    public function get_vehicle_details($route_id) {
        if (!$this->route_exists($route_id)) {
            return new WP_Error(
                'route_not_found',
                __('Transport route not found.', 'school-management-system')
            );
        }

        $vehicle_details = get_field('vehicle_details', $route_id);
        
        if (empty($vehicle_details)) {
            return array();
        }

        // Add calculated fields
        $vehicle_details['maintenance_status'] = $this->get_vehicle_maintenance_status($vehicle_details);
        $vehicle_details['insurance_status'] = $this->get_insurance_status($vehicle_details);
        $vehicle_details['inspection_status'] = $this->get_inspection_status($vehicle_details);

        return $vehicle_details;
    }

    /**
     * Get driver details for a route
     *
     * @param int $route_id Route ID
     * @return array|WP_Error Driver details on success, WP_Error on failure
     */
    public function get_driver_details($route_id) {
        if (!$this->route_exists($route_id)) {
            return new WP_Error(
                'route_not_found',
                __('Transport route not found.', 'school-management-system')
            );
        }

        $driver_details = get_field('driver_details', $route_id);
        
        if (empty($driver_details)) {
            return array();
        }

        // Add calculated fields
        $driver_details['license_status'] = $this->get_license_status($driver_details);
        $driver_details['experience_level'] = $this->get_experience_level($driver_details);

        return $driver_details;
    }

    /**
     * Get maintenance alerts for all vehicles
     *
     * @return array List of maintenance alerts
     */
    public function get_maintenance_alerts() {
        $alerts = array();
        
        // Get all routes with vehicle details
        $routes = $this->get_routes_with_vehicles();
        
        foreach ($routes as $route) {
            $vehicle_details = get_field('vehicle_details', $route->ID);
            
            if (empty($vehicle_details)) {
                continue;
            }

            // Check insurance expiry
            if (!empty($vehicle_details['insurance_expiry'])) {
                $days_until_expiry = $this->days_until_date($vehicle_details['insurance_expiry']);
                if ($days_until_expiry <= 30) {
                    $alerts[] = array(
                        'type' => 'insurance_expiry',
                        'route_id' => $route->ID,
                        'route_name' => $route->post_title,
                        'vehicle_registration' => $vehicle_details['registration_number'] ?? '',
                        'days_remaining' => $days_until_expiry,
                        'expiry_date' => $vehicle_details['insurance_expiry'],
                        'priority' => $days_until_expiry <= 7 ? 'high' : 'medium'
                    );
                }
            }

            // Check inspection expiry
            if (!empty($vehicle_details['inspection_expiry'])) {
                $days_until_expiry = $this->days_until_date($vehicle_details['inspection_expiry']);
                if ($days_until_expiry <= 30) {
                    $alerts[] = array(
                        'type' => 'inspection_expiry',
                        'route_id' => $route->ID,
                        'route_name' => $route->post_title,
                        'vehicle_registration' => $vehicle_details['registration_number'] ?? '',
                        'days_remaining' => $days_until_expiry,
                        'expiry_date' => $vehicle_details['inspection_expiry'],
                        'priority' => $days_until_expiry <= 7 ? 'high' : 'medium'
                    );
                }
            }

            // Check vehicle condition
            if (!empty($vehicle_details['condition']) && 
                in_array($vehicle_details['condition'], array('poor', 'maintenance'))) {
                $alerts[] = array(
                    'type' => 'vehicle_condition',
                    'route_id' => $route->ID,
                    'route_name' => $route->post_title,
                    'vehicle_registration' => $vehicle_details['registration_number'] ?? '',
                    'condition' => $vehicle_details['condition'],
                    'priority' => $vehicle_details['condition'] === 'poor' ? 'high' : 'medium'
                );
            }
        }

        return $alerts;
    }

    /**
     * Get license alerts for all drivers
     *
     * @return array List of license alerts
     */
    public function get_license_alerts() {
        $alerts = array();
        
        // Get all routes with driver details
        $routes = $this->get_routes_with_drivers();
        
        foreach ($routes as $route) {
            $driver_details = get_field('driver_details', $route->ID);
            
            if (empty($driver_details) || empty($driver_details['license_expiry'])) {
                continue;
            }

            $days_until_expiry = $this->days_until_date($driver_details['license_expiry']);
            if ($days_until_expiry <= 60) {
                $alerts[] = array(
                    'type' => 'license_expiry',
                    'route_id' => $route->ID,
                    'route_name' => $route->post_title,
                    'driver_name' => $driver_details['name'] ?? '',
                    'license_number' => $driver_details['license_number'] ?? '',
                    'days_remaining' => $days_until_expiry,
                    'expiry_date' => $driver_details['license_expiry'],
                    'priority' => $days_until_expiry <= 14 ? 'high' : 'medium'
                );
            }
        }

        return $alerts;
    }

    /**
     * Validate vehicle capacity against route assignments
     *
     * @param int $route_id Route ID
     * @param int $vehicle_capacity Vehicle capacity
     * @return array Validation result
     */
    public function validate_vehicle_capacity($route_id, $vehicle_capacity) {
        $current_enrollment = $this->get_route_enrollment($route_id);
        $route_capacity = get_field('total_capacity', $route_id);

        $result = array(
            'is_valid' => true,
            'current_enrollment' => $current_enrollment,
            'route_capacity' => $route_capacity,
            'vehicle_capacity' => $vehicle_capacity,
            'warnings' => array(),
            'errors' => array()
        );

        // Check if vehicle capacity is less than current enrollment
        if ($vehicle_capacity < $current_enrollment) {
            $result['is_valid'] = false;
            $result['errors'][] = sprintf(
                __('Vehicle capacity (%d) is less than current enrollment (%d).', 'school-management-system'),
                $vehicle_capacity,
                $current_enrollment
            );
        }

        // Check if vehicle capacity is less than route capacity
        if ($vehicle_capacity < $route_capacity) {
            $result['warnings'][] = sprintf(
                __('Vehicle capacity (%d) is less than route capacity (%d). Consider updating route capacity.', 'school-management-system'),
                $vehicle_capacity,
                $route_capacity
            );
        }

        return $result;
    }

    /**
     * Validate vehicle data
     *
     * @param array $vehicle_data Vehicle data to validate
     * @return bool|WP_Error True if valid, WP_Error if invalid
     */
    private function validate_vehicle_data($vehicle_data) {
        $errors = array();

        // Validate registration number format (Kenyan format)
        if (!empty($vehicle_data['registration_number'])) {
            if (!$this->is_valid_kenyan_registration($vehicle_data['registration_number'])) {
                $errors[] = __('Invalid vehicle registration number format.', 'school-management-system');
            }
        }

        // Validate capacity
        if (!empty($vehicle_data['capacity'])) {
            if (!is_numeric($vehicle_data['capacity']) || $vehicle_data['capacity'] < 1) {
                $errors[] = __('Vehicle capacity must be a positive number.', 'school-management-system');
            }
        }

        // Validate year
        if (!empty($vehicle_data['year'])) {
            $current_year = date('Y');
            if (!is_numeric($vehicle_data['year']) || 
                $vehicle_data['year'] < 1990 || 
                $vehicle_data['year'] > ($current_year + 1)) {
                $errors[] = __('Invalid vehicle year.', 'school-management-system');
            }
        }

        // Validate dates
        if (!empty($vehicle_data['insurance_expiry'])) {
            if (!$this->is_valid_date($vehicle_data['insurance_expiry'])) {
                $errors[] = __('Invalid insurance expiry date.', 'school-management-system');
            }
        }

        if (!empty($vehicle_data['inspection_expiry'])) {
            if (!$this->is_valid_date($vehicle_data['inspection_expiry'])) {
                $errors[] = __('Invalid inspection expiry date.', 'school-management-system');
            }
        }

        if (!empty($errors)) {
            return new WP_Error('validation_failed', implode(' ', $errors));
        }

        return true;
    }

    /**
     * Validate driver data
     *
     * @param array $driver_data Driver data to validate
     * @return bool|WP_Error True if valid, WP_Error if invalid
     */
    private function validate_driver_data($driver_data) {
        $errors = array();

        // Validate phone number
        if (!empty($driver_data['phone'])) {
            if (!$this->is_valid_kenyan_phone($driver_data['phone'])) {
                $errors[] = __('Invalid phone number format.', 'school-management-system');
            }
        }

        // Validate emergency contact
        if (!empty($driver_data['emergency_contact'])) {
            if (!$this->is_valid_kenyan_phone($driver_data['emergency_contact'])) {
                $errors[] = __('Invalid emergency contact phone number format.', 'school-management-system');
            }
        }

        // Validate experience
        if (!empty($driver_data['experience'])) {
            if (!is_numeric($driver_data['experience']) || $driver_data['experience'] < 0) {
                $errors[] = __('Experience must be a non-negative number.', 'school-management-system');
            }
        }

        // Validate license expiry date
        if (!empty($driver_data['license_expiry'])) {
            if (!$this->is_valid_date($driver_data['license_expiry'])) {
                $errors[] = __('Invalid license expiry date.', 'school-management-system');
            } elseif (strtotime($driver_data['license_expiry']) < time()) {
                $errors[] = __('Driver license has already expired.', 'school-management-system');
            }
        }

        if (!empty($errors)) {
            return new WP_Error('validation_failed', implode(' ', $errors));
        }

        return true;
    }

    /**
     * Sanitize vehicle data
     *
     * @param array $vehicle_data Raw vehicle data
     * @return array Sanitized vehicle data
     */
    private function sanitize_vehicle_data($vehicle_data) {
        return array(
            'registration_number' => sanitize_text_field($vehicle_data['registration_number'] ?? ''),
            'make_model' => sanitize_text_field($vehicle_data['make_model'] ?? ''),
            'year' => intval($vehicle_data['year'] ?? 0),
            'capacity' => intval($vehicle_data['capacity'] ?? 0),
            'condition' => sanitize_text_field($vehicle_data['condition'] ?? ''),
            'insurance_expiry' => sanitize_text_field($vehicle_data['insurance_expiry'] ?? ''),
            'inspection_expiry' => sanitize_text_field($vehicle_data['inspection_expiry'] ?? ''),
        );
    }

    /**
     * Sanitize driver data
     *
     * @param array $driver_data Raw driver data
     * @return array Sanitized driver data
     */
    private function sanitize_driver_data($driver_data) {
        return array(
            'name' => sanitize_text_field($driver_data['name'] ?? ''),
            'phone' => sanitize_text_field($driver_data['phone'] ?? ''),
            'license_number' => sanitize_text_field($driver_data['license_number'] ?? ''),
            'license_expiry' => sanitize_text_field($driver_data['license_expiry'] ?? ''),
            'experience' => intval($driver_data['experience'] ?? 0),
            'emergency_contact' => sanitize_text_field($driver_data['emergency_contact'] ?? ''),
        );
    }

    /**
     * Check if route exists
     *
     * @param int $route_id Route ID
     * @return bool True if exists, false otherwise
     */
    private function route_exists($route_id) {
        $route = get_post($route_id);
        return $route && $route->post_type === 'sms_transport_routes';
    }

    /**
     * Check if registration number exists
     *
     * @param string $registration Registration number
     * @param int $exclude_route_id Route ID to exclude
     * @return bool True if exists, false otherwise
     */
    private function registration_exists($registration, $exclude_route_id = 0) {
        $args = array(
            'post_type' => 'sms_transport_routes',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => 'vehicle_details_registration_number',
                    'value' => $registration,
                    'compare' => '='
                )
            )
        );

        if ($exclude_route_id > 0) {
            $args['post__not_in'] = array($exclude_route_id);
        }

        $routes = get_posts($args);
        return !empty($routes);
    }

    /**
     * Check if license number exists
     *
     * @param string $license License number
     * @param int $exclude_route_id Route ID to exclude
     * @return bool True if exists, false otherwise
     */
    private function license_exists($license, $exclude_route_id = 0) {
        $args = array(
            'post_type' => 'sms_transport_routes',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => 'driver_details_license_number',
                    'value' => $license,
                    'compare' => '='
                )
            )
        );

        if ($exclude_route_id > 0) {
            $args['post__not_in'] = array($exclude_route_id);
        }

        $routes = get_posts($args);
        return !empty($routes);
    }

    /**
     * Get routes with vehicle details
     *
     * @return array List of routes with vehicles
     */
    private function get_routes_with_vehicles() {
        $args = array(
            'post_type' => 'sms_transport_routes',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => 'vehicle_details_registration_number',
                    'value' => '',
                    'compare' => '!='
                )
            )
        );

        return get_posts($args);
    }

    /**
     * Get routes with driver details
     *
     * @return array List of routes with drivers
     */
    private function get_routes_with_drivers() {
        $args = array(
            'post_type' => 'sms_transport_routes',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => 'driver_details_name',
                    'value' => '',
                    'compare' => '!='
                )
            )
        );

        return get_posts($args);
    }

    /**
     * Get route enrollment count
     *
     * @param int $route_id Route ID
     * @return int Enrollment count
     */
    private function get_route_enrollment($route_id) {
        $args = array(
            'post_type' => 'sms_students',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => 'transport_route',
                    'value' => $route_id,
                    'compare' => '='
                )
            )
        );

        $students = get_posts($args);
        return count($students);
    }

    /**
     * Update route capacity based on vehicle capacity
     *
     * @param int $route_id Route ID
     * @param int $vehicle_capacity Vehicle capacity
     */
    private function update_route_capacity_from_vehicle($route_id, $vehicle_capacity) {
        $current_route_capacity = get_field('total_capacity', $route_id);
        
        // Only update if vehicle capacity is different and reasonable
        if ($vehicle_capacity != $current_route_capacity && $vehicle_capacity > 0) {
            update_field('total_capacity', $vehicle_capacity, $route_id);
            
            // Recalculate available capacity
            $current_enrollment = $this->get_route_enrollment($route_id);
            update_field('available_capacity', $vehicle_capacity - $current_enrollment, $route_id);
        }
    }

    /**
     * Get vehicle maintenance status
     *
     * @param array $vehicle_details Vehicle details
     * @return string Maintenance status
     */
    private function get_vehicle_maintenance_status($vehicle_details) {
        if (empty($vehicle_details)) {
            return 'unknown';
        }

        if (!empty($vehicle_details['condition'])) {
            if (in_array($vehicle_details['condition'], array('poor', 'maintenance'))) {
                return 'needs_attention';
            }
        }

        // Check insurance and inspection expiry
        $insurance_days = !empty($vehicle_details['insurance_expiry']) ? 
            $this->days_until_date($vehicle_details['insurance_expiry']) : 999;
        $inspection_days = !empty($vehicle_details['inspection_expiry']) ? 
            $this->days_until_date($vehicle_details['inspection_expiry']) : 999;

        if ($insurance_days <= 7 || $inspection_days <= 7) {
            return 'urgent';
        } elseif ($insurance_days <= 30 || $inspection_days <= 30) {
            return 'attention_needed';
        }

        return 'good';
    }

    /**
     * Get insurance status
     *
     * @param array $vehicle_details Vehicle details
     * @return string Insurance status
     */
    private function get_insurance_status($vehicle_details) {
        if (empty($vehicle_details['insurance_expiry'])) {
            return 'unknown';
        }

        $days_until_expiry = $this->days_until_date($vehicle_details['insurance_expiry']);
        
        if ($days_until_expiry < 0) {
            return 'expired';
        } elseif ($days_until_expiry <= 7) {
            return 'expires_soon';
        } elseif ($days_until_expiry <= 30) {
            return 'expires_this_month';
        }

        return 'valid';
    }

    /**
     * Get inspection status
     *
     * @param array $vehicle_details Vehicle details
     * @return string Inspection status
     */
    private function get_inspection_status($vehicle_details) {
        if (empty($vehicle_details['inspection_expiry'])) {
            return 'unknown';
        }

        $days_until_expiry = $this->days_until_date($vehicle_details['inspection_expiry']);
        
        if ($days_until_expiry < 0) {
            return 'expired';
        } elseif ($days_until_expiry <= 7) {
            return 'expires_soon';
        } elseif ($days_until_expiry <= 30) {
            return 'expires_this_month';
        }

        return 'valid';
    }

    /**
     * Get license status
     *
     * @param array $driver_details Driver details
     * @return string License status
     */
    private function get_license_status($driver_details) {
        if (empty($driver_details['license_expiry'])) {
            return 'unknown';
        }

        $days_until_expiry = $this->days_until_date($driver_details['license_expiry']);
        
        if ($days_until_expiry < 0) {
            return 'expired';
        } elseif ($days_until_expiry <= 14) {
            return 'expires_soon';
        } elseif ($days_until_expiry <= 60) {
            return 'expires_this_quarter';
        }

        return 'valid';
    }

    /**
     * Get experience level
     *
     * @param array $driver_details Driver details
     * @return string Experience level
     */
    private function get_experience_level($driver_details) {
        $experience = intval($driver_details['experience'] ?? 0);
        
        if ($experience < 2) {
            return 'novice';
        } elseif ($experience < 5) {
            return 'intermediate';
        } elseif ($experience < 10) {
            return 'experienced';
        }

        return 'expert';
    }

    /**
     * Calculate days until a date
     *
     * @param string $date Date string
     * @return int Days until date (negative if past)
     */
    private function days_until_date($date) {
        $target_date = strtotime($date);
        $current_date = strtotime(date('Y-m-d'));
        
        return floor(($target_date - $current_date) / (60 * 60 * 24));
    }

    /**
     * Validate Kenyan vehicle registration format
     *
     * @param string $registration Registration number
     * @return bool True if valid, false otherwise
     */
    private function is_valid_kenyan_registration($registration) {
        // Kenyan registration formats: KXX XXXZ, KXXX XXXZ
        $patterns = array(
            '/^K[A-Z]{2}\s\d{3}[A-Z]$/',  // KAA 123A
            '/^K[A-Z]{3}\s\d{3}[A-Z]$/',  // KAAA 123A
        );

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, strtoupper($registration))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validate Kenyan phone number format
     *
     * @param string $phone Phone number
     * @return bool True if valid, false otherwise
     */
    private function is_valid_kenyan_phone($phone) {
        $patterns = array(
            '/^\+254[17]\d{8}$/',  // +254 format
            '/^254[17]\d{8}$/',    // 254 format
            '/^0[17]\d{8}$/'       // 0 format
        );

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $phone)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validate date format
     *
     * @param string $date Date string
     * @return bool True if valid, false otherwise
     */
    private function is_valid_date($date) {
        return strtotime($date) !== false;
    }

    /**
     * Check maintenance alerts (scheduled task)
     */
    public function check_maintenance_alerts() {
        $alerts = $this->get_maintenance_alerts();
        
        if (!empty($alerts)) {
            // Send notifications to administrators
            do_action('sms_maintenance_alerts_found', $alerts);
        }
    }

    /**
     * Check license alerts (scheduled task)
     */
    public function check_license_alerts() {
        $alerts = $this->get_license_alerts();
        
        if (!empty($alerts)) {
            // Send notifications to administrators
            do_action('sms_license_alerts_found', $alerts);
        }
    }

    /**
     * AJAX handler for updating vehicle details
     */
    public function ajax_update_vehicle_details() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_transport_nonce')) {
            wp_die(__('Security check failed.', 'school-management-system'));
        }

        // Check permissions
        if (!current_user_can('manage_transport')) {
            wp_die(__('You do not have permission to update vehicle details.', 'school-management-system'));
        }

        $route_id = intval($_POST['route_id']);
        $vehicle_data = $_POST['vehicle_data'];
        $result = $this->update_vehicle_details($route_id, $vehicle_data);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success(array(
                'message' => __('Vehicle details updated successfully.', 'school-management-system')
            ));
        }
    }

    /**
     * AJAX handler for updating driver details
     */
    public function ajax_update_driver_details() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_transport_nonce')) {
            wp_die(__('Security check failed.', 'school-management-system'));
        }

        // Check permissions
        if (!current_user_can('manage_transport')) {
            wp_die(__('You do not have permission to update driver details.', 'school-management-system'));
        }

        $route_id = intval($_POST['route_id']);
        $driver_data = $_POST['driver_data'];
        $result = $this->update_driver_details($route_id, $driver_data);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success(array(
                'message' => __('Driver details updated successfully.', 'school-management-system')
            ));
        }
    }

    /**
     * AJAX handler for getting maintenance alerts
     */
    public function ajax_get_maintenance_alerts() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_transport_nonce')) {
            wp_die(__('Security check failed.', 'school-management-system'));
        }

        // Check permissions
        if (!current_user_can('view_transport')) {
            wp_die(__('You do not have permission to view maintenance alerts.', 'school-management-system'));
        }

        $alerts = $this->get_maintenance_alerts();
        wp_send_json_success($alerts);
    }

    /**
     * AJAX handler for getting license alerts
     */
    public function ajax_get_license_alerts() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_transport_nonce')) {
            wp_die(__('Security check failed.', 'school-management-system'));
        }

        // Check permissions
        if (!current_user_can('view_transport')) {
            wp_die(__('You do not have permission to view license alerts.', 'school-management-system'));
        }

        $alerts = $this->get_license_alerts();
        wp_send_json_success($alerts);
    }

    /**
     * AJAX handler for validating vehicle capacity
     */
    public function ajax_validate_vehicle_capacity() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_transport_nonce')) {
            wp_die(__('Security check failed.', 'school-management-system'));
        }

        $route_id = intval($_POST['route_id']);
        $vehicle_capacity = intval($_POST['vehicle_capacity']);
        $result = $this->validate_vehicle_capacity($route_id, $vehicle_capacity);

        wp_send_json_success($result);
    }
}