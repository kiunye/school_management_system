<?php
/**
 * Administrative Reporting Dashboard
 *
 * @package    School_Management_System
 * @subpackage School_Management_System/admin/partials
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Get current user capabilities
$can_view_admin_reports = current_user_can('manage_options') || current_user_can('manage_administrative_reports');

if (!$can_view_admin_reports) {
    wp_die(__('You do not have sufficient permissions to access this page.', 'school-management-system'));
}

// Initialize administrative reporter
$admin_reporter = new SMS_Administrative_Reporter();

// Handle report generation
$current_report = null;
$report_error = null;

if (isset($_POST['generate_report']) && wp_verify_nonce($_POST['admin_report_nonce'], 'generate_administrative_report')) {
    $report_type = sanitize_text_field($_POST['report_type']);
    $date_range = [
        'start' => sanitize_text_field($_POST['start_date']),
        'end' => sanitize_text_field($_POST['end_date'])
    ];
    
    $filters = [];
    if (!empty($_POST['user_role_filter'])) {
        $filters['user_role'] = sanitize_text_field($_POST['user_role_filter']);
    }
    if (!empty($_POST['activity_type_filter'])) {
        $filters['activity_type'] = sanitize_text_field($_POST['activity_type_filter']);
    }
    
    $current_report = $admin_reporter->generate_report($report_type, $date_range, $filters);
    
    if (is_wp_error($current_report)) {
        $report_error = $current_report->get_error_message();
        $current_report = null;
    }
}

// Handle report export
if (isset($_POST['export_report']) && wp_verify_nonce($_POST['export_report_nonce'], 'export_administrative_report')) {
    $export_format = sanitize_text_field($_POST['export_format']);
    $report_data = json_decode(stripslashes($_POST['report_data']), true);
    
    if ($report_data) {
        $filename = 'administrative_report_' . date('Y-m-d_H-i-s');
        $export_result = $admin_reporter->export_report($report_data, $export_format, $filename);
        
        if (!is_wp_error($export_result)) {
            // Trigger download
            $upload_dir = wp_upload_dir();
            $file_url = str_replace($upload_dir['path'], $upload_dir['url'], $export_result);
            echo '<script>window.open("' . esc_url($file_url) . '", "_blank");</script>';
        }
    }
}

// Get system statistics for dashboard overview
$system_stats = [
    'total_users' => count_users()['total_users'],
    'active_students' => wp_count_posts('sms_students')->publish,
    'total_classes' => wp_count_posts('sms_classes')->publish,
    'recent_activities' => 25, // This would come from activity logs
    'sms_sent_today' => 15, // This would come from SMS logs
    'system_uptime' => '99.9%' // This would come from monitoring
];
?>

<div class="wrap">
    <h1><?php _e('Administrative Dashboard', 'school-management-system'); ?></h1>
    
    <?php if ($report_error): ?>
        <div class="notice notice-error">
            <p><?php echo esc_html($report_error); ?></p>
        </div>
    <?php endif; ?>
    
    <div class="sms-admin-dashboard">
        <!-- System Overview Cards -->
        <div class="sms-overview-cards">
            <div class="sms-overview-card users">
                <div class="card-icon">
                    <span class="dashicons dashicons-admin-users"></span>
                </div>
                <div class="card-content">
                    <div class="card-title"><?php _e('Total Users', 'school-management-system'); ?></div>
                    <div class="card-value"><?php echo number_format($system_stats['total_users']); ?></div>
                </div>
            </div>
            
            <div class="sms-overview-card students">
                <div class="card-icon">
                    <span class="dashicons dashicons-groups"></span>
                </div>
                <div class="card-content">
                    <div class="card-title"><?php _e('Active Students', 'school-management-system'); ?></div>
                    <div class="card-value"><?php echo number_format($system_stats['active_students']); ?></div>
                </div>
            </div>
            
            <div class="sms-overview-card classes">
                <div class="card-icon">
                    <span class="dashicons dashicons-building"></span>
                </div>
                <div class="card-content">
                    <div class="card-title"><?php _e('Total Classes', 'school-management-system'); ?></div>
                    <div class="card-value"><?php echo number_format($system_stats['total_classes']); ?></div>
                </div>
            </div>
            
            <div class="sms-overview-card activities">
                <div class="card-icon">
                    <span class="dashicons dashicons-chart-line"></span>
                </div>
                <div class="card-content">
                    <div class="card-title"><?php _e('Recent Activities', 'school-management-system'); ?></div>
                    <div class="card-value"><?php echo number_format($system_stats['recent_activities']); ?></div>
                </div>
            </div>
            
            <div class="sms-overview-card sms">
                <div class="card-icon">
                    <span class="dashicons dashicons-email-alt"></span>
                </div>
                <div class="card-content">
                    <div class="card-title"><?php _e('SMS Sent Today', 'school-management-system'); ?></div>
                    <div class="card-value"><?php echo number_format($system_stats['sms_sent_today']); ?></div>
                </div>
            </div>
            
            <div class="sms-overview-card uptime">
                <div class="card-icon">
                    <span class="dashicons dashicons-yes-alt"></span>
                </div>
                <div class="card-content">
                    <div class="card-title"><?php _e('System Uptime', 'school-management-system'); ?></div>
                    <div class="card-value"><?php echo esc_html($system_stats['system_uptime']); ?></div>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="sms-quick-actions">
            <h2><?php _e('Quick Actions', 'school-management-system'); ?></h2>
            <div class="quick-actions-grid">
                <a href="#" class="quick-action-btn" data-report="user_activity">
                    <span class="dashicons dashicons-admin-users"></span>
                    <?php _e('User Activity Report', 'school-management-system'); ?>
                </a>
                <a href="#" class="quick-action-btn" data-report="system_usage">
                    <span class="dashicons dashicons-chart-bar"></span>
                    <?php _e('System Usage Report', 'school-management-system'); ?>
                </a>
                <a href="#" class="quick-action-btn" data-report="sms_usage">
                    <span class="dashicons dashicons-email-alt"></span>
                    <?php _e('SMS Usage Report', 'school-management-system'); ?>
                </a>
                <a href="#" class="quick-action-btn" data-report="transport_utilization">
                    <span class="dashicons dashicons-car"></span>
                    <?php _e('Transport Report', 'school-management-system'); ?>
                </a>
            </div>
        </div>
        
        <!-- Report Generation Form -->
        <div class="sms-report-generator">
            <h2><?php _e('Generate Administrative Report', 'school-management-system'); ?></h2>
            
            <form method="post" action="" class="sms-report-form">
                <?php wp_nonce_field('generate_administrative_report', 'admin_report_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="report_type"><?php _e('Report Type', 'school-management-system'); ?></label>
                        </th>
                        <td>
                            <select name="report_type" id="report_type" class="regular-text" required>
                                <option value=""><?php _e('Select Report Type', 'school-management-system'); ?></option>
                                <option value="user_activity"><?php _e('User Activity Report', 'school-management-system'); ?></option>
                                <option value="system_usage"><?php _e('System Usage Analytics', 'school-management-system'); ?></option>
                                <option value="sms_usage"><?php _e('SMS Usage Statistics', 'school-management-system'); ?></option>
                                <option value="communication_tracking"><?php _e('Communication Tracking', 'school-management-system'); ?></option>
                                <option value="transport_utilization"><?php _e('Transport Utilization', 'school-management-system'); ?></option>
                                <option value="route_efficiency"><?php _e('Route Efficiency', 'school-management-system'); ?></option>
                                <option value="system_performance"><?php _e('System Performance', 'school-management-system'); ?></option>
                                <option value="security_audit"><?php _e('Security Audit', 'school-management-system'); ?></option>
                            </select>
                            <p class="description"><?php _e('Select the type of administrative report to generate.', 'school-management-system'); ?></p>
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
                    
                    <tr class="user-role-filter-row">
                        <th scope="row">
                            <label for="user_role_filter"><?php _e('User Role', 'school-management-system'); ?></label>
                        </th>
                        <td>
                            <select name="user_role_filter" id="user_role_filter" class="regular-text">
                                <option value=""><?php _e('All Roles', 'school-management-system'); ?></option>
                                <option value="administrator"><?php _e('Administrator', 'school-management-system'); ?></option>
                                <option value="teacher"><?php _e('Teacher', 'school-management-system'); ?></option>
                                <option value="parent"><?php _e('Parent', 'school-management-system'); ?></option>
                                <option value="student"><?php _e('Student', 'school-management-system'); ?></option>
                            </select>
                            <p class="description"><?php _e('Filter by user role (optional).', 'school-management-system'); ?></p>
                        </td>
                    </tr>
                    
                    <tr class="activity-type-filter-row">
                        <th scope="row">
                            <label for="activity_type_filter"><?php _e('Activity Type', 'school-management-system'); ?></label>
                        </th>
                        <td>
                            <select name="activity_type_filter" id="activity_type_filter" class="regular-text">
                                <option value=""><?php _e('All Activities', 'school-management-system'); ?></option>
                                <option value="login"><?php _e('Login', 'school-management-system'); ?></option>
                                <option value="student_created"><?php _e('Student Created', 'school-management-system'); ?></option>
                                <option value="attendance_marked"><?php _e('Attendance Marked', 'school-management-system'); ?></option>
                                <option value="fee_processed"><?php _e('Fee Processed', 'school-management-system'); ?></option>
                                <option value="sms_sent"><?php _e('SMS Sent', 'school-management-system'); ?></option>
                            </select>
                            <p class="description"><?php _e('Filter by activity type (optional).', 'school-management-system'); ?></p>
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
                        <?php wp_nonce_field('export_administrative_report', 'export_report_nonce'); ?>
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
                                <?php if (is_numeric($value) || is_string($value)): ?>
                                    <div class="sms-summary-card <?php echo esc_attr($this->get_summary_card_class($key)); ?>">
                                        <div class="sms-summary-label">
                                            <?php echo esc_html(ucfirst(str_replace('_', ' ', $key))); ?>
                                        </div>
                                        <div class="sms-summary-value">
                                            <?php if (is_numeric($value)): ?>
                                                <?php if (strpos($key, 'rate') !== false || strpos($key, 'percentage') !== false): ?>
                                                    <?php echo number_format($value, 1); ?>%
                                                <?php elseif (strpos($key, 'cost') !== false || strpos($key, 'amount') !== false): ?>
                                                    KES <?php echo number_format($value, 2); ?>
                                                <?php else: ?>
                                                    <?php echo number_format($value); ?>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <?php echo esc_html($value); ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- User Activity Breakdown -->
                <?php if (isset($current_report['by_user'])): ?>
                    <div class="sms-report-breakdown">
                        <h3><?php _e('Top Active Users', 'school-management-system'); ?></h3>
                        
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php _e('User Name', 'school-management-system'); ?></th>
                                    <th><?php _e('Activity Count', 'school-management-system'); ?></th>
                                    <th><?php _e('Last Activity', 'school-management-system'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($current_report['by_user'] as $user): ?>
                                    <tr>
                                        <td><strong><?php echo esc_html($user['name']); ?></strong></td>
                                        <td><?php echo number_format($user['count']); ?></td>
                                        <td><?php echo esc_html($user['last_activity'] ?? 'N/A'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
                
                <!-- Role Activity Breakdown -->
                <?php if (isset($current_report['by_role'])): ?>
                    <div class="sms-report-breakdown">
                        <h3><?php _e('Activity by Role', 'school-management-system'); ?></h3>
                        
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Role', 'school-management-system'); ?></th>
                                    <th><?php _e('Activity Count', 'school-management-system'); ?></th>
                                    <th><?php _e('Percentage', 'school-management-system'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total_activities = array_sum(array_column($current_report['by_role'], 'count'));
                                foreach ($current_report['by_role'] as $role): 
                                    $percentage = $total_activities > 0 ? ($role['count'] / $total_activities) * 100 : 0;
                                ?>
                                    <tr>
                                        <td><strong><?php echo esc_html($role['name']); ?></strong></td>
                                        <td><?php echo number_format($role['count']); ?></td>
                                        <td>
                                            <div class="activity-percentage">
                                                <span class="percentage-text"><?php echo number_format($percentage, 1); ?>%</span>
                                                <div class="percentage-bar">
                                                    <div class="percentage-fill" style="width: <?php echo esc_attr($percentage); ?>%"></div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
                
                <!-- Daily Activity Trends -->
                <?php if (isset($current_report['daily_activity'])): ?>
                    <div class="sms-report-trends">
                        <h3><?php _e('Daily Activity Trends', 'school-management-system'); ?></h3>
                        
                        <div class="sms-trend-chart">
                            <canvas id="daily-activity-chart" 
                                    data-chart-data="<?php echo esc_attr(json_encode($current_report['daily_activity'])); ?>"></canvas>
                        </div>
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
/* Administrative Dashboard Styles */
.sms-admin-dashboard {
    max-width: 1200px;
}

