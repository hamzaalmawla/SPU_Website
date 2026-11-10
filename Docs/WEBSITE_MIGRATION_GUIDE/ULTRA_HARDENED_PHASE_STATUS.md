# Ultra-Hardened Migration Phase Status

Last updated: 2026-07-30

This file tracks implementation status against `ULTRA_HARDENED_DATABASE_MIGRATION_MASTER_PLAN.md` so migration work stays phase-gated.

## Current Assessment

| Phase | Status | Notes |
| --- | --- | --- |
| Phase 0: Freeze And Protect | Mostly complete | Broad import is blocked by default, modules are disabled unless allowlisted, and legacy media imports are forced into the legacy scope. |
| Phase 1: Migration Infrastructure Hardening | Partial | Inventory, dry-run, guarded run, batch tracking, and runner registry exist. Row-level controlled import execution is still not complete for approved modules. |
| Phase 2: Legacy Media And File Safety | Complete pending real file-root mount | Legacy media scope hardening exists. File inventory scans source references, checks configured roots, computes checksum/size/MIME when files exist, marks missing references explicitly, and promotion from legacy archive to main media is tested. Current local run found no legacy files because the real legacy public root is not mounted/configured. |
| Phase 3: Data Cleaning Engine | Report foundation complete | Reusable cleaning decision service, cleaned-row service, warning-free real reports, duplicate-safe quarantine/review recording, summaries, and machine-readable decision plans exist for known legacy modules. Approved runner execution is blocked when cleaning or integrity reports have blockers. Full editor UI and runner consumption remain later work. |
| Phase 4: Legacy Inventory And Classification | Staging review foundation complete | Read-only classification report exports table summary, mapping sheet, and JSON for configured legacy modules. Proposed mappings are persisted in `legacy_content_mappings` with `mapping_status=proposed`; no mappings are approved yet. Review-candidate reports separate safe candidates from decision-plan candidates and blocked rows. Full-database staging review persists `38689` audit/review records in `legacy_review_items`, and staging summaries now produce small review packets without importing content. |
| Phase 5: URL Continuity Foundation | Evidence foundation complete | Query normalization, unresolved logging, initial news/page resolvers, read-only continuity inventory, generated DB-backed URL inventory, unresolved URL triage, and redirect evidence/preview exports now exist. Generated evidence produced `6070` preview-ready runtime resolver rows and `6679` blocked/backlog rows. Redirect persistence remains gated by approval/import/file availability. |
| Phase 6: Current-Scope Canonical Import | Multiple controlled lanes imported | Phase 6 current-scope candidate planner exports approval/import readiness from staged review rows. The clean `menu_links` lane was approved/imported as `40` disabled localized footer menu items from `20` legacy rows. The clean `pages` lane was approved/imported as `21` disabled draft pages with `42` translations. The approved `settings` safe mappings were imported as `8` unique live settings units from `16` source rows; duplicate/conflicting/unsafe/backlog rows remain excluded. On 2026-07-30 the current database was reconciled: `4939` alumni and `1067` honor students were imported as source-locale-only records, `310` duplicates were skipped, and `35` seeded placeholders were disabled with provenance. Explicit publication enabled `4904` visible alumni and `1066` visible honor students; `36` hidden source rows remain disabled. AR and EN list the same profiles, with presentation-only fallback to the original Arabic name when no EN source name exists. Faculty profiles use the separate audited public-staff lane; research publication writes remain blocked by the private `/members/` policy. Broader imports remain gated by mapping approval and target-specific runners. |
| Phase 7+: Archive And Later Phases | Not ready | Do not proceed until Phase 6 approval/import gates pass or an explicit archive model is added. |

## Correct Next Order

1. Finish Phase 2 file inventory foundation before adding more redirect resolvers.
2. Build Phase 3 cleaning/rejection services with tests for unsafe HTML, Word markup, base64 images, fake dates, invalid emails, spam links, and unsupported locales.
3. Use Phase 4 classification reports as the source evidence for mapping old query parameters into new targets.
4. Resume Phase 5 resolvers only when their source table/source ID/target mapping is traceable.

