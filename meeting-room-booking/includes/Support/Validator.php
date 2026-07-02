<?php
/**
 * Reservation input validator.
 *
 * @package MeetingRoomBooking
 */

namespace MRB\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stateless validator for reservation field data.
 *
 * Returns a WP_Error on the first failing field, or null when all fields pass.
 * The service layer is responsible for calling this before any persistence.
 */
class Validator {

	/**
	 * Validate sanitized reservation data.
	 *
	 * @param  array<string, mixed> $data Already-sanitized field values.
	 * @return \WP_Error|null             WP_Error with the first violation, or null on success.
	 */
	public static function validateReservation( array $data ): ?\WP_Error {
		// ── Required fields ────────────────────────────────────────────────
		$required = [
			'first_name'    => __( 'First name is required.',    'meeting-room-booking' ),
			'last_name'     => __( 'Last name is required.',     'meeting-room-booking' ),
			'mobile'        => __( 'Mobile number is required.', 'meeting-room-booking' ),
			'email'         => __( 'Email address is required.', 'meeting-room-booking' ),
			'meeting_title' => __( 'Meeting title is required.', 'meeting-room-booking' ),
			'meeting_date'  => __( 'Meeting date is required.',  'meeting-room-booking' ),
			'start_time'    => __( 'Start time is required.',    'meeting-room-booking' ),
			'end_time'      => __( 'End time is required.',      'meeting-room-booking' ),
		];

		foreach ( $required as $field => $message ) {
			if ( empty( $data[ $field ] ) ) {
				return new \WP_Error( 'missing_' . $field, $message );
			}
		}

		// ── Format validation ──────────────────────────────────────────────
		if ( ! is_email( $data['email'] ) ) {
			return new \WP_Error(
				'invalid_email',
				__( 'Email address is not valid.', 'meeting-room-booking' )
			);
		}

		if ( ! self::isValidDate( $data['meeting_date'] ) ) {
			return new \WP_Error(
				'invalid_meeting_date',
				__( 'Meeting date format is invalid (expected YYYY-MM-DD).', 'meeting-room-booking' )
			);
		}

		if ( ! self::isValidTime( $data['start_time'] ) ) {
			return new \WP_Error(
				'invalid_start_time',
				__( 'Start time format is invalid.', 'meeting-room-booking' )
			);
		}

		if ( ! self::isValidTime( $data['end_time'] ) ) {
			return new \WP_Error(
				'invalid_end_time',
				__( 'End time format is invalid.', 'meeting-room-booking' )
			);
		}

		// ── Range validation ───────────────────────────────────────────────
		$start_ts = strtotime( $data['meeting_date'] . ' ' . $data['start_time'] );
		$end_ts   = strtotime( $data['meeting_date'] . ' ' . $data['end_time']   );

		if ( ! $start_ts || ! $end_ts ) {
			return new \WP_Error(
				'invalid_datetime',
				__( 'Meeting date or time is invalid.', 'meeting-room-booking' )
			);
		}

		if ( $end_ts <= $start_ts ) {
			return new \WP_Error(
				'invalid_time_range',
				__( 'End time must be later than start time.', 'meeting-room-booking' )
			);
		}

		return null;
	}

	// ── Private helpers ───────────────────────────────────────────────────────

	private static function isValidDate( string $date ): bool {
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return false;
		}
		[ $year, $month, $day ] = array_map( 'intval', explode( '-', $date ) );
		return checkdate( $month, $day, $year );
	}

	private static function isValidTime( string $time ): bool {
		return (bool) preg_match( '/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $time );
	}

	/** Private constructor — this class is not meant to be instantiated. */
	private function __construct() {}
}
