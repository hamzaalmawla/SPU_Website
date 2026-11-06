# SPU Website Master Legacy, Media, Migration, And Continuity Plan

## Purpose

This document defines the clean phased plan for turning the current mixed legacy/new SPU website into a reliable production system.

It covers:

- clean Media Library design
- legacy media archive strategy
- CMS upload/editing workflow
- old-to-new data migration
- URL continuity and redirects
- SEO preservation
- public performance
- admin usability
- validation, launch, and post-launch cleanup

The goal is not only to make the website work. The goal is to prevent the legacy mess from becoming the new system's permanent foundation.

## Core Principle

The new system must have two separate layers.

### Operational Layer

This is the clean system that admins and public pages use every day.

- clean CMS data
- clean main Media Library
- curated metadata
- stable public URLs
- fast cached public payloads
- tested publishing workflow
- admin upload/select workflows

### Legacy Compatibility Layer

This exists only to preserve old links, old files, auditability, and migration recovery.

- legacy media archive
- legacy URL resolver
- redirect inventory
- legacy source mapping
- migration logs
- unresolved URL reports
- recovery tools

The two layers must not be mixed.

## Current Problems

### Media Library

The current database contains a large legacy import.

Observed local state:

- 17,946 media records
- all originally marked with `disk = legacy`
- imported on `2026-06-21`
- main directories:
  - `news/images`: 16,878 records
  - `news/files`: 1,068 records

Problems:

- many filenames are numeric
- many assets have no meaningful title
- many images have no alt text
- PDFs/documents are mixed with images
- legacy media pollutes admin picker results
- admins cannot reasonably identify useful files
- public compatibility is required, but admin usability is poor

### CMS Editing

Admins should not paste raw URLs for CMS-managed uploads.

Required workflow:

- click `Choose / Upload`
- search existing clean media
- upload new file if needed
- automatically save the correct URL/reference
- keep old `/images/...` values rendering until migration finishes

### Migration

A full blind migration would import too much bad data.

Problems with blind migration:

- unused files enter the clean system
- numeric filenames become admin-facing assets
- missing SEO/accessibility metadata spreads into new CMS
- redirects become harder to validate
- performance suffers

### Continuity

Old URLs must not break.

The old code can reveal URL generation rules, but the new system should not run old routing logic forever on every request.

## Target End State

### Admin Experience

For any page editor:

- image fields have a `Choose / Upload` button
- PDF/document fields have a `Choose / Upload` button
- external links remain URL fields
- uploaded files require useful metadata
- selected files preview clearly
- admins never need to paste raw media URLs for uploaded CMS content

### Media Library

There are two separate admin areas.

#### Main Media Library

Used by normal CMS editors.

- clean assets only
- new uploads only by default
- promoted legacy assets only when needed
- meaningful title required
- alt text required for images where appropriate
- searchable by title, filename, type, and usage
- deduplicated by checksum
- safe for non-technical admins

#### Legacy Media Archive

Used for audit and recovery.

- old imported files
- read-only by default
- not shown in normal pickers
- searchable by old path, filename, mime type, date
- can promote selected records into Main Media Library

### Public Pages

- public pages render from published CMS payloads/DTOs
- public pages do not query media assets for every image
- media URLs are resolved at save/publish time
- old file paths continue to work
- redirects are fast and indexed

## Non-Negotiable Architecture Rules

This plan must respect project architecture.

- Business logic belongs in services.
- Controllers stay thin.
- Controllers and higher layers depend on interfaces in `app/Contracts`.
- Do not query Eloquent from controllers.
- Do not instantiate services with `new`.
- Public service methods must not return raw Eloquent models.
- DTOs cross service boundaries.
- Public views must not query media assets.
- Existing draft/preview/publish protections must remain intact.
- Arabic/English and RTL/LTR behavior must remain intact.

## Phased Execution Overview

