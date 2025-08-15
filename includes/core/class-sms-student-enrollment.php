<?php
/**
 * Student Enrollment and Transfer System
 *
 * Handles student enrollment in classes, transfers between classes,
 * and academic year progression.
 *
 * @package    School_Management_System
 * @subpackage School_Management_System/includes/core
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Student Enrollment Manager Class
 */
class SMS_Student_Enrollment extends SMS_Base {

    /**
     * Transfer reasons
     */
    const TRANSFER_PROMOTION = 'promotion';
    const TRANSFER_DEMOTION = 'demotion';
    const TRANSFER_LATERAL = 'lateral';
    const TRANSFER_DISCIPLINARY = 'disciplinary';
    const TRANSFER_PARENT_REQUEST = 'parent_request';
    const TRANSFER_ACADEMIC_PERFORMANCE = 'academic_performance';

    /**
     * Initialize the enrollment manager.
     */
    public function __construct() {
        parent::__construct();
        
        // Add enrollment management meta boxes
        add_action('add_meta_boxes', [$this, 'add_enrollment_meta_boxes']);
        
        // Handle AJAX requests for enrollment management
        add_action('wp_ajax_sms_enroll_student_in_class', [$this, 'ajax_enroll_student_in_class']);
        add_action('wp_ajax_sms_transfer_student', [$this, 'ajax_transfer_student']);
        add_action('wp_ajax_sms_get_available_classes', [$this, 'ajax_get_available_classes']);
        
        // Add bulk actions for class enrollment
        add_filter('bulk_actions-edit-sms_students', [$this, 'add_bulk_enrollment_actions']);
        add_filter('handle_bulk_actions-edit-sms_students', [$this, 'handle_bulk_enrollment_actions'], 10, 3);
        
        // Hook for academic year progression
        add_action('sms_academic_year_progression', [$this, 'handle_academic_year_progression']);
        
        // Add class capacity validation
        add_action('acf/validate_value/name=assigned_class', [$this, 'validate_class_assignment'], 10, 4);
    }

    /**
     * Enroll student in a specific class.
     */
    public function enroll_student_in_class($student_id, $class_id, $enrollment_date = null, $notes = '') {
        // Validate permissions
        if (!current_user_can('manage_students')) {
            return new WP_Error('insufficient_permissions', __('You do not have permission to enroll students', 'school-management-system'));
        }

        // Validate student exists and is eligible for enrollment
        $student_validation = $this->validate_student_for_enrollment($student_id);
        if (is_wp_error($student_validation)) {
            return $student_validation;
        }

        // Validate class exists and has capacity
        $class_validation = $this->validate_class_for_enrollment($class_id);
        if (is_wp_error($class_validation)) {
            return $class_validation;
        }

        // Check if student is already enrolled in this class
        $current_class = get_field('assigned_class', $student_id);
        if ($current_class == $class_id) {
            return new WP_Error('already_enrolled', __('Student is already enrolled in this class', 'school-management-system'));
        }

        // Store previous class information if exists
        if ($current_class) {
            $this->record_previous_class($student_id, $current_class, 'enrollment_change');
        }

        // Set enrollment date
        $enrollment_date = $enrollment_date ?: current_time('Y-m-d');

        // Update student's class assignment
        update_field('assigned_class', $class_id, $student_id);
        update_field('enrollment_date', $enrollment_date, $student_id);

        // Add enrollment record to student's history
        $this->add_enrollment_history($student_id, $class_id, $enrollment_date, 'enrolled', $notes);

        // Update class enrollment count
        $this->update_class_enrollment_count($class_id);

        // Log the enrollment
        $this->log("Student {$student_id} enrolled in class {$class_id}", 'info', [
            'student_id' => $student_id,
            'class_id' => $class_id,
            'enrollment_date' => $enrollment_date,
            'enrolled_by' => get_current_user_id(),
            'previous_class' => $current_class
        ]);

        // Trigger enrollment action
        do_action('sms_student_enrolled_in_class', $student_id, $class_id, $current_class);

        return true;
    }

