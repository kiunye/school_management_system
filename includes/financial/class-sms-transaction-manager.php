<?php
/**
 * Transaction Manager
 *
 * Handles payment transaction recording, status tracking, and receipt generation
 *
 * @package SchoolManagementSystem
 * @subpackage Financial
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Transaction Manager Class
 */
class SMS_Transaction_Manager {
    
    /**
     * Instance of this class
     *
     * @var SMS_Transaction_Manager
     */
    private static $instance = null;
    
    /**
     * Transaction statuses
     */
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_REFUNDED = 'refunded';
    const STATUS_DISPUTED = 'disputed';
    
    /**
     * Verification statuses
     */
    const VERIFICATION_UNVERIFIED = 'unverified';
    const VERIFICATION_VERIFIED = 'verified';
    const VERIFICATION_FAILED = 'failed_verification';
    const VERIFICATION_MANUAL = 'manual_verification';
    
    /**
     * Get singleton instance
     *
     * @return SMS_Transaction_Manager
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init();
    }
    
    /**
     * Initialize the transaction manager
     */
    private function init() {
        // Hook into payment gateway callbacks
        add_action('sms_payment_gateway_callback_processed', array($this, 'handle_gateway_callback'), 10, 3);
        
        // Schedule automatic status updates
        add_action('sms_update_transaction_statuses', array($this, 'update_pending_transactions'));
        
        // Hook into transaction status changes
        add_action('sms_transaction_status_changed', array($this, 'handle_status_change'), 10, 4);
        
        // Auto-generate receipts for completed payments
        add_action('sms_transaction_completed', array($this, 'auto_generate_receipt'), 10, 2);
        
        // Schedule receipt sending
        add_action('sms_send_transaction_receipt', array($this, 'send_receipt'), 10, 2);
    }
    
    /**
     * Create a new transaction record
     *
     * @param array $transaction_data Transaction data
     * @return int|WP_Error Transaction post ID or error
     */
    public function create_transaction($transaction_data) {
        // Validate required fields
        $validation = $this->validate_transaction_data($transaction_data);
        if (is_wp_error($validation)) {
            return $validation;
        }
        
        // Prepare post data
        $post_data = array(
            'post_type' => 'sms_transactions',
            'post_status' => 'publish',
            'post_title' => $this->generate_transaction_title($transaction_data),
            'post_content' => isset($transaction_data['description']) ? $transaction_data['description'] : '',
            'meta_input' => $this->prepare_transaction_meta($transaction_data)
        );
        
        // Create the transaction post
        $transaction_id = wp_insert_post($post_data);
        
        if (is_wp_error($transaction_id)) {
            return $transaction_id;
        }
        
        // Generate and save transaction number
        $transaction_number = $this->generate_transaction_number($transaction_id);
        update_field('transaction_number', $transaction_number, $transaction_id);
        
        // Log transaction creation
        $this->log_transaction_activity($transaction_id, 'created', 'Transaction created');
        
        // Trigger action for other components
        do_action('sms_transaction_created', $transaction_id, $transaction_data);
        
        return $transaction_id;
    }
    
    /**
     * Update transaction status
     *
     * @param int $transaction_id Transaction ID
     * @param string $new_status New status
     * @param string $reason Reason for status change
     * @param array $additional_data Additional data to update
     * @return bool|WP_Error
     */
    public function update_transaction_status($transaction_id, $new_status, $reason = '', $additional_data = array()) {
        if (!$this->transaction_exists($transaction_id)) {
            return new WP_Error('transaction_not_found', 'Transaction not found');
        }
        
        $old_status = get_field('transaction_status', $transaction_id);
        
        // Update status
        update_field('transaction_status', $new_status, $transaction_id);
        
        // Update additional data if provided
        foreach ($additional_data as $field => $value) {
            update_field($field, $value, $transaction_id);
        }
        
        // Add processing note if reason provided
        if (!empty($reason)) {
            $this->add_processing_note($transaction_id, $reason);
        }
        
        // Log status change
        $this->log_transaction_activity($transaction_id, 'status_changed', 
            "Status changed from {$old_status} to {$new_status}. Reason: {$reason}");
        
        // Trigger status change action
        do_action('sms_transaction_status_changed', $transaction_id, $new_status, $old_status, $reason);
        
        // Trigger specific status actions
        switch ($new_status) {
            case self::STATUS_COMPLETED:
                do_action('sms_transaction_completed', $transaction_id, $additional_data);
                break;
            case self::STATUS_FAILED:
                do_action('sms_transaction_failed', $transaction_id, $reason);
                break;
            case self::STATUS_REFUNDED:
                do_action('sms_transaction_refunded', $transaction_id, $additional_data);
                break;
        }
        
        return true;
    }
    
