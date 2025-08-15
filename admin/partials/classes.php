<?php
/**
 * Classes Management Page
 *
 * @package SchoolManagementSystem
 * @subpackage Admin/Partials
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

?>
<div class="wrap">
    <h1><?php _e('Classes Management', 'school-management-system'); ?></h1>
    
    <div class="sms-admin-content">
        <div class="sms-card">
            <h2><?php _e('Manage Classes', 'school-management-system'); ?></h2>
            <p><?php _e('This page will contain the classes management interface. For now, you can manage classes through the WordPress admin menu under "Classes".', 'school-management-system'); ?></p>
            
            <div class="sms-quick-links">
                <a href="<?php echo admin_url('edit.php?post_type=sms_classes'); ?>" class="button button-primary">
                    <?php _e('View All Classes', 'school-management-system'); ?>
                </a>
                <a href="<?php echo admin_url('post-new.php?post_type=sms_classes'); ?>" class="button">
                    <?php _e('Add New Class', 'school-management-system'); ?>
                </a>
            </div>
        </div>
        
        <div class="sms-card">
            <h3><?php _e('Quick Stats', 'school-management-system'); ?></h3>
            <?php
            // Get classes statistics
            $total_classes = wp_count_posts('sms_classes');
            $active_classes = get_posts(array(
                'post_type' => 'sms_classes',
                'posts_per_page' => -1,
                'meta_query' => array(
                    array(
                        'key' => 'class_status',
                        'value' => 'active',
                        'compare' => '='
                    )
                ),
                'fields' => 'ids'
            ));
            ?>
            <div class="sms-stats-grid">
                <div class="stat-item">
                    <div class="stat-number"><?php echo $total_classes->publish; ?></div>
                    <div class="stat-label"><?php _e('Total Classes', 'school-management-system'); ?></div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo count($active_classes); ?></div>
                    <div class="stat-label"><?php _e('Active Classes', 'school-management-system'); ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.sms-admin-content {
    display: grid;
    gap: 20px;
    margin-top: 20px;
}

.sms-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    box-shadow: 0 1px 1px rgba(0, 0, 0, 0.04);
}

.sms-card h2, .sms-card h3 {
    margin-top: 0;
}

.sms-quick-links {
    margin-top: 15px;
}

.sms-quick-links .button {
    margin-right: 10px;
}

.sms-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 20px;
    margin-top: 15px;
}

.stat-item {
    text-align: center;
    padding: 15px;
    background: #f9f9f9;
    border-radius: 4px;
}

.stat-number {
    font-size: 32px;
    font-weight: bold;
    color: #0073aa;
}

.stat-label {
    font-size: 14px;
    color: #666;
    margin-top: 5px;
}
</style>