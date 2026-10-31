# SPU Old Website To New Website Redirect Migration Record

## Document Purpose

This document records the work completed in the Laravel repository for moving public URL continuity from the old SPU website to the new website.

It covers:

- old URL discovery and classification;
- old database and legacy URL normalization;
- exact redirects;
- query-string redirects;
- pattern redirects;
- reference frontend `.html` aliases;
- direct route compatibility redirects;
- Arabic/English and retired-language behavior;
- public Business-subsite versus Laravel admin separation;
- private `/members/` behavior;
- legacy file and PDF continuity;
- evidence generation, review, approval, persistence, and rollback;
- validation, tests, deployment, monitoring, and rollback procedures;
- remaining gaps and launch blockers.

This is an implementation and handoff record. It is not a claim that the final production cutover has already happened.

**Repository state reviewed:** `2026-08-21`

**Repository:** `C:\Users\hamza\SPU_Website`

**Canonical public URL shape:** `https://spu.edu.sy/{locale}/...`, where `{locale}` is `ar` or `en`.

## Executive Status

### Implemented

- A global Laravel continuity middleware runs before application routes.
- Exact database-backed redirect rules exist.
- Regex pattern redirect rules exist.
- Legacy query-router URLs are normalized and resolved through typed resolvers.
- Legacy `ser`, `Ser`, `service`, `cat`, `cat_id`, `mylang`, and `show_cat` aliases are normalized.
- Legacy subsite and language context is retained during resolution.
- The approved 175-file reference frontend inventory has exact `.html` and nested `index.html` aliases.
- Browser-locale negotiation handles unprefixed legacy/reference paths.
- Explicit locale paths remain authoritative.
- Old public `/admin/index.php` Business-faculty URLs are separated from the new Laravel `/admin` panel.
- Supported-language `/members/` continuity is intentionally private and unresolved.
- Retired legacy language IDs `3`, `6`, and `7` have an explicit temporary `302` fallback to `/en`.
- Unknown language IDs remain unresolved and are not allowed to use the fallback.
- Mapped legacy files can redirect once to a current path.
- Existing physical legacy static files are designed to be served directly by Apache/LiteSpeed.
- Unresolved requests are logged with normalized legacy metadata.
- URL inventory, generated URL inventory, triage, evidence, validation, reconciliation, and rollback tooling exists.
- Redirect writes are dry-run-first, approval-gated, transactional, idempotent, audited, and batch rollback-capable.
- Redirect rule safety includes allowlisted destinations, duplicate detection, conflict detection, loop detection, self-redirect detection, and locale validation.
- Focused unit and feature coverage exists for the runtime, resolver families, aliases, file continuity, evidence pipeline, validation, and approval workflow.

### Not Yet Proven For Production

- The actual cPanel legacy public file root has not been verified as final launch evidence in this local workspace.
- The final production old URL universe still needs to be regenerated from the final database, old source, Search Console, access logs, and production file manifest.
- Most generated legacy URL candidates remain blocked, unresolved, or awaiting mapping and editorial approval.
- Not every old module has a public new equivalent.
- The `LegacyPageQueryResolver` class exists but is not registered in the active resolver registry; generic imported static-page query URLs must not be described as active runtime redirects without correcting that wiring and adding coverage.
- `LegacyQueryResolverRegistry` does not currently register a generic council resolver. Public staff and council imports remain approval/publication-gated.
- The direct research detail controller redirect currently uses Laravel's default redirect status instead of explicitly setting `301`; this should be corrected if that compatibility path is intended to be permanent.
- Production cPanel document-root, Apache/LiteSpeed alias, symlink, ownership, MIME, range-request, and Linux case-sensitivity checks remain required.
- The historical evidence counts in this document must be regenerated before final cutover.

## 1. Migration Rules We Implemented

The redirect work follows these rules:

| Rule | Implemented behavior |
|---|---|
| Preserve intent | A legacy URL is mapped to the closest proven public equivalent, not automatically to the homepage. |
| Permanent moves | Proven permanent replacements use `301`. |
| Temporary fallback | Retired language handling uses the explicitly approved `302` fallback. |
| No guessing | Unknown, ambiguous, private, disabled, or unreviewed targets remain unresolved. |
| No homepage dumping | Unknown or valuable unresolved URLs are never sent to `/ar` or `/en` as a generic fallback. |
| Locale preservation | Reliable old Arabic/English context becomes the matching localized destination. |
| Query identity | Legacy router redirects are identified by normalized path plus normalized query signature. |
| One hop | Redirect chains are limited and loop-checked. |
| File distinction | A static file is served directly, redirected to a verified replacement, or logged as an unresolved file; it is not sent to an unrelated HTML page. |
| Security boundary | Old public Business URLs are not treated as the new Laravel admin panel. |
| Publication boundary | Draft, disabled, private, scheduled, or missing-locale targets cannot become public redirect targets. |
| Auditability | Applied redirect decisions retain source identity, evidence checksum, approval metadata, batch ID, and migration logs. |

The supported final outcomes are:

| Outcome | Result |
|---|---|
| Canonical migration | `301` to the stable localized new URL. |
| Equivalent redirect | `301` to a documented current equivalent. |
| File served in place | `200` from the web server at the old path. |
| File redirect | `301` to a verified current file path. |
| Archive | A public archive destination when a current module is not appropriate. |
| Intentional retirement | `410` or `404` after a documented decision. |
| Unknown/unresolved | Real `404`, logged for triage. |
| Retired language exception | Approved `302` to `/en`. |

## 2. What The Old Website Actually Used

The old SPU website was not based only on clean modern slugs. Its public address space included database-backed query-router URLs and multiple subsite routers.

### Legacy URL families identified

- `/index.php?...`
- `/windex.php?...`
- `/med/index.php?...`
- `/dent/index.php?...`
- `/pharm/index.php?...`
- `/info/index.php?...`
- `/petrol/index.php?...`
- `/admin/index.php?...`
- `/research/index.php?...`
- `/hospital/index.php?...`
- `/dent_clinic/index.php?...`
- `/alumni/index.php?...`
- `/clubs/index.php?...`
- `/members/index.php?...`
- old `.html` files;
- nested `/index.html` files;
- direct `/downloads/files/...` files;
- direct `/images/...`, `/pdf/...`, subsite image, CV, research, and document paths;
- explicit legacy URL fields stored in old database records;
- internal legacy links embedded in imported content.

### Old language identifiers

| Old value | Old symbol | New locale/policy |
|---:|---|---|
| `1` | Arabic | `ar` |
| `2` | English | `en` |
| `3` | French | Retired-language `302` to `/en` |
| `6` | Spanish | Retired-language `302` to `/en` |
| `7` | German | Retired-language `302` to `/en` |
| Any other value | Unknown | No fallback; unresolved `404` and log |

### Old subsite identifiers

| Old path prefix | Site ID | Interpreted role |
|---|---:|---|
| root | `0` | Main SPU website |
| `/med` | `2` | Medicine |
| `/dent` | `3` | Dentistry |
| `/pharm` | `4` | Pharmacy |
| `/info` | `5` | Artificial Intelligence / Information-related faculty |
| `/petrol` | `6` | Petroleum |
| `/admin` | `7` | Public Business Administration faculty, not Laravel admin |
| `/research` | `8` | Research subsite |
| `/hospital` | `9` | Hospital |
| `/dent_clinic` | `10` | Dental clinic |
| `/alumni` | `11` | Alumni |
| `/clubs` | `12` | Clubs and activities |
| `/members` | `13` | Private members archive policy |

