<?php
/**
 * Unit tests for SMS_Payment_Gateway_Manager class.
 */

class Test_SMS_Payment_Gateway_Manager extends SMS_Test_Case {

    /**
     * Payment gateway manager instance.
     */
    private $gateway_manager;

    /**
     * Mock gateway instances.
     */
    private $mock_gateways = [];

    /**
     * Set up test environment.
     */
    public function setUp(): void {
        parent::setUp();
        
        // Create mock base gateway class
        if (!class_exists('SMS_Payment_Gateway_Base')) {
            abstract class SMS_Payment_Gateway_Base {
                protected $gateway_id;
                protected $name;
                protected $enabled = true;
                protected $sandbox = true;
                
                public function __construct($gateway_id, $name) {
                    $this->gateway_id = $gateway_id;
                    $this->name = $name;
                }
                
                public function get_name() { return $this->name; }
                public function is_enabled() { return $this->enabled; }
                public function is_sandbox() { return $this->sandbox; }
                public function get_capabilities() { return ['payment', 'verification']; }
                
                abstract public function initialize_payment($amount, $phone_number, $reference, $additional_data = []);
                abstract public function verify_payment($transaction_id);
                abstract public function handle_callback($callback_data);
            }
        }
        
        $this->gateway_manager = SMS_Payment_Gateway_Manager::get_instance();
        $this->create_mock_gateways();
    }

    /**
     * Create mock payment gateways for testing.
     */
    private function create_mock_gateways() {
        // Mock M-Pesa gateway
        $this->mock_gateways['mpesa'] = new class('mpesa', 'M-Pesa') extends SMS_Payment_Gateway_Base {
            public function initialize_payment($amount, $phone_number, $reference, $additional_data = []) {
                if ($amount <= 0) {
                    return new WP_Error('invalid_amount', 'Amount must be greater than zero');
                }
                
                return [
                    'status' => 'success',
                    'transaction_id' => 'MPESA_' . wp_rand(100000, 999999),
                    'checkout_request_id' => 'ws_CO_' . wp_rand(100000, 999999),
                    'message' => 'Payment initiated successfully'
                ];
            }
            
            public function verify_payment($transaction_id) {
                return [
                    'status' => 'completed',
                    'transaction_id' => $transaction_id,
                    'amount' => 1000,
                    'receipt_number' => 'MPESA_RECEIPT_123'
                ];
            }
            
            public function handle_callback($callback_data) {
                return ['status' => 'processed'];
            }
        };
        
        // Mock Airtel Money gateway
        $this->mock_gateways['airtel_money'] = new class('airtel_money', 'Airtel Money') extends SMS_Payment_Gateway_Base {
            public function initialize_payment($amount, $phone_number, $reference, $additional_data = []) {
                return [
                    'status' => 'success',
                    'transaction_id' => 'AIRTEL_' . wp_rand(100000, 999999),
                    'message' => 'Payment initiated successfully'
                ];
            }
            
            public function verify_payment($transaction_id) {
                return [
                    'status' => 'completed',
                    'transaction_id' => $transaction_id,
                    'amount' => 1000
                ];
            }
            
            public function handle_callback($callback_data) {
                return ['status' => 'processed'];
            }
        };
        
        // Mock failing gateway for testing fallbacks
        $this->mock_gateways['failing_gateway'] = new class('failing_gateway', 'Failing Gateway') extends SMS_Payment_Gateway_Base {
            public function initialize_payment($amount, $phone_number, $reference, $additional_data = []) {
                return new WP_Error('gateway_error', 'Gateway temporarily unavailable');
            }
            
            public function verify_payment($transaction_id) {
                return new WP_Error('verification_failed', 'Cannot verify payment');
            }
            
            public function handle_callback($callback_data) {
                return new WP_Error('callback_error', 'Callback processing failed');
            }
        };
    }

    /**
     * Test gateway registration.
     */
    public function test_gateway_registration() {
        $result = $this->gateway_manager->register_gateway('mpesa', $this->mock_gateways['mpesa']);
        $this->assertTrue($result);
        
        // Test duplicate registration
        $result = $this->gateway_manager->register_gateway('mpesa', $this->mock_gateways['mpesa']);
        $this->assertFalse($result);
        
        // Verify gateway is registered
        $gateway = $this->gateway_manager->get_gateway('mpesa');
        $this->assertNotNull($gateway);
        $this->assertEquals('M-Pesa', $gateway->get_name());
    }

