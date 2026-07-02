<?php
/**
 * Front-end manage-reservation page controller.
 *
 * @package MeetingRoomBooking
 */

namespace MRB\Controllers\Front;

use MRB\Enums\ReservationStatus;
use MRB\Contracts\ReservationRepositoryInterface;
use MRB\Support\View;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the /reservation/{token}/ front-end page.
 *
 * Fetches the reservation by token (with room name via LEFT JOIN),
 * determines whether the guest can still modify the booking,
 * then delegates HTML output to the view template.
 */
class ManageReservationController {

	private ReservationRepositoryInterface $repository;

	public function __construct( ReservationRepositoryInterface $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Show the manage-reservation page for a given token.
	 *
	 * Called from Plugin::handleReservationRoute().
	 *
	 * @param string $token Guest edit token.
	 */
	public function show( string $token ): void {
		wp_enqueue_style( 'mrb-manage-reservation' );

		if ( '' === $token ) {
			wp_die( esc_html__( 'Missing reservation token.', 'meeting-room-booking' ) );
		}

		$reservation = $this->repository->findByToken( $token );

		if ( ! $reservation ) {
			wp_die( esc_html__( 'Reservation not found.', 'meeting-room-booking' ) );
		}

		$status     = isset( $reservation['status'] ) ? sanitize_key( $reservation['status'] ) : '';
		$can_manage = ! ReservationStatus::isLocked( $status );

		View::output( 'front/manage-reservation', compact( 'reservation', 'token', 'can_manage' ) );
		exit;
	}
}
