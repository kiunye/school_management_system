<?php
/**
 * Fee Category Management
 *
 * Handles fee type categorization, bulk fee operations,
 * and category-based fee structures.
 *
 * @package    School_Management_System
 * @subpackage School_Management_System/includes/financial
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Fee Category Manager Class
 */
class SMS_Fee_Category_Manager extends SMS_Base {

    /**
     * Default fee categories
     */
    private $default_categories = [
        'tuition' => [
            'name' => 'Tuition Fee',
            'description' => 'Academic tuition and instruction fees',
            'mandatory' => true,
            'color' => '#2196F3'
        ],
        'transport' => [
            'name' => 'Transport Fee',
            'description' => 'School bus and transportation fees',
            'mandatory' => false,
            'color' => '#FF9800'
        ],
        'meals' => [
            'name' => 'Meals Fee',
            'description' => 'School meals and nutrition program fees',
            'mandatory' => false,
            'color' => '#4CAF50'
        ],
        'books' => [
            'name' => 'Books & Materials',
            'description' => 'Textbooks, stationery, and learning materials',
            'mandatory' => true,
            'color' => '#9C27B0'
        ],
        'uniform' => [
            'name' => 'Uniform Fee',
            'description' => 'School uniform and dress code items',
            'mandatory' => true,
            'color' => '#607D8B'
        ],
        'activity' => [
            'name' => 'Activity Fee',
            'description' => 'Sports, clubs, and extracurricular activities',
            'mandatory' => false,
            'color' => '#E91E63'
        ],
        'exam' => [
            'name' => 'Examination Fee',
            'description' => 'Examination and assessment fees',
            'mandatory' => true,
            'color' => '#795548'
        ],
        'registration' => [
            'name' => 'Registration Fee',
            'description' => 'Student registration and admission fees',
            'mandatory' => true,
            'color' => '#009688'
        ]
    ];

    /**
     * Initialize the class and set its properties.
     */
    public function __construct() {
        parent::__construct();
        
        // Initialize default categories
        add_action('sms_init_fee_categories', [$this, 'initialize_default_categories']);
        
        // Category management hooks
        add_action('sms_create_fee_category', [$this, 'create_fee_category'], 10, 2);
        add_action('sms_update_fee_category', [$this, 'update_fee_category'], 10, 3);
        add_action('sms_delete_fee_category', [$this, 'delete_fee_category'], 10, 1);
        
        // Bulk operations
        add_action('sms_bulk_create_fees_by_category', [$this, 'bulk_create_fees_by_category'], 10, 2);
        add_action('sms_bulk_update_category_fees', [$this, 'bulk_update_category_fees'], 10, 3);
        
        // Category-based calculations
        add_filter('sms_calculate_category_totals', [$this, 'calculate_category_totals'], 10, 2);
        add_filter('sms_get_student_category_fees', [$this, 'get_student_category_fees'], 10, 2);
    }

    /**
     * Initialize default fee categories.
     */
    public function initialize_default_categories() {
        $existing_categories = get_option('sms_fee_categories', []);
        
        if (empty($existing_categories)) {
            update_option('sms_fee_categories', $this->default_categories);
            
            // Log initialization
            $this->log_activity(
                get_current_user_id(),
                'fee_categories_initialized',
                'system',
                0,
                ['categories_count' => count($this->default_categories)]
            );
        }
    }

    /**
     * Get all fee categories.
     *
     * @return array Fee categories
     */
    public function get_fee_categories() {
        $categories = get_option('sms_fee_categories', $this->default_categories);
        
        // Apply filters to allow customization
        return apply_filters('sms_fee_categories', $categories);
    }

    /**
     * Get a specific fee category.
     *
     * @param string $category_key Category key
     * @return array|false Category data or false
     */
    public function get_fee_category($category_key) {
        $categories = $this->get_fee_categories();
        
        return isset($categories[$category_key]) ? $categories[$category_key] : false;
    }

