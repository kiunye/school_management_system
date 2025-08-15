<?php
/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @package    School_Management_System
 * @subpackage School_Management_System/includes/core
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Fired during plugin deactivation.
 */
class SMS_Deactivator {

    /**
     * Plugin deactivation handler.
     *
     * Cleans up temporary data, clears scheduled events,
     * and performs necessary cleanup operations.
     */
    public static function deactivate() {
        // Clear scheduled events
        self::clear_scheduled_events();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Clear any cached data
        self::clear_cache();
        
        // Set deactivation flag
        update_option('sms_plugin_deactivated', true);
        update_option('sms_plugin_deactivated_time', current_time('mysql'));
    }

    /**
     * Clear all scheduled events created by the plugin.
     */
    private static function clear_scheduled_events() {
        // Clear SMS queue processing
        wp_clear_scheduled_hook('sms_process_message_queue');
        
        // Clear fee reminder notifications
        wp_clear_scheduled_hook('sms_send_fee_reminders');
        
        // Clear attendance notifications
        wp_clear_scheduled_hook('sms_send_attendance_notifications');
        
        // Clear daily cleanup tasks
        wp_clear_scheduled_hook('sms_daily_cleanup');
        
        // Clear backup tasks
        wp_clear_scheduled_hook('sms_daily_backup');
    }

    /**
     * Clear plugin-related cached data.
     */
    private static function clear_cache() {
        // Clear object cache for plugin data
        wp_cache_delete('sms_active_students', 'sms_cache');
        wp_cache_delete('sms_active_classes', 'sms_cache');
        wp_cache_delete('sms_fee_structures', 'sms_cache');
        wp_cache_delete('sms_payment_gateways', 'sms_cache');
        
        // Clear transients
        delete_transient('sms_system_stats');
        delete_transient('sms_financial_summary');
        delete_transient('sms_attendance_summary');
        
        // Clear any plugin-specific cache directories
        self::clear_cache_directories();
    }

    /**
     * Clear plugin cache directories.
     */
    private static function clear_cache_directories() {
        $upload_dir = wp_upload_dir();
        $cache_dir = $upload_dir['basedir'] . '/sms-cache/';
        
        if (is_dir($cache_dir)) {
            $files = glob($cache_dir . '*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }
}