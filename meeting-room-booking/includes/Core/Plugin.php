<?php

namespace MRB\Core;

use MRB\Front\BookingFormShortcode;
use MRB\Admin\AdminPage;
use MRB\Admin\SettingsPage;
use MRB\Front\ManageReservationHandler;
use MRB\Database\ReservationRepository;
use MRB\Services\ReservationService;

if (!defined('ABSPATH')) {
    exit;
}

class Plugin
{
    public function boot(): void
    {
        if (class_exists(Assets::class)) {
            (new Assets())->register();
        }

        /*
        |--------------------------------------------------------------------------
        | Admin actions
        |--------------------------------------------------------------------------
        */

        add_action(
            'admin_post_mrb_admin_update_reservation',
            [AdminPage::class, 'handleAdminUpdate']
        );

        add_action(
            'admin_post_mrb_change_status',
            [AdminPage::class, 'handleStatusChange']
        );

        // SETTINGS SAVE HANDLER (Fix for blank page)
        add_action(
            'admin_post_mrb_save_settings',
            [SettingsPage::class, 'handleSave']
        );

        /*
        |--------------------------------------------------------------------------
        | Front booking submission
        |--------------------------------------------------------------------------
        */

        add_action(
            'admin_post_mrb_submit_booking',
            [BookingFormShortcode::class, 'handleSubmit']
        );

        add_action(
            'admin_post_nopriv_mrb_submit_booking',
            [BookingFormShortcode::class, 'handleSubmit']
        );

        /*
        |--------------------------------------------------------------------------
        | Guest actions
        |--------------------------------------------------------------------------
        */

        add_action(
            'admin_post_nopriv_mrb_guest_cancel',
            [$this, 'handleGuestCancel']
        );

        add_action(
            'admin_post_mrb_guest_cancel',
            [$this, 'handleGuestCancel']
        );

        add_action(
            'admin_post_nopriv_mrb_guest_update',
            [$this, 'handleGuestUpdate']
        );

        add_action(
            'admin_post_mrb_guest_update',
            [$this, 'handleGuestUpdate']
        );

        /*
        |--------------------------------------------------------------------------
        | WordPress hooks
        |--------------------------------------------------------------------------
        */

        add_action('init', [$this, 'registerShortcodes']);
        add_action('admin_menu', [$this, 'registerAdminPages']);

        add_action('init', [$this, 'registerReservationEndpoint']);
        add_filter('query_vars', [$this, 'addQueryVars']);
        add_action('template_redirect', [$this, 'handleReservationRoute']);
    }

    public function registerShortcodes(): void
    {
        add_shortcode('mrb_booking_form', [BookingFormShortcode::class, 'render']);
    }

    public function registerAdminPages(): void
    {
        add_menu_page(
            'Meeting Bookings',
            'Meeting Bookings',
            'manage_options',
            'mrb-reservations',
            [AdminPage::class, 'render'],
            'dashicons-calendar-alt',
            26
        );

        $settingsPage = new SettingsPage();
        $settingsPage->register();
    }

    public function registerReservationEndpoint(): void
    {
        add_rewrite_rule(
            '^reservation/([a-zA-Z0-9]+)/?$',
            'index.php?mrb_token=$matches[1]',
            'top'
        );
    }

    public function addQueryVars(array $vars): array
    {
        $vars[] = 'mrb_token';
        return $vars;
    }

    public function handleReservationRoute(): void
    {
        $token = get_query_var('mrb_token');

        if (!$token) {
            return;
        }

        $token = sanitize_text_field($token);

        $repository = $this->makeRepository();
        $reservation = $repository->findByToken($token);

        if (!$reservation) {
            wp_die('Reservation not found.');
        }

        $this->renderReservationManagementPage($reservation, $token);
        exit;
    }

    public function handleGuestCancel(): void
    {
        $handler = $this->makeReservationHandler();
        $handler->handleCancel();
    }

    public function handleGuestUpdate(): void
    {
        if (
            !isset($_POST['mrb_nonce']) ||
            !wp_verify_nonce($_POST['mrb_nonce'], 'mrb_guest_update_' . ($_POST['token'] ?? ''))
        ) {
            wp_die('Security check failed.');
        }

        $handler = $this->makeReservationHandler();
        $handler->handleUpdate();
    }

    private function makeRepository(): ReservationRepository
    {
        return new ReservationRepository();
    }

    private function makeReservationService(): ReservationService
    {
        return new ReservationService($this->makeRepository());
    }

    private function makeReservationHandler(): ManageReservationHandler
    {
        return new ManageReservationHandler($this->makeReservationService());
    }

