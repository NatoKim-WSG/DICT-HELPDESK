<?php

return [
    'csp' => [
        'enabled_in_production' => true,
        'force' => env('SECURITY_FORCE_CSP', false),
        'directives' => [
            'default-src' => ["'self'"],
            'base-uri' => ["'self'"],
            'object-src' => ["'none'"],
            'frame-src' => ["'none'"],
            'frame-ancestors' => ["'none'"],
            'form-action' => ["'self'"],
            'img-src' => ["'self'", 'data:', 'blob:'],
            'media-src' => ["'self'", 'data:', 'blob:'],
            'font-src' => ["'self'", 'https://fonts.bunny.net', 'data:'],
            'style-src' => ["'self'", "'unsafe-inline'", 'https://fonts.bunny.net'],
            // Alpine.js expression evaluation relies on eval/new Function.
            'script-src' => ["'self'", "'unsafe-inline'", "'unsafe-eval'"],
            'connect-src' => ["'self'"],
            'manifest-src' => ["'self'"],
            'worker-src' => ["'self'", 'blob:'],
            'upgrade-insecure-requests' => [],
            'block-all-mixed-content' => [],
        ],
    ],
];
