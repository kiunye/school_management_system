<?php
/**
 * Parent access control and data filtering system.
 *
 * @package    School_Management_System
 * @subpackage School_Management_System/includes/user-roles
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Parent access control class.
 */
class SMS_Parent_Access_Control extends SMS_Base {

    /**
     * Initialize parent access control.
     */
    public function __construct() {
        parent::__construct();
        
        // Filter queries for parent users
        add_action('pre_get_posts', [$this, 'filter_posts_for_parents']);
        add_filter('posts_where', [$this, 'filter_posts_where_for_parents'], 10, 2);
        
        // Filter admin menu items for parents
        add_action('admin_menu', [$this, 'filter_admin_menu_for_parents'], 999);
        
        // Filter admin bar for parents
        add_action('wp_before_admin_bar_render', [$this, 'filter_admin_bar_for_parents']);
        
        // Restrict direct post access
        add_action('load-post.php', [$this, 'restrict_post_access']);
        add_action('load-edit.php', [$this, 'restrict_edit_access']);
        
        // Filter REST API responses
        add_filter('rest_prepare_sms_students', [$this, 'filter_rest_student_response'], 10, 3);
        
        // Add parent dashboard redirect
        add_action('admin_init', [$this, 'redirect_parents_to_dashboard']);
    }

    /**
     * Filter posts query for parent users.
     */
    public function filter_posts_for_parents($query) {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }

        $current_user = wp_get_current_user();
        if (!in_array('parent', $current_user->roles)) {
            return;
        }

        $post_type = $query->get('post_type');
        
        // Filter student posts
        if ($post_type === 'sms_students') {
            $children = get_user_meta($current_user->ID, 'sms_children', true);
            
            if (is_array($children) && !empty($children)) {
                $query->set('post__in', $children);
            } else {
                $query->set('post__in', [0]); // Show no posts
            }
        }
        
        // Filter fee-related posts
        elseif (in_array($post_type, ['sms_fees', 'sms_invoices', 'sms_transactions'])) {
            $children = get_user_meta($current_user->ID, 'sms_children', true);
            
            if (is_array($children) && !empty($children)) {
                $query->set('meta_query', [
                    [
                        'key' => 'student_id',
                        'value' => $children,
                        'compare' => 'IN'
                    ]
                ]);
            } else {
                $query->set('post__in', [0]);
            }
        }
        
        // Filter attendance posts
        elseif ($post_type === 'sms_attendance') {
            $children = get_user_meta($current_user->ID, 'sms_children', true);
            
            if (is_array($children) && !empty($children)) {
                $query->set('meta_query', [
                    [
                        'key' => 'student_id',
                        'value' => $children,
                        'compare' => 'IN'
                    ]
                ]);
            } else {
                $query->set('post__in', [0]);
            }
        }
        
