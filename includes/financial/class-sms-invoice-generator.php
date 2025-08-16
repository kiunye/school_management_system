<?php
/**
 * Invoice Generation System
 *
 * Handles automated invoice generation with customizable templates,
 * invoice numbering system, status tracking, and bulk generation.
 *
 * @package    School_Management_System
 * @subpackage School_Management_System/includes/financial
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Invoice Generator Class
 */
class SMS_Invoice_Generator extends SMS_Base {

    /**
     * Invoice number format settings
     */
    private $invoice_format_settings;

    /**
     * Invoice template settings
     */
    private $template_settings;

    /**
     * Initialize the class and set its properties.
     */
    public function __construct() {
        parent::__construct();
        
        // Load settings
        $this->load_settings();
        
        // Hook into invoice generation events
        add_action('sms_generate_student_invoice', [$this, 'generate_student_invoice'], 10, 3);
        add_action('sms_bulk_generate_invoices', [$this, 'bulk_generate_invoices'], 10, 2);
        add_action('sms_auto_generate_invoices', [$this, 'process_auto_generation']);
        
        // Hook into fee creation for auto-invoice generation
        add_action('sms_fee_created_auto_invoice', [$this, 'generate_invoices_for_new_fee']);
        
        // Invoice status management
        add_action('sms_update_invoice_status', [$this, 'update_invoice_status'], 10, 3);
        add_action('sms_process_overdue_invoices', [$this, 'process_overdue_invoices']);
        
        // Template management
        add_action('sms_register_invoice_template', [$this, 'register_invoice_template'], 10, 2);
    }

    /**
     * Generate invoice for a specific student and fees.
     *
     * @param int   $student_id Student ID
     * @param array $fee_items  Array of fee items to include
     * @param array $options    Generation options
     * @return int|WP_Error Invoice ID or error
     */
    public function generate_student_invoice($student_id, $fee_items, $options = []) {
        try {
            // Validate student exists
            if (!$this->student_exists($student_id)) {
                return new WP_Error('student_not_found', __('Student not found.', 'school-management-system'));
            }

            // Validate fee items
            $validation_result = $this->validate_fee_items($fee_items);
            if (is_wp_error($validation_result)) {
                return $validation_result;
            }

            // Check for existing unpaid invoices if specified
            if (!empty($options['check_existing'])) {
                $existing_invoice = $this->find_existing_unpaid_invoice($student_id, $fee_items);
                if ($existing_invoice) {
                    return new WP_Error(
                        'existing_invoice_found',
                        sprintf(__('Unpaid invoice #%s already exists for these fees.', 'school-management-system'), 
                               get_field('invoice_number', $existing_invoice))
                    );
                }
            }

            // Calculate invoice totals
            $invoice_calculations = $this->calculate_invoice_totals($student_id, $fee_items, $options);
            if (is_wp_error($invoice_calculations)) {
                return $invoice_calculations;
            }

            // Generate invoice number
            $invoice_number = $this->generate_invoice_number($options);

            // Prepare invoice data
            $invoice_data = $this->prepare_invoice_data($student_id, $fee_items, $invoice_calculations, $options);
            $invoice_data['invoice_number'] = $invoice_number;

            // Create the invoice post
            $invoice_id = $this->create_invoice_post($invoice_data);
            if (is_wp_error($invoice_id)) {
                return $invoice_id;
            }

            // Update invoice meta fields
            $this->update_invoice_meta($invoice_id, $invoice_data);

            // Set invoice status
            $initial_status = $options['initial_status'] ?? 'draft';
            $this->update_invoice_status($invoice_id, $initial_status, 'Invoice created');

            // Send notifications if enabled
            if (!empty($options['send_notifications'])) {
                $this->send_invoice_notifications($invoice_id, $options);
            }

            // Log the creation
            $this->log_activity(
                get_current_user_id(),
                'invoice_generated',
                'invoice',
                $invoice_id,
                [
                    'student_id' => $student_id,
                    'invoice_number' => $invoice_number,
                    'total_amount' => $invoice_calculations['total_amount']
                ]
            );

            return $invoice_id;

        } catch (Exception $e) {
            return new WP_Error('invoice_generation_failed', $e->getMessage());
        }
    }

