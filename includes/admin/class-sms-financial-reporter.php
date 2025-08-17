<?php
/**
 * Financial Reporting Module
 *
 * Handles income, expense, cash flow reports with visualizations,
 * fee collection status, outstanding amounts, invoice tracking,
 * payment trend analysis, and payment gateway transaction reports.
 *
 * @package    School_Management_System
 * @subpackage School_Management_System/includes/admin
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Financial Reporter Class
 */
class SMS_Financial_Reporter extends SMS_Base {

    /**
     * Report cache duration in seconds
     */
    const CACHE_DURATION = 3600; // 1 hour

    /**
     * Initialize the class and set its properties.
     */
    public function __construct() {
        parent::__construct();
        
        // Hook into report generation
        add_action('sms_generate_financial_report', [$this, 'generate_report'], 10, 3);
        add_action('sms_export_financial_report', [$this, 'export_report'], 10, 4);
        
        // Scheduled report generation
        add_action('sms_generate_scheduled_reports', [$this, 'generate_scheduled_reports']);
        
        // Clear cache when financial data changes
        add_action('sms_transaction_completed', [$this, 'clear_report_cache']);
        add_action('sms_invoice_status_changed', [$this, 'clear_report_cache']);
    }

    /**
     * Generate comprehensive financial report.
     *
     * @param string $report_type Report type (income, expense, cash_flow, collection_status, etc.)
     * @param array  $date_range  Date range for the report
     * @param array  $filters     Additional filters
     * @return array|WP_Error Report data or error
     */
    public function generate_report($report_type, $date_range, $filters = []) {
        try {
            // Validate inputs
            $validation = $this->validate_report_parameters($report_type, $date_range, $filters);
            if (is_wp_error($validation)) {
                return $validation;
            }

            // Check cache first
            $cache_key = $this->get_cache_key($report_type, $date_range, $filters);
            $cached_report = get_transient($cache_key);
            if ($cached_report !== false && empty($filters['bypass_cache'])) {
                return $cached_report;
            }

            // Generate report based on type
            switch ($report_type) {
                case 'income':
                    $report_data = $this->generate_income_report($date_range, $filters);
                    break;
                    
                case 'expense':
                    $report_data = $this->generate_expense_report($date_range, $filters);
                    break;
                    
                case 'cash_flow':
                    $report_data = $this->generate_cash_flow_report($date_range, $filters);
                    break;
                    
                case 'collection_status':
                    $report_data = $this->generate_collection_status_report($date_range, $filters);
                    break;
                    
                case 'outstanding_amounts':
                    $report_data = $this->generate_outstanding_amounts_report($date_range, $filters);
                    break;
                    
                case 'invoice_tracking':
                    $report_data = $this->generate_invoice_tracking_report($date_range, $filters);
                    break;
                    
                case 'payment_trends':
                    $report_data = $this->generate_payment_trends_report($date_range, $filters);
                    break;
                    
                case 'gateway_transactions':
                    $report_data = $this->generate_gateway_transactions_report($date_range, $filters);
                    break;
                    
                case 'reconciliation':
                    $report_data = $this->generate_reconciliation_report($date_range, $filters);
                    break;
                    
                default:
                    return new WP_Error('invalid_report_type', __('Invalid report type specified.', 'school-management-system'));
            }

            if (is_wp_error($report_data)) {
                return $report_data;
            }

            // Add metadata
            $report_data['metadata'] = [
                'report_type' => $report_type,
                'date_range' => $date_range,
                'filters' => $filters,
                'generated_at' => current_time('mysql'),
                'generated_by' => get_current_user_id(),
                'cache_key' => $cache_key
            ];

            // Cache the report
            set_transient($cache_key, $report_data, self::CACHE_DURATION);

            // Log report generation
            $this->log_activity(
                get_current_user_id(),
                'financial_report_generated',
                'report',
                0,
                [
                    'report_type' => $report_type,
                    'date_range' => $date_range
                ]
            );

            return $report_data;

        } catch (Exception $e) {
            return new WP_Error('report_generation_failed', $e->getMessage());
        }
    }

