# SPU Spec-Driven Changelog

This changelog records changes made through `SPU_SPEC_DRIVEN_EXECUTION_TODO.md`.

It is intentionally scoped to the current homepage/admin foundation and does not authorize full-site modules.

---

## 2026-06-16

### A01: Source-Of-Truth Synchronization

Status: Completed

Type: Governance documentation alignment after foundation hardening

Changed files:

| File | Change |
| --- | --- |
| `Docs/launch-readiness-checklist.md` | Added explicit quick gates for admin auth/2FA/admin locale changes and included SEO rendering in the redirect/SEO continuity gate. |
| `TODO.md` | Aligned P3-ARCH-04 with the actual architecture guard suite, including Filament workflow model constraints, concrete service dependency blocking, contract bindings, homepage keys, controller model imports, and no legacy Kernel. |
| `SPU_SPEC_DRIVEN_EXECUTION_TODO.md` | Marked A01 complete with source-of-truth synchronization evidence. |
| `SPU_SPEC_DRIVEN_CHANGELOG.md` | Added this A01 changelog entry. |

Completion evidence:

| Check | Result |
| --- | --- |
| Scope alignment | C01 remains manual-browser gated; no full-site module scope was enabled. |
| Evidence alignment | Completed F01, F02, and H02 entries retain command evidence. |
| Architecture guard | `php artisan test tests/Feature/ArchitectureGuardTest.php` passed: 6 tests, 7 assertions. |
| Diff hygiene | `git diff --check` passed with no output. |

### F02: Admin Auth Bilingual Brand Hardening

Status: Completed

Type: Admin auth locale fix with bilingual branding regression coverage

Changed files:

| File | Change |
| --- | --- |
| `app/Http/Middleware/AdminLocaleMiddleware.php` | Defaults admin auth locale to `ar` instead of `config('app.locale')`, preserving the project's Arabic default on admin auth screens. |
| `tests/Feature/AdminAuthFlowTest.php` | Added coverage for Arabic RTL default login rendering, English LTR login rendering with Arabic brand fallback, and admin locale switch persistence. |
| `SPU_SPEC_DRIVEN_EXECUTION_TODO.md` | Marked F02 complete with targeted verification evidence. |
| `SPU_SPEC_DRIVEN_CHANGELOG.md` | Added this F02 changelog entry. |

Completion evidence:

| Check | Result |
| --- | --- |
| Syntax | `php -l app/Http/Middleware/AdminLocaleMiddleware.php` and `php -l tests/Feature/AdminAuthFlowTest.php` passed. |
| Admin auth flow | `php artisan test tests/Feature/AdminAuthFlowTest.php` passed: 6 tests, 38 assertions. |
| Admin middleware subset | `php artisan test tests/Feature/MiddlewarePipelineTest.php --filter=admin` passed: 5 tests, 17 assertions. |
| Two-factor auth layout | `php artisan test tests/Feature/TwoFactorChallengeTest.php` passed: 11 tests, 27 assertions. |
| Architecture guard | `php artisan test tests/Feature/ArchitectureGuardTest.php` passed: 6 tests, 7 assertions. |
| Admin boundary suite | `php artisan test tests/Feature/PX06` passed: 47 tests, 137 assertions. |
| Frontend build | `npm run build` passed: Vite build completed in 1.50s with no unexpected warnings. |
| Route boot | `php artisan route:list` passed: 67 routes listed. |
| Full regression | `php artisan test` passed: 3425 tests, 15526 assertions, 160.41s. |
| Diff hygiene | `git diff --check` passed with no output. |

### H02: SEO Validation Completeness Hardening

Status: Completed

Type: SEO validation hardening with targeted and property coverage

Changed files:

| File | Change |
| --- | --- |
| `app/Console/Commands/ValidateSeoCommand.php` | Reports `missing_canonical_url` when the stored page SEO record lacks a canonical URL, while keeping render-time canonical fallback in `SeoMetadataService`. |
| `tests/Feature/PX07/SeoValidationTest.php` | Added regression coverage for incomplete SEO records, including missing title, description, canonical URL, weak OG fields, and missing OG image. |
| `tests/Unit/SeoValidationPropertyTest.php` | Updated property expectations so complete SEO metadata includes a stored canonical URL. |
| `SPU_SPEC_DRIVEN_EXECUTION_TODO.md` | Marked H02 complete with targeted verification evidence. |
| `SPU_SPEC_DRIVEN_CHANGELOG.md` | Added this H02 changelog entry. |

