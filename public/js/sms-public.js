/**
 * Public JavaScript for School Management System
 */

(function($) {
    'use strict';

    // Initialize when document is ready
    $(document).ready(function() {
        SMS_Public.init();
    });

    // Main public object
    window.SMS_Public = {
        
        // Initialize public functionality
        init: function() {
            this.bindEvents();
            this.initPaymentForms();
            this.initFormValidation();
        },

        // Bind event handlers
        bindEvents: function() {
            // Payment method selection
            $(document).on('click', '.sms-payment-method', this.selectPaymentMethod);
            
            // Form submission
            $(document).on('submit', '.sms-payment-form', this.handlePaymentForm);
            
            // Phone number formatting
            $(document).on('input', 'input[type="tel"]', this.formatPhoneInput);
            
            // Auto-refresh for payment status
            if ($('.sms-payment-status').length) {
                this.checkPaymentStatus();
            }
        },

        // Initialize payment forms
        initPaymentForms: function() {
            // Set default payment method if only one is available
            var $methods = $('.sms-payment-method');
            if ($methods.length === 1) {
                $methods.first().addClass('selected');
                $('input[name="payment_method"]').val($methods.first().data('method'));
            }
        },

        // Initialize form validation
        initFormValidation: function() {
            // Real-time validation
            $(document).on('blur', 'input[required]', function() {
                SMS_Public.validateField($(this));
            });

            $(document).on('blur', 'input[type="email"]', function() {
                if ($(this).val() && !SMS_Public.validateEmail($(this).val())) {
                    SMS_Public.showFieldError($(this), 'Please enter a valid email address');
                } else {
                    SMS_Public.clearFieldError($(this));
                }
            });

            $(document).on('blur', 'input[type="tel"]', function() {
                if ($(this).val() && !SMS_Public.validatePhone($(this).val())) {
                    SMS_Public.showFieldError($(this), 'Please enter a valid phone number');
                } else {
                    SMS_Public.clearFieldError($(this));
                }
            });
        },

        // Select payment method
        selectPaymentMethod: function(e) {
            e.preventDefault();
            
            var $method = $(this);
            var methodValue = $method.data('method');
            
            // Remove selection from other methods
            $('.sms-payment-method').removeClass('selected');
            
            // Select this method
            $method.addClass('selected');
            
            // Update hidden input
            $('input[name="payment_method"]').val(methodValue);
            
            // Show/hide method-specific fields
            $('.sms-payment-fields').hide();
            $('.sms-payment-fields[data-method="' + methodValue + '"]').show();
        },

        // Handle payment form submission
        handlePaymentForm: function(e) {
            e.preventDefault();
            
            var $form = $(this);
            
            // Validate form
            if (!SMS_Public.validateForm($form)) {
                return false;
            }
            
            // Show loading state
            SMS_Public.showLoading($form);
            
            // Submit form via AJAX
            var formData = new FormData($form[0]);
            formData.append('action', 'sms_process_payment');
            formData.append('nonce', sms_public_ajax.nonce);
            
            $.ajax({
                url: sms_public_ajax.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    SMS_Public.hideLoading($form);
                    
                    if (response.success) {
                        if (response.data.redirect_url) {
                            window.location.href = response.data.redirect_url;
                        } else {
                            SMS_Public.showMessage(response.data.message, 'success');
                        }
                    } else {
                        SMS_Public.showMessage(response.data.message || 'Payment processing failed', 'error');
                    }
                },
                error: function() {
                    SMS_Public.hideLoading($form);
                    SMS_Public.showMessage('An error occurred. Please try again.', 'error');
                }
            });
        },

        // Format phone input
        formatPhoneInput: function() {
            var $input = $(this);
            var value = $input.val().replace(/[^0-9+]/g, '');
            
            // Auto-format Kenyan numbers
            if (value.length === 10 && value.charAt(0) === '0') {
                value = '+254' + value.substring(1);
            } else if (value.length === 12 && value.substring(0, 3) === '254') {
                value = '+' + value;
            }
            
            $input.val(value);
        },

        // Check payment status
        checkPaymentStatus: function() {
            var $status = $('.sms-payment-status');
            var transactionId = $status.data('transaction-id');
            
            if (!transactionId) return;
            
            var checkStatus = function() {
                $.post(sms_public_ajax.ajax_url, {
                    action: 'sms_check_payment_status',
                    transaction_id: transactionId,
                    nonce: sms_public_ajax.nonce
                }, function(response) {
                    if (response.success) {
                        var status = response.data.status;
                        
                        $status.removeClass('pending completed failed')
                               .addClass(status.toLowerCase());
                        
                        $status.find('.status-text').text(response.data.status_text);
                        
                        if (status === 'completed' || status === 'failed') {
                            clearInterval(statusInterval);
                            
                            if (status === 'completed') {
                                SMS_Public.showMessage('Payment completed successfully!', 'success');
                                
                                // Redirect after 3 seconds
                                setTimeout(function() {
                                    if (response.data.redirect_url) {
                                        window.location.href = response.data.redirect_url;
                                    }
                                }, 3000);
                            } else {
                                SMS_Public.showMessage('Payment failed. Please try again.', 'error');
                            }
                        }
                    }
                });
            };
            
            // Check status every 5 seconds
            var statusInterval = setInterval(checkStatus, 5000);
            
            // Stop checking after 5 minutes
            setTimeout(function() {
                clearInterval(statusInterval);
            }, 300000);
        },

        // Validate entire form
        validateForm: function($form) {
            var isValid = true;
            
            $form.find('input[required], select[required], textarea[required]').each(function() {
                if (!SMS_Public.validateField($(this))) {
                    isValid = false;
                }
            });
            
            // Validate payment method selection
            if (!$('input[name="payment_method"]').val()) {
                SMS_Public.showMessage('Please select a payment method', 'error');
                isValid = false;
            }
            
            return isValid;
        },

        // Validate individual field
        validateField: function($field) {
            var value = $field.val().trim();
            var isRequired = $field.prop('required');
            
            if (isRequired && !value) {
                SMS_Public.showFieldError($field, 'This field is required');
                return false;
            }
            
            if (value) {
                var type = $field.attr('type');
                
                if (type === 'email' && !SMS_Public.validateEmail(value)) {
                    SMS_Public.showFieldError($field, 'Please enter a valid email address');
                    return false;
                }
                
                if (type === 'tel' && !SMS_Public.validatePhone(value)) {
                    SMS_Public.showFieldError($field, 'Please enter a valid phone number');
                    return false;
                }
                
                var min = $field.attr('min');
                if (min && parseFloat(value) < parseFloat(min)) {
                    SMS_Public.showFieldError($field, 'Value must be at least ' + min);
                    return false;
                }
                
                var max = $field.attr('max');
                if (max && parseFloat(value) > parseFloat(max)) {
                    SMS_Public.showFieldError($field, 'Value must not exceed ' + max);
                    return false;
                }
            }
            
            SMS_Public.clearFieldError($field);
            return true;
        },

        // Show field error
        showFieldError: function($field, message) {
            $field.addClass('error');
            
            var $errorMsg = $field.siblings('.error-message');
            if (!$errorMsg.length) {
                $errorMsg = $('<div class="error-message"></div>');
                $field.after($errorMsg);
            }
            
            $errorMsg.text(message);
        },

        // Clear field error
        clearFieldError: function($field) {
            $field.removeClass('error');
            $field.siblings('.error-message').remove();
        },

        // Show loading state
        showLoading: function($element) {
            var $submitBtn = $element.find('input[type="submit"], button[type="submit"]');
            $submitBtn.prop('disabled', true);
            
            var originalText = $submitBtn.val() || $submitBtn.text();
            $submitBtn.data('original-text', originalText);
            
            if ($submitBtn.is('input')) {
                $submitBtn.val(sms_public_ajax.strings.processing);
            } else {
                $submitBtn.html('<span class="sms-loading"></span> ' + sms_public_ajax.strings.processing);
            }
        },

        // Hide loading state
        hideLoading: function($element) {
            var $submitBtn = $element.find('input[type="submit"], button[type="submit"]');
            $submitBtn.prop('disabled', false);
            
            var originalText = $submitBtn.data('original-text');
            if (originalText) {
                if ($submitBtn.is('input')) {
                    $submitBtn.val(originalText);
                } else {
                    $submitBtn.text(originalText);
                }
            }
        },

        // Show message to user
        showMessage: function(message, type) {
            type = type || 'info';
            
            var $alert = $('<div class="sms-alert sms-alert-' + type + '">' + message + '</div>');
            
            // Insert at top of main content
            if ($('.sms-public-container').length) {
                $('.sms-public-container').prepend($alert);
            } else {
                $('body').prepend($alert);
            }
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $alert.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
            
            // Scroll to message
            $('html, body').animate({
                scrollTop: $alert.offset().top - 20
            }, 500);
        },

        // Validate email
        validateEmail: function(email) {
            var pattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return pattern.test(email);
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

        // Format currency
        formatCurrency: function(amount, currency) {
            currency = currency || 'KES';
            return currency + ' ' + parseFloat(amount).toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }
    };

})(jQuery);