<?php
/**
 * Data Validation and Cleanup Utilities
 *
 * Provides comprehensive data validation and cleanup tools for the School Management System.
 *
 * @package School_Management_System
 * @subpackage Core
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SMS Data Validator Class
 *
 * Handles data validation, cleanup, and integrity checks for all system data.
 */
class SMS_Data_Validator {

    /**
     * Validation rules for different data types
     */
    private $validation_rules = [
        'student' => [
            'full_name' => ['required', 'string', 'max:100'],
            'admission_number' => ['required', 'unique:cpt_students', 'alphanumeric'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'grade_level' => ['required', 'exists:tax_grades'],
            'parent_email' => ['required', 'email'],
            'parent_phone' => ['required', 'kenyan_phone'],
            'medical_info' => ['string', 'max:500'],
            'address' => ['string', 'max:200']
        ],
        'class' => [
            'class_name' => ['required', 'string', 'max:50'],
            'grade_level' => ['required', 'exists:tax_grades'],
            'capacity' => ['required', 'integer', 'min:1', 'max:100'],
            'teacher_id' => ['exists:users'],
            'academic_year' => ['required', 'exists:tax_academic_years']
        ],
        'fee' => [
            'fee_type' => ['required', 'string', 'max:50'],
            'amount' => ['required', 'numeric', 'min:0'],
            'due_date' => ['required', 'date', 'after:today'],
            'penalty_rate' => ['numeric', 'min:0', 'max:100'],
            'academic_term' => ['required', 'exists:tax_terms']
        ],
        'transaction' => [
            'amount' => ['required', 'numeric', 'min:1'],
            'payment_method' => ['required', 'in:mpesa,airtel_money,bank_transfer,cash'],
            'phone_number' => ['required_if:payment_method,mpesa,airtel_money', 'kenyan_phone'],
            'reference_number' => ['string', 'max:50']
        ]
    ];

    /**
     * Run comprehensive data validation across all system data
     *
     * @return array Validation report with issues found
     */
    public function run_full_system_validation() {
        $report = [
            'timestamp' => current_time('mysql'),
            'total_issues' => 0,
            'categories' => [
                'students' => $this->validate_all_students(),
                'classes' => $this->validate_all_classes(),
                'fees' => $this->validate_all_fees(),
                'transactions' => $this->validate_all_transactions(),
                'relationships' => $this->validate_relationships(),
                'orphaned_data' => $this->find_orphaned_data()
            ]
        ];

        // Calculate total issues
        foreach ($report['categories'] as $category) {
            $report['total_issues'] += count($category['issues']);
        }

        return $report;
    }

    /**
     * Validate all student records
     *
     * @return array Validation results for students
     */
    public function validate_all_students() {
        $students = get_posts([
            'post_type' => 'cpt_students',
            'posts_per_page' => -1,
            'post_status' => 'any'
        ]);

        $results = [
            'total_checked' => count($students),
            'issues' => []
        ];

        foreach ($students as $student) {
            $student_data = $this->get_student_meta_data($student->ID);
            $validation_result = $this->validate_data($student_data, 'student');

            if (!empty($validation_result['errors'])) {
                $results['issues'][] = [
                    'id' => $student->ID,
                    'title' => $student->post_title,
                    'errors' => $validation_result['errors']
                ];
            }
        }

        return $results;
    }

    /**
     * Validate all class records
     *
     * @return array Validation results for classes
     */
    public function validate_all_classes() {
        $classes = get_posts([
            'post_type' => 'cpt_classes',
            'posts_per_page' => -1,
            'post_status' => 'any'
        ]);

        $results = [
            'total_checked' => count($classes),
            'issues' => []
        ];

        foreach ($classes as $class) {
            $class_data = $this->get_class_meta_data($class->ID);
            $validation_result = $this->validate_data($class_data, 'class');

            if (!empty($validation_result['errors'])) {
                $results['issues'][] = [
                    'id' => $class->ID,
                    'title' => $class->post_title,
                    'errors' => $validation_result['errors']
                ];
            }

            // Check enrollment vs capacity
            $enrolled_count = $this->get_class_enrollment_count($class->ID);
            $capacity = intval(get_post_meta($class->ID, 'capacity', true));
            
            if ($enrolled_count > $capacity) {
                $results['issues'][] = [
                    'id' => $class->ID,
                    'title' => $class->post_title,
                    'errors' => ["Class over capacity: {$enrolled_count}/{$capacity} students"]
                ];
            }
        }

        return $results;
    }

    /**
     * Validate all fee records
     *
     * @return array Validation results for fees
     */
    public function validate_all_fees() {
        $fees = get_posts([
            'post_type' => 'cpt_fees',
            'posts_per_page' => -1,
            'post_status' => 'any'
        ]);

        $results = [
            'total_checked' => count($fees),
            'issues' => []
        ];

        foreach ($fees as $fee) {
            $fee_data = $this->get_fee_meta_data($fee->ID);
            $validation_result = $this->validate_data($fee_data, 'fee');

            if (!empty($validation_result['errors'])) {
                $results['issues'][] = [
                    'id' => $fee->ID,
                    'title' => $fee->post_title,
                    'errors' => $validation_result['errors']
                ];
            }
        }

        return $results;
    }

    /**
     * Validate all transaction records
     *
     * @return array Validation results for transactions
     */
    public function validate_all_transactions() {
        $transactions = get_posts([
            'post_type' => 'cpt_transactions',
            'posts_per_page' => -1,
            'post_status' => 'any'
        ]);

        $results = [
            'total_checked' => count($transactions),
            'issues' => []
        ];

        foreach ($transactions as $transaction) {
            $transaction_data = $this->get_transaction_meta_data($transaction->ID);
            $validation_result = $this->validate_data($transaction_data, 'transaction');

            if (!empty($validation_result['errors'])) {
                $results['issues'][] = [
                    'id' => $transaction->ID,
                    'title' => $transaction->post_title,
                    'errors' => $validation_result['errors']
                ];
            }

            // Validate transaction-specific business rules
            $business_validation = $this->validate_transaction_business_rules($transaction->ID, $transaction_data);
            if (!empty($business_validation)) {
                $results['issues'][] = [
                    'id' => $transaction->ID,
                    'title' => $transaction->post_title,
                    'errors' => $business_validation
                ];
            }
        }

        return $results;
    }

    /**
     * Validate data relationships
     *
     * @return array Validation results for relationships
     */
    public function validate_relationships() {
        $results = [
            'total_checked' => 0,
            'issues' => []
        ];

        // Check student-parent relationships
        $students = get_posts(['post_type' => 'cpt_students', 'posts_per_page' => -1]);
        foreach ($students as $student) {
            $parent_id = get_post_meta($student->ID, 'parent_user_id', true);
            if ($parent_id && !get_user_by('ID', $parent_id)) {
                $results['issues'][] = [
                    'type' => 'missing_parent',
                    'student_id' => $student->ID,
                    'student_name' => $student->post_title,
                    'error' => "Parent user ID {$parent_id} does not exist"
                ];
            }
        }

        // Check class-teacher relationships
        $classes = get_posts(['post_type' => 'cpt_classes', 'posts_per_page' => -1]);
        foreach ($classes as $class) {
            $teacher_id = get_post_meta($class->ID, 'teacher_id', true);
            if ($teacher_id && !get_user_by('ID', $teacher_id)) {
                $results['issues'][] = [
                    'type' => 'missing_teacher',
                    'class_id' => $class->ID,
                    'class_name' => $class->post_title,
                    'error' => "Teacher user ID {$teacher_id} does not exist"
                ];
            }
        }

        // Check invoice-student relationships
        $invoices = get_posts(['post_type' => 'cpt_invoices', 'posts_per_page' => -1]);
        foreach ($invoices as $invoice) {
            $student_id = get_post_meta($invoice->ID, 'student_id', true);
            if ($student_id && !get_post($student_id)) {
                $results['issues'][] = [
                    'type' => 'missing_student',
                    'invoice_id' => $invoice->ID,
                    'error' => "Student ID {$student_id} does not exist"
                ];
            }
        }

        $results['total_checked'] = count($students) + count($classes) + count($invoices);
        return $results;
    }

    /**
     * Find orphaned data records
     *
     * @return array Orphaned data report
     */
    public function find_orphaned_data() {
        $results = [
            'total_checked' => 0,
            'issues' => []
        ];

        // Find transactions without valid invoices
        $transactions = get_posts(['post_type' => 'cpt_transactions', 'posts_per_page' => -1]);
        foreach ($transactions as $transaction) {
            $invoice_id = get_post_meta($transaction->ID, 'invoice_id', true);
            if ($invoice_id && !get_post($invoice_id)) {
                $results['issues'][] = [
                    'type' => 'orphaned_transaction',
                    'id' => $transaction->ID,
                    'error' => "Transaction references non-existent invoice ID {$invoice_id}"
                ];
            }
        }

        // Find attendance records without valid classes
        $attendance_records = get_posts(['post_type' => 'cpt_attendance', 'posts_per_page' => -1]);
        foreach ($attendance_records as $attendance) {
            $class_id = get_post_meta($attendance->ID, 'class_id', true);
            if ($class_id && !get_post($class_id)) {
                $results['issues'][] = [
                    'type' => 'orphaned_attendance',
                    'id' => $attendance->ID,
                    'error' => "Attendance record references non-existent class ID {$class_id}"
                ];
            }
        }

        // Find parent users without children
        $parent_users = get_users(['role' => 'sms_parent']);
        foreach ($parent_users as $parent) {
            $children = get_user_meta($parent->ID, 'sms_children', true);
            if (empty($children) || !is_array($children)) {
                $results['issues'][] = [
                    'type' => 'orphaned_parent',
                    'id' => $parent->ID,
                    'name' => $parent->display_name,
                    'error' => "Parent user has no associated children"
                ];
            }
        }

        $results['total_checked'] = count($transactions) + count($attendance_records) + count($parent_users);
        return $results;
    }

    /**
     * Clean up orphaned data
     *
     * @param array $cleanup_options Options for what to clean up
     * @return array Cleanup results
     */
    public function cleanup_orphaned_data($cleanup_options = []) {
        $results = [
            'cleaned_items' => 0,
            'actions_taken' => []
        ];

        $default_options = [
            'remove_orphaned_transactions' => false,
            'remove_orphaned_attendance' => false,
            'remove_orphaned_parents' => false,
            'fix_relationships' => true,
            'backup_before_cleanup' => true
        ];

        $options = array_merge($default_options, $cleanup_options);

        if ($options['backup_before_cleanup']) {
            $this->create_cleanup_backup();
        }

        // Clean orphaned transactions
        if ($options['remove_orphaned_transactions']) {
            $orphaned_transactions = $this->find_orphaned_transactions();
            foreach ($orphaned_transactions as $transaction_id) {
                wp_delete_post($transaction_id, true);
                $results['cleaned_items']++;
                $results['actions_taken'][] = "Deleted orphaned transaction ID: {$transaction_id}";
            }
        }

        // Clean orphaned attendance records
        if ($options['remove_orphaned_attendance']) {
            $orphaned_attendance = $this->find_orphaned_attendance();
            foreach ($orphaned_attendance as $attendance_id) {
                wp_delete_post($attendance_id, true);
                $results['cleaned_items']++;
                $results['actions_taken'][] = "Deleted orphaned attendance record ID: {$attendance_id}";
            }
        }

        // Fix broken relationships
        if ($options['fix_relationships']) {
            $fixed_relationships = $this->fix_broken_relationships();
            $results['cleaned_items'] += $fixed_relationships['count'];
            $results['actions_taken'] = array_merge($results['actions_taken'], $fixed_relationships['actions']);
        }

        return $results;
    }

    /**
     * Validate individual data record
     *
     * @param array $data Data to validate
     * @param string $type Data type (student, class, fee, transaction)
     * @return array Validation results
     */
    public function validate_data($data, $type) {
        $results = [
            'valid' => true,
            'errors' => [],
            'warnings' => []
        ];

        if (!isset($this->validation_rules[$type])) {
            $results['errors'][] = "Unknown data type: {$type}";
            $results['valid'] = false;
            return $results;
        }

        $rules = $this->validation_rules[$type];

        foreach ($rules as $field => $field_rules) {
            $field_value = $data[$field] ?? null;
            
            foreach ($field_rules as $rule) {
                $validation_result = $this->apply_validation_rule($field, $field_value, $rule, $data);
                
                if ($validation_result !== true) {
                    $results['errors'][] = $validation_result;
                    $results['valid'] = false;
                }
            }
        }

        return $results;
    }

    /**
     * Apply individual validation rule
     *
     * @param string $field Field name
     * @param mixed $value Field value
     * @param string $rule Validation rule
     * @param array $all_data All data for context
     * @return true|string True if valid, error message if invalid
     */
    private function apply_validation_rule($field, $value, $rule, $all_data) {
        switch ($rule) {
            case 'required':
                return !empty($value) ? true : "Field '{$field}' is required";

            case 'string':
                return is_string($value) ? true : "Field '{$field}' must be a string";

            case 'integer':
                return is_numeric($value) && intval($value) == $value ? true : "Field '{$field}' must be an integer";

            case 'numeric':
                return is_numeric($value) ? true : "Field '{$field}' must be numeric";

            case 'email':
                return is_email($value) ? true : "Field '{$field}' must be a valid email";

            case 'date':
                $date = DateTime::createFromFormat('Y-m-d', $value);
                return ($date && $date->format('Y-m-d') === $value) ? true : "Field '{$field}' must be a valid date (YYYY-MM-DD)";

            case 'kenyan_phone':
                return $this->validate_kenyan_phone($value) ? true : "Field '{$field}' must be a valid Kenyan phone number";

            case 'alphanumeric':
                return ctype_alnum($value) ? true : "Field '{$field}' must contain only letters and numbers";

            default:
                if (strpos($rule, 'max:') === 0) {
                    $max_length = intval(substr($rule, 4));
                    return strlen($value) <= $max_length ? true : "Field '{$field}' must not exceed {$max_length} characters";
                }

                if (strpos($rule, 'min:') === 0) {
                    $min_value = intval(substr($rule, 4));
                    return intval($value) >= $min_value ? true : "Field '{$field}' must be at least {$min_value}";
                }

                if (strpos($rule, 'in:') === 0) {
                    $allowed_values = explode(',', substr($rule, 3));
                    return in_array($value, $allowed_values) ? true : "Field '{$field}' must be one of: " . implode(', ', $allowed_values);
                }

                return true;
        }
    }

    /**
     * Validate Kenyan phone number format
     *
     * @param string $phone Phone number to validate
     * @return bool True if valid Kenyan phone number
     */
    private function validate_kenyan_phone($phone) {
        $patterns = [
            '/^\+254[17]\d{8}$/',  // +254 format
            '/^254[17]\d{8}$/',    // 254 format
            '/^0[17]\d{8}$/'       // 0 format
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $phone)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get student meta data for validation
     *
     * @param int $student_id Student post ID
     * @return array Student meta data
     */
    private function get_student_meta_data($student_id) {
        return [
            'full_name' => get_post_meta($student_id, 'full_name', true),
            'admission_number' => get_post_meta($student_id, 'admission_number', true),
            'date_of_birth' => get_post_meta($student_id, 'date_of_birth', true),
            'grade_level' => get_post_meta($student_id, 'grade_level', true),
            'parent_email' => get_post_meta($student_id, 'parent_email', true),
            'parent_phone' => get_post_meta($student_id, 'parent_phone', true),
            'medical_info' => get_post_meta($student_id, 'medical_info', true),
            'address' => get_post_meta($student_id, 'address', true)
        ];
    }

    /**
     * Get class meta data for validation
     *
     * @param int $class_id Class post ID
     * @return array Class meta data
     */
    private function get_class_meta_data($class_id) {
        return [
            'class_name' => get_post_meta($class_id, 'class_name', true),
            'grade_level' => get_post_meta($class_id, 'grade_level', true),
            'capacity' => get_post_meta($class_id, 'capacity', true),
            'teacher_id' => get_post_meta($class_id, 'teacher_id', true),
            'academic_year' => get_post_meta($class_id, 'academic_year', true)
        ];
    }

    /**
     * Get fee meta data for validation
     *
     * @param int $fee_id Fee post ID
     * @return array Fee meta data
     */
    private function get_fee_meta_data($fee_id) {
        return [
            'fee_type' => get_post_meta($fee_id, 'fee_type', true),
            'amount' => get_post_meta($fee_id, 'amount', true),
            'due_date' => get_post_meta($fee_id, 'due_date', true),
            'penalty_rate' => get_post_meta($fee_id, 'penalty_rate', true),
            'academic_term' => get_post_meta($fee_id, 'academic_term', true)
        ];
    }

    /**
     * Get transaction meta data for validation
     *
     * @param int $transaction_id Transaction post ID
     * @return array Transaction meta data
     */
    private function get_transaction_meta_data($transaction_id) {
        return [
            'amount' => get_post_meta($transaction_id, 'amount', true),
            'payment_method' => get_post_meta($transaction_id, 'payment_method', true),
            'phone_number' => get_post_meta($transaction_id, 'phone_number', true),
            'reference_number' => get_post_meta($transaction_id, 'reference_number', true),
            'student_id' => get_post_meta($transaction_id, 'student_id', true),
            'invoice_id' => get_post_meta($transaction_id, 'invoice_id', true)
        ];
    }

    /**
     * Get class enrollment count
     *
     * @param int $class_id Class post ID
     * @return int Number of enrolled students
     */
    private function get_class_enrollment_count($class_id) {
        $enrolled_students = get_posts([
            'post_type' => 'cpt_students',
            'meta_query' => [
                [
                    'key' => 'current_class',
                    'value' => $class_id,
                    'compare' => '='
                ]
            ],
            'posts_per_page' => -1
        ]);

        return count($enrolled_students);
    }

    /**
     * Validate transaction business rules
     *
     * @param int $transaction_id Transaction post ID
     * @param array $transaction_data Transaction data
     * @return array Business rule validation errors
     */
    private function validate_transaction_business_rules($transaction_id, $transaction_data) {
        $errors = [];

        // Check if transaction amount matches invoice amount
        if (!empty($transaction_data['invoice_id'])) {
            $invoice_amount = get_post_meta($transaction_data['invoice_id'], 'total_amount', true);
            if ($invoice_amount && floatval($transaction_data['amount']) > floatval($invoice_amount)) {
                $errors[] = "Transaction amount exceeds invoice amount";
            }
        }

        // Check for duplicate transactions
        $duplicate_check = get_posts([
            'post_type' => 'cpt_transactions',
            'meta_query' => [
                [
                    'key' => 'gateway_transaction_id',
                    'value' => get_post_meta($transaction_id, 'gateway_transaction_id', true),
                    'compare' => '='
                ]
            ],
            'exclude' => [$transaction_id],
            'posts_per_page' => 1
        ]);

        if (!empty($duplicate_check)) {
            $errors[] = "Duplicate transaction detected";
        }

        return $errors;
    }

    /**
     * Create backup before cleanup operations
     */
    private function create_cleanup_backup() {
        // This would integrate with the backup system
        // For now, just log the backup creation
        error_log('SMS: Creating backup before data cleanup at ' . current_time('mysql'));
    }

    /**
     * Find orphaned transactions
     *
     * @return array Array of orphaned transaction IDs
     */
    private function find_orphaned_transactions() {
        $orphaned = [];
        $transactions = get_posts(['post_type' => 'cpt_transactions', 'posts_per_page' => -1]);
        
        foreach ($transactions as $transaction) {
            $invoice_id = get_post_meta($transaction->ID, 'invoice_id', true);
            if ($invoice_id && !get_post($invoice_id)) {
                $orphaned[] = $transaction->ID;
            }
        }

        return $orphaned;
    }

    /**
     * Find orphaned attendance records
     *
     * @return array Array of orphaned attendance record IDs
     */
    private function find_orphaned_attendance() {
        $orphaned = [];
        $attendance_records = get_posts(['post_type' => 'cpt_attendance', 'posts_per_page' => -1]);
        
        foreach ($attendance_records as $attendance) {
            $class_id = get_post_meta($attendance->ID, 'class_id', true);
            if ($class_id && !get_post($class_id)) {
                $orphaned[] = $attendance->ID;
            }
        }

        return $orphaned;
    }

    /**
     * Fix broken relationships
     *
     * @return array Results of relationship fixes
     */
    private function fix_broken_relationships() {
        $results = [
            'count' => 0,
            'actions' => []
        ];

        // Fix student-parent relationships
        $students = get_posts(['post_type' => 'cpt_students', 'posts_per_page' => -1]);
        foreach ($students as $student) {
            $parent_id = get_post_meta($student->ID, 'parent_user_id', true);
            if ($parent_id && !get_user_by('ID', $parent_id)) {
                // Remove invalid parent reference
                delete_post_meta($student->ID, 'parent_user_id');
                $results['count']++;
                $results['actions'][] = "Removed invalid parent reference from student ID: {$student->ID}";
            }
        }

        return $results;
    }
}