Completion evidence:

| Check | Result |
| --- | --- |
| Syntax | `php -l app/Console/Commands/ValidateSeoCommand.php`, `php -l tests/Feature/PX07/SeoValidationTest.php`, and `php -l tests/Unit/SeoValidationPropertyTest.php` passed. |
| SEO rendering and validation | `php artisan test tests/Feature/PX05/SeoRenderingTest.php tests/Feature/PX07/SeoValidationTest.php` passed: 10 tests, 26 assertions. |
| SEO-focused suite | `php artisan test --filter=Seo` passed: 618 tests, 2481 assertions. |
| Architecture guard | `php artisan test tests/Feature/ArchitectureGuardTest.php` passed: 6 tests, 7 assertions. |
| Frontend build | `npm run build` passed: Vite build completed in 1.41s with no unexpected warnings. |
| Route boot | `php artisan route:list` passed: 67 routes listed. |
| Full regression | `php artisan test` passed: 3423 tests, 15446 assertions, 158.93s. |
| Diff hygiene | `git diff --check` passed with no output. |

### F01: Filament Workflow Boundary Guard

Status: Completed

Type: Architecture guard hardening with admin boundary verification

Changed files:

| File | Change |
| --- | --- |
| `tests/Feature/ArchitectureGuardTest.php` | Expanded Filament workflow model guard coverage for workflow storage models while preserving Filament Resource `$model` declarations, and added a guard preventing Filament code from importing, constructing, or resolving concrete `App\Services` classes instead of service contracts. |
| `SPU_SPEC_DRIVEN_EXECUTION_TODO.md` | Marked F01 complete with targeted verification evidence. |
| `SPU_SPEC_DRIVEN_CHANGELOG.md` | Added this F01 changelog entry. |

Completion evidence:

| Check | Result |
| --- | --- |
| Syntax | `php -l tests/Feature/ArchitectureGuardTest.php` passed. |
| Architecture guard | `php artisan test tests/Feature/ArchitectureGuardTest.php` passed: 6 tests, 7 assertions. |
| Admin boundary suite | `php artisan test tests/Feature/PX06` passed: 47 tests, 137 assertions. |
| Frontend build | `npm run build` passed: Vite build completed in 1.42s with no unexpected warnings. |
| Route boot | `php artisan route:list` passed: 67 routes listed. |
| Full regression | `php artisan test` passed: 3422 tests, 15308 assertions, 160.19s. |
| Diff hygiene | `git diff --check` passed with no output. |

## 2026-06-15

### G02: Media Upload Hardening

Status: Completed

Type: Security hardening with service-layer upload validation coverage

Changed files:

| File | Change |
| --- | --- |
| `app/Services/MediaFileValidator.php` | Rejected empty uploads and files without an approved client extension before storage. Existing MIME allowlist, extension mapping, SVG block, size, and dimension checks remain service-owned. |
| `tests/Unit/MediaServiceTest.php` | Added service-layer coverage for empty file rejection and missing client extension rejection. |
| `SPU_SPEC_DRIVEN_EXECUTION_TODO.md` | Marked G02 complete with targeted verification evidence. |
| `SPU_SPEC_DRIVEN_CHANGELOG.md` | Added this G02 changelog entry. |

Completion evidence:

| Check | Result |
| --- | --- |
| Syntax | `php -l app/Services/MediaFileValidator.php` and `php -l tests/Unit/MediaServiceTest.php` passed. |
| Media-focused suite | `php artisan test --filter=Media` passed: 38 tests, 92 assertions. |
| Media service unit tests | `php artisan test tests/Unit/MediaServiceTest.php` passed: 28 tests, 65 assertions. |
| Frontend build | `npm run build` passed: Vite build completed in 2.46s with no unexpected warnings. |
| Route boot | `php artisan route:list` passed: 67 routes listed. |
| Full regression | `php artisan test` passed: 3421 tests, 15451 assertions, 170.16s. |

### G01: Sanitization Coverage Hardening

Status: Completed

Type: Security hardening with storage-time and publish-time sanitization coverage

Changed files:

| File | Change |
| --- | --- |
| `app/Services/PageService.php` | Hardened page body payload sanitization to recursively sanitize nested `legacy_html` blocks while leaving non-HTML block content unchanged for escaped rendering. |
| `tests/Unit/PageServiceSanitizationTest.php` | Added nested `legacy_html` body payload coverage. |
| `tests/Feature/HomepageCmsWorkflowTest.php` | Extended homepage recursive sanitization coverage to include URL-like payload keys with unsafe `javascript:` values. |
| `SPU_SPEC_DRIVEN_EXECUTION_TODO.md` | Marked G01 complete with targeted verification evidence. |
| `SPU_SPEC_DRIVEN_CHANGELOG.md` | Added this G01 changelog entry. |

Completion evidence:

| Check | Result |
| --- | --- |
| Syntax | `php -l app/Services/PageService.php`, `php -l tests/Unit/PageServiceSanitizationTest.php`, and `php -l tests/Feature/HomepageCmsWorkflowTest.php` passed. |
| Sanitizer-focused suite | `php artisan test --filter=Sanitizer` passed: 546 tests, 1562 assertions. |
| Homepage sanitization workflow | `php artisan test tests/Feature/HomepageCmsWorkflowTest.php --filter=sanitizes` passed: 1 test, 5 assertions. |
| Page sanitizer unit tests | `php artisan test tests/Unit/PageServiceSanitizationTest.php` passed: 5 tests, 19 assertions. |
| Homepage workflow | `php artisan test tests/Feature/HomepageCmsWorkflowTest.php` passed: 20 tests, 129 assertions. |
| Page service filter | `php artisan test --filter=PageService` passed: 25 tests, 101 assertions. |
| Public runtime | `php artisan test tests/Feature/PublicRuntimeTest.php` passed: 14 tests, 79 assertions. |
| Frontend build | `npm run build` passed: Vite build completed in 1.47s with no unexpected warnings. |
| Route boot | `php artisan route:list` passed: 67 routes listed. |
| Full regression | `php artisan test` passed: 3419 tests, 15394 assertions, 172.04s. |

### G03: Preview Token Confidentiality Hardening

Status: Completed

Type: Security hardening with preview invalidation coverage

Changed files:

| File | Change |
| --- | --- |
| `app/Services/PreviewTokenStore.php` | Added raw-token shape validation before resolve, validate, or invalidate lookups; token generation now uses a named 64-character length constant. |
| `tests/Feature/HomepageCmsWorkflowTest.php` | Added malformed token rejection coverage and extended homepage unpublish coverage to prove preview token invalidation. |
| `tests/Feature/Integration/PageServiceIntegrationTest.php` | Added page preview token invalidation coverage for draft save and publish operations. |
| `SPU_SPEC_DRIVEN_EXECUTION_TODO.md` | Marked G03 complete with targeted verification evidence. |
| `SPU_SPEC_DRIVEN_CHANGELOG.md` | Added this G03 changelog entry. |

Completion evidence:

| Check | Result |
| --- | --- |
| Syntax | `php -l app/Services/PreviewTokenStore.php`, `php -l tests/Feature/HomepageCmsWorkflowTest.php`, and `php -l tests/Feature/Integration/PageServiceIntegrationTest.php` passed. |
| Preview-focused suite | `php artisan test --filter=Preview` passed: 18 tests, 96 assertions. |
| Homepage workflow | `php artisan test tests/Feature/HomepageCmsWorkflowTest.php` passed: 20 tests, 128 assertions. |
| Page service integration | `php artisan test tests/Feature/Integration/PageServiceIntegrationTest.php` passed: 20 tests, 82 assertions. |
| Public runtime | `php artisan test tests/Feature/PublicRuntimeTest.php` passed: 14 tests, 79 assertions. |
| Middleware pipeline | `php artisan test tests/Feature/MiddlewarePipelineTest.php` passed: 13 tests, 75 assertions. |
| Full regression | `php artisan test` passed: 3418 tests, 15567 assertions, 173.08s. |

### D01: Page Publish Safety Characterization

Status: Completed

Type: Publish workflow regression coverage

Changed files:

| File | Change |
| --- | --- |
| `tests/Feature/Integration/PageServiceIntegrationTest.php` | Added side-effect coverage for failed publish attempts: incomplete pages stay private without success audit logs, and invalid drafts do not replace existing public pages. |
| `SPU_SPEC_DRIVEN_EXECUTION_TODO.md` | Marked D01 complete with targeted verification evidence. |
| `SPU_SPEC_DRIVEN_CHANGELOG.md` | Added this D01 changelog entry. |

