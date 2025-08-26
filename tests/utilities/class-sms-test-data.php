<?php
/**
 * Test data provider for realistic testing scenarios.
 */

class SMS_Test_Data {

    /**
     * Get sample student data.
     */
    public function get_sample_student_data() {
        return [
            'full_name' => 'John Doe',
            'date_of_birth' => '2010-03-15',
            'gender' => 'male',
            'national_id' => '12345678',
            'parent_details' => [
                [
                    'parent_name' => 'Jane Doe',
                    'parent_phone' => '+254712345678',
                    'parent_email' => 'jane.doe@example.com',
                    'relationship' => 'mother',
                    'occupation' => 'Teacher',
                    'workplace' => 'ABC School'
                ],
                [
                    'parent_name' => 'John Doe Sr.',
                    'parent_phone' => '+254723456789',
                    'parent_email' => 'john.doe@example.com',
                    'relationship' => 'father',
                    'occupation' => 'Engineer',
                    'workplace' => 'XYZ Company'
                ]
            ],
            'medical_conditions' => 'None',
            'allergies' => 'Peanuts',
            'blood_group' => 'O+',
            'medications' => 'None',
            'emergency_contacts' => [
                [
                    'contact_name' => 'Mary Smith',
                    'contact_phone' => '+254734567890',
                    'relationship' => 'aunt'
                ]
            ]
        ];
    }

    /**
     * Get invalid student data for validation testing.
     */
    public function get_invalid_student_data() {
        return [
            'missing_name' => [
                'date_of_birth' => '2010-03-15',
                'gender' => 'male'
            ],
            'invalid_date' => [
                'full_name' => 'John Doe',
                'date_of_birth' => '2030-03-15', // Future date
                'gender' => 'male'
            ],
            'missing_parent_info' => [
                'full_name' => 'John Doe',
                'date_of_birth' => '2010-03-15',
                'gender' => 'male'
            ],
            'invalid_parent_email' => [
                'full_name' => 'John Doe',
                'date_of_birth' => '2010-03-15',
                'gender' => 'male',
                'parent_details' => [
                    [
                        'parent_name' => 'Jane Doe',
                        'parent_phone' => '+254712345678',
                        'parent_email' => 'invalid-email'
                    ]
                ]
            ]
        ];
    }

    /**
     * Get sample class data.
     */
    public function get_sample_class_data() {
        return [
            'class_name' => 'Grade 5A',
            'grade_level' => 'grade-5',
            'capacity' => 30,
            'academic_year' => '2024-2025',
            'class_teacher_id' => 0,
            'subject_assignments' => [
                'mathematics' => 'teacher_1',
                'english' => 'teacher_2',
                'science' => 'teacher_3'
            ]
        ];
    }

    /**
     * Get sample fee structure data.
     */
    public function get_sample_fee_data() {
        return [
            'tuition' => [
                'fee_type' => 'tuition',
                'amount' => 15000,
                'due_date' => date('Y-m-d', strtotime('+30 days')),
                'academic_term' => 'term-1',
                'penalty_rate' => 5,
                'installment_options' => [
                    'full_payment' => 15000,
                    'two_installments' => [7500, 7500],
                    'three_installments' => [5000, 5000, 5000]
                ]
            ],
            'transport' => [
                'fee_type' => 'transport',
                'amount' => 3000,
                'due_date' => date('Y-m-d', strtotime('+15 days')),
                'academic_term' => 'term-1',
                'penalty_rate' => 10
            ],
            'meals' => [
                'fee_type' => 'meals',
                'amount' => 2000,
                'due_date' => date('Y-m-d', strtotime('+20 days')),
                'academic_term' => 'term-1',
                'penalty_rate' => 5
            ]
        ];
    }

    /**
     * Get sample payment gateway responses.
     */
    public function get_sample_gateway_responses() {
        return [
            'mpesa_success' => [
                'MerchantRequestID' => 'test-merchant-123',
                'CheckoutRequestID' => 'test-checkout-456',
                'ResponseCode' => '0',
                'ResponseDescription' => 'Success. Request accepted for processing',
                'CustomerMessage' => 'Success. Request accepted for processing'
            ],
            'mpesa_callback_success' => [
                'Body' => [
                    'stkCallback' => [
                        'MerchantRequestID' => 'test-merchant-123',
                        'CheckoutRequestID' => 'test-checkout-456',
                        'ResultCode' => 0,
                        'ResultDesc' => 'The service request is processed successfully.',
                        'CallbackMetadata' => [
                            'Item' => [
                                ['Name' => 'Amount', 'Value' => 5000],
                                ['Name' => 'MpesaReceiptNumber', 'Value' => 'TEST123456'],
                                ['Name' => 'TransactionDate', 'Value' => 20240315120000],
                                ['Name' => 'PhoneNumber', 'Value' => 254712345678]
                            ]
                        ]
                    ]
                ]
            ],
            'airtel_success' => [
                'status' => 'success',
                'message' => 'Transaction initiated successfully',
                'transaction_id' => 'AIRTEL-123456789',
                'reference' => 'REF-789'
            ]
        ];
    }

