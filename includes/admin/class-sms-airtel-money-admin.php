<?php
/**
 * Airtel Money Admin Interface
 *
 * Handles Airtel Money gateway administration interface
 *
 * @package SchoolManagementSystem
 * @subpackage Admin
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Airtel Money Admin Class
 */
class SMS_Airtel_Money_Admin {
    
    /**
     * Gateway configuration manager
     *
     * @var SMS_Gateway_Config_Manager
     */
    private $config_manager;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->config_manager = new SMS_Gateway_Config_Manager();
        
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'init_admin']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_ajax_sms_test_airtel_connection', [$this, 'test_airtel_connection']);
        add_action('wp_ajax_sms_save_airtel_config', [$this, 'save_airtel_config']);
        add_action('wp_ajax_sms_test_airtel_payment', [$this, 'test_airtel_payment']);
        add_action('wp_ajax_sms_verify_airtel_payment', [$this, 'verify_airtel_payment']);
    }
    
    /**
     * Initialize admin functionality
     */
    public function init_admin() {
        // Register Airtel Money settings
        register_setting('sms_airtel_money_settings', 'sms_airtel_money_config');
        
        // Add settings sections
        add_settings_section(
            'sms_airtel_money_general',
            'General Settings',
            [$this, 'render_general_section'],
            'sms_airtel_money_settings'
        );
        
        add_settings_section(
            'sms_airtel_money_api',
            'API Configuration',
            [$this, 'render_api_section'],
            'sms_airtel_money_settings'
        );
        
        // Add settings fields
        $this->add_settings_fields();
    }
    
    /**
     * Add settings fields
     */
    private function add_settings_fields() {
        // General settings
        add_settings_field(
            'enabled',
            'Enable Airtel Money',
            [$this, 'render_enabled_field'],
            'sms_airtel_money_settings',
            'sms_airtel_money_general'
        );
        
        add_settings_field(
            'sandbox_mode',
            'Sandbox Mode',
            [$this, 'render_sandbox_field'],
            'sms_airtel_money_settings',
            'sms_airtel_money_general'
        );
        
        // API settings
        add_settings_field(
            'client_id',
            'Client ID',
            [$this, 'render_client_id_field'],
            'sms_airtel_money_settings',
            'sms_airtel_money_api'
        );
        
        add_settings_field(
            'client_secret',
            'Client Secret',
            [$this, 'render_client_secret_field'],
            'sms_airtel_money_settings',
            'sms_airtel_money_api'
        );
        
        add_settings_field(
            'merchant_id',
            'Merchant ID',
            [$this, 'render_merchant_id_field'],
            'sms_airtel_money_settings',
            'sms_airtel_money_api'
        );
        
        add_settings_field(
            'callback_url',
            'Callback URL',
            [$this, 'render_callback_url_field'],
            'sms_airtel_money_settings',
            'sms_airtel_money_api'
        );
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook_suffix) {
        // Only load on Airtel Money admin pages
        if (strpos($hook_suffix, 'sms-airtel-money') === false) {
            return;
        }
        
        // Enqueue CSS
        wp_enqueue_style(
            'sms-airtel-money-admin',
            SMS_PLUGIN_URL . 'admin/css/airtel-money-admin.css',
            [],
            SMS_VERSION
        );
        
        // Enqueue JavaScript
        wp_enqueue_script(
            'sms-airtel-money-admin',
            SMS_PLUGIN_URL . 'admin/js/airtel-money-admin.js',
            ['jquery'],
            SMS_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('sms-airtel-money-admin', 'smsAirtelAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sms_airtel_admin'),
            'restUrl' => rest_url('sms/v1/'),
            'restNonce' => wp_create_nonce('wp_rest'),
            'messages' => [
                'connectionSuccess' => 'Connection successful!',
                'connectionFailed' => 'Connection failed. Please check your configuration.',
                'paymentInitiated' => 'Payment initiated successfully!',
                'paymentFailed' => 'Payment initiation failed.',
                'verificationSuccess' => 'Payment verification completed!',
                'verificationFailed' => 'Payment verification failed.'
            ]
        ]);
    }
    
    /**
     * Add admin menu items
     */
    public function add_admin_menu() {
        // Add Airtel Money configuration page
        add_submenu_page(
            'sms-admin',
            'Airtel Money Configuration',
            'Airtel Money',
            'manage_options',
            'sms-airtel-money',
            [$this, 'render_config_page']
        );
        
        // Add Airtel Money test page
        add_submenu_page(
            'sms-admin',
            'Airtel Money Test',
            'Airtel Money Test',
            'manage_options',
            'sms-airtel-money-test',
            [$this, 'render_test_page']
        );
    }
    
    /**
     * Render configuration page
     */
    public function render_config_page() {
        $config = $this->config_manager->get_config('airtel_money') ?: [];
        
        ?>
        <div class="wrap">
            <h1>Airtel Money Configuration</h1>
            
            <?php if (isset($_GET['settings-updated']) && $_GET['settings-updated']): ?>
                <div class="notice notice-success is-dismissible">
                    <p>Airtel Money configuration saved successfully!</p>
                </div>
            <?php endif; ?>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('sms_airtel_money_settings');
                do_settings_sections('sms_airtel_money_settings');
                ?>
                
                <div class="card">
                    <h2>Test Connection</h2>
                    <p>Test your Airtel Money API configuration to ensure it's working correctly.</p>
                    <button type="button" id="test-airtel-connection" class="button button-secondary">
                        Test Connection
                    </button>
                    <div id="test-result" style="margin-top: 10px;"></div>
                </div>
                
                <?php submit_button('Save Configuration'); ?>
            </form>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#test-airtel-connection').on('click', function() {
                var button = $(this);
                var resultDiv = $('#test-result');
                
                button.prop('disabled', true).text('Testing...');
                resultDiv.html('');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'sms_test_airtel_connection',
                        nonce: '<?php echo wp_create_nonce('sms_airtel_admin'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            resultDiv.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                        } else {
                            resultDiv.html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
                        }
                    },
                    error: function() {
                        resultDiv.html('<div class="notice notice-error"><p>Connection test failed</p></div>');
                    },
                    complete: function() {
                        button.prop('disabled', false).text('Test Connection');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Render test page
     */
    public function render_test_page() {
        include SMS_PLUGIN_PATH . 'admin/partials/airtel-money-test.php';
    }
    
    /**
     * Render general section
     */
    public function render_general_section() {
        echo '<p>Configure general Airtel Money gateway settings.</p>';
    }
    
    /**
     * Render API section
     */
    public function render_api_section() {
        echo '<p>Configure your Airtel Money API credentials. You can get these from your Airtel Money developer account.</p>';
    }
    
    /**
     * Render enabled field
     */
    public function render_enabled_field() {
        $config = $this->config_manager->get_config('airtel_money') ?: [];
        $enabled = isset($config['enabled']) ? $config['enabled'] : false;
        
        echo '<input type="checkbox" name="sms_airtel_money_config[enabled]" value="1" ' . checked($enabled, true, false) . ' />';
        echo '<p class="description">Enable Airtel Money payment gateway</p>';
    }
    
    /**
     * Render sandbox field
     */
    public function render_sandbox_field() {
        $config = $this->config_manager->get_config('airtel_money') ?: [];
        $sandbox = isset($config['sandbox_mode']) ? $config['sandbox_mode'] : true;
        
        echo '<input type="checkbox" name="sms_airtel_money_config[sandbox_mode]" value="1" ' . checked($sandbox, true, false) . ' />';
        echo '<p class="description">Enable sandbox mode for testing</p>';
    }
    
    /**
     * Render client ID field
     */
    public function render_client_id_field() {
        $config = $this->config_manager->get_config('airtel_money') ?: [];
        $client_id = isset($config['client_id']) ? $config['client_id'] : '';
        
        echo '<input type="text" name="sms_airtel_money_config[client_id]" value="' . esc_attr($client_id) . '" class="regular-text" />';
        echo '<p class="description">Your Airtel Money API Client ID</p>';
    }
    
    /**
     * Render client secret field
     */
    public function render_client_secret_field() {
        $config = $this->config_manager->get_config('airtel_money') ?: [];
        $client_secret = isset($config['client_secret']) ? $config['client_secret'] : '';
        
        echo '<input type="password" name="sms_airtel_money_config[client_secret]" value="' . esc_attr($client_secret) . '" class="regular-text" />';
        echo '<p class="description">Your Airtel Money API Client Secret</p>';
    }
    
    /**
     * Render merchant ID field
     */
    public function render_merchant_id_field() {
        $config = $this->config_manager->get_config('airtel_money') ?: [];
        $merchant_id = isset($config['merchant_id']) ? $config['merchant_id'] : '';
        
        echo '<input type="text" name="sms_airtel_money_config[merchant_id]" value="' . esc_attr($merchant_id) . '" class="regular-text" />';
        echo '<p class="description">Your Airtel Money Merchant ID</p>';
    }
    
    /**
     * Render callback URL field
     */
    public function render_callback_url_field() {
        $config = $this->config_manager->get_config('airtel_money') ?: [];
        $callback_url = isset($config['callback_url']) ? $config['callback_url'] : site_url('/wp-json/sms/v1/airtel/callback');
        
        echo '<input type="url" name="sms_airtel_money_config[callback_url]" value="' . esc_attr($callback_url) . '" class="regular-text" readonly />';
        echo '<p class="description">Callback URL for payment notifications (automatically generated)</p>';
    }
    
    /**
     * Test Airtel Money connection via AJAX
     */
    public function test_airtel_connection() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_airtel_admin')) {
            wp_send_json_error('Invalid security token');
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        try {
            // Get current configuration
            $config = $this->config_manager->get_config('airtel_money');
            
            if (!$config) {
                wp_send_json_error('No Airtel Money configuration found');
            }
            
            // Create gateway instance and test connection
            $gateway = new SMS_Airtel_Money_Gateway($config);
            $test_result = $gateway->test_connection();
            
            if (is_wp_error($test_result)) {
                wp_send_json_error($test_result->get_error_message());
            }
            
            wp_send_json_success($test_result);
            
        } catch (Exception $e) {
            wp_send_json_error('Connection test failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Save Airtel Money configuration via AJAX
     */
    public function save_airtel_config() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_airtel_admin')) {
            wp_send_json_error('Invalid security token');
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        try {
            $config_data = $_POST['config'];
            
            // Validate configuration
            $validation = $this->config_manager->validate_config('airtel_money', $config_data);
            
            if (is_wp_error($validation)) {
                wp_send_json_error($validation->get_error_message());
            }
            
            // Save configuration
            $saved = $this->config_manager->save_config('airtel_money', $config_data);
            
            if ($saved) {
                wp_send_json_success('Configuration saved successfully');
            } else {
                wp_send_json_error('Failed to save configuration');
            }
            
        } catch (Exception $e) {
            wp_send_json_error('Save failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Get Airtel Money transaction statistics
     *
     * @return array
     */
    public function get_transaction_stats() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'sms_airtel_money_transactions';
        
        $stats = [];
        
        // Total transactions
        $stats['total'] = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
        
        // Successful transactions
        $stats['successful'] = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE status = 'completed'");
        
        // Failed transactions
        $stats['failed'] = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE status = 'failed'");
        
        // Pending transactions
        $stats['pending'] = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE status = 'pending'");
        
        // Total amount processed
        $stats['total_amount'] = $wpdb->get_var("SELECT SUM(amount) FROM {$table_name} WHERE status = 'completed'");
        
        // Recent transactions
        $stats['recent'] = $wpdb->get_results(
            "SELECT * FROM {$table_name} ORDER BY created_at DESC LIMIT 10",
            ARRAY_A
        );
        
        return $stats;
    }
    
    /**
     * Test Airtel Money payment via AJAX
     */
    public function test_airtel_payment() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_airtel_admin')) {
            wp_send_json_error('Invalid security token');
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        try {
            // Get payment data
            $amount = floatval($_POST['amount']);
            $phone_number = sanitize_text_field($_POST['phone_number']);
            $reference = sanitize_text_field($_POST['reference']);
            $description = sanitize_text_field($_POST['description']) ?: 'Test Payment';
            
            // Validate input
            if ($amount <= 0 || $amount > 70000) {
                wp_send_json_error('Invalid amount. Must be between KES 1 and KES 70,000');
            }
            
            if (!preg_match('/^07\d{8}$/', $phone_number)) {
                wp_send_json_error('Invalid phone number format. Use 07XXXXXXXX');
            }
            
            if (empty($reference)) {
                wp_send_json_error('Payment reference is required');
            }
            
            // Get gateway configuration
            $config = $this->config_manager->get_config('airtel_money');
            
            if (!$config || !$config['enabled']) {
                wp_send_json_error('Airtel Money gateway is not configured or enabled');
            }
            
            // Create gateway instance and process payment
            $gateway = new SMS_Airtel_Money_Gateway($config);
            $result = $gateway->initialize_payment($amount, $phone_number, $reference, [
                'description' => $description
            ]);
            
            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
            }
            
            wp_send_json_success($result);
            
        } catch (Exception $e) {
            wp_send_json_error('Payment test failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Verify Airtel Money payment via AJAX
     */
    public function verify_airtel_payment() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_airtel_admin')) {
            wp_send_json_error('Invalid security token');
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        try {
            $transaction_id = sanitize_text_field($_POST['transaction_id']);
            
            if (empty($transaction_id)) {
                wp_send_json_error('Transaction ID is required');
            }
            
            // Get gateway configuration
            $config = $this->config_manager->get_config('airtel_money');
            
            if (!$config || !$config['enabled']) {
                wp_send_json_error('Airtel Money gateway is not configured or enabled');
            }
            
            // Create gateway instance and verify payment
            $gateway = new SMS_Airtel_Money_Gateway($config);
            $result = $gateway->verify_payment($transaction_id);
            
            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
            }
            
            wp_send_json_success($result);
            
        } catch (Exception $e) {
            wp_send_json_error('Payment verification failed: ' . $e->getMessage());
        }
    }
}

// Initialize the admin class
new SMS_Airtel_Money_Admin();