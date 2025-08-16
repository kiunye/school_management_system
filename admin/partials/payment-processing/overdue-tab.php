<?php
/**
 * Overdue Payments Tab
 *
 * Displays and manages overdue payments with penalty application
 * and bulk reminder functionality.
 *
 * @package    School_Management_System
 * @subpackage School_Management_System/admin/partials/payment-processing
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Get overdue invoices
$overdue_invoices = get_posts([
    'post_type' => 'sms_invoices',
    'meta_query' => [
        'relation' => 'AND',
        [
            'key' => 'due_date',
            'value' => current_time('Y-m-d'),
            'compare' => '<',
            'type' => 'DATE'
        ],
        [
            'key' => 'balance_due',
            'value' => 0,
            'compare' => '>',
            'type' => 'NUMERIC'
        ]
    ],
    'posts_per_page' => -1,
    'orderby' => 'meta_value',
    'meta_key' => 'due_date',
    'order' => 'ASC'
]);

// Calculate overdue statistics
$total_overdue_amount = 0;
$overdue_by_days = [
    '1-7' => ['count' => 0, 'amount' => 0],
    '8-30' => ['count' => 0, 'amount' => 0],
    '31-60' => ['count' => 0, 'amount' => 0],
    '60+' => ['count' => 0, 'amount' => 0]
];

foreach ($overdue_invoices as $invoice) {
    $balance_due = floatval(get_field('balance_due', $invoice->ID));
    $due_date = get_field('due_date', $invoice->ID);
    $days_overdue = ceil((current_time('timestamp') - strtotime($due_date)) / DAY_IN_SECONDS);
    
    $total_overdue_amount += $balance_due;
    
    if ($days_overdue <= 7) {
        $overdue_by_days['1-7']['count']++;
        $overdue_by_days['1-7']['amount'] += $balance_due;
    } elseif ($days_overdue <= 30) {
        $overdue_by_days['8-30']['count']++;
        $overdue_by_days['8-30']['amount'] += $balance_due;
    } elseif ($days_overdue <= 60) {
        $overdue_by_days['31-60']['count']++;
        $overdue_by_days['31-60']['amount'] += $balance_due;
    } else {
        $overdue_by_days['60+']['count']++;
        $overdue_by_days['60+']['amount'] += $balance_due;
    }
}

// Handle search and filters
$search_term = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
$days_filter = isset($_GET['days_filter']) ? sanitize_text_field($_GET['days_filter']) : '';
$amount_filter = isset($_GET['amount_filter']) ? sanitize_text_field($_GET['amount_filter']) : '';

// Filter invoices based on search criteria
$filtered_invoices = $overdue_invoices;

if ($search_term) {
    $filtered_invoices = array_filter($filtered_invoices, function($invoice) use ($search_term) {
        $student_id = get_field('student', $invoice->ID);
        $student_name = get_field('full_name', $student_id);
        $admission_number = get_field('admission_number', $student_id);
        $invoice_number = get_field('invoice_number', $invoice->ID);
        
        return (stripos($student_name, $search_term) !== false ||
                stripos($admission_number, $search_term) !== false ||
                stripos($invoice_number, $search_term) !== false);
    });
}

if ($days_filter) {
    $filtered_invoices = array_filter($filtered_invoices, function($invoice) use ($days_filter) {
        $due_date = get_field('due_date', $invoice->ID);
        $days_overdue = ceil((current_time('timestamp') - strtotime($due_date)) / DAY_IN_SECONDS);
        
        switch ($days_filter) {
            case '1-7':
                return $days_overdue >= 1 && $days_overdue <= 7;
            case '8-30':
                return $days_overdue >= 8 && $days_overdue <= 30;
            case '31-60':
                return $days_overdue >= 31 && $days_overdue <= 60;
            case '60+':
                return $days_overdue > 60;
            default:
                return true;
        }
    });
}

if ($amount_filter) {
    $filtered_invoices = array_filter($filtered_invoices, function($invoice) use ($amount_filter) {
        $balance_due = floatval(get_field('balance_due', $invoice->ID));
        
        switch ($amount_filter) {
            case 'under_1000':
                return $balance_due < 1000;
            case '1000_5000':
                return $balance_due >= 1000 && $balance_due < 5000;
            case '5000_10000':
                return $balance_due >= 5000 && $balance_due < 10000;
            case 'over_10000':
                return $balance_due >= 10000;
            default:
                return true;
        }
    });
}
?>

<div class="overdue-payments-tab">
    <!-- Overdue Statistics -->
    <div class="overdue-stats-section">
        <h3>Overdue Payment Statistics</h3>
        <div class="payment-stats-grid">
            <div class="stat-card">
                <h4>Total Overdue</h4>
                <div class="stat-value"><?php echo count($overdue_invoices); ?></div>
                <div class="stat-change">KES <?php echo number_format($total_overdue_amount, 2); ?></div>
            </div>
            
            <?php foreach ($overdue_by_days as $range => $data): ?>
            <div class="stat-card">
                <h4><?php echo $range; ?> Days</h4>
                <div class="stat-value"><?php echo $data['count']; ?></div>
                <div class="stat-change">KES <?php echo number_format($data['amount'], 2); ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Search and Filters -->
    <div class="search-filters">
        <form method="get" action="">
            <input type="hidden" name="page" value="sms-payment-processing">
            <input type="hidden" name="tab" value="overdue">
            
            <div class="filter-row">
                <div class="filter-group">
                    <label for="search">Search</label>
                    <input type="text" id="search" name="search" value="<?php echo esc_attr($search_term); ?>" 
                           placeholder="Student name, admission number, or invoice number">
                </div>
                
                <div class="filter-group">
                    <label for="days_filter">Days Overdue</label>
                    <select id="days_filter" name="days_filter">
                        <option value="">All</option>
                        <option value="1-7" <?php selected($days_filter, '1-7'); ?>>1-7 Days</option>
                        <option value="8-30" <?php selected($days_filter, '8-30'); ?>>8-30 Days</option>
                        <option value="31-60" <?php selected($days_filter, '31-60'); ?>>31-60 Days</option>
                        <option value="60+" <?php selected($days_filter, '60+'); ?>>60+ Days</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="amount_filter">Amount Range</label>
                    <select id="amount_filter" name="amount_filter">
                        <option value="">All</option>
                        <option value="under_1000" <?php selected($amount_filter, 'under_1000'); ?>>Under KES 1,000</option>
                        <option value="1000_5000" <?php selected($amount_filter, '1000_5000'); ?>>KES 1,000 - 5,000</option>
                        <option value="5000_10000" <?php selected($amount_filter, '5000_10000'); ?>>KES 5,000 - 10,000</option>
                        <option value="over_10000" <?php selected($amount_filter, 'over_10000'); ?>>Over KES 10,000</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="?page=sms-payment-processing&tab=overdue" class="btn btn-secondary">Clear</a>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Bulk Actions -->
    <?php if (!empty($filtered_invoices)): ?>
    <form method="post" id="overdue-payments-form">
        <?php wp_nonce_field('bulk_overdue_actions'); ?>
        
        <div class="tablenav top">
            <div class="alignleft actions bulkactions">
                <select name="action" id="bulk-action-selector-top">
                    <option value="-1">Bulk Actions</option>
                    <option value="send_reminder">Send Reminder</option>
                    <option value="apply_penalties">Apply Penalties</option>
                    <option value="mark_contacted">Mark as Contacted</option>
                    <option value="export_selected">Export Selected</option>
                </select>
                <input type="submit" class="btn btn-secondary" value="Apply">
            </div>
            
            <div class="alignright actions">
                <button type="button" class="btn btn-primary" onclick="processAllOverdue()">
                    Process All Overdue
                </button>
            </div>
        </div>
        
        <!-- Overdue Invoices Table -->
        <table class="payment-table">
            <thead>
                <tr>
                    <td class="check-column">
                        <input type="checkbox" id="cb-select-all-1">
                    </td>
                    <th>Invoice</th>
                    <th>Student</th>
                    <th>Due Date</th>
                    <th>Days Overdue</th>
                    <th>Amount Due</th>
                    <th>Penalties</th>
                    <th>Last Contact</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($filtered_invoices as $invoice): 
                    $student_id = get_field('student', $invoice->ID);
                    $student_name = get_field('full_name', $student_id);
                    $admission_number = get_field('admission_number', $student_id);
                    $invoice_number = get_field('invoice_number', $invoice->ID);
                    $due_date = get_field('due_date', $invoice->ID);
                    $balance_due = floatval(get_field('balance_due', $invoice->ID));
                    $penalties = floatval(get_field('penalties', $invoice->ID));
                    $days_overdue = ceil((current_time('timestamp') - strtotime($due_date)) / DAY_IN_SECONDS);
                    
                    // Get last reminder sent
                    $reminder_history = get_field('reminder_history', $invoice->ID) ?? [];
                    $last_reminder = null;
                    if (!empty($reminder_history)) {
                        $last_reminder = end($reminder_history);
                    }
                ?>
                <tr>
                    <th class="check-column">
                        <input type="checkbox" name="invoice_ids[]" value="<?php echo $invoice->ID; ?>">
                    </th>
                    <td>
                        <strong>
                            <a href="<?php echo admin_url('post.php?post=' . $invoice->ID . '&action=edit'); ?>">
                                #<?php echo esc_html($invoice_number); ?>
                            </a>
                        </strong>
                    </td>
                    <td>
                        <strong><?php echo esc_html($student_name); ?></strong><br>
                        <small><?php echo esc_html($admission_number); ?></small>
                    </td>
                    <td>
                        <?php echo date('M j, Y', strtotime($due_date)); ?>
                    </td>
                    <td>
                        <span class="days-overdue days-<?php echo $days_overdue > 60 ? 'critical' : ($days_overdue > 30 ? 'warning' : 'normal'); ?>">
                            <?php echo $days_overdue; ?> days
                        </span>
                    </td>
                    <td>
                        <strong>KES <?php echo number_format($balance_due, 2); ?></strong>
                    </td>
                    <td>
                        <?php if ($penalties > 0): ?>
                        <span class="penalty-amount">KES <?php echo number_format($penalties, 2); ?></span>
                        <?php else: ?>
                        <span class="no-penalty">None</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($last_reminder): ?>
                        <small>
                            <?php echo ucfirst($last_reminder['type']); ?> reminder<br>
                            <?php echo date('M j', strtotime($last_reminder['sent_date'])); ?>
                        </small>
                        <?php else: ?>
                        <small class="no-contact">No contact</small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="row-actions">
                            <button type="button" class="btn-link" onclick="sendReminder(<?php echo $invoice->ID; ?>)">
                                Send Reminder
                            </button> |
                            <button type="button" class="btn-link" onclick="applyPenalty(<?php echo $invoice->ID; ?>)">
                                Apply Penalty
                            </button> |
                            <a href="<?php echo admin_url('post.php?post=' . $invoice->ID . '&action=edit'); ?>">
                                View
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="tablenav bottom">
            <div class="alignleft actions bulkactions">
                <select name="action2" id="bulk-action-selector-bottom">
                    <option value="-1">Bulk Actions</option>
                    <option value="send_reminder">Send Reminder</option>
                    <option value="apply_penalties">Apply Penalties</option>
                    <option value="mark_contacted">Mark as Contacted</option>
                    <option value="export_selected">Export Selected</option>
                </select>
                <input type="submit" class="btn btn-secondary" value="Apply">
            </div>
        </div>
    </form>
    
    <?php else: ?>
    <div class="no-overdue-payments">
        <h3>No Overdue Payments Found</h3>
        <p>
            <?php if ($search_term || $days_filter || $amount_filter): ?>
            No overdue payments match your current filters. 
            <a href="?page=sms-payment-processing&tab=overdue">Clear filters</a> to see all overdue payments.
            <?php else: ?>
            Great! There are currently no overdue payments in the system.
            <?php endif; ?>
        </p>
    </div>
    <?php endif; ?>
    
    <!-- Export Section -->
    <div class="export-section">
        <h4>Export Overdue Payments</h4>
        <div class="export-options">
            <button type="button" class="btn btn-secondary export-btn" data-format="csv">
                Export to CSV
            </button>
            <button type="button" class="btn btn-secondary export-btn" data-format="excel">
                Export to Excel
            </button>
            <button type="button" class="btn btn-secondary export-btn" data-format="pdf">
                Export to PDF
            </button>
        </div>
    </div>
</div>

<style>
.overdue-payments-tab {
    margin: 20px 0;
}

.overdue-stats-section {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin: 20px 0;
}

.days-overdue {
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 600;
}

.days-normal {
    background-color: #ffb900;
    color: white;
}

.days-warning {
    background-color: #ff8c00;
    color: white;
}

.days-critical {
    background-color: #dc3232;
    color: white;
}

.penalty-amount {
    color: #dc3232;
    font-weight: 600;
}

.no-penalty {
    color: #666;
    font-style: italic;
}

.no-contact {
    color: #dc3232;
    font-style: italic;
}

.row-actions {
    font-size: 12px;
}

.btn-link {
    background: none;
    border: none;
    color: #0073aa;
    text-decoration: underline;
    cursor: pointer;
    padding: 0;
    font-size: 12px;
}

.btn-link:hover {
    color: #005a87;
}

.no-overdue-payments {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 40px;
    text-align: center;
    margin: 20px 0;
}

.no-overdue-payments h3 {
    color: #46b450;
    margin-bottom: 10px;
}

.tablenav {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin: 10px 0;
    padding: 10px 0;
}

.check-column {
    width: 40px;
    text-align: center;
}

@media (max-width: 768px) {
    .tablenav {
        flex-direction: column;
        gap: 10px;
        align-items: stretch;
    }
    
    .payment-table {
        font-size: 12px;
    }
    
    .payment-table th,
    .payment-table td {
        padding: 8px 4px;
    }
    
    .row-actions {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Handle select all checkbox
    $('#cb-select-all-1').change(function() {
        $('input[name="invoice_ids[]"]').prop('checked', this.checked);
    });
    
    // Update select all when individual checkboxes change
    $('input[name="invoice_ids[]"]').change(function() {
        var total = $('input[name="invoice_ids[]"]').length;
        var checked = $('input[name="invoice_ids[]"]:checked').length;
        $('#cb-select-all-1').prop('checked', total === checked);
    });
    
    // Handle bulk actions
    $('#overdue-payments-form').submit(function(e) {
        var action = $('select[name="action"]').val() || $('select[name="action2"]').val();
        var checkedCount = $('input[name="invoice_ids[]"]:checked').length;
        
        if (action === '-1') {
            alert('Please select an action.');
            e.preventDefault();
            return false;
        }
        
        if (checkedCount === 0) {
            alert('Please select at least one invoice.');
            e.preventDefault();
            return false;
        }
        
        var confirmMessage = '';
        switch (action) {
            case 'send_reminder':
                confirmMessage = 'Send payment reminders for ' + checkedCount + ' invoice(s)?';
                break;
            case 'apply_penalties':
                confirmMessage = 'Apply penalties to ' + checkedCount + ' overdue invoice(s)? This action cannot be undone.';
                break;
            case 'mark_contacted':
                confirmMessage = 'Mark ' + checkedCount + ' invoice(s) as contacted?';
                break;
            case 'export_selected':
                confirmMessage = 'Export ' + checkedCount + ' selected invoice(s)?';
                break;
        }
        
        if (confirmMessage && !confirm(confirmMessage)) {
            e.preventDefault();
            return false;
        }
    });
});

function sendReminder(invoiceId) {
    if (confirm('Send payment reminder for this invoice?')) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="send_manual_reminder">
            <input type="hidden" name="invoice_ids[]" value="${invoiceId}">
            <input type="hidden" name="reminder_type" value="overdue">
            <input type="hidden" name="_wpnonce" value="<?php echo wp_create_nonce('send_manual_reminders'); ?>">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function applyPenalty(invoiceId) {
    if (confirm('Apply penalty to this overdue invoice? This action cannot be undone.')) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="apply_penalty">
            <input type="hidden" name="invoice_id" value="${invoiceId}">
            <input type="hidden" name="_wpnonce" value="<?php echo wp_create_nonce('apply_penalty'); ?>">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function processAllOverdue() {
    if (confirm('Process all overdue payments and apply penalties where applicable? This action cannot be undone.')) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="process_overdue_payments">
            <input type="hidden" name="_wpnonce" value="<?php echo wp_create_nonce('process_overdue_payments'); ?>">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>