<?php
/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @package    School_Management_System
 * @subpackage School_Management_System/includes/core
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Fired during plugin activation.
 */
class SMS_Activator {

    /**
     * Plugin activation handler.
     *
     * Creates necessary database tables, sets up default options,
     * creates user roles and capabilities, and flushes rewrite rules.
     */
    public static function activate() {
        // Create custom database tables if needed
        self::create_custom_tables();
        
        // Set up default plugin options
        self::set_default_options();
        
        // Create custom user roles and capabilities
        self::create_user_roles();
        
        // Flush rewrite rules to ensure custom post types work
        flush_rewrite_rules();
        
        // Set activation flag
        update_option('sms_plugin_activated', true);
        update_option('sms_plugin_version', SMS_VERSION);
    }

    /**
     * Create custom database tables.
     */
    private static function create_custom_tables() {
        // Load database setup class
        require_once SMS_PLUGIN_DIR . 'includes/core/class-sms-database-setup.php';
        
        if (class_exists('SMS_Database_Setup')) {
            $db_setup = new SMS_Database_Setup();
            $db_setup->create_tables();
        }

        // Load database setup class
        require_once SMS_PLUGIN_DIR . 'includes/core/class-sms-database-setup.php';
        
        if (class_exists('SMS_Database_Setup')) {
            $db_setup = new SMS_Database_Setup();
            $db_setup->create_tables();
        }

        $charset_collate = $wpdb->get_charset_collate();

        // Activity log table
        $activity_log_table = $wpdb->prefix . 'sms_activity_log';
        $activity_log_sql = "CREATE TABLE $activity_log_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            action varchar(100) NOT NULL,
            object_type varchar(50) NOT NULL,
            object_id bigint(20) NOT NULL,
            details longtext,
            ip_address varchar(45),
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY action (action),
            KEY object_type (object_type),
            KEY timestamp (timestamp)
        ) $charset_collate;";

        // SMS queue table
        $sms_queue_table = $wpdb->prefix . 'sms_message_queue';
        $sms_queue_sql = "CREATE TABLE $sms_queue_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            recipients longtext NOT NULL,
            message text NOT NULL,
            priority varchar(20) DEFAULT 'normal',
            status varchar(20) DEFAULT 'pending',
            gateway_response longtext,
            attempts int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            sent_at datetime NULL,
            PRIMARY KEY (id),
            KEY status (status),
            KEY priority (priority),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($activity_log_sql);
        dbDelta($sms_queue_sql);
    }

    /**
     * Set default plugin options.
     */
    private static function set_default_options() {
        $default_options = array(
            'sms_school_name' => get_bloginfo('name'),
            'sms_academic_year' => date('Y') . '-' . (date('Y') + 1),
            'sms_current_term' => 'Term 1',
            'sms_admission_number_prefix' => 'ADM',
            'sms_admission_number_start' => 1000,
            'sms_default_class_capacity' => 40,
            'sms_attendance_notification_enabled' => true,
            'sms_fee_reminder_days' => 7,
            'sms_payment_gateways_enabled' => array('mpesa', 'airtel_money', 'cash'),
            'sms_africastalking_enabled' => false,
            'sms_email_notifications_enabled' => true
        );

        foreach ($default_options as $option_name => $option_value) {
            if (get_option($option_name) === false) {
                add_option($option_name, $option_value);
            }
        }
    }

    /**
     * Create custom user roles and capabilities.
     */
    private static function create_user_roles() {
        // SMS Administrator role
        add_role('sms_admin', 'School Administrator', array(
            'read' => true,
            'manage_students' => true,
            'manage_classes' => true,
            'manage_fees' => true,
            'manage_financial_reports' => true,
            'send_bulk_sms' => true,
            'manage_system_settings' => true,
            'manage_transport' => true,
            'manage_notices' => true,
            'view_all_reports' => true,
            'manage_users' => true,
            'edit_posts' => true,
            'edit_others_posts' => true,
            'publish_posts' => true,
            'manage_categories' => true
        ));

        // Teacher role
        add_role('sms_teacher', 'Teacher', array(
            'read' => true,
            'edit_assigned_classes' => true,
            'mark_attendance' => true,
            'create_lessons' => true,
            'view_student_records' => true,
            'create_academic_notices' => true,
            'view_class_reports' => true,
            'edit_posts' => true,
            'publish_posts' => true
        ));

        // Parent role
        add_role('sms_parent', 'Parent', array(
            'read' => true,
            'view_child_records' => true,
            'view_child_fees' => true,
            'make_payments' => true,
            'update_contact_info' => true,
            'view_child_attendance' => true,
            'view_notices' => true
        ));

        // Student role
        add_role('sms_student', 'Student', array(
            'read' => true,
            'view_own_records' => true,
            'view_timetable' => true,
            'view_notices' => true,
            'view_assignments' => true
        ));

        // Add capabilities to administrator
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $admin_capabilities = array(
                'manage_students', 'manage_classes', 'manage_fees',
                'manage_financial_reports', 'send_bulk_sms', 'manage_system_settings',
                'manage_transport', 'manage_notices', 'view_all_reports'
            );
            
            foreach ($admin_capabilities as $cap) {
                $admin_role->add_cap($cap);
            }
        }
    }
}