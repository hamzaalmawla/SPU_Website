# Copilot Instructions - SPU Website Backend

## Current Guidance Sources

This file is active lightweight guidance only. If it conflicts with any of the following files, those files win:

- `AGENTS.md`
- `Docs/ARCHITECTURE.md`
- `enhance_todo.md`

Do not use `.kiro/specs/**` as active governance; those files are historical planning artifacts.

## Project Stack

Laravel 12, Filament v3, MySQL 8, Redis, optional Meilisearch, PHP 8.2+, PHPUnit 11, Larastan/PHPStan.

## Architecture Rules

### Layer Boundaries

- Controllers must inject interfaces from `app/Contracts` and must not import/query Eloquent models.
- Business logic belongs in services or service-owned collaborators.
- Models are limited to relationships, scopes, casts, simple accessors, and lightweight helpers.
- Contracts must not import Eloquent model types or return raw Eloquent models.
- Filament resources may use Eloquent for normal Filament CRUD mechanics, including `$model` declarations and resource queries.
- Filament business workflows such as publish, preview, settings, menu, media, and account changes must delegate to service interfaces.
- DTOs should be `final readonly` data carriers unless an exception is explicitly documented.

### Homepage And Locale Rules

- The homepage is a fixed 11-section CMS page.
- Approved section keys: `hero`, `hero_stats`, `academic_faculties`, `achievements_highlights`, `choose_your_path`, `university_news`, `research_studies`, `events_activities`, `medical_facilities_services`, `bottom_stats`, `footer`.
- Default locale is `ar`; secondary locale is `en`.
- CMS-managed content must support AR/EN and RTL/LTR behavior.

### Return Type Rules

- Single-entity public service returns should use DTOs or `null`.
- Multiple-entity public service returns should use typed collections or paginators with DTO payloads.
- Write operations should return `bool` unless an approved result DTO is introduced.
- Composite view payload arrays are allowed for homepage/navigation shells when documented with PHPDoc shapes.

### Never Acceptable

- Business logic in controllers or models.
- Controller-level concrete `App\Services` injection.
- Direct DB queries in controllers.
- Public service interfaces returning raw Eloquent models.
- Old homepage keys or 10-section homepage assumptions.
- Reintroducing the old global polymorphic translation model.