### Old database sources used by the continuity work

| Legacy table | URL/content meaning used by migration work |
|---|---|
| `jx_categories` | Main categories, news, announcements, navigation categories, and generated item URLs |
| `jx_items` | Child media and attachments |
| `jx_site_static_pages` | Static page source records and generated static-page candidates |
| `jx_docs` | Documents, files, language-specific links, and legacy internal links |
| `jx_sites` | External or named site links |
| `jx_member_categories` | Research/publication-like members content |
| `jx_member_items` | Member content attachments and child items |
| `jx_councils` | Public staff/profile source candidates |
| `jx_councils1` | Alternate/ambiguous profile source retained for reconciliation evidence |
| `jx_home_photos` | Homepage media references |
| `jx_faqs` | FAQ source records, kept in a separate review/import lane |
| `jx_job_sites` | External career-link candidates |
| `jx_graduated_students` | Alumni source records |
| `jx_good_students` | Honor-student source records |
| `jx_config`, `jx_config1` | Legacy settings and subsite context |

## 3. Git Implementation History

The redirect work was delivered incrementally. The key continuity milestones are:

| Commit/date | Work recorded |
|---|---|
| `9c3b7c3`, 2026-04-27 | Initial PX05 SEO and continuity layer: redirect tables, models, DTOs, contracts, services, controller/middleware, routes, and initial file continuity foundation. |
| `9fa2fc7`, 2026-04-27 | PX07 backfill and operational CLI commands for URL inventory, file inventory, reconciliation, unresolved reporting, redirect validation, and SEO validation. |
| `80890a1`, 2026-04-28 | Continuity, admin-flow, and launch-check hardening. |
| `70f2607`, 2026-06-15 | Redirect safety and publish-safety regression/property coverage. |
| `59ba6b0`, 2026-07-08 | Hardened legacy migration foundation: old DB configuration, normalization, staged evidence, generated URL inventory, URL triage, redirect evidence, file inventory, and guarded import workflows. |
| `c6676c4`, 2026-07-19 | Full-site parity/release-readiness work, including route compatibility and reference-page coverage. |
| `f5ad093`, 2026-07-29 | Production-readiness continuity work: query resolvers, subsite-home resolver, file continuity probe, cPanel runbooks, redirect decision apply/rollback, private policy handling, and approval workflows. |
| `decde21`, 2026-07-31 | News and research improvements that changed which imported records could become public redirect targets. |
| `7186e26`, 2026-08-15 | Current branch state reviewed for this handoff; redirect behavior must still be revalidated against the final database before production. |

## 4. Runtime Request Flow

### Middleware registration

`bootstrap/app.php` prepends `RedirectContinuityMiddleware` to the web middleware stack. This is important because continuity is checked before normal route handling.

The middleware is injected through:

- `ContinuityServiceInterface`;
- `LegacyUrlNormalizerInterface`.

Bindings are registered in `app/Providers/AppServiceProvider.php`.

### Runtime sequence

For a safe incoming request, the effective flow is:

```text
request
  -> skip admin/Livewire/Filament unless path is /admin/index.php
  -> skip unsafe HTTP methods
  -> resolve browser preferred locale when needed
  -> normalize path and query context
  -> retired-language policy
  -> unknown-language rejection
  -> private /members/ policy
  -> approved physical reference .html alias
  -> exact database redirect
  -> typed legacy query resolver
  -> pattern redirect
  -> file inventory redirect when request has a file extension
  -> normal Laravel route handling
  -> unresolved 404 logging in terminate()
```

### Middleware exclusions

The middleware skips these prefixes:

- `/admin`
- `/livewire`
- `/filament`

The exception is `/admin/index.php`, which must remain eligible because the old `/admin` path was a public Business Administration subsite, not the old CMS login.

Unsafe methods do not run redirect resolution. This prevents a redirect rule from changing the behavior of POST or other write requests.

### Unresolved request behavior

When the downstream application returns `404`, middleware termination records the request without blocking the response. The log includes:

- full URL;
- raw query string;
- method;
- referrer;
- resolved locale;
- page/file request type;
- normalized path and parameter payload;
- handler key;
- outcome;
- subsite;
- old site ID;
- old language ID and symbol;
- first/last seen timestamps;
- deduplicated hit count.

Repeated identical URL/method requests update one record and increment `hit_count`.

## 5. Core Continuity Engine

### `ContinuityService`

`app/Services/Shared/ContinuityService.php` owns the public continuity behavior.

It provides:

- `resolveRedirect()`;
- `resolveFileContinuity()`;
- unresolved request logging;
- exact redirect DTO retrieval;
- pattern rule DTO retrieval;
- redirect rule validation;
- unresolved request reporting;
- file inventory reporting.

### Exact redirect lookup

Exact lookup uses:

- case-insensitive legacy path matching;
- normalized query signature matching;
- active rules only;
- query-aware preference;
- continuity cache tags;
- hit count and last-hit timestamp updates;
- destination allowlisting;
- destination query/fragment sanitization.

For a legacy router such as `/index.php`, a query-specific rule must carry a matching query signature. This prevents one path-only rule from incorrectly capturing multiple old records identified by different query parameters.

### Pattern lookup

Pattern rules are stored in `legacy_pattern_rules` and evaluated after exact rules and typed query resolution.

Pattern behavior includes:

- active-only selection;
- priority ordering;
- regular-expression capture replacement such as `$1`;
- destination safety checks;
- hit counts and last-hit timestamps;
- invalid-pattern logging instead of application failure.

Exact redirects always win over pattern rules. Typed query resolution also wins over a generic pattern for the same request.

### Redirect-chain protection

The service follows at most five redirect hops while checking for repeated paths. It logs loops and returns the last valid result instead of hanging.

Validation separately checks exact redirect path maps for potential loops.

### Destination safety

Relative destinations are allowed. Absolute destinations are limited to:

- `spu.edu.sy`;
- configured `CONTINUITY_ALLOWED_HOSTS` values;
- subdomains of `spu.edu.sy`.

Unsupported schemes such as `javascript:` are rejected. Database exact/pattern destinations are sanitized to remove query strings and fragments. Approval-applied destinations must be localized and start with the approved `ar` or `en` path.

## 6. Legacy URL Normalization

`app/Services/Legacy/LegacyUrlNormalizer.php` converts old requests into a typed `NormalizedLegacyUrlDTO`.

### Normalization performed

- Converts full URLs or paths into a normalized path.
- Converts backslashes to forward slashes.
- Parses query parameters.
- Discards array-valued parameters from the resolver identity.
- Maps `ser` and `Ser` to `service`.
- Maps `cat` to `cat_id`.
- Maps `show_cat` to `show`.
- Converts presence of `mylang` into Arabic language `1` when `lang` is absent.
- Resolves old language ID and symbol.
- Resolves old subsite and site ID.
- Identifies `index.php`, `windex.php`, `slider.php`, and `sitemap.xml` entrypoints.
- Identifies `legacy_router`, `legacy_media_file`, `legacy_slider`, `sitemap`, or `unknown` request types.
- Creates handler keys such as `root:items:show`, `admin:items:list`, and `members:councils:show`.
- Sorts normalized parameters into a stable RFC3986 query signature when used for evidence or exact redirect identity.

This avoids resolving an overloaded old `cat_id` or `id` without its subsite, directory, page, service, and language context.

## 7. Active Typed Query Resolvers

The active registry is `app/Services/Legacy/LegacyQueryResolverRegistry.php`.

Its active resolver order is:

1. unsupported-language resolver;
2. subsite-home resolver;
3. functional-route resolver;
4. reviewed root-category resolver;
5. public news resolver;
6. public research resolver.

### 7.1 Legacy subsite homes

`LegacySubsiteHomeQueryResolver` maps query-router home requests to localized current landing pages:

| Old context | New destination suffix |
|---|---|
| root | `/` |
| `med` | `/facilities/medicine` |
| `dent` | `/facilities/dentistry` |
| `pharm` | `/facilities/pharmacy` |
| `info` | `/facilities/artificial-intelligence` |
| `petrol` | `/facilities/petroleum` |
| public `admin` subsite | `/facilities/business-administration` |
| `research` | `/research` |
| `hospital` | `/campus-life/hospital` |
| `dent_clinic` | `/campus-life/dental` |
| `clubs` | `/campus-life/clubs-activities` |

The resolver prefixes the destination with `/ar` or `/en` based on old language context.

`members` is intentionally excluded from generic subsite-home resolution because it is governed by the private archive policy.

### 7.2 Functional route signatures

`LegacyFunctionalRouteQueryResolver` accepts only exact reviewed signatures:

| Old normalized signature | New destination |
|---|---|
| `dir=html&ex=1&lang=1&page=contactus` | `/ar/contact` |
| `dir=html&ex=1&lang=2&page=contactus` | `/en/contact` |
| `dir=html&ex=1&lang=1&page=complaint` | `/ar/e-services/suggestions-complaints` |
| `dir=jobs&ex=2&lang=1&page=list&service=49` | `/ar/campus-life/career-development/jobs` |

Near-miss signatures are rejected rather than guessed.

### 7.3 Reviewed root category routes

`LegacyCategoryRouteQueryResolver` uses exact source ID plus exact service type. It does not use title similarity or a generic category fallback.

The reviewed source ID groups currently encoded in the resolver are:

| Destination | Service 1 IDs | Service 2 IDs |
|---|---|---|
| `/ar` or `/en` | `12` | `2029` |
| `/about` | `16` | none |
| `/about/accreditation` | `28` | none |
| `/admissions/academic-warnings` | `1237` | none |
| `/e-services/suggestions-complaints` | none | `60` |
| `/facilities/medicine` | `299`, `1525`, `1641` | `1261`, `1270` |
| `/facilities/dentistry` | `298`, `1526`, `1647` | `1263`, `1271` |
| `/facilities/pharmacy` | `297`, `1527`, `1646` | `1272`, `1640` |
| `/facilities/artificial-intelligence` | `296`, `1528`, `1645` | `1265`, `1273` |
| `/facilities/petroleum` | `295`, `1529`, `1644` | `1266`, `1274` |
| `/facilities/business-administration` | `294`, `1530`, `1643` | `1264`, `1275` |

The destination is localized according to old language ID. A wrong service, wrong subsite, or unknown source ID remains unresolved.

### 7.4 News and announcement detail URLs

`LegacyNewsQueryResolver` handles root `items/show` requests for service types `3` and `4` when:

- the source ID resolves from `cat_id`, `id`, or `act`;
- the imported article has the matching legacy source table and source ID;
- the service type matches;
- the article is enabled and published;
- the requested locale translation exists.

The destination is:

```text
/{locale}/news/{numeric-new-article-id}
```

Numeric canonical article URLs are used so old source IDs can be traced without relying on unstable historical slugs.

Imported drafts, disabled articles, scheduled articles, missing-locale articles, and unknown source IDs do not redirect.

### 7.5 Research publication detail URLs

`LegacyResearchQueryResolver` handles only public service-1 member publication requests:

```text
/members/index.php?page=show&dir=items&service=1&cat_id={id}&lang={1|2}
```

It asks `ResearchPageServiceInterface` for the approved publication slug for the legacy ID and redirects to:

```text
/{locale}/research/publications/{slug}
```

Service-2 teaching/archive content remains private and unresolved. Supported-language members requests cannot bypass the private policy through exact rules, patterns, file mappings, or the research resolver.

### 7.6 Static-page resolver status

`app/Services/Legacy/QueryResolvers/LegacyPageQueryResolver.php` contains logic that can resolve an imported `jx_site_static_pages` record through a successful `migration_logs` entry and a published/enabled page target.

However, the current `LegacyQueryResolverRegistry` does not inject or register this resolver. Therefore:

- the class is present as an implementation candidate;
- it is not active in the current runtime registry;
- tests intentionally reject generic static snippet guessing;
- generic static-page query continuity must not be marked complete until registry wiring, publication rules, and tests are finalized.

## 8. Approved Physical Reference HTML Continuity

`config/reference_html_aliases.php` contains the complete approved physical reference inventory.

The inventory is tested to contain:

- `175` unique source paths;
- `175` unique destinations;
- only absolute path source keys beginning with `/`;
- only `.html` source files;
- destination paths without query strings or fragments.

The reference count is derived from:

- `167` entries in the approved reference frontend page inventory;
- `8` generated career-detail pages;
- `175` effective physical files.

### Alias families included

- `/index.html` -> localized homepage;
- top-level About, Admissions, Research, Campus Life, E-Services, News, Contact, Facilities, and Virtual Tour files;
- nested Research center, project, publication, researcher, theme, conference, library, policy, office, and expert-finder files;
- nested Admissions files;
- nested Campus Life files;
- nested E-Services files;
- nested News and event files;
- all seven faculty/facility families;
- all nested overview, department, laboratory, project, research, study-plan, course, alumni, and valedictorian files;
- all eight generated career-detail files;
- renamed `student-life.html` -> `/campus-life`;
- renamed `services.html` -> `/e-services`;
- `/about/partnership.html` -> `/about/partnerships`.

### Alias status behavior

| Request shape | Status | Behavior |
|---|---:|---|
| Explicit `/ar/...html` or `/en/...html` | `301` | Uses the explicit locale. |
| Unprefixed `/...html` with browser language | `302` | Negotiates `Accept-Language`, then redirects to localized path. |
| Unprefixed `/...html` without language | `302` | Defaults to `/ar`. |
| Alias with a query string | Redirect | Preserves the raw query string for compatibility. |
| Unapproved `.html` path | No alias | Falls through to normal routing and normally becomes a logged `404`. |
| POST or other unsafe method to alias | No redirect | Does not redirect through the safe-method middleware path. |

Unprefixed aliases set `Vary: Accept-Language` and `Cache-Control: no-store, private`. Explicit locale aliases use public one-day caching and do not need `Vary`.

Physical reference aliases are authoritative over database exact rules. A conflicting database rule cannot override an approved reference alias.

## 9. Direct In-Application Compatibility Routes

These are route/controller-level redirects for old or compatibility paths that are already understood by the new application.

