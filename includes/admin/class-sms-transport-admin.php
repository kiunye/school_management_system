<?php
/**
 * Transport Administration Class
 *
 * @package SchoolManagementSystem
 * @subpackage Admin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class SMS_Transport_Admin
 * 
 * Handles transport administration interface and functionality
 */
class SMS_Transport_Admin {

    /**
     * Route Manager instance
     *
     * @var SMS_Route_Manager
     */
    private $route_manager;

    /**
     * Bus Manager instance
     *
     * @var SMS_Bus_Manager
     */
    private $bus_manager;

    /**
     * Constructor
     */
    public function __construct() {
        $this->route_manager = new SMS_Route_Manager();
        $this->bus_manager = new SMS_Bus_Manager();
        
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_sms_get_transport_dashboard_data', array($this, 'ajax_get_dashboard_data'));
    }

    /**
     * Add transport management to admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=sms_transport_routes',
            __('Transport Dashboard', 'school-management-system'),
            __('Dashboard', 'school-management-system'),
            'manage_transport',
            'sms-transport-dashboard',
            array($this, 'display_transport_dashboard')
        );

        add_submenu_page(
            'edit.php?post_type=sms_transport_routes',
            __('Route Management', 'school-management-system'),
            __('Route Management', 'school-management-system'),
            'manage_transport',
            'sms-route-management',
            array($this, 'display_route_management')
        );

        add_submenu_page(
            'edit.php?post_type=sms_transport_routes',
            __('Vehicle & Driver Management', 'school-management-system'),
            __('Vehicles & Drivers', 'school-management-system'),
            'manage_transport',
            'sms-vehicle-management',
            array($this, 'display_vehicle_management')
        );

        add_submenu_page(
            'edit.php?post_type=sms_transport_routes',
            __('Student Route Assignment', 'school-management-system'),
            __('Student Assignment', 'school-management-system'),
            'manage_transport',
            'sms-student-route-assignment',
            array($this, 'display_student_assignment')
        );

        add_submenu_page(
            'edit.php?post_type=sms_transport_routes',
            __('Maintenance Alerts', 'school-management-system'),
            __('Maintenance Alerts', 'school-management-system'),
            'manage_transport',
            'sms-maintenance-alerts',
            array($this, 'display_maintenance_alerts')
        );
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on transport pages
        if (strpos($hook, 'sms-transport') === false && 
            strpos($hook, 'sms-route') === false && 
            strpos($hook, 'sms-vehicle') === false && 
            strpos($hook, 'sms-maintenance') === false &&
            strpos($hook, 'sms-student-route') === false) {
            return;
        }

        wp_enqueue_script(
            'sms-transport-admin',
            plugin_dir_url(__FILE__) . '../../admin/js/transport-admin.js',
            array('jquery', 'wp-util'),
            '1.0.0',
            true
        );

        wp_enqueue_style(
            'sms-transport-admin',
            plugin_dir_url(__FILE__) . '../../admin/css/transport-admin.css',
            array(),
            '1.0.0'
        );

        // Localize script
        wp_localize_script('sms-transport-admin', 'smsTransportAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sms_transport_nonce'),
            'strings' => array(
                'confirmDelete' => __('Are you sure you want to delete this route?', 'school-management-system'),
                'routeCreated' => __('Route created successfully.', 'school-management-system'),
                'routeUpdated' => __('Route updated successfully.', 'school-management-system'),
                'routeDeleted' => __('Route deleted successfully.', 'school-management-system'),
                'vehicleUpdated' => __('Vehicle details updated successfully.', 'school-management-system'),
                'driverUpdated' => __('Driver details updated successfully.', 'school-management-system'),
                'error' => __('An error occurred. Please try again.', 'school-management-system'),
            )
        ));
    }

    /**
     * Display transport dashboard
     */
    public function display_transport_dashboard() {
        // Get dashboard statistics
        $stats = $this->get_dashboard_statistics();
        $recent_alerts = $this->get_recent_alerts();
        $active_routes = $this->route_manager->get_active_routes();

        include plugin_dir_path(__FILE__) . '../admin/partials/transport-dashboard.php';
    }

    /**
     * Display route management interface
     */
    public function display_route_management() {
        $routes = $this->get_all_routes_with_details();
        
        include plugin_dir_path(__FILE__) . '../admin/partials/route-management.php';
    }

    /**
     * Display vehicle and driver management interface
     */
    public function display_vehicle_management() {
        $routes_with_vehicles = $this->get_routes_with_vehicle_details();
        
        include plugin_dir_path(__FILE__) . '../admin/partials/vehicle-management.php';
    }

    /**
     * Display student route assignment interface
     */
    public function display_student_assignment() {
        include plugin_dir_path(__FILE__) . '../admin/partials/student-route-assignment.php';
    }

    /**
     * Display maintenance alerts interface
     */
    public function display_maintenance_alerts() {
        $maintenance_alerts = $this->bus_manager->get_maintenance_alerts();
        $license_alerts = $this->bus_manager->get_license_alerts();
        
        include plugin_dir_path(__FILE__) . '../admin/partials/maintenance-alerts.php';
    }

