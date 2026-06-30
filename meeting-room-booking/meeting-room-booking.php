<?php
/**
 * Plugin Name: Meeting Room Booking
 * Description: MVC meeting room booking system for WordPress.
 * Version: 1.0.0
 * Author: Mohammad Taghipoor
 * Text Domain: meeting-room-booking
 */

if (!defined("ABSPATH")) {
    exit();
}

define("MRB_VERSION", "1.0.0");
define("MRB_PLUGIN_FILE", __FILE__);
define("MRB_PLUGIN_DIR", plugin_dir_path(__FILE__));
define("MRB_PLUGIN_URL", plugin_dir_url(__FILE__));

// Autoloader: MRB\Foo\Bar → includes/Foo/Bar.php
spl_autoload_register(static function (string $class): void {
    $prefix = "MRB\\";

    if (strpos($class, $prefix) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file =
        MRB_PLUGIN_DIR .
        "includes/" .
        str_replace("\\", "/", $relative) .
        ".php";

    if (file_exists($file)) {
        require_once $file;
    }
});

register_activation_hook(__FILE__, [\MRB\Core\Activator::class, "activate"]);

add_action("plugins_loaded", static function (): void {
    $container = \MRB\Core\Container::build();
    $plugin = new \MRB\Core\Plugin($container);
    $plugin->boot();
});
