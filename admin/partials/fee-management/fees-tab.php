<?php
/**
 * Fee Structures Tab
 *
 * Interface for managing individual fee structures.
 *
 * @package    School_Management_System
 * @subpackage School_Management_System/admin/partials/fee-management
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Get recent fees
$recent_fees = get_posts([
    'post_type' => 'sms_fees',
    'post_status' => 'publish',
    'posts_per_page' => 10,
    'orderby' => 'date',
    'order' => 'DESC'
]);

// Get fee statistics
$fee_stats = [
    'total_fees' => wp_count_posts('sms_fees')->publish,
    'active_fees' => 0,
    'inactive_fees' => 0,
    'total_amount' => 0
];

foreach ($recent_fees as $fee) {
    $status = get_field('fee_status', $fee->ID);
    $amount = floatval(get_field('fee_amount', $fee->ID));
    
    if ($status === 'active') {
        $fee_stats['active_fees']++;
    } else {
        $fee_stats['inactive_fees']++;
    }
    
    $fee_stats['total_amount'] += $amount;
}
?>

<div class="fees-tab-content">
    <!-- Fee Statistics -->
    <div class="report-summary">
        <div class="summary-card">
            <span class="summary-value"><?php echo number_format($fee_stats['total_fees']); ?></span>
            <span class="summary-label"><?php _e('Total Fees', 'school-management-system'); ?></span>
        </div>
        <div class="summary-card">
            <span class="summary-value"><?php echo number_format($fee_stats['active_fees']); ?></span>
            <span class="summary-label"><?php _e('Active Fees', 'school-management-system'); ?></span>
        </div>
        <div class="summary-card">
            <span class="summary-value"><?php echo number_format($fee_stats['inactive_fees']); ?></span>
            <span class="summary-label"><?php _e('Inactive Fees', 'school-management-system'); ?></span>
        </div>
        <div class="summary-card">
            <span class="summary-value">KES <?php echo number_format($fee_stats['total_amount'], 2); ?></span>
            <span class="summary-label"><?php _e('Total Amount', 'school-management-system'); ?></span>
        </div>
    </div>

    <div class="fee-actions">
        <a href="<?php echo admin_url('post-new.php?post_type=sms_fees'); ?>" class="button button-primary">
            <?php _e('Add New Fee', 'school-management-system'); ?>
        </a>
        <a href="<?php echo admin_url('edit.php?post_type=sms_fees'); ?>" class="button">
            <?php _e('View All Fees', 'school-management-system'); ?>
        </a>
        <button type="button" class="button" id="quick-fee-setup">
            <?php _e('Quick Fee Setup', 'school-management-system'); ?>
        </button>
    </div>

    <!-- Quick Fee Creation Form -->
    <div id="quick-fee-form" style="display: none;">
        <div class="fee-form-section">
            <h3><?php _e('Quick Fee Setup', 'school-management-system'); ?></h3>
            <form method="post" action="">
                <?php wp_nonce_field('sms_fee_action', 'sms_fee_nonce'); ?>
                <input type="hidden" name="action" value="create_fee">
                
                <div class="fee-form-grid">
                    <!-- Basic Information -->
                    <div class="fee-form-section">
                        <h4><?php _e('Basic Information', 'school-management-system'); ?></h4>
                        
                        <div class="form-field">
                            <label for="fee_name"><?php _e('Fee Name', 'school-management-system'); ?> *</label>
                            <input type="text" id="fee_name" name="fee_data[fee_name]" required>
                            <div class="field-description"><?php _e('e.g., Tuition Fee, Transport Fee', 'school-management-system'); ?></div>
                        </div>
                        
                        <div class="form-field">
                            <label for="fee_code"><?php _e('Fee Code', 'school-management-system'); ?> *</label>
                            <input type="text" id="fee_code" name="fee_data[fee_code]" maxlength="10" required>
                            <div class="field-description"><?php _e('Short code (2-10 characters, uppercase)', 'school-management-system'); ?></div>
                        </div>
                        
                        <div class="form-field">
                            <label for="fee_type"><?php _e('Fee Category', 'school-management-system'); ?> *</label>
                            <select id="fee_type" name="fee_data[fee_type]" required>
                                <?php foreach ($fee_categories as $key => $category): ?>
                                    <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($category['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-field">
                            <label for="fee_amount"><?php _e('Amount (KES)', 'school-management-system'); ?> *</label>
                            <input type="number" id="fee_amount" name="fee_data[fee_amount]" min="0" step="0.01" required>
                        </div>
                        
                        <div class="form-field">
                            <label for="payment_frequency"><?php _e('Payment Frequency', 'school-management-system'); ?></label>
                            <select id="payment_frequency" name="fee_data[payment_frequency]">
                                <option value="one_time"><?php _e('One Time', 'school-management-system'); ?></option>
                                <option value="termly" selected><?php _e('Termly', 'school-management-system'); ?></option>
                                <option value="monthly"><?php _e('Monthly', 'school-management-system'); ?></option>
                                <option value="yearly"><?php _e('Yearly', 'school-management-system'); ?></option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Payment Options -->
                    <div class="fee-form-section">
                        <h4><?php _e('Payment Options', 'school-management-system'); ?></h4>
                        
                        <div class="form-field">
                            <label for="due_date_type"><?php _e('Due Date Type', 'school-management-system'); ?></label>
                            <select id="due_date_type" name="fee_data[due_date_type]">
                                <option value="term_start" selected><?php _e('Days after Term Start', 'school-management-system'); ?></option>
                                <option value="enrollment"><?php _e('Days after Enrollment', 'school-management-system'); ?></option>
                                <option value="fixed"><?php _e('Fixed Date', 'school-management-system'); ?></option>
                            </select>
                        </div>
                        
                        <div class="form-field" id="due_date_days_field">
                            <label for="due_date_days"><?php _e('Days After', 'school-management-system'); ?></label>
                            <input type="number" id="due_date_days" name="fee_data[due_date_days]" value="30" min="0">
                        </div>
                        
                        <div class="form-field" id="due_date_fixed_field" style="display: none;">
                            <label for="due_date_fixed"><?php _e('Fixed Due Date', 'school-management-system'); ?></label>
                            <input type="date" id="due_date_fixed" name="fee_data[due_date_fixed]">
                        </div>
                        
                        <div class="form-field">
                            <label>
                                <input type="checkbox" name="fee_data[mandatory]" value="1" checked>
                                <?php _e('Mandatory Fee', 'school-management-system'); ?>
                            </label>
                        </div>
                        
                        <div class="form-field">
                            <label>
                                <input type="checkbox" name="fee_data[auto_generate_invoices]" value="1" checked>
                                <?php _e('Auto-Generate Invoices', 'school-management-system'); ?>
                            </label>
                        </div>
                    </div>
                    
                    <!-- Installments -->
                    <div class="fee-form-section">
                        <h4><?php _e('Installment Options', 'school-management-system'); ?></h4>
                        
                        <div class="installments-container">
                            <!-- Installment rows will be added here -->
                        </div>
                        
                        <button type="button" class="btn-add-installment btn-add-row">
                            <?php _e('Add Installment', 'school-management-system'); ?>
                        </button>
                    </div>
                    
                    <!-- Penalties -->
                    <div class="fee-form-section">
                        <h4><?php _e('Late Payment Penalties', 'school-management-system'); ?></h4>
                        
                        <div class="form-field">
                            <label>
                                <input type="checkbox" name="fee_data[late_payment_penalty][penalty_enabled]" value="1" checked>
                                <?php _e('Enable Penalties', 'school-management-system'); ?>
                            </label>
                        </div>
                        
                        <div class="penalty-settings">
                            <div class="form-field">
                                <label for="penalty_type"><?php _e('Penalty Type', 'school-management-system'); ?></label>
                                <select id="penalty_type" name="fee_data[late_payment_penalty][penalty_type]">
                                    <option value="percentage" selected><?php _e('Percentage of Fee', 'school-management-system'); ?></option>
                                    <option value="fixed"><?php _e('Fixed Amount', 'school-management-system'); ?></option>
                                    <option value="daily"><?php _e('Daily Penalty', 'school-management-system'); ?></option>
                                </select>
                            </div>
                            
                            <div class="form-field">
                                <label for="penalty_amount"><?php _e('Penalty Amount/Percentage', 'school-management-system'); ?></label>
                                <input type="number" id="penalty_amount" name="fee_data[late_payment_penalty][penalty_amount]" value="5" min="0" step="0.01">
                            </div>
                            
                            <div class="form-field">
                                <label for="penalty_grace_days"><?php _e('Grace Period (Days)', 'school-management-system'); ?></label>
                                <input type="number" id="penalty_grace_days" name="fee_data[late_payment_penalty][penalty_grace_days]" value="7" min="0">
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="button button-primary">
                        <?php _e('Create Fee Structure', 'school-management-system'); ?>
                    </button>
                    <button type="button" class="button" id="cancel-quick-fee">
                        <?php _e('Cancel', 'school-management-system'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Recent Fees -->
    <div class="recent-fees">
        <h3><?php _e('Recent Fee Structures', 'school-management-system'); ?></h3>
        
        <?php if (!empty($recent_fees)): ?>
            <div class="fees-grid">
                <?php foreach ($recent_fees as $fee): 
                    $fee_name = get_field('fee_name', $fee->ID);
                    $fee_code = get_field('fee_code', $fee->ID);
                    $fee_type = get_field('fee_type', $fee->ID);
                    $fee_amount = get_field('fee_amount', $fee->ID);
                    $fee_status = get_field('fee_status', $fee->ID);
                    $mandatory = get_field('mandatory', $fee->ID);
                    $category = $fee_categories[$fee_type] ?? [];
                ?>
                    <div class="fee-card">
                        <div class="fee-header">
                            <div class="fee-color" style="background-color: <?php echo esc_attr($category['color'] ?? '#2196F3'); ?>"></div>
                            <div class="fee-title">
                                <h4><?php echo esc_html($fee_name ?: $fee->post_title); ?></h4>
                                <span class="fee-code"><?php echo esc_html($fee_code); ?></span>
                            </div>
                            <div class="fee-status status-<?php echo esc_attr($fee_status); ?>">
                                <?php echo esc_html(ucfirst($fee_status)); ?>
                            </div>
                        </div>
                        
                        <div class="fee-details">
                            <div class="fee-amount">
                                <span class="amount-label"><?php _e('Amount:', 'school-management-system'); ?></span>
                                <span class="amount-value">KES <?php echo number_format($fee_amount, 2); ?></span>
                            </div>
                            
                            <div class="fee-meta">
                                <span class="fee-category"><?php echo esc_html($category['name'] ?? ucfirst($fee_type)); ?></span>
                                <?php if ($mandatory): ?>
                                    <span class="mandatory-badge"><?php _e('Mandatory', 'school-management-system'); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="fee-actions">
                            <a href="<?php echo get_edit_post_link($fee->ID); ?>" class="button button-small">
                                <?php _e('Edit', 'school-management-system'); ?>
                            </a>
                            <a href="#" class="button button-small view-fee-details" data-fee-id="<?php echo $fee->ID; ?>">
                                <?php _e('View Details', 'school-management-system'); ?>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-fees-message">
                <p><?php _e('No fee structures found.', 'school-management-system'); ?></p>
                <a href="<?php echo admin_url('post-new.php?post_type=sms_fees'); ?>" class="button button-primary">
                    <?php _e('Create Your First Fee', 'school-management-system'); ?>
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.fees-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.fee-card {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 6px;
    padding: 20px;
    transition: box-shadow 0.2s;
}

.fee-card:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.fee-header {
    display: flex;
    align-items: center;
    margin-bottom: 15px;
}

.fee-color {
    width: 4px;
    height: 40px;
    border-radius: 2px;
    margin-right: 12px;
}

.fee-title {
    flex: 1;
}

.fee-title h4 {
    margin: 0 0 4px 0;
    font-size: 16px;
    font-weight: 600;
}

.fee-code {
    font-size: 12px;
    color: #666;
    background: #f0f0f0;
    padding: 2px 6px;
    border-radius: 3px;
}

.fee-status {
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 500;
}

.fee-status.status-active {
    background: #d4edda;
    color: #155724;
}

.fee-status.status-inactive {
    background: #f8d7da;
    color: #721c24;
}

.fee-details {
    margin-bottom: 15px;
}

.fee-amount {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.amount-label {
    font-size: 14px;
    color: #666;
}

.amount-value {
    font-size: 18px;
    font-weight: 600;
    color: #2271b1;
}

.fee-meta {
    display: flex;
    gap: 8px;
    align-items: center;
}

.fee-category {
    font-size: 12px;
    color: #666;
    background: #f0f0f0;
    padding: 2px 6px;
    border-radius: 3px;
}

.mandatory-badge {
    font-size: 11px;
    background: #ff6b35;
    color: #fff;
    padding: 2px 6px;
    border-radius: 3px;
}

.fee-actions {
    display: flex;
    gap: 8px;
}

.no-fees-message {
    text-align: center;
    padding: 40px 20px;
    background: #f9f9f9;
    border-radius: 6px;
}

.fee-actions {
    margin-bottom: 20px;
}

.fee-actions .button {
    margin-right: 10px;
}

.penalty-settings {
    margin-left: 20px;
}

.form-actions {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #ddd;
}

.form-actions .button {
    margin-right: 10px;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Show/hide quick fee form
    $('#quick-fee-setup').on('click', function() {
        $('#quick-fee-form').slideToggle();
    });
    
    $('#cancel-quick-fee').on('click', function() {
        $('#quick-fee-form').slideUp();
    });
    
    // Due date type change
    $('#due_date_type').on('change', function() {
        var type = $(this).val();
        if (type === 'fixed') {
            $('#due_date_days_field').hide();
            $('#due_date_fixed_field').show();
        } else {
            $('#due_date_days_field').show();
            $('#due_date_fixed_field').hide();
        }
    });
    
    // Fee code auto-generation
    $('#fee_name').on('blur', function() {
        var name = $(this).val();
        var code = $('#fee_code').val();
        
        if (name && !code) {
            // Generate code from name
            var generatedCode = name.toUpperCase()
                .replace(/[^A-Z0-9\s]/g, '')
                .split(' ')
                .map(word => word.substring(0, 3))
                .join('')
                .substring(0, 10);
            
            $('#fee_code').val(generatedCode);
        }
    });
    
    // View fee details modal (placeholder)
    $('.view-fee-details').on('click', function(e) {
        e.preventDefault();
        var feeId = $(this).data('fee-id');
        // Implementation for viewing fee details in modal
        alert('Fee details modal for fee ID: ' + feeId);
    });
});
</script>