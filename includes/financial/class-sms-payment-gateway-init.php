<?php
/**
 * Payment Gateway Initialization
 *
 * Initializes and coordinates all payment gateway components
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
 * Payment Gateway Initialization Class
 */
class SMS_Payment_Gateway_Init {
    
    /**
     * Gateway manager instance
     *
     * @var SMS_Payment_Gateway_Manager
     */
    private $gateway_manager;
    
    /**
     * Gateway selector instance
     *
     * @var SMS_Gateway_Selector
     */
    private $gateway_selector;
    
    /**
     * Configuration manager instance
     *
     * @var SMS_Gateway_Config_Manager
     */
    private $config_manager;
    
    /**
     * Instance of this class
     *
     * @var SMS_Payment_Gateway_Init
     */
    private static $instance = null;
    
    /**
     * Get singleton instance
     *
     * @return SMS_Payment_Gateway_Init
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
        $this->load_dependencies();
        $this->init_components();
        $this->setup_hooks();
    }
    
    /**
     * Load required dependencies
     */
    private function load_dependencies() {
        // Load base classes
        require_once SMS_PLUGIN_PATH . 'includes/financial/class-sms-payment-gateway-base.php';
        require_once SMS_PLUGIN_PATH . 'includes/financial/class-sms-payment-gateway-manager.php';
        require_once SMS_PLUGIN_PATH . 'includes/financial/class-sms-gateway-selector.php';
        require_once SMS_PLUGIN_PATH . 'includes/financial/class-sms-gateway-config-manager.php';
        
        // Load gateway implementations
        require_once SMS_PLUGIN_PATH . 'includes/financial/class-sms-mpesa-gateway.php';
        require_once SMS_PLUGIN_PATH . 'includes/financial/class-sms-airtel-money-gateway.php';
    }
    
    /**
     * Initialize components
     */
    private function init_components() {
        // Initialize configuration manager first
        $this->config_manager = new SMS_Gateway_Config_Manager();
        
        // Initialize gateway manager
        $this->gateway_manager = SMS_Payment_Gateway_Manager::get_instance();
        
        // Initialize gateway selector
        $this->gateway_selector = new SMS_Gateway_Selector($this->gateway_manager);
    }
    
    /**
     * Setup WordPress hooks
     */
    private function setup_hooks() {
        // Initialize on WordPress init
        add_action('init', array($this, 'init_payment_system'));
        
        // Register REST API endpoints
        add_action('rest_api_init', array($this, 'register_rest_endpoints'));
        
        // Handle payment processing
        add_action('wp_ajax_sms_process_payment', array($this, 'handle_payment_request'));
        add_action('wp_ajax_nopriv_sms_process_payment', array($this, 'handle_payment_request'));
        
        // Handle payment verification
        add_action('wp_ajax_sms_verify_payment', array($this, 'handle_payment_verification'));
        add_action('wp_ajax_nopriv_sms_verify_payment', array($this, 'handle_payment_verification'));
        
        // Handle gateway selection
        add_action('wp_ajax_sms_select_gateway', array($this, 'handle_gateway_selection'));
        add_action('wp_ajax_nopriv_sms_select_gateway', array($this, 'handle_gateway_selection'));
        
        // Admin hooks
        if (is_admin()) {
            add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        }
        
        // Frontend hooks
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
    }
    
    /**
     * Initialize payment system
     */
    public function init_payment_system() {
        // Register default gateways
        $this->register_default_gateways();
        
        // Allow other plugins/themes to register custom gateways
        do_action('sms_register_payment_gateways', $this->gateway_manager);
        
        // Initialize default configurations if not exist
        $this->init_default_configs();
        
        // Set up payment processing hooks
        $this->setup_payment_hooks();
    }
    
    /**
     * Register default payment gateways
     */
    private function register_default_gateways() {
        // Register M-Pesa gateway
        $mpesa_config = $this->config_manager->get_config('mpesa');
        if ($mpesa_config) {
            $mpesa_gateway = new SMS_MPESA_Gateway($mpesa_config);
            $this->gateway_manager->register_gateway('mpesa', $mpesa_gateway);
            
            // Create M-Pesa transactions table if it doesn't exist
            SMS_MPESA_Gateway::create_transactions_table();
        }
        
        // Register Airtel Money gateway
        $airtel_config = $this->config_manager->get_config('airtel_money');
        if ($airtel_config) {
            $airtel_gateway = new SMS_Airtel_Money_Gateway($airtel_config);
            $this->gateway_manager->register_gateway('airtel_money', $airtel_gateway);
            
            // Create Airtel Money transactions table if it doesn't exist
            SMS_Airtel_Money_Gateway::create_transactions_table();
        }
        
        // TODO: Register other gateways (Equity Bank, etc.)
    }
    
