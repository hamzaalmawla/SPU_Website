# Audit-Driven Production Endgame Plan

Last updated: 2026-07-28

## Authority And Purpose

This is the production endgame plan for completing the new SPU Laravel website, the remaining legacy database decisions, old-file preservation, legacy URL continuity, and safe cPanel cutover.

It supersedes generic assumptions in earlier migration/continuity plans where the old code audit has supplied stronger evidence.

Primary evidence sources:

1. `LEGACY_CODE_AND_CONTINUITY_AUDIT.md`, audited 2026-07-28 from the old production PHP code and `spuedu_db.sql`.
2. Generated audit artifacts under the professor's `legacy-audit-output/` directory.
3. This Laravel repository, its final public routes, imported target data, migration logs, and final production deployment evidence.
4. Production cPanel filesystem paths, access logs, Search Console, and staging smoke tests.

When evidence conflicts, use this order:

```text
actual production behavior/path test
> old PHP route/handler code
> old SQL schema/data
> generated audit artifact
> current Laravel code
> earlier migration assumptions
```

## Execution Checkpoint: 2026-07-28

Completed in the first audit-driven implementation slice:

- Created private credential-safe snapshots of `spu_website` and `spu_legacy` before staging writes.
- Fixed legacy connection initialization so read-only schema inspection works without a prior connection call.
- Added canonical aliases for `ser`/`Ser`, `cat`, and `show_cat`.
- Added audited `dent_clinic` subsite detection.
- Added high-confidence legacy subsite-home resolution, including public Business `/admin/index.php` while preserving normal Laravel `/admin/*` behavior.
- Disabled the disproven `jx_site_static_pages` standalone-page query resolver.
- Corrected generated `jx_categories` URLs to use audited service-decade subsite prefixes instead of root paths.
- Corrected classification so all `jx_categories` rows require contextual archive/review rather than automatic canonical news rebuild.
- Removed direct `jx_member_items -> research_publications` and broad `jx_categories -> news_articles` future-map assumptions.
- Added read-only `legacy-import:category-matrix`, producing exactly one evidence row per source category.
- Added read-only `legacy-import:category-review-packets` with context-specific root and Business semantics, child-item/file evidence, blockers, and blank approval fields.
- Corrected root service `4` to announcements and Business service `74` to faculty research/projects based on source context and row evidence.
- Replaced broad news import behavior with `legacy-import:news <reviewed-packet.csv>`; only explicit `import` + `news` approvals are eligible.
- Forced approved legacy news imports into disabled drafts with `noindex,nofollow`, no locale synthesis, and approval packet SHA-256 evidence.
- Removed automatic news and `/members/`-derived research imports from `legacy-import:phase6-restore` pending approved packet/reconciliation workflows.
- Added `legacy-import:public-staff-review-packets` for the audited `jx_councils` public source, with faculty ownership, identity conflicts, cPanel file paths, and `jx_councils1` overlap evidence.
- Added `legacy-import:public-staff <reviewed-packet.csv>`; approved rows import only as disabled faculty-member drafts with actual locales, deferred media, and packet SHA-256 evidence.
- Removed automatic `jx_councils1` faculty-profile restoration; the lane is blocked pending archive reconciliation and is never used as proof of public continuity.
- Added `legacy-import:central-councils <reviewed-packet.csv>` for services `1/2`, creating only disabled Board of Trustees/University Council review records and standalone translated memberships.
- Corrected generated `jx_councils` continuity URLs to audited root/faculty subsite paths with `cat_id`; `jx_councils1` now produces no guessed public router URLs.
- Added evidence-only `legacy-import:members-review-packets` for `jx_member_categories` and `jx_member_items`, explicitly treating category `parent` as staff-owner identity rather than hierarchy.
- Hard-blocked all writes through the old broad research importer until `/members/` ownership and product policy are approved; approval tokens cannot bypass the freeze.
- Preserved category URL candidates only where the old `/members/` grammar is known; member-item URLs remain blank with `needs_runtime_evidence`.
- Added private `legacy-import:faq-review-packets` and packet-gated `legacy-import:faqs`; submitter PII values are never exported, imported, or logged, and approved rows remain disabled single-locale review records.
- Added `legacy-import:career-link-review-packets` and packet-gated `legacy-import:career-links`; safe external links remain disabled and legacy photos stay deferred metadata.
- Kept FAQ/career target tables disconnected from public rendering and Filament until their CMS/public integration is explicitly completed; these imports do not claim feature parity.

Current local evidence results:

| Evidence | Result |
| --- | ---: |
| Legacy classified rows | `38,689` |
| Automatic canonical category rows | `0` |
| Category matrix rows | `4,944` |
| Known-subsite categories | `4,926` |
| Unknown-subsite categories | `18` |
| Hidden categories | `944` |
| Link-review categories | `227` |
| Orphaned categories | `21` |
| Categories mapped to current targets | `0` |
| Generated category URL candidates | `9,997` |
| Currently resolved category URL candidates | `9` |
| Root + Business review rows | `3,699` |
| Root + Business packet files | `19` |
| Root + Business hidden rows | `739` |
| Root + Business link-review rows | `94` |
| Root + Business under-construction translations | `1,439` |
| Public `jx_councils` rows | `648` |
| Faculty-profile candidates | `603` |
| Central board/council rows | `45` |
| Duplicate staff identity rows | `104` |
| `jx_councils1` email-overlap rows | `8` |
| Staff rows mapped to current targets | `0` |
| Central board/council review rows | `45` |
| Generated public `jx_councils` URL candidates | `1,245` |
| Guessed `jx_councils1` public URL candidates | `0` |
| `/members/` categories | `349` |
| `/members/` child items | `429` |
| Research-like category/item rows | `552` |
| Teaching category/item rows | `226` |
| `/members/` file references | `424` |
| Ambiguous dual-source owner rows | `185` |
| Missing owner rows | `12` |
| Existing `/members/` target mappings | `0` |
| Legacy FAQ submissions | `1,553` |
| Supported visible answered FAQ candidates | `47` |
| Private FAQ backlog rows | `1,506` |
| Duplicate FAQ candidate rows | `4` |
| Career-link candidates | `3` |
| FAQ/career rows imported | `0` |

Private generated evidence:

```text
storage/app/private/legacy-import-backups/20260728_1425_spu_website.sql
storage/app/private/legacy-import-backups/20260728_1425_spu_legacy.sql
storage/app/private/legacy-import-exports/category-matrix/20260728_162001_jx_categories_matrix.csv
storage/app/private/legacy-import-exports/category-matrix/20260728_162001_jx_categories_matrix.json
storage/app/private/legacy-import-exports/category-review-packets/20260728_160443/manifest.json
storage/app/private/legacy-import-exports/category-review-packets/20260728_160443/summary.md
storage/app/private/legacy-import-exports/public-staff-review-packets/20260728_164734/manifest.json
storage/app/private/legacy-import-exports/public-staff-review-packets/20260728_164734/summary.md
storage/app/private/legacy-import-exports/members-review-packets/20260728_180031/manifest.json
storage/app/private/legacy-import-exports/members-review-packets/20260728_180031/summary.md
storage/app/private/legacy-import-exports/faq-review-packets/20260728_182957/manifest.json
storage/app/private/legacy-import-exports/faq-review-packets/20260728_182957/summary.md
storage/app/private/legacy-import-exports/career-link-review-packets/20260728_182957/manifest.json
storage/app/private/legacy-import-exports/generated-url-inventory/20260728_142144_generated_url_inventory_jx_categories.csv
storage/app/private/legacy-import-exports/classification/20260728_141756_classification_report_mapping.csv
```

Focused verification includes legacy normalization, query resolution, URL inventory, category matrix export, review packets, packet-driven news quarantine import, restore freezes, continuity middleware, command behavior, and architecture guards.

## Non-Negotiable Goal

The goal is not to copy every old row into public Laravel CMS tables.

The goal is to give every valuable legacy record and public legacy URL a correct final outcome:

| Final outcome | Meaning |
| --- | --- |
| Canonical migration | Clean data exists in the correct new module and has stable public URL |
| Equivalent redirect | Specific current page replaces retired old page |
| Archive | Valuable historic content is safe/reachable but not part of an active module |
| Preserved file | Existing old cPanel file remains directly reachable at its old public URL |
| File redirect | Old file URL permanently redirects to an intentional canonical replacement |
| Private retention | Sensitive/admin/complaint data remains private or is excluded under policy |
| Intentional retirement | Approved obsolete endpoint returns `410` |
| Unresolved | Real `404`, logged and triaged; never redirected to homepage |

## Critical Audit Corrections

These findings change earlier migration/continuity assumptions and must be handled before cutover.

### 1. `/admin/` Is a Public Business Faculty Subsite

- Old `/admin/index.php?...` is **not** the old CMS.
- It is the public Faculty of Business Administration subsite, `$SITE=7`, service types `71-78`.
- Old authenticated CMS is `/CMS_SPU_SyrianMonster_17/engine/*`.

