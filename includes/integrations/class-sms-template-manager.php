<?php
/**
 * SMS Template Manager
 *
 * @package SchoolManagementSystem
 * @subpackage Integrations
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class SMS_Template_Manager
 * 
 * Manages SMS templates with dynamic content placeholders
 */
class SMS_Template_Manager {

    /**
     * Default templates
     */
    private $default_templates;

    /**
     * Available placeholders
     */
    private $available_placeholders;

    /**
     * Constructor
     */
    public function __construct() {
        $this->init_default_templates();
        $this->init_placeholders();
        
        // Add hooks
        add_action('wp_ajax_sms_save_template', array($this, 'save_template'));
        add_action('wp_ajax_sms_delete_template', array($this, 'delete_template'));
        add_action('wp_ajax_sms_preview_template', array($this, 'preview_template'));
        add_action('wp_ajax_sms_get_template_placeholders', array($this, 'get_template_placeholders'));
    }

    /**
     * Initialize default templates
     */
    private function init_default_templates() {
        $this->default_templates = array(
            'attendance_alert' => array(
                'name' => __('Attendance Alert', 'school-management-system'),
                'content' => __('Dear {parent_name}, {student_name} was absent from {class_name} on {date}. Please contact the school if this is unexpected. - {school_name}', 'school-management-system'),
                'category' => 'attendance',
                'placeholders' => array('parent_name', 'student_name', 'class_name', 'date', 'school_name'),
                'description' => __('Sent to parents when their child is marked absent', 'school-management-system')
            ),
            'fee_reminder' => array(
                'name' => __('Fee Reminder', 'school-management-system'),
                'content' => __('Dear {parent_name}, {student_name}\'s {fee_type} of KES {amount} is due on {due_date}. Pay via M-Pesa: {mpesa_number} or visit the school office. - {school_name}', 'school-management-system'),
                'category' => 'financial',
                'placeholders' => array('parent_name', 'student_name', 'fee_type', 'amount', 'due_date', 'mpesa_number', 'school_name'),
                'description' => __('Sent to parents for fee payment reminders', 'school-management-system')
            ),
            'fee_overdue' => array(
                'name' => __('Overdue Fee Notice', 'school-management-system'),
                'content' => __('URGENT: Dear {parent_name}, {student_name}\'s {fee_type} of KES {amount} is overdue by {days_overdue} days. Please pay immediately to avoid penalties. - {school_name}', 'school-management-system'),
                'category' => 'financial',
                'placeholders' => array('parent_name', 'student_name', 'fee_type', 'amount', 'days_overdue', 'school_name'),
                'description' => __('Sent to parents for overdue fee payments', 'school-management-system')
            ),
            'payment_confirmation' => array(
                'name' => __('Payment Confirmation', 'school-management-system'),
                'content' => __('Dear {parent_name}, we have received your payment of KES {amount} for {student_name}\'s {fee_type}. Receipt: {receipt_number}. Thank you! - {school_name}', 'school-management-system'),
                'category' => 'financial',
                'placeholders' => array('parent_name', 'amount', 'student_name', 'fee_type', 'receipt_number', 'school_name'),
                'description' => __('Sent to parents to confirm successful payments', 'school-management-system')
            ),
            'general_notice' => array(
                'name' => __('General Notice', 'school-management-system'),
                'content' => __('Dear {recipient_name}, {notice_content} For more information, contact the school office. - {school_name}', 'school-management-system'),
                'category' => 'communication',
                'placeholders' => array('recipient_name', 'notice_content', 'school_name'),
                'description' => __('General purpose notice template', 'school-management-system')
            ),
            'urgent_notice' => array(
                'name' => __('Urgent Notice', 'school-management-system'),
                'content' => __('URGENT: Dear {recipient_name}, {notice_content} Please take immediate action. - {school_name}', 'school-management-system'),
                'category' => 'communication',
                'placeholders' => array('recipient_name', 'notice_content', 'school_name'),
                'description' => __('Template for urgent notices and announcements', 'school-management-system')
            ),
            'transport_update' => array(
                'name' => __('Transport Update', 'school-management-system'),
                'content' => __('Dear {parent_name}, transport route for {student_name} has been updated. New pickup time: {pickup_time} at {pickup_location}. - {school_name}', 'school-management-system'),
                'category' => 'transport',
                'placeholders' => array('parent_name', 'student_name', 'pickup_time', 'pickup_location', 'school_name'),
                'description' => __('Sent when transport schedules are updated', 'school-management-system')
            ),
            'exam_reminder' => array(
                'name' => __('Exam Reminder', 'school-management-system'),
                'content' => __('Dear {parent_name}, reminder that {student_name} has {exam_name} on {exam_date} at {exam_time}. Please ensure they are well prepared. - {school_name}', 'school-management-system'),
                'category' => 'academic',
                'placeholders' => array('parent_name', 'student_name', 'exam_name', 'exam_date', 'exam_time', 'school_name'),
                'description' => __('Sent to remind parents about upcoming exams', 'school-management-system')
            ),
            'event_invitation' => array(
                'name' => __('Event Invitation', 'school-management-system'),
                'content' => __('Dear {recipient_name}, you are invited to {event_name} on {event_date} at {event_time}. Venue: {event_venue}. RSVP by {rsvp_date}. - {school_name}', 'school-management-system'),
                'category' => 'events',
                'placeholders' => array('recipient_name', 'event_name', 'event_date', 'event_time', 'event_venue', 'rsvp_date', 'school_name'),
                'description' => __('Sent for school events and activities', 'school-management-system')
            ),
            'welcome_student' => array(
                'name' => __('Welcome New Student', 'school-management-system'),
                'content' => __('Welcome to {school_name}! Dear {parent_name}, {student_name} has been successfully enrolled in {class_name}. Admission number: {admission_number}. School starts on {start_date}.', 'school-management-system'),
                'category' => 'enrollment',
                'placeholders' => array('school_name', 'parent_name', 'student_name', 'class_name', 'admission_number', 'start_date'),
                'description' => __('Sent to welcome newly enrolled students', 'school-management-system')
            )
        );
    }

