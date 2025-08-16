<?php
/**
 * M-Pesa Payment Gateway Implementation
 *
 * Handles M-Pesa STK Push payments with Safaricom Daraja API
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
 * M-Pesa Payment Gateway Class
 */
class SMS_MPESA_Gateway extends SMS_Payment_Gateway_Base {
    
    /**
     * Gateway identifier
     *
     * @var string
     */
    protected $gateway_id = 'mpesa';
    
    /**
     * Gateway name
     *
     * @var string
     */
    protected $gateway_name = 'M-Pesa';
    
    /**
     * M-Pesa API endpoints
     *
     * @var array
     */
    private $api_endpoints = [
        'sandbox' => [
            'auth' => 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials',
            'stk_push' => 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest',
            'stk_query' => 'https://sandbox.safaricom.co.ke/mpesa/stkpushquery/v1/query'
        ],
        'production' => [
            'auth' => 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials',
            'stk_push' => 'https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest',
            'stk_query' => 'https://api.safaricom.co.ke/mpesa/stkpushquery/v1/query'
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
     * Initialize M-Pesa gateway
     */
    protected function init() {
        // Only validate configuration if gateway is enabled
        if ($this->enabled) {
            $required_config = ['consumer_key', 'consumer_secret', 'shortcode', 'passkey'];
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
            $this->config['callback_url'] = site_url('/wp-json/sms/v1/mpesa/callback');
        }
        
        // Register REST API endpoints
        add_action('rest_api_init', [$this, 'register_rest_routes']);
    }
    
    /**
     * Register REST API routes for M-Pesa callbacks
     */
    public function register_rest_routes() {
        register_rest_route('sms/v1', '/mpesa/callback', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_rest_callback'],
            'permission_callback' => '__return_true'
        ]);
        
        register_rest_route('sms/v1', '/mpesa/timeout', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_timeout_callback'],
            'permission_callback' => '__return_true'
        ]);
    }
    
    /**
     * Initialize M-Pesa payment
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
        
        // Normalize phone number for M-Pesa
        $normalized_phone = $this->normalize_phone_for_mpesa($phone_number);
        
        // Get access token
        $access_token = $this->get_access_token();
        if (is_wp_error($access_token)) {
            return $access_token;
        }
        
        // Generate timestamp and password
        $timestamp = date('YmdHis');
        $password = base64_encode($this->config['shortcode'] . $this->config['passkey'] . $timestamp);
        
        // Prepare STK Push request
        $stk_request = [
            'BusinessShortCode' => $this->config['shortcode'],
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => round($amount),
            'PartyA' => $normalized_phone,
            'PartyB' => $this->config['shortcode'],
            'PhoneNumber' => $normalized_phone,
            'CallBackURL' => $this->config['callback_url'],
            'AccountReference' => $reference,
            'TransactionDesc' => $additional_data['description'] ?? 'School Fee Payment'
        ];
        
        // Make STK Push request
        $response = $this->make_stk_push_request($stk_request, $access_token);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        // Process response
        if (isset($response['ResponseCode']) && $response['ResponseCode'] === '0') {
            $result = [
                'status' => 'pending',
                'transaction_id' => $response['CheckoutRequestID'],
                'merchant_request_id' => $response['MerchantRequestID'],
                'checkout_request_id' => $response['CheckoutRequestID'],
                'response_code' => $response['ResponseCode'],
                'response_description' => $response['ResponseDescription'],
                'customer_message' => $response['CustomerMessage'],
                'gateway' => $this->gateway_id,
                'phone_number' => $normalized_phone,
                'amount' => $amount,
                'reference' => $reference
            ];
            
            // Store transaction for tracking
            $this->store_pending_transaction($result);
            
            $this->log_transaction('stk_push_success', $result);
            
            return $result;
        } else {
            $error_message = $response['errorMessage'] ?? 'STK Push failed';
            $this->log_transaction('stk_push_failed', [
                'error' => $error_message,
                'response' => $response
            ], 'error');
            
            return new WP_Error('stk_push_failed', $this->get_user_friendly_error($error_message));
        }
    }
    
    /**
     * Verify M-Pesa payment
     *
     * @param string $transaction_id CheckoutRequestID
     * @return array|WP_Error
     */
    public function verify_payment($transaction_id) {
        // Get access token
        $access_token = $this->get_access_token();
        if (is_wp_error($access_token)) {
            return $access_token;
        }
        
        // Generate timestamp and password
        $timestamp = date('YmdHis');
        $password = base64_encode($this->config['shortcode'] . $this->config['passkey'] . $timestamp);
        
        // Prepare STK query request
        $query_request = [
            'BusinessShortCode' => $this->config['shortcode'],
            'Password' => $password,
            'Timestamp' => $timestamp,
            'CheckoutRequestID' => $transaction_id
        ];
        
        // Make STK query request
        $response = $this->make_stk_query_request($query_request, $access_token);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        // Process response
        if (isset($response['ResponseCode'])) {
            $result = [
                'transaction_id' => $transaction_id,
                'response_code' => $response['ResponseCode'],
                'response_description' => $response['ResponseDescription'],
                'merchant_request_id' => $response['MerchantRequestID'] ?? '',
                'checkout_request_id' => $response['CheckoutRequestID'] ?? '',
                'result_code' => $response['ResultCode'] ?? '',
                'result_desc' => $response['ResultDesc'] ?? ''
            ];
            
            // Determine status based on result code
            if (isset($response['ResultCode'])) {
                switch ($response['ResultCode']) {
                    case '0':
                        $result['status'] = 'completed';
                        break;
                    case '1032':
                        $result['status'] = 'cancelled';
                        break;
                    case '1037':
                        $result['status'] = 'timeout';
                        break;
                    default:
                        $result['status'] = 'failed';
                }
            } else {
                $result['status'] = 'pending';
            }
            
            $this->log_transaction('payment_verification', $result);
            
            return $result;
        } else {
            return new WP_Error('verification_failed', 'Payment verification failed');
        }
    }
    
