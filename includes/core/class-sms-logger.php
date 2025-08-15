<?php
/**
 * Logging functionality for the plugin.
 *
 * Provides centralized logging with different levels and contexts.
 *
 * @package    School_Management_System
 * @subpackage School_Management_System/includes/core
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Logger class.
 */
class SMS_Logger {

    /**
     * Log levels.
     */
    const EMERGENCY = 'emergency';
    const ALERT     = 'alert';
    const CRITICAL  = 'critical';
    const ERROR     = 'error';
    const WARNING   = 'warning';
    const NOTICE    = 'notice';
    const INFO      = 'info';
    const DEBUG     = 'debug';

    /**
     * Log file path.
     */
    private $log_file;

    /**
     * Constructor.
     */
    public function __construct() {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/sms-logs/';
        
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }
        
        $this->log_file = $log_dir . 'sms-' . date('Y-m-d') . '.log';
    }

    /**
     * Log a message.
     */
    public function log($message, $level = self::INFO, $context = array()) {
        if (!$this->should_log($level)) {
            return;
        }
        
        $timestamp = current_time('Y-m-d H:i:s');
        $user_id = get_current_user_id();
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        $log_entry = array(
            'timestamp' => $timestamp,
            'level' => strtoupper($level),
            'message' => $message,
            'user_id' => $user_id,
            'ip_address' => $ip_address,
            'context' => $context
        );
        
        // Write to file
        $this->write_to_file($log_entry);
        
        // Store critical errors in database
        if (in_array($level, array(self::EMERGENCY, self::ALERT, self::CRITICAL, self::ERROR))) {
            $this->store_in_database($log_entry);
        }
        
        // Send email for critical errors
        if (in_array($level, array(self::EMERGENCY, self::ALERT, self::CRITICAL))) {
            $this->send_critical_error_email($log_entry);
        }
    }

    /**
     * Check if we should log this level.
     */
    private function should_log($level) {
        $log_level = get_option('sms_log_level', self::INFO);
        
        $levels = array(
            self::EMERGENCY => 0,
            self::ALERT     => 1,
            self::CRITICAL  => 2,
            self::ERROR     => 3,
            self::WARNING   => 4,
            self::NOTICE    => 5,
            self::INFO      => 6,
            self::DEBUG     => 7
        );
        
        return isset($levels[$level]) && isset($levels[$log_level]) && 
               $levels[$level] <= $levels[$log_level];
    }

    /**
     * Write log entry to file.
     */
    private function write_to_file($log_entry) {
        $formatted_entry = sprintf(
            "[%s] %s: %s (User: %s, IP: %s) %s\n",
            $log_entry['timestamp'],
            $log_entry['level'],
            $log_entry['message'],
            $log_entry['user_id'],
            $log_entry['ip_address'],
            !empty($log_entry['context']) ? wp_json_encode($log_entry['context']) : ''
        );
        
        error_log($formatted_entry, 3, $this->log_file);
    }

    /**
     * Store critical errors in database.
     */
    private function store_in_database($log_entry) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'sms_activity_log',
            array(
                'user_id' => $log_entry['user_id'],
                'action' => 'system_log',
                'object_type' => 'log_entry',
                'object_id' => 0,
                'details' => wp_json_encode($log_entry),
                'ip_address' => $log_entry['ip_address'],
                'timestamp' => $log_entry['timestamp']
            )
        );
    }

    /**
     * Send email for critical errors.
     */
    private function send_critical_error_email($log_entry) {
        if (!get_option('sms_critical_error_emails', true)) {
            return;
        }
        
        $admin_email = get_option('admin_email');
        $subject = sprintf(
            '[%s] Critical Error in School Management System',
            get_bloginfo('name')
        );
        
        $message = sprintf(
            "A critical error occurred in the School Management System:\n\n" .
            "Time: %s\n" .
            "Level: %s\n" .
            "Message: %s\n" .
            "User ID: %s\n" .
            "IP Address: %s\n" .
            "Context: %s\n\n" .
            "Please check the system logs for more details.",
            $log_entry['timestamp'],
            $log_entry['level'],
            $log_entry['message'],
            $log_entry['user_id'],
            $log_entry['ip_address'],
            wp_json_encode($log_entry['context'])
        );
        
        wp_mail($admin_email, $subject, $message);
    }

    /**
     * Log emergency message.
     */
    public function emergency($message, $context = array()) {
        $this->log($message, self::EMERGENCY, $context);
    }

    /**
     * Log alert message.
     */
    public function alert($message, $context = array()) {
        $this->log($message, self::ALERT, $context);
    }

    /**
     * Log critical message.
     */
    public function critical($message, $context = array()) {
        $this->log($message, self::CRITICAL, $context);
    }

    /**
     * Log error message.
     */
    public function error($message, $context = array()) {
        $this->log($message, self::ERROR, $context);
    }

    /**
     * Log warning message.
     */
    public function warning($message, $context = array()) {
        $this->log($message, self::WARNING, $context);
    }

    /**
     * Log notice message.
     */
    public function notice($message, $context = array()) {
        $this->log($message, self::NOTICE, $context);
    }

    /**
     * Log info message.
     */
    public function info($message, $context = array()) {
        $this->log($message, self::INFO, $context);
    }

    /**
     * Log debug message.
     */
    public function debug($message, $context = array()) {
        $this->log($message, self::DEBUG, $context);
    }

    /**
     * Get log entries from database.
     */
    public function get_log_entries($limit = 100, $level = null) {
        global $wpdb;
        
        $where_clause = "WHERE action = 'system_log'";
        if ($level) {
            $where_clause .= $wpdb->prepare(" AND details LIKE %s", '%"level":"' . strtoupper($level) . '"%');
        }
        
        $query = $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sms_activity_log 
             {$where_clause} 
             ORDER BY timestamp DESC 
             LIMIT %d",
            $limit
        );
        
        return $wpdb->get_results($query);
    }

    /**
     * Clear old log files.
     */
    public function cleanup_old_logs($days = 30) {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/sms-logs/';
        
        if (!is_dir($log_dir)) {
            return;
        }
        
        $files = glob($log_dir . 'sms-*.log');
        $cutoff_time = time() - ($days * 24 * 60 * 60);
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoff_time) {
                unlink($file);
            }
        }
        
        $this->info("Cleaned up log files older than {$days} days");
    }
}