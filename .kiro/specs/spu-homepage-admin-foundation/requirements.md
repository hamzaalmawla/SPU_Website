# Requirements Document

## Introduction

This document defines the requirements for the Syrian Private University (spu.edu.sy) website homepage and admin panel foundation. The project is a multi-phase implementation (PX00–PX08) that transforms a partially-built Laravel 12 / Filament v3 repository into a production-ready, bilingual (AR/EN) CMS-driven homepage with admin panel, SEO continuity, and migration tooling. The system follows a strict Service Layer architecture where all business logic resides exclusively in services, controllers remain thin orchestration layers, and models are passive data containers.

## Glossary

- **System**: The SPU website application (Laravel 12 + Filament v3 + MySQL 8 + Redis)
- **Service_Layer**: The application layer (`app/Services/`) that is the exclusive location for all business logic
- **Controller**: Thin orchestration layer (`app/Http/Controllers/`) that injects interfaces and passes structured payloads to views
- **Model**: Passive data container (`app/Models/`) limited to relationships, scopes, casts, and simple accessors
- **Contract**: PHP interface (`app/Contracts/`) defining service method signatures with typed returns
- **DTO**: Final readonly PHP 8.2 class (`app/DTOs/`) used for structured data transfer between layers
- **Homepage**: The fixed 10-section CMS-driven landing page rendered at `/{locale}` (ar or en)
- **Homepage_Section**: One of exactly 10 approved CMS sections: hero, hero_stats, academic_faculties, achievements_highlights, university_news, research_studies, events_activities, medical_facilities_services, bottom_stats, footer
- **Landing_Page**: A generic bilingual page resolved by locale and slug, rendered through the page builder shell
- **Locale**: One of two supported languages — `ar` (Arabic, RTL, default) or `en` (English, LTR)
- **Draft**: Unpublished content state that is editable but invisible to public visitors
- **Published**: Content state that is visible to public visitors
- **Scheduled**: Content with a future publish timestamp that becomes published automatically at that time
- **Preview_Token**: A time-limited, cryptographic token granting temporary access to draft content without public exposure
- **Filament**: The v3 admin panel framework mounted at `/admin`
- **Audit_Log**: An append-only record of admin write operations stored in the `audit_logs` table
- **Cache_Service**: The Redis-backed caching layer with locale-aware keys and targeted invalidation
- **Navigation_Service**: The service aggregating primary nav, utility nav, footer, language-switch, and active-state data
- **Settings_Service**: The service managing grouped, locale-aware application settings
- **SEO_Service**: The service generating canonical URLs, hreflang tags, meta tags, and OG tags
- **Continuity_Layer**: The redirect and file continuity runtime resolving legacy URLs to current destinations
- **super_admin**: Role with full system access
- **editor**: Role with homepage, pages, menu, media, and settings access within allowed scope
- **faculty_editor**: Role with scoped content access only, restricted by `faculty_scope_slug`

## Requirements

### Requirement 1: Baseline Reconciliation and Roadmap (PX00)

**User Story:** As a development lead, I want a verified baseline of the current repository state against the old prompt pack, so that all future implementation phases are grounded in repo reality rather than outdated assumptions.

#### Acceptance Criteria

1. WHEN the baseline reconciliation phase executes, THE System SHALL produce a repo-reality baseline document that classifies each old P01–P12 prompt area as done, partially done, missing, obsolete, or dangerous to rerun
2. WHEN the baseline reconciliation phase executes, THE System SHALL record critical project decisions for admin strategy, page URL strategy, slug strategy, locale strategy, legacy content strategy, archive/redirect/410 strategy, and rollback threshold
3. WHEN the baseline reconciliation phase executes, THE System SHALL identify foundation areas that must be patched rather than regenerated, including contracts, DTOs, middleware, auth scaffolding, schema, models, and import tooling
4. WHEN the baseline reconciliation phase executes, THE System SHALL produce a replacement phased roadmap (PX00–PX08) based on current repo state that explicitly separates already-built foundation, incomplete runtime, homepage CMS, navigation/settings, SEO/continuity, admin completion, migration backfill, and hardening/launch
5. WHEN the baseline reconciliation phase executes, THE System SHALL identify the actual critical path to migration readiness based on repository evidence

