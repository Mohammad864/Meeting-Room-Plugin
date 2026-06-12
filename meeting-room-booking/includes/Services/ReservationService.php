<?php

namespace MRB\Services;

use MRB\Database\ReservationRepository;
use MRB\Support\Validator;

if (!defined('ABSPATH')) {
    exit;
}

class ReservationService
{
    private ReservationRepository $reservations;
    private RoomAllocator $roomAllocator;

    public function __construct(
        ?ReservationRepository $reservations = null,
        ?RoomAllocator $roomAllocator = null
    ) {
        $this->reservations = $reservations ?: new ReservationRepository();
        $this->roomAllocator = $roomAllocator ?: new RoomAllocator();
    }

    public function create(array $input): array
    {
        $data = $this->sanitize($input);
        $errors = Validator::validateReservation($data);

        if (!empty($errors)) {
            return [
                'success' => false,
                'errors' => $errors,
            ];
        }

        $roomId = $this->roomAllocator->allocate(
            $data['meeting_date'],
            $data['start_time'],
            $data['end_time']
        );

        $data['room_id'] = $roomId;
        $data['status'] = 'pending';

        $id = $this->reservations->create($data);

        return [
            'success' => true,
            'id' => $id,
            'room_id' => $roomId,
        ];
    }

    public function approve(int $reservationId): array
    {
        $reservation = $this->reservations->find($reservationId);

        if (!$reservation) {
            return [
                'success' => false,
                'message' => 'Reservation not found.',
            ];
        }

        $roomId = $this->roomAllocator->allocate(
            $reservation['meeting_date'],
            $reservation['start_time'],
            $reservation['end_time'],
            $reservationId
        );

        if (!$roomId) {
            return [
                'success' => false,
                'message' => 'No available room for this time range.',
            ];
        }

        $this->reservations->updateStatus($reservationId, 'approved', $roomId);

        return [
            'success' => true,
            'message' => 'Reservation approved successfully.',
        ];
    }

    public function reject(int $reservationId): array
    {
        $reservation = $this->reservations->find($reservationId);

        if (!$reservation) {
            return [
                'success' => false,
                'message' => 'Reservation not found.',
            ];
        }

        $this->reservations->updateStatus($reservationId, 'rejected');

        return [
            'success' => true,
            'message' => 'Reservation rejected successfully.',
        ];
    }

    private function sanitize(array $input): array
    {
        return [
            'first_name' => sanitize_text_field($input['first_name'] ?? ''),
            'last_name' => sanitize_text_field($input['last_name'] ?? ''),
            'mobile' => sanitize_text_field($input['mobile'] ?? ''),
            'email' => sanitize_email($input['email'] ?? ''),
            'meeting_title' => sanitize_text_field($input['meeting_title'] ?? ''),
            'meeting_date' => sanitize_text_field($input['meeting_date'] ?? ''),
            'start_time' => sanitize_text_field($input['start_time'] ?? ''),
            'end_time' => sanitize_text_field($input['end_time'] ?? ''),
            'description' => sanitize_textarea_field($input['description'] ?? ''),
        ];
    }
}
