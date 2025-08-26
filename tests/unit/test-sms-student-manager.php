<?php
/**
 * Unit tests for SMS_Student_Manager class.
 */

class Test_SMS_Student_Manager extends SMS_Test_Case {

    /**
     * Student manager instance.
     */
    private $student_manager;

    /**
     * Set up test environment.
     */
    public function setUp(): void {
        parent::setUp();
        
        // Mock required classes
        if (!class_exists('SMS_Logger')) {
            class SMS_Logger {
                public static function get_instance() {
                    return new self();
                }
                public function log($message, $level = 'info', $context = []) {}
            }
        }
        
        if (!class_exists('SMS_Security')) {
            class SMS_Security {
                public static function get_instance() {
                    return new self();
                }
                public function sanitize_input($data, $type = 'text') {
                    return $data;
                }
                public function verify_nonce($action, $nonce) {
                    return true;
                }
                public function check_user_capability($capability) {
                    return current_user_can($capability);
                }
            }
        }
        
        $this->student_manager = new SMS_Student_Manager();
    }

    /**
     * Test student creation with valid data.
     */
    public function test_create_student_with_valid_data() {
        $admin_id = $this->create_test_admin();
        $this->set_current_user($admin_id);
        
        $student_data = $this->test_data->get_sample_student_data();
        $result = $this->student_manager->create_student($student_data);
        
        $this->assertNotWPError($result);
        $this->assertIsInt($result);
        $this->assertPostExists($result, 'sms_students');
        
        // Verify admission status is set to pending
        $admission_status = get_post_meta($result, 'admission_status', true);
        $this->assertEquals('pending', $admission_status);
    }

    /**
     * Test student creation with auto-approval.
     */
    public function test_create_student_with_auto_approval() {
        $admin_id = $this->create_test_admin();
        $this->set_current_user($admin_id);
        
        $student_data = $this->test_data->get_sample_student_data();
        $result = $this->student_manager->create_student($student_data, true);
        
        $this->assertNotWPError($result);
        
        // Verify admission status is set to approved
        $admission_status = get_post_meta($result, 'admission_status', true);
        $this->assertEquals('approved', $admission_status);
    }

    /**
     * Test student creation with invalid data.
     */
    public function test_create_student_with_invalid_data() {
        $admin_id = $this->create_test_admin();
        $this->set_current_user($admin_id);
        
        $invalid_data_sets = $this->test_data->get_invalid_student_data();
        
        foreach ($invalid_data_sets as $test_case => $invalid_data) {
            $result = $this->student_manager->create_student($invalid_data);
            $this->assertWPError($result, "Test case: {$test_case}");
        }
    }

    /**
     * Test admission approval workflow.
     */
    public function test_admission_approval_workflow() {
        $admin_id = $this->create_test_admin();
        $this->set_current_user($admin_id);
        
        // Create a pending student
        $student_data = $this->test_data->get_sample_student_data();
        $student_id = $this->student_manager->create_student($student_data);
        
        $this->assertNotWPError($student_id);
        
        // Approve the admission
        $result = $this->student_manager->approve_admission($student_id);
        $this->assertTrue($result);
        
        // Verify status change
        $admission_status = get_post_meta($student_id, 'admission_status', true);
        $this->assertEquals('approved', $admission_status);
        
        // Verify approval metadata
        $approved_by = get_post_meta($student_id, 'approved_by', true);
        $this->assertEquals($admin_id, $approved_by);
        
        $approved_date = get_post_meta($student_id, 'approved_date', true);
        $this->assertNotEmpty($approved_date);
    }

