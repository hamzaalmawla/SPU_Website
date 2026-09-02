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

**Resolved 2026-08-29 — the bare `/alumni` root.** The old homepage links
`alumni` as a relative path, and Apache answered it with a 301 to `/alumni/`.
That spelling had no rule and 404'd, because `alumni` was missing from the
unprefixed reference-route whitelist in `routes/web.php` while every sibling
section (`about`, `news`, `research`, …) was listed. Adding it to that one
alternation makes `/alumni` negotiate the visitor's locale and land on
`/ar/alumni` or `/en/alumni`, exactly like the other unprefixed section paths.
This is a routing fix, not a new redirect rule — the per-record policy above is
unchanged.

### A3 — Content held back pending review

Both are correct defaults; releasing them is an editorial call.

- **194 news articles** stay drafts, blocked as `incomplete_ar_content` — they
  have no usable Arabic body. Publishing them would put empty pages live.
- **36 research publications** are private pending duplicate-title review.
- **33 legacy categories** were rejected during import as duplicate titles.
  Their old URLs 404. Recoverable by re-running the approval packet allowing
  duplicates — but that publishes two articles with identical titles.

### A5 — Two legacy URLs with no destination, investigated 2026-09-02

Both were carried as "continuity gaps, 51 inbound links each". They are not the
same kind of problem, and neither is fixed by adding a redirect rule.

**`/index.php?dir=items&ex=2&lang={1|2}&page=list&service=11` — real content,
no destination.** It is the student card offers listing: discounts for SPU card
holders at restaurants, gyms and an ISP, paginated across 20 pages. Verified
against the live old site the same way the FAQ rule was: 1,892 visible
characters in Arabic against 1,205 for a nonsense `service` id on the identical
template, so this is content and not the empty shared shell.

Nothing on the new site covers it. `عروض`, `خصم`, `حسم` and `بطاقة` each appear
zero times across `/campus-life`, `/campus-life/services` and
`/campus-life/clubs-activities`. A 301 to campus services would satisfy a
coverage number and land 51 links' worth of visitors on a page that does not
have what they came for — which the continuity guide treats as a failure, not a
save. **The decision is editorial: recreate the offers content, or accept that
this section is retired.** Only once that is answered does a redirect rule have
a correct target. No rule has been added.

**`/index.php?dir=sites&...` — nothing to preserve.** Every spelling tried
returns the near-empty template: 1,418 visible characters against a 1,316
baseline, and exactly two internal links on the page, both to the homepage and
the contact form. There are no item links because there are no items — it was
already empty on the old site. Nothing is lost by leaving these unmapped, and a
redirect would be inventing a destination for a page that never had content.

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
`Accept-Encoding` upstream. Consequently neither the `mod_deflate` rules in
`public/.htaccess` nor `zlib.output_compression` in `public/.user.ini` could
fire — both were inert for the same reason.

**Superseded on 1 September, but still worth doing.** The application now
compresses its own HTML in `CompressPublicResponses`, so this is no longer a
launch blocker. Two things changed with it:

- `zlib.output_compression` has been **removed** from the deployed
  `public/.user.ini` and must not come back. It compresses at the SAPI output
  layer, after the middleware has already set `Content-Encoding: gzip` on a body
  it compressed itself, and neither can see the other: the pair emits
  `gzip(gzip(body))` under one header and every page becomes unreadable. The
  deploy now refuses to run if it finds that directive active.
- If nginx is fixed, set `COMPRESS_RESPONSES=false` and delete the middleware in
  the same change. Compressing in PHP costs worker CPU that nginx would not, so
  the edge is still the better place — it is just no longer the only place.

**Impact:** a public page is ~250 KB of HTML that would compress to roughly
30 KB — an ~8× reduction on the largest single transfer, and this audience is
largely on slow connections.

Either enable `gzip on` with `gzip_types text/html text/css application/javascript
application/json image/svg+xml`, or let Apache compress by passing
`Accept-Encoding` through.

Static assets are still shipped uncompressed either way — the middleware only
handles responses the application renders — so there is real work left here even
though HTML is now covered.

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

### B4 — What to actually send the host, and why they should say yes

B1, B2 and B3 have been open since 2026-08-21 with no provider movement. The
account-level server report obtained on 2026-08-31 changes the argument, because
it shows the machine is not under pressure:

| Measure | Value |
|---|---|
| CPU count | 96 |
| Server load | 3.26 (about 3% of capacity) |
| Memory used | 40.01% |
| Swap used | 0.65% |
| `sshd` | up |
| Database | MariaDB 10.11.18 |
| Apache / nginx | 2.4.68 / both running |