Required action:

- Add an explicit legacy public-business resolver for `/admin/index.php?...` before any generic admin-prefix skip behavior.
- Never redirect `/CMS_SPU_SyrianMonster_17/engine/*` to the Laravel admin login.
- Block or `404` old CMS/admin paths after security review.
- Add regression tests proving public Business URLs resolve while CMS URLs never enter public or new admin routes.

### 2. `jx_councils` Is the Public Staff/Profile Source

- Old public `dir=councils&page=show&cat_id={id}` reads `jx_councils`.
- `jx_councils1` exists in the dump but no public read path was found in audited code.

Required action:

- Treat existing imported `jx_councils1` faculty profiles as disabled archive/review data until manually reconciled.
- Do not publish them as proof of old public profile continuity.
- Build a separate evidence-backed `jx_councils` public staff/council import and resolver lane.
- Preserve CV/photo paths as legacy file references; do not require copying files locally.

### 3. `jx_member_categories` and `jx_member_items` Belong to `/members/`

- Old `/members/` is a distinct public/member-oriented subsite.
- It uses `jx_member_categories` and `jx_member_items`.
- Its service type range overlaps the dentistry decade, so source ID/service type alone is unsafe.

Required action:

- Do not create broad continuity assumptions from `jx_member_categories` without `/members/` route context.
- Reconcile the `289` currently imported research publication records sourced from `jx_member_categories` against the audit matrix.
- Do not add permanent legacy research redirects from that source until the source semantics are proven per route/context.
- If records are genuinely publications, retain them; if they are member-portal content, move/archive/reclassify through an approved remediation process, never by destructive bulk deletion.

### 4. `jx_site_static_pages` Are Module Intro Snippets

- Audited code uses them as introductory HTML on list pages.
- They are not proven standalone public pages with their own direct legacy route.

Required action:

- Keep the existing `21` imported pages disabled/draft.
- Do not publish or create redirects to them merely because they were imported.
- Re-map each snippet to the target module's CMS intro/landing payload, archive it, or retire it.
- Reassess the current static-page query resolver before using it for final redirect coverage.

### 5. Files Are Direct Static Paths, Not Database Assets

The old application emits direct URLs only after `file_exists` checks. It does not use a download controller for primary uploaded files.

Must-preserve public prefixes from audit:

```text
/downloads/files/
/downloads/files/thumb/
/images/
/{subsite}/images/
/{subsite}/css/
/{subsite}/js/
/pdf/
```

Required action:

- Preserve/serve these paths on cPanel before Laravel handles dynamic legacy routes.
- Do not require downloading/copying every file locally.
- Verify production filesystem layout and web-server behavior with actual requests.

### 6. Legacy Route Meaning Requires Full Context

`jx_categories` has no `site_id`. Meaning is determined by all of:

```text
subsite URL prefix + service_type decade block + suffix + dir + page + parent hierarchy + visibility + file/link flags
```

No importer or redirect may infer meaning from `cat_id` or `service_type` alone.

## Proven Legacy Runtime Model

### Public Subsites and Service Decades

| Legacy prefix | Old `$SITE` | Service decade | Current target direction |
| --- | ---: | --- | --- |
| `/` | `0` | `1-10` | Main site modules |
| `/med/` | `2` | `21-28` | Facilities/Medicine |
| `/dent/` | `3` | `31-38` | Facilities/Dentistry |
| `/pharm/` | `4` | `41-48` | Facilities/Pharmacy |
| `/info/` | `5` | `51-58` | Facilities/AI |
| `/petrol/` | `6` | `61-68` | Facilities/Petroleum |
| `/admin/` | `7` | `71-78` | Facilities/Business Administration |
| `/research/` | `8` | `81-89` | Research |
| `/hospital/` | `9` | `91-98` | Campus Life/Hospital or dedicated module |
| `/dent_clinic/` | `10` | `101-108` | Campus Life/Dental or dedicated module |
| `/alumni/` | `11` | `111-119` | Alumni/current career paths |
| `/clubs/` | `12` | `121-128` | Campus Life/Clubs |
| `/members/` | `13` | overlaps `31-38` | Separate reviewed members module |

### Legacy Locale Rules

| `lang` | Old symbol | New handling |
| ---: | --- | --- |
| `1` | `ar` | Redirect to Arabic when target exists |
| `2` | `en` | Redirect to English when target exists |
| `3` | `fr` | Preserve only in archive/snapshot unless product approves French |
| `6` | `sp` / Spanish | Preserve only in archive/snapshot unless product approves Spanish |
| `7` | `ge` / German | Preserve only in archive/snapshot unless product approves German |

