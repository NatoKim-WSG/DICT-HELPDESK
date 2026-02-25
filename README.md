# DICT Helpdesk Ticketing System

Laravel 12 help desk application with separate client and admin portals for ticket management.

## Tech Stack

- PHP 8.3+ for full development and tests (runtime supports PHP 8.2+)
- Laravel 12
- PostgreSQL (default) or MySQL/MariaDB
- Vite 7 + Tailwind CSS 4 + Alpine.js

## Prerequisites

- PHP with required extensions (`mbstring`, `openssl`, `pdo`, `tokenizer`, `xml`)
- Composer
- Node.js and npm
- PostgreSQL or MySQL/MariaDB

## Setup

1. Install dependencies:
```bash
composer install
npm install
```

2. Configure environment:
```bash
cp .env.example .env
# Windows PowerShell alternative:
# Copy-Item .env.example .env
php artisan key:generate
```

3. Configure database values in `.env` (`DB_*`) and run migrations:
```bash
php artisan migrate --seed
php artisan storage:link
```

4. Build frontend assets:
```bash
npm run build
```

5. Run the app:
```bash
php artisan serve
```

## Environment Variables

These project variables are used by seeders and account workflows:

- `DEFAULT_USER_PASSWORD`
- `DEFAULT_SHADOW_PASSWORD`

Legacy compatibility is kept for older env files using `DEFAULT_DEVELOPER_PASSWORD`, but `DEFAULT_SHADOW_PASSWORD` is the active key.

Legal/consent behavior is versioned and configurable using:

- `LEGAL_REQUIRE_ACCEPTANCE`
- `LEGAL_TERMS_VERSION`
- `LEGAL_PRIVACY_VERSION`
- `LEGAL_PLATFORM_CONSENT_VERSION`
- `LEGAL_TICKET_CONSENT_VERSION`
- `LEGAL_DPO_EMAIL`
- `LEGAL_SUPPORT_EMAIL`

## Seeded Accounts

After `php artisan migrate --seed`, these accounts are created:

- `admin@ioneresources.net` (`admin`)
- `shadow@ione.com` (`shadow`)
- `cjose@ioneresources.net` (`super_user`)
- `xtianjose02@gmail.com` (`technical`)
- `Technical2@ioneresources.net` (`technical`)
- `DICTR1@gmail.com` (`client`)
- `AFPR2@gmail.com` (`client`)
- `AFPR1@gmail.com` (`client`)

Password behavior:

- Non-shadow seeded users use `DEFAULT_USER_PASSWORD`
- Shadow user uses `DEFAULT_SHADOW_PASSWORD`

## Real Email Delivery (External SMTP)

Set mail values in `.env`:

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

Then clear config cache and test:

```bash
php artisan config:clear
php artisan mail:test your-email@example.com
```

## Security Notes

- Do not use shared/default credentials in production.
- Set `APP_ENV=production` and `APP_DEBUG=false` in production.
- Keep `.env` out of version control.
- Ensure `storage` and `bootstrap/cache` are writable by the app process.
