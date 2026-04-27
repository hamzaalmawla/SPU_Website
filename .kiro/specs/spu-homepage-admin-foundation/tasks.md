# Implementation Plan: SPU Homepage Admin Foundation (PX05–PX08)

## Overview

This plan covers the remaining implementation phases for the SPU website foundation. PX00–PX04 are already complete and stable. Tasks are organized by phase dependency: PX05 (SEO/Continuity) → PX06 (Admin/Filament Completion) → PX07 (Migration Backfill Tooling) → PX08 (Hardening/Tests/Launch). Each task builds incrementally on previous work, and all code delegates to the Service Layer via contracts.

## Tasks

- [x] 1. PX05 — Database migrations and Eloquent models for continuity layer
  - [x] 1.1 Create migration for `legacy_exact_redirects` table
    - Create `database/migrations/xxxx_create_legacy_exact_redirects_table.php`
    - Columns: id, legacy_path (varchar 2048), destination_url (varchar 2048), status_code (smallint unsigned default 301), locale (varchar 5 nullable), is_active (boolean default true), hit_count (int unsigned default 0), last_hit_at (timestamp nullable), notes (text nullable), timestamps
    - Indexes: idx_legacy_path (legacy_path(191)), idx_is_active (is_active)
    - _Requirements: 17.1, 17.2, 17.5_

  - [x] 1.2 Create migration for `legacy_pattern_rules` table
    - Create `database/migrations/xxxx_create_legacy_pattern_rules_table.php`
    - Columns: id, pattern (varchar 2048), replacement (varchar 2048), status_code (smallint unsigned default 301), priority (int unsigned default 100), is_active (boolean default true), hit_count (int unsigned default 0), last_hit_at (timestamp nullable), notes (text nullable), timestamps
    - Indexes: idx_priority (priority), idx_is_active (is_active)
    - _Requirements: 17.2, 17.5_

  - [x] 1.3 Create migration for `unresolved_legacy_requests` table
    - Create `database/migrations/xxxx_create_unresolved_legacy_requests_table.php`
    - Columns: id, url (varchar 2048), query_string (varchar 2048 nullable), method (varchar 10 default 'GET'), referrer (varchar 2048 nullable), resolved_locale (varchar 5 nullable), request_type (enum page/file default 'page'), user_agent (varchar 512 nullable), ip_hash (varchar 64 nullable), hit_count (int unsigned default 1), first_seen_at (timestamp), last_seen_at (timestamp), created_at (timestamp nullable)
    - Indexes: idx_url (url(191)), idx_request_type (request_type), idx_last_seen (last_seen_at)
    - _Requirements: 17.3_

  - [x] 1.4 Create migration for `legacy_file_inventory` table
    - Create `database/migrations/xxxx_create_legacy_file_inventory_table.php`
    - Columns: id, legacy_path (varchar 2048), current_path (varchar 2048 nullable), media_asset_id (bigint unsigned nullable FK → media_assets.id ON DELETE SET NULL), status (enum mapped/unmapped/missing default 'unmapped'), mime_type (varchar 255 nullable), file_size_bytes (bigint unsigned nullable), notes (text nullable), timestamps
    - Indexes: idx_legacy_path (legacy_path(191)), idx_status (status)
    - _Requirements: 18.1, 18.2, 18.3_

  - [x] 1.5 Create `LegacyExactRedirect` Eloquent model
    - Create `app/Models/LegacyExactRedirect.php`
    - HasFactory, typed $fillable, explicit casts(), passive (no business logic)
    - Scope: `scopeActive($query)` for `is_active = true`
    - _Requirements: 17.1, 31.4_

  - [x] 1.6 Create `LegacyPatternRule` Eloquent model
    - Create `app/Models/LegacyPatternRule.php`
    - HasFactory, typed $fillable, explicit casts(), passive
    - Scope: `scopeActive($query)` for `is_active = true`, `scopeOrdered($query)` for priority ASC
    - _Requirements: 17.2, 31.4_

  - [x] 1.7 Create `UnresolvedLegacyRequest` Eloquent model
    - Create `app/Models/UnresolvedLegacyRequest.php`
    - HasFactory, typed $fillable, explicit casts(), passive
    - No UPDATED_AT (append-only with hit_count increment)
    - _Requirements: 17.3, 31.4_

  - [x] 1.8 Create `LegacyFileInventory` Eloquent model
    - Create `app/Models/LegacyFileInventory.php`
    - HasFactory, typed $fillable, explicit casts(), passive
    - Relationship: `mediaAsset()` → BelongsTo MediaAsset
    - Scope: `scopeMapped($query)`, `scopeUnmapped($query)`
    - _Requirements: 18.1, 18.2, 31.4_