    /**
     * Test gateway unregistration.
     */
    public function test_gateway_unregistration() {
        // Register and then unregister
        $this->gateway_manager->register_gateway('mpesa', $this->mock_gateways['mpesa']);
        $result = $this->gateway_manager->unregister_gateway('mpesa');
        $this->assertTrue($result);
        
        // Verify gateway is unregistered
        $gateway = $this->gateway_manager->get_gateway('mpesa');
        $this->assertNull($gateway);
        
        // Test unregistering non-existent gateway
        $result = $this->gateway_manager->unregister_gateway('non_existent');
        $this->assertFalse($result);
    }

    /**
     * Test getting all gateways.
     */
    public function test_get_gateways() {
        // Register multiple gateways
        $this->gateway_manager->register_gateway('mpesa', $this->mock_gateways['mpesa']);
        $this->gateway_manager->register_gateway('airtel_money', $this->mock_gateways['airtel_money']);
        
        $all_gateways = $this->gateway_manager->get_gateways();
        $this->assertCount(2, $all_gateways);
        $this->assertArrayHasKey('mpesa', $all_gateways);
        $this->assertArrayHasKey('airtel_money', $all_gateways);
        
        // Test getting only enabled gateways
        $enabled_gateways = $this->gateway_manager->get_gateways(true);
        $this->assertCount(2, $enabled_gateways); // Both are enabled by default
    }

    /**
     * Test available payment methods.
     */
    public function test_get_available_payment_methods() {
        $this->gateway_manager->register_gateway('mpesa', $this->mock_gateways['mpesa']);
        $this->gateway_manager->register_gateway('airtel_money', $this->mock_gateways['airtel_money']);
        
        $methods = $this->gateway_manager->get_available_payment_methods();
        
        $this->assertCount(2, $methods);
        $this->assertArrayHasKey('mpesa', $methods);
        $this->assertArrayHasKey('airtel_money', $methods);
        
        // Verify method structure
        $this->assertEquals('mpesa', $methods['mpesa']['id']);
        $this->assertEquals('M-Pesa', $methods['mpesa']['name']);
        $this->assertTrue($methods['mpesa']['enabled']);
        $this->assertIsArray($methods['mpesa']['capabilities']);
    }

    /**
     * Test gateway selection logic.
     */
    public function test_gateway_selection() {
        $this->gateway_manager->register_gateway('mpesa', $this->mock_gateways['mpesa']);
        $this->gateway_manager->register_gateway('airtel_money', $this->mock_gateways['airtel_money']);
        
        // Test preferred gateway selection
        $selected = $this->gateway_manager->select_gateway('airtel_money', 1000);
        $this->assertEquals('airtel_money', $selected);
        
        // Test fallback to default when preferred is not available
        $selected = $this->gateway_manager->select_gateway('non_existent', 1000);
        $this->assertNotWPError($selected); // Should fallback to available gateway
    }

    /**
     * Test payment processing.
     */
    public function test_payment_processing() {
        $this->gateway_manager->register_gateway('mpesa', $this->mock_gateways['mpesa']);
        
        $result = $this->gateway_manager->process_payment(
            'mpesa',
            1000,
            '+254712345678',
            'TEST_REF_123'
        );
        
        $this->assertNotWPError($result);
        $this->assertEquals('success', $result['status']);
        $this->assertArrayHasKey('transaction_id', $result);
        $this->assertStringStartsWith('MPESA_', $result['transaction_id']);
    }

    /**
     * Test payment processing with invalid data.
     */
    public function test_payment_processing_validation() {
        $this->gateway_manager->register_gateway('mpesa', $this->mock_gateways['mpesa']);
        
        // Test with invalid amount
        $result = $this->gateway_manager->process_payment('mpesa', 0, '+254712345678', 'TEST_REF');
        $this->assertWPError($result, 'invalid_amount');
        
        // Test with missing phone number
        $result = $this->gateway_manager->process_payment('mpesa', 1000, '', 'TEST_REF');
        $this->assertWPError($result, 'missing_phone');
        
        // Test with missing reference
        $result = $this->gateway_manager->process_payment('mpesa', 1000, '+254712345678', '');
        $this->assertWPError($result, 'missing_reference');
        
        // Test with non-existent gateway
        $result = $this->gateway_manager->process_payment('non_existent', 1000, '+254712345678', 'TEST_REF');
        $this->assertWPError($result, 'gateway_not_found');
    }