    /**
     * Record gateway-specific transaction data
     *
     * @param int $transaction_id Transaction ID
     * @param string $gateway_id Gateway identifier
     * @param array $gateway_data Gateway response data
     * @return bool|WP_Error
     */
    public function record_gateway_data($transaction_id, $gateway_id, $gateway_data) {
        if (!$this->transaction_exists($transaction_id)) {
            return new WP_Error('transaction_not_found', 'Transaction not found');
        }
        
        // Update gateway fields
        update_field('gateway_name', $gateway_id, $transaction_id);
        
        if (isset($gateway_data['transaction_id'])) {
            update_field('gateway_transaction_id', $gateway_data['transaction_id'], $transaction_id);
        }
        
        if (isset($gateway_data['reference'])) {
            update_field('gateway_reference', $gateway_data['reference'], $transaction_id);
        }
        
        if (isset($gateway_data['phone_number'])) {
            update_field('phone_number', $gateway_data['phone_number'], $transaction_id);
        }
        
        // Store full gateway response
        update_field('gateway_response', json_encode($gateway_data), $transaction_id);
        
        // Log gateway data recording
        $this->log_transaction_activity($transaction_id, 'gateway_data_recorded', 
            "Gateway data recorded for {$gateway_id}");
        
        return true;
    }
    
    /**
     * Verify transaction with payment gateway
     *
     * @param int $transaction_id Transaction ID
     * @param bool $force_verification Force verification even if already verified
     * @return bool|WP_Error
     */
    public function verify_transaction($transaction_id, $force_verification = false) {
        if (!$this->transaction_exists($transaction_id)) {
            return new WP_Error('transaction_not_found', 'Transaction not found');
        }
        
        $verification_status = get_field('verification_status', $transaction_id);
        
        // Skip if already verified and not forcing
        if ($verification_status === self::VERIFICATION_VERIFIED && !$force_verification) {
            return true;
        }
        
        $gateway_name = get_field('gateway_name', $transaction_id);
        $gateway_transaction_id = get_field('gateway_transaction_id', $transaction_id);
        
        if (empty($gateway_name) || empty($gateway_transaction_id)) {
            update_field('verification_status', self::VERIFICATION_MANUAL, $transaction_id);
            return new WP_Error('missing_gateway_data', 'Missing gateway data for verification');
        }
        
        // Get payment gateway manager
        $gateway_manager = SMS_Payment_Gateway_Manager::get_instance();
        $gateway = $gateway_manager->get_gateway($gateway_name);
        
        if (!$gateway) {
            update_field('verification_status', self::VERIFICATION_FAILED, $transaction_id);
            return new WP_Error('gateway_not_found', 'Payment gateway not found');
        }
        
        // Verify with gateway
        $verification_result = $gateway->verify_payment($gateway_transaction_id);
        
        if (is_wp_error($verification_result)) {
            update_field('verification_status', self::VERIFICATION_FAILED, $transaction_id);
            $this->log_transaction_activity($transaction_id, 'verification_failed', 
                'Verification failed: ' . $verification_result->get_error_message());
            return $verification_result;
        }
        
        // Update verification status based on result
        if (isset($verification_result['status']) && $verification_result['status'] === 'completed') {
            update_field('verification_status', self::VERIFICATION_VERIFIED, $transaction_id);
            
            // Update transaction status if needed
            $current_status = get_field('transaction_status', $transaction_id);
            if ($current_status === self::STATUS_PENDING || $current_status === self::STATUS_PROCESSING) {
                $this->update_transaction_status($transaction_id, self::STATUS_COMPLETED, 
                    'Verified with payment gateway');
            }
            
            $this->log_transaction_activity($transaction_id, 'verification_successful', 
                'Transaction verified with gateway');
        } else {
            update_field('verification_status', self::VERIFICATION_FAILED, $transaction_id);
            $this->log_transaction_activity($transaction_id, 'verification_failed', 
                'Gateway verification returned non-completed status');
        }
        
        return true;
    }
    
