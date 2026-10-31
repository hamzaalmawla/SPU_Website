# ARCHITECTURE.md

## Architecture Pattern
MVC + Service Layer

Flow:
Request → Middleware → Controller → Service (returns DTO / structured payload) → Model → Database → Response

---

## Scope of This Architecture

This architecture file applies only to the current backend foundation scope:

- public homepage
- public navigation shell
- generic bilingual landing-page shell
- admin panel / CMS foundation
- homepage draft / preview / publish workflow
- settings, menu builder, media library, audit logging

This architecture file does **not** define the full SPU website domain model.

Out of scope here:
- full Facilities module
- full Research repository
- full News module
- full Events module
- full Admissions module
- full Contact / CRM system beyond optional foundation-level lead capture

---

## Layer Responsibilities

### Middleware
Handles:
- Locale (ar / en)
- Auth
- Role
- CSRF
- Throttle
- Cache

Pipeline order:
1. Locale
2. Auth
3. Role
4. CSRF
5. Throttle
6. Cache

Middleware must not contain business logic or Eloquent queries.

---

### Controllers

Controllers MUST:
- Use dependency injection with interfaces only
- Call services and receive DTOs or structured service payloads
- Pass DTOs / payloads to views or serialize them to JSON
- NEVER contain business logic
- NEVER import or type-hint Eloquent models directly
- NEVER call Eloquent queries

Controllers in this foundation scope are orchestration-only.

---

### Services

Services:
- Contain ALL business logic
- Are injected via interfaces only
- May depend on other services via constructor injection
- Are the primary layer allowed to import and use Eloquent models
- Map Eloquent models to DTOs via private `toDTO()` methods where entity mapping is needed
- NEVER return raw Eloquent models from public methods

Public service methods may return:
- DTO
- null
- Collection of DTOs
- LengthAwarePaginator where appropriate
- bool for write operations
- array for composite page/home/navigation payloads only

---

### Service-Layer Actions And Internal Collaborators

Action-style classes are allowed only as internal service-layer collaborators when a service method has become too large to maintain safely.

They do not create a new application layer. The architectural flow remains:

Request → Middleware → Controller → Service Interface → Service Implementation → Internal service-layer collaborator → Model / Database

Rules:
- Controllers must never inject or call Action classes directly.
- Filament pages/resources must never inject or call Action classes directly.
- Public service interfaces remain the only entry point for controllers and higher layers.
- Actions live under `app/Actions/` only after this pattern is explicitly needed for business workflows.
- Actions are owned by services and may be injected into service implementations.
- Actions may use Eloquent because they are service-layer collaborators.
- Actions must not return raw Eloquent models from public methods.
- Actions must return DTOs, bool, scalar values, or typed value objects compatible with the owning service contract.
- Actions must have one clear workflow responsibility, for example publishing a resolved page draft.
- Do not create Actions for simple mapping, formatting, or array normalization.

Pure transformation helpers belong in `app/Support/` instead of `app/Actions/`.

Support helper rules:
- They may normalize arrays, map DTO payloads, sanitize URLs through existing support utilities, or preserve deterministic fallback behavior.
- They must not write to the database.
- They must not perform authorization, cache invalidation, audit logging, or publishing workflow decisions.
- They may be static when they are dependency-free and deterministic, following the existing `HomepagePayloadMapper` pattern.
- They must have characterization tests before replacing existing service-private behavior.

Legacy import exception:
- `app/Support/LegacyImport/**` is a narrow historical migration exception because it is used by one-off legacy import seeders and import verification commands, not public runtime controllers, Filament workflows, or service contracts.
- The only approved database-aware helpers in that namespace are `OldDatabaseConnection`, `TargetIdResolver`, and `MigrationLogger`.
- This exception must not be used as precedent for new Support helpers. New database access belongs in services or service-owned collaborators.
- No controller, Filament page/resource, middleware, or public service contract may depend on `app/Support/LegacyImport/**`.
- If legacy import tooling becomes a maintained product workflow, move persistence into explicit legacy import services/contracts and remove this exception.

Extraction requirements:
- Measure the current service hotspot before extracting.
- Add characterization tests for current behavior before moving code.
- Extract one responsibility at a time.
- Keep the owning service public interface unchanged unless a separate approved task changes it.
- Run architecture guard and targeted workflow tests after extraction.

