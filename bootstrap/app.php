<?php

use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\EnsureLegalConsentAccepted;
use App\Http\Middleware\EnsurePasswordChange;
use App\Http\Middleware\EnsureSystemLogsUnlocked;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\SetSecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->append(SetSecurityHeaders::class);
        $trustedProxies = env('TRUSTED_PROXIES');
        if (is_string($trustedProxies) && trim($trustedProxies) !== '') {
            $parsedTrustedProxies = trim($trustedProxies) === '*'
                ? '*'
                : array_values(array_filter(array_map('trim', explode(',', $trustedProxies))));
        } else {
            $parsedTrustedProxies = ['127.0.0.1', '::1'];
        }

        if ($parsedTrustedProxies === '*' || count($parsedTrustedProxies) > 0) {
            $middleware->trustProxies(
                at: $parsedTrustedProxies,
                headers: Request::HEADER_X_FORWARDED_FOR
                    | Request::HEADER_X_FORWARDED_HOST
                    | Request::HEADER_X_FORWARDED_PORT
                    | Request::HEADER_X_FORWARDED_PROTO
            );
        }

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'active' => EnsureActiveUser::class,
            'consent.accepted' => EnsureLegalConsentAccepted::class,
            'system_logs.unlocked' => EnsureSystemLogsUnlocked::class,
            'password.change.required' => EnsurePasswordChange::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
