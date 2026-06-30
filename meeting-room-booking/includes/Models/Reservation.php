<?php

namespace MRB\Models;

use MRB\Enums\ReservationStatus;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Reservation domain model / data-transfer object.
 *
 * Wraps a raw database row so the rest of the codebase can work with
 * typed properties and domain methods instead of raw array access.
 */
class Reservation
{
    public int     $id           = 0;
    public string  $firstName    = '';
    public string  $lastName     = '';
    public string  $email        = '';
    public string  $mobile       = '';
    public string  $meetingTitle = '';
    public string  $meetingDate  = '';
    public string  $startTime    = '';
    public string  $endTime      = '';
    public string  $description  = '';
    public ?int    $roomId       = null;
    public string  $status       = ReservationStatus::PENDING;
    public string  $createdAt    = '';
    public ?string $updatedAt    = null;
    public string  $editToken    = '';
    public ?string $cancelledAt  = null;

    /**
     * Build a Reservation from a raw database array.
     */
    public static function fromArray(array $data): self
    {
        $model = new self();

        $model->id           = isset($data['id'])           ? (int) $data['id']           : 0;
        $model->firstName    = isset($data['first_name'])   ? (string) $data['first_name'] : '';
        $model->lastName     = isset($data['last_name'])    ? (string) $data['last_name']  : '';
        $model->email        = isset($data['email'])        ? (string) $data['email']      : '';
        $model->mobile       = isset($data['mobile'])       ? (string) $data['mobile']     : '';
        $model->meetingTitle = isset($data['meeting_title'])? (string) $data['meeting_title'] : '';
        $model->meetingDate  = isset($data['meeting_date']) ? (string) $data['meeting_date']  : '';
        $model->startTime    = isset($data['start_time'])   ? (string) $data['start_time'] : '';
        $model->endTime      = isset($data['end_time'])     ? (string) $data['end_time']   : '';
        $model->description  = isset($data['description'])  ? (string) $data['description'] : '';
        $model->roomId       = isset($data['room_id']) && $data['room_id'] !== null
                                ? (int) $data['room_id'] : null;
        $model->status       = isset($data['status'])       ? (string) $data['status']     : ReservationStatus::PENDING;
        $model->createdAt    = isset($data['created_at'])   ? (string) $data['created_at'] : '';
        $model->updatedAt    = isset($data['updated_at'])   ? (string) $data['updated_at'] : null;
        $model->editToken    = isset($data['edit_token'])   ? (string) $data['edit_token'] : '';
        $model->cancelledAt  = isset($data['cancelled_at']) ? (string) $data['cancelled_at'] : null;

        return $model;
    }

    /** Full name of the person who made the reservation. */
    public function fullName(): string
    {
        return trim($this->firstName . ' ' . $this->lastName);
    }

    /** Human-readable time range, e.g. "09:00 – 10:30". */
    public function timeRange(): string
    {
        return $this->startTime . ' – ' . $this->endTime;
    }

    /** Whether the reservation is in a final state that prevents guest edits. */
    public function isLocked(): bool
    {
        return ReservationStatus::isLocked($this->status);
    }

    /** Convert back to a raw array (compatible with repository write methods). */
    public function toArray(): array
    {
        return [
            'id'            => $this->id,
            'first_name'    => $this->firstName,
            'last_name'     => $this->lastName,
            'email'         => $this->email,
            'mobile'        => $this->mobile,
            'meeting_title' => $this->meetingTitle,
            'meeting_date'  => $this->meetingDate,
            'start_time'    => $this->startTime,
            'end_time'      => $this->endTime,
            'description'   => $this->description,
            'room_id'       => $this->roomId,
            'status'        => $this->status,
            'created_at'    => $this->createdAt,
            'updated_at'    => $this->updatedAt,
            'edit_token'    => $this->editToken,
            'cancelled_at'  => $this->cancelledAt,
        ];
    }
}
