<?php
/**
 * Automated role assignment and user account management.
 *
 * @package    School_Management_System
 * @subpackage School_Management_System/includes/user-roles
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Role automation class.
 */
class SMS_Role_Automation extends SMS_Base {

    /**
     * Initialize role automation.
     */
    public function __construct() {
        parent::__construct();
        
        // Hook into user registration
        add_action('user_register', [$this, 'handle_new_user_registration'], 10, 1);
        
        // Hook into student creation for parent account generation
        add_action('save_post_sms_students', [$this, 'handle_student_creation'], 20, 3);
        
        // Hook into parent linking
        add_action('sms_parent_linked_to_student', [$this, 'send_parent_welcome_notification'], 10, 2);
        
        // Hook into teacher assignment
        add_action('sms_teacher_assigned_to_class', [$this, 'notify_teacher_assignment'], 10, 3);
    }

    /**
     * Handle new user registration.
     */
    public function handle_new_user_registration($user_id) {
        $user = get_userdata($user_id);
        if (!$user) {
            return;
        }

        // Determine role based on email domain or other criteria
        $role = $this->determine_user_role($user);
        
        if ($role) {
            $user_roles = new SMS_User_Roles();
            $result = $user_roles->assign_user_role($user_id, $role);
            
            if (!is_wp_error($result)) {
                $this->log("Auto-assigned role {$role} to new user {$user_id}", 'info', [
                    'user_id' => $user_id,
                    'role' => $role,
                    'email' => $user->user_email
                ]);
                
                // Send welcome email based on role
                $this->send_welcome_email($user_id, $role);
            }
        }
    }

    /**
     * Determine user role based on registration data.
     */
    private function determine_user_role($user) {
        // Check if email matches existing parent records
        if ($this->is_existing_parent_email($user->user_email)) {
            return 'parent';
        }
        
        // Check if email matches teacher domain patterns
        $teacher_domains = apply_filters('sms_teacher_email_domains', []);
        if (!empty($teacher_domains)) {
            $email_domain = substr(strrchr($user->user_email, "@"), 1);
            if (in_array($email_domain, $teacher_domains)) {
                return 'teacher';
            }
        }
        
        // Check for admin email patterns
        $admin_emails = apply_filters('sms_admin_emails', []);
        if (in_array($user->user_email, $admin_emails)) {
            return 'school_administrator';
        }
        
        // Default to parent role
        return apply_filters('sms_default_new_user_role', 'parent', $user);
    }

    /**
     * Check if email exists in parent records.
     */
    private function is_existing_parent_email($email) {
        $students = get_posts([
            'post_type' => 'sms_students',
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ]);
        
        foreach ($students as $student) {
            $parent_details = get_field('parent_details', $student->ID);
            if (is_array($parent_details)) {
                foreach ($parent_details as $parent) {
                    if (isset($parent['parent_email']) && $parent['parent_email'] === $email) {
                        return true;
                    }
                }
            }
        }
        
        return false;
    }

    /**
     * Handle student creation and parent account generation.
     */
    public function handle_student_creation($post_id, $post, $update) {
        // Skip if this is an update or autosave
        if ($update || wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }

        // Get parent details
        $parent_details = get_field('parent_details', $post_id);
        if (!is_array($parent_details) || empty($parent_details)) {
            return;
        }

        foreach ($parent_details as $index => $parent) {
            if (empty($parent['parent_email'])) {
                continue;
            }

            // Check if user account already exists
            $existing_user = get_user_by('email', $parent['parent_email']);
            
            if (!$existing_user) {
                // Create parent account
                $parent_user_id = $this->create_parent_account($parent, $post_id);
                
                if ($parent_user_id && !is_wp_error($parent_user_id)) {
                    // Update parent details with user ID
                    $parent_details[$index]['parent_user_id'] = $parent_user_id;
                    update_field('parent_details', $parent_details, $post_id);
                    
                    // Link parent to student
                    $role_manager = new SMS_Role_Manager();
                    $role_manager->link_parent_to_student($parent_user_id, $post_id);
                }
            } else {
                // Link existing user to student
                $role_manager = new SMS_Role_Manager();
                $role_manager->link_parent_to_student($existing_user->ID, $post_id);
                
                // Update parent details with user ID
                $parent_details[$index]['parent_user_id'] = $existing_user->ID;
                update_field('parent_details', $parent_details, $post_id);
            }
        }
    }

