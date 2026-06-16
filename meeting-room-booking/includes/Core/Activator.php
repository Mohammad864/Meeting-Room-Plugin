<?php

namespace MRB\Core;

if (!defined('ABSPATH')) {
    exit;
}

class Activator
{
    /**
     * Run on plugin activation
     */
    public static function activate(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charsetCollate = $wpdb->get_charset_collate();

        $roomsTable = $wpdb->prefix . 'mrb_rooms';
        $reservationsTable = $wpdb->prefix . 'mrb_reservations';

        // 1. Create Rooms Table
        $roomsSql = "CREATE TABLE {$roomsTable} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(191) NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id)
        ) {$charsetCollate};";

        // 2. Create Reservations Table
        // Added composite index and foreign key constraint
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
            edit_token VARCHAR(64) NOT NULL,
            cancelled_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY edit_token_idx (edit_token),
            KEY meeting_date_idx (meeting_date),
            KEY status_idx (status),
            KEY date_status_idx (meeting_date, status),
            KEY room_id_idx (room_id),
            CONSTRAINT fk_mrb_room
                FOREIGN KEY (room_id)
                REFERENCES {$roomsTable}(id)
                ON DELETE SET NULL
        ) {$charsetCollate};";

        dbDelta($roomsSql);
        dbDelta($reservationsSql);

        // 3. Initialize default room count if not exists
        if (get_option('mrb_number_of_rooms', false) === false) {
            update_option('mrb_number_of_rooms', 3);
        }

        // 4. Initial Sync
        self::syncRoomsToConfiguredCount();

        // 5. Setup Rewrite Rules
        add_rewrite_rule('^reservation/([a-zA-Z0-9]+)/?$', 'index.php?mrb_token=$matches[1]', 'top');
        flush_rewrite_rules();
    }

    /**
     * Sync mrb_rooms table with configured room count.
     * This is safe to call during activation OR from the settings page.
     */
    public static function syncRoomsToConfiguredCount(): void
    {
        global $wpdb;

        $roomsTable = $wpdb->prefix . 'mrb_rooms';
        $reservationsTable = $wpdb->prefix . 'mrb_reservations';

        // Get count from options (default to 3 if missing)
        $desiredCount = max(1, (int) get_option('mrb_number_of_rooms', 3));

        // Get current rooms
        $existingRooms = $wpdb->get_results(
            "SELECT id FROM {$roomsTable} ORDER BY id ASC",
            ARRAY_A
        );

        $currentCount = count($existingRooms);
        $now = current_time('mysql');

        // CASE 1: Need more rooms
        if ($currentCount < $desiredCount) {
            $roomsToAdd = $desiredCount - $currentCount;
            for ($i = 1; $i <= $roomsToAdd; $i++) {
                $roomNumber = $currentCount + $i;
                $wpdb->insert(
                    $roomsTable,
                    [
                        'name'       => 'Room ' . $roomNumber,
                        'created_at' => $now,
                    ],
                    ['%s', '%s']
                );
            }
        }

        // CASE 2: Too many rooms (Safe deletion)
        elseif ($currentCount > $desiredCount) {
            // Take the extra rooms from the end of the list
            $extraRooms = array_slice($existingRooms, $desiredCount);

            foreach ($extraRooms as $room) {
                $roomId = (int) $room['id'];

                // Check if this room is used by ANY reservation (including old ones)
                $isUsed = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$reservationsTable} WHERE room_id = %d",
                    $roomId
                ));

                // ONLY delete if NO reservations are linked to this room ID
                if ((int)$isUsed === 0) {
                    $wpdb->delete($roomsTable, ['id' => $roomId], ['%d']);
                }
            }
        }
    }
}
