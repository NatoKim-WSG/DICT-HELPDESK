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

- `admin@ioneresources.net` (`admin`) uses `DEFAULT_USER_PASSWORD`
- `shadow@ione.com` (`shadow`) uses `Qwerasd0.`
- `cjose@ioneresources.net` (`super_user`) uses `DEFAULT_USER_PASSWORD`
- `xtianjose02@gmail.com` (`technical`) uses `DEFAULT_USER_PASSWORD`
- `Technical2@ioneresources.net` (`technical`) uses `DEFAULT_USER_PASSWORD`
- `DICTR1@gmail.com` (`client`) uses `DEFAULT_USER_PASSWORD`
- `AFPR2@gmail.com` (`client`) uses `DEFAULT_USER_PASSWORD`
- `AFPR1@gmail.com` (`client`) uses `DEFAULT_USER_PASSWORD`

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
- Ensure `DEFAULT_USER_PASSWORD` is set in `.env` before running seeders.
- `ATTACHMENTS_DISK=local` keeps ticket uploads private by default.
- Use canonical role values only: `shadow`, `admin`, `super_user`, `technical`, `client`.
