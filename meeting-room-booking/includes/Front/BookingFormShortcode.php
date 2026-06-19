<?php

namespace MRB\Front;

use DateInterval;
use DateTimeImmutable;
use Exception;
use MRB\Services\ReservationService;
use MRB\Services\EmailNotificationService;
use MRB\Database\ReservationRepository;
use MRB\Database\RoomRepository;


if (!defined('ABSPATH')) {
    exit;
}

class BookingFormShortcode
{
    public static function render(): string
    {
        wp_enqueue_style('mrb-booking-form');
        wp_enqueue_script('jquery');

        $ajaxData = [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('mrb_get_booked_times_range'),
        ];

        wp_add_inline_script(
            'jquery',
            'window.MRBBookingForm = ' . wp_json_encode($ajaxData) . ';',
            'before'
        );

        wp_add_inline_script(
            'jquery',
            self::getInlineScript(),
            'after'
        );

        // wp_add_inline_style(
        //     'mrb-booking-form',
        //     self::getInlineStyle()
        // );

        $message = '';

        $status = isset($_GET['mrb_status'])
            ? sanitize_key(wp_unslash($_GET['mrb_status']))
            : '';

        if ($status === 'success') {
            $token = isset($_GET['token'])
                ? sanitize_text_field(wp_unslash($_GET['token']))
                : '';

            if ($token) {
                $manageLink = home_url('/reservation/' . $token);

                $message = '
                <div class="mrb-notice mrb-notice-success mrb-success-card">
                    <div class="mrb-success-icon">✓</div>
                    <div class="mrb-success-content">
                        <strong>Reservation submitted successfully.</strong>
                        <p class="mrb-manage-link-label">Save this link to manage your booking later:</p>

                        <div class="mrb-manage-link-row">
                            <a class="mrb-manage-link" id="mrb-manage-link" href="' . esc_url($manageLink) . '">' . esc_html($manageLink) . '</a>
                            <button type="button" class="mrb-copy-link-btn" data-copy-target="#mrb-manage-link">Copy link</button>
                        </div>

                        <p class="mrb-copy-feedback" id="mrb-copy-feedback" style="display:none;">Link copied.</p>
                    </div>
                </div>';
            }
        }

        if ($status === 'error') {
            $error = isset($_GET['mrb_error'])
                ? sanitize_text_field(wp_unslash($_GET['mrb_error']))
                : 'Something went wrong.';

            $message = '
            <div class="mrb-notice mrb-notice-error mrb-error-card">
                <strong>Could not submit reservation.</strong>
                <p>' . esc_html($error) . '</p>
            </div>';
        }

        ob_start();

        echo $message;
        ?>

        <form method="post"
              action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
              class="mrb-booking-form"
              id="mrb-booking-form"
              novalidate>

            <?php wp_nonce_field('mrb_submit_booking', 'mrb_nonce'); ?>
            <input type="hidden" name="action" value="mrb_submit_booking">

            <input type="hidden" name="start_time" id="mrb_start_time">
            <input type="hidden" name="end_time" id="mrb_end_time">

            <div class="mrb-card mrb-booking-card">

                <div class="mrb-header mrb-booking-header">
                    <div>
                        <span class="mrb-eyebrow">Meeting room booking</span>
                        <h2 class="mrb-title">Reserve a meeting room</h2>
                        <p class="mrb-subtitle">
                            Choose your meeting details, pick an available time, and submit your reservation.
                        </p>
                    </div>
                </div>

                <div class="mrb-steps" aria-label="Booking steps">

                    <button type="button" class="mrb-step is-active" data-target="meeting">
                        <span>1</span>
                        <strong>Meeting</strong>
                    </button>

                    <button type="button" class="mrb-step" data-target="time">
                        <span>2</span>
                        <strong>Time</strong>
                    </button>

                    <button type="button" class="mrb-step" data-target="contact">
                        <span>3</span>
                        <strong>Contact</strong>
                    </button>

                </div>


                <div id="mrb-form-feedback" class="mrb-form-feedback" style="display:none;"></div>

                <section id="mrb-section-meeting" class="mrb-section">
                    <div class="mrb-section-header">
                        <h3>Meeting details</h3>
                        <p>Tell us what the reservation is for.</p>
                    </div>

                    <div class="mrb-field">
                        <label for="mrb_meeting_title">Meeting Title <span aria-hidden="true">*</span></label>
                        <input
                            type="text"
                            name="meeting_title"
                            id="mrb_meeting_title"
                            placeholder="Example: Weekly planning meeting"
                            autocomplete="off"
                            required>
                    </div>

                    <div class="mrb-field">
                        <label for="mrb_description">Description</label>
                        <textarea
                            name="description"
                            id="mrb_description"
                            rows="4"
                            placeholder="Optional notes, agenda, equipment needs, or extra details."></textarea>
                    </div>
                </section>

                <section id="mrb-section-time" class="mrb-section">
                    <div class="mrb-section-header">
                        <h3>Date and time</h3>
                        <p>Select a date, then choose an available time from the calendar.</p>
                    </div>

                    <div class="mrb-grid mrb-grid-date">
                        <div class="mrb-field">
                            <label for="mrb_meeting_date">Meeting Date <span aria-hidden="true">*</span></label>
                            <input
                                type="date"
                                name="meeting_date"
                                id="mrb_meeting_date"
                                required>
                        </div>

                        <div class="mrb-field mrb-selected-time-field">
                            <label>Selected Time <span aria-hidden="true">*</span></label>

                            <div class="mrb-selected-time-box" id="mrb-selected-time-box">
                                <div class="mrb-selected-time-icon">⏱</div>
                                <div>
                                    <strong id="mrb-selected-time-text">No time selected yet</strong>
                                    <span id="mrb-selected-time-help">Use the availability calendar to choose a free slot.</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mrb-time-actions">
                        <button type="button" id="mrb-open-calendar" class="mrb-open-calendar-btn">
                            <span aria-hidden="true">📅</span>
                            Choose available time
                        </button>

                        <button type="button" id="mrb-clear-time" class="mrb-clear-time-btn" style="display:none;">
                            Clear selected time
                        </button>
                    </div>

                    <div id="mrb-time-conflict-warning" class="mrb-time-conflict-warning" style="display:none;">
                        The selected time overlaps with an existing reservation. Please choose an empty time.
                    </div>
                </section>

                <section id="mrb-section-contact" class="mrb-section">
                    <div class="mrb-section-header">
                        <h3>Contact information</h3>
                        <p>We will use this information to confirm your reservation.</p>
                    </div>

                    <div class="mrb-grid">
                        <div class="mrb-field">
                            <label for="mrb_first_name">First Name <span aria-hidden="true">*</span></label>
                            <input
                                type="text"
                                name="first_name"
                                id="mrb_first_name"
                                placeholder="First name"
                                autocomplete="given-name"
                                required>
                        </div>

                        <div class="mrb-field">
                            <label for="mrb_last_name">Last Name <span aria-hidden="true">*</span></label>
                            <input
                                type="text"
                                name="last_name"
                                id="mrb_last_name"
                                placeholder="Last name"
                                autocomplete="family-name"
                                required>
                        </div>

                        <div class="mrb-field">
                            <label for="mrb_email">Email <span aria-hidden="true">*</span></label>
                            <input
                                type="email"
                                name="email"
                                id="mrb_email"
                                placeholder="name@example.com"
                                autocomplete="email"
                                required>
                        </div>

                        <div class="mrb-field">
                            <label for="mrb_mobile">Mobile <span aria-hidden="true">*</span></label>
                            <input
                                type="tel"
                                name="mobile"
                                id="mrb_mobile"
                                placeholder="Your mobile number"
                                autocomplete="tel"
                                required>
                        </div>
                    </div>
                </section>

                <div class="mrb-submit-area">
                    <button type="submit" class="mrb-submit-btn" id="mrb-submit-btn">
                        <span class="mrb-submit-label">Reserve Room</span>
                        <span class="mrb-submit-loading" style="display:none;">Submitting...</span>
                    </button>

                    <p class="mrb-submit-note">
                        Your reservation will be checked against existing bookings before it is confirmed.
                    </p>
                </div>

            </div>

            <div id="mrb-calendar-modal"
                 class="mrb-calendar-modal"
                 aria-hidden="true">

                <div class="mrb-calendar-modal-backdrop"></div>

                <div class="mrb-calendar-modal-panel"
                     role="dialog"
                     aria-modal="true"
                     aria-labelledby="mrb-calendar-modal-title">

                    <div class="mrb-calendar-modal-header">
                        <div>
                            <h3 class="mrb-calendar-modal-title" id="mrb-calendar-modal-title">
                                Choose reservation time
                            </h3>
                            <p class="mrb-calendar-modal-subtitle">
                                Drag on a free area to select your meeting time. Time precision is 30 minutes.
                            </p>
                        </div>

                        <button type="button"
                                id="mrb-calendar-close"
                                class="mrb-calendar-close-btn"
                                aria-label="Close calendar">
                            ×
                        </button>
                    </div>

                    <div id="mrb-calendar-error-box" class="mrb-calendar-error" style="display:none;"></div>

                    <div class="mrb-calendar-layout">

                        <aside class="mrb-calendar-days-panel">
                            <div class="mrb-calendar-days-heading">Available days</div>
                            <div id="mrb-calendar-days" class="mrb-calendar-days">
                                Loading days...
                            </div>
                        </aside>

                        <div class="mrb-calendar-timeline-panel">

                            <div class="mrb-calendar-current-day">
                                <div>
                                    <strong id="mrb-current-day-title">Select a date</strong>
                                    <span id="mrb-current-day-subtitle"></span>
                                </div>

                                <div id="mrb-current-selection-label" class="mrb-current-selection-label">
                                    Drag to select
                                </div>
                            </div>

                            <div id="mrb-calendar-loading" class="mrb-calendar-loading" style="display:none;">
                                Loading availability...
                            </div>

                            <div id="mrb-google-timeline-scroll" class="mrb-google-timeline-scroll">

                                <div id="mrb-google-timeline" class="mrb-google-timeline">

                                    <div id="mrb-timeline-labels" class="mrb-timeline-labels"></div>

                                    <div id="mrb-timeline-grid" class="mrb-timeline-grid">
                                        <div id="mrb-booked-layer" class="mrb-booked-layer"></div>
                                        <div id="mrb-selection-layer" class="mrb-selection-layer"></div>
                                    </div>

                                </div>

                            </div>

                            <div class="mrb-calendar-help">
                                <span class="mrb-help-item"><span class="mrb-dot mrb-dot-booked"></span> Booked</span>
                                <span class="mrb-help-item"><span class="mrb-dot mrb-dot-selected"></span> Your selection</span>
                                <span class="mrb-help-item"><span class="mrb-dot mrb-dot-free"></span> Free</span>
                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </form>

        <?php
        return ob_get_clean();
    }

