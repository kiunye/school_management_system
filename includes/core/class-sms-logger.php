<?php
/**
 * Audit Logging System
 *
 * Handles logging of user actions, data changes, and security events
 *
 * @package School_Management_System
 * @subpackage Core
 */

if (!defined('ABSPATH')) {
    exit;
}

class SMS_Logger {
    
    /**
     * Instance of this class
     */
    private static $instance = null;
    
    /**
     * Log table name
     */
    private $activity_table;
    private $security_table;
    
    /**
     * Get instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        global $wpdb;
        $this->activity_table = $wpdb->prefix . 'sms_activity_log';
        $this->security_table = $wpdb->prefix . 'sms_security_log';
        
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Log post actions
        add_action('save_post', array($this, 'log_post_save'), 10, 3);
        add_action('delete_post', array($this, 'log_post_delete'), 10, 1);
        add_action('wp_trash_post', array($this, 'log_post_trash'), 10, 1);
        add_action('untrash_post', array($this, 'log_post_untrash'), 10, 1);
        
        // Log user actions
        add_action('user_register', array($this, 'log_user_register'), 10, 1);
        add_action('profile_update', array($this, 'log_user_update'), 10, 2);
        add_action('delete_user', array($this, 'log_user_delete'), 10, 1);
        
        // Log payment actions
        add_action('sms_payment_processed', array($this, 'log_payment_processed'), 10, 2);
        add_action('sms_payment_failed', array($this, 'log_payment_failed'), 10, 2);
        add_action('sms_invoice_generated', array($this, 'log_invoice_generated'), 10, 2);
        
        // Log SMS actions
        add_action('sms_message_sent', array($this, 'log_sms_sent'), 10, 3);
        add_action('sms_bulk_message_sent', array($this, 'log_bulk_sms_sent'), 10, 3);
    }
    
    /**
     * Log user activity
     */
    public function log_activity($user_id, $action, $object_type, $object_id, $details = array()) {
        global $wpdb;
        
        $log_entry = array(
            'user_id' => intval($user_id),
            'action' => sanitize_text_field($action),
            'object_type' => sanitize_text_field($object_type),
            'object_id' => intval($object_id),
            'details' => wp_json_encode($details),
            'ip_address' => $this->get_client_ip(),
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            'created_at' => current_time('mysql')
        );
        
        $wpdb->insert($this->activity_table, $log_entry);
        
        // Clean old logs periodically
        if (rand(1, 100) === 1) {
            $this->cleanup_old_logs();
        }
    }
    
    /**
     * Log security events
     */
    public function log_security_event($event_type, $details = array()) {
        global $wpdb;
        
        $log_entry = array(
            'event_type' => sanitize_text_field($event_type),
            'details' => wp_json_encode($details),
            'ip_address' => $this->get_client_ip(),
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            'created_at' => current_time('mysql')
        );
        
        $wpdb->insert($this->security_table, $log_entry);
    }
    
    /**
     * Log post save action
     */
    public function log_post_save($post_id, $post, $update) {
        // Skip autosaves and revisions
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }
        
        // Only log SMS post types
        $sms_post_types = array('cpt_students', 'cpt_classes', 'cpt_fees', 'cpt_invoices', 'cpt_transactions', 'cpt_attendance', 'cpt_timetables', 'cpt_notices', 'cpt_transport_routes');
        
        if (!in_array($post->post_type, $sms_post_types)) {
            return;
        }
        
        $action = $update ? 'updated' : 'created';
        $user_id = get_current_user_id();
        