- [x] 2. PX05 — DTOs, contracts, and service implementations for continuity and sitemap
  - [x] 2.1 Create PX05 DTOs
    - Create `app/DTOs/RedirectResultDTO.php`: final readonly, fields: int $statusCode, string $destinationUrl, string $matchType
    - Create `app/DTOs/UnresolvedRequestDTO.php`: final readonly, fields: string $url, ?string $queryString, string $method, ?string $referrer, ?string $resolvedLocale, string $requestType, string $timestamp
    - Create `app/DTOs/SitemapEntryDTO.php`: final readonly, fields: string $loc, string $lastmod, ?string $changefreq, ?string $priority, array $alternates
    - Create `app/DTOs/RedirectRuleDTO.php`: final readonly, fields: int $id, string $legacyPath, string $destinationUrl, int $statusCode, ?string $locale, bool $isActive
    - Create `app/DTOs/PatternRuleDTO.php`: final readonly, fields: int $id, string $pattern, string $replacement, int $statusCode, int $priority, bool $isActive
    - Create `app/DTOs/FileInventoryItemDTO.php`: final readonly, fields: int $id, string $legacyPath, ?string $currentPath, ?int $mediaAssetId, string $status
    - _Requirements: 17.1, 17.2, 17.3, 18.1, 16.1, 31.3_

  - [x] 2.2 Create `ContinuityServiceInterface` contract
    - Create `app/Contracts/ContinuityServiceInterface.php`
    - Methods: resolveRedirect(string $path, ?string $queryString): ?RedirectResultDTO, resolveFileContinuity(string $path): ?string, logUnresolved(UnresolvedRequestDTO $request): bool, getExactRedirects(): Collection, getPatternRules(): Collection, validateRedirectRules(): ValidationResultDTO, getUnresolvedRequests(array $filters): Collection, getFileInventory(): Collection
    - All returns typed with DTOs/Collections, no raw models
    - _Requirements: 17.1, 17.2, 17.3, 17.4, 17.5, 18.1, 18.2, 31.1, 31.3_

  - [x] 2.3 Create `SitemapServiceInterface` contract
    - Create `app/Contracts/SitemapServiceInterface.php`
    - Methods: generateEntries(): Collection, renderXml(): string
    - _Requirements: 16.1, 16.2, 31.1, 31.3_

  - [x] 2.4 Create `ContinuityService` implementation
    - Create `app/Services/ContinuityService.php` implementing `ContinuityServiceInterface`
    - Inject `CacheServiceInterface` for cached redirect lookups
    - resolveRedirect: query exact matches first (case-insensitive), then pattern rules ordered by priority; detect loops (max 5 hops); return RedirectResultDTO or null
    - resolveFileContinuity: query legacy_file_inventory for mapped entries; return current_path or null
    - logUnresolved: upsert to unresolved_legacy_requests (increment hit_count on duplicate URL+method)
    - validateRedirectRules: detect duplicate legacy_paths, conflicting patterns, potential loops; return ValidationResultDTO
    - getExactRedirects/getPatternRules/getUnresolvedRequests/getFileInventory: query and map to DTOs
    - _Requirements: 17.1, 17.2, 17.3, 17.4, 17.5, 18.1, 18.2, 18.3, 27.2_

  - [x] 2.5 Create `SitemapService` implementation
    - Create `app/Services/SitemapService.php` implementing `SitemapServiceInterface`
    - Inject `PageServiceInterface`, `CacheServiceInterface`, `SeoMetadataServiceInterface`
    - generateEntries: query pages where status='published' AND is_enabled=true AND published_at IS NOT NULL; exclude homepage shells (use canonical /{locale} instead); include both AR/EN URLs with hreflang alternates; return Collection of SitemapEntryDTO
    - renderXml: generate valid XML sitemap string from entries; cache output with tag-based invalidation
    - _Requirements: 16.1, 16.2_

  - [x] 2.6 Register `ContinuityServiceInterface` and `SitemapServiceInterface` bindings in `AppServiceProvider`
    - Add singleton bindings for ContinuityServiceInterface → ContinuityService and SitemapServiceInterface → SitemapService
    - _Requirements: 31.1, 31.5_

