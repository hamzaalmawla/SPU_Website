# Research Publication Database Delta - 2026-08-27

## Purpose

This report compares the legacy research data in these two SQL snapshots:

| Snapshot | File | Generated | SHA-256 |
|---|---|---|---|
| Previous snapshot | `Docs/STUDENT_GUIDE/spuedu_db.sql` | 2026-03-29 | `7df5cacee877fe2374b3f4c27c599064c24962de1a995cb632cba8137d81d79a` |
| New snapshot | `Docs/spuedu_db (1).sql` | 2026-08-27 10:05 | `4fef016e21043477b5ff12ca50599bc3f4b2690ea1a560a439d8549bae03eb80` |

The goal was to identify research publications present in the new legacy dump but missing from the previous dump or the current Laravel site. The comparison covers the legacy publication parent table `jx_member_categories`, the related child/attachment table `jx_member_items`, and current `research_publications` coverage.

## Conclusion

**No missing research publications were found.**

The new dump has exactly the same `289` importable research publication IDs as the previous dump. The current configured Laravel database also contains all `289` IDs, with no missing or extra active legacy publication IDs.

There are no new category rows, no deleted category rows, no newly importable records, no removed importable records, and no changes to titles, visibility, service type, ownership, URLs, semantic metadata after newline normalization, or attachments that would require a delta import.

One publication parent row differs at the byte level, but the only difference is line-ending encoding in `ar_data`. Its text and HTML are unchanged after normalizing CRLF/LF line endings.

## Summary Counts

| Measure | Previous | New | Difference |
|---|---:|---:|---:|
| `jx_member_categories` rows | 349 | 349 | 0 |
| `jx_member_categories` added IDs | - | - | 0 |
| `jx_member_categories` deleted IDs | - | - | 0 |
| `jx_member_categories` byte-level changed rows | - | - | 1 |
| `jx_member_categories` semantic content changes | - | - | 0 |
| Service-1 rows | 302 | 302 | 0 |
| Service-2 rows | 47 | 47 | 0 |
| Visible service-1 rows | 298 | 298 | 0 |
| Visible service-1 rows missing both AR and EN titles | 9 | 9 | 0 |
| Importable publication candidates | 289 | 289 | 0 |
| `jx_member_items` rows | 429 | 429 | 0 |
| `jx_member_items` added IDs | - | - | 0 |
| `jx_member_items` deleted IDs | - | - | 0 |
| `jx_member_items` changed rows | - | - | 0 |

## Stable-ID Comparison

| Table | Minimum ID | Maximum ID | Result |
|---|---:|---:|---|
| `jx_member_categories` | 11 | 380 | Previous and new ID sets match exactly |
| `jx_member_items` | 7 | 467 | Previous and new ID sets match exactly |

The ordered ID-list hashes also match between snapshots:

| Table | Ordered ID-list SHA-256 |
|---|---|
| `jx_member_categories` | `aaf23bae7ec82302921520f3c37f53560112d6c102bc2850cc3b107cd939682c` |
| `jx_member_items` | `3bff7bf4bdd8aa3ddf5e2e97ca764dcaff901ca826bae8b84d7e83081137a33f` |

## Publication Eligibility

Eligibility was evaluated using the relevant rules in `app/Services/Legacy/LegacyResearchPublicationImportService.php`:

- `service_type` must be numeric `1`;
- `is_visible` must satisfy the importer's visibility rule;
- at least one of `ar_name` or `en_name` must remain non-empty after trimming and HTML-entity decoding.

The importer treats `is_visible = NULL` as visible, in addition to `1`. That implementation detail was reproduced in the comparison.

| Eligibility result | Count |
|---|---:|
| Importable in both snapshots | 289 |
| Newly importable in the new snapshot | 0 |
| Previously importable but no longer importable | 0 |
| Visibility transitions affecting eligibility | 0 |
| Title transitions affecting eligibility | 0 |
| Service-type transitions affecting eligibility | 0 |

## Changed Parent Record

Only one `jx_member_categories` row differs in its exact decoded field bytes.

### Legacy source ID 11

| Property | Value |
|---|---|
| Arabic title | `Unusual Pulmonary Masses in Beta Thalassemia Major` |
| English title | `Unusual Pulmonary Masses in Beta Thalassemia Major` |
| Previous source evidence | `Docs/STUDENT_GUIDE/spuedu_db.sql:39550` |
| New source evidence | `Docs/spuedu_db (1).sql:41928` |
| Changed field | `ar_data` |
| Importable before | Yes |
| Importable now | Yes |
| Migration action required | No |

The old value uses LF line separators, while the new value uses CRLF separators. There are no text or HTML changes after newline normalization.