    /**
     * Generate receipt for transaction
     *
     * @param int $transaction_id Transaction ID
     * @param array $options Receipt generation options
     * @return string|WP_Error Receipt content or error
     */
    public function generate_receipt($transaction_id, $options = array()) {
        if (!$this->transaction_exists($transaction_id)) {
            return new WP_Error('transaction_not_found', 'Transaction not found');
        }
        
        // Get transaction data
        $transaction_data = $this->get_transaction_data($transaction_id);
        
        if (empty($transaction_data)) {
            return new WP_Error('transaction_data_missing', 'Transaction data not found');
        }
        
        // Generate receipt number if not exists
        $receipt_number = get_field('receipt_number', $transaction_id);
        if (empty($receipt_number)) {
            $receipt_number = $this->generate_receipt_number($transaction_id);
            update_field('receipt_number', $receipt_number, $transaction_id);
        }
        
        // Get receipt template
        $template = isset($options['template']) ? $options['template'] : 'default';
        $receipt_content = $this->render_receipt_template($transaction_data, $template);
        
        // Log receipt generation
        $this->log_transaction_activity($transaction_id, 'receipt_generated', 
            "Receipt generated with number: {$receipt_number}");
        
        return $receipt_content;
    }
    
    /**
     * Send receipt to student/parent
     *
     * @param int $transaction_id Transaction ID
     * @param array $methods Delivery methods (email, sms, etc.)
     * @return bool|WP_Error
     */
    public function send_receipt($transaction_id, $methods = array('email')) {
        if (!$this->transaction_exists($transaction_id)) {
            return new WP_Error('transaction_not_found', 'Transaction not found');
        }
        
        // Generate receipt if not already generated
        $receipt_content = $this->generate_receipt($transaction_id);
        if (is_wp_error($receipt_content)) {
            return $receipt_content;
        }
        
        $student_id = get_field('student', $transaction_id);
        if (!$student_id) {
            return new WP_Error('student_not_found', 'Student not found for transaction');
        }
        
        $sent_methods = array();
        $errors = array();
        
        foreach ($methods as $method) {
            switch ($method) {
                case 'email':
                    $result = $this->send_receipt_email($transaction_id, $student_id, $receipt_content);
                    break;
                case 'sms':
                    $result = $this->send_receipt_sms($transaction_id, $student_id);
                    break;
                default:
                    $result = new WP_Error('invalid_method', "Invalid delivery method: {$method}");
            }
            
            if (is_wp_error($result)) {
                $errors[] = $result->get_error_message();
            } else {
                $sent_methods[] = $method;
            }
        }
        
        // Update receipt sent status
        if (!empty($sent_methods)) {
            update_field('receipt_sent', true, $transaction_id);
            update_field('receipt_sent_date', current_time('mysql'), $transaction_id);
            update_field('receipt_method', $sent_methods, $transaction_id);
            
            $this->log_transaction_activity($transaction_id, 'receipt_sent', 
                'Receipt sent via: ' . implode(', ', $sent_methods));
        }
        
        if (!empty($errors)) {
            return new WP_Error('receipt_send_partial_failure', 
                'Some receipts failed to send: ' . implode(', ', $errors));
        }
        
        return true;
    }    

    /**
     * Handle payment gateway callback
     *
     * @param string $gateway_id Gateway identifier
     * @param array $callback_data Callback data
     * @param array $verification_result Verification result
     */
    public function handle_gateway_callback($gateway_id, $callback_data, $verification_result) {
        // Find transaction by gateway reference or transaction ID
        $transaction_id = $this->find_transaction_by_gateway_data($gateway_id, $callback_data);
        
        if (!$transaction_id) {
            error_log("SMS Transaction Manager: No transaction found for gateway callback from {$gateway_id}");
            return;
        }
        
        // Record callback data
        $this->record_gateway_data($transaction_id, $gateway_id, $callback_data);
        
        // Update status based on callback
        if (isset($verification_result['status'])) {
            $new_status = $this->map_gateway_status_to_transaction_status($verification_result['status']);
            $this->update_transaction_status($transaction_id, $new_status, 
                'Status updated from gateway callback');
        }
    }
    
    /**
     * Update pending transactions by checking with gateways
     */
    public function update_pending_transactions() {
        $pending_transactions = $this->get_transactions_by_status(array(
            self::STATUS_PENDING, 
            self::STATUS_PROCESSING
        ));
        
        foreach ($pending_transactions as $transaction_id) {
            $this->verify_transaction($transaction_id);
        }
    }
    
