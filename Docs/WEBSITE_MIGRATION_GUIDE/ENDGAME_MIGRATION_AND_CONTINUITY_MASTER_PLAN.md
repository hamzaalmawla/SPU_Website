# SPU Endgame: Legacy Migration, File Preservation, URL Continuity, And Cutover Plan

Last updated: 2026-07-09

> Current execution note (2026-08-21): this master plan remains the historical
> migration blueprint. `Docs/CURRENT_REMEDIATION_EXECUTION_CHECKLIST.md` is the
> authoritative status map for the current remediation. Local code changes are not
> deployed; the local automated suite is green but accessibility needs browser QA; host,
> proxy, front-controller, OPcache, gzip, and PHP-FPM verification remains; cPanel
> shell is disabled; caching optimization is deferred and nginx private/full-page
> caching must remain disabled. Do not infer cutover approval or sign-off.

## Purpose

This is the final operational blueprint for completing the old SPU website migration and preserving every valuable old URL when the Laravel website replaces the old website.

It covers all remaining work across:

- legacy database migration
- old file preservation and serving
- legacy URL parsing and canonical redirects
- equivalent-page decisions
- deployed cPanel file/path setup
- redirect generation and validation
- SEO continuity
- launch, rollback, observability, and post-launch triage

This document supplements, but does not replace:

- `ULTRA_HARDENED_DATABASE_MIGRATION_MASTER_PLAN.md`
- `MASTER_LEGACY_MEDIA_MIGRATION_CONTINUITY_PLAN.md`
- `PHASE6_MIGRATION_HANDOFF.md`
- `LEGACY_IMPORT_RUNBOOK.md`

## Critical Deployment Fact

The database does **not** read files.

The PHP/Laravel application and Apache/LiteSpeed read files from the server filesystem. The owner’s instruction means the old files can remain in cPanel and do not need to be downloaded locally, provided the production deployment preserves filesystem access to them.

The final production arrangement must satisfy both conditions:

1. Laravel connects to the new database containing migrated data, mappings, redirect rules, and logs.
2. The web server/PHP user can still read the old file directories at the paths expected by legacy URLs or configured Laravel disks.

Do **not** assume that uploading a database makes old files available. Confirm the actual production path and read permissions on cPanel.

## Endgame Definition Of Done

The migration/cutover is complete only when all of these are true:

- New Laravel site is the canonical public application for `spu.edu.sy`.
- Existing old dynamic URLs return one-hop `301` redirects to the correct canonical new public URL when an equivalent exists.
- Existing old static files continue to return `200`, or redirect once to an intentional new file URL.
- Unknown old URLs return a real `404`, not the homepage, and are logged for triage.
- Every migrated target is traceable to legacy source table/source ID through `migration_logs`.
- Draft, disabled, unsafe, or unreviewed legacy records never become public merely because a redirect exists.
- Arabic and English old URLs retain locale whenever the old URL contains reliable locale evidence.
- Redirects have no loops, unsafe destinations, or unnecessary chains.
- Canonical URLs, hreflang, sitemap, robots, and redirect status codes are correct.
- Production deployment, queues, cache, scheduler, storage, security, logs, and backup/rollback procedures are verified.

## Current State: What Is Already Done

### Migration Safety Foundation

- Broad imports are blocked by default.
- Legacy DB access uses the dedicated `legacy_mysql` connection alias when needed.
- Legacy imports use service contracts, DTOs, services, dry-run defaults, explicit approval tokens, idempotency, and `migration_logs`.
- Cleaning, integrity, quarantine, classification, staging, review, URL inventory, triage, and redirect evidence tooling exists.
- `38,689` classified/staged legacy source rows are represented in `legacy_content_mappings` and `legacy_review_items`.

### Migration Progress

Use both metrics, always labelled:

| Metric | Current Result | Meaning |
| --- | ---: | --- |
| Classified-scope migration | `9,846 / 38,689 = 25.45%` | All classified rows, including file-only, deferred, duplicate, blocked, and archive rows |
| Text/content-first migration | `9,846 / 15,892 = 61.95%` | Excludes file-only rows intentionally deferred for file/path handling |

