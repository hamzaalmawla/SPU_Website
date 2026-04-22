# Project Decision Log

## Purpose

This file records current implementation decisions that future prompts must treat as the active project baseline unless the user explicitly changes them.

## Active Decisions

### 1. Admin Path Strategy

- Decision: Filament admin path is `/cms`.
- Why it matters: avoids conflict with the legacy public `/admin` context and gives the new control panel a dedicated internal path.
- Affects:
  - `app/Providers/Filament/AdminPanelProvider.php`
  - `routes/web.php`
  - auth redirects and admin middleware expectations
  - continuity and redirect planning for legacy `/admin`
- Status: resolved

### 2. Public URL Model

- Decision: public pages use `/{locale}/{slug}`.
- Why it matters: this is the canonical routing model for public page rendering, navigation, canonical URLs, sitemap generation, and redirect targets.
- Affects:
  - `routes/web.php`
  - page runtime controllers/services
  - navigation generation
  - SEO metadata generation
- Status: resolved

### 3. Homepage URL Strategy

- Decision:
  - homepage URLs are `/ar` and `/en`
  - root `/` redirects to `/en`
- Why it matters: defines the public homepage entry points and the default public landing locale.
- Affects:
  - `routes/web.php`
  - homepage rendering
  - canonical generation
  - locale switching
  - public cache keys
- Status: resolved

### 4. Slug Strategy

- Decision: use one shared canonical slug per page for now.
- Why it matters: keeps page resolution simple during current-scope implementation while avoiding premature locale-specific slug complexity.
- Affects:
  - `pages.slug`
  - `SlugServiceInterface` implementation
  - page lookup logic
  - navigation links
  - canonical/hreflang generation
- Status: resolved
- Note: this does not change the continuity strategy for legacy URLs; legacy preservation must still rely on redirect and archive-aware resolution, not slug reconstruction.

### 5. Supported Public Locales

- Decision: only `ar` and `en` are supported as public locales in the new runtime.
- Why it matters: constrains routing, middleware, sitemap output, hreflang generation, and CMS-managed content scope.
- Affects:
  - `routes/web.php`
  - locale middleware
  - page/homepage/settings translations
  - SEO output
- Status: resolved

### 6. Unsupported Legacy Locale Strategy

- Decision: preserve and classify unsupported legacy locales, but do not promote them into the CMS.
- Why it matters: protects migration evidence and archive decisions without expanding public runtime scope beyond approved languages.
- Affects:
  - legacy import tooling
  - `legacy_record_snapshots`
  - continuity/archive decisions
  - migration reporting
- Status: resolved

### 7. Continuity Model

- Decision: use redirect + archive-first continuity. Use `410` only by explicit approval.
- Why it matters: preserves public value for legacy URLs and files without forcing unsafe deletion decisions during migration.
- Affects:
  - redirect schema/runtime
  - archive routing strategy
  - legacy URL resolution
  - file/PDF continuity
  - unresolved-request logging
- Status: resolved

### 8. Cutover Rule

- Decision: no-go for public cutover until continuity, SEO, and file handling are proven.
- Why it matters: prevents a premature launch that would break legacy URLs, weaken search signals, or lose public files.
- Affects:
  - launch readiness criteria
  - QA gates
  - rollback planning
  - test scope
- Status: resolved

## Implementation Guardrails

- Future prompts must not re-open these decisions unless the user explicitly changes them.
- Future prompts should patch existing foundation code instead of regenerating contracts, DTOs, schema, or models from scratch.
- Legacy continuity must remain query-aware where needed; do not assume slug reconstruction is enough.
- Unsupported legacy locales may be preserved for archive and reconciliation purposes, but they are not part of the public CMS locale model.
- `410` responses are approval-only, not a default migration cleanup mechanism.

## Prompt Reference Summary

Use these assumptions in future implementation prompts:

- admin path: `/cms`
- root redirect: `/` -> `/en`
- homepage URLs: `/ar`, `/en`
- public page URLs: `/{locale}/{slug}`
- public locales: `ar`, `en`
- slug model: one shared canonical slug per page
- unsupported legacy locales: preserve/classify only
- continuity: redirect + archive-first
- `410`: approval-only
- cutover: blocked until continuity + SEO + file handling are proven
