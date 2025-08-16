<?php
/**
 * Payment History Tracker
 *
 * Handles comprehensive payment history tracking, reporting,
 * and analytics for students, invoices, and system-wide payments.
 *
 * @package    School_Management_System
 * @subpackage School_Management_System/includes/financial
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Payment History Tracker Class
 */
class SMS_Payment_History_Tracker extends SMS_Base {

    /**
     * Payment history cache
     */
    private $history_cache = [];

    /**
     * Initialize the class and set its properties.
     */
    public function __construct() {
        parent::__construct();
        
        // Hook into payment events
        add_action('sms_transaction_completed', [$this, 'track_payment_completion'], 10, 2);
        add_action('sms_payment_refunded', [$this, 'track_payment_refund'], 10, 2);
        add_action('sms_payment_disputed', [$this, 'track_payment_dispute'], 10, 2);
        
        // Hook into invoice events
        add_action('sms_invoice_fully_paid', [$this, 'track_invoice_completion'], 10, 1);
        add_action('sms_invoice_partially_paid', [$this, 'track_partial_payment'], 10, 2);
        
        // Daily history consolidation
        add_action('sms_daily_history_consolidation', [$this, 'consolidate_daily_history']);
        
        // Schedule daily consolidation if not already scheduled
        if (!wp_next_scheduled('sms_daily_history_consolidation')) {
            wp_schedule_event(time(), 'daily', 'sms_daily_history_consolidation');
        }
    }

    /**
     * Track payment completion and update history.
     *
     * @param int   $transaction_id Transaction ID
     * @param array $transaction_data Transaction data
     */
    public function track_payment_completion($transaction_id, $transaction_data) {
        try {
            // Get transaction details
            $transaction_details = $this->get_transaction_details($transaction_id);
            
            if (!$transaction_details) {
                return;
            }
            
            // Update student payment history
            $this->update_student_payment_history($transaction_details);
            
            // Update invoice payment history
            if ($transaction_details['invoice_id']) {
                $this->update_invoice_payment_history($transaction_details);
            }
            
            // Update system-wide payment statistics
            $this->update_system_payment_statistics($transaction_details);
            
            // Clear relevant caches
            $this->clear_history_cache($transaction_details['student_id']);
            
            // Log payment tracking
            $this->log_activity(
                0,
                'payment_tracked',
                'transaction',
                $transaction_id,
                [
                    'student_id' => $transaction_details['student_id'],
                    'amount' => $transaction_details['amount'],
                    'method' => $transaction_details['payment_method']
                ]
            );
            
        } catch (Exception $e) {
            error_log('SMS Payment History Tracker: Failed to track payment completion - ' . $e->getMessage());
        }
    }

    /**
     * Generate comprehensive payment history report.
     *
     * @param array $filters Report filters
     * @return array Payment history report
     */
    public function generate_payment_history_report($filters = []) {
        $cache_key = 'payment_history_report_' . md5(serialize($filters));
        
        if (isset($this->history_cache[$cache_key])) {
            return $this->history_cache[$cache_key];
        }
        
        $report = [
            'summary' => [
                'total_payments' => 0,
                'total_amount' => 0,
                'average_payment' => 0,
                'payment_methods' => [],
                'date_range' => [],
                'currency' => 'KES'
            ],
            'payments' => [],
            'trends' => [],
            'filters_applied' => $filters,
            'generated_at' => current_time('mysql')
        ];
        
        // Build query for transactions
        $transactions = $this->query_payment_history($filters);
        
        // Process transactions
        foreach ($transactions as $transaction) {
            $payment_data = $this->format_payment_data($transaction);
            $report['payments'][] = $payment_data;
            
            // Update summary
            $report['summary']['total_payments']++;
            $report['summary']['total_amount'] += $payment_data['amount'];
            
            // Track payment methods
            $method = $payment_data['payment_method'];
            if (!isset($report['summary']['payment_methods'][$method])) {
                $report['summary']['payment_methods'][$method] = [
                    'count' => 0,
                    'amount' => 0,
                    'percentage' => 0
                ];
            }
            $report['summary']['payment_methods'][$method]['count']++;
            $report['summary']['payment_methods'][$method]['amount'] += $payment_data['amount'];
        }
        
        // Calculate averages and percentages
        if ($report['summary']['total_payments'] > 0) {
            $report['summary']['average_payment'] = $report['summary']['total_amount'] / $report['summary']['total_payments'];
            
            foreach ($report['summary']['payment_methods'] as $method => &$method_data) {
                $method_data['percentage'] = ($method_data['amount'] / $report['summary']['total_amount']) * 100;
            }
        }
        
        // Set date range
        if (!empty($report['payments'])) {
            $dates = array_column($report['payments'], 'payment_date');
            $report['summary']['date_range'] = [
                'from' => min($dates),
                'to' => max($dates)
            ];
        }
        
        // Generate trends if date range is specified
        if (!empty($filters['date_from']) && !empty($filters['date_to'])) {
            $report['trends'] = $this->generate_payment_trends($report['payments'], $filters);
        }
        
        // Cache the report
        $this->history_cache[$cache_key] = $report;
        
        return $report;
    }

