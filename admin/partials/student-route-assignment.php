<?php
/**
 * Student Route Assignment Admin Partial
 *
 * @package SchoolManagementSystem
 * @subpackage Admin/Partials
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get available routes and students
$transport_assigner = new SMS_Transport_Assigner();
$available_routes = $transport_assigner->get_available_routes();

// Get all students
$students_query = new WP_Query(array(
    'post_type' => 'sms_students',
    'post_status' => 'publish',
    'posts_per_page' => -1,
    'orderby' => 'title',
    'order' => 'ASC'
));

$students = array();
if ($students_query->have_posts()) {
    while ($students_query->have_posts()) {
        $students_query->the_post();
        $student_id = get_the_ID();
        $students[] = array(
            'id' => $student_id,
            'name' => get_the_title(),
            'admission_number' => get_field('admission_number', $student_id),
            'grade_level' => get_field('grade_level', $student_id),
            'current_route' => get_field('transport_route', $student_id),
            'current_stop' => get_field('transport_pickup_stop', $student_id),
            'transport_fee' => get_field('transport_fee', $student_id),
            'parent_phone' => get_field('parent_phone', $student_id)
        );
    }
    wp_reset_postdata();
}
?>

<div class="wrap">
    <h1><?php _e('Student Route Assignment', 'school-management-system'); ?></h1>

    <!-- Assignment Tabs -->
    <div class="sms-assignment-tabs">
        <nav class="nav-tab-wrapper">
            <a href="#individual-assignment" class="nav-tab nav-tab-active"><?php _e('Individual Assignment', 'school-management-system'); ?></a>
            <a href="#bulk-assignment" class="nav-tab"><?php _e('Bulk Assignment', 'school-management-system'); ?></a>
            <a href="#route-overview" class="nav-tab"><?php _e('Route Overview', 'school-management-system'); ?></a>
            <a href="#unassigned-students" class="nav-tab"><?php _e('Unassigned Students', 'school-management-system'); ?></a>
        </nav>

        <!-- Individual Assignment Tab -->
        <div id="individual-assignment" class="sms-tab-content active">
            <div class="sms-assignment-form">
                <h2><?php _e('Assign Student to Route', 'school-management-system'); ?></h2>
                
                <form id="sms-individual-assignment-form">
                    <div class="sms-form-grid">
                        <div class="sms-form-group">
                            <label for="student_select"><?php _e('Select Student', 'school-management-system'); ?> *</label>
                            <select id="student_select" name="student_id" required class="sms-select2">
                                <option value=""><?php _e('Choose a student...', 'school-management-system'); ?></option>
                                <?php foreach ($students as $student): ?>
                                <option value="<?php echo esc_attr($student['id']); ?>" 
                                        data-admission="<?php echo esc_attr($student['admission_number']); ?>"
                                        data-grade="<?php echo esc_attr($student['grade_level']); ?>"
                                        data-current-route="<?php echo esc_attr($student['current_route']); ?>"
                                        data-parent-phone="<?php echo esc_attr($student['parent_phone']); ?>">
                                    <?php echo esc_html($student['name']); ?> (<?php echo esc_html($student['admission_number']); ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="sms-form-group">
                            <label for="route_select"><?php _e('Select Route', 'school-management-system'); ?> *</label>
                            <select id="route_select" name="route_id" required class="sms-select2">
                                <option value=""><?php _e('Choose a route...', 'school-management-system'); ?></option>
                                <?php foreach ($available_routes as $route): ?>
                                <option value="<?php echo esc_attr($route['id']); ?>"
                                        data-capacity="<?php echo esc_attr($route['total_capacity']); ?>"
                                        data-enrollment="<?php echo esc_attr($route['current_enrollment']); ?>"
                                        data-available="<?php echo esc_attr($route['available_capacity']); ?>"
                                        data-fee-type="<?php echo esc_attr($route['fee_structure_type']); ?>"
                                        data-base-fee="<?php echo esc_attr($route['base_fee']); ?>">
                                    <?php echo esc_html($route['name']); ?> (<?php echo esc_html($route['code']); ?>) - 
                                    <?php echo esc_html($route['available_capacity']); ?> <?php _e('seats available', 'school-management-system'); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="sms-form-group">
                            <label for="pickup_stop_select"><?php _e('Pickup Stop', 'school-management-system'); ?></label>
                            <select id="pickup_stop_select" name="pickup_stop" class="sms-select2">
                                <option value=""><?php _e('Select pickup stop...', 'school-management-system'); ?></option>
                            </select>
                        </div>
                    </div>

                    <!-- Student Information Display -->
                    <div id="student-info" class="sms-info-panel" style="display: none;">
                        <h3><?php _e('Student Information', 'school-management-system'); ?></h3>
                        <div class="sms-info-grid">
                            <div class="sms-info-item">
                                <label><?php _e('Admission Number:', 'school-management-system'); ?></label>
                                <span id="student-admission"></span>
                            </div>
                            <div class="sms-info-item">
                                <label><?php _e('Grade Level:', 'school-management-system'); ?></label>
                                <span id="student-grade"></span>
                            </div>
                            <div class="sms-info-item">
                                <label><?php _e('Current Route:', 'school-management-system'); ?></label>
                                <span id="student-current-route"></span>
                            </div>
                            <div class="sms-info-item">
                                <label><?php _e('Parent Phone:', 'school-management-system'); ?></label>
                                <span id="student-parent-phone"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Route Information Display -->
                    <div id="route-info" class="sms-info-panel" style="display: none;">
                        <h3><?php _e('Route Information', 'school-management-system'); ?></h3>
                        <div class="sms-info-grid">
                            <div class="sms-info-item">
                                <label><?php _e('Capacity:', 'school-management-system'); ?></label>
                                <span id="route-capacity"></span>
                            </div>
                            <div class="sms-info-item">
                                <label><?php _e('Current Enrollment:', 'school-management-system'); ?></label>
                                <span id="route-enrollment"></span>
                            </div>
                            <div class="sms-info-item">
                                <label><?php _e('Available Seats:', 'school-management-system'); ?></label>
                                <span id="route-available"></span>
                            </div>
                            <div class="sms-info-item">
                                <label><?php _e('Base Fee:', 'school-management-system'); ?></label>
                                <span id="route-base-fee"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Fee Calculation Display -->
                    <div id="fee-calculation" class="sms-info-panel" style="display: none;">
                        <h3><?php _e('Transport Fee Calculation', 'school-management-system'); ?></h3>
                        <div class="sms-fee-display">
                            <div class="sms-fee-amount">
                                <label><?php _e('Calculated Fee:', 'school-management-system'); ?></label>
                                <span id="calculated-fee" class="sms-fee-value">KES 0.00</span>
                            </div>
                            <div class="sms-fee-breakdown" id="fee-breakdown"></div>
                        </div>
                    </div>

                    <div class="sms-form-actions">
                        <button type="button" id="calculate-fee-btn" class="button"><?php _e('Calculate Fee', 'school-management-system'); ?></button>
                        <button type="submit" class="button button-primary"><?php _e('Assign Student', 'school-management-system'); ?></button>
                        <button type="button" id="unassign-student-btn" class="button button-secondary" style="display: none;"><?php _e('Unassign Student', 'school-management-system'); ?></button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Bulk Assignment Tab -->
        <div id="bulk-assignment" class="sms-tab-content">
            <div class="sms-bulk-assignment">
                <h2><?php _e('Bulk Student Assignment', 'school-management-system'); ?></h2>
                
                <div class="sms-bulk-controls">
                    <div class="sms-form-group">
                        <label for="bulk_route_select"><?php _e('Select Route for Bulk Assignment', 'school-management-system'); ?></label>
                        <select id="bulk_route_select" class="sms-select2">
                            <option value=""><?php _e('Choose a route...', 'school-management-system'); ?></option>
                            <?php foreach ($available_routes as $route): ?>
                            <option value="<?php echo esc_attr($route['id']); ?>"
                                    data-available="<?php echo esc_attr($route['available_capacity']); ?>">
                                <?php echo esc_html($route['name']); ?> (<?php echo esc_html($route['available_capacity']); ?> available)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="sms-form-group">
                        <label for="bulk_pickup_stop"><?php _e('Default Pickup Stop', 'school-management-system'); ?></label>
                        <select id="bulk_pickup_stop" class="sms-select2">
                            <option value=""><?php _e('Select pickup stop...', 'school-management-system'); ?></option>
                        </select>
                    </div>
                </div>

                <div class="sms-student-selection">
                    <h3><?php _e('Select Students to Assign', 'school-management-system'); ?></h3>
                    <div class="sms-selection-controls">
                        <button type="button" id="select-all-students" class="button"><?php _e('Select All', 'school-management-system'); ?></button>
                        <button type="button" id="select-unassigned-only" class="button"><?php _e('Select Unassigned Only', 'school-management-system'); ?></button>
                        <button type="button" id="clear-selection" class="button"><?php _e('Clear Selection', 'school-management-system'); ?></button>
                    </div>
                    
                    <div class="sms-students-grid">
                        <?php foreach ($students as $student): ?>
                        <div class="sms-student-card">
                            <label class="sms-student-checkbox">
                                <input type="checkbox" name="bulk_students[]" value="<?php echo esc_attr($student['id']); ?>"
                                       data-current-route="<?php echo esc_attr($student['current_route']); ?>">
                                <div class="sms-student-info">
                                    <h4><?php echo esc_html($student['name']); ?></h4>
                                    <p><?php echo esc_html($student['admission_number']); ?> - <?php echo esc_html($student['grade_level']); ?></p>
                                    <?php if ($student['current_route']): ?>
                                    <span class="sms-current-assignment"><?php _e('Currently assigned', 'school-management-system'); ?></span>
                                    <?php else: ?>
                                    <span class="sms-no-assignment"><?php _e('Not assigned', 'school-management-system'); ?></span>
                                    <?php endif; ?>
                                </div>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="sms-bulk-actions">
                    <button type="button" id="bulk-assign-btn" class="button button-primary" disabled>
                        <?php _e('Assign Selected Students', 'school-management-system'); ?>
                    </button>
                    <span id="selected-count">0 <?php _e('students selected', 'school-management-system'); ?></span>
                </div>
            </div>
        </div>

        <!-- Route Overview Tab -->
        <div id="route-overview" class="sms-tab-content">
            <div class="sms-route-overview">
                <h2><?php _e('Route Assignment Overview', 'school-management-system'); ?></h2>
                
                <div class="sms-routes-grid">
                    <?php foreach ($available_routes as $route): ?>
                    <div class="sms-route-card">
                        <div class="sms-route-header">
                            <h3><?php echo esc_html($route['name']); ?></h3>
                            <span class="sms-route-code"><?php echo esc_html($route['code']); ?></span>
                        </div>
                        
                        <div class="sms-route-stats">
                            <div class="sms-stat">
                                <label><?php _e('Capacity:', 'school-management-system'); ?></label>
                                <span><?php echo esc_html($route['total_capacity']); ?></span>
                            </div>
                            <div class="sms-stat">
                                <label><?php _e('Enrolled:', 'school-management-system'); ?></label>
                                <span><?php echo esc_html($route['current_enrollment']); ?></span>
                            </div>
                            <div class="sms-stat">
                                <label><?php _e('Available:', 'school-management-system'); ?></label>
                                <span><?php echo esc_html($route['available_capacity']); ?></span>
                            </div>
                        </div>
                        
                        <div class="sms-route-progress">
                            <?php 
                            $utilization = $route['total_capacity'] > 0 ? ($route['current_enrollment'] / $route['total_capacity']) * 100 : 0;
                            $progress_class = $utilization >= 90 ? 'high' : ($utilization >= 70 ? 'medium' : 'low');
                            ?>
                            <div class="sms-progress-bar <?php echo esc_attr($progress_class); ?>">
                                <div class="sms-progress-fill" style="width: <?php echo esc_attr($utilization); ?>%"></div>
                            </div>
                            <span class="sms-progress-text"><?php echo round($utilization, 1); ?>% utilized</span>
                        </div>
                        
                        <div class="sms-route-actions">
                            <button type="button" class="button view-assignments" data-route-id="<?php echo esc_attr($route['id']); ?>">
                                <?php _e('View Assignments', 'school-management-system'); ?>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Unassigned Students Tab -->
        <div id="unassigned-students" class="sms-tab-content">
            <div class="sms-unassigned-students">
                <h2><?php _e('Unassigned Students', 'school-management-system'); ?></h2>
                
                <div class="sms-unassigned-list">
                    <?php 
                    $unassigned_students = array_filter($students, function($student) {
                        return empty($student['current_route']);
                    });
                    ?>
                    
                    <?php if (empty($unassigned_students)): ?>
                    <div class="sms-empty-state">
                        <p><?php _e('All students are assigned to transport routes.', 'school-management-system'); ?></p>
                    </div>
                    <?php else: ?>
                    <table class="sms-students-table wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Student Name', 'school-management-system'); ?></th>
                                <th><?php _e('Admission Number', 'school-management-system'); ?></th>
                                <th><?php _e('Grade Level', 'school-management-system'); ?></th>
                                <th><?php _e('Parent Phone', 'school-management-system'); ?></th>
                                <th><?php _e('Actions', 'school-management-system'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($unassigned_students as $student): ?>
                            <tr>
                                <td><?php echo esc_html($student['name']); ?></td>
                                <td><?php echo esc_html($student['admission_number']); ?></td>
                                <td><?php echo esc_html($student['grade_level']); ?></td>
                                <td><?php echo esc_html($student['parent_phone']); ?></td>
                                <td>
                                    <button type="button" class="button button-small assign-student" 
                                            data-student-id="<?php echo esc_attr($student['id']); ?>"
                                            data-student-name="<?php echo esc_attr($student['name']); ?>">
                                        <?php _e('Assign to Route', 'school-management-system'); ?>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Route Assignments Modal -->
<div id="route-assignments-modal" class="sms-modal" style="display: none;">
    <div class="sms-modal-dialog sms-modal-lg">
        <div class="sms-modal-content">
            <div class="sms-modal-header">
                <h4 class="sms-modal-title"><?php _e('Route Assignments', 'school-management-system'); ?></h4>
                <button type="button" class="sms-modal-close" data-dismiss="modal">&times;</button>
            </div>
            <div class="sms-modal-body">
                <div id="assignments-loading" class="sms-loading">
                    <span class="dashicons dashicons-update"></span>
                    <?php _e('Loading assignments...', 'school-management-system'); ?>
                </div>
                <div id="assignments-content"></div>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    var TransportAssignment = {
        init: function() {
            this.bindEvents();
            this.initializeComponents();
        },

        bindEvents: function() {
            // Tab switching
            $('.nav-tab').on('click', this.switchTab);
            
            // Individual assignment
            $('#student_select').on('change', this.loadStudentInfo);
            $('#route_select').on('change', this.loadRouteInfo);
            $('#pickup_stop_select').on('change', this.calculateFee);
            $('#calculate-fee-btn').on('click', this.calculateFee);
            $('#sms-individual-assignment-form').on('submit', this.submitIndividualAssignment);
            $('#unassign-student-btn').on('click', this.unassignStudent);
            
            // Bulk assignment
            $('#bulk_route_select').on('change', this.loadBulkRouteStops);
            $('#select-all-students').on('click', this.selectAllStudents);
            $('#select-unassigned-only').on('click', this.selectUnassignedOnly);
            $('#clear-selection').on('click', this.clearSelection);
            $('input[name="bulk_students[]"]').on('change', this.updateBulkSelection);
            $('#bulk-assign-btn').on('click', this.submitBulkAssignment);
            
            // Route overview
            $('.view-assignments').on('click', this.viewRouteAssignments);
            
            // Unassigned students
            $('.assign-student').on('click', this.quickAssignStudent);
        },

        initializeComponents: function() {
            // Initialize Select2
            $('.sms-select2').select2({
                width: '100%'
            });
        },

        switchTab: function(e) {
            e.preventDefault();
            
            var $tab = $(this);
            var target = $tab.attr('href');
            
            // Update tab states
            $('.nav-tab').removeClass('nav-tab-active');
            $tab.addClass('nav-tab-active');
            
            // Update content
            $('.sms-tab-content').removeClass('active');
            $(target).addClass('active');
        },

        loadStudentInfo: function() {
            var $option = $(this).find('option:selected');
            var studentId = $option.val();
            
            if (!studentId) {
                $('#student-info').hide();
                $('#unassign-student-btn').hide();
                return;
            }
            
            $('#student-admission').text($option.data('admission'));
            $('#student-grade').text($option.data('grade'));
            $('#student-parent-phone').text($option.data('parent-phone'));
            
            var currentRoute = $option.data('current-route');
            if (currentRoute) {
                $('#student-current-route').text('Route assigned');
                $('#unassign-student-btn').show();
            } else {
                $('#student-current-route').text('No route assigned');
                $('#unassign-student-btn').hide();
            }
            
            $('#student-info').show();
        },

        loadRouteInfo: function() {
            var $option = $(this).find('option:selected');
            var routeId = $option.val();
            
            if (!routeId) {
                $('#route-info').hide();
                $('#pickup_stop_select').empty().append('<option value="">Select pickup stop...</option>');
                return;
            }
            
            $('#route-capacity').text($option.data('capacity'));
            $('#route-enrollment').text($option.data('enrollment'));
            $('#route-available').text($option.data('available'));
            $('#route-base-fee').text('KES ' + parseFloat($option.data('base-fee')).toFixed(2));
            
            $('#route-info').show();
            
            // Load route stops
            TransportAssignment.loadRouteStops(routeId, '#pickup_stop_select');
        },

        loadRouteStops: function(routeId, targetSelect) {
            $.post(ajaxurl, {
                action: 'sms_get_route_stops',
                route_id: routeId,
                nonce: smsTransportAdmin.nonce
            }, function(response) {
                if (response.success) {
                    var $select = $(targetSelect);
                    $select.empty().append('<option value="">Select pickup stop...</option>');
                    
                    $.each(response.data, function(index, stop) {
                        $select.append('<option value="' + stop.name + '">' + stop.name + ' (' + stop.pickup_time + ')</option>');
                    });
                }
            });
        },

        loadBulkRouteStops: function() {
            var routeId = $(this).val();
            if (routeId) {
                TransportAssignment.loadRouteStops(routeId, '#bulk_pickup_stop');
            } else {
                $('#bulk_pickup_stop').empty().append('<option value="">Select pickup stop...</option>');
            }
        },

        calculateFee: function() {
            var studentId = $('#student_select').val();
            var routeId = $('#route_select').val();
            var pickupStop = $('#pickup_stop_select').val();
            
            if (!studentId || !routeId) {
                return;
            }
            
            $.post(ajaxurl, {
                action: 'sms_calculate_transport_fee',
                student_id: studentId,
                route_id: routeId,
                pickup_stop: pickupStop,
                nonce: smsTransportAdmin.nonce
            }, function(response) {
                if (response.success) {
                    $('#calculated-fee').text(response.data.formatted_fee);
                    $('#fee-calculation').show();
                } else {
                    alert('Error calculating fee: ' + response.data);
                }
            });
        },

        submitIndividualAssignment: function(e) {
            e.preventDefault();
            
            var formData = $(this).serialize();
            var $submitBtn = $(this).find('[type="submit"]');
            
            $submitBtn.prop('disabled', true).text('Assigning...');
            
            $.post(ajaxurl, {
                action: 'sms_assign_student_to_route',
                student_id: $('#student_select').val(),
                route_id: $('#route_select').val(),
                pickup_stop: $('#pickup_stop_select').val(),
                nonce: smsTransportAdmin.nonce
            }, function(response) {
                if (response.success) {
                    alert(response.data.message);
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                    $submitBtn.prop('disabled', false).text('Assign Student');
                }
            });
        },

        unassignStudent: function() {
            if (!confirm('Are you sure you want to unassign this student from their transport route?')) {
                return;
            }
            
            var studentId = $('#student_select').val();
            
            $.post(ajaxurl, {
                action: 'sms_unassign_student_from_route',
                student_id: studentId,
                nonce: smsTransportAdmin.nonce
            }, function(response) {
                if (response.success) {
                    alert(response.data.message);
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                }
            });
        },

        selectAllStudents: function() {
            $('input[name="bulk_students[]"]').prop('checked', true);
            TransportAssignment.updateBulkSelection();
        },

        selectUnassignedOnly: function() {
            $('input[name="bulk_students[]"]').each(function() {
                var hasRoute = $(this).data('current-route');
                $(this).prop('checked', !hasRoute);
            });
            TransportAssignment.updateBulkSelection();
        },

        clearSelection: function() {
            $('input[name="bulk_students[]"]').prop('checked', false);
            TransportAssignment.updateBulkSelection();
        },

        updateBulkSelection: function() {
            var selectedCount = $('input[name="bulk_students[]"]:checked').length;
            $('#selected-count').text(selectedCount + ' students selected');
            $('#bulk-assign-btn').prop('disabled', selectedCount === 0);
        },

        submitBulkAssignment: function() {
            var routeId = $('#bulk_route_select').val();
            var pickupStop = $('#bulk_pickup_stop').val();
            var selectedStudents = [];
            
            $('input[name="bulk_students[]"]:checked').each(function() {
                selectedStudents.push({
                    student_id: $(this).val(),
                    route_id: routeId,
                    pickup_stop: pickupStop
                });
            });
            
            if (selectedStudents.length === 0) {
                alert('Please select students to assign.');
                return;
            }
            
            if (!routeId) {
                alert('Please select a route.');
                return;
            }
            
            var $btn = $(this);
            $btn.prop('disabled', true).text('Assigning...');
            
            $.post(ajaxurl, {
                action: 'sms_bulk_assign_students',
                assignments: selectedStudents,
                nonce: smsTransportAdmin.nonce
            }, function(response) {
                if (response.success) {
                    var results = response.data;
                    var message = results.success.length + ' students assigned successfully.';
                    
                    if (results.errors.length > 0) {
                        message += '\n' + results.errors.length + ' assignments failed.';
                    }
                    
                    alert(message);
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                    $btn.prop('disabled', false).text('Assign Selected Students');
                }
            });
        },

        viewRouteAssignments: function() {
            var routeId = $(this).data('route-id');
            
            $('#route-assignments-modal').show();
            $('#assignments-loading').show();
            $('#assignments-content').hide();
            
            $.post(ajaxurl, {
                action: 'sms_get_route_assignments',
                route_id: routeId,
                nonce: smsTransportAdmin.nonce
            }, function(response) {
                $('#assignments-loading').hide();
                
                if (response.success) {
                    var assignments = response.data;
                    var html = '<table class="wp-list-table widefat fixed striped">';
                    html += '<thead><tr><th>Student Name</th><th>Admission Number</th><th>Grade</th><th>Pickup Stop</th><th>Fee</th></tr></thead>';
                    html += '<tbody>';
                    
                    if (assignments.length === 0) {
                        html += '<tr><td colspan="5">No students assigned to this route.</td></tr>';
                    } else {
                        $.each(assignments, function(index, assignment) {
                            html += '<tr>';
                            html += '<td>' + assignment.student_name + '</td>';
                            html += '<td>' + assignment.admission_number + '</td>';
                            html += '<td>' + assignment.grade_level + '</td>';
                            html += '<td>' + (assignment.pickup_stop || 'Not specified') + '</td>';
                            html += '<td>KES ' + parseFloat(assignment.transport_fee || 0).toFixed(2) + '</td>';
                            html += '</tr>';
                        });
                    }
                    
                    html += '</tbody></table>';
                    $('#assignments-content').html(html).show();
                } else {
                    $('#assignments-content').html('<p>Error loading assignments: ' + response.data + '</p>').show();
                }
            });
        },

        quickAssignStudent: function() {
            var studentId = $(this).data('student-id');
            var studentName = $(this).data('student-name');
            
            // Set student in individual assignment form and switch to that tab
            $('#student_select').val(studentId).trigger('change');
            $('a[href="#individual-assignment"]').click();
        }
    };

    TransportAssignment.init();
});
</script>