<?php
/**
 * Asset registration.
 *
 * @package MeetingRoomBooking
 */

namespace MRB\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers plugin CSS and JS with WordPress so controllers can enqueue
 * them on demand without re-specifying paths or versions.
 *
 * NOTE: mrb-admin-dashboard.css was removed because the file never existed
 * in the plugin bundle. mrb-admin-calendar.css now has no dependency.
 */
class Assets {

	public function register(): void {
		add_action( 'wp_enqueue_scripts',    [ $this, 'registerFrontendAssets' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'registerAdminAssets'    ] );
	}

	/** Register front-end styles and scripts. */
	public function registerFrontendAssets(): void {
		wp_register_style(
			'mrb-booking-form',
			MRB_PLUGIN_URL . 'assets/css/booking-form.css',
			[],
			$this->version( 'assets/css/booking-form.css' )
		);

		wp_register_style(
			'mrb-manage-reservation',
			MRB_PLUGIN_URL . 'assets/css/mrb-manage-reservation.css',
			[],
			$this->version( 'assets/css/mrb-manage-reservation.css' )
		);

		// booking-form.js depends on jQuery (bundled with WordPress).
		wp_register_script(
			'mrb-booking-form',
			MRB_PLUGIN_URL . 'assets/js/booking-form.js',
			[ 'jquery' ],
			$this->version( 'assets/js/booking-form.js' ),
			true
		);

		// Inject AJAX configuration: window.MRBBookingForm.ajaxUrl / .nonce
		wp_add_inline_script(
			'mrb-booking-form',
			'window.MRBBookingForm = ' . wp_json_encode( [
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'mrb_get_booked_times_range' ),
			] ) . ';',
			'before'
		);
	}

	/** Register admin-only styles. */
	public function registerAdminAssets(): void {
		wp_register_style(
			'mrb-admin-calendar',
			MRB_PLUGIN_URL . 'assets/css/mrb-admin-calendar.css',
			[],
			$this->version( 'assets/css/mrb-admin-calendar.css' )
		);
	}

	// ── Private helpers ───────────────────────────────────────────────────────

	/**
	 * Return a cache-busting version string for an asset file.
	 *
	 * Uses filemtime() in development so browsers pick up changes immediately.
	 * Falls back to MRB_VERSION when the file is absent (e.g. production build).
	 *
	 * @param  string $relative_path Path relative to the plugin root.
	 * @return string Version string.
	 */
	private function version( string $relative_path ): string {
		$file = MRB_PLUGIN_DIR . ltrim( $relative_path, '/' );
		return file_exists( $file ) ? (string) filemtime( $file ) : MRB_VERSION;
	}
}
