<?php
/**
 * Integration tests for SMS custom post types.
 */

class Test_SMS_Custom_Post_Types extends SMS_Test_Case {

    /**
     * Test student custom post type registration.
     */
    public function test_student_post_type_registration() {
        $this->assertTrue(post_type_exists('sms_students'));
        
        $post_type = get_post_type_object('sms_students');
        $this->assertNotNull($post_type);
        $this->assertEquals('Students', $post_type->labels->name);
        $this->assertTrue($post_type->public);
        $this->assertTrue($post_type->show_ui);
        $this->assertTrue($post_type->show_in_menu);
    }

    /**
     * Test class custom post type registration.
     */
    public function test_class_post_type_registration() {
        $this->assertTrue(post_type_exists('sms_classes'));
        
        $post_type = get_post_type_object('sms_classes');
        $this->assertNotNull($post_type);
        $this->assertEquals('Classes', $post_type->labels->name);
    }

    /**
     * Test fee custom post type registration.
     */
    public function test_fee_post_type_registration() {
        $this->assertTrue(post_type_exists('sms_fees'));
        
        $post_type = get_post_type_object('sms_fees');
        $this->assertNotNull($post_type);
        $this->assertEquals('Fees', $post_type->labels->name);
    }

    /**
     * Test invoice custom post type registration.
     */
    public function test_invoice_post_type_registration() {
        $this->assertTrue(post_type_exists('sms_invoices'));
        
        $post_type = get_post_type_object('sms_invoices');
        $this->assertNotNull($post_type);
        $this->assertEquals('Invoices', $post_type->labels->name);
    }

    /**
     * Test transaction custom post type registration.
     */
    public function test_transaction_post_type_registration() {
        $this->assertTrue(post_type_exists('sms_transactions'));
        
        $post_type = get_post_type_object('sms_transactions');
        $this->assertNotNull($post_type);
        $this->assertEquals('Transactions', $post_type->labels->name);
    }

    /**
     * Test attendance custom post type registration.
     */
    public function test_attendance_post_type_registration() {
        $this->assertTrue(post_type_exists('sms_attendance'));
        
        $post_type = get_post_type_object('sms_attendance');
        $this->assertNotNull($post_type);
        $this->assertEquals('Attendance', $post_type->labels->name);
    }

    /**
     * Test timetable custom post type registration.
     */
    public function test_timetable_post_type_registration() {
        $this->assertTrue(post_type_exists('sms_timetables'));
        
        $post_type = get_post_type_object('sms_timetables');
        $this->assertNotNull($post_type);
        $this->assertEquals('Timetables', $post_type->labels->name);
    }

    /**
     * Test notice custom post type registration.
     */
    public function test_notice_post_type_registration() {
        $this->assertTrue(post_type_exists('sms_notices'));
        
        $post_type = get_post_type_object('sms_notices');
        $this->assertNotNull($post_type);
        $this->assertEquals('Notices', $post_type->labels->name);
    }

    /**
     * Test transport route custom post type registration.
     */
    public function test_transport_route_post_type_registration() {
        $this->assertTrue(post_type_exists('sms_transport_routes'));
        
        $post_type = get_post_type_object('sms_transport_routes');
        $this->assertNotNull($post_type);
        $this->assertEquals('Transport Routes', $post_type->labels->name);
    }

    /**
     * Test student post creation and meta data.
     */
    public function test_student_post_creation() {
        $student_data = $this->test_data->get_sample_student_data();
        $student_id = $this->factory->create_student([
            'post_title' => $student_data['full_name'],
            'meta_input' => $student_data
        ]);
        
        $this->assertPostExists($student_id, 'sms_students');
        
        // Test meta data retrieval
        $full_name = get_post_meta($student_id, 'full_name', true);
        $this->assertEquals($student_data['full_name'], $full_name);
        
        $date_of_birth = get_post_meta($student_id, 'date_of_birth', true);
        $this->assertEquals($student_data['date_of_birth'], $date_of_birth);
        
        $parent_details = get_post_meta($student_id, 'parent_details', true);
        $this->assertIsArray($parent_details);
        $this->assertEquals($student_data['parent_details'], $parent_details);
    }

    /**
     * Test class post creation and relationships.
     */
    public function test_class_post_creation() {
        $class_data = $this->test_data->get_sample_class_data();
        $class_id = $this->factory->create_class([
            'post_title' => $class_data['class_name'],
            'meta_input' => $class_data
        ]);
        
        $this->assertPostExists($class_id, 'sms_classes');
        
        // Test meta data
        $capacity = get_post_meta($class_id, 'capacity', true);
        $this->assertEquals($class_data['capacity'], $capacity);
        
        $grade_level = get_post_meta($class_id, 'grade_level', true);
        $this->assertEquals($class_data['grade_level'], $grade_level);
    }

