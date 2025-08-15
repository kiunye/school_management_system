/**
 * Admin JavaScript for School Management System
 */

(function($) {
    'use strict';

    // Initialize when document is ready
    $(document).ready(function() {
        SMS_Admin.init();
    });

    // Main admin object
    window.SMS_Admin = {
        
        // Initialize admin functionality
        init: function() {
            this.bindEvents();
            this.initDataTables();
            this.initFormValidation();
            this.initAjaxForms();
        },

        // Bind event handlers
        bindEvents: function() {
            // Confirm delete actions
            $(document).on('click', '.sms-delete-confirm', this.confirmDelete);
            
            // Toggle sections
            $(document).on('click', '.sms-toggle-section', this.toggleSection);
            
            // Form submission with loading state
            $(document).on('submit', '.sms-form', this.handleFormSubmit);
            
            // Auto-save functionality
            $(document).on('change', '.sms-auto-save', this.autoSave);
        },

        // Initialize data tables
        initDataTables: function() {
            if ($.fn.DataTable) {
                $('.sms-data-table').DataTable({
                    responsive: true,
                    pageLength: 25,
                    language: {
                        search: sms_admin_ajax.strings.search || 'Search:',
                        lengthMenu: 'Show _MENU_ entries',
                        info: 'Showing _START_ to _END_ of _TOTAL_ entries',
                        paginate: {
                            first: 'First',
                            last: 'Last',
                            next: 'Next',
                            previous: 'Previous'
                        }
                    }
                });
            }
        },

        // Initialize form validation
        initFormValidation: function() {
            // Phone number validation
            $(document).on('blur', 'input[type="tel"]', function() {
                var phone = $(this).val();
                if (phone && !SMS_Admin.validatePhone(phone)) {
                    $(this).addClass('error');
                    SMS_Admin.showMessage('Invalid phone number format', 'error');
                } else {
                    $(this).removeClass('error');
                }
            });

            // Email validation
            $(document).on('blur', 'input[type="email"]', function() {
                var email = $(this).val();
                if (email && !SMS_Admin.validateEmail(email)) {
                    $(this).addClass('error');
                    SMS_Admin.showMessage('Invalid email format', 'error');
                } else {
                    $(this).removeClass('error');
                }
            });

            // Required field validation
            $(document).on('blur', 'input[required], select[required], textarea[required]', function() {
                if (!$(this).val()) {
                    $(this).addClass('error');
                } else {
                    $(this).removeClass('error');
                }
            });
        },

        // Initialize AJAX forms
        initAjaxForms: function() {
            $(document).on('submit', '.sms-ajax-form', function(e) {
                e.preventDefault();
                SMS_Admin.submitAjaxForm($(this));
            });
        },

        // Confirm delete action
        confirmDelete: function(e) {
            e.preventDefault();
            
            if (confirm(sms_admin_ajax.strings.confirm_delete)) {
                var url = $(this).attr('href');
                window.location.href = url;
            }
        },

        // Toggle section visibility
        toggleSection: function(e) {
            e.preventDefault();
            
            var target = $(this).data('target');
            $(target).slideToggle();
            
            var icon = $(this).find('.dashicons');
            if (icon.hasClass('dashicons-arrow-down')) {
                icon.removeClass('dashicons-arrow-down').addClass('dashicons-arrow-up');
            } else {
                icon.removeClass('dashicons-arrow-up').addClass('dashicons-arrow-down');
            }
        },

        // Handle form submission with loading state
        handleFormSubmit: function(e) {
            var $form = $(this);
            var $submitBtn = $form.find('input[type="submit"], button[type="submit"]');
            
            // Show loading state
            $submitBtn.prop('disabled', true);
            $submitBtn.val(sms_admin_ajax.strings.processing);
            
            // Add loading spinner
            if (!$form.find('.sms-loading').length) {
                $submitBtn.after('<span class="sms-loading"></span>');
            }
        },

        // Auto-save functionality
        autoSave: function() {
            var $field = $(this);
            var data = {
                action: 'sms_auto_save',
                nonce: sms_admin_ajax.nonce,
                field: $field.attr('name'),
                value: $field.val(),
                post_id: $field.data('post-id')
            };

            $.post(sms_admin_ajax.ajax_url, data, function(response) {
                if (response.success) {
                    $field.addClass('sms-saved');
                    setTimeout(function() {
                        $field.removeClass('sms-saved');
                    }, 2000);
                }
            });
        },

        // Submit AJAX form
        submitAjaxForm: function($form) {
            var formData = new FormData($form[0]);
            formData.append('nonce', sms_admin_ajax.nonce);

            $.ajax({
                url: sms_admin_ajax.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                beforeSend: function() {
                    SMS_Admin.showLoading($form);
                },
                success: function(response) {
                    SMS_Admin.hideLoading($form);
                    
                    if (response.success) {
                        SMS_Admin.showMessage(response.data.message || 'Operation completed successfully', 'success');
                        
                        // Reload page if specified
                        if (response.data.reload) {
                            setTimeout(function() {
                                location.reload();
                            }, 1500);
                        }
                    } else {
                        SMS_Admin.showMessage(response.data.message || 'An error occurred', 'error');
                    }
                },
                error: function() {
                    SMS_Admin.hideLoading($form);
                    SMS_Admin.showMessage(sms_admin_ajax.strings.error, 'error');
                }
            });
        },

        // Show loading state
        showLoading: function($element) {
            $element.find('input[type="submit"], button[type="submit"]').prop('disabled', true);
            if (!$element.find('.sms-loading').length) {
                $element.append('<div class="sms-loading"></div>');
            }
        },

        // Hide loading state
        hideLoading: function($element) {
            $element.find('input[type="submit"], button[type="submit"]').prop('disabled', false);
            $element.find('.sms-loading').remove();
        },

        // Show message to user
        showMessage: function(message, type) {
            type = type || 'info';
            
            var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            
            // Insert after page title or at top of content
            if ($('.wrap h1').length) {
                $('.wrap h1').after($notice);
            } else {
                $('.wrap').prepend($notice);
            }

            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        },

        // Validate phone number (Kenyan format)
        validatePhone: function(phone) {
            var patterns = [
                /^\+254[17]\d{8}$/,  // +254 format
                /^254[17]\d{8}$/,    // 254 format
                /^0[17]\d{8}$/       // 0 format
            ];
            
            return patterns.some(function(pattern) {
                return pattern.test(phone);
            });
        },

        // Validate email
        validateEmail: function(email) {
            var pattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return pattern.test(email);
        },

        // Format currency
        formatCurrency: function(amount, currency) {
            currency = currency || 'KES';
            return currency + ' ' + parseFloat(amount).toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        },

        // Format phone number
        formatPhone: function(phone) {
            // Remove all non-numeric characters
            phone = phone.replace(/[^0-9]/g, '');
            
            // Convert to +254 format
            if (phone.length === 10 && phone.charAt(0) === '0') {
                return '+254' + phone.substring(1);
            } else if (phone.length === 12 && phone.substring(0, 3) === '254') {
                return '+' + phone;
            } else if (phone.length === 13 && phone.substring(0, 4) === '+254') {
                return phone;
            }
            
            return phone;
        },

        // Utility function to get URL parameter
        getUrlParameter: function(name) {
            name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
            var regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
            var results = regex.exec(location.search);
            return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
        }
    };

})(jQuery);