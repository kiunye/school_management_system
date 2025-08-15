<?php
/**
 * Subjects Taxonomy
 *
 * Handles the registration and management of the subjects taxonomy
 * for organizing academic subjects across the school.
 *
 * @package    School_Management_System
 * @subpackage School_Management_System/includes/taxonomies
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Subjects Taxonomy Class
 */
class SMS_Subjects_Taxonomy extends SMS_Base {

    /**
     * The taxonomy name.
     */
    const TAXONOMY = 'sms_subjects';

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
     * Register the subjects taxonomy.
     */
    public function register_taxonomy() {
        $labels = [
            'name'                       => _x('Subjects', 'Taxonomy General Name', 'school-management-system'),
            'singular_name'              => _x('Subject', 'Taxonomy Singular Name', 'school-management-system'),
            'menu_name'                  => __('Subjects', 'school-management-system'),
            'all_items'                  => __('All Subjects', 'school-management-system'),
            'parent_item'                => __('Parent Subject', 'school-management-system'),
            'parent_item_colon'          => __('Parent Subject:', 'school-management-system'),
            'new_item_name'              => __('New Subject Name', 'school-management-system'),
            'add_new_item'               => __('Add New Subject', 'school-management-system'),
            'edit_item'                  => __('Edit Subject', 'school-management-system'),
            'update_item'                => __('Update Subject', 'school-management-system'),
            'view_item'                  => __('View Subject', 'school-management-system'),
            'separate_items_with_commas' => __('Separate subjects with commas', 'school-management-system'),
            'add_or_remove_items'        => __('Add or remove subjects', 'school-management-system'),
            'choose_from_most_used'      => __('Choose from the most used', 'school-management-system'),
            'popular_items'              => __('Popular Subjects', 'school-management-system'),
            'search_items'               => __('Search Subjects', 'school-management-system'),
            'not_found'                  => __('Not Found', 'school-management-system'),
            'no_terms'                   => __('No subjects', 'school-management-system'),
            'items_list'                 => __('Subjects list', 'school-management-system'),
            'items_list_navigation'      => __('Subjects list navigation', 'school-management-system'),
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
            'rest_base'                  => 'subjects',
            'rest_controller_class'      => 'WP_REST_Terms_Controller',
            'capabilities'               => [
                'manage_terms' => 'manage_subjects',
                'edit_terms'   => 'manage_subjects',
                'delete_terms' => 'manage_subjects',
                'assign_terms' => 'manage_subjects',
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
            'key' => 'group_subject_details',
            'title' => 'Subject Details',
            'fields' => [
                [
                    'key' => 'field_subject_code',
                    'label' => 'Subject Code',
                    'name' => 'subject_code',
                    'type' => 'text',
                    'instructions' => 'Short code for the subject (e.g., MATH, ENG, SCI)',
                    'required' => 1,
                    'maxlength' => 10,
                ],
                [
                    'key' => 'field_subject_description',
                    'label' => 'Description',
                    'name' => 'subject_description',
                    'type' => 'textarea',
                    'instructions' => 'Brief description of the subject',
                    'rows' => 3,
                ],
                [
                    'key' => 'field_subject_color',
                    'label' => 'Subject Color',
                    'name' => 'subject_color',
                    'type' => 'color_picker',
                    'instructions' => 'Color for timetables and visual identification',
                    'default_value' => '#0073aa',
                ],
                [
                    'key' => 'field_periods_per_week',
                    'label' => 'Default Periods per Week',
                    'name' => 'periods_per_week',
                    'type' => 'number',
                    'instructions' => 'Default number of periods per week for this subject',
                    'default_value' => 3,
                    'min' => 1,
                    'max' => 10,
                ],
                [
                    'key' => 'field_subject_category',
                    'label' => 'Subject Category',
                    'name' => 'subject_category',
                    'type' => 'select',
                    'instructions' => 'Category this subject belongs to',
                    'choices' => [
                        'core' => 'Core Subject',
                        'elective' => 'Elective',
                        'extracurricular' => 'Extracurricular',
                        'vocational' => 'Vocational',
                    ],
                    'default_value' => 'core',
                ],
                [
                    'key' => 'field_assessment_methods',
                    'label' => 'Assessment Methods',
                    'name' => 'assessment_methods',
                    'type' => 'checkbox',
                    'instructions' => 'Select applicable assessment methods',
                    'choices' => [
                        'written_exam' => 'Written Examination',
                        'practical' => 'Practical Assessment',
                        'project' => 'Project Work',
                        'continuous' => 'Continuous Assessment',
                        'oral' => 'Oral Assessment',
                    ],
                    'default_value' => ['written_exam', 'continuous'],
                ],
                [
                    'key' => 'field_required_resources',
                    'label' => 'Required Resources',
                    'name' => 'required_resources',
                    'type' => 'textarea',
                    'instructions' => 'List of resources needed for this subject (books, equipment, etc.)',
                    'rows' => 4,
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
     * Add custom columns to the subjects admin list.
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
        $new_columns['subject_code'] = __('Code', 'school-management-system');
        $new_columns['category'] = __('Category', 'school-management-system');
        $new_columns['periods'] = __('Periods/Week', 'school-management-system');
        $new_columns['color'] = __('Color', 'school-management-system');
        
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
            case 'subject_code':
                $code = get_field('subject_code', self::TAXONOMY . '_' . $term_id);
                return $code ? esc_html($code) : '—';
                
            case 'category':
                $category = get_field('subject_category', self::TAXONOMY . '_' . $term_id);
                if ($category) {
                    $categories = [
                        'core' => 'Core Subject',
                        'elective' => 'Elective',
                        'extracurricular' => 'Extracurricular',
                        'vocational' => 'Vocational',
                    ];
                    $category_class = 'category-' . $category;
                    return '<span class="' . esc_attr($category_class) . '">' . 
                           esc_html($categories[$category] ?? ucfirst($category)) . '</span>';
                }
                return '—';
                
            case 'periods':
                $periods = get_field('periods_per_week', self::TAXONOMY . '_' . $term_id);
                return $periods ? esc_html($periods) : '—';
                
            case 'color':
                $color = get_field('subject_color', self::TAXONOMY . '_' . $term_id);
                if ($color) {
                    return '<span class="color-indicator" style="background-color: ' . 
                           esc_attr($color) . '; width: 20px; height: 20px; display: inline-block; border-radius: 3px; border: 1px solid #ddd;"></span>';
                }
                return '—';
        }
        
        return $content;
    }

    /**
     * Add default subject terms.
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

        $default_subjects = [
            // Core Subjects
            [
                'name' => 'Mathematics',
                'slug' => 'mathematics',
                'description' => 'Mathematical concepts, problem solving, and numerical skills',
                'meta' => [
                    'subject_code' => 'MATH',
                    'subject_category' => 'core',
                    'periods_per_week' => 5,
                    'subject_color' => '#2196F3',
                    'assessment_methods' => ['written_exam', 'continuous'],
                ]
            ],
            [
                'name' => 'English',
                'slug' => 'english',
                'description' => 'Language arts, literature, reading, and writing skills',
                'meta' => [
                    'subject_code' => 'ENG',
                    'subject_category' => 'core',
                    'periods_per_week' => 5,
                    'subject_color' => '#4CAF50',
                    'assessment_methods' => ['written_exam', 'continuous', 'oral'],
                ]
            ],
            [
                'name' => 'Science',
                'slug' => 'science',
                'description' => 'General science covering biology, chemistry, and physics',
                'meta' => [
                    'subject_code' => 'SCI',
                    'subject_category' => 'core',
                    'periods_per_week' => 4,
                    'subject_color' => '#FF9800',
                    'assessment_methods' => ['written_exam', 'practical', 'continuous'],
                ]
            ],
            [
                'name' => 'Social Studies',
                'slug' => 'social-studies',
                'description' => 'History, geography, civics, and social sciences',
                'meta' => [
                    'subject_code' => 'SST',
                    'subject_category' => 'core',
                    'periods_per_week' => 3,
                    'subject_color' => '#9C27B0',
                    'assessment_methods' => ['written_exam', 'project', 'continuous'],
                ]
            ],
            [
                'name' => 'Kiswahili',
                'slug' => 'kiswahili',
                'description' => 'Kenyan national language studies',
                'meta' => [
                    'subject_code' => 'KISW',
                    'subject_category' => 'core',
                    'periods_per_week' => 4,
                    'subject_color' => '#E91E63',
                    'assessment_methods' => ['written_exam', 'continuous', 'oral'],
                ]
            ],
            // Elective Subjects
            [
                'name' => 'Art & Craft',
                'slug' => 'art-craft',
                'description' => 'Creative arts, drawing, painting, and crafts',
                'meta' => [
                    'subject_code' => 'ART',
                    'subject_category' => 'elective',
                    'periods_per_week' => 2,
                    'subject_color' => '#FF5722',
                    'assessment_methods' => ['practical', 'project', 'continuous'],
                ]
            ],
            [
                'name' => 'Music',
                'slug' => 'music',
                'description' => 'Music theory, singing, and instrumental skills',
                'meta' => [
                    'subject_code' => 'MUS',
                    'subject_category' => 'elective',
                    'periods_per_week' => 2,
                    'subject_color' => '#3F51B5',
                    'assessment_methods' => ['practical', 'continuous', 'oral'],
                ]
            ],
            [
                'name' => 'Physical Education',
                'slug' => 'physical-education',
                'description' => 'Sports, fitness, and physical development',
                'meta' => [
                    'subject_code' => 'PE',
                    'subject_category' => 'elective',
                    'periods_per_week' => 3,
                    'subject_color' => '#607D8B',
                    'assessment_methods' => ['practical', 'continuous'],
                ]
            ],
            [
                'name' => 'Computer Studies',
                'slug' => 'computer-studies',
                'description' => 'Basic computer skills and digital literacy',
                'meta' => [
                    'subject_code' => 'COMP',
                    'subject_category' => 'elective',
                    'periods_per_week' => 2,
                    'subject_color' => '#795548',
                    'assessment_methods' => ['practical', 'project', 'continuous'],
                ]
            ],
            [
                'name' => 'Religious Education',
                'slug' => 'religious-education',
                'description' => 'Religious and moral education',
                'meta' => [
                    'subject_code' => 'RE',
                    'subject_category' => 'core',
                    'periods_per_week' => 2,
                    'subject_color' => '#009688',
                    'assessment_methods' => ['written_exam', 'continuous', 'project'],
                ]
            ],
        ];

        foreach ($default_subjects as $subject) {
            $term = wp_insert_term(
                $subject['name'],
                self::TAXONOMY,
                [
                    'slug' => $subject['slug'],
                    'description' => $subject['description']
                ]
            );

            if (!is_wp_error($term)) {
                // Add custom field values
                foreach ($subject['meta'] as $key => $value) {
                    update_field($key, $value, self::TAXONOMY . '_' . $term['term_id']);
                }
            }
        }
    }

    /**
     * Get subjects by category.
     */
    public static function get_subjects_by_category($category = 'core') {
        $terms = get_terms([
            'taxonomy' => self::TAXONOMY,
            'hide_empty' => false,
            'meta_query' => [
                [
                    'key' => 'subject_category',
                    'value' => $category,
                    'compare' => '='
                ]
            ]
        ]);

        return is_wp_error($terms) ? [] : $terms;
    }

    /**
     * Get subject by code.
     */
    public static function get_subject_by_code($code) {
        $terms = get_terms([
            'taxonomy' => self::TAXONOMY,
            'hide_empty' => false,
            'meta_query' => [
                [
                    'key' => 'subject_code',
                    'value' => $code,
                    'compare' => '='
                ]
            ]
        ]);

        return (!is_wp_error($terms) && !empty($terms)) ? $terms[0] : null;
    }
}

// Initialize the class
new SMS_Subjects_Taxonomy();