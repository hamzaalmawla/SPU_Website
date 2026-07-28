# Legacy Import Runbook

## Scope

These import seeders are manual only.

They do not run from `DatabaseSeeder`.

Current import wrappers:

- `ImportLegacyAdminsSeeder`
- `ImportLegacySettingsSeeder`
- `ImportLegacyStaticPagesSeeder`
- `ImportLegacyHomepageSeeder`
- `ImportLegacyLinksSeeder`

All legacy import modules are disabled by default in `config/old_database.php`.

Broad legacy import is intentionally blocked unless `OLD_DB_ALLOW_BROAD_IMPORT=true` is set. Even then, each individual module must also be explicitly enabled before it can run.

## Configure Legacy Connection

Set the old database connection values in local environment only.

If `OLD_DB_CONNECTION` accidentally matches the app default connection, legacy tooling uses a dedicated `legacy_mysql` alias with the configured old database settings instead of repointing the application connection.

Example variables:

```env
OLD_DB_CONNECTION=legacy_mysql
OLD_DB_HOST=127.0.0.1
OLD_DB_PORT=3306
OLD_DB_DATABASE=spu_legacy
OLD_DB_USERNAME=root
OLD_DB_PASSWORD=
OLD_DB_CHARSET=utf8mb4
OLD_DB_COLLATION=utf8mb4_unicode_ci
OLD_DB_ENGINE=InnoDB
OLD_DB_ALLOW_BROAD_IMPORT=false
OLD_PUBLIC_ROOT=/path/to/legacy/public/root
```

## Phase 6 Restore

Production deployments must run schema migrations with `php artisan migrate --force`. Never run `migrate:fresh` against a production database because it drops all managed content before rebuilding the schema.

For a new local environment or an explicitly approved disaster-recovery database, rebuild the foundation and replay the still-approved Phase 6 lanes with:

```bash
php artisan migrate:fresh --seed
php artisan legacy-import:phase6-restore
php artisan legacy-import:phase6-restore --write --approve=phase6-restore --batch=phase6-restore-YYYYMMDD
```

The first restore invocation is a dry run. The approved write invocation rebuilds classification, mapping, staging, and approval prerequisites before restoring these lanes in one workflow:

- locations, disabled for review
- alumni and honor students, enabled according to legacy visibility
- faculty profiles are not restored; public staff requires the separate private `jx_councils` review-packet workflow
- research publications are not restored; audit reconciliation blocks `jx_member_*` because those records belong to `/members/`
- news and announcements are not restored; they require a separate approved category review packet
- static pages, disabled drafts
- footer menu links, disabled
- reviewed safe settings, live

The restore is idempotent through migration logs and applies version-controlled source corrections before cleaning and duplicate detection. It requires the configured legacy database to remain available. Database backups remain the authoritative production disaster-recovery mechanism; this command is a controlled reconstruction tool, not a replacement for backups.

FAQ and career-link review data are deliberately excluded from `legacy-import:phase6-restore`. They must use the separate packet-driven workflows below and are never restored automatically.

### FAQ Review Packets

The audited `jx_faqs` source contains 1,553 rows: language 1 has 887 rows with 24 visible/answered, language 2 has 283 rows with 23 visible/answered, language 7 has 379 rows with none visible/answered, and languages 0, 3, and 6 account for the remaining four rows. Only exact mappings `1=ar` and `2=en` are supported. Unsupported locales are never synthesized or used as fallback content.

Generate the private candidate and metadata-only backlog packet:

```bash
php artisan legacy-import:faq-review-packets
php artisan legacy-import:faq-review-packets --disk=local --dir=legacy-import-exports/faq-review-packets --json
```

The exporter never selects or exports values from `first_name`, `last_name`, `email`, `country`, or `phone`. Candidate question and answer text is private evidence and may still contain contact-like text, which is marked `content_contains_contact_pattern`; keep all packets off public storage and out of version control. Backlog rows contain metadata only and never include question, answer, subject, or submitter values.

Review `faq_candidates.csv` privately. Set only explicitly approved rows to `approval_decision=import` and `approved_target=faqs`, then dry-run and write with the separate token:

```bash
php artisan legacy-import:faqs legacy-import-private/reviewed-faqs.csv --disk=local --batch=legacy-faq-review
php artisan legacy-import:faqs legacy-import-private/reviewed-faqs.csv --disk=local --write --approve=legacy-faq-import --batch=legacy-faqs-YYYYMMDD
```

The importer re-reads approved source IDs and does not trust candidate text. Contact-pattern content remains blocked even when approved. Imported records use the disabled `legacy-faq-review` category and one source locale only. Submitter PII values are never selected, imported, or logged; migration metadata stores only presence booleans. The workflow creates no redirects, routes, publication state, or media.

### Career-Link Review Packets

The audited `jx_job_sites` source contains exactly three visible external-link records with Arabic/English names, URL, photo evidence, and ordering. Generate and privately review the packet:

```bash
php artisan legacy-import:career-link-review-packets
php artisan legacy-import:career-link-review-packets --disk=local --dir=legacy-import-exports/career-link-review-packets --json
php artisan legacy-import:career-links legacy-import-private/reviewed-career-links.csv --disk=local --batch=legacy-career-review
php artisan legacy-import:career-links legacy-import-private/reviewed-career-links.csv --disk=local --write --approve=legacy-career-links-import --batch=legacy-career-links-YYYYMMDD
```

Only absolute `http` and `https` URLs are eligible. The importer re-verifies URL, visibility, titles, duplicates, and mappings from the source. It creates disabled external links with only the locales actually present. Legacy photo paths are retained as migration evidence only; no media is imported and no redirects or routes are created.

The current FAQ and career-link tables are not wired to public rendering or Filament. These imports are disabled archival/review data only and do not constitute public FAQ, careers, CMS, migration-parity, or feature completion.

### Approved News Packets

Generate private, read-only review packets for root news and announcements:

```bash
php artisan legacy-import:category-review-packets --subsite=root --service=3 --service=4 --disk=local
```

Copy the relevant generated CSVs to a private review location, review each candidate, and fill `approval_decision` and `approved_target` only in the reviewed copies. A row is eligible only when those fields are exactly `import` and `news` after case and surrounding-whitespace normalization. Blank decisions are never candidates. Packet exports and reviewed copies can contain sensitive migration evidence: keep them on private storage and never commit them.

Run the reviewed packet first without writes, inspect all reason counts, and then use the separate approval token for the gated write:

