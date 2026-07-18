# AGENTS.md

## Project
Syrian Private University Website (spu.edu.sy)

## Current Build Scope
This repository is in **full-site completion and production-readiness scope**.

In scope:
- complete migration of every approved page and feature from the reference frontend
- public homepage and navigation shell
- About, Admissions, Campus Life, Contact, E-Services, Facilities, News, Events, Research, and Virtual Tour
- faculty pages, subpages, profiles, projects, galleries, study plans, and research pages
- dynamic forms, registrations, applications, filtering, search, pagination, and detail flows
- admin panel and CMS coverage for all managed content
- draft / preview / publish workflows, scheduling, cache invalidation, and audit logging
- menu builder, settings, media library, SEO metadata, redirects, and legacy URL continuity
- AR / EN support and RTL / LTR behavior across every public and admin feature
- responsive, accessible, secure, observable, and production-ready behavior
- route, content, visual, interaction, and asset parity with the approved reference frontend

No approved reference page or existing public module is out of scope. Features that are absent from both the requirements and the approved reference frontend still require explicit product approval.

---

## Stack
- Laravel 12 project, following Laravel 11+ structure conventions
- PHP 8.2+
- MySQL 8
- Redis
- Filament v3
- Meilisearch remains optional and should be introduced only when a real search requirement justifies it

---

## Source of Truth
- Product and backend requirements: **SPU_Requirements_v5_1**, except its obsolete foundation-only phase restriction
- Approved frontend parity reference: `C:\Users\hamza\Spu-Website\Spu-Website`
- Laravel production implementation: this repository, `C:\Users\hamza\SPU_Website`
- Architecture: MVC + Service Layer
- Supporting rules:
  - ARCHITECTURE.md
  - BACKEND_RULES.md
  - STYLEGUIDE.md
  - WORKFLOW.md

The full-site scope in this file supersedes older foundation-only phase restrictions. Requirements remain authoritative for business rules, while the approved reference frontend is authoritative for page inventory, content structure, visual intent, and interaction parity. Reference frontend code must not override the Laravel architecture and security rules in this file.

---

## Full-Site Migration Rules (CRITICAL)

- Every approved reference path must have one of:
  - a dedicated functional Laravel implementation
  - an explicitly approved redirect
  - a documented retirement decision
- Known reference pages must not silently fall through to a generic catch-all when they require specialized behavior.
- Visible controls must be functional. Do not ship inert filters, pagination, search, tabs, buttons, sliders, hotspots, or form actions.
- Migration includes behavior and data flow, not markup alone.
- CMS-managed pages must include the appropriate admin editor, authorization, validation, draft, preview, publish, cache, and audit behavior.
- Public pages must use DTOs and service contracts and must not query Eloquent directly.
- Port required assets into the Laravel public/media system and verify that every rendered asset resolves.
- Preserve or redirect published legacy URLs, including query-based and `.html` URLs, when continuity data or imported content depends on them.
- Match the reference frontend in AR and EN, including responsive behavior, keyboard interaction, reduced motion, and RTL scrolling.
- Replace sample, placeholder, fake, and developer-facing public content before production release.
- Track parity work in `Docs/FRONTEND_IMPORT_TRACKER.md` with route, backend, CMS, interaction, asset, test, and QA status.

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
- ALWAYS complete the assigned parity slice end-to-end across backend, CMS, public rendering, and tests
- NEVER represent a reference feature with placeholder markup or non-functional controls

---

## Multilingual Rules (CRITICAL)

- Default locale: `ar`
- Secondary locale: `en`
- All managed content must support AR / EN
- Arabic UI/content must support RTL
- English UI/content must support LTR
- Do NOT use the old global polymorphic translation model
- Use explicit locale-specific storage where applicable:
  - `page_translations`
  - `homepage_section_translations`
  - `page_seo_meta`
- No hardcoded translatable content in CMS-managed areas

---

## Role System

Roles:
- `super_admin` → full access
- `editor` → all global content modules / pages / menu / media / settings within granted permissions
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
- ❌ Reintroducing the old polymorphic translation model
- ❌ Shipping known reference pages through generic placeholders when dedicated behavior is required
- ❌ Inert or decorative-only controls that imply unavailable functionality
- ❌ Broken asset references, fake production content, or publicly visible developer metadata
- ❌ Declaring parity based only on route existence or static markup

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
- Relevant reference routes, content, interactions, and responsive states are matched
- AR and EN behavior is verified, including RTL / LTR and locale-preserving navigation
- Every visible control works with keyboard and pointer input
- Required assets resolve without build or runtime 404s
- SEO, canonical, hreflang, and structured metadata are present where applicable
- Legacy URLs are redirected or explicitly documented as retired
- No placeholder, sample, fake, or developer-facing content is publicly exposed
- Targeted tests and the relevant module suite pass
- Frontend production build and PHP production dependency installation pass
- Production configuration, queues, cache, storage, scheduler, security headers, and observability requirements are verified before release
