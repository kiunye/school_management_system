<?php
/**
 * Financial Reports Admin Interface
 *
 * @package    School_Management_System
 * @subpackage School_Management_System/admin/partials
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Get current user capabilities
$can_view_reports = current_user_can('manage_financial_reports') || current_user_can('manage_options');

if (!$can_view_reports) {
    wp_die(__('You do not have sufficient permissions to access this page.', 'school-management-system'));
}

// Initialize financial reporter
$financial_reporter = new SMS_Financial_Reporter();

// Handle report generation
$current_report = null;
$report_error = null;

if (isset($_POST['generate_report']) && wp_verify_nonce($_POST['financial_report_nonce'], 'generate_financial_report')) {
    $report_type = sanitize_text_field($_POST['report_type']);
    $date_range = [
        'start' => sanitize_text_field($_POST['start_date']),
        'end' => sanitize_text_field($_POST['end_date'])
    ];
    
    $filters = [];
    if (!empty($_POST['payment_method'])) {
        $filters['payment_method'] = sanitize_text_field($_POST['payment_method']);
    }
    if (!empty($_POST['class_filter'])) {
        $filters['class_id'] = intval($_POST['class_filter']);
    }
    if (!empty($_POST['fee_type_filter'])) {
        $filters['fee_type'] = sanitize_text_field($_POST['fee_type_filter']);
    }
    
    $current_report = $financial_reporter->generate_report($report_type, $date_range, $filters);
    
    if (is_wp_error($current_report)) {
        $report_error = $current_report->get_error_message();
        $current_report = null;
    }
}

// Handle report export
if (isset($_POST['export_report']) && wp_verify_nonce($_POST['export_report_nonce'], 'export_financial_report')) {
    $export_format = sanitize_text_field($_POST['export_format']);
    $report_data = json_decode(stripslashes($_POST['report_data']), true);
    
    if ($report_data) {
        $filename = 'financial_report_' . date('Y-m-d_H-i-s');
        $export_result = $financial_reporter->export_report($report_data, $export_format, $filename);
        
        if (!is_wp_error($export_result)) {
            // Trigger download
            $upload_dir = wp_upload_dir();
            $file_url = str_replace($upload_dir['path'], $upload_dir['url'], $export_result);
            echo '<script>window.open("' . esc_url($file_url) . '", "_blank");</script>';
        }
    }
}

// Get available classes for filter
$classes = get_posts([
    'post_type' => 'sms_classes',
    'post_status' => 'publish',
    'posts_per_page' => -1,
    'orderby' => 'title',
    'order' => 'ASC'
]);

// Get available fee types
$fee_types = get_terms([
    'taxonomy' => 'sms_fee_types',
    'hide_empty' => false
]);
?>

<div class="wrap">
    <h1><?php _e('Financial Reports', 'school-management-system'); ?></h1>
    
    <?php if ($report_error): ?>
        <div class="notice notice-error">
            <p><?php echo esc_html($report_error); ?></p>
        </div>
    <?php endif; ?>
    
    <div class="sms-financial-reports">
        <!-- Report Generation Form -->
        <div class="sms-report-generator">
            <h2><?php _e('Generate Financial Report', 'school-management-system'); ?></h2>
            
            <form method="post" action="" class="sms-report-form">
                <?php wp_nonce_field('generate_financial_report', 'financial_report_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="report_type"><?php _e('Report Type', 'school-management-system'); ?></label>
                        </th>
                        <td>
                            <select name="report_type" id="report_type" class="regular-text" required>
                                <option value=""><?php _e('Select Report Type', 'school-management-system'); ?></option>
                                <option value="income"><?php _e('Income Report', 'school-management-system'); ?></option>
                                <option value="expense"><?php _e('Expense Report', 'school-management-system'); ?></option>
                                <option value="cash_flow"><?php _e('Cash Flow Report', 'school-management-system'); ?></option>
                                <option value="collection_status"><?php _e('Fee Collection Status', 'school-management-system'); ?></option>
                                <option value="outstanding_amounts"><?php _e('Outstanding Amounts', 'school-management-system'); ?></option>
                                <option value="invoice_tracking"><?php _e('Invoice Tracking', 'school-management-system'); ?></option>
                                <option value="payment_trends"><?php _e('Payment Trends', 'school-management-system'); ?></option>
                                <option value="gateway_transactions"><?php _e('Gateway Transactions', 'school-management-system'); ?></option>
                                <option value="reconciliation"><?php _e('Reconciliation Report', 'school-management-system'); ?></option>
                            </select>
                            <p class="description"><?php _e('Select the type of financial report to generate.', 'school-management-system'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="start_date"><?php _e('Date Range', 'school-management-system'); ?></label>
                        </th>
                        <td>
                            <input type="date" name="start_date" id="start_date" class="regular-text" 
                                   value="<?php echo date('Y-m-01'); ?>" required>
                            <span><?php _e('to', 'school-management-system'); ?></span>
                            <input type="date" name="end_date" id="end_date" class="regular-text" 
                                   value="<?php echo date('Y-m-t'); ?>" required>
                            <p class="description"><?php _e('Select the date range for the report.', 'school-management-system'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="payment_method"><?php _e('Payment Method', 'school-management-system'); ?></label>
                        </th>
                        <td>
                            <select name="payment_method" id="payment_method" class="regular-text">
                                <option value=""><?php _e('All Payment Methods', 'school-management-system'); ?></option>
                                <option value="mpesa"><?php _e('M-Pesa', 'school-management-system'); ?></option>
                                <option value="airtel_money"><?php _e('Airtel Money', 'school-management-system'); ?></option>
                                <option value="cash"><?php _e('Cash', 'school-management-system'); ?></option>
                                <option value="bank_transfer"><?php _e('Bank Transfer', 'school-management-system'); ?></option>
                            </select>
                            <p class="description"><?php _e('Filter by payment method (optional).', 'school-management-system'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="class_filter"><?php _e('Class', 'school-management-system'); ?></label>
                        </th>
                        <td>
                            <select name="class_filter" id="class_filter" class="regular-text">
                                <option value=""><?php _e('All Classes', 'school-management-system'); ?></option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?php echo esc_attr($class->ID); ?>">
                                        <?php echo esc_html($class->post_title); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php _e('Filter by class (optional).', 'school-management-system'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="fee_type_filter"><?php _e('Fee Type', 'school-management-system'); ?></label>
                        </th>
                        <td>
                            <select name="fee_type_filter" id="fee_type_filter" class="regular-text">
                                <option value=""><?php _e('All Fee Types', 'school-management-system'); ?></option>
                                <?php if ($fee_types): ?>
                                    <?php foreach ($fee_types as $fee_type): ?>
                                        <option value="<?php echo esc_attr($fee_type->slug); ?>">
                                            <?php echo esc_html($fee_type->name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <p class="description"><?php _e('Filter by fee type (optional).', 'school-management-system'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="generate_report" class="button-primary" 
                           value="<?php _e('Generate Report', 'school-management-system'); ?>">
                </p>
            </form>
        </div>
        
        <?php if ($current_report): ?>
            <!-- Report Display -->
            <div class="sms-report-display">
                <div class="sms-report-header">
                    <h2><?php echo esc_html(ucfirst(str_replace('_', ' ', $current_report['metadata']['report_type']))); ?> Report</h2>
                    <div class="sms-report-meta">
                        <span><?php _e('Period:', 'school-management-system'); ?> 
                              <?php echo esc_html($current_report['metadata']['date_range']['start']); ?> - 
                              <?php echo esc_html($current_report['metadata']['date_range']['end']); ?></span>
                        <span><?php _e('Generated:', 'school-management-system'); ?> 
                              <?php echo esc_html($current_report['metadata']['generated_at']); ?></span>
                    </div>
                </div>
                
                <!-- Export Options -->
                <div class="sms-report-export">
                    <form method="post" action="" style="display: inline;">
                        <?php wp_nonce_field('export_financial_report', 'export_report_nonce'); ?>
                        <input type="hidden" name="report_data" value="<?php echo esc_attr(json_encode($current_report)); ?>">
                        
                        <select name="export_format" required>
                            <option value="pdf"><?php _e('PDF', 'school-management-system'); ?></option>
                            <option value="excel"><?php _e('Excel', 'school-management-system'); ?></option>
                            <option value="csv"><?php _e('CSV', 'school-management-system'); ?></option>
                        </select>
                        
                        <input type="submit" name="export_report" class="button" 
                               value="<?php _e('Export Report', 'school-management-system'); ?>">
                    </form>
                </div>
                
                <!-- Summary Section -->
                <?php if (isset($current_report['summary'])): ?>
                    <div class="sms-report-summary">
                        <h3><?php _e('Summary', 'school-management-system'); ?></h3>
                        <div class="sms-summary-cards">
                            <?php foreach ($current_report['summary'] as $key => $value): ?>
                                <?php if (is_numeric($value)): ?>
                                    <div class="sms-summary-card">
                                        <div class="sms-summary-label">
                                            <?php echo esc_html(ucfirst(str_replace('_', ' ', $key))); ?>
                                        </div>
                                        <div class="sms-summary-value">
                                            <?php if (strpos($key, 'amount') !== false || strpos($key, 'income') !== false || strpos($key, 'expense') !== false): ?>
                                                KES <?php echo number_format($value, 2); ?>
                                            <?php elseif (strpos($key, 'rate') !== false): ?>
                                                <?php echo number_format($value, 1); ?>%
                                            <?php else: ?>
                                                <?php echo number_format($value); ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Breakdown Section -->
                <?php if (isset($current_report['breakdown'])): ?>
                    <div class="sms-report-breakdown">
                        <h3><?php _e('Breakdown', 'school-management-system'); ?></h3>
                        
                        <?php foreach ($current_report['breakdown'] as $breakdown_type => $breakdown_data): ?>
                            <div class="sms-breakdown-section">
                                <h4><?php echo esc_html(ucfirst(str_replace('_', ' ', $breakdown_type))); ?></h4>
                                
                                <table class="wp-list-table widefat fixed striped">
                                    <thead>
                                        <tr>
                                            <th><?php _e('Name', 'school-management-system'); ?></th>
                                            <th><?php _e('Amount', 'school-management-system'); ?></th>
                                            <th><?php _e('Count', 'school-management-system'); ?></th>
                                            <th><?php _e('Percentage', 'school-management-system'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $total_amount = array_sum(array_column($breakdown_data, 'amount'));
                                        foreach ($breakdown_data as $item): 
                                            $percentage = $total_amount > 0 ? ($item['amount'] / $total_amount) * 100 : 0;
                                        ?>
                                            <tr>
                                                <td><?php echo esc_html($item['name']); ?></td>
                                                <td>KES <?php echo number_format($item['amount'], 2); ?></td>
                                                <td><?php echo number_format($item['count'] ?? 0); ?></td>
                                                <td><?php echo number_format($percentage, 1); ?>%</td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Trends Section -->
                <?php if (isset($current_report['trends'])): ?>
                    <div class="sms-report-trends">
                        <h3><?php _e('Trends', 'school-management-system'); ?></h3>
                        
                        <?php foreach ($current_report['trends'] as $trend_type => $trend_data): ?>
                            <div class="sms-trend-section">
                                <h4><?php echo esc_html(ucfirst(str_replace('_', ' ', $trend_type))); ?></h4>
                                
                                <div class="sms-trend-chart" data-chart-type="line" data-chart-data="<?php echo esc_attr(json_encode($trend_data)); ?>">
                                    <!-- Chart will be rendered here by JavaScript -->
                                    <canvas id="chart-<?php echo esc_attr($trend_type); ?>"></canvas>
                                </div>
                                
                                <table class="wp-list-table widefat fixed striped">
                                    <thead>
                                        <tr>
                                            <th><?php _e('Period', 'school-management-system'); ?></th>
                                            <th><?php _e('Amount', 'school-management-system'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($trend_data as $period => $amount): ?>
                                            <tr>
                                                <td><?php echo esc_html($period); ?></td>
                                                <td>KES <?php echo number_format($amount, 2); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Visualizations Section -->
                <?php if (isset($current_report['visualizations'])): ?>
                    <div class="sms-report-visualizations">
                        <h3><?php _e('Charts', 'school-management-system'); ?></h3>
                        
                        <div class="sms-charts-grid">
                            <?php foreach ($current_report['visualizations'] as $chart_name => $chart_data): ?>
                                <div class="sms-chart-container">
                                    <h4><?php echo esc_html(ucfirst(str_replace('_', ' ', $chart_name))); ?></h4>
                                    <canvas id="chart-<?php echo esc_attr($chart_name); ?>" 
                                            data-chart-data="<?php echo esc_attr(json_encode($chart_data)); ?>"></canvas>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.sms-financial-reports {
    max-width: 1200px;
}

.sms-report-generator {
    background: #fff;
    padding: 20px;
    margin-bottom: 20px;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.sms-report-display {
    background: #fff;
    padding: 20px;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.sms-report-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid #eee;
}

.sms-report-meta span {
    margin-left: 15px;
    color: #666;
    font-size: 14px;
}

.sms-report-export {
    margin-bottom: 20px;
    text-align: right;
}

.sms-summary-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 30px;
}

.sms-summary-card {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 4px;
    text-align: center;
    border-left: 4px solid #0073aa;
}

.sms-summary-label {
    font-size: 12px;
    color: #666;
    text-transform: uppercase;
    margin-bottom: 5px;
}

.sms-summary-value {
    font-size: 24px;
    font-weight: bold;
    color: #0073aa;
}

.sms-breakdown-section,
.sms-trend-section {
    margin-bottom: 30px;
}

.sms-breakdown-section h4,
.sms-trend-section h4 {
    margin-bottom: 15px;
    color: #333;
}

.sms-charts-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 20px;
}

.sms-chart-container {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 4px;
}

.sms-chart-container h4 {
    margin-bottom: 15px;
    text-align: center;
}

.sms-chart-container canvas {
    max-height: 300px;
}

.sms-trend-chart {
    margin-bottom: 20px;
}

@media (max-width: 768px) {
    .sms-report-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .sms-report-meta {
        margin-top: 10px;
    }
    
    .sms-report-meta span {
        display: block;
        margin-left: 0;
        margin-bottom: 5px;
    }
    
    .sms-charts-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
// Chart rendering would be implemented here using Chart.js or similar library
document.addEventListener('DOMContentLoaded', function() {
    // Initialize charts if Chart.js is available
    if (typeof Chart !== 'undefined') {
        initializeCharts();
    }
});

function initializeCharts() {
    // Chart initialization code would go here
    console.log('Charts would be initialized here');
}
</script>