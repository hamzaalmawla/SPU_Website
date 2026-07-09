# Phase 6 Migration Handoff

Last updated: 2026-07-09

## Short Answer

Overall legacy migration progress is about `25%` of the classified migration scope.

Text/content-first progress is about `62%` if file-only rows are excluded.

Use this wording when reporting status:

> We have migrated about 25% of the total classified legacy migration scope, and about 62% of the text/content-first scope. File/media-heavy rows remain deferred until reliable old file access is available.

## Current Position

The project is paused from data migration work after completing the safe text-first Phase 6 lanes that were practical without old file bytes.

The next work should move to other project priorities unless explicitly resuming migration.

Do not continue broad importing casually. The remaining rows are either file-dependent, duplicate/blocked, require manual selection, or belong to later full-module phases.

## Core Migration Rules To Preserve

- Do not run broad legacy imports.
- Do not add old import seeders to `DatabaseSeeder`.
- Do not publish imported content by default unless explicitly approved.
- Do not redirect valuable old URLs to the homepage.
- Do not promote legacy files/media until old file bytes are verified.
- Keep imported media/file references as metadata until the media pass.
- Use service-layer importers with contracts, DTOs, dry-run, approval token, idempotency, and `migration_logs`.
- Controllers stay thin and must not query Eloquent directly.
- Business logic stays in services.

## Main Documents

- Master plan: `Docs/WEBSITE_MIGRATION_GUIDE/ULTRA_HARDENED_DATABASE_MIGRATION_MASTER_PLAN.md`
- Phase status: `Docs/WEBSITE_MIGRATION_GUIDE/ULTRA_HARDENED_PHASE_STATUS.md`
- Runbook: `Docs/LEGACY_IMPORT_RUNBOOK.md`
- Media continuity plan: `Docs/WEBSITE_MIGRATION_GUIDE/MASTER_LEGACY_MEDIA_MIGRATION_CONTINUITY_PLAN.md`

## What We Completed

### Safety And Infrastructure

- Broad imports are blocked by default.
- Legacy modules are disabled unless explicitly enabled/approved.
- Old DB access uses a dedicated `legacy_mysql` alias when needed.
- Added controlled commands for inventory, dry-runs, cleaning, integrity, classification, staging, URL continuity, candidates, approvals, and imports.
- Added migration traceability using `migration_logs`, `legacy_content_mappings`, `legacy_review_items`, and file inventory tables.
- Added service contracts, DTOs, service implementations, tests, and container bindings for the migration system.

### Phase 3 Cleaning And Integrity

- Built cleaning services for text, HTML, URLs, emails, dates, unsafe HTML, Word HTML, base64 images, spam links, and unsupported locales.
- Built dry-run and recording commands for cleaning/integrity/internal-link reports.
- Produced quarantine/review exports and summaries.

### Phase 4 Classification And Staging

- Classified and staged `38,689` legacy rows.
- Persisted proposed mappings for all classified rows.
- Created full staging review records.
- Separated review candidates, decision-plan candidates, and blocked rows.

### Phase 5 URL Continuity Foundation

- Added URL normalization and query resolver foundation.
- Added generated URL inventory and redirect evidence exports.
- Added runtime query resolver support for already-imported news/research style legacy URLs.
- Final redirect persistence remains gated and has not been done.

### Phase 6 Imported Lanes

Imported distinct legacy source records:

| Module | Imported Source Records | Target State |
| --- | ---: | --- |
| News | `3,035` | Existing migrated news/announcements; many published, some draft |
| Alumni | `4,939` | Enabled public alumni records, photos deferred |
| Honor students | `1,067` | Enabled public honor records, photos deferred |
| Faculty members | `337` | Disabled faculty profile records, photos/CVs deferred |
| Research publications | `289` | Enabled public research records, PDFs/media deferred |
| Locations | `122` | Disabled countries/cities reference rows |
| Static pages | `21` | Disabled draft pages with translations |
| Menu links | `20` | Disabled localized footer menu items |
| Settings | `16` | Imported as `8` unique live setting units |

