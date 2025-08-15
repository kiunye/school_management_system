<?php
/**
 * Students Custom Post Type
 *
 * Handles the registration and management of the students custom post type
 * with ACF field groups for comprehensive student data management.
 *
 * @package    School_Management_System
 * @subpackage School_Management_System/includes/post-types
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Students Custom Post Type Class
 */
class SMS_Students_CPT extends SMS_Base {

    /**
     * The post type name.
     */
    const POST_TYPE = 'sms_students';

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
        
        // Handle admission number generation
        add_action('save_post_' . self::POST_TYPE, [$this, 'generate_admission_number'], 10, 3);
        
        // Add meta boxes
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        
        // Save meta data
        add_action('save_post', [$this, 'save_meta_data']);
    }

    /**
     * Register the students custom post type.
     */
    public function register_post_type() {
        $labels = [
            'name'                  => _x('Students', 'Post Type General Name', 'school-management-system'),
            'singular_name'         => _x('Student', 'Post Type Singular Name', 'school-management-system'),
            'menu_name'             => __('Students', 'school-management-system'),
            'name_admin_bar'        => __('Student', 'school-management-system'),
            'archives'              => __('Student Archives', 'school-management-system'),
            'attributes'            => __('Student Attributes', 'school-management-system'),
            'parent_item_colon'     => __('Parent Student:', 'school-management-system'),
            'all_items'             => __('All Students', 'school-management-system'),
            'add_new_item'          => __('Add New Student', 'school-management-system'),
            'add_new'               => __('Add New', 'school-management-system'),
            'new_item'              => __('New Student', 'school-management-system'),
            'edit_item'             => __('Edit Student', 'school-management-system'),
            'update_item'           => __('Update Student', 'school-management-system'),
            'view_item'             => __('View Student', 'school-management-system'),
            'view_items'            => __('View Students', 'school-management-system'),
            'search_items'          => __('Search Student', 'school-management-system'),
            'not_found'             => __('Not found', 'school-management-system'),
            'not_found_in_trash'    => __('Not found in Trash', 'school-management-system'),
            'featured_image'        => __('Profile Photo', 'school-management-system'),
            'set_featured_image'    => __('Set profile photo', 'school-management-system'),
            'remove_featured_image' => __('Remove profile photo', 'school-management-system'),
            'use_featured_image'    => __('Use as profile photo', 'school-management-system'),
            'insert_into_item'      => __('Insert into student', 'school-management-system'),
            'uploaded_to_this_item' => __('Uploaded to this student', 'school-management-system'),
            'items_list'            => __('Students list', 'school-management-system'),
            'items_list_navigation' => __('Students list navigation', 'school-management-system'),
            'filter_items_list'     => __('Filter students list', 'school-management-system'),
        ];

        $args = [
            'label'                 => __('Student', 'school-management-system'),
            'description'           => __('Student records and information', 'school-management-system'),
            'labels'                => $labels,
            'supports'              => ['title', 'editor', 'thumbnail', 'revisions'],
            'taxonomies'            => ['sms_grades', 'sms_academic_years'],
            'hierarchical'          => false,
            'public'                => false,
            'show_ui'               => true,
            'show_in_menu'          => 'school-management',
            'menu_position'         => 5,
            'menu_icon'             => 'dashicons-groups',
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
            'rest_base'             => 'students',
            'rest_controller_class' => 'WP_REST_Posts_Controller',
        ];

        register_post_type(self::POST_TYPE, $args);
    }

    /**
     * Register ACF field groups for students.
     */
    public function register_acf_fields() {
        if (!function_exists('acf_add_local_field_group')) {
            return;
        }

        // Basic Information Field Group
        acf_add_local_field_group([
            'key' => 'group_student_basic_info',
            'title' => 'Basic Information',
            'fields' => [
                [
                    'key' => 'field_admission_number',
                    'label' => 'Admission Number',
                    'name' => 'admission_number',
                    'type' => 'text',
                    'instructions' => 'Unique admission number (auto-generated if empty)',
                    'required' => 0,
                    'readonly' => 1,
                    'wrapper' => [
                        'width' => '50',
                    ],
                ],
                [
                    'key' => 'field_full_name',
                    'label' => 'Full Name',
                    'name' => 'full_name',
                    'type' => 'text',
                    'instructions' => 'Student\'s complete name',
                    'required' => 1,
                    'wrapper' => [
                        'width' => '50',
                    ],
                ],
                [
                    'key' => 'field_date_of_birth',
                    'label' => 'Date of Birth',
                    'name' => 'date_of_birth',
                    'type' => 'date_picker',
                    'instructions' => 'Student\'s date of birth',
                    'required' => 1,
                    'display_format' => 'd/m/Y',
                    'return_format' => 'Y-m-d',
                    'wrapper' => [
                        'width' => '33',
                    ],
                ],
                [
                    'key' => 'field_gender',
                    'label' => 'Gender',
                    'name' => 'gender',
                    'type' => 'select',
                    'instructions' => 'Student\'s gender',
                    'required' => 1,
                    'choices' => [
                        'male' => 'Male',
                        'female' => 'Female',
                        'other' => 'Other',
                    ],
                    'wrapper' => [
                        'width' => '33',
                    ],
                ],
                [
                    'key' => 'field_admission_date',
                    'label' => 'Admission Date',
                    'name' => 'admission_date',
                    'type' => 'date_picker',
                    'instructions' => 'Date when student was admitted',
                    'required' => 1,
                    'display_format' => 'd/m/Y',
                    'return_format' => 'Y-m-d',
                    'default_value' => date('Y-m-d'),
                    'wrapper' => [
                        'width' => '34',
                    ],
                ],
                [
                    'key' => 'field_student_status',
                    'label' => 'Status',
                    'name' => 'student_status',
                    'type' => 'select',
                    'instructions' => 'Current status of the student',
                    'required' => 1,
                    'choices' => [
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'graduated' => 'Graduated',
                        'transferred' => 'Transferred',
                        'suspended' => 'Suspended',
                    ],
                    'default_value' => 'active',
                    'wrapper' => [
                        'width' => '50',
                    ],
                ],
                [
                    'key' => 'field_national_id',
                    'label' => 'National ID / Birth Certificate Number',
                    'name' => 'national_id',
                    'type' => 'text',
                    'instructions' => 'National ID or Birth Certificate number',
                    'wrapper' => [
                        'width' => '50',
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

        // Class Assignment Field Group
        acf_add_local_field_group([
            'key' => 'group_student_class_assignment',
            'title' => 'Class Assignment',
            'fields' => [
                [
                    'key' => 'field_assigned_class',
                    'label' => 'Assigned Class',
                    'name' => 'assigned_class',
                    'type' => 'post_object',
                    'instructions' => 'Select the class this student is assigned to',
                    'post_type' => ['sms_classes'],
                    'return_format' => 'id',
                    'ui' => 1,
                    'wrapper' => [
                        'width' => '50',
                    ],
                ],
                [
                    'key' => 'field_enrollment_date',
                    'label' => 'Enrollment Date',
                    'name' => 'enrollment_date',
                    'type' => 'date_picker',
                    'instructions' => 'Date when student was enrolled in current class',
                    'display_format' => 'd/m/Y',
                    'return_format' => 'Y-m-d',
                    'wrapper' => [
                        'width' => '50',
                    ],
                ],
                [
                    'key' => 'field_previous_classes',
                    'label' => 'Previous Classes',
                    'name' => 'previous_classes',
                    'type' => 'repeater',
                    'instructions' => 'Record of previous class assignments',
                    'layout' => 'table',
                    'button_label' => 'Add Previous Class',
                    'sub_fields' => [
                        [
                            'key' => 'field_prev_class',
                            'label' => 'Class',
                            'name' => 'prev_class',
                            'type' => 'post_object',
                            'post_type' => ['sms_classes'],
                            'return_format' => 'id',
                            'ui' => 1,
                        ],
                        [
                            'key' => 'field_prev_start_date',
                            'label' => 'Start Date',
                            'name' => 'prev_start_date',
                            'type' => 'date_picker',
                            'display_format' => 'd/m/Y',
                            'return_format' => 'Y-m-d',
                        ],
                        [
                            'key' => 'field_prev_end_date',
                            'label' => 'End Date',
                            'name' => 'prev_end_date',
                            'type' => 'date_picker',
                            'display_format' => 'd/m/Y',
                            'return_format' => 'Y-m-d',
                        ],
                        [
                            'key' => 'field_prev_reason',
                            'label' => 'Reason for Change',
                            'name' => 'prev_reason',
                            'type' => 'text',
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
            'menu_order' => 0,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
        ]);

        // Parent/Guardian Information Field Group
        acf_add_local_field_group([
            'key' => 'group_student_parent_info',
            'title' => 'Parent/Guardian Information',
            'fields' => [
                [
                    'key' => 'field_parent_details',
                    'label' => 'Parent/Guardian Details',
                    'name' => 'parent_details',
                    'type' => 'repeater',
                    'instructions' => 'Add parent or guardian information',
                    'required' => 1,
                    'min' => 1,
                    'max' => 2,
                    'layout' => 'block',
                    'button_label' => 'Add Parent/Guardian',
                    'sub_fields' => [
                        [
                            'key' => 'field_parent_type',
                            'label' => 'Relationship',
                            'name' => 'parent_type',
                            'type' => 'select',
                            'required' => 1,
                            'choices' => [
                                'father' => 'Father',
                                'mother' => 'Mother',
                                'guardian' => 'Guardian',
                                'other' => 'Other',
                            ],
                            'wrapper' => [
                                'width' => '25',
                            ],
                        ],
                        [
                            'key' => 'field_parent_name',
                            'label' => 'Full Name',
                            'name' => 'parent_name',
                            'type' => 'text',
                            'required' => 1,
                            'wrapper' => [
                                'width' => '75',
                            ],
                        ],
                        [
                            'key' => 'field_parent_phone',
                            'label' => 'Phone Number',
                            'name' => 'parent_phone',
                            'type' => 'text',
                            'required' => 1,
                            'wrapper' => [
                                'width' => '33',
                            ],
                        ],
                        [
                            'key' => 'field_parent_email',
                            'label' => 'Email Address',
                            'name' => 'parent_email',
                            'type' => 'email',
                            'wrapper' => [
                                'width' => '33',
                            ],
                        ],
                        [
                            'key' => 'field_parent_occupation',
                            'label' => 'Occupation',
                            'name' => 'parent_occupation',
                            'type' => 'text',
                            'wrapper' => [
                                'width' => '34',
                            ],
                        ],
                        [
                            'key' => 'field_parent_address',
                            'label' => 'Address',
                            'name' => 'parent_address',
                            'type' => 'textarea',
                            'rows' => 3,
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

        // Medical Information Field Group
        acf_add_local_field_group([
            'key' => 'group_student_medical_info',
            'title' => 'Medical Information',
            'fields' => [
                [
                    'key' => 'field_blood_group',
                    'label' => 'Blood Group',
                    'name' => 'blood_group',
                    'type' => 'select',
                    'instructions' => 'Student\'s blood group',
                    'choices' => [
                        'A+' => 'A+',
                        'A-' => 'A-',
                        'B+' => 'B+',
                        'B-' => 'B-',
                        'AB+' => 'AB+',
                        'AB-' => 'AB-',
                        'O+' => 'O+',
                        'O-' => 'O-',
                        'unknown' => 'Unknown',
                    ],
                    'wrapper' => [
                        'width' => '50',
                    ],
                ],
                [
                    'key' => 'field_allergies',
                    'label' => 'Allergies',
                    'name' => 'allergies',
                    'type' => 'textarea',
                    'instructions' => 'List any known allergies',
                    'rows' => 3,
                    'wrapper' => [
                        'width' => '50',
                    ],
                ],
                [
                    'key' => 'field_medical_conditions',
                    'label' => 'Medical Conditions',
                    'name' => 'medical_conditions',
                    'type' => 'textarea',
                    'instructions' => 'List any medical conditions or special needs',
                    'rows' => 3,
                ],
                [
                    'key' => 'field_medications',
                    'label' => 'Current Medications',
                    'name' => 'medications',
                    'type' => 'textarea',
                    'instructions' => 'List any medications the student is currently taking',
                    'rows' => 3,
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

        // Emergency Contacts Field Group
        acf_add_local_field_group([
            'key' => 'group_student_emergency_contacts',
            'title' => 'Emergency Contacts',
            'fields' => [
                [
                    'key' => 'field_emergency_contacts',
                    'label' => 'Emergency Contacts',
                    'name' => 'emergency_contacts',
                    'type' => 'repeater',
                    'instructions' => 'Add emergency contact information',
                    'required' => 1,
                    'min' => 1,
                    'max' => 3,
                    'layout' => 'block',
                    'button_label' => 'Add Emergency Contact',
                    'sub_fields' => [
                        [
                            'key' => 'field_emergency_name',
                            'label' => 'Full Name',
                            'name' => 'emergency_name',
                            'type' => 'text',
                            'required' => 1,
                            'wrapper' => [
                                'width' => '50',
                            ],
                        ],
                        [
                            'key' => 'field_emergency_relationship',
                            'label' => 'Relationship',
                            'name' => 'emergency_relationship',
                            'type' => 'text',
                            'required' => 1,
                            'wrapper' => [
                                'width' => '50',
                            ],
                        ],
                        [
                            'key' => 'field_emergency_phone',
                            'label' => 'Phone Number',
                            'name' => 'emergency_phone',
                            'type' => 'text',
                            'required' => 1,
                            'wrapper' => [
                                'width' => '50',
                            ],
                        ],
                        [
                            'key' => 'field_emergency_email',
                            'label' => 'Email Address',
                            'name' => 'emergency_email',
                            'type' => 'email',
                            'wrapper' => [
                                'width' => '50',
                            ],
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
            'menu_order' => 3,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
        ]);
    }

    /**
     * Add custom columns to the students admin list.
     */
    public function add_custom_columns($columns) {
        $new_columns = [];
        
        // Keep the checkbox
        if (isset($columns['cb'])) {
            $new_columns['cb'] = $columns['cb'];
        }
        
        // Add custom columns
        $new_columns['admission_number'] = __('Admission Number', 'school-management-system');
        $new_columns['full_name'] = __('Full Name', 'school-management-system');
        $new_columns['grade'] = __('Grade', 'school-management-system');
        $new_columns['status'] = __('Status', 'school-management-system');
        $new_columns['admission_date'] = __('Admission Date', 'school-management-system');
        
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
            case 'admission_number':
                $admission_number = get_field('admission_number', $post_id);
                echo $admission_number ? esc_html($admission_number) : '—';
                break;
                
            case 'full_name':
                $full_name = get_field('full_name', $post_id);
                echo $full_name ? esc_html($full_name) : '—';
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
                
            case 'status':
                $status = get_field('student_status', $post_id);
                if ($status) {
                    $status_class = 'status-' . $status;
                    echo '<span class="' . esc_attr($status_class) . '">' . esc_html(ucfirst($status)) . '</span>';
                } else {
                    echo '—';
                }
                break;
                
            case 'admission_date':
                $admission_date = get_field('admission_date', $post_id);
                if ($admission_date) {
                    echo esc_html(date('d/m/Y', strtotime($admission_date)));
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
        $columns['admission_number'] = 'admission_number';
        $columns['full_name'] = 'full_name';
        $columns['admission_date'] = 'admission_date';
        $columns['status'] = 'student_status';
        
        return $columns;
    }

    /**
     * Generate admission number automatically.
     */
    public function generate_admission_number($post_id, $post, $update) {
        // Skip if this is an autosave or revision
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }

        // Skip if admission number already exists
        $existing_admission_number = get_field('admission_number', $post_id);
        if (!empty($existing_admission_number)) {
            return;
        }

        // Generate new admission number
        $admission_number = $this->generate_unique_admission_number();
        
        // Update the field
        update_field('admission_number', $admission_number, $post_id);
        
        // Trigger parent relationship update
        do_action('sms_student_admission_number_generated', $post_id, $admission_number);
    }

    /**
     * Generate a unique admission number.
     */
    private function generate_unique_admission_number() {
        $year = date('Y');
        $prefix = 'STU' . $year;
        
        // Get the highest existing number for this year
        $args = [
            'post_type' => self::POST_TYPE,
            'posts_per_page' => 1,
            'meta_query' => [
                [
                    'key' => 'admission_number',
                    'value' => $prefix,
                    'compare' => 'LIKE'
                ]
            ],
            'meta_key' => 'admission_number',
            'orderby' => 'meta_value',
            'order' => 'DESC'
        ];
        
        $query = new WP_Query($args);
        
        if ($query->have_posts()) {
            $last_admission = get_field('admission_number', $query->posts[0]->ID);
            $last_number = intval(substr($last_admission, -4));
            $new_number = $last_number + 1;
        } else {
            $new_number = 1;
        }
        
        wp_reset_postdata();
        
        return $prefix . str_pad($new_number, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Add meta boxes.
     */
    public function add_meta_boxes() {
        add_meta_box(
            'student-quick-info',
            __('Quick Information', 'school-management-system'),
            [$this, 'render_quick_info_meta_box'],
            self::POST_TYPE,
            'side',
            'high'
        );
    }

    /**
     * Render quick info meta box.
     */
    public function render_quick_info_meta_box($post) {
        $admission_number = get_field('admission_number', $post->ID);
        $full_name = get_field('full_name', $post->ID);
        $status = get_field('student_status', $post->ID);
        
        echo '<p><strong>' . __('Admission Number:', 'school-management-system') . '</strong><br>';
        echo $admission_number ? esc_html($admission_number) : __('Will be generated on save', 'school-management-system');
        echo '</p>';
        
        if ($full_name) {
            echo '<p><strong>' . __('Full Name:', 'school-management-system') . '</strong><br>';
            echo esc_html($full_name) . '</p>';
        }
        
        if ($status) {
            echo '<p><strong>' . __('Status:', 'school-management-system') . '</strong><br>';
            echo '<span class="status-' . esc_attr($status) . '">' . esc_html(ucfirst($status)) . '</span></p>';
        }
    }

    /**
     * Save meta data.
     */
    public function save_meta_data($post_id) {
        // This method can be used for additional meta data processing
        // ACF handles most of the field saving automatically
        
        // Verify nonce and permissions would go here if needed
        // For now, ACF handles the security
    }

    /**
     * Get student by admission number.
     */
    public static function get_student_by_admission_number($admission_number) {
        $args = [
            'post_type' => self::POST_TYPE,
            'posts_per_page' => 1,
            'meta_query' => [
                [
                    'key' => 'admission_number',
                    'value' => $admission_number,
                    'compare' => '='
                ]
            ]
        ];
        
        $query = new WP_Query($args);
        
        if ($query->have_posts()) {
            return $query->posts[0];
        }
        
        return null;
    }

    /**
     * Get students by status.
     */
    public static function get_students_by_status($status = 'active') {
        $args = [
            'post_type' => self::POST_TYPE,
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => 'student_status',
                    'value' => $status,
                    'compare' => '='
                ]
            ]
        ];
        
        return new WP_Query($args);
    }
}

// Initialize the class
new SMS_Students_CPT();