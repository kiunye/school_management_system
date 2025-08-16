<?php
/**
 * Payment Reminder Scheduler
 *
 * Handles scheduling and management of automated payment reminders
 * with configurable intervals and notification methods.
 *
 * @package    School_Management_System
 * @subpackage School_Management_System/includes/financial
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Payment Reminder Scheduler Class
 */
class SMS_Payment_Reminder_Scheduler extends SMS_Base {

    /**
     * Reminder schedule settings
     */
    private $reminder_settings;

    /**
     * Initialize the class and set its properties.
     */
    public function __construct() {
        parent::__construct();
        
        // Load reminder settings
        $this->load_reminder_settings();
        
        // Schedule reminder tasks
        $this->schedule_reminder_tasks();
        
        // Hook into reminder processing
        add_action('sms_process_payment_reminders', [$this, 'process_scheduled_reminders']);
        add_action('sms_send_upcoming_reminders', [$this, 'send_upcoming_payment_reminders']);
        add_action('sms_send_due_reminders', [$this, 'send_due_payment_reminders']);
        add_action('sms_send_overdue_reminders', [$this, 'send_overdue_payment_reminders']);
        
        // Settings management
        add_action('sms_update_reminder_settings', [$this, 'update_reminder_settings']);
        
        // Manual reminder triggers
        add_action('sms_send_manual_reminder', [$this, 'send_manual_reminder'], 10, 3);
    }

    /**
     * Schedule reminder tasks if not already scheduled.
     */
    private function schedule_reminder_tasks() {
        // Schedule main reminder processing (runs multiple times daily)
        if (!wp_next_scheduled('sms_process_payment_reminders')) {
            wp_schedule_event(time(), 'twicedaily', 'sms_process_payment_reminders');
        }
        
        // Schedule upcoming payment reminders (daily at 9 AM)
        if (!wp_next_scheduled('sms_send_upcoming_reminders')) {
            $schedule_time = strtotime('today 9:00 AM');
            if ($schedule_time < time()) {
                $schedule_time = strtotime('tomorrow 9:00 AM');
            }
            wp_schedule_event($schedule_time, 'daily', 'sms_send_upcoming_reminders');
        }
        
        // Schedule due payment reminders (daily at 10 AM)
        if (!wp_next_scheduled('sms_send_due_reminders')) {
            $schedule_time = strtotime('today 10:00 AM');
            if ($schedule_time < time()) {
                $schedule_time = strtotime('tomorrow 10:00 AM');
            }
            wp_schedule_event($schedule_time, 'daily', 'sms_send_due_reminders');
        }
        
        // Schedule overdue payment reminders (daily at 11 AM)
        if (!wp_next_scheduled('sms_send_overdue_reminders')) {
            $schedule_time = strtotime('today 11:00 AM');
            if ($schedule_time < time()) {
                $schedule_time = strtotime('tomorrow 11:00 AM');
            }
            wp_schedule_event($schedule_time, 'daily', 'sms_send_overdue_reminders');
        }
    }

    /**
     * Process all scheduled reminders.
     */
    public function process_scheduled_reminders() {
        try {
            $processing_summary = [
                'start_time' => current_time('mysql'),
                'upcoming_sent' => 0,
                'due_sent' => 0,
                'overdue_sent' => 0,
                'errors' => []
            ];
            
            // Process upcoming payment reminders
            $upcoming_result = $this->send_upcoming_payment_reminders();
            $processing_summary['upcoming_sent'] = $upcoming_result['sent_count'];
            
            // Process due payment reminders
            $due_result = $this->send_due_payment_reminders();
            $processing_summary['due_sent'] = $due_result['sent_count'];
            
            // Process overdue payment reminders
            $overdue_result = $this->send_overdue_payment_reminders();
            $processing_summary['overdue_sent'] = $overdue_result['sent_count'];
            
            // Collect errors
            $processing_summary['errors'] = array_merge(
                $upcoming_result['errors'] ?? [],
                $due_result['errors'] ?? [],
                $overdue_result['errors'] ?? []
            );
            
            $processing_summary['end_time'] = current_time('mysql');
            $processing_summary['total_sent'] = $processing_summary['upcoming_sent'] + 
                                               $processing_summary['due_sent'] + 
                                               $processing_summary['overdue_sent'];
            
            // Log processing summary
            $this->log_activity(
                0,
                'reminder_processing_completed',
                'reminder',
                0,
                $processing_summary
            );
            
            // Store processing summary for reporting
            update_option('sms_last_reminder_processing', $processing_summary);
            
        } catch (Exception $e) {
            error_log('SMS Payment Reminder Scheduler: Processing failed - ' . $e->getMessage());
        }
    }

