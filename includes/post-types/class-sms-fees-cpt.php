<?php
/**
 * Fees Custom Post Type
 *
 * Handles the registration and management of the fees custom post type
 * with fee structures, installments, and penalty calculations.
 *
 * @package    School_Management_System
 * @subpackage School_Management_System/includes/post-types
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Fees Custom Post Type Class
 */
class SMS_Fees_CPT extends SMS_Base {

    /**
     * The post type name.
     */
    const POST_TYPE = 'sms_fees';

    /**
     * Initialize the class and set its properties.
     */
    public function __construct() {
        parent::__construct();
        
        // Register the custom post type
        add_action('sms_register_post_types', [$this, 'register_post_type']);
        
        // Add ACF field groups
        add_action('acf/init', [$this, 'register_acf_fields']);
        
        // Add custom columns to admin list
        add_filter('manage_' . self::POST_TYPE . '_posts_columns', [$this, 'add_custom_columns']);
        add_action('manage_' . self::POST_TYPE . '_posts_custom_column', [$this, 'populate_custom_columns'], 10, 2);
        
        // Make columns sortable
        add_filter('manage_edit-' . self::POST_TYPE . '_sortable_columns', [$this, 'make_columns_sortable']);
        
        // Add meta boxes
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        
        // Save fee data and handle calculations
        add_action('save_post_' . self::POST_TYPE, [$this, 'save_fee_data'], 10, 3);
    }

    /**
     * Register the fees custom post type.
     */
    public function register_post_type() {
        $labels = [
            'name'                  => _x('Fees', 'Post Type General Name', 'school-management-system'),
            'singular_name'         => _x('Fee', 'Post Type Singular Name', 'school-management-system'),
            'menu_name'             => __('Fees', 'school-management-system'),
            'name_admin_bar'        => __('Fee', 'school-management-system'),
            'archives'              => __('Fee Archives', 'school-management-system'),
            'attributes'            => __('Fee Attributes', 'school-management-system'),
            'parent_item_colon'     => __('Parent Fee:', 'school-management-system'),
            'all_items'             => __('All Fees', 'school-management-system'),
            'add_new_item'          => __('Add New Fee', 'school-management-system'),
            'add_new'               => __('Add New', 'school-management-system'),
            'new_item'              => __('New Fee', 'school-management-system'),
            'edit_item'             => __('Edit Fee', 'school-management-system'),
            'update_item'           => __('Update Fee', 'school-management-system'),
            'view_item'             => __('View Fee', 'school-management-system'),
            'view_items'            => __('View Fees', 'school-management-system'),
            'search_items'          => __('Search Fee', 'school-management-system'),
            'not_found'             => __('Not found', 'school-management-system'),
            'not_found_in_trash'    => __('Not found in Trash', 'school-management-system'),
            'featured_image'        => __('Fee Image', 'school-management-system'),
            'set_featured_image'    => __('Set fee image', 'school-management-system'),
            'remove_featured_image' => __('Remove fee image', 'school-management-system'),
            'use_featured_image'    => __('Use as fee image', 'school-management-system'),
            'insert_into_item'      => __('Insert into fee', 'school-management-system'),
            'uploaded_to_this_item' => __('Uploaded to this fee', 'school-management-system'),
            'items_list'            => __('Fees list', 'school-management-system'),
            'items_list_navigation' => __('Fees list navigation', 'school-management-system'),
            'filter_items_list'     => __('Filter fees list', 'school-management-system'),
        ];

        $args = [
            'label'                 => __('Fee', 'school-management-system'),
            'description'           => __('Fee structures and management', 'school-management-system'),
            'labels'                => $labels,
            'supports'              => ['title', 'editor', 'revisions'],
            'taxonomies'            => ['sms_academic_years', 'sms_terms'],
            'hierarchical'          => false,
            'public'                => false,
            'show_ui'               => true,
            'show_in_menu'          => 'sms-dashboard',
            'menu_position'         => 7,
            'menu_icon'             => 'dashicons-money-alt',
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
            'rest_base'             => 'fees',
            'rest_controller_class' => 'WP_REST_Posts_Controller',
        ];

        register_post_type(self::POST_TYPE, $args);
    }

