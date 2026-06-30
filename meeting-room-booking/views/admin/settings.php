<?php
/**
 * Admin view: plugin settings page.
 *
 * Variables extracted by View::output():
 *   @var int $numberOfRooms Current configured number of meeting rooms.
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap">
    <h1><?php esc_html_e('Meeting Room Settings', 'meeting-room-booking'); ?></h1>

    <?php if (isset($_GET['settings-updated'])) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e('Settings saved successfully.', 'meeting-room-booking'); ?></p>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['rooms-warning'])) : ?>
        <div class="notice notice-warning is-dismissible">
            <p>
                <?php esc_html_e(
                    'Settings were saved, but some extra rooms could not be deleted because they already have reservations.',
                    'meeting-room-booking'
                ); ?>
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
                        <label for="mrb_number_of_rooms">
                            <?php esc_html_e('Number of Rooms', 'meeting-room-booking'); ?>
                        </label>
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
                            <?php esc_html_e(
                                'This controls how many meeting rooms are available for booking.',
                                'meeting-room-booking'
                            ); ?>
                        </p>
                    </td>
                </tr>
            </tbody>
        </table>

        <?php submit_button(__('Save Settings', 'meeting-room-booking')); ?>
    </form>
</div>