    /**
     * Generate income report with visualizations.
     *
     * @param array $date_range Date range
     * @param array $filters    Filters
     * @return array Income report data
     */
    private function generate_income_report($date_range, $filters = []) {
        $report = [
            'summary' => [],
            'breakdown' => [],
            'trends' => [],
            'visualizations' => []
        ];

        // Get income transactions
        $income_transactions = $this->get_income_transactions($date_range, $filters);
        
        // Calculate summary metrics
        $report['summary'] = [
            'total_income' => array_sum(array_column($income_transactions, 'amount')),
            'transaction_count' => count($income_transactions),
            'average_transaction' => count($income_transactions) > 0 ? 
                array_sum(array_column($income_transactions, 'amount')) / count($income_transactions) : 0,
            'period_start' => $date_range['start'],
            'period_end' => $date_range['end']
        ];

        // Breakdown by fee type
        $report['breakdown']['by_fee_type'] = $this->group_transactions_by_fee_type($income_transactions);
        
        // Breakdown by payment method
        $report['breakdown']['by_payment_method'] = $this->group_transactions_by_payment_method($income_transactions);
        
        // Breakdown by class/grade
        $report['breakdown']['by_class'] = $this->group_transactions_by_class($income_transactions);
        
        // Monthly trends
        $report['trends']['monthly'] = $this->calculate_monthly_trends($income_transactions, $date_range);
        
        // Daily trends for shorter periods
        if ($this->is_short_period($date_range)) {
            $report['trends']['daily'] = $this->calculate_daily_trends($income_transactions, $date_range);
        }

        // Generate visualization data
        $report['visualizations'] = [
            'fee_type_pie_chart' => $this->prepare_pie_chart_data($report['breakdown']['by_fee_type']),
            'payment_method_bar_chart' => $this->prepare_bar_chart_data($report['breakdown']['by_payment_method']),
            'monthly_trend_line_chart' => $this->prepare_line_chart_data($report['trends']['monthly'])
        ];

        return $report;
    }

    /**
     * Generate expense report.
     *
     * @param array $date_range Date range
     * @param array $filters    Filters
     * @return array Expense report data
     */
    private function generate_expense_report($date_range, $filters = []) {
        $report = [
            'summary' => [],
            'breakdown' => [],
            'trends' => [],
            'visualizations' => []
        ];

        // Get expense transactions
        $expense_transactions = $this->get_expense_transactions($date_range, $filters);
        
        // Calculate summary metrics
        $report['summary'] = [
            'total_expenses' => array_sum(array_column($expense_transactions, 'amount')),
            'transaction_count' => count($expense_transactions),
            'average_expense' => count($expense_transactions) > 0 ? 
                array_sum(array_column($expense_transactions, 'amount')) / count($expense_transactions) : 0,
            'period_start' => $date_range['start'],
            'period_end' => $date_range['end']
        ];

        // Breakdown by expense category
        $report['breakdown']['by_category'] = $this->group_expenses_by_category($expense_transactions);
        
        // Monthly trends
        $report['trends']['monthly'] = $this->calculate_monthly_trends($expense_transactions, $date_range);

        // Generate visualization data
        $report['visualizations'] = [
            'category_pie_chart' => $this->prepare_pie_chart_data($report['breakdown']['by_category']),
            'monthly_trend_line_chart' => $this->prepare_line_chart_data($report['trends']['monthly'])
        ];

        return $report;
    }

    /**
     * Generate cash flow report.
     *
     * @param array $date_range Date range
     * @param array $filters    Filters
     * @return array Cash flow report data
     */
    private function generate_cash_flow_report($date_range, $filters = []) {
        // Get income and expense data
        $income_data = $this->generate_income_report($date_range, $filters);
        $expense_data = $this->generate_expense_report($date_range, $filters);

        $report = [
            'summary' => [
                'total_income' => $income_data['summary']['total_income'],
                'total_expenses' => $expense_data['summary']['total_expenses'],
                'net_cash_flow' => $income_data['summary']['total_income'] - $expense_data['summary']['total_expenses'],
                'period_start' => $date_range['start'],
                'period_end' => $date_range['end']
            ],
            'monthly_cash_flow' => [],
            'visualizations' => []
        ];

        // Calculate monthly cash flow
        $income_monthly = $income_data['trends']['monthly'];
        $expense_monthly = $expense_data['trends']['monthly'];
        
        foreach ($income_monthly as $month => $income_amount) {
            $expense_amount = $expense_monthly[$month] ?? 0;
            $report['monthly_cash_flow'][$month] = [
                'income' => $income_amount,
                'expenses' => $expense_amount,
                'net_flow' => $income_amount - $expense_amount
            ];
        }

        // Generate visualization data
        $report['visualizations'] = [
            'cash_flow_chart' => $this->prepare_cash_flow_chart_data($report['monthly_cash_flow'])
        ];

        return $report;
    }

