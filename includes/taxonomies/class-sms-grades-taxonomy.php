<?php
/**
 * Grades Taxonomy
 *
 * Handles the registration and management of the grades taxonomy
 * for organizing students and classes by grade levels.
 *
 * @package    School_Management_System
 * @subpackage School_Management_System/includes/taxonomies
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Grades Taxonomy Class
 */
class SMS_Grades_Taxonomy extends SMS_Base {

    /**
     * The taxonomy name.
     */
    const TAXONOMY = 'sms_grades';

    /**
     * Initialize the class and set its properties.
     */
    public function __construct() {
        parent::__construct();
        
        // Register the taxonomy
        add_action('sms_register_taxonomies', [$this, 'register_taxonomy']);
        
        // Add custom fields to taxonomy terms
        add_action('init', [$this, 'add_taxonomy_fields']);
        
        // Add custom columns to admin list
        add_filter('manage_edit-' . self::TAXONOMY . '_columns', [$this, 'add_custom_columns']);
        add_filter('manage_' . self::TAXONOMY . '_custom_column', [$this, 'populate_custom_columns'], 10, 3);
        
        // Add default terms on activation
        add_action('init', [$this, 'add_default_terms'], 20);
    }

    /**
     * Register the grades taxonomy.
     */
    public function register_taxonomy() {
        $labels = [
            'name'                       => _x('Grades', 'Taxonomy General Name', 'school-management-system'),
            'singular_name'              => _x('Grade', 'Taxonomy Singular Name', 'school-management-system'),
            'menu_name'                  => __('Grades', 'school-management-system'),
            'all_items'                  => __('All Grades', 'school-management-system'),
            'parent_item'                => __('Parent Grade', 'school-management-system'),
            'parent_item_colon'          => __('Parent Grade:', 'school-management-system'),
            'new_item_name'              => __('New Grade Name', 'school-management-system'),
            'add_new_item'               => __('Add New Grade', 'school-management-system'),
            'edit_item'                  => __('Edit Grade', 'school-management-system'),
            'update_item'                => __('Update Grade', 'school-management-system'),
            'view_item'                  => __('View Grade', 'school-management-system'),
            'separate_items_with_commas' => __('Separate grades with commas', 'school-management-system'),
            'add_or_remove_items'        => __('Add or remove grades', 'school-management-system'),
            'choose_from_most_used'      => __('Choose from the most used', 'school-management-system'),
            'popular_items'              => __('Popular Grades', 'school-management-system'),
            'search_items'               => __('Search Grades', 'school-management-system'),
            'not_found'                  => __('Not Found', 'school-management-system'),
            'no_terms'                   => __('No grades', 'school-management-system'),
            'items_list'                 => __('Grades list', 'school-management-system'),
            'items_list_navigation'      => __('Grades list navigation', 'school-management-system'),
        ];

        $args = [
            'labels'                     => $labels,
            'hierarchical'               => true,
            'public'                     => false,
            'show_ui'                    => true,
            'show_admin_column'          => true,
            'show_in_nav_menus'          => false,
            'show_tagcloud'              => false,
            'show_in_rest'               => true,
            'rest_base'                  => 'grades',
            'rest_controller_class'      => 'WP_REST_Terms_Controller',
            'capabilities'               => [
                'manage_terms' => 'manage_grades',
                'edit_terms'   => 'manage_grades',
                'delete_terms' => 'manage_grades',
                'assign_terms' => 'manage_grades',
            ],
        ];

        register_taxonomy(self::TAXONOMY, ['sms_students', 'sms_classes'], $args);
    }