.sms-overview-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.sms-overview-card {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,.1);
    display: flex;
    align-items: center;
    gap: 15px;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.sms-overview-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,.15);
}

.sms-overview-card.users {
    border-left: 4px solid #007cba;
}

.sms-overview-card.students {
    border-left: 4px solid #00a32a;
}

.sms-overview-card.classes {
    border-left: 4px solid #ff6900;
}

.sms-overview-card.activities {
    border-left: 4px solid #826eb4;
}

.sms-overview-card.sms {
    border-left: 4px solid #d63638;
}

.sms-overview-card.uptime {
    border-left: 4px solid #00ba37;
}

.card-icon {
    font-size: 32px;
    color: #666;
}

.card-content {
    flex: 1;
}

.card-title {
    font-size: 12px;
    color: #666;
    text-transform: uppercase;
    margin-bottom: 5px;
}

.card-value {
    font-size: 24px;
    font-weight: bold;
    color: #23282d;
}

.sms-quick-actions {
    background: white;
    padding: 25px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,.1);
    margin-bottom: 30px;
}

.sms-quick-actions h2 {
    margin-top: 0;
    margin-bottom: 20px;
}

.quick-actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}

.quick-action-btn {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 15px;
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    text-decoration: none;
    color: #495057;
    transition: all 0.2s ease;
}

