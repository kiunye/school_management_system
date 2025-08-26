<?php
/**
 * End-to-end workflow tests for SMS system.
 */

class Test_SMS_End_To_End_Workflows extends SMS_Test_Case {

    /**
     * Student manager instance.
     */
    private $student_manager;

    /**
     * Payment gateway manager instance.
     */
    private $gateway_manager;

    /**
     * Set up test environment.
     */
    public function setUp(): void {
        parent::setUp();
        
        // Initialize managers
        $this->student_manager = new SMS_Student_Manager();
        $this->gateway_manager = SMS_Payment_Gateway_Manager::get_instance();
        
        // Set up mock gateways and configurations
        $this->setup_test_environment();
    }

    /**
     * Set up test environment with mock services.
     */
    private function setup_test_environment() {
        // Mock SMS service
        if (!class_exists('SMS_Communication_Handler')) {
            class SMS_Communication_Handler {
                public function send_sms($recipients, $message, $template_vars = []) {
                    return ['status' => 'sent', 'message_id' => 'SMS_' . wp_rand(100000, 999999)];
                }
                
                public function send_bulk_sms($recipients, $message) {
                    return ['status' => 'sent', 'sent_count' => count($recipients)];
                }
            }
        }
        
        // Mock payment gateways
        $this->setup_mock_payment_gateways();
        
        // Set up test configurations
        update_option('sms_auto_create_parent_accounts', true);
        update_option('sms_send_admission_notifications', true);
        update_option('sms_send_payment_confirmations', true);
    }

    /**
     * Set up mock payment gateways.
     */
    private function setup_mock_payment_gateways() {
        // Create mock M-Pesa gateway
        $mpesa_gateway = new class('mpesa', 'M-Pesa') extends SMS_Payment_Gateway_Base {
            public function initialize_payment($amount, $phone_number, $reference, $additional_data = []) {
                return [
                    'status' => 'pending',
                    'transaction_id' => 'MPESA_' . wp_rand(100000, 999999),
                    'message' => 'Payment initiated'
                ];
            }
            
            public function verify_payment($transaction_id) {
                return [
                    'status' => 'completed',
                    'transaction_id' => $transaction_id,
                    'amount' => 1000,
                    'receipt_number' => 'MPESA_RECEIPT_' . wp_rand(100000, 999999)
                ];
            }
            
            public function handle_callback($callback_data) {
                return ['status' => 'processed'];
            }
        };
        
        $this->gateway_manager->register_gateway('mpesa', $mpesa_gateway);
    }

    /**
     * Test complete student admission to graduation workflow.
     */
    public function test_complete_student_lifecycle_workflow() {
        $admin_id = $this->create_test_admin();
        $this->set_current_user($admin_id);
        
        // Step 1: Student Application
        $student_data = $this->test_data->get_sample_student_data();
        $student_id = $this->student_manager->create_student($student_data);
        
        $this->assertNotWPError($student_id);
        $this->assertPostExists($student_id, 'sms_students');
        
        // Verify initial status
        $admission_status = get_post_meta($student_id, 'admission_status', true);
        $this->assertEquals('pending', $admission_status);
        
        // Step 2: Admission Review and Approval
        $approval_result = $this->student_manager->approve_admission($student_id);
        $this->assertTrue($approval_result);
        
        $admission_status = get_post_meta($student_id, 'admission_status', true);
        $this->assertEquals('approved', $admission_status);
        
        // Step 3: Class Assignment and Enrollment
        $class_id = $this->factory->create_class([
            'meta_input' => [
                'class_name' => 'Grade 5A',
                'capacity' => 30,
                'academic_year' => '2024-2025'
            ]
        ]);
        
        $enrollment_result = $this->student_manager->enroll_student($student_id, $class_id);
        $this->assertTrue($enrollment_result);
        
        $admission_status = get_post_meta($student_id, 'admission_status', true);
        $this->assertEquals('enrolled', $admission_status);
        
        $assigned_class = get_post_meta($student_id, 'assigned_class', true);
        $this->assertEquals($class_id, $assigned_class);
        
        // Step 4: Academic Progress (Attendance)
        $attendance_id = $this->factory->create_attendance([
            'meta_input' => [
                'class_id' => $class_id,
                'attendance_date' => current_time('Y-m-d'),
                'student_attendance_data' => [
                    ['student_id' => $student_id, 'status' => 'present']
                ]
            ]
        ]);
        
        $this->assertPostExists($attendance_id, 'sms_attendance');
        
        // Step 5: Academic Progress (Timetable)
        $timetable_id = $this->factory->create_timetable([
            'meta_input' => [
                'class_id' => $class_id,
                'academic_year' => '2024-2025',
                'term' => 'term-1',
                'schedule_data' => $this->test_data->get_sample_timetable_data()['schedule_data']
            ]
        ]);
        
        $this->assertPostExists($timetable_id, 'sms_timetables');
        
        // Step 6: Graduation (Status Update)
        update_post_meta($student_id, 'student_status', 'graduated');
        update_post_meta($student_id, 'graduation_date', current_time('Y-m-d'));
        
        $student_status = get_post_meta($student_id, 'student_status', true);
        $this->assertEquals('graduated', $student_status);
        
        $graduation_date = get_post_meta($student_id, 'graduation_date', true);
        $this->assertNotEmpty($graduation_date);
    }

