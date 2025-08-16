<?php
/**
 * Fees & Payments Management Page
 *
 * @package SchoolManagementSystem
 * @subpackage Admin/Partials
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

?>
<div class="wrap">
    <h1><?php _e('Fees & Payments Management', 'school-management-system'); ?></h1>
    
    <div class="sms-admin-content">
        <div class="sms-card">
            <h2><?php _e('Manage Fees & Payments', 'school-management-system'); ?></h2>
            <p><?php _e('This page will contain the fees and payments management interface. For now, you can manage fees through the WordPress admin menu.', 'school-management-system'); ?></p>
            
            <div class="sms-quick-links">
                <a href="<?php echo admin_url('edit.php?post_type=sms_fees'); ?>" class="button button-primary">
                    <?php _e('View All Fees', 'school-management-system'); ?>
                </a>
                <a href="<?php echo admin_url('post-new.php?post_type=sms_fees'); ?>" class="button">
                    <?php _e('Add New Fee', 'school-management-system'); ?>
                </a>
                <a href="<?php echo admin_url('edit.php?post_type=sms_invoices'); ?>" class="button">
                    <?php _e('View Invoices', 'school-management-system'); ?>
                </a>
                <a href="<?php echo admin_url('edit.php?post_type=sms_transactions'); ?>" class="button">
                    <?php _e('View Transactions', 'school-management-system'); ?>
                </a>
            </div>
        </div>
        
        <div class="sms-card">
            <h3><?php _e('Financial Overview', 'school-management-system'); ?></h3>
            <?php
            // Get financial statistics
            $total_fees = wp_count_posts('sms_fees');
            $total_invoices = wp_count_posts('sms_invoices');
            $total_transactions = wp_count_posts('sms_transactions');
            
            // Calculate pending payments (this would need more complex logic in real implementation)
            $pending_invoices = get_posts(array(
                'post_type' => 'sms_invoices',
                'posts_per_page' => -1,
                'meta_query' => array(
                    array(
                        'key' => 'invoice_status',
                        'value' => 'pending',
                        'compare' => '='
                    )
                ),
                'fields' => 'ids'
            ));
            ?>
            <div class="sms-stats-grid">
                <div class="stat-item">
                    <div class="stat-number"><?php echo $total_fees->publish; ?></div>
                    <div class="stat-label"><?php _e('Fee Structures', 'school-management-system'); ?></div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo $total_invoices->publish; ?></div>
                    <div class="stat-label"><?php _e('Total Invoices', 'school-management-system'); ?></div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo count($pending_invoices); ?></div>
                    <div class="stat-label"><?php _e('Pending Payments', 'school-management-system'); ?></div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo $total_transactions->publish; ?></div>
                    <div class="stat-label"><?php _e('Transactions', 'school-management-system'); ?></div>
                </div>
            </div>
        </div>
        
        <div class="sms-card">
            <h3><?php _e('Payment Gateways', 'school-management-system'); ?></h3>
            <p><?php _e('Configure payment gateway settings to enable online payments.', 'school-management-system'); ?></p>
            
            <div class="payment-gateways">
                <div class="gateway-item">
                    <div class="gateway-icon">📱</div>
                    <div class="gateway-info">
                        <h4>M-Pesa</h4>
                        <p><?php _e('Enable M-Pesa STK Push payments', 'school-management-system'); ?></p>
                        <span class="status-badge status-inactive"><?php _e('Not Configured', 'school-management-system'); ?></span>
                    </div>
                </div>
                
                <div class="gateway-item">
                    <div class="gateway-icon">💳</div>
                    <div class="gateway-info">
                        <h4>Airtel Money</h4>
                        <p><?php _e('Enable Airtel Money payments', 'school-management-system'); ?></p>
                        <span class="status-badge status-inactive"><?php _e('Not Configured', 'school-management-system'); ?></span>
                    </div>
                </div>
                
                <div class="gateway-item">
                    <div class="gateway-icon">🏦</div>
                    <div class="gateway-info">
                        <h4>Bank Transfer</h4>
                        <p><?php _e('Enable bank transfer payments', 'school-management-system'); ?></p>
                        <span class="status-badge status-active"><?php _e('Available', 'school-management-system'); ?></span>
                    </div>
                </div>
            </div>
            
            <div class="gateway-actions">
                <a href="<?php echo admin_url('admin.php?page=sms-payment-gateways'); ?>" class="button button-primary">
                    <?php _e('Configure Payment Gateways', 'school-management-system'); ?>
                </a>
            </div>
        </div>
    </div>
</div>

<style>
.sms-admin-content {
    display: grid;
    gap: 20px;
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

.sms-quick-links {
    margin-top: 15px;
}

.sms-quick-links .button {
    margin-right: 10px;
    margin-bottom: 5px;
}

.sms-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 20px;
    margin-top: 15px;
}

.stat-item {
    text-align: center;
    padding: 15px;
    background: #f9f9f9;
    border-radius: 4px;
}

.stat-number {
    font-size: 32px;
    font-weight: bold;
    color: #0073aa;
}

.stat-label {
    font-size: 14px;
    color: #666;
    margin-top: 5px;
}

.payment-gateways {
    display: grid;
    gap: 15px;
    margin-top: 15px;
}

.gateway-item {
    display: flex;
    align-items: center;
    padding: 15px;
    border: 1px solid #ddd;
    border-radius: 4px;
    background: #fafafa;
}

.gateway-icon {
    font-size: 32px;
    margin-right: 15px;
}

.gateway-info {
    flex: 1;
}

.gateway-info h4 {
    margin: 0 0 5px 0;
    font-size: 16px;
}

.gateway-info p {
    margin: 0 0 10px 0;
    color: #666;
    font-size: 14px;
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

.gateway-actions {
    margin-top: 20px;
    text-align: center;
}
</style>