    /**
     * Initialize available placeholders
     */
    private function init_placeholders() {
        $this->available_placeholders = array(
            'student' => array(
                'student_name' => __('Student full name', 'school-management-system'),
                'student_first_name' => __('Student first name', 'school-management-system'),
                'student_last_name' => __('Student last name', 'school-management-system'),
                'admission_number' => __('Student admission number', 'school-management-system'),
                'class_name' => __('Student class name', 'school-management-system'),
                'grade_level' => __('Student grade level', 'school-management-system')
            ),
            'parent' => array(
                'parent_name' => __('Parent full name', 'school-management-system'),
                'parent_first_name' => __('Parent first name', 'school-management-system'),
                'parent_phone' => __('Parent phone number', 'school-management-system'),
                'parent_email' => __('Parent email address', 'school-management-system')
            ),
            'school' => array(
                'school_name' => __('School name', 'school-management-system'),
                'school_phone' => __('School phone number', 'school-management-system'),
                'school_email' => __('School email address', 'school-management-system'),
                'school_address' => __('School address', 'school-management-system'),
                'academic_year' => __('Current academic year', 'school-management-system'),
                'current_term' => __('Current term', 'school-management-system')
            ),
            'financial' => array(
                'fee_type' => __('Type of fee', 'school-management-system'),
                'amount' => __('Fee amount', 'school-management-system'),
                'due_date' => __('Fee due date', 'school-management-system'),
                'days_overdue' => __('Days overdue', 'school-management-system'),
                'receipt_number' => __('Payment receipt number', 'school-management-system'),
                'mpesa_number' => __('M-Pesa payment number', 'school-management-system'),
                'balance' => __('Outstanding balance', 'school-management-system')
            ),
            'attendance' => array(
                'date' => __('Attendance date', 'school-management-system'),
                'time' => __('Time of absence', 'school-management-system'),
                'reason' => __('Reason for absence', 'school-management-system'),
                'attendance_percentage' => __('Attendance percentage', 'school-management-system')
            ),
            'transport' => array(
                'pickup_time' => __('Pickup time', 'school-management-system'),
                'pickup_location' => __('Pickup location', 'school-management-system'),
                'drop_time' => __('Drop-off time', 'school-management-system'),
                'route_name' => __('Transport route name', 'school-management-system'),
                'driver_name' => __('Driver name', 'school-management-system'),
                'driver_phone' => __('Driver phone number', 'school-management-system')
            ),
            'academic' => array(
                'exam_name' => __('Exam name', 'school-management-system'),
                'exam_date' => __('Exam date', 'school-management-system'),
                'exam_time' => __('Exam time', 'school-management-system'),
                'subject' => __('Subject name', 'school-management-system'),
                'teacher_name' => __('Teacher name', 'school-management-system'),
                'grade' => __('Student grade/score', 'school-management-system')
            ),
            'events' => array(
                'event_name' => __('Event name', 'school-management-system'),
                'event_date' => __('Event date', 'school-management-system'),
                'event_time' => __('Event time', 'school-management-system'),
                'event_venue' => __('Event venue', 'school-management-system'),
                'rsvp_date' => __('RSVP deadline', 'school-management-system'),
                'event_description' => __('Event description', 'school-management-system')
            ),
            'general' => array(
                'recipient_name' => __('Recipient name', 'school-management-system'),
                'notice_content' => __('Notice content', 'school-management-system'),
                'date' => __('Current date', 'school-management-system'),
                'time' => __('Current time', 'school-management-system'),
                'start_date' => __('Start date', 'school-management-system'),
                'end_date' => __('End date', 'school-management-system')
            )
        );
    }

