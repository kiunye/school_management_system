<?php
/**
 * Fee Structure Management
 *
 * Handles fee structure creation, management, installment calculations,
 * penalty applications, and discount management.
 *
 * @package    School_Management_System
 * @subpackage School_Management_System/includes/financial
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Fee Manager Class
 */
class SMS_Fee_Manager extends SMS_Base {

    /**
     * Fee calculation cache
     */
    private $calculation_cache = [];

    /**
     * Initialize the class and set its properties.
     */
    public function __construct() {
        parent::__construct();
        
        // Hook into fee calculations
        add_action('sms_calculate_student_fees', [$this, 'calculate_student_fees'], 10, 2);
        add_action('sms_apply_fee_penalties', [$this, 'apply_overdue_penalties']);
        add_action('sms_process_fee_discounts', [$this, 'process_applicable_discounts'], 10, 2);
        
        // Fee structure validation
        add_action('save_post_sms_fees', [$this, 'validate_fee_structure'], 20, 3);
        
        // Automated fee processing
        add_action('sms_daily_fee_processing', [$this, 'process_daily_fee_tasks']);
    }

    /**
     * Create a new fee structure.
     *
     * @param array $fee_data Fee structure data
     * @return int|WP_Error Fee post ID or error
     */
    public function create_fee_structure($fee_data) {
        try {
            // Validate required fields
            $validation_result = $this->validate_fee_data($fee_data);
            if (is_wp_error($validation_result)) {
                return $validation_result;
            }

            // Check for duplicate fee codes
            if ($this->fee_code_exists($fee_data['fee_code'])) {
                return new WP_Error(
                    'duplicate_fee_code',
                    __('Fee code already exists. Please use a unique code.', 'school-management-system')
                );
            }

            // Create the fee post
            $fee_post = [
                'post_title'   => sanitize_text_field($fee_data['fee_name']),
                'post_content' => sanitize_textarea_field($fee_data['description'] ?? ''),
                'post_status'  => 'publish',
                'post_type'    => 'sms_fees',
                'meta_input'   => $this->prepare_fee_meta($fee_data)
            ];

            $fee_id = wp_insert_post($fee_post);

            if (is_wp_error($fee_id)) {
                return $fee_id;
            }

            // Set taxonomies if provided
            if (!empty($fee_data['academic_years'])) {
                wp_set_object_terms($fee_id, $fee_data['academic_years'], 'sms_academic_years');
            }

            if (!empty($fee_data['terms'])) {
                wp_set_object_terms($fee_id, $fee_data['terms'], 'sms_terms');
            }

            // Log the creation
            $this->log_activity(
                get_current_user_id(),
                'fee_created',
                'fee',
                $fee_id,
                ['fee_name' => $fee_data['fee_name'], 'amount' => $fee_data['fee_amount']]
            );

            // Trigger auto-invoice generation if enabled
            if (!empty($fee_data['auto_generate_invoices'])) {
                do_action('sms_fee_created_auto_invoice', $fee_id);
            }

            return $fee_id;

        } catch (Exception $e) {
            return new WP_Error('fee_creation_failed', $e->getMessage());
        }
    }

    /**
     * Update an existing fee structure.
     *
     * @param int   $fee_id   Fee ID
     * @param array $fee_data Updated fee data
     * @return bool|WP_Error Success or error
     */
    public function update_fee_structure($fee_id, $fee_data) {
        try {
            // Verify fee exists
            if (!$this->fee_exists($fee_id)) {
                return new WP_Error('fee_not_found', __('Fee not found.', 'school-management-system'));
            }

            // Validate data
            $validation_result = $this->validate_fee_data($fee_data);
            if (is_wp_error($validation_result)) {
                return $validation_result;
            }

            // Check for duplicate fee codes (excluding current fee)
            if ($this->fee_code_exists($fee_data['fee_code'], $fee_id)) {
                return new WP_Error(
                    'duplicate_fee_code',
                    __('Fee code already exists. Please use a unique code.', 'school-management-system')
                );
            }

            // Update the post
            $update_data = [
                'ID'           => $fee_id,
                'post_title'   => sanitize_text_field($fee_data['fee_name']),
                'post_content' => sanitize_textarea_field($fee_data['description'] ?? ''),
            ];

            $result = wp_update_post($update_data);

            if (is_wp_error($result)) {
                return $result;
            }

            // Update meta fields
            $meta_data = $this->prepare_fee_meta($fee_data);
            foreach ($meta_data as $key => $value) {
                update_post_meta($fee_id, $key, $value);
            }

            // Clear calculation cache
            $this->clear_calculation_cache($fee_id);

            // Log the update
            $this->log_activity(
                get_current_user_id(),
                'fee_updated',
                'fee',
                $fee_id,
                ['fee_name' => $fee_data['fee_name']]
            );

            return true;

        } catch (Exception $e) {
            return new WP_Error('fee_update_failed', $e->getMessage());
        }
    }

