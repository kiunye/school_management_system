<?php
/**
 * M-Pesa Gateway Test Script
 *
 * Simple test script to verify M-Pesa gateway functionality
 * This file should be removed in production
 *
 * @package SchoolManagementSystem
 * @subpackage Tests
 * @since 1.0.0
 */

// This is a development test file - remove in production
if (!defined('WP_DEBUG') || !WP_DEBUG) {
    wp_die('This test file is only available in debug mode.');
}

// Load WordPress
require_once('../../../wp-load.php');

// Check if user has admin privileges
if (!current_user_can('manage_options')) {
    wp_die('You do not have permission to access this test.');
}

echo '<h1>M-Pesa Gateway Test</h1>';

// Test 1: Check if classes are loaded
echo '<h2>Class Loading Test</h2>';
$classes_to_check = [
    'SMS_Payment_Gateway_Base',
    'SMS_Payment_Gateway_Manager', 
    'SMS_MPESA_Gateway',
    'SMS_Gateway_Config_Manager',
    'SMS_Payment_Gateway_Init'
];

foreach ($classes_to_check as $class) {
    if (class_exists($class)) {
        echo "<p>✅ {$class} - Loaded</p>";
    } else {
        echo "<p>❌ {$class} - Not loaded</p>";
    }
}

// Test 2: Check gateway registration
echo '<h2>Gateway Registration Test</h2>';
try {
    $gateway_manager = SMS_Payment_Gateway_Manager::get_instance();
    $mpesa_gateway = $gateway_manager->get_gateway('mpesa');
    
    if ($mpesa_gateway) {
        echo "<p>✅ M-Pesa gateway registered successfully</p>";
        echo "<p>Gateway ID: " . $mpesa_gateway->get_id() . "</p>";
        echo "<p>Gateway Name: " . $mpesa_gateway->get_name() . "</p>";
        echo "<p>Enabled: " . ($mpesa_gateway->is_enabled() ? 'Yes' : 'No') . "</p>";
        echo "<p>Sandbox Mode: " . ($mpesa_gateway->is_sandbox() ? 'Yes' : 'No') . "</p>";
    } else {
        echo "<p>❌ M-Pesa gateway not registered</p>";
    }
} catch (Exception $e) {
    echo "<p>❌ Error checking gateway registration: " . $e->getMessage() . "</p>";
}

// Test 3: Check configuration
echo '<h2>Configuration Test</h2>';
try {
    $config_manager = new SMS_Gateway_Config_Manager();
    $mpesa_config = $config_manager->get_config('mpesa');
    
    if ($mpesa_config) {
        echo "<p>✅ M-Pesa configuration found</p>";
        echo "<p>Configuration keys: " . implode(', ', array_keys($mpesa_config)) . "</p>";
    } else {
        echo "<p>⚠️ M-Pesa configuration not found - using defaults</p>";
        $default_config = $config_manager->get_default_config_template('mpesa');
        echo "<p>Default configuration keys: " . implode(', ', array_keys($default_config)) . "</p>";
    }
} catch (Exception $e) {
    echo "<p>❌ Error checking configuration: " . $e->getMessage() . "</p>";
}

// Test 4: Check database table
echo '<h2>Database Table Test</h2>';
global $wpdb;
$table_name = $wpdb->prefix . 'sms_mpesa_transactions';
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;

if ($table_exists) {
    echo "<p>✅ M-Pesa transactions table exists</p>";
    $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
    echo "<p>Transaction count: {$count}</p>";
} else {
    echo "<p>⚠️ M-Pesa transactions table does not exist</p>";
    echo "<p>Creating table...</p>";
    try {
        SMS_MPESA_Gateway::create_transactions_table();
        echo "<p>✅ Table created successfully</p>";
    } catch (Exception $e) {
        echo "<p>❌ Error creating table: " . $e->getMessage() . "</p>";
    }
}

// Test 5: Phone number validation
echo '<h2>Phone Number Validation Test</h2>';
$test_numbers = [
    '0712345678',
    '254712345678', 
    '+254712345678',
    '0123456789', // Invalid
    '254123456789' // Invalid
];

if ($mpesa_gateway) {
    foreach ($test_numbers as $number) {
        $reflection = new ReflectionClass($mpesa_gateway);
        $method = $reflection->getMethod('validate_phone_number');
        $method->setAccessible(true);
        
        $result = $method->invoke($mpesa_gateway, $number);
        $status = is_wp_error($result) ? '❌' : '✅';
        echo "<p>{$status} {$number}</p>";
    }
}

echo '<h2>Test Complete</h2>';
echo '<p><a href="' . admin_url('admin.php?page=sms-mpesa-test') . '">Go to M-Pesa Admin Page</a></p>';
?>