    /**
     * Handle transaction status changes
     *
     * @param int $transaction_id Transaction ID
     * @param string $new_status New status
     * @param string $old_status Old status
     * @param string $reason Reason for change
     */
    public function handle_status_change($transaction_id, $new_status, $old_status, $reason) {
        // Update invoice status if transaction is linked to an invoice
        $invoice_id = get_field('invoice', $transaction_id);
        if ($invoice_id && $new_status === self::STATUS_COMPLETED) {
            $this->update_invoice_payment_status($invoice_id, $transaction_id);
        }
        
        // Send notifications for status changes
        $this->send_status_change_notification($transaction_id, $new_status, $old_status);
    }
    
    /**
     * Auto-generate receipt for completed transactions
     *
     * @param int $transaction_id Transaction ID
     * @param array $additional_data Additional transaction data
     */
    public function auto_generate_receipt($transaction_id, $additional_data) {
        // Check if auto-receipt generation is enabled
        $auto_receipt = get_option('sms_auto_generate_receipts', true);
        if (!$auto_receipt) {
            return;
        }
        
        // Generate and send receipt
        $receipt_methods = get_option('sms_default_receipt_methods', array('email'));
        $this->send_receipt($transaction_id, $receipt_methods);
    }
    
    /**
     * Get transaction data
     *
     * @param int $transaction_id Transaction ID
     * @return array|null Transaction data
     */
    public function get_transaction_data($transaction_id) {
        if (!$this->transaction_exists($transaction_id)) {
            return null;
        }
        
        $transaction = get_post($transaction_id);
        if (!$transaction) {
            return null;
        }
        
        // Get all ACF fields
        $fields = get_fields($transaction_id);
        
        // Get student data
        $student_id = isset($fields['student']) ? $fields['student'] : null;
        $student_data = null;
        if ($student_id) {
            $student_data = array(
                'id' => $student_id,
                'name' => get_field('full_name', $student_id),
                'admission_number' => get_field('admission_number', $student_id),
                'class' => get_field('current_class', $student_id),
                'parent_email' => get_field('parent_email', $student_id),
                'parent_phone' => get_field('parent_phone', $student_id)
            );
        }
        
        // Get invoice data if linked
        $invoice_id = isset($fields['invoice']) ? $fields['invoice'] : null;
        $invoice_data = null;
        if ($invoice_id) {
            $invoice_data = array(
                'id' => $invoice_id,
                'number' => get_field('invoice_number', $invoice_id),
                'due_date' => get_field('due_date', $invoice_id),
                'total_amount' => get_field('total_amount', $invoice_id)
            );
        }
        
        return array(
            'id' => $transaction_id,
            'post' => $transaction,
            'fields' => $fields,
            'student' => $student_data,
            'invoice' => $invoice_data
        );
    }
    
    /**
     * Get transactions by status
     *
     * @param array $statuses Array of statuses to search for
     * @param int $limit Number of transactions to return
     * @return array Transaction IDs
     */
    public function get_transactions_by_status($statuses, $limit = -1) {
        $args = array(
            'post_type' => 'sms_transactions',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'fields' => 'ids',
            'meta_query' => array(
                array(
                    'key' => 'transaction_status',
                    'value' => $statuses,
                    'compare' => 'IN'
                )
            )
        );
        
        return get_posts($args);
    }
    
    /**
     * Find transaction by gateway data
     *
     * @param string $gateway_id Gateway identifier
     * @param array $callback_data Callback data
     * @return int|null Transaction ID
     */
    private function find_transaction_by_gateway_data($gateway_id, $callback_data) {
        $search_fields = array();
        
        // Common fields to search by
        if (isset($callback_data['transaction_id'])) {
            $search_fields['gateway_transaction_id'] = $callback_data['transaction_id'];
        }
        if (isset($callback_data['reference'])) {
            $search_fields['gateway_reference'] = $callback_data['reference'];
        }
        
        foreach ($search_fields as $field => $value) {
            $args = array(
                'post_type' => 'sms_transactions',
                'post_status' => 'publish',
                'posts_per_page' => 1,
                'fields' => 'ids',
                'meta_query' => array(
                    'relation' => 'AND',
                    array(
                        'key' => 'gateway_name',
                        'value' => $gateway_id,
                        'compare' => '='
                    ),
                    array(
                        'key' => $field,
                        'value' => $value,
                        'compare' => '='
                    )
                )
            );
            
            $transactions = get_posts($args);
            if (!empty($transactions)) {
                return $transactions[0];
            }
        }
        
        return null;
    }
    
