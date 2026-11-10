# Legacy Research Publication Mapping

## Scope And Status

The old `/members/` data contains:

- `349` `jx_member_categories` rows.
- `302` service-1 research/publication-like rows.
- `47` service-2 teaching/course rows, which are not research publications.
- `289` visible, titled service-1 publication candidates.
- `250` service-1 child items and `241` file paths across `240` attachment groups.

Batch `approved-structured-research-import-20260731` imported all `289` candidates into the new relational research schema as disabled review records before the separate archive publication decision below.

Publication batch `approved-public-research-20260731` historically enabled all `289` imported records for the public research archive. The approved duplicate-title policy is now fail-closed: duplicate-review records remain private unless the publisher supplies `--include-duplicate-review` alongside the existing publication approval. Records remain source-authentic, unknown dates remain unknown, and missing files remain deferred rather than exposed as broken downloads.

## Field Mapping

| New field | Legacy evidence | Rule |
|---|---|---|
| Localized title | `ar_name`, `en_name` | Store only source-present locales; never synthesize a missing translation. |
| Authors/byline | Isolated Author/Authors or Arabic-equivalent section | Store the complete source byline. Do not infer authors from owner ID. |
| Abstract | Isolated Abstract/Summary section | Prefer the extracted section; preserve sanitized source body when no section exists. |
| Citation | Isolated Published in/Journal or Arabic-equivalent section | Preserve citation text independently from publisher. |
| Publisher/journal | Clean leading citation segment | Reject copyright, license, received, revised, and accepted prose. |
| Keywords | `ar_keywords`, `en_keywords`; labeled section fallback | Prefer explicit source columns and store locale-specific arrays. |
| DOI | DOI syntax in title/body/citation | Normalize and store only validated `10.<registrant>/<suffix>` values. |
| Publication year | Year inside an isolated citation section | Never use category zero dates, item upload date, filename timestamp, abstract prose, or migration date. |
| Journal rank | Explicit Q1-Q4 text only | Current source coverage is zero; rank remains null rather than inferred. |
| Current faculty owner | Approved `jx_councils -> faculty_members` mapping and unambiguous `jx_councils`-only owner | Dual-table, `jx_councils1`-only, and missing owners remain unlinked. |
| Legacy owner | `parent` | Preserve ID and source-resolution status even when current owner is unresolved. |
| Files | Visible, accepted, service-1 `jx_member_items` paths | Store deferred references; create `research_files` only after cPanel bytes, MIME, and checksum verification. |

## Ownership Safety

`jx_member_categories.parent` is a staff-owner reference, not a category hierarchy and not necessarily the sole author. The same numeric ID can identify different people in `jx_councils` and `jx_councils1`.

Allowed automatic owner link:

- Owner ID exists only in `jx_councils`.
- A successful current `jx_councils -> faculty_members` migration mapping exists.
- The target faculty member still exists.

All other cases retain one of these provenance states:

- `both_sources`
- `jx_councils1_only`
- `missing`
- `jx_councils_only` without a valid current target

No numeric-ID-only or fuzzy-name attachment is allowed.

## Applied Coverage

| Coverage | Publications |
|---|---:|
| Imported candidates | `289` |
| Source translations | `549` |
| Publications with extracted authors | `156` |
| Publications with citation | `69` |
| Publications with clean publisher/journal | `59` |
| Publications with validated DOI | `11` |
| Publications with citation-backed year | `63` |
| Publications with explicit keywords | `225` |
| Publications with journal rank | `0` |
| Safely linked current owners | `5` |
| Duplicate-title review records | `36` |
| Deferred file paths | `241` |

## Publication Gate

Imported records remain disabled until the research editorial workflow can approve:

1. Publication proof and duplicate disposition.
2. Localized title and usable abstract/body.
3. Author/byline review where available.
4. Owner mapping or explicit owner-unresolved approval.
5. Citation, publisher, DOI, year, and rank accuracy.
6. File reconciliation or explicit no-file publication.
7. Preview, SEO, indexing, audit, and cache invalidation.
8. Exact `/members/` continuity redirect after the target becomes public.

Service-2 rows, hidden rows, and titleless rows remain excluded and logged as explicit skips.

## Public Publication

The public publication command is dry-run-first and requires an unlocked publisher:

```bash
php artisan legacy-import:publish-research --actor=<publisher-user-id> --batch=<batch> --json
php artisan legacy-import:publish-research --actor=<publisher-user-id> --write --approve=publish-legacy-research --batch=<batch> --json
php artisan legacy-import:publish-research --actor=<publisher-user-id> --write --approve=publish-legacy-research --include-duplicate-review --batch=<batch> --json
```

The publication gate requires successful import provenance and a source title. Duplicate-review records are blocked unless explicitly included. It does not require a fabricated date, rank, owner, or file. Legacy records become public through `extraction_status=published`; native records retain their ordinary date-based public rules.

Public service-1 `/members/index.php?page=show&ex=2&dir=items&ser=1&cat_id=<id>` requests now resolve to the localized Laravel research publication URL when the imported source record is enabled. Other `/members/` services remain private and unresolved.

## Commands

Dry run:

```bash
php artisan legacy-import:research-publications --batch=<review-batch> --json
```

Guarded disabled import:

```bash
php artisan legacy-import:research-publications --write --approve=legacy-research-publications-import --batch=<approved-batch> --json
```

The `--enable` flag is intentionally rejected in write mode.
