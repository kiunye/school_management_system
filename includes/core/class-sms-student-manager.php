<?php
/**
 * Student Management System - Core student management functionality
 *
 * Handles student admission workflow, enrollment, and lifecycle management.
 *
 * @package    School_Management_System
 * @subpackage School_Management_System/includes/core
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Student Manager Class
 */
class SMS_Student_Manager extends SMS_Base {

    /**
     * Student admission statuses
     */
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_ENROLLED = 'enrolled';
    const STATUS_REJECTED = 'rejected';
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';
    const STATUS_GRADUATED = 'graduated';
    const STATUS_TRANSFERRED = 'transferred';
    const STATUS_SUSPENDED = 'suspended';

    /**
     * Initialize the student manager.
     */
    public function __construct() {
        parent::__construct();
        
        // Hook into student post save for admission workflow
        add_action('save_post_sms_students', [$this, 'handle_student_save'], 10, 3);
        
        // Add admission workflow meta box
        add_action('add_meta_boxes', [$this, 'add_admission_workflow_meta_box']);
        
        // Handle AJAX requests for admission workflow
        add_action('wp_ajax_sms_approve_student_admission', [$this, 'ajax_approve_student_admission']);
        add_action('wp_ajax_sms_reject_student_admission', [$this, 'ajax_reject_student_admission']);
        add_action('wp_ajax_sms_enroll_student', [$this, 'ajax_enroll_student']);
        
        // Add custom admin columns for admission status
        add_filter('manage_sms_students_posts_columns', [$this, 'add_admission_status_column']);
        add_action('manage_sms_students_posts_custom_column', [$this, 'populate_admission_status_column'], 10, 2);
        
        // Add bulk actions for admission workflow
        add_filter('bulk_actions-edit-sms_students', [$this, 'add_bulk_admission_actions']);
        add_filter('handle_bulk_actions-edit-sms_students', [$this, 'handle_bulk_admission_actions'], 10, 3);
        
        // Hook for sending welcome notifications
        add_action('sms_student_admission_approved', [$this, 'send_admission_approval_notification'], 10, 1);
        add_action('sms_student_enrolled', [$this, 'send_enrollment_welcome_notification'], 10, 1);
    }

    /**
     * Create a new student with admission workflow.
     */
    public function create_student($student_data, $auto_approve = false) {
        // Validate required fields
        $validation_result = $this->validate_student_data($student_data);
        if (is_wp_error($validation_result)) {
            return $validation_result;
        }

        // Set initial admission status
        $admission_status = $auto_approve ? self::STATUS_APPROVED : self::STATUS_PENDING;
        
        // Create the student post
        $post_data = [
            'post_type' => 'sms_students',
            'post_title' => $student_data['full_name'],
            'post_status' => 'publish',
            'post_content' => '',
            'meta_input' => [
                'admission_status' => $admission_status,
                'admission_date' => current_time('Y-m-d'),
                'created_by' => get_current_user_id()
            ]
        ];

        $student_id = wp_insert_post($post_data);
        
        if (is_wp_error($student_id)) {
            return $student_id;
        }

        // Save student data using ACF fields
        $this->save_student_fields($student_id, $student_data);

        // Log the creation
        $this->log("Student created with ID {$student_id}", 'info', [
            'student_id' => $student_id,
            'admission_status' => $admission_status,
            'created_by' => get_current_user_id()
        ]);

        // Trigger admission workflow
        if ($auto_approve) {
            do_action('sms_student_admission_approved', $student_id);
        } else {
            do_action('sms_student_admission_pending', $student_id);
        }

        return $student_id;
    }

