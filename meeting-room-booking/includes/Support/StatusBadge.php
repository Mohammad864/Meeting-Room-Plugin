<?php
/**
 * Status badge renderer.
 *
 * @package MeetingRoomBooking
 */

namespace MRB\Support;

use MRB\Enums\ReservationStatus;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders a coloured inline badge for a reservation status value.
 *
 * Callers receive already-escaped HTML and must not double-escape it.
 */
class StatusBadge {

	/**
	 * Return the badge HTML for a given status.
	 *
	 * The returned string is safe to echo directly — all values are
	 * escaped or come from a hard-coded whitelist.
	 *
	 * @param  string $status Reservation status constant.
	 * @return string         HTML badge element.
	 */
	public static function render( string $status ): string {
		$status = sanitize_key( $status );

		$colors = [
			ReservationStatus::PENDING   => '#f0ad4e',
			ReservationStatus::APPROVED  => '#46b450',
			ReservationStatus::REJECTED  => '#dc3232',
			ReservationStatus::CANCELLED => '#666666',
		];

		$color = $colors[ $status ] ?? '#777777';
		$label = ReservationStatus::label( $status );

		return sprintf(
			'<span class="mrb-status-badge mrb-status-%s" style="display:inline-block;padding:3px 8px;border-radius:4px;background:%s;color:#fff;font-size:12px;font-weight:600;">%s</span>',
			esc_attr( $status ),
			esc_attr( $color ),
			esc_html( $label )
		);
	}

	/** Private constructor — this class is not meant to be instantiated. */
	private function __construct() {}
}
