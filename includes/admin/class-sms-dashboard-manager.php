<?php
/**
 * Dashboard Manager for role-specific dashboards
 *
 * @package    School_Management_System
 * @subpackage School_Management_System/includes/admin
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Dashboard Manager Class
 */
class SMS_Dashboard_Manager extends SMS_Base {

    /**
     * Single instance of the class
     */
    private static $instance = null;

    /**
     * Get single instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize the dashboard manager
     */
    public function __construct() {
        // Prevent multiple instances
        if (null !== self::$instance) {
            return self::$instance;
        }
        
        parent::__construct();
        add_action('admin_menu', array($this, 'add_role_specific_menus'), 15);
        add_action('wp_ajax_sms_get_dashboard_data', array($this, 'get_dashboard_data'));
        add_action('wp_ajax_sms_get_recent_activity', array($this, 'get_recent_activity'));
    }

    /**
     * Add role-specific menu items
     */
    public function add_role_specific_menus() {
        // Ensure we're in admin and user is logged in
        if (!is_admin() || !is_user_logged_in()) {
            return;
        }
        
        $current_user = wp_get_current_user();
        if (!$current_user || !isset($current_user->roles)) {
            return;
        }
        
        $user_roles = $current_user->roles;

        // Administrator Dashboard
        if (in_array('administrator', $user_roles) || in_array('sms_admin', $user_roles) || current_user_can('manage_options') || current_user_can('manage_system_settings')) {
            add_submenu_page(
                'school-management',
                __('Admin Dashboard', 'school-management-system'),
                __('Admin Dashboard', 'school-management-system'),
                'manage_system_settings',
                'sms-admin-dashboard',
                [$this, 'display_admin_dashboard']
            );
        }

        // Teacher Dashboard
        if (in_array('sms_teacher', $user_roles)) {
            add_submenu_page(
                'school-management',
                __('Teacher Dashboard', 'school-management-system'),
                __('Teacher Dashboard', 'school-management-system'),
                'read', // Changed from 'edit_posts' to 'read' for broader access
                'sms-teacher-dashboard',
                [$this, 'display_teacher_dashboard']
            );
        }

        // Parent Dashboard
        if (in_array('sms_parent', $user_roles)) {
            add_submenu_page(
                'school-management',
                __('Parent Dashboard', 'school-management-system'),
                __('Parent Dashboard', 'school-management-system'),
                'read',
                'sms-parent-dashboard',
                [$this, 'display_parent_dashboard']
            );
        }

        // Student Dashboard
        if (in_array('sms_student', $user_roles)) {
            add_submenu_page(
                'school-management',
                __('Student Dashboard', 'school-management-system'),
                __('Student Dashboard', 'school-management-system'),
                'read',
                'sms-student-dashboard',
                [$this, 'display_student_dashboard']
            );
        }
    }

