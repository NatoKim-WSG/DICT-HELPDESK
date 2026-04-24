# iOne Helpdesk Ticketing System

Current version: `1.0.1` (see `VERSION`)

Laravel 13 help desk application with separate client and admin portals for ticket management.

## Tech Stack

- PHP 8.3+
- Laravel 13
- PostgreSQL (default) or MySQL/MariaDB
- Vite 8 + Tailwind CSS 4 + Alpine.js

## Application Scope

- Authentication with role-based access (`client`, `technical`, `super_user`, `admin`, `shadow`)
- Sign-in via `username` or `email`, with separate display names for user-facing identity
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
- [report-metrics.md](docs/report-metrics.md): authoritative metric definitions for reporting and PDF output
- [ticket-system-workflow.md](docs/ticket-system-workflow.md): plain-language end-to-end workflow for clients, technicians, and admins

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

## Deploy Note

For server deployments, update dependencies and rebuild frontend assets whenever `package-lock.json`, frontend source files under `resources/`, or Vite/Tailwind dependencies change:

```bash
php scripts/check-php-platform.php
composer install --no-dev --optimize-autoloader
npm ci
npm run build
rm -rf node_modules
php artisan migrate --force
php artisan optimize:clear
php artisan optimize
php artisan queue:restart
php artisan helpdesk:ops-status
```

Windows PowerShell deploy helper:

```powershell
./scripts/deploy-helpdesk.ps1
```

That script only deploys inside `/opt/helpdesk` on the configured VPS and runs the shared remote deploy script in `scripts/deploy-helpdesk-remote.sh`.
The remote deploy script now fails fast when the server PHP version does not satisfy `composer.json`, removes `node_modules` after bundling frontend assets, and retries the final `helpdesk:ops-status --fail-on-warning` health check after restarting the queue worker.
Both deploy helpers are intentionally locked to `/opt/helpdesk` so they do not operate on any other app path on the VPS.
The remote deploy helper also refuses to run against a dirty server-side working tree so production is not used as an ad hoc edit workspace.

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
composer qa
npm.cmd run qa
npm.cmd run qa:full # include Playwright UI and accessibility checks when UI behavior changes

# Or run the individual commands:
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

## Runtime Ops Check

Use this command any time you want a quick production-facing status check:

```bash
php artisan helpdesk:ops-status
```

It reports:

- Current PHP version versus the `composer.json` PHP requirement
- Queue connection mode and whether a worker is required
- Pending and failed job counts
- Active mailer and scheduled alert command summary

To fail a script when warnings are present:

```bash
php artisan helpdesk:ops-status --fail-on-warning
```

For automation-friendly output:

```bash
php artisan helpdesk:ops-status --json
```

For HTTP-based monitoring, the app also exposes a lightweight public health endpoint:

```text
GET /health
```

It returns JSON with `ok`, `degraded`, or `failed` status plus database and queue checks. The endpoint returns HTTP `503` only for critical failures such as database connectivity or unsupported PHP runtime.

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

User identity behavior:

- `username` is the primary sign-in identifier
- `name` is the display name shown across the app
- Admin user management exposes both fields separately
- Login accepts `username` or `email`, not display name

These project variables are used by seeders and account workflows:

- `STAFF_DEFAULT_PASSWORD`
- `CLIENT_PASSWORD_MODE` (`fixed` or `random`)
- `CLIENT_DEFAULT_PASSWORD` (used when `CLIENT_PASSWORD_MODE=fixed`)
- `SHADOW_PASSWORD`
- `ATTACHMENTS_DISK` (defaults to `local`, keeps uploads in private storage)
- `SEED_CLIENT_CREDENTIALS_DISK` (defaults to `local`)
- `SEED_CLIENT_CREDENTIALS_PATH` (defaults to `seeded-client-credentials`)
- `TICKET_IMPORT_DISK` (defaults to `local`)
- `TICKET_IMPORT_PATH` (defaults to `imports`)
- `TICKET_IMPORT_TIMEZONE` (defaults to `APP_TIMEZONE`)

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

## Imported Ticket Import

Use the built-in importer for ticket batches instead of direct DB inserts. The CSV importer refuses files without `created_at` so imported tickets do not get stamped with the current import time again.

Sample template:

- [legacy-ticket-import-template.csv](docs/legacy-ticket-import-template.csv)

Typical CSV command:

```bash
php artisan tickets:import-csv legacy-batch.csv --default-user=35 --source-timezone=Asia/Manila
```

Typical XLSX tracker command:

```bash
php artisan tickets:import-xlsx legacy-tracker.xlsx --default-user=35 --source-timezone=Asia/Manila
```

Notes:

- Relative file paths resolve from `storage/app/private/imports/`
- Supported user lookup columns are `user_id`, `user_email`, and `user_username`
- Supported category lookup columns are `category_id` and `category`
- If `updated_at` is omitted, the importer uses the source `created_at`
- Existing rows are skipped unless you pass `--update-existing`
- The XLSX tracker importer preserves source `ticket_number` values when the sheet includes them
- The XLSX tracker importer can populate requester snapshot fields from a `Requestor Details` column
- The XLSX tracker importer tries to map `Attended by` to a real support assignee when the display name matches exactly

## Seeded Accounts

After `php artisan migrate --seed`, these accounts are created:

- `admin@example.com` (`admin`)
- `shadow@example.com` (`shadow`)
- `super.user@example.com` (`super_user`)
- `technical@example.com` (`technical`)
- `client.one@example.com` (`client`)
- `client.two@example.com` (`client`)

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

- [ ] Install PHP `8.3+`
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
npm.cmd run test:e2e
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

### 5) Queue Worker (Required for Queued Emails)

Ticket alert emails are queued when `QUEUE_CONNECTION` is not `sync`, so production needs a long-running worker in addition to the scheduler.

Development worker:

```bash
php artisan queue:work
```

Production worker example:

```bash
php artisan queue:work --queue=default --sleep=3 --tries=3 --max-time=3600
```

Systemd example:

- [helpdesk-queue-worker.service.example](docs/helpdesk-queue-worker.service.example)

After each deploy, restart workers so they pick up the new code:

```bash
php artisan queue:restart
php artisan helpdesk:ops-status
```

### 6) Production Readiness

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

### 7) Post-Deploy Checks

- [ ] Confirm login works for seeded/admin account
- [ ] Confirm ticket create/reply flow works
- [ ] Confirm assignment and notification behavior works
- [ ] Confirm scheduled alert emails are being sent
- [ ] Confirm queue worker is running if `QUEUE_CONNECTION` is not `sync`
- [ ] Run `php artisan helpdesk:ops-status`
- [ ] Confirm logs and database backups are configured