```bash
php artisan legacy-import:news legacy-import-private/reviewed-root-news.csv --disk=local --batch=phase6-news-review
php artisan legacy-import:news legacy-import-private/reviewed-root-news.csv --disk=local --write --approve=phase6-news --batch=phase6-news-YYYYMMDD
```

Every approved article is imported as a disabled draft with no publish or schedule date and `noindex,nofollow` SEO. The import does not create continuity redirects, does not copy one locale into another, and keeps attachment files deferred for separate media reconciliation.

### Members Reconciliation Evidence

Generate private, read-only `/members/` evidence packets for `jx_member_categories` and `jx_member_items`:

```bash
php artisan legacy-import:members-review-packets
php artisan legacy-import:members-review-packets --service=1 --service=2 --disk=local --json
```

The command writes one category CSV and one item CSV for each selected service, plus a manifest and Markdown summary. It reads only scalar metadata and database-computed text lengths; legacy HTML descriptions and data are not exported. Service 1 is evidence for publication/research-like member output requiring publication proof. Service 2 is teaching/course-like archive evidence and must not be treated as research.

These packets are evidence only. There is no members approval command, importer, target mapping workflow, or redirect workflow. `candidate_target_policy` remains `unresolved_product_decision`, and approval and target fields are intentionally blank.

`jx_member_categories.parent` is a staff-owner identity, not a category hierarchy. The same owner ID can occur in both `jx_councils` and `jx_councils1`; packets report both sources independently and mark that condition as ambiguous. Product and ownership reconciliation must resolve the intended person source and destination before any target design or continuity decision.

All writes through `legacy-import:research-publications` are frozen regardless of approval token. Its dry run is retained only as non-authoritative historical inspection and does not establish that a service-1 category is a publication. The `jx_member_*` write freeze remains in force pending `/members/` product, ownership, attachment, and publication-proof reconciliation.

## Run Manual Imports

Run one module at a time.

Before running a module, temporarily set that module's `enabled` flag to `true` in local-only configuration. Do not enable modules by default or commit enabled module flags.

```bash
php artisan db:seed --class="Database\\Seeders\\LegacyImport\\ImportLegacyAdminsSeeder"
php artisan db:seed --class="Database\\Seeders\\LegacyImport\\ImportLegacySettingsSeeder"
php artisan db:seed --class="Database\\Seeders\\LegacyImport\\ImportLegacyStaticPagesSeeder"
php artisan db:seed --class="Database\\Seeders\\LegacyImport\\ImportLegacyHomepageSeeder"
php artisan db:seed --class="Database\\Seeders\\LegacyImport\\ImportLegacyLinksSeeder"
```

## Reporting

Available commands:

```bash
php artisan legacy-import:file-inventory
php artisan legacy-import:file-inventory --write
php artisan legacy-import:inventory
php artisan legacy-import:inventory homepage
php artisan legacy-import:dry-run homepage
php artisan legacy-import:run homepage --dry-run --batch=homepage-dry-run-review
php artisan legacy-import:cleaning-report news
php artisan legacy-import:cleaning-report news --record-quarantine
php artisan legacy-import:integrity-report news
php artisan legacy-import:integrity-report news --record-quarantine
php artisan legacy-import:internal-links-report news
php artisan legacy-import:internal-links-report news --record-review
php artisan legacy-import:quarantine-export
php artisan legacy-import:quarantine-export news --format=json
php artisan legacy-import:quarantine-export news --reason=legacy_internal_link
php artisan legacy-import:quarantine-summary news
php artisan legacy-import:decision-plan news
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
php artisan legacy-import:url-continuity-inventory
php artisan legacy-import:url-continuity-inventory --without-files --json
php artisan legacy-import:url-continuity-triage legacy-import-exports/url-continuity/20260705_211357_url_continuity_inventory.csv
php artisan legacy-import:generated-url-inventory
php artisan legacy-import:url-continuity-triage legacy-import-exports/generated-url-inventory/20260705_233452_generated_url_inventory.csv
php artisan legacy-import:redirect-evidence legacy-import-exports/generated-url-inventory/20260705_233452_generated_url_inventory.csv legacy-import-exports/url-continuity-triage/20260705_233523_url_continuity_triage_rows.csv
php artisan legacy-import:phase6-candidates
php artisan legacy-import:phase6-candidates menu_links
php artisan legacy-import:phase6-approve menu_links --write --approve=phase6-menu-links
php artisan legacy-import:phase6-menu-links --write --approve=phase6-menu-links --batch=phase6-menu-links-approval
php artisan legacy-import:phase6-approve pages --write --approve=phase6-pages
php artisan legacy-import:phase6-pages --write --approve=phase6-pages --batch=phase6-pages-approval
php artisan legacy-import:phase6-settings-mapping
php artisan legacy-import:phase6-settings --input=legacy-import-exports/phase6-settings/20260706_152803_phase6_settings_mapping_safe_mappings.csv --batch=phase6-settings-dry-run
php artisan legacy-import:phase6-settings --input=legacy-import-exports/phase6-settings/20260706_152803_phase6_settings_mapping_safe_mappings.csv --write --approve=phase6-settings --batch=phase6-settings-approval
php artisan legacy-import:locations
php artisan legacy-import:locations --write --approve=phase6-locations --batch=phase6-locations-20260708
php artisan legacy-import:news-review
php artisan legacy-import:members-review-packets
php artisan legacy-import:news-slug-plan --limit=50
php artisan legacy-import:news-slug-plan --all --json --output=storage/app/legacy-news-slug-plan.json
php artisan legacy-import:news-slug-apply --all --approve=news-slug-cleanup
php artisan legacy-import:report
php artisan legacy-import:report faqs --details
php artisan legacy-import:verify
php artisan legacy-import:verify research
php artisan legacy-import:audit
php artisan legacy-import:audit faqs --details
php artisan legacy-import:export-missing
php artisan legacy-import:export-missing faqs
```

Direct database checks are still useful for deeper inspection:

```bash
php artisan tinker
DB::table('migration_logs')->selectRaw('module, status, count(*) as c')->groupBy('module', 'status')->get();
DB::table('migration_rejections')->selectRaw('module, reason_code, count(*) as c')->groupBy('module', 'reason_code')->get();
DB::table('legacy_record_snapshots')->selectRaw('module, classification, count(*) as c')->groupBy('module', 'classification')->get();
```