Completion evidence:

| Check | Result |
| --- | --- |
| Syntax | `php -l tests/Feature/Integration/PageServiceIntegrationTest.php` passed. |
| Page service integration | `php artisan test tests/Feature/Integration/PageServiceIntegrationTest.php` passed: 18 tests, 75 assertions. |
| Page service filter | `php artisan test --filter=PageService` passed: 22 tests, 89 assertions. |
| Full regression | `php artisan test` passed: 3415 tests, 15513 assertions, 172.39s. |

### H03: Unsafe Redirect Validation Hardening

Status: Completed

Type: Continuity security hardening with runtime and command coverage

Changed files:

| File | Change |
| --- | --- |
| `app/Services/ContinuityService.php` | Added unsafe destination validation for exact and pattern redirect rules; rejected non-HTTP(S) schemes. |
| `app/Console/Commands/ValidateRedirectsCommand.php` | Extended `--fix` handling to deactivate unsafe exact redirect rules as well as unsafe pattern rules. |
| `tests/Feature/PX05/RedirectContinuityTest.php` | Added runtime coverage for untrusted external and unsafe-scheme redirect blocking. |
| `tests/Feature/PX07/RedirectValidationTest.php` | Added command coverage for detecting and deactivating unsafe exact and pattern redirects. |
| `SPU_SPEC_DRIVEN_EXECUTION_TODO.md` | Marked H03 complete with implementation and verification evidence. |
| `SPU_SPEC_DRIVEN_CHANGELOG.md` | Added this H03 changelog entry. |

Completion evidence:

| Check | Result |
| --- | --- |
| Syntax | `php -l app/Services/ContinuityService.php` and `php -l app/Console/Commands/ValidateRedirectsCommand.php` passed. |
| Redirect continuity | `php artisan test tests/Feature/PX05/RedirectContinuityTest.php` passed: 11 tests, 17 assertions. |
| File continuity | `php artisan test tests/Feature/PX05/FileContinuityTest.php` passed: 4 tests, 6 assertions. |
| Redirect validation | `php artisan test tests/Feature/PX07/RedirectValidationTest.php` passed: 6 tests, 10 assertions. |
| Frontend build | `npm run build` passed: Vite build completed in 1.40s with no unexpected warnings. |
| Route boot | `php artisan route:list` passed: 67 routes listed. |
| Full regression | `php artisan test` passed: 3415 tests, 15513 assertions, 172.39s. |

### H01: Runtime Cache Locale Isolation Coverage

Status: Completed

Type: Cache behavior regression coverage

Changed files:

| File | Change |
| --- | --- |
| `tests/Feature/MiddlewarePipelineTest.php` | Added runtime assertions that AR and EN cached homepage HIT responses do not cross-contaminate, and public POST traffic bypasses cache. |
| `SPU_SPEC_DRIVEN_EXECUTION_TODO.md` | Marked H01 complete with implementation and verification evidence. |
| `SPU_SPEC_DRIVEN_CHANGELOG.md` | Added this H01 changelog entry. |

Completion evidence:

| Check | Result |
| --- | --- |
| Middleware pipeline | `php artisan test tests/Feature/MiddlewarePipelineTest.php` passed: 13 tests, 75 assertions. |
| Cache-focused suite | `php artisan test --filter=Cache` passed: 917 tests, 2092 assertions. |
| Public runtime | `php artisan test tests/Feature/PublicRuntimeTest.php` passed: 14 tests, 79 assertions. |
| Route boot | `php artisan route:list` passed: 67 routes listed. |
| Full regression | `php artisan test` passed: 3415 tests, 15513 assertions, 172.39s. |

### C02: FontAwesome Runtime Removal

Status: Completed

Type: Frontend performance implementation with measured bundle reduction

Changed files:

| File | Change |
| --- | --- |
| `resources/js/app.js` | Removed FontAwesome imports, library registration, CSS import, and `dom.watch()`. |
| `resources/views/public/layout/footer-fallback.blade.php` | Replaced FontAwesome icons with existing static SVG masks. |
| `resources/views/public/about/directorates.blade.php` | Replaced the FontAwesome info icon with inline SVG. |
| `package.json` | Removed unused FontAwesome dependencies. |
| `package-lock.json` | Removed FontAwesome packages from the lockfile. |
| `TODO.md` | Marked the FontAwesome runtime replacement and preview publish invalidation evidence complete. |
| `SPU_SPEC_DRIVEN_EXECUTION_TODO.md` | Marked C02 complete with implementation and verification evidence. |
| `SPU_SPEC_DRIVEN_CHANGELOG.md` | Added this C02 changelog entry. |

Completion evidence:

| Check | Result |
| --- | --- |
| Source usage review | Only two source Blade files used FontAwesome classes before removal. |
| Build measurement | Public app JS changed from 143.03 kB / 45.24 kB gzip to 64.59 kB / 21.32 kB gzip. |
| Removed CSS chunk | The separate 11.27 kB FontAwesome CSS chunk no longer builds. |
| Frontend build | `npm run build` passed with no warnings. |
| Public runtime | `php artisan test tests/Feature/PublicRuntimeTest.php` passed: 14 tests, 79 assertions. |
| Homepage Blade | `php artisan test tests/Feature/HomepageBlade` passed: 73 tests, 218 assertions. |
| Public page tests | `php artisan test tests/Feature/ContactPageTest.php tests/Feature/EServicesPageTest.php` passed: 5 tests, 53 assertions. |
| Admin auth brand check | `php artisan test tests/Feature/AdminAuthFlowTest.php --filter=test_admin_login_page_loads` passed: 1 test, 3 assertions. |
| Route boot | `php artisan route:list` passed: 67 routes listed. |
| Full regression | `php artisan test` passed: 3415 tests, 15513 assertions, 172.39s. |

### E06: Homepage Preview Assembler Extraction

Status: Completed

Type: Service-layer assembler extraction with preview invalidation and authorization characterization

Changed files:

| File | Change |
| --- | --- |
| `app/Contracts/HomepagePreviewAssemblerInterface.php` | Added a contract for assembling homepage preview DTO payloads from a locale and optional token snapshot. |
| `app/Services/HomepagePreviewAssembler.php` | Added service-layer assembler for homepage draft lookup, snapshot fallback, public fallback loading, section mapping, and direction resolution. |
| `app/Services/PreviewService.php` | Delegated homepage preview assembly to the new contract while retaining token lifecycle, authorization, page preview delegation, and navigation assembly. |
| `app/Providers/AppServiceProvider.php` | Bound `HomepagePreviewAssemblerInterface` to `HomepagePreviewAssembler`. |
| `tests/Feature/HomepageCmsWorkflowTest.php` | Added manual preview invalidation, homepage publish invalidation, and homepage preview authorization characterization. |
| `tests/Feature/PublicRuntimeTest.php` | Added faculty editor scoped page preview characterization. |
| `SPU_SPEC_DRIVEN_EXECUTION_TODO.md` | Added and completed E06 with implementation and verification evidence. |
| `SPU_SPEC_DRIVEN_CHANGELOG.md` | Added this E06 changelog entry. |

Architecture boundary:

| Boundary | Result |
| --- | --- |
| Public preview contract | `PreviewServiceInterface` unchanged. |
| Preview workflow ownership | Token creation, resolution, validation, invalidation, authorization, page preview delegation, and navigation shell assembly remain in `PreviewService`. |
| Extracted responsibility | Homepage-only preview draft lookup, snapshot interpretation, fallback loading, section mapping, and locale direction. |
| Out-of-scope modules | No full-site module implementation added. |

Completion evidence:

| Check | Result |
| --- | --- |
| Pre-extraction homepage workflow | `php artisan test tests/Feature/HomepageCmsWorkflowTest.php` passed: 19 tests, 110 assertions. |
| Pre-extraction public runtime | `php artisan test tests/Feature/PublicRuntimeTest.php` passed: 14 tests, 79 assertions. |
| PHP syntax | `php -l` passed for `HomepagePreviewAssemblerInterface.php`, `HomepagePreviewAssembler.php`, `PreviewService.php`, and `AppServiceProvider.php`. |
| Post-extraction homepage workflow | `php artisan test tests/Feature/HomepageCmsWorkflowTest.php` passed: 19 tests, 110 assertions. |
| Post-extraction public runtime | `php artisan test tests/Feature/PublicRuntimeTest.php` passed: 14 tests, 79 assertions. |
| Architecture guard | `php artisan test tests/Feature/ArchitectureGuardTest.php` passed: 5 tests, 6 assertions. |
| Preview banner regression | `php artisan test tests/Feature/HomepageBlade/PreviewBannerTest.php` passed: 2 tests, 2 assertions. |
| Route checks | `php artisan route:list --path=preview`, `php artisan route:list --path=admin/manage-homepage`, and `php artisan route:list` passed. |
| Full regression | `php artisan test` passed: 3407 tests, 15271 assertions, 152.82s. |

