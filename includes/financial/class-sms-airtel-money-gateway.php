<?php
/**
 * Airtel Money Payment Gateway Implementation
 *
 * Handles Airtel Money payments with Airtel Money API
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
 * Airtel Money Payment Gateway Class
 */
class SMS_Airtel_Money_Gateway extends SMS_Payment_Gateway_Base {
    
    /**
     * Gateway identifier
     *
     * @var string
     */
    protected $gateway_id = 'airtel_money';
    
    /**
     * Gateway name
     *
     * @var string
     */
    protected $gateway_name = 'Airtel Money';
    
    /**
     * Airtel Money API endpoints
     *
     * @var array
     */
    private $api_endpoints = [
        'sandbox' => [
            'auth' => 'https://openapiuat.airtel.africa/auth/oauth2/token',
            'payment' => 'https://openapiuat.airtel.africa/merchant/v1/payments/',
            'status' => 'https://openapiuat.airtel.africa/standard/v1/payments/',
            'refund' => 'https://openapiuat.airtel.africa/standard/v1/payments/refund'
        ],
        'production' => [
            'auth' => 'https://openapi.airtel.africa/auth/oauth2/token',
            'payment' => 'https://openapi.airtel.africa/merchant/v1/payments/',
            'status' => 'https://openapi.airtel.africa/standard/v1/payments/',
            'refund' => 'https://openapi.airtel.africa/standard/v1/payments/refund'
        ]
    ];
    
    /**
     * Access token cache
     *
     * @var string
     */
    private $access_token;
    
    /**
     * Token expiry time
     *
     * @var int
     */
    private $token_expires_at;
    
    /**
     * Initialize Airtel Money gateway
     */
    protected function init() {
        // Only validate configuration if gateway is enabled
        if ($this->enabled) {
            $required_config = ['client_id', 'client_secret', 'merchant_id'];
            $validation = $this->validate_config($required_config);
            
            if (is_wp_error($validation)) {
                $this->log_transaction('config_validation_failed', [
                    'error' => $validation->get_error_message()
                ], 'error');
                return;
            }
        }
        
        // Set up callback URL
        if (empty($this->config['callback_url'])) {
            $this->config['callback_url'] = site_url('/wp-json/sms/v1/airtel/callback');
        }
        
        // Register REST API endpoints
        add_action('rest_api_init', [$this, 'register_rest_routes']);
    }
    
    /**
     * Register REST API routes for Airtel Money callbacks
     */
    public function register_rest_routes() {
        register_rest_route('sms/v1', '/airtel/callback', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_rest_callback'],
            'permission_callback' => '__return_true'
        ]);
        
