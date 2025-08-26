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
        
        // Enqueue dashboard styles
        wp_enqueue_style(
            $this->plugin_name . '-dashboard',
            SMS_PLUGIN_URL . 'admin/css/dashboard-styles.css',
            array(),
            $this->version,
            'all'
        );
        
        // Enqueue responsive table styles
        wp_enqueue_style(
            $this->plugin_name . '-responsive-tables',
            SMS_PLUGIN_URL . 'admin/css/responsive-tables.css',
            array(),
            $this->version,
            'all'
        );
        
        // Enqueue dashboard fix styles
        wp_enqueue_style(
            $this->plugin_name . '-dashboard-fix',
            SMS_PLUGIN_URL . 'admin/css/dashboard-fix.css',
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
        
        // Enqueue dashboard functionality
        wp_enqueue_script(
            $this->plugin_name . '-dashboard',
            SMS_PLUGIN_URL . 'admin/js/dashboard-functionality.js',
            array('jquery'),
            $this->version,
            false
        );
        
        // Enqueue responsive table functionality
        wp_enqueue_script(
            $this->plugin_name . '-responsive-tables',
            SMS_PLUGIN_URL . 'admin/js/responsive-tables.js',
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
                'admin_url' => admin_url(),
                'nonce' => wp_create_nonce('sms_admin_nonce'),
                'strings' => array(
                    'confirm_delete' => __('Are you sure you want to delete this item?', 'school-management-system'),
                    'processing' => __('Processing...', 'school-management-system'),
                    'error' => __('An error occurred. Please try again.', 'school-management-system'),
                    'no_activity' => __('No recent activity found.', 'school-management-system'),
                    'no_notifications' => __('No notifications at this time.', 'school-management-system'),
                    'no_items_selected' => __('Please select items and choose an action.', 'school-management-system'),
                    'confirm_bulk_action' => __('Are you sure you want to perform this action on the selected items?', 'school-management-system')
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
            'read', // Changed from 'edit_posts' to 'read' for broader access
            'school-management',
            array($this, 'redirect_to_appropriate_dashboard'),
            'dashicons-graduation-cap',
            30
        );

        // Note: Dashboard submenu is handled by SMS_Dashboard_Manager for role-specific dashboards

        // Students submenu
        add_submenu_page(
            'school-management',
            __('Students', 'school-management-system'),
            __('Students', 'school-management-system'),
            'view_student_records', // Use custom capability for teachers
            'sms-students',
            array($this, 'display_students_page')
        );

        // Classes submenu
        add_submenu_page(
            'school-management',
            __('Classes', 'school-management-system'),
            __('Classes', 'school-management-system'),
            'edit_assigned_classes', // Use custom capability for teachers
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

        // Notices submenu
        add_submenu_page(
            'school-management',
            __('Notices', 'school-management-system'),
            __('Notices', 'school-management-system'),
            'manage_notices',
            'sms-notices',
            array($this, 'display_notices_page')
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

        // Payment Gateways submenu
        add_submenu_page(
            'school-management',
            __('Payment Gateways', 'school-management-system'),
            __('Payment Gateways', 'school-management-system'),
            'manage_options',
            'sms-payment-gateways',
            array($this, 'display_payment_gateways_page')
        );

        // SMS Communication submenu
        add_submenu_page(
            'school-management',
            __('SMS Communication', 'school-management-system'),
            __('SMS Communication', 'school-management-system'),
            'manage_options',
            'sms-communication',
            array($this, 'display_sms_communication_page')
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

        // Role Tester submenu (for administrators only)
        if (current_user_can('manage_options') && class_exists('SMS_Role_Tester')) {
            add_submenu_page(
                'school-management',
                __('Role Tester', 'school-management-system'),
                __('Role Tester', 'school-management-system'),
                'manage_options',
                'sms-role-tester',
                array($this, 'display_role_tester_page')
            );
        }
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
     * Redirect to appropriate dashboard based on user role.
     */
    public function redirect_to_appropriate_dashboard() {
        $current_user = wp_get_current_user();
        $user_roles = $current_user->roles;
        
        // Redirect based on user role
        if (in_array('sms_teacher', $user_roles)) {
            wp_redirect(admin_url('admin.php?page=sms-teacher-dashboard'));
            exit;
        } elseif (in_array('sms_parent', $user_roles)) {
            wp_redirect(admin_url('admin.php?page=sms-parent-dashboard'));
            exit;
        } elseif (in_array('sms_student', $user_roles)) {
            wp_redirect(admin_url('admin.php?page=sms-student-dashboard'));
            exit;
        } elseif (in_array('administrator', $user_roles) || in_array('sms_admin', $user_roles)) {
            wp_redirect(admin_url('admin.php?page=sms-admin-dashboard'));
            exit;
        }
        
        // Fallback: show message for users without specific roles
        echo '<div class="wrap">';
        echo '<h1>' . __('School Management System', 'school-management-system') . '</h1>';
        echo '<div class="notice notice-info">';
        echo '<p>' . __('Please contact your administrator to assign you an appropriate role to access the dashboard.', 'school-management-system') . '</p>';
        echo '</div>';
        echo '</div>';
    }

    /**
     * Display students page.
     */
    public function display_students_page() {
        // Allow both admin capabilities and teacher capabilities
        if (!$this->check_capability('manage_students') && !$this->check_capability('view_student_records')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        include SMS_PLUGIN_DIR . 'admin/partials/students.php';
    }

    /**
     * Display classes page.
     */
    public function display_classes_page() {
        // Allow both admin capabilities and teacher capabilities
        if (!$this->check_capability('manage_classes') && !$this->check_capability('edit_assigned_classes')) {
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
     * Display notices page.
     */
    public function display_notices_page() {
        if (!$this->check_capability('manage_notices')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        // Initialize notice manager if not already done
        if (!class_exists('SMS_Notice_Manager')) {
            require_once SMS_PLUGIN_DIR . 'includes/admin/class-sms-notice-manager.php';
        }
        
        $notice_manager = new SMS_Notice_Manager();
        $notice_manager->display_notices_page();
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
     * Display payment gateways page.
     */
    public function display_payment_gateways_page() {
        if (!$this->check_capability('manage_system_settings')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        include SMS_PLUGIN_DIR . 'admin/partials/payment-gateway-config.php';
    }

    /**
     * Display SMS communication page.
     */
    public function display_sms_communication_page() {
        if (!$this->check_capability('manage_system_settings')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        include SMS_PLUGIN_DIR . 'admin/partials/sms-settings.php';
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
     * Display role tester page.
     */
    public function display_role_tester_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        if (class_exists('SMS_Role_Tester')) {
            $role_tester = new SMS_Role_Tester();
            $role_tester->display_role_tester_page();
        } else {
            echo '<div class="wrap"><h1>Role Tester</h1><p>Role Tester class not found.</p></div>';
        }
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