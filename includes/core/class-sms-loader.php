<?php
/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * @package    School_Management_System
 * @subpackage School_Management_System/includes/core
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * The core plugin class.
 */
class SMS_Loader {

    /**
     * The array of actions registered with WordPress.
     */
    protected $actions;

    /**
     * The array of filters registered with WordPress.
     */
    protected $filters;

    /**
     * The current version of the plugin.
     */
    protected $version;

    /**
     * The unique identifier of this plugin.
     */
    protected $plugin_name;

    /**
     * Define the core functionality of the plugin.
     */
    public function __construct() {
        if (defined('SMS_VERSION')) {
            $this->version = SMS_VERSION;
        } else {
            $this->version = '1.0.0';
        }
        
        $this->plugin_name = 'school-management-system';
        $this->actions = array();
        $this->filters = array();

        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        $this->define_custom_post_types();
        $this->define_taxonomies();
        $this->define_student_management();
        $this->define_transport_management();
        $this->define_payment_gateways();
        $this->define_api_hooks();
    }

    /**
     * Load the required dependencies for this plugin.
     */
    private function load_dependencies() {
        // Core functionality
        require_once SMS_PLUGIN_DIR . 'includes/core/class-sms-i18n.php';
        require_once SMS_PLUGIN_DIR . 'includes/core/class-sms-hook-manager.php';
        
        // Base classes
        require_once SMS_PLUGIN_DIR . 'includes/core/class-sms-base.php';
        require_once SMS_PLUGIN_DIR . 'includes/core/class-sms-security.php';
        require_once SMS_PLUGIN_DIR . 'includes/core/class-sms-logger.php';
        require_once SMS_PLUGIN_DIR . 'includes/core/class-sms-database-setup.php';
        require_once SMS_PLUGIN_DIR . 'includes/core/class-sms-data-migrator.php';
        require_once SMS_PLUGIN_DIR . 'includes/core/class-sms-data-validator.php';
        require_once SMS_PLUGIN_DIR . 'includes/core/class-sms-backup-manager.php';
        require_once SMS_PLUGIN_DIR . 'includes/core/class-sms-communication-handler.php';
        
        // Financial management and payment gateways (load in dependency order)
        require_once SMS_PLUGIN_DIR . 'includes/financial/class-sms-payment-gateway-base.php';
        require_once SMS_PLUGIN_DIR . 'includes/financial/class-sms-gateway-config-manager.php';
        require_once SMS_PLUGIN_DIR . 'includes/financial/class-sms-payment-gateway-manager.php';
        require_once SMS_PLUGIN_DIR . 'includes/financial/class-sms-gateway-selector.php';
        require_once SMS_PLUGIN_DIR . 'includes/financial/class-sms-mpesa-gateway.php';
        require_once SMS_PLUGIN_DIR . 'includes/financial/class-sms-airtel-money-gateway.php';
        require_once SMS_PLUGIN_DIR . 'includes/financial/class-sms-payment-gateway-init.php';
        require_once SMS_PLUGIN_DIR . 'includes/financial/class-sms-fee-manager.php';
        require_once SMS_PLUGIN_DIR . 'includes/financial/class-sms-fee-category-manager.php';
        require_once SMS_PLUGIN_DIR . 'includes/financial/class-sms-fee-exemption-manager.php';
        require_once SMS_PLUGIN_DIR . 'includes/financial/class-sms-invoice-generator.php';
        require_once SMS_PLUGIN_DIR . 'includes/financial/class-sms-invoice-template-manager.php';
        require_once SMS_PLUGIN_DIR . 'includes/financial/class-sms-invoice-status-tracker.php';
        require_once SMS_PLUGIN_DIR . 'includes/financial/class-sms-transaction-manager.php';
        require_once SMS_PLUGIN_DIR . 'includes/financial/class-sms-receipt-generator.php';
        require_once SMS_PLUGIN_DIR . 'includes/financial/class-sms-transaction-status-tracker.php';
        require_once SMS_PLUGIN_DIR . 'includes/financial/class-sms-transaction-integration.php';
        require_once SMS_PLUGIN_DIR . 'includes/financial/class-sms-payment-processor.php';
        require_once SMS_PLUGIN_DIR . 'includes/financial/class-sms-payment-history-tracker.php';
        require_once SMS_PLUGIN_DIR . 'includes/financial/class-sms-payment-reminder-scheduler.php';

        // Admin functionality (load after financial classes)
        require_once SMS_PLUGIN_DIR . 'includes/admin/class-sms-admin.php';
        require_once SMS_PLUGIN_DIR . 'includes/admin/class-sms-dashboard-manager.php';
        require_once SMS_PLUGIN_DIR . 'includes/admin/class-sms-dashboard-redirects.php';
        require_once SMS_PLUGIN_DIR . 'includes/admin/class-sms-help-system.php';
        require_once SMS_PLUGIN_DIR . 'includes/admin/class-sms-role-tester.php';
        require_once SMS_PLUGIN_DIR . 'includes/admin/class-sms-student-admission-form.php';
        require_once SMS_PLUGIN_DIR . 'includes/admin/class-sms-attendance-manager.php';
        require_once SMS_PLUGIN_DIR . 'includes/admin/class-sms-attendance-reports.php';
        require_once SMS_PLUGIN_DIR . 'includes/admin/class-sms-attendance-notifications.php';
        require_once SMS_PLUGIN_DIR . 'includes/admin/class-sms-timetable-builder.php';
        require_once SMS_PLUGIN_DIR . 'includes/admin/class-sms-timetable-conflict-detector.php';
        require_once SMS_PLUGIN_DIR . 'includes/admin/class-sms-timetable-bulk-operations.php';
        require_once SMS_PLUGIN_DIR . 'includes/admin/class-sms-timetable-display-export.php';
        require_once SMS_PLUGIN_DIR . 'includes/admin/class-sms-bulk-invoice-handler.php';
        require_once SMS_PLUGIN_DIR . 'includes/admin/class-sms-mpesa-admin.php';
        require_once SMS_PLUGIN_DIR . 'includes/admin/class-sms-airtel-money-admin.php';
        require_once SMS_PLUGIN_DIR . 'includes/admin/class-sms-transaction-admin.php';
        require_once SMS_PLUGIN_DIR . 'includes/admin/class-sms-notice-manager.php';
        require_once SMS_PLUGIN_DIR . 'includes/admin/class-sms-notice-attachments.php';
        
        // SMS Integration classes
        require_once SMS_PLUGIN_DIR . 'includes/integrations/class-sms-africastalking-api.php';
        require_once SMS_PLUGIN_DIR . 'includes/integrations/class-sms-template-manager.php';
        require_once SMS_PLUGIN_DIR . 'includes/integrations/class-sms-queue-manager.php';
        require_once SMS_PLUGIN_DIR . 'includes/integrations/class-sms-notification-scheduler.php';
        require_once SMS_PLUGIN_DIR . 'includes/integrations/class-sms-transport-notifications.php';
        
        // Public functionality
        require_once SMS_PLUGIN_DIR . 'includes/public/class-sms-public.php';
        
        // Custom post types (will be created in subsequent tasks)
        $this->load_post_types();
        
        // Taxonomies (will be created in subsequent tasks)
        $this->load_taxonomies();
        
        // User roles
        require_once SMS_PLUGIN_DIR . 'includes/user-roles/class-sms-user-roles.php';
        require_once SMS_PLUGIN_DIR . 'includes/user-roles/class-sms-role-manager.php';
        require_once SMS_PLUGIN_DIR . 'includes/user-roles/class-sms-capability-checker.php';
        require_once SMS_PLUGIN_DIR . 'includes/user-roles/class-sms-role-automation.php';
        require_once SMS_PLUGIN_DIR . 'includes/user-roles/class-sms-parent-student-manager.php';
        require_once SMS_PLUGIN_DIR . 'includes/user-roles/class-sms-parent-access-control.php';
        require_once SMS_PLUGIN_DIR . 'includes/user-roles/class-sms-bulk-parent-operations.php';
        
        // Student management functionality
        require_once SMS_PLUGIN_DIR . 'includes/core/class-sms-student-manager.php';
        require_once SMS_PLUGIN_DIR . 'includes/core/class-sms-student-enrollment.php';
        
        // Transport management functionality
        require_once SMS_PLUGIN_DIR . 'includes/core/class-sms-route-manager.php';
        require_once SMS_PLUGIN_DIR . 'includes/core/class-sms-bus-manager.php';
        require_once SMS_PLUGIN_DIR . 'includes/core/class-sms-transport-assigner.php';
        require_once SMS_PLUGIN_DIR . 'includes/admin/class-sms-transport-admin.php';
        
        // API endpoints
        require_once SMS_PLUGIN_DIR . 'includes/api/class-sms-api.php';
    }

