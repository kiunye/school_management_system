/**
 * Transaction Admin JavaScript
 *
 * Handles transaction management interface interactions
 *
 * @package SchoolManagementSystem
 * @since 1.0.0
 */

(function($) {
    'use strict';
    
    var TransactionAdmin = {
        
        /**
         * Initialize the transaction admin interface
         */
        init: function() {
            this.bindEvents();
            this.initializeComponents();
        },
        
        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Status update handlers
            $(document).on('click', '#update-status', this.handleStatusUpdate);
            $(document).on('click', '#verify-transaction', this.handleVerification);
            $(document).on('click', '#send-receipt, #resend-receipt', this.handleSendReceipt);
            
            // Receipt preview and download
            $(document).on('click', '#preview-receipt', this.handleReceiptPreview);
            $(document).on('click', '#download-receipt', this.handleReceiptDownload);
            
            // Quick actions
            $(document).on('click', '#update-pending-transactions', this.handleUpdatePendingTransactions);
            $(document).on('click', '#send-pending-receipts', this.handleSendPendingReceipts);
            
            // Bulk actions
            $(document).on('click', '#doaction, #doaction2', this.handleBulkActions);
            
            // Modal handlers
            $(document).on('click', '.sms-transaction-modal-close', this.closeModal);
            $(document).on('click', '.sms-transaction-modal', function(e) {
                if (e.target === this) {
                    TransactionAdmin.closeModal();
                }
            });
            
            // Auto-refresh for pending transactions
            this.setupAutoRefresh();
        },
        
        /**
         * Initialize components
         */
        initializeComponents: function() {
            // Initialize tooltips if available
            if (typeof $.fn.tooltip === 'function') {
                $('[data-toggle="tooltip"]').tooltip();
            }
            
            // Initialize select2 if available
            if (typeof $.fn.select2 === 'function') {
                $('.sms-select2').select2({
                    width: '100%'
                });
            }
            
            // Initialize date pickers if available
            if (typeof $.fn.datepicker === 'function') {
                $('.sms-datepicker').datepicker({
                    dateFormat: 'yy-mm-dd'
                });
            }
        },
        
        /**
         * Handle status update
         */
        handleStatusUpdate: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var newStatus = $('#new_status').val();
            var reason = $('textarea[name="status_reason"]').val();
            var nonce = $('#sms_transaction_nonce').val();
            var transactionId = TransactionAdmin.getTransactionId();
            
            if (!newStatus) {
                TransactionAdmin.showError('Please select a status');
                return;
            }
            
            TransactionAdmin.setButtonLoading($button, 'Updating...');
            
            $.ajax({
                url: smsTransactionAdmin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'sms_update_transaction_status',
                    transaction_id: transactionId,
                    status: newStatus,
                    reason: reason,
                    nonce: nonce
                },
                success: function(response) {
                    if (response.success) {
                        TransactionAdmin.showSuccess('Transaction status updated successfully');
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        TransactionAdmin.showError(response.data || 'Failed to update status');
                        TransactionAdmin.resetButton($button, 'Update Status');
                    }
                },
                error: function() {
                    TransactionAdmin.showError('Network error occurred');
                    TransactionAdmin.resetButton($button, 'Update Status');
                }
            });
        },
        
        /**
         * Handle transaction verification
         */
        handleVerification: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var nonce = $('#sms_transaction_nonce').val();
            var transactionId = TransactionAdmin.getTransactionId();
            
            TransactionAdmin.setButtonLoading($button, 'Verifying...');
            
            $.ajax({
                url: smsTransactionAdmin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'sms_verify_transaction',
                    transaction_id: transactionId,
                    nonce: nonce
                },
                success: function(response) {
                    if (response.success) {
                        TransactionAdmin.showSuccess('Transaction verified successfully');
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        TransactionAdmin.showError(response.data || 'Verification failed');
                        TransactionAdmin.resetButton($button, 'Verify with Gateway');
                    }
                },
                error: function() {
                    TransactionAdmin.showError('Network error occurred');
                    TransactionAdmin.resetButton($button, 'Verify with Gateway');
                }
            });
        },
        
        /**
         * Handle send receipt
         */
        handleSendReceipt: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var nonce = $('#sms_transaction_nonce').val();
            var transactionId = TransactionAdmin.getTransactionId();
            var isResend = $button.attr('id') === 'resend-receipt';
            
            TransactionAdmin.setButtonLoading($button, 'Sending...');
            
            $.ajax({
                url: smsTransactionAdmin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'sms_send_receipt',
                    transaction_id: transactionId,
                    nonce: nonce
                },
                success: function(response) {
                    if (response.success) {
                        TransactionAdmin.showSuccess('Receipt sent successfully');
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        TransactionAdmin.showError(response.data || 'Failed to send receipt');
                        TransactionAdmin.resetButton($button, isResend ? 'Resend Receipt' : 'Send Receipt');
                    }
                },
                error: function() {
                    TransactionAdmin.showError('Network error occurred');
                    TransactionAdmin.resetButton($button, isResend ? 'Resend Receipt' : 'Send Receipt');
                }
            });
        },
        
        /**
         * Handle receipt preview
         */
        handleReceiptPreview: function(e) {
            e.preventDefault();
            
            var transactionId = TransactionAdmin.getTransactionId();
            var nonce = TransactionAdmin.createNonce('sms_receipt_preview');
            var url = smsTransactionAdmin.ajaxurl + '?action=sms_preview_receipt&transaction_id=' + transactionId + '&nonce=' + nonce;
            
            window.open(url, '_blank', 'width=800,height=600,scrollbars=yes');
        },
        
        /**
         * Handle receipt download
         */
        handleReceiptDownload: function(e) {
            e.preventDefault();
            
            var transactionId = TransactionAdmin.getTransactionId();
            var nonce = TransactionAdmin.createNonce('sms_receipt_download');
            var url = smsTransactionAdmin.ajaxurl + '?action=sms_download_receipt&transaction_id=' + transactionId + '&nonce=' + nonce;
            
            window.location.href = url;
        },
        
        /**
         * Handle update pending transactions
         */
        handleUpdatePendingTransactions: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            
            if (!confirm('This will check all pending transactions with their payment gateways. Continue?')) {
                return;
            }
            
            TransactionAdmin.setButtonLoading($button, 'Updating...');
            
            $.ajax({
                url: smsTransactionAdmin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'sms_update_pending_transactions',
                    nonce: TransactionAdmin.createNonce('sms_bulk_actions')
                },
                success: function(response) {
                    if (response.success) {
                        TransactionAdmin.showSuccess('Pending transactions updated successfully');
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        TransactionAdmin.showError(response.data || 'Failed to update transactions');
                        TransactionAdmin.resetButton($button, 'Update Pending Transactions');
                    }
                },
                error: function() {
                    TransactionAdmin.showError('Network error occurred');
                    TransactionAdmin.resetButton($button, 'Update Pending Transactions');
                }
            });
        },
        
        /**
         * Handle send pending receipts
         */
        handleSendPendingReceipts: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            
            if (!confirm('This will send receipts for all completed transactions that haven\'t been sent yet. Continue?')) {
                return;
            }
            
            TransactionAdmin.setButtonLoading($button, 'Sending...');
            
            $.ajax({
                url: smsTransactionAdmin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'sms_send_pending_receipts',
                    nonce: TransactionAdmin.createNonce('sms_bulk_actions')
                },
                success: function(response) {
                    if (response.success) {
                        TransactionAdmin.showSuccess('Pending receipts sent successfully');
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        TransactionAdmin.showError(response.data || 'Failed to send receipts');
                        TransactionAdmin.resetButton($button, 'Send Pending Receipts');
                    }
                },
                error: function() {
                    TransactionAdmin.showError('Network error occurred');
                    TransactionAdmin.resetButton($button, 'Send Pending Receipts');
                }
            });
        },
        
        /**
         * Handle bulk actions
         */
        handleBulkActions: function(e) {
            var action = $(this).siblings('select[name="action"], select[name="action2"]').val();
            var selectedItems = $('input[name="post[]"]:checked').length;
            
            if (!action || action === '-1') {
                return;
            }
            
            if (selectedItems === 0) {
                e.preventDefault();
                alert('Please select at least one transaction');
                return;
            }
            
            // Confirm bulk actions
            var confirmMessage = '';
            switch (action) {
                case 'sms_verify_transactions':
                    confirmMessage = 'Verify ' + selectedItems + ' transaction(s) with their payment gateways?';
                    break;
                case 'sms_send_receipts':
                    confirmMessage = 'Send receipts for ' + selectedItems + ' transaction(s)?';
                    break;
                case 'sms_mark_completed':
                    confirmMessage = 'Mark ' + selectedItems + ' transaction(s) as completed?';
                    break;
            }
            
            if (confirmMessage && !confirm(confirmMessage)) {
                e.preventDefault();
            }
        },
        
        /**
         * Setup auto-refresh for pending transactions
         */
        setupAutoRefresh: function() {
            // Only auto-refresh on transaction list pages with pending transactions
            if ($('.status-pending, .status-processing').length > 0 && 
                window.location.href.indexOf('post_type=sms_transactions') !== -1) {
                
                // Refresh every 2 minutes
                setTimeout(function() {
                    location.reload();
                }, 120000);
            }
        },
        
        /**
         * Show success message
         */
        showSuccess: function(message) {
            this.showMessage(message, 'success');
        },
        
        /**
         * Show error message
         */
        showError: function(message) {
            this.showMessage(message, 'error');
        },
        
        /**
         * Show message
         */
        showMessage: function(message, type) {
            var className = type === 'success' ? 'sms-success-message' : 'sms-error-message';
            var $message = $('<div class="' + className + '">' + message + '</div>');
            
            // Remove existing messages
            $('.sms-success-message, .sms-error-message').remove();
            
            // Add new message
            if ($('.wrap h1').length) {
                $message.insertAfter('.wrap h1');
            } else {
                $message.prependTo('.wrap');
            }
            
            // Auto-hide after 5 seconds
            setTimeout(function() {
                $message.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        },
        
        /**
         * Set button loading state
         */
        setButtonLoading: function($button, text) {
            $button.prop('disabled', true)
                   .addClass('loading')
                   .data('original-text', $button.text())
                   .text(text);
        },
        
        /**
         * Reset button state
         */
        resetButton: function($button, text) {
            var originalText = $button.data('original-text') || text;
            $button.prop('disabled', false)
                   .removeClass('loading')
                   .text(originalText);
        },
        
        /**
         * Get current transaction ID
         */
        getTransactionId: function() {
            // Try to get from URL parameter
            var urlParams = new URLSearchParams(window.location.search);
            var postId = urlParams.get('post');
            
            if (postId) {
                return postId;
            }
            
            // Try to get from hidden input or data attribute
            var $transactionId = $('[data-transaction-id]');
            if ($transactionId.length) {
                return $transactionId.data('transaction-id');
            }
            
            // Try to get from form input
            var $hiddenInput = $('input[name="transaction_id"]');
            if ($hiddenInput.length) {
                return $hiddenInput.val();
            }
            
            return null;
        },
        
        /**
         * Create nonce for AJAX requests
         */
        createNonce: function(action) {
            // This is a simplified nonce creation
            // In a real implementation, you'd want to use wp_create_nonce() from PHP
            return smsTransactionAdmin.nonce || '';
        },
        
        /**
         * Show modal
         */
        showModal: function(content, title) {
            var modalHtml = '<div class="sms-transaction-modal">' +
                           '<div class="sms-transaction-modal-content">' +
                           '<span class="sms-transaction-modal-close">&times;</span>' +
                           (title ? '<h2>' + title + '</h2>' : '') +
                           content +
                           '</div></div>';
            
            $('body').append(modalHtml);
            $('.sms-transaction-modal').fadeIn();
        },
        
        /**
         * Close modal
         */
        closeModal: function() {
            $('.sms-transaction-modal').fadeOut(function() {
                $(this).remove();
            });
        },
        
        /**
         * Format currency
         */
        formatCurrency: function(amount, currency) {
            currency = currency || 'KES';
            return currency + ' ' + parseFloat(amount).toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        },
        
        /**
         * Format date
         */
        formatDate: function(dateString) {
            var date = new Date(dateString);
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        TransactionAdmin.init();
    });
    
    // Export to global scope for external access
    window.TransactionAdmin = TransactionAdmin;
    
})(jQuery);