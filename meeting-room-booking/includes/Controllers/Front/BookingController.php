<?php

namespace MRB\Controllers\Front;

use DateInterval;
use DateTimeImmutable;
use MRB\Contracts\ReservationRepositoryInterface;
use MRB\Repositories\ReservationRepository;
use MRB\Repositories\RoomRepository;
use MRB\Services\ReservationService;
use MRB\Support\View;

if (!defined("ABSPATH")) {
    exit();
}

class BookingController
{
    private ReservationService $service;

    public function __construct(ReservationService $service)
    {
        $this->service = $service;
    }

    /**
     * Shortcode callback. Returns HTML string.
     * Registered as: add_shortcode('mrb_booking_form', [$controller, 'renderShortcode'])
     */
    public function renderShortcode(): string
    {
        // Assets are registered in Assets.php — just enqueue here
        wp_enqueue_style("mrb-booking-form");
        wp_enqueue_script("mrb-booking-form"); // registered in Assets.php

        $statusMessage = null;
        $status = isset($_GET["mrb_status"])
            ? sanitize_key(wp_unslash($_GET["mrb_status"]))
            : "";

        if ($status === "success") {
            $token = isset($_GET["token"])
                ? sanitize_text_field(wp_unslash($_GET["token"]))
                : "";

            if ($token) {
                $manageLink = home_url("/reservation/" . $token);

                $statusMessage = [
                    "type" => "success",
                    "html" =>
                        '<div class="mrb-notice mrb-notice-success mrb-success-card">
                        <div class="mrb-success-icon">&#10003;</div>
                        <div class="mrb-success-content">
                            <strong>' .
                        esc_html__(
                            "Reservation submitted successfully.",
                            "meeting-room-booking",
                        ) .
                        '</strong>
                            <p class="mrb-manage-link-label">' .
                        esc_html__(
                            "Save this link to manage your booking later:",
                            "meeting-room-booking",
                        ) .
                        '</p>
                            <div class="mrb-manage-link-row">
                                <a class="mrb-manage-link" id="mrb-manage-link" href="' .
                        esc_url($manageLink) .
                        '">' .
                        esc_html($manageLink) .
                        '</a>
                                <button type="button" class="mrb-copy-link-btn" data-copy-target="#mrb-manage-link">' .
                        esc_html__("Copy link", "meeting-room-booking") .
                        '</button>
                            </div>
                            <p class="mrb-copy-feedback" id="mrb-copy-feedback" style="display:none;">' .
                        esc_html__("Link copied.", "meeting-room-booking") .
                        '</p>
                        </div>
                    </div>',
                ];
            }
        }

        if ($status === "error") {
            $error = isset($_GET["mrb_error"])
                ? sanitize_text_field(wp_unslash($_GET["mrb_error"]))
                : esc_html__("Something went wrong.", "meeting-room-booking");

            $statusMessage = [
                "type" => "error",
                "html" =>
                    '<div class="mrb-notice mrb-notice-error mrb-error-card">
                    <strong>' .
                    esc_html__(
                        "Could not submit reservation.",
                        "meeting-room-booking",
                    ) .
                    '</strong>
                    <p>' .
                    esc_html($error) .
                    '</p>
                </div>',
            ];
        }

        return View::render("front/booking-form", [
            "statusMessage" => $statusMessage,
            "actionUrl" => admin_url("admin-post.php"),
        ]);
    }

    /**
     * Form submission handler.
     * Hook: admin_post_mrb_submit_booking / admin_post_nopriv_mrb_submit_booking
     */
    public function handleSubmit(): void
    {
        if (
            empty($_POST["mrb_nonce"]) ||
            !wp_verify_nonce(
                sanitize_text_field(wp_unslash($_POST["mrb_nonce"])),
                "mrb_submit_booking",
            )
        ) {
            wp_die(esc_html__("Invalid nonce.", "meeting-room-booking"));
        }

        $result = $this->service->create(wp_unslash($_POST));
        $redirectUrl = wp_get_referer() ?: home_url();

        if (!$result["success"]) {
            $error =
                $result["errors"][0] ??
                __("Invalid data.", "meeting-room-booking");

            wp_safe_redirect(
                add_query_arg(
                    [
                        "mrb_status" => "error",
                        "mrb_error" => rawurlencode($error),
                    ],
                    $redirectUrl,
                ),
            );
            exit();
        }

        // Email notifications are handled inside ReservationService::create().
        wp_safe_redirect(
            add_query_arg(
                [
                    "mrb_status" => "success",
                    "token" => $result["token"],
                ],
                $redirectUrl,
            ),
        );
        exit();
    }

