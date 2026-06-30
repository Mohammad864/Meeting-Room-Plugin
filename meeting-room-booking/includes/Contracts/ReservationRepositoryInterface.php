<?php

namespace MRB\Contracts;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Repository contract for reservations.
 *
 * Depend on this interface, not the concrete class, so implementations
 * can be swapped (e.g. for testing).
 */
interface ReservationRepositoryInterface
{
    public function create(array $data): int;

    public function update(int $id, array $data): bool;

    public function findById(int $id): ?array;

    public function findByToken(string $token): ?array;

    public function query(array $args = []): array;

    public function getTotalCount(array $args = []): int;

    /** Returns approved reservations for the given date. */
    public function findByDate(string $date): array;

    /** Returns non-cancelled/rejected reservations for a date (for room-count calculation). */
    public function findActiveByDate(string $date): array;

    public function getBookedTimesBetweenDates(string $startDate, string $endDate): array;

    /**
     * Returns approved reservations for a date range (used by the calendar AJAX endpoint).
     *
     * @param string $status Empty string means all statuses.
     */
    public function findByDateRange(string $startDate, string $endDate, string $status = ''): array;

    public function findOverlapping(string $date, string $startTime, string $endTime): array;

    public function countOverlappingApproved(
        string $date,
        string $startTime,
        string $endTime,
        int $excludeId = 0
    ): int;

    /**
     * Count overlapping APPROVED reservations for a specific room.
     * Used by ConflictDetector to check per-room availability.
     */
    public function countOverlappingApprovedForRoom(
        int $roomId,
        string $date,
        string $startTime,
        string $endTime,
        int $excludeId = 0
    ): int;

    /** Find the ID of a room that is free for the requested slot. */
    public function findAvailableRoom(string $date, string $startTime, string $endTime): ?int;
}
