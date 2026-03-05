# iOne Resources Ticketing System Setup

This file summarizes the expected post-setup state for local development.

## Expected Local URL

- http://localhost:8000

## Quick Verification

Run these commands from the project root:

```bash
php artisan migrate --seed
php artisan test
npm run build
# Windows PowerShell fallback:
# npm.cmd run build
```

If all commands pass, the local project is synced.

## Seeded Accounts

- `admin@ioneresources.net` (`admin`) uses `STAFF_DEFAULT_PASSWORD` and must change password on first login
- `shadow@ione.com` (`shadow`) uses `SHADOW_PASSWORD`
- `cjose@ioneresources.net` (`super_user`) uses `STAFF_DEFAULT_PASSWORD` and must change password on first login
- `xtianjose02@gmail.com` (`technical`) uses `STAFF_DEFAULT_PASSWORD` and must change password on first login
- `Technical2@ioneresources.net` (`technical`) uses `STAFF_DEFAULT_PASSWORD` and must change password on first login
- `DICTR1@gmail.com` (`client`) uses:
  - `CLIENT_DEFAULT_PASSWORD` when `CLIENT_PASSWORD_MODE=fixed`
  - random 10-character password when `CLIENT_PASSWORD_MODE=random`
- `AFPR2@gmail.com` (`client`) follows the same client policy above
- `AFPR1@gmail.com` (`client`) follows the same client policy above

## Development Commands

```bash
php artisan serve
npm run dev
# Windows PowerShell fallback:
# npm.cmd run dev
php artisan schedule:work
```

## Notes

- Keep `.env` private and environment-specific.
- Ensure `STAFF_DEFAULT_PASSWORD`, `CLIENT_PASSWORD_MODE`, `CLIENT_DEFAULT_PASSWORD`, and `SHADOW_PASSWORD` are set in `.env` before running seeders.
- In random client mode, seeded client credentials are exported to `storage/app/private/seeded-client-credentials/`.
- `ATTACHMENTS_DISK=local` keeps ticket uploads private by default.
- Use canonical role values only: `shadow`, `admin`, `super_user`, `technical`, `client`.
