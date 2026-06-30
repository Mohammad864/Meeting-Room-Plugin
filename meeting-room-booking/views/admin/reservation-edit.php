<?php
/**
 * Admin view: reservation edit form.
 *
 * Variables extracted by View::output():
 *   @var int        $id          Reservation ID (may be 0 if missing from URL).
 *   @var array|null $reservation Row from repository->findById(), or null if not found.
 */

if (!defined('ABSPATH')) {
    exit;
}

if ($id <= 0) : ?>
    <div class="wrap">
        <div class="notice notice-error">
            <p><?php esc_html_e('Invalid reservation ID.', 'meeting-room-booking'); ?></p>
        </div>
    </div>
    <?php return;
endif;

if (!$reservation) : ?>
    <div class="wrap">
        <div class="notice notice-error">
            <p><?php esc_html_e('Reservation not found.', 'meeting-room-booking'); ?></p>
        </div>
    </div>
    <?php return;
endif;

// ── Inline notices ────────────────────────────────────────────────────────────
$noticeMessage = isset($_GET['mrb_message']) ? sanitize_key($_GET['mrb_message']) : '';
$noticeError   = isset($_GET['mrb_error'])   ? sanitize_key($_GET['mrb_error'])   : '';

$backUrl = admin_url('admin.php?page=mrb-reservations');
?>
<div class="wrap">
    <h1><?php echo esc_html(sprintf(__('Edit Reservation #%d', 'meeting-room-booking'), $id)); ?></h1>

    <?php if ($noticeMessage === 'updated') : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e('Reservation updated successfully. A notification email was sent if mail is configured.', 'meeting-room-booking'); ?></p>
        </div>
    <?php endif; ?>

    <?php if ($noticeError === 'update_failed') : ?>
        <?php
        $msg = isset($_GET['mrb_error_msg'])
            ? sanitize_text_field(wp_unslash(rawurldecode($_GET['mrb_error_msg'])))
            : __('Failed to update reservation.', 'meeting-room-booking');
        ?>
        <div class="notice notice-error is-dismissible">
            <p><?php echo esc_html($msg); ?></p>
        </div>
    <?php endif; ?>

    <p>
        <a href="<?php echo esc_url($backUrl); ?>" class="button">
            &larr; <?php esc_html_e('Back to Reservations', 'meeting-room-booking'); ?>
        </a>
    </p>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('mrb_admin_edit_' . $id, 'mrb_nonce'); ?>

        <input type="hidden" name="action"         value="mrb_admin_update_reservation">
        <input type="hidden" name="reservation_id" value="<?php echo esc_attr($id); ?>">

        <table class="form-table" role="presentation">
            <tbody>

                <tr>
                    <th scope="row">
                        <label for="first_name"><?php esc_html_e('First Name', 'meeting-room-booking'); ?></label>
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
                        <label for="last_name"><?php esc_html_e('Last Name', 'meeting-room-booking'); ?></label>
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
                        <label for="mobile"><?php esc_html_e('Mobile', 'meeting-room-booking'); ?></label>
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
                        <label for="email"><?php esc_html_e('Email', 'meeting-room-booking'); ?></label>
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
                        <label for="meeting_title"><?php esc_html_e('Meeting Title', 'meeting-room-booking'); ?></label>
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
                        <label for="meeting_date"><?php esc_html_e('Meeting Date', 'meeting-room-booking'); ?></label>
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
                        <label for="start_time"><?php esc_html_e('Start Time', 'meeting-room-booking'); ?></label>
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
                        <label for="end_time"><?php esc_html_e('End Time', 'meeting-room-booking'); ?></label>
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
                        <label for="status"><?php esc_html_e('Status', 'meeting-room-booking'); ?></label>
                    </th>
                    <td>
                        <select id="status" name="status">
                            <?php foreach (\MRB\Enums\ReservationStatus::ALL as $s) : ?>
                                <option value="<?php echo esc_attr($s); ?>"
                                    <?php selected($reservation['status'] ?? '', $s); ?>>
                                    <?php echo esc_html(\MRB\Enums\ReservationStatus::label($s)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="room_id"><?php esc_html_e('Room ID', 'meeting-room-booking'); ?></label>
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
                            <?php esc_html_e('Leave empty or 0 if no room is assigned yet.', 'meeting-room-booking'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="description"><?php esc_html_e('Description', 'meeting-room-booking'); ?></label>
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

        <?php submit_button(__('Update Reservation', 'meeting-room-booking')); ?>
    </form>
</div>