Rules:

- `lang=1` and `lang=2` are the only normal public canonical locale targets today.
- Do not silently claim FR/ES/DE parity; preserve source text privately or in archive evidence.
- For old FR/ES/DE inbound URLs, make a documented policy: archive fallback, AR fallback, EN fallback, or 404. Do not decide ad hoc per request.

### Query Grammar That Must Be Parsed

```text
index.php?page={page}&ex={extension}&dir={module}&lang={language}&service|ser|Ser={service_type}&cat_id|cat={id}
```

Relevant aliases:

- `show_cat` means `show`
- `ser`, `service`, and `Ser` are equivalent service parameters
- query parameter ordering does not matter

Other recognized parameters include `act`, `id`, `nid`, `nt`, `Keywords`, and `StartSearch`.

## Production Readiness Has Two Parallel Tracks

The website is not ready merely when migration/redirect work is done. Execute both tracks in parallel, then merge for cutover.

### Track A: Finish New Website Product Readiness

- [ ] Every approved public route exists, renders correctly, and has AR/EN behavior.
- [ ] Every visible control works with keyboard and pointer input.
- [ ] Every CMS-managed module has admin editor, validation, draft/preview/publish, cache invalidation, and audit logging.
- [ ] All public pages use services/DTOs and respect architecture rules.
- [ ] Forms have validation, throttling, authorization, persistence, notifications, and privacy handling.
- [ ] No placeholder/fake/developer-facing public content remains.
- [ ] Public media references resolve in production.
- [ ] SEO/canonical/hreflang/structured data/sitemap/robots are correct.
- [ ] Full test suite, production dependency install, and frontend build pass.
- [ ] Production queue, scheduler, Redis/cache, storage, logs, security headers, backups, and observability are confirmed.

### Track B: Finish Legacy Data and Continuity

- [ ] Every source module/row group has a final disposition.
- [ ] Every high-value dynamic old URL family has a resolver, exact redirect, archive target, retirement, or documented 404 outcome.
- [ ] Every required old static file prefix survives cutover.
- [ ] Every redirect target is public, enabled, canonical, and tested.
- [ ] Every unknown legacy request is logged and triaged after launch.

## Execution Order

Do not reorder these phases casually.

### Phase E0: Preserve Evidence and Freeze Unsafe Actions

1. Preserve the available professor audit report and manifest as private evidence; the unavailable ZIP is optional.
2. Reconstruct required structured artifacts from the report and local read-only legacy DB; do not publish source code, credentials, or private data.
3. Commit this plan and current migration tooling before broad endgame changes.
4. Freeze these actions until their remediation gate passes:
   - final redirect persistence
   - publishing `jx_councils1`-derived profiles
   - creating research legacy redirects from `jx_member_categories`
   - publishing `jx_site_static_pages` as standalone pages
   - bulk `jx_categories` imports
5. Back up the current new DB, old DB, redirect tables, migration logs, and cPanel file metadata.

Exit criteria:

- Sufficient audit evidence and locally reconstructed artifacts are available.
- Current database state is recoverable.
- Earlier assumptions contradicted by audit are marked/remediated.

### Phase E1: Import and Route Reconciliation

Create a reconciliation report for every existing importer:

| Existing lane | Required proof before final use |
| --- | --- |
| News/announcements | Source IDs/service types map to old root x3 URLs; published targets resolve |
| Alumni/honor | Old faculty/subsite list semantics match current public list/filter routes |
| Faculty profiles | Separate `jx_councils1` archive imports from future public `jx_councils` imports |
| Research | Verify whether each source is real public research versus `/members/` content |
| Static pages | Remap snippets to module payloads; no standalone publication without proof |
| Menu/settings | Confirm source values are still correct/current and not dead external links |
| Locations | Keep disabled until active form/profile usage requires them |

For each target record, verify:

- source table/source ID/source context
- target table/target ID
- route/public state
- locale behavior
- file reference behavior
- legacy URL family that may point to it
- migration log consistency

Exit criteria:

- No existing imported lane is used for public continuity under a disproven source assumption.

### Phase E2: Build the Master Legacy Route and Data Registry

Use the audit CSVs to import or maintain a private registry, not public CMS content.

Required registry fields:

```text
legacy path
normalized query signature
subsite
site ID
locale/language ID
dir
page
ex
service type
service suffix semantic type
source table
source ID column/value
parent/source hierarchy
visibility
file/link flags
legacy public URL example
current target decision
confidence
evidence file/line
review status
```

