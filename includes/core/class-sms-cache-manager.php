<?php
/**
 * Cache Management System
 *
 * Handles caching of frequently accessed data for improved performance
 *
 * @package School_Management_System
 * @subpackage Core
 */

if (!defined('ABSPATH')) {
    exit;
}

class SMS_Cache_Manager {
    
    /**
     * Instance of this class
     */
    private static $instance = null;
    
    /**
     * Cache groups
     */
    private $cache_groups = array(
        'students' => 'sms_students',
        'classes' => 'sms_classes',
        'financial' => 'sms_financial',
        'attendance' => 'sms_attendance',
        'reports' => 'sms_reports',
        'settings' => 'sms_settings'
    );
    
    /**
     * Default cache expiration times (in seconds)
     */
    private $cache_expiration = array(
        'students' => 3600,    // 1 hour
        'classes' => 3600,     // 1 hour
        'financial' => 1800,   // 30 minutes
        'attendance' => 1800,  // 30 minutes
        'reports' => 900,      // 15 minutes
        'settings' => 7200     // 2 hours
    );
    
    /**
     * Get instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
        $this->setup_cache_groups();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('save_post', array($this, 'invalidate_post_cache'), 10, 2);
        add_action('delete_post', array($this, 'invalidate_post_cache'), 10, 2);
        add_action('updated_post_meta', array($this, 'invalidate_meta_cache'), 10, 4);
        add_action('deleted_post_meta', array($this, 'invalidate_meta_cache'), 10, 4);
        
        // Clear cache on settings update
        add_action('update_option', array($this, 'invalidate_settings_cache'), 10, 3);
        
        // Scheduled cache cleanup
        add_action('sms_cache_cleanup', array($this, 'cleanup_expired_cache'));
        
        if (!wp_next_scheduled('sms_cache_cleanup')) {
            wp_schedule_event(time(), 'hourly', 'sms_cache_cleanup');
        }
    }
    
    /**
     * Setup cache groups
     */
    private function setup_cache_groups() {
        wp_cache_add_global_groups(array_values($this->cache_groups));
    }
    
    /**
     * Set cache data
     */
    public function set($key, $data, $group = 'students', $expiration = null) {
        $cache_group = $this->cache_groups[$group] ?? $this->cache_groups['students'];
        
        if ($expiration === null) {
            $expiration = $this->cache_expiration[$group] ?? $this->cache_expiration['students'];
        }
        
        return wp_cache_set($key, $data, $cache_group, $expiration);
    }
    
    /**
     * Get cache data
     */
    public function get($key, $group = 'students') {
        $cache_group = $this->cache_groups[$group] ?? $this->cache_groups['students'];
        return wp_cache_get($key, $cache_group);
    }
    
    /**
     * Delete cache data
     */
    public function delete($key, $group = 'students') {
        $cache_group = $this->cache_groups[$group] ?? $this->cache_groups['students'];
        return wp_cache_delete($key, $cache_group);
    }
    
    /**
     * Cache student data
     */
    public function cache_student($student_id, $force_refresh = false) {
        $cache_key = 'student_' . $student_id;
        
        if (!$force_refresh) {
            $cached_data = $this->get($cache_key, 'students');
            if ($cached_data !== false) {
                return $cached_data;
            }
        }
        
        $student_data = array(
            'id' => $student_id,
            'post' => get_post($student_id),
            'meta' => get_post_meta($student_id),
            'admission_number' => get_post_meta($student_id, 'admission_number', true),
            'class_id' => get_post_meta($student_id, 'student_class', true),
            'parent_phone' => get_post_meta($student_id, 'parent_phone', true),
            'parent_email' => get_post_meta($student_id, 'parent_email', true),
            'transport_route' => get_post_meta($student_id, 'transport_route', true),
            'cached_at' => time()
        );
        
        $this->set($cache_key, $student_data, 'students');
        return $student_data;
    }
    
