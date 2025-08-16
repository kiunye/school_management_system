<?php
/**
 * Timetable Bulk Operations Manager
 *
 * @package SchoolManagementSystem
 * @subpackage Admin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class SMS_Timetable_Bulk_Operations
 * 
 * Handles bulk operations and template system for timetables
 */
class SMS_Timetable_Bulk_Operations {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_ajax_sms_bulk_create_timetables', array($this, 'bulk_create_timetables'));
        add_action('wp_ajax_sms_bulk_update_timetables', array($this, 'bulk_update_timetables'));
        add_action('wp_ajax_sms_bulk_delete_timetables', array($this, 'bulk_delete_timetables'));
        add_action('wp_ajax_sms_duplicate_timetable', array($this, 'duplicate_timetable'));
        add_action('wp_ajax_sms_copy_timetable_to_classes', array($this, 'copy_timetable_to_classes'));
        add_action('wp_ajax_sms_generate_timetable_from_template', array($this, 'generate_timetable_from_template'));
        add_action('wp_ajax_sms_export_timetables_bulk', array($this, 'export_timetables_bulk'));
        add_action('wp_ajax_sms_import_timetables_bulk', array($this, 'import_timetables_bulk'));
        
        // Add bulk actions to timetables list
        add_filter('bulk_actions-edit-sms_timetables', array($this, 'add_bulk_actions'));
        add_filter('handle_bulk_actions-edit-sms_timetables', array($this, 'handle_bulk_actions'), 10, 3);
    }

    /**
     * Add custom bulk actions to timetables list
     */
    public function add_bulk_actions($bulk_actions) {
        $bulk_actions['sms_bulk_validate'] = __('Validate Timetables', 'school-management-system');
        $bulk_actions['sms_bulk_activate'] = __('Activate Timetables', 'school-management-system');
        $bulk_actions['sms_bulk_deactivate'] = __('Deactivate Timetables', 'school-management-system');
        $bulk_actions['sms_bulk_duplicate'] = __('Duplicate Timetables', 'school-management-system');
        $bulk_actions['sms_bulk_export'] = __('Export Timetables', 'school-management-system');
        
        return $bulk_actions;
    }

    /**
     * Handle custom bulk actions
     */
    public function handle_bulk_actions($redirect_to, $doaction, $post_ids) {
        if (empty($post_ids)) {
            return $redirect_to;
        }

        switch ($doaction) {
            case 'sms_bulk_validate':
                $result = $this->bulk_validate_timetables_action($post_ids);
                $redirect_to = add_query_arg('bulk_validated', $result['count'], $redirect_to);
                break;
                
            case 'sms_bulk_activate':
                $result = $this->bulk_activate_timetables($post_ids);
                $redirect_to = add_query_arg('bulk_activated', $result['count'], $redirect_to);
                break;
                
            case 'sms_bulk_deactivate':
                $result = $this->bulk_deactivate_timetables($post_ids);
                $redirect_to = add_query_arg('bulk_deactivated', $result['count'], $redirect_to);
                break;
                
            case 'sms_bulk_duplicate':
                $result = $this->bulk_duplicate_timetables($post_ids);
                $redirect_to = add_query_arg('bulk_duplicated', $result['count'], $redirect_to);
                break;
                
            case 'sms_bulk_export':
                $this->bulk_export_timetables($post_ids);
                // Export doesn't redirect, it downloads
                return $redirect_to;
        }

        return $redirect_to;
    }

    /**
     * Bulk create timetables via AJAX
     */
    public function bulk_create_timetables() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_timetable_builder_nonce')) {
            wp_send_json_error(__('Security check failed', 'school-management-system'));
        }

        // Check user capabilities
        if (!current_user_can('manage_timetables')) {
            wp_send_json_error(__('You do not have permission to create timetables', 'school-management-system'));
        }

        $class_ids = array_map('intval', $_POST['class_ids']);
        $academic_year = sanitize_text_field($_POST['academic_year']);
        $term = sanitize_text_field($_POST['term']);
        $template_id = intval($_POST['template_id']);

        if (empty($class_ids) || !$academic_year || !$term) {
            wp_send_json_error(__('Missing required fields', 'school-management-system'));
        }

        $created_timetables = array();
        $errors = array();

        foreach ($class_ids as $class_id) {
            try {
                $timetable_id = $this->create_timetable_for_class($class_id, $academic_year, $term, $template_id);
                
                if ($timetable_id) {
                    $created_timetables[] = array(
                        'timetable_id' => $timetable_id,
                        'class_id' => $class_id,
                        'class_name' => get_the_title($class_id)
                    );
                } else {
                    $errors[] = sprintf(__('Failed to create timetable for class: %s', 'school-management-system'), get_the_title($class_id));
                }
            } catch (Exception $e) {
                $errors[] = sprintf(__('Error creating timetable for class %s: %s', 'school-management-system'), get_the_title($class_id), $e->getMessage());
            }
        }

        wp_send_json_success(array(
            'created_count' => count($created_timetables),
            'created_timetables' => $created_timetables,
            'errors' => $errors,
            'message' => sprintf(__('%d timetables created successfully', 'school-management-system'), count($created_timetables))
        ));
    }

    /**
     * Bulk update timetables via AJAX
     */
    public function bulk_update_timetables() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_timetable_builder_nonce')) {
            wp_send_json_error(__('Security check failed', 'school-management-system'));
        }

        $timetable_ids = array_map('intval', $_POST['timetable_ids']);
        $update_data = json_decode(stripslashes($_POST['update_data']), true);

        if (empty($timetable_ids) || !$update_data) {
            wp_send_json_error(__('Missing required data', 'school-management-system'));
        }

        $updated_count = 0;
        $errors = array();

        foreach ($timetable_ids as $timetable_id) {
            try {
                $result = $this->update_timetable_bulk($timetable_id, $update_data);
                
                if ($result) {
                    $updated_count++;
                } else {
                    $errors[] = sprintf(__('Failed to update timetable ID: %d', 'school-management-system'), $timetable_id);
                }
            } catch (Exception $e) {
                $errors[] = sprintf(__('Error updating timetable ID %d: %s', 'school-management-system'), $timetable_id, $e->getMessage());
            }
        }

        wp_send_json_success(array(
            'updated_count' => $updated_count,
            'errors' => $errors,
            'message' => sprintf(__('%d timetables updated successfully', 'school-management-system'), $updated_count)
        ));
    }

    /**
     * Duplicate timetable via AJAX
     */
    public function duplicate_timetable() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_timetable_builder_nonce')) {
            wp_send_json_error(__('Security check failed', 'school-management-system'));
        }

        $source_timetable_id = intval($_POST['source_timetable_id']);
        $target_class_id = intval($_POST['target_class_id']);
        $new_academic_year = sanitize_text_field($_POST['new_academic_year']);
        $new_term = sanitize_text_field($_POST['new_term']);

        if (!$source_timetable_id) {
            wp_send_json_error(__('Source timetable ID is required', 'school-management-system'));
        }

        try {
            $new_timetable_id = $this->duplicate_timetable_internal($source_timetable_id, $target_class_id, $new_academic_year, $new_term);
            
            wp_send_json_success(array(
                'new_timetable_id' => $new_timetable_id,
                'message' => __('Timetable duplicated successfully', 'school-management-system')
            ));
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Copy timetable to multiple classes via AJAX
     */
    public function copy_timetable_to_classes() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_timetable_builder_nonce')) {
            wp_send_json_error(__('Security check failed', 'school-management-system'));
        }

        $source_timetable_id = intval($_POST['source_timetable_id']);
        $target_class_ids = array_map('intval', $_POST['target_class_ids']);
        $academic_year = sanitize_text_field($_POST['academic_year']);
        $term = sanitize_text_field($_POST['term']);
        $copy_mode = sanitize_text_field($_POST['copy_mode']); // 'structure_only' or 'full_copy'

        if (!$source_timetable_id || empty($target_class_ids)) {
            wp_send_json_error(__('Source timetable and target classes are required', 'school-management-system'));
        }

        $copied_timetables = array();
        $errors = array();

        foreach ($target_class_ids as $class_id) {
            try {
                $new_timetable_id = $this->copy_timetable_to_class($source_timetable_id, $class_id, $academic_year, $term, $copy_mode);
                
                if ($new_timetable_id) {
                    $copied_timetables[] = array(
                        'timetable_id' => $new_timetable_id,
                        'class_id' => $class_id,
                        'class_name' => get_the_title($class_id)
                    );
                } else {
                    $errors[] = sprintf(__('Failed to copy timetable to class: %s', 'school-management-system'), get_the_title($class_id));
                }
            } catch (Exception $e) {
                $errors[] = sprintf(__('Error copying timetable to class %s: %s', 'school-management-system'), get_the_title($class_id), $e->getMessage());
            }
        }

        wp_send_json_success(array(
            'copied_count' => count($copied_timetables),
            'copied_timetables' => $copied_timetables,
            'errors' => $errors,
            'message' => sprintf(__('Timetable copied to %d classes successfully', 'school-management-system'), count($copied_timetables))
        ));
    }

    /**
     * Generate timetable from template via AJAX
     */
    public function generate_timetable_from_template() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_timetable_builder_nonce')) {
            wp_send_json_error(__('Security check failed', 'school-management-system'));
        }

        $template_id = intval($_POST['template_id']);
        $class_ids = array_map('intval', $_POST['class_ids']);
        $academic_year = sanitize_text_field($_POST['academic_year']);
        $term = sanitize_text_field($_POST['term']);
        $auto_assign_teachers = isset($_POST['auto_assign_teachers']) && $_POST['auto_assign_teachers'] === 'true';

        if (!$template_id || empty($class_ids)) {
            wp_send_json_error(__('Template and classes are required', 'school-management-system'));
        }

        $generated_timetables = array();
        $errors = array();

        foreach ($class_ids as $class_id) {
            try {
                $timetable_id = $this->generate_from_template($template_id, $class_id, $academic_year, $term, $auto_assign_teachers);
                
                if ($timetable_id) {
                    $generated_timetables[] = array(
                        'timetable_id' => $timetable_id,
                        'class_id' => $class_id,
                        'class_name' => get_the_title($class_id)
                    );
                } else {
                    $errors[] = sprintf(__('Failed to generate timetable for class: %s', 'school-management-system'), get_the_title($class_id));
                }
            } catch (Exception $e) {
                $errors[] = sprintf(__('Error generating timetable for class %s: %s', 'school-management-system'), get_the_title($class_id), $e->getMessage());
            }
        }

        wp_send_json_success(array(
            'generated_count' => count($generated_timetables),
            'generated_timetables' => $generated_timetables,
            'errors' => $errors,
            'message' => sprintf(__('%d timetables generated from template successfully', 'school-management-system'), count($generated_timetables))
        ));
    }

    /**
     * Export timetables in bulk via AJAX
     */
    public function export_timetables_bulk() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_timetable_builder_nonce')) {
            wp_send_json_error(__('Security check failed', 'school-management-system'));
        }

        $timetable_ids = array_map('intval', $_POST['timetable_ids']);
        $export_format = sanitize_text_field($_POST['export_format']); // 'csv', 'pdf', 'json'

        if (empty($timetable_ids)) {
            wp_send_json_error(__('No timetables selected for export', 'school-management-system'));
        }

        try {
            $export_file = $this->create_bulk_export($timetable_ids, $export_format);
            
            wp_send_json_success(array(
                'export_file' => $export_file,
                'download_url' => wp_get_attachment_url($export_file),
                'message' => __('Export created successfully', 'school-management-system')
            ));
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Import timetables in bulk via AJAX
     */
    public function import_timetables_bulk() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_timetable_builder_nonce')) {
            wp_send_json_error(__('Security check failed', 'school-management-system'));
        }

        if (!isset($_FILES['import_file'])) {
            wp_send_json_error(__('No import file provided', 'school-management-system'));
        }

        $import_mode = sanitize_text_field($_POST['import_mode']); // 'create_new', 'update_existing', 'merge'

        try {
            $result = $this->process_bulk_import($_FILES['import_file'], $import_mode);
            
            wp_send_json_success(array(
                'imported_count' => $result['imported_count'],
                'updated_count' => $result['updated_count'],
                'errors' => $result['errors'],
                'message' => sprintf(__('%d timetables imported successfully', 'school-management-system'), $result['imported_count'])
            ));
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Internal methods
     */

    /**
     * Create timetable for a specific class
     */
    private function create_timetable_for_class($class_id, $academic_year, $term, $template_id = 0) {
        $class_name = get_the_title($class_id);
        
        // Create timetable post
        $post_data = array(
            'post_type' => 'sms_timetables',
            'post_title' => sprintf('%s - %s - %s', $class_name, $academic_year, $term),
            'post_status' => 'publish',
            'post_author' => get_current_user_id()
        );

        $timetable_id = wp_insert_post($post_data);

        if (is_wp_error($timetable_id)) {
            throw new Exception(__('Failed to create timetable post', 'school-management-system'));
        }

        // Set ACF fields
        update_field('timetable_class', $class_id, $timetable_id);
        update_field('academic_year', $academic_year, $timetable_id);
        update_field('term', $term, $timetable_id);
        update_field('created_by_teacher', get_current_user_id(), $timetable_id);
        update_field('timetable_status', 'draft', $timetable_id);
        update_field('effective_date', current_time('Y-m-d'), $timetable_id);
        update_field('last_modified', current_time('mysql'), $timetable_id);

        // Apply template if provided
        if ($template_id > 0) {
            $this->apply_template_to_timetable($template_id, $timetable_id);
        }

        return $timetable_id;
    }

    /**
     * Duplicate timetable internally
     */
    private function duplicate_timetable_internal($source_id, $target_class_id = 0, $new_academic_year = '', $new_term = '') {
        $source_post = get_post($source_id);
        
        if (!$source_post) {
            throw new Exception(__('Source timetable not found', 'school-management-system'));
        }

        // Get source data
        $source_class = get_field('timetable_class', $source_id);
        $source_academic_year = get_field('academic_year', $source_id);
        $source_term = get_field('term', $source_id);
        $source_time_slots = get_field('time_slots', $source_id);

        // Use source data if new data not provided
        $target_class_id = $target_class_id ?: $source_class->ID;
        $new_academic_year = $new_academic_year ?: $source_academic_year->slug;
        $new_term = $new_term ?: $source_term->slug;

        // Create new timetable
        $new_timetable_id = $this->create_timetable_for_class($target_class_id, $new_academic_year, $new_term);

        // Copy time slots
        if ($source_time_slots) {
            update_field('time_slots', $source_time_slots, $new_timetable_id);
            
            // Generate schedule data
            $schedule_data = $this->generate_schedule_data_from_slots($source_time_slots);
            update_field('schedule_data', json_encode($schedule_data), $new_timetable_id);
        }

        return $new_timetable_id;
    }

    /**
     * Copy timetable to a specific class
     */
    private function copy_timetable_to_class($source_id, $target_class_id, $academic_year, $term, $copy_mode) {
        if ($copy_mode === 'structure_only') {
            // Copy only the time structure, not teachers/subjects
            return $this->copy_timetable_structure($source_id, $target_class_id, $academic_year, $term);
        } else {
            // Full copy
            return $this->duplicate_timetable_internal($source_id, $target_class_id, $academic_year, $term);
        }
    }

    /**
     * Copy only timetable structure (times, not teachers/subjects)
     */
    private function copy_timetable_structure($source_id, $target_class_id, $academic_year, $term) {
        $source_time_slots = get_field('time_slots', $source_id);
        
        if (!$source_time_slots) {
            throw new Exception(__('Source timetable has no time slots', 'school-management-system'));
        }

        // Create new timetable
        $new_timetable_id = $this->create_timetable_for_class($target_class_id, $academic_year, $term);

        // Copy structure only (clear teachers and subjects)
        $structure_slots = array();
        foreach ($source_time_slots as $slot) {
            $structure_slots[] = array(
                'day' => $slot['day'],
                'start_time' => $slot['start_time'],
                'end_time' => $slot['end_time'],
                'subject' => '', // Clear subject
                'teacher' => '', // Clear teacher
                'room' => $slot['room'], // Keep room
                'slot_type' => $slot['slot_type']
            );
        }

        update_field('time_slots', $structure_slots, $new_timetable_id);

        return $new_timetable_id;
    }

    /**
     * Generate timetable from template
     */
    private function generate_from_template($template_id, $class_id, $academic_year, $term, $auto_assign_teachers) {
        $template_slots = get_post_meta($template_id, 'template_time_slots', true);
        
        if (!$template_slots) {
            throw new Exception(__('Template has no time slots', 'school-management-system'));
        }

        // Create new timetable
        $new_timetable_id = $this->create_timetable_for_class($class_id, $academic_year, $term);

        // Process template slots
        $processed_slots = $template_slots;
        
        if ($auto_assign_teachers) {
            $processed_slots = $this->auto_assign_teachers_to_slots($processed_slots, $class_id);
        }

        update_field('time_slots', $processed_slots, $new_timetable_id);

        return $new_timetable_id;
    }

    /**
     * Auto-assign teachers to time slots based on subjects
     */
    private function auto_assign_teachers_to_slots($slots, $class_id) {
        foreach ($slots as &$slot) {
            if (!empty($slot['subject']) && empty($slot['teacher'])) {
                $available_teacher = $this->find_available_teacher_for_subject($slot['subject'], $slot['day'], $slot['start_time'], $slot['end_time']);
                
                if ($available_teacher) {
                    $slot['teacher'] = $available_teacher->ID;
                }
            }
        }
        
        return $slots;
    }

    /**
     * Find available teacher for subject
     */
    private function find_available_teacher_for_subject($subject_id, $day, $start_time, $end_time) {
        // Get teachers qualified for this subject
        $qualified_teachers = get_users(array(
            'role__in' => array('sms_teacher', 'administrator'),
            'meta_query' => array(
                array(
                    'key' => 'qualified_subjects',
                    'value' => $subject_id,
                    'compare' => 'LIKE'
                )
            )
        ));

        // Check availability
        foreach ($qualified_teachers as $teacher) {
            if ($this->is_teacher_available($teacher->ID, $day, $start_time, $end_time)) {
                return $teacher;
            }
        }

        return null;
    }

    /**
     * Check if teacher is available at given time
     */
    private function is_teacher_available($teacher_id, $day, $start_time, $end_time) {
        // Get all active timetables
        $active_timetables = get_posts(array(
            'post_type' => 'sms_timetables',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => 'timetable_status',
                    'value' => 'active',
                    'compare' => '='
                )
            )
        ));

        foreach ($active_timetables as $timetable) {
            $time_slots = get_field('time_slots', $timetable->ID);
            
            if (!$time_slots) {
                continue;
            }

            foreach ($time_slots as $slot) {
                if ($slot['teacher'] == $teacher_id && 
                    $slot['day'] == $day && 
                    $this->times_overlap($start_time, $end_time, $slot['start_time'], $slot['end_time'])) {
                    return false; // Teacher is busy
                }
            }
        }

        return true; // Teacher is available
    }

    /**
     * Bulk validate timetables action
     */
    private function bulk_validate_timetables_action($timetable_ids) {
        $validated_count = 0;
        $conflict_detector = new SMS_Timetable_Conflict_Detector();

        foreach ($timetable_ids as $timetable_id) {
            $time_slots = get_field('time_slots', $timetable_id);
            
            if ($time_slots) {
                $conflicts = $conflict_detector->detect_comprehensive_conflicts($time_slots, array($timetable_id));
                
                // Update timetable with validation results
                update_post_meta($timetable_id, 'last_validation_date', current_time('mysql'));
                update_post_meta($timetable_id, 'validation_conflicts', $conflicts);
                update_post_meta($timetable_id, 'validation_status', empty($conflicts) ? 'valid' : 'has_conflicts');
                
                $validated_count++;
            }
        }

        return array('count' => $validated_count);
    }

    /**
     * Bulk activate timetables
     */
    private function bulk_activate_timetables($timetable_ids) {
        $activated_count = 0;

        foreach ($timetable_ids as $timetable_id) {
            update_field('timetable_status', 'active', $timetable_id);
            update_field('last_modified', current_time('mysql'), $timetable_id);
            $activated_count++;
        }

        return array('count' => $activated_count);
    }

    /**
     * Bulk deactivate timetables
     */
    private function bulk_deactivate_timetables($timetable_ids) {
        $deactivated_count = 0;

        foreach ($timetable_ids as $timetable_id) {
            update_field('timetable_status', 'archived', $timetable_id);
            update_field('last_modified', current_time('mysql'), $timetable_id);
            $deactivated_count++;
        }

        return array('count' => $deactivated_count);
    }

    /**
     * Bulk duplicate timetables
     */
    private function bulk_duplicate_timetables($timetable_ids) {
        $duplicated_count = 0;

        foreach ($timetable_ids as $timetable_id) {
            try {
                $this->duplicate_timetable_internal($timetable_id);
                $duplicated_count++;
            } catch (Exception $e) {
                // Log error but continue with other timetables
                error_log('Error duplicating timetable ' . $timetable_id . ': ' . $e->getMessage());
            }
        }

        return array('count' => $duplicated_count);
    }

    /**
     * Create bulk export file
     */
    private function create_bulk_export($timetable_ids, $format) {
        switch ($format) {
            case 'csv':
                return $this->create_csv_export($timetable_ids);
            case 'pdf':
                return $this->create_pdf_export($timetable_ids);
            case 'json':
                return $this->create_json_export($timetable_ids);
            default:
                throw new Exception(__('Unsupported export format', 'school-management-system'));
        }
    }

    /**
     * Create CSV export
     */
    private function create_csv_export($timetable_ids) {
        $upload_dir = wp_upload_dir();
        $filename = 'timetables_export_' . date('Y-m-d_H-i-s') . '.csv';
        $filepath = $upload_dir['path'] . '/' . $filename;

        $file = fopen($filepath, 'w');
        
        // CSV headers
        fputcsv($file, array(
            'Timetable ID', 'Class', 'Academic Year', 'Term', 'Day', 
            'Start Time', 'End Time', 'Subject', 'Teacher', 'Room', 'Type'
        ));

        foreach ($timetable_ids as $timetable_id) {
            $class = get_field('timetable_class', $timetable_id);
            $academic_year = get_field('academic_year', $timetable_id);
            $term = get_field('term', $timetable_id);
            $time_slots = get_field('time_slots', $timetable_id);

            if ($time_slots) {
                foreach ($time_slots as $slot) {
                    fputcsv($file, array(
                        $timetable_id,
                        $class ? $class->post_title : '',
                        $academic_year ? $academic_year->name : '',
                        $term ? $term->name : '',
                        $slot['day'],
                        $slot['start_time'],
                        $slot['end_time'],
                        get_term($slot['subject'])->name ?? '',
                        get_userdata($slot['teacher'])->display_name ?? '',
                        $slot['room'],
                        $slot['slot_type']
                    ));
                }
            }
        }

        fclose($file);

        // Create attachment
        $attachment = array(
            'guid' => $upload_dir['url'] . '/' . basename($filename),
            'post_mime_type' => 'text/csv',
            'post_title' => preg_replace('/\.[^.]+$/', '', basename($filename)),
            'post_content' => '',
            'post_status' => 'inherit'
        );

        $attach_id = wp_insert_attachment($attachment, $filepath);
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attach_data = wp_generate_attachment_metadata($attach_id, $filepath);
        wp_update_attachment_metadata($attach_id, $attach_data);

        return $attach_id;
    }

    /**
     * Helper methods
     */
    private function times_overlap($start1, $end1, $start2, $end2) {
        $start1_time = strtotime($start1);
        $end1_time = strtotime($end1);
        $start2_time = strtotime($start2);
        $end2_time = strtotime($end2);

        return ($start1_time < $end2_time) && ($end1_time > $start2_time);
    }

    private function generate_schedule_data_from_slots($time_slots) {
        $schedule_data = array(
            'days' => array('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'),
            'time_slots' => array()
        );

        foreach ($time_slots as $slot) {
            $schedule_data['time_slots'][] = array(
                'day' => $slot['day'],
                'start_time' => $slot['start_time'],
                'end_time' => $slot['end_time'],
                'subject_id' => $slot['subject'],
                'subject_name' => get_term($slot['subject'])->name ?? '',
                'teacher_id' => $slot['teacher'],
                'teacher_name' => get_userdata($slot['teacher'])->display_name ?? '',
                'room' => $slot['room'],
                'slot_type' => $slot['slot_type']
            );
        }

        return $schedule_data;
    }

    private function apply_template_to_timetable($template_id, $timetable_id) {
        $template_slots = get_post_meta($template_id, 'template_time_slots', true);
        
        if ($template_slots) {
            update_field('time_slots', $template_slots, $timetable_id);
        }
    }

    private function update_timetable_bulk($timetable_id, $update_data) {
        foreach ($update_data as $field => $value) {
            switch ($field) {
                case 'status':
                    update_field('timetable_status', $value, $timetable_id);
                    break;
                case 'academic_year':
                    update_field('academic_year', $value, $timetable_id);
                    break;
                case 'term':
                    update_field('term', $value, $timetable_id);
                    break;
                // Add more fields as needed
            }
        }
        
        update_field('last_modified', current_time('mysql'), $timetable_id);
        return true;
    }

    private function create_pdf_export($timetable_ids) {
        // PDF export implementation would go here
        throw new Exception(__('PDF export not implemented yet', 'school-management-system'));
    }

    private function create_json_export($timetable_ids) {
        // JSON export implementation would go here
        throw new Exception(__('JSON export not implemented yet', 'school-management-system'));
    }

    private function process_bulk_import($file, $import_mode) {
        // Import processing implementation would go here
        throw new Exception(__('Bulk import not implemented yet', 'school-management-system'));
    }
}

// Initialize the bulk operations manager
new SMS_Timetable_Bulk_Operations();