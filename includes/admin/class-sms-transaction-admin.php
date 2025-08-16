<?php
/**
 * Transaction Admin Interface
 *
 * Handles admin interface for transaction management
 *
 * @package SchoolManagementSystem
 * @subpackage Admin
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Transaction Admin Class
 */
class SMS_Transaction_Admin {
    
    /**
     * Transaction manager instance
     *
     * @var SMS_Transaction_Manager
     */
    private $transaction_manager;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->transaction_manager = SMS_Transaction_Manager::get_instance();
        $this->init();
    }
    
    /**
     * Initialize the admin interface
     */
    private function init() {
        // Add admin menu items
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Add meta boxes to transaction edit screen
        add_action('add_meta_boxes', array($this, 'add_transaction_meta_boxes'));
        
        // Handle transaction actions
        add_action('admin_post_sms_update_transaction_status', array($this, 'handle_status_update'));
        add_action('admin_post_sms_verify_transaction', array($this, 'handle_verification'));
        add_action('admin_post_sms_send_receipt', array($this, 'handle_send_receipt'));
        
        // AJAX handlers for transaction management
        add_action('wp_ajax_sms_update_transaction_status', array($this, 'handle_status_update_ajax'));
        add_action('wp_ajax_sms_verify_transaction', array($this, 'handle_verification_ajax'));
        add_action('wp_ajax_sms_send_receipt', array($this, 'handle_send_receipt_ajax'));
        add_action('wp_ajax_sms_preview_receipt', array($this, 'handle_receipt_preview'));
        add_action('wp_ajax_sms_download_receipt', array($this, 'handle_receipt_download'));
        add_action('wp_ajax_sms_update_pending_transactions', array($this, 'handle_update_pending_transactions'));
        add_action('wp_ajax_sms_send_pending_receipts', array($this, 'handle_send_pending_receipts'));
        
        // Add custom columns to transaction list
        add_filter('manage_sms_transactions_posts_columns', array($this, 'add_transaction_columns'));
        add_action('manage_sms_transactions_posts_custom_column', array($this, 'populate_transaction_columns'), 10, 2);
        
        // Add bulk actions
        add_filter('bulk_actions-edit-sms_transactions', array($this, 'add_bulk_actions'));
        add_filter('handle_bulk_actions-edit-sms_transactions', array($this, 'handle_bulk_actions'), 10, 3);
        
        // Enqueue admin scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // Add admin notices
        add_action('admin_notices', array($this, 'show_admin_notices'));
    }
    
    /**
     * Add admin menu items
     */
    public function add_admin_menu() {
        // Add transaction management submenu
        add_submenu_page(
            'sms-dashboard',
            __('Transaction Management', 'school-management-system'),
            __('Transactions', 'school-management-system'),
            'manage_transactions',
            'sms-transactions',
            array($this, 'render_transaction_management_page')
        );
        
        // Add receipt management submenu
        add_submenu_page(
            'sms-dashboard',
            __('Receipt Management', 'school-management-system'),
            __('Receipts', 'school-management-system'),
            'manage_transactions',
            'sms-receipts',
            array($this, 'render_receipt_management_page')
        );
    }
    
    /**
     * Add meta boxes to transaction edit screen
     */
    public function add_transaction_meta_boxes() {
        add_meta_box(
            'sms-transaction-actions',
            __('Transaction Actions', 'school-management-system'),
            array($this, 'render_transaction_actions_meta_box'),
            'sms_transactions',
            'side',
            'high'
        );
        
        add_meta_box(
            'sms-transaction-status',
            __('Status Information', 'school-management-system'),
            array($this, 'render_transaction_status_meta_box'),
            'sms_transactions',
            'side',
            'default'
        );
        
        add_meta_box(
            'sms-receipt-info',
            __('Receipt Information', 'school-management-system'),
            array($this, 'render_receipt_info_meta_box'),
            'sms_transactions',
            'normal',
            'default'
        );
    }
    
    /**
     * Render transaction actions meta box
     */
    public function render_transaction_actions_meta_box($post) {
        $transaction_status = get_field('transaction_status', $post->ID);
        $verification_status = get_field('verification_status', $post->ID);
        $gateway_name = get_field('gateway_name', $post->ID);
        
        wp_nonce_field('sms_transaction_actions', 'sms_transaction_nonce');
        
        echo '<div class="sms-transaction-actions">';
        
        // Status update section
        echo '<h4>' . __('Update Status', 'school-management-system') . '</h4>';
        echo '<select name="new_status" id="new_status">';
        
        $statuses = array(
            SMS_Transaction_Manager::STATUS_PENDING => __('Pending', 'school-management-system'),
            SMS_Transaction_Manager::STATUS_PROCESSING => __('Processing', 'school-management-system'),
            SMS_Transaction_Manager::STATUS_COMPLETED => __('Completed', 'school-management-system'),
            SMS_Transaction_Manager::STATUS_FAILED => __('Failed', 'school-management-system'),
            SMS_Transaction_Manager::STATUS_CANCELLED => __('Cancelled', 'school-management-system'),
            SMS_Transaction_Manager::STATUS_REFUNDED => __('Refunded', 'school-management-system')
        );
        
        foreach ($statuses as $status => $label) {
            $selected = ($status === $transaction_status) ? 'selected' : '';
            echo "<option value='{$status}' {$selected}>{$label}</option>";
        }
        
        echo '</select>';
        echo '<textarea name="status_reason" placeholder="' . __('Reason for status change...', 'school-management-system') . '" rows="3" style="width: 100%; margin-top: 10px;"></textarea>';
        echo '<button type="button" class="button button-primary" id="update-status" style="margin-top: 10px;">' . __('Update Status', 'school-management-system') . '</button>';
        
        echo '<hr style="margin: 15px 0;">';
        
        // Verification section
        if (!empty($gateway_name)) {
            echo '<h4>' . __('Gateway Verification', 'school-management-system') . '</h4>';
            echo '<p><strong>' . __('Current Status:', 'school-management-system') . '</strong> ' . ucfirst(str_replace('_', ' ', $verification_status)) . '</p>';
            echo '<button type="button" class="button" id="verify-transaction">' . __('Verify with Gateway', 'school-management-system') . '</button>';
            echo '<hr style="margin: 15px 0;">';
        }
        
        // Receipt section
        echo '<h4>' . __('Receipt Management', 'school-management-system') . '</h4>';
        $receipt_sent = get_field('receipt_sent', $post->ID);
        
        if ($receipt_sent) {
            echo '<p style="color: green;">' . __('Receipt has been sent', 'school-management-system') . '</p>';
            echo '<button type="button" class="button" id="resend-receipt">' . __('Resend Receipt', 'school-management-system') . '</button>';
        } else {
            echo '<p>' . __('Receipt not sent yet', 'school-management-system') . '</p>';
            echo '<button type="button" class="button button-secondary" id="send-receipt">' . __('Send Receipt', 'school-management-system') . '</button>';
        }
        
        echo '</div>';
        
        // Add JavaScript for actions
        ?>
        <script>
        jQuery(document).ready(function($) {
            $('#update-status').click(function() {
                var newStatus = $('#new_status').val();
                var reason = $('textarea[name="status_reason"]').val();
                var nonce = $('#sms_transaction_nonce').val();
                
                $.post(ajaxurl, {
                    action: 'sms_update_transaction_status',
                    transaction_id: <?php echo $post->ID; ?>,
                    status: newStatus,
                    reason: reason,
                    nonce: nonce
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + response.data);
                    }
                });
            });
            
            $('#verify-transaction').click(function() {
                var nonce = $('#sms_transaction_nonce').val();
                
                $(this).prop('disabled', true).text('Verifying...');
                
                $.post(ajaxurl, {
                    action: 'sms_verify_transaction',
                    transaction_id: <?php echo $post->ID; ?>,
                    nonce: nonce
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + response.data);
                        $('#verify-transaction').prop('disabled', false).text('Verify with Gateway');
                    }
                });
            });
            
            $('#send-receipt, #resend-receipt').click(function() {
                var nonce = $('#sms_transaction_nonce').val();
                
                $(this).prop('disabled', true).text('Sending...');
                
                $.post(ajaxurl, {
                    action: 'sms_send_receipt',
                    transaction_id: <?php echo $post->ID; ?>,
                    nonce: nonce
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + response.data);
                        $('#send-receipt, #resend-receipt').prop('disabled', false).text('Send Receipt');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Render transaction status meta box
     */
    public function render_transaction_status_meta_box($post) {
        $transaction_status = get_field('transaction_status', $post->ID);
        $verification_status = get_field('verification_status', $post->ID);
        $gateway_name = get_field('gateway_name', $post->ID);
        $gateway_transaction_id = get_field('gateway_transaction_id', $post->ID);
        $processing_notes = get_field('processing_notes', $post->ID);
        
        echo '<table class="form-table">';
        
        echo '<tr>';
        echo '<th><label>' . __('Transaction Status', 'school-management-system') . '</label></th>';
        echo '<td><span class="status-badge status-' . esc_attr($transaction_status) . '">' . esc_html(ucfirst($transaction_status)) . '</span></td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th><label>' . __('Verification Status', 'school-management-system') . '</label></th>';
        echo '<td><span class="verification-badge verification-' . esc_attr($verification_status) . '">' . esc_html(ucfirst(str_replace('_', ' ', $verification_status))) . '</span></td>';
        echo '</tr>';
        
        if (!empty($gateway_name)) {
            echo '<tr>';
            echo '<th><label>' . __('Payment Gateway', 'school-management-system') . '</label></th>';
            echo '<td>' . esc_html(ucfirst($gateway_name)) . '</td>';
            echo '</tr>';
        }
        
        if (!empty($gateway_transaction_id)) {
            echo '<tr>';
            echo '<th><label>' . __('Gateway Transaction ID', 'school-management-system') . '</label></th>';
            echo '<td><code>' . esc_html($gateway_transaction_id) . '</code></td>';
            echo '</tr>';
        }
        
        echo '</table>';
        
        if (!empty($processing_notes)) {
            echo '<h4>' . __('Processing Notes', 'school-management-system') . '</h4>';
            echo '<div style="background: #f9f9f9; padding: 10px; border-left: 4px solid #0073aa; max-height: 200px; overflow-y: auto;">';
            echo '<pre style="white-space: pre-wrap; font-family: inherit; margin: 0;">' . esc_html($processing_notes) . '</pre>';
            echo '</div>';
        }
    }
    
    /**
     * Render receipt info meta box
     */
    public function render_receipt_info_meta_box($post) {
        $receipt_number = get_field('receipt_number', $post->ID);
        $receipt_sent = get_field('receipt_sent', $post->ID);
        $receipt_sent_date = get_field('receipt_sent_date', $post->ID);
        $receipt_methods = get_field('receipt_method', $post->ID);
        
        echo '<table class="form-table">';
        
        if (!empty($receipt_number)) {
            echo '<tr>';
            echo '<th><label>' . __('Receipt Number', 'school-management-system') . '</label></th>';
            echo '<td><strong>' . esc_html($receipt_number) . '</strong></td>';
            echo '</tr>';
        }
        
        echo '<tr>';
        echo '<th><label>' . __('Receipt Status', 'school-management-system') . '</label></th>';
        echo '<td>';
        if ($receipt_sent) {
            echo '<span style="color: green;">' . __('Sent', 'school-management-system') . '</span>';
            if (!empty($receipt_sent_date)) {
                echo '<br><small>' . date('d/m/Y H:i', strtotime($receipt_sent_date)) . '</small>';
            }
        } else {
            echo '<span style="color: orange;">' . __('Not Sent', 'school-management-system') . '</span>';
        }
        echo '</td>';
        echo '</tr>';
        
        if (!empty($receipt_methods) && is_array($receipt_methods)) {
            echo '<tr>';
            echo '<th><label>' . __('Delivery Methods', 'school-management-system') . '</label></th>';
            echo '<td>' . esc_html(implode(', ', array_map('ucfirst', $receipt_methods))) . '</td>';
            echo '</tr>';
        }
        
        echo '</table>';
        
        // Add receipt preview/download buttons
        echo '<div style="margin-top: 15px;">';
        echo '<button type="button" class="button" id="preview-receipt">' . __('Preview Receipt', 'school-management-system') . '</button>';
        echo ' <button type="button" class="button" id="download-receipt">' . __('Download Receipt', 'school-management-system') . '</button>';
        echo '</div>';
        
        ?>
        <script>
        jQuery(document).ready(function($) {
            $('#preview-receipt').click(function() {
                var url = '<?php echo admin_url('admin-ajax.php'); ?>?action=sms_preview_receipt&transaction_id=<?php echo $post->ID; ?>&nonce=<?php echo wp_create_nonce('sms_receipt_preview'); ?>';
                window.open(url, '_blank');
            });
            
            $('#download-receipt').click(function() {
                var url = '<?php echo admin_url('admin-ajax.php'); ?>?action=sms_download_receipt&transaction_id=<?php echo $post->ID; ?>&nonce=<?php echo wp_create_nonce('sms_receipt_download'); ?>';
                window.location.href = url;
            });
        });
        </script>
        <?php
    }
    
    /**
     * Handle status update
     */
    public function handle_status_update() {
        if (!wp_verify_nonce($_POST['nonce'], 'sms_transaction_nonce') || 
            !current_user_can('manage_transactions')) {
            wp_send_json_error('Unauthorized');
        }
        
        $transaction_id = intval($_POST['transaction_id']);
        $new_status = sanitize_text_field($_POST['status']);
        $reason = sanitize_textarea_field($_POST['reason']);
        
        $result = $this->transaction_manager->update_transaction_status($transaction_id, $new_status, $reason);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success('Transaction status updated successfully');
    }
    
    /**
     * Handle verification
     */
    public function handle_verification() {
        if (!wp_verify_nonce($_POST['nonce'], 'sms_transaction_nonce') || 
            !current_user_can('manage_transactions')) {
            wp_send_json_error('Unauthorized');
        }
        
        $transaction_id = intval($_POST['transaction_id']);
        
        $result = $this->transaction_manager->verify_transaction($transaction_id, true);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success('Transaction verified successfully');
    }
    
    /**
     * Handle send receipt
     */
    public function handle_send_receipt() {
        if (!wp_verify_nonce($_POST['nonce'], 'sms_transaction_nonce') || 
            !current_user_can('manage_transactions')) {
            wp_send_json_error('Unauthorized');
        }
        
        $transaction_id = intval($_POST['transaction_id']);
        
        $result = $this->transaction_manager->send_receipt($transaction_id, array('email'));
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success('Receipt sent successfully');
    }
    
    /**
     * Render transaction management page
     */
    public function render_transaction_management_page() {
        echo '<div class="wrap">';
        echo '<h1>' . __('Transaction Management', 'school-management-system') . '</h1>';
        
        // Get transaction statistics
        $status_tracker = SMS_Transaction_Status_Tracker::get_instance();
        $stats = $status_tracker->get_status_statistics();
        
        // Display statistics
        echo '<div class="sms-transaction-stats" style="display: flex; gap: 20px; margin: 20px 0;">';
        
        foreach ($stats as $status => $count) {
            $status_label = ucfirst(str_replace('_', ' ', $status));
            echo '<div class="stat-card" style="background: white; padding: 15px; border: 1px solid #ddd; border-radius: 4px; text-align: center; min-width: 120px;">';
            echo '<div style="font-size: 24px; font-weight: bold; color: #0073aa;">' . $count . '</div>';
            echo '<div style="font-size: 12px; color: #666;">' . $status_label . '</div>';
            echo '</div>';
        }
        
        echo '</div>';
        
        // Quick actions
        echo '<div class="sms-quick-actions" style="background: white; padding: 15px; border: 1px solid #ddd; border-radius: 4px; margin: 20px 0;">';
        echo '<h3>' . __('Quick Actions', 'school-management-system') . '</h3>';
        echo '<button type="button" class="button button-primary" id="update-pending-transactions">' . __('Update Pending Transactions', 'school-management-system') . '</button>';
        echo ' <button type="button" class="button" id="send-pending-receipts">' . __('Send Pending Receipts', 'school-management-system') . '</button>';
        echo '</div>';
        
        echo '<p>' . __('Use the transaction list below to manage individual transactions, or use the quick actions above for bulk operations.', 'school-management-system') . '</p>';
        echo '<p><a href="' . admin_url('edit.php?post_type=sms_transactions') . '" class="button button-secondary">' . __('View All Transactions', 'school-management-system') . '</a></p>';
        
        echo '</div>';
        
        ?>
        <script>
        jQuery(document).ready(function($) {
            $('#update-pending-transactions').click(function() {
                $(this).prop('disabled', true).text('Updating...');
                
                $.post(ajaxurl, {
                    action: 'sms_update_pending_transactions',
                    nonce: '<?php echo wp_create_nonce('sms_bulk_actions'); ?>'
                }, function(response) {
                    if (response.success) {
                        alert('Pending transactions updated successfully');
                        location.reload();
                    } else {
                        alert('Error: ' + response.data);
                    }
                    $('#update-pending-transactions').prop('disabled', false).text('Update Pending Transactions');
                });
            });
            
            $('#send-pending-receipts').click(function() {
                $(this).prop('disabled', true).text('Sending...');
                
                $.post(ajaxurl, {
                    action: 'sms_send_pending_receipts',
                    nonce: '<?php echo wp_create_nonce('sms_bulk_actions'); ?>'
                }, function(response) {
                    if (response.success) {
                        alert('Pending receipts sent successfully');
                        location.reload();
                    } else {
                        alert('Error: ' + response.data);
                    }
                    $('#send-pending-receipts').prop('disabled', false).text('Send Pending Receipts');
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Render receipt management page
     */
    public function render_receipt_management_page() {
        echo '<div class="wrap">';
        echo '<h1>' . __('Receipt Management', 'school-management-system') . '</h1>';
        echo '<p>' . __('Manage receipt templates and settings.', 'school-management-system') . '</p>';
        
        // Receipt settings form
        echo '<form method="post" action="options.php">';
        settings_fields('sms_receipt_settings');
        do_settings_sections('sms_receipt_settings');
        
        echo '<table class="form-table">';
        
        echo '<tr>';
        echo '<th scope="row">' . __('Auto-generate Receipts', 'school-management-system') . '</th>';
        echo '<td>';
        $auto_receipt = get_option('sms_auto_generate_receipts', true);
        echo '<input type="checkbox" name="sms_auto_generate_receipts" value="1" ' . checked($auto_receipt, true, false) . '>';
        echo '<label>' . __('Automatically generate receipts for completed transactions', 'school-management-system') . '</label>';
        echo '</td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th scope="row">' . __('Default Receipt Methods', 'school-management-system') . '</th>';
        echo '<td>';
        $default_methods = get_option('sms_default_receipt_methods', array('email'));
        $methods = array('email' => 'Email', 'sms' => 'SMS', 'print' => 'Print');
        
        foreach ($methods as $method => $label) {
            $checked = in_array($method, $default_methods) ? 'checked' : '';
            echo '<label><input type="checkbox" name="sms_default_receipt_methods[]" value="' . $method . '" ' . $checked . '> ' . $label . '</label><br>';
        }
        echo '</td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th scope="row">' . __('Receipt Number Prefix', 'school-management-system') . '</th>';
        echo '<td>';
        $prefix = get_option('sms_receipt_number_prefix', 'RCP');
        echo '<input type="text" name="sms_receipt_number_prefix" value="' . esc_attr($prefix) . '" class="regular-text">';
        echo '<p class="description">' . __('Prefix for receipt numbers (e.g., RCP-2024-01-00001)', 'school-management-system') . '</p>';
        echo '</td>';
        echo '</tr>';
        
        echo '</table>';
        
        submit_button();
        echo '</form>';
        
        echo '</div>';
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'sms_transactions') !== false || strpos($hook, 'sms-transactions') !== false || strpos($hook, 'sms-receipts') !== false) {
            wp_enqueue_style('sms-transaction-admin', plugin_dir_url(__FILE__) . '../../admin/css/transaction-admin.css', array(), '1.0.0');
            wp_enqueue_script('sms-transaction-admin', plugin_dir_url(__FILE__) . '../../admin/js/transaction-admin.js', array('jquery'), '1.0.0', true);
            
            wp_localize_script('sms-transaction-admin', 'smsTransactionAdmin', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('sms_transaction_admin')
            ));
        }
    }
    
    /**
     * Show admin notices
     */
    public function show_admin_notices() {
        // This method can be used to show transaction-related notices
        // Implementation depends on specific requirements
    }
    
    /**
     * Add custom columns to transaction list
     */
    public function add_transaction_columns($columns) {
        // This is handled by the CPT class, but can be extended here if needed
        return $columns;
    }
    
    /**
     * Populate custom columns
     */
    public function populate_transaction_columns($column, $post_id) {
        // This is handled by the CPT class, but can be extended here if needed
    }
    
    /**
     * Add bulk actions
     */
    public function add_bulk_actions($actions) {
        $actions['sms_verify_transactions'] = __('Verify with Gateway', 'school-management-system');
        $actions['sms_send_receipts'] = __('Send Receipts', 'school-management-system');
        $actions['sms_mark_completed'] = __('Mark as Completed', 'school-management-system');
        
        return $actions;
    }
    
    /**
     * Handle bulk actions
     */
    public function handle_bulk_actions($redirect_to, $action, $post_ids) {
        if (!current_user_can('manage_transactions')) {
            return $redirect_to;
        }
        
        switch ($action) {
            case 'sms_verify_transactions':
                $verified = 0;
                foreach ($post_ids as $post_id) {
                    $result = $this->transaction_manager->verify_transaction($post_id, true);
                    if (!is_wp_error($result)) {
                        $verified++;
                    }
                }
                $redirect_to = add_query_arg('sms_verified', $verified, $redirect_to);
                break;
                
            case 'sms_send_receipts':
                $sent = 0;
                foreach ($post_ids as $post_id) {
                    $result = $this->transaction_manager->send_receipt($post_id, array('email'));
                    if (!is_wp_error($result)) {
                        $sent++;
                    }
                }
                $redirect_to = add_query_arg('sms_receipts_sent', $sent, $redirect_to);
                break;
                
            case 'sms_mark_completed':
                $completed = 0;
                foreach ($post_ids as $post_id) {
                    $result = $this->transaction_manager->update_transaction_status(
                        $post_id, 
                        SMS_Transaction_Manager::STATUS_COMPLETED, 
                        'Bulk action: marked as completed'
                    );
                    if (!is_wp_error($result)) {
                        $completed++;
                    }
                }
                $redirect_to = add_query_arg('sms_completed', $completed, $redirect_to);
                break;
        }
        
        return $redirect_to;
    }
    
    /**
     * Handle status update via AJAX
     */
    public function handle_status_update_ajax() {
        // Verify nonce and permissions
        if (!wp_verify_nonce($_POST['nonce'], 'sms_transaction_nonce') || 
            !current_user_can('manage_transactions')) {
            wp_send_json_error('Unauthorized');
        }
        
        $transaction_id = intval($_POST['transaction_id']);
        $new_status = sanitize_text_field($_POST['status']);
        $reason = sanitize_textarea_field($_POST['reason']);
        
        $result = $this->transaction_manager->update_transaction_status($transaction_id, $new_status, $reason);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success('Transaction status updated successfully');
    }
    
    /**
     * Handle verification via AJAX
     */
    public function handle_verification_ajax() {
        // Verify nonce and permissions
        if (!wp_verify_nonce($_POST['nonce'], 'sms_transaction_nonce') || 
            !current_user_can('manage_transactions')) {
            wp_send_json_error('Unauthorized');
        }
        
        $transaction_id = intval($_POST['transaction_id']);
        
        $result = $this->transaction_manager->verify_transaction($transaction_id, true);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success('Transaction verified successfully');
    }
    
    /**
     * Handle send receipt via AJAX
     */
    public function handle_send_receipt_ajax() {
        // Verify nonce and permissions
        if (!wp_verify_nonce($_POST['nonce'], 'sms_transaction_nonce') || 
            !current_user_can('manage_transactions')) {
            wp_send_json_error('Unauthorized');
        }
        
        $transaction_id = intval($_POST['transaction_id']);
        
        $result = $this->transaction_manager->send_receipt($transaction_id, array('email'));
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success('Receipt sent successfully');
    }
    
    /**
     * Handle receipt preview
     */
    public function handle_receipt_preview() {
        // Verify nonce and permissions
        if (!wp_verify_nonce($_GET['nonce'], 'sms_receipt_preview') || 
            !current_user_can('manage_transactions')) {
            wp_die('Unauthorized');
        }
        
        $transaction_id = intval($_GET['transaction_id']);
        
        $receipt_content = $this->transaction_manager->generate_receipt($transaction_id);
        
        if (is_wp_error($receipt_content)) {
            wp_die('Error generating receipt: ' . $receipt_content->get_error_message());
        }
        
        // Output receipt HTML
        echo $receipt_content;
        exit;
    }
    
    /**
     * Handle receipt download
     */
    public function handle_receipt_download() {
        // Verify nonce and permissions
        if (!wp_verify_nonce($_GET['nonce'], 'sms_receipt_download') || 
            !current_user_can('manage_transactions')) {
            wp_die('Unauthorized');
        }
        
        $transaction_id = intval($_GET['transaction_id']);
        
        $receipt_content = $this->transaction_manager->generate_receipt($transaction_id);
        
        if (is_wp_error($receipt_content)) {
            wp_die('Error generating receipt: ' . $receipt_content->get_error_message());
        }
        
        // Get transaction data for filename
        $transaction_data = $this->transaction_manager->get_transaction_data($transaction_id);
        $receipt_number = $transaction_data['fields']['receipt_number'] ?? 'receipt';
        
        // Set headers for download
        header('Content-Type: text/html');
        header('Content-Disposition: attachment; filename="' . $receipt_number . '.html"');
        header('Content-Length: ' . strlen($receipt_content));
        
        echo $receipt_content;
        exit;
    }
    
    /**
     * Handle update pending transactions
     */
    public function handle_update_pending_transactions() {
        // Verify nonce and permissions
        if (!wp_verify_nonce($_POST['nonce'], 'sms_bulk_actions') || 
            !current_user_can('manage_transactions')) {
            wp_send_json_error('Unauthorized');
        }
        
        $status_tracker = SMS_Transaction_Status_Tracker::get_instance();
        $status_tracker->update_pending_transactions();
        
        wp_send_json_success('Pending transactions updated successfully');
    }
    
    /**
     * Handle send pending receipts
     */
    public function handle_send_pending_receipts() {
        // Verify nonce and permissions
        if (!wp_verify_nonce($_POST['nonce'], 'sms_bulk_actions') || 
            !current_user_can('manage_transactions')) {
            wp_send_json_error('Unauthorized');
        }
        
        // Get completed transactions without receipts
        $args = array(
            'post_type' => 'sms_transactions',
            'post_status' => 'publish',
            'posts_per_page' => 50,
            'fields' => 'ids',
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => 'transaction_status',
                    'value' => SMS_Transaction_Manager::STATUS_COMPLETED,
                    'compare' => '='
                ),
                array(
                    'relation' => 'OR',
                    array(
                        'key' => 'receipt_sent',
                        'value' => '',
                        'compare' => '='
                    ),
                    array(
                        'key' => 'receipt_sent',
                        'compare' => 'NOT EXISTS'
                    )
                )
            )
        );
        
        $transactions = get_posts($args);
        $sent_count = 0;
        
        foreach ($transactions as $transaction_id) {
            $result = $this->transaction_manager->send_receipt($transaction_id, array('email'));
            if (!is_wp_error($result)) {
                $sent_count++;
            }
        }
        
        wp_send_json_success("Sent {$sent_count} receipts successfully");
    }
}