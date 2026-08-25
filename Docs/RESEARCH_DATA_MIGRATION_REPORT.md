# SPU Research Data Migration Report

## 1. Purpose

This report documents the migration of research-publication data from the legacy Syrian Private University website database into the new Laravel database. It explains:

- what source data was available;
- how that data was inspected and classified;
- how publications, translations, owners, metadata, and files were mapped;
- which records were imported;
- which records were intentionally excluded and why;
- which imported records remain incomplete or require editorial review;
- how the old and new database structures differ;
- what the local database currently contains;
- what could not be verified against the current live legacy website;
- which technical failures and inconsistencies were encountered.

The report is evidence-based. Counts described as "documented" come from the migration records and review artifacts already present in this workspace. Counts described as "locally verified" were checked against the configured local legacy and current databases on 2026-08-24. No claim is made that the local legacy copy includes research added to the old production website after that copy was obtained.

## 2. Executive Summary

The legacy research area was stored in a generic `/members/` content system rather than a purpose-built publication schema. The migration reviewed all `349` rows in `jx_member_categories`:

| Disposition | Count | Explanation |
|---|---:|---|
| Imported publication candidates | `289` | Visible, titled, service-1 rows |
| Hidden service-1 rows excluded | `4` | Not public on the old website |
| Titleless service-1 rows excluded | `9` | No usable Arabic or English title |
| Service-2 rows excluded | `47` | Teaching/course material, not research publications |
| **Total legacy category rows** | **`349`** | `289 + 4 + 9 + 47` |

The guarded import batch was `approved-structured-research-import-20260731`. It created:

| New-database result | Count |
|---|---:|
| `research_publications` imported from legacy categories | `289` |
| Source-present AR/EN translations | `549` |
| Deferred legacy file paths | `241` |
| Normalized `research_files` created from those paths | `0` |
| Successful research migration mappings | `289` |

The importer did not fabricate missing information. As a result, metadata coverage is incomplete:

| Structured field | Present | Missing or unresolved |
|---|---:|---:|
| Authors | `156` | `133` |
| Citation | `69` | `220` |
| Clean publisher/journal | `59` | `230` |
| Validated DOI | `11` | `278` |
| Citation-backed publication year | `63` | `226` |
| Keywords | `225` | `64` |
| Explicit Q1-Q4 journal rank | `0` | `289` |
| Safely linked current owner | `5` | `284` |

There are `36` imported records whose normalized AR or EN titles duplicate another imported title. The current fail-closed policy says these records should remain private until explicitly reviewed. However, the current local database still has all `289` legacy publications enabled with `extraction_status=published`. This is a material discrepancy between the intended policy and the local database state.

The local legacy database is replay-complete for this historical snapshot: a dry run scanned all `349` category rows and classified all `349` as `already_processed`. This proves that the known local snapshot was processed; it does **not** prove that research subsequently added to the live old website has been acquired or migrated.

## 3. Evidence and Scope

### 3.1 Primary tracked evidence

The main tracked sources used for this report are:

- `Docs/LEGACY_RESEARCH_PUBLICATION_MAPPING.md`
- `Docs/LEGACY_IMPORT_RUNBOOK.md`
- `Docs/FRONTEND_IMPORT_TRACKER.md`
- `Docs/V2_PRE_CUTOVER_ACTIONS.md`
- `app/Console/Commands/LegacyImportResearchPublicationsCommand.php`
- `app/Console/Commands/LegacyPublishResearchPublicationsCommand.php`
- `app/Services/Legacy/LegacyResearchPublicationImportService.php`
- `app/Services/Legacy/LegacyResearchMetadataExtractor.php`
- `app/Services/Legacy/LegacyResearchPublicationPublishingService.php`
- `database/migrations/2026_04_18_000003_create_research_domain_tables.php`
- `database/migrations/2026_07_31_000001_add_legacy_metadata_to_research_publications.php`
- the research import, publication, redirect, and public-page tests under `tests/`

### 3.2 Local private evidence

The workspace also contains private/ignored evidence that was inspected but is not tracked by Git:

- legacy SQL snapshot: `Docs/STUDENT_GUIDE/spuedu_db.sql`;
- members review packet: `storage/app/private/legacy-import-exports/members-review-packets/20260728_180031/`;
- local Laravel runtime log: `storage/logs/laravel.log`.

These artifacts must not be treated as portable repository documentation. The SQL snapshot and review export should remain private because they can contain source-system content and operational details.

### 3.3 Fresh local verification performed for this report

The following read-only commands were run on 2026-08-24:

```bash
php artisan legacy-import:verify research
php artisan legacy-import:research-publications --json
```

The verification command reported:

```text
research -> research_publications -> 289 distinct successful source rows
```

The dry run reported:

```text
scanned_rows: 349
imported_rows: 0
skipped_rows: 349
skip_reason_counts.already_processed: 349
```

A read-only local target-database count reported:

| Local current-database measure | Count |
|---|---:|
| Total research publications | `289` |
| Publications with a legacy source ID | `289` |
| Enabled legacy publications | `289` |
| Legacy publications with status `published` | `289` |
| Legacy publication translations | `549` |
| Legacy file references | `241` |
| Normalized research files | `0` |

These are local database results, not a production-database audit.

