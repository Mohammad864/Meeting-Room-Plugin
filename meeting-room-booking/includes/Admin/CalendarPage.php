<?php

namespace MRB\Admin;

use MRB\Database\ReservationRepository;

if (!defined('ABSPATH')) {
    exit;
}

class CalendarPage
{
    /**
     * Render calendar admin page.
     */
    public static function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('You do not have permission to access this page.');
        }

        ?>
        <div class="wrap">
            <h1>Reservation Calendar</h1>

            <div style="margin: 15px 0; padding: 12px; background: #fff; border: 1px solid #ccd0d4; border-radius: 6px;">
                <strong>Legend:</strong>

                <span style="display:inline-block;margin-left:15px;">
                    <span style="display:inline-block;width:12px;height:12px;background:#f0ad4e;border-radius:50%;"></span>
                    Pending
                </span>

                <span style="display:inline-block;margin-left:15px;">
                    <span style="display:inline-block;width:12px;height:12px;background:#46b450;border-radius:50%;"></span>
                    Approved
                </span>

                <span style="display:inline-block;margin-left:15px;">
                    <span style="display:inline-block;width:12px;height:12px;background:#dc3232;border-radius:50%;"></span>
                    Rejected
                </span>

                <span style="display:inline-block;margin-left:15px;">
                    <span style="display:inline-block;width:12px;height:12px;background:#666666;border-radius:50%;"></span>
                    Cancelled
                </span>
            </div>

            <div style="margin-bottom:15px;">
                <label for="mrb-calendar-status-filter">
                    <strong>Status:</strong>
                </label>

                <select id="mrb-calendar-status-filter">
                    <option value="">All Statuses</option>
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>

            <div id="mrb-calendar" style="background:#fff;padding:20px;border:1px solid #ccd0d4;border-radius:6px;"></div>
        </div>
        <?php
    }

    /**
     * Enqueue FullCalendar assets.
     */
    public static function enqueueAssets(string $hook): void
    {
        if (!isset($_GET['page']) || $_GET['page'] !== 'mrb-calendar') {
            return;
        }

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

        wp_add_inline_style(
            'wp-admin',
            '
            #mrb-calendar .fc-event {
                cursor: pointer;
            }

            #mrb-calendar .fc-event-title {
                font-weight: 600;
            }

            #mrb-calendar .fc-toolbar-title {
                font-size: 22px;
                font-weight: 600;
            }
            '
        );
    }

    /**
     * AJAX endpoint for FullCalendar events.
     */
    public static function getEvents(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => 'Permission denied.',
            ], 403);
        }

        check_ajax_referer('mrb_calendar_nonce', 'nonce');

        global $wpdb;

        $start = isset($_GET['start'])
            ? sanitize_text_field(wp_unslash($_GET['start']))
            : '';

        $end = isset($_GET['end'])
            ? sanitize_text_field(wp_unslash($_GET['end']))
            : '';

        $status = isset($_GET['status'])
            ? sanitize_key($_GET['status'])
            : '';

        $allowedStatuses = ['pending', 'approved', 'rejected', 'cancelled'];

        if ($status && !in_array($status, $allowedStatuses, true)) {
            $status = '';
        }

        if (!$start || !$end) {
            wp_send_json([]);
        }

        /*
         * FullCalendar sends ISO date strings.
         * We only need Y-m-d for the database comparison.
         */
        $startDate = substr($start, 0, 10);
        $endDate   = substr($end, 0, 10);

        $table = $wpdb->prefix . 'mrb_reservations';

        if ($status) {
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT *
                     FROM {$table}
                     WHERE meeting_date >= %s
                     AND meeting_date < %s
                     AND status = %s
                     ORDER BY meeting_date ASC, start_time ASC",
                    $startDate,
                    $endDate,
                    $status
                ),
                ARRAY_A
            );
        } else {
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT *
                     FROM {$table}
                     WHERE meeting_date >= %s
                     AND meeting_date < %s
                     ORDER BY meeting_date ASC, start_time ASC",
                    $startDate,
                    $endDate
                ),
                ARRAY_A
            );
        }

        if (!$rows) {
            wp_send_json([]);
        }

        $events = [];

        foreach ($rows as $row) {
            $id = (int) $row['id'];

            $firstName = $row['first_name'] ?? '';
            $lastName  = $row['last_name'] ?? '';
            $fullName  = trim($firstName . ' ' . $lastName);

            $meetingTitle = $row['meeting_title'] ?? 'Untitled Meeting';
            $reservationStatus = $row['status'] ?? 'pending';

            $roomLabel = !empty($row['room_id'])
                ? 'Room #' . (int) $row['room_id']
                : 'No room assigned';

            $color = self::getStatusColor($reservationStatus);

            $title = $meetingTitle;

            if ($fullName) {
                $title .= ' - ' . $fullName;
            }

            $events[] = [
                'id'    => $id,
                'title' => $title,
                'start' => $row['meeting_date'] . 'T' . $row['start_time'],
                'end'   => $row['meeting_date'] . 'T' . $row['end_time'],
                'url'   => admin_url('admin.php?page=mrb-reservations&action=edit&id=' . $id),

                'backgroundColor' => $color,
                'borderColor'     => $color,

                'extendedProps' => [
                    'reservation_id' => $id,
                    'status'         => $reservationStatus,
                    'room'           => $roomLabel,
                    'mobile'         => $row['mobile'] ?? '',
                    'email'          => $row['email'] ?? '',
                    'description'    => $row['description'] ?? '',
                ],
            ];
        }

        wp_send_json($events);
    }

    /**
     * Get calendar event color by status.
     */
    private static function getStatusColor(string $status): string
    {
        $status = sanitize_key($status);

        $colors = [
            'pending'   => '#f0ad4e',
            'approved'  => '#46b450',
            'rejected'  => '#dc3232',
            'cancelled' => '#666666',
        ];

        return $colors[$status] ?? '#777777';
    }
}
