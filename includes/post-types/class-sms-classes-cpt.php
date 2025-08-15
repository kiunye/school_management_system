<?php
/**
 * Classes Custom Post Type
 *
 * Handles the registration and management of the classes custom post type
 * with capacity management, teacher assignments, and student relationships.
 *
 * @package    School_Management_System
 * @subpackage School_Management_System/includes/post-types
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Classes Custom Post Type Class
 */
class SMS_Classes_CPT extends SMS_Base {

    /**
     * The post type name.
     */
    const POST_TYPE = 'sms_classes';

    /**
     * Initialize the class and set its properties.
     */
    public function __construct() {
        parent::__construct();
        
        // Register the custom post type
        add_action('init', [$this, 'register_post_type']);
        
        // Add ACF field groups
        add_action('acf/init', [$this, 'register_acf_fields']);
        
        // Add custom columns to admin list
        add_filter('manage_' . self::POST_TYPE . '_posts_columns', [$this, 'add_custom_columns']);
        add_action('manage_' . self::POST_TYPE . '_posts_custom_column', [$this, 'populate_custom_columns'], 10, 2);
        
        // Make columns sortable
        add_filter('manage_edit-' . self::POST_TYPE . '_sortable_columns', [$this, 'make_columns_sortable']);
        
        // Add meta boxes
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        
        // Save meta data and handle capacity validation
        add_action('save_post_' . self::POST_TYPE, [$this, 'save_class_data'], 10, 3);
        
        // Add AJAX handlers for capacity checking
        add_action('wp_ajax_check_class_capacity', [$this, 'ajax_check_class_capacity']);
        add_action('wp_ajax_get_class_students', [$this, 'ajax_get_class_students']);
    }

    /**
     * Register the classes custom post type.
     */
    public function register_post_type() {
        $labels = [
            'name'                  => _x('Classes', 'Post Type General Name', 'school-management-system'),
            'singular_name'         => _x('Class', 'Post Type Singular Name', 'school-management-system'),
            'menu_name'             => __('Classes', 'school-management-system'),
            'name_admin_bar'        => __('Class', 'school-management-system'),
            'archives'              => __('Class Archives', 'school-management-system'),
            'attributes'            => __('Class Attributes', 'school-management-system'),
            'parent_item_colon'     => __('Parent Class:', 'school-management-system'),
            'all_items'             => __('All Classes', 'school-management-system'),
            'add_new_item'          => __('Add New Class', 'school-management-system'),
            'add_new'               => __('Add New', 'school-management-system'),
            'new_item'              => __('New Class', 'school-management-system'),
            'edit_item'             => __('Edit Class', 'school-management-system'),
            'update_item'           => __('Update Class', 'school-management-system'),
            'view_item'             => __('View Class', 'school-management-system'),
            'view_items'            => __('View Classes', 'school-management-system'),
            'search_items'          => __('Search Class', 'school-management-system'),
            'not_found'             => __('Not found', 'school-management-system'),
            'not_found_in_trash'    => __('Not found in Trash', 'school-management-system'),
            'featured_image'        => __('Class Image', 'school-management-system'),
            'set_featured_image'    => __('Set class image', 'school-management-system'),
            'remove_featured_image' => __('Remove class image', 'school-management-system'),
            'use_featured_image'    => __('Use as class image', 'school-management-system'),
            'insert_into_item'      => __('Insert into class', 'school-management-system'),
            'uploaded_to_this_item' => __('Uploaded to this class', 'school-management-system'),
            'items_list'            => __('Classes list', 'school-management-system'),
            'items_list_navigation' => __('Classes list navigation', 'school-management-system'),
            'filter_items_list'     => __('Filter classes list', 'school-management-system'),
        ];

        $args = [
            'label'                 => __('Class', 'school-management-system'),
            'description'           => __('Class management and organization', 'school-management-system'),
            'labels'                => $labels,
            'supports'              => ['title', 'editor', 'thumbnail', 'revisions'],
            'taxonomies'            => ['sms_grades', 'sms_academic_years', 'sms_subjects'],
            'hierarchical'          => false,
            'public'                => false,
            'show_ui'               => true,
            'show_in_menu'          => 'school-management',
            'menu_position'         => 6,
            'menu_icon'             => 'dashicons-welcome-learn-more',
            'show_in_admin_bar'     => true,
            'show_in_nav_menus'     => false,
            'can_export'            => true,
            'has_archive'           => false,
            'exclude_from_search'   => true,
            'publicly_queryable'    => false,
            'capability_type'       => 'post',
            'capabilities'          => [
                'edit_post'          => 'edit_posts',
                'read_post'          => 'edit_posts',
                'delete_post'        => 'delete_posts',
                'edit_posts'         => 'edit_posts',
                'edit_others_posts'  => 'edit_others_posts',
                'delete_posts'       => 'delete_posts',
                'publish_posts'      => 'publish_posts',
                'read_private_posts' => 'read_private_posts',
            ],
            'show_in_rest'          => true,
            'rest_base'             => 'classes',
            'rest_controller_class' => 'WP_REST_Posts_Controller',
        ];

        register_post_type(self::POST_TYPE, $args);
    }