Imported legacy source records:

| Module | Source records | State |
| --- | ---: | --- |
| News/announcements | `3,035` | Already migrated; publication state varies |
| Alumni | `4,939` | Public enabled; photos deferred |
| Honor students | `1,067` | Public enabled; photos deferred |
| Faculty profiles | `337` | Disabled; photos/CVs deferred |
| Research publications | `289` | Public enabled; PDFs/media deferred |
| Countries/cities | `122` | Disabled reference records |
| Static pages | `21` | Disabled draft pages |
| Menu link sources | `20` | Disabled localized footer menu items |
| Settings sources | `16` | Imported as `8` current setting units |

### Public/Admin Improvements Completed During Migration

- CMS-driven footer replaced hardcoded footer/social link arrays.
- Public alumni/honor pages have functional filters, search, pagination, AR/EN support, and responsive grids.
- Faculty editor avoids loading huge student repeaters until filters are used and preserves unshown records.
- Research listing supports GET search/filters.
- Imported research records clean legacy HTML and expose recoverable author, publisher/journal, abstract, and keyword metadata.
- Empty research metadata/actions are hidden instead of exposing placeholders.
- Numeric news URLs are the preferred public canonical route.

### URL Continuity Foundation Completed

- `RedirectContinuityMiddleware` runs before application routes for safe non-admin requests.
- Exact redirect, known query resolver, pattern redirect, and file-continuity lookup flow exists.
- Unresolved 404 legacy-looking requests are logged in `unresolved_legacy_requests`.
- URL normalizer sorts query parameters and recognizes old subsites/language values.
- Existing query resolvers support:
  - root news/announcement old item detail URLs using `jx_categories` source IDs and service types `3`/`4`
  - migrated static-page query URLs after the target page is enabled/published
- Public routes include several in-app legacy aliases for news, research, faculty, project, and profile detail requests.
- Generated URL inventory, triage, and redirect evidence commands are available.

### URL Continuity Evidence Baseline

Previous generated legacy URL evidence:

| Evidence status | Count | Meaning |
| --- | ---: | --- |
| Resolver-ready | `6,070` | Existing resolver can safely reach a target |
| Needs imported target | `2,486` | Legacy source identified but target is missing/not public |
| Missing target module | `460` | New equivalent/module not ready |
| Blocked by Phase 3 findings | `574` | Unsafe/duplicate/orphan/other import concern |
| Blocked by file dependency | `819` | Depends on file continuity |
| Needs mapping | `2,339` | Source/target relationship not approved |
| Unknown | `1` | No safe inference |

These counts are an inventory baseline, not final launch proof. Regenerate every report from the final production-ready database before cutover.

## Legacy URL Model

Old SPU URLs are primarily query-router URLs, not modern slugs. A correct continuity system must retain path, query, subsite, module, source ID, and locale context.

Legacy URL forms to support:

- `/index.php?...`
- `/med/index.php?...`
- `/dent/index.php?...`
- `/pharm/index.php?...`
- `/info/index.php?...`
- `/petrol/index.php?...`
- `/research/index.php?...`
- `/hospital/index.php?...`
- `/alumni/index.php?...`
- `/clubs/index.php?...`
- `/members/index.php?...`
- historical `/admin/index.php?...` public URLs
- old `.html` aliases
- old direct static file URLs such as PDFs, DOCs, images, and downloads

The source ID is overloaded in old URLs. Never resolve `cat_id`, `id`, `act`, `item_id`, or similar parameters without the normalized `(subsite, dir, page, service, locale)` context.

## Required Redirect Decision Policy

For every legacy URL, choose exactly one outcome:

| Outcome | When allowed | HTTP result |
| --- | --- | --- |
| Canonical redirect | Exact migrated public equivalent exists | `301` to final new canonical URL |
| Equivalent redirect | Old page is retired but a specific current page genuinely replaces it | `301` to documented equivalent |
| File served directly | Original static file remains under public server path | `200` from Apache/LiteSpeed; no Laravel redirect needed |
| File redirect | File moved/promoted and a canonical replacement exists | `301` to final file URL |
| Archive redirect | Valuable historic content is represented by a safe archive record | `301` to archive URL |
| Intentional retirement | No public value/equivalent, decision documented | `410` preferred when intentionally gone |
| Unknown/unresolved | No evidence-based target | `404`, logged and triaged |

Forbidden outcomes:

- Do not redirect unknown or valuable old URLs to the homepage.
- Do not redirect private/admin legacy pages into the current admin panel.
- Do not redirect an old file to an unrelated page merely because the file exists.
- Do not redirect a disabled/draft target.
- Do not preserve unsafe query parameters in the new destination.

## File Preservation Strategy On cPanel

### Chosen Strategy

The old physical files remain on cPanel. They do not need a local download or a file-copy migration to make legacy links work.

Use a **preserve-in-place first** approach:

1. Identify old public file directories and their current absolute cPanel paths.
2. Keep files at those paths throughout cutover.
3. Configure Laravel/PHP to read them through a read-only legacy disk when application code needs a file reference.
4. Configure Apache/LiteSpeed so existing physical files are served before Laravel routing when their legacy URL path still maps to the document root.
5. Redirect only old files that were intentionally moved to a canonical new location.

### Required Production Facts To Record Before Deployment

Record these exact values in the secure deployment runbook, not Git:

- cPanel account username
- Laravel release path
- actual domain document root
- old website document root
- absolute old public file root
- PHP-FPM/Apache user and group
- whether Laravel and old files are in the same cPanel account
- permissions/ownership proving PHP can read old files
- file directories that must remain public
- whether symbolic links are allowed by hosting
- whether Apache `.htaccess`, `Alias`, or LiteSpeed rules are available
- backups of old database and old public files

### Supported Production Layouts

#### Preferred: Same Public Root With Preserved Legacy Folders

Use when Laravel is deployed beneath the same cPanel account and existing old file paths can remain in the domain document root.

- Point the domain to Laravel `public/` only if old file directories are also copied/symlinked/exposed beneath that root.
- Preserve exact legacy directories such as `/images`, `/med`, `/downloads`, `/uploads`, `/research`, or other verified paths.
- Apache/LiteSpeed must serve existing `-f` and `-d` paths before forwarding requests to Laravel.

Example `.htaccess` principle, adapted to actual hosting rules:

```apache
RewriteEngine On

# Serve real old files/directories exactly as they exist.
RewriteCond %{REQUEST_FILENAME} -f [OR]
RewriteCond %{REQUEST_FILENAME} -d
RewriteRule ^ - [L]

# Send everything else, including legacy index.php query routes, to Laravel.
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [L]
```

Do not apply this blindly. Confirm it does not bypass Laravel for any new application asset/path that requires Laravel authorization.

#### Alternative: Laravel Public Root Separate From Old Files

Use when old files remain elsewhere in the same cPanel account.

- Add a read-only Laravel filesystem disk rooted at the absolute old public file root.
- Add Apache/LiteSpeed aliases or safe rewrite rules from exact legacy file URL prefixes to that absolute directory.
- If aliases are unavailable, create controlled symlinks only if hosting and permissions permit them.
- Do not expose parent directories, private backups, `.env`, source code, or arbitrary server paths.

### File Continuity Rules

- Existing old static files should remain direct `200` responses whenever possible.
- Do not send static file requests through PHP/Laravel if Apache/LiteSpeed can serve them safely.
- For a moved file, use `legacy_file_inventory.current_path` and a one-hop `301` only after verification.
- Keep legacy file paths indefinitely through the stabilization period.
- Preserve Content-Type, Content-Disposition, range requests, and caching behavior for PDFs/documents where applicable.
- Test URL encoding, Arabic filenames, spaces, case sensitivity, and duplicate filename paths.
- Protect against traversal: normalize paths and reject `..`, null bytes, control characters, and unexpected schemes.

