<?php

namespace MRB\Support;

use MRB\Enums\ReservationStatus;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Renders a coloured status badge.
 *
 * Single source of truth — replaces the two separate implementations
 * that used to live in AdminPage and Plugin.
 */
final class StatusBadge
{
    private const COLORS = [
        ReservationStatus::PENDING   => '#f59e0b',
        ReservationStatus::APPROVED  => '#16a34a',
        ReservationStatus::REJECTED  => '#dc2626',
        ReservationStatus::CANCELLED => '#6b7280',
    ];

    public static function render(string $status): string
    {
        $status = sanitize_key($status ?: 'unknown');
        $color  = self::COLORS[$status] ?? '#6b7280';
        $label  = ReservationStatus::label($status);

        return sprintf(
            '<span class="mrb-status-badge mrb-status-%1$s" style="display:inline-block;padding:4px 10px;border-radius:4px;background:%2$s;color:#fff;font-size:12px;font-weight:600;">%3$s</span>',
            esc_attr($status),
            esc_attr($color),
            esc_html($label)
        );
    }
}
