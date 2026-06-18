<?php

namespace MRB\Front;

use MRB\Services\ReservationService;
use MRB\Services\EmailNotificationService;

if (!defined('ABSPATH')) {
    exit;
}

class ManageReservationHandler
{
    private ReservationService $service;

    /**
     * Production mode.
     *
     * IMPORTANT:
     * Keep this false in production to avoid exposing internal debug data.
     */
    private bool $debugMode = false;

    /**
     * Prevent duplicate frontend notices.
     *
     * This is useful because notices may be rendered manually in a template
     * and also automatically through wp_body_open/wp_footer.
     */
    private static bool $noticeRendered = false;

    public function __construct(ReservationService $service)
    {
        $this->service = $service;

        add_action('admin_post_mrb_guest_update', [$this, 'handleUpdate']);
        add_action('admin_post_nopriv_mrb_guest_update', [$this, 'handleUpdate']);

        add_action('admin_post_mrb_guest_cancel', [$this, 'handleCancel']);
        add_action('admin_post_nopriv_mrb_guest_cancel', [$this, 'handleCancel']);

        /*
         * Automatically render frontend notices.
         *
         * Best practice is still to call:
         * \MRB\Front\ManageReservationHandler::renderFrontendNotice();
         *
         * directly inside your reservation page/template near the form.
         *
         * But these hooks make sure notices still appear if the template call is missing.
         */
        add_action('wp_body_open', [self::class, 'renderFrontendNotice'], 5);
        add_action('wp_footer', [self::class, 'renderFrontendNotice'], 5);
    }

    /*
    |--------------------------------------------------------------------------
    | HANDLE UPDATE
    |--------------------------------------------------------------------------
    */

    public function handleUpdate(): void
    {
        $this->ensurePostRequest();

        $token = $this->getPostedToken();

        if ($token === '') {
            $this->fail(
                'Invalid request',
                'missing_token',
                'Missing reservation token.'
            );
        }

        if (!$this->verifyNonce('mrb_guest_update_' . $token)) {
            $this->fail(
                'Security check failed',
                'security',
                'Nonce verification failed.',
                [
                    'token' => $token,
                    'post'  => $this->getDebugPostData(),
                ],
                $token
            );
        }

        /*
         * Use unslashed POST data for the service.
         *
         * The ReservationService should validate and sanitize the fields it uses.
         */
        $postData = $this->getUnslashedPostData();

        /*
         * Expected return:
         * - true on success
         * - WP_Error on known failure
         * - false on unknown failure
         */
        $updated = $this->service->updateReservation($token, $postData);

        if (is_wp_error($updated)) {
            $this->fail(
                'Reservation update failed',
                $updated->get_error_code(),
                $updated->get_error_message(),
                [
                    'token'      => $token,
                    'post'       => $this->getDebugPostData(),
                    'error_data' => $updated->get_error_data(),
                ],
                $token
            );
        }

        if ($updated !== true) {
            $this->fail(
                'Reservation update failed',
                'update_failed',
                'Reservation update failed.',
                [
                    'token' => $token,
                    'post'  => $this->getDebugPostData(),
                ],
                $token
            );
        }

        /*
         * Fetch updated reservation for email notification.
         */
        $reservation = $this->getReservationByToken($token);

        /*
         * Send email notifications after successful update.
         *
         * Email failures are logged but do not block the user.
         */
        if (is_array($reservation) && !empty($reservation)) {
            $this->sendUpdatedEmailsSafely($reservation);
        }

        $this->redirectToReservation($token, ['updated' => '1']);
    }

    /*
    |--------------------------------------------------------------------------
    | HANDLE CANCEL
    |--------------------------------------------------------------------------
    */

