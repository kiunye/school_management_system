<?php
/**
 * Admin dashboard template
 *
 * @package    School_Management_System
 * @subpackage School_Management_System/admin/partials
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Prevent duplicate rendering
if (defined('SMS_MAIN_DASHBOARD_RENDERED')) {
    return;
}
define('SMS_MAIN_DASHBOARD_RENDERED', true);
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="sms-admin-container">
        
        <!-- Dashboard Widgets -->
        <div class="sms-dashboard-widgets">
            
            <!-- Students Widget -->
            <div class="sms-widget">
                <h3><?php _e('Students', 'school-management-system'); ?></h3>
                <div class="sms-widget-stat">
                    <?php
                    $student_count = wp_count_posts('cpt_students');
                    echo isset($student_count->publish) ? $student_count->publish : 0;
                    ?>
                </div>
                <p><?php _e('Total active students', 'school-management-system'); ?></p>
                <a href="<?php echo admin_url('admin.php?page=sms-students'); ?>" class="sms-btn">
                    <?php _e('Manage Students', 'school-management-system'); ?>
                </a>
            </div>
            
            <!-- Classes Widget -->
            <div class="sms-widget">
                <h3><?php _e('Classes', 'school-management-system'); ?></h3>
                <div class="sms-widget-stat">
                    <?php
                    $class_count = wp_count_posts('cpt_classes');
                    echo isset($class_count->publish) ? $class_count->publish : 0;
                    ?>
                </div>
                <p><?php _e('Active classes', 'school-management-system'); ?></p>
                <a href="<?php echo admin_url('admin.php?page=sms-classes'); ?>" class="sms-btn">
                    <?php _e('Manage Classes', 'school-management-system'); ?>
                </a>
            </div>
            
            <!-- Fees Widget -->
            <div class="sms-widget">
                <h3><?php _e('Fees & Payments', 'school-management-system'); ?></h3>
                <div class="sms-widget-stat">
                    <?php
                    $pending_payments = 0; // This would be calculated from actual data
                    echo $pending_payments;
                    ?>
                </div>
                <p><?php _e('Pending payments', 'school-management-system'); ?></p>
                <a href="<?php echo admin_url('admin.php?page=sms-fees'); ?>" class="sms-btn">
                    <?php _e('Manage Fees', 'school-management-system'); ?>
                </a>
            </div>
            
            <!-- System Status Widget -->
            <div class="sms-widget">
                <h3><?php _e('System Status', 'school-management-system'); ?></h3>
                <div class="sms-widget-stat" style="color: #28a745;">
                    <?php _e('Active', 'school-management-system'); ?>
                </div>
                <p><?php _e('All systems operational', 'school-management-system'); ?></p>
                <a href="<?php echo admin_url('admin.php?page=sms-settings'); ?>" class="sms-btn">
                    <?php _e('System Settings', 'school-management-system'); ?>
                </a>
            </div>
            
        </div>
        
        <!-- Quick Actions -->
        <div class="sms-widget">
            <h3><?php _e('Quick Actions', 'school-management-system'); ?></h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <a href="<?php echo admin_url('post-new.php?post_type=sms_students'); ?>" class="sms-btn">
                    <?php _e('Add New Student', 'school-management-system'); ?>
                </a>
                <a href="<?php echo admin_url('post-new.php?post_type=sms_classes'); ?>" class="sms-btn sms-btn-secondary">
                    <?php _e('Create Class', 'school-management-system'); ?>
                </a>
                <a href="<?php echo admin_url('post-new.php?post_type=sms_notices'); ?>" class="sms-btn sms-btn-success">
                    <?php _e('Send Notice', 'school-management-system'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=sms-reports'); ?>" class="sms-btn sms-btn-secondary">
                    <?php _e('View Reports', 'school-management-system'); ?>
                </a>
            </div>
        </div>
        
        <!-- Recent Activity -->
        <div class="sms-widget">
            <h3><?php _e('Recent Activity', 'school-management-system'); ?></h3>
            <div id="sms-recent-activity">
                <p><?php _e('Loading recent activity...', 'school-management-system'); ?></p>
            </div>
        </div>
        
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Load recent activity via AJAX
    $.post(ajaxurl, {
        action: 'sms_get_recent_activity',
        nonce: '<?php echo wp_create_nonce('sms_admin_nonce'); ?>'
    }, function(response) {
        if (response.success) {
            $('#sms-recent-activity').html(response.data.html);
        } else {
            $('#sms-recent-activity').html('<p><?php _e('No recent activity found.', 'school-management-system'); ?></p>');
        }
    });
});
</script>