<?php

namespace MRB\Database;

if (!defined('ABSPATH')) {
    exit;
}

class RoomRepository
{
    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'mrb_rooms';
    }

    public function all(): array
    {
        global $wpdb;

        return $wpdb->get_results(
            "SELECT * FROM {$this->table} ORDER BY id ASC",
            ARRAY_A
        );
    }

    public function findNameById(?int $roomId): string
    {
        if (!$roomId) {
            return '-';
        }

        global $wpdb;

        $name = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT name FROM {$this->table} WHERE id = %d",
                $roomId
            )
        );

        return $name ?: '-';
    }
}
