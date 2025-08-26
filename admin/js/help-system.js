/**
 * Help System JavaScript
 *
 * Handles tooltips, help modals, and guided tours.
 */

(function($) {
    'use strict';

    // Help System Object
    const SMSHelp = {
        
        // Initialize the help system
        init: function() {
            this.bindEvents();
            this.initTooltips();
        },

        // Bind event handlers
        bindEvents: function() {
            // Help trigger buttons
            $(document).on('click', '.sms-help-trigger', this.showHelp);
            
            // Tour trigger buttons
            $(document).on('click', '.sms-tour-trigger', this.startTour);
            
            // Modal close buttons
            $(document).on('click', '.sms-modal-close', this.closeModal);
            
            // Tour navigation
            $(document).on('click', '#sms-tour-next', this.nextTourStep);
            $(document).on('click', '#sms-tour-prev', this.prevTourStep);
            $(document).on('click', '#sms-tour-finish', this.finishTour);
            
            // Close modal on overlay click
            $(document).on('click', '.sms-modal', function(e) {
                if (e.target === this) {
                    SMSHelp.closeModal();
                }
            });
            
            // Keyboard navigation
            $(document).on('keydown', this.handleKeyboard);
        },

        // Initialize tooltips
        initTooltips: function() {
            // Enhanced tooltip functionality is handled by CSS
            // This function can be extended for dynamic tooltip content
        },

        // Show help modal
        showHelp: function(e) {
            e.preventDefault();
            
            const $trigger = $(this);
            const topic = $trigger.data('topic');
            const section = $trigger.data('section');
            
            if (!topic) {
                console.error('Help topic not specified');
                return;
            }
            
            SMSHelp.loadHelpContent(topic, section);
        },

        // Load help content via AJAX
        loadHelpContent: function(topic, section) {
            const $modal = $('#sms-help-modal');
            const $content = $('#sms-help-content');
            const $title = $('#sms-help-title');
            const $steps = $('#sms-help-steps');
            const $tips = $('#sms-help-tips');
            
            // Show loading state
            $title.text(smsHelp.strings.loading);
            $content.html('<div class="sms-help-loading">' + smsHelp.strings.loading + '</div>');
            $steps.hide();
            $tips.hide();
            $modal.show();
            
            // Make AJAX request
            $.post(smsHelp.ajaxUrl, {
                action: 'sms_get_help_content',
                topic: topic,
                section: section,
                nonce: smsHelp.nonce
            })
            .done(function(response) {
                if (response.success) {
                    SMSHelp.displayHelpContent(response.data);
                } else {
                    SMSHelp.showError(response.data || smsHelp.strings.error);
                }
            })
            .fail(function() {
                SMSHelp.showError(smsHelp.strings.error);
            });
        },

        // Display help content in modal
        displayHelpContent: function(data) {
            const $title = $('#sms-help-title');
            const $content = $('#sms-help-content');
            const $steps = $('#sms-help-steps');
            const $stepsList = $('#sms-help-steps-list');
            const $tips = $('#sms-help-tips');
            const $tipsList = $('#sms-help-tips-list');
            
            // Set title and content
            $title.text(data.title);
            $content.html('<div class="sms-help-content">' + data.content + '</div>');
            
            // Show steps if available
            if (data.steps && data.steps.length > 0) {
                $stepsList.empty();
                data.steps.forEach(function(step) {
                    $stepsList.append('<li>' + step + '</li>');
                });
                $steps.show();
            } else {
                $steps.hide();
            }
            
            // Show tips if available
            if (data.tips && data.tips.length > 0) {
                $tipsList.empty();
                data.tips.forEach(function(tip) {
                    $tipsList.append('<li>' + tip + '</li>');
                });
                $tips.show();
            } else {
                $tips.hide();
            }
        },

        // Show error message
        showError: function(message) {
            const $content = $('#sms-help-content');
            $content.html(
                '<div class="sms-help-error">' +
                '<span class="dashicons dashicons-warning"></span><br>' +
                message +
                '</div>'
            );
        },

        // Start guided tour
        startTour: function(e) {
            e.preventDefault();
            
            const $trigger = $(this);
            const tourId = $trigger.data('tour');
            
            if (!tourId) {
                console.error('Tour ID not specified');
                return;
            }
            
            SMSHelp.initTour(tourId);
        },

        // Initialize tour
        initTour: function(tourId) {
            // This would load tour data and start the tour
            // For now, we'll show a placeholder
            const $modal = $('#sms-tour-modal');
            const $title = $('#sms-tour-title');
            const $content = $('#sms-tour-content');
            
            $title.text('Guided Tour');
            $content.html('<p>Tour functionality will be implemented here.</p>');
            $modal.show();
            
            // Initialize tour state
            this.currentTour = {
                id: tourId,
                currentStep: 0,
                steps: []
            };
        },

        // Next tour step
        nextTourStep: function(e) {
            e.preventDefault();
            
            if (!SMSHelp.currentTour) return;
            
            SMSHelp.currentTour.currentStep++;
            SMSHelp.updateTourStep();
        },

        // Previous tour step
        prevTourStep: function(e) {
            e.preventDefault();
            
            if (!SMSHelp.currentTour) return;
            
            SMSHelp.currentTour.currentStep--;
            SMSHelp.updateTourStep();
        },

        // Update tour step
        updateTourStep: function() {
            const tour = this.currentTour;
            const $prevBtn = $('#sms-tour-prev');
            const $nextBtn = $('#sms-tour-next');
            const $finishBtn = $('#sms-tour-finish');
            const $counter = $('#sms-tour-step-counter');
            const $progress = $('#sms-tour-progress-fill');
            
            // Update step counter
            $counter.text(`Step ${tour.currentStep + 1} of ${tour.steps.length}`);
            
            // Update progress bar
            const progress = ((tour.currentStep + 1) / tour.steps.length) * 100;
            $progress.css('width', progress + '%');
            
            // Update button states
            $prevBtn.toggle(tour.currentStep > 0);
            
            if (tour.currentStep === tour.steps.length - 1) {
                $nextBtn.hide();
                $finishBtn.show();
            } else {
                $nextBtn.show();
                $finishBtn.hide();
            }
        },

        // Finish tour
        finishTour: function(e) {
            e.preventDefault();
            SMSHelp.closeModal();
            SMSHelp.currentTour = null;
        },

        // Close modal
        closeModal: function() {
            $('.sms-modal').hide();
            
            // Clean up tour highlights
            $('.sms-tour-highlight').removeClass('sms-tour-highlight');
            $('.sms-tour-overlay').remove();
        },

        // Handle keyboard navigation
        handleKeyboard: function(e) {
            if (!$('.sms-modal:visible').length) return;
            
            switch(e.keyCode) {
                case 27: // Escape
                    SMSHelp.closeModal();
                    break;
                case 37: // Left arrow
                    if ($('#sms-tour-prev:visible').length) {
                        SMSHelp.prevTourStep();
                    }
                    break;
                case 39: // Right arrow
                    if ($('#sms-tour-next:visible').length) {
                        SMSHelp.nextTourStep();
                    }
                    break;
            }
        },

        // Highlight element for tour
        highlightElement: function(selector) {
            // Remove existing highlights
            $('.sms-tour-highlight').removeClass('sms-tour-highlight');
            $('.sms-tour-overlay').remove();
            
            const $element = $(selector);
            if ($element.length) {
                // Add overlay
                $('body').append('<div class="sms-tour-overlay"></div>');
                
                // Highlight element
                $element.addClass('sms-tour-highlight');
                
                // Scroll to element
                $('html, body').animate({
                    scrollTop: $element.offset().top - 100
                }, 500);
            }
        },

        // Show contextual help for current page
        showContextualHelp: function() {
            const currentPage = this.getCurrentPage();
            if (currentPage) {
                this.loadHelpContent(currentPage);
            }
        },

        // Get current page identifier
        getCurrentPage: function() {
            const body = $('body');
            
            if (body.hasClass('sms_page_students')) return 'student_management';
            if (body.hasClass('sms_page_fees')) return 'fee_management';
            if (body.hasClass('sms_page_attendance')) return 'attendance_management';
            if (body.hasClass('sms_page_communication')) return 'communication_system';
            if (body.hasClass('sms_page_transport')) return 'transport_management';
            if (body.hasClass('sms_page_reports')) return 'reporting_system';
            if (body.hasClass('sms_page_settings')) return 'payment_gateways';
            
            return null;
        },

        // Add help button to page
        addHelpButton: function(selector, topic, section) {
            const $target = $(selector);
            if ($target.length) {
                const helpButton = $('<button type="button" class="button button-secondary sms-help-trigger" data-topic="' + topic + '" data-section="' + (section || '') + '">' +
                    '<span class="dashicons dashicons-editor-help"></span> Help' +
                    '</button>');
                $target.append(helpButton);
            }
        },

        // Add tooltip to element
        addTooltip: function(selector, content, position) {
            const $target = $(selector);
            if ($target.length) {
                const tooltip = $('<span class="sms-tooltip" data-tooltip="' + content + '" data-position="' + (position || 'top') + '">' +
                    '<span class="dashicons dashicons-editor-help"></span>' +
                    '</span>');
                $target.after(tooltip);
            }
        },

        // Show quick tip
        showQuickTip: function(message, type, duration) {
            type = type || 'info';
            duration = duration || 3000;
            
            const $tip = $('<div class="sms-quick-tip sms-quick-tip-' + type + '">' + message + '</div>');
            $('body').append($tip);
            
            // Position the tip
            $tip.css({
                position: 'fixed',
                top: '20px',
                right: '20px',
                background: type === 'error' ? '#d63638' : type === 'success' ? '#00a32a' : '#0073aa',
                color: 'white',
                padding: '10px 15px',
                borderRadius: '4px',
                zIndex: 100001,
                boxShadow: '0 2px 10px rgba(0,0,0,0.2)'
            });
            
            // Animate in
            $tip.hide().fadeIn(300);
            
            // Auto remove
            setTimeout(function() {
                $tip.fadeOut(300, function() {
                    $tip.remove();
                });
            }, duration);
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        SMSHelp.init();
        
        // Add contextual help buttons based on current page
        const currentPage = SMSHelp.getCurrentPage();
        if (currentPage) {
            // Add help button to page header if it doesn't exist
            if (!$('.sms-help-trigger').length) {
                $('.wrap h1').first().after(
                    '<button type="button" class="button button-secondary sms-help-trigger" data-topic="' + currentPage + '" style="margin-left: 10px;">' +
                    '<span class="dashicons dashicons-editor-help"></span> Help' +
                    '</button>'
                );
            }
        }
    });

    // Expose SMSHelp object globally
    window.SMSHelp = SMSHelp;

})(jQuery);