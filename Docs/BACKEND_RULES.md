# BACKEND_RULES.md

## Laravel Conventions

- Laravel 11+ structure only
- Use FormRequest for validation
- Use bootstrap/app.php for middleware (no Kernel.php)
- Use route names: {locale}.{resource}.{action}
- No enums in DB — varchar with app-level validation only

---

## Interface Rules

- All interfaces live in app/Contracts/
- Naming: SomethingServiceInterface
- Method return types:
  - Single entity → DTO or null
  - Collection → Collection (PHPDoc must specify DTO type)
  - Write operations → bool
  - Paginated → LengthAwarePaginator
  - Never → array<string,mixed> for entity data
  - Never → Eloquent model
- ID parameters → int|string (never Eloquent model type-hints)

---

## DTO Rules

- Located in app/DTOs/
- PHP 8.2 readonly classes
- No methods, no logic
- One DTO per entity type
- Constructed only inside service toDTO() methods

---

## Service Rules

- Located in app/Services/
- Must implement their interface from app/Contracts/
- Must use constructor injection (never new SomeService())
- No static methods
- All public methods return DTOs, Collections, bool, or scalar types
- Must include private toDTO(Model $model): DTO mapping method
- Are the ONLY layer allowed to import Eloquent models
- All interfaces bound in AppServiceProvider

---

## Controller Rules

- Inject interfaces only (never concrete classes, never models)
- No DB queries
- No business logic
- No validation logic (use FormRequest)
- No Eloquent model imports
- Pass DTOs directly to views or serialize to JSON response

---

## Model Rules

- Use SoftDeletes where required
- Define relationships
- Use $casts for:
  - datetime fields
  - boolean fields
  - JSON fields → array
- translate(string $key, string $locale): string helper method
- setTranslation(string $key, string $locale, string $value): void helper
- scopeActive(), scopePublished(), scopeUpcoming() where applicable
- NO business logic
- NEVER used outside app/Services/

---

## Migration Rules

- No enum columns — varchar + app-level validation
- Use json() for JSON fields (not text())
- Use unsignedBigInteger for all FK columns
- Use bigint auto-increment PKs (no UUIDs)
- Add indexes on: slug, locale, status, published_at, sort_order, translatable_id
- softDeletes() on: Faculty, Department, Staff, Article, Event, User
- audit_logs has created_at only — no updated_at

---

## Auth Rules

- 5 failed attempts → lock account
- Locked accounts cannot login even with correct password
- Role-based access enforced via Gates and Policies
- 3 roles: super_admin, editor, faculty_editor
- faculty_editor scoped to own faculty_id in ALL queries
- MFA required on all admin accounts (P2 implementation)
- Passwords: bcrypt hashing — no plaintext ever stored or logged
- Sessions: HttpOnly + Secure cookie flags

---

## Cache Rules

- Use Redis tagged cache
- Cache public pages only — bypass for authenticated users
- Cache keys must include locale: spu_homepage_ar, spu_menu_en
- TTLs by content type:
  - Homepage sections: 1 hour
  - Faculty/Department/Staff: 2 hours
  - News/Events index: 30 minutes
  - Menu tree: 4 hours
  - Settings: 6 hours
  - Search results: 10 minutes
- Invalidate immediately on publish/update/delete
- X-Cache: HIT / MISS / BYPASS headers on all public responses
- Never hardcode Redis connection — use env vars

---

## Search Rules

- Use Meilisearch via Laravel Scout
- Index only published/active content (shouldBeSearchable())
- Index name pattern: {model_plural}_{locale} e.g. articles_ar, events_en
- Each model indexed into both ar and en indexes separately
- Stop words configured per locale
- Never index draft content

---

## CMS Rules

- All content must be CMS-driven — no hardcoded UI data
- Homepage sections: 10 fixed keys, all CMS-controlled
- Draft → Review → Approve → Publish workflow enforced
- Scheduled publishing supported on all content types
- No placeholder pages on live site

---

## Forms

- Always use FormRequest for validation
- Honeypot required on all public-facing forms
- Silent 200 response for honeypot-filled submissions (no DB write, no error)
- Contact form: dual delivery (email + admin dashboard notification)
- Lead capture: UTM source parameters stored per submission

---

## Audit Logging

- Every admin write operation creates an audit_logs row
- Actions logged: content.create, content.update, content.publish,
  content.unpublish, content.delete, user.login, user.logout,
  user.login_failed, user.locked, homepage.publish, homepage.update,
  media.upload, media.delete, menu.update, settings.update
- Audit logs are append-only — never updated by application code
- metadata JSON must NEVER contain passwords, tokens, or credentials
- IP address logged for auth events only
- Retention: 90 days — auto-purged by scheduler at 03:00 daily