    /**
     * Generate invoices in bulk for multiple students.
     *
     * @param array $student_ids Array of student IDs
     * @param array $fee_items   Array of fee items to include
     * @param array $options     Generation options
     * @return array Results array with success/error counts
     */
    public function bulk_generate_invoices($student_ids, $fee_items, $options = []) {
        $results = [
            'success_count' => 0,
            'error_count' => 0,
            'generated_invoices' => [],
            'errors' => []
        ];

        // Set batch processing flag
        $options['batch_processing'] = true;

        foreach ($student_ids as $student_id) {
            $invoice_result = $this->generate_student_invoice($student_id, $fee_items, $options);
            
            if (is_wp_error($invoice_result)) {
                $results['error_count']++;
                $results['errors'][] = [
                    'student_id' => $student_id,
                    'error' => $invoice_result->get_error_message()
                ];
            } else {
                $results['success_count']++;
                $results['generated_invoices'][] = [
                    'student_id' => $student_id,
                    'invoice_id' => $invoice_result
                ];
            }
        }

        // Log bulk generation
        $this->log_activity(
            get_current_user_id(),
            'bulk_invoices_generated',
            'invoice',
            0,
            [
                'total_students' => count($student_ids),
                'success_count' => $results['success_count'],
                'error_count' => $results['error_count']
            ]
        );

        return $results;
    }

    /**
     * Generate invoice number based on format settings.
     *
     * @param array $options Generation options
     * @return string Generated invoice number
     */
    public function generate_invoice_number($options = []) {
        $format = $this->invoice_format_settings['format'] ?? 'INV-{YYYY}-{MM}-{NNNN}';
        $prefix = $this->invoice_format_settings['prefix'] ?? 'INV';
        $separator = $this->invoice_format_settings['separator'] ?? '-';
        
        // Get current date components
        $year = date('Y');
        $month = date('m');
        $day = date('d');
        
        // Get next sequence number
        $sequence_number = $this->get_next_sequence_number($year, $month);
        
        // Replace placeholders
        $replacements = [
            '{PREFIX}' => $prefix,
            '{YYYY}' => $year,
            '{YY}' => substr($year, -2),
            '{MM}' => $month,
            '{DD}' => $day,
            '{NNNN}' => str_pad($sequence_number, 4, '0', STR_PAD_LEFT),
            '{NNN}' => str_pad($sequence_number, 3, '0', STR_PAD_LEFT),
            '{NN}' => str_pad($sequence_number, 2, '0', STR_PAD_LEFT),
            '{N}' => $sequence_number,
            '{SEP}' => $separator
        ];
        
        $invoice_number = str_replace(array_keys($replacements), array_values($replacements), $format);
        
        // Ensure uniqueness
        $counter = 1;
        $original_number = $invoice_number;
        while ($this->invoice_number_exists($invoice_number)) {
            $invoice_number = $original_number . $separator . $counter;
            $counter++;
        }
        
        return $invoice_number;
    }