    /**
     * Cache class data
     */
    public function cache_class($class_id, $force_refresh = false) {
        $cache_key = 'class_' . $class_id;
        
        if (!$force_refresh) {
            $cached_data = $this->get($cache_key, 'classes');
            if ($cached_data !== false) {
                return $cached_data;
            }
        }
        
        $class_data = array(
            'id' => $class_id,
            'post' => get_post($class_id),
            'meta' => get_post_meta($class_id),
            'teacher_id' => get_post_meta($class_id, 'class_teacher', true),
            'capacity' => get_post_meta($class_id, 'class_capacity', true),
            'grade_level' => get_post_meta($class_id, 'grade_level', true),
            'students' => $this->get_class_students($class_id),
            'cached_at' => time()
        );
        
        $this->set($cache_key, $class_data, 'classes');
        return $class_data;
    }
    
    /**
     * Cache class students
     */
    public function cache_class_students($class_id, $force_refresh = false) {
        $cache_key = 'class_students_' . $class_id;
        
        if (!$force_refresh) {
            $cached_data = $this->get($cache_key, 'students');
            if ($cached_data !== false) {
                return $cached_data;
            }
        }
        
        $students = $this->get_class_students($class_id);
        $this->set($cache_key, $students, 'students');
        
        return $students;
    }
    
    /**
     * Get class students from database
     */
    private function get_class_students($class_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT p.ID, p.post_title, 
                   pm1.meta_value as admission_number,
                   pm2.meta_value as parent_phone
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            LEFT JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = 'admission_number'
            LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = 'parent_phone'
            WHERE p.post_type = 'cpt_students'
            AND p.post_status = 'publish'
            AND pm.meta_key = 'student_class'
            AND pm.meta_value = %s
            ORDER BY p.post_title
        ", $class_id));
    }
    
    /**
     * Cache financial summary
     */
    public function cache_financial_summary($date_range = null, $force_refresh = false) {
        $cache_key = 'financial_summary_' . md5(serialize($date_range));
        
        if (!$force_refresh) {
            $cached_data = $this->get($cache_key, 'financial');
            if ($cached_data !== false) {
                return $cached_data;
            }
        }
        
        $summary = $this->calculate_financial_summary($date_range);
        $this->set($cache_key, $summary, 'financial');
        
        return $summary;
    }
    
