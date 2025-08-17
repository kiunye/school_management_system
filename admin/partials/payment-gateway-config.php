<?php
/**
 * Payment Gateway Configuration Admin Interface
 *
 * Provides admin interface for configuring payment gateways including
 * M-Pesa, Airtel Money, and other payment methods.
 *
 * @package    School_Management_System
 * @subpackage School_Management_System/admin/partials
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Ensure textdomain is loaded for translations
if (class_exists('SMS_i18n')) {
    SMS_i18n::ensure_textdomain_loaded();
}

// Include required gateway classes
require_once SMS_PLUGIN_DIR . 'includes/financial/class-sms-gateway-config-manager.php';
require_once SMS_PLUGIN_DIR . 'includes/financial/class-sms-payment-gateway-base.php';
require_once SMS_PLUGIN_DIR . 'includes/financial/class-sms-mpesa-gateway.php';
require_once SMS_PLUGIN_DIR . 'includes/financial/class-sms-airtel-money-gateway.php';

// Initialize gateway config manager
try {
    $config_manager = SMS_Gateway_Config_Manager::get_instance();
} catch (Exception $e) {
    wp_die(__('Payment gateway configuration system is not available. Please check your plugin installation.', 'school-management-system'));
}

// Handle form submissions
$message = '';
$message_type = 'success';

if ($_POST) {
    if (!wp_verify_nonce($_POST['_wpnonce'], 'sms_gateway_config')) {
        $message = __('Security check failed. Please try again.', 'school-management-system');
        $message_type = 'error';
    } else {
        $action = sanitize_text_field($_POST['action']);
        
        switch ($action) {
            case 'save_mpesa_config':
                $mpesa_config = array(
                    'enabled' => isset($_POST['mpesa_enabled']) ? true : false,
                    'sandbox_mode' => isset($_POST['mpesa_sandbox']) ? true : false,
                    'consumer_key' => sanitize_text_field($_POST['mpesa_consumer_key']),
                    'consumer_secret' => sanitize_text_field($_POST['mpesa_consumer_secret']),
                    'shortcode' => sanitize_text_field($_POST['mpesa_shortcode']),
                    'passkey' => sanitize_text_field($_POST['mpesa_passkey']),
                    'callback_url' => esc_url_raw($_POST['mpesa_callback_url']),
                    'timeout_url' => esc_url_raw($_POST['mpesa_timeout_url']),
                    'result_url' => esc_url_raw($_POST['mpesa_result_url'])
                );
                
                $validation = $config_manager->validate_config('mpesa', $mpesa_config);
                if (is_wp_error($validation)) {
                    $message = __('M-Pesa configuration validation failed: ', 'school-management-system') . $validation->get_error_message();
                    $message_type = 'error';
                } else {
                    $config_manager->save_config('mpesa', $mpesa_config);
                    $message = __('M-Pesa configuration saved successfully!', 'school-management-system');
                }
                break;
                
            case 'save_airtel_config':
                $airtel_config = array(
                    'enabled' => isset($_POST['airtel_enabled']) ? true : false,
                    'sandbox_mode' => isset($_POST['airtel_sandbox']) ? true : false,
                    'client_id' => sanitize_text_field($_POST['airtel_client_id']),
                    'client_secret' => sanitize_text_field($_POST['airtel_client_secret']),
                    'merchant_id' => sanitize_text_field($_POST['airtel_merchant_id']),
                    'callback_url' => esc_url_raw($_POST['airtel_callback_url'])
                );
                
                $validation = $config_manager->validate_config('airtel_money', $airtel_config);
                if (is_wp_error($validation)) {
                    $message = __('Airtel Money configuration validation failed: ', 'school-management-system') . $validation->get_error_message();
                    $message_type = 'error';
                } else {
                    $config_manager->save_config('airtel_money', $airtel_config);
                    $message = __('Airtel Money configuration saved successfully!', 'school-management-system');
                }
                break;
                
            case 'test_mpesa_connection':
                $mpesa_config = $config_manager->get_config('mpesa');
                if ($mpesa_config && !empty($mpesa_config['consumer_key'])) {
                    $gateway = new SMS_MPESA_Gateway($mpesa_config);
                    $test_result = $gateway->test_connection();
                    if (is_wp_error($test_result)) {
                        $message = __('M-Pesa connection test failed: ', 'school-management-system') . $test_result->get_error_message();
                        $message_type = 'error';
                    } else {
                        $message = __('M-Pesa connection test successful!', 'school-management-system');
                    }
                } else {
                    $message = __('Please save M-Pesa configuration first.', 'school-management-system');
                    $message_type = 'warning';
                }
                break;
                
            case 'test_airtel_connection':
                $airtel_config = $config_manager->get_config('airtel_money');
                if ($airtel_config && !empty($airtel_config['client_id'])) {
                    $gateway = new SMS_Airtel_Money_Gateway($airtel_config);
                    $test_result = $gateway->test_connection();
                    if (is_wp_error($test_result)) {
                        $message = __('Airtel Money connection test failed: ', 'school-management-system') . $test_result->get_error_message();
                        $message_type = 'error';
                    } else {
                        $message = __('Airtel Money connection test successful!', 'school-management-system');
                    }
                } else {
                    $message = __('Please save Airtel Money configuration first.', 'school-management-system');
                    $message_type = 'warning';
                }
                break;
        }
    }
}

// Get current configurations
$mpesa_config = $config_manager->get_config('mpesa') ?: array();
$airtel_config = $config_manager->get_config('airtel_money') ?: array();

// Get current tab
$current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'mpesa';
?>

<div class="wrap">
    <h1><?php _e('Payment Gateway Configuration', 'school-management-system'); ?></h1>
    
    <?php if ($message): ?>
        <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible">
            <p><?php echo esc_html($message); ?></p>
        </div>
    <?php endif; ?>
    
    <div class="nav-tab-wrapper">
        <a href="?page=sms-payment-gateways&tab=mpesa" class="nav-tab <?php echo $current_tab === 'mpesa' ? 'nav-tab-active' : ''; ?>">
            <?php _e('M-Pesa', 'school-management-system'); ?>
        </a>
        <a href="?page=sms-payment-gateways&tab=airtel" class="nav-tab <?php echo $current_tab === 'airtel' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Airtel Money', 'school-management-system'); ?>
        </a>
        <a href="?page=sms-payment-gateways&tab=overview" class="nav-tab <?php echo $current_tab === 'overview' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Overview', 'school-management-system'); ?>
        </a>
    </div>
    
    <div class="tab-content">
        <?php if ($current_tab === 'mpesa'): ?>
            <!-- M-Pesa Configuration -->
            <div class="sms-card">
                <h2><?php _e('M-Pesa STK Push Configuration', 'school-management-system'); ?></h2>
                <p><?php _e('Configure your M-Pesa STK Push settings to enable mobile payments.', 'school-management-system'); ?></p>
                
                <form method="post" action="">
                    <?php wp_nonce_field('sms_gateway_config'); ?>
                    <input type="hidden" name="action" value="save_mpesa_config">
                    
                    <table class="form-table">
                        <tbody>
                            <tr>
                                <th scope="row">
                                    <label for="mpesa_enabled"><?php _e('Enable M-Pesa', 'school-management-system'); ?></label>
                                </th>
                                <td>
                                    <input type="checkbox" id="mpesa_enabled" name="mpesa_enabled" value="1" 
                                           <?php checked(isset($mpesa_config['enabled']) ? $mpesa_config['enabled'] : false); ?>>
                                    <p class="description"><?php _e('Enable M-Pesa STK Push payments', 'school-management-system'); ?></p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="mpesa_sandbox"><?php _e('Sandbox Mode', 'school-management-system'); ?></label>
                                </th>
                                <td>
                                    <input type="checkbox" id="mpesa_sandbox" name="mpesa_sandbox" value="1" 
                                           <?php checked(isset($mpesa_config['sandbox_mode']) ? $mpesa_config['sandbox_mode'] : true); ?>>
                                    <p class="description"><?php _e('Use M-Pesa sandbox environment for testing', 'school-management-system'); ?></p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="mpesa_consumer_key"><?php _e('Consumer Key', 'school-management-system'); ?> <span class="required">*</span></label>
                                </th>
                                <td>
                                    <input type="text" id="mpesa_consumer_key" name="mpesa_consumer_key" 
                                           value="<?php echo esc_attr($mpesa_config['consumer_key'] ?? ''); ?>" 
                                           class="regular-text" required>
                                    <p class="description"><?php _e('Your M-Pesa Consumer Key from Safaricom Developer Portal', 'school-management-system'); ?></p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="mpesa_consumer_secret"><?php _e('Consumer Secret', 'school-management-system'); ?> <span class="required">*</span></label>
                                </th>
                                <td>
                                    <input type="password" id="mpesa_consumer_secret" name="mpesa_consumer_secret" 
                                           value="<?php echo esc_attr($mpesa_config['consumer_secret'] ?? ''); ?>" 
                                           class="regular-text" required>
                                    <p class="description"><?php _e('Your M-Pesa Consumer Secret from Safaricom Developer Portal', 'school-management-system'); ?></p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="mpesa_shortcode"><?php _e('Business Shortcode', 'school-management-system'); ?> <span class="required">*</span></label>
                                </th>
                                <td>
                                    <input type="text" id="mpesa_shortcode" name="mpesa_shortcode" 
                                           value="<?php echo esc_attr($mpesa_config['shortcode'] ?? ''); ?>" 
                                           class="regular-text" required pattern="[0-9]{5,7}">
                                    <p class="description"><?php _e('Your M-Pesa Business Shortcode (5-7 digits)', 'school-management-system'); ?></p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="mpesa_passkey"><?php _e('Passkey', 'school-management-system'); ?> <span class="required">*</span></label>
                                </th>
                                <td>
                                    <input type="password" id="mpesa_passkey" name="mpesa_passkey" 
                                           value="<?php echo esc_attr($mpesa_config['passkey'] ?? ''); ?>" 
                                           class="regular-text" required>
                                    <p class="description"><?php _e('Your M-Pesa STK Push Passkey', 'school-management-system'); ?></p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="mpesa_callback_url"><?php _e('Callback URL', 'school-management-system'); ?></label>
                                </th>
                                <td>
                                    <input type="url" id="mpesa_callback_url" name="mpesa_callback_url" 
                                           value="<?php echo esc_attr($mpesa_config['callback_url'] ?? site_url('/wp-json/sms/v1/mpesa/callback')); ?>" 
                                           class="regular-text">
                                    <p class="description"><?php _e('URL where M-Pesa will send payment confirmations', 'school-management-system'); ?></p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="mpesa_timeout_url"><?php _e('Timeout URL', 'school-management-system'); ?></label>
                                </th>
                                <td>
                                    <input type="url" id="mpesa_timeout_url" name="mpesa_timeout_url" 
                                           value="<?php echo esc_attr($mpesa_config['timeout_url'] ?? site_url('/wp-json/sms/v1/mpesa/timeout')); ?>" 
                                           class="regular-text">
                                    <p class="description"><?php _e('URL where M-Pesa will send timeout notifications', 'school-management-system'); ?></p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="mpesa_result_url"><?php _e('Result URL', 'school-management-system'); ?></label>
                                </th>
                                <td>
                                    <input type="url" id="mpesa_result_url" name="mpesa_result_url" 
                                           value="<?php echo esc_attr($mpesa_config['result_url'] ?? site_url('/wp-json/sms/v1/mpesa/result')); ?>" 
                                           class="regular-text">
                                    <p class="description"><?php _e('URL where M-Pesa will send transaction results', 'school-management-system'); ?></p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <p class="submit">
                        <input type="submit" name="submit" id="submit" class="button-primary" 
                               value="<?php _e('Save M-Pesa Configuration', 'school-management-system'); ?>">
                    </p>
                </form>
                
                <!-- Test Connection -->
                <div class="test-connection-section">
                    <h3><?php _e('Test M-Pesa Connection', 'school-management-system'); ?></h3>
                    <p><?php _e('Test your M-Pesa configuration to ensure it\'s working correctly.', 'school-management-system'); ?></p>
                    
                    <form method="post" action="" style="display: inline;">
                        <?php wp_nonce_field('sms_gateway_config'); ?>
                        <input type="hidden" name="action" value="test_mpesa_connection">
                        <input type="submit" class="button-secondary" value="<?php _e('Test Connection', 'school-management-system'); ?>">
                    </form>
                </div>
            </div>
            
        <?php elseif ($current_tab === 'airtel'): ?>
            <!-- Airtel Money Configuration -->
            <div class="sms-card">
                <h2><?php _e('Airtel Money Configuration', 'school-management-system'); ?></h2>
                <p><?php _e('Configure your Airtel Money settings to enable mobile payments.', 'school-management-system'); ?></p>
                
                <form method="post" action="">
                    <?php wp_nonce_field('sms_gateway_config'); ?>
                    <input type="hidden" name="action" value="save_airtel_config">
                    
                    <table class="form-table">
                        <tbody>
                            <tr>
                                <th scope="row">
                                    <label for="airtel_enabled"><?php _e('Enable Airtel Money', 'school-management-system'); ?></label>
                                </th>
                                <td>
                                    <input type="checkbox" id="airtel_enabled" name="airtel_enabled" value="1" 
                                           <?php checked(isset($airtel_config['enabled']) ? $airtel_config['enabled'] : false); ?>>
                                    <p class="description"><?php _e('Enable Airtel Money payments', 'school-management-system'); ?></p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="airtel_sandbox"><?php _e('Sandbox Mode', 'school-management-system'); ?></label>
                                </th>
                                <td>
                                    <input type="checkbox" id="airtel_sandbox" name="airtel_sandbox" value="1" 
                                           <?php checked(isset($airtel_config['sandbox_mode']) ? $airtel_config['sandbox_mode'] : true); ?>>
                                    <p class="description"><?php _e('Use Airtel Money sandbox environment for testing', 'school-management-system'); ?></p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="airtel_client_id"><?php _e('Client ID', 'school-management-system'); ?> <span class="required">*</span></label>
                                </th>
                                <td>
                                    <input type="text" id="airtel_client_id" name="airtel_client_id" 
                                           value="<?php echo esc_attr($airtel_config['client_id'] ?? ''); ?>" 
                                           class="regular-text" required>
                                    <p class="description"><?php _e('Your Airtel Money Client ID from Airtel Developer Portal', 'school-management-system'); ?></p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="airtel_client_secret"><?php _e('Client Secret', 'school-management-system'); ?> <span class="required">*</span></label>
                                </th>
                                <td>
                                    <input type="password" id="airtel_client_secret" name="airtel_client_secret" 
                                           value="<?php echo esc_attr($airtel_config['client_secret'] ?? ''); ?>" 
                                           class="regular-text" required>
                                    <p class="description"><?php _e('Your Airtel Money Client Secret from Airtel Developer Portal', 'school-management-system'); ?></p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="airtel_merchant_id"><?php _e('Merchant ID', 'school-management-system'); ?> <span class="required">*</span></label>
                                </th>
                                <td>
                                    <input type="text" id="airtel_merchant_id" name="airtel_merchant_id" 
                                           value="<?php echo esc_attr($airtel_config['merchant_id'] ?? ''); ?>" 
                                           class="regular-text" required>
                                    <p class="description"><?php _e('Your Airtel Money Merchant ID', 'school-management-system'); ?></p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="airtel_callback_url"><?php _e('Callback URL', 'school-management-system'); ?></label>
                                </th>
                                <td>
                                    <input type="url" id="airtel_callback_url" name="airtel_callback_url" 
                                           value="<?php echo esc_attr($airtel_config['callback_url'] ?? site_url('/wp-json/sms/v1/airtel/callback')); ?>" 
                                           class="regular-text">
                                    <p class="description"><?php _e('URL where Airtel Money will send payment confirmations', 'school-management-system'); ?></p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <p class="submit">
                        <input type="submit" name="submit" id="submit" class="button-primary" 
                               value="<?php _e('Save Airtel Money Configuration', 'school-management-system'); ?>">
                    </p>
                </form>
                
                <!-- Test Connection -->
                <div class="test-connection-section">
                    <h3><?php _e('Test Airtel Money Connection', 'school-management-system'); ?></h3>
                    <p><?php _e('Test your Airtel Money configuration to ensure it\'s working correctly.', 'school-management-system'); ?></p>
                    
                    <form method="post" action="" style="display: inline;">
                        <?php wp_nonce_field('sms_gateway_config'); ?>
                        <input type="hidden" name="action" value="test_airtel_connection">
                        <input type="submit" class="button-secondary" value="<?php _e('Test Connection', 'school-management-system'); ?>">
                    </form>
                </div>
            </div>
            
        <?php elseif ($current_tab === 'overview'): ?>
            <!-- Gateway Overview -->
            <div class="sms-card">
                <h2><?php _e('Payment Gateway Overview', 'school-management-system'); ?></h2>
                <p><?php _e('Overview of all configured payment gateways and their status.', 'school-management-system'); ?></p>
                
                <div class="gateway-status-grid">
                    <!-- M-Pesa Status -->
                    <div class="gateway-status-card">
                        <div class="gateway-icon">📱</div>
                        <div class="gateway-info">
                            <h3><?php _e('M-Pesa STK Push', 'school-management-system'); ?></h3>
                            <?php 
                            $mpesa_enabled = isset($mpesa_config['enabled']) && $mpesa_config['enabled'];
                            $mpesa_configured = !empty($mpesa_config['consumer_key']);
                            ?>
                            <div class="status-indicators">
                                <span class="status-badge <?php echo $mpesa_configured ? 'status-configured' : 'status-not-configured'; ?>">
                                    <?php echo $mpesa_configured ? __('Configured', 'school-management-system') : __('Not Configured', 'school-management-system'); ?>
                                </span>
                                <span class="status-badge <?php echo $mpesa_enabled ? 'status-enabled' : 'status-disabled'; ?>">
                                    <?php echo $mpesa_enabled ? __('Enabled', 'school-management-system') : __('Disabled', 'school-management-system'); ?>
                                </span>
                                <?php if (isset($mpesa_config['sandbox_mode']) && $mpesa_config['sandbox_mode']): ?>
                                    <span class="status-badge status-sandbox"><?php _e('Sandbox', 'school-management-system'); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="gateway-actions">
                                <a href="?page=sms-payment-gateways&tab=mpesa" class="button"><?php _e('Configure', 'school-management-system'); ?></a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Airtel Money Status -->
                    <div class="gateway-status-card">
                        <div class="gateway-icon">💳</div>
                        <div class="gateway-info">
                            <h3><?php _e('Airtel Money', 'school-management-system'); ?></h3>
                            <?php 
                            $airtel_enabled = isset($airtel_config['enabled']) && $airtel_config['enabled'];
                            $airtel_configured = !empty($airtel_config['client_id']);
                            ?>
                            <div class="status-indicators">
                                <span class="status-badge <?php echo $airtel_configured ? 'status-configured' : 'status-not-configured'; ?>">
                                    <?php echo $airtel_configured ? __('Configured', 'school-management-system') : __('Not Configured', 'school-management-system'); ?>
                                </span>
                                <span class="status-badge <?php echo $airtel_enabled ? 'status-enabled' : 'status-disabled'; ?>">
                                    <?php echo $airtel_enabled ? __('Enabled', 'school-management-system') : __('Disabled', 'school-management-system'); ?>
                                </span>
                                <?php if (isset($airtel_config['sandbox_mode']) && $airtel_config['sandbox_mode']): ?>
                                    <span class="status-badge status-sandbox"><?php _e('Sandbox', 'school-management-system'); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="gateway-actions">
                                <a href="?page=sms-payment-gateways&tab=airtel" class="button"><?php _e('Configure', 'school-management-system'); ?></a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Bank Transfer (Future) -->
                    <div class="gateway-status-card">
                        <div class="gateway-icon">🏦</div>
                        <div class="gateway-info">
                            <h3><?php _e('Bank Transfer', 'school-management-system'); ?></h3>
                            <div class="status-indicators">
                                <span class="status-badge status-available"><?php _e('Available', 'school-management-system'); ?></span>
                            </div>
                            <div class="gateway-actions">
                                <button class="button" disabled><?php _e('Coming Soon', 'school-management-system'); ?></button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Setup Guide -->
                <div class="setup-guide">
                    <h3><?php _e('Quick Setup Guide', 'school-management-system'); ?></h3>
                    <ol>
                        <li><?php _e('Register for M-Pesa and/or Airtel Money merchant accounts', 'school-management-system'); ?></li>
                        <li><?php _e('Obtain your API credentials from the respective developer portals', 'school-management-system'); ?></li>
                        <li><?php _e('Configure the gateway settings using the tabs above', 'school-management-system'); ?></li>
                        <li><?php _e('Test the connection to ensure everything is working', 'school-management-system'); ?></li>
                        <li><?php _e('Enable the gateways to start accepting payments', 'school-management-system'); ?></li>
                    </ol>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.sms-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin-bottom: 20px;
}

.required {
    color: #d63638;
}

.test-connection-section {
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #ddd;
}

.gateway-status-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.gateway-status-card {
    display: flex;
    align-items: center;
    padding: 20px;
    border: 1px solid #ddd;
    border-radius: 8px;
    background: #f9f9f9;
}

.gateway-icon {
    font-size: 48px;
    margin-right: 20px;
}

.gateway-info h3 {
    margin: 0 0 10px 0;
}

.status-indicators {
    margin: 10px 0;
}

.status-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: bold;
    text-transform: uppercase;
    margin-right: 5px;
}

.status-configured { background: #d1e7dd; color: #0f5132; }
.status-not-configured { background: #f8d7da; color: #721c24; }
.status-enabled { background: #d1e7dd; color: #0f5132; }
.status-disabled { background: #fff3cd; color: #664d03; }
.status-sandbox { background: #cff4fc; color: #055160; }
.status-available { background: #e2e3e5; color: #41464b; }

.gateway-actions {
    margin-top: 10px;
}

.setup-guide {
    margin-top: 30px;
    padding: 20px;
    background: #f0f6fc;
    border-radius: 8px;
}

.setup-guide ol {
    margin-left: 20px;
}

.setup-guide li {
    margin-bottom: 8px;
}
</style>