| Old/compatibility request | Current result | Code location |
|---|---|---|
| `/` | Browser-locale redirect to `/ar` or `/en` | `BrowserLocaleRedirectController` |
| Unprefixed approved deep path such as `/about` | Browser-locale redirect to `/{locale}/about` | `routes/web.php`, `BrowserLocaleRedirectController` |
| `/{locale}/faculties/{legacyPath}` | Canonical `/facilities/...` path with faculty slug normalization | `FacultyController::redirectLegacy` |
| `/{locale}/facilities?id={legacy-id}` | Canonical localized facility slug | `FacultyController::hub` |
| `/{locale}/projects/detail?id={id}` | Resolved canonical faculty project URL | `FacultyController::redirectLegacyProject` |
| `/{locale}/about/university-council` | `/{locale}/about/leadership` | `AboutController::redirectUniversityCouncil` |
| `/{locale}/about/partnership` | `/{locale}/about/partnerships` | `AboutController::redirectPartnershipAlias` |
| `/{locale}/about/directorates/it-services` | `/{locale}/e-services/it-support` | `AboutController::directorateDetail` |
| `/{locale}/about/profile?slug=...` or `?id=...` | Resolved `/{locale}/about/profile/{slug}` | `AboutController::redirectLegacyProfile` |
| `/{locale}/about/profile/{source}/{slug}` | `/{locale}/about/profile/{slug}` | `AboutController::profileLegacy` |
| `/{locale}/admissions/study-system` | `/{locale}/admissions/documents?tab=study-system` | `AdmissionsController::redirectToDocuments` |
| `/{locale}/admissions/academic-warnings` | `/{locale}/admissions/documents?tab=academic-warnings` | `AdmissionsController::redirectToDocuments` |
| `/{locale}/news/article?id=...` | Published article DTO URL | `NewsController::redirectLegacyArticle` |
| `/{locale}/research/detail?id=...` | Publication slug route | `ResearchController::legacyDetail` |
| `/{locale}/research/publications/detail?id=...` | Publication slug route | `ResearchController::legacyDetail` |

Permanent compatibility routes explicitly use `301` except where noted in the remaining-gaps section. External portal redirects, form POST responses, and authentication redirects are application behavior and are not old-site URL continuity rules.

## 10. Redirect Decision And Persistence Workflow

Evidence is deliberately separate from production redirect rules.

### Evidence pipeline

```text
legacy DB and current DB
  -> URL inventory
  -> generated URL inventory
  -> URL triage
  -> redirect evidence export
  -> private editorial review
  -> redirect decision dry run
  -> approved transactional apply
  -> validation and smoke tests
```

### Inventory sources

`LegacyUrlContinuityInventoryService` combines:

- existing exact redirect rules;
- Phase 3 internal-link review rows;
- Phase 4 redirect-to-equivalent mapping proposals;
- observed unresolved request records;
- legacy file inventory rows unless `--without-files` is used.

`LegacyGeneratedUrlInventoryService` generates evidence-backed candidate URLs from:

- explicit URL/file columns;
- `jx_categories` router patterns;
- `jx_member_categories` router patterns;
- `jx_councils` and `jx_councils1` profile patterns;
- `jx_site_static_pages` static-page patterns.

Generated evidence is read-only. It never creates redirects by itself.

### Triage statuses

| Status | Meaning |
|---|---|
| `resolver_candidate` | Handler and source ID are parseable and mapping evidence exists; a resolver may be designed or reviewed. |
| `needs_phase4_mapping` | Source/target mapping is not approved or does not exist. |
| `blocked_missing_target_module` | New equivalent module is not ready. |
| `blocked_target_not_public` | Target exists but is disabled, draft, scheduled, private, or missing the requested locale. |
| `blocked_file_url` | File bytes or a verified file mapping is missing. |
| `unknown_legacy_url` | URL shape or semantics are unknown. |

### Redirect evidence output

`LegacyRedirectEvidenceService` writes private:

- Markdown summary;
- all-row CSV;
- preview-ready CSV;
- blocked/backlog CSV;
- JSON summary.

Preview-ready runtime rows are marked:

- `redirect_readiness=preview_ready`;
- `evidence_status=resolver_ready`;
- `approval_status=runtime_resolver`;
- blank approval fields until a reviewer decides.

Blocked rows retain blocker reasons such as:

- missing content mapping;
- unapproved mapping;
- missing imported target;
- private target;
- missing target module;
- file dependency;
- Phase 3 findings;
- unknown URL;
- unresolved continuity backlog.

### Approval fields

A row is not eligible for persistence until it has:

- `approval_decision=redirect`;
- a non-empty `approved_by`;
- valid `preview_ready`/`resolver_ready`/`runtime_resolver` evidence state;
- no blockers;
- supported locale `ar` or `en`;
- status code `301`;
- safe normalized source path;
- safe localized target;
- matching normalized evidence;
- current runtime resolution equal to the packet target;
- no duplicate redirect identity;
- no existing conflict;
- no self-redirect.

### Apply behavior

`LegacyRedirectDecisionService`:

- reads a private CSV packet;
- computes a SHA-256 evidence checksum;
- validates the CSV schema;
- validates every eligible row;
- requires one consistent approver per batch;
- uses a stable batch ID;
- writes exact redirect rows transactionally;
- writes `migration_logs` success entries;
- records evidence checksum and decision batch;
- refuses to overwrite conflicting existing rules;
- treats equivalent replay as idempotent;
- invalidates the continuity cache after a write.

### Rollback behavior

Rollback is restricted to one applied batch. It:

- requires a separate rollback approval token;
- deletes only rows created by that batch;
- writes rolled-back migration logs;
- marks the batch `rolled_back`;
- refuses reuse of a rolled-back batch ID;
- invalidates the continuity cache.

## 11. Database Structures Added For Continuity

### `legacy_exact_redirects`

Created by `2026_04_19_000001_create_legacy_exact_redirects_table.php` and extended by `2026_07_29_000001_add_decision_metadata_to_legacy_exact_redirects.php`.

Stores:

- legacy path;
- normalized query signature;
- destination URL;
- status code;
- locale;
- active flag;
- hit count;
- last hit time;
- notes;
- decision batch;
- evidence SHA-256.

### `legacy_pattern_rules`

Created by `2026_04_19_000002_create_legacy_pattern_rules_table.php`.

Stores:

- regex pattern;
- replacement;
- status code;
- priority;
- active flag;
- hit count;
- last hit time;
- notes.

### `unresolved_legacy_requests`

Created by `2026_04_19_000003_create_unresolved_legacy_requests_table.php` and extended by `2026_07_04_000004_add_normalized_legacy_request_metadata.php`.

Stores deduplicated unresolved page/file requests and normalized legacy context.

### `legacy_file_inventory`

Created by `2026_04_19_000004_create_legacy_file_inventory_table.php` and extended by later file/checksum migrations.

Stores legacy file paths, current paths, media IDs, status, MIME/size, checksum state, and source evidence.

### `legacy_redirect_decision_batches`

Created by `2026_07_29_000001_add_decision_metadata_to_legacy_exact_redirects.php`.

Stores batch ID, evidence checksum, packet path, approver, applied/rolled-back state, redirect count, and timestamps.

### Supporting migration/audit tables

The continuity process also depends on:

- `migration_logs` for source-to-target provenance and redirect apply/rollback audit;
- `legacy_content_mappings` for source/target classifications and proposed/approved mapping evidence;
- `legacy_review_items` for staging/review blockers;
- `migration_rejections` for content and internal-link review findings;
- `legacy_record_snapshots` for imported/source evidence;
- private storage under `storage/app/private/legacy-import-exports` for packets and reports.

## 12. File, PDF, Image, And Media Continuity

### The key deployment fact

The database does not make old files available. Apache/LiteSpeed and PHP need filesystem access to the actual cPanel legacy public directories.

The chosen strategy is preserve-in-place first:

1. Identify the real old public file root on cPanel.
2. Keep approved static directories available during cutover.
3. Point the domain to Laravel `public/` only when those old URL paths remain safely reachable.
4. Serve existing approved physical files directly from Apache/LiteSpeed where possible.
5. Use one-hop `301` only for intentionally moved and verified files.
6. Keep private files, old controllers, CMS engine code, backups, SQL dumps, and configuration files outside public aliases.