- [x] 3. PX05 — Controller, middleware, and route registration
  - [x] 3.1 Create `SitemapController`
    - Create `app/Http/Controllers/SitemapController.php`
    - Constructor injects `SitemapServiceInterface` only
    - `sitemap()`: returns XML response with Content-Type application/xml from SitemapService::renderXml()
    - `robots()`: returns text/plain response; references sitemap URL; environment-aware noindex for non-production; defaults to restrictive on detection failure
    - _Requirements: 16.1, 16.2, 16.3, 16.4, 31.2_

  - [x] 3.2 Create `RedirectContinuityMiddleware`
    - Create `app/Http/Middleware/RedirectContinuityMiddleware.php`
    - Inject `ContinuityServiceInterface`
    - handle(): skip /admin, /livewire, /filament prefixes; call resolveRedirect() for incoming path; return redirect response if match found; otherwise pass through
    - terminate(): on 404 responses, log unresolved request via logUnresolved() with URL, query string, method, referrer, resolved locale, request_type (file if path has extension, page otherwise), timestamp
    - _Requirements: 17.1, 17.2, 17.3, 17.4, 17.5, 18.1, 18.2_

  - [x] 3.3 Register new routes in `routes/web.php`
    - Add `Route::get('/sitemap.xml', [SitemapController::class, 'sitemap'])->name('sitemap');`
    - Add `Route::get('/robots.txt', [SitemapController::class, 'robots'])->name('robots');`
    - Place before locale-prefixed group
    - _Requirements: 16.1, 16.3_

  - [x] 3.4 Register `RedirectContinuityMiddleware` in `bootstrap/app.php`
    - Add to global middleware pipeline, early (before locale middleware)
    - _Requirements: 17.1, 17.2_

- [x] 4. Checkpoint — PX05 verification
  - Ensure all tests pass, ask the user if questions arise.
  - Verify: `php artisan migrate` runs without errors
  - Verify: `php artisan route:list` shows sitemap.xml and robots.txt routes
  - Verify: container resolves ContinuityServiceInterface and SitemapServiceInterface

- [-] 5. PX06 — Real MediaService and SlugService (replacing placeholders)
  - [x] 5.1 Create real `MediaService` implementation
    - Create `app/Services/MediaService.php` implementing `MediaServiceInterface`
    - Inject configured disk (local/s3 via config)
    - upload(): validate file type, size, dimensions; store to disk; persist metadata to media_assets table; return MediaUploadResultDTO
    - delete(): soft-delete media asset
    - updateMetadata(): update title, alt_text, caption fields (AR/EN)
    - list(): query with filters, map to Collection of MediaUploadResultDTO
    - _Requirements: 22.1, 22.2, 22.3, 22.4, 22.5, 31.1_

  - [~] 5.2 Create real `SlugService` implementation
    - Create `app/Services/SlugService.php` implementing `SlugServiceInterface`
    - generate(): create URL-safe slug from Arabic/English source text; transliterate Arabic; ensure uniqueness within target model table; append numeric suffix on collision (max 10 attempts, then throw)
    - _Requirements: 31.1_

  - [~] 5.3 Update `AppServiceProvider` to bind real MediaService and SlugService
    - Move MediaServiceInterface and SlugServiceInterface from `intentionalPlaceholderBindings()` to `resolvedBindings()`
    - Bind MediaServiceInterface → MediaService, SlugServiceInterface → SlugService
    - Remove MediaServicePlaceholder and SlugServicePlaceholder imports
    - _Requirements: 31.1, 31.5_

