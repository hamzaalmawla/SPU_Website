# Ultra-Hardened Database Migration Master Plan

## Purpose

This document defines the master plan for moving the legacy SPU website database into the new Laravel system without polluting the clean CMS, losing public value, or carrying forward unsafe legacy data.

The target outcome is a clean, reliable new database that contains everything SPU needs:

- current clean CMS content
- preserved legacy public value
- recoverable legacy media and files
- explicit migration logs and rejection records
- safe redirects and continuity mappings
- no spam, unsafe HTML, fake dates, or accidental legacy noise in public CMS tables

## Core Rule

Do not migrate table-to-table.

Every legacy record must move through this pipeline:

```text
legacy raw DB
-> inventory
-> classification
-> cleaning
-> staging/review
-> target import
-> verification
-> publish/continuity
```

Nothing skips this pipeline.

## Non-Negotiables

- Keep `spuedu_db.sql` immutable.
- Never edit the legacy dump manually.
- Never run broad legacy seeders accidentally.
- Never import unsupported locales into public CMS tables.
- Never publish unsanitized legacy HTML.
- Never migrate legacy passwords as trusted credentials.
- Never send valuable legacy URLs to the homepage as a shortcut.
- Never allow legacy media into normal CMS pickers unless promoted.
- Never delete or move legacy files during migration unless explicitly approved.
- Business logic belongs in services.
- Controllers must stay thin.
- Import execution must be traceable by batch/module/source record.

## Phase 0: Freeze And Protect

### Goal

Prevent accidental damage before real migration starts.

### Work

- Keep `spuedu_db.sql` as the immutable historical source.
- Disable accidental broad imports by default.
- Require explicit module enablement before any import runs.
- Keep all imported public content as draft or archive until verified.
- Keep all legacy media isolated as `library_scope = legacy`.
- Document which modules are safe to run and which are blocked.

### Deliverables

- Safe import rules enforced in code.
- Broad import cannot run accidentally.
- Current migration scope is documented.

### Done Criteria

- `LegacyImportSeeder` cannot import broad modules unexpectedly.
- Individual module imports respect config/module allowlists.
- Tests prove disabled modules do not run.

## Phase 1: Migration Infrastructure Hardening

### Goal

Replace seeder-first migration with controlled, auditable import tooling.

### Work

- Add `legacy-import:inventory`.
- Add `legacy-import:dry-run {module}`.
- Add `legacy-import:run {module} --batch=...`.
- Add safe batch tracking.
- Keep seeders as thin/manual wrappers only, or retire them from the main workflow.
- Move migration business logic into service-layer classes.
- Enforce module enable checks from `config/old_database.php`.
- Log every migrated, skipped, rejected, and quarantined row.

### Required Tracking

- source table
- source ID
- source locale
- source URL/query signature where available
- target table/type
- target ID
- batch name
- import status
- rejection reason
- metadata
- notes

### Deliverables

- Controlled import command layer.
- Dry-run mode.
- Batch-level reconciliation.
- Module-level allowlist enforcement.

### Done Criteria

- No import runs without traceability.
- Dry-run reports do not write target content.
- Disabled modules are blocked.
- Import commands are covered by tests.

## Phase 2: Legacy Media And File Safety

### Goal

Preserve legacy media/files while keeping the Main Media Library clean.

### Work

- Fix every legacy media import path.
- Set `library_scope = legacy` for all old media.
- Set `source_path` to the normalized original path/reference.
- Set `metadata_status = missing` unless explicitly reviewed.
- Derive `media_type` from MIME/extension.
- Populate `legacy_file_inventory`.
- Do not move/delete original legacy files.
- Deduplicate by checksum when available.
- Promote only referenced/useful assets into `library_scope = main`.

### High-Priority File Fields

- `jx_items.photo`
- `jx_items.en_file`
- `jx_items.ar_file`
- `jx_categories.photo`
- `jx_home_photos.photo`
- `jx_member_items.en_file`
- `jx_councils.cv`
- `jx_councils.ar_cv`
- `jx_councils1.cv`
- `jx_docs.file`

### Deliverables

- Complete legacy media/file inventory foundation.
- Legacy archive remains separate from Main Media Library.
- CMS pickers remain main-only by default.

### Done Criteria

- Tests prove imported legacy media has `library_scope = legacy`.
- Tests prove normal pickers exclude legacy media.
- Tests prove promotion creates or reuses a main asset.
- Tests prove legacy file URLs do not crash when disk config is missing.

## Phase 3: Data Cleaning Engine

### Goal

Build reusable cleaning and validation before public imports.

### Required Cleaners

- Text trimming and whitespace normalization.
- Invisible character removal.
- Fake date normalization to `null`.
- Email validation.
- URL sanitizer.
- External URL allow/review logic.
- HTML sanitizer.
- Word/Office HTML remover.
- Inline style/class cleanup.
- Spam link detector.
- Inline base64 image detector.
- Legacy internal link extractor.
- Unsupported locale parker.
- Duplicate detector.
- Orphan detector.

### Reject Or Quarantine

