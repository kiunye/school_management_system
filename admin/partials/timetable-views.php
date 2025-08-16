<?php
/**
 * Timetable Views Interface
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
    <h1 class="wp-heading-inline"><?php _e('Timetable Views', 'school-management-system'); ?></h1>
    <hr class="wp-header-end">

    <div id="timetable-views-container">
        <!-- View Selection Panel -->
        <div class="view-selection-panel">
            <div class="panel-header">
                <h2><?php _e('Select View Type', 'school-management-system'); ?></h2>
            </div>
            <div class="panel-content">
                <div class="view-type-tabs">
                    <button type="button" class="view-tab active" data-view="class">
                        <span class="dashicons dashicons-groups"></span>
                        <?php _e('Class View', 'school-management-system'); ?>
                    </button>
                    <button type="button" class="view-tab" data-view="teacher">
                        <span class="dashicons dashicons-businessman"></span>
                        <?php _e('Teacher View', 'school-management-system'); ?>
                    </button>
                    <button type="button" class="view-tab" data-view="overview">
                        <span class="dashicons dashicons-dashboard"></span>
                        <?php _e('Overview', 'school-management-system'); ?>
                    </button>
                </div>

                <!-- Class View Panel -->
                <div class="view-panel" id="class-view-panel">
                    <form class="view-form" id="class-view-form">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="class-select"><?php _e('Select Class', 'school-management-system'); ?></label>
                                </th>
                                <td>
                                    <select id="class-select" name="class_id" required>
                                        <option value=""><?php _e('Choose a class', 'school-management-system'); ?></option>
                                        <?php foreach ($classes as $class) : ?>
                                            <option value="<?php echo $class->ID; ?>"><?php echo esc_html($class->post_title); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="class-academic-year"><?php _e('Academic Year', 'school-management-system'); ?></label>
                                </th>
                                <td>
                                    <select id="class-academic-year" name="academic_year">
                                        <option value=""><?php _e('Current Academic Year', 'school-management-system'); ?></option>
                                        <?php foreach ($academic_years as $year) : ?>
                                            <option value="<?php echo $year->slug; ?>"><?php echo esc_html($year->name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="class-term"><?php _e('Term', 'school-management-system'); ?></label>
                                </th>
                                <td>
                                    <select id="class-term" name="term">
                                        <option value=""><?php _e('Current Term', 'school-management-system'); ?></option>
                                        <?php foreach ($terms as $term) : ?>
                                            <option value="<?php echo $term->slug; ?>"><?php echo esc_html($term->name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="class-display-format"><?php _e('Display Format', 'school-management-system'); ?></label>
                                </th>
                                <td>
                                    <select id="class-display-format" name="display_format">
                                        <option value="grid"><?php _e('Grid View', 'school-management-system'); ?></option>
                                        <option value="list"><?php _e('List View', 'school-management-system'); ?></option>
                                        <option value="compact"><?php _e('Compact View', 'school-management-system'); ?></option>
                                    </select>
                                </td>
                            </tr>
                        </table>
                        <p class="submit">
                            <button type="submit" class="button button-primary">
                                <?php _e('View Timetable', 'school-management-system'); ?>
                            </button>
                            <button type="button" class="button button-secondary export-pdf-btn" disabled>
                                <?php _e('Export PDF', 'school-management-system'); ?>
                            </button>
                            <button type="button" class="button button-secondary export-csv-btn" disabled>
                                <?php _e('Export CSV', 'school-management-system'); ?>
                            </button>
                        </p>
                    </form>
                </div>

                <!-- Teacher View Panel -->
                <div class="view-panel" id="teacher-view-panel" style="display: none;">
                    <form class="view-form" id="teacher-view-form">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="teacher-select"><?php _e('Select Teacher', 'school-management-system'); ?></label>
                                </th>
                                <td>
                                    <select id="teacher-select" name="teacher_id" required>
                                        <option value=""><?php _e('Choose a teacher', 'school-management-system'); ?></option>
                                        <?php foreach ($teachers as $teacher) : ?>
                                            <option value="<?php echo $teacher->ID; ?>"><?php echo esc_html($teacher->display_name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="teacher-academic-year"><?php _e('Academic Year', 'school-management-system'); ?></label>
                                </th>
                                <td>
                                    <select id="teacher-academic-year" name="academic_year">
                                        <option value=""><?php _e('Current Academic Year', 'school-management-system'); ?></option>
                                        <?php foreach ($academic_years as $year) : ?>
                                            <option value="<?php echo $year->slug; ?>"><?php echo esc_html($year->name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="teacher-term"><?php _e('Term', 'school-management-system'); ?></label>
                                </th>
                                <td>
                                    <select id="teacher-term" name="term">
                                        <option value=""><?php _e('Current Term', 'school-management-system'); ?></option>
                                        <?php foreach ($terms as $term) : ?>
                                            <option value="<?php echo $term->slug; ?>"><?php echo esc_html($term->name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="teacher-display-format"><?php _e('Display Format', 'school-management-system'); ?></label>
                                </th>
                                <td>
                                    <select id="teacher-display-format" name="display_format">
                                        <option value="grid"><?php _e('Grid View', 'school-management-system'); ?></option>
                                        <option value="list"><?php _e('List View', 'school-management-system'); ?></option>
                                        <option value="compact"><?php _e('Compact View', 'school-management-system'); ?></option>
                                    </select>
                                </td>
                            </tr>
                        </table>
                        <p class="submit">
                            <button type="submit" class="button button-primary">
                                <?php _e('View Timetable', 'school-management-system'); ?>
                            </button>
                            <button type="button" class="button button-secondary export-pdf-btn" disabled>
                                <?php _e('Export PDF', 'school-management-system'); ?>
                            </button>
                            <button type="button" class="button button-secondary export-csv-btn" disabled>
                                <?php _e('Export CSV', 'school-management-system'); ?>
                            </button>
                        </p>
                    </form>
                </div>

                <!-- Overview Panel -->
                <div class="view-panel" id="overview-panel" style="display: none;">
                    <div class="overview-content">
                        <div class="overview-stats">
                            <div class="stat-card">
                                <h3><?php _e('Total Timetables', 'school-management-system'); ?></h3>
                                <div class="stat-number" id="total-timetables">-</div>
                            </div>
                            <div class="stat-card">
                                <h3><?php _e('Active Timetables', 'school-management-system'); ?></h3>
                                <div class="stat-number" id="active-timetables">-</div>
                            </div>
                            <div class="stat-card">
                                <h3><?php _e('Classes with Timetables', 'school-management-system'); ?></h3>
                                <div class="stat-number" id="classes-with-timetables">-</div>
                            </div>
                            <div class="stat-card">
                                <h3><?php _e('Teachers Assigned', 'school-management-system'); ?></h3>
                                <div class="stat-number" id="teachers-assigned">-</div>
                            </div>
                        </div>

                        <div class="overview-filters">
                            <h3><?php _e('Filter Overview', 'school-management-system'); ?></h3>
                            <form id="overview-filter-form">
                                <div class="filter-row">
                                    <div class="filter-group">
                                        <label for="overview-academic-year"><?php _e('Academic Year', 'school-management-system'); ?></label>
                                        <select id="overview-academic-year" name="academic_year">
                                            <option value=""><?php _e('All Years', 'school-management-system'); ?></option>
                                            <?php foreach ($academic_years as $year) : ?>
                                                <option value="<?php echo $year->slug; ?>"><?php echo esc_html($year->name); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="filter-group">
                                        <label for="overview-term"><?php _e('Term', 'school-management-system'); ?></label>
                                        <select id="overview-term" name="term">
                                            <option value=""><?php _e('All Terms', 'school-management-system'); ?></option>
                                            <?php foreach ($terms as $term) : ?>
                                                <option value="<?php echo $term->slug; ?>"><?php echo esc_html($term->name); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="filter-group">
                                        <button type="submit" class="button button-primary">
                                            <?php _e('Update Overview', 'school-management-system'); ?>
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <div class="overview-tables">
                            <div class="overview-table-container">
                                <h3><?php _e('Timetable Status Summary', 'school-management-system'); ?></h3>
                                <table class="wp-list-table widefat fixed striped" id="timetable-status-table">
                                    <thead>
                                        <tr>
                                            <th><?php _e('Class', 'school-management-system'); ?></th>
                                            <th><?php _e('Academic Year', 'school-management-system'); ?></th>
                                            <th><?php _e('Term', 'school-management-system'); ?></th>
                                            <th><?php _e('Status', 'school-management-system'); ?></th>
                                            <th><?php _e('Last Modified', 'school-management-system'); ?></th>
                                            <th><?php _e('Actions', 'school-management-system'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td colspan="6" class="loading-row">
                                                <?php _e('Loading timetable data...', 'school-management-system'); ?>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Timetable Display Area -->
        <div class="timetable-display-area" id="timetable-display-area" style="display: none;">
            <div class="display-header">
                <div class="display-title">
                    <h2 id="display-title"><?php _e('Timetable Display', 'school-management-system'); ?></h2>
                    <div class="display-info" id="display-info"></div>
                </div>
                <div class="display-controls">
                    <div class="format-switcher">
                        <label><?php _e('View:', 'school-management-system'); ?></label>
                        <select id="format-switcher">
                            <option value="grid"><?php _e('Grid', 'school-management-system'); ?></option>
                            <option value="list"><?php _e('List', 'school-management-system'); ?></option>
                            <option value="compact"><?php _e('Compact', 'school-management-system'); ?></option>
                        </select>
                    </div>
                    <div class="display-actions">
                        <button type="button" class="button button-secondary" id="print-timetable-btn">
                            <?php _e('Print', 'school-management-system'); ?>
                        </button>
                        <button type="button" class="button button-secondary" id="fullscreen-btn">
                            <?php _e('Fullscreen', 'school-management-system'); ?>
                        </button>
                        <button type="button" class="button button-secondary" id="close-display-btn">
                            <?php _e('Close', 'school-management-system'); ?>
                        </button>
                    </div>
                </div>
            </div>
            <div class="display-content" id="display-content">
                <div class="loading-message">
                    <div class="spinner"></div>
                    <p><?php _e('Loading timetable...', 'school-management-system'); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Print Styles -->
    <style media="print">
        .view-selection-panel,
        .display-header,
        .wp-header-end,
        .wrap h1,
        #wpadminbar,
        #adminmenumain,
        #wpfooter {
            display: none !important;
        }
        
        .timetable-display-area {
            display: block !important;
            margin: 0 !important;
            padding: 0 !important;
        }
        
        .display-content {
            margin: 0 !important;
            padding: 20px !important;
        }
        
        .timetable-grid-table {
            width: 100% !important;
            border-collapse: collapse !important;
        }
        
        .timetable-grid-table th,
        .timetable-grid-table td {
            border: 1px solid #000 !important;
            padding: 8px !important;
            font-size: 12px !important;
        }
        
        .timetable-grid-table th {
            background: #f0f0f0 !important;
            font-weight: bold !important;
        }
    </style>

    <!-- Loading Overlay -->
    <div id="loading-overlay" class="loading-overlay" style="display: none;">
        <div class="loading-spinner">
            <div class="spinner"></div>
            <p><?php _e('Loading...', 'school-management-system'); ?></p>
        </div>
    </div>
</div>