    /**
     * Calculate total fee amount for a student including installments, penalties, and discounts.
     *
     * @param int $student_id Student ID
     * @param int $fee_id     Fee ID
     * @param array $options  Calculation options
     * @return array Detailed fee calculation
     */
    public function calculate_student_fee($student_id, $fee_id, $options = []) {
        $cache_key = "fee_calc_{$student_id}_{$fee_id}_" . md5(serialize($options));
        
        if (isset($this->calculation_cache[$cache_key])) {
            return $this->calculation_cache[$cache_key];
        }

        try {
            // Get fee structure
            $fee_structure = $this->get_fee_structure($fee_id);
            if (!$fee_structure) {
                return new WP_Error('fee_not_found', __('Fee structure not found.', 'school-management-system'));
            }

            // Check if fee applies to this student
            if (!$this->fee_applies_to_student($fee_id, $student_id)) {
                return [
                    'applicable' => false,
                    'reason' => 'Fee does not apply to this student'
                ];
            }

            $base_amount = floatval($fee_structure['fee_amount']);
            $calculation = [
                'fee_id' => $fee_id,
                'student_id' => $student_id,
                'base_amount' => $base_amount,
                'installments' => [],
                'discounts' => [],
                'penalties' => [],
                'total_amount' => $base_amount,
                'amount_due' => $base_amount,
                'amount_paid' => 0,
                'applicable' => true,
                'calculation_date' => current_time('mysql')
            ];

            // Calculate installments
            if (!empty($fee_structure['installment_options'])) {
                $calculation['installments'] = $this->calculate_installments($fee_structure, $base_amount, $options);
            }

            // Apply discounts
            $applicable_discounts = $this->get_applicable_discounts($fee_id, $student_id, $options);
            if (!empty($applicable_discounts)) {
                $calculation['discounts'] = $applicable_discounts;
                $calculation['total_amount'] -= array_sum(array_column($applicable_discounts, 'amount'));
            }

            // Calculate penalties for overdue amounts
            if (!empty($options['include_penalties'])) {
                $penalties = $this->calculate_penalties($fee_id, $student_id, $options);
                if (!empty($penalties)) {
                    $calculation['penalties'] = $penalties;
                    $calculation['total_amount'] += array_sum(array_column($penalties, 'amount'));
                }
            }

            // Get payment history
            $payment_history = $this->get_student_fee_payments($student_id, $fee_id);
            $calculation['amount_paid'] = array_sum(array_column($payment_history, 'amount'));
            $calculation['amount_due'] = max(0, $calculation['total_amount'] - $calculation['amount_paid']);

            // Cache the result
            $this->calculation_cache[$cache_key] = $calculation;

            return $calculation;

        } catch (Exception $e) {
            return new WP_Error('calculation_failed', $e->getMessage());
        }
    }

