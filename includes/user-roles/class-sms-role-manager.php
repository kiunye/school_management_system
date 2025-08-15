<?php
/**
 * Role management utility class for advanced role operations.
 *
 * @package    School_Management_System
 * @subpackage School_Management_System/includes/user-roles
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Role management utility class.
 */
class SMS_Role_Manager extends SMS_Base {

    /**
     * Role hierarchy for permission inheritance.
     */
    const ROLE_HIERARCHY = [
        'school_administrator' => 4,
        'teacher' => 3,
        'parent' => 2,
        'student' => 1
    ];

    /**
     * Initialize the role manager.
     */
    public function __construct() {
        parent::__construct();
        add_action('init', [$this, 'setup_role_capabilities']);
        add_filter('user_has_cap', [$this, 'filter_user_capabilities'], 10, 4);
    }

    /**
     * Setup additional role capabilities based on context.
     */
    public function setup_role_capabilities() {
        // Add dynamic capabilities based on user context
        add_filter('sms_user_can_view_student', [$this, 'can_view_student'], 10, 3);
        add_filter('sms_user_can_edit_student', [$this, 'can_edit_student'], 10, 3);
        add_filter('sms_user_can_view_financial_data', [$this, 'can_view_financial_data'], 10, 3);
    }

    /**
     * Filter user capabilities for SMS-specific permissions.
     */
    public function filter_user_capabilities($allcaps, $caps, $args, $user) {
        if (!isset($args[0])) {
            return $allcaps;
        }

        $capability = $args[0];
        $user_id = $user->ID;

        // Handle SMS-specific capabilities
        switch ($capability) {
            case 'view_student_data':
                $allcaps[$capability] = $this->can_view_student_data($user_id, $args);
                break;

            case 'edit_student_data':
                $allcaps[$capability] = $this->can_edit_student_data($user_id, $args);
                break;

            case 'view_financial_reports':
                $allcaps[$capability] = $this->can_view_financial_reports($user_id);
                break;

            case 'manage_class_students':
                $allcaps[$capability] = $this->can_manage_class_students($user_id, $args);
                break;
        }

        return $allcaps;
    }

    /**
     * Check if user can view specific student data.
     */
    public function can_view_student($can_view, $user_id, $student_id) {
        $user_role = $this->get_user_primary_sms_role($user_id);

        switch ($user_role) {
            case 'school_administrator':
                return true;

            case 'teacher':
                return $this->is_student_in_teacher_classes($student_id, $user_id);

            case 'parent':
                return $this->is_user_parent_of_student($user_id, $student_id);

            case 'student':
                return $user_id == $student_id;

            default:
                return false;
        }
    }

    /**
     * Check if user can edit specific student data.
     */
    public function can_edit_student($can_edit, $user_id, $student_id) {
        $user_role = $this->get_user_primary_sms_role($user_id);

        switch ($user_role) {
            case 'school_administrator':
                return true;

            case 'teacher':
                // Teachers can edit basic info of students in their classes
                return $this->is_student_in_teacher_classes($student_id, $user_id);

            case 'parent':
                // Parents can edit limited info of their children
                return $this->is_user_parent_of_student($user_id, $student_id);

            default:
                return false;
        }
    }

    /**
     * Check if user can view financial data.
     */
    public function can_view_financial_data($can_view, $user_id, $student_id = null) {
        $user_role = $this->get_user_primary_sms_role($user_id);

        switch ($user_role) {
            case 'school_administrator':
                return true;

            case 'teacher':
                // Teachers can view class-level financial summaries
                return $student_id ? $this->is_student_in_teacher_classes($student_id, $user_id) : false;

            case 'parent':
                // Parents can view their children's financial data
                return $student_id ? $this->is_user_parent_of_student($user_id, $student_id) : false;

            default:
                return false;
        }
    }

    /**
     * Check if user can view student data (capability callback).
     */
    private function can_view_student_data($user_id, $args) {
        if (!isset($args[1])) {
            return false;
        }

        $student_id = $args[1];
        return apply_filters('sms_user_can_view_student', false, $user_id, $student_id);
    }

