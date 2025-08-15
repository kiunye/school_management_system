<?php
/**
 * Students Management Page
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
    <h1><?php _e('Students Management', 'school-management-system'); ?></h1>
    
    <div class="sms-admin-content">
        <div class="sms-card">
            <h2><?php _e('Manage Students', 'school-management-system'); ?></h2>
            <p><?php _e('This page will contain the students management interface. For now, you can manage students through the WordPress admin menu under "Students".', 'school-management-system'); ?></p>
            
            <div class="sms-quick-links">
                <a href="<?php echo admin_url('edit.php?post_type=sms_students'); ?>" class="button button-primary">
                    <?php _e('View All Students', 'school-management-system'); ?>
                </a>
                <a href="<?php echo admin_url('post-new.php?post_type=sms_students'); ?>" class="button">
                    <?php _e('Add New Student', 'school-management-system'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=sms-student-admission'); ?>" class="button">
                    <?php _e('Student Admission', 'school-management-system'); ?>
                </a>
            </div>
        </div>
        
        <div class="sms-card">
            <h3><?php _e('Quick Stats', 'school-management-system'); ?></h3>
            <?php
            // Get students statistics
            $total_students = wp_count_posts('sms_students');
            $active_students = get_posts(array(
                'post_type' => 'sms_students',
                'posts_per_page' => -1,
                'meta_query' => array(
                    array(
                        'key' => 'student_status',
                        'value' => 'active',
                        'compare' => '='
                    )
                ),
                'fields' => 'ids'
            ));
            
            $pending_admissions = get_posts(array(
                'post_type' => 'sms_students',
                'posts_per_page' => -1,
                'meta_query' => array(
                    array(
                        'key' => 'student_status',
                        'value' => 'pending',
                        'compare' => '='
                    )
                ),
                'fields' => 'ids'
            ));
            ?>
            <div class="sms-stats-grid">
                <div class="stat-item">
                    <div class="stat-number"><?php echo $total_students->publish; ?></div>
                    <div class="stat-label"><?php _e('Total Students', 'school-management-system'); ?></div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo count($active_students); ?></div>
                    <div class="stat-label"><?php _e('Active Students', 'school-management-system'); ?></div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo count($pending_admissions); ?></div>
                    <div class="stat-label"><?php _e('Pending Admissions', 'school-management-system'); ?></div>
                </div>
            </div>
        </div>
        
        <div class="sms-card">
            <h3><?php _e('Recent Activities', 'school-management-system'); ?></h3>
            <?php
            // Get recent student activities
            $recent_students = get_posts(array(
                'post_type' => 'sms_students',
                'posts_per_page' => 5,
                'orderby' => 'date',
                'order' => 'DESC'
            ));
            
            if (!empty($recent_students)): ?>
                <ul class="sms-recent-list">
                    <?php foreach ($recent_students as $student): ?>
                        <li>
                            <strong><?php echo get_field('full_name', $student->ID) ?: $student->post_title; ?></strong>
                            <span class="admission-number">#<?php echo get_field('admission_number', $student->ID); ?></span>
                            <span class="date"><?php echo get_the_date('M j, Y', $student); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p><?php _e('No recent student activities.', 'school-management-system'); ?></p>
            <?php endif; ?>
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

.sms-recent-list {
    list-style: none;
    padding: 0;
    margin: 15px 0 0 0;
}

.sms-recent-list li {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #eee;
}

.sms-recent-list li:last-child {
    border-bottom: none;
}

.admission-number {
    background: #f0f0f0;
    padding: 2px 6px;
    border-radius: 3px;
    font-family: monospace;
    font-size: 12px;
}

.date {
    color: #666;
    font-size: 12px;
}
</style>