    /**
     * Calculate invoice totals including items, discounts, and penalties.
     *
     * @param int   $student_id Student ID
     * @param array $fee_items  Fee items
     * @param array $options    Calculation options
     * @return array|WP_Error Calculation results or error
     */
    private function calculate_invoice_totals($student_id, $fee_items, $options = []) {
        $calculations = [
            'items' => [],
            'subtotal' => 0,
            'total_discount' => 0,
            'penalties' => 0,
            'total_amount' => 0,
            'calculation_date' => current_time('mysql')
        ];

        foreach ($fee_items as $item) {
            $fee_id = $item['fee_id'];
            $quantity = $item['quantity'] ?? 1;
            $custom_amount = $item['custom_amount'] ?? null;

            // Get fee structure
            $fee_manager = new SMS_Fee_Manager();
            $fee_calculation = $fee_manager->calculate_student_fee($student_id, $fee_id, $options);
            
            if (is_wp_error($fee_calculation)) {
                return $fee_calculation;
            }

            if (!$fee_calculation['applicable']) {
                continue; // Skip non-applicable fees
            }

            // Use custom amount if provided, otherwise use calculated amount
            $unit_price = $custom_amount ?? $fee_calculation['total_amount'];
            $line_total = $unit_price * $quantity;

            // Apply item-level discounts
            $item_discount = 0;
            if (!empty($item['discount_amount'])) {
                $item_discount = floatval($item['discount_amount']);
            } elseif (!empty($item['discount_percentage'])) {
                $item_discount = ($line_total * floatval($item['discount_percentage'])) / 100;
            }

            $item_total = $line_total - $item_discount;

            $calculations['items'][] = [
                'fee_id' => $fee_id,
                'fee_name' => get_the_title($fee_id),
                'description' => $item['description'] ?? get_field('fee_name', $fee_id),
                'quantity' => $quantity,
                'unit_price' => $unit_price,
                'line_total' => $line_total,
                'discount' => $item_discount,
                'total' => $item_total
            ];

            $calculations['subtotal'] += $line_total;
            $calculations['total_discount'] += $item_discount;
        }

        // Apply invoice-level discounts
        if (!empty($options['invoice_discount'])) {
            $invoice_discount = $this->calculate_invoice_discount($calculations['subtotal'], $options['invoice_discount']);
            $calculations['total_discount'] += $invoice_discount;
        }

        // Add penalties if specified
        if (!empty($options['include_penalties'])) {
            $penalties = $this->calculate_invoice_penalties($student_id, $options);
            $calculations['penalties'] = $penalties;
        }

        // Calculate final total
        $calculations['total_amount'] = $calculations['subtotal'] - $calculations['total_discount'] + $calculations['penalties'];

        return $calculations;
    }

    /**
     * Create invoice post with basic data.
     *
     * @param array $invoice_data Invoice data
     * @return int|WP_Error Invoice post ID or error
     */
    private function create_invoice_post($invoice_data) {
        $student_name = get_field('full_name', $invoice_data['student_id']);
        $invoice_title = sprintf(
            __('Invoice %s - %s', 'school-management-system'),
            $invoice_data['invoice_number'],
            $student_name
        );

        $post_data = [
            'post_title' => $invoice_title,
            'post_content' => $invoice_data['notes'] ?? '',
            'post_status' => 'publish',
            'post_type' => 'sms_invoices',
            'post_author' => get_current_user_id()
        ];

        $invoice_id = wp_insert_post($post_data);

        if (is_wp_error($invoice_id)) {
            return $invoice_id;
        }

        // Set taxonomies
        if (!empty($invoice_data['academic_year'])) {
            wp_set_object_terms($invoice_id, $invoice_data['academic_year'], 'sms_academic_years');
        }

        if (!empty($invoice_data['term'])) {
            wp_set_object_terms($invoice_id, $invoice_data['term'], 'sms_terms');
        }

        return $invoice_id;
    }

    /**
     * Update invoice meta fields with calculated data.
     *
     * @param int   $invoice_id   Invoice ID
     * @param array $invoice_data Invoice data
     */
    private function update_invoice_meta($invoice_id, $invoice_data) {
        // Basic invoice information
        update_field('invoice_number', $invoice_data['invoice_number'], $invoice_id);
        update_field('invoice_date', $invoice_data['invoice_date'] ?? current_time('Y-m-d'), $invoice_id);
        update_field('due_date', $invoice_data['due_date'], $invoice_id);
        update_field('student', $invoice_data['student_id'], $invoice_id);
        update_field('invoice_status', $invoice_data['status'] ?? 'draft', $invoice_id);

        // Invoice items
        $acf_items = [];
        foreach ($invoice_data['items'] as $item) {
            $acf_items[] = [
                'item_fee' => $item['fee_id'],
                'item_description' => $item['description'],
                'item_quantity' => $item['quantity'],
                'item_unit_price' => $item['unit_price'],
                'item_discount' => $item['discount'],
                'item_total' => $item['total']
            ];
        }
        update_field('invoice_items', $acf_items, $invoice_id);

        // Totals
        update_field('subtotal', $invoice_data['subtotal'], $invoice_id);
        update_field('total_discount', $invoice_data['total_discount'], $invoice_id);
        update_field('penalties', $invoice_data['penalties'], $invoice_id);
        update_field('total_amount', $invoice_data['total_amount'], $invoice_id);
        update_field('amount_paid', 0, $invoice_id);
        update_field('balance_due', $invoice_data['total_amount'], $invoice_id);
        update_field('payment_status', 'unpaid', $invoice_id);

        // Payment information
        if (!empty($invoice_data['payment_methods'])) {
            update_field('payment_methods', $invoice_data['payment_methods'], $invoice_id);
        }

        if (!empty($invoice_data['payment_instructions'])) {
            update_field('payment_instructions', $invoice_data['payment_instructions'], $invoice_id);
        }

        // Reminder settings
        update_field('payment_deadline_reminder', $invoice_data['reminder_days'] ?? 3, $invoice_id);
        update_field('auto_overdue_processing', $invoice_data['auto_overdue'] ?? true, $invoice_id);
    }