- [ ] 6. PX06 — Filament pages and resources (Homepage, Pages, Menu)
  - [~] 6.1 Create `ManageHomepage` Filament page
    - Create `app/Filament/Pages/ManageHomepage.php`
    - Custom Filament page (not a resource — homepage is a singleton)
    - Renders fixed 10-section model with tabbed/accordion layout per section
    - Each section has AR/EN locale tabs with structured form fields matching section payload schema
    - Actions: Save Draft, Preview (AR), Preview (EN), Publish, Schedule, Unpublish
    - Displays current state badge (draft/published/scheduled)
    - Delegates to HomepageSectionServiceInterface, HomepagePublishingServiceInterface, PreviewServiceInterface
    - canAccess(): true for super_admin and editor roles
    - _Requirements: 19.1, 19.2, 19.3, 19.4, 19.5, 26.1, 26.2_

  - [~] 6.2 Create `PageResource` Filament resource
    - Create `app/Filament/Resources/PageResource.php` with ListPages, CreatePage, EditPage, ViewPage pages
    - List: filterable by status, locale, parent; sortable by title, updated_at
    - Create/Edit form tabs: Metadata (parent, slug, template, status, enabled/nav/breadcrumb toggles), Arabic Translation, English Translation, Arabic SEO, English SEO
    - Actions: Save Draft, Preview, Publish, Schedule, Unpublish — all delegate to PageServiceInterface
    - canAccess(): super_admin, editor, faculty_editor (scoped)
    - _Requirements: 20.1, 20.2, 20.3, 20.4, 20.5, 26.1, 26.2, 26.3_

  - [~] 6.3 Create `ManageMenu` Filament page
    - Create `app/Filament/Pages/ManageMenu.php`
    - Custom Filament page with tree builder UI
    - Tabs for header, footer, utility menu groups
    - Each item: label (AR/EN), target type (page/custom URL/external), URL, enabled toggle, open_in_new_tab
    - Drag/drop reordering with depth enforcement (max 2)
    - Delegates to MenuServiceInterface
    - canAccess(): super_admin, editor
    - _Requirements: 21.1, 21.2, 21.3, 21.4, 21.5, 26.1, 26.2_

- [ ] 7. PX06 — Filament resources (Media, Settings, Users, Audit)
  - [~] 7.1 Create `MediaAssetResource` Filament resource
    - Create `app/Filament/Resources/MediaAssetResource.php` with list, create, edit, view pages
    - List: grid/table view toggle, search by filename/title, filter by mime_type
    - Upload: file upload field delegating to MediaServiceInterface::upload()
    - Edit: title (AR/EN), alt_text (AR/EN), caption (AR/EN)
    - View: preview image/file, metadata display (URL, type, dimensions, size)
    - canAccess(): super_admin, editor, faculty_editor
    - _Requirements: 22.1, 22.2, 22.3, 22.4, 22.5, 26.1, 26.2, 26.3_

  - [~] 7.2 Create `ManageSettings` Filament page
    - Create `app/Filament/Pages/ManageSettings.php`
    - Custom Filament page with grouped form sections: Utility Navigation, Footer, Emergency Notice, Contact, Social, SEO Defaults
    - Locale-aware fields where applicable (AR/EN tabs within groups)
    - Delegates to SettingsServiceInterface
    - canAccess(): super_admin, editor
    - _Requirements: 23.1, 23.2, 23.3, 23.4, 26.1, 26.2_

  - [~] 7.3 Create `UserResource` Filament resource
    - Create `app/Filament/Resources/UserResource.php` with list, edit pages (no create/delete)
    - List: name, email, role, locked status, last login
    - Edit: name, email, role assignment, faculty_scope_slug, lock/unlock toggle, password reset
    - No delete action (soft-delete only via lock)
    - Delegates to AuthServiceInterface
    - canAccess(): super_admin only
    - _Requirements: 24.1, 24.2, 24.3, 26.1_

  - [~] 7.4 Create `AuditLogResource` Filament resource
    - Create `app/Filament/Resources/AuditLogResource.php` with list, view pages
    - Read-only resource — no create/edit/delete actions
    - List: filterable by action, entity_type, user, date range
    - View: full metadata JSON display
    - canAccess(): super_admin only
    - _Requirements: 25.1, 25.2, 25.3, 26.1_