### Configured legacy static directory families

`config/old_database.php` limits direct file continuity to:

- `downloads/files`;
- `downloads/files/thumb`;
- `downloads/files2`;
- `images`;
- `pdf`;
- `cv_bank`;
- `med/images`;
- `dent/images`;
- `pharm/images`;
- `info/images`;
- `petrol/images`;
- `admin/images`;
- `research/images`;
- `hospital/images`;
- `dent_clinic/images`;
- `alumni/images`;
- `clubs/images`.

### File inventory behavior

`LegacyFileInventoryService`:

- scans configured high-priority legacy file columns;
- is dry-run by default;
- stores normalized paths only with `--write`;
- records source table, source column, source ID, and references;
- checks configured `OLD_PUBLIC_ROOT` values;
- computes SHA-256, MIME type, and size when bytes are available with `--checksum`;
- distinguishes existing, missing, unverified, checksum-failed, symlink, and unexpected-error states;
- never moves, deletes, or promotes files automatically.

### cPanel file probe

`LegacyFileContinuityProbeService` is read-only and exports credential-safe evidence.

It checks:

- approved static directories only;
- encoded URL paths;
- SHA-256, MIME type, and size;
- case-collision groups;
- target path collisions against Laravel `public/`;
- identical versus differing target bytes;
- symlink escapes outside the supplied root;
- executable and sensitive filename extensions;
- dotfiles and hidden configuration files;
- missing configured directories.

The private JSON manifest stores a root fingerprint instead of an absolute production path.

### Web-server hardening

`public/.htaccess`:

- keeps directory indexing disabled;
- blocks executable/sensitive extensions in approved legacy static trees;
- blocks dotfiles;
- preserves Authorization and XSRF headers;
- lets existing files/directories bypass the Laravel front controller through `!-f`/`!-d` conditions;
- sends non-file/non-directory requests to Laravel.

This file is not a substitute for cPanel deployment verification. The final host must confirm that old static directories are actually beneath the document root or are safely exposed by exact aliases/symlinks.

### Media URL bridge

`Docs/LEGACY_MEDIA_DEPLOYMENT.md` documents `MediaUrlResolver::resolveLegacy()` and the `LEGACY_MEDIA_ENABLED`/`LEGACY_MEDIA_BASE_URL` configuration:

- same public root: leave `LEGACY_MEDIA_BASE_URL` unset;
- separate public legacy host/path: set a browser-accessible `LEGACY_MEDIA_BASE_URL`;
- never use a filesystem path as the browser base URL;
- retain old paths for news, research, alumni, honor students, faculty, council, career-link, and legacy media records;
- do not expose a private filesystem root through this setting.

## 13. Legacy Import And Mapping Work That Feeds Redirects

Redirect correctness depends on knowing whether a target is real and public. The related migration work therefore added gates before redirect persistence.

### Legacy database protection

`config/old_database.php` and `Docs/LEGACY_IMPORT_RUNBOOK.md` establish:

- dedicated `legacy_mysql` connection behavior;
- `OLD_DB_*` environment variables;
- `OLD_PUBLIC_ROOT` for file evidence;
- `OLD_DB_ALLOW_BROAD_IMPORT=false` by default;
- every module disabled by default;
- manual, one-module-at-a-time import execution;
- dry-run-first operation;
- explicit approval tokens;
- idempotent batch behavior;
- migration logs and rejection logs;
- private review packet storage.

### Cleaning and integrity gates

Before content can become a redirect target, the migration foundation checks:

- unsafe HTML and URL schemes;
- base64 inline images;
- spam URLs;
- fake dates;
- invalid emails;
- unsupported locales;
- duplicate and orphan records;
- unsafe or missing parent relationships;
- legacy internal links;
- file dependencies;
- hidden/private source state.

Rows with unresolved blockers stay out of public targets and redirect decisions.

### Classification outcomes

Legacy rows are classified into:

- `canonical_rebuild_now`;
- `archive_now_remodel_later`;
- `redirect_to_equivalent`;
- `file_only_preserve`;
- `quarantine`;
- `retire_after_approval`.

The classification and mapping tools do not automatically import content, publish content, promote files, or create redirects.

### Important publication rule

An imported record is not a valid redirect target merely because it exists in the new database. It must also be:

- enabled;
- published;
- not scheduled for the future;
- localized for the requested language;
- backed by valid source provenance;
- mapped to a canonical route;
- free of unresolved publication blockers.

## 14. Recorded Evidence Snapshots

The following numbers are historical recorded evidence from private reports and runbooks. They are not live production counts and must be regenerated before cutover.

### Phase 4 classification baseline

Recorded in `ULTRA_HARDENED_PHASE_STATUS.md`:

- `38,689` source rows classified and staged;
- `27` tables covered;
- `38,689` classified rows;
- `0` unknown/unruled rows;
- `4,944` `canonical_rebuild_now`;
- `22,797` `file_only_preserve`;
- `10,897` `archive_now_remodel_later`;
- `23` `redirect_to_equivalent`;
- `28` `retire_after_approval`;
- `20` safe redirect candidates from `links` in the recorded candidate report.

### URL-only continuity baseline from 2026-07-05

- `796` URL-only rows;
- `7` resolved;
- `789` unresolved/backlog;
- `12` resolver candidates;
- `395` blocked by missing target module;
- `335` unknown legacy URLs;
- `25` needing Phase 4 mapping;
- `22` blocked file URLs.

### Generated URL baseline from 2026-07-05

- `29,176` legacy source rows scanned;
- `12,749` generated URL rows;
- `12,580` generated router URLs;
- `169` generated explicit URLs;
- `6,070` resolved by the existing query resolver;
- `6,679` unresolved/backlog.

Generated evidence status counts recorded at that checkpoint:

- `6,070` `resolver_ready`;
- `2,486` `needs_imported_target`;
- `460` `blocked_missing_target_module`;
- `574` `blocked_phase3_findings`;
- `819` `blocked_file_dependency`;
- `2,339` `needs_phase4_mapping`;
- `1` unknown.

### Reviewed subsite-home redirect batch from 2026-07-29

- Batch: `approved-subsite-homes-20260729`;
- `14` reviewed;
- `14` approved;
- `14` applied;
- `14` success migration logs;
- replay created `0` additional rows and reported `14` idempotent;
- all `14` signatures resolved with `301`;
- all `14` targets rendered `200`;
- rollback preview found `14` batch redirects and deleted `0` because it was preview-only;
- the batch remained applied in the recorded evidence.

The batch covered root AR/EN and the six reviewed faculty/subsite home groups: Business, Petroleum, Artificial Intelligence, Pharmacy, Dentistry, and Medicine.

### Latest documented route-mapping checkpoint from 2026-07-30

- `11,917` total generated URL/evidence rows;
- `101` resolver-ready rows;
- `11,816` blocked/backlog rows;
- `8,637` mapping backlog rows;
- `3,166` imported-but-intentionally-private URL variants;
- `13` unsupported gallery-list routes blocked by missing target behavior;
- `0` unknown generated URL rows in that report;
- `14` active stored exact redirects.

These values are the reason the redirect migration must not be described as "all old URLs are finished." The subsystem is implemented, but the final old URL universe still needs review and production reconciliation.

## 15. Tests And Validation Implemented

### Runtime continuity tests

`tests/Feature/PX05/RedirectContinuityTest.php` covers:

- exact `301` redirects;
- unsafe external destination rejection;
- unsafe scheme rejection;
- pattern capture replacement;
- pass-through for unmatched paths;
- unresolved request logging;
- normalized handler/subsite/language metadata;
- root news query redirects;
- query resolver precedence over generic patterns;
- no generic static snippet guessing;
- public Business `/admin/index.php` behavior;
- Laravel `/admin` isolation;
- reviewed category mappings;
- reviewed functional signatures;
- repeated-request hit counts;
- `/admin`, `/livewire`, and `/filament` middleware exclusions;
- loop termination;
- retired-language fallback;
- private members exact/pattern/file blocking;
- unknown language ID rejection.

`tests/Feature/PX05/ReferenceHtmlAliasContinuityTest.php` covers:

- all `175` aliases;
- alias source/destination safety;
- browser-locale `302` behavior;
- Arabic default;
- explicit locale `301` behavior;
- query preservation;
- HEAD behavior;
- alias precedence over database redirects;
- unknown aliases;
- unsafe methods.

`tests/Feature/PX05/FileContinuityTest.php` covers:

- mapped file resolution;
- unmapped file behavior;
- nonexistent file behavior;
- runtime `301` file redirect.

### Resolver and normalizer tests

- `tests/Unit/LegacyUrlNormalizerTest.php`;
- `tests/Unit/LegacyQueryRedirectResolverTest.php`;
- `tests/Unit/LegacyGeneratedUrlInventoryServiceTest.php`;
- `tests/Unit/LegacyUrlContinuityInventoryServiceTest.php`;
- `tests/Unit/LegacyUrlContinuityTriageServiceTest.php`;
- `tests/Unit/LegacyRedirectEvidenceServiceTest.php`;
- `tests/Unit/ContinuityServiceTest.php`.

These cover parameter aliases, order independence, language mapping, public admin recognition, file identification, exact-over-pattern precedence, loop and conflict validation, inventory row generation, triage categories, and evidence splitting.

### Command and workflow tests

- `tests/Feature/PX07/RedirectValidationTest.php`;
- `tests/Feature/PX07/UrlInventoryExportTest.php`;
- `tests/Feature/PX07/FileInventoryExportTest.php`;
- `tests/Feature/PX07/UnresolvedReportTest.php`;
- `tests/Feature/LegacyImportUrlContinuityInventoryCommandTest.php`;
- `tests/Feature/LegacyImportUrlContinuityTriageCommandTest.php`;
- `tests/Feature/LegacyImportRedirectEvidenceCommandTest.php`;
- `tests/Feature/LegacyRedirectDecisionWorkflowTest.php`;
- `tests/Feature/LegacyFileContinuityProbeCommandTest.php`;
- `tests/Feature/PX08/LaunchValidationTest.php`.

The decision workflow test verifies dry-run default, approval-token enforcement, normalized query signature persistence, migration audit logs, idempotency, conflict rejection, rollback-token enforcement, rollback, and cache-safe batch handling.

### Validation commands

Implemented commands include:

```bash
php artisan continuity:validate-redirects
php artisan continuity:validate-seo
php artisan continuity:report-unresolved
php artisan continuity:export-url-inventory --format=json
php artisan continuity:export-file-inventory --format=csv
php artisan continuity:reconciliation-report --format=json
php artisan launch:validate
```

`continuity:validate-redirects` checks duplicates, conflicting patterns, unsafe destinations, potential loops, and invalid reference aliases. Its `--fix` mode deactivates identified invalid/conflicting rules rather than silently rewriting them.

The recorded frontend tracker states that `continuity:validate-redirects` passed and `launch:validate` passed with warnings for unmapped file continuity and local database cache-tag support. Those warnings are not production sign-off.

## 16. Operational Command Runbook

### Configure the old database locally or on staging

Do not put credentials or real production paths in Git.

```dotenv
OLD_DB_CONNECTION=legacy_mysql
OLD_DB_HOST=127.0.0.1
OLD_DB_PORT=3306
OLD_DB_DATABASE=spu_legacy
OLD_DB_USERNAME=...
OLD_DB_PASSWORD=...
OLD_PUBLIC_ROOT=/verified/legacy/public/root
OLD_DB_ALLOW_BROAD_IMPORT=false
```

### Generate file evidence

```bash
php artisan legacy-import:file-continuity-probe /absolute/legacy/public/root --target-root=/absolute/laravel/public
php artisan config:clear
php artisan legacy-import:file-inventory --checksum
php artisan legacy-import:file-inventory --checksum --write
php artisan continuity:export-file-inventory --format=csv --disk=local --dir=legacy-import-exports/file-continuity-inventory
```

The probe is read-only. The legacy file inventory writes only when `--write` is supplied.

### Generate URL evidence

```bash
php artisan legacy-import:url-continuity-inventory --without-files --json
php artisan legacy-import:generated-url-inventory
php artisan legacy-import:url-continuity-triage <generated-inventory.csv> --json
php artisan legacy-import:redirect-evidence <generated-inventory.csv> <triage-rows.csv> --json
```

For observed/unresolved URL evidence, run the inventory with files or use the URL-only inventory as appropriate. Keep all generated packets on private storage.

### Review and apply a redirect packet

The review copy must be kept outside `public/` and outside version control.

```bash
php artisan legacy-import:redirect-decisions <reviewed-preview.csv> --batch=redirect-cutover-YYYYMMDD
php artisan legacy-import:redirect-decisions <reviewed-preview.csv> --batch=redirect-cutover-YYYYMMDD --write --approve=legacy-redirect-apply
```

### Roll back one applied batch

```bash
php artisan legacy-import:redirect-rollback redirect-cutover-YYYYMMDD
php artisan legacy-import:redirect-rollback redirect-cutover-YYYYMMDD --write --approve=legacy-redirect-rollback
```

### Validate after apply

```bash
php artisan continuity:validate-redirects
php artisan continuity:reconciliation-report --format=json
php artisan continuity:report-unresolved --since="1 hour ago" --format=json
php artisan launch:validate --environment=production
```

### Legacy news slug cleanup

The separate news slug cleanup lane can change an article slug and create/update locale-specific exact `301` rules in one transaction:

```bash
php artisan legacy-import:news-slug-plan --all --json --output=storage/app/legacy-news-slug-plan.json
php artisan legacy-import:news-slug-apply --all --approve=news-slug-cleanup --json
```

It flushes news, public page, SEO, sitemap, and continuity cache tags. It is separate from the evidence-packet redirect decision workflow.

## 17. cPanel Cutover Procedure

### Before cutover

- Back up the old database.
- Back up the old public filesystem and retain a checksum manifest.
- Back up the new database.
- Record the old document root and final Laravel `public/` root privately.
- Confirm PHP-FPM/Apache user and group permissions.
- Confirm whether cPanel allows aliases or symlinks.
- Confirm which approved static directories remain public.
- Run the read-only file probe against the real cPanel root.
- Regenerate URL inventory and redirect evidence from final data.
- Review high-traffic, high-backlink, research, faculty, profile, PDF, admissions, and policy URLs.
- Apply only approved rules to staging first.
- Test a production-layout rehearsal.

### Deploy

The documented production command sequence is:

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

Run these only after backups, environment configuration, file-path verification, and redirect approval are complete.

### Verify externally

Check from outside cPanel:

- `/ar`;
- `/en`;
- representative old query-router URLs;
- representative old `.html` aliases;
- representative old subsite paths;
- representative old PDFs, DOCs, images, and encoded filenames;
- `/sitemap.xml`;
- `/robots.txt`;
- old Business `/admin/index.php` URLs;
- Laravel `/admin/login` and protected admin routes;
- 404 response and unresolved logging for unknown paths.

