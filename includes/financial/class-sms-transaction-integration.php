<?php
/**
 * Transaction Integration
 *
 * Integrates transaction management with payment gateways and other components
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
 * Transaction Integration Class
 */
class SMS_Transaction_Integration {
    
    /**
     * Instance of this class
     *
     * @var SMS_Transaction_Integration
     */
    private static $instance = null;
    
    /**
     * Transaction manager instance
     *
     * @var SMS_Transaction_Manager
     */
    private $transaction_manager;
    
    /**
     * Status tracker instance
     *
     * @var SMS_Transaction_Status_Tracker
     */
    private $status_tracker;
    
    /**
     * Receipt generator instance
     *
     * @var SMS_Receipt_Generator
     */
    private $receipt_generator;
    
    /**
     * Get singleton instance
     *
     * @return SMS_Transaction_Integration
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
        $this->transaction_manager = SMS_Transaction_Manager::get_instance();
        $this->status_tracker = SMS_Transaction_Status_Tracker::get_instance();
        $this->receipt_generator = new SMS_Receipt_Generator();
        $this->init();
    }
    
    /**
     * Initialize the integration
     */
    private function init() {
        // Hook into payment gateway events
        add_action('sms_payment_initiated', array($this, 'handle_payment_initiation'), 10, 3);
        add_action('sms_payment_completed', array($this, 'handle_payment_completion'), 10, 3);
        add_action('sms_payment_failed', array($this, 'handle_payment_failure'), 10, 3);
        
        // Hook into invoice events
        add_action('sms_invoice_generated', array($this, 'prepare_for_payment'), 10, 2);
        
        // Hook into transaction events for additional processing
        add_action('sms_transaction_completed', array($this, 'process_completed_transaction'), 10, 2);
        add_action('sms_transaction_failed', array($this, 'process_failed_transaction'), 10, 2);
        
        // Schedule transaction verification
        add_action('sms_verify_single_transaction', array($this, 'verify_single_transaction'));
        
        // Add admin initialization
        add_action('admin_init', array($this, 'init_admin_components'));
    }
    
    /**
     * Initialize admin components
     */
    public function init_admin_components() {
        if (is_admin()) {
            new SMS_Transaction_Admin();
        }
    }
    
    /**
     * Handle payment initiation
     *
     * @param array $payment_data Payment data
     * @param string $gateway_id Gateway identifier
     * @param array $gateway_response Gateway response
     */
    public function handle_payment_initiation($payment_data, $gateway_id, $gateway_response) {
        // Create transaction record
        $transaction_data = array(
            'student_id' => $payment_data['student_id'],
            'invoice_id' => isset($payment_data['invoice_id']) ? $payment_data['invoice_id'] : null,
            'amount' => $payment_data['amount'],
            'currency' => isset($payment_data['currency']) ? $payment_data['currency'] : 'KES',
            'payment_method' => $gateway_id,
            'gateway_name' => $gateway_id,
            'transaction_status' => SMS_Transaction_Manager::STATUS_PENDING,
            'verification_status' => SMS_Transaction_Manager::VERIFICATION_UNVERIFIED
        );
        
        // Add gateway-specific data if available
        if (isset($gateway_response['transaction_id'])) {
            $transaction_data['gateway_transaction_id'] = $gateway_response['transaction_id'];
        }
        
        if (isset($gateway_response['reference'])) {
            $transaction_data['gateway_reference'] = $gateway_response['reference'];
        }
        
        if (isset($payment_data['phone_number'])) {
            $transaction_data['phone_number'] = $payment_data['phone_number'];
        }
        
        $transaction_id = $this->transaction_manager->create_transaction($transaction_data);
        
        if (!is_wp_error($transaction_id)) {
            // Store the transaction ID for later reference
            do_action('sms_transaction_created_for_payment', $transaction_id, $payment_data, $gateway_response);
        }
        
        return $transaction_id;
    }
    
