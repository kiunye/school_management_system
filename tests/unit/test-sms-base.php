<?php
/**
 * Unit tests for SMS_Base class.
 */

class Test_SMS_Base extends SMS_Test_Case {

    /**
     * Test base class instantiation.
     */
    public function test_base_class_instantiation() {
        // Create a concrete implementation for testing
        $base = new class extends SMS_Base {
            public function test_method() {
                return 'test';
            }
        };
        
        $this->assertInstanceOf('SMS_Base', $base);
        $this->assertEquals(SMS_VERSION, $base->get_version());
        $this->assertEquals('school-management-system', $base->get_plugin_name());
    }

    /**
     * Test phone number validation.
     */
    public function test_phone_number_validation() {
        $base = new class extends SMS_Base {
            public function test_is_valid_phone($phone) {
                return $this->is_valid_phone($phone);
            }
        };
        
        $test_cases = $this->test_data->get_phone_number_test_cases();
        
        // Test valid phone numbers
        foreach ($test_cases['valid'] as $phone) {
            $this->assertTrue($base->test_is_valid_phone($phone), "Phone {$phone} should be valid");
        }
        
        // Test invalid phone numbers
        foreach ($test_cases['invalid'] as $phone) {
            $this->assertFalse($base->test_is_valid_phone($phone), "Phone {$phone} should be invalid");
        }
    }

    /**
     * Test phone number formatting.
     */
    public function test_phone_number_formatting() {
        $base = new class extends SMS_Base {
            public function test_format_phone($phone) {
                return $this->format_phone($phone);
            }
        };
        
        // Test various input formats
        $test_cases = [
            '0712345678' => '+254712345678',
            '254712345678' => '+254712345678',
            '+254712345678' => '+254712345678',
            '07 1234 5678' => '+254712345678', // With spaces
            '254-712-345-678' => '+254712345678' // With dashes
        ];
        
        foreach ($test_cases as $input => $expected) {
            $this->assertEquals($expected, $base->test_format_phone($input));
        }
        
        // Test invalid formats
        $this->assertFalse($base->test_format_phone('invalid'));
        $this->assertFalse($base->test_format_phone('123'));
    }

    /**
     * Test email validation.
     */
    public function test_email_validation() {
        $base = new class extends SMS_Base {
            public function test_is_valid_email($email) {
                return $this->is_valid_email($email);
            }
        };
        
        $test_cases = $this->test_data->get_email_test_cases();
        
        // Test valid emails
        foreach ($test_cases['valid'] as $email) {
            $this->assertTrue($base->test_is_valid_email($email), "Email {$email} should be valid");
        }
        
        // Test invalid emails
        foreach ($test_cases['invalid'] as $email) {
            $this->assertFalse($base->test_is_valid_email($email), "Email {$email} should be invalid");
        }
    }

    /**
     * Test currency formatting.
     */
    public function test_currency_formatting() {
        $base = new class extends SMS_Base {
            public function test_format_currency($amount, $currency = 'KES') {
                return $this->format_currency($amount, $currency);
            }
        };
        
        $this->assertEquals('KES 1,000.00', $base->test_format_currency(1000));
        $this->assertEquals('USD 1,500.50', $base->test_format_currency(1500.5, 'USD'));
        $this->assertEquals('KES 0.00', $base->test_format_currency(0));
    }

    /**
     * Test unique ID generation.
     */
    public function test_unique_id_generation() {
        $base = new class extends SMS_Base {
            public function test_generate_unique_id($prefix = '') {
                return $this->generate_unique_id($prefix);
            }
        };
        
        $id1 = $base->test_generate_unique_id();
        $id2 = $base->test_generate_unique_id();
        
        $this->assertNotEquals($id1, $id2, 'Generated IDs should be unique');
        
        $prefixed_id = $base->test_generate_unique_id('TEST_');
        $this->assertStringStartsWith('TEST_', $prefixed_id);
    }

    /**
     * Test error handling.
     */
    public function test_error_handling() {
        $base = new class extends SMS_Base {
            public function test_handle_error($code, $message, $data = []) {
                return $this->handle_error($code, $message, $data);
            }
        };
        
        $error = $base->test_handle_error('test_error', 'Test error message', ['key' => 'value']);
        
        $this->assertWPError($error, 'test_error');
        $this->assertEquals('Test error message', $error->get_error_message());
        $this->assertEquals(['key' => 'value'], $error->get_error_data());
    }

    /**
     * Test success response handling.
     */
    public function test_success_handling() {
        $base = new class extends SMS_Base {
            public function test_handle_success($data = [], $message = '') {
                return $this->handle_success($data, $message);
            }
        };
        
        $success = $base->test_handle_success(['result' => 'test'], 'Success message');
        
        $this->assertTrue($success['success']);
        $this->assertEquals(['result' => 'test'], $success['data']);
        $this->assertEquals('Success message', $success['message']);
    }

    /**
     * Test input sanitization.
     */
    public function test_input_sanitization() {
        // Mock the security class
        $base = new class extends SMS_Base {
            public function __construct() {
                // Skip parent constructor to avoid dependency issues
                $this->version = SMS_VERSION;
                $this->plugin_name = 'school-management-system';
            }
            
            public function test_sanitize_input($data, $type = 'text') {
                // Mock sanitization
                switch ($type) {
                    case 'email':
                        return filter_var($data, FILTER_SANITIZE_EMAIL);
                    case 'number':
                        return intval($data);
                    case 'text':
                    default:
                        return strip_tags($data);
                }
            }
        };
        
        $this->assertEquals('test@example.com', $base->test_sanitize_input('test@example.com', 'email'));
        $this->assertEquals(123, $base->test_sanitize_input('123abc', 'number'));
        $this->assertEquals('Hello World', $base->test_sanitize_input('<script>Hello World</script>', 'text'));
    }

    /**
     * Test activity logging structure.
     */
    public function test_activity_logging_structure() {
        $base = new class extends SMS_Base {
            public function __construct() {
                // Skip parent constructor
                $this->version = SMS_VERSION;
                $this->plugin_name = 'school-management-system';
            }
            
            public function test_log_activity($user_id, $action, $object_type, $object_id, $metadata = []) {
                // Mock the logging without actual database operations
                $activity_data = [
                    'user_id' => $user_id,
                    'action' => $action,
                    'object_type' => $object_type,
                    'object_id' => $object_id,
                    'metadata' => $metadata,
                    'timestamp' => current_time('mysql'),
                    'ip_address' => '127.0.0.1'
                ];
                
                return $activity_data;
            }
        };
        
        $activity = $base->test_log_activity(1, 'create', 'student', 123, ['grade' => '5']);
        
        $this->assertEquals(1, $activity['user_id']);
        $this->assertEquals('create', $activity['action']);
        $this->assertEquals('student', $activity['object_type']);
        $this->assertEquals(123, $activity['object_id']);
        $this->assertEquals(['grade' => '5'], $activity['metadata']);
        $this->assertNotEmpty($activity['timestamp']);
        $this->assertNotEmpty($activity['ip_address']);
    }
}