## Phase 2 Added Foundation

Command:

```bash
php artisan legacy-import:file-inventory
php artisan legacy-import:file-inventory --write
```

Behavior:

- Dry-run by default.
- Writes only with `--write`.
- Scans configured high-priority file columns from `config/old_database.php`.
- Stores normalized legacy paths in `legacy_file_inventory`.
- Preserves source table, source column, source ID, reference count, extension, and source references.
- Checks configured file roots from `old_database.file_inventory_roots`.
- Computes `checksum_sha256`, `file_size_bytes`, and `mime_type` when file bytes are available.
- Marks unavailable references as `status = missing` instead of leaving them ambiguous.
- Does not move, delete, or promote files.

Current Phase 2 operational dependency:

- Mount or configure the production legacy public file root with `OLD_PUBLIC_ROOT` before final reconciliation.
- Current local run: `25385` references, `25182` unique paths, `0` existing files, `25182` missing files.

## Phase 3 Added Foundation

Service:

- `App\Contracts\Legacy\LegacyContentCleaningServiceInterface`
- `App\Services\Legacy\LegacyContentCleaningService`
- `App\Contracts\Legacy\LegacyCleaningInspectionServiceInterface`
- `App\Services\Legacy\LegacyCleaningInspectionService`

Command:

```bash
php artisan legacy-import:cleaning-report news
php artisan legacy-import:cleaning-report news --record-quarantine
php artisan legacy-import:cleaning-report news --json
php artisan legacy-import:integrity-report news
php artisan legacy-import:integrity-report news --record-quarantine
php artisan legacy-import:integrity-report news --json
php artisan legacy-import:internal-links-report news
php artisan legacy-import:internal-links-report news --record-review
php artisan legacy-import:internal-links-report news --json
php artisan legacy-import:quarantine-export news
php artisan legacy-import:quarantine-export news --format=json
```

Decision outputs:

- `cleaned`: safe automatic cleanup was applied, or the value is already safe.
- `quarantine`: value may be useful but cannot enter public CMS tables without review.
- `rejected`: reserved for truly unusable or dangerous records; this is not the default for fixable content.

Current coverage:

- Phase 3 report config now covers known legacy modules beyond the initial `news`, `static_pages`, and `links` scope, including settings, homepage, faculties, faculty members, research, councils, FAQs, complaints, career links, alumni, honor students, countries, cities, and admins where applicable.
- Text trimming, whitespace normalization, invisible character removal, and non-breaking space normalization.
- Word/Office HTML cleanup.
- Inline style/class cleanup.
- Unsafe script/URL detection.
- Inline base64 image quarantine.
- Spam URL quarantine.
- Fake date normalization to `null`.
- Invalid email handling, with required emails quarantined and optional emails nulled.
- Unsupported locale quarantine.
- Dry-run cleaning report for explicitly configured legacy fields.
- Optional quarantine recording into `migration_rejections` with duplicate-safe behavior.
- Approved controlled runners are blocked when Phase 3 cleaning reports `blockedFields > 0`.
- Dry-run integrity reports for explicitly configured duplicate and orphan rules.
- Optional duplicate/orphan quarantine recording into `migration_rejections` with duplicate-safe behavior.
- Approved controlled runners are blocked when Phase 3 integrity reports `blockedRows > 0`.
- Dry-run legacy internal link extraction for explicitly configured fields.
- Optional internal-link review recording into `migration_rejections` with `raw_summary.legacy_path`, so URL inventory exports can consume it.
- CSV/JSON quarantine review export for `migration_rejections`, including module, source table, source ID, reason, field, previews, and `legacy_path` when present.
- Human-readable quarantine summaries and machine-readable decision plans now classify all recorded Phase 3 rows with automatic policies and zero current user decision groups.

Latest real Phase 3 pass against the mounted legacy database:

- Cleaning reports are warning-free across all configured modules.
- Cleaning blockers remain only in `admins` (`1` invalid required email) and `news` (`211` blocked fields already recorded previously).
- Integrity reports are warning-free across all configured modules.
- Integrity blockers recorded or already present: `settings` (`403`), `news` (`612`), `faculties` (`68`), `research` (`71`), `councils` (`2`), `faqs` (`1046`), `complaints` (`2`), `alumni` (`586`), `honor_students` (`6`).
- Internal-link reports are warning-free across configured content modules.
- Internal-link review rows recorded or already present for `settings`, `links`, `news`, `faculties`, `research`, and `faqs`.
- Aggregate quarantine exports created at `storage/app/private/legacy-import-exports/quarantine/20260705_183555_quarantine_review.csv` and `storage/app/private/legacy-import-exports/quarantine/20260705_183557_quarantine_review.json` with `3675` rows.
- Latest aggregate summary created at `storage/app/private/legacy-import-exports/quarantine-summary/20260705_183916_quarantine_summary.md`; `_needs_decision.csv` is header-only.
- Latest decision plans under `storage/app/private/legacy-import-exports/decision-plans/` cover `admins`, `settings`, `links`, `news`, `faculties`, `research`, `councils`, `faqs`, `complaints`, `alumni`, and `honor_students`, all with `manual_review_count = 0`.

Remaining Phase 3 gaps:

- Integrate `LegacyCleanedRowService` and decision-plan consumption into approved module import runners.
- Persist successful cleaning summaries into migration logs/snapshots consistently.
- Build the full editor review/approval workflow for exported quarantined rows.
- Expand spam/suspicious external URL rules from production findings.

Latest verification:

- `php artisan test --filter=LegacyPhaseThreeConfigurationTest`
- `php artisan test --filter=LegacyCleanedRowServiceTest`
- `php artisan test --filter=LegacyContentCleaningServiceTest`
- `php artisan test --filter=LegacyQuarantineSummaryServiceTest`
- `php artisan test --filter=LegacyDecisionPlanServiceTest`
- `php artisan test --filter=LegacyImport`
- `php artisan test --filter=ArchitectureGuardTest`

## Phase 4 Added Foundation

Service:

- `App\Contracts\Legacy\LegacyClassificationReportServiceInterface`
- `App\Services\Legacy\LegacyClassificationReportService`
- `App\Contracts\Legacy\LegacyMappingProposalServiceInterface`
- `App\Services\Legacy\LegacyMappingProposalService`
- `App\Contracts\Legacy\LegacyReviewCandidateReportServiceInterface`
- `App\Services\Legacy\LegacyReviewCandidateReportService`
- `App\Contracts\Legacy\LegacyStagingReviewServiceInterface`
- `App\Services\Legacy\LegacyStagingReviewService`
- `App\Contracts\Legacy\LegacyStagingSummaryServiceInterface`
- `App\Services\Legacy\LegacyStagingSummaryService`

Command:

```bash
php artisan legacy-import:classification-report
php artisan legacy-import:classification-report news --json
php artisan legacy-import:mapping-proposals legacy-import-exports/classification/20260705_194115_classification_report_mapping.csv
php artisan legacy-import:mapping-proposals legacy-import-exports/classification/20260705_194115_classification_report_mapping.csv --write
php artisan legacy-import:review-candidates
php artisan legacy-import:review-candidates links --json
php artisan legacy-import:staging-review
php artisan legacy-import:staging-review --write
php artisan legacy-import:staging-summary
php artisan legacy-import:staging-summary links --status=review_candidate --sample-limit=20
```

Current coverage:

- Classification buckets match the master plan: `canonical_rebuild_now`, `archive_now_remodel_later`, `redirect_to_equivalent`, `file_only_preserve`, `quarantine`, and `retire_after_approval`.
- High-risk source tables from the master plan are covered by configured rules: `jx_categories`, `jx_items`, `jx_docs`, `jx_member_categories`, `jx_member_items`, `jx_councils`, `jx_councils1`, `jx_config`, and `jx_config1`.
- Classification exports are read-only and do not import content, publish content, promote files, or create redirects.
- Mapping proposal imports are dry-run by default and persist only `mapping_status=proposed` rows with explicit `--write`.
- Existing approved mappings are protected from proposal overwrite.
- Review-candidate reports are read-only and do not approve mappings.
- Staging review creates review/audit records only; it does not approve mappings or import CMS content.
- Staging summaries are read-only review packets over `legacy_review_items`.
- Safe candidates are restricted to low-risk buckets with no file dependency and no Phase 3 findings.
- Rows with file dependencies, non-low-risk buckets, duplicate/orphan findings, or unresolved Phase 3 findings are blocked from approval candidates.
- Rows with file references are explicitly marked `missing_external_source_root` when `OLD_PUBLIC_ROOT` is unavailable.

Latest real Phase 4 pass against the mounted legacy database:

- Status: warning-free.
- Tables: `27`.
- Source rows: `38689`.
- Classified rows: `38689`.
- Unknown/unruled rows: `0`.
- High-risk table coverage: `11/11`.
- Bucket counts: `canonical_rebuild_now=4944`, `file_only_preserve=22797`, `archive_now_remodel_later=10897`, `redirect_to_equivalent=23`, `retire_after_approval=28`.
- File dependency status: `26585` rows marked `missing_external_source_root`, `12104` rows have no file dependency.
- Proposed mappings persisted: `38689`, all with `mapping_status=proposed`.
- Full staging review persisted: `38689` rows in `legacy_review_items`.
- Latest review candidate report:
  - Safe candidates: `7107`.
  - Decision-plan candidates: `2`.
  - Blocked rows: `31580`.
  - Main blocker counts: `file_dependency_missing_external_source_root=26585`, `not_low_risk_bucket=27741`, `phase3_findings_block_review=2557`.
  - Safe redirect candidates: `20` from `links`.
- Latest staging review artifacts:
  - `storage/app/private/legacy-import-exports/staging-review/20260705_235933_staging_review.md`
  - `storage/app/private/legacy-import-exports/staging-review/20260705_235933_staging_review.csv`
  - `storage/app/private/legacy-import-exports/staging-review/20260705_235933_staging_review.json`
- Latest staging summary artifacts:
  - `storage/app/private/legacy-import-exports/staging-summary/20260706_001855_staging_summary.md`
  - `storage/app/private/legacy-import-exports/staging-summary/20260706_001855_staging_summary_groups.csv`
  - `storage/app/private/legacy-import-exports/staging-summary/20260706_001855_staging_summary_samples.csv`
  - `storage/app/private/legacy-import-exports/staging-summary/20260706_001855_staging_summary.json`
  - `storage/app/private/legacy-import-exports/staging-summary/20260706_001914_staging_summary_links_review_candidate.md`
- Latest artifacts:
  - `storage/app/private/legacy-import-exports/classification/20260705_194115_classification_report.md`
  - `storage/app/private/legacy-import-exports/classification/20260705_194115_classification_report_tables.csv`
  - `storage/app/private/legacy-import-exports/classification/20260705_194115_classification_report_mapping.csv`
  - `storage/app/private/legacy-import-exports/classification/20260705_194115_classification_report.json`
  - `storage/app/private/legacy-import-exports/review-candidates/20260705_200139_review_candidates.md`
  - `storage/app/private/legacy-import-exports/review-candidates/20260705_200139_review_candidates_safe_candidates.csv`
  - `storage/app/private/legacy-import-exports/review-candidates/20260705_200139_review_candidates_decision_plan_candidates.csv`
  - `storage/app/private/legacy-import-exports/review-candidates/20260705_200139_review_candidates_blocked.csv`
  - `storage/app/private/legacy-import-exports/review-candidates/20260705_200139_review_candidates.json`

Remaining Phase 4 gaps:

- Approve module-specific mapping subsets after review.
- Keep file-dependent rows out of media promotion until the old public file root is available.
- Use classification evidence before adding more URL resolvers or final redirect mappings.