    /**
     * Test fee post creation with complex data.
     */
    public function test_fee_post_creation() {
        $fee_data = $this->test_data->get_sample_fee_data()['tuition'];
        $fee_id = $this->factory->create_fee([
            'post_title' => 'Tuition Fee',
            'meta_input' => $fee_data
        ]);
        
        $this->assertPostExists($fee_id, 'sms_fees');
        
        // Test complex meta data
        $installment_options = get_post_meta($fee_id, 'installment_options', true);
        $this->assertIsArray($installment_options);
        $this->assertEquals($fee_data['installment_options'], $installment_options);
    }

    /**
     * Test post queries and filtering.
     */
    public function test_post_queries() {
        // Create multiple students
        $students = $this->factory->create_students(5);
        
        // Query all students
        $query = new WP_Query([
            'post_type' => 'sms_students',
            'posts_per_page' => -1
        ]);
        
        $this->assertEquals(5, $query->found_posts);
        
        // Test meta query
        $query = new WP_Query([
            'post_type' => 'sms_students',
            'meta_query' => [
                [
                    'key' => 'student_status',
                    'value' => 'active',
                    'compare' => '='
                ]
            ]
        ]);
        
        $this->assertGreaterThan(0, $query->found_posts);
    }

    /**
     * Test post relationships.
     */
    public function test_post_relationships() {
        // Create class and students
        $class_id = $this->factory->create_class();
        $student_id = $this->factory->create_student();
        
        // Assign student to class
        update_post_meta($student_id, 'assigned_class', $class_id);
        
        // Verify relationship
        $assigned_class = get_post_meta($student_id, 'assigned_class', true);
        $this->assertEquals($class_id, $assigned_class);
        
        // Query students by class
        $query = new WP_Query([
            'post_type' => 'sms_students',
            'meta_query' => [
                [
                    'key' => 'assigned_class',
                    'value' => $class_id,
                    'compare' => '='
                ]
            ]
        ]);
        
        $this->assertEquals(1, $query->found_posts);
    }

    /**
     * Test post status transitions.
     */
    public function test_post_status_transitions() {
        $student_id = $this->factory->create_student();
        
        // Test initial status
        $post = get_post($student_id);
        $this->assertEquals('publish', $post->post_status);
        
        // Test status change
        wp_update_post([
            'ID' => $student_id,
            'post_status' => 'draft'
        ]);
        
        $post = get_post($student_id);
        $this->assertEquals('draft', $post->post_status);
    }

    /**
     * Test post deletion and cleanup.
     */
    public function test_post_deletion() {
        $student_id = $this->factory->create_student([
            'meta_input' => [
                'full_name' => 'Test Student',
                'test_meta' => 'test_value'
            ]
        ]);
        
        // Verify post and meta exist
        $this->assertPostExists($student_id);
        $this->assertEquals('test_value', get_post_meta($student_id, 'test_meta', true));
        
        // Delete post
        wp_delete_post($student_id, true);
        
        // Verify post is deleted
        $post = get_post($student_id);
        $this->assertNull($post);
        
        // Verify meta is cleaned up
        $meta = get_post_meta($student_id, 'test_meta', true);
        $this->assertEmpty($meta);
    }

    /**
     * Test bulk operations on posts.
     */
    public function test_bulk_operations() {
        // Create multiple students
        $students = $this->factory->create_students(10);
        
        // Bulk update meta
        foreach ($students as $student_id) {
            update_post_meta($student_id, 'bulk_test', 'updated');
        }
        
        // Verify bulk update
        foreach ($students as $student_id) {
            $meta_value = get_post_meta($student_id, 'bulk_test', true);
            $this->assertEquals('updated', $meta_value);
        }
        
        // Bulk query
        $query = new WP_Query([
            'post_type' => 'sms_students',
            'meta_query' => [
                [
                    'key' => 'bulk_test',
                    'value' => 'updated',
                    'compare' => '='
                ]
            ],
            'posts_per_page' => -1
        ]);
        
        $this->assertEquals(10, $query->found_posts);
    }

    /**
     * Test post capabilities and permissions.
     */
    public function test_post_capabilities() {
        $admin_id = $this->create_test_admin();
        $teacher_id = $this->create_test_teacher();
        $parent_id = $this->create_test_parent();
        
        // Test admin capabilities
        $this->set_current_user($admin_id);
        $this->assertUserCan($admin_id, 'manage_students');
        $this->assertUserCan($admin_id, 'manage_classes');
        
        // Test teacher capabilities
        $this->set_current_user($teacher_id);
        $this->assertUserCan($teacher_id, 'mark_attendance');
        $this->assertUserCannot($teacher_id, 'manage_students');
        
        // Test parent capabilities
        $this->set_current_user($parent_id);
        $this->assertUserCan($parent_id, 'view_child_records');
        $this->assertUserCannot($parent_id, 'manage_students');
    }
}