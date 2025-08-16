/**
 * M-Pesa Payment JavaScript
 *
 * Handles M-Pesa payment processing on the frontend
 *
 * @package SchoolManagementSystem
 * @subpackage Admin
 * @since 1.0.0
 */

(function($) {
    'use strict';

    /**
     * M-Pesa Payment Handler
     */
    var SMSMpesaPayment = {
        
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
        },
        
        /**
         * Bind events
         */
        bindEvents: function() {
            $(document).on('click', '.sms-mpesa-pay-btn', this.handlePaymentClick);
            $(document).on('submit', '.sms-mpesa-payment-form', this.handlePaymentSubmit);
            $(document).on('click', '.sms-verify-payment-btn', this.handleVerifyPayment);
        },
        
        /**
         * Handle payment button click
         */
        handlePaymentClick: function(e) {
            e.preventDefault();
            
            var $btn = $(this);
            var amount = $btn.data('amount');
            var reference = $btn.data('reference');
            var phoneNumber = $btn.data('phone') || prompt('Enter your M-Pesa phone number (07XXXXXXXX):');
            
            if (!phoneNumber) {
                return;
            }
            
            SMSMpesaPayment.processPayment(amount, phoneNumber, reference, $btn);
        },
        
        /**
         * Handle payment form submit
         */
        handlePaymentSubmit: function(e) {
            e.preventDefault();
            
            var $form = $(this);
            var $btn = $form.find('.sms-submit-payment');
            var formData = $form.serializeArray();
            var data = {};
            
            // Convert form data to object
            $.each(formData, function(i, field) {
                data[field.name] = field.value;
            });
            
            // Validate required fields
            if (!data.amount || !data.phone_number || !data.reference) {
                SMSMpesaPayment.showError('Please fill in all required fields.');
                return;
            }
            
            SMSMpesaPayment.processPayment(data.amount, data.phone_number, data.reference, $btn);
        },
        
        /**
         * Process M-Pesa payment
         */
        processPayment: function(amount, phoneNumber, reference, $btn) {
            var originalText = $btn.text();
            
            // Show loading state
            $btn.prop('disabled', true).text('Processing...');
            SMSMpesaPayment.showMessage('Initiating M-Pesa payment...', 'info');
            
            // Prepare payment data
            var paymentData = {
                action: 'sms_process_payment',
                nonce: smsPaymentGateway.nonce,
                gateway_id: 'mpesa',
                amount: parseFloat(amount),
                phone_number: phoneNumber,
                reference: reference,
                additional_data: {
                    description: 'School Fee Payment - ' + reference
                }
            };
            
            // Make AJAX request
            $.ajax({
                url: smsPaymentGateway.ajaxUrl,
                type: 'POST',
                data: paymentData,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        SMSMpesaPayment.handlePaymentSuccess(response.data);
                    } else {
                        SMSMpesaPayment.showError(response.data || 'Payment failed. Please try again.');
                    }
                },
                error: function(xhr, status, error) {
                    SMSMpesaPayment.showError('Network error. Please check your connection and try again.');
                },
                complete: function() {
                    $btn.prop('disabled', false).text(originalText);
                }
            });
        },
        
        /**
         * Handle payment success
         */
        handlePaymentSuccess: function(data) {
            SMSMpesaPayment.showMessage('Payment request sent successfully! Please check your phone for the M-Pesa prompt.', 'success');
            
            // Store transaction details for verification
            if (data.checkout_request_id) {
                localStorage.setItem('sms_mpesa_checkout_id', data.checkout_request_id);
                localStorage.setItem('sms_mpesa_reference', data.reference);
                
                // Show verification button
                SMSMpesaPayment.showVerificationButton(data.checkout_request_id);
                
                // Auto-verify after 30 seconds
                setTimeout(function() {
                    SMSMpesaPayment.verifyPayment(data.checkout_request_id);
                }, 30000);
            }
            
            // Display transaction details
            SMSMpesaPayment.displayTransactionDetails(data);
        },
        
        /**
         * Handle verify payment button click
         */
        handleVerifyPayment: function(e) {
            e.preventDefault();
            
            var $btn = $(this);
            var checkoutRequestId = $btn.data('checkout-id') || localStorage.getItem('sms_mpesa_checkout_id');
            
            if (!checkoutRequestId) {
                SMSMpesaPayment.showError('No transaction to verify.');
                return;
            }
            
            SMSMpesaPayment.verifyPayment(checkoutRequestId, $btn);
        },
        
        /**
         * Verify payment status
         */
        verifyPayment: function(checkoutRequestId, $btn) {
            var originalText = $btn ? $btn.text() : '';
            
            if ($btn) {
                $btn.prop('disabled', true).text('Verifying...');
            }
            
            SMSMpesaPayment.showMessage('Verifying payment status...', 'info');
            
            $.ajax({
                url: smsPaymentGateway.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sms_verify_payment',
                    nonce: smsPaymentGateway.nonce,
                    gateway_id: 'mpesa',
                    transaction_id: checkoutRequestId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        SMSMpesaPayment.handleVerificationResult(response.data);
                    } else {
                        SMSMpesaPayment.showError(response.data || 'Verification failed.');
                    }
                },
                error: function() {
                    SMSMpesaPayment.showError('Network error during verification.');
                },
                complete: function() {
                    if ($btn) {
                        $btn.prop('disabled', false).text(originalText);
                    }
                }
            });
        },
        
        /**
         * Handle verification result
         */
        handleVerificationResult: function(data) {
            switch (data.status) {
                case 'completed':
                    SMSMpesaPayment.showMessage('Payment completed successfully!', 'success');
                    SMSMpesaPayment.hideVerificationButton();
                    localStorage.removeItem('sms_mpesa_checkout_id');
                    localStorage.removeItem('sms_mpesa_reference');
                    break;
                    
                case 'failed':
                    SMSMpesaPayment.showError('Payment failed: ' + (data.result_desc || 'Unknown error'));
                    SMSMpesaPayment.hideVerificationButton();
                    break;
                    
                case 'cancelled':
                    SMSMpesaPayment.showMessage('Payment was cancelled by user.', 'warning');
                    SMSMpesaPayment.hideVerificationButton();
                    break;
                    
                case 'timeout':
                    SMSMpesaPayment.showMessage('Payment request timed out. Please try again.', 'warning');
                    SMSMpesaPayment.hideVerificationButton();
                    break;
                    
                case 'pending':
                default:
                    SMSMpesaPayment.showMessage('Payment is still pending. Please complete the transaction on your phone.', 'info');
                    break;
            }
            
            // Update transaction details display
            SMSMpesaPayment.updateTransactionDetails(data);
        },
        
        /**
         * Show verification button
         */
        showVerificationButton: function(checkoutRequestId) {
            var $container = $('.sms-payment-actions');
            if ($container.length === 0) {
                $container = $('<div class="sms-payment-actions"></div>').insertAfter('.sms-payment-messages');
            }
            
            var $btn = $('<button type="button" class="button button-secondary sms-verify-payment-btn" data-checkout-id="' + checkoutRequestId + '">Verify Payment Status</button>');
            $container.html($btn);
        },
        
        /**
         * Hide verification button
         */
        hideVerificationButton: function() {
            $('.sms-verify-payment-btn').remove();
        },
        
        /**
         * Display transaction details
         */
        displayTransactionDetails: function(data) {
            var $container = $('.sms-transaction-details');
            if ($container.length === 0) {
                $container = $('<div class="sms-transaction-details"></div>').insertAfter('.sms-payment-messages');
            }
            
            var html = '<h4>Transaction Details</h4>';
            html += '<table class="widefat">';
            html += '<tr><td><strong>Reference:</strong></td><td>' + (data.reference || 'N/A') + '</td></tr>';
            html += '<tr><td><strong>Amount:</strong></td><td>KES ' + (data.amount || 'N/A') + '</td></tr>';
            html += '<tr><td><strong>Phone Number:</strong></td><td>' + (data.phone_number || 'N/A') + '</td></tr>';
            html += '<tr><td><strong>Status:</strong></td><td><span class="status-' + (data.status || 'pending') + '">' + (data.status || 'Pending').toUpperCase() + '</span></td></tr>';
            html += '<tr><td><strong>Transaction ID:</strong></td><td>' + (data.checkout_request_id || 'N/A') + '</td></tr>';
            if (data.mpesa_receipt_number) {
                html += '<tr><td><strong>M-Pesa Receipt:</strong></td><td>' + data.mpesa_receipt_number + '</td></tr>';
            }
            html += '</table>';
            
            $container.html(html);
        },
        
        /**
         * Update transaction details
         */
        updateTransactionDetails: function(data) {
            var $container = $('.sms-transaction-details');
            if ($container.length > 0) {
                SMSMpesaPayment.displayTransactionDetails(data);
            }
        },
        
        /**
         * Show success message
         */
        showMessage: function(message, type) {
            type = type || 'info';
            var $container = $('.sms-payment-messages');
            if ($container.length === 0) {
                $container = $('<div class="sms-payment-messages"></div>').prependTo('.sms-payment-container');
            }
            
            var $message = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            $container.html($message);
            
            // Auto-hide info messages after 5 seconds
            if (type === 'info') {
                setTimeout(function() {
                    $message.fadeOut();
                }, 5000);
            }
        },
        
        /**
         * Show error message
         */
        showError: function(message) {
            SMSMpesaPayment.showMessage(message, 'error');
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        SMSMpesaPayment.init();
        
        // Check for pending transactions on page load
        var checkoutId = localStorage.getItem('sms_mpesa_checkout_id');
        if (checkoutId) {
            SMSMpesaPayment.showVerificationButton(checkoutId);
        }
    });
    
})(jQuery);