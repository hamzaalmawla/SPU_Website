# v2.spu.edu.sy — staging overlay

> Current-state note (2026-08-21): this file records a prior deployed staging
> snapshot plus reconstruction requirements. The current working-tree remediation
> has not been deployed or verified on the host. The byte-identity statement below
> is historical evidence only. See
> `Docs/CURRENT_REMEDIATION_EXECUTION_CHECKLIST.md`; do not infer deployment,
> cutover approval, or sign-off.

Everything the deployed host has that the repository does not. Nothing about the
v2 deployment should exist only on cPanel; if it does, it is lost the moment the
account is rebuilt.

The application code on the server is byte-identical to the repository — verified
by hashing all 808 deployed PHP files. What follows is the rest.

---

## 1. Files that differ from the repo, and why

| Path in the web root | Repo version | Deployed version |
|---|---|---|
| `.htaccess` | ships as-is at cutover | repo file **plus** the staging blocks in §2 |
| `robots.txt` | app serves its own via `SitemapController` | replaced by the disallow-all in §3 |
| `.user.ini` | same file | same file |

`app.php`, `index.php`, `site.webmanifest` and every PHP file are identical.

---

## 2. The `.htaccess` staging overlay

The deployed `.htaccess` is the repository's `public/.htaccess` with two blocks
added. Both are marked `STAGING ONLY` in the deployed file. **Remove both at
cutover** and the repository file is what ships.

Prepended at the very top:

```apache
<IfModule mod_headers.c>
    # STAGING ONLY — this host must never be indexed while spu.edu.sy is live.
    Header always set X-Robots-Tag "noindex, nofollow, noarchive"
</IfModule>
```

Inserted immediately after `RewriteEngine On`, along with `Options +FollowSymLinks`
(needed because the legacy media trees are symlinks):

```apache
    Options +FollowSymLinks

    # STAGING ONLY — host guard.
    # This document root physically lives under public_html, so it is also
    # reachable as spu.edu.sy/spu_v2/public/... Refuse to answer there so the
    # new site has exactly one address and cannot shadow the old site.
    RewriteCond %{HTTP_HOST} !^v2\.spu\.edu\.sy$ [NC]
    RewriteRule ^ - [R=404,L]
```

At cutover, `Options +FollowSymLinks` must be **kept** if the legacy media
symlinks are kept. Only the two `STAGING ONLY` blocks go.

---

## 3. The staging `robots.txt`

The deployed file replaces the application's own robots route so the rehearsal
host cannot be indexed:

```
# v2.spu.edu.sy is the pre-cutover staging host for the new SPU website.
# The canonical site is https://spu.edu.sy — this host must not be indexed.
# At cutover: delete this file so the application serves its own robots.txt.
User-agent: *
Disallow: /
```

**Delete this file at cutover** so `SitemapController@robots` serves the real one.

---

## 4. Filesystem layout and symlinks

cPanel forces subdomain document roots under `public_html`, so the application
lives outside the web root and only the front controller is inside it.

```
/home/spuedu/spu_v2_app                  application (.env chmod 600)
/home/spuedu/public_html/spu_v2/public   document root for v2.spu.edu.sy
/home/spuedu/.spu_v2_tools/composer.phar composer
```

Recreate with:

```bash
APP=/home/spuedu/spu_v2_app
WEB=/home/spuedu/public_html/spu_v2/public
OLD=/home/spuedu/public_html

# Cache stores the release expects. Missing directories fail at runtime, not at
# deploy time, so create them before the first request.
mkdir -p "$APP/storage/framework/cache/data" \
         "$APP/storage/framework/cache/webhook" \
         "$APP/storage/framework/cache/rate-limiter"
chmod -R 775 "$APP/storage" "$APP/bootstrap/cache"

# public_path() must resolve, or @vite silently emits no stylesheet and the
# whole site renders unstyled.
ln -sfn "$WEB" "$APP/public"

# storage:link equivalent
ln -sfn "$APP/storage/app/public" "$WEB/storage"

# Legacy media: symlinked read-only, never copied. downloads/files alone is
# 17 GB and the account has ~12 GB of quota free.
mkdir -p "$WEB/_legacy/downloads"
ln -sfn "$OLD/downloads/files"  "$WEB/_legacy/downloads/files"
ln -sfn "$OLD/downloads/files2" "$WEB/_legacy/downloads/files2"
ln -sfn "$OLD/images"           "$WEB/_legacy/images"
for f in med dent pharm info petrol admin research hospital dent_clinic alumni clubs; do
  mkdir -p "$WEB/_legacy/$f"
  ln -sfn "$OLD/$f/images" "$WEB/_legacy/$f/images"
done
```

