# v2.spu.edu.sy — actions required before cutover

> Current-state note (2026-08-21): this document preserves verified staging
> findings, but the application working tree has moved since that observation.
> Use `Docs/CURRENT_REMEDIATION_EXECUTION_CHECKLIST.md` for execution status. No
> deployment, cutover approval, or sign-off is claimed.

Everything on this list is **outside what a deployment can decide**. Each item is
either a content decision that belongs to SPU, or a server change that needs
WHM/root. All of them are verified findings, not guesses — the evidence is given
so nobody has to rediscover it.

Ordered by consequence if shipped as-is.

---

## A. Content decisions

### A1 — Historical staging finding: placeholder content was publicly live

At the time of the staging inspection, the research section rendered from
`resources/data/research-content.json`, a fixture written during frontend
development. It was the app's fallback when nothing was published in the CMS,
and the inspected database had no matching rows:

```
cms_target_contents where target_key like 'research.%'  →  0 rows
```

The following were therefore live at `v2.spu.edu.sy` and were **not SPU content**:

| Section | Placeholder entries | Example |
|---|---|---|
| Research centres | 3 | `ai-digital-innovation`, `clinical-research-simulation` |
| Research projects | 5 | `ai-dental-diagnostics-system`, `arabic-clinical-nlp-system` |
| Research themes | 12 | `ai-ml`, `clinical-medicine` |
| Researchers | 9 | — |
| Publications | 8 | `ai-dental-diagnostics`, `renewable-energy-integration-syrian-grid` |
| Stats | 4 | — |

The publications archive showed **261 items: 253 real migrated publications plus
these 8 placeholders**, and the placeholders sorted to the top of page 1, so the
first thing a visitor saw on the research archive was invented content.

`NavigationSeeder` also linked to them, so the inspected site menu pointed at
placeholder pages alongside real ones.

**Current remediation.** Public runtime fallback use has now been removed locally
for the affected research paths and other remediated fixture-backed areas. The
fixture files/readers may still exist for editor defaults or unrelated code, so do
not report that all fixture files were deleted. This code is not deployed.

Removing public fixture output does not supply real content. Before launch, SPU
must publish reviewed AR/EN CMS/database content or explicitly retire each empty
section and remove/redirect its navigation and routes. Do not restore fixture
fallbacks merely to avoid an empty state.

**Why this was not fixed during the historical staging pass.** Removing the placeholders was not a one-line
change: they are referenced by the seeded navigation, and for centres, projects
and themes there is *no* legacy equivalent to replace them with — the old site
had none. Deleting them empties those sections entirely. That is an information
architecture decision for SPU, not a deployment fix, and doing it unilaterally
would leave the site half-consistent.

**What to do.** Decide per section:

- **Publish real content** through the admin for `research.centers`,
  `research.projects`, `research.themes`, `research.publications`. Publishing a
  CMS payload replaces the fixture entirely — for publications, publishing one
  with an empty `items` array leaves only the 253 real records.
- **Or retire the section** and remove its navigation entries.

The historical staging build also omitted eligible database publications from the
sitemap when the synthetic `research.publications` CMS target was unpublished.
That sitemap code has been fixed locally: real published database records are now
independently eligible while drafts remain excluded. Deployment and
production-like sitemap verification are still pending; the historical count is
not a current production claim.

The current remediation also removes the affected public fallback behavior for
`news-events-content.json`, `news-gallery-content.json`, and
`frontend-faculty-projects.json`. Deployment/content verification remains under
REM-01 and REM-02.

### A2 — Alumni URLs have nowhere to go

The localized global directory is now available at `/ar/alumni` and `/en/alumni`
when enabled, named alumni records exist. The reviewed list signatures
`/alumni/index.php?page=list&ex=2&dir=graduated_students&lang={1|2}&d={2..7}`
redirect to the matching localized directory with a verified faculty filter.
Unknown `/alumni/**` paths, record/detail guesses, and unverified query variants
remain honest 404s and are logged for triage. The legacy `d` value is a faculty
code, not an alumni record ID, so no per-record continuity is claimed.

### A3 — Content held back pending review

Both are correct defaults; releasing them is an editorial call.

- **194 news articles** stay drafts, blocked as `incomplete_ar_content` — they
  have no usable Arabic body. Publishing them would put empty pages live.
- **36 research publications** are private pending duplicate-title review.
- **33 legacy categories** were rejected during import as duplicate titles.
  Their old URLs 404. Recoverable by re-running the approval packet allowing
  duplicates — but that publishes two articles with identical titles.

