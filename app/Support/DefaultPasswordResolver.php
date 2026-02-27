<?php

namespace App\Support;

class DefaultPasswordResolver
{
    private const DEFAULT_USER_PASSWORD = 'i0n3i0n3';

    public static function user(): string
    {
        $configuredPassword = trim((string) config('helpdesk.default_user_password'));

        return $configuredPassword !== ''
            ? $configuredPassword
            : self::DEFAULT_USER_PASSWORD;
    }
}
