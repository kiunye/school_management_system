<?php
/**
 * The public-facing functionality of the plugin.
 *
 * @package    School_Management_System
 * @subpackage School_Management_System/includes/public
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * The public-facing functionality of the plugin.
 */
class SMS_Public extends SMS_Base {

    /**
     * Initialize the class and set its properties.
     */
    public function __construct($plugin_name, $version) {
        parent::__construct();
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Register the stylesheets for the public-facing side of the site.
     */
    public function enqueue_styles() {
        wp_enqueue_style(
            $this->plugin_name,
            SMS_PLUGIN_URL . 'public/css/sms-public.css',
            array(),
            $this->version,
            'all'
        );
    }

    /**
     * Register the JavaScript for the public-facing side of the site.
     */
    public function enqueue_scripts() {
        wp_enqueue_script(
            $this->plugin_name,
            SMS_PLUGIN_URL . 'public/js/sms-public.js',
            array('jquery'),
            $this->version,
            false
        );

        // Localize script for AJAX
        wp_localize_script(
            $this->plugin_name,
            'sms_public_ajax',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('sms_public_nonce'),
                'strings' => array(
                    'processing' => __('Processing...', 'school-management-system'),
                    'error' => __('An error occurred. Please try again.', 'school-management-system'),
                    'success' => __('Operation completed successfully.', 'school-management-system')
                )
            )
        );
    }
}