    /**
     * Register ACF field groups for classes.
     */
    public function register_acf_fields() {
        if (!function_exists('acf_add_local_field_group')) {
            return;
        }

        // Basic Class Information Field Group
        acf_add_local_field_group([
            'key' => 'group_class_basic_info',
            'title' => 'Class Information',
            'fields' => [
                [
                    'key' => 'field_class_name',
                    'label' => 'Class Name',
                    'name' => 'class_name',
                    'type' => 'text',
                    'instructions' => 'Name of the class (e.g., Grade 5A, Form 2 Blue)',
                    'required' => 1,
                    'wrapper' => [
                        'width' => '50',
                    ],
                ],
                [
                    'key' => 'field_class_code',
                    'label' => 'Class Code',
                    'name' => 'class_code',
                    'type' => 'text',
                    'instructions' => 'Short code for the class (e.g., G5A, F2B)',
                    'required' => 1,
                    'wrapper' => [
                        'width' => '50',
                    ],
                ],
                [
                    'key' => 'field_class_capacity',
                    'label' => 'Class Capacity',
                    'name' => 'class_capacity',
                    'type' => 'number',
                    'instructions' => 'Maximum number of students allowed in this class',
                    'required' => 1,
                    'default_value' => 40,
                    'min' => 1,
                    'max' => 100,
                    'wrapper' => [
                        'width' => '33',
                    ],
                ],
                [
                    'key' => 'field_current_enrollment',
                    'label' => 'Current Enrollment',
                    'name' => 'current_enrollment',
                    'type' => 'number',
                    'instructions' => 'Current number of enrolled students (auto-calculated)',
                    'readonly' => 1,
                    'default_value' => 0,
                    'wrapper' => [
                        'width' => '33',
                    ],
                ],
                [
                    'key' => 'field_class_status',
                    'label' => 'Class Status',
                    'name' => 'class_status',
                    'type' => 'select',
                    'instructions' => 'Current status of the class',
                    'required' => 1,
                    'choices' => [
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'archived' => 'Archived',
                    ],
                    'default_value' => 'active',
                    'wrapper' => [
                        'width' => '34',
                    ],
                ],
            ],
            'location' => [
                [
                    [
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => self::POST_TYPE,
                    ],
                ],
            ],
            'menu_order' => 0,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
        ]);

        // Teacher Assignment Field Group
        acf_add_local_field_group([
            'key' => 'group_class_teacher_assignment',
            'title' => 'Teacher Assignment',
            'fields' => [
                [
                    'key' => 'field_class_teacher',
                    'label' => 'Class Teacher',
                    'name' => 'class_teacher',
                    'type' => 'user',
                    'instructions' => 'Primary teacher responsible for this class',
                    'required' => 1,
                    'role' => ['teacher', 'administrator'],
                    'wrapper' => [
                        'width' => '50',
                    ],
                ],
                [
                    'key' => 'field_assistant_teachers',
                    'label' => 'Assistant Teachers',
                    'name' => 'assistant_teachers',
                    'type' => 'user',
                    'instructions' => 'Additional teachers assigned to this class',
                    'multiple' => 1,
                    'role' => ['teacher', 'administrator'],
                    'wrapper' => [
                        'width' => '50',
                    ],
                ],
                [
                    'key' => 'field_subject_assignments',
                    'label' => 'Subject Assignments',
                    'name' => 'subject_assignments',
                    'type' => 'repeater',
                    'instructions' => 'Assign teachers to specific subjects for this class',
                    'layout' => 'table',
                    'button_label' => 'Add Subject Assignment',
                    'sub_fields' => [
                        [
                            'key' => 'field_subject',
                            'label' => 'Subject',
                            'name' => 'subject',
                            'type' => 'taxonomy',
                            'taxonomy' => 'sms_subjects',
                            'field_type' => 'select',
                            'required' => 1,
                        ],
                        [
                            'key' => 'field_subject_teacher',
                            'label' => 'Teacher',
                            'name' => 'subject_teacher',
                            'type' => 'user',
                            'role' => ['teacher', 'administrator'],
                            'required' => 1,
                        ],
                        [
                            'key' => 'field_periods_per_week',
                            'label' => 'Periods/Week',
                            'name' => 'periods_per_week',
                            'type' => 'number',
                            'min' => 1,
                            'max' => 10,
                            'default_value' => 1,
                        ],
                    ],
                ],
            ],
            'location' => [
                [
                    [
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => self::POST_TYPE,
                    ],
                ],
            ],
            'menu_order' => 1,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
        ]);

        // Class Settings Field Group
        acf_add_local_field_group([
            'key' => 'group_class_settings',
            'title' => 'Class Settings',
            'fields' => [
                [
                    'key' => 'field_classroom_location',
                    'label' => 'Classroom Location',
                    'name' => 'classroom_location',
                    'type' => 'text',
                    'instructions' => 'Physical location of the classroom (e.g., Block A, Room 101)',
                    'wrapper' => [
                        'width' => '50',
                    ],
                ],
                [
                    'key' => 'field_class_schedule',
                    'label' => 'Class Schedule',
                    'name' => 'class_schedule',
                    'type' => 'select',
                    'instructions' => 'Daily schedule for this class',
                    'choices' => [
                        'morning' => 'Morning (8:00 AM - 12:00 PM)',
                        'afternoon' => 'Afternoon (1:00 PM - 5:00 PM)',
                        'full_day' => 'Full Day (8:00 AM - 5:00 PM)',
                    ],
                    'default_value' => 'full_day',
                    'wrapper' => [
                        'width' => '50',
                    ],
                ],
                [
                    'key' => 'field_class_fees',
                    'label' => 'Class-Specific Fees',
                    'name' => 'class_fees',
                    'type' => 'repeater',
                    'instructions' => 'Additional fees specific to this class',
                    'layout' => 'table',
                    'button_label' => 'Add Fee',
                    'sub_fields' => [
                        [
                            'key' => 'field_fee_name',
                            'label' => 'Fee Name',
                            'name' => 'fee_name',
                            'type' => 'text',
                            'required' => 1,
                        ],
                        [
                            'key' => 'field_fee_amount',
                            'label' => 'Amount (KES)',
                            'name' => 'fee_amount',
                            'type' => 'number',
                            'required' => 1,
                            'min' => 0,
                        ],
                        [
                            'key' => 'field_fee_frequency',
                            'label' => 'Frequency',
                            'name' => 'fee_frequency',
                            'type' => 'select',
                            'choices' => [
                                'one_time' => 'One Time',
                                'monthly' => 'Monthly',
                                'termly' => 'Termly',
                                'yearly' => 'Yearly',
                            ],
                            'default_value' => 'termly',
                        ],
                    ],
                ],
                [
                    'key' => 'field_class_notes',
                    'label' => 'Class Notes',
                    'name' => 'class_notes',
                    'type' => 'textarea',
                    'instructions' => 'Additional notes or information about this class',
                    'rows' => 4,
                ],
            ],
            'location' => [
                [
                    [
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => self::POST_TYPE,
                    ],
                ],
            ],
            'menu_order' => 2,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
        ]);
    }

