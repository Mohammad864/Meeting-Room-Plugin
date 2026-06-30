<?php

namespace MRB\Services;

use MRB\Contracts\NotificationServiceInterface;
use MRB\Contracts\ReservationRepositoryInterface;
use MRB\Enums\ReservationStatus;
use MRB\Support\Validator;

if (!defined("ABSPATH")) {
    exit();
}

class ReservationService
{
    private ReservationRepositoryInterface $repository;
    private NotificationServiceInterface $notifications;
    private RoomAllocator $roomAllocator;

    public function __construct(
        ReservationRepositoryInterface $repository,
        NotificationServiceInterface $notifications,
        RoomAllocator $roomAllocator,
    ) {
        $this->repository = $repository;
        $this->notifications = $notifications;
        $this->roomAllocator = $roomAllocator;
    }

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Create a new reservation.
     *
     * @return array{success: bool, id?: int, token?: string, errors?: string[]}
     */
    public function create(array $data): array
    {
        $clean = $this->sanitizeReservationData($data);

        $validationError = Validator::validateReservation($clean);

        if ($validationError !== null) {
            return [
                "success" => false,
                "errors" => [$validationError->get_error_message()],
            ];
        }

        if (
            !$this->validateMaxDuration(
                $clean["start_time"],
                $clean["end_time"],
            )
        ) {
            return [
                "success" => false,
                "errors" => [
                    __(
                        "A reservation cannot exceed 8 hours.",
                        "meeting-room-booking",
                    ),
                ],
            ];
        }

        if ($clean["meeting_date"] < date("Y-m-d")) {
            return [
                "success" => false,
                "errors" => [
                    __(
                        "Reservations cannot be created in the past.",
                        "meeting-room-booking",
                    ),
                ],
            ];
        }

        $token = $this->generateToken();

        $clean["edit_token"] = $token;
        $clean["status"] = ReservationStatus::PENDING;
        $clean["created_at"] = current_time("mysql");
        $clean["updated_at"] = current_time("mysql");

        $insertedId = $this->repository->create($clean);

        if (!$insertedId) {
            return [
                "success" => false,
                "errors" => [
                    __(
                        "Database error: Could not save reservation.",
                        "meeting-room-booking",
                    ),
                ],
            ];
        }

        $reservation = $this->repository->findById($insertedId);

        if ($reservation) {
            $this->sendNotificationSafely(
                fn() => $this->notifications->sendReservationCreatedEmails(
                    $reservation,
                ),
            );
        }

        return [
            "success" => true,
            "id" => $insertedId,
            "token" => $token,
        ];
    }

    /**
     * Update a reservation using its edit token (guest action).
     *
     * @return true|\WP_Error
     */
    public function updateReservation(string $token, array $data)
    {
        $token = sanitize_text_field($token);

        if ($token === "") {
            return new \WP_Error("missing_token", "Missing reservation token.");
        }

        $reservation = $this->repository->findByToken($token);

        if (!$reservation || !is_array($reservation)) {
            return new \WP_Error(
                "reservation_not_found",
                "Reservation not found.",
                ["token" => $token],
            );
        }

        if (ReservationStatus::isLocked($reservation["status"] ?? "")) {
            return new \WP_Error(
                "reservation_locked",
                "This reservation can no longer be updated because it is cancelled or rejected.",
                ["status" => $reservation["status"], "token" => $token],
            );
        }

        $startTimestamp = strtotime(
            $reservation["meeting_date"] . " " . $reservation["start_time"],
        );

        if (!$startTimestamp) {
            return new \WP_Error(
                "invalid_existing_datetime",
                "The existing reservation date or time is invalid.",
            );
        }

        if ($startTimestamp < time()) {
            return new \WP_Error(
                "past_reservation",
                "Past reservations cannot be updated.",
            );
        }

        $clean = $this->sanitizeReservationData($data);

        $validationError = Validator::validateReservation($clean);

        if ($validationError !== null) {
            return $validationError;
        }

        if (
            !$this->validateMaxDuration(
                $clean["start_time"],
                $clean["end_time"],
            )
        ) {
            return new \WP_Error(
                "max_duration_exceeded",
                "A reservation cannot exceed 8 hours.",
            );
        }

        if ($clean["meeting_date"] < date("Y-m-d")) {
            return new \WP_Error(
                "past_meeting_date",
                "Reservations cannot be updated to a past date.",
            );
        }

        $clean["updated_at"] = current_time("mysql");

        // If an approved reservation is changed by the guest, it reverts to pending.
        if (($reservation["status"] ?? "") === ReservationStatus::APPROVED) {
            $clean["status"] = ReservationStatus::PENDING;
            $clean["room_id"] = null;
        }

        $result = $this->repository->update((int) $reservation["id"], $clean);

        if ($result === false) {
            return new \WP_Error(
                "database_update_failed",
                "Database update failed.",
            );
        }

        $updated = $this->repository->findByToken($token);

        if ($updated) {
            $this->sendNotificationSafely(
                fn() => $this->notifications->sendReservationUpdatedEmails(
                    $updated,
                ),
            );
        }

        return true;
    }