## Logging Behavior

Successful imports write to `migration_logs` with status `success`.

Skipped rows write to `migration_logs` with status `skipped`.

Rejected rows write to `migration_rejections` with reason codes such as:

- `invalid_email`
- `unsupported_locale`
- `unsafe_html`
- `conflicting_setting`
- `unknown_mapping`
- `missing_parent`
- `duplicate_conflict`
- `base64_inline_image`
- `spam_link`
- `suspicious_external_url`
- `missing_required_value`
- `orphaned_child`
- `duplicate_legacy_content`
- `legacy_internal_link`

## Phase 3 Cleaning Reports

Inspect configured fields without importing content:

```bash
php artisan legacy-import:cleaning-report news
php artisan legacy-import:cleaning-report static_pages
php artisan legacy-import:cleaning-report links
php artisan legacy-import:cleaning-report research
php artisan legacy-import:cleaning-report faculty_members
```

Persist blocked cleaning decisions to `migration_rejections` for review:

```bash
php artisan legacy-import:cleaning-report news --record-quarantine
```

Machine-readable output:

```bash
php artisan legacy-import:cleaning-report news --json
```

Rules:

- dry-run does not write
- `--record-quarantine` records only blocked fields, not target CMS content
- duplicate review rows are skipped on repeated runs
- quarantined content remains preserved for review; it is not deleted
- approved controlled runners are blocked when the cleaning report has blocked fields

## Phase 3 Integrity Reports

Inspect configured duplicate and orphan rules without importing content:

```bash
php artisan legacy-import:integrity-report news
php artisan legacy-import:integrity-report static_pages
php artisan legacy-import:integrity-report links
php artisan legacy-import:integrity-report research
php artisan legacy-import:integrity-report faculty_members
```

Persist duplicate/orphan blockers to `migration_rejections` for review:

```bash
php artisan legacy-import:integrity-report news --record-quarantine
```

Machine-readable output:

```bash
php artisan legacy-import:integrity-report news --json
```

Rules:

- dry-run does not write
- `--record-quarantine` records only duplicate/orphan blockers, not target CMS content
- duplicate review rows are skipped on repeated runs
- approved controlled runners are blocked when the integrity report has blocked rows

## Phase 3 Internal Link Reports

Extract old internal URLs embedded in configured legacy fields without importing content:

```bash
php artisan legacy-import:internal-links-report news
php artisan legacy-import:internal-links-report static_pages
php artisan legacy-import:internal-links-report links
php artisan legacy-import:internal-links-report research
php artisan legacy-import:internal-links-report faculty_members
```

Persist extracted links to `migration_rejections` for continuity review:

```bash
php artisan legacy-import:internal-links-report news --record-review
```

Machine-readable output:

```bash
php artisan legacy-import:internal-links-report news --json
```

Rules:

- dry-run does not write
- `--record-review` records extracted internal links, not target CMS content
- duplicate review rows are skipped on repeated runs
- review rows include `raw_summary.legacy_path` so URL inventory exports can consume them

## Quarantine Review Export

Export `migration_rejections` rows for editor or migration-review workflows:

```bash
php artisan legacy-import:quarantine-export
php artisan legacy-import:quarantine-export news
php artisan legacy-import:quarantine-export news --format=json
php artisan legacy-import:quarantine-export news --reason=legacy_internal_link
php artisan legacy-import:quarantine-summary news
php artisan legacy-import:decision-plan news
```

Rules:

- export is read-only and does not import, clean, publish, or delete content
- default format is CSV for editor review; JSON is available for machine processing
- exported rows include module, source table, source ID, reason, field, previews, and `legacy_path` when present
- exported files are written under `storage/app/private/legacy-import-exports/quarantine` by default on the local disk

Use `legacy-import:quarantine-summary` when the raw CSV is too technical. It creates a Markdown summary, grouped issue CSV, and smaller `_needs_decision.csv` file under `storage/app/private/legacy-import-exports/quarantine-summary` on the local disk.

Use `legacy-import:decision-plan <module>` to convert quarantine rows into a machine-readable auto-decision plan for future controlled import runners. It does not import content, publish content, or create redirects.

Current automatic policies:

- sanitized HTML is accepted via cleaned previews
- inline base64 images are stripped from public HTML and left traceable for later media extraction
- invalid required contact emails are skipped until identity data is verified; email repairs are not guessed
- unsupported locales are skipped in this AR/EN foundation phase
- orphaned rows are skipped until verified parent mappings exist
- duplicate groups receive deterministic canonical/skip decisions
- legacy internal links are resolved when safe, otherwise retained in the continuity backlog without homepage guessing

Latest Phase 3 real-report baseline:

- All configured cleaning, integrity, and internal-link reports are warning-free against the current `spu_legacy` schema.
- Aggregate quarantine export: `storage/app/private/legacy-import-exports/quarantine/20260705_183555_quarantine_review.csv` and `storage/app/private/legacy-import-exports/quarantine/20260705_183557_quarantine_review.json`.
- Aggregate summary: `storage/app/private/legacy-import-exports/quarantine-summary/20260705_183916_quarantine_summary.md` with zero user decision groups.
- Latest decision plans are under `storage/app/private/legacy-import-exports/decision-plans/` and currently have `manual_review_count = 0`.

## Phase 4 Classification Reports

Export the read-only classification summary and mapping sheet before any import work:

```bash
php artisan legacy-import:classification-report
php artisan legacy-import:classification-report news
php artisan legacy-import:classification-report --json
```

Rules:

- export is read-only and does not import, publish, promote files, or create redirects
- every source row covered by `old_database.classification_rules` receives one Phase 4 bucket
- invalid or missing classification buckets fall back to `quarantine`
- high-risk table coverage is reported explicitly
- rows with legacy file references are marked as `missing_external_source_root` until `OLD_PUBLIC_ROOT` is configured

Latest Phase 4 real-report baseline:

- Aggregate report: `storage/app/private/legacy-import-exports/classification/20260705_194115_classification_report.md`.
- Mapping sheet: `storage/app/private/legacy-import-exports/classification/20260705_194115_classification_report_mapping.csv`.
- JSON summary: `storage/app/private/legacy-import-exports/classification/20260705_194115_classification_report.json`.
- `38689` source rows classified across `27` configured legacy source tables.
- High-risk table coverage: `11/11`.
- Unknown/unruled rows: `0`.
- Bucket counts: `canonical_rebuild_now=4944`, `file_only_preserve=22797`, `archive_now_remodel_later=10897`, `redirect_to_equivalent=23`, `retire_after_approval=28`.
- File dependency status: `26585` rows require legacy file reconciliation and are marked `missing_external_source_root` because old file bytes are not mounted.

