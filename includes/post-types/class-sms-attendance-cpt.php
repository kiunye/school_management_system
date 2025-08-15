<?php
/**
 * Attendance Custom Post Type
 *
 * @package SchoolManagementSystem
 * @subpackage PostTypes
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class SMS_Attendance_CPT
 * 
 * Handles the attendance custom post type registration and functionality
 */
class SMS_Attendance_CPT {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'register_post_type'));
        add_action('acf/init', array($this, 'register_acf_fields'));
        add_action('wp_ajax_sms_bulk_attendance', array($this, 'handle_bulk_attendance'));
        add_action('wp_ajax_sms_get_class_students', array($this, 'get_class_students'));
        add_filter('manage_sms_attendance_posts_columns', array($this, 'set_custom_columns'));
        add_action('manage_sms_attendance_posts_custom_column', array($this, 'custom_column_content'), 10, 2);
    }

    /**
     * Register the attendance custom post type
     */
    public function register_post_type() {
        $labels = array(
            'name'                  => _x('Attendance Records', 'Post Type General Name', 'school-management-system'),
            'singular_name'         => _x('Attendance Record', 'Post Type Singular Name', 'school-management-system'),
            'menu_name'             => __('Attendance', 'school-management-system'),
            'name_admin_bar'        => __('Attendance', 'school-management-system'),
            'archives'              => __('Attendance Archives', 'school-management-system'),
            'attributes'            => __('Attendance Attributes', 'school-management-system'),
            'parent_item_colon'     => __('Parent Attendance:', 'school-management-system'),
            'all_items'             => __('All Attendance Records', 'school-management-system'),
            'add_new_item'          => __('Add New Attendance Record', 'school-management-system'),
            'add_new'               => __('Add New', 'school-management-system'),
            'new_item'              => __('New Attendance Record', 'school-management-system'),
            'edit_item'             => __('Edit Attendance Record', 'school-management-system'),
            'update_item'           => __('Update Attendance Record', 'school-management-system'),
            'view_item'             => __('View Attendance Record', 'school-management-system'),
            'view_items'            => __('View Attendance Records', 'school-management-system'),
            'search_items'          => __('Search Attendance Records', 'school-management-system'),
            'not_found'             => __('Not found', 'school-management-system'),
            'not_found_in_trash'    => __('Not found in Trash', 'school-management-system'),
            'featured_image'        => __('Featured Image', 'school-management-system'),
            'set_featured_image'    => __('Set featured image', 'school-management-system'),
            'remove_featured_image' => __('Remove featured image', 'school-management-system'),
            'use_featured_image'    => __('Use as featured image', 'school-management-system'),
            'insert_into_item'      => __('Insert into attendance record', 'school-management-system'),
            'uploaded_to_this_item' => __('Uploaded to this attendance record', 'school-management-system'),
            'items_list'            => __('Attendance records list', 'school-management-system'),
            'items_list_navigation' => __('Attendance records list navigation', 'school-management-system'),
            'filter_items_list'     => __('Filter attendance records list', 'school-management-system'),
        );

        $args = array(
            'label'                 => __('Attendance Record', 'school-management-system'),
            'description'           => __('Daily attendance records for classes', 'school-management-system'),
            'labels'                => $labels,
            'supports'              => array('title', 'editor', 'author', 'custom-fields'),
            'taxonomies'            => array(),
            'hierarchical'          => false,
            'public'                => false,
            'show_ui'               => true,
            'show_in_menu'          => true,
            'menu_position'         => 25,
            'menu_icon'             => 'dashicons-yes-alt',
            'show_in_admin_bar'     => true,
            'show_in_nav_menus'     => false,
            'can_export'            => true,
            'has_archive'           => false,
            'exclude_from_search'   => true,
            'publicly_queryable'    => false,
            'capability_type'       => 'post',
            'capabilities'          => array(
                'create_posts'       => 'manage_attendance',
                'edit_posts'         => 'manage_attendance',
                'edit_others_posts'  => 'manage_attendance',
                'publish_posts'      => 'manage_attendance',
                'read_private_posts' => 'manage_attendance',
                'delete_posts'       => 'manage_attendance',
                'delete_others_posts' => 'manage_attendance',
            ),
            'show_in_rest'          => true,
            'rest_base'             => 'attendance',
        );

        register_post_type('sms_attendance', $args);
    }

    /**
     * Register ACF field groups for attendance
     */
    public function register_acf_fields() {
        if (!function_exists('acf_add_local_field_group')) {
            return;
        }

        acf_add_local_field_group(array(
            'key' => 'group_attendance_details',
            'title' => 'Attendance Details',
            'fields' => array(
                array(
                    'key' => 'field_attendance_class',
                    'label' => 'Class',
                    'name' => 'attendance_class',
                    'type' => 'post_object',
                    'instructions' => 'Select the class for this attendance record',
                    'required' => 1,
                    'post_type' => array('sms_classes'),
                    'taxonomy' => '',
                    'allow_null' => 0,
                    'multiple' => 0,
                    'return_format' => 'object',
                    'ui' => 1,
                ),
                array(
                    'key' => 'field_attendance_date',
                    'label' => 'Attendance Date',
                    'name' => 'attendance_date',
                    'type' => 'date_picker',
                    'instructions' => 'Select the date for this attendance record',
                    'required' => 1,
                    'display_format' => 'd/m/Y',
                    'return_format' => 'Y-m-d',
                    'first_day' => 1,
                ),
                array(
                    'key' => 'field_student_attendance_data',
                    'label' => 'Student Attendance Data',
                    'name' => 'student_attendance_data',
                    'type' => 'textarea',
                    'instructions' => 'JSON data containing student attendance information (managed automatically)',
                    'required' => 0,
                    'rows' => 4,
                    'readonly' => 1,
                ),
                array(
                    'key' => 'field_marked_by_teacher',
                    'label' => 'Marked by Teacher',
                    'name' => 'marked_by_teacher',
                    'type' => 'user',
                    'instructions' => 'Teacher who marked this attendance',
                    'required' => 0,
                    'role' => array('sms_teacher', 'administrator'),
                    'allow_null' => 0,
                    'multiple' => 0,
                    'return_format' => 'object',
                ),
                array(
                    'key' => 'field_attendance_notes',
                    'label' => 'Notes',
                    'name' => 'attendance_notes',
                    'type' => 'textarea',
                    'instructions' => 'Additional notes about this attendance record',
                    'required' => 0,
                    'rows' => 3,
                ),
                array(
                    'key' => 'field_total_students',
                    'label' => 'Total Students',
                    'name' => 'total_students',
                    'type' => 'number',
                    'instructions' => 'Total number of students in the class',
                    'required' => 0,
                    'readonly' => 1,
                ),
                array(
                    'key' => 'field_present_count',
                    'label' => 'Present Count',
                    'name' => 'present_count',
                    'type' => 'number',
                    'instructions' => 'Number of students present',
                    'required' => 0,
                    'readonly' => 1,
                ),
                array(
                    'key' => 'field_absent_count',
                    'label' => 'Absent Count',
                    'name' => 'absent_count',
                    'type' => 'number',
                    'instructions' => 'Number of students absent',
                    'required' => 0,
                    'readonly' => 1,
                ),
            ),
            'location' => array(
                array(
                    array(
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'sms_attendance',
                    ),
                ),
            ),
            'menu_order' => 0,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
        ));
    }

    /**
     * Set custom columns for attendance list
     */
    public function set_custom_columns($columns) {
        $new_columns = array();
        $new_columns['cb'] = $columns['cb'];
        $new_columns['title'] = __('Attendance Record', 'school-management-system');
        $new_columns['class'] = __('Class', 'school-management-system');
        $new_columns['date'] = __('Date', 'school-management-system');
        $new_columns['present'] = __('Present', 'school-management-system');
        $new_columns['absent'] = __('Absent', 'school-management-system');
        $new_columns['teacher'] = __('Marked By', 'school-management-system');
        $new_columns['date_created'] = __('Created', 'school-management-system');
        
        return $new_columns;
    }

    /**
     * Display custom column content
     */
    public function custom_column_content($column, $post_id) {
        switch ($column) {
            case 'class':
                $class = get_field('attendance_class', $post_id);
                if ($class) {
                    echo esc_html($class->post_title);
                } else {
                    echo '—';
                }
                break;
                
            case 'date':
                $date = get_field('attendance_date', $post_id);
                if ($date) {
                    echo esc_html(date('d/m/Y', strtotime($date)));
                } else {
                    echo '—';
                }
                break;
                
            case 'present':
                $present_count = get_field('present_count', $post_id);
                echo $present_count ? esc_html($present_count) : '0';
                break;
                
            case 'absent':
                $absent_count = get_field('absent_count', $post_id);
                echo $absent_count ? esc_html($absent_count) : '0';
                break;
                
            case 'teacher':
                $teacher = get_field('marked_by_teacher', $post_id);
                if ($teacher) {
                    echo esc_html($teacher->display_name);
                } else {
                    echo '—';
                }
                break;
                
            case 'date_created':
                echo get_the_date('d/m/Y H:i', $post_id);
                break;
        }
    }

    /**
     * Handle bulk attendance marking via AJAX
     */
    public function handle_bulk_attendance() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_bulk_attendance_nonce')) {
            wp_die(__('Security check failed', 'school-management-system'));
        }

        // Check user capabilities
        if (!current_user_can('manage_attendance')) {
            wp_die(__('You do not have permission to mark attendance', 'school-management-system'));
        }

        $class_id = intval($_POST['class_id']);
        $attendance_date = sanitize_text_field($_POST['attendance_date']);
        $attendance_data = json_decode(stripslashes($_POST['attendance_data']), true);

        if (!$class_id || !$attendance_date || !$attendance_data) {
            wp_send_json_error(__('Missing required data', 'school-management-system'));
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
            $post_data = array(
                'post_title' => sprintf(
                    __('Attendance for %s on %s', 'school-management-system'),
                    get_the_title($class_id),
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

        // Update ACF fields
        update_field('attendance_class', $class_id, $post_id);
        update_field('attendance_date', $attendance_date, $post_id);
        update_field('student_attendance_data', json_encode($attendance_data), $post_id);
        update_field('marked_by_teacher', get_current_user_id(), $post_id);

        // Calculate and update counts
        $present_count = 0;
        $absent_count = 0;
        $total_students = count($attendance_data);

        foreach ($attendance_data as $student_data) {
            if ($student_data['status'] === 'present') {
                $present_count++;
            } else {
                $absent_count++;
            }
        }

        update_field('total_students', $total_students, $post_id);
        update_field('present_count', $present_count, $post_id);
        update_field('absent_count', $absent_count, $post_id);

        // Send notifications for absent students
        $this->send_absence_notifications($attendance_data, $class_id, $attendance_date);

        wp_send_json_success(array(
            'message' => __('Attendance marked successfully', 'school-management-system'),
            'post_id' => $post_id,
            'present_count' => $present_count,
            'absent_count' => $absent_count,
        ));
    }

    /**
     * Get students for a specific class via AJAX
     */
    public function get_class_students() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_get_students_nonce')) {
            wp_die(__('Security check failed', 'school-management-system'));
        }

        $class_id = intval($_POST['class_id']);
        
        if (!$class_id) {
            wp_send_json_error(__('Invalid class ID', 'school-management-system'));
        }

        // Get students enrolled in this class
        $students = get_posts(array(
            'post_type' => 'sms_students',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => 'current_class',
                    'value' => $class_id,
                    'compare' => '='
                )
            ),
            'orderby' => 'title',
            'order' => 'ASC'
        ));

        $student_data = array();
        foreach ($students as $student) {
            $student_data[] = array(
                'id' => $student->ID,
                'name' => $student->post_title,
                'admission_number' => get_field('admission_number', $student->ID),
            );
        }

        wp_send_json_success($student_data);
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
     * Send absence notifications to parents
     */
    private function send_absence_notifications($attendance_data, $class_id, $date) {
        foreach ($attendance_data as $student_data) {
            if ($student_data['status'] === 'absent') {
                // Trigger absence notification
                do_action('sms_student_absent', $student_data['student_id'], $class_id, $date);
            }
        }
    }

    /**
     * Get attendance statistics for a class
     */
    public function get_class_attendance_stats($class_id, $start_date = null, $end_date = null) {
        $meta_query = array(
            array(
                'key' => 'attendance_class',
                'value' => $class_id,
                'compare' => '='
            )
        );

        if ($start_date && $end_date) {
            $meta_query[] = array(
                'key' => 'attendance_date',
                'value' => array($start_date, $end_date),
                'compare' => 'BETWEEN',
                'type' => 'DATE'
            );
        }

        $attendance_records = get_posts(array(
            'post_type' => 'sms_attendance',
            'posts_per_page' => -1,
            'meta_query' => $meta_query
        ));

        $stats = array(
            'total_days' => count($attendance_records),
            'total_present' => 0,
            'total_absent' => 0,
            'average_attendance' => 0
        );

        foreach ($attendance_records as $record) {
            $stats['total_present'] += intval(get_field('present_count', $record->ID));
            $stats['total_absent'] += intval(get_field('absent_count', $record->ID));
        }

        $total_attendance = $stats['total_present'] + $stats['total_absent'];
        if ($total_attendance > 0) {
            $stats['average_attendance'] = round(($stats['total_present'] / $total_attendance) * 100, 2);
        }

        return $stats;
    }
}

// Initialize the class
new SMS_Attendance_CPT();