<?php
/**
 * Academic Reporting System
 *
 * Handles attendance reports with class-wise and student-wise breakdowns,
 * academic performance tracking, enrollment statistics, and report export
 * functionality (PDF, Excel, CSV).
 *
 * @package    School_Management_System
 * @subpackage School_Management_System/includes/admin
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Academic Reporter Class
 */
class SMS_Academic_Reporter extends SMS_Base {

    /**
     * Report cache duration in seconds
     */
    const CACHE_DURATION = 1800; // 30 minutes

    /**
     * Initialize the class and set its properties.
     */
    public function __construct() {
        parent::__construct();
        
        // Hook into report generation
        add_action('sms_generate_academic_report', [$this, 'generate_report'], 10, 3);
        add_action('sms_export_academic_report', [$this, 'export_report'], 10, 4);
        
        // Scheduled report generation
        add_action('sms_generate_scheduled_academic_reports', [$this, 'generate_scheduled_reports']);
        
        // Clear cache when academic data changes
        add_action('sms_attendance_marked', [$this, 'clear_report_cache']);
        add_action('sms_student_enrolled', [$this, 'clear_report_cache']);
        add_action('sms_student_transferred', [$this, 'clear_report_cache']);
    }

    /**
     * Generate comprehensive academic report.
     *
     * @param string $report_type Report type (attendance, performance, enrollment, etc.)
     * @param array  $date_range  Date range for the report
     * @param array  $filters     Additional filters
     * @return array|WP_Error Report data or error
     */
    public function generate_report($report_type, $date_range, $filters = []) {
        try {
            // Validate inputs
            $validation = $this->validate_report_parameters($report_type, $date_range, $filters);
            if (is_wp_error($validation)) {
                return $validation;
            }

            // Check cache first
            $cache_key = $this->get_cache_key($report_type, $date_range, $filters);
            $cached_report = get_transient($cache_key);
            if ($cached_report !== false && empty($filters['bypass_cache'])) {
                return $cached_report;
            }

            // Generate report based on type
            switch ($report_type) {
                case 'attendance':
                    $report_data = $this->generate_attendance_report($date_range, $filters);
                    break;
                    
                case 'attendance_summary':
                    $report_data = $this->generate_attendance_summary_report($date_range, $filters);
                    break;
                    
                case 'student_attendance':
                    $report_data = $this->generate_student_attendance_report($date_range, $filters);
                    break;
                    
                case 'class_attendance':
                    $report_data = $this->generate_class_attendance_report($date_range, $filters);
                    break;
                    
                case 'enrollment_statistics':
                    $report_data = $this->generate_enrollment_statistics_report($date_range, $filters);
                    break;
                    
                case 'academic_performance':
                    $report_data = $this->generate_academic_performance_report($date_range, $filters);
                    break;
                    
                case 'class_performance':
                    $report_data = $this->generate_class_performance_report($date_range, $filters);
                    break;
                    
                case 'student_progress':
                    $report_data = $this->generate_student_progress_report($date_range, $filters);
                    break;
                    
                default:
                    return new WP_Error('invalid_report_type', __('Invalid academic report type specified.', 'school-management-system'));
            }

            if (is_wp_error($report_data)) {
                return $report_data;
            }

            // Add metadata
            $report_data['metadata'] = [
                'report_type' => $report_type,
                'date_range' => $date_range,
                'filters' => $filters,
                'generated_at' => current_time('mysql'),
                'generated_by' => get_current_user_id(),
                'cache_key' => $cache_key
            ];

            // Cache the report
            set_transient($cache_key, $report_data, self::CACHE_DURATION);

            // Log report generation
            $this->log_activity(
                get_current_user_id(),
                'academic_report_generated',
                'report',
                0,
                [
                    'report_type' => $report_type,
                    'date_range' => $date_range
                ]
            );

            return $report_data;

        } catch (Exception $e) {
            return new WP_Error('report_generation_failed', $e->getMessage());
        }
    }