    /**
     * Transfer student between classes.
     */
    public function transfer_student($student_id, $from_class_id, $to_class_id, $reason = '', $transfer_date = null, $notes = '') {
        // Validate permissions
        if (!current_user_can('manage_students')) {
            return new WP_Error('insufficient_permissions', __('You do not have permission to transfer students', 'school-management-system'));
        }

        // Validate student and classes
        $student_validation = $this->validate_student_for_transfer($student_id, $from_class_id);
        if (is_wp_error($student_validation)) {
            return $student_validation;
        }

        $class_validation = $this->validate_class_for_enrollment($to_class_id);
        if (is_wp_error($class_validation)) {
            return $class_validation;
        }

        // Set transfer date
        $transfer_date = $transfer_date ?: current_time('Y-m-d');

        // Record the transfer in previous classes
        $this->record_previous_class($student_id, $from_class_id, $reason, $transfer_date);

        // Update student's class assignment
        update_field('assigned_class', $to_class_id, $student_id);
        update_field('enrollment_date', $transfer_date, $student_id);

        // Add transfer record to student's history
        $this->add_enrollment_history($student_id, $to_class_id, $transfer_date, 'transferred', $notes, $from_class_id);

        // Update enrollment counts for both classes
        $this->update_class_enrollment_count($from_class_id);
        $this->update_class_enrollment_count($to_class_id);

        // Log the transfer
        $this->log("Student {$student_id} transferred from class {$from_class_id} to class {$to_class_id}", 'info', [
            'student_id' => $student_id,
            'from_class_id' => $from_class_id,
            'to_class_id' => $to_class_id,
            'reason' => $reason,
            'transfer_date' => $transfer_date,
            'transferred_by' => get_current_user_id()
        ]);

        // Trigger transfer action
        do_action('sms_student_transferred', $student_id, $from_class_id, $to_class_id, $reason);

        return true;
    }

    /**
     * Validate student for enrollment.
     */
    private function validate_student_for_enrollment($student_id) {
        $post = get_post($student_id);
        if (!$post || $post->post_type !== 'sms_students') {
            return new WP_Error('invalid_student', __('Invalid student ID', 'school-management-system'));
        }

        $student_status = get_field('student_status', $student_id);
        if (!in_array($student_status, ['active', 'enrolled'])) {
            return new WP_Error('invalid_status', __('Student must be active to be enrolled in a class', 'school-management-system'));
        }

        return true;
    }

    /**
     * Validate student for transfer.
     */
    private function validate_student_for_transfer($student_id, $from_class_id) {
        $validation = $this->validate_student_for_enrollment($student_id);
        if (is_wp_error($validation)) {
            return $validation;
        }

        $current_class = get_field('assigned_class', $student_id);
        if ($current_class != $from_class_id) {
            return new WP_Error('class_mismatch', __('Student is not currently enrolled in the specified source class', 'school-management-system'));
        }

        return true;
    }

    /**
     * Validate class for enrollment.
     */
    private function validate_class_for_enrollment($class_id) {
        $post = get_post($class_id);
        if (!$post || $post->post_type !== 'sms_classes') {
            return new WP_Error('invalid_class', __('Invalid class ID', 'school-management-system'));
        }

        // Check class capacity
        $capacity = get_field('capacity', $class_id);
        if ($capacity) {
            $current_enrollment = $this->get_class_enrollment_count($class_id);
            if ($current_enrollment >= $capacity) {
                return new WP_Error('capacity_exceeded', sprintf(
                    __('Class capacity exceeded. Current: %d, Maximum: %d', 'school-management-system'),
                    $current_enrollment,
                    $capacity
                ));
            }
        }

        return true;
    }

    /**
     * Record previous class information.
     */
    private function record_previous_class($student_id, $class_id, $reason, $end_date = null) {
        $previous_classes = get_field('previous_classes', $student_id) ?: [];
        $enrollment_date = get_field('enrollment_date', $student_id);
        $end_date = $end_date ?: current_time('Y-m-d');

        $previous_classes[] = [
            'prev_class' => $class_id,
            'prev_start_date' => $enrollment_date,
            'prev_end_date' => $end_date,
            'prev_reason' => $reason
        ];

        update_field('previous_classes', $previous_classes, $student_id);
    }

    /**
     * Add enrollment history record.
     */
    private function add_enrollment_history($student_id, $class_id, $date, $action, $notes = '', $from_class_id = null) {
        $history = get_post_meta($student_id, 'enrollment_history', true) ?: [];
        
        $history[] = [
            'date' => $date,
            'action' => $action,
            'class_id' => $class_id,
            'from_class_id' => $from_class_id,
            'notes' => $notes,
            'user_id' => get_current_user_id(),
            'timestamp' => current_time('Y-m-d H:i:s')
        ];

        update_post_meta($student_id, 'enrollment_history', $history);
    }

