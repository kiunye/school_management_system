<?php
/**
 * M-Pesa Admin Interface
 *
 * Handles M-Pesa gateway administration interface
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
 * M-Pesa Admin Class
 */
class SMS_MPESA_Admin {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'init_admin']);
    }
    
    /**
     * Initialize admin functionality
     */
    public function init_admin() {
        // Register settings if needed
        // This can be expanded for more admin functionality
    }
    
    /**
     * Add admin menu items
     */
    public function add_admin_menu() {
        // Add M-Pesa test page under SMS admin menu
        add_submenu_page(
            'sms-admin',
            'M-Pesa Gateway Test',
            'M-Pesa Test',
            'manage_options',
            'sms-mpesa-test',
            [$this, 'render_test_page']
        );
    }
    
    /**
     * Render M-Pesa test page
     */
    public function render_test_page() {
        // Include the test page template
        include SMS_PLUGIN_PATH . 'admin/partials/mpesa-test.php';
    }
}

// Initialize the admin class
new SMS_MPESA_Admin();