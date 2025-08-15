/**
 * Attendance Manager JavaScript
 * 
 * Handles the interactive attendance marking interface
 */

jQuery(document).ready(function($) {
    'use strict';

    // Initialize datepicker
    $('#attendance-date').datepicker({
        dateFormat: 'yy-mm-dd',
        maxDate: 0, // Don't allow future dates
        changeMonth: true,
        changeYear: true
    });

    // Load attendance data when class and date are selected
    $('#load-attendance').on('click', function() {
        loadAttendanceData();
    });

    // Save attendance
    $('#save-attendance').on('click', function() {
        saveAttendance();
    });

    // Bulk mark all present
    $('#mark-all-present').on('click', function() {
        if (confirm(smsAttendance.strings.confirmMarkAll.replace('{status}', smsAttendance.strings.present))) {
            bulkMarkAttendance('present');
        }
    });

    // Bulk mark all absent
    $('#mark-all-absent').on('click', function() {
        if (confirm(smsAttendance.strings.confirmMarkAll.replace('{status}', smsAttendance.strings.absent))) {
            bulkMarkAttendance('absent');
        }
    });

    // Handle status changes
    $(document).on('change', '.attendance-status-select', function() {
        updateAttendanceCounts();
        
        // Add visual feedback
        const $row = $(this).closest('.student-row');
        const status = $(this).val();
        
        $row.removeClass('status-present status-absent status-late status-excused');
        $row.addClass('status-' + status);
    });

    // Handle notes changes
    $(document).on('input', '.attendance-notes-input', function() {
        // Auto-save notes after a delay
        clearTimeout($(this).data('timeout'));
        $(this).data('timeout', setTimeout(function() {
            // Could implement auto-save here if needed
        }, 1000));
    });

    /**
     * Load attendance data for selected class and date
     */
    function loadAttendanceData() {
        const classId = $('#attendance-class').val();
        const attendanceDate = $('#attendance-date').val();

        if (!classId) {
            alert(smsAttendance.strings.selectClass);
            return;
        }

        if (!attendanceDate) {
            alert(smsAttendance.strings.selectDate);
            return;
        }

        showLoading(true);

        $.post(smsAttendance.ajaxurl, {
            action: 'sms_get_attendance_data',
            nonce: smsAttendance.nonce,
            class_id: classId,
            attendance_date: attendanceDate
        })
        .done(function(response) {
            if (response.success) {
                displayAttendanceData(response.data);
            } else {
                alert(response.data || smsAttendance.strings.error);
            }
        })
        .fail(function() {
            alert(smsAttendance.strings.error);
        })
        .always(function() {
            showLoading(false);
        });
    }

    /**
     * Display attendance data in the interface
     */
    function displayAttendanceData(data) {
        // Update title
        $('#attendance-title').text(
            'Mark Attendance - ' + data.class_name + ' (' + data.class_code + ') - ' + 
            formatDate(data.attendance_date)
        );

        // Clear and populate students list
        const $studentsList = $('#students-list');
        $studentsList.empty();

        if (data.students.length === 0) {
            $studentsList.html('<div class="no-students">No students enrolled in this class.</div>');
            $('#attendance-marking-section').show();
            return;
        }

        data.students.forEach(function(student) {
            const studentRow = createStudentRow(student);
            $studentsList.append(studentRow);
        });

        // Update counts
        updateAttendanceCounts();

        // Show the attendance marking section
        $('#attendance-marking-section').show();

        // Scroll to the attendance section
        $('html, body').animate({
            scrollTop: $('#attendance-marking-section').offset().top - 50
        }, 500);
    }

    /**
     * Create a student row for attendance marking
     */
    function createStudentRow(student) {
        const statusOptions = [
            { value: 'present', label: smsAttendance.strings.present },
            { value: 'absent', label: smsAttendance.strings.absent },
            { value: 'late', label: smsAttendance.strings.late },
            { value: 'excused', label: smsAttendance.strings.excused }
        ];

        let optionsHtml = '';
        statusOptions.forEach(function(option) {
            const selected = option.value === student.status ? 'selected' : '';
            optionsHtml += `<option value="${option.value}" ${selected}>${option.label}</option>`;
        });

        const markedTime = student.marked_time ? 
            '<small class="marked-time">Last updated: ' + formatDateTime(student.marked_time) + '</small>' : '';

        return $(`
            <div class="student-row status-${student.status}" data-student-id="${student.id}">
                <div class="student-info">
                    <div class="student-name">${student.full_name}</div>
                    <div class="student-details">
                        <span class="admission-number">#${student.admission_number}</span>
                        ${markedTime}
                    </div>
                </div>
                <div class="attendance-status">
                    <select class="attendance-status-select" data-student-id="${student.id}">
                        ${optionsHtml}
                    </select>
                </div>
                <div class="attendance-notes">
                    <input type="text" class="attendance-notes-input" 
                           data-student-id="${student.id}" 
                           value="${student.notes}" 
                           placeholder="Add notes...">
                </div>
            </div>
        `);
    }

    /**
     * Update attendance counts
     */
    function updateAttendanceCounts() {
        const counts = {
            total: 0,
            present: 0,
            absent: 0,
            late: 0,
            excused: 0
        };

        $('.student-row').each(function() {
            counts.total++;
            const status = $(this).find('.attendance-status-select').val();
            if (counts.hasOwnProperty(status)) {
                counts[status]++;
            }
        });

        // Update display
        $('#total-students').text(counts.total);
        $('#present-count').text(counts.present);
        $('#absent-count').text(counts.absent);
        $('#late-count').text(counts.late);
        $('#excused-count').text(counts.excused);

        // Update progress indicators
        updateProgressIndicators(counts);
    }

    /**
     * Update visual progress indicators
     */
    function updateProgressIndicators(counts) {
        if (counts.total === 0) return;

        const presentPercentage = (counts.present / counts.total) * 100;
        const absentPercentage = (counts.absent / counts.total) * 100;

        // Add visual feedback based on attendance rates
        const $summary = $('.attendance-summary');
        $summary.removeClass('high-attendance medium-attendance low-attendance');

        if (presentPercentage >= 90) {
            $summary.addClass('high-attendance');
        } else if (presentPercentage >= 75) {
            $summary.addClass('medium-attendance');
        } else {
            $summary.addClass('low-attendance');
        }
    }

    /**
     * Bulk mark attendance status
     */
    function bulkMarkAttendance(status) {
        $('.attendance-status-select').val(status).trigger('change');
        updateAttendanceCounts();
        
        // Show feedback
        showNotification(`All students marked as ${status}`, 'success');
    }

    /**
     * Save attendance data
     */
    function saveAttendance() {
        const classId = $('#attendance-class').val();
        const attendanceDate = $('#attendance-date').val();

        if (!classId || !attendanceDate) {
            alert(smsAttendance.strings.selectClass);
            return;
        }

        // Collect attendance data
        const attendanceData = [];
        $('.student-row').each(function() {
            const $row = $(this);
            const studentId = $row.data('student-id');
            const status = $row.find('.attendance-status-select').val();
            const notes = $row.find('.attendance-notes-input').val();

            attendanceData.push({
                student_id: studentId,
                status: status,
                notes: notes
            });
        });

        if (attendanceData.length === 0) {
            alert('No attendance data to save.');
            return;
        }

        // Show saving state
        const $saveButton = $('#save-attendance');
        const originalText = $saveButton.text();
        $saveButton.text(smsAttendance.strings.saving).prop('disabled', true);

        $.post(smsAttendance.ajaxurl, {
            action: 'sms_mark_attendance',
            nonce: smsAttendance.nonce,
            class_id: classId,
            attendance_date: attendanceDate,
            attendance_data: JSON.stringify(attendanceData)
        })
        .done(function(response) {
            if (response.success) {
                showNotification(smsAttendance.strings.saved, 'success');
                
                // Update the interface with saved data
                if (response.data.counts) {
                    updateSavedCounts(response.data.counts);
                }
                
                // Add saved indicator to rows
                $('.student-row').addClass('saved');
                setTimeout(function() {
                    $('.student-row').removeClass('saved');
                }, 2000);
                
            } else {
                showNotification(response.data || smsAttendance.strings.error, 'error');
            }
        })
        .fail(function() {
            showNotification(smsAttendance.strings.error, 'error');
        })
        .always(function() {
            $saveButton.text(originalText).prop('disabled', false);
        });
    }

    /**
     * Update saved counts display
     */
    function updateSavedCounts(counts) {
        $('#total-students').text(counts.total);
        $('#present-count').text(counts.present);
        $('#absent-count').text(counts.absent);
        $('#late-count').text(counts.late);
        $('#excused-count').text(counts.excused);
    }

    /**
     * Show loading indicator
     */
    function showLoading(show) {
        if (show) {
            $('#loading-indicator').show();
            $('#attendance-marking-section').hide();
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
     * Format date for display
     */
    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-GB', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric'
        });
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

    // Keyboard shortcuts
    $(document).on('keydown', function(e) {
        // Ctrl+S to save
        if (e.ctrlKey && e.which === 83) {
            e.preventDefault();
            if ($('#attendance-marking-section').is(':visible')) {
                saveAttendance();
            }
        }
        
        // Ctrl+P to mark all present
        if (e.ctrlKey && e.which === 80) {
            e.preventDefault();
            if ($('#attendance-marking-section').is(':visible')) {
                bulkMarkAttendance('present');
            }
        }
        
        // Ctrl+A to mark all absent
        if (e.ctrlKey && e.which === 65 && e.shiftKey) {
            e.preventDefault();
            if ($('#attendance-marking-section').is(':visible')) {
                bulkMarkAttendance('absent');
            }
        }
    });

    // Auto-save functionality (optional)
    let autoSaveTimeout;
    $(document).on('change', '.attendance-status-select, .attendance-notes-input', function() {
        clearTimeout(autoSaveTimeout);
        autoSaveTimeout = setTimeout(function() {
            // Could implement auto-save here
            // saveAttendance();
        }, 30000); // Auto-save after 30 seconds of inactivity
    });
});