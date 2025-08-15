<?php
/**
 * Bulk operations for parent-student relationship management.
 *
 * @package    School_Management_System
 * @subpackage School_Management_System/includes/user-roles
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Bulk parent operations class.
 */
class SMS_Bulk_Parent_Operations extends SMS_Base {

    /**
     * Initialize bulk parent operations.
     */
    public function __construct() {
        parent::__construct();
        
        // Add admin page for bulk operations
        add_action('admin_menu', [$this, 'add_admin_menu']);
        
        // Handle bulk operation requests
        add_action('admin_post_sms_bulk_create_parent_accounts', [$this, 'handle_bulk_create_parent_accounts']);
        add_action('admin_post_sms_bulk_link_parents', [$this, 'handle_bulk_link_parents']);
        add_action('admin_post_sms_bulk_send_parent_notifications', [$this, 'handle_bulk_send_notifications']);
        
        // Add AJAX handlers
        add_action('wp_ajax_sms_get_bulk_operation_status', [$this, 'ajax_get_bulk_operation_status']);
    }

    /**
     * Add admin menu for bulk operations.
     */
    public function add_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=sms_students',
            __('Bulk Parent Operations', 'school-management-system'),
            __('Bulk Parent Ops', 'school-management-system'),
            'manage_students',
            'sms-bulk-parent-operations',
            [$this, 'render_admin_page']
        );
    }

    /**
     * Render admin page for bulk operations.
     */
    public function render_admin_page() {
        // Get statistics
        $stats = $this->get_parent_relationship_stats();
        ?>
        <div class="wrap">
            <h1><?php _e('Bulk Parent Operations', 'school-management-system'); ?></h1>
            
            <div class="notice notice-info">
                <p><?php _e('Use these tools to manage parent accounts and relationships in bulk.', 'school-management-system'); ?></p>
            </div>

            <!-- Statistics -->
            <div class="card">
                <h2><?php _e('Current Statistics', 'school-management-system'); ?></h2>
                <table class="widefat">
                    <tr>
                        <td><?php _e('Total Students', 'school-management-system'); ?></td>
                        <td><strong><?php echo esc_html($stats['total_students']); ?></strong></td>
                    </tr>
                    <tr>
                        <td><?php _e('Students with Parent Accounts', 'school-management-system'); ?></td>
                        <td><strong><?php echo esc_html($stats['students_with_parent_accounts']); ?></strong></td>
                    </tr>
                    <tr>
                        <td><?php _e('Students without Parent Accounts', 'school-management-system'); ?></td>
                        <td><strong><?php echo esc_html($stats['students_without_parent_accounts']); ?></strong></td>
                    </tr>
                    <tr>
                        <td><?php _e('Total Parent Users', 'school-management-system'); ?></td>
                        <td><strong><?php echo esc_html($stats['total_parent_users']); ?></strong></td>
                    </tr>
                    <tr>
                        <td><?php _e('Unlinked Parent Emails', 'school-management-system'); ?></td>
                        <td><strong><?php echo esc_html($stats['unlinked_parent_emails']); ?></strong></td>
                    </tr>
                </table>
            </div>

            <!-- Bulk Create Parent Accounts -->
            <div class="card">
                <h2><?php _e('Bulk Create Parent Accounts', 'school-management-system'); ?></h2>
                <p><?php _e('Create WordPress user accounts for all parents who don\'t have accounts yet.', 'school-management-system'); ?></p>
                
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <?php wp_nonce_field('sms_bulk_create_parent_accounts'); ?>
                    <input type="hidden" name="action" value="sms_bulk_create_parent_accounts">
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Send Welcome Emails', 'school-management-system'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="send_welcome_emails" value="1" checked>
                                    <?php _e('Send welcome emails with login credentials to new parent accounts', 'school-management-system'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Specific Students', 'school-management-system'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="specific_students" value="1" id="specific_students_checkbox">
                                    <?php _e('Only create accounts for specific students (comma-separated admission numbers)', 'school-management-system'); ?>
                                </label>
                                <br>
                                <textarea name="student_admission_numbers" rows="3" cols="50" style="display:none;" id="student_admission_numbers"></textarea>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button(__('Create Parent Accounts', 'school-management-system'), 'primary', 'submit', false); ?>
                </form>
            </div>

            <!-- Bulk Link Existing Parents -->
            <div class="card">
                <h2><?php _e('Bulk Link Existing Parents', 'school-management-system'); ?></h2>
                <p><?php _e('Link existing WordPress users to students based on email addresses.', 'school-management-system'); ?></p>
                
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <?php wp_nonce_field('sms_bulk_link_parents'); ?>
                    <input type="hidden" name="action" value="sms_bulk_link_parents">
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Assign Parent Role', 'school-management-system'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="assign_parent_role" value="1" checked>
                                    <?php _e('Automatically assign parent role to linked users', 'school-management-system'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Send Notification', 'school-management-system'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="send_link_notification" value="1" checked>
                                    <?php _e('Send notification emails to newly linked parents', 'school-management-system'); ?>
                                </label>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button(__('Link Existing Parents', 'school-management-system'), 'primary', 'submit', false); ?>
                </form>
            </div>

            <!-- Bulk Send Notifications -->
            <div class="card">
                <h2><?php _e('Bulk Send Parent Notifications', 'school-management-system'); ?></h2>
                <p><?php _e('Send notifications to all parents or specific groups.', 'school-management-system'); ?></p>
                
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <?php wp_nonce_field('sms_bulk_send_parent_notifications'); ?>
                    <input type="hidden" name="action" value="sms_bulk_send_parent_notifications">
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Notification Type', 'school-management-system'); ?></th>
                            <td>
                                <select name="notification_type" required>
                                    <option value=""><?php _e('Select notification type', 'school-management-system'); ?></option>
                                    <option value="welcome"><?php _e('Welcome Message', 'school-management-system'); ?></option>
                                    <option value="account_info"><?php _e('Account Information Reminder', 'school-management-system'); ?></option>
                                    <option value="system_update"><?php _e('System Update Notification', 'school-management-system'); ?></option>
                                    <option value="custom"><?php _e('Custom Message', 'school-management-system'); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Target Audience', 'school-management-system'); ?></th>
                            <td>
                                <select name="target_audience">
                                    <option value="all"><?php _e('All Parents', 'school-management-system'); ?></option>
                                    <option value="new_accounts"><?php _e('Recently Created Accounts (Last 30 days)', 'school-management-system'); ?></option>
                                    <option value="inactive"><?php _e('Inactive Parents (Never logged in)', 'school-management-system'); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr id="custom_message_row" style="display:none;">
                            <th scope="row"><?php _e('Custom Message', 'school-management-system'); ?></th>
                            <td>
                                <textarea name="custom_message" rows="5" cols="50" placeholder="<?php _e('Enter your custom message here...', 'school-management-system'); ?>"></textarea>
                                <p class="description"><?php _e('Available placeholders: {parent_name}, {student_name}, {school_name}, {login_url}', 'school-management-system'); ?></p>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button(__('Send Notifications', 'school-management-system'), 'primary', 'submit', false); ?>
                </form>
            </div>

            <!-- Operation Status -->
            <div id="operation-status" style="display:none;">
                <div class="card">
                    <h2><?php _e('Operation Status', 'school-management-system'); ?></h2>
                    <div id="status-content">
                        <p><?php _e('Operation in progress...', 'school-management-system'); ?></p>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: 0%;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Show/hide specific students textarea
            $('#specific_students_checkbox').change(function() {
                if ($(this).is(':checked')) {
                    $('#student_admission_numbers').show();
                } else {
                    $('#student_admission_numbers').hide();
                }
            });
            
            // Show/hide custom message textarea
            $('select[name="notification_type"]').change(function() {
                if ($(this).val() === 'custom') {
                    $('#custom_message_row').show();
                } else {
                    $('#custom_message_row').hide();
                }
            });
        });
        </script>

        <style>
        .progress-bar {
            width: 100%;
            height: 20px;
            background-color: #f0f0f0;
            border-radius: 10px;
            overflow: hidden;
        }
        .progress-fill {
            height: 100%;
            background-color: #0073aa;
            transition: width 0.3s ease;
        }
        </style>
        <?php
    }

    /**
     * Get parent relationship statistics.
     */
    private function get_parent_relationship_stats() {
        $students = get_posts([
            'post_type' => 'sms_students',
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ]);

        $total_students = count($students);
        $students_with_accounts = 0;
        $unlinked_emails = 0;

        foreach ($students as $student) {
            $parent_details = get_field('parent_details', $student->ID);
            $has_account = false;
            
            if (is_array($parent_details)) {
                foreach ($parent_details as $parent) {
                    if (isset($parent['parent_user_id']) && $parent['parent_user_id']) {
                        $has_account = true;
                        break;
                    } elseif (!empty($parent['parent_email'])) {
                        $unlinked_emails++;
                    }
                }
            }
            
            if ($has_account) {
                $students_with_accounts++;
            }
        }

        $total_parent_users = count(get_users(['role' => 'parent']));

        return [
            'total_students' => $total_students,
            'students_with_parent_accounts' => $students_with_accounts,
            'students_without_parent_accounts' => $total_students - $students_with_accounts,
            'total_parent_users' => $total_parent_users,
            'unlinked_parent_emails' => $unlinked_emails
        ];
    }

    /**
     * Handle bulk create parent accounts.
     */
    public function handle_bulk_create_parent_accounts() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['_wpnonce'], 'sms_bulk_create_parent_accounts')) {
            wp_die('Security check failed');
        }

        // Check permissions
        if (!current_user_can('manage_students')) {
            wp_die('Insufficient permissions');
        }

        $send_welcome_emails = isset($_POST['send_welcome_emails']);
        $specific_students = isset($_POST['specific_students']);
        $student_admission_numbers = [];

        if ($specific_students && !empty($_POST['student_admission_numbers'])) {
            $admission_numbers = explode(',', $_POST['student_admission_numbers']);
            $student_admission_numbers = array_map('trim', $admission_numbers);
        }

        // Get students to process
        $students_query = [
            'post_type' => 'sms_students',
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ];

        if (!empty($student_admission_numbers)) {
            $students_query['meta_query'] = [
                [
                    'key' => 'admission_number',
                    'value' => $student_admission_numbers,
                    'compare' => 'IN'
                ]
            ];
        }

        $students = get_posts($students_query);
        $automation = new SMS_Role_Automation();
        $result = $automation->bulk_create_parent_accounts(wp_list_pluck($students, 'ID'));

        // Redirect with results
        $redirect_url = add_query_arg([
            'page' => 'sms-bulk-parent-operations',
            'operation' => 'create_accounts',
            'created' => $result['created_count'],
            'errors' => count($result['errors'])
        ], admin_url('edit.php?post_type=sms_students'));

        wp_redirect($redirect_url);
        exit;
    }

    /**
     * Handle bulk link parents.
     */
    public function handle_bulk_link_parents() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['_wpnonce'], 'sms_bulk_link_parents')) {
            wp_die('Security check failed');
        }

        // Check permissions
        if (!current_user_can('manage_students')) {
            wp_die('Insufficient permissions');
        }

        $assign_parent_role = isset($_POST['assign_parent_role']);
        $send_notification = isset($_POST['send_link_notification']);

        $parent_manager = new SMS_Parent_Student_Manager();
        $result = $parent_manager->bulk_link_parents_by_email();

        // Redirect with results
        $redirect_url = add_query_arg([
            'page' => 'sms-bulk-parent-operations',
            'operation' => 'link_parents',
            'linked' => $result['linked_count'],
            'errors' => count($result['errors'])
        ], admin_url('edit.php?post_type=sms_students'));

        wp_redirect($redirect_url);
        exit;
    }

    /**
     * Handle bulk send notifications.
     */
    public function handle_bulk_send_notifications() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['_wpnonce'], 'sms_bulk_send_parent_notifications')) {
            wp_die('Security check failed');
        }

        // Check permissions
        if (!current_user_can('send_bulk_sms')) {
            wp_die('Insufficient permissions');
        }

        $notification_type = sanitize_text_field($_POST['notification_type']);
        $target_audience = sanitize_text_field($_POST['target_audience']);
        $custom_message = sanitize_textarea_field($_POST['custom_message']);

        $result = $this->send_bulk_parent_notifications($notification_type, $target_audience, $custom_message);

        // Redirect with results
        $redirect_url = add_query_arg([
            'page' => 'sms-bulk-parent-operations',
            'operation' => 'send_notifications',
            'sent' => $result['sent_count'],
            'errors' => count($result['errors'])
        ], admin_url('edit.php?post_type=sms_students'));

        wp_redirect($redirect_url);
        exit;
    }

    /**
     * Send bulk parent notifications.
     */
    private function send_bulk_parent_notifications($notification_type, $target_audience, $custom_message = '') {
        // Get target parents
        $parents = $this->get_target_parents($target_audience);
        
        $sent_count = 0;
        $errors = [];

        foreach ($parents as $parent) {
            $message = $this->get_notification_message($notification_type, $parent, $custom_message);
            $subject = $this->get_notification_subject($notification_type);

            $result = wp_mail($parent->user_email, $subject, $message);
            
            if ($result) {
                $sent_count++;
            } else {
                $errors[] = "Failed to send email to {$parent->user_email}";
            }
        }

        $this->log("Bulk parent notifications sent", 'info', [
            'notification_type' => $notification_type,
            'target_audience' => $target_audience,
            'sent_count' => $sent_count,
            'error_count' => count($errors)
        ]);

        return [
            'sent_count' => $sent_count,
            'errors' => $errors
        ];
    }

    /**
     * Get target parents based on audience selection.
     */
    private function get_target_parents($target_audience) {
        $query_args = ['role' => 'parent'];

        switch ($target_audience) {
            case 'new_accounts':
                $query_args['meta_query'] = [
                    [
                        'key' => 'sms_role_assigned_date',
                        'value' => date('Y-m-d H:i:s', strtotime('-30 days')),
                        'compare' => '>='
                    ]
                ];
                break;

            case 'inactive':
                $query_args['meta_query'] = [
                    [
                        'key' => 'sms_last_login',
                        'compare' => 'NOT EXISTS'
                    ]
                ];
                break;

            case 'all':
            default:
                // No additional filters
                break;
        }

        return get_users($query_args);
    }

    /**
     * Get notification message based on type.
     */
    private function get_notification_message($type, $parent, $custom_message = '') {
        $placeholders = [
            '{parent_name}' => $parent->display_name,
            '{school_name}' => get_bloginfo('name'),
            '{login_url}' => wp_login_url()
        ];

        // Get parent's children for student name placeholder
        $children = get_user_meta($parent->ID, 'sms_children', true);
        if (is_array($children) && !empty($children)) {
            $student_names = [];
            foreach ($children as $child_id) {
                $student_names[] = get_field('full_name', $child_id);
            }
            $placeholders['{student_name}'] = implode(', ', $student_names);
        } else {
            $placeholders['{student_name}'] = '';
        }

        $templates = [
            'welcome' => __('Dear {parent_name},

Welcome to {school_name}\'s parent portal! You can now access your child\'s school information online.

Student(s): {student_name}

Login at: {login_url}

Best regards,
{school_name}', 'school-management-system'),

            'account_info' => __('Dear {parent_name},

This is a reminder about your parent portal account for {school_name}.

You can access your child\'s information at: {login_url}

If you need help with your account, please contact the school office.

Best regards,
{school_name}', 'school-management-system'),

            'system_update' => __('Dear {parent_name},

We have updated our school management system with new features and improvements.

Please login to explore the new features: {login_url}

Best regards,
{school_name}', 'school-management-system'),

            'custom' => $custom_message
        ];

        $message = $templates[$type] ?? $templates['welcome'];
        
        return str_replace(array_keys($placeholders), array_values($placeholders), $message);
    }

    /**
     * Get notification subject based on type.
     */
    private function get_notification_subject($type) {
        $subjects = [
            'welcome' => __('Welcome to Parent Portal - %s', 'school-management-system'),
            'account_info' => __('Parent Portal Account Information - %s', 'school-management-system'),
            'system_update' => __('System Update Notification - %s', 'school-management-system'),
            'custom' => __('Important Message from %s', 'school-management-system')
        ];

        $subject_template = $subjects[$type] ?? $subjects['welcome'];
        
        return sprintf($subject_template, get_bloginfo('name'));
    }
}

// Initialize the class
new SMS_Bulk_Parent_Operations();