So this account is capped at five PHP workers on a box that is asleep. The
request is not for more resources; it is to stop throttling an account on
hardware that is 97% idle. Note also that `sshd` is running at server level —
enabling jailed shell for this account is a per-account setting, not a new
service, which is the whole of REM-07.

Suggested wording:

> We are preparing spu.edu.sy for a platform migration and need three
> account-level changes that require WHM. Measured from outside, the site
> degrades from 1.05s to a 7.9s median at twelve concurrent requests, while the
> server reports load 3.26 across 96 CPUs and 40% memory. The bottleneck is our
> account's configuration, not the machine.
>
> 1. Install and enable `ea-php84-php-opcache` for the `v2.spu.edu.sy` vhost.
>    There is currently no `opcache.so` anywhere under `/opt/cpanel/ea-php84`,
>    so roughly 8,000 PHP files are re-parsed on every request — a measured
>    333ms of pure framework boot per hit. Suggested values: `enable=1`,
>    `memory_consumption=192`, `max_accelerated_files=20000`,
>    `validate_timestamps=1`, `revalidate_freq=60`.
>
> 2. Enable gzip at nginx for `text/html`, `text/css`, `application/javascript`,
>    `application/json` and `image/svg+xml`, or pass `Accept-Encoding` through to
>    Apache. Requesting Apache directly with an explicit `Accept-Encoding: gzip`
>    still returns uncompressed bytes, so nginx is neither compressing nor
>    forwarding the negotiation. Our `mod_deflate` rules are already in place and
>    start working the moment either is done.
>
> 3. Raise the PHP-FPM pool for this account from `pm.max_children=5`,
>    `pm.max_requests=20`, `pm.process_idle_timeout=10` to `16`, `1000` and `60`.
>    Recycling a worker every twenty requests means an almost permanently cold
>    process. Note that cPanel's `LangPHP::php_set_vhost_versions` accepts these
>    parameters and silently ignores them, and
>    `/var/cpanel/userdata/spuedu/*.php_fpm.yaml` is root-owned, so this has to
>    be done in WHM.
>
> Separately: please enable jailed SSH for this account. `sshd` is already
> running on the server; we need an auditable way to deploy that is not a web or
> cron execution bridge.

`deploy/v2-staging/host-diagnostics.sh` collects the account-side evidence that
can be gathered without WHM. It is read-only and does not deploy anything.

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

**Before cutover, not after — run the destination probe:**

```bash
php artisan optimize:clear
php artisan continuity:validate-redirects --probe
```

`--probe` (added 2026-08-29) requests every active app-relative destination
through the application's own router and fails if any does not answer 200, or if
one redirects again when it should land in one hop. Rule validation on its own
cannot see a well-formed rule pointing at a page that no longer exists, which is
the failure that is hardest to notice from the outside.

Three campus-life destinations (`hospital`, `dental`, `clubs-activities`) are
editorial CMS content. They are live on the deployed site, but if that content is
ever unpublished the probe will report them — that is the check working.

**Two entry-point families to watch specifically**, both newly mapped in
§13 of `Docs/LEGACY_REDIRECT_MAINTENANCE_GUIDE.md`: the bare subsite roots
(`/med`, `/dent`, …) and the root `service=N` content lists. Both are linked
directly from the old homepage, so they will take real traffic immediately.

Also expect `/index.php?...page=good_students` (the old root honour page) to
appear in the queue. It is a deliberate 404 — the old page was empty and there is
no university-wide honour list to send it to. See §13.6.

---

## E. Deployment attempt, 2026-08-23 — rolled back

The release was deployed to `v2.spu.edu.sy` and **rolled back the same session**.
The site is running the pre-remediation code again and is fully healthy: 89
working navigation links, every legacy redirect landing on 200, media intact.

Recording this so the next attempt does not repeat it.

### E1 — Why it was rolled back: 30 dead navigation links

The release removes the public fixture fallback for Admissions, Campus Life and
E-Services as well as Research — but only Research has the matching
retire-from-navigation logic and the designed empty state.

With no CMS content published for the other sections, deploying produced:

| Section | Before deploy | After deploy |
|---|---|---|
| `/ar/admissions` and 9 sub-pages | 200 | **404** |
| `/ar/campus-life` and 13 sub-pages | 200 | **404** |
| `/ar/e-services` sub-pages (4) | 200 | **404** |
| `/ar/research/*` | 200 (placeholder) | 200 (empty state) — correct |

**30 of 73 navigation links returned 404**, and legacy redirects that target
`/campus-life/career-development/jobs` began landing on a 404 — the exact
"redirect that lands on a 404" failure the maintenance guide forbids.

Placeholder content is bad; a main menu that is 40% dead is worse. Hence the
rollback.

