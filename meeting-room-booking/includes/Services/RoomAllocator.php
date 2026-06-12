<?php

namespace MRB\Services;

use MRB\Database\RoomRepository;

if (!defined('ABSPATH')) {
    exit;
}

class RoomAllocator
{
    private RoomRepository $rooms;
    private ConflictDetector $conflictDetector;

    public function __construct(
        ?RoomRepository $rooms = null,
        ?ConflictDetector $conflictDetector = null
    ) {
        $this->rooms = $rooms ?: new RoomRepository();
        $this->conflictDetector = $conflictDetector ?: new ConflictDetector();
    }

    public function allocate(
        string $date,
        string $startTime,
        string $endTime,
        ?int $excludeReservationId = null
    ): ?int {
        $rooms = $this->rooms->all();

        foreach ($rooms as $room) {
            $roomId = (int) $room['id'];

            if (!$this->conflictDetector->hasConflict(
                $roomId,
                $date,
                $startTime,
                $endTime,
                $excludeReservationId
            )) {
                return $roomId;
            }
        }

        return null;
    }
}
