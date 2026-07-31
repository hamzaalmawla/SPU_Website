# Frontend Import Tracker

This tracker records frontend additions from `C:\Users\hamza\Spu-Website\Spu-Website` that must be implemented in the Laravel backend without losing scope.

The project is now in full-site completion scope. A route is not `Done` unless its specialized behavior, backend data flow, CMS workflow, assets, AR/EN presentation, accessibility, and tests are complete where applicable. Route existence or static markup alone does not establish parity.

## Full-Site Parity Backlog

The 175-page reference inventory remains the route baseline. Counts are provisional until every path has a dedicated implementation, approved redirect, or documented retirement decision.

| Status | Priority | Area | Work item |
|---|---|---|---|
| Done | P0 | Governance | `AGENTS.MD` now defines full-site completion, reference parity, and production readiness as mandatory scope. |
| Done | P0 | Dynamic forms | Guard schema-dependent markup so closing a successful form cannot dereference a null schema. |
| Done | P0 | Homepage News | Render each homepage news card as a keyboard-accessible link to its article DTO URL. |
| Done | P0 | Locale switching | Reuse contextual language-switch DTOs in the footer so switching locale preserves the current page. |
| Done | P0 | Browser locale | Bare-domain requests now negotiate AR/EN from the browser `Accept-Language` preference while explicit locale URLs remain authoritative. |
| Done | P0 | Shared runtime | Removed generic-page slug/template metadata, ported the missing admissions hero, corrected the dormant footer-logo seed, and restored the reference header stacking level. |
| Done | P0 | Route inventory | Reconciled all 175 effective reference pages in `Docs/FRONTEND_ROUTE_PARITY_MATRIX.md`: 168 complete, 7 approved redirects, 0 partial, and 0 missing. |
| Done | P0 | Route compatibility | Added browser-locale negotiation for approved unprefixed deep links and canonical compatibility for reference profile, article, facilities hub, and shared project-detail query URLs. |
| Done | P0 | Legacy HTML continuity | Added exact locale-aware redirects for all 175 approved physical reference files, including query preservation, nested `index.html` paths, and renamed Campus Life/E-Services aliases. |
| Done | P0 | Announcements | Added dedicated route precedence, announcement-only data queries, category filtering, pagination, CMS editor, protected preview, publication workflow, and landing-page links. |
| Done | P0 | Events | Added dedicated calendar, listing, registration, and past-detail routes backed by one bilingual CMS catalog, with filtering, capacity and duplicate enforcement, confirmation mail, preview/publish workflows, and scheduled CMS publication. |
| Done | P0 | News navigation | Announcements, Events, and Gallery now use dedicated routes before the article wildcard. |
| Done | P1 | About | Completed all approved About routes with bilingual curated editors, entity draft/preview/publish/schedule workflows, publication-aware profiles/directories, Partnerships controls/proposal flow, verified content cleanup, assets, SEO/sitemap, accessibility, and focused tests. |
| Done | P1 | E-Services | Added dedicated bilingual Library, Staff Email, and IT Support pages with independent CMS workflows, safe verified destinations, contact integration, navigation, SEO/sitemap, continuity, and tests. |
| Done | P1 | News Gallery | Added bilingual Media Library curation, filters, featured selection, pagination, keyboard-accessible image viewing, preview/publish workflows, media readiness checks, and cache invalidation. |
| Done | P1 | Facilities | Implemented all seven faculty research pages with central publication data, bilingual CMS workflows, faculty scope, server pagination, canonical links, SEO, sitemap, assets, and tests. Gallery and project-pagination parity remain tracked separately. |
| Done | P1 | Faculty Directories | Completed 33 department, lab, project, alumni, and valedictorian routes with family-wide AR/EN evidence, server pagination, related lab details, managed media, and query-preserving navigation. |
| Done | P1 | Faculty Overviews | Completed all seven overview routes with eligible central research, safe canonical dean/profile links, bilingual CMS/preview behavior, and family-wide tests. |
| Done | P1 | Campus Life Jobs | Added one bilingual CMS job catalog covering board, application, and eight details with filters, preview/publication, status/expiry enforcement, trusted application context, private CV storage, JobPosting metadata, and tests. |
| Done | P1 | Faculty Study Plans | Completed all 14 study-plan/course routes with accessible dialogs and graph controls, validated selectors, safe resources/profiles, localized query preservation, and family-wide tests. |
| Done | P1 | Admissions | Completed all nine remaining Admissions routes, removed fabricated/inert public data, added verified-media readiness and a validated application flow, and expanded AR/EN accessibility tests. |
| Done | P1 | Homepage UX | Completed RTL-aware keyboard sliders, reduced-motion/autoplay/focus behavior, counters, dynamic reveals, asset validation, and focused JS/Blade tests. |
| Done | P1 | Research | Added functional project, publication, researcher, and expert-finder controls plus verified scholarly metadata, structured data, and publication downloads. |
| Done | P1 | Research Centers | Added one bilingual center/laboratory catalog with validated AR/EN identity, protected listing/detail preview, publish/schedule/unpublish workflow, explicit relationships, real affiliated researchers, SEO/sitemap coverage, and tests. |
| Done | P1 | Research Projects & Themes | Added aggregate bilingual catalogs covering 19 listing/detail routes with protected preview, validated publication workflows, relationships, SEO/sitemap, and tests. |
| Done | P1 | Research Completion | Completed repository behavior, eight scholarly publication details, researcher preview/taxonomy, conferences/registration context, and verified policy documents across 13 routes. |
| Done | P1 | Campus Life | Completed job-board filtering, selected-job application context, sharing, pagination, related jobs, and safe landing portal guidance. |
| Done | P1 | Virtual Tour | Added CMS-managed scenes with accessible switching, pan/zoom, hotspots, autoplay, thumbnails, fullscreen fallback, RTL, and reduced motion. |
| Done | P2 | Shared UX | Normalized RTL sliders, dynamic reveals, counters, keyboard behavior, focus/autoplay handling, and reduced motion. |
| Done | P1 | Final Route Batch | Completed Suggestions/Complaints secure workflow, News Articles shell CMS, and Pharmacy-only training editor; no reference routes remain partial. |
| In Progress | P0 | Legacy continuity | Current evidence covers 11,917 generated URLs: 101 resolver-ready, 8,637 requiring mappings, 3,166 blocked on private targets, and 13 gallery routes blocked for a missing approved target module. |
| Pending | P2 | Production content | Remove fake/sample contact data, broken settings assets, placeholders, and developer-facing public metadata. |
| Pending | P2 | Production gate | Verify clean dependency installation, full tests, frontend build, queues, scheduler, cache, storage, security headers, monitoring, SEO, and launch checks. |