## Phase 5 Added Foundation

Service:

- `App\Contracts\Legacy\LegacyUrlContinuityInventoryServiceInterface`
- `App\Services\Legacy\LegacyUrlContinuityInventoryService`
- `App\Contracts\Legacy\LegacyUrlContinuityTriageServiceInterface`
- `App\Services\Legacy\LegacyUrlContinuityTriageService`
- `App\Contracts\Legacy\LegacyGeneratedUrlInventoryServiceInterface`
- `App\Services\Legacy\LegacyGeneratedUrlInventoryService`
- `App\Contracts\Legacy\LegacyRedirectEvidenceServiceInterface`
- `App\Services\Legacy\LegacyRedirectEvidenceService`

Command:

```bash
php artisan legacy-import:url-continuity-inventory
php artisan legacy-import:url-continuity-inventory --without-files --json
php artisan legacy-import:url-continuity-triage legacy-import-exports/url-continuity/20260705_211357_url_continuity_inventory.csv
php artisan legacy-import:generated-url-inventory
php artisan legacy-import:url-continuity-triage legacy-import-exports/generated-url-inventory/20260705_233452_generated_url_inventory.csv
php artisan legacy-import:redirect-evidence legacy-import-exports/generated-url-inventory/20260705_233452_generated_url_inventory.csv legacy-import-exports/url-continuity-triage/20260705_233523_url_continuity_triage_rows.csv
```

Current coverage:

- Normalizes legacy paths and query strings into sorted query signatures.
- Inventories existing exact redirects, Phase 3 internal-link review rows, Phase 4 redirect mapping proposals, unresolved request logs, and legacy file inventory rows.
- Generates expected old URL candidates from legacy DB rows using evidence-backed router patterns and explicit URL fields.
- Resolves only when an existing exact redirect, query resolver, or mapped file inventory proves a safe target.
- Leaves unresolved valuable URLs in backlog with explicit no-homepage fallback notes.
- Triage groups unresolved URL-only inventory rows into resolver candidates, mapping gaps, file blockers, target-module blockers, and unknown URL shapes.
- Redirect evidence exports split runtime preview-ready rows from blocked/backlog rows with explicit blockers.
- Does not create redirects, import content, publish content, or promote files.

Latest real Phase 5 pass with file inventory:

- Rows: `25978`.
- Resolved rows: `7`.
- Unresolved/backlog rows: `25971`.
- File rows: `25182`.
- Status counts: `file_inventory_missing_source=25182`, `resolved_by_query_resolver=7`, `unresolved_for_continuity_phase=454`, `unresolved_unknown_legacy_url=335`.
- Latest artifacts:
  - `storage/app/private/legacy-import-exports/url-continuity/20260705_211318_url_continuity_inventory.md`
  - `storage/app/private/legacy-import-exports/url-continuity/20260705_211318_url_continuity_inventory.csv`
  - `storage/app/private/legacy-import-exports/url-continuity/20260705_211318_url_continuity_inventory.json`

Latest URL-only pass:

- Rows: `796`.
- Resolved rows: `7`.
- Unresolved/backlog rows: `789`.
- Main unresolved handler groups: `members:councils:show=120`, `petrol:councils:show=65`, `admin:councils:show=56`, `admin:items:show=54`, `pharm:councils:show=49`.
- Latest artifacts:
  - `storage/app/private/legacy-import-exports/url-continuity/20260705_211357_url_continuity_inventory.md`
  - `storage/app/private/legacy-import-exports/url-continuity/20260705_211357_url_continuity_inventory.csv`
  - `storage/app/private/legacy-import-exports/url-continuity/20260705_211357_url_continuity_inventory.json`

Latest URL-only triage:

- Scanned URL-only rows: `796`.
- Unresolved rows triaged: `789`.
- Resolver candidates: `12`, all `root:items:show` with `jx_categories` mapping evidence.
- Blocked rows: `777`.
- Triage counts: `blocked_missing_target_module=395`, `unknown_legacy_url=335`, `needs_phase4_mapping=25`, `blocked_file_url=22`, `resolver_candidate=12`.
- Latest artifacts:
  - `storage/app/private/legacy-import-exports/url-continuity-triage/20260705_213658_url_continuity_triage.md`
  - `storage/app/private/legacy-import-exports/url-continuity-triage/20260705_213658_url_continuity_triage_groups.csv`
  - `storage/app/private/legacy-import-exports/url-continuity-triage/20260705_213658_url_continuity_triage_rows.csv`
  - `storage/app/private/legacy-import-exports/url-continuity-triage/20260705_213658_url_continuity_triage.json`

Latest generated DB-backed URL inventory:

- Source rows scanned: `29176`.
- Generated URL rows: `12749`.
- Resolved rows: `6070`.
- Unresolved/backlog rows: `6679`.
- Source counts: `generated_router_url=12580`, `generated_explicit_url=169`.
- Latest artifacts:
  - `storage/app/private/legacy-import-exports/generated-url-inventory/20260705_233452_generated_url_inventory.md`
  - `storage/app/private/legacy-import-exports/generated-url-inventory/20260705_233452_generated_url_inventory.csv`
  - `storage/app/private/legacy-import-exports/generated-url-inventory/20260705_233452_generated_url_inventory.json`

Latest generated URL triage:

- Unresolved generated rows triaged: `6679`.
- Resolver candidates: `3834`.
- Blocked/backlog rows: `2845`.
- Triage counts: `resolver_candidate=3834`, `needs_phase4_mapping=2339`, `blocked_missing_target_module=505`, `unknown_legacy_url=1`.
- Latest artifacts:
  - `storage/app/private/legacy-import-exports/url-continuity-triage/20260705_233523_url_continuity_triage.md`
  - `storage/app/private/legacy-import-exports/url-continuity-triage/20260705_233523_url_continuity_triage_groups.csv`
  - `storage/app/private/legacy-import-exports/url-continuity-triage/20260705_233523_url_continuity_triage_rows.csv`
  - `storage/app/private/legacy-import-exports/url-continuity-triage/20260705_233523_url_continuity_triage.json`

Latest redirect evidence:

- Generated evidence rows: `12749`.
- Generated redirect preview rows: `6070`.
- Generated blocked/backlog rows: `6679`.
- Generated evidence statuses: `resolver_ready=6070`, `needs_imported_target=2486`, `blocked_missing_target_module=460`, `blocked_phase3_findings=574`, `blocked_file_dependency=819`, `needs_phase4_mapping=2339`, `unknown_legacy_url=1`.
- Discovered-link preview rows: `7`.
- Discovered-link blocked/backlog rows: `789`.
- Latest artifacts:
  - `storage/app/private/legacy-import-exports/redirect-evidence/20260706_142351_redirect_evidence.md`
  - `storage/app/private/legacy-import-exports/redirect-evidence/20260706_142351_redirect_evidence_preview.csv`
  - `storage/app/private/legacy-import-exports/redirect-evidence/20260706_142351_redirect_evidence_blocked.csv`
  - `storage/app/private/legacy-import-exports/redirect-evidence/20260706_142351_redirect_evidence.json`
  - `storage/app/private/legacy-import-exports/redirect-evidence/discovered/20260706_142417_redirect_evidence.md`

Remaining Phase 5 gates:

- Final redirect persistence is gated by approved mappings and imported target records.
- File redirect evidence is gated by mounting/fetching old file bytes.
- Do not persist final redirect mappings until resolver evidence and review approval exist.
- Old file URL resolution remains blocked by missing `OLD_PUBLIC_ROOT` / missing file bytes.

## Do Not Do Yet

- Do not add broad final redirect mappings.
- Do not continue slug cleanup as the primary migration path.
- Do not import real modules without approved runners and row-level traceability.
- Do not resolve overloaded IDs like `cat_id` outside a module-specific `(subsite, dir, page)` context.
- Do not redirect unresolved valuable legacy URLs to the homepage.
