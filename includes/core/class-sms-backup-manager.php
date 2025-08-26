<?php
/**
 * Backup and Restore Manager
 *
 * Handles system backup creation, restoration, and management for the School Management System.
 *
 * @package School_Management_System
 * @subpackage Core
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SMS Backup Manager Class
 *
 * Provides comprehensive backup and restore functionality for all system data.
 */
class SMS_Backup_Manager {

    /**
     * Backup directory path
     */
    private $backup_dir;

    /**
     * Maximum number of backups to keep
     */
    const MAX_BACKUPS = 30;

    /**
     * Constructor
     */
    public function __construct() {
        $upload_dir = wp_upload_dir();
        $this->backup_dir = $upload_dir['basedir'] . '/sms-backups/';
        
        // Create backup directory if it doesn't exist
        if (!file_exists($this->backup_dir)) {
            wp_mkdir_p($this->backup_dir);
            
            // Add .htaccess to protect backup files
            $htaccess_content = "Order deny,allow\nDeny from all\n";
            file_put_contents($this->backup_dir . '.htaccess', $htaccess_content);
        }
    }

    /**
     * Create full system backup
     *
     * @param array $options Backup options
     * @return array|WP_Error Backup results or error
     */
    public function create_full_backup($options = []) {
        $default_options = [
            'include_database' => true,
            'include_files' => true,
            'include_uploads' => false,
            'compress' => true,
            'description' => 'Full system backup'
        ];

        $options = array_merge($default_options, $options);
        
        $backup_id = 'sms_backup_' . date('Y-m-d_H-i-s');
        $backup_path = $this->backup_dir . $backup_id . '/';
        
        // Create backup directory
        if (!wp_mkdir_p($backup_path)) {
            return new WP_Error('backup_dir_error', 'Could not create backup directory.');
        }

        $backup_info = [
            'id' => $backup_id,
            'timestamp' => current_time('mysql'),
            'description' => $options['description'],
            'user_id' => get_current_user_id(),
            'options' => $options,
            'files' => [],
            'size' => 0,
            'status' => 'in_progress'
        ];

        try {
            // Backup database
            if ($options['include_database']) {
                $db_backup_result = $this->backup_database($backup_path);
                if (is_wp_error($db_backup_result)) {
                    throw new Exception($db_backup_result->get_error_message());
                }
                $backup_info['files']['database'] = $db_backup_result;
            }

            // Backup SMS-specific data
            $data_backup_result = $this->backup_sms_data($backup_path);
            if (is_wp_error($data_backup_result)) {
                throw new Exception($data_backup_result->get_error_message());
            }
            $backup_info['files']['sms_data'] = $data_backup_result;

            // Backup plugin files
            if ($options['include_files']) {
                $files_backup_result = $this->backup_plugin_files($backup_path);
                if (is_wp_error($files_backup_result)) {
                    throw new Exception($files_backup_result->get_error_message());
                }
                $backup_info['files']['plugin_files'] = $files_backup_result;
            }

            // Backup uploads (if requested)
            if ($options['include_uploads']) {
                $uploads_backup_result = $this->backup_uploads($backup_path);
                if (is_wp_error($uploads_backup_result)) {
                    throw new Exception($uploads_backup_result->get_error_message());
                }
                $backup_info['files']['uploads'] = $uploads_backup_result;
            }

            // Calculate total backup size
            $backup_info['size'] = $this->calculate_directory_size($backup_path);

            // Compress backup if requested
            if ($options['compress']) {
                $compressed_file = $this->compress_backup($backup_path, $backup_id);
                if (is_wp_error($compressed_file)) {
                    throw new Exception($compressed_file->get_error_message());
                }
                $backup_info['compressed_file'] = $compressed_file;
                
                // Remove uncompressed directory
                $this->delete_directory($backup_path);
            }

            $backup_info['status'] = 'completed';
            
            // Save backup metadata
            $this->save_backup_metadata($backup_id, $backup_info);
            
            // Clean up old backups
            $this->cleanup_old_backups();

            return $backup_info;

        } catch (Exception $e) {
            // Clean up failed backup
            if (file_exists($backup_path)) {
                $this->delete_directory($backup_path);
            }
            
            return new WP_Error('backup_failed', 'Backup failed: ' . $e->getMessage());
        }
    }

