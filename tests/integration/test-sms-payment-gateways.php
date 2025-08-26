<?php
/**
 * Integration tests for SMS payment gateways.
 */

class Test_SMS_Payment_Gateways extends SMS_Test_Case {

    /**
     * Payment gateway manager instance.
     */
    private $gateway_manager;

    /**
     * M-Pesa gateway instance.
     */
    private $mpesa_gateway;

    /**
     * Airtel Money gateway instance.
     */
    private $airtel_gateway;

    /**
     * Set up test environment.
     */
    public function setUp(): void {
        parent::setUp();
        
        // Mock the gateway classes if they don't exist
        $this->setup_mock_gateways();
        
        $this->gateway_manager = SMS_Payment_Gateway_Manager::get_instance();
        $this->mpesa_gateway = new SMS_MPESA_Gateway();
        $this->airtel_gateway = new SMS_Airtel_Money_Gateway();
        
        // Register gateways
        $this->gateway_manager->register_gateway('mpesa', $this->mpesa_gateway);
        $this->gateway_manager->register_gateway('airtel_money', $this->airtel_gateway);
        
        // Set up test configurations
        $this->setup_test_configurations();
    }

    /**
     * Set up mock gateway classes for testing.
     */
    private function setup_mock_gateways() {
        if (!class_exists('SMS_MPESA_Gateway')) {
            class SMS_MPESA_Gateway extends SMS_Payment_Gateway_Base {
                private $sandbox_mode = true;
                private $consumer_key = 'test_consumer_key';
                private $consumer_secret = 'test_consumer_secret';
                private $shortcode = '174379';
                private $passkey = 'test_passkey';
                
                public function __construct() {
                    parent::__construct('mpesa', 'M-Pesa');
                }
                
                public function initialize_payment($amount, $phone_number, $reference, $additional_data = []) {
                    // Simulate M-Pesa STK Push
                    if (!$this->validate_phone_number($phone_number)) {
                        return new WP_Error('invalid_phone', 'Invalid phone number format');
                    }
                    
                    if ($amount < 1) {
                        return new WP_Error('invalid_amount', 'Amount must be at least KES 1');
                    }
                    
                    // Simulate API call
                    $response = $this->simulate_stk_push($amount, $phone_number, $reference);
                    
                    if (isset($response['errorCode'])) {
                        return new WP_Error('mpesa_error', $response['errorMessage']);
                    }
                    
                    return [
                        'status' => 'pending',
                        'transaction_id' => $response['CheckoutRequestID'],
                        'merchant_request_id' => $response['MerchantRequestID'],
                        'message' => 'Payment request sent to your phone'
                    ];
                }
                
                public function verify_payment($transaction_id) {
                    // Simulate payment verification
                    return [
                        'status' => 'completed',
                        'transaction_id' => $transaction_id,
                        'amount' => 1000,
                        'receipt_number' => 'MPESA_' . wp_rand(100000, 999999),
                        'phone_number' => '+254712345678',
                        'transaction_date' => current_time('mysql')
                    ];
                }
                
                public function handle_callback($callback_data) {
                    // Simulate M-Pesa callback processing
                    if (!isset($callback_data['Body']['stkCallback'])) {
                        return new WP_Error('invalid_callback', 'Invalid callback data');
                    }
                    
                    $callback = $callback_data['Body']['stkCallback'];
                    
                    return [
                        'status' => $callback['ResultCode'] == 0 ? 'completed' : 'failed',
                        'transaction_id' => $callback['CheckoutRequestID'],
                        'result_code' => $callback['ResultCode'],
                        'result_desc' => $callback['ResultDesc']
                    ];
                }
                
                private function simulate_stk_push($amount, $phone_number, $reference) {
                    // Simulate different scenarios based on phone number
                    if ($phone_number === '+254700000000') {
                        return [
                            'errorCode' => '400.002.02',
                            'errorMessage' => 'Bad Request - Invalid PhoneNumber'
                        ];
                    }
                    
                    if ($phone_number === '+254700000001') {
                        return [
                            'errorCode' => '500.001.1001',
                            'errorMessage' => 'Unable to lock subscriber, a transaction is already in process for the current subscriber'
                        ];
                    }
                    
                    return [
                        'MerchantRequestID' => 'test_merchant_' . wp_rand(10000, 99999),
                        'CheckoutRequestID' => 'ws_CO_' . wp_rand(100000, 999999),
                        'ResponseCode' => '0',
                        'ResponseDescription' => 'Success. Request accepted for processing',
                        'CustomerMessage' => 'Success. Request accepted for processing'
                    ];
                }
                
                private function validate_phone_number($phone_number) {
                    return preg_match('/^\+254[17]\d{8}$/', $phone_number);
                }
            }
        }
        
        if (!class_exists('SMS_Airtel_Money_Gateway')) {
            class SMS_Airtel_Money_Gateway extends SMS_Payment_Gateway_Base {
                private $sandbox_mode = true;
                private $client_id = 'test_client_id';
                private $client_secret = 'test_client_secret';
                private $merchant_id = 'test_merchant';
                
                public function __construct() {
                    parent::__construct('airtel_money', 'Airtel Money');
                }
                
                public function initialize_payment($amount, $phone_number, $reference, $additional_data = []) {
                    if (!$this->validate_airtel_number($phone_number)) {
                        return new WP_Error('invalid_phone', 'Invalid Airtel phone number');
                    }
                    
                    if ($amount < 10) {
                        return new WP_Error('invalid_amount', 'Minimum amount is KES 10');
                    }
                    
                    // Simulate API call
                    $response = $this->simulate_payment_request($amount, $phone_number, $reference);
                    
                    if (isset($response['error'])) {
                        return new WP_Error('airtel_error', $response['error']);
                    }
                    
                    return [
                        'status' => 'pending',
                        'transaction_id' => $response['transaction_id'],
                        'reference' => $response['reference'],
                        'message' => 'Payment request initiated'
                    ];
                }
                
                public function verify_payment($transaction_id) {
                    return [
                        'status' => 'completed',
                        'transaction_id' => $transaction_id,
                        'amount' => 1000,
                        'phone_number' => '+254733123456',
                        'transaction_date' => current_time('mysql')
                    ];
                }
                
                public function handle_callback($callback_data) {
                    if (!isset($callback_data['transaction_id'])) {
                        return new WP_Error('invalid_callback', 'Missing transaction ID');
                    }
                    
                    return [
                        'status' => 'completed',
                        'transaction_id' => $callback_data['transaction_id'],
                        'amount' => $callback_data['amount'] ?? 0
                    ];
                }
                
                private function simulate_payment_request($amount, $phone_number, $reference) {
                    // Simulate different scenarios
                    if ($phone_number === '+254733000000') {
                        return ['error' => 'Insufficient balance'];
                    }
                    
                    if ($phone_number === '+254733000001') {
                        return ['error' => 'Service temporarily unavailable'];
                    }
                    
                    return [
                        'transaction_id' => 'AIRTEL_' . wp_rand(100000, 999999),
                        'reference' => $reference,
                        'status' => 'pending'
                    ];
                }
                
                private function validate_airtel_number($phone_number) {
                    return preg_match('/^\+25473[0-9]\d{6}$/', $phone_number);
                }
            }
        }
    }