    /**
     * Calculate financial summary
     */
    private function calculate_financial_summary($date_range) {
        global $wpdb;
        
        $date_condition = '';
        if ($date_range && isset($date_range['start']) && isset($date_range['end'])) {
            $date_condition = $wpdb->prepare(
                " AND p.post_date BETWEEN %s AND %s",
                $date_range['start'],
                $date_range['end']
            );
        }
        
        // Total payments
        $payments = $wpdb->get_row("
            SELECT 
                COUNT(*) as count,
                SUM(CAST(pm.meta_value AS DECIMAL(10,2))) as total
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'cpt_transactions'
            AND p.post_status = 'publish'
            AND pm.meta_key = 'transaction_amount'
            {$date_condition}
        ");
        
        // Pending invoices
        $pending = $wpdb->get_row("
            SELECT 
                COUNT(*) as count,
                SUM(CAST(pm.meta_value AS DECIMAL(10,2))) as total
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id
            WHERE p.post_type = 'cpt_invoices'
            AND p.post_status = 'publish'
            AND pm.meta_key = 'invoice_total'
            AND pm2.meta_key = 'payment_status'
            AND pm2.meta_value = 'pending'
            {$date_condition}
        ");
        
        return array(
            'total_payments' => floatval($payments->total ?? 0),
            'payment_count' => intval($payments->count ?? 0),
            'pending_amount' => floatval($pending->total ?? 0),
            'pending_count' => intval($pending->count ?? 0),
            'cached_at' => time()
        );
    }
    
    /**
     * Cache attendance statistics
     */
    public function cache_attendance_stats($class_id, $date_range, $force_refresh = false) {
        $cache_key = 'attendance_' . $class_id . '_' . md5(serialize($date_range));
        
        if (!$force_refresh) {
            $cached_data = $this->get($cache_key, 'attendance');
            if ($cached_data !== false) {
                return $cached_data;
            }
        }
        
        $stats = $this->calculate_attendance_stats($class_id, $date_range);
        $this->set($cache_key, $stats, 'attendance');
        
        return $stats;
    }
    
    /**
     * Calculate attendance statistics
     */
    private function calculate_attendance_stats($class_id, $date_range) {
        global $wpdb;
        
        $stats = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(*) as total_records,
                SUM(CASE WHEN pm2.meta_value = 'present' THEN 1 ELSE 0 END) as present_count,
                SUM(CASE WHEN pm2.meta_value = 'absent' THEN 1 ELSE 0 END) as absent_count
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = 'attendance_class'
            INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = 'attendance_status'
            INNER JOIN {$wpdb->postmeta} pm3 ON p.ID = pm3.post_id AND pm3.meta_key = 'attendance_date'
            WHERE p.post_type = 'cpt_attendance'
            AND p.post_status = 'publish'
            AND pm1.meta_value = %s
            AND pm3.meta_value BETWEEN %s AND %s
        ", $class_id, $date_range['start'], $date_range['end']));
        
        $attendance_rate = 0;
        if ($stats->total_records > 0) {
            $attendance_rate = round(($stats->present_count / $stats->total_records) * 100, 2);
        }
        
        return array(
            'total_records' => intval($stats->total_records ?? 0),
            'present_count' => intval($stats->present_count ?? 0),
            'absent_count' => intval($stats->absent_count ?? 0),
            'attendance_rate' => $attendance_rate,
            'cached_at' => time()
        );
    }
    
    /**
     * Cache report data
     */
    public function cache_report($report_type, $params, $data) {
        $cache_key = 'report_' . $report_type . '_' . md5(serialize($params));
        return $this->set($cache_key, $data, 'reports');
    }
    
    /**
     * Get cached report data
     */
    public function get_cached_report($report_type, $params) {
        $cache_key = 'report_' . $report_type . '_' . md5(serialize($params));
        return $this->get($cache_key, 'reports');
    }
    
    /**
     * Cache settings
     */
    public function cache_settings($settings_group, $settings_data) {
        $cache_key = 'settings_' . $settings_group;
        return $this->set($cache_key, $settings_data, 'settings');
    }
    
    /**
     * Get cached settings
     */
    public function get_cached_settings($settings_group) {
        $cache_key = 'settings_' . $settings_group;
        return $this->get($cache_key, 'settings');
    }
    
    /**
     * Invalidate post-related cache
     */
    public function invalidate_post_cache($post_id, $post = null) {
        if (!$post) {
            $post = get_post($post_id);
        }
        
        if (!$post) {
            return;
        }
        
        switch ($post->post_type) {
            case 'cpt_students':
                $this->delete('student_' . $post_id, 'students');
                $class_id = get_post_meta($post_id, 'student_class', true);
                if ($class_id) {
                    $this->delete('class_students_' . $class_id, 'students');
                    $this->delete('class_' . $class_id, 'classes');
                }
                $this->invalidate_group_cache('financial');
                break;
                
            case 'cpt_classes':
                $this->delete('class_' . $post_id, 'classes');
                $this->delete('class_students_' . $post_id, 'students');
                break;
                
            case 'cpt_transactions':
            case 'cpt_invoices':
            case 'cpt_fees':
                $this->invalidate_group_cache('financial');
                break;
                
            case 'cpt_attendance':
                $class_id = get_post_meta($post_id, 'attendance_class', true);
                if ($class_id) {
                    $this->invalidate_attendance_cache($class_id);
                }
                break;
        }
        
        // Always invalidate reports cache when any SMS post is updated
        $this->invalidate_group_cache('reports');
    }
    
    /**
     * Invalidate meta-related cache
     */
    public function invalidate_meta_cache($meta_id, $post_id, $meta_key, $meta_value) {
        $post = get_post($post_id);
        if (!$post) {
            return;
        }
        
        // Invalidate specific caches based on meta key
        switch ($meta_key) {
            case 'student_class':
                $this->delete('student_' . $post_id, 'students');
                $this->delete('class_students_' . $meta_value, 'students');
                break;
                
            case 'class_teacher':
            case 'class_capacity':
                $this->delete('class_' . $post_id, 'classes');
                break;
                
            case 'transaction_amount':
            case 'payment_status':
            case 'invoice_total':
                $this->invalidate_group_cache('financial');
                break;
                
            case 'attendance_status':
            case 'attendance_date':
                $class_id = get_post_meta($post_id, 'attendance_class', true);
                if ($class_id) {
                    $this->invalidate_attendance_cache($class_id);
                }
                break;
        }
    }
    
    /**
     * Invalidate settings cache
     */
    public function invalidate_settings_cache($option_name, $old_value, $value) {
        if (strpos($option_name, 'sms_') === 0) {
            $this->invalidate_group_cache('settings');
        }
    }
    
    /**
     * Invalidate entire cache group
     */
    public function invalidate_group_cache($group) {
        // Since WordPress doesn't have a built-in way to clear cache groups,
        // we'll use a simple approach with cache keys
        $cache_group = $this->cache_groups[$group] ?? $this->cache_groups['students'];
        
        // Store a timestamp to invalidate all caches in this group
        wp_cache_set('group_invalidated_' . $group, time(), $cache_group, 86400);
    }
    
    /**
     * Check if cache group is invalidated
     */
    private function is_group_invalidated($group, $cached_at) {
        $cache_group = $this->cache_groups[$group] ?? $this->cache_groups['students'];
        $invalidated_at = wp_cache_get('group_invalidated_' . $group, $cache_group);
        
        return $invalidated_at && $invalidated_at > $cached_at;
    }
    
    /**
     * Invalidate attendance cache for a class
     */
    private function invalidate_attendance_cache($class_id) {
        // We can't easily delete all attendance caches for a class,
        // so we'll invalidate the attendance group
        $this->invalidate_group_cache('attendance');
    }
    
    /**
     * Clean up expired cache
     */
    public function cleanup_expired_cache() {
        // This is handled automatically by WordPress object cache
        // But we can log the cleanup for monitoring
        SMS_Logger::get_instance()->log_activity(
            0,
            'cache_cleanup',
            'system',
            0,
            array(
                'timestamp' => time()
            )
        );
    }
    
    /**
     * Get cache statistics
     */
    public function get_cache_stats() {
        $stats = array();
        
        foreach ($this->cache_groups as $group_name => $cache_group) {
            // Try to get some sample cache keys to check hit rates
            $sample_keys = array(
                'student_1',
                'class_1',
                'financial_summary_',
                'attendance_1_',
                'report_financial_',
                'settings_general'
            );
            
            $hits = 0;
            $total = count($sample_keys);
            
            foreach ($sample_keys as $key) {
                if (wp_cache_get($key, $cache_group) !== false) {
                    $hits++;
                }
            }
            
            $stats[$group_name] = array(
                'cache_group' => $cache_group,
                'sample_hit_rate' => $total > 0 ? round(($hits / $total) * 100, 2) : 0,
                'expiration' => $this->cache_expiration[$group_name] ?? 0
            );
        }
        
        return $stats;
    }
    
    /**
     * Warm up cache with frequently accessed data
     */
    public function warm_cache() {
        // Cache the first 10 students
        $students = get_posts(array(
            'post_type' => 'cpt_students',
            'posts_per_page' => 10,
            'post_status' => 'publish'
        ));
        
        foreach ($students as $student) {
            $this->cache_student($student->ID, true);
        }
        
        // Cache the first 5 classes
        $classes = get_posts(array(
            'post_type' => 'cpt_classes',
            'posts_per_page' => 5,
            'post_status' => 'publish'
        ));
        
        foreach ($classes as $class) {
            $this->cache_class($class->ID, true);
            $this->cache_class_students($class->ID, true);
        }
        
        // Cache financial summary for current month
        $this->cache_financial_summary(array(
            'start' => date('Y-m-01'),
            'end' => date('Y-m-t')
        ), true);
        
        SMS_Logger::get_instance()->log_activity(
            get_current_user_id(),
            'cache_warmed',
            'system',
            0,
            array(
                'students_cached' => count($students),
                'classes_cached' => count($classes)
            )
        );
    }
}