    /**
     * Cancel a reservation using its edit token (guest action).
     *
     * @return true|\WP_Error
     */
    public function cancelByToken(string $token)
    {
        $token = sanitize_text_field($token);

        if ($token === "") {
            return new \WP_Error("missing_token", "Missing reservation token.");
        }

        $reservation = $this->repository->findByToken($token);

        if (!$reservation || !is_array($reservation)) {
            return new \WP_Error(
                "reservation_not_found",
                "Reservation not found.",
                ["token" => $token],
            );
        }

        if (($reservation["status"] ?? "") === ReservationStatus::CANCELLED) {
            return new \WP_Error(
                "already_cancelled",
                "This reservation is already cancelled.",
            );
        }

        $startTimestamp = strtotime(
            $reservation["meeting_date"] . " " . $reservation["start_time"],
        );

        if (!$startTimestamp) {
            return new \WP_Error(
                "invalid_existing_datetime",
                "The existing reservation date or time is invalid.",
            );
        }

        if ($startTimestamp < time()) {
            return new \WP_Error(
                "past_reservation",
                "Past reservations cannot be cancelled.",
            );
        }

        $result = $this->repository->update((int) $reservation["id"], [
            "status" => ReservationStatus::CANCELLED,
            "cancelled_at" => current_time("mysql"),
            "updated_at" => current_time("mysql"),
            "room_id" => null,
        ]);

        if ($result === false) {
            return new \WP_Error(
                "database_cancel_failed",
                "Database error while cancelling reservation.",
            );
        }

        $cancelled = $reservation;
        $cancelled["status"] = ReservationStatus::CANCELLED;

        $this->sendNotificationSafely(
            fn() => $this->notifications->sendReservationCancelledEmails(
                $cancelled,
            ),
        );

        return true;
    }

    /**
     * Admin: full edit of a reservation (all fields + status + room_id).
     *
     * @return true|false|\WP_Error
     */
    public function adminEdit(int $id, array $data)
    {
        $status = isset($data["status"])
            ? sanitize_key($data["status"])
            : ReservationStatus::PENDING;

        if (!ReservationStatus::isValid($status)) {
            $status = ReservationStatus::PENDING;
        }

        $roomId = isset($data["room_id"]) ? absint($data["room_id"]) : null;

        $updateData = [
            "first_name" => sanitize_text_field($data["first_name"] ?? ""),
            "last_name" => sanitize_text_field($data["last_name"] ?? ""),
            "mobile" => sanitize_text_field($data["mobile"] ?? ""),
            "email" => sanitize_email($data["email"] ?? ""),
            "meeting_title" => sanitize_text_field(
                $data["meeting_title"] ?? "",
            ),
            "meeting_date" => sanitize_text_field($data["meeting_date"] ?? ""),
            "start_time" => $this->normalizeTime($data["start_time"] ?? ""),
            "end_time" => $this->normalizeTime($data["end_time"] ?? ""),
            "description" => sanitize_textarea_field(
                $data["description"] ?? "",
            ),
            "status" => $status,
            "room_id" => $roomId > 0 ? $roomId : null,
            "updated_at" => current_time("mysql"),
        ];

        $validationError = Validator::validateReservation($updateData);

        if ($validationError !== null) {
            return $validationError;
        }

        if (
            !$this->validateMaxDuration(
                $updateData["start_time"],
                $updateData["end_time"],
            )
        ) {
            return new \WP_Error(
                "max_duration_exceeded",
                "A reservation cannot exceed 8 hours.",
            );
        }

        $result = $this->repository->update($id, $updateData);

        if (is_wp_error($result)) {
            return $result;
        }

        if ($result === false) {
            return false;
        }

        $updated = $this->repository->findById($id);

        if ($updated) {
            $this->sendNotificationSafely(
                fn() => $this->notifications->sendReservationUpdatedEmails(
                    $updated,
                ),
            );
        }

        return true;
    }

