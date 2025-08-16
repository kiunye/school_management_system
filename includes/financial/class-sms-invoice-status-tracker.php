<?php
/**
 * Invoice Status Tracking System
 *
 * Handles invoice status management, tracking, and automated
 * status updates based on payments and due dates.
 *
 * @package    School_Management_System
 * @subpackage School_Management_System/includes/financial
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Invoice Status Tracker Class
 */
class SMS_Invoice_Status_Tracker extends SMS_Base {

    /**
     * Available invoice statuses
     */
    const STATUS_DRAFT = 'draft';
    const STATUS_SENT = 'sent';
    const STATUS_VIEWED = 'viewed';
    const STATUS_PARTIAL = 'partial';
    const STATUS_PAID = 'paid';
    const STATUS_OVERDUE = 'overdue';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * Status transition rules
     */
    private $status_transitions = [
        self::STATUS_DRAFT => [self::STATUS_SENT, self::STATUS_CANCELLED],
        self::STATUS_SENT => [self::STATUS_VIEWED, self::STATUS_PARTIAL, self::STATUS_PAID, self::STATUS_OVERDUE, self::STATUS_CANCELLED],
        self::STATUS_VIEWED => [self::STATUS_PARTIAL, self::STATUS_PAID, self::STATUS_OVERDUE, self::STATUS_CANCELLED],
        self::STATUS_PARTIAL => [self::STATUS_PAID, self::STATUS_OVERDUE, self::STATUS_CANCELLED],
        self::STATUS_PAID => [self::STATUS_CANCELLED], // Only allow cancellation of paid invoices
        self::STATUS_OVERDUE => [self::STATUS_PARTIAL, self::STATUS_PAID, self::STATUS_CANCELLED],
        self::STATUS_CANCELLED => [] // No transitions from cancelled
    ];

    /**
     * Initialize the class and set its properties.
     */
    public function __construct() {
        parent::__construct();
        
        // Hook into invoice status changes
        add_action('sms_invoice_status_changed', [$this, 'handle_status_change'], 10, 3);
        add_action('sms_payment_recorded', [$this, 'update_payment_status'], 10, 2);
        
        // Scheduled tasks
        add_action('sms_daily_invoice_processing', [$this, 'process_daily_status_updates']);
        add_action('sms_check_overdue_invoices', [$this, 'check_overdue_invoices']);
        
        // Admin hooks
        add_action('admin_post_sms_update_invoice_status', [$this, 'handle_manual_status_update']);
        add_action('wp_ajax_sms_get_invoice_status_history', [$this, 'ajax_get_status_history']);
        
        // Automatic status detection
        add_action('init', [$this, 'schedule_status_checks']);
    }

    /**
     * Update invoice status with validation and tracking.
     *
     * @param int    $invoice_id Invoice ID
     * @param string $new_status New status
     * @param string $note       Optional note for the change
     * @param int    $user_id    User making the change (0 for system)
     * @return bool|WP_Error Success or error
     */
    public function update_status($invoice_id, $new_status, $note = '', $user_id = null) {
        try {
            // Validate invoice exists
            if (!$this->invoice_exists($invoice_id)) {
                return new WP_Error('invoice_not_found', __('Invoice not found.', 'school-management-system'));
            }

            // Validate status
            if (!$this->is_valid_status($new_status)) {
                return new WP_Error('invalid_status', __('Invalid invoice status.', 'school-management-system'));
            }

            $current_status = get_field('invoice_status', $invoice_id);
            
            // Check if status change is allowed
            if (!$this->is_status_transition_allowed($current_status, $new_status)) {
                return new WP_Error(
                    'invalid_transition',
                    sprintf(
                        __('Cannot change status from %s to %s.', 'school-management-system'),
                        $current_status,
                        $new_status
                    )
                );
            }

            // No change needed
            if ($current_status === $new_status) {
                return true;
            }

            // Get user ID if not provided
            if ($user_id === null) {
                $user_id = get_current_user_id();
            }

            // Update status
            update_field('invoice_status', $new_status, $invoice_id);

            // Record status history
            $this->record_status_change($invoice_id, $current_status, $new_status, $note, $user_id);

            // Trigger status-specific actions
            $this->trigger_status_actions($invoice_id, $new_status, $current_status);

            // Fire action hook
            do_action('sms_invoice_status_changed', $invoice_id, $new_status, $current_status);

            return true;

        } catch (Exception $e) {
            return new WP_Error('status_update_failed', $e->getMessage());
        }
    }

