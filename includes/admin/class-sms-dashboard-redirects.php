<?php
/**
 * Dashboard Redirects Manager
 *
 * Handles redirecting users to their role-specific dashboards after login.
 *
 * @package School_Management_System
 * @subpackage Admin
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SMS Dashboard Redirects Class
 *
 * Manages user redirects to appropriate dashboards based on their roles.
 */
class SMS_Dashboard_Redirects {

    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Redirect users after login
        add_filter('login_redirect', [$this, 'redirect_after_login'], 10, 3);
        
        // Redirect users when accessing admin dashboard
        add_action('admin_init', [$this, 'redirect_to_role_dashboard']);
        
        // Modify admin menu for non-admin users
        add_action('admin_menu', [$this, 'modify_admin_menu_for_roles'], 999);
        
        // Remove dashboard widgets for non-admin users
        add_action('wp_dashboard_setup', [$this, 'remove_dashboard_widgets']);
        
        // Add custom dashboard widgets
        add_action('wp_dashboard_setup', [$this, 'add_custom_dashboard_widgets']);
    }

    /**
     * Redirect users to appropriate dashboard after login
     *
     * @param string $redirect_to URL to redirect to
     * @param string $request URL the user is coming from
     * @param WP_User|WP_Error $user Logged in user's data
     * @return string Redirect URL
     */
    public function redirect_after_login($redirect_to, $request, $user) {
        // Check if user login was successful
        if (isset($user->roles) && is_array($user->roles)) {
            // Get the user's primary role
            $role = $user->roles[0];
            
            // Redirect based on role
            switch ($role) {
                case 'sms_teacher':
                    return admin_url('admin.php?page=sms-teacher-dashboard');
                    
                case 'sms_parent':
                    return admin_url('admin.php?page=sms-parent-dashboard');
                    
                case 'sms_student':
                    return admin_url('admin.php?page=sms-student-dashboard');
                    
                case 'administrator':
                case 'sms_admin':
                    return admin_url('admin.php?page=sms-admin-dashboard');
                    
                default:
                    // For other roles, use default redirect
                    return $redirect_to;
            }
        }
        
        return $redirect_to;
    }

    /**
     * Redirect users to their role-specific dashboard when accessing admin
     */
    public function redirect_to_role_dashboard() {
        global $pagenow;
        
        // Only redirect on main admin page
        if ($pagenow !== 'index.php') {
            return;
        }
        
        // Don't redirect AJAX requests
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return;
        }
        
        // Don't redirect if already on a specific page
        if (isset($_GET['page'])) {
            return;
        }
        
        $current_user = wp_get_current_user();
        
        if (!$current_user->exists()) {
            return;
        }
        
        $user_roles = $current_user->roles;
        
        // Redirect based on user role
        if (in_array('sms_teacher', $user_roles)) {
            wp_redirect(admin_url('admin.php?page=sms-teacher-dashboard'));
            exit;
        } elseif (in_array('sms_parent', $user_roles)) {
            wp_redirect(admin_url('admin.php?page=sms-parent-dashboard'));
            exit;
        } elseif (in_array('sms_student', $user_roles)) {
            wp_redirect(admin_url('admin.php?page=sms-student-dashboard'));
            exit;
        } elseif (in_array('administrator', $user_roles) || in_array('sms_admin', $user_roles)) {
            wp_redirect(admin_url('admin.php?page=sms-admin-dashboard'));
            exit;
        }
    }

    /**
     * Modify admin menu for different user roles
     */
    public function modify_admin_menu_for_roles() {
        $current_user = wp_get_current_user();
        $user_roles = $current_user->roles;
        
        // For teachers
        if (in_array('sms_teacher', $user_roles) && !in_array('administrator', $user_roles)) {
            $this->setup_teacher_menu();
        }
        
        // For parents
        if (in_array('sms_parent', $user_roles) && !in_array('administrator', $user_roles)) {
            $this->setup_parent_menu();
        }
        
        // For students
        if (in_array('sms_student', $user_roles) && !in_array('administrator', $user_roles)) {
            $this->setup_student_menu();
        }
    }

    /**
     * Setup menu for teachers
     */
    private function setup_teacher_menu() {
        // Remove unnecessary menu items
        remove_menu_page('edit.php'); // Posts
        remove_menu_page('upload.php'); // Media
        remove_menu_page('edit.php?post_type=page'); // Pages
        remove_menu_page('edit-comments.php'); // Comments
        remove_menu_page('themes.php'); // Appearance
        remove_menu_page('plugins.php'); // Plugins
        remove_menu_page('tools.php'); // Tools
        remove_menu_page('options-general.php'); // Settings
        
        // Remove dashboard submenu items
        remove_submenu_page('index.php', 'update-core.php');
        
        // Modify the main dashboard link to point to teacher dashboard
        global $menu;
        foreach ($menu as $key => $item) {
            if ($item[2] === 'index.php') {
                $menu[$key][2] = 'admin.php?page=sms-teacher-dashboard';
                break;
            }
        }
    }

    /**
     * Setup menu for parents
     */
    private function setup_parent_menu() {
        // Remove all unnecessary menu items for parents
        remove_menu_page('edit.php'); // Posts
        remove_menu_page('upload.php'); // Media
        remove_menu_page('edit.php?post_type=page'); // Pages
        remove_menu_page('edit-comments.php'); // Comments
        remove_menu_page('themes.php'); // Appearance
        remove_menu_page('plugins.php'); // Plugins
        remove_menu_page('tools.php'); // Tools
        remove_menu_page('options-general.php'); // Settings
        
        // Remove profile submenu items except profile editing
        remove_submenu_page('users.php', 'users.php');
        remove_submenu_page('users.php', 'user-new.php');
        
        // Modify the main dashboard link to point to parent dashboard
        global $menu;
        foreach ($menu as $key => $item) {
            if ($item[2] === 'index.php') {
                $menu[$key][2] = 'admin.php?page=sms-parent-dashboard';
                break;
            }
        }
    }

    /**
     * Setup menu for students
     */
    private function setup_student_menu() {
        // Remove all unnecessary menu items for students
        remove_menu_page('edit.php'); // Posts
        remove_menu_page('upload.php'); // Media
        remove_menu_page('edit.php?post_type=page'); // Pages
        remove_menu_page('edit-comments.php'); // Comments
        remove_menu_page('themes.php'); // Appearance
        remove_menu_page('plugins.php'); // Plugins
        remove_menu_page('tools.php'); // Tools
        remove_menu_page('options-general.php'); // Settings
        remove_menu_page('users.php'); // Users
        
        // Modify the main dashboard link to point to student dashboard
        global $menu;
        foreach ($menu as $key => $item) {
            if ($item[2] === 'index.php') {
                $menu[$key][2] = 'admin.php?page=sms-student-dashboard';
                break;
            }
        }
    }

    /**
     * Remove default dashboard widgets for non-admin users
     */
    public function remove_dashboard_widgets() {
        $current_user = wp_get_current_user();
        $user_roles = $current_user->roles;
        
        // Don't remove widgets for administrators
        if (in_array('administrator', $user_roles)) {
            return;
        }
        
        // Remove default WordPress dashboard widgets
        remove_meta_box('dashboard_right_now', 'dashboard', 'normal');
        remove_meta_box('dashboard_recent_comments', 'dashboard', 'normal');
        remove_meta_box('dashboard_incoming_links', 'dashboard', 'normal');
        remove_meta_box('dashboard_plugins', 'dashboard', 'normal');
        remove_meta_box('dashboard_quick_press', 'dashboard', 'side');
        remove_meta_box('dashboard_recent_drafts', 'dashboard', 'side');
        remove_meta_box('dashboard_primary', 'dashboard', 'side');
        remove_meta_box('dashboard_secondary', 'dashboard', 'side');
        remove_meta_box('dashboard_activity', 'dashboard', 'normal');
    }

    /**
     * Add custom dashboard widgets based on user role
     */
    public function add_custom_dashboard_widgets() {
        $current_user = wp_get_current_user();
        $user_roles = $current_user->roles;
        
        // Add widgets for teachers
        if (in_array('sms_teacher', $user_roles)) {
            wp_add_dashboard_widget(
                'sms_teacher_classes',
                'My Classes',
                [$this, 'teacher_classes_widget']
            );
            
            wp_add_dashboard_widget(
                'sms_teacher_schedule',
                'Today\'s Schedule',
                [$this, 'teacher_schedule_widget']
            );
        }
        
        // Add widgets for parents
        if (in_array('sms_parent', $user_roles)) {
            wp_add_dashboard_widget(
                'sms_parent_children',
                'My Children',
                [$this, 'parent_children_widget']
            );
            
            wp_add_dashboard_widget(
                'sms_parent_payments',
                'Payment Status',
                [$this, 'parent_payments_widget']
            );
        }
        
        // Add widgets for students
        if (in_array('sms_student', $user_roles)) {
            wp_add_dashboard_widget(
                'sms_student_attendance',
                'My Attendance',
                [$this, 'student_attendance_widget']
            );
            
            wp_add_dashboard_widget(
                'sms_student_notices',
                'School Notices',
                [$this, 'student_notices_widget']
            );
        }
    }

    /**
     * Teacher classes widget
     */
    public function teacher_classes_widget() {
        $current_user_id = get_current_user_id();
        
        // Get teacher's classes
        $classes = get_posts([
            'post_type' => 'cpt_classes',
            'meta_query' => [
                [
                    'key' => 'teacher_id',
                    'value' => $current_user_id,
                    'compare' => '='
                ]
            ],
            'posts_per_page' => -1
        ]);
        
        if (empty($classes)) {
            echo '<p>No classes assigned yet.</p>';
            return;
        }
        
        echo '<ul>';
        foreach ($classes as $class) {
            $student_count = $this->get_class_student_count($class->ID);
            echo '<li>';
            echo '<strong>' . esc_html($class->post_title) . '</strong><br>';
            echo 'Students: ' . $student_count;
            echo '</li>';
        }
        echo '</ul>';
        
        echo '<p><a href="' . admin_url('admin.php?page=sms-teacher-dashboard') . '" class="button">View Full Dashboard</a></p>';
    }

    /**
     * Teacher schedule widget
     */
    public function teacher_schedule_widget() {
        echo '<p>Today\'s schedule will be displayed here.</p>';
        echo '<p><a href="' . admin_url('admin.php?page=sms-teacher-dashboard') . '" class="button">View Full Schedule</a></p>';
    }

    /**
     * Parent children widget
     */
    public function parent_children_widget() {
        $current_user_id = get_current_user_id();
        
        // Get parent's children
        $children = get_posts([
            'post_type' => 'cpt_students',
            'meta_query' => [
                [
                    'key' => 'parent_user_id',
                    'value' => $current_user_id,
                    'compare' => '='
                ]
            ],
            'posts_per_page' => -1
        ]);
        
        if (empty($children)) {
            echo '<p>No children found in the system.</p>';
            return;
        }
        
        echo '<ul>';
        foreach ($children as $child) {
            $class = get_post_meta($child->ID, 'current_class', true);
            $attendance_rate = $this->get_student_attendance_rate($child->ID);
            
            echo '<li>';
            echo '<strong>' . esc_html($child->post_title) . '</strong><br>';
            if ($class) {
                $class_post = get_post($class);
                echo 'Class: ' . ($class_post ? esc_html($class_post->post_title) : 'N/A') . '<br>';
            }
            echo 'Attendance: ' . $attendance_rate . '%';
            echo '</li>';
        }
        echo '</ul>';
        
        echo '<p><a href="' . admin_url('admin.php?page=sms-parent-dashboard') . '" class="button">View Full Dashboard</a></p>';
    }

    /**
     * Parent payments widget
     */
    public function parent_payments_widget() {
        $current_user_id = get_current_user_id();
        
        // Get outstanding payments for parent's children
        $outstanding_amount = $this->get_parent_outstanding_payments($current_user_id);
        
        if ($outstanding_amount > 0) {
            echo '<p><strong>Outstanding Balance:</strong> KES ' . number_format($outstanding_amount, 2) . '</p>';
            echo '<p><a href="' . admin_url('admin.php?page=sms-parent-dashboard') . '" class="button button-primary">Make Payment</a></p>';
        } else {
            echo '<p><strong>All payments up to date!</strong></p>';
        }
        
        echo '<p><a href="' . admin_url('admin.php?page=sms-parent-dashboard') . '" class="button">View Payment History</a></p>';
    }

    /**
     * Student attendance widget
     */
    public function student_attendance_widget() {
        $current_user_id = get_current_user_id();
        
        // Get student record for current user
        $student = $this->get_student_by_user_id($current_user_id);
        
        if (!$student) {
            echo '<p>Student record not found.</p>';
            return;
        }
        
        $attendance_rate = $this->get_student_attendance_rate($student->ID);
        
        echo '<p><strong>Current Attendance Rate:</strong> ' . $attendance_rate . '%</p>';
        
        if ($attendance_rate < 80) {
            echo '<p style="color: #d63638;"><strong>Warning:</strong> Your attendance is below the required 80%.</p>';
        } elseif ($attendance_rate >= 95) {
            echo '<p style="color: #00a32a;"><strong>Excellent!</strong> Keep up the good attendance.</p>';
        }
        
        echo '<p><a href="' . admin_url('admin.php?page=sms-student-dashboard') . '" class="button">View Full Dashboard</a></p>';
    }

    /**
     * Student notices widget
     */
    public function student_notices_widget() {
        // Get recent notices
        $notices = get_posts([
            'post_type' => 'cpt_notices',
            'posts_per_page' => 3,
            'post_status' => 'publish',
            'orderby' => 'date',
            'order' => 'DESC'
        ]);
        
        if (empty($notices)) {
            echo '<p>No recent notices.</p>';
            return;
        }
        
        echo '<ul>';
        foreach ($notices as $notice) {
            echo '<li>';
            echo '<strong>' . esc_html($notice->post_title) . '</strong><br>';
            echo '<small>' . date('M j, Y', strtotime($notice->post_date)) . '</small>';
            echo '</li>';
        }
        echo '</ul>';
        
        echo '<p><a href="' . admin_url('admin.php?page=sms-student-dashboard') . '" class="button">View All Notices</a></p>';
    }

    /**
     * Helper method to get class student count
     */
    private function get_class_student_count($class_id) {
        global $wpdb;
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} 
             WHERE meta_key = 'current_class' AND meta_value = %d",
            $class_id
        ));
    }

    /**
     * Helper method to get student attendance rate
     */
    private function get_student_attendance_rate($student_id) {
        // This is a simplified calculation
        // In a real implementation, you'd calculate based on actual attendance records
        return rand(75, 98); // Placeholder
    }

    /**
     * Helper method to get parent's outstanding payments
     */
    private function get_parent_outstanding_payments($parent_id) {
        // Get parent's children
        $children = get_posts([
            'post_type' => 'cpt_students',
            'meta_query' => [
                [
                    'key' => 'parent_user_id',
                    'value' => $parent_id,
                    'compare' => '='
                ]
            ],
            'posts_per_page' => -1,
            'fields' => 'ids'
        ]);
        
        if (empty($children)) {
            return 0;
        }
        
        global $wpdb;
        
        $child_ids = implode(',', array_map('intval', $children));
        
        $outstanding = $wpdb->get_var("
            SELECT SUM(pm.meta_value) 
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE p.post_type = 'cpt_invoices'
            AND p.post_status = 'publish'
            AND pm.meta_key = 'invoice_amount'
            AND pm.post_id IN (
                SELECT post_id FROM {$wpdb->postmeta} 
                WHERE meta_key = 'student_id' 
                AND meta_value IN ({$child_ids})
            )
            AND NOT EXISTS (
                SELECT 1 FROM {$wpdb->postmeta} pm2 
                WHERE pm2.post_id = p.ID 
                AND pm2.meta_key = 'payment_status' 
                AND pm2.meta_value = 'paid'
            )
        ");
        
        return $outstanding ? floatval($outstanding) : 0;
    }

    /**
     * Helper method to get student by user ID
     */
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
}