    /**
     * Handle payment completion
     *
     * @param array $payment_data Payment data
     * @param string $gateway_id Gateway identifier
     * @param array $gateway_response Gateway response
     */
    public function handle_payment_completion($payment_data, $gateway_id, $gateway_response) {
        // Find the transaction
        $transaction_id = $this->find_transaction_by_gateway_data($gateway_id, $gateway_response);
        
        if ($transaction_id) {
            // Update transaction status
            $this->transaction_manager->update_transaction_status(
                $transaction_id,
                SMS_Transaction_Manager::STATUS_COMPLETED,
                'Payment completed via gateway callback'
            );
            
            // Record additional gateway data
            $this->transaction_manager->record_gateway_data($transaction_id, $gateway_id, $gateway_response);
        }
    }
    
    /**
     * Handle payment failure
     *
     * @param array $payment_data Payment data
     * @param string $gateway_id Gateway identifier
     * @param array $gateway_response Gateway response
     */
    public function handle_payment_failure($payment_data, $gateway_id, $gateway_response) {
        // Find the transaction
        $transaction_id = $this->find_transaction_by_gateway_data($gateway_id, $gateway_response);
        
        if ($transaction_id) {
            $failure_reason = isset($gateway_response['error_message']) ? 
                $gateway_response['error_message'] : 'Payment failed';
            
            // Update transaction status
            $this->transaction_manager->update_transaction_status(
                $transaction_id,
                SMS_Transaction_Manager::STATUS_FAILED,
                $failure_reason
            );
            
            // Record gateway response
            $this->transaction_manager->record_gateway_data($transaction_id, $gateway_id, $gateway_response);
        }
    }
    
    /**
     * Prepare invoice for payment
     *
     * @param int $invoice_id Invoice ID
     * @param array $invoice_data Invoice data
     */
    public function prepare_for_payment($invoice_id, $invoice_data) {
        // This can be used to set up payment links or prepare payment data
        // For now, we'll just log that the invoice is ready for payment
        error_log("SMS Transaction Integration: Invoice {$invoice_id} prepared for payment");
    }
    
    /**
     * Process completed transaction
     *
     * @param int $transaction_id Transaction ID
     * @param array $additional_data Additional data
     */
    public function process_completed_transaction($transaction_id, $additional_data) {
        // Update related invoice status
        $invoice_id = get_field('invoice', $transaction_id);
        if ($invoice_id) {
            $this->update_invoice_payment_status($invoice_id);
        }
        
        // Send completion notifications
        $this->send_completion_notifications($transaction_id);
        
        // Log completion
        error_log("SMS Transaction Integration: Transaction {$transaction_id} completed and processed");
    }
    
    /**
     * Process failed transaction
     *
     * @param int $transaction_id Transaction ID
     * @param string $reason Failure reason
     */
    public function process_failed_transaction($transaction_id, $reason) {
        // Send failure notifications
        $this->send_failure_notifications($transaction_id, $reason);
        
        // Log failure
        error_log("SMS Transaction Integration: Transaction {$transaction_id} failed: {$reason}");
    }
    
    /**
     * Verify single transaction
     *
     * @param int $transaction_id Transaction ID
     */
    public function verify_single_transaction($transaction_id) {
        $this->status_tracker->update_transaction_status($transaction_id);
    }
    
    /**
     * Find transaction by gateway data
     *
     * @param string $gateway_id Gateway identifier
     * @param array $gateway_response Gateway response
     * @return int|null Transaction ID
     */
    private function find_transaction_by_gateway_data($gateway_id, $gateway_response) {
        $search_fields = array();
        
        if (isset($gateway_response['transaction_id'])) {
            $search_fields['gateway_transaction_id'] = $gateway_response['transaction_id'];
        }
        
        if (isset($gateway_response['reference'])) {
            $search_fields['gateway_reference'] = $gateway_response['reference'];
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
     * Update invoice payment status
     *
     * @param int $invoice_id Invoice ID
     */
    private function update_invoice_payment_status($invoice_id) {
        // Get invoice total
        $invoice_total = get_field('total_amount', $invoice_id);
        
        // Get all completed transactions for this invoice
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
                    'value' => SMS_Transaction_Manager::STATUS_COMPLETED,
                    'compare' => '='
                )
            )
        );
        
        $completed_transactions = get_posts($args);
        