### Required static-file outcomes

- Existing approved file: `200` directly from Apache/LiteSpeed.
- Moved verified file: one `301` to canonical file URL.
- Missing/unmapped file: real `404` and unresolved file log.
- Executable/sensitive file: `404`, never executed.
- Directory request: no directory listing.
- PDF range request: `206` where supported.
- URL-encoded spaces and non-ASCII filenames: resolve correctly.

## 18. Monitoring And Rollback

### First 24 hours

Monitor at least every 15 minutes initially:

- unresolved URL count and unique URL count;
- page versus file unresolved volume;
- redirect hit counts;
- 404 and 5xx spikes;
- old query-router samples;
- old file/PDF samples;
- Arabic and English canonical/hreflang behavior;
- queue, cache, storage, and PHP errors.

### First 14/90 days

- Triage unresolved URLs daily for the first 14 days.
- Triage unresolved URLs weekly through day 90.
- Prioritize high-hit and externally referred URLs.
- Add a regression test for every new redirect family.
- Keep old physical files in place during stabilization.
- Do not delete old database/filesystem backups.

### Recorded rollback thresholds

| Signal | Threshold/action |
|---|---|
| Unresolved URL spike | More than `50` unique URLs/hour for two hours: investigate and consider rollback if user-facing. |
| Homepage failure | Any locale returns `500`: immediate rollback. |
| Admin failure | `/admin` returns `500` or auth loops: immediate rollback. |
| SEO regression | Canonical/hreflang errors on more than `10%`: rollback within one hour. |
| Redirect continuity | More than `5%` of sampled legacy URLs fail: investigate and roll back if not fixed within 30 minutes. |
| File continuity | More than `10%` of sampled legacy files fail: investigate and roll back if not fixed within one hour. |
| Database errors | Persistent connection/query failures: immediate rollback. |

### Rollback actions

- Restore DNS/document-root routing to the old application if required.
- Restore the pre-cutover database snapshot if the new database was changed.
- Preserve new unresolved-request logs for analysis.
- Keep old redirect rules and old files intact.
- Clear new cache and allow the old application to rebuild its cache.
- Confirm the old homepage, old URLs, old files, and old admin behavior.

## 19. Remaining Work Before Calling The Migration Complete

### Must be completed

- Confirm the real cPanel filesystem/document-root layout.
- Run the checksum-enabled file probe against the actual legacy root.
- Reconcile `legacy_file_inventory` against production evidence.
- Decide the final Apache/LiteSpeed alias or symlink layout.
- Regenerate the complete old URL universe from final legacy DB, old source, Search Console, analytics/access logs, and file manifests.
- Review and approve the final redirect decision register.
- Complete high-traffic resolver families that are still blocked.
- Complete or intentionally retire archive targets for valuable unresolved content.
- Publish only reviewed targets that are intended to receive redirects.
- Wire or explicitly retire the inactive `LegacyPageQueryResolver` candidate.
- Decide the public council/profile resolver and publication workflow.
- Reconcile ambiguous `jx_councils`/`jx_councils1` identities.
- Resolve the direct research detail redirect status if it is meant to be permanent.
- Add/verify hidden-source and disabled-target regression tests for every final family.
- Run Linux/cPanel file behavior tests, not only Windows-local tests.
- Run final staging smoke matrix and production-like `launch:validate`.
- Produce a dated launch evidence package outside the public web root.

### Must not be done

- Do not redirect every unknown URL to the homepage.
- Do not point old CMS engine URLs to the new admin login.
- Do not expose old PHP controllers, backups, SQL, `.env`, or private submission folders.
- Do not make disabled/draft records public only to increase redirect counts.
- Do not use local missing-file results as proof that production files are missing.
- Do not use local Windows behavior as proof of Linux case-sensitive path behavior.
- Do not apply evidence CSVs without editorial approval.
- Do not overwrite an existing conflicting redirect silently.
- Do not use stale historical counts as launch proof.

## 20. Complete File Inventory For Redirect Work

### Runtime and configuration

- `bootstrap/app.php`
- `routes/web.php`
- `config/continuity.php`
- `config/reference_html_aliases.php`
- `config/old_database.php`
- `public/.htaccess`
- `app/Http/Middleware/RedirectContinuityMiddleware.php`
- `app/Http/Controllers/Public/BrowserLocaleRedirectController.php`
- `app/Services/Shared/ContinuityService.php`
- `app/Services/Legacy/LegacyUrlNormalizer.php`
- `app/Services/Legacy/LegacyQueryRedirectResolver.php`
- `app/Services/Legacy/LegacyQueryResolverRegistry.php`
- `app/Services/Legacy/LegacyRedirectEvidenceService.php`
- `app/Services/Legacy/LegacyRedirectDecisionService.php`
- `app/Services/Legacy/LegacyUrlContinuityInventoryService.php`
- `app/Services/Legacy/LegacyUrlContinuityTriageService.php`
- `app/Services/Legacy/LegacyGeneratedUrlInventoryService.php`
- `app/Services/Legacy/LegacyFileContinuityProbeService.php`
- `app/Services/Legacy/LegacyNewsSlugCleanupPlannerService.php`
- `app/Services/Legacy/LegacyNewsSlugCleanupApplyService.php`
- `app/Services/Legacy/QueryResolvers/LegacySubsiteHomeQueryResolver.php`
- `app/Services/Legacy/QueryResolvers/LegacyFunctionalRouteQueryResolver.php`
- `app/Services/Legacy/QueryResolvers/LegacyCategoryRouteQueryResolver.php`
- `app/Services/Legacy/QueryResolvers/LegacyNewsQueryResolver.php`
- `app/Services/Legacy/QueryResolvers/LegacyResearchQueryResolver.php`
- `app/Services/Legacy/QueryResolvers/LegacyUnsupportedLanguageQueryResolver.php`
- `app/Services/Legacy/QueryResolvers/LegacyPageQueryResolver.php`

### Direct compatibility controllers

- `app/Http/Controllers/Public/AboutController.php`
- `app/Http/Controllers/Public/AdmissionsController.php`
- `app/Http/Controllers/Public/FacultyController.php`
- `app/Http/Controllers/Public/NewsController.php`
- `app/Http/Controllers/Public/ResearchController.php`

### Models, contracts, and DTOs

- `app/Models/Legacy/LegacyExactRedirect.php`
- `app/Models/Legacy/LegacyPatternRule.php`
- `app/Models/Legacy/LegacyFileInventory.php`
- `app/Models/Page/UnresolvedLegacyRequest.php`
- `app/Contracts/Shared/ContinuityServiceInterface.php`
- `app/Contracts/Legacy/LegacyUrlNormalizerInterface.php`
- `app/Contracts/Legacy/LegacyQueryRedirectResolverInterface.php`
- `app/Contracts/Legacy/LegacyQueryResolverRegistryInterface.php`
- `app/Contracts/Legacy/LegacyRedirectEvidenceServiceInterface.php`
- `app/Contracts/Legacy/LegacyRedirectDecisionServiceInterface.php`
- `app/Contracts/Legacy/LegacyUrlContinuityInventoryServiceInterface.php`
- `app/Contracts/Legacy/LegacyUrlContinuityTriageServiceInterface.php`
- `app/Contracts/Legacy/LegacyGeneratedUrlInventoryServiceInterface.php`
- `app/Contracts/Legacy/LegacyFileContinuityProbeServiceInterface.php`
- `app/DTOs/Legacy/RedirectResultDTO.php`
- `app/DTOs/Legacy/RedirectRuleDTO.php`
- `app/DTOs/Legacy/NormalizedLegacyUrlDTO.php`
- `app/DTOs/Legacy/LegacyQueryResolutionDTO.php`
- `app/DTOs/Legacy/UnresolvedRequestDTO.php`
- `app/DTOs/Legacy/LegacyRedirectEvidenceResultDTO.php`
- `app/DTOs/Legacy/LegacyRedirectDecisionResultDTO.php`
- `app/DTOs/Legacy/LegacyUrlContinuityInventoryResultDTO.php`
- `app/DTOs/Legacy/LegacyUrlContinuityTriageResultDTO.php`
- `app/DTOs/Legacy/LegacyGeneratedUrlInventoryResultDTO.php`
- `app/DTOs/Legacy/LegacyFileContinuityProbeResultDTO.php`

