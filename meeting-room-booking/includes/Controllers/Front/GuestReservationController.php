<?php

namespace MRB\Controllers\Front;

use MRB\Services\ReservationService;
use MRB\Support\ErrorMessages;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles guest POST actions: update and cancel a reservation.
 *
 * No self-registration: hooks are registered in Plugin::boot().
 * Email notifications are handled inside ReservationService.
 */
class GuestReservationController
{
    private ReservationService $service;
    private bool $debugMode = false;

    public function __construct(ReservationService $service)
    {
        $this->service = $service;
    }

    public function handleUpdate(): void
    {
        $this->ensurePostRequest();

        $token = $this->getPostedToken();

        if ($token === '') {
            $this->fail('missing_token');
        }

        if (!$this->verifyNonce('mrb_guest_update_' . $token)) {
            $this->fail('security', $token);
        }

        $result = $this->service->updateReservation($token, $this->getUnslashedPostData());

        if (is_wp_error($result)) {
            $this->fail($result->get_error_code(), $token, $result->get_error_message());
        }

        if ($result !== true) {
            $this->fail('update_failed', $token);
        }

        $this->redirectToReservation($token, ['updated' => '1']);
    }

    public function handleCancel(): void
    {
        $this->ensurePostRequest();

        $token = $this->getPostedToken();

        if ($token === '') {
            $this->fail('missing_token');
        }

        if (!$this->verifyNonce('mrb_guest_cancel_' . $token)) {
            $this->fail('security', $token);
        }

        $result = $this->service->cancelByToken($token);

        if (is_wp_error($result)) {
            $this->fail($result->get_error_code(), $token, $result->get_error_message());
        }

        if ($result !== true) {
            $this->fail('cancel_failed', $token);
        }

        $this->redirectToReservation($token, ['cancelled' => '1']);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function ensurePostRequest(): void
    {
        $method = isset($_SERVER['REQUEST_METHOD'])
            ? strtoupper(sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])))
            : '';

        if ($method !== 'POST') {
            $this->fail('invalid_request_method');
        }
    }

    private function getPostedToken(): string
    {
        return empty($_POST['token'])
            ? ''
            : sanitize_text_field(wp_unslash($_POST['token']));
    }

    private function verifyNonce(string $action): bool
    {
        if (empty($_POST['mrb_nonce'])) {
            return false;
        }

        return (bool) wp_verify_nonce(
            sanitize_text_field(wp_unslash($_POST['mrb_nonce'])),
            $action
        );
    }

    private function getUnslashedPostData(): array
    {
        return isset($_POST) && is_array($_POST) ? wp_unslash($_POST) : [];
    }

    private function fail(
        string $errorCode,
        string $token = '',
        string $message = ''
    ): void {
        $errorCode = sanitize_key($errorCode);

        error_log(sprintf(
            '[MRB] Guest action failed — code: %s, message: %s',
            $errorCode,
            $message ?: ErrorMessages::get($errorCode)
        ));

        if ($this->debugMode) {
            wp_die('<h1>Guest Action Failed</h1><p>Code: ' . esc_html($errorCode) . '</p><p>' . esc_html($message) . '</p>');
        }

        if ($token !== '') {
            $this->redirectToReservation($token, ['error' => $errorCode]);
        }

        wp_safe_redirect(add_query_arg(['error' => $errorCode], home_url('/')));
        exit;
    }

    private function redirectToReservation(string $token, array $queryArgs = []): void
    {
        $url = home_url('/reservation/' . rawurlencode(sanitize_text_field($token)) . '/');

        if (!empty($queryArgs)) {
            $url = add_query_arg($queryArgs, $url);
        }

        wp_safe_redirect($url);
        exit;
    }
}
