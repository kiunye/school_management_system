<?php
/**
 * Reports Management Page
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
    <h1><?php _e('Reports & Analytics', 'school-management-system'); ?></h1>
    
    <div class="sms-admin-content">
        <div class="sms-card">
            <h2><?php _e('Available Reports', 'school-management-system'); ?></h2>
            <p><?php _e('Generate comprehensive reports for students, attendance, fees, and more.', 'school-management-system'); ?></p>
            
            <div class="reports-grid">
                <div class="report-item">
                    <div class="report-icon">👥</div>
                    <div class="report-info">
                        <h3><?php _e('Student Reports', 'school-management-system'); ?></h3>
                        <p><?php _e('Enrollment, demographics, and academic performance reports', 'school-management-system'); ?></p>
                        <a href="<?php echo admin_url('edit.php?post_type=sms_students'); ?>" class="button">
                            <?php _e('View Students', 'school-management-system'); ?>
                        </a>
                    </div>
                </div>
                
                <div class="report-item">
                    <div class="report-icon">📊</div>
                    <div class="report-info">
                        <h3><?php _e('Attendance Reports', 'school-management-system'); ?></h3>
                        <p><?php _e('Daily, weekly, and monthly attendance analytics', 'school-management-system'); ?></p>
                        <a href="<?php echo admin_url('admin.php?page=sms-attendance-reports'); ?>" class="button button-primary">
                            <?php _e('View Reports', 'school-management-system'); ?>
                        </a>
                    </div>
                </div>
                
                <div class="report-item">
                    <div class="report-icon">💰</div>
                    <div class="report-info">
                        <h3><?php _e('Financial Reports', 'school-management-system'); ?></h3>
                        <p><?php _e('Fee collection, payment tracking, and financial summaries', 'school-management-system'); ?></p>
                        <a href="<?php echo admin_url('edit.php?post_type=sms_transactions'); ?>" class="button">
                            <?php _e('View Transactions', 'school-management-system'); ?>
                        </a>
                    </div>
                </div>
                
                <div class="report-item">
                    <div class="report-icon">🏫</div>
                    <div class="report-info">
                        <h3><?php _e('Class Reports', 'school-management-system'); ?></h3>
                        <p><?php _e('Class enrollment, capacity, and performance metrics', 'school-management-system'); ?></p>
                        <a href="<?php echo admin_url('edit.php?post_type=sms_classes'); ?>" class="button">
                            <?php _e('View Classes', 'school-management-system'); ?>
                        </a>
                    </div>
                </div>
                
                <div class="report-item">
                    <div class="report-icon">🚌</div>
                    <div class="report-info">
                        <h3><?php _e('Transport Reports', 'school-management-system'); ?></h3>
                        <p><?php _e('Route utilization and transport management reports', 'school-management-system'); ?></p>
                        <a href="<?php echo admin_url('edit.php?post_type=sms_transport_routes'); ?>" class="button">
                            <?php _e('View Routes', 'school-management-system'); ?>
                        </a>
                    </div>
                </div>
                
                <div class="report-item">
                    <div class="report-icon">📢</div>
                    <div class="report-info">
                        <h3><?php _e('Communication Reports', 'school-management-system'); ?></h3>
                        <p><?php _e('SMS delivery, email notifications, and communication logs', 'school-management-system'); ?></p>
                        <a href="<?php echo admin_url('edit.php?post_type=sms_notices'); ?>" class="button">
                            <?php _e('View Notices', 'school-management-system'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="sms-card">
            <h3><?php _e('Quick Analytics', 'school-management-system'); ?></h3>
            <?php
            // Get quick statistics
            $total_students = wp_count_posts('sms_students');
            $total_classes = wp_count_posts('sms_classes');
            $total_attendance_records = wp_count_posts('sms_attendance');
            $total_transactions = wp_count_posts('sms_transactions');
            
            // Get today's attendance
            $today_attendance = get_posts(array(
                'post_type' => 'sms_attendance',
                'posts_per_page' => -1,
                'meta_query' => array(
                    array(
                        'key' => 'attendance_date',
                        'value' => date('Y-m-d'),
                        'compare' => '='
                    )
                ),
                'fields' => 'ids'
            ));
            ?>
            <div class="analytics-grid">
                <div class="analytics-item">
                    <div class="analytics-number"><?php echo $total_students->publish; ?></div>
                    <div class="analytics-label"><?php _e('Total Students', 'school-management-system'); ?></div>
                </div>
                <div class="analytics-item">
                    <div class="analytics-number"><?php echo $total_classes->publish; ?></div>
                    <div class="analytics-label"><?php _e('Active Classes', 'school-management-system'); ?></div>
                </div>
                <div class="analytics-item">
                    <div class="analytics-number"><?php echo count($today_attendance); ?></div>
                    <div class="analytics-label"><?php _e('Today\'s Attendance Records', 'school-management-system'); ?></div>
                </div>
                <div class="analytics-item">
                    <div class="analytics-number"><?php echo $total_transactions->publish; ?></div>
                    <div class="analytics-label"><?php _e('Total Transactions', 'school-management-system'); ?></div>
                </div>
            </div>
        </div>
        
        <div class="sms-card">
            <h3><?php _e('Export Options', 'school-management-system'); ?></h3>
            <p><?php _e('Export data in various formats for external analysis.', 'school-management-system'); ?></p>
            
            <div class="export-options">
                <button class="button" onclick="alert('Export functionality will be implemented in future updates.')">
                    📊 <?php _e('Export to Excel', 'school-management-system'); ?>
                </button>
                <button class="button" onclick="alert('Export functionality will be implemented in future updates.')">
                    📄 <?php _e('Export to PDF', 'school-management-system'); ?>
                </button>
                <button class="button" onclick="alert('Export functionality will be implemented in future updates.')">
                    📋 <?php _e('Export to CSV', 'school-management-system'); ?>
                </button>
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

.reports-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.report-item {
    display: flex;
    align-items: flex-start;
    padding: 20px;
    border: 1px solid #ddd;
    border-radius: 8px;
    background: #fafafa;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.report-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

.report-icon {
    font-size: 48px;
    margin-right: 15px;
    flex-shrink: 0;
}

.report-info {
    flex: 1;
}

.report-info h3 {
    margin: 0 0 10px 0;
    font-size: 18px;
    color: #23282d;
}

.report-info p {
    margin: 0 0 15px 0;
    color: #666;
    font-size: 14px;
    line-height: 1.5;
}

.analytics-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 20px;
    margin-top: 15px;
}

.analytics-item {
    text-align: center;
    padding: 20px;
    background: #f9f9f9;
    border-radius: 8px;
    border: 1px solid #e5e5e5;
}

.analytics-number {
    font-size: 36px;
    font-weight: bold;
    color: #0073aa;
    margin-bottom: 5px;
}

.analytics-label {
    font-size: 14px;
    color: #666;
    line-height: 1.3;
}

.export-options {
    display: flex;
    gap: 10px;
    margin-top: 15px;
    flex-wrap: wrap;
}

.export-options .button {
    display: flex;
    align-items: center;
    gap: 5px;
}

@media (max-width: 768px) {
    .reports-grid {
        grid-template-columns: 1fr;
    }
    
    .report-item {
        flex-direction: column;
        text-align: center;
    }
    
    .report-icon {
        margin-right: 0;
        margin-bottom: 10px;
    }
    
    .analytics-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .export-options {
        flex-direction: column;
    }
}
</style>