        // Block access to other post types
        elseif (in_array($post_type, ['sms_classes', 'sms_timetables', 'sms_transport_routes'])) {
            // Parents should not see these directly
            $query->set('post__in', [0]);
        }
    }

    /**
     * Filter posts WHERE clause for parent users.
     */
    public function filter_posts_where_for_parents($where, $query) {
        global $wpdb;
        
        if (!is_admin() || !$query->is_main_query()) {
            return $where;
        }

        $current_user = wp_get_current_user();
        if (!in_array('parent', $current_user->roles)) {
            return $where;
        }

        $post_type = $query->get('post_type');
        
        // Additional WHERE filtering for complex queries
        if (in_array($post_type, ['sms_fees', 'sms_invoices', 'sms_transactions'])) {
            $children = get_user_meta($current_user->ID, 'sms_children', true);
            
            if (is_array($children) && !empty($children)) {
                $children_ids = implode(',', array_map('intval', $children));
                $where .= " AND EXISTS (
                    SELECT 1 FROM {$wpdb->postmeta} 
                    WHERE {$wpdb->postmeta}.post_id = {$wpdb->posts}.ID 
                    AND {$wpdb->postmeta}.meta_key = 'student_id' 
                    AND {$wpdb->postmeta}.meta_value IN ({$children_ids})
                )";
            } else {
                $where .= " AND 1=0";
            }
        }
        
        return $where;
    }

    /**
     * Filter admin menu items for parent users.
     */
    public function filter_admin_menu_for_parents() {
        $current_user = wp_get_current_user();
        if (!in_array('parent', $current_user->roles)) {
            return;
        }

        // Remove menu items that parents shouldn't access
        $restricted_menus = [
            'edit.php?post_type=sms_classes',
            'edit.php?post_type=sms_timetables',
            'edit.php?post_type=sms_transport_routes',
            'users.php',
            'tools.php',
            'options-general.php',
            'plugins.php',
            'themes.php'
        ];

        foreach ($restricted_menus as $menu) {
            remove_menu_page($menu);
        }

        // Remove submenu items
        remove_submenu_page('edit.php?post_type=sms_students', 'post-new.php?post_type=sms_students');
        
        // Customize existing menu items
        global $menu, $submenu;
        
        // Rename "Students" to "My Children"
        foreach ($menu as $key => $item) {
            if (isset($item[2]) && $item[2] === 'edit.php?post_type=sms_students') {
                $menu[$key][0] = __('My Children', 'school-management-system');
                break;
            }
        }
    }

    /**
     * Filter admin bar for parent users.
     */
    public function filter_admin_bar_for_parents() {
        $current_user = wp_get_current_user();
        if (!in_array('parent', $current_user->roles)) {
            return;
        }

        global $wp_admin_bar;
        
        // Remove admin bar items that parents shouldn't see
        $wp_admin_bar->remove_node('new-content');
        $wp_admin_bar->remove_node('comments');
        $wp_admin_bar->remove_node('appearance');
        $wp_admin_bar->remove_node('updates');
    }

    /**
     * Restrict direct post access for parents.
     */
    public function restrict_post_access() {
        if (!isset($_GET['post'])) {
            return;
        }

        $post_id = intval($_GET['post']);
        $post = get_post($post_id);
        
        if (!$post) {
            return;
        }

        $current_user = wp_get_current_user();
        if (!in_array('parent', $current_user->roles)) {
            return;
        }

        // Check if parent can access this post
        if (!$this->can_parent_access_post($current_user->ID, $post)) {
            wp_die(__('You do not have permission to access this item.', 'school-management-system'));
        }
    }

    /**
     * Restrict edit page access for parents.
     */
    public function restrict_edit_access() {
        if (!isset($_GET['post_type'])) {
            return;
        }

        $post_type = $_GET['post_type'];
        $current_user = wp_get_current_user();
        
        if (!in_array('parent', $current_user->roles)) {
            return;
        }

        // Block access to restricted post types
        $restricted_types = ['sms_classes', 'sms_timetables', 'sms_transport_routes'];
        
        if (in_array($post_type, $restricted_types)) {
            wp_die(__('You do not have permission to access this section.', 'school-management-system'));
        }
    }

    /**
     * Check if parent can access specific post.
     */
    private function can_parent_access_post($parent_id, $post) {
        $children = get_user_meta($parent_id, 'sms_children', true);
        
        if (!is_array($children) || empty($children)) {
            return false;
        }

        switch ($post->post_type) {
            case 'sms_students':
                return in_array($post->ID, $children);
                
            case 'sms_fees':
            case 'sms_invoices':
            case 'sms_transactions':
                $student_id = get_field('student_id', $post->ID);
                return in_array($student_id, $children);
                
            case 'sms_attendance':
                $student_id = get_field('student_id', $post->ID);
                return in_array($student_id, $children);
                
            case 'sms_notices':
                // Parents can view notices targeted to them or their children's classes
                return $this->can_parent_view_notice($parent_id, $post->ID);
                
            default:
                return false;
        }
    }

    /**
     * Check if parent can view specific notice.
     */
    private function can_parent_view_notice($parent_id, $notice_id) {
        $target_audience = get_field('target_audience', $notice_id);
        
        if (!is_array($target_audience)) {
            return false;
        }

        // Check if notice is targeted to parents
        if (in_array('parents', $target_audience)) {
            return true;
        }

        // Check if notice is targeted to parent's children's classes
        $children = get_user_meta($parent_id, 'sms_children', true);
        if (!is_array($children)) {
            return false;
        }

        foreach ($children as $child_id) {
            $child_class = get_field('assigned_class', $child_id);
            if ($child_class && in_array($child_class, $target_audience)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Filter REST API responses for parents.
     */
    public function filter_rest_student_response($response, $post, $request) {
        $current_user = wp_get_current_user();
        
        if (!in_array('parent', $current_user->roles)) {
            return $response;
        }

        $children = get_user_meta($current_user->ID, 'sms_children', true);
        
        if (!is_array($children) || !in_array($post->ID, $children)) {
            return new WP_Error('rest_forbidden', __('You do not have permission to view this student.', 'school-management-system'), ['status' => 403]);
        }

        return $response;
    }

    /**
     * Redirect parents to appropriate dashboard.
     */
    public function redirect_parents_to_dashboard() {
        $current_user = wp_get_current_user();
        
        if (!in_array('parent', $current_user->roles)) {
            return;
        }

        // Get current page
        global $pagenow;
        
        // Redirect from main dashboard to parent-specific dashboard
        if ($pagenow === 'index.php' && !isset($_GET['page'])) {
            $redirect_url = admin_url('edit.php?post_type=sms_students');
            wp_redirect($redirect_url);
            exit;
        }
    }

    /**
     * Get filtered data for parent dashboard.
     */
    public function get_parent_dashboard_data($parent_id) {
        $children = get_user_meta($parent_id, 'sms_children', true);
        
        if (!is_array($children) || empty($children)) {
            return [
                'children' => [],
                'total_children' => 0,
                'pending_fees' => 0,
                'recent_notices' => [],
                'attendance_summary' => []
            ];
        }

        $dashboard_data = [
            'children' => $this->get_children_summary($children),
            'total_children' => count($children),
            'pending_fees' => $this->get_total_pending_fees($children),
            'recent_notices' => $this->get_recent_notices_for_parent($parent_id),
            'attendance_summary' => $this->get_children_attendance_summary($children),
            'upcoming_events' => $this->get_upcoming_events_for_children($children)
        ];

        return apply_filters('sms_parent_dashboard_data', $dashboard_data, $parent_id);
    }

    /**
     * Get children summary for parent.
     */
    private function get_children_summary($children_ids) {
        $children_summary = [];
        
        foreach ($children_ids as $child_id) {
            $child_data = [
                'id' => $child_id,
                'name' => get_field('full_name', $child_id),
                'admission_number' => get_field('admission_number', $child_id),
                'class' => $this->get_student_class_info($child_id),
                'status' => get_field('student_status', $child_id),
                'attendance_percentage' => $this->calculate_attendance_percentage($child_id),
                'pending_fees' => $this->get_student_pending_fees($child_id)
            ];
            $children_summary[] = $child_data;
        }
        
        return $children_summary;
    }

    /**
     * Get student class information.
     */
    private function get_student_class_info($student_id) {
        $class_id = get_field('assigned_class', $student_id);
        if (!$class_id) {
            return null;
        }

        return [
            'id' => $class_id,
            'name' => get_field('class_name', $class_id),
            'code' => get_field('class_code', $class_id),
            'teacher' => $this->get_class_teacher_info($class_id)
        ];
    }

    /**
     * Get class teacher information.
     */
    private function get_class_teacher_info($class_id) {
        $teacher_id = get_field('class_teacher', $class_id);
        if (!$teacher_id) {
            return null;
        }

        $teacher = get_userdata($teacher_id);
        return [
            'id' => $teacher_id,
            'name' => $teacher->display_name,
            'email' => $teacher->user_email
        ];
    }

    /**
     * Get total pending fees for all children.
     */
    private function get_total_pending_fees($children_ids) {
        $total = 0;
        foreach ($children_ids as $child_id) {
            $total += $this->get_student_pending_fees($child_id);
        }
        return $total;
    }

    /**
     * Get recent notices for parent.
     */
    private function get_recent_notices_for_parent($parent_id, $limit = 5) {
        $children = get_user_meta($parent_id, 'sms_children', true);
        
        $notices = get_posts([
            'post_type' => 'sms_notices',
            'posts_per_page' => $limit,
            'post_status' => 'publish',
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => 'target_audience',
                    'value' => 'parents',
                    'compare' => 'LIKE'
                ]
                // TODO: Add class-specific notices when notices CPT is implemented
            ]
        ]);

        return $notices;
    }

    /**
     * Get attendance summary for children.
     */
    private function get_children_attendance_summary($children_ids) {
        $summary = [];
        
        foreach ($children_ids as $child_id) {
            $summary[] = [
                'student_id' => $child_id,
                'student_name' => get_field('full_name', $child_id),
                'attendance_percentage' => $this->calculate_attendance_percentage($child_id),
                'days_present' => $this->get_student_days_present($child_id),
                'days_absent' => $this->get_student_days_absent($child_id)
            ];
        }
        
        return $summary;
    }

    /**
     * Get upcoming events for children.
     */
    private function get_upcoming_events_for_children($children_ids) {
        // TODO: Implement when events system is ready
        return [];
    }

    // Helper methods (placeholder implementations)
    private function calculate_attendance_percentage($student_id) {
        // TODO: Implement when attendance system is ready
        return 95; // Placeholder
    }

    private function get_student_pending_fees($student_id) {
        // TODO: Implement when fee system is ready
        return 0; // Placeholder
    }

    private function get_student_days_present($student_id) {
        // TODO: Implement when attendance system is ready
        return 0; // Placeholder
    }

    private function get_student_days_absent($student_id) {
        // TODO: Implement when attendance system is ready
        return 0; // Placeholder
    }
}

// Initialize the class
new SMS_Parent_Access_Control();