### Requirement 2: Foundation Normalization (PX01)

**User Story:** As a developer, I want the existing foundation (contracts, DTOs, bindings, middleware, auth, models, seeders) patched to match current repo reality, so that subsequent phases build on a stable and intentional base.

#### Acceptance Criteria

1. THE System SHALL ensure all contracts in `app/Contracts/` use typed PHP 8.2 signatures and no public service contract returns a raw Eloquent model
2. THE System SHALL ensure all DTOs in `app/DTOs/` are PHP 8.2 `final readonly` classes aligned to current service boundaries with no stale wide-scope leftovers
3. WHEN the foundation normalization phase completes, THE System SHALL have explicit documentation of page content precedence between `pages.content_json` and `page_translations` payload fields, sufficient for PX02 runtime implementation
4. THE System SHALL ensure all interface-to-implementation bindings in `AppServiceProvider` are intentional, with placeholder bindings clearly marked as deliberately temporary
5. THE System SHALL ensure middleware aliases, locale middleware, admin middleware, and cache middleware are coherent and registered in `bootstrap/app.php`
6. THE System SHALL ensure the auth/RBAC foundation supports three roles (super_admin, editor, faculty_editor) with account lock after 5 failed login attempts
7. THE System SHALL ensure all models contain only relationships, scopes, casts, and simple accessors with no embedded business logic
8. WHEN the foundation normalization phase completes, THE System SHALL have a seeding policy that explicitly separates local/dev scaffolding from production-safe seeding
9. WHEN `php artisan list` executes after foundation normalization, THE System SHALL complete without container resolution errors
10. WHEN `php artisan route:list` executes after foundation normalization, THE System SHALL complete without errors

### Requirement 3: Public Homepage Rendering (PX02)

**User Story:** As a public visitor, I want to view the university homepage in Arabic or English, so that I can access university information in my preferred language.

#### Acceptance Criteria

1. WHEN a visitor requests `/ar`, THE System SHALL render the Arabic homepage from real published homepage data through the Service_Layer
2. WHEN a visitor requests `/en`, THE System SHALL render the English homepage from real published homepage data through the Service_Layer
3. THE HomeController SHALL inject interfaces only and contain no business logic or direct Eloquent model imports
4. THE System SHALL pass structured data to homepage views including navigation, utility navigation, footer payload, SEO metadata, locale, and text direction
5. WHEN the homepage renders, THE System SHALL use only published and enabled Homepage_Section data
6. WHEN a visitor requests `/`, THE System SHALL redirect to `/ar`

### Requirement 4: Public Landing Page Rendering (PX02)

**User Story:** As a public visitor, I want to view landing pages by locale and slug, so that I can navigate to specific university content sections.

#### Acceptance Criteria

1. WHEN a visitor requests `/{locale}/{slug}` for a published and enabled page, THE System SHALL render the landing page with real page content, breadcrumbs, navigation, and SEO metadata through the Service_Layer
2. WHEN a visitor requests a page slug that does not exist, THE System SHALL return HTTP 404
3. WHEN a visitor requests a page that is in draft status, THE System SHALL return HTTP 404
4. WHEN a visitor requests a page that is disabled, THE System SHALL return HTTP 404
5. WHEN a visitor requests a page that is unpublished, THE System SHALL return HTTP 404
6. WHEN a visitor requests a scheduled page before its publish timestamp, THE System SHALL return HTTP 404
7. THE PageController SHALL inject interfaces only and contain no business logic or direct Eloquent model imports
8. WHEN a landing page renders, THE System SHALL include a breadcrumb payload derived from the page hierarchy
9. WHEN a landing page has an equivalent translation in the other locale, THE System SHALL generate a language-switch URL preserving the page context


### Requirement 5: Page Content Precedence and URL Model (PX02)

**User Story:** As a developer, I want a clear, documented content precedence rule and URL model for public page hydration, so that runtime rendering is deterministic and unambiguous.