Total distinct migrated legacy source records: `9,846`.

Classified migration scope: `38,689` rows.

Overall migrated percentage: `25.45%`.

Text/content-first denominator excluding file-only rows: `15,892` rows.

Text/content-first migrated percentage: `61.95%`.

## Latest Imported Data Counts

Target table counts at handoff:

| Target | Count |
| --- | ---: |
| `news_articles` | `3,035` |
| `menu_items` | `196` total, including imported legacy menu items |
| `pages` | `29` total, including `21` imported draft pages |
| `settings` | `20` total |
| `alumni` | `4,959` total |
| `honor_students` | `1,097` total |
| `faculty_members` | `337` |
| `research_publications` | `289` |
| `countries` | `107`, all disabled from import |
| `cities` | `15`, all disabled from import |

## Latest Location Import

Added and ran:

```bash
php artisan legacy-import:locations --batch=phase6-locations-dry-run
php artisan legacy-import:locations --write --approve=phase6-locations --batch=phase6-locations-20260708
```

Result:

- `107` countries imported.
- `214` country translations imported.
- `15` cities imported.
- `30` city translations imported.
- Imported disabled by default.
- `122` success logs written.
- Rerun skipped `122` rows as `already_imported`.

Files added for this lane:

- `app/Contracts/Legacy/LegacyLocationImportServiceInterface.php`
- `app/DTOs/Legacy/LegacyLocationImportResultDTO.php`
- `app/Services/Legacy/LegacyLocationImportService.php`
- `app/Console/Commands/LegacyImportLocationsCommand.php`
- `tests/Feature/LegacyImportLocationsCommandTest.php`

## Public/Admin Fixes Completed During Migration

- Public footer now uses CMS/admin navigation and settings payloads instead of hardcoded arrays.
- Faculty alumni and valedictorian pages support filters, search, pagination, and proper public rendering.
- Faculty admin editor no longer loads huge student repeaters until filters are applied.
- Hidden student records are preserved on save.
- Research publications listing supports GET filters/search.
- Imported research detail pages clean legacy HTML and show parsed author, publisher/journal, abstract, and keywords when recoverable.
- Empty DOI/action/theme/rank metadata is hidden instead of displaying placeholders.
- News and announcements were verified already imported; numeric public URLs remain preferred.

## What Is Still Blocked

### File/Media Work

Blocked until reliable old file access is available.

Known issue:

- `OLD_PUBLIC_ROOT=X:/` rclone mount became unavailable during previous work.
- Full checksum scanning over FTP/rclone was too slow and unreliable.
- Current `legacy_file_inventory` should not be treated as fully authoritative until rerun against a stable archive/root.

Do not continue file/media migration until one of these is available:

- server-side archive of the old public root
- reliable mounted old public root
- professor-provided manifest/checksums/archive

### Homepage And Documents

- `homepage` has `54` rows, mostly file/media-dependent.
- `documents_and_links` has `45` rows, file-dependent.
- Keep these blocked until media/files are available.

### Selected Core Pages

- `selected_core_pages` has `4,944` rows.
- This requires explicit manual selection, not bulk import.
- Do not import all `jx_categories` rows as pages.

### Complaints

- Old complaints contain personal/contact data.
- Keep out of current scope unless there is explicit approval and a privacy/review policy.

### FAQs

- `jx_faqs` has `1,553` rows.
- `507` are clean review candidates.
- `1,046` have duplicate/blocker issues.
- Target FAQ tables exist, but a dedicated FAQ import policy is needed before importing.

### Career Links

- `jx_job_sites` has `3` rows.
- Target career link tables exist.
- Rows include legacy photo references, but text/link data could be imported disabled with photos deferred.
- This is the smallest likely next migration if migration work resumes.

