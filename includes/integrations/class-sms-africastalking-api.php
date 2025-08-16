<?php
/**
 * Africastalking API Integration
 *
 * @package SchoolManagementSystem
 * @subpackage Integrations
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class SMS_Africastalking_API
 * 
 * Handles integration with Africastalking SMS API
 */
class SMS_Africastalking_API {

    /**
     * API credentials
     */
    private $username;
    private $api_key;
    private $sender_id;
    private $environment;

    /**
     * API endpoints
     */
    private $base_url;
    private $endpoints;

    /**
     * Constructor
     */
    public function __construct() {
        $this->load_settings();
        $this->set_endpoints();
        
        // Add hooks
        add_action('wp_ajax_sms_test_africastalking_connection', array($this, 'test_connection'));
        add_action('wp_ajax_sms_get_africastalking_balance', array($this, 'get_balance'));
        add_action('wp_ajax_sms_get_delivery_reports', array($this, 'get_delivery_reports'));
    }

    /**
     * Load API settings
     */
    private function load_settings() {
        $settings = get_option('sms_africastalking_settings', array());
        
        $this->username = $settings['username'] ?? '';
        $this->api_key = $settings['api_key'] ?? '';
        $this->sender_id = $settings['sender_id'] ?? '';
        $this->environment = $settings['environment'] ?? 'sandbox';
    }

    /**
     * Set API endpoints based on environment
     */
    private function set_endpoints() {
        if ($this->environment === 'production') {
            $this->base_url = 'https://api.africastalking.com/version1';
        } else {
            $this->base_url = 'https://api.sandbox.africastalking.com/version1';
        }

        $this->endpoints = array(
            'sms' => $this->base_url . '/messaging',
            'user' => $this->base_url . '/user',
            'delivery_reports' => $this->base_url . '/messaging/delivery-reports'
        );
    }

