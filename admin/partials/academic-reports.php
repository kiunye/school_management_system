<?php
/**
 * Academic Reports Admin Interface
 *
 * @package    School_Management_System
 * @subpackage School_Management_System/admin/partials
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Get current user capabilities
$can_view_reports = current_user_can('manage_academic_reports') || current_user_can('manage_options');

if (!$can_view_reports) {
    wp_die(__('You do not have sufficient permissions to access this page.', 'school-management-system'));
}

// Initialize academic reporter
$academic_reporter = new SMS_Academic_Reporter();

// Handle report generation
$current_report = null;
$report_error = null;

if (isset($_POST['generate_report']) && wp_verify_nonce($_POST['academic_report_nonce'], 'generate_academic_report')) {
    $report_type = sanitize_text_field($_POST['report_type']);
    $date_range = [
        'start' => sanitize_text_field($_POST['start_date']),
        'end' => sanitize_text_field($_POST['end_date'])
    ];
    
    $filters = [];
    if (!empty($_POST['class_filter'])) {
        $filters['class_id'] = intval($_POST['class_filter']);
    }
    if (!empty($_POST['grade_filter'])) {
        $filters['grade_level'] = sanitize_text_field($_POST['grade_filter']);
    }
    if (!empty($_POST['student_filter'])) {
        $filters['student_id'] = intval($_POST['student_filter']);
    }
    
    $current_report = $academic_reporter->generate_report($report_type, $date_range, $filters);
    
    if (is_wp_error($current_report)) {
        $report_error = $current_report->get_error_message();
        $current_report = null;
    }
}

// Handle report export
if (isset($_POST['export_report']) && wp_verify_nonce($_POST['export_report_nonce'], 'export_academic_report')) {
    $export_format = sanitize_text_field($_POST['export_format']);
    $report_data = json_decode(stripslashes($_POST['report_data']), true);
    
    if ($report_data) {
        $filename = 'academic_report_' . date('Y-m-d_H-i-s');
        $export_result = $academic_reporter->export_report($report_data, $export_format, $filename);
        
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

// Get available grades
$grades = get_terms([
    'taxonomy' => 'sms_grades',
    'hide_empty' => false
]);

// Get available students for specific reports
$students = get_posts([
    'post_type' => 'sms_students',
    'post_status' => 'publish',
    'posts_per_page' => 50,
    'orderby' => 'title',
    'order' => 'ASC'
]);
?>

<div class="wrap">
    <h1><?php _e('Academic Reports', 'school-management-system'); ?></h1>
    
    <?php if ($report_error): ?>
        <div class="notice notice-error">
            <p><?php echo esc_html($report_error); ?></p>
        </div>
    <?php endif; ?>
    
    <div class="sms-academic-reports">
        <!-- Report Generation Form -->
        <div class="sms-report-generator">
            <h2><?php _e('Generate Academic Report', 'school-management-system'); ?></h2>
            
            <form method="post" action="" class="sms-report-form">
                <?php wp_nonce_field('generate_academic_report', 'academic_report_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="report_type"><?php _e('Report Type', 'school-management-system'); ?></label>
                        </th>
                        <td>
                            <select name="report_type" id="report_type" class="regular-text" required>
                                <option value=""><?php _e('Select Report Type', 'school-management-system'); ?></option>
                                <option value="attendance"><?php _e('Comprehensive Attendance Report', 'school-management-system'); ?></option>
                                <option value="attendance_summary"><?php _e('Attendance Summary', 'school-management-system'); ?></option>
                                <option value="student_attendance"><?php _e('Student-wise Attendance', 'school-management-system'); ?></option>
                                <option value="class_attendance"><?php _e('Class-wise Attendance', 'school-management-system'); ?></option>
                                <option value="enrollment_statistics"><?php _e('Enrollment Statistics', 'school-management-system'); ?></option>
                                <option value="academic_performance"><?php _e('Academic Performance', 'school-management-system'); ?></option>
                                <option value="class_performance"><?php _e('Class Performance', 'school-management-system'); ?></option>
                                <option value="student_progress"><?php _e('Student Progress', 'school-management-system'); ?></option>
                            </select>
                            <p class="description"><?php _e('Select the type of academic report to generate.', 'school-management-system'); ?></p>
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
                            <p class="description"><?php _e('Filter by specific class (optional).', 'school-management-system'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="grade_filter"><?php _e('Grade Level', 'school-management-system'); ?></label>
                        </th>
                        <td>
                            <select name="grade_filter" id="grade_filter" class="regular-text">
                                <option value=""><?php _e('All Grades', 'school-management-system'); ?></option>
                                <?php if ($grades): ?>
                                    <?php foreach ($grades as $grade): ?>
                                        <option value="<?php echo esc_attr($grade->slug); ?>">
                                            <?php echo esc_html($grade->name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <p class="description"><?php _e('Filter by grade level (optional).', 'school-management-system'); ?></p>
                        </td>
                    </tr>
                    
                    <tr class="student-filter-row" style="display: none;">
                        <th scope="row">
                            <label for="student_filter"><?php _e('Student', 'school-management-system'); ?></label>
                        </th>
                        <td>
                            <select name="student_filter" id="student_filter" class="regular-text">
                                <option value=""><?php _e('Select Student', 'school-management-system'); ?></option>
                                <?php foreach ($students as $student): ?>
                                    <option value="<?php echo esc_attr($student->ID); ?>">
                                        <?php echo esc_html(get_field('full_name', $student->ID) . ' - ' . get_field('admission_number', $student->ID)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php _e('Select specific student for individual reports.', 'school-management-system'); ?></p>
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
                        <?php wp_nonce_field('export_academic_report', 'export_report_nonce'); ?>
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
                                    <div class="sms-summary-card <?php echo esc_attr($this->get_card_class($key)); ?>">
                                        <div class="sms-summary-label">
                                            <?php echo esc_html(ucfirst(str_replace('_', ' ', $key))); ?>
                                        </div>
                                        <div class="sms-summary-value">
                                            <?php if (strpos($key, 'rate') !== false): ?>
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
                
                <!-- Class Breakdown Section -->
                <?php if (isset($current_report['class_breakdown'])): ?>
                    <div class="sms-report-breakdown">
                        <h3><?php _e('Class Breakdown', 'school-management-system'); ?></h3>
                        
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Class Name', 'school-management-system'); ?></th>
                                    <th><?php _e('Total Records', 'school-management-system'); ?></th>
                                    <th><?php _e('Present', 'school-management-system'); ?></th>
                                    <th><?php _e('Absent', 'school-management-system'); ?></th>
                                    <th><?php _e('Late', 'school-management-system'); ?></th>
                                    <th><?php _e('Attendance Rate', 'school-management-system'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($current_report['class_breakdown'] as $class): ?>
                                    <tr>
                                        <td><strong><?php echo esc_html($class['name']); ?></strong></td>
                                        <td><?php echo number_format($class['total_records']); ?></td>
                                        <td><span class="status-present"><?php echo number_format($class['present_count']); ?></span></td>
                                        <td><span class="status-absent"><?php echo number_format($class['absent_count']); ?></span></td>
                                        <td><span class="status-late"><?php echo number_format($class['late_count']); ?></span></td>
                                        <td>
                                            <div class="attendance-rate">
                                                <span class="rate-text"><?php echo number_format($class['attendance_rate'], 1); ?>%</span>
                                                <div class="rate-bar">
                                                    <div class="rate-fill" style="width: <?php echo esc_attr($class['attendance_rate']); ?>%"></div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
                
                <!-- Student Breakdown Section -->
                <?php if (isset($current_report['student_breakdown'])): ?>
                    <div class="sms-report-breakdown">
                        <h3><?php _e('Student Breakdown (Top Absentees)', 'school-management-system'); ?></h3>
                        
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Student Name', 'school-management-system'); ?></th>
                                    <th><?php _e('Admission No.', 'school-management-system'); ?></th>
                                    <th><?php _e('Class', 'school-management-system'); ?></th>
                                    <th><?php _e('Present', 'school-management-system'); ?></th>
                                    <th><?php _e('Absent', 'school-management-system'); ?></th>
                                    <th><?php _e('Late', 'school-management-system'); ?></th>
                                    <th><?php _e('Attendance Rate', 'school-management-system'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($current_report['student_breakdown'] as $student): ?>
                                    <tr>
                                        <td><strong><?php echo esc_html($student['name']); ?></strong></td>
                                        <td><?php echo esc_html($student['admission_number']); ?></td>
                                        <td><?php echo esc_html($student['class']); ?></td>
                                        <td><span class="status-present"><?php echo number_format($student['present_count']); ?></span></td>
                                        <td><span class="status-absent"><?php echo number_format($student['absent_count']); ?></span></td>
                                        <td><span class="status-late"><?php echo number_format($student['late_count']); ?></span></td>
                                        <td>
                                            <div class="attendance-rate">
                                                <span class="rate-text"><?php echo number_format($student['attendance_rate'], 1); ?>%</span>
                                                <div class="rate-bar">
                                                    <div class="rate-fill" style="width: <?php echo esc_attr($student['attendance_rate']); ?>%"></div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
                
                <!-- Daily Trends Section -->
                <?php if (isset($current_report['daily_trends'])): ?>
                    <div class="sms-report-trends">
                        <h3><?php _e('Daily Attendance Trends', 'school-management-system'); ?></h3>
                        
                        <div class="sms-trend-chart">
                            <canvas id="daily-trends-chart" data-chart-data="<?php echo esc_attr(json_encode($current_report['daily_trends'])); ?>"></canvas>
                        </div>
                        
                        <div class="sms-trend-summary">
                            <?php 
                            $trend_values = array_column($current_report['daily_trends'], 'attendance_rate');
                            $avg_rate = array_sum($trend_values) / count($trend_values);
                            $max_rate = max($trend_values);
                            $min_rate = min($trend_values);
                            ?>
                            <div class="trend-stats">
                                <div class="trend-stat">
                                    <span class="stat-label"><?php _e('Average Rate:', 'school-management-system'); ?></span>
                                    <span class="stat-value"><?php echo number_format($avg_rate, 1); ?>%</span>
                                </div>
                                <div class="trend-stat">
                                    <span class="stat-label"><?php _e('Highest Rate:', 'school-management-system'); ?></span>
                                    <span class="stat-value"><?php echo number_format($max_rate, 1); ?>%</span>
                                </div>
                                <div class="trend-stat">
                                    <span class="stat-label"><?php _e('Lowest Rate:', 'school-management-system'); ?></span>
                                    <span class="stat-value"><?php echo number_format($min_rate, 1); ?>%</span>
                                </div>
                            </div>
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
/* Academic Reports Specific Styles */
.sms-academic-reports {
    max-width: 1200px;
}