## Recommended Next Migration If Resuming Later

### Option 1: Career Links

This is the lowest-risk next lane.

Implementation pattern:

- Add `LegacyCareerLinkImportServiceInterface`.
- Add `LegacyCareerLinkImportResultDTO`.
- Add `LegacyCareerLinkImportService`.
- Add `legacy-import:career-links` command.
- Use `jx_job_sites` as source.
- Target `career_links` and `career_link_translations`.
- Import disabled unless `--enable` is supplied.
- Preserve legacy `photo`, `visits`, `record_order`, `added_date`, `updated_date` in `migration_logs.metadata`.
- Leave media fields out because the target table has no media column and file bytes are unavailable.
- Require `--approve=phase6-career-links` for writes.
- Add unit/feature tests.

Suggested commands after implementation:

```bash
php artisan legacy-import:career-links --batch=phase6-career-links-dry-run
php artisan legacy-import:career-links --write --approve=phase6-career-links --batch=phase6-career-links-YYYYMMDD
```

### Option 2: FAQs

Only do after defining duplicate policy.

Required decisions:

- Which `lang` values map to `ar` and `en`.
- Whether `subject` becomes category or keyword.
- How to handle duplicate `lang + subject` rows.
- Whether unanswered questions are imported as disabled, skipped, or archived.
- Whether old submitter names/emails/phone fields are ignored or preserved privately in logs only.

Suggested safe behavior:

- Import only visible FAQs with a non-empty cleaned question and answer.
- Disable by default.
- Preserve submitter/contact fields only in private `migration_logs.metadata`, not public FAQ tables.
- Skip duplicate blocked rows unless a manual rule is approved.

### Option 3: Media/File Pass

Only do after stable old file root/archive is available.

Required steps:

```bash
php artisan legacy-import:file-inventory --write
```

Then verify:

- resolved file count
- missing file count
- checksum status
- MIME/type detection
- file references by module

After that, create module-specific media attachment passes.

## Commands Worth Running To Rebuild Context Later

```bash
php artisan legacy-import:phase6-candidates
php artisan legacy-import:phase6-candidates homepage
php artisan legacy-import:phase6-candidates settings
php artisan legacy-import:staging-summary --status=review_candidate --sample-limit=20
php artisan legacy-import:locations
php artisan test tests/Feature/LegacyImportLocationsCommandTest.php tests/Feature/ArchitectureGuardTest.php
```

Useful DB checks:

```php
DB::table('migration_logs')
    ->selectRaw('module, source_table, target_table, status, count(*) as c')
    ->groupBy('module', 'source_table', 'target_table', 'status')
    ->orderBy('module')
    ->get();

DB::table('legacy_review_items')
    ->selectRaw('module, review_status, classification, count(*) as c')
    ->groupBy('module', 'review_status', 'classification')
    ->orderBy('module')
    ->get();
```

## Verification Completed Recently

Passed:

```bash
php artisan test tests/Feature/LegacyImportLocationsCommandTest.php tests/Feature/ArchitectureGuardTest.php
php artisan test tests/Feature/ResearchPublicPagesTest.php tests/Feature/ArchitectureGuardTest.php tests/Feature/LegacyImportSafetyTest.php
```

Full `php artisan test` was started after the large migration commit but was aborted because it was taking too long. Two stale assertions found during that run were fixed and verified separately.

## Git State At This Handoff

The large migration foundation was committed as:

```text
59ba6b0 feat(legacy): build hardened migration and publishing foundation
```

After that commit, new uncommitted location-import work was added and run:

- location importer files
- location importer tests
- updated runbook/status docs
- imported disabled country/city records into the local DB

Before switching branches or doing risky work, either commit or intentionally stash these post-commit changes.

## Stop Point

Stop data migration here for now.

The next non-migration project work can proceed safely. The current migration state is documented, traceable, and resumable.
