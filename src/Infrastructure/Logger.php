<?php

declare(strict_types=1);

namespace ABP\Infrastructure;

final class Logger
{
    public static function error(string $message, array $context = array()): void
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[ABP] ' . $message . ' ' . wp_json_encode($context)); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
        }
        do_action('abp_log_error', $message, $context);
    }
}