    /**
     * Get dashboard statistics
     *
     * @return array Dashboard statistics
     */
    private function get_dashboard_statistics() {
        // Get total routes
        $total_routes = wp_count_posts('sms_transport_routes')->publish;
        
        // Get active routes
        $active_routes_query = new WP_Query(array(
            'post_type' => 'sms_transport_routes',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => 'route_status',
                    'value' => 'active',
                    'compare' => '='
                )
            )
        ));
        $active_routes = $active_routes_query->found_posts;

        // Get total capacity and enrollment
        $total_capacity = 0;
        $total_enrollment = 0;
        
        $routes = get_posts(array(
            'post_type' => 'sms_transport_routes',
            'post_status' => 'publish',
            'posts_per_page' => -1
        ));

        foreach ($routes as $route) {
            $capacity = get_field('total_capacity', $route->ID);
            $enrollment = get_field('current_enrollment', $route->ID);
            
            $total_capacity += intval($capacity);
            $total_enrollment += intval($enrollment);
        }

        // Get vehicles with issues
        $maintenance_alerts = $this->bus_manager->get_maintenance_alerts();
        $vehicles_with_issues = count(array_unique(array_column($maintenance_alerts, 'route_id')));

        // Get drivers with expiring licenses
        $license_alerts = $this->bus_manager->get_license_alerts();
        $drivers_with_issues = count($license_alerts);

        return array(
            'total_routes' => $total_routes,
            'active_routes' => $active_routes,
            'inactive_routes' => $total_routes - $active_routes,
            'total_capacity' => $total_capacity,
            'total_enrollment' => $total_enrollment,
            'available_capacity' => $total_capacity - $total_enrollment,
            'capacity_utilization' => $total_capacity > 0 ? round(($total_enrollment / $total_capacity) * 100, 1) : 0,
            'vehicles_with_issues' => $vehicles_with_issues,
            'drivers_with_issues' => $drivers_with_issues,
            'total_alerts' => count($maintenance_alerts) + count($license_alerts)
        );
    }

    /**
     * Get recent alerts
     *
     * @return array Recent alerts
     */
    private function get_recent_alerts() {
        $maintenance_alerts = $this->bus_manager->get_maintenance_alerts();
        $license_alerts = $this->bus_manager->get_license_alerts();
        
        $all_alerts = array_merge($maintenance_alerts, $license_alerts);
        
        // Sort by priority and limit to recent alerts
        usort($all_alerts, function($a, $b) {
            $priority_order = array('high' => 3, 'medium' => 2, 'low' => 1);
            $a_priority = $priority_order[$a['priority']] ?? 0;
            $b_priority = $priority_order[$b['priority']] ?? 0;
            
            return $b_priority - $a_priority;
        });

        return array_slice($all_alerts, 0, 10);
    }

    /**
     * Get all routes with details
     *
     * @return array Routes with details
     */
    private function get_all_routes_with_details() {
        $routes = get_posts(array(
            'post_type' => 'sms_transport_routes',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ));

        $routes_with_details = array();

        foreach ($routes as $route) {
            $route_details = $this->route_manager->get_route_details($route->ID);
            if (!is_wp_error($route_details)) {
                $routes_with_details[] = $route_details;
            }
        }

        return $routes_with_details;
    }

    /**
     * Get routes with vehicle details
     *
     * @return array Routes with vehicle information
     */
    private function get_routes_with_vehicle_details() {
        $routes = get_posts(array(
            'post_type' => 'sms_transport_routes',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ));

        $routes_with_vehicles = array();

        foreach ($routes as $route) {
            $vehicle_details = $this->bus_manager->get_vehicle_details($route->ID);
            $driver_details = $this->bus_manager->get_driver_details($route->ID);
            
            $routes_with_vehicles[] = array(
                'id' => $route->ID,
                'name' => $route->post_title,
                'code' => get_field('route_code', $route->ID),
                'status' => get_field('route_status', $route->ID),
                'vehicle_details' => is_wp_error($vehicle_details) ? array() : $vehicle_details,
                'driver_details' => is_wp_error($driver_details) ? array() : $driver_details,
            );
        }

        return $routes_with_vehicles;
    }

    /**
     * AJAX handler for getting dashboard data
     */
    public function ajax_get_dashboard_data() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_transport_nonce')) {
            wp_die(__('Security check failed.', 'school-management-system'));
        }

        // Check permissions
        if (!current_user_can('view_transport')) {
            wp_die(__('You do not have permission to view transport data.', 'school-management-system'));
        }

        $data_type = sanitize_text_field($_POST['data_type']);

        switch ($data_type) {
            case 'statistics':
                $data = $this->get_dashboard_statistics();
                break;
            case 'alerts':
                $data = $this->get_recent_alerts();
                break;
            case 'routes':
                $data = $this->route_manager->get_active_routes();
                break;
            default:
                wp_send_json_error(__('Invalid data type requested.', 'school-management-system'));
                return;
        }

        wp_send_json_success($data);
    }
}