    /**
     * Generate comprehensive attendance report.
     *
     * @param array $date_range Date range
     * @param array $filters    Filters
     * @return array Attendance report data
     */
    private function generate_attendance_report($date_range, $filters = []) {
        $report = [
            'summary' => [],
            'class_breakdown' => [],
            'student_breakdown' => [],
            'daily_trends' => [],
            'monthly_trends' => [],
            'visualizations' => []
        ];

        // Get attendance records for the period
        $attendance_records = $this->get_attendance_records($date_range, $filters);
        
        // Calculate summary metrics
        $total_possible_attendance = $this->calculate_total_possible_attendance($date_range, $filters);
        $total_present = $this->calculate_total_present($attendance_records);
        $total_absent = $this->calculate_total_absent($attendance_records);
        $total_late = $this->calculate_total_late($attendance_records);
        
        $report['summary'] = [
            'total_possible_attendance' => $total_possible_attendance,
            'total_present' => $total_present,
            'total_absent' => $total_absent,
            'total_late' => $total_late,
            'overall_attendance_rate' => $total_possible_attendance > 0 ? 
                ($total_present / $total_possible_attendance) * 100 : 0,
            'absence_rate' => $total_possible_attendance > 0 ? 
                ($total_absent / $total_possible_attendance) * 100 : 0,
            'late_rate' => $total_possible_attendance > 0 ? 
                ($total_late / $total_possible_attendance) * 100 : 0,
            'period_start' => $date_range['start'],
            'period_end' => $date_range['end']
        ];

        // Class-wise breakdown
        $report['class_breakdown'] = $this->calculate_class_attendance_breakdown($attendance_records, $date_range, $filters);
        
        // Student-wise breakdown (top absentees)
        $report['student_breakdown'] = $this->calculate_student_attendance_breakdown($attendance_records, $date_range, $filters);
        
        // Daily trends
        $report['daily_trends'] = $this->calculate_daily_attendance_trends($attendance_records, $date_range);
        
        // Monthly trends (if period spans multiple months)
        if ($this->spans_multiple_months($date_range)) {
            $report['monthly_trends'] = $this->calculate_monthly_attendance_trends($attendance_records, $date_range);
        }

        // Generate visualization data
        $report['visualizations'] = [
            'attendance_overview_pie' => $this->prepare_attendance_overview_pie($report['summary']),
            'class_attendance_bar' => $this->prepare_bar_chart_data($report['class_breakdown']),
            'daily_trends_line' => $this->prepare_line_chart_data($report['daily_trends'])
        ];

        return $report;
    }

    /**
     * Generate attendance summary report.
     *
     * @param array $date_range Date range
     * @param array $filters    Filters
     * @return array Attendance summary report data
     */
    private function generate_attendance_summary_report($date_range, $filters = []) {
        $report = [
            'summary' => [],
            'grade_summary' => [],
            'weekly_summary' => [],
            'attendance_patterns' => [],
            'visualizations' => []
        ];

        // Get attendance data
        $attendance_records = $this->get_attendance_records($date_range, $filters);
        
        // Overall summary
        $report['summary'] = $this->calculate_overall_attendance_summary($attendance_records, $date_range);
        
        // Grade-wise summary
        $report['grade_summary'] = $this->calculate_grade_attendance_summary($attendance_records, $date_range);
        
        // Weekly summary
        $report['weekly_summary'] = $this->calculate_weekly_attendance_summary($attendance_records, $date_range);
        
        // Attendance patterns (day of week analysis)
        $report['attendance_patterns'] = $this->analyze_attendance_patterns($attendance_records);

        // Generate visualizations
        $report['visualizations'] = [
            'grade_summary_bar' => $this->prepare_bar_chart_data($report['grade_summary']),
            'weekly_trends_line' => $this->prepare_line_chart_data($report['weekly_summary']),
            'day_patterns_bar' => $this->prepare_bar_chart_data($report['attendance_patterns'])
        ];

        return $report;
    }

