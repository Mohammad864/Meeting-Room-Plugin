<?php

namespace MRB\Core;

use MRB\Controllers\Admin\CalendarController;
use MRB\Controllers\Admin\ReservationActionController;
use MRB\Controllers\Admin\ReservationController;
use MRB\Controllers\Admin\SettingsController;
use MRB\Controllers\Front\BookingController;
use MRB\Controllers\Front\GuestReservationController;
use MRB\Controllers\Front\ManageReservationController;
use MRB\Support\FrontendNotice;

if (!defined("ABSPATH")) {
    exit();
}

/**
 * Plugin bootstrap.
 *
 * The only responsibility of this class is registering WordPress hooks.
 * All business logic lives in Services; all request handling in Controllers;
 * all output in view templates under views/.
 */
class Plugin
{
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function boot(): void
{
    $assets = new Assets();
    $assets->register();

    $this->registerAdminHooks();
    $this->registerFrontHooks();
    $this->registerWordPressHooks();
}


    // ── Admin hooks ───────────────────────────────────────────────────────────

    private function registerAdminHooks(): void
    {
        /** @var ReservationActionController $reservationActions */
        $reservationActions = $this->container->make(
            ReservationActionController::class,
        );

        add_action("admin_post_mrb_admin_update_reservation", [
            $reservationActions,
            "handleUpdate",
        ]);
        add_action("admin_post_mrb_change_status", [
            $reservationActions,
            "handleStatusChange",
        ]);

        /** @var SettingsController $settings */
        $settings = $this->container->make(SettingsController::class);

        add_action("admin_post_mrb_save_settings", [$settings, "handleSave"]);

        /** @var CalendarController $calendar */
        $calendar = $this->container->make(CalendarController::class);

        add_action("admin_enqueue_scripts", [$calendar, "enqueueAssets"]);
        add_action("wp_ajax_mrb_get_calendar_events", [
            $calendar,
            "handleGetEvents",
        ]);

        add_action("admin_menu", [$this, "registerAdminPages"]);
    }

    // ── Front hooks ───────────────────────────────────────────────────────────

    private function registerFrontHooks(): void
    {
        /** @var GuestReservationController $guest */
        $guest = $this->container->make(GuestReservationController::class);

        add_action("admin_post_mrb_guest_update", [$guest, "handleUpdate"]);
        add_action("admin_post_nopriv_mrb_guest_update", [
            $guest,
            "handleUpdate",
        ]);
        add_action("admin_post_mrb_guest_cancel", [$guest, "handleCancel"]);
        add_action("admin_post_nopriv_mrb_guest_cancel", [
            $guest,
            "handleCancel",
        ]);

        /** @var BookingController $booking */
        $booking = $this->container->make(BookingController::class);

        add_action("admin_post_mrb_submit_booking", [$booking, "handleSubmit"]);
        add_action("admin_post_nopriv_mrb_submit_booking", [
            $booking,
            "handleSubmit",
        ]);
        add_action("wp_ajax_mrb_get_booked_times_range", [
            $booking,
            "handleGetBookedTimesRange",
        ]);
        add_action("wp_ajax_nopriv_mrb_get_booked_times_range", [
            $booking,
            "handleGetBookedTimesRange",
        ]);
    }

    // ── WordPress core hooks ──────────────────────────────────────────────────

    private function registerWordPressHooks(): void
    {
        add_action("init", [$this, "registerShortcodes"]);
        add_action("init", [$this, "registerReservationEndpoint"]);
        add_filter("query_vars", [$this, "addQueryVars"]);
        add_action("template_redirect", [$this, "handleReservationRoute"]);

        // Render frontend notices on any page that carries ?updated=1 / ?cancelled=1 / ?error=…
        add_action(
            "wp_body_open",
            [FrontendNotice::class, "renderFromRequest"],
            5,
        );
        add_action(
            "wp_footer",
            [FrontendNotice::class, "renderFromRequest"],
            5,
        );
    }

    // ── Public callbacks ──────────────────────────────────────────────────────

    public function registerShortcodes(): void
    {
        /** @var BookingController $booking */
        $booking = $this->container->make(BookingController::class);

        add_shortcode("mrb_booking_form", [$booking, "renderShortcode"]);
    }

    public function registerAdminPages(): void
    {
        /** @var ReservationController $reservations */
        $reservations = $this->container->make(ReservationController::class);

        add_menu_page(
            __("Meeting Bookings", "meeting-room-booking"),
            __("Meeting Bookings", "meeting-room-booking"),
            "manage_options",
            "mrb-reservations",
            [$reservations, "dispatch"],
            "dashicons-calendar-alt",
            26,
        );

        /** @var CalendarController $calendar */
        $calendar = $this->container->make(CalendarController::class);

        add_submenu_page(
            "mrb-reservations",
            __("Calendar View", "meeting-room-booking"),
            __("Calendar View", "meeting-room-booking"),
            "manage_options",
            "mrb-calendar",
            [$calendar, "show"],
        );

        /** @var SettingsController $settings */
        $settings = $this->container->make(SettingsController::class);
        $settings->register();
    }

    public function registerReservationEndpoint(): void
    {
        add_rewrite_rule(
            "^reservation/([a-zA-Z0-9]+)/?",
            'index.php?mrb_token=$matches[1]',
            "top",
        );
    }

    public function addQueryVars(array $vars): array
    {
        $vars[] = "mrb_token";
        $vars[] = "error";
        $vars[] = "updated";
        $vars[] = "cancelled";

        return $vars;
    }

    public function handleReservationRoute(): void
    {
        $token = get_query_var("mrb_token");

        if (!$token) {
            return;
        }

        /** @var ManageReservationController $manage */
        $manage = $this->container->make(ManageReservationController::class);
        $manage->show(sanitize_text_field((string) $token));
    }
}
