<?php

namespace MRB\Admin;

use MRB\Database\ReservationRepository;
use MRB\Services\ReservationService;
use MRB\Services\MinimumRoomsCalculator;

if (!defined('ABSPATH')) {
    exit;
}

class AdminPage
{
    public static function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('You do not have permission to access this page.');
        }

        $action = isset($_GET['action']) ? sanitize_key($_GET['action']) : '';

        if ($action === 'edit') {
            $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
            self::renderEditForm($id);
            return;
        }

        self::renderListPage();
    }

    private static function renderListPage(): void
    {
        $repository = new ReservationRepository();

        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $date   = isset($_GET['meeting_date']) ? sanitize_text_field(wp_unslash($_GET['meeting_date'])) : '';
        $status = isset($_GET['status']) ? sanitize_key($_GET['status']) : '';

        $allowedStatuses = ['pending', 'approved', 'rejected', 'cancelled'];

        if (!in_array($status, $allowedStatuses, true)) {
            $status = '';
        }

        $args = [
            'search' => $search,
            'date'   => $date,
            'status' => $status,
            'limit'  => 100,
            'offset' => 0,
        ];

        $reservations = $repository->query($args);

        /*
        |--------------------------------------------------------------------------
        | Minimum Rooms Calculation
        |--------------------------------------------------------------------------
        */

        $calculationDate = $date ?: date('Y-m-d');
        $minimumRooms = self::calculateMinimumRoomsForDay($calculationDate);

        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Meeting Room Reservations</h1>

            <?php self::renderNotices(); ?>

            <hr class="wp-header-end">

            <div style="background:#fff;border:1px solid #ccd0d4;padding:14px;margin-top:20px;margin-bottom:20px;border-radius:6px;">
                <strong>Minimum Rooms Required for <?php echo esc_html($calculationDate); ?>:</strong>
                <?php echo esc_html($minimumRooms); ?>
            </div>

            <form method="get" style="margin: 20px 0;">
                <input type="hidden" name="page" value="mrb-reservations">

                <p class="search-box" style="float:none;">
                    <label class="screen-reader-text" for="mrb-search-input">
                        Search Reservations
                    </label>

                    <input
                        type="search"
                        id="mrb-search-input"
                        name="s"
                        value="<?php echo esc_attr($search); ?>"
                        placeholder="Search by name or mobile"
                    >

                    <input
                        type="date"
                        name="meeting_date"
                        value="<?php echo esc_attr($date); ?>"
                    >

                    <select name="status">
                        <option value="">All Statuses</option>
                        <option value="pending" <?php selected($status, 'pending'); ?>>Pending</option>
                        <option value="approved" <?php selected($status, 'approved'); ?>>Approved</option>
                        <option value="rejected" <?php selected($status, 'rejected'); ?>>Rejected</option>
                        <option value="cancelled" <?php selected($status, 'cancelled'); ?>>Cancelled</option>
                    </select>

                    <button type="submit" class="button">Filter</button>

                    <a href="<?php echo esc_url(admin_url('admin.php?page=mrb-reservations')); ?>" class="button">
                        Reset
                    </a>
                </p>
            </form>

            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th width="60">ID</th>
                        <th>Name</th>
                        <th>Mobile</th>
                        <th>Email</th>
                        <th>Meeting Title</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Room</th>
                        <th>Status</th>
                        <th width="220">Actions</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (empty($reservations)) : ?>
                        <tr>
                            <td colspan="10">No reservations found.</td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($reservations as $reservation) : ?>
                            <?php
                            $id = (int) $reservation['id'];

                            $editUrl = admin_url(
                                'admin.php?page=mrb-reservations&action=edit&id=' . $id
                            );

                            $approveUrl = wp_nonce_url(
                                admin_url('admin-post.php?action=mrb_change_status&id=' . $id . '&status=approved'),
                                'mrb_change_status_' . $id
                            );

                            $rejectUrl = wp_nonce_url(
                                admin_url('admin-post.php?action=mrb_change_status&id=' . $id . '&status=rejected'),
                                'mrb_change_status_' . $id
                            );

                            $pendingUrl = wp_nonce_url(
                                admin_url('admin-post.php?action=mrb_change_status&id=' . $id . '&status=pending'),
                                'mrb_change_status_' . $id
                            );

                            $fullName = trim(($reservation['first_name'] ?? '') . ' ' . ($reservation['last_name'] ?? ''));

                            $roomLabel = !empty($reservation['room_id'])
                                ? 'Room #' . (int) $reservation['room_id']
                                : '-';
                            ?>
                            <tr>
                                <td><?php echo esc_html($id); ?></td>

                                <td>
                                    <strong><?php echo esc_html($fullName); ?></strong>
                                </td>

                                <td><?php echo esc_html($reservation['mobile'] ?? ''); ?></td>

                                <td>
                                    <?php if (!empty($reservation['email'])) : ?>
                                        <a href="mailto:<?php echo esc_attr($reservation['email']); ?>">
                                            <?php echo esc_html($reservation['email']); ?>
                                        </a>
                                    <?php else : ?>
                                        -
                                    <?php endif; ?>
                                </td>

                                <td><?php echo esc_html($reservation['meeting_title'] ?? ''); ?></td>

                                <td><?php echo esc_html($reservation['meeting_date'] ?? ''); ?></td>

                                <td>
                                    <?php echo esc_html(($reservation['start_time'] ?? '') . ' - ' . ($reservation['end_time'] ?? '')); ?>
                                </td>

                                <td><?php echo esc_html($roomLabel); ?></td>

                                <td>
                                    <?php echo self::renderStatusBadge($reservation['status'] ?? 'pending'); ?>
                                </td>

                                <td>
                                    <a class="button button-small" href="<?php echo esc_url($editUrl); ?>">
                                        Edit
                                    </a>

                                    <a class="button button-small" href="<?php echo esc_url($approveUrl); ?>">
                                        Approve
                                    </a>

                                    <a class="button button-small" href="<?php echo esc_url($rejectUrl); ?>">
                                        Reject
                                    </a>

                                    <a class="button button-small" href="<?php echo esc_url($pendingUrl); ?>">
                                        Pending
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /*
    |--------------------------------------------------------------------------
    | Minimum Rooms Calculation Method
    |--------------------------------------------------------------------------
    */

    private static function calculateMinimumRoomsForDay(string $date): int
    {
        global $wpdb;

        $table = $wpdb->prefix . 'mrb_reservations';

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT start_time, end_time
                 FROM {$table}
                 WHERE meeting_date = %s
                 AND status != 'cancelled'
                 AND status != 'rejected'",
                $date
            ),
            ARRAY_A
        );

        if (!$rows) {
            return 0;
        }

        $calculator = new MinimumRoomsCalculator();

        return $calculator->calculate($rows);
    }

    /**
     * Render admin reservation edit form.
     */
    private static function renderEditForm(int $id): void
    {
        if ($id <= 0) {
            echo '<div class="wrap"><div class="notice notice-error"><p>Invalid reservation ID.</p></div></div>';
            return;
        }

        $repository = new ReservationRepository();
        $reservation = $repository->findById($id);

        if (!$reservation) {
            echo '<div class="wrap"><div class="notice notice-error"><p>Reservation not found.</p></div></div>';
            return;
        }

        $backUrl = admin_url('admin.php?page=mrb-reservations');

        ?>
        <div class="wrap">
            <h1>Edit Reservation #<?php echo esc_html($id); ?></h1>

            <?php self::renderNotices(); ?>

            <p>
                <a href="<?php echo esc_url($backUrl); ?>" class="button">
                    ← Back to Reservations
                </a>
            </p>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('mrb_admin_edit_' . $id, 'mrb_nonce'); ?>

                <input type="hidden" name="action" value="mrb_admin_update_reservation">
                <input type="hidden" name="reservation_id" value="<?php echo esc_attr($id); ?>">

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="first_name">First Name</label>
                            </th>
                            <td>
                                <input
                                    type="text"
                                    id="first_name"
                                    name="first_name"
                                    class="regular-text"
                                    value="<?php echo esc_attr($reservation['first_name'] ?? ''); ?>"
                                    required
                                >
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="last_name">Last Name</label>
                            </th>
                            <td>
                                <input
                                    type="text"
                                    id="last_name"
                                    name="last_name"
                                    class="regular-text"
                                    value="<?php echo esc_attr($reservation['last_name'] ?? ''); ?>"
                                    required
                                >
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="mobile">Mobile</label>
                            </th>
                            <td>
                                <input
                                    type="text"
                                    id="mobile"
                                    name="mobile"
                                    class="regular-text"
                                    value="<?php echo esc_attr($reservation['mobile'] ?? ''); ?>"
                                    required
                                >
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="email">Email</label>
                            </th>
                            <td>
                                <input
                                    type="email"
                                    id="email"
                                    name="email"
                                    class="regular-text"
                                    value="<?php echo esc_attr($reservation['email'] ?? ''); ?>"
                                    required
                                >
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="meeting_title">Meeting Title</label>
                            </th>
                            <td>
                                <input
                                    type="text"
                                    id="meeting_title"
                                    name="meeting_title"
                                    class="regular-text"
                                    value="<?php echo esc_attr($reservation['meeting_title'] ?? ''); ?>"
                                    required
                                >
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="meeting_date">Meeting Date</label>
                            </th>
                            <td>
                                <input
                                    type="date"
                                    id="meeting_date"
                                    name="meeting_date"
                                    value="<?php echo esc_attr($reservation['meeting_date'] ?? ''); ?>"
                                    required
                                >
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="start_time">Start Time</label>
                            </th>
                            <td>
                                <input
                                    type="time"
                                    id="start_time"
                                    name="start_time"
                                    value="<?php echo esc_attr(substr($reservation['start_time'] ?? '', 0, 5)); ?>"
                                    required
                                >
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="end_time">End Time</label>
                            </th>
                            <td>
                                <input
                                    type="time"
                                    id="end_time"
                                    name="end_time"
                                    value="<?php echo esc_attr(substr($reservation['end_time'] ?? '', 0, 5)); ?>"
                                    required
                                >
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="status">Status</label>
                            </th>
                            <td>
                                <select id="status" name="status">
                                    <option value="pending" <?php selected($reservation['status'] ?? '', 'pending'); ?>>
                                        Pending
                                    </option>
                                    <option value="approved" <?php selected($reservation['status'] ?? '', 'approved'); ?>>
                                        Approved
                                    </option>
                                    <option value="rejected" <?php selected($reservation['status'] ?? '', 'rejected'); ?>>
                                        Rejected
                                    </option>
                                    <option value="cancelled" <?php selected($reservation['status'] ?? '', 'cancelled'); ?>>
                                        Cancelled
                                    </option>
                                </select>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="room_id">Room ID</label>
                            </th>
                            <td>
                                <input
                                    type="number"
                                    id="room_id"
                                    name="room_id"
                                    min="0"
                                    value="<?php echo esc_attr($reservation['room_id'] ?? ''); ?>"
                                >
                                <p class="description">
                                    Leave empty or 0 if no room is assigned yet.
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="description">Description</label>
                            </th>
                            <td>
                                <textarea
                                    id="description"
                                    name="description"
                                    class="large-text"
                                    rows="6"
                                ><?php echo esc_textarea($reservation['description'] ?? ''); ?></textarea>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <?php submit_button('Update Reservation'); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Handle admin edit submission.
     *
     * Hook this in Plugin.php:
     * add_action('admin_post_mrb_admin_update_reservation', [AdminPage::class, 'handleAdminUpdate']);
     */
    public static function handleAdminUpdate(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('You do not have permission to perform this action.');
        }

        $id = isset($_POST['reservation_id']) ? absint($_POST['reservation_id']) : 0;

        if ($id <= 0) {
            wp_die('Invalid reservation ID.');
        }

        $nonce = isset($_POST['mrb_nonce'])
            ? sanitize_text_field(wp_unslash($_POST['mrb_nonce']))
            : '';

        if (!wp_verify_nonce($nonce, 'mrb_admin_edit_' . $id)) {
            wp_die('Invalid nonce.');
        }

        $repository = new ReservationRepository();
        $service    = new ReservationService($repository);

        $updated = $service->adminEdit($id, wp_unslash($_POST));

        $redirectUrl = admin_url('admin.php?page=mrb-reservations&action=edit&id=' . $id);

        $updated = $service->adminEdit($id, wp_unslash($_POST));

        if (!$updated) {
            echo '<h2>Reservation Update Debug</h2>';

            echo '<h3>POST Data</h3>';
            echo '<pre>';
            print_r($_POST);
            echo '</pre>';

            echo '<p><strong>Update returned FALSE.</strong></p>';

            global $wpdb;

            if (!empty($wpdb->last_error)) {
                echo '<h3>Database Error</h3>';
                echo '<pre>' . esc_html($wpdb->last_error) . '</pre>';
            }

            echo '<p>Fix the issue and then remove this debug code.</p>';

            exit;
        }

        wp_safe_redirect(add_query_arg('mrb_message', 'updated', $redirectUrl));
        exit;
    }

    /**
     * Handle approve/reject/pending status change.
     *
     * Hook this in Plugin.php:
     * add_action('admin_post_mrb_change_status', [AdminPage::class, 'handleStatusChange']);
     */
    public static function handleStatusChange(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('You do not have permission to perform this action.');
        }

        $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        $status = isset($_GET['status']) ? sanitize_key($_GET['status']) : '';

        if ($id <= 0) {
            wp_die('Invalid reservation ID.');
        }

        $allowedStatuses = ['pending', 'approved', 'rejected'];

        if (!in_array($status, $allowedStatuses, true)) {
            wp_die('Invalid reservation status.');
        }

        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(
            sanitize_text_field(wp_unslash($_GET['_wpnonce'])),
            'mrb_change_status_' . $id
        )) {
            wp_die('Invalid nonce.');
        }

        $repository = new ReservationRepository();
        $service    = new ReservationService($repository);

        $result = $service->changeStatus($id, $status);

        $redirectUrl = admin_url('admin.php?page=mrb-reservations');

        if (!$result['success']) {
            wp_safe_redirect(add_query_arg([
                'mrb_error' => 'status_failed',
                'mrb_error_msg' => urlencode($result['message']),
            ], $redirectUrl));
            exit;
        }

        wp_safe_redirect(add_query_arg([
            'mrb_message' => 'status_updated',
            'mrb_msg' => urlencode($result['message']),
        ], $redirectUrl));
        exit;
    }

    /**
     * Render admin notices.
     */
    private static function renderNotices(): void
    {
        $message = isset($_GET['mrb_message']) ? sanitize_key($_GET['mrb_message']) : '';
        $error   = isset($_GET['mrb_error']) ? sanitize_key($_GET['mrb_error']) : '';

        if ($message === 'updated') {
            echo '<div class="notice notice-success is-dismissible"><p>Reservation updated successfully.</p></div>';
        }

        if ($message === 'status_updated') {
            $msg = isset($_GET['mrb_msg'])
                ? urldecode(wp_unslash($_GET['mrb_msg']))
                : 'Reservation status updated successfully.';

            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($msg) . '</p></div>';
        }

        if ($error === 'update_failed') {
            echo '<div class="notice notice-error is-dismissible"><p>Failed to update reservation.</p></div>';
        }

        if ($error === 'status_failed') {
            $msg = isset($_GET['mrb_error_msg'])
                ? urldecode(wp_unslash($_GET['mrb_error_msg']))
                : 'Failed to update reservation status.';

            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($msg) . '</p></div>';
        }
    }

    /**
     * Render status badge.
     */
    private static function renderStatusBadge(string $status): string
    {
        $status = sanitize_key($status);

        $colors = [
            'pending'   => '#f0ad4e',
            'approved'  => '#46b450',
            'rejected'  => '#dc3232',
            'cancelled' => '#666666',
        ];

        $labels = [
            'pending'   => 'Pending',
            'approved'  => 'Approved',
            'rejected'  => 'Rejected',
            'cancelled' => 'Cancelled',
        ];

        $color = $colors[$status] ?? '#777777';
        $label = $labels[$status] ?? ucfirst($status);

        return sprintf(
            '<span style="display:inline-block;padding:4px 8px;border-radius:4px;background:%s;color:#fff;font-size:12px;">%s</span>',
            esc_attr($color),
            esc_html($label)
        );
    }
}
