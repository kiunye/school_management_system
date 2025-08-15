<?php
/**
 * Student Admission Form Handler
 *
 * Handles the student admission form display and processing.
 *
 * @package    School_Management_System
 * @subpackage School_Management_System/includes/admin
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Student Admission Form Class
 */
class SMS_Student_Admission_Form extends SMS_Base {

    /**
     * Initialize the admission form handler.
     */
    public function __construct() {
        parent::__construct();
        
        // Add admin menu for student admission
        add_action('admin_menu', [$this, 'add_admission_menu'], 20);
        
        // Handle form submission
        add_action('admin_post_sms_submit_student_admission', [$this, 'handle_admission_form_submission']);
        
        // Add admin notices for form feedback
        add_action('admin_notices', [$this, 'display_admin_notices']);
    }

    /**
     * Add admission form to admin menu.
     */
    public function add_admission_menu() {
        add_submenu_page(
            'sms-dashboard',
            __('Student Admission', 'school-management-system'),
            __('New Admission', 'school-management-system'),
            'manage_students',
            'sms-student-admission',
            [$this, 'render_admission_form']
        );
    }

    /**
     * Render the student admission form.
     */
    public function render_admission_form() {
        // Check user permissions
        if (!current_user_can('manage_students')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'school-management-system'));
        }

        // Get available grades and academic years for dropdowns
        $grades = get_terms([
            'taxonomy' => 'sms_grades',
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC'
        ]);

        $academic_years = get_terms([
            'taxonomy' => 'sms_academic_years',
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'DESC'
        ]);

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                <?php wp_nonce_field('sms_student_admission', 'sms_admission_nonce'); ?>
                <input type="hidden" name="action" value="sms_submit_student_admission">
                
                <div id="poststuff">
                    <div id="post-body" class="metabox-holder columns-2">
                        <div id="post-body-content">
                            <!-- Basic Information -->
                            <div class="postbox">
                                <div class="postbox-header">
                                    <h2 class="hndle"><?php _e('Basic Information', 'school-management-system'); ?></h2>
                                </div>
                                <div class="inside">
                                    <table class="form-table">
                                        <tr>
                                            <th scope="row">
                                                <label for="full_name"><?php _e('Full Name', 'school-management-system'); ?> <span class="required">*</span></label>
                                            </th>
                                            <td>
                                                <input type="text" id="full_name" name="full_name" class="regular-text" required>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row">
                                                <label for="date_of_birth"><?php _e('Date of Birth', 'school-management-system'); ?> <span class="required">*</span></label>
                                            </th>
                                            <td>
                                                <input type="date" id="date_of_birth" name="date_of_birth" required>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row">
                                                <label for="gender"><?php _e('Gender', 'school-management-system'); ?> <span class="required">*</span></label>
                                            </th>
                                            <td>
                                                <select id="gender" name="gender" required>
                                                    <option value=""><?php _e('Select Gender', 'school-management-system'); ?></option>
                                                    <option value="male"><?php _e('Male', 'school-management-system'); ?></option>
                                                    <option value="female"><?php _e('Female', 'school-management-system'); ?></option>
                                                    <option value="other"><?php _e('Other', 'school-management-system'); ?></option>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row">
                                                <label for="national_id"><?php _e('National ID / Birth Certificate Number', 'school-management-system'); ?></label>
                                            </th>
                                            <td>
                                                <input type="text" id="national_id" name="national_id" class="regular-text">
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row">
                                                <label for="grade_level"><?php _e('Grade Level', 'school-management-system'); ?> <span class="required">*</span></label>
                                            </th>
                                            <td>
                                                <select id="grade_level" name="grade_level" required>
                                                    <option value=""><?php _e('Select Grade', 'school-management-system'); ?></option>
                                                    <?php foreach ($grades as $grade): ?>
                                                        <option value="<?php echo esc_attr($grade->slug); ?>"><?php echo esc_html($grade->name); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row">
                                                <label for="academic_year"><?php _e('Academic Year', 'school-management-system'); ?> <span class="required">*</span></label>
                                            </th>
                                            <td>
                                                <select id="academic_year" name="academic_year" required>
                                                    <option value=""><?php _e('Select Academic Year', 'school-management-system'); ?></option>
                                                    <?php foreach ($academic_years as $year): ?>
                                                        <option value="<?php echo esc_attr($year->slug); ?>"><?php echo esc_html($year->name); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>

                            <!-- Parent/Guardian Information -->
                            <div class="postbox">
                                <div class="postbox-header">
                                    <h2 class="hndle"><?php _e('Parent/Guardian Information', 'school-management-system'); ?></h2>
                                </div>
                                <div class="inside">
                                    <div id="parent-details-container">
                                        <div class="parent-detail-row" data-index="0">
                                            <h4><?php _e('Primary Parent/Guardian', 'school-management-system'); ?></h4>
                                            <table class="form-table">
                                                <tr>
                                                    <th scope="row">
                                                        <label for="parent_type_0"><?php _e('Relationship', 'school-management-system'); ?> <span class="required">*</span></label>
                                                    </th>
                                                    <td>
                                                        <select id="parent_type_0" name="parent_details[0][parent_type]" required>
                                                            <option value=""><?php _e('Select Relationship', 'school-management-system'); ?></option>
                                                            <option value="father"><?php _e('Father', 'school-management-system'); ?></option>
                                                            <option value="mother"><?php _e('Mother', 'school-management-system'); ?></option>
                                                            <option value="guardian"><?php _e('Guardian', 'school-management-system'); ?></option>
                                                            <option value="other"><?php _e('Other', 'school-management-system'); ?></option>
                                                        </select>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <th scope="row">
                                                        <label for="parent_name_0"><?php _e('Full Name', 'school-management-system'); ?> <span class="required">*</span></label>
                                                    </th>
                                                    <td>
                                                        <input type="text" id="parent_name_0" name="parent_details[0][parent_name]" class="regular-text" required>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <th scope="row">
                                                        <label for="parent_phone_0"><?php _e('Phone Number', 'school-management-system'); ?> <span class="required">*</span></label>
                                                    </th>
                                                    <td>
                                                        <input type="tel" id="parent_phone_0" name="parent_details[0][parent_phone]" class="regular-text" required>
                                                        <p class="description"><?php _e('Format: 0712345678 or +254712345678', 'school-management-system'); ?></p>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <th scope="row">
                                                        <label for="parent_email_0"><?php _e('Email Address', 'school-management-system'); ?></label>
                                                    </th>
                                                    <td>
                                                        <input type="email" id="parent_email_0" name="parent_details[0][parent_email]" class="regular-text">
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <th scope="row">
                                                        <label for="parent_occupation_0"><?php _e('Occupation', 'school-management-system'); ?></label>
                                                    </th>
                                                    <td>
                                                        <input type="text" id="parent_occupation_0" name="parent_details[0][parent_occupation]" class="regular-text">
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <th scope="row">
                                                        <label for="parent_address_0"><?php _e('Address', 'school-management-system'); ?></label>
                                                    </th>
                                                    <td>
                                                        <textarea id="parent_address_0" name="parent_details[0][parent_address]" rows="3" cols="50"></textarea>
                                                    </td>
                                                </tr>
                                            </table>
                                        </div>
                                    </div>
                                    
                                    <p>
                                        <button type="button" id="add-parent-btn" class="button"><?php _e('Add Another Parent/Guardian', 'school-management-system'); ?></button>
                                    </p>
                                </div>
                            </div>

                            <!-- Medical Information -->
                            <div class="postbox">
                                <div class="postbox-header">
                                    <h2 class="hndle"><?php _e('Medical Information', 'school-management-system'); ?></h2>
                                </div>
                                <div class="inside">
                                    <table class="form-table">
                                        <tr>
                                            <th scope="row">
                                                <label for="blood_group"><?php _e('Blood Group', 'school-management-system'); ?></label>
                                            </th>
                                            <td>
                                                <select id="blood_group" name="blood_group">
                                                    <option value=""><?php _e('Select Blood Group', 'school-management-system'); ?></option>
                                                    <option value="A+">A+</option>
                                                    <option value="A-">A-</option>
                                                    <option value="B+">B+</option>
                                                    <option value="B-">B-</option>
                                                    <option value="AB+">AB+</option>
                                                    <option value="AB-">AB-</option>
                                                    <option value="O+">O+</option>
                                                    <option value="O-">O-</option>
                                                    <option value="unknown"><?php _e('Unknown', 'school-management-system'); ?></option>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row">
                                                <label for="allergies"><?php _e('Allergies', 'school-management-system'); ?></label>
                                            </th>
                                            <td>
                                                <textarea id="allergies" name="allergies" rows="3" cols="50" placeholder="<?php _e('List any known allergies...', 'school-management-system'); ?>"></textarea>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row">
                                                <label for="medical_conditions"><?php _e('Medical Conditions', 'school-management-system'); ?></label>
                                            </th>
                                            <td>
                                                <textarea id="medical_conditions" name="medical_conditions" rows="3" cols="50" placeholder="<?php _e('List any medical conditions or special needs...', 'school-management-system'); ?>"></textarea>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row">
                                                <label for="medications"><?php _e('Current Medications', 'school-management-system'); ?></label>
                                            </th>
                                            <td>
                                                <textarea id="medications" name="medications" rows="3" cols="50" placeholder="<?php _e('List any medications the student is currently taking...', 'school-management-system'); ?>"></textarea>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>

                            <!-- Emergency Contacts -->
                            <div class="postbox">
                                <div class="postbox-header">
                                    <h2 class="hndle"><?php _e('Emergency Contacts', 'school-management-system'); ?></h2>
                                </div>
                                <div class="inside">
                                    <div id="emergency-contacts-container">
                                        <div class="emergency-contact-row" data-index="0">
                                            <h4><?php _e('Emergency Contact 1', 'school-management-system'); ?></h4>
                                            <table class="form-table">
                                                <tr>
                                                    <th scope="row">
                                                        <label for="emergency_name_0"><?php _e('Full Name', 'school-management-system'); ?> <span class="required">*</span></label>
                                                    </th>
                                                    <td>
                                                        <input type="text" id="emergency_name_0" name="emergency_contacts[0][emergency_name]" class="regular-text" required>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <th scope="row">
                                                        <label for="emergency_relationship_0"><?php _e('Relationship', 'school-management-system'); ?> <span class="required">*</span></label>
                                                    </th>
                                                    <td>
                                                        <input type="text" id="emergency_relationship_0" name="emergency_contacts[0][emergency_relationship]" class="regular-text" required>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <th scope="row">
                                                        <label for="emergency_phone_0"><?php _e('Phone Number', 'school-management-system'); ?> <span class="required">*</span></label>
                                                    </th>
                                                    <td>
                                                        <input type="tel" id="emergency_phone_0" name="emergency_contacts[0][emergency_phone]" class="regular-text" required>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <th scope="row">
                                                        <label for="emergency_email_0"><?php _e('Email Address', 'school-management-system'); ?></label>
                                                    </th>
                                                    <td>
                                                        <input type="email" id="emergency_email_0" name="emergency_contacts[0][emergency_email]" class="regular-text">
                                                    </td>
                                                </tr>
                                            </table>
                                        </div>
                                    </div>
                                    
                                    <p>
                                        <button type="button" id="add-emergency-contact-btn" class="button"><?php _e('Add Another Emergency Contact', 'school-management-system'); ?></button>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div id="postbox-container-1" class="postbox-container">
                            <div class="postbox">
                                <div class="postbox-header">
                                    <h2 class="hndle"><?php _e('Admission Options', 'school-management-system'); ?></h2>
                                </div>
                                <div class="inside">
                                    <p>
                                        <label>
                                            <input type="checkbox" name="auto_approve" value="1">
                                            <?php _e('Auto-approve admission', 'school-management-system'); ?>
                                        </label>
                                        <br><small><?php _e('If checked, the student will be automatically approved for admission', 'school-management-system'); ?></small>
                                    </p>
                                    
                                    <p>
                                        <label>
                                            <input type="checkbox" name="create_parent_accounts" value="1" checked>
                                            <?php _e('Create parent accounts', 'school-management-system'); ?>
                                        </label>
                                        <br><small><?php _e('Automatically create user accounts for parents/guardians', 'school-management-system'); ?></small>
                                    </p>
                                </div>
                            </div>

                            <div class="postbox">
                                <div class="postbox-header">
                                    <h2 class="hndle"><?php _e('Submit Application', 'school-management-system'); ?></h2>
                                </div>
                                <div class="inside">
                                    <p>
                                        <?php submit_button(__('Submit Student Admission', 'school-management-system'), 'primary', 'submit', false); ?>
                                    </p>
                                    <p>
                                        <a href="<?php echo admin_url('edit.php?post_type=sms_students'); ?>" class="button"><?php _e('Cancel', 'school-management-system'); ?></a>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <style>
        .required { color: #d63638; }
        .parent-detail-row, .emergency-contact-row { 
            border: 1px solid #ddd; 
            padding: 15px; 
            margin-bottom: 15px; 
            background: #f9f9f9; 
        }
        .parent-detail-row h4, .emergency-contact-row h4 { 
            margin-top: 0; 
            color: #23282d; 
        }
        .remove-row-btn { 
            float: right; 
            color: #d63638; 
        }
        </style>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            var parentIndex = 1;
            var emergencyIndex = 1;
            
            // Add parent/guardian
            $('#add-parent-btn').on('click', function() {
                if (parentIndex >= 2) {
                    alert('<?php echo esc_js(__('Maximum 2 parents/guardians allowed', 'school-management-system')); ?>');
                    return;
                }
                
                var parentHtml = `
                    <div class="parent-detail-row" data-index="${parentIndex}">
                        <h4><?php _e('Additional Parent/Guardian', 'school-management-system'); ?> 
                            <button type="button" class="button-link remove-row-btn remove-parent-btn"><?php _e('Remove', 'school-management-system'); ?></button>
                        </h4>
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="parent_type_${parentIndex}"><?php _e('Relationship', 'school-management-system'); ?> <span class="required">*</span></label>
                                </th>
                                <td>
                                    <select id="parent_type_${parentIndex}" name="parent_details[${parentIndex}][parent_type]" required>
                                        <option value=""><?php _e('Select Relationship', 'school-management-system'); ?></option>
                                        <option value="father"><?php _e('Father', 'school-management-system'); ?></option>
                                        <option value="mother"><?php _e('Mother', 'school-management-system'); ?></option>
                                        <option value="guardian"><?php _e('Guardian', 'school-management-system'); ?></option>
                                        <option value="other"><?php _e('Other', 'school-management-system'); ?></option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="parent_name_${parentIndex}"><?php _e('Full Name', 'school-management-system'); ?> <span class="required">*</span></label>
                                </th>
                                <td>
                                    <input type="text" id="parent_name_${parentIndex}" name="parent_details[${parentIndex}][parent_name]" class="regular-text" required>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="parent_phone_${parentIndex}"><?php _e('Phone Number', 'school-management-system'); ?> <span class="required">*</span></label>
                                </th>
                                <td>
                                    <input type="tel" id="parent_phone_${parentIndex}" name="parent_details[${parentIndex}][parent_phone]" class="regular-text" required>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="parent_email_${parentIndex}"><?php _e('Email Address', 'school-management-system'); ?></label>
                                </th>
                                <td>
                                    <input type="email" id="parent_email_${parentIndex}" name="parent_details[${parentIndex}][parent_email]" class="regular-text">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="parent_occupation_${parentIndex}"><?php _e('Occupation', 'school-management-system'); ?></label>
                                </th>
                                <td>
                                    <input type="text" id="parent_occupation_${parentIndex}" name="parent_details[${parentIndex}][parent_occupation]" class="regular-text">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="parent_address_${parentIndex}"><?php _e('Address', 'school-management-system'); ?></label>
                                </th>
                                <td>
                                    <textarea id="parent_address_${parentIndex}" name="parent_details[${parentIndex}][parent_address]" rows="3" cols="50"></textarea>
                                </td>
                            </tr>
                        </table>
                    </div>
                `;
                
                $('#parent-details-container').append(parentHtml);
                parentIndex++;
            });
            
            // Remove parent/guardian
            $(document).on('click', '.remove-parent-btn', function() {
                $(this).closest('.parent-detail-row').remove();
                parentIndex--;
            });
            
            // Add emergency contact
            $('#add-emergency-contact-btn').on('click', function() {
                if (emergencyIndex >= 3) {
                    alert('<?php echo esc_js(__('Maximum 3 emergency contacts allowed', 'school-management-system')); ?>');
                    return;
                }
                
                var emergencyHtml = `
                    <div class="emergency-contact-row" data-index="${emergencyIndex}">
                        <h4><?php _e('Emergency Contact', 'school-management-system'); ?> ${emergencyIndex + 1}
                            <button type="button" class="button-link remove-row-btn remove-emergency-btn"><?php _e('Remove', 'school-management-system'); ?></button>
                        </h4>
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="emergency_name_${emergencyIndex}"><?php _e('Full Name', 'school-management-system'); ?> <span class="required">*</span></label>
                                </th>
                                <td>
                                    <input type="text" id="emergency_name_${emergencyIndex}" name="emergency_contacts[${emergencyIndex}][emergency_name]" class="regular-text" required>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="emergency_relationship_${emergencyIndex}"><?php _e('Relationship', 'school-management-system'); ?> <span class="required">*</span></label>
                                </th>
                                <td>
                                    <input type="text" id="emergency_relationship_${emergencyIndex}" name="emergency_contacts[${emergencyIndex}][emergency_relationship]" class="regular-text" required>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="emergency_phone_${emergencyIndex}"><?php _e('Phone Number', 'school-management-system'); ?> <span class="required">*</span></label>
                                </th>
                                <td>
                                    <input type="tel" id="emergency_phone_${emergencyIndex}" name="emergency_contacts[${emergencyIndex}][emergency_phone]" class="regular-text" required>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="emergency_email_${emergencyIndex}"><?php _e('Email Address', 'school-management-system'); ?></label>
                                </th>
                                <td>
                                    <input type="email" id="emergency_email_${emergencyIndex}" name="emergency_contacts[${emergencyIndex}][emergency_email]" class="regular-text">
                                </td>
                            </tr>
                        </table>
                    </div>
                `;
                
                $('#emergency-contacts-container').append(emergencyHtml);
                emergencyIndex++;
            });
            
            // Remove emergency contact
            $(document).on('click', '.remove-emergency-btn', function() {
                $(this).closest('.emergency-contact-row').remove();
                emergencyIndex--;
            });
        });
        </script>
        <?php
    }

    /**
     * Handle admission form submission.
     */
    public function handle_admission_form_submission() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['sms_admission_nonce'], 'sms_student_admission')) {
            wp_die(__('Security check failed', 'school-management-system'));
        }

        // Check permissions
        if (!current_user_can('manage_students')) {
            wp_die(__('You do not have sufficient permissions to submit student admissions.', 'school-management-system'));
        }

        // Sanitize and validate form data
        $student_data = $this->sanitize_form_data($_POST);
        
        // Create student using the student manager
        $student_manager = new SMS_Student_Manager();
        $auto_approve = isset($_POST['auto_approve']) && $_POST['auto_approve'] === '1';
        
        $result = $student_manager->create_student($student_data, $auto_approve);

        if (is_wp_error($result)) {
            // Redirect with error message
            $redirect_url = add_query_arg([
                'page' => 'sms-student-admission',
                'message' => 'error',
                'error_message' => urlencode($result->get_error_message())
            ], admin_url('admin.php'));
        } else {
            // Set taxonomies
            if (!empty($student_data['grade_level'])) {
                wp_set_object_terms($result, $student_data['grade_level'], 'sms_grades');
            }
            if (!empty($student_data['academic_year'])) {
                wp_set_object_terms($result, $student_data['academic_year'], 'sms_academic_years');
            }

            // Redirect with success message
            $redirect_url = add_query_arg([
                'page' => 'sms-student-admission',
                'message' => 'success',
                'student_id' => $result
            ], admin_url('admin.php'));
        }

        wp_redirect($redirect_url);
        exit;
    }

    /**
     * Sanitize form data.
     */
    private function sanitize_form_data($data) {
        $sanitized = [];

        // Basic information
        $sanitized['full_name'] = sanitize_text_field($data['full_name']);
        $sanitized['date_of_birth'] = sanitize_text_field($data['date_of_birth']);
        $sanitized['gender'] = sanitize_text_field($data['gender']);
        $sanitized['national_id'] = sanitize_text_field($data['national_id']);
        $sanitized['grade_level'] = sanitize_text_field($data['grade_level']);
        $sanitized['academic_year'] = sanitize_text_field($data['academic_year']);

        // Medical information
        $sanitized['blood_group'] = sanitize_text_field($data['blood_group']);
        $sanitized['allergies'] = sanitize_textarea_field($data['allergies']);
        $sanitized['medical_conditions'] = sanitize_textarea_field($data['medical_conditions']);
        $sanitized['medications'] = sanitize_textarea_field($data['medications']);

        // Parent details
        if (isset($data['parent_details']) && is_array($data['parent_details'])) {
            $sanitized['parent_details'] = [];
            foreach ($data['parent_details'] as $parent) {
                $sanitized['parent_details'][] = [
                    'parent_type' => sanitize_text_field($parent['parent_type']),
                    'parent_name' => sanitize_text_field($parent['parent_name']),
                    'parent_phone' => sanitize_text_field($parent['parent_phone']),
                    'parent_email' => sanitize_email($parent['parent_email']),
                    'parent_occupation' => sanitize_text_field($parent['parent_occupation']),
                    'parent_address' => sanitize_textarea_field($parent['parent_address'])
                ];
            }
        }

        // Emergency contacts
        if (isset($data['emergency_contacts']) && is_array($data['emergency_contacts'])) {
            $sanitized['emergency_contacts'] = [];
            foreach ($data['emergency_contacts'] as $contact) {
                $sanitized['emergency_contacts'][] = [
                    'emergency_name' => sanitize_text_field($contact['emergency_name']),
                    'emergency_relationship' => sanitize_text_field($contact['emergency_relationship']),
                    'emergency_phone' => sanitize_text_field($contact['emergency_phone']),
                    'emergency_email' => sanitize_email($contact['emergency_email'])
                ];
            }
        }

        return $sanitized;
    }

    /**
     * Display admin notices for form feedback.
     */
    public function display_admin_notices() {
        if (!isset($_GET['page']) || $_GET['page'] !== 'sms-student-admission') {
            return;
        }

        if (isset($_GET['message'])) {
            if ($_GET['message'] === 'success') {
                $student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
                $student_name = $student_id ? get_field('full_name', $student_id) : '';
                $admission_number = $student_id ? get_field('admission_number', $student_id) : '';
                
                echo '<div class="notice notice-success is-dismissible">';
                echo '<p>' . sprintf(
                    __('Student admission submitted successfully! %s has been assigned admission number %s.', 'school-management-system'),
                    '<strong>' . esc_html($student_name) . '</strong>',
                    '<strong>' . esc_html($admission_number) . '</strong>'
                );
                if ($student_id) {
                    echo ' <a href="' . get_edit_post_link($student_id) . '">' . __('View Student Record', 'school-management-system') . '</a>';
                }
                echo '</p></div>';
            } elseif ($_GET['message'] === 'error') {
                $error_message = isset($_GET['error_message']) ? urldecode($_GET['error_message']) : __('An error occurred', 'school-management-system');
                echo '<div class="notice notice-error is-dismissible">';
                echo '<p>' . esc_html($error_message) . '</p>';
                echo '</div>';
            }
        }
    }
}