## Phase 4 Mapping Proposals

Persist proposed mappings from a classification mapping CSV only after reviewing the dry-run:

```bash
php artisan legacy-import:mapping-proposals legacy-import-exports/classification/20260705_194115_classification_report_mapping.csv
php artisan legacy-import:mapping-proposals legacy-import-exports/classification/20260705_194115_classification_report_mapping.csv --write
```

Rules:

- dry-run is the default and writes nothing
- `--write` creates or updates `mapping_status=proposed` rows in `legacy_content_mappings`
- approved mappings are never overwritten by proposal imports
- proposal imports do not import content, publish content, promote files, approve mappings, or create redirects

Current proposal baseline:

- `38689` proposed mappings persisted.
- Status counts: all rows are `mapping_status=proposed`.
- Classification counts: `canonical_rebuild_now=4944`, `file_only_preserve=22797`, `archive_now_remodel_later=10897`, `redirect_to_equivalent=23`, `retire_after_approval=28`.
- Target type counts: `canonical_content_candidate=4944`, `legacy_file_candidate=22797`, `archive_candidate=10897`, `redirect_candidate=23`, `retire_candidate=28`.

## Phase 4/11 Review Candidate Reports

Export a read-only candidate report from proposed mappings before any approvals:

```bash
php artisan legacy-import:review-candidates
php artisan legacy-import:review-candidates links --json
```

Rules:

- report is read-only and does not approve mappings, import content, publish content, promote files, or create redirects
- safe candidates require a low-risk bucket: `redirect_to_equivalent`, `archive_now_remodel_later`, or `retire_after_approval`
- safe candidates must have `file_dependency=none`
- safe candidates must have no Phase 3 findings
- rows with sanitizer/internal-link findings that can be handled by decision-plan policy are separated as `decision_plan_candidate`
- rows with file dependencies, non-low-risk buckets, duplicate findings, orphan findings, or other unresolved Phase 3 blockers are exported as blocked

Latest review-candidate baseline:

- Report: `storage/app/private/legacy-import-exports/review-candidates/20260705_200139_review_candidates.md`.
- Safe candidates: `storage/app/private/legacy-import-exports/review-candidates/20260705_200139_review_candidates_safe_candidates.csv`.
- Decision-plan candidates: `storage/app/private/legacy-import-exports/review-candidates/20260705_200139_review_candidates_decision_plan_candidates.csv`.
- Blocked rows: `storage/app/private/legacy-import-exports/review-candidates/20260705_200139_review_candidates_blocked.csv`.
- JSON summary: `storage/app/private/legacy-import-exports/review-candidates/20260705_200139_review_candidates.json`.
- Scanned proposed mappings: `38689`.
- Safe candidates: `7107`.
- Decision-plan candidates: `2`.
- Blocked rows: `31580`.
- Main blocker counts: `file_dependency_missing_external_source_root=26585`, `not_low_risk_bucket=27741`, `phase3_findings_block_review=2557`.
- Safe redirect candidates: `20` from `links`.

## Full Database Staging Review

Build review/audit staging records for every persisted legacy mapping without importing content:

```bash
php artisan legacy-import:staging-review
php artisan legacy-import:staging-review --write
php artisan legacy-import:staging-review links --json
```

Rules:

- dry-run is the default and writes only export artifacts
- `--write` upserts rows into `legacy_review_items`
- staging review records are not CMS content and are not import approval
- the command does not approve mappings, import content, publish content, promote files, or create redirects
- rows retain source table/source ID, classification, mapping status, review status, Phase 3 reasons, cleaning status, URL status, file dependency, and blockers
- review candidates still require low-risk classification, no file dependency, and no blocking Phase 3 findings
- blocked rows stay visible in staging instead of being discarded

Latest staging-review baseline:

- Dry-run report: `storage/app/private/legacy-import-exports/staging-review/20260705_235411_staging_review.md`.
- Written report: `storage/app/private/legacy-import-exports/staging-review/20260705_235933_staging_review.md`.
- Written CSV: `storage/app/private/legacy-import-exports/staging-review/20260705_235933_staging_review.csv`.
- Written JSON: `storage/app/private/legacy-import-exports/staging-review/20260705_235933_staging_review.json`.
- Staged review rows in `legacy_review_items`: `38689`.
- Review candidates: `7107`.
- Decision-plan candidates: `2`.
- Blocked rows: `31580`.
- Classification counts: `canonical_rebuild_now=4944`, `file_only_preserve=22797`, `archive_now_remodel_later=10897`, `redirect_to_equivalent=23`, `retire_after_approval=28`.
- Main blocker counts: `file_dependency_missing_external_source_root=26585`, `not_low_risk_bucket=27741`, `phase3_findings_block_review=2557`.

## Staging Summary Review Packets

Export grouped summaries from `legacy_review_items` for review without opening the full staging CSV:

```bash
php artisan legacy-import:staging-summary
php artisan legacy-import:staging-summary --status=review_candidate --sample-limit=10
php artisan legacy-import:staging-summary links --status=review_candidate --sample-limit=20
```

Rules:

- summary exports are read-only
- summaries do not approve mappings, import content, publish content, promote files, or create redirects
- groups are derived from staged review rows, not raw legacy tables
- samples are capped by `--sample-limit` per module/status/classification
- filtered summaries should be used to create small approval packets before any approval mechanism is added

Latest staging-summary baseline:

- Full summary report: `storage/app/private/legacy-import-exports/staging-summary/20260706_001855_staging_summary.md`.
- Full summary groups: `storage/app/private/legacy-import-exports/staging-summary/20260706_001855_staging_summary_groups.csv`.
- Full summary samples: `storage/app/private/legacy-import-exports/staging-summary/20260706_001855_staging_summary_samples.csv`.
- Full summary JSON: `storage/app/private/legacy-import-exports/staging-summary/20260706_001855_staging_summary.json`.
- Review-candidate summary: `storage/app/private/legacy-import-exports/staging-summary/20260706_001903_staging_summary_review_candidate.md`.
- Links review packet candidate: `storage/app/private/legacy-import-exports/staging-summary/20260706_001914_staging_summary_links_review_candidate.md`.
- Full staged rows summarized: `38689`.
- Review candidates summarized: `7107`.
- Links redirect review candidates summarized: `20`.

