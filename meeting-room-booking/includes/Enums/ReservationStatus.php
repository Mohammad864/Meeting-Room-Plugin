<?php

namespace MRB\Enums;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Reservation status constants.
 *
 * Single source of truth for every status string used across the plugin.
 */
final class ReservationStatus
{
    const PENDING   = 'pending';
    const APPROVED  = 'approved';
    const REJECTED  = 'rejected';
    const CANCELLED = 'cancelled';

    /** All valid statuses. */
    const ALL = [
        self::PENDING,
        self::APPROVED,
        self::REJECTED,
        self::CANCELLED,
    ];

    /** Statuses an admin may set via the status-change action. */
    const ADMIN_SETTABLE = [
        self::PENDING,
        self::APPROVED,
        self::REJECTED,
    ];

    /**
     * Statuses that prevent further guest edits or cancellations.
     * A reservation with one of these statuses is considered "locked".
     */
    const LOCKED = [
        self::CANCELLED,
        self::REJECTED,
    ];

    public static function isValid(string $status): bool
    {
        return in_array($status, self::ALL, true);
    }

    public static function isAdminSettable(string $status): bool
    {
        return in_array($status, self::ADMIN_SETTABLE, true);
    }

    public static function isLocked(string $status): bool
    {
        return in_array($status, self::LOCKED, true);
    }

    /** Human-readable label for display. */
    public static function label(string $status): string
    {
        return ucfirst($status);
    }
}