### 3.4 Critical scope limitation

The available legacy SQL snapshot was generated on 2026-03-29. The project team has stated that additional research was added to the old website after the local database copy was obtained. Therefore:

- this report completely reconciles the **available local snapshot**;
- this report cannot enumerate later records that exist only in the live old database;
- the successful `https://v2.spu.edu.sy/up` check proves only that the Laravel web server responds;
- `/up` does not query MariaDB and does not expose research data;
- no authenticated research-list/export endpoint has yet been identified;
- the Bearer token cannot be used to discover records without the correct API endpoint and response contract;
- a fresh read-only production export or documented research API is still required for a final delta import.

## 4. Legacy Research Data Model

### 4.1 Legacy parent table: `jx_member_categories`

The old system stored publication-like records in `jx_member_categories`. It was a generic table shared by different content semantics.

Important columns included:

| Legacy column | Meaning used by migration |
|---|---|
| `id` | Stable source identity |
| `service_type` | Distinguishes research-like service 1 from teaching/course service 2 |
| `parent` | Staff-owner reference, not a publication category and not necessarily the author |
| `ar_name`, `en_name` | Arabic and English titles |
| `ar_brief`, `en_brief` | Short localized summaries |
| `ar_data`, `en_data` | Large HTML bodies containing mixed publication metadata |
| `ar_keywords`, `en_keywords` | Localized keyword text |
| `photo` | Unverified legacy image path or filename |
| `url` | Optional external link |
| `member_category_order` | Legacy display order |
| `is_visible` | Whether the category was public in the legacy system |
| `start_date`, `end_date` | Generic dates, frequently invalid or zero-valued |

The old table also contained French, Spanish, and German fields. The approved migration scope stores managed content in Arabic and English; missing AR/EN translations are not synthesized from the other language columns.

### 4.2 Legacy child table: `jx_member_items`

Attachments and child content were stored in `jx_member_items`, logically joined using `member_category_id`. The SQL schema did not enforce that relationship with a foreign key.

Important columns included:

| Legacy column | Meaning used by migration |
|---|---|
| `id` | Child source identity |
| `member_category_id` | Logical parent publication ID |
| `service_type` | Child service classification |
| `ar_name`, `en_name` | Localized attachment labels |
| `ar_file`, `en_file` | Unverified localized file paths |
| `photo`, `large_photo` | Unverified image paths |
| `video_link` | Optional video reference |
| `is_visible` | Child visibility |
| `is_accepted` | Legacy acceptance flag |
| `is_archive`, `is_main` | Legacy presentation/workflow flags |
| `post_date`, `added_date`, `updated_date` | Generic operational dates, not reliable publication dates |

The complete review packet contained `429` child items across services 1 and 2. It identified `3` orphan items whose `member_category_id` had no matching parent category. The publication importer only considered visible, accepted, service-1 attachment rows belonging to an imported source publication.

### 4.3 Why direct copying was unsafe

Direct table-to-table copying was rejected because:

- service 1 and service 2 represented different content types;
- bibliographic fields were embedded in free-form HTML;
- generic dates could not be trusted as publication dates;
- `parent` IDs could refer to different people in two separate legacy staff tables;
- legacy file paths did not prove that bytes still existed;
- MIME type, checksum, file size, and safe public URL were unavailable;
- titles and records could be duplicated;
- hidden records could not be made public automatically;
- the legacy schema had no safe draft/review/publish workflow.

## 5. New Research Data Model

### 5.1 `research_publications`

The new relational publication table separates publication-level data from translations and files. It stores:

- optional links to `faculty_members` and the unified `persons` table;
- category key;
- exact publication date when known;
- citation-backed publication year when known;
- DOI and journal rank when explicitly validated;
- external URL;
- normalized primary media link;
- ordering and enabled status;
- soft deletion;
- legacy source table and source ID;
- legacy owner ID and owner-resolution state;
- legacy image path;
- extraction/publication workflow status.

The compound uniqueness constraint on `legacy_source_table` and `legacy_source_id` prevents the same old record from being imported twice.

### 5.2 `research_publication_translations`

Localized content is normalized into one row per publication and locale. The table stores:

- `locale`;
- title;
- authors/byline text;
- excerpt;
- abstract/body;
- publisher/journal text;
- citation;
- keyword JSON array.

The publication/locale pair is unique. Only source-present locales are inserted.

### 5.3 Normalized and deferred files

Two different file representations exist:

| Table | Purpose |
|---|---|
| `research_files` | Verified media linked to normalized `media_assets` records |
| `legacy_research_file_references` | Unverified source paths preserved for later reconciliation |

Normalized `media_assets` can store disk, path, filename, MIME type, extension, byte size, dimensions, checksum, accessibility metadata, and uploader information. The legacy source did not provide enough evidence to create those records safely, so the migration created `241` deferred references and `0` normalized research files.

### 5.4 Provenance tables

`migration_logs` records source and target identities, batch name, status, message, and structured metadata. For successful research imports, the metadata includes source hash, imported locales, extracted-field evidence, owner status, duplicate status, and attachment paths.

`migration_rejections` is available for quarantined failures in the general migration framework. The current structured research importer writes explicit success and skip logs, but it does not convert every unexpected runtime exception into a research-specific rejection row.