    /**
     * Map gateway status to transaction status
     *
     * @param string $gateway_status Gateway status
     * @return string Transaction status
     */
    private function map_gateway_status_to_transaction_status($gateway_status) {
        $status_map = array(
            'completed' => self::STATUS_COMPLETED,
            'success' => self::STATUS_COMPLETED,
            'successful' => self::STATUS_COMPLETED,
            'failed' => self::STATUS_FAILED,
            'cancelled' => self::STATUS_CANCELLED,
            'pending' => self::STATUS_PENDING,
            'processing' => self::STATUS_PROCESSING
        );
        
        return isset($status_map[$gateway_status]) ? $status_map[$gateway_status] : self::STATUS_PENDING;
    }
    
    /**
     * Validate transaction data
     *
     * @param array $transaction_data Transaction data
     * @return bool|WP_Error
     */
    private function validate_transaction_data($transaction_data) {
        $required_fields = array('student_id', 'amount', 'payment_method');
        
        foreach ($required_fields as $field) {
            if (empty($transaction_data[$field])) {
                return new WP_Error('missing_field', "Required field missing: {$field}");
            }
        }
        
        // Validate amount
        if (!is_numeric($transaction_data['amount']) || $transaction_data['amount'] <= 0) {
            return new WP_Error('invalid_amount', 'Amount must be a positive number');
        }
        
        // Validate student exists
        if (!get_post($transaction_data['student_id'])) {
            return new WP_Error('invalid_student', 'Student not found');
        }
        
        return true;
    }
    
    /**
     * Prepare transaction meta data
     *
     * @param array $transaction_data Transaction data
     * @return array Meta data array
     */
    private function prepare_transaction_meta($transaction_data) {
        $meta = array();
        
        // Map transaction data to ACF fields
        $field_mapping = array(
            'student_id' => 'student',
            'invoice_id' => 'invoice',
            'amount' => 'amount',
            'payment_method' => 'payment_method',
            'currency' => 'currency',
            'transaction_type' => 'transaction_type',
            'gateway_name' => 'gateway_name',
            'gateway_transaction_id' => 'gateway_transaction_id',
            'gateway_reference' => 'gateway_reference',
            'phone_number' => 'phone_number',
            'transaction_status' => 'transaction_status',
            'verification_status' => 'verification_status',
            'processed_by' => 'processed_by'
        );
        
        foreach ($field_mapping as $data_key => $meta_key) {
            if (isset($transaction_data[$data_key])) {
                $meta[$meta_key] = $transaction_data[$data_key];
            }
        }
        
        // Set defaults
        $meta['transaction_date'] = isset($transaction_data['transaction_date']) ? 
            $transaction_data['transaction_date'] : current_time('mysql');
        $meta['currency'] = isset($meta['currency']) ? $meta['currency'] : 'KES';
        $meta['transaction_type'] = isset($meta['transaction_type']) ? $meta['transaction_type'] : 'payment';
        $meta['transaction_status'] = isset($meta['transaction_status']) ? $meta['transaction_status'] : self::STATUS_PENDING;
        $meta['verification_status'] = isset($meta['verification_status']) ? $meta['verification_status'] : self::VERIFICATION_UNVERIFIED;
        $meta['processed_by'] = isset($meta['processed_by']) ? $meta['processed_by'] : get_current_user_id();
        
        return $meta;
    }
    
    /**
     * Generate transaction title
     *
     * @param array $transaction_data Transaction data
     * @return string Transaction title
     */
    private function generate_transaction_title($transaction_data) {
        $student_name = '';
        if (isset($transaction_data['student_id'])) {
            $student_name = get_field('full_name', $transaction_data['student_id']);
        }
        
        $amount = isset($transaction_data['amount']) ? $transaction_data['amount'] : '0';
        $currency = isset($transaction_data['currency']) ? $transaction_data['currency'] : 'KES';
        $method = isset($transaction_data['payment_method']) ? ucfirst($transaction_data['payment_method']) : 'Payment';
        
        return "{$method} - {$student_name} - {$currency} {$amount}";
    }
    
