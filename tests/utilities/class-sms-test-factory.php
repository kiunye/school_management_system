<?php
/**
 * Test factory for creating test data.
 */

class SMS_Test_Factory extends WP_UnitTest_Factory {

    /**
     * Create a test student.
     */
    public function create_student($args = []) {
        $defaults = [
            'post_type' => 'sms_students',
            'post_title' => 'Test Student',
            'post_status' => 'publish',
            'meta_input' => [
                'full_name' => 'John Doe',
                'date_of_birth' => '2010-01-15',
                'gender' => 'male',
                'admission_status' => 'pending',
                'student_status' => 'active',
                'parent_details' => [
                    [
                        'parent_name' => 'Jane Doe',
                        'parent_phone' => '+254712345678',
                        'parent_email' => 'jane.doe@example.com',
                        'relationship' => 'mother'
                    ]
                ]
            ]
        ];
        
        $args = wp_parse_args($args, $defaults);
        $student_id = $this->post->create($args);
        
        // Set ACF fields if meta_input is provided
        if (isset($args['meta_input'])) {
            foreach ($args['meta_input'] as $key => $value) {
                update_post_meta($student_id, $key, $value);
            }
        }
        
        return $student_id;
    }

    /**
     * Create a test class.
     */
    public function create_class($args = []) {
        $defaults = [
            'post_type' => 'sms_classes',
            'post_title' => 'Test Class',
            'post_status' => 'publish',
            'meta_input' => [
                'class_name' => 'Grade 5A',
                'grade_level' => 'grade-5',
                'capacity' => 30,
                'academic_year' => '2024-2025'
            ]
        ];
        
        $args = wp_parse_args($args, $defaults);
        $class_id = $this->post->create($args);
        
        // Set ACF fields
        if (isset($args['meta_input'])) {
            foreach ($args['meta_input'] as $key => $value) {
                update_post_meta($class_id, $key, $value);
            }
        }
        
        return $class_id;
    }

    /**
     * Create a test fee.
     */
    public function create_fee($args = []) {
        $defaults = [
            'post_type' => 'sms_fees',
            'post_title' => 'Test Fee',
            'post_status' => 'publish',
            'meta_input' => [
                'fee_type' => 'tuition',
                'amount' => 5000,
                'due_date' => date('Y-m-d', strtotime('+30 days')),
                'academic_term' => 'term-1',
                'penalty_rate' => 5
            ]
        ];
        
        $args = wp_parse_args($args, $defaults);
        $fee_id = $this->post->create($args);
        
        // Set ACF fields
        if (isset($args['meta_input'])) {
            foreach ($args['meta_input'] as $key => $value) {
                update_post_meta($fee_id, $key, $value);
            }
        }
        
        return $fee_id;
    }

    /**
     * Create a test invoice.
     */
    public function create_invoice($args = []) {
        $defaults = [
            'post_type' => 'sms_invoices',
            'post_title' => 'Test Invoice',
            'post_status' => 'publish',
            'meta_input' => [
                'invoice_number' => 'INV-' . date('Y') . '-' . wp_rand(1000, 9999),
                'student_id' => 0,
                'total_amount' => 5000,
                'due_date' => date('Y-m-d', strtotime('+30 days')),
                'status' => 'pending',
                'fee_items' => []
            ]
        ];
        
        $args = wp_parse_args($args, $defaults);
        $invoice_id = $this->post->create($args);
        
        // Set ACF fields
        if (isset($args['meta_input'])) {
            foreach ($args['meta_input'] as $key => $value) {
                update_post_meta($invoice_id, $key, $value);
            }
        }
        
        return $invoice_id;
    }

    /**
     * Create a test transaction.
     */
    public function create_transaction($args = []) {
        $defaults = [
            'post_type' => 'sms_transactions',
            'post_title' => 'Test Transaction',
            'post_status' => 'publish',
            'meta_input' => [
                'transaction_id' => 'TXN-' . wp_rand(100000, 999999),
                'invoice_id' => 0,
                'student_id' => 0,
                'amount' => 5000,
                'payment_method' => 'mpesa',
                'gateway_transaction_id' => 'MPESA-' . wp_rand(100000, 999999),
                'payment_status' => 'completed',
                'payment_date' => current_time('mysql')
            ]
        ];
        
        $args = wp_parse_args($args, $defaults);
        $transaction_id = $this->post->create($args);
        
        // Set ACF fields
        if (isset($args['meta_input'])) {
            foreach ($args['meta_input'] as $key => $value) {
                update_post_meta($transaction_id, $key, $value);
            }
        }
        
        return $transaction_id;
    }

