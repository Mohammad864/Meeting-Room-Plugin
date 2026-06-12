<?php 

namespace MRB\Core;

if (!defined('ABSPATH')) {
    exit;
}

class Assets
{
    public function register(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'registerStyles']);
    }

    public function registerStyles(): void
    {
        wp_register_style(
            'mrb-booking-form',
            MRB_PLUGIN_URL . 'assets/css/booking-form.css',
            [],
            file_exists(MRB_PLUGIN_DIR . 'assets/css/booking-form.css')
                ? filemtime(MRB_PLUGIN_DIR . 'assets/css/booking-form.css')
                : MRB_VERSION
        );
    }
}