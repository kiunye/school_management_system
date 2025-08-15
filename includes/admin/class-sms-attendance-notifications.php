<?php
/**
 * Attendance Notifications System
 *
 * @package SchoolManagementSystem
 * @subpackage Admin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class SMS_Attendance_Notifications
 * 
 * Handles automated notifications for student absences and attendance alerts
 */
class SMS_Attendance_Notifications {

    /**
     * Constructor
     */
    public function __construct() {
        // Hook into attendance marking to send notifications
        add_action('sms_student_absent', array($this, 'handle_student_absence'), 10, 4);
        
        // Schedule daily attendance summary
        add_action('init', array($this, 'schedule_daily_summary'));
        add_action('sms_daily_attendance_summary', array($this, 'send_daily_attendance_summary'));
        
        // Schedule weekly attendance alerts
        add_action('init', array($this, 'schedule_weekly_alerts'));
        add_action('sms_weekly_attendance_alerts', array($this, 'send_weekly_attendance_alerts'));
        
        // Admin settings for notifications
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    /**
     * Handle student absence notification
     */
    public function handle_student_absence($student_id, $class_id, $date, $attendance_data = null) {
        // Check if notifications are enabled
        if (!$this->are_absence_notifications_enabled()) {
            return;
        }

        // Get student information
        $student = get_post($student_id);
        if (!$student) {
            return;
        }

        $student_name = get_field('full_name', $student_id);
        $admission_number = get_field('admission_number', $student_id);
        $class_name = get_field('class_name', $class_id);
        
        // Get parent information
        $parent_contacts = $this->get_student_parent_contacts($student_id);
        
        if (empty($parent_contacts)) {
            error_log("SMS Attendance: No parent contacts found for student ID {$student_id}");
            return;
        }

        // Prepare notification data
        $notification_data = array(
            'student_id' => $student_id,
            'student_name' => $student_name,
            'admission_number' => $admission_number,
            'class_name' => $class_name,
            'absence_date' => $date,
            'absence_notes' => isset($attendance_data['notes']) ? $attendance_data['notes'] : '',
            'parent_contacts' => $parent_contacts,
        );

        // Send immediate absence notification
        $this->send_absence_notification($notification_data);
        
        // Log the notification
        $this->log_notification('absence', $student_id, $notification_data);
    }

    /**
     * Send absence notification to parents
     */
    private function send_absence_notification($data) {
        $message_template = $this->get_absence_message_template();
        
        // Replace placeholders in message
        $message = str_replace(
            array(
                '{student_name}',
                '{admission_number}',
                '{class_name}',
                '{absence_date}',
                '{school_name}',
                '{school_phone}'
            ),
            array(
                $data['student_name'],
                $data['admission_number'],
                $data['class_name'],
                date('d/m/Y', strtotime($data['absence_date'])),
                get_option('sms_school_name', get_bloginfo('name')),
                get_option('sms_school_phone', '')
            ),
            $message_template
        );

        // Send SMS to parent contacts
        foreach ($data['parent_contacts'] as $contact) {
            if (!empty($contact['phone'])) {
                $this->send_sms_notification($contact['phone'], $message, 'absence');
            }
        }

        // Send email notification if enabled
        if ($this->are_email_notifications_enabled()) {
            foreach ($data['parent_contacts'] as $contact) {
                if (!empty($contact['email'])) {
                    $this->send_email_notification($contact['email'], $data, 'absence');
                }
            }
        }
    }

    /**
     * Schedule daily attendance summary
     */
    public function schedule_daily_summary() {
        if (!wp_next_scheduled('sms_daily_attendance_summary')) {
            // Schedule for 6 PM daily
            wp_schedule_event(
                strtotime('today 18:00'),
                'daily',
                'sms_daily_attendance_summary'
            );
        }
    }

    /**
     * Send daily attendance summary to administrators
     */
    public function send_daily_attendance_summary() {
        if (!$this->are_daily_summaries_enabled()) {
            return;
        }

        $today = date('Y-m-d');
        $summary_data = $this->generate_daily_summary($today);
        
        if (empty($summary_data['classes'])) {
            return; // No attendance data for today
        }

        // Get administrator contacts
        $admin_contacts = $this->get_administrator_contacts();
        
        if (empty($admin_contacts)) {
            return;
        }

        // Prepare summary message
        $message = $this->format_daily_summary_message($summary_data);
        
        // Send to administrators
        foreach ($admin_contacts as $contact) {
            if (!empty($contact['phone'])) {
                $this->send_sms_notification($contact['phone'], $message, 'daily_summary');
            }
            
            if (!empty($contact['email']) && $this->are_email_notifications_enabled()) {
                $this->send_email_notification($contact['email'], $summary_data, 'daily_summary');
            }
        }
    }

    /**
     * Schedule weekly attendance alerts
     */
    public function schedule_weekly_alerts() {
        if (!wp_next_scheduled('sms_weekly_attendance_alerts')) {
            // Schedule for Friday 5 PM weekly
            wp_schedule_event(
                strtotime('next Friday 17:00'),
                'weekly',
                'sms_weekly_attendance_alerts'
            );
        }
    }

    /**
     * Send weekly attendance alerts for chronic absentees
     */
    public function send_weekly_attendance_alerts() {
        if (!$this->are_weekly_alerts_enabled()) {
            return;
        }

        $end_date = date('Y-m-d');
        $start_date = date('Y-m-d', strtotime('-7 days'));
        
        // Get students with poor attendance
        $chronic_absentees = $this->get_chronic_absentees($start_date, $end_date);
        
        if (empty($chronic_absentees)) {
            return;
        }

        // Send alerts to parents and administrators
        foreach ($chronic_absentees as $student_data) {
            $this->send_chronic_absentee_alert($student_data);
        }
    }

    /**
     * Generate daily attendance summary
     */
    private function generate_daily_summary($date) {
        $classes = get_posts(array(
            'post_type' => 'sms_classes',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => 'class_status',
                    'value' => 'active',
                    'compare' => '='
                )
            )
        ));

