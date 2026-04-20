<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Branding
    |--------------------------------------------------------------------------
    |
    | These values keep user-facing product copy aligned while still allowing
    | the support organization and support department to be configured.
    |
    */
    'support_department' => env('HELPDESK_SUPPORT_DEPARTMENT', 'iOne'),
    'support_brand_name' => env('HELPDESK_SUPPORT_BRAND_NAME', 'iOne'),
    'support_organization_name' => env('HELPDESK_SUPPORT_ORGANIZATION_NAME', 'iOne Resources Inc.'),
    'support_team_name' => env('HELPDESK_SUPPORT_TEAM_NAME', 'iOne Technical Team'),
    'support_logo_path' => env('HELPDESK_SUPPORT_LOGO_PATH', 'images/iOne Logo.png'),

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
    'seed_client_credentials_path' => env('SEED_CLIENT_CREDENTIALS_PATH', 'seeded-client-credentials'),
    'ticket_import_disk' => env('TICKET_IMPORT_DISK', 'local'),
    'ticket_import_path' => env('TICKET_IMPORT_PATH', 'imports'),
    'ticket_import_timezone' => env('TICKET_IMPORT_TIMEZONE', env('APP_TIMEZONE', 'UTC')),
    'attachments_disk' => env('ATTACHMENTS_DISK', 'local'),
    'cleanup' => [
        'backup_path' => env('HELPDESK_BACKUP_PATH', 'backups'),
        'backup_retention_days' => (int) env('HELPDESK_BACKUP_RETENTION_DAYS', 30),
        'seeded_client_credentials_path' => env('HELPDESK_SEEDED_CLIENT_CREDENTIALS_PATH', env('SEED_CLIENT_CREDENTIALS_PATH', 'seeded-client-credentials')),
        'seeded_client_credentials_retention_days' => (int) env('HELPDESK_SEEDED_CLIENT_CREDENTIAL_RETENTION_DAYS', 14),
        'import_path' => env('HELPDESK_IMPORT_RETENTION_PATH', env('TICKET_IMPORT_PATH', 'imports')),
        'import_retention_days' => (int) env('HELPDESK_IMPORT_RETENTION_DAYS', 7),
    ],
];
