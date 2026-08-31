# Legacy Redirect & Migration — Maintenance Guide

**Read this before changing routing, the front controller, `public/.htaccess`, any
`App\Services\Legacy\**` class, `ContinuityService`, `SitemapService`, or any legacy import.**

The old SPU site had **28,418 public URLs**. They are answered today by a chain of
narrow, deliberate rules. Most of them are *not* covered by an obvious route, and
several look like dead code until you understand what they hold up. This document
exists so the next person does not silently break 27,000 redirects with a
one-line "cleanup".

Last verified: 2026-08-21 · 97.3% of a 2,000-URL random sample resolving · 0 broken redirects.

> **Before cutover, read `Docs/V2_PRE_CUTOVER_ACTIONS.md`.** It lists the content
> decisions and the three server changes that need WHM/root — including placeholder
> research content that is currently public.

---

## 1. The five invariants

If you change nothing else in this document, honour these. Each one has already
been broken once, and each cost real coverage.

### 1.1 The front controller is `public/app.php`, **not** `index.php`

`/index.php?page=…&lang=…&service=…` is the single largest legacy URL family —
about **14,000 of the 28,418 URLs**. If the front controller is named
`index.php`, Apache serves those requests *as the script*:

```
REQUEST_URI  = /index.php?page=show&…
SCRIPT_NAME  = /index.php
→ Symfony computes baseUrl = "/index.php"
→ $request->path() collapses to "/"
→ RedirectContinuityMiddleware never sees the real path
→ every generated redirect is prefixed: https://spu.edu.sy/index.php/ar?…
```

**Never rename `app.php` back to `index.php`.** `public/index.php` is kept only so
`artisan serve` and non-Apache environments work; `public/.htaccess` explicitly
routes a literal `/index.php` request through `app.php` so it stays an ordinary
request path.

If you ever see redirects containing `/index.php/` in the destination, this is why.

### 1.2 Unresolved means 404, never a "helpful" fallback

Do **not** add `Route::fallback()` sending unknown URLs to `/`, and do not add a
catch-all rule to any resolver. Google treats mass redirect-to-homepage as a soft
404, and — more importantly — a swallowed URL disappears from
`unresolved_legacy_requests`, which is the only triage queue there is.

A URL that cannot be placed **must** return a real 404 so it gets logged.

### 1.3 A legacy `service` number belongs to exactly one subsite

The old CMS encoded both facts in one integer:

```
service_type = subsite_index * 10 + kind
```

| subsite | index | subsite | index |
|---|---|---|---|
| root | 0 | `admin` (Business faculty) | 7 |
| `med` | 2 | `research` | 8 |
| `dent` | 3 | `hospital` | 9 |
| `pharm` | 4 | `dent_clinic` | 10 |
| `info` (AI) | 5 | `alumni` | 11 |
| `petrol` | 6 | `clubs` | 12 |

`/med/index.php?service=3` is a **mismatch** — service 3 is a root service — and
must not resolve. `LegacySubsiteContentQueryResolver::serviceMatchesSubsite()`
enforces this with `intdiv($service, 10) === $subsiteIndex`. Removing that check
makes any subsite accept any service whose last digit happens to match a valid
kind. Three tests cover it; do not "simplify" them away.

### 1.4 Legacy media columns hold a **path**, not a filename

The old CMS stored `jx_categories.photo` / `jx_items.photo` / `jx_councils.cv` as
a **bare filename** and prefixed the download directory when rendering. Every
`legacy_*_path` column on the new side is consumed as a path by
`MediaUrlResolver::resolveLegacy()`.

Write a bare filename into one of those columns and it resolves to
`/1494920895_5171123882.jpg` at the web root → 404 → broken image.

`LegacyNewsImportService::legacyPhotoPath()` puts the directory back, using
`config('legacy_media.photo_directory')` (default `downloads/files`). Any new
importer touching a legacy media column must do the same.

### 1.5 The private members archive stays private

`/members/` held staff records and their publications. Those URLs may resolve to
the **public section that replaced them** (`/about/directorates/staff`,
`/research/publications`) but must **never** resolve to a specific imported staff
record. The boundary is enforced in two places, and both must stay:

- `LegacyQueryResolverRegistry::resolve()` — offers members URLs only to the
  section-level resolver.