## 6. Import Process

### 6.1 Evidence generation

The `/members/` review packet was generated as a read-only inspection artifact. It exported scalar metadata and content lengths rather than copying complete legacy HTML into a review spreadsheet.

The packet classified:

- service-1 categories as research/publication-like records requiring publication proof;
- service-1 items as research attachment evidence;
- service-2 categories as teaching/course material;
- service-2 items as teaching archive material;
- owner IDs independently against `jx_councils` and `jx_councils1`;
- hidden, titleless, orphan, invalid-date, missing-file, and ownership blockers.

### 6.2 Dry-run-first import

The authoritative command is:

```bash
php artisan legacy-import:research-publications --batch=<review-batch> --json
```

It is dry-run-only unless `--write` and the exact approval token are supplied:

```bash
php artisan legacy-import:research-publications \
  --write \
  --approve=legacy-research-publications-import \
  --batch=<approved-batch> \
  --json
```

The command deliberately rejects enabling records during import. Every imported record initially enters review with `is_enabled=false` and an extraction status of either `metadata_review` or `duplicate_review`.

### 6.3 Selection rules

For every row in `jx_member_categories`, the importer applies these checks in order:

| Check | Result when check fails |
|---|---|
| Numeric source ID exists | Count as `missing_source_id` |
| Source has not already been processed | Count as `already_processed` |
| `service_type === 1` | Log `deferred_non_publication_row` |
| Legacy row is visible | Log `not_published_on_old_site` |
| Sanitization permits public import | Log `cleaning_blocked` with blocked fields |
| At least one AR/EN title remains | Log `missing_title` |

The applied historical batch had no documented source-ID or sanitization exclusions. Its exclusions were exactly `47` non-publication service-2 rows, `4` hidden rows, and `9` titleless rows.

### 6.4 Cleaning and sanitization

Legacy Arabic and English HTML bodies are sanitized before storage. The URL is cleaned and retained only when it passes URL validation. Empty values are converted to null. Invalid or zero dates become null rather than being replaced with the import date.

This preserves source authenticity and prevents old HTML or malformed values from being copied blindly into the public application.

### 6.5 Structured metadata extraction

The metadata extractor recognizes isolated Arabic and English labels for authors, citation/journal, abstract/summary, and keywords. Its main rules are:

- prefer explicit labeled sections over inference;
- keep complete source bylines as text;
- cap field lengths;
- prefer explicit keyword columns and otherwise use a labeled keyword section;
- deduplicate keyword values;
- accept DOI only when it matches validated DOI syntax;
- derive year only from an isolated citation section;
- accept journal rank only when Q1, Q2, Q3, or Q4 is explicitly present;
- derive publisher only from a clean citation prefix;
- reject copyright, license, received, revised, and accepted prose as publisher data.

The extractor intentionally does not infer a publication year from upload timestamps, filenames, abstract prose, generic category dates, or migration time.

### 6.6 Translation creation

For each source-present Arabic or English title, the importer creates one translation. It maps:

- source title to `title`;
- extracted byline to `authors`;
- legacy brief to `excerpt`;
- extracted abstract to `abstract`, falling back to sanitized source body;
- extracted publisher/journal to `publisher`;
- extracted citation to `citation`;
- source or extracted keywords to the locale-specific keyword array.

The result was `549` translations for `289` publications, rather than an artificial `578`, because missing source locales were not fabricated.

### 6.7 Duplicate detection

Duplicate detection is title-based and locale-specific. It:

- strips HTML;
- collapses whitespace;
- trims the title;
- lowercases it;
- compares Arabic titles separately from English titles.

Every record participating in a duplicate title group receives `duplicate_review`. Records are not automatically merged, and no DOI-, author-, year-, or fuzzy-title merge is attempted. This preserves each source ID while requiring editorial review.

### 6.8 Owner reconciliation

The legacy `parent` field is treated as an owner reference, not as a category and not automatically as the publication author.

Each owner ID is checked independently in `jx_councils` and `jx_councils1`, producing one of these provenance states:

- `both_sources`;
- `jx_councils_only`;
- `jx_councils1_only`;
- `missing`.

Automatic linkage to a current `faculty_members` row is allowed only when:

- the ID exists solely in `jx_councils`;
- a successful `jx_councils -> faculty_members` migration mapping exists;
- the target faculty member still exists.

Only `5` publications met that standard. The remaining `284` preserve owner provenance but do not make an unsupported identity claim. Numeric-ID-only matching and fuzzy-name matching are forbidden.

### 6.9 Attachment preservation

For each imported publication, the importer reads matching `jx_member_items` rows that are:

- service 1;
- visible;
- accepted;
- linked to the source publication.

Unique `en_file`, `ar_file`, and `photo` values become deferred legacy references. File bytes are not copied by this command, and no normalized media asset is created until file existence, MIME type, checksum, and safe path can be verified.

### 6.10 Provenance and replay safety

Each successful import records:

- source and target IDs;
- batch name;
- source SHA-256;
- owner ID and owner-resolution state;
- linked current owner when safe;
- service type;
- legacy image and URL;
- imported locales;
- extracted metadata and extraction evidence;
- duplicate-review state;
- attachment references.