    /**
     * Handle payment recording and update invoice status accordingly.
     *
     * @param int   $invoice_id Invoice ID
     * @param float $payment_amount Payment amount
     */
    public function update_payment_status($invoice_id, $payment_amount) {
        $total_amount = floatval(get_field('total_amount', $invoice_id));
        $current_paid = floatval(get_field('amount_paid', $invoice_id));
        $new_paid_amount = $current_paid + $payment_amount;
        
        // Update payment amounts
        update_field('amount_paid', $new_paid_amount, $invoice_id);
        update_field('balance_due', $total_amount - $new_paid_amount, $invoice_id);

        // Determine new payment status
        $payment_status = 'unpaid';
        $invoice_status = get_field('invoice_status', $invoice_id);

        if ($new_paid_amount >= $total_amount) {
            $payment_status = 'paid';
            $new_invoice_status = self::STATUS_PAID;
        } elseif ($new_paid_amount > 0) {
            $payment_status = 'partial';
            $new_invoice_status = self::STATUS_PARTIAL;
        } else {
            $payment_status = 'unpaid';
            $new_invoice_status = $invoice_status; // Keep current status
        }

        // Update payment status
        update_field('payment_status', $payment_status, $invoice_id);

        // Update invoice status if needed
        if ($new_invoice_status !== $invoice_status) {
            $this->update_status(
                $invoice_id,
                $new_invoice_status,
                sprintf(__('Payment of %s recorded', 'school-management-system'), $this->format_currency($payment_amount)),
                0 // System update
            );
        }
    }

    /**
     * Get invoice status history.
     *
     * @param int $invoice_id Invoice ID
     * @return array Status history
     */
    public function get_status_history($invoice_id) {
        $history = get_field('status_history', $invoice_id);
        
        if (empty($history)) {
            return [];
        }

        // Sort by date (newest first)
        usort($history, function($a, $b) {
            return strtotime($b['changed_date']) - strtotime($a['changed_date']);
        });

        // Add user names and format dates
        foreach ($history as &$entry) {
            if ($entry['changed_by'] > 0) {
                $user = get_user_by('id', $entry['changed_by']);
                $entry['changed_by_name'] = $user ? $user->display_name : __('Unknown User', 'school-management-system');
            } else {
                $entry['changed_by_name'] = __('System', 'school-management-system');
            }
            
            $entry['formatted_date'] = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($entry['changed_date']));
            $entry['status_label'] = $this->get_status_label($entry['status']);
            $entry['previous_status_label'] = $this->get_status_label($entry['previous_status']);
        }

