<?php
/**
 * Transport Routes Custom Post Type
 *
 * @package SchoolManagementSystem
 * @subpackage PostTypes
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class SMS_Transport_Routes_CPT
 * 
 * Handles the transport routes custom post type registration and functionality
 */
class SMS_Transport_Routes_CPT {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'register_post_type'));
        add_action('acf/init', array($this, 'register_acf_fields'));
        add_action('wp_ajax_sms_assign_student_to_route', array($this, 'assign_student_to_route'));
        add_action('wp_ajax_sms_calculate_route_fee', array($this, 'calculate_route_fee'));
        add_action('wp_ajax_sms_get_route_capacity', array($this, 'get_route_capacity'));
        add_action('wp_ajax_sms_notify_route_changes', array($this, 'notify_route_changes'));
        add_filter('manage_sms_transport_routes_posts_columns', array($this, 'set_custom_columns'));
        add_action('manage_sms_transport_routes_posts_custom_column', array($this, 'custom_column_content'), 10, 2);
        add_action('save_post_sms_transport_routes', array($this, 'handle_route_save'), 10, 2);
    }

    /**
     * Register the transport routes custom post type
     */
    public function register_post_type() {
        $labels = array(
            'name'                  => _x('Transport Routes', 'Post Type General Name', 'school-management-system'),
            'singular_name'         => _x('Transport Route', 'Post Type Singular Name', 'school-management-system'),
            'menu_name'             => __('Transport Routes', 'school-management-system'),
            'name_admin_bar'        => __('Transport Route', 'school-management-system'),
            'archives'              => __('Transport Route Archives', 'school-management-system'),
            'attributes'            => __('Transport Route Attributes', 'school-management-system'),
            'parent_item_colon'     => __('Parent Transport Route:', 'school-management-system'),
            'all_items'             => __('All Transport Routes', 'school-management-system'),
            'add_new_item'          => __('Add New Transport Route', 'school-management-system'),
            'add_new'               => __('Add New', 'school-management-system'),
            'new_item'              => __('New Transport Route', 'school-management-system'),
            'edit_item'             => __('Edit Transport Route', 'school-management-system'),
            'update_item'           => __('Update Transport Route', 'school-management-system'),
            'view_item'             => __('View Transport Route', 'school-management-system'),
            'view_items'            => __('View Transport Routes', 'school-management-system'),
            'search_items'          => __('Search Transport Routes', 'school-management-system'),
            'not_found'             => __('Not found', 'school-management-system'),
            'not_found_in_trash'    => __('Not found in Trash', 'school-management-system'),
            'featured_image'        => __('Featured Image', 'school-management-system'),
            'set_featured_image'    => __('Set featured image', 'school-management-system'),
            'remove_featured_image' => __('Remove featured image', 'school-management-system'),
            'use_featured_image'    => __('Use as featured image', 'school-management-system'),
            'insert_into_item'      => __('Insert into transport route', 'school-management-system'),
            'uploaded_to_this_item' => __('Uploaded to this transport route', 'school-management-system'),
            'items_list'            => __('Transport routes list', 'school-management-system'),
            'items_list_navigation' => __('Transport routes list navigation', 'school-management-system'),
            'filter_items_list'     => __('Filter transport routes list', 'school-management-system'),
        );

        $args = array(
            'label'                 => __('Transport Route', 'school-management-system'),
            'description'           => __('School transport routes and bus management', 'school-management-system'),
            'labels'                => $labels,
            'supports'              => array('title', 'editor', 'author', 'custom-fields'),
            'taxonomies'            => array(),
            'hierarchical'          => false,
            'public'                => false,
            'show_ui'               => true,
            'show_in_menu'          => true,
            'menu_position'         => 28,
            'menu_icon'             => 'dashicons-bus',
            'show_in_admin_bar'     => true,
            'show_in_nav_menus'     => false,
            'can_export'            => true,
            'has_archive'           => false,
            'exclude_from_search'   => true,
            'publicly_queryable'    => false,
            'capability_type'       => 'post',
            'capabilities'          => array(
                'create_posts'       => 'manage_transport',
                'edit_posts'         => 'manage_transport',
                'edit_others_posts'  => 'manage_transport',
                'publish_posts'      => 'manage_transport',
                'read_private_posts' => 'manage_transport',
                'delete_posts'       => 'manage_transport',
                'delete_others_posts' => 'manage_transport',
            ),
            'show_in_rest'          => true,
            'rest_base'             => 'transport-routes',
        );

        register_post_type('sms_transport_routes', $args);
    }

    /**
     * Register ACF field groups for transport routes
     */
    public function register_acf_fields() {
        if (!function_exists('acf_add_local_field_group')) {
            return;
        }

        acf_add_local_field_group(array(
            'key' => 'group_transport_route_details',
            'title' => 'Transport Route Details',
            'fields' => array(
                array(
                    'key' => 'field_route_name',
                    'label' => 'Route Name',
                    'name' => 'route_name',
                    'type' => 'text',
                    'instructions' => 'Enter a descriptive name for this transport route',
                    'required' => 1,
                    'maxlength' => 100,
                ),
                array(
                    'key' => 'field_route_code',
                    'label' => 'Route Code',
                    'name' => 'route_code',
                    'type' => 'text',
                    'instructions' => 'Unique code for this route (e.g., RT001, RT002)',
                    'required' => 1,
                    'maxlength' => 20,
                ),
                array(
                    'key' => 'field_route_description',
                    'label' => 'Route Description',
                    'name' => 'route_description',
                    'type' => 'textarea',
                    'instructions' => 'Detailed description of the route coverage area',
                    'required' => 0,
                    'rows' => 3,
                ),
                array(
                    'key' => 'field_route_status',
                    'label' => 'Route Status',
                    'name' => 'route_status',
                    'type' => 'select',
                    'instructions' => 'Current operational status of the route',
                    'required' => 1,
                    'choices' => array(
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'maintenance' => 'Under Maintenance',
                        'suspended' => 'Suspended',
                    ),
                    'default_value' => 'active',
                    'allow_null' => 0,
                    'multiple' => 0,
                    'ui' => 1,
                    'return_format' => 'value',
                ),
                array(
                    'key' => 'field_total_capacity',
                    'label' => 'Total Capacity',
                    'name' => 'total_capacity',
                    'type' => 'number',
                    'instructions' => 'Maximum number of students this route can accommodate',
                    'required' => 1,
                    'min' => 1,
                    'max' => 100,
                ),
                array(
                    'key' => 'field_current_enrollment',
                    'label' => 'Current Enrollment',
                    'name' => 'current_enrollment',
                    'type' => 'number',
                    'instructions' => 'Number of students currently assigned to this route',
                    'required' => 0,
                    'readonly' => 1,
                    'min' => 0,
                ),
                array(
                    'key' => 'field_available_capacity',
                    'label' => 'Available Capacity',
                    'name' => 'available_capacity',
                    'type' => 'number',
                    'instructions' => 'Remaining capacity for new student assignments',
                    'required' => 0,
                    'readonly' => 1,
                    'min' => 0,
                ),
            ),
            'location' => array(
                array(
                    array(
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'sms_transport_routes',
                    ),
                ),
            ),
            'menu_order' => 0,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
        ));

        // Route Stops and Timing
        acf_add_local_field_group(array(
            'key' => 'group_route_stops',
            'title' => 'Route Stops and Timing',
            'fields' => array(
                array(
                    'key' => 'field_route_stops',
                    'label' => 'Route Stops',
                    'name' => 'route_stops',
                    'type' => 'repeater',
                    'instructions' => 'Define the stops along this route with timing',
                    'required' => 1,
                    'sub_fields' => array(
                        array(
                            'key' => 'field_stop_name',
                            'label' => 'Stop Name',
                            'name' => 'stop_name',
                            'type' => 'text',
                            'required' => 1,
                            'maxlength' => 100,
                        ),
                        array(
                            'key' => 'field_stop_location',
                            'label' => 'Location/Address',
                            'name' => 'stop_location',
                            'type' => 'textarea',
                            'required' => 1,
                            'rows' => 2,
                        ),
                        array(
                            'key' => 'field_pickup_time',
                            'label' => 'Pickup Time (Morning)',
                            'name' => 'pickup_time',
                            'type' => 'time_picker',
                            'required' => 1,
                            'display_format' => 'g:i a',
                            'return_format' => 'H:i:s',
                        ),
                        array(
                            'key' => 'field_dropoff_time',
                            'label' => 'Drop-off Time (Evening)',
                            'name' => 'dropoff_time',
                            'type' => 'time_picker',
                            'required' => 1,
                            'display_format' => 'g:i a',
                            'return_format' => 'H:i:s',
                        ),
                        array(
                            'key' => 'field_stop_order',
                            'label' => 'Stop Order',
                            'name' => 'stop_order',
                            'type' => 'number',
                            'required' => 1,
                            'min' => 1,
                            'max' => 50,
                        ),
                        array(
                            'key' => 'field_stop_distance',
                            'label' => 'Distance from School (KM)',
                            'name' => 'stop_distance',
                            'type' => 'number',
                            'required' => 0,
                            'min' => 0,
                            'step' => 0.1,
                        ),
                        array(
                            'key' => 'field_stop_landmarks',
                            'label' => 'Landmarks',
                            'name' => 'stop_landmarks',
                            'type' => 'text',
                            'required' => 0,
                            'maxlength' => 200,
                        ),
                    ),
                    'min' => 1,
                    'max' => 20,
                    'layout' => 'table',
                    'button_label' => 'Add Stop',
                ),
                array(
                    'key' => 'field_total_distance',
                    'label' => 'Total Route Distance (KM)',
                    'name' => 'total_distance',
                    'type' => 'number',
                    'instructions' => 'Total distance covered by this route',
                    'required' => 0,
                    'min' => 0,
                    'step' => 0.1,
                ),
                array(
                    'key' => 'field_estimated_duration',
                    'label' => 'Estimated Duration (Minutes)',
                    'name' => 'estimated_duration',
                    'type' => 'number',
                    'instructions' => 'Estimated time to complete the entire route',
                    'required' => 0,
                    'min' => 0,
                ),
            ),
            'location' => array(
                array(
                    array(
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'sms_transport_routes',
                    ),
                ),
            ),
            'menu_order' => 1,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
        ));

        // Vehicle and Driver Information
        acf_add_local_field_group(array(
            'key' => 'group_vehicle_driver',
            'title' => 'Vehicle and Driver Information',
            'fields' => array(
                array(
                    'key' => 'field_vehicle_details',
                    'label' => 'Vehicle Details',
                    'name' => 'vehicle_details',
                    'type' => 'group',
                    'instructions' => 'Information about the vehicle assigned to this route',
                    'required' => 0,
                    'sub_fields' => array(
                        array(
                            'key' => 'field_vehicle_registration',
                            'label' => 'Registration Number',
                            'name' => 'registration_number',
                            'type' => 'text',
                            'required' => 0,
                            'maxlength' => 20,
                        ),
                        array(
                            'key' => 'field_vehicle_make_model',
                            'label' => 'Make and Model',
                            'name' => 'make_model',
                            'type' => 'text',
                            'required' => 0,
                            'maxlength' => 100,
                        ),
                        array(
                            'key' => 'field_vehicle_year',
                            'label' => 'Year of Manufacture',
                            'name' => 'year',
                            'type' => 'number',
                            'required' => 0,
                            'min' => 1990,
                            'max' => 2030,
                        ),
                        array(
                            'key' => 'field_vehicle_capacity',
                            'label' => 'Vehicle Capacity',
                            'name' => 'capacity',
                            'type' => 'number',
                            'required' => 0,
                            'min' => 1,
                            'max' => 100,
                        ),
                        array(
                            'key' => 'field_vehicle_condition',
                            'label' => 'Vehicle Condition',
                            'name' => 'condition',
                            'type' => 'select',
                            'choices' => array(
                                'excellent' => 'Excellent',
                                'good' => 'Good',
                                'fair' => 'Fair',
                                'poor' => 'Poor',
                                'maintenance' => 'Under Maintenance',
                            ),
                            'allow_null' => 1,
                            'multiple' => 0,
                            'ui' => 1,
                        ),
                        array(
                            'key' => 'field_insurance_expiry',
                            'label' => 'Insurance Expiry Date',
                            'name' => 'insurance_expiry',
                            'type' => 'date_picker',
                            'required' => 0,
                            'display_format' => 'd/m/Y',
                            'return_format' => 'Y-m-d',
                            'first_day' => 1,
                        ),
                        array(
                            'key' => 'field_inspection_expiry',
                            'label' => 'Inspection Expiry Date',
                            'name' => 'inspection_expiry',
                            'type' => 'date_picker',
                            'required' => 0,
                            'display_format' => 'd/m/Y',
                            'return_format' => 'Y-m-d',
                            'first_day' => 1,
                        ),
                    ),
                ),
                array(
                    'key' => 'field_driver_details',
                    'label' => 'Driver Details',
                    'name' => 'driver_details',
                    'type' => 'group',
                    'instructions' => 'Information about the driver assigned to this route',
                    'required' => 0,
                    'sub_fields' => array(
                        array(
                            'key' => 'field_driver_name',
                            'label' => 'Driver Name',
                            'name' => 'name',
                            'type' => 'text',
                            'required' => 0,
                            'maxlength' => 100,
                        ),
                        array(
                            'key' => 'field_driver_phone',
                            'label' => 'Phone Number',
                            'name' => 'phone',
                            'type' => 'text',
                            'required' => 0,
                            'maxlength' => 20,
                        ),
                        array(
                            'key' => 'field_driver_license',
                            'label' => 'License Number',
                            'name' => 'license_number',
                            'type' => 'text',
                            'required' => 0,
                            'maxlength' => 50,
                        ),
                        array(
                            'key' => 'field_license_expiry',
                            'label' => 'License Expiry Date',
                            'name' => 'license_expiry',
                            'type' => 'date_picker',
                            'required' => 0,
                            'display_format' => 'd/m/Y',
                            'return_format' => 'Y-m-d',
                            'first_day' => 1,
                        ),
                        array(
                            'key' => 'field_driver_experience',
                            'label' => 'Years of Experience',
                            'name' => 'experience',
                            'type' => 'number',
                            'required' => 0,
                            'min' => 0,
                            'max' => 50,
                        ),
                        array(
                            'key' => 'field_emergency_contact',
                            'label' => 'Emergency Contact',
                            'name' => 'emergency_contact',
                            'type' => 'text',
                            'required' => 0,
                            'maxlength' => 20,
                        ),
                    ),
                ),
            ),
            'location' => array(
                array(
                    array(
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'sms_transport_routes',
                    ),
                ),
            ),
            'menu_order' => 2,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
        ));

        // Fee Structure
        acf_add_local_field_group(array(
            'key' => 'group_transport_fees',
            'title' => 'Transport Fee Structure',
            'fields' => array(
                array(
                    'key' => 'field_fee_structure_type',
                    'label' => 'Fee Structure Type',
                    'name' => 'fee_structure_type',
                    'type' => 'select',
                    'instructions' => 'How transport fees are calculated for this route',
                    'required' => 1,
                    'choices' => array(
                        'flat_rate' => 'Flat Rate (Same for all stops)',
                        'distance_based' => 'Distance Based (Varies by stop distance)',
                        'stop_based' => 'Stop Based (Different rate per stop)',
                        'custom' => 'Custom Pricing',
                    ),
                    'default_value' => 'flat_rate',
                    'allow_null' => 0,
                    'multiple' => 0,
                    'ui' => 1,
                    'return_format' => 'value',
                ),
                array(
                    'key' => 'field_base_fee',
                    'label' => 'Base Fee (KES)',
                    'name' => 'base_fee',
                    'type' => 'number',
                    'instructions' => 'Base transport fee amount',
                    'required' => 1,
                    'min' => 0,
                    'step' => 0.01,
                    'conditional_logic' => array(
                        array(
                            array(
                                'field' => 'field_fee_structure_type',
                                'operator' => '==',
                                'value' => 'flat_rate',
                            ),
                        ),
                    ),
                ),
                array(
                    'key' => 'field_rate_per_km',
                    'label' => 'Rate per KM (KES)',
                    'name' => 'rate_per_km',
                    'type' => 'number',
                    'instructions' => 'Fee rate per kilometer from school',
                    'required' => 0,
                    'min' => 0,
                    'step' => 0.01,
                    'conditional_logic' => array(
                        array(
                            array(
                                'field' => 'field_fee_structure_type',
                                'operator' => '==',
                                'value' => 'distance_based',
                            ),
                        ),
                    ),
                ),
                array(
                    'key' => 'field_stop_fees',
                    'label' => 'Stop-specific Fees',
                    'name' => 'stop_fees',
                    'type' => 'repeater',
                    'instructions' => 'Define specific fees for each stop',
                    'required' => 0,
                    'sub_fields' => array(
                        array(
                            'key' => 'field_stop_name_fee',
                            'label' => 'Stop Name',
                            'name' => 'stop_name',
                            'type' => 'text',
                            'required' => 1,
                            'maxlength' => 100,
                        ),
                        array(
                            'key' => 'field_stop_fee_amount',
                            'label' => 'Fee Amount (KES)',
                            'name' => 'fee_amount',
                            'type' => 'number',
                            'required' => 1,
                            'min' => 0,
                            'step' => 0.01,
                        ),
                    ),
                    'min' => 0,
                    'max' => 20,
                    'layout' => 'table',
                    'button_label' => 'Add Stop Fee',
                    'conditional_logic' => array(
                        array(
                            array(
                                'field' => 'field_fee_structure_type',
                                'operator' => '==',
                                'value' => 'stop_based',
                            ),
                        ),
                    ),
                ),
                array(
                    'key' => 'field_fee_frequency',
                    'label' => 'Fee Frequency',
                    'name' => 'fee_frequency',
                    'type' => 'select',
                    'instructions' => 'How often transport fees are charged',
                    'required' => 1,
                    'choices' => array(
                        'monthly' => 'Monthly',
                        'termly' => 'Per Term',
                        'annually' => 'Annually',
                        'weekly' => 'Weekly',
                    ),
                    'default_value' => 'monthly',
                    'allow_null' => 0,
                    'multiple' => 0,
                    'ui' => 1,
                    'return_format' => 'value',
                ),
                array(
                    'key' => 'field_discount_siblings',
                    'label' => 'Sibling Discount (%)',
                    'name' => 'discount_siblings',
                    'type' => 'number',
                    'instructions' => 'Discount percentage for siblings using the same route',
                    'required' => 0,
                    'min' => 0,
                    'max' => 100,
                    'step' => 0.01,
                ),
            ),
            'location' => array(
                array(
                    array(
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'sms_transport_routes',
                    ),
                ),
            ),
            'menu_order' => 3,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
        ));
    }

    /**
     * Set custom columns for transport routes list
     */
    public function set_custom_columns($columns) {
        $new_columns = array();
        $new_columns['cb'] = $columns['cb'];
        $new_columns['title'] = __('Route Name', 'school-management-system');
        $new_columns['route_code'] = __('Route Code', 'school-management-system');
        $new_columns['status'] = __('Status', 'school-management-system');
        $new_columns['capacity'] = __('Capacity', 'school-management-system');
        $new_columns['enrollment'] = __('Enrollment', 'school-management-system');
        $new_columns['driver'] = __('Driver', 'school-management-system');
        $new_columns['vehicle'] = __('Vehicle', 'school-management-system');
        $new_columns['base_fee'] = __('Base Fee', 'school-management-system');
        $new_columns['date'] = __('Created', 'school-management-system');
        
        return $new_columns;
    }

    /**
     * Display custom column content
     */
    public function custom_column_content($column, $post_id) {
        switch ($column) {
            case 'route_code':
                $route_code = get_field('route_code', $post_id);
                echo $route_code ? esc_html($route_code) : '—';
                break;
                
            case 'status':
                $status = get_field('route_status', $post_id);
                $status_labels = array(
                    'active' => '<span class="status-active">Active</span>',
                    'inactive' => '<span class="status-inactive">Inactive</span>',
                    'maintenance' => '<span class="status-maintenance">Maintenance</span>',
                    'suspended' => '<span class="status-suspended">Suspended</span>',
                );
                echo $status_labels[$status] ?? '—';
                break;
                
            case 'capacity':
                $capacity = get_field('total_capacity', $post_id);
                echo $capacity ? esc_html($capacity) : '—';
                break;
                
            case 'enrollment':
                $current = get_field('current_enrollment', $post_id);
                $total = get_field('total_capacity', $post_id);
                if ($current !== null && $total) {
                    $percentage = round(($current / $total) * 100);
                    echo esc_html($current . '/' . $total . ' (' . $percentage . '%)');
                } else {
                    echo '—';
                }
                break;
                
            case 'driver':
                $driver_details = get_field('driver_details', $post_id);
                if ($driver_details && !empty($driver_details['name'])) {
                    echo esc_html($driver_details['name']);
                    if (!empty($driver_details['phone'])) {
                        echo '<br><small>' . esc_html($driver_details['phone']) . '</small>';
                    }
                } else {
                    echo '—';
                }
                break;
                
            case 'vehicle':
                $vehicle_details = get_field('vehicle_details', $post_id);
                if ($vehicle_details && !empty($vehicle_details['registration_number'])) {
                    echo esc_html($vehicle_details['registration_number']);
                    if (!empty($vehicle_details['make_model'])) {
                        echo '<br><small>' . esc_html($vehicle_details['make_model']) . '</small>';
                    }
                } else {
                    echo '—';
                }
                break;
                
            case 'base_fee':
                $fee_structure = get_field('fee_structure_type', $post_id);
                if ($fee_structure === 'flat_rate') {
                    $base_fee = get_field('base_fee', $post_id);
                    echo $base_fee ? 'KES ' . number_format($base_fee, 2) : '—';
                } else {
                    echo ucfirst(str_replace('_', ' ', $fee_structure));
                }
                break;
        }
    }

    /**
     * Handle route save actions
     */
    public function handle_route_save($post_id, $post) {
        // Skip if this is an autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Skip if user doesn't have permission
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Update capacity calculations
        $this->update_capacity_calculations($post_id);
    }

    /**
     * Assign student to route via AJAX
     */
    public function assign_student_to_route() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_assign_student_route_nonce')) {
            wp_die(__('Security check failed', 'school-management-system'));
        }

        // Check user capabilities
        if (!current_user_can('manage_transport')) {
            wp_die(__('You do not have permission to assign students to routes', 'school-management-system'));
        }

        $student_id = intval($_POST['student_id']);
        $route_id = intval($_POST['route_id']);
        $stop_name = sanitize_text_field($_POST['stop_name']);

        if (!$student_id || !$route_id) {
            wp_send_json_error(__('Missing required data', 'school-management-system'));
        }

        // Check route capacity
        $capacity_check = $this->check_route_capacity($route_id);
        if (!$capacity_check['available']) {
            wp_send_json_error(__('Route is at full capacity', 'school-management-system'));
        }

        // Assign student to route
        $result = $this->assign_student_to_transport_route($student_id, $route_id, $stop_name);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * Calculate route fee via AJAX
     */
    public function calculate_route_fee() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_calculate_fee_nonce')) {
            wp_die(__('Security check failed', 'school-management-system'));
        }

        $route_id = intval($_POST['route_id']);
        $stop_name = sanitize_text_field($_POST['stop_name']);
        $student_id = intval($_POST['student_id']);

        if (!$route_id) {
            wp_send_json_error(__('Invalid route ID', 'school-management-system'));
        }

        $fee = $this->calculate_transport_fee($route_id, $stop_name, $student_id);

        wp_send_json_success(array(
            'fee_amount' => $fee,
            'formatted_fee' => 'KES ' . number_format($fee, 2),
            'message' => sprintf(__('Transport fee: KES %s', 'school-management-system'), number_format($fee, 2))
        ));
    }

    /**
     * Get route capacity via AJAX
     */
    public function get_route_capacity() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_route_capacity_nonce')) {
            wp_die(__('Security check failed', 'school-management-system'));
        }

        $route_id = intval($_POST['route_id']);

        if (!$route_id) {
            wp_send_json_error(__('Invalid route ID', 'school-management-system'));
        }

        $capacity_info = $this->check_route_capacity($route_id);
        wp_send_json_success($capacity_info);
    }

    /**
     * Notify route changes via AJAX
     */
    public function notify_route_changes() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_notify_changes_nonce')) {
            wp_die(__('Security check failed', 'school-management-system'));
        }

        // Check user capabilities
        if (!current_user_can('manage_transport')) {
            wp_die(__('You do not have permission to send notifications', 'school-management-system'));
        }

        $route_id = intval($_POST['route_id']);
        $change_message = sanitize_textarea_field($_POST['change_message']);

        if (!$route_id || !$change_message) {
            wp_send_json_error(__('Missing required data', 'school-management-system'));
        }

        $result = $this->send_route_change_notifications($route_id, $change_message);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * Assign student to transport route
     */
    private function assign_student_to_transport_route($student_id, $route_id, $stop_name) {
        // Check if student is already assigned to a route
        $current_route = get_field('transport_route', $student_id);
        if ($current_route && $current_route->ID !== $route_id) {
            // Remove from current route first
            $this->remove_student_from_route($student_id, $current_route->ID);
        }

        // Assign to new route
        update_field('transport_route', $route_id, $student_id);
        update_field('transport_stop', $stop_name, $student_id);
        update_field('transport_assigned_date', current_time('mysql'), $student_id);

        // Update route enrollment count
        $this->update_capacity_calculations($route_id);

        // Calculate and update transport fee
        $fee = $this->calculate_transport_fee($route_id, $stop_name, $student_id);
        update_field('transport_fee', $fee, $student_id);

        return array(
            'success' => true,
            'message' => __('Student successfully assigned to transport route', 'school-management-system'),
            'route_id' => $route_id,
            'stop_name' => $stop_name,
            'fee_amount' => $fee
        );
    }

    /**
     * Remove student from route
     */
    private function remove_student_from_route($student_id, $route_id) {
        delete_field('transport_route', $student_id);
        delete_field('transport_stop', $student_id);
        delete_field('transport_fee', $student_id);
        
        // Update route enrollment count
        $this->update_capacity_calculations($route_id);
    }

    /**
     * Check route capacity
     */
    private function check_route_capacity($route_id) {
        $total_capacity = get_field('total_capacity', $route_id);
        $current_enrollment = $this->get_current_enrollment_count($route_id);
        $available_capacity = $total_capacity - $current_enrollment;

        return array(
            'total_capacity' => $total_capacity,
            'current_enrollment' => $current_enrollment,
            'available_capacity' => $available_capacity,
            'available' => $available_capacity > 0,
            'utilization_percentage' => $total_capacity > 0 ? round(($current_enrollment / $total_capacity) * 100, 2) : 0
        );
    }

    /**
     * Get current enrollment count for a route
     */
    private function get_current_enrollment_count($route_id) {
        $students = get_posts(array(
            'post_type' => 'sms_students',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => 'transport_route',
                    'value' => $route_id,
                    'compare' => '='
                )
            )
        ));

        return count($students);
    }

    /**
     * Update capacity calculations
     */
    private function update_capacity_calculations($route_id) {
        $current_enrollment = $this->get_current_enrollment_count($route_id);
        $total_capacity = get_field('total_capacity', $route_id);
        $available_capacity = $total_capacity - $current_enrollment;

        update_field('current_enrollment', $current_enrollment, $route_id);
        update_field('available_capacity', $available_capacity, $route_id);
    }

    /**
     * Calculate transport fee
     */
    private function calculate_transport_fee($route_id, $stop_name, $student_id = null) {
        $fee_structure_type = get_field('fee_structure_type', $route_id);
        $base_fee = 0;

        switch ($fee_structure_type) {
            case 'flat_rate':
                $base_fee = get_field('base_fee', $route_id);
                break;

            case 'distance_based':
                $rate_per_km = get_field('rate_per_km', $route_id);
                $stop_distance = $this->get_stop_distance($route_id, $stop_name);
                $base_fee = $rate_per_km * $stop_distance;
                break;

            case 'stop_based':
                $stop_fees = get_field('stop_fees', $route_id);
                if ($stop_fees) {
                    foreach ($stop_fees as $stop_fee) {
                        if ($stop_fee['stop_name'] === $stop_name) {
                            $base_fee = $stop_fee['fee_amount'];
                            break;
                        }
                    }
                }
                break;

            case 'custom':
                // Custom pricing logic can be implemented here
                $base_fee = apply_filters('sms_custom_transport_fee', 0, $route_id, $stop_name, $student_id);
                break;
        }

        // Apply sibling discount if applicable
        if ($student_id) {
            $discount = $this->calculate_sibling_discount($route_id, $student_id);
            $base_fee = $base_fee * (1 - ($discount / 100));
        }

        return round($base_fee, 2);
    }

    /**
     * Get stop distance from route data
     */
    private function get_stop_distance($route_id, $stop_name) {
        $route_stops = get_field('route_stops', $route_id);
        
        if ($route_stops) {
            foreach ($route_stops as $stop) {
                if ($stop['stop_name'] === $stop_name) {
                    return floatval($stop['stop_distance']);
                }
            }
        }

        return 0;
    }

    /**
     * Calculate sibling discount
     */
    private function calculate_sibling_discount($route_id, $student_id) {
        $discount_percentage = get_field('discount_siblings', $route_id);
        
        if (!$discount_percentage) {
            return 0;
        }

        // Check if student has siblings on the same route
        $student_parent_ids = get_field('parent_user_ids', $student_id);
        
        if (!$student_parent_ids) {
            return 0;
        }

        // Find other students with same parents on same route
        $siblings_on_route = get_posts(array(
            'post_type' => 'sms_students',
            'posts_per_page' => -1,
            'post__not_in' => array($student_id),
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => 'transport_route',
                    'value' => $route_id,
                    'compare' => '='
                ),
                array(
                    'key' => 'parent_user_ids',
                    'value' => $student_parent_ids,
                    'compare' => 'IN'
                )
            )
        ));

        return count($siblings_on_route) > 0 ? $discount_percentage : 0;
    }

    /**
     * Send route change notifications
     */
    private function send_route_change_notifications($route_id, $change_message) {
        // Get all students assigned to this route
        $students = get_posts(array(
            'post_type' => 'sms_students',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => 'transport_route',
                    'value' => $route_id,
                    'compare' => '='
                )
            )
        ));

        $route_name = get_the_title($route_id);
        $notification_count = 0;
        $failed_count = 0;

        foreach ($students as $student) {
            // Get parent contact information
            $parent_user_ids = get_field('parent_user_ids', $student->ID);
            
            if ($parent_user_ids) {
                foreach ($parent_user_ids as $parent_user_id) {
                    $parent = get_userdata($parent_user_id);
                    if ($parent) {
                        $phone = get_user_meta($parent->ID, 'phone_number', true);
                        
                        if ($phone) {
                            $message = sprintf(
                                __('Transport Update: %s\n\nRoute: %s\nStudent: %s\n\n%s\n\n- %s', 'school-management-system'),
                                $change_message,
                                $route_name,
                                $student->post_title,
                                $change_message,
                                get_bloginfo('name')
                            );

                            // Send SMS notification
                            $sms_sent = apply_filters('sms_send_notification', false, $phone, $message);
                            
                            if ($sms_sent) {
                                $notification_count++;
                            } else {
                                $failed_count++;
                            }

                            // Send email notification
                            $email_sent = wp_mail(
                                $parent->user_email,
                                sprintf(__('Transport Route Update - %s', 'school-management-system'), $route_name),
                                nl2br($message),
                                array('Content-Type: text/html; charset=UTF-8')
                            );
                        }
                    }
                }
            }
        }

        return array(
            'success' => true,
            'message' => sprintf(
                __('Route change notifications sent to %d recipients (%d failed)', 'school-management-system'),
                $notification_count,
                $failed_count
            ),
            'sent_count' => $notification_count,
            'failed_count' => $failed_count,
            'total_students' => count($students)
        );
    }

    /**
     * Get route information with stops
     */
    public function get_route_info($route_id) {
        $route = get_post($route_id);
        
        if (!$route || $route->post_type !== 'sms_transport_routes') {
            return null;
        }

        return array(
            'id' => $route->ID,
            'name' => $route->post_title,
            'code' => get_field('route_code', $route->ID),
            'description' => get_field('route_description', $route->ID),
            'status' => get_field('route_status', $route->ID),
            'capacity' => get_field('total_capacity', $route->ID),
            'current_enrollment' => get_field('current_enrollment', $route->ID),
            'available_capacity' => get_field('available_capacity', $route->ID),
            'stops' => get_field('route_stops', $route->ID),
            'vehicle_details' => get_field('vehicle_details', $route->ID),
            'driver_details' => get_field('driver_details', $route->ID),
            'fee_structure' => array(
                'type' => get_field('fee_structure_type', $route->ID),
                'base_fee' => get_field('base_fee', $route->ID),
                'rate_per_km' => get_field('rate_per_km', $route->ID),
                'frequency' => get_field('fee_frequency', $route->ID),
                'sibling_discount' => get_field('discount_siblings', $route->ID)
            )
        );
    }

    /**
     * Get students assigned to a route
     */
    public function get_route_students($route_id) {
        $students = get_posts(array(
            'post_type' => 'sms_students',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => 'transport_route',
                    'value' => $route_id,
                    'compare' => '='
                )
            ),
            'orderby' => 'title',
            'order' => 'ASC'
        ));

        $student_data = array();
        foreach ($students as $student) {
            $student_data[] = array(
                'id' => $student->ID,
                'name' => $student->post_title,
                'admission_number' => get_field('admission_number', $student->ID),
                'stop' => get_field('transport_stop', $student->ID),
                'fee' => get_field('transport_fee', $student->ID),
                'assigned_date' => get_field('transport_assigned_date', $student->ID)
            );
        }

        return $student_data;
    }
}

// Initialize the class
new SMS_Transport_Routes_CPT();