### E05: Media File Validator Extraction

Status: Completed

Type: Service-layer validator extraction with upload hardening characterization

Changed files:

| File | Change |
| --- | --- |
| `app/Services/MediaFileValidator.php` | Added internal validator for MIME allow-list, extension matching, file size, and image dimension limits. |
| `app/Services/MediaService.php` | Delegated file validation and primary extension resolution to the validator while retaining upload workflow ownership. |
| `app/Providers/AppServiceProvider.php` | Registered `MediaFileValidator` as an internal singleton collaborator. |
| `tests/Unit/MediaServiceTest.php` | Added characterization for extension mismatch, image dimensions, Office document allow-listing, and faculty editor missing-scope rejection. |
| `SPU_SPEC_DRIVEN_EXECUTION_TODO.md` | Added and completed E05 with implementation and verification evidence. |
| `SPU_SPEC_DRIVEN_CHANGELOG.md` | Added this E05 changelog entry. |

Architecture boundary:

| Boundary | Result |
| --- | --- |
| Public service contracts | Unchanged. |
| Upload workflow ownership | Authorization, storage, DB persistence, faculty scoping, audit logging, and DTO mapping remain in `MediaService`. |
| Extracted responsibility | Security-sensitive file validation only. |
| Out-of-scope modules | No full-site module implementation added. |

Completion evidence:

| Check | Result |
| --- | --- |
| Environment check | GD extension loaded, allowing image dimension characterization. |
| Pre-extraction media suite | `php artisan test --filter=Media` passed: 36 tests, 88 assertions. |
| PHP syntax | `php -l` passed for `MediaFileValidator.php`, `MediaService.php`, and `AppServiceProvider.php`. |
| Post-extraction media suite | `php artisan test --filter=Media` passed: 36 tests, 88 assertions. |
| Architecture guard | `php artisan test tests/Feature/ArchitectureGuardTest.php` passed: 5 tests, 6 assertions. |
| Route boot | `php artisan route:list` passed: 67 routes listed. |
| Full regression | `php artisan test` passed: 3404 tests, 15187 assertions, 175.12s. |

### E04: Page Publishability Validator Extraction

Status: Completed

Type: Service-layer validator extraction and publish validation bug fix

Changed files:

| File | Change |
| --- | --- |
| `app/Services/PagePublishabilityValidator.php` | Added internal service-layer validator for live-page and draft DTO publishability checks. |
| `app/Services/PageService.php` | Delegated publishability checks to the validator while retaining publish workflow ownership. |
| `app/Services/PageDraftService.php` | Preserved explicit empty draft translation titles so publish validation can reject incomplete draft payloads. |
| `app/Providers/AppServiceProvider.php` | Registered `PagePublishabilityValidator` as an internal singleton collaborator. |
| `tests/Feature/Integration/PageServiceIntegrationTest.php` | Added valid draft publish coverage and empty AR/EN draft title rejection coverage. |
| `tests/Unit/PageServiceSanitizationTest.php` | Updated manual `PageService` construction for the new internal collaborator. |
| `SPU_SPEC_DRIVEN_EXECUTION_TODO.md` | Added and completed E04 with implementation and verification evidence. |
| `SPU_SPEC_DRIVEN_CHANGELOG.md` | Added this E04 changelog entry. |

Behavior fixed:

| Issue | Result |
| --- | --- |
| Empty draft AR/EN title | No longer hidden by generated default title during publish validation. |
| Valid draft payload | Still publishes and applies localized draft content. |
| Public contracts | Unchanged. |
| Workflow ownership | Authorization, transaction, draft application, cache invalidation, preview invalidation, and audit logging remain in `PageService`. |

