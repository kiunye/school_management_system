<?php
/**
 * Attendance Reports and Analytics
 *
 * @package SchoolManagementSystem
 * @subpackage Admin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class SMS_Attendance_Reports
 * 
 * Handles attendance reporting, analytics, and trend tracking
 */
class SMS_Attendance_Reports {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_sms_generate_attendance_report', array($this, 'ajax_generate_attendance_report'));
        add_action('wp_ajax_sms_export_attendance_report', array($this, 'ajax_export_attendance_report'));
        add_action('wp_ajax_sms_get_attendance_analytics', array($this, 'ajax_get_attendance_analytics'));
    }

    /**
     * Add admin menu for attendance reports
     */
    public function add_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=sms_attendance',
            __('Attendance Reports', 'school-management-system'),
            __('Reports & Analytics', 'school-management-system'),
            'view_attendance_reports',
            'sms-attendance-reports',
            array($this, 'render_reports_page')
        );
    }

    /**
     * Enqueue scripts and styles for attendance reports
     */
    public function enqueue_scripts($hook) {
        if (strpos($hook, 'sms-attendance-reports') === false) {
            return;
        }

        wp_enqueue_script('jquery');
        wp_enqueue_script('jquery-ui-datepicker');
        wp_enqueue_style('jquery-ui-datepicker', 'https://code.jquery.com/ui/1.12.1/themes/ui-lightness/jquery-ui.css');
        
        // Chart.js for analytics
        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '3.9.1', true);
        
        wp_enqueue_script(
            'sms-attendance-reports',
            plugin_dir_url(__FILE__) . '../../admin/js/attendance-reports.js',
            array('jquery', 'jquery-ui-datepicker', 'chart-js'),
            '1.0.0',
            true
        );

        wp_enqueue_style(
            'sms-attendance-reports',
            plugin_dir_url(__FILE__) . '../../admin/css/attendance-reports.css',
            array(),
            '1.0.0'
        );

        wp_localize_script('sms-attendance-reports', 'smsReports', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sms_reports_nonce'),
            'strings' => array(
                'loading' => __('Loading...', 'school-management-system'),
                'generating' => __('Generating report...', 'school-management-system'),
                'exporting' => __('Exporting...', 'school-management-system'),
                'error' => __('Error generating report. Please try again.', 'school-management-system'),
                'noData' => __('No attendance data found for the selected criteria.', 'school-management-system'),
                'selectClass' => __('Please select at least one class.', 'school-management-system'),
                'selectDateRange' => __('Please select a valid date range.', 'school-management-system'),
            )
        ));
    }

    /**
     * Render the attendance reports page
     */
    public function render_reports_page() {
        // Get all active classes
        $classes = get_posts(array(
            'post_type' => 'sms_classes',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => 'class_status',
                    'value' => 'active',
                    'compare' => '='
                )
            ),
            'orderby' => 'title',
            'order' => 'ASC'
        ));

        ?>
        <div class="wrap">
            <h1><?php _e('Attendance Reports & Analytics', 'school-management-system'); ?></h1>
            
            <div class="sms-reports-container">
                <!-- Report Filters -->
                <div class="sms-card">
                    <h2><?php _e('Report Filters', 'school-management-system'); ?></h2>
                    
                    <form id="sms-report-filters" class="report-filters">
                        <div class="filter-row">
                            <div class="filter-group">
                                <label for="report-type"><?php _e('Report Type', 'school-management-system'); ?></label>
                                <select id="report-type" name="report_type" required>
                                    <option value="daily"><?php _e('Daily Report', 'school-management-system'); ?></option>
                                    <option value="weekly"><?php _e('Weekly Report', 'school-management-system'); ?></option>
                                    <option value="monthly" selected><?php _e('Monthly Report', 'school-management-system'); ?></option>
                                    <option value="term"><?php _e('Term Report', 'school-management-system'); ?></option>
                                    <option value="custom"><?php _e('Custom Date Range', 'school-management-system'); ?></option>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <label for="report-classes"><?php _e('Classes', 'school-management-system'); ?></label>
                                <select id="report-classes" name="classes[]" multiple>
                                    <option value="all" selected><?php _e('All Classes', 'school-management-system'); ?></option>
                                    <?php foreach ($classes as $class): ?>
                                        <option value="<?php echo esc_attr($class->ID); ?>">
                                            <?php echo esc_html(get_field('class_name', $class->ID) ?: $class->post_title); ?>
                                            (<?php echo esc_html(get_field('class_code', $class->ID)); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="filter-row" id="date-range-filters">
                            <div class="filter-group">
                                <label for="start-date"><?php _e('Start Date', 'school-management-system'); ?></label>
                                <input type="text" id="start-date" name="start_date" 
                                       value="<?php echo esc_attr(date('Y-m-01')); ?>" 
                                       placeholder="<?php _e('Select start date...', 'school-management-system'); ?>">
                            </div>
                            
                            <div class="filter-group">
                                <label for="end-date"><?php _e('End Date', 'school-management-system'); ?></label>
                                <input type="text" id="end-date" name="end_date" 
                                       value="<?php echo esc_attr(date('Y-m-t')); ?>" 
                                       placeholder="<?php _e('Select end date...', 'school-management-system'); ?>">
                            </div>
                        </div>
                        
                        <div class="filter-actions">
                            <button type="button" id="generate-report" class="button button-primary">
                                <?php _e('Generate Report', 'school-management-system'); ?>
                            </button>
                            <button type="button" id="export-report" class="button" disabled>
                                <?php _e('Export to CSV', 'school-management-system'); ?>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Analytics Dashboard -->
                <div id="analytics-dashboard" class="analytics-dashboard" style="display: none;">
                    <div class="sms-card">
                        <h2><?php _e('Attendance Analytics', 'school-management-system'); ?></h2>
                        
                        <!-- Summary Cards -->
                        <div class="analytics-summary">
                            <div class="summary-card">
                                <div class="card-icon present">📊</div>
                                <div class="card-content">
                                    <div class="card-value" id="overall-attendance">0%</div>
                                    <div class="card-label"><?php _e('Overall Attendance', 'school-management-system'); ?></div>
                                </div>
                            </div>
                            
                            <div class="summary-card">
                                <div class="card-icon">👥</div>
                                <div class="card-content">
                                    <div class="card-value" id="total-students">0</div>
                                    <div class="card-label"><?php _e('Total Students', 'school-management-system'); ?></div>
                                </div>
                            </div>
                            
                            <div class="summary-card">
                                <div class="card-icon">📅</div>
                                <div class="card-content">
                                    <div class="card-value" id="school-days">0</div>
                                    <div class="card-label"><?php _e('School Days', 'school-management-system'); ?></div>
                                </div>
                            </div>
                            
                            <div class="summary-card">
                                <div class="card-icon absent">⚠️</div>
                                <div class="card-content">
                                    <div class="card-value" id="chronic-absentees">0</div>
                                    <div class="card-label"><?php _e('Chronic Absentees', 'school-management-system'); ?></div>
                                </div>
                            </div>
                        </div>

                        <!-- Charts -->
                        <div class="charts-container">
                            <div class="chart-section">
                                <h3><?php _e('Attendance Trends', 'school-management-system'); ?></h3>
                                <canvas id="attendance-trend-chart"></canvas>
                            </div>
                            
                            <div class="chart-section">
                                <h3><?php _e('Class Comparison', 'school-management-system'); ?></h3>
                                <canvas id="class-comparison-chart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Report Results -->
                <div id="report-results" class="report-results" style="display: none;">
                    <div class="sms-card">
                        <div class="report-header">
                            <h2 id="report-title"><?php _e('Attendance Report', 'school-management-system'); ?></h2>
                            <div class="report-meta">
                                <span id="report-period"></span>
                                <span id="report-generated"></span>
                            </div>
                        </div>
                        
                        <!-- Report Table -->
                        <div class="report-table-container">
                            <table id="attendance-report-table" class="wp-list-table widefat fixed striped">
                                <thead>
                                    <tr>
                                        <th><?php _e('Student', 'school-management-system'); ?></th>
                                        <th><?php _e('Class', 'school-management-system'); ?></th>
                                        <th><?php _e('Present Days', 'school-management-system'); ?></th>
                                        <th><?php _e('Absent Days', 'school-management-system'); ?></th>
                                        <th><?php _e('Late Days', 'school-management-system'); ?></th>
                                        <th><?php _e('Excused Days', 'school-management-system'); ?></th>
                                        <th><?php _e('Attendance Rate', 'school-management-system'); ?></th>
                                        <th><?php _e('Status', 'school-management-system'); ?></th>
                                    </tr>
                                </thead>
                                <tbody id="report-table-body">
                                    <!-- Report data will be loaded here -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Loading Indicator -->
                <div id="loading-indicator" class="loading-indicator" style="display: none;">
                    <div class="spinner"></div>
                    <p><?php _e('Generating report...', 'school-management-system'); ?></p>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX handler to generate attendance report
     */
    public function ajax_generate_attendance_report() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_reports_nonce')) {
            wp_send_json_error(__('Security check failed', 'school-management-system'));
        }

        // Check user capabilities
        if (!current_user_can('view_attendance_reports')) {
            wp_send_json_error(__('You do not have permission to view reports', 'school-management-system'));
        }

        $report_type = sanitize_text_field($_POST['report_type']);
        $classes = isset($_POST['classes']) ? array_map('intval', $_POST['classes']) : array();
        $start_date = sanitize_text_field($_POST['start_date']);
        $end_date = sanitize_text_field($_POST['end_date']);

        // Validate inputs
        if (empty($classes) || (in_array('all', $_POST['classes']) && count($_POST['classes']) === 1)) {
            $classes = $this->get_all_class_ids();
        }

        if (!$start_date || !$end_date) {
            wp_send_json_error(__('Please provide valid date range', 'school-management-system'));
        }

        // Generate report data
        $report_data = $this->generate_report_data($classes, $start_date, $end_date, $report_type);
        
        if (empty($report_data['students'])) {
            wp_send_json_error(__('No attendance data found for the selected criteria', 'school-management-system'));
        }

        // Generate analytics data
        $analytics_data = $this->generate_analytics_data($classes, $start_date, $end_date);

        wp_send_json_success(array(
            'report_data' => $report_data,
            'analytics_data' => $analytics_data,
            'report_meta' => array(
                'type' => $report_type,
                'period' => $this->format_period($start_date, $end_date, $report_type),
                'generated' => current_time('mysql'),
                'classes_count' => count($classes),
                'students_count' => count($report_data['students']),
            )
        ));
    }

    /**
     * AJAX handler to export attendance report
     */
    public function ajax_export_attendance_report() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_reports_nonce')) {
            wp_send_json_error(__('Security check failed', 'school-management-system'));
        }

        // Check user capabilities
        if (!current_user_can('view_attendance_reports')) {
            wp_send_json_error(__('You do not have permission to export reports', 'school-management-system'));
        }

        $report_data = json_decode(stripslashes($_POST['report_data']), true);
        
        if (empty($report_data)) {
            wp_send_json_error(__('No report data to export', 'school-management-system'));
        }

        // Generate CSV content
        $csv_content = $this->generate_csv_content($report_data);
        
        // Create temporary file
        $upload_dir = wp_upload_dir();
        $filename = 'attendance-report-' . date('Y-m-d-H-i-s') . '.csv';
        $file_path = $upload_dir['path'] . '/' . $filename;
        
        if (file_put_contents($file_path, $csv_content) === false) {
            wp_send_json_error(__('Failed to create export file', 'school-management-system'));
        }

        wp_send_json_success(array(
            'download_url' => $upload_dir['url'] . '/' . $filename,
            'filename' => $filename,
        ));
    }

    /**
     * AJAX handler to get attendance analytics
     */
    public function ajax_get_attendance_analytics() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_reports_nonce')) {
            wp_send_json_error(__('Security check failed', 'school-management-system'));
        }

        // Check user capabilities
        if (!current_user_can('view_attendance_reports')) {
            wp_send_json_error(__('You do not have permission to view analytics', 'school-management-system'));
        }

        $classes = isset($_POST['classes']) ? array_map('intval', $_POST['classes']) : array();
        $start_date = sanitize_text_field($_POST['start_date']);
        $end_date = sanitize_text_field($_POST['end_date']);

        if (empty($classes) || (in_array('all', $_POST['classes']) && count($_POST['classes']) === 1)) {
            $classes = $this->get_all_class_ids();
        }

        $analytics_data = $this->generate_analytics_data($classes, $start_date, $end_date);

        wp_send_json_success($analytics_data);
    }

    /**
     * Generate report data for given parameters
     */
    private function generate_report_data($classes, $start_date, $end_date, $report_type) {
        $students_data = array();
        
        foreach ($classes as $class_id) {
            $class_students = $this->get_class_students($class_id);
            
            foreach ($class_students as $student) {
                $attendance_stats = $this->calculate_student_attendance($student->ID, $class_id, $start_date, $end_date);
                
                $students_data[] = array(
                    'student_id' => $student->ID,
                    'student_name' => get_field('full_name', $student->ID),
                    'admission_number' => get_field('admission_number', $student->ID),
                    'class_id' => $class_id,
                    'class_name' => get_field('class_name', $class_id),
                    'class_code' => get_field('class_code', $class_id),
                    'present_days' => $attendance_stats['present'],
                    'absent_days' => $attendance_stats['absent'],
                    'late_days' => $attendance_stats['late'],
                    'excused_days' => $attendance_stats['excused'],
                    'total_days' => $attendance_stats['total_days'],
                    'attendance_rate' => $attendance_stats['attendance_rate'],
                    'status' => $this->get_attendance_status($attendance_stats['attendance_rate']),
                );
            }
        }

        return array(
            'students' => $students_data,
            'summary' => $this->calculate_summary_stats($students_data),
        );
    }

    /**
     * Generate analytics data
     */
    private function generate_analytics_data($classes, $start_date, $end_date) {
        $analytics = array(
            'overall_attendance' => 0,
            'total_students' => 0,
            'school_days' => 0,
            'chronic_absentees' => 0,
            'trend_data' => array(),
            'class_comparison' => array(),
        );

        // Calculate overall statistics
        $all_students = array();
        $total_present = 0;
        $total_possible = 0;

        foreach ($classes as $class_id) {
            $class_students = $this->get_class_students($class_id);
            $class_name = get_field('class_name', $class_id);
            $class_present = 0;
            $class_possible = 0;

            foreach ($class_students as $student) {
                $stats = $this->calculate_student_attendance($student->ID, $class_id, $start_date, $end_date);
                $all_students[] = $stats;
                
                $class_present += $stats['present'];
                $class_possible += $stats['total_days'];
                
                // Count chronic absentees (attendance rate < 75%)
                if ($stats['attendance_rate'] < 75) {
                    $analytics['chronic_absentees']++;
                }
            }

            $total_present += $class_present;
            $total_possible += $class_possible;

            // Class comparison data
            $class_attendance_rate = $class_possible > 0 ? ($class_present / $class_possible) * 100 : 0;
            $analytics['class_comparison'][] = array(
                'class_name' => $class_name,
                'attendance_rate' => round($class_attendance_rate, 2),
                'student_count' => count($class_students),
            );
        }

        $analytics['total_students'] = count($all_students);
        $analytics['overall_attendance'] = $total_possible > 0 ? round(($total_present / $total_possible) * 100, 2) : 0;
        $analytics['school_days'] = $this->count_school_days($start_date, $end_date);

        // Generate trend data (daily attendance rates)
        $analytics['trend_data'] = $this->generate_trend_data($classes, $start_date, $end_date);

        return $analytics;
    }

    /**
     * Calculate student attendance statistics
     */
    private function calculate_student_attendance($student_id, $class_id, $start_date, $end_date) {
        $attendance_records = get_posts(array(
            'post_type' => 'sms_attendance',
            'posts_per_page' => -1,
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => 'attendance_class',
                    'value' => $class_id,
                    'compare' => '='
                ),
                array(
                    'key' => 'attendance_date',
                    'value' => array($start_date, $end_date),
                    'compare' => 'BETWEEN',
                    'type' => 'DATE'
                )
            )
        ));

        $stats = array(
            'present' => 0,
            'absent' => 0,
            'late' => 0,
            'excused' => 0,
            'total_days' => 0,
            'attendance_rate' => 0,
        );

        foreach ($attendance_records as $record) {
            $attendance_data = get_field('student_attendance_data', $record->ID);
            if ($attendance_data) {
                $decoded_data = json_decode($attendance_data, true);
                if ($decoded_data) {
                    foreach ($decoded_data as $student_record) {
                        if ($student_record['student_id'] == $student_id) {
                            $stats['total_days']++;
                            switch ($student_record['status']) {
                                case 'present':
                                    $stats['present']++;
                                    break;
                                case 'absent':
                                    $stats['absent']++;
                                    break;
                                case 'late':
                                    $stats['late']++;
                                    break;
                                case 'excused':
                                    $stats['excused']++;
                                    break;
                            }
                            break;
                        }
                    }
                }
            }
        }

        // Calculate attendance rate (present + late + excused / total days)
        if ($stats['total_days'] > 0) {
            $attended_days = $stats['present'] + $stats['late'] + $stats['excused'];
            $stats['attendance_rate'] = round(($attended_days / $stats['total_days']) * 100, 2);
        }

        return $stats;
    }

    /**
     * Get all class IDs
     */
    private function get_all_class_ids() {
        $classes = get_posts(array(
            'post_type' => 'sms_classes',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => array(
                array(
                    'key' => 'class_status',
                    'value' => 'active',
                    'compare' => '='
                )
            )
        ));

        return $classes;
    }

    /**
     * Get students in a class
     */
    private function get_class_students($class_id) {
        return get_posts(array(
            'post_type' => 'sms_students',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => 'assigned_class',
                    'value' => $class_id,
                    'compare' => '='
                ),
                array(
                    'key' => 'student_status',
                    'value' => 'active',
                    'compare' => '='
                )
            ),
            'orderby' => 'meta_value',
            'meta_key' => 'full_name',
            'order' => 'ASC'
        ));
    }

    /**
     * Get attendance status based on attendance rate
     */
    private function get_attendance_status($attendance_rate) {
        if ($attendance_rate >= 95) {
            return 'excellent';
        } elseif ($attendance_rate >= 85) {
            return 'good';
        } elseif ($attendance_rate >= 75) {
            return 'satisfactory';
        } elseif ($attendance_rate >= 60) {
            return 'needs_improvement';
        } else {
            return 'poor';
        }
    }

    /**
     * Calculate summary statistics
     */
    private function calculate_summary_stats($students_data) {
        if (empty($students_data)) {
            return array();
        }

        $total_students = count($students_data);
        $total_present = array_sum(array_column($students_data, 'present_days'));
        $total_absent = array_sum(array_column($students_data, 'absent_days'));
        $total_late = array_sum(array_column($students_data, 'late_days'));
        $total_excused = array_sum(array_column($students_data, 'excused_days'));
        $total_possible = array_sum(array_column($students_data, 'total_days'));

        $overall_rate = $total_possible > 0 ? round((($total_present + $total_late + $total_excused) / $total_possible) * 100, 2) : 0;

        return array(
            'total_students' => $total_students,
            'total_present' => $total_present,
            'total_absent' => $total_absent,
            'total_late' => $total_late,
            'total_excused' => $total_excused,
            'total_possible' => $total_possible,
            'overall_attendance_rate' => $overall_rate,
        );
    }

    /**
     * Generate trend data for charts
     */
    private function generate_trend_data($classes, $start_date, $end_date) {
        $trend_data = array();
        
        $current_date = new DateTime($start_date);
        $end_date_obj = new DateTime($end_date);

        while ($current_date <= $end_date_obj) {
            $date_str = $current_date->format('Y-m-d');
            $day_attendance = $this->get_daily_attendance_rate($classes, $date_str);
            
            $trend_data[] = array(
                'date' => $date_str,
                'attendance_rate' => $day_attendance,
                'formatted_date' => $current_date->format('M j'),
            );
            
            $current_date->add(new DateInterval('P1D'));
        }

        return $trend_data;
    }

    /**
     * Get daily attendance rate for all classes
     */
    private function get_daily_attendance_rate($classes, $date) {
        $total_present = 0;
        $total_students = 0;

        foreach ($classes as $class_id) {
            $attendance_record = get_posts(array(
                'post_type' => 'sms_attendance',
                'posts_per_page' => 1,
                'meta_query' => array(
                    'relation' => 'AND',
                    array(
                        'key' => 'attendance_class',
                        'value' => $class_id,
                        'compare' => '='
                    ),
                    array(
                        'key' => 'attendance_date',
                        'value' => $date,
                        'compare' => '='
                    )
                )
            ));

            if (!empty($attendance_record)) {
                $record = $attendance_record[0];
                $present_count = get_field('present_count', $record->ID) ?: 0;
                $late_count = get_post_meta($record->ID, '_late_count', true) ?: 0;
                $excused_count = get_post_meta($record->ID, '_excused_count', true) ?: 0;
                $total_count = get_field('total_students', $record->ID) ?: 0;

                $total_present += ($present_count + $late_count + $excused_count);
                $total_students += $total_count;
            }
        }

        return $total_students > 0 ? round(($total_present / $total_students) * 100, 2) : 0;
    }

    /**
     * Count school days between dates (excluding weekends)
     */
    private function count_school_days($start_date, $end_date) {
        $start = new DateTime($start_date);
        $end = new DateTime($end_date);
        $days = 0;

        while ($start <= $end) {
            // Count only weekdays (Monday to Friday)
            if ($start->format('N') < 6) {
                $days++;
            }
            $start->add(new DateInterval('P1D'));
        }

        return $days;
    }

    /**
     * Format period for display
     */
    private function format_period($start_date, $end_date, $report_type) {
        $start = new DateTime($start_date);
        $end = new DateTime($end_date);

        switch ($report_type) {
            case 'daily':
                return $start->format('F j, Y');
            case 'weekly':
                return $start->format('M j') . ' - ' . $end->format('M j, Y');
            case 'monthly':
                return $start->format('F Y');
            case 'term':
                return 'Term Report: ' . $start->format('M j') . ' - ' . $end->format('M j, Y');
            default:
                return $start->format('M j, Y') . ' - ' . $end->format('M j, Y');
        }
    }

    /**
     * Generate CSV content for export
     */
    private function generate_csv_content($report_data) {
        $csv_content = '';
        
        // CSV Headers
        $headers = array(
            'Student Name',
            'Admission Number',
            'Class',
            'Present Days',
            'Absent Days',
            'Late Days',
            'Excused Days',
            'Total Days',
            'Attendance Rate (%)',
            'Status'
        );
        
        $csv_content .= implode(',', $headers) . "\n";
        
        // CSV Data
        foreach ($report_data['students'] as $student) {
            $row = array(
                '"' . $student['student_name'] . '"',
                $student['admission_number'],
                '"' . $student['class_name'] . '"',
                $student['present_days'],
                $student['absent_days'],
                $student['late_days'],
                $student['excused_days'],
                $student['total_days'],
                $student['attendance_rate'],
                ucfirst($student['status'])
            );
            
            $csv_content .= implode(',', $row) . "\n";
        }
        
        return $csv_content;
    }
}

// Initialize the class
new SMS_Attendance_Reports();