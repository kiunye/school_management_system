/**
 * Notices Admin JavaScript
 *
 * @package SchoolManagementSystem
 * @subpackage Admin/JS
 */

(function($) {
    'use strict';

    // Initialize when document is ready
    $(document).ready(function() {
        NoticeManager.init();
    });

    /**
     * Notice Management Object
     */
    var NoticeManager = {
        
        /**
         * Initialize the notice manager
         */
        init: function() {
            this.bindEvents();
            this.initializeComponents();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Bulk actions form submission
            $('#bulk-action-form').on('submit', this.handleBulkAction);
            
            // Individual notice actions
            $(document).on('click', '.send-notice-notification', this.sendNoticeNotification);
            $(document).on('click', '.archive-notice', this.archiveNotice);
            $(document).on('click', '.restore-notice', this.restoreNotice);
            
            // Archive expired notices button
            $('#archive-expired-notices').on('click', this.archiveExpiredNotices);
            
            // Quick notice creation
            $('#create-quick-notice').on('click', this.showQuickNoticeModal);
            $('#quick-notice-form').on('submit', this.createQuickNotice);
            
            // Modal controls
            $(document).on('click', '.sms-modal-close', this.closeModal);
            $(document).on('click', '.sms-modal', function(e) {
                if (e.target === this) {
                    NoticeManager.closeModal();
                }
            });
            
            // Audience type change
            $('#audience_type').on('change', this.updateAudiencePreview);
            
            // Select all checkbox
            $('#cb-select-all-1').on('change', this.toggleSelectAll);
            
            // Individual checkboxes
            $('input[name="notice_ids[]"]').on('change', this.updateSelectAllState);
            
            // Escape key to close modal
            $(document).on('keydown', function(e) {
                if (e.keyCode === 27) { // Escape key
                    NoticeManager.closeModal();
                }
            });
        },

        /**
         * Initialize components
         */
        initializeComponents: function() {
            // Initialize tooltips if available
            if (typeof $.fn.tooltip === 'function') {
                $('[data-tooltip]').tooltip();
            }
            
            // Auto-refresh delivery status every 30 seconds
            setInterval(this.refreshDeliveryStatus, 30000);
            
            // Initialize audience preview
            this.updateAudiencePreview();
        },

        /**
         * Handle bulk action form submission
         */
        handleBulkAction: function(e) {
            var action = $('#bulk-action-selector-top').val();
            var checkedBoxes = $('input[name="notice_ids[]"]:checked');
            
            if (action === '-1') {
                e.preventDefault();
                NoticeManager.showAlert(smsNoticesAdmin.strings.error, 'Please select an action.', 'error');
                return false;
            }
            
            if (checkedBoxes.length === 0) {
                e.preventDefault();
                NoticeManager.showAlert(smsNoticesAdmin.strings.error, 'Please select at least one notice.', 'error');
                return false;
            }
            
            // Confirm destructive actions
            if (action === 'delete') {
                if (!confirm(smsNoticesAdmin.strings.confirmBulkDelete)) {
                    e.preventDefault();
                    return false;
                }
            }
            
            // Show loading state
            NoticeManager.showLoading();
        },

        /**
         * Send notice notification
         */
        sendNoticeNotification: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var noticeId = $button.data('notice-id');
            var originalText = $button.text();
            
            if (!confirm('Send notifications for this notice now?')) {
                return;
            }
            
            // Update button state
            $button.text(smsNoticesAdmin.strings.sending).addClass('loading');
            
            $.ajax({
                url: smsNoticesAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sms_send_notice',
                    notice_id: noticeId,
                    nonce: smsNoticesAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        NoticeManager.showAlert('Success', 'Notifications sent successfully!', 'success');
                        // Update delivery status in the table
                        NoticeManager.updateDeliveryStatus(noticeId, response.data);
                    } else {
                        NoticeManager.showAlert('Error', 'Error sending notifications: ' + response.data.message, 'error');
                    }
                },
                error: function() {
                    NoticeManager.showAlert('Error', 'Error sending notifications. Please try again.', 'error');
                },
                complete: function() {
                    $button.text(originalText).removeClass('loading');
                }
            });
        },

        /**
         * Archive expired notices
         */
        archiveExpiredNotices: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var originalText = $button.text();
            
            if (!confirm('Archive all expired notices?')) {
                return;
            }
            
            $button.text(smsNoticesAdmin.strings.processing).addClass('loading');
            
            $.ajax({
                url: smsNoticesAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sms_archive_expired_notices',
                    nonce: smsNoticesAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        NoticeManager.showAlert('Success', response.data.message, 'success');
                        // Reload page to show updated counts
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        NoticeManager.showAlert('Error', 'Error archiving notices.', 'error');
                    }
                },
                error: function() {
                    NoticeManager.showAlert('Error', 'Error archiving notices. Please try again.', 'error');
                },
                complete: function() {
                    $button.text(originalText).removeClass('loading');
                }
            });
        },

        /**
         * Show quick notice creation modal
         */
        showQuickNoticeModal: function(e) {
            e.preventDefault();
            $('#notice-creation-modal').addClass('show');
            $('#notice_title').focus();
        },

        /**
         * Create quick notice
         */
        createQuickNotice: function(e) {
            e.preventDefault();
            
            var $form = $(this);
            var formData = $form.serialize();
            
            // Add nonce
            formData += '&nonce=' + smsNoticesAdmin.nonce;
            
            // Show loading state
            $form.addClass('loading');
            
            $.ajax({
                url: smsNoticesAdmin.ajaxUrl,
                type: 'POST',
                data: formData + '&action=sms_create_quick_notice',
                success: function(response) {
                    if (response.success) {
                        NoticeManager.showAlert('Success', response.data.message, 'success');
                        NoticeManager.closeModal();
                        
                        // Redirect to notices page or reload
                        if (response.data.redirect_url) {
                            setTimeout(function() {
                                window.location.href = response.data.redirect_url;
                            }, 1000);
                        } else {
                            setTimeout(function() {
                                location.reload();
                            }, 1000);
                        }
                    } else {
                        NoticeManager.showAlert('Error', response.data.message || 'Failed to create notice.', 'error');
                    }
                },
                error: function() {
                    NoticeManager.showAlert('Error', 'Error creating notice. Please try again.', 'error');
                },
                complete: function() {
                    $form.removeClass('loading');
                }
            });
        },

        /**
         * Close modal
         */
        closeModal: function() {
            $('.sms-modal').removeClass('show');
            // Reset form
            $('#quick-notice-form')[0].reset();
        },

        /**
         * Update audience preview
         */
        updateAudiencePreview: function() {
            var audienceType = $('#audience_type').val();
            var audienceData = {};
            
            // Collect audience-specific data
            switch (audienceType) {
                case 'roles':
                    audienceData.roles = $('#target_roles').val() || [];
                    break;
                case 'classes':
                    audienceData.classes = $('#target_classes').val() || [];
                    break;
                case 'individuals':
                    audienceData.individuals = $('#target_individuals').val() || [];
                    break;
            }
            
            $.ajax({
                url: smsNoticesAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sms_get_audience_preview',
                    audience_type: audienceType,
                    audience_data: JSON.stringify(audienceData),
                    nonce: smsNoticesAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('#audience-preview').html(
                            '<div class="audience-preview">' +
                            '<span class="count">' + response.data.count + '</span> estimated recipients' +
                            '</div>'
                        );
                    }
                }
            });
        },

        /**
         * Toggle select all checkboxes
         */
        toggleSelectAll: function() {
            var isChecked = $(this).prop('checked');
            $('input[name="notice_ids[]"]').prop('checked', isChecked);
        },

        /**
         * Update select all state based on individual checkboxes
         */
        updateSelectAllState: function() {
            var totalCheckboxes = $('input[name="notice_ids[]"]').length;
            var checkedCheckboxes = $('input[name="notice_ids[]"]:checked').length;
            
            $('#cb-select-all-1').prop('checked', totalCheckboxes === checkedCheckboxes);
        },

        /**
         * Refresh delivery status
         */
        refreshDeliveryStatus: function() {
            // Only refresh if we're on the notices page
            if (!$('.notices-management-page').length) {
                return;
            }
            
            var noticeIds = [];
            $('.delivery-sending').each(function() {
                var $row = $(this).closest('tr');
                var noticeId = $row.attr('id').replace('notice-', '');
                noticeIds.push(noticeId);
            });
            
            if (noticeIds.length === 0) {
                return;
            }
            
            $.ajax({
                url: smsNoticesAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sms_get_delivery_status',
                    notice_ids: noticeIds,
                    nonce: smsNoticesAdmin.nonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        $.each(response.data, function(noticeId, status) {
                            NoticeManager.updateDeliveryStatus(noticeId, status);
                        });
                    }
                }
            });
        },

        /**
         * Update delivery status in the table
         */
        updateDeliveryStatus: function(noticeId, statusData) {
            var $row = $('#notice-' + noticeId);
            var $deliveryCell = $row.find('.column-delivery');
            
            var statusClass = 'delivery-' + statusData.status;
            var statusText = statusData.status.charAt(0).toUpperCase() + statusData.status.slice(1);
            
            var html = '<span class="delivery-status ' + statusClass + '">' + statusText + '</span>';
            
            if (statusData.sent_count || statusData.failed_count) {
                html += '<br><small>Sent: ' + (statusData.sent_count || 0) + ' | Failed: ' + (statusData.failed_count || 0) + '</small>';
            }
            
            $deliveryCell.html(html);
        },

        /**
         * Show loading state
         */
        showLoading: function() {
            $('body').addClass('loading');
        },

        /**
         * Hide loading state
         */
        hideLoading: function() {
            $('body').removeClass('loading');
        },

        /**
         * Show alert message
         */
        showAlert: function(title, message, type) {
            type = type || 'info';
            
            var alertClass = 'notice-' + type;
            var $alert = $('<div class="notice ' + alertClass + ' is-dismissible">' +
                '<p><strong>' + title + ':</strong> ' + message + '</p>' +
                '<button type="button" class="notice-dismiss">' +
                '<span class="screen-reader-text">Dismiss this notice.</span>' +
                '</button>' +
                '</div>');
            
            // Insert after the page title
            $('.wp-header-end').after($alert);
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $alert.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
            
            // Handle manual dismiss
            $alert.find('.notice-dismiss').on('click', function() {
                $alert.fadeOut(function() {
                    $(this).remove();
                });
            });
        },

        /**
         * Format date for display
         */
        formatDate: function(dateString) {
            var date = new Date(dateString);
            return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
        },

        /**
         * Validate form data
         */
        validateForm: function($form) {
            var isValid = true;
            var errors = [];
            
            // Check required fields
            $form.find('[required]').each(function() {
                var $field = $(this);
                var value = $field.val().trim();
                
                if (!value) {
                    isValid = false;
                    errors.push($field.prev('label').text() + ' is required.');
                    $field.addClass('error');
                } else {
                    $field.removeClass('error');
                }
            });
            
            // Show errors if any
            if (!isValid) {
                NoticeManager.showAlert('Validation Error', errors.join('<br>'), 'error');
            }
            
            return isValid;
        },

        /**
         * Debounce function for performance
         */
        debounce: function(func, wait, immediate) {
            var timeout;
            return function() {
                var context = this, args = arguments;
                var later = function() {
                    timeout = null;
                    if (!immediate) func.apply(context, args);
                };
                var callNow = immediate && !timeout;
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
                if (callNow) func.apply(context, args);
            };
        }
    };

    // Make NoticeManager globally available
    window.NoticeManager = NoticeManager;

})(jQuery);