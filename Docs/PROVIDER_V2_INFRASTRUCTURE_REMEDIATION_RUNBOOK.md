# v2 Provider Infrastructure Remediation Runbook

Status: approved scope, provider execution pending (2026-08-22)

This runbook is for `v2.spu.edu.sy` only. It contains no credentials and makes
no deployment, host-change, cutover, or sign-off claim. Record the executor,
UTC time, command, result, and relevant output for every step. Store raw output
outside the repository and redact cookies, tokens, passwords, DSNs, database
values, and private keys before sharing it.

Deploy the current release artifact before running the final cache probes. The
repository now uses an internal application cache for anonymous HTML, but every
dynamic HTTP response deliberately returns `Cache-Control: private, no-store`.
That is intentional: Laravel may reuse its internal representation, while the
browser, nginx, CDN, and proxy must never share rendered HTML, CSRF, session,
flash, preview, form, or personalized state.

## Approved Decisions

| Decision | Required outcome |
|---|---|
| Edge caching | nginx dynamic, private, `fastcgi_cache`, `proxy_cache`, and full-page caching are disabled for v2. Caching is allowed for versioned static assets only. Laravel application cache behavior is not changed by this runbook. |
| Deployment access | Enable jailed SSH for the cPanel account, or use an approved auditable deployment mechanism. Do not create a web command bridge, temporary web shell, or cron-as-HTTP bridge. |
| PHP runtime | Install `ea-php84-php-opcache` and apply the reviewed OPcache values below. |
| Compression | Enable nginx gzip for HTML/text, JSON, CSS, JavaScript, and SVG responses. |
| PHP-FPM | Do not change pool sizing until the bounded load check passes and memory headroom is reviewed. |
| Scheduler and queue | Install the two locked cron entries from `deploy/v2-staging/README.md` exactly as shown below. |

## Ownership Boundary

### cPanel account user

- Use cPanel or the approved jailed-SSH/deployment path to inspect the v2
  release, run the PHP and Artisan probes, install the two cron entries, and
  capture application logs.
- The account user cannot install PHP extensions, change nginx, or change the
  root-owned PHP-FPM pool. Do not work around that boundary with a web bridge.

### WHM/root/provider

- Enable jailed SSH or provide the approved deployment mechanism.
- Change nginx cache and gzip configuration, install the EasyApache OPcache
  package, and reload nginx/PHP-FPM.
- Review and, only after the bounded test, change the PHP-FPM pool.
- Verify host log rotation, quota/disk headroom, and the provider-side Sentry
  delivery path. Retain root-only configuration output outside git.

## 1. Capture Before Evidence

Run as the cPanel account after the provider has supplied the approved access
path. The existing v2 layout is:

```bash
APP=/home/spuedu/spu_v2_app
PHP=/opt/cpanel/ea-php84/root/usr/bin/php
BASE=https://v2.spu.edu.sy
EVIDENCE="$HOME/v2-remediation-$(date -u +%Y%m%dT%H%M%SZ)"
umask 077
mkdir -p "$EVIDENCE/before" "$EVIDENCE/after"
cd "$APP"

php_probe() {
    command -v flock > "$EVIDENCE/$PHASE/flock.path.txt"
    crontab -l > "$EVIDENCE/$PHASE/crontab.txt" 2>&1 || true
    "$PHP" -v > "$EVIDENCE/$PHASE/php-version.txt"
    "$PHP" -m > "$EVIDENCE/$PHASE/php-modules.txt"
    "$PHP" --ri opcache > "$EVIDENCE/$PHASE/php-opcache.txt" 2>&1 || true
    "$PHP" -r '$status = function_exists("opcache_get_status") ? opcache_get_status(false) : false; var_export(["sapi" => PHP_SAPI, "version" => PHP_VERSION, "opcache_loaded" => extension_loaded("Zend OPcache"), "opcache_enabled" => is_array($status) ? ($status["opcache_enabled"] ?? null) : null]);' > "$EVIDENCE/$PHASE/php-runtime.txt"
    "$PHP" artisan about > "$EVIDENCE/$PHASE/artisan-about.txt"
    "$PHP" artisan schedule:list > "$EVIDENCE/$PHASE/schedule-list.txt"
    "$PHP" artisan queue:failed > "$EVIDENCE/$PHASE/queue-failed.txt"
    "$PHP" artisan tinker --execute='dump(config("logging.default"), config("logging.channels.daily.days"), config("queue.default"));' > "$EVIDENCE/$PHASE/runtime-settings.txt"
}

PHASE=before
php_probe
```