    /**
     * Test complete financial workflow from fee setup to payment collection.
     */
    public function test_complete_financial_workflow() {
        $admin_id = $this->create_test_admin();
        $this->set_current_user($admin_id);
        
        // Step 1: Create Student
        $student_data = $this->test_data->get_sample_student_data();
        $student_id = $this->student_manager->create_student($student_data, true); // Auto-approve
        
        $class_id = $this->factory->create_class();
        $this->student_manager->enroll_student($student_id, $class_id);
        
        // Step 2: Set up Fee Structure
        $fee_data = $this->test_data->get_sample_fee_data()['tuition'];
        $fee_id = $this->factory->create_fee([
            'post_title' => 'Tuition Fee - Term 1',
            'meta_input' => $fee_data
        ]);
        
        $this->assertPostExists($fee_id, 'sms_fees');
        
        // Step 3: Generate Invoice
        $invoice_id = $this->factory->create_invoice([
            'meta_input' => [
                'invoice_number' => 'INV-2024-' . wp_rand(1000, 9999),
                'student_id' => $student_id,
                'total_amount' => $fee_data['amount'],
                'due_date' => $fee_data['due_date'],
                'status' => 'pending',
                'fee_items' => [
                    [
                        'fee_id' => $fee_id,
                        'description' => 'Tuition Fee - Term 1',
                        'amount' => $fee_data['amount']
                    ]
                ]
            ]
        ]);
        
        $this->assertPostExists($invoice_id, 'sms_invoices');
        
        // Step 4: Process Payment via M-Pesa
        $parent_phone = $student_data['parent_details'][0]['parent_phone'];
        $invoice_number = get_post_meta($invoice_id, 'invoice_number', true);
        
        $payment_result = $this->gateway_manager->process_payment(
            'mpesa',
            $fee_data['amount'],
            $parent_phone,
            $invoice_number
        );
        
        $this->assertNotWPError($payment_result);
        $this->assertEquals('pending', $payment_result['status']);
        
        // Step 5: Record Transaction
        $transaction_id = $this->factory->create_transaction([
            'meta_input' => [
                'invoice_id' => $invoice_id,
                'student_id' => $student_id,
                'amount' => $fee_data['amount'],
                'payment_method' => 'mpesa',
                'gateway_transaction_id' => $payment_result['transaction_id'],
                'payment_status' => 'pending',
                'payment_date' => current_time('mysql')
            ]
        ]);
        
        $this->assertPostExists($transaction_id, 'sms_transactions');
        
        // Step 6: Verify Payment
        $verification_result = $this->gateway_manager->verify_payment(
            'mpesa',
            $payment_result['transaction_id']
        );
        
        $this->assertNotWPError($verification_result);
        $this->assertEquals('completed', $verification_result['status']);
        
        // Step 7: Update Transaction and Invoice Status
        update_post_meta($transaction_id, 'payment_status', 'completed');
        update_post_meta($transaction_id, 'gateway_response', $verification_result);
        
        update_post_meta($invoice_id, 'status', 'paid');
        update_post_meta($invoice_id, 'paid_amount', $fee_data['amount']);
        update_post_meta($invoice_id, 'paid_date', current_time('mysql'));
        
        // Verify final status
        $transaction_status = get_post_meta($transaction_id, 'payment_status', true);
        $this->assertEquals('completed', $transaction_status);
        
        $invoice_status = get_post_meta($invoice_id, 'status', true);
        $this->assertEquals('paid', $invoice_status);
    }

