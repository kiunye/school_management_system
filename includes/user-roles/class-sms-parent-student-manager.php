<?php
/**
 * Parent-Student relationship management system.
 *
 * @package    School_Management_System
 * @subpackage School_Management_System/includes/user-roles
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Parent-Student relationship manager class.
 */
class SMS_Parent_Student_Manager extends SMS_Base {

    /**
     * Initialize the parent-student manager.
     */
    public function __construct() {
        parent::__construct();
        
        // Hook into student save to manage parent relationships
        add_action('acf/save_post', [$this, 'handle_parent_data_update'], 20);
        
        // Add parent management meta boxes
        add_action('add_meta_boxes', [$this, 'add_parent_management_meta_boxes']);
        
        // Handle AJAX requests for parent management
        add_action('wp_ajax_sms_link_parent_to_student', [$this, 'ajax_link_parent_to_student']);
        add_action('wp_ajax_sms_unlink_parent_from_student', [$this, 'ajax_unlink_parent_from_student']);
        add_action('wp_ajax_sms_search_parents', [$this, 'ajax_search_parents']);
        add_action('wp_ajax_sms_create_parent_account', [$this, 'ajax_create_parent_account']);
        
        // Add parent profile enhancements
        add_action('show_user_profile', [$this, 'add_parent_profile_fields']);
        add_action('edit_user_profile', [$this, 'add_parent_profile_fields']);
        add_action('personal_options_update', [$this, 'save_parent_profile_fields']);
        add_action('edit_user_profile_update', [$this, 'save_parent_profile_fields']);
        
        // Restrict parent access to only their children's data
        add_filter('pre_get_posts', [$this, 'restrict_parent_student_access']);
        add_filter('posts_where', [$this, 'filter_student_posts_for_parents'], 10, 2);
    }

    /**
     * Handle parent data updates when student is saved.
     */
    public function handle_parent_data_update($post_id) {
        // Only process student posts
        if (get_post_type($post_id) !== 'sms_students') {
            return;
        }

        // Skip if this is an autosave or revision
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }

        $parent_details = get_field('parent_details', $post_id);
        if (!is_array($parent_details) || empty($parent_details)) {
            return;
        }

        foreach ($parent_details as $index => $parent) {
            if (empty($parent['parent_email'])) {
                continue;
            }

            // Process parent account creation or linking
            $this->process_parent_relationship($parent, $index, $post_id);
        }

