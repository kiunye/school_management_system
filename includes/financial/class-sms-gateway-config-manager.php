<?php
/**
 * Payment Gateway Configuration Manager
 *
 * Manages payment gateway configurations and settings
 *
 * @package SchoolManagementSystem
 * @subpackage Financial
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Gateway Configuration Manager Class
 */
class SMS_Gateway_Config_Manager {
    
    /**
     * Configuration option key
     *
     * @var string
     */
    private $config_key = 'sms_payment_gateway_configs';
    
    /**
     * Default gateway option key
     *
     * @var string
     */
    private $default_gateway_key = 'sms_default_payment_gateway';
    
    /**
     * Fallback order option key
     *
     * @var string
     */
    private $fallback_order_key = 'sms_payment_gateway_fallback_order';
    
    /**
     * Encryption key for sensitive data
     *
     * @var string
     */
    private $encryption_key;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->encryption_key = $this->get_encryption_key();
        
        // Add hooks for admin interface
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
    }
    
    /**
     * Get encryption key for sensitive data
     *
     * @return string
     */
    private function get_encryption_key() {
        $key = get_option('sms_gateway_encryption_key');
        
        if (!$key) {
            $key = wp_generate_password(32, false);
            update_option('sms_gateway_encryption_key', $key);
        }
        
        return $key;
    }
    
    /**
     * Get all gateway configurations
     *
     * @return array
     */
    public function get_all_configs() {
        $configs = get_option($this->config_key, array());
        
        // Decrypt sensitive data
        foreach ($configs as $gateway_id => &$config) {
            $config = $this->decrypt_config($config);
        }
        
        return $configs;
    }
    
    /**
     * Get configuration for specific gateway
     *
     * @param string $gateway_id Gateway identifier
     * @return array|null
     */
    public function get_config($gateway_id) {
        $configs = $this->get_all_configs();
        return isset($configs[$gateway_id]) ? $configs[$gateway_id] : null;
    }
    
    /**
     * Save gateway configuration
     *
     * @param string $gateway_id Gateway identifier
     * @param array $config Configuration data
     * @return bool
     */
    public function save_config($gateway_id, $config) {
        $configs = get_option($this->config_key, array());
        
        // Encrypt sensitive data before saving
        $encrypted_config = $this->encrypt_config($config);
        $configs[$gateway_id] = $encrypted_config;
        
        $result = update_option($this->config_key, $configs);
        
        if ($result) {
            do_action('sms_gateway_config_saved', $gateway_id, $config);
        }
        
        return $result;
    }
    
    /**
     * Delete gateway configuration
     *
     * @param string $gateway_id Gateway identifier
     * @return bool
     */
    public function delete_config($gateway_id) {
        $configs = get_option($this->config_key, array());
        
        if (isset($configs[$gateway_id])) {
            unset($configs[$gateway_id]);
            $result = update_option($this->config_key, $configs);
            
            if ($result) {
                do_action('sms_gateway_config_deleted', $gateway_id);
            }
            
            return $result;
        }
        
        return false;
    }
    
    /**
     * Get default gateway
     *
     * @return string
     */
    public function get_default_gateway() {
        return get_option($this->default_gateway_key, 'mpesa');
    }
    
    /**
     * Set default gateway
     *
     * @param string $gateway_id Gateway identifier
     * @return bool
     */
    public function set_default_gateway($gateway_id) {
        return update_option($this->default_gateway_key, $gateway_id);
    }
    
    /**
     * Get fallback order
     *
     * @return array
     */
    public function get_fallback_order() {
        return get_option($this->fallback_order_key, array('mpesa', 'airtel_money', 'cash'));
    }
    
    /**
     * Set fallback order
     *
     * @param array $order Gateway IDs in fallback order
     * @return bool
     */
    public function set_fallback_order($order) {
        return update_option($this->fallback_order_key, $order);
    }
    
    /**
     * Encrypt sensitive configuration data
     *
     * @param array $config Configuration data
     * @return array Encrypted configuration
     */
    private function encrypt_config($config) {
        $sensitive_fields = array(
            'api_key',
            'secret_key',
            'consumer_key',
            'consumer_secret',
            'client_secret',
            'passkey',
            'private_key'
        );
        
        $encrypted_config = $config;
        
        foreach ($sensitive_fields as $field) {
            if (isset($config[$field]) && !empty($config[$field])) {
                $encrypted_config[$field] = $this->encrypt_value($config[$field]);
            }
        }
        
        return $encrypted_config;
    }
    
    /**
     * Decrypt sensitive configuration data
     *
     * @param array $config Encrypted configuration data
     * @return array Decrypted configuration
     */
    private function decrypt_config($config) {
        $sensitive_fields = array(
            'api_key',
            'secret_key',
            'consumer_key',
            'consumer_secret',
            'client_secret',
            'passkey',
            'private_key'
        );
        
        $decrypted_config = $config;
        
        foreach ($sensitive_fields as $field) {
            if (isset($config[$field]) && !empty($config[$field])) {
                $decrypted_value = $this->decrypt_value($config[$field]);
                if ($decrypted_value !== false) {
                    $decrypted_config[$field] = $decrypted_value;
                }
            }
        }
        
        return $decrypted_config;
    }
    
    /**
     * Encrypt a single value
     *
     * @param string $value Value to encrypt
     * @return string Encrypted value
     */
    private function encrypt_value($value) {
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($value, 'AES-256-CBC', $this->encryption_key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Decrypt a single value
     *
     * @param string $encrypted_value Encrypted value
     * @return string|false Decrypted value or false on failure
     */
    private function decrypt_value($encrypted_value) {
        $data = base64_decode($encrypted_value);
        if ($data === false) {
            return false;
        }
        
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        
        return openssl_decrypt($encrypted, 'AES-256-CBC', $this->encryption_key, 0, $iv);
    }
    
    /**
     * Get default configuration template for gateway
     *
     * @param string $gateway_id Gateway identifier
     * @return array
     */
    public function get_default_config_template($gateway_id) {
        $templates = array(
            'mpesa' => array(
                'enabled' => false,
                'sandbox_mode' => true,
                'consumer_key' => '',
                'consumer_secret' => '',
                'shortcode' => '',
                'passkey' => '',
                'callback_url' => site_url('/wp-json/sms/v1/mpesa/callback'),
                'timeout_url' => site_url('/wp-json/sms/v1/mpesa/timeout'),
                'result_url' => site_url('/wp-json/sms/v1/mpesa/result')
            ),
            'airtel_money' => array(
                'enabled' => false,
                'sandbox_mode' => true,
                'client_id' => '',
                'client_secret' => '',
                'merchant_id' => '',
                'callback_url' => site_url('/wp-json/sms/v1/airtel/callback')
            ),
            'equity_bank' => array(
                'enabled' => false,
                'sandbox_mode' => true,
                'merchant_id' => '',
                'api_key' => '',
                'secret_key' => '',
                'callback_url' => site_url('/wp-json/sms/v1/equity/callback')
            ),
            'cash' => array(
                'enabled' => true,
                'require_receipt' => true,
                'auto_approve' => false
            )
        );
        
        return isset($templates[$gateway_id]) ? $templates[$gateway_id] : array();
    }
    
    /**
     * Validate gateway configuration
     *
     * @param string $gateway_id Gateway identifier
     * @param array $config Configuration data
     * @return bool|WP_Error
     */
    public function validate_config($gateway_id, $config) {
        $validation_rules = $this->get_validation_rules($gateway_id);
        
        foreach ($validation_rules as $field => $rules) {
            if (isset($rules['required']) && $rules['required']) {
                if (empty($config[$field])) {
                    return new WP_Error('missing_field', "Field '{$field}' is required");
                }
            }
            
            if (isset($config[$field]) && !empty($config[$field])) {
                if (isset($rules['type'])) {
                    $validation_result = $this->validate_field_type($config[$field], $rules['type']);
                    if (is_wp_error($validation_result)) {
                        return $validation_result;
                    }
                }
                
                if (isset($rules['pattern'])) {
                    if (!preg_match($rules['pattern'], $config[$field])) {
                        return new WP_Error('invalid_format', "Field '{$field}' has invalid format");
                    }
                }
            }
        }
        
        return true;
    }
    
    /**
     * Get validation rules for gateway
     *
     * @param string $gateway_id Gateway identifier
     * @return array
     */
    private function get_validation_rules($gateway_id) {
        $rules = array(
            'mpesa' => array(
                'consumer_key' => array('required' => true, 'type' => 'string'),
                'consumer_secret' => array('required' => true, 'type' => 'string'),
                'shortcode' => array('required' => true, 'type' => 'numeric'),
                'passkey' => array('required' => true, 'type' => 'string'),
                'callback_url' => array('required' => true, 'type' => 'url')
            ),
            'airtel_money' => array(
                'client_id' => array('required' => true, 'type' => 'string'),
                'client_secret' => array('required' => true, 'type' => 'string'),
                'merchant_id' => array('required' => true, 'type' => 'string'),
                'callback_url' => array('required' => true, 'type' => 'url')
            ),
            'equity_bank' => array(
                'merchant_id' => array('required' => true, 'type' => 'string'),
                'api_key' => array('required' => true, 'type' => 'string'),
                'secret_key' => array('required' => true, 'type' => 'string'),
                'callback_url' => array('required' => true, 'type' => 'url')
            )
        );
        
        return isset($rules[$gateway_id]) ? $rules[$gateway_id] : array();
    }
    
    /**
     * Validate field type
     *
     * @param mixed $value Field value
     * @param string $type Expected type
     * @return bool|WP_Error
     */
    private function validate_field_type($value, $type) {
        switch ($type) {
            case 'string':
                return is_string($value) ? true : new WP_Error('invalid_type', 'Value must be a string');
                
            case 'numeric':
                return is_numeric($value) ? true : new WP_Error('invalid_type', 'Value must be numeric');
                
            case 'url':
                return filter_var($value, FILTER_VALIDATE_URL) ? true : new WP_Error('invalid_type', 'Value must be a valid URL');
                
            case 'email':
                return filter_var($value, FILTER_VALIDATE_EMAIL) ? true : new WP_Error('invalid_type', 'Value must be a valid email');
                
            default:
                return true;
        }
    }
    
    /**
     * Test gateway connection
     *
     * @param string $gateway_id Gateway identifier
     * @param array $config Configuration to test
     * @return bool|WP_Error
     */
    public function test_gateway_connection($gateway_id, $config = null) {
        if (!$config) {
            $config = $this->get_config($gateway_id);
        }
        
        if (!$config) {
            return new WP_Error('no_config', 'No configuration found for gateway');
        }
        
        // This would be implemented by each gateway class
        return apply_filters("sms_test_gateway_connection_{$gateway_id}", true, $config);
    }
    
    /**
     * Register admin settings
     */
    public function register_settings() {
        register_setting('sms_payment_gateways', $this->config_key);
        register_setting('sms_payment_gateways', $this->default_gateway_key);
        register_setting('sms_payment_gateways', $this->fallback_order_key);
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'sms-admin',
            'Payment Gateways',
            'Payment Gateways',
            'manage_options',
            'sms-payment-gateways',
            array($this, 'admin_page')
        );
    }
    
    /**
     * Admin page callback
     */
    public function admin_page() {
        // This would render the admin interface for gateway configuration
        echo '<div class="wrap">';
        echo '<h1>Payment Gateway Configuration</h1>';
        echo '<p>Gateway configuration interface would be implemented here.</p>';
        echo '</div>';
    }
}