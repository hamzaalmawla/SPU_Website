# Post-Pull Checklist — SPU Website

Run these steps every time you `git pull` from GitHub to keep your local environment in sync.

---

## Quick Safe Mode (recommended if unsure)

If you don't know exactly what changed in the pull, run these commands in order. They are safe to run even when nothing changed.

```bash
# 1. PHP dependencies
composer install

# 2. Node dependencies
npm install

# 3. Database migrations
php artisan migrate

# 4. Clear all Laravel caches
php artisan optimize:clear

# 5. Rebuild frontend assets
npm run build

# 6. Ensure storage link exists
php artisan storage:link

# 7. (Optional) Filament asset refresh
php artisan filament:assets
```

Then start the server:
```bash
php artisan serve
```

Or use the built-in dev command to run server + queue + logs + Vite together:
```bash
composer dev
```

---

## Detailed Conditional Steps

Only run the steps that apply to what changed in the pull.

### A. Backend / PHP changes
- **If `composer.json` or `composer.lock` changed:**
  ```bash
  composer install
  ```

- **If new files were added in `app/`, `config/`, `routes/`, or `bootstrap/`:**
  ```bash
  php artisan optimize:clear
  ```

- **If `config/` files changed:**
  ```bash
  php artisan config:clear
  php artisan cache:clear
  ```

### B. Database changes
- **If new files appear in `database/migrations/`:**
  ```bash
  php artisan migrate
  ```
  > ⚠️ Only run `migrate:fresh --seed` if you explicitly want to **reset** your local database and re-seed it.

- **If `database/seeders/` changed and you want fresh seed data:**
  ```bash
  php artisan db:seed
  ```

### C. Frontend / Asset changes
- **If `package.json` or `package-lock.json` changed:**
  ```bash
  npm install
  ```

- **If `resources/css/`, `resources/js/`, `resources/views/`, or `vite.config.js` changed:**
  ```bash
  npm run build
  ```
  > Use `npm run dev` instead if you want the Vite dev server with hot reload.

### D. Admin / Filament changes
- **If Filament pages, resources, or forms look broken after the pull:**
  ```bash
  php artisan optimize:clear
  php artisan filament:assets
  php artisan filament:cache-components
  ```

### E. Storage / Uploads
- **If `public/storage` link is broken or missing:**
  ```bash
  php artisan storage:link
  ```

### F. Queue worker (if you run background jobs locally)
- **If you have a `php artisan queue:work` or `queue:listen` running:**
  Stop it (`Ctrl+C`) and restart it after the pull so it picks up new code.
  ```bash
  php artisan queue:work
  ```

---

## One-Liner Power Command (Windows PowerShell)

Copy-paste this single block after every pull:

```powershell
composer install; npm install; php artisan migrate; php artisan optimize:clear; npm run build; php artisan storage:link
```

Then run the app:
```powershell
php artisan serve
```

---

## Local Environment Requirements

Make sure these are running before you start:

| Service | Your `.env` setting | Status |
|---------|---------------------|--------|
| MySQL | `DB_CONNECTION=mysql` | Must be running (XAMPP / Laragon / etc.) |
| Redis | `CACHE_STORE=array` (local) | Optional for local dev |
| Queue | `QUEUE_CONNECTION=database` | Handled automatically; worker only needed for mail/background jobs |

---

## Verify Everything Works

After the steps above, open these URLs:

- **Public site (AR):** http://localhost:8000/ar
- **Public site (EN):** http://localhost:8000/en
- **Admin panel:** http://localhost:8000/admin

If the admin panel loads with styles, and the homepage shows content, you're good.

---

## Troubleshooting

| Problem | Fix |
|---------|-----|
| "Class not found" or "target not found" | `composer install` then `php artisan optimize:clear` |
| Admin panel looks unstyled | `php artisan filament:assets` |
| CSS/JS missing on public pages | `npm run build` |
| 500 error after pull | `php artisan optimize:clear` then check `storage/logs/laravel.log` |
| Database error | Confirm MySQL is running, then `php artisan migrate` |
| Uploads return 404 | `php artisan storage:link` |

---

*Last updated: 2026-08-01*
