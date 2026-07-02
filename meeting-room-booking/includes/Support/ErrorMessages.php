<?php
/**
 * Centralised front-end error message map.
 *
 * @package MeetingRoomBooking
 */

namespace MRB\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps error codes (used in redirect query strings) to human-readable,
 * translatable messages shown to guest users.
 *
 * All messages are run through __() so they appear in the plugin's .pot file.
 */
class ErrorMessages {

	/**
	 * Return the translated message for a given error code.
	 *
	 * @param  string $code Sanitized error code key.
	 * @return string       Translated message, or a generic fallback.
	 */
	public static function get( string $code ): string {
		$messages = [
			// Security / request errors
			'security'               => __( 'Security check failed. Please refresh the page and try again.',      'meeting-room-booking' ),
			'invalid_request'        => __( 'Invalid reservation request.',                                        'meeting-room-booking' ),
			'invalid_request_method' => __( 'Invalid request method.',                                             'meeting-room-booking' ),
			'missing_token'          => __( 'Missing reservation token.',                                          'meeting-room-booking' ),

			// Not found / locked
			'reservation_not_found'  => __( 'Reservation not found.',                                             'meeting-room-booking' ),
			'reservation_locked'     => __( 'This reservation can no longer be edited.',                           'meeting-room-booking' ),
			'already_cancelled'      => __( 'This reservation has already been cancelled.',                        'meeting-room-booking' ),
			'past_reservation'       => __( 'Past reservations cannot be modified.',                               'meeting-room-booking' ),

			// Conflict / availability
			'conflict'               => __( 'The selected time slot is no longer available.',                      'meeting-room-booking' ),
			'time_conflict'          => __( 'The selected time slot is no longer available.',                      'meeting-room-booking' ),
			'no_rooms_available'     => __( 'No meeting rooms are available for the selected time slot.',          'meeting-room-booking' ),

			// Validation errors
			'invalid_time_range'     => __( 'Start time must be earlier than end time.',                           'meeting-room-booking' ),
			'invalid_datetime'       => __( 'Meeting date or time format is invalid.',                             'meeting-room-booking' ),
			'invalid_meeting_date'   => __( 'Meeting date format is invalid.',                                     'meeting-room-booking' ),
			'invalid_start_time'     => __( 'Start time format is invalid.',                                       'meeting-room-booking' ),
			'invalid_end_time'       => __( 'End time format is invalid.',                                         'meeting-room-booking' ),
			'invalid_email'          => __( 'Email address is not valid.',                                         'meeting-room-booking' ),
			'max_duration_exceeded'  => __( 'The reservation duration exceeds the maximum allowed time (8 hours).','meeting-room-booking' ),
			'past_meeting_date'      => __( 'Meeting date cannot be in the past.',                                 'meeting-room-booking' ),

			// Missing required fields
			'missing_first_name'     => __( 'First name is required.',    'meeting-room-booking' ),
			'missing_last_name'      => __( 'Last name is required.',     'meeting-room-booking' ),
			'missing_email'          => __( 'Email address is required.', 'meeting-room-booking' ),
			'missing_mobile'         => __( 'Mobile number is required.', 'meeting-room-booking' ),
			'missing_meeting_title'  => __( 'Meeting title is required.', 'meeting-room-booking' ),
			'missing_meeting_date'   => __( 'Meeting date is required.',  'meeting-room-booking' ),
			'missing_start_time'     => __( 'Start time is required.',    'meeting-room-booking' ),
			'missing_end_time'       => __( 'End time is required.',      'meeting-room-booking' ),

			// Database / persistence errors
			'update_failed'          => __( 'Failed to update the reservation. Please try again.',   'meeting-room-booking' ),
			'cancel_failed'          => __( 'Failed to cancel the reservation. Please try again.',   'meeting-room-booking' ),
			'database_error'         => __( 'A database error occurred. Please try again.',          'meeting-room-booking' ),
			'database_update_failed' => __( 'A database error occurred while saving. Please try again.', 'meeting-room-booking' ),
			'database_cancel_failed' => __( 'A database error occurred while cancelling. Please try again.', 'meeting-room-booking' ),
		];

		return $messages[ sanitize_key( $code ) ]
			?? __( 'Something went wrong. Please try again.', 'meeting-room-booking' );
	}

	/** Private constructor — this class is not meant to be instantiated. */
	private function __construct() {}
}