    /**
     * Validate student data before creation.
     */
    private function validate_student_data($data) {
        $errors = new WP_Error();

        // Required fields validation
        $required_fields = [
            'full_name' => __('Full Name is required', 'school-management-system'),
            'date_of_birth' => __('Date of Birth is required', 'school-management-system'),
            'gender' => __('Gender is required', 'school-management-system')
        ];

        foreach ($required_fields as $field => $message) {
            if (empty($data[$field])) {
                $errors->add('missing_field', $message, ['field' => $field]);
            }
        }

        // Validate date of birth
        if (!empty($data['date_of_birth'])) {
            $dob = DateTime::createFromFormat('Y-m-d', $data['date_of_birth']);
            if (!$dob || $dob->format('Y-m-d') !== $data['date_of_birth']) {
                $errors->add('invalid_date', __('Invalid date of birth format', 'school-management-system'));
            } elseif ($dob >= new DateTime()) {
                $errors->add('future_date', __('Date of birth cannot be in the future', 'school-management-system'));
            }
        }

        // Validate parent information
        if (!empty($data['parent_details']) && is_array($data['parent_details'])) {
            foreach ($data['parent_details'] as $index => $parent) {
                if (empty($parent['parent_name'])) {
                    $errors->add('missing_parent_name', sprintf(__('Parent %d name is required', 'school-management-system'), $index + 1));
                }
                if (empty($parent['parent_phone'])) {
                    $errors->add('missing_parent_phone', sprintf(__('Parent %d phone number is required', 'school-management-system'), $index + 1));
                }
                if (!empty($parent['parent_email']) && !is_email($parent['parent_email'])) {
                    $errors->add('invalid_parent_email', sprintf(__('Parent %d email is invalid', 'school-management-system'), $index + 1));
                }
            }
        } else {
            $errors->add('missing_parent_info', __('At least one parent/guardian information is required', 'school-management-system'));
        }

        return $errors->has_errors() ? $errors : true;
    }

    /**
     * Save student fields using ACF.
     */
    private function save_student_fields($student_id, $data) {
        $field_mappings = [
            'full_name' => 'full_name',
            'date_of_birth' => 'date_of_birth',
            'gender' => 'gender',
            'national_id' => 'national_id',
            'parent_details' => 'parent_details',
            'medical_conditions' => 'medical_conditions',
            'allergies' => 'allergies',
            'blood_group' => 'blood_group',
            'medications' => 'medications',
            'emergency_contacts' => 'emergency_contacts'
        ];

        foreach ($field_mappings as $data_key => $field_key) {
            if (isset($data[$data_key])) {
                update_field($field_key, $data[$data_key], $student_id);
            }
        }

        // Set default student status
        update_field('student_status', self::STATUS_ACTIVE, $student_id);
        
        // Set admission date if not provided
        if (empty($data['admission_date'])) {
            update_field('admission_date', current_time('Y-m-d'), $student_id);
        } else {
            update_field('admission_date', $data['admission_date'], $student_id);
        }
    }

    /**
     * Approve student admission.
     */
    public function approve_admission($student_id, $approved_by = null) {
        if (!$this->can_manage_admissions()) {
            return new WP_Error('insufficient_permissions', __('You do not have permission to approve admissions', 'school-management-system'));
        }

        $current_status = get_post_meta($student_id, 'admission_status', true);
        
        if ($current_status !== self::STATUS_PENDING) {
            return new WP_Error('invalid_status', __('Only pending admissions can be approved', 'school-management-system'));
        }

        // Update admission status
        update_post_meta($student_id, 'admission_status', self::STATUS_APPROVED);
        update_post_meta($student_id, 'approved_by', $approved_by ?: get_current_user_id());
        update_post_meta($student_id, 'approved_date', current_time('Y-m-d H:i:s'));

        // Log the approval
        $this->log("Student admission approved for ID {$student_id}", 'info', [
            'student_id' => $student_id,
            'approved_by' => $approved_by ?: get_current_user_id(),
            'previous_status' => $current_status
        ]);

        // Trigger approval actions
        do_action('sms_student_admission_approved', $student_id);

        return true;
    }