    /**
     * Create incremental backup (only changed data)
     *
     * @param string $base_backup_id Base backup to compare against
     * @return array|WP_Error Backup results or error
     */
    public function create_incremental_backup($base_backup_id) {
        $base_backup = $this->get_backup_metadata($base_backup_id);
        if (!$base_backup) {
            return new WP_Error('base_backup_not_found', 'Base backup not found.');
        }

        $backup_id = 'sms_incremental_' . date('Y-m-d_H-i-s');
        $backup_path = $this->backup_dir . $backup_id . '/';
        
        if (!wp_mkdir_p($backup_path)) {
            return new WP_Error('backup_dir_error', 'Could not create backup directory.');
        }

        $backup_info = [
            'id' => $backup_id,
            'type' => 'incremental',
            'base_backup_id' => $base_backup_id,
            'timestamp' => current_time('mysql'),
            'description' => 'Incremental backup',
            'user_id' => get_current_user_id(),
            'files' => [],
            'changes' => [],
            'size' => 0,
            'status' => 'in_progress'
        ];

        try {
            // Find changed posts since base backup
            $changed_posts = $this->find_changed_posts($base_backup['timestamp']);
            if (!empty($changed_posts)) {
                $posts_backup = $this->backup_changed_posts($backup_path, $changed_posts);
                $backup_info['files']['changed_posts'] = $posts_backup;
                $backup_info['changes']['posts'] = count($changed_posts);
            }

            // Find changed users since base backup
            $changed_users = $this->find_changed_users($base_backup['timestamp']);
            if (!empty($changed_users)) {
                $users_backup = $this->backup_changed_users($backup_path, $changed_users);
                $backup_info['files']['changed_users'] = $users_backup;
                $backup_info['changes']['users'] = count($changed_users);
            }

            // Find changed options since base backup
            $changed_options = $this->find_changed_options($base_backup['timestamp']);
            if (!empty($changed_options)) {
                $options_backup = $this->backup_changed_options($backup_path, $changed_options);
                $backup_info['files']['changed_options'] = $options_backup;
                $backup_info['changes']['options'] = count($changed_options);
            }

            $backup_info['size'] = $this->calculate_directory_size($backup_path);
            $backup_info['status'] = 'completed';
            
            $this->save_backup_metadata($backup_id, $backup_info);
            
            return $backup_info;

        } catch (Exception $e) {
            if (file_exists($backup_path)) {
                $this->delete_directory($backup_path);
            }
            
            return new WP_Error('incremental_backup_failed', 'Incremental backup failed: ' . $e->getMessage());
        }
    }