    /**
     * Get student payment history with detailed breakdown.
     *
     * @param int   $student_id Student ID
     * @param array $options History options
     * @return array Student payment history
     */
    public function get_student_payment_history($student_id, $options = []) {
        $cache_key = "student_history_{$student_id}_" . md5(serialize($options));
        
        if (isset($this->history_cache[$cache_key])) {
            return $this->history_cache[$cache_key];
        }
        
        $history = [
            'student_id' => $student_id,
            'student_info' => $this->get_student_info($student_id),
            'summary' => [
                'total_paid' => 0,
                'total_invoices' => 0,
                'paid_invoices' => 0,
                'pending_amount' => 0,
                'overdue_amount' => 0,
                'payment_methods_used' => [],
                'first_payment_date' => null,
                'last_payment_date' => null
            ],
            'payments' => [],
            'invoices' => [],
            'payment_timeline' => []
        ];
        
        // Get all transactions for this student
        $transactions = $this->get_student_transactions($student_id, $options);
        
        foreach ($transactions as $transaction) {
            $payment_data = $this->format_payment_data($transaction);
            $history['payments'][] = $payment_data;
            
            // Update summary
            $history['summary']['total_paid'] += $payment_data['amount'];
            
            // Track payment methods
            $method = $payment_data['payment_method'];
            if (!in_array($method, $history['summary']['payment_methods_used'])) {
                $history['summary']['payment_methods_used'][] = $method;
            }
            
            // Track date range
            if (!$history['summary']['first_payment_date'] || 
                $payment_data['payment_date'] < $history['summary']['first_payment_date']) {
                $history['summary']['first_payment_date'] = $payment_data['payment_date'];
            }
            
            if (!$history['summary']['last_payment_date'] || 
                $payment_data['payment_date'] > $history['summary']['last_payment_date']) {
                $history['summary']['last_payment_date'] = $payment_data['payment_date'];
            }
        }
        
        // Get invoice information
        $invoices = $this->get_student_invoices($student_id, $options);
        
        foreach ($invoices as $invoice) {
            $invoice_data = $this->format_invoice_data($invoice);
            $history['invoices'][] = $invoice_data;
            
            // Update invoice summary
            $history['summary']['total_invoices']++;
            
            if ($invoice_data['payment_status'] === 'paid') {
                $history['summary']['paid_invoices']++;
            } elseif ($invoice_data['payment_status'] === 'overdue') {
                $history['summary']['overdue_amount'] += $invoice_data['balance_due'];
            } else {
                $history['summary']['pending_amount'] += $invoice_data['balance_due'];
            }
        }
        
        // Generate payment timeline
        $history['payment_timeline'] = $this->generate_payment_timeline($history['payments'], $history['invoices']);
        
        // Cache the history
        $this->history_cache[$cache_key] = $history;
        
        return $history;
    }

    /**
     * Get invoice payment history with transaction details.
     *
     * @param int $invoice_id Invoice ID
     * @return array Invoice payment history
     */
    public function get_invoice_payment_history($invoice_id) {
        $cache_key = "invoice_history_{$invoice_id}";
        
        if (isset($this->history_cache[$cache_key])) {
            return $this->history_cache[$cache_key];
        }
        
        $history = [
            'invoice_id' => $invoice_id,
            'invoice_info' => $this->get_invoice_info($invoice_id),
            'payment_summary' => [
                'total_amount' => 0,
                'amount_paid' => 0,
                'balance_due' => 0,
                'payment_count' => 0,
                'first_payment_date' => null,
                'last_payment_date' => null,
                'payment_status' => 'unpaid'
            ],
            'payments' => [],
            'payment_schedule' => [],
            'status_history' => []
        ];
        
        // Get invoice basic info
        $invoice_info = $history['invoice_info'];
        $history['payment_summary']['total_amount'] = $invoice_info['total_amount'];
        $history['payment_summary']['balance_due'] = $invoice_info['balance_due'];
        $history['payment_summary']['payment_status'] = $invoice_info['payment_status'];
        
        // Get all payments for this invoice
        $payments = $this->get_invoice_payments($invoice_id);
        
        foreach ($payments as $payment) {
            $payment_data = $this->format_payment_data($payment);
            $history['payments'][] = $payment_data;
            
            // Update payment summary
            $history['payment_summary']['amount_paid'] += $payment_data['amount'];
            $history['payment_summary']['payment_count']++;
            
            // Track payment dates
            if (!$history['payment_summary']['first_payment_date'] || 
                $payment_data['payment_date'] < $history['payment_summary']['first_payment_date']) {
                $history['payment_summary']['first_payment_date'] = $payment_data['payment_date'];
            }
            
            if (!$history['payment_summary']['last_payment_date'] || 
                $payment_data['payment_date'] > $history['payment_summary']['last_payment_date']) {
                $history['payment_summary']['last_payment_date'] = $payment_data['payment_date'];
            }
        }
        
        // Get payment schedule if invoice has installments
        $history['payment_schedule'] = $this->get_invoice_payment_schedule($invoice_id);
        
        // Get status change history
        $history['status_history'] = $this->get_invoice_status_history($invoice_id);
        
        // Cache the history
        $this->history_cache[$cache_key] = $history;
        
        return $history;
    }