| Verification | Previous | New |
|---|---:|---:|
| Decoded byte length | 2,527 | 2,537 |
| LF characters | 10 | 10 |
| CRLF sequences | 0 | 10 |
| Exact field SHA-256 | `14f2990c299f087672a70ddd28c12735fd49dcc9a5b66ca462ad5b12febbb198` | `d258dc9904355237a6c433960742fcbde4c74f99ca41e5ef8fb7d83cc3d963b6` |
| Newline-normalized SHA-256 | `14f2990c299f087672a70ddd28c12735fd49dcc9a5b66ca462ad5b12febbb198` | `14f2990c299f087672a70ddd28c12735fd49dcc9a5b66ca462ad5b12febbb198` |

No title, brief, keyword, metadata, owner, service type, visibility, ordering, photo, URL, or date field changed for this record.

The configured current Laravel database already represents it as:

| Current field | Value |
|---|---|
| `research_publications.id` | 1 |
| `legacy_source_table` | `jx_member_categories` |
| `legacy_source_id` | 11 |
| `extraction_status` | `published` |
| `is_enabled` | 1 |

## Child And Attachment Comparison

Every column of every `jx_member_items` row is identical between the two snapshots.

The attachment comparison reproduced the importer conditions:

- matching `member_category_id`;
- `service_type = 1`;
- `is_visible = 1`;
- `is_accepted = 1`;
- at least one non-empty `en_file`, `ar_file`, or `photo` value.

| Attachment measure | Previous | New | Difference |
|---|---:|---:|---:|
| Total child rows | 429 | 429 | 0 |
| Child rows satisfying child-level predicates | 242 | 242 | 0 |
| Paths from child-level predicates before parent eligibility | 243 | 243 | 0 |
| Importer-reachable child rows under eligible parents | 240 | 240 | 0 |
| Importer-reachable paths | 241 | 241 | 0 |
| Added child IDs | 0 | 0 | 0 |
| Deleted child IDs | 0 | 0 | 0 |
| Changed child rows | 0 | 0 | 0 |

The path count can exceed the child-row count because one child row can provide more than one unique path. Two child rows satisfy the child-level attachment predicates but are not reachable by the importer because their parent publications are ineligible:

| Child ID | Parent ID | Exclusion reason | Previous evidence | New evidence |
|---:|---:|---|---|---|
| 158 | 163 | Parent is hidden with `is_visible = 0` | Parent `Docs/STUDENT_GUIDE/spuedu_db.sql:39711`; child `:40136` | Parent `Docs/spuedu_db (1).sql:42089`; child `:42514` |
| 431 | 339 | Parent has neither an Arabic nor English title | Parent `Docs/STUDENT_GUIDE/spuedu_db.sql:39899`; child `:40388` | Parent `Docs/spuedu_db (1).sql:42277`; child `:42766` |

After parent eligibility is applied, the expected importer output is 240 attachment groups producing 241 paths. This exactly matches the current database's 241 deferred references from 240 distinct child source IDs and 241 distinct legacy paths. The August dump contains no new attachment evidence.

## Current Laravel Coverage

A read-only comparison against the currently configured Laravel database found:

| Measure | Count |
|---|---:|
| Importable source IDs in the new dump | 289 |
| Active `research_publications` sourced from `jx_member_categories` | 289 |
| Distinct active legacy source IDs | 289 |
| New-dump source IDs missing from Laravel | 0 |
| Extra active Laravel legacy source IDs | 0 |

The source candidate ID set and the active Laravel legacy publication ID set match exactly. As separate migration-log evidence, `php artisan legacy-import:verify research` reports `289` distinct successful research source mappings; target existence and exact ID-set coverage were established by the read-only target comparison, not by that command alone.

## Required Action

No publication delta import is required from `Docs/spuedu_db (1).sql`.

Do not create duplicate `research_publications` from this dump. The only parent-row byte difference is newline formatting and should not trigger a content update unless there is a separate requirement to normalize stored source HTML.

The outstanding research work documented elsewhere, including deferred file reconciliation and editorial review, remains valid but is not caused by new publication records in this SQL snapshot.

## Methodology

1. Stream-parse the `INSERT` statements for `jx_member_categories` and `jx_member_items` from both dumps.
2. Decode MySQL quoted values, escapes, `NULL`, embedded HTML, and embedded line endings.
3. Key records by stable numeric `id` and reject duplicate IDs.
4. Compare complete row values across every column.
5. Compare importer-relevant fields separately from exact raw-content differences.
6. Apply the current importer eligibility and attachment-reference rules.
7. Verify physical tuple counts, minimum and maximum IDs, and ordered ID-list hashes independently.
8. Normalize newline styles only as a secondary semantic comparison; retain the exact byte-level difference in this report.
9. Compare all eligible source IDs with active current Laravel `legacy_source_id` values using read-only access.

## Scope And Caveats

- This report is a complete comparison of research data in `jx_member_categories` and `jx_member_items`, not a whole-database audit of unrelated legacy modules.
- Current Laravel coverage refers to the database configured in this workspace at comparison time; it does not independently assert the state of another deployment.
- A real import can additionally be blocked by cleaning policy or prior processing. Those conditions do not create a source delta here because no eligible source IDs changed.
- The two whole SQL files have different hashes because they contain dump-format and unrelated database differences. Those whole-file differences do not imply a research-publication delta.
