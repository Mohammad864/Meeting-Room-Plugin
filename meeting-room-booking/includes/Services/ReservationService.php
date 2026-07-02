<?php
/**
 * Reservation domain service.
 *
 * @package MeetingRoomBooking
 */

namespace MRB\Services;

use MRB\Contracts\NotificationServiceInterface;
use MRB\Contracts\ReservationRepositoryInterface;
use MRB\Enums\ReservationStatus;
use MRB\Support\Validator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Core business-logic for creating, updating, cancelling, and approving
 * reservations. Delegates persistence to the repository and emails to the
 * notification service.
 */
class ReservationService {

	private ReservationRepositoryInterface $repository;
	private NotificationServiceInterface $notifications;
	private RoomAllocator $room_allocator;

	public function __construct(
		ReservationRepositoryInterface $repository,
		NotificationServiceInterface $notifications,
		RoomAllocator $room_allocator
	) {
		$this->repository     = $repository;
		$this->notifications  = $notifications;
		$this->room_allocator = $room_allocator;
	}

	// ── Public API ────────────────────────────────────────────────────────────

	/**
	 * Create a new reservation.
	 *
	 * @param  array $data Raw (wp_unslashed) POST data.
	 * @return array{success: bool, id?: int, token?: string, errors?: string[]}
	 */
	public function create( array $data ): array {
		$clean = $this->sanitizeReservationData( $data );

		$validation_error = Validator::validateReservation( $clean );
		if ( null !== $validation_error ) {
			return [
				'success' => false,
				'errors'  => [ $validation_error->get_error_message() ],
			];
		}

		if ( ! $this->validateMaxDuration( $clean['start_time'], $clean['end_time'] ) ) {
			return [
				'success' => false,
				'errors'  => [ __( 'A reservation cannot exceed 8 hours.', 'meeting-room-booking' ) ],
			];
		}

		// current_time('Y-m-d') respects the WordPress timezone setting.
		if ( $clean['meeting_date'] < current_time( 'Y-m-d' ) ) {
			return [
				'success' => false,
				'errors'  => [ __( 'Reservations cannot be created in the past.', 'meeting-room-booking' ) ],
			];
		}

		$token = $this->generateToken();

		$clean['edit_token'] = $token;
		$clean['status']     = ReservationStatus::PENDING;
		$clean['created_at'] = current_time( 'mysql' );
		$clean['updated_at'] = current_time( 'mysql' );

		$inserted_id = $this->repository->create( $clean );
		if ( ! $inserted_id ) {
			return [
				'success' => false,
				'errors'  => [ __( 'Database error: could not save reservation.', 'meeting-room-booking' ) ],
			];
		}

		$reservation = $this->repository->findById( $inserted_id );
		if ( $reservation ) {
			$this->sendNotificationSafely(
				fn() => $this->notifications->sendReservationCreatedEmails( $reservation )
			);
		}

		return [
			'success' => true,
			'id'      => $inserted_id,
			'token'   => $token,
		];
	}

	/**
	 * Update a reservation via its guest edit token.
	 *
	 * @param  string $token Guest edit token.
	 * @param  array  $data  Raw (wp_unslashed) POST data.
	 * @return true|\WP_Error
	 */
	public function updateReservation( string $token, array $data ) {
		$token = sanitize_text_field( $token );

		if ( '' === $token ) {
			return new \WP_Error( 'missing_token', 'Missing reservation token.' );
		}

		$reservation = $this->repository->findByToken( $token );
		if ( ! $reservation || ! is_array( $reservation ) ) {
			return new \WP_Error( 'reservation_not_found', 'Reservation not found.', [ 'token' => $token ] );
		}

		if ( ReservationStatus::isLocked( $reservation['status'] ?? '' ) ) {
			return new \WP_Error(
				'reservation_locked',
				'This reservation can no longer be updated because it is cancelled or rejected.',
				[ 'status' => $reservation['status'] ]
			);
		}

		$start_ts = strtotime( ( $reservation['meeting_date'] ?? '' ) . ' ' . ( $reservation['start_time'] ?? '' ) );
		if ( ! $start_ts ) {
			return new \WP_Error( 'invalid_existing_datetime', 'The existing reservation date or time is invalid.' );
		}
		if ( $start_ts < time() ) {
			return new \WP_Error( 'past_reservation', 'Past reservations cannot be updated.' );
		}

		$clean            = $this->sanitizeReservationData( $data );
		$validation_error = Validator::validateReservation( $clean );
		if ( null !== $validation_error ) {
			return $validation_error;
		}

		if ( ! $this->validateMaxDuration( $clean['start_time'], $clean['end_time'] ) ) {
			return new \WP_Error( 'max_duration_exceeded', 'A reservation cannot exceed 8 hours.' );
		}

		if ( $clean['meeting_date'] < current_time( 'Y-m-d' ) ) {
			return new \WP_Error( 'past_meeting_date', 'Reservations cannot be updated to a past date.' );
		}

		$clean['updated_at'] = current_time( 'mysql' );

		// Revert to pending when guest edits an approved reservation.
		if ( ReservationStatus::APPROVED === ( $reservation['status'] ?? '' ) ) {
			$clean['status']  = ReservationStatus::PENDING;
			$clean['room_id'] = null;
		}

		if ( ! $this->repository->update( (int) $reservation['id'], $clean ) ) {
			return new \WP_Error( 'database_update_failed', 'Database update failed.' );
		}

		$updated = $this->repository->findByToken( $token );
		if ( $updated ) {
			$this->sendNotificationSafely(
				fn() => $this->notifications->sendReservationUpdatedEmails( $updated )
			);
		}

		return true;
	}