    /**
     * Generate payment analytics and insights.
     *
     * @param array $filters Analytics filters
     * @return array Payment analytics
     */
    public function generate_payment_analytics($filters = []) {
        $analytics = [
            'overview' => [
                'total_revenue' => 0,
                'payment_count' => 0,
                'average_payment' => 0,
                'collection_rate' => 0,
                'overdue_rate' => 0
            ],
            'trends' => [
                'daily' => [],
                'weekly' => [],
                'monthly' => []
            ],
            'payment_methods' => [],
            'student_segments' => [],
            'fee_categories' => [],
            'collection_efficiency' => []
        ];
        
        // Get payment data for analysis
        $payments = $this->query_payment_history($filters);
        $invoices = $this->query_invoice_history($filters);
        
        // Calculate overview metrics
        $analytics['overview'] = $this->calculate_overview_metrics($payments, $invoices);
        
        // Generate trend analysis
        $analytics['trends'] = $this->analyze_payment_trends($payments, $filters);
        
        // Analyze payment methods
        $analytics['payment_methods'] = $this->analyze_payment_methods($payments);
        
        // Segment student payment behavior
        $analytics['student_segments'] = $this->analyze_student_segments($payments, $invoices);
        
        // Analyze fee categories
        $analytics['fee_categories'] = $this->analyze_fee_categories($payments);
        
        // Calculate collection efficiency
        $analytics['collection_efficiency'] = $this->calculate_collection_efficiency($invoices);
        
        return $analytics;
    }

    /**
     * Export payment history to various formats.
     *
     * @param array  $filters Export filters
     * @param string $format Export format (csv, excel, pdf)
     * @return array Export result
     */
    public function export_payment_history($filters, $format = 'csv') {
        $export_result = [
            'success' => false,
            'file_path' => null,
            'file_url' => null,
            'error' => null
        ];
        
        try {
            // Generate payment history report
            $report = $this->generate_payment_history_report($filters);
            
            // Create export file based on format
            switch ($format) {
                case 'csv':
                    $export_result = $this->export_to_csv($report, $filters);
                    break;
                case 'excel':
                    $export_result = $this->export_to_excel($report, $filters);
                    break;
                case 'pdf':
                    $export_result = $this->export_to_pdf($report, $filters);
                    break;
                default:
                    throw new Exception('Unsupported export format: ' . $format);
            }
            
            // Log export activity
            if ($export_result['success']) {
                $this->log_activity(
                    get_current_user_id(),
                    'payment_history_exported',
                    'export',
                    0,
                    [
                        'format' => $format,
                        'filters' => $filters,
                        'record_count' => count($report['payments'])
                    ]
                );
            }
            
        } catch (Exception $e) {
            $export_result['error'] = $e->getMessage();
        }
        
        return $export_result;
    }

    /**
     * Track payment refund.
     *
     * @param int   $transaction_id Transaction ID
     * @param array $refund_data Refund data
     */
    public function track_payment_refund($transaction_id, $refund_data) {
        // Update transaction history
        $refund_record = [
            'type' => 'refund',
            'date' => current_time('mysql'),
            'amount' => $refund_data['amount'],
            'reason' => $refund_data['reason'] ?? '',
            'processed_by' => get_current_user_id()
        ];
        
        $transaction_history = get_field('transaction_history', $transaction_id) ?? [];
        $transaction_history[] = $refund_record;
        update_field('transaction_history', $transaction_history, $transaction_id);
        
        // Update system statistics
        $this->update_refund_statistics($refund_data);
        
        // Clear relevant caches
        $student_id = get_field('student', $transaction_id);
        $this->clear_history_cache($student_id);
    }

    /**
     * Track payment dispute.
     *
     * @param int   $transaction_id Transaction ID
     * @param array $dispute_data Dispute data
     */
    public function track_payment_dispute($transaction_id, $dispute_data) {
        // Update transaction history
        $dispute_record = [
            'type' => 'dispute',
            'date' => current_time('mysql'),
            'reason' => $dispute_data['reason'] ?? '',
            'status' => $dispute_data['status'] ?? 'open',
            'reported_by' => get_current_user_id()
        ];
        
        $transaction_history = get_field('transaction_history', $transaction_id) ?? [];
        $transaction_history[] = $dispute_record;
        update_field('transaction_history', $transaction_history, $transaction_id);
        
        // Update dispute statistics
        $this->update_dispute_statistics($dispute_data);
        
        // Clear relevant caches
        $student_id = get_field('student', $transaction_id);
        $this->clear_history_cache($student_id);
    }

