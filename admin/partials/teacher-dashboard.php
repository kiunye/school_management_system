<?php
/**
 * Teacher Dashboard Template
 *
 * @package    School_Management_System
 * @subpackage School_Management_System/admin/partials
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Ensure dashboard data is available
if (!isset($dashboard_data)) {
    $dashboard_manager = new SMS_Dashboard_Manager();
    $dashboard_data = $dashboard_manager->get_teacher_dashboard_data();
}

$assigned_classes = $dashboard_data['assigned_classes'];
$class_stats = $dashboard_data['class_stats'];
$todays_schedule = $dashboard_data['todays_schedule'];
$current_user = wp_get_current_user();
?>

<div class="wrap sms-teacher-dashboard">
    <h1><?php printf(__('Welcome, %s', 'school-management-system'), $current_user->display_name); ?></h1>
    <p class="dashboard-subtitle"><?php _e('Teacher Dashboard', 'school-management-system'); ?></p>
    
    <div class="sms-dashboard-grid">
        <!-- My Classes Overview -->
        <div class="sms-classes-section">
            <h2><?php _e('My Classes', 'school-management-system'); ?></h2>
            <?php if (!empty($assigned_classes)): ?>
                <div class="sms-classes-grid">
                    <?php foreach ($assigned_classes as $class): ?>
                        <?php 
                        $class_id = $class->ID;
                        $stats = isset($class_stats[$class_id]) ? $class_stats[$class_id] : array();
                        $class_name = get_the_title($class);
                        $grade_level = get_post_meta($class_id, 'grade_level', true);
                        ?>
                        <div class="sms-class-card">
                            <div class="class-header">
                                <h3><?php echo esc_html($class_name); ?></h3>
                                <span class="class-grade"><?php echo esc_html($grade_level); ?></span>
                            </div>
                            <div class="class-stats">
                                <div class="stat-item">
                                    <div class="stat-number"><?php echo isset($stats['total_students']) ? $stats['total_students'] : 0; ?></div>
                                    <div class="stat-label"><?php _e('Students', 'school-management-system'); ?></div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-number attendance-rate">
                                        <?php echo isset($stats['attendance_rate']) ? $stats['attendance_rate'] : 0; ?>%
                                    </div>
                                    <div class="stat-label"><?php _e('Attendance Rate', 'school-management-system'); ?></div>
                                </div>
                            </div>
                            <div class="class-today">
                                <div class="today-attendance">
                                    <span class="present"><?php echo isset($stats['present_today']) ? $stats['present_today'] : 0; ?> <?php _e('Present', 'school-management-system'); ?></span>
                                    <span class="absent"><?php echo isset($stats['absent_today']) ? $stats['absent_today'] : 0; ?> <?php _e('Absent', 'school-management-system'); ?></span>
                                </div>
                            </div>
                            <div class="class-actions">
                                <a href="<?php echo admin_url('admin.php?page=sms-attendance-manager&class_id=' . $class_id); ?>" class="button button-primary button-small">
                                    <?php _e('Mark Attendance', 'school-management-system'); ?>
                                </a>
                                <a href="<?php echo admin_url('edit.php?post_type=sms_students&class_id=' . $class_id); ?>" class="button button-small">
                                    <?php _e('View Students', 'school-management-system'); ?>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="sms-empty-state">
                    <div class="empty-icon">
                        <span class="dashicons dashicons-building"></span>
                    </div>
                    <h3><?php _e('No Classes Assigned', 'school-management-system'); ?></h3>
                    <p><?php _e('You have not been assigned to any classes yet. Please contact the administrator.', 'school-management-system'); ?></p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Today's Schedule -->
        <div class="sms-schedule-section">
            <h2><?php _e('Today\'s Schedule', 'school-management-system'); ?></h2>
            <div class="schedule-date"><?php echo date_i18n(get_option('date_format')); ?></div>
            
            <?php if (!empty($todays_schedule)): ?>
                <div class="sms-schedule-list">
                    <?php foreach ($todays_schedule as $schedule_item): ?>
                        <?php 
                        $schedule_data = json_decode($schedule_item->schedule_data, true);
                        $current_time = current_time('H:i');
                        ?>
                        <div class="schedule-item <?php echo ($current_time >= $schedule_data['start_time'] && $current_time <= $schedule_data['end_time']) ? 'current' : ''; ?>">
                            <div class="schedule-time">
                                <?php echo esc_html($schedule_data['start_time']); ?> - <?php echo esc_html($schedule_data['end_time']); ?>
                            </div>
                            <div class="schedule-content">
                                <div class="schedule-subject"><?php echo esc_html($schedule_data['subject']); ?></div>
                                <div class="schedule-class"><?php echo get_the_title($schedule_data['class_id']); ?></div>
                            </div>
                            <div class="schedule-actions">
                                <a href="<?php echo admin_url('admin.php?page=sms-attendance-manager&class_id=' . $schedule_data['class_id']); ?>" class="button button-small">
                                    <?php _e('Attendance', 'school-management-system'); ?>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="sms-empty-schedule">
                    <div class="empty-icon">
                        <span class="dashicons dashicons-calendar-alt"></span>
                    </div>
                    <p><?php _e('No classes scheduled for today.', 'school-management-system'); ?></p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Quick Actions -->
        <div class="sms-teacher-actions-section">
            <h2><?php _e('Quick Actions', 'school-management-system'); ?></h2>
            <div class="sms-teacher-actions-grid">
                <a href="<?php echo admin_url('admin.php?page=sms-attendance-manager'); ?>" class="sms-action-btn primary">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <?php _e('Mark Attendance', 'school-management-system'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=sms-timetable-builder'); ?>" class="sms-action-btn secondary">
                    <span class="dashicons dashicons-calendar-alt"></span>
                    <?php _e('Manage Timetable', 'school-management-system'); ?>
                </a>
                <a href="<?php echo admin_url('post-new.php?post_type=sms_notices'); ?>" class="sms-action-btn success">
                    <span class="dashicons dashicons-megaphone"></span>
                    <?php _e('Create Notice', 'school-management-system'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=sms-attendance-reports'); ?>" class="sms-action-btn info">
                    <span class="dashicons dashicons-chart-bar"></span>
                    <?php _e('Attendance Reports', 'school-management-system'); ?>
                </a>
            </div>
        </div>

        <!-- Recent Attendance Activity -->
        <div class="sms-recent-activity-section">
            <h2><?php _e('Recent Attendance Activity', 'school-management-system'); ?></h2>
            <div class="sms-activity-container">
                <div id="sms-teacher-recent-activity">
                    <div class="loading-spinner">
                        <span class="dashicons dashicons-update spin"></span>
                        <?php _e('Loading recent activity...', 'school-management-system'); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Attendance Summary -->
        <div class="sms-attendance-summary-section">
            <h2><?php _e('Attendance Summary', 'school-management-system'); ?></h2>
            <div class="attendance-summary-grid">
                <?php foreach ($assigned_classes as $class): ?>
                    <?php 
                    $class_id = $class->ID;
                    $stats = isset($class_stats[$class_id]) ? $class_stats[$class_id] : array();
                    $attendance_rate = isset($stats['attendance_rate']) ? $stats['attendance_rate'] : 0;
                    ?>
                    <div class="attendance-summary-card">
                        <div class="summary-header">
                            <h4><?php echo get_the_title($class); ?></h4>
                            <div class="attendance-percentage <?php echo $attendance_rate >= 90 ? 'excellent' : ($attendance_rate >= 80 ? 'good' : 'needs-attention'); ?>">
                                <?php echo $attendance_rate; ?>%
                            </div>
                        </div>
                        <div class="summary-details">
                            <div class="detail-item">
                                <span class="detail-label"><?php _e('This Month:', 'school-management-system'); ?></span>
                                <span class="detail-value"><?php echo $attendance_rate; ?>%</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label"><?php _e('Today:', 'school-management-system'); ?></span>
                                <span class="detail-value">
                                    <?php echo isset($stats['present_today']) ? $stats['present_today'] : 0; ?>/<?php echo isset($stats['total_students']) ? $stats['total_students'] : 0; ?>
                                </span>
                            </div>
                        </div>
                        <div class="summary-actions">
                            <a href="<?php echo admin_url('admin.php?page=sms-attendance-reports&class_id=' . $class_id); ?>" class="button button-small">
                                <?php _e('View Report', 'school-management-system'); ?>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Notifications & Alerts -->
        <div class="sms-notifications-section">
            <h2><?php _e('Notifications & Alerts', 'school-management-system'); ?></h2>
            <div class="sms-notifications-container">
                <div id="sms-teacher-notifications">
                    <!-- Notifications will be loaded via AJAX -->
                </div>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Load recent activity
    loadTeacherActivity();
    
    // Load notifications
    loadTeacherNotifications();
    
    // Auto-refresh every 10 minutes
    setInterval(function() {
        loadTeacherActivity();
        loadTeacherNotifications();
    }, 600000);
    
    function loadTeacherActivity() {
        $.post(ajaxurl, {
            action: 'sms_get_teacher_recent_activity',
            nonce: '<?php echo wp_create_nonce('sms_admin_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                $('#sms-teacher-recent-activity').html(response.data.html);
            } else {
                $('#sms-teacher-recent-activity').html('<p><?php _e('No recent activity found.', 'school-management-system'); ?></p>');
            }
        });
    }
    
    function loadTeacherNotifications() {
        $.post(ajaxurl, {
            action: 'sms_get_teacher_notifications',
            nonce: '<?php echo wp_create_nonce('sms_admin_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                $('#sms-teacher-notifications').html(response.data.html);
            } else {
                $('#sms-teacher-notifications').html('<p><?php _e('No notifications at this time.', 'school-management-system'); ?></p>');
            }
        });
    }
    
    // Highlight current time slot
    function highlightCurrentTimeSlot() {
        var currentTime = new Date();
        var currentHour = currentTime.getHours();
        var currentMinute = currentTime.getMinutes();
        var currentTimeString = String(currentHour).padStart(2, '0') + ':' + String(currentMinute).padStart(2, '0');
        
        $('.schedule-item').each(function() {
            var startTime = $(this).find('.schedule-time').text().split(' - ')[0];
            var endTime = $(this).find('.schedule-time').text().split(' - ')[1];
            
            if (currentTimeString >= startTime && currentTimeString <= endTime) {
                $(this).addClass('current');
            } else {
                $(this).removeClass('current');
            }
        });
    }
    
    // Update current time slot every minute
    setInterval(highlightCurrentTimeSlot, 60000);
    highlightCurrentTimeSlot();
});
</script>