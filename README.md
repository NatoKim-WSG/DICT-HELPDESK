# DICT Helpdesk Ticketing System

Laravel 11 help desk application with separate client and admin portals for ticket management.

## Tech Stack

- PHP 8.2+
- Laravel 11
- PostgreSQL (default) or MySQL/MariaDB
- Vite + Tailwind CSS

## Environment Notes

- Enable the PHP `intl` extension if you plan to use `php artisan db:show` or `php artisan db:table`.

## Core Features

- Client ticket creation with attachments
- Ticket replies with threaded reply targets
- Admin assignment, priority, status, and due date management
- Role-based access (`client`, `super_user`, `technical`, `super_admin`)
- Account activation/deactivation controls

## Setup

1. Install dependencies:
```bash
composer install
npm install
```

2. Configure environment:
```bash
copy .env.example .env
php artisan key:generate
```

3. Configure database in `.env`, then run:
```bash
php artisan migrate
php artisan storage:link
```

4. Seed data (optional):
```bash
php artisan db:seed
```

5. Build frontend assets:
```bash
npm run build
```

6. Run application:
```bash
php artisan serve
```

## Seeded Accounts

Seeded users use `SEED_DEFAULT_USER_PASSWORD` from `.env`.

- `admin@ioneresources.com` (`super_user`)
- `support@ioneresources.com` (`technical`)
- `client@ioneresources.com` (`client`)
- `jane@ioneresources.com` (`client`)
- `bob@ioneresources.com` (`client`)

- If `SEED_DEFAULT_USER_PASSWORD` is missing, the seeder generates a random temporary password and prints it during seeding.
- `SuperAdminSeeder` uses `SEED_SUPER_ADMIN_PASSWORD` (or generates one and prints it).
- `admin@ione.com` is created as `super_admin`.

## Security Notes

- Do not use seeders with generated or shared credentials in production.
- Set `APP_ENV=production` and `APP_DEBUG=false` in production.
- Ensure `storage` and `bootstrap/cache` are writable by the app process.