    /**
     * Initialize default configurations
     */
    private function init_default_configs() {
        $gateways = array('mpesa', 'airtel_money', 'equity_bank', 'cash');
        
        foreach ($gateways as $gateway_id) {
            $existing_config = $this->config_manager->get_config($gateway_id);
            
            if (!$existing_config) {
                $default_config = $this->config_manager->get_default_config_template($gateway_id);
                $this->config_manager->save_config($gateway_id, $default_config);
            }
        }
    }
    
    /**
     * Setup payment processing hooks
     */
    private function setup_payment_hooks() {
        // Hook into invoice payment processing
        add_action('sms_process_invoice_payment', array($this, 'process_invoice_payment'), 10, 3);
        
        // Hook into payment verification
        add_action('sms_verify_payment', array($this, 'verify_payment'), 10, 2);
        
        // Hook into payment status updates
        add_action('sms_payment_status_updated', array($this, 'handle_payment_status_update'), 10, 3);
    }
    
    /**
     * Register REST API endpoints
     */
    public function register_rest_endpoints() {
        // Payment processing endpoint
        register_rest_route('sms/v1', '/payment/process', array(
            'methods' => 'POST',
            'callback' => array($this, 'rest_process_payment'),
            'permission_callback' => array($this, 'check_payment_permissions'),
            'args' => array(
                'gateway_id' => array(
                    'required' => true,
                    'type' => 'string'
                ),
                'amount' => array(
                    'required' => true,
                    'type' => 'number'
                ),
                'phone_number' => array(
                    'required' => true,
                    'type' => 'string'
                ),
                'reference' => array(
                    'required' => true,
                    'type' => 'string'
                )
            )
        ));
        
        // Gateway selection endpoint
        register_rest_route('sms/v1', '/payment/select-gateway', array(
            'methods' => 'POST',
            'callback' => array($this, 'rest_select_gateway'),
            'permission_callback' => array($this, 'check_payment_permissions'),
            'args' => array(
                'amount' => array(
                    'required' => true,
                    'type' => 'number'
                ),
                'phone_number' => array(
                    'required' => false,
                    'type' => 'string'
                ),
                'preferred_gateway' => array(
                    'required' => false,
                    'type' => 'string'
                )
            )
        ));
        
        // Available gateways endpoint
        register_rest_route('sms/v1', '/payment/gateways', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_get_gateways'),
            'permission_callback' => array($this, 'check_payment_permissions')
        ));
    }
    
    /**
     * Check payment permissions for REST API
     *
     * @param WP_REST_Request $request
     * @return bool
     */
    public function check_payment_permissions($request) {
        // Allow logged-in users with payment capabilities
        return current_user_can('make_payments') || current_user_can('manage_fees');
    }
    
    /**
     * REST API: Process payment
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function rest_process_payment($request) {
        $gateway_id = $request->get_param('gateway_id');
        $amount = $request->get_param('amount');
        $phone_number = $request->get_param('phone_number');
        $reference = $request->get_param('reference');
        $additional_data = $request->get_param('additional_data') ?: array();
        
        $result = $this->gateway_manager->process_payment(
            $gateway_id,
            $amount,
            $phone_number,
            $reference,
            $additional_data
        );
        
        if (is_wp_error($result)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => $result->get_error_message(),
                'code' => $result->get_error_code()
            ), 400);
        }
        
        return new WP_REST_Response(array(
            'success' => true,
            'data' => $result
        ), 200);
    }
    
    /**
     * REST API: Select gateway
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function rest_select_gateway($request) {
        $criteria = array(
            'amount' => $request->get_param('amount'),
            'phone_number' => $request->get_param('phone_number'),
            'preferred_gateway' => $request->get_param('preferred_gateway')
        );
        
        $selected_gateway = $this->gateway_selector->select_best_gateway($criteria);
        
        if (is_wp_error($selected_gateway)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => $selected_gateway->get_error_message()
            ), 400);
        }
        
        return new WP_REST_Response(array(
            'success' => true,
            'gateway_id' => $selected_gateway,
            'fallback_order' => $this->gateway_selector->get_fallback_order($selected_gateway, $criteria)
        ), 200);
    }
    
    /**
     * REST API: Get available gateways
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function rest_get_gateways($request) {
        $gateways = $this->gateway_manager->get_available_payment_methods();
        
        return new WP_REST_Response(array(
            'success' => true,
            'gateways' => $gateways
        ), 200);
    }
    
    /**
     * Handle AJAX payment request
     */
    public function handle_payment_request() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_payment_nonce')) {
            wp_send_json_error('Invalid security token');
        }
        
        $gateway_id = sanitize_text_field($_POST['gateway_id']);
        $amount = floatval($_POST['amount']);
        $phone_number = sanitize_text_field($_POST['phone_number']);
        $reference = sanitize_text_field($_POST['reference']);
        
        $result = $this->gateway_manager->process_payment($gateway_id, $amount, $phone_number, $reference);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * Handle AJAX payment verification
     */
    public function handle_payment_verification() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_payment_nonce')) {
            wp_send_json_error('Invalid security token');
        }
        
        $gateway_id = sanitize_text_field($_POST['gateway_id']);
        $transaction_id = sanitize_text_field($_POST['transaction_id']);
        
        $result = $this->gateway_manager->verify_payment($gateway_id, $transaction_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * Handle AJAX gateway selection
     */
    public function handle_gateway_selection() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_payment_nonce')) {
            wp_send_json_error('Invalid security token');
        }
        
        $criteria = array(
            'amount' => floatval($_POST['amount']),
            'phone_number' => sanitize_text_field($_POST['phone_number']),
            'preferred_gateway' => sanitize_text_field($_POST['preferred_gateway'])
        );
        
        $selected_gateway = $this->gateway_selector->select_best_gateway($criteria);
        
        if (is_wp_error($selected_gateway)) {
            wp_send_json_error($selected_gateway->get_error_message());
        }
        
        wp_send_json_success(array(
            'gateway_id' => $selected_gateway,
            'fallback_order' => $this->gateway_selector->get_fallback_order($selected_gateway, $criteria)
        ));
    }
    
    /**
     * Process invoice payment
     *
     * @param int $invoice_id Invoice ID
     * @param array $payment_data Payment data
     * @param string $gateway_id Gateway ID
     */
    public function process_invoice_payment($invoice_id, $payment_data, $gateway_id = null) {
        if (!$gateway_id) {
            $gateway_id = $this->gateway_selector->select_best_gateway($payment_data);
            
            if (is_wp_error($gateway_id)) {
                do_action('sms_payment_failed', $invoice_id, $gateway_id->get_error_message());
                return;
            }
        }
        
        $result = $this->gateway_manager->process_payment(
            $gateway_id,
            $payment_data['amount'],
            $payment_data['phone_number'],
            $payment_data['reference'],
            $payment_data
        );
        
        if (is_wp_error($result)) {
            do_action('sms_payment_failed', $invoice_id, $result->get_error_message());
        } else {
            do_action('sms_payment_initiated', $invoice_id, $result);
        }
    }
    
    /**
     * Verify payment
     *
     * @param string $gateway_id Gateway ID
     * @param string $transaction_id Transaction ID
     */
    public function verify_payment($gateway_id, $transaction_id) {
        $result = $this->gateway_manager->verify_payment($gateway_id, $transaction_id);
        
        if (is_wp_error($result)) {
            do_action('sms_payment_verification_failed', $gateway_id, $transaction_id, $result->get_error_message());
        } else {
            do_action('sms_payment_verified', $gateway_id, $transaction_id, $result);
        }
    }
    
    /**
     * Handle payment status update
     *
     * @param string $gateway_id Gateway ID
     * @param string $transaction_id Transaction ID
     * @param array $status_data Status data
     */
    public function handle_payment_status_update($gateway_id, $transaction_id, $status_data) {
        // Update transaction record
        do_action('sms_update_transaction_status', $transaction_id, $status_data);
        
        // Send notifications if needed
        if ($status_data['status'] === 'completed') {
            do_action('sms_payment_completed', $gateway_id, $transaction_id, $status_data);
        } elseif ($status_data['status'] === 'failed') {
            do_action('sms_payment_failed', $gateway_id, $transaction_id, $status_data);
        }
    }
    
    /**
     * Enqueue admin scripts
     *
     * @param string $hook_suffix
     */
    public function enqueue_admin_scripts($hook_suffix) {
        if (strpos($hook_suffix, 'sms-') !== false) {
            wp_enqueue_script(
                'sms-payment-gateway-admin',
                SMS_PLUGIN_URL . 'admin/js/payment-gateway-admin.js',
                array('jquery'),
                SMS_VERSION,
                true
            );
            
            wp_localize_script('sms-payment-gateway-admin', 'smsPaymentGateway', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('sms_payment_nonce'),
                'restUrl' => rest_url('sms/v1/'),
                'restNonce' => wp_create_nonce('wp_rest')
            ));
        }
    }
    
    /**
     * Enqueue frontend scripts
     */
    public function enqueue_frontend_scripts() {
        if (is_user_logged_in()) {
            wp_enqueue_script(
                'sms-payment-gateway',
                SMS_PLUGIN_URL . 'public/js/payment-gateway.js',
                array('jquery'),
                SMS_VERSION,
                true
            );
            
            wp_localize_script('sms-payment-gateway', 'smsPaymentGateway', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('sms_payment_nonce'),
                'restUrl' => rest_url('sms/v1/'),
                'restNonce' => wp_create_nonce('wp_rest')
            ));
        }
    }
    
    /**
     * Get gateway manager instance
     *
     * @return SMS_Payment_Gateway_Manager
     */
    public function get_gateway_manager() {
        return $this->gateway_manager;
    }
    
    /**
     * Get gateway selector instance
     *
     * @return SMS_Gateway_Selector
     */
    public function get_gateway_selector() {
        return $this->gateway_selector;
    }
    
    /**
     * Get configuration manager instance
     *
     * @return SMS_Gateway_Config_Manager
     */
    public function get_config_manager() {
        return $this->config_manager;
    }
}