    /**
     * Send SMS to single or multiple recipients
     */
    public function send_sms($recipients, $message, $options = array()) {
        // Validate inputs
        if (empty($recipients) || empty($message)) {
            return array(
                'success' => false,
                'error' => __('Recipients and message are required', 'school-management-system')
            );
        }

        // Ensure recipients is an array
        if (!is_array($recipients)) {
            $recipients = array($recipients);
        }

        // Format phone numbers
        $formatted_recipients = array();
        foreach ($recipients as $recipient) {
            $phone = $this->format_phone_number($recipient);
            if ($phone) {
                $formatted_recipients[] = $phone;
            }
        }

        if (empty($formatted_recipients)) {
            return array(
                'success' => false,
                'error' => __('No valid phone numbers found', 'school-management-system')
            );
        }

        // Prepare SMS data
        $sms_data = array(
            'username' => $this->username,
            'to' => implode(',', $formatted_recipients),
            'message' => $message
        );

        // Add sender ID if configured
        if (!empty($this->sender_id)) {
            $sms_data['from'] = $this->sender_id;
        }

        // Add optional parameters
        if (isset($options['enqueue'])) {
            $sms_data['enqueue'] = $options['enqueue'] ? '1' : '0';
        }

        if (isset($options['keyword'])) {
            $sms_data['keyword'] = $options['keyword'];
        }

        if (isset($options['linkId'])) {
            $sms_data['linkId'] = $options['linkId'];
        }

        if (isset($options['retryDurationInHours'])) {
            $sms_data['retryDurationInHours'] = $options['retryDurationInHours'];
        }

        // Make API request
        $response = $this->make_request('POST', $this->endpoints['sms'], $sms_data);

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error' => $response->get_error_message()
            );
        }

        // Parse response
        $result = $this->parse_sms_response($response);
        
        // Log the SMS sending attempt
        $this->log_sms_activity($recipients, $message, $result);

        return $result;
    }

    /**
     * Send bulk SMS with recipient groups
     */
    public function send_bulk_sms($recipient_groups, $message, $options = array()) {
        $results = array();
        $total_sent = 0;
        $total_failed = 0;

        foreach ($recipient_groups as $group_name => $recipients) {
            if (empty($recipients)) {
                continue;
            }

            $group_result = $this->send_sms($recipients, $message, $options);
            $results[$group_name] = $group_result;

            if ($group_result['success']) {
                $total_sent += $group_result['sent_count'] ?? 0;
            } else {
                $total_failed += count($recipients);
            }

            // Add delay between batches to respect rate limits
            if (count($recipient_groups) > 1) {
                sleep(1);
            }
        }

        return array(
            'success' => $total_sent > 0,
            'results' => $results,
            'total_sent' => $total_sent,
            'total_failed' => $total_failed,
            'summary' => sprintf(
                __('Sent: %d, Failed: %d', 'school-management-system'),
                $total_sent,
                $total_failed
            )
        );
    }

    /**
     * Get account balance
     */
    public function get_account_balance() {
        $response = $this->make_request('GET', $this->endpoints['user'], array(
            'username' => $this->username
        ));

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error' => $response->get_error_message()
            );
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (isset($data['UserData']['balance'])) {
            return array(
                'success' => true,
                'balance' => $data['UserData']['balance']
            );
        }

        return array(
            'success' => false,
            'error' => __('Unable to retrieve balance', 'school-management-system')
        );
    }

    /**
     * Get SMS delivery reports
     */
    public function get_sms_delivery_reports($message_ids = array()) {
        $params = array('username' => $this->username);
        
        if (!empty($message_ids)) {
            $params['messageId'] = implode(',', $message_ids);
        }

        $response = $this->make_request('GET', $this->endpoints['delivery_reports'], $params);

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error' => $response->get_error_message()
            );
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (isset($data['SMSMessageData']['Recipients'])) {
            return array(
                'success' => true,
                'reports' => $data['SMSMessageData']['Recipients']
            );
        }

        return array(
            'success' => false,
            'error' => __('No delivery reports found', 'school-management-system')
        );
    }

    /**
     * Test API connection
     */
    public function test_api_connection() {
        $balance_result = $this->get_account_balance();
        
        if ($balance_result['success']) {
            return array(
                'success' => true,
                'message' => sprintf(
                    __('Connection successful. Account balance: %s', 'school-management-system'),
                    $balance_result['balance']
                )
            );
        }

        return array(
            'success' => false,
            'error' => $balance_result['error']
        );
    }

    /**
     * Format phone number for Africastalking
     */
    private function format_phone_number($phone) {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Handle different Kenyan number formats
        if (strlen($phone) === 10 && (substr($phone, 0, 2) === '07' || substr($phone, 0, 2) === '01')) {
            // Convert 07XXXXXXXX or 01XXXXXXXX to +254XXXXXXXXX
            return '+254' . substr($phone, 1);
        } elseif (strlen($phone) === 9 && (substr($phone, 0, 1) === '7' || substr($phone, 0, 1) === '1')) {
            // Convert 7XXXXXXXX or 1XXXXXXXX to +254XXXXXXXXX
            return '+254' . $phone;
        } elseif (strlen($phone) === 12 && substr($phone, 0, 3) === '254') {
            // Convert 254XXXXXXXXX to +254XXXXXXXXX
            return '+' . $phone;
        } elseif (strlen($phone) === 13 && substr($phone, 0, 4) === '+254') {
            // Already in correct format
            return $phone;
        }

        // For other countries or invalid formats, return as is
        return $phone;
    }

    /**
     * Make HTTP request to Africastalking API
     */
    private function make_request($method, $url, $data = array()) {
        $headers = array(
            'Accept' => 'application/json',
            'Content-Type' => 'application/x-www-form-urlencoded',
            'apiKey' => $this->api_key
        );

        $args = array(
            'method' => $method,
            'headers' => $headers,
            'timeout' => 30,
            'sslverify' => true
        );

        if ($method === 'POST') {
            $args['body'] = http_build_query($data);
        } elseif ($method === 'GET' && !empty($data)) {
            $url .= '?' . http_build_query($data);
        }

        $response = wp_remote_request($url, $args);

        // Check for HTTP errors
        if (is_wp_error($response)) {
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code >= 400) {
            $body = wp_remote_retrieve_body($response);
            $error_data = json_decode($body, true);
            $error_message = $error_data['SMSMessageData']['Message'] ?? 
                           $error_data['message'] ?? 
                           sprintf(__('HTTP Error %d', 'school-management-system'), $response_code);
            
            return new WP_Error('api_error', $error_message);
        }

        return $response;
    }

    /**
     * Parse SMS response from Africastalking
     */
    private function parse_sms_response($response) {
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!isset($data['SMSMessageData'])) {
            return array(
                'success' => false,
                'error' => __('Invalid response format', 'school-management-system')
            );
        }

        $sms_data = $data['SMSMessageData'];
        $recipients = $sms_data['Recipients'] ?? array();
        
        $sent_count = 0;
        $failed_count = 0;
        $message_ids = array();
        $failed_recipients = array();

        foreach ($recipients as $recipient) {
            if (isset($recipient['status']) && $recipient['status'] === 'Success') {
                $sent_count++;
                if (isset($recipient['messageId'])) {
                    $message_ids[] = $recipient['messageId'];
                }
            } else {
                $failed_count++;
                $failed_recipients[] = array(
                    'number' => $recipient['number'] ?? '',
                    'status' => $recipient['status'] ?? 'Unknown',
                    'cost' => $recipient['cost'] ?? ''
                );
            }
        }

        return array(
            'success' => $sent_count > 0,
            'message' => $sms_data['Message'] ?? '',
            'sent_count' => $sent_count,
            'failed_count' => $failed_count,
            'message_ids' => $message_ids,
            'failed_recipients' => $failed_recipients,
            'total_cost' => $this->calculate_total_cost($recipients)
        );
    }

    /**
     * Calculate total cost from recipients data
     */
    private function calculate_total_cost($recipients) {
        $total_cost = 0;
        
        foreach ($recipients as $recipient) {
            if (isset($recipient['cost'])) {
                // Remove currency symbols and convert to float
                $cost = preg_replace('/[^0-9.]/', '', $recipient['cost']);
                $total_cost += floatval($cost);
            }
        }

        return $total_cost;
    }

    /**
     * Log SMS activity
     */
    private function log_sms_activity($recipients, $message, $result) {
        $log_data = array(
            'timestamp' => current_time('mysql'),
            'recipients_count' => is_array($recipients) ? count($recipients) : 1,
            'message_length' => strlen($message),
            'sent_count' => $result['sent_count'] ?? 0,
            'failed_count' => $result['failed_count'] ?? 0,
            'total_cost' => $result['total_cost'] ?? 0,
            'success' => $result['success'] ?? false
        );

        // Store in options table (you might want to use a custom table for better performance)
        $existing_logs = get_option('sms_africastalking_logs', array());
        $existing_logs[] = $log_data;
        
        // Keep only last 100 logs
        if (count($existing_logs) > 100) {
            $existing_logs = array_slice($existing_logs, -100);
        }
        
        update_option('sms_africastalking_logs', $existing_logs);
    }

    /**
     * Get SMS usage statistics
     */
    public function get_usage_statistics($period = 'month') {
        $logs = get_option('sms_africastalking_logs', array());
        
        if (empty($logs)) {
            return array(
                'total_sent' => 0,
                'total_failed' => 0,
                'total_cost' => 0,
                'success_rate' => 0
            );
        }

        // Filter logs by period
        $cutoff_date = $this->get_period_cutoff_date($period);
        $filtered_logs = array_filter($logs, function($log) use ($cutoff_date) {
            return strtotime($log['timestamp']) >= $cutoff_date;
        });

        $total_sent = array_sum(array_column($filtered_logs, 'sent_count'));
        $total_failed = array_sum(array_column($filtered_logs, 'failed_count'));
        $total_cost = array_sum(array_column($filtered_logs, 'total_cost'));
        $total_attempts = $total_sent + $total_failed;
        
        $success_rate = $total_attempts > 0 ? ($total_sent / $total_attempts) * 100 : 0;

        return array(
            'total_sent' => $total_sent,
            'total_failed' => $total_failed,
            'total_cost' => $total_cost,
            'success_rate' => round($success_rate, 2),
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
     * AJAX handler for testing connection
     */
    public function test_connection() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_africastalking_test_nonce')) {
            wp_send_json_error(__('Security check failed', 'school-management-system'));
        }

        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to test the connection', 'school-management-system'));
        }

        $result = $this->test_api_connection();
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX handler for getting balance
     */
    public function get_balance() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_africastalking_balance_nonce')) {
            wp_send_json_error(__('Security check failed', 'school-management-system'));
        }

        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to check balance', 'school-management-system'));
        }

        $result = $this->get_account_balance();
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX handler for getting delivery reports
     */
    public function get_delivery_reports() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_africastalking_reports_nonce')) {
            wp_send_json_error(__('Security check failed', 'school-management-system'));
        }

        // Check capabilities
        if (!current_user_can('manage_notices')) {
            wp_send_json_error(__('You do not have permission to view delivery reports', 'school-management-system'));
        }

        $message_ids = isset($_POST['message_ids']) ? array_map('sanitize_text_field', $_POST['message_ids']) : array();
        
        $result = $this->get_sms_delivery_reports($message_ids);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * Validate API credentials
     */
    public function validate_credentials($username, $api_key) {
        $temp_username = $this->username;
        $temp_api_key = $this->api_key;
        
        // Temporarily set new credentials
        $this->username = $username;
        $this->api_key = $api_key;
        
        // Test connection
        $result = $this->test_api_connection();
        
        // Restore original credentials
        $this->username = $temp_username;
        $this->api_key = $temp_api_key;
        
        return $result;
    }

    /**
     * Get API configuration status
     */
    public function get_configuration_status() {
        $status = array(
            'configured' => false,
            'username' => !empty($this->username),
            'api_key' => !empty($this->api_key),
            'sender_id' => !empty($this->sender_id),
            'environment' => $this->environment
        );

        $status['configured'] = $status['username'] && $status['api_key'];

        return $status;
    }

    /**
     * Update API settings
     */
    public function update_settings($settings) {
        $current_settings = get_option('sms_africastalking_settings', array());
        $new_settings = array_merge($current_settings, $settings);
        
        update_option('sms_africastalking_settings', $new_settings);
        
        // Reload settings
        $this->load_settings();
        $this->set_endpoints();
        
        return true;
    }
}

// Initialize the Africastalking API integration
new SMS_Africastalking_API();