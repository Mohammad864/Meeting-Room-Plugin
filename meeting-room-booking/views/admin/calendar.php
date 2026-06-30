<?php
/**
 * Admin view: reservation calendar page.
 *
 * No PHP variables required — the calendar is fully rendered by FullCalendar JS.
 * Assets are enqueued by CalendarController::enqueueAssets().
 */

if (!defined('ABSPATH')) {
    exit;
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