- unsafe HTML
- spam links
- unsupported locales
- invalid emails
- fake/empty rows
- orphaned children
- unknown module mapping
- missing file references
- duplicate conflicts
- suspicious external URLs

### Deliverables

- Sanitization service layer.
- Controlled rejection codes.
- Quarantine/report outputs.

### Done Criteria

- No unsafe legacy content can enter published CMS tables.
- Tests cover unsafe links, Word HTML, base64 images, fake dates, and invalid emails.

## Phase 4: Legacy Inventory And Classification

### Goal

Classify all legacy records before importing them into product tables.

### Classification Buckets

- `canonical_rebuild_now`
- `archive_now_remodel_later`
- `redirect_to_equivalent`
- `file_only_preserve`
- `quarantine`
- `retire_after_approval`

### High-Risk Tables

- `jx_categories`
- `jx_items`
- `jx_docs`
- `jx_member_categories`
- `jx_member_items`
- `jx_councils`
- `jx_councils1`
- `jx_config`
- `jx_config1`

### Deliverables

- Full source inventory.
- Per-table/module classification report.
- Mapping sheet for canonical/archive/redirect/retire decisions.

### Done Criteria

- Every source table has row counts.
- High-risk tables have classification rules.
- Unknown mappings are quarantined, not imported blindly.

## Phase 5: URL Continuity Foundation

### Goal

Support old query-string URLs and subpath URLs safely.

Old SPU URLs are not slug-based. They are mostly ID/query driven, so path-only redirects are insufficient.

### Required Support

- normalized full legacy URL
- normalized query signature
- source table
- source ID
- source module
- source locale
- confidence level
- target URL
- redirect status
- notes

### Resolution Order

1. exact full URL match
2. normalized query match
3. pattern rule
4. DB-backed legacy record resolver
5. file inventory resolver
6. unresolved request logging

### Must Handle

- `index.php?...`
- `/med/index.php?...`
- `/dent/index.php?...`
- `/pharm/index.php?...`
- `/info/index.php?...`
- `/petrol/index.php?...`
- `/research/index.php?...`
- `/hospital/index.php?...`
- `/alumni/index.php?...`
- `/clubs/index.php?...`
- `/members/index.php?...`
- old file URLs
- historical public `/admin` context conflict

### Deliverables

- Query-aware legacy URL resolver.
- Redirect inventory/import support.
- Unresolved request logging with normalized signatures.

### Done Criteria

- Representative old query-string URLs resolve correctly.
- Parameter order does not break matching.
- Valuable old URLs do not redirect to homepage by default.
- Unresolved requests are logged for triage.

## Phase 6: Current-Scope Canonical Import

### Goal

Import only content that belongs in the current homepage/admin foundation.

### Import First

- reviewed settings from `jx_config` and `jx_config1`
- homepage-relevant media from `jx_home_photos`
- footer/social/contact settings
- menu/navigation links from safe `jx_docs` and `jx_sites`
- selected static pages from `jx_site_static_pages`
- selected core `jx_categories` pages that map to current landing pages

### Target Areas

- `settings`
- `menu_items`
- `pages`
- `page_translations`
- `page_seo_meta`
- `homepage_sections`
- `homepage_section_translations`
- `media_assets`
- `legacy_exact_redirects`
- `legacy_file_inventory`

### Rules

- Import as draft unless already editorially approved.
- Do not hardcode CMS content.
- Do not publish unsafe HTML.
- Do not import unsupported locales into public tables.
- Preserve unsupported locales in snapshots only.

### Deliverables

- Clean homepage content.
- Clean navigation/settings/current pages.
- Reconciliation reports.

### Done Criteria

- Public content is service-backed and publish-controlled.
- Draft content does not leak.
- All imported page/media references resolve.
- AR/EN payloads are valid.

## Phase 7: Archive Layer Before Full Module Rebuild

### Goal

Preserve out-of-scope but valuable content without pretending full modules are complete.

### Archive First

- historical news
- research/publication-like content
- faculty profile pages
- council/profile content
- old public static pages
- important old PDFs/files
- alumni/honor-student public lists if not yet rebuilt

### Archive Records Must Preserve

- source table
- source ID
- old title
- old locale
- old URL/query signature
- cleaned body
- original body snapshot
- media/file references
- classification
- review status

### Deliverables

- Public or internal archive preservation model.
- Archive continuity URLs.
- Safe path for long-tail legacy content.

### Done Criteria

- Valuable out-of-scope content remains recoverable/reachable.
- Archive content has canonical behavior.
- Unsafe archive content is not public until sanitized/reviewed.

## Phase 8: Module-By-Module Full Migration

### Goal

Migrate full modules only when the target module is production-ready.

### Recommended Module Order

1. News and announcements
2. Core pages and student resources
3. Faculties and academic units
4. Faculty members/profiles
5. Research/publications
6. Councils/governance
7. Alumni
8. Honor students
9. FAQs
10. Career links
11. Complaints/support
12. Comments only if moderation exists

### Each Module Must Have

- target schema verified
- import service
- DTOs
- contracts
- policies
- admin resource/page
- public route/template if public
- migration tests
- reconciliation report
- redirect generation
- media promotion rules

### Deliverables