    /**
     * Create a new fee category.
     *
     * @param string $category_key Category key
     * @param array  $category_data Category data
     * @return bool|WP_Error Success or error
     */
    public function create_fee_category($category_key, $category_data) {
        try {
            // Validate category data
            $validation_result = $this->validate_category_data($category_data);
            if (is_wp_error($validation_result)) {
                return $validation_result;
            }

            // Check if category already exists
            $categories = $this->get_fee_categories();
            if (isset($categories[$category_key])) {
                return new WP_Error(
                    'category_exists',
                    __('Fee category already exists.', 'school-management-system')
                );
            }

            // Prepare category data
            $category_data = wp_parse_args($category_data, [
                'name' => '',
                'description' => '',
                'mandatory' => false,
                'color' => '#2196F3',
                'sort_order' => 999,
                'active' => true,
                'created_date' => current_time('mysql'),
                'created_by' => get_current_user_id()
            ]);

            // Add category
            $categories[$category_key] = $category_data;
            update_option('sms_fee_categories', $categories);

            // Log creation
            $this->log_activity(
                get_current_user_id(),
                'fee_category_created',
                'category',
                0,
                ['category_key' => $category_key, 'name' => $category_data['name']]
            );

            // Trigger action
            do_action('sms_fee_category_created', $category_key, $category_data);

            return true;

        } catch (Exception $e) {
            return new WP_Error('category_creation_failed', $e->getMessage());
        }
    }

    /**
     * Update an existing fee category.
     *
     * @param string $category_key  Category key
     * @param array  $category_data Updated category data
     * @return bool|WP_Error Success or error
     */
    public function update_fee_category($category_key, $category_data) {
        try {
            // Check if category exists
            $categories = $this->get_fee_categories();
            if (!isset($categories[$category_key])) {
                return new WP_Error(
                    'category_not_found',
                    __('Fee category not found.', 'school-management-system')
                );
            }

            // Validate category data
            $validation_result = $this->validate_category_data($category_data);
            if (is_wp_error($validation_result)) {
                return $validation_result;
            }

            // Update category
            $categories[$category_key] = array_merge($categories[$category_key], $category_data);
            $categories[$category_key]['updated_date'] = current_time('mysql');
            $categories[$category_key]['updated_by'] = get_current_user_id();

            update_option('sms_fee_categories', $categories);

            // Log update
            $this->log_activity(
                get_current_user_id(),
                'fee_category_updated',
                'category',
                0,
                ['category_key' => $category_key, 'name' => $category_data['name'] ?? '']
            );

            // Trigger action
            do_action('sms_fee_category_updated', $category_key, $category_data);

            return true;

        } catch (Exception $e) {
            return new WP_Error('category_update_failed', $e->getMessage());
        }
    }

    /**
     * Delete a fee category.
     *
     * @param string $category_key Category key
     * @return bool|WP_Error Success or error
     */
    public function delete_fee_category($category_key) {
        try {
            // Check if category exists
            $categories = $this->get_fee_categories();
            if (!isset($categories[$category_key])) {
                return new WP_Error(
                    'category_not_found',
                    __('Fee category not found.', 'school-management-system')
                );
            }

            // Check if category is in use
            $fees_using_category = $this->get_fees_by_category($category_key);
            if (!empty($fees_using_category)) {
                return new WP_Error(
                    'category_in_use',
                    sprintf(
                        __('Cannot delete category. %d fees are using this category.', 'school-management-system'),
                        count($fees_using_category)
                    )
                );
            }

            // Remove category
            unset($categories[$category_key]);
            update_option('sms_fee_categories', $categories);

            // Log deletion
            $this->log_activity(
                get_current_user_id(),
                'fee_category_deleted',
                'category',
                0,
                ['category_key' => $category_key]
            );

            // Trigger action
            do_action('sms_fee_category_deleted', $category_key);

            return true;

        } catch (Exception $e) {
            return new WP_Error('category_deletion_failed', $e->getMessage());
        }
    }

    /**
     * Get fees by category.
     *
     * @param string $category_key Category key
     * @return array Fees in category
     */
    public function get_fees_by_category($category_key) {
        $args = [
            'post_type' => 'sms_fees',
            'post_status' => 'any',
            'meta_query' => [
                [
                    'key' => 'fee_type',
                    'value' => $category_key,
                    'compare' => '='
                ]
            ],
            'posts_per_page' => -1
        ];

        return get_posts($args);
    }

