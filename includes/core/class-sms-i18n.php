<?php
/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @package    School_Management_System
 * @subpackage School_Management_System/includes/core
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Define the internationalization functionality.
 */
class SMS_i18n {

    /**
     * Whether textdomain has been loaded
     *
     * @var bool
     */
    private static $textdomain_loaded = false;

    /**
     * Load the plugin text domain for translation.
     */
    public function load_plugin_textdomain() {
        if (self::$textdomain_loaded) {
            return;
        }

        load_plugin_textdomain(
            'school-management-system',
            false,
            dirname(dirname(plugin_basename(__FILE__))) . '/languages/'
        );
        
        self::$textdomain_loaded = true;
    }

    /**
     * Ensure textdomain is loaded (can be called early if needed)
     */
    public static function ensure_textdomain_loaded() {
        if (!self::$textdomain_loaded) {
            $instance = new self();
            $instance->load_plugin_textdomain();
        }
    }
}