- [ ] 8. Checkpoint — PX06 verification
  - Ensure all tests pass, ask the user if questions arise.
  - Verify: Filament panel at /admin discovers all resources and pages
  - Verify: container resolves MediaServiceInterface → MediaService and SlugServiceInterface → SlugService
  - Verify: role-based visibility works (super_admin sees all, editor sees allowed, faculty_editor sees scoped)

- [ ] 9. PX07 — Migration backfill CLI commands
  - [~] 9.1 Create `continuity:export-url-inventory` Artisan command
    - Register in `routes/console.php`
    - Signature: `continuity:export-url-inventory {--format=json} {--disk=local} {--dir=continuity-exports}`
    - Produce machine-readable (JSON/CSV) list of legacy public URL candidates with source type, legacy path, expected destination, locale, and status classification
    - Use ContinuityServiceInterface for data access
    - Reuse existing MigrationLog/LegacyRecordSnapshot infrastructure
    - _Requirements: 27.1, 27.7_

  - [~] 9.2 Create `continuity:validate-redirects` Artisan command
    - Register in `routes/console.php`
    - Signature: `continuity:validate-redirects {--fix}`
    - Detect and report invalid, duplicate, or conflicting redirect rules
    - Use ContinuityServiceInterface::validateRedirectRules()
    - With --fix flag: deactivate invalid rules
    - _Requirements: 27.2, 27.7_

  - [~] 9.3 Create `continuity:export-file-inventory` Artisan command
    - Register in `routes/console.php`
    - Signature: `continuity:export-file-inventory {--format=json} {--disk=local} {--dir=continuity-exports}`
    - Produce machine-readable report of legacy file/document continuity state (mapped vs unmapped)
    - Use ContinuityServiceInterface::getFileInventory()
    - _Requirements: 27.3, 27.7_

  - [~] 9.4 Create `continuity:report-unresolved` Artisan command
    - Register in `routes/console.php`
    - Signature: `continuity:report-unresolved {--since=} {--type=} {--format=json}`
    - Produce structured report of unresolved URL and file continuity issues
    - Use ContinuityServiceInterface::getUnresolvedRequests()
    - _Requirements: 27.4, 27.7_

  - [~] 9.5 Create `continuity:validate-seo` Artisan command
    - Register in `routes/console.php`
    - Signature: `continuity:validate-seo {--locale=} {--format=json}`
    - Identify published pages with weak, incomplete, or missing SEO metadata
    - Use SeoMetadataServiceInterface for data access
    - _Requirements: 27.5, 27.7_

  - [~] 9.6 Create `continuity:reconciliation-report` Artisan command
    - Register in `routes/console.php`
    - Signature: `continuity:reconciliation-report {--format=json} {--disk=local} {--dir=continuity-exports}`
    - Combined reconciliation report: URL inventory + redirect validation + file inventory + unresolved requests + SEO gaps
    - Identify ambiguous or overlapping legacy structures requiring engineering review
    - _Requirements: 27.6, 27.7_

- [ ] 10. Checkpoint — PX07 verification
  - Ensure all tests pass, ask the user if questions arise.
  - Verify: `php artisan list` shows all 6 continuity commands
  - Verify: each command runs without errors against empty/seeded data

