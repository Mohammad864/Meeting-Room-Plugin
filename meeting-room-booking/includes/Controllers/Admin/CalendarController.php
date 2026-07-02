<?php
/**
 * Admin calendar controller.
 *
 * @package MeetingRoomBooking
 */

namespace MRB\Controllers\Admin;

use MRB\Contracts\ReservationRepositoryInterface;
use MRB\Support\View;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the "Reservation Calendar" admin page and its AJAX events endpoint.
 *
 * FullCalendar JS is now bundled locally (assets/vendor/fullcalendar/)
 * instead of loaded from an external CDN. WordPress.org guidelines
 * prohibit loading scripts/styles from third-party CDN URLs.
 */
class CalendarController {

	private ReservationRepositoryInterface $repository;

	public function __construct( ReservationRepositoryInterface $repository ) {
		$this->repository = $repository;
	}

	// ── Page render ───────────────────────────────────────────────────────────

	/** WordPress menu page callback. */
	public function show(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'meeting-room-booking' ) );
		}

		View::output( 'admin/calendar', [] );
	}

	// ── Asset enqueue ─────────────────────────────────────────────────────────

	/**
	 * Enqueue FullCalendar (local bundle) and calendar.js on the calendar page.
	 *
	 * Hook: admin_enqueue_scripts
	 *
	 * @param string $hook Current admin page hook suffix (unused — we key off $_GET['page']).
	 */
	public function enqueueAssets( string $hook ): void {
		$page = isset( $_GET['page'] )
			? sanitize_key( wp_unslash( $_GET['page'] ) )
			: '';

		if ( 'mrb-calendar' !== $page ) {
			return;
		}

		// FullCalendar is bundled locally to comply with WordPress.org guidelines
		// that prohibit loading from external CDNs. The minified build lives at
		// assets/vendor/fullcalendar/index.global.min.js.
		// Download URL: https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js
		wp_enqueue_style( 'mrb-admin-calendar' );

		wp_enqueue_script(
			'mrb-fullcalendar',
			MRB_PLUGIN_URL . 'assets/vendor/fullcalendar/index.global.min.js',
			[],
			'6.1.15',
			true
		);

		wp_enqueue_script(
			'mrb-calendar-admin',
			MRB_PLUGIN_URL . 'assets/admin/js/calendar.js',
			[ 'mrb-fullcalendar' ],
			MRB_VERSION,
			true
		);

		wp_localize_script(
			'mrb-calendar-admin',
			'MRBCalendar',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'mrb_calendar_nonce' ),
				'editUrl' => admin_url( 'admin.php?page=mrb-reservations&action=edit&id=' ),
			]
		);
	}

	// ── AJAX handler ──────────────────────────────────────────────────────────

	/**
	 * Return FullCalendar event objects for the requested date range.
	 *
	 * Hook: wp_ajax_mrb_get_calendar_events
	 */
	public function handleGetEvents(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				[ 'message' => __( 'Permission denied.', 'meeting-room-booking' ) ],
				403
			);
			return;
		}

		check_ajax_referer( 'mrb_calendar_nonce', 'nonce' );

		$start  = isset( $_GET['start']  ) ? sanitize_text_field( wp_unslash( $_GET['start']  ) ) : '';
		$end    = isset( $_GET['end']    ) ? sanitize_text_field( wp_unslash( $_GET['end']    ) ) : '';
		$status = isset( $_GET['status'] ) ? sanitize_key(        wp_unslash( $_GET['status'] ) ) : '';

		$allowed_statuses = [ 'pending', 'approved', 'rejected', 'cancelled' ];
		if ( $status && ! in_array( $status, $allowed_statuses, true ) ) {
			$status = '';
		}

		if ( ! $start || ! $end ) {
			wp_send_json( [] );
			return;
		}

		$start_date = substr( $start, 0, 10 );
		$end_date   = substr( $end,   0, 10 );

		$rows = $this->repository->findByDateRange( $start_date, $end_date, $status );

		if ( ! $rows ) {
			wp_send_json( [] );
			return;
		}

		$events = [];

		foreach ( $rows as $row ) {
			$id = isset( $row['id'] ) ? (int) $row['id'] : 0;
			if ( $id <= 0 ) {
				continue;
			}

			$first_name    = isset( $row['first_name'] )    ? sanitize_text_field( $row['first_name'] )        : '';
			$last_name     = isset( $row['last_name'] )     ? sanitize_text_field( $row['last_name'] )         : '';
			$full_name     = trim( $first_name . ' ' . $last_name );
			$meeting_title = ! empty( $row['meeting_title'] )
				? sanitize_text_field( $row['meeting_title'] )
				: __( 'Untitled Meeting', 'meeting-room-booking' );

			$res_status = ! empty( $row['status'] ) ? sanitize_key( $row['status'] ) : 'pending';
			/* translators: %d: Room ID number. */
			$room_label = ! empty( $row['room_id'] )
				? sprintf( __( 'Room #%d', 'meeting-room-booking' ), (int) $row['room_id'] )
				: __( 'No room assigned', 'meeting-room-booking' );

			$title        = $meeting_title . ( $full_name ? ' - ' . $full_name : '' );
			$meeting_date = isset( $row['meeting_date'] ) ? sanitize_text_field( $row['meeting_date'] ) : '';
			$start_time   = isset( $row['start_time'] )   ? sanitize_text_field( $row['start_time'] )   : '';
			$end_time     = isset( $row['end_time'] )     ? sanitize_text_field( $row['end_time'] )     : '';

			if ( ! $meeting_date || ! $start_time || ! $end_time ) {
				continue;
			}

			$color = $this->getStatusColor( $res_status );

			$events[] = [
				'id'              => $id,
				'title'           => $title,
				'start'           => $meeting_date . 'T' . $start_time,
				'end'             => $meeting_date . 'T' . $end_time,
				'url'             => admin_url( 'admin.php?page=mrb-reservations&action=edit&id=' . $id ),
				'backgroundColor' => $color,
				'borderColor'     => $color,
				'extendedProps'   => [
					'reservation_id' => $id,
					'status'         => $res_status,
					'room'           => $room_label,
					'mobile'         => isset( $row['mobile'] )      ? sanitize_text_field( $row['mobile'] )          : '',
					'email'          => isset( $row['email'] )        ? sanitize_email( $row['email'] )                : '',
					'description'    => isset( $row['description'] )  ? sanitize_textarea_field( $row['description'] ) : '',
				],
			];
		}

		wp_send_json( $events );
	}

	// ── Private helpers ───────────────────────────────────────────────────────

	private function getStatusColor( string $status ): string {
		$colors = [
			'pending'   => '#f59e0b',
			'approved'  => '#16a34a',
			'rejected'  => '#dc2626',
			'cancelled' => '#6b7280',
		];
		return $colors[ sanitize_key( $status ) ] ?? '#6b7280';
	}
}