    /**
     * AJAX: return booked time slots for a date range.
     * Hook: wp_ajax_mrb_get_booked_times_range / wp_ajax_nopriv_mrb_get_booked_times_range
     */
    public function handleGetBookedTimesRange(): void
    {
        // Copy the full logic from BookingFormShortcode::getBookedTimesRange() exactly,
        // including the nonce check, date parsing, repository queries, grouping, and wp_send_json_success().
        // Use `new \MRB\Repositories\ReservationRepository()` and `new \MRB\Repositories\RoomRepository()`
        // since this is a static-like AJAX handler.
        // The action name stays the same: 'mrb_get_booked_times_range'
        try {
            $nonceIsValid = check_ajax_referer(
                "mrb_get_booked_times_range",
                "nonce",
                false,
            );

            if (!$nonceIsValid) {
                wp_send_json_error([
                    "message" => esc_html__(
                        "Security check failed. Please refresh the page and try again.",
                        "meeting-room-booking",
                    ),
                ]);
                return;
            }

            $selectedDate = isset($_POST["date"])
                ? sanitize_text_field(wp_unslash($_POST["date"]))
                : "";

            if (
                !$selectedDate ||
                !preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)
            ) {
                wp_send_json_error([
                    "message" => esc_html__(
                        "Invalid date.",
                        "meeting-room-booking",
                    ),
                    "date" => $selectedDate,
                ]);
                return;
            }

            $selected = new DateTimeImmutable($selectedDate);
            $start = $selected;
            $end = $selected->add(new DateInterval("P6D"));

            $startDate = $start->format("Y-m-d");
            $endDate = $end->format("Y-m-d");

            $repository = new \MRB\Repositories\ReservationRepository();
            $roomRepo = new \MRB\Repositories\RoomRepository();

            $rows = $repository->getBookedTimesBetweenDates(
                $startDate,
                $endDate,
            );
            $rooms = $roomRepo->all();
            $totalRooms = max(1, is_array($rooms) ? count($rooms) : 1);

            $grouped = [];

            for ($i = 0; $i <= 6; $i++) {
                $day = $selected->modify("+" . $i . " days");
                $date = $day->format("Y-m-d");

                $grouped[$date] = [
                    "date" => $date,
                    "day_label" => $day->format("D"),
                    "month_label" => $day->format("M j"),
                    "full_label" => $day->format("l, F j, Y"),
                    "is_selected" => $date === $selectedDate,
                    "slots" => [],
                ];
            }

            foreach ($rows as $row) {
                $date = isset($row["meeting_date"])
                    ? (string) $row["meeting_date"]
                    : "";
                $startTime = isset($row["start_time"])
                    ? (string) $row["start_time"]
                    : "";
                $endTime = isset($row["end_time"])
                    ? (string) $row["end_time"]
                    : "";
                $status = isset($row["status"]) ? (string) $row["status"] : "";

                if (
                    !isset($grouped[$date]) ||
                    $startTime === "" ||
                    $endTime === ""
                ) {
                    continue;
                }

                $grouped[$date]["slots"][] = [
                    "start_time" => substr($startTime, 0, 5),
                    "end_time" => substr($endTime, 0, 5),
                    "status" => $status,
                ];
            }

            wp_send_json_success([
                "selected_date" => $selectedDate,
                "start_date" => $startDate,
                "end_date" => $endDate,
                "days" => array_values($grouped),
                "total_rooms" => $totalRooms,
            ]);
        } catch (\Throwable $e) {
            wp_send_json_error([
                "message" => "PHP error: " . $e->getMessage(),
            ]);
        }
    }
}
