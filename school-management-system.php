<?php
/**
 * Plugin Name: School Management System
 * Plugin URI: https://github.com/kiunye/school-management-system
 * Description: A comprehensive WordPress-based School Management System for streamlined administrative tasks, communication, and financial management.
 * Version: 1.0.0
 * Author: Kiunye Araya
 * Author URI: https://github.com/kiunye
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: school-management-system
 * Domain Path: /languages
 * Requires at least: 6.0
 * Tested up to: 6.4
 * Requires PHP: 8.0
 * Network: false
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Currently plugin version.
 */
define('SMS_VERSION', '1.0.0');

/**
 * Plugin directory path.
 */
define('SMS_PLUGIN_DIR', plugin_dir_path(__FILE__));

/**
 * Plugin directory URL.
 */
define('SMS_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Plugin basename.
 */
define('SMS_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * The code that runs during plugin activation.
 */
function activate_school_management_system() {
    require_once SMS_PLUGIN_DIR . 'includes/core/class-sms-activator.php';
    SMS_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_school_management_system() {
    require_once SMS_PLUGIN_DIR . 'includes/core/class-sms-deactivator.php';
    SMS_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_school_management_system');
register_deactivation_hook(__FILE__, 'deactivate_school_management_system');

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require SMS_PLUGIN_DIR . 'includes/core/class-sms-loader.php';

/**
 * Begins execution of the plugin.
 */
function run_school_management_system() {
    $plugin = new SMS_Loader();
    $plugin->run();
}

run_school_management_system();