- [ ] 11. PX08 — Property-based test infrastructure and property tests
  - [~] 11.1 Create `PropertyTestHelpers` trait
    - Create `tests/Support/PropertyTestHelpers.php`
    - Methods: randomLocale(), randomSlugPath(), randomSeoFields(), randomRedirectRules(), randomPageCollection()
    - Each method generates random valid inputs for property test data providers
    - _Requirements: 33.2_

  - [~] 11.2 Write property test: Canonical URL is always absolute and locale-correct (Property 1)
    - **Property 1: Canonical URL is always absolute and locale-correct**
    - **Validates: Requirements 15.1, 15.2**
    - Create `tests/Unit/SeoMetadataServicePropertyTest.php`
    - Data provider generates 100+ random locale/path combinations
    - Assert: result starts with http:// or https://, contains correct locale prefix

  - [~] 11.3 Write property test: Hreflang reciprocity (Property 2)
    - **Property 2: Hreflang reciprocity**
    - **Validates: Requirements 15.3**
    - Add to `tests/Unit/SeoMetadataServicePropertyTest.php`
    - Data provider generates 100+ random locale-path maps
    - Assert: output count matches input count, output locales match input locales, all URLs are absolute

  - [~] 11.4 Write property test: SEO field resolution with fallback (Property 3)
    - **Property 3: SEO field resolution with fallback**
    - **Validates: Requirements 15.4, 15.5**
    - Add to `tests/Unit/SeoMetadataServicePropertyTest.php`
    - Data provider generates 100+ random SEO field combinations with nullable fields
    - Assert: output title is never null, page-specific values used when present, fallback used when null

  - [~] 11.5 Write property test: Sitemap contains only published, enabled pages (Property 4)
    - **Property 4: Sitemap contains only published, enabled pages**
    - **Validates: Requirements 16.1, 16.2**
    - Create `tests/Unit/SitemapServiceTest.php`
    - Data provider generates 100+ random page collections with mixed statuses
    - Assert: output contains only pages where status=published AND is_enabled=true AND published_at IS NOT NULL

  - [~] 11.6 Write property test: Exact redirect resolution correctness (Property 5)
    - **Property 5: Exact redirect resolution correctness**
    - **Validates: Requirements 17.1**
    - Create `tests/Unit/ContinuityServiceTest.php`
    - Data provider generates 100+ random exact redirect rules
    - Assert: resolveRedirect returns correct destination, status code, and matchType='exact'

  - [~] 11.7 Write property test: Pattern redirect resolution correctness (Property 6)
    - **Property 6: Pattern redirect resolution correctness**
    - **Validates: Requirements 17.2**
    - Add to `tests/Unit/ContinuityServiceTest.php`
    - Data provider generates 100+ random pattern rules with matching paths
    - Assert: resolveRedirect returns resolved destination with capture groups applied, matchType='pattern'

  - [~] 11.8 Write property test: Unresolved request logging completeness (Property 7)
    - **Property 7: Unresolved request logging completeness**
    - **Validates: Requirements 17.3**
    - Add to `tests/Unit/ContinuityServiceTest.php`
    - Data provider generates 100+ random unresolved request paths (mix of file-like and page-like)
    - Assert: record persisted with all required fields, request_type='file' for paths with extensions, 'page' otherwise

  - [~] 11.9 Write property test: No redirect loops (Property 8)
    - **Property 8: No redirect loops**
    - **Validates: Requirements 17.4**
    - Add to `tests/Unit/ContinuityServiceTest.php`
    - Data provider generates 100+ redirect chain scenarios including cycles
    - Assert: resolution terminates within 5 hops, returns last non-looping destination on cycle

  - [~] 11.10 Write property test: Exact rules take priority over pattern rules (Property 9)
    - **Property 9: Exact rules take priority over pattern rules**
    - **Validates: Requirements 17.5**
    - Add to `tests/Unit/ContinuityServiceTest.php`
    - Data provider generates 100+ scenarios where path matches both exact and pattern rules
    - Assert: resolveRedirect returns exact rule destination, never pattern rule destination

  - [~] 11.11 Write property test: File continuity resolution correctness (Property 10)
    - **Property 10: File continuity resolution correctness**
    - **Validates: Requirements 18.1**
    - Add to `tests/Unit/ContinuityServiceTest.php`
    - Data provider generates 100+ file inventory entries (mapped and unmapped)
    - Assert: mapped entries return current_path, non-matching paths return null

  - [~] 11.12 Write property test: Redirect rule conflict detection (Property 11)
    - **Property 11: Redirect rule conflict detection**
    - **Validates: Requirements 27.2**
    - Add to `tests/Unit/ContinuityServiceTest.php`
    - Data provider generates 100+ rule sets with intentional duplicates and conflicts
    - Assert: validateRedirectRules identifies all duplicates and conflicts in ValidationResultDTO

  - [~] 11.13 Write property test: SEO completeness validation identifies weak entries (Property 12)
    - **Property 12: SEO completeness validation identifies weak entries**
    - **Validates: Requirements 27.5**
    - Create `tests/Unit/SeoValidationPropertyTest.php`
    - Data provider generates 100+ published pages with various SEO completeness levels
    - Assert: pages with null/empty meta_title, meta_description, or canonical_url are flagged