    /**
     * Generate student-specific attendance report.
     *
     * @param array $date_range Date range
     * @param array $filters    Filters
     * @return array Student attendance report data
     */
    private function generate_student_attendance_report($date_range, $filters = []) {
        $report = [
            'students' => [],
            'summary' => [],
            'visualizations' => []
        ];

        // Get students based on filters
        $students = $this->get_students_for_report($filters);
        
        foreach ($students as $student) {
            $student_attendance = $this->calculate_student_attendance($student['id'], $date_range);
            
            $report['students'][] = [
                'student_id' => $student['id'],
                'student_name' => $student['name'],
                'admission_number' => $student['admission_number'],
                'class' => $student['class'],
                'attendance_data' => $student_attendance
            ];
        }

        // Calculate summary statistics
        $report['summary'] = $this->calculate_student_report_summary($report['students']);

        // Generate visualizations
        $report['visualizations'] = [
            'student_attendance_comparison' => $this->prepare_student_comparison_chart($report['students'])
        ];

        return $report;
    }

    /**
     * Generate class-specific attendance report.
     *
     * @param array $date_range Date range
     * @param array $filters    Filters
     * @return array Class attendance report data
     */
    private function generate_class_attendance_report($date_range, $filters = []) {
        $report = [
            'classes' => [],
            'summary' => [],
            'visualizations' => []
        ];

        // Get classes based on filters
        $classes = $this->get_classes_for_report($filters);
        
        foreach ($classes as $class) {
            $class_attendance = $this->calculate_class_attendance($class['id'], $date_range);
            
            $report['classes'][] = [
                'class_id' => $class['id'],
                'class_name' => $class['name'],
                'grade_level' => $class['grade_level'],
                'total_students' => $class['student_count'],
                'attendance_data' => $class_attendance
            ];
        }

        // Calculate summary statistics
        $report['summary'] = $this->calculate_class_report_summary($report['classes']);

        // Generate visualizations
        $report['visualizations'] = [
            'class_attendance_comparison' => $this->prepare_class_comparison_chart($report['classes'])
        ];

        return $report;
    }

    /**
     * Generate enrollment statistics report.
     *
     * @param array $date_range Date range
     * @param array $filters    Filters
     * @return array Enrollment statistics report data
     */
    private function generate_enrollment_statistics_report($date_range, $filters = []) {
        $report = [
            'summary' => [],
            'by_grade' => [],
            'by_class' => [],
            'enrollment_trends' => [],
            'demographics' => [],
            'visualizations' => []
        ];

        // Get enrollment data
        $enrollment_data = $this->get_enrollment_data($date_range, $filters);
        
        // Summary statistics
        $report['summary'] = [
            'total_students' => $enrollment_data['total_students'],
            'new_enrollments' => $enrollment_data['new_enrollments'],
            'transfers_in' => $enrollment_data['transfers_in'],
            'transfers_out' => $enrollment_data['transfers_out'],
            'withdrawals' => $enrollment_data['withdrawals'],
            'net_enrollment_change' => $enrollment_data['net_change']
        ];

        // Enrollment by grade
        $report['by_grade'] = $this->calculate_enrollment_by_grade($enrollment_data);
        
        // Enrollment by class
        $report['by_class'] = $this->calculate_enrollment_by_class($enrollment_data);
        
        // Enrollment trends over time
        $report['enrollment_trends'] = $this->calculate_enrollment_trends($date_range, $filters);
        
        // Demographics breakdown
        $report['demographics'] = $this->calculate_enrollment_demographics($enrollment_data);

        // Generate visualizations
        $report['visualizations'] = [
            'grade_enrollment_pie' => $this->prepare_pie_chart_data($report['by_grade']),
            'enrollment_trends_line' => $this->prepare_line_chart_data($report['enrollment_trends']),
            'demographics_bar' => $this->prepare_bar_chart_data($report['demographics'])
        ];

        return $report;
    }

    /**
     * Generate academic performance report.
     *
     * @param array $date_range Date range
     * @param array $filters    Filters
     * @return array Academic performance report data
     */
    private function generate_academic_performance_report($date_range, $filters = []) {
        $report = [
            'summary' => [],
            'by_subject' => [],
            'by_class' => [],
            'performance_trends' => [],
            'grade_distribution' => [],
            'visualizations' => []
        ];

        // Note: This is a placeholder implementation as the system doesn't have
        // a grades/marks system implemented yet. This would integrate with
        // assessment and grading modules when available.

        $report['summary'] = [
            'total_assessments' => 0,
            'average_performance' => 0,
            'top_performers' => [],
            'improvement_needed' => []
        ];

        // Placeholder data structure for future implementation
        $report['by_subject'] = [];
        $report['by_class'] = [];
        $report['performance_trends'] = [];
        $report['grade_distribution'] = [];

        $report['visualizations'] = [
            'performance_overview' => [],
            'subject_performance' => [],
            'grade_distribution_pie' => []
        ];

        return $report;
    }

