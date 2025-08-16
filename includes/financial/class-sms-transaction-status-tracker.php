<?php
/**
 * Transaction Status Tracker
 *
 * Handles automatic transaction status updates and monitoring
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
 * Transaction Status Tracker Class
 */
class SMS_Transaction_Status_Tracker {
    
    /**
     * Instance of this class
     *
     * @var SMS_Transaction_Status_Tracker
     */
    private static $instance = null;
    
    /**
     * Transaction manager instance
     *
     * @var SMS_Transaction_Manager
     */
    private $transaction_manager;
    
    /**
     * Payment gateway manager instance
     *
     * @var SMS_Payment_Gateway_Manager
     */
    private $gateway_manager;
    
    /**
     * Get singleton instance
     *
     * @return SMS_Transaction_Status_Tracker
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
        $this->gateway_manager = SMS_Payment_Gateway_Manager::get_instance();
        $this->init();
    }
    
    /**
     * Initialize the status tracker
     */
    private function init() {
        // Schedule automatic status updates
        add_action('init', array($this, 'schedule_status_updates'));
        
        // Hook into WordPress cron
        add_action('sms_update_transaction_statuses', array($this, 'update_pending_transactions'));
        add_action('sms_cleanup_old_transactions', array($this, 'cleanup_old_transactions'));
        
        // Hook into transaction creation
        add_action('sms_transaction_created', array($this, 'schedule_transaction_monitoring'), 10, 2);
        
        // AJAX handlers for manual status updates
        add_action('wp_ajax_sms_update_transaction_status', array($this, 'handle_manual_status_update'));
        add_action('wp_ajax_sms_verify_transaction', array($this, 'handle_manual_verification'));
        
        // Add admin notices for failed transactions
        add_action('admin_notices', array($this, 'show_failed_transaction_notices'));
    }
    
    /**
     * Schedule automatic status updates
     */
    public function schedule_status_updates() {
        // Schedule hourly status updates
        if (!wp_next_scheduled('sms_update_transaction_statuses')) {
            wp_schedule_event(time(), 'hourly', 'sms_update_transaction_statuses');
        }
        
        // Schedule daily cleanup
        if (!wp_next_scheduled('sms_cleanup_old_transactions')) {
            wp_schedule_event(time(), 'daily', 'sms_cleanup_old_transactions');
        }
    }
    
    /**
     * Update pending transactions
     */
    public function update_pending_transactions() {
        $pending_statuses = array(
            SMS_Transaction_Manager::STATUS_PENDING,
            SMS_Transaction_Manager::STATUS_PROCESSING
        );
        
        $pending_transactions = $this->transaction_manager->get_transactions_by_status($pending_statuses, 50);
        
        foreach ($pending_transactions as $transaction_id) {
            $this->update_transaction_status($transaction_id);
        }
        
        // Log the update process
        error_log("SMS Transaction Status Tracker: Updated " . count($pending_transactions) . " pending transactions");
    }
    
    /**
     * Update individual transaction status
     *
     * @param int $transaction_id Transaction ID
     * @return bool|WP_Error
     */
    public function update_transaction_status($transaction_id) {
        $gateway_name = get_field('gateway_name', $transaction_id);
        $gateway_transaction_id = get_field('gateway_transaction_id', $transaction_id);
        $current_status = get_field('transaction_status', $transaction_id);
        
        // Skip if no gateway information
        if (empty($gateway_name) || empty($gateway_transaction_id)) {
            return $this->handle_manual_transaction($transaction_id);
        }
        
        // Get gateway instance
        $gateway = $this->gateway_manager->get_gateway($gateway_name);
        if (!$gateway) {
            return new WP_Error('gateway_not_found', 'Payment gateway not found');
        }
        
        try {
            // Check transaction status with gateway
            $gateway_status = $gateway->get_transaction_status($gateway_transaction_id);
            
            if (is_wp_error($gateway_status)) {
                $this->handle_gateway_error($transaction_id, $gateway_status);
                return $gateway_status;
            }
            
            // Map gateway status to transaction status
            $new_status = $this->map_gateway_status($gateway_status);
            
            // Update status if changed
            if ($new_status !== $current_status) {
                $this->transaction_manager->update_transaction_status(
                    $transaction_id, 
                    $new_status, 
                    'Status updated automatically from gateway'
                );
                
                // Log status change
                $this->log_status_update($transaction_id, $current_status, $new_status, 'automatic');
            }
            
            return true;
            
        } catch (Exception $e) {
            $error = new WP_Error('gateway_exception', $e->getMessage());
            $this->handle_gateway_error($transaction_id, $error);
            return $error;
        }
    }
    
