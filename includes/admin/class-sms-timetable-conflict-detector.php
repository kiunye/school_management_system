<?php
/**
 * Timetable Conflict Detection and Management
 *
 * @package SchoolManagementSystem
 * @subpackage Admin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class SMS_Timetable_Conflict_Detector
 * 
 * Handles advanced conflict detection, teacher availability checking, and bulk operations
 */
class SMS_Timetable_Conflict_Detector {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_ajax_sms_bulk_validate_timetables', array($this, 'bulk_validate_timetables'));
        add_action('wp_ajax_sms_get_teacher_availability', array($this, 'get_teacher_availability'));
        add_action('wp_ajax_sms_get_room_availability', array($this, 'get_room_availability'));
        add_action('wp_ajax_sms_resolve_conflict', array($this, 'resolve_conflict'));
        add_action('wp_ajax_sms_create_timetable_template', array($this, 'create_timetable_template'));
        add_action('wp_ajax_sms_apply_timetable_template', array($this, 'apply_timetable_template'));
    }

    /**
     * Comprehensive conflict detection for multiple timetables
     */
    public function detect_comprehensive_conflicts($timetable_data, $exclude_timetable_ids = array()) {
        $conflicts = array();
        
        // Get all active timetables
        $active_timetables = $this->get_active_timetables($exclude_timetable_ids);
        
        foreach ($timetable_data as $slot_index => $slot) {
            if (empty($slot['teacher_id']) || empty($slot['day']) || empty($slot['start_time']) || empty($slot['end_time'])) {
                continue;
            }

            // Check teacher conflicts
            $teacher_conflicts = $this->check_teacher_conflicts_advanced(
                $slot['teacher_id'],
                $slot['day'],
                $slot['start_time'],
                $slot['end_time'],
                $active_timetables
            );

            if (!empty($teacher_conflicts)) {
                $conflicts[] = array(
                    'slot_index' => $slot_index,
                    'type' => 'teacher_conflict',
                    'severity' => $this->calculate_conflict_severity($teacher_conflicts),
                    'message' => $this->generate_conflict_message('teacher', $slot, $teacher_conflicts),
                    'conflicts_with' => $teacher_conflicts,
                    'suggestions' => $this->generate_conflict_suggestions('teacher', $slot, $teacher_conflicts)
                );
            }

            // Check room conflicts
            if (!empty($slot['room'])) {
                $room_conflicts = $this->check_room_conflicts_advanced(
                    $slot['room'],
                    $slot['day'],
                    $slot['start_time'],
                    $slot['end_time'],
                    $active_timetables
                );

                if (!empty($room_conflicts)) {
                    $conflicts[] = array(
                        'slot_index' => $slot_index,
                        'type' => 'room_conflict',
                        'severity' => $this->calculate_conflict_severity($room_conflicts),
                        'message' => $this->generate_conflict_message('room', $slot, $room_conflicts),
                        'conflicts_with' => $room_conflicts,
                        'suggestions' => $this->generate_conflict_suggestions('room', $slot, $room_conflicts)
                    );
                }
            }

            // Check class conflicts (same class having multiple subjects at same time)
            $class_conflicts = $this->check_class_conflicts(
                $slot['class_id'] ?? 0,
                $slot['day'],
                $slot['start_time'],
                $slot['end_time'],
                $active_timetables,
                $slot_index,
                $timetable_data
            );

            if (!empty($class_conflicts)) {
                $conflicts[] = array(
                    'slot_index' => $slot_index,
                    'type' => 'class_conflict',
                    'severity' => 'high',
                    'message' => $this->generate_conflict_message('class', $slot, $class_conflicts),
                    'conflicts_with' => $class_conflicts,
                    'suggestions' => $this->generate_conflict_suggestions('class', $slot, $class_conflicts)
                );
            }

            // Check workload conflicts (teacher overload)
            $workload_conflicts = $this->check_teacher_workload(
                $slot['teacher_id'],
                $slot['day'],
                $active_timetables,
                $timetable_data
            );

            if (!empty($workload_conflicts)) {
                $conflicts[] = array(
                    'slot_index' => $slot_index,
                    'type' => 'workload_conflict',
                    'severity' => 'medium',
                    'message' => $this->generate_conflict_message('workload', $slot, $workload_conflicts),
                    'conflicts_with' => $workload_conflicts,
                    'suggestions' => $this->generate_conflict_suggestions('workload', $slot, $workload_conflicts)
                );
            }
        }

        return $conflicts;
    }

    /**
     * Advanced teacher conflict checking with availability patterns
     */
    private function check_teacher_conflicts_advanced($teacher_id, $day, $start_time, $end_time, $active_timetables) {
        $conflicts = array();
        
        // Check against existing timetables
        foreach ($active_timetables as $timetable) {
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
                        'class_name' => get_the_title(get_field('timetable_class', $timetable->ID)),
                        'conflicting_slot' => $slot,
                        'overlap_duration' => $this->calculate_overlap_duration($start_time, $end_time, $slot['start_time'], $slot['end_time'])
                    );
                }
            }
        }

        // Check teacher availability preferences (if implemented)
        $teacher_preferences = $this->get_teacher_availability_preferences($teacher_id);
        if ($teacher_preferences && !$this->is_teacher_available_by_preference($teacher_preferences, $day, $start_time, $end_time)) {
            $conflicts[] = array(
                'type' => 'preference_conflict',
                'message' => 'Teacher has indicated unavailability during this time'
            );
        }

        return $conflicts;
    }

    /**
     * Advanced room conflict checking
     */
    private function check_room_conflicts_advanced($room, $day, $start_time, $end_time, $active_timetables) {
        $conflicts = array();
        
        foreach ($active_timetables as $timetable) {
            $time_slots = get_field('time_slots', $timetable->ID);
            
            if (!$time_slots) {
                continue;
            }

            foreach ($time_slots as $slot) {
                if (!empty($slot['room']) && 
                    strtolower(trim($slot['room'])) == strtolower(trim($room)) && 
                    $slot['day'] == $day && 
                    $this->times_overlap($start_time, $end_time, $slot['start_time'], $slot['end_time'])) {
                    
                    $conflicts[] = array(
                        'timetable_id' => $timetable->ID,
                        'timetable_title' => $timetable->post_title,
                        'class_name' => get_the_title(get_field('timetable_class', $timetable->ID)),
                        'conflicting_slot' => $slot,
                        'overlap_duration' => $this->calculate_overlap_duration($start_time, $end_time, $slot['start_time'], $slot['end_time'])
                    );
                }
            }
        }

        return $conflicts;
    }

    /**
     * Check for class conflicts (same class, multiple subjects at same time)
     */
    private function check_class_conflicts($class_id, $day, $start_time, $end_time, $active_timetables, $current_slot_index, $current_timetable_data) {
        $conflicts = array();
        
        // Check within current timetable data
        foreach ($current_timetable_data as $index => $slot) {
            if ($index != $current_slot_index && 
                isset($slot['class_id']) && 
                $slot['class_id'] == $class_id && 
                $slot['day'] == $day && 
                $this->times_overlap($start_time, $end_time, $slot['start_time'], $slot['end_time'])) {
                
                $conflicts[] = array(
                    'type' => 'internal_class_conflict',
                    'conflicting_slot' => $slot,
                    'slot_index' => $index
                );
            }
        }

        // Check against other timetables for the same class
        foreach ($active_timetables as $timetable) {
            $timetable_class = get_field('timetable_class', $timetable->ID);
            if ($timetable_class && $timetable_class->ID == $class_id) {
                $time_slots = get_field('time_slots', $timetable->ID);
                
                if (!$time_slots) {
                    continue;
                }

                foreach ($time_slots as $slot) {
                    if ($slot['day'] == $day && 
                        $this->times_overlap($start_time, $end_time, $slot['start_time'], $slot['end_time'])) {
                        
                        $conflicts[] = array(
                            'timetable_id' => $timetable->ID,
                            'timetable_title' => $timetable->post_title,
                            'conflicting_slot' => $slot
                        );
                    }
                }
            }
        }

        return $conflicts;
    }

    /**
     * Check teacher workload for the day
     */
    private function check_teacher_workload($teacher_id, $day, $active_timetables, $current_timetable_data) {
        $daily_hours = 0;
        $max_daily_hours = 8; // Configurable maximum
        $max_consecutive_hours = 4; // Maximum consecutive teaching hours
        
        $teacher_slots = array();

        // Collect all slots for this teacher on this day
        foreach ($active_timetables as $timetable) {
            $time_slots = get_field('time_slots', $timetable->ID);
            
            if (!$time_slots) {
                continue;
            }

            foreach ($time_slots as $slot) {
                if ($slot['teacher'] == $teacher_id && $slot['day'] == $day) {
                    $teacher_slots[] = $slot;
                    $daily_hours += $this->calculate_slot_duration($slot['start_time'], $slot['end_time']);
                }
            }
        }

        // Add current timetable slots
        foreach ($current_timetable_data as $slot) {
            if ($slot['teacher_id'] == $teacher_id && $slot['day'] == $day) {
                $teacher_slots[] = $slot;
                $daily_hours += $this->calculate_slot_duration($slot['start_time'], $slot['end_time']);
            }
        }

        $conflicts = array();

        // Check daily hour limit
        if ($daily_hours > $max_daily_hours) {
            $conflicts[] = array(
                'type' => 'daily_hour_limit',
                'current_hours' => $daily_hours,
                'max_hours' => $max_daily_hours,
                'message' => "Teacher exceeds daily hour limit ({$daily_hours}h > {$max_daily_hours}h)"
            );
        }

        // Check consecutive hours
        $consecutive_conflicts = $this->check_consecutive_teaching_hours($teacher_slots, $max_consecutive_hours);
        $conflicts = array_merge($conflicts, $consecutive_conflicts);

        return $conflicts;
    }

    /**
     * Check consecutive teaching hours
     */
    private function check_consecutive_teaching_hours($teacher_slots, $max_consecutive_hours) {
        $conflicts = array();
        
        // Sort slots by start time
        usort($teacher_slots, function($a, $b) {
            return strcmp($a['start_time'], $b['start_time']);
        });

        $consecutive_hours = 0;
        $consecutive_start = null;
        
        for ($i = 0; $i < count($teacher_slots); $i++) {
            $current_slot = $teacher_slots[$i];
            $slot_duration = $this->calculate_slot_duration($current_slot['start_time'], $current_slot['end_time']);
            
            if ($i == 0) {
                $consecutive_hours = $slot_duration;
                $consecutive_start = $current_slot['start_time'];
            } else {
                $previous_slot = $teacher_slots[$i - 1];
                
                // Check if slots are consecutive (end time of previous = start time of current)
                if ($previous_slot['end_time'] == $current_slot['start_time']) {
                    $consecutive_hours += $slot_duration;
                } else {
                    // Reset consecutive count
                    $consecutive_hours = $slot_duration;
                    $consecutive_start = $current_slot['start_time'];
                }
            }
            
            // Check if consecutive hours exceed limit
            if ($consecutive_hours > $max_consecutive_hours) {
                $conflicts[] = array(
                    'type' => 'consecutive_hour_limit',
                    'consecutive_hours' => $consecutive_hours,
                    'max_consecutive_hours' => $max_consecutive_hours,
                    'start_time' => $consecutive_start,
                    'end_time' => $current_slot['end_time'],
                    'message' => "Teacher has {$consecutive_hours} consecutive teaching hours (max: {$max_consecutive_hours})"
                );
            }
        }

        return $conflicts;
    }

    /**
     * Get teacher availability via AJAX
     */
    public function get_teacher_availability() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_timetable_builder_nonce')) {
            wp_send_json_error(__('Security check failed', 'school-management-system'));
        }

        $teacher_id = intval($_POST['teacher_id']);
        $week_start = sanitize_text_field($_POST['week_start']);
        $academic_year = sanitize_text_field($_POST['academic_year']);
        $term = sanitize_text_field($_POST['term']);

        if (!$teacher_id) {
            wp_send_json_error(__('Invalid teacher ID', 'school-management-system'));
        }

        $availability = $this->calculate_teacher_weekly_availability($teacher_id, $week_start, $academic_year, $term);
        wp_send_json_success($availability);
    }

    /**
     * Get room availability via AJAX
     */
    public function get_room_availability() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_timetable_builder_nonce')) {
            wp_send_json_error(__('Security check failed', 'school-management-system'));
        }

        $room = sanitize_text_field($_POST['room']);
        $week_start = sanitize_text_field($_POST['week_start']);
        $academic_year = sanitize_text_field($_POST['academic_year']);
        $term = sanitize_text_field($_POST['term']);

        if (!$room) {
            wp_send_json_error(__('Room name is required', 'school-management-system'));
        }

        $availability = $this->calculate_room_weekly_availability($room, $week_start, $academic_year, $term);
        wp_send_json_success($availability);
    }

    /**
     * Bulk validate multiple timetables
     */
    public function bulk_validate_timetables() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_timetable_builder_nonce')) {
            wp_send_json_error(__('Security check failed', 'school-management-system'));
        }

        $timetable_ids = array_map('intval', $_POST['timetable_ids']);
        
        if (empty($timetable_ids)) {
            wp_send_json_error(__('No timetables selected for validation', 'school-management-system'));
        }

        $validation_results = array();
        
        foreach ($timetable_ids as $timetable_id) {
            $time_slots = get_field('time_slots', $timetable_id);
            
            if (!$time_slots) {
                $validation_results[$timetable_id] = array(
                    'status' => 'empty',
                    'message' => __('No time slots found', 'school-management-system'),
                    'conflicts' => array()
                );
                continue;
            }

            $conflicts = $this->detect_comprehensive_conflicts($time_slots, array($timetable_id));
            
            $validation_results[$timetable_id] = array(
                'status' => empty($conflicts) ? 'valid' : 'conflicts',
                'message' => empty($conflicts) ? 
                    __('No conflicts detected', 'school-management-system') : 
                    sprintf(__('%d conflicts detected', 'school-management-system'), count($conflicts)),
                'conflicts' => $conflicts,
                'timetable_title' => get_the_title($timetable_id)
            );
        }

        wp_send_json_success($validation_results);
    }

    /**
     * Create timetable template
     */
    public function create_timetable_template() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_timetable_builder_nonce')) {
            wp_send_json_error(__('Security check failed', 'school-management-system'));
        }

        $template_name = sanitize_text_field($_POST['template_name']);
        $template_description = sanitize_textarea_field($_POST['template_description']);
        $source_timetable_id = intval($_POST['source_timetable_id']);

        if (!$template_name || !$source_timetable_id) {
            wp_send_json_error(__('Template name and source timetable are required', 'school-management-system'));
        }

        // Get source timetable data
        $time_slots = get_field('time_slots', $source_timetable_id);
        
        if (!$time_slots) {
            wp_send_json_error(__('Source timetable has no time slots', 'school-management-system'));
        }

        // Create template post
        $template_data = array(
            'post_type' => 'sms_timetable_template',
            'post_title' => $template_name,
            'post_content' => $template_description,
            'post_status' => 'publish',
            'post_author' => get_current_user_id()
        );

        $template_id = wp_insert_post($template_data);

        if (is_wp_error($template_id)) {
            wp_send_json_error(__('Error creating template', 'school-management-system'));
        }

        // Save template data
        update_post_meta($template_id, 'template_time_slots', $time_slots);
        update_post_meta($template_id, 'source_timetable_id', $source_timetable_id);
        update_post_meta($template_id, 'created_by', get_current_user_id());

        wp_send_json_success(array(
            'template_id' => $template_id,
            'message' => __('Template created successfully', 'school-management-system')
        ));
    }

    /**
     * Apply timetable template
     */
    public function apply_timetable_template() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_timetable_builder_nonce')) {
            wp_send_json_error(__('Security check failed', 'school-management-system'));
        }

        $template_id = intval($_POST['template_id']);
        $target_timetable_id = intval($_POST['target_timetable_id']);
        $apply_mode = sanitize_text_field($_POST['apply_mode']); // 'replace' or 'merge'

        if (!$template_id || !$target_timetable_id) {
            wp_send_json_error(__('Template and target timetable are required', 'school-management-system'));
        }

        // Get template data
        $template_slots = get_post_meta($template_id, 'template_time_slots', true);
        
        if (!$template_slots) {
            wp_send_json_error(__('Template has no time slots', 'school-management-system'));
        }

        // Apply template based on mode
        if ($apply_mode === 'replace') {
            // Replace all existing slots
            update_field('time_slots', $template_slots, $target_timetable_id);
        } else {
            // Merge with existing slots
            $existing_slots = get_field('time_slots', $target_timetable_id) ?: array();
            $merged_slots = array_merge($existing_slots, $template_slots);
            update_field('time_slots', $merged_slots, $target_timetable_id);
        }

        // Update last modified
        update_field('last_modified', current_time('mysql'), $target_timetable_id);

        wp_send_json_success(array(
            'message' => __('Template applied successfully', 'school-management-system')
        ));
    }

    /**
     * Resolve conflict automatically
     */
    public function resolve_conflict() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_timetable_builder_nonce')) {
            wp_send_json_error(__('Security check failed', 'school-management-system'));
        }

        $conflict_data = json_decode(stripslashes($_POST['conflict_data']), true);
        $resolution_method = sanitize_text_field($_POST['resolution_method']);

        if (!$conflict_data) {
            wp_send_json_error(__('Invalid conflict data', 'school-management-system'));
        }

        $resolution_result = $this->apply_conflict_resolution($conflict_data, $resolution_method);

        if ($resolution_result['success']) {
            wp_send_json_success($resolution_result);
        } else {
            wp_send_json_error($resolution_result['message']);
        }
    }

    /**
     * Apply conflict resolution
     */
    private function apply_conflict_resolution($conflict_data, $resolution_method) {
        switch ($resolution_method) {
            case 'suggest_alternative_time':
                return $this->suggest_alternative_time_slots($conflict_data);
                
            case 'suggest_alternative_teacher':
                return $this->suggest_alternative_teachers($conflict_data);
                
            case 'suggest_alternative_room':
                return $this->suggest_alternative_rooms($conflict_data);
                
            case 'auto_reschedule':
                return $this->auto_reschedule_conflicting_slots($conflict_data);
                
            default:
                return array(
                    'success' => false,
                    'message' => __('Unknown resolution method', 'school-management-system')
                );
        }
    }

    /**
     * Suggest alternative time slots
     */
    private function suggest_alternative_time_slots($conflict_data) {
        $suggestions = array();
        $slot = $conflict_data['slot'];
        $day = $slot['day'];
        
        // Define possible time slots
        $time_slots = array(
            '08:00:00' => '08:40:00',
            '08:40:00' => '09:20:00',
            '09:20:00' => '10:00:00',
            '10:20:00' => '11:00:00',
            '11:00:00' => '11:40:00',
            '11:40:00' => '12:20:00',
            '13:00:00' => '13:40:00',
            '13:40:00' => '14:20:00',
            '14:20:00' => '15:00:00',
            '15:00:00' => '15:40:00'
        );

        foreach ($time_slots as $start_time => $end_time) {
            // Skip current time slot
            if ($start_time == $slot['start_time'] && $end_time == $slot['end_time']) {
                continue;
            }

            // Check if this time slot is available
            $test_conflicts = $this->check_teacher_conflicts_advanced(
                $slot['teacher_id'],
                $day,
                $start_time,
                $end_time,
                $this->get_active_timetables()
            );

            if (empty($test_conflicts)) {
                $suggestions[] = array(
                    'start_time' => $start_time,
                    'end_time' => $end_time,
                    'day' => $day,
                    'confidence' => 'high'
                );
            }
        }

        return array(
            'success' => true,
            'suggestions' => $suggestions,
            'message' => sprintf(__('%d alternative time slots found', 'school-management-system'), count($suggestions))
        );
    }

    /**
     * Suggest alternative teachers
     */
    private function suggest_alternative_teachers($conflict_data) {
        $suggestions = array();
        $slot = $conflict_data['slot'];
        
        // Get teachers who can teach this subject
        $subject_id = $slot['subject_id'];
        $qualified_teachers = $this->get_teachers_for_subject($subject_id);

        foreach ($qualified_teachers as $teacher) {
            // Skip current teacher
            if ($teacher->ID == $slot['teacher_id']) {
                continue;
            }

            // Check if teacher is available
            $conflicts = $this->check_teacher_conflicts_advanced(
                $teacher->ID,
                $slot['day'],
                $slot['start_time'],
                $slot['end_time'],
                $this->get_active_timetables()
            );

            if (empty($conflicts)) {
                $suggestions[] = array(
                    'teacher_id' => $teacher->ID,
                    'teacher_name' => $teacher->display_name,
                    'confidence' => 'high'
                );
            }
        }

        return array(
            'success' => true,
            'suggestions' => $suggestions,
            'message' => sprintf(__('%d alternative teachers found', 'school-management-system'), count($suggestions))
        );
    }

    /**
     * Suggest alternative rooms
     */
    private function suggest_alternative_rooms($conflict_data) {
        $suggestions = array();
        $slot = $conflict_data['slot'];
        
        // Get available rooms
        $all_rooms = $this->get_all_rooms();

        foreach ($all_rooms as $room) {
            // Skip current room
            if ($room == $slot['room']) {
                continue;
            }

            // Check if room is available
            $conflicts = $this->check_room_conflicts_advanced(
                $room,
                $slot['day'],
                $slot['start_time'],
                $slot['end_time'],
                $this->get_active_timetables()
            );

            if (empty($conflicts)) {
                $suggestions[] = array(
                    'room' => $room,
                    'confidence' => 'high'
                );
            }
        }

        return array(
            'success' => true,
            'suggestions' => $suggestions,
            'message' => sprintf(__('%d alternative rooms found', 'school-management-system'), count($suggestions))
        );
    }

    /**
     * Helper methods
     */
    private function get_active_timetables($exclude_ids = array()) {
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

        if (!empty($exclude_ids)) {
            $args['post__not_in'] = $exclude_ids;
        }

        return get_posts($args);
    }

    private function times_overlap($start1, $end1, $start2, $end2) {
        $start1_time = strtotime($start1);
        $end1_time = strtotime($end1);
        $start2_time = strtotime($start2);
        $end2_time = strtotime($end2);

        return ($start1_time < $end2_time) && ($end1_time > $start2_time);
    }

    private function calculate_overlap_duration($start1, $end1, $start2, $end2) {
        $start1_time = strtotime($start1);
        $end1_time = strtotime($end1);
        $start2_time = strtotime($start2);
        $end2_time = strtotime($end2);

        $overlap_start = max($start1_time, $start2_time);
        $overlap_end = min($end1_time, $end2_time);

        return max(0, ($overlap_end - $overlap_start) / 3600); // Return hours
    }

    private function calculate_slot_duration($start_time, $end_time) {
        return (strtotime($end_time) - strtotime($start_time)) / 3600; // Return hours
    }

    private function calculate_conflict_severity($conflicts) {
        $count = count($conflicts);
        if ($count >= 3) return 'high';
        if ($count >= 2) return 'medium';
        return 'low';
    }

    private function generate_conflict_message($type, $slot, $conflicts) {
        $count = count($conflicts);
        
        switch ($type) {
            case 'teacher':
                return sprintf(
                    __('Teacher %s has %d scheduling conflicts on %s from %s to %s', 'school-management-system'),
                    get_userdata($slot['teacher_id'])->display_name,
                    $count,
                    ucfirst($slot['day']),
                    $slot['start_time'],
                    $slot['end_time']
                );
                
            case 'room':
                return sprintf(
                    __('Room %s has %d booking conflicts on %s from %s to %s', 'school-management-system'),
                    $slot['room'],
                    $count,
                    ucfirst($slot['day']),
                    $slot['start_time'],
                    $slot['end_time']
                );
                
            case 'class':
                return sprintf(
                    __('Class has %d scheduling conflicts on %s from %s to %s', 'school-management-system'),
                    $count,
                    ucfirst($slot['day']),
                    $slot['start_time'],
                    $slot['end_time']
                );
                
            default:
                return sprintf(__('%d conflicts detected', 'school-management-system'), $count);
        }
    }

    private function generate_conflict_suggestions($type, $slot, $conflicts) {
        $suggestions = array();
        
        switch ($type) {
            case 'teacher':
                $suggestions[] = __('Try a different time slot', 'school-management-system');
                $suggestions[] = __('Assign a different teacher', 'school-management-system');
                $suggestions[] = __('Move conflicting classes to different times', 'school-management-system');
                break;
                
            case 'room':
                $suggestions[] = __('Use a different room', 'school-management-system');
                $suggestions[] = __('Change the time slot', 'school-management-system');
                $suggestions[] = __('Check room capacity requirements', 'school-management-system');
                break;
                
            case 'class':
                $suggestions[] = __('Reschedule one of the conflicting subjects', 'school-management-system');
                $suggestions[] = __('Split the class into groups', 'school-management-system');
                break;
        }
        
        return $suggestions;
    }

    private function get_teacher_availability_preferences($teacher_id) {
        // This would get teacher availability preferences from user meta
        return get_user_meta($teacher_id, 'availability_preferences', true);
    }

    private function is_teacher_available_by_preference($preferences, $day, $start_time, $end_time) {
        // This would check against teacher preferences
        return true; // Simplified for now
    }

    private function calculate_teacher_weekly_availability($teacher_id, $week_start, $academic_year, $term) {
        // This would calculate and return teacher's weekly availability
        return array(
            'teacher_id' => $teacher_id,
            'week_start' => $week_start,
            'availability' => array() // Detailed availability data
        );
    }

    private function calculate_room_weekly_availability($room, $week_start, $academic_year, $term) {
        // This would calculate and return room's weekly availability
        return array(
            'room' => $room,
            'week_start' => $week_start,
            'availability' => array() // Detailed availability data
        );
    }

    private function get_teachers_for_subject($subject_id) {
        // Get teachers qualified to teach this subject
        return get_users(array(
            'role__in' => array('sms_teacher', 'administrator'),
            'meta_query' => array(
                array(
                    'key' => 'qualified_subjects',
                    'value' => $subject_id,
                    'compare' => 'LIKE'
                )
            )
        ));
    }

    private function get_all_rooms() {
        // Get all available rooms
        global $wpdb;
        
        $rooms = $wpdb->get_col("
            SELECT DISTINCT meta_value 
            FROM {$wpdb->postmeta} 
            WHERE meta_key LIKE '%room%' 
            AND meta_value != '' 
            ORDER BY meta_value
        ");
        
        return array_filter($rooms);
    }

    private function auto_reschedule_conflicting_slots($conflict_data) {
        // This would implement automatic rescheduling logic
        return array(
            'success' => false,
            'message' => __('Auto-rescheduling not implemented yet', 'school-management-system')
        );
    }
}

// Initialize the conflict detector
new SMS_Timetable_Conflict_Detector();