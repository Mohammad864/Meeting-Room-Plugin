<?php
/**
 * Uninstall handler.
 *
 * Runs when the plugin is deleted from the WordPress Plugins screen.
 * Drops all plugin database tables and cleans up options.
 *
 * In a production plugin consider adding a "Delete data on uninstall" option
 * so data is only removed when the admin explicitly opts in.
 */

if (!defined("WP_UNINSTALL_PLUGIN")) {
    exit();
}

global $wpdb;

// Drop tables in dependency order (reservations first, then rooms).
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}mrb_reservations");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}mrb_rooms");

// Remove plugin options.
delete_option("mrb_number_of_rooms");
delete_option("mrb_admin_notification_email");
delete_option("mrb_from_email");
