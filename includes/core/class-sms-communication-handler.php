<?php
/**
 * Communication Handler
 *
 * Handles SMS and email communications for the school management system.
 *
 * @package    School_Management_System
 * @subpackage School_Management_System/includes/core
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Communication Handler Class
 */
class SMS_Communication_Handler extends SMS_Base {

    /**
     * SMS service provider settings
     */
    private $sms_settings;

    /**
     * Email settings
     */
    private $email_settings;

    /**
     * SMS API instance
     */
    private $africastalking_api;

    /**
     * Template manager instance
     */
    private $template_manager;

    /**
     * Queue manager instance
     */
    private $queue_manager;

    /**
     * Initialize the communication handler.
     */
    public function __construct() {
        parent::__construct();
        
        $this->load_settings();
    }

    /**
     * Load communication settings
     */
    private function load_settings() {
        $this->sms_settings = get_option('sms_africastalking_settings', array());
        $this->email_settings = get_option('sms_email_settings', array());
    }

    /**
     * Initialize SMS components
     */
    private function init_sms_components() {
        if (!$this->africastalking_api) {
            if (!class_exists('SMS_Africastalking_API')) {
                require_once SMS_PLUGIN_DIR . 'includes/integrations/class-sms-africastalking-api.php';
            }
            $this->africastalking_api = new SMS_Africastalking_API();
        }

        if (!$this->template_manager) {
            if (!class_exists('SMS_Template_Manager')) {
                require_once SMS_PLUGIN_DIR . 'includes/integrations/class-sms-template-manager.php';
            }
            $this->template_manager = new SMS_Template_Manager();
        }

        if (!$this->queue_manager) {
            if (!class_exists('SMS_Queue_Manager')) {
                require_once SMS_PLUGIN_DIR . 'includes/integrations/class-sms-queue-manager.php';
            }
            $this->queue_manager = new SMS_Queue_Manager();
        }
    }