#### Acceptance Criteria

1. THE System SHALL implement a single, documented content precedence rule defining which fields are authoritative for homepage shell versus landing-page shell runtime reads when both `pages.content_json` and `page_translations` payload fields exist
2. THE System SHALL implement locale-prefixed public URLs in the form `/{locale}/{slug}` for all landing pages
3. WHEN a page has child pages, THE System SHALL resolve child page URLs using the hierarchical slug path
4. THE System SHALL generate language-switch URLs from real routing and page context, preserving the current page when equivalent content exists in the other locale

### Requirement 6: Preview Hydration (PX02)

**User Story:** As an editor, I want to preview draft content without exposing it publicly, so that I can verify changes before publishing.

#### Acceptance Criteria

1. WHEN a valid Preview_Token is provided, THE System SHALL render draft content for the specified locale
2. WHEN a Preview_Token has expired, THE System SHALL deny access to draft content
3. WHEN no Preview_Token is provided, THE System SHALL never render draft content on public routes
4. THE System SHALL support locale-aware preview for both AR and EN independently
5. THE System SHALL bypass public page cache for all preview requests

### Requirement 7: Homepage CMS Section Management (PX03)

**User Story:** As an editor, I want to manage homepage sections through a structured CMS, so that I can update homepage content without developer intervention.

#### Acceptance Criteria

1. THE HomepageSectionService SHALL support exactly 10 fixed section keys: hero, hero_stats, academic_faculties, achievements_highlights, university_news, research_studies, events_activities, medical_facilities_services, bottom_stats, footer
2. WHEN an editor updates a homepage section, THE HomepageSectionService SHALL accept locale-specific structured payloads and validate them against the section's schema
3. WHEN an editor requests all homepage sections, THE HomepageSectionService SHALL return sections in their defined sort order
4. WHEN an editor toggles a section's enabled state, THE HomepageSectionService SHALL update the section without affecting other sections
5. THE System SHALL support AR and EN homepage content independently, allowing different content per locale per section
6. WHEN a section payload fails validation, THE HomepageSectionService SHALL return structured validation errors identifying the specific field violations

### Requirement 8: Homepage Section Payload Schemas (PX03)

**User Story:** As an editor, I want each homepage section to have a defined structured payload schema, so that content entry is guided and validated.

#### Acceptance Criteria

1. THE hero section payload SHALL support background image (required), optional video, overlay config, headline, subheadline, primary CTA (label + URL), secondary CTA (label + URL), optional badge/kicker, and optional alignment config
2. THE hero_stats section payload SHALL support a collection of stat cards with value, optional suffix/prefix, label, optional icon, optional helper text, optional link, and ordering support
3. THE academic_faculties section payload SHALL support section title, optional subtitle, and repeating faculty cards with title, short description, icon or image, accent/theme token, CTA label, and CTA URL
4. THE achievements_highlights section payload SHALL support section title, optional subtitle, and repeating highlight cards with title, short text, optional icon, optional metric, optional date/label, CTA label, and CTA URL
5. THE university_news section payload SHALL support section title, card collection with manual selection mode, and item fields including image, title, optional excerpt, publish date, category label, optional badge/tag, CTA URL, and section CTA
6. THE research_studies section payload SHALL support section title, card collection with manual selection mode, and item fields including optional image, title, optional excerpt, publish date, category/type, optional authors, CTA URL, and section CTA
7. THE events_activities section payload SHALL support section title, highlighted event cards, optional mini-calendar payload, and item fields including optional image, title, date, optional time, optional location, optional short description, and CTA URL
8. THE medical_facilities_services section payload SHALL support section title and repeating service cards with title, short description, image, CTA label, CTA URL, and optional type tag
9. THE bottom_stats section payload SHALL support a collection of stat items with numeric value, label, optional suffix/prefix, and ordering support
10. THE footer section payload SHALL support logo/brand block, contact block, optional map/embed block, social links, footer navigation groups, legal links, copyright text, and optional emergency notice zone