    /**
     * Generate unique transaction number
     *
     * @param int $transaction_id Transaction ID
     * @return string Transaction number
     */
    private function generate_transaction_number($transaction_id) {
        $prefix = get_option('sms_transaction_number_prefix', 'TXN');
        $year = date('Y');
        $month = date('m');
        
        // Format: TXN-YYYY-MM-XXXXX
        return sprintf('%s-%s-%s-%05d', $prefix, $year, $month, $transaction_id);
    }
    
    /**
     * Generate unique receipt number
     *
     * @param int $transaction_id Transaction ID
     * @return string Receipt number
     */
    private function generate_receipt_number($transaction_id) {
        $prefix = get_option('sms_receipt_number_prefix', 'RCP');
        $year = date('Y');
        $month = date('m');
        
        // Format: RCP-YYYY-MM-XXXXX
        return sprintf('%s-%s-%s-%05d', $prefix, $year, $month, $transaction_id);
    }
    
    /**
     * Check if transaction exists
     *
     * @param int $transaction_id Transaction ID
     * @return bool
     */
    private function transaction_exists($transaction_id) {
        $post = get_post($transaction_id);
        return $post && $post->post_type === 'sms_transactions';
    }
    
    /**
     * Add processing note to transaction
     *
     * @param int $transaction_id Transaction ID
     * @param string $note Note to add
     */
    private function add_processing_note($transaction_id, $note) {
        $existing_notes = get_field('processing_notes', $transaction_id);
        $timestamp = current_time('mysql');
        $user = wp_get_current_user();
        $user_name = $user->display_name;
        
        $new_note = "[{$timestamp}] {$user_name}: {$note}";
        
        if (!empty($existing_notes)) {
            $updated_notes = $existing_notes . "\n" . $new_note;
        } else {
            $updated_notes = $new_note;
        }
        
        update_field('processing_notes', $updated_notes, $transaction_id);
    }
    
    /**
     * Log transaction activity
     *
     * @param int $transaction_id Transaction ID
     * @param string $action Action performed
     * @param string $message Log message
     */
    private function log_transaction_activity($transaction_id, $action, $message) {
        $log_entry = array(
            'transaction_id' => $transaction_id,
            'action' => $action,
            'message' => $message,
            'user_id' => get_current_user_id(),
            'timestamp' => current_time('mysql')
        );
        
        // Use WordPress error logging
        error_log('SMS Transaction Activity: ' . json_encode($log_entry));
        
        // Store in custom activity log if available
        do_action('sms_log_transaction_activity', $log_entry);
    }
    
    /**
     * Render receipt template
     *
     * @param array $transaction_data Transaction data
     * @param string $template Template name
     * @return string Receipt HTML content
     */
    private function render_receipt_template($transaction_data, $template = 'default') {
        $receipt_generator = new SMS_Receipt_Generator();
        return $receipt_generator->generate_receipt($transaction_data, $template);
    }
    
    /**
     * Send receipt via email
     *
     * @param int $transaction_id Transaction ID
     * @param int $student_id Student ID
     * @param string $receipt_content Receipt content
     * @return bool|WP_Error
     */
    private function send_receipt_email($transaction_id, $student_id, $receipt_content) {
        $parent_email = get_field('parent_email', $student_id);
        if (empty($parent_email)) {
            return new WP_Error('no_email', 'Parent email not found');
        }
        
        $student_name = get_field('full_name', $student_id);
        $receipt_number = get_field('receipt_number', $transaction_id);
        $school_name = get_option('sms_school_name', get_bloginfo('name'));
        
        $subject = "Payment Receipt - {$receipt_number} - {$student_name}";
        
        $message = "Dear Parent,\n\n";
        $message .= "Please find attached the payment receipt for {$student_name}.\n\n";
        $message .= "Receipt Details:\n";
        $message .= "Receipt Number: {$receipt_number}\n";
        $message .= "Student: {$student_name}\n";
        $message .= "Date: " . date('d/m/Y H:i') . "\n\n";
        $message .= "Thank you for your payment.\n\n";
        $message .= "Best regards,\n{$school_name}";
        
        $headers = array('Content-Type: text/html; charset=UTF-8');
        
        return wp_mail($parent_email, $subject, $receipt_content, $headers);
    }
    
