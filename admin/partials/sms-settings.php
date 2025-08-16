<?php
/**
 * SMS Settings Interface
 *
 * @package SchoolManagementSystem
 * @subpackage Admin/Partials
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get current tab
$current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'api';

// Initialize SMS components
if (!class_exists('SMS_Africastalking_API')) {
    require_once SMS_PLUGIN_DIR . 'includes/integrations/class-sms-africastalking-api.php';
}
if (!class_exists('SMS_Template_Manager')) {
    require_once SMS_PLUGIN_DIR . 'includes/integrations/class-sms-template-manager.php';
}
if (!class_exists('SMS_Notification_Scheduler')) {
    require_once SMS_PLUGIN_DIR . 'includes/integrations/class-sms-notification-scheduler.php';
}

$africastalking_api = new SMS_Africastalking_API();
$template_manager = new SMS_Template_Manager();
$notification_scheduler = new SMS_Notification_Scheduler();

// Handle form submissions
if (isset($_POST['submit']) && wp_verify_nonce($_POST['sms_settings_nonce'], 'sms_settings_action')) {
    $this->handle_settings_form_submission();
}

// Get current settings
$africastalking_settings = get_option('sms_africastalking_settings', array());
$sms_general_settings = get_option('sms_general_settings', array());
?>

<div class="wrap">
    <h1 class="wp-heading-inline">
        <?php _e('SMS Communication Settings', 'school-management-system'); ?>
    </h1>
    
    <hr class="wp-header-end">

    <!-- Tab Navigation -->
    <nav class="nav-tab-wrapper wp-clearfix">
        <a href="<?php echo admin_url('admin.php?page=sms-communication&tab=api'); ?>" 
           class="nav-tab <?php echo $current_tab === 'api' ? 'nav-tab-active' : ''; ?>">
            <?php _e('API Settings', 'school-management-system'); ?>
        </a>
        <a href="<?php echo admin_url('admin.php?page=sms-communication&tab=templates'); ?>" 
           class="nav-tab <?php echo $current_tab === 'templates' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Message Templates', 'school-management-system'); ?>
        </a>
        <a href="<?php echo admin_url('admin.php?page=sms-communication&tab=notifications'); ?>" 
           class="nav-tab <?php echo $current_tab === 'notifications' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Automated Notifications', 'school-management-system'); ?>
        </a>
        <a href="<?php echo admin_url('admin.php?page=sms-communication&tab=queue'); ?>" 
           class="nav-tab <?php echo $current_tab === 'queue' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Message Queue', 'school-management-system'); ?>
        </a>
        <a href="<?php echo admin_url('admin.php?page=sms-communication&tab=reports'); ?>" 
           class="nav-tab <?php echo $current_tab === 'reports' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Reports & Analytics', 'school-management-system'); ?>
        </a>
    </nav>

    <div class="tab-content">
        <?php if ($current_tab === 'api'): ?>
            <!-- API Settings Tab -->
            <div class="sms-settings-section">
                <h2><?php _e('Africastalking API Configuration', 'school-management-system'); ?></h2>
                
                <form method="post" action="">
                    <?php wp_nonce_field('sms_settings_action', 'sms_settings_nonce'); ?>
                    <input type="hidden" name="tab" value="api">
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="africastalking_username"><?php _e('Username', 'school-management-system'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="africastalking_username" name="africastalking_settings[username]" 
                                       value="<?php echo esc_attr($africastalking_settings['username'] ?? ''); ?>" 
                                       class="regular-text" required>
                                <p class="description"><?php _e('Your Africastalking username', 'school-management-system'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="africastalking_api_key"><?php _e('API Key', 'school-management-system'); ?></label>
                            </th>
                            <td>
                                <input type="password" id="africastalking_api_key" name="africastalking_settings[api_key]" 
                                       value="<?php echo esc_attr($africastalking_settings['api_key'] ?? ''); ?>" 
                                       class="regular-text" required>
                                <p class="description"><?php _e('Your Africastalking API key', 'school-management-system'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="africastalking_sender_id"><?php _e('Sender ID', 'school-management-system'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="africastalking_sender_id" name="africastalking_settings[sender_id]" 
                                       value="<?php echo esc_attr($africastalking_settings['sender_id'] ?? ''); ?>" 
                                       class="regular-text">
                                <p class="description"><?php _e('Optional: Custom sender ID (must be approved by Africastalking)', 'school-management-system'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="africastalking_environment"><?php _e('Environment', 'school-management-system'); ?></label>
                            </th>
                            <td>
                                <select id="africastalking_environment" name="africastalking_settings[environment]">
                                    <option value="sandbox" <?php selected($africastalking_settings['environment'] ?? 'sandbox', 'sandbox'); ?>>
                                        <?php _e('Sandbox (Testing)', 'school-management-system'); ?>
                                    </option>
                                    <option value="production" <?php selected($africastalking_settings['environment'] ?? 'sandbox', 'production'); ?>>
                                        <?php _e('Production (Live)', 'school-management-system'); ?>
                                    </option>
                                </select>
                                <p class="description"><?php _e('Use sandbox for testing, production for live messages', 'school-management-system'); ?></p>
                            </td>
                        </tr>
                    </table>
                    
                    <div class="api-test-section">
                        <h3><?php _e('Test Connection', 'school-management-system'); ?></h3>
                        <p><?php _e('Test your API configuration to ensure it\'s working correctly.', 'school-management-system'); ?></p>
                        
                        <button type="button" id="test-api-connection" class="button button-secondary">
                            <?php _e('Test Connection', 'school-management-system'); ?>
                        </button>
                        
                        <button type="button" id="check-balance" class="button button-secondary">
                            <?php _e('Check Balance', 'school-management-system'); ?>
                        </button>
                        
                        <div id="api-test-results" class="api-test-results" style="display: none;">
                            <div class="test-result"></div>
                        </div>
                    </div>
                    
                    <?php submit_button(__('Save API Settings', 'school-management-system')); ?>
                </form>
            </div>

        <?php elseif ($current_tab === 'templates'): ?>
            <!-- Templates Tab -->
            <div class="sms-templates-section">
                <h2><?php _e('SMS Message Templates', 'school-management-system'); ?></h2>
                
                <div class="templates-toolbar">
                    <button type="button" id="create-new-template" class="button button-primary">
                        <?php _e('Create New Template', 'school-management-system'); ?>
                    </button>
                    
                    <div class="template-filters">
                        <label for="template-category-filter"><?php _e('Filter by category:', 'school-management-system'); ?></label>
                        <select id="template-category-filter">
                            <option value=""><?php _e('All Categories', 'school-management-system'); ?></option>
                            <?php foreach ($template_manager->get_categories() as $category_id => $category_name): ?>
                                <option value="<?php echo esc_attr($category_id); ?>"><?php echo esc_html($category_name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="templates-list">
                    <?php
                    $templates = $template_manager->get_templates();
                    if (!empty($templates)):
                    ?>
                        <table class="wp-list-table widefat fixed striped templates">
                            <thead>
                                <tr>
                                    <th scope="col" class="manage-column column-name column-primary">
                                        <?php _e('Template Name', 'school-management-system'); ?>
                                    </th>
                                    <th scope="col" class="manage-column column-category">
                                        <?php _e('Category', 'school-management-system'); ?>
                                    </th>
                                    <th scope="col" class="manage-column column-placeholders">
                                        <?php _e('Placeholders', 'school-management-system'); ?>
                                    </th>
                                    <th scope="col" class="manage-column column-actions">
                                        <?php _e('Actions', 'school-management-system'); ?>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($templates as $template_id => $template): ?>
                                    <tr data-template-id="<?php echo esc_attr($template_id); ?>" 
                                        data-category="<?php echo esc_attr($template['category']); ?>">
                                        <td class="name column-name column-primary">
                                            <strong><?php echo esc_html($template['name']); ?></strong>
                                            <div class="template-description">
                                                <?php echo esc_html($template['description'] ?? ''); ?>
                                            </div>
                                            <div class="row-actions">
                                                <span class="edit">
                                                    <a href="#" class="edit-template" data-template-id="<?php echo esc_attr($template_id); ?>">
                                                        <?php _e('Edit', 'school-management-system'); ?>
                                                    </a> |
                                                </span>
                                                <span class="preview">
                                                    <a href="#" class="preview-template" data-template-id="<?php echo esc_attr($template_id); ?>">
                                                        <?php _e('Preview', 'school-management-system'); ?>
                                                    </a> |
                                                </span>
                                                <span class="duplicate">
                                                    <a href="#" class="duplicate-template" data-template-id="<?php echo esc_attr($template_id); ?>">
                                                        <?php _e('Duplicate', 'school-management-system'); ?>
                                                    </a>
                                                    <?php if (!isset($template_manager->get_default_templates()[$template_id])): ?>
                                                        |
                                                        <span class="delete">
                                                            <a href="#" class="delete-template" data-template-id="<?php echo esc_attr($template_id); ?>">
                                                                <?php _e('Delete', 'school-management-system'); ?>
                                                            </a>
                                                        </span>
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td class="category column-category">
                                            <?php
                                            $categories = $template_manager->get_categories();
                                            echo esc_html($categories[$template['category']] ?? $template['category']);
                                            ?>
                                        </td>
                                        <td class="placeholders column-placeholders">
                                            <?php if (!empty($template['placeholders'])): ?>
                                                <div class="placeholder-tags">
                                                    <?php foreach ($template['placeholders'] as $placeholder): ?>
                                                        <span class="placeholder-tag">{<?php echo esc_html($placeholder); ?>}</span>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php else: ?>
                                                —
                                            <?php endif; ?>
                                        </td>
                                        <td class="actions column-actions">
                                            <button type="button" class="button button-small test-template" 
                                                    data-template-id="<?php echo esc_attr($template_id); ?>">
                                                <?php _e('Test Send', 'school-management-system'); ?>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="no-templates">
                            <p><?php _e('No templates found.', 'school-management-system'); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($current_tab === 'notifications'): ?>
            <!-- Notifications Tab -->
            <div class="sms-notifications-section">
                <h2><?php _e('Automated Notification Rules', 'school-management-system'); ?></h2>
                
                <div class="notifications-toolbar">
                    <button type="button" id="create-notification-rule" class="button button-primary">
                        <?php _e('Create New Rule', 'school-management-system'); ?>
                    </button>
                </div>
                
                <div class="notification-rules-list">
                    <?php
                    $notification_rules = $notification_scheduler->get_notification_rules();
                    if (!empty($notification_rules)):
                    ?>
                        <table class="wp-list-table widefat fixed striped notification-rules">
                            <thead>
                                <tr>
                                    <th scope="col" class="manage-column column-name column-primary">
                                        <?php _e('Rule Name', 'school-management-system'); ?>
                                    </th>
                                    <th scope="col" class="manage-column column-trigger">
                                        <?php _e('Trigger', 'school-management-system'); ?>
                                    </th>
                                    <th scope="col" class="manage-column column-template">
                                        <?php _e('Template', 'school-management-system'); ?>
                                    </th>
                                    <th scope="col" class="manage-column column-status">
                                        <?php _e('Status', 'school-management-system'); ?>
                                    </th>
                                    <th scope="col" class="manage-column column-actions">
                                        <?php _e('Actions', 'school-management-system'); ?>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($notification_rules as $rule_id => $rule): ?>
                                    <tr data-rule-id="<?php echo esc_attr($rule_id); ?>">
                                        <td class="name column-name column-primary">
                                            <strong><?php echo esc_html($rule['name']); ?></strong>
                                            <div class="rule-description">
                                                <?php echo esc_html($rule['description'] ?? ''); ?>
                                            </div>
                                            <div class="row-actions">
                                                <span class="edit">
                                                    <a href="#" class="edit-rule" data-rule-id="<?php echo esc_attr($rule_id); ?>">
                                                        <?php _e('Edit', 'school-management-system'); ?>
                                                    </a> |
                                                </span>
                                                <span class="test">
                                                    <a href="#" class="test-rule" data-rule-id="<?php echo esc_attr($rule_id); ?>">
                                                        <?php _e('Test', 'school-management-system'); ?>
                                                    </a>
                                                </span>
                                            </div>
                                        </td>
                                        <td class="trigger column-trigger">
                                            <?php echo esc_html(ucfirst(str_replace('_', ' ', $rule['trigger']))); ?>
                                        </td>
                                        <td class="template column-template">
                                            <?php echo esc_html($rule['template_id']); ?>
                                        </td>
                                        <td class="status column-status">
                                            <span class="status-badge status-<?php echo $rule['enabled'] ? 'enabled' : 'disabled'; ?>">
                                                <?php echo $rule['enabled'] ? __('Enabled', 'school-management-system') : __('Disabled', 'school-management-system'); ?>
                                            </span>
                                        </td>
                                        <td class="actions column-actions">
                                            <button type="button" class="button button-small toggle-rule" 
                                                    data-rule-id="<?php echo esc_attr($rule_id); ?>"
                                                    data-current-status="<?php echo $rule['enabled'] ? 'enabled' : 'disabled'; ?>">
                                                <?php echo $rule['enabled'] ? __('Disable', 'school-management-system') : __('Enable', 'school-management-system'); ?>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="no-rules">
                            <p><?php _e('No notification rules found.', 'school-management-system'); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($current_tab === 'queue'): ?>
            <!-- Queue Tab -->
            <div class="sms-queue-section">
                <h2><?php _e('SMS Message Queue', 'school-management-system'); ?></h2>
                
                <div class="queue-stats">
                    <?php
                    if (!class_exists('SMS_Queue_Manager')) {
                        require_once SMS_PLUGIN_DIR . 'includes/integrations/class-sms-queue-manager.php';
                    }
                    $queue_manager = new SMS_Queue_Manager();
                    $queue_stats = $queue_manager->get_queue_stats();
                    ?>
                    
                    <div class="stats-cards">
                        <div class="stat-card">
                            <div class="stat-number"><?php echo number_format($queue_stats['pending'] ?? 0); ?></div>
                            <div class="stat-label"><?php _e('Pending', 'school-management-system'); ?></div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo number_format($queue_stats['sent'] ?? 0); ?></div>
                            <div class="stat-label"><?php _e('Sent', 'school-management-system'); ?></div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo number_format($queue_stats['failed'] ?? 0); ?></div>
                            <div class="stat-label"><?php _e('Failed', 'school-management-system'); ?></div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo number_format($queue_stats['processing'] ?? 0); ?></div>
                            <div class="stat-label"><?php _e('Processing', 'school-management-system'); ?></div>
                        </div>
                    </div>
                </div>
                
                <div class="queue-actions">
                    <button type="button" id="process-queue-now" class="button button-primary">
                        <?php _e('Process Queue Now', 'school-management-system'); ?>
                    </button>
                    
                    <button type="button" id="retry-failed-messages" class="button button-secondary">
                        <?php _e('Retry Failed Messages', 'school-management-system'); ?>
                    </button>
                    
                    <button type="button" id="clear-completed-messages" class="button button-secondary">
                        <?php _e('Clear Completed Messages', 'school-management-system'); ?>
                    </button>
                </div>
                
                <div class="queue-items">
                    <h3><?php _e('Recent Queue Items', 'school-management-system'); ?></h3>
                    <div id="queue-items-list">
                        <!-- Queue items will be loaded via AJAX -->
                    </div>
                </div>
            </div>

        <?php elseif ($current_tab === 'reports'): ?>
            <!-- Reports Tab -->
            <div class="sms-reports-section">
                <h2><?php _e('SMS Reports & Analytics', 'school-management-system'); ?></h2>
                
                <div class="reports-filters">
                    <label for="report-period"><?php _e('Period:', 'school-management-system'); ?></label>
                    <select id="report-period">
                        <option value="day"><?php _e('Last 24 Hours', 'school-management-system'); ?></option>
                        <option value="week"><?php _e('Last Week', 'school-management-system'); ?></option>
                        <option value="month" selected><?php _e('Last Month', 'school-management-system'); ?></option>
                        <option value="year"><?php _e('Last Year', 'school-management-system'); ?></option>
                    </select>
                    
                    <button type="button" id="refresh-reports" class="button button-secondary">
                        <?php _e('Refresh', 'school-management-system'); ?>
                    </button>
                </div>
                
                <div class="reports-content">
                    <div class="usage-stats">
                        <h3><?php _e('Usage Statistics', 'school-management-system'); ?></h3>
                        <div id="usage-stats-content">
                            <!-- Stats will be loaded via AJAX -->
                        </div>
                    </div>
                    
                    <div class="notification-stats">
                        <h3><?php _e('Notification Statistics', 'school-management-system'); ?></h3>
                        <div id="notification-stats-content">
                            <!-- Notification stats will be loaded via AJAX -->
                        </div>
                    </div>
                    
                    <div class="recent-activity">
                        <h3><?php _e('Recent Activity', 'school-management-system'); ?></h3>
                        <div id="recent-activity-content">
                            <!-- Recent activity will be loaded via AJAX -->
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Template Editor Modal -->
<div id="template-editor-modal" class="sms-modal" style="display: none;">
    <div class="sms-modal-content">
        <div class="sms-modal-header">
            <h2 id="template-editor-title"><?php _e('Edit Template', 'school-management-system'); ?></h2>
            <span class="sms-modal-close">&times;</span>
        </div>
        <div class="sms-modal-body">
            <form id="template-editor-form">
                <input type="hidden" id="template-id" name="template_id">
                
                <div class="form-group">
                    <label for="template-name"><?php _e('Template Name', 'school-management-system'); ?></label>
                    <input type="text" id="template-name" name="template_name" required>
                </div>
                
                <div class="form-group">
                    <label for="template-category"><?php _e('Category', 'school-management-system'); ?></label>
                    <select id="template-category" name="template_category" required>
                        <?php foreach ($template_manager->get_categories() as $category_id => $category_name): ?>
                            <option value="<?php echo esc_attr($category_id); ?>"><?php echo esc_html($category_name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="template-content"><?php _e('Message Content', 'school-management-system'); ?></label>
                    <textarea id="template-content" name="template_content" rows="6" required></textarea>
                    <div class="character-count">
                        <span id="character-count">0</span> / 1000 <?php _e('characters', 'school-management-system'); ?>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="template-description"><?php _e('Description', 'school-management-system'); ?></label>
                    <textarea id="template-description" name="template_description" rows="2"></textarea>
                </div>
                
                <div class="placeholders-section">
                    <h4><?php _e('Available Placeholders', 'school-management-system'); ?></h4>
                    <div id="available-placeholders">
                        <!-- Placeholders will be loaded based on category -->
                    </div>
                </div>
                
                <div class="template-preview">
                    <h4><?php _e('Preview', 'school-management-system'); ?></h4>
                    <div id="template-preview-content">
                        <!-- Preview will be shown here -->
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="button button-primary">
                        <?php _e('Save Template', 'school-management-system'); ?>
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
/* SMS Settings Styles */
.sms-settings-section,
.sms-templates-section,
.sms-notifications-section,
.sms-queue-section,
.sms-reports-section {
    margin-top: 20px;
}

