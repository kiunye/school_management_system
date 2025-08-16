/**
 * Timetable Builder JavaScript
 *
 * @package SchoolManagementSystem
 * @subpackage Admin/JS
 */

(function($) {
    'use strict';

    // Global variables
    let currentTimetableId = 0;
    let timeSlots = [];
    let currentEditingSlot = null;
    let validationTimer = null;

    // Initialize when document is ready
    $(document).ready(function() {
        initializeTimetableBuilder();
        bindEvents();
        setupDragAndDrop();
    });

    /**
     * Initialize timetable builder
     */
    function initializeTimetableBuilder() {
        // Hide builder interface initially
        $('#timetable-builder-interface').hide();
        
        // Initialize time grid
        generateTimeGrid();
        
        // Load existing timetables for dropdown
        loadExistingTimetables();
    }

    /**
     * Bind event handlers
     */
    function bindEvents() {
        // Configuration form events
        $('#load-timetable-btn').on('click', loadExistingTimetable);
        $('#new-timetable-btn').on('click', startNewTimetable);
        $('#existing-timetable').on('change', onExistingTimetableChange);
        
        // Time slot form events
        $('#add-time-slot-btn').on('click', showTimeSlotForm);
        $('#check-availability-btn').on('click', checkSlotAvailability);
        $('#add-slot-btn').on('click', addTimeSlot);
        
        // Timetable actions
        $('#validate-timetable-btn').on('click', validateTimetable);
        $('#save-timetable-btn').on('click', saveTimetable);
        $('#clear-timetable-btn').on('click', clearTimetable);
        $('#auto-arrange-btn').on('click', autoArrangeSlots);
        
        // List controls
        $('#sort-by-time-btn').on('click', function() { sortTimeSlots('time'); });
        $('#sort-by-day-btn').on('click', function() { sortTimeSlots('day'); });
        
        // Modal events
        $('.modal-close').on('click', closeModal);
        $('#resolve-conflicts-btn').on('click', resolveConflicts);
        
        // Form field events
        $('#timetable-class').on('change', onClassChange);
        $('#slot-teacher').on('change', onTeacherChange);
        $('#slot-start-time, #slot-end-time').on('change', onTimeChange);
        
        // Dynamic validation
        $('#slot-day, #slot-start-time, #slot-end-time, #slot-teacher, #slot-room').on('change', function() {
            clearTimeout(validationTimer);
            validationTimer = setTimeout(validateCurrentSlot, 500);
        });
    }

    /**
     * Setup drag and drop functionality
     */
    function setupDragAndDrop() {
        // Make time slots draggable
        $('#time-slots-list').sortable({
            items: '.time-slot-item',
            handle: '.slot-header',
            placeholder: 'ui-sortable-placeholder',
            helper: 'clone',
            opacity: 0.8,
            update: function(event, ui) {
                updateSlotOrder();
            }
        });

        // Make grid cells droppable
        $('.grid-cell').droppable({
            accept: '.time-slot-item',
            hoverClass: 'drop-zone',
            drop: function(event, ui) {
                handleSlotDrop(event, ui);
            }
        });
    }

    /**
     * Load existing timetable
     */
    function loadExistingTimetable() {
        const timetableId = $('#existing-timetable').val();
        const classId = $('#timetable-class').val();
        const academicYear = $('#academic-year').val();
        const term = $('#term').val();

        if (!classId || !academicYear || !term) {
            showNotification('Please select class, academic year, and term.', 'error');
            return;
        }

        if (timetableId) {
            loadTimetableData(timetableId);
        } else {
            startNewTimetable();
        }
    }

    /**
     * Start new timetable
     */
    function startNewTimetable() {
        const classId = $('#timetable-class').val();
        const academicYear = $('#academic-year').val();
        const term = $('#term').val();

        if (!classId || !academicYear || !term) {
            showNotification('Please select class, academic year, and term.', 'error');
            return;
        }

        // Reset current data
        currentTimetableId = 0;
        timeSlots = [];
        
        // Update title
        const className = $('#timetable-class option:selected').text();
        const yearName = $('#academic-year option:selected').text();
        const termName = $('#term option:selected').text();
        $('#current-timetable-title').text(`${className} - ${yearName} - ${termName}`);
        
        // Show builder interface
        $('#timetable-builder-interface').show();
        
        // Load class subjects
        loadClassSubjects(classId);
        
        // Clear and regenerate grid
        clearTimetableGrid();
        updateTimeSlotsDisplay();
        
        showNotification('New timetable started. You can now add time slots.', 'success');
    }

    /**
     * Load timetable data via AJAX
     */
    function loadTimetableData(timetableId) {
        showLoading(true);

        $.ajax({
            url: sms_timetable_builder.ajax_url,
            type: 'POST',
            data: {
                action: 'sms_load_timetable_data',
                nonce: sms_timetable_builder.nonce,
                timetable_id: timetableId
            },
            success: function(response) {
                if (response.success) {
                    currentTimetableId = timetableId;
                    timeSlots = response.data.time_slots || [];
                    
                    // Update form fields
                    $('#timetable-class').val(response.data.class_id);
                    $('#academic-year').val(response.data.academic_year);
                    $('#term').val(response.data.term);
                    
                    // Update title
                    const className = $('#timetable-class option:selected').text();
                    const yearName = $('#academic-year option:selected').text();
                    const termName = $('#term option:selected').text();
                    $('#current-timetable-title').text(`${className} - ${yearName} - ${termName}`);
                    
                    // Show builder interface
                    $('#timetable-builder-interface').show();
                    
                    // Load class subjects
                    loadClassSubjects(response.data.class_id);
                    
                    // Update displays
                    updateTimetableGrid();
                    updateTimeSlotsDisplay();
                    
                    showNotification('Timetable loaded successfully.', 'success');
                } else {
                    showNotification(response.data || 'Error loading timetable.', 'error');
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
     * Add time slot
     */
    function addTimeSlot() {
        const slotData = {
            day: $('#slot-day').val(),
            start_time: $('#slot-start-time').val(),
            end_time: $('#slot-end-time').val(),
            subject_id: $('#slot-subject').val(),
            subject_name: $('#slot-subject option:selected').text(),
            teacher_id: $('#slot-teacher').val(),
            teacher_name: $('#slot-teacher option:selected').text(),
            room: $('#slot-room').val(),
            slot_type: $('#slot-type').val(),
            slot_type_label: $('#slot-type option:selected').text()
        };

        // Validate required fields
        if (!slotData.day || !slotData.start_time || !slotData.end_time) {
            showNotification('Please fill in all required fields.', 'error');
            return;
        }

        // Validate time order
        if (slotData.start_time >= slotData.end_time) {
            showNotification('End time must be after start time.', 'error');
            return;
        }

        // Check for conflicts
        validateSlotData(slotData, function(isValid, conflicts) {
            if (isValid) {
                // Add slot to array
                slotData.id = generateSlotId();
                timeSlots.push(slotData);
                
                // Update displays
                updateTimetableGrid();
                updateTimeSlotsDisplay();
                
                // Clear form
                clearTimeSlotForm();
                
                showNotification('Time slot added successfully.', 'success');
            } else {
                showConflictModal(conflicts);
            }
        });
    }

    /**
     * Check slot availability
     */
    function checkSlotAvailability() {
        const teacherId = $('#slot-teacher').val();
        const day = $('#slot-day').val();
        const startTime = $('#slot-start-time').val();
        const endTime = $('#slot-end-time').val();
        const room = $('#slot-room').val();

        if (!day || !startTime || !endTime) {
            showNotification('Please select day and time first.', 'error');
            return;
        }

        showLoading(true);

        $.ajax({
            url: sms_timetable_builder.ajax_url,
            type: 'POST',
            data: {
                action: 'sms_validate_time_slot',
                nonce: sms_timetable_builder.nonce,
                teacher_id: teacherId,
                day: day,
                start_time: startTime,
                end_time: endTime,
                room: room,
                exclude_timetable_id: currentTimetableId
            },
            success: function(response) {
                if (response.success) {
                    showValidationResult('No conflicts detected. Slot is available.', 'success');
                } else {
                    showValidationResult('Conflicts detected: ' + response.data.message, 'error');
                }
            },
            error: function() {
                showValidationResult('Error checking availability.', 'error');
            },
            complete: function() {
                showLoading(false);
            }
        });
    }

    /**
     * Validate timetable
     */
    function validateTimetable() {
        if (timeSlots.length === 0) {
            showNotification('No time slots to validate.', 'error');
            return;
        }

        showLoading(true);

        // Validate all slots
        let allConflicts = [];
        let validatedSlots = 0;

        timeSlots.forEach(function(slot, index) {
            validateSlotData(slot, function(isValid, conflicts) {
                validatedSlots++;
                
                if (!isValid) {
                    allConflicts = allConflicts.concat(conflicts.map(function(conflict) {
                        conflict.slot_index = index;
                        return conflict;
                    }));
                }

                // Check if all slots are validated
                if (validatedSlots === timeSlots.length) {
                    showLoading(false);
                    
                    if (allConflicts.length === 0) {
                        showValidationStatus('Timetable validation passed. No conflicts detected.', 'success');
                    } else {
                        showValidationStatus(`Validation failed. ${allConflicts.length} conflicts detected.`, 'error');
                        showConflictModal(allConflicts);
                    }
                }
            });
        });
    }

    /**
     * Save timetable
     */
    function saveTimetable() {
        const classId = $('#timetable-class').val();
        const academicYear = $('#academic-year').val();
        const term = $('#term').val();

        if (!classId || !academicYear || !term) {
            showNotification('Please select class, academic year, and term.', 'error');
            return;
        }

        if (timeSlots.length === 0) {
            showNotification('Please add at least one time slot.', 'error');
            return;
        }

        showLoading(true);

        $.ajax({
            url: sms_timetable_builder.ajax_url,
            type: 'POST',
            data: {
                action: 'sms_save_timetable_builder',
                nonce: sms_timetable_builder.nonce,
                timetable_id: currentTimetableId,
                class_id: classId,
                academic_year: academicYear,
                term: term,
                time_slots: JSON.stringify(timeSlots)
            },
            success: function(response) {
                if (response.success) {
                    currentTimetableId = response.data.timetable_id;
                    showNotification(response.data.message, 'success');
                    
                    // Update existing timetables dropdown
                    loadExistingTimetables();
                } else {
                    if (response.data.conflicts) {
                        showConflictModal(response.data.conflicts);
                    } else {
                        showNotification(response.data.message || 'Error saving timetable.', 'error');
                    }
                }
            },
            error: function() {
                showNotification('Error saving timetable. Please try again.', 'error');
            },
            complete: function() {
                showLoading(false);
            }
        });
    }

    /**
     * Clear timetable
     */
    function clearTimetable() {
        if (confirm('Are you sure you want to clear all time slots? This action cannot be undone.')) {
            timeSlots = [];
            clearTimetableGrid();
            updateTimeSlotsDisplay();
            showNotification('Timetable cleared.', 'success');
        }
    }

    /**
     * Auto arrange slots
     */
    function autoArrangeSlots() {
        if (timeSlots.length === 0) {
            showNotification('No time slots to arrange.', 'error');
            return;
        }

        // Sort slots by day and time
        timeSlots.sort(function(a, b) {
            const dayOrder = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
            const dayA = dayOrder.indexOf(a.day);
            const dayB = dayOrder.indexOf(b.day);
            
            if (dayA !== dayB) {
                return dayA - dayB;
            }
            
            return a.start_time.localeCompare(b.start_time);
        });

        updateTimetableGrid();
        updateTimeSlotsDisplay();
        showNotification('Time slots arranged automatically.', 'success');
    }

    /**
     * Generate time grid
     */
    function generateTimeGrid() {
        const gridBody = $('#timetable-grid-body');
        const timeSlots = [
            '08:00-08:40', '08:40-09:20', '09:20-10:00', '10:00-10:20', // Break
            '10:20-11:00', '11:00-11:40', '11:40-12:20', '12:20-13:00', // Lunch
            '13:00-13:40', '13:40-14:20', '14:20-15:00', '15:00-15:40'
        ];
        
        const days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
        
        gridBody.empty();
        
        timeSlots.forEach(function(timeSlot) {
            const row = $('<div class="grid-row"></div>');
            
            // Time label
            const timeLabel = $('<div class="time-slot-label"></div>').text(timeSlot);
            row.append(timeLabel);
            
            // Day cells
            days.forEach(function(day) {
                const cell = $('<div class="grid-cell"></div>')
                    .attr('data-day', day)
                    .attr('data-time', timeSlot);
                row.append(cell);
            });
            
            gridBody.append(row);
        });
        
        // Make cells droppable
        $('.grid-cell').droppable({
            accept: '.time-slot-item',
            hoverClass: 'drop-zone',
            drop: function(event, ui) {
                handleSlotDrop(event, ui);
            }
        });
    }

    /**
     * Update timetable grid
     */
    function updateTimetableGrid() {
        // Clear all cells
        $('.grid-cell').removeClass('occupied conflict error').empty();
        
        // Populate cells with time slots
        timeSlots.forEach(function(slot) {
            const timeRange = slot.start_time + '-' + slot.end_time;
            const cell = $(`.grid-cell[data-day="${slot.day}"][data-time*="${slot.start_time}"]`).first();
            
            if (cell.length) {
                cell.addClass('occupied');
                
                const content = $('<div class="cell-content"></div>');
                content.append(`<div class="cell-subject">${slot.subject_name || 'No Subject'}</div>`);
                content.append(`<div class="cell-teacher">${slot.teacher_name || 'No Teacher'}</div>`);
                if (slot.room) {
                    content.append(`<div class="cell-room">${slot.room}</div>`);
                }
                
                const actions = $('<div class="cell-actions"></div>');
                actions.append(`<button type="button" class="edit-cell-btn" data-slot-id="${slot.id}"><span class="dashicons dashicons-edit"></span></button>`);
                actions.append(`<button type="button" class="delete-cell-btn" data-slot-id="${slot.id}"><span class="dashicons dashicons-trash"></span></button>`);
                
                cell.append(content).append(actions);
            }
        });
        
        // Bind cell action events
        $('.edit-cell-btn').on('click', function() {
            const slotId = $(this).data('slot-id');
            editTimeSlot(slotId);
        });
        
        $('.delete-cell-btn').on('click', function() {
            const slotId = $(this).data('slot-id');
            deleteTimeSlot(slotId);
        });
    }

    /**
     * Update time slots display
     */
    function updateTimeSlotsDisplay() {
        const container = $('#time-slots-list');
        const noSlotsMessage = $('#no-slots-message');
        
        if (timeSlots.length === 0) {
            container.empty().append(noSlotsMessage.show());
            return;
        }
        
        noSlotsMessage.hide();
        container.empty();
        
        timeSlots.forEach(function(slot) {
            const slotElement = createTimeSlotElement(slot);
            container.append(slotElement);
        });
        
        // Make slots sortable
        container.sortable('refresh');
    }

    /**
     * Create time slot element
     */
    function createTimeSlotElement(slot) {
        const template = $('#time-slot-template').html();
        let html = template;
        
        // Replace placeholders
        html = html.replace(/{{slot_id}}/g, slot.id);
        html = html.replace(/{{day}}/g, capitalizeFirst(slot.day));
        html = html.replace(/{{start_time}}/g, slot.start_time);
        html = html.replace(/{{end_time}}/g, slot.end_time);
        html = html.replace(/{{subject_name}}/g, slot.subject_name || 'No Subject');
        html = html.replace(/{{teacher_name}}/g, slot.teacher_name || 'No Teacher');
        html = html.replace(/{{room}}/g, slot.room || 'No Room');
        html = html.replace(/{{slot_type}}/g, slot.slot_type);
        html = html.replace(/{{slot_type_label}}/g, slot.slot_type_label);
        html = html.replace(/{{status_class}}/g, 'valid');
        html = html.replace(/{{status_text}}/g, 'Valid');
        
        const element = $(html);
        
        // Bind events
        element.find('.edit-slot-btn').on('click', function() {
            editTimeSlot(slot.id);
        });
        
        element.find('.delete-slot-btn').on('click', function() {
            deleteTimeSlot(slot.id);
        });
        
        return element;
    }

    /**
     * Edit time slot
     */
    function editTimeSlot(slotId) {
        const slot = timeSlots.find(s => s.id === slotId);
        if (!slot) return;
        
        // Populate form with slot data
        $('#slot-day').val(slot.day);
        $('#slot-start-time').val(slot.start_time);
        $('#slot-end-time').val(slot.end_time);
        $('#slot-subject').val(slot.subject_id);
        $('#slot-teacher').val(slot.teacher_id);
        $('#slot-room').val(slot.room);
        $('#slot-type').val(slot.slot_type);
        
        // Set editing mode
        currentEditingSlot = slotId;
        $('#add-slot-btn').text('Update Slot');
        
        // Scroll to form
        $('html, body').animate({
            scrollTop: $('#time-slot-form').offset().top - 100
        }, 500);
    }

    /**
     * Delete time slot
     */
    function deleteTimeSlot(slotId) {
        if (confirm(sms_timetable_builder.strings.confirm_delete_slot)) {
            timeSlots = timeSlots.filter(s => s.id !== slotId);
            updateTimetableGrid();
            updateTimeSlotsDisplay();
            showNotification('Time slot deleted.', 'success');
        }
    }

    /**
     * Validate slot data
     */
    function validateSlotData(slotData, callback) {
        $.ajax({
            url: sms_timetable_builder.ajax_url,
            type: 'POST',
            data: {
                action: 'sms_validate_time_slot',
                nonce: sms_timetable_builder.nonce,
                teacher_id: slotData.teacher_id,
                day: slotData.day,
                start_time: slotData.start_time,
                end_time: slotData.end_time,
                room: slotData.room,
                exclude_timetable_id: currentTimetableId
            },
            success: function(response) {
                callback(response.success, response.data.conflicts || []);
            },
            error: function() {
                callback(false, []);
            }
        });
    }

    /**
     * Load class subjects
     */
    function loadClassSubjects(classId) {
        $.ajax({
            url: sms_timetable_builder.ajax_url,
            type: 'POST',
            data: {
                action: 'sms_get_class_subjects',
                nonce: sms_timetable_builder.nonce,
                class_id: classId
            },
            success: function(response) {
                if (response.success) {
                    const subjectSelect = $('#slot-subject');
                    subjectSelect.empty().append('<option value="">Select Subject</option>');
                    
                    response.data.forEach(function(subject) {
                        subjectSelect.append(`<option value="${subject.id}">${subject.name}</option>`);
                    });
                }
            }
        });
    }

    /**
     * Load existing timetables
     */
    function loadExistingTimetables() {
        // This would typically load via AJAX, but for now we'll use the server-rendered options
    }

    /**
     * Show conflict modal
     */
    function showConflictModal(conflicts) {
        const modal = $('#conflict-modal');
        const detailsContainer = $('#conflict-details');
        
        detailsContainer.empty();
        
        conflicts.forEach(function(conflict) {
            const conflictItem = $('<div class="conflict-item"></div>');
            conflictItem.append(`<div class="conflict-type">${conflict.type || 'Conflict'}</div>`);
            conflictItem.append(`<div class="conflict-message">${conflict.message}</div>`);
            
            if (conflict.conflicts_with && conflict.conflicts_with.length > 0) {
                const details = $('<div class="conflict-details"></div>');
                details.append('<strong>Conflicts with:</strong><br>');
                conflict.conflicts_with.forEach(function(conflictWith) {
                    details.append(`- ${conflictWith.timetable_title}<br>`);
                });
                conflictItem.append(details);
            }
            
            detailsContainer.append(conflictItem);
        });
        
        modal.show();
    }

    /**
     * Close modal
     */
    function closeModal() {
        $('.sms-modal').hide();
    }

    /**
     * Resolve conflicts
     */
    function resolveConflicts() {
        // This would implement conflict resolution logic
        closeModal();
        showNotification('Conflict resolution not implemented yet.', 'info');
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
    }

    /**
     * Show validation status
     */
    function showValidationStatus(message, type) {
        const statusElement = $('#validation-status');
        statusElement.removeClass('success error warning').addClass(type);
        statusElement.text(message).show();
        
        setTimeout(function() {
            statusElement.fadeOut();
        }, 5000);
    }

    /**
     * Show validation result
     */
    function showValidationResult(message, type) {
        const resultElement = $('#slot-validation-result');
        resultElement.removeClass('success error').addClass(type);
        resultElement.text(message).show();
        
        setTimeout(function() {
            resultElement.fadeOut();
        }, 5000);
    }

    /**
     * Clear time slot form
     */
    function clearTimeSlotForm() {
        $('#time-slot-form')[0].reset();
        $('#add-slot-btn').text('Add Slot');
        currentEditingSlot = null;
        $('#slot-validation-result').hide();
    }

    /**
     * Clear timetable grid
     */
    function clearTimetableGrid() {
        $('.grid-cell').removeClass('occupied conflict error').empty();
    }

    /**
     * Generate unique slot ID
     */
    function generateSlotId() {
        return 'slot_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    }

    /**
     * Capitalize first letter
     */
    function capitalizeFirst(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }

    /**
     * Sort time slots
     */
    function sortTimeSlots(sortBy) {
        if (sortBy === 'time') {
            timeSlots.sort(function(a, b) {
                return a.start_time.localeCompare(b.start_time);
            });
        } else if (sortBy === 'day') {
            const dayOrder = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
            timeSlots.sort(function(a, b) {
                const dayA = dayOrder.indexOf(a.day);
                const dayB = dayOrder.indexOf(b.day);
                return dayA - dayB;
            });
        }
        
        updateTimeSlotsDisplay();
        showNotification(`Time slots sorted by ${sortBy}.`, 'success');
    }

    /**
     * Handle slot drop
     */
    function handleSlotDrop(event, ui) {
        // This would implement drag and drop functionality
        console.log('Slot dropped:', event, ui);
    }

    /**
     * Update slot order
     */
    function updateSlotOrder() {
        // This would update the order of slots based on sortable
        console.log('Slot order updated');
    }

    /**
     * Event handlers
     */
    function onExistingTimetableChange() {
        const timetableId = $(this).val();
        if (timetableId) {
            // Auto-populate form fields if possible
        }
    }

    function onClassChange() {
        const classId = $(this).val();
        if (classId) {
            loadClassSubjects(classId);
        }
    }

    function onTeacherChange() {
        validateCurrentSlot();
    }

    function onTimeChange() {
        validateCurrentSlot();
    }

    function validateCurrentSlot() {
        const teacherId = $('#slot-teacher').val();
        const day = $('#slot-day').val();
        const startTime = $('#slot-start-time').val();
        const endTime = $('#slot-end-time').val();
        const room = $('#slot-room').val();

        if (teacherId && day && startTime && endTime) {
            checkSlotAvailability();
        }
    }

    function showTimeSlotForm() {
        $('#time-slot-form').show();
        $('html, body').animate({
            scrollTop: $('#time-slot-form').offset().top - 100
        }, 500);
    }

})(jQuery);