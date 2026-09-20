<?php

declare(strict_types=1);

namespace ABP\Infrastructure;

final class Activator
{
    public static function activate(): void
    {
        Database::migrate();
        Capabilities::install();
        if (get_option('abp_settings', null) === null) {
            add_option('abp_settings', Settings::defaults(), '', false);
        }
        flush_rewrite_rules(false);
    }

    public static function deactivate(): void
    {
        flush_rewrite_rules(false);
    }
}
