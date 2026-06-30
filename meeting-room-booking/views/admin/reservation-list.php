<?php
/**
 * Admin view: reservation list page.
 *
 * Variables extracted by View::output():
 *   @var array  $reservations    Rows from repository->query().
 *   @var array  $filters         ['search' => string, 'date' => string, 'status' => string]
 *   @var int    $minimumRooms    Minimum simultaneous rooms required for $calculationDate.
 *   @var string $calculationDate Y-m-d date used for the minimum-rooms calculation.
 */

if (!defined('ABSPATH')) {
    exit;
}

$search = $filters['search'] ?? '';
$date   = $filters['date']   ?? '';
$status = $filters['status'] ?? '';

// ── Inline notices ────────────────────────────────────────────────────────────
$noticeMessage = isset($_GET['mrb_message']) ? sanitize_key($_GET['mrb_message']) : '';
$noticeError   = isset($_GET['mrb_error'])   ? sanitize_key($_GET['mrb_error'])   : '';
?>
<div class="wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e('Meeting Room Reservations', 'meeting-room-booking'); ?></h1>

    <?php if ($noticeMessage === 'updated') : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e('Reservation updated successfully.', 'meeting-room-booking'); ?></p>
        </div>
    <?php endif; ?>

    <?php if ($noticeMessage === 'status_updated') : ?>
        <?php
        $msg = isset($_GET['mrb_msg'])
            ? sanitize_text_field(wp_unslash(rawurldecode($_GET['mrb_msg'])))
            : __('Reservation status updated successfully.', 'meeting-room-booking');
        ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html($msg); ?></p>
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

    <?php if ($noticeError === 'status_failed') : ?>
        <?php
        $msg = isset($_GET['mrb_error_msg'])
            ? sanitize_text_field(wp_unslash(rawurldecode($_GET['mrb_error_msg'])))
            : __('Failed to update reservation status.', 'meeting-room-booking');
        ?>
        <div class="notice notice-error is-dismissible">
            <p><?php echo esc_html($msg); ?></p>
        </div>
    <?php endif; ?>

    <hr class="wp-header-end">

    <div style="background:#fff;border:1px solid #ccd0d4;padding:14px;margin-top:20px;margin-bottom:20px;border-radius:6px;">
        <strong><?php echo esc_html(
            sprintf(
                /* translators: %s: date string */
                __('Minimum Rooms Required for %s:', 'meeting-room-booking'),
                $calculationDate
            )
        ); ?></strong>
        <?php echo esc_html($minimumRooms); ?>
    </div>

    <form method="get" style="margin:20px 0;">
        <input type="hidden" name="page" value="mrb-reservations">

        <p class="search-box" style="float:none;">
            <label class="screen-reader-text" for="mrb-search-input">
                <?php esc_html_e('Search Reservations', 'meeting-room-booking'); ?>
            </label>

            <input
                type="search"
                id="mrb-search-input"
                name="s"
                value="<?php echo esc_attr($search); ?>"
                placeholder="<?php esc_attr_e('Search by name or mobile', 'meeting-room-booking'); ?>"
            >

            <input
                type="date"
                name="meeting_date"
                value="<?php echo esc_attr($date); ?>"
            >

            <select name="status">
                <option value=""><?php esc_html_e('All Statuses', 'meeting-room-booking'); ?></option>
                <?php foreach (\MRB\Enums\ReservationStatus::ALL as $s) : ?>
                    <option value="<?php echo esc_attr($s); ?>" <?php selected($status, $s); ?>>
                        <?php echo esc_html(\MRB\Enums\ReservationStatus::label($s)); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="button"><?php esc_html_e('Filter', 'meeting-room-booking'); ?></button>

            <a href="<?php echo esc_url(admin_url('admin.php?page=mrb-reservations')); ?>" class="button">
                <?php esc_html_e('Reset', 'meeting-room-booking'); ?>
            </a>
        </p>
    </form>

    <table class="widefat fixed striped">
        <thead>
            <tr>
                <th width="60"><?php esc_html_e('ID', 'meeting-room-booking'); ?></th>
                <th><?php esc_html_e('Name', 'meeting-room-booking'); ?></th>
                <th><?php esc_html_e('Mobile', 'meeting-room-booking'); ?></th>
                <th><?php esc_html_e('Email', 'meeting-room-booking'); ?></th>
                <th><?php esc_html_e('Meeting Title', 'meeting-room-booking'); ?></th>
                <th><?php esc_html_e('Date', 'meeting-room-booking'); ?></th>
                <th><?php esc_html_e('Time', 'meeting-room-booking'); ?></th>
                <th><?php esc_html_e('Room', 'meeting-room-booking'); ?></th>
                <th><?php esc_html_e('Status', 'meeting-room-booking'); ?></th>
                <th width="220"><?php esc_html_e('Actions', 'meeting-room-booking'); ?></th>
            </tr>
        </thead>

        <tbody>
            <?php if (empty($reservations)) : ?>
                <tr>
                    <td colspan="10"><?php esc_html_e('No reservations found.', 'meeting-room-booking'); ?></td>
                </tr>
            <?php else : ?>
                <?php foreach ($reservations as $reservation) : ?>
                    <?php
                    $rowId     = (int) $reservation['id'];
                    $fullName  = trim(($reservation['first_name'] ?? '') . ' ' . ($reservation['last_name'] ?? ''));
                    $roomLabel = !empty($reservation['room_id'])
                        ? sprintf(__('Room #%d', 'meeting-room-booking'), (int) $reservation['room_id'])
                        : '-';

                    $editUrl = admin_url(
                        'admin.php?page=mrb-reservations&action=edit&id=' . $rowId
                    );

                    $approveUrl = wp_nonce_url(
                        admin_url('admin-post.php?action=mrb_change_status&id=' . $rowId . '&status=approved'),
                        'mrb_change_status_' . $rowId
                    );

                    $rejectUrl = wp_nonce_url(
                        admin_url('admin-post.php?action=mrb_change_status&id=' . $rowId . '&status=rejected'),
                        'mrb_change_status_' . $rowId
                    );

                    $pendingUrl = wp_nonce_url(
                        admin_url('admin-post.php?action=mrb_change_status&id=' . $rowId . '&status=pending'),
                        'mrb_change_status_' . $rowId
                    );
                    ?>
                    <tr>
                        <td><?php echo esc_html($rowId); ?></td>

                        <td><strong><?php echo esc_html($fullName); ?></strong></td>

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
                            <?php echo esc_html(
                                ($reservation['start_time'] ?? '') . ' - ' . ($reservation['end_time'] ?? '')
                            ); ?>
                        </td>

                        <td><?php echo esc_html($roomLabel); ?></td>

                        <td>
                            <?php echo wp_kses_post(\MRB\Support\StatusBadge::render($reservation['status'] ?? \MRB\Enums\ReservationStatus::PENDING)); ?>
                        </td>

                        <td>
                            <a class="button button-small" href="<?php echo esc_url($editUrl); ?>">
                                <?php esc_html_e('Edit', 'meeting-room-booking'); ?>
                            </a>
                            <a class="button button-small" href="<?php echo esc_url($approveUrl); ?>">
                                <?php esc_html_e('Approve', 'meeting-room-booking'); ?>
                            </a>
                            <a class="button button-small" href="<?php echo esc_url($rejectUrl); ?>">
                                <?php esc_html_e('Reject', 'meeting-room-booking'); ?>
                            </a>
                            <a class="button button-small" href="<?php echo esc_url($pendingUrl); ?>">
                                <?php esc_html_e('Pending', 'meeting-room-booking'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
