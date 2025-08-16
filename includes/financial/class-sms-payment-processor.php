<?php
/**
 * Payment Processing and Tracking System
 *
 * Handles overdue payment identification, penalty application,
 * payment history tracking, and automated payment reminders.
 *
 * @package    School_Management_System
 * @subpackage School_Management_System/includes/financial
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Payment Processor Class
 */
class SMS_Payment_Processor extends SMS_Base {

    /**
     * Payment statuses
     */
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_OVERDUE = 'overdue';
    const STATUS_PARTIAL = 'partial';

    /**
     * Reminder types
     */
    const REMINDER_UPCOMING = 'upcoming';
    const REMINDER_DUE = 'due';
    const REMINDER_OVERDUE = 'overdue';
    const REMINDER_FINAL = 'final';

    /**
     * Initialize the class and set its properties.
     */
    public function __construct() {
        parent::__construct();
        
        // Hook into daily processing
        add_action('sms_daily_payment_processing', [$this, 'process_daily_payment_tasks']);
        
        // Hook into payment status changes
        add_action('sms_payment_completed', [$this, 'handle_payment_completion'], 10, 2);
        add_action('sms_payment_failed', [$this, 'handle_payment_failure'], 10, 2);
        
        // Automated reminder system
        add_action('sms_send_payment_reminders', [$this, 'send_automated_reminders']);
        add_action('sms_process_overdue_payments', [$this, 'process_overdue_payments']);
        
        // Payment history tracking
        add_action('sms_transaction_completed', [$this, 'update_payment_history'], 10, 2);
        
        // Schedule daily tasks if not already scheduled
        if (!wp_next_scheduled('sms_daily_payment_processing')) {
            wp_schedule_event(time(), 'daily', 'sms_daily_payment_processing');
        }
    }

    /**
     * Process daily payment-related tasks.
     */
    public function process_daily_payment_tasks() {
        try {
            // Identify and process overdue payments
            $this->identify_overdue_payments();
            
            // Apply penalties to overdue payments
            $this->apply_overdue_penalties();
            
            // Send automated payment reminders
            $this->send_automated_reminders();
            
            // Update payment statuses
            $this->update_payment_statuses();
            
            // Generate payment reports
            $this->generate_daily_payment_reports();
            
            // Log daily processing completion
            $this->log_activity(
                0, // System action
                'daily_payment_processing_completed',
                'payment',
                0,
                ['processing_date' => current_time('Y-m-d')]
            );
            
        } catch (Exception $e) {
            error_log('SMS Payment Processor: Daily processing failed - ' . $e->getMessage());
        }
    }

    /**
     * Identify overdue payments and update their status.
     *
     * @return array Array of overdue payment IDs
     */
    public function identify_overdue_payments() {
        $overdue_invoices = $this->get_overdue_invoices();
        $processed_count = 0;
        
        foreach ($overdue_invoices as $invoice_id) {
            $current_status = get_field('payment_status', $invoice_id);
            
            // Skip if already marked as overdue
            if ($current_status === self::STATUS_OVERDUE) {
                continue;
            }
            
            // Update status to overdue
            update_field('payment_status', self::STATUS_OVERDUE, $invoice_id);
            
            // Log status change
            $this->log_payment_status_change($invoice_id, self::STATUS_OVERDUE, $current_status, 'Automatically identified as overdue');
            
            // Trigger overdue actions
            do_action('sms_payment_marked_overdue', $invoice_id);
            
            $processed_count++;
        }
        
        // Log processing summary
        if ($processed_count > 0) {
            $this->log_activity(
                0,
                'overdue_payments_identified',
                'payment',
                0,
                ['count' => $processed_count, 'date' => current_time('Y-m-d')]
            );
        }
        
        return $overdue_invoices;
    }