A row is considered already processed when either a matching target source ID exists or a persisted skip log exists. Combined with the unique database constraint, this makes replay idempotent. The current dry run confirms that all `349` known source rows are replay-terminal and no duplicate target would be created.

## 7. Exact Records Not Imported as Research Publications

The phrase "failed to import" requires care. The final guarded batch did not report unexplained record-level insertion failures. It made explicit, policy-driven exclusion decisions for `60` rows.

### 7.1 Hidden service-1 publications: 4

These rows had `is_visible=0` and were therefore not public on the old website:

| Legacy source ID | Available title/evidence | Reason |
|---:|---|---|
| `149` | Study about The Topical Anesthetics and Sedation Use by Dentists | Hidden source |
| `150` | The Use of Local Anesthesia Techniques Among The Dentists | Hidden source |
| `151` | Arabic study concerning local anesthetics and vasoconstrictors | Hidden source |
| `163` | Dietary pattern and metabolic syndrome study | Hidden source |

Related hidden child item IDs `144`, `145`, and `146` belonged to categories `149`, `150`, and `151`.

These were intentionally excluded to prevent private legacy content from becoming public without approval.

### 7.2 Service-1 rows without an AR or EN title: 9

The exact source IDs were:

```text
247, 248, 249, 250, 251, 252, 261, 338, 339
```

Each lacked both `ar_name` and `en_name`. Some also had ambiguous, missing, or unmapped owners, but the decisive import blocker was the absence of any usable AR/EN title. Creating a title from unrelated text or metadata would have fabricated public content.

### 7.3 Service-2 teaching/course rows: 47

The exact source IDs were:

```text
153,
198, 199, 200, 201, 202, 203, 204, 205, 206, 207, 208, 209, 210,
212, 213,
215, 216, 217, 218, 219,
221, 222, 223, 224, 225, 226,
313,
324, 325, 326, 327, 328, 329, 330, 331, 332,
334, 335, 336,
352, 353, 354, 355, 356,
358, 359
```

These were not publication import failures. They were classified as teaching or course archive material and deliberately excluded from `research_publications`. Importing them as research would have changed their meaning.

### 7.4 Accounting proof

The service-1 source population reconciles exactly:

```text
302 service-1 rows
- 4 hidden rows
- 9 titleless rows
= 289 imported candidates
```

The total source population also reconciles:

```text
289 imported candidates
+ 4 hidden service-1 rows
+ 9 titleless service-1 rows
+ 47 service-2 rows
= 349 category rows
```

## 8. Imported Records Requiring Further Review

### 8.1 Duplicate-title review: 36 records

These records were imported, but they participate in duplicate normalized-title groups:

```text
29, 34, 35, 45,
104, 105, 106, 107, 112, 113, 120, 121,
136, 137, 138, 139, 141, 142, 144, 145,
268, 270,
298, 299, 300, 301, 307,
340, 341, 342, 343, 344, 345, 347, 348, 349
```

Examples include:

- IDs `29` and `45`, sharing the same antibiotic-sensitivity title;
- IDs `34` and `35`, sharing the same keratocystic odontogenic tumor title;
- IDs `104/105`, `106/107`, and `112/113`, repeated petroleum-engineering titles;
- IDs `120` and `121`, sharing the same seismic-properties title;
- IDs `298/300` and `299/301`, repeated geology titles by locale;
- IDs `307, 340, 342, 344, 345, 349`, using `Google Scholar` or its Arabic equivalent as title;
- IDs `341, 343, 347, 348`, using `Recognition of Outstanding Contribution` as title.

The historical publication batch enabled all `289` records. The current policy is stricter: duplicate-review records should remain private unless the publisher deliberately supplies `--include-duplicate-review` with the publication approval token.

If the current policy is applied, the expected reviewed public set is:

```text
289 imported - 36 duplicate-review = 253 non-duplicate records
```

The current local database does not reflect that holdback; it reports all `289` enabled and `published`. Before production release, the 36 records need either editorial approval, consolidation, corrected titles, or retirement.

### 8.2 Missing locale coverage

The importer accepted a publication when at least one AR/EN title existed. It did not invent the missing locale.

Known title coverage gaps include:

- source ID `380`: missing Arabic title;
- 28 records missing English title:

```text
174, 176, 177, 181, 186, 233, 240, 241, 309, 357,
360, 361, 362, 363, 364, 365, 366,
369, 370, 371, 372, 373, 374, 375, 376, 377, 378, 379
```

These records were still imported because they had a valid source title in the other supported locale. Any added translation must be editorially authored and approved, not generated as if it were source content.

### 8.3 Empty body content

The review packet identified `83` visible, titled service-1 candidates with zero-length Arabic and English body content. They were not automatically excluded because a title, brief, citation evidence, external URL, or attachment could still make the record a legitimate publication entry.

The known IDs were:

```text
31, 32, 49, 57,
75, 76, 77, 78,
101, 102, 110,
114, 115, 116, 117, 118, 119, 120, 121,
126, 127, 128, 129, 130,
147, 156, 160, 162, 165,
174, 175, 176, 177, 181, 186,
227, 233, 240, 241,
271, 272, 273, 274, 275, 276, 277, 278, 279, 280, 281, 282,
284, 285, 286, 288, 289, 290, 295, 296, 303, 304, 337, 346,
360, 361, 362, 363, 364, 365, 366, 367, 368, 369,
370, 371, 372, 373, 374, 375, 376, 377, 378, 379
```

