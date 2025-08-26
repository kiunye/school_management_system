<?php
/**
 * Database Setup Class
 *
 * Handles creation and management of custom database tables for the SMS plugin.
 *
 * @package School_Management_System
 * @subpackage Core
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SMS Database Setup Class
 *
 * Creates and manages custom database tables required by the plugin.
 */
class SMS_Database_Setup {

    /**
     * Database version
     */
    const DB_VERSION = '1.0.0';

    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('init', [$this, 'maybe_create_tables']);
        // Note: Activation hook should be registered in the main plugin file, not here
    }

    /**
     * Maybe create tables if version changed
     */
    public function maybe_create_tables() {
        $current_version = get_option('sms_db_version', '0.0.0');
        
        if (version_compare($current_version, self::DB_VERSION, '<')) {
            $this->create_tables();
            update_option('sms_db_version', self::DB_VERSION);
        }
    }

    /**
     * Create all required database tables
     */
    public function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // SMS Messages table
        $this->create_sms_messages_table($charset_collate);
        
        // Activity Log table
        $this->create_activity_log_table($charset_collate);
        
        // Payment Queue table
        $this->create_payment_queue_table($charset_collate);
        
        // SMS Queue table
        $this->create_sms_queue_table($charset_collate);

        // Run dbDelta to create tables
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    }

    /**
     * Create SMS messages table
     */
    private function create_sms_messages_table($charset_collate) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'sms_messages';

        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            recipient_phone varchar(20) NOT NULL,
            recipient_name varchar(100) DEFAULT NULL,
            message_content text NOT NULL,
            message_type varchar(50) DEFAULT 'general',
            status varchar(20) DEFAULT 'pending',
            gateway_response text DEFAULT NULL,
            gateway_message_id varchar(100) DEFAULT NULL,
            cost decimal(10,2) DEFAULT 0.00,
            sent_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            user_id bigint(20) DEFAULT NULL,
            batch_id varchar(50) DEFAULT NULL,
            PRIMARY KEY (id),
            KEY recipient_phone (recipient_phone),
            KEY status (status),
            KEY sent_at (sent_at),
            KEY batch_id (batch_id),
            KEY user_id (user_id)
        ) $charset_collate;";

        dbDelta($sql);
    }

    /**
     * Create activity log table
     */
    private function create_activity_log_table($charset_collate) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'sms_activity_log';

        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            action varchar(50) NOT NULL,
            object_type varchar(50) NOT NULL,
            object_id bigint(20) NOT NULL,
            details text DEFAULT NULL,
            metadata longtext DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY action (action),
            KEY object_type (object_type),
            KEY object_id (object_id),
            KEY created_at (created_at)
        ) $charset_collate;";

        dbDelta($sql);
    }

    /**
     * Create payment queue table
     */
    private function create_payment_queue_table($charset_collate) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'sms_payment_queue';

        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            invoice_id bigint(20) NOT NULL,
            student_id bigint(20) NOT NULL,
            amount decimal(10,2) NOT NULL,
            payment_method varchar(50) NOT NULL,
            phone_number varchar(20) DEFAULT NULL,
            status varchar(20) DEFAULT 'pending',
            gateway_reference varchar(100) DEFAULT NULL,
            gateway_response text DEFAULT NULL,
            callback_data longtext DEFAULT NULL,
            attempts int(11) DEFAULT 0,
            max_attempts int(11) DEFAULT 3,
            next_attempt datetime DEFAULT NULL,
            completed_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY invoice_id (invoice_id),
            KEY student_id (student_id),
            KEY status (status),
            KEY payment_method (payment_method),
            KEY next_attempt (next_attempt)
        ) $charset_collate;";

        dbDelta($sql);
    }

    /**
     * Create SMS queue table
     */
    private function create_sms_queue_table($charset_collate) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'sms_queue';

        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            recipient_phone varchar(20) NOT NULL,
            recipient_name varchar(100) DEFAULT NULL,
            message_content text NOT NULL,
            message_type varchar(50) DEFAULT 'general',
            priority int(11) DEFAULT 5,
            status varchar(20) DEFAULT 'queued',
            scheduled_at datetime DEFAULT NULL,
            attempts int(11) DEFAULT 0,
            max_attempts int(11) DEFAULT 3,
            gateway_response text DEFAULT NULL,
            sent_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            user_id bigint(20) DEFAULT NULL,
            batch_id varchar(50) DEFAULT NULL,
            PRIMARY KEY (id),
            KEY recipient_phone (recipient_phone),
            KEY status (status),
            KEY priority (priority),
            KEY scheduled_at (scheduled_at),
            KEY batch_id (batch_id)
        ) $charset_collate;";

        dbDelta($sql);
    }

    /**
     * Drop all SMS tables (for uninstall)
     */
    public static function drop_tables() {
        global $wpdb;

        $tables = [
            $wpdb->prefix . 'sms_messages',
            $wpdb->prefix . 'sms_activity_log',
            $wpdb->prefix . 'sms_payment_queue',
            $wpdb->prefix . 'sms_queue'
        ];

        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }

        // Remove database version option
        delete_option('sms_db_version');
    }

    /**
     * Get table status
     */
    public function get_table_status() {
        global $wpdb;

        $tables = [
            'sms_messages' => $wpdb->prefix . 'sms_messages',
            'sms_activity_log' => $wpdb->prefix . 'sms_activity_log',
            'sms_payment_queue' => $wpdb->prefix . 'sms_payment_queue',
            'sms_queue' => $wpdb->prefix . 'sms_queue'
        ];

        $status = [];

        foreach ($tables as $key => $table_name) {
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
            $count = 0;
            
            if ($exists) {
                $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
            }

            $status[$key] = [
                'exists' => $exists,
                'name' => $table_name,
                'count' => intval($count)
            ];
        }

        return $status;
    }

    /**
     * Repair tables if needed
     */
    public function repair_tables() {
        $this->create_tables();
        return true;
    }

    /**
     * Add sample data for testing
     */
    public function add_sample_data() {
        global $wpdb;

        // Add sample SMS messages
        $sms_table = $wpdb->prefix . 'sms_messages';
        if ($wpdb->get_var("SHOW TABLES LIKE '$sms_table'") == $sms_table) {
            $wpdb->insert($sms_table, [
                'recipient_phone' => '+254712345678',
                'recipient_name' => 'John Doe',
                'message_content' => 'Welcome to our school management system!',
                'message_type' => 'welcome',
                'status' => 'sent',
                'sent_at' => current_time('mysql'),
                'user_id' => get_current_user_id()
            ]);

            $wpdb->insert($sms_table, [
                'recipient_phone' => '+254787654321',
                'recipient_name' => 'Jane Smith',
                'message_content' => 'Your child was marked absent today.',
                'message_type' => 'attendance',
                'status' => 'sent',
                'sent_at' => current_time('mysql'),
                'user_id' => get_current_user_id()
            ]);
        }

        // Add sample activity log entries
        $activity_table = $wpdb->prefix . 'sms_activity_log';
        if ($wpdb->get_var("SHOW TABLES LIKE '$activity_table'") == $activity_table) {
            $wpdb->insert($activity_table, [
                'user_id' => get_current_user_id(),
                'action' => 'dashboard_accessed',
                'object_type' => 'dashboard',
                'object_id' => 0,
                'details' => 'Administrator accessed the dashboard',
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);

            $wpdb->insert($activity_table, [
                'user_id' => get_current_user_id(),
                'action' => 'system_initialized',
                'object_type' => 'system',
                'object_id' => 0,
                'details' => 'School Management System initialized',
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
        }
    }
}