    /**
     * Calculate installment breakdown for a fee.
     *
     * @param array $fee_structure Fee structure data
     * @param float $base_amount   Base fee amount
     * @param array $options       Calculation options
     * @return array Installment breakdown
     */
    private function calculate_installments($fee_structure, $base_amount, $options = []) {
        $installments = [];
        $installment_options = $fee_structure['installment_options'];

        if (empty($installment_options)) {
            return $installments;
        }

        $reference_date = $options['reference_date'] ?? current_time('Y-m-d');
        $total_percentage = 0;

        foreach ($installment_options as $index => $installment) {
            $percentage = floatval($installment['installment_percentage']);
            $due_days = intval($installment['installment_due_days']);
            
            $installment_amount = ($base_amount * $percentage) / 100;
            $due_date = date('Y-m-d', strtotime($reference_date . " + {$due_days} days"));

            $installments[] = [
                'sequence' => $index + 1,
                'name' => sanitize_text_field($installment['installment_name']),
                'percentage' => $percentage,
                'amount' => $installment_amount,
                'due_date' => $due_date,
                'due_days_after' => $due_days,
                'status' => 'pending'
            ];

            $total_percentage += $percentage;
        }

        // Validate total percentage
        if ($total_percentage != 100) {
            // Adjust the last installment to make total 100%
            if (!empty($installments)) {
                $adjustment = 100 - $total_percentage;
                $last_index = count($installments) - 1;
                $installments[$last_index]['percentage'] += $adjustment;
                $installments[$last_index]['amount'] = ($base_amount * $installments[$last_index]['percentage']) / 100;
            }
        }

        return $installments;
    }

    /**
     * Get applicable discounts for a student and fee.
     *
     * @param int   $fee_id    Fee ID
     * @param int   $student_id Student ID
     * @param array $options   Calculation options
     * @return array Applicable discounts
     */
    private function get_applicable_discounts($fee_id, $student_id, $options = []) {
        $discounts = [];
        $fee_discounts = get_field('discounts', $fee_id);

        if (empty($fee_discounts)) {
            return $discounts;
        }

        foreach ($fee_discounts as $discount) {
            if ($this->discount_applies_to_student($discount, $student_id, $options)) {
                $discount_amount = $this->calculate_discount_amount($discount, $options['base_amount'] ?? 0);
                
                $discounts[] = [
                    'name' => $discount['discount_name'],
                    'type' => $discount['discount_type'],
                    'value' => $discount['discount_value'],
                    'condition' => $discount['discount_condition'],
                    'amount' => $discount_amount,
                    'applied_date' => current_time('mysql')
                ];
            }
        }

        return $discounts;
    }

    /**
     * Calculate penalties for overdue fees.
     *
     * @param int   $fee_id    Fee ID
     * @param int   $student_id Student ID
     * @param array $options   Calculation options
     * @return array Penalty calculations
     */
    private function calculate_penalties($fee_id, $student_id, $options = []) {
        $penalties = [];
        $penalty_settings = get_field('late_payment_penalty', $fee_id);

        if (empty($penalty_settings['penalty_enabled'])) {
            return $penalties;
        }

        // Get overdue invoices for this fee and student
        $overdue_invoices = $this->get_overdue_invoices($student_id, $fee_id);

        foreach ($overdue_invoices as $invoice) {
            $days_overdue = $this->calculate_days_overdue($invoice['due_date']);
            $grace_days = intval($penalty_settings['penalty_grace_days'] ?? 0);

            if ($days_overdue > $grace_days) {
                $penalty_amount = $this->calculate_penalty_amount(
                    $penalty_settings,
                    $invoice['amount'],
                    $days_overdue - $grace_days
                );

                if ($penalty_amount > 0) {
                    $penalties[] = [
                        'invoice_id' => $invoice['id'],
                        'penalty_type' => $penalty_settings['penalty_type'],
                        'days_overdue' => $days_overdue,
                        'grace_days' => $grace_days,
                        'penalty_amount' => $penalty_amount,
                        'calculated_date' => current_time('mysql')
                    ];
                }
            }
        }

        return $penalties;
    }