## Data Migration Endgame Plan

### Workstream A: Freeze And Reconcile Existing Imported Data

1. Back up the current new database before any final migration action.
2. Export migration logs, rejections, mappings, review items, unresolved requests, exact redirects, pattern rules, and file inventory.
3. Recalculate module reconciliation reports:
   - legacy source count
   - imported source count
   - skipped count/reasons
   - blocked count/reasons
   - published/enabled count
   - draft/disabled count
   - target translation count
   - old URL target coverage
4. Confirm every `migration_logs.status=success` target still exists and is not soft-deleted.
5. Confirm every public target has AR/EN behavior appropriate to its module.
6. Fix idempotency before rerunning any importer.

### Workstream B: Finish Existing Safe Lanes

The following are not broad-import candidates; they need intentional decisions:

| Lane | Current state | Required action |
| --- | --- | --- |
| Menu links | Imported disabled | Editorially enable/order only approved links |
| Static pages | Imported draft/disabled | Review content, map to current templates, publish only meaningful pages |
| Settings | Safe subset imported | Do not import unresolved/backlog settings without a modern target mapping |
| Countries/cities | Imported disabled | Review/enable only if used by active forms/profiles |
| Faculty profiles | Imported disabled | Match to faculty pages, review names/contact data, decide public profile visibility |
| Research | Public text imported | Attach/reference files only after production file path strategy is verified |

### Workstream C: Remaining Module Decisions

#### Career Links

- Only `3` source rows.
- Target schema exists.
- Implement a gated disabled import with old photo references retained only in logs.
- Suitable next small migration, but not a cutover blocker unless legacy routes depend on it.

#### FAQs

- `1,553` source rows; `507` clean review candidates; `1,046` duplicate/blocked.
- Define category/locale/duplicate policy before import.
- Import only visible, cleaned, answered FAQs as disabled records.
- Keep old submitter names/emails/phones out of public tables.
- Required if old FAQ URLs are high traffic or if current FAQ pages need old content.

#### Core `jx_categories` Pages

- `4,944` rows cannot be bulk-imported as generic pages.
- Classify by old subsite/service/context into news, faculty, facilities, events, research, or archive.
- Create a manual selection/mapping sheet for any page that has a current public equivalent.
- This is the primary blocker behind many `needs_imported_target` continuity rows.

#### Councils/Governance

- Separate people profiles, council memberships, and old committee content.
- Do not misclassify leadership/council entries as faculty profiles.
- Build target model/admin/public routes before canonical import and redirects.

#### Complaints

- Contains personal data and historic support submissions.
- Do not migrate to public content.
- Decide retention/privacy/legal policy before importing privately or retiring.

#### Homepage/Documents

- Homepage source is mostly media-dependent.
- Documents require old file path access and direct-file verification.
- Complete only after production filesystem access is proven.

### Workstream D: Archive For Valuable Long-Tail Content

For a valuable legacy record with no production-ready equivalent, do not force it into a generic page.

Create a deliberate archive model/route only if needed:

- `/{locale}/archive/{module}/{legacy-source-id-or-stable-slug}`
- sanitized title/body
- source/legacy metadata private or controlled
- explicit archival status
- no unsafe HTML
- file links only when valid
- canonical/robots policy decided intentionally

Archive is preferable to an unrelated redirect or silent loss for valuable old research, historical news, council records, or documents.

## URL Continuity Endgame Plan

### Step 1: Produce a Final URL Universe

Generate the complete old URL inventory from all evidence, not only browser logs.

Sources:

- old database explicit URL fields
- old router patterns from the old frontend/codebase
- `jx_categories`, `jx_items`, `jx_member_categories`, `jx_member_items`, `jx_councils`, `jx_councils1`, static pages, docs, homepage links
- existing old sitemap(s), if available
- Google Search Console top pages and crawl errors
- server access logs for at least the previous 90 days, if available
- referrers captured by `unresolved_legacy_requests`
- external backlinks where available
- manually known legacy URLs

