<?php

namespace MRB\Core;

if (!defined("ABSPATH")) {
    exit();
}

/**
 * Registers and conditionally enqueues plugin CSS and JavaScript assets.
 *
 * Scripts/styles are registered here so they can be enqueued on-demand
 * by individual controllers (e.g. BookingController enqueues mrb-booking-form).
 */
class Assets
{
    public function register(): void
    {
        add_action("wp_enqueue_scripts", [$this, "registerFrontendAssets"]);
        add_action("admin_enqueue_scripts", [$this, "registerAdminAssets"]);
    }

    public function registerFrontendAssets(): void
    {
        wp_register_style(
            "mrb-booking-form",
            MRB_PLUGIN_URL . "assets/css/booking-form.css",
            [],
            $this->version("assets/css/booking-form.css"),
        );

        wp_register_style(
            "mrb-manage-reservation",
            MRB_PLUGIN_URL . "assets/css/mrb-manage-reservation.css",
            [],
            $this->version("assets/css/mrb-manage-reservation.css"),
        );

        // Booking-form interactive script.
        // Depends on jQuery (already bundled with WordPress).
        wp_register_script(
            "mrb-booking-form",
            MRB_PLUGIN_URL . "assets/js/booking-form.js",
            ["jquery"],
            $this->version("assets/js/booking-form.js"),
            true,
        );

        // Inject the AJAX configuration object that booking-form.js reads as
        // window.MRBBookingForm.ajaxUrl and window.MRBBookingForm.nonce.
        // This is added once here so it is always present when the script loads.
        wp_add_inline_script(
            "mrb-booking-form",
            "window.MRBBookingForm = " .
                wp_json_encode([
                    "ajaxUrl" => admin_url("admin-ajax.php"),
                    "nonce" => wp_create_nonce("mrb_get_booked_times_range"),
                ]) .
                ";",
            "before",
        );
    }

    public function registerAdminAssets(): void
    {
        wp_register_style(
            "mrb-admin-dashboard",
            MRB_PLUGIN_URL . "assets/css/mrb-admin-dashboard.css",
            [],
            $this->version("assets/css/mrb-admin-dashboard.css"),
        );

        wp_register_style(
            "mrb-admin-calendar",
            MRB_PLUGIN_URL . "assets/css/mrb-admin-calendar.css",
            ["mrb-admin-dashboard"],
            $this->version("assets/css/mrb-admin-calendar.css"),
        );
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Return a cache-busting version string for an asset file.
     *
     * Uses the file modification time during development so browsers pick up
     * CSS/JS changes immediately. Falls back to MRB_VERSION if the file is absent.
     */
    private function version(string $relativePath): string
    {
        $file = MRB_PLUGIN_DIR . ltrim($relativePath, "/");

        return file_exists($file) ? (string) filemtime($file) : MRB_VERSION;
    }
}
