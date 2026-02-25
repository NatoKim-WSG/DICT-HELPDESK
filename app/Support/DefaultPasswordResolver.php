<?php

namespace App\Support;

use RuntimeException;

class DefaultPasswordResolver
{
    public static function user(): string
    {
        return self::resolve('helpdesk.default_user_password', 'DEFAULT_USER_PASSWORD');
    }

    private static function resolve(string $configKey, string $envKey): string
    {
        $value = trim((string) config($configKey));

        if ($value === '') {
            throw new RuntimeException("Missing required configuration value [{$envKey}].");
        }

        return $value;
    }
}
