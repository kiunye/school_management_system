<?php
/**
 * Security Management System
 *
 * Handles input validation, sanitization, authentication, and security features
 *
 * @package School_Management_System
 * @subpackage Core
 */

if (!defined('ABSPATH')) {
    exit;
}

class SMS_Security {
    
    /**
     * Instance of this class
     */
    private static $instance = null;
    
    /**
     * Encryption key for sensitive data
     */
    private $encryption_key;
    
    /**
     * Security settings
     */
    private $security_settings;
    
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
        $this->encryption_key = $this->get_encryption_key();
        $this->security_settings = get_option('sms_security_settings', $this->get_default_security_settings());
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('wp_login', array($this, 'log_user_login'), 10, 2);
        add_action('wp_logout', array($this, 'log_user_logout'));
        add_action('wp_login_failed', array($this, 'log_failed_login'));
        add_filter('authenticate', array($this, 'check_login_attempts'), 30, 3);
    }
    
    /**
     * Sanitize input data based on type
     */
    public function sanitize_input($data, $type = 'text') {
        if (is_array($data)) {
            return array_map(function($item) use ($type) {
                return $this->sanitize_input($item, $type);
            }, $data);
        }
        
        switch ($type) {
            case 'email':
                return sanitize_email($data);
            case 'number':
                return intval($data);
            case 'float':
                return floatval($data);
            case 'phone':
                return $this->sanitize_phone_number($data);
            case 'admission_number':
                return $this->sanitize_admission_number($data);
            case 'payment_reference':
                return $this->sanitize_payment_reference($data);
            case 'url':
                return esc_url_raw($data);
            case 'textarea':
                return sanitize_textarea_field($data);
            case 'html':
                return wp_kses_post($data);
            case 'text':
            default:
                return sanitize_text_field($data);
        }
    }
    
    /**
     * Validate input data
     */
    public function validate_input($data, $rules) {
        $errors = array();
        
        foreach ($rules as $field => $rule_set) {
            $value = isset($data[$field]) ? $data[$field] : null;
            $field_errors = $this->validate_field($field, $value, $rule_set);
            
            if (!empty($field_errors)) {
                $errors[$field] = $field_errors;
            }
        }
        
        return $errors;
    }
    
    /**
     * Validate individual field
     */
    private function validate_field($field, $value, $rules) {
        $errors = array();
        $rules_array = explode('|', $rules);
        
        foreach ($rules_array as $rule) {
            $rule_parts = explode(':', $rule);
            $rule_name = $rule_parts[0];
            $rule_param = isset($rule_parts[1]) ? $rule_parts[1] : null;
            
            switch ($rule_name) {
                case 'required':
                    if (empty($value)) {
                        $errors[] = sprintf(__('%s is required.', 'school-management-system'), ucfirst(str_replace('_', ' ', $field)));
                    }
                    break;
                    
                case 'email':
                    if (!empty($value) && !is_email($value)) {
                        $errors[] = sprintf(__('%s must be a valid email address.', 'school-management-system'), ucfirst(str_replace('_', ' ', $field)));
                    }
                    break;
                    
                case 'numeric':
                    if (!empty($value) && !is_numeric($value)) {
                        $errors[] = sprintf(__('%s must be a number.', 'school-management-system'), ucfirst(str_replace('_', ' ', $field)));
                    }
                    break;
                    
                case 'min':
                    if (!empty($value) && strlen($value) < intval($rule_param)) {
                        $errors[] = sprintf(__('%s must be at least %d characters.', 'school-management-system'), ucfirst(str_replace('_', ' ', $field)), intval($rule_param));
                    }
                    break;
                    
                case 'max':
                    if (!empty($value) && strlen($value) > intval($rule_param)) {
                        $errors[] = sprintf(__('%s must not exceed %d characters.', 'school-management-system'), ucfirst(str_replace('_', ' ', $field)), intval($rule_param));
                    }
                    break;
                    
                case 'phone':
                    if (!empty($value) && !$this->validate_phone_number($value)) {
                        $errors[] = sprintf(__('%s must be a valid phone number.', 'school-management-system'), ucfirst(str_replace('_', ' ', $field)));
                    }
                    break;
                    
                case 'unique':
                    if (!empty($value) && !$this->is_unique_value($field, $value, $rule_param)) {
                        $errors[] = sprintf(__('%s already exists.', 'school-management-system'), ucfirst(str_replace('_', ' ', $field)));
                    }
                    break;
            }
        }
        
        return $errors;
    }
    
    /**
     * Verify nonce
     */
    public function verify_nonce($action, $nonce) {
        return wp_verify_nonce($nonce, $action);
    }
    
    /**
     * Check user capability
     */
    public function check_user_capability($capability) {
        return current_user_can($capability);
    }
    
    /**
     * Encrypt sensitive data
     */
    public function encrypt_data($data) {
        if (empty($data)) {
            return '';
        }
        
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $this->encryption_key, 0, $iv);
        
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Decrypt sensitive data
     */
    public function decrypt_data($encrypted_data) {
        if (empty($encrypted_data)) {
            return '';
        }
        
        $data = base64_decode($encrypted_data);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        
        return openssl_decrypt($encrypted, 'AES-256-CBC', $this->encryption_key, 0, $iv);
    }
    
    /**
     * Get or generate encryption key
     */
    private function get_encryption_key() {
        $key = get_option('sms_encryption_key');
        
        if (empty($key)) {
            // Use fallback if wp_generate_password is not available
            if (function_exists('wp_generate_password')) {
                $key = wp_generate_password(32, false);
            } else {
                // Fallback method for generating secure key
                $key = $this->generate_secure_key(32);
            }
            update_option('sms_encryption_key', $key);
        }
        
        return $key;
    }
    
    /**
     * Generate secure key as fallback when WordPress functions are not available
     */
    private function generate_secure_key($length = 32) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!@#$%^&*()';
        $key = '';
        $max = strlen($characters) - 1;
        
        for ($i = 0; $i < $length; $i++) {
            if (function_exists('random_int')) {
                $key .= $characters[random_int(0, $max)];
            } else {
                $key .= $characters[mt_rand(0, $max)];
            }
        }
        
        return $key;
    }
    
    /**
     * Sanitize phone number
     */
    private function sanitize_phone_number($phone) {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // Convert to standard format
        if (strpos($phone, '+254') === 0) {
            return $phone;
        } elseif (strpos($phone, '254') === 0) {
            return '+' . $phone;
        } elseif (strpos($phone, '0') === 0) {
            return '+254' . substr($phone, 1);
        }
        
        return $phone;
    }
    
    /**
     * Validate phone number format
     */
    private function validate_phone_number($phone) {
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
     * Sanitize admission number
     */
    private function sanitize_admission_number($admission_number) {
        return strtoupper(preg_replace('/[^A-Z0-9]/', '', $admission_number));
    }
    
    /**
     * Sanitize payment reference
     */
    private function sanitize_payment_reference($reference) {
        return preg_replace('/[^A-Za-z0-9\-_]/', '', $reference);
    }
    
    /**
     * Check if value is unique
     */
    private function is_unique_value($field, $value, $post_type) {
        global $wpdb;
        
        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} pm 
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
             WHERE pm.meta_key = %s AND pm.meta_value = %s AND p.post_type = %s AND p.post_status != 'trash'",
            $field,
            $value,
            $post_type
        );
        
        return intval($wpdb->get_var($query)) === 0;
    }
    
    /**
     * Log user login
     */
    public function log_user_login($user_login, $user) {
        SMS_Logger::get_instance()->log_activity(
            $user->ID,
            'login',
            'user',
            $user->ID,
            array(
                'user_login' => $user_login,
                'ip_address' => $this->get_client_ip()
            )
        );
    }
    
    /**
     * Log user logout
     */
    public function log_user_logout() {
        $user_id = get_current_user_id();
        if ($user_id) {
            SMS_Logger::get_instance()->log_activity(
                $user_id,
                'logout',
                'user',
                $user_id,
                array(
                    'ip_address' => $this->get_client_ip()
                )
            );
        }
    }
    
    /**
     * Log failed login attempt
     */
    public function log_failed_login($username) {
        SMS_Logger::get_instance()->log_security_event(
            'failed_login',
            array(
                'username' => $username,
                'ip_address' => $this->get_client_ip(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
            )
        );
        
        $this->increment_login_attempts($this->get_client_ip());
    }
    
    /**
     * Check login attempts and block if necessary
     */
    public function check_login_attempts($user, $username, $password) {
        $ip = $this->get_client_ip();
        $attempts = $this->get_login_attempts($ip);
        
        if ($attempts >= $this->security_settings['max_login_attempts']) {
            return new WP_Error('too_many_attempts', __('Too many failed login attempts. Please try again later.', 'school-management-system'));
        }
        
        return $user;
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
     * Increment login attempts for IP
     */
    private function increment_login_attempts($ip) {
        $attempts = get_transient('sms_login_attempts_' . md5($ip));
        $attempts = $attempts ? $attempts + 1 : 1;
        
        set_transient('sms_login_attempts_' . md5($ip), $attempts, $this->security_settings['lockout_duration']);
    }
    
    /**
     * Get login attempts for IP
     */
    private function get_login_attempts($ip) {
        return get_transient('sms_login_attempts_' . md5($ip)) ?: 0;
    }
    
    /**
     * Get default security settings
     */
    private function get_default_security_settings() {
        return array(
            'max_login_attempts' => 5,
            'lockout_duration' => 1800, // 30 minutes
            'session_timeout' => 3600,  // 1 hour
            'password_min_length' => 8,
            'require_strong_passwords' => true,
            'enable_two_factor' => false
        );
    }
}