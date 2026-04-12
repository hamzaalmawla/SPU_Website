# ARCHITECTURE.md

## Architecture Pattern
MVC + Service Layer

Flow:
Request → Middleware → Controller → Service (returns DTO) → Model → Database → Response

---

## Layer Responsibilities

### Middleware
Handles:
- Locale (ar/en)
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

---

### Controllers

Controllers MUST:
- Use dependency injection (interfaces only, never concrete classes)
- Call services and receive DTOs
- Pass DTOs to views or serialize to JSON
- NEVER contain business logic
- NEVER import or type-hint Eloquent models directly
- NEVER call Eloquent queries

---

### Services

Services:
- Contain ALL business logic
- Are injected via interfaces (never instantiated with new())
- Can depend on other services via constructor injection
- Are the ONLY layer that imports and uses Eloquent models
- Map Eloquent models to DTOs via a private toDTO() method
- NEVER return raw Eloquent models from public methods

---

### DTOs (Data Transfer Objects)

Located in app/DTOs/. All are PHP 8.2 readonly classes with no logic.

Return type rules:
- Single entity return → DTO or null
- Collection return → Collection with PHPDoc DTO type annotation
- Write operations (create/update/delete/publish) → bool
- Never return array<string,mixed> for entity data
- Never return raw Eloquent models from any interface method

Required DTOs:
- FacultyDTO
- DepartmentDTO
- StaffDTO
- ArticleDTO
- EventDTO
- MenuItemDTO
- HomepageSectionDTO
- SearchResultDTO
- MediaUploadResultDTO
- ContactSubmissionDTO
- LeadCaptureDTO

DTO mapping convention — every service implementation has:

```php
private function toDTO(ModelClass $model): ModelDTO
{
    return new ModelDTO(
        id: $model->id,
        // ... map all fields here
    );
}
```

This mapping method is the only place Model→DTO conversion happens.

---

### Models

Models:
- Represent DB tables
- Define relationships
- Support translations via polymorphic Translation model
- NO business logic allowed
- NEVER used outside app/Services/

---

## Translation System

Polymorphic Translation model:
- translatable_type
- translatable_id
- locale
- key
- value

---

## Homepage CMS

- 10 fixed section keys: hero, audience_paths, trust_panel, stats, programs,
  featured_news, featured_events, clinic_strip, milestone_quote, footer
- CMS controlled — no hardcoded section data
- Supports draft + publish workflow
- Publishing:
  - validates draft
  - writes HomepageSection + HomepageSectionTranslation rows
  - invalidates Redis cache (homepage tag group)
  - creates AuditLog entry

---

## Interface Contracts

All service interfaces live in app/Contracts/.
Method signatures use:
- int|string for IDs (never Eloquent model types)
- DTOs for single entity returns
- Collection for multi-entity returns (PHPDoc specifies DTO type)
- bool for write operations
- Carbon for datetime parameters
- LengthAwarePaginator for paginated results

---

## Critical Rules

1. If logic is not in a service → it is WRONG.
2. If an Eloquent model is used outside app/Services/ → it is WRONG.
3. If a controller returns a raw model or array<string,mixed> → it is WRONG.
4. If an interface type-hints an Eloquent model → it is WRONG.
