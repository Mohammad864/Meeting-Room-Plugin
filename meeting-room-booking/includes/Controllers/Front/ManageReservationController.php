<?php

namespace MRB\Controllers\Front;

use MRB\Contracts\ReservationRepositoryInterface;
use MRB\Enums\ReservationStatus;
use MRB\Support\View;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Renders the public "Manage Your Reservation" page.
 *
 * Called from Plugin::handleReservationRoute() when /reservation/{token}/ matches.
 */
class ManageReservationController
{
    private ReservationRepositoryInterface $repository;

    public function __construct(ReservationRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Render the full reservation management page and exit.
     */
    public function show(string $token): void
    {
        $token       = sanitize_text_field($token);
        $reservation = $this->repository->findByToken($token);

        if (!$reservation) {
            wp_die(esc_html__('Reservation not found.', 'meeting-room-booking'));
        }

        wp_enqueue_style('mrb-manage-reservation');

        $status    = (string) ($reservation['status'] ?? '');
        $canManage = !ReservationStatus::isLocked($status);

        get_header();

        View::output('front/manage-reservation', [
            'token'       => $token,
            'reservation' => $reservation,
            'status'      => $status,
            'canManage'   => $canManage,
        ]);

        get_footer();
        exit;
    }
}
