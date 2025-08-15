<?php
/**
 * Notices Custom Post Type
 *
 * @package SchoolManagementSystem
 * @subpackage PostTypes
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class SMS_Notices_CPT
 * 
 * Handles the notices custom post type registration and functionality
 */
class SMS_Notices_CPT {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'register_post_type'));
        add_action('acf/init', array($this, 'register_acf_fields'));
        add_action('wp_ajax_sms_send_notice', array($this, 'send_notice_to_audience'));
        add_action('wp_ajax_sms_get_audience_count', array($this, 'get_audience_count'));
        add_action('wp_ajax_sms_archive_expired_notices', array($this, 'archive_expired_notices'));
        add_filter('manage_sms_notices_posts_columns', array($this, 'set_custom_columns'));
        add_action('manage_sms_notices_posts_custom_column', array($this, 'custom_column_content'), 10, 2);
        add_action('save_post_sms_notices', array($this, 'handle_notice_save'), 10, 2);
        add_action('wp_loaded', array($this, 'schedule_expiry_check'));
        add_action('sms_check_expired_notices', array($this, 'check_and_expire_notices'));
    }

    /**
     * Register the notices custom post type
     */
    public function register_post_type() {
        $labels = array(
            'name'                  => _x('Notices', 'Post Type General Name', 'school-management-system'),
            'singular_name'         => _x('Notice', 'Post Type Singular Name', 'school-management-system'),
            'menu_name'             => __('Notices', 'school-management-system'),
            'name_admin_bar'        => __('Notice', 'school-management-system'),
            'archives'              => __('Notice Archives', 'school-management-system'),
            'attributes'            => __('Notice Attributes', 'school-management-system'),
            'parent_item_colon'     => __('Parent Notice:', 'school-management-system'),
            'all_items'             => __('All Notices', 'school-management-system'),
            'add_new_item'          => __('Add New Notice', 'school-management-system'),
            'add_new'               => __('Add New', 'school-management-system'),
            'new_item'              => __('New Notice', 'school-management-system'),
            'edit_item'             => __('Edit Notice', 'school-management-system'),
            'update_item'           => __('Update Notice', 'school-management-system'),
            'view_item'             => __('View Notice', 'school-management-system'),
            'view_items'            => __('View Notices', 'school-management-system'),
            'search_items'          => __('Search Notices', 'school-management-system'),
            'not_found'             => __('Not found', 'school-management-system'),
            'not_found_in_trash'    => __('Not found in Trash', 'school-management-system'),
            'featured_image'        => __('Featured Image', 'school-management-system'),
            'set_featured_image'    => __('Set featured image', 'school-management-system'),
            'remove_featured_image' => __('Remove featured image', 'school-management-system'),
            'use_featured_image'    => __('Use as featured image', 'school-management-system'),
            'insert_into_item'      => __('Insert into notice', 'school-management-system'),
            'uploaded_to_this_item' => __('Uploaded to this notice', 'school-management-system'),
            'items_list'            => __('Notices list', 'school-management-system'),
            'items_list_navigation' => __('Notices list navigation', 'school-management-system'),
            'filter_items_list'     => __('Filter notices list', 'school-management-system'),
        );

        $args = array(
            'label'                 => __('Notice', 'school-management-system'),
            'description'           => __('School notices and announcements', 'school-management-system'),
            'labels'                => $labels,
            'supports'              => array('title', 'editor', 'author', 'thumbnail', 'custom-fields'),
            'taxonomies'            => array('sms_notice_categories'),
            'hierarchical'          => false,
            'public'                => true,
            'show_ui'               => true,
            'show_in_menu'          => true,
            'menu_position'         => 27,
            'menu_icon'             => 'dashicons-megaphone',
            'show_in_admin_bar'     => true,
            'show_in_nav_menus'     => true,
            'can_export'            => true,
            'has_archive'           => true,
            'exclude_from_search'   => false,
            'publicly_queryable'    => true,
            'capability_type'       => 'post',
            'capabilities'          => array(
                'create_posts'       => 'manage_notices',
                'edit_posts'         => 'manage_notices',
                'edit_others_posts'  => 'manage_notices',
                'publish_posts'      => 'manage_notices',
                'read_private_posts' => 'manage_notices',
                'delete_posts'       => 'manage_notices',
                'delete_others_posts' => 'manage_notices',
            ),
            'show_in_rest'          => true,
            'rest_base'             => 'notices',
        );

        register_post_type('sms_notices', $args);
    }

    /**
     * Register ACF field groups for notices
     */
    public function register_acf_fields() {
        if (!function_exists('acf_add_local_field_group')) {
            return;
        }

        acf_add_local_field_group(array(
            'key' => 'group_notice_details',
            'title' => 'Notice Details',
            'fields' => array(
                array(
                    'key' => 'field_notice_priority',
                    'label' => 'Priority Level',
                    'name' => 'notice_priority',
                    'type' => 'select',
                    'instructions' => 'Set the priority level for this notice',
                    'required' => 1,
                    'choices' => array(
                        'low' => 'Low',
                        'normal' => 'Normal',
                        'high' => 'High',
                        'urgent' => 'Urgent',
                        'emergency' => 'Emergency',
                    ),
                    'default_value' => 'normal',
                    'allow_null' => 0,
                    'multiple' => 0,
                    'ui' => 1,
                    'return_format' => 'value',
                ),
                array(
                    'key' => 'field_notice_expiry_date',
                    'label' => 'Expiry Date',
                    'name' => 'notice_expiry_date',
                    'type' => 'date_time_picker',
                    'instructions' => 'Date and time when this notice should expire (optional)',
                    'required' => 0,
                    'display_format' => 'd/m/Y g:i a',
                    'return_format' => 'Y-m-d H:i:s',
                    'first_day' => 1,
                ),
                array(
                    'key' => 'field_notice_status',
                    'label' => 'Notice Status',
                    'name' => 'notice_status',
                    'type' => 'select',
                    'instructions' => 'Current status of the notice',
                    'required' => 1,
                    'choices' => array(
                        'draft' => 'Draft',
                        'active' => 'Active',
                        'expired' => 'Expired',
                        'archived' => 'Archived',
                    ),
                    'default_value' => 'draft',
                    'allow_null' => 0,
                    'multiple' => 0,
                    'ui' => 1,
                    'return_format' => 'value',
                ),
                array(
                    'key' => 'field_notice_attachments',
                    'label' => 'Attachments',
                    'name' => 'notice_attachments',
                    'type' => 'gallery',
                    'instructions' => 'Upload files or images to attach to this notice',
                    'required' => 0,
                    'return_format' => 'array',
                    'preview_size' => 'medium',
                    'insert' => 'append',
                    'library' => 'all',
                    'min' => 0,
                    'max' => 10,
                ),
                array(
                    'key' => 'field_auto_expire',
                    'label' => 'Auto Expire',
                    'name' => 'auto_expire',
                    'type' => 'true_false',
                    'instructions' => 'Automatically expire this notice after the expiry date',
                    'required' => 0,
                    'message' => 'Enable automatic expiry',
                    'default_value' => 1,
                    'ui' => 1,
                ),
                array(
                    'key' => 'field_send_notifications',
                    'label' => 'Send Notifications',
                    'name' => 'send_notifications',
                    'type' => 'true_false',
                    'instructions' => 'Send SMS/email notifications to target audience',
                    'required' => 0,
                    'message' => 'Enable notifications',
                    'default_value' => 0,
                    'ui' => 1,
                ),
            ),
            'location' => array(
                array(
                    array(
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'sms_notices',
                    ),
                ),
            ),
            'menu_order' => 0,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
        ));

        // Target Audience Configuration
        acf_add_local_field_group(array(
            'key' => 'group_notice_audience',
            'title' => 'Target Audience',
            'fields' => array(
                array(
                    'key' => 'field_audience_type',
                    'label' => 'Audience Type',
                    'name' => 'audience_type',
                    'type' => 'select',
                    'instructions' => 'Select the type of audience for this notice',
                    'required' => 1,
                    'choices' => array(
                        'all' => 'All Users',
                        'roles' => 'Specific Roles',
                        'classes' => 'Specific Classes',
                        'individuals' => 'Specific Individuals',
                        'parents_of_class' => 'Parents of Specific Classes',
                        'custom' => 'Custom Selection',
                    ),
                    'default_value' => 'all',
                    'allow_null' => 0,
                    'multiple' => 0,
                    'ui' => 1,
                    'return_format' => 'value',
                ),
                array(
                    'key' => 'field_target_roles',
                    'label' => 'Target Roles',
                    'name' => 'target_roles',
                    'type' => 'checkbox',
                    'instructions' => 'Select the user roles to target',
                    'required' => 0,
                    'choices' => array(
                        'administrator' => 'Administrators',
                        'sms_teacher' => 'Teachers',
                        'sms_parent' => 'Parents',
                        'sms_student' => 'Students',
                    ),
                    'allow_custom' => 0,
                    'save_custom' => 0,
                    'toggle' => 1,
                    'return_format' => 'value',
                    'conditional_logic' => array(
                        array(
                            array(
                                'field' => 'field_audience_type',
                                'operator' => '==',
                                'value' => 'roles',
                            ),
                        ),
                    ),
                ),
                array(
                    'key' => 'field_target_classes',
                    'label' => 'Target Classes',
                    'name' => 'target_classes',
                    'type' => 'post_object',
                    'instructions' => 'Select the classes to target',
                    'required' => 0,
                    'post_type' => array('sms_classes'),
                    'taxonomy' => '',
                    'allow_null' => 0,
                    'multiple' => 1,
                    'return_format' => 'object',
                    'ui' => 1,
                    'conditional_logic' => array(
                        array(
                            array(
                                'field' => 'field_audience_type',
                                'operator' => '==',
                                'value' => 'classes',
                            ),
                        ),
                        array(
                            array(
                                'field' => 'field_audience_type',
                                'operator' => '==',
                                'value' => 'parents_of_class',
                            ),
                        ),
                    ),
                ),
                array(
                    'key' => 'field_target_individuals',
                    'label' => 'Target Individuals',
                    'name' => 'target_individuals',
                    'type' => 'user',
                    'instructions' => 'Select specific users to target',
                    'required' => 0,
                    'role' => '',
                    'allow_null' => 0,
                    'multiple' => 1,
                    'return_format' => 'object',
                    'conditional_logic' => array(
                        array(
                            array(
                                'field' => 'field_audience_type',
                                'operator' => '==',
                                'value' => 'individuals',
                            ),
                        ),
                    ),
                ),
                array(
                    'key' => 'field_audience_count',
                    'label' => 'Estimated Audience Count',
                    'name' => 'audience_count',
                    'type' => 'number',
                    'instructions' => 'Estimated number of people who will receive this notice',
                    'required' => 0,
                    'readonly' => 1,
                    'min' => 0,
                ),
            ),
            'location' => array(
                array(
                    array(
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'sms_notices',
                    ),
                ),
            ),
            'menu_order' => 1,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
        ));

        // Delivery Tracking
        acf_add_local_field_group(array(
            'key' => 'group_notice_delivery',
            'title' => 'Delivery Tracking',
            'fields' => array(
                array(
                    'key' => 'field_delivery_status',
                    'label' => 'Delivery Status',
                    'name' => 'delivery_status',
                    'type' => 'select',
                    'instructions' => 'Current delivery status',
                    'required' => 0,
                    'choices' => array(
                        'pending' => 'Pending',
                        'sending' => 'Sending',
                        'sent' => 'Sent',
                        'failed' => 'Failed',
                        'partial' => 'Partially Sent',
                    ),
                    'default_value' => 'pending',
                    'allow_null' => 0,
                    'multiple' => 0,
                    'ui' => 1,
                    'return_format' => 'value',
                    'readonly' => 1,
                ),
                array(
                    'key' => 'field_sent_count',
                    'label' => 'Successfully Sent',
                    'name' => 'sent_count',
                    'type' => 'number',
                    'instructions' => 'Number of successful deliveries',
                    'required' => 0,
                    'readonly' => 1,
                    'min' => 0,
                ),
                array(
                    'key' => 'field_failed_count',
                    'label' => 'Failed Deliveries',
                    'name' => 'failed_count',
                    'type' => 'number',
                    'instructions' => 'Number of failed deliveries',
                    'required' => 0,
                    'readonly' => 1,
                    'min' => 0,
                ),
                array(
                    'key' => 'field_last_sent',
                    'label' => 'Last Sent',
                    'name' => 'last_sent',
                    'type' => 'date_time_picker',
                    'instructions' => 'Last time notifications were sent',
                    'required' => 0,
                    'display_format' => 'd/m/Y g:i a',
                    'return_format' => 'Y-m-d H:i:s',
                    'first_day' => 1,
                    'readonly' => 1,
                ),
                array(
                    'key' => 'field_delivery_log',
                    'label' => 'Delivery Log',
                    'name' => 'delivery_log',
                    'type' => 'textarea',
                    'instructions' => 'Detailed delivery log (JSON format)',
                    'required' => 0,
                    'rows' => 4,
                    'readonly' => 1,
                ),
            ),
            'location' => array(
                array(
                    array(
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'sms_notices',
                    ),
                ),
            ),
            'menu_order' => 2,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
        ));
    }

    /**
     * Set custom columns for notices list
     */
    public function set_custom_columns($columns) {
        $new_columns = array();
        $new_columns['cb'] = $columns['cb'];
        $new_columns['title'] = __('Notice Title', 'school-management-system');
        $new_columns['priority'] = __('Priority', 'school-management-system');
        $new_columns['audience'] = __('Target Audience', 'school-management-system');
        $new_columns['status'] = __('Status', 'school-management-system');
        $new_columns['expiry'] = __('Expires', 'school-management-system');
        $new_columns['delivery'] = __('Delivery', 'school-management-system');
        $new_columns['author'] = __('Created By', 'school-management-system');
        $new_columns['date'] = __('Created', 'school-management-system');
        
        return $new_columns;
    }

    /**
     * Display custom column content
     */
    public function custom_column_content($column, $post_id) {
        switch ($column) {
            case 'priority':
                $priority = get_field('notice_priority', $post_id);
                $priority_labels = array(
                    'low' => '<span class="priority-low">Low</span>',
                    'normal' => '<span class="priority-normal">Normal</span>',
                    'high' => '<span class="priority-high">High</span>',
                    'urgent' => '<span class="priority-urgent">Urgent</span>',
                    'emergency' => '<span class="priority-emergency">Emergency</span>',
                );
                echo $priority_labels[$priority] ?? '—';
                break;
                
            case 'audience':
                $audience_type = get_field('audience_type', $post_id);
                $audience_labels = array(
                    'all' => 'All Users',
                    'roles' => 'Specific Roles',
                    'classes' => 'Specific Classes',
                    'individuals' => 'Individuals',
                    'parents_of_class' => 'Parents of Classes',
                    'custom' => 'Custom',
                );
                echo $audience_labels[$audience_type] ?? '—';
                
                $count = get_field('audience_count', $post_id);
                if ($count) {
                    echo '<br><small>(' . $count . ' recipients)</small>';
                }
                break;
                
            case 'status':
                $status = get_field('notice_status', $post_id);
                $status_labels = array(
                    'draft' => '<span class="status-draft">Draft</span>',
                    'active' => '<span class="status-active">Active</span>',
                    'expired' => '<span class="status-expired">Expired</span>',
                    'archived' => '<span class="status-archived">Archived</span>',
                );
                echo $status_labels[$status] ?? '—';
                break;
                
            case 'expiry':
                $expiry_date = get_field('notice_expiry_date', $post_id);
                if ($expiry_date) {
                    $expiry_timestamp = strtotime($expiry_date);
                    $now = current_time('timestamp');
                    
                    if ($expiry_timestamp < $now) {
                        echo '<span class="expired">' . date('d/m/Y H:i', $expiry_timestamp) . '</span>';
                    } else {
                        echo date('d/m/Y H:i', $expiry_timestamp);
                    }
                } else {
                    echo '—';
                }
                break;
                
            case 'delivery':
                $delivery_status = get_field('delivery_status', $post_id);
                $sent_count = get_field('sent_count', $post_id);
                $failed_count = get_field('failed_count', $post_id);
                
                if ($delivery_status) {
                    echo ucfirst($delivery_status);
                    if ($sent_count || $failed_count) {
                        echo '<br><small>Sent: ' . ($sent_count ?: 0) . ' | Failed: ' . ($failed_count ?: 0) . '</small>';
                    }
                } else {
                    echo '—';
                }
                break;
        }
    }

    /**
     * Handle notice save actions
     */
    public function handle_notice_save($post_id, $post) {
        // Skip if this is an autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Skip if user doesn't have permission
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Update audience count when audience settings change
        $this->update_audience_count($post_id);

        // If notice is published and notifications are enabled, send them
        if ($post->post_status === 'publish') {
            $send_notifications = get_field('send_notifications', $post_id);
            $delivery_status = get_field('delivery_status', $post_id);
            
            if ($send_notifications && $delivery_status === 'pending') {
                // Schedule notification sending
                wp_schedule_single_event(time() + 60, 'sms_send_notice_notifications', array($post_id));
            }
        }
    }

    /**
     * Send notice to target audience via AJAX
     */
    public function send_notice_to_audience() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_send_notice_nonce')) {
            wp_die(__('Security check failed', 'school-management-system'));
        }

        // Check user capabilities
        if (!current_user_can('manage_notices')) {
            wp_die(__('You do not have permission to send notices', 'school-management-system'));
        }

        $notice_id = intval($_POST['notice_id']);
        
        if (!$notice_id) {
            wp_send_json_error(__('Invalid notice ID', 'school-management-system'));
        }

        $result = $this->send_notice_notifications($notice_id);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * Get audience count via AJAX
     */
    public function get_audience_count() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_audience_count_nonce')) {
            wp_die(__('Security check failed', 'school-management-system'));
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
     * Archive expired notices via AJAX
     */
    public function archive_expired_notices() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_archive_notices_nonce')) {
            wp_die(__('Security check failed', 'school-management-system'));
        }

        // Check user capabilities
        if (!current_user_can('manage_notices')) {
            wp_die(__('You do not have permission to archive notices', 'school-management-system'));
        }

        $archived_count = $this->check_and_expire_notices();
        
        wp_send_json_success(array(
            'archived_count' => $archived_count,
            'message' => sprintf(__('%d notices have been archived', 'school-management-system'), $archived_count)
        ));
    }

    /**
     * Schedule expiry check
     */
    public function schedule_expiry_check() {
        if (!wp_next_scheduled('sms_check_expired_notices')) {
            wp_schedule_event(time(), 'hourly', 'sms_check_expired_notices');
        }
    }

    /**
     * Check and expire notices
     */
    public function check_and_expire_notices() {
        $args = array(
            'post_type' => 'sms_notices',
            'posts_per_page' => -1,
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => 'notice_status',
                    'value' => 'active',
                    'compare' => '='
                ),
                array(
                    'key' => 'auto_expire',
                    'value' => '1',
                    'compare' => '='
                ),
                array(
                    'key' => 'notice_expiry_date',
                    'value' => current_time('mysql'),
                    'compare' => '<',
                    'type' => 'DATETIME'
                )
            )
        );

        $expired_notices = get_posts($args);
        $archived_count = 0;

        foreach ($expired_notices as $notice) {
            update_field('notice_status', 'expired', $notice->ID);
            $archived_count++;
            
            // Trigger action for expired notice
            do_action('sms_notice_expired', $notice->ID);
        }

        return $archived_count;
    }

    /**
     * Send notice notifications
     */
    private function send_notice_notifications($notice_id) {
        $notice = get_post($notice_id);
        
        if (!$notice || $notice->post_type !== 'sms_notices') {
            return array('success' => false, 'message' => __('Invalid notice', 'school-management-system'));
        }

        // Update delivery status
        update_field('delivery_status', 'sending', $notice_id);

        // Get target audience
        $recipients = $this->get_notice_recipients($notice_id);
        
        if (empty($recipients)) {
            update_field('delivery_status', 'failed', $notice_id);
            return array('success' => false, 'message' => __('No recipients found', 'school-management-system'));
        }

        // Prepare message content
        $message = $this->prepare_notice_message($notice);
        
        // Send notifications (SMS/Email)
        $delivery_results = array();
        $sent_count = 0;
        $failed_count = 0;

        foreach ($recipients as $recipient) {
            try {
                // Send SMS if phone number available
                if (!empty($recipient['phone'])) {
                    $sms_result = $this->send_sms_notification($recipient['phone'], $message);
                    $delivery_results[] = array(
                        'recipient' => $recipient,
                        'type' => 'sms',
                        'status' => $sms_result ? 'sent' : 'failed',
                        'timestamp' => current_time('mysql')
                    );
                    
                    if ($sms_result) {
                        $sent_count++;
                    } else {
                        $failed_count++;
                    }
                }

                // Send email if email available
                if (!empty($recipient['email'])) {
                    $email_result = $this->send_email_notification($recipient['email'], $notice->post_title, $message);
                    $delivery_results[] = array(
                        'recipient' => $recipient,
                        'type' => 'email',
                        'status' => $email_result ? 'sent' : 'failed',
                        'timestamp' => current_time('mysql')
                    );
                    
                    if ($email_result) {
                        $sent_count++;
                    } else {
                        $failed_count++;
                    }
                }
            } catch (Exception $e) {
                $failed_count++;
                $delivery_results[] = array(
                    'recipient' => $recipient,
                    'type' => 'error',
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                    'timestamp' => current_time('mysql')
                );
            }
        }

        // Update delivery tracking
        $final_status = ($failed_count === 0) ? 'sent' : (($sent_count > 0) ? 'partial' : 'failed');
        
        update_field('delivery_status', $final_status, $notice_id);
        update_field('sent_count', $sent_count, $notice_id);
        update_field('failed_count', $failed_count, $notice_id);
        update_field('last_sent', current_time('mysql'), $notice_id);
        update_field('delivery_log', json_encode($delivery_results), $notice_id);

        return array(
            'success' => true,
            'message' => sprintf(__('Notice sent to %d recipients (%d successful, %d failed)', 'school-management-system'), 
                               count($recipients), $sent_count, $failed_count),
            'sent_count' => $sent_count,
            'failed_count' => $failed_count,
            'total_recipients' => count($recipients)
        );
    }

    /**
     * Get notice recipients based on target audience
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
            'fields' => array('ID', 'display_name', 'user_email')
        ));

        $recipients = array();
        foreach ($users as $user) {
            $phone = get_user_meta($user->ID, 'phone_number', true);
            $recipients[] = array(
                'user_id' => $user->ID,
                'name' => $user->display_name,
                'email' => $user->user_email,
                'phone' => $phone
            );
        }

        return $recipients;
    }

    /**
     * Get recipients by roles
     */
    private function get_role_recipients($roles) {
        if (empty($roles)) {
            return array();
        }

        $users = get_users(array(
            'role__in' => $roles,
            'fields' => array('ID', 'display_name', 'user_email')
        ));

        $recipients = array();
        foreach ($users as $user) {
            $phone = get_user_meta($user->ID, 'phone_number', true);
            $recipients[] = array(
                'user_id' => $user->ID,
                'name' => $user->display_name,
                'email' => $user->user_email,
                'phone' => $phone
            );
        }

        return $recipients;
    }

    /**
     * Get recipients by classes (students in classes)
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
                        'key' => 'current_class',
                        'value' => $class->ID,
                        'compare' => '='
                    )
                )
            ));

            foreach ($students as $student) {
                $student_user_id = get_field('student_user_id', $student->ID);
                if ($student_user_id) {
                    $user = get_userdata($student_user_id);
                    if ($user) {
                        $phone = get_user_meta($user->ID, 'phone_number', true);
                        $recipients[] = array(
                            'user_id' => $user->ID,
                            'name' => $user->display_name,
                            'email' => $user->user_email,
                            'phone' => $phone,
                            'student_id' => $student->ID,
                            'class_id' => $class->ID
                        );
                    }
                }
            }
        }

        return $recipients;
    }

    /**
     * Get parents of students in specific classes
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
                        'key' => 'current_class',
                        'value' => $class->ID,
                        'compare' => '='
                    )
                )
            ));

            foreach ($students as $student) {
                // Get parent user IDs for this student
                $parent_user_ids = get_field('parent_user_ids', $student->ID);
                if ($parent_user_ids) {
                    foreach ($parent_user_ids as $parent_user_id) {
                        $user = get_userdata($parent_user_id);
                        if ($user) {
                            $phone = get_user_meta($user->ID, 'phone_number', true);
                            $recipients[] = array(
                                'user_id' => $user->ID,
                                'name' => $user->display_name,
                                'email' => $user->user_email,
                                'phone' => $phone,
                                'student_id' => $student->ID,
                                'class_id' => $class->ID
                            );
                        }
                    }
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
            $recipients[] = array(
                'user_id' => $user->ID,
                'name' => $user->display_name,
                'email' => $user->user_email,
                'phone' => $phone
            );
        }

        return $recipients;
    }

    /**
     * Calculate audience count
     */
    private function calculate_audience_count($audience_type, $audience_data) {
        switch ($audience_type) {
            case 'all':
                return count(get_users());
                
            case 'roles':
                if (empty($audience_data['roles'])) return 0;
                return count(get_users(array('role__in' => $audience_data['roles'])));
                
            case 'classes':
                if (empty($audience_data['classes'])) return 0;
                $count = 0;
                foreach ($audience_data['classes'] as $class_id) {
                    $students = get_posts(array(
                        'post_type' => 'sms_students',
                        'posts_per_page' => -1,
                        'meta_query' => array(
                            array(
                                'key' => 'current_class',
                                'value' => $class_id,
                                'compare' => '='
                            )
                        )
                    ));
                    $count += count($students);
                }
                return $count;
                
            case 'individuals':
                return count($audience_data['individuals'] ?? array());
                
            default:
                return 0;
        }
    }

    /**
     * Update audience count for a notice
     */
    private function update_audience_count($notice_id) {
        $audience_type = get_field('audience_type', $notice_id);
        $audience_data = array();

        switch ($audience_type) {
            case 'roles':
                $audience_data['roles'] = get_field('target_roles', $notice_id);
                break;
            case 'classes':
            case 'parents_of_class':
                $classes = get_field('target_classes', $notice_id);
                $audience_data['classes'] = $classes ? array_map(function($class) { return $class->ID; }, $classes) : array();
                break;
            case 'individuals':
                $individuals = get_field('target_individuals', $notice_id);
                $audience_data['individuals'] = $individuals ? array_map(function($user) { return $user->ID; }, $individuals) : array();
                break;
        }

        $count = $this->calculate_audience_count($audience_type, $audience_data);
        update_field('audience_count', $count, $notice_id);
    }

    /**
     * Prepare notice message for notifications
     */
    private function prepare_notice_message($notice) {
        $content = strip_tags($notice->post_content);
        $content = wp_trim_words($content, 50, '...');
        
        return sprintf(
            "%s\n\n%s\n\n- %s",
            $notice->post_title,
            $content,
            get_bloginfo('name')
        );
    }

    /**
     * Send SMS notification (placeholder)
     */
    private function send_sms_notification($phone, $message) {
        // This would integrate with SMS service (Africastalking)
        // For now, return true as placeholder
        return apply_filters('sms_send_notification', true, $phone, $message);
    }

    /**
     * Send email notification
     */
    private function send_email_notification($email, $subject, $message) {
        $headers = array('Content-Type: text/html; charset=UTF-8');
        return wp_mail($email, $subject, nl2br($message), $headers);
    }

    /**
     * Get active notices for display
     */
    public function get_active_notices($limit = 10, $priority = null) {
        $meta_query = array(
            array(
                'key' => 'notice_status',
                'value' => 'active',
                'compare' => '='
            )
        );

        if ($priority) {
            $meta_query[] = array(
                'key' => 'notice_priority',
                'value' => $priority,
                'compare' => '='
            );
        }

        $args = array(
            'post_type' => 'sms_notices',
            'posts_per_page' => $limit,
            'meta_query' => $meta_query,
            'orderby' => 'date',
            'order' => 'DESC'
        );

        return get_posts($args);
    }
}

// Initialize the class
new SMS_Notices_CPT();