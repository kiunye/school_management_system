<?php
/**
 * Data Migration Tools
 *
 * Handles import/export of student and academic data with validation and cleanup utilities.
 *
 * @package School_Management_System
 * @subpackage Core
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SMS Data Migrator Class
 *
 * Provides tools for importing existing student and academic data,
 * data validation, cleanup utilities, and backup/restore functionality.
 */
class SMS_Data_Migrator {

    /**
     * Supported import formats
     */
    const SUPPORTED_FORMATS = ['csv', 'xlsx', 'json'];

    /**
     * Maximum batch size for processing
     */
    const BATCH_SIZE = 100;

    /**
     * Import students from CSV/Excel file
     *
     * @param string $file_path Path to the import file
     * @param array $mapping Field mapping configuration
     * @return array Import results with success/error counts
     */
    public function import_students($file_path, $mapping = []) {
        $results = [
            'success' => 0,
            'errors' => 0,
            'warnings' => [],
            'error_details' => []
        ];

        if (!file_exists($file_path)) {
            return new WP_Error('file_not_found', 'Import file not found.');
        }

        $data = $this->parse_import_file($file_path);
        if (is_wp_error($data)) {
            return $data;
        }

        // Process in batches to avoid memory issues
        $batches = array_chunk($data, self::BATCH_SIZE);

        foreach ($batches as $batch_index => $batch) {
            foreach ($batch as $row_index => $row_data) {
                $student_data = $this->map_student_data($row_data, $mapping);
                
                // Validate student data
                $validation_result = $this->validate_student_data($student_data);
                if (is_wp_error($validation_result)) {
                    $results['errors']++;
                    $results['error_details'][] = [
                        'row' => ($batch_index * self::BATCH_SIZE) + $row_index + 1,
                        'error' => $validation_result->get_error_message(),
                        'data' => $student_data
                    ];
                    continue;
                }

                // Create student record
                $student_id = $this->create_student_record($student_data);
                if (is_wp_error($student_id)) {
                    $results['errors']++;
                    $results['error_details'][] = [
                        'row' => ($batch_index * self::BATCH_SIZE) + $row_index + 1,
                        'error' => $student_id->get_error_message(),
                        'data' => $student_data
                    ];
                } else {
                    $results['success']++;
                    
                    // Create parent account if needed
                    if (!empty($student_data['parent_email'])) {
                        $this->create_parent_account($student_id, $student_data);
                    }
                }
            }
        }

        // Log import results
        $this->log_import_activity('students', $results);

        return $results;
    }

    /**
     * Import academic data (classes, subjects, etc.)
     *
     * @param string $file_path Path to the import file
     * @param string $data_type Type of academic data (classes, subjects, grades)
     * @param array $mapping Field mapping configuration
     * @return array Import results
     */
    public function import_academic_data($file_path, $data_type, $mapping = []) {
        $results = [
            'success' => 0,
            'errors' => 0,
            'warnings' => [],
            'error_details' => []
        ];

        if (!in_array($data_type, ['classes', 'subjects', 'grades', 'academic_years'])) {
            return new WP_Error('invalid_data_type', 'Unsupported academic data type.');
        }

        $data = $this->parse_import_file($file_path);
        if (is_wp_error($data)) {
            return $data;
        }

        foreach ($data as $row_index => $row_data) {
            $academic_data = $this->map_academic_data($row_data, $data_type, $mapping);
            
            // Validate academic data
            $validation_result = $this->validate_academic_data($academic_data, $data_type);
            if (is_wp_error($validation_result)) {
                $results['errors']++;
                $results['error_details'][] = [
                    'row' => $row_index + 1,
                    'error' => $validation_result->get_error_message(),
                    'data' => $academic_data
                ];
                continue;
            }

            // Create academic record
            $record_id = $this->create_academic_record($academic_data, $data_type);
            if (is_wp_error($record_id)) {
                $results['errors']++;
                $results['error_details'][] = [
                    'row' => $row_index + 1,
                    'error' => $record_id->get_error_message(),
                    'data' => $academic_data
                ];
            } else {
                $results['success']++;
            }
        }

        $this->log_import_activity($data_type, $results);
        return $results;
    }

