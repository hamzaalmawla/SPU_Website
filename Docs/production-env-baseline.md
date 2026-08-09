# Production Environment Baseline

This project must not launch with copied local `.env` values. Use `.env.production.example` as the production checklist template and store real secrets only in the deployment secret manager or server environment.

## Required Values

| Setting | Production requirement |
| --- | --- |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_KEY` | Unique production key, not reused from local or staging |
| `APP_URL` | Canonical HTTPS origin, for example `https://spu.edu.sy` |
| `DB_USERNAME` | Least-privileged application database user, not `root` |
| `DB_PASSWORD` | Strong secret stored outside git |
| `ADMIN_PASSWORD` | Strong one-time bootstrap password, rotated or disabled after first admin setup |
| `SESSION_SECURE_COOKIE` | `true` |
| `SESSION_HTTP_ONLY` | `true` |
| `SESSION_SAME_SITE` | `lax` or stricter after browser testing |
| `SESSION_ENCRYPT` | `true` for production hardening |
| `CACHE_STORE` | `redis` unless a tag-compatible production alternative is explicitly approved |
| `SESSION_DRIVER` | `redis` unless an approved production session store is documented |
| `QUEUE_CONNECTION` | `redis` or another production queue backend with workers configured |
| `MAIL_MAILER` | Real production transport such as `smtp`, never `log` |
| `MAIL_FROM_ADDRESS` | Verified sender address on the production mail domain |
| `FORM_ADMIN_RECIPIENTS` | Optional comma-separated operational recipients; eligible admin/editor users are also notified |
| `HR_EMAIL` / `HR_PASSWORD` | Required only when explicitly running `HrUserSeeder`; store credentials outside git and rotate the bootstrap password |

## Launch Gate

- Confirm `.env` and `.env.production` are ignored by git.
- Confirm no real production secret appears in tracked files.
- Run `php artisan config:clear` after provisioning environment values.
- Run `php artisan route:list` and `php artisan test --filter=Homepage` on the release artifact.
- Run `php artisan launch:validate --environment=production` against production-like data before DNS cutover.
- Rotate `ADMIN_PASSWORD` or disable the bootstrap admin seeder after the first production admin account is verified.

## Manual Verification

Run these checks on the deployment host before launch:

```bash
php artisan tinker --execute="dump(config('app.env'), config('app.debug'), config('app.url'), config('cache.default'), config('session.driver'), config('queue.default'), config('session.secure'), config('session.http_only'), config('session.same_site'));"
```

Expected output must show `production`, `false`, HTTPS URL, Redis or an approved production backend, secure session flags, and `lax` or stricter SameSite policy.

## Form Mail Operations

Public form receipts, status updates, and staff notifications are queued. Run a continuously supervised worker in production, for example:

```bash
php artisan queue:work redis --queue=default --tries=3 --timeout=120
```

After each deployment, restart workers so they load the current release:

```bash
php artisan queue:restart
```

Monitor `failed_jobs` and the form submission/contact message delivery fields. `sent` records indicate that the configured mail transport accepted the message; they are not recipient-provider delivery confirmations.

Provision the restricted HR login only after setting the HR secrets:

```bash
php artisan db:seed --class=HrUserSeeder --force
```