- `ContinuityService::resolveRedirect()` — routes members URLs to the query
  resolver rather than returning a blanket `null`.

---

## 2. How a request is resolved

Order matters: cheap and certain first, guesses never.

```
Apache (public/.htaccess)
 ├─ 1. STAGING ONLY: host guard, X-Robots-Tag noindex        ← remove at cutover
 ├─ 2. Force HTTPS  (X-Forwarded-Proto guard prevents a loop behind nginx)
 ├─ 3. Block script execution inside preserved legacy trees  (.php/.sql/.bak/dotfiles → 404)
 ├─ 4. Legacy static continuity: /images/…, /downloads/files/…, /{faculty}/images/…
 │     internally rewritten into _legacy/…  (200 at the ORIGINAL url, never a redirect)
 │     guarded by !-f so a same-named new-site file always wins
 ├─ 5. /index.php  → app.php   (see invariant 1.1)
 └─ 6. everything else → app.php

Laravel — RedirectContinuityMiddleware (prepended, runs before routes)
 └─ ContinuityService::resolveRedirect()
     ├─ retired language (old ids 3/6/7)  → 302 /en          ← approved policy, NOT 301
     ├─ unknown legacy language            → null (404)
     ├─ private members archive            → section-level resolver only
     ├─ reference .html alias              → config/reference_html_aliases.php
     └─ followRedirectChain()  (max 5 hops, loop-checked, destination allowlisted)
         ├─ resolveExactMatch()        legacy_exact_redirects (path + query_signature)
         ├─ resolveLegacyQueryMatch()  → LegacyQueryResolverRegistry
         └─ resolvePatternMatch()      legacy_pattern_rules (regex on PATH only)

LegacyQueryResolverRegistry — first match wins, in this order
 1. LegacySubsiteHomeQueryResolver     bare subsite home
 2. LegacyFunctionalRouteQueryResolver exact reviewed query signatures
 3. LegacyCategoryRouteQueryResolver   per-record: const map + generated allow-list
 4. LegacyNewsQueryResolver            per-record: imported article by legacy id
 5. LegacyResearchQueryResolver        per-record: publication by legacy id
 6. LegacySubsiteContentQueryResolver  section-level equivalent  ← MUST STAY LAST

unresolved → 404 → middleware terminate() logs it to unresolved_legacy_requests
```

**Resolver 6 must stay last.** It answers at section level; if it ran earlier it
would pre-empt the resolvers that can name the exact record.

---

## 3. The pieces, and what each is load-bearing for

| File | Holds up | Do not |
|---|---|---|
| `public/app.php` | ~14,000 root `/index.php` URLs | rename to `index.php` |
| `public/.htaccess` | legacy media, HTTPS, script blocking | drop the `!-f` guards or the `_legacy` blocks |
| `routes/web.php` reference route | keeps legacy `.php` paths out | remove the `(?!.*\.php$)` lookahead |
| `LegacySubsiteContentQueryResolver` | ~8,500 faculty/subsite URLs | add root `items`, or drop the decade check |
| `LegacyCategoryRouteQueryResolver` | 277 root categories | turn the allow-list into a service-wide rule |
| `config/legacy_category_routes.php` | the allow-list itself | hand-edit (regenerate — see §5) |
| `LegacyNewsQueryResolver` | ~5,000 news URLs | remove the locale fallback |
| `LegacyNewsImportService::legacyPhotoPath()` | every migrated image | bypass on a new import path |
| `ContinuityService` members branch | ~3,400 members URLs | restore the blanket `return null` |
| `SitemapService::publicationSlugs()` | 522 publication URLs | read only page 1 of the archive |

### Why `routes/web.php` needs that lookahead

The unprefixed reference route matches `about|admissions|research|campus-life|…`.
Without the leading `(?!.*\.php$)`, a legacy URL like
`/research/index.php?dir=items&page=show` matches it and 302s to
`/ar/research/index.php` — **a redirect that lands on a 404**. That is worse than
a plain 404: it hides the URL from triage and burns crawl budget. This alone was
42 broken redirects per 1,123 URLs.

---

## 4. Adding or changing a redirect

Pick the **narrowest** mechanism that works.