        $summary_data = array(
            'date' => $date,
            'classes' => array(),
            'totals' => array(
                'total_students' => 0,
                'total_present' => 0,
                'total_absent' => 0,
                'total_late' => 0,
                'total_excused' => 0,
                'overall_attendance_rate' => 0,
            )
        );

        foreach ($classes as $class) {
            $attendance_record = get_posts(array(
                'post_type' => 'sms_attendance',
                'posts_per_page' => 1,
                'meta_query' => array(
                    'relation' => 'AND',
                    array(
                        'key' => 'attendance_class',
                        'value' => $class->ID,
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
                $present = get_field('present_count', $record->ID) ?: 0;
                $absent = get_field('absent_count', $record->ID) ?: 0;
                $late = get_post_meta($record->ID, '_late_count', true) ?: 0;
                $excused = get_post_meta($record->ID, '_excused_count', true) ?: 0;
                $total = get_field('total_students', $record->ID) ?: 0;

                $class_data = array(
                    'class_name' => get_field('class_name', $class->ID),
                    'class_code' => get_field('class_code', $class->ID),
                    'present' => $present,
                    'absent' => $absent,
                    'late' => $late,
                    'excused' => $excused,
                    'total' => $total,
                    'attendance_rate' => $total > 0 ? round((($present + $late + $excused) / $total) * 100, 1) : 0,
                );

                $summary_data['classes'][] = $class_data;
                
                // Add to totals
                $summary_data['totals']['total_students'] += $total;
                $summary_data['totals']['total_present'] += $present;
                $summary_data['totals']['total_absent'] += $absent;
                $summary_data['totals']['total_late'] += $late;
                $summary_data['totals']['total_excused'] += $excused;
            }
        }

        // Calculate overall attendance rate
        $total_attended = $summary_data['totals']['total_present'] + 
                         $summary_data['totals']['total_late'] + 
                         $summary_data['totals']['total_excused'];
        
        if ($summary_data['totals']['total_students'] > 0) {
            $summary_data['totals']['overall_attendance_rate'] = round(
                ($total_attended / $summary_data['totals']['total_students']) * 100, 1
            );
        }

        return $summary_data;
    }

    /**
     * Get chronic absentees (students with attendance rate < 75% in the given period)
     */
    private function get_chronic_absentees($start_date, $end_date) {
        $chronic_absentees = array();
        $threshold = get_option('sms_chronic_absentee_threshold', 75); // Default 75%

        $classes = get_posts(array(
            'post_type' => 'sms_classes',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => 'class_status',
                    'value' => 'active',
                    'compare' => '='
                )
            )
        ));

        foreach ($classes as $class) {
            $students = get_posts(array(
                'post_type' => 'sms_students',
                'posts_per_page' => -1,
                'meta_query' => array(
                    array(
                        'key' => 'assigned_class',
                        'value' => $class->ID,
                        'compare' => '='
                    ),
                    array(
                        'key' => 'student_status',
                        'value' => 'active',
                        'compare' => '='
                    )
                )
            ));

            foreach ($students as $student) {
                $attendance_stats = $this->calculate_student_attendance_stats(
                    $student->ID, 
                    $class->ID, 
                    $start_date, 
                    $end_date
                );

                if ($attendance_stats['attendance_rate'] < $threshold && $attendance_stats['total_days'] > 0) {
                    $chronic_absentees[] = array(
                        'student_id' => $student->ID,
                        'student_name' => get_field('full_name', $student->ID),
                        'admission_number' => get_field('admission_number', $student->ID),
                        'class_name' => get_field('class_name', $class->ID),
                        'attendance_stats' => $attendance_stats,
                        'parent_contacts' => $this->get_student_parent_contacts($student->ID),
                    );
                }
            }
        }

        return $chronic_absentees;
    }

