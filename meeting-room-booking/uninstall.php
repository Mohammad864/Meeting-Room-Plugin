<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * در نسخه production واقعی بهتر است حذف دیتابیس با option کنترل شود.
 * برای تست فنی، حذف کامل قابل قبول است.
 */

global $wpdb;

$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}mrb_reservations");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}mrb_rooms");