    /**
     * Check if a fee applies to a specific student.
     *
     * @param int $fee_id    Fee ID
     * @param int $student_id Student ID
     * @return bool Whether fee applies
     */
    private function fee_applies_to_student($fee_id, $student_id) {
        // Get student's grade and class
        $student_grade = get_field('grade_level', $student_id);
        $student_class = get_field('current_class', $student_id);

        // Check grade applicability
        $applicable_grades = get_field('applicable_grades', $fee_id);
        if (!empty($applicable_grades) && !in_array($student_grade, $applicable_grades)) {
            return false;
        }

        // Check class applicability
        $applicable_classes = get_field('applicable_classes', $fee_id);
        if (!empty($applicable_classes) && !in_array($student_class, $applicable_classes)) {
            return false;
        }

        // Check if fee is active
        $fee_status = get_field('fee_status', $fee_id);
        if ($fee_status !== 'active') {
            return false;
        }

        return true;
    }

    /**
     * Check if a discount applies to a student.
     *
     * @param array $discount   Discount configuration
     * @param int   $student_id Student ID
     * @param array $options    Additional options
     * @return bool Whether discount applies
     */
    private function discount_applies_to_student($discount, $student_id, $options = []) {
        $condition = $discount['discount_condition'];

        switch ($condition) {
            case 'early_payment':
                return $this->check_early_payment_eligibility($student_id, $options);
                
            case 'sibling':
                return $this->check_sibling_discount_eligibility($student_id);
                
            case 'scholarship':
                return $this->check_scholarship_eligibility($student_id);
                
            case 'staff_child':
                return $this->check_staff_child_eligibility($student_id);
                
            case 'other':
                return apply_filters('sms_custom_discount_eligibility', false, $discount, $student_id, $options);
                
            default:
                return false;
        }
    }

    /**
     * Calculate discount amount based on type and value.
     *
     * @param array $discount    Discount configuration
     * @param float $base_amount Base amount to calculate from
     * @return float Discount amount
     */
    private function calculate_discount_amount($discount, $base_amount) {
        $discount_value = floatval($discount['discount_value']);

        if ($discount['discount_type'] === 'percentage') {
            return ($base_amount * $discount_value) / 100;
        } else {
            return $discount_value; // Fixed amount
        }
    }

    /**
     * Validate fee structure data.
     *
     * @param array $fee_data Fee data to validate
     * @return bool|WP_Error True if valid, WP_Error if invalid
     */
    private function validate_fee_data($fee_data) {
        $errors = [];

        // Required fields
        $required_fields = ['fee_name', 'fee_code', 'fee_amount', 'fee_type'];
        foreach ($required_fields as $field) {
            if (empty($fee_data[$field])) {
                $errors[] = sprintf(__('%s is required.', 'school-management-system'), ucfirst(str_replace('_', ' ', $field)));
            }
        }

        // Validate fee amount
        if (isset($fee_data['fee_amount']) && (!is_numeric($fee_data['fee_amount']) || $fee_data['fee_amount'] < 0)) {
            $errors[] = __('Fee amount must be a positive number.', 'school-management-system');
        }

        // Validate fee code format
        if (isset($fee_data['fee_code']) && !preg_match('/^[A-Z0-9_]{2,10}$/', $fee_data['fee_code'])) {
            $errors[] = __('Fee code must be 2-10 characters, uppercase letters, numbers, and underscores only.', 'school-management-system');
        }

        // Validate installment percentages
        if (!empty($fee_data['installment_options'])) {
            $total_percentage = 0;
            foreach ($fee_data['installment_options'] as $installment) {
                $percentage = floatval($installment['installment_percentage'] ?? 0);
                if ($percentage <= 0 || $percentage > 100) {
                    $errors[] = __('Installment percentages must be between 1 and 100.', 'school-management-system');
                }
                $total_percentage += $percentage;
            }

            if ($total_percentage != 100) {
                $errors[] = __('Total installment percentages must equal 100%.', 'school-management-system');
            }
        }

        if (!empty($errors)) {
            return new WP_Error('validation_failed', implode(' ', $errors));
        }

        return true;
    }