    /**
     * Send receipt notification via SMS
     *
     * @param int $transaction_id Transaction ID
     * @param int $student_id Student ID
     * @return bool|WP_Error
     */
    private function send_receipt_sms($transaction_id, $student_id) {
        $parent_phone = get_field('parent_phone', $student_id);
        if (empty($parent_phone)) {
            return new WP_Error('no_phone', 'Parent phone number not found');
        }
        
        $student_name = get_field('full_name', $student_id);
        $receipt_number = get_field('receipt_number', $transaction_id);
        $amount = get_field('amount', $transaction_id);
        $currency = get_field('currency', $transaction_id);
        
        $message = "Payment received for {$student_name}. Amount: {$currency} {$amount}. Receipt: {$receipt_number}. Thank you!";
        
        // Use SMS communication handler if available
        if (class_exists('SMS_Communication_Handler')) {
            $sms_handler = new SMS_Communication_Handler();
            return $sms_handler->send_sms($parent_phone, $message);
        }
        
        return new WP_Error('sms_not_available', 'SMS service not available');
    }
    
    /**
     * Update invoice payment status
     *
     * @param int $invoice_id Invoice ID
     * @param int $transaction_id Transaction ID
     */
    private function update_invoice_payment_status($invoice_id, $transaction_id) {
        // Get invoice total and paid amounts
        $invoice_total = get_field('total_amount', $invoice_id);
        $transaction_amount = get_field('amount', $transaction_id);
        
        // Calculate total paid amount for this invoice
        $paid_transactions = $this->get_completed_transactions_for_invoice($invoice_id);
        $total_paid = array_sum(array_map(function($txn_id) {
            return get_field('amount', $txn_id);
        }, $paid_transactions));
        
        // Update invoice status
        if ($total_paid >= $invoice_total) {
            update_field('payment_status', 'paid', $invoice_id);
        } elseif ($total_paid > 0) {
            update_field('payment_status', 'partial', $invoice_id);
        }
        
        update_field('paid_amount', $total_paid, $invoice_id);
        update_field('balance_amount', $invoice_total - $total_paid, $invoice_id);
    }
    
    /**
     * Get completed transactions for an invoice
     *
     * @param int $invoice_id Invoice ID
     * @return array Transaction IDs
     */
    private function get_completed_transactions_for_invoice($invoice_id) {
        $args = array(
            'post_type' => 'sms_transactions',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => 'invoice',
                    'value' => $invoice_id,
                    'compare' => '='
                ),
                array(
                    'key' => 'transaction_status',
                    'value' => self::STATUS_COMPLETED,
                    'compare' => '='
                )
            )
        );
        
        return get_posts($args);
    }
    
    /**
     * Send status change notification
     *
     * @param int $transaction_id Transaction ID
     * @param string $new_status New status
     * @param string $old_status Old status
     */
    private function send_status_change_notification($transaction_id, $new_status, $old_status) {
        // Only send notifications for significant status changes
        $notify_statuses = array(self::STATUS_COMPLETED, self::STATUS_FAILED, self::STATUS_REFUNDED);
        
        if (!in_array($new_status, $notify_statuses)) {
            return;
        }
        
        $student_id = get_field('student', $transaction_id);
        if (!$student_id) {
            return;
        }
        
        $student_name = get_field('full_name', $student_id);
        $amount = get_field('amount', $transaction_id);
        $currency = get_field('currency', $transaction_id);
        $transaction_number = get_field('transaction_number', $transaction_id);
        
        $status_messages = array(
            self::STATUS_COMPLETED => "Payment of {$currency} {$amount} for {$student_name} has been completed successfully. Transaction: {$transaction_number}",
            self::STATUS_FAILED => "Payment of {$currency} {$amount} for {$student_name} has failed. Please try again. Transaction: {$transaction_number}",
            self::STATUS_REFUNDED => "Payment of {$currency} {$amount} for {$student_name} has been refunded. Transaction: {$transaction_number}"
        );
        
        $message = isset($status_messages[$new_status]) ? $status_messages[$new_status] : '';
        
        if (!empty($message)) {
            // Send SMS notification if SMS service is available
            $parent_phone = get_field('parent_phone', $student_id);
            if (!empty($parent_phone) && class_exists('SMS_Communication_Handler')) {
                $sms_handler = new SMS_Communication_Handler();
                $sms_handler->send_sms($parent_phone, $message);
            }
        }
    }
}