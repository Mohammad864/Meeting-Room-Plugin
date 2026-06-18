<?php

namespace MRB\Core;

use MRB\Front\BookingFormShortcode;
use MRB\Admin\AdminPage;
use MRB\Admin\SettingsPage;
use MRB\Front\ManageReservationHandler;
use MRB\Database\ReservationRepository;
use MRB\Services\ReservationService;
use MRB\Admin\CalendarPage;

if (!defined('ABSPATH')) {
    exit;
}

class Plugin
{
    public function boot(): void
    {
        if (class_exists(Assets::class)) {
            (new Assets())->register();
        }

        /*
        |--------------------------------------------------------------------------
        | Admin actions
        |--------------------------------------------------------------------------
        */

        add_action('admin_post_mrb_admin_update_reservation', [AdminPage::class, 'handleAdminUpdate']);
        add_action('admin_post_mrb_change_status', [AdminPage::class, 'handleStatusChange']);
        add_action('admin_post_mrb_save_settings', [SettingsPage::class, 'handleSave']);

        /*
        |--------------------------------------------------------------------------
        | Calendar
        |--------------------------------------------------------------------------
        */

        add_action('admin_enqueue_scripts', [CalendarPage::class, 'enqueueAssets']);
        add_action('wp_ajax_mrb_calendar_events', [CalendarPage::class, 'getEvents']);

        /*
        |--------------------------------------------------------------------------
        | Booking form submission
        |--------------------------------------------------------------------------
        */

        add_action('admin_post_mrb_submit_booking', [BookingFormShortcode::class, 'handleSubmit']);
        add_action('admin_post_nopriv_mrb_submit_booking', [BookingFormShortcode::class, 'handleSubmit']);

        /*
        |--------------------------------------------------------------------------
        | Guest actions
        |--------------------------------------------------------------------------
        */

        add_action('admin_post_nopriv_mrb_guest_cancel', [$this, 'handleGuestCancel']);
        add_action('admin_post_mrb_guest_cancel', [$this, 'handleGuestCancel']);

        add_action('admin_post_nopriv_mrb_guest_update', [$this, 'handleGuestUpdate']);
        add_action('admin_post_mrb_guest_update', [$this, 'handleGuestUpdate']);

        /*
        |--------------------------------------------------------------------------
        | WordPress hooks
        |--------------------------------------------------------------------------
        */

        add_action('init', [$this, 'registerShortcodes']);
        add_action('admin_menu', [$this, 'registerAdminPages']);

        add_action('init', [$this, 'registerReservationEndpoint']);
        add_filter('query_vars', [$this, 'addQueryVars']);
        add_action('template_redirect', [$this, 'handleReservationRoute']);
    }

    public function registerShortcodes(): void
    {
        add_shortcode('mrb_booking_form', [BookingFormShortcode::class, 'render']);
    }

    public function registerAdminPages(): void
    {
        add_menu_page(
            'Meeting Bookings',
            'Meeting Bookings',
            'manage_options',
            'mrb-reservations',
            [AdminPage::class, 'render'],
            'dashicons-calendar-alt',
            26
        );

        add_submenu_page(
            'mrb-reservations',
            'Calendar View',
            'Calendar View',
            'manage_options',
            'mrb-calendar',
            [CalendarPage::class, 'render']
        );

        $settingsPage = new SettingsPage();
        $settingsPage->register();
    }

    public function registerReservationEndpoint(): void
    {
        add_rewrite_rule(
            '^reservation/([a-zA-Z0-9]+)/?',
            'index.php?mrb_token=$matches[1]',
            'top'
        );
    }

    public function addQueryVars(array $vars): array
    {
        $vars[] = 'mrb_token';

        /*
        |--------------------------------------------------------------------------
        | Notice-related query vars
        |--------------------------------------------------------------------------
        |
        | The frontend notice currently reads directly from REQUEST_URI / QUERY_STRING
        | because this route is manually rendered in template_redirect.
        | These are still registered for compatibility with WordPress query parsing.
        |
        */

        $vars[] = 'error';
        $vars[] = 'updated';
        $vars[] = 'cancelled';

        return $vars;
    }

