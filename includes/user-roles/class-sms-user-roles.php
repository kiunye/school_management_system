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
     * Available SMS roles.
     */
    const SMS_ROLES = [
        'sms_admin' => 'School Administrator',
        'sms_teacher' => 'Teacher', 
        'sms_parent' => 'Parent',
        'sms_student' => 'Student'
    ];

    /**
     * Initialize user roles.
     */
    public function __construct() {
        parent::__construct();
        add_action('init', [$this, 'maybe_update_roles']);
        add_action('user_register', [$this, 'assign_default_role'], 10, 1);
        add_action('wp_login', [$this, 'check_user_role_access'], 10, 2);
        add_filter('editable_roles', [$this, 'filter_editable_roles']);
        add_action('admin_init', [$this, 'restrict_admin_access']);
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
        $this->create_sms_admin_role();
        $this->create_sms_teacher_role();
        $this->create_sms_parent_role();
        $this->create_sms_student_role();
        
        // Update administrator capabilities
        $this->update_administrator_capabilities();
    }

    /**
     * Remove existing custom roles.
     */
    private function remove_custom_roles() {
        $custom_roles = array('sms_admin', 'sms_teacher', 'sms_parent', 'sms_student', 'school_administrator', 'teacher', 'parent', 'student');
        
        foreach ($custom_roles as $role) {
            remove_role($role);
        }
    }

    /**
     * Create SMS administrator role.
     */
    private function create_sms_admin_role() {
        add_role('sms_admin', __('School Administrator', 'school-management-system'), array(
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
     * Create SMS teacher role.
     */
    private function create_sms_teacher_role() {
        add_role('sms_teacher', __('Teacher', 'school-management-system'), array(
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
     * Create SMS parent role.
     */
    private function create_sms_parent_role() {
        add_role('sms_parent', __('Parent', 'school-management-system'), array(
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
     * Create SMS student role.
     */
    private function create_sms_student_role() {
        add_role('sms_student', __('Student', 'school-management-system'), array(
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
                'export_data', 'import_data',
                // Teacher capabilities for admins
                'edit_assigned_classes', 'mark_attendance', 'create_lessons',
                'view_student_records', 'create_academic_notices', 'view_class_reports',
                'manage_class_timetable', 'view_class_fees', 'communicate_with_parents',
                'view_assigned_students', 'create_assignments', 'grade_assignments'
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
        
        $valid_roles = array_keys(self::SMS_ROLES);
        if (!in_array($role, $valid_roles)) {
            return $this->handle_error('invalid_role', 'Invalid role specified');
        }
        
        // Remove existing SMS roles
        foreach ($valid_roles as $existing_role) {
            $user->remove_role($existing_role);
        }
        
        // Add new role
        $user->add_role($role);
        
        // Set additional user meta for role-specific data
        $this->set_role_specific_meta($user_id, $role);
        
        $this->log("User {$user_id} assigned to role {$role}", 'info', [
            'user_id' => $user_id,
            'role' => $role,
            'assigned_by' => get_current_user_id()
        ]);
        
        // Trigger role assignment action
        do_action('sms_user_role_assigned', $user_id, $role);
        
        return $this->handle_success(['user_id' => $user_id, 'role' => $role], 'Role assigned successfully');
    }

    /**
     * Assign default role to new users.
     */
    public function assign_default_role($user_id) {
        $user = get_userdata($user_id);
        if (!$user) {
            return;
        }

        // Check if user already has an SMS role
        if ($this->user_has_sms_role($user_id)) {
            return;
        }

        // Default role assignment logic
        $default_role = apply_filters('sms_default_user_role', 'parent', $user);
        
        if (in_array($default_role, array_keys(self::SMS_ROLES))) {
            $this->assign_user_role($user_id, $default_role);
        }
    }

    /**
     * Check user role access on login.
     */
    public function check_user_role_access($user_login, $user) {
        if (!$this->user_has_sms_role($user->ID)) {
            return;
        }

        // Update last login time
        update_user_meta($user->ID, 'sms_last_login', current_time('mysql'));
        
        // Log user login
        $this->log("User {$user->ID} ({$user_login}) logged in", 'info', [
            'user_id' => $user->ID,
            'user_login' => $user_login,
            'login_time' => current_time('mysql')
        ]);
    }

    /**
     * Filter editable roles to show only SMS roles to SMS users.
     */
    public function filter_editable_roles($roles) {
        $current_user = wp_get_current_user();
        
        if ($this->user_has_sms_role($current_user->ID)) {
            // School administrators can manage all SMS roles
            if (in_array('school_administrator', $current_user->roles)) {
                $sms_roles = [];
                foreach (self::SMS_ROLES as $role_key => $role_name) {
                    if (isset($roles[$role_key])) {
                        $sms_roles[$role_key] = $roles[$role_key];
                    }
                }
                return $sms_roles;
            }
            
            // Teachers can only manage parent and student roles
            if (in_array('teacher', $current_user->roles)) {
                $allowed_roles = ['parent', 'student'];
                $filtered_roles = [];
                foreach ($allowed_roles as $role_key) {
                    if (isset($roles[$role_key])) {
                        $filtered_roles[$role_key] = $roles[$role_key];
                    }
                }
                return $filtered_roles;
            }
        }
        
        return $roles;
    }

    /**
     * Restrict admin access based on user roles.
     */
    public function restrict_admin_access() {
        $current_user = wp_get_current_user();
        
        if (!$this->user_has_sms_role($current_user->ID)) {
            return;
        }

        // Parents and students should not access admin area except for profile
        if (in_array('parent', $current_user->roles) || in_array('student', $current_user->roles)) {
            $allowed_pages = ['profile.php', 'user-edit.php', 'admin-ajax.php'];
            $current_page = basename($_SERVER['PHP_SELF']);
            
            if (!in_array($current_page, $allowed_pages) && !wp_doing_ajax()) {
                wp_redirect(home_url());
                exit;
            }
        }
    }

    /**
     * Set role-specific user meta.
     */
    private function set_role_specific_meta($user_id, $role) {
        // Set role assignment timestamp
        update_user_meta($user_id, 'sms_role_assigned_date', current_time('mysql'));
        update_user_meta($user_id, 'sms_current_role', $role);
        
        // Role-specific meta
        switch ($role) {
            case 'teacher':
                update_user_meta($user_id, 'sms_teacher_id', $this->generate_teacher_id());
                update_user_meta($user_id, 'sms_assigned_classes', []);
                break;
                
            case 'parent':
                update_user_meta($user_id, 'sms_children', []);
                update_user_meta($user_id, 'sms_notification_preferences', [
                    'sms' => true,
                    'email' => true,
                    'attendance_alerts' => true,
                    'fee_reminders' => true
                ]);
                break;
                
            case 'student':
                update_user_meta($user_id, 'sms_student_status', 'pending');
                break;
        }
    }

    /**
     * Generate unique teacher ID.
     */
    private function generate_teacher_id() {
        $year = date('Y');
        $prefix = 'TCH' . $year;
        
        // Get the highest existing teacher ID for this year
        $users = get_users([
            'role' => 'teacher',
            'meta_key' => 'sms_teacher_id',
            'meta_compare' => 'LIKE',
            'meta_value' => $prefix
        ]);
        
        $highest_number = 0;
        foreach ($users as $user) {
            $teacher_id = get_user_meta($user->ID, 'sms_teacher_id', true);
            if ($teacher_id) {
                $number = intval(substr($teacher_id, -4));
                if ($number > $highest_number) {
                    $highest_number = $number;
                }
            }
        }
        
        return $prefix . str_pad($highest_number + 1, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Get user's SMS role.
     */
    public function get_user_sms_role($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }
        
        foreach (array_keys(self::SMS_ROLES) as $role) {
            if (in_array($role, $user->roles)) {
                return $role;
            }
        }
        
        return false;
    }

    /**
     * Check if user can manage specific capability.
     */
    public function user_can_manage($capability, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        return user_can($user_id, $capability);
    }

    /**
     * Get users with specific SMS role and additional filters.
     */
    public function get_sms_users($role, $args = []) {
        $default_args = [
            'role' => $role,
            'orderby' => 'display_name',
            'order' => 'ASC',
            'fields' => 'all'
        ];
        
        $args = wp_parse_args($args, $default_args);
        
        // Add SMS-specific meta query if needed
        if (isset($args['sms_status'])) {
            $args['meta_query'] = [
                [
                    'key' => 'sms_student_status',
                    'value' => $args['sms_status'],
                    'compare' => '='
                ]
            ];
            unset($args['sms_status']);
        }
        
        return get_users($args);
    }

    /**
     * Bulk assign roles to users.
     */
    public function bulk_assign_roles($user_ids, $role) {
        if (!is_array($user_ids)) {
            return $this->handle_error('invalid_input', 'User IDs must be an array');
        }
        
        if (!in_array($role, array_keys(self::SMS_ROLES))) {
            return $this->handle_error('invalid_role', 'Invalid role specified');
        }
        
        $success_count = 0;
        $errors = [];
        
        foreach ($user_ids as $user_id) {
            $result = $this->assign_user_role($user_id, $role);
            if (is_wp_error($result)) {
                $errors[] = "User {$user_id}: " . $result->get_error_message();
            } else {
                $success_count++;
            }
        }
        
        $this->log("Bulk role assignment: {$success_count} users assigned to {$role}", 'info', [
            'role' => $role,
            'success_count' => $success_count,
            'error_count' => count($errors),
            'assigned_by' => get_current_user_id()
        ]);
        
        return $this->handle_success([
            'success_count' => $success_count,
            'errors' => $errors
        ], "Successfully assigned {$success_count} users to {$role} role");
    }

    /**
     * Get role statistics.
     */
    public function get_role_statistics() {
        $stats = [];
        
        foreach (self::SMS_ROLES as $role_key => $role_name) {
            $count = count(get_users(['role' => $role_key]));
            $stats[$role_key] = [
                'name' => $role_name,
                'count' => $count
            ];
        }
        
        return $stats;
    }
}
// Initialize the class
new SMS_User_Roles();