<?php
/**
 * Timetable Display and Export Manager
 *
 * @package SchoolManagementSystem
 * @subpackage Admin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class SMS_Timetable_Display_Export
 * 
 * Handles timetable display interfaces and export functionality
 */
class SMS_Timetable_Display_Export {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_ajax_sms_get_timetable_display', array($this, 'get_timetable_display'));
        add_action('wp_ajax_nopriv_sms_get_timetable_display', array($this, 'get_timetable_display'));
        add_action('wp_ajax_sms_export_timetable_pdf', array($this, 'export_timetable_pdf'));
        add_action('wp_ajax_sms_export_timetable_csv', array($this, 'export_timetable_csv'));
        add_action('wp_ajax_sms_get_role_based_timetable', array($this, 'get_role_based_timetable'));
        
        // Add shortcodes for frontend display
        add_shortcode('sms_timetable_display', array($this, 'timetable_display_shortcode'));
        add_shortcode('sms_teacher_timetable', array($this, 'teacher_timetable_shortcode'));
        add_shortcode('sms_student_timetable', array($this, 'student_timetable_shortcode'));
        
        // Add admin menu for timetable views
        add_action('admin_menu', array($this, 'add_timetable_view_menu'));
        
        // Enqueue assets for display
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_display_assets'));
    }

    /**
     * Add timetable view menu
     */
    public function add_timetable_view_menu() {
        add_submenu_page(
            'edit.php?post_type=sms_timetables',
            __('Timetable Views', 'school-management-system'),
            __('Timetable Views', 'school-management-system'),
            'read_timetables',
            'sms-timetable-views',
            array($this, 'display_timetable_views_page')
        );
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        if (is_singular() || is_page()) {
            global $post;
            if ($post && (has_shortcode($post->post_content, 'sms_timetable_display') || 
                         has_shortcode($post->post_content, 'sms_teacher_timetable') || 
                         has_shortcode($post->post_content, 'sms_student_timetable'))) {
                
                wp_enqueue_style(
                    'sms-timetable-display',
                    SMS_PLUGIN_URL . 'public/css/timetable-display.css',
                    array(),
                    SMS_VERSION
                );

                wp_enqueue_script(
                    'sms-timetable-display',
                    SMS_PLUGIN_URL . 'public/js/timetable-display.js',
                    array('jquery'),
                    SMS_VERSION,
                    true
                );

                wp_localize_script(
                    'sms-timetable-display',
                    'sms_timetable_display',
                    array(
                        'ajax_url' => admin_url('admin-ajax.php'),
                        'nonce' => wp_create_nonce('sms_timetable_display_nonce'),
                        'strings' => array(
                            'loading' => __('Loading timetable...', 'school-management-system'),
                            'error' => __('Error loading timetable', 'school-management-system'),
                            'no_data' => __('No timetable data available', 'school-management-system')
                        )
                    )
                );
            }
        }
    }

    /**
     * Enqueue admin display assets
     */
    public function enqueue_admin_display_assets($hook) {
        if ($hook === 'sms_timetables_page_sms-timetable-views') {
            wp_enqueue_style(
                'sms-timetable-views',
                SMS_PLUGIN_URL . 'admin/css/timetable-views.css',
                array(),
                SMS_VERSION
            );

            wp_enqueue_script(
                'sms-timetable-views',
                SMS_PLUGIN_URL . 'admin/js/timetable-views.js',
                array('jquery'),
                SMS_VERSION,
                true
            );
        }
    }

    /**
     * Display timetable views page
     */
    public function display_timetable_views_page() {
        if (!current_user_can('read_timetables')) {
            wp_die(__('You do not have permission to access this page.', 'school-management-system'));
        }

        // Get classes for dropdown
        $classes = get_posts(array(
            'post_type' => 'sms_classes',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC'
        ));

        // Get teachers for dropdown
        $teachers = get_users(array(
            'role__in' => array('sms_teacher', 'administrator'),
            'orderby' => 'display_name',
            'order' => 'ASC'
        ));

        // Get academic years and terms
        $academic_years = get_terms(array(
            'taxonomy' => 'sms_academic_years',
            'hide_empty' => false
        ));

        $terms = get_terms(array(
            'taxonomy' => 'sms_terms',
            'hide_empty' => false
        ));

        include SMS_PLUGIN_DIR . 'admin/partials/timetable-views.php';
    }

    /**
     * Get timetable display via AJAX
     */
    public function get_timetable_display() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_timetable_display_nonce')) {
            wp_send_json_error(__('Security check failed', 'school-management-system'));
        }

        $view_type = sanitize_text_field($_POST['view_type']); // 'class', 'teacher', 'student'
        $entity_id = intval($_POST['entity_id']);
        $academic_year = sanitize_text_field($_POST['academic_year']);
        $term = sanitize_text_field($_POST['term']);
        $display_format = sanitize_text_field($_POST['display_format']); // 'grid', 'list', 'compact'

        if (!$entity_id) {
            wp_send_json_error(__('Entity ID is required', 'school-management-system'));
        }

        try {
            $timetable_data = $this->get_timetable_data_by_type($view_type, $entity_id, $academic_year, $term);
            $formatted_display = $this->format_timetable_display($timetable_data, $display_format, $view_type);

            wp_send_json_success(array(
                'html' => $formatted_display,
                'data' => $timetable_data,
                'view_type' => $view_type,
                'display_format' => $display_format
            ));
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Get role-based timetable via AJAX
     */
    public function get_role_based_timetable() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_timetable_display_nonce')) {
            wp_send_json_error(__('Security check failed', 'school-management-system'));
        }

        $user_id = get_current_user_id();
        $user = wp_get_current_user();

        if (!$user_id) {
            wp_send_json_error(__('User not logged in', 'school-management-system'));
        }

        try {
            $timetable_data = $this->get_user_specific_timetable($user);
            $formatted_display = $this->format_timetable_display($timetable_data, 'grid', $this->get_user_view_type($user));

            wp_send_json_success(array(
                'html' => $formatted_display,
                'data' => $timetable_data,
                'user_role' => $user->roles[0] ?? 'subscriber'
            ));
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Export timetable as PDF via AJAX
     */
    public function export_timetable_pdf() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_timetable_display_nonce')) {
            wp_send_json_error(__('Security check failed', 'school-management-system'));
        }

        $timetable_id = intval($_POST['timetable_id']);
        $view_type = sanitize_text_field($_POST['view_type']);
        $entity_id = intval($_POST['entity_id']);

        if (!$timetable_id && !$entity_id) {
            wp_send_json_error(__('Timetable ID or Entity ID is required', 'school-management-system'));
        }

        try {
            $pdf_file = $this->generate_pdf_export($timetable_id, $view_type, $entity_id);
            
            wp_send_json_success(array(
                'download_url' => $pdf_file['url'],
                'filename' => $pdf_file['filename'],
                'message' => __('PDF generated successfully', 'school-management-system')
            ));
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Export timetable as CSV via AJAX
     */
    public function export_timetable_csv() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_timetable_display_nonce')) {
            wp_send_json_error(__('Security check failed', 'school-management-system'));
        }

        $timetable_id = intval($_POST['timetable_id']);
        $view_type = sanitize_text_field($_POST['view_type']);
        $entity_id = intval($_POST['entity_id']);

        if (!$timetable_id && !$entity_id) {
            wp_send_json_error(__('Timetable ID or Entity ID is required', 'school-management-system'));
        }

        try {
            $csv_file = $this->generate_csv_export($timetable_id, $view_type, $entity_id);
            
            wp_send_json_success(array(
                'download_url' => $csv_file['url'],
                'filename' => $csv_file['filename'],
                'message' => __('CSV generated successfully', 'school-management-system')
            ));
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Timetable display shortcode
     */
    public function timetable_display_shortcode($atts) {
        $atts = shortcode_atts(array(
            'class_id' => 0,
            'academic_year' => '',
            'term' => '',
            'format' => 'grid',
            'show_controls' => 'true'
        ), $atts);

        if (!$atts['class_id']) {
            return '<p>' . __('Class ID is required for timetable display', 'school-management-system') . '</p>';
        }

        ob_start();
        ?>
        <div class="sms-timetable-display" 
             data-class-id="<?php echo esc_attr($atts['class_id']); ?>"
             data-academic-year="<?php echo esc_attr($atts['academic_year']); ?>"
             data-term="<?php echo esc_attr($atts['term']); ?>"
             data-format="<?php echo esc_attr($atts['format']); ?>"
             data-show-controls="<?php echo esc_attr($atts['show_controls']); ?>">
            
            <?php if ($atts['show_controls'] === 'true') : ?>
            <div class="timetable-controls">
                <div class="format-selector">
                    <label><?php _e('View:', 'school-management-system'); ?></label>
                    <select class="format-select">
                        <option value="grid" <?php selected($atts['format'], 'grid'); ?>><?php _e('Grid View', 'school-management-system'); ?></option>
                        <option value="list" <?php selected($atts['format'], 'list'); ?>><?php _e('List View', 'school-management-system'); ?></option>
                        <option value="compact" <?php selected($atts['format'], 'compact'); ?>><?php _e('Compact View', 'school-management-system'); ?></option>
                    </select>
                </div>
                <div class="export-controls">
                    <button type="button" class="export-pdf-btn"><?php _e('Export PDF', 'school-management-system'); ?></button>
                    <button type="button" class="export-csv-btn"><?php _e('Export CSV', 'school-management-system'); ?></button>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="timetable-content">
                <div class="loading-message"><?php _e('Loading timetable...', 'school-management-system'); ?></div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Teacher timetable shortcode
     */
    public function teacher_timetable_shortcode($atts) {
        $atts = shortcode_atts(array(
            'teacher_id' => 0,
            'academic_year' => '',
            'term' => '',
            'format' => 'grid',
            'show_controls' => 'true'
        ), $atts);

        // If no teacher_id provided, use current user if they're a teacher
        if (!$atts['teacher_id']) {
            $current_user = wp_get_current_user();
            if (in_array('sms_teacher', $current_user->roles) || in_array('administrator', $current_user->roles)) {
                $atts['teacher_id'] = $current_user->ID;
            } else {
                return '<p>' . __('Teacher ID is required or you must be logged in as a teacher', 'school-management-system') . '</p>';
            }
        }

        ob_start();
        ?>
        <div class="sms-teacher-timetable-display" 
             data-teacher-id="<?php echo esc_attr($atts['teacher_id']); ?>"
             data-academic-year="<?php echo esc_attr($atts['academic_year']); ?>"
             data-term="<?php echo esc_attr($atts['term']); ?>"
             data-format="<?php echo esc_attr($atts['format']); ?>"
             data-show-controls="<?php echo esc_attr($atts['show_controls']); ?>">
            
            <?php if ($atts['show_controls'] === 'true') : ?>
            <div class="timetable-controls">
                <div class="teacher-info">
                    <h3><?php echo esc_html(get_userdata($atts['teacher_id'])->display_name); ?></h3>
                </div>
                <div class="format-selector">
                    <label><?php _e('View:', 'school-management-system'); ?></label>
                    <select class="format-select">
                        <option value="grid" <?php selected($atts['format'], 'grid'); ?>><?php _e('Grid View', 'school-management-system'); ?></option>
                        <option value="list" <?php selected($atts['format'], 'list'); ?>><?php _e('List View', 'school-management-system'); ?></option>
                        <option value="compact" <?php selected($atts['format'], 'compact'); ?>><?php _e('Compact View', 'school-management-system'); ?></option>
                    </select>
                </div>
                <div class="export-controls">
                    <button type="button" class="export-pdf-btn"><?php _e('Export PDF', 'school-management-system'); ?></button>
                    <button type="button" class="export-csv-btn"><?php _e('Export CSV', 'school-management-system'); ?></button>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="timetable-content">
                <div class="loading-message"><?php _e('Loading teacher timetable...', 'school-management-system'); ?></div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Student timetable shortcode
     */
    public function student_timetable_shortcode($atts) {
        $atts = shortcode_atts(array(
            'student_id' => 0,
            'class_id' => 0,
            'academic_year' => '',
            'term' => '',
            'format' => 'grid',
            'show_controls' => 'true'
        ), $atts);

        // If no student_id or class_id provided, try to get from current user
        if (!$atts['student_id'] && !$atts['class_id']) {
            $current_user = wp_get_current_user();
            if (in_array('sms_student', $current_user->roles)) {
                // Get student's class
                $student_class = get_user_meta($current_user->ID, 'student_class', true);
                if ($student_class) {
                    $atts['class_id'] = $student_class;
                }
            } elseif (in_array('sms_parent', $current_user->roles)) {
                // Get parent's children classes
                $children = get_user_meta($current_user->ID, 'children', true);
                if ($children && is_array($children) && !empty($children)) {
                    $first_child = $children[0];
                    $child_class = get_user_meta($first_child, 'student_class', true);
                    if ($child_class) {
                        $atts['class_id'] = $child_class;
                    }
                }
            }
        }

        if (!$atts['class_id']) {
            return '<p>' . __('Class ID is required for student timetable display', 'school-management-system') . '</p>';
        }

        ob_start();
        ?>
        <div class="sms-student-timetable-display" 
             data-student-id="<?php echo esc_attr($atts['student_id']); ?>"
             data-class-id="<?php echo esc_attr($atts['class_id']); ?>"
             data-academic-year="<?php echo esc_attr($atts['academic_year']); ?>"
             data-term="<?php echo esc_attr($atts['term']); ?>"
             data-format="<?php echo esc_attr($atts['format']); ?>"
             data-show-controls="<?php echo esc_attr($atts['show_controls']); ?>">
            
            <?php if ($atts['show_controls'] === 'true') : ?>
            <div class="timetable-controls">
                <div class="class-info">
                    <h3><?php echo esc_html(get_the_title($atts['class_id'])); ?></h3>
                </div>
                <div class="format-selector">
                    <label><?php _e('View:', 'school-management-system'); ?></label>
                    <select class="format-select">
                        <option value="grid" <?php selected($atts['format'], 'grid'); ?>><?php _e('Grid View', 'school-management-system'); ?></option>
                        <option value="list" <?php selected($atts['format'], 'list'); ?>><?php _e('List View', 'school-management-system'); ?></option>
                        <option value="compact" <?php selected($atts['format'], 'compact'); ?>><?php _e('Compact View', 'school-management-system'); ?></option>
                    </select>
                </div>
                <div class="export-controls">
                    <button type="button" class="export-pdf-btn"><?php _e('Export PDF', 'school-management-system'); ?></button>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="timetable-content">
                <div class="loading-message"><?php _e('Loading class timetable...', 'school-management-system'); ?></div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get timetable data by type
     */
    private function get_timetable_data_by_type($view_type, $entity_id, $academic_year = '', $term = '') {
        switch ($view_type) {
            case 'class':
                return $this->get_class_timetable_data($entity_id, $academic_year, $term);
            case 'teacher':
                return $this->get_teacher_timetable_data($entity_id, $academic_year, $term);
            case 'student':
                return $this->get_student_timetable_data($entity_id, $academic_year, $term);
            default:
                throw new Exception(__('Invalid view type', 'school-management-system'));
        }
    }

    /**
     * Get class timetable data
     */
    private function get_class_timetable_data($class_id, $academic_year = '', $term = '') {
        $args = array(
            'post_type' => 'sms_timetables',
            'posts_per_page' => 1,
            'meta_query' => array(
                array(
                    'key' => 'timetable_class',
                    'value' => $class_id,
                    'compare' => '='
                ),
                array(
                    'key' => 'timetable_status',
                    'value' => 'active',
                    'compare' => '='
                )
            )
        );

        // Add taxonomy queries if specified
        if ($academic_year || $term) {
            $args['tax_query'] = array();
            
            if ($academic_year) {
                $args['tax_query'][] = array(
                    'taxonomy' => 'sms_academic_years',
                    'field' => 'slug',
                    'terms' => $academic_year
                );
            }
            
            if ($term) {
                $args['tax_query'][] = array(
                    'taxonomy' => 'sms_terms',
                    'field' => 'slug',
                    'terms' => $term
                );
            }
        }

        $timetables = get_posts($args);
        
        if (empty($timetables)) {
            return array(
                'type' => 'class',
                'entity_id' => $class_id,
                'entity_name' => get_the_title($class_id),
                'time_slots' => array(),
                'message' => __('No active timetable found for this class', 'school-management-system')
            );
        }

        $timetable = $timetables[0];
        $time_slots = get_field('time_slots', $timetable->ID) ?: array();

        return array(
            'type' => 'class',
            'entity_id' => $class_id,
            'entity_name' => get_the_title($class_id),
            'timetable_id' => $timetable->ID,
            'timetable_title' => $timetable->post_title,
            'time_slots' => $this->process_time_slots_for_display($time_slots),
            'academic_year' => get_field('academic_year', $timetable->ID),
            'term' => get_field('term', $timetable->ID)
        );
    }

    /**
     * Get teacher timetable data
     */
    private function get_teacher_timetable_data($teacher_id, $academic_year = '', $term = '') {
        $args = array(
            'post_type' => 'sms_timetables',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => 'timetable_status',
                    'value' => 'active',
                    'compare' => '='
                )
            )
        );

        // Add taxonomy queries if specified
        if ($academic_year || $term) {
            $args['tax_query'] = array();
            
            if ($academic_year) {
                $args['tax_query'][] = array(
                    'taxonomy' => 'sms_academic_years',
                    'field' => 'slug',
                    'terms' => $academic_year
                );
            }
            
            if ($term) {
                $args['tax_query'][] = array(
                    'taxonomy' => 'sms_terms',
                    'field' => 'slug',
                    'terms' => $term
                );
            }
        }

        $timetables = get_posts($args);
        $teacher_slots = array();

        foreach ($timetables as $timetable) {
            $time_slots = get_field('time_slots', $timetable->ID) ?: array();
            $class_name = get_the_title(get_field('timetable_class', $timetable->ID));

            foreach ($time_slots as $slot) {
                if ($slot['teacher'] == $teacher_id) {
                    $slot['class_name'] = $class_name;
                    $slot['timetable_id'] = $timetable->ID;
                    $teacher_slots[] = $slot;
                }
            }
        }

        return array(
            'type' => 'teacher',
            'entity_id' => $teacher_id,
            'entity_name' => get_userdata($teacher_id)->display_name,
            'time_slots' => $this->process_time_slots_for_display($teacher_slots),
            'total_classes' => count($teacher_slots)
        );
    }

    /**
     * Get student timetable data (same as class timetable)
     */
    private function get_student_timetable_data($student_id, $academic_year = '', $term = '') {
        // Get student's class
        $student_class = get_user_meta($student_id, 'student_class', true);
        
        if (!$student_class) {
            return array(
                'type' => 'student',
                'entity_id' => $student_id,
                'entity_name' => get_userdata($student_id)->display_name,
                'time_slots' => array(),
                'message' => __('Student is not assigned to any class', 'school-management-system')
            );
        }

        $class_data = $this->get_class_timetable_data($student_class, $academic_year, $term);
        $class_data['type'] = 'student';
        $class_data['student_id'] = $student_id;
        $class_data['student_name'] = get_userdata($student_id)->display_name;

        return $class_data;
    }

    /**
     * Get user-specific timetable based on role
     */
    private function get_user_specific_timetable($user) {
        $user_roles = $user->roles;
        
        if (in_array('sms_teacher', $user_roles) || in_array('administrator', $user_roles)) {
            return $this->get_teacher_timetable_data($user->ID);
        } elseif (in_array('sms_student', $user_roles)) {
            return $this->get_student_timetable_data($user->ID);
        } elseif (in_array('sms_parent', $user_roles)) {
            // Get first child's timetable
            $children = get_user_meta($user->ID, 'children', true);
            if ($children && is_array($children) && !empty($children)) {
                return $this->get_student_timetable_data($children[0]);
            }
        }

        return array(
            'type' => 'none',
            'message' => __('No timetable available for your role', 'school-management-system')
        );
    }

    /**
     * Get user view type based on role
     */
    private function get_user_view_type($user) {
        $user_roles = $user->roles;
        
        if (in_array('sms_teacher', $user_roles) || in_array('administrator', $user_roles)) {
            return 'teacher';
        } elseif (in_array('sms_student', $user_roles)) {
            return 'student';
        } elseif (in_array('sms_parent', $user_roles)) {
            return 'parent';
        }

        return 'guest';
    }

    /**
     * Process time slots for display
     */
    private function process_time_slots_for_display($time_slots) {
        $processed_slots = array();

        foreach ($time_slots as $slot) {
            $processed_slot = array(
                'day' => $slot['day'],
                'start_time' => $slot['start_time'],
                'end_time' => $slot['end_time'],
                'subject_name' => '',
                'teacher_name' => '',
                'room' => $slot['room'] ?? '',
                'slot_type' => $slot['slot_type'] ?? 'lesson',
                'class_name' => $slot['class_name'] ?? ''
            );

            // Get subject name
            if (!empty($slot['subject'])) {
                $subject = get_term($slot['subject']);
                if ($subject && !is_wp_error($subject)) {
                    $processed_slot['subject_name'] = $subject->name;
                }
            }

            // Get teacher name
            if (!empty($slot['teacher'])) {
                $teacher = get_userdata($slot['teacher']);
                if ($teacher) {
                    $processed_slot['teacher_name'] = $teacher->display_name;
                }
            }

            $processed_slots[] = $processed_slot;
        }

        return $processed_slots;
    }

    /**
     * Format timetable display
     */
    private function format_timetable_display($timetable_data, $display_format, $view_type) {
        if (empty($timetable_data['time_slots'])) {
            return '<div class="no-timetable-data">' . 
                   ($timetable_data['message'] ?? __('No timetable data available', 'school-management-system')) . 
                   '</div>';
        }

        switch ($display_format) {
            case 'grid':
                return $this->format_grid_display($timetable_data, $view_type);
            case 'list':
                return $this->format_list_display($timetable_data, $view_type);
            case 'compact':
                return $this->format_compact_display($timetable_data, $view_type);
            default:
                return $this->format_grid_display($timetable_data, $view_type);
        }
    }

    /**
     * Format grid display
     */
    private function format_grid_display($timetable_data, $view_type) {
        $time_slots = $timetable_data['time_slots'];
        $days = array('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday');
        
        // Group slots by day and time
        $grid_data = array();
        $time_ranges = array();
        
        foreach ($time_slots as $slot) {
            $time_key = $slot['start_time'] . '-' . $slot['end_time'];
            $grid_data[$slot['day']][$time_key] = $slot;
            $time_ranges[$time_key] = array(
                'start' => $slot['start_time'],
                'end' => $slot['end_time'],
                'display' => date('g:i A', strtotime($slot['start_time'])) . ' - ' . date('g:i A', strtotime($slot['end_time']))
            );
        }
        
        // Sort time ranges
        uksort($time_ranges, function($a, $b) {
            return strcmp(explode('-', $a)[0], explode('-', $b)[0]);
        });

        ob_start();
        ?>
        <div class="timetable-grid-display">
            <table class="timetable-grid-table">
                <thead>
                    <tr>
                        <th class="time-column"><?php _e('Time', 'school-management-system'); ?></th>
                        <?php foreach ($days as $day) : ?>
                            <th class="day-column"><?php echo esc_html(ucfirst($day)); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($time_ranges as $time_key => $time_range) : ?>
                        <tr class="time-row">
                            <td class="time-cell">
                                <div class="time-display"><?php echo esc_html($time_range['display']); ?></div>
                            </td>
                            <?php foreach ($days as $day) : ?>
                                <td class="day-cell <?php echo isset($grid_data[$day][$time_key]) ? 'has-slot' : 'empty-slot'; ?>">
                                    <?php if (isset($grid_data[$day][$time_key])) : 
                                        $slot = $grid_data[$day][$time_key];
                                    ?>
                                        <div class="slot-content slot-type-<?php echo esc_attr($slot['slot_type']); ?>">
                                            <?php if ($slot['subject_name']) : ?>
                                                <div class="slot-subject"><?php echo esc_html($slot['subject_name']); ?></div>
                                            <?php endif; ?>
                                            
                                            <?php if ($view_type === 'teacher' && $slot['class_name']) : ?>
                                                <div class="slot-class"><?php echo esc_html($slot['class_name']); ?></div>
                                            <?php elseif ($view_type !== 'teacher' && $slot['teacher_name']) : ?>
                                                <div class="slot-teacher"><?php echo esc_html($slot['teacher_name']); ?></div>
                                            <?php endif; ?>
                                            
                                            <?php if ($slot['room']) : ?>
                                                <div class="slot-room"><?php echo esc_html($slot['room']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Format list display
     */
    private function format_list_display($timetable_data, $view_type) {
        $time_slots = $timetable_data['time_slots'];
        
        // Group by day
        $days_data = array();
        foreach ($time_slots as $slot) {
            $days_data[$slot['day']][] = $slot;
        }
        
        // Sort each day's slots by time
        foreach ($days_data as $day => &$slots) {
            usort($slots, function($a, $b) {
                return strcmp($a['start_time'], $b['start_time']);
            });
        }

        ob_start();
        ?>
        <div class="timetable-list-display">
            <?php foreach ($days_data as $day => $slots) : ?>
                <div class="day-section">
                    <h3 class="day-header"><?php echo esc_html(ucfirst($day)); ?></h3>
                    <div class="day-slots">
                        <?php foreach ($slots as $slot) : ?>
                            <div class="slot-item slot-type-<?php echo esc_attr($slot['slot_type']); ?>">
                                <div class="slot-time">
                                    <?php echo esc_html(date('g:i A', strtotime($slot['start_time'])) . ' - ' . date('g:i A', strtotime($slot['end_time']))); ?>
                                </div>
                                <div class="slot-details">
                                    <?php if ($slot['subject_name']) : ?>
                                        <div class="slot-subject"><?php echo esc_html($slot['subject_name']); ?></div>
                                    <?php endif; ?>
                                    
                                    <?php if ($view_type === 'teacher' && $slot['class_name']) : ?>
                                        <div class="slot-class"><?php echo esc_html($slot['class_name']); ?></div>
                                    <?php elseif ($view_type !== 'teacher' && $slot['teacher_name']) : ?>
                                        <div class="slot-teacher"><?php echo esc_html($slot['teacher_name']); ?></div>
                                    <?php endif; ?>
                                    
                                    <?php if ($slot['room']) : ?>
                                        <div class="slot-room"><?php echo esc_html($slot['room']); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Format compact display
     */
    private function format_compact_display($timetable_data, $view_type) {
        $time_slots = $timetable_data['time_slots'];
        
        // Sort by day and time
        usort($time_slots, function($a, $b) {
            $day_order = array('monday' => 1, 'tuesday' => 2, 'wednesday' => 3, 'thursday' => 4, 'friday' => 5, 'saturday' => 6);
            $day_compare = ($day_order[$a['day']] ?? 7) - ($day_order[$b['day']] ?? 7);
            
            if ($day_compare !== 0) {
                return $day_compare;
            }
            
            return strcmp($a['start_time'], $b['start_time']);
        });

        ob_start();
        ?>
        <div class="timetable-compact-display">
            <?php foreach ($time_slots as $slot) : ?>
                <div class="compact-slot-item slot-type-<?php echo esc_attr($slot['slot_type']); ?>">
                    <div class="compact-slot-header">
                        <span class="compact-day"><?php echo esc_html(substr(ucfirst($slot['day']), 0, 3)); ?></span>
                        <span class="compact-time">
                            <?php echo esc_html(date('g:i', strtotime($slot['start_time'])) . '-' . date('g:i', strtotime($slot['end_time']))); ?>
                        </span>
                    </div>
                    <div class="compact-slot-content">
                        <?php if ($slot['subject_name']) : ?>
                            <span class="compact-subject"><?php echo esc_html($slot['subject_name']); ?></span>
                        <?php endif; ?>
                        
                        <?php if ($view_type === 'teacher' && $slot['class_name']) : ?>
                            <span class="compact-class"><?php echo esc_html($slot['class_name']); ?></span>
                        <?php elseif ($view_type !== 'teacher' && $slot['teacher_name']) : ?>
                            <span class="compact-teacher"><?php echo esc_html($slot['teacher_name']); ?></span>
                        <?php endif; ?>
                        
                        <?php if ($slot['room']) : ?>
                            <span class="compact-room"><?php echo esc_html($slot['room']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Generate PDF export
     */
    private function generate_pdf_export($timetable_id, $view_type, $entity_id) {
        // This would require a PDF library like TCPDF or DOMPDF
        // For now, return a placeholder
        throw new Exception(__('PDF export functionality requires additional setup', 'school-management-system'));
    }

    /**
     * Generate CSV export
     */
    private function generate_csv_export($timetable_id, $view_type, $entity_id) {
        $upload_dir = wp_upload_dir();
        $filename = 'timetable_export_' . date('Y-m-d_H-i-s') . '.csv';
        $filepath = $upload_dir['path'] . '/' . $filename;

        // Get timetable data
        if ($timetable_id) {
            $time_slots = get_field('time_slots', $timetable_id);
            $entity_name = get_the_title($timetable_id);
        } else {
            $timetable_data = $this->get_timetable_data_by_type($view_type, $entity_id);
            $time_slots = $timetable_data['time_slots'];
            $entity_name = $timetable_data['entity_name'];
        }

        $file = fopen($filepath, 'w');
        
        // CSV headers
        fputcsv($file, array(
            'Day', 'Start Time', 'End Time', 'Subject', 'Teacher', 'Room', 'Type'
        ));

        foreach ($time_slots as $slot) {
            fputcsv($file, array(
                ucfirst($slot['day']),
                $slot['start_time'],
                $slot['end_time'],
                $slot['subject_name'],
                $slot['teacher_name'],
                $slot['room'],
                $slot['slot_type']
            ));
        }

        fclose($file);

        return array(
            'url' => $upload_dir['url'] . '/' . $filename,
            'filename' => $filename,
            'path' => $filepath
        );
    }
}

// Initialize the display and export manager
new SMS_Timetable_Display_Export();