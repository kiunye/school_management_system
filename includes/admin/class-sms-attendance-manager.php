<?php
/**
 * Attendance Management Interface
 *
 * @package SchoolManagementSystem
 * @subpackage Admin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class SMS_Attendance_Manager
 * 
 * Handles the attendance marking interface and bulk operations
 */
class SMS_Attendance_Manager {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_sms_mark_attendance', array($this, 'ajax_mark_attendance'));
        add_action('wp_ajax_sms_get_attendance_data', array($this, 'ajax_get_attendance_data'));
        add_action('wp_ajax_sms_bulk_mark_all_present', array($this, 'ajax_bulk_mark_all_present'));
        add_action('wp_ajax_sms_bulk_mark_all_absent', array($this, 'ajax_bulk_mark_all_absent'));
    }

    /**
     * Add admin menu for attendance management
     */
    public function add_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=sms_attendance',
            __('Mark Attendance', 'school-management-system'),
            __('Mark Attendance', 'school-management-system'),
            'manage_attendance',
            'sms-mark-attendance',
            array($this, 'render_attendance_page')
        );
    }

    /**
     * Enqueue scripts and styles for attendance management
     */
    public function enqueue_scripts($hook) {
        if (strpos($hook, 'sms-mark-attendance') === false) {
            return;
        }

        wp_enqueue_script('jquery');
        wp_enqueue_script('jquery-ui-datepicker');
        wp_enqueue_style('jquery-ui-datepicker', 'https://code.jquery.com/ui/1.12.1/themes/ui-lightness/jquery-ui.css');
        
        wp_enqueue_script(
            'sms-attendance-manager',
            plugin_dir_url(__FILE__) . '../../admin/js/attendance-manager.js',
            array('jquery', 'jquery-ui-datepicker'),
            '1.0.0',
            true
        );

        wp_enqueue_style(
            'sms-attendance-manager',
            plugin_dir_url(__FILE__) . '../../admin/css/attendance-manager.css',
            array(),
            '1.0.0'
        );

        wp_localize_script('sms-attendance-manager', 'smsAttendance', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sms_attendance_nonce'),
            'strings' => array(
                'loading' => __('Loading...', 'school-management-system'),
                'saving' => __('Saving...', 'school-management-system'),
                'saved' => __('Attendance saved successfully!', 'school-management-system'),
                'error' => __('Error saving attendance. Please try again.', 'school-management-system'),
                'selectClass' => __('Please select a class first.', 'school-management-system'),
                'selectDate' => __('Please select a date.', 'school-management-system'),
                'confirmMarkAll' => __('Are you sure you want to mark all students as {status}?', 'school-management-system'),
                'present' => __('Present', 'school-management-system'),
                'absent' => __('Absent', 'school-management-system'),
                'late' => __('Late', 'school-management-system'),
                'excused' => __('Excused', 'school-management-system'),
            )
        ));
    }

    /**
     * Render the attendance marking page
     */
    public function render_attendance_page() {
        // Get all active classes
        $classes = get_posts(array(
            'post_type' => 'sms_classes',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => 'class_status',
                    'value' => 'active',
                    'compare' => '='
                )
            ),
            'orderby' => 'title',
            'order' => 'ASC'
        ));

        ?>
        <div class="wrap">
            <h1><?php _e('Mark Attendance', 'school-management-system'); ?></h1>
            
            <div class="sms-attendance-container">
                <!-- Attendance Selection Form -->
                <div class="sms-attendance-form-section">
                    <div class="sms-card">
                        <h2><?php _e('Select Class and Date', 'school-management-system'); ?></h2>
                        
                        <form id="sms-attendance-form" class="sms-attendance-selection">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="attendance-class"><?php _e('Class', 'school-management-system'); ?></label>
                                    <select id="attendance-class" name="class_id" required>
                                        <option value=""><?php _e('Select a class...', 'school-management-system'); ?></option>
                                        <?php foreach ($classes as $class): ?>
                                            <option value="<?php echo esc_attr($class->ID); ?>">
                                                <?php echo esc_html(get_field('class_name', $class->ID) ?: $class->post_title); ?>
                                                (<?php echo esc_html(get_field('class_code', $class->ID)); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="attendance-date"><?php _e('Date', 'school-management-system'); ?></label>
                                    <input type="text" id="attendance-date" name="attendance_date" 
                                           value="<?php echo esc_attr(date('Y-m-d')); ?>" 
                                           placeholder="<?php _e('Select date...', 'school-management-system'); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <button type="button" id="load-attendance" class="button button-primary">
                                        <?php _e('Load Students', 'school-management-system'); ?>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Attendance Marking Section -->
                <div id="attendance-marking-section" class="sms-attendance-marking" style="display: none;">
                    <div class="sms-card">
                        <div class="attendance-header">
                            <h2 id="attendance-title"><?php _e('Mark Attendance', 'school-management-system'); ?></h2>
                            
                            <!-- Bulk Actions -->
                            <div class="bulk-actions">
                                <button type="button" id="mark-all-present" class="button">
                                    <?php _e('Mark All Present', 'school-management-system'); ?>
                                </button>
                                <button type="button" id="mark-all-absent" class="button">
                                    <?php _e('Mark All Absent', 'school-management-system'); ?>
                                </button>
                                <button type="button" id="save-attendance" class="button button-primary">
                                    <?php _e('Save Attendance', 'school-management-system'); ?>
                                </button>
                            </div>
                        </div>

                        <!-- Attendance Summary -->
                        <div class="attendance-summary">
                            <div class="summary-item">
                                <span class="label"><?php _e('Total Students:', 'school-management-system'); ?></span>
                                <span id="total-students" class="value">0</span>
                            </div>
                            <div class="summary-item">
                                <span class="label"><?php _e('Present:', 'school-management-system'); ?></span>
                                <span id="present-count" class="value present">0</span>
                            </div>
                            <div class="summary-item">
                                <span class="label"><?php _e('Absent:', 'school-management-system'); ?></span>
                                <span id="absent-count" class="value absent">0</span>
                            </div>
                            <div class="summary-item">
                                <span class="label"><?php _e('Late:', 'school-management-system'); ?></span>
                                <span id="late-count" class="value late">0</span>
                            </div>
                            <div class="summary-item">
                                <span class="label"><?php _e('Excused:', 'school-management-system'); ?></span>
                                <span id="excused-count" class="value excused">0</span>
                            </div>
                        </div>

                        <!-- Students List -->
                        <div class="students-attendance-list">
                            <div class="list-header">
                                <div class="student-info"><?php _e('Student', 'school-management-system'); ?></div>
                                <div class="attendance-status"><?php _e('Status', 'school-management-system'); ?></div>
                                <div class="attendance-notes"><?php _e('Notes', 'school-management-system'); ?></div>
                            </div>
                            
                            <div id="students-list" class="students-list">
                                <!-- Students will be loaded here via AJAX -->
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Loading Indicator -->
                <div id="loading-indicator" class="loading-indicator" style="display: none;">
                    <div class="spinner"></div>
                    <p><?php _e('Loading students...', 'school-management-system'); ?></p>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX handler to get attendance data for a class and date
     */
    public function ajax_get_attendance_data() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_attendance_nonce')) {
            wp_send_json_error(__('Security check failed', 'school-management-system'));
        }

        // Check user capabilities
        if (!current_user_can('manage_attendance')) {
            wp_send_json_error(__('You do not have permission to view attendance', 'school-management-system'));
        }

        $class_id = intval($_POST['class_id']);
        $attendance_date = sanitize_text_field($_POST['attendance_date']);

        if (!$class_id || !$attendance_date) {
            wp_send_json_error(__('Missing required data', 'school-management-system'));
        }

        // Get class information
        $class = get_post($class_id);
        if (!$class || $class->post_type !== 'sms_classes') {
            wp_send_json_error(__('Invalid class selected', 'school-management-system'));
        }

        // Get students enrolled in this class
        $students = get_posts(array(
            'post_type' => 'sms_students',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => 'assigned_class',
                    'value' => $class_id,
                    'compare' => '='
                ),
                array(
                    'key' => 'student_status',
                    'value' => 'active',
                    'compare' => '='
                )
            ),
            'orderby' => 'meta_value',
            'meta_key' => 'full_name',
            'order' => 'ASC'
        ));

        // Check for existing attendance record
        $existing_attendance = $this->get_existing_attendance($class_id, $attendance_date);
        $existing_data = array();
        
        if ($existing_attendance) {
            $attendance_data = get_field('student_attendance_data', $existing_attendance->ID);
            if ($attendance_data) {
                $decoded_data = json_decode($attendance_data, true);
                if ($decoded_data) {
                    foreach ($decoded_data as $record) {
                        $existing_data[$record['student_id']] = $record;
                    }
                }
            }
        }

        // Prepare student data
        $student_data = array();
        foreach ($students as $student) {
            $student_id = $student->ID;
            $admission_number = get_field('admission_number', $student_id);
            $full_name = get_field('full_name', $student_id);
            
            // Get existing attendance status or default to present
            $existing_record = isset($existing_data[$student_id]) ? $existing_data[$student_id] : null;
            
            $student_data[] = array(
                'id' => $student_id,
                'admission_number' => $admission_number,
                'full_name' => $full_name,
                'status' => $existing_record ? $existing_record['status'] : 'present',
                'notes' => $existing_record ? $existing_record['notes'] : '',
                'marked_time' => $existing_record ? $existing_record['marked_time'] : null,
            );
        }

        wp_send_json_success(array(
            'class_name' => get_field('class_name', $class_id) ?: $class->post_title,
            'class_code' => get_field('class_code', $class_id),
            'attendance_date' => $attendance_date,
            'students' => $student_data,
            'existing_record' => $existing_attendance ? $existing_attendance->ID : null,
        ));
    }

    /**
     * AJAX handler to mark attendance
     */
    public function ajax_mark_attendance() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_attendance_nonce')) {
            wp_send_json_error(__('Security check failed', 'school-management-system'));
        }

        // Check user capabilities
        if (!current_user_can('manage_attendance')) {
            wp_send_json_error(__('You do not have permission to mark attendance', 'school-management-system'));
        }

        $class_id = intval($_POST['class_id']);
        $attendance_date = sanitize_text_field($_POST['attendance_date']);
        $attendance_data = json_decode(stripslashes($_POST['attendance_data']), true);

        if (!$class_id || !$attendance_date || !$attendance_data) {
            wp_send_json_error(__('Missing required data', 'school-management-system'));
        }

        // Validate date format
        if (!$this->validate_date($attendance_date)) {
            wp_send_json_error(__('Invalid date format', 'school-management-system'));
        }

        // Check if attendance already exists for this class and date
        $existing_attendance = $this->get_existing_attendance($class_id, $attendance_date);
        
        if ($existing_attendance) {
            // Update existing attendance
            $post_id = $existing_attendance->ID;
            wp_update_post(array(
                'ID' => $post_id,
                'post_modified' => current_time('mysql'),
            ));
        } else {
            // Create new attendance record
            $class = get_post($class_id);
            $class_name = get_field('class_name', $class_id) ?: $class->post_title;
            
            $post_data = array(
                'post_title' => sprintf(
                    __('Attendance for %s on %s', 'school-management-system'),
                    $class_name,
                    date('d/m/Y', strtotime($attendance_date))
                ),
                'post_type' => 'sms_attendance',
                'post_status' => 'publish',
                'post_author' => get_current_user_id(),
            );

            $post_id = wp_insert_post($post_data);
            
            if (is_wp_error($post_id)) {
                wp_send_json_error(__('Failed to create attendance record', 'school-management-system'));
            }
        }

        // Prepare attendance data with timestamps
        $processed_data = array();
        $present_count = 0;
        $absent_count = 0;
        $late_count = 0;
        $excused_count = 0;

        foreach ($attendance_data as $student_data) {
            $processed_record = array(
                'student_id' => intval($student_data['student_id']),
                'status' => sanitize_text_field($student_data['status']),
                'notes' => sanitize_textarea_field($student_data['notes']),
                'marked_time' => current_time('mysql'),
                'marked_by' => get_current_user_id(),
            );

            $processed_data[] = $processed_record;

            // Count statuses
            switch ($processed_record['status']) {
                case 'present':
                    $present_count++;
                    break;
                case 'absent':
                    $absent_count++;
                    break;
                case 'late':
                    $late_count++;
                    break;
                case 'excused':
                    $excused_count++;
                    break;
            }
        }

        // Update ACF fields
        update_field('attendance_class', $class_id, $post_id);
        update_field('attendance_date', $attendance_date, $post_id);
        update_field('student_attendance_data', json_encode($processed_data), $post_id);
        update_field('marked_by_teacher', get_current_user_id(), $post_id);
        update_field('total_students', count($processed_data), $post_id);
        update_field('present_count', $present_count, $post_id);
        update_field('absent_count', $absent_count, $post_id);

        // Add custom meta for late and excused counts
        update_post_meta($post_id, '_late_count', $late_count);
        update_post_meta($post_id, '_excused_count', $excused_count);

        // Send notifications for absent students
        $this->send_absence_notifications($processed_data, $class_id, $attendance_date);

        wp_send_json_success(array(
            'message' => __('Attendance saved successfully', 'school-management-system'),
            'post_id' => $post_id,
            'counts' => array(
                'total' => count($processed_data),
                'present' => $present_count,
                'absent' => $absent_count,
                'late' => $late_count,
                'excused' => $excused_count,
            ),
        ));
    }

    /**
     * AJAX handler to bulk mark all students as present
     */
    public function ajax_bulk_mark_all_present() {
        $this->handle_bulk_mark('present');
    }

    /**
     * AJAX handler to bulk mark all students as absent
     */
    public function ajax_bulk_mark_all_absent() {
        $this->handle_bulk_mark('absent');
    }

    /**
     * Handle bulk marking operations
     */
    private function handle_bulk_mark($status) {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_attendance_nonce')) {
            wp_send_json_error(__('Security check failed', 'school-management-system'));
        }

        // Check user capabilities
        if (!current_user_can('manage_attendance')) {
            wp_send_json_error(__('You do not have permission to mark attendance', 'school-management-system'));
        }

        $class_id = intval($_POST['class_id']);
        
        if (!$class_id) {
            wp_send_json_error(__('Missing class ID', 'school-management-system'));
        }

        // Get students enrolled in this class
        $students = get_posts(array(
            'post_type' => 'sms_students',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => 'assigned_class',
                    'value' => $class_id,
                    'compare' => '='
                ),
                array(
                    'key' => 'student_status',
                    'value' => 'active',
                    'compare' => '='
                )
            ),
        ));

        $bulk_data = array();
        foreach ($students as $student) {
            $bulk_data[] = array(
                'student_id' => $student->ID,
                'status' => $status,
                'notes' => '',
            );
        }

        wp_send_json_success(array(
            'attendance_data' => $bulk_data,
            'message' => sprintf(__('All students marked as %s', 'school-management-system'), $status),
        ));
    }

    /**
     * Get existing attendance record for class and date
     */
    private function get_existing_attendance($class_id, $date) {
        $args = array(
            'post_type' => 'sms_attendance',
            'posts_per_page' => 1,
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => 'attendance_class',
                    'value' => $class_id,
                    'compare' => '='
                ),
                array(
                    'key' => 'attendance_date',
                    'value' => $date,
                    'compare' => '='
                )
            )
        );

        $posts = get_posts($args);
        return !empty($posts) ? $posts[0] : null;
    }

    /**
     * Validate date format
     */
    private function validate_date($date) {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }

    /**
     * Send absence notifications to parents
     */
    private function send_absence_notifications($attendance_data, $class_id, $date) {
        foreach ($attendance_data as $student_data) {
            if ($student_data['status'] === 'absent') {
                // Trigger absence notification hook
                do_action('sms_student_absent', $student_data['student_id'], $class_id, $date, $student_data);
            }
        }
    }
}

// Initialize the class
new SMS_Attendance_Manager();