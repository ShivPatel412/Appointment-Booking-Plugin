<?php

declare(strict_types=1);

namespace ABP\Infrastructure;

final class Capabilities
{
    public const MANAGE = 'abp_manage_appointments';
    public const VIEW = 'abp_view_appointments';
    public const DELETE = 'abp_delete_appointments';

    public static function install(): void
    {
        $admin = get_role('administrator');
        if (! $admin) {
            return;
        }
        foreach (array(self::MANAGE, self::VIEW, self::DELETE) as $capability) {
            $admin->add_cap($capability);
        }
    }
}
