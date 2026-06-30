<?php

namespace MRB\Services;

use MRB\Contracts\ReservationRepositoryInterface;

if (!defined("ABSPATH")) {
    exit();
}

/**
 * Checks whether a specific room has a scheduling conflict.
 *
 * Used by RoomAllocator to find a free room for a requested time slot.
 */
class ConflictDetector
{
    private ReservationRepositoryInterface $repository;

    public function __construct(ReservationRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Return true if $roomId already has an approved reservation that overlaps
     * the requested [startTime, endTime) window on $date.
     */
    public function hasConflict(
        int $roomId,
        string $date,
        string $startTime,
        string $endTime,
        ?int $excludeReservationId = null,
    ): bool {
        return $this->repository->countOverlappingApprovedForRoom(
            $roomId,
            $date,
            $startTime,
            $endTime,
            $excludeReservationId ?? 0,
        ) > 0;
    }
}
