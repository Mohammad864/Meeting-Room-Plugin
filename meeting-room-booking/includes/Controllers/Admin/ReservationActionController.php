<?php

namespace MRB\Controllers\Admin;

use MRB\Enums\ReservationStatus;
use MRB\Services\ReservationService;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles admin POST actions for reservations.
 *
 * Hooks:
 *   admin_post_mrb_admin_update_reservation → handleUpdate()
 *   admin_post_mrb_change_status            → handleStatusChange()
 *
 * Email notifications are handled inside ReservationService — not here.
 */
class ReservationActionController
{
    private ReservationService $service;

    public function __construct(ReservationService $service)
    {
        $this->service = $service;
    }

    /**
     * Handle admin reservation edit form submission.
     *
     * Hook: admin_post_mrb_admin_update_reservation
     */
    public function handleUpdate(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'meeting-room-booking'));
        }

        $id = isset($_POST['reservation_id']) ? absint($_POST['reservation_id']) : 0;

        if ($id <= 0) {
            wp_die(esc_html__('Invalid reservation ID.', 'meeting-room-booking'));
        }

        $nonce = isset($_POST['mrb_nonce'])
            ? sanitize_text_field(wp_unslash($_POST['mrb_nonce']))
            : '';

        if (!wp_verify_nonce($nonce, 'mrb_admin_edit_' . $id)) {
            wp_die(esc_html__('Security check failed.', 'meeting-room-booking'));
        }

        $redirectUrl = admin_url('admin.php?page=mrb-reservations&action=edit&id=' . $id);
        $result      = $this->service->adminEdit($id, wp_unslash($_POST));

        if (is_wp_error($result)) {
            error_log('[MRB] Admin update failed: ' . $result->get_error_message());

            wp_safe_redirect(add_query_arg([
                'mrb_error'     => 'update_failed',
                'mrb_error_msg' => rawurlencode($result->get_error_message()),
            ], $redirectUrl));
            exit;
        }

        if (!$result) {
            wp_safe_redirect(add_query_arg('mrb_error', 'update_failed', $redirectUrl));
            exit;
        }

        wp_safe_redirect(add_query_arg('mrb_message', 'updated', $redirectUrl));
        exit;
    }

    /**
     * Handle quick status change (approve / reject / pending).
     *
     * Hook: admin_post_mrb_change_status
     */
    public function handleStatusChange(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'meeting-room-booking'));
        }

        $id     = isset($_GET['id'])     ? absint($_GET['id'])           : 0;
        $status = isset($_GET['status']) ? sanitize_key($_GET['status']) : '';

        if ($id <= 0) {
            wp_die(esc_html__('Invalid reservation ID.', 'meeting-room-booking'));
        }

        if (!ReservationStatus::isAdminSettable($status)) {
            wp_die(esc_html__('Invalid reservation status.', 'meeting-room-booking'));
        }

        if (
            !isset($_GET['_wpnonce']) ||
            !wp_verify_nonce(
                sanitize_text_field(wp_unslash($_GET['_wpnonce'])),
                'mrb_change_status_' . $id
            )
        ) {
            wp_die(esc_html__('Security check failed.', 'meeting-room-booking'));
        }

        $redirectUrl = admin_url('admin.php?page=mrb-reservations');
        $result      = $this->service->changeStatus($id, $status);

        if (is_wp_error($result)) {
            error_log('[MRB] Status change failed: ' . $result->get_error_message());

            wp_safe_redirect(add_query_arg([
                'mrb_error'     => 'status_failed',
                'mrb_error_msg' => rawurlencode($result->get_error_message()),
            ], $redirectUrl));
            exit;
        }

        if (!is_array($result) || empty($result['success'])) {
            $message = is_array($result) && !empty($result['message'])
                ? $result['message']
                : __('Failed to update reservation status.', 'meeting-room-booking');

            error_log('[MRB] Status change failed: ' . $message);

            wp_safe_redirect(add_query_arg([
                'mrb_error'     => 'status_failed',
                'mrb_error_msg' => rawurlencode($message),
            ], $redirectUrl));
            exit;
        }

        $message = $result['message'] ?? __('Reservation status updated successfully.', 'meeting-room-booking');

        wp_safe_redirect(add_query_arg([
            'mrb_message' => 'status_updated',
            'mrb_msg'     => rawurlencode($message),
        ], $redirectUrl));
        exit;
    }
}