        // Calculate total paid
        $total_paid = 0;
        foreach ($completed_transactions as $transaction_id) {
            $amount = get_field('amount', $transaction_id);
            $total_paid += floatval($amount);
        }
        
        // Update invoice status
        if ($total_paid >= $invoice_total) {
            update_field('payment_status', 'paid', $invoice_id);
        } elseif ($total_paid > 0) {
            update_field('payment_status', 'partial', $invoice_id);
        } else {
            update_field('payment_status', 'unpaid', $invoice_id);
        }
        
        update_field('paid_amount', $total_paid, $invoice_id);
        update_field('balance_amount', $invoice_total - $total_paid, $invoice_id);
    }
    
    /**
     * Send completion notifications
     *
     * @param int $transaction_id Transaction ID
     */
    private function send_completion_notifications($transaction_id) {
        $student_id = get_field('student', $transaction_id);
        if (!$student_id) {
            return;
        }
        
        $student_name = get_field('full_name', $student_id);
        $amount = get_field('amount', $transaction_id);
        $currency = get_field('currency', $transaction_id);
        $receipt_number = get_field('receipt_number', $transaction_id);
        
        // Send SMS notification
        $parent_phone = get_field('parent_phone', $student_id);
        if (!empty($parent_phone)) {
            $message = "Payment of {$currency} {$amount} for {$student_name} completed successfully. Receipt: {$receipt_number}";
            $this->send_sms_notification($parent_phone, $message);
        }
        
        // Send email notification with receipt
        $parent_email = get_field('parent_email', $student_id);
        if (!empty($parent_email)) {
            $this->transaction_manager->send_receipt($transaction_id, array('email'));
        }
    }
    
    /**
     * Send failure notifications
     *
     * @param int $transaction_id Transaction ID
     * @param string $reason Failure reason
     */
    private function send_failure_notifications($transaction_id, $reason) {
        $student_id = get_field('student', $transaction_id);
        if (!$student_id) {
            return;
        }
        
        $student_name = get_field('full_name', $student_id);
        $amount = get_field('amount', $transaction_id);
        $currency = get_field('currency', $transaction_id);
        
        // Send SMS notification
        $parent_phone = get_field('parent_phone', $student_id);
        if (!empty($parent_phone)) {
            $message = "Payment of {$currency} {$amount} for {$student_name} failed. Please try again or contact school.";
            $this->send_sms_notification($parent_phone, $message);
        }
    }
    
    /**
     * Send SMS notification
     *
     * @param string $phone_number Phone number
     * @param string $message Message
     */
    private function send_sms_notification($phone_number, $message) {
        // Use SMS communication handler if available
        if (class_exists('SMS_Communication_Handler')) {
            $sms_handler = new SMS_Communication_Handler();
            $sms_handler->send_sms($phone_number, $message);
        }
    }
    
    /**
     * Get transaction statistics
     *
     * @return array Statistics
     */
    public function get_transaction_statistics() {
        return $this->status_tracker->get_status_statistics();
    }
    
    /**
     * Process bulk transaction operations
     *
     * @param string $operation Operation type
     * @param array $transaction_ids Transaction IDs
     * @return array Results
     */
    public function process_bulk_operations($operation, $transaction_ids) {
        $results = array(
            'success' => 0,
            'failed' => 0,
            'errors' => array()
        );
        
        foreach ($transaction_ids as $transaction_id) {
            switch ($operation) {
                case 'verify':
                    $result = $this->transaction_manager->verify_transaction($transaction_id, true);
                    break;
                case 'send_receipt':
                    $result = $this->transaction_manager->send_receipt($transaction_id, array('email'));
                    break;
                case 'mark_completed':
                    $result = $this->transaction_manager->update_transaction_status(
                        $transaction_id,
                        SMS_Transaction_Manager::STATUS_COMPLETED,
                        'Bulk operation: marked as completed'
                    );
                    break;
                default:
                    $result = new WP_Error('invalid_operation', 'Invalid bulk operation');
            }
            
            if (is_wp_error($result)) {
                $results['failed']++;
                $results['errors'][] = "Transaction {$transaction_id}: " . $result->get_error_message();
            } else {
                $results['success']++;
            }
        }
        
        return $results;
    }
}