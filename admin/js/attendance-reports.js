/**
 * Attendance Reports JavaScript
 * 
 * Handles the interactive attendance reports and analytics interface
 */

jQuery(document).ready(function($) {
    'use strict';

    let attendanceTrendChart = null;
    let classComparisonChart = null;
    let currentReportData = null;

    // Initialize datepickers
    $('#start-date, #end-date').datepicker({
        dateFormat: 'yy-mm-dd',
        maxDate: 0, // Don't allow future dates
        changeMonth: true,
        changeYear: true,
        onSelect: function() {
            updateDateRangeVisibility();
        }
    });

    // Handle report type changes
    $('#report-type').on('change', function() {
        updateDateRangeVisibility();
        setDefaultDateRange();
    });

    // Generate report
    $('#generate-report').on('click', function() {
        generateReport();
    });

    // Export report
    $('#export-report').on('click', function() {
        exportReport();
    });

    // Initialize with default settings
    updateDateRangeVisibility();
    setDefaultDateRange();

    /**
     * Update date range visibility based on report type
     */
    function updateDateRangeVisibility() {
        const reportType = $('#report-type').val();
        const $dateRangeFilters = $('#date-range-filters');
        
        if (reportType === 'custom') {
            $dateRangeFilters.show();
        } else {
            $dateRangeFilters.show(); // Always show for now, but could hide for preset ranges
        }
    }

    /**
     * Set default date range based on report type
     */
    function setDefaultDateRange() {
        const reportType = $('#report-type').val();
        const today = new Date();
        let startDate, endDate;

        switch (reportType) {
            case 'daily':
                startDate = endDate = formatDate(today);
                break;
            case 'weekly':
                const weekStart = new Date(today);
                weekStart.setDate(today.getDate() - today.getDay() + 1); // Monday
                const weekEnd = new Date(weekStart);
                weekEnd.setDate(weekStart.getDate() + 6); // Sunday
                startDate = formatDate(weekStart);
                endDate = formatDate(weekEnd);
                break;
            case 'monthly':
                startDate = formatDate(new Date(today.getFullYear(), today.getMonth(), 1));
                endDate = formatDate(new Date(today.getFullYear(), today.getMonth() + 1, 0));
                break;
            case 'term':
                // Assume term is 3 months
                startDate = formatDate(new Date(today.getFullYear(), today.getMonth() - 2, 1));
                endDate = formatDate(today);
                break;
            default:
                // Keep current values
                return;
        }

        $('#start-date').val(startDate);
        $('#end-date').val(endDate);
    }

    /**
     * Generate attendance report
     */
    function generateReport() {
        const formData = {
            action: 'sms_generate_attendance_report',
            nonce: smsReports.nonce,
            report_type: $('#report-type').val(),
            classes: $('#report-classes').val() || ['all'],
            start_date: $('#start-date').val(),
            end_date: $('#end-date').val()
        };

        // Validate inputs
        if (!formData.start_date || !formData.end_date) {
            alert(smsReports.strings.selectDateRange);
            return;
        }

        if (new Date(formData.start_date) > new Date(formData.end_date)) {
            alert('Start date must be before end date.');
            return;
        }

        showLoading(true);
        $('#generate-report').prop('disabled', true).text(smsReports.strings.generating);

        $.post(smsReports.ajaxurl, formData)
        .done(function(response) {
            if (response.success) {
                currentReportData = response.data;
                displayReport(response.data);
                displayAnalytics(response.data.analytics_data);
                $('#export-report').prop('disabled', false);
            } else {
                alert(response.data || smsReports.strings.error);
            }
        })
        .fail(function() {
            alert(smsReports.strings.error);
        })
        .always(function() {
            showLoading(false);
            $('#generate-report').prop('disabled', false).text('Generate Report');
        });
    }

    /**
     * Display report results
     */
    function displayReport(data) {
        const reportData = data.report_data;
        const reportMeta = data.report_meta;

        // Update report header
        $('#report-title').text(`Attendance Report - ${reportMeta.type.charAt(0).toUpperCase() + reportMeta.type.slice(1)}`);
        $('#report-period').text(`Period: ${reportMeta.period}`);
        $('#report-generated').text(`Generated: ${formatDateTime(reportMeta.generated)}`);

        // Clear and populate table
        const $tableBody = $('#report-table-body');
        $tableBody.empty();

        if (reportData.students.length === 0) {
            $tableBody.append(`
                <tr>
                    <td colspan="8" class="no-data">${smsReports.strings.noData}</td>
                </tr>
            `);
        } else {
            reportData.students.forEach(function(student) {
                const statusClass = getStatusClass(student.status);
                const row = `
                    <tr class="student-row ${statusClass}">
                        <td>
                            <strong>${student.student_name}</strong><br>
                            <small>#${student.admission_number}</small>
                        </td>
                        <td>${student.class_name} (${student.class_code})</td>
                        <td class="number">${student.present_days}</td>
                        <td class="number">${student.absent_days}</td>
                        <td class="number">${student.late_days}</td>
                        <td class="number">${student.excused_days}</td>
                        <td class="number">
                            <strong>${student.attendance_rate}%</strong>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: ${student.attendance_rate}%"></div>
                            </div>
                        </td>
                        <td>
                            <span class="status-badge ${statusClass}">${formatStatus(student.status)}</span>
                        </td>
                    </tr>
                `;
                $tableBody.append(row);
            });
        }

        // Show report results
        $('#report-results').show();
        
        // Scroll to results
        $('html, body').animate({
            scrollTop: $('#report-results').offset().top - 50
        }, 500);
    }

    /**
     * Display analytics dashboard
     */
    function displayAnalytics(analyticsData) {
        // Update summary cards
        $('#overall-attendance').text(analyticsData.overall_attendance + '%');
        $('#total-students').text(analyticsData.total_students);
        $('#school-days').text(analyticsData.school_days);
        $('#chronic-absentees').text(analyticsData.chronic_absentees);

        // Update charts
        updateAttendanceTrendChart(analyticsData.trend_data);
        updateClassComparisonChart(analyticsData.class_comparison);

        // Show analytics dashboard
        $('#analytics-dashboard').show();
    }

    /**
     * Update attendance trend chart
     */
    function updateAttendanceTrendChart(trendData) {
        const ctx = document.getElementById('attendance-trend-chart').getContext('2d');
        
        if (attendanceTrendChart) {
            attendanceTrendChart.destroy();
        }

        const labels = trendData.map(item => item.formatted_date);
        const data = trendData.map(item => item.attendance_rate);

        attendanceTrendChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Attendance Rate (%)',
                    data: data,
                    borderColor: '#0073aa',
                    backgroundColor: 'rgba(0, 115, 170, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        ticks: {
                            callback: function(value) {
                                return value + '%';
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Attendance: ' + context.parsed.y + '%';
                            }
                        }
                    }
                }
            }
        });
    }

    /**
     * Update class comparison chart
     */
    function updateClassComparisonChart(classData) {
        const ctx = document.getElementById('class-comparison-chart').getContext('2d');
        
        if (classComparisonChart) {
            classComparisonChart.destroy();
        }

        const labels = classData.map(item => item.class_name);
        const data = classData.map(item => item.attendance_rate);
        const colors = data.map(rate => {
            if (rate >= 90) return '#46b450';
            if (rate >= 75) return '#ffb900';
            return '#dc3232';
        });

        classComparisonChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Attendance Rate (%)',
                    data: data,
                    backgroundColor: colors,
                    borderColor: colors,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        ticks: {
                            callback: function(value) {
                                return value + '%';
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const classInfo = classData[context.dataIndex];
                                return [
                                    'Attendance: ' + context.parsed.y + '%',
                                    'Students: ' + classInfo.student_count
                                ];
                            }
                        }
                    }
                }
            }
        });
    }

    /**
     * Export report to CSV
     */
    function exportReport() {
        if (!currentReportData) {
            alert('No report data to export.');
            return;
        }

        const $exportButton = $('#export-report');
        const originalText = $exportButton.text();
        $exportButton.prop('disabled', true).text(smsReports.strings.exporting);

        $.post(smsReports.ajaxurl, {
            action: 'sms_export_attendance_report',
            nonce: smsReports.nonce,
            report_data: JSON.stringify(currentReportData.report_data)
        })
        .done(function(response) {
            if (response.success) {
                // Create temporary download link
                const link = document.createElement('a');
                link.href = response.data.download_url;
                link.download = response.data.filename;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                
                showNotification('Report exported successfully!', 'success');
            } else {
                alert(response.data || 'Export failed.');
            }
        })
        .fail(function() {
            alert('Export failed.');
        })
        .always(function() {
            $exportButton.prop('disabled', false).text(originalText);
        });
    }

    /**
     * Show loading indicator
     */
    function showLoading(show) {
        if (show) {
            $('#loading-indicator').show();
            $('#report-results, #analytics-dashboard').hide();
        } else {
            $('#loading-indicator').hide();
        }
    }

    /**
     * Show notification message
     */
    function showNotification(message, type) {
        // Remove existing notifications
        $('.sms-notification').remove();

        const $notification = $(`
            <div class="sms-notification sms-notification-${type}">
                <span class="message">${message}</span>
                <button type="button" class="notice-dismiss">
                    <span class="screen-reader-text">Dismiss this notice.</span>
                </button>
            </div>
        `);

        $('.wrap h1').after($notification);

        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            $notification.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);

        // Manual dismiss
        $notification.find('.notice-dismiss').on('click', function() {
            $notification.fadeOut(function() {
                $(this).remove();
            });
        });
    }

    /**
     * Get status class for styling
     */
    function getStatusClass(status) {
        const statusClasses = {
            'excellent': 'status-excellent',
            'good': 'status-good',
            'satisfactory': 'status-satisfactory',
            'needs_improvement': 'status-needs-improvement',
            'poor': 'status-poor'
        };
        return statusClasses[status] || '';
    }

    /**
     * Format status for display
     */
    function formatStatus(status) {
        const statusLabels = {
            'excellent': 'Excellent',
            'good': 'Good',
            'satisfactory': 'Satisfactory',
            'needs_improvement': 'Needs Improvement',
            'poor': 'Poor'
        };
        return statusLabels[status] || status;
    }

    /**
     * Format date for input
     */
    function formatDate(date) {
        return date.toISOString().split('T')[0];
    }

    /**
     * Format datetime for display
     */
    function formatDateTime(dateTimeString) {
        const date = new Date(dateTimeString);
        return date.toLocaleString('en-GB', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    // Handle window resize for charts
    $(window).on('resize', function() {
        if (attendanceTrendChart) {
            attendanceTrendChart.resize();
        }
        if (classComparisonChart) {
            classComparisonChart.resize();
        }
    });
});