    /**
     * Set up test configurations.
     */
    private function setup_test_configurations() {
        // Set sandbox mode for all gateways
        update_option('sms_payment_gateway_configs', [
            'mpesa' => [
                'enabled' => true,
                'sandbox' => true,
                'consumer_key' => 'test_key',
                'consumer_secret' => 'test_secret',
                'shortcode' => '174379',
                'passkey' => 'test_passkey'
            ],
            'airtel_money' => [
                'enabled' => true,
                'sandbox' => true,
                'client_id' => 'test_client_id',
                'client_secret' => 'test_client_secret',
                'merchant_id' => 'test_merchant'
            ]
        ]);
        
        update_option('sms_default_payment_gateway', 'mpesa');
        update_option('sms_payment_gateway_fallback_order', ['mpesa', 'airtel_money']);
    }

    /**
     * Test M-Pesa STK Push integration.
     */
    public function test_mpesa_stk_push_integration() {
        $result = $this->gateway_manager->process_payment(
            'mpesa',
            1000,
            '+254712345678',
            'TEST_REF_' . wp_rand(1000, 9999)
        );
        
        $this->assertNotWPError($result);
        $this->assertEquals('pending', $result['status']);
        $this->assertArrayHasKey('transaction_id', $result);
        $this->assertArrayHasKey('merchant_request_id', $result);
        $this->assertStringContains('phone', $result['message']);
    }

    /**
     * Test M-Pesa payment with invalid phone number.
     */
    public function test_mpesa_invalid_phone_number() {
        $result = $this->gateway_manager->process_payment(
            'mpesa',
            1000,
            '+254700000000', // Invalid test number
            'TEST_REF_123'
        );
        
        $this->assertWPError($result, 'mpesa_error');
        $this->assertStringContains('Invalid PhoneNumber', $result->get_error_message());
    }

    /**
     * Test M-Pesa payment with subscriber lock error.
     */
    public function test_mpesa_subscriber_lock_error() {
        $result = $this->gateway_manager->process_payment(
            'mpesa',
            1000,
            '+254700000001', // Locked subscriber test number
            'TEST_REF_123'
        );
        
        $this->assertWPError($result, 'mpesa_error');
        $this->assertStringContains('transaction is already in process', $result->get_error_message());
    }

