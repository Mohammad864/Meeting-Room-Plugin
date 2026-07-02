<?php
/**
 * Reservation repository.
 *
 * @package MeetingRoomBooking
 */

namespace MRB\Repositories;

use MRB\Contracts\ReservationRepositoryInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides all database read/write operations for reservations.
 *
 * Depends on the global $wpdb instance stored at construction time so that
 * unit tests can substitute a mock without relying on the global.
 */
class ReservationRepository implements ReservationRepositoryInterface {

	/** @var \wpdb */
	private $wpdb;
	private string $table;
	private string $room_table;

	public function __construct() {
		global $wpdb;
		$this->wpdb       = $wpdb;
		$this->table      = $wpdb->prefix . 'mrb_reservations';
		$this->room_table = $wpdb->prefix . 'mrb_rooms';
	}

	// ── Write ─────────────────────────────────────────────────────────────────

	/**
	 * Insert a new reservation row.
	 *
	 * @param  array $data Column => value pairs.
	 * @return int         Inserted ID, or 0 on failure.
	 */
	public function create( array $data ): int {
		$this->wpdb->insert( $this->table, $data );
		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Update a reservation by primary key.
	 *
	 * Returns true even when 0 rows were affected (data identical to stored
	 * values) — that is a valid, non-error outcome.
	 *
	 * @param  int   $id   Reservation ID.
	 * @param  array $data Column => value pairs.
	 * @return bool        False only on database error.
	 */
	public function update( int $id, array $data ): bool {
		$result = $this->wpdb->update( $this->table, $data, [ 'id' => $id ] );
		return false !== $result;
	}

	// ── Read – single row ─────────────────────────────────────────────────────

	public function findById( int $id ): ?array {
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT r.*, rm.name AS room_name
				 FROM {$this->table} r
				 LEFT JOIN {$this->room_table} rm ON rm.id = r.room_id
				 WHERE r.id = %d",
				$id
			),
			ARRAY_A
		);
		return $row ?: null;
	}

	/**
	 * Find a reservation by its guest edit token.
	 *
	 * Performs a LEFT JOIN so the room name is available in the returned row
	 * without an extra query (used by the manage-reservation view and emails).
	 *
	 * @param  string $token Edit token.
	 * @return array|null    Row including `room_name`, or null.
	 */
	public function findByToken( string $token ): ?array {
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT r.*, rm.name AS room_name
				 FROM {$this->table} r
				 LEFT JOIN {$this->room_table} rm ON rm.id = r.room_id
				 WHERE r.edit_token = %s",
				$token
			),
			ARRAY_A
		);
		return $row ?: null;
	}

	// ── Read – collections ────────────────────────────────────────────────────

	/**
	 * Query reservations with optional search, date, and status filters.
	 *
	 * @param  array{search?: string, date?: string, status?: string, limit?: int, offset?: int} $args
	 * @return array<int, array<string, mixed>>
	 */
	public function query( array $args = [] ): array {
		[ $where, $params ] = $this->buildWhereClause( $args );

		$limit    = isset( $args['limit'] )  ? (int) $args['limit']  : 20;
		$offset   = isset( $args['offset'] ) ? (int) $args['offset'] : 0;
		$params[] = $limit;
		$params[] = $offset;

		$results = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table} {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d",
				...$params
			),
			ARRAY_A
		);

		return is_array( $results ) ? $results : [];
	}

	/**
	 * Count rows matching the same filters as query().
	 *
	 * @param  array $args Same keys as query().
	 * @return int
	 */
	public function getTotalCount( array $args = [] ): int {
		[ $where, $params ] = $this->buildWhereClause( $args );

		$sql = "SELECT COUNT(*) FROM {$this->table} {$where}";

		$prepared = empty( $params )
			? $sql
			: $this->wpdb->prepare( $sql, ...$params );

		return (int) $this->wpdb->get_var( $prepared );
	}

	/** Returns APPROVED reservations for a specific date, ordered by start_time. */
	public function findByDate( string $date ): array {
		$results = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table}
				 WHERE meeting_date = %s AND status = 'approved'
				 ORDER BY start_time ASC",
				$date
			),
			ARRAY_A
		);
		return is_array( $results ) ? $results : [];
	}

	/**
	 * Returns non-cancelled, non-rejected reservations for a date.
	 *
	 * Used by MinimumRoomsCalculator which counts both pending and approved.
	 */
	public function findActiveByDate( string $date ): array {
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
		return is_array( $results ) ? $results : [];
	}

	/**
	 * Returns approved booked slots for a date range.
	 *
	 * Used by the availability calendar on the booking form.
	 */
	public function getBookedTimesBetweenDates( string $start_date, string $end_date ): array {
		$results = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT meeting_date, start_time, end_time, status
				 FROM {$this->table}
				 WHERE meeting_date BETWEEN %s AND %s AND status = 'approved'
				 ORDER BY meeting_date ASC, start_time ASC",
				$start_date,
				$end_date
			),
			ARRAY_A
		);
		return is_array( $results ) ? $results : [];
	}

	/**
	 * Returns reservations in a date range, optionally filtered by status.
	 *
	 * $end_date is exclusive (< end_date) to match FullCalendar conventions.
	 */
	public function findByDateRange( string $start_date, string $end_date, string $status = '' ): array {
		if ( $status ) {
			$results = $this->wpdb->get_results(
				$this->wpdb->prepare(
					"SELECT * FROM {$this->table}
					 WHERE meeting_date >= %s AND meeting_date < %s AND status = %s
					 ORDER BY meeting_date ASC, start_time ASC",
					$start_date,
					$end_date,
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
					$start_date,
					$end_date
				),
				ARRAY_A
			);
		}
		return is_array( $results ) ? $results : [];
	}

	// ── Overlap / conflict queries ────────────────────────────────────────────

	public function findOverlapping( string $date, string $start_time, string $end_time ): array {
		$results = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table}
				 WHERE meeting_date = %s AND status = 'approved'
				   AND start_time < %s AND end_time > %s",
				$date,
				$end_time,
				$start_time
			),
			ARRAY_A
		);
		return is_array( $results ) ? $results : [];
	}

	public function countOverlappingApproved(
		string $date,
		string $start_time,
		string $end_time,
		int $exclude_id = 0
	): int {
		if ( $exclude_id > 0 ) {
			return (int) $this->wpdb->get_var( $this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table}
				 WHERE meeting_date = %s AND status = 'approved'
				   AND start_time < %s AND end_time > %s
				   AND id != %d",
				$date, $end_time, $start_time, $exclude_id
			) );
		}

		return (int) $this->wpdb->get_var( $this->wpdb->prepare(
			"SELECT COUNT(*) FROM {$this->table}
			 WHERE meeting_date = %s AND status = 'approved'
			   AND start_time < %s AND end_time > %s",
			$date, $end_time, $start_time
		) );
	}

	public function countOverlappingApprovedForRoom(
		int $room_id,
		string $date,
		string $start_time,
		string $end_time,
		int $exclude_id = 0
	): int {
		if ( $exclude_id > 0 ) {
			return (int) $this->wpdb->get_var( $this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table}
				 WHERE meeting_date = %s AND status = 'approved'
				   AND room_id = %d
				   AND start_time < %s AND end_time > %s
				   AND id != %d",
				$date, $room_id, $end_time, $start_time, $exclude_id
			) );
		}

		return (int) $this->wpdb->get_var( $this->wpdb->prepare(
			"SELECT COUNT(*) FROM {$this->table}
			 WHERE meeting_date = %s AND status = 'approved'
			   AND room_id = %d
			   AND start_time < %s AND end_time > %s",
			$date, $room_id, $end_time, $start_time
		) );
	}

	/**
	 * Find the ID of the first room that is free for the requested slot.
	 */
	public function findAvailableRoom( string $date, string $start_time, string $end_time ): ?int {
		$room_id = $this->wpdb->get_var( $this->wpdb->prepare(
			"SELECT r.id
			 FROM {$this->room_table} r
			 WHERE r.id NOT IN (
			     SELECT room_id FROM {$this->table}
			     WHERE meeting_date = %s AND status = 'approved'
			       AND room_id IS NOT NULL
			       AND start_time < %s AND end_time > %s
			 )
			 ORDER BY r.id ASC
			 LIMIT 1",
			$date, $end_time, $start_time
		) );

		return $room_id ? (int) $room_id : null;
	}

	// ── Private helpers ───────────────────────────────────────────────────────

	/**
	 * Build a WHERE clause and params array from filter arguments.
	 *
	 * @param  array $args Filter keys: search, date, status.
	 * @return array{0: string, 1: array<mixed>}
	 */
	private function buildWhereClause( array $args ): array {
		$where  = 'WHERE 1=1';
		$params = [];

		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $this->wpdb->esc_like( $args['search'] ) . '%';
			$where   .= ' AND (first_name LIKE %s OR last_name LIKE %s OR mobile LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		if ( ! empty( $args['date'] ) ) {
			$where   .= ' AND meeting_date = %s';
			$params[] = $args['date'];
		}

		if ( ! empty( $args['status'] ) ) {
			$where   .= ' AND status = %s';
			$params[] = $args['status'];
		}

		return [ $where, $params ];
	}
}