The PHP command output is a CLI probe only. The provider must also capture the
effective v2 PHP-FPM configuration and, after the change, verify the web FPM
runtime through the provider's existing cPanel/WHM PHP-FPM diagnostic. Do not
add a public `phpinfo` route or diagnostic file.

Use this curl probe for both `before` and `after`. The same paths and headers
must be used in both runs. Header output is filtered so `Set-Cookie` values are
never recorded.

```bash
probe() {
    name="$1"
    path="$2"
    curl --silent --show-error --fail-with-body --http1.1 --max-time 20 \
        -H 'Accept-Encoding: gzip' \
        -D "$EVIDENCE/$PHASE/$name.headers" \
        -o "$EVIDENCE/$PHASE/$name.body" \
        "$BASE$path"
    awk 'BEGIN { IGNORECASE=1 } \
        /^HTTP\// || /^(content-type|content-encoding|cache-control|content-length|vary|age|etag|x-cache):/ { print } \
        /^set-cookie:/ { print "set-cookie: PRESENT (value redacted)" }' \
        "$EVIDENCE/$PHASE/$name.headers"
    wc -c "$EVIDENCE/$PHASE/$name.body"
}

# The probe above stores the body exactly as it arrived, so `wc -c` reports
# bytes on the wire — which is the number that matters here. To read a
# compressed body, re-run the same URL with `--compressed` and curl will decode
# it; comparing the two byte counts gives the compression ratio directly.

PHASE=before
probe homepage-ar /ar
probe homepage-en /en
probe admin-login /admin/login
probe health /up
probe manifest-json /build/manifest.json
probe static-css /css/filament/filament/app.css
probe static-js /js/filament/filament/app.js
probe static-svg /images/time.svg
```

If a release uses different CSS/JS paths, use the deployed
`/build/manifest.json` paths and record the replacements. Do not replace a
failed asset probe with an invented or placeholder URL.

Before evidence must include the provider's nginx view showing the current
`fastcgi_cache`, `proxy_cache`, gzip, and static-cache directives, plus the
effective v2 PHP-FPM pool values. Do not copy a full nginx dump into git.

## 2. Apply Provider Changes

The provider performs these actions as WHM/root. Save a redacted before/after
configuration excerpt and the reload/test output.

### nginx cache boundary and gzip

- In the v2 nginx location, disable dynamic caching with `fastcgi_cache off;`
  and `proxy_cache off;`, or the equivalent provider configuration. Remove or
  bypass inherited cache zones for Laravel HTML, sessions, CSRF, admin,
  preview, forms, and other private responses.
- Permit caching only for versioned static asset paths such as
  `/build/assets/` and other provider-approved immutable static paths. Do not
  cache `/ar`, `/en`, `/admin`, preview pages, form responses, or dynamic JSON.
- Enable gzip with at least these types:

```nginx
gzip on;
gzip_vary on;
gzip_min_length 1024;
gzip_types text/plain text/html text/css application/json application/javascript text/javascript image/svg+xml;
```

- Test the provider-generated configuration before reload. Reload only after
  the test passes. Before and after the change, record only the relevant
  provider configuration lines and the syntax result:

```bash
nginx -T 2>&1 | grep -nE 'server_name|fastcgi_cache|proxy_cache|gzip|gzip_types|expires|Cache-Control'
nginx -t
```

Use the provider's cPanel nginx reload procedure after `nginx -t` passes; do
not edit generated configuration blindly.

### ea-php84 OPcache

Install in WHM:

```text
WHM -> EasyApache 4 -> PHP Extensions -> ea-php84-php-opcache
```

Apply these reviewed values to the effective v2 `ea-php84` FPM runtime:

```ini
opcache.enable=1
opcache.memory_consumption=192
opcache.max_accelerated_files=20000
opcache.validate_timestamps=1
opcache.revalidate_freq=60
```

Restart or reload the v2 PHP-FPM service after the module/settings change.
The web-FPM probe, not `php -m` alone, is the close condition.

As root, retain the effective v2 pool excerpt and FPM syntax result outside the
repository:

```bash
find /var/cpanel/userdata/spuedu -maxdepth 1 -type f -name '*.php_fpm.yaml' \
    -print -exec grep -nH -E 'pm(_|\.)?(max_children|max_requests|process_idle_timeout)|opcache\.' {} \;
/opt/cpanel/ea-php84/root/usr/sbin/php-fpm -tt
```

This file/configuration probe still does not replace the provider's web-FPM
diagnostic. It proves which root-owned pool configuration was parsed.

### PHP-FPM pool

Do not apply pool changes before the bounded test in the next section. The
reviewed candidate is:

```text
pm.max_children = 16
pm.max_requests = 1000
pm.process_idle_timeout = 60
```

These are not unconditional values. Keep the existing values and record the
reason if the provider's memory review or bounded test does not support the
candidate.

## 3. Bounded PHP-FPM Check

Run a short, fixed test against the staging v2 host before changing pool
limits. Do not use an open-ended benchmark or production traffic generator.

```bash
/usr/bin/ab -n 120 -c 4 -H 'Accept-Encoding: gzip' "$BASE/ar" | tee "$EVIDENCE/before/bounded-load.txt"
free -m | tee "$EVIDENCE/before/memory-after-load.txt"
```

The provider records failed requests, latency, PHP-FPM busy/idle workers, and
available memory. Apply the candidate only if the test has no application
errors and the provider's bounded capacity review approves it. Then reload
FPM and repeat the same command into `after/bounded-load.txt`. Record the
effective pool values; a successful reload alone is not proof that cPanel
accepted root-owned values.

## 4. Install the Locked Cron Entries

Confirm the lock binary first:

```bash
test "$(command -v flock)" = /usr/bin/flock
```

As the cPanel account user, or as the provider on behalf of that account, add
these two lines to the v2 account crontab exactly. They are copied from
`deploy/v2-staging/README.md` and must not be replaced with an HTTP request:

```cron
* * * * * /usr/bin/flock -n /home/spuedu/spu_v2_app/storage/framework/scheduler.lock /opt/cpanel/ea-php84/root/usr/bin/php /home/spuedu/spu_v2_app/artisan schedule:run >> /home/spuedu/spu_v2_app/storage/logs/scheduler.log 2>&1
* * * * * /usr/bin/flock -n /home/spuedu/spu_v2_app/storage/framework/queue-default.lock /opt/cpanel/ea-php84/root/usr/bin/php /home/spuedu/spu_v2_app/artisan queue:work database --queue=default --stop-when-empty --max-time=50 --tries=3 --timeout=40 >> /home/spuedu/spu_v2_app/storage/logs/queue.log 2>&1
```

The queue timeout must remain below the 50-second worker lifetime and below
the configured `retry_after`. Verify the entries with `crontab -l`, the lock
files, and fresh scheduler/queue log timestamps. Review `queue:failed` daily;
alert on every new row and retry an ID only after its cause is reviewed.

## 5. After Probes and Operations Verification

In the same shell session, repeat the PHP probe and curl function after the
provider changes. This records a separate after-evidence set:

```bash
PHASE=after
php_probe
probe homepage-ar /ar
probe homepage-en /en
probe admin-login /admin/login
probe health /up
probe manifest-json /build/manifest.json
probe static-css /css/filament/filament/app.css
probe static-js /js/filament/filament/app.js
probe static-svg /images/time.svg
```

Expected results:

- `/ar`, `/en`, `/admin/login`, and `/up` do not show an nginx private/full-
  page cache hit or `Age` replay. `X-Cache: HIT` from Laravel's application
  middleware is not nginx evidence and is not by itself a failure.
- Dynamic responses do not replay a prior session cookie, CSRF token, preview,
  draft, admin response, or private content.
- `/build/manifest.json`, CSS, JS, and SVG return the expected content type,
  and approved static paths may include public cache headers.
- Compression is measured on **static** paths only. Since 1 September the
  application compresses its own HTML in `CompressPublicResponses`, so
  `Content-Encoding: gzip` on `/ar` proves nothing about nginx — PHP put it
  there. A static file like `/build/manifest.json` never touches PHP, so it is
  the only honest test of whether the edge compresses. If a large static asset
  comes back without `Content-Encoding: gzip`, the edge still does not
  compress, whatever `/ar` says.
- PHP-FPM reports Zend OPcache loaded/enabled with the five reviewed values.
  The provider's FPM/web-runtime evidence must be attached; CLI evidence alone
  cannot close this item.