## Reported Content Pages

| Status | Area | Frontend route | Backend target |
|---|---|---|---|
| Done | About | `/about/quality-policy/` | `about.quality-policy` |
| Done | About | `/about/ethical-charter/` | `about.ethical-charter` |
| Done | About | `/about/organizational-structure/` | `about.organizational-structure` |
| Done | Admissions | `/admissions/filling-vacancies/` | `admissions.filling-vacancies` |
| Done | Admissions | `/admissions/graduation-exams/` | `admissions.graduation-exams` |
| Done | Campus Life | `/campus-life/damascus-research-pub/` | `campus_life.damascus-research-pub` |
| Done | Campus Life | `/campus-life/rules-regulations/` | `campus_life.rules-regulations` |
| Done | Campus Life | `/campus-life/general-rules/` | `campus_life.general-rules` |
| Done | Campus Life | `/campus-life/exam-instructions/` | `campus_life.exam-instructions` |
| Done | Campus Life | `/campus-life/exam-penalties/` | `campus_life.exam-penalties` |

## Reported Redirects

| Status | From | To |
|---|---|---|
| Done | `/about/university-council/` | `/about/leadership/` |
| Done | `/admissions/study-system/` | `/admissions/documents/` |
| Done | `/admissions/academic-warnings/` | `/admissions/documents/` |

## Extra Frontend Pages Found

| Status | Area | Frontend route | Notes |
|---|---|---|---|
| Done | About | `/about/accreditation/` | Backend route, fallback payload, CMS target, admin labels, and copied hero image added. |
| Done | About | `/about/why-spu/` | Backend route, fallback payload, CMS target, and admin labels added. |
| Done | Campus Life | `/campus-life/career-development/jobs/` | Backend-rendered Job Board page added with job detail routes and application links. |
| Done | Campus Life | `/campus-life/career-development/jobs/apply/` | Backend-rendered frontend-compatible page using `job-application` dynamic form. |
| Done | Research | `/research/conferences/register?event=conf-001` | Backend-rendered frontend-compatible page using `conference-registration` dynamic form. |
| Done | Research | `/research/conferences/register?event=conf-002` | Backend-rendered frontend-compatible page using `symposium-registration` dynamic form. |
| Done | E-Services | `/e-services/suggestions-complaints/` | Backend-rendered form page added; submissions store through contact message service. |
| Done | News | `/news/events-list/register/` | Dedicated route, shared bilingual event catalog, server-validated event context, capacity and duplicate enforcement, queued confirmation mail, and CMS workflow implemented. |

