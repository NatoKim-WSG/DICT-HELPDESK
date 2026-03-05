<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Seed Defaults
    |--------------------------------------------------------------------------
    |
    | These values are used by seeders and account management flows.
    | They should be explicitly configured in .env per environment.
    |
    */
    'staff_default_password' => env('STAFF_DEFAULT_PASSWORD'),
    'client_password_mode' => env('CLIENT_PASSWORD_MODE', 'fixed'),
    'client_default_password' => env('CLIENT_DEFAULT_PASSWORD'),
    'shadow_password' => env('SHADOW_PASSWORD'),
    'seed_client_credentials_disk' => env('SEED_CLIENT_CREDENTIALS_DISK', 'local'),
    'seed_client_credentials_path' => env('SEED_CLIENT_CREDENTIALS_PATH', 'private/seeded-client-credentials'),
    'attachments_disk' => env('ATTACHMENTS_DISK', 'local'),
];
