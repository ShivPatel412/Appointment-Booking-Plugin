<?php

declare(strict_types=1);

namespace ABP;

final class Autoloader
{
    public static function register(): void
    {
        spl_autoload_register(static function (string $class): void {
            if (strncmp($class, 'ABP\\', 4) !== 0) {
                return;
            }

            $relative = str_replace('\\', DIRECTORY_SEPARATOR, substr($class, 4));
            $file = ABP_PATH . 'src/' . $relative . '.php';
            if (is_readable($file)) {
                require_once $file;
            }
        });
    }
}