## URL Compatibility

| Status | Frontend URL | Backend URL | Decision |
|---|---|---|---|
| Done | `/about/partnership/` | `/about/partnerships/` | Redirect alias added. |

## Header / Navigation

| Status | Item | Notes |
|---|---|---|
| Done | Frontend header menu tree | `NavigationSeeder` now matches frontend top-level order: About, Facilities, Admissions, Research, Campus Life, E-Services, News, Contact. |
| Done | About dropdown additions | Added Quality Policy, Ethical Charter, Organizational Structure, Accreditation, and Why SPU to seeded AR/EN header menu. |
| Done | Admissions dropdown additions | Added Filling Vacancies and Graduation & National Exams, and aligned labels/order with frontend. |
| Done | Research grouped dropdown | Added frontend-style grouped Research dropdown with Publications, Researchers, Research Themes, Research Projects, Research Centers, featured links, and flat utility links. |
| Done | Campus Life dropdown additions | Added Job Board plus imported Campus Life pages including Damascus Research Center, Rules & Regulations, General Rules, Exam Instructions, and Exam Penalties. |
| Done | E-Services dropdown | Aligned labels/links with frontend E-Services dropdown, including external Student Portal and Staff Email. |
| Done | News dropdown | Added News, Announcements, Events Calendar, and Media Gallery dropdown items. |
| Done | Header Blade rendering | Desktop and mobile header now render nested menu groups with `site-nav-dropdown-group*` and `site-nav-mobile-group*` classes. |
| Done | Header CSS | Added missing grouped/featured dropdown styles and scroll handling from frontend navigation CSS. |
| Done | Local DB navigation | Ran `php artisan db:seed --class=NavigationSeeder` so the workspace database uses the updated header immediately. |
| Done | Missing page follow-up | Added real backend targets/routes for Job Board, Accreditation, Why SPU, and Suggestions & Complaints after header-only links were found. |
| Done | Missing image follow-up | Copied `/images/about/hero-img.jpg` from frontend source into Laravel `public/images/about/hero-img.jpg`. |

## Dynamic Forms

Frontend files:
- `src/alpine/dynamic-form-store.js`
- `src/data/dynamic-forms.js`
- `src/fragments/components/dynamic-form.html`

| Status | Form ID | Used by | Backend requirement |
|---|---|---|---|
| Done | `conference-registration` | Research conference registration | Stores submissions, validates server-side, supports admin review. |
| Done | `symposium-registration` | Event/symposium registration | Stores submissions, validates server-side, supports admin review. |
| Done | `activity-registration` | Activity registration | Stores submissions, validates server-side, supports admin review. |
| Done | `job-application` | Job application page | Stores multi-step data, CV upload, secure local storage, supports admin review. |

## Backend Implementation Checklist

| Status | Item |
|---|---|
| Done | Public routes and redirects added. |
| Done | CMS target registry updated. |
| Done | Admin labels added for AR/EN. |
| Done | Service fallback payloads imported. |
| Done | Filament edit forms support imported pages. |
| Done | Public Blade views render imported page shapes. |
| Done | Navigation seeder matches frontend additions. |
| Done | SEO, sitemap, preview, publish, and cache behavior verified. Route registration, Blade compilation, existing publish/preview workflow tests, launch validation, redirect validation, and SEO metadata validation pass. |
| Done | Dynamic form backend implemented. |
| Done | Frontend dynamic form store/view registered in Vite and submits to Laravel with CSRF, validation errors, uploads, and success state. |
| Done | Reusable dynamic-form Blade component added for backend-rendered frontend-compatible form pages. |
| Done | Tests cover routes, redirects, CMS targets, public rendering, CMS editor saves for imported content pages, dynamic form submissions/uploads, and rendered form-page shells. |

## Latest Verification