Run and record:

```bash
"$PHP" artisan schedule:list
"$PHP" artisan queue:failed
stat -c '%y %s %n' "$APP/storage/logs/scheduler.log" "$APP/storage/logs/queue.log"
tail -n 50 "$APP/storage/logs/scheduler.log" "$APP/storage/logs/queue.log"
find "$APP/storage/logs" -maxdepth 1 -type f -printf '%TY-%Tm-%Td %TH:%TM %s %p\n' | sort
df -h "$APP" "$APP/storage"
du -sh "$APP/storage"
"$PHP" artisan tinker --execute='dump(config("logging.default"), config("logging.channels.daily.days"), config("sentry.dsn") !== null, config("sentry.environment"));'
```

For host log rotation, the provider records the active cPanel/system log
rotation policy, retention, and a non-destructive dry run where supported.
Application logging must remain `daily` with the reviewed retention baseline
of 14 days unless a different retention is approved and recorded.

For Sentry, run an approved synthetic message with a unique non-secret marker,
then confirm the matching event in the correct project/environment. Record the
event ID and timestamp only:

```bash
PROBE_ID="v2-remediation-$(date -u +%Y%m%dT%H%M%SZ)"
"$PHP" artisan tinker --execute="echo (string) \\Sentry\\captureMessage('$PROBE_ID');"
```

Do not print or record `SENTRY_LARAVEL_DSN`.

For privileged 2FA, verify configuration and status without printing secrets:

```bash
"$PHP" artisan tinker --execute='dump(config("auth.two_factor.require_for_privileged_roles"), config("auth.two_factor.privileged_roles"));'
"$PHP" artisan tinker --execute='dump(App\Models\User\User::query()->whereHas("role", fn ($query) => $query->whereIn("slug", config("auth.two_factor.privileged_roles")))->get(["id", "two_factor_enabled", "two_factor_confirmed_at"])->toArray());'
```

The owner of each privileged account must also complete one supervised login,
TOTP challenge, logout, and challenged re-login. Recovery codes remain offline
and must not be put in the evidence record.

## 6. Evidence Record

Attach a redacted transcript or ticket entry with these fields:

```text
Environment/host:
Executor and role (cPanel user or WHM/root/provider):
UTC start/end:
Approved deployment mechanism (name only, no credentials):
Before probe directory/hash:
After probe directory/hash:
nginx cache result and reload/test output:
gzip result and representative content types:
OPcache package/settings and FPM/web-runtime output:
Bounded load command, failed requests, latency, memory result:
Effective PHP-FPM pool values:
Crontab verification and scheduler/queue log timestamps:
Failed-job count and review result:
Log rotation policy/retention:
Sentry event ID and UTC timestamp:
Disk/quota result:
Privileged 2FA result:
Rollback readiness and saved configuration references:
Open failures/owner/next action:
```

Do not mark a checklist row complete from a command that was not run on the
target host. Local tests and historical staging output are not provider
evidence.

## 7. Rollback

Rollback is provider-led and must be recorded with the same evidence rules:

1. If nginx validation or requests fail, restore the saved v2 nginx include,
   run the provider's nginx config test, and reload only after it passes. Do
   not restore dynamic/private/full-page caching as a workaround.
2. If OPcache causes errors, disable the new OPcache settings or extension in
   WHM, restart the v2 PHP-FPM service, and rerun the PHP/curl probes. Keep the
   before/after output showing the effective runtime.
3. If the FPM change causes saturation or errors, restore the prior pool
   values, reload FPM, and record the bounded retest. Do not increase limits
   again without a new bounded load check.
4. If scheduler or queue duplication/failure occurs, remove only the two new
   v2 cron lines after reviewing active jobs and failed jobs. Preserve logs,
   locks, and job IDs; do not retry blindly.
5. If the approved SSH mechanism is unsafe or unavailable, disable it through
   the provider and use the separately approved deployment mechanism. Never
   substitute a web bridge.
6. An application release or database rollback uses the approved deployment
   and backup procedure, not this infrastructure runbook. Record the release
   identity, backup reference, owner approval, probes, and resulting status.

After rollback, rerun the required before/after probes as a new evidence set,
update `Docs/CURRENT_REMEDIATION_EXECUTION_CHECKLIST.md`, and leave deployment,
cutover, and sign-off unclaimed until the remaining gates are independently
closed.