    /**
     * Get class enrollment count.
     */
    private function get_class_enrollment_count($class_id) {
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
     * Update class enrollment count meta.
     */
    private function update_class_enrollment_count($class_id) {
        $count = $this->get_class_enrollment_count($class_id);
        update_post_meta($class_id, 'current_enrollment', $count);
        
        // Update last enrollment update timestamp
        update_post_meta($class_id, 'enrollment_last_updated', current_time('Y-m-d H:i:s'));
    }

    /**
     * Get available classes for enrollment.
     */
    public function get_available_classes($grade_level = null, $academic_year = null) {
        $args = [
            'post_type' => 'sms_classes',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC'
        ];

        $meta_query = [];
        $tax_query = [];

        // Filter by grade level if specified
        if ($grade_level) {
            $tax_query[] = [
                'taxonomy' => 'sms_grades',
                'field' => 'slug',
                'terms' => $grade_level
            ];
        }

        // Filter by academic year if specified
        if ($academic_year) {
            $tax_query[] = [
                'taxonomy' => 'sms_academic_years',
                'field' => 'slug',
                'terms' => $academic_year
            ];
        }

        if (!empty($tax_query)) {
            $args['tax_query'] = $tax_query;
        }

        $classes = get_posts($args);
        $available_classes = [];

        foreach ($classes as $class) {
            $capacity = get_field('capacity', $class->ID);
            $current_enrollment = $this->get_class_enrollment_count($class->ID);
            $available_spots = $capacity ? ($capacity - $current_enrollment) : null;

            // Only include classes with available spots (or no capacity limit)
            if (!$capacity || $available_spots > 0) {
                $available_classes[] = [
                    'id' => $class->ID,
                    'title' => $class->post_title,
                    'capacity' => $capacity,
                    'current_enrollment' => $current_enrollment,
                    'available_spots' => $available_spots,
                    'grade_level' => $this->get_class_grade_level($class->ID),
                    'academic_year' => $this->get_class_academic_year($class->ID)
                ];
            }
        }

        return $available_classes;
    }

    /**
     * Get class grade level.
     */
    private function get_class_grade_level($class_id) {
        $terms = get_the_terms($class_id, 'sms_grades');
        return $terms && !is_wp_error($terms) ? $terms[0]->name : '';
    }

    /**
     * Get class academic year.
     */
    private function get_class_academic_year($class_id) {
        $terms = get_the_terms($class_id, 'sms_academic_years');
        return $terms && !is_wp_error($terms) ? $terms[0]->name : '';
    }

    /**
     * Handle academic year progression.
     */
    public function handle_academic_year_progression($from_year, $to_year, $promotion_rules = []) {
        if (!current_user_can('manage_students')) {
            return new WP_Error('insufficient_permissions', __('You do not have permission to manage academic year progression', 'school-management-system'));
        }

        // Get all active students
        $students = get_posts([
            'post_type' => 'sms_students',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => 'student_status',
                    'value' => 'active',
                    'compare' => '='
                ]
            ]
        ]);

        $promoted = 0;
        $errors = 0;
        $results = [];

        foreach ($students as $student) {
            $current_class = get_field('assigned_class', $student->ID);
            if (!$current_class) {
                continue;
            }

            // Determine next class based on promotion rules
            $next_class = $this->determine_next_class($student->ID, $current_class, $promotion_rules);
            
            if ($next_class) {
                $result = $this->transfer_student(
                    $student->ID,
                    $current_class,
                    $next_class,
                    self::TRANSFER_PROMOTION,
                    current_time('Y-m-d'),
                    sprintf(__('Academic year progression from %s to %s', 'school-management-system'), $from_year, $to_year)
                );

                if (is_wp_error($result)) {
                    $errors++;
                    $results[] = [
                        'student_id' => $student->ID,
                        'student_name' => get_field('full_name', $student->ID),
                        'status' => 'error',
                        'message' => $result->get_error_message()
                    ];
                } else {
                    $promoted++;
                    $results[] = [
                        'student_id' => $student->ID,
                        'student_name' => get_field('full_name', $student->ID),
                        'status' => 'promoted',
                        'from_class' => get_the_title($current_class),
                        'to_class' => get_the_title($next_class)
                    ];
                }
            }
        }