    /**
     * Generate fee collection status report.
     *
     * @param array $date_range Date range
     * @param array $filters    Filters
     * @return array Collection status report data
     */
    private function generate_collection_status_report($date_range, $filters = []) {
        $report = [
            'summary' => [],
            'by_fee_type' => [],
            'by_class' => [],
            'collection_efficiency' => [],
            'visualizations' => []
        ];

        // Get all invoices in the period
        $invoices = $this->get_invoices_in_period($date_range, $filters);
        
        $total_invoiced = 0;
        $total_collected = 0;
        $total_outstanding = 0;
        
        foreach ($invoices as $invoice) {
            $total_invoiced += $invoice['total_amount'];
            $total_collected += $invoice['amount_paid'];
            $total_outstanding += $invoice['balance_due'];
        }

        $report['summary'] = [
            'total_invoiced' => $total_invoiced,
            'total_collected' => $total_collected,
            'total_outstanding' => $total_outstanding,
            'collection_rate' => $total_invoiced > 0 ? ($total_collected / $total_invoiced) * 100 : 0,
            'invoice_count' => count($invoices),
            'paid_invoices' => count(array_filter($invoices, function($inv) { return $inv['balance_due'] == 0; })),
            'partial_paid_invoices' => count(array_filter($invoices, function($inv) { 
                return $inv['amount_paid'] > 0 && $inv['balance_due'] > 0; 
            })),
            'unpaid_invoices' => count(array_filter($invoices, function($inv) { return $inv['amount_paid'] == 0; }))
        ];

        // Collection status by fee type
        $report['by_fee_type'] = $this->group_collection_by_fee_type($invoices);
        
        // Collection status by class
        $report['by_class'] = $this->group_collection_by_class($invoices);
        
        // Collection efficiency over time
        $report['collection_efficiency'] = $this->calculate_collection_efficiency_trends($date_range, $filters);

        // Generate visualizations
        $report['visualizations'] = [
            'collection_status_pie' => $this->prepare_collection_status_pie_data($report['summary']),
            'fee_type_collection_bar' => $this->prepare_bar_chart_data($report['by_fee_type']),
            'collection_efficiency_line' => $this->prepare_line_chart_data($report['collection_efficiency'])
        ];

        return $report;
    }

    /**
     * Generate outstanding amounts report.
     *
     * @param array $date_range Date range
     * @param array $filters    Filters
     * @return array Outstanding amounts report data
     */
    private function generate_outstanding_amounts_report($date_range, $filters = []) {
        $report = [
            'summary' => [],
            'aging_analysis' => [],
            'by_student' => [],
            'by_fee_type' => [],
            'overdue_analysis' => [],
            'visualizations' => []
        ];

        // Get outstanding invoices
        $outstanding_invoices = $this->get_outstanding_invoices($date_range, $filters);
        
        $total_outstanding = array_sum(array_column($outstanding_invoices, 'balance_due'));
        
        $report['summary'] = [
            'total_outstanding' => $total_outstanding,
            'invoice_count' => count($outstanding_invoices),
            'average_outstanding' => count($outstanding_invoices) > 0 ? 
                $total_outstanding / count($outstanding_invoices) : 0
        ];

        // Aging analysis (0-30, 31-60, 61-90, 90+ days)
        $report['aging_analysis'] = $this->calculate_aging_analysis($outstanding_invoices);
        
        // Top outstanding amounts by student
        $report['by_student'] = $this->group_outstanding_by_student($outstanding_invoices);
        
        // Outstanding by fee type
        $report['by_fee_type'] = $this->group_outstanding_by_fee_type($outstanding_invoices);
        
        // Overdue analysis
        $report['overdue_analysis'] = $this->calculate_overdue_analysis($outstanding_invoices);

        // Generate visualizations
        $report['visualizations'] = [
            'aging_analysis_bar' => $this->prepare_bar_chart_data($report['aging_analysis']),
            'top_outstanding_students' => $this->prepare_bar_chart_data(array_slice($report['by_student'], 0, 10)),
            'fee_type_outstanding_pie' => $this->prepare_pie_chart_data($report['by_fee_type'])
        ];

        return $report;
    }

    /**
     * Generate invoice status tracking report.
     *
     * @param array $date_range Date range
     * @param array $filters    Filters
     * @return array Invoice tracking report data
     */
    private function generate_invoice_tracking_report($date_range, $filters = []) {
        $report = [
            'summary' => [],
            'status_breakdown' => [],
            'processing_times' => [],
            'trends' => [],
            'visualizations' => []
        ];

        // Get all invoices in period
        $invoices = $this->get_invoices_in_period($date_range, $filters);
        
        // Status breakdown
        $status_counts = [];
        foreach ($invoices as $invoice) {
            $status = $invoice['invoice_status'];
            $status_counts[$status] = ($status_counts[$status] ?? 0) + 1;
        }
        
        $report['summary'] = [
            'total_invoices' => count($invoices),
            'status_breakdown' => $status_counts
        ];

        // Processing time analysis
        $report['processing_times'] = $this->calculate_invoice_processing_times($invoices);
        
        // Invoice creation trends
        $report['trends'] = $this->calculate_invoice_creation_trends($invoices, $date_range);

        // Generate visualizations
        $report['visualizations'] = [
            'status_breakdown_pie' => $this->prepare_pie_chart_data($status_counts),
            'creation_trends_line' => $this->prepare_line_chart_data($report['trends'])
        ];

        return $report;
    }

