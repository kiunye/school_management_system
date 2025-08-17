<?php
/**
 * Administrator Dashboard Template
 *
 * @package    School_Management_System
 * @subpackage School_Management_System/admin/partials
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Ensure dashboard data is available
if (!isset($dashboard_data)) {
    $dashboard_manager = new SMS_Dashboard_Manager();
    $dashboard_data = $dashboard_manager->get_admin_dashboard_data();
}

$system_stats = $dashboard_data['system_stats'];
$financial_stats = $dashboard_data['financial_stats'];
$gateway_status = $dashboard_data['gateway_status'];
?>

<div class="wrap sms-admin-dashboard">
    <h1><?php _e('Administrator Dashboard', 'school-management-system'); ?></h1>
    
    <!-- System Overview Cards -->
    <div class="sms-dashboard-grid">
        <div class="sms-overview-section">
            <h2><?php _e('System Overview', 'school-management-system'); ?></h2>
            <div class="sms-stats-grid">
                <div class="sms-stat-card students">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-groups"></span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo number_format($system_stats['total_students']); ?></div>
                        <div class="stat-label"><?php _e('Total Students', 'school-management-system'); ?></div>
                    </div>
                    <div class="stat-action">
                        <a href="<?php echo admin_url('edit.php?post_type=sms_students'); ?>" class="button button-small">
                            <?php _e('Manage', 'school-management-system'); ?>
                        </a>
                    </div>
                </div>

                <div class="sms-stat-card teachers">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-admin-users"></span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo number_format($system_stats['total_teachers']); ?></div>
                        <div class="stat-label"><?php _e('Teachers', 'school-management-system'); ?></div>
                    </div>
                    <div class="stat-action">
                        <a href="<?php echo admin_url('users.php?role=sms_teacher'); ?>" class="button button-small">
                            <?php _e('View', 'school-management-system'); ?>
                        </a>
                    </div>
                </div>

                <div class="sms-stat-card parents">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-admin-home"></span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo number_format($system_stats['total_parents']); ?></div>
                        <div class="stat-label"><?php _e('Parents', 'school-management-system'); ?></div>
                    </div>
                    <div class="stat-action">
                        <a href="<?php echo admin_url('users.php?role=sms_parent'); ?>" class="button button-small">
                            <?php _e('View', 'school-management-system'); ?>
                        </a>
                    </div>
                </div>

                <div class="sms-stat-card classes">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-building"></span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo number_format($system_stats['total_classes']); ?></div>
                        <div class="stat-label"><?php _e('Classes', 'school-management-system'); ?></div>
                    </div>
                    <div class="stat-action">
                        <a href="<?php echo admin_url('edit.php?post_type=sms_classes'); ?>" class="button button-small">
                            <?php _e('Manage', 'school-management-system'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Financial Overview -->
        <div class="sms-financial-section">
            <h2><?php _e('Financial Overview', 'school-management-system'); ?></h2>
            <div class="sms-financial-grid">
                <div class="sms-financial-card revenue">
                    <div class="financial-header">
                        <h3><?php _e('Monthly Revenue', 'school-management-system'); ?></h3>
                        <span class="financial-trend up">
                            <span class="dashicons dashicons-arrow-up-alt"></span>
                        </span>
                    </div>
                    <div class="financial-amount">
                        KES <?php echo number_format($financial_stats['total_revenue_month'], 2); ?>
                    </div>
                    <div class="financial-meta">
                        <?php echo date('F Y'); ?>
                    </div>
                </div>

                <div class="sms-financial-card outstanding">
                    <div class="financial-header">
                        <h3><?php _e('Outstanding Fees', 'school-management-system'); ?></h3>
                        <span class="financial-trend">
                            <span class="dashicons dashicons-minus"></span>
                        </span>
                    </div>
                    <div class="financial-amount">
                        KES <?php echo number_format($financial_stats['outstanding_fees'], 2); ?>
                    </div>
                    <div class="financial-meta">
                        <?php echo $system_stats['active_invoices']; ?> <?php _e('pending invoices', 'school-management-system'); ?>
                    </div>
                </div>

                <div class="sms-financial-card success-rate">
                    <div class="financial-header">
                        <h3><?php _e('Payment Success Rate', 'school-management-system'); ?></h3>
                        <span class="financial-trend up">
                            <span class="dashicons dashicons-yes-alt"></span>
                        </span>
                    </div>
                    <div class="financial-amount">
                        <?php echo $financial_stats['payment_success_rate']; ?>%
                    </div>
                    <div class="financial-meta">
                        <?php _e('Top method:', 'school-management-system'); ?> <?php echo $financial_stats['top_payment_method']; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payment Gateway Status -->
        <div class="sms-gateway-section">
            <h2><?php _e('Payment Gateway Status', 'school-management-system'); ?></h2>
            <div class="sms-gateway-grid">
                <?php foreach ($gateway_status as $gateway_key => $gateway): ?>
                    <div class="sms-gateway-card <?php echo $gateway['status']; ?>">
                        <div class="gateway-header">
                            <h3><?php echo esc_html($gateway['name']); ?></h3>
                            <span class="gateway-status <?php echo $gateway['status']; ?>">
                                <?php echo ucfirst($gateway['status']); ?>
                            </span>
                        </div>
                        <div class="gateway-meta">
                            <?php if ($gateway['last_transaction']): ?>
                                <?php _e('Last transaction:', 'school-management-system'); ?>
                                <?php echo human_time_diff(strtotime($gateway['last_transaction']), current_time('timestamp')); ?> <?php _e('ago', 'school-management-system'); ?>
                            <?php else: ?>
                                <?php _e('No recent transactions', 'school-management-system'); ?>
                            <?php endif; ?>
                        </div>
                        <div class="gateway-actions">
                            <a href="<?php echo admin_url('admin.php?page=sms-payment-gateways&gateway=' . $gateway_key); ?>" class="button button-small">
                                <?php _e('Configure', 'school-management-system'); ?>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="sms-quick-actions-section">
            <h2><?php _e('Quick Actions', 'school-management-system'); ?></h2>
            <div class="sms-actions-grid">
                <a href="<?php echo admin_url('post-new.php?post_type=sms_students'); ?>" class="sms-action-btn primary">
                    <span class="dashicons dashicons-plus-alt"></span>
                    <?php _e('Add Student', 'school-management-system'); ?>
                </a>
                <a href="<?php echo admin_url('post-new.php?post_type=sms_classes'); ?>" class="sms-action-btn secondary">
                    <span class="dashicons dashicons-building"></span>
                    <?php _e('Create Class', 'school-management-system'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=sms-bulk-invoice-generator'); ?>" class="sms-action-btn success">
                    <span class="dashicons dashicons-money-alt"></span>
                    <?php _e('Generate Invoices', 'school-management-system'); ?>
                </a>
                <a href="<?php echo admin_url('post-new.php?post_type=sms_notices'); ?>" class="sms-action-btn info">
                    <span class="dashicons dashicons-megaphone"></span>
                    <?php _e('Send Notice', 'school-management-system'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=sms-communication'); ?>" class="sms-action-btn warning">
                    <span class="dashicons dashicons-email-alt"></span>
                    <?php _e('Send SMS', 'school-management-system'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=sms-reports'); ?>" class="sms-action-btn dark">
                    <span class="dashicons dashicons-chart-bar"></span>
                    <?php _e('View Reports', 'school-management-system'); ?>
                </a>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="sms-activity-section">
            <h2><?php _e('Recent System Activity', 'school-management-system'); ?></h2>
            <div class="sms-activity-container">
                <div id="sms-recent-activity-admin">
                    <div class="loading-spinner">
                        <span class="dashicons dashicons-update spin"></span>
                        <?php _e('Loading recent activity...', 'school-management-system'); ?>
                    </div>
                </div>
                <div class="activity-actions">
                    <a href="<?php echo admin_url('admin.php?page=sms-admin-dashboard&view=activity-log'); ?>" class="button">
                        <?php _e('View Full Activity Log', 'school-management-system'); ?>
                    </a>
                </div>
            </div>
        </div>

        <!-- System Health -->
        <div class="sms-health-section">
            <h2><?php _e('System Health', 'school-management-system'); ?></h2>
            <div class="sms-health-grid">
                <div class="health-item">
                    <div class="health-icon success">
                        <span class="dashicons dashicons-yes-alt"></span>
                    </div>
                    <div class="health-content">
                        <div class="health-title"><?php _e('Database', 'school-management-system'); ?></div>
                        <div class="health-status"><?php _e('Operational', 'school-management-system'); ?></div>
                    </div>
                </div>

                <div class="health-item">
                    <div class="health-icon success">
                        <span class="dashicons dashicons-yes-alt"></span>
                    </div>
                    <div class="health-content">
                        <div class="health-title"><?php _e('SMS Service', 'school-management-system'); ?></div>
                        <div class="health-status">
                            <?php echo $system_stats['sms_sent_today']; ?> <?php _e('sent today', 'school-management-system'); ?>
                        </div>
                    </div>
                </div>

                <div class="health-item">
                    <div class="health-icon success">
                        <span class="dashicons dashicons-yes-alt"></span>
                    </div>
                    <div class="health-content">
                        <div class="health-title"><?php _e('System Uptime', 'school-management-system'); ?></div>
                        <div class="health-status"><?php echo $system_stats['system_uptime']; ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Load recent activity
    loadRecentActivity();
    
    // Refresh dashboard data every 5 minutes
    setInterval(function() {
        refreshDashboardData();
    }, 300000);
    
    function loadRecentActivity() {
        $.post(ajaxurl, {
            action: 'sms_get_recent_activity',
            nonce: '<?php echo wp_create_nonce('sms_admin_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                $('#sms-recent-activity-admin').html(response.data.html);
            } else {
                $('#sms-recent-activity-admin').html('<p><?php _e('Unable to load recent activity.', 'school-management-system'); ?></p>');
            }
        }).fail(function() {
            $('#sms-recent-activity-admin').html('<p><?php _e('Error loading recent activity.', 'school-management-system'); ?></p>');
        });
    }
    
    function refreshDashboardData() {
        $.post(ajaxurl, {
            action: 'sms_get_dashboard_data',
            dashboard_type: 'admin',
            nonce: '<?php echo wp_create_nonce('sms_admin_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                // Update dashboard with fresh data
                updateDashboardStats(response.data);
            }
        });
    }
    
    function updateDashboardStats(data) {
        // Update system stats
        $('.sms-stat-card.students .stat-number').text(data.system_stats.total_students.toLocaleString());
        $('.sms-stat-card.teachers .stat-number').text(data.system_stats.total_teachers.toLocaleString());
        $('.sms-stat-card.parents .stat-number').text(data.system_stats.total_parents.toLocaleString());
        $('.sms-stat-card.classes .stat-number').text(data.system_stats.total_classes.toLocaleString());
        
        // Update financial stats
        $('.sms-financial-card.revenue .financial-amount').text('KES ' + data.financial_stats.total_revenue_month.toLocaleString());
        $('.sms-financial-card.outstanding .financial-amount').text('KES ' + data.financial_stats.outstanding_fees.toLocaleString());
        $('.sms-financial-card.success-rate .financial-amount').text(data.financial_stats.payment_success_rate + '%');
    }
});
</script>