Completion evidence:

| Check | Result |
| --- | --- |
| PageService focused suite | `php artisan test --filter=PageService` passed: 20 tests, 73 assertions. |
| Architecture guard | `php artisan test tests/Feature/ArchitectureGuardTest.php` passed: 5 tests, 6 assertions. |
| Preview focused regression | `php artisan test --filter=Preview` passed: 12 tests, 65 assertions. |
| Optimistic locking regression | `php artisan test tests/Feature/OptimisticLockingPropertyTest.php` passed: 40 tests, 120 assertions. |
| Full regression | `php artisan test` passed: 3400 tests, 15371 assertions, 162.07s. |

### E03: First Low-Risk Collaborator Extraction

Status: Completed

Type: Service-layer support extraction with characterization tests

Changed files:

| File | Change |
| --- | --- |
| `app/Support/HomepageDraftSectionMapper.php` | Added deterministic mapper for homepage draft section normalization, stored draft rehydration, and preview section mapping. |
| `app/Services/HomepagePublishingService.php` | Delegated pure draft section mapping to the support mapper while retaining publish workflow ownership. |
| `app/Services/PreviewService.php` | Delegated homepage preview section mapping to the support mapper while retaining token, authorization, and navigation orchestration. |
| `tests/Feature/HomepageCmsWorkflowTest.php` | Added characterization for unknown section filtering and fixed homepage order restoration during draft save. |
| `tests/Feature/PublicRuntimeTest.php` | Added characterization for locale-specific homepage preview payloads and published fallback preservation. |
| `SPU_SPEC_DRIVEN_EXECUTION_TODO.md` | Marked E03 complete with implementation and verification evidence. |
| `SPU_SPEC_DRIVEN_CHANGELOG.md` | Added this E03 changelog entry. |

Architecture boundary:

| Boundary | Result |
| --- | --- |
| Public service contracts | Unchanged. |
| Controllers and Filament | No new dependencies and no Action usage. |
| Business workflow ownership | Stayed in services. |
| Extracted responsibility | Pure homepage draft section mapping only. |
| Out-of-scope modules | No News, Facilities, Research, Events, Admissions, CRM, or migration dashboard implementation added. |

Completion evidence:

| Check | Result |
| --- | --- |
| Pre-extraction characterization | `php artisan test tests/Feature/HomepageCmsWorkflowTest.php tests/Feature/PublicRuntimeTest.php --filter=homepage` passed: 20 tests, 132 assertions. |
| PHP syntax | `php -l` passed for `HomepageDraftSectionMapper.php`, `HomepagePublishingService.php`, and `PreviewService.php`. |
| Homepage/runtime regression | `php artisan test tests/Feature/HomepageCmsWorkflowTest.php tests/Feature/PublicRuntimeTest.php` passed: 30 tests, 175 assertions. |
| Preview focused regression | `php artisan test --filter=Preview` passed: 12 tests, 65 assertions. |
| Architecture guard | `php artisan test tests/Feature/ArchitectureGuardTest.php` passed: 5 tests, 6 assertions. |
| Full regression | `php artisan test` passed: 3397 tests, 15356 assertions, 164.04s. |

### E02: Action/Internal Collaborator Architecture Rules

Status: Completed

Type: Architecture documentation and governance

Changed files:

| File | Change |
| --- | --- |
| `Docs/ARCHITECTURE.md` | Added service-layer Action/internal collaborator rules, including controller/Filament restrictions and extraction requirements. |
| `Docs/BACKEND_RULES.md` | Added internal collaborator rules that keep public service interfaces as the only higher-layer entry point. |
| `Docs/STYLEGUIDE.md` | Added naming guidance for architecture-approved Actions and pure support mappers/helpers. |
| `SPU_SPEC_DRIVEN_EXECUTION_TODO.md` | Marked E02 complete with decision and verification evidence. |
| `SPU_SPEC_DRIVEN_CHANGELOG.md` | Added this E02 changelog entry. |

Key decisions:

| Decision | Result |
| --- | --- |
| Are Actions a separate layer? | No. They are internal service-layer collaborators only. |
| Can controllers inject Actions? | No. Controllers continue to consume service interfaces only. |
| Can Filament workflow code inject Actions? | No. It continues through service interfaces. |
| Can Actions return raw Eloquent models? | No. |
| Where do pure mappers go? | `app/Support/`, not `app/Actions/`. |

