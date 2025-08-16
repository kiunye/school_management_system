<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @package    School_Management_System
 * @subpackage School_Management_System/includes/admin
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * The admin-specific functionality of the plugin.
 */
class SMS_Admin extends SMS_Base {

    /**
     * Initialize the class and set its properties.
     */
    public function __construct($plugin_name, $version) {
        parent::__construct();
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        
        // Initialize timetable builder
        $this->init_timetable_builder();
    }
    
    /**
     * Initialize timetable builder
     */
    private function init_timetable_builder() {
        if (class_exists('SMS_Timetable_Builder')) {
            new SMS_Timetable_Builder();
        }
    }

    /**
     * Register the stylesheets for the admin area.
     */
    public function enqueue_styles() {
        wp_enqueue_style(
            $this->plugin_name,
            SMS_PLUGIN_URL . 'admin/css/sms-admin.css',
            array(),
            $this->version,
            'all'
        );
    }

    /**
     * Register the JavaScript for the admin area.
     */
    public function enqueue_scripts() {
        wp_enqueue_script(
            $this->plugin_name,
            SMS_PLUGIN_URL . 'admin/js/sms-admin.js',
            array('jquery'),
            $this->version,
            false
        );

        // Localize script for AJAX
        wp_localize_script(
            $this->plugin_name,
            'sms_admin_ajax',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('sms_admin_nonce'),
                'strings' => array(
                    'confirm_delete' => __('Are you sure you want to delete this item?', 'school-management-system'),
                    'processing' => __('Processing...', 'school-management-system'),
                    'error' => __('An error occurred. Please try again.', 'school-management-system')
                )
            )
        );
    }

    /**
     * Add admin menu pages.
     */
    public function add_admin_menu() {
        // Main menu page
        add_menu_page(
            __('School Management', 'school-management-system'),
            __('School Management', 'school-management-system'),
            'edit_posts',
            'school-management',
            array($this, 'display_dashboard'),
            'dashicons-graduation-cap',
            30
        );

        // Dashboard submenu
        add_submenu_page(
            'school-management',
            __('Dashboard', 'school-management-system'),
            __('Dashboard', 'school-management-system'),
            'edit_posts',
            'school-management',
            array($this, 'display_dashboard')
        );

        // Students submenu
        add_submenu_page(
            'school-management',
            __('Students', 'school-management-system'),
            __('Students', 'school-management-system'),
            'edit_posts',
            'sms-students',
            array($this, 'display_students_page')
        );

        // Classes submenu
        add_submenu_page(
            'school-management',
            __('Classes', 'school-management-system'),
            __('Classes', 'school-management-system'),
            'edit_posts',
            'sms-classes',
            array($this, 'display_classes_page')
        );

        // Fees submenu
        add_submenu_page(
            'school-management',
            __('Fees & Payments', 'school-management-system'),
            __('Fees & Payments', 'school-management-system'),
            'edit_posts',
            'sms-fees',
            array($this, 'display_fees_page')
        );

        // Reports submenu
        add_submenu_page(
            'school-management',
            __('Reports', 'school-management-system'),
            __('Reports', 'school-management-system'),
            'edit_posts',
            'sms-reports',
            array($this, 'display_reports_page')
        );

        // Settings submenu
        add_submenu_page(
            'school-management',
            __('Settings', 'school-management-system'),
            __('Settings', 'school-management-system'),
            'manage_options',
            'sms-settings',
            array($this, 'display_settings_page')
        );
    }

    /**
     * Initialize admin functionality.
     */
    public function admin_init() {
        // Register settings
        $this->register_settings();
        
        // Add admin notices
        add_action('admin_notices', array($this, 'display_admin_notices'));
    }

    /**
     * Register plugin settings.
     */
    private function register_settings() {
        // General settings
        register_setting('sms_general_settings', 'sms_school_name');
        register_setting('sms_general_settings', 'sms_academic_year');
        register_setting('sms_general_settings', 'sms_current_term');
        
        // Payment gateway settings
        register_setting('sms_payment_settings', 'sms_payment_gateways_enabled');
        register_setting('sms_payment_settings', 'sms_mpesa_settings');
        register_setting('sms_payment_settings', 'sms_airtel_money_settings');
        
        // SMS settings
        register_setting('sms_communication_settings', 'sms_africastalking_enabled');
        register_setting('sms_communication_settings', 'sms_africastalking_settings');
    }

    /**
     * Display dashboard page.
     */
    public function display_dashboard() {
        if (!$this->check_capability('manage_students')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        include SMS_PLUGIN_DIR . 'admin/partials/dashboard.php';
    }

    /**
     * Display students page.
     */
    public function display_students_page() {
        if (!$this->check_capability('manage_students')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        include SMS_PLUGIN_DIR . 'admin/partials/students.php';
    }

    /**
     * Display classes page.
     */
    public function display_classes_page() {
        if (!$this->check_capability('manage_classes')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        include SMS_PLUGIN_DIR . 'admin/partials/classes.php';
    }

    /**
     * Display fees page.
     */
    public function display_fees_page() {
        if (!$this->check_capability('manage_fees')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        include SMS_PLUGIN_DIR . 'admin/partials/fees.php';
    }

    /**
     * Display reports page.
     */
    public function display_reports_page() {
        if (!$this->check_capability('view_all_reports')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        include SMS_PLUGIN_DIR . 'admin/partials/reports.php';
    }

    /**
     * Display settings page.
     */
    public function display_settings_page() {
        if (!$this->check_capability('manage_system_settings')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        include SMS_PLUGIN_DIR . 'admin/partials/settings.php';
    }

    /**
     * Display admin notices.
     */
    public function display_admin_notices() {
        // Check for plugin activation notice
        if (get_option('sms_plugin_activated')) {
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p>' . __('School Management System has been activated successfully!', 'school-management-system') . '</p>';
            echo '</div>';
            delete_option('sms_plugin_activated');
        }

        // Check for required plugins
        $this->check_required_plugins();
    }

    /**
     * Check for required plugins.
     */
    private function check_required_plugins() {
        $required_plugins = array(
            'advanced-custom-fields-pro/acf.php' => 'Advanced Custom Fields Pro',
            'user-role-editor/user-role-editor.php' => 'User Role Editor'
        );

        $missing_plugins = array();
        
        foreach ($required_plugins as $plugin_file => $plugin_name) {
            if (!is_plugin_active($plugin_file)) {
                $missing_plugins[] = $plugin_name;
            }
        }

        if (!empty($missing_plugins)) {
            echo '<div class="notice notice-warning">';
            echo '<p>' . sprintf(
                __('School Management System requires the following plugins to be installed and activated: %s', 'school-management-system'),
                implode(', ', $missing_plugins)
            ) . '</p>';
            echo '</div>';
        }
    }
}