    /**
     * Display administrator dashboard
     */
    public function display_admin_dashboard() {
        if (!current_user_can('manage_options') && !current_user_can('manage_system_settings')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        try {
            $dashboard_data = $this->get_admin_dashboard_data();
            $template_path = SMS_PLUGIN_DIR . 'admin/partials/admin-dashboard.php';
            
            if (file_exists($template_path)) {
                include $template_path;
            } else {
                echo '<div class="wrap"><h1>Admin Dashboard</h1><p>Dashboard template not found.</p></div>';
            }
        } catch (Exception $e) {
            echo '<div class="wrap"><h1>Admin Dashboard</h1><div class="notice notice-error"><p>Error loading dashboard: ' . esc_html($e->getMessage()) . '</p></div></div>';
        }
    }

    /**
     * Display teacher dashboard
     */
    public function display_teacher_dashboard() {
        if (!current_user_can('read')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        try {
            $dashboard_data = $this->get_teacher_dashboard_data();
            $template_path = SMS_PLUGIN_DIR . 'admin/partials/teacher-dashboard.php';
            
            if (file_exists($template_path)) {
                include $template_path;
            } else {
                echo '<div class="wrap"><h1>Teacher Dashboard</h1><p>Dashboard template not found.</p></div>';
            }
        } catch (Exception $e) {
            echo '<div class="wrap"><h1>Teacher Dashboard</h1><div class="notice notice-error"><p>Error loading dashboard: ' . esc_html($e->getMessage()) . '</p></div></div>';
        }
    }

    /**
     * Display parent dashboard
     */
    public function display_parent_dashboard() {
        if (!current_user_can('read')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        try {
            $dashboard_data = $this->get_parent_dashboard_data();
            $template_path = SMS_PLUGIN_DIR . 'admin/partials/parent-dashboard.php';
            
            if (file_exists($template_path)) {
                include $template_path;
            } else {
                echo '<div class="wrap"><h1>Parent Dashboard</h1><p>Dashboard template not found.</p></div>';
            }
        } catch (Exception $e) {
            echo '<div class="wrap"><h1>Parent Dashboard</h1><div class="notice notice-error"><p>Error loading dashboard: ' . esc_html($e->getMessage()) . '</p></div></div>';
        }
    }

    /**
     * Display student dashboard
     */
    public function display_student_dashboard() {
        if (!current_user_can('read')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        try {
            $dashboard_data = $this->get_student_dashboard_data();
            $template_path = SMS_PLUGIN_DIR . 'admin/partials/student-dashboard.php';

            if (file_exists($template_path)) {
                include $template_path;
            } else {
                echo '<div class="wrap"><h1>Student Dashboard</h1><p>Dashboard template not found.</p></div>';
            }
        } catch (Exception $e) {
            echo '<div class="wrap"><h1>Student Dashboard</h1><div class="notice notice-error"><p>Error loading dashboard: ' . esc_html($e->getMessage()) . '</p></div></div>';
        }
    }

    /**
     * Get administrator dashboard data
     */
    private function get_admin_dashboard_data() {
        global $wpdb;

        $data = array();

        // System overview statistics
        $data['system_stats'] = array(
            'total_students' => $this->get_post_count('cpt_students'),
            'total_teachers' => $this->get_user_count_by_role('sms_teacher'),
            'total_parents' => $this->get_user_count_by_role('sms_parent'),
            'total_classes' => $this->get_post_count('cpt_classes'),
            'active_invoices' => $this->get_active_invoices_count(),
            'pending_payments' => $this->get_pending_payments_amount(),
            'sms_sent_today' => $this->get_sms_sent_today(),
            'system_uptime' => '99.9%' // This would come from monitoring
        );

        // Financial analytics
        $data['financial_stats'] = array(
            'total_revenue_month' => $this->get_monthly_revenue(),
            'outstanding_fees' => $this->get_outstanding_fees(),
            'payment_success_rate' => $this->get_payment_success_rate(),
            'top_payment_method' => $this->get_top_payment_method()
        );

        // Recent activities
        $data['recent_activities'] = $this->get_recent_system_activities(10);

        // Payment gateway status
        $data['gateway_status'] = $this->get_payment_gateway_status();

        return $data;
    }

    /**
     * Get teacher dashboard data
     */
    private function get_teacher_dashboard_data() {
        $current_user_id = get_current_user_id();
        $data = array();

        // Get teacher's assigned classes
        $data['assigned_classes'] = $this->get_teacher_classes($current_user_id);

        // Get class statistics
        $data['class_stats'] = array();
        foreach ($data['assigned_classes'] as $class) {
            $class_id = $class->ID;
            $data['class_stats'][$class_id] = array(
                'total_students' => $this->get_class_student_count($class_id),
                'present_today' => $this->get_class_attendance_today($class_id, 'present'),
                'absent_today' => $this->get_class_attendance_today($class_id, 'absent'),
                'attendance_rate' => $this->get_class_attendance_rate($class_id, 'this_month')
            );
        }

        // Today's schedule
        $data['todays_schedule'] = $this->get_teacher_schedule_today($current_user_id);

        // Recent attendance activities
        $data['recent_attendance'] = $this->get_teacher_recent_attendance($current_user_id, 5);

        // Pending tasks
        $data['pending_tasks'] = $this->get_teacher_pending_tasks($current_user_id);

        return $data;
    }

    /**
     * Get parent dashboard data
     */
    private function get_parent_dashboard_data() {
        $current_user_id = get_current_user_id();
        $data = array();

        // Get parent's children
        $data['children'] = $this->get_parent_children($current_user_id);

        // Get children data
        $data['children_data'] = array();
        foreach ($data['children'] as $child) {
            $student_id = $child->ID;
            $data['children_data'][$student_id] = array(
                'attendance_rate' => $this->get_student_attendance_rate($student_id, 'this_month'),
                'recent_attendance' => $this->get_student_recent_attendance($student_id, 7),
                'outstanding_fees' => $this->get_student_outstanding_fees($student_id),
                'recent_payments' => $this->get_student_recent_payments($student_id, 3),
                'upcoming_events' => $this->get_student_upcoming_events($student_id),
                'recent_notices' => $this->get_student_recent_notices($student_id, 5)
            );
        }

        // Payment options
        $data['payment_options'] = $this->get_available_payment_methods();

        return $data;
    }

    /**
     * Get student dashboard data
     */
    private function get_student_dashboard_data() {
        $current_user_id = get_current_user_id();
        $student = $this->get_student_by_user_id($current_user_id);

        if (!$student) {
            return array(
                'student' => null,
                'attendance_rate' => 0,
                'recent_notices' => array(),
                'upcoming_events' => array()
            );
        }

        return array(
            'student' => $student,
            'attendance_rate' => $this->get_student_attendance_rate($student->ID, 'this_month'),
            'recent_notices' => $this->get_student_recent_notices($student->ID, 5),
            'upcoming_events' => $this->get_student_upcoming_events($student->ID)
        );
    }

    /**
     * AJAX handler for dashboard data
     */
    public function get_dashboard_data() {
        check_ajax_referer('sms_admin_nonce', 'nonce');

        $dashboard_type = sanitize_key(wp_unslash($_POST['dashboard_type'] ?? ''));
        $data = array();

        switch ($dashboard_type) {
            case 'admin':
                if (!current_user_can('manage_options') && !current_user_can('manage_system_settings')) {
                    wp_send_json_error(array('message' => __('Insufficient permissions.', 'school-management-system')), 403);
                }
                $data = $this->get_admin_dashboard_data();
                break;
            case 'teacher':
                if (!current_user_can('read')) {
                    wp_send_json_error(array('message' => __('Insufficient permissions.', 'school-management-system')), 403);
                }
                $data = $this->get_teacher_dashboard_data();
                break;
            case 'parent':
                if (!current_user_can('read')) {
                    wp_send_json_error(array('message' => __('Insufficient permissions.', 'school-management-system')), 403);
                }
                $data = $this->get_parent_dashboard_data();
                break;
            case 'student':
                if (!current_user_can('read')) {
                    wp_send_json_error(array('message' => __('Insufficient permissions.', 'school-management-system')), 403);
                }
                $data = $this->get_student_dashboard_data();
                break;
            default:
                wp_send_json_error(array('message' => __('Invalid dashboard type.', 'school-management-system')), 400);
        }

        wp_send_json_success($data);
    }

    /**
     * AJAX handler for recent activity
     */
    public function get_recent_activity() {
        check_ajax_referer('sms_admin_nonce', 'nonce');

        if (!current_user_can('manage_options') && !current_user_can('manage_system_settings')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'school-management-system')), 403);
        }

        $activities = $this->get_recent_system_activities(10);
        $html = '';

        if (!empty($activities)) {
            $html .= '<ul class="sms-activity-list">';
            foreach ($activities as $activity) {
                $html .= '<li class="sms-activity-item">';
                $html .= '<div class="activity-content">';
                $html .= '<strong>' . esc_html($activity['user_name']) . '</strong> ';
                $html .= esc_html($activity['action_description']);
                $html .= '</div>';
                $html .= '<div class="activity-time">' . esc_html($activity['time_ago']) . '</div>';
                $html .= '</li>';
            }
            $html .= '</ul>';
        } else {
            $html = '<p>' . __('No recent activity found.', 'school-management-system') . '</p>';
        }

        wp_send_json_success(array('html' => $html));
    }

    // Helper methods for data retrieval

    private function get_post_count($post_type) {
        // Check if post type exists
        if (!post_type_exists($post_type)) {
            return 0;
        }
        
        $count = wp_count_posts($post_type);
        return isset($count->publish) ? intval($count->publish) : 0;
    }

    private function get_user_count_by_role($role) {
        // Check if role exists
        if (!get_role($role)) {
            return 0;
        }
        
        $users = get_users(array('role' => $role));
        return is_array($users) ? count($users) : 0;
    }

    private function get_active_invoices_count() {
        global $wpdb;
        return $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->posts} 
            WHERE post_type = 'cpt_invoices' 
            AND post_status = 'publish'
        ");
    }

