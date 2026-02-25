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
    'default_user_password' => env('DEFAULT_USER_PASSWORD'),
    'attachments_disk' => env('ATTACHMENTS_DISK', 'local'),
];