    /**
     * Export report in specified format.
     *
     * @param array  $report_data Report data
     * @param string $format      Export format (pdf, excel, csv)
     * @param string $filename    Output filename
     * @param array  $options     Export options
     * @return string|WP_Error File path or error
     */
    public function export_report($report_data, $format, $filename, $options = []) {
        try {
            switch ($format) {
                case 'pdf':
                    return $this->export_to_pdf($report_data, $filename, $options);
                    
                case 'excel':
                    return $this->export_to_excel($report_data, $filename, $options);
                    
                case 'csv':
                    return $this->export_to_csv($report_data, $filename, $options);
                    
                default:
                    return new WP_Error('invalid_format', __('Invalid export format specified.', 'school-management-system'));
            }
        } catch (Exception $e) {
            return new WP_Error('export_failed', $e->getMessage());
        }
    }

    /**
     * Get attendance records for the specified period.
     *
     * @param array $date_range Date range
     * @param array $filters    Additional filters
     * @return array Attendance records
     */
    private function get_attendance_records($date_range, $filters = []) {
        $args = [
            'post_type' => 'sms_attendance',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => 'attendance_date',
                    'value' => [$date_range['start'], $date_range['end']],
                    'compare' => 'BETWEEN',
                    'type' => 'DATE'
                ]
            ]
        ];

        // Apply additional filters
        if (!empty($filters['class_id'])) {
            $args['meta_query'][] = [
                'key' => 'class_id',
                'value' => $filters['class_id'],
                'compare' => '='
            ];
        }

        if (!empty($filters['grade_level'])) {
            $args['meta_query'][] = [
                'key' => 'grade_level',
                'value' => $filters['grade_level'],
                'compare' => '='
            ];
        }

        $attendance_posts = get_posts($args);
        
        $records = [];
        foreach ($attendance_posts as $post) {
            $attendance_data = get_field('student_attendance_data', $post->ID);
            $class_id = get_field('class_id', $post->ID);
            $date = get_field('attendance_date', $post->ID);
            
            if (!empty($attendance_data)) {
                foreach ($attendance_data as $student_id => $status) {
                    $records[] = [
                        'attendance_id' => $post->ID,
                        'student_id' => $student_id,
                        'class_id' => $class_id,
                        'date' => $date,
                        'status' => $status,
                        'marked_by' => get_field('marked_by_teacher_id', $post->ID)
                    ];
                }
            }
        }
        
        return $records;
    }

    /**
     * Calculate total possible attendance for the period.
     *
     * @param array $date_range Date range
     * @param array $filters    Filters
     * @return int Total possible attendance
     */
    private function calculate_total_possible_attendance($date_range, $filters = []) {
        // Get school days in the period
        $school_days = $this->get_school_days_in_period($date_range);
        
        // Get total students
        $total_students = $this->get_total_students_for_period($date_range, $filters);
        
        return count($school_days) * $total_students;
    }

    /**
     * Calculate total present from attendance records.
     *
     * @param array $attendance_records Attendance records
     * @return int Total present
     */
    private function calculate_total_present($attendance_records) {
        return count(array_filter($attendance_records, function($record) {
            return $record['status'] === 'present';
        }));
    }

    /**
     * Calculate total absent from attendance records.
     *
     * @param array $attendance_records Attendance records
     * @return int Total absent
     */
    private function calculate_total_absent($attendance_records) {
        return count(array_filter($attendance_records, function($record) {
            return $record['status'] === 'absent';
        }));
    }

    /**
     * Calculate total late from attendance records.
     *
     * @param array $attendance_records Attendance records
     * @return int Total late
     */
    private function calculate_total_late($attendance_records) {
        return count(array_filter($attendance_records, function($record) {
            return $record['status'] === 'late';
        }));
    }

    /**
     * Calculate class attendance breakdown.
     *
     * @param array $attendance_records Attendance records
     * @param array $date_range         Date range
     * @param array $filters            Filters
     * @return array Class breakdown
     */
    private function calculate_class_attendance_breakdown($attendance_records, $date_range, $filters = []) {
        $breakdown = [];
        
        // Group by class
        $by_class = [];
        foreach ($attendance_records as $record) {
            $class_id = $record['class_id'];
            if (!isset($by_class[$class_id])) {
                $by_class[$class_id] = [];
            }
            $by_class[$class_id][] = $record;
        }
        
        foreach ($by_class as $class_id => $class_records) {
            $class_name = get_the_title($class_id);
            $total_records = count($class_records);
            $present_count = count(array_filter($class_records, function($r) { return $r['status'] === 'present'; }));
            $absent_count = count(array_filter($class_records, function($r) { return $r['status'] === 'absent'; }));
            $late_count = count(array_filter($class_records, function($r) { return $r['status'] === 'late'; }));
            
            $breakdown[] = [
                'name' => $class_name,
                'class_id' => $class_id,
                'total_records' => $total_records,
                'present_count' => $present_count,
                'absent_count' => $absent_count,
                'late_count' => $late_count,
                'attendance_rate' => $total_records > 0 ? ($present_count / $total_records) * 100 : 0,
                'absence_rate' => $total_records > 0 ? ($absent_count / $total_records) * 100 : 0
            ];
        }
        
        // Sort by attendance rate descending
        usort($breakdown, function($a, $b) {
            return $b['attendance_rate'] <=> $a['attendance_rate'];
        });
        
        return $breakdown;
    }

    /**
     * Calculate student attendance breakdown.
     *
     * @param array $attendance_records Attendance records
     * @param array $date_range         Date range
     * @param array $filters            Filters
     * @return array Student breakdown
     */
    private function calculate_student_attendance_breakdown($attendance_records, $date_range, $filters = []) {
        $breakdown = [];
        
        // Group by student
        $by_student = [];
        foreach ($attendance_records as $record) {
            $student_id = $record['student_id'];
            if (!isset($by_student[$student_id])) {
                $by_student[$student_id] = [];
            }
            $by_student[$student_id][] = $record;
        }
        
        foreach ($by_student as $student_id => $student_records) {
            $student_name = get_field('full_name', $student_id);
            $admission_number = get_field('admission_number', $student_id);
            $class_name = get_field('current_class', $student_id);
            
            $total_records = count($student_records);
            $present_count = count(array_filter($student_records, function($r) { return $r['status'] === 'present'; }));
            $absent_count = count(array_filter($student_records, function($r) { return $r['status'] === 'absent'; }));
            $late_count = count(array_filter($student_records, function($r) { return $r['status'] === 'late'; }));
            
            $breakdown[] = [
                'name' => $student_name,
                'student_id' => $student_id,
                'admission_number' => $admission_number,
                'class' => $class_name,
                'total_records' => $total_records,
                'present_count' => $present_count,
                'absent_count' => $absent_count,
                'late_count' => $late_count,
                'attendance_rate' => $total_records > 0 ? ($present_count / $total_records) * 100 : 0,
                'absence_rate' => $total_records > 0 ? ($absent_count / $total_records) * 100 : 0
            ];
        }
        
        // Sort by absence rate descending (worst attendance first)
        usort($breakdown, function($a, $b) {
            return $b['absence_rate'] <=> $a['absence_rate'];
        });
        
        // Return top 20 for report
        return array_slice($breakdown, 0, 20);
    }

    /**
     * Calculate daily attendance trends.
     *
     * @param array $attendance_records Attendance records
     * @param array $date_range         Date range
     * @return array Daily trends
     */
    private function calculate_daily_attendance_trends($attendance_records, $date_range) {
        $trends = [];
        
        // Initialize all days in range
        $start = new DateTime($date_range['start']);
        $end = new DateTime($date_range['end']);
        
        while ($start <= $end) {
            $day_key = $start->format('Y-m-d');
            $trends[$day_key] = [
                'present' => 0,
                'absent' => 0,
                'late' => 0,
                'total' => 0
            ];
            $start->modify('+1 day');
        }
        
        // Aggregate attendance by day
        foreach ($attendance_records as $record) {
            $day_key = $record['date'];
            if (isset($trends[$day_key])) {
                $trends[$day_key]['total']++;
                $trends[$day_key][$record['status']]++;
            }
        }
        
        // Calculate attendance rates
        foreach ($trends as $day => &$data) {
            $data['attendance_rate'] = $data['total'] > 0 ? 
                ($data['present'] / $data['total']) * 100 : 0;
        }
        
        return $trends;
    }

    /**
     * Validate report parameters.
     *
     * @param string $report_type Report type
     * @param array  $date_range  Date range
     * @param array  $filters     Filters
     * @return bool|WP_Error True if valid, WP_Error if invalid
     */
    private function validate_report_parameters($report_type, $date_range, $filters) {
        $valid_report_types = [
            'attendance', 'attendance_summary', 'student_attendance', 'class_attendance',
            'enrollment_statistics', 'academic_performance', 'class_performance', 'student_progress'
        ];

        if (!in_array($report_type, $valid_report_types)) {
            return new WP_Error('invalid_report_type', __('Invalid academic report type.', 'school-management-system'));
        }

        if (empty($date_range['start']) || empty($date_range['end'])) {
            return new WP_Error('invalid_date_range', __('Start and end dates are required.', 'school-management-system'));
        }

        if (strtotime($date_range['start']) > strtotime($date_range['end'])) {
            return new WP_Error('invalid_date_range', __('Start date must be before end date.', 'school-management-system'));
        }

        return true;
    }

    /**
     * Generate cache key for report.
     *
     * @param string $report_type Report type
     * @param array  $date_range  Date range
     * @param array  $filters     Filters
     * @return string Cache key
     */
    private function get_cache_key($report_type, $date_range, $filters) {
        $key_data = [
            'type' => $report_type,
            'start' => $date_range['start'],
            'end' => $date_range['end'],
            'filters' => $filters
        ];
        
        return 'sms_academic_report_' . md5(serialize($key_data));
    }

    /**
     * Clear report cache.
     */
    public function clear_report_cache() {
        global $wpdb;
        
        // Delete all academic report transients
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_sms_academic_report_%' 
             OR option_name LIKE '_transient_timeout_sms_academic_report_%'"
        );
    }

    /**
     * Check if date range spans multiple months.
     *
     * @param array $date_range Date range
     * @return bool Whether it spans multiple months
     */
    private function spans_multiple_months($date_range) {
        $start_month = date('Y-m', strtotime($date_range['start']));
        $end_month = date('Y-m', strtotime($date_range['end']));
        
        return $start_month !== $end_month;
    }

    /**
     * Prepare attendance overview pie chart data.
     *
     * @param array $summary Summary data
     * @return array Chart data
     */
    private function prepare_attendance_overview_pie($summary) {
        return [
            'labels' => ['Present', 'Absent', 'Late'],
            'values' => [
                $summary['total_present'],
                $summary['total_absent'],
                $summary['total_late']
            ],
            'colors' => ['#28a745', '#dc3545', '#ffc107']
        ];
    }

    /**
     * Prepare bar chart data.
     *
     * @param array $data Data array
     * @return array Chart data
     */
    private function prepare_bar_chart_data($data) {
        $chart_data = [
            'labels' => [],
            'values' => []
        ];
        
        foreach ($data as $item) {
            $chart_data['labels'][] = $item['name'];
            $chart_data['values'][] = $item['attendance_rate'] ?? $item['amount'] ?? $item['count'] ?? 0;
        }
        
        return $chart_data;
    }

    /**
     * Prepare line chart data.
     *
     * @param array $data Data array
     * @return array Chart data
     */
    private function prepare_line_chart_data($data) {
        $chart_data = [
            'labels' => array_keys($data),
            'values' => []
        ];
        
        foreach ($data as $item) {
            if (is_array($item) && isset($item['attendance_rate'])) {
                $chart_data['values'][] = $item['attendance_rate'];
            } else {
                $chart_data['values'][] = $item;
            }
        }
        
        return $chart_data;
    }

    /**
     * Prepare pie chart data.
     *
     * @param array $data Data array
     * @return array Chart data
     */
    private function prepare_pie_chart_data($data) {
        $chart_data = [
            'labels' => [],
            'values' => [],
            'colors' => []
        ];
        
        $colors = ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40'];
        $color_index = 0;
        
        foreach ($data as $item) {
            $chart_data['labels'][] = $item['name'];
            $chart_data['values'][] = $item['count'] ?? $item['amount'] ?? 0;
            $chart_data['colors'][] = $colors[$color_index % count($colors)];
            $color_index++;
        }
        
        return $chart_data;
    }

    /**
     * Export report to CSV.
     *
     * @param array  $report_data Report data
     * @param string $filename    Filename
     * @param array  $options     Options
     * @return string File path
     */
    private function export_to_csv($report_data, $filename, $options = []) {
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['path'] . '/' . $filename . '.csv';
        
        $csv_content = $this->generate_csv_content($report_data, $options);
        file_put_contents($file_path, $csv_content);
        
        return $file_path;
    }

    /**
     * Generate CSV content from report data.
     *
     * @param array $report_data Report data
     * @param array $options     Options
     * @return string CSV content
     */
    private function generate_csv_content($report_data, $options = []) {
        $csv_lines = [];
        
        // Add header
        $csv_lines[] = 'Academic Report - ' . ($report_data['metadata']['report_type'] ?? 'Unknown');
        $csv_lines[] = 'Generated: ' . ($report_data['metadata']['generated_at'] ?? date('Y-m-d H:i:s'));
        $csv_lines[] = 'Period: ' . ($report_data['metadata']['date_range']['start'] ?? '') . ' to ' . ($report_data['metadata']['date_range']['end'] ?? '');
        $csv_lines[] = '';
        
        // Add summary data
        if (isset($report_data['summary'])) {
            $csv_lines[] = 'Summary';
            foreach ($report_data['summary'] as $key => $value) {
                if (is_numeric($value)) {
                    $csv_lines[] = ucfirst(str_replace('_', ' ', $key)) . ',' . $value;
                }
            }
            $csv_lines[] = '';
        }
        
        // Add breakdown data
        if (isset($report_data['class_breakdown'])) {
            $csv_lines[] = 'Class Breakdown';
            $csv_lines[] = 'Class Name,Total Records,Present,Absent,Late,Attendance Rate %';
            
            foreach ($report_data['class_breakdown'] as $class) {
                $csv_lines[] = implode(',', [
                    $class['name'],
                    $class['total_records'],
                    $class['present_count'],
                    $class['absent_count'],
                    $class['late_count'],
                    number_format($class['attendance_rate'], 2)
                ]);
            }
            $csv_lines[] = '';
        }
        
        if (isset($report_data['student_breakdown'])) {
            $csv_lines[] = 'Student Breakdown (Top Absentees)';
            $csv_lines[] = 'Student Name,Admission Number,Class,Total Records,Present,Absent,Late,Attendance Rate %';
            
            foreach ($report_data['student_breakdown'] as $student) {
                $csv_lines[] = implode(',', [
                    $student['name'],
                    $student['admission_number'],
                    $student['class'],
                    $student['total_records'],
                    $student['present_count'],
                    $student['absent_count'],
                    $student['late_count'],
                    number_format($student['attendance_rate'], 2)
                ]);
            }
            $csv_lines[] = '';
        }
        
        return implode("\n", $csv_lines);
    }

    /**
     * Export to PDF placeholder.
     *
     * @param array  $report_data Report data
     * @param string $filename    Filename
     * @param array  $options     Options
     * @return string File path
     */
    private function export_to_pdf($report_data, $filename, $options = []) {
        // Placeholder for PDF generation
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['path'] . '/' . $filename . '.pdf';
        
        // This would integrate with a PDF library
        file_put_contents($file_path, 'PDF content would be generated here');
        
        return $file_path;
    }

    /**
     * Export to Excel placeholder.
     *
     * @param array  $report_data Report data
     * @param string $filename    Filename
     * @param array  $options     Options
     * @return string File path
     */
    private function export_to_excel($report_data, $filename, $options = []) {
        // Placeholder for Excel generation
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['path'] . '/' . $filename . '.xlsx';
        
        // This would integrate with PhpSpreadsheet
        file_put_contents($file_path, 'Excel content would be generated here');
        
        return $file_path;
    }

    // Additional helper methods would be implemented here...
    // Due to length constraints, showing core structure
}