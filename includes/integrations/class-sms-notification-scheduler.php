<?php
/**
 * SMS Notification Scheduler
 *
 * @package SchoolManagementSystem
 * @subpackage Integrations
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class SMS_Notification_Scheduler
 * 
 * Handles automated SMS notifications with scheduling and triggers
 */
class SMS_Notification_Scheduler {

    /**
     * Communication handler instance
     */
    private $communication_handler;

    /**
     * Notification rules
     */
    private $notification_rules;

    /**
     * Constructor
     */
    public function __construct() {
        $this->init_communication_handler();
        $this->load_notification_rules();
        
        // Schedule automated checks
        $this->schedule_automated_checks();
        
        // Add hooks for automated triggers
        $this->add_automated_triggers();
        
        // Add AJAX handlers
        add_action('wp_ajax_sms_save_notification_rule', array($this, 'save_notification_rule'));
        add_action('wp_ajax_sms_delete_notification_rule', array($this, 'delete_notification_rule'));
        add_action('wp_ajax_sms_test_notification_rule', array($this, 'test_notification_rule'));
    }

    /**
     * Initialize communication handler
     */
    private function init_communication_handler() {
        if (!class_exists('SMS_Communication_Handler')) {
            require_once SMS_PLUGIN_DIR . 'includes/core/class-sms-communication-handler.php';
        }
        $this->communication_handler = new SMS_Communication_Handler();
    }

    /**
     * Load notification rules from database
     */
    private function load_notification_rules() {
        $this->notification_rules = get_option('sms_notification_rules', $this->get_default_rules());
    }

    /**
     * Get default notification rules
     */
    private function get_default_rules() {
        return array(
            'attendance_alert' => array(
                'name' => __('Attendance Alert', 'school-management-system'),
                'description' => __('Send SMS to parents when student is marked absent', 'school-management-system'),
                'trigger' => 'attendance_marked',
                'conditions' => array(
                    'attendance_status' => 'absent'
                ),
                'template_id' => 'attendance_alert',
                'recipients' => 'student_parents',
                'enabled' => true,
                'delay_minutes' => 0,
                'priority' => 'normal'
            ),
            'fee_reminder_7_days' => array(
                'name' => __('Fee Reminder (7 days before due)', 'school-management-system'),
                'description' => __('Send fee reminder 7 days before due date', 'school-management-system'),
                'trigger' => 'scheduled',
                'schedule' => 'daily',
                'conditions' => array(
                    'days_until_due' => 7,
                    'payment_status' => 'unpaid'
                ),
                'template_id' => 'fee_reminder',
                'recipients' => 'student_parents',
                'enabled' => true,
                'delay_minutes' => 0,
                'priority' => 'normal'
            ),
            'fee_reminder_1_day' => array(
                'name' => __('Fee Reminder (1 day before due)', 'school-management-system'),
                'description' => __('Send urgent fee reminder 1 day before due date', 'school-management-system'),
                'trigger' => 'scheduled',
                'schedule' => 'daily',
                'conditions' => array(
                    'days_until_due' => 1,
                    'payment_status' => 'unpaid'
                ),
                'template_id' => 'fee_reminder',
                'recipients' => 'student_parents',
                'enabled' => true,
                'delay_minutes' => 0,
                'priority' => 'high'
            ),
            'fee_overdue' => array(
                'name' => __('Overdue Fee Notice', 'school-management-system'),
                'description' => __('Send overdue notice for unpaid fees', 'school-management-system'),
                'trigger' => 'scheduled',
                'schedule' => 'daily',
                'conditions' => array(
                    'days_overdue' => array('>', 0),
                    'payment_status' => 'unpaid'
                ),
                'template_id' => 'fee_overdue',
                'recipients' => 'student_parents',
                'enabled' => true,
                'delay_minutes' => 0,
                'priority' => 'urgent'
            ),
            'payment_confirmation' => array(
                'name' => __('Payment Confirmation', 'school-management-system'),
                'description' => __('Send confirmation when payment is received', 'school-management-system'),
                'trigger' => 'payment_received',
                'conditions' => array(),
                'template_id' => 'payment_confirmation',
                'recipients' => 'student_parents',
                'enabled' => true,
                'delay_minutes' => 0,
                'priority' => 'normal'
            ),
            'exam_reminder' => array(
                'name' => __('Exam Reminder', 'school-management-system'),
                'description' => __('Send exam reminder 2 days before exam', 'school-management-system'),
                'trigger' => 'scheduled',
                'schedule' => 'daily',
                'conditions' => array(
                    'days_until_exam' => 2
                ),
                'template_id' => 'exam_reminder',
                'recipients' => 'student_parents',
                'enabled' => true,
                'delay_minutes' => 0,
                'priority' => 'normal'
            ),
            'transport_update' => array(
                'name' => __('Transport Update', 'school-management-system'),
                'description' => __('Send notification when transport schedule changes', 'school-management-system'),
                'trigger' => 'transport_updated',
                'conditions' => array(),
                'template_id' => 'transport_update',
                'recipients' => 'affected_parents',
                'enabled' => true,
                'delay_minutes' => 0,
                'priority' => 'high'
            ),
            'welcome_student' => array(
                'name' => __('Welcome New Student', 'school-management-system'),
                'description' => __('Send welcome message to newly enrolled students', 'school-management-system'),
                'trigger' => 'student_enrolled',
                'conditions' => array(),
                'template_id' => 'welcome_student',
                'recipients' => 'student_parents',
                'enabled' => true,
                'delay_minutes' => 30,
                'priority' => 'normal'
            )
        );
    }

