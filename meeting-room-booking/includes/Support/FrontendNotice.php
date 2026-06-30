<?php

namespace MRB\Support;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Renders frontend user-facing notices from query-string parameters.
 *
 * Replaces the duplicated notice rendering that existed in both
 * Plugin::renderFrontendNotice() and ManageReservationHandler::renderFrontendNotice().
 *
 * Supports both standard $_GET and custom /reservation/{token}/ routes where
 * WordPress's rewrite layer processes the URL via template_redirect.
 */
final class FrontendNotice
{
    private static bool $rendered = false;

    /**
     * Read query-string parameters and print the appropriate notice.
     *
     * Safe to call from wp_body_open, wp_footer, or directly inside a template —
     * the static $rendered flag prevents duplicate output.
     */
    public static function renderFromRequest(): void
    {
        if (is_admin() || self::$rendered) {
            return;
        }

        $params    = self::resolveQueryParams();
        $updated   = isset($params['updated'])   ? sanitize_text_field((string) $params['updated'])   : '';
        $cancelled = isset($params['cancelled']) ? sanitize_text_field((string) $params['cancelled']) : '';
        $error     = isset($params['error'])     ? sanitize_key((string) $params['error'])             : '';

        if ($updated !== '1' && $cancelled !== '1' && $error === '') {
            return;
        }

        self::$rendered = true;

        self::printStyles();

        echo '<div class="mrb-user-notice-wrap">';

        if ($updated === '1') {
            self::success(__('Your reservation has been updated successfully.', 'meeting-room-booking'));
        } elseif ($cancelled === '1') {
            self::success(__('Your reservation has been cancelled successfully.', 'meeting-room-booking'));
        } elseif ($error !== '') {
            self::error(ErrorMessages::get($error));
        }

        echo '</div>';
    }

    public static function success(string $message): void
    {
        echo '<div class="mrb-user-notice mrb-user-notice-success">' . esc_html($message) . '</div>';
    }

    public static function error(string $message): void
    {
        echo '<div class="mrb-user-notice mrb-user-notice-error">' . esc_html($message) . '</div>';
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Resolve query parameters.
     *
     * On the /reservation/{token}/ custom route WordPress processes the request
     * via template_redirect. In this context $_GET is always available (PHP
     * populates it from QUERY_STRING), but we add REQUEST_URI parsing as a
     * belt-and-suspenders fallback for edge-case server configurations.
     */
    private static function resolveQueryParams(): array
    {
        if (!empty($_GET)) {
            return (array) $_GET;
        }

        $queryString = '';

        if (!empty($_SERVER['REQUEST_URI'])) {
            $parsed = wp_parse_url(
                sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])),
                PHP_URL_QUERY
            );

            if (is_string($parsed)) {
                $queryString = $parsed;
            }
        }

        if ($queryString === '' && !empty($_SERVER['QUERY_STRING'])) {
            $queryString = sanitize_text_field(wp_unslash($_SERVER['QUERY_STRING']));
        }

        if ($queryString === '') {
            return [];
        }

        parse_str($queryString, $params);

        return is_array($params) ? $params : [];
    }

    private static function printStyles(): void
    {
        echo '<style>
            .mrb-user-notice-wrap{max-width:1100px;margin:20px auto;padding:0 16px;box-sizing:border-box;}
            .mrb-user-notice{padding:14px 16px;margin:0 0 20px;border-radius:8px;font-size:15px;line-height:1.5;font-weight:500;box-sizing:border-box;}
            .mrb-user-notice-success{background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0;}
            .mrb-user-notice-error{background:#fef2f2;color:#991b1b;border:1px solid #fecaca;}
        </style>';
    }
}
