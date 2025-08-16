<?php
/**
 * M-Pesa Payment Gateway Test Page
 *
 * Admin interface for testing M-Pesa payment functionality
 *
 * @package SchoolManagementSystem
 * @subpackage Admin
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Check user permissions
if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}

// Get M-Pesa gateway instance
$gateway_manager = SMS_Payment_Gateway_Manager::get_instance();
$mpesa_gateway = $gateway_manager->get_gateway('mpesa');

// Handle form submissions
$message = '';
$message_type = '';

if (isset($_POST['test_connection'])) {
    if (wp_verify_nonce($_POST['_wpnonce'], 'sms_mpesa_test')) {
        if ($mpesa_gateway) {
            $test_result = $mpesa_gateway->test_connection();
            if (is_wp_error($test_result)) {
                $message = 'Connection test failed: ' . $test_result->get_error_message();
                $message_type = 'error';
            } else {
                $message = 'Connection test successful! Environment: ' . $test_result['environment'];
                $message_type = 'success';
            }
        } else {
            $message = 'M-Pesa gateway not found or not configured.';
            $message_type = 'error';
        }
    }
}

if (isset($_POST['save_config'])) {
    if (wp_verify_nonce($_POST['_wpnonce'], 'sms_mpesa_config')) {
        $config_manager = new SMS_Gateway_Config_Manager();
        
        $config = [
            'enabled' => isset($_POST['enabled']),
            'sandbox_mode' => isset($_POST['sandbox_mode']),
            'consumer_key' => sanitize_text_field($_POST['consumer_key']),
            'consumer_secret' => sanitize_text_field($_POST['consumer_secret']),
            'shortcode' => sanitize_text_field($_POST['shortcode']),
            'passkey' => sanitize_text_field($_POST['passkey']),
            'callback_url' => esc_url_raw($_POST['callback_url'])
        ];
        
        $validation = $config_manager->validate_config('mpesa', $config);
        if (is_wp_error($validation)) {
            $message = 'Configuration validation failed: ' . $validation->get_error_message();
            $message_type = 'error';
        } else {
            $result = $config_manager->save_config('mpesa', $config);
            if ($result) {
                $message = 'M-Pesa configuration saved successfully!';
                $message_type = 'success';
                // Refresh gateway instance
                $mpesa_gateway = $gateway_manager->get_gateway('mpesa');
            } else {
                $message = 'Failed to save configuration.';
                $message_type = 'error';
            }
        }
    }
}

// Get current configuration
$config_manager = new SMS_Gateway_Config_Manager();
$current_config = $config_manager->get_config('mpesa') ?: $config_manager->get_default_config_template('mpesa');
?>

<div class="wrap sms-payment-container">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="sms-payment-messages">
        <?php if ($message): ?>
            <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible">
                <p><?php echo esc_html($message); ?></p>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="nav-tab-wrapper">
        <a href="#configuration" class="nav-tab nav-tab-active">Configuration</a>
        <a href="#test-payment" class="nav-tab">Test Payment</a>
        <a href="#transactions" class="nav-tab">Transactions</a>
    </div>
    
    <div id="configuration" class="tab-content">
        <h2>M-Pesa Gateway Configuration</h2>
        
        <form method="post" action="">
            <?php wp_nonce_field('sms_mpesa_config'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">Enable M-Pesa</th>
                    <td>
                        <label>
                            <input type="checkbox" name="enabled" value="1" <?php checked($current_config['enabled']); ?>>
                            Enable M-Pesa payment gateway
                        </label>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">Sandbox Mode</th>
                    <td>
                        <label>
                            <input type="checkbox" name="sandbox_mode" value="1" <?php checked($current_config['sandbox_mode']); ?>>
                            Use sandbox environment for testing
                        </label>
                        <p class="description">Uncheck this for production environment</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">Consumer Key</th>
                    <td>
                        <input type="text" name="consumer_key" value="<?php echo esc_attr($current_config['consumer_key']); ?>" class="regular-text" required>
                        <p class="description">Your M-Pesa app consumer key from Safaricom Developer Portal</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">Consumer Secret</th>
                    <td>
                        <input type="password" name="consumer_secret" value="<?php echo esc_attr($current_config['consumer_secret']); ?>" class="regular-text" required>
                        <p class="description">Your M-Pesa app consumer secret</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">Business Shortcode</th>
                    <td>
                        <input type="text" name="shortcode" value="<?php echo esc_attr($current_config['shortcode']); ?>" class="regular-text" required>
                        <p class="description">Your M-Pesa business shortcode (e.g., 174379 for sandbox)</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">Passkey</th>
                    <td>
                        <input type="password" name="passkey" value="<?php echo esc_attr($current_config['passkey']); ?>" class="regular-text" required>
                        <p class="description">Your M-Pesa passkey for STK Push</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">Callback URL</th>
                    <td>
                        <input type="url" name="callback_url" value="<?php echo esc_attr($current_config['callback_url']); ?>" class="regular-text" required>
                        <p class="description">URL where M-Pesa will send payment notifications</p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" name="save_config" class="button-primary" value="Save Configuration">
                <input type="submit" name="test_connection" class="button-secondary" value="Test Connection">
            </p>
        </form>
    </div>
    
    <div id="test-payment" class="tab-content" style="display: none;">
        <h2>Test M-Pesa Payment</h2>
        
        <?php if ($mpesa_gateway && $mpesa_gateway->is_enabled()): ?>
            <div class="card">
                <h3>Payment Test Form</h3>
                <form class="sms-mpesa-payment-form">
                    <table class="form-table">
                        <tr>
                            <th scope="row">Amount (KES)</th>
                            <td>
                                <input type="number" name="amount" min="1" max="70000" value="10" required>
                                <p class="description">Amount to charge (minimum 1 KES)</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">Phone Number</th>
                            <td>
                                <input type="tel" name="phone_number" placeholder="254712345678" pattern="254[17]\d{8}" required>
                                <p class="description">Customer phone number (format: 254712345678)</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">Reference</th>
                            <td>
                                <input type="text" name="reference" value="TEST<?php echo time(); ?>" required>
                                <p class="description">Payment reference/invoice number</p>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <button type="submit" class="button-primary sms-submit-payment">Initiate M-Pesa Payment</button>
                    </p>
                </form>
            </div>
            
            <div class="card">
                <h3>Test Phone Numbers (Sandbox)</h3>
                <p>Use these test phone numbers in sandbox mode:</p>
                <ul>
                    <li><strong>254708374149</strong> - Successful payment</li>
                    <li><strong>254711111111</strong> - Insufficient funds</li>
                    <li><strong>254722222222</strong> - Invalid account</li>
                    <li><strong>254733333333</strong> - User cancelled</li>
                </ul>
            </div>
        <?php else: ?>
            <div class="notice notice-warning">
                <p>M-Pesa gateway is not enabled or configured. Please configure it first in the Configuration tab.</p>
            </div>
        <?php endif; ?>
    </div>
    
    <div id="transactions" class="tab-content" style="display: none;">
        <h2>Recent M-Pesa Transactions</h2>
        
        <?php
        // Get recent transactions
        global $wpdb;
        $table_name = $wpdb->prefix . 'sms_mpesa_transactions';
        
        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
        
        if ($table_exists):
            $transactions = $wpdb->get_results(
                "SELECT * FROM {$table_name} ORDER BY created_at DESC LIMIT 20",
                ARRAY_A
            );
            
            if ($transactions):
        ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Phone Number</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>M-Pesa Receipt</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $transaction): ?>
                        <tr>
                            <td><?php echo esc_html($transaction['reference']); ?></td>
                            <td><?php echo esc_html($transaction['phone_number']); ?></td>
                            <td>KES <?php echo esc_html(number_format($transaction['amount'], 2)); ?></td>
                            <td>
                                <span class="status-<?php echo esc_attr($transaction['status']); ?>">
                                    <?php echo esc_html(ucfirst($transaction['status'])); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html($transaction['mpesa_receipt_number'] ?: '-'); ?></td>
                            <td><?php echo esc_html(date('Y-m-d H:i:s', strtotime($transaction['created_at']))); ?></td>
                            <td>
                                <?php if ($transaction['status'] === 'pending'): ?>
                                    <button type="button" class="button-secondary sms-verify-payment-btn" 
                                            data-checkout-id="<?php echo esc_attr($transaction['checkout_request_id']); ?>">
                                        Verify
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No transactions found.</p>
        <?php endif; ?>
        
        <?php else: ?>
            <div class="notice notice-info">
                <p>M-Pesa transactions table not found. It will be created automatically when the first payment is processed.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.tab-content {
    margin-top: 20px;
}

.nav-tab-wrapper {
    margin-bottom: 0;
}

.nav-tab {
    cursor: pointer;
}

.card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin-bottom: 20px;
}

.status-pending {
    color: #f56e28;
}

.status-completed {
    color: #00a32a;
}

.status-failed {
    color: #d63638;
}

.status-cancelled {
    color: #8c8f94;
}

.status-timeout {
    color: #dba617;
}

.sms-payment-messages {
    margin-bottom: 20px;
}

.sms-transaction-details {
    margin-top: 20px;
    background: #f9f9f9;
    padding: 15px;
    border-radius: 4px;
}

.sms-payment-actions {
    margin-top: 15px;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Tab switching
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        
        var target = $(this).attr('href');
        
        // Update active tab
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        
        // Show/hide content
        $('.tab-content').hide();
        $(target).show();
    });
});
</script>

<?php
// Enqueue M-Pesa payment script
wp_enqueue_script(
    'sms-mpesa-payment',
    SMS_PLUGIN_URL . 'admin/js/mpesa-payment.js',
    ['jquery'],
    SMS_VERSION,
    true
);

// Localize script with AJAX data
wp_localize_script('sms-mpesa-payment', 'smsPaymentGateway', [
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('sms_payment_nonce'),
    'restUrl' => rest_url('sms/v1/'),
    'restNonce' => wp_create_nonce('wp_rest')
]);
?>