    /**
     * Handle M-Pesa payment callback
     *
     * @param array $callback_data Callback data from M-Pesa
     * @return array|WP_Error
     */
    public function handle_callback($callback_data) {
        $this->log_transaction('callback_received', $callback_data);
        
        // Validate callback structure
        if (!isset($callback_data['Body']['stkCallback'])) {
            return new WP_Error('invalid_callback', 'Invalid callback structure');
        }
        
        $stk_callback = $callback_data['Body']['stkCallback'];
        
        // Extract callback data
        $result = [
            'merchant_request_id' => $stk_callback['MerchantRequestID'],
            'checkout_request_id' => $stk_callback['CheckoutRequestID'],
            'result_code' => $stk_callback['ResultCode'],
            'result_desc' => $stk_callback['ResultDesc']
        ];
        
        // Process successful payment
        if ($stk_callback['ResultCode'] === 0) {
            $result['status'] = 'completed';
            
            // Extract callback metadata
            if (isset($stk_callback['CallbackMetadata']['Item'])) {
                $metadata = [];
                foreach ($stk_callback['CallbackMetadata']['Item'] as $item) {
                    $metadata[$item['Name']] = $item['Value'] ?? '';
                }
                
                $result['amount'] = $metadata['Amount'] ?? 0;
                $result['mpesa_receipt_number'] = $metadata['MpesaReceiptNumber'] ?? '';
                $result['transaction_date'] = $metadata['TransactionDate'] ?? '';
                $result['phone_number'] = $metadata['PhoneNumber'] ?? '';
            }
            
            // Update transaction status
            $this->update_transaction_status($result['checkout_request_id'], 'completed', $result);
            
            // Trigger payment completed action
            do_action('sms_mpesa_payment_completed', $result);
            
        } else {
            // Handle failed payment
            $result['status'] = 'failed';
            $this->update_transaction_status($result['checkout_request_id'], 'failed', $result);
            
            // Trigger payment failed action
            do_action('sms_mpesa_payment_failed', $result);
        }
        
        $this->log_transaction('callback_processed', $result);
        
        return $result;
    }
    
    /**
     * Get transaction status
     *
     * @param string $transaction_id CheckoutRequestID
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
     * Get M-Pesa access token
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
        
        // Prepare authentication
        $credentials = base64_encode($this->config['consumer_key'] . ':' . $this->config['consumer_secret']);
        
        $headers = [
            'Authorization' => 'Basic ' . $credentials,
            'Content-Type' => 'application/json'
        ];
        
        // Make authentication request
        $response = $this->make_api_request($auth_url, [], 'GET', $headers);
        
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
            $error_message = $response['error_description'] ?? 'Authentication failed';
            return new WP_Error('auth_failed', $error_message);
        }
    }    

    /**
     * Make STK Push request
     *
     * @param array $request_data STK Push request data
     * @param string $access_token Access token
     * @return array|WP_Error
     */
    private function make_stk_push_request($request_data, $access_token) {
        $environment = $this->sandbox_mode ? 'sandbox' : 'production';
        $stk_url = $this->api_endpoints[$environment]['stk_push'];
        
        $headers = [
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type' => 'application/json'
        ];
        
        return $this->make_api_request($stk_url, $request_data, 'POST', $headers);
    }
    
    /**
     * Make STK Query request
     *
     * @param array $request_data STK Query request data
     * @param string $access_token Access token
     * @return array|WP_Error
     */
    private function make_stk_query_request($request_data, $access_token) {
        $environment = $this->sandbox_mode ? 'sandbox' : 'production';
        $query_url = $this->api_endpoints[$environment]['stk_query'];
        
        $headers = [
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type' => 'application/json'
        ];
        
        return $this->make_api_request($query_url, $request_data, 'POST', $headers);
    }
    
