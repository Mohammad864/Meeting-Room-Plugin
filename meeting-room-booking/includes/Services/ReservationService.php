<?php

namespace MRB\Services;

use MRB\Database\ReservationRepository;

if (!defined('ABSPATH')) {
    exit;
}

class ReservationService
{
    private ReservationRepository $repository;

    public function __construct(ReservationRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Create reservation
     */
    public function create(array $data): array
    {
        $clean = $this->sanitizeReservationData($data);

        if (!$this->validateReservationData($clean)) {
            return [
                'success' => false,
                'errors'  => ['Invalid data. Please check all required fields.']
            ];
        }

        if (!$this->validateMaxDuration($clean['start_time'], $clean['end_time'])) {
            return [
                'success' => false,
                'errors'  => ['A reservation cannot exceed 8 hours.']
            ];
        }

        if ($clean['meeting_date'] < date('Y-m-d')) {
            return [
                'success' => false,
                'errors'  => ['Reservations cannot be created in the past.']
            ];
        }

        $token = $this->generateToken();

        $clean['edit_token']  = $token;
        $clean['status']      = 'pending';
        $clean['created_at']  = current_time('mysql');
        $clean['updated_at']  = current_time('mysql');

        $insertedId = $this->repository->create($clean);

        if (is_wp_error($insertedId)) {
            return [
                'success' => false,
                'errors'  => [$insertedId->get_error_message()]
            ];
        }

        if (!$insertedId) {
            return [
                'success' => false,
                'errors'  => ['Database error: Could not save reservation.']
            ];
        }

        $this->sendEmail(
            $clean['email'],
            'Reservation Received',
            "Your reservation has been received and is pending approval.\n\n"
            . "Meeting: {$clean['meeting_title']}\n"
            . "Date: {$clean['meeting_date']}\n"
            . "Time: {$clean['start_time']} - {$clean['end_time']}"
        );

        return [
            'success' => true,
            'id'      => $insertedId,
            'token'   => $token
        ];
    }

    /**
     * Update reservation using token.
     *
     * Return:
     * - true on success
     * - WP_Error on known failure
     *
     * No strict return type is used because this method can return WP_Error.
     */
    public function updateReservation(string $token, array $data)
    {
        $token = sanitize_text_field($token);

        if ($token === '') {
            return new \WP_Error(
                'missing_token',
                'Missing reservation token.'
            );
        }

        $reservation = $this->repository->findByToken($token);

        if (is_wp_error($reservation)) {
            return $reservation;
        }

        if (!$reservation || !is_array($reservation)) {
            return new \WP_Error(
                'reservation_not_found',
                'Reservation not found.',
                [
                    'token' => $token,
                ]
            );
        }

        if (in_array($reservation['status'], ['cancelled', 'rejected'], true)) {
            return new \WP_Error(
                'reservation_locked',
                'This reservation can no longer be updated because it is cancelled or rejected.',
                [
                    'status' => $reservation['status'],
                    'token'  => $token,
                ]
            );
        }

        $startTimestamp = strtotime(
            $reservation['meeting_date'] . ' ' . $reservation['start_time']
        );

        if (!$startTimestamp) {
            return new \WP_Error(
                'invalid_existing_datetime',
                'The existing reservation date or time is invalid.',
                [
                    'reservation' => $reservation,
                ]
            );
        }

        if ($startTimestamp < time()) {
            return new \WP_Error(
                'past_reservation',
                'Past reservations cannot be updated.',
                [
                    'meeting_date' => $reservation['meeting_date'],
                    'start_time'   => $reservation['start_time'],
                    'token'        => $token,
                ]
            );
        }

        $clean = $this->sanitizeReservationData($data);

        $validationError = $this->getReservationValidationError($clean);

        if (is_wp_error($validationError)) {
            return $validationError;
        }

        if (!$this->validateMaxDuration($clean['start_time'], $clean['end_time'])) {
            return new \WP_Error(
                'max_duration_exceeded',
                'A reservation cannot exceed 8 hours.',
                [
                    'start_time' => $clean['start_time'],
                    'end_time'   => $clean['end_time'],
                ]
            );
        }

        if ($clean['meeting_date'] < date('Y-m-d')) {
            return new \WP_Error(
                'past_meeting_date',
                'Reservations cannot be updated to a past date.',
                [
                    'meeting_date' => $clean['meeting_date'],
                ]
            );
        }

        $clean['updated_at'] = current_time('mysql');

        /*
         * If an approved reservation is changed by the guest,
         * it should go back to pending and lose assigned room.
         */
        if (($reservation['status'] ?? '') === 'approved') {
            $clean['status']  = 'pending';
            $clean['room_id'] = null;
        }

        $result = $this->repository->update((int) $reservation['id'], $clean);

        if (is_wp_error($result)) {
            return $result;
        }

        /*
         * Important:
         * If repository is fixed correctly, 0 affected rows should already return true.
         * This fallback is only for old repository behavior.
         */
        if ($result === false) {
            return new \WP_Error(
                'database_update_failed',
                'Database update failed. Repository returned false.',
                [
                    'reservation_id' => (int) $reservation['id'],
                    'token'          => $token,
                    'data'           => $clean,
                    'help'           => 'Check ReservationRepository::update(). $wpdb->update() result 0 must be treated as success, not failure.',
                ]
            );
        }

        return true;
    }

    /**
     * Cancel reservation.
     *
     * Return:
     * - true on success
     * - WP_Error on known failure
     */
    public function cancelByToken(string $token)
    {
        $token = sanitize_text_field($token);

        if ($token === '') {
            return new \WP_Error(
                'missing_token',
                'Missing reservation token.'
            );
        }

        $reservation = $this->repository->findByToken($token);

        if (is_wp_error($reservation)) {
            return $reservation;
        }

        if (!$reservation || !is_array($reservation)) {
            return new \WP_Error(
                'reservation_not_found',
                'Reservation not found.',
                [
                    'token' => $token,
                ]
            );
        }

        if (($reservation['status'] ?? '') === 'cancelled') {
            return new \WP_Error(
                'already_cancelled',
                'This reservation is already cancelled.',
                [
                    'token'  => $token,
                    'status' => $reservation['status'],
                ]
            );
        }

        $startTimestamp = strtotime(
            $reservation['meeting_date'] . ' ' . $reservation['start_time']
        );

        if (!$startTimestamp) {
            return new \WP_Error(
                'invalid_existing_datetime',
                'The existing reservation date or time is invalid.',
                [
                    'reservation' => $reservation,
                ]
            );
        }

        if ($startTimestamp < time()) {
            return new \WP_Error(
                'past_reservation',
                'Past reservations cannot be cancelled.',
                [
                    'meeting_date' => $reservation['meeting_date'],
                    'start_time'   => $reservation['start_time'],
                    'token'        => $token,
                ]
            );
        }

        $result = $this->repository->update((int) $reservation['id'], [
            'status'       => 'cancelled',
            'cancelled_at' => current_time('mysql'),
            'updated_at'   => current_time('mysql'),
            'room_id'      => null,
        ]);

        if (is_wp_error($result)) {
            return $result;
        }

        if ($result === false) {
            return new \WP_Error(
                'database_cancel_failed',
                'Database error while cancelling reservation.',
                [
                    'reservation_id' => (int) $reservation['id'],
                    'token'          => $token,
                    'help'           => 'Check ReservationRepository::update(). $wpdb->update() result 0 must be treated as success, not failure.',
                ]
            );
        }

        $this->sendEmail(
            $reservation['email'],
            'Reservation Cancelled',
            "Your reservation '{$reservation['meeting_title']}' has been cancelled."
        );

        return true;
    }

    /**
     * Admin edit reservation.
     *
     * Return:
     * - true on success
     * - false or WP_Error on failure
     */
    public function adminEdit(int $id, array $data)
    {
        $allowedStatuses = ['pending', 'approved', 'rejected', 'cancelled'];

        $status = isset($data['status']) ? sanitize_key($data['status']) : 'pending';

        if (!in_array($status, $allowedStatuses, true)) {
            $status = 'pending';
        }

        $roomId = isset($data['room_id']) ? absint($data['room_id']) : null;

        $updateData = [
            'first_name'    => sanitize_text_field($data['first_name'] ?? ''),
            'last_name'     => sanitize_text_field($data['last_name'] ?? ''),
            'mobile'        => sanitize_text_field($data['mobile'] ?? ''),
            'email'         => sanitize_email($data['email'] ?? ''),
            'meeting_title' => sanitize_text_field($data['meeting_title'] ?? ''),
            'meeting_date'  => sanitize_text_field($data['meeting_date'] ?? ''),
            'start_time'    => $this->normalizeTime($data['start_time'] ?? ''),
            'end_time'      => $this->normalizeTime($data['end_time'] ?? ''),
            'description'   => sanitize_textarea_field($data['description'] ?? ''),
            'status'        => $status,
            'room_id'       => $roomId > 0 ? $roomId : null,
            'updated_at'    => current_time('mysql'),
        ];

        $validationError = $this->getReservationValidationError($updateData);

        if (is_wp_error($validationError)) {
            return $validationError;
        }

        if (!$this->validateMaxDuration($updateData['start_time'], $updateData['end_time'])) {
            return new \WP_Error(
                'max_duration_exceeded',
                'A reservation cannot exceed 8 hours.',
                [
                    'start_time' => $updateData['start_time'],
                    'end_time'   => $updateData['end_time'],
                ]
            );
        }

        $result = $this->repository->update($id, $updateData);

        if (is_wp_error($result)) {
            return $result;
        }

        return $result !== false;
    }

    /**
     * Change reservation status admin action.
     */
    public function changeStatus(int $id, string $status): array
    {
        $allowed = ['pending', 'approved', 'rejected'];

        if (!in_array($status, $allowed, true)) {
            return [
                'success' => false,
                'message' => 'Invalid reservation status.',
            ];
        }

        $reservation = $this->repository->findById($id);

        if (is_wp_error($reservation)) {
            return [
                'success' => false,
                'message' => $reservation->get_error_message(),
            ];
        }

        if (!$reservation) {
            return [
                'success' => false,
                'message' => 'Reservation not found.',
            ];
        }

        $updateData = [
            'status'     => $status,
            'updated_at' => current_time('mysql'),
        ];

        if ($status === 'approved') {
            $roomId = $this->repository->findAvailableRoom(
                $reservation['meeting_date'],
                $reservation['start_time'],
                $reservation['end_time']
            );

            if (is_wp_error($roomId)) {
                return [
                    'success' => false,
                    'message' => $roomId->get_error_message(),
                ];
            }

            if (!$roomId) {
                return [
                    'success' => false,
                    'message' => 'No meeting rooms are available for this time slot.',
                ];
            }

            $updateData['room_id'] = $roomId;
        }

        $result = $this->repository->update($id, $updateData);

        if (is_wp_error($result)) {
            return [
                'success' => false,
                'message' => $result->get_error_message(),
            ];
        }

        if ($result === false) {
            return [
                'success' => false,
                'message' => 'Database error while updating reservation.',
            ];
        }

        if ($status === 'approved') {
            $this->sendEmail(
                $reservation['email'],
                'Reservation Approved',
                "Your reservation '{$reservation['meeting_title']}' has been approved."
            );
        }

        if ($status === 'rejected') {
            $this->sendEmail(
                $reservation['email'],
                'Reservation Rejected',
                "Your reservation '{$reservation['meeting_title']}' has been rejected."
            );
        }

        return [
            'success' => true,
            'message' => 'Reservation status updated successfully.',
        ];
    }

    /**
     * Find reservation by token.
     */
    public function findByToken(string $token): ?array
    {
        $reservation = $this->repository->findByToken($token);

        if (is_wp_error($reservation)) {
            return null;
        }

        return $reservation ?: null;
    }

    /**
     * Optional alias for handlers that call getByToken().
     */
    public function getByToken(string $token): ?array
    {
        return $this->findByToken($token);
    }

    /**
     * Calculate minimum rooms required.
     */
    public function calculateMinimumRooms(string $date): int
    {
        $reservations = $this->repository->findByDate($date);

        if (is_wp_error($reservations) || !$reservations) {
            return 0;
        }

        $events = [];

        foreach ($reservations as $r) {
            $events[] = [
                'time' => $this->normalizeTime($r['start_time'] ?? ''),
                'type' => 'start',
            ];

            $events[] = [
                'time' => $this->normalizeTime($r['end_time'] ?? ''),
                'type' => 'end',
            ];
        }

        usort($events, function ($a, $b) {
            /*
             * If one meeting ends exactly when another starts,
             * process "end" before "start" to avoid over-counting rooms.
             */
            if ($a['time'] === $b['time']) {
                if ($a['type'] === $b['type']) {
                    return 0;
                }

                return $a['type'] === 'end' ? -1 : 1;
            }

            return strcmp($a['time'], $b['time']);
        });

        $current = 0;
        $max     = 0;

        foreach ($events as $event) {
            if ($event['type'] === 'start') {
                $current++;
                $max = max($max, $current);
            } else {
                $current--;
                $current = max(0, $current);
            }
        }

        return $max;
    }

    private function sendEmail(string $to, string $subject, string $message): void
    {
        if (!is_email($to)) {
            return;
        }

        wp_mail(
            $to,
            $subject,
            $message,
            ['Content-Type: text/plain; charset=UTF-8']
        );
    }

    private function generateToken(): string
    {
        try {
            return bin2hex(random_bytes(16));
        } catch (\Throwable $e) {
            return wp_generate_password(32, false, false);
        }
    }

    private function sanitizeReservationData(array $data): array
    {
        return [
            'first_name'    => sanitize_text_field($data['first_name'] ?? ''),
            'last_name'     => sanitize_text_field($data['last_name'] ?? ''),
            'mobile'        => sanitize_text_field($data['mobile'] ?? ''),
            'email'         => sanitize_email($data['email'] ?? ''),
            'meeting_title' => sanitize_text_field($data['meeting_title'] ?? ''),
            'meeting_date'  => sanitize_text_field($data['meeting_date'] ?? ''),
            'start_time'    => $this->normalizeTime($data['start_time'] ?? ''),
            'end_time'      => $this->normalizeTime($data['end_time'] ?? ''),
            'description'   => sanitize_textarea_field($data['description'] ?? ''),
        ];
    }

    private function normalizeTime(string $time): string
    {
        $time = sanitize_text_field($time);
        $time = trim($time);

        /*
         * Convert HH:MM to HH:MM:00.
         */
        if (preg_match('/^\d{2}:\d{2}$/', $time)) {
            return $time . ':00';
        }

        /*
         * Keep valid HH:MM:SS.
         */
        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $time)) {
            return $time;
        }