.api-test-section {
    margin-top: 30px;
    padding: 20px;
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 4px;
}

.api-test-results {
    margin-top: 15px;
    padding: 10px;
    border-radius: 4px;
}

.api-test-results.success {
    background: #d1ecf1;
    border: 1px solid #bee5eb;
    color: #0c5460;
}

.api-test-results.error {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
}

.templates-toolbar,
.notifications-toolbar,
.queue-actions {
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.template-filters {
    display: flex;
    align-items: center;
    gap: 10px;
}

.placeholder-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
}

.placeholder-tag {
    background: #e3f2fd;
    color: #1976d2;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 11px;
    font-family: monospace;
}

.status-badge {
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: bold;
    text-transform: uppercase;
}

.status-enabled {
    background: #d1ecf1;
    color: #0c5460;
}

.status-disabled {
    background: #f8d7da;
    color: #721c24;
}

.stats-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 20px;
    text-align: center;
}

.stat-number {
    font-size: 32px;
    font-weight: bold;
    color: #2271b1;
    display: block;
}

.stat-label {
    font-size: 12px;
    color: #646970;
    text-transform: uppercase;
    margin-top: 5px;
}

.reports-filters {
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
}

.reports-content {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
}

.recent-activity {
    grid-column: 1 / -1;
}