        // Update parent notification preferences
        $this->update_parent_notification_preferences($post_id);
    }

    /**
     * Process parent relationship (create account or link existing).
     */
    private function process_parent_relationship($parent_data, $index, $student_id) {
        $email = $parent_data['parent_email'];
        
        // Check if user account exists
        $existing_user = get_user_by('email', $email);
        
        if ($existing_user) {
            // Link existing user to student
            $this->link_existing_parent_to_student($existing_user->ID, $student_id, $index);
        } else {
            // Check if auto-creation is enabled
            $auto_create = apply_filters('sms_auto_create_parent_accounts', true);
            
            if ($auto_create) {
                $this->create_and_link_parent_account($parent_data, $student_id, $index);
            }
        }
    }

    /**
     * Link existing parent user to student.
     */
    private function link_existing_parent_to_student($parent_id, $student_id, $parent_index) {
        // Ensure user has parent role
        $user = get_userdata($parent_id);
        if (!in_array('parent', $user->roles)) {
            $user_roles = new SMS_User_Roles();
            $user_roles->assign_user_role($parent_id, 'parent');
        }

        // Get current children list
        $children = get_user_meta($parent_id, 'sms_children', true);
        if (!is_array($children)) {
            $children = [];
        }

        // Add student to children list if not already present
        if (!in_array($student_id, $children)) {
            $children[] = $student_id;
            update_user_meta($parent_id, 'sms_children', $children);
        }

        // Update parent details with user ID
        $parent_details = get_field('parent_details', $student_id);
        if (isset($parent_details[$parent_index])) {
            $parent_details[$parent_index]['parent_user_id'] = $parent_id;
            update_field('parent_details', $parent_details, $student_id);
        }

        // Log the relationship
        $this->log("Linked existing parent {$parent_id} to student {$student_id}", 'info', [
            'parent_id' => $parent_id,
            'student_id' => $student_id,
            'parent_email' => $user->user_email
        ]);

        // Trigger action
        do_action('sms_parent_linked_to_student', $parent_id, $student_id);

        return $parent_id;
    }

    /**
     * Create and link new parent account.
     */
    private function create_and_link_parent_account($parent_data, $student_id, $parent_index) {
        $automation = new SMS_Role_Automation();
        $parent_id = $automation->create_parent_account($parent_data, $student_id);

        if (!is_wp_error($parent_id)) {
            // Update parent details with user ID
            $parent_details = get_field('parent_details', $student_id);
            if (isset($parent_details[$parent_index])) {
                $parent_details[$parent_index]['parent_user_id'] = $parent_id;
                update_field('parent_details', $parent_details, $student_id);
            }

            return $parent_id;
        }

        return false;
    }

    /**
     * Update parent notification preferences based on student data.
     */
    private function update_parent_notification_preferences($student_id) {
        $parent_details = get_field('parent_details', $student_id);
        if (!is_array($parent_details)) {
            return;
        }

        foreach ($parent_details as $parent) {
            if (!isset($parent['parent_user_id'])) {
                continue;
            }

            $parent_id = $parent['parent_user_id'];
            
            // Set default notification preferences if not set
            $preferences = get_user_meta($parent_id, 'sms_notification_preferences', true);
            if (!is_array($preferences)) {
                $preferences = [
                    'sms' => !empty($parent['parent_phone']),
                    'email' => !empty($parent['parent_email']),
                    'attendance_alerts' => true,
                    'fee_reminders' => true,
                    'academic_updates' => true,
                    'transport_updates' => true
                ];
                update_user_meta($parent_id, 'sms_notification_preferences', $preferences);
            }

            // Update contact information
            if (!empty($parent['parent_phone'])) {
                update_user_meta($parent_id, 'sms_phone_number', $parent['parent_phone']);
            }
        }
    }

    /**
     * Add parent management meta boxes to student edit screen.
     */
    public function add_parent_management_meta_boxes() {
        add_meta_box(
            'sms-parent-management',
            __('Parent Account Management', 'school-management-system'),
            [$this, 'render_parent_management_meta_box'],
            'sms_students',
            'side',
            'default'
        );
    }

    /**
     * Render parent management meta box.
     */
    public function render_parent_management_meta_box($post) {
        $parent_details = get_field('parent_details', $post->ID);
        
        echo '<div id="sms-parent-management">';
        
        if (is_array($parent_details) && !empty($parent_details)) {
            foreach ($parent_details as $index => $parent) {
                echo '<div class="parent-account-status" data-index="' . esc_attr($index) . '">';
                echo '<h4>' . esc_html($parent['parent_name'] ?? 'Parent ' . ($index + 1)) . '</h4>';
                
                if (isset($parent['parent_user_id']) && $parent['parent_user_id']) {
                    $user = get_userdata($parent['parent_user_id']);
                    if ($user) {
                        echo '<p><span class="dashicons dashicons-yes-alt" style="color: green;"></span> ';
                        echo __('Account linked', 'school-management-system') . '</p>';
                        echo '<p><strong>' . __('Username:', 'school-management-system') . '</strong> ' . esc_html($user->user_login) . '</p>';
                        echo '<p><strong>' . __('Email:', 'school-management-system') . '</strong> ' . esc_html($user->user_email) . '</p>';
                        echo '<button type="button" class="button unlink-parent" data-parent-id="' . esc_attr($parent['parent_user_id']) . '" data-student-id="' . esc_attr($post->ID) . '">';
                        echo __('Unlink Account', 'school-management-system') . '</button>';
                    } else {
                        echo '<p><span class="dashicons dashicons-warning" style="color: orange;"></span> ';
                        echo __('Account ID exists but user not found', 'school-management-system') . '</p>';
                    }
                } else {
                    echo '<p><span class="dashicons dashicons-minus" style="color: red;"></span> ';
                    echo __('No account linked', 'school-management-system') . '</p>';
                    
                    if (!empty($parent['parent_email'])) {
                        $existing_user = get_user_by('email', $parent['parent_email']);
                        if ($existing_user) {
                            echo '<p>' . __('User exists with this email', 'school-management-system') . '</p>';
                            echo '<button type="button" class="button link-existing-parent" data-parent-email="' . esc_attr($parent['parent_email']) . '" data-student-id="' . esc_attr($post->ID) . '" data-index="' . esc_attr($index) . '">';
                            echo __('Link Existing Account', 'school-management-system') . '</button>';
                        } else {
                            echo '<button type="button" class="button button-primary create-parent-account" data-student-id="' . esc_attr($post->ID) . '" data-index="' . esc_attr($index) . '">';
                            echo __('Create Account', 'school-management-system') . '</button>';
                        }
                    }
                }
                
                echo '</div><hr>';
            }
        } else {
            echo '<p>' . __('No parent information available. Please add parent details in the Parent/Guardian Information section.', 'school-management-system') . '</p>';
        }
        
        echo '</div>';
        
        // Add JavaScript for parent management
        $this->add_parent_management_scripts();
    }

    /**
     * Add JavaScript for parent management functionality.
     */
    private function add_parent_management_scripts() {
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Link existing parent
            $('.link-existing-parent').on('click', function() {
                var button = $(this);
                var email = button.data('parent-email');
                var studentId = button.data('student-id');
                var index = button.data('index');
                
                if (confirm('<?php echo esc_js(__('Link existing parent account to this student?', 'school-management-system')); ?>')) {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'sms_link_parent_to_student',
                            parent_email: email,
                            student_id: studentId,
                            parent_index: index,
                            nonce: '<?php echo wp_create_nonce('sms_parent_management'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                location.reload();
                            } else {
                                alert('Error: ' + response.data.message);
                            }
                        }
                    });
                }
            });
            
            // Create parent account
            $('.create-parent-account').on('click', function() {
                var button = $(this);
                var studentId = button.data('student-id');
                var index = button.data('index');
                
                if (confirm('<?php echo esc_js(__('Create new parent account for this parent?', 'school-management-system')); ?>')) {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'sms_create_parent_account',
                            student_id: studentId,
                            parent_index: index,
                            nonce: '<?php echo wp_create_nonce('sms_parent_management'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                location.reload();
                            } else {
                                alert('Error: ' + response.data.message);
                            }
                        }
                    });
                }
            });
            
            // Unlink parent
            $('.unlink-parent').on('click', function() {
                var button = $(this);
                var parentId = button.data('parent-id');
                var studentId = button.data('student-id');
                
                if (confirm('<?php echo esc_js(__('Unlink parent account from this student? The account will not be deleted.', 'school-management-system')); ?>')) {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'sms_unlink_parent_from_student',
                            parent_id: parentId,
                            student_id: studentId,
                            nonce: '<?php echo wp_create_nonce('sms_parent_management'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                location.reload();
                            } else {
                                alert('Error: ' + response.data.message);
                            }
                        }
                    });
                }
            });
        });
        </script>
        <?php
    }

    /**
     * AJAX handler to link parent to student.
     */
    public function ajax_link_parent_to_student() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_parent_management')) {
            wp_die('Security check failed');
        }

        // Check permissions
        if (!current_user_can('manage_students')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'school-management-system')]);
        }

        $email = sanitize_email($_POST['parent_email']);
        $student_id = intval($_POST['student_id']);
        $parent_index = intval($_POST['parent_index']);

        $user = get_user_by('email', $email);
        if (!$user) {
            wp_send_json_error(['message' => __('User not found', 'school-management-system')]);
        }

        $result = $this->link_existing_parent_to_student($user->ID, $student_id, $parent_index);
        
        if ($result) {
            wp_send_json_success(['message' => __('Parent successfully linked to student', 'school-management-system')]);
        } else {
            wp_send_json_error(['message' => __('Failed to link parent to student', 'school-management-system')]);
        }
    }

    /**
     * AJAX handler to unlink parent from student.
     */
    public function ajax_unlink_parent_from_student() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_parent_management')) {
            wp_die('Security check failed');
        }

        // Check permissions
        if (!current_user_can('manage_students')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'school-management-system')]);
        }

        $parent_id = intval($_POST['parent_id']);
        $student_id = intval($_POST['student_id']);

        $result = $this->unlink_parent_from_student($parent_id, $student_id);
        
        if ($result) {
            wp_send_json_success(['message' => __('Parent successfully unlinked from student', 'school-management-system')]);
        } else {
            wp_send_json_error(['message' => __('Failed to unlink parent from student', 'school-management-system')]);
        }
    }

    /**
     * AJAX handler to create parent account.
     */
    public function ajax_create_parent_account() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_parent_management')) {
            wp_die('Security check failed');
        }

        // Check permissions
        if (!current_user_can('manage_students')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'school-management-system')]);
        }

        $student_id = intval($_POST['student_id']);
        $parent_index = intval($_POST['parent_index']);

        $parent_details = get_field('parent_details', $student_id);
        if (!isset($parent_details[$parent_index])) {
            wp_send_json_error(['message' => __('Parent data not found', 'school-management-system')]);
        }

        $result = $this->create_and_link_parent_account($parent_details[$parent_index], $student_id, $parent_index);
        
        if ($result) {
            wp_send_json_success(['message' => __('Parent account created and linked successfully', 'school-management-system')]);
        } else {
            wp_send_json_error(['message' => __('Failed to create parent account', 'school-management-system')]);
        }
    }

    /**
     * Unlink parent from student.
     */
    public function unlink_parent_from_student($parent_id, $student_id) {
        // Remove student from parent's children list
        $children = get_user_meta($parent_id, 'sms_children', true);
        if (is_array($children)) {
            $children = array_diff($children, [$student_id]);
            update_user_meta($parent_id, 'sms_children', $children);
        }

        // Remove user ID from parent details
        $parent_details = get_field('parent_details', $student_id);
        if (is_array($parent_details)) {
            foreach ($parent_details as $index => $parent) {
                if (isset($parent['parent_user_id']) && $parent['parent_user_id'] == $parent_id) {
                    unset($parent_details[$index]['parent_user_id']);
                    break;
                }
            }
            update_field('parent_details', $parent_details, $student_id);
        }

        $this->log("Unlinked parent {$parent_id} from student {$student_id}", 'info', [
            'parent_id' => $parent_id,
            'student_id' => $student_id,
            'unlinked_by' => get_current_user_id()
        ]);

        do_action('sms_parent_unlinked_from_student', $parent_id, $student_id);

        return true;
    }

    /**
     * Add parent profile fields to user profile page.
     */
    public function add_parent_profile_fields($user) {
        if (!in_array('parent', $user->roles)) {
            return;
        }

        $children = get_user_meta($user->ID, 'sms_children', true);
        $notification_preferences = get_user_meta($user->ID, 'sms_notification_preferences', true);
        $phone_number = get_user_meta($user->ID, 'sms_phone_number', true);
        $occupation = get_user_meta($user->ID, 'sms_occupation', true);
        $address = get_user_meta($user->ID, 'sms_address', true);
        ?>
        <h3><?php _e('Parent Information', 'school-management-system'); ?></h3>
        <table class="form-table">
            <tr>
                <th><label for="sms_phone_number"><?php _e('Phone Number', 'school-management-system'); ?></label></th>
                <td>
                    <input type="text" name="sms_phone_number" id="sms_phone_number" value="<?php echo esc_attr($phone_number); ?>" class="regular-text" />
                </td>
            </tr>
            <tr>
                <th><label for="sms_occupation"><?php _e('Occupation', 'school-management-system'); ?></label></th>
                <td>
                    <input type="text" name="sms_occupation" id="sms_occupation" value="<?php echo esc_attr($occupation); ?>" class="regular-text" />
                </td>
            </tr>
            <tr>
                <th><label for="sms_address"><?php _e('Address', 'school-management-system'); ?></label></th>
                <td>
                    <textarea name="sms_address" id="sms_address" rows="3" cols="30"><?php echo esc_textarea($address); ?></textarea>
                </td>
            </tr>
            <tr>
                <th><?php _e('Linked Children', 'school-management-system'); ?></th>
                <td>
                    <?php if (is_array($children) && !empty($children)): ?>
                        <ul>
                            <?php foreach ($children as $child_id): ?>
                                <?php $child_name = get_field('full_name', $child_id); ?>
                                <?php $admission_number = get_field('admission_number', $child_id); ?>
                                <li>
                                    <a href="<?php echo get_edit_post_link($child_id); ?>">
                                        <?php echo esc_html($child_name); ?> (<?php echo esc_html($admission_number); ?>)
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p><?php _e('No children linked to this account.', 'school-management-system'); ?></p>
                    <?php endif; ?>
                </td>
            </tr>
        </table>

        <h3><?php _e('Notification Preferences', 'school-management-system'); ?></h3>
        <table class="form-table">
            <tr>
                <th><?php _e('Notification Methods', 'school-management-system'); ?></th>
                <td>
                    <fieldset>
                        <label>
                            <input type="checkbox" name="sms_notification_preferences[sms]" value="1" <?php checked(isset($notification_preferences['sms']) ? $notification_preferences['sms'] : false); ?> />
                            <?php _e('SMS Notifications', 'school-management-system'); ?>
                        </label><br>
                        <label>
                            <input type="checkbox" name="sms_notification_preferences[email]" value="1" <?php checked(isset($notification_preferences['email']) ? $notification_preferences['email'] : false); ?> />
                            <?php _e('Email Notifications', 'school-management-system'); ?>
                        </label>
                    </fieldset>
                </td>
            </tr>
            <tr>
                <th><?php _e('Notification Types', 'school-management-system'); ?></th>
                <td>
                    <fieldset>
                        <label>
                            <input type="checkbox" name="sms_notification_preferences[attendance_alerts]" value="1" <?php checked(isset($notification_preferences['attendance_alerts']) ? $notification_preferences['attendance_alerts'] : false); ?> />
                            <?php _e('Attendance Alerts', 'school-management-system'); ?>
                        </label><br>
                        <label>
                            <input type="checkbox" name="sms_notification_preferences[fee_reminders]" value="1" <?php checked(isset($notification_preferences['fee_reminders']) ? $notification_preferences['fee_reminders'] : false); ?> />
                            <?php _e('Fee Reminders', 'school-management-system'); ?>
                        </label><br>
                        <label>
                            <input type="checkbox" name="sms_notification_preferences[academic_updates]" value="1" <?php checked(isset($notification_preferences['academic_updates']) ? $notification_preferences['academic_updates'] : false); ?> />
                            <?php _e('Academic Updates', 'school-management-system'); ?>
                        </label><br>
                        <label>
                            <input type="checkbox" name="sms_notification_preferences[transport_updates]" value="1" <?php checked(isset($notification_preferences['transport_updates']) ? $notification_preferences['transport_updates'] : false); ?> />
                            <?php _e('Transport Updates', 'school-management-system'); ?>
                        </label>
                    </fieldset>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Save parent profile fields.
     */
    public function save_parent_profile_fields($user_id) {
        if (!current_user_can('edit_user', $user_id)) {
            return false;
        }

        $user = get_userdata($user_id);
        if (!in_array('parent', $user->roles)) {
            return false;
        }

        // Save basic information
        if (isset($_POST['sms_phone_number'])) {
            update_user_meta($user_id, 'sms_phone_number', sanitize_text_field($_POST['sms_phone_number']));
        }

        if (isset($_POST['sms_occupation'])) {
            update_user_meta($user_id, 'sms_occupation', sanitize_text_field($_POST['sms_occupation']));
        }

        if (isset($_POST['sms_address'])) {
            update_user_meta($user_id, 'sms_address', sanitize_textarea_field($_POST['sms_address']));
        }

        // Save notification preferences
        if (isset($_POST['sms_notification_preferences'])) {
            $preferences = [];
            $preferences['sms'] = isset($_POST['sms_notification_preferences']['sms']);
            $preferences['email'] = isset($_POST['sms_notification_preferences']['email']);
            $preferences['attendance_alerts'] = isset($_POST['sms_notification_preferences']['attendance_alerts']);
            $preferences['fee_reminders'] = isset($_POST['sms_notification_preferences']['fee_reminders']);
            $preferences['academic_updates'] = isset($_POST['sms_notification_preferences']['academic_updates']);
            $preferences['transport_updates'] = isset($_POST['sms_notification_preferences']['transport_updates']);
            
            update_user_meta($user_id, 'sms_notification_preferences', $preferences);
        }
    }

    /**
     * Restrict parent access to only their children's student posts.
     */
    public function restrict_parent_student_access($query) {
        if (is_admin() && $query->is_main_query()) {
            $current_user = wp_get_current_user();
            
            if (in_array('parent', $current_user->roles) && $query->get('post_type') === 'sms_students') {
                $children = get_user_meta($current_user->ID, 'sms_children', true);
                
                if (is_array($children) && !empty($children)) {
                    $query->set('post__in', $children);
                } else {
                    // No children linked, show no posts
                    $query->set('post__in', [0]);
                }
            }
        }
    }

    /**
     * Filter student posts for parents in SQL queries.
     */
    public function filter_student_posts_for_parents($where, $query) {
        global $wpdb;
        
        if (is_admin() && $query->is_main_query()) {
            $current_user = wp_get_current_user();
            
            if (in_array('parent', $current_user->roles) && $query->get('post_type') === 'sms_students') {
                $children = get_user_meta($current_user->ID, 'sms_children', true);
                
                if (is_array($children) && !empty($children)) {
                    $children_ids = implode(',', array_map('intval', $children));
                    $where .= " AND {$wpdb->posts}.ID IN ({$children_ids})";
                } else {
                    $where .= " AND 1=0"; // Show no posts
                }
            }
        }
        
        return $where;
    }

    /**
     * Get parent's children with detailed information.
     */
    public function get_parent_children($parent_id, $include_details = false) {
        $children_ids = get_user_meta($parent_id, 'sms_children', true);
        
        if (!is_array($children_ids) || empty($children_ids)) {
            return [];
        }

        $children = get_posts([
            'post_type' => 'sms_students',
            'posts_per_page' => -1,
            'post__in' => $children_ids,
            'post_status' => 'publish'
        ]);

        if (!$include_details) {
            return $children;
        }

        // Add detailed information
        $detailed_children = [];
        foreach ($children as $child) {
            $child_data = [
                'post' => $child,
                'admission_number' => get_field('admission_number', $child->ID),
                'full_name' => get_field('full_name', $child->ID),
                'class' => get_field('assigned_class', $child->ID),
                'status' => get_field('student_status', $child->ID),
                'grade' => wp_get_post_terms($child->ID, 'sms_grades'),
                'attendance_percentage' => $this->calculate_attendance_percentage($child->ID),
                'pending_fees' => $this->get_student_pending_fees($child->ID)
            ];
            $detailed_children[] = $child_data;
        }

        return $detailed_children;
    }

    /**
     * Get all parents with their children count.
     */
    public function get_parents_summary() {
        $parents = get_users(['role' => 'parent']);
        $summary = [];

        foreach ($parents as $parent) {
            $children = get_user_meta($parent->ID, 'sms_children', true);
            $children_count = is_array($children) ? count($children) : 0;
            
            $summary[] = [
                'parent' => $parent,
                'children_count' => $children_count,
                'phone' => get_user_meta($parent->ID, 'sms_phone_number', true),
                'last_login' => get_user_meta($parent->ID, 'sms_last_login', true)
            ];
        }

        return $summary;
    }

    /**
     * Bulk link parents to students based on email matching.
     */
    public function bulk_link_parents_by_email() {
        $students = get_posts([
            'post_type' => 'sms_students',
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ]);

        $linked_count = 0;
        $errors = [];

        foreach ($students as $student) {
            $parent_details = get_field('parent_details', $student->ID);
            if (!is_array($parent_details)) {
                continue;
            }

            foreach ($parent_details as $index => $parent) {
                if (empty($parent['parent_email']) || isset($parent['parent_user_id'])) {
                    continue;
                }

                $user = get_user_by('email', $parent['parent_email']);
                if ($user) {
                    $result = $this->link_existing_parent_to_student($user->ID, $student->ID, $index);
                    if ($result) {
                        $linked_count++;
                    } else {
                        $errors[] = "Failed to link {$parent['parent_email']} to student {$student->ID}";
                    }
                }
            }
        }

        $this->log("Bulk parent linking completed", 'info', [
            'linked_count' => $linked_count,
            'error_count' => count($errors)
        ]);

        return [
            'linked_count' => $linked_count,
            'errors' => $errors
        ];
    }

    // Helper methods (placeholder implementations)
    private function calculate_attendance_percentage($student_id) {
        // TODO: Implement when attendance system is ready
        return 0;
    }

    private function get_student_pending_fees($student_id) {
        // TODO: Implement when fee system is ready
        return 0;
    }
}

// Initialize the class
new SMS_Parent_Student_Manager();