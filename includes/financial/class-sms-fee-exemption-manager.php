<?php
/**
 * Fee Exemption and Discount Management
 *
 * Handles fee exemptions, scholarships, and special discount management
 * for students with specific circumstances.
 *
 * @package    School_Management_System
 * @subpackage School_Management_System/includes/financial
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Fee Exemption Manager Class
 */
class SMS_Fee_Exemption_Manager extends SMS_Base {

    /**
     * Exemption post type
     */
    const EXEMPTION_POST_TYPE = 'sms_fee_exemptions';

    /**
     * Initialize the class and set its properties.
     */
    public function __construct() {
        parent::__construct();
        
        // Register exemption post type
        add_action('sms_register_post_types', [$this, 'register_exemption_post_type']);
        
        // Add ACF fields for exemptions
        add_action('acf/init', [$this, 'register_exemption_acf_fields']);
        
        // Hook into fee calculations
        add_filter('sms_calculate_student_fee', [$this, 'apply_exemptions_to_calculation'], 10, 3);
        add_filter('sms_student_applicable_discounts', [$this, 'get_student_exemptions'], 10, 2);
        
        // Admin interface hooks
        add_filter('manage_' . self::EXEMPTION_POST_TYPE . '_posts_columns', [$this, 'add_exemption_columns']);
        add_action('manage_' . self::EXEMPTION_POST_TYPE . '_posts_custom_column', [$this, 'populate_exemption_columns'], 10, 2);
        
        // Exemption validation
        add_action('save_post_' . self::EXEMPTION_POST_TYPE, [$this, 'validate_exemption'], 10, 3);
        
        // Automated exemption processing
        add_action('sms_process_exemption_renewals', [$this, 'process_exemption_renewals']);
    }

    /**
     * Register the fee exemptions custom post type.
     */
    public function register_exemption_post_type() {
        $labels = [
            'name'                  => _x('Fee Exemptions', 'Post Type General Name', 'school-management-system'),
            'singular_name'         => _x('Fee Exemption', 'Post Type Singular Name', 'school-management-system'),
            'menu_name'             => __('Fee Exemptions', 'school-management-system'),
            'name_admin_bar'        => __('Fee Exemption', 'school-management-system'),
            'archives'              => __('Exemption Archives', 'school-management-system'),
            'attributes'            => __('Exemption Attributes', 'school-management-system'),
            'parent_item_colon'     => __('Parent Exemption:', 'school-management-system'),
            'all_items'             => __('All Exemptions', 'school-management-system'),
            'add_new_item'          => __('Add New Exemption', 'school-management-system'),
            'add_new'               => __('Add New', 'school-management-system'),
            'new_item'              => __('New Exemption', 'school-management-system'),
            'edit_item'             => __('Edit Exemption', 'school-management-system'),
            'update_item'           => __('Update Exemption', 'school-management-system'),
            'view_item'             => __('View Exemption', 'school-management-system'),
            'view_items'            => __('View Exemptions', 'school-management-system'),
            'search_items'          => __('Search Exemptions', 'school-management-system'),
            'not_found'             => __('Not found', 'school-management-system'),
            'not_found_in_trash'    => __('Not found in Trash', 'school-management-system'),
        ];

        $args = [
            'label'                 => __('Fee Exemption', 'school-management-system'),
            'description'           => __('Fee exemptions and special discounts', 'school-management-system'),
            'labels'                => $labels,
            'supports'              => ['title', 'editor', 'revisions'],
            'hierarchical'          => false,
            'public'                => false,
            'show_ui'               => true,
            'show_in_menu'          => 'sms-dashboard',
            'menu_position'         => 8,
            'menu_icon'             => 'dashicons-awards',
            'show_in_admin_bar'     => true,
            'show_in_nav_menus'     => false,
            'can_export'            => true,
            'has_archive'           => false,
            'exclude_from_search'   => true,
            'publicly_queryable'    => false,
            'capability_type'       => 'post',
            'capabilities'          => [
                'edit_post'          => 'manage_fees',
                'read_post'          => 'manage_fees',
                'delete_post'        => 'manage_fees',
                'edit_posts'         => 'manage_fees',
                'edit_others_posts'  => 'manage_fees',
                'delete_posts'       => 'manage_fees',
                'publish_posts'      => 'manage_fees',
                'read_private_posts' => 'manage_fees',
            ],
            'show_in_rest'          => true,
        ];

        register_post_type(self::EXEMPTION_POST_TYPE, $args);
    }

