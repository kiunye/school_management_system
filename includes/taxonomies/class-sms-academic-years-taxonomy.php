<?php
/**
 * Academic Years Taxonomy
 *
 * Handles the registration and management of the academic years taxonomy
 * for organizing school activities by academic year periods.
 *
 * @package    School_Management_System
 * @subpackage School_Management_System/includes/taxonomies
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Academic Years Taxonomy Class
 */
class SMS_Academic_Years_Taxonomy extends SMS_Base {

    /**
     * The taxonomy name.
     */
    const TAXONOMY = 'sms_academic_years';

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
     * Register the academic years taxonomy.
     */
    public function register_taxonomy() {
        $labels = [
            'name'                       => _x('Academic Years', 'Taxonomy General Name', 'school-management-system'),
            'singular_name'              => _x('Academic Year', 'Taxonomy Singular Name', 'school-management-system'),
            'menu_name'                  => __('Academic Years', 'school-management-system'),
            'all_items'                  => __('All Academic Years', 'school-management-system'),
            'parent_item'                => __('Parent Academic Year', 'school-management-system'),
            'parent_item_colon'          => __('Parent Academic Year:', 'school-management-system'),
            'new_item_name'              => __('New Academic Year Name', 'school-management-system'),
            'add_new_item'               => __('Add New Academic Year', 'school-management-system'),
            'edit_item'                  => __('Edit Academic Year', 'school-management-system'),
            'update_item'                => __('Update Academic Year', 'school-management-system'),
            'view_item'                  => __('View Academic Year', 'school-management-system'),
            'separate_items_with_commas' => __('Separate academic years with commas', 'school-management-system'),
            'add_or_remove_items'        => __('Add or remove academic years', 'school-management-system'),
            'choose_from_most_used'      => __('Choose from the most used', 'school-management-system'),
            'popular_items'              => __('Popular Academic Years', 'school-management-system'),
            'search_items'               => __('Search Academic Years', 'school-management-system'),
            'not_found'                  => __('Not Found', 'school-management-system'),
            'no_terms'                   => __('No academic years', 'school-management-system'),
            'items_list'                 => __('Academic Years list', 'school-management-system'),
            'items_list_navigation'      => __('Academic Years list navigation', 'school-management-system'),
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
            'rest_base'                  => 'academic-years',
            'rest_controller_class'      => 'WP_REST_Terms_Controller',
            'capabilities'               => [
                'manage_terms' => 'manage_academic_years',
                'edit_terms'   => 'manage_academic_years',
                'delete_terms' => 'manage_academic_years',
                'assign_terms' => 'manage_academic_years',
            ],
        ];

        register_taxonomy(self::TAXONOMY, ['sms_students', 'sms_classes', 'sms_fees', 'sms_invoices'], $args);
    }