    /**
     * Bulk create fees for multiple categories.
     *
     * @param array $categories_data Categories and their fee data
     * @param array $common_settings Common settings for all fees
     * @return array Results of fee creation
     */
    public function bulk_create_fees_by_category($categories_data, $common_settings = []) {
        $results = [];
        $fee_manager = new SMS_Fee_Manager();

        foreach ($categories_data as $category_key => $fee_data) {
            // Merge with common settings
            $complete_fee_data = array_merge($common_settings, $fee_data, [
                'fee_type' => $category_key
            ]);

            // Create fee
            $result = $fee_manager->create_fee_structure($complete_fee_data);
            
            $results[$category_key] = [
                'success' => !is_wp_error($result),
                'fee_id' => is_wp_error($result) ? null : $result,
                'error' => is_wp_error($result) ? $result->get_error_message() : null
            ];
        }

        // Log bulk operation
        $successful_count = count(array_filter($results, function($r) { return $r['success']; }));
        $this->log_activity(
            get_current_user_id(),
            'bulk_fees_created_by_category',
            'bulk_operation',
            0,
            [
                'total_categories' => count($categories_data),
                'successful_count' => $successful_count,
                'failed_count' => count($categories_data) - $successful_count
            ]
        );

        return $results;
    }

    /**
     * Bulk update fees in a category.
     *
     * @param string $category_key Category key
     * @param array  $update_data  Data to update
     * @param array  $filters      Filters for selecting fees
     * @return array Update results
     */
    public function bulk_update_category_fees($category_key, $update_data, $filters = []) {
        $fees = $this->get_fees_by_category($category_key);
        $results = [];
        $fee_manager = new SMS_Fee_Manager();

        foreach ($fees as $fee) {
            // Apply filters if specified
            if (!empty($filters) && !$this->fee_matches_filters($fee, $filters)) {
                continue;
            }

            // Update fee
            $result = $fee_manager->update_fee_structure($fee->ID, $update_data);
            
            $results[$fee->ID] = [
                'success' => !is_wp_error($result),
                'error' => is_wp_error($result) ? $result->get_error_message() : null
            ];
        }

        // Log bulk operation
        $successful_count = count(array_filter($results, function($r) { return $r['success']; }));
        $this->log_activity(
            get_current_user_id(),
            'bulk_category_fees_updated',
            'bulk_operation',
            0,
            [
                'category_key' => $category_key,
                'total_fees' => count($results),
                'successful_count' => $successful_count,
                'failed_count' => count($results) - $successful_count
            ]
        );

        return $results;
    }

    /**
     * Calculate category totals for a student.
     *
     * @param int   $student_id Student ID
     * @param array $options    Calculation options
     * @return array Category totals
     */
    public function calculate_category_totals($student_id, $options = []) {
        $categories = $this->get_fee_categories();
        $category_totals = [];
        $fee_manager = new SMS_Fee_Manager();

        foreach ($categories as $category_key => $category_data) {
            $category_fees = $this->get_fees_by_category($category_key);
            $category_total = [
                'category_key' => $category_key,
                'category_name' => $category_data['name'],
                'category_color' => $category_data['color'] ?? '#2196F3',
                'mandatory' => $category_data['mandatory'] ?? false,
                'fees' => [],
                'total_amount' => 0,
                'total_due' => 0,
                'total_paid' => 0,
                'total_exemptions' => 0,
                'total_penalties' => 0
            ];

            foreach ($category_fees as $fee) {
                // Calculate fee for student
                $fee_calculation = $fee_manager->calculate_student_fee($student_id, $fee->ID, $options);
                
                if (!is_wp_error($fee_calculation) && $fee_calculation['applicable']) {
                    $category_total['fees'][] = $fee_calculation;
                    $category_total['total_amount'] += $fee_calculation['total_amount'];
                    $category_total['total_due'] += $fee_calculation['amount_due'];
                    $category_total['total_paid'] += $fee_calculation['amount_paid'];
                    
                    if (!empty($fee_calculation['exemptions'])) {
                        $category_total['total_exemptions'] += array_sum(array_column($fee_calculation['exemptions'], 'amount'));
                    }
                    
                    if (!empty($fee_calculation['penalties'])) {
                        $category_total['total_penalties'] += array_sum(array_column($fee_calculation['penalties'], 'amount'));
                    }
                }
            }

            $category_totals[$category_key] = $category_total;
        }

        return $category_totals;
    }