/* Modal Styles */
.sms-modal {
    position: fixed;
    z-index: 100000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
}

.sms-modal-content {
    background-color: #fefefe;
    margin: 5% auto;
    padding: 0;
    border: 1px solid #888;
    width: 90%;
    max-width: 800px;
    border-radius: 4px;
    max-height: 90vh;
    overflow-y: auto;
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
    margin-bottom: 20px;
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

.character-count {
    text-align: right;
    font-size: 12px;
    color: #666;
    margin-top: 5px;
}

.placeholders-section {
    margin: 20px 0;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 4px;
}

.template-preview {
    margin: 20px 0;
    padding: 15px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.form-actions {
    margin-top: 20px;
    text-align: right;
    border-top: 1px solid #ddd;
    padding-top: 15px;
}

.form-actions .button {
    margin-left: 10px;
}

/* Responsive Design */
@media screen and (max-width: 782px) {
    .templates-toolbar,
    .notifications-toolbar,
    .queue-actions {
        flex-direction: column;
        align-items: stretch;
        gap: 10px;
    }
    
    .stats-cards {
        grid-template-columns: 1fr;
    }
    
    .reports-content {
        grid-template-columns: 1fr;
    }
    
    .sms-modal-content {
        width: 95%;
        margin: 2% auto;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // API Testing
    $('#test-api-connection').on('click', function() {
        var $button = $(this);
        var originalText = $button.text();
        
        $button.text('<?php _e("Testing...", "school-management-system"); ?>').prop('disabled', true);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'sms_test_africastalking_connection',
                nonce: '<?php echo wp_create_nonce("sms_africastalking_test_nonce"); ?>'
            },
            success: function(response) {
                var $results = $('#api-test-results');
                $results.show();
                
                if (response.success) {
                    $results.removeClass('error').addClass('success');
                    $results.find('.test-result').html('<strong><?php _e("Success:", "school-management-system"); ?></strong> ' + response.data.message);
                } else {
                    $results.removeClass('success').addClass('error');
                    $results.find('.test-result').html('<strong><?php _e("Error:", "school-management-system"); ?></strong> ' + response.data.error);
                }
            },
            error: function() {
                var $results = $('#api-test-results');
                $results.show().removeClass('success').addClass('error');
                $results.find('.test-result').html('<strong><?php _e("Error:", "school-management-system"); ?></strong> <?php _e("Connection failed", "school-management-system"); ?>');
            },
            complete: function() {
                $button.text(originalText).prop('disabled', false);
            }
        });
    });
    
    // Balance Check
    $('#check-balance').on('click', function() {
        var $button = $(this);
        var originalText = $button.text();
        
        $button.text('<?php _e("Checking...", "school-management-system"); ?>').prop('disabled', true);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'sms_get_africastalking_balance',
                nonce: '<?php echo wp_create_nonce("sms_africastalking_balance_nonce"); ?>'
            },
            success: function(response) {
                var $results = $('#api-test-results');
                $results.show();
                
                if (response.success) {
                    $results.removeClass('error').addClass('success');
                    $results.find('.test-result').html('<strong><?php _e("Balance:", "school-management-system"); ?></strong> ' + response.data.balance);
                } else {
                    $results.removeClass('success').addClass('error');
                    $results.find('.test-result').html('<strong><?php _e("Error:", "school-management-system"); ?></strong> ' + response.data.error);
                }
            },
            complete: function() {
                $button.text(originalText).prop('disabled', false);
            }
        });
    });
    
    // Template Management
    $('#create-new-template').on('click', function() {
        openTemplateEditor();
    });
    
    $('.edit-template').on('click', function(e) {
        e.preventDefault();
        var templateId = $(this).data('template-id');
        openTemplateEditor(templateId);
    });
    
    // Modal Management
    $('.sms-modal-close').on('click', function() {
        $(this).closest('.sms-modal').hide();
    });
    
    // Template Editor Functions
    function openTemplateEditor(templateId) {
        var $modal = $('#template-editor-modal');
        var $form = $('#template-editor-form');
        
        if (templateId) {
            // Load existing template
            // This would make an AJAX call to get template data
            $('#template-editor-title').text('<?php _e("Edit Template", "school-management-system"); ?>');
        } else {
            // New template
            $('#template-editor-title').text('<?php _e("Create New Template", "school-management-system"); ?>');
            $form[0].reset();
        }
        
        $modal.show();
    }
    
    // Character count for template content
    $('#template-content').on('input', function() {
        var length = $(this).val().length;
        $('#character-count').text(length);
        
        if (length > 1000) {
            $('#character-count').css('color', '#d63638');
        } else {
            $('#character-count').css('color', '#666');
        }
    });
    
    // Template category change - update placeholders
    $('#template-category').on('change', function() {
        var category = $(this).val();
        loadPlaceholders(category);
    });
    
    function loadPlaceholders(category) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'sms_get_template_placeholders',
                category: category,
                nonce: '<?php echo wp_create_nonce("sms_template_nonce"); ?>'
            },
            success: function(response) {
                if (response.success) {
                    var $container = $('#available-placeholders');
                    $container.empty();
                    
                    $.each(response.data.placeholders, function(group, placeholders) {
                        if (typeof placeholders === 'object') {
                            var $group = $('<div class="placeholder-group"><h5>' + group + '</h5></div>');
                            var $tags = $('<div class="placeholder-tags"></div>');
                            
                            $.each(placeholders, function(key, description) {
                                var $tag = $('<span class="placeholder-tag clickable" title="' + description + '">{' + key + '}</span>');
                                $tag.on('click', function() {
                                    insertPlaceholder('{' + key + '}');
                                });
                                $tags.append($tag);
                            });
                            
                            $group.append($tags);
                            $container.append($group);
                        }
                    });
                }
            }
        });
    }
    
    function insertPlaceholder(placeholder) {
        var $textarea = $('#template-content');
        var textarea = $textarea[0];
        var start = textarea.selectionStart;
        var end = textarea.selectionEnd;
        var text = $textarea.val();
        
        $textarea.val(text.substring(0, start) + placeholder + text.substring(end));
        
        // Update character count
        $('#template-content').trigger('input');
        
        // Set cursor position after inserted placeholder
        textarea.selectionStart = textarea.selectionEnd = start + placeholder.length;
        textarea.focus();
    }
    
    // Template preview
    $('#template-content').on('input', function() {
        var content = $(this).val();
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'sms_preview_template',
                template_content: content,
                nonce: '<?php echo wp_create_nonce("sms_template_nonce"); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $('#template-preview-content').html('<div class="preview-message">' + response.data.preview + '</div>');
                }
            }
        });
    });
    
    // Load initial placeholders
    loadPlaceholders($('#template-category').val());
});
</script>