    /**
     * Add custom fields to taxonomy terms.
     */
    public function add_taxonomy_fields() {
        if (!function_exists('acf_add_local_field_group')) {
            return;
        }

        acf_add_local_field_group([
            'key' => 'group_grade_details',
            'title' => 'Grade Details',
            'fields' => [
                [
                    'key' => 'field_grade_level',
                    'label' => 'Grade Level',
                    'name' => 'grade_level',
                    'type' => 'number',
                    'instructions' => 'Numeric grade level (1-12)',
                    'required' => 1,
                    'min' => 1,
                    'max' => 12,
                ],
                [
                    'key' => 'field_grade_section',
                    'label' => 'Education Section',
                    'name' => 'grade_section',
                    'type' => 'select',
                    'instructions' => 'Which section of education this grade belongs to',
                    'required' => 1,
                    'choices' => [
                        'pre_primary' => 'Pre-Primary (PP1-PP2)',
                        'primary' => 'Primary (Grade 1-8)',
                        'secondary' => 'Secondary (Form 1-4)',
                    ],
                    'default_value' => 'primary',
                ],
                [
                    'key' => 'field_age_range',
                    'label' => 'Typical Age Range',
                    'name' => 'age_range',
                    'type' => 'text',
                    'instructions' => 'Typical age range for students in this grade (e.g., 6-7 years)',
                    'placeholder' => '6-7 years',
                ],
                [
                    'key' => 'field_curriculum_focus',
                    'label' => 'Curriculum Focus',
                    'name' => 'curriculum_focus',
                    'type' => 'textarea',
                    'instructions' => 'Main curriculum focus and learning objectives for this grade',
                    'rows' => 4,
                ],
                [
                    'key' => 'field_core_subjects',
                    'label' => 'Core Subjects',
                    'name' => 'core_subjects',
                    'type' => 'taxonomy',
                    'taxonomy' => 'sms_subjects',
                    'field_type' => 'multi_select',
                    'instructions' => 'Select core subjects for this grade level',
                    'multiple' => 1,
                ],
                [
                    'key' => 'field_elective_subjects',
                    'label' => 'Elective Subjects',
                    'name' => 'elective_subjects',
                    'type' => 'taxonomy',
                    'taxonomy' => 'sms_subjects',
                    'field_type' => 'multi_select',
                    'instructions' => 'Select available elective subjects for this grade',
                    'multiple' => 1,
                ],
                [
                    'key' => 'field_promotion_requirements',
                    'label' => 'Promotion Requirements',
                    'name' => 'promotion_requirements',
                    'type' => 'textarea',
                    'instructions' => 'Requirements for promotion to the next grade',
                    'rows' => 3,
                ],
                [
                    'key' => 'field_assessment_structure',
                    'label' => 'Assessment Structure',
                    'name' => 'assessment_structure',
                    'type' => 'repeater',
                    'instructions' => 'Define assessment structure for this grade',
                    'layout' => 'table',
                    'button_label' => 'Add Assessment',
                    'sub_fields' => [
                        [
                            'key' => 'field_assessment_type',
                            'label' => 'Assessment Type',
                            'name' => 'assessment_type',
                            'type' => 'select',
                            'choices' => [
                                'continuous' => 'Continuous Assessment',
                                'mid_term' => 'Mid-Term Exam',
                                'end_term' => 'End of Term Exam',
                                'project' => 'Project Work',
                                'practical' => 'Practical Assessment',
                            ],
                            'required' => 1,
                        ],
                        [
                            'key' => 'field_assessment_weight',
                            'label' => 'Weight (%)',
                            'name' => 'assessment_weight',
                            'type' => 'number',
                            'min' => 1,
                            'max' => 100,
                            'required' => 1,
                        ],
                        [
                            'key' => 'field_assessment_frequency',
                            'label' => 'Frequency',
                            'name' => 'assessment_frequency',
                            'type' => 'select',
                            'choices' => [
                                'weekly' => 'Weekly',
                                'monthly' => 'Monthly',
                                'termly' => 'Termly',
                                'yearly' => 'Yearly',
                            ],
                        ],
                    ],
                ],
            ],
            'location' => [
                [
                    [
                        'param' => 'taxonomy',
                        'operator' => '==',
                        'value' => self::TAXONOMY,
                    ],
                ],
            ],
            'menu_order' => 0,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
        ]);
    }

    /**
     * Add custom columns to the grades admin list.
     */
    public function add_custom_columns($columns) {
        $new_columns = [];
        
        // Keep the checkbox
        if (isset($columns['cb'])) {
            $new_columns['cb'] = $columns['cb'];
        }
        
        // Keep the name column
        if (isset($columns['name'])) {
            $new_columns['name'] = $columns['name'];
        }
        
        // Add custom columns
        $new_columns['grade_level'] = __('Level', 'school-management-system');
        $new_columns['section'] = __('Section', 'school-management-system');
        $new_columns['age_range'] = __('Age Range', 'school-management-system');
        $new_columns['core_subjects'] = __('Core Subjects', 'school-management-system');
        
        // Keep the description and posts columns
        if (isset($columns['description'])) {
            $new_columns['description'] = $columns['description'];
        }
        if (isset($columns['posts'])) {
            $new_columns['posts'] = $columns['posts'];
        }
        
        return $new_columns;
    }

