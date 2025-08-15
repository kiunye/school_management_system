<?php
/**
 * Invoices Custom Post Type
 *
 * Handles the registration and management of the invoices custom post type
 * with invoice generation, tracking, and payment management.
 *
 * @package    School_Management_System
 * @subpackage School_Management_System/includes/post-types
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Invoices Custom Post Type Class
 */
class SMS_Invoices_CPT extends SMS_Base {

    /**
     * The post type name.
     */
    const POST_TYPE = 'sms_invoices';

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
        
        // Handle invoice number generation
        add_action('save_post_' . self::POST_TYPE, [$this, 'generate_invoice_number'], 10, 3);
        
        // Add custom actions
        add_filter('post_row_actions', [$this, 'add_custom_row_actions'], 10, 2);
    }

    /**
     * Register the invoices custom post type.
     */
    public function register_post_type() {
        $labels = [
            'name'                  => _x('Invoices', 'Post Type General Name', 'school-management-system'),
            'singular_name'         => _x('Invoice', 'Post Type Singular Name', 'school-management-system'),
            'menu_name'             => __('Invoices', 'school-management-system'),
            'name_admin_bar'        => __('Invoice', 'school-management-system'),
            'archives'              => __('Invoice Archives', 'school-management-system'),
            'attributes'            => __('Invoice Attributes', 'school-management-system'),
            'parent_item_colon'     => __('Parent Invoice:', 'school-management-system'),
            'all_items'             => __('All Invoices', 'school-management-system'),
            'add_new_item'          => __('Add New Invoice', 'school-management-system'),
            'add_new'               => __('Add New', 'school-management-system'),
            'new_item'              => __('New Invoice', 'school-management-system'),
            'edit_item'             => __('Edit Invoice', 'school-management-system'),
            'update_item'           => __('Update Invoice', 'school-management-system'),
            'view_item'             => __('View Invoice', 'school-management-system'),
            'view_items'            => __('View Invoices', 'school-management-system'),
            'search_items'          => __('Search Invoice', 'school-management-system'),
            'not_found'             => __('Not found', 'school-management-system'),
            'not_found_in_trash'    => __('Not found in Trash', 'school-management-system'),
            'featured_image'        => __('Invoice Image', 'school-management-system'),
            'set_featured_image'    => __('Set invoice image', 'school-management-system'),
            'remove_featured_image' => __('Remove invoice image', 'school-management-system'),
            'use_featured_image'    => __('Use as invoice image', 'school-management-system'),
            'insert_into_item'      => __('Insert into invoice', 'school-management-system'),
            'uploaded_to_this_item' => __('Uploaded to this invoice', 'school-management-system'),
            'items_list'            => __('Invoices list', 'school-management-system'),
            'items_list_navigation' => __('Invoices list navigation', 'school-management-system'),
            'filter_items_list'     => __('Filter invoices list', 'school-management-system'),
        ];

        $args = [
            'label'                 => __('Invoice', 'school-management-system'),
            'description'           => __('Student fee invoices and billing', 'school-management-system'),
            'labels'                => $labels,
            'supports'              => ['title', 'editor', 'revisions'],
            'taxonomies'            => ['sms_academic_years', 'sms_terms'],
            'hierarchical'          => false,
            'public'                => false,
            'show_ui'               => true,
            'show_in_menu'          => 'sms-dashboard',
            'menu_position'         => 8,
            'menu_icon'             => 'dashicons-media-spreadsheet',
            'show_in_admin_bar'     => true,
            'show_in_nav_menus'     => false,
            'can_export'            => true,
            'has_archive'           => false,
            'exclude_from_search'   => true,
            'publicly_queryable'    => false,
            'capability_type'       => 'post',
            'capabilities'          => [
                'edit_post'          => 'manage_invoices',
                'read_post'          => 'manage_invoices',
                'delete_post'        => 'manage_invoices',
                'edit_posts'         => 'manage_invoices',
                'edit_others_posts'  => 'manage_invoices',
                'delete_posts'       => 'manage_invoices',
                'publish_posts'      => 'manage_invoices',
                'read_private_posts' => 'manage_invoices',
            ],
            'show_in_rest'          => true,
            'rest_base'             => 'invoices',
            'rest_controller_class' => 'WP_REST_Posts_Controller',
        ];

        register_post_type(self::POST_TYPE, $args);
    }

    /**
     * Register ACF field groups for invoices.
     */
    public function register_acf_fields() {
        if (!function_exists('acf_add_local_field_group')) {
            return;
        }

        // Basic Invoice Information Field Group
        acf_add_local_field_group([
            'key' => 'group_invoice_basic_info',
            'title' => 'Invoice Information',
            'fields' => [
                [
                    'key' => 'field_invoice_number',
                    'label' => 'Invoice Number',
                    'name' => 'invoice_number',
                    'type' => 'text',
                    'instructions' => 'Unique invoice number (auto-generated if empty)',
                    'readonly' => 1,
                    'wrapper' => [
                        'width' => '33',
                    ],
                ],
                [
                    'key' => 'field_invoice_date',
                    'label' => 'Invoice Date',
                    'name' => 'invoice_date',
                    'type' => 'date_picker',
                    'instructions' => 'Date when invoice was generated',
                    'required' => 1,
                    'display_format' => 'd/m/Y',
                    'return_format' => 'Y-m-d',
                    'default_value' => date('Y-m-d'),
                    'wrapper' => [
                        'width' => '33',
                    ],
                ],
                [
                    'key' => 'field_due_date',
                    'label' => 'Due Date',
                    'name' => 'due_date',
                    'type' => 'date_picker',
                    'instructions' => 'Payment due date',
                    'required' => 1,
                    'display_format' => 'd/m/Y',
                    'return_format' => 'Y-m-d',
                    'wrapper' => [
                        'width' => '34',
                    ],
                ],
                [
                    'key' => 'field_student',
                    'label' => 'Student',
                    'name' => 'student',
                    'type' => 'post_object',
                    'instructions' => 'Select the student this invoice is for',
                    'required' => 1,
                    'post_type' => ['sms_students'],
                    'return_format' => 'id',
                    'ui' => 1,
                    'wrapper' => [
                        'width' => '50',
                    ],
                ],
                [
                    'key' => 'field_invoice_status',
                    'label' => 'Invoice Status',
                    'name' => 'invoice_status',
                    'type' => 'select',
                    'instructions' => 'Current status of this invoice',
                    'required' => 1,
                    'choices' => [
                        'draft' => 'Draft',
                        'sent' => 'Sent',
                        'viewed' => 'Viewed',
                        'partial' => 'Partially Paid',
                        'paid' => 'Paid',
                        'overdue' => 'Overdue',
                        'cancelled' => 'Cancelled',
                    ],
                    'default_value' => 'draft',
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

        // Invoice Items Field Group
        acf_add_local_field_group([
            'key' => 'group_invoice_items',
            'title' => 'Invoice Items',
            'fields' => [
                [
                    'key' => 'field_invoice_items',
                    'label' => 'Invoice Items',
                    'name' => 'invoice_items',
                    'type' => 'repeater',
                    'instructions' => 'Add fees and charges to this invoice',
                    'required' => 1,
                    'min' => 1,
                    'layout' => 'table',
                    'button_label' => 'Add Item',
                    'sub_fields' => [
                        [
                            'key' => 'field_item_fee',
                            'label' => 'Fee',
                            'name' => 'item_fee',
                            'type' => 'post_object',
                            'post_type' => ['sms_fees'],
                            'return_format' => 'id',
                            'ui' => 1,
                            'required' => 1,
                        ],
                        [
                            'key' => 'field_item_description',
                            'label' => 'Description',
                            'name' => 'item_description',
                            'type' => 'text',
                            'placeholder' => 'Fee description',
                        ],
                        [
                            'key' => 'field_item_quantity',
                            'label' => 'Quantity',
                            'name' => 'item_quantity',
                            'type' => 'number',
                            'default_value' => 1,
                            'min' => 1,
                            'required' => 1,
                        ],
                        [
                            'key' => 'field_item_unit_price',
                            'label' => 'Unit Price (KES)',
                            'name' => 'item_unit_price',
                            'type' => 'number',
                            'step' => 0.01,
                            'min' => 0,
                            'required' => 1,
                        ],
                        [
                            'key' => 'field_item_discount',
                            'label' => 'Discount (KES)',
                            'name' => 'item_discount',
                            'type' => 'number',
                            'step' => 0.01,
                            'min' => 0,
                            'default_value' => 0,
                        ],
                        [
                            'key' => 'field_item_total',
                            'label' => 'Total (KES)',
                            'name' => 'item_total',
                            'type' => 'number',
                            'step' => 0.01,
                            'readonly' => 1,
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

        // Invoice Totals Field Group
        acf_add_local_field_group([
            'key' => 'group_invoice_totals',
            'title' => 'Invoice Totals',
            'fields' => [
                [
                    'key' => 'field_subtotal',
                    'label' => 'Subtotal (KES)',
                    'name' => 'subtotal',
                    'type' => 'number',
                    'step' => 0.01,
                    'readonly' => 1,
                    'wrapper' => [
                        'width' => '25',
                    ],
                ],
                [
                    'key' => 'field_total_discount',
                    'label' => 'Total Discount (KES)',
                    'name' => 'total_discount',
                    'type' => 'number',
                    'step' => 0.01,
                    'readonly' => 1,
                    'wrapper' => [
                        'width' => '25',
                    ],
                ],
                [
                    'key' => 'field_penalties',
                    'label' => 'Penalties (KES)',
                    'name' => 'penalties',
                    'type' => 'number',
                    'step' => 0.01,
                    'default_value' => 0,
                    'wrapper' => [
                        'width' => '25',
                    ],
                ],
                [
                    'key' => 'field_total_amount',
                    'label' => 'Total Amount (KES)',
                    'name' => 'total_amount',
                    'type' => 'number',
                    'step' => 0.01,
                    'readonly' => 1,
                    'wrapper' => [
                        'width' => '25',
                    ],
                ],
                [
                    'key' => 'field_amount_paid',
                    'label' => 'Amount Paid (KES)',
                    'name' => 'amount_paid',
                    'type' => 'number',
                    'step' => 0.01,
                    'default_value' => 0,
                    'readonly' => 1,
                    'wrapper' => [
                        'width' => '33',
                    ],
                ],
                [
                    'key' => 'field_balance_due',
                    'label' => 'Balance Due (KES)',
                    'name' => 'balance_due',
                    'type' => 'number',
                    'step' => 0.01,
                    'readonly' => 1,
                    'wrapper' => [
                        'width' => '33',
                    ],
                ],
                [
                    'key' => 'field_payment_status',
                    'label' => 'Payment Status',
                    'name' => 'payment_status',
                    'type' => 'select',
                    'choices' => [
                        'unpaid' => 'Unpaid',
                        'partial' => 'Partially Paid',
                        'paid' => 'Fully Paid',
                        'overpaid' => 'Overpaid',
                    ],
                    'default_value' => 'unpaid',
                    'readonly' => 1,
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
            'menu_order' => 2,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
        ]);

        // Payment Information Field Group
        acf_add_local_field_group([
            'key' => 'group_invoice_payment_info',
            'title' => 'Payment Information',
            'fields' => [
                [
                    'key' => 'field_payment_methods',
                    'label' => 'Accepted Payment Methods',
                    'name' => 'payment_methods',
                    'type' => 'checkbox',
                    'instructions' => 'Select accepted payment methods for this invoice',
                    'choices' => [
                        'mpesa' => 'M-Pesa',
                        'airtel_money' => 'Airtel Money',
                        'bank_transfer' => 'Bank Transfer',
                        'cash' => 'Cash Payment',
                        'cheque' => 'Cheque',
                    ],
                    'default_value' => ['mpesa', 'airtel_money', 'cash'],
                ],
                [
                    'key' => 'field_payment_instructions',
                    'label' => 'Payment Instructions',
                    'name' => 'payment_instructions',
                    'type' => 'textarea',
                    'instructions' => 'Special instructions for payment',
                    'rows' => 3,
                    'placeholder' => 'Please include invoice number in payment reference',
                ],
                [
                    'key' => 'field_payment_deadline_reminder',
                    'label' => 'Send Reminder Before Due Date',
                    'name' => 'payment_deadline_reminder',
                    'type' => 'number',
                    'instructions' => 'Days before due date to send payment reminder (0 to disable)',
                    'default_value' => 3,
                    'min' => 0,
                    'max' => 30,
                    'wrapper' => [
                        'width' => '50',
                    ],
                ],
                [
                    'key' => 'field_auto_overdue_processing',
                    'label' => 'Auto Process Overdue',
                    'name' => 'auto_overdue_processing',
                    'type' => 'true_false',
                    'instructions' => 'Automatically mark as overdue and apply penalties after due date',
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
     * Add custom columns to the invoices admin list.
     */
    public function add_custom_columns($columns) {
        $new_columns = [];
        
        // Keep the checkbox
        if (isset($columns['cb'])) {
            $new_columns['cb'] = $columns['cb'];
        }
        
        // Add custom columns
        $new_columns['invoice_number'] = __('Invoice #', 'school-management-system');
        $new_columns['student'] = __('Student', 'school-management-system');
        $new_columns['total_amount'] = __('Total Amount', 'school-management-system');
        $new_columns['amount_paid'] = __('Paid', 'school-management-system');
        $new_columns['balance_due'] = __('Balance', 'school-management-system');
        $new_columns['due_date'] = __('Due Date', 'school-management-system');
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
            case 'invoice_number':
                $invoice_number = get_field('invoice_number', $post_id);
                echo $invoice_number ? '<strong>' . esc_html($invoice_number) . '</strong>' : '—';
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
                
            case 'total_amount':
                $total = get_field('total_amount', $post_id);
                echo $total ? '<strong>' . $this->format_currency($total) . '</strong>' : '—';
                break;
                
            case 'amount_paid':
                $paid = get_field('amount_paid', $post_id);
                echo $paid ? $this->format_currency($paid) : $this->format_currency(0);
                break;
                
            case 'balance_due':
                $balance = get_field('balance_due', $post_id);
                if ($balance > 0) {
                    echo '<span class="balance-due">' . $this->format_currency($balance) . '</span>';
                } elseif ($balance < 0) {
                    echo '<span class="overpaid">' . $this->format_currency(abs($balance)) . ' overpaid</span>';
                } else {
                    echo '<span class="paid-full">Paid</span>';
                }
                break;
                
            case 'due_date':
                $due_date = get_field('due_date', $post_id);
                if ($due_date) {
                    $due_timestamp = strtotime($due_date);
                    $today = time();
                    $formatted_date = date('d/m/Y', $due_timestamp);
                    
                    if ($due_timestamp < $today) {
                        echo '<span class="overdue-date">' . esc_html($formatted_date) . '</span>';
                    } elseif ($due_timestamp < ($today + (3 * 24 * 60 * 60))) {
                        echo '<span class="due-soon">' . esc_html($formatted_date) . '</span>';
                    } else {
                        echo esc_html($formatted_date);
                    }
                } else {
                    echo '—';
                }
                break;
                
            case 'status':
                $status = get_field('invoice_status', $post_id);
                $payment_status = get_field('payment_status', $post_id);
                
                if ($status) {
                    $status_class = 'invoice-status-' . $status;
                    echo '<span class="' . esc_attr($status_class) . '">' . esc_html(ucfirst($status)) . '</span>';
                    
                    if ($payment_status && $payment_status !== 'unpaid') {
                        echo '<br><small class="payment-status-' . esc_attr($payment_status) . '">' . 
                             esc_html(ucfirst(str_replace('_', ' ', $payment_status))) . '</small>';
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
        $columns['invoice_number'] = 'invoice_number';
        $columns['total_amount'] = 'total_amount';
        $columns['due_date'] = 'due_date';
        $columns['status'] = 'invoice_status';
        
        return $columns;
    }

    /**
     * Add meta boxes.
     */
    public function add_meta_boxes() {
        add_meta_box(
            'invoice-summary',
            __('Invoice Summary', 'school-management-system'),
            [$this, 'render_invoice_summary_meta_box'],
            self::POST_TYPE,
            'side',
            'high'
        );

        add_meta_box(
            'invoice-actions',
            __('Invoice Actions', 'school-management-system'),
            [$this, 'render_invoice_actions_meta_box'],
            self::POST_TYPE,
            'side',
            'default'
        );
    }

    /**
     * Render invoice summary meta box.
     */
    public function render_invoice_summary_meta_box($post) {
        $invoice_number = get_field('invoice_number', $post->ID);
        $total_amount = get_field('total_amount', $post->ID);
        $amount_paid = get_field('amount_paid', $post->ID);
        $balance_due = get_field('balance_due', $post->ID);
        $due_date = get_field('due_date', $post->ID);
        $status = get_field('invoice_status', $post->ID);
        
        echo '<div class="invoice-summary-stats">';
        
        if ($invoice_number) {
            echo '<p><strong>' . __('Invoice Number:', 'school-management-system') . '</strong><br>';
            echo esc_html($invoice_number) . '</p>';
        }
        
        if ($total_amount) {
            echo '<p><strong>' . __('Total Amount:', 'school-management-system') . '</strong><br>';
            echo '<span class="total-amount">' . $this->format_currency($total_amount) . '</span></p>';
        }
        
        if ($amount_paid !== null) {
            echo '<p><strong>' . __('Amount Paid:', 'school-management-system') . '</strong><br>';
            echo '<span class="amount-paid">' . $this->format_currency($amount_paid) . '</span></p>';
        }
        
        if ($balance_due !== null) {
            echo '<p><strong>' . __('Balance Due:', 'school-management-system') . '</strong><br>';
            if ($balance_due > 0) {
                echo '<span class="balance-due">' . $this->format_currency($balance_due) . '</span>';
            } elseif ($balance_due < 0) {
                echo '<span class="overpaid">' . $this->format_currency(abs($balance_due)) . ' overpaid</span>';
            } else {
                echo '<span class="paid-full">Fully Paid</span>';
            }
            echo '</p>';
        }
        
        if ($due_date) {
            echo '<p><strong>' . __('Due Date:', 'school-management-system') . '</strong><br>';
            $due_timestamp = strtotime($due_date);
            $today = time();
            
            if ($due_timestamp < $today) {
                echo '<span class="overdue-date">' . esc_html(date('d/m/Y', $due_timestamp)) . ' (Overdue)</span>';
            } else {
                echo esc_html(date('d/m/Y', $due_timestamp));
            }
            echo '</p>';
        }
        
        if ($status) {
            echo '<p><strong>' . __('Status:', 'school-management-system') . '</strong><br>';
            echo '<span class="invoice-status-' . esc_attr($status) . '">' . esc_html(ucfirst($status)) . '</span></p>';
        }
        
        echo '</div>';
    }

    /**
     * Render invoice actions meta box.
     */
    public function render_invoice_actions_meta_box($post) {
        echo '<div class="invoice-actions">';
        
        echo '<p><a href="#" class="button button-primary" onclick="window.print(); return false;">';
        echo __('Print Invoice', 'school-management-system') . '</a></p>';
        
        echo '<p><a href="#" class="button" id="send-invoice-email">';
        echo __('Send via Email', 'school-management-system') . '</a></p>';
        
        echo '<p><a href="#" class="button" id="send-invoice-sms">';
        echo __('Send SMS Reminder', 'school-management-system') . '</a></p>';
        
        echo '<p><a href="#" class="button" id="record-payment">';
        echo __('Record Payment', 'school-management-system') . '</a></p>';
        
        $status = get_field('invoice_status', $post->ID);
        if ($status !== 'cancelled') {
            echo '<p><a href="#" class="button button-secondary" id="cancel-invoice">';
            echo __('Cancel Invoice', 'school-management-system') . '</a></p>';
        }
        
        echo '</div>';
    }

    /**
     * Generate invoice number automatically.
     */
    public function generate_invoice_number($post_id, $post, $update) {
        // Skip if this is an autosave or revision
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }

        // Skip if invoice number already exists
        $existing_invoice_number = get_field('invoice_number', $post_id);
        if (!empty($existing_invoice_number)) {
            return;
        }

        // Generate new invoice number
        $invoice_number = $this->generate_unique_invoice_number();
        
        // Update the field
        update_field('invoice_number', $invoice_number, $post_id);
        
        // Calculate totals
        $this->calculate_invoice_totals($post_id);
    }

    /**
     * Generate a unique invoice number.
     */
    private function generate_unique_invoice_number() {
        $year = date('Y');
        $month = date('m');
        $prefix = 'INV' . $year . $month;
        
        // Get the highest existing number for this month
        $args = [
            'post_type' => self::POST_TYPE,
            'posts_per_page' => 1,
            'meta_query' => [
                [
                    'key' => 'invoice_number',
                    'value' => $prefix,
                    'compare' => 'LIKE'
                ]
            ],
            'meta_key' => 'invoice_number',
            'orderby' => 'meta_value',
            'order' => 'DESC'
        ];
        
        $query = new WP_Query($args);
        
        if ($query->have_posts()) {
            $last_invoice = get_field('invoice_number', $query->posts[0]->ID);
            $last_number = intval(substr($last_invoice, -4));
            $new_number = $last_number + 1;
        } else {
            $new_number = 1;
        }
        
        wp_reset_postdata();
        
        return $prefix . str_pad($new_number, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Calculate invoice totals.
     */
    public function calculate_invoice_totals($invoice_id) {
        $items = get_field('invoice_items', $invoice_id);
        
        if (!$items || !is_array($items)) {
            return;
        }
        
        $subtotal = 0;
        $total_discount = 0;
        
        foreach ($items as $item) {
            $quantity = $item['item_quantity'] ?? 1;
            $unit_price = $item['item_unit_price'] ?? 0;
            $discount = $item['item_discount'] ?? 0;
            
            $item_total = ($quantity * $unit_price) - $discount;
            $subtotal += $quantity * $unit_price;
            $total_discount += $discount;
            
            // Update item total
            $item['item_total'] = $item_total;
        }
        
        $penalties = get_field('penalties', $invoice_id) ?? 0;
        $total_amount = $subtotal - $total_discount + $penalties;
        $amount_paid = get_field('amount_paid', $invoice_id) ?? 0;
        $balance_due = $total_amount - $amount_paid;
        
        // Update totals
        update_field('subtotal', $subtotal, $invoice_id);
        update_field('total_discount', $total_discount, $invoice_id);
        update_field('total_amount', $total_amount, $invoice_id);
        update_field('balance_due', $balance_due, $invoice_id);
        
        // Update payment status
        if ($balance_due <= 0) {
            $payment_status = $balance_due < 0 ? 'overpaid' : 'paid';
        } elseif ($amount_paid > 0) {
            $payment_status = 'partial';
        } else {
            $payment_status = 'unpaid';
        }
        
        update_field('payment_status', $payment_status, $invoice_id);
        
        // Update invoice status based on payment and due date
        $this->update_invoice_status($invoice_id);
    }

    /**
     * Update invoice status based on payment and due date.
     */
    private function update_invoice_status($invoice_id) {
        $current_status = get_field('invoice_status', $invoice_id);
        $payment_status = get_field('payment_status', $invoice_id);
        $due_date = get_field('due_date', $invoice_id);
        
        // Don't change cancelled invoices
        if ($current_status === 'cancelled') {
            return;
        }
        
        $new_status = $current_status;
        
        if ($payment_status === 'paid') {
            $new_status = 'paid';
        } elseif ($due_date && strtotime($due_date) < time() && $payment_status !== 'paid') {
            $new_status = 'overdue';
        } elseif ($payment_status === 'partial') {
            $new_status = 'partial';
        }
        
        if ($new_status !== $current_status) {
            update_field('invoice_status', $new_status, $invoice_id);
        }
    }

    /**
     * Add custom row actions.
     */
    public function add_custom_row_actions($actions, $post) {
        if ($post->post_type === self::POST_TYPE) {
            $actions['view_invoice'] = '<a href="' . get_permalink($post->ID) . '" target="_blank">' . 
                                      __('View Invoice', 'school-management-system') . '</a>';
            
            $actions['print_invoice'] = '<a href="#" onclick="window.open(\'' . get_permalink($post->ID) . '?print=1\', \'_blank\'); return false;">' . 
                                       __('Print', 'school-management-system') . '</a>';
        }
        
        return $actions;
    }

    /**
     * Get invoices by student.
     */
    public static function get_invoices_by_student($student_id) {
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
     * Get overdue invoices.
     */
    public static function get_overdue_invoices() {
        $args = [
            'post_type' => self::POST_TYPE,
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => 'due_date',
                    'value' => date('Y-m-d'),
                    'compare' => '<',
                    'type' => 'DATE'
                ],
                [
                    'key' => 'payment_status',
                    'value' => 'paid',
                    'compare' => '!='
                ]
            ]
        ];
        
        return new WP_Query($args);
    }

    /**
     * Get unpaid invoices.
     */
    public static function get_unpaid_invoices() {
        $args = [
            'post_type' => self::POST_TYPE,
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => 'payment_status',
                    'value' => ['unpaid', 'partial'],
                    'compare' => 'IN'
                ]
            ]
        ];
        
        return new WP_Query($args);
    }
}

// Initialize the class
new SMS_Invoices_CPT();