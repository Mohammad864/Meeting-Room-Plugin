<?php

namespace MRB\Front;

use MRB\Services\ReservationService;

if (!defined('ABSPATH')) {
    exit;
}

class ManageReservationHandler
{
    private ReservationService $service;

    public function __construct(ReservationService $service)
    {
        $this->service = $service;

        add_action('admin_post_mrb_guest_update', [$this, 'handleUpdate']);
        add_action('admin_post_nopriv_mrb_guest_update', [$this, 'handleUpdate']);

        add_action('admin_post_mrb_guest_cancel', [$this, 'handleCancel']);
        add_action('admin_post_nopriv_mrb_guest_cancel', [$this, 'handleCancel']);
    }

    /*
    |--------------------------------------------------------------------------
    | HANDLE UPDATE
    |--------------------------------------------------------------------------
    */

    public function handleUpdate(): void
    {
        if (!isset($_POST['token'])) {
            wp_die('Missing reservation token.');
        }

        $token = sanitize_text_field($_POST['token']);

        if (
            !isset($_POST['mrb_nonce']) ||
            !wp_verify_nonce($_POST['mrb_nonce'], 'mrb_guest_update_' . $token)
        ) {
            wp_die('Security check failed.');
        }

        $updated = $this->service->updateReservation($token, $_POST);

        if (!$updated) {
            wp_die('Failed to update reservation.');
        }

        wp_redirect(home_url('/reservation/' . $token . '/?updated=1'));
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | HANDLE CANCEL
    |--------------------------------------------------------------------------
    */

    public function handleCancel(): void
    {
        if (!isset($_POST['token'])) {
            wp_die('Missing reservation token.');
        }

        $token = sanitize_text_field($_POST['token']);

        if (
            !isset($_POST['mrb_nonce']) ||
            !wp_verify_nonce($_POST['mrb_nonce'], 'mrb_guest_cancel_' . $token)
        ) {
            wp_die('Security check failed.');
        }

        $cancelled = $this->service->cancelByToken($token);

        if (!$cancelled) {
            wp_die('Failed to cancel reservation.');
        }

        wp_redirect(home_url('/reservation/' . $token . '/?cancelled=1'));
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | FRONTEND USER NOTICE
    |--------------------------------------------------------------------------
    */

    public static function renderFrontendNotice(): void
    {
        if (isset($_GET['updated']) && $_GET['updated'] === '1') {
            echo '<div class="mrb-user-notice mrb-user-notice-success">';
            echo esc_html__('Your reservation has been updated successfully.', 'meeting-room-booking');
            echo '</div>';
            return;
        }

        if (isset($_GET['cancelled']) && $_GET['cancelled'] === '1') {
            echo '<div class="mrb-user-notice mrb-user-notice-success">';
            echo esc_html__('Your reservation has been cancelled successfully.', 'meeting-room-booking');
            echo '</div>';
            return;
        }
    }
}
