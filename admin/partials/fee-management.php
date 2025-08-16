<?php
/**
 * Fee Management Admin Interface
 *
 * Provides the admin interface for managing fee structures,
 * categories, exemptions, and bulk operations.
 *
 * @package    School_Management_System
 * @subpackage School_Management_System/admin/partials
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Initialize managers
$fee_manager = new SMS_Fee_Manager();
$category_manager = new SMS_Fee_Category_Manager();
$exemption_manager = new SMS_Fee_Exemption_Manager();

// Get current tab
$current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'fees';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sms_fee_nonce']) && wp_verify_nonce($_POST['sms_fee_nonce'], 'sms_fee_action')) {
    $action = sanitize_text_field($_POST['action']);
    
    switch ($action) {
        case 'create_fee':
            $result = $fee_manager->create_fee_structure($_POST['fee_data']);
            if (is_wp_error($result)) {
                $error_message = $result->get_error_message();
            } else {
                $success_message = __('Fee structure created successfully.', 'school-management-system');
            }
            break;
            
        case 'create_category':
            $result = $category_manager->create_fee_category($_POST['category_key'], $_POST['category_data']);
            if (is_wp_error($result)) {
                $error_message = $result->get_error_message();
            } else {
                $success_message = __('Fee category created successfully.', 'school-management-system');
            }
            break;
            
        case 'bulk_create_fees':
            $result = $category_manager->bulk_create_fees_by_category($_POST['categories_data'], $_POST['common_settings']);
            $successful_count = count(array_filter($result, function($r) { return $r['success']; }));
            $success_message = sprintf(__('%d fees created successfully.', 'school-management-system'), $successful_count);
            break;
    }
}

// Get data for display
$fee_categories = $category_manager->get_fee_categories();
$category_statistics = $category_manager->get_category_statistics();
?>

<div class="wrap sms-fee-management">
    <h1><?php _e('Fee Management', 'school-management-system'); ?></h1>
    
    <?php if (isset($success_message)): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html($success_message); ?></p>
        </div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
        <div class="notice notice-error is-dismissible">
            <p><?php echo esc_html($error_message); ?></p>
        </div>
    <?php endif; ?>

    <!-- Tab Navigation -->
    <nav class="nav-tab-wrapper">
        <a href="?page=sms-fee-management&tab=fees" class="nav-tab <?php echo $current_tab === 'fees' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Fee Structures', 'school-management-system'); ?>
        </a>
        <a href="?page=sms-fee-management&tab=categories" class="nav-tab <?php echo $current_tab === 'categories' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Categories', 'school-management-system'); ?>
        </a>
        <a href="?page=sms-fee-management&tab=exemptions" class="nav-tab <?php echo $current_tab === 'exemptions' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Exemptions', 'school-management-system'); ?>
        </a>
        <a href="?page=sms-fee-management&tab=bulk" class="nav-tab <?php echo $current_tab === 'bulk' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Bulk Operations', 'school-management-system'); ?>
        </a>
        <a href="?page=sms-fee-management&tab=reports" class="nav-tab <?php echo $current_tab === 'reports' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Reports', 'school-management-system'); ?>
        </a>
    </nav>

    <div class="tab-content">
        <?php
        switch ($current_tab) {
            case 'fees':
                include 'fee-management/fees-tab.php';
                break;
            case 'categories':
                include 'fee-management/categories-tab.php';
                break;
            case 'exemptions':
                include 'fee-management/exemptions-tab.php';
                break;
            case 'bulk':
                include 'fee-management/bulk-tab.php';
                break;
            case 'reports':
                include 'fee-management/reports-tab.php';
                break;
            default:
                include 'fee-management/fees-tab.php';
        }
        ?>
    </div>
</div>

<style>
.sms-fee-management {
    margin: 20px 0;
}

.tab-content {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-top: none;
    padding: 20px;
}

.fee-category-card {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 15px;
    margin-bottom: 15px;
    position: relative;
}

.fee-category-card .category-header {
    display: flex;
    align-items: center;
    margin-bottom: 10px;
}

.fee-category-card .category-color {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    margin-right: 10px;
}

.fee-category-card .category-name {
    font-weight: 600;
    font-size: 16px;
}

.fee-category-card .category-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 10px;
    margin-top: 10px;
}

.fee-category-card .stat-item {
    text-align: center;
    padding: 8px;
    background: #f9f9f9;
    border-radius: 3px;
}

.fee-category-card .stat-value {
    display: block;
    font-weight: 600;
    font-size: 18px;
    color: #2271b1;
}

.fee-category-card .stat-label {
    display: block;
    font-size: 12px;
    color: #666;
    margin-top: 2px;
}

.fee-form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.fee-form-section {
    background: #f9f9f9;
    padding: 20px;
    border-radius: 4px;
}

.fee-form-section h3 {
    margin-top: 0;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #ddd;
}

.form-field {
    margin-bottom: 15px;
}

.form-field label {
    display: block;
    font-weight: 600;
    margin-bottom: 5px;
}

.form-field input,
.form-field select,
.form-field textarea {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 3px;
}

.form-field .field-description {
    font-size: 12px;
    color: #666;
    margin-top: 3px;
}

.installment-row {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr auto;
    gap: 10px;
    align-items: end;
    margin-bottom: 10px;
    padding: 10px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 3px;
}

.discount-row {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr 1fr auto;
    gap: 10px;
    align-items: end;
    margin-bottom: 10px;
    padding: 10px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 3px;
}

.btn-add-row,
.btn-remove-row {
    padding: 6px 12px;
    border: none;
    border-radius: 3px;
    cursor: pointer;
    font-size: 12px;
}

.btn-add-row {
    background: #2271b1;
    color: #fff;
}

.btn-remove-row {
    background: #d63638;
    color: #fff;
}

.bulk-operation-card {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 20px;
    margin-bottom: 20px;
}

.bulk-operation-card h3 {
    margin-top: 0;
    margin-bottom: 15px;
}

.operation-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}

.report-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.summary-card {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 20px;
    text-align: center;
}

.summary-card .summary-value {
    font-size: 32px;
    font-weight: 600;
    color: #2271b1;
    display: block;
}

.summary-card .summary-label {
    font-size: 14px;
    color: #666;
    margin-top: 5px;
}

@media (max-width: 768px) {
    .fee-form-grid {
        grid-template-columns: 1fr;
    }
    
    .installment-row,
    .discount-row {
        grid-template-columns: 1fr;
    }
    
    .operation-grid {
        grid-template-columns: 1fr;
    }
    
    .report-summary {
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Add installment row
    $('.btn-add-installment').on('click', function() {
        var container = $(this).siblings('.installments-container');
        var rowCount = container.find('.installment-row').length;
        var newRow = `
            <div class="installment-row">
                <div class="form-field">
                    <input type="text" name="installments[${rowCount}][name]" placeholder="Installment Name" required>
                </div>
                <div class="form-field">
                    <input type="number" name="installments[${rowCount}][percentage]" placeholder="%" min="1" max="100" required>
                </div>
                <div class="form-field">
                    <input type="number" name="installments[${rowCount}][due_days]" placeholder="Days" min="0" required>
                </div>
                <button type="button" class="btn-remove-row">Remove</button>
            </div>
        `;
        container.append(newRow);
    });
    
    // Remove installment row
    $(document).on('click', '.btn-remove-row', function() {
        $(this).closest('.installment-row, .discount-row').remove();
    });
    
    // Add discount row
    $('.btn-add-discount').on('click', function() {
        var container = $(this).siblings('.discounts-container');
        var rowCount = container.find('.discount-row').length;
        var newRow = `
            <div class="discount-row">
                <div class="form-field">
                    <input type="text" name="discounts[${rowCount}][name]" placeholder="Discount Name" required>
                </div>
                <div class="form-field">
                    <select name="discounts[${rowCount}][type]" required>
                        <option value="percentage">Percentage</option>
                        <option value="fixed">Fixed Amount</option>
                    </select>
                </div>
                <div class="form-field">
                    <input type="number" name="discounts[${rowCount}][value]" placeholder="Value" min="0" step="0.01" required>
                </div>
                <div class="form-field">
                    <select name="discounts[${rowCount}][condition]" required>
                        <option value="early_payment">Early Payment</option>
                        <option value="sibling">Sibling Discount</option>
                        <option value="scholarship">Scholarship</option>
                        <option value="staff_child">Staff Child</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <button type="button" class="btn-remove-row">Remove</button>
            </div>
        `;
        container.append(newRow);
    });
    
    // Form validation
    $('form').on('submit', function(e) {
        var form = $(this);
        var isValid = true;
        
        // Validate installment percentages total 100%
        var installmentContainer = form.find('.installments-container');
        if (installmentContainer.length > 0) {
            var totalPercentage = 0;
            installmentContainer.find('input[name*="[percentage]"]').each(function() {
                totalPercentage += parseFloat($(this).val()) || 0;
            });
            
            if (totalPercentage !== 100) {
                alert('Installment percentages must total 100%');
                isValid = false;
            }
        }
        
        if (!isValid) {
            e.preventDefault();
        }
    });
    
    // Category color picker
    $('.category-color-input').on('change', function() {
        var color = $(this).val();
        $(this).siblings('.category-color-preview').css('background-color', color);
    });
});
</script>