    /**
     * Handle manual transactions (no gateway)
     *
     * @param int $transaction_id Transaction ID
     * @return bool
     */
    private function handle_manual_transaction($transaction_id) {
        $current_status = get_field('transaction_status', $transaction_id);
        $transaction_date = get_field('transaction_date', $transaction_id);
        $payment_method = get_field('payment_method', $transaction_id);
        
        // Auto-complete cash transactions after 24 hours if still pending
        if ($payment_method === 'cash' && $current_status === SMS_Transaction_Manager::STATUS_PENDING) {
            $hours_since_creation = (time() - strtotime($transaction_date)) / 3600;
            
            if ($hours_since_creation > 24) {
                $this->transaction_manager->update_transaction_status(
                    $transaction_id,
                    SMS_Transaction_Manager::STATUS_COMPLETED,
                    'Auto-completed cash transaction after 24 hours'
                );
                
                return true;
            }
        }
        
        // Mark old pending transactions for manual review
        if ($current_status === SMS_Transaction_Manager::STATUS_PENDING) {
            $hours_since_creation = (time() - strtotime($transaction_date)) / 3600;
            
            if ($hours_since_creation > 72) { // 3 days
                update_field('verification_status', SMS_Transaction_Manager::VERIFICATION_MANUAL, $transaction_id);
                $this->add_processing_note($transaction_id, 'Transaction marked for manual review due to age');
            }
        }
        
        return true;
    }
    
    /**
     * Handle gateway errors
     *
     * @param int $transaction_id Transaction ID
     * @param WP_Error $error Error object
     */
    private function handle_gateway_error($transaction_id, $error) {
        $error_count = get_post_meta($transaction_id, '_sms_gateway_error_count', true);
        $error_count = intval($error_count) + 1;
        
        update_post_meta($transaction_id, '_sms_gateway_error_count', $error_count);
        update_post_meta($transaction_id, '_sms_last_gateway_error', $error->get_error_message());
        update_post_meta($transaction_id, '_sms_last_error_time', current_time('mysql'));
        
        // Mark for manual review after 5 failed attempts
        if ($error_count >= 5) {
            update_field('verification_status', SMS_Transaction_Manager::VERIFICATION_MANUAL, $transaction_id);
            $this->add_processing_note($transaction_id, 
                "Marked for manual review after {$error_count} gateway errors. Last error: " . $error->get_error_message());
        }
        
        // Log the error
        error_log("SMS Transaction Status Tracker: Gateway error for transaction {$transaction_id}: " . $error->get_error_message());
    }
    
    /**
     * Map gateway status to transaction status
     *
     * @param string $gateway_status Gateway status
     * @return string Transaction status
     */
    private function map_gateway_status($gateway_status) {
        $status_map = array(
            'completed' => SMS_Transaction_Manager::STATUS_COMPLETED,
            'success' => SMS_Transaction_Manager::STATUS_COMPLETED,
            'successful' => SMS_Transaction_Manager::STATUS_COMPLETED,
            'paid' => SMS_Transaction_Manager::STATUS_COMPLETED,
            'confirmed' => SMS_Transaction_Manager::STATUS_COMPLETED,
            'failed' => SMS_Transaction_Manager::STATUS_FAILED,
            'error' => SMS_Transaction_Manager::STATUS_FAILED,
            'declined' => SMS_Transaction_Manager::STATUS_FAILED,
            'rejected' => SMS_Transaction_Manager::STATUS_FAILED,
            'cancelled' => SMS_Transaction_Manager::STATUS_CANCELLED,
            'canceled' => SMS_Transaction_Manager::STATUS_CANCELLED,
            'pending' => SMS_Transaction_Manager::STATUS_PENDING,
            'processing' => SMS_Transaction_Manager::STATUS_PROCESSING,
            'in_progress' => SMS_Transaction_Manager::STATUS_PROCESSING,
            'refunded' => SMS_Transaction_Manager::STATUS_REFUNDED,
            'reversed' => SMS_Transaction_Manager::STATUS_REFUNDED
        );
        
        $normalized_status = strtolower(trim($gateway_status));
        return isset($status_map[$normalized_status]) ? $status_map[$normalized_status] : SMS_Transaction_Manager::STATUS_PENDING;
    }
    