    /**
     * Apply penalties to overdue payments.
     *
     * @return array Summary of penalty applications
     */
    public function apply_overdue_penalties() {
        $overdue_invoices = $this->get_overdue_invoices();
        $penalty_summary = [
            'processed_count' => 0,
            'total_penalties' => 0,
            'errors' => []
        ];
        
        foreach ($overdue_invoices as $invoice_id) {
            try {
                $penalty_result = $this->calculate_and_apply_penalties($invoice_id);
                
                if ($penalty_result['penalty_applied'] > 0) {
                    $penalty_summary['processed_count']++;
                    $penalty_summary['total_penalties'] += $penalty_result['penalty_applied'];
                    
                    // Send penalty notification
                    $this->send_penalty_notification($invoice_id, $penalty_result);
                }
                
            } catch (Exception $e) {
                $penalty_summary['errors'][] = [
                    'invoice_id' => $invoice_id,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        // Log penalty processing summary
        if ($penalty_summary['processed_count'] > 0) {
            $this->log_activity(
                0,
                'penalties_applied',
                'payment',
                0,
                $penalty_summary
            );
        }
        
        return $penalty_summary;
    }

    /**
     * Calculate and apply penalties for an overdue invoice.
     *
     * @param int $invoice_id Invoice ID
     * @return array Penalty calculation results
     */
    private function calculate_and_apply_penalties($invoice_id) {
        $due_date = get_field('due_date', $invoice_id);
        $current_penalties = get_field('penalties', $invoice_id) ?? 0;
        $days_overdue = $this->calculate_days_overdue($due_date);
        
        $penalty_result = [
            'invoice_id' => $invoice_id,
            'days_overdue' => $days_overdue,
            'previous_penalties' => $current_penalties,
            'new_penalties' => 0,
            'penalty_applied' => 0,
            'penalty_details' => []
        ];
        
        // Get invoice items to calculate penalties
        $invoice_items = get_field('invoice_items', $invoice_id);
        $total_new_penalties = 0;
        
        foreach ($invoice_items as $item) {
            $fee_id = $item['item_fee'];
            $penalty_settings = get_field('late_payment_penalty', $fee_id);
            
            if (empty($penalty_settings['penalty_enabled'])) {
                continue;
            }
            
            $grace_days = intval($penalty_settings['penalty_grace_days'] ?? 0);
            
            if ($days_overdue > $grace_days) {
                $penalty_amount = $this->calculate_penalty_amount(
                    $penalty_settings,
                    $item['item_total'],
                    $days_overdue - $grace_days
                );
                
                if ($penalty_amount > 0) {
                    $total_new_penalties += $penalty_amount;
                    
                    $penalty_result['penalty_details'][] = [
                        'fee_id' => $fee_id,
                        'fee_name' => get_the_title($fee_id),
                        'base_amount' => $item['item_total'],
                        'penalty_type' => $penalty_settings['penalty_type'],
                        'penalty_amount' => $penalty_amount,
                        'days_overdue' => $days_overdue - $grace_days
                    ];
                }
            }
        }
        
        // Apply penalties if there are new ones
        if ($total_new_penalties > $current_penalties) {
            $penalty_applied = $total_new_penalties - $current_penalties;
            
            // Update invoice with new penalty amount
            update_field('penalties', $total_new_penalties, $invoice_id);
            
            // Recalculate totals
            $this->recalculate_invoice_totals($invoice_id);
            
            // Record penalty application
            $this->record_penalty_application($invoice_id, $penalty_applied, $penalty_result['penalty_details']);
            
            $penalty_result['new_penalties'] = $total_new_penalties;
            $penalty_result['penalty_applied'] = $penalty_applied;
        }
        
        return $penalty_result;
    }

    /**
     * Send automated payment reminders based on due dates and overdue status.
     *
     * @return array Summary of reminders sent
     */
    public function send_automated_reminders() {
        $reminder_summary = [
            'upcoming_reminders' => 0,
            'due_reminders' => 0,
            'overdue_reminders' => 0,
            'final_reminders' => 0,
            'errors' => []
        ];
        
        // Get reminder settings
        $reminder_settings = get_option('sms_payment_reminder_settings', [
            'upcoming_days' => 3,
            'overdue_intervals' => [1, 7, 14, 30],
            'final_notice_days' => 45,
            'enabled_methods' => ['email', 'sms']
        ]);
        
        try {
            // Send upcoming payment reminders
            $upcoming_invoices = $this->get_upcoming_due_invoices($reminder_settings['upcoming_days']);
            foreach ($upcoming_invoices as $invoice_id) {
                if ($this->should_send_reminder($invoice_id, self::REMINDER_UPCOMING)) {
                    $this->send_payment_reminder($invoice_id, self::REMINDER_UPCOMING, $reminder_settings);
                    $reminder_summary['upcoming_reminders']++;
                }
            }
            
            // Send due payment reminders
            $due_invoices = $this->get_due_today_invoices();
            foreach ($due_invoices as $invoice_id) {
                if ($this->should_send_reminder($invoice_id, self::REMINDER_DUE)) {
                    $this->send_payment_reminder($invoice_id, self::REMINDER_DUE, $reminder_settings);
                    $reminder_summary['due_reminders']++;
                }
            }
            
            // Send overdue payment reminders
            $overdue_invoices = $this->get_overdue_invoices();
            foreach ($overdue_invoices as $invoice_id) {
                $days_overdue = $this->calculate_days_overdue(get_field('due_date', $invoice_id));
                
                // Check if it's time for an overdue reminder
                if (in_array($days_overdue, $reminder_settings['overdue_intervals'])) {
                    if ($this->should_send_reminder($invoice_id, self::REMINDER_OVERDUE)) {
                        $this->send_payment_reminder($invoice_id, self::REMINDER_OVERDUE, $reminder_settings);
                        $reminder_summary['overdue_reminders']++;
                    }
                }
                
                // Send final notice
                if ($days_overdue >= $reminder_settings['final_notice_days']) {
                    if ($this->should_send_reminder($invoice_id, self::REMINDER_FINAL)) {
                        $this->send_payment_reminder($invoice_id, self::REMINDER_FINAL, $reminder_settings);
                        $reminder_summary['final_reminders']++;
                    }
                }
            }
            
        } catch (Exception $e) {
            $reminder_summary['errors'][] = $e->getMessage();
        }
        
        // Log reminder summary
        if (array_sum(array_slice($reminder_summary, 0, 4)) > 0) {
            $this->log_activity(
                0,
                'payment_reminders_sent',
                'payment',
                0,
                $reminder_summary
            );
        }
        
        return $reminder_summary;
    }

    /**
     * Send a payment reminder for a specific invoice.
     *
     * @param int    $invoice_id Invoice ID
     * @param string $reminder_type Reminder type
     * @param array  $settings Reminder settings
     * @return bool Success status
     */
    private function send_payment_reminder($invoice_id, $reminder_type, $settings) {
        $student_id = get_field('student', $invoice_id);
        if (!$student_id) {
            return false;
        }
        
        $student_data = $this->get_student_contact_data($student_id);
        $invoice_data = $this->get_invoice_reminder_data($invoice_id);
        
        $reminder_sent = false;
        
        // Send email reminder
        if (in_array('email', $settings['enabled_methods']) && !empty($student_data['parent_email'])) {
            $email_result = $this->send_email_reminder($student_data, $invoice_data, $reminder_type);
            if ($email_result) {
                $reminder_sent = true;
            }
        }
        
        // Send SMS reminder
        if (in_array('sms', $settings['enabled_methods']) && !empty($student_data['parent_phone'])) {
            $sms_result = $this->send_sms_reminder($student_data, $invoice_data, $reminder_type);
            if ($sms_result) {
                $reminder_sent = true;
            }
        }
        
        // Record reminder sent
        if ($reminder_sent) {
            $this->record_reminder_sent($invoice_id, $reminder_type);
        }
        
        return $reminder_sent;
    }

    /**
     * Update payment history when a transaction is completed.
     *
     * @param int   $transaction_id Transaction ID
     * @param array $transaction_data Transaction data
     */
    public function update_payment_history($transaction_id, $transaction_data) {
        $invoice_id = get_field('invoice', $transaction_id);
        if (!$invoice_id) {
            return;
        }
        
        // Get current payment history
        $payment_history = get_field('payment_history', $invoice_id) ?? [];
        
        // Add new payment record
        $payment_record = [
            'transaction_id' => $transaction_id,
            'payment_date' => current_time('mysql'),
            'amount' => get_field('amount', $transaction_id),
            'payment_method' => get_field('payment_method', $transaction_id),
            'gateway_reference' => get_field('gateway_reference', $transaction_id),
            'status' => 'completed'
        ];
        
        $payment_history[] = $payment_record;
        update_field('payment_history', $payment_history, $invoice_id);
        
        // Update invoice payment totals
        $this->update_invoice_payment_totals($invoice_id);
        
        // Check if invoice is fully paid
        $this->check_invoice_payment_completion($invoice_id);
    }

    /**
     * Generate payment history report for a student or invoice.
     *
     * @param array $filters Report filters
     * @return array Payment history report data
     */
    public function generate_payment_history_report($filters = []) {
        $report_data = [
            'summary' => [
                'total_payments' => 0,
                'total_amount' => 0,
                'payment_methods' => [],
                'date_range' => []
            ],
            'payments' => [],
            'filters_applied' => $filters
        ];
        
        // Build query arguments
        $query_args = [
            'post_type' => 'sms_transactions',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => 'transaction_status',
                    'value' => 'completed',
                    'compare' => '='
                ]
            ]
        ];
        
        // Apply filters
        if (!empty($filters['student_id'])) {
            $query_args['meta_query'][] = [
                'key' => 'student',
                'value' => $filters['student_id'],
                'compare' => '='
            ];
        }
        
        if (!empty($filters['invoice_id'])) {
            $query_args['meta_query'][] = [
                'key' => 'invoice',
                'value' => $filters['invoice_id'],
                'compare' => '='
            ];
        }
        
        if (!empty($filters['date_from'])) {
            $query_args['meta_query'][] = [
                'key' => 'transaction_date',
                'value' => $filters['date_from'],
                'compare' => '>=',
                'type' => 'DATE'
            ];
        }
        
        if (!empty($filters['date_to'])) {
            $query_args['meta_query'][] = [
                'key' => 'transaction_date',
                'value' => $filters['date_to'],
                'compare' => '<=',
                'type' => 'DATE'
            ];
        }
        
        // Execute query
        $transactions = get_posts($query_args);
        
        foreach ($transactions as $transaction) {
            $transaction_data = [
                'id' => $transaction->ID,
                'date' => get_field('transaction_date', $transaction->ID),
                'amount' => get_field('amount', $transaction->ID),
                'payment_method' => get_field('payment_method', $transaction->ID),
                'student_id' => get_field('student', $transaction->ID),
                'student_name' => get_field('full_name', get_field('student', $transaction->ID)),
                'invoice_id' => get_field('invoice', $transaction->ID),
                'invoice_number' => get_field('invoice_number', get_field('invoice', $transaction->ID)),
                'gateway_reference' => get_field('gateway_reference', $transaction->ID),
                'receipt_number' => get_field('receipt_number', $transaction->ID)
            ];
            
            $report_data['payments'][] = $transaction_data;
            
            // Update summary
            $report_data['summary']['total_payments']++;
            $report_data['summary']['total_amount'] += $transaction_data['amount'];
            
            // Track payment methods
            $method = $transaction_data['payment_method'];
            if (!isset($report_data['summary']['payment_methods'][$method])) {
                $report_data['summary']['payment_methods'][$method] = [
                    'count' => 0,
                    'amount' => 0
                ];
            }
            $report_data['summary']['payment_methods'][$method]['count']++;
            $report_data['summary']['payment_methods'][$method]['amount'] += $transaction_data['amount'];
        }
        
        // Set date range
        if (!empty($report_data['payments'])) {
            $dates = array_column($report_data['payments'], 'date');
            $report_data['summary']['date_range'] = [
                'from' => min($dates),
                'to' => max($dates)
            ];
        }
        
        return $report_data;
    }

    /**
     * Get overdue invoices.
     *
     * @return array Array of overdue invoice IDs
     */
    public function get_overdue_invoices() {
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
     * Get invoices due in the next N days.
     *
     * @param int $days Number of days ahead to look
     * @return array Array of invoice IDs
     */
    public function get_upcoming_due_invoices($days) {
        $future_date = date('Y-m-d', strtotime("+{$days} days"));
        
        $args = [
            'post_type' => 'sms_invoices',
            'post_status' => 'publish',
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => 'due_date',
                    'value' => [current_time('Y-m-d'), $future_date],
                    'compare' => 'BETWEEN',
                    'type' => 'DATE'
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
     * Get invoices due today.
     *
     * @return array Array of invoice IDs
     */
    public function get_due_today_invoices() {
        $today = current_time('Y-m-d');
        
        $args = [
            'post_type' => 'sms_invoices',
            'post_status' => 'publish',
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => 'due_date',
                    'value' => $today,
                    'compare' => '=',
                    'type' => 'DATE'
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
     * Calculate days overdue from due date.
     *
     * @param string $due_date Due date
     * @return int Days overdue
     */
    public function calculate_days_overdue($due_date) {
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
                
            case 'percentage_daily':
                return ($base_amount * $penalty_value / 100) * $days_overdue;
                
            default:
                return 0;
        }
    }

    /**
     * Recalculate invoice totals after penalty application.
     *
     * @param int $invoice_id Invoice ID
     */
    private function recalculate_invoice_totals($invoice_id) {
        $subtotal = get_field('subtotal', $invoice_id) ?? 0;
        $total_discount = get_field('total_discount', $invoice_id) ?? 0;
        $penalties = get_field('penalties', $invoice_id) ?? 0;
        $amount_paid = get_field('amount_paid', $invoice_id) ?? 0;
        
        $new_total = $subtotal - $total_discount + $penalties;
        $new_balance = max(0, $new_total - $amount_paid);
        
        update_field('total_amount', $new_total, $invoice_id);
        update_field('balance_due', $new_balance, $invoice_id);
        
        // Update payment status based on balance
        if ($new_balance == 0) {
            update_field('payment_status', 'paid', $invoice_id);
        } elseif ($amount_paid > 0) {
            update_field('payment_status', self::STATUS_PARTIAL, $invoice_id);
        }
    }

    /**
     * Record penalty application in invoice history.
     *
     * @param int   $invoice_id Invoice ID
     * @param float $penalty_amount Penalty amount applied
     * @param array $penalty_details Penalty calculation details
     */
    private function record_penalty_application($invoice_id, $penalty_amount, $penalty_details) {
        $penalty_history = get_field('penalty_history', $invoice_id) ?? [];
        
        $penalty_record = [
            'date' => current_time('mysql'),
            'amount' => $penalty_amount,
            'details' => $penalty_details,
            'applied_by' => 'system'
        ];
        
        $penalty_history[] = $penalty_record;
        update_field('penalty_history', $penalty_history, $invoice_id);
    }

    /**
     * Check if a reminder should be sent for an invoice.
     *
     * @param int    $invoice_id Invoice ID
     * @param string $reminder_type Reminder type
     * @return bool Whether reminder should be sent
     */
    private function should_send_reminder($invoice_id, $reminder_type) {
        $reminder_history = get_field('reminder_history', $invoice_id) ?? [];
        $today = current_time('Y-m-d');
        
        // Check if reminder was already sent today
        foreach ($reminder_history as $reminder) {
            if ($reminder['type'] === $reminder_type && 
                date('Y-m-d', strtotime($reminder['sent_date'])) === $today) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Record that a reminder was sent.
     *
     * @param int    $invoice_id Invoice ID
     * @param string $reminder_type Reminder type
     */
    private function record_reminder_sent($invoice_id, $reminder_type) {
        $reminder_history = get_field('reminder_history', $invoice_id) ?? [];
        
        $reminder_record = [
            'type' => $reminder_type,
            'sent_date' => current_time('mysql'),
            'sent_by' => 'system'
        ];
        
        $reminder_history[] = $reminder_record;
        update_field('reminder_history', $reminder_history, $invoice_id);
    }

    /**
     * Get student contact data for reminders.
     *
     * @param int $student_id Student ID
     * @return array Student contact data
     */
    public function get_student_contact_data($student_id) {
        return [
            'student_id' => $student_id,
            'student_name' => get_field('full_name', $student_id),
            'admission_number' => get_field('admission_number', $student_id),
            'parent_name' => get_field('parent_name', $student_id),
            'parent_email' => get_field('parent_email', $student_id),
            'parent_phone' => get_field('parent_phone', $student_id),
            'class' => get_field('current_class', $student_id)
        ];
    }

    /**
     * Get invoice data for reminders.
     *
     * @param int $invoice_id Invoice ID
     * @return array Invoice reminder data
     */
    public function get_invoice_reminder_data($invoice_id) {
        return [
            'invoice_id' => $invoice_id,
            'invoice_number' => get_field('invoice_number', $invoice_id),
            'due_date' => get_field('due_date', $invoice_id),
            'total_amount' => get_field('total_amount', $invoice_id),
            'balance_due' => get_field('balance_due', $invoice_id),
            'days_overdue' => $this->calculate_days_overdue(get_field('due_date', $invoice_id)),
            'payment_methods' => get_field('payment_methods', $invoice_id),
            'payment_instructions' => get_field('payment_instructions', $invoice_id)
        ];
    }

    /**
     * Send email reminder.
     *
     * @param array  $student_data Student contact data
     * @param array  $invoice_data Invoice data
     * @param string $reminder_type Reminder type
     * @return bool Success status
     */
    private function send_email_reminder($student_data, $invoice_data, $reminder_type) {
        $email_template = $this->get_email_reminder_template($reminder_type);
        $subject = $this->render_template($email_template['subject'], $student_data, $invoice_data);
        $message = $this->render_template($email_template['message'], $student_data, $invoice_data);
        
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        
        return wp_mail($student_data['parent_email'], $subject, $message, $headers);
    }

    /**
     * Send SMS reminder.
     *
     * @param array  $student_data Student contact data
     * @param array  $invoice_data Invoice data
     * @param string $reminder_type Reminder type
     * @return bool Success status
     */
    private function send_sms_reminder($student_data, $invoice_data, $reminder_type) {
        $sms_template = $this->get_sms_reminder_template($reminder_type);
        $message = $this->render_template($sms_template, $student_data, $invoice_data);
        
        // Use SMS communication handler
        $sms_handler = new SMS_Communication_Handler();
        return $sms_handler->send_sms([$student_data['parent_phone']], $message);
    }

    /**
     * Get email reminder template.
     *
     * @param string $reminder_type Reminder type
     * @return array Email template
     */
    private function get_email_reminder_template($reminder_type) {
        $templates = [
            self::REMINDER_UPCOMING => [
                'subject' => 'Payment Reminder: Invoice {invoice_number} Due Soon',
                'message' => 'Dear {parent_name}, this is a reminder that payment for {student_name} (Invoice #{invoice_number}) of KES {balance_due} is due on {due_date}. Please make payment to avoid late fees.'
            ],
            self::REMINDER_DUE => [
                'subject' => 'Payment Due Today: Invoice {invoice_number}',
                'message' => 'Dear {parent_name}, payment for {student_name} (Invoice #{invoice_number}) of KES {balance_due} is due today. Please make payment immediately.'
            ],
            self::REMINDER_OVERDUE => [
                'subject' => 'Overdue Payment: Invoice {invoice_number}',
                'message' => 'Dear {parent_name}, payment for {student_name} (Invoice #{invoice_number}) of KES {balance_due} is now {days_overdue} days overdue. Late fees may apply. Please make payment immediately.'
            ],
            self::REMINDER_FINAL => [
                'subject' => 'Final Notice: Invoice {invoice_number}',
                'message' => 'Dear {parent_name}, this is a final notice for overdue payment for {student_name} (Invoice #{invoice_number}) of KES {balance_due}. Please contact the school office immediately to resolve this matter.'
            ]
        ];
        
        return $templates[$reminder_type] ?? $templates[self::REMINDER_DUE];
    }

    /**
     * Get SMS reminder template.
     *
     * @param string $reminder_type Reminder type
     * @return string SMS template
     */
    private function get_sms_reminder_template($reminder_type) {
        $templates = [
            self::REMINDER_UPCOMING => 'Payment reminder: {student_name} invoice #{invoice_number} of KES {balance_due} due {due_date}. Pay via M-Pesa to avoid late fees.',
            self::REMINDER_DUE => 'Payment due today: {student_name} invoice #{invoice_number} of KES {balance_due}. Pay immediately via M-Pesa.',
            self::REMINDER_OVERDUE => 'Overdue payment: {student_name} invoice #{invoice_number} of KES {balance_due} is {days_overdue} days overdue. Late fees applied. Pay now.',
            self::REMINDER_FINAL => 'FINAL NOTICE: {student_name} invoice #{invoice_number} of KES {balance_due} severely overdue. Contact school office immediately.'
        ];
        
        return $templates[$reminder_type] ?? $templates[self::REMINDER_DUE];
    }

    /**
     * Render template with data placeholders.
     *
     * @param string $template Template string
     * @param array  $student_data Student data
     * @param array  $invoice_data Invoice data
     * @return string Rendered template
     */
    private function render_template($template, $student_data, $invoice_data) {
        $placeholders = array_merge($student_data, $invoice_data);
        
        foreach ($placeholders as $key => $value) {
            $template = str_replace('{' . $key . '}', $value, $template);
        }
        
        return $template;
    }

    /**
     * Update invoice payment totals after a payment.
     *
     * @param int $invoice_id Invoice ID
     */
    private function update_invoice_payment_totals($invoice_id) {
        // Get all completed transactions for this invoice
        $args = [
            'post_type' => 'sms_transactions',
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => 'invoice',
                    'value' => $invoice_id,
                    'compare' => '='
                ],
                [
                    'key' => 'transaction_status',
                    'value' => 'completed',
                    'compare' => '='
                ]
            ],
            'posts_per_page' => -1
        ];
        
        $transactions = get_posts($args);
        $total_paid = 0;
        
        foreach ($transactions as $transaction) {
            $total_paid += get_field('amount', $transaction->ID);
        }
        
        // Update invoice payment fields
        update_field('amount_paid', $total_paid, $invoice_id);
        
        $total_amount = get_field('total_amount', $invoice_id);
        $balance_due = max(0, $total_amount - $total_paid);
        update_field('balance_due', $balance_due, $invoice_id);
        
        // Update payment status
        if ($balance_due == 0) {
            update_field('payment_status', 'paid', $invoice_id);
        } elseif ($total_paid > 0) {
            update_field('payment_status', self::STATUS_PARTIAL, $invoice_id);
        }
    }

    /**
     * Check if invoice is fully paid and trigger completion actions.
     *
     * @param int $invoice_id Invoice ID
     */
    private function check_invoice_payment_completion($invoice_id) {
        $balance_due = get_field('balance_due', $invoice_id);
        
        if ($balance_due == 0) {
            // Mark as fully paid
            update_field('payment_status', 'paid', $invoice_id);
            update_field('paid_date', current_time('mysql'), $invoice_id);
            
            // Trigger completion actions
            do_action('sms_invoice_fully_paid', $invoice_id);
            
            // Send payment confirmation
            $this->send_payment_confirmation($invoice_id);
        }
    }

    /**
     * Send payment confirmation notification.
     *
     * @param int $invoice_id Invoice ID
     */
    private function send_payment_confirmation($invoice_id) {
        $student_id = get_field('student', $invoice_id);
        $student_data = $this->get_student_contact_data($student_id);
        $invoice_data = $this->get_invoice_reminder_data($invoice_id);
        
        // Send confirmation email
        if (!empty($student_data['parent_email'])) {
            $subject = 'Payment Confirmation: Invoice ' . $invoice_data['invoice_number'];
            $message = "Dear {$student_data['parent_name']}, we confirm receipt of payment for {$student_data['student_name']} (Invoice #{$invoice_data['invoice_number']}). Thank you for your payment.";
            
            wp_mail($student_data['parent_email'], $subject, $message);
        }
        
        // Send confirmation SMS
        if (!empty($student_data['parent_phone'])) {
            $sms_message = "Payment confirmed: {$student_data['student_name']} invoice #{$invoice_data['invoice_number']} fully paid. Thank you.";
            
            $sms_handler = new SMS_Communication_Handler();
            $sms_handler->send_sms([$student_data['parent_phone']], $sms_message);
        }
    }

    /**
     * Send penalty notification.
     *
     * @param int   $invoice_id Invoice ID
     * @param array $penalty_result Penalty calculation results
     */
    private function send_penalty_notification($invoice_id, $penalty_result) {
        $student_id = get_field('student', $invoice_id);
        $student_data = $this->get_student_contact_data($student_id);
        $invoice_data = $this->get_invoice_reminder_data($invoice_id);
        
        // Send penalty notification email
        if (!empty($student_data['parent_email'])) {
            $subject = 'Late Payment Penalty Applied: Invoice ' . $invoice_data['invoice_number'];
            $message = "Dear {$student_data['parent_name']}, a late payment penalty of KES {$penalty_result['penalty_applied']} has been applied to {$student_data['student_name']}'s invoice #{$invoice_data['invoice_number']} due to {$penalty_result['days_overdue']} days overdue. New balance: KES {$invoice_data['balance_due']}.";
            
            wp_mail($student_data['parent_email'], $subject, $message);
        }
        
        // Send penalty notification SMS
        if (!empty($student_data['parent_phone'])) {
            $sms_message = "Late fee applied: {$student_data['student_name']} invoice #{$invoice_data['invoice_number']} - KES {$penalty_result['penalty_applied']} penalty. New balance: KES {$invoice_data['balance_due']}.";
            
            $sms_handler = new SMS_Communication_Handler();
            $sms_handler->send_sms([$student_data['parent_phone']], $sms_message);
        }
    }

    /**
     * Log payment status change.
     *
     * @param int    $invoice_id Invoice ID
     * @param string $new_status New status
     * @param string $old_status Old status
     * @param string $reason Reason for change
     */
    private function log_payment_status_change($invoice_id, $new_status, $old_status, $reason) {
        $this->log_activity(
            get_current_user_id(),
            'payment_status_changed',
            'invoice',
            $invoice_id,
            [
                'old_status' => $old_status,
                'new_status' => $new_status,
                'reason' => $reason
            ]
        );
    }

    /**
     * Update payment statuses based on current conditions.
     */
    private function update_payment_statuses() {
        // This method can be expanded to handle various status updates
        // For now, it's a placeholder for future enhancements
    }

    /**
     * Generate daily payment reports.
     */
    private function generate_daily_payment_reports() {
        // Generate summary report for today's payment activities
        $today = current_time('Y-m-d');
        
        $report_data = [
            'date' => $today,
            'payments_received' => $this->get_daily_payments_count($today),
            'total_amount_received' => $this->get_daily_payments_total($today),
            'overdue_invoices' => count($this->get_overdue_invoices()),
            'reminders_sent' => $this->get_daily_reminders_count($today),
            'penalties_applied' => $this->get_daily_penalties_total($today)
        ];
        
        // Store daily report
        update_option('sms_daily_payment_report_' . str_replace('-', '_', $today), $report_data);
        
        // Trigger report generation action
        do_action('sms_daily_payment_report_generated', $report_data);
    }

    /**
     * Get daily payments count.
     *
     * @param string $date Date (Y-m-d format)
     * @return int Payments count
     */
    private function get_daily_payments_count($date) {
        $args = [
            'post_type' => 'sms_transactions',
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => 'transaction_date',
                    'value' => $date,
                    'compare' => 'LIKE'
                ],
                [
                    'key' => 'transaction_status',
                    'value' => 'completed',
                    'compare' => '='
                ]
            ],
            'posts_per_page' => -1
        ];
        
        return count(get_posts($args));
    }

    /**
     * Get daily payments total amount.
     *
     * @param string $date Date (Y-m-d format)
     * @return float Total amount
     */
    private function get_daily_payments_total($date) {
        $args = [
            'post_type' => 'sms_transactions',
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => 'transaction_date',
                    'value' => $date,
                    'compare' => 'LIKE'
                ],
                [
                    'key' => 'transaction_status',
                    'value' => 'completed',
                    'compare' => '='
                ]
            ],
            'posts_per_page' => -1
        ];
        
        $transactions = get_posts($args);
        $total = 0;
        
        foreach ($transactions as $transaction) {
            $total += get_field('amount', $transaction->ID);
        }
        
        return $total;
    }

    /**
     * Get daily reminders count.
     *
     * @param string $date Date (Y-m-d format)
     * @return int Reminders count
     */
    private function get_daily_reminders_count($date) {
        // This would need to be tracked in a separate log or meta field
        // For now, return 0 as placeholder
        return 0;
    }

    /**
     * Get daily penalties total.
     *
     * @param string $date Date (Y-m-d format)
     * @return float Total penalties
     */
    private function get_daily_penalties_total($date) {
        // This would need to be tracked in penalty history
        // For now, return 0 as placeholder
        return 0;
    }

    /**
     * Handle payment completion.
     *
     * @param int   $transaction_id Transaction ID
     * @param array $transaction_data Transaction data
     */
    public function handle_payment_completion($transaction_id, $transaction_data) {
        // Update payment history
        $this->update_payment_history($transaction_id, $transaction_data);
        
        // Log payment completion
        $this->log_activity(
            get_current_user_id(),
            'payment_completed',
            'transaction',
            $transaction_id,
            ['amount' => $transaction_data['amount'] ?? 0]
        );
    }

    /**
     * Handle payment failure.
     *
     * @param int    $transaction_id Transaction ID
     * @param string $failure_reason Failure reason
     */
    public function handle_payment_failure($transaction_id, $failure_reason) {
        // Log payment failure
        $this->log_activity(
            get_current_user_id(),
            'payment_failed',
            'transaction',
            $transaction_id,
            ['reason' => $failure_reason]
        );
        
        // Could trigger retry logic or notifications here
    }

    /**
     * Process overdue payments (additional processing beyond identification).
     */
    public function process_overdue_payments() {
        // This method can be used for additional overdue payment processing
        // such as generating reports, sending escalation notices, etc.
        
        $overdue_invoices = $this->get_overdue_invoices();
        
        foreach ($overdue_invoices as $invoice_id) {
            $days_overdue = $this->calculate_days_overdue(get_field('due_date', $invoice_id));
            
            // Escalate severely overdue payments
            if ($days_overdue > 60) {
                do_action('sms_payment_severely_overdue', $invoice_id, $days_overdue);
            }
        }
    }
}