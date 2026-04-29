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
        require_once SMS_PLUGIN_DIR . 'includes/user-roles/class-sms-user-roles.php';

        if (class_exists('SMS_User_Roles')) {
            $roles_manager = new SMS_User_Roles();
            $roles_manager->update_user_roles();
        }
    }
}