        return $time;
    }

    private function validateReservationData(array $data): bool
    {
        return !is_wp_error($this->getReservationValidationError($data));
    }

    private function getReservationValidationError(array $data)
    {
        if (empty($data['first_name'])) {
            return new \WP_Error(
                'missing_first_name',
                'First name is missing.'
            );
        }

        if (empty($data['last_name'])) {
            return new \WP_Error(
                'missing_last_name',
                'Last name is missing.'
            );
        }

        if (empty($data['mobile'])) {
            return new \WP_Error(
                'missing_mobile',
                'Mobile number is missing.'
            );
        }

        if (empty($data['email'])) {
            return new \WP_Error(
                'missing_email',
                'Email address is missing.'
            );
        }

        if (!is_email($data['email'])) {
            return new \WP_Error(
                'invalid_email',
                'Email address is invalid.',
                [
                    'email' => $data['email'],
                ]
            );
        }

        if (empty($data['meeting_title'])) {
            return new \WP_Error(
                'missing_meeting_title',
                'Meeting title is missing.'
            );
        }

        if (empty($data['meeting_date'])) {
            return new \WP_Error(
                'missing_meeting_date',
                'Meeting date is missing.'
            );
        }

        if (!$this->isValidDate($data['meeting_date'])) {
            return new \WP_Error(
                'invalid_meeting_date',
                'Meeting date format is invalid.',
                [
                    'meeting_date' => $data['meeting_date'],
                ]
            );
        }

        if (empty($data['start_time'])) {
            return new \WP_Error(
                'missing_start_time',
                'Start time is missing.'
            );
        }

        if (empty($data['end_time'])) {
            return new \WP_Error(
                'missing_end_time',
                'End time is missing.'
            );
        }

        if (!$this->isValidTime($data['start_time'])) {
            return new \WP_Error(
                'invalid_start_time',
                'Start time format is invalid.',
                [
                    'start_time' => $data['start_time'],
                ]
            );
        }

        if (!$this->isValidTime($data['end_time'])) {
            return new \WP_Error(
                'invalid_end_time',
                'End time format is invalid.',
                [
                    'end_time' => $data['end_time'],
                ]
            );
        }

        $startTimestamp = strtotime($data['meeting_date'] . ' ' . $data['start_time']);
        $endTimestamp   = strtotime($data['meeting_date'] . ' ' . $data['end_time']);

        if (!$startTimestamp || !$endTimestamp) {
            return new \WP_Error(
                'invalid_datetime',
                'Meeting date or time format is invalid.',
                [
                    'meeting_date' => $data['meeting_date'],
                    'start_time'   => $data['start_time'],
                    'end_time'     => $data['end_time'],
                ]
            );
        }

        if ($endTimestamp <= $startTimestamp) {
            return new \WP_Error(
                'invalid_time_range',
                'Start time must be earlier than end time.',
                [
                    'start_time' => $data['start_time'],
                    'end_time'   => $data['end_time'],
                ]
            );
        }

        return true;
    }

    private function validateMaxDuration(string $start, string $end): bool
    {
        $start = $this->normalizeTime($start);
        $end   = $this->normalizeTime($end);

        $startTS = strtotime($start);
        $endTS   = strtotime($end);

        if (!$startTS || !$endTS) {
            return false;
        }

        $hours = ($endTS - $startTS) / 3600;

        return $hours > 0 && $hours <= 8;
    }

    private function isValidDate(string $date): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return false;
        }

        [$year, $month, $day] = array_map('intval', explode('-', $date));

        return checkdate($month, $day, $year);
    }

    private function isValidTime(string $time): bool
    {
        return (bool) preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $time);
    }
}