    /**
     * Test payment verification.
     */
    public function test_payment_verification() {
        $this->gateway_manager->register_gateway('mpesa', $this->mock_gateways['mpesa']);
        
        $result = $this->gateway_manager->verify_payment('mpesa', 'TEST_TRANSACTION_123');
        
        $this->assertNotWPError($result);
        $this->assertEquals('completed', $result['status']);
        $this->assertEquals('TEST_TRANSACTION_123', $result['transaction_id']);
    }

    /**
     * Test fallback payment processing.
     */
    public function test_fallback_payment_processing() {
        // Register failing gateway first, then working gateway
        $this->gateway_manager->register_gateway('failing_gateway', $this->mock_gateways['failing_gateway']);
        $this->gateway_manager->register_gateway('mpesa', $this->mock_gateways['mpesa']);
        
        // Set fallback order
        update_option('sms_payment_gateway_fallback_order', ['failing_gateway', 'mpesa']);
        
        $result = $this->gateway_manager->process_payment(
            'failing_gateway',
            1000,
            '+254712345678',
            'TEST_REF_123',
            ['enable_fallback' => true]
        );
        
        // Should fail with the failing gateway since fallback is handled internally
        $this->assertWPError($result);
    }

    /**
     * Test gateway availability checking.
     */
    public function test_gateway_availability() {
        $this->gateway_manager->register_gateway('mpesa', $this->mock_gateways['mpesa']);
        
        // Use reflection to test private method
        $reflection = new ReflectionClass($this->gateway_manager);
        $method = $reflection->getMethod('is_gateway_available');
        $method->setAccessible(true);
        
        $available = $method->invokeArgs($this->gateway_manager, ['mpesa', 1000, []]);
        $this->assertTrue($available);
        
        $available = $method->invokeArgs($this->gateway_manager, ['non_existent', 1000, []]);
        $this->assertFalse($available);
    }

    /**
     * Test gateway statistics.
     */
    public function test_gateway_statistics() {
        $this->gateway_manager->register_gateway('mpesa', $this->mock_gateways['mpesa']);
        $this->gateway_manager->register_gateway('airtel_money', $this->mock_gateways['airtel_money']);
        
        $stats = $this->gateway_manager->get_gateway_statistics();
        
        $this->assertCount(2, $stats);
        $this->assertArrayHasKey('mpesa', $stats);
        $this->assertArrayHasKey('airtel_money', $stats);
        
        // Verify stat structure
        $this->assertEquals('M-Pesa', $stats['mpesa']['name']);
        $this->assertTrue($stats['mpesa']['enabled']);
        $this->assertTrue($stats['mpesa']['sandbox']);
    }

    /**
     * Test payment data validation edge cases.
     */
    public function test_payment_validation_edge_cases() {
        $this->gateway_manager->register_gateway('mpesa', $this->mock_gateways['mpesa']);
        
        // Use reflection to test private validation method
        $reflection = new ReflectionClass($this->gateway_manager);
        $method = $reflection->getMethod('validate_payment_data');
        $method->setAccessible(true);
        
        // Test negative amount
        $result = $method->invokeArgs($this->gateway_manager, [-100, '+254712345678', 'REF']);
        $this->assertWPError($result, 'invalid_amount');
        
        // Test zero amount
        $result = $method->invokeArgs($this->gateway_manager, [0, '+254712345678', 'REF']);
        $this->assertWPError($result, 'invalid_amount');
        
        // Test valid data
        $result = $method->invokeArgs($this->gateway_manager, [1000, '+254712345678', 'REF']);
        $this->assertTrue($result);
    }

    /**
     * Test singleton pattern.
     */
    public function test_singleton_pattern() {
        $instance1 = SMS_Payment_Gateway_Manager::get_instance();
        $instance2 = SMS_Payment_Gateway_Manager::get_instance();
        
        $this->assertSame($instance1, $instance2);
    }

    /**
     * Test gateway hooks are triggered.
     */
    public function test_gateway_hooks() {
        $hooks_fired = [];
        
        add_action('sms_payment_gateway_registered', function($gateway_id, $gateway) use (&$hooks_fired) {
            $hooks_fired[] = 'registered_' . $gateway_id;
        }, 10, 2);
        
        add_action('sms_payment_gateway_unregistered', function($gateway_id) use (&$hooks_fired) {
            $hooks_fired[] = 'unregistered_' . $gateway_id;
        });
        
        // Register and unregister gateway
        $this->gateway_manager->register_gateway('test_gateway', $this->mock_gateways['mpesa']);
        $this->gateway_manager->unregister_gateway('test_gateway');
        
        $this->assertContains('registered_test_gateway', $hooks_fired);
        $this->assertContains('unregistered_test_gateway', $hooks_fired);
    }
}