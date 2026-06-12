<?php

namespace MRB\Database;

if (!defined('ABSPATH')) {
    exit;
}

class ReservationRepository
{
    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'mrb_reservations';
    }

    public function create(array $data): int
    {
        global $wpdb;

        $wpdb->insert(
            $this->table,
            [
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'mobile' => $data['mobile'],
                'email' => $data['email'],
                'meeting_title' => $data['meeting_title'],
                'meeting_date' => $data['meeting_date'],
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'description' => $data['description'],
                'room_id' => $data['room_id'],
                'status' => $data['status'],
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ],
            [
                '%s', '%s', '%s', '%s', '%s',
                '%s', '%s', '%s', '%s',
                '%d', '%s', '%s', '%s'
            ]
        );

        return (int) $wpdb->insert_id;
    }

    public function hasConflict(
        int $roomId,
        string $date,
        string $startTime,
        string $endTime,
        ?int $excludeReservationId = null
    ): bool {
        global $wpdb;

        $sql = "
            SELECT COUNT(*)
            FROM {$this->table}
            WHERE meeting_date = %s
            AND room_id = %d
            AND status = 'approved'
            AND start_time < %s
            AND end_time > %s
        ";

        $params = [$date, $roomId, $endTime, $startTime];

        if ($excludeReservationId) {
            $sql .= " AND id != %d";
            $params[] = $excludeReservationId;
        }

        $count = (int) $wpdb->get_var(
            $wpdb->prepare($sql, ...$params)
        );

        return $count > 0;
    }

    public function find(int $id): ?array
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE id = %d",
                $id
            ),
            ARRAY_A
        );

        return $row ?: null;
    }

    public function updateStatus(int $id, string $status, ?int $roomId = null): bool
    {
        global $wpdb;

        $data = [
            'status' => $status,
            'updated_at' => current_time('mysql'),
        ];

        $format = ['%s', '%s'];

        if ($roomId !== null) {
            $data['room_id'] = $roomId;
            $format[] = '%d';
        }

        return false !== $wpdb->update(
            $this->table,
            $data,
            ['id' => $id],
            $format,
            ['%d']
        );
    }

    public function query(array $args): array
    {
        global $wpdb;

        $where = ['1=1'];
        $params = [];

        if (!empty($args['search'])) {
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $where[] = "(first_name LIKE %s OR last_name LIKE %s OR mobile LIKE %s)";
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }

        if (!empty($args['date'])) {
            $where[] = "meeting_date = %s";
            $params[] = $args['date'];
        }

        $limit = isset($args['limit']) ? (int) $args['limit'] : 20;
        $offset = isset($args['offset']) ? (int) $args['offset'] : 0;

        $sql = "
            SELECT *
            FROM {$this->table}
            WHERE " . implode(' AND ', $where) . "
            ORDER BY meeting_date DESC, start_time DESC
            LIMIT %d OFFSET %d
        ";

        $params[] = $limit;
        $params[] = $offset;

        return $wpdb->get_results(
            $wpdb->prepare($sql, ...$params),
            ARRAY_A
        );
    }

    public function count(array $args): int
    {
        global $wpdb;

        $where = ['1=1'];
        $params = [];

        if (!empty($args['search'])) {
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $where[] = "(first_name LIKE %s OR last_name LIKE %s OR mobile LIKE %s)";
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }

        if (!empty($args['date'])) {
            $where[] = "meeting_date = %s";
            $params[] = $args['date'];
        }

        $sql = "
            SELECT COUNT(*)
            FROM {$this->table}
            WHERE " . implode(' AND ', $where);

        if (!empty($params)) {
            return (int) $wpdb->get_var($wpdb->prepare($sql, ...$params));
        }

        return (int) $wpdb->get_var($sql);
    }

    public function getApprovedByDate(string $date): array
    {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT start_time, end_time FROM {$this->table}
                 WHERE meeting_date = %s AND status = 'approved'
                 ORDER BY start_time ASC",
                $date
            ),
            ARRAY_A
        );
    }
}
