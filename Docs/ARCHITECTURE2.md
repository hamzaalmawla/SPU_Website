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

The homepage is a fixed 10-section CMS page.

Approved homepage section keys:
- hero
- hero_stats
- academic_faculties
- achievements_highlights
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