    public static function handleSubmit(): void
    {
        if (
            empty($_POST['mrb_nonce']) ||
            !wp_verify_nonce(
                sanitize_text_field(wp_unslash($_POST['mrb_nonce'])),
                'mrb_submit_booking'
            )
        ) {
            wp_die('Invalid nonce.');
        }

        $repository = new ReservationRepository();
        $service    = new ReservationService($repository);

        $result = $service->create(wp_unslash($_POST));

        $redirectUrl = wp_get_referer();

        if (!$redirectUrl) {
            $redirectUrl = home_url();
        }

        if (!$result['success']) {
            $error = $result['errors'][0] ?? 'Invalid data.';

            wp_safe_redirect(add_query_arg([
                'mrb_status' => 'error',
                'mrb_error'  => rawurlencode($error),
            ], $redirectUrl));

            exit;
        }

        if (!empty($result['reservation_id'])) {
            $reservation = $repository->findById((int) $result['reservation_id']);

            if ($reservation) {
                $emailService = new EmailNotificationService();
                $emailService->sendReservationCreatedEmails($reservation);
            }
        }

        wp_safe_redirect(add_query_arg([
            'mrb_status' => 'success',
            'token'      => $result['token'],
        ], $redirectUrl));

        exit;
    }

    public static function getBookedTimesRange(): void
{
    try {
        $nonceIsValid = check_ajax_referer(
            'mrb_get_booked_times_range',
            'nonce',
            false
        );

        if (!$nonceIsValid) {
            wp_send_json_error([
                'message' => 'Security check failed. Please refresh the page and try again.',
            ]);
        }

        $selectedDate = isset($_POST['date'])
            ? sanitize_text_field(wp_unslash($_POST['date']))
            : '';

        if (!$selectedDate || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
            wp_send_json_error([
                'message' => 'Invalid date.',
                'date'    => $selectedDate,
            ]);
        }

        try {
            $selected = new DateTimeImmutable($selectedDate);

            $start = $selected;
            $end   = $selected->add(new DateInterval('P6D'));
        } catch (Exception $e) {
            wp_send_json_error([
                'message' => 'Invalid date.',
                'error'   => $e->getMessage(),
            ]);
        }

        $startDate = $start->format('Y-m-d');
        $endDate   = $end->format('Y-m-d');

        $repository = new ReservationRepository();
        $roomRepo   = new RoomRepository();

        $rows = $repository->getBookedTimesBetweenDates($startDate, $endDate);

        /* NEW: count total rooms */
        $rooms = $roomRepo->all();
        $totalRooms = is_array($rooms) ? count($rooms) : 1;

        if ($totalRooms < 1) {
            $totalRooms = 1;
        }

        $grouped = [];

        for ($i = 0; $i <= 6; $i++) {
            $day  = $selected->modify('+' . $i . ' days');
            $date = $day->format('Y-m-d');

            $grouped[$date] = [
                'date'        => $date,
                'day_label'   => $day->format('D'),
                'month_label' => $day->format('M j'),
                'full_label'  => $day->format('l, F j, Y'),
                'is_selected' => $date === $selectedDate,
                'slots'       => [],
            ];
        }

        foreach ($rows as $row) {
            $date = isset($row['meeting_date']) ? (string) $row['meeting_date'] : '';

            if (!isset($grouped[$date])) {
                continue;
            }

            $startTime = isset($row['start_time']) ? (string) $row['start_time'] : '';
            $endTime   = isset($row['end_time']) ? (string) $row['end_time'] : '';
            $status    = isset($row['status']) ? (string) $row['status'] : '';

            if ($startTime === '' || $endTime === '') {
                continue;
            }

            $grouped[$date]['slots'][] = [
                'start_time' => substr($startTime, 0, 5),
                'end_time'   => substr($endTime, 0, 5),
                'status'     => $status,
            ];
        }

        wp_send_json_success([
            'selected_date' => $selectedDate,
            'start_date'    => $startDate,
            'end_date'      => $endDate,
            'days'          => array_values($grouped),
            'total_rooms'   => $totalRooms
        ]);

    } catch (\Throwable $e) {
        wp_send_json_error([
            'message' => 'PHP error: ' . $e->getMessage(),
            'file'    => $e->getFile(),
            'line'    => $e->getLine(),
        ]);
    }
}


