<?php
/**
 * Route Manager Class
 *
 * @package SchoolManagementSystem
 * @subpackage Core
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class SMS_Route_Manager
 * 
 * Handles transport route creation, management, and operations
 */
class SMS_Route_Manager {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'init'));
    }

    /**
     * Initialize the route manager
     */
    public function init() {
        // Hook into WordPress actions
        add_action('wp_ajax_sms_create_route', array($this, 'ajax_create_route'));
        add_action('wp_ajax_sms_update_route', array($this, 'ajax_update_route'));
        add_action('wp_ajax_sms_delete_route', array($this, 'ajax_delete_route'));
        add_action('wp_ajax_sms_get_route_details', array($this, 'ajax_get_route_details'));
        add_action('wp_ajax_sms_validate_route_capacity', array($this, 'ajax_validate_route_capacity'));
    }

    /**
     * Create a new transport route
     *
     * @param array $route_data Route information
     * @return int|WP_Error Route ID on success, WP_Error on failure
     */
    public function create_route($route_data) {
        // Validate required fields
        $validation_result = $this->validate_route_data($route_data);
        if (is_wp_error($validation_result)) {
            return $validation_result;
        }

        // Check for duplicate route code
        if ($this->route_code_exists($route_data['route_code'])) {
            return new WP_Error(
                'duplicate_route_code',
                __('Route code already exists. Please use a different code.', 'school-management-system')
            );
        }

        // Create the route post
        $route_post = array(
            'post_title'    => sanitize_text_field($route_data['route_name']),
            'post_content'  => sanitize_textarea_field($route_data['route_description'] ?? ''),
            'post_status'   => 'publish',
            'post_type'     => 'sms_transport_routes',
            'post_author'   => get_current_user_id(),
        );

        $route_id = wp_insert_post($route_post);

        if (is_wp_error($route_id)) {
            return $route_id;
        }

        // Save route metadata
        $this->save_route_metadata($route_id, $route_data);

        // Log the activity
        do_action('sms_route_created', $route_id, $route_data);

        return $route_id;
    }

    /**
     * Update an existing transport route
     *
     * @param int $route_id Route ID
     * @param array $route_data Updated route information
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function update_route($route_id, $route_data) {
        // Check if route exists
        if (!$this->route_exists($route_id)) {
            return new WP_Error(
                'route_not_found',
                __('Transport route not found.', 'school-management-system')
            );
        }

        // Validate route data
        $validation_result = $this->validate_route_data($route_data);
        if (is_wp_error($validation_result)) {
            return $validation_result;
        }

        // Check for duplicate route code (excluding current route)
        if ($this->route_code_exists($route_data['route_code'], $route_id)) {
            return new WP_Error(
                'duplicate_route_code',
                __('Route code already exists. Please use a different code.', 'school-management-system')
            );
        }

        // Update the route post
        $route_post = array(
            'ID'            => $route_id,
            'post_title'    => sanitize_text_field($route_data['route_name']),
            'post_content'  => sanitize_textarea_field($route_data['route_description'] ?? ''),
        );

        $result = wp_update_post($route_post);

        if (is_wp_error($result)) {
            return $result;
        }

        // Update route metadata
        $this->save_route_metadata($route_id, $route_data);

        // Check if route changes affect students and notify if needed
        $this->handle_route_changes($route_id, $route_data);

        // Log the activity
        do_action('sms_route_updated', $route_id, $route_data);

        return true;
    }

    /**
     * Delete a transport route
     *
     * @param int $route_id Route ID
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function delete_route($route_id) {
        // Check if route exists
        if (!$this->route_exists($route_id)) {
            return new WP_Error(
                'route_not_found',
                __('Transport route not found.', 'school-management-system')
            );
        }

        // Check if route has assigned students
        $assigned_students = $this->get_assigned_students($route_id);
        if (!empty($assigned_students)) {
            return new WP_Error(
                'route_has_students',
                __('Cannot delete route with assigned students. Please reassign students first.', 'school-management-system')
            );
        }

        // Delete the route
        $result = wp_delete_post($route_id, true);

        if (!$result) {
            return new WP_Error(
                'delete_failed',
                __('Failed to delete transport route.', 'school-management-system')
            );
        }

        // Log the activity
        do_action('sms_route_deleted', $route_id);

        return true;
    }

    /**
     * Get route details
     *
     * @param int $route_id Route ID
     * @return array|WP_Error Route details on success, WP_Error on failure
     */
    public function get_route_details($route_id) {
        if (!$this->route_exists($route_id)) {
            return new WP_Error(
                'route_not_found',
                __('Transport route not found.', 'school-management-system')
            );
        }

        $route_post = get_post($route_id);
        
        $route_details = array(
            'id' => $route_id,
            'route_name' => $route_post->post_title,
            'route_description' => $route_post->post_content,
            'route_code' => get_field('route_code', $route_id),
            'route_status' => get_field('route_status', $route_id),
            'total_capacity' => get_field('total_capacity', $route_id),
            'current_enrollment' => get_field('current_enrollment', $route_id),
            'available_capacity' => get_field('available_capacity', $route_id),
            'route_stops' => get_field('route_stops', $route_id),
            'total_distance' => get_field('total_distance', $route_id),
            'estimated_duration' => get_field('estimated_duration', $route_id),
            'vehicle_details' => get_field('vehicle_details', $route_id),
            'driver_details' => get_field('driver_details', $route_id),
            'fee_structure_type' => get_field('fee_structure_type', $route_id),
            'base_fee' => get_field('base_fee', $route_id),
            'rate_per_km' => get_field('rate_per_km', $route_id),
            'stop_fees' => get_field('stop_fees', $route_id),
            'fee_frequency' => get_field('fee_frequency', $route_id),
            'discount_siblings' => get_field('discount_siblings', $route_id),
            'created_date' => $route_post->post_date,
            'modified_date' => $route_post->post_modified,
        );

        return $route_details;
    }

    /**
     * Get all active routes
     *
     * @return array List of active routes
     */
    public function get_active_routes() {
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
            )
        );

        $routes = get_posts($args);
        $active_routes = array();

        foreach ($routes as $route) {
            $active_routes[] = array(
                'id' => $route->ID,
                'name' => $route->post_title,
                'code' => get_field('route_code', $route->ID),
                'capacity' => get_field('total_capacity', $route->ID),
                'enrollment' => get_field('current_enrollment', $route->ID),
                'available' => get_field('available_capacity', $route->ID),
            );
        }

        return $active_routes;
    }

    /**
     * Validate route data
     *
     * @param array $route_data Route data to validate
     * @return bool|WP_Error True if valid, WP_Error if invalid
     */
    private function validate_route_data($route_data) {
        $errors = array();

        // Required fields
        if (empty($route_data['route_name'])) {
            $errors[] = __('Route name is required.', 'school-management-system');
        }

        if (empty($route_data['route_code'])) {
            $errors[] = __('Route code is required.', 'school-management-system');
        }

        if (empty($route_data['total_capacity']) || !is_numeric($route_data['total_capacity']) || $route_data['total_capacity'] < 1) {
            $errors[] = __('Valid total capacity is required.', 'school-management-system');
        }

        // Validate route stops if provided
        if (!empty($route_data['route_stops']) && is_array($route_data['route_stops'])) {
            foreach ($route_data['route_stops'] as $index => $stop) {
                if (empty($stop['stop_name'])) {
                    $errors[] = sprintf(__('Stop name is required for stop %d.', 'school-management-system'), $index + 1);
                }
                if (empty($stop['stop_location'])) {
                    $errors[] = sprintf(__('Stop location is required for stop %d.', 'school-management-system'), $index + 1);
                }
                if (empty($stop['pickup_time'])) {
                    $errors[] = sprintf(__('Pickup time is required for stop %d.', 'school-management-system'), $index + 1);
                }
            }
        }

        if (!empty($errors)) {
            return new WP_Error('validation_failed', implode(' ', $errors));
        }

        return true;
    }

    /**
     * Check if route code exists
     *
     * @param string $route_code Route code to check
     * @param int $exclude_id Route ID to exclude from check
     * @return bool True if exists, false otherwise
     */
    private function route_code_exists($route_code, $exclude_id = 0) {
        $args = array(
            'post_type' => 'sms_transport_routes',
            'post_status' => 'any',
            'posts_per_page' => 1,
            'meta_query' => array(
                array(
                    'key' => 'route_code',
                    'value' => $route_code,
                    'compare' => '='
                )
            )
        );

        if ($exclude_id > 0) {
            $args['post__not_in'] = array($exclude_id);
        }

        $existing_routes = get_posts($args);
        return !empty($existing_routes);
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
     * Save route metadata
     *
     * @param int $route_id Route ID
     * @param array $route_data Route data
     */
    private function save_route_metadata($route_id, $route_data) {
        // Basic route information
        update_field('route_code', sanitize_text_field($route_data['route_code']), $route_id);
        update_field('route_status', sanitize_text_field($route_data['route_status'] ?? 'active'), $route_id);
        update_field('total_capacity', intval($route_data['total_capacity']), $route_id);
        
        // Calculate and update current enrollment and available capacity
        $current_enrollment = $this->calculate_current_enrollment($route_id);
        update_field('current_enrollment', $current_enrollment, $route_id);
        update_field('available_capacity', intval($route_data['total_capacity']) - $current_enrollment, $route_id);

        // Route stops and timing
        if (!empty($route_data['route_stops'])) {
            update_field('route_stops', $route_data['route_stops'], $route_id);
        }

        if (isset($route_data['total_distance'])) {
            update_field('total_distance', floatval($route_data['total_distance']), $route_id);
        }

        if (isset($route_data['estimated_duration'])) {
            update_field('estimated_duration', intval($route_data['estimated_duration']), $route_id);
        }

        // Vehicle and driver details
        if (!empty($route_data['vehicle_details'])) {
            update_field('vehicle_details', $route_data['vehicle_details'], $route_id);
        }

        if (!empty($route_data['driver_details'])) {
            update_field('driver_details', $route_data['driver_details'], $route_id);
        }

        // Fee structure
        if (isset($route_data['fee_structure_type'])) {
            update_field('fee_structure_type', sanitize_text_field($route_data['fee_structure_type']), $route_id);
        }

        if (isset($route_data['base_fee'])) {
            update_field('base_fee', floatval($route_data['base_fee']), $route_id);
        }

        if (isset($route_data['rate_per_km'])) {
            update_field('rate_per_km', floatval($route_data['rate_per_km']), $route_id);
        }

        if (!empty($route_data['stop_fees'])) {
            update_field('stop_fees', $route_data['stop_fees'], $route_id);
        }

        if (isset($route_data['fee_frequency'])) {
            update_field('fee_frequency', sanitize_text_field($route_data['fee_frequency']), $route_id);
        }

        if (isset($route_data['discount_siblings'])) {
            update_field('discount_siblings', floatval($route_data['discount_siblings']), $route_id);
        }
    }

    /**
     * Calculate current enrollment for a route
     *
     * @param int $route_id Route ID
     * @return int Current enrollment count
     */
    private function calculate_current_enrollment($route_id) {
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
     * Get students assigned to a route
     *
     * @param int $route_id Route ID
     * @return array List of assigned students
     */
    private function get_assigned_students($route_id) {
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

        return get_posts($args);
    }

    /**
     * Handle route changes and notify affected parties
     *
     * @param int $route_id Route ID
     * @param array $route_data Updated route data
     */
    private function handle_route_changes($route_id, $route_data) {
        // Get assigned students
        $assigned_students = $this->get_assigned_students($route_id);
        
        if (!empty($assigned_students)) {
            // Notify parents of route changes
            do_action('sms_route_changed', $route_id, $assigned_students, $route_data);
        }
    }

    /**
     * AJAX handler for creating a route
     */
    public function ajax_create_route() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_transport_nonce')) {
            wp_die(__('Security check failed.', 'school-management-system'));
        }

        // Check permissions
        if (!current_user_can('manage_transport')) {
            wp_die(__('You do not have permission to create transport routes.', 'school-management-system'));
        }

        $route_data = $_POST['route_data'];
        $result = $this->create_route($route_data);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success(array(
                'route_id' => $result,
                'message' => __('Transport route created successfully.', 'school-management-system')
            ));
        }
    }

    /**
     * AJAX handler for updating a route
     */
    public function ajax_update_route() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_transport_nonce')) {
            wp_die(__('Security check failed.', 'school-management-system'));
        }

        // Check permissions
        if (!current_user_can('manage_transport')) {
            wp_die(__('You do not have permission to update transport routes.', 'school-management-system'));
        }

        $route_id = intval($_POST['route_id']);
        $route_data = $_POST['route_data'];
        $result = $this->update_route($route_id, $route_data);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success(array(
                'message' => __('Transport route updated successfully.', 'school-management-system')
            ));
        }
    }

    /**
     * AJAX handler for deleting a route
     */
    public function ajax_delete_route() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_transport_nonce')) {
            wp_die(__('Security check failed.', 'school-management-system'));
        }

        // Check permissions
        if (!current_user_can('manage_transport')) {
            wp_die(__('You do not have permission to delete transport routes.', 'school-management-system'));
        }

        $route_id = intval($_POST['route_id']);
        $result = $this->delete_route($route_id);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success(array(
                'message' => __('Transport route deleted successfully.', 'school-management-system')
            ));
        }
    }

    /**
     * AJAX handler for getting route details
     */
    public function ajax_get_route_details() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_transport_nonce')) {
            wp_die(__('Security check failed.', 'school-management-system'));
        }

        // Check permissions
        if (!current_user_can('view_transport')) {
            wp_die(__('You do not have permission to view transport routes.', 'school-management-system'));
        }

        $route_id = intval($_POST['route_id']);
        $result = $this->get_route_details($route_id);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success($result);
        }
    }

    /**
     * AJAX handler for validating route capacity
     */
    public function ajax_validate_route_capacity() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_transport_nonce')) {
            wp_die(__('Security check failed.', 'school-management-system'));
        }

        $route_id = intval($_POST['route_id']);
        $current_enrollment = $this->calculate_current_enrollment($route_id);
        $total_capacity = get_field('total_capacity', $route_id);
        $available_capacity = $total_capacity - $current_enrollment;

        wp_send_json_success(array(
            'current_enrollment' => $current_enrollment,
            'total_capacity' => $total_capacity,
            'available_capacity' => $available_capacity,
            'is_full' => $available_capacity <= 0
        ));
    }
}