    /**
     * Track invoice completion.
     *
     * @param int $invoice_id Invoice ID
     */
    public function track_invoice_completion($invoice_id) {
        // Update invoice completion statistics
        $completion_data = [
            'invoice_id' => $invoice_id,
            'completion_date' => current_time('mysql'),
            'total_amount' => get_field('total_amount', $invoice_id),
            'payment_count' => count($this->get_invoice_payments($invoice_id))
        ];
        
        $this->update_invoice_completion_statistics($completion_data);
        
        // Clear relevant caches
        $student_id = get_field('student', $invoice_id);
        $this->clear_history_cache($student_id);
    }

    /**
     * Track partial payment.
     *
     * @param int   $invoice_id Invoice ID
     * @param array $payment_data Payment data
     */
    public function track_partial_payment($invoice_id, $payment_data) {
        // Update partial payment tracking
        $partial_payments = get_field('partial_payments', $invoice_id) ?? [];
        $partial_payments[] = [
            'date' => current_time('mysql'),
            'amount' => $payment_data['amount'],
            'transaction_id' => $payment_data['transaction_id'],
            'balance_remaining' => get_field('balance_due', $invoice_id)
        ];
        update_field('partial_payments', $partial_payments, $invoice_id);
        
        // Clear relevant caches
        $student_id = get_field('student', $invoice_id);
        $this->clear_history_cache($student_id);
    }

    /**
     * Consolidate daily payment history for performance.
     */
    public function consolidate_daily_history() {
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        
        // Consolidate daily payment statistics
        $daily_stats = $this->calculate_daily_payment_statistics($yesterday);
        update_option('sms_daily_payment_stats_' . str_replace('-', '_', $yesterday), $daily_stats);
        
        // Consolidate weekly statistics if it's Sunday
        if (date('w') == 0) { // Sunday
            $week_start = date('Y-m-d', strtotime('last Monday', strtotime($yesterday)));
            $weekly_stats = $this->calculate_weekly_payment_statistics($week_start);
            update_option('sms_weekly_payment_stats_' . str_replace('-', '_', $week_start), $weekly_stats);
        }
        
        // Consolidate monthly statistics if it's the first day of the month
        if (date('j') == 1) {
            $last_month = date('Y-m', strtotime('-1 month'));
            $monthly_stats = $this->calculate_monthly_payment_statistics($last_month);
            update_option('sms_monthly_payment_stats_' . str_replace('-', '_', $last_month), $monthly_stats);
        }
        
        // Clear old cache entries
        $this->cleanup_old_cache();
    }

