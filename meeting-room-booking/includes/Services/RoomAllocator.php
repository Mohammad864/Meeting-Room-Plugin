<?php

namespace MRB\Services;

use MRB\Repositories\RoomRepository;

if (!defined("ABSPATH")) {
    exit();
}

/**
 * Allocates the first available room for a requested time slot.
 *
 * Iterates over all rooms and uses ConflictDetector to skip rooms that already
 * have an approved overlapping reservation.
 */
class RoomAllocator
{
    private RoomRepository $rooms;
    private ConflictDetector $conflictDetector;

    public function __construct(
        RoomRepository $rooms,
        ConflictDetector $conflictDetector,
    ) {
        $this->rooms = $rooms;
        $this->conflictDetector = $conflictDetector;
    }

    /**
     * Return the ID of the first available room, or null if none is free.
     */
    public function allocate(
        string $date,
        string $startTime,
        string $endTime,
        ?int $excludeReservationId = null,
    ): ?int {
        foreach ($this->rooms->all() as $room) {
            $roomId = (int) $room["id"];

            if (
                !$this->conflictDetector->hasConflict(
                    $roomId,
                    $date,
                    $startTime,
                    $endTime,
                    $excludeReservationId,
                )
            ) {
                return $roomId;
            }
        }

        return null;
    }
}