    /**
     * Register ACF field groups for fees.
     */
    public function register_acf_fields() {
        if (!function_exists('acf_add_local_field_group')) {
            return;
        }

        // Basic Fee Information Field Group
        acf_add_local_field_group([
            'key' => 'group_fee_basic_info',
            'title' => 'Fee Information',
            'fields' => [
                [
                    'key' => 'field_fee_name',
                    'label' => 'Fee Name',
                    'name' => 'fee_name',
                    'type' => 'text',
                    'instructions' => 'Name of the fee (e.g., Tuition Fee, Transport Fee)',
                    'required' => 1,
                    'wrapper' => [
                        'width' => '50',
                    ],
                ],
                [
                    'key' => 'field_fee_code',
                    'label' => 'Fee Code',
                    'name' => 'fee_code',
                    'type' => 'text',
                    'instructions' => 'Short code for the fee (e.g., TUI, TRA, MEA)',
                    'required' => 1,
                    'maxlength' => 10,
                    'wrapper' => [
                        'width' => '50',
                    ],
                ],
                [
                    'key' => 'field_fee_type',
                    'label' => 'Fee Type',
                    'name' => 'fee_type',
                    'type' => 'select',
                    'instructions' => 'Category of this fee',
                    'required' => 1,
                    'choices' => [
                        'tuition' => 'Tuition Fee',
                        'transport' => 'Transport Fee',
                        'meals' => 'Meals Fee',
                        'books' => 'Books & Materials',
                        'uniform' => 'Uniform Fee',
                        'activity' => 'Activity Fee',
                        'exam' => 'Examination Fee',
                        'registration' => 'Registration Fee',
                        'other' => 'Other Fee',
                    ],
                    'default_value' => 'tuition',
                    'wrapper' => [
                        'width' => '33',
                    ],
                ],
                [
                    'key' => 'field_fee_amount',
                    'label' => 'Fee Amount (KES)',
                    'name' => 'fee_amount',
                    'type' => 'number',
                    'instructions' => 'Base amount for this fee',
                    'required' => 1,
                    'min' => 0,
                    'step' => 0.01,
                    'wrapper' => [
                        'width' => '33',
                    ],
                ],
                [
                    'key' => 'field_fee_status',
                    'label' => 'Fee Status',
                    'name' => 'fee_status',
                    'type' => 'select',
                    'instructions' => 'Current status of this fee',
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

        // Fee Structure Field Group
        acf_add_local_field_group([
            'key' => 'group_fee_structure',
            'title' => 'Fee Structure & Payment Options',
            'fields' => [
                [
                    'key' => 'field_payment_frequency',
                    'label' => 'Payment Frequency',
                    'name' => 'payment_frequency',
                    'type' => 'select',
                    'instructions' => 'How often this fee should be paid',
                    'required' => 1,
                    'choices' => [
                        'one_time' => 'One Time',
                        'termly' => 'Termly',
                        'monthly' => 'Monthly',
                        'yearly' => 'Yearly',
                    ],
                    'default_value' => 'termly',
                    'wrapper' => [
                        'width' => '50',
                    ],
                ],
                [
                    'key' => 'field_due_date_type',
                    'label' => 'Due Date Type',
                    'name' => 'due_date_type',
                    'type' => 'select',
                    'instructions' => 'How due dates are determined',
                    'required' => 1,
                    'choices' => [
                        'fixed' => 'Fixed Date',
                        'term_start' => 'Days after Term Start',
                        'enrollment' => 'Days after Enrollment',
                    ],
                    'default_value' => 'term_start',
                    'wrapper' => [
                        'width' => '50',
                    ],
                ],
                [
                    'key' => 'field_due_date_fixed',
                    'label' => 'Fixed Due Date',
                    'name' => 'due_date_fixed',
                    'type' => 'date_picker',
                    'instructions' => 'Fixed due date for this fee',
                    'conditional_logic' => [
                        [
                            [
                                'field' => 'field_due_date_type',
                                'operator' => '==',
                                'value' => 'fixed',
                            ],
                        ],
                    ],
                    'display_format' => 'd/m/Y',
                    'return_format' => 'Y-m-d',
                    'wrapper' => [
                        'width' => '50',
                    ],
                ],
                [
                    'key' => 'field_due_date_days',
                    'label' => 'Days After',
                    'name' => 'due_date_days',
                    'type' => 'number',
                    'instructions' => 'Number of days after the reference date',
                    'conditional_logic' => [
                        [
                            [
                                'field' => 'field_due_date_type',
                                'operator' => '!=',
                                'value' => 'fixed',
                            ],
                        ],
                    ],
                    'default_value' => 30,
                    'min' => 0,
                    'max' => 365,
                    'wrapper' => [
                        'width' => '50',
                    ],
                ],
                [
                    'key' => 'field_installment_options',
                    'label' => 'Installment Options',
                    'name' => 'installment_options',
                    'type' => 'repeater',
                    'instructions' => 'Define installment payment options',
                    'layout' => 'table',
                    'button_label' => 'Add Installment',
                    'sub_fields' => [
                        [
                            'key' => 'field_installment_name',
                            'label' => 'Installment Name',
                            'name' => 'installment_name',
                            'type' => 'text',
                            'required' => 1,
                            'placeholder' => '1st Installment',
                        ],
                        [
                            'key' => 'field_installment_percentage',
                            'label' => 'Percentage (%)',
                            'name' => 'installment_percentage',
                            'type' => 'number',
                            'required' => 1,
                            'min' => 1,
                            'max' => 100,
                            'default_value' => 50,
                        ],
                        [
                            'key' => 'field_installment_due_days',
                            'label' => 'Due Days After',
                            'name' => 'installment_due_days',
                            'type' => 'number',
                            'required' => 1,
                            'min' => 0,
                            'default_value' => 0,
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

        // Penalty & Discount Field Group
        acf_add_local_field_group([
            'key' => 'group_fee_penalties_discounts',
            'title' => 'Penalties & Discounts',
            'fields' => [
                [
                    'key' => 'field_late_payment_penalty',
                    'label' => 'Late Payment Penalty',
                    'name' => 'late_payment_penalty',
                    'type' => 'group',
                    'instructions' => 'Configure late payment penalties',
                    'layout' => 'block',
                    'sub_fields' => [
                        [
                            'key' => 'field_penalty_enabled',
                            'label' => 'Enable Penalties',
                            'name' => 'penalty_enabled',
                            'type' => 'true_false',
                            'default_value' => 1,
                            'ui' => 1,
                        ],
                        [
                            'key' => 'field_penalty_type',
                            'label' => 'Penalty Type',
                            'name' => 'penalty_type',
                            'type' => 'select',
                            'conditional_logic' => [
                                [
                                    [
                                        'field' => 'field_penalty_enabled',
                                        'operator' => '==',
                                        'value' => '1',
                                    ],
                                ],
                            ],
                            'choices' => [
                                'fixed' => 'Fixed Amount',
                                'percentage' => 'Percentage of Fee',
                                'daily' => 'Daily Penalty',
                            ],
                            'default_value' => 'percentage',
                        ],
                        [
                            'key' => 'field_penalty_amount',
                            'label' => 'Penalty Amount/Percentage',
                            'name' => 'penalty_amount',
                            'type' => 'number',
                            'conditional_logic' => [
                                [
                                    [
                                        'field' => 'field_penalty_enabled',
                                        'operator' => '==',
                                        'value' => '1',
                                    ],
                                ],
                            ],
                            'min' => 0,
                            'step' => 0.01,
                            'default_value' => 5,
                        ],
                        [
                            'key' => 'field_penalty_grace_days',
                            'label' => 'Grace Period (Days)',
                            'name' => 'penalty_grace_days',
                            'type' => 'number',
                            'conditional_logic' => [
                                [
                                    [
                                        'field' => 'field_penalty_enabled',
                                        'operator' => '==',
                                        'value' => '1',
                                    ],
                                ],
                            ],
                            'instructions' => 'Days after due date before penalty applies',
                            'min' => 0,
                            'default_value' => 7,
                        ],
                    ],
                ],
                [
                    'key' => 'field_discounts',
                    'label' => 'Available Discounts',
                    'name' => 'discounts',
                    'type' => 'repeater',
                    'instructions' => 'Define available discounts for this fee',
                    'layout' => 'table',
                    'button_label' => 'Add Discount',
                    'sub_fields' => [
                        [
                            'key' => 'field_discount_name',
                            'label' => 'Discount Name',
                            'name' => 'discount_name',
                            'type' => 'text',
                            'required' => 1,
                            'placeholder' => 'Early Payment Discount',
                        ],
                        [
                            'key' => 'field_discount_type',
                            'label' => 'Discount Type',
                            'name' => 'discount_type',
                            'type' => 'select',
                            'choices' => [
                                'fixed' => 'Fixed Amount',
                                'percentage' => 'Percentage',
                            ],
                            'default_value' => 'percentage',
                        ],
                        [
                            'key' => 'field_discount_value',
                            'label' => 'Discount Value',
                            'name' => 'discount_value',
                            'type' => 'number',
                            'required' => 1,
                            'min' => 0,
                            'step' => 0.01,
                        ],
                        [
                            'key' => 'field_discount_condition',
                            'label' => 'Condition',
                            'name' => 'discount_condition',
                            'type' => 'select',
                            'choices' => [
                                'early_payment' => 'Early Payment',
                                'sibling' => 'Sibling Discount',
                                'scholarship' => 'Scholarship',
                                'staff_child' => 'Staff Child',
                                'other' => 'Other',
                            ],
                            'default_value' => 'early_payment',
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
            'menu_order' => 2,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
        ]);

        // Applicability Field Group
        acf_add_local_field_group([
            'key' => 'group_fee_applicability',
            'title' => 'Fee Applicability',
            'fields' => [
                [
                    'key' => 'field_applicable_grades',
                    'label' => 'Applicable Grades',
                    'name' => 'applicable_grades',
                    'type' => 'taxonomy',
                    'taxonomy' => 'sms_grades',
                    'field_type' => 'multi_select',
                    'instructions' => 'Select grades this fee applies to (leave empty for all grades)',
                    'multiple' => 1,
                    'wrapper' => [
                        'width' => '50',
                    ],
                ],
                [
                    'key' => 'field_applicable_classes',
                    'label' => 'Applicable Classes',
                    'name' => 'applicable_classes',
                    'type' => 'post_object',
                    'post_type' => ['sms_classes'],
                    'instructions' => 'Select specific classes this fee applies to (optional)',
                    'multiple' => 1,
                    'return_format' => 'id',
                    'ui' => 1,
                    'wrapper' => [
                        'width' => '50',
                    ],
                ],
                [
                    'key' => 'field_mandatory',
                    'label' => 'Mandatory Fee',
                    'name' => 'mandatory',
                    'type' => 'true_false',
                    'instructions' => 'Is this fee mandatory for all applicable students?',
                    'default_value' => 1,
                    'ui' => 1,
                    'wrapper' => [
                        'width' => '50',
                    ],
                ],
                [
                    'key' => 'field_auto_generate_invoices',
                    'label' => 'Auto-Generate Invoices',
                    'name' => 'auto_generate_invoices',
                    'type' => 'true_false',
                    'instructions' => 'Automatically generate invoices for this fee?',
                    'default_value' => 1,
                    'ui' => 1,
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
            'menu_order' => 3,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
        ]);
    }

    /**
     * Add custom columns to the fees admin list.
     */
    public function add_custom_columns($columns) {
        $new_columns = [];
        
        // Keep the checkbox
        if (isset($columns['cb'])) {
            $new_columns['cb'] = $columns['cb'];
        }
        
        // Add custom columns
        $new_columns['fee_name'] = __('Fee Name', 'school-management-system');
        $new_columns['fee_code'] = __('Code', 'school-management-system');
        $new_columns['fee_type'] = __('Type', 'school-management-system');
        $new_columns['amount'] = __('Amount (KES)', 'school-management-system');
        $new_columns['frequency'] = __('Frequency', 'school-management-system');
        $new_columns['status'] = __('Status', 'school-management-system');
        $new_columns['mandatory'] = __('Mandatory', 'school-management-system');
        
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
            case 'fee_name':
                $fee_name = get_field('fee_name', $post_id);
                echo $fee_name ? esc_html($fee_name) : '—';
                break;
                
            case 'fee_code':
                $fee_code = get_field('fee_code', $post_id);
                echo $fee_code ? '<code>' . esc_html($fee_code) . '</code>' : '—';
                break;
                
            case 'fee_type':
                $fee_type = get_field('fee_type', $post_id);
                if ($fee_type) {
                    $types = [
                        'tuition' => 'Tuition',
                        'transport' => 'Transport',
                        'meals' => 'Meals',
                        'books' => 'Books',
                        'uniform' => 'Uniform',
                        'activity' => 'Activity',
                        'exam' => 'Exam',
                        'registration' => 'Registration',
                        'other' => 'Other',
                    ];
                    $type_class = 'fee-type-' . $fee_type;
                    echo '<span class="' . esc_attr($type_class) . '">' . 
                         esc_html($types[$fee_type] ?? ucfirst($fee_type)) . '</span>';
                } else {
                    echo '—';
                }
                break;
                
            case 'amount':
                $amount = get_field('fee_amount', $post_id);
                echo $amount ? $this->format_currency($amount) : '—';
                break;
                
            case 'frequency':
                $frequency = get_field('payment_frequency', $post_id);
                if ($frequency) {
                    $frequencies = [
                        'one_time' => 'One Time',
                        'termly' => 'Termly',
                        'monthly' => 'Monthly',
                        'yearly' => 'Yearly',
                    ];
                    echo esc_html($frequencies[$frequency] ?? ucfirst($frequency));
                } else {
                    echo '—';
                }
                break;
                
            case 'status':
                $status = get_field('fee_status', $post_id);
                if ($status) {
                    $status_class = 'status-' . $status;
                    echo '<span class="' . esc_attr($status_class) . '">' . esc_html(ucfirst($status)) . '</span>';
                } else {
                    echo '—';
                }
                break;
                
            case 'mandatory':
                $mandatory = get_field('mandatory', $post_id);
                if ($mandatory) {
                    echo '<span class="mandatory-yes">✓ Yes</span>';
                } else {
                    echo '<span class="mandatory-no">— No</span>';
                }
                break;
        }
    }

    /**
     * Make custom columns sortable.
     */
    public function make_columns_sortable($columns) {
        $columns['fee_name'] = 'fee_name';
        $columns['fee_code'] = 'fee_code';
        $columns['amount'] = 'fee_amount';
        $columns['status'] = 'fee_status';
        
        return $columns;
    }

    /**
     * Add meta boxes.
     */
    public function add_meta_boxes() {
        add_meta_box(
            'fee-summary',
            __('Fee Summary', 'school-management-system'),
            [$this, 'render_fee_summary_meta_box'],
            self::POST_TYPE,
            'side',
            'high'
        );
    }

    /**
     * Render fee summary meta box.
     */
    public function render_fee_summary_meta_box($post) {
        $fee_name = get_field('fee_name', $post->ID);
        $fee_code = get_field('fee_code', $post->ID);
        $amount = get_field('fee_amount', $post->ID);
        $frequency = get_field('payment_frequency', $post->ID);
        $mandatory = get_field('mandatory', $post->ID);
        
        echo '<div class="fee-summary-stats">';
        
        if ($fee_name) {
            echo '<p><strong>' . __('Fee Name:', 'school-management-system') . '</strong><br>';
            echo esc_html($fee_name) . '</p>';
        }
        
        if ($fee_code) {
            echo '<p><strong>' . __('Fee Code:', 'school-management-system') . '</strong><br>';
            echo '<code>' . esc_html($fee_code) . '</code></p>';
        }
        
        if ($amount) {
            echo '<p><strong>' . __('Amount:', 'school-management-system') . '</strong><br>';
            echo '<span class="fee-amount">' . $this->format_currency($amount) . '</span></p>';
        }
        
        if ($frequency) {
            $frequencies = [
                'one_time' => 'One Time',
                'termly' => 'Termly',
                'monthly' => 'Monthly',
                'yearly' => 'Yearly',
            ];
            echo '<p><strong>' . __('Frequency:', 'school-management-system') . '</strong><br>';
            echo esc_html($frequencies[$frequency] ?? ucfirst($frequency)) . '</p>';
        }
        
        echo '<p><strong>' . __('Mandatory:', 'school-management-system') . '</strong><br>';
        if ($mandatory) {
            echo '<span class="mandatory-yes">✓ Yes</span>';
        } else {
            echo '<span class="mandatory-no">— No</span>';
        }
        echo '</p>';
        
        echo '</div>';
        
        // Add some basic styling
        echo '<style>
            .fee-summary-stats p {
                margin: 12px 0;
                padding-bottom: 8px;
                border-bottom: 1px solid #eee;
            }
            .fee-summary-stats p:last-child {
                border-bottom: none;
            }
            .fee-amount {
                font-size: 16px;
                font-weight: bold;
                color: #0073aa;
            }
            .mandatory-yes {
                color: #46b450;
                font-weight: bold;
            }
            .mandatory-no {
                color: #999;
            }
        </style>';
    }

    /**
     * Save fee data and handle calculations.
     */
    public function save_fee_data($post_id, $post, $update) {
        // Skip if this is an autosave or revision
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }

        // Log fee changes
        if ($update) {
            $old_amount = get_post_meta($post_id, '_previous_amount', true);
            $new_amount = get_field('fee_amount', $post_id);
            
            if ($old_amount && $old_amount != $new_amount) {
                $this->log("Fee amount changed from {$old_amount} to {$new_amount} for fee ID {$post_id}");
            }
            
            update_post_meta($post_id, '_previous_amount', $new_amount);
        }
    }

    /**
     * Calculate fee with penalties and discounts.
     */
    public function calculate_fee_total($fee_id, $student_id = null, $due_date = null) {
        $base_amount = get_field('fee_amount', $fee_id);
        if (!$base_amount) {
            return 0;
        }
        
        $total = $base_amount;
        
        // Apply discounts if student provided
        if ($student_id) {
            $discounts = get_field('discounts', $fee_id);
            if ($discounts && is_array($discounts)) {
                foreach ($discounts as $discount) {
                    if ($this->is_discount_applicable($discount, $student_id)) {
                        if ($discount['discount_type'] === 'percentage') {
                            $total -= ($base_amount * $discount['discount_value'] / 100);
                        } else {
                            $total -= $discount['discount_value'];
                        }
                    }
                }
            }
        }
        
        // Apply penalties if due date provided and passed
        if ($due_date && strtotime($due_date) < time()) {
            $penalty_config = get_field('late_payment_penalty', $fee_id);
            if ($penalty_config && $penalty_config['penalty_enabled']) {
                $days_overdue = floor((time() - strtotime($due_date)) / (24 * 60 * 60));
                $grace_days = $penalty_config['penalty_grace_days'] ?? 0;
                
                if ($days_overdue > $grace_days) {
                    $penalty_amount = $this->calculate_penalty($penalty_config, $base_amount, $days_overdue - $grace_days);
                    $total += $penalty_amount;
                }
            }
        }
        
        return max(0, $total); // Ensure total is not negative
    }

    /**
     * Check if discount is applicable to student.
     */
    private function is_discount_applicable($discount, $student_id) {
        // This would contain logic to check discount conditions
        // For now, return true for basic implementation
        return true;
    }

    /**
     * Calculate penalty amount.
     */
    private function calculate_penalty($penalty_config, $base_amount, $overdue_days) {
        $penalty_type = $penalty_config['penalty_type'];
        $penalty_amount = $penalty_config['penalty_amount'];
        
        switch ($penalty_type) {
            case 'fixed':
                return $penalty_amount;
                
            case 'percentage':
                return ($base_amount * $penalty_amount / 100);
                
            case 'daily':
                return ($penalty_amount * $overdue_days);
                
            default:
                return 0;
        }
    }

    /**
     * Get fees by type.
     */
    public static function get_fees_by_type($fee_type = 'tuition') {
        $args = [
            'post_type' => self::POST_TYPE,
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => 'fee_type',
                    'value' => $fee_type,
                    'compare' => '='
                ],
                [
                    'key' => 'fee_status',
                    'value' => 'active',
                    'compare' => '='
                ]
            ]
        ];
        
        return new WP_Query($args);
    }

    /**
     * Get active fees.
     */
    public static function get_active_fees() {
        $args = [
            'post_type' => self::POST_TYPE,
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => 'fee_status',
                    'value' => 'active',
                    'compare' => '='
                ]
            ]
        ];
        
        return new WP_Query($args);
    }

    /**
     * Get mandatory fees.
     */
    public static function get_mandatory_fees() {
        $args = [
            'post_type' => self::POST_TYPE,
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => 'mandatory',
                    'value' => '1',
                    'compare' => '='
                ],
                [
                    'key' => 'fee_status',
                    'value' => 'active',
                    'compare' => '='
                ]
            ]
        ];
        
        return new WP_Query($args);
    }
}

// Initialize the class
new SMS_Fees_CPT();