    /**
     * Prepare invoice data from fee items and calculations.
     *
     * @param int   $student_id   Student ID
     * @param array $fee_items    Fee items
     * @param array $calculations Calculated totals
     * @param array $options      Generation options
     * @return array Prepared invoice data
     */
    private function prepare_invoice_data($student_id, $fee_items, $calculations, $options) {
        // Calculate due date
        $due_date = $this->calculate_due_date($fee_items, $options);

        return [
            'student_id' => $student_id,
            'invoice_date' => $options['invoice_date'] ?? current_time('Y-m-d'),
            'due_date' => $due_date,
            'items' => $calculations['items'],
            'subtotal' => $calculations['subtotal'],
            'total_discount' => $calculations['total_discount'],
            'penalties' => $calculations['penalties'],
            'total_amount' => $calculations['total_amount'],
            'status' => $options['initial_status'] ?? 'draft',
            'payment_methods' => $options['payment_methods'] ?? ['mpesa', 'airtel_money', 'cash'],
            'payment_instructions' => $options['payment_instructions'] ?? '',
            'reminder_days' => $options['reminder_days'] ?? 3,
            'auto_overdue' => $options['auto_overdue'] ?? true,
            'academic_year' => $options['academic_year'] ?? null,
            'term' => $options['term'] ?? null,
            'notes' => $options['notes'] ?? ''
        ];
    }

    /**
     * Calculate due date based on fee items and options.
     *
     * @param array $fee_items Fee items
     * @param array $options   Generation options
     * @return string Due date (Y-m-d format)
     */
    private function calculate_due_date($fee_items, $options) {
        // Use explicit due date if provided
        if (!empty($options['due_date'])) {
            return $options['due_date'];
        }

        // Use default days if provided
        if (!empty($options['due_days'])) {
            return date('Y-m-d', strtotime('+' . intval($options['due_days']) . ' days'));
        }

        // Calculate based on fee due date settings
        $earliest_due_date = null;
        foreach ($fee_items as $item) {
            $fee_id = $item['fee_id'];
            $due_date_type = get_field('due_date_type', $fee_id);
            
            if ($due_date_type === 'fixed') {
                $fee_due_date = get_field('due_date_fixed', $fee_id);
            } else {
                $due_days = get_field('due_date_days', $fee_id) ?? 30;
                $fee_due_date = date('Y-m-d', strtotime('+' . $due_days . ' days'));
            }

            if (!$earliest_due_date || $fee_due_date < $earliest_due_date) {
                $earliest_due_date = $fee_due_date;
            }
        }

        return $earliest_due_date ?? date('Y-m-d', strtotime('+30 days'));
    }

    /**
     * Update invoice status with tracking.
     *
     * @param int    $invoice_id Invoice ID
     * @param string $new_status New status
     * @param string $note       Status change note
     * @return bool Success
     */
    public function update_invoice_status($invoice_id, $new_status, $note = '') {
        $current_status = get_field('invoice_status', $invoice_id);
        
        if ($current_status === $new_status) {
            return true; // No change needed
        }

        // Update status
        update_field('invoice_status', $new_status, $invoice_id);

        // Log status change
        $status_history = get_field('status_history', $invoice_id) ?? [];
        $status_history[] = [
            'status' => $new_status,
            'previous_status' => $current_status,
            'changed_by' => get_current_user_id(),
            'changed_date' => current_time('mysql'),
            'note' => $note
        ];
        update_field('status_history', $status_history, $invoice_id);

        // Trigger status-specific actions
        do_action('sms_invoice_status_changed', $invoice_id, $new_status, $current_status);

        return true;
    }

