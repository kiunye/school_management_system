<?php
/**
 * Payment Gateway Manager
 *
 * Manages multiple payment gateways and coordinates payment processing
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
 * Payment Gateway Manager Class
 */
class SMS_Payment_Gateway_Manager {
    
    /**
     * Registered payment gateways
     *
     * @var array
     */
    private $gateways = array();
    
    /**
     * Default gateway
     *
     * @var string
     */
    private $default_gateway;
    
    /**
     * Fallback gateways order
     *
     * @var array
     */
    private $fallback_order = array();
    
    /**
     * Instance of this class
     *
     * @var SMS_Payment_Gateway_Manager
     */
    private static $instance = null;
    
    /**
     * Get singleton instance
     *
     * @return SMS_Payment_Gateway_Manager
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
     * Initialize the gateway manager
     */
    private function init() {
        // Load gateway configurations
        $this->load_gateway_configs();
        
        // Set up hooks
        add_action('init', array($this, 'register_default_gateways'));
        add_action('wp_ajax_sms_process_payment', array($this, 'handle_ajax_payment'));
        add_action('wp_ajax_nopriv_sms_process_payment', array($this, 'handle_ajax_payment'));
        
        // Handle gateway callbacks
        add_action('wp_ajax_sms_payment_callback', array($this, 'handle_payment_callback'));
        add_action('wp_ajax_nopriv_sms_payment_callback', array($this, 'handle_payment_callback'));
    }
    
    /**
     * Load gateway configurations from WordPress options
     */
    private function load_gateway_configs() {
        $configs = get_option('sms_payment_gateway_configs', array());
        $this->default_gateway = get_option('sms_default_payment_gateway', 'mpesa');
        $this->fallback_order = get_option('sms_payment_gateway_fallback_order', array('mpesa', 'airtel_money', 'cash'));
    }
    
    /**
     * Register a payment gateway
     *
     * @param string $gateway_id Gateway identifier
     * @param SMS_Payment_Gateway_Base $gateway Gateway instance
     * @return bool
     */
    public function register_gateway($gateway_id, SMS_Payment_Gateway_Base $gateway) {
        if (isset($this->gateways[$gateway_id])) {
            return false; // Gateway already registered
        }
        
        $this->gateways[$gateway_id] = $gateway;
        
        do_action('sms_payment_gateway_registered', $gateway_id, $gateway);
        
        return true;
    }
    
    /**
     * Unregister a payment gateway
     *
     * @param string $gateway_id Gateway identifier
     * @return bool
     */
    public function unregister_gateway($gateway_id) {
        if (!isset($this->gateways[$gateway_id])) {
            return false;
        }
        
        unset($this->gateways[$gateway_id]);
        
        do_action('sms_payment_gateway_unregistered', $gateway_id);
        
        return true;
    }
    
    /**
     * Get a specific gateway
     *
     * @param string $gateway_id Gateway identifier
     * @return SMS_Payment_Gateway_Base|null
     */
    public function get_gateway($gateway_id) {
        return isset($this->gateways[$gateway_id]) ? $this->gateways[$gateway_id] : null;
    }
    
    /**
     * Get all registered gateways
     *
     * @param bool $enabled_only Return only enabled gateways
     * @return array
     */
    public function get_gateways($enabled_only = false) {
        if (!$enabled_only) {
            return $this->gateways;
        }
        
        return array_filter($this->gateways, function($gateway) {
            return $gateway->is_enabled();
        });
    }
    
    /**
     * Get available payment methods for user selection
     *
     * @return array
     */
    public function get_available_payment_methods() {
        $methods = array();
        
        foreach ($this->get_gateways(true) as $gateway_id => $gateway) {
            $methods[$gateway_id] = array(
                'id' => $gateway_id,
                'name' => $gateway->get_name(),
                'enabled' => $gateway->is_enabled(),
                'capabilities' => $gateway->get_capabilities()
            );
        }
        
        return $methods;
    }
    
