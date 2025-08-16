<?php
/**
 * Notice Management Admin Class
 *
 * @package SchoolManagementSystem
 * @subpackage Admin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class SMS_Notice_Manager
 * 
 * Handles notice management functionality in the admin area
 */
class SMS_Notice_Manager {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_sms_create_quick_notice', array($this, 'create_quick_notice'));
        add_action('wp_ajax_sms_get_audience_preview', array($this, 'get_audience_preview'));
        add_action('wp_ajax_sms_bulk_notice_action', array($this, 'handle_bulk_action'));
        add_filter('post_row_actions', array($this, 'add_notice_row_actions'), 10, 2);
    }

    /**
     * Add admin menu for notices
     */
    public function add_admin_menu() {
        add_submenu_page(
            'school-management',
            __('Notices', 'school-management-system'),
            __('Notices', 'school-management-system'),
            'manage_notices',
            'sms-notices',
            array($this, 'display_notices_page')
        );
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'sms-notices') !== false) {
            wp_enqueue_style(
                'sms-notices-admin',
                SMS_PLUGIN_URL . 'admin/css/notices-admin.css',
                array(),
                SMS_VERSION
            );
            
            wp_enqueue_script(
                'sms-notices-admin',
                SMS_PLUGIN_URL . 'admin/js/notices-admin.js',
                array('jquery'),
                SMS_VERSION,
                true
            );
            
            wp_localize_script('sms-notices-admin', 'smsNoticesAdmin', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('sms_notices_admin_nonce'),
                'strings' => array(
                    'confirmDelete' => __('Are you sure you want to delete this notice?', 'school-management-system'),
                    'confirmBulkDelete' => __('Are you sure you want to delete the selected notices?', 'school-management-system'),
                    'sending' => __('Sending...', 'school-management-system'),
                    'processing' => __('Processing...', 'school-management-system'),
                    'error' => __('An error occurred. Please try again.', 'school-management-system'),
                    'success' => __('Operation completed successfully.', 'school-management-system')
                )
            ));
        }
    }

    /**
     * Display notices management page
     */
    public function display_notices_page() {
        // Handle form submissions
        if (isset($_POST['submit']) && wp_verify_nonce($_POST['notices_nonce'], 'sms_notices_bulk_action')) {
            $this->handle_form_submission();
        }
        
        include SMS_PLUGIN_PATH . 'admin/partials/notices.php';
    }

    /**
     * Get notices by status
     */
    public function get_notices_by_status($status = 'active', $limit = 20, $offset = 0) {
        $args = array(
            'post_type' => 'sms_notices',
            'posts_per_page' => $limit,
            'offset' => $offset,
            'post_status' => 'publish',
            'orderby' => 'date',
            'order' => 'DESC'
        );

        // Add meta query based on status
        switch ($status) {
            case 'active':
                $args['meta_query'] = array(
                    array(
                        'key' => 'notice_status',
                        'value' => 'active',
                        'compare' => '='
                    )
                );
                break;
                
            case 'draft':
                $args['post_status'] = 'draft';
                break;
                
            case 'expired':
                $args['meta_query'] = array(
                    array(
                        'key' => 'notice_status',
                        'value' => 'expired',
                        'compare' => '='
                    )
                );
                break;
                
            case 'archived':
                $args['meta_query'] = array(
                    array(
                        'key' => 'notice_status',
                        'value' => 'archived',
                        'compare' => '='
                    )
                );
                break;
        }

        return get_posts($args);
    }

    /**
     * Get notices count by status
     */
    public function get_notices_count($status = 'active') {
        $args = array(
            'post_type' => 'sms_notices',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'fields' => 'ids'
        );

        switch ($status) {
            case 'active':
                $args['meta_query'] = array(
                    array(
                        'key' => 'notice_status',
                        'value' => 'active',
                        'compare' => '='
                    )
                );
                break;
                
            case 'draft':
                $args['post_status'] = 'draft';
                break;
                
            case 'expired':
                $args['meta_query'] = array(
                    array(
                        'key' => 'notice_status',
                        'value' => 'expired',
                        'compare' => '='
                    )
                );
                break;
                
            case 'archived':
                $args['meta_query'] = array(
                    array(
                        'key' => 'notice_status',
                        'value' => 'archived',
                        'compare' => '='
                    )
                );
                break;
        }

        $notices = get_posts($args);
        return count($notices);
    }

    /**
     * Handle form submissions
     */
    private function handle_form_submission() {
        if (isset($_POST['bulk_action']) && isset($_POST['notice_ids'])) {
            $action = sanitize_text_field($_POST['bulk_action']);
            $notice_ids = array_map('intval', $_POST['notice_ids']);
            
            $this->handle_bulk_actions($action, $notice_ids);
        }
    }

    /**
     * Handle bulk actions
     */
    public function handle_bulk_actions($action, $notice_ids) {
        if (empty($notice_ids) || !is_array($notice_ids)) {
            return false;
        }

        $success_count = 0;
        $error_count = 0;

        foreach ($notice_ids as $notice_id) {
            $result = false;
            
            switch ($action) {
                case 'expire':
                    $result = $this->expire_notice($notice_id);
                    break;
                    
                case 'archive':
                    $result = $this->archive_notice($notice_id);
                    break;
                    
                case 'reactivate':
                    $result = $this->reactivate_notice($notice_id);
                    break;
                    
                case 'restore':
                    $result = $this->restore_notice($notice_id);
                    break;
                    
                case 'publish':
                    $result = $this->publish_notice($notice_id);
                    break;
                    
                case 'delete':
                    $result = wp_delete_post($notice_id, true);
                    break;
                    
                case 'send_notifications':
                    $result = $this->send_notice_notifications($notice_id);
                    break;
            }
            
            if ($result) {
                $success_count++;
            } else {
                $error_count++;
            }
        }

        // Add admin notice
        $message = sprintf(
            __('%d notices processed successfully. %d errors.', 'school-management-system'),
            $success_count,
            $error_count
        );
        
        add_action('admin_notices', function() use ($message) {
            echo '<div class="notice notice-success is-dismissible"><p>' . $message . '</p></div>';
        });

        return $success_count > 0;
    }

    /**
     * Expire a notice
     */
    private function expire_notice($notice_id) {
        return update_field('notice_status', 'expired', $notice_id);
    }

    /**
     * Archive a notice
     */
    private function archive_notice($notice_id) {
        return update_field('notice_status', 'archived', $notice_id);
    }

    /**
     * Reactivate a notice
     */
    private function reactivate_notice($notice_id) {
        // Reset expiry date if needed
        $expiry_date = get_field('notice_expiry_date', $notice_id);
        if ($expiry_date && strtotime($expiry_date) < current_time('timestamp')) {
            // Extend expiry by 7 days
            $new_expiry = date('Y-m-d H:i:s', current_time('timestamp') + (7 * 24 * 60 * 60));
            update_field('notice_expiry_date', $new_expiry, $notice_id);
        }
        
        return update_field('notice_status', 'active', $notice_id);
    }

    /**
     * Restore a notice
     */
    private function restore_notice($notice_id) {
        return update_field('notice_status', 'active', $notice_id);
    }

    /**
     * Publish a notice
     */
    private function publish_notice($notice_id) {
        $result = wp_update_post(array(
            'ID' => $notice_id,
            'post_status' => 'publish'
        ));
        
        if ($result) {
            update_field('notice_status', 'active', $notice_id);
        }
        
        return $result;
    }

    /**
     * Send notice notifications
     */
    private function send_notice_notifications($notice_id) {
        // Get the notice
        $notice = get_post($notice_id);
        if (!$notice) {
            return false;
        }

        // Check if SMS integration is available
        if (!class_exists('SMS_Communication_Handler')) {
            return false;
        }

        $communication_handler = new SMS_Communication_Handler();
        
        // Get target audience
        $recipients = $this->get_notice_recipients($notice_id);
        
        if (empty($recipients)) {
            return false;
        }

        // Prepare message
        $message = $this->prepare_notice_message($notice);
        
        // Send notifications
        $result = $communication_handler->send_bulk_sms($recipients, $message, array(
            'priority' => get_field('notice_priority', $notice_id),
            'reference' => 'notice_' . $notice_id
        ));

        // Update delivery status
        if ($result['success']) {
            update_field('delivery_status', 'sent', $notice_id);
            update_field('sent_count', $result['sent_count'], $notice_id);
            update_field('failed_count', $result['failed_count'], $notice_id);
            update_field('last_sent', current_time('mysql'), $notice_id);
        } else {
            update_field('delivery_status', 'failed', $notice_id);
        }

        return $result['success'];
    }

    /**
     * Get notice recipients
     */
    private function get_notice_recipients($notice_id) {
        $audience_type = get_field('audience_type', $notice_id);
        $recipients = array();

        switch ($audience_type) {
            case 'all':
                $recipients = $this->get_all_users_recipients();
                break;
                
            case 'roles':
                $target_roles = get_field('target_roles', $notice_id);
                $recipients = $this->get_role_recipients($target_roles);
                break;
                
            case 'classes':
                $target_classes = get_field('target_classes', $notice_id);
                $recipients = $this->get_class_recipients($target_classes);
                break;
                
            case 'parents_of_class':
                $target_classes = get_field('target_classes', $notice_id);
                $recipients = $this->get_parents_of_class_recipients($target_classes);
                break;
                
            case 'individuals':
                $target_individuals = get_field('target_individuals', $notice_id);
                $recipients = $this->get_individual_recipients($target_individuals);
                break;
        }

        return $recipients;
    }

    /**
     * Get all users as recipients
     */
    private function get_all_users_recipients() {
        $users = get_users(array(
            'meta_query' => array(
                array(
                    'key' => 'phone_number',
                    'value' => '',
                    'compare' => '!='
                )
            )
        ));

        $recipients = array();
        foreach ($users as $user) {
            $phone = get_user_meta($user->ID, 'phone_number', true);
            if ($phone) {
                $recipients[] = array(
                    'user_id' => $user->ID,
                    'name' => $user->display_name,
                    'phone' => $phone,
                    'email' => $user->user_email
                );
            }
        }

        return $recipients;
    }

    /**
     * Get recipients by role
     */
    private function get_role_recipients($roles) {
        if (empty($roles)) {
            return array();
        }

        $recipients = array();
        foreach ($roles as $role) {
            $users = get_users(array(
                'role' => $role,
                'meta_query' => array(
                    array(
                        'key' => 'phone_number',
                        'value' => '',
                        'compare' => '!='
                    )
                )
            ));

            foreach ($users as $user) {
                $phone = get_user_meta($user->ID, 'phone_number', true);
                if ($phone) {
                    $recipients[] = array(
                        'user_id' => $user->ID,
                        'name' => $user->display_name,
                        'phone' => $phone,
                        'email' => $user->user_email
                    );
                }
            }
        }

        return $recipients;
    }

    /**
     * Get recipients by class
     */
    private function get_class_recipients($classes) {
        if (empty($classes)) {
            return array();
        }

        $recipients = array();
        foreach ($classes as $class) {
            // Get students in this class
            $students = get_posts(array(
                'post_type' => 'sms_students',
                'posts_per_page' => -1,
                'meta_query' => array(
                    array(
                        'key' => 'assigned_class',
                        'value' => $class->ID,
                        'compare' => '='
                    )
                )
            ));

            foreach ($students as $student) {
                $student_user_id = get_field('student_user_id', $student->ID);
                if ($student_user_id) {
                    $user = get_userdata($student_user_id);
                    $phone = get_user_meta($student_user_id, 'phone_number', true);
                    
                    if ($user && $phone) {
                        $recipients[] = array(
                            'user_id' => $student_user_id,
                            'name' => $user->display_name,
                            'phone' => $phone,
                            'email' => $user->user_email
                        );
                    }
                }
            }
        }

        return $recipients;
    }

    /**
     * Get parents of class recipients
     */
    private function get_parents_of_class_recipients($classes) {
        if (empty($classes)) {
            return array();
        }

        $recipients = array();
        foreach ($classes as $class) {
            // Get students in this class
            $students = get_posts(array(
                'post_type' => 'sms_students',
                'posts_per_page' => -1,
                'meta_query' => array(
                    array(
                        'key' => 'assigned_class',
                        'value' => $class->ID,
                        'compare' => '='
                    )
                )
            ));

            foreach ($students as $student) {
                // Get parent details
                $parent_details = get_field('parent_details', $student->ID);
                if ($parent_details && !empty($parent_details['phone'])) {
                    $recipients[] = array(
                        'user_id' => null,
                        'name' => $parent_details['name'] ?? 'Parent',
                        'phone' => $parent_details['phone'],
                        'email' => $parent_details['email'] ?? ''
                    );
                }
            }
        }

        return $recipients;
    }

    /**
     * Get individual recipients
     */
    private function get_individual_recipients($individuals) {
        if (empty($individuals)) {
            return array();
        }

        $recipients = array();
        foreach ($individuals as $user) {
            $phone = get_user_meta($user->ID, 'phone_number', true);
            if ($phone) {
                $recipients[] = array(
                    'user_id' => $user->ID,
                    'name' => $user->display_name,
                    'phone' => $phone,
                    'email' => $user->user_email
                );
            }
        }

        return $recipients;
    }

    /**
     * Prepare notice message for SMS
     */
    private function prepare_notice_message($notice) {
        $school_name = get_option('sms_school_name', get_bloginfo('name'));
        $priority = get_field('notice_priority', $notice->ID);
        
        $message = '';
        
        // Add priority prefix for urgent notices
        if (in_array($priority, array('urgent', 'emergency'))) {
            $message .= strtoupper($priority) . ': ';
        }
        
        // Add notice title and content
        $message .= $notice->post_title . ' - ';
        $message .= wp_strip_all_tags($notice->post_content);
        
        // Truncate if too long (SMS limit is usually 160 characters)
        if (strlen($message) > 140) {
            $message = substr($message, 0, 137) . '...';
        }
        
        // Add school signature
        $message .= ' - ' . $school_name;
        
        return $message;
    }

    /**
     * Create quick notice via AJAX
     */
    public function create_quick_notice() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_notices_admin_nonce')) {
            wp_send_json_error(__('Security check failed', 'school-management-system'));
        }

        // Check capabilities
        if (!current_user_can('manage_notices')) {
            wp_send_json_error(__('You do not have permission to create notices', 'school-management-system'));
        }

        // Sanitize input
        $title = sanitize_text_field($_POST['notice_title']);
        $content = sanitize_textarea_field($_POST['notice_content']);
        $priority = sanitize_text_field($_POST['notice_priority']);
        $audience_type = sanitize_text_field($_POST['audience_type']);
        $send_notifications = isset($_POST['send_notifications']) ? true : false;

        // Create notice
        $notice_id = wp_insert_post(array(
            'post_title' => $title,
            'post_content' => $content,
            'post_type' => 'sms_notices',
            'post_status' => 'publish',
            'post_author' => get_current_user_id()
        ));

        if (is_wp_error($notice_id)) {
            wp_send_json_error(__('Failed to create notice', 'school-management-system'));
        }

        // Update custom fields
        update_field('notice_priority', $priority, $notice_id);
        update_field('audience_type', $audience_type, $notice_id);
        update_field('notice_status', 'active', $notice_id);
        update_field('send_notifications', $send_notifications, $notice_id);

        // Send notifications if requested
        if ($send_notifications) {
            $this->send_notice_notifications($notice_id);
        }

        wp_send_json_success(array(
            'notice_id' => $notice_id,
            'message' => __('Notice created successfully', 'school-management-system'),
            'redirect_url' => admin_url('admin.php?page=sms-notices')
        ));
    }

    /**
     * Get audience preview via AJAX
     */
    public function get_audience_preview() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_notices_admin_nonce')) {
            wp_send_json_error(__('Security check failed', 'school-management-system'));
        }

        $audience_type = sanitize_text_field($_POST['audience_type']);
        $audience_data = json_decode(stripslashes($_POST['audience_data']), true);

        $count = $this->calculate_audience_count($audience_type, $audience_data);
        
        wp_send_json_success(array(
            'count' => $count,
            'message' => sprintf(__('Estimated %d recipients', 'school-management-system'), $count)
        ));
    }

    /**
     * Calculate audience count
     */
    private function calculate_audience_count($audience_type, $audience_data = array()) {
        switch ($audience_type) {
            case 'all':
                return $this->count_all_users();
                
            case 'roles':
                return $this->count_role_users($audience_data['roles'] ?? array());
                
            case 'classes':
                return $this->count_class_users($audience_data['classes'] ?? array());
                
            case 'parents_of_class':
                return $this->count_parents_of_class($audience_data['classes'] ?? array());
                
            case 'individuals':
                return count($audience_data['individuals'] ?? array());
                
            default:
                return 0;
        }
    }

    /**
     * Count all users with phone numbers
     */
    private function count_all_users() {
        $users = get_users(array(
            'meta_query' => array(
                array(
                    'key' => 'phone_number',
                    'value' => '',
                    'compare' => '!='
                )
            ),
            'fields' => 'ID'
        ));
        
        return count($users);
    }

    /**
     * Count users by role
     */
    private function count_role_users($roles) {
        if (empty($roles)) {
            return 0;
        }

        $count = 0;
        foreach ($roles as $role) {
            $users = get_users(array(
                'role' => $role,
                'meta_query' => array(
                    array(
                        'key' => 'phone_number',
                        'value' => '',
                        'compare' => '!='
                    )
                ),
                'fields' => 'ID'
            ));
            $count += count($users);
        }

        return $count;
    }

    /**
     * Count users by class
     */
    private function count_class_users($classes) {
        if (empty($classes)) {
            return 0;
        }

        $count = 0;
        foreach ($classes as $class_id) {
            $students = get_posts(array(
                'post_type' => 'sms_students',
                'posts_per_page' => -1,
                'meta_query' => array(
                    array(
                        'key' => 'assigned_class',
                        'value' => $class_id,
                        'compare' => '='
                    )
                ),
                'fields' => 'ids'
            ));
            $count += count($students);
        }

        return $count;
    }

    /**
     * Count parents of class
     */
    private function count_parents_of_class($classes) {
        if (empty($classes)) {
            return 0;
        }

        $count = 0;
        foreach ($classes as $class_id) {
            $students = get_posts(array(
                'post_type' => 'sms_students',
                'posts_per_page' => -1,
                'meta_query' => array(
                    array(
                        'key' => 'assigned_class',
                        'value' => $class_id,
                        'compare' => '='
                    )
                ),
                'fields' => 'ids'
            ));
            
            foreach ($students as $student_id) {
                $parent_details = get_field('parent_details', $student_id);
                if ($parent_details && !empty($parent_details['phone'])) {
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Add custom row actions to notice posts
     */
    public function add_notice_row_actions($actions, $post) {
        if ($post->post_type === 'sms_notices') {
            $notice_status = get_field('notice_status', $post->ID);
            
            if ($notice_status === 'active') {
                $actions['send_notification'] = sprintf(
                    '<a href="#" class="send-notice-notification" data-notice-id="%d">%s</a>',
                    $post->ID,
                    __('Send Notification', 'school-management-system')
                );
            }
            
            if (in_array($notice_status, array('active', 'expired'))) {
                $actions['archive'] = sprintf(
                    '<a href="%s">%s</a>',
                    wp_nonce_url(
                        admin_url('admin.php?page=sms-notices&action=archive&notice_id=' . $post->ID),
                        'archive_notice_' . $post->ID
                    ),
                    __('Archive', 'school-management-system')
                );
            }
        }
        
        return $actions;
    }
}

// Initialize the notice manager
new SMS_Notice_Manager();