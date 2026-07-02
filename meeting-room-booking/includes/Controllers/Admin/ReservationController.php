<?php
/**
 * Admin reservation list/edit controller.
 *
 * @package MeetingRoomBooking
 */

namespace MRB\Controllers\Admin;

use MRB\Contracts\ReservationRepositoryInterface;
use MRB\Enums\ReservationStatus;
use MRB\Services\MinimumRoomsCalculator;
use MRB\Support\View;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the "Meeting Bookings" admin menu page.
 *
 * Dispatches to index() (list) or edit() based on ?action=edit.
 */
class ReservationController {

	private ReservationRepositoryInterface $repository;
	private MinimumRoomsCalculator $calculator;

	public function __construct(
		ReservationRepositoryInterface $repository,
		MinimumRoomsCalculator $calculator
	) {
		$this->repository = $repository;
		$this->calculator = $calculator;
	}

	/**
	 * WordPress menu page callback — dispatches to edit() or index().
	 */
	public function dispatch(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'meeting-room-booking' ) );
		}

		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';

		if ( 'edit' === $action ) {
			$this->edit();
			return;
		}

		$this->index();
	}

	/** Reservation list page. */
	public function index(): void {
		$search = isset( $_GET['s'] )            ? sanitize_text_field( wp_unslash( $_GET['s'] ) )            : '';
		$date   = isset( $_GET['meeting_date'] ) ? sanitize_text_field( wp_unslash( $_GET['meeting_date'] ) ) : '';
		$status = isset( $_GET['status'] )       ? sanitize_key( wp_unslash( $_GET['status'] ) )              : '';

		if ( ! ReservationStatus::isValid( $status ) ) {
			$status = '';
		}

		$reservations    = $this->repository->query( [
			'search' => $search,
			'date'   => $date,
			'status' => $status,
			'limit'  => 100,
			'offset' => 0,
		] );

		// Use the filtered date for the room-count display; fall back to today.
		$calculation_date = $date ?: current_time( 'Y-m-d' );
		$minimum_rooms    = $this->calculateMinimumRooms( $calculation_date );

		View::output( 'admin/reservation-list', [
			'reservations'    => $reservations,
			'filters'         => compact( 'search', 'date', 'status' ),
			'minimumRooms'    => $minimum_rooms,
			'calculationDate' => $calculation_date,
		] );
	}

	/** Reservation edit form page. */
	public function edit(): void {
		global $wpdb;

		$id          = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$reservation = $id > 0 ? $this->repository->findById( $id ) : null;

		// Fetch rooms for the room select dropdown.
		$rooms = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT id, name FROM {$wpdb->prefix}mrb_rooms ORDER BY id ASC",
			ARRAY_A
		) ?: [];

		View::output( 'admin/reservation-edit', compact( 'id', 'reservation', 'rooms' ) );
	}

	// ── Private helpers ───────────────────────────────────────────────────────

	private function calculateMinimumRooms( string $date ): int {
		$rows = $this->repository->findActiveByDate( $date );
		if ( empty( $rows ) ) {
			return 0;
		}
		return $this->calculator->calculate( $rows );
	}
}