    /**
     * Generate payment trends analysis report.
     *
     * @param array $date_range Date range
     * @param array $filters    Filters
     * @return array Payment trends report data
     */
    private function generate_payment_trends_report($date_range, $filters = []) {
        $report = [
            'summary' => [],
            'monthly_trends' => [],
            'payment_method_trends' => [],
            'seasonal_analysis' => [],
            'visualizations' => []
        ];

        // Get payment transactions
        $payments = $this->get_payment_transactions($date_range, $filters);
        
        // Monthly payment trends
        $report['monthly_trends'] = $this->calculate_monthly_payment_trends($payments, $date_range);
        
        // Payment method trends
        $report['payment_method_trends'] = $this->calculate_payment_method_trends($payments, $date_range);
        
        // Seasonal analysis (if data spans multiple years)
        if ($this->spans_multiple_years($date_range)) {
            $report['seasonal_analysis'] = $this->calculate_seasonal_payment_patterns($payments);
        }

        // Generate visualizations
        $report['visualizations'] = [
            'monthly_trends_line' => $this->prepare_line_chart_data($report['monthly_trends']),
            'payment_method_trends_stacked' => $this->prepare_stacked_chart_data($report['payment_method_trends'])
        ];

        return $report;
    }

    /**
     * Generate payment gateway transaction report.
     *
     * @param array $date_range Date range
     * @param array $filters    Filters
     * @return array Gateway transactions report data
     */
    private function generate_gateway_transactions_report($date_range, $filters = []) {
        $report = [
            'summary' => [],
            'by_gateway' => [],
            'success_rates' => [],
            'transaction_volumes' => [],
            'error_analysis' => [],
            'visualizations' => []
        ];

        // Get gateway transactions
        $transactions = $this->get_gateway_transactions($date_range, $filters);
        
        // Summary metrics
        $total_transactions = count($transactions);
        $successful_transactions = count(array_filter($transactions, function($t) { 
            return $t['transaction_status'] === 'completed'; 
        }));
        
        $report['summary'] = [
            'total_transactions' => $total_transactions,
            'successful_transactions' => $successful_transactions,
            'failed_transactions' => $total_transactions - $successful_transactions,
            'success_rate' => $total_transactions > 0 ? ($successful_transactions / $total_transactions) * 100 : 0,
            'total_amount' => array_sum(array_column($transactions, 'amount'))
        ];

        // Breakdown by gateway
        $report['by_gateway'] = $this->group_transactions_by_gateway($transactions);
        
        // Success rates by gateway
        $report['success_rates'] = $this->calculate_gateway_success_rates($transactions);
        
        // Transaction volumes over time
        $report['transaction_volumes'] = $this->calculate_gateway_volume_trends($transactions, $date_range);
        
        // Error analysis
        $report['error_analysis'] = $this->analyze_gateway_errors($transactions);

        // Generate visualizations
        $report['visualizations'] = [
            'gateway_breakdown_pie' => $this->prepare_pie_chart_data($report['by_gateway']),
            'success_rates_bar' => $this->prepare_bar_chart_data($report['success_rates']),
            'volume_trends_line' => $this->prepare_line_chart_data($report['transaction_volumes'])
        ];

        return $report;
    }

    /**
     * Generate reconciliation report for payment gateways.
     *
     * @param array $date_range Date range
     * @param array $filters    Filters
     * @return array Reconciliation report data
     */
    private function generate_reconciliation_report($date_range, $filters = []) {
        $report = [
            'summary' => [],
            'discrepancies' => [],
            'by_gateway' => [],
            'unmatched_transactions' => [],
            'recommendations' => []
        ];

        // Get system transactions and gateway records
        $system_transactions = $this->get_system_transactions($date_range, $filters);
        $gateway_records = $this->get_gateway_records($date_range, $filters);
        
        // Perform reconciliation
        $reconciliation_result = $this->perform_reconciliation($system_transactions, $gateway_records);
        
        $report['summary'] = [
            'system_transactions' => count($system_transactions),
            'gateway_records' => count($gateway_records),
            'matched_transactions' => $reconciliation_result['matched_count'],
            'discrepancies_found' => $reconciliation_result['discrepancy_count'],
            'reconciliation_rate' => $reconciliation_result['reconciliation_rate']
        ];

        $report['discrepancies'] = $reconciliation_result['discrepancies'];
        $report['unmatched_transactions'] = $reconciliation_result['unmatched'];
        
        // Generate recommendations
        $report['recommendations'] = $this->generate_reconciliation_recommendations($reconciliation_result);

        return $report;
    }

    /**
     * Export report in specified format.
     *
     * @param array  $report_data Report data
     * @param string $format      Export format (pdf, excel, csv)
     * @param string $filename    Output filename
     * @param array  $options     Export options
     * @return string|WP_Error File path or error
     */
    public function export_report($report_data, $format, $filename, $options = []) {
        try {
            switch ($format) {
                case 'pdf':
                    return $this->export_to_pdf($report_data, $filename, $options);
                    
                case 'excel':
                    return $this->export_to_excel($report_data, $filename, $options);
                    
                case 'csv':
                    return $this->export_to_csv($report_data, $filename, $options);
                    
                default:
                    return new WP_Error('invalid_format', __('Invalid export format specified.', 'school-management-system'));
            }
        } catch (Exception $e) {
            return new WP_Error('export_failed', $e->getMessage());
        }
    }