1. **One exact URL, no query** → row in `legacy_exact_redirects`
   (see `database/seeders/LegacyEntryPointRedirectSeeder.php`; tag a
   `decision_batch` so it can be rolled back).
   For a legacy router path (`index.php`), a `NULL` query_signature matches
   **only** the bare URL — which is why an entry-point rule can never hijack a
   deep content URL.

2. **A known set of legacy record ids** → add to
   `config/legacy_category_routes.php` (regenerate, §5). The allow-list is the
   safeguard: unknown ids stay 404.

3. **A whole family with a structural rule** → a resolver. Copy the shape of
   `LegacySubsiteContentQueryResolver`: `canResolve()` must be strict, and
   `resolve()` must return `null` rather than guess.

4. **Never** reach for a pattern rule to cover a content family. Patterns match
   the **path only** — they cannot see the query string, so a pattern on
   `^/med/index\.php$` swallows every Medicine content URL.

**Before adding any destination, confirm it returns 200.** A redirect to a 404 is
worse than no redirect.

---

## 5. Regenerating the category allow-list

`config/legacy_category_routes.php` is a **generated file**. It maps legacy root
`jx_categories` ids to the section that owns that material, and includes only rows
that were `is_visible = 1`, `is_link = 0`, and had a real title.

To rebuild it you need read access to the legacy database (`OLD_DB_*` in `.env`,
a **SELECT-only** user). The service→path mapping, verified by sampling the rows:

| service | content | destination |
|---|---|---|
| 1 | vision, org structure, quality policy | `/about` |
| 2, 9 | job vacancies | `/campus-life/career-development/jobs` |
| 5 | partnership MoUs | `/about/partnerships` |
| 6, 7 | community news, achievements | `/news` |
| 8 | campus life features | `/campus-life` |
| 10 | events | `/news/events-list` |
| 11 | student card offers | `/campus-life/services` |

Services **3 and 4 are news and announcements** and belong to
`LegacyNewsQueryResolver`, which resolves them to the exact article. Do **not**
add them here — a root 3/4 URL that does not resolve was hidden or empty on the
old site and must stay a 404.

---

## 6. Legacy media

Old media is **not copied**. It is symlinked read-only from the old document root
into `public/_legacy/`, and served at its original URL by internal rewrite.

```
public/_legacy/downloads/files   → /home/spuedu/public_html/downloads/files   (17 GB)
public/_legacy/images            → /home/spuedu/public_html/images
public/_legacy/{faculty}/images  → /home/spuedu/public_html/{faculty}/images
```

Copying is not an option — the account has ~12 GB of quota free — and duplicating
would leave two divergent copies at cutover.

`cv_bank` was **deliberately removed from scope** (applicant CVs, personal data).
Do not re-add it without an explicit decision.

**When writing any legacy media path, verify the file exists first.** The backfills
searched the approved directories and retried case-insensitively — Linux is
case-sensitive, the old CMS was not. Paths that resolved to nothing were cleared,
not left pointing at a 404, so the UI falls back to its no-image layout.

---

## 7. Verifying a change

Never trust a spot check. The measurement procedure:

```bash
# 1. the old URL corpus (28,418 URLs, 85 families)
curl -s https://spu.edu.sy/sitemap.xml -o old_sitemap.xml

# 2. probe a UNIFORM RANDOM sample, following one redirect hop
#    (stratified sampling over-weights rare shapes and flatters the number)
python3 probe.py sample.json out.json

# 3. the numbers that matter
#    - resolved → 200      (a redirect landing on 404 counts as a failure)
#    - broken redirects    (must be 0)
#    - hop count           (must be 1)
```

Then run the suite. `Redirect|Continuity|Legacy|Locale|Research|News` covers the
redirect surface; the full suite takes ~20 minutes.

**Two tests fail on `dev` for reasons unrelated to redirects** — an image checksum
in `AdmissionsCampusRouteCorrectnessTest` and a settings URL in
`NavigationShellTest`. Confirm any failure you see is one of those by stashing
your changes and re-running, before assuming you caused it.

---

## 8. Deploying to the cPanel host

There is **no SSH**, and cPanel shell/Terminal is disabled. The previously used
temporary cron-driven execution bridge is historical evidence, not an approved
current deployment method. Do not recreate a web/cron command bridge without a
separate security approval. The host/operator must provide or execute an approved
deployment mechanism and retain command output.