    /**
     * Get student fees grouped by category.
     *
     * @param int   $student_id Student ID
     * @param array $options    Options
     * @return array Categorized fees
     */
    public function get_student_category_fees($student_id, $options = []) {
        return $this->calculate_category_totals($student_id, $options);
    }

    /**
     * Get category statistics.
     *
     * @param string $category_key Category key (optional)
     * @return array Category statistics
     */
    public function get_category_statistics($category_key = null) {
        if ($category_key) {
            return $this->get_single_category_statistics($category_key);
        }

        $categories = $this->get_fee_categories();
        $statistics = [];

        foreach ($categories as $key => $category) {
            $statistics[$key] = $this->get_single_category_statistics($key);
        }

        return $statistics;
    }

    /**
     * Get statistics for a single category.
     *
     * @param string $category_key Category key
     * @return array Category statistics
     */
    private function get_single_category_statistics($category_key) {
        $fees = $this->get_fees_by_category($category_key);
        $category = $this->get_fee_category($category_key);

        $statistics = [
            'category_key' => $category_key,
            'category_name' => $category['name'] ?? '',
            'total_fees' => count($fees),
            'active_fees' => 0,
            'inactive_fees' => 0,
            'total_amount' => 0,
            'average_amount' => 0,
            'mandatory_fees' => 0,
            'optional_fees' => 0
        ];

        foreach ($fees as $fee) {
            $fee_status = get_field('fee_status', $fee->ID);
            $fee_amount = floatval(get_field('fee_amount', $fee->ID));
            $mandatory = get_field('mandatory', $fee->ID);

            if ($fee_status === 'active') {
                $statistics['active_fees']++;
            } else {
                $statistics['inactive_fees']++;
            }

            $statistics['total_amount'] += $fee_amount;

            if ($mandatory) {
                $statistics['mandatory_fees']++;
            } else {
                $statistics['optional_fees']++;
            }
        }

        if ($statistics['total_fees'] > 0) {
            $statistics['average_amount'] = $statistics['total_amount'] / $statistics['total_fees'];
        }

        return $statistics;
    }

    /**
     * Generate category-based fee report.
     *
     * @param array $options Report options
     * @return array Report data
     */
    public function generate_category_report($options = []) {
        $categories = $this->get_fee_categories();
        $report = [
            'report_date' => current_time('mysql'),
            'report_type' => 'category_summary',
            'categories' => [],
            'summary' => [
                'total_categories' => count($categories),
                'total_fees' => 0,
                'total_amount' => 0,
                'active_categories' => 0
            ]
        ];

        foreach ($categories as $category_key => $category_data) {
            $category_stats = $this->get_single_category_statistics($category_key);
            $report['categories'][$category_key] = array_merge($category_data, $category_stats);

            // Update summary
            $report['summary']['total_fees'] += $category_stats['total_fees'];
            $report['summary']['total_amount'] += $category_stats['total_amount'];
            
            if ($category_data['active'] ?? true) {
                $report['summary']['active_categories']++;
            }
        }

        return $report;
    }