    /**
     * Get income transactions for the specified period.
     *
     * @param array $date_range Date range
     * @param array $filters    Additional filters
     * @return array Income transactions
     */
    private function get_income_transactions($date_range, $filters = []) {
        $args = [
            'post_type' => 'sms_transactions',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => 'transaction_type',
                    'value' => 'payment',
                    'compare' => '='
                ],
                [
                    'key' => 'transaction_status',
                    'value' => 'completed',
                    'compare' => '='
                ],
                [
                    'key' => 'transaction_date',
                    'value' => [$date_range['start'], $date_range['end']],
                    'compare' => 'BETWEEN',
                    'type' => 'DATE'
                ]
            ]
        ];

        // Apply additional filters
        if (!empty($filters['payment_method'])) {
            $args['meta_query'][] = [
                'key' => 'payment_method',
                'value' => $filters['payment_method'],
                'compare' => '='
            ];
        }

        if (!empty($filters['class_id'])) {
            $args['meta_query'][] = [
                'key' => 'student_class',
                'value' => $filters['class_id'],
                'compare' => '='
            ];
        }

        $transactions = get_posts($args);
        
        return array_map(function($transaction) {
            return [
                'id' => $transaction->ID,
                'amount' => floatval(get_field('amount', $transaction->ID)),
                'date' => get_field('transaction_date', $transaction->ID),
                'payment_method' => get_field('payment_method', $transaction->ID),
                'student_id' => get_field('student', $transaction->ID),
                'invoice_id' => get_field('invoice', $transaction->ID),
                'fee_type' => $this->get_transaction_fee_type($transaction->ID)
            ];
        }, $transactions);
    }

    /**
     * Get expense transactions for the specified period.
     *
     * @param array $date_range Date range
     * @param array $filters    Additional filters
     * @return array Expense transactions
     */
    private function get_expense_transactions($date_range, $filters = []) {
        $args = [
            'post_type' => 'sms_transactions',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => 'transaction_type',
                    'value' => 'expense',
                    'compare' => '='
                ],
                [
                    'key' => 'transaction_date',
                    'value' => [$date_range['start'], $date_range['end']],
                    'compare' => 'BETWEEN',
                    'type' => 'DATE'
                ]
            ]
        ];

        $transactions = get_posts($args);
        
        return array_map(function($transaction) {
            return [
                'id' => $transaction->ID,
                'amount' => floatval(get_field('amount', $transaction->ID)),
                'date' => get_field('transaction_date', $transaction->ID),
                'category' => get_field('expense_category', $transaction->ID),
                'description' => $transaction->post_content
            ];
        }, $transactions);
    }

    /**
     * Validate report parameters.
     *
     * @param string $report_type Report type
     * @param array  $date_range  Date range
     * @param array  $filters     Filters
     * @return bool|WP_Error True if valid, WP_Error if invalid
     */
    private function validate_report_parameters($report_type, $date_range, $filters) {
        $valid_report_types = [
            'income', 'expense', 'cash_flow', 'collection_status', 
            'outstanding_amounts', 'invoice_tracking', 'payment_trends', 
            'gateway_transactions', 'reconciliation'
        ];

        if (!in_array($report_type, $valid_report_types)) {
            return new WP_Error('invalid_report_type', __('Invalid report type.', 'school-management-system'));
        }

        if (empty($date_range['start']) || empty($date_range['end'])) {
            return new WP_Error('invalid_date_range', __('Start and end dates are required.', 'school-management-system'));
        }

        if (strtotime($date_range['start']) > strtotime($date_range['end'])) {
            return new WP_Error('invalid_date_range', __('Start date must be before end date.', 'school-management-system'));
        }

        return true;
    }

    /**
     * Generate cache key for report.
     *
     * @param string $report_type Report type
     * @param array  $date_range  Date range
     * @param array  $filters     Filters
     * @return string Cache key
     */
    private function get_cache_key($report_type, $date_range, $filters) {
        $key_data = [
            'type' => $report_type,
            'start' => $date_range['start'],
            'end' => $date_range['end'],
            'filters' => $filters
        ];
        
        return 'sms_financial_report_' . md5(serialize($key_data));
    }

    /**
     * Clear report cache.
     */
    public function clear_report_cache() {
        global $wpdb;
        
        // Delete all financial report transients
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_sms_financial_report_%' 
             OR option_name LIKE '_transient_timeout_sms_financial_report_%'"
        );
    }

    // Additional helper methods would be implemented here...
    // Due to length constraints, I'm showing the core structure
}    /**

     * Group transactions by fee type.
     *
     * @param array $transactions Transactions array
     * @return array Grouped data
     */
    private function group_transactions_by_fee_type($transactions) {
        $grouped = [];
        
        foreach ($transactions as $transaction) {
            $fee_type = $transaction['fee_type'] ?? 'Unknown';
            if (!isset($grouped[$fee_type])) {
                $grouped[$fee_type] = [
                    'name' => $fee_type,
                    'amount' => 0,
                    'count' => 0
                ];
            }
            $grouped[$fee_type]['amount'] += $transaction['amount'];
            $grouped[$fee_type]['count']++;
        }
        
        // Sort by amount descending
        uasort($grouped, function($a, $b) {
            return $b['amount'] <=> $a['amount'];
        });
        
        return $grouped;
    }

    /**
     * Group transactions by payment method.
     *
     * @param array $transactions Transactions array
     * @return array Grouped data
     */
    private function group_transactions_by_payment_method($transactions) {
        $grouped = [];
        
        foreach ($transactions as $transaction) {
            $method = $transaction['payment_method'] ?? 'Unknown';
            if (!isset($grouped[$method])) {
                $grouped[$method] = [
                    'name' => ucfirst($method),
                    'amount' => 0,
                    'count' => 0
                ];
            }
            $grouped[$method]['amount'] += $transaction['amount'];
            $grouped[$method]['count']++;
        }
        
        return $grouped;
    }

    /**
     * Group transactions by class.
     *
     * @param array $transactions Transactions array
     * @return array Grouped data
     */
    private function group_transactions_by_class($transactions) {
        $grouped = [];
        
        foreach ($transactions as $transaction) {
            $student_id = $transaction['student_id'];
            $class_name = get_field('current_class', $student_id) ?? 'Unknown';
            
            if (!isset($grouped[$class_name])) {
                $grouped[$class_name] = [
                    'name' => $class_name,
                    'amount' => 0,
                    'count' => 0
                ];
            }
            $grouped[$class_name]['amount'] += $transaction['amount'];
            $grouped[$class_name]['count']++;
        }
        
        return $grouped;
    }

    /**
     * Calculate monthly trends.
     *
     * @param array $transactions Transactions array
     * @param array $date_range   Date range
     * @return array Monthly trends
     */
    private function calculate_monthly_trends($transactions, $date_range) {
        $trends = [];
        
        // Initialize all months in range
        $start = new DateTime($date_range['start']);
        $end = new DateTime($date_range['end']);
        
        while ($start <= $end) {
            $month_key = $start->format('Y-m');
            $trends[$month_key] = 0;
            $start->modify('+1 month');
        }
        
        // Aggregate transactions by month
        foreach ($transactions as $transaction) {
            $month_key = date('Y-m', strtotime($transaction['date']));
            if (isset($trends[$month_key])) {
                $trends[$month_key] += $transaction['amount'];
            }
        }
        
        return $trends;
    }

    /**
     * Calculate daily trends for short periods.
     *
     * @param array $transactions Transactions array
     * @param array $date_range   Date range
     * @return array Daily trends
     */
    private function calculate_daily_trends($transactions, $date_range) {
        $trends = [];
        
        // Initialize all days in range
        $start = new DateTime($date_range['start']);
        $end = new DateTime($date_range['end']);
        
        while ($start <= $end) {
            $day_key = $start->format('Y-m-d');
            $trends[$day_key] = 0;
            $start->modify('+1 day');
        }
        
        // Aggregate transactions by day
        foreach ($transactions as $transaction) {
            $day_key = $transaction['date'];
            if (isset($trends[$day_key])) {
                $trends[$day_key] += $transaction['amount'];
            }
        }
        
        return $trends;
    }

    /**
     * Get transaction fee type.
     *
     * @param int $transaction_id Transaction ID
     * @return string Fee type
     */
    private function get_transaction_fee_type($transaction_id) {
        $invoice_id = get_field('invoice', $transaction_id);
        if (!$invoice_id) {
            return 'Direct Payment';
        }
        
        $invoice_items = get_field('invoice_items', $invoice_id);
        if (empty($invoice_items)) {
            return 'Unknown';
        }
        
        // Get the first fee type (could be enhanced to handle multiple types)
        $first_item = $invoice_items[0];
        $fee_id = $first_item['item_fee'];
        
        return get_field('fee_type', $fee_id) ?? 'Unknown';
    }

    /**
     * Check if date range is a short period (less than 60 days).
     *
     * @param array $date_range Date range
     * @return bool Whether it's a short period
     */
    private function is_short_period($date_range) {
        $start = strtotime($date_range['start']);
        $end = strtotime($date_range['end']);
        $days = ($end - $start) / DAY_IN_SECONDS;
        
        return $days <= 60;
    }

    /**
     * Prepare pie chart data.
     *
     * @param array $data Data array
     * @return array Chart data
     */
    private function prepare_pie_chart_data($data) {
        $chart_data = [
            'labels' => [],
            'values' => [],
            'colors' => []
        ];
        
        $colors = ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40'];
        $color_index = 0;
        
        foreach ($data as $item) {
            $chart_data['labels'][] = $item['name'];
            $chart_data['values'][] = $item['amount'];
            $chart_data['colors'][] = $colors[$color_index % count($colors)];
            $color_index++;
        }
        
        return $chart_data;
    }

    /**
     * Prepare bar chart data.
     *
     * @param array $data Data array
     * @return array Chart data
     */
    private function prepare_bar_chart_data($data) {
        $chart_data = [
            'labels' => [],
            'values' => []
        ];
        
        foreach ($data as $item) {
            $chart_data['labels'][] = $item['name'];
            $chart_data['values'][] = $item['amount'];
        }
        
        return $chart_data;
    }

    /**
     * Prepare line chart data.
     *
     * @param array $data Data array
     * @return array Chart data
     */
    private function prepare_line_chart_data($data) {
        $chart_data = [
            'labels' => array_keys($data),
            'values' => array_values($data)
        ];
        
        return $chart_data;
    }

    /**
     * Export report to PDF.
     *
     * @param array  $report_data Report data
     * @param string $filename    Filename
     * @param array  $options     Options
     * @return string File path
     */
    private function export_to_pdf($report_data, $filename, $options = []) {
        // This would integrate with a PDF library like TCPDF or DOMPDF
        // For now, return a placeholder
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['path'] . '/' . $filename . '.pdf';
        
        // Generate PDF content (placeholder)
        $pdf_content = $this->generate_pdf_content($report_data, $options);
        file_put_contents($file_path, $pdf_content);
        
        return $file_path;
    }

    /**
     * Export report to Excel.
     *
     * @param array  $report_data Report data
     * @param string $filename    Filename
     * @param array  $options     Options
     * @return string File path
     */
    private function export_to_excel($report_data, $filename, $options = []) {
        // This would integrate with PhpSpreadsheet
        // For now, return a placeholder
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['path'] . '/' . $filename . '.xlsx';
        
        // Generate Excel content (placeholder)
        $excel_content = $this->generate_excel_content($report_data, $options);
        file_put_contents($file_path, $excel_content);
        
        return $file_path;
    }

    /**
     * Export report to CSV.
     *
     * @param array  $report_data Report data
     * @param string $filename    Filename
     * @param array  $options     Options
     * @return string File path
     */
    private function export_to_csv($report_data, $filename, $options = []) {
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['path'] . '/' . $filename . '.csv';
        
        $csv_content = $this->generate_csv_content($report_data, $options);
        file_put_contents($file_path, $csv_content);
        
        return $file_path;
    }

    /**
     * Generate CSV content from report data.
     *
     * @param array $report_data Report data
     * @param array $options     Options
     * @return string CSV content
     */
    private function generate_csv_content($report_data, $options = []) {
        $csv_lines = [];
        
        // Add header
        $csv_lines[] = 'Financial Report - ' . ($report_data['metadata']['report_type'] ?? 'Unknown');
        $csv_lines[] = 'Generated: ' . ($report_data['metadata']['generated_at'] ?? date('Y-m-d H:i:s'));
        $csv_lines[] = 'Period: ' . ($report_data['metadata']['date_range']['start'] ?? '') . ' to ' . ($report_data['metadata']['date_range']['end'] ?? '');
        $csv_lines[] = '';
        
        // Add summary data
        if (isset($report_data['summary'])) {
            $csv_lines[] = 'Summary';
            foreach ($report_data['summary'] as $key => $value) {
                $csv_lines[] = ucfirst(str_replace('_', ' ', $key)) . ',' . $value;
            }
            $csv_lines[] = '';
        }
        
        // Add breakdown data
        if (isset($report_data['breakdown'])) {
            foreach ($report_data['breakdown'] as $breakdown_type => $breakdown_data) {
                $csv_lines[] = ucfirst(str_replace('_', ' ', $breakdown_type));
                $csv_lines[] = 'Name,Amount,Count';
                
                foreach ($breakdown_data as $item) {
                    $csv_lines[] = implode(',', [
                        $item['name'],
                        $item['amount'],
                        $item['count'] ?? ''
                    ]);
                }
                $csv_lines[] = '';
            }
        }
        
        return implode("\n", $csv_lines);
    }

    /**
     * Generate PDF content placeholder.
     *
     * @param array $report_data Report data
     * @param array $options     Options
     * @return string PDF content
     */
    private function generate_pdf_content($report_data, $options = []) {
        // Placeholder for PDF generation
        return 'PDF content would be generated here using TCPDF or similar library';
    }

    /**
     * Generate Excel content placeholder.
     *
     * @param array $report_data Report data
     * @param array $options     Options
     * @return string Excel content
     */
    private function generate_excel_content($report_data, $options = []) {
        // Placeholder for Excel generation
        return 'Excel content would be generated here using PhpSpreadsheet';
    }

    /**
     * Get invoices in period.
     *
     * @param array $date_range Date range
     * @param array $filters    Filters
     * @return array Invoices
     */
    private function get_invoices_in_period($date_range, $filters = []) {
        $args = [
            'post_type' => 'sms_invoices',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => 'invoice_date',
                    'value' => [$date_range['start'], $date_range['end']],
                    'compare' => 'BETWEEN',
                    'type' => 'DATE'
                ]
            ]
        ];

        $invoices = get_posts($args);
        
        return array_map(function($invoice) {
            return [
                'id' => $invoice->ID,
                'invoice_number' => get_field('invoice_number', $invoice->ID),
                'student_id' => get_field('student', $invoice->ID),
                'total_amount' => floatval(get_field('total_amount', $invoice->ID)),
                'amount_paid' => floatval(get_field('amount_paid', $invoice->ID)),
                'balance_due' => floatval(get_field('balance_due', $invoice->ID)),
                'invoice_status' => get_field('invoice_status', $invoice->ID),
                'due_date' => get_field('due_date', $invoice->ID),
                'invoice_date' => get_field('invoice_date', $invoice->ID)
            ];
        }, $invoices);
    }

    /**
     * Get outstanding invoices.
     *
     * @param array $date_range Date range
     * @param array $filters    Filters
     * @return array Outstanding invoices
     */
    private function get_outstanding_invoices($date_range, $filters = []) {
        $args = [
            'post_type' => 'sms_invoices',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => 'balance_due',
                    'value' => 0,
                    'compare' => '>',
                    'type' => 'NUMERIC'
                ],
                [
                    'key' => 'invoice_date',
                    'value' => $date_range['end'],
                    'compare' => '<=',
                    'type' => 'DATE'
                ]
            ]
        ];

        $invoices = get_posts($args);
        
        return array_map(function($invoice) {
            return [
                'id' => $invoice->ID,
                'invoice_number' => get_field('invoice_number', $invoice->ID),
                'student_id' => get_field('student', $invoice->ID),
                'balance_due' => floatval(get_field('balance_due', $invoice->ID)),
                'due_date' => get_field('due_date', $invoice->ID),
                'days_overdue' => $this->calculate_days_overdue(get_field('due_date', $invoice->ID))
            ];
        }, $invoices);
    }

    /**
     * Calculate days overdue.
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
     * Calculate aging analysis.
     *
     * @param array $outstanding_invoices Outstanding invoices
     * @return array Aging analysis
     */
    private function calculate_aging_analysis($outstanding_invoices) {
        $aging = [
            '0-30 days' => ['amount' => 0, 'count' => 0],
            '31-60 days' => ['amount' => 0, 'count' => 0],
            '61-90 days' => ['amount' => 0, 'count' => 0],
            '90+ days' => ['amount' => 0, 'count' => 0]
        ];
        
        foreach ($outstanding_invoices as $invoice) {
            $days_overdue = $invoice['days_overdue'];
            $amount = $invoice['balance_due'];
            
            if ($days_overdue <= 30) {
                $aging['0-30 days']['amount'] += $amount;
                $aging['0-30 days']['count']++;
            } elseif ($days_overdue <= 60) {
                $aging['31-60 days']['amount'] += $amount;
                $aging['31-60 days']['count']++;
            } elseif ($days_overdue <= 90) {
                $aging['61-90 days']['amount'] += $amount;
                $aging['61-90 days']['count']++;
            } else {
                $aging['90+ days']['amount'] += $amount;
                $aging['90+ days']['count']++;
            }
        }
        
        return $aging;
    }

    /**
     * Group outstanding amounts by student.
     *
     * @param array $outstanding_invoices Outstanding invoices
     * @return array Grouped by student
     */
    private function group_outstanding_by_student($outstanding_invoices) {
        $grouped = [];
        
        foreach ($outstanding_invoices as $invoice) {
            $student_id = $invoice['student_id'];
            $student_name = get_field('full_name', $student_id);
            
            if (!isset($grouped[$student_id])) {
                $grouped[$student_id] = [
                    'name' => $student_name,
                    'amount' => 0,
                    'count' => 0
                ];
            }
            
            $grouped[$student_id]['amount'] += $invoice['balance_due'];
            $grouped[$student_id]['count']++;
        }
        
        // Sort by amount descending
        uasort($grouped, function($a, $b) {
            return $b['amount'] <=> $a['amount'];
        });
        
        return $grouped;
    }

    /**
     * Group outstanding amounts by fee type.
     *
     * @param array $outstanding_invoices Outstanding invoices
     * @return array Grouped by fee type
     */
    private function group_outstanding_by_fee_type($outstanding_invoices) {
        $grouped = [];
        
        foreach ($outstanding_invoices as $invoice) {
            $invoice_items = get_field('invoice_items', $invoice['id']);
            
            if (!empty($invoice_items)) {
                foreach ($invoice_items as $item) {
                    $fee_id = $item['item_fee'];
                    $fee_type = get_field('fee_type', $fee_id) ?? 'Unknown';
                    
                    if (!isset($grouped[$fee_type])) {
                        $grouped[$fee_type] = [
                            'name' => $fee_type,
                            'amount' => 0,
                            'count' => 0
                        ];
                    }
                    
                    // Proportional allocation based on item total
                    $item_proportion = $item['item_total'] / get_field('total_amount', $invoice['id']);
                    $allocated_outstanding = $invoice['balance_due'] * $item_proportion;
                    
                    $grouped[$fee_type]['amount'] += $allocated_outstanding;
                    $grouped[$fee_type]['count']++;
                }
            }
        }
        
        return $grouped;
    }
}