Layout (cPanel forces subdomain document roots under `public_html`, hence the split):

```
/home/spuedu/spu_v2_app                    application, outside the web root, .env chmod 600
/home/spuedu/spu_v2_app/public             → symlink to the web root, so public_path() resolves
/home/spuedu/public_html/spu_v2/public     document root for v2.spu.edu.sy
/home/spuedu/.spu_v2_tools/composer.phar   composer
```

That `public` symlink is **required**: without it `public_path()` points at nothing,
`@vite` silently emits no stylesheet tag, and the whole site renders unstyled.

After deploying code:

```bash
php artisan optimize:clear && php artisan optimize
php artisan continuity:validate-redirects
```

`optimize:clear` matters — `ContinuityService` caches exact-redirect lookups for an
hour under the `continuity` tag.

---

## 9. At cutover

Three things in the deployed `public/.htaccess` exist only because v2 is a
rehearsal host. All are marked `STAGING ONLY`:

1. `X-Robots-Tag: noindex, nofollow, noarchive`
2. the disallow-all `public/robots.txt` (delete it so the app serves its own)
3. the Host header guard restricting responses to `v2.spu.edu.sy`

Remove those three and the repository's `public/.htaccess` is what ships.

Also required: point the domain at the Laravel `public/` root **only while the old
static trees stay reachable**, keep the legacy symlinks, and set the vhost to
PHP 8.2+.

---

## 10. Known state — what is intentionally unresolved

Do not "fix" these by adding fallbacks. Each is a content decision.

| Lane | Why it 404s |
|---|---|
| `/alumni/**` | Reviewed `graduated_students` list queries redirect to the localized global alumni directory with verified faculty filters. Unknown paths, record/detail guesses, and unverified query variants remain honest 404s. The legacy `d` value is a faculty code, not a record ID. |
| ~27 root category ids | Visible on the old site but with an **empty body**. There is no content to redirect to. |
| 2 root ids | The old row was an external link, not a page. |
| 194 news drafts | Blocked as `incomplete_ar_content`. Empty pages should not go live. |
| 36 research publications | Held private pending duplicate-title review. |
| `/members/` per-record | Private archive, by policy (invariant 1.5). |

The duplicate-title theory was tested directly: **none** of the remaining failing
ids has a published article with the same title, so duplicate recovery would gain
nothing. Do not spend time there.

---

## 11. Performance and caching

Measured 2026-08-21. Read this before "optimising" anything here.

Current remediation decision: additional application/full-page caching
optimization is explicitly deferred. nginx private/full-page caching for dynamic
Laravel responses must remain disabled. The measurements below are historical
staging evidence and do not prove the current working tree is deployed.

### What is already in place

| Layer | Setting | Why |
|---|---|---|
| Laravel page cache | Historical staging configuration; no new optimization is approved in the current remediation | must not be treated as current deployment evidence |
| Cache store | `file` | benchmarked at 0.1 ms/read vs 0.5 ms for `database`, and it keeps page HTML out of MySQL |
| Config/route/view | `artisan optimize` | required after every deploy |
| Static assets | `Cache-Control: immutable, 1 year` for hashed build output | Vite puts a content hash in every filename |
| Legacy media | `Cache-Control: public, 30 days` | filenames are stable but files can be replaced in place |
| Images | resized to max 2400px, re-encoded | `public/images` went from **89 MB to 22 MB** |

### The CSRF token must never be cached

`CachePublicPages` masks the token going into the cache and substitutes the
requesting visitor's own token coming out. Without that, one visitor's
per-session token is served to everyone and **every AJAX form post from a cached
page fails with a 419** — `resources/js/alpine/dynamicFormStore.js` sends the
`<meta name="csrf-token">` value as `X-CSRF-TOKEN`.

`tests/Feature/PublicPageCacheCsrfTest.php` guards this. If you change what the
cache stores, keep those tests passing.

### Three blockers that need root / WHM — none is fixable from the cPanel account

These are the largest remaining wins, and both were verified, not assumed:

1. **OPcache is not installed for `ea-php84`.** There is no `opcache.so` anywhere
   under `/opt/cpanel/ea-php84`. Framework boot measures **333 ms** on every
   single request because ~8,000 PHP files are re-parsed each time. Installing
   `ea-php84-php-opcache` in WHM → EasyApache 4 is typically a 2–4× improvement
   in time-to-first-byte and is the single highest-value change available.