### Requirement 9: Homepage Draft/Publish Workflow (PX03)

**User Story:** As an editor, I want to save drafts, publish, schedule, and unpublish the homepage, so that I can control when content goes live.

#### Acceptance Criteria

1. WHEN an editor saves a homepage draft, THE HomepagePublishingService SHALL persist the draft snapshot without affecting the currently published homepage
2. WHEN an editor publishes the homepage, THE HomepagePublishingService SHALL validate that all required section content exists before allowing publication
3. IF required homepage content is missing during publish, THEN THE HomepagePublishingService SHALL block the publish operation and return structured validation errors
4. WHEN an editor schedules a homepage publish, THE HomepagePublishingService SHALL store the future publish timestamp and execute publication at the scheduled time
5. WHEN an editor unpublishes the homepage, THE HomepagePublishingService SHALL remove the homepage from public published state while preserving draft and history data
6. WHEN the homepage is published, THE HomepagePublishingService SHALL invalidate the homepage cache for all locales
7. WHEN any homepage write operation occurs, THE HomepagePublishingService SHALL create an Audit_Log entry recording the action, user, and metadata

### Requirement 10: Homepage Preview (PX03)

**User Story:** As an editor, I want to preview the homepage draft before publishing, so that I can verify the appearance across locales and device modes.

#### Acceptance Criteria

1. WHEN an editor requests a homepage preview, THE PreviewService SHALL issue a time-limited Preview_Token
2. WHEN a valid homepage Preview_Token is used, THE System SHALL render the draft homepage content for the specified locale
3. THE System SHALL support homepage preview for AR and EN independently
4. THE System SHALL bypass public cache for homepage preview requests
5. WHEN a homepage Preview_Token expires, THE System SHALL deny access to draft homepage content


### Requirement 11: Menu Management (PX04)

**User Story:** As an editor, I want to manage primary and utility navigation menus with nesting up to depth 2, so that the site navigation reflects the current information architecture.

#### Acceptance Criteria

1. THE MenuService SHALL support creating, updating, deleting, reordering, and toggling enabled state of menu items
2. IF a menu item is nested beyond depth 2, THEN THE MenuService SHALL reject the operation with a validation error
3. THE MenuService SHALL support page target, custom URL, and external URL target types
4. WHEN an editor requests the primary menu tree, THE MenuService SHALL return a locale-aware ordered tree structure as DTOs
5. WHEN an editor requests the utility menu tree, THE MenuService SHALL return a locale-aware ordered tree structure as DTOs
6. WHEN a menu item is created, updated, reordered, or toggled, THE MenuService SHALL create an Audit_Log entry
7. WHEN a menu item is modified, THE MenuService SHALL invalidate the navigation cache

### Requirement 12: Settings Management (PX04)

**User Story:** As an admin, I want to manage grouped, locale-aware application settings, so that shared shell elements (CTA, portal URLs, emergency notice, footer, social, legal) are configurable without code changes.

#### Acceptance Criteria

1. THE Settings_Service SHALL support grouped and locale-aware settings retrieval for apply CTA, student portal URL, staff access URL, emergency notice, footer contact, footer social, and legal/footer link payloads
2. WHEN an admin updates a setting, THE Settings_Service SHALL invalidate the affected cache groups
3. WHEN an admin updates a setting, THE Settings_Service SHALL create an Audit_Log entry
4. THE Settings_Service SHALL support structured JSON and text settings appropriately per setting type
5. WHEN a setting affects navigation payload, THE Settings_Service SHALL also invalidate the navigation cache

### Requirement 13: Navigation Aggregation (PX04)

**User Story:** As a public visitor, I want consistent, locale-aware navigation across all pages, so that I can navigate the site reliably.

#### Acceptance Criteria

1. THE Navigation_Service SHALL aggregate primary navigation, utility navigation, language-switch metadata, CTA/settings-driven links, emergency notice state, active-state hints, and footer payload into a single structured payload
2. WHEN a visitor is on a specific page, THE Navigation_Service SHALL mark the corresponding navigation item and its parent as active
3. THE Navigation_Service SHALL generate locale-specific URLs for all navigation items
4. THE Navigation_Service SHALL resolve utility navigation items (apply CTA, student portal, staff access) from the Settings_Service
5. WHEN an emergency notice is configured and active, THE Navigation_Service SHALL include the emergency notice content in the navigation payload