    /**
     * Test communication workflow including SMS and email notifications.
     */
    public function test_communication_workflow() {
        $admin_id = $this->create_test_admin();
        $this->set_current_user($admin_id);
        
        // Step 1: Create Students and Class
        $students = $this->factory->create_students(3);
        $class_id = $this->factory->create_class();
        
        // Enroll students in class
        foreach ($students as $student_id) {
            $this->student_manager->approve_admission($student_id);
            $this->student_manager->enroll_student($student_id, $class_id);
        }
        
        // Step 2: Create and Send Notice
        $notice_id = $this->factory->create_notice([
            'post_title' => 'Important School Notice',
            'post_content' => 'This is an important notice for all Grade 5 students.',
            'meta_input' => [
                'target_audience' => ['class_' . $class_id],
                'priority_level' => 'high',
                'expiry_date' => date('Y-m-d', strtotime('+7 days'))
            ]
        ]);
        
        $this->assertPostExists($notice_id, 'sms_notices');
        
        // Step 3: Send SMS Notifications
        $sms_handler = new SMS_Communication_Handler();
        
        // Get parent phone numbers
        $parent_phones = [];
        foreach ($students as $student_id) {
            $parent_details = get_post_meta($student_id, 'parent_details', true);
            if (is_array($parent_details)) {
                foreach ($parent_details as $parent) {
                    if (!empty($parent['parent_phone'])) {
                        $parent_phones[] = $parent['parent_phone'];
                    }
                }
            }
        }
        
        $sms_result = $sms_handler->send_bulk_sms(
            $parent_phones,
            'Important school notice has been posted. Please check the school portal for details.'
        );
        
        $this->assertEquals('sent', $sms_result['status']);
        $this->assertEquals(count($parent_phones), $sms_result['sent_count']);
        
        // Step 4: Attendance Alert Workflow
        $attendance_data = [
            'class_id' => $class_id,
            'attendance_date' => current_time('Y-m-d'),
            'student_attendance_data' => [
                ['student_id' => $students[0], 'status' => 'present'],
                ['student_id' => $students[1], 'status' => 'absent'],
                ['student_id' => $students[2], 'status' => 'present']
            ]
        ];
        
        $attendance_id = $this->factory->create_attendance([
            'meta_input' => $attendance_data
        ]);
        
        // Send absence notification for absent student
        $absent_student_id = $students[1];
        $absent_student_parents = get_post_meta($absent_student_id, 'parent_details', true);
        
        if (is_array($absent_student_parents)) {
            $parent_phone = $absent_student_parents[0]['parent_phone'];
            $student_name = get_post_meta($absent_student_id, 'full_name', true);
            $class_name = get_post_meta($class_id, 'class_name', true);
            
            $absence_message = "Dear Parent, {$student_name} was absent from {$class_name} today. Please contact the school if this is unexpected.";
            
            $absence_sms_result = $sms_handler->send_sms(
                [$parent_phone],
                $absence_message
            );
            
            $this->assertEquals('sent', $absence_sms_result['status']);
        }
    }

