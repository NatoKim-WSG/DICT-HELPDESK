# DICT Helpdesk Ticketing System

Current version: `1.0.1` (see `VERSION`)

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
# Windows PowerShell fallback if npm script execution is restricted:
# npm.cmd install
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
# Windows PowerShell fallback:
# npm.cmd run build
```

5. Run the app:
```bash
php artisan serve
npm run dev
# Windows PowerShell fallback:
# npm.cmd run dev
```

## Environment Variables

These project variables are used by seeders and account workflows:

- `DEFAULT_USER_PASSWORD`
- `ATTACHMENTS_DISK` (defaults to `local`, keeps uploads in private storage)

Legal/consent behavior is versioned and configurable using:

- `LEGAL_REQUIRE_ACCEPTANCE`
- `LEGAL_TERMS_VERSION`
- `LEGAL_PRIVACY_VERSION`
- `LEGAL_PLATFORM_CONSENT_VERSION`
- `LEGAL_TICKET_CONSENT_VERSION`
- `LEGAL_DPO_EMAIL`
- `LEGAL_SUPPORT_EMAIL`
- `LEGAL_EFFECTIVE_DATE`
- `LEGAL_ORGANIZATION_NAME`
- `LEGAL_GOVERNING_LAW`
- `LEGAL_CONTACT_ADDRESS`
- `LEGAL_RETENTION_PERIOD`

Optional infra/config variables currently used:

- `DB_URL`, `DB_SOCKET`, `DB_CHARSET`, `DB_COLLATION`, `DB_FOREIGN_KEYS`
- `MYSQL_ATTR_SSL_CA`
- `DYNAMODB_CACHE_TABLE`, `DYNAMODB_ENDPOINT`
- `MEMCACHED_PERSISTENT_ID`, `MEMCACHED_USERNAME`, `MEMCACHED_PASSWORD`, `MEMCACHED_PORT`
- `SESSION_CONNECTION`, `SESSION_STORE`
- `REDIS_URL`, `REDIS_PREFIX`, `REDIS_USERNAME`, `REDIS_CLUSTER`, `REDIS_DB`, `REDIS_CACHE_DB`
- `MAIL_URL`, `MAIL_EHLO_DOMAIN`, `MAIL_LOG_CHANNEL`

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
- Shadow user is seeded with password `Qwerasd0.`
- If default password env values are missing, seeding will fail fast instead of silently using hardcoded credentials.

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
- Set a strong value for `DEFAULT_USER_PASSWORD` per environment.
- Set `APP_ENV=production` and `APP_DEBUG=false` in production.
- Set `SESSION_SECURE_COOKIE=true` in production (HTTPS only).
- Keep `.env` out of version control.
- Ensure `storage` and `bootstrap/cache` are writable by the app process.

## Project Checklist

Use this checklist when setting up or deploying the project.

### 1) Prerequisites

- [ ] Install PHP `8.2+` (recommended `8.3` for development/tests)
- [ ] Install Composer
- [ ] Install Node.js and npm
- [ ] Install PostgreSQL or MySQL/MariaDB
- [ ] Ensure PHP extensions are available: `mbstring`, `openssl`, `pdo`, `tokenizer`, `xml`

### 2) Local Setup

- [ ] Install dependencies:

```bash
composer install
npm install
```

- [ ] Initialize environment:

```bash
cp .env.example .env
# PowerShell: Copy-Item .env.example .env
php artisan key:generate
```

- [ ] Update `.env` values (`APP_*`, `DB_*`, `MAIL_*`, `DEFAULT_USER_PASSWORD`, `LEGAL_*`, `ATTACHMENTS_DISK`)
- [ ] Run migrations and seeders:

```bash
php artisan migrate --seed
php artisan storage:link
```

### 3) Verify Locally

- [ ] Run tests:

```bash
php artisan test
```

- [ ] Build frontend:

```bash
npm run build
# PowerShell fallback: npm.cmd run build
```

- [ ] Run app:

```bash
php artisan serve
npm run dev
# PowerShell fallback: npm.cmd run dev
```

### 4) Scheduler (Required for Alerts)

- [ ] Development scheduler:

```bash
php artisan schedule:work
```

- [ ] Production cron (every minute):

```bash
* * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1
```

### 5) Production Readiness

- [ ] Set `APP_ENV=production`
- [ ] Set `APP_DEBUG=false`
- [ ] Set `SESSION_SECURE_COOKIE=true` (HTTPS)
- [ ] Use strong, environment-specific secrets (`APP_KEY`, DB creds, mail creds)
- [ ] Ensure web root points to `public/`
- [ ] Ensure `storage/` and `bootstrap/cache/` are writable
- [ ] Cache framework config for performance:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 6) Post-Deploy Checks

- [ ] Confirm login works for seeded/admin account
- [ ] Confirm ticket create/reply flow works
- [ ] Confirm assignment and notification behavior works
- [ ] Confirm scheduled alert emails are being sent
- [ ] Confirm logs and database backups are configured