This registry becomes the source for:

- typed import candidate packets
- deterministic query resolvers
- exact redirect plans
- archive decisions
- smoke tests
- unresolved URL triage

Exit criteria:

- Every known route family has a machine-readable source/ID/locale rule.

### Phase E3: Complete Remaining Database Work by Typed Module

Never perform `jx_categories -> pages` bulk migration.

#### E3.1 Root `jx_categories` (`1-10`)

Process semantic groups separately:

| Legacy semantic group | Audit rule | New outcome |
| --- | --- | --- |
| x1 main navigation | Container/menu | Menu mapping only; no page import |
| x2 footer/vertical navigation | Container/menu | Footer/navigation mapping only |
| x3 news/announcements | `jx_categories` + `jx_items` | Reconcile existing news import and redirects |
| x4 projects/success | Items content | Projects module or archive |
| x5 events/cooperation | Items content | Events/partnerships or archive |
| x6 general/static page | Leaf HTML/content | Typed current CMS page/module payload, archive, or retire |
| x7 research/statistics | Items/PDFs | Research module only if public target proven |
| x8/root 6 gallery | Photos | Preserve files; gallery module/archive after media review |
| x9 jobs/publications | Jobs/items | Career, research, archive, or retire based on route context |

Start with a curated service x6 root batch after removing hidden, duplicate, under-construction, file-only, and no-equivalent records.

#### E3.2 Faculty Subsites

For each decade, process x1-x9 semantics using both prefix and service type:

```text
/med/ + 21-28
/dent/ + 31-38
/pharm/ + 41-48
/info/ + 51-58
/petrol/ + 61-68
/admin/ + 71-78  (Business Faculty, public)
```

Map only to existing final facility routes or archive targets:

- overview/general pages
- departments
- labs
- projects
- staff profiles
- events/cooperation
- research
- galleries
- jobs/other resources

If the current Laravel page does not support the content structure, either build that module before import or archive it. Do not flatten it into generic pages.

#### E3.3 Public Staff and Councils

1. Import `jx_councils` through a new controlled public staff/council lane.
2. Preserve `photo`, `cv`, and `ar_cv` as old cPanel file paths/metadata.
3. Match staff to faculty from subsite + public route context, not email alone.
4. Build public profile route only when target is approved.
5. Treat `jx_councils1` as separate archive/review until a public route is proven.

#### E3.4 Members Subsite

1. Decide product policy: public archive, authenticated member system, or retirement.
2. If public continuity is needed, create a separate archive/module for `/members/`.
3. Never use dentistry service-decade assumptions for members content.

#### E3.5 FAQs

1. Use `lang`, visibility, cleaned question/answer, and duplicate policy.
2. Import only visible, answered, clean records as disabled initially.
3. Keep historic submitter information private; do not expose it in public FAQ data.
4. Create current FAQ categories from approved content semantics, not raw subject duplicates.

#### E3.6 Career Links

1. Import the three `jx_job_sites` records as disabled external links if current career page needs them.
2. Preserve old photos only as metadata/direct file references.
3. Do not make an external URL a fake Laravel detail page.

#### E3.7 Homepage/Logos/Docs/Galleries

1. First prove cPanel static file access.
2. Map each source to current homepage section, partner carousel, gallery, document, or archive.
3. Preserve old direct file paths when the asset has not been intentionally promoted/moved.
4. Do not import every old image into normal media library.

#### E3.8 Sensitive/Private Data

- `jx_complaints`, `jx_admins`, CMS users, password/reset flows, and private submissions are never public migration targets.
- Apply legal/privacy retention decision separately.
- Old public GET forms can map to a new public form only, never to old submitted records.

Exit criteria:

- Every remaining table/group is migrated, archived, preserved as file, retained privately, retired, or explicitly unresolved.

### Phase E4: Build the Final Redirect System

#### E4.1 Runtime Resolution Order

Production request order must be:

1. Web server serves a real approved static file/directory directly.
2. Laravel exact path/query redirect rule.
3. Laravel deterministic legacy query resolver.
4. Laravel path-pattern redirect where evidence is unambiguous.
5. Laravel file inventory redirect for intentionally moved files.
6. Correct new route rendering when request already matches current route.
7. Real `404` and structured unresolved log.

No homepage fallback at any stage.

#### E4.2 Mandatory Resolver Normalization

All legacy resolvers must normalize:

- path/subsite prefix
- `lang`
- `service`, `ser`, `Ser`
- `cat_id`, `cat`
- `show_cat`, `show`
- query order
- `ex` extension selector
- `dir` and `page`