    /**
     * Create parent account.
     */
    private function create_parent_account($parent_data, $student_id) {
        if (empty($parent_data['parent_email']) || empty($parent_data['parent_name'])) {
            return new WP_Error('missing_data', 'Parent email and name are required');
        }

        // Generate username from email
        $username = $this->generate_username_from_email($parent_data['parent_email']);
        
        // Generate secure password
        $password = wp_generate_password(12, true, true);
        
        // Create user account
        $user_id = wp_create_user($username, $password, $parent_data['parent_email']);
        
        if (is_wp_error($user_id)) {
            $this->log("Failed to create parent account: " . $user_id->get_error_message(), 'error', [
                'email' => $parent_data['parent_email'],
                'student_id' => $student_id
            ]);
            return $user_id;
        }

        // Update user profile
        wp_update_user([
            'ID' => $user_id,
            'display_name' => $parent_data['parent_name'],
            'first_name' => $this->extract_first_name($parent_data['parent_name']),
            'last_name' => $this->extract_last_name($parent_data['parent_name'])
        ]);

        // Assign parent role
        $user_roles = new SMS_User_Roles();
        $user_roles->assign_user_role($user_id, 'parent');

        // Store additional parent information
        if (isset($parent_data['parent_phone'])) {
            update_user_meta($user_id, 'sms_phone_number', $parent_data['parent_phone']);
        }
        
        if (isset($parent_data['parent_occupation'])) {
            update_user_meta($user_id, 'sms_occupation', $parent_data['parent_occupation']);
        }
        
        if (isset($parent_data['parent_address'])) {
            update_user_meta($user_id, 'sms_address', $parent_data['parent_address']);
        }

        // Store temporary password for welcome email
        update_user_meta($user_id, 'sms_temp_password', $password);
        update_user_meta($user_id, 'sms_account_created_for_student', $student_id);

        $this->log("Created parent account for student {$student_id}", 'info', [
            'parent_user_id' => $user_id,
            'student_id' => $student_id,
            'email' => $parent_data['parent_email']
        ]);

        // Send welcome email with login credentials
        $this->send_parent_account_created_email($user_id, $password, $student_id);

        return $user_id;
    }

    /**
     * Generate username from email.
     */
    private function generate_username_from_email($email) {
        $base_username = sanitize_user(substr($email, 0, strpos($email, '@')));
        $username = $base_username;
        $counter = 1;

        while (username_exists($username)) {
            $username = $base_username . $counter;
            $counter++;
        }

        return $username;
    }

    /**
     * Extract first name from full name.
     */
    private function extract_first_name($full_name) {
        $parts = explode(' ', trim($full_name));
        return $parts[0] ?? '';
    }

    /**
     * Extract last name from full name.
     */
    private function extract_last_name($full_name) {
        $parts = explode(' ', trim($full_name));
        if (count($parts) > 1) {
            array_shift($parts);
            return implode(' ', $parts);
        }
        return '';
    }

    /**
     * Send welcome email based on role.
     */
    private function send_welcome_email($user_id, $role) {
        $user = get_userdata($user_id);
        if (!$user) {
            return;
        }

        $subject = sprintf(__('Welcome to %s', 'school-management-system'), get_bloginfo('name'));
        
        $message = $this->get_welcome_email_template($role, $user);
        
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        
        wp_mail($user->user_email, $subject, $message, $headers);
        
        $this->log("Sent welcome email to user {$user_id} with role {$role}", 'info', [
            'user_id' => $user_id,
            'role' => $role,
            'email' => $user->user_email
        ]);
    }