## Phase 5 URL Continuity Inventory

Export a read-only URL continuity inventory before creating any final redirects:

```bash
php artisan legacy-import:url-continuity-inventory
php artisan legacy-import:url-continuity-inventory --without-files --json
```

Sources included:

- existing `legacy_exact_redirects`
- Phase 3 `legacy_internal_link` review rows
- Phase 4 `redirect_to_equivalent` mapping proposals
- unresolved legacy request logs
- `legacy_file_inventory` rows unless `--without-files` is used

Rules:

- report is read-only and does not create redirects
- query parameters are normalized into sorted query signatures
- rows resolve only when an exact redirect, query resolver, or mapped file inventory proves a safe target
- unresolved valuable URLs stay in backlog and explicitly note not to redirect to homepage

Latest Phase 5 baseline with files:

- Report: `storage/app/private/legacy-import-exports/url-continuity/20260705_211318_url_continuity_inventory.md`.
- CSV: `storage/app/private/legacy-import-exports/url-continuity/20260705_211318_url_continuity_inventory.csv`.
- JSON: `storage/app/private/legacy-import-exports/url-continuity/20260705_211318_url_continuity_inventory.json`.
- Rows: `25978`.
- Resolved rows: `7`.
- Unresolved/backlog rows: `25971`.
- File rows: `25182`, all currently `file_inventory_missing_source`.

Latest URL-only baseline:

- Report: `storage/app/private/legacy-import-exports/url-continuity/20260705_211357_url_continuity_inventory.md`.
- CSV: `storage/app/private/legacy-import-exports/url-continuity/20260705_211357_url_continuity_inventory.csv`.
- JSON: `storage/app/private/legacy-import-exports/url-continuity/20260705_211357_url_continuity_inventory.json`.
- Rows: `796`.
- Resolved rows: `7`.
- Unresolved/backlog rows: `789`.
- Main unresolved handler groups: `members:councils:show=120`, `petrol:councils:show=65`, `admin:councils:show=56`, `admin:items:show=54`, `pharm:councils:show=49`.

## Phase 5 URL Continuity Triage

Triage unresolved URL-only inventory rows before building resolvers:

```bash
php artisan legacy-import:url-continuity-triage legacy-import-exports/url-continuity/20260705_211357_url_continuity_inventory.csv
```

Rules:

- triage is read-only and does not create redirects
- resolved rows are skipped
- `resolver_candidate` requires a parseable source ID and existing Phase 4 mapping evidence
- `needs_phase4_mapping` means the URL shape is known but source mapping is missing
- `blocked_missing_target_module` means the old handler points to a module/subsite not production-ready in current scope
- `blocked_file_url` means file continuity remains blocked by missing legacy file bytes or unmapped inventory
- `unknown_legacy_url` means no known handler exists; do not guess or redirect to homepage

Latest triage baseline:

- Report: `storage/app/private/legacy-import-exports/url-continuity-triage/20260705_213658_url_continuity_triage.md`.
- Groups: `storage/app/private/legacy-import-exports/url-continuity-triage/20260705_213658_url_continuity_triage_groups.csv`.
- Rows: `storage/app/private/legacy-import-exports/url-continuity-triage/20260705_213658_url_continuity_triage_rows.csv`.
- JSON: `storage/app/private/legacy-import-exports/url-continuity-triage/20260705_213658_url_continuity_triage.json`.
- Scanned URL-only rows: `796`.
- Unresolved rows triaged: `789`.
- Resolver candidates: `12`, all `root:items:show` with `jx_categories` mapping evidence.
- Blocked rows: `777`.
- Triage counts: `blocked_missing_target_module=395`, `unknown_legacy_url=335`, `needs_phase4_mapping=25`, `blocked_file_url=22`, `resolver_candidate=12`.

## Phase 5 Generated URL Inventory

Generate expected legacy URL candidates from legacy DB rows before treating discovered links as representative:

```bash
php artisan legacy-import:generated-url-inventory
php artisan legacy-import:generated-url-inventory jx_categories --limit=100
php artisan legacy-import:url-continuity-triage legacy-import-exports/generated-url-inventory/20260705_233452_generated_url_inventory.csv
```

Sources included:

- explicit URL/file columns on configured high-value tables
- `jx_categories` item-detail router URLs
- `jx_member_categories` member item-detail router URLs
- `jx_councils` / `jx_councils1` council/profile detail router URLs
- `jx_site_static_pages` static-page router URLs

Rules:

- generation is read-only and does not create redirects
- only evidence-backed router patterns from `OLD_SYSTEM_FINDINGS.md` are generated
- generated rows use the same CSV shape as the URL continuity inventory so they can be triaged by `legacy-import:url-continuity-triage`
- unresolved generated URLs explicitly remain backlog and must not redirect to homepage

## Phase 5 Redirect Decisions

`legacy-import:redirect-evidence` now produces a preview CSV with blank `approval_decision`, `approved_by`, and `approval_notes` fields. Evidence is not a redirect rule until those fields are explicitly reviewed.

Only rows that remain `preview_ready`, `resolver_ready`, and `runtime_resolver` are eligible. Set `approval_decision` to `redirect` and provide one consistent `approved_by` value for the batch. Leave rejected or deferred rows blank or give them a non-redirect decision.

Preview the reviewed packet without writing:

```bash
php artisan legacy-import:redirect-decisions <reviewed-preview.csv> --batch=redirect-review-YYYYMMDD
```

Apply after reviewing eligible, idempotent, conflict, and skipped counts:

```bash
php artisan legacy-import:redirect-decisions <reviewed-preview.csv> \
  --batch=redirect-review-YYYYMMDD \
  --write \
  --approve=legacy-redirect-apply
```

Preview and apply rollback for exactly that batch:

```bash
php artisan legacy-import:redirect-rollback redirect-review-YYYYMMDD
php artisan legacy-import:redirect-rollback redirect-review-YYYYMMDD \
  --write \
  --approve=legacy-redirect-rollback
```

Safety rules:

- router redirects are keyed by normalized path plus normalized query signature;
- `service`, `ser`, and `Ser` share one canonical identity, as do `cat_id` and `cat`;
- query order does not affect matching;
- unsupported locales, blank approvals, stale runtime targets, unsafe targets, self-redirects, duplicates, and existing conflicts are rejected;
- the current query resolver must still resolve to the packet target at apply time;
- existing manual rules are never overwritten;
- writes are transactional and recorded in `legacy_redirect_decision_batches` and `migration_logs`;
- rollback deletes only redirects created by the selected decision batch;
- continuity cache is invalidated after apply and rollback.

Current generated evidence has blank approval fields, so running this workflow without editorial decisions creates zero redirects.

Latest regenerated decision evidence:

- Generated URL inventory: `storage/app/private/legacy-import-exports/generated-url-inventory/20260728_223735_generated_url_inventory.csv`.
- Triage rows: `storage/app/private/legacy-import-exports/url-continuity-triage/20260728_223810_url_continuity_triage_rows.csv`.
- Reviewed decision input: `storage/app/private/legacy-import-exports/redirect-evidence/20260728_223901_redirect_evidence_preview.csv`.
- Total/preview-ready/blocked: `11919/12/11907`.
- Current blank-approval dry-run: `12` scanned, `0` approved, `0` eligible, `0` created, `12` skipped.

Approved subsite-home batch:

- Reviewed packet: `storage/app/private/legacy-import-exports/redirect-evidence/20260729_approved_subsite_home_redirects.csv`.
- Batch: `approved-subsite-homes-20260729`.
- Scope: root AR/EN plus Business, Petroleum, AI, Pharmacy, Dentistry, and Medicine AR/EN homes.
- Dry-run: `14` scanned, `14` approved, `14` eligible, `0` skipped.
- Apply: `14` created and `14` success migration logs.
- Idempotency replay: `0` created, `14` idempotent.
- Validation: all redirect rules valid; all 14 source signatures resolve with `301`; all 14 targets render `200`.
- Rollback preview: `14` batch redirects, `0` deleted. The batch remains applied.

## Public Staff Audit And Approval

Public staff reconciliation is a separate, private workflow. The only source eligible for this workflow is `jx_councils`. `jx_councils1` is not proven to represent public profiles and must not be imported by the Phase 6 restore or used to create public faculty profiles. It is read only for cross-source email overlap evidence.

Export all 14 service packets:

```bash
php artisan legacy-import:public-staff-review-packets
php artisan legacy-import:public-staff-review-packets --json
```

Export selected services to a private location:

```bash
php artisan legacy-import:public-staff-review-packets --service=4 --service=13 --disk=local --dir=legacy-import-exports/public-staff-review-packets
```

Packet rules:

- output contains metadata and `CHAR_LENGTH` evidence only; legacy AR/EN HTML is never exported
- every row starts with blank `approval_decision` and `approved_target`
- every row remains `pending_editorial_review` or `mapped_reconciliation_review`; packets never claim approval or import readiness
- service 1 and 2 rows require a separate councils target and cannot enter the faculty member importer
- odd/even service labels preserve faculty leadership/staff semantics
- missing media is not a blocker; file paths are deferred evidence only
- exports and edited approvals must remain private and must not be placed under `public/`

An editor must review blockers and evidence in one service CSV. To approve a faculty profile candidate, set exactly:

```text
approval_decision=import
approved_target=faculty_members
```

Do not edit source identity/content fields as a way to alter imported data. The importer uses packet IDs only to fetch approved `jx_councils` rows and verifies packet service/faculty context against the fixed mapping. Dry-run the privately edited packet first:

```bash
php artisan legacy-import:public-staff legacy-import-exports/public-staff-review-packets/<timestamp>/service_04_medicine_staff.csv
php artisan legacy-import:public-staff legacy-import-exports/public-staff-review-packets/<timestamp>/service_04_medicine_staff.csv --json
```

After reviewing dry-run counts and skip reasons, write disabled drafts with the explicit token:

```bash
php artisan legacy-import:public-staff legacy-import-exports/public-staff-review-packets/<timestamp>/service_04_medicine_staff.csv --write --approve=public-staff-import --batch=<reviewed-batch>
```

Write guarantees:

- creates only disabled `draft` `faculty_members` with `published_at`, photo media, and CV media left null
- creates only usable source locales; it does not synthesize AR or EN translations
- invalid email and URL-in-email values become null and are retained only in migration-log evidence
- media paths, source visibility/rank/service, packet path, and packet SHA-256 remain migration-log metadata
- creates no redirects and never publishes content
- successful `jx_councils` migration logs make replay idempotent
- duplicate approved identities, existing current emails, missing faculties, and prior mappings are skipped rather than merged

## Central Council Approval Import

Central governance uses the service 1 and 2 packets from the same private packet generator:

```bash
php artisan legacy-import:public-staff-review-packets --service=1 --service=2 --disk=local --dir=legacy-import-exports/public-staff-review-packets
```

In each reviewed copy, approve only intended central members by setting exactly:

```text
approval_decision=import
approved_target=council_members
```

Keep `candidate_target_module=councils` and `candidate_faculty_slug` blank. Dry-run each reviewed copy before writing:

```bash
php artisan legacy-import:central-councils legacy-import-exports/public-staff-review-packets/<timestamp>/service_01_university_board.csv
php artisan legacy-import:central-councils legacy-import-exports/public-staff-review-packets/<timestamp>/service_02_university_council.csv --json
```

Write only after checking importable and reason counts:

```bash
php artisan legacy-import:central-councils legacy-import-exports/public-staff-review-packets/<timestamp>/service_01_university_board.csv --write --approve=central-councils-import --batch=<reviewed-batch>
```

This workflow imports only verified `jx_councils` service 1 and 2 rows. It never reads `jx_councils1`, auto-links faculty identities, or trusts packet names/content. Councils and members are created disabled as review/archive data, no content is published or publicly listed, and no routes or redirects are created. Packet files must remain private.

Latest generated URL baseline:

- Report: `storage/app/private/legacy-import-exports/generated-url-inventory/20260705_233452_generated_url_inventory.md`.
- CSV: `storage/app/private/legacy-import-exports/generated-url-inventory/20260705_233452_generated_url_inventory.csv`.
- JSON: `storage/app/private/legacy-import-exports/generated-url-inventory/20260705_233452_generated_url_inventory.json`.
- Source rows scanned: `29176`.
- Generated URL rows: `12749`.
- Resolved by existing query resolver: `6070`.
- Unresolved/backlog generated URLs: `6679`.
- Source counts: `generated_router_url=12580`, `generated_explicit_url=169`.

Latest generated URL triage:

- Report: `storage/app/private/legacy-import-exports/url-continuity-triage/20260705_233523_url_continuity_triage.md`.
- Groups: `storage/app/private/legacy-import-exports/url-continuity-triage/20260705_233523_url_continuity_triage_groups.csv`.
- Rows: `storage/app/private/legacy-import-exports/url-continuity-triage/20260705_233523_url_continuity_triage_rows.csv`.
- JSON: `storage/app/private/legacy-import-exports/url-continuity-triage/20260705_233523_url_continuity_triage.json`.
- Unresolved generated rows triaged: `6679`.
- Resolver candidates: `3834`.
- Blocked/backlog rows: `2845`.
- Triage counts: `resolver_candidate=3834`, `needs_phase4_mapping=2339`, `blocked_missing_target_module=505`, `unknown_legacy_url=1`.
- Existing resolver already handles `6070` generated `root:items:show` rows.

## Phase 5 Redirect Evidence

Build the evidence-backed redirect preview and backlog split after URL inventory and triage:

```bash
php artisan legacy-import:redirect-evidence legacy-import-exports/generated-url-inventory/20260705_233452_generated_url_inventory.csv legacy-import-exports/url-continuity-triage/20260705_233523_url_continuity_triage_rows.csv
php artisan legacy-import:redirect-evidence legacy-import-exports/url-continuity/20260705_211357_url_continuity_inventory.csv legacy-import-exports/url-continuity-triage/20260705_213658_url_continuity_triage_rows.csv --dir=legacy-import-exports/redirect-evidence/discovered
```

Rules:

- evidence export is read-only and does not persist final redirects
- preview rows require a proven target URL from existing runtime query resolver output
- resolver candidates without imported/assigned targets remain blocked as `needs_imported_target`
- proposed mappings remain blocked as `blocked_unapproved_mapping` until approved
- file URLs remain blocked until legacy file bytes are available
- unknown URLs stay backlog and must not redirect to homepage

Latest generated redirect evidence:

- Report: `storage/app/private/legacy-import-exports/redirect-evidence/20260706_142351_redirect_evidence.md`.
- All evidence rows: `storage/app/private/legacy-import-exports/redirect-evidence/20260706_142351_redirect_evidence_all.csv`.
- Redirect preview rows: `storage/app/private/legacy-import-exports/redirect-evidence/20260706_142351_redirect_evidence_preview.csv`.
- Blocked/backlog rows: `storage/app/private/legacy-import-exports/redirect-evidence/20260706_142351_redirect_evidence_blocked.csv`.
- JSON summary: `storage/app/private/legacy-import-exports/redirect-evidence/20260706_142351_redirect_evidence.json`.
- Scanned evidence rows: `12749`.
- Redirect preview rows: `6070`.
- Blocked/backlog rows: `6679`.
- Evidence statuses: `resolver_ready=6070`, `needs_imported_target=2486`, `blocked_missing_target_module=460`, `blocked_phase3_findings=574`, `blocked_file_dependency=819`, `needs_phase4_mapping=2339`, `unknown_legacy_url=1`.

Latest discovered-link redirect evidence:

- Report: `storage/app/private/legacy-import-exports/redirect-evidence/discovered/20260706_142417_redirect_evidence.md`.
- Preview rows: `storage/app/private/legacy-import-exports/redirect-evidence/discovered/20260706_142417_redirect_evidence_preview.csv`.
- Scanned evidence rows: `796`.
- Redirect preview rows: `7`.
- Blocked/backlog rows: `789`.

## Phase 6 Current-Scope Candidates

Export Phase 6 candidates from staged review rows before approving or importing current-scope content:

```bash
php artisan legacy-import:phase6-candidates
php artisan legacy-import:phase6-candidates menu_links
php artisan legacy-import:phase6-candidates pages
php artisan legacy-import:phase6-candidates settings
```

Rules:

- candidate export is read-only and does not import content
- only current-scope source tables are included
- no row is import-ready until its mapping is approved
- file-dependent rows remain blocked until legacy file bytes are available
- `jx_categories` rows require explicit core-page selection before Phase 6 import
- blocked rows remain visible for reconciliation and are not discarded

Latest Phase 6 candidate baseline:

- Full report: `storage/app/private/legacy-import-exports/phase6-candidates/20260706_143718_phase6_candidates.md`.
- Full approval candidates: `storage/app/private/legacy-import-exports/phase6-candidates/20260706_143718_phase6_candidates_approval_candidates.csv`.
- Full blocked rows: `storage/app/private/legacy-import-exports/phase6-candidates/20260706_143718_phase6_candidates_blocked.csv`.
- Current-scope rows scanned: `5559`.
- Approval candidates: `116`.
- Import-ready rows: `0`.
- Blocked rows: `5443`.
- Lane counts: `selected_core_pages=4944`, `settings=475`, `documents_and_links=45`, `homepage=54`, `pages=21`, `menu_links=20`.
- First clean lane: `menu_links` with `20` approval candidates and `0` blocked rows.
- Pages lane: `21` approval candidates and `0` blocked rows.
- Settings lane: `72` approval candidates and `403` blocked rows.

## Phase 6 Menu Link Approval And Import

Approve and import the clean `menu_links` lane only after reviewing the candidate packet:

```bash
php artisan legacy-import:phase6-approve menu_links
php artisan legacy-import:phase6-approve menu_links --write --approve=phase6-menu-links
php artisan legacy-import:phase6-menu-links --batch=phase6-menu-links-approval
php artisan legacy-import:phase6-menu-links --write --approve=phase6-menu-links --batch=phase6-menu-links-approval
```

Rules:

- approval write requires `--approve=phase6-menu-links`
- import write requires `--approve=phase6-menu-links`
- imported legacy links are created as disabled localized footer menu items
- each approved source row creates AR and EN menu items when imported
- import records `migration_logs` rows with source table/source ID and target menu item IDs
- reruns skip already imported source rows
- no imported legacy menu link is public until an editor explicitly enables/reorders it

Latest Phase 6 menu-link import baseline:

- Approval dry-run: `20` approvable rows, `0` blocked.
- Approval write: `20` approved rows.
- Refreshed candidate packet: `storage/app/private/legacy-import-exports/phase6-candidates/20260706_145114_phase6_candidates_menu_links.md`.
- Import dry-run: `20` importable rows, `0` skipped.
- Import write batch: `phase6-menu-links-approval`.
- Imported rows: `20`.
- Created disabled menu items: `40`.
- Rerun duplicate check: `20` skipped as `already_imported`.

