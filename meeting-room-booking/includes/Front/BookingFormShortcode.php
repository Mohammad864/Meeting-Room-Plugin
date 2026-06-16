<?php

namespace MRB\Front;

use MRB\Services\ReservationService;
use MRB\Database\ReservationRepository;

if (!defined('ABSPATH')) {
    exit;
}

class BookingFormShortcode
{
    public static function render(): string
    {
        // wp_enqueue_style('mrb-booking-form'); // Ensure this is registered
        
        $message = '';

        if (!empty($_GET['mrb_status'])) {
            if ($_GET['mrb_status'] === 'success') {
                $token = sanitize_text_field($_GET['token'] ?? '');
                $manageLink = home_url('/reservation/' . $token);
                
                $message = '<div style="padding:15px;background:#d1e7dd;color:#0f5132;margin-bottom:15px;border:1px solid #badbcc;border-radius:4px;">
                    <strong>Reservation submitted successfully.</strong><br>
                    Save this link to manage your booking later: <br>
                    <a href="' . esc_url($manageLink) . '">' . esc_url($manageLink) . '</a>
                </div>';
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

            <div class="mrb-card">
                <div class="mrb-header">
                    <h2>Meeting Room Reservation</h2>
                </div>

                <div class="mrb-grid">
                    <div class="mrb-field"><label>First Name:</label><input type="text" name="first_name" required></div>
                    <div class="mrb-field"><label>Last Name:</label><input type="text" name="last_name" required></div>
                    <div class="mrb-field"><label>Mobile:</label><input type="text" name="mobile" required></div>
                    <div class="mrb-field"><label>Email:</label><input type="email" name="email" required></div>
                </div>

                <div class="mrb-field"><label>Meeting Title:</label><input type="text" name="meeting_title" required></div>
                <div class="mrb-grid">
                    <div class="mrb-field"><label>Date:</label><input type="date" name="meeting_date" required></div>
                    <div class="mrb-field"><label>Start Time:</label><input type="time" name="start_time" required></div>
                    <div class="mrb-field"><label>End Time:</label><input type="time" name="end_time" required></div>
                </div>

                <div class="mrb-field"><label>Description:</label><textarea name="description" rows="5"></textarea></div>
                <button type="submit" class="mrb-submit-btn">Reserve Room</button>
            </div>
        </form>
        <?php
        return ob_get_clean();
    }

    public static function handleSubmit(): void
    {
        if (empty($_POST['mrb_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mrb_nonce'])), 'mrb_submit_booking')) {
            wp_die('Invalid nonce.');
        }

        // Initialize Service Stack
        $repository = new ReservationRepository();
        $service    = new ReservationService($repository);
        
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

        // Redirect to the management route with the generated token
        wp_safe_redirect(add_query_arg([
            'mrb_status' => 'success',
            'token'      => $result['token'], // Ensure create() returns this
        ], $redirectUrl));
        exit;
    }
}