    /**
     * Schedule automated checks
     */
    private function schedule_automated_checks() {
        // Schedule daily checks for fee reminders and other scheduled notifications
        if (!wp_next_scheduled('sms_daily_notification_check')) {
            wp_schedule_event(time(), 'daily', 'sms_daily_notification_check');
        }

        // Schedule hourly checks for urgent notifications
        if (!wp_next_scheduled('sms_hourly_notification_check')) {
            wp_schedule_event(time(), 'hourly', 'sms_hourly_notification_check');
        }

        // Add action handlers
        add_action('sms_daily_notification_check', array($this, 'process_daily_notifications'));
        add_action('sms_hourly_notification_check', array($this, 'process_hourly_notifications'));
    }

    /**
     * Add automated triggers
     */
    private function add_automated_triggers() {
        // Attendance triggers
        add_action('sms_attendance_marked', array($this, 'handle_attendance_trigger'), 10, 2);
        
        // Payment triggers
        add_action('sms_payment_received', array($this, 'handle_payment_trigger'), 10, 2);
        add_action('sms_payment_failed', array($this, 'handle_payment_failed_trigger'), 10, 2);
        
        // Student enrollment triggers
        add_action('sms_student_enrolled', array($this, 'handle_student_enrollment_trigger'), 10, 2);
        
        // Transport triggers
        add_action('sms_transport_updated', array($this, 'handle_transport_update_trigger'), 10, 2);
        
        // Notice triggers
        add_action('sms_notice_published', array($this, 'handle_notice_trigger'), 10, 2);
    }

    /**
     * Process daily notifications
     */
    public function process_daily_notifications() {
        $this->process_fee_reminders();
        $this->process_exam_reminders();
        $this->process_overdue_notifications();
        $this->process_scheduled_notifications('daily');
    }

    /**
     * Process hourly notifications
     */
    public function process_hourly_notifications() {
        $this->process_urgent_reminders();
        $this->process_scheduled_notifications('hourly');
    }

