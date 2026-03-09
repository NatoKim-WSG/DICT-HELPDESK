# DICT Helpdesk Ticketing System

Current version: `1.0.1` (see `VERSION`)

Laravel 12 help desk application with separate client and admin portals for ticket management.

## Tech Stack

- PHP 8.3+ for full development and tests (runtime supports PHP 8.2+)
- Laravel 12
- PostgreSQL (default) or MySQL/MariaDB
- Vite 7 + Tailwind CSS 4 + Alpine.js

## Application Scope

- Authentication with role-based access (`client`, `technical`, `super_user`, `admin`, `shadow`)
- Separate client/admin ticket consoles with controlled visibility and assignment scope
- Ticket lifecycle rules (`open`, `in_progress`, `pending`, `resolved`, `closed`) with policy gates
- Notification center with per-user seen/dismiss state
- Legal consent gate and versioned acceptance tracking
- Report dashboards with month/day drilldowns and PDF export

## Core Services

- [TicketEmailAlertService.php](app/Services/TicketEmailAlertService.php): assignment, SLA, and inactivity alert emails
- [SystemLogService.php](app/Services/SystemLogService.php): centralized security/audit event logging
- [TicketMutationService.php](app/Services/Admin/TicketMutationService.php): transactional destructive ticket operations (delete/merge)
- [ReportBreakdownService.php](app/Services/Admin/ReportBreakdownService.php): reusable report category/priority breakdown logic

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

## Dependency and Security Checks

Run these regularly (or before release):

```bash
composer diagnose
composer audit --locked
composer outdated --direct
npm audit --audit-level=high
npm outdated
# Windows PowerShell fallback for npm:
# npm.cmd audit --audit-level=high
# npm.cmd outdated
```

## Local Quality Gate

Run this full gate before push/release:

```bash
composer validate --strict
composer audit --locked --no-interaction
composer analyse
vendor/bin/pint --test
php artisan test

# PowerShell-friendly npm invocations:
npm.cmd run lint
npm.cmd run test:unit
npm.cmd run build
npm.cmd audit --audit-level=high
```

## CI and CodeQL

- CI workflow lives at `.github/workflows/ci.yml` and validates secret scanning, backend checks, frontend checks, and e2e.
- CodeQL workflow lives at `.github/workflows/codeql.yml` and analyzes JavaScript/TypeScript.
- PHP static/security checks are handled in CI (PHPStan/composer audit), not CodeQL language matrix.
- GitHub Actions history keeps old failed runs; verify current health using the latest run or workflow badge status.

## Fix Composer SSL Certificate Errors (Windows)

If Composer commands show `curl error 60` (`SSL certificate problem: unable to get local issuer certificate`):

1. Download the latest CA bundle from `https://curl.se/ca/cacert.pem`.
2. Save it to a stable path, for example `C:\tools\php\certs\cacert.pem`.
3. Update your active `php.ini`:
   - `curl.cainfo="C:\tools\php\certs\cacert.pem"`
   - `openssl.cafile="C:\tools\php\certs\cacert.pem"`
4. Restart terminal/IDE, then verify:

```bash
php -i | findstr /I "curl.cainfo openssl.cafile"
composer diagnose
```

## Environment Variables

These project variables are used by seeders and account workflows:

- `STAFF_DEFAULT_PASSWORD`
- `CLIENT_PASSWORD_MODE` (`fixed` or `random`)
- `CLIENT_DEFAULT_PASSWORD` (used when `CLIENT_PASSWORD_MODE=fixed`)
- `SHADOW_PASSWORD`
- `ATTACHMENTS_DISK` (defaults to `local`, keeps uploads in private storage)
- `SEED_CLIENT_CREDENTIALS_DISK` (defaults to `local`)
- `SEED_CLIENT_CREDENTIALS_PATH` (defaults to `private/seeded-client-credentials`)

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
- `AFPR2@gmail.com` (`client`)
- `AFPR1@gmail.com` (`client`)

Password behavior:

- Staff seeded users (`admin`, `super_user`, `technical`) use `STAFF_DEFAULT_PASSWORD` and must change password on first login.
- Shadow user password comes from `SHADOW_PASSWORD`.
- Client users use:
  - `CLIENT_DEFAULT_PASSWORD` when `CLIENT_PASSWORD_MODE=fixed`
  - random 10-character passwords when `CLIENT_PASSWORD_MODE=random`
- In random client mode, one-time plaintext credentials are written to a private handoff file under `storage/app/private/seeded-client-credentials/`.
- If required password env values are missing, seeding fails fast instead of silently using hardcoded credentials.

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
- Set strong environment-specific values for `STAFF_DEFAULT_PASSWORD`, `CLIENT_DEFAULT_PASSWORD`, and `SHADOW_PASSWORD`.
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

- [ ] Update `.env` values (`APP_*`, `DB_*`, `MAIL_*`, `STAFF_DEFAULT_PASSWORD`, `CLIENT_PASSWORD_MODE`, `CLIENT_DEFAULT_PASSWORD`, `SHADOW_PASSWORD`, `LEGAL_*`, `ATTACHMENTS_DISK`)
- [ ] Run migrations and seeders:

```bash
php artisan migrate --seed
php artisan storage:link
```

### 3) Verify Locally

- [ ] Run tests:

```bash
composer analyse
vendor/bin/pint --test
php artisan test
npm.cmd run test:unit
npm.cmd run lint
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
