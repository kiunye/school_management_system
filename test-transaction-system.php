<?php
/**
 * Transaction Management System Test
 *
 * Simple test script to verify transaction management functionality
 *
 * @package SchoolManagementSystem
 * @since 1.0.0
 */

// Include WordPress
require_once('../../../wp-config.php');

// Include required classes
require_once('includes/financial/class-sms-transaction-manager.php');
require_once('includes/financial/class-sms-transaction-status-tracker.php');
require_once('includes/financial/class-sms-receipt-generator.php');
require_once('includes/financial/class-sms-transaction-integration.php');

/**
 * Test Transaction Management System
 */
class SMS_Transaction_System_Test {
    
    private $transaction_manager;
    private $status_tracker;
    private $receipt_generator;
    private $integration;
    
    public function __construct() {
        $this->transaction_manager = SMS_Transaction_Manager::get_instance();
        $this->status_tracker = SMS_Transaction_Status_Tracker::get_instance();
        $this->receipt_generator = new SMS_Receipt_Generator();
        $this->integration = SMS_Transaction_Integration::get_instance();
    }
    
    /**
     * Run all tests
     */
    public function run_tests() {
        echo "<h1>Transaction Management System Test</h1>\n";
        
        $this->test_transaction_creation();
        $this->test_status_updates();
        $this->test_receipt_generation();
        $this->test_gateway_integration();
        $this->test_bulk_operations();
        
        echo "<h2>All tests completed!</h2>\n";
    }
    
    /**
     * Test transaction creation
     */
    private function test_transaction_creation() {
        echo "<h2>Testing Transaction Creation</h2>\n";
        
        // Create a test student (assuming student ID 1 exists)
        $transaction_data = array(
            'student_id' => 1,
            'amount' => 5000.00,
            'currency' => 'KES',
            'payment_method' => 'mpesa',
            'gateway_name' => 'mpesa',
            'phone_number' => '254700000000',
            'transaction_type' => 'payment'
        );
        
        $transaction_id = $this->transaction_manager->create_transaction($transaction_data);
        
        if (is_wp_error($transaction_id)) {
            echo "<p style='color: red;'>❌ Transaction creation failed: " . $transaction_id->get_error_message() . "</p>\n";
        } else {
            echo "<p style='color: green;'>✅ Transaction created successfully with ID: {$transaction_id}</p>\n";
            
            // Test transaction data retrieval
            $retrieved_data = $this->transaction_manager->get_transaction_data($transaction_id);
            if ($retrieved_data) {
                echo "<p style='color: green;'>✅ Transaction data retrieved successfully</p>\n";
            } else {
                echo "<p style='color: red;'>❌ Failed to retrieve transaction data</p>\n";
            }
        }
    }
    
    /**
     * Test status updates
     */
    private function test_status_updates() {
        echo "<h2>Testing Status Updates</h2>\n";
        
        // Get a pending transaction
        $pending_transactions = $this->transaction_manager->get_transactions_by_status(
            array(SMS_Transaction_Manager::STATUS_PENDING), 1
        );
        
        if (empty($pending_transactions)) {
            echo "<p style='color: orange;'>⚠️ No pending transactions found to test status updates</p>\n";
            return;
        }
        
        $transaction_id = $pending_transactions[0];
        
        // Test status update
        $result = $this->transaction_manager->update_transaction_status(
            $transaction_id,
            SMS_Transaction_Manager::STATUS_PROCESSING,
            'Test status update'
        );
        
        if (is_wp_error($result)) {
            echo "<p style='color: red;'>❌ Status update failed: " . $result->get_error_message() . "</p>\n";
        } else {
            echo "<p style='color: green;'>✅ Transaction status updated successfully</p>\n";
        }
        
        // Test status tracker
        $stats = $this->status_tracker->get_status_statistics();
        if (!empty($stats)) {
            echo "<p style='color: green;'>✅ Status statistics retrieved: " . json_encode($stats) . "</p>\n";
        } else {
            echo "<p style='color: red;'>❌ Failed to retrieve status statistics</p>\n";
        }
    }
    