    /**
     * Send upcoming payment reminders.
     *
     * @return array Processing results
     */
    public function send_upcoming_payment_reminders() {
        $results = [
            'sent_count' => 0,
            'skipped_count' => 0,
            'errors' => []
        ];
        
        $upcoming_days = $this->reminder_settings['upcoming_days'] ?? 3;
        $upcoming_invoices = $this->get_upcoming_due_invoices($upcoming_days);
        
        foreach ($upcoming_invoices as $invoice_id) {
            try {
                // Check if reminder should be sent
                if (!$this->should_send_upcoming_reminder($invoice_id)) {
                    $results['skipped_count']++;
                    continue;
                }
                
                // Send reminder
                $reminder_result = $this->send_payment_reminder($invoice_id, 'upcoming');
                
                if ($reminder_result['success']) {
                    $results['sent_count']++;
                    
                    // Record reminder sent
                    $this->record_reminder_sent($invoice_id, 'upcoming', $reminder_result);
                } else {
                    $results['errors'][] = [
                        'invoice_id' => $invoice_id,
                        'error' => $reminder_result['error']
                    ];
                }
                
            } catch (Exception $e) {
                $results['errors'][] = [
                    'invoice_id' => $invoice_id,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return $results;
    }

    /**
     * Send due payment reminders.
     *
     * @return array Processing results
     */
    public function send_due_payment_reminders() {
        $results = [
            'sent_count' => 0,
            'skipped_count' => 0,
            'errors' => []
        ];
        
        $due_invoices = $this->get_due_today_invoices();
        
        foreach ($due_invoices as $invoice_id) {
            try {
                // Check if reminder should be sent
                if (!$this->should_send_due_reminder($invoice_id)) {
                    $results['skipped_count']++;
                    continue;
                }
                
                // Send reminder
                $reminder_result = $this->send_payment_reminder($invoice_id, 'due');
                
                if ($reminder_result['success']) {
                    $results['sent_count']++;
                    
                    // Record reminder sent
                    $this->record_reminder_sent($invoice_id, 'due', $reminder_result);
                } else {
                    $results['errors'][] = [
                        'invoice_id' => $invoice_id,
                        'error' => $reminder_result['error']
                    ];
                }
                
            } catch (Exception $e) {
                $results['errors'][] = [
                    'invoice_id' => $invoice_id,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return $results;
    }

    /**
     * Send overdue payment reminders.
     *
     * @return array Processing results
     */
    public function send_overdue_payment_reminders() {
        $results = [
            'sent_count' => 0,
            'skipped_count' => 0,
            'errors' => []
        ];
        
        $overdue_invoices = $this->get_overdue_invoices();
        $overdue_intervals = $this->reminder_settings['overdue_intervals'] ?? [1, 7, 14, 30];
        $final_notice_days = $this->reminder_settings['final_notice_days'] ?? 45;
        
        foreach ($overdue_invoices as $invoice_id) {
            try {
                $days_overdue = $this->calculate_days_overdue(get_field('due_date', $invoice_id));
                $reminder_type = 'overdue';
                
                // Determine if this is a final notice
                if ($days_overdue >= $final_notice_days) {
                    $reminder_type = 'final';
                }
                
                // Check if it's time for a reminder based on intervals
                $should_send = false;
                if ($reminder_type === 'final') {
                    $should_send = $this->should_send_final_reminder($invoice_id, $days_overdue);
                } else {
                    $should_send = in_array($days_overdue, $overdue_intervals) && 
                                  $this->should_send_overdue_reminder($invoice_id, $days_overdue);
                }
                
                if (!$should_send) {
                    $results['skipped_count']++;
                    continue;
                }
                
                // Send reminder
                $reminder_result = $this->send_payment_reminder($invoice_id, $reminder_type);
                
                if ($reminder_result['success']) {
                    $results['sent_count']++;
                    
                    // Record reminder sent
                    $this->record_reminder_sent($invoice_id, $reminder_type, $reminder_result);
                } else {
                    $results['errors'][] = [
                        'invoice_id' => $invoice_id,
                        'error' => $reminder_result['error']
                    ];
                }
                
            } catch (Exception $e) {
                $results['errors'][] = [
                    'invoice_id' => $invoice_id,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return $results;
    }

    /**
     * Send a payment reminder for a specific invoice.
     *
     * @param int    $invoice_id Invoice ID
     * @param string $reminder_type Reminder type
     * @return array Reminder result
     */
    private function send_payment_reminder($invoice_id, $reminder_type) {
        $result = [
            'success' => false,
            'methods_sent' => [],
            'error' => null
        ];
        
        try {
            // Get student and invoice data
            $student_id = get_field('student', $invoice_id);
            if (!$student_id) {
                throw new Exception('Student not found for invoice');
            }
            
            $student_data = $this->get_student_contact_data($student_id);
            $invoice_data = $this->get_invoice_reminder_data($invoice_id);
            
            $enabled_methods = $this->reminder_settings['enabled_methods'] ?? ['email', 'sms'];
            $methods_sent = [];
            
            // Send email reminder
            if (in_array('email', $enabled_methods) && !empty($student_data['parent_email'])) {
                $email_result = $this->send_email_reminder($student_data, $invoice_data, $reminder_type);
                if ($email_result) {
                    $methods_sent[] = 'email';
                }
            }
            
            // Send SMS reminder
            if (in_array('sms', $enabled_methods) && !empty($student_data['parent_phone'])) {
                $sms_result = $this->send_sms_reminder($student_data, $invoice_data, $reminder_type);
                if ($sms_result) {
                    $methods_sent[] = 'sms';
                }
            }
            
            // Send push notification if enabled
            if (in_array('push', $enabled_methods)) {
                $push_result = $this->send_push_reminder($student_data, $invoice_data, $reminder_type);
                if ($push_result) {
                    $methods_sent[] = 'push';
                }
            }
            
            if (!empty($methods_sent)) {
                $result['success'] = true;
                $result['methods_sent'] = $methods_sent;
            } else {
                $result['error'] = 'No reminder methods succeeded';
            }
            
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
        }
        
        return $result;
    }

    /**
     * Send manual reminder for specific invoice.
     *
     * @param int    $invoice_id Invoice ID
     * @param string $reminder_type Reminder type
     * @param array  $options Reminder options
     * @return array Reminder result
     */
    public function send_manual_reminder($invoice_id, $reminder_type, $options = []) {
        // Override settings for manual reminders
        $original_settings = $this->reminder_settings;
        
        if (!empty($options['methods'])) {
            $this->reminder_settings['enabled_methods'] = $options['methods'];
        }
        
        if (!empty($options['custom_message'])) {
            $this->reminder_settings['custom_message'] = $options['custom_message'];
        }
        
        // Send reminder
        $result = $this->send_payment_reminder($invoice_id, $reminder_type);
        
        // Record manual reminder
        if ($result['success']) {
            $this->record_reminder_sent($invoice_id, $reminder_type . '_manual', $result);
            
            // Log manual reminder
            $this->log_activity(
                get_current_user_id(),
                'manual_reminder_sent',
                'invoice',
                $invoice_id,
                [
                    'reminder_type' => $reminder_type,
                    'methods' => $result['methods_sent']
                ]
            );
        }
        
        // Restore original settings
        $this->reminder_settings = $original_settings;
        
        return $result;
    }

    /**
     * Check if upcoming reminder should be sent.
     *
     * @param int $invoice_id Invoice ID
     * @return bool Whether reminder should be sent
     */
    private function should_send_upcoming_reminder($invoice_id) {
        $reminder_history = get_field('reminder_history', $invoice_id) ?? [];
        $today = current_time('Y-m-d');
        
        // Check if upcoming reminder was already sent today
        foreach ($reminder_history as $reminder) {
            if ($reminder['type'] === 'upcoming' && 
                date('Y-m-d', strtotime($reminder['sent_date'])) === $today) {
                return false;
            }
        }
        
        // Check if invoice is still unpaid
        $balance_due = get_field('balance_due', $invoice_id);
        return $balance_due > 0;
    }

    /**
     * Check if due reminder should be sent.
     *
     * @param int $invoice_id Invoice ID
     * @return bool Whether reminder should be sent
     */
    private function should_send_due_reminder($invoice_id) {
        $reminder_history = get_field('reminder_history', $invoice_id) ?? [];
        $today = current_time('Y-m-d');
        
        // Check if due reminder was already sent today
        foreach ($reminder_history as $reminder) {
            if ($reminder['type'] === 'due' && 
                date('Y-m-d', strtotime($reminder['sent_date'])) === $today) {
                return false;
            }
        }
        
        // Check if invoice is still unpaid
        $balance_due = get_field('balance_due', $invoice_id);
        return $balance_due > 0;
    }

    /**
     * Check if overdue reminder should be sent.
     *
     * @param int $invoice_id Invoice ID
     * @param int $days_overdue Days overdue
     * @return bool Whether reminder should be sent
     */
    private function should_send_overdue_reminder($invoice_id, $days_overdue) {
        $reminder_history = get_field('reminder_history', $invoice_id) ?? [];
        $today = current_time('Y-m-d');
        
        // Check if overdue reminder for this specific day count was already sent
        foreach ($reminder_history as $reminder) {
            if ($reminder['type'] === 'overdue' && 
                isset($reminder['days_overdue']) &&
                $reminder['days_overdue'] == $days_overdue &&
                date('Y-m-d', strtotime($reminder['sent_date'])) === $today) {
                return false;
            }
        }
        
        // Check if invoice is still unpaid
        $balance_due = get_field('balance_due', $invoice_id);
        return $balance_due > 0;
    }

    /**
     * Check if final reminder should be sent.
     *
     * @param int $invoice_id Invoice ID
     * @param int $days_overdue Days overdue
     * @return bool Whether reminder should be sent
     */
    private function should_send_final_reminder($invoice_id, $days_overdue) {
        $reminder_history = get_field('reminder_history', $invoice_id) ?? [];
        $final_notice_interval = $this->reminder_settings['final_notice_interval'] ?? 7; // Send final notice every 7 days
        
        // Find last final reminder
        $last_final_reminder = null;
        foreach (array_reverse($reminder_history) as $reminder) {
            if ($reminder['type'] === 'final') {
                $last_final_reminder = $reminder;
                break;
            }
        }
        
        // If no final reminder sent yet, send it
        if (!$last_final_reminder) {
            return true;
        }
        
        // Check if enough days have passed since last final reminder
        $days_since_last = floor((current_time('timestamp') - strtotime($last_final_reminder['sent_date'])) / DAY_IN_SECONDS);
        
        return $days_since_last >= $final_notice_interval;
    }

    /**
     * Record that a reminder was sent.
     *
     * @param int    $invoice_id Invoice ID
     * @param string $reminder_type Reminder type
     * @param array  $reminder_result Reminder result data
     */
    private function record_reminder_sent($invoice_id, $reminder_type, $reminder_result) {
        $reminder_history = get_field('reminder_history', $invoice_id) ?? [];
        
        $reminder_record = [
            'type' => $reminder_type,
            'sent_date' => current_time('mysql'),
            'methods' => $reminder_result['methods_sent'],
            'sent_by' => 'system'
        ];
        
        // Add days overdue for overdue reminders
        if (in_array($reminder_type, ['overdue', 'final'])) {
            $reminder_record['days_overdue'] = $this->calculate_days_overdue(get_field('due_date', $invoice_id));
        }
        
        $reminder_history[] = $reminder_record;
        update_field('reminder_history', $reminder_history, $invoice_id);
        
        // Update reminder statistics
        $this->update_reminder_statistics($reminder_type);
    }

    /**
     * Update reminder statistics.
     *
     * @param string $reminder_type Reminder type
     */
    private function update_reminder_statistics($reminder_type) {
        $stats_key = 'sms_reminder_statistics_' . date('Y_m');
        $stats = get_option($stats_key, [
            'upcoming' => 0,
            'due' => 0,
            'overdue' => 0,
            'final' => 0
        ]);
        
        if (isset($stats[$reminder_type])) {
            $stats[$reminder_type]++;
            update_option($stats_key, $stats);
        }
    }

    /**
     * Send email reminder.
     *
     * @param array  $student_data Student contact data
     * @param array  $invoice_data Invoice data
     * @param string $reminder_type Reminder type
     * @return bool Success status
     */
    private function send_email_reminder($student_data, $invoice_data, $reminder_type) {
        $email_template = $this->get_email_reminder_template($reminder_type);
        
        // Use custom message if provided
        if (!empty($this->reminder_settings['custom_message'])) {
            $email_template['message'] = $this->reminder_settings['custom_message'];
        }
        
        $subject = $this->render_template($email_template['subject'], $student_data, $invoice_data);
        $message = $this->render_template($email_template['message'], $student_data, $invoice_data);
        
        // Add payment instructions
        if (!empty($invoice_data['payment_instructions'])) {
            $message .= "\n\nPayment Instructions:\n" . $invoice_data['payment_instructions'];
        }
        
        // Add school contact information
        $school_info = get_option('sms_school_information', []);
        if (!empty($school_info['contact_email']) || !empty($school_info['contact_phone'])) {
            $message .= "\n\nFor assistance, contact us at:";
            if (!empty($school_info['contact_email'])) {
                $message .= "\nEmail: " . $school_info['contact_email'];
            }
            if (!empty($school_info['contact_phone'])) {
                $message .= "\nPhone: " . $school_info['contact_phone'];
            }
        }
        
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        
        return wp_mail($student_data['parent_email'], $subject, nl2br($message), $headers);
    }

    /**
     * Send SMS reminder.
     *
     * @param array  $student_data Student contact data
     * @param array  $invoice_data Invoice data
     * @param string $reminder_type Reminder type
     * @return bool Success status
     */
    private function send_sms_reminder($student_data, $invoice_data, $reminder_type) {
        $sms_template = $this->get_sms_reminder_template($reminder_type);
        
        // Use custom message if provided
        if (!empty($this->reminder_settings['custom_sms_message'])) {
            $sms_template = $this->reminder_settings['custom_sms_message'];
        }
        
        $message = $this->render_template($sms_template, $student_data, $invoice_data);
        
        // Ensure message is within SMS length limits
        if (strlen($message) > 160) {
            $message = substr($message, 0, 157) . '...';
        }
        
        // Use SMS communication handler
        if (class_exists('SMS_Communication_Handler')) {
            $sms_handler = new SMS_Communication_Handler();
            return $sms_handler->send_sms([$student_data['parent_phone']], $message);
        }
        
        return false;
    }

    /**
     * Send push notification reminder.
     *
     * @param array  $student_data Student contact data
     * @param array  $invoice_data Invoice data
     * @param string $reminder_type Reminder type
     * @return bool Success status
     */
    private function send_push_reminder($student_data, $invoice_data, $reminder_type) {
        // Placeholder for push notification implementation
        // This would integrate with a push notification service
        return false;
    }

    /**
     * Get email reminder template.
     *
     * @param string $reminder_type Reminder type
     * @return array Email template
     */
    private function get_email_reminder_template($reminder_type) {
        $templates = [
            'upcoming' => [
                'subject' => 'Payment Reminder: Invoice {invoice_number} Due Soon',
                'message' => 'Dear {parent_name},\n\nThis is a friendly reminder that payment for {student_name} (Invoice #{invoice_number}) of KES {balance_due} is due on {due_date}.\n\nPlease make payment before the due date to avoid late fees.\n\nThank you for your prompt attention to this matter.'
            ],
            'due' => [
                'subject' => 'Payment Due Today: Invoice {invoice_number}',
                'message' => 'Dear {parent_name},\n\nPayment for {student_name} (Invoice #{invoice_number}) of KES {balance_due} is due today ({due_date}).\n\nPlease make payment immediately to avoid late fees.\n\nThank you.'
            ],
            'overdue' => [
                'subject' => 'Overdue Payment: Invoice {invoice_number}',
                'message' => 'Dear {parent_name},\n\nPayment for {student_name} (Invoice #{invoice_number}) of KES {balance_due} is now {days_overdue} days overdue.\n\nLate fees may have been applied. Please make payment immediately to avoid further penalties.\n\nThank you for your immediate attention.'
            ],
            'final' => [
                'subject' => 'FINAL NOTICE: Invoice {invoice_number}',
                'message' => 'Dear {parent_name},\n\nThis is a FINAL NOTICE for overdue payment for {student_name} (Invoice #{invoice_number}) of KES {balance_due}.\n\nThis payment is now {days_overdue} days overdue. Please contact the school office immediately to resolve this matter and avoid further action.\n\nUrgent attention required.'
            ]
        ];
        
        return $templates[$reminder_type] ?? $templates['due'];
    }

    /**
     * Get SMS reminder template.
     *
     * @param string $reminder_type Reminder type
     * @return string SMS template
     */
    private function get_sms_reminder_template($reminder_type) {
        $templates = [
            'upcoming' => 'Payment reminder: {student_name} invoice #{invoice_number} of KES {balance_due} due {due_date}. Pay via M-Pesa to avoid late fees.',
            'due' => 'Payment due today: {student_name} invoice #{invoice_number} of KES {balance_due}. Pay immediately via M-Pesa.',
            'overdue' => 'Overdue payment: {student_name} invoice #{invoice_number} of KES {balance_due} is {days_overdue} days overdue. Late fees applied. Pay now.',
            'final' => 'FINAL NOTICE: {student_name} invoice #{invoice_number} of KES {balance_due} severely overdue ({days_overdue} days). Contact school office immediately.'
        ];
        
        return $templates[$reminder_type] ?? $templates['due'];
    }

    /**
     * Render template with data placeholders.
     *
     * @param string $template Template string
     * @param array  $student_data Student data
     * @param array  $invoice_data Invoice data
     * @return string Rendered template
     */
    private function render_template($template, $student_data, $invoice_data) {
        $placeholders = array_merge($student_data, $invoice_data);
        
        foreach ($placeholders as $key => $value) {
            $template = str_replace('{' . $key . '}', $value, $template);
        }
        
        return $template;
    }

    /**
     * Load reminder settings from options.
     */
    private function load_reminder_settings() {
        $this->reminder_settings = get_option('sms_payment_reminder_settings', [
            'enabled' => true,
            'upcoming_days' => 3,
            'overdue_intervals' => [1, 7, 14, 30],
            'final_notice_days' => 45,
            'final_notice_interval' => 7,
            'enabled_methods' => ['email', 'sms'],
            'business_hours_only' => true,
            'business_start_hour' => 8,
            'business_end_hour' => 18,
            'weekend_reminders' => false
        ]);
    }

    /**
     * Update reminder settings.
     *
     * @param array $new_settings New settings
     * @return bool Success status
     */
    public function update_reminder_settings($new_settings) {
        // Validate settings
        $validated_settings = $this->validate_reminder_settings($new_settings);
        
        if (is_wp_error($validated_settings)) {
            return $validated_settings;
        }
        
        // Update settings
        $this->reminder_settings = array_merge($this->reminder_settings, $validated_settings);
        update_option('sms_payment_reminder_settings', $this->reminder_settings);
        
        // Reschedule tasks if timing changed
        $this->reschedule_reminder_tasks();
        
        return true;
    }

    /**
     * Validate reminder settings.
     *
     * @param array $settings Settings to validate
     * @return array|WP_Error Validated settings or error
     */
    private function validate_reminder_settings($settings) {
        $validated = [];
        
        // Validate upcoming days
        if (isset($settings['upcoming_days'])) {
            $upcoming_days = intval($settings['upcoming_days']);
            if ($upcoming_days < 1 || $upcoming_days > 30) {
                return new WP_Error('invalid_upcoming_days', 'Upcoming days must be between 1 and 30');
            }
            $validated['upcoming_days'] = $upcoming_days;
        }
        
        // Validate overdue intervals
        if (isset($settings['overdue_intervals'])) {
            if (!is_array($settings['overdue_intervals'])) {
                return new WP_Error('invalid_overdue_intervals', 'Overdue intervals must be an array');
            }
            
            $intervals = array_map('intval', $settings['overdue_intervals']);
            $intervals = array_filter($intervals, function($interval) {
                return $interval > 0 && $interval <= 365;
            });
            
            if (empty($intervals)) {
                return new WP_Error('invalid_overdue_intervals', 'At least one valid overdue interval is required');
            }
            
            sort($intervals);
            $validated['overdue_intervals'] = $intervals;
        }
        
        // Validate final notice days
        if (isset($settings['final_notice_days'])) {
            $final_days = intval($settings['final_notice_days']);
            if ($final_days < 1 || $final_days > 365) {
                return new WP_Error('invalid_final_notice_days', 'Final notice days must be between 1 and 365');
            }
            $validated['final_notice_days'] = $final_days;
        }
        
        // Validate enabled methods
        if (isset($settings['enabled_methods'])) {
            $valid_methods = ['email', 'sms', 'push'];
            $methods = array_intersect($settings['enabled_methods'], $valid_methods);
            
            if (empty($methods)) {
                return new WP_Error('invalid_methods', 'At least one valid reminder method must be enabled');
            }
            
            $validated['enabled_methods'] = $methods;
        }
        
        // Validate business hours
        if (isset($settings['business_start_hour'])) {
            $start_hour = intval($settings['business_start_hour']);
            if ($start_hour < 0 || $start_hour > 23) {
                return new WP_Error('invalid_business_hours', 'Business start hour must be between 0 and 23');
            }
            $validated['business_start_hour'] = $start_hour;
        }
        
        if (isset($settings['business_end_hour'])) {
            $end_hour = intval($settings['business_end_hour']);
            if ($end_hour < 0 || $end_hour > 23) {
                return new WP_Error('invalid_business_hours', 'Business end hour must be between 0 and 23');
            }
            $validated['business_end_hour'] = $end_hour;
        }
        
        // Copy boolean settings
        $boolean_settings = ['enabled', 'business_hours_only', 'weekend_reminders'];
        foreach ($boolean_settings as $setting) {
            if (isset($settings[$setting])) {
                $validated[$setting] = (bool) $settings[$setting];
            }
        }
        
        return $validated;
    }

    /**
     * Reschedule reminder tasks with new timing.
     */
    private function reschedule_reminder_tasks() {
        // Clear existing schedules
        wp_clear_scheduled_hook('sms_send_upcoming_reminders');
        wp_clear_scheduled_hook('sms_send_due_reminders');
        wp_clear_scheduled_hook('sms_send_overdue_reminders');
        
        // Reschedule with new settings
        $this->schedule_reminder_tasks();
    }

    /**
     * Get reminder statistics for reporting.
     *
     * @param string $period Period (month, year)
     * @return array Reminder statistics
     */
    public function get_reminder_statistics($period = 'current_month') {
        switch ($period) {
            case 'current_month':
                $stats_key = 'sms_reminder_statistics_' . date('Y_m');
                break;
            case 'last_month':
                $stats_key = 'sms_reminder_statistics_' . date('Y_m', strtotime('-1 month'));
                break;
            case 'current_year':
                $stats = [];
                for ($month = 1; $month <= 12; $month++) {
                    $month_key = 'sms_reminder_statistics_' . date('Y') . '_' . str_pad($month, 2, '0', STR_PAD_LEFT);
                    $month_stats = get_option($month_key, []);
                    foreach ($month_stats as $type => $count) {
                        if (!isset($stats[$type])) {
                            $stats[$type] = 0;
                        }
                        $stats[$type] += $count;
                    }
                }
                return $stats;
            default:
                $stats_key = $period;
        }
        
        return get_option($stats_key, [
            'upcoming' => 0,
            'due' => 0,
            'overdue' => 0,
            'final' => 0
        ]);
    }

    /**
     * Get helper methods from payment processor.
     */
    private function get_upcoming_due_invoices($days) {
        $payment_processor = new SMS_Payment_Processor();
        return $payment_processor->get_upcoming_due_invoices($days);
    }

    private function get_due_today_invoices() {
        $payment_processor = new SMS_Payment_Processor();
        return $payment_processor->get_due_today_invoices();
    }

    private function get_overdue_invoices() {
        $payment_processor = new SMS_Payment_Processor();
        return $payment_processor->get_overdue_invoices();
    }

    private function calculate_days_overdue($due_date) {
        $payment_processor = new SMS_Payment_Processor();
        return $payment_processor->calculate_days_overdue($due_date);
    }

    private function get_student_contact_data($student_id) {
        $payment_processor = new SMS_Payment_Processor();
        return $payment_processor->get_student_contact_data($student_id);
    }

    private function get_invoice_reminder_data($invoice_id) {
        $payment_processor = new SMS_Payment_Processor();
        return $payment_processor->get_invoice_reminder_data($invoice_id);
    }
}