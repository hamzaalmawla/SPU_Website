# Production Environment Baseline

This project must not launch with copied local `.env` values. Use `.env.production.example` as the production checklist template and store real secrets only in the deployment secret manager or server environment.

## Required Values

| Setting | Production requirement |
| --- | --- |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_KEY` | Unique production key, not reused from local or staging |
| `APP_URL` | Canonical HTTPS origin, for example `https://spu.edu.sy` |
| `APP_CANONICAL_URL` | Same canonical HTTPS origin; controls host redirects and crawler output |
| `ENFORCE_CANONICAL_HOST` | `true` |
| `LOG_CHANNEL` / `LOG_DAILY_DAYS` | `daily` with reviewed retention (baseline: 14 days) |
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
| `REQUIRE_PRIVILEGED_ADMIN_2FA` | `true`; privileged roles cannot enter production admin until TOTP enrollment is confirmed |
| `PRIVILEGED_ADMIN_2FA_ROLES` | `super_admin,editor,faculty_editor,hr`, unless a reviewed role decision changes it |
| `TRUSTED_PORTAL_HOSTS` | Exact comma-separated HTTPS hosts approved for student/staff portal links; no wildcard or parent-domain matching |

## Launch Gate

- Confirm `.env` and `.env.production` are ignored by git.
- Confirm no real production secret appears in tracked files.
- Run `php artisan config:clear` after provisioning environment values.
- Run `php artisan route:list` and `php artisan test --filter=Homepage` on the release artifact.
- Run `php artisan launch:validate --environment=production` against production-like data before DNS cutover.
- Rotate `ADMIN_PASSWORD` or disable the bootstrap admin seeder after the first production admin account is verified.
- Sign in each privileged account through the enrollment path, confirm a live TOTP, store recovery codes offline, sign out, and prove the next login is challenged.

## Manual Verification

Run these checks on the deployment host before launch:

```bash
php artisan tinker --execute="dump(config('app.env'), config('app.debug'), config('app.url'), config('cache.default'), config('session.driver'), config('queue.default'), config('session.secure'), config('session.http_only'), config('session.same_site'));"
```

Expected output must show `production`, `false`, HTTPS URL, Redis or an approved production backend, secure session flags, and `lax` or stricter SameSite policy.

The repository fixes trusted proxies to `127.0.0.1` and `::1` for the documented
cPanel nginx-to-Apache topology. A topology change requires a reviewed code
change; do not replace these addresses with `*` or a public CIDR.

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

For cPanel cron workers, use the locked commands in
`deploy/v2-staging/README.md`: both scheduler and queue commands use `flock`,
queue `--tries`/`--timeout` values, and dedicated output logs. Alert when the
scheduler log has no output for five minutes and on every new `queue:failed`
entry. Exercise a harmless scheduled command and a deliberately failing test
job in staging to prove heartbeat and failure notifications before launch.

## Release Artifact Verification

Build once in CI or another approved build host, then deploy that exact artifact:

```bash
composer validate --strict
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
npm ci
npm run build
test -f public/build/manifest.json
php artisan route:list >/dev/null
php artisan config:cache
php artisan config:clear
```

Create and retain a SHA-256 manifest for the packaged artifact, including
`composer.lock`, `package-lock.json`, `public/build/manifest.json`, application
code, migrations, and deployment configuration. Verify that manifest after
transfer and before changing the release symlink. Reject artifacts containing
`.env`, tests, local caches, `node_modules`, VCS metadata, or writable runtime
logs. Verify the deployed release reports the expected commit/release ID and
rerun `composer audit --locked`, `npm audit --package-lock-only`,
`php artisan about`, and `php artisan launch:validate --environment=production`.

## CSP Compatibility Blocker

Public pages currently prohibit `unsafe-eval`, but Blade/Alpine still emits
inline scripts and styles. Filament/Livewire also requires inline script/style
execution and currently uses eval-compatible behavior. Removing
`unsafe-inline` or the admin-only `unsafe-eval` token without first replacing
inline handlers, bundling compatible admin assets, and propagating per-response
nonces through Blade, Livewire, and Filament would break administration.
Complete that nonce migration as a separately browser-tested change; do not
claim strict nonce-only CSP support until both AR/EN public pages and all admin
workflows pass without console violations.

Provision the restricted HR login only after setting the HR secrets:

```bash
php artisan db:seed --class=HrUserSeeder --force
```