    /**
     * Load custom post type classes.
     */
    private function load_post_types() {
        $post_types = array(
            'students', 'classes', 'fees', 'invoices', 'transactions',
            'attendance', 'timetables', 'notices', 'transport-routes'
        );

        foreach ($post_types as $post_type) {
            $file_path = SMS_PLUGIN_DIR . "includes/post-types/class-sms-{$post_type}-cpt.php";
            if (file_exists($file_path)) {
                require_once $file_path;
            }
        }
    }

    /**
     * Load taxonomy classes.
     */
    private function load_taxonomies() {
        $taxonomies = array(
            'subjects', 'grades', 'academic-years', 'terms',
            'fee-types', 'transaction-types', 'notice-types'
        );

        foreach ($taxonomies as $taxonomy) {
            $file_path = SMS_PLUGIN_DIR . "includes/taxonomies/class-sms-{$taxonomy}-taxonomy.php";
            if (file_exists($file_path)) {
                require_once $file_path;
            }
        }
    }

    /**
     * Define the locale for this plugin for internationalization.
     */
    private function set_locale() {
        $plugin_i18n = new SMS_i18n();
        // Load textdomain on init hook to avoid early loading warnings
        $this->add_action('init', $plugin_i18n, 'load_plugin_textdomain', 1);
    }