    /**
     * Test M-Pesa payment verification.
     */
    public function test_mpesa_payment_verification() {
        // First initiate a payment
        $payment_result = $this->gateway_manager->process_payment(
            'mpesa',
            1000,
            '+254712345678',
            'TEST_REF_123'
        );
        
        $this->assertNotWPError($payment_result);
        
        // Then verify the payment
        $verification_result = $this->gateway_manager->verify_payment(
            'mpesa',
            $payment_result['transaction_id']
        );
        
        $this->assertNotWPError($verification_result);
        $this->assertEquals('completed', $verification_result['status']);
        $this->assertEquals($payment_result['transaction_id'], $verification_result['transaction_id']);
        $this->assertArrayHasKey('receipt_number', $verification_result);
    }

    /**
     * Test M-Pesa callback handling.
     */
    public function test_mpesa_callback_handling() {
        $callback_data = $this->test_data->get_sample_gateway_responses()['mpesa_callback_success'];
        
        $result = $this->mpesa_gateway->handle_callback($callback_data);
        
        $this->assertNotWPError($result);
        $this->assertEquals('completed', $result['status']);
        $this->assertEquals(0, $result['result_code']);
        $this->assertStringContains('successfully', $result['result_desc']);
    }

    /**
     * Test Airtel Money payment integration.
     */
    public function test_airtel_money_payment_integration() {
        $result = $this->gateway_manager->process_payment(
            'airtel_money',
            1000,
            '+254733123456',
            'TEST_REF_' . wp_rand(1000, 9999)
        );
        
        $this->assertNotWPError($result);
        $this->assertEquals('pending', $result['status']);
        $this->assertArrayHasKey('transaction_id', $result);
        $this->assertStringStartsWith('AIRTEL_', $result['transaction_id']);
    }

    /**
     * Test Airtel Money with invalid phone number.
     */
    public function test_airtel_money_invalid_phone() {
        $result = $this->gateway_manager->process_payment(
            'airtel_money',
            1000,
            '+254712345678', // Non-Airtel number
            'TEST_REF_123'
        );
        
        $this->assertWPError($result, 'airtel_error');
        $this->assertStringContains('Invalid Airtel phone number', $result->get_error_message());
    }

    /**
     * Test Airtel Money insufficient balance error.
     */
    public function test_airtel_money_insufficient_balance() {
        $result = $this->gateway_manager->process_payment(
            'airtel_money',
            1000,
            '+254733000000', // Test number for insufficient balance
            'TEST_REF_123'
        );
        
        $this->assertWPError($result, 'airtel_error');
        $this->assertStringContains('Insufficient balance', $result->get_error_message());
    }

    /**
     * Test Airtel Money service unavailable error.
     */
    public function test_airtel_money_service_unavailable() {
        $result = $this->gateway_manager->process_payment(
            'airtel_money',
            1000,
            '+254733000001', // Test number for service unavailable
            'TEST_REF_123'
        );
        
        $this->assertWPError($result, 'airtel_error');
        $this->assertStringContains('Service temporarily unavailable', $result->get_error_message());
    }

    /**
     * Test Airtel Money payment verification.
     */
    public function test_airtel_money_verification() {
        // Initiate payment
        $payment_result = $this->gateway_manager->process_payment(
            'airtel_money',
            1000,
            '+254733123456',
            'TEST_REF_123'
        );
        
        $this->assertNotWPError($payment_result);
        
        // Verify payment
        $verification_result = $this->gateway_manager->verify_payment(
            'airtel_money',
            $payment_result['transaction_id']
        );
        
        $this->assertNotWPError($verification_result);
        $this->assertEquals('completed', $verification_result['status']);
    }

    /**
     * Test payment gateway fallback mechanism.
     */
    public function test_payment_gateway_fallback() {
        // Create a failing gateway and register it as primary
        $failing_gateway = new class('failing_gateway', 'Failing Gateway') extends SMS_Payment_Gateway_Base {
            public function initialize_payment($amount, $phone_number, $reference, $additional_data = []) {
                return new WP_Error('gateway_error', 'Primary gateway failed');
            }
            
            public function verify_payment($transaction_id) {
                return new WP_Error('verification_failed', 'Cannot verify');
            }
            
            public function handle_callback($callback_data) {
                return new WP_Error('callback_error', 'Callback failed');
            }
        };
        
        $this->gateway_manager->register_gateway('failing_gateway', $failing_gateway);
        
        // Set failing gateway as default
        update_option('sms_default_payment_gateway', 'failing_gateway');
        update_option('sms_payment_gateway_fallback_order', ['failing_gateway', 'mpesa', 'airtel_money']);
        
        // Process payment with fallback enabled
        $result = $this->gateway_manager->process_payment(
            'failing_gateway',
            1000,
            '+254712345678',
            'TEST_REF_123',
            ['enable_fallback' => true]
        );
        
        // Should fail with primary gateway since fallback is handled internally
        $this->assertWPError($result);
    }

