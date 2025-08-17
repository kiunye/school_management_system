<?php
/**
 * Transport Notifications Handler
 *
 * @package SchoolManagementSystem
 * @subpackage Integrations
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class SMS_Transport_Notifications
 * 
 * Handles SMS notifications for transport-related events
 */
class SMS_Transport_Notifications {

    /**
     * SMS Template Manager instance
     *
     * @var SMS_Template_Manager
     */
    private $template_manager;

    /**
     * SMS Queue Manager instance
     *
     * @var SMS_Queue_Manager
     */
    private $queue_manager;

    /**
     * Constructor
     */
    public function __construct() {
        $this->template_manager = new SMS_Template_Manager();
        $this->queue_manager = new SMS_Queue_Manager();
        
        add_action('init', array($this, 'init'));
    }

    /**
     * Initialize transport notifications
     */
    public function init() {
        // Hook into transport events
        add_action('sms_send_transport_assignment_notification', array($this, 'send_assignment_notification'));
        add_action('sms_send_transport_unassignment_notification', array($this, 'send_unassignment_notification'));
        add_action('sms_send_route_change_notification', array($this, 'send_route_change_notification'));
        
        // Hook into maintenance alerts
        add_action('sms_maintenance_alerts_found', array($this, 'send_maintenance_alerts'));
        add_action('sms_license_alerts_found', array($this, 'send_license_alerts'));
        
        // Register transport SMS templates
        add_action('sms_register_templates', array($this, 'register_transport_templates'));
    }

    /**
     * Send transport assignment notification
     *
     * @param array $data Assignment notification data
     */
    public function send_assignment_notification($data) {
        if (empty($data['parent_phone'])) {
            return;
        }

        $template_variables = array(
            'student_name' => $data['student_name'],
            'route_name' => $data['route_name'],
            'pickup_stop' => $data['pickup_stop'],
            'pickup_time' => $data['pickup_time'],
            'transport_fee' => 'KES ' . number_format($data['transport_fee'], 2),
            'school_name' => get_bloginfo('name')
        );

        $message = $this->template_manager->render_template('transport_assignment', $template_variables);
        
        if (!empty($message)) {
            $this->queue_manager->queue_sms(
                array($data['parent_phone']),
                $message,
                'transport_assignment',
                'normal'
            );
        }
    }

    /**
     * Send transport unassignment notification
     *
     * @param array $data Unassignment notification data
     */
    public function send_unassignment_notification($data) {
        if (empty($data['parent_phone'])) {
            return;
        }

        $template_variables = array(
            'student_name' => $data['student_name'],
            'route_name' => $data['route_name'],
            'school_name' => get_bloginfo('name')
        );

        $message = $this->template_manager->render_template('transport_unassignment', $template_variables);
        
        if (!empty($message)) {
            $this->queue_manager->queue_sms(
                array($data['parent_phone']),
                $message,
                'transport_unassignment',
                'normal'
            );
        }
    }

    /**
     * Send route change notification
     *
     * @param array $data Route change notification data
     */
    public function send_route_change_notification($data) {
        if (empty($data['parent_phone'])) {
            return;
        }

        // Determine what changed
        $changes_text = $this->format_route_changes($data['changes']);

        $template_variables = array(
            'student_name' => $data['student_name'],
            'route_name' => $data['route_name'],
            'changes' => $changes_text,
            'school_name' => get_bloginfo('name')
        );

        $message = $this->template_manager->render_template('route_change', $template_variables);
        
        if (!empty($message)) {
            $this->queue_manager->queue_sms(
                array($data['parent_phone']),
                $message,
                'route_change',
                'high'
            );
        }
    }

    /**
     * Send maintenance alerts to administrators
     *
     * @param array $alerts Maintenance alerts
     */
    public function send_maintenance_alerts($alerts) {
        $admin_phones = $this->get_admin_phone_numbers();
        
        if (empty($admin_phones)) {
            return;
        }

        foreach ($alerts as $alert) {
            $template_variables = array(
                'alert_type' => $this->format_alert_type($alert['type']),
                'route_name' => $alert['route_name'],
                'vehicle_registration' => $alert['vehicle_registration'] ?? 'N/A',
                'days_remaining' => $alert['days_remaining'] ?? 0,
                'priority' => strtoupper($alert['priority']),
                'school_name' => get_bloginfo('name')
            );

            $message = $this->template_manager->render_template('maintenance_alert', $template_variables);
            
            if (!empty($message)) {
                $priority = $alert['priority'] === 'high' ? 'high' : 'normal';
                
                $this->queue_manager->queue_sms(
                    $admin_phones,
                    $message,
                    'maintenance_alert',
                    $priority
                );
            }
        }
    }

