<?php

namespace MRB\Core;

if (!defined('ABSPATH')) {
    exit;
}

class Assets
{
    /**
     * Register WordPress hooks for frontend and admin assets.
     */
    public function register(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'registerFrontendAssets']);
        add_action('admin_enqueue_scripts', [$this, 'registerAdminAssets']);
    }

    /**
     * Register frontend CSS/JS assets.
     *
     * These assets are available on public site pages.
     */
    public function registerFrontendAssets(): void
    {
        wp_register_style(
            'mrb-booking-form',
            MRB_PLUGIN_URL . 'assets/css/booking-form.css',
            [],
            $this->getAssetVersion('assets/css/booking-form.css')
        );

        wp_register_style(
            'mrb-manage-reservation',
            MRB_PLUGIN_URL . 'assets/css/mrb-manage-reservation.css',
            [],
            $this->getAssetVersion('assets/css/mrb-manage-reservation.css')
        );
    }

    /**
     * Register admin CSS/JS assets.
     *
     * These assets are available inside wp-admin.
     */
    public function registerAdminAssets(): void
    {
        wp_register_style(
            'mrb-admin-dashboard',
            MRB_PLUGIN_URL . 'assets/css/mrb-admin-dashboard.css',
            [],
            $this->getAssetVersion('assets/css/mrb-admin-dashboard.css')
        );

        wp_register_style(
            'mrb-admin-calendar',
            MRB_PLUGIN_URL . 'assets/css/mrb-admin-calendar.css',
            ['mrb-admin-dashboard'],
            $this->getAssetVersion('assets/css/mrb-admin-calendar.css')
        );
    }

    /**
     * Get asset version.
     *
     * Uses filemtime during development so CSS updates are not cached.
     * Falls back to MRB_VERSION if the file does not exist.
     */
    private function getAssetVersion(string $relativePath): string
    {
        $filePath = trailingslashit(MRB_PLUGIN_DIR) . ltrim($relativePath, '/');

        if (file_exists($filePath)) {
            return (string) filemtime($filePath);
        }

        return MRB_VERSION;
    }
}
