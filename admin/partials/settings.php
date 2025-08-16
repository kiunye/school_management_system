<?php
/**
 * Settings Management Page
 *
 * @package SchoolManagementSystem
 * @subpackage Admin/Partials
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Handle form submissions
if (isset($_POST['submit']) && wp_verify_nonce($_POST['sms_settings_nonce'], 'sms_settings_action')) {
    // Save general settings
    if (isset($_POST['sms_school_name'])) {
        update_option('sms_school_name', sanitize_text_field($_POST['sms_school_name']));
    }
    if (isset($_POST['sms_school_address'])) {
        update_option('sms_school_address', sanitize_textarea_field($_POST['sms_school_address']));
    }
    if (isset($_POST['sms_school_phone'])) {
        update_option('sms_school_phone', sanitize_text_field($_POST['sms_school_phone']));
    }
    if (isset($_POST['sms_school_email'])) {
        update_option('sms_school_email', sanitize_email($_POST['sms_school_email']));
    }
    if (isset($_POST['sms_academic_year'])) {
        update_option('sms_academic_year', sanitize_text_field($_POST['sms_academic_year']));
    }
    if (isset($_POST['sms_current_term'])) {
        update_option('sms_current_term', sanitize_text_field($_POST['sms_current_term']));
    }
    
    echo '<div class="notice notice-success is-dismissible"><p>' . __('Settings saved successfully!', 'school-management-system') . '</p></div>';
}

// Get current settings
$school_name = get_option('sms_school_name', get_bloginfo('name'));
$school_address = get_option('sms_school_address', '');
$school_phone = get_option('sms_school_phone', '');
$school_email = get_option('sms_school_email', get_option('admin_email'));
$academic_year = get_option('sms_academic_year', date('Y') . '-' . (date('Y') + 1));
$current_term = get_option('sms_current_term', 'Term 1');

// Get active tab
$active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
?>

<div class="wrap">
    <h1><?php _e('School Management Settings', 'school-management-system'); ?></h1>
    
    <!-- Settings Tabs -->
    <nav class="nav-tab-wrapper">
        <a href="?page=sms-settings&tab=general" class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">
            <?php _e('General', 'school-management-system'); ?>
        </a>
        <a href="?page=sms-settings&tab=academic" class="nav-tab <?php echo $active_tab === 'academic' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Academic', 'school-management-system'); ?>
        </a>
        <a href="?page=sms-settings&tab=payments" class="nav-tab <?php echo $active_tab === 'payments' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Payments', 'school-management-system'); ?>
        </a>
        <a href="?page=sms-settings&tab=communication" class="nav-tab <?php echo $active_tab === 'communication' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Communication', 'school-management-system'); ?>
        </a>
        <a href="?page=sms-settings&tab=notifications" class="nav-tab <?php echo $active_tab === 'notifications' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Notifications', 'school-management-system'); ?>
        </a>
    </nav>
    
    <div class="sms-settings-content">
        <?php if ($active_tab === 'general'): ?>
            <!-- General Settings -->
            <div class="sms-card">
                <h2><?php _e('General Settings', 'school-management-system'); ?></h2>
                
                <form method="post" action="">
                    <?php wp_nonce_field('sms_settings_action', 'sms_settings_nonce'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="sms_school_name"><?php _e('School Name', 'school-management-system'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="sms_school_name" name="sms_school_name" 
                                       value="<?php echo esc_attr($school_name); ?>" class="regular-text" required>
                                <p class="description"><?php _e('The official name of your school.', 'school-management-system'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="sms_school_address"><?php _e('School Address', 'school-management-system'); ?></label>
                            </th>
                            <td>
                                <textarea id="sms_school_address" name="sms_school_address" 
                                          rows="3" class="large-text"><?php echo esc_textarea($school_address); ?></textarea>
                                <p class="description"><?php _e('The physical address of your school.', 'school-management-system'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="sms_school_phone"><?php _e('School Phone', 'school-management-system'); ?></label>
                            </th>
                            <td>
                                <input type="tel" id="sms_school_phone" name="sms_school_phone" 
                                       value="<?php echo esc_attr($school_phone); ?>" class="regular-text">
                                <p class="description"><?php _e('Main contact phone number for the school.', 'school-management-system'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="sms_school_email"><?php _e('School Email', 'school-management-system'); ?></label>
                            </th>
                            <td>
                                <input type="email" id="sms_school_email" name="sms_school_email" 
                                       value="<?php echo esc_attr($school_email); ?>" class="regular-text">
                                <p class="description"><?php _e('Main contact email address for the school.', 'school-management-system'); ?></p>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button(); ?>
                </form>
            </div>
            
        <?php elseif ($active_tab === 'academic'): ?>
            <!-- Academic Settings -->
            <div class="sms-card">
                <h2><?php _e('Academic Settings', 'school-management-system'); ?></h2>
                
                <form method="post" action="">
                    <?php wp_nonce_field('sms_settings_action', 'sms_settings_nonce'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="sms_academic_year"><?php _e('Current Academic Year', 'school-management-system'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="sms_academic_year" name="sms_academic_year" 
                                       value="<?php echo esc_attr($academic_year); ?>" class="regular-text" 
                                       placeholder="2024-2025">
                                <p class="description"><?php _e('The current academic year (e.g., 2024-2025).', 'school-management-system'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="sms_current_term"><?php _e('Current Term', 'school-management-system'); ?></label>
                            </th>
                            <td>
                                <select id="sms_current_term" name="sms_current_term">
                                    <option value="Term 1" <?php selected($current_term, 'Term 1'); ?>><?php _e('Term 1', 'school-management-system'); ?></option>
                                    <option value="Term 2" <?php selected($current_term, 'Term 2'); ?>><?php _e('Term 2', 'school-management-system'); ?></option>
                                    <option value="Term 3" <?php selected($current_term, 'Term 3'); ?>><?php _e('Term 3', 'school-management-system'); ?></option>
                                </select>
                                <p class="description"><?php _e('The current academic term.', 'school-management-system'); ?></p>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button(); ?>
                </form>
            </div>
            
        <?php elseif ($active_tab === 'payments'): ?>
            <!-- Payment Settings -->
            <div class="sms-card">
                <h2><?php _e('Payment Gateway Settings', 'school-management-system'); ?></h2>
                <p><?php _e('Configure payment gateways to enable online fee payments.', 'school-management-system'); ?></p>
                
                <?php
                // Include required classes
                if (!class_exists('SMS_Gateway_Config_Manager')) {
                    require_once SMS_PLUGIN_DIR . 'includes/financial/class-sms-gateway-config-manager.php';
                }
                
                // Get gateway configurations
                $mpesa_config = array();
                $airtel_config = array();
                
                try {
                    if (class_exists('SMS_Gateway_Config_Manager') && method_exists('SMS_Gateway_Config_Manager', 'get_instance')) {
                        $config_manager = SMS_Gateway_Config_Manager::get_instance();
                        $mpesa_config = $config_manager->get_config('mpesa') ?: array();
                        $airtel_config = $config_manager->get_config('airtel_money') ?: array();
                    }
                } catch (Exception $e) {
                    // Silently handle any initialization issues
                    error_log('SMS Gateway Config Manager initialization failed: ' . $e->getMessage());
                }
                ?>
                
                <div class="payment-gateway-status">
                    <h3><?php _e('Available Payment Gateways', 'school-management-system'); ?></h3>
                    
                    <div class="gateway-list">
                        <div class="gateway-item">
                            <div class="gateway-info">
                                <h4>M-Pesa STK Push</h4>
                                <p><?php _e('Enable M-Pesa mobile payments for fee collection.', 'school-management-system'); ?></p>
                            </div>
                            <div class="gateway-status">
                                <?php 
                                $mpesa_configured = !empty($mpesa_config['consumer_key']);
                                $mpesa_enabled = isset($mpesa_config['enabled']) && $mpesa_config['enabled'];
                                ?>
                                <span class="status-badge <?php echo $mpesa_configured ? 'status-active' : 'status-inactive'; ?>">
                                    <?php echo $mpesa_configured ? __('Configured', 'school-management-system') : __('Not Configured', 'school-management-system'); ?>
                                </span>
                                <?php if ($mpesa_configured && $mpesa_enabled): ?>
                                    <span class="status-badge status-enabled"><?php _e('Enabled', 'school-management-system'); ?></span>
                                <?php endif; ?>
                                <a href="<?php echo admin_url('admin.php?page=sms-payment-gateways&tab=mpesa'); ?>" class="button"><?php _e('Configure', 'school-management-system'); ?></a>
                            </div>
                        </div>
                        
                        <div class="gateway-item">
                            <div class="gateway-info">
                                <h4>Airtel Money</h4>
                                <p><?php _e('Enable Airtel Money payments for fee collection.', 'school-management-system'); ?></p>
                            </div>
                            <div class="gateway-status">
                                <?php 
                                $airtel_configured = !empty($airtel_config['client_id']);
                                $airtel_enabled = isset($airtel_config['enabled']) && $airtel_config['enabled'];
                                ?>
                                <span class="status-badge <?php echo $airtel_configured ? 'status-active' : 'status-inactive'; ?>">
                                    <?php echo $airtel_configured ? __('Configured', 'school-management-system') : __('Not Configured', 'school-management-system'); ?>
                                </span>
                                <?php if ($airtel_configured && $airtel_enabled): ?>
                                    <span class="status-badge status-enabled"><?php _e('Enabled', 'school-management-system'); ?></span>
                                <?php endif; ?>
                                <a href="<?php echo admin_url('admin.php?page=sms-payment-gateways&tab=airtel'); ?>" class="button"><?php _e('Configure', 'school-management-system'); ?></a>
                            </div>
                        </div>
                        
                        <div class="gateway-item">
                            <div class="gateway-info">
                                <h4>Bank Transfer</h4>
                                <p><?php _e('Enable manual bank transfer payments.', 'school-management-system'); ?></p>
                            </div>
                            <div class="gateway-status">
                                <span class="status-badge status-active"><?php _e('Available', 'school-management-system'); ?></span>
                                <button class="button" onclick="alert('Bank transfer settings will be implemented in future updates.')"><?php _e('Configure', 'school-management-system'); ?></button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
        <?php elseif ($active_tab === 'communication'): ?>
            <!-- Communication Settings -->
            <div class="sms-card">
                <h2><?php _e('Communication Settings', 'school-management-system'); ?></h2>
                <p><?php _e('Configure SMS and email communication settings.', 'school-management-system'); ?></p>
                
                <div class="communication-settings">
                    <h3><?php _e('SMS Settings (Africastalking)', 'school-management-system'); ?></h3>
                    <p><?php _e('SMS integration will be implemented in future updates.', 'school-management-system'); ?></p>
                    
                    <div class="setting-item">
                        <label>
                            <input type="checkbox" disabled> <?php _e('Enable SMS Notifications', 'school-management-system'); ?>
                        </label>
                        <p class="description"><?php _e('Send SMS notifications for attendance, fees, and announcements.', 'school-management-system'); ?></p>
                    </div>
                    
                    <h3><?php _e('Email Settings', 'school-management-system'); ?></h3>
                    <p><?php _e('Email notifications use WordPress default email settings.', 'school-management-system'); ?></p>
                    
                    <div class="setting-item">
                        <label>
                            <input type="checkbox" checked disabled> <?php _e('Enable Email Notifications', 'school-management-system'); ?>
                        </label>
                        <p class="description"><?php _e('Send email notifications to parents and administrators.', 'school-management-system'); ?></p>
                    </div>
                </div>
            </div>
            
        <?php elseif ($active_tab === 'notifications'): ?>
            <!-- Notification Settings -->
            <div class="sms-card">
                <h2><?php _e('Notification Settings', 'school-management-system'); ?></h2>
                <p><?php _e('Configure when and how notifications are sent.', 'school-management-system'); ?></p>
                
                <div class="notification-settings">
                    <h3><?php _e('Attendance Notifications', 'school-management-system'); ?></h3>
                    
                    <div class="setting-item">
                        <label>
                            <input type="checkbox" checked disabled> <?php _e('Send absence notifications to parents', 'school-management-system'); ?>
                        </label>
                        <p class="description"><?php _e('Automatically notify parents when their child is marked absent.', 'school-management-system'); ?></p>
                    </div>
                    
                    <div class="setting-item">
                        <label>
                            <input type="checkbox" checked disabled> <?php _e('Send daily attendance summaries to administrators', 'school-management-system'); ?>
                        </label>
                        <p class="description"><?php _e('Send daily attendance reports to school administrators.', 'school-management-system'); ?></p>
                    </div>
                    
                    <div class="setting-item">
                        <label>
                            <input type="checkbox" checked disabled> <?php _e('Send weekly chronic absentee alerts', 'school-management-system'); ?>
                        </label>
                        <p class="description"><?php _e('Alert parents and administrators about students with poor attendance.', 'school-management-system'); ?></p>
                    </div>
                    
                    <h3><?php _e('Fee Notifications', 'school-management-system'); ?></h3>
                    
                    <div class="setting-item">
                        <label>
                            <input type="checkbox" disabled> <?php _e('Send fee due reminders', 'school-management-system'); ?>
                        </label>
                        <p class="description"><?php _e('Remind parents about upcoming fee payments.', 'school-management-system'); ?></p>
                    </div>
                    
                    <div class="setting-item">
                        <label>
                            <input type="checkbox" disabled> <?php _e('Send payment confirmations', 'school-management-system'); ?>
                        </label>
                        <p class="description"><?php _e('Confirm successful fee payments to parents.', 'school-management-system'); ?></p>
                    </div>
                </div>
            </div>
            
        <?php endif; ?>
    </div>
</div>

<style>
.status-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: bold;
    text-transform: uppercase;
    margin-right: 5px;
}

.status-active { 
    background: #d1e7dd; 
    color: #0f5132; 
}

.status-inactive { 
    background: #f8d7da; 
    color: #721c24; 
}

.status-enabled { 
    background: #d1e7dd; 
    color: #0f5132; 
}
</style>

<style>
.sms-settings-content {
    margin-top: 20px;
}

.sms-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    box-shadow: 0 1px 1px rgba(0, 0, 0, 0.04);
}

.sms-card h2, .sms-card h3 {
    margin-top: 0;
}

.gateway-list {
    margin-top: 20px;
}

.gateway-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    border: 1px solid #ddd;
    border-radius: 4px;
    margin-bottom: 10px;
    background: #fafafa;
}

.gateway-info h4 {
    margin: 0 0 5px 0;
}

.gateway-info p {
    margin: 0;
    color: #666;
    font-size: 14px;
}

.gateway-status {
    display: flex;
    align-items: center;
    gap: 10px;
}

.status-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.status-badge.status-active {
    background: #d4edda;
    color: #155724;
}

.status-badge.status-inactive {
    background: #f8d7da;
    color: #721c24;
}

.setting-item {
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid #eee;
}

.setting-item:last-child {
    border-bottom: none;
}

.setting-item label {
    font-weight: 600;
    display: block;
    margin-bottom: 5px;
}

.setting-item input[type="checkbox"] {
    margin-right: 8px;
}

.setting-item .description {
    margin-top: 5px;
    margin-bottom: 0;
}

@media (max-width: 768px) {
    .gateway-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .gateway-status {
        width: 100%;
        justify-content: space-between;
    }
}
</style>