<?php
/**
 * Admin settings controller.
 *
 * @package MeetingRoomBooking
 */

namespace MRB\Controllers\Admin;

use MRB\Core\Activator;
use MRB\Support\View;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the "Settings" admin submenu page.
 */
class SettingsController {

	/** Register the submenu page with WordPress. */
	public function register(): void {
		add_submenu_page(
			'mrb-reservations',
			__( 'Meeting Room Settings', 'meeting-room-booking' ),
			__( 'Settings',             'meeting-room-booking' ),
			'manage_options',
			'mrb-settings',
			[ $this, 'show' ]
		);
	}

	/** WordPress menu page callback. */
	public function show(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'meeting-room-booking' ) );
		}

		$number_of_rooms = absint( get_option( 'mrb_number_of_rooms', 3 ) );

		View::output( 'admin/settings', [ 'numberOfRooms' => $number_of_rooms ] );
	}

	/**
	 * Handle settings form submission.
	 *
	 * Hook: admin_post_mrb_save_settings
	 */
	public function handleSave(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'meeting-room-booking' ) );
		}

		// wp_unslash() before sanitize/verify — required by WPCS and defensively
		// correct when running under environments with magic_quotes active.
		$nonce = isset( $_POST['mrb_settings_nonce'] )
			? sanitize_text_field( wp_unslash( $_POST['mrb_settings_nonce'] ) )
			: '';

		if ( ! wp_verify_nonce( $nonce, 'mrb_save_settings' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'meeting-room-booking' ) );
		}

		$old_count = absint( get_option( 'mrb_number_of_rooms', 3 ) );
		$new_count = isset( $_POST['mrb_number_of_rooms'] )
			? max( 1, absint( $_POST['mrb_number_of_rooms'] ) )
			: 3;

		update_option( 'mrb_number_of_rooms', $new_count );
		Activator::syncRoomsToConfiguredCount();

		$actual_count = $this->getActualRoomCount();

		$redirect_url = add_query_arg(
			[ 'page' => 'mrb-settings', 'settings-updated' => '1' ],
			admin_url( 'admin.php' )
		);

		if ( $new_count < $old_count && $actual_count > $new_count ) {
			$redirect_url = add_query_arg( 'rooms-warning', '1', $redirect_url );
		}

		wp_safe_redirect( $redirect_url );
		exit;
	}

	// ── Private helpers ───────────────────────────────────────────────────────

	private function getActualRoomCount(): int {
		global $wpdb;
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}mrb_rooms" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
}
