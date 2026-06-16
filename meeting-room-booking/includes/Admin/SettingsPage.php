<?php

namespace MRB\Admin;

use MRB\Core\Activator;

if (!defined('ABSPATH')) {
    exit;
}

class SettingsPage
{
    public function register(): void
    {
        add_submenu_page(
            'mrb-reservations',
            'Meeting Room Settings',
            'Settings',
            'manage_options',
            'mrb-settings',
            [$this, 'render']
        );
    }

    public static function handleSave(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action.', 'meeting-room-booking'));
        }

        if (
            !isset($_POST['mrb_settings_nonce']) ||
            !wp_verify_nonce($_POST['mrb_settings_nonce'], 'mrb_save_settings')
        ) {
            wp_die(__('Security check failed.', 'meeting-room-booking'));
        }

        $oldCount = absint(get_option('mrb_number_of_rooms', 3));
        $newCount = isset($_POST['mrb_number_of_rooms'])
            ? max(1, absint($_POST['mrb_number_of_rooms']))
            : 3;

        update_option('mrb_number_of_rooms', $newCount);

        Activator::syncRoomsToConfiguredCount();

        $actualRoomCount = self::getActualRoomCount();

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

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this page.', 'meeting-room-booking'));
        }

        $numberOfRooms = absint(get_option('mrb_number_of_rooms', 3));

        ?>
        <div class="wrap">
            <h1>Meeting Room Settings</h1>

            <?php if (isset($_GET['settings-updated'])) : ?>
                <div class="notice notice-success is-dismissible">
                    <p>Settings saved successfully.</p>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['rooms-warning'])) : ?>
                <div class="notice notice-warning is-dismissible">
                    <p>
                        Settings were saved, but some extra rooms could not be deleted because they already have reservations.
                    </p>
                </div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('mrb_save_settings', 'mrb_settings_nonce'); ?>

                <input type="hidden" name="action" value="mrb_save_settings">

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="mrb_number_of_rooms">Number of Rooms</label>
                            </th>
                            <td>
                                <input
                                    type="number"
                                    id="mrb_number_of_rooms"
                                    name="mrb_number_of_rooms"
                                    value="<?php echo esc_attr($numberOfRooms); ?>"
                                    min="1"
                                    step="1"
                                    class="small-text"
                                >
                                <p class="description">
                                    This controls how many meeting rooms are available for booking.
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <?php submit_button('Save Settings'); ?>
            </form>
        </div>
        <?php
    }

    private static function getActualRoomCount(): int
    {
        global $wpdb;

        $roomsTable = $wpdb->prefix . 'mrb_rooms';

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$roomsTable}");
    }
}
