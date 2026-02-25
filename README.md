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
- Role-based access (`client`, `technical`, `super_user`, `admin`, `developer`)
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

## Real Email Delivery (External SMTP)

Ticket alerts can be delivered to real inboxes when SMTP is configured with a real provider.

1. Set mail values in `.env` (example):
```bash
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your_smtp_username
MAIL_PASSWORD=your_smtp_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=helpdesk@yourdomain.com
MAIL_FROM_NAME="${APP_NAME}"
MAIL_REPLY_TO_ADDRESS=support@yourdomain.com
MAIL_REPLY_TO_NAME="${APP_NAME}"
```

2. Clear config cache after updates:
```bash
php artisan config:clear
```

3. Send a live test email:
```bash
php artisan mail:test your-email@example.com
```

4. Keep scheduler running for timed reminder alerts:
```bash
php artisan schedule:work
```

## Seeded Accounts

Seeded users use `DEFAULT_USER_PASSWORD` from `.env`.

- `admin@ioneresources.com` (`super_user`)
- `support@ioneresources.com` (`technical`)
- `client@ioneresources.com` (`client`)
- `jane@ioneresources.com` (`client`)
- `bob@ioneresources.com` (`client`)

- `admin@ione.com` is created as `admin`.
- `developer@ione.com` is created as `developer` and uses `DEFAULT_DEVELOPER_PASSWORD`.

## Security Notes

- Do not use seeders with generated or shared credentials in production.
- Set `APP_ENV=production` and `APP_DEBUG=false` in production.
- Ensure `storage` and `bootstrap/cache` are writable by the app process.
