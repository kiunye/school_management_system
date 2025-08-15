<?php
/**
 * Security management for the plugin.
 *
 * Handles input sanitization, nonce verification, and capability checking.
 *
 * @package    School_Management_System
 * @subpackage School_Management_System/includes/core
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Security management class.
 */
class SMS_Security {

    /**
     * Sanitize input data based on type.
     */
    public function sanitize_input($data, $type = 'text') {
        switch ($type) {
            case 'email':
                return sanitize_email($data);
            
            case 'number':
            case 'int':
                return intval($data);
            
            case 'float':
                return floatval($data);
            
            case 'url':
                return esc_url_raw($data);
            
            case 'textarea':
                return sanitize_textarea_field($data);
            
            case 'html':
                return wp_kses_post($data);
            
            case 'phone':
                return $this->sanitize_phone($data);
            
            case 'admission_number':
                return $this->sanitize_admission_number($data);
            
            case 'array':
                return $this->sanitize_array($data);
            
            case 'json':
                return $this->sanitize_json($data);
            
            case 'text':
            default:
                return sanitize_text_field($data);
        }
    }

    /**
     * Sanitize phone number.
     */
    private function sanitize_phone($phone) {
        // Remove all non-numeric characters except +
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // Validate format
        $patterns = array(
            '/^\+254[17]\d{8}$/',
            '/^254[17]\d{8}$/',
            '/^0[17]\d{8}$/'
        );
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $phone)) {
                return $phone;
            }
        }
        
        return '';
    }

    /**
     * Sanitize admission number.
     */
    private function sanitize_admission_number($admission_number) {
        // Allow alphanumeric characters, hyphens, and underscores
        return preg_replace('/[^a-zA-Z0-9\-_]/', '', $admission_number);
    }

    /**
     * Sanitize array data.
     */
    private function sanitize_array($data) {
        if (!is_array($data)) {
            return array();
        }
        
        $sanitized = array();
        foreach ($data as $key => $value) {
            $sanitized_key = sanitize_key($key);
            if (is_array($value)) {
                $sanitized[$sanitized_key] = $this->sanitize_array($value);
            } else {
                $sanitized[$sanitized_key] = sanitize_text_field($value);
            }
        }
        
        return $sanitized;
    }

    /**
     * Sanitize JSON data.
     */
    private function sanitize_json($data) {
        if (is_string($data)) {
            $decoded = json_decode($data, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return wp_json_encode($this->sanitize_array($decoded));
            }
        }
        
        return '';
    }

    /**
     * Verify nonce.
     */
    public function verify_nonce($action, $nonce) {
        return wp_verify_nonce($nonce, $action);
    }

    /**
     * Check user capability.
     */
    public function check_user_capability($capability) {
        return current_user_can($capability);
    }

    /**
     * Check if user can manage students.
     */
    public function can_manage_students() {
        return current_user_can('manage_students') || current_user_can('administrator');
    }

    /**
     * Check if user can manage classes.
     */
    public function can_manage_classes() {
        return current_user_can('manage_classes') || current_user_can('administrator');
    }

    /**
     * Check if user can manage fees.
     */
    public function can_manage_fees() {
        return current_user_can('manage_fees') || current_user_can('administrator');
    }

    /**
     * Check if user can view student records.
     */
    public function can_view_student_records($student_id = null) {
        if (current_user_can('manage_students') || current_user_can('administrator')) {
            return true;
        }
        
        if (current_user_can('view_student_records')) {
            return true;
        }
        
        // Parents can only view their own children's records
        if (current_user_can('view_child_records') && $student_id) {
            return $this->is_parent_of_student($student_id);
        }
        
        return false;
    }

    /**
     * Check if current user is parent of specific student.
     */
    private function is_parent_of_student($student_id) {
        $current_user_id = get_current_user_id();
        $parent_ids = get_post_meta($student_id, 'parent_user_ids', true);
        
        if (is_array($parent_ids)) {
            return in_array($current_user_id, $parent_ids);
        }
        
        return false;
    }

    /**
     * Encrypt sensitive data.
     */
    public function encrypt_data($data) {
        if (!function_exists('openssl_encrypt')) {
            return base64_encode($data); // Fallback to base64
        }
        
        $key = $this->get_encryption_key();
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
        
        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt sensitive data.
     */
    public function decrypt_data($encrypted_data) {
        if (!function_exists('openssl_decrypt')) {
            return base64_decode($encrypted_data); // Fallback from base64
        }
        
        $data = base64_decode($encrypted_data);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        $key = $this->get_encryption_key();
        
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }

    /**
     * Get encryption key.
     */
    private function get_encryption_key() {
        $key = get_option('sms_encryption_key');
        
        if (!$key) {
            $key = wp_generate_password(32, false);
            update_option('sms_encryption_key', $key);
        }
        
        return $key;
    }

    /**
     * Log security events.
     */
    public function log_security_event($event, $details = array()) {
        $log_data = array(
            'event' => $event,
            'user_id' => get_current_user_id(),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'timestamp' => current_time('mysql'),
            'details' => $details
        );
        
        // Log to database or file
        error_log('SMS Security Event: ' . wp_json_encode($log_data));
        
        // Store in database for audit trail
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'sms_activity_log',
            array(
                'user_id' => $log_data['user_id'],
                'action' => 'security_event',
                'object_type' => 'security',
                'object_id' => 0,
                'details' => wp_json_encode($log_data),
                'ip_address' => $log_data['ip_address'],
                'timestamp' => $log_data['timestamp']
            )
        );
    }
}