| Phase | Goal | Result |
| --- | --- | --- |
| 0 | Freeze rules and audit current state | no more guessing |
| 1 | Split media into main vs legacy | clean admin library starts |
| 2 | Fix upload/picker workflow | admins upload from page editors |
| 3 | Build legacy archive tools | old assets remain recoverable |
| 4 | Build URL continuity system | old URLs redirect safely |
| 5 | Migrate active content only | useful content enters clean CMS |
| 6 | Promote referenced media | clean media grows intentionally |
| 7 | Validate and launch | production-safe release |
| 8 | Post-launch cleanup | legacy debt reduced gradually |

## Phase 0: Freeze Rules And Audit

### Objective

Stop the system from getting worse and establish a factual baseline.

### Tasks

- Audit current media assets.
- Count by disk, directory, mime type, media type, and created date.
- Identify legacy imports.
- Identify admin forms still using raw file URL text inputs.
- Identify public views using CMS media payloads.
- Identify old URL patterns from the old codebase.
- Identify old file directories that must remain publicly accessible.

### Deliverables

- media inventory report
- admin upload field inventory
- old URL generation notes
- list of must-preserve public paths
- list of high-priority migration targets

### Acceptance Criteria

- No migration decisions are based on assumptions.
- Legacy media counts are documented.
- Raw upload URL fields are inventoried.
- Old URL patterns are grouped by module.

## Phase 1: Split Main Media Library From Legacy Archive

### Objective

Prevent legacy imported files from polluting the clean Media Library.

### Schema Changes

Add media library classification fields.

Recommended fields:

```php
$table->string('library_scope')->default('main')->index(); // main, legacy
$table->string('metadata_status')->default('missing')->index(); // missing, auto_generated, reviewed
$table->foreignId('promoted_from_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
$table->string('source_path')->nullable()->index();
$table->timestamp('reviewed_at')->nullable();
$table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
```

If minimal schema is required, start with only:

```php
$table->string('library_scope')->default('main')->index();
$table->string('metadata_status')->default('missing')->index();
```

### Backfill Rules

- Existing `disk = legacy` rows become `library_scope = legacy`.
- Existing imported rows with directory `news/images` or `news/files` become `legacy`.
- New uploads become `library_scope = main`.
- Promoted legacy assets become `main`.

### Admin Behavior

- Main Media Library shows only `library_scope = main` by default.
- Legacy Media Archive shows only `library_scope = legacy`.
- Normal CMS pickers only query `main`.
- Legacy archive is read-only except for promote/review actions.

### Acceptance Criteria

- Normal admins do not see 17k legacy files in pickers.
- Main Media Library is clean by default.
- Legacy files remain accessible and searchable separately.
- Existing public legacy URLs keep working.

## Phase 2: Fix CMS Upload And Picker Workflow

### Objective

Admins upload/select files directly from the page they are editing.

### Required UX

Every CMS-managed image/file field should show:

- current selected file preview or filename
- `Choose / Upload` button
- modal with two paths:
  - choose existing clean media
  - upload new file
- clear/replace option
- automatic payload URL/reference update

### Field Types

Use picker by field intent.

| Field Type | Picker Type |
| --- | --- |
| hero image | images only |
| card image | images only |
| profile image | images only |
| logo | images only |
| icon | images only or SVG policy if allowed |
| PDF | documents/PDFs only |
| course file | documents/PDFs only |
| lesson file | documents/PDFs only |
| downloadable document | documents/PDFs only |
| external website link | URL input |
| social URL | URL input |
| map/embed URL | URL input |

### Payload Rules

For existing CMS JSON payloads:

- keep storing the public URL/path where views expect it
- optionally store companion media id such as `imageMediaId`
- do not make public Blade views query media assets
- resolve selected media into URL before save/publish

### Upload Rules

- New upload goes to Main Media Library.
- Calculate checksum.
- If same checksum exists in Main Media Library, reuse existing asset.
- Require title for all main media uploads.
- Require alt text for important content images.
- Validate MIME and file size in service layer.
- Store binary files on Laravel Storage, not in DB.

### Acceptance Criteria

- Admin can upload/select hero image from homepage editor.
- Admin can upload/select PDFs/documents from page editors.
- No normal CMS upload requires pasting a raw URL.
- Existing `/images/...` values continue rendering.
- Pickers do not preload thousands of media records.