Resolvers must then validate:

- recognized public route family
- source ID exists in correct old context/mapping
- source was visible/public when relevant
- target exists in new DB
- target is enabled/published/public
- target locale route is valid
- target destination is canonical and internal/allowlisted

#### E4.3 Required Resolver Families

| Family | Requirement |
| --- | --- |
| Root home/subsite homes | Redirect to correct current AR/EN landing/facility page |
| Root x3 news/announcements | Verify existing resolver; support aliases and old templates |
| Category/item x4-x9 | Typed resolver based on subsite + semantic matrix + approved target mapping |
| General x6 pages | Redirect only after typed target page/module is published |
| Public Business `/admin/` | Dedicated Business-faculty resolver before admin middleware skips it |
| Councils | `jx_councils` only, after public target import |
| Members | Separate resolver/policy; never collide with dentistry |
| FAQ/list/forms | New equivalent or archive/404 policy |
| `is_link=1` | Direct external URL handling only if approved/safe |
| `is_download=1` | Direct old file or verified mapped file |
| `slider.php` | Separate explicit decision; never generic fallback |
| `.html` aliases | Exact/path-pattern mapping with locale policy |

#### E4.4 Exact Redirect Plan and Apply Workflow

Evidence reports are not production redirect rules. Add a gated final workflow:

```bash
php artisan legacy-import:redirect-decisions <reviewed-evidence.csv> --batch=redirect-cutover-YYYYMMDD
php artisan legacy-import:redirect-decisions <reviewed-evidence.csv> --write --approve=legacy-redirect-apply --batch=redirect-cutover-YYYYMMDD
php artisan legacy-import:redirect-rollback redirect-cutover-YYYYMMDD
php artisan legacy-import:redirect-rollback redirect-cutover-YYYYMMDD --write --approve=legacy-redirect-rollback
```

The workflow must:

- consume only reviewed/approved rows
- store normalized path and normalized query signature/hash
- avoid path-only redirect collisions for different query targets
- reject external/unsafe targets unless explicitly allowlisted
- enforce one-hop `301` routes where permanent
- support `410` decisions separately
- not overwrite manual rules silently
- write audit/migration logs and batch ID
- invalidate continuity cache
- support rollback by batch
- produce created/updated/skipped/rejected report

#### E4.5 Existing Code Changes Required Before Cutover

- [x] Verify `RedirectContinuityMiddleware` safely handles `/admin/index.php` as public Business route before generic `/admin` bypass.
- [x] Extend exact redirect lookup to consider normalized query signature for exceptions, or use deterministic resolvers exclusively where valid.
- [x] Replace/guard static page resolver assumptions based on `jx_site_static_pages` audit finding.
- [ ] Add `jx_councils` public resolver/import path.
- [ ] Add `/members/` context isolation.
- [x] Add `show_cat` and `ser` alias normalization tests.
- [ ] Add 5-language inbound policy tests.
- [ ] Add test ensuring hidden legacy source never redirects to public target unless explicit archival policy allows it.

Exit criteria:

- Every high-value route family resolves to correct one-hop outcome in staging.

### Phase E5: cPanel File and Web-Server Cutover Design

The database is not responsible for files. Apache/LiteSpeed/PHP filesystem access is.

#### Required Production Discovery

Record outside Git:

- actual cPanel account and PHP user
- current old website document root
- new Laravel release path
- final domain document root
- absolute file root for old uploaded files
- Apache/LiteSpeed configuration ability
- symlink/alias permissions
- old directory ownership and read permissions
- backup locations/checksums

#### Required Static File Behavior

Old public static paths must remain available:

```text
/downloads/files/*
/downloads/files/thumb/*
/images/*
/{subsite}/images/*
/pdf/*
```

Recommended principle:

```apache
RewriteEngine On

# Existing approved static legacy file/directory: web server serves it.
RewriteCond %{REQUEST_FILENAME} -f [OR]
RewriteCond %{REQUEST_FILENAME} -d
RewriteRule ^ - [L]

# Non-file dynamic requests: Laravel front controller.
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [L]
```

Do not deploy this exact snippet until staging confirms it works with the hosting layout and does not expose unwanted old PHP/source directories.

Critical security rules:

- Do not leave old PHP front controllers executable under public paths if Laravel replaces them.
- Preserve static uploads, not old executable PHP application code.
- Deny public access to old CMS engine and backups.
- Deny directory listing.
- Normalize/reject traversal requests.
- Keep private legacy files outside public aliases.

#### File Inventory Reconciliation

Do not use old local rclone results as launch truth.