        register_rest_route('sms/v1', '/airtel/status', [
            'methods' => 'GET',
            'callback' => [$this, 'handle_status_check'],
            'permission_callback' => '__return_true'
        ]);
    }
    
    /**
     * Initialize Airtel Money payment
     *
     * @param float $amount Payment amount
     * @param string $phone_number Customer phone number
     * @param string $reference Payment reference
     * @param array $additional_data Additional payment data
     * @return array|WP_Error
     */
    public function initialize_payment($amount, $phone_number, $reference, $additional_data = []) {
        // Validate phone number
        $phone_validation = $this->validate_phone_number($phone_number);
        if (is_wp_error($phone_validation)) {
            return $phone_validation;
        }
        
        // Normalize phone number for Airtel Money
        $normalized_phone = $this->normalize_phone_for_airtel($phone_number);
        
        // Get access token
        $access_token = $this->get_access_token();
        if (is_wp_error($access_token)) {
            return $access_token;
        }
        
        // Generate unique transaction ID
        $transaction_id = $this->generate_transaction_id($reference);
        
        // Prepare payment request
        $payment_request = [
            'reference' => $reference,
            'subscriber' => [
                'country' => 'KE',
                'currency' => 'KES',
                'msisdn' => $normalized_phone
            ],
            'transaction' => [
                'amount' => round($amount, 2),
                'country' => 'KE',
                'currency' => 'KES',
                'id' => $transaction_id
            ]
        ];
        
        // Make payment request
        $response = $this->make_payment_request($payment_request, $access_token);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        // Process response
        if (isset($response['status']) && $response['status']['success'] === true) {
            $result = [
                'status' => 'pending',
                'transaction_id' => $transaction_id,
                'airtel_transaction_id' => $response['data']['transaction']['id'] ?? $transaction_id,
                'response_code' => $response['status']['code'] ?? '200',
                'response_message' => $response['status']['message'] ?? 'Payment initiated',
                'gateway' => $this->gateway_id,
                'phone_number' => $normalized_phone,
                'amount' => $amount,
                'reference' => $reference
            ];
            
            // Store transaction for tracking
            $this->store_pending_transaction($result);
            
            $this->log_transaction('payment_initiated', $result);
            
            return $result;
        } else {
            $error_message = $response['status']['message'] ?? 'Payment initiation failed';
            $error_code = $response['status']['code'] ?? 'UNKNOWN_ERROR';
            
            $this->log_transaction('payment_initiation_failed', [
                'error' => $error_message,
                'error_code' => $error_code,
                'response' => $response
            ], 'error');
            
            return new WP_Error('payment_failed', $this->get_user_friendly_error($error_message, $error_code));
        }
    }
    
    /**
     * Verify Airtel Money payment
     *
     * @param string $transaction_id Transaction ID
     * @return array|WP_Error
     */
    public function verify_payment($transaction_id) {
        // Get access token
        $access_token = $this->get_access_token();
        if (is_wp_error($access_token)) {
            return $access_token;
        }
        
        // Make status check request
        $response = $this->make_status_request($transaction_id, $access_token);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        // Process response
        if (isset($response['status'])) {
            $result = [
                'transaction_id' => $transaction_id,
                'status_code' => $response['status']['code'] ?? '',
                'status_message' => $response['status']['message'] ?? '',
                'success' => $response['status']['success'] ?? false
            ];
            
            // Extract transaction data if available
            if (isset($response['data']['transaction'])) {
                $transaction_data = $response['data']['transaction'];
                $result['airtel_transaction_id'] = $transaction_data['id'] ?? '';
                $result['amount'] = $transaction_data['amount'] ?? 0;
                $result['currency'] = $transaction_data['currency'] ?? 'KES';
                $result['status_description'] = $transaction_data['status'] ?? '';
            }
            
            // Determine payment status
            if ($result['success'] && isset($response['data']['transaction']['status'])) {
                switch (strtoupper($response['data']['transaction']['status'])) {
                    case 'TS':
                    case 'SUCCESS':
                        $result['status'] = 'completed';
                        break;
                    case 'TF':
                    case 'FAILED':
                        $result['status'] = 'failed';
                        break;
                    case 'TA':
                    case 'AMBIGUOUS':
                        $result['status'] = 'pending';
                        break;
                    case 'TIP':
                    case 'IN_PROGRESS':
                        $result['status'] = 'pending';
                        break;
                    default:
                        $result['status'] = 'unknown';
                }
            } else {
                $result['status'] = 'failed';
            }
            
            $this->log_transaction('payment_verification', $result);
            
            return $result;
        } else {
            return new WP_Error('verification_failed', 'Payment verification failed');
        }
    }
    
    /**
     * Handle Airtel Money payment callback
     *
     * @param array $callback_data Callback data from Airtel Money
     * @return array|WP_Error
     */
    public function handle_callback($callback_data) {
        $this->log_transaction('callback_received', $callback_data);
        
        // Validate callback structure
        if (!isset($callback_data['transaction'])) {
            return new WP_Error('invalid_callback', 'Invalid callback structure');
        }
        
        $transaction = $callback_data['transaction'];
        
        // Extract callback data
        $result = [
            'transaction_id' => $transaction['id'] ?? '',
            'airtel_transaction_id' => $transaction['airtel_money_id'] ?? '',
            'status_code' => $transaction['status'] ?? '',
            'amount' => $transaction['amount'] ?? 0,
            'currency' => $transaction['currency'] ?? 'KES',
            'phone_number' => $transaction['msisdn'] ?? ''
        ];
        
        // Process payment status
        switch (strtoupper($transaction['status'] ?? '')) {
            case 'TS':
            case 'SUCCESS':
                $result['status'] = 'completed';
                
                // Update transaction status
                $this->update_transaction_status($result['transaction_id'], 'completed', $result);
                
                // Trigger payment completed action
                do_action('sms_airtel_money_payment_completed', $result);
                break;
                
            case 'TF':
            case 'FAILED':
                $result['status'] = 'failed';
                $this->update_transaction_status($result['transaction_id'], 'failed', $result);
                
                // Trigger payment failed action
                do_action('sms_airtel_money_payment_failed', $result);
                break;
                
            case 'TA':
            case 'AMBIGUOUS':
                $result['status'] = 'pending';
                $this->update_transaction_status($result['transaction_id'], 'pending', $result);
                break;
                
            default:
                $result['status'] = 'unknown';
                $this->log_transaction('unknown_callback_status', $result, 'warning');
        }
        
        $this->log_transaction('callback_processed', $result);
        
        return $result;
    }
    
    /**
     * Get transaction status
     *
     * @param string $transaction_id Transaction ID
     * @return string
     */
    public function get_transaction_status($transaction_id) {
        $verification = $this->verify_payment($transaction_id);
        
        if (is_wp_error($verification)) {
            return 'unknown';
        }
        
        return $verification['status'] ?? 'unknown';
    }
    
    /**
     * Refund Airtel Money payment
     *
     * @param string $transaction_id Transaction ID
     * @param float $amount Refund amount
     * @param string $reason Refund reason
     * @return array|WP_Error
     */
    public function refund_payment($transaction_id, $amount, $reason = '') {
        // Get access token
        $access_token = $this->get_access_token();
        if (is_wp_error($access_token)) {
            return $access_token;
        }
        
        // Prepare refund request
        $refund_request = [
            'transaction' => [
                'airtel_money_id' => $transaction_id,
                'amount' => round($amount, 2),
                'currency' => 'KES'
            ]
        ];
        
        if (!empty($reason)) {
            $refund_request['transaction']['reason'] = $reason;
        }
        
        // Make refund request
        $response = $this->make_refund_request($refund_request, $access_token);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        // Process refund response
        if (isset($response['status']) && $response['status']['success'] === true) {
            $result = [
                'status' => 'refunded',
                'refund_id' => $response['data']['transaction']['id'] ?? '',
                'original_transaction_id' => $transaction_id,
                'refund_amount' => $amount,
                'refund_reason' => $reason,
                'response_code' => $response['status']['code'] ?? '200',
                'response_message' => $response['status']['message'] ?? 'Refund processed'
            ];
            
            $this->log_transaction('refund_processed', $result);
            
            // Trigger refund completed action
            do_action('sms_airtel_money_refund_completed', $result);
            
            return $result;
        } else {
            $error_message = $response['status']['message'] ?? 'Refund failed';
            $this->log_transaction('refund_failed', [
                'error' => $error_message,
                'response' => $response
            ], 'error');
            
            return new WP_Error('refund_failed', $error_message);
        }
    }
    
    /**
     * Get Airtel Money access token
     *
     * @return string|WP_Error
     */
    private function get_access_token() {
        // Check if we have a valid cached token
        if ($this->access_token && $this->token_expires_at > time()) {
            return $this->access_token;
        }
        
        // Get environment endpoints
        $environment = $this->sandbox_mode ? 'sandbox' : 'production';
        $auth_url = $this->api_endpoints[$environment]['auth'];
        
        // Prepare authentication request
        $auth_data = [
            'client_id' => $this->config['client_id'],
            'client_secret' => $this->config['client_secret'],
            'grant_type' => 'client_credentials'
        ];
        
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ];
        
        // Make authentication request
        $response = $this->make_api_request($auth_url, $auth_data, 'POST', $headers);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        if (isset($response['access_token'])) {
            $this->access_token = $response['access_token'];
            $this->token_expires_at = time() + (int)$response['expires_in'] - 60; // 60 seconds buffer
            
            $this->log_transaction('token_generated', [
                'expires_in' => $response['expires_in']
            ]);
            
            return $this->access_token;
        } else {
            $error_message = $response['error_description'] ?? $response['error'] ?? 'Authentication failed';
            return new WP_Error('auth_failed', $error_message);
        }
    }
    
    /**
     * Make payment request
     *
     * @param array $request_data Payment request data
     * @param string $access_token Access token
     * @return array|WP_Error
     */
    private function make_payment_request($request_data, $access_token) {
        $environment = $this->sandbox_mode ? 'sandbox' : 'production';
        $payment_url = $this->api_endpoints[$environment]['payment'];
        
        $headers = [
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-Country' => 'KE',
            'X-Currency' => 'KES'
        ];
        
        return $this->make_api_request($payment_url, $request_data, 'POST', $headers);
    }
    
    /**
     * Make status check request
     *
     * @param string $transaction_id Transaction ID
     * @param string $access_token Access token
     * @return array|WP_Error
     */
    private function make_status_request($transaction_id, $access_token) {
        $environment = $this->sandbox_mode ? 'sandbox' : 'production';
        $status_url = $this->api_endpoints[$environment]['status'] . $transaction_id;
        
        $headers = [
            'Authorization' => 'Bearer ' . $access_token,
            'Accept' => 'application/json',
            'X-Country' => 'KE',
            'X-Currency' => 'KES'
        ];
        
        return $this->make_api_request($status_url, [], 'GET', $headers);
    }
    
    /**
     * Make refund request
     *
     * @param array $request_data Refund request data
     * @param string $access_token Access token
     * @return array|WP_Error
     */
    private function make_refund_request($request_data, $access_token) {
        $environment = $this->sandbox_mode ? 'sandbox' : 'production';
        $refund_url = $this->api_endpoints[$environment]['refund'];
        
        $headers = [
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-Country' => 'KE',
            'X-Currency' => 'KES'
        ];
        
        return $this->make_api_request($refund_url, $request_data, 'POST', $headers);
    }
    
    /**
     * Normalize phone number for Airtel Money format
     *
     * @param string $phone Phone number
     * @return string
     */
    private function normalize_phone_for_airtel($phone) {
        // Remove all non-numeric characters except +
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // Convert to international format without + (Airtel Money requirement)
        if (preg_match('/^0([17]\d{8})$/', $phone, $matches)) {
            return '254' . $matches[1];
        } elseif (preg_match('/^\+254([17]\d{8})$/', $phone, $matches)) {
            return '254' . $matches[1];
        } elseif (preg_match('/^254[17]\d{8}$/', $phone)) {
            return $phone;
        }
        
        return $phone;
    }
    
    /**
     * Generate unique transaction ID
     *
     * @param string $reference Payment reference
     * @return string
     */
    private function generate_transaction_id($reference) {
        return 'AIRTEL_' . strtoupper($reference) . '_' . time() . '_' . $this->generate_random_string(6);
    }


    
    /**
     * Store pending transaction for tracking
     *
     * @param array $transaction_data Transaction data
     */
    private function store_pending_transaction($transaction_data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'sms_airtel_money_transactions';
        
        $wpdb->insert(
            $table_name,
            [
                'transaction_id' => $transaction_data['transaction_id'],
                'airtel_transaction_id' => $transaction_data['airtel_transaction_id'],
                'phone_number' => $transaction_data['phone_number'],
                'amount' => $transaction_data['amount'],
                'reference' => $transaction_data['reference'],
                'status' => 'pending',
                'created_at' => current_time('mysql'),
                'response_data' => json_encode($transaction_data)
            ],
            [
                '%s', '%s', '%s', '%f', '%s', '%s', '%s', '%s'
            ]
        );
    }
    
    /**
     * Update transaction status
     *
     * @param string $transaction_id Transaction ID
     * @param string $status New status
     * @param array $callback_data Callback data
     */
    private function update_transaction_status($transaction_id, $status, $callback_data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'sms_airtel_money_transactions';
        
        $update_data = [
            'status' => $status,
            'updated_at' => current_time('mysql'),
            'callback_data' => json_encode($callback_data)
        ];
        
        if ($status === 'completed' && isset($callback_data['airtel_transaction_id'])) {
            $update_data['airtel_transaction_id'] = $callback_data['airtel_transaction_id'];
        }
        
        $wpdb->update(
            $table_name,
            $update_data,
            ['transaction_id' => $transaction_id],
            ['%s', '%s', '%s', '%s'],
            ['%s']
        );
    }
    
    /**
     * Handle REST API callback
     *
     * @param WP_REST_Request $request REST request
     * @return WP_REST_Response
     */
    public function handle_rest_callback($request) {
        $callback_data = $request->get_json_params();
        
        if (empty($callback_data)) {
            $callback_data = $request->get_body_params();
        }
        
        $result = $this->handle_callback($callback_data);
        
        if (is_wp_error($result)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => $result->get_error_message()
            ], 400);
        }
        
        return new WP_REST_Response([
            'success' => true,
            'data' => $result
        ], 200);
    }
    
    /**
     * Handle status check request
     *
     * @param WP_REST_Request $request REST request
     * @return WP_REST_Response
     */
    public function handle_status_check($request) {
        $transaction_id = $request->get_param('transaction_id');
        
        if (empty($transaction_id)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Transaction ID is required'
            ], 400);
        }
        
        $result = $this->verify_payment($transaction_id);
        
        if (is_wp_error($result)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => $result->get_error_message()
            ], 400);
        }
        
        return new WP_REST_Response([
            'success' => true,
            'data' => $result
        ], 200);
    }
    
    /**
     * Get user-friendly error message
     *
     * @param string $error_message Original error message
     * @param string $error_code Error code
     * @return string
     */
    private function get_user_friendly_error($error_message, $error_code = '') {
        $error_mappings = [
            'DP00800001001' => 'Invalid phone number. Please check and try again.',
            'DP00800001002' => 'Insufficient Airtel Money balance. Please top up and try again.',
            'DP00800001003' => 'Transaction limit exceeded. Please try with a smaller amount.',
            'DP00800001004' => 'Service temporarily unavailable. Please try again later.',
            'DP00800001005' => 'Invalid transaction amount. Please check and try again.',
            'DP00800001006' => 'Transaction declined by customer.',
            'DP00800001007' => 'Transaction timeout. Please try again.',
            'INVALID_MSISDN' => 'Invalid phone number format. Please use format 07XXXXXXXX.',
            'INSUFFICIENT_BALANCE' => 'Insufficient Airtel Money balance. Please top up and try again.',
            'TRANSACTION_FAILED' => 'Transaction failed. Please try again.',
            'SERVICE_UNAVAILABLE' => 'Airtel Money service is temporarily unavailable.',
            'INVALID_AMOUNT' => 'Invalid payment amount. Please check and try again.',
            'USER_CANCELLED' => 'Payment was cancelled. You can try again anytime.',
            'TIMEOUT' => 'Transaction timed out. Please try again.'
        ];
        
        // Check by error code first
        if (!empty($error_code) && isset($error_mappings[$error_code])) {
            return $error_mappings[$error_code];
        }
        
        // Check by error message
        foreach ($error_mappings as $original => $friendly) {
            if (stripos($error_message, $original) !== false) {
                return $friendly;
            }
        }
        
        return 'Payment processing failed. Please try again or contact support.';
    }
    
    /**
     * Get gateway capabilities
     *
     * @return array
     */
    public function get_capabilities() {
        return [
            'initialize_payment' => true,
            'verify_payment' => true,
            'handle_callback' => true,
            'refund_payment' => true,
            'recurring_payments' => false,
            'partial_payments' => false
        ];
    }
    
    /**
     * Get supported currencies
     *
     * @return array
     */
    public function get_supported_currencies() {
        return ['KES']; // Airtel Money Kenya supports Kenyan Shilling
    }
    
    /**
     * Validate Airtel Money specific configuration
     *
     * @return bool|WP_Error
     */
    public function validate_gateway_config() {
        $required_fields = [
            'client_id' => 'Client ID',
            'client_secret' => 'Client Secret',
            'merchant_id' => 'Merchant ID'
        ];
        
        foreach ($required_fields as $field => $label) {
            if (empty($this->config[$field])) {
                return new WP_Error('missing_config', "Missing required Airtel Money configuration: {$label}");
            }
        }
        
        return true;
    }
    
    /**
     * Test Airtel Money connection
     *
     * @return array|WP_Error
     */
    public function test_connection() {
        $access_token = $this->get_access_token();
        
        if (is_wp_error($access_token)) {
            return $access_token;
        }
        
        return [
            'success' => true,
            'message' => 'Airtel Money connection successful',
            'environment' => $this->sandbox_mode ? 'sandbox' : 'production',
            'merchant_id' => $this->config['merchant_id']
        ];
    }
    
    /**
     * Get transaction by transaction ID
     *
     * @param string $transaction_id Transaction ID
     * @return array|null
     */
    public function get_transaction($transaction_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'sms_airtel_money_transactions';
        
        $transaction = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE transaction_id = %s",
                $transaction_id
            ),
            ARRAY_A
        );
        
        if ($transaction) {
            $transaction['response_data'] = json_decode($transaction['response_data'], true);
            $transaction['callback_data'] = json_decode($transaction['callback_data'], true);
        }
        
        return $transaction;
    }
    
    /**
     * Create Airtel Money transactions table
     */
    public static function create_transactions_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'sms_airtel_money_transactions';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            transaction_id varchar(100) NOT NULL,
            airtel_transaction_id varchar(100) DEFAULT NULL,
            phone_number varchar(20) NOT NULL,
            amount decimal(10,2) NOT NULL,
            reference varchar(100) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            response_data longtext DEFAULT NULL,
            callback_data longtext DEFAULT NULL,
            created_at datetime NOT NULL,
            updated_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY transaction_id (transaction_id),
            KEY status (status),
            KEY phone_number (phone_number),
            KEY reference (reference),
            KEY airtel_transaction_id (airtel_transaction_id)
        ) {$charset_collate};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}