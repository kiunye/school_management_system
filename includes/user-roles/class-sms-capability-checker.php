<?php
/**
 * Capability checking utility for SMS-specific permissions.
 *
 * @package    School_Management_System
 * @subpackage School_Management_System/includes/user-roles
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Capability checking utility class.
 */
class SMS_Capability_Checker {

    /**
     * Check if current user can manage students.
     */
    public static function can_manage_students($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        return user_can($user_id, 'manage_students');
    }

    /**
     * Check if current user can view specific student.
     */
    public static function can_view_student($student_id, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        return apply_filters('sms_user_can_view_student', false, $user_id, $student_id);
    }

    /**
     * Check if current user can edit specific student.
     */
    public static function can_edit_student($student_id, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        return apply_filters('sms_user_can_edit_student', false, $user_id, $student_id);
    }

    /**
     * Check if current user can manage classes.
     */
    public static function can_manage_classes($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        return user_can($user_id, 'manage_classes');
    }

    /**
     * Check if current user can manage specific class.
     */
    public static function can_manage_class($class_id, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        return user_can($user_id, 'manage_class_students', $class_id);
    }

    /**
     * Check if current user can manage fees.
     */
    public static function can_manage_fees($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        return user_can($user_id, 'manage_fees');
    }

    /**
     * Check if current user can view financial data.
     */
    public static function can_view_financial_data($student_id = null, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        return apply_filters('sms_user_can_view_financial_data', false, $user_id, $student_id);
    }

    /**
     * Check if current user can process payments.
     */
    public static function can_process_payments($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        return user_can($user_id, 'process_payments');
    }

    /**
     * Check if current user can send SMS.
     */
    public static function can_send_sms($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        return user_can($user_id, 'send_bulk_sms');
    }

    /**
     * Check if current user can manage attendance.
     */
    public static function can_manage_attendance($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        return user_can($user_id, 'manage_attendance');
    }

    /**
     * Check if current user can mark attendance for specific class.
     */
    public static function can_mark_attendance($class_id, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        // School administrators can mark attendance for any class
        if (user_can($user_id, 'manage_attendance')) {
            return true;
        }
        
        // Teachers can mark attendance for their assigned classes
        if (user_can($user_id, 'mark_attendance')) {
            $role_manager = new SMS_Role_Manager();
            return $role_manager->is_teacher_assigned_to_class($user_id, $class_id);
        }
        
        return false;
    }

    /**
     * Check if current user can manage timetables.
     */
    public static function can_manage_timetables($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        return user_can($user_id, 'manage_timetables');
    }

    /**
     * Check if current user can manage notices.
     */
    public static function can_manage_notices($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        return user_can($user_id, 'manage_notices') || user_can($user_id, 'create_academic_notices');
    }

    /**
     * Check if current user can manage transport.
     */
    public static function can_manage_transport($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        return user_can($user_id, 'manage_transport');
    }

    /**
     * Check if current user can view reports.
     */
    public static function can_view_reports($report_type = 'all', $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        switch ($report_type) {
            case 'financial':
                return user_can($user_id, 'view_financial_data') || user_can($user_id, 'manage_financial_reports');
                
            case 'academic':
                return user_can($user_id, 'view_class_reports') || user_can($user_id, 'view_all_reports');
                
            case 'all':
            default:
                return user_can($user_id, 'view_all_reports');
        }
    }

    /**
     * Check if current user can export data.
     */
    public static function can_export_data($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        return user_can($user_id, 'export_data');
    }

    /**
     * Check if current user can import data.
     */
    public static function can_import_data($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        return user_can($user_id, 'import_data');
    }

    /**
     * Check if current user can manage system settings.
     */
    public static function can_manage_system_settings($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        return user_can($user_id, 'manage_system_settings');
    }

    /**
     * Get user's role-based menu items.
     */
    public static function get_user_menu_items($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $menu_items = [];
        
        // Students
        if (self::can_view_student(null, $user_id)) {
            $menu_items['students'] = [
                'title' => __('Students', 'school-management-system'),
                'capability' => 'manage_students',
                'icon' => 'dashicons-groups'
            ];
        }
        
        // Classes
        if (self::can_manage_classes($user_id)) {
            $menu_items['classes'] = [
                'title' => __('Classes', 'school-management-system'),
                'capability' => 'manage_classes',
                'icon' => 'dashicons-welcome-learn-more'
            ];
        }
        
        // Attendance
        if (self::can_manage_attendance($user_id)) {
            $menu_items['attendance'] = [
                'title' => __('Attendance', 'school-management-system'),
                'capability' => 'manage_attendance',
                'icon' => 'dashicons-yes-alt'
            ];
        }
        
        // Timetables
        if (self::can_manage_timetables($user_id)) {
            $menu_items['timetables'] = [
                'title' => __('Timetables', 'school-management-system'),
                'capability' => 'manage_timetables',
                'icon' => 'dashicons-calendar-alt'
            ];
        }
        
        // Fees & Finance
        if (self::can_manage_fees($user_id)) {
            $menu_items['fees'] = [
                'title' => __('Fees & Finance', 'school-management-system'),
                'capability' => 'manage_fees',
                'icon' => 'dashicons-money-alt'
            ];
        }
        
        // Communication
        if (self::can_send_sms($user_id) || self::can_manage_notices($user_id)) {
            $menu_items['communication'] = [
                'title' => __('Communication', 'school-management-system'),
                'capability' => 'send_bulk_sms',
                'icon' => 'dashicons-email-alt'
            ];
        }
        
        // Transport
        if (self::can_manage_transport($user_id)) {
            $menu_items['transport'] = [
                'title' => __('Transport', 'school-management-system'),
                'capability' => 'manage_transport',
                'icon' => 'dashicons-car'
            ];
        }
        
        // Reports
        if (self::can_view_reports('all', $user_id)) {
            $menu_items['reports'] = [
                'title' => __('Reports', 'school-management-system'),
                'capability' => 'view_all_reports',
                'icon' => 'dashicons-chart-bar'
            ];
        }
        
        // Settings
        if (self::can_manage_system_settings($user_id)) {
            $menu_items['settings'] = [
                'title' => __('Settings', 'school-management-system'),
                'capability' => 'manage_system_settings',
                'icon' => 'dashicons-admin-settings'
            ];
        }
        
        return apply_filters('sms_user_menu_items', $menu_items, $user_id);
    }

    /**
     * Check multiple capabilities at once.
     */
    public static function user_can_any($capabilities, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        foreach ($capabilities as $capability) {
            if (user_can($user_id, $capability)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check if user has all specified capabilities.
     */
    public static function user_can_all($capabilities, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        foreach ($capabilities as $capability) {
            if (!user_can($user_id, $capability)) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Get user's effective capabilities.
     */
    public static function get_user_capabilities($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $user = get_userdata($user_id);
        if (!$user) {
            return [];
        }
        
        return array_keys(array_filter($user->allcaps));
    }

    /**
     * Check if user has SMS role.
     */
    public static function has_sms_role($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $user_roles = new SMS_User_Roles();
        return $user_roles->user_has_sms_role($user_id);
    }
}