    /**
     * Send license alerts to administrators
     *
     * @param array $alerts License alerts
     */
    public function send_license_alerts($alerts) {
        $admin_phones = $this->get_admin_phone_numbers();
        
        if (empty($admin_phones)) {
            return;
        }

        foreach ($alerts as $alert) {
            $template_variables = array(
                'driver_name' => $alert['driver_name'],
                'route_name' => $alert['route_name'],
                'license_number' => $alert['license_number'],
                'days_remaining' => $alert['days_remaining'],
                'expiry_date' => date('d/m/Y', strtotime($alert['expiry_date'])),
                'priority' => strtoupper($alert['priority']),
                'school_name' => get_bloginfo('name')
            );

            $message = $this->template_manager->render_template('license_alert', $template_variables);
            
            if (!empty($message)) {
                $priority = $alert['priority'] === 'high' ? 'high' : 'normal';
                
                $this->queue_manager->queue_sms(
                    $admin_phones,
                    $message,
                    'license_alert',
                    $priority
                );
            }
        }
    }

    /**
     * Register transport SMS templates
     */
    public function register_transport_templates() {
        // Transport assignment template
        $this->template_manager->register_template(
            'transport_assignment',
            __('Transport Assignment', 'school-management-system'),
            __('Dear parent, {student_name} has been assigned to transport route {route_name}. Pickup stop: {pickup_stop} at {pickup_time}. Monthly fee: {transport_fee}. From {school_name}', 'school-management-system'),
            array('student_name', 'route_name', 'pickup_stop', 'pickup_time', 'transport_fee', 'school_name')
        );

        // Transport unassignment template
        $this->template_manager->register_template(
            'transport_unassignment',
            __('Transport Unassignment', 'school-management-system'),
            __('Dear parent, {student_name} has been removed from transport route {route_name}. Please make alternative transport arrangements. From {school_name}', 'school-management-system'),
            array('student_name', 'route_name', 'school_name')
        );

        // Route change template
        $this->template_manager->register_template(
            'route_change',
            __('Route Change Notification', 'school-management-system'),
            __('Dear parent, transport route {route_name} for {student_name} has been updated. Changes: {changes}. From {school_name}', 'school-management-system'),
            array('student_name', 'route_name', 'changes', 'school_name')
        );

        // Maintenance alert template
        $this->template_manager->register_template(
            'maintenance_alert',
            __('Vehicle Maintenance Alert', 'school-management-system'),
            __('[{priority}] {alert_type} for route {route_name} (Vehicle: {vehicle_registration}). Days remaining: {days_remaining}. Action required. - {school_name}', 'school-management-system'),
            array('alert_type', 'route_name', 'vehicle_registration', 'days_remaining', 'priority', 'school_name')
        );

        // License alert template
        $this->template_manager->register_template(
            'license_alert',
            __('Driver License Alert', 'school-management-system'),
            __('[{priority}] Driver {driver_name} (Route: {route_name}) license {license_number} expires in {days_remaining} days ({expiry_date}). Renewal required. - {school_name}', 'school-management-system'),
            array('driver_name', 'route_name', 'license_number', 'days_remaining', 'expiry_date', 'priority', 'school_name')
        );
    }

    /**
     * Format route changes for notification
     *
     * @param array $changes Route changes data
     * @return string Formatted changes text
     */
    private function format_route_changes($changes) {
        $change_items = array();

        // Check for timing changes
        if (!empty($changes['route_stops'])) {
            $change_items[] = __('pickup times updated', 'school-management-system');
        }

        // Check for route status changes
        if (!empty($changes['route_status'])) {
            $change_items[] = sprintf(__('status changed to %s', 'school-management-system'), $changes['route_status']);
        }

        // Check for vehicle changes
        if (!empty($changes['vehicle_details'])) {
            $change_items[] = __('vehicle information updated', 'school-management-system');
        }

        // Check for driver changes
        if (!empty($changes['driver_details'])) {
            $change_items[] = __('driver information updated', 'school-management-system');
        }

        // Check for fee changes
        if (!empty($changes['base_fee']) || !empty($changes['fee_structure_type'])) {
            $change_items[] = __('fee structure updated', 'school-management-system');
        }

        if (empty($change_items)) {
            return __('route information updated', 'school-management-system');
        }

        return implode(', ', $change_items);
    }