    /**
     * Calculate student attendance statistics for a period
     */
    private function calculate_student_attendance_stats($student_id, $class_id, $start_date, $end_date) {
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

        // Calculate attendance rate
        if ($stats['total_days'] > 0) {
            $attended_days = $stats['present'] + $stats['late'] + $stats['excused'];
            $stats['attendance_rate'] = round(($attended_days / $stats['total_days']) * 100, 2);
        }

        return $stats;
    }

    /**
     * Send chronic absentee alert
     */
    private function send_chronic_absentee_alert($student_data) {
        $message_template = $this->get_chronic_absentee_message_template();
        
        $message = str_replace(
            array(
                '{student_name}',
                '{admission_number}',
                '{class_name}',
                '{attendance_rate}',
                '{absent_days}',
                '{total_days}',
                '{school_name}',
                '{school_phone}'
            ),
            array(
                $student_data['student_name'],
                $student_data['admission_number'],
                $student_data['class_name'],
                $student_data['attendance_stats']['attendance_rate'] . '%',
                $student_data['attendance_stats']['absent'],
                $student_data['attendance_stats']['total_days'],
                get_option('sms_school_name', get_bloginfo('name')),
                get_option('sms_school_phone', '')
            ),
            $message_template
        );

        // Send to parents
        foreach ($student_data['parent_contacts'] as $contact) {
            if (!empty($contact['phone'])) {
                $this->send_sms_notification($contact['phone'], $message, 'chronic_absentee');
            }
        }

        // Also notify administrators
        $admin_contacts = $this->get_administrator_contacts();
        foreach ($admin_contacts as $contact) {
            if (!empty($contact['phone'])) {
                $admin_message = "ALERT: " . $student_data['student_name'] . " (" . $student_data['admission_number'] . ") has " . $student_data['attendance_stats']['attendance_rate'] . "% attendance rate.";
                $this->send_sms_notification($contact['phone'], $admin_message, 'admin_alert');
            }
        }
    }

