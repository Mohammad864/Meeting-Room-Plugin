<?php
/**
 * Front-end guest reservation controller.
 *
 * @package MeetingRoomBooking
 */

namespace MRB\Controllers\Front;

use MRB\Services\ReservationService;
use MRB\Support\ErrorMessages;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles guest POST actions: update and cancel a reservation by token.
 *
 * No self-registration: hooks are wired in Plugin::boot().
 * Email notifications are handled inside ReservationService.
 */
class GuestReservationController {

	private ReservationService $service;

	public function __construct( ReservationService $service ) {
		$this->service = $service;
	}

	// ── Handlers ──────────────────────────────────────────────────────────────

	/**
	 * Handle guest reservation update form submission.
	 *
	 * Hook: admin_post_mrb_guest_update
	 *       admin_post_nopriv_mrb_guest_update
	 */
	public function handleUpdate(): void {
		$this->ensurePostRequest();

		$token = $this->getPostedToken();
		if ( '' === $token ) {
			$this->fail( 'missing_token' );
		}

		if ( ! $this->verifyNonce( 'mrb_guest_update_' . $token ) ) {
			$this->fail( 'security', $token );
		}

		$result = $this->service->updateReservation( $token, $this->getUnslashedPostData() );

		if ( is_wp_error( $result ) ) {
			$this->fail( $result->get_error_code(), $token );
		}

		if ( true !== $result ) {
			$this->fail( 'update_failed', $token );
		}

		$this->redirectToReservation( $token, [ 'updated' => '1' ] );
	}

	/**
	 * Handle guest reservation cancel form submission.
	 *
	 * Hook: admin_post_mrb_guest_cancel
	 *       admin_post_nopriv_mrb_guest_cancel
	 */
	public function handleCancel(): void {
		$this->ensurePostRequest();

		$token = $this->getPostedToken();
		if ( '' === $token ) {
			$this->fail( 'missing_token' );
		}

		if ( ! $this->verifyNonce( 'mrb_guest_cancel_' . $token ) ) {
			$this->fail( 'security', $token );
		}

		$result = $this->service->cancelByToken( $token );

		if ( is_wp_error( $result ) ) {
			$this->fail( $result->get_error_code(), $token );
		}

		if ( true !== $result ) {
			$this->fail( 'cancel_failed', $token );
		}

		$this->redirectToReservation( $token, [ 'cancelled' => '1' ] );
	}

	// ── Private helpers ───────────────────────────────────────────────────────

	private function ensurePostRequest(): void {
		$method = isset( $_SERVER['REQUEST_METHOD'] )
			? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) )
			: '';

		if ( 'POST' !== $method ) {
			$this->fail( 'invalid_request_method' );
		}
	}

	private function getPostedToken(): string {
		return empty( $_POST['token'] )
			? ''
			: sanitize_text_field( wp_unslash( $_POST['token'] ) );
	}

	private function verifyNonce( string $action ): bool {
		if ( empty( $_POST['mrb_nonce'] ) ) {
			return false;
		}
		return (bool) wp_verify_nonce(
			sanitize_text_field( wp_unslash( $_POST['mrb_nonce'] ) ),
			$action
		);
	}

	private function getUnslashedPostData(): array {
		return isset( $_POST ) && is_array( $_POST ) ? wp_unslash( $_POST ) : [];
	}

	/**
	 * Log the error, then redirect to the reservation page (or home) with
	 * an error query param so FrontendNotice can display a user-friendly message.
	 *
	 * Marked @return never because it always calls exit via wp_safe_redirect.
	 *
	 * @param string $error_code Sanitized error code key.
	 * @param string $token      Optional — when set, redirects back to the reservation page.
	 */
	private function fail( string $error_code, string $token = '' ): void {
		$error_code = sanitize_key( $error_code );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf(
				'[MRB] Guest action failed — code: %s, message: %s',
				$error_code,
				ErrorMessages::get( $error_code )
			) );
		}

		if ( '' !== $token ) {
			$this->redirectToReservation( $token, [ 'error' => $error_code ] );
		}

		wp_safe_redirect( add_query_arg( [ 'error' => $error_code ], home_url( '/' ) ) );
		exit;
	}

	private function redirectToReservation( string $token, array $query_args = [] ): void {
		$url = home_url( '/reservation/' . rawurlencode( sanitize_text_field( $token ) ) . '/' );

		if ( ! empty( $query_args ) ) {
			$url = add_query_arg( $query_args, $url );
		}

		wp_safe_redirect( $url );
		exit;
	}
}