    /**
     * Register all of the hooks related to the admin area functionality.
     */
    private function define_admin_hooks() {
        $plugin_admin = new SMS_Admin($this->get_plugin_name(), $this->get_version());

        $this->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
        $this->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');
        $this->add_action('admin_menu', $plugin_admin, 'add_admin_menu');
        $this->add_action('admin_init', $plugin_admin, 'admin_init');
        
        // Initialize student admission form
        if (class_exists('SMS_Student_Admission_Form')) {
            new SMS_Student_Admission_Form();
        }
        
        // Initialize attendance manager
        if (class_exists('SMS_Attendance_Manager')) {
            new SMS_Attendance_Manager();
        }
        
        // Initialize attendance reports
        if (class_exists('SMS_Attendance_Reports')) {
            new SMS_Attendance_Reports();
        }
        
        // Initialize attendance notifications
        if (class_exists('SMS_Attendance_Notifications')) {
            new SMS_Attendance_Notifications();
        }
        
        // Initialize dashboard manager
        if (class_exists('SMS_Dashboard_Manager')) {
            SMS_Dashboard_Manager::get_instance();
        }
        
        // Initialize dashboard redirects
        if (class_exists('SMS_Dashboard_Redirects')) {
            new SMS_Dashboard_Redirects();
        }

        // Initialize help system
        if (class_exists('SMS_Help_System')) {
            new SMS_Help_System();
        }
        
        // Initialize user roles
        if (class_exists('SMS_User_Roles')) {
            new SMS_User_Roles();
        }
        
        // Initialize role tester (admin only)
        if (class_exists('SMS_Role_Tester') && is_admin()) {
            new SMS_Role_Tester();
        }
        
        // Initialize database setup
        if (class_exists('SMS_Database_Setup')) {
            new SMS_Database_Setup();
        }
    }

    /**
     * Register all of the hooks related to the public-facing functionality.
     */
    private function define_public_hooks() {
        $plugin_public = new SMS_Public($this->get_plugin_name(), $this->get_version());

        $this->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_styles');
        $this->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_scripts');
    }

    /**
     * Register custom post types.
     */
    private function define_custom_post_types() {
        $this->add_action('init', $this, 'register_custom_post_types');
    }

    /**
     * Register custom taxonomies.
     */
    private function define_taxonomies() {
        $this->add_action('init', $this, 'register_custom_taxonomies');
    }

    /**
     * Initialize student management functionality.
     */
    private function define_student_management() {
        // Initialize student manager
        if (class_exists('SMS_Student_Manager')) {
            new SMS_Student_Manager();
        }
        
        // Initialize student enrollment manager
        if (class_exists('SMS_Student_Enrollment')) {
            new SMS_Student_Enrollment();
        }
    }
    