    /**
     * Add custom fields to taxonomy terms.
     */
    public function add_taxonomy_fields() {
        if (!function_exists('acf_add_local_field_group')) {
            return;
        }

        acf_add_local_field_group([
            'key' => 'group_academic_year_details',
            'title' => 'Academic Year Details',
            'fields' => [
                [
                    'key' => 'field_start_date',
                    'label' => 'Start Date',
                    'name' => 'start_date',
                    'type' => 'date_picker',
                    'instructions' => 'Academic year start date',
                    'required' => 1,
                    'display_format' => 'd/m/Y',
                    'return_format' => 'Y-m-d',
                    'wrapper' => [
                        'width' => '50',
                    ],
                ],
                [
                    'key' => 'field_end_date',
                    'label' => 'End Date',
                    'name' => 'end_date',
                    'type' => 'date_picker',
                    'instructions' => 'Academic year end date',
                    'required' => 1,
                    'display_format' => 'd/m/Y',
                    'return_format' => 'Y-m-d',
                    'wrapper' => [
                        'width' => '50',
                    ],
                ],
                [
                    'key' => 'field_is_current',
                    'label' => 'Current Academic Year',
                    'name' => 'is_current',
                    'type' => 'true_false',
                    'instructions' => 'Mark this as the current academic year',
                    'default_value' => 0,
                    'ui' => 1,
                ],
                [
                    'key' => 'field_year_status',
                    'label' => 'Status',
                    'name' => 'year_status',
                    'type' => 'select',
                    'instructions' => 'Current status of this academic year',
                    'required' => 1,
                    'choices' => [
                        'upcoming' => 'Upcoming',
                        'active' => 'Active',
                        'completed' => 'Completed',
                        'archived' => 'Archived',
                    ],
                    'default_value' => 'upcoming',
                ],
                [
                    'key' => 'field_term_structure',
                    'label' => 'Term Structure',
                    'name' => 'term_structure',
                    'type' => 'repeater',
                    'instructions' => 'Define the terms for this academic year',
                    'required' => 1,
                    'min' => 1,
                    'max' => 4,
                    'layout' => 'table',
                    'button_label' => 'Add Term',
                    'sub_fields' => [
                        [
                            'key' => 'field_term_name',
                            'label' => 'Term Name',
                            'name' => 'term_name',
                            'type' => 'text',
                            'required' => 1,
                            'placeholder' => 'Term 1',
                        ],
                        [
                            'key' => 'field_term_start',
                            'label' => 'Start Date',
                            'name' => 'term_start',
                            'type' => 'date_picker',
                            'required' => 1,
                            'display_format' => 'd/m/Y',
                            'return_format' => 'Y-m-d',
                        ],
                        [
                            'key' => 'field_term_end',
                            'label' => 'End Date',
                            'name' => 'term_end',
                            'type' => 'date_picker',
                            'required' => 1,
                            'display_format' => 'd/m/Y',
                            'return_format' => 'Y-m-d',
                        ],
                        [
                            'key' => 'field_term_weeks',
                            'label' => 'Weeks',
                            'name' => 'term_weeks',
                            'type' => 'number',
                            'min' => 1,
                            'max' => 20,
                            'default_value' => 12,
                        ],
                    ],
                ],
                [
                    'key' => 'field_holidays',
                    'label' => 'Holidays & Breaks',
                    'name' => 'holidays',
                    'type' => 'repeater',
                    'instructions' => 'Define holidays and breaks for this academic year',
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
                            'key' => 'field_holiday_start',
                            'label' => 'Start Date',
                            'name' => 'holiday_start',
                            'type' => 'date_picker',
                            'required' => 1,
                            'display_format' => 'd/m/Y',
                            'return_format' => 'Y-m-d',
                        ],
                        [
                            'key' => 'field_holiday_end',
                            'label' => 'End Date',
                            'name' => 'holiday_end',
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
                                'term_break' => 'Term Break',
                                'public_holiday' => 'Public Holiday',
                                'school_holiday' => 'School Holiday',
                                'exam_period' => 'Exam Period',
                            ],
                            'default_value' => 'term_break',
                        ],
                    ],
                ],
                [
                    'key' => 'field_academic_calendar',
                    'label' => 'Academic Calendar Notes',
                    'name' => 'academic_calendar',
                    'type' => 'textarea',
                    'instructions' => 'Additional notes about the academic calendar',
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
     * Add custom columns to the academic years admin list.
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
        $new_columns['period'] = __('Period', 'school-management-system');
        $new_columns['status'] = __('Status', 'school-management-system');
        $new_columns['current'] = __('Current', 'school-management-system');
        $new_columns['terms'] = __('Terms', 'school-management-system');
        
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
            case 'period':
                $start_date = get_field('start_date', self::TAXONOMY . '_' . $term_id);
                $end_date = get_field('end_date', self::TAXONOMY . '_' . $term_id);
                
                if ($start_date && $end_date) {
                    return esc_html(date('d/m/Y', strtotime($start_date))) . ' - ' . 
                           esc_html(date('d/m/Y', strtotime($end_date)));
                }
                return '—';
                
            case 'status':
                $status = get_field('year_status', self::TAXONOMY . '_' . $term_id);
                if ($status) {
                    $status_class = 'status-' . $status;
                    return '<span class="' . esc_attr($status_class) . '">' . esc_html(ucfirst($status)) . '</span>';
                }
                return '—';
                
            case 'current':
                $is_current = get_field('is_current', self::TAXONOMY . '_' . $term_id);
                if ($is_current) {
                    return '<span class="current-year">✓ Current</span>';
                }
                return '—';
                
            case 'terms':
                $terms = get_field('term_structure', self::TAXONOMY . '_' . $term_id);
                if ($terms && is_array($terms)) {
                    return esc_html(count($terms)) . ' terms';
                }
                return '—';
        }
        
        return $content;
    }