Operational procedure and the read-only cPanel probe are documented in `CPANEL_FILE_CONTINUITY_RUNBOOK.md`. The probe command is:

```text
php artisan legacy-import:file-continuity-probe /absolute/legacy/public/root --target-root=/absolute/laravel/public
```

It inventories only approved static trees, fingerprints roots without exporting absolute paths, computes checksums, records encoded URL paths, detects case collisions, target-path collisions, and symlink escapes, and blocks executable/sensitive files from being treated as safe static assets.

On production/staging cPanel paths:

1. Run file inventory against actual old root or create a server-side manifest.
2. Validate direct URL `200`, content type, size, encoded filename, PDF range/download behavior, and no directory listing.
3. Update `legacy_file_inventory` only from this reliable evidence.
4. Map only intentionally moved files to canonical replacements.

Exit criteria:

- Representative old media/document URLs work without Laravel errors.
- No required legacy static file is accidentally routed to an unresolved Laravel 404.

### Phase E6: Final Production Readiness Validation

Run all checks on staging first, then production after cutover.

#### Migration Validation

- [ ] Every `migration_logs.success` target exists.
- [ ] Source/target count reconciliation by module is documented.
- [ ] Disabled/draft state is correct.
- [ ] AR/EN translations exist where required.
- [ ] Unsafe HTML, spam links, invalid emails, unsupported locales, duplicates, and private data have correct disposition.

#### Redirect Validation

- [ ] One smoke test for every route family in audit CSV.
- [ ] Arabic and English test for every applicable family.
- [ ] Reordered query test.
- [ ] Alias tests: `ser`, `service`, `Ser`, `show_cat`.
- [ ] Correct unknown ID behavior.
- [ ] Correct hidden/disabled source behavior.
- [ ] No redirect loops or chains.
- [ ] No unsafe destinations.
- [ ] `/admin/` Business public URLs work; old CMS URLs do not map to new admin.
- [ ] Static files serve or redirect correctly.
- [ ] `continuity:validate-redirects` passes.
- [ ] `continuity:reconciliation-report` is reviewed.
- [ ] `continuity:report-unresolved` is empty or has accepted backlog only.

#### Website/Product Validation

- [ ] Full test suite passes.
- [ ] `composer install --no-dev --optimize-autoloader` passes.
- [ ] `npm run build` passes.
- [ ] All public routes render desktop/mobile AR/EN RTL/LTR.
- [ ] Draft/preview access is protected.
- [ ] Forms, queues, mail, rate limits, policies, audit logs, scheduler, cache, and storage work.
- [ ] Canonical, hreflang, sitemap, robots, OG metadata, and structured data work.
- [ ] `launch:validate --environment=staging` passes.

### Phase E7: Cutover and Stabilization

#### Before Cutover

1. Production database backup.
2. Old database backup, unchanged as rollback source if possible.
3. Old static filesystem backup/manifest.
4. Final redirect decision register approved.
5. Final redirect apply dry-run reviewed.
6. Staging production-layout rehearsal passed.
7. Rollback tested.

#### Cutover

1. Enable maintenance/low-write mode only if necessary.
2. Deploy immutable Laravel release.
3. Set final document root/rewrite/static-file rules.
4. Configure production environment and old file disk/path access.
5. Run migrations and cache/route/view/config optimization.
6. Apply approved redirect batch.
7. Run:

```bash
php artisan continuity:validate-redirects
php artisan continuity:reconciliation-report
php artisan launch:validate --environment=production
```

8. Verify externally:

```text
https://spu.edu.sy/sitemap.xml
https://spu.edu.sy/robots.txt
```

9. Execute high-priority old URL and file smoke matrix from outside cPanel.
10. Monitor logs, queue workers, 404s, redirect hits, and file responses.

#### After Cutover

- Daily unresolved URL triage for 14 days.
- Weekly unresolved URL triage for 90 days.
- Add only evidence-backed redirect rules with a regression test.
- Keep old files and old DB backup intact for at least the stabilization period.
- Never solve traffic-driven 404s with generic homepage redirects.

## Audit Artifact Intake Checklist

### Delivery Status: 2026-07-28

The professor's audit delivery manifest confirms that the audit package exists in the old-code workspace:

```text
/Users/amerkhorsheed/Desktop/spu_web/legacy-audit-output/legacy-audit-artifacts.zip
```

Reported package facts:

- ZIP size: `34,822` bytes.
- SHA-256: `54c1578a6c13989b23e49336acf5aa18bc096e4bc5712a4e0924824855aeb1e9`.
- Safe contents: audit report, delivery manifest, and ten CSV files only.
- Excluded: old source code, SQL dump, uploads, CMS engine, credentials, user data, backups, and private keys.

