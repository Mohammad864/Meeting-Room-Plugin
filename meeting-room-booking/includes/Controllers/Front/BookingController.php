<?php
/**
 * Front-end booking form controller.
 *
 * @package MeetingRoomBooking
 */

namespace MRB\Controllers\Front;

use DateInterval;
use DateTimeImmutable;
use MRB\Repositories\ReservationRepository;
use MRB\Repositories\RoomRepository;
use MRB\Services\ReservationService;
use MRB\Support\ErrorMessages;
use MRB\Support\View;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the [mrb_booking_form] shortcode, booking form submission, and
 * the availability-calendar AJAX endpoint.
 */
class BookingController {

	private ReservationService $service;

	public function __construct( ReservationService $service ) {
		$this->service = $service;
	}

	// ── Shortcode ─────────────────────────────────────────────────────────────

	/**
	 * Render the booking form shortcode.
	 *
	 * @return string HTML output.
	 */
	public function renderShortcode(): string {
		wp_enqueue_style( 'mrb-booking-form' );
		wp_enqueue_script( 'mrb-booking-form' );

		$status_message = null;
		$mrb_status     = isset( $_GET['mrb_status'] )
			? sanitize_key( wp_unslash( $_GET['mrb_status'] ) )
			: '';

		if ( 'success' === $mrb_status ) {
			$token = isset( $_GET['token'] )
				? sanitize_text_field( wp_unslash( $_GET['token'] ) )
				: '';

			if ( $token ) {
				$manage_link    = esc_url( home_url( '/reservation/' . $token ) );
				$status_message = [
					'type'  => 'success',
					'token' => $token,
					'link'  => $manage_link,
				];
			}
		}

		if ( 'error' === $mrb_status ) {
			$error_code     = isset( $_GET['mrb_error'] )
				? sanitize_key( wp_unslash( $_GET['mrb_error'] ) )
				: 'unknown';
			$status_message = [
				'type'    => 'error',
				'message' => ErrorMessages::get( $error_code ),
			];
		}

		return View::render( 'front/booking-form', [
			'statusMessage' => $status_message,
			'actionUrl'     => esc_url( admin_url( 'admin-post.php' ) ),
		] );
	}

	// ── Form submission ───────────────────────────────────────────────────────

	/**
	 * Handle booking form POST submission.
	 *
	 * Hook: admin_post_mrb_submit_booking
	 *       admin_post_nopriv_mrb_submit_booking
	 */
	public function handleSubmit(): void {
		if (
			empty( $_POST['mrb_nonce'] ) ||
			! wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_POST['mrb_nonce'] ) ),
				'mrb_submit_booking'
			)
		) {
			wp_die( esc_html__( 'Invalid nonce.', 'meeting-room-booking' ) );
		}

		$result      = $this->service->create( wp_unslash( $_POST ) );
		$redirect_url = wp_get_referer() ?: home_url();

		if ( ! $result['success'] ) {
			$error_message = $result['errors'][0] ?? __( 'Invalid data.', 'meeting-room-booking' );
			wp_safe_redirect(
				add_query_arg(
					[
						'mrb_status' => 'error',
						'mrb_error'  => rawurlencode( $error_message ),
					],
					$redirect_url
				)
			);
			exit;
		}

		// Email notifications are handled inside ReservationService::create().
		wp_safe_redirect(
			add_query_arg(
				[
					'mrb_status' => 'success',
					'token'      => $result['token'],
				],
				$redirect_url
			)
		);
		exit;
	}

	// ── AJAX: availability calendar ───────────────────────────────────────────

	/**
	 * Return booked time slots for a 7-day window starting at the requested date.
	 *
	 * Hook: wp_ajax_mrb_get_booked_times_range
	 *       wp_ajax_nopriv_mrb_get_booked_times_range
	 */
	public function handleGetBookedTimesRange(): void {
		if ( ! check_ajax_referer( 'mrb_get_booked_times_range', 'nonce', false ) ) {
			wp_send_json_error( [
				'message' => __( 'Security check failed. Please refresh the page and try again.', 'meeting-room-booking' ),
			] );
			return;
		}

		$selected_date = isset( $_POST['date'] )
			? sanitize_text_field( wp_unslash( $_POST['date'] ) )
			: '';

		if ( ! $selected_date || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $selected_date ) ) {
			wp_send_json_error( [
				'message' => __( 'Invalid date.', 'meeting-room-booking' ),
			] );
			return;
		}

		try {
			$selected   = new DateTimeImmutable( $selected_date );
			$start_date = $selected->format( 'Y-m-d' );
			$end_date   = $selected->add( new DateInterval( 'P6D' ) )->format( 'Y-m-d' );
		} catch ( \Exception $e ) {
			wp_send_json_error( [ 'message' => __( 'Invalid date.', 'meeting-room-booking' ) ] );
			return;
		}

		$repository  = new ReservationRepository();
		$room_repo   = new RoomRepository();
		$rows        = $repository->getBookedTimesBetweenDates( $start_date, $end_date );
		$rooms       = $room_repo->all();
		$total_rooms = max( 1, count( $rooms ) );

		$grouped = [];
		for ( $i = 0; $i <= 6; $i++ ) {
			$day  = $selected->modify( '+' . $i . ' days' );
			$date = $day->format( 'Y-m-d' );

			$grouped[ $date ] = [
				'date'        => $date,
				'day_label'   => $day->format( 'D' ),
				'month_label' => $day->format( 'M j' ),
				'full_label'  => $day->format( 'l, F j, Y' ),
				'is_selected' => ( $date === $selected_date ),
				'slots'       => [],
			];
		}

		foreach ( $rows as $row ) {
			$date       = isset( $row['meeting_date'] ) ? (string) $row['meeting_date'] : '';
			$start_time = isset( $row['start_time'] )   ? (string) $row['start_time']   : '';
			$end_time   = isset( $row['end_time'] )     ? (string) $row['end_time']     : '';

			if ( ! isset( $grouped[ $date ] ) || '' === $start_time || '' === $end_time ) {
				continue;
			}

			$grouped[ $date ]['slots'][] = [
				'start_time' => substr( $start_time, 0, 5 ),
				'end_time'   => substr( $end_time,   0, 5 ),
				'status'     => (string) ( $row['status'] ?? '' ),
			];
		}

		wp_send_json_success( [
			'selected_date' => $selected_date,
			'start_date'    => $start_date,
			'end_date'      => $end_date,
			'days'          => array_values( $grouped ),
			'total_rooms'   => $total_rooms,
		] );
	}
}
