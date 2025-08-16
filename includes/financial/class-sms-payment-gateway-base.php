<?php
/**
 * Abstract Payment Gateway Base Class
 *
 * Provides standardized interface for all payment gateway implementations
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
 * Abstract base class for payment gateways
 */
abstract class SMS_Payment_Gateway_Base {
    
    /**
     * Gateway identifier
     *
     * @var string
     */
    protected $gateway_id;
    
    /**
     * Gateway name
     *
     * @var string
     */
    protected $gateway_name;
    
    /**
     * Gateway configuration
     *
     * @var array
     */
    protected $config;
    
    /**
     * Whether gateway is in sandbox mode
     *
     * @var bool
     */
    protected $sandbox_mode;
    
    /**
     * Gateway status
     *
     * @var bool
     */
    protected $enabled;
    
    /**
     * Constructor
     *
     * @param array $config Gateway configuration
     */
    public function __construct($config = array()) {
        $this->config = $config;
        $this->sandbox_mode = isset($config['sandbox_mode']) ? $config['sandbox_mode'] : true;
        $this->enabled = isset($config['enabled']) ? $config['enabled'] : false;
        
        $this->init();
    }
    
    /**
     * Initialize gateway
     */
    protected function init() {
        // Override in child classes for specific initialization
    }
    
    /**
     * Get gateway ID
     *
     * @return string
     */
    public function get_id() {
        return $this->gateway_id;
    }
    
    /**
     * Get gateway name
     *
     * @return string
     */
    public function get_name() {
        return $this->gateway_name;
    }
    
    /**
     * Check if gateway is enabled
     *
     * @return bool
     */
    public function is_enabled() {
        return $this->enabled;
    }
    
    /**
     * Check if gateway is in sandbox mode
     *
     * @return bool
     */
    public function is_sandbox() {
        return $this->sandbox_mode;
    }
    
    /**
     * Get gateway configuration
     *
     * @param string $key Optional configuration key
     * @return mixed
     */
    public function get_config($key = null) {
        if ($key) {
            return isset($this->config[$key]) ? $this->config[$key] : null;
        }
        return $this->config;
    }
    
    /**
     * Initialize payment transaction
     *
     * @param float $amount Payment amount
     * @param string $phone_number Customer phone number
     * @param string $reference Payment reference
     * @param array $additional_data Additional payment data
     * @return array|WP_Error Payment initialization result
     */
    abstract public function initialize_payment($amount, $phone_number, $reference, $additional_data = array());
    
    /**
     * Verify payment status
     *
     * @param string $transaction_id Gateway transaction ID
     * @return array|WP_Error Payment verification result
     */
    abstract public function verify_payment($transaction_id);
    
    /**
     * Handle payment callback
     *
     * @param array $callback_data Callback data from gateway
     * @return array|WP_Error Callback processing result
     */
    abstract public function handle_callback($callback_data);
    
    /**
     * Get transaction status
     *
     * @param string $transaction_id Gateway transaction ID
     * @return string Transaction status
     */
    abstract public function get_transaction_status($transaction_id);
    
    /**
     * Refund payment
     *
     * @param string $transaction_id Gateway transaction ID
     * @param float $amount Refund amount
     * @param string $reason Refund reason
     * @return array|WP_Error Refund result
     */
    public function refund_payment($transaction_id, $amount, $reason = '') {
        return new WP_Error('not_supported', 'Refunds not supported by this gateway');
    }
    
    /**
     * Validate phone number format
     *
     * @param string $phone Phone number to validate
     * @return bool|WP_Error
     */
    protected function validate_phone_number($phone) {
        // Remove spaces and special characters
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // Kenyan phone number patterns
        $patterns = array(
            '/^\+254[17]\d{8}$/',  // +254 format
            '/^254[17]\d{8}$/',    // 254 format
            '/^0[17]\d{8}$/'       // 0 format
        );
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $phone)) {
                return true;
            }
        }
        
        return new WP_Error('invalid_phone', 'Invalid phone number format');
    }
    
    /**
     * Normalize phone number to international format
     *
     * @param string $phone Phone number
     * @return string Normalized phone number
     */
    protected function normalize_phone_number($phone) {
        // Remove spaces and special characters
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // Convert to +254 format
        if (preg_match('/^0([17]\d{8})$/', $phone, $matches)) {
            return '+254' . $matches[1];
        } elseif (preg_match('/^254([17]\d{8})$/', $phone, $matches)) {
            return '+254' . $matches[1];
        } elseif (preg_match('/^\+254[17]\d{8}$/', $phone)) {
            return $phone;
        }
        
        return $phone;
    }
    
    /**
     * Log transaction activity
     *
     * @param string $action Action performed
     * @param array $data Transaction data
     * @param string $level Log level (info, warning, error)
     */
    protected function log_transaction($action, $data, $level = 'info') {
        $log_entry = array(
            'gateway' => $this->gateway_id,
            'action' => $action,
            'data' => $data,
            'timestamp' => current_time('mysql'),
            'level' => $level
        );
        
        // Use WordPress error logging
        if ($level === 'error') {
            error_log('SMS Payment Gateway Error: ' . json_encode($log_entry));
        }
        
        // Store in custom log table if needed
        do_action('sms_payment_gateway_log', $log_entry);
    }
    
    /**
     * Generate unique transaction reference
     *
     * @param string $prefix Optional prefix
     * @return string
     */
    protected function generate_reference($prefix = 'SMS') {
        return $prefix . '_' . time() . '_' . wp_generate_password(8, false);
    }
    
    /**
     * Validate required configuration
     *
     * @param array $required_keys Required configuration keys
     * @return bool|WP_Error
     */
    protected function validate_config($required_keys) {
        foreach ($required_keys as $key) {
            if (empty($this->config[$key])) {
                return new WP_Error('missing_config', "Missing required configuration: {$key}");
            }
        }
        return true;
    }
    
    /**
     * Make HTTP request to gateway API
     *
     * @param string $url API endpoint URL
     * @param array $data Request data
     * @param string $method HTTP method
     * @param array $headers Request headers
     * @return array|WP_Error
     */
    protected function make_api_request($url, $data = array(), $method = 'POST', $headers = array()) {
        $args = array(
            'method' => $method,
            'headers' => $headers,
            'timeout' => 30,
            'sslverify' => !$this->sandbox_mode
        );
        
        if ($method === 'POST') {
            $args['body'] = is_array($data) ? json_encode($data) : $data;
            if (!isset($headers['Content-Type'])) {
                $args['headers']['Content-Type'] = 'application/json';
            }
        } elseif ($method === 'GET' && !empty($data)) {
            $url .= '?' . http_build_query($data);
        }
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            $this->log_transaction('api_request_failed', array(
                'url' => $url,
                'error' => $response->get_error_message()
            ), 'error');
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('invalid_response', 'Invalid JSON response from gateway');
        }
        
        return $decoded;
    }
    
    /**
     * Get supported currencies
     *
     * @return array
     */
    public function get_supported_currencies() {
        return array('KES'); // Default to Kenyan Shilling
    }
    
    /**
     * Get gateway capabilities
     *
     * @return array
     */
    public function get_capabilities() {
        return array(
            'initialize_payment' => true,
            'verify_payment' => true,
            'handle_callback' => true,
            'refund_payment' => false
        );
    }
}