    /**
     * Reject student admission.
     */
    public function reject_admission($student_id, $reason = '', $rejected_by = null) {
        if (!$this->can_manage_admissions()) {
            return new WP_Error('insufficient_permissions', __('You do not have permission to reject admissions', 'school-management-system'));
        }

        $current_status = get_post_meta($student_id, 'admission_status', true);
        
        if ($current_status !== self::STATUS_PENDING) {
            return new WP_Error('invalid_status', __('Only pending admissions can be rejected', 'school-management-system'));
        }

        // Update admission status
        update_post_meta($student_id, 'admission_status', self::STATUS_REJECTED);
        update_post_meta($student_id, 'rejected_by', $rejected_by ?: get_current_user_id());
        update_post_meta($student_id, 'rejected_date', current_time('Y-m-d H:i:s'));
        update_post_meta($student_id, 'rejection_reason', $reason);

        // Log the rejection
        $this->log("Student admission rejected for ID {$student_id}", 'info', [
            'student_id' => $student_id,
            'rejected_by' => $rejected_by ?: get_current_user_id(),
            'reason' => $reason,
            'previous_status' => $current_status
        ]);

        // Trigger rejection actions
        do_action('sms_student_admission_rejected', $student_id, $reason);

        return true;
    }

    /**
     * Enroll approved student.
     */
    public function enroll_student($student_id, $class_id = null, $enrolled_by = null) {
        if (!$this->can_manage_students()) {
            return new WP_Error('insufficient_permissions', __('You do not have permission to enroll students', 'school-management-system'));
        }

        $current_status = get_post_meta($student_id, 'admission_status', true);
        
        if ($current_status !== self::STATUS_APPROVED) {
            return new WP_Error('invalid_status', __('Only approved students can be enrolled', 'school-management-system'));
        }

        // Validate class capacity if class is specified
        if ($class_id) {
            $capacity_check = $this->check_class_capacity($class_id);
            if (is_wp_error($capacity_check)) {
                return $capacity_check;
            }
        }

        // Update admission status to enrolled
        update_post_meta($student_id, 'admission_status', self::STATUS_ENROLLED);
        update_post_meta($student_id, 'enrolled_by', $enrolled_by ?: get_current_user_id());
        update_post_meta($student_id, 'enrollment_date', current_time('Y-m-d H:i:s'));

        // Assign to class if specified
        if ($class_id) {
            update_field('assigned_class', $class_id, $student_id);
            update_field('enrollment_date', current_time('Y-m-d'), $student_id);
        }

        // Log the enrollment
        $this->log("Student enrolled with ID {$student_id}", 'info', [
            'student_id' => $student_id,
            'class_id' => $class_id,
            'enrolled_by' => $enrolled_by ?: get_current_user_id(),
            'previous_status' => $current_status
        ]);

        // Trigger enrollment actions
        do_action('sms_student_enrolled', $student_id, $class_id);

        return true;
    }

    /**
     * Check if current user can manage admissions.
     */
    private function can_manage_admissions() {
        return current_user_can('manage_students') || current_user_can('manage_admissions');
    }

    /**
     * Check if current user can manage students.
     */
    private function can_manage_students() {
        return current_user_can('manage_students');
    }

    /**
     * Check class capacity before enrollment.
     */
    private function check_class_capacity($class_id) {
        $capacity = get_field('capacity', $class_id);
        if (!$capacity) {
            return true; // No capacity limit set
        }

        // Count current enrolled students
        $enrolled_count = $this->get_class_enrolled_count($class_id);
        
        if ($enrolled_count >= $capacity) {
            return new WP_Error('capacity_exceeded', __('Class capacity has been reached', 'school-management-system'));
        }

        return true;
    }

