<?php
/**
 * Parent Dashboard Template
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
    $dashboard_data = $dashboard_manager->get_parent_dashboard_data();
}

$children = $dashboard_data['children'];
$children_data = $dashboard_data['children_data'];
$payment_options = $dashboard_data['payment_options'];
$current_user = wp_get_current_user();
?>

<div class="wrap sms-parent-dashboard">
    <h1><?php printf(__('Welcome, %s', 'school-management-system'), $current_user->display_name); ?></h1>
    <p class="dashboard-subtitle"><?php _e('Parent Dashboard', 'school-management-system'); ?></p>
    
    <div class="sms-dashboard-grid">
        <!-- Children Overview -->
        <div class="sms-children-section">
            <h2><?php _e('My Children', 'school-management-system'); ?></h2>
            <?php if (!empty($children)): ?>
                <div class="sms-children-grid">
                    <?php foreach ($children as $child): ?>
                        <?php 
                        $student_id = $child->ID;
                        $child_data = isset($children_data[$student_id]) ? $children_data[$student_id] : array();
                        $student_name = get_the_title($child);
                        $admission_number = get_post_meta($student_id, 'admission_number', true);
                        $class_id = get_post_meta($student_id, 'student_class', true);
                        $class_name = $class_id ? get_the_title($class_id) : __('Not Assigned', 'school-management-system');
                        $grade_level = get_post_meta($student_id, 'grade_level', true);
                        ?>
                        <div class="sms-child-card">
                            <div class="child-header">
                                <div class="child-avatar">
                                    <?php 
                                    $profile_photo = get_post_meta($student_id, 'profile_photo', true);
                                    if ($profile_photo): ?>
                                        <img src="<?php echo esc_url($profile_photo); ?>" alt="<?php echo esc_attr($student_name); ?>">
                                    <?php else: ?>
                                        <span class="default-avatar"><?php echo substr($student_name, 0, 1); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="child-info">
                                    <h3><?php echo esc_html($student_name); ?></h3>
                                    <div class="child-meta">
                                        <span class="admission-number"><?php echo esc_html($admission_number); ?></span>
                                        <span class="class-info"><?php echo esc_html($class_name); ?> - <?php echo esc_html($grade_level); ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="child-stats">
                                <div class="stat-item attendance">
                                    <div class="stat-icon">
                                        <span class="dashicons dashicons-yes-alt"></span>
                                    </div>
                                    <div class="stat-content">
                                        <div class="stat-number"><?php echo isset($child_data['attendance_rate']) ? $child_data['attendance_rate'] : 0; ?>%</div>
                                        <div class="stat-label"><?php _e('Attendance Rate', 'school-management-system'); ?></div>
                                    </div>
                                </div>
                                
                                <div class="stat-item fees">
                                    <div class="stat-icon">
                                        <span class="dashicons dashicons-money-alt"></span>
                                    </div>
                                    <div class="stat-content">
                                        <div class="stat-number">
                                            KES <?php echo isset($child_data['outstanding_fees']) ? number_format($child_data['outstanding_fees'], 2) : '0.00'; ?>
                                        </div>
                                        <div class="stat-label"><?php _e('Outstanding Fees', 'school-management-system'); ?></div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="child-actions">
                                <a href="<?php echo admin_url('admin.php?page=sms-parent-dashboard&view=child&student_id=' . $student_id); ?>" class="button button-primary button-small">
                                    <?php _e('View Details', 'school-management-system'); ?>
                                </a>
                                <?php if (isset($child_data['outstanding_fees']) && $child_data['outstanding_fees'] > 0): ?>
                                    <a href="<?php echo admin_url('admin.php?page=sms-parent-payments&student_id=' . $student_id); ?>" class="button button-secondary button-small">
                                        <?php _e('Pay Fees', 'school-management-system'); ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="sms-empty-state">
                    <div class="empty-icon">
                        <span class="dashicons dashicons-groups"></span>
                    </div>
                    <h3><?php _e('No Children Found', 'school-management-system'); ?></h3>
                    <p><?php _e('No student records are associated with your account. Please contact the school administration.', 'school-management-system'); ?></p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Payment Options -->
        <?php if (!empty($payment_options)): ?>
        <div class="sms-payment-section">
            <h2><?php _e('Payment Options', 'school-management-system'); ?></h2>
            <div class="sms-payment-methods">
                <?php foreach ($payment_options as $method_key => $method): ?>
                    <div class="payment-method-card">
                        <div class="method-icon">
                            <img src="<?php echo SMS_PLUGIN_URL . 'admin/images/' . $method['icon']; ?>" alt="<?php echo esc_attr($method['name']); ?>">
                        </div>
                        <div class="method-content">
                            <h4><?php echo esc_html($method['name']); ?></h4>
                            <p><?php echo esc_html($method['description']); ?></p>
                        </div>
                        <div class="method-action">
                            <a href="<?php echo admin_url('admin.php?page=sms-parent-payments&method=' . $method_key); ?>" class="button">
                                <?php _e('Pay Now', 'school-management-system'); ?>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Recent Attendance -->
        <div class="sms-attendance-section">
            <h2><?php _e('Recent Attendance', 'school-management-system'); ?></h2>
            <?php if (!empty($children)): ?>
                <div class="attendance-tabs">
                    <?php foreach ($children as $index => $child): ?>
                        <button class="attendance-tab <?php echo $index === 0 ? 'active' : ''; ?>" data-student-id="<?php echo $child->ID; ?>">
                            <?php echo esc_html(get_the_title($child)); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
                
                <?php foreach ($children as $index => $child): ?>
                    <?php 
                    $student_id = $child->ID;
                    $child_data = isset($children_data[$student_id]) ? $children_data[$student_id] : array();
                    $recent_attendance = isset($child_data['recent_attendance']) ? $child_data['recent_attendance'] : array();
                    ?>
                    <div class="attendance-content <?php echo $index === 0 ? 'active' : ''; ?>" data-student-id="<?php echo $student_id; ?>">
                        <?php if (!empty($recent_attendance)): ?>
                            <div class="attendance-calendar">
                                <?php foreach ($recent_attendance as $attendance): ?>
                                    <div class="attendance-day <?php echo $attendance['status']; ?>">
                                        <div class="day-date"><?php echo date('M j', strtotime($attendance['date'])); ?></div>
                                        <div class="day-status"><?php echo ucfirst($attendance['status']); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p><?php _e('No recent attendance data available.', 'school-management-system'); ?></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Recent Payments -->
        <div class="sms-payments-section">
            <h2><?php _e('Recent Payments', 'school-management-system'); ?></h2>
            <div class="payments-container">
                <?php 
                $has_payments = false;
                foreach ($children as $child):
                    $student_id = $child->ID;
                    $child_data = isset($children_data[$student_id]) ? $children_data[$student_id] : array();
                    $recent_payments = isset($child_data['recent_payments']) ? $child_data['recent_payments'] : array();
                    
                    if (!empty($recent_payments)):
                        $has_payments = true;
                ?>
                    <div class="student-payments">
                        <h4><?php echo esc_html(get_the_title($child)); ?></h4>
                        <div class="payments-list">
                            <?php foreach ($recent_payments as $payment): ?>
                                <div class="payment-item">
                                    <div class="payment-info">
                                        <div class="payment-amount">KES <?php echo number_format($payment['amount'], 2); ?></div>
                                        <div class="payment-date"><?php echo date_i18n(get_option('date_format'), strtotime($payment['date'])); ?></div>
                                    </div>
                                    <div class="payment-method"><?php echo esc_html($payment['method']); ?></div>
                                    <div class="payment-status <?php echo $payment['status']; ?>">
                                        <?php echo ucfirst($payment['status']); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php 
                    endif;
                endforeach;
                
                if (!$has_payments): ?>
                    <div class="sms-empty-state">
                        <div class="empty-icon">
                            <span class="dashicons dashicons-money-alt"></span>
                        </div>
                        <p><?php _e('No recent payments found.', 'school-management-system'); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- School Notices -->
        <div class="sms-notices-section">
            <h2><?php _e('School Notices', 'school-management-system'); ?></h2>
            <div class="notices-container">
                <div id="sms-parent-notices">
                    <div class="loading-spinner">
                        <span class="dashicons dashicons-update spin"></span>
                        <?php _e('Loading notices...', 'school-management-system'); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="sms-parent-actions-section">
            <h2><?php _e('Quick Actions', 'school-management-system'); ?></h2>
            <div class="sms-parent-actions-grid">
                <a href="<?php echo admin_url('admin.php?page=sms-parent-payments'); ?>" class="sms-action-btn primary">
                    <span class="dashicons dashicons-money-alt"></span>
                    <?php _e('Make Payment', 'school-management-system'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=sms-parent-attendance'); ?>" class="sms-action-btn secondary">
                    <span class="dashicons dashicons-calendar-alt"></span>
                    <?php _e('View Attendance', 'school-management-system'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=sms-parent-notices'); ?>" class="sms-action-btn info">
                    <span class="dashicons dashicons-megaphone"></span>
                    <?php _e('View All Notices', 'school-management-system'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=sms-parent-profile'); ?>" class="sms-action-btn success">
                    <span class="dashicons dashicons-admin-users"></span>
                    <?php _e('Update Profile', 'school-management-system'); ?>
                </a>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Load notices
    loadParentNotices();
    
    // Handle attendance tabs
    $('.attendance-tab').on('click', function() {
        var studentId = $(this).data('student-id');
        
        // Update active tab
        $('.attendance-tab').removeClass('active');
        $(this).addClass('active');
        
        // Update active content
        $('.attendance-content').removeClass('active');
        $('.attendance-content[data-student-id="' + studentId + '"]').addClass('active');
    });
    
    // Auto-refresh notices every 15 minutes
    setInterval(function() {
        loadParentNotices();
    }, 900000);
    
    function loadParentNotices() {
        $.post(ajaxurl, {
            action: 'sms_get_parent_notices',
            nonce: '<?php echo wp_create_nonce('sms_admin_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                $('#sms-parent-notices').html(response.data.html);
            } else {
                $('#sms-parent-notices').html('<p><?php _e('No notices available at this time.', 'school-management-system'); ?></p>');
            }
        });
    }
    
    // Payment method selection
    $('.payment-method-card').on('click', function() {
        var methodUrl = $(this).find('a').attr('href');
        if (methodUrl) {
            window.location.href = methodUrl;
        }
    });
    
    // Highlight outstanding fees
    $('.stat-item.fees').each(function() {
        var amount = parseFloat($(this).find('.stat-number').text().replace('KES ', '').replace(',', ''));
        if (amount > 0) {
            $(this).addClass('has-outstanding');
        }
    });
});
</script>