    /**
     * Select appropriate gateway for payment
     *
     * @param string $preferred_gateway Preferred gateway ID
     * @param float $amount Payment amount
     * @param array $criteria Selection criteria
     * @return string|WP_Error Selected gateway ID
     */
    public function select_gateway($preferred_gateway = null, $amount = 0, $criteria = array()) {
        // If preferred gateway is specified and available, use it
        if ($preferred_gateway && $this->is_gateway_available($preferred_gateway, $amount, $criteria)) {
            return $preferred_gateway;
        }
        
        // Try default gateway
        if ($this->is_gateway_available($this->default_gateway, $amount, $criteria)) {
            return $this->default_gateway;
        }
        
        // Try fallback gateways in order
        foreach ($this->fallback_order as $gateway_id) {
            if ($this->is_gateway_available($gateway_id, $amount, $criteria)) {
                return $gateway_id;
            }
        }
        
        return new WP_Error('no_gateway_available', 'No payment gateway available for this transaction');
    }
    
    /**
     * Check if gateway is available for payment
     *
     * @param string $gateway_id Gateway identifier
     * @param float $amount Payment amount
     * @param array $criteria Selection criteria
     * @return bool
     */
    private function is_gateway_available($gateway_id, $amount = 0, $criteria = array()) {
        $gateway = $this->get_gateway($gateway_id);
        
        if (!$gateway || !$gateway->is_enabled()) {
            return false;
        }
        
        // Apply custom availability filters
        return apply_filters('sms_gateway_available', true, $gateway_id, $amount, $criteria);
    }
    
    /**
     * Process payment through selected gateway
     *
     * @param string $gateway_id Gateway identifier
     * @param float $amount Payment amount
     * @param string $phone_number Customer phone number
     * @param string $reference Payment reference
     * @param array $additional_data Additional payment data
     * @return array|WP_Error Payment result
     */
    public function process_payment($gateway_id, $amount, $phone_number, $reference, $additional_data = array()) {
        $gateway = $this->get_gateway($gateway_id);
        
        if (!$gateway) {
            return new WP_Error('gateway_not_found', 'Payment gateway not found');
        }
        
        if (!$gateway->is_enabled()) {
            return new WP_Error('gateway_disabled', 'Payment gateway is disabled');
        }
        
        // Validate payment data
        $validation = $this->validate_payment_data($amount, $phone_number, $reference);
        if (is_wp_error($validation)) {
            return $validation;
        }
        
        // Log payment attempt
        $this->log_payment_attempt($gateway_id, $amount, $phone_number, $reference);
        
        try {
            $result = $gateway->initialize_payment($amount, $phone_number, $reference, $additional_data);
            
            if (is_wp_error($result)) {
                $this->log_payment_failure($gateway_id, $result->get_error_message());
                
                // Try fallback if enabled
                if (isset($additional_data['enable_fallback']) && $additional_data['enable_fallback']) {
                    return $this->try_fallback_payment($gateway_id, $amount, $phone_number, $reference, $additional_data);
                }
            } else {
                $this->log_payment_success($gateway_id, $result);
            }
            
            return $result;
            
        } catch (Exception $e) {
            $error_message = 'Payment processing failed: ' . $e->getMessage();
            $this->log_payment_failure($gateway_id, $error_message);
            return new WP_Error('payment_exception', $error_message);
        }
    }
    
    /**
     * Try fallback payment gateways
     *
     * @param string $failed_gateway_id Failed gateway ID
     * @param float $amount Payment amount
     * @param string $phone_number Customer phone number
     * @param string $reference Payment reference
     * @param array $additional_data Additional payment data
     * @return array|WP_Error
     */
    private function try_fallback_payment($failed_gateway_id, $amount, $phone_number, $reference, $additional_data) {
        foreach ($this->fallback_order as $gateway_id) {
            if ($gateway_id === $failed_gateway_id) {
                continue; // Skip the failed gateway
            }
            
            if ($this->is_gateway_available($gateway_id, $amount)) {
                $gateway = $this->get_gateway($gateway_id);
                
                try {
                    $result = $gateway->initialize_payment($amount, $phone_number, $reference, $additional_data);
                    
                    if (!is_wp_error($result)) {
                        $this->log_fallback_success($failed_gateway_id, $gateway_id);
                        return $result;
                    }
                } catch (Exception $e) {
                    // Continue to next fallback
                    continue;
                }
            }
        }
        
        return new WP_Error('all_gateways_failed', 'All payment gateways failed to process the payment');
    }
    
