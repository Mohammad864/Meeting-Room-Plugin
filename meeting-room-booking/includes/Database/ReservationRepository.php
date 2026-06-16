<?php

namespace MRB\Database;

if (!defined('ABSPATH')) {
    exit;
}

class ReservationRepository
{
    private $wpdb;
    private $table;

    public function __construct()
    {
        global $wpdb;

        $this->wpdb  = $wpdb;
        $this->table = $wpdb->prefix . 'mrb_reservations';
    }

    /**
     * Insert new reservation
     */
    public function create(array $data): int
    {
        $this->wpdb->insert(
            $this->table,
            $data
        );

        return (int) $this->wpdb->insert_id;
    }

    /**
     * Update reservation
     */
    public function update(int $id, array $data): bool
    {
        $updated = $this->wpdb->update(
            $this->table,
            $data,
            ['id' => $id]
        );

        return $updated !== false;
    }

    /**
     * Cancel reservation
     */
    public function cancel(int $id): bool
    {
        return $this->update($id, [
            'status' => 'cancelled'
        ]);
    }

    /**
     * Find reservation by ID
     */
    public function findById(int $id): ?array
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE id = %d",
                $id
            ),
            ARRAY_A
        );

        return $row ?: null;
    }

    /**
     * Find reservation by edit token
     */
    public function findByToken(string $token): ?array
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE edit_token = %s",
                $token
            ),
            ARRAY_A
        );

        return $row ?: null;
    }

    /**
     * Query reservations with filters
     */
    public function query(array $args = []): array
    {
        $where = "WHERE 1=1";

        $params = [];

        /**
         * Search by name or mobile
         */
        if (!empty($args['search'])) {

            $search = '%' . $this->wpdb->esc_like($args['search']) . '%';

            $where .= " AND (
                first_name LIKE %s
                OR last_name LIKE %s
                OR mobile LIKE %s
            )";

            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }

        /**
         * Filter by date
         */
        if (!empty($args['date'])) {

            $where .= " AND meeting_date = %s";

            $params[] = $args['date'];
        }

        /**
         * Filter by status
         */
        if (!empty($args['status'])) {

            $where .= " AND status = %s";

            $params[] = $args['status'];
        }

        $limit  = isset($args['limit']) ? (int) $args['limit'] : 20;
        $offset = isset($args['offset']) ? (int) $args['offset'] : 0;

        $sql = "SELECT *
                FROM {$this->table}
                $where
                ORDER BY created_at DESC
                LIMIT %d OFFSET %d";

        $params[] = $limit;
        $params[] = $offset;

        $prepared = $this->wpdb->prepare($sql, ...$params);

        return $this->wpdb->get_results($prepared, ARRAY_A);
    }

    /**
     * Count reservations (for pagination)
     */
    public function getTotalCount(array $args = []): int
    {
        $where = "WHERE 1=1";

        $params = [];

        if (!empty($args['search'])) {

            $search = '%' . $this->wpdb->esc_like($args['search']) . '%';

            $where .= " AND (
                first_name LIKE %s
                OR last_name LIKE %s
                OR mobile LIKE %s
            )";

            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }

        if (!empty($args['date'])) {

            $where .= " AND meeting_date = %s";

            $params[] = $args['date'];
        }

        if (!empty($args['status'])) {

            $where .= " AND status = %s";

            $params[] = $args['status'];
        }

        $sql = "SELECT COUNT(*) FROM {$this->table} $where";

        if (!empty($params)) {

            $sql = $this->wpdb->prepare($sql, ...$params);
        }

        return (int) $this->wpdb->get_var($sql);
    }

    /**
     * Alias required by WP_List_Table compatibility
     */
    public function count(array $args = []): int
    {
        return $this->getTotalCount($args);
    }

    /**
     * Get reservations by date
     * (useful for room allocation algorithm)
     */
    public function findByDate(string $date): array
    {
        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT *
                 FROM {$this->table}
                 WHERE meeting_date = %s
                 AND status = 'approved'
                 ORDER BY start_time ASC",
                $date
            ),
            ARRAY_A
        );

        return $results ?: [];
    }

    /**
     * Get reservations that overlap with a time slot
     */
    public function findOverlapping(
        string $date,
        string $startTime,
        string $endTime
    ): array {

        $sql = "
            SELECT *
            FROM {$this->table}
            WHERE meeting_date = %s
            AND status = 'approved'
            AND (
                start_time < %s
                AND end_time > %s
            )
        ";

        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                $sql,
                $date,
                $endTime,
                $startTime
            ),
            ARRAY_A
        );

        return $results ?: [];
    }
}