Run/re-run:

```bash
php artisan legacy-import:url-continuity-inventory --without-files --json
php artisan legacy-import:generated-url-inventory
php artisan legacy-import:url-continuity-triage <generated-url-inventory.csv>
php artisan legacy-import:redirect-evidence <generated-url-inventory.csv> <triage-rows.csv>
```

Deliverable: one deduplicated master URL dataset with:

- raw URL
- normalized path/query signature
- URL type: router, static page, file, `.html`, direct path
- old locale/subsite/module/service/source ID
- traffic/backlink priority where available
- target decision
- target URL
- confidence
- status: ready, needs mapping, missing target, file direct, archive, retire, unknown
- owner/reviewer
- test result

### Step 2: Build a Redirect Decision Register

Every high-value old URL pattern must be assigned a decision before launch.

The register must be versioned and reviewable. It should not be an undocumented collection of rewrite rules.

Minimum columns:

| Column | Required value |
| --- | --- |
| Legacy path | Exact normalized path |
| Legacy query signature | Sorted normalized query or blank |
| URL pattern/context | Subsite, dir, page, service, locale evidence |
| Source table/id | When known |
| Target type | canonical, equivalent, archive, file, retire, unresolved |
| Target URL | Final one-hop destination when applicable |
| Status code | 301, 410, or intentionally 404 |
| Confidence | high, medium, low |
| Evidence | import log, target mapping, old code, traffic, manual review |
| Reviewer/approval | Named decision owner |
| Test outcome | staging and production result |

### Step 3: Expand Deterministic Query Resolvers

Current runtime resolver coverage is limited. Expand only for modules that have a public target and safe source-to-target mapping.

Required resolver families:

| Legacy family | Current requirement |
| --- | --- |
| Root news/announcements item detail | Already supported; verify against final imported data |
| Static pages | Partially supported; only works after target is enabled/published |
| Research detail | Add/verify DB-backed source-ID resolver for imported research records |
| Alumni/honor profiles/lists | Add only when old URL format and target equivalence are proven |
| Faculty member profiles | Add only after profiles are enabled and stable public routes exist |
| Facility/faculty pages | Map old subsite/context to current facilities routes, not generic pages |
| Projects/labs/departments | Add only if exact target slug/record exists |
| Councils | Add only after governance module/archive is implemented |
| FAQ detail/list | Add only if FAQ import/archive is implemented |
| Career links | Use direct external link handling or current career page, never a fake internal detail redirect |
| Old admin public context | Explicitly return 404/410 or map only truly public historical pages; never expose admin routes |

Resolver implementation rules:

- Use interfaces, services, DTOs, and source mappings.
- Validate target public state before returning `301`.
- Preserve locale only if old URL evidence is reliable; otherwise use defined default and log ambiguity.
- Do not query the old DB at request time.
- Cache deterministic mapping where appropriate.
- Add route-level tests for each recognized legacy URL shape, parameter order, unknown ID, wrong service type, disabled target, and Arabic/English behavior.

### Step 4: Persist Final Exact Redirects

The current evidence commands are read-only. Before launch, implement or complete a dedicated approval-gated redirect apply service/command.

Required command behavior:

```bash
php artisan legacy-import:redirect-plan <evidence.csv> --output=<plan.csv>
php artisan legacy-import:redirect-apply <approved-plan.csv> --dry-run
php artisan legacy-import:redirect-apply <approved-plan.csv> --write --approve=legacy-redirect-cutover --batch=redirect-cutover-YYYYMMDD
```

The apply workflow must:

- accept only approved decision-register rows
- normalize paths and queries consistently with runtime middleware
- reject unsafe targets and external hosts not allowlisted
- enforce one canonical exact source per active redirect
- create/update `legacy_exact_redirects` transactionally
- never overwrite manually reviewed redirect rules without explicit replacement flag
- write audit/migration records
- invalidate continuity cache tags
- be idempotent
- emit created/updated/skipped/rejected counts
- support rollback by batch ID