    /**
     * Add custom columns to the classes admin list.
     */
    public function add_custom_columns($columns) {
        $new_columns = [];
        
        // Keep the checkbox
        if (isset($columns['cb'])) {
            $new_columns['cb'] = $columns['cb'];
        }
        
        // Add custom columns
        $new_columns['class_name'] = __('Class Name', 'school-management-system');
        $new_columns['class_code'] = __('Code', 'school-management-system');
        $new_columns['grade'] = __('Grade', 'school-management-system');
        $new_columns['teacher'] = __('Class Teacher', 'school-management-system');
        $new_columns['enrollment'] = __('Enrollment', 'school-management-system');
        $new_columns['status'] = __('Status', 'school-management-system');
        
        // Keep the date column
        if (isset($columns['date'])) {
            $new_columns['date'] = $columns['date'];
        }
        
        return $new_columns;
    }

    /**
     * Populate custom columns with data.
     */
    public function populate_custom_columns($column, $post_id) {
        switch ($column) {
            case 'class_name':
                $class_name = get_field('class_name', $post_id);
                echo $class_name ? esc_html($class_name) : '—';
                break;
                
            case 'class_code':
                $class_code = get_field('class_code', $post_id);
                echo $class_code ? esc_html($class_code) : '—';
                break;
                
            case 'grade':
                $terms = get_the_terms($post_id, 'sms_grades');
                if ($terms && !is_wp_error($terms)) {
                    $grade_names = wp_list_pluck($terms, 'name');
                    echo esc_html(implode(', ', $grade_names));
                } else {
                    echo '—';
                }
                break;
                
            case 'teacher':
                $teacher_id = get_field('class_teacher', $post_id);
                if ($teacher_id) {
                    $teacher = get_userdata($teacher_id);
                    if ($teacher) {
                        echo esc_html($teacher->display_name);
                    } else {
                        echo '—';
                    }
                } else {
                    echo '—';
                }
                break;
                
            case 'enrollment':
                $capacity = get_field('class_capacity', $post_id);
                $current = $this->get_current_enrollment($post_id);
                
                $percentage = $capacity > 0 ? ($current / $capacity) * 100 : 0;
                $class = '';
                
                if ($percentage >= 90) {
                    $class = 'status-full';
                } elseif ($percentage >= 75) {
                    $class = 'status-high';
                } elseif ($percentage >= 50) {
                    $class = 'status-medium';
                } else {
                    $class = 'status-low';
                }
                
                echo '<span class="' . esc_attr($class) . '">' . 
                     esc_html($current . '/' . $capacity) . 
                     ' (' . esc_html(round($percentage, 1)) . '%)</span>';
                break;
                
            case 'status':
                $status = get_field('class_status', $post_id);
                if ($status) {
                    $status_class = 'status-' . $status;
                    echo '<span class="' . esc_attr($status_class) . '">' . esc_html(ucfirst($status)) . '</span>';
                } else {
                    echo '—';
                }
                break;
        }
    }

