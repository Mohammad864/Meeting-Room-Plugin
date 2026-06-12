<?php
/**
 * Plugin Name: Meeting Room Booking
 * Description: Minimal production-ready meeting room booking system.
 * Version: 1.0.0
 * Author: Mohammad Taghipoor
 * Text Domain: meeting-room-booking
 */

if (!defined('ABSPATH')) {
    exit;
}

define('MRB_VERSION', '1.0.0');
define('MRB_PLUGIN_FILE', __FILE__);
define('MRB_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MRB_PLUGIN_URL', plugin_dir_url(__FILE__));

spl_autoload_register(function ($class) {
    $prefix = 'MRB\\';

    if (strpos($class, $prefix) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = MRB_PLUGIN_DIR . 'includes/' . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

register_activation_hook(__FILE__, ['MRB\\Core\\Activator', 'activate']);

add_action('plugins_loaded', function () {
    $plugin = new MRB\Core\Plugin();
    $plugin->boot();
});