    private function renderReservationManagementPage(array $reservation, string $token): void
    {
        $status = $reservation['status'] ?? '';

        get_header();
        ?>

        <main style="max-width:760px;margin:50px auto;padding:24px;font-family:Arial,sans-serif;">
            <h1>Manage Your Reservation</h1>

            <div style="background:#fff;border:1px solid #ddd;padding:20px;border-radius:8px;margin-bottom:20px;">
                <p><strong>Name:</strong> <?php echo esc_html(($reservation['first_name'] ?? '') . ' ' . ($reservation['last_name'] ?? '')); ?></p>
                <p><strong>Email:</strong> <?php echo esc_html($reservation['email'] ?? ''); ?></p>
                <p><strong>Mobile:</strong> <?php echo esc_html($reservation['mobile'] ?? ''); ?></p>
                <p><strong>Meeting Title:</strong> <?php echo esc_html($reservation['meeting_title'] ?? ''); ?></p>
                <p><strong>Date:</strong> <?php echo esc_html($reservation['meeting_date'] ?? ''); ?></p>
                <p><strong>Time:</strong> <?php echo esc_html(($reservation['start_time'] ?? '') . ' - ' . ($reservation['end_time'] ?? '')); ?></p>
                <p><strong>Status:</strong> <?php echo esc_html($status); ?></p>
            </div>

            <?php if ($status !== 'cancelled' && $status !== 'rejected') : ?>

                <button onclick="document.getElementById('edit-form').style.display='block'"
                style="background:#2271b1;color:#fff;border:none;padding:10px 16px;border-radius:4px;cursor:pointer;margin-bottom:20px;">
                    Edit Reservation
                </button>

                <div id="edit-form" style="display:none;background:#f6f6f6;padding:20px;border:1px solid #ddd;border-radius:8px;">
                    <h3>Edit Reservation</h3>

                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">

                        <input type="hidden" name="action" value="mrb_guest_update">
                        <input type="hidden" name="token" value="<?php echo esc_attr($token); ?>">

                        <?php wp_nonce_field('mrb_guest_update_' . $token, 'mrb_nonce'); ?>

                        <p><label>First Name:</label><br>
                        <input type="text" name="first_name" value="<?php echo esc_attr($reservation['first_name'] ?? ''); ?>" required></p>

                        <p><label>Last Name:</label><br>
                        <input type="text" name="last_name" value="<?php echo esc_attr($reservation['last_name'] ?? ''); ?>" required></p>

                        <p><label>Email:</label><br>
                        <input type="email" name="email" value="<?php echo esc_attr($reservation['email'] ?? ''); ?>" required></p>

                        <p><label>Mobile:</label><br>
                        <input type="text" name="mobile" value="<?php echo esc_attr($reservation['mobile'] ?? ''); ?>" required></p>

                        <p><label>Meeting Title:</label><br>
                        <input type="text" name="meeting_title" value="<?php echo esc_attr($reservation['meeting_title'] ?? ''); ?>" required></p>

                        <p><label>Date:</label><br>
                        <input type="date" name="meeting_date" value="<?php echo esc_attr($reservation['meeting_date'] ?? ''); ?>" required></p>

                        <p><label>Start Time:</label><br>
                        <input type="time" name="start_time" value="<?php echo esc_attr($reservation['start_time'] ?? ''); ?>" required></p>

                        <p><label>End Time:</label><br>
                        <input type="time" name="end_time" value="<?php echo esc_attr($reservation['end_time'] ?? ''); ?>" required></p>

                        <p><label>Description:</label><br>
                        <textarea name="description"><?php echo esc_textarea($reservation['description'] ?? ''); ?></textarea></p>

                        <button type="submit"
                        style="background:#2c8f2c;color:#fff;border:none;padding:10px 16px;border-radius:4px;cursor:pointer;">
                            Save Changes
                        </button>

                        <button type="button"
                        onclick="document.getElementById('edit-form').style.display='none'"
                        style="background:#666;color:#fff;border:none;padding:10px 16px;border-radius:4px;cursor:pointer;">
                            Cancel Edit
                        </button>

                    </form>
                </div>

                <hr style="margin:20px 0;">

                <h3>Cancel Reservation</h3>

                <form method="post"
                action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                onsubmit="return confirm('Are you sure you want to cancel this reservation?');">

                    <input type="hidden" name="action" value="mrb_guest_cancel">
                    <input type="hidden" name="token" value="<?php echo esc_attr($token); ?>">

                    <?php wp_nonce_field('mrb_guest_cancel_' . $token, 'mrb_nonce'); ?>

                    <button type="submit"
                    style="background:#d63638;color:#fff;border:none;padding:10px 16px;border-radius:4px;cursor:pointer;">
                        Cancel Reservation
                    </button>

                </form>

            <?php endif; ?>

        </main>

        <?php
        get_footer();
    }

}
