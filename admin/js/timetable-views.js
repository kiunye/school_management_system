/**
 * Timetable Views JavaScript
 *
 * @package SchoolManagementSystem
 * @subpackage Admin/JS
 */

(function($) {
    'use strict';

    // Global variables
    let currentViewType = 'class';
    let currentTimetableData = null;
    let currentDisplayFormat = 'grid';

    // Initialize when document is ready
    $(document).ready(function() {
        initializeTimetableViews();
        bindEvents();
        loadOverviewData();
    });

    /**
     * Initialize timetable views
     */
    function initializeTimetableViews() {
        // Set initial view
        showViewPanel('class');
        
        // Initialize overview stats
        updateOverviewStats();
    }

    /**
     * Bind event handlers
     */
    function bindEvents() {
        // View tab switching
        $('.view-tab').on('click', function() {
            const viewType = $(this).data('view');
            switchViewTab(viewType);
        });

        // Form submissions
        $('#class-view-form').on('submit', function(e) {
            e.preventDefault();
            loadClassTimetable();
        });

        $('#teacher-view-form').on('submit', function(e) {
            e.preventDefault();
            loadTeacherTimetable();
        });

        $('#overview-filter-form').on('submit', function(e) {
            e.preventDefault();
            updateOverviewData();
        });

        // Export buttons
        $('.export-pdf-btn').on('click', function() {
            exportTimetable('pdf');
        });

        $('.export-csv-btn').on('click', function() {
            exportTimetable('csv');
        });

        // Display controls
        $('#format-switcher').on('change', function() {
            const newFormat = $(this).val();
            switchDisplayFormat(newFormat);
        });

        $('#print-timetable-btn').on('click', function() {
            printTimetable();
        });

        $('#fullscreen-btn').on('click', function() {
            toggleFullscreen();
        });

        $('#close-display-btn').on('click', function() {
            closeTimetableDisplay();
        });

        // Form field changes
        $('#class-select, #teacher-select').on('change', function() {
            const form = $(this).closest('form');
            const exportButtons = form.find('.export-pdf-btn, .export-csv-btn');
            
            if ($(this).val()) {
                exportButtons.prop('disabled', false);
            } else {
                exportButtons.prop('disabled', true);
            }
        });
    }

    /**
     * Switch view tab
     */
    function switchViewTab(viewType) {
        // Update active tab
        $('.view-tab').removeClass('active');
        $(`.view-tab[data-view="${viewType}"]`).addClass('active');

        // Show corresponding panel
        showViewPanel(viewType);
        
        // Update current view type
        currentViewType = viewType;

        // Hide timetable display if switching views
        $('#timetable-display-area').hide();
    }

    /**
     * Show view panel
     */
    function showViewPanel(viewType) {
        $('.view-panel').hide();
        $(`#${viewType}-view-panel, #${viewType}-panel`).show();
    }

    /**
     * Load class timetable
     */
    function loadClassTimetable() {
        const formData = {
            action: 'sms_get_timetable_display',
            nonce: sms_timetable_display.nonce,
            view_type: 'class',
            entity_id: $('#class-select').val(),
            academic_year: $('#class-academic-year').val(),
            term: $('#class-term').val(),
            display_format: $('#class-display-format').val()
        };

        if (!formData.entity_id) {
            showNotification('Please select a class', 'error');
            return;
        }

        loadTimetableDisplay(formData);
    }

    /**
     * Load teacher timetable
     */
    function loadTeacherTimetable() {
        const formData = {
            action: 'sms_get_timetable_display',
            nonce: sms_timetable_display.nonce,
            view_type: 'teacher',
            entity_id: $('#teacher-select').val(),
            academic_year: $('#teacher-academic-year').val(),
            term: $('#teacher-term').val(),
            display_format: $('#teacher-display-format').val()
        };

        if (!formData.entity_id) {
            showNotification('Please select a teacher', 'error');
            return;
        }

        loadTimetableDisplay(formData);
    }

    /**
     * Load timetable display
     */
    function loadTimetableDisplay(formData) {
        showLoading(true);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    currentTimetableData = response.data;
                    currentDisplayFormat = formData.display_format;
                    
                    // Update display
                    displayTimetable(response.data.html, response.data.data, formData.view_type);
                    
                    showNotification('Timetable loaded successfully', 'success');
                } else {
                    showNotification(response.data || 'Error loading timetable', 'error');
                }
            },
            error: function() {
                showNotification('Error loading timetable. Please try again.', 'error');
            },
            complete: function() {
                showLoading(false);
            }
        });
    }

    /**
     * Display timetable
     */
    function displayTimetable(html, data, viewType) {
        // Update display title and info
        let title = 'Timetable Display';
        let info = '';

        if (viewType === 'class') {
            title = `Class Timetable: ${data.entity_name}`;
            info = `Academic Year: ${data.academic_year?.name || 'Current'} | Term: ${data.term?.name || 'Current'}`;
        } else if (viewType === 'teacher') {
            title = `Teacher Timetable: ${data.entity_name}`;
            info = `Total Classes: ${data.total_classes || 0}`;
        }

        $('#display-title').text(title);
        $('#display-info').text(info);

        // Update format switcher
        $('#format-switcher').val(currentDisplayFormat);

        // Update content
        $('#display-content').html(html);

        // Show display area
        $('#timetable-display-area').show();

        // Scroll to display area
        $('html, body').animate({
            scrollTop: $('#timetable-display-area').offset().top - 100
        }, 500);
    }

    /**
     * Switch display format
     */
    function switchDisplayFormat(newFormat) {
        if (!currentTimetableData) {
            return;
        }

        currentDisplayFormat = newFormat;

        // Update the current form's display format
        if (currentViewType === 'class') {
            $('#class-display-format').val(newFormat);
            loadClassTimetable();
        } else if (currentViewType === 'teacher') {
            $('#teacher-display-format').val(newFormat);
            loadTeacherTimetable();
        }
    }

    /**
     * Export timetable
     */
    function exportTimetable(format) {
        let entityId, viewType;

        if (currentViewType === 'class') {
            entityId = $('#class-select').val();
            viewType = 'class';
        } else if (currentViewType === 'teacher') {
            entityId = $('#teacher-select').val();
            viewType = 'teacher';
        }

        if (!entityId) {
            showNotification('Please select an entity to export', 'error');
            return;
        }

        const exportData = {
            action: `sms_export_timetable_${format}`,
            nonce: sms_timetable_display.nonce,
            view_type: viewType,
            entity_id: entityId,
            timetable_id: currentTimetableData?.timetable_id || 0
        };

        showLoading(true);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: exportData,
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
                showLoading(false);
            }
        });
    }

    /**
     * Print timetable
     */
    function printTimetable() {
        if (!currentTimetableData) {
            showNotification('No timetable to print', 'error');
            return;
        }

        // Hide non-printable elements and print
        window.print();
    }

    /**
     * Toggle fullscreen
     */
    function toggleFullscreen() {
        const displayArea = $('#timetable-display-area');
        
        if (displayArea.hasClass('fullscreen-mode')) {
            displayArea.removeClass('fullscreen-mode');
            $('#fullscreen-btn').text('Fullscreen');
        } else {
            displayArea.addClass('fullscreen-mode');
            $('#fullscreen-btn').text('Exit Fullscreen');
        }
    }

    /**
     * Close timetable display
     */
    function closeTimetableDisplay() {
        $('#timetable-display-area').hide();
        currentTimetableData = null;
        
        // Reset export buttons
        $('.export-pdf-btn, .export-csv-btn').prop('disabled', true);
    }

    /**
     * Load overview data
     */
    function loadOverviewData() {
        updateOverviewStats();
        updateTimetableStatusTable();
    }

    /**
     * Update overview stats
     */
    function updateOverviewStats() {
        // Get filter values
        const academicYear = $('#overview-academic-year').val();
        const term = $('#overview-term').val();

        // This would typically make an AJAX call to get stats
        // For now, we'll use placeholder values
        $('#total-timetables').text('24');
        $('#active-timetables').text('18');
        $('#classes-with-timetables').text('12');
        $('#teachers-assigned').text('15');
    }

    /**
     * Update overview data
     */
    function updateOverviewData() {
        updateOverviewStats();
        updateTimetableStatusTable();
    }

    /**
     * Update timetable status table
     */
    function updateTimetableStatusTable() {
        const tableBody = $('#timetable-status-table tbody');
        
        // Show loading
        tableBody.html('<tr><td colspan="6" class="loading-row">Loading timetable data...</td></tr>');

        // This would typically make an AJAX call to get timetable status data
        // For now, we'll simulate with a timeout
        setTimeout(function() {
            const sampleData = [
                {
                    class: 'Grade 1A',
                    academic_year: '2024-2025',
                    term: 'Term 1',
                    status: 'active',
                    last_modified: '2024-01-15',
                    id: 1
                },
                {
                    class: 'Grade 2B',
                    academic_year: '2024-2025',
                    term: 'Term 1',
                    status: 'draft',
                    last_modified: '2024-01-10',
                    id: 2
                },
                {
                    class: 'Grade 3A',
                    academic_year: '2024-2025',
                    term: 'Term 1',
                    status: 'active',
                    last_modified: '2024-01-12',
                    id: 3
                }
            ];

            let tableRows = '';
            sampleData.forEach(function(row) {
                tableRows += `
                    <tr>
                        <td>${row.class}</td>
                        <td>${row.academic_year}</td>
                        <td>${row.term}</td>
                        <td><span class="status-${row.status}">${capitalizeFirst(row.status)}</span></td>
                        <td>${row.last_modified}</td>
                        <td>
                            <div class="action-buttons">
                                <a href="#" class="action-btn view-btn" data-id="${row.id}">View</a>
                                <a href="#" class="action-btn edit-btn" data-id="${row.id}">Edit</a>
                            </div>
                        </td>
                    </tr>
                `;
            });

            tableBody.html(tableRows);

            // Bind action button events
            $('.action-btn.view-btn').on('click', function(e) {
                e.preventDefault();
                const timetableId = $(this).data('id');
                viewTimetableFromTable(timetableId);
            });

            $('.action-btn.edit-btn').on('click', function(e) {
                e.preventDefault();
                const timetableId = $(this).data('id');
                editTimetableFromTable(timetableId);
            });
        }, 1000);
    }

    /**
     * View timetable from table
     */
    function viewTimetableFromTable(timetableId) {
        // This would load the specific timetable
        showNotification(`Viewing timetable ID: ${timetableId}`, 'info');
    }

    /**
     * Edit timetable from table
     */
    function editTimetableFromTable(timetableId) {
        // This would redirect to the timetable builder
        const editUrl = `edit.php?post_type=sms_timetables&page=sms-timetable-builder&timetable_id=${timetableId}`;
        window.location.href = editUrl;
    }

    /**
     * Show loading overlay
     */
    function showLoading(show) {
        if (show) {
            $('#loading-overlay').show();
        } else {
            $('#loading-overlay').hide();
        }
    }

    /**
     * Show notification
     */
    function showNotification(message, type) {
        // Create notification element
        const notification = $(`<div class="notice notice-${type} is-dismissible"><p>${message}</p></div>`);
        
        // Add to page
        $('.wrap h1').after(notification);
        
        // Auto dismiss after 5 seconds
        setTimeout(function() {
            notification.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);

        // Make dismissible
        notification.on('click', '.notice-dismiss', function() {
            notification.fadeOut(function() {
                $(this).remove();
            });
        });
    }

    /**
     * Capitalize first letter
     */
    function capitalizeFirst(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }

    // Handle keyboard shortcuts
    $(document).on('keydown', function(e) {
        // ESC key to close fullscreen or display
        if (e.keyCode === 27) {
            if ($('#timetable-display-area').hasClass('fullscreen-mode')) {
                toggleFullscreen();
            } else if ($('#timetable-display-area').is(':visible')) {
                closeTimetableDisplay();
            }
        }
        
        // Ctrl+P to print
        if (e.ctrlKey && e.keyCode === 80 && currentTimetableData) {
            e.preventDefault();
            printTimetable();
        }
    });

    // Handle window resize in fullscreen
    $(window).on('resize', function() {
        if ($('#timetable-display-area').hasClass('fullscreen-mode')) {
            // Adjust fullscreen layout if needed
        }
    });

})(jQuery);