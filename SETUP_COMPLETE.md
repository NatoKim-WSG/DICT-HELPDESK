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
```

If all commands pass, the local project is synced.

## Seeded Accounts

- `admin@ioneresources.net` (`admin`) uses `DEFAULT_USER_PASSWORD`
- `shadow@ione.com` (`shadow`) uses `DEFAULT_SHADOW_PASSWORD`
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
php artisan schedule:work
```

## Notes

- Keep `.env` private and environment-specific.
- Use `DEFAULT_SHADOW_PASSWORD` for the shadow account key in env files.
- `DEFAULT_DEVELOPER_PASSWORD` is only a backward-compatible legacy alias.