    /**
     * Test transport management workflow.
     */
    public function test_transport_management_workflow() {
        $admin_id = $this->create_test_admin();
        $this->set_current_user($admin_id);
        
        // Step 1: Create Transport Route
        $route_data = $this->test_data->get_sample_transport_data();
        $route_id = $this->factory->create_transport_route([
            'post_title' => $route_data['route_name'],
            'meta_input' => $route_data
        ]);
        
        $this->assertPostExists($route_id, 'sms_transport_routes');
        
        // Step 2: Create Students
        $students = $this->factory->create_students(5);
        
        // Step 3: Assign Students to Route
        $assigned_students = array_slice($students, 0, 3); // Assign 3 out of 5 students
        
        foreach ($assigned_students as $student_id) {
            update_post_meta($student_id, 'transport_route', $route_id);
            update_post_meta($student_id, 'transport_stop', $route_data['stops_data'][0]['stop_name']);
            update_post_meta($student_id, 'transport_fee', $route_data['route_fee']);
        }
        
        // Step 4: Verify Route Capacity
        $route_capacity = get_post_meta($route_id, 'bus_capacity', true);
        $assigned_count = count($assigned_students);
        
        $this->assertLessThanOrEqual($route_capacity, $assigned_count);
        
        // Step 5: Generate Transport Fee Invoices
        foreach ($assigned_students as $student_id) {
            $transport_invoice_id = $this->factory->create_invoice([
                'meta_input' => [
                    'invoice_number' => 'TRANSPORT-' . wp_rand(1000, 9999),
                    'student_id' => $student_id,
                    'total_amount' => $route_data['route_fee'],
                    'due_date' => date('Y-m-d', strtotime('+15 days')),
                    'status' => 'pending',
                    'fee_items' => [
                        [
                            'description' => 'Transport Fee - ' . $route_data['route_name'],
                            'amount' => $route_data['route_fee']
                        ]
                    ]
                ]
            ]);
            
            $this->assertPostExists($transport_invoice_id, 'sms_invoices');
        }
        
        // Step 6: Route Change Notification
        $new_pickup_time = '07:15';
        $stops_data = get_post_meta($route_id, 'stops_data', true);
        $stops_data[0]['pickup_time'] = $new_pickup_time;
        update_post_meta($route_id, 'stops_data', $stops_data);
        
        // Send notifications to affected students
        $sms_handler = new SMS_Communication_Handler();
        
        foreach ($assigned_students as $student_id) {
            $parent_details = get_post_meta($student_id, 'parent_details', true);
            $student_name = get_post_meta($student_id, 'full_name', true);
            
            if (is_array($parent_details) && !empty($parent_details[0]['parent_phone'])) {
                $notification_message = "Dear Parent, transport pickup time for {$student_name} has been updated to {$new_pickup_time}. Route: {$route_data['route_name']}.";
                
                $notification_result = $sms_handler->send_sms(
                    [$parent_details[0]['parent_phone']],
                    $notification_message
                );
                
                $this->assertEquals('sent', $notification_result['status']);
            }
        }
    }

    /**
     * Test academic reporting workflow.
     */
    public function test_academic_reporting_workflow() {
        $admin_id = $this->create_test_admin();
        $this->set_current_user($admin_id);
        
        // Step 1: Set up Academic Structure
        $class_id = $this->factory->create_class([
            'meta_input' => [
                'class_name' => 'Grade 5A',
                'capacity' => 30,
                'academic_year' => '2024-2025'
            ]
        ]);
        
        $students = $this->factory->create_students(10);
        
        // Enroll students
        foreach ($students as $student_id) {
            $this->student_manager->approve_admission($student_id);
            $this->student_manager->enroll_student($student_id, $class_id);
        }
        
        // Step 2: Record Attendance for Multiple Days
        $attendance_records = [];
        for ($day = 0; $day < 5; $day++) {
            $attendance_date = date('Y-m-d', strtotime("-{$day} days"));
            
            $attendance_data = [];
            foreach ($students as $index => $student_id) {
                // Simulate some absences
                $status = ($index % 3 === 0 && $day % 2 === 0) ? 'absent' : 'present';
                $attendance_data[] = ['student_id' => $student_id, 'status' => $status];
            }
            
            $attendance_id = $this->factory->create_attendance([
                'meta_input' => [
                    'class_id' => $class_id,
                    'attendance_date' => $attendance_date,
                    'student_attendance_data' => $attendance_data
                ]
            ]);
            
            $attendance_records[] = $attendance_id;
        }
        
        // Step 3: Generate Attendance Report
        $report_data = $this->generate_attendance_report($class_id, $attendance_records);
        
        $this->assertIsArray($report_data);
        $this->assertArrayHasKey('total_students', $report_data);
        $this->assertArrayHasKey('attendance_summary', $report_data);
        $this->assertEquals(10, $report_data['total_students']);
        
        // Step 4: Financial Reporting
        $financial_data = $this->generate_financial_report($students);
        
        $this->assertIsArray($financial_data);
        $this->assertArrayHasKey('total_invoices', $financial_data);
        $this->assertArrayHasKey('total_amount', $financial_data);
        
        // Step 5: Export Report Data
        $export_data = [
            'academic_report' => $report_data,
            'financial_report' => $financial_data,
            'generated_at' => current_time('mysql'),
            'generated_by' => $admin_id
        ];
        
        $this->assertIsArray($export_data);
        $this->assertArrayHasKey('generated_at', $export_data);
        $this->assertArrayHasKey('generated_by', $export_data);
    }