    public function handleCancel(): void
    {
        $this->ensurePostRequest();

        $token = $this->getPostedToken();

        if ($token === '') {
            $this->fail(
                'Invalid request',
                'missing_token',
                'Missing reservation token.'
            );
        }

        if (!$this->verifyNonce('mrb_guest_cancel_' . $token)) {
            $this->fail(
                'Security check failed',
                'security',
                'Nonce verification failed.',
                [
                    'token' => $token,
                    'post'  => $this->getDebugPostData(),
                ],
                $token
            );
        }

        /*
         * Fetch reservation before cancellation.
         *
         * Useful for emails because some cancellation logic may change the record.
         */
        $reservation = $this->getReservationByToken($token);

        /*
         * Expected return:
         * - true on success
         * - WP_Error on known failure
         * - false on unknown failure
         */
        $cancelled = $this->service->cancelByToken($token);

        if (is_wp_error($cancelled)) {
            $this->fail(
                'Reservation cancellation failed',
                $cancelled->get_error_code(),
                $cancelled->get_error_message(),
                [
                    'token'       => $token,
                    'post'        => $this->getDebugPostData(),
                    'reservation' => $reservation,
                    'error_data'  => $cancelled->get_error_data(),
                ],
                $token
            );
        }

        if ($cancelled !== true) {
            $this->fail(
                'Reservation cancellation failed',
                'cancel_failed',
                'Reservation cancellation failed.',
                [
                    'token'       => $token,
                    'post'        => $this->getDebugPostData(),
                    'reservation' => $reservation,
                ],
                $token
            );
        }

        /*
         * Send email notifications after successful cancellation.
         */
        if (is_array($reservation) && !empty($reservation)) {
            $reservation['status'] = 'cancelled';

            $this->sendCancelledEmailsSafely($reservation);
        }

        $this->redirectToReservation($token, ['cancelled' => '1']);
    }

    /*
    |--------------------------------------------------------------------------
    | REQUEST HELPERS
    |--------------------------------------------------------------------------
    */

    private function ensurePostRequest(): void
    {
        $method = isset($_SERVER['REQUEST_METHOD'])
            ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD']))
            : '';

        if (strtoupper($method) !== 'POST') {
            $this->fail(
                'Invalid request',
                'invalid_request_method',
                'Invalid request method.'
            );
        }
    }

    private function getPostedToken(): string
    {
        if (empty($_POST['token'])) {
            return '';
        }

        return sanitize_text_field(wp_unslash($_POST['token']));
    }

    private function verifyNonce(string $action): bool
    {
        if (empty($_POST['mrb_nonce'])) {
            return false;
        }

        $nonce = sanitize_text_field(wp_unslash($_POST['mrb_nonce']));

        return (bool) wp_verify_nonce($nonce, $action);
    }

    /*
    |--------------------------------------------------------------------------
    | GET RESERVATION BY TOKEN
    |--------------------------------------------------------------------------
    */

    private function getReservationByToken(string $token): ?array
    {
        $reservation = null;

        if (method_exists($this->service, 'findByToken')) {
            $reservation = $this->service->findByToken($token);
        } elseif (method_exists($this->service, 'getByToken')) {
            $reservation = $this->service->getByToken($token);
        }

        if (is_wp_error($reservation)) {
            error_log('[MRB] Failed to fetch reservation by token: ' . $reservation->get_error_message());
            return null;
        }

        return is_array($reservation) && !empty($reservation)
            ? $reservation
            : null;
    }

    /*
    |--------------------------------------------------------------------------
    | EMAIL HELPERS
    |--------------------------------------------------------------------------
    */

    private function sendUpdatedEmailsSafely(array $reservation): void
    {
        try {
            $emailService = new EmailNotificationService();

            if (method_exists($emailService, 'sendReservationUpdatedEmails')) {
                $emailService->sendReservationUpdatedEmails($reservation);
            }
        } catch (\Throwable $e) {
            /*
             * Do not block successful update because of mail failure.
             */
            error_log('[MRB] Failed to send reservation updated emails: ' . $e->getMessage());
        }
    }

    private function sendCancelledEmailsSafely(array $reservation): void
    {
        try {
            $emailService = new EmailNotificationService();

            /*
             * Preferred explicit cancellation email method.
             */
            if (method_exists($emailService, 'sendReservationCancelledEmails')) {
                $emailService->sendReservationCancelledEmails($reservation);
                return;
            }

            /*
             * Fallback for your finalized email architecture.
             *
             * Your current architecture expects:
             * - sendReservationUpdatedEmails(array $reservation)
             * - sendReservationStatusChangedEmails(array $reservation)
             */
            if (method_exists($emailService, 'sendReservationStatusChangedEmails')) {
                $emailService->sendReservationStatusChangedEmails($reservation);
            }
        } catch (\Throwable $e) {
            /*
             * Do not block successful cancellation because of mail failure.
             */
            error_log('[MRB] Failed to send reservation cancelled/status emails: ' . $e->getMessage());
        }
    }

    /*
    |--------------------------------------------------------------------------
    | POST DATA HELPERS
    |--------------------------------------------------------------------------
    */

    private function getUnslashedPostData(): array
    {
        return isset($_POST) && is_array($_POST)
            ? wp_unslash($_POST)
            : [];
    }