- [ ] 12. PX08 — Feature tests for PX05 (SEO & Continuity)
  - [~] 12.1 Write PX05 feature tests: Sitemap and robots.txt
    - Create `tests/Feature/PX05/SitemapTest.php`
    - Test: sitemap.xml returns valid XML with correct Content-Type
    - Test: sitemap contains only published, enabled page URLs with locale alternates
    - Test: sitemap excludes draft, unpublished, scheduled, disabled, admin, preview URLs
    - Create `tests/Feature/PX05/RobotsTxtTest.php`
    - Test: robots.txt returns text/plain with sitemap reference
    - Test: non-production environment returns noindex directives
    - _Requirements: 16.1, 16.2, 16.3, 16.4_

  - [~] 12.2 Write PX05 feature tests: Redirect continuity
    - Create `tests/Feature/PX05/RedirectContinuityTest.php`
    - Test: exact match returns 301 redirect to correct destination
    - Test: pattern match returns 301 redirect with resolved destination
    - Test: no match passes through to normal routing (404)
    - Test: unresolved request is logged with all required fields
    - Test: /admin, /livewire, /filament prefixes are skipped by middleware
    - Test: redirect loops terminate within 5 hops
    - _Requirements: 17.1, 17.2, 17.3, 17.4, 17.5_

  - [~] 12.3 Write PX05 feature tests: File continuity
    - Create `tests/Feature/PX05/FileContinuityTest.php`
    - Test: mapped file path resolves to current delivery path
    - Test: unmapped file path is logged as unresolved
    - _Requirements: 18.1, 18.2, 18.3_

  - [~] 12.4 Write PX05 feature tests: SEO rendering
    - Create `tests/Feature/PX05/SeoRenderingTest.php`
    - Test: homepage renders absolute canonical URL with correct locale
    - Test: landing page renders absolute canonical URL with correct locale and slug
    - Test: pages with translations render reciprocal hreflang tags
    - Test: page-specific SEO values override defaults
    - Test: missing SEO fields fall back to settings defaults
    - Test: robots directive renders when set
    - _Requirements: 15.1, 15.2, 15.3, 15.4, 15.5, 15.6_

- [ ] 13. PX08 — Feature tests for PX06 (Filament Admin)
  - [~] 13.1 Write PX06 feature tests: Filament resources and authorization
    - Create `tests/Feature/PX06/ManageHomepageTest.php` — test section editing, draft save, publish, schedule, unpublish actions delegate to services
    - Create `tests/Feature/PX06/PageResourceTest.php` — test list, create, edit, view; draft/publish/schedule/unpublish actions
    - Create `tests/Feature/PX06/ManageMenuTest.php` — test tree management, reordering, depth enforcement
    - Create `tests/Feature/PX06/MediaAssetResourceTest.php` — test upload, list, edit metadata, view
    - Create `tests/Feature/PX06/ManageSettingsTest.php` — test grouped settings editing, cache invalidation
    - Create `tests/Feature/PX06/UserResourceTest.php` — test user list, edit, role assignment, lock/unlock
    - Create `tests/Feature/PX06/AuditLogResourceTest.php` — test read-only list, view, filtering
    - _Requirements: 19.1–19.5, 20.1–20.5, 21.1–21.5, 22.1–22.5, 23.1–23.4, 24.1–24.3, 25.1–25.3_

  - [~] 13.2 Write PX06 feature tests: Role-based visibility
    - Create `tests/Feature/PX06/RoleBasedVisibilityTest.php`
    - Test: super_admin sees all resources and pages
    - Test: editor sees homepage, pages, menu, media, settings only
    - Test: faculty_editor sees only scoped pages and media
    - Test: unauthorized access returns 403
    - _Requirements: 26.1, 26.2, 26.3, 26.4_