    /**
     * Prepare meta data for fee post.
     *
     * @param array $fee_data Fee data
     * @return array Meta data array
     */
    private function prepare_fee_meta($fee_data) {
        $meta_data = [];

        // Basic fee information
        $basic_fields = [
            'fee_name', 'fee_code', 'fee_type', 'fee_amount', 'fee_status',
            'payment_frequency', 'due_date_type', 'due_date_fixed', 'due_date_days',
            'mandatory', 'auto_generate_invoices'
        ];

        foreach ($basic_fields as $field) {
            if (isset($fee_data[$field])) {
                $meta_data[$field] = $fee_data[$field];
            }
        }

        // Complex fields
        if (isset($fee_data['installment_options'])) {
            $meta_data['installment_options'] = $fee_data['installment_options'];
        }

        if (isset($fee_data['late_payment_penalty'])) {
            $meta_data['late_payment_penalty'] = $fee_data['late_payment_penalty'];
        }

        if (isset($fee_data['discounts'])) {
            $meta_data['discounts'] = $fee_data['discounts'];
        }

        if (isset($fee_data['applicable_grades'])) {
            $meta_data['applicable_grades'] = $fee_data['applicable_grades'];
        }

        if (isset($fee_data['applicable_classes'])) {
            $meta_data['applicable_classes'] = $fee_data['applicable_classes'];
        }

        return $meta_data;
    }

    /**
     * Check if fee code exists.
     *
     * @param string $fee_code Fee code to check
     * @param int    $exclude_id Fee ID to exclude from check
     * @return bool Whether fee code exists
     */
    private function fee_code_exists($fee_code, $exclude_id = 0) {
        $args = [
            'post_type' => 'sms_fees',
            'meta_query' => [
                [
                    'key' => 'fee_code',
                    'value' => $fee_code,
                    'compare' => '='
                ]
            ],
            'post_status' => 'any',
            'fields' => 'ids'
        ];

        if ($exclude_id > 0) {
            $args['post__not_in'] = [$exclude_id];
        }

        $existing_fees = get_posts($args);
        return !empty($existing_fees);
    }

    /**
     * Check if fee exists.
     *
     * @param int $fee_id Fee ID
     * @return bool Whether fee exists
     */
    private function fee_exists($fee_id) {
        $post = get_post($fee_id);
        return $post && $post->post_type === 'sms_fees';
    }

    /**
     * Get fee structure data.
     *
     * @param int $fee_id Fee ID
     * @return array|false Fee structure data or false
     */
    public function get_fee_structure($fee_id) {
        if (!$this->fee_exists($fee_id)) {
            return false;
        }

        $fee_post = get_post($fee_id);
        $fee_data = [
            'id' => $fee_id,
            'name' => $fee_post->post_title,
            'description' => $fee_post->post_content,
            'status' => $fee_post->post_status
        ];

        // Get all meta fields
        $meta_fields = [
            'fee_name', 'fee_code', 'fee_type', 'fee_amount', 'fee_status',
            'payment_frequency', 'due_date_type', 'due_date_fixed', 'due_date_days',
            'installment_options', 'late_payment_penalty', 'discounts',
            'applicable_grades', 'applicable_classes', 'mandatory', 'auto_generate_invoices'
        ];

        foreach ($meta_fields as $field) {
            $fee_data[$field] = get_field($field, $fee_id);
        }

        return $fee_data;
    }

    /**
     * Clear calculation cache for a fee.
     *
     * @param int $fee_id Fee ID
     */
    private function clear_calculation_cache($fee_id = null) {
        if ($fee_id) {
            // Clear cache for specific fee
            foreach ($this->calculation_cache as $key => $value) {
                if (strpos($key, "_{$fee_id}_") !== false) {
                    unset($this->calculation_cache[$key]);
                }
            }
        } else {
            // Clear all cache
            $this->calculation_cache = [];
        }
    }

    /**
     * Get student fee payments.
     *
     * @param int $student_id Student ID
     * @param int $fee_id     Fee ID
     * @return array Payment history
     */
    private function get_student_fee_payments($student_id, $fee_id) {
        // This would integrate with the transaction system
        // For now, return empty array as placeholder
        return [];
    }

    /**
     * Get overdue invoices for a student and fee.
     *
     * @param int $student_id Student ID
     * @param int $fee_id     Fee ID
     * @return array Overdue invoices
     */
    private function get_overdue_invoices($student_id, $fee_id) {
        // This would integrate with the invoice system
        // For now, return empty array as placeholder
        return [];
    }