    public function handleReservationRoute(): void
    {
        $token = get_query_var('mrb_token');

        if (!$token) {
            return;
        }

        $token = sanitize_text_field((string) $token);

        wp_enqueue_style('mrb-manage-reservation');

        $repository  = $this->makeRepository();
        $reservation = $repository->findByToken($token);

        if (!$reservation) {
            wp_die(esc_html__('Reservation not found.', 'meeting-room-booking'));
        }

        $this->renderReservationManagementPage($reservation, $token);
        exit;
    }

    public function handleGuestCancel(): void
    {
        $handler = $this->makeReservationHandler();
        $handler->handleCancel();
    }

    public function handleGuestUpdate(): void
    {
        $handler = $this->makeReservationHandler();
        $handler->handleUpdate();
    }

    private function makeRepository(): ReservationRepository
    {
        return new ReservationRepository();
    }

    private function makeReservationService(): ReservationService
    {
        return new ReservationService($this->makeRepository());
    }

    private function makeReservationHandler(): ManageReservationHandler
    {
        return new ManageReservationHandler($this->makeReservationService());
    }

    private function getFrontendQueryParams(): array
    {
        $queryString = '';

        /*
        |--------------------------------------------------------------------------
        | Primary source: REQUEST_URI
        |--------------------------------------------------------------------------
        |
        | On this custom /reservation/{token}/ route, WordPress may not expose
        | query parameters in $_GET because the page is rendered manually inside
        | template_redirect. Reading REQUEST_URI gives us the real URL query string.
        |
        */

        if (!empty($_SERVER['REQUEST_URI'])) {
            $requestUri = sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI']));
            $parsedQuery = wp_parse_url($requestUri, PHP_URL_QUERY);

            if (is_string($parsedQuery)) {
                $queryString = $parsedQuery;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Fallback source: QUERY_STRING
        |--------------------------------------------------------------------------
        */

        if ($queryString === '' && !empty($_SERVER['QUERY_STRING'])) {
            $queryString = sanitize_text_field(wp_unslash($_SERVER['QUERY_STRING']));
        }

        $query = [];

        if ($queryString !== '') {
            parse_str($queryString, $query);
        }

        return is_array($query) ? $query : [];
    }

    private function renderFrontendNotice(): void
    {
        $query = $this->getFrontendQueryParams();

        $error = isset($query['error'])
            ? sanitize_key((string) $query['error'])
            : '';

        $updated = isset($query['updated'])
            ? sanitize_text_field((string) $query['updated'])
            : '';

        $cancelled = isset($query['cancelled'])
            ? sanitize_text_field((string) $query['cancelled'])
            : '';

        if ($updated !== '1' && $cancelled !== '1' && $error === '') {
            return;
        }

        if ($updated === '1') {
            echo '<div class="mrb-user-notice mrb-user-notice-success" style="background:#d1e7dd;color:#0f5132;padding:15px;margin:20px 0;border:1px solid #badbcc;border-radius:4px;">';
            echo esc_html__('Your reservation has been updated successfully.', 'meeting-room-booking');
            echo '</div>';

            return;
        }

        if ($cancelled === '1') {
            echo '<div class="mrb-user-notice mrb-user-notice-success" style="background:#d1e7dd;color:#0f5132;padding:15px;margin:20px 0;border:1px solid #badbcc;border-radius:4px;">';
            echo esc_html__('Your reservation has been cancelled successfully.', 'meeting-room-booking');
            echo '</div>';

            return;
        }

        if ($error !== '') {
            echo '<div class="mrb-user-notice mrb-user-notice-error" style="background:#f8d7da;color:#842029;padding:15px;margin:20px 0;border:1px solid #f5c2c7;border-radius:4px;">';
            echo esc_html($this->getFrontendErrorMessage($error));
            echo '</div>';
        }
    }

    private function getFrontendErrorMessage(string $error): string
    {
        $messages = [
            'conflict' => __(
                'The selected time conflicts with another reservation. Please choose a different time.',
                'meeting-room-booking'
            ),

            'time_conflict' => __(
                'The selected time conflicts with another reservation. Please choose a different time.',
                'meeting-room-booking'
            ),

            'not_found' => __(
                'Reservation not found or the management link is invalid.',
                'meeting-room-booking'
            ),

            'reservation_not_found' => __(
                'Reservation not found or the management link is invalid.',
                'meeting-room-booking'
            ),

            'security' => __(
                'Security validation failed. Please refresh the page and try again.',
                'meeting-room-booking'
            ),

            'invalid_request' => __(
                'Invalid request. Please review your input and try again.',
                'meeting-room-booking'
            ),

            'invalid_request_method' => __(
                'Invalid request method. Please try again.',
                'meeting-room-booking'
            ),

            'missing_token' => __(
                'The reservation token is missing. Please use the link from your confirmation email.',
                'meeting-room-booking'
            ),

            'update_failed' => __(
                'We could not update your reservation. Please try again.',
                'meeting-room-booking'
            ),

            'cancel_failed' => __(
                'We could not cancel your reservation. Please try again.',
                'meeting-room-booking'
            ),

            'max_duration_exceeded' => __(
                'The reservation duration exceeds the maximum allowed time.',
                'meeting-room-booking'
            ),

            'min_duration_not_met' => __(
                'The reservation duration is shorter than the minimum allowed time.',
                'meeting-room-booking'
            ),

            'invalid_duration' => __(
                'The selected reservation duration is invalid.',
                'meeting-room-booking'
            ),

            'invalid_time_range' => __(
                'The selected start and end time are invalid.',
                'meeting-room-booking'
            ),

            'invalid_datetime' => __(
                'The selected reservation date or time is invalid.',
                'meeting-room-booking'
            ),

            'past_meeting_date' => __(
                'The meeting date cannot be in the past.',
                'meeting-room-booking'
            ),

            'past_meeting_time' => __(
                'The meeting time cannot be in the past.',
                'meeting-room-booking'
            ),

            'missing_first_name' => __(
                'Please enter your first name.',
                'meeting-room-booking'
            ),

            'missing_last_name' => __(
                'Please enter your last name.',
                'meeting-room-booking'
            ),

            'missing_email' => __(
                'Please enter your email address.',
                'meeting-room-booking'
            ),

            'invalid_email' => __(
                'Please enter a valid email address.',
                'meeting-room-booking'
            ),

            'missing_mobile' => __(
                'Please enter your mobile number.',
                'meeting-room-booking'
            ),

            'missing_meeting_title' => __(
                'Please enter a meeting title.',
                'meeting-room-booking'
            ),

            'missing_meeting_date' => __(
                'Please select a meeting date.',
                'meeting-room-booking'
            ),

            'missing_start_time' => __(
                'Please select a start time.',
                'meeting-room-booking'
            ),

            'missing_end_time' => __(
                'Please select an end time.',
                'meeting-room-booking'
            ),

            'database_error' => __(
                'A database error occurred. Please try again.',
                'meeting-room-booking'
            ),
        ];

        return $messages[$error] ?? __(
            'Something went wrong. Please try again.',
            'meeting-room-booking'
        );
    }

    private function renderStatusBadge(string $status): string
    {
        $status = sanitize_html_class($status ?: 'unknown');

        return sprintf(
            '<span class="mrb-status-badge mrb-status-%1$s">%2$s</span>',
            esc_attr($status),
            esc_html(ucfirst($status))
        );
    }

    private function renderReservationManagementPage(array $reservation, string $token): void
    {
        $status    = isset($reservation['status']) ? (string) $reservation['status'] : '';
        $canManage = !in_array($status, ['cancelled', 'rejected'], true);

        get_header();

        ?>
        <main class="mrb-container">
            <?php $this->renderFrontendNotice(); ?>

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
                            <?php echo wp_kses_post($this->renderStatusBadge($status)); ?>
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
        <?php

        get_footer();
    }
}
