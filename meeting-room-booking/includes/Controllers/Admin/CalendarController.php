<?php

namespace MRB\Controllers\Admin;

use MRB\Contracts\ReservationRepositoryInterface;
use MRB\Support\View;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles the "Reservation Calendar" admin page and its AJAX events endpoint.
 */
class CalendarController
{
    private ReservationRepositoryInterface $repository;

    public function __construct(ReservationRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * WordPress menu page callback.
     */
    public function show(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'meeting-room-booking'));
        }

        View::output('admin/calendar', []);
    }

    /**
     * Enqueue FullCalendar and local calendar.js assets.
     *
     * Hook: admin_enqueue_scripts
     *
     * @param string $hook Current admin page hook suffix (unused — we key off $_GET['page']).
     */
    public function enqueueAssets(string $hook): void
    {
        $page = isset($_GET['page'])
            ? sanitize_key(wp_unslash($_GET['page']))
            : '';

        if ($page !== 'mrb-calendar') {
            return;
        }

        wp_enqueue_style('mrb-admin-dashboard');
        wp_enqueue_style('mrb-admin-calendar');

        wp_enqueue_script(
            'mrb-fullcalendar',
            'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js',
            [],
            '6.1.15',
            true
        );

        wp_enqueue_script(
            'mrb-calendar-admin',
            plugin_dir_url(MRB_PLUGIN_FILE) . 'assets/admin/js/calendar.js',
            ['mrb-fullcalendar'],
            MRB_VERSION,
            true
        );

        wp_localize_script(
            'mrb-calendar-admin',
            'MRBCalendar',
            [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('mrb_calendar_nonce'),
                'editUrl' => admin_url('admin.php?page=mrb-reservations&action=edit&id='),
            ]
        );
    }

    /**
     * AJAX handler: return FullCalendar event objects for the requested date range.
     *
     * Hook: wp_ajax_mrb_get_calendar_events
     */
    public function handleGetEvents(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(
                ['message' => __('Permission denied.', 'meeting-room-booking')],
                403
            );
        }

        check_ajax_referer('mrb_calendar_nonce', 'nonce');

        $start = isset($_GET['start'])
            ? sanitize_text_field(wp_unslash($_GET['start']))
            : '';

        $end = isset($_GET['end'])
            ? sanitize_text_field(wp_unslash($_GET['end']))
            : '';

        $status = isset($_GET['status'])
            ? sanitize_key(wp_unslash($_GET['status']))
            : '';

        $allowedStatuses = ['pending', 'approved', 'rejected', 'cancelled'];

        if ($status && !in_array($status, $allowedStatuses, true)) {
            $status = '';
        }

        if (!$start || !$end) {
            wp_send_json([]);
        }

        $startDate = substr($start, 0, 10);
        $endDate   = substr($end, 0, 10);

        $rows = $this->repository->findByDateRange($startDate, $endDate, $status);

        if (!$rows) {
            wp_send_json([]);
        }

        $events = [];

        foreach ($rows as $row) {
            $id = isset($row['id']) ? (int) $row['id'] : 0;

            if ($id <= 0) {
                continue;
            }

            $firstName = isset($row['first_name'])
                ? sanitize_text_field($row['first_name'])
                : '';

            $lastName = isset($row['last_name'])
                ? sanitize_text_field($row['last_name'])
                : '';

            $fullName = trim($firstName . ' ' . $lastName);

            $meetingTitle = !empty($row['meeting_title'])
                ? sanitize_text_field($row['meeting_title'])
                : __('Untitled Meeting', 'meeting-room-booking');

            $reservationStatus = !empty($row['status'])
                ? sanitize_key($row['status'])
                : 'pending';

            $roomLabel = !empty($row['room_id'])
                ? sprintf(
                    /* translators: %d: Room ID. */
                    __('Room #%d', 'meeting-room-booking'),
                    (int) $row['room_id']
                )
                : __('No room assigned', 'meeting-room-booking');

            $color = $this->getStatusColor($reservationStatus);

            $title = $meetingTitle;

            if ($fullName) {
                $title .= ' - ' . $fullName;
            }

            $meetingDate = isset($row['meeting_date'])
                ? sanitize_text_field($row['meeting_date'])
                : '';

            $startTime = isset($row['start_time'])
                ? sanitize_text_field($row['start_time'])
                : '';

            $endTime = isset($row['end_time'])
                ? sanitize_text_field($row['end_time'])
                : '';

            if (!$meetingDate || !$startTime || !$endTime) {
                continue;
            }

            $events[] = [
                'id'    => $id,
                'title' => $title,
                'start' => $meetingDate . 'T' . $startTime,
                'end'   => $meetingDate . 'T' . $endTime,
                'url'   => admin_url('admin.php?page=mrb-reservations&action=edit&id=' . $id),

                'backgroundColor' => $color,
                'borderColor'     => $color,

                'extendedProps' => [
                    'reservation_id' => $id,
                    'status'         => $reservationStatus,
                    'room'           => $roomLabel,
                    'mobile'         => isset($row['mobile'])      ? sanitize_text_field($row['mobile'])          : '',
                    'email'          => isset($row['email'])        ? sanitize_email($row['email'])                : '',
                    'description'    => isset($row['description'])  ? sanitize_textarea_field($row['description']) : '',
                ],
            ];
        }

        wp_send_json($events);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Map a reservation status to a calendar event hex color.
     */
    private function getStatusColor(string $status): string
    {
        $status = sanitize_key($status);

        $colors = [
            'pending'   => '#f59e0b',
            'approved'  => '#16a34a',
            'rejected'  => '#dc2626',
            'cancelled' => '#6b7280',
        ];

        return $colors[$status] ?? '#6b7280';
    }
}
