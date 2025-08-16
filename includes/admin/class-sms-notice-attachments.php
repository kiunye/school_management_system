<?php
/**
 * Notice Attachments Handler
 *
 * @package SchoolManagementSystem
 * @subpackage Admin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class SMS_Notice_Attachments
 * 
 * Handles file attachments for notices
 */
class SMS_Notice_Attachments {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_ajax_sms_upload_notice_attachment', array($this, 'handle_attachment_upload'));
        add_action('wp_ajax_sms_delete_notice_attachment', array($this, 'handle_attachment_delete'));
        add_action('wp_ajax_sms_get_attachment_info', array($this, 'get_attachment_info'));
        add_filter('upload_mimes', array($this, 'add_allowed_mime_types'));
        add_action('delete_post', array($this, 'cleanup_notice_attachments'));
    }

    /**
     * Initialize the attachment handler
     */
    public function init() {
        // Add custom upload directory for notice attachments
        add_filter('upload_dir', array($this, 'custom_upload_dir'));
    }

    /**
     * Set custom upload directory for notice attachments
     */
    public function custom_upload_dir($upload) {
        // Only apply to notice attachments
        if (isset($_REQUEST['notice_attachment']) && $_REQUEST['notice_attachment'] === '1') {
            $upload['subdir'] = '/notices';
            $upload['path'] = $upload['basedir'] . $upload['subdir'];
            $upload['url'] = $upload['baseurl'] . $upload['subdir'];
        }
        
        return $upload;
    }

    /**
     * Add allowed MIME types for notice attachments
     */
    public function add_allowed_mime_types($mimes) {
        // Add common document types
        $mimes['pdf'] = 'application/pdf';
        $mimes['doc'] = 'application/msword';
        $mimes['docx'] = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
        $mimes['xls'] = 'application/vnd.ms-excel';
        $mimes['xlsx'] = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        $mimes['ppt'] = 'application/vnd.ms-powerpoint';
        $mimes['pptx'] = 'application/vnd.openxmlformats-officedocument.presentationml.presentation';
        $mimes['txt'] = 'text/plain';
        $mimes['rtf'] = 'application/rtf';
        
        return $mimes;
    }

    /**
     * Handle attachment upload via AJAX
     */
    public function handle_attachment_upload() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_notice_attachment_nonce')) {
            wp_send_json_error(__('Security check failed', 'school-management-system'));
        }

        // Check capabilities
        if (!current_user_can('manage_notices')) {
            wp_send_json_error(__('You do not have permission to upload attachments', 'school-management-system'));
        }

        // Check if file was uploaded
        if (empty($_FILES['attachment'])) {
            wp_send_json_error(__('No file uploaded', 'school-management-system'));
        }

        $notice_id = intval($_POST['notice_id']);
        if (!$notice_id) {
            wp_send_json_error(__('Invalid notice ID', 'school-management-system'));
        }

        // Validate file
        $file = $_FILES['attachment'];
        $validation_result = $this->validate_attachment($file);
        
        if (is_wp_error($validation_result)) {
            wp_send_json_error($validation_result->get_error_message());
        }

        // Set custom upload directory flag
        $_REQUEST['notice_attachment'] = '1';

        // Handle the upload
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        $attachment_id = media_handle_upload('attachment', $notice_id);

        if (is_wp_error($attachment_id)) {
            wp_send_json_error($attachment_id->get_error_message());
        }

        // Get attachment info
        $attachment_info = $this->get_attachment_data($attachment_id);
        
        // Update notice attachments field
        $this->add_attachment_to_notice($notice_id, $attachment_id);

        wp_send_json_success(array(
            'attachment_id' => $attachment_id,
            'attachment_info' => $attachment_info,
            'message' => __('File uploaded successfully', 'school-management-system')
        ));
    }

    /**
     * Handle attachment deletion via AJAX
     */
    public function handle_attachment_delete() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_notice_attachment_nonce')) {
            wp_send_json_error(__('Security check failed', 'school-management-system'));
        }

        // Check capabilities
        if (!current_user_can('manage_notices')) {
            wp_send_json_error(__('You do not have permission to delete attachments', 'school-management-system'));
        }

        $attachment_id = intval($_POST['attachment_id']);
        $notice_id = intval($_POST['notice_id']);

        if (!$attachment_id || !$notice_id) {
            wp_send_json_error(__('Invalid attachment or notice ID', 'school-management-system'));
        }

        // Remove from notice attachments
        $this->remove_attachment_from_notice($notice_id, $attachment_id);

        // Delete the attachment
        $deleted = wp_delete_attachment($attachment_id, true);

        if ($deleted) {
            wp_send_json_success(array(
                'message' => __('Attachment deleted successfully', 'school-management-system')
            ));
        } else {
            wp_send_json_error(__('Failed to delete attachment', 'school-management-system'));
        }
    }

    /**
     * Get attachment info via AJAX
     */
    public function get_attachment_info() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sms_notice_attachment_nonce')) {
            wp_send_json_error(__('Security check failed', 'school-management-system'));
        }

        $attachment_id = intval($_POST['attachment_id']);
        if (!$attachment_id) {
            wp_send_json_error(__('Invalid attachment ID', 'school-management-system'));
        }

        $attachment_info = $this->get_attachment_data($attachment_id);
        
        if ($attachment_info) {
            wp_send_json_success($attachment_info);
        } else {
            wp_send_json_error(__('Attachment not found', 'school-management-system'));
        }
    }

    /**
     * Validate attachment file
     */
    private function validate_attachment($file) {
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return new WP_Error('upload_error', $this->get_upload_error_message($file['error']));
        }

        // Check file size (10MB limit)
        $max_size = 10 * 1024 * 1024; // 10MB in bytes
        if ($file['size'] > $max_size) {
            return new WP_Error('file_too_large', __('File size exceeds 10MB limit', 'school-management-system'));
        }

        // Check file type
        $allowed_types = array(
            'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
            'txt', 'rtf', 'jpg', 'jpeg', 'png', 'gif', 'mp4', 'avi'
        );
        
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($file_extension, $allowed_types)) {
            return new WP_Error('invalid_file_type', 
                sprintf(__('File type %s is not allowed', 'school-management-system'), $file_extension)
            );
        }

        // Check MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowed_mimes = array(
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'text/plain',
            'application/rtf',
            'image/jpeg',
            'image/png',
            'image/gif',
            'video/mp4',
            'video/x-msvideo'
        );

        if (!in_array($mime_type, $allowed_mimes)) {
            return new WP_Error('invalid_mime_type', 
                sprintf(__('MIME type %s is not allowed', 'school-management-system'), $mime_type)
            );
        }

        return true;
    }

    /**
     * Get upload error message
     */
    private function get_upload_error_message($error_code) {
        switch ($error_code) {
            case UPLOAD_ERR_INI_SIZE:
                return __('File exceeds the upload_max_filesize directive in php.ini', 'school-management-system');
            case UPLOAD_ERR_FORM_SIZE:
                return __('File exceeds the MAX_FILE_SIZE directive', 'school-management-system');
            case UPLOAD_ERR_PARTIAL:
                return __('File was only partially uploaded', 'school-management-system');
            case UPLOAD_ERR_NO_FILE:
                return __('No file was uploaded', 'school-management-system');
            case UPLOAD_ERR_NO_TMP_DIR:
                return __('Missing a temporary folder', 'school-management-system');
            case UPLOAD_ERR_CANT_WRITE:
                return __('Failed to write file to disk', 'school-management-system');
            case UPLOAD_ERR_EXTENSION:
                return __('File upload stopped by extension', 'school-management-system');
            default:
                return __('Unknown upload error', 'school-management-system');
        }
    }

    /**
     * Get attachment data
     */
    private function get_attachment_data($attachment_id) {
        $attachment = get_post($attachment_id);
        if (!$attachment) {
            return false;
        }

        $file_path = get_attached_file($attachment_id);
        $file_url = wp_get_attachment_url($attachment_id);
        $file_size = filesize($file_path);
        $file_type = get_post_mime_type($attachment_id);

        return array(
            'id' => $attachment_id,
            'title' => $attachment->post_title,
            'filename' => basename($file_path),
            'url' => $file_url,
            'size' => $this->format_file_size($file_size),
            'size_bytes' => $file_size,
            'type' => $file_type,
            'extension' => strtolower(pathinfo($file_path, PATHINFO_EXTENSION)),
            'upload_date' => $attachment->post_date,
            'is_image' => wp_attachment_is_image($attachment_id),
            'thumbnail' => wp_attachment_is_image($attachment_id) ? wp_get_attachment_image_url($attachment_id, 'thumbnail') : null
        );
    }

    /**
     * Format file size for display
     */
    private function format_file_size($bytes) {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }

    /**
     * Add attachment to notice
     */
    private function add_attachment_to_notice($notice_id, $attachment_id) {
        $current_attachments = get_field('notice_attachments', $notice_id) ?: array();
        
        // Add new attachment if not already present
        if (!in_array($attachment_id, $current_attachments)) {
            $current_attachments[] = $attachment_id;
            update_field('notice_attachments', $current_attachments, $notice_id);
        }
    }

    /**
     * Remove attachment from notice
     */
    private function remove_attachment_from_notice($notice_id, $attachment_id) {
        $current_attachments = get_field('notice_attachments', $notice_id) ?: array();
        
        // Remove attachment from array
        $current_attachments = array_filter($current_attachments, function($id) use ($attachment_id) {
            return $id != $attachment_id;
        });
        
        update_field('notice_attachments', array_values($current_attachments), $notice_id);
    }

    /**
     * Get notice attachments
     */
    public function get_notice_attachments($notice_id) {
        $attachment_ids = get_field('notice_attachments', $notice_id) ?: array();
        $attachments = array();

        foreach ($attachment_ids as $attachment_id) {
            $attachment_data = $this->get_attachment_data($attachment_id);
            if ($attachment_data) {
                $attachments[] = $attachment_data;
            }
        }

        return $attachments;
    }

    /**
     * Render attachment list for admin
     */
    public function render_attachment_list($notice_id) {
        $attachments = $this->get_notice_attachments($notice_id);
        
        if (empty($attachments)) {
            echo '<p class="no-attachments">' . __('No attachments', 'school-management-system') . '</p>';
            return;
        }

        echo '<div class="notice-attachments-list">';
        foreach ($attachments as $attachment) {
            $this->render_attachment_item($attachment);
        }
        echo '</div>';
    }

    /**
     * Render individual attachment item
     */
    private function render_attachment_item($attachment) {
        $icon_class = $this->get_file_icon_class($attachment['extension']);
        
        echo '<div class="attachment-item" data-attachment-id="' . $attachment['id'] . '">';
        echo '<div class="attachment-icon">';
        
        if ($attachment['is_image'] && $attachment['thumbnail']) {
            echo '<img src="' . esc_url($attachment['thumbnail']) . '" alt="' . esc_attr($attachment['filename']) . '">';
        } else {
            echo '<span class="dashicons ' . $icon_class . '"></span>';
        }
        
        echo '</div>';
        echo '<div class="attachment-info">';
        echo '<div class="attachment-filename">' . esc_html($attachment['filename']) . '</div>';
        echo '<div class="attachment-meta">' . $attachment['size'] . ' • ' . strtoupper($attachment['extension']) . '</div>';
        echo '</div>';
        echo '<div class="attachment-actions">';
        echo '<a href="' . esc_url($attachment['url']) . '" target="_blank" class="button button-small">' . __('View', 'school-management-system') . '</a>';
        echo '<button type="button" class="button button-small delete-attachment" data-attachment-id="' . $attachment['id'] . '">' . __('Delete', 'school-management-system') . '</button>';
        echo '</div>';
        echo '</div>';
    }

    /**
     * Get file icon class based on extension
     */
    private function get_file_icon_class($extension) {
        $icon_map = array(
            'pdf' => 'dashicons-pdf',
            'doc' => 'dashicons-media-document',
            'docx' => 'dashicons-media-document',
            'xls' => 'dashicons-media-spreadsheet',
            'xlsx' => 'dashicons-media-spreadsheet',
            'ppt' => 'dashicons-media-interactive',
            'pptx' => 'dashicons-media-interactive',
            'txt' => 'dashicons-media-text',
            'rtf' => 'dashicons-media-text',
            'jpg' => 'dashicons-format-image',
            'jpeg' => 'dashicons-format-image',
            'png' => 'dashicons-format-image',
            'gif' => 'dashicons-format-image',
            'mp4' => 'dashicons-format-video',
            'avi' => 'dashicons-format-video'
        );

        return $icon_map[$extension] ?? 'dashicons-media-default';
    }

    /**
     * Cleanup attachments when notice is deleted
     */
    public function cleanup_notice_attachments($post_id) {
        if (get_post_type($post_id) !== 'sms_notices') {
            return;
        }

        $attachments = $this->get_notice_attachments($post_id);
        
        foreach ($attachments as $attachment) {
            wp_delete_attachment($attachment['id'], true);
        }
    }

    /**
     * Get attachment download URL with access control
     */
    public function get_secure_download_url($attachment_id, $notice_id) {
        return add_query_arg(array(
            'action' => 'sms_download_notice_attachment',
            'attachment_id' => $attachment_id,
            'notice_id' => $notice_id,
            'nonce' => wp_create_nonce('sms_download_attachment_' . $attachment_id)
        ), admin_url('admin-ajax.php'));
    }

    /**
     * Handle secure attachment download
     */
    public function handle_secure_download() {
        $attachment_id = intval($_GET['attachment_id']);
        $notice_id = intval($_GET['notice_id']);
        $nonce = sanitize_text_field($_GET['nonce']);

        // Verify nonce
        if (!wp_verify_nonce($nonce, 'sms_download_attachment_' . $attachment_id)) {
            wp_die(__('Security check failed', 'school-management-system'));
        }

        // Check if user can view this notice
        if (!$this->can_user_view_notice($notice_id)) {
            wp_die(__('You do not have permission to download this attachment', 'school-management-system'));
        }

        // Get file path
        $file_path = get_attached_file($attachment_id);
        if (!$file_path || !file_exists($file_path)) {
            wp_die(__('File not found', 'school-management-system'));
        }

        // Serve the file
        $this->serve_file($file_path);
    }

    /**
     * Check if user can view notice
     */
    private function can_user_view_notice($notice_id) {
        // Administrators and users with manage_notices capability can view all
        if (current_user_can('manage_notices')) {
            return true;
        }

        // Check if notice is targeted to current user
        $audience_type = get_field('audience_type', $notice_id);
        $current_user = wp_get_current_user();

        switch ($audience_type) {
            case 'all':
                return true;
                
            case 'roles':
                $target_roles = get_field('target_roles', $notice_id);
                return !empty(array_intersect($current_user->roles, $target_roles));
                
            case 'individuals':
                $target_individuals = get_field('target_individuals', $notice_id);
                return in_array($current_user->ID, wp_list_pluck($target_individuals, 'ID'));
                
            default:
                return false;
        }
    }

    /**
     * Serve file for download
     */
    private function serve_file($file_path) {
        $filename = basename($file_path);
        $mime_type = wp_check_filetype($filename)['type'];

        // Set headers
        header('Content-Type: ' . $mime_type);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($file_path));
        header('Cache-Control: private');
        header('Pragma: private');
        header('Expires: 0');

        // Clear output buffer
        if (ob_get_level()) {
            ob_end_clean();
        }

        // Read and output file
        readfile($file_path);
        exit;
    }
}

// Initialize the attachment handler
new SMS_Notice_Attachments();