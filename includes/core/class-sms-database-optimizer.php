<?php
/**
 * Database Performance Optimizer
 *
 * Handles database indexing, query optimization, and caching for improved performance
 *
 * @package School_Management_System
 * @subpackage Core
 */

if (!defined('ABSPATH')) {
    exit;
}

class SMS_Database_Optimizer {
    
    /**
     * Instance of this class
     */
    private static $instance = null;
    
    /**
     * Cache group for SMS data
     */
    private $cache_group = 'sms_data';
    
    /**
     * Default cache expiration (1 hour)
     */
    private $cache_expiration = 3600;
    
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
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('init', array($this, 'setup_object_cache'));
        add_action('save_post', array($this, 'clear_related_cache'), 10, 2);
        add_action('delete_post', array($this, 'clear_related_cache'), 10, 2);
        add_action('updated_post_meta', array($this, 'clear_post_cache'), 10, 4);
        add_action('deleted_post_meta', array($this, 'clear_post_cache'), 10, 4);
        
        // Clear cache on user updates
        add_action('profile_update', array($this, 'clear_user_cache'), 10, 2);
        add_action('user_register', array($this, 'clear_user_cache'), 10, 1);
        
        // Optimize queries
        add_filter('posts_clauses', array($this, 'optimize_student_queries'), 10, 2);
        add_filter('posts_clauses', array($this, 'optimize_financial_queries'), 10, 2);
    }
    
    /**
     * Setup object cache groups
     */
    public function setup_object_cache() {
        wp_cache_add_global_groups(array($this->cache_group));
    }
    
    /**
     * Create database indexes for better performance
     */
    public function create_indexes() {
        global $wpdb;
        
        $indexes = array(
            // Post meta indexes for frequently queried fields
            array(
                'table' => $wpdb->postmeta,
                'name' => 'idx_sms_admission_number',
                'columns' => array('meta_key', 'meta_value(20)'),
                'condition' => "meta_key = 'admission_number'"
            ),
            array(
                'table' => $wpdb->postmeta,
                'name' => 'idx_sms_student_class',
                'columns' => array('meta_key', 'meta_value'),
                'condition' => "meta_key = 'student_class'"
            ),
            array(
                'table' => $wpdb->postmeta,
                'name' => 'idx_sms_parent_phone',
                'columns' => array('meta_key', 'meta_value(20)'),
                'condition' => "meta_key = 'parent_phone'"
            ),
            array(
                'table' => $wpdb->postmeta,
                'name' => 'idx_sms_fee_amount',
                'columns' => array('meta_key', 'meta_value'),
                'condition' => "meta_key = 'fee_amount'"
            ),
            array(
                'table' => $wpdb->postmeta,
                'name' => 'idx_sms_payment_status',
                'columns' => array('meta_key', 'meta_value'),
                'condition' => "meta_key = 'payment_status'"
            ),
            array(
                'table' => $wpdb->postmeta,
                'name' => 'idx_sms_attendance_date',
                'columns' => array('meta_key', 'meta_value'),
                'condition' => "meta_key = 'attendance_date'"
            ),
            array(
                'table' => $wpdb->postmeta,
                'name' => 'idx_sms_invoice_student',
                'columns' => array('meta_key', 'meta_value'),
                'condition' => "meta_key = 'invoice_student_id'"
            ),
            array(
                'table' => $wpdb->postmeta,
                'name' => 'idx_sms_transaction_reference',
                'columns' => array('meta_key', 'meta_value(50)'),
                'condition' => "meta_key = 'transaction_reference'"
            ),
            
            // Posts table indexes
            array(
                'table' => $wpdb->posts,
                'name' => 'idx_sms_post_type_status_date',
                'columns' => array('post_type', 'post_status', 'post_date'),
                'condition' => "post_type IN ('cpt_students', 'cpt_classes', 'cpt_fees', 'cpt_invoices', 'cpt_transactions', 'cpt_attendance')"
            ),
            
            // User meta indexes
            array(
                'table' => $wpdb->usermeta,
                'name' => 'idx_sms_user_role',
                'columns' => array('meta_key', 'meta_value(20)'),
                'condition' => "meta_key = 'wp_capabilities'"
            ),
            
            // Term relationships for taxonomies
            array(
                'table' => $wpdb->term_relationships,
                'name' => 'idx_sms_term_object',
                'columns' => array('term_taxonomy_id', 'object_id'),
                'condition' => null
            )
        );
        
        foreach ($indexes as $index) {
            $this->create_index($index);
        }
        
        // Log index creation
        SMS_Logger::get_instance()->log_activity(
            get_current_user_id(),
            'database_indexes_created',
            'system',
            0,
            array(
                'index_count' => count($indexes)
            )
        );
    }
    
    /**
     * Create individual database index
     */
    private function create_index($index_config) {
        global $wpdb;
        
        $table = $index_config['table'];
        $index_name = $index_config['name'];
        $columns = implode(', ', $index_config['columns']);
        
        // Check if index already exists
        $existing_indexes = $wpdb->get_results("SHOW INDEX FROM {$table} WHERE Key_name = '{$index_name}'");
        
        if (!empty($existing_indexes)) {
            return; // Index already exists
        }
        
        // Create the index
        $sql = "CREATE INDEX {$index_name} ON {$table} ({$columns})";
        
        if ($index_config['condition']) {
            $sql .= " WHERE {$index_config['condition']}";
        }
        
        $wpdb->query($sql);
    }
    
    /**
     * Optimize student-related queries
     */
    public function optimize_student_queries($clauses, $query) {
        global $wpdb;
        
        if (!$query->is_main_query() || $query->get('post_type') !== 'cpt_students') {
            return $clauses;
        }
        
        // Add index hints for student queries
        if (strpos($clauses['join'], $wpdb->postmeta) !== false) {
            $clauses['join'] = str_replace(
                "INNER JOIN {$wpdb->postmeta}",
                "INNER JOIN {$wpdb->postmeta} USE INDEX (idx_sms_admission_number, idx_sms_student_class)",
                $clauses['join']
            );
        }
        
        return $clauses;
    }
    
    /**
     * Optimize financial queries
     */
    public function optimize_financial_queries($clauses, $query) {
        global $wpdb;
        
        $financial_post_types = array('cpt_fees', 'cpt_invoices', 'cpt_transactions');
        
        if (!$query->is_main_query() || !in_array($query->get('post_type'), $financial_post_types)) {
            return $clauses;
        }
        
        // Add index hints for financial queries
        if (strpos($clauses['join'], $wpdb->postmeta) !== false) {
            $clauses['join'] = str_replace(
                "INNER JOIN {$wpdb->postmeta}",
                "INNER JOIN {$wpdb->postmeta} USE INDEX (idx_sms_fee_amount, idx_sms_payment_status, idx_sms_invoice_student)",
                $clauses['join']
            );
        }
        
        return $clauses;
    }
    
    /**
     * Cache frequently accessed data
     */
    public function cache_set($key, $data, $expiration = null) {
        if ($expiration === null) {
            $expiration = $this->cache_expiration;
        }
        
        return wp_cache_set($key, $data, $this->cache_group, $expiration);
    }
    
    /**
     * Get cached data
     */
    public function cache_get($key) {
        return wp_cache_get($key, $this->cache_group);
    }
    
    /**
     * Delete cached data
     */
    public function cache_delete($key) {
        return wp_cache_delete($key, $this->cache_group);
    }
    
    /**
     * Get student data with caching
     */
    public function get_student_data($student_id, $force_refresh = false) {
        $cache_key = 'student_data_' . $student_id;
        
        if (!$force_refresh) {
            $cached_data = $this->cache_get($cache_key);
            if ($cached_data !== false) {
                return $cached_data;
            }
        }
        
        // Fetch student data
        $student_data = array(
            'post' => get_post($student_id),
            'meta' => get_post_meta($student_id),
            'class' => get_post_meta($student_id, 'student_class', true),
            'admission_number' => get_post_meta($student_id, 'admission_number', true),
            'parent_phone' => get_post_meta($student_id, 'parent_phone', true),
            'parent_email' => get_post_meta($student_id, 'parent_email', true)
        );
        
        // Cache the data
        $this->cache_set($cache_key, $student_data);
        
        return $student_data;
    }
    
    /**
     * Get class students with caching
     */
    public function get_class_students($class_id, $force_refresh = false) {
        $cache_key = 'class_students_' . $class_id;
        
        if (!$force_refresh) {
            $cached_data = $this->cache_get($cache_key);
            if ($cached_data !== false) {
                return $cached_data;
            }
        }
        
        // Optimized query for class students
        global $wpdb;
        
        $students = $wpdb->get_results($wpdb->prepare("
            SELECT p.ID, p.post_title, pm1.meta_value as admission_number, pm2.meta_value as parent_phone
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
        
        // Cache the data
        $this->cache_set($cache_key, $students);
        
        return $students;
    }
    
    /**
     * Get financial summary with caching
     */
    public function get_financial_summary($date_range = null, $force_refresh = false) {
        $cache_key = 'financial_summary_' . md5(serialize($date_range));
        
        if (!$force_refresh) {
            $cached_data = $this->cache_get($cache_key);
            if ($cached_data !== false) {
                return $cached_data;
            }
        }
        
        global $wpdb;
        
        $date_condition = '';
        if ($date_range && isset($date_range['start']) && isset($date_range['end'])) {
            $date_condition = $wpdb->prepare(
                " AND p.post_date BETWEEN %s AND %s",
                $date_range['start'],
                $date_range['end']
            );
        }
        
        // Get payment totals
        $payment_totals = $wpdb->get_row("
            SELECT 
                COUNT(*) as total_transactions,
                SUM(CAST(pm.meta_value AS DECIMAL(10,2))) as total_amount
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'cpt_transactions'
            AND p.post_status = 'publish'
            AND pm.meta_key = 'transaction_amount'
            {$date_condition}
        ");
        
        // Get pending invoices
        $pending_invoices = $wpdb->get_row("
            SELECT 
                COUNT(*) as pending_count,
                SUM(CAST(pm.meta_value AS DECIMAL(10,2))) as pending_amount
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
        
        $summary = array(
            'total_transactions' => intval($payment_totals->total_transactions ?? 0),
            'total_amount' => floatval($payment_totals->total_amount ?? 0),
            'pending_invoices' => intval($pending_invoices->pending_count ?? 0),
            'pending_amount' => floatval($pending_invoices->pending_amount ?? 0)
        );
        
        // Cache for 30 minutes
        $this->cache_set($cache_key, $summary, 1800);
        
        return $summary;
    }
    
    /**
     * Get attendance statistics with caching
     */
    public function get_attendance_stats($class_id, $date_range, $force_refresh = false) {
        $cache_key = 'attendance_stats_' . $class_id . '_' . md5(serialize($date_range));
        
        if (!$force_refresh) {
            $cached_data = $this->cache_get($cache_key);
            if ($cached_data !== false) {
                return $cached_data;
            }
        }
        
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
        
        $attendance_stats = array(
            'total_records' => intval($stats->total_records ?? 0),
            'present_count' => intval($stats->present_count ?? 0),
            'absent_count' => intval($stats->absent_count ?? 0),
            'attendance_rate' => 0
        );
        
        if ($attendance_stats['total_records'] > 0) {
            $attendance_stats['attendance_rate'] = round(
                ($attendance_stats['present_count'] / $attendance_stats['total_records']) * 100,
                2
            );
        }
        
        // Cache for 1 hour
        $this->cache_set($cache_key, $attendance_stats);
        
        return $attendance_stats;
    }
    
    /**
     * Clear related cache when posts are updated
     */
    public function clear_related_cache($post_id, $post = null) {
        if (!$post) {
            $post = get_post($post_id);
        }
        
        if (!$post) {
            return;
        }
        
        switch ($post->post_type) {
            case 'cpt_students':
                $this->cache_delete('student_data_' . $post_id);
                $class_id = get_post_meta($post_id, 'student_class', true);
                if ($class_id) {
                    $this->cache_delete('class_students_' . $class_id);
                }
                break;
                
            case 'cpt_transactions':
            case 'cpt_invoices':
                // Clear financial summaries
                $this->clear_cache_by_pattern('financial_summary_');
                break;
                
            case 'cpt_attendance':
                $class_id = get_post_meta($post_id, 'attendance_class', true);
                if ($class_id) {
                    $this->clear_cache_by_pattern('attendance_stats_' . $class_id . '_');
                }
                break;
        }
    }
    
    /**
     * Clear post-specific cache when meta is updated
     */
    public function clear_post_cache($meta_id, $post_id, $meta_key, $meta_value) {
        $post = get_post($post_id);
        if (!$post) {
            return;
        }
        
        // Clear student data cache when student meta is updated
        if ($post->post_type === 'cpt_students') {
            $this->cache_delete('student_data_' . $post_id);
            
            // If class changed, clear both old and new class caches
            if ($meta_key === 'student_class') {
                $this->cache_delete('class_students_' . $meta_value);
                // Also clear the old class cache (we don't know the old value here)
                $this->clear_cache_by_pattern('class_students_');
            }
        }
    }
    
    /**
     * Clear user-related cache
     */
    public function clear_user_cache($user_id, $old_user_data = null) {
        // Clear any user-specific caches
        $this->cache_delete('user_students_' . $user_id);
        $this->cache_delete('user_classes_' . $user_id);
    }
    
    /**
     * Clear cache by pattern (simplified version)
     */
    private function clear_cache_by_pattern($pattern) {
        // This is a simplified implementation
        // In a production environment, you might want to use Redis or Memcached
        // which support pattern-based deletion
        
        // For now, we'll just clear some common cache keys
        $common_keys = array(
            'financial_summary_',
            'class_students_',
            'attendance_stats_'
        );
        
        foreach ($common_keys as $key_prefix) {
            if (strpos($pattern, $key_prefix) === 0) {
                // Clear up to 100 possible variations
                for ($i = 1; $i <= 100; $i++) {
                    $this->cache_delete($key_prefix . $i);
                }
            }
        }
    }
    
    /**
     * Get database performance statistics
     */
    public function get_performance_stats() {
        global $wpdb;
        
        $stats = array();
        
        // Get slow query log status
        $slow_query_log = $wpdb->get_var("SHOW VARIABLES LIKE 'slow_query_log'");
        $stats['slow_query_log_enabled'] = ($slow_query_log === 'ON');
        
        // Get query cache statistics
        $query_cache_size = $wpdb->get_var("SHOW VARIABLES LIKE 'query_cache_size'");
        $stats['query_cache_size'] = intval($query_cache_size);
        
        // Get table sizes for SMS tables
        $sms_tables = array(
            $wpdb->prefix . 'sms_activity_log',
            $wpdb->prefix . 'sms_security_log',
            $wpdb->prefix . 'sms_user_sessions'
        );
        
        $stats['table_sizes'] = array();
        foreach ($sms_tables as $table) {
            $size = $wpdb->get_var($wpdb->prepare(
                "SELECT ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'MB' 
                 FROM information_schema.TABLES 
                 WHERE table_schema = %s AND table_name = %s",
                DB_NAME,
                $table
            ));
            $stats['table_sizes'][$table] = floatval($size);
        }
        
        // Get index usage statistics
        $stats['index_usage'] = $this->get_index_usage_stats();
        
        return $stats;
    }
    
    /**
     * Get index usage statistics
     */
    private function get_index_usage_stats() {
        global $wpdb;
        
        $indexes = $wpdb->get_results("
            SELECT 
                TABLE_NAME,
                INDEX_NAME,
                CARDINALITY
            FROM information_schema.STATISTICS 
            WHERE TABLE_SCHEMA = '" . DB_NAME . "'
            AND INDEX_NAME LIKE 'idx_sms_%'
            ORDER BY TABLE_NAME, INDEX_NAME
        ");
        
        $usage_stats = array();
        foreach ($indexes as $index) {
            $usage_stats[] = array(
                'table' => $index->TABLE_NAME,
                'index' => $index->INDEX_NAME,
                'cardinality' => intval($index->CARDINALITY)
            );
        }
        
        return $usage_stats;
    }
    
    /**
     * Optimize database tables
     */
    public function optimize_tables() {
        global $wpdb;
        
        $tables_to_optimize = array(
            $wpdb->posts,
            $wpdb->postmeta,
            $wpdb->users,
            $wpdb->usermeta,
            $wpdb->prefix . 'sms_activity_log',
            $wpdb->prefix . 'sms_security_log',
            $wpdb->prefix . 'sms_user_sessions'
        );
        
        $optimized_tables = array();
        
        foreach ($tables_to_optimize as $table) {
            $result = $wpdb->query("OPTIMIZE TABLE {$table}");
            $optimized_tables[$table] = $result !== false;
        }
        
        // Log optimization
        SMS_Logger::get_instance()->log_activity(
            get_current_user_id(),
            'database_optimized',
            'system',
            0,
            array(
                'tables_optimized' => array_keys(array_filter($optimized_tables))
            )
        );
        
        return $optimized_tables;
    }
}