    /**
     * Process fee reminders
     */
    private function process_fee_reminders() {
        if (!$this->is_rule_enabled('fee_reminder_7_days') && !$this->is_rule_enabled('fee_reminder_1_day')) {
            return;
        }

        // Get unpaid invoices
        $unpaid_invoices = get_posts(array(
            'post_type' => 'sms_invoices',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => 'payment_status',
                    'value' => 'unpaid',
                    'compare' => '='
                )
            )
        ));

        foreach ($unpaid_invoices as $invoice) {
            $due_date = get_field('due_date', $invoice->ID);
            if (!$due_date) continue;

            $days_until_due = $this->calculate_days_until($due_date);
            
            // Check for 7-day reminder
            if ($days_until_due === 7 && $this->is_rule_enabled('fee_reminder_7_days')) {
                $this->send_fee_reminder($invoice, 'fee_reminder_7_days');
            }
            
            // Check for 1-day reminder
            if ($days_until_due === 1 && $this->is_rule_enabled('fee_reminder_1_day')) {
                $this->send_fee_reminder($invoice, 'fee_reminder_1_day');
            }
        }
    }

    /**
     * Process overdue notifications
     */
    private function process_overdue_notifications() {
        if (!$this->is_rule_enabled('fee_overdue')) {
            return;
        }

        // Get overdue invoices
        $overdue_invoices = get_posts(array(
            'post_type' => 'sms_invoices',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => 'payment_status',
                    'value' => 'unpaid',
                    'compare' => '='
                ),
                array(
                    'key' => 'due_date',
                    'value' => current_time('Y-m-d'),
                    'compare' => '<',
                    'type' => 'DATE'
                )
            )
        ));

        foreach ($overdue_invoices as $invoice) {
            $due_date = get_field('due_date', $invoice->ID);
            $days_overdue = $this->calculate_days_overdue($due_date);
            
            // Send overdue notice (limit to once per week to avoid spam)
            if ($days_overdue > 0 && $days_overdue % 7 === 0) {
                $this->send_overdue_notice($invoice);
            }
        }
    }

    /**
     * Process exam reminders
     */
    private function process_exam_reminders() {
        if (!$this->is_rule_enabled('exam_reminder')) {
            return;
        }

        // This would integrate with an exam/timetable system
        // For now, we'll check for any scheduled exams in custom posts or meta
        $upcoming_exams = $this->get_upcoming_exams(2); // 2 days ahead
        
        foreach ($upcoming_exams as $exam) {
            $this->send_exam_reminder($exam);
        }
    }

    /**
     * Handle attendance trigger
     */
    public function handle_attendance_trigger($attendance_data, $student_id) {
        if (!$this->is_rule_enabled('attendance_alert')) {
            return;
        }

        // Check if student was marked absent
        if ($attendance_data['status'] === 'absent') {
            $this->send_attendance_alert($student_id, $attendance_data);
        }
    }

    /**
     * Handle payment trigger
     */
    public function handle_payment_trigger($payment_data, $invoice_id) {
        if (!$this->is_rule_enabled('payment_confirmation')) {
            return;
        }

        $this->send_payment_confirmation($payment_data, $invoice_id);
    }

    /**
     * Handle student enrollment trigger
     */
    public function handle_student_enrollment_trigger($student_data, $student_id) {
        if (!$this->is_rule_enabled('welcome_student')) {
            return;
        }

        // Schedule welcome message with delay
        $rule = $this->notification_rules['welcome_student'];
        $delay_minutes = $rule['delay_minutes'] ?? 0;
        
        if ($delay_minutes > 0) {
            wp_schedule_single_event(
                time() + ($delay_minutes * 60),
                'sms_send_delayed_notification',
                array('welcome_student', $student_id, $student_data)
            );
        } else {
            $this->send_welcome_message($student_id, $student_data);
        }
    }

    /**
     * Handle transport update trigger
     */
    public function handle_transport_update_trigger($transport_data, $route_id) {
        if (!$this->is_rule_enabled('transport_update')) {
            return;
        }

        $this->send_transport_update($transport_data, $route_id);
    }

    /**
     * Send fee reminder
     */
    private function send_fee_reminder($invoice, $rule_id) {
        $student_id = get_field('student_id', $invoice->ID);
        if (!$student_id) return;

        $student = get_post($student_id);
        if (!$student) return;

        // Get parent contact info
        $parent_details = get_field('parent_details', $student_id);
        if (!$parent_details || empty($parent_details['phone'])) return;

        // Prepare template data
        $template_data = array(
            'parent_name' => $parent_details['name'] ?? 'Parent',
            'student_name' => $student->post_title,
            'fee_type' => get_field('fee_type', $invoice->ID),
            'amount' => number_format(get_field('amount', $invoice->ID)),
            'due_date' => date('d/m/Y', strtotime(get_field('due_date', $invoice->ID))),
            'mpesa_number' => get_option('sms_mpesa_number', '')
        );

        // Get rule configuration
        $rule = $this->notification_rules[$rule_id];
        
        // Send notification
        $result = $this->communication_handler->send_template_sms(
            $rule['template_id'],
            array($parent_details['phone']),
            $template_data,
            array(
                'priority' => $rule['priority'],
                'reference_id' => $invoice->ID,
                'reference_type' => 'fee_reminder'
            )
        );

        // Log the notification
        $this->log_notification($rule_id, $student_id, $result);
    }

    /**
     * Send overdue notice
     */
    private function send_overdue_notice($invoice) {
        $student_id = get_field('student_id', $invoice->ID);
        if (!$student_id) return;

        $student = get_post($student_id);
        if (!$student) return;

        $parent_details = get_field('parent_details', $student_id);
        if (!$parent_details || empty($parent_details['phone'])) return;

        $due_date = get_field('due_date', $invoice->ID);
        $days_overdue = $this->calculate_days_overdue($due_date);

        $template_data = array(
            'parent_name' => $parent_details['name'] ?? 'Parent',
            'student_name' => $student->post_title,
            'fee_type' => get_field('fee_type', $invoice->ID),
            'amount' => number_format(get_field('amount', $invoice->ID)),
            'days_overdue' => $days_overdue
        );

        $rule = $this->notification_rules['fee_overdue'];
        
        $result = $this->communication_handler->send_template_sms(
            $rule['template_id'],
            array($parent_details['phone']),
            $template_data,
            array(
                'priority' => $rule['priority'],
                'reference_id' => $invoice->ID,
                'reference_type' => 'fee_overdue'
            )
        );

        $this->log_notification('fee_overdue', $student_id, $result);
    }

    /**
     * Send attendance alert
     */
    private function send_attendance_alert($student_id, $attendance_data) {
        $student = get_post($student_id);
        if (!$student) return;

        $parent_details = get_field('parent_details', $student_id);
        if (!$parent_details || empty($parent_details['phone'])) return;

        $class_id = get_field('assigned_class', $student_id);
        $class_name = $class_id ? get_the_title($class_id) : 'Unknown Class';

        $template_data = array(
            'parent_name' => $parent_details['name'] ?? 'Parent',
            'student_name' => $student->post_title,
            'class_name' => $class_name,
            'date' => date('d/m/Y', strtotime($attendance_data['date']))
        );

        $rule = $this->notification_rules['attendance_alert'];
        
        $result = $this->communication_handler->send_template_sms(
            $rule['template_id'],
            array($parent_details['phone']),
            $template_data,
            array(
                'priority' => $rule['priority'],
                'reference_id' => $student_id,
                'reference_type' => 'attendance_alert'
            )
        );

        $this->log_notification('attendance_alert', $student_id, $result);
    }

    /**
     * Send payment confirmation
     */
    private function send_payment_confirmation($payment_data, $invoice_id) {
        $student_id = get_field('student_id', $invoice_id);
        if (!$student_id) return;

        $student = get_post($student_id);
        if (!$student) return;

        $parent_details = get_field('parent_details', $student_id);
        if (!$parent_details || empty($parent_details['phone'])) return;

        $template_data = array(
            'parent_name' => $parent_details['name'] ?? 'Parent',
            'student_name' => $student->post_title,
            'amount' => number_format($payment_data['amount']),
            'fee_type' => get_field('fee_type', $invoice_id),
            'receipt_number' => $payment_data['receipt_number'] ?? 'N/A'
        );

        $rule = $this->notification_rules['payment_confirmation'];
        
        $result = $this->communication_handler->send_template_sms(
            $rule['template_id'],
            array($parent_details['phone']),
            $template_data,
            array(
                'priority' => $rule['priority'],
                'reference_id' => $invoice_id,
                'reference_type' => 'payment_confirmation'
            )
        );

        $this->log_notification('payment_confirmation', $student_id, $result);
    }

    /**
     * Send welcome message
     */
    private function send_welcome_message($student_id, $student_data) {
        $student = get_post($student_id);
        if (!$student) return;

        $parent_details = get_field('parent_details', $student_id);
        if (!$parent_details || empty($parent_details['phone'])) return;

        $class_id = get_field('assigned_class', $student_id);
        $class_name = $class_id ? get_the_title($class_id) : 'Unknown Class';

        $template_data = array(
            'parent_name' => $parent_details['name'] ?? 'Parent',
            'student_name' => $student->post_title,
            'class_name' => $class_name,
            'admission_number' => get_field('admission_number', $student_id),
            'start_date' => date('d/m/Y', strtotime(get_option('sms_academic_year_start', '+1 week')))
        );

        $rule = $this->notification_rules['welcome_student'];
        
        $result = $this->communication_handler->send_template_sms(
            $rule['template_id'],
            array($parent_details['phone']),
            $template_data,
            array(
                'priority' => $rule['priority'],
                'reference_id' => $student_id,
                'reference_type' => 'welcome_student'
            )
        );

        $this->log_notification('welcome_student', $student_id, $result);
    }

    /**
     * Send transport update
     */
    private function send_transport_update($transport_data, $route_id) {
        // Get students assigned to this route
        $students = get_posts(array(
            'post_type' => 'sms_students',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => 'transport_route',
                    'value' => $route_id,
                    'compare' => '='
                )
            )
        ));

        $recipients = array();
        foreach ($students as $student) {
            $parent_details = get_field('parent_details', $student->ID);
            if ($parent_details && !empty($parent_details['phone'])) {
                $recipients[] = array(
                    'phone' => $parent_details['phone'],
                    'student_name' => $student->post_title,
                    'parent_name' => $parent_details['name'] ?? 'Parent'
                );
            }
        }

        if (empty($recipients)) return;

        $rule = $this->notification_rules['transport_update'];
        
        foreach ($recipients as $recipient) {
            $template_data = array(
                'parent_name' => $recipient['parent_name'],
                'student_name' => $recipient['student_name'],
                'pickup_time' => $transport_data['pickup_time'] ?? 'TBD',
                'pickup_location' => $transport_data['pickup_location'] ?? 'TBD'
            );

            $result = $this->communication_handler->send_template_sms(
                $rule['template_id'],
                array($recipient['phone']),
                $template_data,
                array(
                    'priority' => $rule['priority'],
                    'reference_id' => $route_id,
                    'reference_type' => 'transport_update'
                )
            );

            $this->log_notification('transport_update', $route_id, $result);
        }
    }

    /**
     * Calculate days until date
     */
    private function calculate_days_until($date) {
        $target_date = strtotime($date);
        $current_date = strtotime(current_time('Y-m-d'));
        
        return floor(($target_date - $current_date) / (24 * 60 * 60));
    }

    /**
     * Calculate days overdue
     */
    private function calculate_days_overdue($due_date) {
        $due_timestamp = strtotime($due_date);
        $current_timestamp = strtotime(current_time('Y-m-d'));
        
        return max(0, floor(($current_timestamp - $due_timestamp) / (24 * 60 * 60)));
    }

    /**
     * Check if notification rule is enabled
     */
    private function is_rule_enabled($rule_id) {
        return isset($this->notification_rules[$rule_id]) && 
               ($this->notification_rules[$rule_id]['enabled'] ?? false);
    }

    /**
     * Log notification
     */
    private function log_notification($rule_id, $reference_id, $result) {
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'rule_id' => $rule_id,
            'reference_id' => $reference_id,
            'success' => $result['success'] ?? false,
            'message' => $result['message'] ?? '',
            'error' => $result['error'] ?? null
        );

        $existing_logs = get_option('sms_notification_logs', array());
        $existing_logs[] = $log_entry;
        
        // Keep only last 500 logs
        if (count($existing_logs) > 500) {
            $existing_logs = array_slice($existing_logs, -500);
        }
        
        update_option('sms_notification_logs', $existing_logs);
    }

    /**
     * Get upcoming exams
     */
    private function get_upcoming_exams($days_ahead) {
        // This would integrate with your exam/timetable system
        // For now, return empty array
        return array();
    }

    /**
     * Process scheduled notifications
     */
    private function process_scheduled_notifications($frequency) {
        foreach ($this->notification_rules as $rule_id => $rule) {
            if ($rule['trigger'] === 'scheduled' && 
                ($rule['schedule'] ?? '') === $frequency && 
                $this->is_rule_enabled($rule_id)) {
                
                $this->process_scheduled_rule($rule_id, $rule);
            }
        }
    }

    /**
     * Process urgent reminders
     */
    private function process_urgent_reminders() {
        // Process any urgent notifications that need hourly checking
        $this->process_fee_reminders(); // Check for same-day reminders
    }

    /**
     * Process scheduled rule
     */
    private function process_scheduled_rule($rule_id, $rule) {
        // This method would contain the logic for each scheduled rule
        // Implementation depends on the specific rule type
        switch ($rule_id) {
            case 'fee_reminder_7_days':
            case 'fee_reminder_1_day':
                // Already handled in process_fee_reminders
                break;
            case 'fee_overdue':
                // Already handled in process_overdue_notifications
                break;
            case 'exam_reminder':
                // Already handled in process_exam_reminders
                break;
        }
    }

    /**
     * Get notification rules
     */
    public function get_notification_rules() {
        return $this->notification_rules;
    }

    /**
     * Save notification rule
     */
    public function save_notification_rule_data($rule_id, $rule_data) {
        $this->notification_rules[$rule_id] = $rule_data;
        update_option('sms_notification_rules', $this->notification_rules);
        return true;
    }

    /**
     * Delete notification rule
     */
    public function delete_notification_rule_data($rule_id) {
        if (isset($this->notification_rules[$rule_id])) {
            unset($this->notification_rules[$rule_id]);
            update_option('sms_notification_rules', $this->notification_rules);
            return true;
        }
        return false;
    }

    /**
     * Get notification logs
     */
    public function get_notification_logs($limit = 50) {
        $logs = get_option('sms_notification_logs', array());
        return array_slice(array_reverse($logs), 0, $limit);
    }

    /**
     * Get notification statistics
     */
    public function get_notification_stats($period = 'month') {
        $logs = get_option('sms_notification_logs', array());
        
        if (empty($logs)) {
            return array(
                'total_sent' => 0,
                'total_failed' => 0,
                'success_rate' => 0,
                'by_rule' => array()
            );
        }

        // Filter logs by period
        $cutoff_date = $this->get_period_cutoff_date($period);
        $filtered_logs = array_filter($logs, function($log) use ($cutoff_date) {
            return strtotime($log['timestamp']) >= $cutoff_date;
        });

        $total_sent = 0;
        $total_failed = 0;
        $by_rule = array();

        foreach ($filtered_logs as $log) {
            if ($log['success']) {
                $total_sent++;
            } else {
                $total_failed++;
            }

            $rule_id = $log['rule_id'];
            if (!isset($by_rule[$rule_id])) {
                $by_rule[$rule_id] = array('sent' => 0, 'failed' => 0);
            }

            if ($log['success']) {
                $by_rule[$rule_id]['sent']++;
            } else {
                $by_rule[$rule_id]['failed']++;
            }
        }

        $total_attempts = $total_sent + $total_failed;
        $success_rate = $total_attempts > 0 ? ($total_sent / $total_attempts) * 100 : 0;

        return array(
            'total_sent' => $total_sent,
            'total_failed' => $total_failed,
            'success_rate' => round($success_rate, 2),
            'by_rule' => $by_rule,
            'period' => $period
        );
    }

    /**
     * Get period cutoff date
     */
    private function get_period_cutoff_date($period) {
        switch ($period) {
            case 'day':
                return strtotime('-1 day');
            case 'week':
                return strtotime('-1 week');
            case 'month':
                return strtotime('-1 month');
            case 'year':
                return strtotime('-1 year');
            default:
                return strtotime('-1 month');
        }
    }

    /**
     * AJAX handler for saving notification rule
     */
    public function save_notification_rule() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_notification_rule_nonce')) {
            wp_send_json_error(__('Security check failed', 'school-management-system'));
        }

        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to save notification rules', 'school-management-system'));
        }

        $rule_id = sanitize_text_field($_POST['rule_id']);
        $rule_data = array(
            'name' => sanitize_text_field($_POST['rule_name']),
            'description' => sanitize_textarea_field($_POST['rule_description']),
            'trigger' => sanitize_text_field($_POST['rule_trigger']),
            'template_id' => sanitize_text_field($_POST['template_id']),
            'recipients' => sanitize_text_field($_POST['recipients']),
            'enabled' => isset($_POST['enabled']) ? true : false,
            'priority' => sanitize_text_field($_POST['priority']),
            'delay_minutes' => intval($_POST['delay_minutes'])
        );

        $result = $this->save_notification_rule_data($rule_id, $rule_data);

        if ($result) {
            wp_send_json_success(array(
                'message' => __('Notification rule saved successfully', 'school-management-system')
            ));
        } else {
            wp_send_json_error(__('Failed to save notification rule', 'school-management-system'));
        }
    }

    /**
     * AJAX handler for deleting notification rule
     */
    public function delete_notification_rule() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_notification_rule_nonce')) {
            wp_send_json_error(__('Security check failed', 'school-management-system'));
        }

        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to delete notification rules', 'school-management-system'));
        }

        $rule_id = sanitize_text_field($_POST['rule_id']);
        $result = $this->delete_notification_rule_data($rule_id);

        if ($result) {
            wp_send_json_success(array(
                'message' => __('Notification rule deleted successfully', 'school-management-system')
            ));
        } else {
            wp_send_json_error(__('Failed to delete notification rule', 'school-management-system'));
        }
    }

    /**
     * AJAX handler for testing notification rule
     */
    public function test_notification_rule() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_notification_rule_nonce')) {
            wp_send_json_error(__('Security check failed', 'school-management-system'));
        }

        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to test notification rules', 'school-management-system'));
        }

        $rule_id = sanitize_text_field($_POST['rule_id']);
        $test_phone = sanitize_text_field($_POST['test_phone']);

        if (empty($test_phone)) {
            wp_send_json_error(__('Test phone number is required', 'school-management-system'));
        }

        $rule = $this->notification_rules[$rule_id] ?? null;
        if (!$rule) {
            wp_send_json_error(__('Notification rule not found', 'school-management-system'));
        }

        // Send test message
        $test_data = array(
            'parent_name' => 'Test Parent',
            'student_name' => 'Test Student',
            'class_name' => 'Test Class',
            'fee_type' => 'Test Fee',
            'amount' => '10,000',
            'due_date' => date('d/m/Y', strtotime('+7 days')),
            'date' => date('d/m/Y')
        );

        $result = $this->communication_handler->send_template_sms(
            $rule['template_id'],
            array($test_phone),
            $test_data,
            array('priority' => 'normal')
        );

        if ($result['success']) {
            wp_send_json_success(array(
                'message' => __('Test notification sent successfully', 'school-management-system')
            ));
        } else {
            wp_send_json_error($result['error'] ?? __('Failed to send test notification', 'school-management-system'));
        }
    }
}

// Initialize the notification scheduler
new SMS_Notification_Scheduler();