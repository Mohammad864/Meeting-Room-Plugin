<?php

namespace MRB\Repositories;

use MRB\Contracts\ReservationRepositoryInterface;

if (!defined('ABSPATH')) {
    exit;
}

class ReservationRepository implements ReservationRepositoryInterface
{
    private $wpdb;
    private string $table;
    private string $roomTable;

    public function __construct()
    {
        global $wpdb;

        $this->wpdb      = $wpdb;
        $this->table     = $wpdb->prefix . 'mrb_reservations';
        $this->roomTable = $wpdb->prefix . 'mrb_rooms';
    }

    // ── Write ─────────────────────────────────────────────────────────────────

    public function create(array $data): int
    {
        $this->wpdb->insert($this->table, $data);

        return (int) $this->wpdb->insert_id;
    }

    public function update(int $id, array $data): bool
    {
        $result = $this->wpdb->update($this->table, $data, ['id' => $id]);

        return $result !== false;
    }

    /** Convenience: update the status column only. */
    public function updateStatus(int $id, string $status): bool
    {
        return $this->update($id, ['status' => $status]);
    }

    /** Convenience: mark a reservation cancelled. */
    public function cancel(int $id): bool
    {
        return $this->update($id, ['status' => 'cancelled']);
    }

    // ── Read – single row ─────────────────────────────────────────────────────

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

    // ── Read – collections ────────────────────────────────────────────────────

    public function query(array $args = []): array
    {
        $where  = 'WHERE 1=1';
        $params = [];

        if (!empty($args['search'])) {
            $search  = '%' . $this->wpdb->esc_like($args['search']) . '%';
            $where  .= ' AND (first_name LIKE %s OR last_name LIKE %s OR mobile LIKE %s)';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }

        if (!empty($args['date'])) {
            $where   .= ' AND meeting_date = %s';
            $params[] = $args['date'];
        }

        if (!empty($args['status'])) {
            $where   .= ' AND status = %s';
            $params[] = $args['status'];
        }

        $limit  = isset($args['limit'])  ? (int) $args['limit']  : 20;
        $offset = isset($args['offset']) ? (int) $args['offset'] : 0;

        $sql      = "SELECT * FROM {$this->table} {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;

        $results = $this->wpdb->get_results(
            $this->wpdb->prepare($sql, ...$params),
            ARRAY_A
        );

        return is_array($results) ? $results : [];
    }

    public function getTotalCount(array $args = []): int
    {
        $where  = 'WHERE 1=1';
        $params = [];

        if (!empty($args['search'])) {
            $search  = '%' . $this->wpdb->esc_like($args['search']) . '%';
            $where  .= ' AND (first_name LIKE %s OR last_name LIKE %s OR mobile LIKE %s)';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }

        if (!empty($args['date'])) {
            $where   .= ' AND meeting_date = %s';
            $params[] = $args['date'];
        }

        if (!empty($args['status'])) {
            $where   .= ' AND status = %s';
            $params[] = $args['status'];
        }

        $sql = "SELECT COUNT(*) FROM {$this->table} {$where}";

        if (!empty($params)) {
            $sql = $this->wpdb->prepare($sql, ...$params);
        }

        return (int) $this->wpdb->get_var($sql);
    }

    /** Alias kept for WP_List_Table compatibility. */
    public function count(array $args = []): int
    {
        return $this->getTotalCount($args);
    }

