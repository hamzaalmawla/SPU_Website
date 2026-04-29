# AGENTS.md

## Project
Syrian Private University Website (spu.edu.sy)

## Current Build Scope
This repository is currently focused on the **homepage + admin panel foundation only**.

In scope:
- public homepage
- public navigation shell
- generic bilingual landing-page builder
- admin panel / CMS foundation
- homepage draft / preview / publish workflow
- menu builder
- settings
- media library
- audit logging
- AR / EN support
- RTL / LTR support

Out of scope for this phase:
- full Facilities module
- full Research repository
- full News module
- full Events module
- full Admissions module
- full Contact / CRM system beyond optional foundation-level lead capture

---

## Stack
- Laravel 12 project, following Laravel 11+ structure conventions
- PHP 8.2+
- MySQL 8
- Redis
- Filament v3
- Meilisearch optional in this phase only if minimal search is actually needed

---

## Source of Truth
- Requirements: **SPU_Requirements_v5_1**
- Architecture: MVC + Service Layer
- Supporting rules:
  - ARCHITECTURE.md
  - BACKEND_RULES.md
  - STYLEGUIDE.md
  - WORKFLOW.md

If any older prompt, document, or generated code conflicts with v5.1 scope, v5.1 wins.

---

## Core Architecture Rules (NON-NEGOTIABLE)

1. Service Layer is the ONLY place for business logic
2. Controllers must be thin
3. Models contain ONLY:
   - relationships
   - scopes
   - casts
   - simple accessors / lightweight helpers
4. All dependencies must use interfaces in `app/Contracts`
5. NEVER instantiate services using `new`
6. Use DTOs for structured entity data
7. All public methods must have typed signatures
8. Public service methods must NEVER return raw Eloquent models
9. Controllers must NEVER import or query Eloquent models directly
10. If logic is not in a service, it is wrong

---

## Laravel Structure Rules (CRITICAL)

- Use Laravel 11+ application structure
- DO NOT generate `app/Http/Kernel.php`
- Middleware must be registered in `bootstrap/app.php`
- Use modern service providers
- Use FormRequest for validation where needed
- Use Policies / Gates for authorization
- Use constructor injection for all service dependencies
- Use interfaces only in controllers and higher layers

---

## AI GENERATION RULES

When generating code:

- NEVER move business logic into controllers or models
- NEVER invent database columns
- NEVER assume missing relationships
- ALWAYS respect interfaces
- ALWAYS generate full imports and namespaces
- ALWAYS follow DTO return rules
- ALWAYS validate against acceptance criteria
- ALWAYS keep output within the current sprint scope
- NEVER silently expand into full-site architecture when the task is foundation-only

---

## Multilingual Rules (CRITICAL)

- Default locale: `ar`
- Secondary locale: `en`
- All managed content must support AR / EN
- Arabic UI/content must support RTL
- English UI/content must support LTR
- Do NOT use the old global polymorphic translation model in this phase
- Use explicit locale-specific storage where applicable:
  - `page_translations`
  - `homepage_section_translations`
  - `page_seo_meta`
- No hardcoded translatable content in CMS-managed areas

---

## Role System

Roles:
- `super_admin` → full access
- `editor` → homepage / pages / menu / media / settings within allowed scope
- `faculty_editor` → scoped content access only, using the ownership model defined by the current schema (`faculty_scope_slug` if applicable)

Rules:
- Never bypass role checks
- Use Gates / Policies
- Locked accounts cannot log in
- 5 failed attempts must lock the account

---

## Homepage Rules

The homepage is a fixed 11-section CMS page.

Approved homepage section keys:
- `hero`
- `hero_stats`
- `academic_faculties`
- `achievements_highlights`
- `choose_your_path`
- `university_news`
- `research_studies`
- `events_activities`
- `medical_facilities_services`
- `bottom_stats`
- `footer`

Rules:
- Homepage content must be CMS-driven
- No hardcoded homepage section content
- Do not use old homepage block names
- Draft content must never leak publicly
- Publish must validate required content
- Preview must be tokenized or otherwise protected
- Homepage publish must invalidate relevant cache and create audit logs

---

## Data / DTO Rules

- DTOs live in `app/DTOs`
- Interfaces live in `app/Contracts`
- Services live in `app/Services`
- All DTOs should be PHP 8.2 `final readonly` classes where applicable
- Use DTOs for single-entity returns
- Use `Collection` with PHPDoc DTO type for multiple entities
- Use `bool` for write operations
- Use structured arrays only for composite view payloads like homepage or navigation shells
- Never return Eloquent models from interface methods

---

## Cache / Publish Rules

- Public page cache must include locale awareness
- Bypass public cache for:
  - authenticated users
  - admin routes
  - preview flows
  - non-GET requests
- Invalidate relevant cache on publish / update / delete
- Draft and scheduled content must not become public before intended time

---

## Forbidden

- ❌ Business logic in controllers
- ❌ Business logic in models
- ❌ Direct DB queries outside services
- ❌ Hardcoded CMS content
- ❌ Using concrete classes instead of interfaces in controller-level code
- ❌ Returning raw models from public service methods
- ❌ Old homepage keys in new homepage code
- ❌ Reintroducing the old polymorphic translation model for this foundation scope
- ❌ Drifting into full-site implementation when the task is homepage/admin foundation only

---

## Definition of Done

A task is complete ONLY if:

- Code compiles and Laravel commands run
- Interfaces resolve via the container
- No architecture violations exist
- Acceptance criteria are satisfied
- No missing imports or classes remain
- Scope has not drifted beyond the assigned prompt/task
- No raw model returns exist in public service interfaces
- No draft content is publicly exposed