### Database migrations

- `database/migrations/2026_04_19_000001_create_legacy_exact_redirects_table.php`
- `database/migrations/2026_04_19_000002_create_legacy_pattern_rules_table.php`
- `database/migrations/2026_04_19_000003_create_unresolved_legacy_requests_table.php`
- `database/migrations/2026_04_19_000004_create_legacy_file_inventory_table.php`
- `database/migrations/2026_07_04_000004_add_normalized_legacy_request_metadata.php`
- `database/migrations/2026_07_05_000001_harden_legacy_file_inventory_metadata.php`
- `database/migrations/2026_07_08_000001_add_checksum_status_to_legacy_file_inventory.php`
- `database/migrations/2026_07_29_000001_add_decision_metadata_to_legacy_exact_redirects.php`

### Artisan commands

- `app/Console/Commands/ValidateRedirectsCommand.php`
- `app/Console/Commands/ReportUnresolvedCommand.php`
- `app/Console/Commands/ExportUrlInventoryCommand.php`
- `app/Console/Commands/ExportFileInventoryCommand.php`
- `app/Console/Commands/ReconciliationReportCommand.php`
- `app/Console/Commands/ValidateSeoCommand.php`
- `app/Console/Commands/LaunchValidateCommand.php`
- `app/Console/Commands/LegacyImportFileInventoryCommand.php`
- `app/Console/Commands/LegacyImportFileContinuityProbeCommand.php`
- `app/Console/Commands/LegacyImportGeneratedUrlInventoryCommand.php`
- `app/Console/Commands/LegacyImportUrlContinuityInventoryCommand.php`
- `app/Console/Commands/LegacyImportUrlContinuityTriageCommand.php`
- `app/Console/Commands/LegacyImportRedirectEvidenceCommand.php`
- `app/Console/Commands/LegacyImportRedirectDecisionsCommand.php`
- `app/Console/Commands/LegacyImportRedirectRollbackCommand.php`
- `app/Console/Commands/LegacyImportNewsSlugPlanCommand.php`
- `app/Console/Commands/LegacyImportNewsSlugApplyCommand.php`

### Tests

- `tests/Feature/PX05/RedirectContinuityTest.php`
- `tests/Feature/PX05/ReferenceHtmlAliasContinuityTest.php`
- `tests/Feature/PX05/FileContinuityTest.php`
- `tests/Feature/PX07/RedirectValidationTest.php`
- `tests/Feature/PX07/UrlInventoryExportTest.php`
- `tests/Feature/PX07/FileInventoryExportTest.php`
- `tests/Feature/PX07/UnresolvedReportTest.php`
- `tests/Feature/PX08/LaunchValidationTest.php`
- `tests/Feature/LegacyRedirectDecisionWorkflowTest.php`
- `tests/Feature/LegacyImportRedirectEvidenceCommandTest.php`
- `tests/Feature/LegacyImportUrlContinuityInventoryCommandTest.php`
- `tests/Feature/LegacyImportUrlContinuityTriageCommandTest.php`
- `tests/Feature/LegacyFileContinuityProbeCommandTest.php`
- `tests/Unit/ContinuityServiceTest.php`
- `tests/Unit/LegacyUrlNormalizerTest.php`
- `tests/Unit/LegacyQueryRedirectResolverTest.php`
- `tests/Unit/LegacyUrlContinuityInventoryServiceTest.php`
- `tests/Unit/LegacyUrlContinuityTriageServiceTest.php`
- `tests/Unit/LegacyGeneratedUrlInventoryServiceTest.php`
- `tests/Unit/LegacyRedirectEvidenceServiceTest.php`

### Documentation

- `Docs/FRONTEND_IMPORT_TRACKER.md`
- `Docs/FRONTEND_ROUTE_PARITY_MATRIX.md`
- `Docs/LEGACY_IMPORT_RUNBOOK.md`
- `Docs/LEGACY_MEDIA_DEPLOYMENT.md`
- `Docs/launch-readiness-checklist.md`
- `Docs/rollback-preparation.md`
- `Docs/WEBSITE_MIGRATION_GUIDE/LEGACY_TO_NEW_MAPPING.md`
- `Docs/WEBSITE_MIGRATION_GUIDE/SEARCH_CONTINUITY_BLUEPRINT.md`
- `Docs/WEBSITE_MIGRATION_GUIDE/SEO_PRESERVATION_GUIDE.md`
- `Docs/WEBSITE_MIGRATION_GUIDE/CPANEL_FILE_CONTINUITY_RUNBOOK.md`
- `Docs/WEBSITE_MIGRATION_GUIDE/ENDGAME_MIGRATION_AND_CONTINUITY_MASTER_PLAN.md`
- `Docs/WEBSITE_MIGRATION_GUIDE/AUDIT_DRIVEN_PRODUCTION_ENDGAME_PLAN.md`
- `Docs/WEBSITE_MIGRATION_GUIDE/ULTRA_HARDENED_PHASE_STATUS.md`
- `Docs/WEBSITE_MIGRATION_GUIDE/PROFESSOR_LEGACY_CODE_AUDIT_PROMPT.md`
- `Docs/OLD_TO_NEW_REDIRECT_MIGRATION_COMPLETE.md`

## 21. Source Documents And Evidence Locations

The original detailed plans and private evidence are retained in:

- `storage/app/private/legacy-import-exports/url-continuity/`;
- `storage/app/private/legacy-import-exports/url-continuity-triage/`;
- `storage/app/private/legacy-import-exports/generated-url-inventory/`;
- `storage/app/private/legacy-import-exports/redirect-evidence/`;
- `storage/app/private/legacy-import-exports/file-continuity-probes/`;
- `storage/app/private/legacy-import-exports/file-continuity-inventory/`;
- `storage/app/private/legacy-import-exports/category-review-packets/`;
- `storage/app/private/legacy-import-exports/public-staff-review-packets/`;
- `storage/app/private/legacy-import-exports/members-review-packets/`;
- `storage/app/private/legacy-import-backups/`.

These locations must remain private. Do not move review packets, source content, database dumps, absolute path manifests, credentials, or approval copies under `public/` or commit them to Git.

## Final Handoff Statement

The old-to-new redirect work is not a single `.htaccess` rewrite. It is a layered continuity system that now understands the old SPU database, query-router URL semantics, subsites, language IDs, physical reference files, public/private boundaries, imported target state, file availability, evidence review, and post-launch discovery.

The runtime foundation and safety controls are implemented. The remaining work is final production evidence and approval: verify the real cPanel file layout, regenerate the final URL universe, finish high-value unresolved families, approve only safe redirect rows, validate the deployed target behavior, and keep the old site/filesystem rollback path available until continuity is proven in production.