    /**
     * Populate custom columns with data.
     */
    public function populate_custom_columns($content, $column_name, $term_id) {
        switch ($column_name) {
            case 'grade_level':
                $level = get_field('grade_level', self::TAXONOMY . '_' . $term_id);
                return $level ? esc_html($level) : '—';
                
            case 'section':
                $section = get_field('grade_section', self::TAXONOMY . '_' . $term_id);
                if ($section) {
                    $sections = [
                        'pre_primary' => 'Pre-Primary',
                        'primary' => 'Primary',
                        'secondary' => 'Secondary',
                    ];
                    $section_class = 'section-' . $section;
                    return '<span class="' . esc_attr($section_class) . '">' . 
                           esc_html($sections[$section] ?? ucfirst($section)) . '</span>';
                }
                return '—';
                
            case 'age_range':
                $age_range = get_field('age_range', self::TAXONOMY . '_' . $term_id);
                return $age_range ? esc_html($age_range) : '—';
                
            case 'core_subjects':
                $subjects = get_field('core_subjects', self::TAXONOMY . '_' . $term_id);
                if ($subjects && is_array($subjects)) {
                    $subject_names = [];
                    foreach ($subjects as $subject) {
                        if (is_object($subject)) {
                            $subject_names[] = $subject->name;
                        }
                    }
                    return esc_html(implode(', ', array_slice($subject_names, 0, 3))) . 
                           (count($subject_names) > 3 ? '...' : '');
                }
                return '—';
        }
        
        return $content;
    }

