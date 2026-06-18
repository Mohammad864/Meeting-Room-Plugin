<?php

namespace MRB\Admin;

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
            wp_die(esc_html__('You do not have permission to access this page.', 'meeting-room-booking'));
        }
        ?>

        <div class="wrap mrb-admin-wrap">

            <div class="mrb-admin-page-header">
                <div>
                    <h1 class="mrb-admin-page-title">
                        <?php esc_html_e('Reservation Calendar', 'meeting-room-booking'); ?>
                    </h1>

                    <p class="mrb-admin-page-subtitle">
                        <?php esc_html_e('View and manage meeting room reservations in a calendar view.', 'meeting-room-booking'); ?>
                    </p>
                </div>
            </div>

            <div class="mrb-admin-card">
                <div class="mrb-admin-card-body">

                    <div class="mrb-calendar-legend">
                        <strong class="mrb-calendar-legend-title">
                            <?php esc_html_e('Legend:', 'meeting-room-booking'); ?>
                        </strong>

                        <span class="mrb-badge mrb-badge-pending">
                            <?php esc_html_e('Pending', 'meeting-room-booking'); ?>
                        </span>

                        <span class="mrb-badge mrb-badge-confirmed">
                            <?php esc_html_e('Approved', 'meeting-room-booking'); ?>
                        </span>

                        <span class="mrb-badge mrb-badge-rejected">
                            <?php esc_html_e('Rejected', 'meeting-room-booking'); ?>
                        </span>

                        <span class="mrb-badge mrb-badge-cancelled">
                            <?php esc_html_e('Cancelled', 'meeting-room-booking'); ?>
                        </span>
                    </div>

                    <div class="mrb-filter-bar">

                        <div class="mrb-filter-group">
                            <label for="mrb-calendar-status-filter">
                                <?php esc_html_e('Status', 'meeting-room-booking'); ?>
                            </label>

                            <select id="mrb-calendar-status-filter" class="mrb-admin-select">
                                <option value="">
                                    <?php esc_html_e('All Statuses', 'meeting-room-booking'); ?>
                                </option>

                                <option value="pending">
                                    <?php esc_html_e('Pending', 'meeting-room-booking'); ?>
                                </option>

                                <option value="approved">
                                    <?php esc_html_e('Approved', 'meeting-room-booking'); ?>
                                </option>

                                <option value="rejected">
                                    <?php esc_html_e('Rejected', 'meeting-room-booking'); ?>
                                </option>

                                <option value="cancelled">
                                    <?php esc_html_e('Cancelled', 'meeting-room-booking'); ?>
                                </option>
                            </select>
                        </div>

                    </div>

                    <div class="mrb-calendar-loading" aria-hidden="true">
                        <?php esc_html_e('Loading calendar...', 'meeting-room-booking'); ?>
                    </div>

                    <div id="mrb-calendar" class="mrb-admin-calendar"></div>

                </div>
            </div>

        </div>

        <?php
    }

    /**
     * Enqueue FullCalendar assets.
     */
    public static function enqueueAssets(string $hook): void
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
     * AJAX endpoint for FullCalendar events.
     */
    public static function getEvents(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(
                ['message' => __('Permission denied.', 'meeting-room-booking')],
                403
            );
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

            $color = self::getStatusColor($reservationStatus);

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
                    'mobile'         => isset($row['mobile']) ? sanitize_text_field($row['mobile']) : '',
                    'email'          => isset($row['email']) ? sanitize_email($row['email']) : '',
                    'description'    => isset($row['description']) ? sanitize_textarea_field($row['description']) : '',
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
            'pending'   => '#f59e0b',
            'approved'  => '#16a34a',
            'rejected'  => '#dc2626',
            'cancelled' => '#6b7280',
        ];

        return $colors[$status] ?? '#6b7280';
    }
}