The cPanel Git deployment is defined in the repository's `.cpanel.yml`. It runs
`deploy/v2-staging/publish-svg-assets.sh`, which publishes every tracked SVG
under `public/images` into `$WEB/images` and fails if representative navigation
icons are missing. This explicit copy is required because `$APP/public` is a
symlink to the separate cPanel document root; updating the application checkout
alone does not publish newly tracked static files into that document root.

After each deployment, verify both root-level and nested icon paths:

```bash
curl --fail --head https://v2.spu.edu.sy/images/icon-search-outline.svg
curl --fail --head https://v2.spu.edu.sy/images/icons/check-circle.svg
```

`cv_bank` is deliberately **not** linked — it holds applicant CVs and was
removed from scope. Do not re-add it without an explicit decision.

---

## 5. cPanel configuration

Not files, so not in git. Recreate through cPanel or its API:

| Setting | Value |
|---|---|
| Subdomain | `v2.spu.edu.sy`, docroot `public_html/spu_v2/public` |
| PHP version | `ea-php84` — **on this vhost only**; `spu.edu.sy` stays on `ea-php83` |
| SSL | the account's wildcard `*.spu.edu.sy` certificate |
| nginx dynamic private/full-page caching | must remain disabled; host verification pending |
| nginx static asset caching / proxy buffering | may be configured separately; do not treat it as permission to cache Laravel HTML |
| Databases | `spuedu_v2` (app) and a **SELECT-only** user on `spuedu_db` (legacy) |