2. **Nothing is compressed.** nginx terminates TLS on :80/:443, does not gzip,
   and strips `Accept-Encoding` before proxying to Apache — verified by
   requesting Apache directly from the server with an explicit
   `Accept-Encoding: gzip` and still receiving `Content-Length: 328750`. So
   neither the `mod_deflate` rules in `public/.htaccess` nor
   `zlib.output_compression` in `public/.user.ini` can fire. **Both are correct
   configuration and will start working the moment nginx gzip is enabled** — do
   not delete them thinking they are dead code. A public page is ~250 KB of HTML
   that would compress to roughly 30 KB.

3. **The PHP-FPM pool is tuned far too small.** `pm_max_children = 5`,
   `pm_max_requests = 20`, `pm_process_idle_timeout = 10`. Recycling a worker
   every 20 requests means a cold PHP process constantly — which, with no
   OPcache, re-parses the whole framework each time. Raising `pm_max_requests`
   to ~1000 and `pm_max_children` to ~16 would remove most of that. cPanel's
   `LangPHP::php_set_vhost_versions` accepts the pool parameters and returns
   success but **silently ignores them**, and
   `/var/cpanel/userdata/…php_fpm.yaml` is root-owned, so this needs WHM too.

`/etc/nginx` is not readable from the account, so items 2 and 3 need the host.

### Why full-page edge caching must remain disabled

Do not rely on cookies or `Cache-Control: private` as the only protection against
an nginx cache configuration mistake. nginx `fastcgi_cache`/`proxy_cache` must be
off or explicitly bypass dynamic Laravel responses, including public HTML, forms,
admin, authenticated, preview, and non-GET traffic. Static asset caching and proxy
buffering are separate. A future edge-cache design requires its own reviewed
session/CSRF/publication/invalidation model and is outside this remediation.

### Historical staging finding: research CMS targets were not set up on v2

At inspection time there were **no `research.*` rows in `cms_target_contents`**,
so research pages rendered from `ResearchPageService`'s static fixture fallback.
The publications archive listed the 253 migrated database publications and was
linked from `/ar/research`, but `appendResearchPublicationEntries` and
`appendResearchCatalogEntries` required published targets, so those ~506 URLs
were absent from that staging sitemap.

Current local code removes public fixture-backed output and fixes publication
sitemap eligibility so real published database publications do not require a
synthetic CMS payload. Real CMS content/product decisions are still needed for
the retained sections. Deployment and sitemap validation remain pending; do not
weaken draft/publication guards or restore fixture fallbacks.

### Zero-byte legacy files

17 of the 33,560 files in `downloads/files` are **0 bytes** on the old server.
`is_file()` returns true for them, so any repair script that only checks
existence will happily write a path that renders as a broken image. Check
`filesize($p) > 0` as well.

---

## 12. Quick reference — approval tokens

Imports are gated so they cannot run by accident:

| Command | `--approve=` |
|---|---|
| `legacy-import:news` | `phase6-news` |
| `legacy-import:publish-news` | `publish-legacy-news` (max 25 ids per batch) |
| `legacy-import:research-publications` | `legacy-research-publications-import` |
| `legacy-import:publish-research` | `publish-legacy-research` |
| `legacy-import:public-staff` | `public-staff-import` |
| `legacy-import:central-councils` | `central-councils-import` |
| `legacy-import:faqs` | `legacy-faq-import` |
| `legacy-import:phase6-restore` | `phase6-restore` |

Publishing requires an actor with the **`editor`** role — note that
`publish-content` does **not** grant to `super_admin`, so a super admin cannot
publish content in the admin panel. `content.editor@spu.edu.sy` exists as the
migration's publishing actor. Whether that gate is intentional is still an open
question for the team.

---

## 13. Legacy entry-point coverage (added 2026-08-29)

The old homepage links a set of entry points that had no rule on the new site.
They are not obscure: they are the site's own top-level navigation, and they were
about to 404 the moment DNS moved. This section records what was added, the
evidence behind each destination, and what was deliberately left alone.

### 13.1 How the evidence was gathered

Three independent sources, all re-runnable:

1. **The live old homepage** — `curl https://spu.edu.sy/` and extract every
   `href`. It links the subsite roots as *relative* paths (`med`, `dent`,
   `admin`, …) plus six `service=N` content lists and the honour-roll page.
2. **The live old sitemap** — `https://spu.edu.sy/sitemap.xml`, 28,765 `<loc>`
   entries. This is what search engines were actually fed, and it disagrees with
   the homepage about spelling: the sitemap uses the `index.php` form throughout.
3. **The live pages themselves** — each list page was fetched and its body diffed
   against the shared template to see what content it actually holds, rather than
   inferring from the service number alone.

Destinations were then confirmed to answer **200** on the deployed site
(`v2.spu.edu.sy`) and asserted in `tests/Feature/PX05/LegacyEntryPointContinuityTest.php`.

### 13.2 Bare subsite directory roots

Apache answered `/med` with a 301 to `/med/`, which served that subsite's
`index.php`. `LegacyEntryPointRedirectSeeder` already mapped the
`/med/index.php` spelling but not the bare one, so the form the old homepage
actually links returned a 404.

Laravel normalises the trailing slash away — `Request::path()` reports `med` for
both `/med` and `/med/` — so one row covers both spellings.

| Legacy root | Destination | Evidence |
|---|---|---|
| `/med` | `/ar/facilities/medicine` | 301→`/med/`→200; matches the existing `/med/index.php` row |
| `/dent` | `/ar/facilities/dentistry` | as above |
| `/pharm` | `/ar/facilities/pharmacy` | as above |
| `/info` | `/ar/facilities/artificial-intelligence` | as above |
| `/petrol` | `/ar/facilities/petroleum` | as above |
| `/hospital` | `/ar/campus-life/hospital` | as above |
| `/dent_clinic` | `/ar/campus-life/dental` | as above |
| `/clubs` | `/ar/campus-life/clubs-activities` | as above |

Each destination mirrors the `index.php` row that already existed, so the two
spellings of one subsite can never disagree.

`/research` and `/alumni` are **not** in that table on purpose — see 13.5.

### 13.3 Root `service=N` content lists

These are section indexes, not records. No per-record resolver can place them:
`LegacyNewsQueryResolver` only answers `page=show` URLs carrying a legacy id, and
`LegacySubsiteContentQueryResolver` deliberately offers no section-level
catch-all for the root subsite (see §1.2). They now live in
`LegacyFunctionalRouteQueryResolver` as exact reviewed query signatures.

| service | What the live page holds | Destination |
|---|---|---|
| 3 | news — Webometrics ranking, competition results | `/{locale}/news` |
| 4 | announcements — "إعلان عن…", course notices | `/{locale}/news/announcements` |
| 5 | partnership MoUs — "الاتفاقيات التي أبرمتها" | `/{locale}/about/partnerships` |
| 6 | community service — responsibility text, photo library | `/{locale}/news` |
| 7 | achievements — rankings, awards, published research | `/{locale}/news` |
| 10 | events — competitions, book fair, receptions | `/{locale}/news/events-list` |

Services 5, 6, 7 and 10 use the destination `config/legacy_category_routes.php`
already assigns to the same service id, so a list URL and a record URL from one
service agree on where they land. Services 3 and 4 have no row in that generated
file on purpose — their *records* belong to `LegacyNewsQueryResolver` — but their
*list* pages are still section indexes, and the new site has a dedicated route
for each. This does not contradict §5: that instruction is about the per-record
`cat_id` allow-list, not the section index.

Both `lang=1` and `lang=2` are registered; the resolver localises the
destination from the URL's own language.

### 13.4 Per-faculty honour rolls

`/{faculty}/index.php?page=list&ex=2&dir=good_students&lang=1` served each
faculty's "لائحة الشرف". `good_students` was not in
`LegacySubsiteContentQueryResolver`'s allowed `dir` list, so all six returned 404.
It is now a `PEOPLE_DIRS` entry resolving to `/{locale}/facilities/{slug}/valedictorians`
— the new site's first-class subpage for exactly this material, labelled
"قائمة الشرف" / "Honor List" in `FacultyPageService`.

Covers `med`, `dent`, `pharm`, `info`, `petrol` and `admin` (Business).

