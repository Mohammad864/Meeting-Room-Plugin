<?php

namespace MRB\Support;

if (!defined("ABSPATH")) {
    exit();
}

/**
 * Validates reservation form data.
 *
 * Single source of validation truth for the plugin.
 * Returns null on success, \WP_Error on the first failure found.
 */
final class Validator
{
    /**
     * Validate all required reservation fields.
     *
     * @return \WP_Error|null  null = valid, WP_Error = first failure.
     */
    public static function validateReservation(array $data): ?\WP_Error
    {
        if (empty($data["first_name"])) {
            return new \WP_Error(
                "missing_first_name",
                __("First name is missing.", "meeting-room-booking"),
            );
        }

        if (empty($data["last_name"])) {
            return new \WP_Error(
                "missing_last_name",
                __("Last name is missing.", "meeting-room-booking"),
            );
        }

        if (empty($data["mobile"])) {
            return new \WP_Error(
                "missing_mobile",
                __("Mobile number is missing.", "meeting-room-booking"),
            );
        }

        if (empty($data["email"])) {
            return new \WP_Error(
                "missing_email",
                __("Email address is missing.", "meeting-room-booking"),
            );
        }

        if (!is_email($data["email"])) {
            return new \WP_Error(
                "invalid_email",
                __("Email address is invalid.", "meeting-room-booking"),
                ["email" => $data["email"]],
            );
        }

        if (empty($data["meeting_title"])) {
            return new \WP_Error(
                "missing_meeting_title",
                __("Meeting title is missing.", "meeting-room-booking"),
            );
        }

        if (empty($data["meeting_date"])) {
            return new \WP_Error(
                "missing_meeting_date",
                __("Meeting date is missing.", "meeting-room-booking"),
            );
        }

        if (!self::isValidDate($data["meeting_date"])) {
            return new \WP_Error(
                "invalid_meeting_date",
                __("Meeting date format is invalid.", "meeting-room-booking"),
                ["meeting_date" => $data["meeting_date"]],
            );
        }

        if (empty($data["start_time"])) {
            return new \WP_Error(
                "missing_start_time",
                __("Start time is missing.", "meeting-room-booking"),
            );
        }

        if (empty($data["end_time"])) {
            return new \WP_Error(
                "missing_end_time",
                __("End time is missing.", "meeting-room-booking"),
            );
        }

        if (!self::isValidTime($data["start_time"])) {
            return new \WP_Error(
                "invalid_start_time",
                __("Start time format is invalid.", "meeting-room-booking"),
                ["start_time" => $data["start_time"]],
            );
        }

        if (!self::isValidTime($data["end_time"])) {
            return new \WP_Error(
                "invalid_end_time",
                __("End time format is invalid.", "meeting-room-booking"),
                ["end_time" => $data["end_time"]],
            );
        }

        $startTS = strtotime($data["meeting_date"] . " " . $data["start_time"]);
        $endTS = strtotime($data["meeting_date"] . " " . $data["end_time"]);

        if (!$startTS || !$endTS) {
            return new \WP_Error(
                "invalid_datetime",
                __(
                    "Meeting date or time format is invalid.",
                    "meeting-room-booking",
                ),
            );
        }

        if ($endTS <= $startTS) {
            return new \WP_Error(
                "invalid_time_range",
                __(
                    "Start time must be earlier than end time.",
                    "meeting-room-booking",
                ),
                [
                    "start_time" => $data["start_time"],
                    "end_time" => $data["end_time"],
                ],
            );
        }

        return null;
    }

    public static function isValidDate(string $date): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return false;
        }

        [$year, $month, $day] = array_map("intval", explode("-", $date));

        return checkdate($month, $day, $year);
    }

    public static function isValidTime(string $time): bool
    {
        return (bool) preg_match(
            '/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/',
            $time,
        );
    }
}
