<?php

namespace MRB\Admin;

use MRB\Services\ReservationService;
use MRB\Database\ReservationRepository;
use MRB\Services\MinimumRoomsCalculator;

if (!defined('ABSPATH')) {
    exit;
}

class AdminPage
{
    public static function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Access denied.');
        }

        if (!class_exists('WP_List_Table')) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
        }

        $table = new ReservationsListTable();
        $table->prepare_items();

        $date = sanitize_text_field($_GET['filter_date'] ?? '');
        $minimumRooms = null;

        if ($date) {
            $repository = new ReservationRepository();
            $calculator = new MinimumRoomsCalculator();
            $minimumRooms = $calculator->calculate($repository->getApprovedByDate($date));
        }

        ?>
        <div class="wrap">
            <h1>Meeting Room Reservations</h1>

            <?php if (!empty($_GET['mrb_message'])): ?>
                <div class="notice notice-info is-dismissible">
                    <p><?php echo esc_html(sanitize_text_field($_GET['mrb_message'])); ?></p>
                </div>
            <?php endif; ?>

            <?php if ($date && $minimumRooms !== null): ?>
                <div class="notice notice-success">
                    <p>
                        Minimum rooms required for
                        <strong><?php echo esc_html($date); ?></strong>:
                        <strong><?php echo esc_html((string) $minimumRooms); ?></strong>
                    </p>
                </div>
            <?php endif; ?>

            <form method="get">
                <input type="hidden" name="page" value="mrb-reservations">

                <p class="search-box">
                    <label class="screen-reader-text" for="mrb-search-input">Search</label>
                    <input type="search" id="mrb-search-input" name="s"
                           value="<?php echo esc_attr($_GET['s'] ?? ''); ?>"
                           placeholder="Name or mobile">

                    <input type="date" name="filter_date"
                           value="<?php echo esc_attr($_GET['filter_date'] ?? ''); ?>">

                    <?php submit_button('Search / Filter', '', '', false); ?>
                </p>

                <?php $table->display(); ?>
            </form>
        </div>
        <?php
    }

    public static function handleStatusChange(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Access denied.');
        }

        $reservationId = absint($_GET['reservation_id'] ?? 0);
        $status = sanitize_text_field($_GET['status'] ?? '');
        $nonce = sanitize_text_field($_GET['_wpnonce'] ?? '');

        if (!$reservationId || !wp_verify_nonce($nonce, 'mrb_change_status_' . $reservationId)) {
            wp_die('Invalid request.');
        }

        $service = new ReservationService();

        if ($status === 'approved') {
            $result = $service->approve($reservationId);
        } elseif ($status === 'rejected') {
            $result = $service->reject($reservationId);
        } else {
            $result = [
                'success' => false,
                'message' => 'Invalid status.',
            ];
        }

        wp_safe_redirect(add_query_arg([
            'page' => 'mrb-reservations',
            'mrb_message' => rawurlencode($result['message']),
        ], admin_url('admin.php')));
        exit;
    }
}
