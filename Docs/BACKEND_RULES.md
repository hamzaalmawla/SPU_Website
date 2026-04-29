# BACKEND_RULES.md

## Laravel Conventions

- Laravel 11+ structure only
- Use FormRequest for validation
- Use `bootstrap/app.php` for middleware registration
- Do not create `app/Http/Kernel.php`
- Use predictable route names for locale-aware public routes and admin routes
- No enums in DB — use varchar with app-level validation only

---

## Scope Rule

These backend rules apply only to the current foundation scope:

- homepage
- navigation shell
- generic landing-page builder
- admin panel / CMS foundation
- settings
- menu builder
- media library
- preview / publish flow
- audit logging

Do not use this file to justify building the full SPU site in this sprint.

---

## Interface Rules

- All interfaces live in `app/Contracts/`
- Naming: `SomethingServiceInterface`
- Method return types:
  - Single entity → DTO or null
  - Collection → Collection (PHPDoc must specify DTO type)
  - Write operations → bool
  - Paginated → LengthAwarePaginator only if truly needed
  - Composite homepage / navigation / builder payloads → array where appropriate
  - Never → Eloquent model
  - Never → `array<string,mixed>` for entity data when a DTO is appropriate
- ID parameters → `int|string` (never Eloquent model type-hints)

---

## DTO Rules

- Located in `app/DTOs/`
- PHP 8.2 `final readonly` classes
- No methods, no logic
- One DTO per entity / payload type where useful
- Constructed inside service mapping methods or dedicated service-layer factories only

Required DTOs for this foundation scope:
- HomepageSectionDTO
- HomepageDraftDTO
- PageDTO
- PageSeoDTO
- MenuItemDTO
- NavigationTreeDTO
- MediaUploadResultDTO
- SettingsDTO
- PreviewDTO
- ArticleCardDTO
- ResearchCardDTO
- EventCardDTO

---

## Service Rules

- Located in `app/Services/`
- Must implement their interface from `app/Contracts/`
- Must use constructor injection
- No static methods
- All public methods return DTOs, Collections, bool, scalar types, or approved composite arrays
- Must not return raw Eloquent models publicly
- Are the primary layer allowed to import Eloquent models
- All interfaces must be bound in `AppServiceProvider`

---

## Controller Rules

- Inject interfaces only
- No DB queries
- No business logic
- No validation logic inline — use FormRequest where needed
- No Eloquent model imports in controller logic
- Pass DTOs or structured service payloads to views / JSON responses

---

## Model Rules

- Use SoftDeletes where required by the schema
- Define relationships
- Use `$casts` for:
  - datetime fields
  - boolean fields
  - JSON fields → array
- Support only lightweight helpers and scopes
- No business logic
- Controllers must not use models directly

Examples of acceptable model helpers in this scope:
- `Page::translation(string $locale)`
- `Page::seoFor(string $locale)`
- `HomepageSection::translation(string $locale)`
- `scopeDraft()`, `scopeScheduled()`, `scopePublished()`, `scopeEnabled()` where relevant

Not acceptable:
- publish workflows in models
- cache invalidation in models
- preview-token issuance in models
- SEO generation in models

---

## Migration Rules

- No enum columns — varchar + app-level validation
- Use `json()` for JSON fields
- Use unsigned bigint foreign keys
- Use bigint auto-increment primary keys only
- Add indexes where appropriate on:
  - slug
  - locale
  - status
  - publish_at
  - sort_order
  - is_enabled
  - parent_id
  - expires_at
- Soft deletes where required, typically:
  - users
  - pages
  - media_assets
- `audit_logs` has `created_at` only — no `updated_at`

---

## Auth Rules

- 5 failed attempts → lock account
- Locked accounts cannot log in even with correct password
- Role-based access enforced via Gates and Policies
- 3 roles:
  - super_admin
  - editor
  - faculty_editor
- `faculty_editor` is scoped according to the ownership field used by this foundation schema, currently `faculty_scope_slug`
- MFA is future-phase only, not required in this sprint implementation
- Passwords use bcrypt / Laravel hashing
- Sessions use secure framework defaults

---

## Cache Rules

- Use Redis tagged cache where supported
- Cache public pages only
- Bypass cache for:
  - authenticated users
  - admin routes
  - preview routes / preview tokens
  - non-GET requests
- Cache keys must include locale
- Recommended TTLs:
  - homepage: 1 hour
  - landing pages: 1 hour
  - navigation: 4 hours
  - settings: 6 hours
  - SEO/page shell payloads: aligned with page cache
- Invalidate immediately on publish / update / delete where public output changes
- `X-Cache: HIT / MISS / BYPASS` headers on public responses
- Never hardcode Redis connection values

---

## Search Rules

- Search is optional and minimal in this sprint
- Do not require full-site Meilisearch indexing yet
- Never index draft or unpublished content
- Only introduce Scout / Meilisearch now if the homepage/nav shell truly needs it

---

## CMS Rules

- All homepage and landing-page content must be CMS-driven
- No hardcoded UI content for homepage sections
- Homepage uses these fixed keys only:
  - hero
  - hero_stats
  - academic_faculties
  - achievements_highlights
  - choose_your_path
  - university_news
  - research_studies
  - events_activities
  - medical_facilities_services
  - bottom_stats
  - footer
- Draft / scheduled / published / unpublished workflow supported
- Publish validation required before public release
- Preview must not expose draft content publicly
- No placeholder live pages

---

## Forms

- Always use FormRequest for validation
- Use honeypot only on public-facing forms that actually exist in this foundation scope
- Honeypot-filled submissions return silent 200 with no DB write
- Lead capture is optional and only applies if that feature remains in scope
- If lead capture is implemented, UTM parameters must be stored

---

## Audit Logging

- Every admin write operation creates an `audit_logs` row
- Audit actions should cover at minimum:
  - user.login
  - user.logout
  - user.login_failed
  - user.locked
  - homepage.section_updated
  - homepage.draft_saved
  - homepage.publish
  - homepage.schedule
  - homepage.unpublish
  - page.created
  - page.updated
  - page.publish
  - page.schedule
  - page.unpublish
  - menu.created
  - menu.updated
  - menu.reordered
  - menu.toggled
  - media.upload
  - media.updated
  - media.delete
  - settings.update
- Audit logs are append-only
- Metadata JSON must never contain passwords, tokens, or credentials
- IP address logging should be limited to auth-related events
- Retention policy may be enforced by scheduler if the project keeps that requirement