    /**
     * Calculate days overdue from due date.
     *
     * @param string $due_date Due date
     * @return int Days overdue
     */
    private function calculate_days_overdue($due_date) {
        $due_timestamp = strtotime($due_date);
        $current_timestamp = current_time('timestamp');
        
        if ($current_timestamp > $due_timestamp) {
            return ceil(($current_timestamp - $due_timestamp) / DAY_IN_SECONDS);
        }
        
        return 0;
    }

    /**
     * Calculate penalty amount based on settings.
     *
     * @param array $penalty_settings Penalty configuration
     * @param float $base_amount      Base amount
     * @param int   $days_overdue     Days overdue
     * @return float Penalty amount
     */
    private function calculate_penalty_amount($penalty_settings, $base_amount, $days_overdue) {
        $penalty_type = $penalty_settings['penalty_type'];
        $penalty_value = floatval($penalty_settings['penalty_amount']);

        switch ($penalty_type) {
            case 'fixed':
                return $penalty_value;
                
            case 'percentage':
                return ($base_amount * $penalty_value) / 100;
                
            case 'daily':
                return $penalty_value * $days_overdue;
                
            default:
                return 0;
        }
    }

    /**
     * Check early payment eligibility.
     *
     * @param int   $student_id Student ID
     * @param array $options    Options
     * @return bool Eligibility
     */
    private function check_early_payment_eligibility($student_id, $options) {
        // Implementation would check payment timing
        return false;
    }

    /**
     * Check sibling discount eligibility.
     *
     * @param int $student_id Student ID
     * @return bool Eligibility
     */
    private function check_sibling_discount_eligibility($student_id) {
        // Implementation would check for siblings in the school
        return false;
    }

    /**
     * Check scholarship eligibility.
     *
     * @param int $student_id Student ID
     * @return bool Eligibility
     */
    private function check_scholarship_eligibility($student_id) {
        // Implementation would check scholarship records
        return false;
    }

    /**
     * Check staff child eligibility.
     *
     * @param int $student_id Student ID
     * @return bool Eligibility
     */
    private function check_staff_child_eligibility($student_id) {
        // Implementation would check if parent is staff member
        return false;
    }

    /**
     * Process daily fee tasks (penalties, reminders, etc.).
     */
    public function process_daily_fee_tasks() {
        // Apply overdue penalties
        $this->apply_overdue_penalties();
        
        // Send fee reminders
        $this->send_fee_reminders();
        
        // Generate auto-invoices for new fees
        $this->generate_auto_invoices();
    }

    /**
     * Apply overdue penalties to applicable fees.
     */
    private function apply_overdue_penalties() {
        // Implementation for applying penalties
        do_action('sms_penalties_applied');
    }

    /**
     * Send fee reminders.
     */
    private function send_fee_reminders() {
        // Implementation for sending reminders
        do_action('sms_fee_reminders_sent');
    }

    /**
     * Generate auto-invoices for fees with auto-generation enabled.
     */
    private function generate_auto_invoices() {
        // Implementation for auto-invoice generation
        do_action('sms_auto_invoices_generated');
    }

    /**
     * Validate fee structure on save.
     *
     * @param int     $post_id Post ID
     * @param WP_Post $post    Post object
     * @param bool    $update  Whether this is an update
     */
    public function validate_fee_structure($post_id, $post, $update) {
        // Skip validation for auto-saves and revisions
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }

        // Validate installment percentages
        $installments = get_field('installment_options', $post_id);
        if (!empty($installments)) {
            $total_percentage = 0;
            foreach ($installments as $installment) {
                $total_percentage += floatval($installment['installment_percentage'] ?? 0);
            }

            if ($total_percentage != 100) {
                // Add admin notice for validation error
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-error"><p>' . 
                         __('Warning: Installment percentages do not total 100%. Please review the fee structure.', 'school-management-system') . 
                         '</p></div>';
                });
            }
        }
    }
}