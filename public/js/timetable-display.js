/**
 * Frontend Timetable Display JavaScript
 *
 * @package SchoolManagementSystem
 * @subpackage Public/JS
 */

(function($) {
    'use strict';

    // Initialize when document is ready
    $(document).ready(function() {
        initializeTimetableDisplays();
    });

    /**
     * Initialize all timetable displays on the page
     */
    function initializeTimetableDisplays() {
        // Initialize class timetable displays
        $('.sms-timetable-display').each(function() {
            initializeClassTimetableDisplay($(this));
        });

        // Initialize teacher timetable displays
        $('.sms-teacher-timetable-display').each(function() {
            initializeTeacherTimetableDisplay($(this));
        });

        // Initialize student timetable displays
        $('.sms-student-timetable-display').each(function() {
            initializeStudentTimetableDisplay($(this));
        });
    }

    /**
     * Initialize class timetable display
     */
    function initializeClassTimetableDisplay($container) {
        const classId = $container.data('class-id');
        const academicYear = $container.data('academic-year');
        const term = $container.data('term');
        const format = $container.data('format') || 'grid';
        const showControls = $container.data('show-controls') === 'true';

        if (!classId) {
            showError($container, 'Class ID is required');
            return;
        }

        // Bind events if controls are shown
        if (showControls) {
            bindTimetableControls($container, 'class', classId);
        }

        // Load timetable data
        loadTimetableData($container, {
            view_type: 'class',
            entity_id: classId,
            academic_year: academicYear,
            term: term,
            display_format: format
        });
    }

    /**
     * Initialize teacher timetable display
     */
    function initializeTeacherTimetableDisplay($container) {
        const teacherId = $container.data('teacher-id');
        const academicYear = $container.data('academic-year');
        const term = $container.data('term');
        const format = $container.data('format') || 'grid';
        const showControls = $container.data('show-controls') === 'true';

        if (!teacherId) {
            showError($container, 'Teacher ID is required');
            return;
        }

        // Bind events if controls are shown
        if (showControls) {
            bindTimetableControls($container, 'teacher', teacherId);
        }

        // Load timetable data
        loadTimetableData($container, {
            view_type: 'teacher',
            entity_id: teacherId,
            academic_year: academicYear,
            term: term,
            display_format: format
        });
    }

    /**
     * Initialize student timetable display
     */
    function initializeStudentTimetableDisplay($container) {
        const studentId = $container.data('student-id');
        const classId = $container.data('class-id');
        const academicYear = $container.data('academic-year');
        const term = $container.data('term');
        const format = $container.data('format') || 'grid';
        const showControls = $container.data('show-controls') === 'true';

        if (!classId) {
            showError($container, 'Class ID is required');
            return;
        }

        // Bind events if controls are shown
        if (showControls) {
            bindTimetableControls($container, 'student', classId);
        }

        // Load timetable data (use class view for students)
        loadTimetableData($container, {
            view_type: 'class',
            entity_id: classId,
            academic_year: academicYear,
            term: term,
            display_format: format
        });
    }

    /**
     * Bind timetable control events
     */
    function bindTimetableControls($container, viewType, entityId) {
        // Format selector
        $container.find('.format-select').on('change', function() {
            const newFormat = $(this).val();
            $container.data('format', newFormat);
            
            // Reload timetable with new format
            loadTimetableData($container, {
                view_type: viewType,
                entity_id: entityId,
                academic_year: $container.data('academic-year'),
                term: $container.data('term'),
                display_format: newFormat
            });
        });

        // Export PDF button
        $container.find('.export-pdf-btn').on('click', function() {
            exportTimetable($container, viewType, entityId, 'pdf');
        });

        // Export CSV button
        $container.find('.export-csv-btn').on('click', function() {
            exportTimetable($container, viewType, entityId, 'csv');
        });
    }

    /**
     * Load timetable data via AJAX
     */
    function loadTimetableData($container, params) {
        const $content = $container.find('.timetable-content');
        
        // Show loading state
        showLoading($content);

        $.ajax({
            url: sms_timetable_display.ajax_url,
            type: 'POST',
            data: {
                action: 'sms_get_timetable_display',
                nonce: sms_timetable_display.nonce,
                ...params
            },
            success: function(response) {
                if (response.success) {
                    $content.html(response.data.html);
                    
                    // Store data for export
                    $container.data('timetable-data', response.data.data);
                    
                    // Initialize interactive features
                    initializeInteractiveFeatures($container);
                } else {
                    showError($content, response.data || sms_timetable_display.strings.error);
                }
            },
            error: function() {
                showError($content, sms_timetable_display.strings.error);
            }
        });
    }

    /**
     * Export timetable
     */
    function exportTimetable($container, viewType, entityId, format) {
        const timetableData = $container.data('timetable-data');
        
        if (!timetableData) {
            showNotification('No timetable data to export', 'error');
            return;
        }

        // Show loading on export button
        const $exportBtn = $container.find(`.export-${format}-btn`);
        const originalText = $exportBtn.text();
        $exportBtn.text(sms_timetable_display.strings.loading).prop('disabled', true);

        $.ajax({
            url: sms_timetable_display.ajax_url,
            type: 'POST',
            data: {
                action: `sms_export_timetable_${format}`,
                nonce: sms_timetable_display.nonce,
                view_type: viewType,
                entity_id: entityId,
                timetable_id: timetableData.timetable_id || 0
            },
            success: function(response) {
                if (response.success) {
                    // Create download link
                    const link = document.createElement('a');
                    link.href = response.data.download_url;
                    link.download = response.data.filename;
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    
                    showNotification(response.data.message, 'success');
                } else {
                    showNotification(response.data || 'Export failed', 'error');
                }
            },
            error: function() {
                showNotification('Export failed. Please try again.', 'error');
            },
            complete: function() {
                $exportBtn.text(originalText).prop('disabled', false);
            }
        });
    }

    /**
     * Initialize interactive features
     */
    function initializeInteractiveFeatures($container) {
        // Add hover effects to grid cells
        $container.find('.day-cell.has-slot').hover(
            function() {
                $(this).addClass('hover-highlight');
            },
            function() {
                $(this).removeClass('hover-highlight');
            }
        );

        // Add click handlers for slot details
        $container.find('.slot-content, .slot-item, .compact-slot-item').on('click', function() {
            showSlotDetails($(this));
        });

        // Add keyboard navigation for grid
        $container.find('.timetable-grid-table').on('keydown', function(e) {
            handleGridKeyNavigation(e, $(this));
        });

        // Add touch support for mobile
        if ('ontouchstart' in window) {
            addTouchSupport($container);
        }
    }

    /**
     * Show slot details in a modal or tooltip
     */
    function showSlotDetails($slot) {
        // Extract slot information
        const subject = $slot.find('.slot-subject, .compact-subject').text();
        const teacher = $slot.find('.slot-teacher, .compact-teacher').text();
        const room = $slot.find('.slot-room, .compact-room').text();
        const time = $slot.closest('.time-row').find('.time-display').text() || 
                    $slot.find('.compact-time').text();
        const day = $slot.find('.compact-day').text() || 
                   $slot.closest('tr').find('.day-header').text();

        if (!subject && !teacher) {
            return; // No details to show
        }

        // Create and show tooltip
        const tooltip = $(`
            <div class="timetable-tooltip">
                <div class="tooltip-content">
                    ${subject ? `<div class="tooltip-subject">${subject}</div>` : ''}
                    ${teacher ? `<div class="tooltip-teacher">Teacher: ${teacher}</div>` : ''}
                    ${room ? `<div class="tooltip-room">Room: ${room}</div>` : ''}
                    ${time ? `<div class="tooltip-time">Time: ${time}</div>` : ''}
                    ${day ? `<div class="tooltip-day">Day: ${day}</div>` : ''}
                </div>
            </div>
        `);

        // Position and show tooltip
        $('body').append(tooltip);
        
        const offset = $slot.offset();
        tooltip.css({
            position: 'absolute',
            top: offset.top + $slot.outerHeight() + 5,
            left: offset.left,
            zIndex: 1000
        }).fadeIn(200);

        // Auto-hide tooltip after 3 seconds
        setTimeout(function() {
            tooltip.fadeOut(200, function() {
                $(this).remove();
            });
        }, 3000);

        // Hide on click outside
        $(document).one('click', function() {
            tooltip.fadeOut(200, function() {
                $(this).remove();
            });
        });
    }

    /**
     * Handle keyboard navigation in grid
     */
    function handleGridKeyNavigation(e, $table) {
        const $focused = $table.find('.day-cell:focus');
        if ($focused.length === 0) return;

        let $target;
        
        switch(e.keyCode) {
            case 37: // Left arrow
                $target = $focused.prev('.day-cell');
                break;
            case 39: // Right arrow
                $target = $focused.next('.day-cell');
                break;
            case 38: // Up arrow
                const upIndex = $focused.index();
                $target = $focused.closest('tr').prev().find('.day-cell').eq(upIndex);
                break;
            case 40: // Down arrow
                const downIndex = $focused.index();
                $target = $focused.closest('tr').next().find('.day-cell').eq(downIndex);
                break;
            case 13: // Enter
                $focused.click();
                return;
        }

        if ($target && $target.length) {
            e.preventDefault();
            $target.focus();
        }
    }

    /**
     * Add touch support for mobile devices
     */
    function addTouchSupport($container) {
        let touchStartX, touchStartY;

        $container.on('touchstart', '.timetable-grid-display', function(e) {
            touchStartX = e.originalEvent.touches[0].clientX;
            touchStartY = e.originalEvent.touches[0].clientY;
        });

        $container.on('touchmove', '.timetable-grid-display', function(e) {
            if (!touchStartX || !touchStartY) return;

            const touchEndX = e.originalEvent.touches[0].clientX;
            const touchEndY = e.originalEvent.touches[0].clientY;

            const diffX = touchStartX - touchEndX;
            const diffY = touchStartY - touchEndY;

            // Horizontal scroll
            if (Math.abs(diffX) > Math.abs(diffY)) {
                $(this).scrollLeft($(this).scrollLeft() + diffX);
            }
        });

        $container.on('touchend', '.timetable-grid-display', function() {
            touchStartX = null;
            touchStartY = null;
        });
    }

    /**
     * Show loading state
     */
    function showLoading($container) {
        $container.html(`
            <div class="loading-message">
                <div class="loading-spinner"></div>
                <p>${sms_timetable_display.strings.loading}</p>
            </div>
        `);
    }

    /**
     * Show error message
     */
    function showError($container, message) {
        $container.html(`
            <div class="error-message">
                <p>${message}</p>
            </div>
        `);
    }

    /**
     * Show notification
     */
    function showNotification(message, type) {
        const notification = $(`
            <div class="timetable-notification timetable-notification-${type}">
                ${message}
            </div>
        `);

        $('body').append(notification);

        // Position notification
        notification.css({
            position: 'fixed',
            top: '20px',
            right: '20px',
            zIndex: 10000,
            padding: '10px 15px',
            borderRadius: '4px',
            color: '#fff',
            fontWeight: '500',
            maxWidth: '300px'
        });

        // Set background color based on type
        switch(type) {
            case 'success':
                notification.css('background', '#28a745');
                break;
            case 'error':
                notification.css('background', '#dc3545');
                break;
            case 'info':
                notification.css('background', '#17a2b8');
                break;
            default:
                notification.css('background', '#6c757d');
        }

        // Show and auto-hide
        notification.fadeIn(200);
        setTimeout(function() {
            notification.fadeOut(200, function() {
                $(this).remove();
            });
        }, 4000);

        // Click to dismiss
        notification.on('click', function() {
            $(this).fadeOut(200, function() {
                $(this).remove();
            });
        });
    }

    // Add CSS for loading spinner and tooltip
    $('<style>')
        .prop('type', 'text/css')
        .html(`
            .loading-spinner {
                border: 3px solid #f3f3f3;
                border-top: 3px solid #0073aa;
                border-radius: 50%;
                width: 30px;
                height: 30px;
                animation: spin 1s linear infinite;
                margin: 0 auto 10px;
            }
            
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
            
            .timetable-tooltip {
                background: rgba(0, 0, 0, 0.9);
                color: #fff;
                padding: 10px;
                border-radius: 4px;
                font-size: 12px;
                max-width: 200px;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
            }
            
            .tooltip-subject {
                font-weight: 600;
                margin-bottom: 4px;
            }
            
            .tooltip-teacher,
            .tooltip-room,
            .tooltip-time,
            .tooltip-day {
                margin-bottom: 2px;
                opacity: 0.9;
            }
            
            .hover-highlight {
                background: #e3f2fd !important;
                transform: scale(1.02);
                transition: all 0.2s;
            }
            
            .error-message {
                text-align: center;
                padding: 40px 20px;
                color: #dc3545;
                background: #f8d7da;
                border: 1px solid #f5c6cb;
                border-radius: 4px;
            }
            
            .day-cell {
                cursor: pointer;
                transition: all 0.2s;
            }
            
            .day-cell:focus {
                outline: 2px solid #0073aa;
                outline-offset: -2px;
            }
        `)
        .appendTo('head');

})(jQuery);