	/**
	 * Cancel a reservation via its guest edit token.
	 *
	 * @param  string $token Guest edit token.
	 * @return true|\WP_Error
	 */
	public function cancelByToken( string $token ) {
		$token = sanitize_text_field( $token );

		if ( '' === $token ) {
			return new \WP_Error( 'missing_token', 'Missing reservation token.' );
		}

		$reservation = $this->repository->findByToken( $token );
		if ( ! $reservation || ! is_array( $reservation ) ) {
			return new \WP_Error( 'reservation_not_found', 'Reservation not found.', [ 'token' => $token ] );
		}

		if ( ReservationStatus::CANCELLED === ( $reservation['status'] ?? '' ) ) {
			return new \WP_Error( 'already_cancelled', 'This reservation is already cancelled.' );
		}

		$start_ts = strtotime( ( $reservation['meeting_date'] ?? '' ) . ' ' . ( $reservation['start_time'] ?? '' ) );
		if ( ! $start_ts ) {
			return new \WP_Error( 'invalid_existing_datetime', 'The existing reservation date or time is invalid.' );
		}
		if ( $start_ts < time() ) {
			return new \WP_Error( 'past_reservation', 'Past reservations cannot be cancelled.' );
		}

		if ( ! $this->repository->update( (int) $reservation['id'], [
			'status'       => ReservationStatus::CANCELLED,
			'cancelled_at' => current_time( 'mysql' ),
			'updated_at'   => current_time( 'mysql' ),
			'room_id'      => null,
		] ) ) {
			return new \WP_Error( 'database_cancel_failed', 'Database error while cancelling reservation.' );
		}

		$cancelled           = $reservation;
		$cancelled['status'] = ReservationStatus::CANCELLED;

		$this->sendNotificationSafely(
			fn() => $this->notifications->sendReservationCancelledEmails( $cancelled )
		);

		return true;
	}

	/**
	 * Admin: full edit of a reservation (all fields + status + room_id).
	 *
	 * @param  int   $id   Reservation ID.
	 * @param  array $data Raw POST data.
	 * @return true|\WP_Error
	 */
	public function adminEdit( int $id, array $data ) {
		$status = isset( $data['status'] ) ? sanitize_key( $data['status'] ) : ReservationStatus::PENDING;
		if ( ! ReservationStatus::isValid( $status ) ) {
			$status = ReservationStatus::PENDING;
		}

		$room_id = isset( $data['room_id'] ) ? absint( $data['room_id'] ) : null;

		$update_data = [
			'first_name'    => sanitize_text_field( $data['first_name']    ?? '' ),
			'last_name'     => sanitize_text_field( $data['last_name']     ?? '' ),
			'mobile'        => sanitize_text_field( $data['mobile']        ?? '' ),
			'email'         => sanitize_email(      $data['email']         ?? '' ),
			'meeting_title' => sanitize_text_field( $data['meeting_title'] ?? '' ),
			'meeting_date'  => sanitize_text_field( $data['meeting_date']  ?? '' ),
			'start_time'    => $this->normalizeTime( $data['start_time']   ?? '' ),
			'end_time'      => $this->normalizeTime( $data['end_time']     ?? '' ),
			'description'   => sanitize_textarea_field( $data['description'] ?? '' ),
			'status'        => $status,
			'room_id'       => $room_id > 0 ? $room_id : null,
			'updated_at'    => current_time( 'mysql' ),
		];

		$validation_error = Validator::validateReservation( $update_data );
		if ( null !== $validation_error ) {
			return $validation_error;
		}

		if ( ! $this->validateMaxDuration( $update_data['start_time'], $update_data['end_time'] ) ) {
			return new \WP_Error( 'max_duration_exceeded', 'A reservation cannot exceed 8 hours.' );
		}

		if ( ! $this->repository->update( $id, $update_data ) ) {
			return new \WP_Error( 'database_update_failed', 'Database update failed.' );
		}

		$updated = $this->repository->findById( $id );
		if ( $updated ) {
			$this->sendNotificationSafely(
				fn() => $this->notifications->sendReservationUpdatedEmails( $updated )
			);
		}

		return true;
	}