### Requirement 14: Footer Shell (PX04)

**User Story:** As a public visitor, I want a consistent, locale-aware footer across all pages, so that I can access contact information, social links, legal links, and secondary navigation.

#### Acceptance Criteria

1. THE Navigation_Service SHALL resolve footer payload including brand/logo references, footer navigation groups, legal links, contact information, social links, and locale-aware footer text
2. THE System SHALL render footer data from service-resolved payloads, not from hardcoded view content
3. WHEN footer-affecting settings change, THE System SHALL invalidate the footer-related cache

### Requirement 15: SEO Metadata Rendering (PX05)

**User Story:** As a search engine crawler, I want correct canonical, hreflang, meta, and OG tags on every public page, so that the site is properly indexed.

#### Acceptance Criteria

1. WHEN the homepage renders, THE SEO_Service SHALL generate an absolute canonical URL for the current locale
2. WHEN a landing page renders, THE SEO_Service SHALL generate an absolute canonical URL for the current locale and slug
3. WHEN a page has an equivalent translation, THE SEO_Service SHALL generate reciprocal hreflang tags for AR and EN
4. THE SEO_Service SHALL render meta title, meta description, OG title, OG description, and OG image from page-specific SEO data
5. WHEN page-specific SEO metadata is incomplete, THE SEO_Service SHALL fall back to settings-backed SEO defaults
6. WHEN a page has a robots directive set, THE SEO_Service SHALL render the robots meta tag with the specified directive

### Requirement 16: Sitemap and Robots (PX05)

**User Story:** As a search engine crawler, I want a valid XML sitemap and robots.txt, so that I can discover and index public pages correctly.

#### Acceptance Criteria

1. WHEN a crawler requests the sitemap endpoint, THE System SHALL return valid XML containing only published, publicly visible page URLs with locale awareness
2. THE System SHALL exclude draft, unpublished, scheduled-not-yet-public, admin, and preview URLs from the sitemap
3. WHEN a crawler requests `/robots.txt`, THE System SHALL return an environment-appropriate response referencing the sitemap location
4. WHILE the application is in a non-production environment, THE System SHALL support configurable noindex behavior in robots.txt

### Requirement 17: Redirect Continuity (PX05)

**User Story:** As a returning visitor following an old bookmark or external link, I want legacy URLs to redirect to the correct current page, so that I do not encounter broken links after the site migration.

#### Acceptance Criteria

1. WHEN a request matches an exact legacy redirect rule, THE Continuity_Layer SHALL respond with the configured HTTP redirect status and destination URL
2. WHEN a request matches a pattern-based legacy redirect rule, THE Continuity_Layer SHALL resolve the destination using the pattern and respond with the configured redirect
3. WHEN a legacy URL request does not match any redirect rule, THE Continuity_Layer SHALL log the unresolved request with URL, query string, method, referrer, resolved locale, timestamp, and whether the request appears file-like or page-like
4. THE Continuity_Layer SHALL never produce redirect loops
5. THE Continuity_Layer SHALL use explicit, deterministic matching priority between exact and pattern-based rules

### Requirement 18: File and Document Continuity (PX05)

**User Story:** As a returning visitor or external system referencing old file URLs, I want legacy file/document paths to resolve to current delivery paths, so that linked PDFs and documents remain accessible.

#### Acceptance Criteria

1. WHEN a request matches a known legacy file path in the file continuity inventory, THE Continuity_Layer SHALL resolve it to the current delivery path or media asset
2. WHEN a legacy file request does not match any continuity mapping, THE Continuity_Layer SHALL log the unresolved file request structurally
3. THE Continuity_Layer SHALL use the existing media/storage architecture for file delivery resolution


### Requirement 19: Admin Homepage Editing UX (PX06)