These need editorial assessment for usable detail-page content. An empty body was not replaced with invented research text.

### 8.4 Incomplete bibliographic metadata

Missing structured metadata is not an import error. It reflects limitations in the legacy source and conservative extraction rules.

The most significant gaps are:

- `133` records without extracted authors;
- `220` without an isolated citation;
- `230` without a clean publisher/journal value;
- `278` without a validated DOI;
- `226` without a citation-backed year;
- `64` without explicit or safely extracted keywords;
- all `289` without an explicit Q1-Q4 journal rank;
- `284` without a safely linked current owner.

These values remain null or unresolved rather than being guessed.

## 9. Files and Attachments Not Fully Imported

### 9.1 Deferred path results

The source inventory found:

- `250` service-1 child items;
- `240` qualifying attachment groups;
- `241` unique path references preserved in the new database.

The path count exceeds the group count by one because at least one group contains more than one unique file/photo path.

### 9.2 Why normalized files were not created

The local current database contains `0` normalized `research_files`. The `241` paths remain in `legacy_research_file_references` because the migration did not have verified evidence for:

- physical file existence;
- exact public path;
- MIME type;
- file extension consistency;
- byte size;
- checksum;
- safe access behavior;
- duplicate file identity.

Representing an unresolved path as a verified public download would risk broken links or unsafe files.

### 9.3 Child rows with no usable file or description

Five known service-1 item rows had no file and no description:

| Child item ID | Parent category ID |
|---:|---:|
| `26` | `32` |
| `151` | `156` |
| `398` | `306` |
| `434` | `258` |
| `435` | `257` |

These rows supplied no usable attachment payload. Their parent publication could still be imported independently.

### 9.4 Documentation/runtime inconsistency

The mapping and runbook say deferred references should not render until file verification. Current runtime code, however, attempts to render a legacy reference whenever `MediaUrlResolver::resolveLegacy()` produces a safe URL; it does not explicitly filter on `status=deferred`. A feature test also expects a deferred path to render.

Therefore, "deferred" currently means "not normalized or byte-verified," but it may not reliably mean "never rendered." This discrepancy must be resolved before claiming that all unverified files are private.

## 10. Old Database Versus New Database

| Area | Legacy database | New database |
|---|---|---|
| Core structure | Generic category/item tables | Dedicated publication, translation, file, media, and provenance tables |
| Content types | Research and teaching mixed by `service_type` | Research publications separated; teaching rows excluded |
| Localization | AR/EN/FR/SP/GE columns on one row | One translation row per supported locale |
| Title requirement | Could be empty | At least one AR/EN title required for import |
| Authors | Embedded in free-form HTML | Structured localized byline text |
| Abstract | Embedded in body HTML | Dedicated localized abstract field |
| Citation | Embedded in body HTML | Dedicated localized citation field |
| Keywords | Text columns or embedded labels | Locale-specific JSON arrays |
| DOI | Not structurally modeled | Validated dedicated DOI field |
| Publication year | Generic dates and body text | Stored only from citation-backed evidence |
| Journal rank | Not structurally modeled | Dedicated field, null unless explicit Q1-Q4 |
| Owner | Ambiguous numeric `parent` | Preserved provenance plus optional safe current relation |
| Visibility | `is_visible` | `is_enabled` plus extraction/publication workflow status |
| Publication workflow | No safe review gate | Dry-run, approval token, actor authorization, audit and cache invalidation |
| Files | Bare paths/filenames | Deferred references or verified normalized media assets |
| Referential integrity | Logical child relation without FK | Foreign keys and unique constraints |
| Replay protection | None | Unique source identity plus migration logs |
| Deletion | Direct row behavior | Soft-deleted publications |
| Public URL | Query-based `/members/` route | Stable localized slug ending in immutable source ID |

## 11. Publication Workflow

Import and publication are separate operations.

The publication dry run is:

```bash
php artisan legacy-import:publish-research \
  --actor=<publisher-user-id> \
  --batch=<batch> \
  --json
```

The guarded write is:

```bash
php artisan legacy-import:publish-research \
  --actor=<publisher-user-id> \
  --write \
  --approve=publish-legacy-research \
  --batch=<batch> \
  --json
```

Publishing duplicate-review records additionally requires:

```text
--include-duplicate-review
```

The publisher must be an existing, unlocked user authorized for `publish-content`. A candidate must have successful import provenance and at least one title. Publication sets `is_enabled=true` and `extraction_status=published`, writes migration and audit logs, and invalidates relevant caches.

Legacy public eligibility differs from native publication eligibility:

- imported legacy rows require `is_enabled=true`, source table `jx_member_categories`, and `extraction_status=published`;
- native rows require `is_enabled=true` and a non-future `published_at` date;
- unknown historical dates remain null for imported legacy rows and are not replaced with migration time.

## 12. URL Continuity

An eligible legacy service-1 detail URL can resolve to the localized Laravel publication slug when a public imported target exists. The accepted legacy shape is based on:

```text
/members/index.php?page=show&ex=2&dir=items&ser=1&cat_id=<source-id>
```