    /**
     * Make custom columns sortable.
     */
    public function make_columns_sortable($columns) {
        $columns['class_name'] = 'class_name';
        $columns['class_code'] = 'class_code';
        $columns['status'] = 'class_status';
        
        return $columns;
    }

    /**
     * Add meta boxes.
     */
    public function add_meta_boxes() {
        add_meta_box(
            'class-enrollment-info',
            __('Enrollment Information', 'school-management-system'),
            [$this, 'render_enrollment_info_meta_box'],
            self::POST_TYPE,
            'side',
            'high'
        );

        add_meta_box(
            'class-students-list',
            __('Enrolled Students', 'school-management-system'),
            [$this, 'render_students_list_meta_box'],
            self::POST_TYPE,
            'normal',
            'low'
        );
    }

    /**
     * Render enrollment info meta box.
     */
    public function render_enrollment_info_meta_box($post) {
        $capacity = get_field('class_capacity', $post->ID) ?: 0;
        $current = $this->get_current_enrollment($post->ID);
        $available = $capacity - $current;
        $percentage = $capacity > 0 ? ($current / $capacity) * 100 : 0;
        
        echo '<div class="enrollment-stats">';
        echo '<p><strong>' . __('Capacity:', 'school-management-system') . '</strong> ' . esc_html($capacity) . '</p>';
        echo '<p><strong>' . __('Current Enrollment:', 'school-management-system') . '</strong> ' . esc_html($current) . '</p>';
        echo '<p><strong>' . __('Available Spots:', 'school-management-system') . '</strong> ' . esc_html($available) . '</p>';
        echo '<p><strong>' . __('Utilization:', 'school-management-system') . '</strong> ' . esc_html(round($percentage, 1)) . '%</p>';
        
        // Progress bar
        echo '<div class="enrollment-progress">';
        echo '<div class="progress-bar" style="width: ' . esc_attr($percentage) . '%"></div>';
        echo '</div>';
        echo '</div>';
        
        // Add some basic styling
        echo '<style>
            .enrollment-progress {
                width: 100%;
                height: 20px;
                background-color: #f0f0f0;
                border-radius: 10px;
                overflow: hidden;
                margin-top: 10px;
            }
            .progress-bar {
                height: 100%;
                background-color: #0073aa;
                transition: width 0.3s ease;
            }
            .enrollment-stats p {
                margin: 8px 0;
            }
        </style>';
    }