    /** Returns APPROVED reservations for a specific date, ordered by start_time. */
    public function findByDate(string $date): array
    {
        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table}
                 WHERE meeting_date = %s AND status = 'approved'
                 ORDER BY start_time ASC",
                $date
            ),
            ARRAY_A
        );

        return is_array($results) ? $results : [];
    }

    /**
     * Returns non-cancelled, non-rejected reservations for a date.
     * Used by MinimumRoomsCalculator which should count pending + approved.
     */
    public function findActiveByDate(string $date): array
    {
        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT start_time, end_time FROM {$this->table}
                 WHERE meeting_date = %s
                 AND status NOT IN ('cancelled', 'rejected')
                 ORDER BY start_time ASC",
                $date
            ),
            ARRAY_A
        );

        return is_array($results) ? $results : [];
    }

    public function getBookedTimesByDate(string $date): array
    {
        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT meeting_date, start_time, end_time, status
                 FROM {$this->table}
                 WHERE meeting_date = %s AND status = 'approved'
                 ORDER BY start_time ASC",
                $date
            ),
            ARRAY_A
        );

        return is_array($results) ? $results : [];
    }

    public function getBookedTimesBetweenDates(string $startDate, string $endDate): array
    {
        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT meeting_date, start_time, end_time, status
                 FROM {$this->table}
                 WHERE meeting_date BETWEEN %s AND %s AND status = 'approved'
                 ORDER BY meeting_date ASC, start_time ASC",
                $startDate,
                $endDate
            ),
            ARRAY_A
        );

        return is_array($results) ? $results : [];
    }

    /**
     * Returns reservations in a date range, optionally filtered by status.
     *
     * Used by CalendarController::handleGetEvents() AJAX endpoint.
     * $endDate is exclusive (< endDate) to match FullCalendar conventions.
     */
    public function findByDateRange(string $startDate, string $endDate, string $status = ''): array
    {
        if ($status) {
            $results = $this->wpdb->get_results(
                $this->wpdb->prepare(
                    "SELECT * FROM {$this->table}
                     WHERE meeting_date >= %s AND meeting_date < %s AND status = %s
                     ORDER BY meeting_date ASC, start_time ASC",
                    $startDate,
                    $endDate,
                    $status
                ),
                ARRAY_A
            );
        } else {
            $results = $this->wpdb->get_results(
                $this->wpdb->prepare(
                    "SELECT * FROM {$this->table}
                     WHERE meeting_date >= %s AND meeting_date < %s
                     ORDER BY meeting_date ASC, start_time ASC",
                    $startDate,
                    $endDate
                ),
                ARRAY_A
            );
        }

        return is_array($results) ? $results : [];
    }

    // ── Overlap / conflict queries ────────────────────────────────────────────

    public function findOverlapping(
        string $date,
        string $startTime,
        string $endTime
    ): array {
        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table}
                 WHERE meeting_date = %s AND status = 'approved'
                 AND start_time < %s AND end_time > %s",
                $date, $endTime, $startTime
            ),
            ARRAY_A
        );

        return is_array($results) ? $results : [];
    }

    public function countOverlappingApproved(
        string $date,
        string $startTime,
        string $endTime,
        int $excludeId = 0
    ): int {
        $sql = "SELECT COUNT(*) FROM {$this->table}
                WHERE meeting_date = %s AND status = 'approved'
                AND start_time < %s AND end_time > %s";

        if ($excludeId > 0) {
            $sql .= ' AND id != %d';

            return (int) $this->wpdb->get_var(
                $this->wpdb->prepare($sql, $date, $endTime, $startTime, $excludeId)
            );
        }

        return (int) $this->wpdb->get_var(
            $this->wpdb->prepare($sql, $date, $endTime, $startTime)
        );
    }

    /**
     * Count overlapping APPROVED reservations for a specific room.
     *
     * Used by ConflictDetector to check per-room slot availability.
     */
    public function countOverlappingApprovedForRoom(
        int $roomId,
        string $date,
        string $startTime,
        string $endTime,
        int $excludeId = 0
    ): int {
        $sql = "SELECT COUNT(*) FROM {$this->table}
                WHERE meeting_date = %s AND status = 'approved'
                AND room_id = %d
                AND start_time < %s AND end_time > %s";

        if ($excludeId > 0) {
            $sql .= ' AND id != %d';

            return (int) $this->wpdb->get_var(
                $this->wpdb->prepare($sql, $date, $roomId, $endTime, $startTime, $excludeId)
            );
        }

        return (int) $this->wpdb->get_var(
            $this->wpdb->prepare($sql, $date, $roomId, $endTime, $startTime)
        );
    }

    // ── Room allocation ───────────────────────────────────────────────────────

    /**
     * Find the first room that has no approved overlapping reservation.
     *
     * Used as the fallback in ReservationService::changeStatus() when
     * RoomAllocator is not available, but RoomAllocator is the preferred path.
     */
    public function findAvailableRoom(
        string $date,
        string $startTime,
        string $endTime
    ): ?int {
        $sql = "SELECT r.id
                FROM {$this->roomTable} r
                WHERE r.id NOT IN (
                    SELECT room_id FROM {$this->table}
                    WHERE meeting_date = %s AND status = 'approved'
                    AND room_id IS NOT NULL
                    AND start_time < %s AND end_time > %s
                )
                ORDER BY r.id ASC
                LIMIT 1";

        $roomId = $this->wpdb->get_var(
            $this->wpdb->prepare($sql, $date, $endTime, $startTime)
        );

        return $roomId ? (int) $roomId : null;
    }
}