The new slug includes the immutable legacy source ID, preventing title edits from breaking identity. Service-2 routes remain private/unresolved because those rows were not imported as research.

The runbook contains stale wording claiming there is no public `/members/` redirect workflow, while later runbook text, the resolver implementation, and tests describe the implemented service-1 redirect. The implementation should be treated as current behavior, but the stale sentence should not be used as evidence of route absence.

## 13. Technical Failures and Non-Record Failures

### 13.1 Final import result

No unexplained per-publication insertion failure is documented in the final guarded batch. All `349` source categories have a known disposition, and all `289` eligible candidates have successful target mappings.

### 13.2 Initial migration foreign-key failure

A local migration attempt failed because the automatically generated foreign-key identifier for `research_publication_translations` exceeded the database identifier limit. The tracked migration now uses the explicit shorter name `rpt_pub_fk`.

This was a schema-creation problem, not the loss of a specific publication record.

### 13.3 Exploratory legacy query failure

An exploratory query failed because it referenced a nonexistent `jx_config.ar_title` column. The final importer does not depend on that column, and the failure does not identify any publication omitted by the final pipeline.

### 13.4 Publication authorization failure

A publication command attempt failed because actor user ID `1` was not an unlocked user with the required publication permission. The publication service correctly rejected the operation. No specific partial publication count can be inferred from that log entry.

### 13.5 Unexpected exception observability gap

The current structured importer logs explicit skips and successful imports, but it does not wrap all unexpected database/runtime exceptions in research-specific rejection records. Publication creation is transactional, while its success provenance log is written after the target transaction. In theory, a logging failure could leave a target without the success provenance required by the publication gate.

This did not prevent reconciliation of the historical 289-record batch, but it is a technical weakness for future delta imports.

## 14. Other Research-Labeled Legacy Material Not Imported

### 14.1 Root service 7 statistics/achievements

A separate review packet contained `8` root-service-7 rows associated with research statistics or achievements. They were not mapped into research publications because the source meaning did not match a publication entity and included placeholder or invalid evidence.

| Legacy ID | Known issue |
|---:|---|
| `5343` | Hidden; English content says `Under Construction` |
| `5255` | Pending editorial review under the unmapped module |
| `3484` | Invalid start/end dates |
| `1627` | Invalid dates and different AR/EN achievement meanings |
| `1713` | Invalid dates |
| `1626` | Invalid dates |
| `1629` | Invalid dates |
| `1628` | Invalid dates |

These are not part of the `349` `/members/` category accounting. They require a separate product decision about achievements/statistics and must not be silently counted as missing publications.

### 14.2 Placeholder frontend research content

Historical staging used fixture content from `resources/data/research-content.json` as a public fallback when no research CMS payload existed. That fixture included invented content:

| Fixture area | Placeholder count |
|---|---:|
| Research centers | `3` |
| Research projects | `5` |
| Research themes | `12` |
| Researchers | `9` |
| Publications | `8` |
| Statistics | `4` |

At that time, the archive displayed `261` items: `253` real migrated non-duplicate publications plus `8` placeholder publications. The placeholder items sorted to the first page. Public fixture fallback has been removed locally; fixture files may remain for editor defaults and must not be mistaken for migrated legacy research.

Empty research centers, projects, themes, conferences, library, office, and policies are currently retired/404 unless approved CMS content exists. This avoids replacing missing real content with fabricated material.

## 15. Current Discrepancies and Risks

### 15.1 Local publication state conflicts with current duplicate policy

The local database has `289` enabled/published legacy publications. Current documentation says `36` duplicate-review records should remain private, producing `253` non-duplicate public records. The historical publication batch predates or bypassed that fail-closed decision.

Required resolution: review the 36 source IDs and deliberately approve, merge, correct, or retire each one before production state is certified.

### 15.2 Current live legacy delta is unknown

The local source snapshot is known to be outdated. A replay result of `already_processed=349` proves only that the old local snapshot was fully handled. It says nothing about records added later to the live old website.

Required resolution: obtain a new read-only SQL export or the documented authenticated research API endpoint, then run a source-ID and content-hash delta analysis before importing anything.

### 15.3 Deferred file behavior is inconsistent

Documentation says unverified files remain non-public, while runtime currently resolves and can render deferred paths. Safe URL normalization does not prove that the file exists or has a safe MIME type.

Required resolution: either enforce a verified status before rendering or formally approve the current legacy-path bridge after file inventory and security verification.

### 15.4 Persisted skips are replay-terminal

The importer treats persisted skip logs as already processed. If a source row is later corrected, it will not automatically re-enter the candidate set without an explicit remediation process. This protects against accidental replay but must be considered during a live delta import.

### 15.5 Duplicate detection is intentionally limited

Duplicate detection compares normalized titles by locale. It does not merge by DOI, authors, citation, year, or fuzzy similarity. This minimizes destructive false merges but can miss semantically duplicate publications with different titles and can flag legitimate same-title outputs.

### 15.6 Ownership remains mostly unresolved

Only `5` of `289` imported publications were safely linked to current owners. This is expected under the conservative policy, but it means owner/faculty filtering and profile associations remain incomplete until reviewed.

### 15.7 Current database audit is local only

