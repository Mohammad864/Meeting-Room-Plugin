<?php

namespace MRB\Support;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Centralised frontend error-message map.
 *
 * Replaces the two separate (and divergent) copies that lived in
 * Plugin::getFrontendErrorMessage() and ManageReservationHandler::getFrontendErrorMessage().
 */
final class ErrorMessages
{
    public static function get(string $code): string
    {
        $map = [
            'security'               => __('Security validation failed. Please refresh the page and try again.', 'meeting-room-booking'),
            'invalid_request'        => __('Invalid request. Please review your input and try again.', 'meeting-room-booking'),
            'invalid_request_method' => __('Invalid request method. Please try again.', 'meeting-room-booking'),
            'missing_token'          => __('The reservation token is missing. Please use the link from your confirmation email.', 'meeting-room-booking'),

            'update_failed'          => __('We could not update your reservation. Please try again.', 'meeting-room-booking'),
            'cancel_failed'          => __('We could not cancel your reservation. Please try again.', 'meeting-room-booking'),

            'not_found'              => __('Reservation not found or the management link is invalid.', 'meeting-room-booking'),
            'reservation_not_found'  => __('Reservation not found or the management link is invalid.', 'meeting-room-booking'),

            'conflict'               => __('The selected time conflicts with another reservation. Please choose a different time.', 'meeting-room-booking'),
            'time_conflict'          => __('The selected time conflicts with another reservation. Please choose a different time.', 'meeting-room-booking'),

            'max_duration_exceeded'  => __('The reservation duration exceeds the maximum allowed time.', 'meeting-room-booking'),
            'min_duration_not_met'   => __('The reservation duration is shorter than the minimum allowed time.', 'meeting-room-booking'),
            'invalid_duration'       => __('The selected reservation duration is invalid.', 'meeting-room-booking'),
            'invalid_time_range'     => __('The selected start and end time are invalid.', 'meeting-room-booking'),
            'invalid_datetime'       => __('The selected reservation date or time is invalid.', 'meeting-room-booking'),
            'past_meeting_date'      => __('The meeting date cannot be in the past.', 'meeting-room-booking'),
            'past_meeting_time'      => __('The meeting time cannot be in the past.', 'meeting-room-booking'),

            'missing_first_name'     => __('Please enter your first name.', 'meeting-room-booking'),
            'missing_last_name'      => __('Please enter your last name.', 'meeting-room-booking'),
            'missing_email'          => __('Please enter your email address.', 'meeting-room-booking'),
            'invalid_email'          => __('Please enter a valid email address.', 'meeting-room-booking'),
            'missing_mobile'         => __('Please enter your mobile number.', 'meeting-room-booking'),
            'missing_meeting_title'  => __('Please enter a meeting title.', 'meeting-room-booking'),
            'missing_meeting_date'   => __('Please select a meeting date.', 'meeting-room-booking'),
            'missing_start_time'     => __('Please select a start time.', 'meeting-room-booking'),
            'missing_end_time'       => __('Please select an end time.', 'meeting-room-booking'),

            'database_error'         => __('A database error occurred. Please try again.', 'meeting-room-booking'),
            'database_update_failed' => __('A database error occurred. Please try again.', 'meeting-room-booking'),
            'wpdb_update_failed'     => __('A database error occurred. Please try again.', 'meeting-room-booking'),
        ];

        return $map[$code] ?? __('Something went wrong. Please try again.', 'meeting-room-booking');
    }
}