    /**
     * Generate attendance report for testing.
     */
    private function generate_attendance_report($class_id, $attendance_records) {
        $total_students = 0;
        $total_present = 0;
        $total_absent = 0;
        
        foreach ($attendance_records as $attendance_id) {
            $attendance_data = get_post_meta($attendance_id, 'student_attendance_data', true);
            
            if (is_array($attendance_data)) {
                foreach ($attendance_data as $record) {
                    $total_students++;
                    if ($record['status'] === 'present') {
                        $total_present++;
                    } else {
                        $total_absent++;
                    }
                }
            }
        }
        
        $attendance_rate = $total_students > 0 ? ($total_present / $total_students) * 100 : 0;
        
        return [
            'class_id' => $class_id,
            'total_students' => $total_students / count($attendance_records), // Average per day
            'attendance_summary' => [
                'total_present' => $total_present,
                'total_absent' => $total_absent,
                'attendance_rate' => round($attendance_rate, 2)
            ],
            'period' => [
                'start_date' => date('Y-m-d', strtotime('-4 days')),
                'end_date' => current_time('Y-m-d')
            ]
        ];
    }

    /**
     * Generate financial report for testing.
     */
    private function generate_financial_report($students) {
        $total_invoices = 0;
        $total_amount = 0;
        $paid_amount = 0;
        
        // Query invoices for these students
        $invoice_query = new WP_Query([
            'post_type' => 'sms_invoices',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => 'student_id',
                    'value' => $students,
                    'compare' => 'IN'
                ]
            ]
        ]);
        
        if ($invoice_query->have_posts()) {
            while ($invoice_query->have_posts()) {
                $invoice_query->the_post();
                $invoice_id = get_the_ID();
                
                $total_invoices++;
                $amount = get_post_meta($invoice_id, 'total_amount', true);
                $status = get_post_meta($invoice_id, 'status', true);
                
                $total_amount += $amount;
                
                if ($status === 'paid') {
                    $paid_amount += $amount;
                }
            }
            wp_reset_postdata();
        }
        
        $outstanding_amount = $total_amount - $paid_amount;
        $collection_rate = $total_amount > 0 ? ($paid_amount / $total_amount) * 100 : 0;
        
        return [
            'total_invoices' => $total_invoices,
            'total_amount' => $total_amount,
            'paid_amount' => $paid_amount,
            'outstanding_amount' => $outstanding_amount,
            'collection_rate' => round($collection_rate, 2)
        ];
    }

    /**
     * Test error handling in workflows.
     */
    public function test_workflow_error_handling() {
        $admin_id = $this->create_test_admin();
        $this->set_current_user($admin_id);
        
        // Test student creation with invalid data
        $invalid_student_data = [
            'full_name' => '', // Missing required field
            'date_of_birth' => '2030-01-01', // Future date
            'parent_details' => [] // Missing parent info
        ];
        
        $result = $this->student_manager->create_student($invalid_student_data);
        $this->assertWPError($result);
        
        // Test payment with invalid amount
        $payment_result = $this->gateway_manager->process_payment(
            'mpesa',
            -100, // Invalid amount
            '+254712345678',
            'TEST_REF'
        );
        
        $this->assertWPError($payment_result);
        
        // Test enrollment without approval
        $student_id = $this->factory->create_student();
        $class_id = $this->factory->create_class();
        
        $enrollment_result = $this->student_manager->enroll_student($student_id, $class_id);
        $this->assertWPError($enrollment_result, 'invalid_status');
    }
}