	/**
	 * Admin: quick status change (approve / reject / pending).
	 *
	 * Automatically allocates a room when approving.
	 *
	 * @param  int    $id     Reservation ID.
	 * @param  string $status Target status.
	 * @return array{success: bool, message: string}
	 */
	public function changeStatus( int $id, string $status ): array {
		if ( ! ReservationStatus::isAdminSettable( $status ) ) {
			return [
				'success' => false,
				'message' => __( 'Invalid reservation status.', 'meeting-room-booking' ),
			];
		}

		$reservation = $this->repository->findById( $id );
		if ( ! $reservation ) {
			return [
				'success' => false,
				'message' => __( 'Reservation not found.', 'meeting-room-booking' ),
			];
		}

		$update_data = [
			'status'     => $status,
			'updated_at' => current_time( 'mysql' ),
		];

		if ( ReservationStatus::APPROVED === $status ) {
			$room_id = $this->room_allocator->allocate(
				$reservation['meeting_date'],
				$reservation['start_time'],
				$reservation['end_time']
			);

			if ( ! $room_id ) {
				return [
					'success' => false,
					'message' => __( 'No meeting rooms are available for this time slot.', 'meeting-room-booking' ),
				];
			}

			$update_data['room_id'] = $room_id;
		}

		if ( ! $this->repository->update( $id, $update_data ) ) {
			return [
				'success' => false,
				'message' => __( 'Database error while updating reservation.', 'meeting-room-booking' ),
			];
		}

		$updated = $this->repository->findById( $id );
		if ( $updated ) {
			$this->sendNotificationSafely(
				fn() => $this->notifications->sendReservationStatusChangedEmails( $updated )
			);
		}

		return [
			'success' => true,
			'message' => __( 'Reservation status updated successfully.', 'meeting-room-booking' ),
		];
	}

	/**
	 * Find a reservation by its guest edit token.
	 *
	 * @param  string $token Edit token.
	 * @return array|null    Reservation row or null.
	 */
	public function findByToken( string $token ): ?array {
		$result = $this->repository->findByToken( sanitize_text_field( $token ) );
		return is_array( $result ) ? $result : null;
	}

	// ── Private helpers ───────────────────────────────────────────────────────

	private function sanitizeReservationData( array $data ): array {
		return [
			'first_name'    => sanitize_text_field( $data['first_name']    ?? '' ),
			'last_name'     => sanitize_text_field( $data['last_name']     ?? '' ),
			'mobile'        => sanitize_text_field( $data['mobile']        ?? '' ),
			'email'         => sanitize_email(      $data['email']         ?? '' ),
			'meeting_title' => sanitize_text_field( $data['meeting_title'] ?? '' ),
			'meeting_date'  => sanitize_text_field( $data['meeting_date']  ?? '' ),
			'start_time'    => $this->normalizeTime( $data['start_time']   ?? '' ),
			'end_time'      => $this->normalizeTime( $data['end_time']     ?? '' ),
			'description'   => sanitize_textarea_field( $data['description'] ?? '' ),
		];
	}

	/** Normalise HH:MM to HH:MM:SS; pass HH:MM:SS through unchanged. */
	private function normalizeTime( string $time ): string {
		$time = trim( sanitize_text_field( $time ) );
		return preg_match( '/^\d{2}:\d{2}$/', $time ) ? $time . ':00' : $time;
	}

	private function validateMaxDuration( string $start, string $end ): bool {
		$s = strtotime( $this->normalizeTime( $start ) );
		$e = strtotime( $this->normalizeTime( $end ) );

		if ( ! $s || ! $e ) {
			return false;
		}

		$hours = ( $e - $s ) / 3600;
		return $hours > 0 && $hours <= 8;
	}

	private function generateToken(): string {
		try {
			return bin2hex( random_bytes( 16 ) );
		} catch ( \Throwable $e ) {
			return wp_generate_password( 32, false, false );
		}
	}

	/**
	 * Execute a notification callback, swallowing any exceptions.
	 *
	 * Mail failures must never bubble up and break the main operation.
	 *
	 * @param callable $fn Callback that sends one or more emails.
	 */
	private function sendNotificationSafely( callable $fn ): void {
		try {
			$fn();
		} catch ( \Throwable $e ) {
			error_log( '[MRB] Notification failed: ' . $e->getMessage() );
		}
	}
}
