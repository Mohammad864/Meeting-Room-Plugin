<?php

namespace MRB\Controllers\Admin;

use MRB\Core\Activator;
use MRB\Support\View;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles the "Settings" admin submenu page.
 */
class SettingsController
{
    /**
     * Register the submenu page with WordPress.
     *
     * Call this from Plugin::registerAdminPages() or a container boot method.
     */
    public function register(): void
    {
        add_submenu_page(
            'mrb-reservations',
            __('Meeting Room Settings', 'meeting-room-booking'),
            __('Settings', 'meeting-room-booking'),
            'manage_options',
            'mrb-settings',
            [$this, 'show']
        );
    }

    /**
     * WordPress menu page callback.
     */
    public function show(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'meeting-room-booking'));
        }

        $numberOfRooms = absint(get_option('mrb_number_of_rooms', 3));

        View::output('admin/settings', ['numberOfRooms' => $numberOfRooms]);
    }

    /**
     * Handle settings form submission.
     *
     * Hook: admin_post_mrb_save_settings
     */
    public function handleSave(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'meeting-room-booking'));
        }

        if (
            !isset($_POST['mrb_settings_nonce']) ||
            !wp_verify_nonce($_POST['mrb_settings_nonce'], 'mrb_save_settings')
        ) {
            wp_die(esc_html__('Security check failed.', 'meeting-room-booking'));
        }

        $oldCount = absint(get_option('mrb_number_of_rooms', 3));
        $newCount = isset($_POST['mrb_number_of_rooms'])
            ? max(1, absint($_POST['mrb_number_of_rooms']))
            : 3;

        update_option('mrb_number_of_rooms', $newCount);

        Activator::syncRoomsToConfiguredCount();

        $actualRoomCount = $this->getActualRoomCount();

        $redirectUrl = add_query_arg(
            [
                'page'             => 'mrb-settings',
                'settings-updated' => '1',
            ],
            admin_url('admin.php')
        );

        if ($newCount < $oldCount && $actualRoomCount > $newCount) {
            $redirectUrl = add_query_arg('rooms-warning', '1', $redirectUrl);
        }

        wp_safe_redirect($redirectUrl);
        exit;
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function getActualRoomCount(): int
    {
        global $wpdb;

        $roomsTable = $wpdb->prefix . 'mrb_rooms';

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$roomsTable}");
    }
}
