<?php

namespace MRB\Support;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Minimal template renderer.
 *
 * Templates live in meeting-room-booking/views/{template}.php.
 * Variables in $data are extracted into the template scope.
 */
final class View
{
    /**
     * Render a template and return the output as a string.
     *
     * @param string $template Relative path from views/ without .php extension.
     *                         e.g. 'admin/reservation-list'
     * @param array  $data     Variables to make available in the template.
     */
    public static function render(string $template, array $data = []): string
    {
        $file = MRB_PLUGIN_DIR . 'views/' . ltrim($template, '/') . '.php';

        if (!file_exists($file)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                return '<!-- [MRB] View not found: ' . esc_html($template) . ' -->';
            }
            return '';
        }

        // Prevent leaking superglobals into templates.
        unset($data['_SERVER'], $data['_POST'], $data['_GET'], $data['_COOKIE'], $data['_SESSION']);

        extract($data, EXTR_SKIP);

        ob_start();
        include $file;
        return (string) ob_get_clean();
    }

    /**
     * Render a template and echo the result immediately.
     */
    public static function output(string $template, array $data = []): void
    {
        echo self::render($template, $data); // phpcs:ignore WordPress.Security.EscapeOutput
    }
}