These pages carry student names and grades, so the destination matters. The
`valedictorians` subpage is the section SPU already publishes through the CMS and
is editorially gated the same way, so this resolves an old public list to the new
public list and reveals nothing that is not already public. It never names an
individual record — the same boundary invariant 1.5 draws around `/members/`. A
`good_students` URL under `/members/` still returns 404, because the members
branch runs first.

### 13.5 The `/admin` collision — why the CMS did not move

On the old site `/admin/` is the **Faculty of Business Administration**. On the
new site `/admin` is the Filament CMS. The obvious fear is that every indexed
Business Administration link lands on a sign-in page after cutover.

**It does not, and the sitemap is why.** All **1,955** indexed Business
Administration URLs use the `/admin/index.php` spelling. Not one uses bare
`/admin` or `/admin/`:

```bash
grep -c "spu.edu.sy/admin" old_urls.txt          # 1955
grep "spu.edu.sy/admin" old_urls.txt \
  | sed 's|https://spu.edu.sy||; s|?.*||' \
  | sort | uniq -c                                # 1955 /admin/index.php
```

`/admin/index.php` is already mapped, and `RedirectContinuityMiddleware::shouldSkip()`
already exempts that exact path from the admin skip-list so continuity can claim
it. The indexed corpus is therefore covered today. Its typed query variants —
including the honour roll added in 13.4 — resolve through the same exemption.

That leaves only the bare root, and it was left with the CMS deliberately:

- **Moving the panel is the larger risk.** The path is referenced in ~96 hardcoded
  literals across ~20 test files plus every Filament-generated URL. Relocating it
  to rescue a single URL that no search engine has indexed trades a small,
  measured exposure for a large, unmeasured one.
- **Redirecting bare `/admin` would break the CMS entry point.** Filament serves
  the dashboard *at* `/admin`. A rule there would bounce authenticated staff off
  their own dashboard, and a staff member with an expired session would be sent
  to a public faculty page with no way back.
- **The current behaviour is an honest answer, not a wrong one.** An
  unauthenticated visitor is sent to a sign-in page — clearly not the faculty
  page they wanted, and clearly not a page pretending to be it. §1.2's objection
  is to answers that *look* right while being wrong.

Nothing about the admin surface was changed: not the panel path, the route group,
the middleware skip-list, or the guest redirect.

**Recommended post-launch decision for SPU:** relocate the CMS panel from
`/admin` to `/cms`, then map bare `/admin` and `/admin/` to
`/ar/facilities/business-administration`. Doing it *after* cutover decouples a
large internal change from the DNS move, so the two cannot fail together, and it
can be scheduled when staff can be told the new URL. This is a decision for the
university, not a code change to make silently.

### 13.6 Left as honest 404s — open decisions for SPU

| URL | Why it stays a 404 |
|---|---|
| `/index.php?lang=…&dir=html&ex=1&page=good_students` | The old **root** honour page renders its heading and nothing else — 11 characters of body against the empty-page template, versus 113 for the contact page. There is no university-wide honour list on the new site; the honour rolls are per faculty. Sending it to `/facilities` would name a faculties index, not an honour list. **Decision needed:** does SPU want a university-wide honour page? If it publishes one, add a single signature to `LegacyFunctionalRouteQueryResolver`. |
| Root `service=` ids other than 3, 4, 5, 6, 7, 10 | Only the six the old homepage links were reviewed against live content. `service=8`, `11` and `16` appear in the sitemap but were not reviewed, so they stay 404 and get logged for triage rather than absorbed by a pattern. |
| `/members/**` honour rolls | Private archive, invariant 1.5. |

### 13.7 Re-probing coverage before cutover

`continuity:validate-redirects` gained a `--probe` flag. Rule validation alone
cannot catch the failure this guide cares about most — a well-formed rule whose
destination no longer answers — so `--probe` requests every active app-relative
destination through the application's own router and reports anything that is not
a direct 200, plus anything that redirects again when it should land in one hop.

```bash
php artisan continuity:validate-redirects --probe
```

Run it after `artisan optimize:clear` and before cutover. It exits non-zero if any
destination is broken, so it can gate a deploy.

Note that three campus-life destinations (`hospital`, `dental`,
`clubs-activities`) are **editorial CMS content**, not seeded structure. They are
live on the deployed site, but a database without that content published will
report them as broken — that is the check working, not a false alarm.