    /**
     * Get all templates
     */
    public function get_templates($category = null) {
        $custom_templates = get_option('sms_custom_templates', array());
        $all_templates = array_merge($this->default_templates, $custom_templates);

        if ($category) {
            return array_filter($all_templates, function($template) use ($category) {
                return $template['category'] === $category;
            });
        }

        return $all_templates;
    }

    /**
     * Get single template
     */
    public function get_template($template_id) {
        $templates = $this->get_templates();
        return $templates[$template_id] ?? null;
    }

    /**
     * Save template
     */
    public function save_template_data($template_id, $template_data) {
        $custom_templates = get_option('sms_custom_templates', array());
        
        // Validate template data
        $validated_data = $this->validate_template_data($template_data);
        if (is_wp_error($validated_data)) {
            return $validated_data;
        }

        $custom_templates[$template_id] = $validated_data;
        update_option('sms_custom_templates', $custom_templates);

        return true;
    }

    /**
     * Delete template
     */
    public function delete_template_data($template_id) {
        // Don't allow deletion of default templates
        if (isset($this->default_templates[$template_id])) {
            return new WP_Error('cannot_delete_default', __('Cannot delete default templates', 'school-management-system'));
        }

        $custom_templates = get_option('sms_custom_templates', array());
        
        if (isset($custom_templates[$template_id])) {
            unset($custom_templates[$template_id]);
            update_option('sms_custom_templates', $custom_templates);
            return true;
        }

        return new WP_Error('template_not_found', __('Template not found', 'school-management-system'));
    }

    /**
     * Render template with data
     */
    public function render_template($template_id, $data = array()) {
        $template = $this->get_template($template_id);
        
        if (!$template) {
            return new WP_Error('template_not_found', __('Template not found', 'school-management-system'));
        }

        $content = $template['content'];
        
        // Replace placeholders with actual data
        foreach ($data as $key => $value) {
            $placeholder = '{' . $key . '}';
            $content = str_replace($placeholder, $value, $content);
        }

        // Replace any remaining school-specific placeholders
        $content = $this->replace_school_placeholders($content);

        return $content;
    }

    /**
     * Replace school-specific placeholders
     */
    private function replace_school_placeholders($content) {
        $school_data = array(
            'school_name' => get_option('sms_school_name', get_bloginfo('name')),
            'school_phone' => get_option('sms_school_phone', ''),
            'school_email' => get_option('sms_school_email', get_option('admin_email')),
            'school_address' => get_option('sms_school_address', ''),
            'academic_year' => get_option('sms_academic_year', ''),
            'current_term' => get_option('sms_current_term', ''),
            'date' => date_i18n(get_option('date_format')),
            'time' => date_i18n(get_option('time_format'))
        );

        foreach ($school_data as $key => $value) {
            $placeholder = '{' . $key . '}';
            $content = str_replace($placeholder, $value, $content);
        }

        return $content;
    }

    /**
     * Get available placeholders for a category
     */
    public function get_placeholders($category = null) {
        if ($category && isset($this->available_placeholders[$category])) {
            return $this->available_placeholders[$category];
        }

        return $this->available_placeholders;
    }