## Phase 3: Legacy Media Archive And Promotion Workflow

### Objective

Keep old assets available without making admins browse the mess.

### Legacy Archive Features

- search by old path
- search by filename
- filter by type
- filter by directory
- filter missing file/broken path
- filter duplicate checksum
- filter referenced/unreferenced
- view public URL
- view metadata

### Promotion Workflow

Admins or migration commands can promote a legacy asset into the Main Media Library.

Promotion should:

- check checksum
- avoid duplicates in main library
- require title
- require alt text for images where applicable
- preserve old source path
- create a main media record
- mark `promoted_from_media_id`
- optionally copy file into clean storage path

### Copy vs Reference Decision

Two valid approaches exist.

#### Reference Legacy File

Pros:

- faster
- no duplicate physical files
- preserves old URL

Cons:

- main library still depends on legacy public location

#### Copy Into Main Storage

Pros:

- clean storage structure
- independent from legacy folders
- better long-term maintenance

Cons:

- duplicate physical file unless old copy is removed later

Recommended:

- For actively promoted main assets, copy into clean main storage.
- Keep old file in place until continuity audit says it is safe to remove.

### Acceptance Criteria

- Legacy archive is separate.
- Main pickers exclude legacy by default.
- Legacy assets can be promoted intentionally.
- Promotion requires metadata.
- Duplicate promoted assets reuse existing main media where possible.

## Phase 4: URL Continuity And Redirect System

### Objective

Old URLs must resolve to correct new URLs with minimal runtime cost.

### Strategy

Use the old code to understand URL generation, but do not run old application logic forever on every request.

### Components

Create:

```php
App\Contracts\Continuity\LegacyUrlResolverInterface
App\Services\Continuity\LegacyUrlResolver
App\Contracts\Continuity\RedirectRuleServiceInterface
App\Services\Continuity\RedirectRuleService
```

Commands:

```bash
php artisan continuity:discover-legacy-routes
php artisan continuity:generate-redirects
php artisan continuity:validate-redirects
php artisan continuity:report-unresolved
```

### Redirect Table

Recommended fields:

```php
$table->id();
$table->string('source_url', 2048)->index();
$table->string('source_path', 2048)->index();
$table->string('source_query_hash', 64)->nullable()->index();
$table->string('target_url', 2048);
$table->unsignedSmallInteger('status_code')->default(301);
$table->string('locale', 5)->nullable()->index();
$table->string('source_type')->nullable()->index();
$table->string('source_id')->nullable()->index();
$table->string('confidence')->default('generated')->index();
$table->boolean('is_active')->default(true)->index();
$table->json('metadata')->nullable();
$table->timestamps();
```

### Runtime Redirect Order

1. Exact redirect table lookup.
2. Deterministic resolver fallback for safe known patterns.
3. Existing public file fallback.
4. 404 and unresolved URL log.

### Performance Rules

- Normalize request path before lookup.
- Index `source_path` and `is_active`.
- Cache hot redirect rules.
- Avoid slow old-code/database logic in middleware.
- Use commands to generate redirect rules ahead of launch.

### Old Code Usage

Use the old code to answer:

- how old news URLs were generated
- how old category URLs were generated
- how old faculty URLs were generated
- how old language switching worked
- how old files/documents were linked
- which IDs/slugs were used publicly

Do not copy old controllers directly into the new runtime.

### Acceptance Criteria

- Top old URLs redirect to correct canonical new URLs.
- Query-string old URLs are handled.
- Language-specific URLs redirect correctly.
- Redirect validation command passes before launch.
- Unresolved URLs are logged for review.

## Phase 5: Active Content Migration

### Objective

Move useful public content into the clean CMS without importing all legacy garbage.

### Migration Priority

1. Homepage
2. Navigation/menu/settings/footer
3. Admissions
4. Faculties/facilities
5. Research
6. News/events
7. Campus life
8. About/contact/static pages
9. Remaining low-priority archives

### Per-Content Migration Steps

