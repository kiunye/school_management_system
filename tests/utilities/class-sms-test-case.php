<?php
/**
 * Base test case class for SMS tests.
 */

class SMS_Test_Case extends WP_UnitTestCase {

    /**
     * Test factory instance.
     */
    protected $factory;

    /**
     * Test data instance.
     */
    protected $test_data;

    /**
     * Set up test environment.
     */
    public function setUp(): void {
        parent::setUp();
        
        $this->factory = new SMS_Test_Factory();
        $this->test_data = new SMS_Test_Data();
        
        // Activate ACF if available
        if (class_exists('ACF')) {
            // Load ACF field groups for testing
            $this->load_acf_field_groups();
        }
        
        // Set up user roles and capabilities
        $this->setup_user_roles();
        
        // Clear any existing test data
        $this->cleanup_test_data();
    }

    /**
     * Clean up after tests.
     */
    public function tearDown(): void {
        $this->cleanup_test_data();
        parent::tearDown();
    }

    /**
     * Load ACF field groups for testing.
     */
    protected function load_acf_field_groups() {
        // Mock ACF field groups if needed
        if (!function_exists('acf_add_local_field_group')) {
            function acf_add_local_field_group($field_group) {
                // Mock implementation
            }
        }
        
        if (!function_exists('get_field')) {
            function get_field($selector, $post_id = false, $format_value = true) {
                return get_post_meta($post_id, $selector, true);
            }
        }
        
        if (!function_exists('update_field')) {
            function update_field($selector, $value, $post_id = false) {
                return update_post_meta($post_id, $selector, $value);
            }
        }
    }

    /**
     * Set up user roles and capabilities for testing.
     */
    protected function setup_user_roles() {
        // Add custom capabilities to administrator role
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $admin_capabilities = [
                'manage_students',
                'manage_classes',
                'manage_fees',
                'manage_financial_reports',
                'send_bulk_sms',
                'manage_system_settings',
                'manage_admissions'
            ];
            
            foreach ($admin_capabilities as $cap) {
                $admin_role->add_cap($cap);
            }
        }
        
        // Create teacher role if it doesn't exist
        if (!get_role('teacher')) {
            add_role('teacher', 'Teacher', [
                'read' => true,
                'edit_assigned_classes' => true,
                'mark_attendance' => true,
                'create_lessons' => true,
                'view_student_records' => true,
                'create_academic_notices' => true
            ]);
        }
        
        // Create parent role if it doesn't exist
        if (!get_role('parent')) {
            add_role('parent', 'Parent', [
                'read' => true,
                'view_child_records' => true,
                'view_child_fees' => true,
                'make_payments' => true,
                'update_contact_info' => true
            ]);
        }
    }

    /**
     * Clean up test data.
     */
    protected function cleanup_test_data() {
        global $wpdb;
        
        // Delete test posts
        $post_types = ['sms_students', 'sms_classes', 'sms_fees', 'sms_invoices', 'sms_transactions'];
        foreach ($post_types as $post_type) {
            $posts = get_posts([
                'post_type' => $post_type,
                'posts_per_page' => -1,
                'post_status' => 'any'
            ]);
            
            foreach ($posts as $post) {
                wp_delete_post($post->ID, true);
            }
        }
        
        // Clean up test users
        $test_users = get_users(['meta_key' => 'test_user', 'meta_value' => '1']);
        foreach ($test_users as $user) {
            wp_delete_user($user->ID);
        }
        
        // Clean up custom tables if they exist
        $custom_tables = [
            $wpdb->prefix . 'sms_activity_log',
            $wpdb->prefix . 'sms_payment_queue'
        ];
        
        foreach ($custom_tables as $table) {
            if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") == $table) {
                $wpdb->query("TRUNCATE TABLE {$table}");
            }
        }
    }

    /**
     * Create test administrator user.
     */
    protected function create_test_admin() {
        $user_id = $this->factory->user->create([
            'role' => 'administrator',
            'user_login' => 'test_admin',
            'user_email' => 'admin@test.com',
            'display_name' => 'Test Administrator'
        ]);
        
        update_user_meta($user_id, 'test_user', '1');
        return $user_id;
    }

    /**
     * Create test teacher user.
     */
    protected function create_test_teacher() {
        $user_id = $this->factory->user->create([
            'role' => 'teacher',
            'user_login' => 'test_teacher',
            'user_email' => 'teacher@test.com',
            'display_name' => 'Test Teacher'
        ]);
        
        update_user_meta($user_id, 'test_user', '1');
        return $user_id;
    }

    /**
     * Create test parent user.
     */
    protected function create_test_parent() {
        $user_id = $this->factory->user->create([
            'role' => 'parent',
            'user_login' => 'test_parent',
            'user_email' => 'parent@test.com',
            'display_name' => 'Test Parent'
        ]);
        
        update_user_meta($user_id, 'test_user', '1');
        return $user_id;
    }

    /**
     * Assert that a WP_Error has a specific error code.
     */
    protected function assertWPError($error, $expected_code = null) {
        $this->assertInstanceOf('WP_Error', $error);
        
        if ($expected_code !== null) {
            $this->assertEquals($expected_code, $error->get_error_code());
        }
    }

    /**
     * Assert that a value is not a WP_Error.
     */
    protected function assertNotWPError($value) {
        $this->assertNotInstanceOf('WP_Error', $value);
    }

    /**
     * Assert that a post exists and has the expected post type.
     */
    protected function assertPostExists($post_id, $expected_post_type = null) {
        $post = get_post($post_id);
        $this->assertNotNull($post, "Post with ID {$post_id} should exist");
        
        if ($expected_post_type !== null) {
            $this->assertEquals($expected_post_type, $post->post_type);
        }
    }

    /**
     * Assert that a user has a specific capability.
     */
    protected function assertUserCan($user_id, $capability) {
        $user = new WP_User($user_id);
        $this->assertTrue($user->has_cap($capability), "User {$user_id} should have capability {$capability}");
    }

    /**
     * Assert that a user does not have a specific capability.
     */
    protected function assertUserCannot($user_id, $capability) {
        $user = new WP_User($user_id);
        $this->assertFalse($user->has_cap($capability), "User {$user_id} should not have capability {$capability}");
    }

    /**
     * Mock WordPress functions for unit tests.
     */
    protected function mock_wordpress_functions() {
        // Mock current_time function
        if (!function_exists('current_time')) {
            function current_time($type, $gmt = 0) {
                return date($type === 'mysql' ? 'Y-m-d H:i:s' : $type);
            }
        }
        
        // Mock wp_create_nonce function
        if (!function_exists('wp_create_nonce')) {
            function wp_create_nonce($action = -1) {
                return 'test_nonce_' . md5($action);
            }
        }
        
        // Mock wp_verify_nonce function
        if (!function_exists('wp_verify_nonce')) {
            function wp_verify_nonce($nonce, $action = -1) {
                return $nonce === 'test_nonce_' . md5($action);
            }
        }
    }

    /**
     * Set current user for testing.
     */
    protected function set_current_user($user_id) {
        wp_set_current_user($user_id);
    }

    /**
     * Reset current user.
     */
    protected function reset_current_user() {
        wp_set_current_user(0);
    }
}