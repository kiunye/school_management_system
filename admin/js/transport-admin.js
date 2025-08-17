/**
 * Transport Administration JavaScript
 *
 * @package SchoolManagementSystem
 * @subpackage Admin/JS
 */

(function($) {
    'use strict';

    var TransportAdmin = {
        
        /**
         * Initialize transport admin functionality
         */
        init: function() {
            this.bindEvents();
            this.initializeComponents();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Route management events
            $(document).on('click', '.sms-create-route', this.showCreateRouteModal);
            $(document).on('click', '.sms-edit-route', this.showEditRouteModal);
            $(document).on('click', '.sms-delete-route', this.confirmDeleteRoute);
            $(document).on('submit', '#sms-route-form', this.handleRouteSubmit);

            // Vehicle management events
            $(document).on('click', '.sms-edit-vehicle', this.showEditVehicleModal);
            $(document).on('click', '.sms-edit-driver', this.showEditDriverModal);
            $(document).on('submit', '#sms-vehicle-form', this.handleVehicleSubmit);
            $(document).on('submit', '#sms-driver-form', this.handleDriverSubmit);

            // Capacity validation
            $(document).on('change', '#vehicle_capacity', this.validateVehicleCapacity);
            $(document).on('change', '#total_capacity', this.validateRouteCapacity);

            // Alert management
            $(document).on('click', '.sms-dismiss-alert', this.dismissAlert);
            $(document).on('change', '.sms-alert-filter', this.filterAlerts);

            // Dashboard refresh
            $(document).on('click', '.sms-refresh-dashboard', this.refreshDashboard);
        },

        /**
         * Initialize components
         */
        initializeComponents: function() {
            // Initialize tooltips
            this.initializeTooltips();
            
            // Initialize date pickers
            this.initializeDatePickers();
            
            // Initialize select2 dropdowns
            this.initializeSelect2();
            
            // Load initial data
            this.loadDashboardData();
        },

        /**
         * Show create route modal
         */
        showCreateRouteModal: function(e) {
            e.preventDefault();
            
            var modal = TransportAdmin.createModal('create-route', smsTransportAdmin.strings.createRoute);
            var form = TransportAdmin.buildRouteForm();
            
            modal.find('.sms-modal-body').html(form);
            modal.modal('show');
        },

        /**
         * Show edit route modal
         */
        showEditRouteModal: function(e) {
            e.preventDefault();
            
            var routeId = $(this).data('route-id');
            var modal = TransportAdmin.createModal('edit-route', smsTransportAdmin.strings.editRoute);
            
            // Show loading state
            modal.find('.sms-modal-body').html('<div class="sms-loading"><span class="dashicons dashicons-update"></span> Loading...</div>');
            modal.modal('show');
            
            // Load route data
            TransportAdmin.loadRouteData(routeId, function(data) {
                var form = TransportAdmin.buildRouteForm(data);
                modal.find('.sms-modal-body').html(form);
            });
        },

        /**
         * Confirm route deletion
         */
        confirmDeleteRoute: function(e) {
            e.preventDefault();
            
            if (!confirm(smsTransportAdmin.strings.confirmDelete)) {
                return;
            }
            
            var routeId = $(this).data('route-id');
            var $button = $(this);
            
            $button.prop('disabled', true).text('Deleting...');
            
            $.post(smsTransportAdmin.ajaxUrl, {
                action: 'sms_delete_route',
                route_id: routeId,
                nonce: smsTransportAdmin.nonce
            }, function(response) {
                if (response.success) {
                    TransportAdmin.showNotice('success', response.data.message);
                    $button.closest('tr').fadeOut(function() {
                        $(this).remove();
                    });
                } else {
                    TransportAdmin.showNotice('error', response.data);
                    $button.prop('disabled', false).text('Delete');
                }
            });
        },

        /**
         * Handle route form submission
         */
        handleRouteSubmit: function(e) {
            e.preventDefault();
            
            var $form = $(this);
            var formData = $form.serialize();
            var isEdit = $form.find('#route_id').length > 0;
            var action = isEdit ? 'sms_update_route' : 'sms_create_route';
            
            // Disable submit button
            var $submitBtn = $form.find('[type="submit"]');
            $submitBtn.prop('disabled', true).text('Saving...');
            
            $.post(smsTransportAdmin.ajaxUrl, {
                action: action,
                route_data: formData,
                nonce: smsTransportAdmin.nonce
            }, function(response) {
                if (response.success) {
                    TransportAdmin.showNotice('success', response.data.message);
                    $('.sms-modal').modal('hide');
                    location.reload(); // Refresh page to show changes
                } else {
                    TransportAdmin.showNotice('error', response.data);
                    $submitBtn.prop('disabled', false).text('Save Route');
                }
            });
        },

        /**
         * Show edit vehicle modal
         */
        showEditVehicleModal: function(e) {
            e.preventDefault();
            
            var routeId = $(this).data('route-id');
            var modal = TransportAdmin.createModal('edit-vehicle', smsTransportAdmin.strings.editVehicle);
            
            // Show loading state
            modal.find('.sms-modal-body').html('<div class="sms-loading"><span class="dashicons dashicons-update"></span> Loading...</div>');
            modal.modal('show');
            
            // Load vehicle data
            TransportAdmin.loadVehicleData(routeId, function(data) {
                var form = TransportAdmin.buildVehicleForm(data, routeId);
                modal.find('.sms-modal-body').html(form);
            });
        },

        /**
         * Show edit driver modal
         */
        showEditDriverModal: function(e) {
            e.preventDefault();
            
            var routeId = $(this).data('route-id');
            var modal = TransportAdmin.createModal('edit-driver', smsTransportAdmin.strings.editDriver);
            
            // Show loading state
            modal.find('.sms-modal-body').html('<div class="sms-loading"><span class="dashicons dashicons-update"></span> Loading...</div>');
            modal.modal('show');
            
            // Load driver data
            TransportAdmin.loadDriverData(routeId, function(data) {
                var form = TransportAdmin.buildDriverForm(data, routeId);
                modal.find('.sms-modal-body').html(form);
            });
        },

        /**
         * Handle vehicle form submission
         */
        handleVehicleSubmit: function(e) {
            e.preventDefault();
            
            var $form = $(this);
            var formData = $form.serialize();
            var routeId = $form.find('#route_id').val();
            
            // Disable submit button
            var $submitBtn = $form.find('[type="submit"]');
            $submitBtn.prop('disabled', true).text('Saving...');
            
            $.post(smsTransportAdmin.ajaxUrl, {
                action: 'sms_update_vehicle_details',
                route_id: routeId,
                vehicle_data: formData,
                nonce: smsTransportAdmin.nonce
            }, function(response) {
                if (response.success) {
                    TransportAdmin.showNotice('success', response.data.message);
                    $('.sms-modal').modal('hide');
                    location.reload();
                } else {
                    TransportAdmin.showNotice('error', response.data);
                    $submitBtn.prop('disabled', false).text('Save Vehicle');
                }
            });
        },

        /**
         * Handle driver form submission
         */
        handleDriverSubmit: function(e) {
            e.preventDefault();
            
            var $form = $(this);
            var formData = $form.serialize();
            var routeId = $form.find('#route_id').val();
            
            // Disable submit button
            var $submitBtn = $form.find('[type="submit"]');
            $submitBtn.prop('disabled', true).text('Saving...');
            
            $.post(smsTransportAdmin.ajaxUrl, {
                action: 'sms_update_driver_details',
                route_id: routeId,
                driver_data: formData,
                nonce: smsTransportAdmin.nonce
            }, function(response) {
                if (response.success) {
                    TransportAdmin.showNotice('success', response.data.message);
                    $('.sms-modal').modal('hide');
                    location.reload();
                } else {
                    TransportAdmin.showNotice('error', response.data);
                    $submitBtn.prop('disabled', false).text('Save Driver');
                }
            });
        },

        /**
         * Validate vehicle capacity
         */
        validateVehicleCapacity: function() {
            var vehicleCapacity = parseInt($(this).val());
            var routeId = $('#route_id').val();
            
            if (!vehicleCapacity || !routeId) {
                return;
            }
            
            $.post(smsTransportAdmin.ajaxUrl, {
                action: 'sms_validate_vehicle_capacity',
                route_id: routeId,
                vehicle_capacity: vehicleCapacity,
                nonce: smsTransportAdmin.nonce
            }, function(response) {
                if (response.success) {
                    var validation = response.data;
                    var $warning = $('#capacity-warning');
                    
                    if (!validation.is_valid) {
                        $warning.html('<div class="notice notice-error"><p>' + validation.errors.join('<br>') + '</p></div>').show();
                    } else if (validation.warnings.length > 0) {
                        $warning.html('<div class="notice notice-warning"><p>' + validation.warnings.join('<br>') + '</p></div>').show();
                    } else {
                        $warning.hide();
                    }
                }
            });
        },

        /**
         * Validate route capacity
         */
        validateRouteCapacity: function() {
            var routeCapacity = parseInt($(this).val());
            var routeId = $('#route_id').val();
            
            if (!routeCapacity || !routeId) {
                return;
            }
            
            $.post(smsTransportAdmin.ajaxUrl, {
                action: 'sms_validate_route_capacity',
                route_id: routeId,
                nonce: smsTransportAdmin.nonce
            }, function(response) {
                if (response.success) {
                    var data = response.data;
                    var $info = $('#capacity-info');
                    
                    if (routeCapacity < data.current_enrollment) {
                        $info.html('<div class="notice notice-error"><p>Route capacity cannot be less than current enrollment (' + data.current_enrollment + ').</p></div>').show();
                    } else {
                        $info.html('<div class="notice notice-info"><p>Available capacity will be: ' + (routeCapacity - data.current_enrollment) + '</p></div>').show();
                    }
                }
            });
        },

        /**
         * Dismiss alert
         */
        dismissAlert: function(e) {
            e.preventDefault();
            $(this).closest('.sms-alert-item').fadeOut();
        },

        /**
         * Filter alerts
         */
        filterAlerts: function() {
            var filterType = $(this).val();
            var $alerts = $('.sms-alert-item');
            
            if (filterType === 'all') {
                $alerts.show();
            } else {
                $alerts.hide();
                $('.sms-alert-item[data-type="' + filterType + '"]').show();
            }
        },

        /**
         * Refresh dashboard
         */
        refreshDashboard: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            $button.find('.dashicons').addClass('sms-loading');
            
            $.post(smsTransportAdmin.ajaxUrl, {
                action: 'sms_get_transport_dashboard_data',
                data_type: 'statistics',
                nonce: smsTransportAdmin.nonce
            }, function(response) {
                if (response.success) {
                    TransportAdmin.updateDashboardStats(response.data);
                    TransportAdmin.showNotice('success', 'Dashboard refreshed successfully.');
                }
                $button.find('.dashicons').removeClass('sms-loading');
            });
        },

        /**
         * Load route data
         */
        loadRouteData: function(routeId, callback) {
            $.post(smsTransportAdmin.ajaxUrl, {
                action: 'sms_get_route_details',
                route_id: routeId,
                nonce: smsTransportAdmin.nonce
            }, function(response) {
                if (response.success && callback) {
                    callback(response.data);
                }
            });
        },

        /**
         * Load vehicle data
         */
        loadVehicleData: function(routeId, callback) {
            $.post(smsTransportAdmin.ajaxUrl, {
                action: 'sms_get_vehicle_details',
                route_id: routeId,
                nonce: smsTransportAdmin.nonce
            }, function(response) {
                if (response.success && callback) {
                    callback(response.data);
                }
            });
        },

        /**
         * Load driver data
         */
        loadDriverData: function(routeId, callback) {
            $.post(smsTransportAdmin.ajaxUrl, {
                action: 'sms_get_driver_details',
                route_id: routeId,
                nonce: smsTransportAdmin.nonce
            }, function(response) {
                if (response.success && callback) {
                    callback(response.data);
                }
            });
        },

        /**
         * Load dashboard data
         */
        loadDashboardData: function() {
            if ($('.sms-dashboard-stats').length === 0) {
                return;
            }
            
            $.post(smsTransportAdmin.ajaxUrl, {
                action: 'sms_get_transport_dashboard_data',
                data_type: 'statistics',
                nonce: smsTransportAdmin.nonce
            }, function(response) {
                if (response.success) {
                    TransportAdmin.updateDashboardStats(response.data);
                }
            });
        },

        /**
         * Update dashboard statistics
         */
        updateDashboardStats: function(stats) {
            $('.sms-stat-card').each(function() {
                var $card = $(this);
                var $content = $card.find('.sms-stat-content h3');
                var text = $card.find('.sms-stat-content p').text();
                
                if (text.includes('Total Routes')) {
                    $content.text(stats.total_routes);
                } else if (text.includes('Active Routes')) {
                    $content.text(stats.active_routes);
                } else if (text.includes('Students Enrolled')) {
                    $content.text(stats.total_enrollment);
                } else if (text.includes('Capacity Utilization')) {
                    $content.text(stats.capacity_utilization + '%');
                } else if (text.includes('Vehicles Need Attention')) {
                    $content.text(stats.vehicles_with_issues);
                } else if (text.includes('License Renewals Due')) {
                    $content.text(stats.drivers_with_issues);
                }
            });
        },

        /**
         * Create modal
         */
        createModal: function(id, title) {
            var modalHtml = '<div class="sms-modal" id="' + id + '">' +
                '<div class="sms-modal-dialog">' +
                    '<div class="sms-modal-content">' +
                        '<div class="sms-modal-header">' +
                            '<h4 class="sms-modal-title">' + title + '</h4>' +
                            '<button type="button" class="sms-modal-close" data-dismiss="modal">&times;</button>' +
                        '</div>' +
                        '<div class="sms-modal-body"></div>' +
                    '</div>' +
                '</div>' +
            '</div>';
            
            // Remove existing modal
            $('#' + id).remove();
            
            // Add new modal
            $('body').append(modalHtml);
            
            return $('#' + id);
        },

        /**
         * Build route form
         */
        buildRouteForm: function(data) {
            data = data || {};
            
            var form = '<form id="sms-route-form">';
            
            if (data.id) {
                form += '<input type="hidden" id="route_id" name="route_id" value="' + data.id + '">';
            }
            
            form += '<div class="sms-form-grid">' +
                '<div class="sms-form-group">' +
                    '<label for="route_name">Route Name *</label>' +
                    '<input type="text" id="route_name" name="route_name" value="' + (data.route_name || '') + '" required>' +
                '</div>' +
                '<div class="sms-form-group">' +
                    '<label for="route_code">Route Code *</label>' +
                    '<input type="text" id="route_code" name="route_code" value="' + (data.route_code || '') + '" required>' +
                '</div>' +
                '<div class="sms-form-group">' +
                    '<label for="total_capacity">Total Capacity *</label>' +
                    '<input type="number" id="total_capacity" name="total_capacity" value="' + (data.total_capacity || '') + '" min="1" required>' +
                    '<div id="capacity-info"></div>' +
                '</div>' +
                '<div class="sms-form-group">' +
                    '<label for="route_status">Status</label>' +
                    '<select id="route_status" name="route_status">' +
                        '<option value="active"' + (data.route_status === 'active' ? ' selected' : '') + '>Active</option>' +
                        '<option value="inactive"' + (data.route_status === 'inactive' ? ' selected' : '') + '>Inactive</option>' +
                        '<option value="maintenance"' + (data.route_status === 'maintenance' ? ' selected' : '') + '>Maintenance</option>' +
                    '</select>' +
                '</div>' +
            '</div>' +
            '<div class="sms-form-group">' +
                '<label for="route_description">Description</label>' +
                '<textarea id="route_description" name="route_description" rows="3">' + (data.route_description || '') + '</textarea>' +
            '</div>' +
            '<div class="sms-form-actions">' +
                '<button type="button" class="button" data-dismiss="modal">Cancel</button>' +
                '<button type="submit" class="button button-primary">Save Route</button>' +
            '</div>' +
            '</form>';
            
            return form;
        },

        /**
         * Build vehicle form
         */
        buildVehicleForm: function(data, routeId) {
            data = data || {};
            
            var form = '<form id="sms-vehicle-form">' +
                '<input type="hidden" id="route_id" name="route_id" value="' + routeId + '">' +
                '<div class="sms-form-grid">' +
                    '<div class="sms-form-group">' +
                        '<label for="registration_number">Registration Number</label>' +
                        '<input type="text" id="registration_number" name="registration_number" value="' + (data.registration_number || '') + '">' +
                    '</div>' +
                    '<div class="sms-form-group">' +
                        '<label for="make_model">Make & Model</label>' +
                        '<input type="text" id="make_model" name="make_model" value="' + (data.make_model || '') + '">' +
                    '</div>' +
                    '<div class="sms-form-group">' +
                        '<label for="year">Year</label>' +
                        '<input type="number" id="year" name="year" value="' + (data.year || '') + '" min="1990" max="2030">' +
                    '</div>' +
                    '<div class="sms-form-group">' +
                        '<label for="capacity">Vehicle Capacity</label>' +
                        '<input type="number" id="vehicle_capacity" name="capacity" value="' + (data.capacity || '') + '" min="1">' +
                        '<div id="capacity-warning"></div>' +
                    '</div>' +
                    '<div class="sms-form-group">' +
                        '<label for="condition">Condition</label>' +
                        '<select id="condition" name="condition">' +
                            '<option value="">Select Condition</option>' +
                            '<option value="excellent"' + (data.condition === 'excellent' ? ' selected' : '') + '>Excellent</option>' +
                            '<option value="good"' + (data.condition === 'good' ? ' selected' : '') + '>Good</option>' +
                            '<option value="fair"' + (data.condition === 'fair' ? ' selected' : '') + '>Fair</option>' +
                            '<option value="poor"' + (data.condition === 'poor' ? ' selected' : '') + '>Poor</option>' +
                            '<option value="maintenance"' + (data.condition === 'maintenance' ? ' selected' : '') + '>Under Maintenance</option>' +
                        '</select>' +
                    '</div>' +
                    '<div class="sms-form-group">' +
                        '<label for="insurance_expiry">Insurance Expiry</label>' +
                        '<input type="date" id="insurance_expiry" name="insurance_expiry" value="' + (data.insurance_expiry || '') + '">' +
                    '</div>' +
                    '<div class="sms-form-group">' +
                        '<label for="inspection_expiry">Inspection Expiry</label>' +
                        '<input type="date" id="inspection_expiry" name="inspection_expiry" value="' + (data.inspection_expiry || '') + '">' +
                    '</div>' +
                '</div>' +
                '<div class="sms-form-actions">' +
                    '<button type="button" class="button" data-dismiss="modal">Cancel</button>' +
                    '<button type="submit" class="button button-primary">Save Vehicle</button>' +
                '</div>' +
            '</form>';
            
            return form;
        },

        /**
         * Build driver form
         */
        buildDriverForm: function(data, routeId) {
            data = data || {};
            
            var form = '<form id="sms-driver-form">' +
                '<input type="hidden" id="route_id" name="route_id" value="' + routeId + '">' +
                '<div class="sms-form-grid">' +
                    '<div class="sms-form-group">' +
                        '<label for="name">Driver Name</label>' +
                        '<input type="text" id="name" name="name" value="' + (data.name || '') + '">' +
                    '</div>' +
                    '<div class="sms-form-group">' +
                        '<label for="phone">Phone Number</label>' +
                        '<input type="tel" id="phone" name="phone" value="' + (data.phone || '') + '">' +
                    '</div>' +
                    '<div class="sms-form-group">' +
                        '<label for="license_number">License Number</label>' +
                        '<input type="text" id="license_number" name="license_number" value="' + (data.license_number || '') + '">' +
                    '</div>' +
                    '<div class="sms-form-group">' +
                        '<label for="license_expiry">License Expiry</label>' +
                        '<input type="date" id="license_expiry" name="license_expiry" value="' + (data.license_expiry || '') + '">' +
                    '</div>' +
                    '<div class="sms-form-group">' +
                        '<label for="experience">Years of Experience</label>' +
                        '<input type="number" id="experience" name="experience" value="' + (data.experience || '') + '" min="0">' +
                    '</div>' +
                    '<div class="sms-form-group">' +
                        '<label for="emergency_contact">Emergency Contact</label>' +
                        '<input type="tel" id="emergency_contact" name="emergency_contact" value="' + (data.emergency_contact || '') + '">' +
                    '</div>' +
                '</div>' +
                '<div class="sms-form-actions">' +
                    '<button type="button" class="button" data-dismiss="modal">Cancel</button>' +
                    '<button type="submit" class="button button-primary">Save Driver</button>' +
                '</div>' +
            '</form>';
            
            return form;
        },

        /**
         * Show notice
         */
        showNotice: function(type, message) {
            var noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
            var notice = '<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>';
            
            $('.wrap h1').after(notice);
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $('.notice').fadeOut();
            }, 5000);
        },

        /**
         * Initialize tooltips
         */
        initializeTooltips: function() {
            $('[data-tooltip]').each(function() {
                $(this).attr('title', $(this).data('tooltip'));
            });
        },

        /**
         * Initialize date pickers
         */
        initializeDatePickers: function() {
            if ($.fn.datepicker) {
                $('input[type="date"]').datepicker({
                    dateFormat: 'yy-mm-dd',
                    changeMonth: true,
                    changeYear: true
                });
            }
        },

        /**
         * Initialize Select2 dropdowns
         */
        initializeSelect2: function() {
            if ($.fn.select2) {
                $('.sms-select2').select2({
                    width: '100%'
                });
            }
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        TransportAdmin.init();
    });

    // Modal functionality
    $(document).on('click', '[data-dismiss="modal"]', function() {
        $(this).closest('.sms-modal').hide();
    });

    $(document).on('click', '.sms-modal', function(e) {
        if (e.target === this) {
            $(this).hide();
        }
    });

})(jQuery);