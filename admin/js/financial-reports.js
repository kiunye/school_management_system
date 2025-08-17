/**
 * Financial Reports Admin JavaScript
 *
 * @package    School_Management_System
 * @subpackage School_Management_System/admin/js
 */

(function($) {
    'use strict';

    /**
     * Financial Reports Handler
     */
    class FinancialReports {
        constructor() {
            this.init();
        }

        init() {
            this.bindEvents();
            this.initializeCharts();
            this.setupDateRangePresets();
            this.setupFormValidation();
        }

        bindEvents() {
            // Report type change handler
            $('#report_type').on('change', this.handleReportTypeChange.bind(this));
            
            // Date range preset buttons
            $(document).on('click', '.date-preset-btn', this.handleDatePreset.bind(this));
            
            // Export format change
            $('select[name="export_format"]').on('change', this.handleExportFormatChange.bind(this));
            
            // Form submission
            $('.sms-report-form').on('submit', this.handleFormSubmission.bind(this));
            
            // Chart interactions
            $(document).on('click', '.chart-toggle', this.toggleChartType.bind(this));
            
            // Print report
            $(document).on('click', '.print-report', this.printReport.bind(this));
            
            // Refresh report
            $(document).on('click', '.refresh-report', this.refreshReport.bind(this));
        }

        handleReportTypeChange(e) {
            const reportType = $(e.target).val();
            this.updateFormFields(reportType);
            this.showReportDescription(reportType);
        }

        updateFormFields(reportType) {
            // Show/hide relevant filters based on report type
            const $paymentMethodFilter = $('#payment_method').closest('tr');
            const $classFilter = $('#class_filter').closest('tr');
            const $feeTypeFilter = $('#fee_type_filter').closest('tr');

            // Reset visibility
            $paymentMethodFilter.show();
            $classFilter.show();
            $feeTypeFilter.show();

            switch (reportType) {
                case 'expense':
                    $paymentMethodFilter.hide();
                    $feeTypeFilter.hide();
                    break;
                case 'gateway_transactions':
                    $classFilter.hide();
                    $feeTypeFilter.hide();
                    break;
                case 'reconciliation':
                    $classFilter.hide();
                    $feeTypeFilter.hide();
                    break;
            }
        }

        showReportDescription(reportType) {
            const descriptions = {
                'income': 'Analyze income from fees, payments, and other revenue sources with detailed breakdowns and trends.',
                'expense': 'Track school expenses across different categories with spending patterns and budget analysis.',
                'cash_flow': 'Monitor cash inflows and outflows to understand the school\'s financial health over time.',
                'collection_status': 'Review fee collection efficiency, payment rates, and outstanding amounts by various criteria.',
                'outstanding_amounts': 'Identify overdue payments with aging analysis and student-wise breakdowns.',
                'invoice_tracking': 'Monitor invoice lifecycle from creation to payment with processing time analysis.',
                'payment_trends': 'Analyze payment patterns, seasonal trends, and method preferences over time.',
                'gateway_transactions': 'Review payment gateway performance, success rates, and transaction volumes.',
                'reconciliation': 'Compare system records with gateway data to identify discrepancies and ensure accuracy.'
            };

            const description = descriptions[reportType] || '';
            
            // Remove existing description
            $('.report-description').remove();
            
            if (description) {
                const $description = $('<div class="report-description">' + 
                    '<p><strong>About this report:</strong> ' + description + '</p>' +
                    '</div>');
                $('#report_type').closest('td').append($description);
            }
        }

        setupDateRangePresets() {
            const presets = [
                { label: 'This Month', start: moment().startOf('month'), end: moment().endOf('month') },
                { label: 'Last Month', start: moment().subtract(1, 'month').startOf('month'), end: moment().subtract(1, 'month').endOf('month') },
                { label: 'This Quarter', start: moment().startOf('quarter'), end: moment().endOf('quarter') },
                { label: 'Last Quarter', start: moment().subtract(1, 'quarter').startOf('quarter'), end: moment().subtract(1, 'quarter').endOf('quarter') },
                { label: 'This Year', start: moment().startOf('year'), end: moment().endOf('year') },
                { label: 'Last Year', start: moment().subtract(1, 'year').startOf('year'), end: moment().subtract(1, 'year').endOf('year') }
            ];

            const $presetContainer = $('<div class="date-presets"></div>');
            
            presets.forEach(preset => {
                const $btn = $('<button type="button" class="button date-preset-btn" data-start="' + 
                    preset.start.format('YYYY-MM-DD') + '" data-end="' + 
                    preset.end.format('YYYY-MM-DD') + '">' + preset.label + '</button>');
                $presetContainer.append($btn);
            });

            $('#start_date').closest('td').append($presetContainer);
        }

        handleDatePreset(e) {
            e.preventDefault();
            const $btn = $(e.target);
            const startDate = $btn.data('start');
            const endDate = $btn.data('end');

            $('#start_date').val(startDate);
            $('#end_date').val(endDate);

            // Highlight selected preset
            $('.date-preset-btn').removeClass('button-primary').addClass('button-secondary');
            $btn.removeClass('button-secondary').addClass('button-primary');
        }

        setupFormValidation() {
            $('.sms-report-form').on('submit', function(e) {
                const startDate = new Date($('#start_date').val());
                const endDate = new Date($('#end_date').val());

                if (startDate > endDate) {
                    e.preventDefault();
                    alert('Start date must be before end date.');
                    return false;
                }

                // Check if date range is too large (more than 2 years)
                const daysDiff = (endDate - startDate) / (1000 * 60 * 60 * 24);
                if (daysDiff > 730) {
                    if (!confirm('The selected date range is very large and may take a long time to process. Continue?')) {
                        e.preventDefault();
                        return false;
                    }
                }
            });
        }

        handleFormSubmission(e) {
            const $form = $(e.target);
            const $submitBtn = $form.find('input[type="submit"]');
            
            // Show loading state
            $submitBtn.prop('disabled', true).val('Generating Report...');
            
            // Add loading spinner
            $form.append('<div class="sms-report-loading"><div class="spinner is-active"></div><p>Generating report, please wait...</p></div>');
        }

        initializeCharts() {
            if (typeof Chart === 'undefined') {
                console.warn('Chart.js not loaded. Charts will not be displayed.');
                return;
            }

            // Initialize all charts on the page
            $('.sms-chart-container canvas').each((index, canvas) => {
                this.renderChart(canvas);
            });

            // Initialize trend charts
            $('.sms-trend-chart canvas').each((index, canvas) => {
                this.renderTrendChart(canvas);
            });
        }

        renderChart(canvas) {
            const $canvas = $(canvas);
            const chartData = $canvas.data('chart-data');
            
            if (!chartData) return;

            const ctx = canvas.getContext('2d');
            const chartId = canvas.id;

            let chartConfig = {};

            // Determine chart type based on data structure and chart name
            if (chartId.includes('pie')) {
                chartConfig = this.getPieChartConfig(chartData);
            } else if (chartId.includes('bar')) {
                chartConfig = this.getBarChartConfig(chartData);
            } else if (chartId.includes('line')) {
                chartConfig = this.getLineChartConfig(chartData);
            } else {
                chartConfig = this.getDefaultChartConfig(chartData);
            }

            new Chart(ctx, chartConfig);
        }

        renderTrendChart(canvas) {
            const $canvas = $(canvas);
            const trendData = $canvas.closest('.sms-trend-chart').data('chart-data');
            
            if (!trendData) return;

            const ctx = canvas.getContext('2d');
            const chartConfig = this.getLineChartConfig(trendData);

            new Chart(ctx, chartConfig);
        }

        getPieChartConfig(data) {
            return {
                type: 'pie',
                data: {
                    labels: data.labels || Object.keys(data),
                    datasets: [{
                        data: data.values || Object.values(data),
                        backgroundColor: data.colors || this.getDefaultColors(data.labels?.length || Object.keys(data).length),
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 20,
                                usePointStyle: true
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((value / total) * 100).toFixed(1);
                                    return `${label}: KES ${value.toLocaleString()} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            };
        }

        getBarChartConfig(data) {
            return {
                type: 'bar',
                data: {
                    labels: data.labels || Object.keys(data),
                    datasets: [{
                        label: 'Amount (KES)',
                        data: data.values || Object.values(data),
                        backgroundColor: 'rgba(0, 115, 170, 0.8)',
                        borderColor: 'rgba(0, 115, 170, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return `Amount: KES ${context.parsed.y.toLocaleString()}`;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return 'KES ' + value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            };
        }

        getLineChartConfig(data) {
            return {
                type: 'line',
                data: {
                    labels: Object.keys(data),
                    datasets: [{
                        label: 'Amount (KES)',
                        data: Object.values(data),
                        borderColor: 'rgba(0, 115, 170, 1)',
                        backgroundColor: 'rgba(0, 115, 170, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return `Amount: KES ${context.parsed.y.toLocaleString()}`;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return 'KES ' + value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            };
        }

        getDefaultChartConfig(data) {
            // Default to bar chart
            return this.getBarChartConfig(data);
        }

        getDefaultColors(count) {
            const colors = [
                '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', 
                '#9966FF', '#FF9F40', '#FF6384', '#C9CBCF'
            ];
            
            return Array.from({ length: count }, (_, i) => colors[i % colors.length]);
        }

        handleExportFormatChange(e) {
            const format = $(e.target).val();
            const $submitBtn = $(e.target).siblings('input[type="submit"]');
            
            // Update button text based on format
            const formatLabels = {
                'pdf': 'Export as PDF',
                'excel': 'Export as Excel',
                'csv': 'Export as CSV'
            };
            
            $submitBtn.val(formatLabels[format] || 'Export Report');
        }

        toggleChartType(e) {
            e.preventDefault();
            const $btn = $(e.target);
            const $canvas = $btn.closest('.sms-chart-container').find('canvas');
            const currentType = $canvas.data('chart-type') || 'bar';
            
            // Toggle between chart types
            const newType = currentType === 'bar' ? 'pie' : 'bar';
            $canvas.data('chart-type', newType);
            
            // Re-render chart
            this.renderChart($canvas[0]);
            
            // Update button text
            $btn.text(newType === 'bar' ? 'Show Pie Chart' : 'Show Bar Chart');
        }

        printReport() {
            // Hide non-printable elements
            $('.sms-report-generator, .sms-report-export').hide();
            
            // Print
            window.print();
            
            // Restore elements
            $('.sms-report-generator, .sms-report-export').show();
        }

        refreshReport() {
            // Re-submit the form to refresh the report
            $('.sms-report-form').submit();
        }

        // Utility methods
        formatCurrency(amount) {
            return 'KES ' + parseFloat(amount).toLocaleString('en-KE', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        formatPercentage(value) {
            return parseFloat(value).toFixed(1) + '%';
        }

        formatNumber(value) {
            return parseFloat(value).toLocaleString();
        }
    }

    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        // Check if we're on the financial reports page
        if ($('.sms-financial-reports').length > 0) {
            new FinancialReports();
        }
    });

    /**
     * Export for use in other scripts
     */
    window.SMSFinancialReports = FinancialReports;

})(jQuery);

/**
 * Additional utility functions
 */

// Format currency for display
function formatCurrency(amount) {
    return 'KES ' + parseFloat(amount).toLocaleString('en-KE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

// Format percentage for display
function formatPercentage(value) {
    return parseFloat(value).toFixed(1) + '%';
}

// Export report data as JSON
function exportReportData(reportData, filename) {
    const dataStr = JSON.stringify(reportData, null, 2);
    const dataUri = 'data:application/json;charset=utf-8,'+ encodeURIComponent(dataStr);
    
    const exportFileDefaultName = filename || 'financial_report.json';
    
    const linkElement = document.createElement('a');
    linkElement.setAttribute('href', dataUri);
    linkElement.setAttribute('download', exportFileDefaultName);
    linkElement.click();
}

// Print specific section
function printSection(sectionSelector) {
    const printContents = document.querySelector(sectionSelector).innerHTML;
    const originalContents = document.body.innerHTML;
    
    document.body.innerHTML = printContents;
    window.print();
    document.body.innerHTML = originalContents;
    location.reload();
}

// Generate report summary text
function generateReportSummary(reportData) {
    if (!reportData || !reportData.summary) return '';
    
    const summary = reportData.summary;
    const reportType = reportData.metadata?.report_type || 'Financial';
    
    let summaryText = `${reportType.charAt(0).toUpperCase() + reportType.slice(1)} Report Summary:\n\n`;
    
    Object.entries(summary).forEach(([key, value]) => {
        if (typeof value === 'number') {
            const label = key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
            
            if (key.includes('amount') || key.includes('income') || key.includes('expense')) {
                summaryText += `${label}: ${formatCurrency(value)}\n`;
            } else if (key.includes('rate') || key.includes('percentage')) {
                summaryText += `${label}: ${formatPercentage(value)}\n`;
            } else {
                summaryText += `${label}: ${value.toLocaleString()}\n`;
            }
        }
    });
    
    return summaryText;
}