The locally verified `289/549/241` counts must not be presented as the current production state without running equivalent read-only verification against the deployed database.

## 16. Testing and Verification Coverage

The repository includes tests for:

- importer dry-run behavior;
- hidden and service-2 skips;
- approval-token enforcement;
- disabled-on-import behavior;
- metadata extraction;
- translation creation;
- deferred file-reference creation;
- migration success provenance;
- command JSON output and limits;
- conservative metadata extraction that avoids invented values;
- publication approval and actor authorization;
- duplicate-review holdback unless explicitly included;
- replay-safe publication;
- public filtering and metadata rendering;
- normalized and legacy download assembly;
- deterministic source-ID slugs;
- legacy query redirect behavior.

Important test gaps include:

- unexpected runtime exception logging for the current importer;
- complete owner ambiguity/mapping branches;
- cleaning-blocked and missing-title branches in the current service;
- duplicate-title classification during a full import;
- invalid date and URL combinations;
- attachment filtering across every legacy flag combination;
- production verification of all 289 target pages and 241 file paths;
- delta import behavior against a newer live legacy source.

## 17. Reproduction and Audit Commands

### 17.1 Verify successful import provenance

```bash
php artisan legacy-import:verify research
```

### 17.2 Dry-run the currently configured legacy source

```bash
php artisan legacy-import:research-publications --json
```

Expected for the already-processed local snapshot:

```text
scanned_rows=349
skipped_rows=349
already_processed=349
imported_rows=0
```

### 17.3 Generate a fresh private review packet

```bash
php artisan legacy-import:members-review-packets \
  --service=1 \
  --service=2 \
  --disk=local \
  --json
```

Review packets contain sensitive migration evidence and should remain under private storage.

### 17.4 Dry-run a future reviewed delta

```bash
php artisan legacy-import:research-publications \
  --batch=<new-review-batch> \
  --json
```

Do not use write mode until the fresh source export, candidate list, skip list, duplicate groups, owners, and files have been reviewed.

### 17.5 Guarded write after approval

```bash
php artisan legacy-import:research-publications \
  --write \
  --approve=legacy-research-publications-import \
  --batch=<approved-delta-batch> \
  --json
```

This must import disabled review records. It must not use `--enable`.

## 18. Required Work for the Newer Live Research Data

To migrate research added after the local snapshot:

1. Obtain a current read-only database export or the exact authenticated API endpoint for listing and retrieving all research records.
2. Record the source extraction timestamp and source database identity.
3. Compare source IDs against existing `legacy_source_table/legacy_source_id` mappings.
4. Compare hashes for existing IDs to detect edited legacy records, not only new IDs.
5. Regenerate service-1/service-2 review packets.
6. Recalculate hidden, titleless, duplicate, ownership, metadata, and attachment findings.
7. Keep teaching/course rows outside `research_publications`.
8. Import only reviewed candidates as disabled records.
9. Reconcile physical files before creating normalized media assets.
10. Review every new or changed record in AR and EN.
11. Publish through an authorized actor and preserve audit logs.
12. Verify localized public pages, legacy redirects, sitemap entries, search/indexing, and downloads.
13. Update this report with a second batch reconciliation table rather than overwriting the historical 2026-07-31 accounting.

## 19. Final Conclusion

The known local legacy research snapshot is fully accounted for. Of `349` category rows, `289` were valid visible and titled service-1 publication candidates and were imported with `549` source-present translations. The remaining `60` were not silently lost: `4` were hidden, `9` had no AR/EN title, and `47` were teaching/course records rather than research publications.

The migration preserved incomplete evidence conservatively. It extracted metadata only when supported, linked only `5` owners safely, retained `241` legacy file paths without pretending that they were verified media, and flagged `36` records for duplicate-title review. The current local database nevertheless has all `289` records published, which conflicts with the current duplicate holdback policy and requires editorial resolution.

Most importantly, this reconciliation applies to the available local snapshot, not necessarily to the current old production database. The remaining migration task is not to rerun the same 349-row import. It is to acquire a fresh authorized source, identify genuinely new or changed research, and process that delta through the same guarded review, provenance, and publication workflow.

## 20. Reference Index

| Subject | Primary implementation/evidence |
|---|---|
| Source counts and field policy | `Docs/LEGACY_RESEARCH_PUBLICATION_MAPPING.md` |
| Applied batch and replay totals | `Docs/LEGACY_IMPORT_RUNBOOK.md` |
| Placeholder and duplicate policy findings | `Docs/V2_PRE_CUTOVER_ACTIONS.md` |
| Import command | `app/Console/Commands/LegacyImportResearchPublicationsCommand.php` |
| Import selection and mapping | `app/Services/Legacy/LegacyResearchPublicationImportService.php` |
| Metadata extraction | `app/Services/Legacy/LegacyResearchMetadataExtractor.php` |
| Publication gate | `app/Services/Legacy/LegacyResearchPublicationPublishingService.php` |
| Initial new schema | `database/migrations/2026_04_18_000003_create_research_domain_tables.php` |
| Legacy metadata and file-reference schema | `database/migrations/2026_07_31_000001_add_legacy_metadata_to_research_publications.php` |
| Public eligibility | `app/Models/Research/ResearchPublication.php` |
| Public research rendering | `app/Services/Research/ResearchPageService.php` |
| Import tests | `tests/Unit/LegacyResearchPublicationImportServiceTest.php` |
| Extractor tests | `tests/Unit/LegacyResearchMetadataExtractorTest.php` |
| Publication tests | `tests/Feature/LegacyResearchPublicationPublishingServiceTest.php` |
| Public-page tests | `tests/Feature/ResearchPublicPagesTest.php` |
| Private record-level evidence | `storage/app/private/legacy-import-exports/members-review-packets/20260728_180031/` |