### E2 — What must happen before redeploying

Either publish reviewed bilingual CMS content for `admissions.*`,
`campus-life.*` and `e-services.*`, **or** give those sections the same
treatment Research already has:

- an `isAvailable` flag on the page DTO,
- the empty-state partial instead of a 404,
- retire-from-navigation when unavailable,
- and re-point any legacy redirect whose target would become unavailable.

Verify with a full navigation sweep and a legacy-redirect probe **before**
lifting maintenance mode, not after.

### E3 — The backup recipe in `deploy/v2-staging/README.md` was wrong

The rollback initially failed because the backup archive was created with:

```bash
tar czf app_code.tar.gz -C "$APP" --exclude=vendor --exclude=public ...
```

`--exclude=public` matches **every** path segment named `public`, so it silently
excluded all 118 files under `resources/views/public/` — the entire public view
tree. Restoring from that archive produced a half-reverted tree, and pruning
"files not in the backup" then deleted live views, taking the whole site to 500.

Two rules for any future rollback:

1. **Anchor tar excludes**: use `--exclude=./public` / `--exclude=./vendor`, and
   verify the archive contains what you expect before trusting it
   (`tar tzf archive.tar.gz | grep -c resources/views/public`).
2. **`tar x` does not delete.** Extracting an old archive over a newer tree
   leaves every file the deploy *added* in place, which mixes old classes with
   new views. Do a clean replace of the code trees, or rebuild the known-good
   tree from git — which is what finally restored service here
   (`git archive 8ce7913 app bootstrap config database lang resources routes …`).

The most reliable rollback source is git, not a hand-rolled tar: the deployed
commit is known, so `git archive <commit>` reproduces it exactly.

### E4 — Also fixed during the attempt

- `RATE_LIMIT_CACHE_DRIVER` is the **driver** for the `rate-limiter` store and
  already defaults to `file`. Setting it to the store name breaks cache
  resolution with `Driver [rate-limiter] is not supported`. Leave it unset.
- The release's `public/.htaccess` forces HTTPS to a hard-coded
  `https://spu.edu.sy`. On the staging host that 301s plain-HTTP traffic to the
  **live production site**. The staging overlay must rewrite that origin to
  `https://v2.spu.edu.sy` — the host guard alone does not prevent it, because the
  guard only rejects requests whose `Host` is not `v2.spu.edu.sy`.
- The release needs `storage/framework/cache/webhook` and
  `storage/framework/cache/rate-limiter` to exist and be writable.

---

## F. Deployment, 2026-08-24 — succeeded

The release is **deployed and live** on `v2.spu.edu.sy`.

What made this attempt work where 2026-08-23 failed: the content behind the
fixture removal was migrated instead of deleted, and the verification that had
been run *after* lifting maintenance was run before it.

### F1 — Content migrated, not deleted

`AuthoredPageContentSeeder` published **29 CMS targets** from the content that
already existed in the page services and settings — 10 Admissions, 14 Campus
Life, 5 E-Services. Nothing was invented; every payload came from
`getEditablePayload()` or the legacy settings group that used to back the page.

The seeder skips any target that already has published content, so re-running it
can never overwrite an editor's work.

### F2 — Result

| Check | Before (2026-08-23 attempt) | Now |
|---|---|---|
| Dead navigation links | 30 of 73 | **1** (`/ar/student-life`, pre-existing) |
| Legacy redirects landing on 404 | yes | **none** |
| Invented research publications public | 8 | **0** |
| Real migrated publications listed | mixed with placeholders | **clean** |
| Research landing | placeholder content | designed empty state |

`http://v2.spu.edu.sy` now redirects to `https://v2.spu.edu.sy`, not to
production — the staging overlay rewrites the release's hard-coded canonical
origin, as §E4 requires.

### F3 — Rollback point

`/home/spuedu/.spu_backups/20260824_124824` holds the code archive, `.env`,
web root, database dump, and `DEPLOYED_COMMIT`. The archive was verified to
contain all 118 `resources/views/public/` files before the deploy proceeded —
the check that §E3 exists to enforce.

### F4 — Still outstanding

Nothing in §A changed on the content side except that the sections now render
their real content. Still open:

- **§B** — the three WHM/root items (OPcache, nginx gzip, FPM pool). Untouched.
- **§A1** — research centres, projects, themes and researchers remain retired
  behind the empty state. They have no legacy equivalent, so they need SPU to
  author real bilingual content or an explicit decision to retire them.
- **§A2–A4** — unchanged.
- `/ar/student-life` 404s and is linked from navigation. It predates this work;
  either point it at `/ar/campus-life` or remove the menu entry.
