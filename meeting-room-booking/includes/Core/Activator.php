<?php

namespace MRB\Core;

if (!defined('ABSPATH')) {
    exit;
}

class Activator
{
    public static function activate(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charsetCollate = $wpdb->get_charset_collate();

        $roomsTable = $wpdb->prefix . 'mrb_rooms';
        $reservationsTable = $wpdb->prefix . 'mrb_reservations';

        $roomsSql = "CREATE TABLE {$roomsTable} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(191) NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id)
        ) {$charsetCollate};";

        $reservationsSql = "CREATE TABLE {$reservationsTable} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            mobile VARCHAR(30) NOT NULL,
            email VARCHAR(191) NOT NULL,
            meeting_title VARCHAR(191) NOT NULL,
            meeting_date DATE NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            description TEXT NULL,
            room_id BIGINT UNSIGNED NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY meeting_date_idx (meeting_date),
            KEY mobile_idx (mobile),
            KEY status_idx (status),
            KEY room_id_idx (room_id),
            KEY conflict_idx (meeting_date, room_id, start_time, end_time)
        ) {$charsetCollate};";

        dbDelta($roomsSql);
        dbDelta($reservationsSql);

        self::seedDefaultRooms();
    }

    private static function seedDefaultRooms(): void
    {
        global $wpdb;

        $roomsTable = $wpdb->prefix . 'mrb_rooms';
        $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$roomsTable}");

        if ($count > 0) {
            return;
        }

        $now = current_time('mysql');

        for ($i = 1; $i <= 3; $i++) {
            $wpdb->insert(
                $roomsTable,
                [
                    'name' => 'Room ' . $i,
                    'created_at' => $now,
                ],
                ['%s', '%s']
            );
        }
    }
}