.quick-action-btn:hover {
    background: #e9ecef;
    border-color: #adb5bd;
    color: #212529;
    text-decoration: none;
}

.quick-action-btn .dashicons {
    font-size: 20px;
}

.sms-report-generator {
    background: white;
    padding: 25px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,.1);
    margin-bottom: 30px;
}

.sms-report-display {
    background: white;
    padding: 25px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,.1);
}

.activity-percentage {
    display: flex;
    align-items: center;
    gap: 10px;
}

.percentage-text {
    min-width: 50px;
    font-weight: bold;
}

.percentage-bar {
    flex: 1;
    height: 8px;
    background: #e9ecef;
    border-radius: 4px;
    overflow: hidden;
}

.percentage-fill {
    height: 100%;
    background: linear-gradient(90deg, #007cba 0%, #00a32a 100%);
    transition: width 0.3s ease;
}

.user-role-filter-row,
.activity-type-filter-row {
    display: none;
}

.user-role-filter-row.show,
.activity-type-filter-row.show {
    display: table-row;
}

@media (max-width: 768px) {
    .sms-overview-cards {
        grid-template-columns: 1fr;
    }
    
    .quick-actions-grid {
        grid-template-columns: 1fr;
    }
    
    .activity-percentage {
        flex-direction: column;
        align-items: flex-start;
        gap: 5px;
    }
    
    .percentage-bar {
        width: 100%;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Quick action buttons
    $('.quick-action-btn').on('click', function(e) {
        e.preventDefault();
        const reportType = $(this).data('report');
        $('#report_type').val(reportType);
        
        // Scroll to form
        $('html, body').animate({
            scrollTop: $('.sms-report-generator').offset().top - 50
        }, 500);
    });
    
    // Show/hide filters based on report type
    $('#report_type').on('change', function() {
        const reportType = $(this).val();
        const userRoleRow = $('.user-role-filter-row');
        const activityTypeRow = $('.activity-type-filter-row');
        
        // Hide all filters first
        userRoleRow.hide().removeClass('show');
        activityTypeRow.hide().removeClass('show');
        
        // Show relevant filters
        if (reportType === 'user_activity' || reportType === 'system_usage') {
            userRoleRow.show().addClass('show');
            activityTypeRow.show().addClass('show');
        }
    });
    
    // Initialize charts if Chart.js is available
    if (typeof Chart !== 'undefined') {
        initializeAdminCharts();
    }
});

function initializeAdminCharts() {
    // Initialize daily activity chart
    const dailyActivityCanvas = document.getElementById('daily-activity-chart');
    if (dailyActivityCanvas) {
        const activityData = JSON.parse(dailyActivityCanvas.dataset.chartData);
        renderDailyActivityChart(dailyActivityCanvas, activityData);
    }
    
    // Initialize other charts
    document.querySelectorAll('.sms-chart-container canvas').forEach(canvas => {
        const chartData = JSON.parse(canvas.dataset.chartData);
        renderAdminChart(canvas, chartData);
    });
}

function renderDailyActivityChart(canvas, data) {
    const ctx = canvas.getContext('2d');
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: Object.keys(data),
            datasets: [{
                label: 'Daily Activities',
                data: Object.values(data),
                borderColor: '#007cba',
                backgroundColor: 'rgba(0, 124, 186, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Number of Activities'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Date'
                    }
                }
            }
        }
    });
}