**User Story:** As an editor, I want a structured admin interface for editing the homepage, so that I can manage all 10 sections with draft/preview/publish/schedule/unpublish workflows.

#### Acceptance Criteria

1. THE Filament admin panel SHALL present the homepage as the fixed 10-section model with structured forms per section
2. THE Filament admin panel SHALL support AR and EN editing separately for each homepage section
3. THE Filament admin panel SHALL provide actions for draft save, preview, publish, schedule, and unpublish that delegate to the HomepagePublishingService
4. WHEN a homepage publish validation fails, THE Filament admin panel SHALL display structured validation errors clearly
5. THE Filament admin panel SHALL display the current homepage state (draft, published, scheduled) clearly

### Requirement 20: Admin Landing Page Editing UX (PX06)

**User Story:** As an editor, I want a structured admin interface for managing landing pages, so that I can edit metadata, translations, SEO, and control publish state per locale.

#### Acceptance Criteria

1. THE Filament admin panel SHALL support editing page metadata including hierarchy/parent assignment, slug, status, and enabled/nav/breadcrumb toggles
2. THE Filament admin panel SHALL support AR and EN translation payload editing with structured field groups
3. THE Filament admin panel SHALL support AR and EN SEO payload editing per page
4. THE Filament admin panel SHALL provide actions for draft save, preview, publish, schedule, and unpublish that delegate to the PageService
5. THE Filament admin panel SHALL rely on real services for all page operations and contain no embedded business logic

### Requirement 21: Admin Menu Builder UX (PX06)

**User Story:** As an editor, I want a menu builder in the admin panel, so that I can manage primary and utility navigation with drag/drop ordering and depth enforcement.

#### Acceptance Criteria

1. THE Filament admin panel SHALL provide a menu builder supporting primary and utility navigation management
2. THE Filament admin panel SHALL enforce maximum nesting depth of 2 and surface validation errors when depth rules are violated
3. THE Filament admin panel SHALL support ordering via drag/drop or deterministic ordering workflow
4. THE Filament admin panel SHALL support page, custom URL, and external URL target types
5. THE Filament admin panel SHALL delegate all menu operations to the MenuService

### Requirement 22: Admin Media Library UX (PX06)

**User Story:** As an editor, I want a media library in the admin panel, so that I can upload, search, edit, and manage media assets.

#### Acceptance Criteria

1. THE Filament admin panel SHALL support file upload through the MediaService
2. THE Filament admin panel SHALL support listing, searching, and filtering media assets
3. THE Filament admin panel SHALL support editing title, alt text, and caption in AR and EN
4. THE Filament admin panel SHALL display media previews and metadata (public URL, file type, dimensions)
5. WHEN an upload fails validation, THE Filament admin panel SHALL surface upload validation errors clearly

### Requirement 23: Admin Settings UX (PX06)

**User Story:** As an admin, I want a settings management interface, so that I can configure utility navigation, footer, emergency notice, contact, social, and SEO defaults through structured forms.

#### Acceptance Criteria

1. THE Filament admin panel SHALL present settings in grouped form sections (utility navigation, footer, emergency notice, contact, social, SEO defaults)
2. THE Filament admin panel SHALL support locale-aware settings editing where relevant
3. THE Filament admin panel SHALL delegate all settings operations to the Settings_Service
4. WHEN settings are updated, THE Filament admin panel SHALL trigger cache invalidation through the Settings_Service

### Requirement 24: Admin User Management UX (PX06)

**User Story:** As a super_admin, I want to manage user accounts, roles, and lock states, so that I can control who has access to the admin panel.

#### Acceptance Criteria

1. WHILE a user has the super_admin role, THE Filament admin panel SHALL allow managing user roles, names, emails, password resets, faculty scope slugs, and lock/unlock states
2. WHILE a user does not have the super_admin role, THE Filament admin panel SHALL deny access to user management
3. THE Filament admin panel SHALL delegate all user operations to the AuthService

### Requirement 25: Admin Audit Log Viewer (PX06)

**User Story:** As a super_admin, I want to view audit logs, so that I can review all admin write operations for accountability and debugging.