    /**
     * Initialize transport management functionality.
     */
    private function define_transport_management() {
        // Initialize route manager
        if (class_exists('SMS_Route_Manager')) {
            new SMS_Route_Manager();
        }
        
        // Initialize bus manager
        if (class_exists('SMS_Bus_Manager')) {
            new SMS_Bus_Manager();
        }
        
        // Initialize transport assigner
        if (class_exists('SMS_Transport_Assigner')) {
            new SMS_Transport_Assigner();
        }
        
        // Initialize transport admin
        if (class_exists('SMS_Transport_Admin') && is_admin()) {
            new SMS_Transport_Admin();
        }
        
        // Initialize transport notifications
        if (class_exists('SMS_Transport_Notifications')) {
            new SMS_Transport_Notifications();
        }
    }
    
    /**
     * Initialize payment gateway system.
     */
    private function define_payment_gateways() {
        // Initialize payment gateway system
        if (class_exists('SMS_Payment_Gateway_Init')) {
            SMS_Payment_Gateway_Init::get_instance();
        }
        
        // Initialize transaction management system
        if (class_exists('SMS_Transaction_Manager')) {
            SMS_Transaction_Manager::get_instance();
        }
        
        // Initialize transaction status tracker
        if (class_exists('SMS_Transaction_Status_Tracker')) {
            SMS_Transaction_Status_Tracker::get_instance();
        }
        
        // Initialize transaction integration
        if (class_exists('SMS_Transaction_Integration')) {
            SMS_Transaction_Integration::get_instance();
        }
    }

    /**
     * Register API endpoints.
     */
    private function define_api_hooks() {
        $plugin_api = new SMS_API();
        $this->add_action('rest_api_init', $plugin_api, 'register_routes');
    }

    /**
     * Add a new action to the collection to be registered with WordPress.
     */
    public function add_action($hook, $component, $callback, $priority = 10, $accepted_args = 1) {
        $this->actions = $this->add($this->actions, $hook, $component, $callback, $priority, $accepted_args);
    }

    /**
     * Add a new filter to the collection to be registered with WordPress.
     */
    public function add_filter($hook, $component, $callback, $priority = 10, $accepted_args = 1) {
        $this->filters = $this->add($this->filters, $hook, $component, $callback, $priority, $accepted_args);
    }

    /**
     * A utility function that is used to register the actions and hooks into a single
     * collection.
     */
    private function add($hooks, $hook, $component, $callback, $priority, $accepted_args) {
        $hooks[] = array(
            'hook'          => $hook,
            'component'     => $component,
            'callback'      => $callback,
            'priority'      => $priority,
            'accepted_args' => $accepted_args
        );

        return $hooks;
    }

    /**
     * Register custom post types callback.
     */
    public function register_custom_post_types() {
        // Initialize post type classes if they exist
        $post_type_classes = [
            'SMS_Students_CPT',
            'SMS_Classes_CPT',
            'SMS_Fees_CPT',
            'SMS_Invoices_CPT',
            'SMS_Transactions_CPT',
            'SMS_Attendance_CPT',
            'SMS_Timetables_CPT',
            'SMS_Notices_CPT',
            'SMS_Transport_Routes_CPT'
        ];

        foreach ($post_type_classes as $class_name) {
            if (class_exists($class_name)) {
                // Class is already instantiated in its file
                continue;
            }
        }
        
        // Fire action for any additional post types
        do_action('sms_register_post_types');
    }

    /**
     * Register custom taxonomies callback.
     */
    public function register_custom_taxonomies() {
        // Initialize taxonomy classes if they exist
        $taxonomy_classes = [
            'SMS_Subjects_Taxonomy',
            'SMS_Grades_Taxonomy',
            'SMS_Academic_Years_Taxonomy',
            'SMS_Terms_Taxonomy'
        ];

        foreach ($taxonomy_classes as $class_name) {
            if (class_exists($class_name)) {
                // Class is already instantiated in its file
                continue;
            }
        }
        
        // Fire action for any additional taxonomies
        do_action('sms_register_taxonomies');
    }

    /**
     * Run the loader to execute all of the hooks with WordPress.
     */
    public function run() {
        foreach ($this->filters as $hook) {
            add_filter($hook['hook'], array($hook['component'], $hook['callback']), $hook['priority'], $hook['accepted_args']);
        }

        foreach ($this->actions as $hook) {
            add_action($hook['hook'], array($hook['component'], $hook['callback']), $hook['priority'], $hook['accepted_args']);
        }
    }

    /**
     * The name of the plugin used to uniquely identify it within the context of
     * WordPress and to define internationalization functionality.
     */
    public function get_plugin_name() {
        return $this->plugin_name;
    }

    /**
     * Retrieve the version number of the plugin.
     */
    public function get_version() {
        return $this->version;
    }
}