### A4 — The super admin cannot publish content

`publish-content` grants only to the `editor` role, so a `super_admin` sees no
publish buttons in the admin panel. I did not change the policy;
`content.editor@spu.edu.sy` exists as the migration's publishing actor. Decide
whether the gate is intentional.

---

## B. Server changes — need WHM / root

Send this section to the host. All three were verified from inside the account;
none is fixable from cPanel.

cPanel shell/Terminal and SSH are disabled. Do not assume these commands can be
run by the application account, and do not create a temporary web or cron command
bridge without separate security approval. The host/operator must perform and
evidence the root/WHM changes.

### B1 — Install OPcache for `ea-php84`

```
WHM → EasyApache 4 → PHP Extensions → ea-php84-php-opcache
```

**Evidence:** there is no `opcache.so` anywhere under `/opt/cpanel/ea-php84`, and
`php -m` lists no Zend extensions. Framework boot measures **333 ms on every
single request** because ~8,000 PHP files are re-parsed each time.

**Impact:** typically 2–4× on time-to-first-byte. This is the single
highest-value change available on this server.

Suggested settings once installed:

```ini
opcache.enable=1
opcache.memory_consumption=192
opcache.max_accelerated_files=20000
opcache.validate_timestamps=1
opcache.revalidate_freq=60
```

### B2 — Enable gzip in nginx

**Evidence:** nginx terminates TLS on :80/:443. Requesting Apache directly from
the server with an explicit `Accept-Encoding: gzip` still returns
`Content-Length: 328750` uncompressed — so nginx neither gzips nor forwards
`Accept-Encoding` upstream. Consequently **nothing on the site is compressed**,
and neither the `mod_deflate` rules in `public/.htaccess` nor
`zlib.output_compression` in `public/.user.ini` can fire.

**Impact:** a public page is ~250 KB of HTML that would compress to roughly
30 KB — an ~8× reduction on the largest single transfer, and this audience is
largely on slow connections.

Either enable `gzip on` with `gzip_types text/html text/css application/javascript
application/json image/svg+xml`, or let Apache compress by passing
`Accept-Encoding` through. The application-side configuration is already correct
and starts working the moment either is done — **do not delete it as dead code.**

### B3 — Raise the PHP-FPM pool limits

Current pool for `v2.spu.edu.sy`:

```
pm_max_children = 5      pm_max_requests = 20      pm_process_idle_timeout = 10
```

Recycling a worker every **20 requests** means a cold PHP process almost
constantly, which — with no OPcache — re-parses the entire framework each time.
Five workers also caps concurrency hard.

Suggested: `pm_max_children = 16`, `pm_max_requests = 1000`,
`pm_process_idle_timeout = 60`.

**Note for whoever tries this:** cPanel's `LangPHP::php_set_vhost_versions`
accepts these parameters and returns success but **silently ignores them**, and
`/var/cpanel/userdata/spuedu/*.php_fpm.yaml` is root-owned. It has to be done in
WHM.

---

## C. At cutover

Do not execute this section until the current checklist is complete and an
explicit cutover decision is recorded. Canonical host/proxy/front-controller code
changes are pending deployment verification; the current local suite is green,
and accessibility browser QA is pending.

1. Remove the two `STAGING ONLY` blocks from the deployed `public/.htaccess`
   (noindex header, host guard). **Keep `Options +FollowSymLinks`** — the legacy
   media symlinks depend on it.
2. Delete the deployed `public/robots.txt` so the application serves its own.
3. Point the domain at the Laravel `public/` root **only while the old static
   trees stay reachable** — see `deploy/v2-staging/README.md` §4.
4. Set the vhost to PHP 8.2+.
5. Re-run `php artisan optimize` and `php artisan continuity:validate-redirects`.
6. Re-probe redirect coverage against the old sitemap —
   `Docs/LEGACY_REDIRECT_MAINTENANCE_GUIDE.md` §7.
7. Keep nginx private/full-page caching disabled for dynamic Laravel responses.
   Additional application/full-page cache optimization is explicitly deferred.

---

## D. Watch after cutover

`unresolved_legacy_requests` is the triage queue. Every URL the redirect engine
cannot place lands there with its normalised shape, subsite and old language id.
922 rows are already recorded from probe runs.

Sort by `hit_count` descending in the first 48 hours: anything with real traffic
is a URL worth mapping that the migration missed.