    /**
     * Test admission rejection workflow.
     */
    public function test_admission_rejection_workflow() {
        $admin_id = $this->create_test_admin();
        $this->set_current_user($admin_id);
        
        // Create a pending student
        $student_data = $this->test_data->get_sample_student_data();
        $student_id = $this->student_manager->create_student($student_data);
        
        $rejection_reason = 'Incomplete documentation';
        $result = $this->student_manager->reject_admission($student_id, $rejection_reason);
        $this->assertTrue($result);
        
        // Verify status change
        $admission_status = get_post_meta($student_id, 'admission_status', true);
        $this->assertEquals('rejected', $admission_status);
        
        // Verify rejection metadata
        $rejected_by = get_post_meta($student_id, 'rejected_by', true);
        $this->assertEquals($admin_id, $rejected_by);
        
        $stored_reason = get_post_meta($student_id, 'rejection_reason', true);
        $this->assertEquals($rejection_reason, $stored_reason);
    }

    /**
     * Test student enrollment workflow.
     */
    public function test_student_enrollment_workflow() {
        $admin_id = $this->create_test_admin();
        $this->set_current_user($admin_id);
        
        // Create and approve a student
        $student_data = $this->test_data->get_sample_student_data();
        $student_id = $this->student_manager->create_student($student_data);
        $this->student_manager->approve_admission($student_id);
        
        // Create a class
        $class_id = $this->factory->create_class();
        
        // Enroll the student
        $result = $this->student_manager->enroll_student($student_id, $class_id);
        $this->assertTrue($result);
        
        // Verify enrollment status
        $admission_status = get_post_meta($student_id, 'admission_status', true);
        $this->assertEquals('enrolled', $admission_status);
        
        // Verify class assignment
        $assigned_class = get_post_meta($student_id, 'assigned_class', true);
        $this->assertEquals($class_id, $assigned_class);
    }

    /**
     * Test enrollment with capacity check.
     */
    public function test_enrollment_capacity_check() {
        $admin_id = $this->create_test_admin();
        $this->set_current_user($admin_id);
        
        // Create a class with limited capacity
        $class_id = $this->factory->create_class([
            'meta_input' => ['capacity' => 2]
        ]);
        
        // Create and approve multiple students
        $students = [];
        for ($i = 0; $i < 3; $i++) {
            $student_data = $this->test_data->get_sample_student_data();
            $student_data['full_name'] = "Student {$i}";
            $student_id = $this->student_manager->create_student($student_data);
            $this->student_manager->approve_admission($student_id);
            $students[] = $student_id;
        }
        
        // Enroll first two students (should succeed)
        $result1 = $this->student_manager->enroll_student($students[0], $class_id);
        $this->assertTrue($result1);
        
        $result2 = $this->student_manager->enroll_student($students[1], $class_id);
        $this->assertTrue($result2);
        
        // Third enrollment should fail due to capacity
        $result3 = $this->student_manager->enroll_student($students[2], $class_id);
        $this->assertWPError($result3, 'capacity_exceeded');
    }

    /**
     * Test permission checks for admission management.
     */
    public function test_admission_permission_checks() {
        // Create a user without admin permissions
        $user_id = $this->factory->user->create(['role' => 'subscriber']);
        $this->set_current_user($user_id);
        
        $student_data = $this->test_data->get_sample_student_data();
        $student_id = $this->student_manager->create_student($student_data);
        
        // Attempt to approve admission without permission
        $result = $this->student_manager->approve_admission($student_id);
        $this->assertWPError($result, 'insufficient_permissions');
        
        // Attempt to reject admission without permission
        $result = $this->student_manager->reject_admission($student_id, 'Test reason');
        $this->assertWPError($result, 'insufficient_permissions');
        
        // Attempt to enroll student without permission
        $result = $this->student_manager->enroll_student($student_id);
        $this->assertWPError($result, 'insufficient_permissions');
    }