    /**
     * Create a test attendance record.
     */
    public function create_attendance($args = []) {
        $defaults = [
            'post_type' => 'sms_attendance',
            'post_title' => 'Test Attendance',
            'post_status' => 'publish',
            'meta_input' => [
                'class_id' => 0,
                'attendance_date' => current_time('Y-m-d'),
                'student_attendance_data' => [],
                'marked_by_teacher_id' => 0
            ]
        ];
        
        $args = wp_parse_args($args, $defaults);
        $attendance_id = $this->post->create($args);
        
        // Set ACF fields
        if (isset($args['meta_input'])) {
            foreach ($args['meta_input'] as $key => $value) {
                update_post_meta($attendance_id, $key, $value);
            }
        }
        
        return $attendance_id;
    }

    /**
     * Create a test timetable.
     */
    public function create_timetable($args = []) {
        $defaults = [
            'post_type' => 'sms_timetables',
            'post_title' => 'Test Timetable',
            'post_status' => 'publish',
            'meta_input' => [
                'class_id' => 0,
                'academic_year' => '2024-2025',
                'term' => 'term-1',
                'schedule_data' => []
            ]
        ];
        
        $args = wp_parse_args($args, $defaults);
        $timetable_id = $this->post->create($args);
        
        // Set ACF fields
        if (isset($args['meta_input'])) {
            foreach ($args['meta_input'] as $key => $value) {
                update_post_meta($timetable_id, $key, $value);
            }
        }
        
        return $timetable_id;
    }

    /**
     * Create a test notice.
     */
    public function create_notice($args = []) {
        $defaults = [
            'post_type' => 'sms_notices',
            'post_title' => 'Test Notice',
            'post_status' => 'publish',
            'post_content' => 'This is a test notice content.',
            'meta_input' => [
                'target_audience' => ['all'],
                'priority_level' => 'normal',
                'expiry_date' => date('Y-m-d', strtotime('+7 days'))
            ]
        ];
        
        $args = wp_parse_args($args, $defaults);
        $notice_id = $this->post->create($args);
        
        // Set ACF fields
        if (isset($args['meta_input'])) {
            foreach ($args['meta_input'] as $key => $value) {
                update_post_meta($notice_id, $key, $value);
            }
        }
        
        return $notice_id;
    }

    /**
     * Create a test transport route.
     */
    public function create_transport_route($args = []) {
        $defaults = [
            'post_type' => 'sms_transport_routes',
            'post_title' => 'Test Route',
            'post_status' => 'publish',
            'meta_input' => [
                'route_name' => 'Route A',
                'stops_data' => [
                    ['stop_name' => 'Stop 1', 'pickup_time' => '07:00'],
                    ['stop_name' => 'Stop 2', 'pickup_time' => '07:15']
                ],
                'bus_capacity' => 40,
                'route_fee' => 2000
            ]
        ];
        
        $args = wp_parse_args($args, $defaults);
        $route_id = $this->post->create($args);
        
        // Set ACF fields
        if (isset($args['meta_input'])) {
            foreach ($args['meta_input'] as $key => $value) {
                update_post_meta($route_id, $key, $value);
            }
        }
        
        return $route_id;
    }

    /**
     * Create multiple test students.
     */
    public function create_students($count = 5, $args = []) {
        $students = [];
        
        for ($i = 1; $i <= $count; $i++) {
            $student_args = wp_parse_args($args, [
                'post_title' => "Test Student {$i}",
                'meta_input' => [
                    'full_name' => "Student {$i}",
                    'date_of_birth' => date('Y-m-d', strtotime("-{$i} years")),
                    'parent_details' => [
                        [
                            'parent_name' => "Parent {$i}",
                            'parent_phone' => '+25471234567' . $i,
                            'parent_email' => "parent{$i}@example.com"
                        ]
                    ]
                ]
            ]);
            
            $students[] = $this->create_student($student_args);
        }
        
        return $students;
    }

    /**
     * Create sample data for testing workflows.
     */
    public function create_sample_workflow_data() {
        // Create classes
        $class_1 = $this->create_class([
            'post_title' => 'Grade 5A',
            'meta_input' => [
                'class_name' => 'Grade 5A',
                'grade_level' => 'grade-5',
                'capacity' => 30
            ]
        ]);
        
        $class_2 = $this->create_class([
            'post_title' => 'Grade 6B',
            'meta_input' => [
                'class_name' => 'Grade 6B',
                'grade_level' => 'grade-6',
                'capacity' => 25
            ]
        ]);
        
        // Create students
        $students = $this->create_students(10);
        
        // Create fees
        $tuition_fee = $this->create_fee([
            'post_title' => 'Tuition Fee',
            'meta_input' => [
                'fee_type' => 'tuition',
                'amount' => 15000,
                'academic_term' => 'term-1'
            ]
        ]);
        
        $transport_fee = $this->create_fee([
            'post_title' => 'Transport Fee',
            'meta_input' => [
                'fee_type' => 'transport',
                'amount' => 3000,
                'academic_term' => 'term-1'
            ]
        ]);
        
        // Create transport route
        $route = $this->create_transport_route();
        
        return [
            'classes' => [$class_1, $class_2],
            'students' => $students,
            'fees' => [$tuition_fee, $transport_fee],
            'route' => $route
        ];
    }
}