    /**
     * Parse import file based on format
     *
     * @param string $file_path Path to the import file
     * @return array|WP_Error Parsed data or error
     */
    private function parse_import_file($file_path) {
        $file_extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));

        if (!in_array($file_extension, self::SUPPORTED_FORMATS)) {
            return new WP_Error('unsupported_format', 'Unsupported file format. Supported: ' . implode(', ', self::SUPPORTED_FORMATS));
        }

        switch ($file_extension) {
            case 'csv':
                return $this->parse_csv_file($file_path);
            case 'xlsx':
                return $this->parse_excel_file($file_path);
            case 'json':
                return $this->parse_json_file($file_path);
            default:
                return new WP_Error('parse_error', 'Unable to parse file format.');
        }
    }

    /**
     * Parse CSV file
     *
     * @param string $file_path Path to CSV file
     * @return array|WP_Error Parsed data or error
     */
    private function parse_csv_file($file_path) {
        $data = [];
        $headers = [];

        if (($handle = fopen($file_path, 'r')) !== FALSE) {
            $row_index = 0;
            while (($row = fgetcsv($handle, 1000, ',')) !== FALSE) {
                if ($row_index === 0) {
                    $headers = array_map('trim', $row);
                } else {
                    $data[] = array_combine($headers, array_map('trim', $row));
                }
                $row_index++;
            }
            fclose($handle);
        } else {
            return new WP_Error('file_read_error', 'Unable to read CSV file.');
        }

        return $data;
    }

    /**
     * Parse JSON file
     *
     * @param string $file_path Path to JSON file
     * @return array|WP_Error Parsed data or error
     */
    private function parse_json_file($file_path) {
        $json_content = file_get_contents($file_path);
        if ($json_content === false) {
            return new WP_Error('file_read_error', 'Unable to read JSON file.');
        }

        $data = json_decode($json_content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_parse_error', 'Invalid JSON format: ' . json_last_error_msg());
        }

        return $data;
    }

    /**
     * Parse Excel file (requires PhpSpreadsheet or similar library)
     *
     * @param string $file_path Path to Excel file
     * @return array|WP_Error Parsed data or error
     */
    private function parse_excel_file($file_path) {
        // Note: This would require PhpSpreadsheet library
        // For now, return error suggesting CSV format
        return new WP_Error('excel_not_supported', 'Excel import requires additional library. Please use CSV format.');
    }

    /**
     * Map imported data to student fields
     *
     * @param array $row_data Raw row data from import
     * @param array $mapping Field mapping configuration
     * @return array Mapped student data
     */
    private function map_student_data($row_data, $mapping) {
        $default_mapping = [
            'full_name' => 'full_name',
            'admission_number' => 'admission_number',
            'date_of_birth' => 'date_of_birth',
            'grade_level' => 'grade_level',
            'parent_name' => 'parent_name',
            'parent_email' => 'parent_email',
            'parent_phone' => 'parent_phone',
            'address' => 'address',
            'medical_info' => 'medical_info'
        ];

        $field_mapping = array_merge($default_mapping, $mapping);
        $student_data = [];

        foreach ($field_mapping as $system_field => $import_field) {
            if (isset($row_data[$import_field])) {
                $student_data[$system_field] = $this->sanitize_field_value($row_data[$import_field], $system_field);
            }
        }

        return $student_data;
    }

    /**
     * Map imported data to academic fields
     *
     * @param array $row_data Raw row data from import
     * @param string $data_type Type of academic data
     * @param array $mapping Field mapping configuration
     * @return array Mapped academic data
     */
    private function map_academic_data($row_data, $data_type, $mapping) {
        $default_mappings = [
            'classes' => [
                'class_name' => 'class_name',
                'grade_level' => 'grade_level',
                'capacity' => 'capacity',
                'teacher_name' => 'teacher_name'
            ],
            'subjects' => [
                'subject_name' => 'subject_name',
                'subject_code' => 'subject_code',
                'description' => 'description'
            ],
            'grades' => [
                'grade_name' => 'grade_name',
                'grade_level' => 'grade_level',
                'description' => 'description'
            ]
        ];

        $field_mapping = array_merge($default_mappings[$data_type] ?? [], $mapping);
        $academic_data = [];

        foreach ($field_mapping as $system_field => $import_field) {
            if (isset($row_data[$import_field])) {
                $academic_data[$system_field] = $this->sanitize_field_value($row_data[$import_field], $system_field);
            }
        }

        return $academic_data;
    }

    /**
     * Sanitize field value based on field type
     *
     * @param mixed $value Field value
     * @param string $field_name Field name
     * @return mixed Sanitized value
     */
    private function sanitize_field_value($value, $field_name) {
        switch ($field_name) {
            case 'full_name':
            case 'parent_name':
            case 'class_name':
            case 'subject_name':
                return sanitize_text_field(trim($value));
            
            case 'parent_email':
                return sanitize_email(trim($value));
            
            case 'parent_phone':
                return preg_replace('/[^0-9+]/', '', trim($value));
            
            case 'date_of_birth':
                return date('Y-m-d', strtotime($value));
            
            case 'capacity':
                return intval($value);
            
            case 'admission_number':
                return strtoupper(sanitize_text_field(trim($value)));
            
            default:
                return sanitize_text_field(trim($value));
        }
    }

    /**
     * Validate student data before import
     *
     * @param array $student_data Student data to validate
     * @return true|WP_Error True if valid, WP_Error if invalid
     */
    private function validate_student_data($student_data) {
        // Required fields validation
        $required_fields = ['full_name', 'date_of_birth'];
        foreach ($required_fields as $field) {
            if (empty($student_data[$field])) {
                return new WP_Error('missing_required_field', "Required field '{$field}' is missing or empty.");
            }
        }

        // Validate email format
        if (!empty($student_data['parent_email']) && !is_email($student_data['parent_email'])) {
            return new WP_Error('invalid_email', 'Invalid parent email format.');
        }

        // Validate date of birth
        if (!empty($student_data['date_of_birth'])) {
            $dob = DateTime::createFromFormat('Y-m-d', $student_data['date_of_birth']);
            if (!$dob || $dob->format('Y-m-d') !== $student_data['date_of_birth']) {
                return new WP_Error('invalid_date', 'Invalid date of birth format. Use YYYY-MM-DD.');
            }
        }

        // Check for duplicate admission number
        if (!empty($student_data['admission_number'])) {
            $existing = get_posts([
                'post_type' => 'cpt_students',
                'meta_query' => [
                    [
                        'key' => 'admission_number',
                        'value' => $student_data['admission_number'],
                        'compare' => '='
                    ]
                ],
                'posts_per_page' => 1
            ]);

            if (!empty($existing)) {
                return new WP_Error('duplicate_admission_number', 'Admission number already exists: ' . $student_data['admission_number']);
            }
        }

        return true;
    }

    /**
     * Validate academic data before import
     *
     * @param array $academic_data Academic data to validate
     * @param string $data_type Type of academic data
     * @return true|WP_Error True if valid, WP_Error if invalid
     */
    private function validate_academic_data($academic_data, $data_type) {
        switch ($data_type) {
            case 'classes':
                if (empty($academic_data['class_name'])) {
                    return new WP_Error('missing_class_name', 'Class name is required.');
                }
                if (!empty($academic_data['capacity']) && !is_numeric($academic_data['capacity'])) {
                    return new WP_Error('invalid_capacity', 'Class capacity must be a number.');
                }
                break;

            case 'subjects':
                if (empty($academic_data['subject_name'])) {
                    return new WP_Error('missing_subject_name', 'Subject name is required.');
                }
                break;

            case 'grades':
                if (empty($academic_data['grade_name'])) {
                    return new WP_Error('missing_grade_name', 'Grade name is required.');
                }
                break;
        }

        return true;
    }

    /**
     * Create student record from validated data
     *
     * @param array $student_data Validated student data
     * @return int|WP_Error Student post ID or error
     */
    private function create_student_record($student_data) {
        // Generate admission number if not provided
        if (empty($student_data['admission_number'])) {
            $student_data['admission_number'] = $this->generate_admission_number();
        }

        $post_data = [
            'post_title' => $student_data['full_name'],
            'post_type' => 'cpt_students',
            'post_status' => 'publish',
            'meta_input' => $student_data
        ];

        $student_id = wp_insert_post($post_data);

        if (is_wp_error($student_id)) {
            return $student_id;
        }

        return $student_id;
    }

    /**
     * Create academic record from validated data
     *
     * @param array $academic_data Validated academic data
     * @param string $data_type Type of academic data
     * @return int|WP_Error Record ID or error
     */
    private function create_academic_record($academic_data, $data_type) {
        $post_type_mapping = [
            'classes' => 'cpt_classes',
            'subjects' => 'tax_subjects',
            'grades' => 'tax_grades',
            'academic_years' => 'tax_academic_years'
        ];

        $post_type = $post_type_mapping[$data_type];

        if (strpos($post_type, 'tax_') === 0) {
            // Create taxonomy term
            $taxonomy = $post_type;
            $term_data = wp_insert_term(
                $academic_data[array_keys($academic_data)[0]], // First field as term name
                $taxonomy,
                $academic_data
            );

            if (is_wp_error($term_data)) {
                return $term_data;
            }

            return $term_data['term_id'];
        } else {
            // Create post
            $post_data = [
                'post_title' => $academic_data[array_keys($academic_data)[0]], // First field as title
                'post_type' => $post_type,
                'post_status' => 'publish',
                'meta_input' => $academic_data
            ];

            return wp_insert_post($post_data);
        }
    }

    /**
     * Create parent account for imported student
     *
     * @param int $student_id Student post ID
     * @param array $student_data Student data containing parent info
     * @return int|WP_Error Parent user ID or error
     */
    private function create_parent_account($student_id, $student_data) {
        if (empty($student_data['parent_email'])) {
            return new WP_Error('missing_parent_email', 'Parent email is required to create parent account.');
        }

        // Check if parent account already exists
        $existing_user = get_user_by('email', $student_data['parent_email']);
        if ($existing_user) {
            // Link existing parent to student
            $this->link_parent_to_student($existing_user->ID, $student_id);
            return $existing_user->ID;
        }

        // Create new parent account
        $parent_data = [
            'user_login' => $student_data['parent_email'],
            'user_email' => $student_data['parent_email'],
            'display_name' => $student_data['parent_name'] ?? 'Parent',
            'role' => 'sms_parent',
            'user_pass' => wp_generate_password()
        ];

        $parent_id = wp_insert_user($parent_data);

        if (is_wp_error($parent_id)) {
            return $parent_id;
        }

        // Link parent to student
        $this->link_parent_to_student($parent_id, $student_id);

        // Send welcome email with login credentials
        $this->send_parent_welcome_email($parent_id, $parent_data['user_pass']);

        return $parent_id;
    }

    /**
     * Link parent user to student
     *
     * @param int $parent_id Parent user ID
     * @param int $student_id Student post ID
     */
    private function link_parent_to_student($parent_id, $student_id) {
        $existing_children = get_user_meta($parent_id, 'sms_children', true);
        if (!is_array($existing_children)) {
            $existing_children = [];
        }

        if (!in_array($student_id, $existing_children)) {
            $existing_children[] = $student_id;
            update_user_meta($parent_id, 'sms_children', $existing_children);
        }

        // Also store parent ID in student meta
        update_post_meta($student_id, 'parent_user_id', $parent_id);
    }

    /**
     * Generate unique admission number
     *
     * @return string Generated admission number
     */
    private function generate_admission_number() {
        $year = date('Y');
        $prefix = 'ADM' . $year;
        
        // Get the last admission number for this year
        $last_number = get_option('sms_last_admission_number_' . $year, 0);
        $new_number = $last_number + 1;
        
        // Update the counter
        update_option('sms_last_admission_number_' . $year, $new_number);
        
        return $prefix . str_pad($new_number, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Send welcome email to new parent
     *
     * @param int $parent_id Parent user ID
     * @param string $password Generated password
     */
    private function send_parent_welcome_email($parent_id, $password) {
        $parent = get_user_by('ID', $parent_id);
        $school_name = get_option('sms_school_name', 'School');
        
        $subject = "Welcome to {$school_name} Parent Portal";
        $message = "Dear {$parent->display_name},\n\n";
        $message .= "Your parent account has been created for {$school_name}.\n\n";
        $message .= "Login Details:\n";
        $message .= "Email: {$parent->user_email}\n";
        $message .= "Password: {$password}\n\n";
        $message .= "Please login and change your password.\n\n";
        $message .= "Login URL: " . wp_login_url() . "\n\n";
        $message .= "Best regards,\n{$school_name}";

        wp_mail($parent->user_email, $subject, $message);
    }

    /**
     * Log import activity
     *
     * @param string $import_type Type of import performed
     * @param array $results Import results
     */
    private function log_import_activity($import_type, $results) {
        $log_entry = [
            'import_type' => $import_type,
            'success_count' => $results['success'],
            'error_count' => $results['errors'],
            'timestamp' => current_time('mysql'),
            'user_id' => get_current_user_id()
        ];

        // Store in options table or custom log table
        $import_logs = get_option('sms_import_logs', []);
        $import_logs[] = $log_entry;
        
        // Keep only last 100 import logs
        if (count($import_logs) > 100) {
            $import_logs = array_slice($import_logs, -100);
        }
        
        update_option('sms_import_logs', $import_logs);
    }
}