    /**
     * Send parent account created email.
     */
    private function send_parent_account_created_email($user_id, $password, $student_id) {
        $user = get_userdata($user_id);
        $student_name = get_field('full_name', $student_id);
        
        if (!$user) {
            return;
        }

        $subject = sprintf(__('Parent Account Created - %s', 'school-management-system'), get_bloginfo('name'));
        
        $login_url = wp_login_url();
        
        $message = sprintf(
            __('Dear %s,

A parent account has been created for you to access your child\'s school information.

Student: %s
Your login credentials:
Username: %s
Password: %s

Please login at: %s

For security, please change your password after your first login.

Best regards,
%s', 'school-management-system'),
            $user->display_name,
            $student_name,
            $user->user_login,
            $password,
            $login_url,
            get_bloginfo('name')
        );
        
        $headers = ['Content-Type: text/plain; charset=UTF-8'];
        
        wp_mail($user->user_email, $subject, $message, $headers);
        
        // Clear temporary password
        delete_user_meta($user_id, 'sms_temp_password');
        
        $this->log("Sent parent account creation email to user {$user_id}", 'info', [
            'user_id' => $user_id,
            'student_id' => $student_id,
            'email' => $user->user_email
        ]);
    }

    /**
     * Get welcome email template based on role.
     */
    private function get_welcome_email_template($role, $user) {
        $templates = [
            'school_administrator' => __('Welcome to the school management system. You have been assigned administrator privileges.', 'school-management-system'),
            'teacher' => __('Welcome to the school management system. You have been assigned teacher privileges.', 'school-management-system'),
            'parent' => __('Welcome to the school management system. You can now access your child\'s school information.', 'school-management-system'),
            'student' => __('Welcome to the school management system. You can now access your academic information.', 'school-management-system')
        ];
        
        $template = $templates[$role] ?? $templates['parent'];
        
        return sprintf(
            __('Dear %s,

%s

Login URL: %s

Best regards,
%s', 'school-management-system'),
            $user->display_name,
            $template,
            wp_login_url(),
            get_bloginfo('name')
        );
    }

    /**
     * Send parent welcome notification when linked to student.
     */
    public function send_parent_welcome_notification($parent_id, $student_id) {
        $parent = get_userdata($parent_id);
        $student_name = get_field('full_name', $student_id);
        
        if (!$parent) {
            return;
        }

        // Check if this is a new link (not during account creation)
        $created_for_student = get_user_meta($parent_id, 'sms_account_created_for_student', true);
        if ($created_for_student == $student_id) {
            return; // Skip notification as account was just created for this student
        }

        $subject = sprintf(__('Student Linked to Your Account - %s', 'school-management-system'), get_bloginfo('name'));
        
        $message = sprintf(
            __('Dear %s,

The student "%s" has been linked to your parent account. You can now access their school information through your account.

Login at: %s

Best regards,
%s', 'school-management-system'),
            $parent->display_name,
            $student_name,
            wp_login_url(),
            get_bloginfo('name')
        );
        
        wp_mail($parent->user_email, $subject, $message);
    }

    /**
     * Notify teacher of class assignment.
     */
    public function notify_teacher_assignment($teacher_id, $class_id, $role) {
        $teacher = get_userdata($teacher_id);
        $class_name = get_field('class_name', $class_id);
        
        if (!$teacher) {
            return;
        }

        $subject = sprintf(__('Class Assignment - %s', 'school-management-system'), get_bloginfo('name'));
        
        $role_text = $role === 'main' ? __('main teacher', 'school-management-system') : __('assistant teacher', 'school-management-system');
        
        $message = sprintf(
            __('Dear %s,

You have been assigned as %s for the class "%s".

You can now access class management features through your account.

Login at: %s

Best regards,
%s', 'school-management-system'),
            $teacher->display_name,
            $role_text,
            $class_name,
            wp_login_url(),
            get_bloginfo('name')
        );
        
        wp_mail($teacher->user_email, $subject, $message);
    }

    /**
     * Bulk create parent accounts for existing students.
     */
    public function bulk_create_parent_accounts($student_ids = null) {
        if (!$student_ids) {
            // Get all students without linked parent accounts
            $students = get_posts([
                'post_type' => 'sms_students',
                'posts_per_page' => -1,
                'post_status' => 'publish'
            ]);
        } else {
            $students = get_posts([
                'post_type' => 'sms_students',
                'posts_per_page' => -1,
                'post_status' => 'publish',
                'post__in' => $student_ids
            ]);
        }

        $created_count = 0;
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

                $existing_user = get_user_by('email', $parent['parent_email']);
                if ($existing_user) {
                    continue;
                }

                $result = $this->create_parent_account($parent, $student->ID);
                if (!is_wp_error($result)) {
                    $created_count++;
                    
                    // Update parent details with user ID
                    $parent_details[$index]['parent_user_id'] = $result;
                    update_field('parent_details', $parent_details, $student->ID);
                } else {
                    $errors[] = "Student {$student->ID}: " . $result->get_error_message();
                }
            }
        }

        $this->log("Bulk parent account creation completed", 'info', [
            'created_count' => $created_count,
            'error_count' => count($errors)
        ]);

        return [
            'created_count' => $created_count,
            'errors' => $errors
        ];
    }
}

// Initialize the class
new SMS_Role_Automation();