function renderAdminChart(canvas, data) {
    const ctx = canvas.getContext('2d');
    const chartId = canvas.id;
    
    let chartConfig = {};
    
    if (chartId.includes('pie')) {
        chartConfig = {
            type: 'pie',
            data: {
                labels: data.labels,
                datasets: [{
                    data: data.values,
                    backgroundColor: data.colors || ['#007cba', '#00a32a', '#ff6900', '#826eb4', '#d63638'],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        };
    } else {
        chartConfig = {
            type: 'bar',
            data: {
                labels: data.labels,
                datasets: [{
                    label: 'Count',
                    data: data.values,
                    backgroundColor: 'rgba(0, 124, 186, 0.8)',
                    borderColor: 'rgba(0, 124, 186, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        };
    }
    
    new Chart(ctx, chartConfig);
}
</script>

<?php
// Helper function to get summary card CSS class
function get_summary_card_class($key) {
    if (strpos($key, 'user') !== false) {
        return 'users';
    } elseif (strpos($key, 'activity') !== false || strpos($key, 'activities') !== false) {
        return 'activities';
    } elseif (strpos($key, 'sms') !== false) {
        return 'sms';
    } elseif (strpos($key, 'cost') !== false || strpos($key, 'amount') !== false) {
        return 'cost';
    }
    return 'default';
}
?>