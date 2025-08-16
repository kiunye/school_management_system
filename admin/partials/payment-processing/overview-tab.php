<?php
/**
 * Payment Processing Overview Tab
 *
 * Displays payment processing dashboard with key metrics,
 * recent activities, and quick actions.
 *
 * @package    School_Management_System
 * @subpackage School_Management_System/admin/partials/payment-processing
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Get dashboard metrics
$today = current_time('Y-m-d');
$this_month = current_time('Y-m');
$last_month = date('Y-m', strtotime('-1 month'));

// Payment statistics
$today_payments = $history_tracker->generate_payment_history_report([
    'date_from' => $today,
    'date_to' => $today
]);

$month_payments = $history_tracker->generate_payment_history_report([
    'date_from' => $this_month . '-01',
    'date_to' => date('Y-m-t', strtotime($this_month . '-01'))
]);

$last_month_payments = $history_tracker->generate_payment_history_report([
    'date_from' => $last_month . '-01',
    'date_to' => date('Y-m-t', strtotime($last_month . '-01'))
]);

// Overdue statistics
$overdue_count = count($payment_processor->identify_overdue_payments());
$overdue_invoices = get_posts([
    'post_type' => 'sms_invoices',
    'meta_query' => [
        'relation' => 'AND',
        [
            'key' => 'due_date',
            'value' => $today,
            'compare' => '<',
            'type' => 'DATE'
        ],
        [
            'key' => 'balance_due',
            'value' => 0,
            'compare' => '>',
            'type' => 'NUMERIC'
        ]
    ],
    'posts_per_page' => -1
]);

$overdue_amount = 0;
foreach ($overdue_invoices as $invoice) {
    $overdue_amount += floatval(get_field('balance_due', $invoice->ID));
}

// Recent payment activities
$recent_payments = $history_tracker->generate_payment_history_report([
    'date_from' => date('Y-m-d', strtotime('-7 days')),
    'date_to' => $today
]);

// Reminder statistics
$reminder_stats = $reminder_scheduler->get_reminder_statistics();

// Calculate percentage changes
$payment_change = 0;
if ($last_month_payments['summary']['total_amount'] > 0) {
    $payment_change = (($month_payments['summary']['total_amount'] - $last_month_payments['summary']['total_amount']) / $last_month_payments['summary']['total_amount']) * 100;
}

$collection_rate = 0;
$total_invoices = get_posts([
    'post_type' => 'sms_invoices',
    'meta_query' => [
        [
            'key' => 'invoice_date',
            'value' => $this_month,
            'compare' => 'LIKE'
        ]
    ],
    'posts_per_page' => -1
]);

$paid_invoices = get_posts([
    'post_type' => 'sms_invoices',
    'meta_query' => [
        'relation' => 'AND',
        [
            'key' => 'invoice_date',
            'value' => $this_month,
            'compare' => 'LIKE'
        ],
        [
            'key' => 'payment_status',
            'value' => 'paid',
            'compare' => '='
        ]
    ],
    'posts_per_page' => -1
]);

if (count($total_invoices) > 0) {
    $collection_rate = (count($paid_invoices) / count($total_invoices)) * 100;
}
?>

<div class="overview-dashboard">
    <!-- Key Metrics -->
    <div class="payment-stats-grid">
        <div class="stat-card">
            <h3>Today's Payments</h3>
            <div class="stat-value">KES <?php echo number_format($today_payments['summary']['total_amount'], 2); ?></div>
            <div class="stat-change">
                <?php echo $today_payments['summary']['total_payments']; ?> transactions
            </div>
        </div>
        
        <div class="stat-card">
            <h3>This Month</h3>
            <div class="stat-value">KES <?php echo number_format($month_payments['summary']['total_amount'], 2); ?></div>
            <div class="stat-change <?php echo $payment_change >= 0 ? 'positive' : 'negative'; ?>">
                <?php echo $payment_change >= 0 ? '+' : ''; ?><?php echo number_format($payment_change, 1); ?>% vs last month
            </div>
        </div>
        
        <div class="stat-card">
            <h3>Overdue Payments</h3>
            <div class="stat-value"><?php echo $overdue_count; ?></div>
            <div class="stat-change">
                KES <?php echo number_format($overdue_amount, 2); ?> total
            </div>
        </div>
        
        <div class="stat-card">
            <h3>Collection Rate</h3>
            <div class="stat-value"><?php echo number_format($collection_rate, 1); ?>%</div>
            <div class="stat-change">
                <?php echo count($paid_invoices); ?> of <?php echo count($total_invoices); ?> invoices paid
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="action-buttons">
        <form method="post" style="display: inline;">
            <?php wp_nonce_field('process_overdue_payments'); ?>
            <input type="hidden" name="action" value="process_overdue_payments">
            <button type="submit" class="btn btn-primary">
                Process Overdue Payments
            </button>
        </form>
        
        <button type="button" class="btn btn-secondary" onclick="sendBulkReminders()">
            Send Payment Reminders
        </button>
        
        <a href="?page=sms-payment-processing&tab=history" class="btn btn-secondary">
            View Payment History
        </a>
        
        <button type="button" class="btn btn-secondary" onclick="exportPaymentReport()">
            Export Report
        </button>
    </div>
    
    <!-- Payment Method Breakdown -->
    <?php if (!empty($month_payments['summary']['payment_methods'])): ?>
    <div class="payment-methods-section">
        <h3>Payment Methods This Month</h3>
        <div class="payment-stats-grid">
            <?php foreach ($month_payments['summary']['payment_methods'] as $method => $data): ?>
            <div class="stat-card">
                <h4><?php echo ucfirst(str_replace('_', ' ', $method)); ?></h4>
                <div class="stat-value"><?php echo $data['count']; ?></div>
                <div class="stat-change">
                    KES <?php echo number_format($data['amount'], 2); ?> 
                    (<?php echo number_format($data['percentage'], 1); ?>%)
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Recent Payment Activities -->
    <div class="recent-activities-section">
        <h3>Recent Payment Activities (Last 7 Days)</h3>
        
        <?php if (!empty($recent_payments['payments'])): ?>
        <table class="payment-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Student</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Invoice</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $recent_payments_limited = array_slice($recent_payments['payments'], 0, 10);
                foreach ($recent_payments_limited as $payment): 
                ?>
                <tr>
                    <td><?php echo date('M j, Y', strtotime($payment['payment_date'])); ?></td>
                    <td>
                        <strong><?php echo esc_html($payment['student_name']); ?></strong><br>
                        <small><?php echo esc_html($payment['admission_number']); ?></small>
                    </td>
                    <td>KES <?php echo number_format($payment['amount'], 2); ?></td>
                    <td><?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?></td>
                    <td>
                        <?php if ($payment['invoice_number']): ?>
                        <a href="<?php echo admin_url('post.php?post=' . $payment['invoice_id'] . '&action=edit'); ?>">
                            #<?php echo esc_html($payment['invoice_number']); ?>
                        </a>
                        <?php else: ?>
                        <em>Direct Payment</em>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="status-badge status-<?php echo esc_attr($payment['transaction_status']); ?>">
                            <?php echo ucfirst($payment['transaction_status']); ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <?php if (count($recent_payments['payments']) > 10): ?>
        <p>
            <a href="?page=sms-payment-processing&tab=history">
                View all <?php echo count($recent_payments['payments']); ?> recent payments →
            </a>
        </p>
        <?php endif; ?>
        
        <?php else: ?>
        <p>No recent payment activities found.</p>
        <?php endif; ?>
    </div>
    
    <!-- Overdue Payments Summary -->
    <?php if ($overdue_count > 0): ?>
    <div class="overdue-summary-section">
        <h3>Overdue Payments Summary</h3>
        <div class="alert alert-warning">
            <p>
                <strong><?php echo $overdue_count; ?> invoices are overdue</strong> with a total amount of 
                <strong>KES <?php echo number_format($overdue_amount, 2); ?></strong>.
            </p>
            <p>
                <a href="?page=sms-payment-processing&tab=overdue" class="btn btn-primary">
                    Manage Overdue Payments
                </a>
            </p>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Reminder Statistics -->
    <div class="reminder-stats-section">
        <h3>Payment Reminder Statistics (This Month)</h3>
        <div class="payment-stats-grid">
            <div class="stat-card">
                <h4>Upcoming Reminders</h4>
                <div class="stat-value"><?php echo $reminder_stats['upcoming'] ?? 0; ?></div>
                <div class="stat-change">Sent this month</div>
            </div>
            
            <div class="stat-card">
                <h4>Due Reminders</h4>
                <div class="stat-value"><?php echo $reminder_stats['due'] ?? 0; ?></div>
                <div class="stat-change">Sent this month</div>
            </div>
            
            <div class="stat-card">
                <h4>Overdue Reminders</h4>
                <div class="stat-value"><?php echo $reminder_stats['overdue'] ?? 0; ?></div>
                <div class="stat-change">Sent this month</div>
            </div>
            
            <div class="stat-card">
                <h4>Final Notices</h4>
                <div class="stat-value"><?php echo $reminder_stats['final'] ?? 0; ?></div>
                <div class="stat-change">Sent this month</div>
            </div>
        </div>
    </div>
    
    <!-- System Status -->
    <div class="system-status-section">
        <h3>System Status</h3>
        <div class="status-grid">
            <div class="status-item">
                <span class="status-label">Payment Processing:</span>
                <span class="status-value status-active">Active</span>
            </div>
            
            <div class="status-item">
                <span class="status-label">Reminder System:</span>
                <span class="status-value status-active">Active</span>
            </div>
            
            <div class="status-item">
                <span class="status-label">Last Processing:</span>
                <span class="status-value">
                    <?php 
                    $last_processing = get_option('sms_last_reminder_processing');
                    echo $last_processing ? date('M j, Y H:i', strtotime($last_processing['start_time'])) : 'Never';
                    ?>
                </span>
            </div>
            
            <div class="status-item">
                <span class="status-label">Next Scheduled:</span>
                <span class="status-value">
                    <?php 
                    $next_scheduled = wp_next_scheduled('sms_process_payment_reminders');
                    echo $next_scheduled ? date('M j, Y H:i', $next_scheduled) : 'Not scheduled';
                    ?>
                </span>
            </div>
        </div>
    </div>
</div>

<style>
.overview-dashboard {
    margin: 20px 0;
}

.overview-dashboard h3 {
    margin: 30px 0 15px 0;
    color: #23282d;
    border-bottom: 2px solid #0073aa;
    padding-bottom: 5px;
}

.payment-methods-section,
.recent-activities-section,
.overdue-summary-section,
.reminder-stats-section,
.system-status-section {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin: 20px 0;
}

.alert {
    padding: 15px;
    border-radius: 4px;
    margin: 15px 0;
}

.alert-warning {
    background-color: #fff3cd;
    border: 1px solid #ffeaa7;
    color: #856404;
}

.status-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
}

.status-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px;
    background: #f9f9f9;
    border-radius: 4px;
}

.status-label {
    font-weight: 600;
    color: #666;
}

.status-value {
    font-weight: bold;
}

.status-active {
    color: #46b450;
}

.status-inactive {
    color: #dc3232;
}

@media (max-width: 768px) {
    .status-grid {
        grid-template-columns: 1fr;
    }
    
    .status-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 5px;
    }
}
</style>

<script>
function sendBulkReminders() {
    if (confirm('Send payment reminders to all students with overdue payments?')) {
        // This would trigger the bulk reminder functionality
        var form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="send_bulk_reminders">
            <input type="hidden" name="_wpnonce" value="<?php echo wp_create_nonce('send_bulk_reminders'); ?>">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function exportPaymentReport() {
    var format = prompt('Export format (csv, excel, pdf):', 'csv');
    if (format && ['csv', 'excel', 'pdf'].includes(format.toLowerCase())) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = ajaxurl;
        form.innerHTML = `
            <input type="hidden" name="action" value="sms_export_payment_history">
            <input type="hidden" name="format" value="${format.toLowerCase()}">
            <input type="hidden" name="filters" value='{"date_from":"<?php echo $this_month; ?>-01","date_to":"<?php echo date('Y-m-t', strtotime($this_month . '-01')); ?>"}'>
            <input type="hidden" name="_wpnonce" value="<?php echo wp_create_nonce('export_payment_history'); ?>">
        `;
        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    }
}

// Auto-refresh dashboard every 5 minutes
setInterval(function() {
    if (document.visibilityState === 'visible') {
        location.reload();
    }
}, 300000);
</script>