Artifact counts reported by the manifest:

| Artifact | Status | Rows |
| --- | --- | ---: |
| URL builders | Complete | `380` |
| Core route dispatch families | Complete | `15` |
| Database ownership map | Complete | `23` |
| Service/discriminator mappings | Complete | `114` |
| File path map | Complete | `7` |
| Locale dictionary | Complete | `5` |
| Redirect candidate families | Complete | `18` |
| Unknown/runtime evidence register | Complete | `8` |
| Public URL smoke tests | Complete | `15` |
| `jx_categories` matrix | Partial semantic matrix | `30` aggregate service/subsite rows |

The ZIP is useful but is **not a cutover or implementation blocker**. If it becomes available, copy it into private evidence storage and verify its SHA-256 before trusting it. If it cannot be transferred, reconstruct only the required structured data from the audited Markdown report and the local read-only `spu_legacy` database.

### One Remaining Audit Data Gap

The only material incomplete artifact is the per-record `jx_categories` matrix.

- The delivered `30` rows describe service-type/subsite semantics.
- About `4,914` individual category rows still require a read-only export.
- The professor supplied the exact read-only SQL query in the delivery manifest.
- We can run that query against the locally configured `spu_legacy` database, enrich it with current Laravel mappings/logs, and create the canonical per-record matrix ourselves.

Do not use the query's initial `current_new_module_candidate` or `current_new_route_candidate` placeholders as final decisions. Those fields must be enriched from the final Laravel route inventory, target records, and editorial review.

### Optional Artifact Acceleration

The report alone is enough to begin remediation and final planning. The CSV artifacts save analysis time, but their absence must not block the project.

- [ ] `legacy_url_builders.csv`
- [ ] `legacy_route_dispatch.csv`
- [ ] `legacy_database_ownership.csv`
- [ ] `legacy_service_type_dictionary.csv`
- [ ] `jx_categories_migration_matrix.csv`
- [ ] `legacy_file_path_map.csv`
- [ ] `legacy_locale_dictionary.csv`
- [ ] `legacy_redirect_candidate_families.csv`
- [ ] `legacy_unknowns_and_required_runtime_evidence.csv`
- [ ] `legacy_public_url_smoke_test_matrix.csv`

When available, import them into the private evidence workflow and use them to generate:

1. typed migration approval packets
2. resolver implementation backlog
3. exact redirect decision register
4. cPanel static path test matrix
5. final production smoke suite

### Immediate Next Execution Steps

1. Optional: Transfer `legacy-audit-artifacts.zip` into private evidence storage, verify SHA-256, and do not commit it to Git.
2. Run the audit-supplied read-only `jx_categories` export against local `spu_legacy`.
3. Rebuild the minimum required structured artifacts locally from the report/database:
   - complete per-record `jx_categories` matrix
   - route family registry
   - service/subsite dictionary
   - file prefix/path registry
   - redirect family registry
   - production smoke-test matrix
4. Enrich extracted records with source visibility, hierarchy, file/link flags, current imported target mappings, and current Laravel route candidates.
5. Produce small typed approval packets by subsite + service suffix, beginning with existing public routes and high-value URL families.
6. Reconcile existing `jx_councils1`, research, and static-page imports against audit evidence before creating any final redirects.

## Hard Stop Conditions

Do not cut over while any of these are unresolved:

- public `/admin/` Business routes collide with the Laravel admin skip/routing behavior
- old `/downloads/files/` or `/pdf/` paths fail on staging production layout
- `jx_categories` is being bulk-imported without subsite/service/context classification
- `jx_councils1` profiles are treated as public continuity targets without proof
- research redirects rely on `/members/` sources without context reconciliation
- static page snippets are published as standalone pages without audited route evidence
- redirect plan lacks query signature protection for exceptional query URLs
- high-value old route families have no tested outcome
- old CMS/private data can reach public/new admin routes
- rollback has not been rehearsed

## Final Definition of Complete

The work is complete only when:

- The final Laravel website is fully production ready.
- Each old database group has a documented final disposition.
- All high-value dynamic old URLs have correct tested destinations/outcomes.
- All required old files are still served from cPanel or intentionally redirected.
- No unsafe/public/private boundary is violated.
- Search engines receive canonical `301` continuity, correct sitemap/robots/canonical/hreflang signals.
- Remaining unknowns are logged, actively triaged, and never hidden by homepage fallbacks.
