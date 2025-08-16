<?php
/**
 * Bulk Invoice Generation AJAX Handler
 *
 * Handles AJAX requests for bulk invoice generation,
 * student preview, and related functionality.
 *
 * @package    School_Management_System
 * @subpackage School_Management_System/includes/admin
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Bulk Invoice Handler Class
 */
class SMS_Bulk_Invoice_Handler extends SMS_Base {

    /**
     * Initialize the class and set its properties.
     */
    public function __construct() {
        parent::__construct();
        
        // Register AJAX handlers
        add_action('wp_ajax_sms_bulk_generate_invoices', [$this, 'handle_bulk_generation']);
        add_action('wp_ajax_sms_get_student_preview', [$this, 'handle_student_preview']);
        add_action('wp_ajax_sms_search_students', [$this, 'handle_student_search']);
        add_action('wp_ajax_sms_validate_invoice_data', [$this, 'handle_validation']);
    }

    /**
     * Handle bulk invoice generation AJAX request.
     */
    public function handle_bulk_generation() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['bulk_invoice_nonce'], 'sms_bulk_generate_invoices')) {
            wp_send_json_error(__('Security check failed.', 'school-management-system'));
        }

        // Check permissions
        if (!current_user_can('manage_invoices')) {
            wp_send_json_error(__('Insufficient permissions.', 'school-management-system'));
        }

        try {
            // Get selected students
            $student_ids = $this->get_selected_students();
            if (empty($student_ids)) {
                wp_send_json_error(__('No students selected.', 'school-management-system'));
            }

            // Get selected fees
            $fee_items = $this->get_selected_fees();
            if (empty($fee_items)) {
                wp_send_json_error(__('No fees selected.', 'school-management-system'));
            }

            // Prepare generation options
            $options = $this->prepare_generation_options();

            // Initialize invoice generator
            $invoice_generator = new SMS_Invoice_Generator();

            // Generate invoices in batches to avoid timeout
            $batch_size = 50; // Process 50 students at a time
            $total_students = count($student_ids);
            $processed = 0;
            $results = [
                'success_count' => 0,
                'error_count' => 0,
                'generated_invoices' => [],
                'errors' => []
            ];

            // Process in batches
            for ($i = 0; $i < $total_students; $i += $batch_size) {
                $batch_students = array_slice($student_ids, $i, $batch_size);
                
                // Generate invoices for this batch
                $batch_results = $invoice_generator->bulk_generate_invoices($batch_students, $fee_items, $options);
                
                // Merge results
                $results['success_count'] += $batch_results['success_count'];
                $results['error_count'] += $batch_results['error_count'];
                $results['generated_invoices'] = array_merge($results['generated_invoices'], $batch_results['generated_invoices']);
                $results['errors'] = array_merge($results['errors'], $batch_results['errors']);
                
                $processed += count($batch_students);
                
                // Send progress update (if this was a real-time process)
                // For now, we'll just continue processing
            }

            // Send final results
            wp_send_json_success($results);

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Handle student preview AJAX request.
     */
    public function handle_student_preview() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_student_preview')) {
            wp_send_json_error(__('Security check failed.', 'school-management-system'));
        }

        // Check permissions
        if (!current_user_can('manage_students')) {
            wp_send_json_error(__('Insufficient permissions.', 'school-management-system'));
        }

        try {
            $selection_method = sanitize_text_field($_POST['selection_method']);
            $student_ids = [];

            switch ($selection_method) {
                case 'all':
                    $student_ids = $this->get_all_active_students();
                    break;

                case 'by_grade':
                    $selected_grades = array_map('intval', $_POST['selected_grades'] ?? []);
                    $student_ids = $this->get_students_by_grades($selected_grades);
                    break;

                case 'by_class':
                    $selected_classes = array_map('intval', $_POST['selected_classes'] ?? []);
                    $student_ids = $this->get_students_by_classes($selected_classes);
                    break;

                case 'individual':
                    $selected_students = sanitize_text_field($_POST['selected_students'] ?? '');
                    $student_ids = explode(',', $selected_students);
                    $student_ids = array_filter(array_map('intval', $student_ids));
                    break;
            }

            // Get student preview data
            $preview_data = $this->get_student_preview_data($student_ids);

            wp_send_json_success([
                'count' => count($student_ids),
                'preview' => $preview_data
            ]);

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Handle student search AJAX request.
     */
    public function handle_student_search() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_search_students')) {
            wp_send_json_error(__('Security check failed.', 'school-management-system'));
        }

        // Check permissions
        if (!current_user_can('manage_students')) {
            wp_send_json_error(__('Insufficient permissions.', 'school-management-system'));
        }

        $search_term = sanitize_text_field($_POST['search']);
        if (strlen($search_term) < 2) {
            wp_send_json_error(__('Search term must be at least 2 characters.', 'school-management-system'));
        }

        try {
            $students = $this->search_students($search_term);
            wp_send_json_success(['students' => $students]);

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Handle invoice data validation AJAX request.
     */
    public function handle_validation() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_validate_invoice')) {
            wp_send_json_error(__('Security check failed.', 'school-management-system'));
        }

        // Check permissions
        if (!current_user_can('manage_invoices')) {
            wp_send_json_error(__('Insufficient permissions.', 'school-management-system'));
        }

        try {
            $validation_results = [];

            // Validate students
            $student_ids = $this->get_selected_students();
            $validation_results['students'] = [
                'count' => count($student_ids),
                'valid' => !empty($student_ids)
            ];

            // Validate fees
            $fee_items = $this->get_selected_fees();
            $validation_results['fees'] = [
                'count' => count($fee_items),
                'valid' => !empty($fee_items)
            ];

            // Validate options
            $options = $this->prepare_generation_options();
            $validation_results['options'] = [
                'valid' => !empty($options['invoice_date']) && !empty($options['due_date'])
            ];

            wp_send_json_success($validation_results);

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Get selected students based on form data.
     *
     * @return array Array of student IDs
     */
    private function get_selected_students() {
        $selection_method = sanitize_text_field($_POST['selection_method']);
        $student_ids = [];

        switch ($selection_method) {
            case 'all':
                $student_ids = $this->get_all_active_students();
                break;

            case 'by_grade':
                $selected_grades = array_map('intval', $_POST['selected_grades'] ?? []);
                $student_ids = $this->get_students_by_grades($selected_grades);
                break;

            case 'by_class':
                $selected_classes = array_map('intval', $_POST['selected_classes'] ?? []);
                $student_ids = $this->get_students_by_classes($selected_classes);
                break;

            case 'individual':
                $selected_students = sanitize_text_field($_POST['selected_students'] ?? '');
                $student_ids = explode(',', $selected_students);
                $student_ids = array_filter(array_map('intval', $student_ids));
                break;
        }

        return array_unique($student_ids);
    }

    /**
     * Get selected fees based on form data.
     *
     * @return array Array of fee items
     */
    private function get_selected_fees() {
        $selected_fees = array_map('intval', $_POST['selected_fees'] ?? []);
        $custom_amounts = $_POST['custom_amounts'] ?? [];
        $quantities = $_POST['quantities'] ?? [];

        $fee_items = [];
        foreach ($selected_fees as $fee_id) {
            $item = [
                'fee_id' => $fee_id,
                'quantity' => intval($quantities[$fee_id] ?? 1)
            ];

            // Add custom amount if specified
            if (!empty($custom_amounts[$fee_id])) {
                $item['custom_amount'] = floatval($custom_amounts[$fee_id]);
            }

            $fee_items[] = $item;
        }

        return $fee_items;
    }

    /**
     * Prepare generation options from form data.
     *
     * @return array Generation options
     */
    private function prepare_generation_options() {
        $options = [
            'invoice_date' => sanitize_text_field($_POST['invoice_date']),
            'initial_status' => sanitize_text_field($_POST['initial_status'] ?? 'draft'),
            'payment_methods' => array_map('sanitize_text_field', $_POST['payment_methods'] ?? []),
            'payment_instructions' => sanitize_textarea_field($_POST['payment_instructions'] ?? ''),
            'academic_year' => intval($_POST['academic_year'] ?? 0),
            'term' => intval($_POST['term'] ?? 0),
            'check_existing' => !empty($_POST['check_existing_invoices']),
            'include_penalties' => !empty($_POST['include_penalties']),
            'send_notifications' => true,
            'send_email' => !empty($_POST['send_email_notifications']),
            'send_sms' => !empty($_POST['send_sms_notifications']),
            'batch_processing' => true
        ];

        // Handle due date
        $due_date_option = sanitize_text_field($_POST['due_date_option']);
        if ($due_date_option === 'days') {
            $options['due_days'] = intval($_POST['due_days']);
        } else {
            $options['due_date'] = sanitize_text_field($_POST['due_date_fixed']);
        }

        return $options;
    }

    /**
     * Get all active students.
     *
     * @return array Array of student IDs
     */
    private function get_all_active_students() {
        $args = [
            'post_type' => 'sms_students',
            'post_status' => 'publish',
            'meta_query' => [
                [
                    'key' => 'status',
                    'value' => 'active',
                    'compare' => '='
                ]
            ],
            'fields' => 'ids',
            'posts_per_page' => -1
        ];

        return get_posts($args);
    }

    /**
     * Get students by grade levels.
     *
     * @param array $grade_ids Array of grade term IDs
     * @return array Array of student IDs
     */
    private function get_students_by_grades($grade_ids) {
        if (empty($grade_ids)) {
            return [];
        }

        // Get grade slugs from term IDs
        $grade_slugs = [];
        foreach ($grade_ids as $grade_id) {
            $term = get_term($grade_id, 'sms_grades');
            if ($term && !is_wp_error($term)) {
                $grade_slugs[] = $term->slug;
            }
        }

        if (empty($grade_slugs)) {
            return [];
        }

        $args = [
            'post_type' => 'sms_students',
            'post_status' => 'publish',
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => 'status',
                    'value' => 'active',
                    'compare' => '='
                ],
                [
                    'key' => 'grade_level',
                    'value' => $grade_slugs,
                    'compare' => 'IN'
                ]
            ],
            'fields' => 'ids',
            'posts_per_page' => -1
        ];

        return get_posts($args);
    }

    /**
     * Get students by classes.
     *
     * @param array $class_ids Array of class post IDs
     * @return array Array of student IDs
     */
    private function get_students_by_classes($class_ids) {
        if (empty($class_ids)) {
            return [];
        }

        $args = [
            'post_type' => 'sms_students',
            'post_status' => 'publish',
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => 'status',
                    'value' => 'active',
                    'compare' => '='
                ],
                [
                    'key' => 'current_class',
                    'value' => $class_ids,
                    'compare' => 'IN'
                ]
            ],
            'fields' => 'ids',
            'posts_per_page' => -1
        ];

        return get_posts($args);
    }

    /**
     * Get student preview data for display.
     *
     * @param array $student_ids Array of student IDs
     * @return string HTML preview
     */
    private function get_student_preview_data($student_ids) {
        if (empty($student_ids)) {
            return '<p>' . __('No students found.', 'school-management-system') . '</p>';
        }

        // Limit preview to first 10 students
        $preview_ids = array_slice($student_ids, 0, 10);
        $remaining_count = count($student_ids) - count($preview_ids);

        $html = '<ul class="student-preview-list">';
        
        foreach ($preview_ids as $student_id) {
            $student_name = get_field('full_name', $student_id);
            $admission_number = get_field('admission_number', $student_id);
            $grade = get_field('grade_level', $student_id);
            
            $html .= '<li>';
            $html .= '<strong>' . esc_html($student_name) . '</strong> ';
            $html .= '(' . esc_html($admission_number) . ') - ';
            $html .= esc_html(ucfirst($grade));
            $html .= '</li>';
        }
        
        $html .= '</ul>';
        
        if ($remaining_count > 0) {
            $html .= '<p><em>' . sprintf(
                __('... and %d more students', 'school-management-system'),
                $remaining_count
            ) . '</em></p>';
        }

        return $html;
    }

    /**
     * Search students by name or admission number.
     *
     * @param string $search_term Search term
     * @return array Array of student data
     */
    private function search_students($search_term) {
        $args = [
            'post_type' => 'sms_students',
            'post_status' => 'publish',
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => 'status',
                    'value' => 'active',
                    'compare' => '='
                ],
                [
                    'relation' => 'OR',
                    [
                        'key' => 'full_name',
                        'value' => $search_term,
                        'compare' => 'LIKE'
                    ],
                    [
                        'key' => 'admission_number',
                        'value' => $search_term,
                        'compare' => 'LIKE'
                    ]
                ]
            ],
            'posts_per_page' => 20
        ];

        $students = get_posts($args);
        $results = [];

        foreach ($students as $student) {
            $results[] = [
                'id' => $student->ID,
                'name' => get_field('full_name', $student->ID),
                'admission_number' => get_field('admission_number', $student->ID),
                'grade' => get_field('grade_level', $student->ID),
                'class' => get_field('current_class', $student->ID)
            ];
        }

        return $results;
    }
}