    /**
     * Register ACF fields for exemptions.
     */
    public function register_exemption_acf_fields() {
        if (!function_exists('acf_add_local_field_group')) {
            return;
        }

        // Basic Exemption Information
        acf_add_local_field_group([
            'key' => 'group_exemption_basic',
            'title' => 'Exemption Details',
            'fields' => [
                [
                    'key' => 'field_exemption_student',
                    'label' => 'Student',
                    'name' => 'exemption_student',
                    'type' => 'post_object',
                    'instructions' => 'Select the student for this exemption',
                    'required' => 1,
                    'post_type' => ['sms_students'],
                    'return_format' => 'id',
                    'ui' => 1,
                    'wrapper' => [
                        'width' => '50',
                    ],
                ],
                [
                    'key' => 'field_exemption_type',
                    'label' => 'Exemption Type',
                    'name' => 'exemption_type',
                    'type' => 'select',
                    'instructions' => 'Type of exemption or discount',
                    'required' => 1,
                    'choices' => [
                        'full_scholarship' => 'Full Scholarship',
                        'partial_scholarship' => 'Partial Scholarship',
                        'sibling_discount' => 'Sibling Discount',
                        'staff_discount' => 'Staff Child Discount',
                        'hardship_exemption' => 'Financial Hardship',
                        'merit_scholarship' => 'Merit-based Scholarship',
                        'sports_scholarship' => 'Sports Scholarship',
                        'special_needs' => 'Special Needs Support',
                        'other' => 'Other',
                    ],
                    'default_value' => 'partial_scholarship',
                    'wrapper' => [
                        'width' => '50',
                    ],
                ],
                [
                    'key' => 'field_exemption_status',
                    'label' => 'Status',
                    'name' => 'exemption_status',
                    'type' => 'select',
                    'instructions' => 'Current status of this exemption',
                    'required' => 1,
                    'choices' => [
                        'active' => 'Active',
                        'pending' => 'Pending Approval',
                        'expired' => 'Expired',
                        'suspended' => 'Suspended',
                        'cancelled' => 'Cancelled',
                    ],
                    'default_value' => 'pending',
                    'wrapper' => [
                        'width' => '33',
                    ],
                ],
                [
                    'key' => 'field_exemption_priority',
                    'label' => 'Priority',
                    'name' => 'exemption_priority',
                    'type' => 'select',
                    'instructions' => 'Priority level for applying multiple exemptions',
                    'choices' => [
                        'high' => 'High',
                        'medium' => 'Medium',
                        'low' => 'Low',
                    ],
                    'default_value' => 'medium',
                    'wrapper' => [
                        'width' => '33',
                    ],
                ],
                [
                    'key' => 'field_exemption_reason',
                    'label' => 'Reason/Justification',
                    'name' => 'exemption_reason',
                    'type' => 'textarea',
                    'instructions' => 'Detailed reason for this exemption',
                    'required' => 1,
                    'rows' => 3,
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
                        'value' => self::EXEMPTION_POST_TYPE,
                    ],
                ],
            ],
            'menu_order' => 0,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
        ]);

