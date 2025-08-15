<?php
/**
 * Hook management system for the plugin.
 *
 * Provides centralized hook management and event handling.
 *
 * @package    School_Management_System
 * @subpackage School_Management_System/includes/core
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Hook management system.
 */
class SMS_Hook_Manager {

    /**
     * Registered hooks storage.
     */
    private static $hooks = array();

    /**
     * Register a new hook.
     */
    public static function register_hook($hook_name, $callback, $priority = 10, $accepted_args = 1) {
        if (!isset(self::$hooks[$hook_name])) {
            self::$hooks[$hook_name] = array();
        }

        self::$hooks[$hook_name][] = array(
            'callback' => $callback,
            'priority' => $priority,
            'accepted_args' => $accepted_args
        );

        add_action($hook_name, $callback, $priority, $accepted_args);
    }

    /**
     * Fire a custom hook.
     */
    public static function fire_hook($hook_name, ...$args) {
        do_action($hook_name, ...$args);
    }

    /**
     * Get all registered hooks.
     */
    public static function get_hooks() {
        return self::$hooks;
    }

    /**
     * Remove a hook.
     */
    public static function remove_hook($hook_name, $callback, $priority = 10) {
        remove_action($hook_name, $callback, $priority);
        
        if (isset(self::$hooks[$hook_name])) {
            foreach (self::$hooks[$hook_name] as $key => $hook) {
                if ($hook['callback'] === $callback && $hook['priority'] === $priority) {
                    unset(self::$hooks[$hook_name][$key]);
                    break;
                }
            }
        }
    }
}