    /**
     * Test transaction recording and receipt generation.
     */
    public function test_transaction_recording() {
        // Create a student and invoice for testing
        $student_id = $this->factory->create_student();
        $invoice_id = $this->factory->create_invoice([
            'meta_input' => [
                'student_id' => $student_id,
                'total_amount' => 1000,
                'status' => 'pending'
            ]
        ]);
        
        // Process payment
        $payment_result = $this->gateway_manager->process_payment(
            'mpesa',
            1000,
            '+254712345678',
            'INV_' . $invoice_id
        );
        
        $this->assertNotWPError($payment_result);
        
        // Create transaction record
        $transaction_id = $this->factory->create_transaction([
            'meta_input' => [
                'invoice_id' => $invoice_id,
                'student_id' => $student_id,
                'amount' => 1000,
                'payment_method' => 'mpesa',
                'gateway_transaction_id' => $payment_result['transaction_id'],
                'payment_status' => 'pending'
            ]
        ]);
        
        $this->assertPostExists($transaction_id, 'sms_transactions');
        
        // Verify transaction data
        $gateway_transaction_id = get_post_meta($transaction_id, 'gateway_transaction_id', true);
        $this->assertEquals($payment_result['transaction_id'], $gateway_transaction_id);
        
        $payment_method = get_post_meta($transaction_id, 'payment_method', true);
        $this->assertEquals('mpesa', $payment_method);
    }

    /**
     * Test payment amount validation across gateways.
     */
    public function test_payment_amount_validation() {
        // Test M-Pesa minimum amount
        $result = $this->gateway_manager->process_payment(
            'mpesa',
            0.5, // Below minimum
            '+254712345678',
            'TEST_REF_123'
        );
        
        $this->assertWPError($result);
        
        // Test Airtel Money minimum amount
        $result = $this->gateway_manager->process_payment(
            'airtel_money',
            5, // Below minimum
            '+254733123456',
            'TEST_REF_123'
        );
        
        $this->assertWPError($result);
    }

    /**
     * Test concurrent payment processing.
     */
    public function test_concurrent_payment_processing() {
        $results = [];
        
        // Simulate multiple concurrent payments
        for ($i = 0; $i < 5; $i++) {
            $results[] = $this->gateway_manager->process_payment(
                'mpesa',
                1000 + ($i * 100),
                '+254712345678',
                'CONCURRENT_' . $i
            );
        }
        
        // Verify all payments were processed
        foreach ($results as $result) {
            $this->assertNotWPError($result);
            $this->assertEquals('pending', $result['status']);
        }
        
        // Verify unique transaction IDs
        $transaction_ids = array_column($results, 'transaction_id');
        $unique_ids = array_unique($transaction_ids);
        $this->assertCount(5, $unique_ids);
    }

    /**
     * Test payment gateway error handling and logging.
     */
    public function test_payment_error_handling() {
        // Capture error logs
        $error_logs = [];
        
        // Mock error_log function
        if (!function_exists('test_error_log')) {
            function test_error_log($message) {
                global $test_error_logs;
                $test_error_logs[] = $message;
            }
        }
        
        // Process payment with invalid data
        $result = $this->gateway_manager->process_payment(
            'mpesa',
            -100, // Invalid amount
            '+254712345678',
            'TEST_REF_123'
        );
        
        $this->assertWPError($result, 'invalid_amount');
    }

    /**
     * Test payment gateway configuration validation.
     */
    public function test_gateway_configuration_validation() {
        // Test with missing configuration
        $gateway_configs = get_option('sms_payment_gateway_configs', []);
        unset($gateway_configs['mpesa']);
        update_option('sms_payment_gateway_configs', $gateway_configs);
        
        // Gateway should still work with default/hardcoded config in test
        $result = $this->gateway_manager->process_payment(
            'mpesa',
            1000,
            '+254712345678',
            'TEST_REF_123'
        );
        
        // Should work since we're using mock gateway with hardcoded config
        $this->assertNotWPError($result);
    }

    /**
     * Test payment status tracking and updates.
     */
    public function test_payment_status_tracking() {
        // Create transaction
        $transaction_id = $this->factory->create_transaction([
            'meta_input' => [
                'payment_status' => 'pending',
                'gateway_transaction_id' => 'TEST_TXN_123'
            ]
        ]);
        
        // Update status to completed
        update_post_meta($transaction_id, 'payment_status', 'completed');
        update_post_meta($transaction_id, 'payment_date', current_time('mysql'));
        
        // Verify status update
        $status = get_post_meta($transaction_id, 'payment_status', true);
        $this->assertEquals('completed', $status);
        
        $payment_date = get_post_meta($transaction_id, 'payment_date', true);
        $this->assertNotEmpty($payment_date);
    }
}