    /**
     * Schedule monitoring for a specific transaction
     *
     * @param int $transaction_id Transaction ID
     * @param array $transaction_data Transaction data
     */
    public function schedule_transaction_monitoring($transaction_id, $transaction_data) {
        $gateway_name = isset($transaction_data['gateway_name']) ? $transaction_data['gateway_name'] : '';
        
        // Only schedule monitoring for gateway transactions
        if (empty($gateway_name)) {
            return;
        }
        
        // Schedule immediate verification after 5 minutes
        wp_schedule_single_event(time() + 300, 'sms_verify_single_transaction', array($transaction_id));
        
        // Schedule follow-up checks
        wp_schedule_single_event(time() + 1800, 'sms_verify_single_transaction', array($transaction_id)); // 30 minutes
        wp_schedule_single_event(time() + 3600, 'sms_verify_single_transaction', array($transaction_id)); // 1 hour
        wp_schedule_single_event(time() + 7200, 'sms_verify_single_transaction', array($transaction_id)); // 2 hours
    }
    
    /**
     * Handle manual status update via AJAX
     */
    public function handle_manual_status_update() {
        // Verify nonce and permissions
        if (!wp_verify_nonce($_POST['nonce'], 'sms_transaction_nonce') || 
            !current_user_can('manage_transactions')) {
            wp_send_json_error('Unauthorized');
        }
        
        $transaction_id = intval($_POST['transaction_id']);
        $new_status = sanitize_text_field($_POST['status']);
        $reason = sanitize_textarea_field($_POST['reason']);
        
        $result = $this->transaction_manager->update_transaction_status($transaction_id, $new_status, $reason);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        $this->log_status_update($transaction_id, '', $new_status, 'manual', $reason);
        
        wp_send_json_success('Transaction status updated successfully');
    }
    
    /**
     * Handle manual verification via AJAX
     */
    public function handle_manual_verification() {
        // Verify nonce and permissions
        if (!wp_verify_nonce($_POST['nonce'], 'sms_transaction_nonce') || 
            !current_user_can('manage_transactions')) {
            wp_send_json_error('Unauthorized');
        }
        
        $transaction_id = intval($_POST['transaction_id']);
        
        $result = $this->transaction_manager->verify_transaction($transaction_id, true);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success('Transaction verified successfully');
    }
    
    /**
     * Show admin notices for failed transactions
     */
    public function show_failed_transaction_notices() {
        if (!current_user_can('manage_transactions')) {
            return;
        }
        
        // Get count of failed transactions in last 24 hours
        $failed_count = $this->get_recent_failed_transactions_count();
        
        if ($failed_count > 0) {
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p><strong>School Management System:</strong> ';
            echo sprintf(
                _n(
                    'There is %d failed transaction that may need attention.',
                    'There are %d failed transactions that may need attention.',
                    $failed_count,
                    'school-management-system'
                ),
                $failed_count
            );
            echo ' <a href="' . admin_url('edit.php?post_type=sms_transactions&transaction_status=failed') . '">View failed transactions</a>';
            echo '</p>';
            echo '</div>';
        }
        
        // Get count of transactions needing manual review
        $manual_review_count = $this->get_manual_review_transactions_count();
        
        if ($manual_review_count > 0) {
            echo '<div class="notice notice-info is-dismissible">';
            echo '<p><strong>School Management System:</strong> ';
            echo sprintf(
                _n(
                    'There is %d transaction requiring manual review.',
                    'There are %d transactions requiring manual review.',
                    $manual_review_count,
                    'school-management-system'
                ),
                $manual_review_count
            );
            echo ' <a href="' . admin_url('edit.php?post_type=sms_transactions&verification_status=manual_verification') . '">Review transactions</a>';
            echo '</p>';
            echo '</div>';
        }
    }
    
    /**
     * Get count of recent failed transactions
     *
     * @return int Failed transaction count
     */
    private function get_recent_failed_transactions_count() {
        $args = array(
            'post_type' => 'sms_transactions',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'date_query' => array(
                array(
                    'after' => '24 hours ago'
                )
            ),
            'meta_query' => array(
                array(
                    'key' => 'transaction_status',
                    'value' => SMS_Transaction_Manager::STATUS_FAILED,
                    'compare' => '='
                )
            )
        );
        
        $transactions = get_posts($args);
        return count($transactions);
    }
    