        $this->log_activity(
            $user_id,
            $action,
            $post->post_type,
            $post_id,
            array(
                'post_title' => $post->post_title,
                'post_status' => $post->post_status
            )
        );
    }
    
    /**
     * Log post delete action
     */
    public function log_post_delete($post_id) {
        $post = get_post($post_id);
        if (!$post) {
            return;
        }
        
        $user_id = get_current_user_id();
        
        $this->log_activity(
            $user_id,
            'deleted',
            $post->post_type,
            $post_id,
            array(
                'post_title' => $post->post_title
            )
        );
    }
    
    /**
     * Log post trash action
     */
    public function log_post_trash($post_id) {
        $post = get_post($post_id);
        if (!$post) {
            return;
        }
        
        $user_id = get_current_user_id();
        
        $this->log_activity(
            $user_id,
            'trashed',
            $post->post_type,
            $post_id,
            array(
                'post_title' => $post->post_title
            )
        );
    }
    
    /**
     * Log post untrash action
     */
    public function log_post_untrash($post_id) {
        $post = get_post($post_id);
        if (!$post) {
            return;
        }
        
        $user_id = get_current_user_id();
        
        $this->log_activity(
            $user_id,
            'restored',
            $post->post_type,
            $post_id,
            array(
                'post_title' => $post->post_title
            )
        );
    }
    
    /**
     * Log user registration
     */
    public function log_user_register($user_id) {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return;
        }
        
        $this->log_activity(
            $user_id,
            'registered',
            'user',
            $user_id,
            array(
                'user_login' => $user->user_login,
                'user_email' => $user->user_email,
                'roles' => $user->roles
            )
        );
    }
    
    /**
     * Log user profile update
     */
    public function log_user_update($user_id, $old_user_data) {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return;
        }
        
        $current_user_id = get_current_user_id();
        
        $this->log_activity(
            $current_user_id,
            'updated_profile',
            'user',
            $user_id,
            array(
                'user_login' => $user->user_login,
                'updated_by_self' => $current_user_id === $user_id
            )
        );
    }
    
    /**
     * Log user deletion
     */
    public function log_user_delete($user_id) {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return;
        }
        
        $current_user_id = get_current_user_id();
        
        $this->log_activity(
            $current_user_id,
            'deleted_user',
            'user',
            $user_id,
            array(
                'user_login' => $user->user_login,
                'user_email' => $user->user_email
            )
        );
    }
    
    /**
     * Log payment processed
     */
    public function log_payment_processed($transaction_id, $payment_data) {
        $user_id = get_current_user_id();
        
        $this->log_activity(
            $user_id,
            'payment_processed',
            'transaction',
            $transaction_id,
            array(
                'amount' => $payment_data['amount'],
                'payment_method' => $payment_data['payment_method'],
                'gateway_reference' => $payment_data['gateway_reference'] ?? '',
                'student_id' => $payment_data['student_id'] ?? ''
            )
        );
    }
    
    /**
     * Log payment failure
     */
    public function log_payment_failed($transaction_id, $error_data) {
        $user_id = get_current_user_id();
        
        $this->log_activity(
            $user_id,
            'payment_failed',
            'transaction',
            $transaction_id,
            array(
                'error_message' => $error_data['error_message'] ?? '',
                'gateway_response' => $error_data['gateway_response'] ?? '',
                'student_id' => $error_data['student_id'] ?? ''
            )
        );
    }
    
    /**
     * Log invoice generation
     */
    public function log_invoice_generated($invoice_id, $invoice_data) {
        $user_id = get_current_user_id();
        
        $this->log_activity(
            $user_id,
            'invoice_generated',
            'invoice',
            $invoice_id,
            array(
                'student_id' => $invoice_data['student_id'] ?? '',
                'total_amount' => $invoice_data['total_amount'] ?? '',
                'fee_items' => $invoice_data['fee_items'] ?? array()
            )
        );
    }
    
    /**
     * Log SMS sent
     */
    public function log_sms_sent($message_id, $recipient, $message) {
        $user_id = get_current_user_id();
        
        $this->log_activity(
            $user_id,
            'sms_sent',
            'sms',
            $message_id,
            array(
                'recipient' => $recipient,
                'message_length' => strlen($message),
                'message_preview' => substr($message, 0, 50) . (strlen($message) > 50 ? '...' : '')
            )
        );
    }
    
    /**
     * Log bulk SMS sent
     */
    public function log_bulk_sms_sent($batch_id, $recipients, $message) {
        $user_id = get_current_user_id();
        
        $this->log_activity(
            $user_id,
            'bulk_sms_sent',
            'sms_batch',
            $batch_id,
            array(
                'recipient_count' => count($recipients),
                'message_length' => strlen($message),
                'message_preview' => substr($message, 0, 50) . (strlen($message) > 50 ? '...' : '')
            )
        );
    }
    
    /**
     * General log method for compatibility with SMS_Base
     */
    public function log($message, $level = 'info', $context = array()) {
        // Use WordPress error_log for simple logging
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            $log_message = sprintf('[%s] %s: %s', strtoupper($level), current_time('mysql'), $message);
            if (!empty($context)) {
                $log_message .= ' | Context: ' . wp_json_encode($context);
            }
            error_log($log_message);
        }
        
        // Also log to security table for error and warning levels
        if (in_array($level, array('error', 'warning', 'critical'))) {
            $this->log_security_event($level, array(
                'message' => $message,
                'context' => $context
            ));
        }
    }
    
    /**
     * Get activity logs
     */
    public function get_activity_logs($filters = array()) {
        global $wpdb;
        
        $where_clauses = array('1=1');
        $where_values = array();
        
        if (!empty($filters['user_id'])) {
            $where_clauses[] = 'user_id = %d';
            $where_values[] = intval($filters['user_id']);
        }
        
        if (!empty($filters['action'])) {
            $where_clauses[] = 'action = %s';
            $where_values[] = sanitize_text_field($filters['action']);
        }
        
        if (!empty($filters['object_type'])) {
            $where_clauses[] = 'object_type = %s';
            $where_values[] = sanitize_text_field($filters['object_type']);
        }
        
        if (!empty($filters['date_from'])) {
            $where_clauses[] = 'created_at >= %s';
            $where_values[] = sanitize_text_field($filters['date_from']);
        }
        
        if (!empty($filters['date_to'])) {
            $where_clauses[] = 'created_at <= %s';
            $where_values[] = sanitize_text_field($filters['date_to']);
        }
        
        $limit = isset($filters['limit']) ? intval($filters['limit']) : 100;
        $offset = isset($filters['offset']) ? intval($filters['offset']) : 0;
        
        $where_sql = implode(' AND ', $where_clauses);
        
        if (!empty($where_values)) {
            $query = $wpdb->prepare(
                "SELECT * FROM {$this->activity_table} WHERE {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d",
                array_merge($where_values, array($limit, $offset))
            );
        } else {
            $query = $wpdb->prepare(
                "SELECT * FROM {$this->activity_table} WHERE {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $limit,
                $offset
            );
        }
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Get security logs
     */
    public function get_security_logs($filters = array()) {
        global $wpdb;
        
        $where_clauses = array('1=1');
        $where_values = array();
        
        if (!empty($filters['event_type'])) {
            $where_clauses[] = 'event_type = %s';
            $where_values[] = sanitize_text_field($filters['event_type']);
        }
        
        if (!empty($filters['ip_address'])) {
            $where_clauses[] = 'ip_address = %s';
            $where_values[] = sanitize_text_field($filters['ip_address']);
        }
        
        if (!empty($filters['date_from'])) {
            $where_clauses[] = 'created_at >= %s';
            $where_values[] = sanitize_text_field($filters['date_from']);
        }
        
        if (!empty($filters['date_to'])) {
            $where_clauses[] = 'created_at <= %s';
            $where_values[] = sanitize_text_field($filters['date_to']);
        }
        
        $limit = isset($filters['limit']) ? intval($filters['limit']) : 100;
        $offset = isset($filters['offset']) ? intval($filters['offset']) : 0;
        
        $where_sql = implode(' AND ', $where_clauses);
        
        if (!empty($where_values)) {
            $query = $wpdb->prepare(
                "SELECT * FROM {$this->security_table} WHERE {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d",
                array_merge($where_values, array($limit, $offset))
            );
        } else {
            $query = $wpdb->prepare(
                "SELECT * FROM {$this->security_table} WHERE {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $limit,
                $offset
            );
        }
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Clean up old logs
     */
    private function cleanup_old_logs() {
        global $wpdb;
        
        $retention_days = apply_filters('sms_log_retention_days', 90);
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$retention_days} days"));
        
        // Clean activity logs
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->activity_table} WHERE created_at < %s",
            $cutoff_date
        ));
        
        // Clean security logs
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->security_table} WHERE created_at < %s",
            $cutoff_date
        ));
    }
    
    /**
     * Create log tables
     */
    public function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Activity log table
        $activity_table_sql = "CREATE TABLE {$this->activity_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            action varchar(50) NOT NULL,
            object_type varchar(50) NOT NULL,
            object_id bigint(20) unsigned NOT NULL,
            details longtext,
            ip_address varchar(45) NOT NULL,
            user_agent varchar(255) NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY action (action),
            KEY object_type (object_type),
            KEY object_id (object_id),
            KEY created_at (created_at),
            KEY ip_address (ip_address)
        ) $charset_collate;";
        
        // Security log table
        $security_table_sql = "CREATE TABLE {$this->security_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_type varchar(50) NOT NULL,
            details longtext,
            ip_address varchar(45) NOT NULL,
            user_agent varchar(255) NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY event_type (event_type),
            KEY ip_address (ip_address),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($activity_table_sql);
        dbDelta($security_table_sql);
    }
}