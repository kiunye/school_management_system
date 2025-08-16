/**
 * Airtel Money Admin JavaScript
 *
 * @package SchoolManagementSystem
 * @subpackage Admin
 * @since 1.0.0
 */

(function($) {
    'use strict';

    /**
     * Airtel Money Admin Handler
     */
    var AirtelMoneyAdmin = {
        
        /**
         * Initialize the admin interface
         */
        init: function() {
            this.bindEvents();
            this.initializeComponents();
        },
        
        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Configuration form submission
            $('#airtel-config-form').on('submit', this.handleConfigSave.bind(this));
            
            // Connection test
            $('#test-airtel-connection').on('click', this.testConnection.bind(this));
            
            // Test payment form
            $('#airtel-test-form').on('submit', this.handleTestPayment.bind(this));
            
            // Payment verification
            $('#verify-payment').on('click', this.verifyCurrentPayment.bind(this));
            $('.verify-transaction').on('click', this.verifyTransaction.bind(this));
            
            // Form validation
            $('#test_phone').on('input', this.validatePhoneNumber.bind(this));
            $('#test_amount').on('input', this.validateAmount.bind(this));
            
            // Auto-refresh transaction status
            this.startStatusPolling();
        },
        
        /**
         * Initialize components
         */
        initializeComponents: function() {
            // Initialize tooltips
            this.initTooltips();
            
            // Load initial statistics
            this.loadStatistics();
            
            // Check connection status
            this.checkConnectionStatus();
        },
        
        /**
         * Handle configuration save
         */
        handleConfigSave: function(e) {
            e.preventDefault();
            
            var form = $(e.target);
            var submitBtn = form.find('button[type="submit"]');
            var formData = form.serialize();
            
            // Show loading state
            submitBtn.prop('disabled', true).text('Saving...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData + '&action=sms_save_airtel_config&nonce=' + smsAirtelAdmin.nonce,
                success: function(response) {
                    if (response.success) {
                        AirtelMoneyAdmin.showNotice('Configuration saved successfully!', 'success');
                        // Reload page to reflect changes
                        setTimeout(function() {
                            window.location.reload();
                        }, 1500);
                    } else {
                        AirtelMoneyAdmin.showNotice('Failed to save configuration: ' + response.data, 'error');
                    }
                },
                error: function() {
                    AirtelMoneyAdmin.showNotice('Request failed. Please try again.', 'error');
                },
                complete: function() {
                    submitBtn.prop('disabled', false).text('Save Configuration');
                }
            });
        },
        
        /**
         * Test Airtel Money connection
         */
        testConnection: function(e) {
            e.preventDefault();
            
            var button = $(e.target);
            var resultDiv = $('#connection-result');
            
            button.prop('disabled', true).text('Testing...');
            resultDiv.html('<div class="connection-status testing">Testing connection...</div>');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'sms_test_airtel_connection',
                    nonce: smsAirtelAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var html = '<div class="connection-status connected">Connected</div>';
                        html += '<div class="success-message">' + response.data.message + '</div>';
                        if (response.data.environment) {
                            html += '<p><strong>Environment:</strong> <span class="env-indicator ' + response.data.environment + '">' + response.data.environment + '</span></p>';
                        }
                        if (response.data.merchant_id) {
                            html += '<p><strong>Merchant ID:</strong> <code>' + response.data.merchant_id + '</code></p>';
                        }
                        resultDiv.html(html);
                    } else {
                        resultDiv.html('<div class="connection-status disconnected">Connection Failed</div><div class="error-message">' + response.data + '</div>');
                    }
                },
                error: function() {
                    resultDiv.html('<div class="connection-status disconnected">Connection Failed</div><div class="error-message">Request failed</div>');
                },
                complete: function() {
                    button.prop('disabled', false).text('Test Connection');
                }
            });
        },
        
        /**
         * Handle test payment
         */
        handleTestPayment: function(e) {
            e.preventDefault();
            
            var form = $(e.target);
            var submitBtn = form.find('button[type="submit"]');
            var resultDiv = $('#test-result');
            
            // Validate form
            if (!this.validateTestForm(form)) {
                return;
            }
            
            var formData = {
                action: 'sms_test_airtel_payment',
                nonce: smsAirtelAdmin.nonce,
                amount: $('#test_amount').val(),
                phone_number: $('#test_phone').val(),
                reference: $('#test_reference').val(),
                description: $('#test_description').val()
            };
            
            // Show loading state
            submitBtn.prop('disabled', true).text('Processing...');
            resultDiv.html('<div class="notice notice-info"><p>Initiating payment...</p></div>');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        AirtelMoneyAdmin.currentTransactionId = response.data.transaction_id;
                        $('#verify-payment').prop('disabled', false);
                        
                        var html = '<div class="notice notice-success"><p><strong>Payment initiated successfully!</strong></p></div>';
                        html += AirtelMoneyAdmin.formatTransactionDetails(response.data);
                        
                        resultDiv.html(html);
                        
                        // Start polling for status updates
                        AirtelMoneyAdmin.startTransactionPolling(response.data.transaction_id);
                        
                    } else {
                        resultDiv.html('<div class="notice notice-error"><p><strong>Payment failed:</strong> ' + response.data + '</p></div>');
                    }
                },
                error: function(xhr, status, error) {
                    resultDiv.html('<div class="notice notice-error"><p><strong>Request failed:</strong> ' + error + '</p></div>');
                },
                complete: function() {
                    submitBtn.prop('disabled', false).text('Initiate Test Payment');
                }
            });
        },
        
        /**
         * Verify current payment
         */
        verifyCurrentPayment: function(e) {
            e.preventDefault();
            
            if (!this.currentTransactionId) {
                this.showNotice('No transaction to verify', 'error');
                return;
            }
            
            this.verifyPayment(this.currentTransactionId);
        },
        
        /**
         * Verify specific transaction
         */
        verifyTransaction: function(e) {
            e.preventDefault();
            
            var transactionId = $(e.target).data('transaction-id');
            this.verifyPayment(transactionId);
        },
        
        /**
         * Verify payment by transaction ID
         */
        verifyPayment: function(transactionId) {
            var resultDiv = $('#test-result');
            
            resultDiv.html('<div class="notice notice-info"><p>Verifying payment...</p></div>');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'sms_verify_airtel_payment',
                    nonce: smsAirtelAdmin.nonce,
                    transaction_id: transactionId
                },
                success: function(response) {
                    if (response.success) {
                        var html = '<div class="notice notice-success"><p><strong>Payment verification completed!</strong></p></div>';
                        html += AirtelMoneyAdmin.formatVerificationResults(response.data);
                        resultDiv.html(html);
                        
                        // Update transaction status in table if visible
                        AirtelMoneyAdmin.updateTransactionStatus(transactionId, response.data.status);
                        
                    } else {
                        resultDiv.html('<div class="notice notice-error"><p><strong>Verification failed:</strong> ' + response.data + '</p></div>');
                    }
                },
                error: function(xhr, status, error) {
                    resultDiv.html('<div class="notice notice-error"><p><strong>Verification request failed:</strong> ' + error + '</p></div>');
                }
            });
        },
        
        /**
         * Validate test form
         */
        validateTestForm: function(form) {
            var isValid = true;
            
            // Clear previous validation errors
            form.find('.form-invalid').removeClass('form-invalid');
            form.find('.validation-error').remove();
            
            // Validate amount
            var amount = parseFloat($('#test_amount').val());
            if (isNaN(amount) || amount < 1 || amount > 70000) {
                $('#test_amount').addClass('form-invalid');
                $('#test_amount').after('<span class="validation-error">Amount must be between KES 1 and KES 70,000</span>');
                isValid = false;
            }
            
            // Validate phone number
            var phone = $('#test_phone').val().trim();
            if (!phone.match(/^07\d{8}$/)) {
                $('#test_phone').addClass('form-invalid');
                $('#test_phone').after('<span class="validation-error">Please enter a valid Airtel Kenya phone number (07XXXXXXXX)</span>');
                isValid = false;
            }
            
            // Validate reference
            var reference = $('#test_reference').val().trim();
            if (!reference) {
                $('#test_reference').addClass('form-invalid');
                $('#test_reference').after('<span class="validation-error">Payment reference is required</span>');
                isValid = false;
            }
            
            return isValid;
        },
        
        /**
         * Validate phone number input
         */
        validatePhoneNumber: function(e) {
            var input = $(e.target);
            var phone = input.val().trim();
            
            input.removeClass('form-invalid');
            input.siblings('.validation-error').remove();
            
            if (phone && !phone.match(/^07\d{8}$/)) {
                input.addClass('form-invalid');
                input.after('<span class="validation-error">Please enter a valid Airtel Kenya phone number (07XXXXXXXX)</span>');
            }
        },
        
        /**
         * Validate amount input
         */
        validateAmount: function(e) {
            var input = $(e.target);
            var amount = parseFloat(input.val());
            
            input.removeClass('form-invalid');
            input.siblings('.validation-error').remove();
            
            if (isNaN(amount) || amount < 1 || amount > 70000) {
                input.addClass('form-invalid');
                input.after('<span class="validation-error">Amount must be between KES 1 and KES 70,000</span>');
            }
        },
        
        /**
         * Format transaction details for display
         */
        formatTransactionDetails: function(data) {
            var html = '<div class="transaction-details">';
            html += '<h4>Transaction Details:</h4>';
            html += '<p><strong>Transaction ID:</strong> <code>' + data.transaction_id + '</code></p>';
            html += '<p><strong>Status:</strong> <span class="status-' + data.status + '">' + data.status + '</span></p>';
            html += '<p><strong>Amount:</strong> KES ' + parseFloat(data.amount).toFixed(2) + '</p>';
            html += '<p><strong>Phone:</strong> ' + data.phone_number + '</p>';
            if (data.response_message) {
                html += '<p><strong>Message:</strong> ' + data.response_message + '</p>';
            }
            if (data.airtel_transaction_id) {
                html += '<p><strong>Airtel Transaction ID:</strong> <code>' + data.airtel_transaction_id + '</code></p>';
            }
            html += '</div>';
            return html;
        },
        
        /**
         * Format verification results for display
         */
        formatVerificationResults: function(data) {
            var html = '<div class="transaction-details">';
            html += '<h4>Verification Results:</h4>';
            html += '<p><strong>Transaction ID:</strong> <code>' + data.transaction_id + '</code></p>';
            html += '<p><strong>Status:</strong> <span class="status-' + data.status + '">' + data.status + '</span></p>';
            if (data.amount) {
                html += '<p><strong>Amount:</strong> KES ' + parseFloat(data.amount).toFixed(2) + '</p>';
            }
            if (data.airtel_transaction_id) {
                html += '<p><strong>Airtel Transaction ID:</strong> <code>' + data.airtel_transaction_id + '</code></p>';
            }
            if (data.status_message) {
                html += '<p><strong>Status Message:</strong> ' + data.status_message + '</p>';
            }
            if (data.currency) {
                html += '<p><strong>Currency:</strong> ' + data.currency + '</p>';
            }
            html += '</div>';
            return html;
        },
        
        /**
         * Update transaction status in table
         */
        updateTransactionStatus: function(transactionId, status) {
            $('tr').each(function() {
                var row = $(this);
                var rowTransactionId = row.find('code').first().text();
                if (rowTransactionId === transactionId) {
                    row.find('.status-' + status.replace(/[^a-z]/gi, '')).removeClass().addClass('status-' + status).text(status.charAt(0).toUpperCase() + status.slice(1));
                }
            });
        },
        
        /**
         * Start polling for transaction status updates
         */
        startTransactionPolling: function(transactionId) {
            var pollCount = 0;
            var maxPolls = 12; // Poll for 2 minutes (12 * 10 seconds)
            
            var pollInterval = setInterval(function() {
                pollCount++;
                
                if (pollCount > maxPolls) {
                    clearInterval(pollInterval);
                    return;
                }
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'sms_verify_airtel_payment',
                        nonce: smsAirtelAdmin.nonce,
                        transaction_id: transactionId
                    },
                    success: function(response) {
                        if (response.success && response.data.status !== 'pending') {
                            clearInterval(pollInterval);
                            
                            // Update display with final status
                            var resultDiv = $('#test-result');
                            var currentHtml = resultDiv.html();
                            var statusClass = 'status-' + response.data.status;
                            var newHtml = currentHtml.replace(/status-pending/g, statusClass);
                            newHtml = newHtml.replace(/pending/g, response.data.status);
                            resultDiv.html(newHtml);
                            
                            // Show notification
                            if (response.data.status === 'completed') {
                                AirtelMoneyAdmin.showNotice('Payment completed successfully!', 'success');
                            } else if (response.data.status === 'failed') {
                                AirtelMoneyAdmin.showNotice('Payment failed.', 'error');
                            }
                        }
                    }
                });
            }, 10000); // Poll every 10 seconds
        },
        
        /**
         * Start general status polling
         */
        startStatusPolling: function() {
            // Poll for general updates every 30 seconds
            setInterval(function() {
                AirtelMoneyAdmin.loadStatistics();
            }, 30000);
        },
        
        /**
         * Load statistics
         */
        loadStatistics: function() {
            // This would load updated statistics via AJAX
            // Implementation depends on specific requirements
        },
        
        /**
         * Check connection status
         */
        checkConnectionStatus: function() {
            var statusIndicator = $('.connection-status');
            if (statusIndicator.length === 0) return;
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'sms_test_airtel_connection',
                    nonce: smsAirtelAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        statusIndicator.removeClass('disconnected testing').addClass('connected').text('Connected');
                    } else {
                        statusIndicator.removeClass('connected testing').addClass('disconnected').text('Disconnected');
                    }
                },
                error: function() {
                    statusIndicator.removeClass('connected testing').addClass('disconnected').text('Disconnected');
                }
            });
        },
        
        /**
         * Initialize tooltips
         */
        initTooltips: function() {
            // Add tooltips to help icons
            $('[data-tooltip]').each(function() {
                var element = $(this);
                var tooltip = element.data('tooltip');
                
                element.attr('title', tooltip);
            });
        },
        
        /**
         * Show admin notice
         */
        showNotice: function(message, type) {
            type = type || 'info';
            
            var notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            $('.wrap h1').after(notice);
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                notice.fadeOut(function() {
                    notice.remove();
                });
            }, 5000);
        },
        
        /**
         * Current transaction ID for verification
         */
        currentTransactionId: null
    };

    // Initialize when document is ready
    $(document).ready(function() {
        AirtelMoneyAdmin.init();
    });

    // Make AirtelMoneyAdmin globally available
    window.AirtelMoneyAdmin = AirtelMoneyAdmin;

})(jQuery);