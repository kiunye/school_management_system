<?php
/**
 * Transport Assignment Manager Class
 *
 * @package SchoolManagementSystem
 * @subpackage Core
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class SMS_Transport_Assigner
 * 
 * Handles student-route assignments, capacity validation, and fee calculations
 */
class SMS_Transport_Assigner {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'init'));
    }

    /**
     * Initialize the transport assigner
     */
    public function init() {
        // Hook into WordPress actions
        add_action('wp_ajax_sms_assign_student_to_route', array($this, 'ajax_assign_student_to_route'));
        add_action('wp_ajax_sms_unassign_student_from_route', array($this, 'ajax_unassign_student_from_route'));
        add_action('wp_ajax_sms_bulk_assign_students', array($this, 'ajax_bulk_assign_students'));
        add_action('wp_ajax_sms_get_route_assignments', array($this, 'ajax_get_route_assignments'));
        add_action('wp_ajax_sms_calculate_transport_fee', array($this, 'ajax_calculate_transport_fee'));
        add_action('wp_ajax_sms_get_available_routes', array($this, 'ajax_get_available_routes'));
        
        // Hook into student save to handle transport assignments
        add_action('save_post_sms_students', array($this, 'handle_student_transport_save'), 10, 2);
        
        // Hook into route changes to notify parents
        add_action('sms_route_changed', array($this, 'notify_parents_of_route_changes'), 10, 3);
    }

    /**
     * Assign a student to a transport route
     *
     * @param int $student_id Student ID
     * @param int $route_id Route ID
     * @param string $pickup_stop Pickup stop name
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function assign_student_to_route($student_id, $route_id, $pickup_stop = '') {
        // Validate student exists
        if (!$this->student_exists($student_id)) {
            return new WP_Error(
                'student_not_found',
                __('Student not found.', 'school-management-system')
            );
        }

        // Validate route exists and is active
        if (!$this->route_exists_and_active($route_id)) {
            return new WP_Error(
                'route_not_available',
                __('Transport route not found or not active.', 'school-management-system')
            );
        }

        // Check route capacity
        $capacity_check = $this->check_route_capacity($route_id);
        if (is_wp_error($capacity_check)) {
            return $capacity_check;
        }

        // Check if student is already assigned to a route
        $current_route = get_field('transport_route', $student_id);
        if ($current_route && $current_route != $route_id) {
            // Unassign from current route first
            $this->unassign_student_from_route($student_id);
        }

        // Validate pickup stop if provided
        if (!empty($pickup_stop)) {
            $stop_validation = $this->validate_pickup_stop($route_id, $pickup_stop);
            if (is_wp_error($stop_validation)) {
                return $stop_validation;
            }
        }

        // Assign student to route
        update_field('transport_route', $route_id, $student_id);
        
        if (!empty($pickup_stop)) {
            update_field('transport_pickup_stop', $pickup_stop, $student_id);
        }

        // Update route enrollment count
        $this->update_route_enrollment($route_id);

        // Calculate and save transport fee
        $transport_fee = $this->calculate_transport_fee($student_id, $route_id, $pickup_stop);
        if (!is_wp_error($transport_fee)) {
            update_field('transport_fee', $transport_fee, $student_id);
        }

        // Log the assignment
        do_action('sms_student_assigned_to_route', $student_id, $route_id, $pickup_stop);

        // Send notification to parents
        $this->notify_parent_of_assignment($student_id, $route_id);

        return true;
    }

    /**
     * Unassign a student from their current transport route
     *
     * @param int $student_id Student ID
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function unassign_student_from_route($student_id) {
        // Validate student exists
        if (!$this->student_exists($student_id)) {
            return new WP_Error(
                'student_not_found',
                __('Student not found.', 'school-management-system')
            );
        }

        // Get current route assignment
        $current_route = get_field('transport_route', $student_id);
        if (!$current_route) {
            return new WP_Error(
                'no_assignment',
                __('Student is not assigned to any transport route.', 'school-management-system')
            );
        }

        // Remove assignment
        delete_field('transport_route', $student_id);
        delete_field('transport_pickup_stop', $student_id);
        delete_field('transport_fee', $student_id);

        // Update route enrollment count
        $this->update_route_enrollment($current_route);

        // Log the unassignment
        do_action('sms_student_unassigned_from_route', $student_id, $current_route);

        // Send notification to parents
        $this->notify_parent_of_unassignment($student_id, $current_route);

        return true;
    }

    /**
     * Bulk assign multiple students to routes
     *
     * @param array $assignments Array of student-route assignments
     * @return array Results of assignments
     */
    public function bulk_assign_students($assignments) {
        $results = array(
            'success' => array(),
            'errors' => array(),
            'total' => count($assignments)
        );

        foreach ($assignments as $assignment) {
            $student_id = intval($assignment['student_id']);
            $route_id = intval($assignment['route_id']);
            $pickup_stop = sanitize_text_field($assignment['pickup_stop'] ?? '');

            $result = $this->assign_student_to_route($student_id, $route_id, $pickup_stop);

            if (is_wp_error($result)) {
                $results['errors'][] = array(
                    'student_id' => $student_id,
                    'error' => $result->get_error_message()
                );
            } else {
                $results['success'][] = $student_id;
            }
        }

        return $results;
    }

    /**
     * Get all students assigned to a specific route
     *
     * @param int $route_id Route ID
     * @return array List of assigned students
     */
    public function get_route_assignments($route_id) {
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
        $assignments = array();

        foreach ($students as $student) {
            $assignments[] = array(
                'student_id' => $student->ID,
                'student_name' => $student->post_title,
                'admission_number' => get_field('admission_number', $student->ID),
                'grade_level' => get_field('grade_level', $student->ID),
                'pickup_stop' => get_field('transport_pickup_stop', $student->ID),
                'transport_fee' => get_field('transport_fee', $student->ID),
                'parent_phone' => get_field('parent_phone', $student->ID),
                'assignment_date' => get_field('transport_assignment_date', $student->ID) ?: $student->post_date
            );
        }

        return $assignments;
    }

    /**
     * Calculate transport fee for a student
     *
     * @param int $student_id Student ID
     * @param int $route_id Route ID
     * @param string $pickup_stop Pickup stop name
     * @return float|WP_Error Transport fee amount or error
     */
    public function calculate_transport_fee($student_id, $route_id, $pickup_stop = '') {
        // Get route fee structure
        $fee_structure_type = get_field('fee_structure_type', $route_id);
        $base_fee = floatval(get_field('base_fee', $route_id));
        
        if (empty($fee_structure_type)) {
            return new WP_Error(
                'no_fee_structure',
                __('No fee structure defined for this route.', 'school-management-system')
            );
        }

        $calculated_fee = 0;

        switch ($fee_structure_type) {
            case 'flat_rate':
                $calculated_fee = $base_fee;
                break;

            case 'distance_based':
                $calculated_fee = $this->calculate_distance_based_fee($route_id, $pickup_stop);
                break;

            case 'stop_based':
                $calculated_fee = $this->calculate_stop_based_fee($route_id, $pickup_stop);
                break;

            case 'custom':
                $calculated_fee = $this->calculate_custom_fee($student_id, $route_id, $pickup_stop);
                break;

            default:
                $calculated_fee = $base_fee;
        }

        // Apply sibling discount if applicable
        $sibling_discount = $this->calculate_sibling_discount($student_id, $route_id);
        if ($sibling_discount > 0) {
            $calculated_fee = $calculated_fee * (1 - ($sibling_discount / 100));
        }

        return round($calculated_fee, 2);
    }

    /**
     * Get available routes for student assignment
     *
     * @param array $filters Optional filters
     * @return array List of available routes
     */
    public function get_available_routes($filters = array()) {
        $args = array(
            'post_type' => 'sms_transport_routes',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => 'route_status',
                    'value' => 'active',
                    'compare' => '='
                )
            ),
            'orderby' => 'title',
            'order' => 'ASC'
        );

        // Apply filters
        if (!empty($filters['has_capacity'])) {
            $args['meta_query'][] = array(
                'key' => 'available_capacity',
                'value' => 0,
                'compare' => '>'
            );
        }

        $routes = get_posts($args);
        $available_routes = array();

        foreach ($routes as $route) {
            $total_capacity = get_field('total_capacity', $route->ID);
            $current_enrollment = get_field('current_enrollment', $route->ID);
            $available_capacity = $total_capacity - $current_enrollment;

            // Skip routes with no capacity if filter is applied
            if (!empty($filters['has_capacity']) && $available_capacity <= 0) {
                continue;
            }

            $route_stops = get_field('route_stops', $route->ID);
            $stops_list = array();
            
            if (is_array($route_stops)) {
                foreach ($route_stops as $stop) {
                    $stops_list[] = array(
                        'name' => $stop['stop_name'],
                        'location' => $stop['stop_location'],
                        'pickup_time' => $stop['pickup_time'],
                        'order' => $stop['stop_order']
                    );
                }
                
                // Sort stops by order
                usort($stops_list, function($a, $b) {
                    return $a['order'] - $b['order'];
                });
            }

            $available_routes[] = array(
                'id' => $route->ID,
                'name' => $route->post_title,
                'code' => get_field('route_code', $route->ID),
                'total_capacity' => $total_capacity,
                'current_enrollment' => $current_enrollment,
                'available_capacity' => $available_capacity,
                'base_fee' => get_field('base_fee', $route->ID),
                'fee_structure_type' => get_field('fee_structure_type', $route->ID),
                'stops' => $stops_list,
                'vehicle_registration' => get_field('vehicle_details_registration_number', $route->ID),
                'driver_name' => get_field('driver_details_name', $route->ID)
            );
        }

        return $available_routes;
    }

    /**
     * Check route capacity before assignment
     *
     * @param int $route_id Route ID
     * @return bool|WP_Error True if capacity available, WP_Error if full
     */
    private function check_route_capacity($route_id) {
        $total_capacity = get_field('total_capacity', $route_id);
        $current_enrollment = get_field('current_enrollment', $route_id);

        if ($current_enrollment >= $total_capacity) {
            return new WP_Error(
                'route_full',
                __('Transport route is at full capacity.', 'school-management-system')
            );
        }

        return true;
    }

    /**
     * Validate pickup stop exists on route
     *
     * @param int $route_id Route ID
     * @param string $pickup_stop Stop name
     * @return bool|WP_Error True if valid, WP_Error if invalid
     */
    private function validate_pickup_stop($route_id, $pickup_stop) {
        $route_stops = get_field('route_stops', $route_id);
        
        if (empty($route_stops) || !is_array($route_stops)) {
            return new WP_Error(
                'no_stops_defined',
                __('No stops defined for this route.', 'school-management-system')
            );
        }

        $stop_names = array_column($route_stops, 'stop_name');
        
        if (!in_array($pickup_stop, $stop_names)) {
            return new WP_Error(
                'invalid_stop',
                __('Invalid pickup stop for this route.', 'school-management-system')
            );
        }

        return true;
    }

    /**
     * Update route enrollment count
     *
     * @param int $route_id Route ID
     */
    private function update_route_enrollment($route_id) {
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
        $enrollment_count = count($students);
        $total_capacity = get_field('total_capacity', $route_id);

        update_field('current_enrollment', $enrollment_count, $route_id);
        update_field('available_capacity', $total_capacity - $enrollment_count, $route_id);
    }

    /**
     * Calculate distance-based fee
     *
     * @param int $route_id Route ID
     * @param string $pickup_stop Stop name
     * @return float Calculated fee
     */
    private function calculate_distance_based_fee($route_id, $pickup_stop) {
        $rate_per_km = floatval(get_field('rate_per_km', $route_id));
        $route_stops = get_field('route_stops', $route_id);
        
        if (empty($route_stops) || empty($pickup_stop)) {
            return floatval(get_field('base_fee', $route_id));
        }

        foreach ($route_stops as $stop) {
            if ($stop['stop_name'] === $pickup_stop) {
                $distance = floatval($stop['stop_distance'] ?? 0);
                return $rate_per_km * $distance;
            }
        }

        return floatval(get_field('base_fee', $route_id));
    }

    /**
     * Calculate stop-based fee
     *
     * @param int $route_id Route ID
     * @param string $pickup_stop Stop name
     * @return float Calculated fee
     */
    private function calculate_stop_based_fee($route_id, $pickup_stop) {
        $stop_fees = get_field('stop_fees', $route_id);
        
        if (empty($stop_fees) || empty($pickup_stop)) {
            return floatval(get_field('base_fee', $route_id));
        }

        foreach ($stop_fees as $stop_fee) {
            if ($stop_fee['stop_name'] === $pickup_stop) {
                return floatval($stop_fee['fee_amount']);
            }
        }

        return floatval(get_field('base_fee', $route_id));
    }

    /**
     * Calculate custom fee (can be extended for specific business logic)
     *
     * @param int $student_id Student ID
     * @param int $route_id Route ID
     * @param string $pickup_stop Stop name
     * @return float Calculated fee
     */
    private function calculate_custom_fee($student_id, $route_id, $pickup_stop) {
        // Default to base fee, can be customized based on specific requirements
        $base_fee = floatval(get_field('base_fee', $route_id));
        
        // Allow custom fee calculation via filter
        return apply_filters('sms_calculate_custom_transport_fee', $base_fee, $student_id, $route_id, $pickup_stop);
    }

    /**
     * Calculate sibling discount
     *
     * @param int $student_id Student ID
     * @param int $route_id Route ID
     * @return float Discount percentage
     */
    private function calculate_sibling_discount($student_id, $route_id) {
        $discount_percentage = floatval(get_field('discount_siblings', $route_id));
        
        if ($discount_percentage <= 0) {
            return 0;
        }

        // Get student's parent information
        $parent_phone = get_field('parent_phone', $student_id);
        $parent_email = get_field('parent_email', $student_id);
        
        if (empty($parent_phone) && empty($parent_email)) {
            return 0;
        }

        // Find siblings on the same route
        $args = array(
            'post_type' => 'sms_students',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'post__not_in' => array($student_id),
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => 'transport_route',
                    'value' => $route_id,
                    'compare' => '='
                ),
                array(
                    'relation' => 'OR',
                    array(
                        'key' => 'parent_phone',
                        'value' => $parent_phone,
                        'compare' => '='
                    ),
                    array(
                        'key' => 'parent_email',
                        'value' => $parent_email,
                        'compare' => '='
                    )
                )
            )
        );

        $siblings = get_posts($args);
        
        return count($siblings) > 0 ? $discount_percentage : 0;
    }

    /**
     * Check if student exists
     *
     * @param int $student_id Student ID
     * @return bool True if exists, false otherwise
     */
    private function student_exists($student_id) {
        $student = get_post($student_id);
        return $student && $student->post_type === 'sms_students';
    }

    /**
     * Check if route exists and is active
     *
     * @param int $route_id Route ID
     * @return bool True if exists and active, false otherwise
     */
    private function route_exists_and_active($route_id) {
        $route = get_post($route_id);
        if (!$route || $route->post_type !== 'sms_transport_routes') {
            return false;
        }

        $route_status = get_field('route_status', $route_id);
        return $route_status === 'active';
    }

    /**
     * Notify parent of transport assignment
     *
     * @param int $student_id Student ID
     * @param int $route_id Route ID
     */
    private function notify_parent_of_assignment($student_id, $route_id) {
        $student_name = get_the_title($student_id);
        $route_name = get_the_title($route_id);
        $parent_phone = get_field('parent_phone', $student_id);
        $pickup_stop = get_field('transport_pickup_stop', $student_id);
        $transport_fee = get_field('transport_fee', $student_id);

        if (empty($parent_phone)) {
            return;
        }

        // Get route timing information
        $route_stops = get_field('route_stops', $route_id);
        $pickup_time = '';
        
        if (is_array($route_stops)) {
            foreach ($route_stops as $stop) {
                if ($stop['stop_name'] === $pickup_stop) {
                    $pickup_time = $stop['pickup_time'];
                    break;
                }
            }
        }

        // Send SMS notification
        do_action('sms_send_transport_assignment_notification', array(
            'student_id' => $student_id,
            'student_name' => $student_name,
            'route_name' => $route_name,
            'pickup_stop' => $pickup_stop,
            'pickup_time' => $pickup_time,
            'transport_fee' => $transport_fee,
            'parent_phone' => $parent_phone
        ));
    }

    /**
     * Notify parent of transport unassignment
     *
     * @param int $student_id Student ID
     * @param int $route_id Route ID
     */
    private function notify_parent_of_unassignment($student_id, $route_id) {
        $student_name = get_the_title($student_id);
        $route_name = get_the_title($route_id);
        $parent_phone = get_field('parent_phone', $student_id);

        if (empty($parent_phone)) {
            return;
        }

        // Send SMS notification
        do_action('sms_send_transport_unassignment_notification', array(
            'student_id' => $student_id,
            'student_name' => $student_name,
            'route_name' => $route_name,
            'parent_phone' => $parent_phone
        ));
    }

    /**
     * Notify parents of route changes
     *
     * @param int $route_id Route ID
     * @param array $affected_students List of affected students
     * @param array $route_data Updated route data
     */
    public function notify_parents_of_route_changes($route_id, $affected_students, $route_data) {
        foreach ($affected_students as $student) {
            $parent_phone = get_field('parent_phone', $student->ID);
            
            if (empty($parent_phone)) {
                continue;
            }

            // Send SMS notification about route changes
            do_action('sms_send_route_change_notification', array(
                'student_id' => $student->ID,
                'student_name' => $student->post_title,
                'route_id' => $route_id,
                'route_name' => get_the_title($route_id),
                'parent_phone' => $parent_phone,
                'changes' => $route_data
            ));
        }
    }

    /**
     * Handle student transport assignment on save
     *
     * @param int $post_id Student post ID
     * @param WP_Post $post Student post object
     */
    public function handle_student_transport_save($post_id, $post) {
        // Skip if this is an autosave or revision
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }

        // Get transport route assignment
        $transport_route = get_field('transport_route', $post_id);
        
        if ($transport_route) {
            // Update assignment date
            update_field('transport_assignment_date', current_time('mysql'), $post_id);
            
            // Recalculate transport fee
            $pickup_stop = get_field('transport_pickup_stop', $post_id);
            $transport_fee = $this->calculate_transport_fee($post_id, $transport_route, $pickup_stop);
            
            if (!is_wp_error($transport_fee)) {
                update_field('transport_fee', $transport_fee, $post_id);
            }
            
            // Update route enrollment
            $this->update_route_enrollment($transport_route);
        }
    }

    /**
     * AJAX handler for assigning student to route
     */
    public function ajax_assign_student_to_route() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_transport_nonce')) {
            wp_die(__('Security check failed.', 'school-management-system'));
        }

        // Check permissions
        if (!current_user_can('manage_transport')) {
            wp_die(__('You do not have permission to assign students to routes.', 'school-management-system'));
        }

        $student_id = intval($_POST['student_id']);
        $route_id = intval($_POST['route_id']);
        $pickup_stop = sanitize_text_field($_POST['pickup_stop'] ?? '');

        $result = $this->assign_student_to_route($student_id, $route_id, $pickup_stop);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success(array(
                'message' => __('Student assigned to transport route successfully.', 'school-management-system')
            ));
        }
    }

    /**
     * AJAX handler for unassigning student from route
     */
    public function ajax_unassign_student_from_route() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_transport_nonce')) {
            wp_die(__('Security check failed.', 'school-management-system'));
        }

        // Check permissions
        if (!current_user_can('manage_transport')) {
            wp_die(__('You do not have permission to unassign students from routes.', 'school-management-system'));
        }

        $student_id = intval($_POST['student_id']);
        $result = $this->unassign_student_from_route($student_id);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success(array(
                'message' => __('Student unassigned from transport route successfully.', 'school-management-system')
            ));
        }
    }

    /**
     * AJAX handler for bulk assigning students
     */
    public function ajax_bulk_assign_students() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_transport_nonce')) {
            wp_die(__('Security check failed.', 'school-management-system'));
        }

        // Check permissions
        if (!current_user_can('manage_transport')) {
            wp_die(__('You do not have permission to assign students to routes.', 'school-management-system'));
        }

        $assignments = $_POST['assignments'];
        $results = $this->bulk_assign_students($assignments);

        wp_send_json_success($results);
    }

    /**
     * AJAX handler for getting route assignments
     */
    public function ajax_get_route_assignments() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_transport_nonce')) {
            wp_die(__('Security check failed.', 'school-management-system'));
        }

        // Check permissions
        if (!current_user_can('view_transport')) {
            wp_die(__('You do not have permission to view transport assignments.', 'school-management-system'));
        }

        $route_id = intval($_POST['route_id']);
        $assignments = $this->get_route_assignments($route_id);

        wp_send_json_success($assignments);
    }

    /**
     * AJAX handler for calculating transport fee
     */
    public function ajax_calculate_transport_fee() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_transport_nonce')) {
            wp_die(__('Security check failed.', 'school-management-system'));
        }

        $student_id = intval($_POST['student_id']);
        $route_id = intval($_POST['route_id']);
        $pickup_stop = sanitize_text_field($_POST['pickup_stop'] ?? '');

        $fee = $this->calculate_transport_fee($student_id, $route_id, $pickup_stop);

        if (is_wp_error($fee)) {
            wp_send_json_error($fee->get_error_message());
        } else {
            wp_send_json_success(array(
                'fee' => $fee,
                'formatted_fee' => 'KES ' . number_format($fee, 2)
            ));
        }
    }

    /**
     * AJAX handler for getting available routes
     */
    public function ajax_get_available_routes() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_transport_nonce')) {
            wp_die(__('Security check failed.', 'school-management-system'));
        }

        // Check permissions
        if (!current_user_can('view_transport')) {
            wp_die(__('You do not have permission to view transport routes.', 'school-management-system'));
        }

        $filters = $_POST['filters'] ?? array();
        $routes = $this->get_available_routes($filters);

        wp_send_json_success($routes);
    }
}