    /**
     * Get count of transactions needing manual review
     *
     * @return int Manual review transaction count
     */
    private function get_manual_review_transactions_count() {
        $args = array(
            'post_type' => 'sms_transactions',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => array(
                array(
                    'key' => 'verification_status',
                    'value' => SMS_Transaction_Manager::VERIFICATION_MANUAL,
                    'compare' => '='
                )
            )
        );
        
        $transactions = get_posts($args);
        return count($transactions);
    }
    
    /**
     * Cleanup old transactions
     */
    public function cleanup_old_transactions() {
        // Remove error metadata from old transactions
        $this->cleanup_old_error_metadata();
        
        // Archive very old completed transactions (optional)
        $this->archive_old_completed_transactions();
    }
    
    /**
     * Cleanup old error metadata
     */
    private function cleanup_old_error_metadata() {
        global $wpdb;
        
        // Remove error metadata older than 30 days
        $thirty_days_ago = date('Y-m-d H:i:s', strtotime('-30 days'));
        
        $wpdb->query($wpdb->prepare("
            DELETE pm FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE p.post_type = 'sms_transactions'
            AND pm.meta_key IN ('_sms_gateway_error_count', '_sms_last_gateway_error', '_sms_last_error_time')
            AND p.post_date < %s
        ", $thirty_days_ago));
    }
    
    /**
     * Archive old completed transactions (optional)
     */
    private function archive_old_completed_transactions() {
        // This is optional and can be implemented based on school requirements
        // For now, we'll just log the count of old transactions
        
        $args = array(
            'post_type' => 'sms_transactions',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'date_query' => array(
                array(
                    'before' => '1 year ago'
                )
            ),
            'meta_query' => array(
                array(
                    'key' => 'transaction_status',
                    'value' => SMS_Transaction_Manager::STATUS_COMPLETED,
                    'compare' => '='
                )
            )
        );
        
        $old_transactions = get_posts($args);
        
        if (!empty($old_transactions)) {
            error_log("SMS Transaction Status Tracker: Found " . count($old_transactions) . " old completed transactions that could be archived");
        }
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
        $user_name = $user->display_name ?: 'System';
        
        $new_note = "[{$timestamp}] {$user_name}: {$note}";
        
        if (!empty($existing_notes)) {
            $updated_notes = $existing_notes . "\n" . $new_note;
        } else {
            $updated_notes = $new_note;
        }
        
        update_field('processing_notes', $updated_notes, $transaction_id);
    }
    
    /**
     * Log status update
     *
     * @param int $transaction_id Transaction ID
     * @param string $old_status Old status
     * @param string $new_status New status
     * @param string $update_type Update type (automatic/manual)
     * @param string $reason Reason for update
     */
    private function log_status_update($transaction_id, $old_status, $new_status, $update_type, $reason = '') {
        $log_entry = array(
            'transaction_id' => $transaction_id,
            'old_status' => $old_status,
            'new_status' => $new_status,
            'update_type' => $update_type,
            'reason' => $reason,
            'user_id' => get_current_user_id(),
            'timestamp' => current_time('mysql')
        );
        
        error_log('SMS Transaction Status Update: ' . json_encode($log_entry));
        
        // Store in custom log table if available
        do_action('sms_log_transaction_status_update', $log_entry);
    }
    
    /**
     * Get transaction status statistics
     *
     * @return array Status statistics
     */
    public function get_status_statistics() {
        $statuses = array(
            SMS_Transaction_Manager::STATUS_PENDING,
            SMS_Transaction_Manager::STATUS_PROCESSING,
            SMS_Transaction_Manager::STATUS_COMPLETED,
            SMS_Transaction_Manager::STATUS_FAILED,
            SMS_Transaction_Manager::STATUS_CANCELLED,
            SMS_Transaction_Manager::STATUS_REFUNDED
        );
        
        $stats = array();
        
        foreach ($statuses as $status) {
            $args = array(
                'post_type' => 'sms_transactions',
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'fields' => 'ids',
                'meta_query' => array(
                    array(
                        'key' => 'transaction_status',
                        'value' => $status,
                        'compare' => '='
                    )
                )
            );
            
            $transactions = get_posts($args);
            $stats[$status] = count($transactions);
        }
        
        return $stats;
    }
}