<?php
/**
 * Base class for all SMS plugin classes.
 *
 * Provides common functionality and utilities for all plugin classes.
 *
 * @package    School_Management_System
 * @subpackage School_Management_System/includes/core
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Base class for all SMS plugin classes.
 */
abstract class SMS_Base {

    /**
     * Plugin version.
     */
    protected $version;

    /**
     * Plugin name.
     */
    protected $plugin_name;

    /**
     * Logger instance.
     */
    protected $logger;

    /**
     * Security instance.
     */
    protected $security;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->version = SMS_VERSION;
        $this->plugin_name = 'school-management-system';
        $this->logger = new SMS_Logger();
        $this->security = new SMS_Security();
    }

    /**
     * Get plugin version.
     */
    public function get_version() {
        return $this->version;
    }

    /**
     * Get plugin name.
     */
    public function get_plugin_name() {
        return $this->plugin_name;
    }

    /**
     * Sanitize input data.
     */
    protected function sanitize_input($data, $type = 'text') {
        return $this->security->sanitize_input($data, $type);
    }

    /**
     * Validate nonce.
     */
    protected function verify_nonce($action, $nonce) {
        return $this->security->verify_nonce($action, $nonce);
    }

    /**
     * Check user capability.
     */
    protected function check_capability($capability) {
        return $this->security->check_user_capability($capability);
    }

    /**
     * Log activity.
     */
    protected function log($message, $level = 'info', $context = array()) {
        $this->logger->log($message, $level, $context);
    }

    /**
     * Get current user ID.
     */
    protected function get_current_user_id() {
        return get_current_user_id();
    }

    /**
     * Get current timestamp.
     */
    protected function get_current_timestamp() {
        return current_time('mysql');
    }

    /**
     * Format currency amount.
     */
    protected function format_currency($amount, $currency = 'KES') {
        return $currency . ' ' . number_format($amount, 2);
    }

    /**
     * Generate unique ID.
     */
    protected function generate_unique_id($prefix = '') {
        return $prefix . uniqid() . '_' . wp_rand(1000, 9999);
    }

    /**
     * Validate email address.
     */
    protected function is_valid_email($email) {
        return is_email($email);
    }

    /**
     * Validate phone number (Kenyan format).
     */
    protected function is_valid_phone($phone) {
        $patterns = array(
            '/^\+254[17]\d{8}$/',  // +254 format
            '/^254[17]\d{8}$/',    // 254 format
            '/^0[17]\d{8}$/'       // 0 format
        );
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $phone)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Format phone number to standard format.
     */
    protected function format_phone($phone) {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Convert to +254 format
        if (strlen($phone) === 10 && substr($phone, 0, 1) === '0') {
            return '+254' . substr($phone, 1);
        } elseif (strlen($phone) === 12 && substr($phone, 0, 3) === '254') {
            return '+' . $phone;
        } elseif (strlen($phone) === 13 && substr($phone, 0, 4) === '+254') {
            return $phone;
        }
        
        return false;
    }

    /**
     * Get option with default value.
     */
    protected function get_option($option_name, $default = false) {
        return get_option($option_name, $default);
    }

    /**
     * Update option.
     */
    protected function update_option($option_name, $option_value) {
        return update_option($option_name, $option_value);
    }

    /**
     * Handle errors consistently.
     */
    protected function handle_error($code, $message, $data = array()) {
        $this->log("Error: {$code} - {$message}", 'error', $data);
        return new WP_Error($code, $message, $data);
    }

    /**
     * Handle success responses consistently.
     */
    protected function handle_success($data = array(), $message = '') {
        if (!empty($message)) {
            $this->log("Success: {$message}", 'info', $data);
        }
        
        return array(
            'success' => true,
            'data' => $data,
            'message' => $message
        );
    }
}