    /**
     * Format alert type for display
     *
     * @param string $alert_type Alert type
     * @return string Formatted alert type
     */
    private function format_alert_type($alert_type) {
        $alert_types = array(
            'insurance_expiry' => __('Insurance Expiry', 'school-management-system'),
            'inspection_expiry' => __('Inspection Expiry', 'school-management-system'),
            'vehicle_condition' => __('Vehicle Condition Alert', 'school-management-system'),
            'license_expiry' => __('License Expiry', 'school-management-system')
        );

        return $alert_types[$alert_type] ?? ucfirst(str_replace('_', ' ', $alert_type));
    }

    /**
     * Get administrator phone numbers for alerts
     *
     * @return array List of admin phone numbers
     */
    private function get_admin_phone_numbers() {
        $admin_phones = array();

        // Get users with transport management capabilities
        $admins = get_users(array(
            'capability' => 'manage_transport',
            'fields' => 'ID'
        ));

        foreach ($admins as $admin_id) {
            $phone = get_user_meta($admin_id, 'phone_number', true);
            if (!empty($phone) && $this->is_valid_phone_number($phone)) {
                $admin_phones[] = $phone;
            }
        }

        // Fallback to site admin phone if configured
        $site_admin_phone = get_option('sms_admin_phone_number');
        if (!empty($site_admin_phone) && $this->is_valid_phone_number($site_admin_phone)) {
            $admin_phones[] = $site_admin_phone;
        }

        return array_unique($admin_phones);
    }

    /**
     * Validate phone number format
     *
     * @param string $phone Phone number
     * @return bool True if valid, false otherwise
     */
    private function is_valid_phone_number($phone) {
        // Basic Kenyan phone number validation
        $patterns = array(
            '/^\+254[17]\d{8}$/',  // +254 format
            '/^254[17]\d{8}$/',    // 254 format
            '/^0[17]\d{8}$/'       // 0 format
        );

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $phone)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Send bulk transport fee reminders
     *
     * @param array $students Students with overdue transport fees
     */
    public function send_transport_fee_reminders($students) {
        foreach ($students as $student) {
            if (empty($student['parent_phone'])) {
                continue;
            }

            $template_variables = array(
                'student_name' => $student['student_name'],
                'route_name' => $student['route_name'],
                'amount_due' => 'KES ' . number_format($student['amount_due'], 2),
                'due_date' => date('d/m/Y', strtotime($student['due_date'])),
                'days_overdue' => $student['days_overdue'],
                'school_name' => get_bloginfo('name')
            );

            $message = $this->template_manager->render_template('transport_fee_reminder', $template_variables);
            
            if (!empty($message)) {
                $priority = $student['days_overdue'] > 30 ? 'high' : 'normal';
                
                $this->queue_manager->queue_sms(
                    array($student['parent_phone']),
                    $message,
                    'transport_fee_reminder',
                    $priority
                );
            }
        }
    }

    /**
     * Send route capacity alerts to administrators
     *
     * @param array $routes Routes approaching capacity
     */
    public function send_capacity_alerts($routes) {
        $admin_phones = $this->get_admin_phone_numbers();
        
        if (empty($admin_phones)) {
            return;
        }

        foreach ($routes as $route) {
            $template_variables = array(
                'route_name' => $route['route_name'],
                'current_enrollment' => $route['current_enrollment'],
                'total_capacity' => $route['total_capacity'],
                'utilization_percentage' => round(($route['current_enrollment'] / $route['total_capacity']) * 100, 1),
                'school_name' => get_bloginfo('name')
            );

            $message = $this->template_manager->render_template('capacity_alert', $template_variables);
            
            if (!empty($message)) {
                $this->queue_manager->queue_sms(
                    $admin_phones,
                    $message,
                    'capacity_alert',
                    'normal'
                );
            }
        }
    }
}