## Phase 6 Static Page Approval And Import

Approve and import the clean `pages` lane after reviewing the candidate packet:

```bash
php artisan legacy-import:phase6-approve pages
php artisan legacy-import:phase6-approve pages --write --approve=phase6-pages
php artisan legacy-import:phase6-pages --batch=phase6-pages-approval
php artisan legacy-import:phase6-pages --write --approve=phase6-pages --batch=phase6-pages-approval
```

Rules:

- approval write requires `--approve=phase6-pages`
- import write requires `--approve=phase6-pages`
- imported static pages are created as disabled draft pages
- each imported source row creates AR and EN translations
- import records `migration_logs` rows with source table/source ID and target page IDs
- reruns skip already imported source rows
- no imported legacy page is public until an editor reviews, enables, and publishes it

Latest Phase 6 page import baseline:

- Approval dry-run: `21` approvable rows, `0` blocked.
- Approval write: `21` approved rows.
- Refreshed candidate packet: `storage/app/private/legacy-import-exports/phase6-candidates/20260706_150728_phase6_candidates_pages.md`.
- Import dry-run: `21` importable rows, `0` skipped.
- Import write batch: `phase6-pages-approval`.
- Imported rows: `21`.
- Created disabled draft pages: `21`.
- Created translations: `42`.
- Rerun duplicate check: `21` skipped as `already_imported`.

## Phase 6 Settings Mapping And Import

Export a conservative settings mapping report before importing anything from `jx_config` or `jx_config1`:

```bash
php artisan legacy-import:phase6-settings-mapping
php artisan legacy-import:phase6-settings --input=legacy-import-exports/phase6-settings/20260706_152803_phase6_settings_mapping_safe_mappings.csv --batch=phase6-settings-dry-run
php artisan legacy-import:phase6-settings --input=legacy-import-exports/phase6-settings/20260706_152803_phase6_settings_mapping_safe_mappings.csv --write --approve=phase6-settings --batch=phase6-settings-approval
```

Rules:

- mapping export is read-only and does not write settings
- only deliberate current settings targets are considered safe mappings
- duplicate conflicting legacy keys stay out of import until manually selected
- unsafe values stay out of import
- the import command consumes a reviewed safe-mappings CSV and defaults to dry-run
- write mode requires `--approve=phase6-settings`
- settings writes are live/public target settings, so do not run write mode until the safe mappings are explicitly accepted

Latest Phase 6 settings mapping baseline:

- Mapping report: `storage/app/private/legacy-import-exports/phase6-settings/20260706_152803_phase6_settings_mapping.md`.
- Safe mappings: `storage/app/private/legacy-import-exports/phase6-settings/20260706_152803_phase6_settings_mapping_safe_mappings.csv`.
- Duplicate conflicts: `storage/app/private/legacy-import-exports/phase6-settings/20260706_152803_phase6_settings_mapping_duplicates.csv`.
- Unsafe values: `storage/app/private/legacy-import-exports/phase6-settings/20260706_152803_phase6_settings_mapping_unsafe.csv`.
- Scanned rows: `475`.
- Safe mapping rows: `16`.
- Duplicate conflict rows: `6`.
- Unsafe value rows: `2`.
- Backlog/blocked rows: `451`.
- Import write batch: `phase6-settings-approval`.
- Imported unique settings units: `8`.
- Source rows logged: `16`.
- Rerun duplicate check: `16` skipped as `already_imported`.
- Refreshed settings candidate packet: `storage/app/private/legacy-import-exports/phase6-candidates/20260706_225104_phase6_candidates_settings.md`.

## Phase 6 Reference Location Import

Import legacy countries and cities as disabled reference records after a clean dry-run:

```bash
php artisan legacy-import:locations --batch=phase6-locations-dry-run
php artisan legacy-import:locations --write --approve=phase6-locations --batch=phase6-locations-20260708
```

Rules:

- dry-run is the default and writes nothing
- write mode requires `--approve=phase6-locations`
- imported countries/cities are disabled unless `--enable` is explicitly supplied
- AR/EN names are imported from legacy `ar_name` and `en_name`, with same-row fallback if one locale is missing
- legacy IDs and unsupported French country names are preserved in migration log metadata
- reruns skip already imported source rows by `migration_logs`

Latest Phase 6 location import baseline:

- Dry-run: `107` countries and `15` cities importable, `0` skipped.
- Import write batch: `phase6-locations-20260708`.
- Imported disabled countries: `107`.
- Imported country translations: `214`.
- Imported disabled cities: `15`.
- Imported city translations: `30`.
- Migration success logs: `122`.
- Rerun duplicate check: `122` skipped as `already_imported`.

## Safety Rules

- do not add manual legacy import seeders to `DatabaseSeeder`
- run only one module at a time
- review `migration_logs` and `migration_rejections` after each run
- do not treat skipped rows as failures until mapping is reviewed
- do not introduce generic product tables for legacy parity
- do not enable real execution until a controlled module runner is registered, approved, tested, and reviewed
- do not approve a module runner while `legacy-import:cleaning-report <module>` reports blocked fields
- do not approve a module runner while `legacy-import:integrity-report <module>` reports blocked rows

## Controlled Runner Status

Real execution through `legacy-import:run` remains blocked by default.

Current controlled runner registry:

- `links`: first low-risk candidate for legacy links and document metadata; registered but not approved for real execution

Modules without a controlled runner, such as `homepage`, are blocked with an explicit "no controlled runner" message even when dry-run source validation passes.

Legacy news and announcements already exist in the local migrated database. Keep them in review/quarantine until `legacy-import:news-review` reports no cleanup blockers and old URL redirect mappings are approved.

Use `legacy-import:news-slug-plan` to dry-run long canonical slug cleanup. It proposes old slug to new slug mappings and AR/EN redirect pairs only; it does not update articles or create redirects.

Use `legacy-import:news-slug-apply` only after reviewing/exporting the plan. It requires `--approve=news-slug-cleanup`, updates news slugs, and creates or updates exact AR/EN redirects in one database transaction.

## Reconciliation Checklist

After each manual import:

1. inspect `migration_logs`, `migration_rejections`, and `legacy_record_snapshots`
2. inspect imported target rows directly in MySQL
3. compare rejection reasons against the source data
4. record any new mapping decisions before expanding import coverage