    /**
     * Get sample SMS templates and data.
     */
    public function get_sample_sms_data() {
        return [
            'attendance_alert' => [
                'template' => 'Dear {parent_name}, {student_name} was absent from {class_name} on {date}. Please contact the school if this is unexpected.',
                'variables' => [
                    'parent_name' => 'Jane Doe',
                    'student_name' => 'John Doe',
                    'class_name' => 'Grade 5A',
                    'date' => '2024-03-15'
                ],
                'expected' => 'Dear Jane Doe, John Doe was absent from Grade 5A on 2024-03-15. Please contact the school if this is unexpected.'
            ],
            'fee_reminder' => [
                'template' => 'Dear {parent_name}, {student_name}\'s {fee_type} of KES {amount} is due on {due_date}. Pay via M-Pesa: {mpesa_number}',
                'variables' => [
                    'parent_name' => 'Jane Doe',
                    'student_name' => 'John Doe',
                    'fee_type' => 'tuition fee',
                    'amount' => '15,000',
                    'due_date' => '2024-04-15',
                    'mpesa_number' => '123456'
                ],
                'expected' => 'Dear Jane Doe, John Doe\'s tuition fee of KES 15,000 is due on 2024-04-15. Pay via M-Pesa: 123456'
            ]
        ];
    }

    /**
     * Get sample timetable data.
     */
    public function get_sample_timetable_data() {
        return [
            'class_id' => 0,
            'academic_year' => '2024-2025',
            'term' => 'term-1',
            'schedule_data' => [
                'monday' => [
                    ['time' => '08:00-09:00', 'subject' => 'mathematics', 'teacher_id' => 1],
                    ['time' => '09:00-10:00', 'subject' => 'english', 'teacher_id' => 2],
                    ['time' => '10:30-11:30', 'subject' => 'science', 'teacher_id' => 3],
                    ['time' => '11:30-12:30', 'subject' => 'social_studies', 'teacher_id' => 4]
                ],
                'tuesday' => [
                    ['time' => '08:00-09:00', 'subject' => 'english', 'teacher_id' => 2],
                    ['time' => '09:00-10:00', 'subject' => 'mathematics', 'teacher_id' => 1],
                    ['time' => '10:30-11:30', 'subject' => 'art', 'teacher_id' => 5],
                    ['time' => '11:30-12:30', 'subject' => 'physical_education', 'teacher_id' => 6]
                ]
            ]
        ];
    }

    /**
     * Get sample attendance data.
     */
    public function get_sample_attendance_data() {
        return [
            'class_id' => 0,
            'attendance_date' => current_time('Y-m-d'),
            'student_attendance_data' => [
                ['student_id' => 1, 'status' => 'present'],
                ['student_id' => 2, 'status' => 'absent'],
                ['student_id' => 3, 'status' => 'present'],
                ['student_id' => 4, 'status' => 'late'],
                ['student_id' => 5, 'status' => 'present']
            ],
            'notes' => 'Regular attendance marking'
        ];
    }

    /**
     * Get sample transport route data.
     */
    public function get_sample_transport_data() {
        return [
            'route_name' => 'Route A - City Center',
            'stops_data' => [
                [
                    'stop_name' => 'City Center Bus Stop',
                    'pickup_time' => '07:00',
                    'dropoff_time' => '15:30',
                    'coordinates' => '-1.2921, 36.8219'
                ],
                [
                    'stop_name' => 'Shopping Mall',
                    'pickup_time' => '07:15',
                    'dropoff_time' => '15:15',
                    'coordinates' => '-1.2850, 36.8172'
                ],
                [
                    'stop_name' => 'Residential Area',
                    'pickup_time' => '07:30',
                    'dropoff_time' => '15:00',
                    'coordinates' => '-1.2780, 36.8125'
                ]
            ],
            'timing_schedule' => [
                'morning_departure' => '06:45',
                'school_arrival' => '08:00',
                'afternoon_departure' => '14:30',
                'route_completion' => '16:00'
            ],
            'bus_capacity' => 40,
            'driver_details' => [
                'driver_name' => 'Peter Kamau',
                'driver_phone' => '+254722334455',
                'license_number' => 'DL123456',
                'experience_years' => 5
            ],
            'route_fee' => 3000
        ];
    }

    /**
     * Get sample report data.
     */
    public function get_sample_report_data() {
        return [
            'financial_summary' => [
                'total_fees_collected' => 450000,
                'outstanding_fees' => 125000,
                'collection_rate' => 78.3,
                'payment_methods' => [
                    'mpesa' => 320000,
                    'airtel_money' => 80000,
                    'bank_transfer' => 50000
                ]
            ],
            'attendance_summary' => [
                'total_students' => 150,
                'average_attendance' => 92.5,
                'absent_today' => 12,
                'late_arrivals' => 5
            ],
            'academic_summary' => [
                'total_classes' => 8,
                'total_teachers' => 12,
                'student_teacher_ratio' => 12.5,
                'active_subjects' => 10
            ]
        ];
    }

    /**
     * Get phone number test cases.
     */
    public function get_phone_number_test_cases() {
        return [
            'valid' => [
                '+254712345678',
                '+254722345678',
                '254712345678',
                '0712345678',
                '0722345678'
            ],
            'invalid' => [
                '712345678',      // Missing country code
                '+255712345678',  // Wrong country code
                '+25471234567',   // Too short
                '+2547123456789', // Too long
                '+254812345678',  // Invalid network code
                'invalid-phone'   // Non-numeric
            ]
        ];
    }

    /**
     * Get email test cases.
     */
    public function get_email_test_cases() {
        return [
            'valid' => [
                'test@example.com',
                'user.name@domain.co.ke',
                'parent123@school.edu'
            ],
            'invalid' => [
                'invalid-email',
                '@domain.com',
                'user@',
                'user name@domain.com'
            ]
        ];
    }
}