    /**
     * Process overdue invoices automatically.
     */
    public function process_overdue_invoices() {
        $overdue_invoices = $this->get_overdue_invoices();
        
        foreach ($overdue_invoices as $invoice_id) {
            // Update status to overdue
            $this->update_invoice_status($invoice_id, 'overdue', 'Automatically marked as overdue');
            
            // Apply penalties if enabled
            $auto_overdue = get_field('auto_overdue_processing', $invoice_id);
            if ($auto_overdue) {
                $this->apply_overdue_penalties($invoice_id);
            }
            
            // Send overdue notifications
            do_action('sms_invoice_overdue', $invoice_id);
        }
    }

    /**
     * Get overdue invoices.
     *
     * @return array Array of overdue invoice IDs
     */
    private function get_overdue_invoices() {
        $args = [
            'post_type' => 'sms_invoices',
            'post_status' => 'publish',
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => 'due_date',
                    'value' => current_time('Y-m-d'),
                    'compare' => '<',
                    'type' => 'DATE'
                ],
                [
                    'key' => 'invoice_status',
                    'value' => ['draft', 'sent', 'viewed', 'partial'],
                    'compare' => 'IN'
                ],
                [
                    'key' => 'balance_due',
                    'value' => 0,
                    'compare' => '>',
                    'type' => 'NUMERIC'
                ]
            ],
            'fields' => 'ids',
            'posts_per_page' => -1
        ];

        return get_posts($args);
    }

    /**
     * Apply overdue penalties to an invoice.
     *
     * @param int $invoice_id Invoice ID
     */
    private function apply_overdue_penalties($invoice_id) {
        $current_penalties = get_field('penalties', $invoice_id) ?? 0;
        $due_date = get_field('due_date', $invoice_id);
        $days_overdue = $this->calculate_days_overdue($due_date);
        
        // Get penalty settings from invoice items
        $invoice_items = get_field('invoice_items', $invoice_id);
        $total_penalty = 0;
        
        foreach ($invoice_items as $item) {
            $fee_id = $item['item_fee'];
            $penalty_settings = get_field('late_payment_penalty', $fee_id);
            
            if (!empty($penalty_settings['penalty_enabled'])) {
                $grace_days = intval($penalty_settings['penalty_grace_days'] ?? 0);
                
                if ($days_overdue > $grace_days) {
                    $penalty_amount = $this->calculate_penalty_amount(
                        $penalty_settings,
                        $item['item_total'],
                        $days_overdue - $grace_days
                    );
                    $total_penalty += $penalty_amount;
                }
            }
        }
        
        if ($total_penalty > $current_penalties) {
            // Update penalty amount
            update_field('penalties', $total_penalty, $invoice_id);
            
            // Recalculate totals
            $subtotal = get_field('subtotal', $invoice_id);
            $total_discount = get_field('total_discount', $invoice_id);
            $amount_paid = get_field('amount_paid', $invoice_id);
            
            $new_total = $subtotal - $total_discount + $total_penalty;
            $new_balance = $new_total - $amount_paid;
            
            update_field('total_amount', $new_total, $invoice_id);
            update_field('balance_due', $new_balance, $invoice_id);
            
            // Log penalty application
            $this->log_activity(
                0, // System action
                'penalty_applied',
                'invoice',
                $invoice_id,
                [
                    'penalty_amount' => $total_penalty - $current_penalties,
                    'days_overdue' => $days_overdue
                ]
            );
        }
    }

    /**
     * Load invoice format and template settings.
     */
    private function load_settings() {
        $this->invoice_format_settings = get_option('sms_invoice_format_settings', [
            'format' => 'INV-{YYYY}-{MM}-{NNNN}',
            'prefix' => 'INV',
            'separator' => '-',
            'reset_sequence' => 'yearly'
        ]);

        $this->template_settings = get_option('sms_invoice_template_settings', [
            'default_template' => 'standard',
            'include_school_logo' => true,
            'include_payment_instructions' => true,
            'footer_text' => 'Thank you for your payment.'
        ]);
    }

    /**
     * Get next sequence number for invoice numbering.
     *
     * @param string $year  Year
     * @param string $month Month
     * @return int Next sequence number
     */
    private function get_next_sequence_number($year, $month) {
        $reset_type = $this->invoice_format_settings['reset_sequence'] ?? 'yearly';
        
        switch ($reset_type) {
            case 'monthly':
                $sequence_key = "invoice_sequence_{$year}_{$month}";
                break;
            case 'yearly':
                $sequence_key = "invoice_sequence_{$year}";
                break;
            default:
                $sequence_key = 'invoice_sequence_global';
        }
        
        $current_sequence = get_option($sequence_key, 0);
        $next_sequence = $current_sequence + 1;
        update_option($sequence_key, $next_sequence);
        
        return $next_sequence;
    }

    /**
     * Check if invoice number already exists.
     *
     * @param string $invoice_number Invoice number to check
     * @return bool Whether invoice number exists
     */
    private function invoice_number_exists($invoice_number) {
        $args = [
            'post_type' => 'sms_invoices',
            'meta_query' => [
                [
                    'key' => 'invoice_number',
                    'value' => $invoice_number,
                    'compare' => '='
                ]
            ],
            'post_status' => 'any',
            'fields' => 'ids'
        ];

        $existing_invoices = get_posts($args);
        return !empty($existing_invoices);
    }

    /**
     * Validate fee items for invoice generation.
     *
     * @param array $fee_items Fee items to validate
     * @return bool|WP_Error True if valid, WP_Error if invalid
     */
    private function validate_fee_items($fee_items) {
        if (empty($fee_items)) {
            return new WP_Error('no_fee_items', __('At least one fee item is required.', 'school-management-system'));
        }

        foreach ($fee_items as $index => $item) {
            if (empty($item['fee_id'])) {
                return new WP_Error(
                    'missing_fee_id',
                    sprintf(__('Fee ID is required for item %d.', 'school-management-system'), $index + 1)
                );
            }

            if (!get_post($item['fee_id']) || get_post_type($item['fee_id']) !== 'sms_fees') {
                return new WP_Error(
                    'invalid_fee_id',
                    sprintf(__('Invalid fee ID for item %d.', 'school-management-system'), $index + 1)
                );
            }

            $quantity = $item['quantity'] ?? 1;
            if (!is_numeric($quantity) || $quantity <= 0) {
                return new WP_Error(
                    'invalid_quantity',
                    sprintf(__('Invalid quantity for item %d.', 'school-management-system'), $index + 1)
                );
            }
        }

        return true;
    }

    /**
     * Check if student exists.
     *
     * @param int $student_id Student ID
     * @return bool Whether student exists
     */
    private function student_exists($student_id) {
        $student = get_post($student_id);
        return $student && $student->post_type === 'sms_students';
    }

    /**
     * Find existing unpaid invoice for student and fees.
     *
     * @param int   $student_id Student ID
     * @param array $fee_items  Fee items
     * @return int|false Invoice ID or false if not found
     */
    private function find_existing_unpaid_invoice($student_id, $fee_items) {
        $fee_ids = array_column($fee_items, 'fee_id');
        
        $args = [
            'post_type' => 'sms_invoices',
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => 'student',
                    'value' => $student_id,
                    'compare' => '='
                ],
                [
                    'key' => 'balance_due',
                    'value' => 0,
                    'compare' => '>',
                    'type' => 'NUMERIC'
                ]
            ],
            'fields' => 'ids',
            'posts_per_page' => -1
        ];

        $existing_invoices = get_posts($args);
        
        foreach ($existing_invoices as $invoice_id) {
            $invoice_items = get_field('invoice_items', $invoice_id);
            $invoice_fee_ids = array_column($invoice_items, 'item_fee');
            
            // Check if there's overlap in fee IDs
            if (array_intersect($fee_ids, $invoice_fee_ids)) {
                return $invoice_id;
            }
        }
        
        return false;
    }

    /**
     * Calculate days overdue from due date.
     *
     * @param string $due_date Due date
     * @return int Days overdue
     */
    private function calculate_days_overdue($due_date) {
        $due_timestamp = strtotime($due_date);
        $current_timestamp = current_time('timestamp');
        
        if ($current_timestamp > $due_timestamp) {
            return ceil(($current_timestamp - $due_timestamp) / DAY_IN_SECONDS);
        }
        
        return 0;
    }

    /**
     * Calculate penalty amount based on settings.
     *
     * @param array $penalty_settings Penalty configuration
     * @param float $base_amount      Base amount
     * @param int   $days_overdue     Days overdue
     * @return float Penalty amount
     */
    private function calculate_penalty_amount($penalty_settings, $base_amount, $days_overdue) {
        $penalty_type = $penalty_settings['penalty_type'];
        $penalty_value = floatval($penalty_settings['penalty_amount']);

        switch ($penalty_type) {
            case 'fixed':
                return $penalty_value;
                
            case 'percentage':
                return ($base_amount * $penalty_value) / 100;
                
            case 'daily':
                return $penalty_value * $days_overdue;
                
            default:
                return 0;
        }
    }

    /**
     * Send invoice notifications.
     *
     * @param int   $invoice_id Invoice ID
     * @param array $options    Notification options
     */
    private function send_invoice_notifications($invoice_id, $options) {
        // Email notification
        if (!empty($options['send_email'])) {
            do_action('sms_send_invoice_email', $invoice_id);
        }

        // SMS notification
        if (!empty($options['send_sms'])) {
            do_action('sms_send_invoice_sms', $invoice_id);
        }
    }

    /**
     * Generate invoices for a newly created fee.
     *
     * @param int $fee_id Fee ID
     */
    public function generate_invoices_for_new_fee($fee_id) {
        $auto_generate = get_field('auto_generate_invoices', $fee_id);
        if (!$auto_generate) {
            return;
        }

        // Get applicable students
        $applicable_students = $this->get_applicable_students_for_fee($fee_id);
        
        if (empty($applicable_students)) {
            return;
        }

        // Generate invoices
        $fee_items = [['fee_id' => $fee_id, 'quantity' => 1]];
        $options = [
            'initial_status' => 'sent',
            'send_notifications' => true,
            'batch_processing' => true
        ];

        $this->bulk_generate_invoices($applicable_students, $fee_items, $options);
    }

    /**
     * Get students applicable for a specific fee.
     *
     * @param int $fee_id Fee ID
     * @return array Array of student IDs
     */
    private function get_applicable_students_for_fee($fee_id) {
        $applicable_grades = get_field('applicable_grades', $fee_id);
        $applicable_classes = get_field('applicable_classes', $fee_id);
        
        $args = [
            'post_type' => 'sms_students',
            'post_status' => 'publish',
            'fields' => 'ids',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => 'status',
                    'value' => 'active',
                    'compare' => '='
                ]
            ]
        ];

        // Add grade filter if specified
        if (!empty($applicable_grades)) {
            $args['meta_query'][] = [
                'key' => 'grade_level',
                'value' => $applicable_grades,
                'compare' => 'IN'
            ];
        }

        // Add class filter if specified
        if (!empty($applicable_classes)) {
            $args['meta_query'][] = [
                'key' => 'current_class',
                'value' => $applicable_classes,
                'compare' => 'IN'
            ];
        }

        return get_posts($args);
    }

    /**
     * Process automatic invoice generation (scheduled task).
     */
    public function process_auto_generation() {
        // Get fees with auto-generation enabled
        $auto_fees = get_posts([
            'post_type' => 'sms_fees',
            'meta_query' => [
                [
                    'key' => 'auto_generate_invoices',
                    'value' => '1',
                    'compare' => '='
                ]
            ],
            'fields' => 'ids',
            'posts_per_page' => -1
        ]);

        foreach ($auto_fees as $fee_id) {
            $this->generate_invoices_for_new_fee($fee_id);
        }
    }
}