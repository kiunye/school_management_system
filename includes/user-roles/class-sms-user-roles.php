<?php
/**
 * User role management for the plugin.
 *
 * @package    School_Management_System
 * @subpackage School_Management_System/includes/user-roles
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * User role management class.
 */
class SMS_User_Roles extends SMS_Base {

    /**
     * Initialize user roles.
     */
    public function __construct() {
        parent::__construct();
        add_action('init', array($this, 'maybe_update_roles'));
    }

    /**
     * Maybe update roles if version changed.
     */
    public function maybe_update_roles() {
        $current_version = get_option('sms_roles_version', '0.0.0');
        
        if (version_compare($current_version, SMS_VERSION, '<')) {
            $this->update_user_roles();
            update_option('sms_roles_version', SMS_VERSION);
        }
    }

    /**
     * Update user roles and capabilities.
     */
    public function update_user_roles() {
        // Remove existing custom roles first
        $this->remove_custom_roles();
        
        // Create new roles
        $this->create_school_administrator_role();
        $this->create_teacher_role();
        $this->create_parent_role();
        $this->create_student_role();
        
        // Update administrator capabilities
        $this->update_administrator_capabilities();
    }

    /**
     * Remove existing custom roles.
     */
    private function remove_custom_roles() {
        $custom_roles = array('school_administrator', 'teacher', 'parent', 'student');
        
        foreach ($custom_roles as $role) {
            remove_role($role);
        }
    }

    /**
     * Create school administrator role.
     */
    private function create_school_administrator_role() {
        add_role('school_administrator', __('School Administrator', 'school-management-system'), array(
            // WordPress core capabilities
            'read' => true,
            'edit_posts' => true,
            'edit_others_posts' => true,
            'publish_posts' => true,
            'manage_categories' => true,
            'upload_files' => true,
            'edit_pages' => true,
            'edit_others_pages' => true,
            'publish_pages' => true,
            
            // Custom SMS capabilities
            'manage_students' => true,
            'manage_classes' => true,
            'manage_fees' => true,
            'manage_financial_reports' => true,
            'send_bulk_sms' => true,
            'manage_system_settings' => true,
            'manage_transport' => true,
            'manage_notices' => true,
            'view_all_reports' => true,
            'manage_attendance' => true,
            'manage_timetables' => true,
            'process_payments' => true,
            'manage_invoices' => true,
            'view_financial_data' => true,
            'manage_communication' => true,
            'export_data' => true,
            'import_data' => true
        ));
    }

    /**
     * Create teacher role.
     */
    private function create_teacher_role() {
        add_role('teacher', __('Teacher', 'school-management-system'), array(
            // WordPress core capabilities
            'read' => true,
            'edit_posts' => true,
            'publish_posts' => true,
            'upload_files' => true,
            
            // Custom SMS capabilities
            'edit_assigned_classes' => true,
            'mark_attendance' => true,
            'create_lessons' => true,
            'view_student_records' => true,
            'create_academic_notices' => true,
            'view_class_reports' => true,
            'manage_class_timetable' => true,
            'view_class_fees' => true,
            'communicate_with_parents' => true,
            'view_assigned_students' => true,
            'create_assignments' => true,
            'grade_assignments' => true
        ));
    }

    /**
     * Create parent role.
     */
    private function create_parent_role() {
        add_role('parent', __('Parent', 'school-management-system'), array(
            // WordPress core capabilities
            'read' => true,
            
            // Custom SMS capabilities
            'view_child_records' => true,
            'view_child_fees' => true,
            'make_payments' => true,
            'update_contact_info' => true,
            'view_child_attendance' => true,
            'view_notices' => true,
            'view_child_timetable' => true,
            'view_child_assignments' => true,
            'communicate_with_teachers' => true,
            'view_payment_history' => true,
            'download_receipts' => true,
            'view_child_transport' => true
        ));
    }

    /**
     * Create student role.
     */
    private function create_student_role() {
        add_role('student', __('Student', 'school-management-system'), array(
            // WordPress core capabilities
            'read' => true,
            
            // Custom SMS capabilities
            'view_own_records' => true,
            'view_timetable' => true,
            'view_notices' => true,
            'view_assignments' => true,
            'submit_assignments' => true,
            'view_own_attendance' => true,
            'view_own_grades' => true,
            'update_own_profile' => true,
            'view_class_notices' => true
        ));
    }

    /**
     * Update administrator capabilities.
     */
    private function update_administrator_capabilities() {
        $admin_role = get_role('administrator');
        
        if ($admin_role) {
            $admin_capabilities = array(
                'manage_students', 'manage_classes', 'manage_fees',
                'manage_financial_reports', 'send_bulk_sms', 'manage_system_settings',
                'manage_transport', 'manage_notices', 'view_all_reports',
                'manage_attendance', 'manage_timetables', 'process_payments',
                'manage_invoices', 'view_financial_data', 'manage_communication',
                'export_data', 'import_data'
            );
            
            foreach ($admin_capabilities as $cap) {
                $admin_role->add_cap($cap);
            }
        }
    }

    /**
     * Get user role display name.
     */
    public function get_role_display_name($role) {
        $role_names = array(
            'school_administrator' => __('School Administrator', 'school-management-system'),
            'teacher' => __('Teacher', 'school-management-system'),
            'parent' => __('Parent', 'school-management-system'),
            'student' => __('Student', 'school-management-system')
        );
        
        return isset($role_names[$role]) ? $role_names[$role] : ucfirst($role);
    }

    /**
     * Check if user has SMS role.
     */
    public function user_has_sms_role($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }
        
        $sms_roles = array('school_administrator', 'teacher', 'parent', 'student');
        $user_roles = $user->roles;
        
        return !empty(array_intersect($sms_roles, $user_roles));
    }

    /**
     * Get users by SMS role.
     */
    public function get_users_by_role($role, $args = array()) {
        $default_args = array(
            'role' => $role,
            'orderby' => 'display_name',
            'order' => 'ASC'
        );
        
        $args = wp_parse_args($args, $default_args);
        
        return get_users($args);
    }

    /**
     * Assign user to SMS role.
     */
    public function assign_user_role($user_id, $role) {
        $user = get_userdata($user_id);
        if (!$user) {
            return $this->handle_error('invalid_user', 'Invalid user ID');
        }
        
        $valid_roles = array('school_administrator', 'teacher', 'parent', 'student');
        if (!in_array($role, $valid_roles)) {
            return $this->handle_error('invalid_role', 'Invalid role specified');
        }
        
        // Remove existing SMS roles
        foreach ($valid_roles as $existing_role) {
            $user->remove_role($existing_role);
        }
        
        // Add new role
        $user->add_role($role);
        
        $this->log("User {$user_id} assigned to role {$role}", 'info', array(
            'user_id' => $user_id,
            'role' => $role,
            'assigned_by' => get_current_user_id()
        ));
        
        return $this->handle_success(array('user_id' => $user_id, 'role' => $role), 'Role assigned successfully');
    }
}