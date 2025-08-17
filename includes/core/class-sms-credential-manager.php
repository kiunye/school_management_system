<?php
/**
 * Credential Management System
 *
 * Handles secure storage and retrieval of payment gateway credentials and other sensitive data
 *
 * @package School_Management_System
 * @subpackage Core
 */

if (!defined('ABSPATH')) {
    exit;
}

class SMS_Credential_Manager {
    
    /**
     * Instance of this class
     */
    private static $instance = null;
    
    /**
     * Encryption key for credentials
     */
    private $credential_key;
    
    /**
     * Credential option prefix
     */
    private $option_prefix = 'sms_encrypted_';
    
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
        $this->credential_key = $this->get_credential_key();
    }
    
    /**
     * Store encrypted credential
     */
    public function store_credential($key, $value) {
        if (empty($value)) {
            return delete_option($this->option_prefix . $key);
        }
        
        $encrypted_value = $this->encrypt_credential($value);
        $stored = update_option($this->option_prefix . $key, $encrypted_value);
        
        if ($stored) {
            // Log credential storage (without the actual value)
            SMS_Logger::get_instance()->log_activity(
                get_current_user_id(),
                'credential_stored',
                'credential',
                0,
                array(
                    'credential_key' => $key,
                    'value_length' => strlen($value)
                )
            );
        }
        
        return $stored;
    }
    
    /**
     * Retrieve and decrypt credential
     */
    public function get_credential($key) {
        $encrypted_value = get_option($this->option_prefix . $key);
        
        if (empty($encrypted_value)) {
            return '';
        }
        
        return $this->decrypt_credential($encrypted_value);
    }
    
    /**
     * Delete credential
     */
    public function delete_credential($key) {
        $deleted = delete_option($this->option_prefix . $key);
        
        if ($deleted) {
            SMS_Logger::get_instance()->log_activity(
                get_current_user_id(),
                'credential_deleted',
                'credential',
                0,
                array(
                    'credential_key' => $key
                )
            );
        }
        
        return $deleted;
    }
    
    /**
     * Store multiple credentials at once
     */
    public function store_credentials($credentials) {
        $results = array();
        
        foreach ($credentials as $key => $value) {
            $results[$key] = $this->store_credential($key, $value);
        }
        
        return $results;
    }
    
    /**
     * Get multiple credentials at once
     */
    public function get_credentials($keys) {
        $credentials = array();
        
        foreach ($keys as $key) {
            $credentials[$key] = $this->get_credential($key);
        }
        
        return $credentials;
    }
    
    /**
     * Store M-Pesa credentials
     */
    public function store_mpesa_credentials($credentials) {
        $mpesa_keys = array(
            'consumer_key',
            'consumer_secret',
            'passkey',
            'shortcode',
            'initiator_name',
            'security_credential'
        );
        
        $results = array();
        foreach ($mpesa_keys as $key) {
            if (isset($credentials[$key])) {
                $results[$key] = $this->store_credential('mpesa_' . $key, $credentials[$key]);
            }
        }
        
        return $results;
    }
    
    /**
     * Get M-Pesa credentials
     */
    public function get_mpesa_credentials() {
        $mpesa_keys = array(
            'consumer_key',
            'consumer_secret',
            'passkey',
            'shortcode',
            'initiator_name',
            'security_credential'
        );
        
        $credentials = array();
        foreach ($mpesa_keys as $key) {
            $credentials[$key] = $this->get_credential('mpesa_' . $key);
        }
        
        return $credentials;
    }
    
    /**
     * Store Airtel Money credentials
     */
    public function store_airtel_credentials($credentials) {
        $airtel_keys = array(
            'client_id',
            'client_secret',
            'merchant_id',
            'api_key'
        );
        
        $results = array();
        foreach ($airtel_keys as $key) {
            if (isset($credentials[$key])) {
                $results[$key] = $this->store_credential('airtel_' . $key, $credentials[$key]);
            }
        }
        
        return $results;
    }
    
    /**
     * Get Airtel Money credentials
     */
    public function get_airtel_credentials() {
        $airtel_keys = array(
            'client_id',
            'client_secret',
            'merchant_id',
            'api_key'
        );
        
        $credentials = array();
        foreach ($airtel_keys as $key) {
            $credentials[$key] = $this->get_credential('airtel_' . $key);
        }
        
        return $credentials;
    }
    
    /**
     * Store SMS API credentials
     */
    public function store_sms_credentials($credentials) {
        $sms_keys = array(
            'username',
            'api_key',
            'sender_id'
        );
        
        $results = array();
        foreach ($sms_keys as $key) {
            if (isset($credentials[$key])) {
                $results[$key] = $this->store_credential('sms_' . $key, $credentials[$key]);
            }
        }
        
        return $results;
    }
    
    /**
     * Get SMS API credentials
     */
    public function get_sms_credentials() {
        $sms_keys = array(
            'username',
            'api_key',
            'sender_id'
        );
        
        $credentials = array();
        foreach ($sms_keys as $key) {
            $credentials[$key] = $this->get_credential('sms_' . $key);
        }
        
        return $credentials;
    }
    
    /**
     * Encrypt credential value
     */
    private function encrypt_credential($value) {
        if (empty($value)) {
            return '';
        }
        
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($value, 'AES-256-CBC', $this->credential_key, 0, $iv);
        
        if ($encrypted === false) {
            return '';
        }
        
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Decrypt credential value
     */
    private function decrypt_credential($encrypted_value) {
        if (empty($encrypted_value)) {
            return '';
        }
        
        $data = base64_decode($encrypted_value);
        if ($data === false) {
            return '';
        }
        
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        
        $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $this->credential_key, 0, $iv);
        
        return $decrypted !== false ? $decrypted : '';
    }
    
    /**
     * Get or generate credential encryption key
     */
    private function get_credential_key() {
        $key = get_option('sms_credential_key');
        
        if (empty($key)) {
            $key = wp_generate_password(32, false);
            update_option('sms_credential_key', $key);
            
            // Log key generation
            SMS_Logger::get_instance()->log_security_event(
                'credential_key_generated',
                array(
                    'user_id' => get_current_user_id(),
                    'ip_address' => $this->get_client_ip()
                )
            );
        }
        
        return $key;
    }
    
    /**
     * Rotate encryption key (re-encrypt all credentials with new key)
     */
    public function rotate_encryption_key() {
        global $wpdb;
        
        // Get all encrypted options
        $encrypted_options = $wpdb->get_results($wpdb->prepare(
            "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
            $this->option_prefix . '%'
        ));
        
        if (empty($encrypted_options)) {
            return true;
        }
        
        // Generate new key
        $new_key = wp_generate_password(32, false);
        $old_key = $this->credential_key;
        
        $re_encrypted = array();
        
        // Decrypt with old key and re-encrypt with new key
        foreach ($encrypted_options as $option) {
            $decrypted_value = $this->decrypt_with_key($option->option_value, $old_key);
            if ($decrypted_value !== false) {
                $re_encrypted_value = $this->encrypt_with_key($decrypted_value, $new_key);
                $re_encrypted[$option->option_name] = $re_encrypted_value;
            }
        }
        
        // Update all options with re-encrypted values
        foreach ($re_encrypted as $option_name => $encrypted_value) {
            update_option($option_name, $encrypted_value);
        }
        
        // Update the key
        update_option('sms_credential_key', $new_key);
        $this->credential_key = $new_key;
        
        // Log key rotation
        SMS_Logger::get_instance()->log_security_event(
            'credential_key_rotated',
            array(
                'user_id' => get_current_user_id(),
                'credentials_re_encrypted' => count($re_encrypted),
                'ip_address' => $this->get_client_ip()
            )
        );
        
        return true;
    }
    
    /**
     * Decrypt with specific key
     */
    private function decrypt_with_key($encrypted_value, $key) {
        if (empty($encrypted_value)) {
            return '';
        }
        
        $data = base64_decode($encrypted_value);
        if ($data === false) {
            return false;
        }
        
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }
    
    /**
     * Encrypt with specific key
     */
    private function encrypt_with_key($value, $key) {
        if (empty($value)) {
            return '';
        }
        
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($value, 'AES-256-CBC', $key, 0, $iv);
        
        if ($encrypted === false) {
            return '';
        }
        
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Validate credential integrity
     */
    public function validate_credentials() {
        global $wpdb;
        
        $encrypted_options = $wpdb->get_results($wpdb->prepare(
            "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
            $this->option_prefix . '%'
        ));
        
        $validation_results = array();
        
        foreach ($encrypted_options as $option) {
            $credential_key = str_replace($this->option_prefix, '', $option->option_name);
            $decrypted_value = $this->decrypt_credential($option->option_value);
            
            $validation_results[$credential_key] = array(
                'exists' => !empty($option->option_value),
                'decryptable' => $decrypted_value !== false && $decrypted_value !== '',
                'length' => strlen($decrypted_value)
            );
        }
        
        return $validation_results;
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
     * Export encrypted credentials (for backup)
     */
    public function export_credentials() {
        global $wpdb;
        
        if (!current_user_can('manage_options')) {
            return false;
        }
        
        $encrypted_options = $wpdb->get_results($wpdb->prepare(
            "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
            $this->option_prefix . '%'
        ));
        
        $export_data = array();
        foreach ($encrypted_options as $option) {
            $export_data[$option->option_name] = $option->option_value;
        }
        
        // Log export
        SMS_Logger::get_instance()->log_activity(
            get_current_user_id(),
            'credentials_exported',
            'system',
            0,
            array(
                'credential_count' => count($export_data)
            )
        );
        
        return $export_data;
    }
    
    /**
     * Import encrypted credentials (from backup)
     */
    public function import_credentials($credential_data) {
        if (!current_user_can('manage_options')) {
            return false;
        }
        
        if (!is_array($credential_data)) {
            return false;
        }
        
        $imported_count = 0;
        
        foreach ($credential_data as $option_name => $option_value) {
            // Validate option name format
            if (strpos($option_name, $this->option_prefix) === 0) {
                update_option($option_name, $option_value);
                $imported_count++;
            }
        }
        
        // Log import
        SMS_Logger::get_instance()->log_activity(
            get_current_user_id(),
            'credentials_imported',
            'system',
            0,
            array(
                'credential_count' => $imported_count
            )
        );
        
        return $imported_count;
    }
}