    /**
     * Add default grade terms.
     */
    public function add_default_terms() {
        // Only add default terms if none exist
        $existing_terms = get_terms([
            'taxonomy' => self::TAXONOMY,
            'hide_empty' => false,
            'count' => true
        ]);

        if (!empty($existing_terms) && !is_wp_error($existing_terms)) {
            return;
        }

        $default_grades = [
            // Pre-Primary
            [
                'name' => 'Pre-Primary 1',
                'slug' => 'pp1',
                'description' => 'Pre-Primary One - Foundation learning',
                'meta' => [
                    'grade_level' => 0,
                    'grade_section' => 'pre_primary',
                    'age_range' => '3-4 years',
                    'curriculum_focus' => 'Basic motor skills, social interaction, and foundational learning through play',
                ]
            ],
            [
                'name' => 'Pre-Primary 2',
                'slug' => 'pp2',
                'description' => 'Pre-Primary Two - School readiness preparation',
                'meta' => [
                    'grade_level' => 0,
                    'grade_section' => 'pre_primary',
                    'age_range' => '4-5 years',
                    'curriculum_focus' => 'School readiness, basic literacy and numeracy, social skills development',
                ]
            ],
            // Primary Grades
            [
                'name' => 'Grade 1',
                'slug' => 'grade-1',
                'description' => 'Grade One - Beginning formal education',
                'meta' => [
                    'grade_level' => 1,
                    'grade_section' => 'primary',
                    'age_range' => '6-7 years',
                    'curriculum_focus' => 'Basic literacy, numeracy, and foundational skills in all subjects',
                ]
            ],
            [
                'name' => 'Grade 2',
                'slug' => 'grade-2',
                'description' => 'Grade Two - Building on foundations',
                'meta' => [
                    'grade_level' => 2,
                    'grade_section' => 'primary',
                    'age_range' => '7-8 years',
                    'curriculum_focus' => 'Strengthening literacy and numeracy, introduction to science concepts',
                ]
            ],
            [
                'name' => 'Grade 3',
                'slug' => 'grade-3',
                'description' => 'Grade Three - Expanding knowledge base',
                'meta' => [
                    'grade_level' => 3,
                    'grade_section' => 'primary',
                    'age_range' => '8-9 years',
                    'curriculum_focus' => 'Advanced reading, writing, mathematics, and introduction to social studies',
                ]
            ],
            [
                'name' => 'Grade 4',
                'slug' => 'grade-4',
                'description' => 'Grade Four - Intermediate primary level',
                'meta' => [
                    'grade_level' => 4,
                    'grade_section' => 'primary',
                    'age_range' => '9-10 years',
                    'curriculum_focus' => 'Intermediate skills in all core subjects, critical thinking development',
                ]
            ],
            [
                'name' => 'Grade 5',
                'slug' => 'grade-5',
                'description' => 'Grade Five - Upper primary preparation',
                'meta' => [
                    'grade_level' => 5,
                    'grade_section' => 'primary',
                    'age_range' => '10-11 years',
                    'curriculum_focus' => 'Advanced primary concepts, preparation for upper primary challenges',
                ]
            ],
            [
                'name' => 'Grade 6',
                'slug' => 'grade-6',
                'description' => 'Grade Six - Upper primary level',
                'meta' => [
                    'grade_level' => 6,
                    'grade_section' => 'primary',
                    'age_range' => '11-12 years',
                    'curriculum_focus' => 'Complex problem solving, advanced literacy, and subject specialization',
                ]
            ],
            [
                'name' => 'Grade 7',
                'slug' => 'grade-7',
                'description' => 'Grade Seven - Pre-secondary preparation',
                'meta' => [
                    'grade_level' => 7,
                    'grade_section' => 'primary',
                    'age_range' => '12-13 years',
                    'curriculum_focus' => 'Advanced concepts, secondary school preparation, career guidance introduction',
                ]
            ],
            [
                'name' => 'Grade 8',
                'slug' => 'grade-8',
                'description' => 'Grade Eight - Primary completion',
                'meta' => [
                    'grade_level' => 8,
                    'grade_section' => 'primary',
                    'age_range' => '13-14 years',
                    'curriculum_focus' => 'KCPE preparation, comprehensive review, secondary school readiness',
                ]
            ],
            // Secondary Forms
            [
                'name' => 'Form 1',
                'slug' => 'form-1',
                'description' => 'Form One - Beginning secondary education',
                'meta' => [
                    'grade_level' => 9,
                    'grade_section' => 'secondary',
                    'age_range' => '14-15 years',
                    'curriculum_focus' => 'Introduction to secondary curriculum, subject specialization begins',
                ]
            ],
            [
                'name' => 'Form 2',
                'slug' => 'form-2',
                'description' => 'Form Two - Intermediate secondary level',
                'meta' => [
                    'grade_level' => 10,
                    'grade_section' => 'secondary',
                    'age_range' => '15-16 years',
                    'curriculum_focus' => 'Deepening subject knowledge, career path exploration',
                ]
            ],
            [
                'name' => 'Form 3',
                'slug' => 'form-3',
                'description' => 'Form Three - Advanced secondary level',
                'meta' => [
                    'grade_level' => 11,
                    'grade_section' => 'secondary',
                    'age_range' => '16-17 years',
                    'curriculum_focus' => 'Advanced concepts, KCSE preparation begins, subject mastery',
                ]
            ],
            [
                'name' => 'Form 4',
                'slug' => 'form-4',
                'description' => 'Form Four - Secondary completion',
                'meta' => [
                    'grade_level' => 12,
                    'grade_section' => 'secondary',
                    'age_range' => '17-18 years',
                    'curriculum_focus' => 'KCSE preparation, comprehensive review, post-secondary planning',
                ]
            ],
        ];

        foreach ($default_grades as $grade) {
            $term = wp_insert_term(
                $grade['name'],
                self::TAXONOMY,
                [
                    'slug' => $grade['slug'],
                    'description' => $grade['description']
                ]
            );

            if (!is_wp_error($term)) {
                // Add custom field values
                foreach ($grade['meta'] as $key => $value) {
                    update_field($key, $value, self::TAXONOMY . '_' . $term['term_id']);
                }
            }
        }
    }

    /**
     * Get grades by section.
     */
    public static function get_grades_by_section($section = 'primary') {
        $terms = get_terms([
            'taxonomy' => self::TAXONOMY,
            'hide_empty' => false,
            'meta_query' => [
                [
                    'key' => 'grade_section',
                    'value' => $section,
                    'compare' => '='
                ]
            ],
            'meta_key' => 'grade_level',
            'orderby' => 'meta_value_num',
            'order' => 'ASC'
        ]);

        return is_wp_error($terms) ? [] : $terms;
    }

    /**
     * Get grade by level.
     */
    public static function get_grade_by_level($level) {
        $terms = get_terms([
            'taxonomy' => self::TAXONOMY,
            'hide_empty' => false,
            'meta_query' => [
                [
                    'key' => 'grade_level',
                    'value' => $level,
                    'compare' => '='
                ]
            ]
        ]);

        return (!is_wp_error($terms) && !empty($terms)) ? $terms[0] : null;
    }
}

// Initialize the class
new SMS_Grades_Taxonomy();