<?php
/**
 * Transport Dashboard Admin Partial
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
    <h1><?php _e('Transport Management Dashboard', 'school-management-system'); ?></h1>

    <!-- Dashboard Statistics -->
    <div class="sms-dashboard-stats">
        <div class="sms-stats-grid">
            <div class="sms-stat-card">
                <div class="sms-stat-icon">
                    <span class="dashicons dashicons-bus"></span>
                </div>
                <div class="sms-stat-content">
                    <h3><?php echo esc_html($stats['total_routes']); ?></h3>
                    <p><?php _e('Total Routes', 'school-management-system'); ?></p>
                </div>
            </div>

            <div class="sms-stat-card">
                <div class="sms-stat-icon active">
                    <span class="dashicons dashicons-yes-alt"></span>
                </div>
                <div class="sms-stat-content">
                    <h3><?php echo esc_html($stats['active_routes']); ?></h3>
                    <p><?php _e('Active Routes', 'school-management-system'); ?></p>
                </div>
            </div>

            <div class="sms-stat-card">
                <div class="sms-stat-icon">
                    <span class="dashicons dashicons-groups"></span>
                </div>
                <div class="sms-stat-content">
                    <h3><?php echo esc_html($stats['total_enrollment']); ?></h3>
                    <p><?php _e('Students Enrolled', 'school-management-system'); ?></p>
                </div>
            </div>

            <div class="sms-stat-card">
                <div class="sms-stat-icon">
                    <span class="dashicons dashicons-chart-bar"></span>
                </div>
                <div class="sms-stat-content">
                    <h3><?php echo esc_html($stats['capacity_utilization']); ?>%</h3>
                    <p><?php _e('Capacity Utilization', 'school-management-system'); ?></p>
                </div>
            </div>

            <?php if ($stats['vehicles_with_issues'] > 0): ?>
            <div class="sms-stat-card warning">
                <div class="sms-stat-icon">
                    <span class="dashicons dashicons-warning"></span>
                </div>
                <div class="sms-stat-content">
                    <h3><?php echo esc_html($stats['vehicles_with_issues']); ?></h3>
                    <p><?php _e('Vehicles Need Attention', 'school-management-system'); ?></p>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($stats['drivers_with_issues'] > 0): ?>
            <div class="sms-stat-card warning">
                <div class="sms-stat-icon">
                    <span class="dashicons dashicons-admin-users"></span>
                </div>
                <div class="sms-stat-content">
                    <h3><?php echo esc_html($stats['drivers_with_issues']); ?></h3>
                    <p><?php _e('License Renewals Due', 'school-management-system'); ?></p>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="sms-dashboard-content">
        <div class="sms-dashboard-left">
            <!-- Recent Alerts -->
            <?php if (!empty($recent_alerts)): ?>
            <div class="sms-dashboard-section">
                <h2><?php _e('Recent Alerts', 'school-management-system'); ?></h2>
                <div class="sms-alerts-list">
                    <?php foreach (array_slice($recent_alerts, 0, 5) as $alert): ?>
                    <div class="sms-alert-item <?php echo esc_attr($alert['priority']); ?>">
                        <div class="sms-alert-icon">
                            <?php if ($alert['type'] === 'insurance_expiry' || $alert['type'] === 'inspection_expiry'): ?>
                                <span class="dashicons dashicons-car"></span>
                            <?php elseif ($alert['type'] === 'license_expiry'): ?>
                                <span class="dashicons dashicons-admin-users"></span>
                            <?php else: ?>
                                <span class="dashicons dashicons-warning"></span>
                            <?php endif; ?>
                        </div>
                        <div class="sms-alert-content">
                            <h4><?php echo esc_html($alert['route_name']); ?></h4>
                            <p>
                                <?php
                                switch ($alert['type']) {
                                    case 'insurance_expiry':
                                        printf(
                                            __('Vehicle insurance expires in %d days', 'school-management-system'),
                                            $alert['days_remaining']
                                        );
                                        break;
                                    case 'inspection_expiry':
                                        printf(
                                            __('Vehicle inspection expires in %d days', 'school-management-system'),
                                            $alert['days_remaining']
                                        );
                                        break;
                                    case 'license_expiry':
                                        printf(
                                            __('Driver license expires in %d days', 'school-management-system'),
                                            $alert['days_remaining']
                                        );
                                        break;
                                    case 'vehicle_condition':
                                        printf(
                                            __('Vehicle condition: %s', 'school-management-system'),
                                            ucfirst($alert['condition'])
                                        );
                                        break;
                                }
                                ?>
                            </p>
                        </div>
                        <div class="sms-alert-actions">
                            <a href="<?php echo admin_url('admin.php?page=sms-maintenance-alerts'); ?>" class="button button-small">
                                <?php _e('View Details', 'school-management-system'); ?>
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if (count($recent_alerts) > 5): ?>
                <div class="sms-alerts-footer">
                    <a href="<?php echo admin_url('admin.php?page=sms-maintenance-alerts'); ?>" class="button">
                        <?php printf(__('View All %d Alerts', 'school-management-system'), count($recent_alerts)); ?>
                    </a>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Quick Actions -->
            <div class="sms-dashboard-section">
                <h2><?php _e('Quick Actions', 'school-management-system'); ?></h2>
                <div class="sms-quick-actions">
                    <a href="<?php echo admin_url('post-new.php?post_type=sms_transport_routes'); ?>" class="sms-quick-action">
                        <span class="dashicons dashicons-plus-alt"></span>
                        <?php _e('Add New Route', 'school-management-system'); ?>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=sms-route-management'); ?>" class="sms-quick-action">
                        <span class="dashicons dashicons-edit"></span>
                        <?php _e('Manage Routes', 'school-management-system'); ?>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=sms-vehicle-management'); ?>" class="sms-quick-action">
                        <span class="dashicons dashicons-car"></span>
                        <?php _e('Manage Vehicles', 'school-management-system'); ?>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=sms-student-route-assignment'); ?>" class="sms-quick-action">
                        <span class="dashicons dashicons-groups"></span>
                        <?php _e('Assign Students', 'school-management-system'); ?>
                    </a>
                </div>
            </div>
        </div>

        <div class="sms-dashboard-right">
            <!-- Active Routes Summary -->
            <div class="sms-dashboard-section">
                <h2><?php _e('Active Routes', 'school-management-system'); ?></h2>
                <?php if (!empty($active_routes)): ?>
                <div class="sms-routes-summary">
                    <?php foreach ($active_routes as $route): ?>
                    <div class="sms-route-summary-item">
                        <div class="sms-route-header">
                            <h4><?php echo esc_html($route['name']); ?></h4>
                            <span class="sms-route-code"><?php echo esc_html($route['code']); ?></span>
                        </div>
                        <div class="sms-route-stats">
                            <div class="sms-route-stat">
                                <span class="label"><?php _e('Capacity:', 'school-management-system'); ?></span>
                                <span class="value"><?php echo esc_html($route['capacity']); ?></span>
                            </div>
                            <div class="sms-route-stat">
                                <span class="label"><?php _e('Enrolled:', 'school-management-system'); ?></span>
                                <span class="value"><?php echo esc_html($route['enrollment']); ?></span>
                            </div>
                            <div class="sms-route-stat">
                                <span class="label"><?php _e('Available:', 'school-management-system'); ?></span>
                                <span class="value"><?php echo esc_html($route['available']); ?></span>
                            </div>
                        </div>
                        <div class="sms-route-progress">
                            <?php 
                            $utilization = $route['capacity'] > 0 ? ($route['enrollment'] / $route['capacity']) * 100 : 0;
                            $progress_class = $utilization >= 90 ? 'high' : ($utilization >= 70 ? 'medium' : 'low');
                            ?>
                            <div class="sms-progress-bar <?php echo esc_attr($progress_class); ?>">
                                <div class="sms-progress-fill" style="width: <?php echo esc_attr($utilization); ?>%"></div>
                            </div>
                            <span class="sms-progress-text"><?php echo round($utilization, 1); ?>% utilized</span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="sms-empty-state">
                    <p><?php _e('No active routes found.', 'school-management-system'); ?></p>
                    <a href="<?php echo admin_url('post-new.php?post_type=sms_transport_routes'); ?>" class="button button-primary">
                        <?php _e('Create First Route', 'school-management-system'); ?>
                    </a>
                </div>
                <?php endif; ?>
            </div>

            <!-- Capacity Overview -->
            <div class="sms-dashboard-section">
                <h2><?php _e('Capacity Overview', 'school-management-system'); ?></h2>
                <div class="sms-capacity-overview">
                    <div class="sms-capacity-chart">
                        <div class="sms-capacity-item">
                            <div class="sms-capacity-bar">
                                <div class="sms-capacity-fill enrolled" style="width: <?php echo $stats['total_capacity'] > 0 ? ($stats['total_enrollment'] / $stats['total_capacity']) * 100 : 0; ?>%"></div>
                            </div>
                            <div class="sms-capacity-labels">
                                <span class="enrolled"><?php echo esc_html($stats['total_enrollment']); ?> <?php _e('Enrolled', 'school-management-system'); ?></span>
                                <span class="available"><?php echo esc_html($stats['available_capacity']); ?> <?php _e('Available', 'school-management-system'); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="sms-capacity-stats">
                        <div class="sms-capacity-stat">
                            <h4><?php echo esc_html($stats['total_capacity']); ?></h4>
                            <p><?php _e('Total Capacity', 'school-management-system'); ?></p>
                        </div>
                        <div class="sms-capacity-stat">
                            <h4><?php echo esc_html($stats['capacity_utilization']); ?>%</h4>
                            <p><?php _e('Utilization Rate', 'school-management-system'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Auto-refresh dashboard data every 5 minutes
    setInterval(function() {
        refreshDashboardData();
    }, 300000);

    function refreshDashboardData() {
        $.post(ajaxurl, {
            action: 'sms_get_transport_dashboard_data',
            data_type: 'statistics',
            nonce: smsTransportAdmin.nonce
        }, function(response) {
            if (response.success) {
                updateDashboardStats(response.data);
            }
        });
    }

    function updateDashboardStats(stats) {
        // Update stat cards with new data
        $('.sms-stat-card').each(function() {
            var $card = $(this);
            var $content = $card.find('.sms-stat-content h3');
            
            if ($card.find('p').text().includes('Total Routes')) {
                $content.text(stats.total_routes);
            } else if ($card.find('p').text().includes('Active Routes')) {
                $content.text(stats.active_routes);
            } else if ($card.find('p').text().includes('Students Enrolled')) {
                $content.text(stats.total_enrollment);
            } else if ($card.find('p').text().includes('Capacity Utilization')) {
                $content.text(stats.capacity_utilization + '%');
            }
        });
    }
});
</script>