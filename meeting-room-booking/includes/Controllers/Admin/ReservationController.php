<?php

namespace MRB\Controllers\Admin;

use MRB\Contracts\ReservationRepositoryInterface;
use MRB\Enums\ReservationStatus;
use MRB\Services\MinimumRoomsCalculator;
use MRB\Support\View;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles the "Meeting Bookings" admin menu page.
 *
 * Registered as the page callback in Plugin::registerAdminPages().
 * Dispatches to index() (list) or edit() based on ?action=edit.
 */
class ReservationController
{
    private ReservationRepositoryInterface $repository;
    private MinimumRoomsCalculator $calculator;

    public function __construct(
        ReservationRepositoryInterface $repository,
        MinimumRoomsCalculator $calculator
    ) {
        $this->repository = $repository;
        $this->calculator = $calculator;
    }

    /**
     * WordPress menu page callback — dispatches to edit() or index().
     */
    public function dispatch(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'meeting-room-booking'));
        }

        $action = isset($_GET['action']) ? sanitize_key($_GET['action']) : '';

        if ($action === 'edit') {
            $this->edit();
            return;
        }

        $this->index();
    }

    /**
     * Reservation list page.
     */
    public function index(): void
    {
        $search = isset($_GET['s'])            ? sanitize_text_field(wp_unslash($_GET['s']))            : '';
        $date   = isset($_GET['meeting_date']) ? sanitize_text_field(wp_unslash($_GET['meeting_date'])) : '';
        $status = isset($_GET['status'])       ? sanitize_key($_GET['status'])                          : '';

        if (!ReservationStatus::isValid($status)) {
            $status = '';
        }

        $args = [
            'search' => $search,
            'date'   => $date,
            'status' => $status,
            'limit'  => 100,
            'offset' => 0,
        ];

        $reservations    = $this->repository->query($args);
        $calculationDate = $date ?: date('Y-m-d');
        $minimumRooms    = $this->calculateMinimumRooms($calculationDate);

        View::output('admin/reservation-list', [
            'reservations'    => $reservations,
            'filters'         => [
                'search' => $search,
                'date'   => $date,
                'status' => $status,
            ],
            'minimumRooms'    => $minimumRooms,
            'calculationDate' => $calculationDate,
        ]);
    }

    /**
     * Reservation edit form page.
     */
    public function edit(): void
    {
        $id          = isset($_GET['id']) ? absint($_GET['id']) : 0;
        $reservation = $id > 0 ? $this->repository->findById($id) : null;

        View::output('admin/reservation-edit', [
            'id'          => $id,
            'reservation' => $reservation,
        ]);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function calculateMinimumRooms(string $date): int
    {
        $rows = $this->repository->findActiveByDate($date);

        if (empty($rows)) {
            return 0;
        }

        return $this->calculator->calculate($rows);
    }
}