Cron (the app's own scheduler and queue worker):

cPanel shell/Terminal and SSH are disabled. The commands/configuration in this
runbook require an approved host/operator deployment mechanism. Do not introduce a
temporary web or cron execution bridge merely to obtain shell-equivalent access.

```cron
* * * * * /usr/bin/flock -n /home/spuedu/spu_v2_app/storage/framework/scheduler.lock /opt/cpanel/ea-php84/root/usr/bin/php /home/spuedu/spu_v2_app/artisan schedule:run >> /home/spuedu/spu_v2_app/storage/logs/scheduler.log 2>&1
* * * * * /usr/bin/flock -n /home/spuedu/spu_v2_app/storage/framework/queue-default.lock /opt/cpanel/ea-php84/root/usr/bin/php /home/spuedu/spu_v2_app/artisan queue:work database --queue=default --stop-when-empty --max-time=50 --tries=3 --timeout=40 >> /home/spuedu/spu_v2_app/storage/logs/queue.log 2>&1
```

Confirm the host's `flock` path before installation. The queue timeout is below
the 50-second cron worker lifetime and must remain below `retry_after`. Review
`php artisan queue:failed` at least daily, alert on any new row, retain the job
ID/error, correct the cause, and use `queue:retry <id>` only after review. Alert
if `scheduler.log` has no new output for five minutes; test both alerts before
cutover. Laravel task-level `withoutOverlapping()` remains in place, while the
outer lock prevents concurrent scheduler/worker processes.

---

## 6. `.env`

Not in git and must not be. The deployed values differ from
`.env.production.example` in three ways worth knowing:

- `CACHE_STORE=file` — there is no Redis or Memcached on this host, and `file`
  benchmarks 5× faster than `database` while keeping page HTML out of MySQL.
- `SESSION_DRIVER=database`, `QUEUE_CONNECTION=database` — same reason.
- `OLD_DB_*` points at `spuedu_db` through a **SELECT-only** user, so the new
  site can never write to the legacy database.
- `APP_CANONICAL_URL=https://v2.spu.edu.sy` and
  `ENFORCE_CANONICAL_HOST=false` — the staging `.htaccess` host guard remains
  the overlay's authority and no application redirect can escape to production.
- Trusted proxies are fixed in repository bootstrap to `127.0.0.1,::1`; only the
  documented local cPanel nginx hop may supply forwarded scheme/host headers.
- `RELEASE_VERSION=v2-<YYYYMMDDHHMM>` — namespaces the cache keys per release, so
  a deploy cannot serve entries built by the previous one. Bump it on every
  deploy; the value itself is arbitrary as long as it changes.
- `CACHE_WEBHOOK_STORE=webhook` — keeps webhook replay state in its own store so
  ordinary cache invalidation cannot clear it. This matches the config default;
  it is set explicitly so the isolation is visible in the environment.
- `RATE_LIMIT_CACHE_DRIVER` must stay **unset**. It selects the *driver* for the
  `rate-limiter` store and already defaults to `file`. Setting it to the store
  name breaks boot with `Driver [rate-limiter] is not supported`.

Credentials are recorded outside the repo, on the operator's machine.

The canonical host, trusted-proxy, HTTPS, and front-controller hardening in the
current working tree is pending deployment. After deployment, verify the real
proxy topology and probe canonical/noncanonical hosts, forwarded-header spoofing,
redirect loops, `/app.php`, `/app.php/*`, and `/index.php/*` before changing DNS.

---

## 7. Database content

The migrated content — 2,145 articles, 9,881 attachments, 253 research
publications, 260 faculty members, 4,939 alumni — lives in `spuedu_v2` and is
**not** in git, which is correct: it is content, not code.

It is reproducible from the legacy database by re-running the documented import
pipeline. See `Docs/LEGACY_REDIRECT_MAINTENANCE_GUIDE.md` §12 for the commands
and approval tokens.

Two pieces of that work *are* in git, because they are code:

- `database/seeders/LegacyEntryPointRedirectSeeder.php` — the 25 deterministic
  redirect rows.
- `config/legacy_category_routes.php` — the generated 277-id allow-list.

---

## 8. Current host work still required

- Install and verify OPcache in the effective PHP-FPM web runtime.
- Enable and verify gzip (or an approved equivalent) at nginx, or correctly pass
  compression negotiation upstream.
- Apply and verify reviewed PHP-FPM pool limits in WHM/root configuration.
- Keep nginx private/full-page caching disabled for dynamic Laravel responses.
- Do not add application/full-page caching optimization in this remediation; it is
  explicitly deferred.
- Deploy and verify fixture-fallback removal, publication sitemap changes,
  accessibility changes, and origin/front-controller hardening.
- Make the current test set green and complete manual AR/EN browser accessibility QA.

None of these items is complete merely because the corresponding local code or
historical staging configuration exists.

---

## 8. Packaging the release — two traps that have caused outages

### 8.1 Never ship `bootstrap/cache/`

A release tarball built as:

```bash
tar czf app.tar.gz app bootstrap config database lang resources routes artisan composer.json composer.lock
```

carries **your machine's** `bootstrap/cache/packages.php`. Locally that manifest
lists dev packages; the server runs `composer install --no-dev`, so on boot it
tries to load e.g. `Laravel\Pail\PailServiceProvider`, which is not installed —
and every page returns 500.

This is only survivable when the deploy also runs `composer install`, because
`package:discover` regenerates the manifest. A code-only deploy that skips
composer inherits the stale file and takes the whole site down.

Exclude it, and let the server regenerate:

```bash
tar czf app.tar.gz --exclude=./bootstrap/cache \
  app bootstrap config database lang resources routes artisan composer.json composer.lock
```

Recovery, if it has already happened:

```bash
rm -f bootstrap/cache/{packages,services,config,routes-v7,events,blade-icons}.php
rm -rf bootstrap/cache/filament
php artisan optimize
```

### 8.2 Anchor every tar exclude

`--exclude=public` matches **every** path segment named `public`, including
`resources/views/public/`. See §E3 in `Docs/V2_PRE_CUTOVER_ACTIONS.md` — an
unanchored exclude silently produced a backup missing 118 view files. Always
write `--exclude=./public`, and verify before trusting the archive:

```bash
tar tzf app_code.tar.gz | grep -c resources/views/public/
```
