# SPU Website — Production Readiness Handoff

**Session date:** 22 August 2026
**Repository:** `SPU_Website`
**Branch:** `dev`
**Deployment status:** Local implementation complete for this remediation; deployment and host verification still pending.

## Executive Summary

The repository was reviewed against the migration reports, project architecture rules, the approved frontend reference, and the live rehearsal host.

The remediation work completed locally during this session covers:

- Public fixture/sample content removal.
- CMS publishing and authorization integrity.
- Legacy URL, host, proxy, sitemap, and SEO hardening.
- Accessibility and frontend interaction fixes.
- Dependency security updates.
- Global alumni routing.
- Research publish/retire policy.
- Duplicate-title publication policy.
- Application cache correctness and invalidation.

The application has **not been deployed** to v2 during this session. The hosting account still has shell/SSH disabled. The live v2 host currently returns HTTP 500 for dynamic pages, so deployment and live verification are mandatory before calling the site production-ready.

## Evidence

Latest local verification:

| Check | Result |
|---|---|
| `php artisan test` | **4,279 passed**, 24,784 assertions |
| `npm test` | **24 passed** |
| `npm run build` | Passed |
| `composer audit --locked` | No advisories |
| `npm audit` | 0 vulnerabilities |
| `php artisan route:list` | Passed; 167 routes |
| `php artisan config:cache` | Passed |
| `git diff --check` | Passed |

No commit was created. All changes remain in the local working tree on `dev`.

## Work Completed

### Security and Dependencies

- Updated vulnerable `guzzlehttp/guzzle` and `league/commonmark` versions.
- Updated the vulnerable development `nanoid` dependency.
- Added fixed canonical-host handling without reflecting arbitrary `Host` headers.
- Restricted trusted proxies to the documented local cPanel proxy addresses.
- Blocked direct public access to internal front controllers and unsupported PHP path variants.
- Added static and legacy-media security headers.
- Blocked browser-active legacy HTML, XML, SVG, and dangerous executable extensions.
- Added exact trusted-host validation for high-trust portal redirects.
- Added mandatory confirmed 2FA enrollment for configured privileged production roles.
- Isolated webhook replay state and rate-limit state from application cache invalidation.
- Preserved private storage and authorization for form attachments.

### Publishing and Authorization

- Scheduled page publishing now revalidates the current actor’s existence, lock state, role, permission, and faculty scope.
- Scheduled homepage publishing received equivalent checks.
- Faculty subpage cards now start as drafts instead of being published automatically.
- Faculty-card mutations now enforce authorization, scope, auditing, and cache invalidation.
- Faculty editors cannot bypass `publish-content` through card actions.
- Sensitive form-submission detail reads require an authorized actor at the service boundary.
- Filament database access was moved behind service contracts where required.
- CMS cache invalidation now runs after a successful database commit.
- Failed post-commit CMS invalidation can be retried through a queued job.

### Public Content Integrity

- Removed automatic public fallback to development JSON/sample content for affected Admissions, Campus Life, E-Services, Research, News Events/Gallery, and faculty-project paths.
- Unpublished CMS content no longer falls back to fake public content.
- Developer-facing generated research titles are excluded from public output.
- Real database research publications remain eligible for public display.
- Real database researcher profiles remain supported.
- Removed synthetic faculty project seeding from the public production path.
- Preserved fixture files only for editor defaults and tests; they are not automatic public content.

### Research Policy

The approved policy is:

- Publish only approved real migrated research publications.
- Hide or retire empty CMS-only research sections.
- Remove unavailable research links from navigation and footer output.
- Restore navigation only when meaningful bilingual content is published.
- Keep CMS-only centers, projects, themes, conferences, library, office, policies, and expert catalogs unavailable until reviewed content exists.
- Keep valid database-backed faculty research and researcher profiles available.

### Alumni Policy

Added localized global directories:

- `/ar/alumni`
- `/en/alumni`

The directory supports:

- Search.
- Pagination.
- Verified faculty, department, and graduation-year filters.
- AR/EN output with RTL/LTR behavior.
- Canonical, hreflang, SEO, sitemap, and structured metadata.
- No email, phone, student identifier, or per-record public detail URLs.

Legacy alumni handling is conservative:

- Reviewed global list signatures redirect to the global directory.
- Verified faculty codes may narrow the directory.
- Unverified record-shaped URLs remain honest 404s.
- No alumni record identity is guessed from legacy query parameters.