- [ ] 14. PX08 — Feature tests for PX07 (CLI Commands)
  - [~] 14.1 Write PX07 feature tests: CLI commands
    - Create `tests/Feature/PX07/UrlInventoryExportTest.php` — test JSON/CSV output with seeded data
    - Create `tests/Feature/PX07/RedirectValidationTest.php` — test detection of invalid/duplicate rules, --fix flag
    - Create `tests/Feature/PX07/FileInventoryExportTest.php` — test mapped/unmapped file reporting
    - Create `tests/Feature/PX07/UnresolvedReportTest.php` — test filtering by --since, --type
    - Create `tests/Feature/PX07/SeoValidationTest.php` — test identification of weak SEO entries
    - Create `tests/Feature/PX07/ReconciliationReportTest.php` — test combined report output
    - _Requirements: 27.1, 27.2, 27.3, 27.4, 27.5, 27.6_

- [ ] 15. PX08 — Unit tests for non-property service logic
  - [~] 15.1 Write unit tests for SlugService and MediaService
    - Create `tests/Unit/SlugServiceTest.php` — test slug generation, Arabic transliteration, uniqueness with collision suffix, max attempts exception
    - Create `tests/Unit/MediaServiceTest.php` — test upload validation (type, size, dimensions), metadata update, soft-delete, list with filters
    - _Requirements: 31.1, 22.1, 22.5_

- [ ] 16. PX08 — Launch validation and cache warm commands
  - [~] 16.1 Create `launch:validate` Artisan command
    - Register in `routes/console.php`
    - Signature: `launch:validate {--environment=staging}`
    - Checks: homepage AR/EN rendering, landing page rendering, canonical/hreflang correctness, sitemap presence, robots.txt correctness, redirect continuity samples, file continuity samples, admin preview safety, cache behavior, audit behavior
    - Continue all checks even if some fail; report all failures at end; exit code 1 if any critical check fails
    - _Requirements: 34.1_

  - [~] 16.2 Create `cache:warm` Artisan command
    - Register in `routes/console.php`
    - Signature: `cache:warm {--locale=} {--include-sitemap}`
    - Warms: homepage AR/EN, top-level landing pages AR/EN, navigation/settings payloads, sitemap output (when --include-sitemap)
    - Log warnings for unavailable targets, continue to next; report partial warm at end
    - _Requirements: 34.3_

  - [~] 16.3 Write PX08 feature tests: Launch validation and cache warm
    - Create `tests/Feature/PX08/LaunchValidationTest.php` — test all checks pass with valid seeded data, fail with invalid data
    - Create `tests/Feature/PX08/CacheWarmTest.php` — test expected cache keys are warmed
    - _Requirements: 34.1, 34.3_

- [ ] 17. PX08 — Launch readiness and rollback documentation
  - [~] 17.1 Create launch-readiness checklist document
    - Create `docs/launch-readiness-checklist.md`
    - Sections: routing, locale, SEO, continuity, file/media, admin, cache, audit, staging noindex, rollback readiness
    - Each section lists specific checks and expected outcomes
    - _Requirements: 33.3_

  - [~] 17.2 Create rollback preparation document
    - Create `docs/rollback-preparation.md`
    - Sections: rollback threshold definitions, cutover abort criteria, pre-cutover snapshot expectations, continuity rollback expectations, unresolved continuity spike monitoring
    - _Requirements: 34.2_

- [ ] 18. Final checkpoint — Full test suite and launch readiness
  - Ensure all tests pass, ask the user if questions arise.
  - Verify: `php artisan test` passes all PX05–PX08 tests
  - Verify: `php artisan launch:validate` passes against seeded staging data
  - Verify: all Filament resources accessible with correct role-based visibility
  - Verify: sitemap.xml, robots.txt, redirect continuity, and file continuity all functional

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation between phases
- Property tests validate universal correctness properties from the design document (Properties 1–12)
- All Filament resources/pages delegate to existing service contracts — no business logic in admin layer
- PX05 must complete before PX06 (ContinuityService needed), and PX06 before PX07 (services needed for CLI commands)
- Existing PX00–PX04 code is not modified unless strictly necessary for integration (e.g., AppServiceProvider bindings)