        return $history;
    }

    /**
     * Get human-readable status label.
     *
     * @param string $status Status code
     * @return string Status label
     */
    public function get_status_label($status) {
        $labels = [
            self::STATUS_DRAFT => __('Draft', 'school-management-system'),
            self::STATUS_SENT => __('Sent', 'school-management-system'),
            self::STATUS_VIEWED => __('Viewed', 'school-management-system'),
            self::STATUS_PARTIAL => __('Partially Paid', 'school-management-system'),
            self::STATUS_PAID => __('Paid', 'school-management-system'),
            self::STATUS_OVERDUE => __('Overdue', 'school-management-system'),
            self::STATUS_CANCELLED => __('Cancelled', 'school-management-system')
        ];

        return $labels[$status] ?? ucfirst($status);
    }

    /**
     * Get available status transitions for current status.
     *
     * @param string $current_status Current status
     * @return array Available transitions
     */
    public function get_available_transitions($current_status) {
        return $this->status_transitions[$current_status] ?? [];
    }

    /**
     * Process daily status updates (scheduled task).
     */
    public function process_daily_status_updates() {
        // Check for overdue invoices
        $this->check_overdue_invoices();
        
        // Update payment statuses based on recent payments
        $this->sync_payment_statuses();
        
        // Clean up old status history (keep last 50 entries per invoice)
        $this->cleanup_status_history();
    }

    /**
     * Check and mark overdue invoices.
     */
    public function check_overdue_invoices() {
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
                    'value' => [self::STATUS_SENT, self::STATUS_VIEWED, self::STATUS_PARTIAL],
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

        $overdue_invoices = get_posts($args);
        
        foreach ($overdue_invoices as $invoice_id) {
            $due_date = get_field('due_date', $invoice_id);
            $days_overdue = $this->calculate_days_overdue($due_date);
            
            $this->update_status(
                $invoice_id,
                self::STATUS_OVERDUE,
                sprintf(__('Automatically marked as overdue (%d days past due)', 'school-management-system'), $days_overdue),
                0 // System update
            );
        }
    }

    /**
     * Sync payment statuses with actual payment records.
     */
    private function sync_payment_statuses() {
        // Get invoices that might need status updates
        $args = [
            'post_type' => 'sms_invoices',
            'post_status' => 'publish',
            'meta_query' => [
                [
                    'key' => 'invoice_status',
                    'value' => [self::STATUS_SENT, self::STATUS_VIEWED, self::STATUS_PARTIAL, self::STATUS_OVERDUE],
                    'compare' => 'IN'
                ]
            ],
            'fields' => 'ids',
            'posts_per_page' => 100 // Process in batches
        ];

        $invoices = get_posts($args);
        
        foreach ($invoices as $invoice_id) {
            // Recalculate payment amounts from transaction records
            $actual_paid = $this->calculate_actual_payments($invoice_id);
            $recorded_paid = floatval(get_field('amount_paid', $invoice_id));
            
            // Update if there's a discrepancy
            if (abs($actual_paid - $recorded_paid) > 0.01) {
                $this->update_payment_status($invoice_id, $actual_paid - $recorded_paid);
            }
        }
    }

    /**
     * Calculate actual payments from transaction records.
     *
     * @param int $invoice_id Invoice ID
     * @return float Total paid amount
     */
    private function calculate_actual_payments($invoice_id) {
        $args = [
            'post_type' => 'sms_transactions',
            'post_status' => 'publish',
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => 'invoice_id',
                    'value' => $invoice_id,
                    'compare' => '='
                ],
                [
                    'key' => 'payment_status',
                    'value' => 'completed',
                    'compare' => '='
                ]
            ],
            'fields' => 'ids',
            'posts_per_page' => -1
        ];

        $transactions = get_posts($args);
        $total_paid = 0;

        foreach ($transactions as $transaction_id) {
            $amount = floatval(get_field('amount', $transaction_id));
            $total_paid += $amount;
        }

        return $total_paid;
    }

    /**
     * Clean up old status history entries.
     */
    private function cleanup_status_history() {
        $args = [
            'post_type' => 'sms_invoices',
            'post_status' => 'publish',
            'fields' => 'ids',
            'posts_per_page' => 50 // Process in batches
        ];

        $invoices = get_posts($args);
        
        foreach ($invoices as $invoice_id) {
            $history = get_field('status_history', $invoice_id);
            
            if (is_array($history) && count($history) > 50) {
                // Sort by date and keep only the latest 50 entries
                usort($history, function($a, $b) {
                    return strtotime($b['changed_date']) - strtotime($a['changed_date']);
                });
                
                $trimmed_history = array_slice($history, 0, 50);
                update_field('status_history', $trimmed_history, $invoice_id);
            }
        }
    }

    /**
     * Record status change in history.
     *
     * @param int    $invoice_id      Invoice ID
     * @param string $previous_status Previous status
     * @param string $new_status      New status
     * @param string $note           Change note
     * @param int    $user_id        User ID making the change
     */
    private function record_status_change($invoice_id, $previous_status, $new_status, $note, $user_id) {
        $history = get_field('status_history', $invoice_id) ?? [];
        
        $history[] = [
            'status' => $new_status,
            'previous_status' => $previous_status,
            'changed_by' => $user_id,
            'changed_date' => current_time('mysql'),
            'note' => $note,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? ''
        ];

        update_field('status_history', $history, $invoice_id);
    }

    /**
     * Trigger status-specific actions.
     *
     * @param int    $invoice_id      Invoice ID
     * @param string $new_status      New status
     * @param string $previous_status Previous status
     */
    private function trigger_status_actions($invoice_id, $new_status, $previous_status) {
        switch ($new_status) {
            case self::STATUS_SENT:
                // Send invoice to parent/student
                do_action('sms_send_invoice_notification', $invoice_id);
                break;

            case self::STATUS_OVERDUE:
                // Send overdue notification and apply penalties
                do_action('sms_invoice_overdue', $invoice_id);
                break;

            case self::STATUS_PAID:
                // Send payment confirmation
                do_action('sms_invoice_paid', $invoice_id);
                break;

            case self::STATUS_CANCELLED:
                // Handle cancellation
                do_action('sms_invoice_cancelled', $invoice_id);
                break;
        }
    }

    /**
     * Check if status transition is allowed.
     *
     * @param string $current_status Current status
     * @param string $new_status     New status
     * @return bool Whether transition is allowed
     */
    private function is_status_transition_allowed($current_status, $new_status) {
        if (!isset($this->status_transitions[$current_status])) {
            return false;
        }

        return in_array($new_status, $this->status_transitions[$current_status]);
    }

    /**
     * Check if status is valid.
     *
     * @param string $status Status to check
     * @return bool Whether status is valid
     */
    private function is_valid_status($status) {
        $valid_statuses = [
            self::STATUS_DRAFT,
            self::STATUS_SENT,
            self::STATUS_VIEWED,
            self::STATUS_PARTIAL,
            self::STATUS_PAID,
            self::STATUS_OVERDUE,
            self::STATUS_CANCELLED
        ];

        return in_array($status, $valid_statuses);
    }

    /**
     * Check if invoice exists.
     *
     * @param int $invoice_id Invoice ID
     * @return bool Whether invoice exists
     */
    private function invoice_exists($invoice_id) {
        $post = get_post($invoice_id);
        return $post && $post->post_type === 'sms_invoices';
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
     * Handle manual status update from admin.
     */
    public function handle_manual_status_update() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_update_invoice_status')) {
            wp_die(__('Security check failed.', 'school-management-system'));
        }

        // Check permissions
        if (!current_user_can('manage_invoices')) {
            wp_die(__('Insufficient permissions.', 'school-management-system'));
        }

        $invoice_id = intval($_POST['invoice_id']);
        $new_status = sanitize_text_field($_POST['new_status']);
        $note = sanitize_textarea_field($_POST['note'] ?? '');

        $result = $this->update_status($invoice_id, $new_status, $note);

        if (is_wp_error($result)) {
            wp_redirect(add_query_arg([
                'post' => $invoice_id,
                'action' => 'edit',
                'message' => 'status_update_failed',
                'error' => urlencode($result->get_error_message())
            ], admin_url('post.php')));
        } else {
            wp_redirect(add_query_arg([
                'post' => $invoice_id,
                'action' => 'edit',
                'message' => 'status_updated'
            ], admin_url('post.php')));
        }
        exit;
    }

    /**
     * AJAX handler for getting status history.
     */
    public function ajax_get_status_history() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_get_status_history')) {
            wp_send_json_error(__('Security check failed.', 'school-management-system'));
        }

        // Check permissions
        if (!current_user_can('manage_invoices')) {
            wp_send_json_error(__('Insufficient permissions.', 'school-management-system'));
        }

        $invoice_id = intval($_POST['invoice_id']);
        $history = $this->get_status_history($invoice_id);

        wp_send_json_success(['history' => $history]);
    }

    /**
     * Schedule status check tasks.
     */
    public function schedule_status_checks() {
        if (!wp_next_scheduled('sms_daily_invoice_processing')) {
            wp_schedule_event(time(), 'daily', 'sms_daily_invoice_processing');
        }

        if (!wp_next_scheduled('sms_check_overdue_invoices')) {
            wp_schedule_event(time(), 'twicedaily', 'sms_check_overdue_invoices');
        }
    }
}