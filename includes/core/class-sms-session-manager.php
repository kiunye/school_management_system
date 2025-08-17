<?php
/**
 * Session Management System
 *
 * Handles secure session management and authentication features
 *
 * @package School_Management_System
 * @subpackage Core
 */

if (!defined('ABSPATH')) {
    exit;
}

class SMS_Session_Manager {
    
    /**
     * Instance of this class
     */
    private static $instance = null;
    
    /**
     * Session table name
     */
    private $session_table;
    
    /**
     * Session timeout in seconds
     */
    private $session_timeout;
    
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
        global $wpdb;
        $this->session_table = $wpdb->prefix . 'sms_user_sessions';
        $this->session_timeout = get_option('sms_session_timeout', 3600); // 1 hour default
        
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('wp_login', array($this, 'create_session'), 10, 2);
        add_action('wp_logout', array($this, 'destroy_session'));
        add_action('init', array($this, 'validate_session'));
        add_action('wp_ajax_sms_extend_session', array($this, 'extend_session'));
        add_action('wp_ajax_nopriv_sms_extend_session', array($this, 'extend_session'));
        
        // Clean expired sessions periodically
        add_action('wp_scheduled_delete', array($this, 'cleanup_expired_sessions'));
    }
    
    /**
     * Create new session on login
     */
    public function create_session($user_login, $user) {
        global $wpdb;
        
        $session_token = wp_generate_password(32, false);
        $session_data = array(
            'user_id' => $user->ID,
            'session_token' => hash('sha256', $session_token),
            'ip_address' => $this->get_client_ip(),
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            'created_at' => current_time('mysql'),
            'last_activity' => current_time('mysql'),
            'expires_at' => date('Y-m-d H:i:s', time() + $this->session_timeout)
        );
        
        // Remove old sessions for this user (limit to 3 concurrent sessions)
        $this->cleanup_user_sessions($user->ID, 2);
        
        $wpdb->insert($this->session_table, $session_data);
        
        // Store session token in cookie
        setcookie('sms_session_token', $session_token, time() + $this->session_timeout, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
        
        // Log session creation
        SMS_Logger::get_instance()->log_activity(
            $user->ID,
            'session_created',
            'session',
            $wpdb->insert_id,
            array(
                'ip_address' => $session_data['ip_address'],
                'user_agent' => $session_data['user_agent']
            )
        );
    }
    
    /**
     * Destroy session on logout
     */
    public function destroy_session() {
        global $wpdb;
        
        $session_token = $_COOKIE['sms_session_token'] ?? '';
        if (empty($session_token)) {
            return;
        }
        
        $hashed_token = hash('sha256', $session_token);
        $user_id = get_current_user_id();
        
        $wpdb->delete(
            $this->session_table,
            array(
                'session_token' => $hashed_token,
                'user_id' => $user_id
            )
        );
        
        // Clear cookie
        setcookie('sms_session_token', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
        
        // Log session destruction
        if ($user_id) {
            SMS_Logger::get_instance()->log_activity(
                $user_id,
                'session_destroyed',
                'session',
                0,
                array(
                    'ip_address' => $this->get_client_ip()
                )
            );
        }
    }
    
    /**
     * Validate current session
     */
    public function validate_session() {
        // Skip validation for non-logged-in users
        if (!is_user_logged_in()) {
            return;
        }
        
        $session_token = $_COOKIE['sms_session_token'] ?? '';
        if (empty($session_token)) {
            return;
        }
        
        global $wpdb;
        $hashed_token = hash('sha256', $session_token);
        $user_id = get_current_user_id();
        
        $session = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->session_table} WHERE session_token = %s AND user_id = %d",
            $hashed_token,
            $user_id
        ));
        
        if (!$session) {
            $this->force_logout('invalid_session');
            return;
        }
        
        // Check if session has expired
        if (strtotime($session->expires_at) < time()) {
            $this->force_logout('session_expired');
            return;
        }
        
        // Check IP address consistency (optional security feature)
        if (get_option('sms_enforce_ip_consistency', false)) {
            if ($session->ip_address !== $this->get_client_ip()) {
                $this->force_logout('ip_mismatch');
                return;
            }
        }
        
        // Update last activity
        $this->update_session_activity($session->id);
    }
    
    /**
     * Extend session via AJAX
     */
    public function extend_session() {
        check_ajax_referer('sms_extend_session', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error('Not logged in');
            return;
        }
        
        $session_token = $_COOKIE['sms_session_token'] ?? '';
        if (empty($session_token)) {
            wp_send_json_error('No session token');
            return;
        }
        
        global $wpdb;
        $hashed_token = hash('sha256', $session_token);
        $user_id = get_current_user_id();
        
        $updated = $wpdb->update(
            $this->session_table,
            array(
                'expires_at' => date('Y-m-d H:i:s', time() + $this->session_timeout),
                'last_activity' => current_time('mysql')
            ),
            array(
                'session_token' => $hashed_token,
                'user_id' => $user_id
            )
        );
        
        if ($updated) {
            // Update cookie expiration
            setcookie('sms_session_token', $session_token, time() + $this->session_timeout, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
            wp_send_json_success('Session extended');
        } else {
            wp_send_json_error('Failed to extend session');
        }
    }
    
    /**
     * Force logout with reason
     */
    private function force_logout($reason) {
        $user_id = get_current_user_id();
        
        // Log forced logout
        SMS_Logger::get_instance()->log_security_event(
            'forced_logout',
            array(
                'user_id' => $user_id,
                'reason' => $reason,
                'ip_address' => $this->get_client_ip()
            )
        );
        
        // Destroy session
        $this->destroy_session();
        
        // Logout user
        wp_logout();
        
        // Redirect to login with message
        $redirect_url = add_query_arg(
            array(
                'sms_logout_reason' => $reason,
                'redirect_to' => urlencode($_SERVER['REQUEST_URI'] ?? '')
            ),
            wp_login_url()
        );
        
        wp_redirect($redirect_url);
        exit;
    }
    
    /**
     * Update session activity timestamp
     */
    private function update_session_activity($session_id) {
        global $wpdb;
        
        $wpdb->update(
            $this->session_table,
            array('last_activity' => current_time('mysql')),
            array('id' => $session_id)
        );
    }
    
    /**
     * Clean up old sessions for a user
     */
    private function cleanup_user_sessions($user_id, $keep_count = 2) {
        global $wpdb;
        
        $sessions_to_delete = $wpdb->get_results($wpdb->prepare(
            "SELECT id FROM {$this->session_table} 
             WHERE user_id = %d 
             ORDER BY last_activity DESC 
             LIMIT %d, 999",
            $user_id,
            $keep_count
        ));
        
        if (!empty($sessions_to_delete)) {
            $session_ids = array_map(function($session) {
                return $session->id;
            }, $sessions_to_delete);
            
            $placeholders = implode(',', array_fill(0, count($session_ids), '%d'));
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$this->session_table} WHERE id IN ($placeholders)",
                $session_ids
            ));
        }
    }
    
    /**
     * Clean up expired sessions
     */
    public function cleanup_expired_sessions() {
        global $wpdb;
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->session_table} WHERE expires_at < %s",
            current_time('mysql')
        ));
    }
    
    /**
     * Get active sessions for a user
     */
    public function get_user_sessions($user_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT id, ip_address, user_agent, created_at, last_activity, expires_at 
             FROM {$this->session_table} 
             WHERE user_id = %d AND expires_at > %s 
             ORDER BY last_activity DESC",
            $user_id,
            current_time('mysql')
        ));
    }
    
    /**
     * Terminate specific session
     */
    public function terminate_session($session_id, $user_id) {
        global $wpdb;
        
        $deleted = $wpdb->delete(
            $this->session_table,
            array(
                'id' => $session_id,
                'user_id' => $user_id
            )
        );
        
        if ($deleted) {
            SMS_Logger::get_instance()->log_activity(
                get_current_user_id(),
                'session_terminated',
                'session',
                $session_id,
                array(
                    'target_user_id' => $user_id
                )
            );
        }
        
        return $deleted > 0;
    }
    
    /**
     * Terminate all sessions for a user
     */
    public function terminate_all_user_sessions($user_id) {
        global $wpdb;
        
        $deleted = $wpdb->delete(
            $this->session_table,
            array('user_id' => $user_id)
        );
        
        if ($deleted) {
            SMS_Logger::get_instance()->log_activity(
                get_current_user_id(),
                'all_sessions_terminated',
                'user',
                $user_id,
                array(
                    'sessions_terminated' => $deleted
                )
            );
        }
        
        return $deleted;
    }
    
    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Create session table
     */
    public function create_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$this->session_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            session_token varchar(64) NOT NULL,
            ip_address varchar(45) NOT NULL,
            user_agent varchar(255) NOT NULL,
            created_at datetime NOT NULL,
            last_activity datetime NOT NULL,
            expires_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY session_token (session_token),
            KEY user_id (user_id),
            KEY expires_at (expires_at),
            KEY last_activity (last_activity)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}