    /**
     * Verify payment across gateways
     *
     * @param string $gateway_id Gateway identifier
     * @param string $transaction_id Transaction ID
     * @return array|WP_Error
     */
    public function verify_payment($gateway_id, $transaction_id) {
        $gateway = $this->get_gateway($gateway_id);
        
        if (!$gateway) {
            return new WP_Error('gateway_not_found', 'Payment gateway not found');
        }
        
        return $gateway->verify_payment($transaction_id);
    }
    
    /**
     * Handle payment callback
     */
    public function handle_payment_callback() {
        $gateway_id = isset($_GET['gateway']) ? sanitize_text_field($_GET['gateway']) : '';
        
        if (empty($gateway_id)) {
            wp_die('Invalid gateway callback', 'Payment Callback Error', array('response' => 400));
        }
        
        $gateway = $this->get_gateway($gateway_id);
        
        if (!$gateway) {
            wp_die('Gateway not found', 'Payment Callback Error', array('response' => 404));
        }
        
        $callback_data = $_POST;
        $result = $gateway->handle_callback($callback_data);
        
        if (is_wp_error($result)) {
            wp_die($result->get_error_message(), 'Payment Callback Error', array('response' => 400));
        }
        
        // Send appropriate response
        wp_send_json_success($result);
    }
    
    /**
     * Handle AJAX payment processing
     */
    public function handle_ajax_payment() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_payment_nonce')) {
            wp_send_json_error('Invalid security token');
        }
        
        $gateway_id = sanitize_text_field($_POST['gateway_id']);
        $amount = floatval($_POST['amount']);
        $phone_number = sanitize_text_field($_POST['phone_number']);
        $reference = sanitize_text_field($_POST['reference']);
        
        $result = $this->process_payment($gateway_id, $amount, $phone_number, $reference);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * Validate payment data
     *
     * @param float $amount Payment amount
     * @param string $phone_number Phone number
     * @param string $reference Payment reference
     * @return bool|WP_Error
     */
    private function validate_payment_data($amount, $phone_number, $reference) {
        if ($amount <= 0) {
            return new WP_Error('invalid_amount', 'Payment amount must be greater than zero');
        }
        
        if (empty($phone_number)) {
            return new WP_Error('missing_phone', 'Phone number is required');
        }
        
        if (empty($reference)) {
            return new WP_Error('missing_reference', 'Payment reference is required');
        }
        
        return true;
    }
    
    /**
     * Register default gateways
     */
    public function register_default_gateways() {
        // This will be called by individual gateway classes
        do_action('sms_register_payment_gateways', $this);
    }
    
    /**
     * Log payment attempt
     */
    private function log_payment_attempt($gateway_id, $amount, $phone_number, $reference) {
        error_log("SMS Payment Attempt: Gateway={$gateway_id}, Amount={$amount}, Phone={$phone_number}, Reference={$reference}");
    }
    
    /**
     * Log payment success
     */
    private function log_payment_success($gateway_id, $result) {
        error_log("SMS Payment Success: Gateway={$gateway_id}, Result=" . json_encode($result));
    }
    
    /**
     * Log payment failure
     */
    private function log_payment_failure($gateway_id, $error_message) {
        error_log("SMS Payment Failure: Gateway={$gateway_id}, Error={$error_message}");
    }
    
    /**
     * Log fallback success
     */
    private function log_fallback_success($failed_gateway, $success_gateway) {
        error_log("SMS Payment Fallback Success: Failed={$failed_gateway}, Success={$success_gateway}");
    }
    
    /**
     * Get gateway statistics
     *
     * @return array
     */
    public function get_gateway_statistics() {
        $stats = array();
        
        foreach ($this->gateways as $gateway_id => $gateway) {
            $stats[$gateway_id] = array(
                'name' => $gateway->get_name(),
                'enabled' => $gateway->is_enabled(),
                'sandbox' => $gateway->is_sandbox()
            );
        }
        
        return $stats;
    }
}