### Duplicate-Title Policy

- Research `duplicate_review` records are private by default.
- Including them requires the explicit `--include-duplicate-review` flag and normal publication approval.
- Distinct records retain deterministic source-ID-based slugs.
- Identical records require an explicit canonical or redirect disposition.
- Uncertain news duplicate groups remain private.
- News records proven materially distinct may remain separate with deterministic source-ID slugs.

### Accessibility and Frontend

- Desktop navigation supports keyboard, click, focus, and Escape interaction.
- Mobile menu and submenu controls expose disclosure state and focus behavior.
- Dynamic form fields now have deterministic IDs, labels, error relationships, invalid state, and live announcements.
- Removed inert `#` links where destinations are unavailable.
- Added iframe titles, social link labels, localized image alternatives, and improved breadcrumb semantics.
- Replaced runtime-composed Tailwind classes with statically discoverable classes.
- Preserved AR/RTL and EN/LTR behavior.

### Application Cache

The cache policy is now:

- Laravel may cache anonymous successful HTML internally.
- Browser, CDN, nginx, and proxy layers must not cache dynamic HTML.
- Dynamic responses return `Cache-Control: private, no-store, max-age=0`.
- Cache bypasses session flash/errors, old form input, authorization, no-cache requests, admin, preview, forms, and non-GET requests.
- CSRF tokens are masked before internal caching and restored per visitor.
- Supported query parameters are allowlisted and canonicalized.
- Cache keys include a release namespace.
- Tagged cache generations persist across PHP-FPM requests.
- Unsupported cache stores do not use broad `flushAll()` as a tag fallback.
- Webhook nonce and rate-limit stores are isolated from application cache.
- CMS invalidation runs after commit.
- Invalidation covers pages, homepage, CMS, news, research, facilities, profiles, media, settings, SEO, sitemap, navigation, About cards, and scheduled About cards.

Relevant documents:

- `Docs/PROVIDER_V2_INFRASTRUCTURE_REMEDIATION_RUNBOOK.md`
- `Docs/cache-adapter-hardening.md`
- `Docs/CURRENT_REMEDIATION_EXECUTION_CHECKLIST.md`

## What the Professor/Provider Must Do

### 1. Provide an Approved Deployment Channel

The account currently reports that shell/SSH access is disabled.

The provider must do one of the following:

- Enable jailed SSH for `spuedu`.
- Provide an auditable cPanel/CI deployment mechanism.
- Apply the deployment commands themselves and return redacted evidence.

Do **not** create a web shell, temporary HTTP command bridge, or cron-as-HTTP bridge.

The previously exposed cPanel token must be rotated. Do not place credentials in git, chat logs, shell history, or this document.

### 2. Deploy the Current Release

Deploy the complete release artifact from the current working tree, including:

- Application code.
- `composer.lock`.
- `package-lock.json`.
- Fresh `public/build` assets.
- Migrations.
- `.htaccess` and deployment configuration.
- New alumni, cache, CMS, and research files.

After deployment, run:

```bash
php artisan optimize:clear
php artisan migrate --force
php artisan optimize
php artisan route:list
php artisan launch:validate --environment=production
```

Do not run migrations or production writes until a database backup is confirmed.

### 3. Disable nginx Dynamic Caching

For `v2.spu.edu.sy`:

- Disable `fastcgi_cache` for Laravel responses.
- Disable `proxy_cache` for Laravel responses.
- Disable full-page/private HTML caching.
- Do not cache `/ar`, `/en`, `/admin`, previews, forms, registration pages, dynamic JSON, or responses containing cookies.
- Static versioned assets under `/build/assets/` may use long-lived public caching.

Verify with repeated requests that dynamic responses do not replay:

- `Set-Cookie` values.
- CSRF tokens.
- Flash messages.
- Old form input.
- Preview content.
- Draft content.
- Admin or private content.

### 4. Enable gzip at nginx

Enable gzip for at least:

```nginx
```

Run `nginx -t` before reload and record the result. Verify HTML, JSON, CSS, JavaScript, and SVG responses include `Content-Encoding: gzip` when large enough.

### 5. Install OPcache

Install for the actual v2 PHP runtime:

```text
WHM -> EasyApache 4 -> PHP Extensions -> ea-php84-php-opcache
```

Apply and verify:

```ini
opcache.enable=1
opcache.memory_consumption=192
opcache.max_accelerated_files=20000
opcache.validate_timestamps=1
opcache.revalidate_freq=60
```

Verify from the web PHP-FPM runtime, not only CLI PHP.

### 6. Tune PHP-FPM Carefully

Run the bounded test first. The reviewed candidate is:

```text
pm.max_children = 16
pm.max_requests = 1000
pm.process_idle_timeout = 60
```

Apply only if memory headroom and bounded load results support it. Record:

- Failed requests.
- Latency.
- Memory usage.
- Busy/idle workers.
- Effective pool configuration after reload.

### 7. Install Scheduler and Queue Cron

Use `flock` and the exact commands in the provider runbook:

```cron
* * * * * /usr/bin/flock -n /home/spuedu/spu_v2_app/storage/framework/scheduler.lock /opt/cpanel/ea-php84/root/usr/bin/php /home/spuedu/spu_v2_app/artisan schedule:run >> /home/spuedu/spu_v2_app/storage/logs/scheduler.log 2>&1
* * * * * /usr/bin/flock -n /home/spuedu/spu_v2_app/storage/framework/queue-default.lock /opt/cpanel/ea-php84/root/usr/bin/php /home/spuedu/spu_v2_app/artisan queue:work database --queue=default --stop-when-empty --max-time=50 --tries=3 --timeout=40 >> /home/spuedu/spu_v2_app/storage/logs/queue.log 2>&1
```

Verify:

```bash
php artisan schedule:list
php artisan queue:failed
crontab -l
```

Alert on new failed jobs and stale scheduler output.

### 8. Verify Operations

The provider/operator must verify and return redacted evidence for:

- Queue backlog and `failed_jobs`.
- Scheduler output freshness.
- Daily application log rotation and retention.
- Sentry DSN activation and one synthetic test event.
- Disk/quota headroom for application, logs, media, and cache.
- Privileged users’ confirmed TOTP enrollment.
- Database connectivity and least-privileged credentials.
- Actual nginx-to-Apache forwarded headers.
- HTTPS, HSTS, canonical host, and front-controller behavior.

Never share passwords, tokens, cookies, private keys, database URLs, or Sentry DSNs in the evidence.

## Content Actions After Deployment

### Research

1. Run a read-only inventory against the migrated production database.
2. Publish only reviewed real migrated publications through the approval-gated publishing command.
3. Keep duplicate-review research rows private unless explicitly reviewed.
4. Verify publication AR/EN coverage, titles, authors, dates, media, and URLs.
5. Confirm empty research sections are hidden from navigation.
6. Publish CMS content only when SPU provides reviewed bilingual content.

### Alumni

1. Verify imported enabled alumni counts and faculty relationships.
2. Confirm `/ar/alumni` and `/en/alumni` render without PII.
3. Re-probe reviewed `/alumni/index.php` list signatures.
4. Keep unverifiable record-shaped alumni URLs as 404 or document an approved 410 policy.

### Duplicate Records

1. Review every duplicate-title group using title, service, date, body, attachment, and source-ID evidence.
2. Mark each group as `retain`, `canonical`, `redirect`, or `private` using the existing approval workflow.
3. Keep uncertain records private.
4. For identical records, select one canonical record and create an exact redirect for the retired source URL.
5. For distinct records, retain source-ID-based slugs without adding artificial display suffixes.

## Final Release Gate

Do not approve production or cutover until all are true:

- Current release is deployed successfully.
- Dynamic nginx cache is disabled and verified.
- OPcache is enabled in web PHP-FPM.
- gzip is verified over HTTPS.
- PHP-FPM settings are verified after bounded testing.
- Queue, scheduler, logs, Sentry, disk, and 2FA evidence is recorded.
- Dynamic routes no longer return HTTP 500.
- `/ar`, `/en`, `/sitemap.xml`, `/robots.txt`, `/ar/alumni`, and `/en/alumni` are verified live.
- Research publication content is approved and published through the workflow.
- Empty research sections are not linked.
- Duplicate records have explicit dispositions.
- Legacy alumni list URLs are verified or explicitly retired.
- Manual AR/EN, mobile/desktop, keyboard, RTL/LTR, reduced-motion, and screen-reader QA passes.
- Rollback procedure and database backup are verified.
- Professor/provider gives named operational sign-off.
