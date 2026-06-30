<?php

namespace MRB\Repositories;

if (!defined('ABSPATH')) {
    exit;
}

class RoomRepository
{
    private $wpdb;
    private string $table;

    public function __construct()
    {
        global $wpdb;

        $this->wpdb  = $wpdb;
        $this->table = $wpdb->prefix . 'mrb_rooms';
    }

    public function all(): array
    {
        return $this->wpdb->get_results(
            "SELECT * FROM {$this->table} ORDER BY id ASC",
            ARRAY_A
        ) ?: [];
    }

    public function findNameById(?int $roomId): string
    {
        if (!$roomId) {
            return '-';
        }

        $name = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT name FROM {$this->table} WHERE id = %d",
                $roomId
            )
        );

        return $name ?: '-';
    }
}