---

## 14. Independent verification against the live databases — 2026-08-24

The report above reconciles a **local** legacy snapshot dated 2026-03-29 and a
**local** current database. This section records an independent check of the same
claims against the **live legacy database** (`spuedu_db`, read through a
SELECT-only user) and the **deployed production database** (`spuedu_v2`).

Method: read-only queries only. Nothing was written to either database.

### 14.1 Every numeric claim verified

| Claim in this report | Verified value | Source |
|---|---|---|
| `jx_member_categories` total | **349** | live legacy DB |
| Service-1 rows | **302** | live legacy DB |
| Hidden service-1 excluded (4) | **4** | live legacy DB |
| Titleless service-1 excluded (9) | **9** | live legacy DB |
| Service-2 excluded (47) | **47** | live legacy DB |
| `research_publications` imported (289) | **289** | production |
| Translations (549) | **549** | production |
| Deferred file references (241) | **241** | production |
| Normalized `research_files` (0) | **0** | production |
| Authors present (156) | **156** | production |
| Citation present (69) | **69** | production |
| Publisher present (59) | **59** | production |
| Validated DOI (11) | **11** | production |
| Citation-backed year (63) | **63** | production |
| Keywords present (225) | **225** | production |
| Explicit journal rank (0) | **0** | production |
| Safely linked owner (5) | **5** | production |

No discrepancy was found. One apparent mismatch — a naive count of non-empty
`keywords` returns 289 — resolves in the report's favour: 121 translation rows
store an empty JSON array `[]`, and excluding those gives exactly **225**
publications with genuine keywords. The report's figure is the correct one.

### 14.2 §3.4 scope limitation — closed

§3.4 states that research may have been added to the live old website after the
2026-03-29 snapshot, and that "a fresh read-only production export or documented
research API is still required for a final delta import."

Queried directly against the live legacy database:

- `jx_member_categories` holds **349 rows** — identical to the snapshot;
- the service-type split is identical (302 service-1, 47 service-2);
- the highest legacy category id is **380**, and the highest `legacy_source_id`
  present in `research_publications` is also **380**.

**There is no delta.** The migration is complete with respect to the live legacy
research source as of this date. No further export or research API is needed for
this table. (Re-check before cutover if the old site remains editable.)

### 14.3 §2 "material discrepancy" — not present in production

The executive summary flags that all 289 publications were enabled with
`extraction_status=published`, contradicting the fail-closed duplicate policy.

That describes the **local** database. Production is correct:

| `extraction_status` | Count | Public? |
|---|---:|---|
| `published` | **253** | enabled |
| `duplicate_review` | **36** | **held private** |

The 36 duplicate-title records identified in §8.1 are not public. The
publication step was run with `--exclude-duplicate-review`, which is the policy
this report asks for. The discrepancy is a local-environment artifact, not a
production defect.

### 14.4 §9.2 deferred files — the missing evidence now exists

§9.2 gives the reason no normalized `research_files` were created: the migration
had no verified evidence of physical file existence, exact public path, MIME
type, extension consistency, or byte size.

Each of the 241 `legacy_research_file_references.legacy_path` values was resolved
against the legacy document root, retrying case-insensitively:

| Result | Count |
|---|---:|
| Resolved to a real file | **231** |
| Genuinely missing | 10 |

All 231 live under `downloads/files`. By type: **227 PDF**, 2 JPG, 1 DOC,
1 DOCX. Byte sizes are readable, and the paths already serve publicly — three
sampled files returned HTTP 200 with `Content-Type: application/pdf` and their
full byte length from `v2.spu.edu.sy`, because `downloads/files` is symlinked
into the web root for legacy media continuity.

So the blocking evidence is available: existence, exact public path, extension
and size are all confirmed, and MIME type is being served correctly today.

**231 research publications have a downloadable paper on the server that the site
does not currently offer.** This is the largest remaining content gap in the
research migration, and it is now a mechanical task rather than a research one.

The 10 unresolved paths should stay unresolved: a reference with no file behind
it must not become a public download link.

### 14.5 Locale coverage, confirmed

| Locale | Publications |
|---|---:|
| Arabic | 288 |
| English | 261 |
| Both | 260 |

Consistent with §8.2: one record (source ID `380`) has no Arabic title, and 28
have no English title. 288 + 261 = 549 translations.

### 14.6 What this section does not claim

- It does not re-verify the §8.3 empty-body list or the §8.1 duplicate pairs
  record by record; those remain editorial review items.
- It does not assert the 231 files are the *correct* paper for each publication —
  only that the referenced path exists, is readable, and is already served.
  Attaching them is still an editorial action.
- It does not change any data. Everything in this section is read-only evidence.
