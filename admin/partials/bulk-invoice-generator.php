<?php
/**
 * Bulk Invoice Generator Interface
 *
 * Admin interface for generating invoices in bulk for multiple students.
 *
 * @package    School_Management_System
 * @subpackage School_Management_System/admin/partials
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Get available fees for selection
$fees_query = new WP_Query([
    'post_type' => 'sms_fees',
    'post_status' => 'publish',
    'posts_per_page' => -1,
    'meta_query' => [
        [
            'key' => 'fee_status',
            'value' => 'active',
            'compare' => '='
        ]
    ]
]);

// Get available grades and classes for filtering
$grades = get_terms(['taxonomy' => 'sms_grades', 'hide_empty' => false]);
$classes_query = new WP_Query([
    'post_type' => 'sms_classes',
    'post_status' => 'publish',
    'posts_per_page' => -1
]);

// Get academic years and terms
$academic_years = get_terms(['taxonomy' => 'sms_academic_years', 'hide_empty' => false]);
$terms = get_terms(['taxonomy' => 'sms_terms', 'hide_empty' => false]);
?>

<div class="wrap">
    <h1><?php _e('Bulk Invoice Generator', 'school-management-system'); ?></h1>
    
    <div class="sms-bulk-invoice-generator">
        <form id="bulk-invoice-form" method="post">
            <?php wp_nonce_field('sms_bulk_generate_invoices', 'bulk_invoice_nonce'); ?>
            
            <!-- Step 1: Select Students -->
            <div class="invoice-step" id="step-1">
                <h2><?php _e('Step 1: Select Students', 'school-management-system'); ?></h2>
                
                <div class="student-selection-options">
                    <div class="selection-method">
                        <label>
                            <input type="radio" name="selection_method" value="all" checked>
                            <?php _e('All Active Students', 'school-management-system'); ?>
                        </label>
                    </div>
                    
                    <div class="selection-method">
                        <label>
                            <input type="radio" name="selection_method" value="by_grade">
                            <?php _e('By Grade Level', 'school-management-system'); ?>
                        </label>
                        <div class="grade-selection" style="display: none;">
                            <?php if (!empty($grades)): ?>
                                <?php foreach ($grades as $grade): ?>
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="selected_grades[]" value="<?php echo esc_attr($grade->term_id); ?>">
                                        <?php echo esc_html($grade->name); ?>
                                    </label>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="selection-method">
                        <label>
                            <input type="radio" name="selection_method" value="by_class">
                            <?php _e('By Class', 'school-management-system'); ?>
                        </label>
                        <div class="class-selection" style="display: none;">
                            <?php if ($classes_query->have_posts()): ?>
                                <?php while ($classes_query->have_posts()): $classes_query->the_post(); ?>
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="selected_classes[]" value="<?php echo get_the_ID(); ?>">
                                        <?php echo get_the_title(); ?>
                                    </label>
                                <?php endwhile; ?>
                                <?php wp_reset_postdata(); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="selection-method">
                        <label>
                            <input type="radio" name="selection_method" value="individual">
                            <?php _e('Individual Students', 'school-management-system'); ?>
                        </label>
                        <div class="individual-selection" style="display: none;">
                            <input type="text" id="student-search" placeholder="<?php _e('Search students...', 'school-management-system'); ?>">
                            <div id="selected-students-list"></div>
                            <input type="hidden" name="selected_students" id="selected-students-input">
                        </div>
                    </div>
                </div>
                
                <div class="student-preview">
                    <h3><?php _e('Selected Students Preview', 'school-management-system'); ?></h3>
                    <div id="student-count">0 students selected</div>
                    <div id="student-list-preview"></div>
                </div>
                
                <button type="button" class="button button-primary" onclick="nextStep(2)">
                    <?php _e('Next: Select Fees', 'school-management-system'); ?>
                </button>
            </div>
            
            <!-- Step 2: Select Fees -->
            <div class="invoice-step" id="step-2" style="display: none;">
                <h2><?php _e('Step 2: Select Fees', 'school-management-system'); ?></h2>
                
                <div class="fee-selection">
                    <?php if ($fees_query->have_posts()): ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th class="check-column">
                                        <input type="checkbox" id="select-all-fees">
                                    </th>
                                    <th><?php _e('Fee Name', 'school-management-system'); ?></th>
                                    <th><?php _e('Amount', 'school-management-system'); ?></th>
                                    <th><?php _e('Type', 'school-management-system'); ?></th>
                                    <th><?php _e('Custom Amount', 'school-management-system'); ?></th>
                                    <th><?php _e('Quantity', 'school-management-system'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($fees_query->have_posts()): $fees_query->the_post(); ?>
                                    <?php
                                    $fee_id = get_the_ID();
                                    $fee_amount = get_field('fee_amount', $fee_id);
                                    $fee_type = get_field('fee_type', $fee_id);
                                    ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" name="selected_fees[]" value="<?php echo $fee_id; ?>" 
                                                   class="fee-checkbox" data-fee-id="<?php echo $fee_id; ?>">
                                        </td>
                                        <td>
                                            <strong><?php echo get_the_title(); ?></strong>
                                            <?php if (get_the_content()): ?>
                                                <br><small><?php echo wp_trim_words(get_the_content(), 10); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo number_format($fee_amount, 2); ?> KES</td>
                                        <td><?php echo esc_html(ucfirst($fee_type)); ?></td>
                                        <td>
                                            <input type="number" name="custom_amounts[<?php echo $fee_id; ?>]" 
                                                   step="0.01" min="0" placeholder="<?php echo $fee_amount; ?>"
                                                   class="small-text" disabled>
                                        </td>
                                        <td>
                                            <input type="number" name="quantities[<?php echo $fee_id; ?>]" 
                                                   value="1" min="1" max="10" class="small-text" disabled>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                                <?php wp_reset_postdata(); ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p><?php _e('No active fees found. Please create fees first.', 'school-management-system'); ?></p>
                    <?php endif; ?>
                </div>
                
                <div class="fee-summary">
                    <h3><?php _e('Selected Fees Summary', 'school-management-system'); ?></h3>
                    <div id="fee-summary-content">
                        <p><?php _e('No fees selected.', 'school-management-system'); ?></p>
                    </div>
                </div>
                
                <div class="step-navigation">
                    <button type="button" class="button" onclick="previousStep(1)">
                        <?php _e('Previous: Select Students', 'school-management-system'); ?>
                    </button>
                    <button type="button" class="button button-primary" onclick="nextStep(3)">
                        <?php _e('Next: Configure Options', 'school-management-system'); ?>
                    </button>
                </div>
            </div>
            
            <!-- Step 3: Configure Options -->
            <div class="invoice-step" id="step-3" style="display: none;">
                <h2><?php _e('Step 3: Configure Invoice Options', 'school-management-system'); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="invoice_date"><?php _e('Invoice Date', 'school-management-system'); ?></label>
                        </th>
                        <td>
                            <input type="date" name="invoice_date" id="invoice_date" 
                                   value="<?php echo current_time('Y-m-d'); ?>" required>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="due_date_option"><?php _e('Due Date', 'school-management-system'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="radio" name="due_date_option" value="days" checked>
                                <?php _e('Days from invoice date:', 'school-management-system'); ?>
                                <input type="number" name="due_days" value="30" min="1" max="365" class="small-text">
                            </label>
                            <br>
                            <label>
                                <input type="radio" name="due_date_option" value="fixed">
                                <?php _e('Fixed date:', 'school-management-system'); ?>
                                <input type="date" name="due_date_fixed" class="regular-text">
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="academic_year"><?php _e('Academic Year', 'school-management-system'); ?></label>
                        </th>
                        <td>
                            <select name="academic_year" id="academic_year">
                                <option value=""><?php _e('Select Academic Year', 'school-management-system'); ?></option>
                                <?php foreach ($academic_years as $year): ?>
                                    <option value="<?php echo esc_attr($year->term_id); ?>">
                                        <?php echo esc_html($year->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="term"><?php _e('Term', 'school-management-system'); ?></label>
                        </th>
                        <td>
                            <select name="term" id="term">
                                <option value=""><?php _e('Select Term', 'school-management-system'); ?></option>
                                <?php foreach ($terms as $term): ?>
                                    <option value="<?php echo esc_attr($term->term_id); ?>">
                                        <?php echo esc_html($term->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="initial_status"><?php _e('Initial Status', 'school-management-system'); ?></label>
                        </th>
                        <td>
                            <select name="initial_status" id="initial_status">
                                <option value="draft"><?php _e('Draft', 'school-management-system'); ?></option>
                                <option value="sent" selected><?php _e('Sent', 'school-management-system'); ?></option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label><?php _e('Payment Methods', 'school-management-system'); ?></label>
                        </th>
                        <td>
                            <label><input type="checkbox" name="payment_methods[]" value="mpesa" checked> M-Pesa</label><br>
                            <label><input type="checkbox" name="payment_methods[]" value="airtel_money" checked> Airtel Money</label><br>
                            <label><input type="checkbox" name="payment_methods[]" value="bank_transfer"> Bank Transfer</label><br>
                            <label><input type="checkbox" name="payment_methods[]" value="cash" checked> Cash Payment</label><br>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="payment_instructions"><?php _e('Payment Instructions', 'school-management-system'); ?></label>
                        </th>
                        <td>
                            <textarea name="payment_instructions" id="payment_instructions" rows="3" class="large-text"
                                      placeholder="<?php _e('Special payment instructions...', 'school-management-system'); ?>"></textarea>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label><?php _e('Notifications', 'school-management-system'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="send_email_notifications" value="1" checked>
                                <?php _e('Send email notifications to parents', 'school-management-system'); ?>
                            </label><br>
                            <label>
                                <input type="checkbox" name="send_sms_notifications" value="1">
                                <?php _e('Send SMS notifications to parents', 'school-management-system'); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label><?php _e('Options', 'school-management-system'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="check_existing_invoices" value="1" checked>
                                <?php _e('Skip students with existing unpaid invoices for these fees', 'school-management-system'); ?>
                            </label><br>
                            <label>
                                <input type="checkbox" name="include_penalties" value="1">
                                <?php _e('Include overdue penalties in calculations', 'school-management-system'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                
                <div class="step-navigation">
                    <button type="button" class="button" onclick="previousStep(2)">
                        <?php _e('Previous: Select Fees', 'school-management-system'); ?>
                    </button>
                    <button type="button" class="button button-primary" onclick="nextStep(4)">
                        <?php _e('Next: Review & Generate', 'school-management-system'); ?>
                    </button>
                </div>
            </div>
            
            <!-- Step 4: Review & Generate -->
            <div class="invoice-step" id="step-4" style="display: none;">
                <h2><?php _e('Step 4: Review & Generate Invoices', 'school-management-system'); ?></h2>
                
                <div class="generation-summary">
                    <div class="summary-section">
                        <h3><?php _e('Students', 'school-management-system'); ?></h3>
                        <div id="final-student-count"></div>
                    </div>
                    
                    <div class="summary-section">
                        <h3><?php _e('Fees', 'school-management-system'); ?></h3>
                        <div id="final-fee-summary"></div>
                    </div>
                    
                    <div class="summary-section">
                        <h3><?php _e('Options', 'school-management-system'); ?></h3>
                        <div id="final-options-summary"></div>
                    </div>
                </div>
                
                <div class="generation-controls">
                    <div class="progress-container" style="display: none;">
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: 0%;"></div>
                        </div>
                        <div class="progress-text">Preparing to generate invoices...</div>
                    </div>
                    
                    <div class="generation-results" style="display: none;">
                        <div id="generation-success" class="notice notice-success" style="display: none;">
                            <p><strong><?php _e('Success!', 'school-management-system'); ?></strong> 
                               <span id="success-count">0</span> <?php _e('invoices generated successfully.', 'school-management-system'); ?></p>
                        </div>
                        
                        <div id="generation-errors" class="notice notice-error" style="display: none;">
                            <p><strong><?php _e('Errors:', 'school-management-system'); ?></strong> 
                               <span id="error-count">0</span> <?php _e('invoices failed to generate.', 'school-management-system'); ?></p>
                            <div id="error-details"></div>
                        </div>
                    </div>
                </div>
                
                <div class="step-navigation">
                    <button type="button" class="button" onclick="previousStep(3)">
                        <?php _e('Previous: Configure Options', 'school-management-system'); ?>
                    </button>
                    <button type="button" class="button button-primary" id="generate-invoices-btn">
                        <?php _e('Generate Invoices', 'school-management-system'); ?>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<style>
.sms-bulk-invoice-generator {
    max-width: 1000px;
}

.invoice-step {
    background: #fff;
    border: 1px solid #ccd0d4;
    padding: 20px;
    margin-bottom: 20px;
}

.invoice-step h2 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #ddd;
}

.selection-method {
    margin-bottom: 15px;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.selection-method label {
    font-weight: bold;
}

.grade-selection,
.class-selection,
.individual-selection {
    margin-top: 10px;
    padding: 10px;
    background: #f9f9f9;
    border-radius: 4px;
}

.checkbox-label {
    display: inline-block;
    margin-right: 15px;
    margin-bottom: 5px;
    font-weight: normal;
}

.student-preview {
    margin-top: 20px;
    padding: 15px;
    background: #f0f8ff;
    border: 1px solid #cce7ff;
    border-radius: 4px;
}

.fee-selection table {
    margin-bottom: 20px;
}

.fee-summary {
    padding: 15px;
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.step-navigation {
    margin-top: 20px;
    padding-top: 15px;
    border-top: 1px solid #ddd;
}

.step-navigation .button {
    margin-right: 10px;
}

.generation-summary {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

.summary-section {
    padding: 15px;
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.summary-section h3 {
    margin-top: 0;
    margin-bottom: 10px;
}

.progress-container {
    margin: 20px 0;
}

.progress-bar {
    width: 100%;
    height: 20px;
    background: #f0f0f0;
    border-radius: 10px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: #0073aa;
    transition: width 0.3s ease;
}

.progress-text {
    margin-top: 10px;
    text-align: center;
    font-weight: bold;
}

.generation-results {
    margin-top: 20px;
}

#error-details {
    margin-top: 10px;
    max-height: 200px;
    overflow-y: auto;
    background: #fff;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

@media (max-width: 768px) {
    .generation-summary {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Selection method change handlers
    $('input[name="selection_method"]').change(function() {
        $('.grade-selection, .class-selection, .individual-selection').hide();
        
        if ($(this).val() === 'by_grade') {
            $('.grade-selection').show();
        } else if ($(this).val() === 'by_class') {
            $('.class-selection').show();
        } else if ($(this).val() === 'individual') {
            $('.individual-selection').show();
        }
        
        updateStudentPreview();
    });
    
    // Student selection change handlers
    $('input[name="selected_grades[]"], input[name="selected_classes[]"]').change(updateStudentPreview);
    
    // Fee selection handlers
    $('#select-all-fees').change(function() {
        $('.fee-checkbox').prop('checked', $(this).is(':checked')).trigger('change');
    });
    
    $('.fee-checkbox').change(function() {
        const isChecked = $(this).is(':checked');
        const row = $(this).closest('tr');
        
        row.find('input[type="number"]').prop('disabled', !isChecked);
        
        if (!isChecked) {
            row.find('input[type="number"]').val('');
        } else {
            // Set default quantity
            row.find('input[name^="quantities"]').val(1);
        }
        
        updateFeesSummary();
    });
    
    // Due date option change
    $('input[name="due_date_option"]').change(function() {
        if ($(this).val() === 'days') {
            $('input[name="due_days"]').prop('disabled', false);
            $('input[name="due_date_fixed"]').prop('disabled', true);
        } else {
            $('input[name="due_days"]').prop('disabled', true);
            $('input[name="due_date_fixed"]').prop('disabled', false);
        }
    });
    
    // Generate invoices button
    $('#generate-invoices-btn').click(function() {
        if (confirm('<?php _e('Are you sure you want to generate invoices for the selected students?', 'school-management-system'); ?>')) {
            generateInvoices();
        }
    });
    
    // Student search functionality
    $('#student-search').on('input', function() {
        const searchTerm = $(this).val();
        if (searchTerm.length >= 2) {
            searchStudents(searchTerm);
        }
    });
});

function nextStep(stepNumber) {
    // Validate current step
    const currentStep = $('.invoice-step:visible').attr('id').split('-')[1];
    
    if (!validateStep(currentStep)) {
        return;
    }
    
    // Hide current step and show next
    $('.invoice-step').hide();
    $('#step-' + stepNumber).show();
    
    // Update summary if going to final step
    if (stepNumber === 4) {
        updateFinalSummary();
    }
}

function previousStep(stepNumber) {
    $('.invoice-step').hide();
    $('#step-' + stepNumber).show();
}

function validateStep(stepNumber) {
    switch (stepNumber) {
        case '1':
            // Validate student selection
            const selectionMethod = $('input[name="selection_method"]:checked').val();
            if (selectionMethod === 'by_grade' && $('input[name="selected_grades[]"]:checked').length === 0) {
                alert('<?php _e('Please select at least one grade.', 'school-management-system'); ?>');
                return false;
            }
            if (selectionMethod === 'by_class' && $('input[name="selected_classes[]"]:checked').length === 0) {
                alert('<?php _e('Please select at least one class.', 'school-management-system'); ?>');
                return false;
            }
            if (selectionMethod === 'individual' && !$('#selected-students-input').val()) {
                alert('<?php _e('Please select at least one student.', 'school-management-system'); ?>');
                return false;
            }
            break;
            
        case '2':
            // Validate fee selection
            if ($('.fee-checkbox:checked').length === 0) {
                alert('<?php _e('Please select at least one fee.', 'school-management-system'); ?>');
                return false;
            }
            break;
            
        case '3':
            // Validate options
            if (!$('#invoice_date').val()) {
                alert('<?php _e('Please select an invoice date.', 'school-management-system'); ?>');
                return false;
            }
            
            const dueDateOption = $('input[name="due_date_option"]:checked').val();
            if (dueDateOption === 'days' && !$('input[name="due_days"]').val()) {
                alert('<?php _e('Please specify the number of days for due date.', 'school-management-system'); ?>');
                return false;
            }
            if (dueDateOption === 'fixed' && !$('input[name="due_date_fixed"]').val()) {
                alert('<?php _e('Please select a fixed due date.', 'school-management-system'); ?>');
                return false;
            }
            break;
    }
    
    return true;
}

function updateStudentPreview() {
    // This would make an AJAX call to get student count and preview
    // For now, just show placeholder
    $('#student-count').text('Loading...');
    
    // AJAX call to get student preview
    const formData = new FormData();
    formData.append('action', 'sms_get_student_preview');
    formData.append('nonce', '<?php echo wp_create_nonce('sms_student_preview'); ?>');
    formData.append('selection_method', $('input[name="selection_method"]:checked').val());
    
    // Add selected values based on method
    const selectionMethod = $('input[name="selection_method"]:checked').val();
    if (selectionMethod === 'by_grade') {
        $('input[name="selected_grades[]"]:checked').each(function() {
            formData.append('selected_grades[]', $(this).val());
        });
    } else if (selectionMethod === 'by_class') {
        $('input[name="selected_classes[]"]:checked').each(function() {
            formData.append('selected_classes[]', $(this).val());
        });
    }
    
    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            if (response.success) {
                $('#student-count').text(response.data.count + ' students selected');
                $('#student-list-preview').html(response.data.preview);
            }
        }
    });
}

function updateFeesSummary() {
    let totalAmount = 0;
    let selectedFees = [];
    
    $('.fee-checkbox:checked').each(function() {
        const row = $(this).closest('tr');
        const feeName = row.find('td:nth-child(2) strong').text();
        const customAmount = row.find('input[name^="custom_amounts"]').val();
        const quantity = row.find('input[name^="quantities"]').val() || 1;
        const originalAmount = parseFloat(row.find('td:nth-child(3)').text().replace(/[^\d.]/g, ''));
        
        const unitPrice = customAmount ? parseFloat(customAmount) : originalAmount;
        const lineTotal = unitPrice * quantity;
        
        selectedFees.push({
            name: feeName,
            quantity: quantity,
            unitPrice: unitPrice,
            total: lineTotal
        });
        
        totalAmount += lineTotal;
    });
    
    if (selectedFees.length > 0) {
        let summaryHtml = '<ul>';
        selectedFees.forEach(function(fee) {
            summaryHtml += '<li>' + fee.name + ' (Qty: ' + fee.quantity + ') - ' + 
                          fee.total.toFixed(2) + ' KES</li>';
        });
        summaryHtml += '</ul>';
        summaryHtml += '<p><strong>Total per student: ' + totalAmount.toFixed(2) + ' KES</strong></p>';
        
        $('#fee-summary-content').html(summaryHtml);
    } else {
        $('#fee-summary-content').html('<p><?php _e('No fees selected.', 'school-management-system'); ?></p>');
    }
}

function updateFinalSummary() {
    // Update student count
    $('#final-student-count').html($('#student-count').html());
    
    // Update fee summary
    $('#final-fee-summary').html($('#fee-summary-content').html());
    
    // Update options summary
    let optionsHtml = '<ul>';
    optionsHtml += '<li>Invoice Date: ' + $('#invoice_date').val() + '</li>';
    
    const dueDateOption = $('input[name="due_date_option"]:checked').val();
    if (dueDateOption === 'days') {
        optionsHtml += '<li>Due Date: ' + $('input[name="due_days"]').val() + ' days from invoice date</li>';
    } else {
        optionsHtml += '<li>Due Date: ' + $('input[name="due_date_fixed"]').val() + '</li>';
    }
    
    optionsHtml += '<li>Status: ' + $('#initial_status option:selected').text() + '</li>';
    
    const paymentMethods = [];
    $('input[name="payment_methods[]"]:checked').each(function() {
        paymentMethods.push($(this).next().text());
    });
    if (paymentMethods.length > 0) {
        optionsHtml += '<li>Payment Methods: ' + paymentMethods.join(', ') + '</li>';
    }
    
    optionsHtml += '</ul>';
    $('#final-options-summary').html(optionsHtml);
}

function generateInvoices() {
    const $btn = $('#generate-invoices-btn');
    const $progress = $('.progress-container');
    const $results = $('.generation-results');
    
    // Disable button and show progress
    $btn.prop('disabled', true).text('<?php _e('Generating...', 'school-management-system'); ?>');
    $progress.show();
    $results.hide();
    
    // Prepare form data
    const formData = new FormData(document.getElementById('bulk-invoice-form'));
    formData.append('action', 'sms_bulk_generate_invoices');
    
    // Make AJAX request
    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        xhr: function() {
            const xhr = new window.XMLHttpRequest();
            xhr.upload.addEventListener('progress', function(evt) {
                if (evt.lengthComputable) {
                    const percentComplete = evt.loaded / evt.total * 100;
                    $('.progress-fill').css('width', percentComplete + '%');
                }
            }, false);
            return xhr;
        },
        success: function(response) {
            $progress.hide();
            $results.show();
            
            if (response.success) {
                const data = response.data;
                
                if (data.success_count > 0) {
                    $('#success-count').text(data.success_count);
                    $('#generation-success').show();
                }
                
                if (data.error_count > 0) {
                    $('#error-count').text(data.error_count);
                    
                    let errorHtml = '<ul>';
                    data.errors.forEach(function(error) {
                        errorHtml += '<li>Student ID ' + error.student_id + ': ' + error.error + '</li>';
                    });
                    errorHtml += '</ul>';
                    
                    $('#error-details').html(errorHtml);
                    $('#generation-errors').show();
                }
                
                // Re-enable button
                $btn.prop('disabled', false).text('<?php _e('Generate More Invoices', 'school-management-system'); ?>');
                
            } else {
                alert('<?php _e('Error generating invoices:', 'school-management-system'); ?> ' + response.data);
                $btn.prop('disabled', false).text('<?php _e('Generate Invoices', 'school-management-system'); ?>');
            }
        },
        error: function() {
            $progress.hide();
            alert('<?php _e('An error occurred while generating invoices.', 'school-management-system'); ?>');
            $btn.prop('disabled', false).text('<?php _e('Generate Invoices', 'school-management-system'); ?>');
        }
    });
}

function searchStudents(searchTerm) {
    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'sms_search_students',
            nonce: '<?php echo wp_create_nonce('sms_search_students'); ?>',
            search: searchTerm
        },
        success: function(response) {
            if (response.success) {
                // Display search results for selection
                // Implementation would show a dropdown or list of students to select
            }
        }
    });
}
</script>