---

### DTOs (Data Transfer Objects)

Located in `app/DTOs/`.

All DTOs are PHP 8.2 `final readonly` classes with no logic.

Return type rules:
- Single entity return → DTO or null
- Collection return → Collection with PHPDoc DTO type annotation
- Write operations → bool
- Composite view payloads (homepage, navigation shell) → array only where appropriate
- Never return raw Eloquent models from any interface method
- Never return `array<string,mixed>` for entity data when a DTO is appropriate

Required DTOs for this scope:
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

DTO mapping convention — every service implementation uses a private mapper when mapping entities:

```php
private function toDTO(Page $model, string $locale): PageDTO
{
    $translation = $model->translation($locale);

    return new PageDTO(
        id: $model->id,
        slug: $model->slug,
        locale: $locale,
        title: $translation?->title ?? '',
        status: $model->status,
        isEnabled: (bool) $model->is_enabled,
    );
}
```

This mapping method is the only place model → DTO conversion happens for entity DTOs.

---

### Models

Models:
- Represent DB tables
- Define relationships
- Support locale-specific content through dedicated translation tables where applicable
- Define casts, scopes, and lightweight helpers only
- Contain NO business logic
- Must not be used directly by controllers

Primary models in this scope include:
- Role
- User
- Page
- PageTranslation
- PageSeoMeta
- MenuItem
- Setting
- MediaAsset
- AuditLog
- HomepageSection
- HomepageSectionTranslation
- HomepageDraft
- PreviewToken
- LeadCapture (only if lead capture remains in scope)

---

## Translation Strategy

This foundation does **not** use the old global polymorphic translation model.

Instead, translations are handled through explicit locale-specific tables:

- `page_translations`
- `homepage_section_translations`
- `page_seo_meta` (locale-specific SEO metadata)

Translation access is model-specific, for example:
- `Page::translation(string $locale)`
- `Page::seoFor(string $locale)`
- `HomepageSection::translation(string $locale)`

AR and EN content are stored independently.

---

## Homepage CMS

The homepage is a fixed 11-section CMS page.

Approved homepage section keys:
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

Rules:
- all homepage section content is CMS-controlled
- no hardcoded homepage section data
- section key set is fixed
- supports draft / scheduled / published / unpublished workflow
- preview must not leak draft content publicly
- publishing validates required payloads
- publishing writes homepage section translation data
- publishing invalidates homepage cache
- publishing creates audit log entries

---

## Landing-Page Builder

The generic landing-page builder powers the public shell for top-level section pages such as:
- /about
- /admissions
- /facilities
- /research
- /campus-life
- /e-services
- /news
- /contact

This layer supports:
- AR / EN content stored independently
- draft / scheduled / published / unpublished states
- preview workflow
- breadcrumbs
- locale-specific SEO metadata
- structured content payloads for hero, cards, rich content, CTA blocks, tables, embeds, and sidebar data

It is a generic page-builder layer, not the full module implementation for each section.

---

## Interface Contracts

All service interfaces live in `app/Contracts/`.

Method signature rules:
- IDs use `int|string` where appropriate
- single entity returns use DTO or null
- multi-entity returns use `Collection` with PHPDoc DTO type
- paginated results use `LengthAwarePaginator` only where truly needed
- write operations return bool
- composite homepage / navigation payloads may return array
- interfaces must not type-hint Eloquent models
- interfaces must not return Eloquent models

Primary interfaces in this scope:
- AuthServiceInterface
- AuditServiceInterface
- CacheServiceInterface
- MediaServiceInterface
- MenuServiceInterface
- NavigationServiceInterface
- SlugServiceInterface
- SeoMetadataServiceInterface
- SettingsServiceInterface
- PageServiceInterface
- HomepageSectionServiceInterface
- HomepagePublishingServiceInterface
- PreviewServiceInterface

---

## Critical Rules

1. If logic is not in a service, it is wrong.
2. If a controller imports or uses an Eloquent model directly, it is wrong.
3. If an interface returns a raw model, it is wrong.
4. If an interface type-hints an Eloquent model, it is wrong.
5. If old homepage block names appear in new homepage CMS code, it is wrong.
6. If draft content can be accessed publicly without preview flow, it is wrong.
