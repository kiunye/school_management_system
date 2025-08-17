/**
 * Dashboard Functionality JavaScript
 * Handles interactive features for role-specific dashboards
 */

(function($) {
    'use strict';

    // Dashboard Manager Object
    var SMSDashboard = {
        
        // Initialize dashboard functionality
        init: function() {
            this.bindEvents();
            this.initializeWidgets();
            this.setupAutoRefresh();
            this.initializeCharts();
        },

        // Bind event handlers
        bindEvents: function() {
            // Quick action buttons
            $(document).on('click', '.sms-action-btn', this.handleQuickAction);
            
            // Stat card hover effects
            $(document).on('mouseenter', '.sms-stat-card', this.animateStatCard);
            
            // Payment method selection
            $(document).on('click', '.payment-method-card', this.handlePaymentMethodSelection);
            
            // Attendance tab switching
            $(document).on('click', '.attendance-tab', this.switchAttendanceTab);
            
            // Dashboard refresh button
            $(document).on('click', '.dashboard-refresh', this.refreshDashboard);
            
            // Notification dismissal
            $(document).on('click', '.notification-dismiss', this.dismissNotification);
            
            // Class card actions
            $(document).on('click', '.class-card-action', this.handleClassAction);
            
            // Child card actions
            $(document).on('click', '.child-card-action', this.handleChildAction);
        },

        // Initialize dashboard widgets
        initializeWidgets: function() {
            this.loadRecentActivity();
            this.loadNotifications();
            this.updateTimeBasedElements();
            this.initializeTooltips();
        },

        // Setup auto-refresh functionality
        setupAutoRefresh: function() {
            var self = this;
            
            // Refresh dashboard data every 5 minutes
            setInterval(function() {
                self.refreshDashboardData();
            }, 300000);
            
            // Update time-based elements every minute
            setInterval(function() {
                self.updateTimeBasedElements();
            }, 60000);
            
            // Refresh notifications every 10 minutes
            setInterval(function() {
                self.loadNotifications();
            }, 600000);
        },

        // Handle quick action button clicks
        handleQuickAction: function(e) {
            var $button = $(this);
            var action = $button.data('action');
            var href = $button.attr('href');
            
            // Add loading state
            $button.addClass('loading');
            $button.find('.dashicons').addClass('spin');
            
            // If it's a direct link, let it proceed normally
            if (href && href !== '#') {
                return true;
            }
            
            e.preventDefault();
            
            // Handle specific actions
            switch(action) {
                case 'mark_attendance':
                    SMSDashboard.openAttendanceModal();
                    break;
                case 'send_sms':
                    SMSDashboard.openSMSModal();
                    break;
                case 'generate_report':
                    SMSDashboard.openReportModal();
                    break;
                default:
                    console.log('Unknown action:', action);
            }
            
            // Remove loading state
            setTimeout(function() {
                $button.removeClass('loading');
                $button.find('.dashicons').removeClass('spin');
            }, 1000);
        },

        // Animate stat cards on hover
        animateStatCard: function() {
            var $card = $(this);
            var $number = $card.find('.stat-number');
            var finalNumber = parseInt($number.text().replace(/,/g, ''));
            
            if (!$card.data('animated')) {
                $card.data('animated', true);
                SMSDashboard.animateNumber($number, 0, finalNumber, 1000);
            }
        },

        // Animate numbers
        animateNumber: function($element, start, end, duration) {
            var startTime = null;
            
            function animate(currentTime) {
                if (startTime === null) startTime = currentTime;
                var timeElapsed = currentTime - startTime;
                var progress = Math.min(timeElapsed / duration, 1);
                
                var currentNumber = Math.floor(progress * (end - start) + start);
                $element.text(currentNumber.toLocaleString());
                
                if (progress < 1) {
                    requestAnimationFrame(animate);
                }
            }
            
            requestAnimationFrame(animate);
        },

        // Handle payment method selection
        handlePaymentMethodSelection: function(e) {
            var $card = $(this);
            var method = $card.data('method');
            var href = $card.find('a').attr('href');
            
            if (href) {
                window.location.href = href;
            }
        },

        // Switch attendance tabs
        switchAttendanceTab: function(e) {
            e.preventDefault();
            
            var $tab = $(this);
            var studentId = $tab.data('student-id');
            
            // Update active tab
            $('.attendance-tab').removeClass('active');
            $tab.addClass('active');
            
            // Update active content
            $('.attendance-content').removeClass('active');
            $('.attendance-content[data-student-id="' + studentId + '"]').addClass('active');
            
            // Load attendance data if not already loaded
            if (!$('.attendance-content[data-student-id="' + studentId + '"]').data('loaded')) {
                SMSDashboard.loadStudentAttendance(studentId);
            }
        },

        // Load recent activity
        loadRecentActivity: function() {
            var $container = $('#sms-recent-activity, #sms-recent-activity-admin, #sms-teacher-recent-activity');
            
            if ($container.length === 0) return;
            
            $.post(ajaxurl, {
                action: 'sms_get_recent_activity',
                nonce: sms_admin_ajax.nonce
            }, function(response) {
                if (response.success) {
                    $container.html(response.data.html);
                } else {
                    $container.html('<p>' + sms_admin_ajax.strings.no_activity + '</p>');
                }
            }).fail(function() {
                $container.html('<p>' + sms_admin_ajax.strings.error + '</p>');
            });
        },

        // Load notifications
        loadNotifications: function() {
            var $container = $('#sms-teacher-notifications, #sms-parent-notices');
            
            if ($container.length === 0) return;
            
            var action = 'sms_get_notifications';
            if ($container.attr('id') === 'sms-parent-notices') {
                action = 'sms_get_parent_notices';
            } else if ($container.attr('id') === 'sms-teacher-notifications') {
                action = 'sms_get_teacher_notifications';
            }
            
            $.post(ajaxurl, {
                action: action,
                nonce: sms_admin_ajax.nonce
            }, function(response) {
                if (response.success) {
                    $container.html(response.data.html);
                } else {
                    $container.html('<p>' + sms_admin_ajax.strings.no_notifications + '</p>');
                }
            });
        },

        // Load student attendance data
        loadStudentAttendance: function(studentId) {
            var $content = $('.attendance-content[data-student-id="' + studentId + '"]');
            
            $content.html('<div class="loading-spinner"><span class="dashicons dashicons-update spin"></span> Loading...</div>');
            
            $.post(ajaxurl, {
                action: 'sms_get_student_attendance',
                student_id: studentId,
                nonce: sms_admin_ajax.nonce
            }, function(response) {
                if (response.success) {
                    $content.html(response.data.html);
                    $content.data('loaded', true);
                } else {
                    $content.html('<p>Unable to load attendance data.</p>');
                }
            });
        },

        // Refresh dashboard data
        refreshDashboardData: function() {
            var dashboardType = 'admin';
            
            if ($('.sms-teacher-dashboard').length) {
                dashboardType = 'teacher';
            } else if ($('.sms-parent-dashboard').length) {
                dashboardType = 'parent';
            }
            
            $.post(ajaxurl, {
                action: 'sms_get_dashboard_data',
                dashboard_type: dashboardType,
                nonce: sms_admin_ajax.nonce
            }, function(response) {
                if (response.success) {
                    SMSDashboard.updateDashboardElements(response.data);
                }
            });
        },

        // Update dashboard elements with fresh data
        updateDashboardElements: function(data) {
            // Update stat numbers
            if (data.system_stats) {
                $('.sms-stat-card.students .stat-number').text(data.system_stats.total_students.toLocaleString());
                $('.sms-stat-card.teachers .stat-number').text(data.system_stats.total_teachers.toLocaleString());
                $('.sms-stat-card.parents .stat-number').text(data.system_stats.total_parents.toLocaleString());
                $('.sms-stat-card.classes .stat-number').text(data.system_stats.total_classes.toLocaleString());
            }
            
            // Update financial data
            if (data.financial_stats) {
                $('.sms-financial-card.revenue .financial-amount').text('KES ' + data.financial_stats.total_revenue_month.toLocaleString());
                $('.sms-financial-card.outstanding .financial-amount').text('KES ' + data.financial_stats.outstanding_fees.toLocaleString());
                $('.sms-financial-card.success-rate .financial-amount').text(data.financial_stats.payment_success_rate + '%');
            }
            
            // Show update indicator
            this.showUpdateIndicator();
        },

        // Show update indicator
        showUpdateIndicator: function() {
            var $indicator = $('<div class="update-indicator">Updated</div>');
            $indicator.css({
                position: 'fixed',
                top: '32px',
                right: '20px',
                background: '#00a32a',
                color: 'white',
                padding: '8px 16px',
                borderRadius: '4px',
                zIndex: 100000,
                fontSize: '12px'
            });
            
            $('body').append($indicator);
            
            setTimeout(function() {
                $indicator.fadeOut(function() {
                    $indicator.remove();
                });
            }, 3000);
        },

        // Update time-based elements
        updateTimeBasedElements: function() {
            // Update current time indicators
            var currentTime = new Date();
            var timeString = currentTime.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
            
            $('.current-time').text(timeString);
            
            // Highlight current schedule items
            this.highlightCurrentSchedule();
            
            // Update relative time stamps
            this.updateRelativeTimestamps();
        },

        // Highlight current schedule items
        highlightCurrentSchedule: function() {
            var currentTime = new Date();
            var currentHour = currentTime.getHours();
            var currentMinute = currentTime.getMinutes();
            var currentTimeMinutes = currentHour * 60 + currentMinute;
            
            $('.schedule-item').each(function() {
                var $item = $(this);
                var timeText = $item.find('.schedule-time').text();
                var times = timeText.split(' - ');
                
                if (times.length === 2) {
                    var startTime = SMSDashboard.parseTime(times[0]);
                    var endTime = SMSDashboard.parseTime(times[1]);
                    
                    if (currentTimeMinutes >= startTime && currentTimeMinutes <= endTime) {
                        $item.addClass('current');
                    } else {
                        $item.removeClass('current');
                    }
                }
            });
        },

        // Parse time string to minutes
        parseTime: function(timeString) {
            var parts = timeString.trim().split(':');
            return parseInt(parts[0]) * 60 + parseInt(parts[1]);
        },

        // Update relative timestamps
        updateRelativeTimestamps: function() {
            $('.relative-time').each(function() {
                var $element = $(this);
                var timestamp = $element.data('timestamp');
                
                if (timestamp) {
                    var relativeTime = SMSDashboard.getRelativeTime(timestamp);
                    $element.text(relativeTime);
                }
            });
        },

        // Get relative time string
        getRelativeTime: function(timestamp) {
            var now = new Date().getTime();
            var time = new Date(timestamp).getTime();
            var diff = now - time;
            
            var seconds = Math.floor(diff / 1000);
            var minutes = Math.floor(seconds / 60);
            var hours = Math.floor(minutes / 60);
            var days = Math.floor(hours / 24);
            
            if (days > 0) {
                return days + ' day' + (days > 1 ? 's' : '') + ' ago';
            } else if (hours > 0) {
                return hours + ' hour' + (hours > 1 ? 's' : '') + ' ago';
            } else if (minutes > 0) {
                return minutes + ' minute' + (minutes > 1 ? 's' : '') + ' ago';
            } else {
                return 'Just now';
            }
        },

        // Initialize tooltips
        initializeTooltips: function() {
            // Add tooltips to elements with title attributes
            $('[title]').each(function() {
                var $element = $(this);
                var title = $element.attr('title');
                
                $element.removeAttr('title').on('mouseenter', function(e) {
                    var tooltip = $('<div class="sms-tooltip">' + title + '</div>');
                    tooltip.css({
                        position: 'absolute',
                        background: '#333',
                        color: 'white',
                        padding: '5px 10px',
                        borderRadius: '4px',
                        fontSize: '12px',
                        zIndex: 10000,
                        whiteSpace: 'nowrap'
                    });
                    
                    $('body').append(tooltip);
                    
                    var offset = $element.offset();
                    tooltip.css({
                        top: offset.top - tooltip.outerHeight() - 5,
                        left: offset.left + ($element.outerWidth() / 2) - (tooltip.outerWidth() / 2)
                    });
                    
                }).on('mouseleave', function() {
                    $('.sms-tooltip').remove();
                });
            });
        },

        // Initialize charts
        initializeCharts: function() {
            if (typeof Chart === 'undefined') {
                return;
            }
            
            // Initialize attendance rate charts
            $('.attendance-chart').each(function() {
                var canvas = this;
                var rate = $(canvas).data('rate');
                
                new Chart(canvas, {
                    type: 'doughnut',
                    data: {
                        datasets: [{
                            data: [rate, 100 - rate],
                            backgroundColor: ['#00a32a', '#f0f0f0'],
                            borderWidth: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            }
                        }
                    }
                });
            });
            
            // Initialize financial charts
            $('.financial-chart').each(function() {
                var canvas = this;
                var data = $(canvas).data('chart-data');
                
                if (data) {
                    SMSDashboard.renderFinancialChart(canvas, data);
                }
            });
        },

        // Render financial chart
        renderFinancialChart: function(canvas, data) {
            new Chart(canvas, {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Revenue',
                        data: data.values,
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
                    plugins: {
                        legend: {
                            display: false
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
            });
        },

        // Refresh entire dashboard
        refreshDashboard: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            $button.addClass('loading');
            $button.find('.dashicons').addClass('spin');
            
            // Reload all dashboard components
            SMSDashboard.loadRecentActivity();
            SMSDashboard.loadNotifications();
            SMSDashboard.refreshDashboardData();
            
            setTimeout(function() {
                $button.removeClass('loading');
                $button.find('.dashicons').removeClass('spin');
                SMSDashboard.showUpdateIndicator();
            }, 2000);
        },

        // Dismiss notification
        dismissNotification: function(e) {
            e.preventDefault();
            
            var $notification = $(this).closest('.notification-item');
            var notificationId = $notification.data('notification-id');
            
            $notification.fadeOut(function() {
                $notification.remove();
            });
            
            // Send AJAX request to mark as dismissed
            $.post(ajaxurl, {
                action: 'sms_dismiss_notification',
                notification_id: notificationId,
                nonce: sms_admin_ajax.nonce
            });
        },

        // Handle class card actions
        handleClassAction: function(e) {
            var $button = $(this);
            var action = $button.data('action');
            var classId = $button.data('class-id');
            
            switch(action) {
                case 'mark_attendance':
                    window.location.href = sms_admin_ajax.admin_url + 'admin.php?page=sms-attendance-manager&class_id=' + classId;
                    break;
                case 'view_students':
                    window.location.href = sms_admin_ajax.admin_url + 'edit.php?post_type=sms_students&class_id=' + classId;
                    break;
            }
        },

        // Handle child card actions
        handleChildAction: function(e) {
            var $button = $(this);
            var action = $button.data('action');
            var studentId = $button.data('student-id');
            
            switch(action) {
                case 'view_details':
                    window.location.href = sms_admin_ajax.admin_url + 'admin.php?page=sms-parent-dashboard&view=child&student_id=' + studentId;
                    break;
                case 'pay_fees':
                    window.location.href = sms_admin_ajax.admin_url + 'admin.php?page=sms-parent-payments&student_id=' + studentId;
                    break;
            }
        },

        // Open attendance modal
        openAttendanceModal: function() {
            // Implementation for attendance modal
            console.log('Opening attendance modal');
        },

        // Open SMS modal
        openSMSModal: function() {
            // Implementation for SMS modal
            console.log('Opening SMS modal');
        },

        // Open report modal
        openReportModal: function() {
            // Implementation for report modal
            console.log('Opening report modal');
        }
    };

    // Initialize dashboard when document is ready
    $(document).ready(function() {
        SMSDashboard.init();
    });

    // Make SMSDashboard globally available
    window.SMSDashboard = SMSDashboard;

})(jQuery);