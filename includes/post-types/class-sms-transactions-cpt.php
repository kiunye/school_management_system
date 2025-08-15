<?php
/**
 * Transactions Custom Post Type
 *
 * Handles the registration and management of the transactions custom post type
 * with payment tracking, gateway integration, and transaction management.
 *
 * @package    School_Management_System
 * @subpackage School_Management_System/includes/post-types
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Transactions Custom Post Type Class
 */
class SMS_Transactions_CPT extends SMS_Base {

    /**
     * The post type name.
     */
    const POST_TYPE = 'sms_transactions';

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
        
        // Handle transaction number generation
        add_action('save_post_' . self::POST_TYPE, [$this, 'generate_transaction_number'], 10, 3);
        
        // Add custom actions
        add_filter('post_row_actions', [$this, 'add_custom_row_actions'], 10, 2);
    }

    /**
     * Register the transactions custom post type.
     */
    public function register_post_type() {
        $labels = [
            'name'                  => _x('Transactions', 'Post Type General Name', 'school-management-system'),
            'singular_name'         => _x('Transaction', 'Post Type Singular Name', 'school-management-system'),
            'menu_name'             => __('Transactions', 'school-management-system'),
            'name_admin_bar'        => __('Transaction', 'school-management-system'),
            'archives'              => __('Transaction Archives', 'school-management-system'),
            'attributes'            => __('Transaction Attributes', 'school-management-system'),
            'parent_item_colon'     => __('Parent Transaction:', 'school-management-system'),
            'all_items'             => __('All Transactions', 'school-management-system'),
            'add_new_item'          => __('Add New Transaction', 'school-management-system'),
            'add_new'               => __('Add New', 'school-management-system'),
            'new_item'              => __('New Transaction', 'school-management-system'),
            'edit_item'             => __('Edit Transaction', 'school-management-system'),
            'update_item'           => __('Update Transaction', 'school-management-system'),
            'view_item'             => __('View Transaction', 'school-management-system'),
            'view_items'            => __('View Transactions', 'school-management-system'),
            'search_items'          => __('Search Transaction', 'school-management-system'),
            'not_found'             => __('Not found', 'school-management-system'),
            'not_found_in_trash'    => __('Not found in Trash', 'school-management-system'),
            'featured_image'        => __('Transaction Image', 'school-management-system'),
            'set_featured_image'    => __('Set transaction image', 'school-management-system'),
            'remove_featured_image' => __('Remove transaction image', 'school-management-system'),
            'use_featured_image'    => __('Use as transaction image', 'school-management-system'),
            'insert_into_item'      => __('Insert into transaction', 'school-management-system'),
            'uploaded_to_this_item' => __('Uploaded to this transaction', 'school-management-system'),
            'items_list'            => __('Transactions list', 'school-management-system'),
            'items_list_navigation' => __('Transactions list navigation', 'school-management-system'),
            'filter_items_list'     => __('Filter transactions list', 'school-management-system'),
        ];

        $args = [
            'label'                 => __('Transaction', 'school-management-system'),
            'description'           => __('Payment transactions and financial records', 'school-management-system'),
            'labels'                => $labels,
            'supports'              => ['title', 'editor', 'revisions'],
            'taxonomies'            => ['sms_academic_years', 'sms_terms'],
            'hierarchical'          => false,
            'public'                => false,
            'show_ui'               => true,
            'show_in_menu'          => 'sms-dashboard',
            'menu_position'         => 9,
            'menu_icon'             => 'dashicons-money',
            'show_in_admin_bar'     => true,
            'show_in_nav_menus'     => false,
            'can_export'            => true,
            'has_archive'           => false,
            'exclude_from_search'   => true,
            'publicly_queryable'    => false,
            'capability_type'       => 'post',
            'capabilities'          => [
                'edit_post'          => 'manage_transactions',
                'read_post'          => 'manage_transactions',
                'delete_post'        => 'manage_transactions',
                'edit_posts'         => 'manage_transactions',
                'edit_others_posts'  => 'manage_transactions',
                'delete_posts'       => 'manage_transactions',
                'publish_posts'      => 'manage_transactions',
                'read_private_posts' => 'manage_transactions',
            ],
            'show_in_rest'          => true,
            'rest_base'             => 'transactions',
            'rest_controller_class' => 'WP_REST_Posts_Controller',
        ];

        register_post_type(self::POST_TYPE, $args);
    }

    /**
     * Register ACF field groups for transactions.
     */
    public function register_acf_fields() {
        if (!function_exists('acf_add_local_field_group')) {
            return;
        }

        // Basic Transaction Information Field Group
        acf_add_local_field_group([
            'key' => 'group_transaction_basic_info',
            'title' => 'Transaction Information',
            'fields' => [
                [
                    'key' => 'field_transaction_number',
                    'label' => 'Transaction Number',
                    'name' => 'transaction_number',
                    'type' => 'text',
                    'instructions' => 'Unique transaction number (auto-generated if empty)',
                    'readonly' => 1,
                    'wrapper' => [
                        'width' => '33',
                    ],
                ],
                [
                    'key' => 'field_transaction_date',
                    'label' => 'Transaction Date',
                    'name' => 'transaction_date',
                    'type' => 'date_time_picker',
                    'instructions' => 'Date and time of transaction',
                    'required' => 1,
                    'display_format' => 'd/m/Y g:i a',
                    'return_format' => 'Y-m-d H:i:s',
                    'default_value' => date('Y-m-d H:i:s'),
                    'wrapper' => [
                        'width' => '33',
                    ],
                ],
                [
                    'key' => 'field_transaction_type',
                    'label' => 'Transaction Type',
                    'name' => 'transaction_type',
                    'type' => 'select',
                    'instructions' => 'Type of transaction',
                    'required' => 1,
                    'choices' => [
                        'payment' => 'Payment (Income)',
                        'refund' => 'Refund (Outgoing)',
                        'adjustment' => 'Adjustment',
                        'penalty' => 'Penalty',
                        'discount' => 'Discount',
                    ],
                    'default_value' => 'payment',
                    'wrapper' => [
                        'width' => '34',
                    ],
                ],
                [
                    'key' => 'field_student',
                    'label' => 'Student',
                    'name' => 'student',
                    'type' => 'post_object',
                    'instructions' => 'Select the student this transaction is for',
                    'required' => 1,
                    'post_type' => ['sms_students'],
                    'return_format' => 'id',
                    'ui' => 1,
                    'wrapper' => [
                        'width' => '50',
                    ],
                ],
                [
                    'key' => 'field_invoice',
                    'label' => 'Related Invoice',
                    'name' => 'invoice',
                    'type' => 'post_object',
                    'instructions' => 'Select the invoice this transaction is related to (optional)',
                    'post_type' => ['sms_invoices'],
                    'return_format' => 'id',
                    'ui' => 1,
                    'allow_null' => 1,
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

        // Payment Details Field Group
        acf_add_local_field_group([
            'key' => 'group_transaction_payment_details',
            'title' => 'Payment Details',
            'fields' => [
                [
                    'key' => 'field_amount',
                    'label' => 'Amount (KES)',
                    'name' => 'amount',
                    'type' => 'number',
                    'instructions' => 'Transaction amount',
                    'required' => 1,
                    'min' => 0,
                    'step' => 0.01,
                    'wrapper' => [
                        'width' => '33',
                    ],
                ],
                [
                    'key' => 'field_payment_method',
                    'label' => 'Payment Method',
                    'name' => 'payment_method',
                    'type' => 'select',
                    'instructions' => 'Method used for payment',
                    'required' => 1,
                    'choices' => [
                        'mpesa' => 'M-Pesa',
                        'airtel_money' => 'Airtel Money',
                        'bank_transfer' => 'Bank Transfer',
                        'cash' => 'Cash',
                        'cheque' => 'Cheque',
                        'card' => 'Credit/Debit Card',
                        'other' => 'Other',
                    ],
                    'wrapper' => [
                        'width' => '33',
                    ],
                ],
                [
                    'key' => 'field_currency',
                    'label' => 'Currency',
                    'name' => 'currency',
                    'type' => 'select',
                    'instructions' => 'Transaction currency',
                    'required' => 1,
                    'choices' => [
                        'KES' => 'Kenyan Shilling (KES)',
                        'USD' => 'US Dollar (USD)',
                        'EUR' => 'Euro (EUR)',
                        'GBP' => 'British Pound (GBP)',
                    ],
                    'default_value' => 'KES',
                    'wrapper' => [
                        'width' => '34',
                    ],
                ],
                [
                    'key' => 'field_exchange_rate',
                    'label' => 'Exchange Rate',
                    'name' => 'exchange_rate',
                    'type' => 'number',
                    'instructions' => 'Exchange rate to KES (if not KES)',
                    'conditional_logic' => [
                        [
                            [
                                'field' => 'field_currency',
                                'operator' => '!=',
                                'value' => 'KES',
                            ],
                        ],
                    ],
                    'step' => 0.0001,
                    'min' => 0,
                    'wrapper' => [
                        'width' => '50',
                    ],
                ],
                [
                    'key' => 'field_amount_kes',
                    'label' => 'Amount in KES',
                    'name' => 'amount_kes',
                    'type' => 'number',
                    'instructions' => 'Amount converted to KES',
                    'step' => 0.01,
                    'readonly' => 1,
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
            'menu_order' => 1,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
        ]);

        // Gateway Information Field Group
        acf_add_local_field_group([
            'key' => 'group_transaction_gateway_info',
            'title' => 'Payment Gateway Information',
            'fields' => [
                [
                    'key' => 'field_gateway_name',
                    'label' => 'Payment Gateway',
                    'name' => 'gateway_name',
                    'type' => 'select',
                    'instructions' => 'Payment gateway used (if applicable)',
                    'choices' => [
                        'mpesa' => 'M-Pesa',
                        'airtel_money' => 'Airtel Money',
                        'equity_bank' => 'Equity Bank',
                        'paypal' => 'PayPal',
                        'stripe' => 'Stripe',
                        'manual' => 'Manual Entry',
                        'other' => 'Other',
                    ],
                    'allow_null' => 1,
                    'wrapper' => [
                        'width' => '50',
                    ],
                ],
                [
                    'key' => 'field_gateway_transaction_id',
                    'label' => 'Gateway Transaction ID',
                    'name' => 'gateway_transaction_id',
                    'type' => 'text',
                    'instructions' => 'Transaction ID from payment gateway',
                    'wrapper' => [
                        'width' => '50',
                    ],
                ],
                [
                    'key' => 'field_gateway_reference',
                    'label' => 'Gateway Reference',
                    'name' => 'gateway_reference',
                    'type' => 'text',
                    'instructions' => 'Reference number from payment gateway',
                    'wrapper' => [
                        'width' => '50',
                    ],
                ],
                [
                    'key' => 'field_phone_number',
                    'label' => 'Phone Number',
                    'name' => 'phone_number',
                    'type' => 'text',
                    'instructions' => 'Phone number used for mobile money payments',
                    'conditional_logic' => [
                        [
                            [
                                'field' => 'field_payment_method',
                                'operator' => '==',
                                'value' => 'mpesa',
                            ],
                        ],
                        [
                            [
                                'field' => 'field_payment_method',
                                'operator' => '==',
                                'value' => 'airtel_money',
                            ],
                        ],
                    ],
                    'wrapper' => [
                        'width' => '50',
                    ],
                ],
                [
                    'key' => 'field_gateway_response',
                    'label' => 'Gateway Response',
                    'name' => 'gateway_response',
                    'type' => 'textarea',
                    'instructions' => 'Full response from payment gateway (JSON format)',
                    'rows' => 4,
                    'readonly' => 1,
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

        // Transaction Status Field Group
        acf_add_local_field_group([
            'key' => 'group_transaction_status',
            'title' => 'Transaction Status & Processing',
            'fields' => [
                [
                    'key' => 'field_transaction_status',
                    'label' => 'Transaction Status',
                    'name' => 'transaction_status',
                    'type' => 'select',
                    'instructions' => 'Current status of the transaction',
                    'required' => 1,
                    'choices' => [
                        'pending' => 'Pending',
                        'processing' => 'Processing',
                        'completed' => 'Completed',
                        'failed' => 'Failed',
                        'cancelled' => 'Cancelled',
                        'refunded' => 'Refunded',
                        'disputed' => 'Disputed',
                    ],
                    'default_value' => 'pending',
                    'wrapper' => [
                        'width' => '33',
                    ],
                ],
                [
                    'key' => 'field_verification_status',
                    'label' => 'Verification Status',
                    'name' => 'verification_status',
                    'type' => 'select',
                    'instructions' => 'Verification status of the transaction',
                    'choices' => [
                        'unverified' => 'Unverified',
                        'verified' => 'Verified',
                        'failed_verification' => 'Failed Verification',
                        'manual_verification' => 'Manual Verification Required',
                    ],
                    'default_value' => 'unverified',
                    'wrapper' => [
                        'width' => '33',
                    ],
                ],
                [
                    'key' => 'field_processed_by',
                    'label' => 'Processed By',
                    'name' => 'processed_by',
                    'type' => 'user',
                    'instructions' => 'User who processed this transaction',
                    'role' => ['administrator', 'teacher'],
                    'wrapper' => [
                        'width' => '34',
                    ],
                ],
                [
                    'key' => 'field_processing_notes',
                    'label' => 'Processing Notes',
                    'name' => 'processing_notes',
                    'type' => 'textarea',
                    'instructions' => 'Notes about transaction processing',
                    'rows' => 3,
                ],
                [
                    'key' => 'field_failure_reason',
                    'label' => 'Failure Reason',
                    'name' => 'failure_reason',
                    'type' => 'textarea',
                    'instructions' => 'Reason for transaction failure (if applicable)',
                    'conditional_logic' => [
                        [
                            [
                                'field' => 'field_transaction_status',
                                'operator' => '==',
                                'value' => 'failed',
                            ],
                        ],
                    ],
                    'rows' => 2,
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

        // Receipt Information Field Group
        acf_add_local_field_group([
            'key' => 'group_transaction_receipt_info',
            'title' => 'Receipt Information',
            'fields' => [
                [
                    'key' => 'field_receipt_number',
                    'label' => 'Receipt Number',
                    'name' => 'receipt_number',
                    'type' => 'text',
                    'instructions' => 'Receipt number for this transaction',
                    'wrapper' => [
                        'width' => '50',
                    ],
                ],
                [
                    'key' => 'field_receipt_sent',
                    'label' => 'Receipt Sent',
                    'name' => 'receipt_sent',
                    'type' => 'true_false',
                    'instructions' => 'Has receipt been sent to student/parent?',
                    'default_value' => 0,
                    'ui' => 1,
                    'wrapper' => [
                        'width' => '50',
                    ],
                ],
                [
                    'key' => 'field_receipt_sent_date',
                    'label' => 'Receipt Sent Date',
                    'name' => 'receipt_sent_date',
                    'type' => 'date_time_picker',
                    'instructions' => 'Date and time receipt was sent',
                    'conditional_logic' => [
                        [
                            [
                                'field' => 'field_receipt_sent',
                                'operator' => '==',
                                'value' => '1',
                            ],
                        ],
                    ],
                    'display_format' => 'd/m/Y g:i a',
                    'return_format' => 'Y-m-d H:i:s',
                    'wrapper' => [
                        'width' => '50',
                    ],
                ],
                [
                    'key' => 'field_receipt_method',
                    'label' => 'Receipt Delivery Method',
                    'name' => 'receipt_method',
                    'type' => 'checkbox',
                    'instructions' => 'How was the receipt delivered?',
                    'conditional_logic' => [
                        [
                            [
                                'field' => 'field_receipt_sent',
                                'operator' => '==',
                                'value' => '1',
                            ],
                        ],
                    ],
                    'choices' => [
                        'email' => 'Email',
                        'sms' => 'SMS',
                        'print' => 'Printed',
                        'portal' => 'Student Portal',
                    ],
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
            'menu_order' => 4,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
        ]);
    }

    /**
     * Add custom columns to the transactions admin list.
     */
    public function add_custom_columns($columns) {
        $new_columns = [];
        
        // Keep the checkbox
        if (isset($columns['cb'])) {
            $new_columns['cb'] = $columns['cb'];
        }
        
        // Add custom columns
        $new_columns['transaction_number'] = __('Transaction #', 'school-management-system');
        $new_columns['student'] = __('Student', 'school-management-system');
        $new_columns['amount'] = __('Amount', 'school-management-system');
        $new_columns['payment_method'] = __('Method', 'school-management-system');
        $new_columns['transaction_status'] = __('Status', 'school-management-system');
        $new_columns['transaction_date'] = __('Date', 'school-management-system');
        $new_columns['gateway'] = __('Gateway', 'school-management-system');
        
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
            case 'transaction_number':
                $transaction_number = get_field('transaction_number', $post_id);
                echo $transaction_number ? '<strong>' . esc_html($transaction_number) . '</strong>' : '—';
                break;
                
            case 'student':
                $student_id = get_field('student', $post_id);
                if ($student_id) {
                    $student_name = get_field('full_name', $student_id);
                    $admission_number = get_field('admission_number', $student_id);
                    echo '<strong>' . esc_html($student_name) . '</strong><br>';
                    echo '<small>' . esc_html($admission_number) . '</small>';
                } else {
                    echo '—';
                }
                break;
                
            case 'amount':
                $amount = get_field('amount', $post_id);
                $currency = get_field('currency', $post_id) ?: 'KES';
                $transaction_type = get_field('transaction_type', $post_id);
                
                if ($amount) {
                    $formatted_amount = $currency . ' ' . number_format($amount, 2);
                    if ($transaction_type === 'refund') {
                        echo '<span class="refund-amount">-' . esc_html($formatted_amount) . '</span>';
                    } else {
                        echo '<strong>' . esc_html($formatted_amount) . '</strong>';
                    }
                } else {
                    echo '—';
                }
                break;
                
            case 'payment_method':
                $method = get_field('payment_method', $post_id);
                if ($method) {
                    $methods = [
                        'mpesa' => 'M-Pesa',
                        'airtel_money' => 'Airtel Money',
                        'bank_transfer' => 'Bank Transfer',
                        'cash' => 'Cash',
                        'cheque' => 'Cheque',
                        'card' => 'Card',
                        'other' => 'Other',
                    ];
                    $method_class = 'payment-method-' . $method;
                    echo '<span class="' . esc_attr($method_class) . '">' . 
                         esc_html($methods[$method] ?? ucfirst($method)) . '</span>';
                } else {
                    echo '—';
                }
                break;
                
            case 'transaction_status':
                $status = get_field('transaction_status', $post_id);
                $verification = get_field('verification_status', $post_id);
                
                if ($status) {
                    $status_class = 'transaction-status-' . $status;
                    echo '<span class="' . esc_attr($status_class) . '">' . esc_html(ucfirst($status)) . '</span>';
                    
                    if ($verification && $verification !== 'verified') {
                        echo '<br><small class="verification-' . esc_attr($verification) . '">' . 
                             esc_html(ucfirst(str_replace('_', ' ', $verification))) . '</small>';
                    }
                } else {
                    echo '—';
                }
                break;
                
            case 'transaction_date':
                $date = get_field('transaction_date', $post_id);
                if ($date) {
                    echo esc_html(date('d/m/Y g:i a', strtotime($date)));
                } else {
                    echo '—';
                }
                break;
                
            case 'gateway':
                $gateway = get_field('gateway_name', $post_id);
                $gateway_ref = get_field('gateway_reference', $post_id);
                
                if ($gateway) {
                    echo esc_html(ucfirst(str_replace('_', ' ', $gateway)));
                    if ($gateway_ref) {
                        echo '<br><small>' . esc_html($gateway_ref) . '</small>';
                    }
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
        $columns['transaction_number'] = 'transaction_number';
        $columns['amount'] = 'amount';
        $columns['transaction_status'] = 'transaction_status';
        $columns['transaction_date'] = 'transaction_date';
        
        return $columns;
    }

    /**
     * Add meta boxes.
     */
    public function add_meta_boxes() {
        add_meta_box(
            'transaction-summary',
            __('Transaction Summary', 'school-management-system'),
            [$this, 'render_transaction_summary_meta_box'],
            self::POST_TYPE,
            'side',
            'high'
        );

        add_meta_box(
            'transaction-actions',
            __('Transaction Actions', 'school-management-system'),
            [$this, 'render_transaction_actions_meta_box'],
            self::POST_TYPE,
            'side',
            'default'
        );
    }

    /**
     * Render transaction summary meta box.
     */
    public function render_transaction_summary_meta_box($post) {
        $transaction_number = get_field('transaction_number', $post->ID);
        $amount = get_field('amount', $post->ID);
        $currency = get_field('currency', $post->ID) ?: 'KES';
        $status = get_field('transaction_status', $post->ID);
        $method = get_field('payment_method', $post->ID);
        $gateway = get_field('gateway_name', $post->ID);
        $verification = get_field('verification_status', $post->ID);
        
        echo '<div class="transaction-summary-stats">';
        
        if ($transaction_number) {
            echo '<p><strong>' . __('Transaction #:', 'school-management-system') . '</strong><br>';
            echo esc_html($transaction_number) . '</p>';
        }
        
        if ($amount) {
            echo '<p><strong>' . __('Amount:', 'school-management-system') . '</strong><br>';
            echo '<span class="transaction-amount">' . esc_html($currency . ' ' . number_format($amount, 2)) . '</span></p>';
        }
        
        if ($method) {
            $methods = [
                'mpesa' => 'M-Pesa',
                'airtel_money' => 'Airtel Money',
                'bank_transfer' => 'Bank Transfer',
                'cash' => 'Cash',
                'cheque' => 'Cheque',
                'card' => 'Card',
                'other' => 'Other',
            ];
            echo '<p><strong>' . __('Payment Method:', 'school-management-system') . '</strong><br>';
            echo esc_html($methods[$method] ?? ucfirst($method)) . '</p>';
        }
        
        if ($gateway) {
            echo '<p><strong>' . __('Gateway:', 'school-management-system') . '</strong><br>';
            echo esc_html(ucfirst(str_replace('_', ' ', $gateway))) . '</p>';
        }
        
        if ($status) {
            echo '<p><strong>' . __('Status:', 'school-management-system') . '</strong><br>';
            echo '<span class="transaction-status-' . esc_attr($status) . '">' . esc_html(ucfirst($status)) . '</span></p>';
        }
        
        if ($verification) {
            echo '<p><strong>' . __('Verification:', 'school-management-system') . '</strong><br>';
            echo '<span class="verification-' . esc_attr($verification) . '">' . 
                 esc_html(ucfirst(str_replace('_', ' ', $verification))) . '</span></p>';
        }
        
        echo '</div>';
    }

    /**
     * Render transaction actions meta box.
     */
    public function render_transaction_actions_meta_box($post) {
        $status = get_field('transaction_status', $post->ID);
        $verification = get_field('verification_status', $post->ID);
        
        echo '<div class="transaction-actions">';
        
        if ($verification === 'unverified') {
            echo '<p><a href="#" class="button button-primary" id="verify-transaction">';
            echo __('Verify Transaction', 'school-management-system') . '</a></p>';
        }
        
        if ($status === 'pending') {
            echo '<p><a href="#" class="button" id="process-transaction">';
            echo __('Process Transaction', 'school-management-system') . '</a></p>';
        }
        
        if ($status === 'completed') {
            echo '<p><a href="#" class="button" id="generate-receipt">';
            echo __('Generate Receipt', 'school-management-system') . '</a></p>';
            
            echo '<p><a href="#" class="button" id="send-receipt">';
            echo __('Send Receipt', 'school-management-system') . '</a></p>';
        }
        
        if (in_array($status, ['completed', 'processing'])) {
            echo '<p><a href="#" class="button button-secondary" id="refund-transaction">';
            echo __('Process Refund', 'school-management-system') . '</a></p>';
        }
        
        echo '<p><a href="#" class="button" onclick="window.print(); return false;">';
        echo __('Print Transaction', 'school-management-system') . '</a></p>';
        
        echo '</div>';
    }

    /**
     * Generate transaction number automatically.
     */
    public function generate_transaction_number($post_id, $post, $update) {
        // Skip if this is an autosave or revision
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }

        // Skip if transaction number already exists
        $existing_transaction_number = get_field('transaction_number', $post_id);
        if (!empty($existing_transaction_number)) {
            return;
        }

        // Generate new transaction number
        $transaction_number = $this->generate_unique_transaction_number();
        
        // Update the field
        update_field('transaction_number', $transaction_number, $post_id);
        
        // Set processed by current user
        $current_user_id = get_current_user_id();
        if ($current_user_id) {
            update_field('processed_by', $current_user_id, $post_id);
        }
        
        // Convert currency if needed
        $this->convert_currency_amount($post_id);
    }

    /**
     * Generate a unique transaction number.
     */
    private function generate_unique_transaction_number() {
        $year = date('Y');
        $month = date('m');
        $prefix = 'TXN' . $year . $month;
        
        // Get the highest existing number for this month
        $args = [
            'post_type' => self::POST_TYPE,
            'posts_per_page' => 1,
            'meta_query' => [
                [
                    'key' => 'transaction_number',
                    'value' => $prefix,
                    'compare' => 'LIKE'
                ]
            ],
            'meta_key' => 'transaction_number',
            'orderby' => 'meta_value',
            'order' => 'DESC'
        ];
        
        $query = new WP_Query($args);
        
        if ($query->have_posts()) {
            $last_transaction = get_field('transaction_number', $query->posts[0]->ID);
            $last_number = intval(substr($last_transaction, -6));
            $new_number = $last_number + 1;
        } else {
            $new_number = 1;
        }
        
        wp_reset_postdata();
        
        return $prefix . str_pad($new_number, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Convert currency amount to KES.
     */
    private function convert_currency_amount($transaction_id) {
        $amount = get_field('amount', $transaction_id);
        $currency = get_field('currency', $transaction_id);
        $exchange_rate = get_field('exchange_rate', $transaction_id);
        
        if ($currency === 'KES') {
            update_field('amount_kes', $amount, $transaction_id);
        } elseif ($exchange_rate && $exchange_rate > 0) {
            $amount_kes = $amount * $exchange_rate;
            update_field('amount_kes', $amount_kes, $transaction_id);
        }
    }

    /**
     * Add custom row actions.
     */
    public function add_custom_row_actions($actions, $post) {
        if ($post->post_type === self::POST_TYPE) {
            $status = get_field('transaction_status', $post->ID);
            
            if ($status === 'completed') {
                $actions['view_receipt'] = '<a href="#" onclick="window.open(\'' . get_permalink($post->ID) . '?receipt=1\', \'_blank\'); return false;">' . 
                                          __('View Receipt', 'school-management-system') . '</a>';
            }
            
            $actions['print_transaction'] = '<a href="#" onclick="window.open(\'' . get_permalink($post->ID) . '?print=1\', \'_blank\'); return false;">' . 
                                           __('Print', 'school-management-system') . '</a>';
        }
        
        return $actions;
    }

    /**
     * Get transactions by student.
     */
    public static function get_transactions_by_student($student_id) {
        $args = [
            'post_type' => self::POST_TYPE,
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => 'student',
                    'value' => $student_id,
                    'compare' => '='
                ]
            ],
            'orderby' => 'date',
            'order' => 'DESC'
        ];
        
        return new WP_Query($args);
    }

    /**
     * Get transactions by status.
     */
    public static function get_transactions_by_status($status = 'completed') {
        $args = [
            'post_type' => self::POST_TYPE,
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => 'transaction_status',
                    'value' => $status,
                    'compare' => '='
                ]
            ]
        ];
        
        return new WP_Query($args);
    }

    /**
     * Get pending transactions.
     */
    public static function get_pending_transactions() {
        return self::get_transactions_by_status('pending');
    }

    /**
     * Get transactions by payment method.
     */
    public static function get_transactions_by_payment_method($method = 'mpesa') {
        $args = [
            'post_type' => self::POST_TYPE,
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => 'payment_method',
                    'value' => $method,
                    'compare' => '='
                ]
            ]
        ];
        
        return new WP_Query($args);
    }

    /**
     * Get transactions by date range.
     */
    public static function get_transactions_by_date_range($start_date, $end_date) {
        $args = [
            'post_type' => self::POST_TYPE,
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => 'transaction_date',
                    'value' => [$start_date, $end_date],
                    'compare' => 'BETWEEN',
                    'type' => 'DATETIME'
                ]
            ]
        ];
        
        return new WP_Query($args);
    }
}

// Initialize the class
new SMS_Transactions_CPT();