For each legacy content item:

1. Read old record.
2. Map to new target type.
3. Normalize locale.
4. Transform content into DTO/payload shape.
5. Identify referenced media.
6. Promote referenced legacy media if needed.
7. Generate title/alt from content context.
8. Store clean main-media URL/reference.
9. Generate old-to-new redirect.
10. Log migration result.

### Migration Tracking Table

Recommended fields:

```php
$table->id();
$table->string('legacy_source')->index();
$table->string('legacy_id')->index();
$table->string('target_type')->index();
$table->string('target_id')->nullable()->index();
$table->string('status')->index(); // pending, migrated, skipped, failed, reviewed
$table->string('locale', 5)->nullable()->index();
$table->json('metadata')->nullable();
$table->text('notes')->nullable();
$table->timestamps();
```

### Acceptance Criteria

- Only active content is migrated first.
- Migrated public pages render correctly.
- Migrated content has media references resolved.
- Old URLs redirect to migrated content.
- Migration can be rerun idempotently.

## Phase 6: Referenced Media Promotion

### Objective

Only media actually used by migrated content enters the Main Media Library.

### Promotion Sources

- homepage hero images
- page hero images
- faculty images/logos
- news cover images
- research PDFs/images
- admissions documents
- policy documents
- course/lesson files
- SEO OG images

### Metadata Generation Rules

Use context to generate first-pass metadata.

Examples:

| Context | Generated Title |
| --- | --- |
| homepage hero | Homepage Hero Image |
| news article cover | article title |
| faculty hero | faculty name + Hero Image |
| researcher profile | researcher name |
| policy PDF | policy document title |
| admissions checklist | checklist title |

### Metadata Status

- `missing`: no useful metadata
- `auto_generated`: generated by migration/context
- `reviewed`: approved by editor

### Acceptance Criteria

- Referenced media appears in Main Media Library.
- Unused legacy media remains in Legacy Archive.
- New main media has usable title.
- Important images have alt text.
- Duplicate files are not promoted repeatedly.

## Phase 7: Validation And Launch Readiness

### Objective

Prove the new website is safe to launch.

### Required Validation Commands

```bash
php artisan test
php artisan continuity:validate-redirects
php artisan continuity:report-unresolved
php artisan launch:validate
php artisan sitemap:generate
npm run build
```

### Media Validation

Check:

- missing main media files
- broken URLs
- missing image alt text
- duplicate main media checksum
- unreviewed high-priority assets
- legacy assets accidentally visible in normal pickers

### Redirect Validation

Check:

- no redirect loops
- no redirect chains longer than one hop where possible
- old top URLs redirect to correct new canonical URLs
- locale redirects are correct
- query parameter redirects are handled
- files either serve or redirect correctly

### SEO Validation

Check:

- canonical URLs
- OG image paths
- sitemap URLs
- robots directives
- metadata per locale
- 301 status codes for old URLs

### Performance Validation

Check:

- homepage public response time
- cached public pages
- admin media picker search time
- redirect lookup time
- sitemap generation time
- no public media N+1 queries

### Acceptance Criteria

- Tests pass.
- Build passes.
- Redirect validation passes.
- Public pages do not leak drafts.
- Admin pages load reliably.
- Normal media pickers show clean media only.

## Phase 8: Post-Launch Cleanup

### Objective

Reduce legacy debt safely after launch.

### Cleanup Reports

- unused legacy media
- broken legacy media
- duplicate legacy media
- unresolved old URLs
- redirected but low-confidence URLs
- main media missing reviewed metadata
- content still using legacy URLs

### Cleanup Rules

- Do not delete legacy files immediately after launch.
- Keep logs for old file hits.
- Only delete or archive files after confirmed no traffic/use.
- Preserve high-value archival documents.
- Continue promoting legacy assets only when needed.

### Acceptance Criteria

- Legacy archive shrinks only by evidence-based cleanup.
- No public link breaks due to cleanup.
- Editors continue using clean media workflow.

## Data Model Summary

### Media Asset

Recommended clean shape:

```php
disk
path
original_name
mime_type
extension
size_bytes
checksum
media_type // image, pdf, document, video, icon, other
library_scope // main, legacy
metadata_status // missing, auto_generated, reviewed
title_ar
title_en
alt_text_ar
alt_text_en
caption_ar
caption_en
width
height
uploaded_by
faculty_scope_slug
source_path
promoted_from_media_id
reviewed_by
reviewed_at
```

### Redirect Rule

Recommended clean shape:

```php
source_url
source_path
source_query_hash
status_code
locale
source_type
source_id
confidence
is_active
metadata
```

### Migration Log

Recommended clean shape:

```php
legacy_source
legacy_id
locale
status
metadata
notes
```

## Public Rendering Rules

Public rendering must be fast and predictable.

- Resolve media URLs before saving/publishing CMS payloads.
- Store final public URL/reference in payload.
- Cache public page payloads by locale.
- Public Blade views read payload strings/DTOs only.
- Public Blade views do not search media assets.
- Preview flows use draft snapshots.
- Draft content never leaks publicly.

## Admin Picker Rules

Normal CMS pickers:

- show `library_scope = main`
- searchable and paginated
- no eager loading thousands of rows
- include upload-new option
- write URL/reference automatically
- do not expose legacy archive by default

Legacy archive:

- separate admin area
- read-only default
- searchable by path and filename
- promote action available to authorized users

## Launch Readiness Checklist

Before launch:

- Main Media Library is separated from Legacy Archive.
- All new uploads go to Main Media Library.
- CMS page editors have upload/select workflows.
- Legacy URLs still render or redirect.
- Top old URLs are redirected.
- Homepage and core pages migrated.
- Navigation/settings/footer migrated.
- Media metadata requirements are enforced for new uploads.
- Public cache is enabled and locale-aware.
- Redirect validation passes.
- Sitemap is correct.
- Build passes.
- Tests pass.
- Rollback plan exists.

## Recommended Immediate Next Sprint

### Sprint Goal

Create a clean media foundation without breaking legacy compatibility.

### Tasks

1. Add `library_scope` and `metadata_status` fields.
2. Backfill existing `disk = legacy` records to `library_scope = legacy`.
3. Make new uploads default to `library_scope = main`.
4. Change all normal media pickers to query only `main`.
5. Add Legacy Media Archive admin page/resource.
6. Add promote-to-main action.
7. Require title for new main uploads.
8. Require alt text for image uploads where appropriate.
9. Add media cleanup dashboard filters.
10. Add tests for main-vs-legacy picker separation.

### Done When

- Admins no longer see 17k legacy files in normal pickers.
- New uploads are clean and metadata-aware.
- Legacy files remain accessible.
- Media tests pass.
- Homepage editor has `Choose / Upload` without freezing.

## Recommended Second Sprint

### Sprint Goal

Build continuity and migration infrastructure.

### Tasks

1. Analyze old URL generation code.
2. Implement `LegacyUrlResolver` service.
3. Add redirect rule table/service.
4. Add unresolved URL log.
5. Add redirect generation command.
6. Add redirect validation command.
7. Generate initial redirect inventory.
8. Validate top old URLs.
9. Add tests for old URL patterns.
10. Prepare migration tracking table.

### Done When

- Old URL patterns are documented and tested.
- Redirect lookup is fast.
- Redirect generation is idempotent.
- Unresolved URLs are logged.

## Recommended Third Sprint

### Sprint Goal

Migrate active content cleanly.

### Tasks

1. Migrate homepage content.
2. Migrate menu/settings/footer.
3. Promote only referenced media.
4. Generate redirects for migrated content.
5. Validate public rendering.
6. Validate SEO metadata.
7. Validate old URLs.

### Done When

- Homepage uses clean CMS payload.
- Main navigation/settings/footer are clean.
- Referenced media is promoted to Main Media Library.
- Old homepage/menu URLs redirect or render correctly.

## Final Rule

Do not turn the legacy archive into the new website.

The legacy system is a source of truth for compatibility and migration history. The new system is the source of truth for the future.
