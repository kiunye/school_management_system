<?php
/**
 * Notices Management Interface
 *
 * @package SchoolManagementSystem
 * @subpackage Admin/Partials
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get current tab
$current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'active';

// Handle bulk actions
if (isset($_POST['bulk_action']) && isset($_POST['notice_ids'])) {
    $this->handle_bulk_actions($_POST['bulk_action'], $_POST['notice_ids']);
}

// Get notices based on current tab
$notices = $this->get_notices_by_status($current_tab);
?>

<div class="wrap">
    <h1 class="wp-heading-inline">
        <?php _e('Notice Management', 'school-management-system'); ?>
    </h1>
    
    <a href="<?php echo admin_url('post-new.php?post_type=sms_notices'); ?>" class="page-title-action">
        <?php _e('Add New Notice', 'school-management-system'); ?>
    </a>
    
    <hr class="wp-header-end">

    <!-- Tab Navigation -->
    <nav class="nav-tab-wrapper wp-clearfix">
        <a href="<?php echo admin_url('admin.php?page=sms-notices&tab=active'); ?>" 
           class="nav-tab <?php echo $current_tab === 'active' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Active Notices', 'school-management-system'); ?>
            <span class="count">(<?php echo $this->get_notices_count('active'); ?>)</span>
        </a>
        <a href="<?php echo admin_url('admin.php?page=sms-notices&tab=draft'); ?>" 
           class="nav-tab <?php echo $current_tab === 'draft' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Drafts', 'school-management-system'); ?>
            <span class="count">(<?php echo $this->get_notices_count('draft'); ?>)</span>
        </a>
        <a href="<?php echo admin_url('admin.php?page=sms-notices&tab=expired'); ?>" 
           class="nav-tab <?php echo $current_tab === 'expired' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Expired', 'school-management-system'); ?>
            <span class="count">(<?php echo $this->get_notices_count('expired'); ?>)</span>
        </a>
        <a href="<?php echo admin_url('admin.php?page=sms-notices&tab=archived'); ?>" 
           class="nav-tab <?php echo $current_tab === 'archived' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Archived', 'school-management-system'); ?>
            <span class="count">(<?php echo $this->get_notices_count('archived'); ?>)</span>
        </a>
    </nav>

    <!-- Bulk Actions and Filters -->
    <div class="tablenav top">
        <div class="alignleft actions bulkactions">
            <form method="post" id="bulk-action-form">
                <?php wp_nonce_field('sms_notices_bulk_action', 'notices_nonce'); ?>
                <label for="bulk-action-selector-top" class="screen-reader-text">
                    <?php _e('Select bulk action', 'school-management-system'); ?>
                </label>
                <select name="bulk_action" id="bulk-action-selector-top">
                    <option value="-1"><?php _e('Bulk Actions', 'school-management-system'); ?></option>
                    <?php if ($current_tab === 'active'): ?>
                        <option value="expire"><?php _e('Mark as Expired', 'school-management-system'); ?></option>
                        <option value="archive"><?php _e('Archive', 'school-management-system'); ?></option>
                        <option value="send_notifications"><?php _e('Send Notifications', 'school-management-system'); ?></option>
                    <?php elseif ($current_tab === 'draft'): ?>
                        <option value="publish"><?php _e('Publish', 'school-management-system'); ?></option>
                        <option value="delete"><?php _e('Delete', 'school-management-system'); ?></option>
                    <?php elseif ($current_tab === 'expired'): ?>
                        <option value="reactivate"><?php _e('Reactivate', 'school-management-system'); ?></option>
                        <option value="archive"><?php _e('Archive', 'school-management-system'); ?></option>
                    <?php elseif ($current_tab === 'archived'): ?>
                        <option value="restore"><?php _e('Restore', 'school-management-system'); ?></option>
                        <option value="delete"><?php _e('Delete Permanently', 'school-management-system'); ?></option>
                    <?php endif; ?>
                </select>
                <input type="submit" id="doaction" class="button action" value="<?php _e('Apply', 'school-management-system'); ?>">
            </form>
        </div>
        
        <div class="alignright actions">
            <button type="button" id="archive-expired-notices" class="button">
                <?php _e('Archive Expired Notices', 'school-management-system'); ?>
            </button>
        </div>
    </div>

    <!-- Notices Table -->
    <table class="wp-list-table widefat fixed striped notices">
        <thead>
            <tr>
                <td id="cb" class="manage-column column-cb check-column">
                    <label class="screen-reader-text" for="cb-select-all-1">
                        <?php _e('Select All', 'school-management-system'); ?>
                    </label>
                    <input id="cb-select-all-1" type="checkbox">
                </td>
                <th scope="col" class="manage-column column-title column-primary">
                    <?php _e('Notice Title', 'school-management-system'); ?>
                </th>
                <th scope="col" class="manage-column column-priority">
                    <?php _e('Priority', 'school-management-system'); ?>
                </th>
                <th scope="col" class="manage-column column-audience">
                    <?php _e('Target Audience', 'school-management-system'); ?>
                </th>
                <th scope="col" class="manage-column column-expiry">
                    <?php _e('Expires', 'school-management-system'); ?>
                </th>
                <th scope="col" class="manage-column column-delivery">
                    <?php _e('Delivery Status', 'school-management-system'); ?>
                </th>
                <th scope="col" class="manage-column column-author">
                    <?php _e('Created By', 'school-management-system'); ?>
                </th>
                <th scope="col" class="manage-column column-date">
                    <?php _e('Date', 'school-management-system'); ?>
                </th>
            </tr>
        </thead>
        <tbody id="the-list">
            <?php if (!empty($notices)): ?>
                <?php foreach ($notices as $notice): ?>
                    <?php
                    $priority = get_field('notice_priority', $notice->ID);
                    $audience_type = get_field('audience_type', $notice->ID);
                    $audience_count = get_field('audience_count', $notice->ID);
                    $expiry_date = get_field('notice_expiry_date', $notice->ID);
                    $delivery_status = get_field('delivery_status', $notice->ID);
                    $sent_count = get_field('sent_count', $notice->ID);
                    $failed_count = get_field('failed_count', $notice->ID);
                    ?>
                    <tr id="notice-<?php echo $notice->ID; ?>">
                        <th scope="row" class="check-column">
                            <input type="checkbox" name="notice_ids[]" value="<?php echo $notice->ID; ?>">
                        </th>
                        <td class="title column-title column-primary">
                            <strong>
                                <a href="<?php echo get_edit_post_link($notice->ID); ?>">
                                    <?php echo esc_html($notice->post_title); ?>
                                </a>
                            </strong>
                            <div class="row-actions">
                                <span class="edit">
                                    <a href="<?php echo get_edit_post_link($notice->ID); ?>">
                                        <?php _e('Edit', 'school-management-system'); ?>
                                    </a> |
                                </span>
                                <span class="view">
                                    <a href="<?php echo get_permalink($notice->ID); ?>" target="_blank">
                                        <?php _e('View', 'school-management-system'); ?>
                                    </a> |
                                </span>
                                <?php if ($current_tab === 'active'): ?>
                                    <span class="send-notification">
                                        <a href="#" class="send-notice-notification" data-notice-id="<?php echo $notice->ID; ?>">
                                            <?php _e('Send Notification', 'school-management-system'); ?>
                                        </a> |
                                    </span>
                                <?php endif; ?>
                                <span class="trash">
                                    <a href="<?php echo get_delete_post_link($notice->ID); ?>" class="submitdelete">
                                        <?php _e('Delete', 'school-management-system'); ?>
                                    </a>
                                </span>
                            </div>
                        </td>
                        <td class="priority column-priority">
                            <?php
                            $priority_classes = array(
                                'low' => 'priority-low',
                                'normal' => 'priority-normal',
                                'high' => 'priority-high',
                                'urgent' => 'priority-urgent',
                                'emergency' => 'priority-emergency'
                            );
                            $priority_labels = array(
                                'low' => __('Low', 'school-management-system'),
                                'normal' => __('Normal', 'school-management-system'),
                                'high' => __('High', 'school-management-system'),
                                'urgent' => __('Urgent', 'school-management-system'),
                                'emergency' => __('Emergency', 'school-management-system')
                            );
                            ?>
                            <span class="priority-badge <?php echo $priority_classes[$priority] ?? ''; ?>">
                                <?php echo $priority_labels[$priority] ?? '—'; ?>
                            </span>
                        </td>
                        <td class="audience column-audience">
                            <?php
                            $audience_labels = array(
                                'all' => __('All Users', 'school-management-system'),
                                'roles' => __('Specific Roles', 'school-management-system'),
                                'classes' => __('Specific Classes', 'school-management-system'),
                                'individuals' => __('Individuals', 'school-management-system'),
                                'parents_of_class' => __('Parents of Classes', 'school-management-system'),
                                'custom' => __('Custom', 'school-management-system')
                            );
                            echo $audience_labels[$audience_type] ?? '—';
                            
                            if ($audience_count) {
                                echo '<br><small>(' . sprintf(__('%d recipients', 'school-management-system'), $audience_count) . ')</small>';
                            }
                            ?>
                        </td>
                        <td class="expiry column-expiry">
                            <?php if ($expiry_date): ?>
                                <?php
                                $expiry_timestamp = strtotime($expiry_date);
                                $now = current_time('timestamp');
                                $is_expired = $expiry_timestamp < $now;
                                ?>
                                <span class="<?php echo $is_expired ? 'expired' : ''; ?>">
                                    <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $expiry_timestamp); ?>
                                </span>
                                <?php if ($is_expired): ?>
                                    <br><small class="expired-text"><?php _e('Expired', 'school-management-system'); ?></small>
                                <?php endif; ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td class="delivery column-delivery">
                            <?php if ($delivery_status): ?>
                                <span class="delivery-status delivery-<?php echo $delivery_status; ?>">
                                    <?php echo ucfirst($delivery_status); ?>
                                </span>
                                <?php if ($sent_count || $failed_count): ?>
                                    <br><small>
                                        <?php printf(__('Sent: %d | Failed: %d', 'school-management-system'), $sent_count ?: 0, $failed_count ?: 0); ?>
                                    </small>
                                <?php endif; ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td class="author column-author">
                            <?php
                            $author = get_userdata($notice->post_author);
                            echo $author ? $author->display_name : '—';
                            ?>
                        </td>
                        <td class="date column-date">
                            <?php echo date_i18n(get_option('date_format'), strtotime($notice->post_date)); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr class="no-items">
                    <td class="colspanchange" colspan="8">
                        <?php _e('No notices found.', 'school-management-system'); ?>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <?php if (!empty($notices) && count($notices) > 20): ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <!-- Pagination will be added here -->
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Notice Creation Modal -->
<div id="notice-creation-modal" class="sms-modal" style="display: none;">
    <div class="sms-modal-content">
        <div class="sms-modal-header">
            <h2><?php _e('Quick Notice Creation', 'school-management-system'); ?></h2>
            <span class="sms-modal-close">&times;</span>
        </div>
        <div class="sms-modal-body">
            <form id="quick-notice-form">
                <?php wp_nonce_field('sms_create_notice', 'notice_nonce'); ?>
                
                <div class="form-group">
                    <label for="notice_title"><?php _e('Notice Title', 'school-management-system'); ?></label>
                    <input type="text" id="notice_title" name="notice_title" required>
                </div>
                
                <div class="form-group">
                    <label for="notice_content"><?php _e('Notice Content', 'school-management-system'); ?></label>
                    <textarea id="notice_content" name="notice_content" rows="5" required></textarea>
                </div>
                
                <div class="form-group">
                    <label for="notice_priority"><?php _e('Priority', 'school-management-system'); ?></label>
                    <select id="notice_priority" name="notice_priority">
                        <option value="normal"><?php _e('Normal', 'school-management-system'); ?></option>
                        <option value="high"><?php _e('High', 'school-management-system'); ?></option>
                        <option value="urgent"><?php _e('Urgent', 'school-management-system'); ?></option>
                        <option value="emergency"><?php _e('Emergency', 'school-management-system'); ?></option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="audience_type"><?php _e('Target Audience', 'school-management-system'); ?></label>
                    <select id="audience_type" name="audience_type">
                        <option value="all"><?php _e('All Users', 'school-management-system'); ?></option>
                        <option value="roles"><?php _e('Specific Roles', 'school-management-system'); ?></option>
                        <option value="classes"><?php _e('Specific Classes', 'school-management-system'); ?></option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="send_notifications" name="send_notifications" value="1">
                        <?php _e('Send SMS notifications immediately', 'school-management-system'); ?>
                    </label>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="button button-primary">
                        <?php _e('Create Notice', 'school-management-system'); ?>
                    </button>
                    <button type="button" class="button sms-modal-close">
                        <?php _e('Cancel', 'school-management-system'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.priority-badge {
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: bold;
    text-transform: uppercase;
}

.priority-low { background: #f0f0f1; color: #646970; }
.priority-normal { background: #c3c4c7; color: #1d2327; }
.priority-high { background: #fcf9e8; color: #b32d2e; }
.priority-urgent { background: #fef7f0; color: #d63638; }
.priority-emergency { background: #fcf0f1; color: #d63638; font-weight: bold; }

.delivery-status {
    padding: 2px 6px;
    border-radius: 2px;
    font-size: 11px;
}

.delivery-pending { background: #f0f0f1; color: #646970; }
.delivery-sending { background: #fff3cd; color: #856404; }
.delivery-sent { background: #d1ecf1; color: #0c5460; }
.delivery-failed { background: #f8d7da; color: #721c24; }
.delivery-partial { background: #ffeaa7; color: #6c757d; }

.expired { color: #d63638; }
.expired-text { color: #d63638; font-style: italic; }

.sms-modal {
    position: fixed;
    z-index: 100000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.sms-modal-content {
    background-color: #fefefe;
    margin: 5% auto;
    padding: 0;
    border: 1px solid #888;
    width: 80%;
    max-width: 600px;
    border-radius: 4px;
}

.sms-modal-header {
    padding: 15px 20px;
    background-color: #f1f1f1;
    border-bottom: 1px solid #ddd;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.sms-modal-close {
    color: #aaa;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}

.sms-modal-close:hover {
    color: #000;
}

.sms-modal-body {
    padding: 20px;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 3px;
}

.form-actions {
    margin-top: 20px;
    text-align: right;
}

.form-actions .button {
    margin-left: 10px;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Handle bulk actions
    $('#bulk-action-form').on('submit', function(e) {
        var action = $('#bulk-action-selector-top').val();
        var checkedBoxes = $('input[name="notice_ids[]"]:checked');
        
        if (action === '-1') {
            e.preventDefault();
            alert('<?php _e("Please select an action.", "school-management-system"); ?>');
            return false;
        }
        
        if (checkedBoxes.length === 0) {
            e.preventDefault();
            alert('<?php _e("Please select at least one notice.", "school-management-system"); ?>');
            return false;
        }
        
        if (action === 'delete') {
            if (!confirm('<?php _e("Are you sure you want to delete the selected notices?", "school-management-system"); ?>')) {
                e.preventDefault();
                return false;
            }
        }
    });
    
    // Handle individual notice notification sending
    $('.send-notice-notification').on('click', function(e) {
        e.preventDefault();
        var noticeId = $(this).data('notice-id');
        var button = $(this);
        
        if (confirm('<?php _e("Send notifications for this notice now?", "school-management-system"); ?>')) {
            button.text('<?php _e("Sending...", "school-management-system"); ?>');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'sms_send_notice',
                    notice_id: noticeId,
                    nonce: '<?php echo wp_create_nonce("sms_send_notice_nonce"); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        alert('<?php _e("Notifications sent successfully!", "school-management-system"); ?>');
                        location.reload();
                    } else {
                        alert('<?php _e("Error sending notifications: ", "school-management-system"); ?>' + response.data.message);
                        button.text('<?php _e("Send Notification", "school-management-system"); ?>');
                    }
                },
                error: function() {
                    alert('<?php _e("Error sending notifications. Please try again.", "school-management-system"); ?>');
                    button.text('<?php _e("Send Notification", "school-management-system"); ?>');
                }
            });
        }
    });
    
    // Handle archive expired notices
    $('#archive-expired-notices').on('click', function(e) {
        e.preventDefault();
        var button = $(this);
        
        if (confirm('<?php _e("Archive all expired notices?", "school-management-system"); ?>')) {
            button.text('<?php _e("Processing...", "school-management-system"); ?>');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'sms_archive_expired_notices',
                    nonce: '<?php echo wp_create_nonce("sms_archive_notices_nonce"); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        location.reload();
                    } else {
                        alert('<?php _e("Error archiving notices.", "school-management-system"); ?>');
                        button.text('<?php _e("Archive Expired Notices", "school-management-system"); ?>');
                    }
                },
                error: function() {
                    alert('<?php _e("Error archiving notices. Please try again.", "school-management-system"); ?>');
                    button.text('<?php _e("Archive Expired Notices", "school-management-system"); ?>');
                }
            });
        }
    });
    
    // Select all checkbox functionality
    $('#cb-select-all-1').on('change', function() {
        $('input[name="notice_ids[]"]').prop('checked', this.checked);
    });
});
</script>