#### Acceptance Criteria

1. THE Filament admin panel SHALL provide a read-only audit log viewer filterable by action, entity type, user, and date/time range
2. WHILE a user has the super_admin role, THE Filament admin panel SHALL allow access to the audit log viewer
3. THE Filament admin panel SHALL display audit log entries from the existing Audit_Log data

### Requirement 26: Role-Based Admin Visibility (PX06)

**User Story:** As a system administrator, I want role-based visibility in the admin panel, so that users only see resources and actions they are authorized to access.

#### Acceptance Criteria

1. WHILE a user has the super_admin role, THE Filament admin panel SHALL display all foundation admin areas
2. WHILE a user has the editor role, THE Filament admin panel SHALL display only homepage, pages, menu, media, and settings within allowed scope
3. WHILE a user has the faculty_editor role, THE Filament admin panel SHALL display only scoped content areas restricted by faculty_scope_slug
4. THE Filament admin panel SHALL use Gates and Policies for authorization checks, not inline role string comparisons

### Requirement 27: Migration Backfill Tooling (PX07)

**User Story:** As a migration engineer, I want repeatable CLI tooling for URL inventory export, redirect validation, file continuity inventory, and reconciliation reporting, so that I can prepare for cutover without manual SQL workflows.

#### Acceptance Criteria

1. WHEN a migration engineer runs the URL inventory export command, THE System SHALL produce a machine-readable (JSON/CSV) list of legacy public URL candidates with source type, legacy path, expected destination, locale, and status classification
2. WHEN a migration engineer runs the redirect validation command, THE System SHALL detect and report invalid, duplicate, or conflicting redirect rules before they are imported
3. WHEN a migration engineer runs the file continuity inventory command, THE System SHALL produce a machine-readable report of legacy file/document continuity state showing mapped versus unmapped files
4. WHEN a migration engineer runs the unresolved continuity report command, THE System SHALL produce a structured report of unresolved URL and file continuity issues
5. WHEN a migration engineer runs the imported page SEO validation command, THE System SHALL identify imported pages with weak, incomplete, or missing SEO metadata and continuity mappings
6. WHEN a migration engineer runs the reconciliation report command, THE System SHALL identify ambiguous or overlapping legacy structures requiring engineering review
7. THE System SHALL reuse and extend existing import audit/export commands (`legacy-import:report`, `legacy-import:verify`, `legacy-import:audit`, `legacy-import:export-missing`) where appropriate

### Requirement 28: Auth and Account Security (Cross-Phase)

**User Story:** As a system administrator, I want secure authentication with account lockout protection, so that brute-force attacks are mitigated.

#### Acceptance Criteria

1. WHEN a user fails 5 login attempts, THE AuthService SHALL lock the account
2. WHILE an account is locked, THE AuthService SHALL deny login even with correct credentials
3. THE System SHALL use bcrypt/Laravel hashing for all passwords
4. THE System SHALL enforce role-based access through Gates and Policies for all admin operations
5. WHEN a user logs in, logs out, fails login, or is locked, THE AuditService SHALL create an Audit_Log entry

### Requirement 29: Cache Strategy (Cross-Phase)

**User Story:** As a system operator, I want locale-aware public page caching with targeted invalidation, so that the site performs well while always serving current content.

#### Acceptance Criteria

1. THE Cache_Service SHALL include locale in all public page cache keys
2. WHILE a user is authenticated, THE Cache_Service SHALL bypass public page cache
3. WHILE a request targets an admin route, THE Cache_Service SHALL bypass public page cache
4. WHILE a request includes a Preview_Token, THE Cache_Service SHALL bypass public page cache
5. WHILE a request uses a non-GET HTTP method, THE Cache_Service SHALL bypass public page cache
6. WHEN content is published, updated, or deleted, THE Cache_Service SHALL invalidate only the affected cache entries
7. THE System SHALL include `X-Cache: HIT`, `MISS`, or `BYPASS` headers on public responses

### Requirement 30: Audit Logging (Cross-Phase)