    /**
     * Get count of enrolled students in a class.
     */
    private function get_class_enrolled_count($class_id) {
        $args = [
            'post_type' => 'sms_students',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => 'assigned_class',
                    'value' => $class_id,
                    'compare' => '='
                ],
                [
                    'key' => 'student_status',
                    'value' => ['active', 'enrolled'],
                    'compare' => 'IN'
                ]
            ],
            'fields' => 'ids'
        ];

        $query = new WP_Query($args);
        return $query->found_posts;
    }

    /**
     * Handle student post save.
     */
    public function handle_student_save($post_id, $post, $update) {
        // Skip if this is an autosave or revision
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }

        // Initialize admission status for new students
        if (!$update) {
            $admission_status = get_post_meta($post_id, 'admission_status', true);
            if (empty($admission_status)) {
                update_post_meta($post_id, 'admission_status', self::STATUS_PENDING);
            }
        }

        // Handle parent account creation if needed
        $this->handle_parent_account_creation($post_id);
    }

    /**
     * Handle parent account creation during student save.
     */
    private function handle_parent_account_creation($student_id) {
        $parent_details = get_field('parent_details', $student_id);
        
        if (!is_array($parent_details) || empty($parent_details)) {
            return;
        }

        $parent_manager = new SMS_Parent_Student_Manager();
        
        foreach ($parent_details as $index => $parent) {
            if (empty($parent['parent_email'])) {
                continue;
            }

            // Check if parent account already exists
            $existing_user = get_user_by('email', $parent['parent_email']);
            
            if (!$existing_user) {
                // Create parent account if auto-creation is enabled
                $auto_create = apply_filters('sms_auto_create_parent_accounts', true);
                if ($auto_create) {
                    $role_automation = new SMS_Role_Automation();
                    $parent_id = $role_automation->create_parent_account($parent, $student_id);
                    
                    if (!is_wp_error($parent_id)) {
                        // Update parent details with user ID
                        $parent_details[$index]['parent_user_id'] = $parent_id;
                        update_field('parent_details', $parent_details, $student_id);
                    }
                }
            }
        }
    }

    /**
     * Add admission workflow meta box.
     */
    public function add_admission_workflow_meta_box() {
        add_meta_box(
            'sms-admission-workflow',
            __('Admission Workflow', 'school-management-system'),
            [$this, 'render_admission_workflow_meta_box'],
            'sms_students',
            'side',
            'high'
        );
    }

    /**
     * Render admission workflow meta box.
     */
    public function render_admission_workflow_meta_box($post) {
        $admission_status = get_post_meta($post->ID, 'admission_status', true) ?: self::STATUS_PENDING;
        $approved_by = get_post_meta($post->ID, 'approved_by', true);
        $approved_date = get_post_meta($post->ID, 'approved_date', true);
        $rejected_by = get_post_meta($post->ID, 'rejected_by', true);
        $rejected_date = get_post_meta($post->ID, 'rejected_date', true);
        $rejection_reason = get_post_meta($post->ID, 'rejection_reason', true);
        $enrolled_by = get_post_meta($post->ID, 'enrolled_by', true);
        $enrollment_date = get_post_meta($post->ID, 'enrollment_date', true);

        echo '<div id="sms-admission-workflow">';
        
        // Current status
        echo '<p><strong>' . __('Current Status:', 'school-management-system') . '</strong> ';
        echo '<span class="status-badge status-' . esc_attr($admission_status) . '">' . esc_html(ucfirst($admission_status)) . '</span></p>';

        // Status-specific information and actions
        switch ($admission_status) {
            case self::STATUS_PENDING:
                echo '<div class="admission-actions">';
                if ($this->can_manage_admissions()) {
                    echo '<button type="button" class="button button-primary approve-admission" data-student-id="' . esc_attr($post->ID) . '">';
                    echo __('Approve Admission', 'school-management-system') . '</button> ';
                    echo '<button type="button" class="button reject-admission" data-student-id="' . esc_attr($post->ID) . '">';
                    echo __('Reject Admission', 'school-management-system') . '</button>';
                }
                echo '</div>';
                break;

            case self::STATUS_APPROVED:
                if ($approved_by && $approved_date) {
                    $approver = get_userdata($approved_by);
                    echo '<p><small>' . sprintf(__('Approved by %s on %s', 'school-management-system'), 
                        $approver ? $approver->display_name : __('Unknown', 'school-management-system'),
                        date('M j, Y g:i A', strtotime($approved_date))
                    ) . '</small></p>';
                }
                
                echo '<div class="admission-actions">';
                if ($this->can_manage_students()) {
                    echo '<button type="button" class="button button-primary enroll-student" data-student-id="' . esc_attr($post->ID) . '">';
                    echo __('Enroll Student', 'school-management-system') . '</button>';
                }
                echo '</div>';
                break;

            case self::STATUS_REJECTED:
                if ($rejected_by && $rejected_date) {
                    $rejector = get_userdata($rejected_by);
                    echo '<p><small>' . sprintf(__('Rejected by %s on %s', 'school-management-system'), 
                        $rejector ? $rejector->display_name : __('Unknown', 'school-management-system'),
                        date('M j, Y g:i A', strtotime($rejected_date))
                    ) . '</small></p>';
                }
                if ($rejection_reason) {
                    echo '<p><strong>' . __('Reason:', 'school-management-system') . '</strong><br>';
                    echo esc_html($rejection_reason) . '</p>';
                }
                break;

            case self::STATUS_ENROLLED:
                if ($enrolled_by && $enrollment_date) {
                    $enroller = get_userdata($enrolled_by);
                    echo '<p><small>' . sprintf(__('Enrolled by %s on %s', 'school-management-system'), 
                        $enroller ? $enroller->display_name : __('Unknown', 'school-management-system'),
                        date('M j, Y g:i A', strtotime($enrollment_date))
                    ) . '</small></p>';
                }
                
                $assigned_class = get_field('assigned_class', $post->ID);
                if ($assigned_class) {
                    echo '<p><strong>' . __('Assigned Class:', 'school-management-system') . '</strong><br>';
                    echo '<a href="' . get_edit_post_link($assigned_class) . '">' . get_the_title($assigned_class) . '</a></p>';
                }
                break;
        }

        echo '</div>';

        // Add JavaScript for workflow actions
        $this->add_admission_workflow_scripts();
    }

    /**
     * Add JavaScript for admission workflow.
     */
    private function add_admission_workflow_scripts() {
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Approve admission
            $('.approve-admission').on('click', function() {
                var studentId = $(this).data('student-id');
                
                if (confirm('<?php echo esc_js(__('Approve this student admission?', 'school-management-system')); ?>')) {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'sms_approve_student_admission',
                            student_id: studentId,
                            nonce: '<?php echo wp_create_nonce('sms_admission_workflow'); ?>'
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
            
            // Reject admission
            $('.reject-admission').on('click', function() {
                var studentId = $(this).data('student-id');
                var reason = prompt('<?php echo esc_js(__('Please provide a reason for rejection:', 'school-management-system')); ?>');
                
                if (reason !== null) {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'sms_reject_student_admission',
                            student_id: studentId,
                            reason: reason,
                            nonce: '<?php echo wp_create_nonce('sms_admission_workflow'); ?>'
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
            
            // Enroll student
            $('.enroll-student').on('click', function() {
                var studentId = $(this).data('student-id');
                
                if (confirm('<?php echo esc_js(__('Enroll this student?', 'school-management-system')); ?>')) {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'sms_enroll_student',
                            student_id: studentId,
                            nonce: '<?php echo wp_create_nonce('sms_admission_workflow'); ?>'
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
        <style>
        .status-badge {
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-pending { background: #ffb900; color: #fff; }
        .status-approved { background: #00a32a; color: #fff; }
        .status-rejected { background: #d63638; color: #fff; }
        .status-enrolled { background: #0073aa; color: #fff; }
        .admission-actions { margin-top: 10px; }
        .admission-actions .button { margin-right: 5px; }
        </style>
        <?php
    }

    /**
     * AJAX handler for approving student admission.
     */
    public function ajax_approve_student_admission() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_admission_workflow')) {
            wp_die('Security check failed');
        }

        $student_id = intval($_POST['student_id']);
        $result = $this->approve_admission($student_id);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        } else {
            wp_send_json_success(['message' => __('Student admission approved successfully', 'school-management-system')]);
        }
    }

    /**
     * AJAX handler for rejecting student admission.
     */
    public function ajax_reject_student_admission() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_admission_workflow')) {
            wp_die('Security check failed');
        }

        $student_id = intval($_POST['student_id']);
        $reason = sanitize_textarea_field($_POST['reason']);
        $result = $this->reject_admission($student_id, $reason);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        } else {
            wp_send_json_success(['message' => __('Student admission rejected successfully', 'school-management-system')]);
        }
    }

    /**
     * AJAX handler for enrolling student.
     */
    public function ajax_enroll_student() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_admission_workflow')) {
            wp_die('Security check failed');
        }

        $student_id = intval($_POST['student_id']);
        $class_id = isset($_POST['class_id']) ? intval($_POST['class_id']) : null;
        $result = $this->enroll_student($student_id, $class_id);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        } else {
            wp_send_json_success(['message' => __('Student enrolled successfully', 'school-management-system')]);
        }
    }

    /**
     * Add admission status column to students list.
     */
    public function add_admission_status_column($columns) {
        // Insert admission status column after title
        $new_columns = [];
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'title') {
                $new_columns['admission_status'] = __('Admission Status', 'school-management-system');
            }
        }
        return $new_columns;
    }

    /**
     * Populate admission status column.
     */
    public function populate_admission_status_column($column, $post_id) {
        if ($column === 'admission_status') {
            $status = get_post_meta($post_id, 'admission_status', true) ?: self::STATUS_PENDING;
            echo '<span class="status-badge status-' . esc_attr($status) . '">' . esc_html(ucfirst($status)) . '</span>';
        }
    }

    /**
     * Add bulk actions for admission workflow.
     */
    public function add_bulk_admission_actions($actions) {
        if ($this->can_manage_admissions()) {
            $actions['approve_admissions'] = __('Approve Admissions', 'school-management-system');
            $actions['reject_admissions'] = __('Reject Admissions', 'school-management-system');
        }
        if ($this->can_manage_students()) {
            $actions['enroll_students'] = __('Enroll Students', 'school-management-system');
        }
        return $actions;
    }

    /**
     * Handle bulk admission actions.
     */
    public function handle_bulk_admission_actions($redirect_to, $doaction, $post_ids) {
        if (!in_array($doaction, ['approve_admissions', 'reject_admissions', 'enroll_students'])) {
            return $redirect_to;
        }

        $processed = 0;
        $errors = 0;

        foreach ($post_ids as $post_id) {
            switch ($doaction) {
                case 'approve_admissions':
                    $result = $this->approve_admission($post_id);
                    break;
                case 'reject_admissions':
                    $result = $this->reject_admission($post_id, __('Bulk rejection', 'school-management-system'));
                    break;
                case 'enroll_students':
                    $result = $this->enroll_student($post_id);
                    break;
            }

            if (is_wp_error($result)) {
                $errors++;
            } else {
                $processed++;
            }
        }

        $redirect_to = add_query_arg([
            'bulk_action' => $doaction,
            'processed' => $processed,
            'errors' => $errors
        ], $redirect_to);

        return $redirect_to;
    }

    /**
     * Send admission approval notification.
     */
    public function send_admission_approval_notification($student_id) {
        $student_name = get_field('full_name', $student_id);
        $admission_number = get_field('admission_number', $student_id);
        $parent_details = get_field('parent_details', $student_id);

        if (!is_array($parent_details) || empty($parent_details)) {
            return;
        }

        $school_name = get_option('sms_school_name', get_bloginfo('name'));
        
        foreach ($parent_details as $parent) {
            if (empty($parent['parent_email']) && empty($parent['parent_phone'])) {
                continue;
            }

            // Send email notification
            if (!empty($parent['parent_email'])) {
                $subject = sprintf(__('Admission Approved - %s', 'school-management-system'), $school_name);
                $message = sprintf(
                    __('Dear %s,

We are pleased to inform you that %s\'s admission to %s has been approved.

Admission Number: %s

Please contact the school office to complete the enrollment process.

Best regards,
%s Administration', 'school-management-system'),
                    $parent['parent_name'],
                    $student_name,
                    $school_name,
                    $admission_number,
                    $school_name
                );

                wp_mail($parent['parent_email'], $subject, $message);
            }

            // Send SMS notification if SMS service is available
            if (!empty($parent['parent_phone']) && class_exists('SMS_Communication_Handler')) {
                $sms_message = sprintf(
                    __('Dear %s, %s\'s admission to %s has been approved. Admission No: %s. Please contact school office to complete enrollment.', 'school-management-system'),
                    $parent['parent_name'],
                    $student_name,
                    $school_name,
                    $admission_number
                );

                $sms_handler = new SMS_Communication_Handler();
                $sms_handler->send_sms([$parent['parent_phone']], $sms_message);
            }
        }

        // Log the notification
        $this->log("Admission approval notification sent for student {$student_id}", 'info', [
            'student_id' => $student_id,
            'student_name' => $student_name,
            'admission_number' => $admission_number
        ]);
    }

    /**
     * Send enrollment welcome notification.
     */
    public function send_enrollment_welcome_notification($student_id) {
        $student_name = get_field('full_name', $student_id);
        $admission_number = get_field('admission_number', $student_id);
        $assigned_class = get_field('assigned_class', $student_id);
        $parent_details = get_field('parent_details', $student_id);

        if (!is_array($parent_details) || empty($parent_details)) {
            return;
        }

        $school_name = get_option('sms_school_name', get_bloginfo('name'));
        $class_name = $assigned_class ? get_the_title($assigned_class) : __('To be assigned', 'school-management-system');
        
        foreach ($parent_details as $parent) {
            if (empty($parent['parent_email']) && empty($parent['parent_phone'])) {
                continue;
            }

            // Send email notification
            if (!empty($parent['parent_email'])) {
                $subject = sprintf(__('Welcome to %s - Enrollment Confirmed', 'school-management-system'), $school_name);
                $message = sprintf(
                    __('Dear %s,

Welcome to %s! We are excited to confirm that %s has been successfully enrolled.

Student Details:
- Name: %s
- Admission Number: %s
- Class: %s

Please ensure you have completed all required documentation and fee payments.

We look forward to a successful academic journey together.

Best regards,
%s Administration', 'school-management-system'),
                    $parent['parent_name'],
                    $school_name,
                    $student_name,
                    $student_name,
                    $admission_number,
                    $class_name,
                    $school_name
                );

                wp_mail($parent['parent_email'], $subject, $message);
            }

            // Send SMS notification
            if (!empty($parent['parent_phone']) && class_exists('SMS_Communication_Handler')) {
                $sms_message = sprintf(
                    __('Welcome to %s! %s has been enrolled successfully. Admission No: %s, Class: %s. Thank you for choosing us.', 'school-management-system'),
                    $school_name,
                    $student_name,
                    $admission_number,
                    $class_name
                );

                $sms_handler = new SMS_Communication_Handler();
                $sms_handler->send_sms([$parent['parent_phone']], $sms_message);
            }
        }

        // Log the notification
        $this->log("Enrollment welcome notification sent for student {$student_id}", 'info', [
            'student_id' => $student_id,
            'student_name' => $student_name,
            'admission_number' => $admission_number,
            'class_name' => $class_name
        ]);
    }

    /**
     * Get students by admission status.
     */
    public function get_students_by_status($status, $limit = -1) {
        $args = [
            'post_type' => 'sms_students',
            'posts_per_page' => $limit,
            'meta_query' => [
                [
                    'key' => 'admission_status',
                    'value' => $status,
                    'compare' => '='
                ]
            ],
            'orderby' => 'date',
            'order' => 'DESC'
        ];

        return get_posts($args);
    }

    /**
     * Get admission statistics.
     */
    public function get_admission_statistics($date_from = null, $date_to = null) {
        $date_query = [];
        if ($date_from || $date_to) {
            $date_query = [
                'date_query' => [
                    [
                        'after' => $date_from ?: '1970-01-01',
                        'before' => $date_to ?: date('Y-m-d'),
                        'inclusive' => true
                    ]
                ]
            ];
        }

        $statuses = [
            self::STATUS_PENDING,
            self::STATUS_APPROVED,
            self::STATUS_REJECTED,
            self::STATUS_ENROLLED
        ];

        $statistics = [];
        foreach ($statuses as $status) {
            $args = array_merge([
                'post_type' => 'sms_students',
                'posts_per_page' => -1,
                'meta_query' => [
                    [
                        'key' => 'admission_status',
                        'value' => $status,
                        'compare' => '='
                    ]
                ],
                'fields' => 'ids'
            ], $date_query);

            $query = new WP_Query($args);
            $statistics[$status] = $query->found_posts;
        }

        return $statistics;
    }
}