        // Exemption Scope and Amount
        acf_add_local_field_group([
            'key' => 'group_exemption_scope',
            'title' => 'Exemption Scope & Amount',
            'fields' => [
                [
                    'key' => 'field_exemption_scope',
                    'label' => 'Exemption Scope',
                    'name' => 'exemption_scope',
                    'type' => 'select',
                    'instructions' => 'What fees does this exemption apply to?',
                    'required' => 1,
                    'choices' => [
                        'all_fees' => 'All Fees',
                        'specific_fees' => 'Specific Fees',
                        'fee_categories' => 'Fee Categories',
                    ],
                    'default_value' => 'specific_fees',
                    'wrapper' => [
                        'width' => '50',
                    ],
                ],
                [
                    'key' => 'field_exemption_amount_type',
                    'label' => 'Amount Type',
                    'name' => 'exemption_amount_type',
                    'type' => 'select',
                    'instructions' => 'How is the exemption amount calculated?',
                    'required' => 1,
                    'choices' => [
                        'percentage' => 'Percentage Discount',
                        'fixed_amount' => 'Fixed Amount Discount',
                        'full_exemption' => 'Full Exemption (100%)',
                    ],
                    'default_value' => 'percentage',
                    'wrapper' => [
                        'width' => '50',
                    ],
                ],
                [
                    'key' => 'field_specific_fees',
                    'label' => 'Specific Fees',
                    'name' => 'specific_fees',
                    'type' => 'post_object',
                    'instructions' => 'Select specific fees this exemption applies to',
                    'conditional_logic' => [
                        [
                            [
                                'field' => 'field_exemption_scope',
                                'operator' => '==',
                                'value' => 'specific_fees',
                            ],
                        ],
                    ],
                    'post_type' => ['sms_fees'],
                    'multiple' => 1,
                    'return_format' => 'id',
                    'ui' => 1,
                ],
                [
                    'key' => 'field_fee_categories',
                    'label' => 'Fee Categories',
                    'name' => 'fee_categories',
                    'type' => 'checkbox',
                    'instructions' => 'Select fee categories this exemption applies to',
                    'conditional_logic' => [
                        [
                            [
                                'field' => 'field_exemption_scope',
                                'operator' => '==',
                                'value' => 'fee_categories',
                            ],
                        ],
                    ],
                    'choices' => [
                        'tuition' => 'Tuition Fees',
                        'transport' => 'Transport Fees',
                        'meals' => 'Meal Fees',
                        'books' => 'Books & Materials',
                        'uniform' => 'Uniform Fees',
                        'activity' => 'Activity Fees',
                        'exam' => 'Examination Fees',
                        'registration' => 'Registration Fees',
                    ],
                    'layout' => 'horizontal',
                ],
                [
                    'key' => 'field_exemption_percentage',
                    'label' => 'Discount Percentage (%)',
                    'name' => 'exemption_percentage',
                    'type' => 'number',
                    'instructions' => 'Percentage discount (1-100)',
                    'conditional_logic' => [
                        [
                            [
                                'field' => 'field_exemption_amount_type',
                                'operator' => '==',
                                'value' => 'percentage',
                            ],
                        ],
                    ],
                    'required' => 1,
                    'min' => 1,
                    'max' => 100,
                    'default_value' => 50,
                    'wrapper' => [
                        'width' => '50',
                    ],
                ],
                [
                    'key' => 'field_exemption_fixed_amount',
                    'label' => 'Fixed Discount Amount (KES)',
                    'name' => 'exemption_fixed_amount',
                    'type' => 'number',
                    'instructions' => 'Fixed amount to discount from fees',
                    'conditional_logic' => [
                        [
                            [
                                'field' => 'field_exemption_amount_type',
                                'operator' => '==',
                                'value' => 'fixed_amount',
                            ],
                        ],
                    ],
                    'required' => 1,
                    'min' => 0,
                    'step' => 0.01,
                    'wrapper' => [
                        'width' => '50',
                    ],
                ],
                [
                    'key' => 'field_max_exemption_amount',
                    'label' => 'Maximum Exemption Amount (KES)',
                    'name' => 'max_exemption_amount',
                    'type' => 'number',
                    'instructions' => 'Maximum amount that can be exempted (optional)',
                    'min' => 0,
                    'step' => 0.01,
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
                        'value' => self::EXEMPTION_POST_TYPE,
                    ],
                ],
            ],
            'menu_order' => 1,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
        ]);

        // Validity Period and Conditions
        acf_add_local_field_group([
            'key' => 'group_exemption_validity',
            'title' => 'Validity Period & Conditions',
            'fields' => [
                [
                    'key' => 'field_exemption_start_date',
                    'label' => 'Start Date',
                    'name' => 'exemption_start_date',
                    'type' => 'date_picker',
                    'instructions' => 'When does this exemption become effective?',
                    'required' => 1,
                    'display_format' => 'd/m/Y',
                    'return_format' => 'Y-m-d',
                    'wrapper' => [
                        'width' => '33',
                    ],
                ],
                [
                    'key' => 'field_exemption_end_date',
                    'label' => 'End Date',
                    'name' => 'exemption_end_date',
                    'type' => 'date_picker',
                    'instructions' => 'When does this exemption expire? (optional for permanent exemptions)',
                    'display_format' => 'd/m/Y',
                    'return_format' => 'Y-m-d',
                    'wrapper' => [
                        'width' => '33',
                    ],
                ],
                [
                    'key' => 'field_exemption_renewable',
                    'label' => 'Renewable',
                    'name' => 'exemption_renewable',
                    'type' => 'true_false',
                    'instructions' => 'Can this exemption be renewed?',
                    'default_value' => 0,
                    'ui' => 1,
                    'wrapper' => [
                        'width' => '34',
                    ],
                ],
                [
                    'key' => 'field_renewal_conditions',
                    'label' => 'Renewal Conditions',
                    'name' => 'renewal_conditions',
                    'type' => 'textarea',
                    'instructions' => 'Conditions that must be met for renewal',
                    'conditional_logic' => [
                        [
                            [
                                'field' => 'field_exemption_renewable',
                                'operator' => '==',
                                'value' => '1',
                            ],
                        ],
                    ],
                    'rows' => 3,
                ],
                [
                    'key' => 'field_academic_requirements',
                    'label' => 'Academic Requirements',
                    'name' => 'academic_requirements',
                    'type' => 'group',
                    'instructions' => 'Academic performance requirements to maintain exemption',
                    'layout' => 'block',
                    'sub_fields' => [
                        [
                            'key' => 'field_min_grade_required',
                            'label' => 'Minimum Grade Required',
                            'name' => 'min_grade_required',
                            'type' => 'select',
                            'choices' => [
                                'none' => 'No Requirement',
                                'A' => 'Grade A',
                                'B' => 'Grade B',
                                'C' => 'Grade C',
                                'D' => 'Grade D',
                            ],
                            'default_value' => 'none',
                        ],
                        [
                            'key' => 'field_min_attendance_required',
                            'label' => 'Minimum Attendance (%)',
                            'name' => 'min_attendance_required',
                            'type' => 'number',
                            'min' => 0,
                            'max' => 100,
                            'default_value' => 0,
                        ],
                        [
                            'key' => 'field_conduct_requirements',
                            'label' => 'Conduct Requirements',
                            'name' => 'conduct_requirements',
                            'type' => 'textarea',
                            'rows' => 2,
                        ],
                    ],
                ],
            ],
            'location' => [
                [
                    [
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => self::EXEMPTION_POST_TYPE,
                    ],
                ],
            ],
            'menu_order' => 2,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
        ]);

        // Approval and Documentation
        acf_add_local_field_group([
            'key' => 'group_exemption_approval',
            'title' => 'Approval & Documentation',
            'fields' => [
                [
                    'key' => 'field_approved_by',
                    'label' => 'Approved By',
                    'name' => 'approved_by',
                    'type' => 'user',
                    'instructions' => 'User who approved this exemption',
                    'role' => ['administrator', 'sms_admin'],
                    'return_format' => 'id',
                    'wrapper' => [
                        'width' => '50',
                    ],
                ],
                [
                    'key' => 'field_approval_date',
                    'label' => 'Approval Date',
                    'name' => 'approval_date',
                    'type' => 'date_picker',
                    'instructions' => 'Date when exemption was approved',
                    'display_format' => 'd/m/Y',
                    'return_format' => 'Y-m-d',
                    'wrapper' => [
                        'width' => '50',
                    ],
                ],
                [
                    'key' => 'field_supporting_documents',
                    'label' => 'Supporting Documents',
                    'name' => 'supporting_documents',
                    'type' => 'file',
                    'instructions' => 'Upload supporting documents (applications, certificates, etc.)',
                    'return_format' => 'array',
                    'library' => 'all',
                    'multiple' => 1,
                ],
                [
                    'key' => 'field_review_notes',
                    'label' => 'Review Notes',
                    'name' => 'review_notes',
                    'type' => 'textarea',
                    'instructions' => 'Internal notes about this exemption',
                    'rows' => 4,
                ],
            ],
            'location' => [
                [
                    [
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => self::EXEMPTION_POST_TYPE,
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
     * Create a new fee exemption.
     *
     * @param array $exemption_data Exemption data
     * @return int|WP_Error Exemption post ID or error
     */
    public function create_exemption($exemption_data) {
        try {
            // Validate exemption data
            $validation_result = $this->validate_exemption_data($exemption_data);
            if (is_wp_error($validation_result)) {
                return $validation_result;
            }

            // Check for existing active exemptions
            $existing_exemptions = $this->get_student_active_exemptions($exemption_data['student_id']);
            if (!empty($existing_exemptions)) {
                // Check for conflicts
                $conflict_check = $this->check_exemption_conflicts($exemption_data, $existing_exemptions);
                if (is_wp_error($conflict_check)) {
                    return $conflict_check;
                }
            }

            // Create exemption post
            $exemption_post = [
                'post_title'   => $this->generate_exemption_title($exemption_data),
                'post_content' => sanitize_textarea_field($exemption_data['reason'] ?? ''),
                'post_status'  => 'publish',
                'post_type'    => self::EXEMPTION_POST_TYPE,
                'meta_input'   => $this->prepare_exemption_meta($exemption_data)
            ];

            $exemption_id = wp_insert_post($exemption_post);

            if (is_wp_error($exemption_id)) {
                return $exemption_id;
            }

            // Log the creation
            $this->log_activity(
                get_current_user_id(),
                'exemption_created',
                'exemption',
                $exemption_id,
                [
                    'student_id' => $exemption_data['student_id'],
                    'type' => $exemption_data['exemption_type']
                ]
            );

            // Trigger exemption created action
            do_action('sms_exemption_created', $exemption_id, $exemption_data);

            return $exemption_id;

        } catch (Exception $e) {
            return new WP_Error('exemption_creation_failed', $e->getMessage());
        }
    }

    /**
     * Get active exemptions for a student.
     *
     * @param int $student_id Student ID
     * @return array Active exemptions
     */
    public function get_student_active_exemptions($student_id) {
        $args = [
            'post_type' => self::EXEMPTION_POST_TYPE,
            'post_status' => 'publish',
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => 'exemption_student',
                    'value' => $student_id,
                    'compare' => '='
                ],
                [
                    'key' => 'exemption_status',
                    'value' => 'active',
                    'compare' => '='
                ]
            ],
            'meta_key' => 'exemption_priority',
            'orderby' => 'meta_value',
            'order' => 'DESC'
        ];

        $exemptions = get_posts($args);
        $active_exemptions = [];

        foreach ($exemptions as $exemption) {
            $exemption_data = $this->get_exemption_data($exemption->ID);
            
            // Check if exemption is still valid
            if ($this->is_exemption_valid($exemption_data)) {
                $active_exemptions[] = $exemption_data;
            }
        }

        return $active_exemptions;
    }

    /**
     * Apply exemptions to fee calculation.
     *
     * @param array $calculation Fee calculation
     * @param int   $student_id  Student ID
     * @param int   $fee_id      Fee ID
     * @return array Modified calculation
     */
    public function apply_exemptions_to_calculation($calculation, $student_id, $fee_id) {
        $exemptions = $this->get_student_active_exemptions($student_id);
        
        if (empty($exemptions)) {
            return $calculation;
        }

        $applicable_exemptions = [];
        
        foreach ($exemptions as $exemption) {
            if ($this->exemption_applies_to_fee($exemption, $fee_id)) {
                $applicable_exemptions[] = $exemption;
            }
        }

        if (empty($applicable_exemptions)) {
            return $calculation;
        }

        // Apply exemptions in priority order
        usort($applicable_exemptions, function($a, $b) {
            $priority_order = ['high' => 3, 'medium' => 2, 'low' => 1];
            return $priority_order[$b['exemption_priority']] - $priority_order[$a['exemption_priority']];
        });

        $total_exemption_amount = 0;
        $exemption_details = [];

        foreach ($applicable_exemptions as $exemption) {
            $exemption_amount = $this->calculate_exemption_amount($exemption, $calculation['base_amount']);
            
            // Check maximum exemption limit
            if (!empty($exemption['max_exemption_amount'])) {
                $exemption_amount = min($exemption_amount, floatval($exemption['max_exemption_amount']));
            }

            $total_exemption_amount += $exemption_amount;
            
            $exemption_details[] = [
                'exemption_id' => $exemption['id'],
                'type' => $exemption['exemption_type'],
                'amount' => $exemption_amount,
                'priority' => $exemption['exemption_priority']
            ];
        }

        // Ensure total exemption doesn't exceed fee amount
        $total_exemption_amount = min($total_exemption_amount, $calculation['base_amount']);

        // Update calculation
        $calculation['exemptions'] = $exemption_details;
        $calculation['total_exemption_amount'] = $total_exemption_amount;
        $calculation['total_amount'] = max(0, $calculation['base_amount'] - $total_exemption_amount);
        $calculation['amount_due'] = max(0, $calculation['total_amount'] - $calculation['amount_paid']);

        return $calculation;
    }

    /**
     * Check if exemption applies to a specific fee.
     *
     * @param array $exemption Exemption data
     * @param int   $fee_id    Fee ID
     * @return bool Whether exemption applies
     */
    private function exemption_applies_to_fee($exemption, $fee_id) {
        $scope = $exemption['exemption_scope'];

        switch ($scope) {
            case 'all_fees':
                return true;
                
            case 'specific_fees':
                $specific_fees = $exemption['specific_fees'] ?? [];
                return in_array($fee_id, $specific_fees);
                
            case 'fee_categories':
                $fee_type = get_field('fee_type', $fee_id);
                $applicable_categories = $exemption['fee_categories'] ?? [];
                return in_array($fee_type, $applicable_categories);
                
            default:
                return false;
        }
    }

    /**
     * Calculate exemption amount based on type and settings.
     *
     * @param array $exemption   Exemption data
     * @param float $base_amount Base fee amount
     * @return float Exemption amount
     */
    private function calculate_exemption_amount($exemption, $base_amount) {
        $amount_type = $exemption['exemption_amount_type'];

        switch ($amount_type) {
            case 'percentage':
                $percentage = floatval($exemption['exemption_percentage'] ?? 0);
                return ($base_amount * $percentage) / 100;
                
            case 'fixed_amount':
                return floatval($exemption['exemption_fixed_amount'] ?? 0);
                
            case 'full_exemption':
                return $base_amount;
                
            default:
                return 0;
        }
    }

    /**
     * Check if exemption is currently valid.
     *
     * @param array $exemption Exemption data
     * @return bool Whether exemption is valid
     */
    private function is_exemption_valid($exemption) {
        $current_date = current_time('Y-m-d');
        
        // Check start date
        if (!empty($exemption['exemption_start_date']) && $current_date < $exemption['exemption_start_date']) {
            return false;
        }
        
        // Check end date
        if (!empty($exemption['exemption_end_date']) && $current_date > $exemption['exemption_end_date']) {
            return false;
        }
        
        // Check status
        if ($exemption['exemption_status'] !== 'active') {
            return false;
        }
        
        // Check academic requirements if applicable
        if (!$this->meets_academic_requirements($exemption)) {
            return false;
        }
        
        return true;
    }

    /**
     * Check if student meets academic requirements for exemption.
     *
     * @param array $exemption Exemption data
     * @return bool Whether requirements are met
     */
    private function meets_academic_requirements($exemption) {
        $requirements = $exemption['academic_requirements'] ?? [];
        
        if (empty($requirements)) {
            return true; // No requirements set
        }
        
        $student_id = $exemption['exemption_student'];
        
        // Check minimum grade requirement
        if (!empty($requirements['min_grade_required']) && $requirements['min_grade_required'] !== 'none') {
            // Implementation would check student's current grades
            // For now, assume requirement is met
        }
        
        // Check minimum attendance requirement
        if (!empty($requirements['min_attendance_required']) && $requirements['min_attendance_required'] > 0) {
            // Implementation would check student's attendance percentage
            // For now, assume requirement is met
        }
        
        return true;
    }

    /**
     * Get exemption data by ID.
     *
     * @param int $exemption_id Exemption ID
     * @return array|false Exemption data or false
     */
    public function get_exemption_data($exemption_id) {
        $post = get_post($exemption_id);
        
        if (!$post || $post->post_type !== self::EXEMPTION_POST_TYPE) {
            return false;
        }
        
        $exemption_data = [
            'id' => $exemption_id,
            'title' => $post->post_title,
            'description' => $post->post_content,
            'status' => $post->post_status
        ];
        
        // Get all meta fields
        $meta_fields = [
            'exemption_student', 'exemption_type', 'exemption_status', 'exemption_priority',
            'exemption_reason', 'exemption_scope', 'exemption_amount_type', 'specific_fees',
            'fee_categories', 'exemption_percentage', 'exemption_fixed_amount', 'max_exemption_amount',
            'exemption_start_date', 'exemption_end_date', 'exemption_renewable', 'renewal_conditions',
            'academic_requirements', 'approved_by', 'approval_date', 'supporting_documents', 'review_notes'
        ];
        
        foreach ($meta_fields as $field) {
            $exemption_data[$field] = get_field($field, $exemption_id);
        }
        
        return $exemption_data;
    }

    /**
     * Validate exemption data.
     *
     * @param array $exemption_data Exemption data
     * @return bool|WP_Error True if valid, WP_Error if invalid
     */
    private function validate_exemption_data($exemption_data) {
        $errors = [];

        // Required fields
        $required_fields = ['student_id', 'exemption_type', 'exemption_scope', 'exemption_amount_type'];
        foreach ($required_fields as $field) {
            if (empty($exemption_data[$field])) {
                $errors[] = sprintf(__('%s is required.', 'school-management-system'), ucfirst(str_replace('_', ' ', $field)));
            }
        }

        // Validate student exists
        if (!empty($exemption_data['student_id'])) {
            $student = get_post($exemption_data['student_id']);
            if (!$student || $student->post_type !== 'sms_students') {
                $errors[] = __('Invalid student selected.', 'school-management-system');
            }
        }

        // Validate percentage
        if ($exemption_data['exemption_amount_type'] === 'percentage') {
            $percentage = floatval($exemption_data['exemption_percentage'] ?? 0);
            if ($percentage <= 0 || $percentage > 100) {
                $errors[] = __('Exemption percentage must be between 1 and 100.', 'school-management-system');
            }
        }

        // Validate fixed amount
        if ($exemption_data['exemption_amount_type'] === 'fixed_amount') {
            $amount = floatval($exemption_data['exemption_fixed_amount'] ?? 0);
            if ($amount <= 0) {
                $errors[] = __('Exemption fixed amount must be greater than 0.', 'school-management-system');
            }
        }

        // Validate date range
        if (!empty($exemption_data['start_date']) && !empty($exemption_data['end_date'])) {
            if (strtotime($exemption_data['start_date']) >= strtotime($exemption_data['end_date'])) {
                $errors[] = __('End date must be after start date.', 'school-management-system');
            }
        }

        if (!empty($errors)) {
            return new WP_Error('validation_failed', implode(' ', $errors));
        }

        return true;
    }

    /**
     * Check for exemption conflicts.
     *
     * @param array $new_exemption      New exemption data
     * @param array $existing_exemptions Existing exemptions
     * @return bool|WP_Error True if no conflicts, WP_Error if conflicts
     */
    private function check_exemption_conflicts($new_exemption, $existing_exemptions) {
        // Implementation would check for overlapping exemptions
        // For now, allow multiple exemptions
        return true;
    }

    /**
     * Generate exemption title.
     *
     * @param array $exemption_data Exemption data
     * @return string Generated title
     */
    private function generate_exemption_title($exemption_data) {
        $student_name = get_the_title($exemption_data['student_id']);
        $type = ucfirst(str_replace('_', ' ', $exemption_data['exemption_type']));
        
        return sprintf('%s - %s', $student_name, $type);
    }

    /**
     * Prepare exemption meta data.
     *
     * @param array $exemption_data Exemption data
     * @return array Meta data
     */
    private function prepare_exemption_meta($exemption_data) {
        $meta_data = [];
        
        $meta_fields = [
            'exemption_student', 'exemption_type', 'exemption_status', 'exemption_priority',
            'exemption_reason', 'exemption_scope', 'exemption_amount_type', 'specific_fees',
            'fee_categories', 'exemption_percentage', 'exemption_fixed_amount', 'max_exemption_amount',
            'exemption_start_date', 'exemption_end_date', 'exemption_renewable', 'renewal_conditions',
            'academic_requirements', 'approved_by', 'approval_date', 'supporting_documents', 'review_notes'
        ];
        
        foreach ($meta_fields as $field) {
            if (isset($exemption_data[$field])) {
                $meta_data[$field] = $exemption_data[$field];
            }
        }
        
        return $meta_data;
    }

    /**
     * Add custom columns to exemptions list.
     *
     * @param array $columns Existing columns
     * @return array Modified columns
     */
    public function add_exemption_columns($columns) {
        $new_columns = [];
        
        if (isset($columns['cb'])) {
            $new_columns['cb'] = $columns['cb'];
        }
        
        $new_columns['student'] = __('Student', 'school-management-system');
        $new_columns['exemption_type'] = __('Type', 'school-management-system');
        $new_columns['amount'] = __('Amount', 'school-management-system');
        $new_columns['status'] = __('Status', 'school-management-system');
        $new_columns['validity'] = __('Valid Period', 'school-management-system');
        $new_columns['priority'] = __('Priority', 'school-management-system');
        
        if (isset($columns['date'])) {
            $new_columns['date'] = $columns['date'];
        }
        
        return $new_columns;
    }

    /**
     * Populate custom columns.
     *
     * @param string $column  Column name
     * @param int    $post_id Post ID
     */
    public function populate_exemption_columns($column, $post_id) {
        switch ($column) {
            case 'student':
                $student_id = get_field('exemption_student', $post_id);
                if ($student_id) {
                    $student_name = get_the_title($student_id);
                    echo '<a href="' . get_edit_post_link($student_id) . '">' . esc_html($student_name) . '</a>';
                } else {
                    echo '—';
                }
                break;
                
            case 'exemption_type':
                $type = get_field('exemption_type', $post_id);
                if ($type) {
                    echo '<span class="exemption-type-' . esc_attr($type) . '">' . 
                         esc_html(ucfirst(str_replace('_', ' ', $type))) . '</span>';
                } else {
                    echo '—';
                }
                break;
                
            case 'amount':
                $amount_type = get_field('exemption_amount_type', $post_id);
                if ($amount_type === 'percentage') {
                    $percentage = get_field('exemption_percentage', $post_id);
                    echo $percentage ? esc_html($percentage . '%') : '—';
                } elseif ($amount_type === 'fixed_amount') {
                    $amount = get_field('exemption_fixed_amount', $post_id);
                    echo $amount ? $this->format_currency($amount) : '—';
                } elseif ($amount_type === 'full_exemption') {
                    echo __('100%', 'school-management-system');
                } else {
                    echo '—';
                }
                break;
                
            case 'status':
                $status = get_field('exemption_status', $post_id);
                if ($status) {
                    echo '<span class="status-' . esc_attr($status) . '">' . esc_html(ucfirst($status)) . '</span>';
                } else {
                    echo '—';
                }
                break;
                
            case 'validity':
                $start_date = get_field('exemption_start_date', $post_id);
                $end_date = get_field('exemption_end_date', $post_id);
                
                if ($start_date) {
                    echo esc_html(date('d/m/Y', strtotime($start_date)));
                    if ($end_date) {
                        echo ' - ' . esc_html(date('d/m/Y', strtotime($end_date)));
                    } else {
                        echo ' - ' . __('Permanent', 'school-management-system');
                    }
                } else {
                    echo '—';
                }
                break;
                
            case 'priority':
                $priority = get_field('exemption_priority', $post_id);
                if ($priority) {
                    echo '<span class="priority-' . esc_attr($priority) . '">' . esc_html(ucfirst($priority)) . '</span>';
                } else {
                    echo '—';
                }
                break;
        }
    }

    /**
     * Validate exemption on save.
     *
     * @param int     $post_id Post ID
     * @param WP_Post $post    Post object
     * @param bool    $update  Whether this is an update
     */
    public function validate_exemption($post_id, $post, $update) {
        // Skip validation for auto-saves and revisions
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }

        // Additional validation logic can be added here
    }

    /**
     * Process exemption renewals.
     */
    public function process_exemption_renewals() {
        // Get exemptions expiring soon
        $expiring_exemptions = $this->get_expiring_exemptions();
        
        foreach ($expiring_exemptions as $exemption) {
            if ($exemption['exemption_renewable']) {
                // Check renewal conditions
                if ($this->check_renewal_eligibility($exemption)) {
                    // Send renewal notification
                    do_action('sms_exemption_renewal_due', $exemption['id']);
                }
            }
        }
    }

    /**
     * Get exemptions expiring within specified days.
     *
     * @param int $days Days ahead to check
     * @return array Expiring exemptions
     */
    private function get_expiring_exemptions($days = 30) {
        $future_date = date('Y-m-d', strtotime("+{$days} days"));
        
        $args = [
            'post_type' => self::EXEMPTION_POST_TYPE,
            'post_status' => 'publish',
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => 'exemption_status',
                    'value' => 'active',
                    'compare' => '='
                ],
                [
                    'key' => 'exemption_end_date',
                    'value' => $future_date,
                    'compare' => '<='
                ]
            ]
        ];
        
        $posts = get_posts($args);
        $exemptions = [];
        
        foreach ($posts as $post) {
            $exemptions[] = $this->get_exemption_data($post->ID);
        }
        
        return $exemptions;
    }

    /**
     * Check if exemption is eligible for renewal.
     *
     * @param array $exemption Exemption data
     * @return bool Eligibility
     */
    private function check_renewal_eligibility($exemption) {
        // Check academic requirements
        if (!$this->meets_academic_requirements($exemption)) {
            return false;
        }
        
        // Additional renewal checks can be added here
        
        return true;
    }
}