Do not bulk-insert evidence preview rows directly into production.

### Step 5: Decide How Query-Specific Exact Rules Work

The current exact redirect lookup is path-based; query-aware resolution is handled by deterministic resolvers.

Before final import, make one deliberate choice:

1. Continue using deterministic query resolvers for known URL families and use exact table rules only for path aliases.
2. Extend exact redirect storage/lookup to include normalized query signatures/hashes for non-deterministic query URLs.

Recommended endgame approach:

- Keep deterministic resolvers for large, proven ID-based families such as news.
- Add query-signature-aware exact rules for manually reviewed exceptional URLs that cannot be represented by a deterministic resolver.
- Do not make path-only exact redirects accidentally capture multiple old query targets.

This must be tested before rules are imported.

### Step 6: Handle Static File URLs Separately

Create a file route matrix:

| Legacy file URL | Physical file exists on cPanel | Moved/promoted | Required result |
| --- | --- | --- | --- |
| exact old public path | yes | no | Apache/LiteSpeed serves `200` |
| exact old public path | yes | yes | `301` to canonical file path if intentionally moved |
| exact old public path | no | target file mapped | `301` to mapped target |
| exact old public path | no | no target | `404` and unresolved log |

`legacy_file_inventory` must be rerun against the actual production old file root or replaced with a production-generated manifest. Local rclone results are not sufficient launch evidence.

### Step 7: Add Traffic-Driven Continuity Operations

On production launch, unresolved URLs become the final discovery source.

Required operational loop:

1. Middleware logs unresolved 404 requests.
2. Scheduled report groups by URL, handler, subsite, hit count, referrer, and last seen.
3. Daily for first 14 days, then weekly for 90 days:
   - sort by hit count and external referrer value
   - classify
   - add safe target, archive, 410, or leave real 404
   - add regression test for each new redirect family
4. Never respond to unresolved volume by adding a homepage fallback.

## Required Engineering Work Still Remaining

### Must Be Done Before Public Cutover

- [ ] Confirm exact cPanel filesystem/document-root layout and old file access.
- [ ] Create production filesystem disk/Apache strategy for preserving old file URLs.
- [ ] Regenerate legacy URL universe from final DB, old code, Search Console, and server logs.
- [ ] Create reviewed redirect decision register.
- [ ] Complete resolvers for every high-traffic supported old dynamic URL family.
- [ ] Implement approved-plan-to-`legacy_exact_redirects` apply command if not already present.
- [ ] Decide and implement query-signature-aware exact redirects for exceptions if needed.
- [ ] Map/implement archive routes for valuable content without a current canonical module.
- [ ] Enable/publish only reviewed imported pages/profiles that will become redirect targets.
- [ ] Confirm target URLs are stable before generating redirects.
- [ ] Rerun production file inventory/manifest reconciliation.
- [ ] Test representative old files directly from production-like paths.
- [ ] Run redirect validation, no-loop validation, and one-hop checks.
- [ ] Run complete production launch validation.
- [ ] Produce rollback plan and database/filesystem backups.

### Strongly Recommended Before Cutover

- [ ] Import disabled career links if their current page needs them.
- [ ] Make FAQ duplicate/category policy and import clean reviewed records if FAQ URLs are valuable.
- [ ] Build councils/governance or archive route if old council URLs have meaningful traffic.
- [ ] Add first-class editable research author/publisher/faculty/rank metadata where business needs it.
- [ ] Reconcile imported faculty profiles to actual faculty scope and decide which profiles publish.
- [ ] Review imported draft static pages and retire duplicates/obsolete contact pages.
- [ ] Update phase status/runbook with final production evidence, not historical local evidence only.

### Explicitly Do Not Do