    /**
     * Validate category data.
     *
     * @param array $category_data Category data
     * @return bool|WP_Error True if valid, WP_Error if invalid
     */
    private function validate_category_data($category_data) {
        $errors = [];

        // Required fields
        if (empty($category_data['name'])) {
            $errors[] = __('Category name is required.', 'school-management-system');
        }

        // Validate color format
        if (!empty($category_data['color']) && !preg_match('/^#[a-fA-F0-9]{6}$/', $category_data['color'])) {
            $errors[] = __('Invalid color format. Use hex format (#RRGGBB).', 'school-management-system');
        }

        // Validate sort order
        if (isset($category_data['sort_order']) && (!is_numeric($category_data['sort_order']) || $category_data['sort_order'] < 0)) {
            $errors[] = __('Sort order must be a positive number.', 'school-management-system');
        }

        if (!empty($errors)) {
            return new WP_Error('validation_failed', implode(' ', $errors));
        }

        return true;
    }

    /**
     * Check if fee matches filters.
     *
     * @param WP_Post $fee     Fee post
     * @param array   $filters Filters to apply
     * @return bool Whether fee matches filters
     */
    private function fee_matches_filters($fee, $filters) {
        // Status filter
        if (!empty($filters['status'])) {
            $fee_status = get_field('fee_status', $fee->ID);
            if ($fee_status !== $filters['status']) {
                return false;
            }
        }

        // Amount range filter
        if (!empty($filters['min_amount']) || !empty($filters['max_amount'])) {
            $fee_amount = floatval(get_field('fee_amount', $fee->ID));
            
            if (!empty($filters['min_amount']) && $fee_amount < floatval($filters['min_amount'])) {
                return false;
            }
            
            if (!empty($filters['max_amount']) && $fee_amount > floatval($filters['max_amount'])) {
                return false;
            }
        }

        // Mandatory filter
        if (isset($filters['mandatory'])) {
            $mandatory = get_field('mandatory', $fee->ID);
            if (!!$mandatory !== !!$filters['mandatory']) {
                return false;
            }
        }

        // Academic year filter
        if (!empty($filters['academic_year'])) {
            $academic_years = wp_get_object_terms($fee->ID, 'sms_academic_years', ['fields' => 'slugs']);
            if (!in_array($filters['academic_year'], $academic_years)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Export category configuration.
     *
     * @return array Category configuration
     */
    public function export_category_configuration() {
        $categories = $this->get_fee_categories();
        
        return [
            'export_date' => current_time('mysql'),
            'export_version' => '1.0',
            'categories' => $categories
        ];
    }

    /**
     * Import category configuration.
     *
     * @param array $configuration Category configuration
     * @return bool|WP_Error Success or error
     */
    public function import_category_configuration($configuration) {
        try {
            // Validate configuration format
            if (empty($configuration['categories']) || !is_array($configuration['categories'])) {
                return new WP_Error('invalid_configuration', __('Invalid configuration format.', 'school-management-system'));
            }

            // Validate each category
            foreach ($configuration['categories'] as $key => $category) {
                $validation_result = $this->validate_category_data($category);
                if (is_wp_error($validation_result)) {
                    return new WP_Error(
                        'invalid_category',
                        sprintf(__('Invalid category "%s": %s', 'school-management-system'), $key, $validation_result->get_error_message())
                    );
                }
            }

            // Import categories
            update_option('sms_fee_categories', $configuration['categories']);

            // Log import
            $this->log_activity(
                get_current_user_id(),
                'fee_categories_imported',
                'system',
                0,
                ['categories_count' => count($configuration['categories'])]
            );

            return true;

        } catch (Exception $e) {
            return new WP_Error('import_failed', $e->getMessage());
        }
    }

    /**
     * Get category choices for form fields.
     *
     * @param bool $include_inactive Include inactive categories
     * @return array Category choices
     */
    public function get_category_choices($include_inactive = false) {
        $categories = $this->get_fee_categories();
        $choices = [];

        foreach ($categories as $key => $category) {
            if (!$include_inactive && isset($category['active']) && !$category['active']) {
                continue;
            }

            $choices[$key] = $category['name'];
        }

        return $choices;
    }

    /**
     * Get category colors for UI styling.
     *
     * @return array Category colors
     */
    public function get_category_colors() {
        $categories = $this->get_fee_categories();
        $colors = [];

        foreach ($categories as $key => $category) {
            $colors[$key] = $category['color'] ?? '#2196F3';
        }

        return $colors;
    }
}