**User Story:** As a system administrator, I want comprehensive audit logging for all admin write operations, so that I can track changes for accountability and debugging.

#### Acceptance Criteria

1. WHEN any admin write operation occurs (homepage update, draft save, publish, schedule, unpublish, page CRUD, menu CRUD, media upload/update/delete, settings update, user management), THE AuditService SHALL create an append-only Audit_Log entry
2. THE Audit_Log entry SHALL record the action type, entity type, entity ID, performing user, timestamp, and metadata JSON
3. THE Audit_Log metadata SHALL never contain passwords, tokens, or credentials
4. THE System SHALL limit IP address logging to auth-related events only

### Requirement 31: Architecture Compliance (Cross-Phase)

**User Story:** As a development lead, I want strict enforcement of the Service Layer architecture, so that the codebase remains maintainable and testable.

#### Acceptance Criteria

1. THE System SHALL place all business logic exclusively in the Service_Layer
2. THE System SHALL ensure all controllers inject Contract interfaces only and never import or query Eloquent models directly
3. THE System SHALL ensure all public service methods return DTOs, Collections of DTOs, booleans, or structured arrays for composite payloads — never raw Eloquent models
4. THE System SHALL ensure all models contain only relationships, scopes, casts, and simple accessors
5. THE System SHALL use constructor injection for all service dependencies and never instantiate services using `new`

### Requirement 32: Multilingual Support (Cross-Phase)

**User Story:** As a bilingual visitor, I want all managed content available in Arabic (RTL) and English (LTR), so that I can use the site in my preferred language.

#### Acceptance Criteria

1. THE System SHALL use `ar` as the default locale and `en` as the secondary locale
2. THE System SHALL use explicit locale-specific storage tables (`page_translations`, `homepage_section_translations`, `page_seo_meta`) rather than a global polymorphic translation model
3. WHEN rendering Arabic content, THE System SHALL set text direction to RTL
4. WHEN rendering English content, THE System SHALL set text direction to LTR
5. THE System SHALL support independent AR and EN content for all CMS-managed areas

### Requirement 33: Hardening and Test Coverage (PX08)

**User Story:** As a development lead, I want comprehensive test coverage for launch-critical behaviors, so that migration rehearsal and go/no-go decisions are evidence-based.

#### Acceptance Criteria

1. THE System SHALL include feature tests covering locale middleware, content-language headers, admin auth redirect, account lockout, role restrictions, homepage render from published data, homepage draft invisibility, scheduled homepage invisibility, preview token behavior, landing-page render, landing-page draft invisibility, breadcrumb generation, language-switch preservation, canonical rendering, hreflang rendering, sitemap output, robots.txt output, redirect continuity, unresolved request logging, file continuity, menu depth enforcement, navigation/settings payload correctness, cache invalidation, and audit logging
2. THE System SHALL include unit tests for slug uniqueness, canonical URL generation, hreflang reciprocity, page visibility state rules, homepage publish readiness validation, menu depth validation, redirect rule matching priority, preview token expiry logic, and settings payload resolution
3. WHEN all launch-critical tests pass, THE System SHALL produce a launch-readiness checklist covering routing, locale, SEO, continuity, file/media, admin, cache, audit, staging noindex, and rollback readiness

### Requirement 34: Launch Validation and Rollback Preparation (PX08)

**User Story:** As a system operator, I want repeatable launch validation tooling and explicit rollback preparation, so that migration cutover can be executed and reverted safely.

#### Acceptance Criteria

1. THE System SHALL provide repeatable validation tooling that checks homepage rendering, landing-page rendering, AR/EN locale correctness, canonical/hreflang correctness, sitemap presence, robots.txt correctness, representative redirect continuity, representative file continuity, admin preview safety, cache behavior, and audit behavior
2. THE System SHALL document rollback threshold definitions, cutover abort criteria, pre-cutover snapshot expectations, continuity rollback expectations, and unresolved continuity spike monitoring
3. THE System SHALL provide cache warm tooling for homepage AR/EN, top-level landing pages AR/EN, shared navigation/settings payloads, and sitemap output
