<?php
/**
 * Fee Categories Tab
 *
 * Interface for managing fee categories and their configurations.
 *
 * @package    School_Management_System
 * @subpackage School_Management_System/admin/partials/fee-management
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}
?>

<div class="categories-tab-content">
    <!-- Category Statistics -->
    <div class="report-summary">
        <div class="summary-card">
            <span class="summary-value"><?php echo count($fee_categories); ?></span>
            <span class="summary-label"><?php _e('Total Categories', 'school-management-system'); ?></span>
        </div>
        <div class="summary-card">
            <span class="summary-value"><?php echo count(array_filter($fee_categories, function($cat) { return $cat['active'] ?? true; })); ?></span>
            <span class="summary-label"><?php _e('Active Categories', 'school-management-system'); ?></span>
        </div>
        <div class="summary-card">
            <span class="summary-value"><?php echo count(array_filter($fee_categories, function($cat) { return $cat['mandatory'] ?? false; })); ?></span>
            <span class="summary-label"><?php _e('Mandatory Categories', 'school-management-system'); ?></span>
        </div>
        <div class="summary-card">
            <span class="summary-value"><?php echo array_sum(array_column($category_statistics, 'total_fees')); ?></span>
            <span class="summary-label"><?php _e('Total Fees', 'school-management-system'); ?></span>
        </div>
    </div>

    <!-- Category Actions -->
    <div class="category-actions">
        <button type="button" class="button button-primary" id="add-new-category">
            <?php _e('Add New Category', 'school-management-system'); ?>
        </button>
        <button type="button" class="button" id="import-categories">
            <?php _e('Import Categories', 'school-management-system'); ?>
        </button>
        <button type="button" class="button" id="export-categories">
            <?php _e('Export Categories', 'school-management-system'); ?>
        </button>
    </div>

    <!-- Add/Edit Category Form -->
    <div id="category-form" style="display: none;">
        <div class="fee-form-section">
            <h3 id="category-form-title"><?php _e('Add New Category', 'school-management-system'); ?></h3>
            <form method="post" action="" id="category-form-element">
                <?php wp_nonce_field('sms_fee_action', 'sms_fee_nonce'); ?>
                <input type="hidden" name="action" value="create_category">
                <input type="hidden" name="category_key" id="category_key">
                
                <div class="fee-form-grid">
                    <div class="fee-form-section">
                        <h4><?php _e('Basic Information', 'school-management-system'); ?></h4>
                        
                        <div class="form-field">
                            <label for="category_name"><?php _e('Category Name', 'school-management-system'); ?> *</label>
                            <input type="text" id="category_name" name="category_data[name]" required>
                            <div class="field-description"><?php _e('Display name for this fee category', 'school-management-system'); ?></div>
                        </div>
                        
                        <div class="form-field">
                            <label for="category_description"><?php _e('Description', 'school-management-system'); ?></label>
                            <textarea id="category_description" name="category_data[description]" rows="3"></textarea>
                            <div class="field-description"><?php _e('Brief description of this category', 'school-management-system'); ?></div>
                        </div>
                        
                        <div class="form-field">
                            <label for="category_color"><?php _e('Category Color', 'school-management-system'); ?></label>
                            <div class="color-picker-container">
                                <input type="color" id="category_color" name="category_data[color]" value="#2196F3" class="category-color-input">
                                <div class="category-color-preview" style="background-color: #2196F3;"></div>
                            </div>
                            <div class="field-description"><?php _e('Color used for visual identification', 'school-management-system'); ?></div>
                        </div>
                    </div>
                    
                    <div class="fee-form-section">
                        <h4><?php _e('Category Settings', 'school-management-system'); ?></h4>
                        
                        <div class="form-field">
                            <label>
                                <input type="checkbox" name="category_data[mandatory]" value="1" id="category_mandatory">
                                <?php _e('Mandatory Category', 'school-management-system'); ?>
                            </label>
                            <div class="field-description"><?php _e('Fees in this category are required for all students', 'school-management-system'); ?></div>
                        </div>
                        
                        <div class="form-field">
                            <label>
                                <input type="checkbox" name="category_data[active]" value="1" id="category_active" checked>
                                <?php _e('Active Category', 'school-management-system'); ?>
                            </label>
                            <div class="field-description"><?php _e('Only active categories can be used for new fees', 'school-management-system'); ?></div>
                        </div>
                        
                        <div class="form-field">
                            <label for="category_sort_order"><?php _e('Sort Order', 'school-management-system'); ?></label>
                            <input type="number" id="category_sort_order" name="category_data[sort_order]" value="999" min="0">
                            <div class="field-description"><?php _e('Lower numbers appear first in lists', 'school-management-system'); ?></div>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="button button-primary" id="save-category">
                        <?php _e('Save Category', 'school-management-system'); ?>
                    </button>
                    <button type="button" class="button" id="cancel-category">
                        <?php _e('Cancel', 'school-management-system'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Categories List -->
    <div class="categories-list">
        <h3><?php _e('Fee Categories', 'school-management-system'); ?></h3>
        
        <div class="categories-grid">
            <?php foreach ($fee_categories as $category_key => $category): 
                $stats = $category_statistics[$category_key] ?? [];
            ?>
                <div class="fee-category-card" data-category="<?php echo esc_attr($category_key); ?>">
                    <div class="category-header">
                        <div class="category-color" style="background-color: <?php echo esc_attr($category['color'] ?? '#2196F3'); ?>"></div>
                        <div class="category-name"><?php echo esc_html($category['name']); ?></div>
                        <div class="category-actions">
                            <button type="button" class="button button-small edit-category" data-category="<?php echo esc_attr($category_key); ?>">
                                <?php _e('Edit', 'school-management-system'); ?>
                            </button>
                            <?php if (!in_array($category_key, ['tuition', 'transport', 'meals', 'books'])): // Prevent deletion of core categories ?>
                                <button type="button" class="button button-small delete-category" data-category="<?php echo esc_attr($category_key); ?>">
                                    <?php _e('Delete', 'school-management-system'); ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="category-description">
                        <?php echo esc_html($category['description'] ?? ''); ?>
                    </div>
                    
                    <div class="category-meta">
                        <?php if ($category['mandatory'] ?? false): ?>
                            <span class="mandatory-badge"><?php _e('Mandatory', 'school-management-system'); ?></span>
                        <?php endif; ?>
                        
                        <?php if (!($category['active'] ?? true)): ?>
                            <span class="inactive-badge"><?php _e('Inactive', 'school-management-system'); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="category-stats">
                        <div class="stat-item">
                            <span class="stat-value"><?php echo number_format($stats['total_fees'] ?? 0); ?></span>
                            <span class="stat-label"><?php _e('Total Fees', 'school-management-system'); ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"><?php echo number_format($stats['active_fees'] ?? 0); ?></span>
                            <span class="stat-label"><?php _e('Active', 'school-management-system'); ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value">KES <?php echo number_format($stats['total_amount'] ?? 0, 0); ?></span>
                            <span class="stat-label"><?php _e('Total Amount', 'school-management-system'); ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value">KES <?php echo number_format($stats['average_amount'] ?? 0, 0); ?></span>
                            <span class="stat-label"><?php _e('Average', 'school-management-system'); ?></span>
                        </div>
                    </div>
                    
                    <div class="category-footer">
                        <a href="<?php echo admin_url('edit.php?post_type=sms_fees&fee_type=' . $category_key); ?>" class="view-fees-link">
                            <?php _e('View Fees in Category', 'school-management-system'); ?> →
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Import/Export Modal -->
    <div id="import-export-modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modal-title"><?php _e('Import Categories', 'school-management-system'); ?></h3>
                <button type="button" class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <div id="import-section">
                    <p><?php _e('Upload a JSON file containing category configuration:', 'school-management-system'); ?></p>
                    <input type="file" id="import-file" accept=".json">
                    <div class="import-preview" id="import-preview" style="display: none;">
                        <h4><?php _e('Preview:', 'school-management-system'); ?></h4>
                        <pre id="import-preview-content"></pre>
                    </div>
                </div>
                <div id="export-section" style="display: none;">
                    <p><?php _e('Download current category configuration:', 'school-management-system'); ?></p>
                    <textarea id="export-content" readonly rows="15"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="button button-primary" id="confirm-import" style="display: none;">
                    <?php _e('Import Categories', 'school-management-system'); ?>
                </button>
                <button type="button" class="button" id="download-export" style="display: none;">
                    <?php _e('Download File', 'school-management-system'); ?>
                </button>
                <button type="button" class="button" id="close-modal">
                    <?php _e('Close', 'school-management-system'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.categories-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.category-actions {
    margin-bottom: 20px;
}

.category-actions .button {
    margin-right: 10px;
}

.category-header .category-actions {
    margin: 0;
}

.category-header .category-actions .button {
    margin-left: 5px;
    margin-right: 0;
}

.category-description {
    color: #666;
    font-size: 14px;
    margin: 10px 0;
    line-height: 1.4;
}

.category-meta {
    margin: 10px 0;
}

.inactive-badge {
    font-size: 11px;
    background: #666;
    color: #fff;
    padding: 2px 6px;
    border-radius: 3px;
    margin-left: 5px;
}

.category-footer {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #eee;
}

.view-fees-link {
    color: #2271b1;
    text-decoration: none;
    font-size: 13px;
}

.view-fees-link:hover {
    text-decoration: underline;
}

.color-picker-container {
    display: flex;
    align-items: center;
    gap: 10px;
}

.category-color-input {
    width: 50px;
    height: 35px;
    border: 1px solid #ddd;
    border-radius: 3px;
    cursor: pointer;
}

.category-color-preview {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    border: 2px solid #fff;
    box-shadow: 0 0 0 1px #ddd;
}

#import-export-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: #fff;
    border-radius: 6px;
    width: 90%;
    max-width: 600px;
    max-height: 80vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.modal-header {
    padding: 20px;
    border-bottom: 1px solid #ddd;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
}

.modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #666;
}

.modal-body {
    padding: 20px;
    flex: 1;
    overflow-y: auto;
}

.modal-footer {
    padding: 20px;
    border-top: 1px solid #ddd;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

.import-preview {
    margin-top: 15px;
    padding: 15px;
    background: #f9f9f9;
    border-radius: 3px;
}

.import-preview pre {
    max-height: 200px;
    overflow-y: auto;
    font-size: 12px;
}

#export-content {
    width: 100%;
    font-family: monospace;
    font-size: 12px;
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 3px;
    padding: 10px;
}

@media (max-width: 768px) {
    .categories-grid {
        grid-template-columns: 1fr;
    }
    
    .modal-content {
        width: 95%;
        margin: 20px;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    var isEditing = false;
    var currentCategory = null;
    
    // Show/hide category form
    $('#add-new-category').on('click', function() {
        isEditing = false;
        currentCategory = null;
        $('#category-form-title').text('<?php _e('Add New Category', 'school-management-system'); ?>');
        $('#category-form-element')[0].reset();
        $('#category_key').val('');
        $('#category-form').slideDown();
    });
    
    $('#cancel-category').on('click', function() {
        $('#category-form').slideUp();
    });
    
    // Edit category
    $('.edit-category').on('click', function() {
        var categoryKey = $(this).data('category');
        var categoryData = <?php echo json_encode($fee_categories); ?>[categoryKey];
        
        if (categoryData) {
            isEditing = true;
            currentCategory = categoryKey;
            
            $('#category-form-title').text('<?php _e('Edit Category', 'school-management-system'); ?>');
            $('#category_key').val(categoryKey);
            $('#category_name').val(categoryData.name);
            $('#category_description').val(categoryData.description || '');
            $('#category_color').val(categoryData.color || '#2196F3');
            $('.category-color-preview').css('background-color', categoryData.color || '#2196F3');
            $('#category_mandatory').prop('checked', categoryData.mandatory || false);
            $('#category_active').prop('checked', categoryData.active !== false);
            $('#category_sort_order').val(categoryData.sort_order || 999);
            
            $('input[name="action"]').val('update_category');
            $('#category-form').slideDown();
        }
    });
    
    // Delete category
    $('.delete-category').on('click', function() {
        var categoryKey = $(this).data('category');
        var categoryName = <?php echo json_encode($fee_categories); ?>[categoryKey].name;
        
        if (confirm('<?php _e('Are you sure you want to delete the category', 'school-management-system'); ?> "' + categoryName + '"?')) {
            // Create form and submit
            var form = $('<form method="post" action="">')
                .append('<?php echo wp_nonce_field('sms_fee_action', 'sms_fee_nonce', true, false); ?>')
                .append('<input type="hidden" name="action" value="delete_category">')
                .append('<input type="hidden" name="category_key" value="' + categoryKey + '">');
            
            $('body').append(form);
            form.submit();
        }
    });
    
    // Generate category key from name
    $('#category_name').on('blur', function() {
        if (!isEditing) {
            var name = $(this).val();
            if (name && !$('#category_key').val()) {
                var key = name.toLowerCase()
                    .replace(/[^a-z0-9\s]/g, '')
                    .replace(/\s+/g, '_')
                    .substring(0, 20);
                $('#category_key').val(key);
            }
        }
    });
    
    // Import categories
    $('#import-categories').on('click', function() {
        $('#modal-title').text('<?php _e('Import Categories', 'school-management-system'); ?>');
        $('#import-section').show();
        $('#export-section').hide();
        $('#confirm-import').hide();
        $('#download-export').hide();
        $('#import-export-modal').show();
    });
    
    // Export categories
    $('#export-categories').on('click', function() {
        $('#modal-title').text('<?php _e('Export Categories', 'school-management-system'); ?>');
        $('#import-section').hide();
        $('#export-section').show();
        $('#confirm-import').hide();
        $('#download-export').show();
        
        // Get current categories configuration
        var exportData = {
            export_date: new Date().toISOString(),
            export_version: '1.0',
            categories: <?php echo json_encode($fee_categories); ?>
        };
        
        $('#export-content').val(JSON.stringify(exportData, null, 2));
        $('#import-export-modal').show();
    });
    
    // Handle file import
    $('#import-file').on('change', function() {
        var file = this.files[0];
        if (file) {
            var reader = new FileReader();
            reader.onload = function(e) {
                try {
                    var data = JSON.parse(e.target.result);
                    $('#import-preview-content').text(JSON.stringify(data, null, 2));
                    $('#import-preview').show();
                    $('#confirm-import').show();
                } catch (error) {
                    alert('<?php _e('Invalid JSON file', 'school-management-system'); ?>');
                    $('#import-preview').hide();
                    $('#confirm-import').hide();
                }
            };
            reader.readAsText(file);
        }
    });
    
    // Download export
    $('#download-export').on('click', function() {
        var content = $('#export-content').val();
        var blob = new Blob([content], { type: 'application/json' });
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = 'fee-categories-' + new Date().toISOString().split('T')[0] + '.json';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    });
    
    // Close modal
    $('#close-modal, .modal-close').on('click', function() {
        $('#import-export-modal').hide();
    });
    
    // Close modal on outside click
    $('#import-export-modal').on('click', function(e) {
        if (e.target === this) {
            $(this).hide();
        }
    });
});
</script>