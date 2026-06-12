<?php

namespace MRB\Services;

use MRB\Database\ReservationRepository;

if (!defined('ABSPATH')) {
    exit;
}

class ConflictDetector
{
    private ReservationRepository $reservations;

    public function __construct(?ReservationRepository $reservations = null)
    {
        $this->reservations = $reservations ?: new ReservationRepository();
    }

    public function hasConflict(
        int $roomId,
        string $date,
        string $startTime,
        string $endTime,
        ?int $excludeReservationId = null
    ): bool {
        return $this->reservations->hasConflict(
            $roomId,
            $date,
            $startTime,
            $endTime,
            $excludeReservationId
        );
    }
}
