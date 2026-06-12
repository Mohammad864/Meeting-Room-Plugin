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
        wp_enqueue_style('mrb-booking-form');
        
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
        <form method="post"
            action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
            class="mrb-booking-form">

            <?php wp_nonce_field('mrb_submit_booking', 'mrb_nonce'); ?>
            <input type="hidden" name="action" value="mrb_submit_booking">

            <div class="mrb-card">

                <div class="mrb-header">
                    <h2>Meeting Room Reservation</h2>
                    <p>Fill out the form below to reserve a meeting room.</p>
                </div>

                <div class="mrb-grid">

                    <div class="mrb-field">
                        <label for="first_name">
                            First Name <span>*</span>
                        </label>
                        <input type="text" id="first_name" name="first_name" required>
                    </div>

                    <div class="mrb-field">
                        <label for="last_name">
                            Last Name <span>*</span>
                        </label>
                        <input type="text" id="last_name" name="last_name" required>
                    </div>

                    <div class="mrb-field">
                        <label for="mobile">
                            Mobile <span>*</span>
                        </label>
                        <input type="text" id="mobile" name="mobile" required>
                    </div>

                    <div class="mrb-field">
                        <label for="email">
                            Email <span>*</span>
                        </label>
                        <input type="email" id="email" name="email" required>
                    </div>

                </div>

                <div class="mrb-field">
                    <label for="meeting_title">
                        Meeting Title <span>*</span>
                    </label>
                    <input type="text" id="meeting_title" name="meeting_title" required>
                </div>

                <div class="mrb-grid">

                    <div class="mrb-field">
                        <label for="meeting_date">
                            Meeting Date <span>*</span>
                        </label>
                        <input type="date" id="meeting_date" name="meeting_date" required>
                    </div>

                    <div class="mrb-field">
                        <label for="start_time">
                            Start Time <span>*</span>
                        </label>
                        <input type="time" id="start_time" name="start_time" required>
                    </div>

                    <div class="mrb-field">
                        <label for="end_time">
                            End Time <span>*</span>
                        </label>
                        <input type="time" id="end_time" name="end_time" required>
                    </div>

                </div>

                <div class="mrb-field">
                    <label for="description">
                        Meeting Description
                    </label>
                    <textarea id="description"
                            name="description"
                            rows="5"></textarea>
                </div>

                <button type="submit" class="mrb-submit-btn">
                    Submit Reservation
                </button>

            </div>

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
