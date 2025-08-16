<?php
/**
 * Timetable Builder Interface
 *
 * @package SchoolManagementSystem
 * @subpackage Admin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class SMS_Timetable_Builder
 * 
 * Handles the drag-and-drop timetable creation interface with conflict detection
 */
class SMS_Timetable_Builder {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_timetable_builder_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_builder_assets'));
        add_action('wp_ajax_sms_save_timetable_builder', array($this, 'save_timetable_builder'));
        add_action('wp_ajax_sms_load_timetable_data', array($this, 'load_timetable_data'));
        add_action('wp_ajax_sms_validate_time_slot', array($this, 'validate_time_slot'));
        add_action('wp_ajax_sms_get_available_teachers', array($this, 'get_available_teachers'));
        add_action('wp_ajax_sms_get_class_subjects', array($this, 'get_class_subjects'));
    }

    /**
     * Add timetable builder menu
     */
    public function add_timetable_builder_menu() {
        add_submenu_page(
            'edit.php?post_type=sms_timetables',
            __('Timetable Builder', 'school-management-system'),
            __('Timetable Builder', 'school-management-system'),
            'manage_timetables',
            'sms-timetable-builder',
            array($this, 'display_timetable_builder')
        );
    }

    /**
     * Enqueue builder assets
     */
    public function enqueue_builder_assets($hook) {
        if ($hook !== 'sms_timetables_page_sms-timetable-builder') {
            return;
        }

        // Enqueue CSS
        wp_enqueue_style(
            'sms-timetable-builder',
            SMS_PLUGIN_URL . 'admin/css/timetable-builder.css',
            array(),
            SMS_VERSION
        );

        // Enqueue JavaScript
        wp_enqueue_script(
            'sms-timetable-builder',
            SMS_PLUGIN_URL . 'admin/js/timetable-builder.js',
            array('jquery', 'jquery-ui-draggable', 'jquery-ui-droppable', 'jquery-ui-sortable'),
            SMS_VERSION,
            true
        );

        // Localize script
        wp_localize_script(
            'sms-timetable-builder',
            'sms_timetable_builder',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('sms_timetable_builder_nonce'),
                'strings' => array(
                    'save_success' => __('Timetable saved successfully!', 'school-management-system'),
                    'save_error' => __('Error saving timetable. Please try again.', 'school-management-system'),
                    'conflict_detected' => __('Conflict detected!', 'school-management-system'),
                    'teacher_unavailable' => __('Teacher is not available at this time.', 'school-management-system'),
                    'room_occupied' => __('Room is already occupied at this time.', 'school-management-system'),
                    'confirm_delete_slot' => __('Are you sure you want to delete this time slot?', 'school-management-system'),
                    'loading' => __('Loading...', 'school-management-system'),
                    'no_conflicts' => __('No conflicts detected.', 'school-management-system')
                )
            )
        );
    }

    /**
     * Display timetable builder interface
     */
    public function display_timetable_builder() {
        if (!current_user_can('manage_timetables')) {
            wp_die(__('You do not have permission to access this page.', 'school-management-system'));
        }

        // Get classes for dropdown
        $classes = get_posts(array(
            'post_type' => 'sms_classes',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC'
        ));

        // Get academic years
        $academic_years = get_terms(array(
            'taxonomy' => 'sms_academic_years',
            'hide_empty' => false
        ));

        // Get terms
        $terms = get_terms(array(
            'taxonomy' => 'sms_terms',
            'hide_empty' => false
        ));

        // Get subjects
        $subjects = get_terms(array(
            'taxonomy' => 'sms_subjects',
            'hide_empty' => false
        ));

        // Get teachers
        $teachers = get_users(array(
            'role__in' => array('sms_teacher', 'administrator'),
            'orderby' => 'display_name',
            'order' => 'ASC'
        ));

        include SMS_PLUGIN_DIR . 'admin/partials/timetable-builder.php';
    }

    /**
     * Save timetable builder data via AJAX
     */
    public function save_timetable_builder() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_timetable_builder_nonce')) {
            wp_send_json_error(__('Security check failed', 'school-management-system'));
        }

        // Check user capabilities
        if (!current_user_can('manage_timetables')) {
            wp_send_json_error(__('You do not have permission to save timetables', 'school-management-system'));
        }

        $timetable_id = intval($_POST['timetable_id']);
        $class_id = intval($_POST['class_id']);
        $academic_year = sanitize_text_field($_POST['academic_year']);
        $term = sanitize_text_field($_POST['term']);
        $time_slots = json_decode(stripslashes($_POST['time_slots']), true);

        if (!$class_id || !$academic_year || !$term) {
            wp_send_json_error(__('Missing required fields', 'school-management-system'));
        }

        // Validate time slots for conflicts
        $conflicts = $this->detect_time_slot_conflicts($time_slots, $timetable_id);
        if (!empty($conflicts)) {
            wp_send_json_error(array(
                'message' => __('Conflicts detected in timetable', 'school-management-system'),
                'conflicts' => $conflicts
            ));
        }

        try {
            // Create or update timetable
            if ($timetable_id > 0) {
                // Update existing timetable
                $post_data = array(
                    'ID' => $timetable_id,
                    'post_title' => get_the_title($class_id) . ' - ' . $academic_year . ' - ' . $term,
                    'post_status' => 'publish'
                );
                $result = wp_update_post($post_data);
            } else {
                // Create new timetable
                $post_data = array(
                    'post_type' => 'sms_timetables',
                    'post_title' => get_the_title($class_id) . ' - ' . $academic_year . ' - ' . $term,
                    'post_status' => 'publish',
                    'post_author' => get_current_user_id()
                );
                $result = wp_insert_post($post_data);
                $timetable_id = $result;
            }

            if (is_wp_error($result)) {
                wp_send_json_error(__('Error creating/updating timetable', 'school-management-system'));
            }

            // Update ACF fields
            update_field('timetable_class', $class_id, $timetable_id);
            update_field('academic_year', $academic_year, $timetable_id);
            update_field('term', $term, $timetable_id);
            update_field('created_by_teacher', get_current_user_id(), $timetable_id);
            update_field('timetable_status', 'active', $timetable_id);
            update_field('effective_date', current_time('Y-m-d'), $timetable_id);
            update_field('last_modified', current_time('mysql'), $timetable_id);

            // Process and save time slots
            $processed_slots = $this->process_time_slots_for_acf($time_slots);
            update_field('time_slots', $processed_slots, $timetable_id);

            // Generate schedule data JSON
            $schedule_data = $this->generate_schedule_data($time_slots);
            update_field('schedule_data', json_encode($schedule_data), $timetable_id);

            wp_send_json_success(array(
                'message' => __('Timetable saved successfully!', 'school-management-system'),
                'timetable_id' => $timetable_id
            ));

        } catch (Exception $e) {
            wp_send_json_error(__('An error occurred while saving the timetable', 'school-management-system'));
        }
    }

    /**
     * Load timetable data via AJAX
     */
    public function load_timetable_data() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_timetable_builder_nonce')) {
            wp_send_json_error(__('Security check failed', 'school-management-system'));
        }

        $timetable_id = intval($_POST['timetable_id']);
        
        if (!$timetable_id) {
            wp_send_json_error(__('Invalid timetable ID', 'school-management-system'));
        }

        $timetable_data = array(
            'class_id' => get_field('timetable_class', $timetable_id)->ID ?? 0,
            'academic_year' => get_field('academic_year', $timetable_id)->slug ?? '',
            'term' => get_field('term', $timetable_id)->slug ?? '',
            'time_slots' => get_field('time_slots', $timetable_id) ?? array(),
            'status' => get_field('timetable_status', $timetable_id) ?? 'draft'
        );

        wp_send_json_success($timetable_data);
    }

    /**
     * Validate time slot for conflicts via AJAX
     */
    public function validate_time_slot() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_timetable_builder_nonce')) {
            wp_send_json_error(__('Security check failed', 'school-management-system'));
        }

        $teacher_id = intval($_POST['teacher_id']);
        $day = sanitize_text_field($_POST['day']);
        $start_time = sanitize_text_field($_POST['start_time']);
        $end_time = sanitize_text_field($_POST['end_time']);
        $room = sanitize_text_field($_POST['room']);
        $exclude_timetable_id = intval($_POST['exclude_timetable_id']);

        $conflicts = array();

        // Check teacher conflicts
        if ($teacher_id) {
            $teacher_conflicts = $this->check_teacher_availability($teacher_id, $day, $start_time, $end_time, $exclude_timetable_id);
            if (!empty($teacher_conflicts)) {
                $conflicts['teacher'] = $teacher_conflicts;
            }
        }

        // Check room conflicts
        if ($room) {
            $room_conflicts = $this->check_room_availability($room, $day, $start_time, $end_time, $exclude_timetable_id);
            if (!empty($room_conflicts)) {
                $conflicts['room'] = $room_conflicts;
            }
        }

        if (empty($conflicts)) {
            wp_send_json_success(array('message' => __('No conflicts detected', 'school-management-system')));
        } else {
            wp_send_json_error(array(
                'message' => __('Conflicts detected', 'school-management-system'),
                'conflicts' => $conflicts
            ));
        }
    }

    /**
     * Get available teachers for a time slot via AJAX
     */
    public function get_available_teachers() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_timetable_builder_nonce')) {
            wp_send_json_error(__('Security check failed', 'school-management-system'));
        }

        $day = sanitize_text_field($_POST['day']);
        $start_time = sanitize_text_field($_POST['start_time']);
        $end_time = sanitize_text_field($_POST['end_time']);
        $exclude_timetable_id = intval($_POST['exclude_timetable_id']);

        // Get all teachers
        $all_teachers = get_users(array(
            'role__in' => array('sms_teacher', 'administrator'),
            'orderby' => 'display_name',
            'order' => 'ASC'
        ));

        $available_teachers = array();

        foreach ($all_teachers as $teacher) {
            $conflicts = $this->check_teacher_availability($teacher->ID, $day, $start_time, $end_time, $exclude_timetable_id);
            if (empty($conflicts)) {
                $available_teachers[] = array(
                    'id' => $teacher->ID,
                    'name' => $teacher->display_name,
                    'email' => $teacher->user_email
                );
            }
        }

        wp_send_json_success($available_teachers);
    }

    /**
     * Get subjects for a class via AJAX
     */
    public function get_class_subjects() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_timetable_builder_nonce')) {
            wp_send_json_error(__('Security check failed', 'school-management-system'));
        }

        $class_id = intval($_POST['class_id']);
        
        if (!$class_id) {
            wp_send_json_error(__('Invalid class ID', 'school-management-system'));
        }

        // Get class grade level
        $grade_level = get_field('grade_level', $class_id);
        
        // Get subjects for this grade level
        $subjects = get_terms(array(
            'taxonomy' => 'sms_subjects',
            'hide_empty' => false,
            'meta_query' => array(
                array(
                    'key' => 'applicable_grades',
                    'value' => $grade_level,
                    'compare' => 'LIKE'
                )
            )
        ));

        // If no grade-specific subjects found, get all subjects
        if (empty($subjects)) {
            $subjects = get_terms(array(
                'taxonomy' => 'sms_subjects',
                'hide_empty' => false
            ));
        }

        $subject_data = array();
        foreach ($subjects as $subject) {
            $subject_data[] = array(
                'id' => $subject->term_id,
                'name' => $subject->name,
                'slug' => $subject->slug
            );
        }

        wp_send_json_success($subject_data);
    }

    /**
     * Detect conflicts in time slots
     */
    private function detect_time_slot_conflicts($time_slots, $exclude_timetable_id = 0) {
        $conflicts = array();

        foreach ($time_slots as $index => $slot) {
            if (empty($slot['teacher_id']) || empty($slot['day']) || empty($slot['start_time']) || empty($slot['end_time'])) {
                continue;
            }

            // Check teacher conflicts
            $teacher_conflicts = $this->check_teacher_availability(
                $slot['teacher_id'],
                $slot['day'],
                $slot['start_time'],
                $slot['end_time'],
                $exclude_timetable_id
            );

            if (!empty($teacher_conflicts)) {
                $conflicts[] = array(
                    'slot_index' => $index,
                    'type' => 'teacher_conflict',
                    'message' => sprintf(
                        __('Teacher %s is not available on %s from %s to %s', 'school-management-system'),
                        get_userdata($slot['teacher_id'])->display_name,
                        ucfirst($slot['day']),
                        $slot['start_time'],
                        $slot['end_time']
                    ),
                    'conflicts_with' => $teacher_conflicts
                );
            }

            // Check room conflicts if room is specified
            if (!empty($slot['room'])) {
                $room_conflicts = $this->check_room_availability(
                    $slot['room'],
                    $slot['day'],
                    $slot['start_time'],
                    $slot['end_time'],
                    $exclude_timetable_id
                );

                if (!empty($room_conflicts)) {
                    $conflicts[] = array(
                        'slot_index' => $index,
                        'type' => 'room_conflict',
                        'message' => sprintf(
                            __('Room %s is not available on %s from %s to %s', 'school-management-system'),
                            $slot['room'],
                            ucfirst($slot['day']),
                            $slot['start_time'],
                            $slot['end_time']
                        ),
                        'conflicts_with' => $room_conflicts
                    );
                }
            }
        }

        return $conflicts;
    }

    /**
     * Check teacher availability for a time slot
     */
    private function check_teacher_availability($teacher_id, $day, $start_time, $end_time, $exclude_timetable_id = 0) {
        // Get all active timetables
        $args = array(
            'post_type' => 'sms_timetables',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => 'timetable_status',
                    'value' => 'active',
                    'compare' => '='
                )
            )
        );

        if ($exclude_timetable_id > 0) {
            $args['post__not_in'] = array($exclude_timetable_id);
        }

        $timetables = get_posts($args);
        $conflicts = array();

        foreach ($timetables as $timetable) {
            $time_slots = get_field('time_slots', $timetable->ID);
            
            if (!$time_slots) {
                continue;
            }

            foreach ($time_slots as $slot) {
                if ($slot['teacher'] == $teacher_id && 
                    $slot['day'] == $day && 
                    $this->times_overlap($start_time, $end_time, $slot['start_time'], $slot['end_time'])) {
                    
                    $conflicts[] = array(
                        'timetable_id' => $timetable->ID,
                        'timetable_title' => $timetable->post_title,
                        'conflicting_slot' => $slot
                    );
                }
            }
        }

        return $conflicts;
    }

    /**
     * Check room availability for a time slot
     */
    private function check_room_availability($room, $day, $start_time, $end_time, $exclude_timetable_id = 0) {
        // Get all active timetables
        $args = array(
            'post_type' => 'sms_timetables',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => 'timetable_status',
                    'value' => 'active',
                    'compare' => '='
                )
            )
        );

        if ($exclude_timetable_id > 0) {
            $args['post__not_in'] = array($exclude_timetable_id);
        }

        $timetables = get_posts($args);
        $conflicts = array();

        foreach ($timetables as $timetable) {
            $time_slots = get_field('time_slots', $timetable->ID);
            
            if (!$time_slots) {
                continue;
            }

            foreach ($time_slots as $slot) {
                if (!empty($slot['room']) && 
                    strtolower($slot['room']) == strtolower($room) && 
                    $slot['day'] == $day && 
                    $this->times_overlap($start_time, $end_time, $slot['start_time'], $slot['end_time'])) {
                    
                    $conflicts[] = array(
                        'timetable_id' => $timetable->ID,
                        'timetable_title' => $timetable->post_title,
                        'conflicting_slot' => $slot
                    );
                }
            }
        }

        return $conflicts;
    }

    /**
     * Check if two time periods overlap
     */
    private function times_overlap($start1, $end1, $start2, $end2) {
        $start1_time = strtotime($start1);
        $end1_time = strtotime($end1);
        $start2_time = strtotime($start2);
        $end2_time = strtotime($end2);

        return ($start1_time < $end2_time) && ($end1_time > $start2_time);
    }

    /**
     * Process time slots for ACF format
     */
    private function process_time_slots_for_acf($time_slots) {
        $processed_slots = array();

        foreach ($time_slots as $slot) {
            $processed_slots[] = array(
                'day' => $slot['day'],
                'start_time' => $slot['start_time'],
                'end_time' => $slot['end_time'],
                'subject' => $slot['subject_id'],
                'teacher' => $slot['teacher_id'],
                'room' => $slot['room'],
                'slot_type' => $slot['slot_type'] ?? 'lesson'
            );
        }

        return $processed_slots;
    }

    /**
     * Generate schedule data JSON
     */
    private function generate_schedule_data($time_slots) {
        $schedule_data = array(
            'days' => array('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'),
            'time_slots' => array()
        );

        foreach ($time_slots as $slot) {
            $schedule_data['time_slots'][] = array(
                'day' => $slot['day'],
                'start_time' => $slot['start_time'],
                'end_time' => $slot['end_time'],
                'subject_id' => $slot['subject_id'],
                'subject_name' => get_term($slot['subject_id'])->name ?? '',
                'teacher_id' => $slot['teacher_id'],
                'teacher_name' => get_userdata($slot['teacher_id'])->display_name ?? '',
                'room' => $slot['room'],
                'slot_type' => $slot['slot_type'] ?? 'lesson'
            );
        }

        return $schedule_data;
    }
}

// Initialize the timetable builder
new SMS_Timetable_Builder();