    /**
     * Send SMS to recipients.
     *
     * @param array  $recipients Array of phone numbers or recipient objects
     * @param string $message SMS message
     * @param array  $options Additional options
     * @return array Result with success status and details
     */
    public function send_sms($recipients, $message, $options = []) {
        try {
            // Initialize SMS components if not already done
            $this->init_sms_components();

            // Validate inputs
            if (empty($recipients) || empty($message)) {
                return array(
                    'success' => false,
                    'error' => __('Recipients and message are required', 'school-management-system')
                );
            }

            // Normalize recipients to phone numbers
            $phone_numbers = $this->extract_phone_numbers($recipients);
            
            if (empty($phone_numbers)) {
                return array(
                    'success' => false,
                    'error' => __('No valid phone numbers found', 'school-management-system')
                );
            }

            // Check if we should use queue for large batches
            $use_queue = (count($phone_numbers) > 50) || ($options['use_queue'] ?? false);
            
            if ($use_queue) {
                return $this->queue_sms($phone_numbers, $message, $options);
            } else {
                return $this->send_immediate_sms($phone_numbers, $message, $options);
            }

        } catch (Exception $e) {
            $this->log("SMS sending failed: " . $e->getMessage(), 'error');
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }

    /**
     * Send bulk SMS to multiple recipient groups
     *
     * @param array $recipient_groups Array of recipient groups
     * @param string $message SMS message
     * @param array $options Additional options
     * @return array Result with success status and details
     */
    public function send_bulk_sms($recipient_groups, $message, $options = []) {
        $this->init_sms_components();
        
        // Use Africastalking API for bulk sending
        return $this->africastalking_api->send_bulk_sms($recipient_groups, $message, $options);
    }

    /**
     * Send SMS using template
     *
     * @param string $template_id Template identifier
     * @param array $recipients Recipients array
     * @param array $template_data Data for template placeholders
     * @param array $options Additional options
     * @return array Result with success status and details
     */
    public function send_template_sms($template_id, $recipients, $template_data = array(), $options = array()) {
        $this->init_sms_components();

        // Render template
        $message = $this->template_manager->render_template($template_id, $template_data);
        
        if (is_wp_error($message)) {
            return array(
                'success' => false,
                'error' => $message->get_error_message()
            );
        }

        // Track template usage
        $this->template_manager->track_template_usage($template_id);

        // Add template info to options
        $options['template_id'] = $template_id;

        return $this->send_sms($recipients, $message, $options);
    }

    /**
     * Extract phone numbers from recipients array
     */
    private function extract_phone_numbers($recipients) {
        $phone_numbers = array();

        foreach ($recipients as $recipient) {
            if (is_string($recipient)) {
                // Direct phone number
                $phone_numbers[] = $recipient;
            } elseif (is_array($recipient) && isset($recipient['phone'])) {
                // Recipient object with phone field
                $phone_numbers[] = $recipient['phone'];
            } elseif (is_object($recipient) && isset($recipient->phone)) {
                // Recipient object with phone property
                $phone_numbers[] = $recipient->phone;
            }
        }

        return array_filter($phone_numbers);
    }

    /**
     * Send immediate SMS (without queue)
     */
    private function send_immediate_sms($phone_numbers, $message, $options = array()) {
        return $this->africastalking_api->send_sms($phone_numbers, $message, $options);
    }

    /**
     * Queue SMS for later processing
     */
    private function queue_sms($phone_numbers, $message, $options = array()) {
        $queue_options = array(
            'priority' => $options['priority'] ?? 'normal',
            'template_id' => $options['template_id'] ?? null,
            'scheduled_at' => $options['scheduled_at'] ?? null,
            'reference_id' => $options['reference_id'] ?? null,
            'reference_type' => $options['reference_type'] ?? null,
            'metadata' => $options['metadata'] ?? null
        );

        $result = $this->queue_manager->add_to_queue($phone_numbers, $message, $queue_options);

        if ($result['success']) {
            return array(
                'success' => true,
                'queued' => true,
                'queued_count' => $result['added_count'],
                'failed_count' => $result['failed_count'],
                'message' => sprintf(
                    __('%d messages queued for sending', 'school-management-system'),
                    $result['added_count']
                )
            );
        } else {
            return array(
                'success' => false,
                'error' => __('Failed to queue messages', 'school-management-system')
            );
        }
    }

    /**
     * Send email to recipients.
     *
     * @param array  $recipients Array of email addresses
     * @param string $subject Email subject
     * @param string $message Email message
     * @param array  $options Additional options
     * @return bool Success status
     */
    public function send_email($recipients, $subject, $message, $options = []) {
        try {
            // Validate recipients
            if (empty($recipients) || !is_array($recipients)) {
                return false;
            }

            // Validate subject and message
            if (empty($subject) || empty($message)) {
                return false;
            }

            // Filter valid email addresses
            $valid_recipients = array_filter($recipients, [$this, 'is_valid_email']);
            
            if (empty($valid_recipients)) {
                return false;
            }

            // Prepare headers
            $headers = ['Content-Type: text/html; charset=UTF-8'];
            
            if (!empty($options['from_email'])) {
                $headers[] = 'From: ' . $options['from_email'];
            }

            if (!empty($options['reply_to'])) {
                $headers[] = 'Reply-To: ' . $options['reply_to'];
            }

            // Send email
            $success = true;
            foreach ($valid_recipients as $recipient) {
                if (!wp_mail($recipient, $subject, $message, $headers)) {
                    $success = false;
                }
            }

            return $success;

        } catch (Exception $e) {
            $this->log("Email sending failed: " . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Send notification (SMS and/or email)
     *
     * @param array $recipients Recipients with contact info
     * @param string $subject Subject/title
     * @param string $message Message content
     * @param array $options Notification options
     * @return array Results
     */
    public function send_notification($recipients, $subject, $message, $options = array()) {
        $results = array(
            'sms' => null,
            'email' => null
        );

        $send_sms = $options['send_sms'] ?? true;
        $send_email = $options['send_email'] ?? false;

        if ($send_sms) {
            $sms_message = $subject . ' - ' . $message;
            $results['sms'] = $this->send_sms($recipients, $sms_message, $options);
        }

        if ($send_email) {
            $email_recipients = $this->extract_email_addresses($recipients);
            if (!empty($email_recipients)) {
                $results['email'] = $this->send_email($email_recipients, $subject, $message, $options);
            }
        }

        return $results;
    }

    /**
     * Extract email addresses from recipients
     */
    private function extract_email_addresses($recipients) {
        $emails = array();

        foreach ($recipients as $recipient) {
            if (is_string($recipient) && is_email($recipient)) {
                $emails[] = $recipient;
            } elseif (is_array($recipient) && isset($recipient['email']) && is_email($recipient['email'])) {
                $emails[] = $recipient['email'];
            } elseif (is_object($recipient) && isset($recipient->email) && is_email($recipient->email)) {
                $emails[] = $recipient->email;
            }
        }

        return $emails;
    }

    /**
     * Get SMS delivery reports
     */
    public function get_sms_delivery_reports($message_ids = array()) {
        $this->init_sms_components();
        return $this->africastalking_api->get_sms_delivery_reports($message_ids);
    }

    /**
     * Get SMS account balance
     */
    public function get_sms_balance() {
        $this->init_sms_components();
        return $this->africastalking_api->get_account_balance();
    }

    /**
     * Get SMS usage statistics
     */
    public function get_sms_usage_stats($period = 'month') {
        $this->init_sms_components();
        return $this->africastalking_api->get_usage_statistics($period);
    }

    /**
     * Get queue statistics
     */
    public function get_queue_stats() {
        $this->init_sms_components();
        return $this->queue_manager->get_queue_stats();
    }

    /**
     * Test SMS configuration
     */
    public function test_sms_configuration() {
        $this->init_sms_components();
        return $this->africastalking_api->test_api_connection();
    }

    /**
     * Validate email address
     */
    protected function is_valid_email($email) {
        return is_email($email);
    }

    /**
     * Format phone number for SMS
     */
    protected function format_phone($phone) {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Handle different Kenyan number formats
        if (strlen($phone) === 10 && (substr($phone, 0, 2) === '07' || substr($phone, 0, 2) === '01')) {
            return '+254' . substr($phone, 1);
        } elseif (strlen($phone) === 9 && (substr($phone, 0, 1) === '7' || substr($phone, 0, 1) === '1')) {
            return '+254' . $phone;
        } elseif (strlen($phone) === 12 && substr($phone, 0, 3) === '254') {
            return '+' . $phone;
        } elseif (strlen($phone) === 13 && substr($phone, 0, 4) === '+254') {
            return $phone;
        }

        return null;
    }

    /**
     * Log communication activity
     */
    protected function log($message, $level = 'info', $context = array()) {
        if (class_exists('SMS_Logger')) {
            $logger = new SMS_Logger();
            $logger->log($message, $level, 'communication');
        } else {
            error_log("SMS Communication: $message");
        }
    }

    /**
     * Get available templates
     */
    public function get_templates($category = null) {
        $this->init_sms_components();
        return $this->template_manager->get_templates($category);
    }

    /**
     * Get template by ID
     */
    public function get_template($template_id) {
        $this->init_sms_components();
        return $this->template_manager->get_template($template_id);
    }

    /**
     * Save custom template
     */
    public function save_template($template_id, $template_data) {
        $this->init_sms_components();
        return $this->template_manager->save_template_data($template_id, $template_data);
    }

    /**
     * Delete custom template
     */
    public function delete_template($template_id) {
        $this->init_sms_components();
        return $this->template_manager->delete_template_data($template_id);
    }

    /**
     * Get template placeholders
     */
    public function get_template_placeholders($category = null) {
        $this->init_sms_components();
        return $this->template_manager->get_placeholders($category);
    }

    /**
     * Cancel queued messages
     */
    public function cancel_queued_messages($reference_id, $reference_type = null) {
        $this->init_sms_components();
        return $this->queue_manager->cancel_messages($reference_id, $reference_type);
    }

    /**
     * Get communication settings
     */
    public function get_settings() {
        return array(
            'sms' => $this->sms_settings,
            'email' => $this->email_settings
        );
    }

    /**
     * Update communication settings
     */
    public function update_settings($settings) {
        if (isset($settings['sms'])) {
            update_option('sms_africastalking_settings', $settings['sms']);
            $this->sms_settings = $settings['sms'];
        }

        if (isset($settings['email'])) {
            update_option('sms_email_settings', $settings['email']);
            $this->email_settings = $settings['email'];
        }

        return true;
    }
}