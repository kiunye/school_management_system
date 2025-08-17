/**
 * Responsive Data Tables JavaScript
 * Handles interactive functionality for responsive data tables
 */

(function($) {
    'use strict';

    // Responsive Table Manager
    var SMSDataTable = {
        
        // Configuration
        config: {
            debounceDelay: 300,
            animationDuration: 200
        },
        
        // Active tables
        tables: {},

        // Initialize all data tables
        init: function() {
            this.bindGlobalEvents();
            this.initializeTables();
            this.setupResponsiveHandlers();
        },

        // Bind global event handlers
        bindGlobalEvents: function() {
            // Search functionality
            $(document).on('input', '.sms-table-search', this.debounce(this.handleSearch, this.config.debounceDelay));
            
            // Filter functionality
            $(document).on('change', '.sms-table-filter', this.handleFilter);
            
            // Sorting functionality
            $(document).on('click', '.sms-responsive-table th.sortable', this.handleSort);
            
            // View toggle
            $(document).on('click', '.view-toggle', this.handleViewToggle);
            
            // Pagination
            $(document).on('click', '.pagination-btn', this.handlePagination);
            $(document).on('change', '.current-page', this.handlePageInput);
            
            // Bulk actions
            $(document).on('change', '.select-all', this.handleSelectAll);
            $(document).on('change', '.row-selector', this.handleRowSelect);
            $(document).on('click', '.sms-bulk-apply', this.handleBulkAction);
            
            // Export functionality
            $(document).on('click', '.sms-export-btn', this.handleExport);
            
            // Row actions
            $(document).on('click', '.row-action', this.handleRowAction);
            
            // Form validation
            $(document).on('submit', '.sms-validated-form', this.handleFormSubmit);
            $(document).on('blur', '.sms-validated-form input, .sms-validated-form select, .sms-validated-form textarea', this.validateField);
        },

        // Initialize individual tables
        initializeTables: function() {
            $('.sms-datatable-wrapper').each(function() {
                var $wrapper = $(this);
                var tableId = $wrapper.attr('id');
                
                if (tableId) {
                    SMSDataTable.tables[tableId] = {
                        wrapper: $wrapper,
                        table: $wrapper.find('.sms-responsive-table'),
                        currentPage: 1,
                        sortColumn: null,
                        sortDirection: 'asc',
                        searchTerm: '',
                        filters: {},
                        selectedRows: []
                    };
                    
                    SMSDataTable.initializeTable(tableId);
                }
            });
        },

        // Initialize single table
        initializeTable: function(tableId) {
            var table = this.tables[tableId];
            if (!table) return;
            
            // Set initial state
            this.updateTableInfo(tableId);
            this.checkResponsiveView(table.wrapper);
            
            // Initialize any additional features
            this.initializeTooltips(table.wrapper);
        },

        // Setup responsive handlers
        setupResponsiveHandlers: function() {
            var self = this;
            
            $(window).on('resize', this.debounce(function() {
                $('.sms-datatable-wrapper').each(function() {
                    self.checkResponsiveView($(this));
                });
            }, 250));
            
            // Initial check
            $('.sms-datatable-wrapper').each(function() {
                self.checkResponsiveView($(this));
            });
        },

        // Check and update responsive view
        checkResponsiveView: function($wrapper) {
            var isMobile = window.innerWidth <= 768;
            var $tableView = $wrapper.find('.table-view');
            var $cardsView = $wrapper.find('.cards-view');
            var $viewToggles = $wrapper.find('.view-toggle');
            
            if (isMobile) {
                // Force cards view on mobile
                $tableView.removeClass('active');
                $cardsView.addClass('active');
                $viewToggles.hide();
            } else {
                // Show view toggles on desktop
                $viewToggles.show();
                
                // Maintain current view if set
                if (!$tableView.hasClass('active') && !$cardsView.hasClass('active')) {
                    $tableView.addClass('active');
                }
            }
        },

        // Handle search input
        handleSearch: function(e) {
            var $input = $(e.target);
            var $wrapper = $input.closest('.sms-datatable-wrapper');
            var tableId = $wrapper.attr('id');
            var searchTerm = $input.val().toLowerCase();
            
            if (SMSDataTable.tables[tableId]) {
                SMSDataTable.tables[tableId].searchTerm = searchTerm;
                SMSDataTable.tables[tableId].currentPage = 1;
                SMSDataTable.filterTable(tableId);
            }
        },

        // Handle filter changes
        handleFilter: function(e) {
            var $select = $(e.target);
            var $wrapper = $select.closest('.sms-datatable-wrapper');
            var tableId = $wrapper.attr('id');
            var filterKey = $select.data('filter');
            var filterValue = $select.val();
            
            if (SMSDataTable.tables[tableId]) {
                SMSDataTable.tables[tableId].filters[filterKey] = filterValue;
                SMSDataTable.tables[tableId].currentPage = 1;
                SMSDataTable.filterTable(tableId);
            }
        },

        // Handle column sorting
        handleSort: function(e) {
            var $th = $(e.currentTarget);
            var $wrapper = $th.closest('.sms-datatable-wrapper');
            var tableId = $wrapper.attr('id');
            var column = $th.data('column');
            
            if (!SMSDataTable.tables[tableId]) return;
            
            var table = SMSDataTable.tables[tableId];
            var newDirection = 'asc';
            
            // Toggle direction if same column
            if (table.sortColumn === column && table.sortDirection === 'asc') {
                newDirection = 'desc';
            }
            
            // Update sort state
            table.sortColumn = column;
            table.sortDirection = newDirection;
            
            // Update UI
            $wrapper.find('th.sortable').removeClass('sorted-asc sorted-desc');
            $th.addClass('sorted-' + newDirection);
            
            // Apply sort
            SMSDataTable.sortTable(tableId);
        },

        // Handle view toggle
        handleViewToggle: function(e) {
            var $button = $(e.currentTarget);
            var $wrapper = $button.closest('.sms-datatable-wrapper');
            var view = $button.data('view');
            
            // Update button states
            $wrapper.find('.view-toggle').removeClass('active');
            $button.addClass('active');
            
            // Update views
            $wrapper.find('.table-view, .cards-view').removeClass('active');
            $wrapper.find('.' + view + '-view').addClass('active');
        },

        // Handle pagination
        handlePagination: function(e) {
            e.preventDefault();
            
            var $button = $(e.currentTarget);
            var $wrapper = $button.closest('.sms-datatable-wrapper');
            var tableId = $wrapper.attr('id');
            var page = parseInt($button.data('page'));
            
            if (SMSDataTable.tables[tableId] && page) {
                SMSDataTable.tables[tableId].currentPage = page;
                SMSDataTable.updateTable(tableId);
            }
        },

        // Handle page input
        handlePageInput: function(e) {
            var $input = $(e.target);
            var $wrapper = $input.closest('.sms-datatable-wrapper');
            var tableId = $wrapper.attr('id');
            var page = parseInt($input.val());
            var maxPage = parseInt($wrapper.find('.total-pages').text());
            
            if (page >= 1 && page <= maxPage && SMSDataTable.tables[tableId]) {
                SMSDataTable.tables[tableId].currentPage = page;
                SMSDataTable.updateTable(tableId);
            }
        },

        // Handle select all checkbox
        handleSelectAll: function(e) {
            var $checkbox = $(e.target);
            var $wrapper = $checkbox.closest('.sms-datatable-wrapper');
            var isChecked = $checkbox.prop('checked');
            
            $wrapper.find('.row-selector').prop('checked', isChecked);
            SMSDataTable.updateBulkActionState($wrapper);
        },

        // Handle individual row selection
        handleRowSelect: function(e) {
            var $checkbox = $(e.target);
            var $wrapper = $checkbox.closest('.sms-datatable-wrapper');
            
            // Update select all state
            var totalRows = $wrapper.find('.row-selector').length;
            var selectedRows = $wrapper.find('.row-selector:checked').length;
            
            $wrapper.find('.select-all').prop('checked', selectedRows === totalRows);
            $wrapper.find('.select-all').prop('indeterminate', selectedRows > 0 && selectedRows < totalRows);
            
            SMSDataTable.updateBulkActionState($wrapper);
        },

        // Update bulk action state
        updateBulkActionState: function($wrapper) {
            var selectedCount = $wrapper.find('.row-selector:checked').length;
            var $bulkActions = $wrapper.find('.sms-bulk-actions');
            
            if (selectedCount > 0) {
                $bulkActions.show();
            } else {
                $bulkActions.hide();
            }
        },

        // Handle bulk actions
        handleBulkAction: function(e) {
            var $button = $(e.target);
            var $wrapper = $button.closest('.sms-datatable-wrapper');
            var action = $wrapper.find('#' + $wrapper.attr('id').replace('-wrapper', '') + '-bulk-action').val();
            var selectedIds = [];
            
            $wrapper.find('.row-selector:checked').each(function() {
                selectedIds.push($(this).val());
            });
            
            if (!action || selectedIds.length === 0) {
                alert(sms_admin_ajax.strings.no_items_selected || 'Please select items and choose an action.');
                return;
            }
            
            if (confirm(sms_admin_ajax.strings.confirm_bulk_action || 'Are you sure you want to perform this action on the selected items?')) {
                SMSDataTable.performBulkAction($wrapper, action, selectedIds);
            }
        },

        // Perform bulk action
        performBulkAction: function($wrapper, action, selectedIds) {
            var tableId = $wrapper.attr('id');
            
            SMSDataTable.showLoading($wrapper);
            
            $.post(ajaxurl, {
                action: 'sms_datatable_action',
                action_type: action,
                row_ids: selectedIds,
                table_id: tableId,
                nonce: sms_admin_ajax.nonce
            }, function(response) {
                SMSDataTable.hideLoading($wrapper);
                
                if (response.success) {
                    SMSDataTable.showMessage($wrapper, response.data.message || 'Action completed successfully.', 'success');
                    SMSDataTable.refreshTable(tableId);
                } else {
                    SMSDataTable.showMessage($wrapper, response.data || 'An error occurred.', 'error');
                }
            }).fail(function() {
                SMSDataTable.hideLoading($wrapper);
                SMSDataTable.showMessage($wrapper, 'Network error occurred.', 'error');
            });
        },

        // Handle export
        handleExport: function(e) {
            var $button = $(e.target).closest('.sms-export-btn');
            var $wrapper = $button.closest('.sms-datatable-wrapper');
            var format = $button.data('format');
            var tableId = $wrapper.attr('id');
            
            SMSDataTable.exportTable(tableId, format);
        },

        // Export table data
        exportTable: function(tableId, format) {
            var table = this.tables[tableId];
            if (!table) return;
            
            var $wrapper = table.wrapper;
            var originalText = $wrapper.find('.sms-export-btn[data-format="' + format + '"]').html();
            
            // Show loading state
            $wrapper.find('.sms-export-btn[data-format="' + format + '"]').html('<span class="dashicons dashicons-update spin"></span> Exporting...');
            
            $.post(ajaxurl, {
                action: 'sms_export_table',
                table_id: tableId,
                format: format,
                search: table.searchTerm,
                filters: table.filters,
                sort_column: table.sortColumn,
                sort_direction: table.sortDirection,
                nonce: sms_admin_ajax.nonce
            }, function(response) {
                // Restore button
                $wrapper.find('.sms-export-btn[data-format="' + format + '"]').html(originalText);
                
                if (response.success && response.data.download_url) {
                    // Trigger download
                    var link = document.createElement('a');
                    link.href = response.data.download_url;
                    link.download = response.data.filename || 'export.' + format;
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                } else {
                    SMSDataTable.showMessage($wrapper, 'Export failed. Please try again.', 'error');
                }
            }).fail(function() {
                $wrapper.find('.sms-export-btn[data-format="' + format + '"]').html(originalText);
                SMSDataTable.showMessage($wrapper, 'Export failed. Please try again.', 'error');
            });
        },

        // Handle row actions
        handleRowAction: function(e) {
            var $link = $(e.currentTarget);
            var action = $link.data('action');
            var rowId = $link.data('row-id');
            
            // Handle specific actions that need confirmation
            if (action === 'delete') {
                if (!confirm(sms_admin_ajax.strings.confirm_delete || 'Are you sure you want to delete this item?')) {
                    e.preventDefault();
                    return false;
                }
            }
            
            // If it's an AJAX action (href="#")
            if ($link.attr('href') === '#') {
                e.preventDefault();
                SMSDataTable.performRowAction($link, action, rowId);
            }
        },

        // Perform row action via AJAX
        performRowAction: function($link, action, rowId) {
            var $wrapper = $link.closest('.sms-datatable-wrapper');
            var tableId = $wrapper.attr('id');
            
            // Show loading state
            var originalHtml = $link.html();
            $link.html('<span class="dashicons dashicons-update spin"></span>');
            
            $.post(ajaxurl, {
                action: 'sms_datatable_action',
                action_type: action,
                row_ids: [rowId],
                table_id: tableId,
                nonce: sms_admin_ajax.nonce
            }, function(response) {
                $link.html(originalHtml);
                
                if (response.success) {
                    if (action === 'delete') {
                        // Remove row with animation
                        var $row = $link.closest('tr, .sms-card-item');
                        $row.fadeOut(SMSDataTable.config.animationDuration, function() {
                            $row.remove();
                            SMSDataTable.updateTableInfo(tableId);
                        });
                    } else {
                        SMSDataTable.showMessage($wrapper, response.data.message || 'Action completed successfully.', 'success');
                        SMSDataTable.refreshTable(tableId);
                    }
                } else {
                    SMSDataTable.showMessage($wrapper, response.data || 'An error occurred.', 'error');
                }
            }).fail(function() {
                $link.html(originalHtml);
                SMSDataTable.showMessage($wrapper, 'Network error occurred.', 'error');
            });
        },

        // Filter table data
        filterTable: function(tableId) {
            var table = this.tables[tableId];
            if (!table) return;
            
            var $wrapper = table.wrapper;
            var $rows = $wrapper.find('tbody tr:not(.no-items)');
            var $cards = $wrapper.find('.sms-card-item');
            var visibleCount = 0;
            
            // Filter rows
            $rows.each(function() {
                var $row = $(this);
                var visible = SMSDataTable.isRowVisible($row, table);
                
                $row.toggle(visible);
                if (visible) visibleCount++;
            });
            
            // Filter cards
            $cards.each(function() {
                var $card = $(this);
                var visible = SMSDataTable.isCardVisible($card, table);
                
                $card.toggle(visible);
            });
            
            // Update no items message
            SMSDataTable.updateNoItemsMessage($wrapper, visibleCount);
            SMSDataTable.updateTableInfo(tableId);
        },

        // Check if row is visible based on filters
        isRowVisible: function($row, table) {
            var searchTerm = table.searchTerm.toLowerCase();
            var filters = table.filters;
            
            // Search filter
            if (searchTerm) {
                var rowText = $row.text().toLowerCase();
                if (rowText.indexOf(searchTerm) === -1) {
                    return false;
                }
            }
            
            // Column filters
            for (var filterKey in filters) {
                if (filters[filterKey]) {
                    var $cell = $row.find('.column-' + filterKey);
                    var cellText = $cell.text().toLowerCase();
                    var filterValue = filters[filterKey].toLowerCase();
                    
                    if (cellText.indexOf(filterValue) === -1) {
                        return false;
                    }
                }
            }
            
            return true;
        },

        // Check if card is visible based on filters
        isCardVisible: function($card, table) {
            var searchTerm = table.searchTerm.toLowerCase();
            
            if (searchTerm) {
                var cardText = $card.text().toLowerCase();
                return cardText.indexOf(searchTerm) !== -1;
            }
            
            return true;
        },

        // Sort table data
        sortTable: function(tableId) {
            var table = this.tables[tableId];
            if (!table || !table.sortColumn) return;
            
            var $wrapper = table.wrapper;
            var $tbody = $wrapper.find('tbody');
            var $rows = $tbody.find('tr:not(.no-items)').get();
            
            $rows.sort(function(a, b) {
                var aVal = $(a).find('.column-' + table.sortColumn).text().trim();
                var bVal = $(b).find('.column-' + table.sortColumn).text().trim();
                
                // Try to parse as numbers
                var aNum = parseFloat(aVal.replace(/[^0-9.-]/g, ''));
                var bNum = parseFloat(bVal.replace(/[^0-9.-]/g, ''));
                
                var result;
                if (!isNaN(aNum) && !isNaN(bNum)) {
                    result = aNum - bNum;
                } else {
                    result = aVal.localeCompare(bVal);
                }
                
                return table.sortDirection === 'desc' ? -result : result;
            });
            
            // Reorder rows
            $.each($rows, function(index, row) {
                $tbody.append(row);
            });
        },

        // Update table
        updateTable: function(tableId) {
            // This would typically reload data via AJAX
            // For now, just update the display
            this.updateTableInfo(tableId);
        },

        // Refresh table data
        refreshTable: function(tableId) {
            var table = this.tables[tableId];
            if (!table) return;
            
            var $wrapper = table.wrapper;
            
            SMSDataTable.showLoading($wrapper);
            
            // Reload page for now - in a real implementation, this would be AJAX
            setTimeout(function() {
                location.reload();
            }, 1000);
        },

        // Update table info
        updateTableInfo: function(tableId) {
            var table = this.tables[tableId];
            if (!table) return;
            
            var $wrapper = table.wrapper;
            var visibleRows = $wrapper.find('tbody tr:visible:not(.no-items)').length;
            var totalRows = $wrapper.find('tbody tr:not(.no-items)').length;
            
            $wrapper.find('.total-entries').text(totalRows);
            $wrapper.find('.start-entry').text(visibleRows > 0 ? 1 : 0);
            $wrapper.find('.end-entry').text(visibleRows);
        },

        // Update no items message
        updateNoItemsMessage: function($wrapper, visibleCount) {
            var $noItems = $wrapper.find('.no-items');
            
            if (visibleCount === 0 && $wrapper.find('tbody tr:not(.no-items)').length > 0) {
                if ($noItems.length === 0) {
                    var colspan = $wrapper.find('thead th').length;
                    $wrapper.find('tbody').append(
                        '<tr class="no-items"><td colspan="' + colspan + '" class="no-items-message">' +
                        'No items match your search criteria.' +
                        '</td></tr>'
                    );
                }
                $noItems.show();
            } else {
                $noItems.hide();
            }
        },

        // Show loading indicator
        showLoading: function($wrapper) {
            $wrapper.find('.sms-table-loading').show();
        },

        // Hide loading indicator
        hideLoading: function($wrapper) {
            $wrapper.find('.sms-table-loading').hide();
        },

        // Show message
        showMessage: function($wrapper, message, type) {
            var $message = $('<div class="sms-table-message ' + type + '">' + message + '</div>');
            
            $wrapper.prepend($message);
            
            setTimeout(function() {
                $message.fadeOut(function() {
                    $message.remove();
                });
            }, 5000);
        },

        // Initialize tooltips
        initializeTooltips: function($wrapper) {
            $wrapper.find('[title]').each(function() {
                var $element = $(this);
                var title = $element.attr('title');
                
                $element.removeAttr('title').on('mouseenter', function(e) {
                    var tooltip = $('<div class="sms-tooltip">' + title + '</div>');
                    tooltip.css({
                        position: 'absolute',
                        background: '#333',
                        color: 'white',
                        padding: '5px 10px',
                        borderRadius: '4px',
                        fontSize: '12px',
                        zIndex: 10000,
                        whiteSpace: 'nowrap'
                    });
                    
                    $('body').append(tooltip);
                    
                    var offset = $element.offset();
                    tooltip.css({
                        top: offset.top - tooltip.outerHeight() - 5,
                        left: offset.left + ($element.outerWidth() / 2) - (tooltip.outerWidth() / 2)
                    });
                    
                }).on('mouseleave', function() {
                    $('.sms-tooltip').remove();
                });
            });
        },

        // Form validation
        handleFormSubmit: function(e) {
            var $form = $(e.target);
            var isValid = true;
            
            // Clear previous errors
            $form.find('.validation-error').remove();
            $form.find('.field-error').removeClass('field-error');
            
            // Validate required fields
            $form.find('[required]').each(function() {
                if (!SMSDataTable.validateField.call(this)) {
                    isValid = false;
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                
                // Show error summary
                var $errorSummary = $('<div class="error-summary"><h4>Please correct the following errors:</h4><ul></ul></div>');
                $form.find('.validation-error').each(function() {
                    $errorSummary.find('ul').append('<li>' + $(this).text() + '</li>');
                });
                
                $form.prepend($errorSummary);
                
                // Scroll to first error
                var $firstError = $form.find('.field-error').first();
                if ($firstError.length) {
                    $('html, body').animate({
                        scrollTop: $firstError.offset().top - 100
                    }, 300);
                }
            }
            
            return isValid;
        },

        // Validate individual field
        validateField: function(e) {
            var $field = $(this);
            var $wrapper = $field.closest('.field-wrapper, .form-field, td, .card-field');
            var value = $field.val().trim();
            var isValid = true;
            var errorMessage = '';
            
            // Clear previous validation
            $wrapper.removeClass('field-error field-success');
            $wrapper.find('.validation-error, .validation-success').remove();
            
            // Required validation
            if ($field.prop('required') && !value) {
                isValid = false;
                errorMessage = 'This field is required.';
            }
            
            // Email validation
            else if ($field.attr('type') === 'email' && value) {
                var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(value)) {
                    isValid = false;
                    errorMessage = 'Please enter a valid email address.';
                }
            }
            
            // Phone validation
            else if ($field.hasClass('phone-field') && value) {
                var phoneRegex = /^(\+254|254|0)[17]\d{8}$/;
                if (!phoneRegex.test(value.replace(/\s/g, ''))) {
                    isValid = false;
                    errorMessage = 'Please enter a valid Kenyan phone number.';
                }
            }
            
            // Number validation
            else if ($field.attr('type') === 'number' && value) {
                var min = parseFloat($field.attr('min'));
                var max = parseFloat($field.attr('max'));
                var numValue = parseFloat(value);
                
                if (isNaN(numValue)) {
                    isValid = false;
                    errorMessage = 'Please enter a valid number.';
                } else if (!isNaN(min) && numValue < min) {
                    isValid = false;
                    errorMessage = 'Value must be at least ' + min + '.';
                } else if (!isNaN(max) && numValue > max) {
                    isValid = false;
                    errorMessage = 'Value must be no more than ' + max + '.';
                }
            }
            
            // Custom validation
            var customValidator = $field.data('validator');
            if (customValidator && window[customValidator]) {
                var customResult = window[customValidator](value, $field);
                if (customResult !== true) {
                    isValid = false;
                    errorMessage = customResult;
                }
            }
            
            // Show validation result
            if (!isValid) {
                $wrapper.addClass('field-error');
                $wrapper.append('<span class="validation-error">' + errorMessage + '</span>');
            } else if (value) {
                $wrapper.addClass('field-success');
                $wrapper.append('<span class="validation-success">✓</span>');
            }
            
            return isValid;
        },

        // Debounce utility
        debounce: function(func, wait) {
            var timeout;
            return function executedFunction() {
                var context = this;
                var args = arguments;
                var later = function() {
                    timeout = null;
                    func.apply(context, args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        SMSDataTable.init();
    });

    // Make SMSDataTable globally available
    window.SMSDataTable = SMSDataTable;

})(jQuery);