    /**
     * Normalize phone number for M-Pesa format
     *
     * @param string $phone Phone number
     * @return string
     */
    private function normalize_phone_for_mpesa($phone) {
        // Remove all non-numeric characters except +
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // Convert to 254XXXXXXXXX format (M-Pesa requirement)
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
     * Store pending transaction for tracking
     *
     * @param array $transaction_data Transaction data
     */
    private function store_pending_transaction($transaction_data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'sms_mpesa_transactions';
        
        $wpdb->insert(
            $table_name,
            [
                'checkout_request_id' => $transaction_data['checkout_request_id'],
                'merchant_request_id' => $transaction_data['merchant_request_id'],
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
     * @param string $checkout_request_id CheckoutRequestID
     * @param string $status New status
     * @param array $callback_data Callback data
     */
    private function update_transaction_status($checkout_request_id, $status, $callback_data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'sms_mpesa_transactions';
        
        $update_data = [
            'status' => $status,
            'updated_at' => current_time('mysql'),
            'callback_data' => json_encode($callback_data)
        ];
        
        if ($status === 'completed' && isset($callback_data['mpesa_receipt_number'])) {
            $update_data['mpesa_receipt_number'] = $callback_data['mpesa_receipt_number'];
            $update_data['transaction_date'] = $callback_data['transaction_date'];
        }
        
        $wpdb->update(
            $table_name,
            $update_data,
            ['checkout_request_id' => $checkout_request_id],
            ['%s', '%s', '%s', '%s', '%s'],
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
     * Handle timeout callback
     *
     * @param WP_REST_Request $request REST request
     * @return WP_REST_Response
     */
    public function handle_timeout_callback($request) {
        $callback_data = $request->get_json_params();
        
        if (empty($callback_data)) {
            $callback_data = $request->get_body_params();
        }
        
        $this->log_transaction('timeout_callback', $callback_data);
        
        // Mark transaction as timeout
        if (isset($callback_data['Body']['stkCallback']['CheckoutRequestID'])) {
            $checkout_request_id = $callback_data['Body']['stkCallback']['CheckoutRequestID'];
            $this->update_transaction_status($checkout_request_id, 'timeout', $callback_data);
            
            do_action('sms_mpesa_payment_timeout', $callback_data);
        }
        
        return new WP_REST_Response([
            'success' => true,
            'message' => 'Timeout callback processed'
        ], 200);
    }
    
    /**
     * Get user-friendly error message
     *
     * @param string $error_message Original error message
     * @return string
     */
    private function get_user_friendly_error($error_message) {
        $error_mappings = [
            'Invalid Access Token' => 'Payment service temporarily unavailable. Please try again.',
            'Bad Request - Invalid PhoneNumber' => 'Invalid phone number format. Please use format 07XXXXXXXX.',
            'Bad Request - Invalid Amount' => 'Invalid payment amount. Please check and try again.',
            'The service request is processed successfully.' => 'Payment request sent. Please check your phone.',
            'Request cancelled by user' => 'Payment was cancelled. You can try again anytime.',
            'The balance is insufficient for the transaction' => 'Insufficient M-Pesa balance. Please top up and try again.',
            'The initiator information is invalid' => 'Payment service configuration error. Please contact support.',
            'DS timeout user cannot be reached' => 'Unable to reach your phone. Please ensure it\'s on and try again.'
        ];
        
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
            'refund_payment' => false,
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
        return ['KES']; // M-Pesa only supports Kenyan Shilling
    }
    
    /**
     * Validate M-Pesa specific configuration
     *
     * @return bool|WP_Error
     */
    public function validate_gateway_config() {
        $required_fields = [
            'consumer_key' => 'Consumer Key',
            'consumer_secret' => 'Consumer Secret',
            'shortcode' => 'Business Shortcode',
            'passkey' => 'Passkey'
        ];
        
        foreach ($required_fields as $field => $label) {
            if (empty($this->config[$field])) {
                return new WP_Error('missing_config', "Missing required M-Pesa configuration: {$label}");
            }
        }
        
        // Validate shortcode format
        if (!preg_match('/^\d{5,7}$/', $this->config['shortcode'])) {
            return new WP_Error('invalid_shortcode', 'Invalid business shortcode format');
        }
        
        return true;
    }
    
    /**
     * Test M-Pesa connection
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
            'message' => 'M-Pesa connection successful',
            'environment' => $this->sandbox_mode ? 'sandbox' : 'production',
            'shortcode' => $this->config['shortcode']
        ];
    }
    
    /**
     * Get transaction by CheckoutRequestID
     *
     * @param string $checkout_request_id CheckoutRequestID
     * @return array|null
     */
    public function get_transaction($checkout_request_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'sms_mpesa_transactions';
        
        $transaction = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE checkout_request_id = %s",
                $checkout_request_id
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
     * Create M-Pesa transactions table
     */
    public static function create_transactions_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'sms_mpesa_transactions';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            checkout_request_id varchar(100) NOT NULL,
            merchant_request_id varchar(100) NOT NULL,
            phone_number varchar(20) NOT NULL,
            amount decimal(10,2) NOT NULL,
            reference varchar(100) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            mpesa_receipt_number varchar(50) DEFAULT NULL,
            transaction_date varchar(20) DEFAULT NULL,
            response_data longtext DEFAULT NULL,
            callback_data longtext DEFAULT NULL,
            created_at datetime NOT NULL,
            updated_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY checkout_request_id (checkout_request_id),
            KEY status (status),
            KEY phone_number (phone_number),
            KEY reference (reference)
        ) {$charset_collate};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}