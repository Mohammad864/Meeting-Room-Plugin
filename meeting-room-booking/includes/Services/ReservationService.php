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

        $token = $this->generateToken();
        
        $clean['edit_token']   = $token;
        $clean['status']       = 'pending';
        $clean['created_at']   = current_time('mysql');
        $clean['updated_at']   = current_time('mysql');

        $insertedId = $this->repository->create($clean);

        if (!$insertedId) {
            return [
                'success' => false,
                'errors'  => ['Database error: Could not save reservation.']
            ];
        }

        return [
            'success' => true,
            'id'      => $insertedId,
            'token'   => $token
        ];
    }

    /**
     * Update reservation using token (guest editing)
     */
    public function updateReservation(string $token, array $data): bool
    {
        $reservation = $this->repository->findByToken($token);

        if (!$reservation || $reservation['status'] === 'cancelled') {
            return false;
        }

        $clean = $this->sanitizeReservationData($data);

        if (!$this->validateReservationData($clean)) {
            return false;
        }

        $clean['updated_at'] = current_time('mysql');

        return $this->repository->update((int)$reservation['id'], $clean);
    }

    /**
     * Cancel reservation via token
     */
    public function cancelByToken(string $token): bool
    {
        $reservation = $this->repository->findByToken($token);
        if (!$reservation) {
            return false;
        }

        return $this->repository->update((int)$reservation['id'], [
            'status'     => 'cancelled',
            'updated_at' => current_time('mysql')
        ]);
    }

    /**
     * Admin edit reservation
     */
    public function adminEdit(int $id, array $data): bool
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
            'start_time'    => sanitize_text_field($data['start_time'] ?? ''),
            'end_time'      => sanitize_text_field($data['end_time'] ?? ''),
            'description'   => sanitize_textarea_field($data['description'] ?? ''),
            'status'        => $status,
            'room_id'       => $roomId > 0 ? $roomId : null,
            'updated_at'    => current_time('mysql'),
        ];

        return $this->repository->update($id, $updateData);
    }

    /**
     * Change reservation status (admin quick action)
     */
    public function changeStatus(int $id, string $status): bool
    {
        $allowed = ['pending', 'approved', 'rejected'];
        
        if (!in_array($status, $allowed, true)) {
            return false;
        }

        return $this->repository->update($id, [
            'status'     => $status,
            'updated_at' => current_time('mysql')
        ]);
    }

    /**
     * Find reservation by token
     */
    public function findByToken(string $token): ?array
    {
        return $this->repository->findByToken($token) ?: null;
    }

    /**
     * Calculate minimum rooms required
     */
    public function calculateMinimumRooms(string $date): int
    {
        $reservations = $this->repository->findByDate($date);
        if (!$reservations) {
            return 0;
        }

        $events = [];
        foreach ($reservations as $r) {
            $events[] = ['time' => $r['start_time'], 'type' => 'start'];
            $events[] = ['time' => $r['end_time'], 'type' => 'end'];
        }

        usort($events, function ($a, $b) {
            return strcmp($a['time'], $b['time']);
        });

        $current = 0;
        $max = 0;
        foreach ($events as $event) {
            if ($event['type'] === 'start') {
                $current++;
                $max = max($max, $current);
            } else {
                $current--;
            }
        }
        return $max;
    }

    private function generateToken(): string
    {
        return bin2hex(random_bytes(16));
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
            'start_time'    => sanitize_text_field($data['start_time'] ?? ''),
            'end_time'      => sanitize_text_field($data['end_time'] ?? ''),
            'description'   => sanitize_textarea_field($data['description'] ?? ''),
        ];
    }

    private function validateReservationData(array $data): bool
    {
        return !empty($data['first_name']) &&
               !empty($data['last_name']) &&
               !empty($data['mobile']) &&
               !empty($data['email']) &&
               is_email($data['email']) &&
               !empty($data['meeting_title']) &&
               !empty($data['meeting_date']) &&
               !empty($data['start_time']) &&
               !empty($data['end_time']);
    }
}