| Status | Check |
|---|---|
| Passed | `php -l app\Services\Research\ResearchPageService.php` |
| Passed | `php -l app\Services\Page\CampusLifePageService.php` |
| Passed | `php -l app\Http\Controllers\Public\ResearchController.php` |
| Passed | `php -l app\Http\Controllers\Public\CampusLifeController.php` |
| Passed | `php -l routes\web.php` |
| Passed | `php artisan route:list --path=campus-life/career-development/jobs/apply` |
| Passed | `php artisan route:list --path=research/conferences/register` |
| Passed | `php artisan route:list --path=forms` |
| Passed | `php artisan view:cache` |
| Passed | `npm run build` |
| Passed | `php artisan test "tests\Feature\DynamicFormSubmissionTest.php"` |
| Passed | `php artisan test "tests\Feature\DynamicFormPageRenderingTest.php"` |
| Passed | `php artisan test "tests\Feature\CampusLifeWorkflowTest.php"` with extended timeout; 21 tests / 149 assertions. |
| Passed | `php artisan test "tests\Feature\HeaderNavigationRenderingTest.php"` |
| Passed | `php artisan test "tests\Feature\HeaderNavigationRenderingTest.php" "tests\Feature\DynamicFormPageRenderingTest.php"` |
| Passed | `php artisan db:seed --class=NavigationSeeder` |
| Passed | `php artisan migrate` created `dynamic_form_submissions`. |
| Passed | `php artisan migrate:status --path="database\migrations\2026_07_09_000001_create_dynamic_form_submissions_table.php"` shows migration as ran. |
| Passed | `php artisan test "tests\Feature\MissingFrontendPagesTest.php"` |
| Passed | `php artisan test "tests\Feature\MissingFrontendPagesTest.php" "tests\Feature\HeaderNavigationRenderingTest.php" "tests\Feature\DynamicFormPageRenderingTest.php" "tests\Feature\DynamicFormSubmissionTest.php"` |
| Passed | `php artisan continuity:validate-redirects` |
| Passed | `php artisan continuity:validate-seo` after reseeding `LandingPageSeeder`; 8 published pages / 2 locales / 0 issues. |
| Passed | `php artisan launch:validate`; warnings remain for unmapped file-continuity inventory and local cache tag support with `CACHE_STORE=database`. |

## Legacy Continuity Checkpoint - 2026-07-30

- Reviewed all uncommitted migration changes and fixed mandatory FAQ content-hash enforcement, source-side duplicate/orphan/archive checks, service-aware news triage, scheme-less external URL filtering, and collision-safe approval packet paths.
- Reconciled quarantine state: `1,341` news drafts, `239` staff drafts, `3` council members, and `43` FAQs remain disabled; news/staff publication timestamps remain null; FAQ featured count is zero.
- Added exact service-and-source-ID resolution for `36` reviewed root navigation categories pointing to the homepage, About, Accreditation, Academic Warnings, Suggestions/Complaints, and six faculty landing pages.
- The exact category mapping resolves `73` generated AR/EN legacy query variants; no generic category fallback was introduced.
- Added strict functional-signature resolution for 14 Contact rows, one Suggestions/Complaints row, and one service-49 Jobs row. Alumni, honor students, FAQs, councils, sites, and generic item lists remain unresolved because their scope or target semantics are not proven.
- Latest generated inventory: `storage/app/private/legacy-import-exports/generated-url-inventory/20260730_144235_generated_url_inventory.csv`.
- Latest triage: `storage/app/private/legacy-import-exports/url-continuity-triage/20260730_144325_url_continuity_triage_rows.csv`.
- Latest redirect evidence: `storage/app/private/legacy-import-exports/redirect-evidence/20260730_144418_redirect_evidence_all.csv`.
- Final counts: `11,917` total, `101` resolver-ready, `8,637` requiring mappings, `3,166` private-target variants, and `13` gallery routes blocked for missing approved target behavior.
- Active stored exact redirects remain `14`; the new mappings are runtime query resolvers and did not alter stored redirect decisions.
- Reconciled student-profile migration on 2026-07-30: imported `4,939` alumni and `1,067` honor students as source-locale-only records, skipped `310` duplicates, disabled all `35` seeded placeholders, and attached no PII or media. Explicit publication then enabled `4,904` visible alumni and `1,066` visible honor students; `36` hidden source records remain disabled. AR and EN lists show the same records; EN uses a genuine English source name when available and otherwise displays the original Arabic name without synthesizing stored translations.
- Root services `5`, `6`, `7`, and `9` were reviewed but not mapped: cooperation/event ambiguity, invalid placeholder dates, achievement/research semantic mismatch, hidden jobs, incomplete translations, and deferred media prevent safe target assignment.
- Short targeted suites passed for approval integrity, generated inventory, continuity triage, news/staff/council source revalidation, and exact query resolution. The long full suite was intentionally not repeated during this checkpoint to avoid tool timeouts.

`npm run build` emitted a non-fatal unresolved runtime asset warning for `/images/admissions-hero-campus.webp`.

## Demo News Publication - 2026-07-31

