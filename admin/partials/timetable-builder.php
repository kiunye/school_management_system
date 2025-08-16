<?php
/**
 * Timetable Builder Interface
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
    <h1 class="wp-heading-inline"><?php _e('Timetable Builder', 'school-management-system'); ?></h1>
    <a href="<?php echo admin_url('post-new.php?post_type=sms_timetables'); ?>" class="page-title-action">
        <?php _e('Add New Timetable', 'school-management-system'); ?>
    </a>
    <hr class="wp-header-end">

    <div id="timetable-builder-container">
        <!-- Timetable Selection Panel -->
        <div class="timetable-selection-panel">
            <div class="panel-header">
                <h2><?php _e('Timetable Configuration', 'school-management-system'); ?></h2>
            </div>
            <div class="panel-content">
                <form id="timetable-config-form">
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="existing-timetable"><?php _e('Load Existing Timetable', 'school-management-system'); ?></label>
                            </th>
                            <td>
                                <select id="existing-timetable" name="existing_timetable">
                                    <option value=""><?php _e('Create New Timetable', 'school-management-system'); ?></option>
                                    <?php
                                    $existing_timetables = get_posts(array(
                                        'post_type' => 'sms_timetables',
                                        'posts_per_page' => -1,
                                        'post_status' => array('publish', 'draft'),
                                        'orderby' => 'title',
                                        'order' => 'ASC'
                                    ));
                                    foreach ($existing_timetables as $timetable) {
                                        echo '<option value="' . $timetable->ID . '">' . esc_html($timetable->post_title) . '</option>';
                                    }
                                    ?>
                                </select>
                                <p class="description"><?php _e('Select an existing timetable to edit, or leave blank to create a new one.', 'school-management-system'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="timetable-class"><?php _e('Class', 'school-management-system'); ?> <span class="required">*</span></label>
                            </th>
                            <td>
                                <select id="timetable-class" name="timetable_class" required>
                                    <option value=""><?php _e('Select Class', 'school-management-system'); ?></option>
                                    <?php foreach ($classes as $class) : ?>
                                        <option value="<?php echo $class->ID; ?>"><?php echo esc_html($class->post_title); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="academic-year"><?php _e('Academic Year', 'school-management-system'); ?> <span class="required">*</span></label>
                            </th>
                            <td>
                                <select id="academic-year" name="academic_year" required>
                                    <option value=""><?php _e('Select Academic Year', 'school-management-system'); ?></option>
                                    <?php foreach ($academic_years as $year) : ?>
                                        <option value="<?php echo $year->slug; ?>"><?php echo esc_html($year->name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="term"><?php _e('Term', 'school-management-system'); ?> <span class="required">*</span></label>
                            </th>
                            <td>
                                <select id="term" name="term" required>
                                    <option value=""><?php _e('Select Term', 'school-management-system'); ?></option>
                                    <?php foreach ($terms as $term) : ?>
                                        <option value="<?php echo $term->slug; ?>"><?php echo esc_html($term->name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                    </table>
                    <p class="submit">
                        <button type="button" id="load-timetable-btn" class="button button-secondary">
                            <?php _e('Load Configuration', 'school-management-system'); ?>
                        </button>
                        <button type="button" id="new-timetable-btn" class="button button-primary">
                            <?php _e('Start New Timetable', 'school-management-system'); ?>
                        </button>
                    </p>
                </form>
            </div>
        </div>

        <!-- Timetable Builder Interface -->
        <div id="timetable-builder-interface" style="display: none;">
            <div class="builder-header">
                <div class="builder-title">
                    <h2 id="current-timetable-title"><?php _e('New Timetable', 'school-management-system'); ?></h2>
                    <div class="builder-actions">
                        <button type="button" id="validate-timetable-btn" class="button button-secondary">
                            <?php _e('Validate Timetable', 'school-management-system'); ?>
                        </button>
                        <button type="button" id="save-timetable-btn" class="button button-primary">
                            <?php _e('Save Timetable', 'school-management-system'); ?>
                        </button>
                    </div>
                </div>
                <div class="validation-status" id="validation-status"></div>
            </div>

            <!-- Time Slot Management Panel -->
            <div class="time-slot-panel">
                <div class="panel-header">
                    <h3><?php _e('Time Slot Management', 'school-management-system'); ?></h3>
                    <button type="button" id="add-time-slot-btn" class="button button-secondary">
                        <?php _e('Add Time Slot', 'school-management-system'); ?>
                    </button>
                </div>
                <div class="panel-content">
                    <div id="time-slot-form" class="time-slot-form">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="slot-day"><?php _e('Day', 'school-management-system'); ?></label>
                                <select id="slot-day" name="slot_day">
                                    <option value="monday"><?php _e('Monday', 'school-management-system'); ?></option>
                                    <option value="tuesday"><?php _e('Tuesday', 'school-management-system'); ?></option>
                                    <option value="wednesday"><?php _e('Wednesday', 'school-management-system'); ?></option>
                                    <option value="thursday"><?php _e('Thursday', 'school-management-system'); ?></option>
                                    <option value="friday"><?php _e('Friday', 'school-management-system'); ?></option>
                                    <option value="saturday"><?php _e('Saturday', 'school-management-system'); ?></option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="slot-start-time"><?php _e('Start Time', 'school-management-system'); ?></label>
                                <input type="time" id="slot-start-time" name="slot_start_time" required>
                            </div>
                            <div class="form-group">
                                <label for="slot-end-time"><?php _e('End Time', 'school-management-system'); ?></label>
                                <input type="time" id="slot-end-time" name="slot_end_time" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="slot-subject"><?php _e('Subject', 'school-management-system'); ?></label>
                                <select id="slot-subject" name="slot_subject">
                                    <option value=""><?php _e('Select Subject', 'school-management-system'); ?></option>
                                    <?php foreach ($subjects as $subject) : ?>
                                        <option value="<?php echo $subject->term_id; ?>"><?php echo esc_html($subject->name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="slot-teacher"><?php _e('Teacher', 'school-management-system'); ?></label>
                                <select id="slot-teacher" name="slot_teacher">
                                    <option value=""><?php _e('Select Teacher', 'school-management-system'); ?></option>
                                    <?php foreach ($teachers as $teacher) : ?>
                                        <option value="<?php echo $teacher->ID; ?>"><?php echo esc_html($teacher->display_name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="slot-room"><?php _e('Room/Location', 'school-management-system'); ?></label>
                                <input type="text" id="slot-room" name="slot_room" placeholder="<?php _e('e.g., Room 101', 'school-management-system'); ?>">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="slot-type"><?php _e('Slot Type', 'school-management-system'); ?></label>
                                <select id="slot-type" name="slot_type">
                                    <option value="lesson"><?php _e('Lesson', 'school-management-system'); ?></option>
                                    <option value="break"><?php _e('Break', 'school-management-system'); ?></option>
                                    <option value="lunch"><?php _e('Lunch', 'school-management-system'); ?></option>
                                    <option value="assembly"><?php _e('Assembly', 'school-management-system'); ?></option>
                                    <option value="sports"><?php _e('Sports', 'school-management-system'); ?></option>
                                    <option value="study"><?php _e('Study Period', 'school-management-system'); ?></option>
                                </select>
                            </div>
                            <div class="form-group form-actions">
                                <button type="button" id="check-availability-btn" class="button button-secondary">
                                    <?php _e('Check Availability', 'school-management-system'); ?>
                                </button>
                                <button type="button" id="add-slot-btn" class="button button-primary">
                                    <?php _e('Add Slot', 'school-management-system'); ?>
                                </button>
                            </div>
                        </div>
                        <div id="slot-validation-result" class="validation-result"></div>
                    </div>
                </div>
            </div>

            <!-- Timetable Grid -->
            <div class="timetable-grid-container">
                <div class="panel-header">
                    <h3><?php _e('Weekly Timetable', 'school-management-system'); ?></h3>
                    <div class="grid-controls">
                        <button type="button" id="clear-timetable-btn" class="button button-secondary">
                            <?php _e('Clear All', 'school-management-system'); ?>
                        </button>
                        <button type="button" id="auto-arrange-btn" class="button button-secondary">
                            <?php _e('Auto Arrange', 'school-management-system'); ?>
                        </button>
                    </div>
                </div>
                <div class="timetable-grid" id="timetable-grid">
                    <div class="grid-header">
                        <div class="time-column-header"><?php _e('Time', 'school-management-system'); ?></div>
                        <div class="day-header"><?php _e('Monday', 'school-management-system'); ?></div>
                        <div class="day-header"><?php _e('Tuesday', 'school-management-system'); ?></div>
                        <div class="day-header"><?php _e('Wednesday', 'school-management-system'); ?></div>
                        <div class="day-header"><?php _e('Thursday', 'school-management-system'); ?></div>
                        <div class="day-header"><?php _e('Friday', 'school-management-system'); ?></div>
                        <div class="day-header"><?php _e('Saturday', 'school-management-system'); ?></div>
                    </div>
                    <div class="grid-body" id="timetable-grid-body">
                        <!-- Grid rows will be dynamically generated -->
                    </div>
                </div>
            </div>

            <!-- Time Slots List -->
            <div class="time-slots-list-container">
                <div class="panel-header">
                    <h3><?php _e('Time Slots', 'school-management-system'); ?></h3>
                    <div class="list-controls">
                        <button type="button" id="sort-by-time-btn" class="button button-secondary">
                            <?php _e('Sort by Time', 'school-management-system'); ?>
                        </button>
                        <button type="button" id="sort-by-day-btn" class="button button-secondary">
                            <?php _e('Sort by Day', 'school-management-system'); ?>
                        </button>
                    </div>
                </div>
                <div class="time-slots-list" id="time-slots-list">
                    <div class="no-slots-message" id="no-slots-message">
                        <?php _e('No time slots added yet. Use the form above to add time slots.', 'school-management-system'); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Conflict Detection Modal -->
    <div id="conflict-modal" class="sms-modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3><?php _e('Conflicts Detected', 'school-management-system'); ?></h3>
                <span class="modal-close">&times;</span>
            </div>
            <div class="modal-body">
                <div id="conflict-details"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="button button-secondary modal-close">
                    <?php _e('Close', 'school-management-system'); ?>
                </button>
                <button type="button" id="resolve-conflicts-btn" class="button button-primary">
                    <?php _e('Resolve Conflicts', 'school-management-system'); ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div id="loading-overlay" class="loading-overlay" style="display: none;">
        <div class="loading-spinner">
            <div class="spinner"></div>
            <p><?php _e('Loading...', 'school-management-system'); ?></p>
        </div>
    </div>
</div>

<!-- Time Slot Template -->
<script type="text/template" id="time-slot-template">
    <div class="time-slot-item" data-slot-id="{{slot_id}}">
        <div class="slot-header">
            <div class="slot-time">{{day}} {{start_time}} - {{end_time}}</div>
            <div class="slot-actions">
                <button type="button" class="edit-slot-btn" title="<?php _e('Edit Slot', 'school-management-system'); ?>">
                    <span class="dashicons dashicons-edit"></span>
                </button>
                <button type="button" class="delete-slot-btn" title="<?php _e('Delete Slot', 'school-management-system'); ?>">
                    <span class="dashicons dashicons-trash"></span>
                </button>
            </div>
        </div>
        <div class="slot-details">
            <div class="slot-subject">{{subject_name}}</div>
            <div class="slot-teacher">{{teacher_name}}</div>
            <div class="slot-room">{{room}}</div>
            <div class="slot-type slot-type-{{slot_type}}">{{slot_type_label}}</div>
        </div>
        <div class="slot-status {{status_class}}">{{status_text}}</div>
    </div>
</script>

<!-- Grid Cell Template -->
<script type="text/template" id="grid-cell-template">
    <div class="grid-cell {{cell_class}}" data-day="{{day}}" data-time="{{time_slot}}">
        <div class="cell-content">
            <div class="cell-subject">{{subject_name}}</div>
            <div class="cell-teacher">{{teacher_name}}</div>
            <div class="cell-room">{{room}}</div>
        </div>
        <div class="cell-actions">
            <button type="button" class="edit-cell-btn" title="<?php _e('Edit', 'school-management-system'); ?>">
                <span class="dashicons dashicons-edit"></span>
            </button>
            <button type="button" class="delete-cell-btn" title="<?php _e('Delete', 'school-management-system'); ?>">
                <span class="dashicons dashicons-trash"></span>
            </button>
        </div>
    </div>
</script>