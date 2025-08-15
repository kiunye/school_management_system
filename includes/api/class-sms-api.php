<?php
/**
 * REST API endpoints for the plugin.
 *
 * @package    School_Management_System
 * @subpackage School_Management_System/includes/api
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * REST API class.
 */
class SMS_API extends SMS_Base {

    /**
     * API namespace.
     */
    private $namespace = 'sms/v1';

    /**
     * Register REST API routes.
     */
    public function register_routes() {
        // System status endpoint
        register_rest_route($this->namespace, '/status', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_system_status'),
            'permission_callback' => array($this, 'check_admin_permissions')
        ));

        // Students endpoints
        register_rest_route($this->namespace, '/students', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_students'),
                'permission_callback' => array($this, 'check_student_read_permissions')
            ),
            array(
                'methods' => 'POST',
                'callback' => array($this, 'create_student'),
                'permission_callback' => array($this, 'check_student_write_permissions')
            )
        ));

        register_rest_route($this->namespace, '/students/(?P<id>\d+)', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_student'),
                'permission_callback' => array($this, 'check_student_read_permissions')
            ),
            array(
                'methods' => 'PUT',
                'callback' => array($this, 'update_student'),
                'permission_callback' => array($this, 'check_student_write_permissions')
            ),
            array(
                'methods' => 'DELETE',
                'callback' => array($this, 'delete_student'),
                'permission_callback' => array($this, 'check_student_write_permissions')
            )
        ));

        // Payment gateway callbacks
        register_rest_route($this->namespace, '/payments/mpesa/callback', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_mpesa_callback'),
            'permission_callback' => '__return_true' // Public endpoint for gateway callbacks
        ));

        register_rest_route($this->namespace, '/payments/airtel/callback', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_airtel_callback'),
            'permission_callback' => '__return_true' // Public endpoint for gateway callbacks
        ));

        // SMS webhook endpoint
        register_rest_route($this->namespace, '/sms/webhook', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_sms_webhook'),
            'permission_callback' => '__return_true' // Public endpoint for SMS service callbacks
        ));
    }

    /**
     * Get system status.
     */
    public function get_system_status($request) {
        $status = array(
            'plugin_version' => SMS_VERSION,
            'wordpress_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'database_version' => $this->get_database_version(),
            'active_students' => $this->get_active_students_count(),
            'active_classes' => $this->get_active_classes_count(),
            'pending_payments' => $this->get_pending_payments_count(),
            'system_health' => $this->check_system_health()
        );

        return rest_ensure_response($status);
    }

    /**
     * Get students.
     */
    public function get_students($request) {
        $params = $request->get_params();
        
        $args = array(
            'post_type' => 'cpt_students',
            'post_status' => 'publish',
            'posts_per_page' => isset($params['per_page']) ? intval($params['per_page']) : 10,
            'paged' => isset($params['page']) ? intval($params['page']) : 1
        );

        $students = get_posts($args);
        $formatted_students = array();

        foreach ($students as $student) {
            $formatted_students[] = $this->format_student_data($student);
        }

        return rest_ensure_response($formatted_students);
    }

    /**
     * Get single student.
     */
    public function get_student($request) {
        $student_id = $request['id'];
        $student = get_post($student_id);

        if (!$student || $student->post_type !== 'cpt_students') {
            return new WP_Error('student_not_found', 'Student not found', array('status' => 404));
        }

        return rest_ensure_response($this->format_student_data($student));
    }

    /**
     * Create student.
     */
    public function create_student($request) {
        $params = $request->get_json_params();
        
        // Validate required fields
        $required_fields = array('full_name', 'date_of_birth', 'grade_level');
        foreach ($required_fields as $field) {
            if (empty($params[$field])) {
                return new WP_Error('missing_field', "Field {$field} is required", array('status' => 400));
            }
        }

        // This would typically call a student manager class
        // For now, return a placeholder response
        return rest_ensure_response(array(
            'message' => 'Student creation endpoint ready',
            'data' => $params
        ));
    }

    /**
     * Update student.
     */
    public function update_student($request) {
        $student_id = $request['id'];
        $params = $request->get_json_params();

        $student = get_post($student_id);
        if (!$student || $student->post_type !== 'cpt_students') {
            return new WP_Error('student_not_found', 'Student not found', array('status' => 404));
        }

        // This would typically call a student manager class
        return rest_ensure_response(array(
            'message' => 'Student update endpoint ready',
            'student_id' => $student_id,
            'data' => $params
        ));
    }

    /**
     * Delete student.
     */
    public function delete_student($request) {
        $student_id = $request['id'];
        
        $student = get_post($student_id);
        if (!$student || $student->post_type !== 'cpt_students') {
            return new WP_Error('student_not_found', 'Student not found', array('status' => 404));
        }

        // This would typically call a student manager class
        return rest_ensure_response(array(
            'message' => 'Student deletion endpoint ready',
            'student_id' => $student_id
        ));
    }

    /**
     * Handle M-Pesa payment callback.
     */
    public function handle_mpesa_callback($request) {
        $callback_data = $request->get_json_params();
        
        // Log the callback for debugging
        $this->log('M-Pesa callback received', 'info', $callback_data);
        
        // This would typically call the M-Pesa gateway handler
        return rest_ensure_response(array(
            'ResultCode' => 0,
            'ResultDesc' => 'Callback received successfully'
        ));
    }

    /**
     * Handle Airtel Money callback.
     */
    public function handle_airtel_callback($request) {
        $callback_data = $request->get_json_params();
        
        // Log the callback for debugging
        $this->log('Airtel Money callback received', 'info', $callback_data);
        
        // This would typically call the Airtel Money gateway handler
        return rest_ensure_response(array(
            'status' => 'success',
            'message' => 'Callback received successfully'
        ));
    }

    /**
     * Handle SMS webhook.
     */
    public function handle_sms_webhook($request) {
        $webhook_data = $request->get_json_params();
        
        // Log the webhook for debugging
        $this->log('SMS webhook received', 'info', $webhook_data);
        
        // This would typically call the SMS handler
        return rest_ensure_response(array(
            'status' => 'success',
            'message' => 'Webhook received successfully'
        ));
    }

    /**
     * Check admin permissions.
     */
    public function check_admin_permissions() {
        return current_user_can('manage_system_settings');
    }

    /**
     * Check student read permissions.
     */
    public function check_student_read_permissions() {
        return current_user_can('view_student_records') || current_user_can('manage_students');
    }

    /**
     * Check student write permissions.
     */
    public function check_student_write_permissions() {
        return current_user_can('manage_students');
    }

    /**
     * Format student data for API response.
     */
    private function format_student_data($student) {
        return array(
            'id' => $student->ID,
            'admission_number' => get_post_meta($student->ID, 'admission_number', true),
            'full_name' => get_post_meta($student->ID, 'full_name', true),
            'date_of_birth' => get_post_meta($student->ID, 'date_of_birth', true),
            'grade_level' => get_post_meta($student->ID, 'grade_level', true),
            'status' => get_post_meta($student->ID, 'status', true),
            'created_date' => $student->post_date
        );
    }

    /**
     * Get database version.
     */
    private function get_database_version() {
        global $wpdb;
        return $wpdb->get_var('SELECT VERSION()');
    }

    /**
     * Get active students count.
     */
    private function get_active_students_count() {
        $count = wp_count_posts('cpt_students');
        return isset($count->publish) ? $count->publish : 0;
    }

    /**
     * Get active classes count.
     */
    private function get_active_classes_count() {
        $count = wp_count_posts('cpt_classes');
        return isset($count->publish) ? $count->publish : 0;
    }

    /**
     * Get pending payments count.
     */
    private function get_pending_payments_count() {
        // This would query for pending invoices/payments
        return 0; // Placeholder
    }

    /**
     * Check system health.
     */
    private function check_system_health() {
        $health_checks = array(
            'database_connection' => $this->check_database_connection(),
            'required_plugins' => $this->check_required_plugins_active(),
            'file_permissions' => $this->check_file_permissions(),
            'memory_limit' => $this->check_memory_limit()
        );

        $overall_health = 'good';
        foreach ($health_checks as $check => $status) {
            if ($status !== 'good') {
                $overall_health = 'warning';
                break;
            }
        }

        return array(
            'overall' => $overall_health,
            'checks' => $health_checks
        );
    }

    /**
     * Check database connection.
     */
    private function check_database_connection() {
        global $wpdb;
        return $wpdb->check_connection() ? 'good' : 'error';
    }

    /**
     * Check required plugins.
     */
    private function check_required_plugins_active() {
        $required_plugins = array(
            'advanced-custom-fields-pro/acf.php',
            'user-role-editor/user-role-editor.php'
        );

        foreach ($required_plugins as $plugin) {
            if (!is_plugin_active($plugin)) {
                return 'warning';
            }
        }

        return 'good';
    }

    /**
     * Check file permissions.
     */
    private function check_file_permissions() {
        $upload_dir = wp_upload_dir();
        return is_writable($upload_dir['basedir']) ? 'good' : 'warning';
    }

    /**
     * Check memory limit.
     */
    private function check_memory_limit() {
        $memory_limit = wp_convert_hr_to_bytes(ini_get('memory_limit'));
        $recommended_limit = 256 * 1024 * 1024; // 256MB
        
        return $memory_limit >= $recommended_limit ? 'good' : 'warning';
    }
}