- Production-safe module imports.
- Module-specific public rendering where required.

### Done Criteria

- No module enters the clean DB without service, policy, admin, public, migration, and test coverage.

## Phase 9: SEO And Public Signal Layer

### Goal

Give search engines clean migration signals.

### Work

- canonical URLs
- AR/EN `hreflang`
- sitemap index
- child sitemaps
- `robots.txt`
- crawlable navigation
- structured data where accurate
- no draft URLs indexed
- no preview URLs indexed
- file MIME handling
- file canonical/header strategy where needed

### Deliverables

- SEO service outputs.
- Public template integration.
- Sitemap routes.

### Done Criteria

- Representative pages expose correct canonical and `hreflang`.
- Sitemap contains only canonical public URLs.
- Draft/preview pages are excluded.

## Phase 10: Verification And Reconciliation

### Goal

Make migration measurable and auditable.

### Reports Per Module

- source rows
- imported rows
- rejected rows
- archived rows
- retired rows
- unresolved media
- unresolved URLs
- unsafe HTML detected
- unsupported locales parked
- duplicates merged
- orphaned children
- public URL coverage
- redirect coverage
- missing translations
- draft vs published counts

### Hard Gates

- No unsafe HTML in published content.
- No legacy media in normal pickers.
- No fake dates shown publicly.
- No invalid emails used operationally.
- No spam domains in public content.
- No orphaned target rows.
- No broad homepage redirects for valuable content.
- No public draft leakage.
- No missing critical file URLs.

### Deliverables

- Reconciliation reports.
- Launch readiness evidence.

### Done Criteria

- Every imported module has a pass/fail report.
- Critical failures block publication/cutover.

## Phase 11: Editorial Review Workflow

### Goal

Ensure ambiguous legacy content is reviewed by humans.

### Review Queues

- unsafe/suspicious HTML
- pages with Word markup
- pages with spam links
- base64 inline images
- missing titles
- missing alt text
- ambiguous module mappings
- conflicting settings
- duplicate people/content
- unsupported locale content
- sensitive files

### Rule

Only reviewed content can move from:

```text
quarantine/archive/draft -> published canonical content
```

### Deliverables

- Review statuses.
- Admin review workflows or report exports.

### Done Criteria

- No ambiguous imported content is published automatically.

## Phase 12: Cutover Preparation

### Goal

Prepare for launch without unknown migration risk.

### Work

- Freeze legacy writes.
- Re-run delta inventory.
- Re-run imports for changed approved modules.
- Validate redirects.
- Validate sitemap.
- Validate representative old URLs.
- Validate representative PDFs/files.
- Validate `/ar` and `/en`.
- Validate homepage publish state.
- Validate admin path strategy.
- Validate cache invalidation.
- Validate unresolved logging.
- Backup old and new DBs.

### Deliverables

- Launch checklist.
- Smoke-test URL set.
- Backups.

### Done Criteria

- Cutover can happen with known and accepted risk.

## Phase 13: Post-Launch Monitoring

### Goal

Catch migration misses quickly after launch.

### First 30 Days

- Review unresolved legacy requests daily.
- Fix high-hit unresolved URLs first.
- Fix file/PDF misses first.
- Watch Search Console.
- Watch Bing Webmaster Tools.
- Track 404/410 volume.
- Track redirect loops/chains.
- Track crawl errors.
- Track old high-value URLs.
- Keep legacy files available.

### Deliverables

- Daily unresolved request triage.
- Redirect/file patch batches.
- Search monitoring notes.

### Done Criteria

- High-value unresolved URLs are resolved or intentionally retired with documentation.

## Immediate Next Build Package

Start here in the next implementation session.

### Objective

Harden legacy import safety gates and media classification before any real migration work continues.

### Tasks

1. Update all legacy media creation paths so legacy-created media rows set:
   - `library_scope = legacy`
   - `metadata_status = missing`
   - `source_path = normalized legacy path`
   - `media_type = derived from MIME/extension`

2. Update these files first:
   - `database/seeders/LegacyImport/BaseLegacyImportSeeder.php`
   - `database/seeders/LegacyImport/ImportLegacyHomepageSeeder.php`
   - `database/seeders/LegacyImport/ImportLegacyLinksSeeder.php`

3. Add module enable guards so disabled modules cannot run accidentally.

4. Protect `LegacyImportSeeder` from broad import execution unless explicitly allowed.

5. Add tests proving:
   - disabled modules do not run
   - broad import cannot run by accident
   - legacy media imports stay `library_scope = legacy`
   - normal media pickers still exclude legacy
   - promoted legacy assets enter `library_scope = main`

6. Add the first inventory/dry-run foundation after the safety gates pass.

### Verification Commands

Run and fix failures:

```bash
php -l <changed PHP files>
php artisan migrate --force
php artisan test --filter=Media
php artisan test --filter=LegacyImport
php artisan test --filter=ManageHomepageTest
```

Run `npm run build` only if frontend assets are touched.

### Completion Criteria

- Legacy imports cannot pollute the Main Media Library.
- Disabled modules cannot import data.
- Import safety behavior is covered by tests.
- Existing public pages and media tests still pass.
