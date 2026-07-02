<?php
/**
 * Admin reservation action controller.
 *
 * @package MeetingRoomBooking
 */

namespace MRB\Controllers\Admin;

use MRB\Services\ReservationService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles admin POST actions for reservations:
 *   - mrb_admin_update_reservation  (edit form save)
 *   - mrb_change_status             (quick approve / reject / pending)
 */
class ReservationActionController {

	private ReservationService $service;

	public function __construct( ReservationService $service ) {
		$this->service = $service;
	}

	// ── Admin edit save ───────────────────────────────────────────────────────

	/**
	 * Handle the "Update Reservation" form submission from the edit page.
	 *
	 * Hook: admin_post_mrb_admin_update_reservation
	 */
	public function handleUpdate(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'meeting-room-booking' ) );
		}

		$id = isset( $_POST['reservation_id'] ) ? absint( $_POST['reservation_id'] ) : 0;
		if ( $id <= 0 ) {
			wp_die( esc_html__( 'Invalid reservation ID.', 'meeting-room-booking' ) );
		}

		$nonce = isset( $_POST['mrb_nonce'] )
			? sanitize_text_field( wp_unslash( $_POST['mrb_nonce'] ) )
			: '';

		if ( ! wp_verify_nonce( $nonce, 'mrb_admin_edit_' . $id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'meeting-room-booking' ) );
		}

		$redirect_url = admin_url( 'admin.php?page=mrb-reservations&action=edit&id=' . $id );

		$result = $this->service->adminEdit( $id, wp_unslash( $_POST ) );

		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( add_query_arg(
				[
					'mrb_error'     => 'update_failed',
					'mrb_error_msg' => rawurlencode( $result->get_error_message() ),
				],
				$redirect_url
			) );
			exit;
		}

		wp_safe_redirect( add_query_arg( 'mrb_message', 'updated', $redirect_url ) );
		exit;
	}

	// ── Quick status change ───────────────────────────────────────────────────

	/**
	 * Handle the quick "Approve / Reject / Pending" action links.
	 *
	 * These are GET requests protected by a nonce in the URL — identical to
	 * the pattern used by WordPress core's post trash/restore actions.
	 *
	 * Hook: admin_post_mrb_change_status
	 */
	public function handleStatusChange(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'meeting-room-booking' ) );
		}

		$id     = isset( $_GET['id'] )     ? absint( $_GET['id'] )                       : 0;
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';

		if ( $id <= 0 ) {
			wp_die( esc_html__( 'Invalid reservation ID.', 'meeting-room-booking' ) );
		}

		$nonce = isset( $_GET['_wpnonce'] )
			? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) )
			: '';

		if ( ! wp_verify_nonce( $nonce, 'mrb_change_status_' . $id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'meeting-room-booking' ) );
		}

		$redirect_url = admin_url( 'admin.php?page=mrb-reservations' );

		$result = $this->service->changeStatus( $id, $status );

		if ( empty( $result['success'] ) ) {
			wp_safe_redirect( add_query_arg(
				[
					'mrb_error'     => 'status_failed',
					'mrb_error_msg' => rawurlencode( $result['message'] ?? __( 'Failed to update status.', 'meeting-room-booking' ) ),
				],
				$redirect_url
			) );
			exit;
		}

		wp_safe_redirect( add_query_arg(
			[
				'mrb_message' => 'status_updated',
				'mrb_msg'     => rawurlencode( $result['message'] ?? __( 'Status updated.', 'meeting-room-booking' ) ),
			],
			$redirect_url
		) );
		exit;
	}
}
