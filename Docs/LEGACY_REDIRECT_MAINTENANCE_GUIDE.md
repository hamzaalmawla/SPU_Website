# Legacy Redirect & Migration — Maintenance Guide

**Read this before changing routing, the front controller, `public/.htaccess`, any
`App\Services\Legacy\**` class, `ContinuityService`, `SitemapService`, or any legacy import.**

The old SPU site had **28,418 public URLs**. They are answered today by a chain of
narrow, deliberate rules. Most of them are *not* covered by an obvious route, and
several look like dead code until you understand what they hold up. This document
exists so the next person does not silently break 27,000 redirects with a
one-line "cleanup".

Last verified: 2026-08-21 · 97.3% of a 2,000-URL random sample resolving · 0 broken redirects.

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

There is **no SSH** (port 22 closed, no SSH UAPI module). Server work goes through
the cPanel API token plus a temporary cron-driven exec bridge, which is created
for the task and **removed afterwards** — do not leave it in place.

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
| `/alumni/**` (210 URLs) | 4,939 alumni records are imported, but the new site has **no alumni page** to send them to. Build the section, or retire the URLs deliberately. |
| ~27 root category ids | Visible on the old site but with an **empty body**. There is no content to redirect to. |
| 2 root ids | The old row was an external link, not a page. |
| 194 news drafts | Blocked as `incomplete_ar_content`. Empty pages should not go live. |
| 36 research publications | Held private pending duplicate-title review. |
| `/members/` per-record | Private archive, by policy (invariant 1.5). |

The duplicate-title theory was tested directly: **none** of the remaining failing
ids has a published article with the same title, so duplicate recovery would gain
nothing. Do not spend time there.

---

## 11. Quick reference — approval tokens

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