    /**
     * Validate template data
     */
    private function validate_template_data($data) {
        $required_fields = array('name', 'content', 'category');
        
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                return new WP_Error('missing_field', sprintf(__('Field %s is required', 'school-management-system'), $field));
            }
        }

        // Sanitize data
        $validated = array(
            'name' => sanitize_text_field($data['name']),
            'content' => sanitize_textarea_field($data['content']),
            'category' => sanitize_text_field($data['category']),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'placeholders' => $this->extract_placeholders($data['content'])
        );

        // Validate content length (SMS limit)
        if (strlen($validated['content']) > 1000) {
            return new WP_Error('content_too_long', __('Template content is too long. Maximum 1000 characters.', 'school-management-system'));
        }

        return $validated;
    }

    /**
     * Extract placeholders from template content
     */
    private function extract_placeholders($content) {
        preg_match_all('/\{([^}]+)\}/', $content, $matches);
        return array_unique($matches[1]);
    }

    /**
     * Get template categories
     */
    public function get_categories() {
        return array(
            'attendance' => __('Attendance', 'school-management-system'),
            'financial' => __('Financial', 'school-management-system'),
            'communication' => __('Communication', 'school-management-system'),
            'transport' => __('Transport', 'school-management-system'),
            'academic' => __('Academic', 'school-management-system'),
            'events' => __('Events', 'school-management-system'),
            'enrollment' => __('Enrollment', 'school-management-system'),
            'general' => __('General', 'school-management-system')
        );
    }

    /**
     * AJAX handler for saving template
     */
    public function save_template() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_template_nonce')) {
            wp_send_json_error(__('Security check failed', 'school-management-system'));
        }

        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to save templates', 'school-management-system'));
        }

        $template_id = sanitize_text_field($_POST['template_id']);
        $template_data = array(
            'name' => sanitize_text_field($_POST['template_name']),
            'content' => sanitize_textarea_field($_POST['template_content']),
            'category' => sanitize_text_field($_POST['template_category']),
            'description' => sanitize_textarea_field($_POST['template_description'])
        );

        $result = $this->save_template_data($template_id, $template_data);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(array(
            'message' => __('Template saved successfully', 'school-management-system'),
            'template_id' => $template_id
        ));
    }

    /**
     * AJAX handler for deleting template
     */
    public function delete_template() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_template_nonce')) {
            wp_send_json_error(__('Security check failed', 'school-management-system'));
        }

        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to delete templates', 'school-management-system'));
        }

        $template_id = sanitize_text_field($_POST['template_id']);
        $result = $this->delete_template_data($template_id);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(array(
            'message' => __('Template deleted successfully', 'school-management-system')
        ));
    }

    /**
     * AJAX handler for previewing template
     */
    public function preview_template() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_template_nonce')) {
            wp_send_json_error(__('Security check failed', 'school-management-system'));
        }

        $template_content = sanitize_textarea_field($_POST['template_content']);
        $sample_data = $this->get_sample_data();

        // Render template with sample data
        $preview = $template_content;
        foreach ($sample_data as $key => $value) {
            $placeholder = '{' . $key . '}';
            $preview = str_replace($placeholder, $value, $preview);
        }

        // Replace school placeholders
        $preview = $this->replace_school_placeholders($preview);

        wp_send_json_success(array(
            'preview' => $preview,
            'character_count' => strlen($preview)
        ));
    }

    /**
     * AJAX handler for getting template placeholders
     */
    public function get_template_placeholders() {
        $category = sanitize_text_field($_POST['category'] ?? '');
        $placeholders = $this->get_placeholders($category);

        wp_send_json_success(array(
            'placeholders' => $placeholders
        ));
    }

    /**
     * Get sample data for template preview
     */
    private function get_sample_data() {
        return array(
            'student_name' => 'John Doe',
            'student_first_name' => 'John',
            'student_last_name' => 'Doe',
            'admission_number' => 'ADM001',
            'class_name' => 'Grade 5A',
            'grade_level' => 'Grade 5',
            'parent_name' => 'Jane Doe',
            'parent_first_name' => 'Jane',
            'parent_phone' => '+254712345678',
            'parent_email' => 'jane.doe@example.com',
            'fee_type' => 'Tuition Fee',
            'amount' => '15,000',
            'due_date' => date('d/m/Y', strtotime('+30 days')),
            'days_overdue' => '5',
            'receipt_number' => 'RCP001',
            'mpesa_number' => '123456',
            'balance' => '5,000',
            'date' => date('d/m/Y'),
            'time' => date('H:i'),
            'pickup_time' => '07:30',
            'pickup_location' => 'Main Gate',
            'route_name' => 'Route A',
            'exam_name' => 'Mid-Term Exam',
            'exam_date' => date('d/m/Y', strtotime('+7 days')),
            'exam_time' => '09:00',
            'event_name' => 'Sports Day',
            'event_date' => date('d/m/Y', strtotime('+14 days')),
            'event_time' => '10:00',
            'event_venue' => 'School Grounds',
            'rsvp_date' => date('d/m/Y', strtotime('+10 days')),
            'recipient_name' => 'John Doe',
            'notice_content' => 'This is a sample notice content.',
            'start_date' => date('d/m/Y', strtotime('+1 month'))
        );
    }

    /**
     * Get template usage statistics
     */
    public function get_template_usage_stats() {
        $usage_stats = get_option('sms_template_usage_stats', array());
        $templates = $this->get_templates();
        
        $stats = array();
        foreach ($templates as $template_id => $template) {
            $stats[$template_id] = array(
                'name' => $template['name'],
                'category' => $template['category'],
                'usage_count' => $usage_stats[$template_id] ?? 0,
                'last_used' => $usage_stats[$template_id . '_last_used'] ?? null
            );
        }

        // Sort by usage count
        uasort($stats, function($a, $b) {
            return $b['usage_count'] - $a['usage_count'];
        });

        return $stats;
    }

    /**
     * Track template usage
     */
    public function track_template_usage($template_id) {
        $usage_stats = get_option('sms_template_usage_stats', array());
        
        $usage_stats[$template_id] = ($usage_stats[$template_id] ?? 0) + 1;
        $usage_stats[$template_id . '_last_used'] = current_time('mysql');
        
        update_option('sms_template_usage_stats', $usage_stats);
    }
}

// Initialize the template manager
new SMS_Template_Manager();