    /**
     * Check if user can edit student data (capability callback).
     */
    private function can_edit_student_data($user_id, $args) {
        if (!isset($args[1])) {
            return false;
        }

        $student_id = $args[1];
        return apply_filters('sms_user_can_edit_student', false, $user_id, $student_id);
    }

    /**
     * Check if user can view financial reports (capability callback).
     */
    private function can_view_financial_reports($user_id) {
        return apply_filters('sms_user_can_view_financial_data', false, $user_id);
    }

    /**
     * Check if user can manage class students (capability callback).
     */
    private function can_manage_class_students($user_id, $args) {
        $user_role = $this->get_user_primary_sms_role($user_id);

        if ($user_role === 'school_administrator') {
            return true;
        }

        if ($user_role === 'teacher' && isset($args[1])) {
            $class_id = $args[1];
            return $this->is_teacher_assigned_to_class($user_id, $class_id);
        }

        return false;
    }

    /**
     * Get user's primary SMS role.
     */
    private function get_user_primary_sms_role($user_id) {
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }

        $sms_roles = array_keys(SMS_User_Roles::SMS_ROLES);
        $user_roles = array_intersect($sms_roles, $user->roles);

        if (empty($user_roles)) {
            return false;
        }

        // Return the highest priority role
        $highest_priority = 0;
        $primary_role = false;

        foreach ($user_roles as $role) {
            $priority = self::ROLE_HIERARCHY[$role] ?? 0;
            if ($priority > $highest_priority) {
                $highest_priority = $priority;
                $primary_role = $role;
            }
        }