    private static function getInlineScript(): string
    {
        return <<<'JS'
(function ($) {
    'use strict';

    var bookedSlotsByDate = {};
var daysByDate = {};
var currentSelectedDate = '';
var totalRooms = 1;


    var slotMinutes = 30;
    var slotHeight = 24;
    var totalMinutes = 24 * 60;
    var timelineHeight = (totalMinutes / slotMinutes) * slotHeight;

    var isDragging = false;
    var dragStartMinutes = null;
    var dragCurrentMinutes = null;

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function showFormFeedback(message, type) {
        var $box = $('#mrb-form-feedback');
        type = type || 'error';

        $box
            .removeClass('is-error is-success is-info')
            .addClass('is-' + type)
            .html(escapeHtml(message))
            .show();

        if ($box.length) {
            $('html, body').animate({
                scrollTop: Math.max(0, $box.offset().top - 120)
            }, 250);
        }
    }

    function hideFormFeedback() {
        $('#mrb-form-feedback').hide().empty();
    }

    function timeToMinutes(time) {
        if (!time || time.indexOf(':') === -1) {
            return null;
        }

        var parts = time.split(':');
        var hours = parseInt(parts[0], 10);
        var minutes = parseInt(parts[1], 10);

        if (isNaN(hours) || isNaN(minutes)) {
            return null;
        }

        return hours * 60 + minutes;
    }

    function minutesToInputTime(minutes) {
        minutes = Math.max(0, Math.min(totalMinutes, minutes));

        if (minutes >= totalMinutes) {
            return '23:59';
        }

        var hours = Math.floor(minutes / 60);
        var mins = minutes % 60;

        return String(hours).padStart(2, '0') + ':' + String(mins).padStart(2, '0');
    }

    function minutesToLabel(minutes) {
        minutes = Math.max(0, Math.min(totalMinutes, minutes));

        if (minutes === totalMinutes) {
            return '24:00';
        }

        var hours = Math.floor(minutes / 60);
        var mins = minutes % 60;

        return String(hours).padStart(2, '0') + ':' + String(mins).padStart(2, '0');
    }

    function roundToSlot(minutes) {
        return Math.round(minutes / slotMinutes) * slotMinutes;
    }

    function floorToSlot(minutes) {
        return Math.floor(minutes / slotMinutes) * slotMinutes;
    }

    function ceilToSlot(minutes) {
        return Math.ceil(minutes / slotMinutes) * slotMinutes;
    }

    function clampMinutes(minutes) {
        return Math.max(0, Math.min(totalMinutes, minutes));
    }

    function getCurrentBookedSlots() {
        var date = $('#mrb_meeting_date').val();

        if (!date || !bookedSlotsByDate[date]) {
            return [];
        }

        return bookedSlotsByDate[date];
    }

    function hasConflict(startTime, endTime) {
    var bookedSlots = getCurrentBookedSlots();

    var selectedStart = timeToMinutes(startTime);
    var selectedEnd = timeToMinutes(endTime);

    if (selectedStart === null || selectedEnd === null) {
        return false;
    }

    var overlapCount = 0;

    for (var i = 0; i < bookedSlots.length; i++) {
        var bookedStart = timeToMinutes(bookedSlots[i].start_time);
        var bookedEnd = timeToMinutes(bookedSlots[i].end_time);

        if (bookedStart === null || bookedEnd === null) {
            continue;
        }

        if (selectedStart < bookedEnd && selectedEnd > bookedStart) {
            overlapCount++;
        }
    }

    return overlapCount >= totalRooms;
}


    function hasConflictByMinutes(startMinutes, endMinutes) {
    var bookedSlots = bookedSlotsByDate[currentSelectedDate] || [];

    if (endMinutes <= startMinutes) {
        return true;
    }

    var overlapCount = 0;

    for (var i = 0; i < bookedSlots.length; i++) {
        var bookedStart = timeToMinutes(bookedSlots[i].start_time);
        var bookedEnd = timeToMinutes(bookedSlots[i].end_time);

        if (bookedStart === null || bookedEnd === null) {
            continue;
        }

        if (startMinutes < bookedEnd && endMinutes > bookedStart) {
            overlapCount++;
        }
    }

    return overlapCount >= totalRooms;
}


    function updateSelectedTimeDisplay() {
        var startTime = $('#mrb_start_time').val();
        var endTime = $('#mrb_end_time').val();

        if (!startTime || !endTime) {
            $('#mrb-selected-time-text').text('No time selected yet');
            $('#mrb-selected-time-help').text('Use the availability calendar to choose a free slot.');
            $('#mrb-selected-time-box').removeClass('has-time has-error');
            $('#mrb-clear-time').hide();
            return;
        }

        $('#mrb-selected-time-text').text(startTime + ' - ' + endTime);
        $('#mrb-selected-time-help').text('Selected from the availability calendar.');
        $('#mrb-selected-time-box').addClass('has-time').removeClass('has-error');
        $('#mrb-clear-time').show();
    }

    function updateConflictWarning() {
        var startTime = $('#mrb_start_time').val();
        var endTime = $('#mrb_end_time').val();

        if (!startTime || !endTime) {
            $('#mrb-time-conflict-warning').hide();
            $('#mrb-selected-time-box').removeClass('has-error');
            return false;
        }

        if (hasConflict(startTime, endTime)) {
            $('#mrb-time-conflict-warning').show();
            $('#mrb-selected-time-box').addClass('has-error');
            return true;
        }

        $('#mrb-time-conflict-warning').hide();
        $('#mrb-selected-time-box').removeClass('has-error');
        return false;
    }

    function clearSelectedTime() {
        $('#mrb_start_time').val('');
        $('#mrb_end_time').val('');
        updateSelectedTimeDisplay();
        updateConflictWarning();
    }

    function openCalendarModal() {
        $('#mrb-calendar-modal').addClass('is-open').attr('aria-hidden', 'false');
        $('body').addClass('mrb-calendar-open');
        hideFormFeedback();

        setTimeout(function () {
            $('#mrb-calendar-close').trigger('focus');
        }, 50);
    }

    function closeCalendarModal() {
        $('#mrb-calendar-modal').removeClass('is-open').attr('aria-hidden', 'true');
        $('body').removeClass('mrb-calendar-open');
        clearSelectionLayer();

        setTimeout(function () {
            $('#mrb-open-calendar').trigger('focus');
        }, 50);
    }

    function showCalendarError(message) {
        $('#mrb-calendar-error-box')
            .html(escapeHtml(message))
            .show();
    }

    function hideCalendarError() {
        $('#mrb-calendar-error-box').hide().empty();
    }

    function getAjaxErrorMessage(response) {
        if (response && response.data && response.data.message) {
            return response.data.message;
        }

        return 'Could not load availability calendar.';
    }

    function renderDayButtons(days) {
        var html = '';

        days.forEach(function (day) {
            var selectedClass = day.date === currentSelectedDate ? ' is-selected' : '';
            var count = day.slots ? day.slots.length : 0;

            html += '<button type="button" class="mrb-calendar-day-btn' + selectedClass + '" data-date="' + escapeHtml(day.date) + '">';
            html += '<span class="mrb-calendar-day-name">' + escapeHtml(day.day_label) + '</span>';
            html += '<span class="mrb-calendar-day-date">' + escapeHtml(day.month_label) + '</span>';

            if (count > 0) {
                html += '<span class="mrb-calendar-day-count">' + count + ' booking' + (count > 1 ? 's' : '') + '</span>';
            } else {
                html += '<span class="mrb-calendar-day-count is-free">Fully available</span>';
            }

            html += '</button>';
        });

        $('#mrb-calendar-days').html(html);
    }

    function buildTimelineBase() {
        var labelsHtml = '';
        var gridHtml = '';

        for (var hour = 0; hour <= 24; hour++) {
            var top = (hour * 60 / slotMinutes) * slotHeight;

            labelsHtml += '<div class="mrb-hour-label" style="top:' + top + 'px;">';
            labelsHtml += hour === 24 ? '24:00' : String(hour).padStart(2, '0') + ':00';
            labelsHtml += '</div>';

            if (hour < 24) {
                gridHtml += '<div class="mrb-hour-line" style="top:' + top + 'px;"></div>';
            }
        }

        for (var half = 30; half < totalMinutes; half += 60) {
            var halfTop = (half / slotMinutes) * slotHeight;
            gridHtml += '<div class="mrb-half-hour-line" style="top:' + halfTop + 'px;"></div>';
        }

        $('#mrb-google-timeline').css('height', timelineHeight + 'px');
        $('#mrb-timeline-labels').html(labelsHtml).css('height', timelineHeight + 'px');
        $('#mrb-timeline-grid').css('height', timelineHeight + 'px');

        $('#mrb-timeline-grid .mrb-hour-line, #mrb-timeline-grid .mrb-half-hour-line').remove();
        $('#mrb-timeline-grid').prepend(gridHtml);
    }

    function renderBookedBlocks(date) {
        var slots = bookedSlotsByDate[date] || [];
        var html = '';

        slots.forEach(function (slot) {
            var start = timeToMinutes(slot.start_time);
            var end = timeToMinutes(slot.end_time);

            if (start === null || end === null || end <= start) {
                return;
            }

            start = clampMinutes(start);
            end = clampMinutes(end);

            var top = (start / slotMinutes) * slotHeight;
            var height = Math.max(slotHeight, ((end - start) / slotMinutes) * slotHeight);

            html += '<div class="mrb-booked-block" style="top:' + top + 'px;height:' + height + 'px;">';
            html += '<strong>Booked</strong>';
            html += '<span>' + escapeHtml(slot.start_time) + ' - ' + escapeHtml(slot.end_time) + '</span>';
            html += '</div>';
        });

        $('#mrb-booked-layer').html(html);
    }

    function renderCurrentDay(date) {
        currentSelectedDate = date;

        var day = daysByDate[date];

        if (!day) {
            return;
        }

        $('#mrb_meeting_date').val(date);

        $('.mrb-calendar-day-btn').removeClass('is-selected');
        $('.mrb-calendar-day-btn[data-date="' + date + '"]').addClass('is-selected');

        $('#mrb-current-day-title').text(day.full_label || date);
        $('#mrb-current-day-subtitle').text(' — drag on an empty area to choose your time');
        $('#mrb-current-selection-label').text('Drag to select');

        buildTimelineBase();
        renderBookedBlocks(date);
        clearSelectionLayer();

        setTimeout(function () {
            $('#mrb-google-timeline-scroll').scrollTop(7 * 60 / slotMinutes * slotHeight);
        }, 50);

        updateConflictWarning();
    }

    function renderCalendar(responseData) {
        var days = responseData.days || [];

        bookedSlotsByDate = {};
        daysByDate = {};

        totalRooms = parseInt(responseData.total_rooms || 1, 10);

        if (!days.length) {
            showCalendarError('No calendar data found.');
            return;
        }

        days.forEach(function (day) {
            bookedSlotsByDate[day.date] = day.slots || [];
            daysByDate[day.date] = day;
        });

        currentSelectedDate = responseData.selected_date || days[0].date;

        renderDayButtons(days);
        renderCurrentDay(currentSelectedDate);
    }

    function fetchCalendar(date) {
        if (!date) {
            showFormFeedback('Please select a date first.', 'error');
            $('#mrb_meeting_date').trigger('focus');
            return;
        }

        hideCalendarError();
        openCalendarModal();

        $('#mrb-calendar-loading').show();
        $('#mrb-calendar-days').html('Loading days...');
        $('#mrb-booked-layer').empty();
        $('#mrb-selection-layer').empty();

        if (!window.MRBBookingForm || !window.MRBBookingForm.ajaxUrl || !window.MRBBookingForm.nonce) {
            $('#mrb-calendar-loading').hide();
            showCalendarError('AJAX configuration is missing.');
            return;
        }

        $.ajax({
            url: window.MRBBookingForm.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'mrb_get_booked_times_range',
                nonce: window.MRBBookingForm.nonce,
                date: date
            }
        }).done(function (response) {
            $('#mrb-calendar-loading').hide();

            if (!response || !response.success) {
                bookedSlotsByDate = {};
                showCalendarError(getAjaxErrorMessage(response));
                updateConflictWarning();
                return;
            }

            renderCalendar(response.data);
        }).fail(function (xhr) {
            $('#mrb-calendar-loading').hide();

            var message = 'Could not load availability calendar.';

            if (xhr && xhr.responseText) {
                message += ' Server response: ' + xhr.responseText.substring(0, 300);
            }

            bookedSlotsByDate = {};
            showCalendarError(message);
            updateConflictWarning();
        });
    }

    function eventPageY(event) {
        var original = event.originalEvent || event;

        if (original.touches && original.touches.length) {
            return original.touches[0].pageY;
        }

        if (original.changedTouches && original.changedTouches.length) {
            return original.changedTouches[0].pageY;
        }

        return event.pageY;
    }

    function minutesFromEvent(event) {
        var $grid = $('#mrb-timeline-grid');
        var offset = $grid.offset();
        var pageY = eventPageY(event);
        var y = pageY - offset.top;

        y = Math.max(0, Math.min(timelineHeight, y));

        var rawMinutes = (y / slotHeight) * slotMinutes;

        return clampMinutes(roundToSlot(rawMinutes));
    }

    function clearSelectionLayer() {
        $('#mrb-selection-layer').empty();
        dragStartMinutes = null;
        dragCurrentMinutes = null;
        isDragging = false;
    }

    function drawSelection(startMinutes, endMinutes, isInvalid) {
        var topMinutes = Math.min(startMinutes, endMinutes);
        var bottomMinutes = Math.max(startMinutes, endMinutes);

        topMinutes = floorToSlot(topMinutes);
        bottomMinutes = ceilToSlot(bottomMinutes);

        if (bottomMinutes <= topMinutes) {
            bottomMinutes = topMinutes + slotMinutes;
        }

        bottomMinutes = clampMinutes(bottomMinutes);

        var top = (topMinutes / slotMinutes) * slotHeight;
        var height = Math.max(slotHeight, ((bottomMinutes - topMinutes) / slotMinutes) * slotHeight);

        var invalidClass = isInvalid ? ' is-invalid' : '';

        var html = '<div class="mrb-selection-block' + invalidClass + '" style="top:' + top + 'px;height:' + height + 'px;">';
        html += '<strong>' + minutesToLabel(topMinutes) + ' - ' + minutesToLabel(bottomMinutes) + '</strong>';
        html += isInvalid ? '<span>Conflicts with booked time</span>' : '<span>Release to choose</span>';
        html += '</div>';

        $('#mrb-selection-layer').html(html);

        $('#mrb-current-selection-label').text(minutesToLabel(topMinutes) + ' - ' + minutesToLabel(bottomMinutes));
    }

    function commitSelection() {
        if (dragStartMinutes === null || dragCurrentMinutes === null) {
            clearSelectionLayer();
            return;
        }

        var startMinutes = Math.min(dragStartMinutes, dragCurrentMinutes);
        var endMinutes = Math.max(dragStartMinutes, dragCurrentMinutes);

        startMinutes = floorToSlot(startMinutes);
        endMinutes = ceilToSlot(endMinutes);

        if (endMinutes <= startMinutes) {
            endMinutes = startMinutes + slotMinutes;
        }

        startMinutes = clampMinutes(startMinutes);
        endMinutes = clampMinutes(endMinutes);

        if (endMinutes <= startMinutes) {
            clearSelectionLayer();
            return;
        }

        if (hasConflictByMinutes(startMinutes, endMinutes)) {
            drawSelection(startMinutes, endMinutes, true);
            showCalendarError('This selected time overlaps with an existing reservation. Please select an empty time.');
            return;
        }

        $('#mrb_start_time').val(minutesToInputTime(startMinutes));
        $('#mrb_end_time').val(minutesToInputTime(endMinutes));

        updateSelectedTimeDisplay();
        updateConflictWarning();

        closeCalendarModal();
    }

    function validateFormBeforeSubmit() {
        hideFormFeedback();

        var requiredFields = [
            ['#mrb_meeting_title', 'Please enter a meeting title.'],
            ['#mrb_meeting_date', 'Please select a meeting date.'],
            ['#mrb_first_name', 'Please enter your first name.'],
            ['#mrb_last_name', 'Please enter your last name.'],
            ['#mrb_email', 'Please enter your email address.'],
            ['#mrb_mobile', 'Please enter your mobile number.']
        ];

        for (var i = 0; i < requiredFields.length; i++) {
            var selector = requiredFields[i][0];
            var message = requiredFields[i][1];

            if (!$(selector).val()) {
                showFormFeedback(message, 'error');
                $(selector).trigger('focus');
                return false;
            }
        }

        if (!$('#mrb_start_time').val() || !$('#mrb_end_time').val()) {
            showFormFeedback('Please choose an available time from the calendar.', 'error');
            $('#mrb-open-calendar').trigger('focus');
            $('#mrb-selected-time-box').addClass('has-error');
            return false;
        }

        if (updateConflictWarning()) {
            showFormFeedback('The selected time overlaps with an existing reservation. Please choose another time.', 'error');
            $('#mrb-open-calendar').trigger('focus');
            return false;
        }

        return true;
    }

    $(document).on('click', '#mrb-open-calendar', function () {
        var date = $('#mrb_meeting_date').val();

        if (!date) {
            showFormFeedback('Please select a date first.', 'error');
            $('#mrb_meeting_date').trigger('focus');
            return;
        }

        fetchCalendar(date);
    });

    $(document).on('change', '#mrb_meeting_date', function () {
        clearSelectedTime();
        bookedSlotsByDate = {};
        daysByDate = {};
        currentSelectedDate = '';
    });

    $(document).on('click', '#mrb-clear-time', function () {
        clearSelectedTime();
    });

    $(document).on('click', '.mrb-calendar-day-btn', function () {
        var date = $(this).data('date');

        if (!date) {
            return;
        }

        clearSelectedTime();
        renderCurrentDay(date);
    });

    $(document).on('mousedown touchstart', '#mrb-timeline-grid', function (event) {
        if (!currentSelectedDate) {
            return;
        }

        if ($(event.target).closest('.mrb-booked-block').length) {
            return;
        }

        event.preventDefault();

        hideCalendarError();

        isDragging = true;
        dragStartMinutes = minutesFromEvent(event);
        dragCurrentMinutes = dragStartMinutes + slotMinutes;

        drawSelection(dragStartMinutes, dragCurrentMinutes, false);
    });

    $(document).on('mousemove touchmove', function (event) {
        if (!isDragging) {
            return;
        }

        event.preventDefault();

        dragCurrentMinutes = minutesFromEvent(event);

        if (dragCurrentMinutes === dragStartMinutes) {
            dragCurrentMinutes = dragStartMinutes + slotMinutes;
        }

        var start = Math.min(dragStartMinutes, dragCurrentMinutes);
        var end = Math.max(dragStartMinutes, dragCurrentMinutes);

        start = floorToSlot(start);
        end = ceilToSlot(end);

        if (end <= start) {
            end = start + slotMinutes;
        }

        var invalid = hasConflictByMinutes(start, end);

        drawSelection(start, end, invalid);
    });

    $(document).on('mouseup touchend', function () {
        if (!isDragging) {
            return;
        }

        isDragging = false;
        commitSelection();
    });

    $(document).on('click', '#mrb-calendar-close, .mrb-calendar-modal-backdrop', function () {
        closeCalendarModal();
    });

    $(document).on('keydown', function (event) {
        if (event.key === 'Escape') {
            closeCalendarModal();
        }
    });

    $(document).on('submit', '#mrb-booking-form', function (event) {
        if (!validateFormBeforeSubmit()) {
            event.preventDefault();
            return false;
        }

        $('#mrb-submit-btn').prop('disabled', true).addClass('is-loading');
        $('#mrb-submit-btn .mrb-submit-label').hide();
        $('#mrb-submit-btn .mrb-submit-loading').show();

        return true;
    });

    $(document).on('click', '.mrb-copy-link-btn', function () {
        var target = $(this).data('copy-target');
        var text = $(target).text();

        if (!text) {
            return;
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function () {
                $('#mrb-copy-feedback').fadeIn(150).delay(1500).fadeOut(200);
            });
        } else {
            var temp = $('<input>');
            $('body').append(temp);
            temp.val(text).select();
            document.execCommand('copy');
            temp.remove();
            $('#mrb-copy-feedback').fadeIn(150).delay(1500).fadeOut(200);
        }
    });

        function setActiveStep(step) {
        $('.mrb-step').removeClass('is-active');
        $('.mrb-step[data-target="' + step + '"]').addClass('is-active');
    }

    function scrollToBookingSection(step) {
        var sectionMap = {
            meeting: '#mrb-section-meeting',
            time: '#mrb-section-time',
            contact: '#mrb-section-contact'
        };

        var sectionSelector = sectionMap[step];

        if (!sectionSelector || !$(sectionSelector).length) {
            return;
        }

        setActiveStep(step);

        $('html, body').animate({
            scrollTop: Math.max(0, $(sectionSelector).offset().top - 120)
        }, 300);
    }

    function updateActiveStepOnScroll() {
        var scrollTop = $(window).scrollTop();
        var activeStep = 'meeting';

        if ($('#mrb-section-time').length && scrollTop >= $('#mrb-section-time').offset().top - 180) {
            activeStep = 'time';
        }

        if ($('#mrb-section-contact').length && scrollTop >= $('#mrb-section-contact').offset().top - 180) {
            activeStep = 'contact';
        }

        setActiveStep(activeStep);
    }

    $(document).on('click', '.mrb-step', function () {
        var target = $(this).data('target');

        if (!target) {
            return;
        }

        scrollToBookingSection(target);
    });

    // $(window).on('scroll', function () {
    //     updateActiveStepOnScroll();
    // });


    updateSelectedTimeDisplay();

})(jQuery);
JS;
    }

//     private static function getInlineStyle(): string
//     {
//         return <<<'CSS'

// CSS;
//     }
}