        // Log the progression
        $this->log("Academic year progression completed", 'info', [
            'from_year' => $from_year,
            'to_year' => $to_year,
            'promoted' => $promoted,
            'errors' => $errors,
            'total_students' => count($students)
        ]);

        return [
            'promoted' => $promoted,
            'errors' => $errors,
            'total' => count($students),
            'results' => $results
        ];
    }

    /**
     * Determine next class for student promotion.
     */
    private function determine_next_class($student_id, $current_class_id, $promotion_rules) {
        // Get current class grade level
        $current_grade_terms = get_the_terms($current_class_id, 'sms_grades');
        if (!$current_grade_terms || is_wp_error($current_grade_terms)) {
            return null;
        }

        $current_grade = $current_grade_terms[0];
        
        // Check if there are specific promotion rules for this grade
        if (isset($promotion_rules[$current_grade->slug])) {
            $next_grade_slug = $promotion_rules[$current_grade->slug];
        } else {
            // Default promotion logic - find next grade numerically
            $next_grade_slug = $this->get_next_grade_slug($current_grade->slug);
        }

        if (!$next_grade_slug) {
            return null; // No next grade (graduation)
        }

        // Find available class in next grade
        $available_classes = $this->get_available_classes($next_grade_slug);
        
        return !empty($available_classes) ? $available_classes[0]['id'] : null;
    }

    /**
     * Get next grade slug for promotion.
     */
    private function get_next_grade_slug($current_grade_slug) {
        // Extract numeric part from grade slug (e.g., 'grade-1' -> '1')
        preg_match('/(\d+)/', $current_grade_slug, $matches);
        if (!$matches) {
            return null;
        }

        $current_number = intval($matches[1]);
        $next_number = $current_number + 1;
        
        // Check if next grade exists
        $next_grade_slug = str_replace($matches[1], $next_number, $current_grade_slug);
        $next_grade = get_term_by('slug', $next_grade_slug, 'sms_grades');
        
        return $next_grade ? $next_grade_slug : null;
    }

    /**
     * Add enrollment management meta boxes.
     */
    public function add_enrollment_meta_boxes() {
        add_meta_box(
            'sms-enrollment-management',
            __('Enrollment Management', 'school-management-system'),
            [$this, 'render_enrollment_meta_box'],
            'sms_students',
            'side',
            'default'
        );

        add_meta_box(
            'sms-enrollment-history',
            __('Enrollment History', 'school-management-system'),
            [$this, 'render_enrollment_history_meta_box'],
            'sms_students',
            'normal',
            'default'
        );
    }

    /**
     * Render enrollment management meta box.
     */
    public function render_enrollment_meta_box($post) {
        $current_class = get_field('assigned_class', $post->ID);
        $enrollment_date = get_field('enrollment_date', $post->ID);
        $student_status = get_field('student_status', $post->ID);

        echo '<div id="sms-enrollment-management">';
        
        // Current enrollment status
        echo '<p><strong>' . __('Current Class:', 'school-management-system') . '</strong><br>';
        if ($current_class) {
            echo '<a href="' . get_edit_post_link($current_class) . '">' . get_the_title($current_class) . '</a>';
            if ($enrollment_date) {
                echo '<br><small>' . sprintf(__('Enrolled: %s', 'school-management-system'), date('M j, Y', strtotime($enrollment_date))) . '</small>';
            }
        } else {
            echo __('Not enrolled in any class', 'school-management-system');
        }
        echo '</p>';

        // Enrollment actions
        if (current_user_can('manage_students') && in_array($student_status, ['active', 'enrolled'])) {
            echo '<div class="enrollment-actions">';
            
            if ($current_class) {
                echo '<button type="button" class="button transfer-student" data-student-id="' . esc_attr($post->ID) . '" data-current-class="' . esc_attr($current_class) . '">';
                echo __('Transfer to Another Class', 'school-management-system') . '</button>';
            } else {
                echo '<button type="button" class="button button-primary enroll-in-class" data-student-id="' . esc_attr($post->ID) . '">';
                echo __('Enroll in Class', 'school-management-system') . '</button>';
            }
            
            echo '</div>';
        }

        echo '</div>';

        // Add JavaScript for enrollment management
        $this->add_enrollment_management_scripts();
    }

    /**
     * Render enrollment history meta box.
     */
    public function render_enrollment_history_meta_box($post) {
        $previous_classes = get_field('previous_classes', $post->ID);
        $enrollment_history = get_post_meta($post->ID, 'enrollment_history', true);

        echo '<div id="sms-enrollment-history">';

        if (is_array($previous_classes) && !empty($previous_classes)) {
            echo '<h4>' . __('Previous Classes', 'school-management-system') . '</h4>';
            echo '<table class="widefat">';
            echo '<thead><tr>';
            echo '<th>' . __('Class', 'school-management-system') . '</th>';
            echo '<th>' . __('Start Date', 'school-management-system') . '</th>';
            echo '<th>' . __('End Date', 'school-management-system') . '</th>';
            echo '<th>' . __('Reason', 'school-management-system') . '</th>';
            echo '</tr></thead><tbody>';

            foreach ($previous_classes as $prev_class) {
                echo '<tr>';
                echo '<td>' . ($prev_class['prev_class'] ? get_the_title($prev_class['prev_class']) : '—') . '</td>';
                echo '<td>' . ($prev_class['prev_start_date'] ? date('M j, Y', strtotime($prev_class['prev_start_date'])) : '—') . '</td>';
                echo '<td>' . ($prev_class['prev_end_date'] ? date('M j, Y', strtotime($prev_class['prev_end_date'])) : '—') . '</td>';
                echo '<td>' . esc_html($prev_class['prev_reason'] ?: '—') . '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
        }

        if (is_array($enrollment_history) && !empty($enrollment_history)) {
            echo '<h4>' . __('Enrollment Activity Log', 'school-management-system') . '</h4>';
            echo '<table class="widefat">';
            echo '<thead><tr>';
            echo '<th>' . __('Date', 'school-management-system') . '</th>';
            echo '<th>' . __('Action', 'school-management-system') . '</th>';
            echo '<th>' . __('Details', 'school-management-system') . '</th>';
            echo '<th>' . __('By', 'school-management-system') . '</th>';
            echo '</tr></thead><tbody>';

            // Sort by date descending
            usort($enrollment_history, function($a, $b) {
                return strtotime($b['timestamp']) - strtotime($a['timestamp']);
            });

            foreach ($enrollment_history as $entry) {
                echo '<tr>';
                echo '<td>' . date('M j, Y g:i A', strtotime($entry['timestamp'])) . '</td>';
                echo '<td>' . esc_html(ucfirst($entry['action'])) . '</td>';
                
                $details = '';
                if ($entry['action'] === 'transferred' && $entry['from_class_id']) {
                    $details = sprintf(__('From %s to %s', 'school-management-system'), 
                        get_the_title($entry['from_class_id']), 
                        get_the_title($entry['class_id'])
                    );
                } else {
                    $details = get_the_title($entry['class_id']);
                }
                if ($entry['notes']) {
                    $details .= '<br><small>' . esc_html($entry['notes']) . '</small>';
                }
                echo '<td>' . $details . '</td>';
                
                $user = get_userdata($entry['user_id']);
                echo '<td>' . ($user ? $user->display_name : __('Unknown', 'school-management-system')) . '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
        }

        if (empty($previous_classes) && empty($enrollment_history)) {
            echo '<p>' . __('No enrollment history available.', 'school-management-system') . '</p>';
        }

        echo '</div>';
    }

    /**
     * Add JavaScript for enrollment management.
     */
    private function add_enrollment_management_scripts() {
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Enroll in class
            $('.enroll-in-class').on('click', function() {
                var studentId = $(this).data('student-id');
                showClassSelectionDialog(studentId, 'enroll');
            });
            
            // Transfer student
            $('.transfer-student').on('click', function() {
                var studentId = $(this).data('student-id');
                var currentClass = $(this).data('current-class');
                showClassSelectionDialog(studentId, 'transfer', currentClass);
            });
            
            function showClassSelectionDialog(studentId, action, currentClass) {
                // Get available classes
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'sms_get_available_classes',
                        nonce: '<?php echo wp_create_nonce('sms_enrollment_management'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            showClassDialog(studentId, action, currentClass, response.data);
                        } else {
                            alert('Error loading classes: ' + response.data.message);
                        }
                    }
                });
            }
            
            function showClassDialog(studentId, action, currentClass, classes) {
                var dialogHtml = '<div id="class-selection-dialog" title="' + 
                    (action === 'enroll' ? '<?php echo esc_js(__('Select Class for Enrollment', 'school-management-system')); ?>' : 
                     '<?php echo esc_js(__('Select Class for Transfer', 'school-management-system')); ?>') + '">';
                
                dialogHtml += '<form id="class-selection-form">';
                dialogHtml += '<p><label for="selected-class"><?php echo esc_js(__('Available Classes:', 'school-management-system')); ?></label></p>';
                dialogHtml += '<select id="selected-class" name="selected_class" style="width: 100%;">';
                dialogHtml += '<option value=""><?php echo esc_js(__('Select a class...', 'school-management-system')); ?></option>';
                
                $.each(classes, function(index, classInfo) {
                    if (classInfo.id != currentClass) {
                        var capacityInfo = classInfo.capacity ? 
                            ' (' + classInfo.current_enrollment + '/' + classInfo.capacity + ')' : 
                            ' (' + classInfo.current_enrollment + ' enrolled)';
                        dialogHtml += '<option value="' + classInfo.id + '">' + 
                            classInfo.title + capacityInfo + '</option>';
                    }
                });
                
                dialogHtml += '</select>';
                
                if (action === 'transfer') {
                    dialogHtml += '<p><label for="transfer-reason"><?php echo esc_js(__('Reason for Transfer:', 'school-management-system')); ?></label></p>';
                    dialogHtml += '<select id="transfer-reason" name="transfer_reason" style="width: 100%;">';
                    dialogHtml += '<option value="<?php echo self::TRANSFER_PROMOTION; ?>"><?php echo esc_js(__('Promotion', 'school-management-system')); ?></option>';
                    dialogHtml += '<option value="<?php echo self::TRANSFER_LATERAL; ?>"><?php echo esc_js(__('Lateral Transfer', 'school-management-system')); ?></option>';
                    dialogHtml += '<option value="<?php echo self::TRANSFER_PARENT_REQUEST; ?>"><?php echo esc_js(__('Parent Request', 'school-management-system')); ?></option>';
                    dialogHtml += '<option value="<?php echo self::TRANSFER_ACADEMIC_PERFORMANCE; ?>"><?php echo esc_js(__('Academic Performance', 'school-management-system')); ?></option>';
                    dialogHtml += '<option value="<?php echo self::TRANSFER_DISCIPLINARY; ?>"><?php echo esc_js(__('Disciplinary', 'school-management-system')); ?></option>';
                    dialogHtml += '</select>';
                }
                
                dialogHtml += '<p><label for="enrollment-notes"><?php echo esc_js(__('Notes (optional):', 'school-management-system')); ?></label></p>';
                dialogHtml += '<textarea id="enrollment-notes" name="notes" rows="3" style="width: 100%;"></textarea>';
                dialogHtml += '</form>';
                dialogHtml += '</div>';
                
                $('body').append(dialogHtml);
                
                $('#class-selection-dialog').dialog({
                    modal: true,
                    width: 500,
                    buttons: {
                        '<?php echo esc_js(__('Confirm', 'school-management-system')); ?>': function() {
                            var selectedClass = $('#selected-class').val();
                            var notes = $('#enrollment-notes').val();
                            
                            if (!selectedClass) {
                                alert('<?php echo esc_js(__('Please select a class', 'school-management-system')); ?>');
                                return;
                            }
                            
                            var ajaxData = {
                                student_id: studentId,
                                class_id: selectedClass,
                                notes: notes,
                                nonce: '<?php echo wp_create_nonce('sms_enrollment_management'); ?>'
                            };
                            
                            if (action === 'enroll') {
                                ajaxData.action = 'sms_enroll_student_in_class';
                            } else {
                                ajaxData.action = 'sms_transfer_student';
                                ajaxData.from_class_id = currentClass;
                                ajaxData.reason = $('#transfer-reason').val();
                            }
                            
                            $.ajax({
                                url: ajaxurl,
                                type: 'POST',
                                data: ajaxData,
                                success: function(response) {
                                    if (response.success) {
                                        location.reload();
                                    } else {
                                        alert('Error: ' + response.data.message);
                                    }
                                }
                            });
                            
                            $(this).dialog('close');
                        },
                        '<?php echo esc_js(__('Cancel', 'school-management-system')); ?>': function() {
                            $(this).dialog('close');
                        }
                    },
                    close: function() {
                        $(this).remove();
                    }
                });
            }
        });
        </script>
        <style>
        .enrollment-actions { margin-top: 10px; }
        .enrollment-actions .button { margin-right: 5px; }
        #sms-enrollment-history table { margin-top: 10px; }
        #sms-enrollment-history th, #sms-enrollment-history td { padding: 8px; }
        </style>
        <?php
    }

    /**
     * AJAX handler for enrolling student in class.
     */
    public function ajax_enroll_student_in_class() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_enrollment_management')) {
            wp_die('Security check failed');
        }

        $student_id = intval($_POST['student_id']);
        $class_id = intval($_POST['class_id']);
        $notes = sanitize_textarea_field($_POST['notes']);

        $result = $this->enroll_student_in_class($student_id, $class_id, null, $notes);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        } else {
            wp_send_json_success(['message' => __('Student enrolled successfully', 'school-management-system')]);
        }
    }

    /**
     * AJAX handler for transferring student.
     */
    public function ajax_transfer_student() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_enrollment_management')) {
            wp_die('Security check failed');
        }

        $student_id = intval($_POST['student_id']);
        $from_class_id = intval($_POST['from_class_id']);
        $to_class_id = intval($_POST['class_id']);
        $reason = sanitize_text_field($_POST['reason']);
        $notes = sanitize_textarea_field($_POST['notes']);

        $result = $this->transfer_student($student_id, $from_class_id, $to_class_id, $reason, null, $notes);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        } else {
            wp_send_json_success(['message' => __('Student transferred successfully', 'school-management-system')]);
        }
    }

    /**
     * AJAX handler for getting available classes.
     */
    public function ajax_get_available_classes() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_enrollment_management')) {
            wp_die('Security check failed');
        }

        $classes = $this->get_available_classes();
        wp_send_json_success($classes);
    }

    /**
     * Add bulk enrollment actions.
     */
    public function add_bulk_enrollment_actions($actions) {
        if (current_user_can('manage_students')) {
            $actions['bulk_enroll_class'] = __('Enroll in Class', 'school-management-system');
            $actions['bulk_transfer_class'] = __('Transfer to Class', 'school-management-system');
        }
        return $actions;
    }

    /**
     * Handle bulk enrollment actions.
     */
    public function handle_bulk_enrollment_actions($redirect_to, $doaction, $post_ids) {
        if (!in_array($doaction, ['bulk_enroll_class', 'bulk_transfer_class'])) {
            return $redirect_to;
        }

        // For bulk actions, we need to redirect to a selection page
        // This would typically be handled by a separate admin page
        $redirect_to = add_query_arg([
            'page' => 'sms-bulk-enrollment',
            'action' => $doaction,
            'students' => implode(',', $post_ids)
        ], admin_url('admin.php'));

        return $redirect_to;
    }

    /**
     * Validate class assignment field.
     */
    public function validate_class_assignment($valid, $value, $field, $input) {
        if (!$valid || !$value) {
            return $valid;
        }

        // Get the student ID from the current post
        $student_id = get_the_ID();
        if (!$student_id) {
            return $valid;
        }

        // Check if this is a new assignment or change
        $current_class = get_field('assigned_class', $student_id);
        if ($current_class == $value) {
            return $valid; // No change
        }

        // Validate class capacity
        $validation = $this->validate_class_for_enrollment($value);
        if (is_wp_error($validation)) {
            $valid = $validation->get_error_message();
        }

        return $valid;
    }

    /**
     * Get enrollment statistics.
     */
    public function get_enrollment_statistics($class_id = null, $grade_level = null, $academic_year = null) {
        $args = [
            'post_type' => 'sms_students',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => 'student_status',
                    'value' => ['active', 'enrolled'],
                    'compare' => 'IN'
                ]
            ],
            'fields' => 'ids'
        ];

        if ($class_id) {
            $args['meta_query'][] = [
                'key' => 'assigned_class',
                'value' => $class_id,
                'compare' => '='
            ];
        }

        $tax_query = [];
        if ($grade_level) {
            $tax_query[] = [
                'taxonomy' => 'sms_grades',
                'field' => 'slug',
                'terms' => $grade_level
            ];
        }

        if ($academic_year) {
            $tax_query[] = [
                'taxonomy' => 'sms_academic_years',
                'field' => 'slug',
                'terms' => $academic_year
            ];
        }

        if (!empty($tax_query)) {
            $args['tax_query'] = $tax_query;
        }

        $query = new WP_Query($args);
        return $query->found_posts;
    }
}