<?php
/**
 * Template: Front / Manage Reservation
 *
 * Variables (extracted by View::output):
 *   @var string $token        The reservation token
 *   @var array  $reservation  Reservation data row
 *   @var string $status       Current reservation status
 *   @var bool   $canManage    Whether guest actions (edit/cancel) are available
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<main class="mrb-container">
    <?php \MRB\Support\FrontendNotice::renderFromRequest(); ?>

    <header class="mrb-page-header">
        <h1 class="mrb-page-title">
            <?php echo esc_html__('Manage Your Reservation', 'meeting-room-booking'); ?>
        </h1>
        <p class="mrb-page-subtitle">
            <?php echo esc_html__('Review your booking details, update the reservation, or cancel it if needed.', 'meeting-room-booking'); ?>
        </p>
    </header>

    <section class="mrb-card">
        <div class="mrb-card-header">
            <h2 class="mrb-card-title">
                <?php echo esc_html__('Reservation Details', 'meeting-room-booking'); ?>
            </h2>
        </div>

        <div class="mrb-details-grid">
            <div class="mrb-detail-item">
                <span class="mrb-detail-label">
                    <?php echo esc_html__('Name', 'meeting-room-booking'); ?>
                </span>
                <span class="mrb-detail-value">
                    <?php echo esc_html(trim(($reservation['first_name'] ?? '') . ' ' . ($reservation['last_name'] ?? ''))); ?>
                </span>
            </div>

            <div class="mrb-detail-item">
                <span class="mrb-detail-label">
                    <?php echo esc_html__('Email', 'meeting-room-booking'); ?>
                </span>
                <span class="mrb-detail-value">
                    <?php echo esc_html($reservation['email'] ?? ''); ?>
                </span>
            </div>

            <div class="mrb-detail-item">
                <span class="mrb-detail-label">
                    <?php echo esc_html__('Mobile', 'meeting-room-booking'); ?>
                </span>
                <span class="mrb-detail-value">
                    <?php echo esc_html($reservation['mobile'] ?? ''); ?>
                </span>
            </div>

            <div class="mrb-detail-item">
                <span class="mrb-detail-label">
                    <?php echo esc_html__('Meeting Title', 'meeting-room-booking'); ?>
                </span>
                <span class="mrb-detail-value">
                    <?php echo esc_html($reservation['meeting_title'] ?? ''); ?>
                </span>
            </div>

            <?php if (!empty($reservation['room_name'])) : ?>
                <div class="mrb-detail-item">
                    <span class="mrb-detail-label">
                        <?php echo esc_html__('Room', 'meeting-room-booking'); ?>
                    </span>
                    <span class="mrb-detail-value">
                        <?php echo esc_html($reservation['room_name']); ?>
                    </span>
                </div>
            <?php endif; ?>

            <div class="mrb-detail-item">
                <span class="mrb-detail-label">
                    <?php echo esc_html__('Date', 'meeting-room-booking'); ?>
                </span>
                <span class="mrb-detail-value">
                    <?php echo esc_html($reservation['meeting_date'] ?? ''); ?>
                </span>
            </div>

            <div class="mrb-detail-item">
                <span class="mrb-detail-label">
                    <?php echo esc_html__('Time', 'meeting-room-booking'); ?>
                </span>
                <span class="mrb-detail-value">
                    <?php echo esc_html(($reservation['start_time'] ?? '') . ' - ' . ($reservation['end_time'] ?? '')); ?>
                </span>
            </div>

            <div class="mrb-detail-item">
                <span class="mrb-detail-label">
                    <?php echo esc_html__('Status', 'meeting-room-booking'); ?>
                </span>
                <span class="mrb-detail-value">
                    <?php echo wp_kses_post(\MRB\Support\StatusBadge::render($status)); ?>
                </span>
            </div>

            <?php if (!empty($reservation['description'])) : ?>
                <div class="mrb-detail-item mrb-detail-item-full">
                    <span class="mrb-detail-label">
                        <?php echo esc_html__('Description', 'meeting-room-booking'); ?>
                    </span>
                    <span class="mrb-detail-value">
                        <?php echo esc_html($reservation['description']); ?>
                    </span>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($canManage) : ?>
        <section class="mrb-card">
            <div class="mrb-card-header">
                <h2 class="mrb-card-title">
                    <?php echo esc_html__('Edit Reservation', 'meeting-room-booking'); ?>
                </h2>
            </div>

            <div class="mrb-card-actions">
                <button
                    type="button"
                    class="mrb-btn mrb-btn-primary"
                    onclick="document.getElementById('mrb-edit-form-panel').style.display='block'; this.style.display='none';"
                >
                    <?php echo esc_html__('Edit Reservation', 'meeting-room-booking'); ?>
                </button>
            </div>

            <div id="mrb-edit-form-panel" class="mrb-panel" style="display:none;">
                <h3 class="mrb-panel-title">
                    <?php echo esc_html__('Update Reservation Details', 'meeting-room-booking'); ?>
                </h3>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="mrb-form">
                    <input type="hidden" name="action" value="mrb_guest_update">
                    <input type="hidden" name="token" value="<?php echo esc_attr($token); ?>">

                    <?php wp_nonce_field('mrb_guest_update_' . $token, 'mrb_nonce'); ?>

                    <div class="mrb-form-grid">
                        <div class="mrb-form-group">
                            <label for="mrb_first_name">
                                <?php echo esc_html__('First Name', 'meeting-room-booking'); ?>
                            </label>
                            <input
                                id="mrb_first_name"
                                class="mrb-input"
                                type="text"
                                name="first_name"
                                value="<?php echo esc_attr($reservation['first_name'] ?? ''); ?>"
                                required
                            >
                        </div>

                        <div class="mrb-form-group">
                            <label for="mrb_last_name">
                                <?php echo esc_html__('Last Name', 'meeting-room-booking'); ?>
                            </label>
                            <input
                                id="mrb_last_name"
                                class="mrb-input"
                                type="text"
                                name="last_name"
                                value="<?php echo esc_attr($reservation['last_name'] ?? ''); ?>"
                                required
                            >
                        </div>

                        <div class="mrb-form-group">
                            <label for="mrb_email">
                                <?php echo esc_html__('Email', 'meeting-room-booking'); ?>
                            </label>
                            <input
                                id="mrb_email"
                                class="mrb-input"
                                type="email"
                                name="email"
                                value="<?php echo esc_attr($reservation['email'] ?? ''); ?>"
                                required
                            >
                        </div>

                        <div class="mrb-form-group">
                            <label for="mrb_mobile">
                                <?php echo esc_html__('Mobile', 'meeting-room-booking'); ?>
                            </label>
                            <input
                                id="mrb_mobile"
                                class="mrb-input"
                                type="text"
                                name="mobile"
                                value="<?php echo esc_attr($reservation['mobile'] ?? ''); ?>"
                                required
                            >
                        </div>

                        <div class="mrb-form-group mrb-form-group-full">
                            <label for="mrb_meeting_title">
                                <?php echo esc_html__('Meeting Title', 'meeting-room-booking'); ?>
                            </label>
                            <input
                                id="mrb_meeting_title"
                                class="mrb-input"
                                type="text"
                                name="meeting_title"
                                value="<?php echo esc_attr($reservation['meeting_title'] ?? ''); ?>"
                                required
                            >
                        </div>

                        <div class="mrb-form-group">
                            <label for="mrb_meeting_date">
                                <?php echo esc_html__('Date', 'meeting-room-booking'); ?>
                            </label>
                            <input
                                id="mrb_meeting_date"
                                class="mrb-input"
                                type="date"
                                name="meeting_date"
                                value="<?php echo esc_attr($reservation['meeting_date'] ?? ''); ?>"
                                required
                            >
                        </div>

                        <div class="mrb-form-group">
                            <label for="mrb_start_time">
                                <?php echo esc_html__('Start Time', 'meeting-room-booking'); ?>
                            </label>
                            <input
                                id="mrb_start_time"
                                class="mrb-input"
                                type="time"
                                name="start_time"
                                value="<?php echo esc_attr($reservation['start_time'] ?? ''); ?>"
                                required
                            >
                        </div>

                        <div class="mrb-form-group">
                            <label for="mrb_end_time">
                                <?php echo esc_html__('End Time', 'meeting-room-booking'); ?>
                            </label>
                            <input
                                id="mrb_end_time"
                                class="mrb-input"
                                type="time"
                                name="end_time"
                                value="<?php echo esc_attr($reservation['end_time'] ?? ''); ?>"
                                required
                            >
                        </div>

                        <div class="mrb-form-group mrb-form-group-full">
                            <label for="mrb_description">
                                <?php echo esc_html__('Description', 'meeting-room-booking'); ?>
                            </label>
                            <textarea
                                id="mrb_description"
                                class="mrb-textarea"
                                name="description"
                                rows="5"
                            ><?php echo esc_textarea($reservation['description'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <div class="mrb-form-actions">
                        <button type="submit" class="mrb-btn mrb-btn-success">
                            <?php echo esc_html__('Save Changes', 'meeting-room-booking'); ?>
                        </button>

                        <button
                            type="button"
                            class="mrb-btn"
                            onclick="document.getElementById('mrb-edit-form-panel').style.display='none';"
                        >
                            <?php echo esc_html__('Cancel Edit', 'meeting-room-booking'); ?>
                        </button>
                    </div>
                </form>
            </div>
        </section>

        <section class="mrb-card mrb-danger-panel">
            <div class="mrb-card-header">
                <h2 class="mrb-card-title">
                    <?php echo esc_html__('Cancel Reservation', 'meeting-room-booking'); ?>
                </h2>
            </div>

            <p class="mrb-panel-text">
                <?php echo esc_html__('If you no longer need this booking, you can cancel it here.', 'meeting-room-booking'); ?>
            </p>

            <form
                method="post"
                action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                onsubmit="return confirm('<?php echo esc_js(__('Are you sure you want to cancel this reservation?', 'meeting-room-booking')); ?>');"
            >
                <input type="hidden" name="action" value="mrb_guest_cancel">
                <input type="hidden" name="token" value="<?php echo esc_attr($token); ?>">

                <?php wp_nonce_field('mrb_guest_cancel_' . $token, 'mrb_nonce'); ?>

                <button type="submit" class="mrb-btn mrb-btn-danger">
                    <?php echo esc_html__('Cancel Reservation', 'meeting-room-booking'); ?>
                </button>
            </form>
        </section>
    <?php endif; ?>
</main>