    private function get_pending_payments_amount() {
        global $wpdb;
        $amount = $wpdb->get_var("
            SELECT SUM(meta_value) 
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE p.post_type = 'cpt_invoices'
            AND p.post_status = 'publish'
            AND pm.meta_key = 'invoice_amount'
            AND NOT EXISTS (
                SELECT 1 FROM {$wpdb->postmeta} pm2 
                WHERE pm2.post_id = p.ID 
                AND pm2.meta_key = 'payment_status' 
                AND pm2.meta_value = 'paid'
            )
        ");
        return $amount ? floatval($amount) : 0;
    }

    private function get_sms_sent_today() {
        global $wpdb;
        
        // Check if table exists first
        $table_name = $wpdb->prefix . 'sms_messages';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") != $table_name) {
            return 0; // Return 0 if table doesn't exist
        }
        
        $result = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$table_name} 
            WHERE DATE(sent_at) = %s
        ", date('Y-m-d')));
        
        return $result ? intval($result) : 0;
    }

    private function get_monthly_revenue() {
        global $wpdb;
        $amount = $wpdb->get_var($wpdb->prepare("
            SELECT SUM(pm.meta_value) 
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE p.post_type = 'cpt_transactions'
            AND p.post_status = 'publish'
            AND pm.meta_key = 'transaction_amount'
            AND MONTH(p.post_date) = %d
            AND YEAR(p.post_date) = %d
        ", date('n'), date('Y')));
        return $amount ? floatval($amount) : 0;
    }

    private function get_outstanding_fees() {
        return $this->get_pending_payments_amount();
    }

    private function get_payment_success_rate() {
        global $wpdb;
        $total = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->posts} 
            WHERE post_type = 'cpt_transactions'
        ");
        
        $successful = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'cpt_transactions'
            AND pm.meta_key = 'transaction_status'
            AND pm.meta_value = 'completed'
        ");
        
        return $total > 0 ? round(($successful / $total) * 100, 1) : 0;
    }

    private function get_top_payment_method() {
        global $wpdb;
        $method = $wpdb->get_var("
            SELECT pm.meta_value
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE p.post_type = 'cpt_transactions'
            AND pm.meta_key = 'payment_method'
            GROUP BY pm.meta_value
            ORDER BY COUNT(*) DESC
            LIMIT 1
        ");
        return $method ? ucfirst(str_replace('_', ' ', $method)) : 'N/A';
    }

    private function get_recent_system_activities($limit = 10) {
        global $wpdb;
        
        // Check if activity log table exists
        $table_name = $wpdb->prefix . 'sms_activity_log';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") != $table_name) {
            // Return sample activities if table doesn't exist
            return array(
                array(
                    'user_name' => 'System',
                    'action_description' => 'System initialized',
                    'time_ago' => '1 hour ago'
                ),
                array(
                    'user_name' => 'Admin',
                    'action_description' => 'Accessed dashboard',
                    'time_ago' => '2 hours ago'
                )
            );
        }
        
        $activities = $wpdb->get_results($wpdb->prepare("
            SELECT al.*, u.display_name as user_name
            FROM {$table_name} al
            LEFT JOIN {$wpdb->users} u ON al.user_id = u.ID
            ORDER BY al.created_at DESC
            LIMIT %d
        ", $limit));

        $formatted_activities = array();
        foreach ($activities as $activity) {
            $formatted_activities[] = array(
                'user_name' => $activity->user_name ?: 'System',
                'action_description' => $this->format_activity_description($activity),
                'time_ago' => human_time_diff(strtotime($activity->created_at), current_time('timestamp')) . ' ago'
            );
        }

        return $formatted_activities;
    }

    private function format_activity_description($activity) {
        $descriptions = array(
            'student_created' => 'created a new student record',
            'attendance_marked' => 'marked attendance for a class',
            'payment_processed' => 'processed a payment',
            'sms_sent' => 'sent an SMS message',
            'notice_created' => 'created a new notice',
            'class_created' => 'created a new class'
        );

        return isset($descriptions[$activity->action]) ? $descriptions[$activity->action] : $activity->action;
    }

    private function get_payment_gateway_status() {
        $gateways = array(
            'mpesa' => array(
                'name' => 'M-Pesa',
                'status' => get_option('sms_mpesa_enabled', false) ? 'active' : 'inactive',
                'last_transaction' => $this->get_last_gateway_transaction('mpesa')
            ),
            'airtel_money' => array(
                'name' => 'Airtel Money',
                'status' => get_option('sms_airtel_money_enabled', false) ? 'active' : 'inactive',
                'last_transaction' => $this->get_last_gateway_transaction('airtel_money')
            )
        );

        return $gateways;
    }

    private function get_last_gateway_transaction($gateway) {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare("
            SELECT p.post_date
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'cpt_transactions'
            AND pm.meta_key = 'payment_method'
            AND pm.meta_value = %s
            ORDER BY p.post_date DESC
            LIMIT 1
        ", $gateway));
    }

    private function get_teacher_classes($teacher_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare("
            SELECT p.*
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'cpt_classes'
            AND p.post_status = 'publish'
            AND pm.meta_key = 'teacher_id'
            AND pm.meta_value = %d
        ", $teacher_id));
    }

    private function get_class_student_count($class_id) {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$wpdb->postmeta}
            WHERE meta_key = 'current_class'
            AND meta_value = %d
        ", $class_id));
    }

    private function get_class_attendance_today($class_id, $status) {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id
            INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id
            WHERE p.post_type = 'cpt_attendance'
            AND pm1.meta_key = 'class_id'
            AND pm1.meta_value = %d
            AND pm2.meta_key = 'date'
            AND pm2.meta_value = %s
            AND p.post_content LIKE %s
        ", $class_id, date('Y-m-d'), '%"' . $status . '"%'));
    }

    private function get_class_attendance_rate($class_id, $period) {
        // Implementation for attendance rate calculation
        return 85.5; // Placeholder
    }

    private function get_teacher_schedule_today($teacher_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare("
            SELECT p.*, pm.meta_value as schedule_data
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'cpt_timetables'
            AND pm.meta_key = 'teacher_id'
            AND pm.meta_value = %d
        ", $teacher_id));
    }

    private function get_teacher_recent_attendance($teacher_id, $limit) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare("
            SELECT p.*, pm.meta_value as class_id
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'cpt_attendance'
            AND p.post_author = %d
            ORDER BY p.post_date DESC
            LIMIT %d
        ", $teacher_id, $limit));
    }

    private function get_teacher_pending_tasks($teacher_id) {
        // Implementation for pending tasks
        return array();
    }

    private function get_student_by_user_id($user_id) {
        $students = get_posts([
            'post_type' => 'cpt_students',
            'meta_query' => [
                [
                    'key' => 'user_id',
                    'value' => $user_id,
                    'compare' => '='
                ]
            ],
            'posts_per_page' => 1
        ]);

        return !empty($students) ? $students[0] : null;
    }

    private function get_parent_children($parent_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare("
            SELECT p.*
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'cpt_students'
            AND p.post_status = 'publish'
            AND pm.meta_key = 'parent_user_id'
            AND pm.meta_value = %d
        ", $parent_id));
    }

    private function get_student_attendance_rate($student_id, $period) {
        // Implementation for student attendance rate
        return 92.3; // Placeholder
    }

    private function get_student_recent_attendance($student_id, $days) {
        // Implementation for recent attendance
        return array();
    }

    private function get_student_outstanding_fees($student_id) {
        global $wpdb;
        $amount = $wpdb->get_var($wpdb->prepare("
            SELECT SUM(pm.meta_value)
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'cpt_invoices'
            AND pm.meta_key = 'student_id'
            AND pm.meta_value = %d
            AND NOT EXISTS (
                SELECT 1 FROM {$wpdb->postmeta} pm2
                WHERE pm2.post_id = p.ID
                AND pm2.meta_key = 'payment_status'
                AND pm2.meta_value = 'paid'
            )
        ", $student_id));
        return $amount ? floatval($amount) : 0;
    }

    private function get_student_recent_payments($student_id, $limit) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare("
            SELECT p.*, pm.meta_value as amount
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'cpt_transactions'
            AND pm.meta_key = 'transaction_student_id'
            AND pm.meta_value = %d
            ORDER BY p.post_date DESC
            LIMIT %d
        ", $student_id, $limit));
    }

    private function get_student_upcoming_events($student_id) {
        // Implementation for upcoming events
        return array();
    }

    private function get_student_recent_notices($student_id, $limit) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare("
            SELECT p.*
            FROM {$wpdb->posts} p
            WHERE p.post_type = 'cpt_notices'
            AND p.post_status = 'publish'
            ORDER BY p.post_date DESC
            LIMIT %d
        ", $limit));
    }

    private function get_available_payment_methods() {
        $methods = array();
        
        if (get_option('sms_mpesa_enabled', false)) {
            $methods['mpesa'] = array(
                'name' => 'M-Pesa',
                'icon' => 'mpesa-icon.png',
                'description' => 'Pay using M-Pesa mobile money'
            );
        }
        
        if (get_option('sms_airtel_money_enabled', false)) {
            $methods['airtel_money'] = array(
                'name' => 'Airtel Money',
                'icon' => 'airtel-icon.png',
                'description' => 'Pay using Airtel Money'
            );
        }
        
        return $methods;
    }
}