    /**
     * Query payment history with filters.
     *
     * @param array $filters Query filters
     * @return array Transaction posts
     */
    private function query_payment_history($filters) {
        $args = [
            'post_type' => 'sms_transactions',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => 'transaction_status',
                    'value' => 'completed',
                    'compare' => '='
                ]
            ],
            'orderby' => 'meta_value',
            'meta_key' => 'transaction_date',
            'order' => 'DESC'
        ];
        
        // Apply filters
        if (!empty($filters['student_id'])) {
            $args['meta_query'][] = [
                'key' => 'student',
                'value' => $filters['student_id'],
                'compare' => '='
            ];
        }
        
        if (!empty($filters['invoice_id'])) {
            $args['meta_query'][] = [
                'key' => 'invoice',
                'value' => $filters['invoice_id'],
                'compare' => '='
            ];
        }
        
        if (!empty($filters['payment_method'])) {
            $args['meta_query'][] = [
                'key' => 'payment_method',
                'value' => $filters['payment_method'],
                'compare' => '='
            ];
        }
        
        if (!empty($filters['date_from'])) {
            $args['meta_query'][] = [
                'key' => 'transaction_date',
                'value' => $filters['date_from'],
                'compare' => '>=',
                'type' => 'DATE'
            ];
        }
        
        if (!empty($filters['date_to'])) {
            $args['meta_query'][] = [
                'key' => 'transaction_date',
                'value' => $filters['date_to'],
                'compare' => '<=',
                'type' => 'DATE'
            ];
        }
        
        if (!empty($filters['amount_min'])) {
            $args['meta_query'][] = [
                'key' => 'amount',
                'value' => $filters['amount_min'],
                'compare' => '>=',
                'type' => 'NUMERIC'
            ];
        }
        
        if (!empty($filters['amount_max'])) {
            $args['meta_query'][] = [
                'key' => 'amount',
                'value' => $filters['amount_max'],
                'compare' => '<=',
                'type' => 'NUMERIC'
            ];
        }
        
        return get_posts($args);
    }

    /**
     * Format payment data for reports.
     *
     * @param WP_Post $transaction Transaction post
     * @return array Formatted payment data
     */
    private function format_payment_data($transaction) {
        $student_id = get_field('student', $transaction->ID);
        $invoice_id = get_field('invoice', $transaction->ID);
        
        return [
            'transaction_id' => $transaction->ID,
            'payment_date' => get_field('transaction_date', $transaction->ID),
            'amount' => floatval(get_field('amount', $transaction->ID)),
            'payment_method' => get_field('payment_method', $transaction->ID),
            'currency' => get_field('currency', $transaction->ID) ?? 'KES',
            'student_id' => $student_id,
            'student_name' => get_field('full_name', $student_id),
            'admission_number' => get_field('admission_number', $student_id),
            'invoice_id' => $invoice_id,
            'invoice_number' => $invoice_id ? get_field('invoice_number', $invoice_id) : null,
            'gateway_reference' => get_field('gateway_reference', $transaction->ID),
            'receipt_number' => get_field('receipt_number', $transaction->ID),
            'transaction_status' => get_field('transaction_status', $transaction->ID),
            'verification_status' => get_field('verification_status', $transaction->ID)
        ];
    }

    /**
     * Get transaction details.
     *
     * @param int $transaction_id Transaction ID
     * @return array|null Transaction details
     */
    private function get_transaction_details($transaction_id) {
        $transaction = get_post($transaction_id);
        if (!$transaction || $transaction->post_type !== 'sms_transactions') {
            return null;
        }
        
        return [
            'transaction_id' => $transaction_id,
            'student_id' => get_field('student', $transaction_id),
            'invoice_id' => get_field('invoice', $transaction_id),
            'amount' => floatval(get_field('amount', $transaction_id)),
            'payment_method' => get_field('payment_method', $transaction_id),
            'transaction_date' => get_field('transaction_date', $transaction_id),
            'gateway_name' => get_field('gateway_name', $transaction_id),
            'currency' => get_field('currency', $transaction_id) ?? 'KES'
        ];
    }

    /**
     * Update student payment history.
     *
     * @param array $transaction_details Transaction details
     */
    private function update_student_payment_history($transaction_details) {
        $student_id = $transaction_details['student_id'];
        $payment_history = get_field('payment_history', $student_id) ?? [];
        
        $payment_record = [
            'transaction_id' => $transaction_details['transaction_id'],
            'date' => $transaction_details['transaction_date'],
            'amount' => $transaction_details['amount'],
            'method' => $transaction_details['payment_method'],
            'invoice_id' => $transaction_details['invoice_id'],
            'currency' => $transaction_details['currency']
        ];
        
        $payment_history[] = $payment_record;
        update_field('payment_history', $payment_history, $student_id);
        
        // Update student payment summary
        $this->update_student_payment_summary($student_id);
    }

    /**
     * Update invoice payment history.
     *
     * @param array $transaction_details Transaction details
     */
    private function update_invoice_payment_history($transaction_details) {
        $invoice_id = $transaction_details['invoice_id'];
        $payment_history = get_field('payment_history', $invoice_id) ?? [];
        
        $payment_record = [
            'transaction_id' => $transaction_details['transaction_id'],
            'date' => $transaction_details['transaction_date'],
            'amount' => $transaction_details['amount'],
            'method' => $transaction_details['payment_method'],
            'gateway' => $transaction_details['gateway_name'],
            'currency' => $transaction_details['currency']
        ];
        
        $payment_history[] = $payment_record;
        update_field('payment_history', $payment_history, $invoice_id);
    }

    /**
     * Update system payment statistics.
     *
     * @param array $transaction_details Transaction details
     */
    private function update_system_payment_statistics($transaction_details) {
        $date_key = date('Y_m_d', strtotime($transaction_details['transaction_date']));
        $stats_key = 'sms_payment_stats_' . $date_key;
        
        $daily_stats = get_option($stats_key, [
            'payment_count' => 0,
            'total_amount' => 0,
            'methods' => [],
            'gateways' => []
        ]);
        
        $daily_stats['payment_count']++;
        $daily_stats['total_amount'] += $transaction_details['amount'];
        
        // Track payment methods
        $method = $transaction_details['payment_method'];
        if (!isset($daily_stats['methods'][$method])) {
            $daily_stats['methods'][$method] = ['count' => 0, 'amount' => 0];
        }
        $daily_stats['methods'][$method]['count']++;
        $daily_stats['methods'][$method]['amount'] += $transaction_details['amount'];
        
        // Track gateways
        $gateway = $transaction_details['gateway_name'];
        if ($gateway) {
            if (!isset($daily_stats['gateways'][$gateway])) {
                $daily_stats['gateways'][$gateway] = ['count' => 0, 'amount' => 0];
            }
            $daily_stats['gateways'][$gateway]['count']++;
            $daily_stats['gateways'][$gateway]['amount'] += $transaction_details['amount'];
        }
        
        update_option($stats_key, $daily_stats);
    }

    /**
     * Clear history cache for a student.
     *
     * @param int $student_id Student ID
     */
    private function clear_history_cache($student_id = null) {
        if ($student_id) {
            // Clear specific student cache
            foreach ($this->history_cache as $key => $value) {
                if (strpos($key, "student_history_{$student_id}") !== false) {
                    unset($this->history_cache[$key]);
                }
            }
        } else {
            // Clear all cache
            $this->history_cache = [];
        }
    }

    /**
     * Get student information.
     *
     * @param int $student_id Student ID
     * @return array Student information
     */
    private function get_student_info($student_id) {
        return [
            'id' => $student_id,
            'name' => get_field('full_name', $student_id),
            'admission_number' => get_field('admission_number', $student_id),
            'class' => get_field('current_class', $student_id),
            'grade' => get_field('grade_level', $student_id),
            'parent_name' => get_field('parent_name', $student_id),
            'parent_email' => get_field('parent_email', $student_id),
            'parent_phone' => get_field('parent_phone', $student_id)
        ];
    }

    /**
     * Get invoice information.
     *
     * @param int $invoice_id Invoice ID
     * @return array Invoice information
     */
    private function get_invoice_info($invoice_id) {
        return [
            'id' => $invoice_id,
            'invoice_number' => get_field('invoice_number', $invoice_id),
            'invoice_date' => get_field('invoice_date', $invoice_id),
            'due_date' => get_field('due_date', $invoice_id),
            'total_amount' => floatval(get_field('total_amount', $invoice_id)),
            'amount_paid' => floatval(get_field('amount_paid', $invoice_id)),
            'balance_due' => floatval(get_field('balance_due', $invoice_id)),
            'payment_status' => get_field('payment_status', $invoice_id),
            'student_id' => get_field('student', $invoice_id)
        ];
    }

    /**
     * Get student transactions.
     *
     * @param int   $student_id Student ID
     * @param array $options Query options
     * @return array Student transactions
     */
    private function get_student_transactions($student_id, $options = []) {
        $args = [
            'post_type' => 'sms_transactions',
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => 'student',
                    'value' => $student_id,
                    'compare' => '='
                ],
                [
                    'key' => 'transaction_status',
                    'value' => 'completed',
                    'compare' => '='
                ]
            ],
            'posts_per_page' => -1,
            'orderby' => 'meta_value',
            'meta_key' => 'transaction_date',
            'order' => 'DESC'
        ];
        
        // Apply date filters if provided
        if (!empty($options['date_from'])) {
            $args['meta_query'][] = [
                'key' => 'transaction_date',
                'value' => $options['date_from'],
                'compare' => '>=',
                'type' => 'DATE'
            ];
        }
        
        if (!empty($options['date_to'])) {
            $args['meta_query'][] = [
                'key' => 'transaction_date',
                'value' => $options['date_to'],
                'compare' => '<=',
                'type' => 'DATE'
            ];
        }
        
        return get_posts($args);
    }

    /**
     * Get student invoices.
     *
     * @param int   $student_id Student ID
     * @param array $options Query options
     * @return array Student invoices
     */
    private function get_student_invoices($student_id, $options = []) {
        $args = [
            'post_type' => 'sms_invoices',
            'meta_query' => [
                [
                    'key' => 'student',
                    'value' => $student_id,
                    'compare' => '='
                ]
            ],
            'posts_per_page' => -1,
            'orderby' => 'meta_value',
            'meta_key' => 'invoice_date',
            'order' => 'DESC'
        ];
        
        return get_posts($args);
    }

    /**
     * Format invoice data.
     *
     * @param WP_Post $invoice Invoice post
     * @return array Formatted invoice data
     */
    private function format_invoice_data($invoice) {
        return [
            'invoice_id' => $invoice->ID,
            'invoice_number' => get_field('invoice_number', $invoice->ID),
            'invoice_date' => get_field('invoice_date', $invoice->ID),
            'due_date' => get_field('due_date', $invoice->ID),
            'total_amount' => floatval(get_field('total_amount', $invoice->ID)),
            'amount_paid' => floatval(get_field('amount_paid', $invoice->ID)),
            'balance_due' => floatval(get_field('balance_due', $invoice->ID)),
            'payment_status' => get_field('payment_status', $invoice->ID),
            'invoice_status' => get_field('invoice_status', $invoice->ID)
        ];
    }

    /**
     * Get invoice payments.
     *
     * @param int $invoice_id Invoice ID
     * @return array Invoice payments
     */
    private function get_invoice_payments($invoice_id) {
        $args = [
            'post_type' => 'sms_transactions',
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => 'invoice',
                    'value' => $invoice_id,
                    'compare' => '='
                ],
                [
                    'key' => 'transaction_status',
                    'value' => 'completed',
                    'compare' => '='
                ]
            ],
            'posts_per_page' => -1,
            'orderby' => 'meta_value',
            'meta_key' => 'transaction_date',
            'order' => 'ASC'
        ];
        
        return get_posts($args);
    }

    /**
     * Generate payment timeline.
     *
     * @param array $payments Payment data
     * @param array $invoices Invoice data
     * @return array Payment timeline
     */
    private function generate_payment_timeline($payments, $invoices) {
        $timeline = [];
        
        // Add payments to timeline
        foreach ($payments as $payment) {
            $timeline[] = [
                'date' => $payment['payment_date'],
                'type' => 'payment',
                'amount' => $payment['amount'],
                'description' => "Payment of KES {$payment['amount']} via {$payment['payment_method']}",
                'reference' => $payment['transaction_id']
            ];
        }
        
        // Add invoice events to timeline
        foreach ($invoices as $invoice) {
            $timeline[] = [
                'date' => $invoice['invoice_date'],
                'type' => 'invoice_created',
                'amount' => $invoice['total_amount'],
                'description' => "Invoice #{$invoice['invoice_number']} created for KES {$invoice['total_amount']}",
                'reference' => $invoice['invoice_id']
            ];
            
            if ($invoice['payment_status'] === 'paid') {
                // Estimate completion date (could be improved with actual data)
                $completion_date = $invoice['due_date']; // Placeholder
                $timeline[] = [
                    'date' => $completion_date,
                    'type' => 'invoice_paid',
                    'amount' => $invoice['total_amount'],
                    'description' => "Invoice #{$invoice['invoice_number']} fully paid",
                    'reference' => $invoice['invoice_id']
                ];
            }
        }
        
        // Sort timeline by date
        usort($timeline, function($a, $b) {
            return strtotime($a['date']) - strtotime($b['date']);
        });
        
        return $timeline;
    }

    /**
     * Export to CSV format.
     *
     * @param array $report Report data
     * @param array $filters Export filters
     * @return array Export result
     */
    private function export_to_csv($report, $filters) {
        $upload_dir = wp_upload_dir();
        $filename = 'payment_history_' . date('Y_m_d_H_i_s') . '.csv';
        $file_path = $upload_dir['path'] . '/' . $filename;
        
        $file = fopen($file_path, 'w');
        
        // Write CSV headers
        $headers = [
            'Transaction ID', 'Payment Date', 'Amount', 'Currency', 'Payment Method',
            'Student Name', 'Admission Number', 'Invoice Number', 'Gateway Reference',
            'Receipt Number', 'Status'
        ];
        fputcsv($file, $headers);
        
        // Write payment data
        foreach ($report['payments'] as $payment) {
            $row = [
                $payment['transaction_id'],
                $payment['payment_date'],
                $payment['amount'],
                $payment['currency'],
                $payment['payment_method'],
                $payment['student_name'],
                $payment['admission_number'],
                $payment['invoice_number'],
                $payment['gateway_reference'],
                $payment['receipt_number'],
                $payment['transaction_status']
            ];
            fputcsv($file, $row);
        }
        
        fclose($file);
        
        return [
            'success' => true,
            'file_path' => $file_path,
            'file_url' => $upload_dir['url'] . '/' . $filename
        ];
    }

    /**
     * Export to Excel format (placeholder).
     *
     * @param array $report Report data
     * @param array $filters Export filters
     * @return array Export result
     */
    private function export_to_excel($report, $filters) {
        // This would require a library like PhpSpreadsheet
        // For now, return CSV export
        return $this->export_to_csv($report, $filters);
    }

    /**
     * Export to PDF format (placeholder).
     *
     * @param array $report Report data
     * @param array $filters Export filters
     * @return array Export result
     */
    private function export_to_pdf($report, $filters) {
        // This would require a library like TCPDF or DOMPDF
        // For now, return error
        return [
            'success' => false,
            'error' => 'PDF export not yet implemented'
        ];
    }

    /**
     * Calculate daily payment statistics.
     *
     * @param string $date Date (Y-m-d format)
     * @return array Daily statistics
     */
    private function calculate_daily_payment_statistics($date) {
        $stats_key = 'sms_payment_stats_' . str_replace('-', '_', $date);
        return get_option($stats_key, [
            'payment_count' => 0,
            'total_amount' => 0,
            'methods' => [],
            'gateways' => []
        ]);
    }

    /**
     * Calculate weekly payment statistics.
     *
     * @param string $week_start Week start date (Y-m-d format)
     * @return array Weekly statistics
     */
    private function calculate_weekly_payment_statistics($week_start) {
        $weekly_stats = [
            'payment_count' => 0,
            'total_amount' => 0,
            'methods' => [],
            'gateways' => [],
            'daily_breakdown' => []
        ];
        
        // Aggregate daily stats for the week
        for ($i = 0; $i < 7; $i++) {
            $date = date('Y-m-d', strtotime($week_start . " +{$i} days"));
            $daily_stats = $this->calculate_daily_payment_statistics($date);
            
            $weekly_stats['payment_count'] += $daily_stats['payment_count'];
            $weekly_stats['total_amount'] += $daily_stats['total_amount'];
            $weekly_stats['daily_breakdown'][$date] = $daily_stats;
            
            // Aggregate methods and gateways
            foreach ($daily_stats['methods'] as $method => $method_data) {
                if (!isset($weekly_stats['methods'][$method])) {
                    $weekly_stats['methods'][$method] = ['count' => 0, 'amount' => 0];
                }
                $weekly_stats['methods'][$method]['count'] += $method_data['count'];
                $weekly_stats['methods'][$method]['amount'] += $method_data['amount'];
            }
            
            foreach ($daily_stats['gateways'] as $gateway => $gateway_data) {
                if (!isset($weekly_stats['gateways'][$gateway])) {
                    $weekly_stats['gateways'][$gateway] = ['count' => 0, 'amount' => 0];
                }
                $weekly_stats['gateways'][$gateway]['count'] += $gateway_data['count'];
                $weekly_stats['gateways'][$gateway]['amount'] += $gateway_data['amount'];
            }
        }
        
        return $weekly_stats;
    }

    /**
     * Calculate monthly payment statistics.
     *
     * @param string $month Month (Y-m format)
     * @return array Monthly statistics
     */
    private function calculate_monthly_payment_statistics($month) {
        $monthly_stats = [
            'payment_count' => 0,
            'total_amount' => 0,
            'methods' => [],
            'gateways' => [],
            'weekly_breakdown' => []
        ];
        
        // Get all days in the month
        $start_date = $month . '-01';
        $end_date = date('Y-m-t', strtotime($start_date));
        
        $current_date = $start_date;
        while ($current_date <= $end_date) {
            $daily_stats = $this->calculate_daily_payment_statistics($current_date);
            
            $monthly_stats['payment_count'] += $daily_stats['payment_count'];
            $monthly_stats['total_amount'] += $daily_stats['total_amount'];
            
            // Aggregate methods and gateways
            foreach ($daily_stats['methods'] as $method => $method_data) {
                if (!isset($monthly_stats['methods'][$method])) {
                    $monthly_stats['methods'][$method] = ['count' => 0, 'amount' => 0];
                }
                $monthly_stats['methods'][$method]['count'] += $method_data['count'];
                $monthly_stats['methods'][$method]['amount'] += $method_data['amount'];
            }
            
            foreach ($daily_stats['gateways'] as $gateway => $gateway_data) {
                if (!isset($monthly_stats['gateways'][$gateway])) {
                    $monthly_stats['gateways'][$gateway] = ['count' => 0, 'amount' => 0];
                }
                $monthly_stats['gateways'][$gateway]['count'] += $gateway_data['count'];
                $monthly_stats['gateways'][$gateway]['amount'] += $gateway_data['amount'];
            }
            
            $current_date = date('Y-m-d', strtotime($current_date . ' +1 day'));
        }
        
        return $monthly_stats;
    }

    /**
     * Cleanup old cache entries.
     */
    private function cleanup_old_cache() {
        // Remove cache entries older than 24 hours
        // This is a simple implementation - could be improved
        if (count($this->history_cache) > 100) {
            $this->history_cache = array_slice($this->history_cache, -50, 50, true);
        }
    }

    /**
     * Update student payment summary.
     *
     * @param int $student_id Student ID
     */
    private function update_student_payment_summary($student_id) {
        $payment_history = get_field('payment_history', $student_id) ?? [];
        
        $summary = [
            'total_paid' => 0,
            'payment_count' => count($payment_history),
            'last_payment_date' => null,
            'payment_methods_used' => []
        ];
        
        foreach ($payment_history as $payment) {
            $summary['total_paid'] += $payment['amount'];
            
            if (!$summary['last_payment_date'] || $payment['date'] > $summary['last_payment_date']) {
                $summary['last_payment_date'] = $payment['date'];
            }
            
            if (!in_array($payment['method'], $summary['payment_methods_used'])) {
                $summary['payment_methods_used'][] = $payment['method'];
            }
        }
        
        update_field('payment_summary', $summary, $student_id);
    }

    /**
     * Update refund statistics.
     *
     * @param array $refund_data Refund data
     */
    private function update_refund_statistics($refund_data) {
        $date_key = date('Y_m_d');
        $stats_key = 'sms_refund_stats_' . $date_key;
        
        $refund_stats = get_option($stats_key, [
            'refund_count' => 0,
            'total_refunded' => 0
        ]);
        
        $refund_stats['refund_count']++;
        $refund_stats['total_refunded'] += $refund_data['amount'];
        
        update_option($stats_key, $refund_stats);
    }

    /**
     * Update dispute statistics.
     *
     * @param array $dispute_data Dispute data
     */
    private function update_dispute_statistics($dispute_data) {
        $date_key = date('Y_m_d');
        $stats_key = 'sms_dispute_stats_' . $date_key;
        
        $dispute_stats = get_option($stats_key, [
            'dispute_count' => 0,
            'open_disputes' => 0
        ]);
        
        $dispute_stats['dispute_count']++;
        if ($dispute_data['status'] === 'open') {
            $dispute_stats['open_disputes']++;
        }
        
        update_option($stats_key, $dispute_stats);
    }

    /**
     * Update invoice completion statistics.
     *
     * @param array $completion_data Completion data
     */
    private function update_invoice_completion_statistics($completion_data) {
        $date_key = date('Y_m_d', strtotime($completion_data['completion_date']));
        $stats_key = 'sms_invoice_completion_stats_' . $date_key;
        
        $completion_stats = get_option($stats_key, [
            'completed_count' => 0,
            'total_amount' => 0,
            'average_payments' => 0
        ]);
        
        $completion_stats['completed_count']++;
        $completion_stats['total_amount'] += $completion_data['total_amount'];
        $completion_stats['average_payments'] = ($completion_stats['average_payments'] * ($completion_stats['completed_count'] - 1) + $completion_data['payment_count']) / $completion_stats['completed_count'];
        
        update_option($stats_key, $completion_stats);
    }
}