- [ ] Do not replace the old production database in place without a full backup and tested rollback.
- [ ] Do not point a domain at Laravel `public/` until old file URL handling is proven.
- [ ] Do not rely on local `X:/` rclone inventory at launch.
- [ ] Do not make all imported draft/disabled records public to improve redirect counts.
- [ ] Do not import all `jx_categories` as pages.
- [ ] Do not add thousands of unreviewed low-confidence redirects.
- [ ] Do not send old URLs to `/ar` or `/en` homepage as a generic fallback.
- [ ] Do not expose legacy `/admin` URLs as current admin routes.

## cPanel Deployment And Cutover Sequence

### T-14 To T-7 Days: Production Discovery

1. Take immutable backup of old DB and full old public filesystem.
2. Export old access logs and Search Console URL/crawl data.
3. Record domain aliases, `www` behavior, HTTPS certificate, document root, cron jobs, PHP version/extensions, and cPanel account path.
4. Confirm new Laravel server requirements:
   - PHP version/extensions matching `composer.lock`
   - MySQL version/charset/collation
   - Redis/queue plan or safe fallback
   - cron access
   - writable `storage/` and `bootstrap/cache/`
   - SSH/Composer/Node build availability or artifact deployment process
5. Build a staging deployment using the same path strategy as production.

### T-7 To T-2 Days: Final Data And Redirect Preparation

1. Freeze schema changes except launch blockers.
2. Re-run safe/idempotent imports and reconcile counts.
3. Create or update redirect decision register.
4. Generate final redirect plan from source evidence.
5. Review high-traffic and high-value rows manually.
6. Apply only approved redirect rules to staging.
7. Crawl old URL sample matrix against staging.
8. Fix all high-priority broken redirects, loops, wrong locales, and file failures.
9. Run full tests and production build.

### T-1 Day: Cutover Rehearsal

1. Restore a production-like database backup to staging.
2. Deploy exact release artifact.
3. Run migrations with `--force` only after backup verification.
4. Configure production-like `.env` without committing secrets.
5. Configure old file disk and web-server path rules.
6. Run redirect plan/apply using a rehearsal database only.
7. Run the full smoke matrix and `launch:validate --environment=production` against staging configuration.
8. Time rollback from release switch back to old site.

### Cutover Window

1. Enable maintenance/low-write mode only if needed.
2. Back up old database, new database, redirect tables, and old filesystem metadata.
3. Import final approved delta data only.
4. Deploy immutable Laravel release.
5. Set document root/rewrite rules while preserving old static file access.
6. Set production `.env` and permissions.
7. Run:

```bash
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan cache:clear
php artisan continuity:validate-redirects
php artisan launch:validate --environment=production
```

8. Verify `https://spu.edu.sy/sitemap.xml` and `https://spu.edu.sy/robots.txt` from outside cPanel.
9. Restart queue workers/PHP-FPM if applicable.
10. Run external HTTPS smoke tests from a machine outside cPanel.
11. Submit/update sitemap in Search Console after the site is confirmed stable.

### First 24 Hours

- Monitor application errors, 404s, unresolved legacy request logs, redirect hit counts, page response times, queue failures, disk errors, and file download failures.
- Check top old URLs every few hours.
- Check Arabic and English home/navigation/contact/news/research/faculty flows.
- Do not delete old files or database backups.

### First 90 Days

- Daily unresolved URL triage for 14 days.
- Weekly unresolved URL triage through day 90.
- Keep old physical files in place.
- Keep redirect logs and redirect rule audit trail.
- Only retire old file paths after traffic and reference evidence show they are unused.

## Test Matrix Required Before Launch

### Dynamic URLs

For each supported URL family, test:

- valid Arabic URL
- valid English URL
- reordered query parameters
- URL encoded parameter values
- known source ID with published target
- known source ID with disabled/draft target
- nonexistent source ID
- wrong service/subsite context for a valid ID
- malformed query
- destination status and exact canonical URL
- exactly one redirect hop

### Static Files

For each critical directory/file type:

- image
- PDF
- DOC/DOCX
- XLS/XLSX if present
- Arabic filename
- spaces/encoded filename
- nested directory
- missing file

Verify:

- `200` for preserved old file
- correct MIME type
- correct download/inline disposition
- no PHP error
- no directory listing
- expected `301` only for intentional moved files

### Public Site

- `/ar` and `/en` home
- navigation/footer links
- news/announcements lists/details
- research list/detail/search/filter
- facilities/faculty list/details
- alumni/honor filters/pagination
- contacts/forms
- all CMS published pages
- 404 page and unresolved legacy log behavior
- canonical/hreflang/structured data/sitemap/robots

### Admin And Security

- super admin/editor/faculty editor access boundaries
- locked account behavior
- 2FA flow
- draft/preview/publish/cache invalidation/audit logs
- normal media picker excludes legacy archive
- legacy archive cannot become public without promotion/review
- no secrets in deployment files, logs, or Git

## Launch Evidence Package

Store a dated launch evidence folder outside public web root containing:

- database backup identifiers/checksums
- old filesystem backup identifiers/checksums
- production path map
- final migration reconciliation report
- final redirect decision register
- redirect apply batch report
- redirect validation output
- old URL smoke test CSV with expected/actual result
- static file smoke test CSV
- `launch:validate` output
- test/build output
- final sitemap URL/count
- unresolved backlog at launch
- rollback procedure and release identifier
- reviewer approvals

## Rollback Plan

Rollback must be tested before launch.

Rollback triggers:

- widespread redirect loops or wrong destinations
- old files unavailable due to document-root/path mistake
- major public page failures
- database migration failure
- severe security/session/cache failure

Rollback actions:

1. Switch document root/virtual host back to the old website release.
2. Keep the old files untouched.
3. Restore old database only if it was modified; preferred strategy is separate new DB to avoid this.
4. Preserve new database and Laravel logs for diagnosis.
5. Export unresolved URLs/errors observed during failed cutover.
6. Fix in staging and repeat rehearsal.

Recommended database strategy:

- Keep old and new databases separate during cutover whenever possible.
- Point Laravel only at the new database.
- Keep old database read-only/unchanged as rollback and audit source.
- Never depend on replacing the old database schema in place unless there is no alternative and a tested restore exists.

## Final Command Checklist

Run in the appropriate environment, never blindly against production:

```bash
# Reconciliation and migration evidence
php artisan legacy-import:phase6-candidates
php artisan legacy-import:generated-url-inventory
php artisan legacy-import:url-continuity-inventory --without-files --json
php artisan legacy-import:url-continuity-triage <inventory.csv>
php artisan legacy-import:redirect-evidence <inventory.csv> <triage.csv>

# Redirect/runtime validation
php artisan continuity:validate-redirects
php artisan continuity:reconciliation-report
php artisan continuity:report-unresolved
php artisan launch:validate --environment=staging

# Application quality
php artisan test
npm run build
composer install --no-dev --optimize-autoloader

# Production optimization, only during tested deployment
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan launch:validate --environment=production
```

Validate the sitemap through the deployed public route rather than a non-existent generation command:

```text
https://spu.edu.sy/sitemap.xml
https://spu.edu.sy/robots.txt
```

## Ownership And Priority Order

Follow this order. Do not attempt all items at once.

1. Confirm cPanel file/document-root architecture.
2. Preserve/serve old file paths correctly.
3. Generate final URL universe from final source data and production traffic evidence.
4. Create approved redirect decision register.
5. Expand only necessary deterministic resolvers and archive targets.
6. Implement/apply final exact redirects safely.
7. Reconcile/publish only redirect targets that are editorially ready.
8. Rehearse full deployment and rollback.
9. Cut over.
10. Operate unresolved URL triage for 90 days.

## Stop Rule

Do not declare migration or continuity complete based on imported-row percentage alone.

It is complete only when old high-value URLs and old files behave correctly in the production cPanel environment, evidence is recorded, and unresolved traffic is under active review.
