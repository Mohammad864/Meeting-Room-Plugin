<?php

namespace MRB\Core;

use MRB\Front\BookingFormShortcode;
use MRB\Admin\AdminPage;

if (!defined('ABSPATH')) {
    exit;
}

class Plugin
{
    public function boot(): void
    {
        add_action('init', [$this, 'registerShortcodes']);
        add_action('admin_menu', [$this, 'registerAdminPages']);
        add_action('admin_post_mrb_submit_booking', [BookingFormShortcode::class, 'handleSubmit']);
        add_action('admin_post_nopriv_mrb_submit_booking', [BookingFormShortcode::class, 'handleSubmit']);
        add_action('admin_post_mrb_change_status', [AdminPage::class, 'handleStatusChange']);
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
    }
}
