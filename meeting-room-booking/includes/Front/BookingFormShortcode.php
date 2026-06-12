<?php

namespace MRB\Front;

use MRB\Services\ReservationService;

if (!defined('ABSPATH')) {
    exit;
}

class BookingFormShortcode
{
    public static function render(): string
    {
        $message = '';

        if (!empty($_GET['mrb_status'])) {
            if ($_GET['mrb_status'] === 'success') {
                $message = '<div style="padding:10px;background:#d1e7dd;color:#0f5132;margin-bottom:15px;">Reservation submitted successfully.</div>';
            }

            if ($_GET['mrb_status'] === 'error') {
                $error = sanitize_text_field($_GET['mrb_error'] ?? 'Something went wrong.');
                $message = '<div style="padding:10px;background:#f8d7da;color:#842029;margin-bottom:15px;">' . esc_html($error) . '</div>';
            }
        }

        ob_start();
        echo $message;
        ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="mrb-booking-form">
            <?php wp_nonce_field('mrb_submit_booking', 'mrb_nonce'); ?>
            <input type="hidden" name="action" value="mrb_submit_booking">

            <p><label>First Name *</label><br><input type="text" name="first_name" required></p>
            <p><label>Last Name *</label><br><input type="text" name="last_name" required></p>
            <p><label>Mobile *</label><br><input type="text" name="mobile" required></p>
            <p><label>Email *</label><br><input type="email" name="email" required></p>
            <p><label>Meeting Title *</label><br><input type="text" name="meeting_title" required></p>
            <p><label>Meeting Date *</label><br><input type="date" name="meeting_date" required></p>
            <p><label>Start Time *</label><br><input type="time" name="start_time" required></p>
            <p><label>End Time *</label><br><input type="time" name="end_time" required></p>
            <p><label>Description</label><br><textarea name="description" rows="4"></textarea></p>
            <p><button type="submit">Submit Reservation</button></p>
        </form>
        <?php

        return ob_get_clean();
    }

    public static function handleSubmit(): void
    {
        if (
            empty($_POST['mrb_nonce']) ||
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mrb_nonce'])), 'mrb_submit_booking')
        ) {
            wp_die('Invalid nonce.');
        }

        $service = new ReservationService();
        $result = $service->create(wp_unslash($_POST));

        $redirectUrl = wp_get_referer() ?: home_url();

        if (!$result['success']) {
            $error = $result['errors'][0] ?? 'Invalid data.';
            wp_safe_redirect(add_query_arg([
                'mrb_status' => 'error',
                'mrb_error' => rawurlencode($error),
            ], $redirectUrl));
            exit;
        }

        wp_safe_redirect(add_query_arg([
            'mrb_status' => 'success',
        ], $redirectUrl));
        exit;
    }
}