    /**
     * Restore system from backup
     *
     * @param string $backup_id Backup ID to restore from
     * @param array $options Restore options
     * @return array|WP_Error Restore results or error
     */
    public function restore_from_backup($backup_id, $options = []) {
        $default_options = [
            'restore_database' => true,
            'restore_files' => true,
            'restore_uploads' => false,
            'create_pre_restore_backup' => true
        ];

        $options = array_merge($default_options, $options);
        
        $backup_metadata = $this->get_backup_metadata($backup_id);
        if (!$backup_metadata) {
            return new WP_Error('backup_not_found', 'Backup not found.');
        }

        $restore_info = [
            'backup_id' => $backup_id,
            'timestamp' => current_time('mysql'),
            'user_id' => get_current_user_id(),
            'options' => $options,
            'status' => 'in_progress',
            'pre_restore_backup_id' => null
        ];

        try {
            // Create pre-restore backup if requested
            if ($options['create_pre_restore_backup']) {
                $pre_restore_backup = $this->create_full_backup([
                    'description' => 'Pre-restore backup before restoring ' . $backup_id
                ]);
                
                if (is_wp_error($pre_restore_backup)) {
                    throw new Exception('Failed to create pre-restore backup: ' . $pre_restore_backup->get_error_message());
                }
                
                $restore_info['pre_restore_backup_id'] = $pre_restore_backup['id'];
            }

            // Extract backup if compressed
            $backup_path = $this->get_backup_path($backup_id, $backup_metadata);
            if (is_wp_error($backup_path)) {
                throw new Exception($backup_path->get_error_message());
            }

            // Restore database
            if ($options['restore_database'] && isset($backup_metadata['files']['database'])) {
                $db_restore_result = $this->restore_database($backup_path . $backup_metadata['files']['database']);
                if (is_wp_error($db_restore_result)) {
                    throw new Exception('Database restore failed: ' . $db_restore_result->get_error_message());
                }
            }

            // Restore SMS data
            if (isset($backup_metadata['files']['sms_data'])) {
                $data_restore_result = $this->restore_sms_data($backup_path . $backup_metadata['files']['sms_data']);
                if (is_wp_error($data_restore_result)) {
                    throw new Exception('SMS data restore failed: ' . $data_restore_result->get_error_message());
                }
            }

            // Restore plugin files
            if ($options['restore_files'] && isset($backup_metadata['files']['plugin_files'])) {
                $files_restore_result = $this->restore_plugin_files($backup_path . $backup_metadata['files']['plugin_files']);
                if (is_wp_error($files_restore_result)) {
                    throw new Exception('Plugin files restore failed: ' . $files_restore_result->get_error_message());
                }
            }

            // Restore uploads
            if ($options['restore_uploads'] && isset($backup_metadata['files']['uploads'])) {
                $uploads_restore_result = $this->restore_uploads($backup_path . $backup_metadata['files']['uploads']);
                if (is_wp_error($uploads_restore_result)) {
                    throw new Exception('Uploads restore failed: ' . $uploads_restore_result->get_error_message());
                }
            }

            $restore_info['status'] = 'completed';
            
            // Log restore activity
            $this->log_restore_activity($restore_info);
            
            return $restore_info;

        } catch (Exception $e) {
            $restore_info['status'] = 'failed';
            $restore_info['error'] = $e->getMessage();
            
            return new WP_Error('restore_failed', 'Restore failed: ' . $e->getMessage());
        }
    }

    /**
     * List all available backups
     *
     * @return array List of backup metadata
     */
    public function list_backups() {
        $backups = [];
        $backup_files = glob($this->backup_dir . 'sms_backup_*/metadata.json');
        $backup_files = array_merge($backup_files, glob($this->backup_dir . 'sms_incremental_*/metadata.json'));
        
        foreach ($backup_files as $metadata_file) {
            $metadata = json_decode(file_get_contents($metadata_file), true);
            if ($metadata) {
                $backups[] = $metadata;
            }
        }

        // Sort by timestamp (newest first)
        usort($backups, function($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });

        return $backups;
    }

    /**
     * Delete backup
     *
     * @param string $backup_id Backup ID to delete
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function delete_backup($backup_id) {
        $backup_metadata = $this->get_backup_metadata($backup_id);
        if (!$backup_metadata) {
            return new WP_Error('backup_not_found', 'Backup not found.');
        }

        $backup_path = $this->backup_dir . $backup_id . '/';
        
        // Delete compressed file if exists
        if (isset($backup_metadata['compressed_file'])) {
            $compressed_file_path = $this->backup_dir . $backup_metadata['compressed_file'];
            if (file_exists($compressed_file_path)) {
                unlink($compressed_file_path);
            }
        }

        // Delete backup directory
        if (file_exists($backup_path)) {
            $this->delete_directory($backup_path);
        }

        return true;
    }

    /**
     * Backup database
     *
     * @param string $backup_path Path to store backup
     * @return string|WP_Error Backup filename or error
     */
    private function backup_database($backup_path) {
        global $wpdb;
        
        $filename = 'database_backup.sql';
        $file_path = $backup_path . $filename;
        
        // Get all SMS-related tables
        $sms_tables = $this->get_sms_tables();
        
        $sql_content = "-- SMS Database Backup\n";
        $sql_content .= "-- Generated on: " . current_time('mysql') . "\n\n";
        
        foreach ($sms_tables as $table) {
            // Get table structure
            $create_table = $wpdb->get_row("SHOW CREATE TABLE `{$table}`", ARRAY_N);
            if ($create_table) {
                $sql_content .= "DROP TABLE IF EXISTS `{$table}`;\n";
                $sql_content .= $create_table[1] . ";\n\n";
            }
            
            // Get table data
            $rows = $wpdb->get_results("SELECT * FROM `{$table}`", ARRAY_A);
            if ($rows) {
                foreach ($rows as $row) {
                    $values = array_map(function($value) use ($wpdb) {
                        return $wpdb->prepare('%s', $value);
                    }, array_values($row));
                    
                    $sql_content .= "INSERT INTO `{$table}` VALUES (" . implode(', ', $values) . ");\n";
                }
                $sql_content .= "\n";
            }
        }
        
        if (file_put_contents($file_path, $sql_content) === false) {
            return new WP_Error('database_backup_failed', 'Could not write database backup file.');
        }
        
        return $filename;
    }