    /**
     * Test invalid status transitions.
     */
    public function test_invalid_status_transitions() {
        $admin_id = $this->create_test_admin();
        $this->set_current_user($admin_id);
        
        // Create and approve a student
        $student_data = $this->test_data->get_sample_student_data();
        $student_id = $this->student_manager->create_student($student_data);
        $this->student_manager->approve_admission($student_id);
        
        // Try to approve already approved student
        $result = $this->student_manager->approve_admission($student_id);
        $this->assertWPError($result, 'invalid_status');
        
        // Try to reject already approved student
        $result = $this->student_manager->reject_admission($student_id, 'Test reason');
        $this->assertWPError($result, 'invalid_status');
        
        // Enroll the student
        $class_id = $this->factory->create_class();
        $this->student_manager->enroll_student($student_id, $class_id);
        
        // Try to enroll already enrolled student
        $result = $this->student_manager->enroll_student($student_id, $class_id);
        $this->assertWPError($result, 'invalid_status');
    }

    /**
     * Test student data validation edge cases.
     */
    public function test_student_data_validation_edge_cases() {
        $admin_id = $this->create_test_admin();
        $this->set_current_user($admin_id);
        
        // Test with empty parent details array
        $student_data = $this->test_data->get_sample_student_data();
        $student_data['parent_details'] = [];
        
        $result = $this->student_manager->create_student($student_data);
        $this->assertWPError($result);
        
        // Test with malformed date
        $student_data = $this->test_data->get_sample_student_data();
        $student_data['date_of_birth'] = 'invalid-date';
        
        $result = $this->student_manager->create_student($student_data);
        $this->assertWPError($result);
        
        // Test with future date of birth
        $student_data = $this->test_data->get_sample_student_data();
        $student_data['date_of_birth'] = date('Y-m-d', strtotime('+1 year'));
        
        $result = $this->student_manager->create_student($student_data);
        $this->assertWPError($result);
    }

    /**
     * Test class enrollment count calculation.
     */
    public function test_class_enrollment_count() {
        $admin_id = $this->create_test_admin();
        $this->set_current_user($admin_id);
        
        $class_id = $this->factory->create_class();
        
        // Create and enroll multiple students
        $enrolled_count = 3;
        for ($i = 0; $i < $enrolled_count; $i++) {
            $student_data = $this->test_data->get_sample_student_data();
            $student_data['full_name'] = "Student {$i}";
            $student_id = $this->student_manager->create_student($student_data);
            $this->student_manager->approve_admission($student_id);
            $this->student_manager->enroll_student($student_id, $class_id);
        }
        
        // Use reflection to test private method
        $reflection = new ReflectionClass($this->student_manager);
        $method = $reflection->getMethod('get_class_enrolled_count');
        $method->setAccessible(true);
        
        $count = $method->invokeArgs($this->student_manager, [$class_id]);
        $this->assertEquals($enrolled_count, $count);
    }

    /**
     * Test admission workflow hooks are triggered.
     */
    public function test_admission_workflow_hooks() {
        $admin_id = $this->create_test_admin();
        $this->set_current_user($admin_id);
        
        $hooks_fired = [];
        
        // Add test hooks
        add_action('sms_student_admission_pending', function($student_id) use (&$hooks_fired) {
            $hooks_fired[] = 'pending';
        });
        
        add_action('sms_student_admission_approved', function($student_id) use (&$hooks_fired) {
            $hooks_fired[] = 'approved';
        });
        
        add_action('sms_student_enrolled', function($student_id, $class_id) use (&$hooks_fired) {
            $hooks_fired[] = 'enrolled';
        });
        
        // Create student (should trigger pending hook)
        $student_data = $this->test_data->get_sample_student_data();
        $student_id = $this->student_manager->create_student($student_data);
        
        // Approve admission (should trigger approved hook)
        $this->student_manager->approve_admission($student_id);
        
        // Enroll student (should trigger enrolled hook)
        $class_id = $this->factory->create_class();
        $this->student_manager->enroll_student($student_id, $class_id);
        
        $this->assertContains('pending', $hooks_fired);
        $this->assertContains('approved', $hooks_fired);
        $this->assertContains('enrolled', $hooks_fired);
    }
}