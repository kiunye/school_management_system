<?php
/**
 * SMS Queue Manager
 *
 * @package SchoolManagementSystem
 * @subpackage Integrations
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class SMS_Queue_Manager
 * 
 * Manages SMS sending queue for large recipient lists and rate limiting
 */
class SMS_Queue_Manager {

    /**
     * Queue table name
     */
    private $queue_table;

    /**
     * Batch size for processing
     */
    private $batch_size = 100;

    /**
     * Rate limit (messages per minute)
     */
    private $rate_limit = 100;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->queue_table = $wpdb->prefix . 'sms_queue';
        
        // Create queue table if it doesn't exist
        $this->create_queue_table();
        
        // Schedule queue processing
        $this->schedule_queue_processing();
        
        // Add hooks
        add_action('sms_process_queue', array($this, 'process_queue'));
        add_action('wp_ajax_sms_queue_status', array($this, 'get_queue_status'));
        add_action('wp_ajax_sms_clear_queue', array($this, 'clear_queue'));
        add_action('wp_ajax_sms_retry_failed', array($this, 'retry_failed_messages'));
    }

    /**
     * Create SMS queue table
     */
    private function create_queue_table() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->queue_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            recipient varchar(20) NOT NULL,
            message text NOT NULL,
            template_id varchar(50) DEFAULT NULL,
            priority enum('low','normal','high','urgent') DEFAULT 'normal',
            status enum('pending','processing','sent','failed','cancelled') DEFAULT 'pending',
            attempts int(11) DEFAULT 0,
            max_attempts int(11) DEFAULT 3,
            scheduled_at datetime DEFAULT NULL,
            sent_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            error_message text DEFAULT NULL,
            reference_id varchar(100) DEFAULT NULL,
            reference_type varchar(50) DEFAULT NULL,
            cost decimal(10,4) DEFAULT NULL,
            message_id varchar(100) DEFAULT NULL,
            delivery_status varchar(20) DEFAULT NULL,
            metadata longtext DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_status (status),
            KEY idx_priority (priority),
            KEY idx_scheduled_at (scheduled_at),
            KEY idx_reference (reference_id, reference_type),
            KEY idx_recipient (recipient)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Add message to queue
     */
    public function add_to_queue($recipients, $message, $options = array()) {
        global $wpdb;

        // Ensure recipients is an array
        if (!is_array($recipients)) {
            $recipients = array($recipients);
        }

        $default_options = array(
            'priority' => 'normal',
            'template_id' => null,
            'scheduled_at' => null,
            'max_attempts' => 3,
            'reference_id' => null,
            'reference_type' => null,
            'metadata' => null
        );

        $options = array_merge($default_options, $options);

        $added_count = 0;
        $failed_count = 0;

        foreach ($recipients as $recipient) {
            // Format phone number
            $formatted_phone = $this->format_phone_number($recipient);
            
            if (!$formatted_phone) {
                $failed_count++;
                continue;
            }

            // Prepare data for insertion
            $data = array(
                'recipient' => $formatted_phone,
                'message' => $message,
                'template_id' => $options['template_id'],
                'priority' => $options['priority'],
                'scheduled_at' => $options['scheduled_at'],
                'max_attempts' => $options['max_attempts'],
                'reference_id' => $options['reference_id'],
                'reference_type' => $options['reference_type'],
                'metadata' => is_array($options['metadata']) ? json_encode($options['metadata']) : $options['metadata']
            );

            $result = $wpdb->insert($this->queue_table, $data);
            
            if ($result) {
                $added_count++;
            } else {
                $failed_count++;
            }
        }

        return array(
            'success' => $added_count > 0,
            'added_count' => $added_count,
            'failed_count' => $failed_count,
            'total_recipients' => count($recipients)
        );
    }

    /**
     * Process queue
     */
    public function process_queue() {
        global $wpdb;

        // Get pending messages ordered by priority and creation time
        $messages = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$this->queue_table} 
            WHERE status = 'pending' 
            AND (scheduled_at IS NULL OR scheduled_at <= %s)
            ORDER BY 
                FIELD(priority, 'urgent', 'high', 'normal', 'low'),
                created_at ASC
            LIMIT %d
        ", current_time('mysql'), $this->batch_size));

        if (empty($messages)) {
            return array(
                'processed' => 0,
                'sent' => 0,
                'failed' => 0
            );
        }

        $processed = 0;
        $sent = 0;
        $failed = 0;

        // Initialize Africastalking API
        if (!class_exists('SMS_Africastalking_API')) {
            require_once SMS_PLUGIN_DIR . 'includes/integrations/class-sms-africastalking-api.php';
        }
        
        $africastalking = new SMS_Africastalking_API();

        // Group messages by content for bulk sending
        $message_groups = array();
        foreach ($messages as $message) {
            $key = md5($message->message);
            if (!isset($message_groups[$key])) {
                $message_groups[$key] = array(
                    'message' => $message->message,
                    'recipients' => array(),
                    'queue_ids' => array()
                );
            }
            $message_groups[$key]['recipients'][] = $message->recipient;
            $message_groups[$key]['queue_ids'][] = $message->id;
        }

        // Process each group
        foreach ($message_groups as $group) {
            // Mark messages as processing
            $queue_ids = implode(',', $group['queue_ids']);
            $wpdb->query("
                UPDATE {$this->queue_table} 
                SET status = 'processing', updated_at = NOW() 
                WHERE id IN ($queue_ids)
            ");

            // Send SMS
            $result = $africastalking->send_sms($group['recipients'], $group['message']);

            // Update queue items based on result
            if ($result['success']) {
                // Update successful sends
                $sent_count = $result['sent_count'] ?? 0;
                $failed_count = $result['failed_count'] ?? 0;

                // For successful messages
                if ($sent_count > 0) {
                    $wpdb->query($wpdb->prepare("
                        UPDATE {$this->queue_table} 
                        SET status = 'sent', sent_at = %s, updated_at = NOW() 
                        WHERE id IN ($queue_ids) 
                        LIMIT %d
                    ", current_time('mysql'), $sent_count));
                    
                    $sent += $sent_count;
                }

                // For failed messages
                if ($failed_count > 0) {
                    $this->handle_failed_messages($group['queue_ids'], $failed_count, $result['error'] ?? 'Unknown error');
                    $failed += $failed_count;
                }
            } else {
                // All messages failed
                $this->handle_failed_messages($group['queue_ids'], count($group['queue_ids']), $result['error'] ?? 'Unknown error');
                $failed += count($group['queue_ids']);
            }

            $processed += count($group['queue_ids']);

            // Respect rate limits
            if ($processed >= $this->rate_limit) {
                break;
            }

            // Small delay between batches
            usleep(100000); // 0.1 seconds
        }

        // Log processing results
        $this->log_queue_processing($processed, $sent, $failed);

        return array(
            'processed' => $processed,
            'sent' => $sent,
            'failed' => $failed
        );
    }

    /**
     * Handle failed messages
     */
    private function handle_failed_messages($queue_ids, $failed_count, $error_message) {
        global $wpdb;

        // Get messages that haven't exceeded max attempts
        $messages_to_retry = $wpdb->get_results($wpdb->prepare("
            SELECT id, attempts, max_attempts 
            FROM {$this->queue_table} 
            WHERE id IN (" . implode(',', $queue_ids) . ") 
            AND attempts < max_attempts
            LIMIT %d
        ", $failed_count));

        $retry_ids = array();
        $permanent_fail_ids = array();

        foreach ($queue_ids as $id) {
            $message = $wpdb->get_row($wpdb->prepare("
                SELECT attempts, max_attempts 
                FROM {$this->queue_table} 
                WHERE id = %d
            ", $id));

            if ($message && $message->attempts < $message->max_attempts) {
                $retry_ids[] = $id;
            } else {
                $permanent_fail_ids[] = $id;
            }
        }

        // Update messages for retry
        if (!empty($retry_ids)) {
            $retry_ids_str = implode(',', $retry_ids);
            $wpdb->query($wpdb->prepare("
                UPDATE {$this->queue_table} 
                SET status = 'pending', 
                    attempts = attempts + 1, 
                    error_message = %s,
                    scheduled_at = DATE_ADD(NOW(), INTERVAL (attempts + 1) * 5 MINUTE),
                    updated_at = NOW()
                WHERE id IN ($retry_ids_str)
            ", $error_message));
        }

        // Mark permanently failed messages
        if (!empty($permanent_fail_ids)) {
            $fail_ids_str = implode(',', $permanent_fail_ids);
            $wpdb->query($wpdb->prepare("
                UPDATE {$this->queue_table} 
                SET status = 'failed', 
                    error_message = %s,
                    updated_at = NOW()
                WHERE id IN ($fail_ids_str)
            ", $error_message));
        }
    }

    /**
     * Get queue statistics
     */
    public function get_queue_stats() {
        global $wpdb;

        $stats = $wpdb->get_row("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
            FROM {$this->queue_table}
        ", ARRAY_A);

        return $stats;
    }

    /**
     * Get queue items with pagination
     */
    public function get_queue_items($status = null, $limit = 50, $offset = 0) {
        global $wpdb;

        $where_clause = '';
        if ($status) {
            $where_clause = $wpdb->prepare("WHERE status = %s", $status);
        }

        $items = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$this->queue_table} 
            $where_clause
            ORDER BY created_at DESC 
            LIMIT %d OFFSET %d
        ", $limit, $offset));

        return $items;
    }

    /**
     * Cancel queued messages
     */
    public function cancel_messages($reference_id, $reference_type = null) {
        global $wpdb;

        $where_conditions = array("reference_id = %s", "status = 'pending'");
        $where_values = array($reference_id);

        if ($reference_type) {
            $where_conditions[] = "reference_type = %s";
            $where_values[] = $reference_type;
        }

        $where_clause = implode(' AND ', $where_conditions);

        $result = $wpdb->query($wpdb->prepare("
            UPDATE {$this->queue_table} 
            SET status = 'cancelled', updated_at = NOW() 
            WHERE $where_clause
        ", $where_values));

        return $result;
    }

    /**
     * Clear completed messages from queue
     */
    public function clear_completed_messages($older_than_days = 7) {
        global $wpdb;

        $result = $wpdb->query($wpdb->prepare("
            DELETE FROM {$this->queue_table} 
            WHERE status IN ('sent', 'failed', 'cancelled') 
            AND updated_at < DATE_SUB(NOW(), INTERVAL %d DAY)
        ", $older_than_days));

        return $result;
    }

    /**
     * Retry failed messages
     */
    public function retry_failed_messages_data($max_age_hours = 24) {
        global $wpdb;

        $result = $wpdb->query($wpdb->prepare("
            UPDATE {$this->queue_table} 
            SET status = 'pending', 
                attempts = 0, 
                error_message = NULL,
                scheduled_at = NULL,
                updated_at = NOW()
            WHERE status = 'failed' 
            AND updated_at > DATE_SUB(NOW(), INTERVAL %d HOUR)
        ", $max_age_hours));

        return $result;
    }

    /**
     * Format phone number
     */
    private function format_phone_number($phone) {
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

        return null; // Invalid format
    }

    /**
     * Schedule queue processing
     */
    private function schedule_queue_processing() {
        if (!wp_next_scheduled('sms_process_queue')) {
            wp_schedule_event(time(), 'sms_queue_interval', 'sms_process_queue');
        }
    }

    /**
     * Add custom cron interval
     */
    public function add_cron_interval($schedules) {
        $schedules['sms_queue_interval'] = array(
            'interval' => 60, // 1 minute
            'display' => __('Every Minute (SMS Queue)', 'school-management-system')
        );
        return $schedules;
    }

    /**
     * Log queue processing
     */
    private function log_queue_processing($processed, $sent, $failed) {
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'processed' => $processed,
            'sent' => $sent,
            'failed' => $failed
        );

        $existing_logs = get_option('sms_queue_processing_logs', array());
        $existing_logs[] = $log_entry;
        
        // Keep only last 50 logs
        if (count($existing_logs) > 50) {
            $existing_logs = array_slice($existing_logs, -50);
        }
        
        update_option('sms_queue_processing_logs', $existing_logs);
    }

    /**
     * AJAX handler for queue status
     */
    public function get_queue_status() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_queue_nonce')) {
            wp_send_json_error(__('Security check failed', 'school-management-system'));
        }

        // Check capabilities
        if (!current_user_can('manage_notices')) {
            wp_send_json_error(__('You do not have permission to view queue status', 'school-management-system'));
        }

        $stats = $this->get_queue_stats();
        $recent_items = $this->get_queue_items(null, 10);

        wp_send_json_success(array(
            'stats' => $stats,
            'recent_items' => $recent_items
        ));
    }

    /**
     * AJAX handler for clearing queue
     */
    public function clear_queue() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_queue_nonce')) {
            wp_send_json_error(__('Security check failed', 'school-management-system'));
        }

        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to clear the queue', 'school-management-system'));
        }

        $older_than_days = intval($_POST['older_than_days'] ?? 7);
        $result = $this->clear_completed_messages($older_than_days);

        wp_send_json_success(array(
            'cleared_count' => $result,
            'message' => sprintf(__('%d messages cleared from queue', 'school-management-system'), $result)
        ));
    }

    /**
     * AJAX handler for retrying failed messages
     */
    public function retry_failed_messages() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_queue_nonce')) {
            wp_send_json_error(__('Security check failed', 'school-management-system'));
        }

        // Check capabilities
        if (!current_user_can('manage_notices')) {
            wp_send_json_error(__('You do not have permission to retry messages', 'school-management-system'));
        }

        $max_age_hours = intval($_POST['max_age_hours'] ?? 24);
        $result = $this->retry_failed_messages_data($max_age_hours);

        wp_send_json_success(array(
            'retried_count' => $result,
            'message' => sprintf(__('%d messages queued for retry', 'school-management-system'), $result)
        ));
    }

    /**
     * Get processing logs
     */
    public function get_processing_logs($limit = 20) {
        $logs = get_option('sms_queue_processing_logs', array());
        return array_slice(array_reverse($logs), 0, $limit);
    }

    /**
     * Update message delivery status
     */
    public function update_delivery_status($message_id, $delivery_status) {
        global $wpdb;

        return $wpdb->update(
            $this->queue_table,
            array(
                'delivery_status' => $delivery_status,
                'updated_at' => current_time('mysql')
            ),
            array('message_id' => $message_id),
            array('%s', '%s'),
            array('%s')
        );
    }

    /**
     * Get queue health status
     */
    public function get_queue_health() {
        $stats = $this->get_queue_stats();
        $processing_logs = $this->get_processing_logs(5);
        
        $health = array(
            'status' => 'healthy',
            'issues' => array()
        );

        // Check for stuck processing messages
        if ($stats['processing'] > 0) {
            global $wpdb;
            $stuck_count = $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(*) FROM {$this->queue_table} 
                WHERE status = 'processing' 
                AND updated_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE)
            "));
            
            if ($stuck_count > 0) {
                $health['status'] = 'warning';
                $health['issues'][] = sprintf(__('%d messages stuck in processing', 'school-management-system'), $stuck_count);
            }
        }

        // Check for high failure rate
        $total_recent = $stats['sent'] + $stats['failed'];
        if ($total_recent > 0) {
            $failure_rate = ($stats['failed'] / $total_recent) * 100;
            if ($failure_rate > 20) {
                $health['status'] = 'warning';
                $health['issues'][] = sprintf(__('High failure rate: %.1f%%', 'school-management-system'), $failure_rate);
            }
        }

        // Check queue size
        if ($stats['pending'] > 1000) {
            $health['status'] = 'warning';
            $health['issues'][] = sprintf(__('Large queue size: %d pending messages', 'school-management-system'), $stats['pending']);
        }

        return $health;
    }
}

// Initialize the queue manager
add_filter('cron_schedules', array(new SMS_Queue_Manager(), 'add_cron_interval'));
new SMS_Queue_Manager();