    /**
     * Backup SMS-specific data (posts, meta, options)
     *
     * @param string $backup_path Path to store backup
     * @return string|WP_Error Backup filename or error
     */
    private function backup_sms_data($backup_path) {
        $filename = 'sms_data_backup.json';
        $file_path = $backup_path . $filename;
        
        $sms_data = [
            'posts' => $this->get_sms_posts(),
            'users' => $this->get_sms_users(),
            'options' => $this->get_sms_options(),
            'terms' => $this->get_sms_terms(),
            'metadata' => [
                'version' => '1.0',
                'timestamp' => current_time('mysql'),
                'site_url' => site_url()
            ]
        ];
        
        if (file_put_contents($file_path, json_encode($sms_data, JSON_PRETTY_PRINT)) === false) {
            return new WP_Error('sms_data_backup_failed', 'Could not write SMS data backup file.');
        }
        
        return $filename;
    }

    /**
     * Backup plugin files
     *
     * @param string $backup_path Path to store backup
     * @return string|WP_Error Backup filename or error
     */
    private function backup_plugin_files($backup_path) {
        $plugin_dir = plugin_dir_path(__FILE__) . '../..';
        $backup_subdir = $backup_path . 'plugin_files/';
        
        if (!wp_mkdir_p($backup_subdir)) {
            return new WP_Error('plugin_backup_failed', 'Could not create plugin backup directory.');
        }
        
        $this->copy_directory($plugin_dir, $backup_subdir);
        
        return 'plugin_files/';
    }

    /**
     * Get SMS-related database tables
     *
     * @return array List of table names
     */
    private function get_sms_tables() {
        global $wpdb;
        
        $tables = [];
        
        // Core WordPress tables with SMS data
        $tables[] = $wpdb->posts;
        $tables[] = $wpdb->postmeta;
        $tables[] = $wpdb->users;
        $tables[] = $wpdb->usermeta;
        $tables[] = $wpdb->options;
        $tables[] = $wpdb->terms;
        $tables[] = $wpdb->term_taxonomy;
        $tables[] = $wpdb->term_relationships;
        
        // Custom SMS tables (if any)
        $custom_tables = $wpdb->get_col("SHOW TABLES LIKE '{$wpdb->prefix}sms_%'");
        $tables = array_merge($tables, $custom_tables);
        
        return $tables;
    }

    /**
     * Get all SMS-related posts
     *
     * @return array SMS posts data
     */
    private function get_sms_posts() {
        $sms_post_types = [
            'cpt_students', 'cpt_classes', 'cpt_fees', 'cpt_invoices',
            'cpt_transactions', 'cpt_attendance', 'cpt_timetables',
            'cpt_notices', 'cpt_transport_routes'
        ];
        
        $posts_data = [];
        
        foreach ($sms_post_types as $post_type) {
            $posts = get_posts([
                'post_type' => $post_type,
                'posts_per_page' => -1,
                'post_status' => 'any'
            ]);
            
            foreach ($posts as $post) {
                $post_data = $post->to_array();
                $post_data['meta'] = get_post_meta($post->ID);
                $posts_data[] = $post_data;
            }
        }
        
        return $posts_data;
    }

