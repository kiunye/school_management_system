<?php
/**
 * Payment Processing and Tracking Admin Interface
 *
 * Provides admin interface for managing payment processing,
 * viewing payment history, and configuring reminder settings.
 *
 * @package    School_Management_System
 * @subpackage School_Management_System/admin/partials
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Get current tab
$current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'overview';

// Initialize classes
$payment_processor = new SMS_Payment_Processor();
$history_tracker = new SMS_Payment_History_Tracker();
$reminder_scheduler = new SMS_Payment_Reminder_Scheduler();

// Handle form submissions
if ($_POST) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_reminder_settings':
                if (wp_verify_nonce($_POST['_wpnonce'], 'update_reminder_settings')) {
                    $settings = [
                        'enabled' => isset($_POST['reminder_enabled']),
                        'upcoming_days' => intval($_POST['upcoming_days']),
                        'overdue_intervals' => array_map('intval', explode(',', $_POST['overdue_intervals'])),
                        'final_notice_days' => intval($_POST['final_notice_days']),
                        'enabled_methods' => $_POST['enabled_methods'] ?? [],
                        'business_hours_only' => isset($_POST['business_hours_only']),
                        'weekend_reminders' => isset($_POST['weekend_reminders'])
                    ];
                    
                    $result = $reminder_scheduler->update_reminder_settings($settings);
                    if (is_wp_error($result)) {
                        echo '<div class="notice notice-error"><p>' . $result->get_error_message() . '</p></div>';
                    } else {
                        echo '<div class="notice notice-success"><p>Reminder settings updated successfully.</p></div>';
                    }
                }
                break;
                
            case 'process_overdue_payments':
                if (wp_verify_nonce($_POST['_wpnonce'], 'process_overdue_payments')) {
                    $result = $payment_processor->apply_overdue_penalties();
                    echo '<div class="notice notice-success"><p>Processed ' . $result['processed_count'] . ' overdue payments with total penalties of KES ' . number_format($result['total_penalties'], 2) . '</p></div>';
                }
                break;
                
            case 'send_manual_reminders':
                if (wp_verify_nonce($_POST['_wpnonce'], 'send_manual_reminders')) {
                    $invoice_ids = array_map('intval', $_POST['invoice_ids']);
                    $reminder_type = sanitize_text_field($_POST['reminder_type']);
                    $sent_count = 0;
                    
                    foreach ($invoice_ids as $invoice_id) {
                        $result = $reminder_scheduler->send_manual_reminder($invoice_id, $reminder_type);
                        if ($result['success']) {
                            $sent_count++;
                        }
                    }
                    
                    echo '<div class="notice notice-success"><p>Sent ' . $sent_count . ' manual reminders.</p></div>';
                }
                break;
        }
    }
}

// Get dashboard data
$overdue_invoices = $payment_processor->identify_overdue_payments();
$reminder_stats = $reminder_scheduler->get_reminder_statistics();
$payment_summary = $history_tracker->generate_payment_analytics([
    'date_from' => date('Y-m-01'),
    'date_to' => date('Y-m-d')
]);
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <!-- Navigation Tabs -->
    <nav class="nav-tab-wrapper">
        <a href="?page=sms-payment-processing&tab=overview" class="nav-tab <?php echo $current_tab === 'overview' ? 'nav-tab-active' : ''; ?>">
            Overview
        </a>
        <a href="?page=sms-payment-processing&tab=overdue" class="nav-tab <?php echo $current_tab === 'overdue' ? 'nav-tab-active' : ''; ?>">
            Overdue Payments
        </a>
        <a href="?page=sms-payment-processing&tab=reminders" class="nav-tab <?php echo $current_tab === 'reminders' ? 'nav-tab-active' : ''; ?>">
            Payment Reminders
        </a>
        <a href="?page=sms-payment-processing&tab=history" class="nav-tab <?php echo $current_tab === 'history' ? 'nav-tab-active' : ''; ?>">
            Payment History
        </a>
        <a href="?page=sms-payment-processing&tab=settings" class="nav-tab <?php echo $current_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
            Settings
        </a>
    </nav>
    
    <div class="tab-content">
        <?php
        switch ($current_tab) {
            case 'overview':
                include 'payment-processing/overview-tab.php';
                break;
            case 'overdue':
                include 'payment-processing/overdue-tab.php';
                break;
            case 'reminders':
                include 'payment-processing/reminders-tab.php';
                break;
            case 'history':
                include 'payment-processing/history-tab.php';
                break;
            case 'settings':
                include 'payment-processing/settings-tab.php';
                break;
            default:
                include 'payment-processing/overview-tab.php';
        }
        ?>
    </div>
</div>

<style>
.payment-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.stat-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    text-align: center;
}

.stat-card h3 {
    margin: 0 0 10px 0;
    color: #23282d;
    font-size: 14px;
    font-weight: 600;
    text-transform: uppercase;
}

.stat-card .stat-value {
    font-size: 32px;
    font-weight: bold;
    color: #0073aa;
    margin: 10px 0;
}

.stat-card .stat-change {
    font-size: 12px;
    color: #666;
}

.stat-change.positive {
    color: #46b450;
}

.stat-change.negative {
    color: #dc3232;
}

.payment-table {
    width: 100%;
    border-collapse: collapse;
    margin: 20px 0;
}

.payment-table th,
.payment-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #ddd;
}

.payment-table th {
    background-color: #f9f9f9;
    font-weight: 600;
}

.payment-table tr:hover {
    background-color: #f5f5f5;
}

.status-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}

.status-paid {
    background-color: #46b450;
    color: white;
}

.status-pending {
    background-color: #ffb900;
    color: white;
}

.status-overdue {
    background-color: #dc3232;
    color: white;
}

.status-partial {
    background-color: #00a0d2;
    color: white;
}

.reminder-form {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin: 20px 0;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
}

.form-section {
    margin-bottom: 20px;
}

.form-section h4 {
    margin: 0 0 15px 0;
    color: #23282d;
    border-bottom: 1px solid #ddd;
    padding-bottom: 5px;
}

.checkbox-group {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
}

.checkbox-group label {
    display: flex;
    align-items: center;
    gap: 5px;
}

.action-buttons {
    margin: 20px 0;
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.btn {
    display: inline-block;
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    text-decoration: none;
    font-size: 14px;
    cursor: pointer;
    transition: background-color 0.3s;
}

.btn-primary {
    background-color: #0073aa;
    color: white;
}

.btn-primary:hover {
    background-color: #005a87;
}

.btn-secondary {
    background-color: #666;
    color: white;
}

.btn-secondary:hover {
    background-color: #555;
}

.btn-danger {
    background-color: #dc3232;
    color: white;
}

.btn-danger:hover {
    background-color: #c62d2d;
}

.search-filters {
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 15px;
    margin: 20px 0;
}

.filter-row {
    display: flex;
    gap: 15px;
    align-items: end;
    flex-wrap: wrap;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.filter-group label {
    font-weight: 600;
    font-size: 12px;
    color: #666;
}

.export-section {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin: 20px 0;
}

.export-options {
    display: flex;
    gap: 15px;
    align-items: center;
    flex-wrap: wrap;
}

@media (max-width: 768px) {
    .payment-stats-grid {
        grid-template-columns: 1fr;
    }
    
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .filter-row {
        flex-direction: column;
        align-items: stretch;
    }
    
    .action-buttons {
        flex-direction: column;
    }
    
    .export-options {
        flex-direction: column;
        align-items: stretch;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Handle bulk actions
    $('#bulk-action-selector-top, #bulk-action-selector-bottom').change(function() {
        var action = $(this).val();
        var form = $(this).closest('form');
        
        if (action === 'send_reminder') {
            var reminderType = prompt('Enter reminder type (upcoming, due, overdue, final):');
            if (reminderType) {
                $('<input>').attr({
                    type: 'hidden',
                    name: 'reminder_type',
                    value: reminderType
                }).appendTo(form);
            }
        }
    });
    
    // Handle export functionality
    $('.export-btn').click(function(e) {
        e.preventDefault();
        var format = $(this).data('format');
        var filters = {};
        
        // Collect filter values
        $('.search-filters input, .search-filters select').each(function() {
            if ($(this).val()) {
                filters[$(this).attr('name')] = $(this).val();
            }
        });
        
        // Create export form
        var form = $('<form>', {
            method: 'POST',
            action: ajaxurl
        });
        
        form.append($('<input>', {
            type: 'hidden',
            name: 'action',
            value: 'sms_export_payment_history'
        }));
        
        form.append($('<input>', {
            type: 'hidden',
            name: 'format',
            value: format
        }));
        
        form.append($('<input>', {
            type: 'hidden',
            name: 'filters',
            value: JSON.stringify(filters)
        }));
        
        form.append($('<input>', {
            type: 'hidden',
            name: '_wpnonce',
            value: '<?php echo wp_create_nonce('export_payment_history'); ?>'
        }));
        
        $('body').append(form);
        form.submit();
        form.remove();
    });
    
    // Auto-refresh overdue payments
    if (window.location.href.indexOf('tab=overdue') > -1) {
        setInterval(function() {
            location.reload();
        }, 300000); // Refresh every 5 minutes
    }
    
    // Confirmation for bulk actions
    $('form').submit(function(e) {
        var action = $('select[name="action"]').val() || $('select[name="action2"]').val();
        
        if (action === 'process_overdue_payments') {
            if (!confirm('Are you sure you want to process overdue payments and apply penalties? This action cannot be undone.')) {
                e.preventDefault();
                return false;
            }
        }
        
        if (action === 'send_reminder') {
            var checkedCount = $('input[name="invoice_ids[]"]:checked').length;
            if (checkedCount === 0) {
                alert('Please select at least one invoice to send reminders.');
                e.preventDefault();
                return false;
            }
            
            if (!confirm('Are you sure you want to send reminders for ' + checkedCount + ' invoice(s)?')) {
                e.preventDefault();
                return false;
            }
        }
    });
    
    // Real-time search
    $('#payment-search').on('input', function() {
        var searchTerm = $(this).val().toLowerCase();
        $('.payment-table tbody tr').each(function() {
            var text = $(this).text().toLowerCase();
            $(this).toggle(text.indexOf(searchTerm) > -1);
        });
    });
    
    // Toggle advanced filters
    $('#toggle-advanced-filters').click(function(e) {
        e.preventDefault();
        $('.advanced-filters').slideToggle();
        $(this).text($(this).text() === 'Show Advanced Filters' ? 'Hide Advanced Filters' : 'Show Advanced Filters');
    });
});
</script>