        return $primary_role;
    }

    /**
     * Check if student is in teacher's classes.
     */
    private function is_student_in_teacher_classes($student_id, $teacher_id) {
        // Get student's assigned class
        $student_class = get_field('assigned_class', $student_id);
        if (!$student_class) {
            return false;
        }

        // Get teacher's assigned classes
        $teacher_classes = get_user_meta($teacher_id, 'sms_assigned_classes', true);
        if (!is_array($teacher_classes)) {
            $teacher_classes = [];
        }

        // Check if student's class is in teacher's classes
        return in_array($student_class, $teacher_classes);
    }

    /**
     * Check if user is parent of student.
     */
    private function is_user_parent_of_student($parent_id, $student_id) {
        // Get parent's children
        $children = get_user_meta($parent_id, 'sms_children', true);
        if (!is_array($children)) {
            $children = [];
        }

        return in_array($student_id, $children);
    }

    /**
     * Check if teacher is assigned to class.
     */
    private function is_teacher_assigned_to_class($teacher_id, $class_id) {
        // Check if teacher is the main class teacher
        $class_teacher = get_field('class_teacher', $class_id);
        if ($class_teacher == $teacher_id) {
            return true;
        }

        // Check if teacher is an assistant teacher
        $assistant_teachers = get_field('assistant_teachers', $class_id);
        if (is_array($assistant_teachers) && in_array($teacher_id, $assistant_teachers)) {
            return true;
        }

        // Check subject assignments
        $subject_assignments = get_field('subject_assignments', $class_id);
        if (is_array($subject_assignments)) {
            foreach ($subject_assignments as $assignment) {
                if (isset($assignment['subject_teacher']) && $assignment['subject_teacher'] == $teacher_id) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Assign teacher to class.
     */
    public function assign_teacher_to_class($teacher_id, $class_id, $role = 'assistant') {
        if (!user_can($teacher_id, 'edit_assigned_classes')) {
            return $this->handle_error('insufficient_permissions', 'User does not have teacher permissions');
        }

        // Get current assigned classes for teacher
        $assigned_classes = get_user_meta($teacher_id, 'sms_assigned_classes', true);
        if (!is_array($assigned_classes)) {
            $assigned_classes = [];
        }

        // Add class to teacher's assignments
        if (!in_array($class_id, $assigned_classes)) {
            $assigned_classes[] = $class_id;
            update_user_meta($teacher_id, 'sms_assigned_classes', $assigned_classes);
        }

        // Update class with teacher assignment
        if ($role === 'main') {
            update_field('class_teacher', $teacher_id, $class_id);
        } else {
            $assistant_teachers = get_field('assistant_teachers', $class_id);
            if (!is_array($assistant_teachers)) {
                $assistant_teachers = [];
            }
            if (!in_array($teacher_id, $assistant_teachers)) {
                $assistant_teachers[] = $teacher_id;
                update_field('assistant_teachers', $assistant_teachers, $class_id);
            }
        }

        $this->log("Teacher {$teacher_id} assigned to class {$class_id} as {$role}", 'info', [
            'teacher_id' => $teacher_id,
            'class_id' => $class_id,
            'role' => $role,
            'assigned_by' => get_current_user_id()
        ]);

        do_action('sms_teacher_assigned_to_class', $teacher_id, $class_id, $role);

        return $this->handle_success([
            'teacher_id' => $teacher_id,
            'class_id' => $class_id,
            'role' => $role
        ], 'Teacher successfully assigned to class');
    }

    /**
     * Link parent to student.
     */
    public function link_parent_to_student($parent_id, $student_id) {
        if (!user_can($parent_id, 'view_child_records')) {
            return $this->handle_error('insufficient_permissions', 'User does not have parent permissions');
        }

        // Get parent's current children
        $children = get_user_meta($parent_id, 'sms_children', true);
        if (!is_array($children)) {
            $children = [];
        }

        // Add student to parent's children
        if (!in_array($student_id, $children)) {
            $children[] = $student_id;
            update_user_meta($parent_id, 'sms_children', $children);
        }

        // Update student's parent information
        $parent_details = get_field('parent_details', $student_id);
        if (!is_array($parent_details)) {
            $parent_details = [];
        }

        // Check if parent is already linked
        $parent_linked = false;
        foreach ($parent_details as &$parent_detail) {
            if (isset($parent_detail['parent_user_id']) && $parent_detail['parent_user_id'] == $parent_id) {
                $parent_linked = true;
                break;
            }
        }

        // If not linked, add user ID to first matching parent record
        if (!$parent_linked && !empty($parent_details)) {
            $parent_user = get_userdata($parent_id);
            foreach ($parent_details as &$parent_detail) {
                if (isset($parent_detail['parent_email']) && 
                    $parent_detail['parent_email'] === $parent_user->user_email) {
                    $parent_detail['parent_user_id'] = $parent_id;
                    $parent_linked = true;
                    break;
                }
            }
            
            if ($parent_linked) {
                update_field('parent_details', $parent_details, $student_id);
            }
        }

        $this->log("Parent {$parent_id} linked to student {$student_id}", 'info', [
            'parent_id' => $parent_id,
            'student_id' => $student_id,
            'linked_by' => get_current_user_id()
        ]);

        do_action('sms_parent_linked_to_student', $parent_id, $student_id);

        return $this->handle_success([
            'parent_id' => $parent_id,
            'student_id' => $student_id
        ], 'Parent successfully linked to student');
    }

    /**
     * Get user's accessible students.
     */
    public function get_user_accessible_students($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        $user_role = $this->get_user_primary_sms_role($user_id);
        $students = [];

        switch ($user_role) {
            case 'school_administrator':
                // Administrators can access all students
                $students = get_posts([
                    'post_type' => 'sms_students',
                    'posts_per_page' => -1,
                    'post_status' => 'publish'
                ]);
                break;

            case 'teacher':
                // Teachers can access students in their classes
                $assigned_classes = get_user_meta($user_id, 'sms_assigned_classes', true);
                if (is_array($assigned_classes) && !empty($assigned_classes)) {
                    $students = get_posts([
                        'post_type' => 'sms_students',
                        'posts_per_page' => -1,
                        'post_status' => 'publish',
                        'meta_query' => [
                            [
                                'key' => 'assigned_class',
                                'value' => $assigned_classes,
                                'compare' => 'IN'
                            ]
                        ]
                    ]);
                }
                break;

            case 'parent':
                // Parents can access their children
                $children = get_user_meta($user_id, 'sms_children', true);
                if (is_array($children) && !empty($children)) {
                    $students = get_posts([
                        'post_type' => 'sms_students',
                        'posts_per_page' => -1,
                        'post_status' => 'publish',
                        'post__in' => $children
                    ]);
                }
                break;

            case 'student':
                // Students can only access their own record
                $student_post = get_posts([
                    'post_type' => 'sms_students',
                    'posts_per_page' => 1,
                    'post_status' => 'publish',
                    'meta_query' => [
                        [
                            'key' => 'student_user_id',
                            'value' => $user_id,
                            'compare' => '='
                        ]
                    ]
                ]);
                $students = $student_post;
                break;
        }

        return $students;
    }

    /**
     * Get role-based dashboard data.
     */
    public function get_dashboard_data($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        $user_role = $this->get_user_primary_sms_role($user_id);
        $dashboard_data = [];

        switch ($user_role) {
            case 'school_administrator':
                $dashboard_data = $this->get_admin_dashboard_data();
                break;

            case 'teacher':
                $dashboard_data = $this->get_teacher_dashboard_data($user_id);
                break;

            case 'parent':
                $dashboard_data = $this->get_parent_dashboard_data($user_id);
                break;

            case 'student':
                $dashboard_data = $this->get_student_dashboard_data($user_id);
                break;
        }

        return apply_filters('sms_dashboard_data', $dashboard_data, $user_role, $user_id);
    }

    /**
     * Get admin dashboard data.
     */
    private function get_admin_dashboard_data() {
        return [
            'total_students' => wp_count_posts('sms_students')->publish,
            'total_teachers' => count(get_users(['role' => 'teacher'])),
            'total_parents' => count(get_users(['role' => 'parent'])),
            'total_classes' => wp_count_posts('sms_classes')->publish,
            'pending_payments' => $this->get_pending_payments_count(),
            'recent_enrollments' => $this->get_recent_enrollments(5)
        ];
    }

    /**
     * Get teacher dashboard data.
     */
    private function get_teacher_dashboard_data($teacher_id) {
        $assigned_classes = get_user_meta($teacher_id, 'sms_assigned_classes', true);
        $students = $this->get_user_accessible_students($teacher_id);

        return [
            'assigned_classes' => count($assigned_classes ?: []),
            'total_students' => count($students),
            'todays_attendance' => $this->get_teacher_attendance_summary($teacher_id),
            'upcoming_lessons' => $this->get_upcoming_lessons($teacher_id)
        ];
    }

    /**
     * Get parent dashboard data.
     */
    private function get_parent_dashboard_data($parent_id) {
        $children = get_user_meta($parent_id, 'sms_children', true);
        
        return [
            'children_count' => count($children ?: []),
            'pending_fees' => $this->get_parent_pending_fees($parent_id),
            'recent_notices' => $this->get_recent_notices_for_parent($parent_id),
            'attendance_summary' => $this->get_children_attendance_summary($parent_id)
        ];
    }

    /**
     * Get student dashboard data.
     */
    private function get_student_dashboard_data($user_id) {
        $student_posts = get_posts([
            'post_type' => 'sms_students',
            'posts_per_page' => 1,
            'meta_query' => [
                [
                    'key' => 'student_user_id',
                    'value' => $user_id,
                    'compare' => '='
                ]
            ]
        ]);

        if (empty($student_posts)) {
            return [];
        }

        $student_id = $student_posts[0]->ID;

        return [
            'student_id' => $student_id,
            'class_info' => $this->get_student_class_info($student_id),
            'attendance_percentage' => $this->get_student_attendance_percentage($student_id),
            'pending_fees' => $this->get_student_pending_fees($student_id),
            'recent_notices' => $this->get_student_notices($student_id)
        ];
    }

    // Helper methods for dashboard data (placeholder implementations)
    private function get_pending_payments_count() { return 0; }
    private function get_recent_enrollments($limit) { return []; }
    private function get_teacher_attendance_summary($teacher_id) { return []; }
    private function get_upcoming_lessons($teacher_id) { return []; }
    private function get_parent_pending_fees($parent_id) { return 0; }
    private function get_recent_notices_for_parent($parent_id) { return []; }
    private function get_children_attendance_summary($parent_id) { return []; }
    private function get_student_class_info($student_id) { return []; }
    private function get_student_attendance_percentage($student_id) { return 0; }
    private function get_student_pending_fees($student_id) { return 0; }
    private function get_student_notices($student_id) { return []; }
}

// Initialize the class
new SMS_Role_Manager();