    /**
     * Render students list meta box.
     */
    public function render_students_list_meta_box($post) {
        $students = $this->get_enrolled_students($post->ID);
        
        if (empty($students)) {
            echo '<p>' . __('No students enrolled in this class yet.', 'school-management-system') . '</p>';
            return;
        }
        
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>' . __('Admission Number', 'school-management-system') . '</th>';
        echo '<th>' . __('Student Name', 'school-management-system') . '</th>';
        echo '<th>' . __('Status', 'school-management-system') . '</th>';
        echo '<th>' . __('Actions', 'school-management-system') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        foreach ($students as $student) {
            $admission_number = get_field('admission_number', $student->ID);
            $full_name = get_field('full_name', $student->ID);
            $status = get_field('student_status', $student->ID);
            
            echo '<tr>';
            echo '<td>' . esc_html($admission_number) . '</td>';
            echo '<td><strong>' . esc_html($full_name) . '</strong></td>';
            echo '<td><span class="status-' . esc_attr($status) . '">' . esc_html(ucfirst($status)) . '</span></td>';
            echo '<td>';
            echo '<a href="' . esc_url(get_edit_post_link($student->ID)) . '" class="button button-small">' . __('Edit', 'school-management-system') . '</a>';
            echo '</td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
    }

    /**
     * Save class data and handle capacity validation.
     */
    public function save_class_data($post_id, $post, $update) {
        // Skip if this is an autosave or revision
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }

        // Update current enrollment count
        $current_enrollment = $this->get_current_enrollment($post_id);
        update_field('current_enrollment', $current_enrollment, $post_id);
        
        // Log class capacity changes
        if ($update) {
            $old_capacity = get_post_meta($post_id, '_previous_capacity', true);
            $new_capacity = get_field('class_capacity', $post_id);
            
            if ($old_capacity && $old_capacity != $new_capacity) {
                $this->log("Class capacity changed from {$old_capacity} to {$new_capacity} for class ID {$post_id}");
            }
            
            update_post_meta($post_id, '_previous_capacity', $new_capacity);
        }
    }

