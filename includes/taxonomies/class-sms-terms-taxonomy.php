<?php
/**
 * Terms Taxonomy
 *
 * Handles the registration and management of the terms taxonomy
 * for organizing academic activities by school terms.
 *
 * @package    School_Management_System
 * @subpackage School_Management_System/includes/taxonomies
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Terms Taxonomy Class
 */
class SMS_Terms_Taxonomy extends SMS_Base {

    /**
     * The taxonomy name.
     */
    const TAXONOMY = 'sms_terms';

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
     * Register the terms taxonomy.
     */
    public function register_taxonomy() {
        $labels = [
            'name'                       => _x('Terms', 'Taxonomy General Name', 'school-management-system'),
            'singular_name'              => _x('Term', 'Taxonomy Singular Name', 'school-management-system'),
            'menu_name'                  => __('Terms', 'school-management-system'),
            'all_items'                  => __('All Terms', 'school-management-system'),
            'parent_item'                => __('Parent Term', 'school-management-system'),
            'parent_item_colon'          => __('Parent Term:', 'school-management-system'),
            'new_item_name'              => __('New Term Name', 'school-management-system'),
            'add_new_item'               => __('Add New Term', 'school-management-system'),
            'edit_item'                  => __('Edit Term', 'school-management-system'),
            'update_item'                => __('Update Term', 'school-management-system'),
            'view_item'                  => __('View Term', 'school-management-system'),
            'separate_items_with_commas' => __('Separate terms with commas', 'school-management-system'),
            'add_or_remove_items'        => __('Add or remove terms', 'school-management-system'),
            'choose_from_most_used'      => __('Choose from the most used', 'school-management-system'),
            'popular_items'              => __('Popular Terms', 'school-management-system'),
            'search_items'               => __('Search Terms', 'school-management-system'),
            'not_found'                  => __('Not Found', 'school-management-system'),
            'no_terms'                   => __('No terms', 'school-management-system'),
            'items_list'                 => __('Terms list', 'school-management-system'),
            'items_list_navigation'      => __('Terms list navigation', 'school-management-system'),
        ];

        $args = [
            'labels'                     => $labels,
            'hierarchical'               => false,
            'public'                     => false,
            'show_ui'                    => true,
            'show_admin_column'          => true,
            'show_in_nav_menus'          => false,
            'show_tagcloud'              => false,
            'show_in_rest'               => true,
            'rest_base'                  => 'terms',
            'rest_controller_class'      => 'WP_REST_Terms_Controller',
            'capabilities'               => [
                'manage_terms' => 'manage_terms',
                'edit_terms'   => 'manage_terms',
                'delete_terms' => 'manage_terms',
                'assign_terms' => 'manage_terms',
            ],
        ];

        register_taxonomy(self::TAXONOMY, ['sms_students', 'sms_classes', 'sms_fees', 'sms_invoices', 'sms_attendance'], $args);
    }