.sms-summary-card.attendance {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
}

.sms-summary-card.absence {
    background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);
}

.sms-summary-card.late {
    background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
}

.sms-summary-card.rate {
    background: linear-gradient(135deg, #007bff 0%, #6610f2 100%);
}

.status-present {
    color: #28a745;
    font-weight: bold;
}

.status-absent {
    color: #dc3545;
    font-weight: bold;
}

.status-late {
    color: #ffc107;
    font-weight: bold;
}

.attendance-rate {
    display: flex;
    align-items: center;
    gap: 10px;
}

.rate-text {
    min-width: 50px;
    font-weight: bold;
}

.rate-bar {
    flex: 1;
    height: 8px;
    background: #e9ecef;
    border-radius: 4px;
    overflow: hidden;
}

.rate-fill {
    height: 100%;
    background: linear-gradient(90deg, #dc3545 0%, #ffc107 50%, #28a745 100%);
    transition: width 0.3s ease;
}

.sms-trend-chart {
    margin-bottom: 20px;
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,.1);
}

.sms-trend-chart canvas {
    max-height: 400px;
}

.trend-stats {
    display: flex;
    justify-content: space-around;
    background: #f8f9fa;
    padding: 15px;
    border-radius: 6px;
    margin-top: 15px;
}

.trend-stat {
    text-align: center;
}

.stat-label {
    display: block;
    font-size: 12px;
    color: #666;
    margin-bottom: 5px;
}

.stat-value {
    display: block;
    font-size: 18px;
    font-weight: bold;
    color: #0073aa;
}

.student-filter-row {
    display: none;
}

.student-filter-row.show {
    display: table-row;
}

@media (max-width: 768px) {
    .attendance-rate {
        flex-direction: column;
        align-items: flex-start;
        gap: 5px;
    }
    
    .rate-bar {
        width: 100%;
    }
    
    .trend-stats {
        flex-direction: column;
        gap: 10px;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Show/hide student filter based on report type
    $('#report_type').on('change', function() {
        const reportType = $(this).val();
        const studentRow = $('.student-filter-row');
        
        if (reportType === 'student_attendance' || reportType === 'student_progress') {
            studentRow.show().addClass('show');
        } else {
            studentRow.hide().removeClass('show');
            $('#student_filter').val('');
        }
    });
    
    // Initialize charts if Chart.js is available
    if (typeof Chart !== 'undefined') {
        initializeAcademicCharts();
    }
});

function initializeAcademicCharts() {
    // Initialize daily trends chart
    const dailyTrendsCanvas = document.getElementById('daily-trends-chart');
    if (dailyTrendsCanvas) {
        const trendsData = JSON.parse(dailyTrendsCanvas.dataset.chartData);
        renderDailyTrendsChart(dailyTrendsCanvas, trendsData);
    }
    
    // Initialize other charts
    document.querySelectorAll('.sms-chart-container canvas').forEach(canvas => {
        const chartData = JSON.parse(canvas.dataset.chartData);
        renderAcademicChart(canvas, chartData);
    });
}

function renderDailyTrendsChart(canvas, data) {
    const ctx = canvas.getContext('2d');
    
    const labels = Object.keys(data);
    const attendanceRates = labels.map(date => data[date].attendance_rate);
    const presentCounts = labels.map(date => data[date].present);
    const absentCounts = labels.map(date => data[date].absent);
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Attendance Rate (%)',
                data: attendanceRates,
                borderColor: '#007bff',
                backgroundColor: 'rgba(0, 123, 255, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4,
                yAxisID: 'y'
            }, {
                label: 'Present',
                data: presentCounts,
                borderColor: '#28a745',
                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                borderWidth: 1,
                fill: false,
                yAxisID: 'y1'
            }, {
                label: 'Absent',
                data: absentCounts,
                borderColor: '#dc3545',
                backgroundColor: 'rgba(220, 53, 69, 0.1)',
                borderWidth: 1,
                fill: false,
                yAxisID: 'y1'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top'
                },
                tooltip: {
                    mode: 'index',
                    intersect: false
                }
            },
            scales: {
                x: {
                    display: true,
                    title: {
                        display: true,
                        text: 'Date'
                    }
                },
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    title: {
                        display: true,
                        text: 'Attendance Rate (%)'
                    },
                    min: 0,
                    max: 100
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: 'Count'
                    },
                    grid: {
                        drawOnChartArea: false
                    }
                }
            }
        }
    });
}

function renderAcademicChart(canvas, data) {
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
                    backgroundColor: data.colors || ['#28a745', '#dc3545', '#ffc107'],
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
                    label: 'Attendance Rate (%)',
                    data: data.values,
                    backgroundColor: 'rgba(0, 123, 255, 0.8)',
                    borderColor: 'rgba(0, 123, 255, 1)',
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
                        beginAtZero: true,
                        max: 100,
                        ticks: {
                            callback: function(value) {
                                return value + '%';
                            }
                        }
                    }
                }
            }
        };
    }
    
    new Chart(ctx, chartConfig);
}
</script>

<?php
// Helper function to get card CSS class based on metric type
function get_card_class($key) {
    if (strpos($key, 'present') !== false || strpos($key, 'attendance') !== false) {
        return 'attendance';
    } elseif (strpos($key, 'absent') !== false) {
        return 'absence';
    } elseif (strpos($key, 'late') !== false) {
        return 'late';
    } elseif (strpos($key, 'rate') !== false) {
        return 'rate';
    }
    return '';
}
?>