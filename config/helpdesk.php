<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Seed Defaults
    |--------------------------------------------------------------------------
    |
    | These values are used for local/dev seed data and fallback migration logic.
    | Override them in .env for your environment.
    |
    */
    'default_user_password' => env('DEFAULT_USER_PASSWORD', 'i0n3R3s0urc3s!'),
    // Keep compatibility with older env files that used DEFAULT_DEVELOPER_PASSWORD.
    'default_shadow_password' => env('DEFAULT_SHADOW_PASSWORD', env('DEFAULT_DEVELOPER_PASSWORD', 'Qwerasd0.')),
];
