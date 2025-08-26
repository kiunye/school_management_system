<?php
/**
 * Role Testing Utility
 *
 * Provides tools for testing and assigning SMS roles to users.
 *
 * @package School_Management_System
 * @subpackage Admin
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SMS Role Tester Class
 *
 * Helps administrators test role-specific dashboards and assign roles to users.
 */
class SMS_Role_Tester {

    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Menu is now handled by SMS_Admin class
        add_action('wp_ajax_sms_assign_role', [$this, 'ajax_assign_role']);
        add_action('wp_ajax_sms_test_dashboard', [$this, 'ajax_test_dashboard']);
        add_action('wp_ajax_sms_recreate_roles', [$this, 'ajax_recreate_roles']);
        add_action('wp_ajax_sms_create_tables', [$this, 'ajax_create_tables']);
        add_action('wp_ajax_sms_add_sample_data', [$this, 'ajax_add_sample_data']);
        add_action('admin_notices', [$this, 'show_role_notices']);
    }



    /**
     * Display role tester page
     */
    public function display_role_tester_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        // Get all users
        $users = get_users(['number' => 50]);
        $sms_roles = [
            'sms_admin' => 'School Administrator',
            'sms_teacher' => 'Teacher',
            'sms_parent' => 'Parent',
            'sms_student' => 'Student'
        ];

        ?>
        <div class="wrap">
            <h1><?php _e('SMS Role Tester', 'school-management-system'); ?></h1>
            <p><?php _e('Use this tool to assign SMS roles to users and test the dashboard system.', 'school-management-system'); ?></p>

            <div class="sms-role-tester-sections">
                <!-- Role Assignment Section -->
                <div class="sms-section">
                    <h2><?php _e('Assign Roles to Users', 'school-management-system'); ?></h2>
                    <form id="sms-role-assignment-form">
                        <?php wp_nonce_field('sms_assign_role', 'sms_role_nonce'); ?>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="user_id"><?php _e('Select User', 'school-management-system'); ?></label>
                                </th>
                                <td>
                                    <select id="user_id" name="user_id" required>
                                        <option value=""><?php _e('Choose a user...', 'school-management-system'); ?></option>
                                        <?php foreach ($users as $user): ?>
                                            <option value="<?php echo esc_attr($user->ID); ?>">
                                                <?php echo esc_html($user->display_name . ' (' . $user->user_email . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="sms_role"><?php _e('SMS Role', 'school-management-system'); ?></label>
                                </th>
                                <td>
                                    <select id="sms_role" name="sms_role" required>
                                        <option value=""><?php _e('Choose a role...', 'school-management-system'); ?></option>
                                        <?php foreach ($sms_roles as $role_key => $role_name): ?>
                                            <option value="<?php echo esc_attr($role_key); ?>">
                                                <?php echo esc_html($role_name); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                        </table>

                        <p class="submit">
                            <button type="submit" class="button button-primary">
                                <?php _e('Assign Role', 'school-management-system'); ?>
                            </button>
                        </p>
                    </form>
                </div>

                <!-- Current Role Assignments -->
                <div class="sms-section">
                    <h2><?php _e('Current SMS Role Assignments', 'school-management-system'); ?></h2>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('User', 'school-management-system'); ?></th>
                                <th><?php _e('Email', 'school-management-system'); ?></th>
                                <th><?php _e('Current Roles', 'school-management-system'); ?></th>
                                <th><?php _e('Dashboard Link', 'school-management-system'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <?php 
                                $user_roles = $user->roles;
                                $sms_user_roles = array_intersect($user_roles, array_keys($sms_roles));
                                if (empty($sms_user_roles)) continue;
                                ?>
                                <tr>
                                    <td><strong><?php echo esc_html($user->display_name); ?></strong></td>
                                    <td><?php echo esc_html($user->user_email); ?></td>
                                    <td>
                                        <?php 
                                        foreach ($sms_user_roles as $role) {
                                            echo '<span class="sms-role-badge sms-role-' . esc_attr($role) . '">';
                                            echo esc_html($sms_roles[$role]);
                                            echo '</span> ';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $primary_role = $sms_user_roles[0];
                                        $dashboard_url = $this->get_dashboard_url_for_role($primary_role);
                                        if ($dashboard_url): ?>
                                            <a href="<?php echo esc_url($dashboard_url); ?>" class="button button-small" target="_blank">
                                                <?php _e('View Dashboard', 'school-management-system'); ?>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Dashboard Testing Section -->
                <div class="sms-section">
                    <h2><?php _e('Dashboard Testing', 'school-management-system'); ?></h2>
                    <p><?php _e('Test each dashboard type to ensure they are working correctly.', 'school-management-system'); ?></p>
                    
                    <div class="sms-dashboard-tests">
                        <?php foreach ($sms_roles as $role_key => $role_name): ?>
                            <div class="sms-dashboard-test">
                                <h3><?php echo esc_html($role_name . ' Dashboard'); ?></h3>
                                <p><?php printf(__('Test the %s dashboard functionality.', 'school-management-system'), $role_name); ?></p>
                                <a href="<?php echo esc_url($this->get_dashboard_url_for_role($role_key)); ?>" 
                                   class="button button-secondary" target="_blank">
                                    <?php printf(__('Test %s Dashboard', 'school-management-system'), $role_name); ?>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Role Creation Status -->
                <div class="sms-section">
                    <h2><?php _e('Role Creation Status', 'school-management-system'); ?></h2>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Role', 'school-management-system'); ?></th>
                                <th><?php _e('Status', 'school-management-system'); ?></th>
                                <th><?php _e('Capabilities', 'school-management-system'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sms_roles as $role_key => $role_name): ?>
                                <?php $role_obj = get_role($role_key); ?>
                                <tr>
                                    <td><strong><?php echo esc_html($role_name); ?></strong></td>
                                    <td>
                                        <?php if ($role_obj): ?>
                                            <span class="sms-status-success">✓ Created</span>
                                        <?php else: ?>
                                            <span class="sms-status-error">✗ Missing</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($role_obj): ?>
                                            <?php echo count($role_obj->capabilities); ?> capabilities
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <p class="submit">
                        <button type="button" id="recreate-roles" class="button button-secondary">
                            <?php _e('Recreate All SMS Roles', 'school-management-system'); ?>
                        </button>
                    </p>
                </div>

                <!-- Database Status Section -->
                <div class="sms-section">
                    <h2><?php _e('Database Status', 'school-management-system'); ?></h2>
                    <?php 
                    $db_setup = new SMS_Database_Setup();
                    $table_status = $db_setup->get_table_status();
                    ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Table', 'school-management-system'); ?></th>
                                <th><?php _e('Status', 'school-management-system'); ?></th>
                                <th><?php _e('Records', 'school-management-system'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($table_status as $table_key => $status): ?>
                                <tr>
                                    <td><code><?php echo esc_html($status['name']); ?></code></td>
                                    <td>
                                        <?php if ($status['exists']): ?>
                                            <span class="sms-status-success">✓ Exists</span>
                                        <?php else: ?>
                                            <span class="sms-status-error">✗ Missing</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html($status['count']); ?> records</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <p class="submit">
                        <button type="button" id="create-tables" class="button button-primary">
                            <?php _e('Create/Repair Database Tables', 'school-management-system'); ?>
                        </button>
                        <button type="button" id="add-sample-data" class="button button-secondary">
                            <?php _e('Add Sample Data', 'school-management-system'); ?>
                        </button>
                    </p>
                </div>

                <!-- Current User Debug Section -->
                <div class="sms-section">
                    <h2><?php _e('Current User Debug Info', 'school-management-system'); ?></h2>
                    <?php 
                    $current_user = wp_get_current_user();
                    $user_roles = $current_user->roles;
                    ?>
                    <table class="wp-list-table widefat fixed striped">
                        <tbody>
                            <tr>
                                <th><?php _e('User ID', 'school-management-system'); ?></th>
                                <td><?php echo esc_html($current_user->ID); ?></td>
                            </tr>
                            <tr>
                                <th><?php _e('Username', 'school-management-system'); ?></th>
                                <td><?php echo esc_html($current_user->user_login); ?></td>
                            </tr>
                            <tr>
                                <th><?php _e('Display Name', 'school-management-system'); ?></th>
                                <td><?php echo esc_html($current_user->display_name); ?></td>
                            </tr>
                            <tr>
                                <th><?php _e('User Roles', 'school-management-system'); ?></th>
                                <td><?php echo esc_html(implode(', ', $user_roles)); ?></td>
                            </tr>
                            <tr>
                                <th><?php _e('Key Capabilities', 'school-management-system'); ?></th>
                                <td>
                                    <?php 
                                    $key_caps = ['read', 'edit_posts', 'manage_options', 'view_student_records', 'edit_assigned_classes'];
                                    foreach ($key_caps as $cap) {
                                        $has_cap = current_user_can($cap) ? '✓' : '✗';
                                        echo "<span style='color: " . (current_user_can($cap) ? 'green' : 'red') . ";'>{$has_cap} {$cap}</span><br>";
                                    }
                                    ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <style>
        .sms-role-tester-sections {
            display: grid;
            gap: 20px;
        }
        
        .sms-section {
            background: #fff;
            padding: 20px;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
        }
        
        .sms-role-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: bold;
            color: white;
            margin-right: 5px;
        }
        
        .sms-role-sms_admin { background: #d63638; }
        .sms-role-sms_teacher { background: #00a32a; }
        .sms-role-sms_parent { background: #0073aa; }
        .sms-role-sms_student { background: #dba617; }
        
        .sms-dashboard-tests {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }
        
        .sms-dashboard-test {
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: #f9f9f9;
        }
        
        .sms-status-success { color: #00a32a; font-weight: bold; }
        .sms-status-error { color: #d63638; font-weight: bold; }
        </style>

        <script>
        jQuery(document).ready(function($) {
            // Handle role assignment
            $('#sms-role-assignment-form').on('submit', function(e) {
                e.preventDefault();
                
                var formData = {
                    action: 'sms_assign_role',
                    user_id: $('#user_id').val(),
                    sms_role: $('#sms_role').val(),
                    nonce: $('#sms_role_nonce').val()
                };
                
                $.post(ajaxurl, formData, function(response) {
                    if (response.success) {
                        alert('Role assigned successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + response.data);
                    }
                });
            });
            
            // Handle role recreation
            $('#recreate-roles').on('click', function() {
                if (confirm('Are you sure you want to recreate all SMS roles? This will reset all role capabilities.')) {
                    $.post(ajaxurl, {
                        action: 'sms_recreate_roles',
                        nonce: '<?php echo wp_create_nonce('sms_recreate_roles'); ?>'
                    }, function(response) {
                        if (response.success) {
                            alert('Roles recreated successfully!');
                            location.reload();
                        } else {
                            alert('Error: ' + response.data);
                        }
                    });
                }
            });
            
            // Handle table creation
            $('#create-tables').on('click', function() {
                $(this).prop('disabled', true).text('Creating Tables...');
                
                $.post(ajaxurl, {
                    action: 'sms_create_tables',
                    nonce: '<?php echo wp_create_nonce('sms_create_tables'); ?>'
                }, function(response) {
                    if (response.success) {
                        alert('Database tables created successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + response.data);
                    }
                }).always(function() {
                    $('#create-tables').prop('disabled', false).text('Create/Repair Database Tables');
                });
            });
            
            // Handle sample data addition
            $('#add-sample-data').on('click', function() {
                if (confirm('This will add sample data to the database. Continue?')) {
                    $(this).prop('disabled', true).text('Adding Sample Data...');
                    
                    $.post(ajaxurl, {
                        action: 'sms_add_sample_data',
                        nonce: '<?php echo wp_create_nonce('sms_add_sample_data'); ?>'
                    }, function(response) {
                        if (response.success) {
                            alert('Sample data added successfully!');
                            location.reload();
                        } else {
                            alert('Error: ' + response.data);
                        }
                    }).always(function() {
                        $('#add-sample-data').prop('disabled', false).text('Add Sample Data');
                    });
                }
            });
        });
        </script>
        <?php
    }

    /**
     * AJAX handler for role assignment
     */
    public function ajax_assign_role() {
        check_ajax_referer('sms_assign_role', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $user_id = intval($_POST['user_id']);
        $sms_role = sanitize_text_field($_POST['sms_role']);

        $user = get_user_by('ID', $user_id);
        if (!$user) {
            wp_send_json_error('User not found');
        }

        // Remove existing SMS roles
        $sms_roles = ['sms_admin', 'sms_teacher', 'sms_parent', 'sms_student'];
        foreach ($sms_roles as $role) {
            $user->remove_role($role);
        }

        // Add new SMS role
        $user->add_role($sms_role);

        wp_send_json_success('Role assigned successfully');
    }

    /**
     * AJAX handler for recreating roles
     */
    public function ajax_recreate_roles() {
        check_ajax_referer('sms_recreate_roles', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        // Force recreate roles
        if (class_exists('SMS_User_Roles')) {
            $user_roles = new SMS_User_Roles();
            $user_roles->update_user_roles();
            wp_send_json_success('Roles recreated successfully');
        } else {
            wp_send_json_error('SMS_User_Roles class not found');
        }
    }

    /**
     * AJAX handler for creating database tables
     */
    public function ajax_create_tables() {
        check_ajax_referer('sms_create_tables', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        if (class_exists('SMS_Database_Setup')) {
            $db_setup = new SMS_Database_Setup();
            $db_setup->create_tables();
            wp_send_json_success('Database tables created successfully');
        } else {
            wp_send_json_error('SMS_Database_Setup class not found');
        }
    }

    /**
     * AJAX handler for adding sample data
     */
    public function ajax_add_sample_data() {
        check_ajax_referer('sms_add_sample_data', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        if (class_exists('SMS_Database_Setup')) {
            $db_setup = new SMS_Database_Setup();
            $db_setup->add_sample_data();
            wp_send_json_success('Sample data added successfully');
        } else {
            wp_send_json_error('SMS_Database_Setup class not found');
        }
    }

    /**
     * Get dashboard URL for role
     */
    private function get_dashboard_url_for_role($role) {
        $dashboard_urls = [
            'sms_admin' => admin_url('admin.php?page=sms-admin-dashboard'),
            'sms_teacher' => admin_url('admin.php?page=sms-teacher-dashboard'),
            'sms_parent' => admin_url('admin.php?page=sms-parent-dashboard'),
            'sms_student' => admin_url('admin.php?page=sms-student-dashboard')
        ];

        return $dashboard_urls[$role] ?? admin_url();
    }

    /**
     * Show role-related notices
     */
    public function show_role_notices() {
        $screen = get_current_screen();
        if ($screen->id !== 'school-management_page_sms-role-tester') {
            return;
        }

        // Check if roles exist
        $sms_roles = ['sms_admin', 'sms_teacher', 'sms_parent', 'sms_student'];
        $missing_roles = [];

        foreach ($sms_roles as $role) {
            if (!get_role($role)) {
                $missing_roles[] = $role;
            }
        }

        if (!empty($missing_roles)) {
            echo '<div class="notice notice-warning">';
            echo '<p><strong>Warning:</strong> Some SMS roles are missing: ' . implode(', ', $missing_roles) . '</p>';
            echo '<p>Click "Recreate All SMS Roles" to fix this issue.</p>';
            echo '</div>';
        }
    }
}