    /**
     * Get current enrollment count for a class.
     */
    public function get_current_enrollment($class_id) {
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
                    'value' => 'active',
                    'compare' => '='
                ]
            ]
        ];
        
        $query = new WP_Query($args);
        return $query->found_posts;
    }

    /**
     * Get enrolled students for a class.
     */
    public function get_enrolled_students($class_id) {
        $args = [
            'post_type' => 'sms_students',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => 'assigned_class',
                    'value' => $class_id,
                    'compare' => '='
                ]
            ],
            'meta_key' => 'full_name',
            'orderby' => 'meta_value',
            'order' => 'ASC'
        ];
        
        $query = new WP_Query($args);
        return $query->posts;
    }

    /**
     * Check if class has available capacity.
     */
    public function has_available_capacity($class_id, $additional_students = 1) {
        $capacity = get_field('class_capacity', $class_id);
        $current = $this->get_current_enrollment($class_id);
        
        return ($current + $additional_students) <= $capacity;
    }

    /**
     * AJAX handler to check class capacity.
     */
    public function ajax_check_class_capacity() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_class_capacity_nonce')) {
            wp_die('Security check failed');
        }
        
        $class_id = intval($_POST['class_id']);
        $additional_students = intval($_POST['additional_students']) ?: 1;
        
        $has_capacity = $this->has_available_capacity($class_id, $additional_students);
        $capacity = get_field('class_capacity', $class_id);
        $current = $this->get_current_enrollment($class_id);
        $available = $capacity - $current;
        
        wp_send_json([
            'has_capacity' => $has_capacity,
            'capacity' => $capacity,
            'current' => $current,
            'available' => $available,
            'message' => $has_capacity 
                ? sprintf(__('Class has %d available spots', 'school-management-system'), $available)
                : __('Class is at full capacity', 'school-management-system')
        ]);
    }

    /**
     * AJAX handler to get class students.
     */
    public function ajax_get_class_students() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_class_students_nonce')) {
            wp_die('Security check failed');
        }
        
        $class_id = intval($_POST['class_id']);
        $students = $this->get_enrolled_students($class_id);
        
        $student_data = [];
        foreach ($students as $student) {
            $student_data[] = [
                'id' => $student->ID,
                'admission_number' => get_field('admission_number', $student->ID),
                'full_name' => get_field('full_name', $student->ID),
                'status' => get_field('student_status', $student->ID),
                'edit_link' => get_edit_post_link($student->ID)
            ];
        }
        
        wp_send_json([
            'students' => $student_data,
            'count' => count($student_data)
        ]);
    }

    /**
     * Get classes by teacher.
     */
    public static function get_classes_by_teacher($teacher_id) {
        $args = [
            'post_type' => self::POST_TYPE,
            'posts_per_page' => -1,
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => 'class_teacher',
                    'value' => $teacher_id,
                    'compare' => '='
                ],
                [
                    'key' => 'assistant_teachers',
                    'value' => '"' . $teacher_id . '"',
                    'compare' => 'LIKE'
                ]
            ]
        ];
        
        return new WP_Query($args);
    }

    /**
     * Get classes by grade.
     */
    public static function get_classes_by_grade($grade_term_id) {
        $args = [
            'post_type' => self::POST_TYPE,
            'posts_per_page' => -1,
            'tax_query' => [
                [
                    'taxonomy' => 'sms_grades',
                    'field' => 'term_id',
                    'terms' => $grade_term_id
                ]
            ]
        ];
        
        return new WP_Query($args);
    }

    /**
     * Get active classes.
     */
    public static function get_active_classes() {
        $args = [
            'post_type' => self::POST_TYPE,
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => 'class_status',
                    'value' => 'active',
                    'compare' => '='
                ]
            ]
        ];
        
        return new WP_Query($args);
    }
}

// Initialize the class
new SMS_Classes_CPT();