    /**
     * Admin: change reservation status (approve / reject / revert to pending).
     *
     * Automatically allocates a room when approving.
     *
     * @return array{success: bool, message: string}|\WP_Error
     */
    public function changeStatus(int $id, string $status)
    {
        if (!ReservationStatus::isAdminSettable($status)) {
            return [
                "success" => false,
                "message" => __(
                    "Invalid reservation status.",
                    "meeting-room-booking",
                ),
            ];
        }

        $reservation = $this->repository->findById($id);

        if (!$reservation) {
            return [
                "success" => false,
                "message" => __(
                    "Reservation not found.",
                    "meeting-room-booking",
                ),
            ];
        }

        $updateData = [
            "status" => $status,
            "updated_at" => current_time("mysql"),
        ];

        if ($status === ReservationStatus::APPROVED) {
            $roomId = $this->roomAllocator->allocate(
                $reservation["meeting_date"],
                $reservation["start_time"],
                $reservation["end_time"],
            );

            if (!$roomId) {
                return [
                    "success" => false,
                    "message" => __(
                        "No meeting rooms are available for this time slot.",
                        "meeting-room-booking",
                    ),
                ];
            }

            $updateData["room_id"] = $roomId;
        }

        $result = $this->repository->update($id, $updateData);

        if ($result === false) {
            return [
                "success" => false,
                "message" => __(
                    "Database error while updating reservation.",
                    "meeting-room-booking",
                ),
            ];
        }

        $updated = $this->repository->findById($id);

        if ($updated) {
            $this->sendNotificationSafely(
                fn() => $this->notifications->sendReservationStatusChangedEmails(
                    $updated,
                ),
            );
        }

        return [
            "success" => true,
            "message" => __(
                "Reservation status updated successfully.",
                "meeting-room-booking",
            ),
        ];
    }

    /**
     * Find a reservation by its edit token.
     */
    public function findByToken(string $token): ?array
    {
        $reservation = $this->repository->findByToken($token);

        return is_array($reservation) ? $reservation : null;
    }

    /**
     * Calculate the minimum number of rooms needed for a given date.
     *
     * Uses the sweep-line algorithm in MinimumRoomsCalculator.
     */
    public function calculateMinimumRooms(string $date): int
    {
        $rows = $this->repository->findByDate($date);

        if (empty($rows)) {
            return 0;
        }

        $events = [];

        foreach ($rows as $r) {
            $events[] = [
                "time" => $this->normalizeTime($r["start_time"] ?? ""),
                "type" => "start",
            ];
            $events[] = [
                "time" => $this->normalizeTime($r["end_time"] ?? ""),
                "type" => "end",
            ];
        }

        usort($events, static function (array $a, array $b): int {
            if ($a["time"] === $b["time"]) {
                return $a["type"] === "end" ? -1 : 1;
            }

            return strcmp($a["time"], $b["time"]);
        });

        $current = 0;
        $max = 0;

        foreach ($events as $event) {
            if ($event["type"] === "start") {
                $current++;
                $max = max($max, $current);
            } else {
                $current = max(0, $current - 1);
            }
        }

        return $max;
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function sanitizeReservationData(array $data): array
    {
        return [
            "first_name" => sanitize_text_field($data["first_name"] ?? ""),
            "last_name" => sanitize_text_field($data["last_name"] ?? ""),
            "mobile" => sanitize_text_field($data["mobile"] ?? ""),
            "email" => sanitize_email($data["email"] ?? ""),
            "meeting_title" => sanitize_text_field(
                $data["meeting_title"] ?? "",
            ),
            "meeting_date" => sanitize_text_field($data["meeting_date"] ?? ""),
            "start_time" => $this->normalizeTime($data["start_time"] ?? ""),
            "end_time" => $this->normalizeTime($data["end_time"] ?? ""),
            "description" => sanitize_textarea_field(
                $data["description"] ?? "",
            ),
        ];
    }

    private function normalizeTime(string $time): string
    {
        $time = trim(sanitize_text_field($time));

        if (preg_match('/^\d{2}:\d{2}$/', $time)) {
            return $time . ":00";
        }

        return $time;
    }

    private function validateMaxDuration(string $start, string $end): bool
    {
        $startTS = strtotime($this->normalizeTime($start));
        $endTS = strtotime($this->normalizeTime($end));

        if (!$startTS || !$endTS) {
            return false;
        }

        $hours = ($endTS - $startTS) / 3600;

        return $hours > 0 && $hours <= 8;
    }

    private function generateToken(): string
    {
        try {
            return bin2hex(random_bytes(16));
        } catch (\Throwable $e) {
            return wp_generate_password(32, false, false);
        }
    }

    /**
     * Execute a notification callback, swallowing any exceptions.
     *
     * Mail failures must never bubble up and break the main operation.
     */
    private function sendNotificationSafely(callable $fn): void
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            error_log("[MRB] Notification failed: " . $e->getMessage());
        }
    }
}
