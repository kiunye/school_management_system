<?php
/**
 * Airtel Money Test Page Template
 *
 * @package SchoolManagementSystem
 * @subpackage Admin
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get gateway configuration
$config_manager = SMS_Gateway_Config_Manager::get_instance();
$config = $config_manager->get_config('airtel_money');

if (!$config || !$config['enabled']) {
    echo '<div class="wrap"><h1>Airtel Money Test</h1>';
    echo '<div class="notice notice-error"><p>Airtel Money gateway is not configured or enabled. Please configure it first.</p></div>';
    echo '</div>';
    return;
}

// Get recent transactions for display
$admin = new SMS_Airtel_Money_Admin();
$stats = $admin->get_transaction_stats();
?>

<div class="wrap">
    <h1>Airtel Money Gateway Test</h1>
    
    <div class="notice notice-info">
        <p><strong>Note:</strong> This is a test interface for the Airtel Money payment gateway. 
        <?php if ($config['sandbox_mode']): ?>
            You are currently in <strong>sandbox mode</strong>.
        <?php else: ?>
            You are in <strong>production mode</strong>. Be careful with real transactions!
        <?php endif; ?>
        </p>
    </div>
    
    <!-- Gateway Statistics -->
    <div class="card">
        <h2>Gateway Statistics</h2>
        <table class="wp-list-table widefat fixed striped">
            <tbody>
                <tr>
                    <td><strong>Total Transactions</strong></td>
                    <td><?php echo number_format($stats['total']); ?></td>
                </tr>
                <tr>
                    <td><strong>Successful</strong></td>
                    <td style="color: green;"><?php echo number_format($stats['successful']); ?></td>
                </tr>
                <tr>
                    <td><strong>Failed</strong></td>
                    <td style="color: red;"><?php echo number_format($stats['failed']); ?></td>
                </tr>
                <tr>
                    <td><strong>Pending</strong></td>
                    <td style="color: orange;"><?php echo number_format($stats['pending']); ?></td>
                </tr>
                <tr>
                    <td><strong>Total Amount Processed</strong></td>
                    <td><strong>KES <?php echo number_format($stats['total_amount'], 2); ?></strong></td>
                </tr>
            </tbody>
        </table>
    </div>
    
    <!-- Test Payment Form -->
    <div class="card">
        <h2>Test Payment</h2>
        <form id="airtel-test-form">
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="test_amount">Amount (KES)</label>
                    </th>
                    <td>
                        <input type="number" id="test_amount" name="amount" min="1" max="70000" step="0.01" value="10.00" class="regular-text" required />
                        <p class="description">Enter amount between KES 1 and KES 70,000</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="test_phone">Phone Number</label>
                    </th>
                    <td>
                        <input type="tel" id="test_phone" name="phone_number" placeholder="0712345678" class="regular-text" required />
                        <p class="description">Enter Airtel Kenya phone number (07XXXXXXXX format)</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="test_reference">Reference</label>
                    </th>
                    <td>
                        <input type="text" id="test_reference" name="reference" value="TEST_<?php echo time(); ?>" class="regular-text" required />
                        <p class="description">Unique payment reference</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="test_description">Description</label>
                    </th>
                    <td>
                        <input type="text" id="test_description" name="description" value="Test Payment" class="regular-text" />
                        <p class="description">Payment description (optional)</p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <button type="submit" class="button button-primary">Initiate Test Payment</button>
                <button type="button" id="verify-payment" class="button button-secondary" disabled>Verify Payment</button>
            </p>
        </form>
        
        <div id="test-result" style="margin-top: 20px;"></div>
    </div>
    
    <!-- Recent Transactions -->
    <?php if (!empty($stats['recent'])): ?>
    <div class="card">
        <h2>Recent Transactions</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Transaction ID</th>
                    <th>Phone Number</th>
                    <th>Amount</th>
                    <th>Reference</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($stats['recent'] as $transaction): ?>
                <tr>
                    <td><code><?php echo esc_html($transaction['transaction_id']); ?></code></td>
                    <td><?php echo esc_html($transaction['phone_number']); ?></td>
                    <td>KES <?php echo number_format($transaction['amount'], 2); ?></td>
                    <td><?php echo esc_html($transaction['reference']); ?></td>
                    <td>
                        <span class="status-<?php echo esc_attr($transaction['status']); ?>">
                            <?php echo ucfirst($transaction['status']); ?>
                        </span>
                    </td>
                    <td><?php echo date('Y-m-d H:i:s', strtotime($transaction['created_at'])); ?></td>
                    <td>
                        <button type="button" class="button button-small verify-transaction" 
                                data-transaction-id="<?php echo esc_attr($transaction['transaction_id']); ?>">
                            Verify
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    
    <!-- Connection Test -->
    <div class="card">
        <h2>Connection Test</h2>
        <p>Test the connection to Airtel Money API servers.</p>
        <button type="button" id="test-connection" class="button button-secondary">Test Connection</button>
        <div id="connection-result" style="margin-top: 10px;"></div>
    </div>
</div>

<style>
.status-completed { color: green; font-weight: bold; }
.status-failed { color: red; font-weight: bold; }
.status-pending { color: orange; font-weight: bold; }
.status-unknown { color: gray; font-weight: bold; }

#test-result .notice {
    margin: 10px 0;
}

.transaction-details {
    background: #f9f9f9;
    padding: 10px;
    margin: 10px 0;
    border-left: 4px solid #0073aa;
}

.transaction-details pre {
    background: white;
    padding: 10px;
    border: 1px solid #ddd;
    overflow-x: auto;
}
</style>

<script>
jQuery(document).ready(function($) {
    var currentTransactionId = null;
    
    // Test payment form submission
    $('#airtel-test-form').on('submit', function(e) {
        e.preventDefault();
        
        var form = $(this);
        var submitBtn = form.find('button[type="submit"]');
        var resultDiv = $('#test-result');
        
        // Validate form
        var amount = parseFloat($('#test_amount').val());
        var phone = $('#test_phone').val().trim();
        var reference = $('#test_reference').val().trim();
        
        if (amount < 1 || amount > 70000) {
            alert('Amount must be between KES 1 and KES 70,000');
            return;
        }
        
        if (!phone.match(/^07\d{8}$/)) {
            alert('Please enter a valid Airtel Kenya phone number (07XXXXXXXX)');
            return;
        }
        
        if (!reference) {
            alert('Please enter a payment reference');
            return;
        }
        
        // Disable form and show loading
        submitBtn.prop('disabled', true).text('Processing...');
        resultDiv.html('<div class="notice notice-info"><p>Initiating payment...</p></div>');
        
        // Make AJAX request
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'sms_test_airtel_payment',
                nonce: '<?php echo wp_create_nonce('sms_airtel_admin'); ?>',
                amount: amount,
                phone_number: phone,
                reference: reference,
                description: $('#test_description').val()
            },
            success: function(response) {
                if (response.success) {
                    currentTransactionId = response.data.transaction_id;
                    $('#verify-payment').prop('disabled', false);
                    
                    var html = '<div class="notice notice-success"><p><strong>Payment initiated successfully!</strong></p></div>';
                    html += '<div class="transaction-details">';
                    html += '<h4>Transaction Details:</h4>';
                    html += '<p><strong>Transaction ID:</strong> <code>' + response.data.transaction_id + '</code></p>';
                    html += '<p><strong>Status:</strong> ' + response.data.status + '</p>';
                    html += '<p><strong>Amount:</strong> KES ' + response.data.amount + '</p>';
                    html += '<p><strong>Phone:</strong> ' + response.data.phone_number + '</p>';
                    if (response.data.response_message) {
                        html += '<p><strong>Message:</strong> ' + response.data.response_message + '</p>';
                    }
                    html += '</div>';
                    
                    resultDiv.html(html);
                } else {
                    resultDiv.html('<div class="notice notice-error"><p><strong>Payment failed:</strong> ' + response.data + '</p></div>');
                }
            },
            error: function(xhr, status, error) {
                resultDiv.html('<div class="notice notice-error"><p><strong>Request failed:</strong> ' + error + '</p></div>');
            },
            complete: function() {
                submitBtn.prop('disabled', false).text('Initiate Test Payment');
            }
        });
    });
    
    // Verify payment button
    $('#verify-payment').on('click', function() {
        if (!currentTransactionId) {
            alert('No transaction to verify');
            return;
        }
        
        verifyTransaction(currentTransactionId);
    });
    
    // Verify transaction buttons in recent transactions table
    $('.verify-transaction').on('click', function() {
        var transactionId = $(this).data('transaction-id');
        verifyTransaction(transactionId);
    });
    
    // Verify transaction function
    function verifyTransaction(transactionId) {
        var resultDiv = $('#test-result');
        
        resultDiv.html('<div class="notice notice-info"><p>Verifying payment...</p></div>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'sms_verify_airtel_payment',
                nonce: '<?php echo wp_create_nonce('sms_airtel_admin'); ?>',
                transaction_id: transactionId
            },
            success: function(response) {
                if (response.success) {
                    var html = '<div class="notice notice-success"><p><strong>Payment verification completed!</strong></p></div>';
                    html += '<div class="transaction-details">';
                    html += '<h4>Verification Results:</h4>';
                    html += '<p><strong>Transaction ID:</strong> <code>' + response.data.transaction_id + '</code></p>';
                    html += '<p><strong>Status:</strong> ' + response.data.status + '</p>';
                    if (response.data.amount) {
                        html += '<p><strong>Amount:</strong> KES ' + response.data.amount + '</p>';
                    }
                    if (response.data.airtel_transaction_id) {
                        html += '<p><strong>Airtel Transaction ID:</strong> <code>' + response.data.airtel_transaction_id + '</code></p>';
                    }
                    if (response.data.status_message) {
                        html += '<p><strong>Status Message:</strong> ' + response.data.status_message + '</p>';
                    }
                    html += '</div>';
                    
                    resultDiv.html(html);
                } else {
                    resultDiv.html('<div class="notice notice-error"><p><strong>Verification failed:</strong> ' + response.data + '</p></div>');
                }
            },
            error: function(xhr, status, error) {
                resultDiv.html('<div class="notice notice-error"><p><strong>Verification request failed:</strong> ' + error + '</p></div>');
            }
        });
    }
    
    // Test connection button
    $('#test-connection').on('click', function() {
        var button = $(this);
        var resultDiv = $('#connection-result');
        
        button.prop('disabled', true).text('Testing...');
        resultDiv.html('');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'sms_test_airtel_connection',
                nonce: '<?php echo wp_create_nonce('sms_airtel_admin'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    resultDiv.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                } else {
                    resultDiv.html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
                }
            },
            error: function() {
                resultDiv.html('<div class="notice notice-error"><p>Connection test failed</p></div>');
            },
            complete: function() {
                button.prop('disabled', false).text('Test Connection');
            }
        });
    });
});
</script>