    /**
     * Get all SMS-related users
     *
     * @return array SMS users data
     */
    private function get_sms_users() {
        $sms_roles = ['sms_admin', 'sms_teacher', 'sms_parent', 'sms_student'];
        $users_data = [];
        
        foreach ($sms_roles as $role) {
            $users = get_users(['role' => $role]);
            
            foreach ($users as $user) {
                $user_data = $user->to_array();
                $user_data['meta'] = get_user_meta($user->ID);
                $users_data[] = $user_data;
            }
        }
        
        return $users_data;
    }

    /**
     * Get all SMS-related options
     *
     * @return array SMS options data
     */
    private function get_sms_options() {
        global $wpdb;
        
        $sms_options = $wpdb->get_results(
            "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE 'sms_%'",
            ARRAY_A
        );
        
        return $sms_options;
    }

    /**
     * Get all SMS-related taxonomy terms
     *
     * @return array SMS terms data
     */
    private function get_sms_terms() {
        $sms_taxonomies = [
            'tax_subjects', 'tax_grades', 'tax_academic_years', 'tax_terms'
        ];
        
        $terms_data = [];
        
        foreach ($sms_taxonomies as $taxonomy) {
            $terms = get_terms([
                'taxonomy' => $taxonomy,
                'hide_empty' => false
            ]);
            
            if (!is_wp_error($terms)) {
                foreach ($terms as $term) {
                    $term_data = $term->to_array();
                    $term_data['meta'] = get_term_meta($term->term_id);
                    $terms_data[] = $term_data;
                }
            }
        }
        
        return $terms_data;
    }

    /**
     * Calculate directory size
     *
     * @param string $directory Directory path
     * @return int Size in bytes
     */
    private function calculate_directory_size($directory) {
        $size = 0;
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($files as $file) {
            $size += $file->getSize();
        }
        
        return $size;
    }