    /**
     * Get student parent contacts
     */
    private function get_student_parent_contacts($student_id) {
        $parent_details = get_field('parent_details', $student_id);
        $contacts = array();

        if ($parent_details) {
            // Handle both JSON string and array formats
            if (is_string($parent_details)) {
                $parent_details = json_decode($parent_details, true);
            }

            if (is_array($parent_details)) {
                // Primary parent/guardian
                if (!empty($parent_details['primary_parent'])) {
                    $contacts[] = array(
                        'name' => $parent_details['primary_parent']['name'] ?? '',
                        'phone' => $parent_details['primary_parent']['phone'] ?? '',
                        'email' => $parent_details['primary_parent']['email'] ?? '',
                        'relationship' => $parent_details['primary_parent']['relationship'] ?? 'Parent',
                    );
                }

                // Secondary parent/guardian
                if (!empty($parent_details['secondary_parent'])) {
                    $contacts[] = array(
                        'name' => $parent_details['secondary_parent']['name'] ?? '',
                        'phone' => $parent_details['secondary_parent']['phone'] ?? '',
                        'email' => $parent_details['secondary_parent']['email'] ?? '',
                        'relationship' => $parent_details['secondary_parent']['relationship'] ?? 'Parent',
                    );
                }
            }
        }

        return $contacts;
    }

    /**
     * Get administrator contacts
     */
    private function get_administrator_contacts() {
        $admin_users = get_users(array(
            'role__in' => array('administrator', 'sms_administrator'),
            'meta_query' => array(
                array(
                    'key' => 'sms_receive_notifications',
                    'value' => '1',
                    'compare' => '='
                )
            )
        ));

        $contacts = array();
        foreach ($admin_users as $user) {
            $phone = get_user_meta($user->ID, 'sms_phone_number', true);
            if (!empty($phone)) {
                $contacts[] = array(
                    'name' => $user->display_name,
                    'phone' => $phone,
                    'email' => $user->user_email,
                );
            }
        }

        return $contacts;
    }

    /**
     * Send SMS notification
     */
    private function send_sms_notification($phone, $message, $type) {
        // Check if SMS notifications are enabled
        if (!$this->are_sms_notifications_enabled()) {
            return false;
        }

        // Use the SMS integration (Africastalking or other)
        if (class_exists('SMS_Communication_Handler')) {
            $sms_handler = new SMS_Communication_Handler();
            return $sms_handler->send_sms($phone, $message, array('type' => $type));
        }

        // Fallback: trigger action for other SMS integrations
        do_action('sms_send_notification', $phone, $message, $type);
        
        return true;
    }

    /**
     * Send email notification
     */
    private function send_email_notification($email, $data, $type) {
        $subject = $this->get_email_subject($type);
        $message = $this->get_email_message($data, $type);
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_option('sms_school_name', get_bloginfo('name')) . ' <' . get_option('admin_email') . '>'
        );

