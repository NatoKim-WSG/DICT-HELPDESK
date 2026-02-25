<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        if (app()->environment('production')) {
            $response->headers->set(
                'Content-Security-Policy',
                "default-src 'self'; ".
                "base-uri 'self'; ".
                "frame-ancestors 'none'; ".
                "form-action 'self'; ".
                "img-src 'self' data: blob:; ".
                "font-src 'self' https://fonts.bunny.net data:; ".
                "style-src 'self' 'unsafe-inline' https://fonts.bunny.net; ".
                "script-src 'self' 'unsafe-inline'; ".
                "connect-src 'self';"
            );
        }

        return $response;
    }
}