    /**
     * Compress backup directory
     *
     * @param string $backup_path Backup directory path
     * @param string $backup_id Backup ID
     * @return string|WP_Error Compressed filename or error
     */
    private function compress_backup($backup_path, $backup_id) {
        if (!class_exists('ZipArchive')) {
            return new WP_Error('zip_not_available', 'ZIP extension not available.');
        }
        
        $zip_filename = $backup_id . '.zip';
        $zip_path = $this->backup_dir . $zip_filename;
        
        $zip = new ZipArchive();
        if ($zip->open($zip_path, ZipArchive::CREATE) !== TRUE) {
            return new WP_Error('zip_create_failed', 'Could not create ZIP file.');
        }
        
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($backup_path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        foreach ($files as $file) {
            if (!$file->isDir()) {
                $file_path = $file->getRealPath();
                $relative_path = substr($file_path, strlen($backup_path));
                $zip->addFile($file_path, $relative_path);
            }
        }
        
        $zip->close();
        
        return $zip_filename;
    }

    /**
     * Copy directory recursively
     *
     * @param string $source Source directory
     * @param string $destination Destination directory
     */
    private function copy_directory($source, $destination) {
        if (!file_exists($destination)) {
            wp_mkdir_p($destination);
        }
        
        $files = scandir($source);
        
        foreach ($files as $file) {
            if ($file != '.' && $file != '..') {
                $source_path = $source . '/' . $file;
                $dest_path = $destination . '/' . $file;
                
                if (is_dir($source_path)) {
                    $this->copy_directory($source_path, $dest_path);
                } else {
                    copy($source_path, $dest_path);
                }
            }
        }
    }

    /**
     * Delete directory recursively
     *
     * @param string $directory Directory to delete
     */
    private function delete_directory($directory) {
        if (!file_exists($directory)) {
            return;
        }
        
        $files = array_diff(scandir($directory), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $directory . '/' . $file;
            
            if (is_dir($path)) {
                $this->delete_directory($path);
            } else {
                unlink($path);
            }
        }
        
        rmdir($directory);
    }

    /**
     * Save backup metadata
     *
     * @param string $backup_id Backup ID
     * @param array $metadata Backup metadata
     */
    private function save_backup_metadata($backup_id, $metadata) {
        $metadata_file = $this->backup_dir . $backup_id . '/metadata.json';
        file_put_contents($metadata_file, json_encode($metadata, JSON_PRETTY_PRINT));
    }

    /**
     * Get backup metadata
     *
     * @param string $backup_id Backup ID
     * @return array|null Backup metadata or null if not found
     */
    private function get_backup_metadata($backup_id) {
        $metadata_file = $this->backup_dir . $backup_id . '/metadata.json';
        
        if (!file_exists($metadata_file)) {
            return null;
        }
        
        return json_decode(file_get_contents($metadata_file), true);
    }

    /**
     * Get backup path (extract if compressed)
     *
     * @param string $backup_id Backup ID
     * @param array $backup_metadata Backup metadata
     * @return string|WP_Error Backup path or error
     */
    private function get_backup_path($backup_id, $backup_metadata) {
        $backup_path = $this->backup_dir . $backup_id . '/';
        
        // If backup is compressed, extract it first
        if (isset($backup_metadata['compressed_file'])) {
            $zip_path = $this->backup_dir . $backup_metadata['compressed_file'];
            
            if (!file_exists($zip_path)) {
                return new WP_Error('backup_file_missing', 'Backup file not found.');
            }
            
            // Extract ZIP file
            $zip = new ZipArchive();
            if ($zip->open($zip_path) === TRUE) {
                wp_mkdir_p($backup_path);
                $zip->extractTo($backup_path);
                $zip->close();
            } else {
                return new WP_Error('backup_extract_failed', 'Could not extract backup file.');
            }
        }
        
        return $backup_path;
    }

    /**
     * Clean up old backups (keep only MAX_BACKUPS)
     */
    private function cleanup_old_backups() {
        $backups = $this->list_backups();
        
        if (count($backups) > self::MAX_BACKUPS) {
            $backups_to_delete = array_slice($backups, self::MAX_BACKUPS);
            
            foreach ($backups_to_delete as $backup) {
                $this->delete_backup($backup['id']);
            }
        }
    }

    /**
     * Find posts changed since timestamp
     *
     * @param string $since_timestamp Timestamp to compare against
     * @return array Changed post IDs
     */
    private function find_changed_posts($since_timestamp) {
        global $wpdb;
        
        $sms_post_types = [
            'cpt_students', 'cpt_classes', 'cpt_fees', 'cpt_invoices',
            'cpt_transactions', 'cpt_attendance', 'cpt_timetables',
            'cpt_notices', 'cpt_transport_routes'
        ];
        
        $post_types_in = "'" . implode("','", $sms_post_types) . "'";
        
        $changed_posts = $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} 
             WHERE post_type IN ({$post_types_in}) 
             AND post_modified > %s",
            $since_timestamp
        ));
        
        return $changed_posts;
    }

    /**
     * Find users changed since timestamp
     *
     * @param string $since_timestamp Timestamp to compare against
     * @return array Changed user IDs
     */
    private function find_changed_users($since_timestamp) {
        global $wpdb;
        
        // WordPress doesn't track user modification time by default
        // This is a simplified approach - in production, you might want to add a custom field
        $changed_users = $wpdb->get_col($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} 
             WHERE meta_key LIKE 'sms_%' 
             AND meta_value != ''
             GROUP BY user_id"
        ));
        
        return $changed_users;
    }

    /**
     * Find options changed since timestamp
     *
     * @param string $since_timestamp Timestamp to compare against
     * @return array Changed option names
     */
    private function find_changed_options($since_timestamp) {
        global $wpdb;
        
        // WordPress doesn't track option modification time by default
        // This returns all SMS options for incremental backup
        $changed_options = $wpdb->get_col(
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'sms_%'"
        );
        
        return $changed_options;
    }

    /**
     * Log restore activity
     *
     * @param array $restore_info Restore information
     */
    private function log_restore_activity($restore_info) {
        $log_entry = [
            'action' => 'restore',
            'backup_id' => $restore_info['backup_id'],
            'timestamp' => $restore_info['timestamp'],
            'user_id' => $restore_info['user_id'],
            'status' => $restore_info['status']
        ];
        
        $activity_logs = get_option('sms_restore_logs', []);
        $activity_logs[] = $log_entry;
        
        // Keep only last 50 restore logs
        if (count($activity_logs) > 50) {
            $activity_logs = array_slice($activity_logs, -50);
        }
        
        update_option('sms_restore_logs', $activity_logs);
    }
}