        return wp_mail($email, $subject, $message, $headers);
    }

    /**
     * Get message templates
     */
    private function get_absence_message_template() {
        return get_option('sms_absence_message_template', 
            'Dear Parent, {student_name} ({admission_number}) was absent from {class_name} today ({absence_date}). Please contact {school_name} at {school_phone} if this is unexpected.'
        );
    }

    private function get_chronic_absentee_message_template() {
        return get_option('sms_chronic_absentee_message_template',
            'ATTENDANCE ALERT: {student_name} ({admission_number}) has {attendance_rate} attendance rate ({absent_days}/{total_days} days). Please contact {school_name} at {school_phone} to discuss.'
        );
    }

    /**
     * Format daily summary message
     */
    private function format_daily_summary_message($summary_data) {
        $message = "Daily Attendance Summary (" . date('d/m/Y', strtotime($summary_data['date'])) . "):\n";
        $message .= "Overall: " . $summary_data['totals']['overall_attendance_rate'] . "% ";
        $message .= "(" . $summary_data['totals']['total_present'] . "/" . $summary_data['totals']['total_students'] . " present)\n";
        
        if ($summary_data['totals']['total_absent'] > 0) {
            $message .= "Absent: " . $summary_data['totals']['total_absent'] . " students\n";
        }
        
        return $message;
    }

    /**
     * Settings and configuration methods
     */
    private function are_absence_notifications_enabled() {
        return get_option('sms_enable_absence_notifications', '1') === '1';
    }

    private function are_sms_notifications_enabled() {
        return get_option('sms_enable_sms_notifications', '1') === '1';
    }

    private function are_email_notifications_enabled() {
        return get_option('sms_enable_email_notifications', '0') === '1';
    }

    private function are_daily_summaries_enabled() {
        return get_option('sms_enable_daily_summaries', '1') === '1';
    }

    private function are_weekly_alerts_enabled() {
        return get_option('sms_enable_weekly_alerts', '1') === '1';
    }

    /**
     * Get email subject and message templates
     */
    private function get_email_subject($type) {
        $subjects = array(
            'absence' => 'Student Absence Notification',
            'daily_summary' => 'Daily Attendance Summary',
            'chronic_absentee' => 'Attendance Alert - Action Required',
        );
        
        return $subjects[$type] ?? 'School Notification';
    }

    private function get_email_message($data, $type) {
        // Return HTML formatted email message based on type
        switch ($type) {
            case 'absence':
                return $this->get_absence_email_template($data);
            case 'daily_summary':
                return $this->get_daily_summary_email_template($data);
            case 'chronic_absentee':
                return $this->get_chronic_absentee_email_template($data);
            default:
                return '';
        }
    }

    private function get_absence_email_template($data) {
        return "
        <h2>Student Absence Notification</h2>
        <p>Dear Parent/Guardian,</p>
        <p>This is to inform you that <strong>{$data['student_name']}</strong> (Admission No: {$data['admission_number']}) was marked absent from <strong>{$data['class_name']}</strong> on <strong>" . date('d/m/Y', strtotime($data['absence_date'])) . "</strong>.</p>
        <p>If this absence was unexpected or if you have any concerns, please contact the school immediately.</p>
        <p>Best regards,<br>" . get_option('sms_school_name', get_bloginfo('name')) . "</p>
        ";
    }

    private function get_daily_summary_email_template($data) {
        $html = "<h2>Daily Attendance Summary - " . date('d/m/Y', strtotime($data['date'])) . "</h2>";
        $html .= "<p><strong>Overall Attendance Rate: " . $data['totals']['overall_attendance_rate'] . "%</strong></p>";
        $html .= "<table border='1' cellpadding='5' cellspacing='0'>";
        $html .= "<tr><th>Class</th><th>Present</th><th>Absent</th><th>Rate</th></tr>";
        
        foreach ($data['classes'] as $class) {
            $html .= "<tr>";
            $html .= "<td>" . $class['class_name'] . "</td>";
            $html .= "<td>" . $class['present'] . "</td>";
            $html .= "<td>" . $class['absent'] . "</td>";
            $html .= "<td>" . $class['attendance_rate'] . "%</td>";
            $html .= "</tr>";
        }
        
        $html .= "</table>";
        return $html;
    }

    private function get_chronic_absentee_email_template($data) {
        return "
        <h2>Attendance Alert - Action Required</h2>
        <p>Dear Parent/Guardian,</p>
        <p>We are concerned about the attendance of <strong>{$data['student_name']}</strong> (Admission No: {$data['admission_number']}) in <strong>{$data['class_name']}</strong>.</p>
        <p><strong>Current Attendance Rate: {$data['attendance_stats']['attendance_rate']}%</strong></p>
        <p>Absent Days: {$data['attendance_stats']['absent']} out of {$data['attendance_stats']['total_days']} school days</p>
        <p>Regular attendance is crucial for your child's academic success. Please contact the school to discuss this matter.</p>
        <p>Best regards,<br>" . get_option('sms_school_name', get_bloginfo('name')) . "</p>
        ";
    }

    /**
     * Log notification for tracking
     */
    private function log_notification($type, $student_id, $data) {
        $log_entry = array(
            'type' => $type,
            'student_id' => $student_id,
            'data' => json_encode($data),
            'timestamp' => current_time('mysql'),
        );
        
        // Store in custom log table or use WordPress logging
        error_log("SMS Attendance Notification: {$type} sent for student ID {$student_id}");
    }

    /**
     * Add settings page (placeholder for future implementation)
     */
    public function add_settings_page() {
        // This would add a settings page for notification configuration
        // Implementation can be added in a future task
    }

    /**
     * Register settings (placeholder for future implementation)
     */
    public function register_settings() {
        // This would register settings for notification templates and preferences
        // Implementation can be added in a future task
    }
}

// Initialize the class
new SMS_Attendance_Notifications();