- Added dry-run-first `legacy-import:publish-news` with explicit source IDs, a 25-record ceiling, publisher authorization, approval token, import-provenance checks, complete AR/EN content checks, canonical category and SEO checks, unresolved-attachment blocking, replay safety, CMS publication, cache invalidation, and publication logs.
- Published batch `approved-public-news-demo-20260731`: `4` news and `4` announcements, including one featured record per lane. The remaining `1,333` imported records stay disabled drafts.
- Retained `noindex,nofollow` on the local demo subset and left all unresolved legacy attachments quarantined.
- Verified AR/EN News, Articles, Announcements, and selected detail routes return `200`; the public service reports the same `8` records in both locales, and reviewed legacy item URLs resolve to numeric canonical article routes.
- Corrected one encoded quote and one obvious missing initial letter through audited CMS revisions.
- Targeted publication workflow tests pass with `27` assertions, including write-token enforcement, CMS promotion, unresolved-attachment rejection, and replay safety.

## Full News Reconciliation - 2026-07-31

- Reconciled all `3,038` root service-3/service-4 source rows: `1,706` news candidates and `1,332` announcement candidates.
- Added an explicit Arabic-source fallback packet policy for visible rows whose legacy English title is `Under Construction`; the policy never creates synthetic EN translations.
- Imported `944` additional disabled drafts in batch `approved-root-news-arabic-fallback-20260731`, bringing the represented target set to `2,285` articles: `1,097` news and `1,188` announcements.
- The importer now streams only identity fields during full-source duplicate checks, avoiding the previous 512 MB exhaustion caused by loading every HTML body.
- Public news mapping now uses a complete Arabic translation when the requested EN translation is absent/incomplete, derives a bounded excerpt from source body text when the legacy brief is empty, decodes scalar HTML entities for display, and suppresses the misleading migration-time date for legacy articles whose source publication date is unknown.
- `753` source rows still require explicit hidden/duplicate/empty/external/missing-title disposition; `9,565` attachment references remain blocked pending cPanel source-file retrieval and checksum/MIME verification.
- Added `Docs/ADMISSION_CHATBOT_INTEGRATION_PLAN.md` after auditing the professor-provided FastAPI/Qdrant/OpenAI repository. No chatbot runtime files were copied or exposed.
- Published every transferred record with complete Arabic source text in batch `all-legacy-news-text-publication-20260731`: `2,085` additional records. Final public totals are `1,090` news and `1,003` announcements in both AR and EN; `192` body-empty records remain private.
- Deferred cPanel attachments remain stored but are filtered out of public DTOs until a verified media asset exists, preventing broken links and empty attachment sections.

## Catalog Ordering And Pagination - 2026-07-31

- Standardized newest-first service ordering for news, announcements, past events, alumni, honor students, research publications, faculty research, and research projects. Upcoming events intentionally remain nearest-first.
- Legacy news chronology uses descending `legacy_source_id` because imported workflow timestamps describe migration publication, not the old article date. Native articles continue to use `published_at`.
- News Articles now excludes announcement records, which remain in the dedicated Announcements catalog.
- Added `resources/views/components/public/pagination.blade.php` as the shared compact paginator for news, announcements, gallery, faculty projects/labs, alumni, honor students, faculty research, and research catalogs.
- Desktop pagination displays first, current-adjacent, and last pages with ellipses; mobile displays localized `Page X of Y`; previous/next links use `rel`, accessible labels, visible focus treatment, logical RTL arrows, and 44px targets.
- Page links preserve normalized filters and omit `page=1` from generated paginator URLs.
- Removed the unsupported hardcoded Second Semester fallback from honor cards.

## Structured Research Publication Migration - 2026-07-31

- Replaced the obsolete/frozen research importer with a guarded service-1 importer that always creates disabled review records and rejects public enablement.
- Added structured relational coverage for publication year, DOI, explicit journal rank, legacy identity/owner provenance, extraction status, localized authors, citation, keyword arrays, and deferred legacy file references.
- Imported batch `approved-structured-research-import-20260731`: `289` publications, `549` source translations, and `241` deferred file paths.
- Coverage: authors `156`, citation `69`, publisher/journal `59`, DOI `11`, year `63`, keywords `225`, rank `0`, safely linked owners `5`, and duplicate-title review `36`.
- Rank remains null where the source has no explicit Q1-Q4 evidence. Ambiguous `jx_councils`/`jx_councils1` IDs remain unlinked rather than assigning papers to the wrong researcher.
- Public research DTO mapping now prefers structured author, citation, publisher, DOI, rank, year, and keywords. Imported records remain private pending editorial publication and cPanel media reconciliation.
- Added `Docs/LEGACY_RESEARCH_PUBLICATION_MAPPING.md` as the authoritative field and confidence policy.
