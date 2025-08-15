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
        
        // Admin functionality
        require_once SMS_PLUGIN_DIR . 'includes/admin/class-sms-admin.php';
        
        // Public functionality
        require_once SMS_PLUGIN_DIR . 'includes/public/class-sms-public.php';
        
        // Custom post types (will be created in subsequent tasks)
        $this->load_post_types();
        
        // Taxonomies (will be created in subsequent tasks)
        $this->load_taxonomies();
        
        // User roles
        require_once SMS_PLUGIN_DIR . 'includes/user-roles/class-sms-user-roles.php';
        
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
        $this->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');
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