    /**
     * Add default academic year terms.
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

        $current_year = date('Y');
        $next_year = $current_year + 1;
        
        $default_academic_years = [
            [
                'name' => $current_year . '-' . $next_year,
                'slug' => $current_year . '-' . $next_year,
                'description' => 'Academic year ' . $current_year . '-' . $next_year,
                'meta' => [
                    'start_date' => $current_year . '-01-15',
                    'end_date' => $current_year . '-11-30',
                    'is_current' => true,
                    'year_status' => 'active',
                    'term_structure' => [
                        [
                            'term_name' => 'Term 1',
                            'term_start' => $current_year . '-01-15',
                            'term_end' => $current_year . '-04-15',
                            'term_weeks' => 12,
                        ],
                        [
                            'term_name' => 'Term 2',
                            'term_start' => $current_year . '-05-01',
                            'term_end' => $current_year . '-08-15',
                            'term_weeks' => 12,
                        ],
                        [
                            'term_name' => 'Term 3',
                            'term_start' => $current_year . '-09-01',
                            'term_end' => $current_year . '-11-30',
                            'term_weeks' => 12,
                        ],
                    ],
                    'holidays' => [
                        [
                            'holiday_name' => 'April Holiday',
                            'holiday_start' => $current_year . '-04-16',
                            'holiday_end' => $current_year . '-04-30',
                            'holiday_type' => 'term_break',
                        ],
                        [
                            'holiday_name' => 'August Holiday',
                            'holiday_start' => $current_year . '-08-16',
                            'holiday_end' => $current_year . '-08-31',
                            'holiday_type' => 'term_break',
                        ],
                        [
                            'holiday_name' => 'December Holiday',
                            'holiday_start' => $current_year . '-12-01',
                            'holiday_end' => ($current_year + 1) . '-01-14',
                            'holiday_type' => 'term_break',
                        ],
                    ],
                ]
            ],
            [
                'name' => $next_year . '-' . ($next_year + 1),
                'slug' => $next_year . '-' . ($next_year + 1),
                'description' => 'Academic year ' . $next_year . '-' . ($next_year + 1),
                'meta' => [
                    'start_date' => $next_year . '-01-15',
                    'end_date' => $next_year . '-11-30',
                    'is_current' => false,
                    'year_status' => 'upcoming',
                    'term_structure' => [
                        [
                            'term_name' => 'Term 1',
                            'term_start' => $next_year . '-01-15',
                            'term_end' => $next_year . '-04-15',
                            'term_weeks' => 12,
                        ],
                        [
                            'term_name' => 'Term 2',
                            'term_start' => $next_year . '-05-01',
                            'term_end' => $next_year . '-08-15',
                            'term_weeks' => 12,
                        ],
                        [
                            'term_name' => 'Term 3',
                            'term_start' => $next_year . '-09-01',
                            'term_end' => $next_year . '-11-30',
                            'term_weeks' => 12,
                        ],
                    ],
                ]
            ],
        ];

        foreach ($default_academic_years as $year) {
            $term = wp_insert_term(
                $year['name'],
                self::TAXONOMY,
                [
                    'slug' => $year['slug'],
                    'description' => $year['description']
                ]
            );

            if (!is_wp_error($term)) {
                // Add custom field values
                foreach ($year['meta'] as $key => $value) {
                    update_field($key, $value, self::TAXONOMY . '_' . $term['term_id']);
                }
            }
        }
    }

    /**
     * Get current academic year.
     */
    public static function get_current_academic_year() {
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
     * Get academic years by status.
     */
    public static function get_academic_years_by_status($status = 'active') {
        $terms = get_terms([
            'taxonomy' => self::TAXONOMY,
            'hide_empty' => false,
            'meta_query' => [
                [
                    'key' => 'year_status',
                    'value' => $status,
                    'compare' => '='
                ]
            ]
        ]);

        return is_wp_error($terms) ? [] : $terms;
    }

    /**
     * Get term dates for academic year.
     */
    public static function get_term_dates($academic_year_id) {
        $term_structure = get_field('term_structure', self::TAXONOMY . '_' . $academic_year_id);
        return $term_structure ?: [];
    }

    /**
     * Check if date falls within academic year.
     */
    public static function is_date_in_academic_year($date, $academic_year_id) {
        $start_date = get_field('start_date', self::TAXONOMY . '_' . $academic_year_id);
        $end_date = get_field('end_date', self::TAXONOMY . '_' . $academic_year_id);
        
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
new SMS_Academic_Years_Taxonomy();