    /**
     * Debug-safe POST data.
     *
     * This removes nonce-like fields before showing debug output.
     * Debug output is disabled in production anyway.
     */
    private function getDebugPostData(): array
    {
        $postData = $this->getUnslashedPostData();

        unset(
            $postData['mrb_nonce'],
            $postData['_wpnonce'],
            $postData['_wp_http_referer']
        );

        return $postData;
    }

    /*
    |--------------------------------------------------------------------------
    | ERROR HANDLING
    |--------------------------------------------------------------------------
    */

    private function fail(
        string $title,
        string $errorCode,
        string $errorMessage,
        array $debugData = [],
        string $token = ''
    ): void {
        $errorCode = sanitize_key($errorCode);

        if ($this->debugMode) {
            $this->debugDie($title, $errorCode, $errorMessage, $debugData);
        }

        /*
         * Production-safe redirect.
         */
        if ($token !== '') {
            $this->redirectToReservation($token, ['error' => $errorCode]);
        }

        wp_safe_redirect(add_query_arg(
            ['error' => $errorCode],
            home_url('/')
        ));
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | REDIRECT HELPER
    |--------------------------------------------------------------------------
    */

    private function redirectToReservation(string $token, array $queryArgs = []): void
    {
        $token = sanitize_text_field($token);

        $url = home_url('/reservation/' . rawurlencode($token) . '/');

        if (!empty($queryArgs)) {
            $url = add_query_arg($queryArgs, $url);
        }

        wp_safe_redirect($url);
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | DEBUG ERROR PAGE
    |--------------------------------------------------------------------------
    |
    | Development only.
    |
    | In production:
    | private bool $debugMode = false;
    |
    */

    private function debugDie(
        string $title,
        string $errorCode,
        string $errorMessage,
        array $debugData = []
    ): void {
        $html  = '<div style="font-family:Arial,sans-serif;max-width:1000px;margin:30px auto;padding:24px;border:1px solid #ddd;border-radius:12px;background:#fff;">';
        $html .= '<h1 style="margin-top:0;color:#b91c1c;">' . esc_html($title) . '</h1>';

        $html .= '<table style="width:100%;border-collapse:collapse;margin:20px 0;">';

        $html .= '<tr>';
        $html .= '<th style="text-align:left;border:1px solid #ddd;padding:10px;background:#f8fafc;width:180px;">Error Code</th>';
        $html .= '<td style="border:1px solid #ddd;padding:10px;"><code>' . esc_html($errorCode) . '</code></td>';
        $html .= '</tr>';

        $html .= '<tr>';
        $html .= '<th style="text-align:left;border:1px solid #ddd;padding:10px;background:#f8fafc;">Message</th>';
        $html .= '<td style="border:1px solid #ddd;padding:10px;">' . esc_html($errorMessage) . '</td>';
        $html .= '</tr>';

        $html .= '</table>';

        if (!empty($debugData)) {
            $html .= '<h2>Debug Data</h2>';
            $html .= '<pre style="background:#0f172a;color:#e5e7eb;padding:16px;border-radius:8px;overflow:auto;white-space:pre-wrap;">';
            $html .= esc_html(print_r($debugData, true));
            $html .= '</pre>';
        }

        $html .= '<p style="margin-top:20px;color:#64748b;">';
        $html .= 'This debug screen is intended for development only. Before production, set <code>$debugMode</code> to <code>false</code>.';
        $html .= '</p>';

        $html .= '</div>';

        wp_die($html);
    }

    /*
    |--------------------------------------------------------------------------
    | FRONTEND USER NOTICE
    |--------------------------------------------------------------------------
    */

    public static function renderFrontendNotice(): void
    {
        /*
         * Do not render in wp-admin.
         */
        if (is_admin()) {
            return;
        }

        /*
         * Prevent duplicate rendering.
         *
         * Example:
         * - once from wp_body_open
         * - once from wp_footer
         * - once manually from a template
         */
        if (self::$noticeRendered) {
            return;
        }

        $updated = isset($_GET['updated'])
            ? sanitize_text_field(wp_unslash($_GET['updated']))
            : '';

        $cancelled = isset($_GET['cancelled'])
            ? sanitize_text_field(wp_unslash($_GET['cancelled']))
            : '';

        $error = isset($_GET['error'])
            ? sanitize_key(wp_unslash($_GET['error']))
            : '';

        /*
         * If there is no notice query parameter, output nothing.
         */
        if ($updated !== '1' && $cancelled !== '1' && $error === '') {
            return;
        }

        self::$noticeRendered = true;

        /*
         * Small inline fallback CSS.
         *
         * You can move this to your frontend stylesheet later.
         */
        echo '<style>
            .mrb-user-notice-wrap {
                max-width: 1100px;
                margin: 20px auto;
                padding-left: 16px;
                padding-right: 16px;
                box-sizing: border-box;
                z-index: 9999;
                position: relative;
            }

            .mrb-user-notice {
                padding: 14px 16px;
                margin: 0 0 20px;
                border-radius: 8px;
                font-size: 15px;
                line-height: 1.5;
                font-weight: 500;
                box-sizing: border-box;
            }

            .mrb-user-notice-success {
                background: #ecfdf5;
                color: #065f46;
                border: 1px solid #a7f3d0;
            }

            .mrb-user-notice-error {
                background: #fef2f2;
                color: #991b1b;
                border: 1px solid #fecaca;
            }
        </style>';

        echo '<div class="mrb-user-notice-wrap">';

        if ($updated === '1') {
            echo '<div class="mrb-user-notice mrb-user-notice-success">';
            echo esc_html__('Your reservation has been updated successfully.', 'meeting-room-booking');
            echo '</div>';
            echo '</div>';
            return;
        }

        if ($cancelled === '1') {
            echo '<div class="mrb-user-notice mrb-user-notice-success">';
            echo esc_html__('Your reservation has been cancelled successfully.', 'meeting-room-booking');
            echo '</div>';
            echo '</div>';
            return;
        }

        if ($error !== '') {
            echo '<div class="mrb-user-notice mrb-user-notice-error">';
            echo esc_html(self::getFrontendErrorMessage($error));
            echo '</div>';
        }

        echo '</div>';
    }

    /*
    |--------------------------------------------------------------------------
    | FRONTEND ERROR MESSAGE
    |--------------------------------------------------------------------------
    */

    private static function getFrontendErrorMessage(string $error): string
    {
        $messages = [
            'security'               => __('Security check failed. Please try again.', 'meeting-room-booking'),
            'invalid_request'        => __('Invalid reservation request.', 'meeting-room-booking'),
            'invalid_request_method' => __('Invalid request method.', 'meeting-room-booking'),
            'missing_token'          => __('Missing reservation token.', 'meeting-room-booking'),

            'update_failed'          => __('Failed to update reservation. Please try again.', 'meeting-room-booking'),
            'cancel_failed'          => __('Failed to cancel reservation. Please try again.', 'meeting-room-booking'),

            'not_found'              => __('Reservation not found.', 'meeting-room-booking'),
            'reservation_not_found'  => __('Reservation not found.', 'meeting-room-booking'),

            'conflict'               => __('The selected time slot is no longer available.', 'meeting-room-booking'),
            'time_conflict'          => __('The selected time slot is no longer available.', 'meeting-room-booking'),

            'invalid_time_range'     => __('Start time must be earlier than end time.', 'meeting-room-booking'),
            'invalid_datetime'       => __('Meeting date or time format is invalid.', 'meeting-room-booking'),

            'max_duration_exceeded'  => __('The reservation duration exceeds the maximum allowed time.', 'meeting-room-booking'),
            'min_duration_not_met'   => __('The reservation duration is shorter than the minimum allowed time.', 'meeting-room-booking'),
            'invalid_duration'       => __('The selected reservation duration is invalid.', 'meeting-room-booking'),

            'missing_first_name'     => __('First name is missing.', 'meeting-room-booking'),
            'missing_last_name'      => __('Last name is missing.', 'meeting-room-booking'),
            'missing_email'          => __('Email address is missing.', 'meeting-room-booking'),
            'invalid_email'          => __('Email address is invalid.', 'meeting-room-booking'),
            'missing_mobile'         => __('Mobile number is missing.', 'meeting-room-booking'),

            'missing_meeting_title'  => __('Meeting title is missing.', 'meeting-room-booking'),
            'missing_meeting_date'   => __('Meeting date is missing.', 'meeting-room-booking'),
            'missing_start_time'     => __('Start time is missing.', 'meeting-room-booking'),
            'missing_end_time'       => __('End time is missing.', 'meeting-room-booking'),

            'database_update_failed' => __('Database update failed.', 'meeting-room-booking'),
            'wpdb_update_failed'     => __('Database update failed.', 'meeting-room-booking'),
        ];

        return $messages[$error] ?? __('Something went wrong. Please try again.', 'meeting-room-booking');
    }
}