    /**
     * Add custom fields to taxonomy terms.
     */
    public function add_taxonomy_fields() {
        if (!function_exists('acf_add_local_field_group')) {
            return;
        }

        acf_add_local_field_group([
            'key' => 'group_term_details',
            'title' => 'Term Details',
            'fields' => [
                [
                    'key' => 'field_academic_year',
                    'label' => 'Academic Year',
                    'name' => 'academic_year',
                    'type' => 'taxonomy',
                    'taxonomy' => 'sms_academic_years',
                    'field_type' => 'select',
                    'instructions' => 'Select the academic year this term belongs to',
                    'required' => 1,
                    'wrapper' => [
                        'width' => '50',
                    ],
                ],
                [
                    'key' => 'field_term_number',
                    'label' => 'Term Number',
                    'name' => 'term_number',
                    'type' => 'number',
                    'instructions' => 'Term number within the academic year (1, 2, 3, etc.)',
                    'required' => 1,
                    'min' => 1,
                    'max' => 4,
                    'wrapper' => [
                        'width' => '50',
                    ],
                ],
                [
                    'key' => 'field_start_date',
                    'label' => 'Start Date',
                    'name' => 'start_date',
                    'type' => 'date_picker',
                    'instructions' => 'Term start date',
                    'required' => 1,
                    'display_format' => 'd/m/Y',
                    'return_format' => 'Y-m-d',
                    'wrapper' => [
                        'width' => '33',
                    ],
                ],
                [
                    'key' => 'field_end_date',
                    'label' => 'End Date',
                    'name' => 'end_date',
                    'type' => 'date_picker',
                    'instructions' => 'Term end date',
                    'required' => 1,
                    'display_format' => 'd/m/Y',
                    'return_format' => 'Y-m-d',
                    'wrapper' => [
                        'width' => '33',
                    ],
                ],
                [
                    'key' => 'field_total_weeks',
                    'label' => 'Total Weeks',
                    'name' => 'total_weeks',
                    'type' => 'number',
                    'instructions' => 'Total number of weeks in this term',
                    'required' => 1,
                    'min' => 1,
                    'max' => 20,
                    'default_value' => 12,
                    'wrapper' => [
                        'width' => '34',
                    ],
                ],
                [
                    'key' => 'field_is_current',
                    'label' => 'Current Term',
                    'name' => 'is_current',
                    'type' => 'true_false',
                    'instructions' => 'Mark this as the current active term',
                    'default_value' => 0,
                    'ui' => 1,
                    'wrapper' => [
                        'width' => '50',
                    ],
                ],
                [
                    'key' => 'field_term_status',
                    'label' => 'Status',
                    'name' => 'term_status',
                    'type' => 'select',
                    'instructions' => 'Current status of this term',
                    'required' => 1,
                    'choices' => [
                        'upcoming' => 'Upcoming',
                        'active' => 'Active',
                        'completed' => 'Completed',
                        'archived' => 'Archived',
                    ],
                    'default_value' => 'upcoming',
                    'wrapper' => [
                        'width' => '50',
                    ],
                ],
                [
                    'key' => 'field_exam_periods',
                    'label' => 'Examination Periods',
                    'name' => 'exam_periods',
                    'type' => 'repeater',
                    'instructions' => 'Define examination periods for this term',
                    'layout' => 'table',
                    'button_label' => 'Add Exam Period',
                    'sub_fields' => [
                        [
                            'key' => 'field_exam_name',
                            'label' => 'Exam Name',
                            'name' => 'exam_name',
                            'type' => 'text',
                            'required' => 1,
                            'placeholder' => 'Mid-Term Exam',
                        ],
                        [
                            'key' => 'field_exam_start',
                            'label' => 'Start Date',
                            'name' => 'exam_start',
                            'type' => 'date_picker',
                            'required' => 1,
                            'display_format' => 'd/m/Y',
                            'return_format' => 'Y-m-d',
                        ],
                        [
                            'key' => 'field_exam_end',
                            'label' => 'End Date',
                            'name' => 'exam_end',
                            'type' => 'date_picker',
                            'required' => 1,
                            'display_format' => 'd/m/Y',
                            'return_format' => 'Y-m-d',
                        ],
                        [
                            'key' => 'field_exam_type',
                            'label' => 'Exam Type',
                            'name' => 'exam_type',
                            'type' => 'select',
                            'choices' => [
                                'continuous' => 'Continuous Assessment',
                                'mid_term' => 'Mid-Term Examination',
                                'end_term' => 'End of Term Examination',
                                'mock' => 'Mock Examination',
                                'national' => 'National Examination',
                            ],
                            'default_value' => 'mid_term',
                        ],
                    ],
                ],
                [
                    'key' => 'field_holidays',
                    'label' => 'Holidays within Term',
                    'name' => 'holidays',
                    'type' => 'repeater',
                    'instructions' => 'Public holidays or breaks within this term',
                    'layout' => 'table',
                    'button_label' => 'Add Holiday',
                    'sub_fields' => [
                        [
                            'key' => 'field_holiday_name',
                            'label' => 'Holiday Name',
                            'name' => 'holiday_name',
                            'type' => 'text',
                            'required' => 1,
                        ],
                        [
                            'key' => 'field_holiday_date',
                            'label' => 'Date',
                            'name' => 'holiday_date',
                            'type' => 'date_picker',
                            'required' => 1,
                            'display_format' => 'd/m/Y',
                            'return_format' => 'Y-m-d',
                        ],
                        [
                            'key' => 'field_holiday_type',
                            'label' => 'Type',
                            'name' => 'holiday_type',
                            'type' => 'select',
                            'choices' => [
                                'public' => 'Public Holiday',
                                'school' => 'School Holiday',
                                'religious' => 'Religious Holiday',
                                'cultural' => 'Cultural Holiday',
                            ],
                            'default_value' => 'public',
                        ],
                    ],
                ],
                [
                    'key' => 'field_term_objectives',
                    'label' => 'Term Objectives',
                    'name' => 'term_objectives',
                    'type' => 'textarea',
                    'instructions' => 'Main objectives and goals for this term',
                    'rows' => 4,
                ],
                [
                    'key' => 'field_special_events',
                    'label' => 'Special Events',
                    'name' => 'special_events',
                    'type' => 'textarea',
                    'instructions' => 'Special events, activities, or programs planned for this term',
                    'rows' => 3,
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
     * Add custom columns to the terms admin list.
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
        $new_columns['academic_year'] = __('Academic Year', 'school-management-system');
        $new_columns['term_number'] = __('Term #', 'school-management-system');
        $new_columns['period'] = __('Period', 'school-management-system');
        $new_columns['weeks'] = __('Weeks', 'school-management-system');
        $new_columns['status'] = __('Status', 'school-management-system');
        $new_columns['current'] = __('Current', 'school-management-system');
        
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
            case 'academic_year':
                $academic_year = get_field('academic_year', self::TAXONOMY . '_' . $term_id);
                if ($academic_year && is_object($academic_year)) {
                    return esc_html($academic_year->name);
                }
                return '—';
                
            case 'term_number':
                $term_number = get_field('term_number', self::TAXONOMY . '_' . $term_id);
                return $term_number ? esc_html($term_number) : '—';
                
            case 'period':
                $start_date = get_field('start_date', self::TAXONOMY . '_' . $term_id);
                $end_date = get_field('end_date', self::TAXONOMY . '_' . $term_id);
                
                if ($start_date && $end_date) {
                    return esc_html(date('d/m/Y', strtotime($start_date))) . ' - ' . 
                           esc_html(date('d/m/Y', strtotime($end_date)));
                }
                return '—';
                
            case 'weeks':
                $weeks = get_field('total_weeks', self::TAXONOMY . '_' . $term_id);
                return $weeks ? esc_html($weeks) . ' weeks' : '—';
                
            case 'status':
                $status = get_field('term_status', self::TAXONOMY . '_' . $term_id);
                if ($status) {
                    $status_class = 'status-' . $status;
                    return '<span class="' . esc_attr($status_class) . '">' . esc_html(ucfirst($status)) . '</span>';
                }
                return '—';
                
            case 'current':
                $is_current = get_field('is_current', self::TAXONOMY . '_' . $term_id);
                if ($is_current) {
                    return '<span class="current-term">✓ Current</span>';
                }
                return '—';
        }
        
        return $content;
    }

    /**
     * Add default term terms.
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

        // Get current academic year
        $current_academic_year = SMS_Academic_Years_Taxonomy::get_current_academic_year();
        if (!$current_academic_year) {
            return; // No academic year to attach terms to
        }

        $current_year = date('Y');
        
        $default_terms = [
            [
                'name' => 'Term 1 ' . $current_year,
                'slug' => 'term-1-' . $current_year,
                'description' => 'First term of the academic year',
                'meta' => [
                    'academic_year' => $current_academic_year->term_id,
                    'term_number' => 1,
                    'start_date' => $current_year . '-01-15',
                    'end_date' => $current_year . '-04-15',
                    'total_weeks' => 12,
                    'is_current' => true,
                    'term_status' => 'active',
                    'exam_periods' => [
                        [
                            'exam_name' => 'Mid-Term 1 Exam',
                            'exam_start' => $current_year . '-02-28',
                            'exam_end' => $current_year . '-03-05',
                            'exam_type' => 'mid_term',
                        ],
                        [
                            'exam_name' => 'End of Term 1 Exam',
                            'exam_start' => $current_year . '-04-08',
                            'exam_end' => $current_year . '-04-12',
                            'exam_type' => 'end_term',
                        ],
                    ],
                ]
            ],
            [
                'name' => 'Term 2 ' . $current_year,
                'slug' => 'term-2-' . $current_year,
                'description' => 'Second term of the academic year',
                'meta' => [
                    'academic_year' => $current_academic_year->term_id,
                    'term_number' => 2,
                    'start_date' => $current_year . '-05-01',
                    'end_date' => $current_year . '-08-15',
                    'total_weeks' => 12,
                    'is_current' => false,
                    'term_status' => 'upcoming',
                    'exam_periods' => [
                        [
                            'exam_name' => 'Mid-Term 2 Exam',
                            'exam_start' => $current_year . '-06-15',
                            'exam_end' => $current_year . '-06-20',
                            'exam_type' => 'mid_term',
                        ],
                        [
                            'exam_name' => 'End of Term 2 Exam',
                            'exam_start' => $current_year . '-08-08',
                            'exam_end' => $current_year . '-08-12',
                            'exam_type' => 'end_term',
                        ],
                    ],
                ]
            ],
            [
                'name' => 'Term 3 ' . $current_year,
                'slug' => 'term-3-' . $current_year,
                'description' => 'Third term of the academic year',
                'meta' => [
                    'academic_year' => $current_academic_year->term_id,
                    'term_number' => 3,
                    'start_date' => $current_year . '-09-01',
                    'end_date' => $current_year . '-11-30',
                    'total_weeks' => 12,
                    'is_current' => false,
                    'term_status' => 'upcoming',
                    'exam_periods' => [
                        [
                            'exam_name' => 'Mid-Term 3 Exam',
                            'exam_start' => $current_year . '-10-15',
                            'exam_end' => $current_year . '-10-20',
                            'exam_type' => 'mid_term',
                        ],
                        [
                            'exam_name' => 'End of Term 3 Exam',
                            'exam_start' => $current_year . '-11-20',
                            'exam_end' => $current_year . '-11-25',
                            'exam_type' => 'end_term',
                        ],
                    ],
                ]
            ],
        ];

        foreach ($default_terms as $term_data) {
            $term = wp_insert_term(
                $term_data['name'],
                self::TAXONOMY,
                [
                    'slug' => $term_data['slug'],
                    'description' => $term_data['description']
                ]
            );

            if (!is_wp_error($term)) {
                // Add custom field values
                foreach ($term_data['meta'] as $key => $value) {
                    update_field($key, $value, self::TAXONOMY . '_' . $term['term_id']);
                }
            }
        }
    }

    /**
     * Get current term.
     */
    public static function get_current_term() {
        $terms = get_terms([
            'taxonomy' => self::TAXONOMY,
            'hide_empty' => false,
            'meta_query' => [
                [
                    'key' => 'is_current',
                    'value' => '1',
                    'compare' => '='
                ]
            ]
        ]);

        return (!is_wp_error($terms) && !empty($terms)) ? $terms[0] : null;
    }

    /**
     * Get terms by academic year.
     */
    public static function get_terms_by_academic_year($academic_year_id) {
        $terms = get_terms([
            'taxonomy' => self::TAXONOMY,
            'hide_empty' => false,
            'meta_query' => [
                [
                    'key' => 'academic_year',
                    'value' => $academic_year_id,
                    'compare' => '='
                ]
            ],
            'meta_key' => 'term_number',
            'orderby' => 'meta_value_num',
            'order' => 'ASC'
        ]);

        return is_wp_error($terms) ? [] : $terms;
    }

    /**
     * Get terms by status.
     */
    public static function get_terms_by_status($status = 'active') {
        $terms = get_terms([
            'taxonomy' => self::TAXONOMY,
            'hide_empty' => false,
            'meta_query' => [
                [
                    'key' => 'term_status',
                    'value' => $status,
                    'compare' => '='
                ]
            ]
        ]);

        return is_wp_error($terms) ? [] : $terms;
    }

    /**
     * Check if date falls within term.
     */
    public static function is_date_in_term($date, $term_id) {
        $start_date = get_field('start_date', self::TAXONOMY . '_' . $term_id);
        $end_date = get_field('end_date', self::TAXONOMY . '_' . $term_id);
        
        if (!$start_date || !$end_date) {
            return false;
        }
        
        $check_date = strtotime($date);
        $start_timestamp = strtotime($start_date);
        $end_timestamp = strtotime($end_date);
        
        return ($check_date >= $start_timestamp && $check_date <= $end_timestamp);
    }
}

// Initialize the class
new SMS_Terms_Taxonomy();