Completion evidence:

| Check | Result |
| --- | --- |
| Architecture guard | `php artisan test tests/Feature/ArchitectureGuardTest.php` passed: 5 tests, 6 assertions. |
| Scope review | Stayed inside homepage/admin foundation. |

### E01: Service Size And Complexity Measurement

Status: Completed

Type: Documentation and architecture-readiness analysis

Changed files:

| File | Change |
| --- | --- |
| `SPU_SPEC_DRIVEN_EXECUTION_TODO.md` | Marked E01 complete, added service LOC/method metrics, hotspot analysis, coverage map, ranked extraction candidates, and required characterization gates. |
| `SPU_SPEC_DRIVEN_CHANGELOG.md` | Added this E01 changelog entry. |

Measured services:

| Service | LOC | Public Methods | Private Methods |
| --- | ---: | ---: | ---: |
| `HomepagePublishingService` | 711 | 9 | 24 |
| `PageService` | 695 | 18 | 15 |
| `MediaService` | 452 | 5 | 10 |
| `PreviewService` | 381 | 4 | 12 |

Refactor decision:

| Decision | Result |
| --- | --- |
| Runtime code movement | None. E01 is measurement and planning only. |
| First recommended extraction | Homepage draft section normalization/mapping, after direct characterization tests. |
| Action classes | Still blocked until E02 documents the pattern. |
| Broad rewrites | Rejected for now as too risky. |

Completion evidence:

| Check | Result |
| --- | --- |
| Homepage CMS workflow | `php artisan test tests/Feature/HomepageCmsWorkflowTest.php` passed: 16 tests, 95 assertions. |
| PageService focused suite | `php artisan test --filter=PageService` passed: 17 tests, 59 assertions. |
| Media focused suite | `php artisan test --filter=Media` passed: 32 tests, 73 assertions. |
| Scope review | Stayed inside homepage/admin foundation and did not introduce Action, Repository, or full-site module code. |

### B02: Release Gate Evidence Status

Status: Completed

Type: Documentation and release-governance implementation

Changed files:

| File | Change |
| --- | --- |
| `TODO.md` | Marked only verified automated release gates complete and added the latest automated evidence table. |
| `Docs/launch-readiness-checklist.md` | Added latest automated release evidence and explicit boundaries for manual/staging/review gates. |
| `SPU_SPEC_DRIVEN_EXECUTION_TODO.md` | Marked B02 complete with implementation and acceptance checkboxes. |
| `SPU_SPEC_DRIVEN_CHANGELOG.md` | Added this B02 changelog entry. |

Completion evidence:

| Check | Result |
| --- | --- |
| Full test suite | `php artisan test` passed: 3395 tests, 15259 assertions, 177.89s. |
| Frontend build | `npm run build` passed: Vite build completed in 1.81s with no unexpected warnings. |
| Route boot | `php artisan route:list` passed: 67 routes listed. |
| Scope review | Stayed inside homepage/admin foundation. |

Evidence boundaries:

| Gate | Status |
| --- | --- |
| Manual browser QA | Still pending. |
| Staging validation | Still pending. |
| Rollback review | Still pending. |
| Product sign-off | Still pending. |
| Focused security/performance review | Still pending. |

### B01: Validation Command Matrix

Status: Completed

Type: Documentation and release-governance implementation

Changed files:

| File | Change |
| --- | --- |
| `Docs/launch-readiness-checklist.md` | Added a structured validation command matrix, release-candidate gate, manual browser gate, and evidence rules. |
| `SPU_SPEC_DRIVEN_EXECUTION_TODO.md` | Marked B01 complete with implementation and acceptance checkboxes. |
| `SPU_SPEC_DRIVEN_CHANGELOG.md` | Created this changelog. |

Completion evidence:

| Check | Result |
| --- | --- |
| Scope review | Stayed inside homepage/admin foundation. |
| Implementation type | Documentation only; no runtime code changed. |
| Verification | `git diff --check` passed; no trailing whitespace found in new spec-driven Markdown files. |

Notes:

| Topic | Decision |
| --- | --- |
| Full News module | Still deferred and out of current scope. |
| Migration dashboard | Still blocked pending migration-within-6-months decision. |
| Action classes | Still require measurement and architecture documentation before implementation. |