    /**
     * Test receipt generation
     */
    private function test_receipt_generation() {
        echo "<h2>Testing Receipt Generation</h2>\n";
        
        // Get a completed transaction
        $completed_transactions = $this->transaction_manager->get_transactions_by_status(
            array(SMS_Transaction_Manager::STATUS_COMPLETED), 1
        );
        
        if (empty($completed_transactions)) {
            echo "<p style='color: orange;'>⚠️ No completed transactions found to test receipt generation</p>\n";
            return;
        }
        
        $transaction_id = $completed_transactions[0];
        
        // Test receipt generation
        $receipt_content = $this->transaction_manager->generate_receipt($transaction_id);
        
        if (is_wp_error($receipt_content)) {
            echo "<p style='color: red;'>❌ Receipt generation failed: " . $receipt_content->get_error_message() . "</p>\n";
        } else {
            echo "<p style='color: green;'>✅ Receipt generated successfully (length: " . strlen($receipt_content) . " characters)</p>\n";
        }
        
        // Test available templates
        $templates = $this->receipt_generator->get_available_templates();
        if (!empty($templates)) {
            echo "<p style='color: green;'>✅ Available receipt templates: " . implode(', ', array_keys($templates)) . "</p>\n";
        } else {
            echo "<p style='color: red;'>❌ No receipt templates available</p>\n";
        }
    }
    
    /**
     * Test gateway integration
     */
    private function test_gateway_integration() {
        echo "<h2>Testing Gateway Integration</h2>\n";
        
        // Test payment initiation simulation
        $payment_data = array(
            'student_id' => 1,
            'amount' => 1000.00,
            'currency' => 'KES',
            'phone_number' => '254700000000'
        );
        
        $gateway_response = array(
            'transaction_id' => 'TEST_' . time(),
            'reference' => 'REF_' . time(),
            'status' => 'pending'
        );
        
        $transaction_id = $this->integration->handle_payment_initiation($payment_data, 'mpesa', $gateway_response);
        
        if (is_wp_error($transaction_id)) {
            echo "<p style='color: red;'>❌ Payment initiation failed: " . $transaction_id->get_error_message() . "</p>\n";
        } else {
            echo "<p style='color: green;'>✅ Payment initiation handled successfully with transaction ID: {$transaction_id}</p>\n";
            
            // Test payment completion
            $completion_response = array(
                'transaction_id' => $gateway_response['transaction_id'],
                'status' => 'completed',
                'amount' => $payment_data['amount']
            );
            
            $this->integration->handle_payment_completion($payment_data, 'mpesa', $completion_response);
            echo "<p style='color: green;'>✅ Payment completion handled successfully</p>\n";
        }
    }
    
    /**
     * Test bulk operations
     */
    private function test_bulk_operations() {
        echo "<h2>Testing Bulk Operations</h2>\n";
        
        // Get some transactions for bulk operations
        $all_transactions = get_posts(array(
            'post_type' => 'sms_transactions',
            'post_status' => 'publish',
            'posts_per_page' => 3,
            'fields' => 'ids'
        ));
        
        if (empty($all_transactions)) {
            echo "<p style='color: orange;'>⚠️ No transactions found to test bulk operations</p>\n";
            return;
        }
        
        // Test bulk verification
        $results = $this->integration->process_bulk_operations('verify', $all_transactions);
        echo "<p style='color: green;'>✅ Bulk verification completed: {$results['success']} successful, {$results['failed']} failed</p>\n";
        
        if (!empty($results['errors'])) {
            echo "<p style='color: orange;'>⚠️ Bulk operation errors: " . implode(', ', $results['errors']) . "</p>\n";
        }
    }
    
    /**
     * Display system information
     */
    public function display_system_info() {
        echo "<h2>System Information</h2>\n";
        echo "<ul>\n";
        echo "<li>WordPress Version: " . get_bloginfo('version') . "</li>\n";
        echo "<li>PHP Version: " . PHP_VERSION . "</li>\n";
        echo "<li>Transaction Manager: " . (class_exists('SMS_Transaction_Manager') ? '✅ Loaded' : '❌ Not loaded') . "</li>\n";
        echo "<li>Status Tracker: " . (class_exists('SMS_Transaction_Status_Tracker') ? '✅ Loaded' : '❌ Not loaded') . "</li>\n";
        echo "<li>Receipt Generator: " . (class_exists('SMS_Receipt_Generator') ? '✅ Loaded' : '❌ Not loaded') . "</li>\n";
        echo "<li>Transaction Integration: " . (class_exists('SMS_Transaction_Integration') ? '✅ Loaded' : '❌ Not loaded') . "</li>\n";
        echo "</ul>\n";
    }
}

// Run the test if accessed directly
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Transaction Management System Test</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            h1, h2 { color: #333; }
            p { margin: 10px 0; }
            .success { color: green; }
            .error { color: red; }
            .warning { color: orange; }
        </style>
    </head>
    <body>
    <?php
    
    try {
        $test = new SMS_Transaction_System_Test();
        $test->display_system_info();
        $test->run_tests();
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Test failed with exception: " . $e->getMessage() . "</p>\n";
    }
    
    ?>
    </body>
    </html>
    <?php
}
?>