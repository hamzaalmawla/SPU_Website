# Legacy Import Implementation Plan

## Purpose

This document replaces ad-hoc planning for the remaining legacy import seeders.

It is based on the current repository state, the actual Laravel schema, and direct inspection of the legacy MySQL tables in `spu_legacy`.

It is intentionally stricter than a backlog note: if a module cannot be imported correctly from the verified source data, it is marked as blocked instead of being guessed.

## Current Verified Seeders

These seeders already exist and are the current safe baseline:

- `ImportLegacyAdminsSeeder`
- `ImportLegacySettingsSeeder`
- `ImportLegacyStaticPagesSeeder`
- `ImportLegacyHomepageSeeder`
- `ImportLegacyLinksSeeder`

## Non-Negotiable Seeder Rules

Every new legacy seeder must follow these rules.

1. Extend `BaseLegacyImportSeeder`.
2. Be manually runnable only. Do not add legacy imports to `DatabaseSeeder`.
3. Be idempotent by checking previous successful imports through `migration_logs`.
4. Import only verified AR and EN content into product tables.
5. Preserve unsupported locales or ambiguous data in `legacy_record_snapshots` instead of guessing.
6. Reject unsafe HTML or URLs using the existing sanitizer helpers.
7. Do not invent product structure when the legacy source does not support it.
8. Only write nullable foreign keys as `null` when the relation is unresolved and the raw legacy reference is preserved in logs or snapshots.
9. Use deterministic identifiers when the target schema requires generated values, for example `LEGACY-COMP-{id}` for complaint tickets.
10. Create related `media_assets` rows for legacy files and images instead of storing raw file references in product tables.

## Verified Legacy Source Reality

These facts were confirmed directly from the legacy database and must drive the design.

### Language Mapping

`jx_languages` defines the real legacy locale map:

- `1 => ar`
- `2 => en`
- `3 => fr`
- `6 => sp`
- `7 => ge`

Product imports should continue to materialize only `ar` and `en`.

### Faculty Taxonomy Source

`jx_member_categories` is not a clean faculty taxonomy table.

- it contains deep mixed hierarchies
- it is dominated by research or course-like content
- it should not be used as the source of truth for `faculties`

The reliable faculty branch lives in `jx_categories` under `parent = 19` where the parent category is `Faculties`.

Verified child rows under `jx_categories.parent = 19` include:

- `294` Administrative Sciences
- `295` Petroleum Engineering
- `296` Artificial Intelligence Engineering
- `297` Pharmacy
- `298` Dentistry
- `299` Medicine
- `5217` Building and Construction Technology Engineering

Verified non-faculty rows under the same branch that must be excluded:

- `293` University Requirements
- `3312` Center for Languages and Skills

### Faculty Members Source

`jx_members` currently has `0` rows.

The real legacy people data lives in:

- `jx_councils`
- `jx_councils1`

Those tables contain names, positions, specializations, photos, CV files, email, and phone fields.

### Research Source

`jx_member_categories` and `jx_member_items` together form the research/publication source.

- `jx_member_categories` contains publication-like titles, briefs, full content, keywords, visibility, and hierarchy
- `jx_member_items` contains related files and per-category attachments

### FAQ Source

`jx_faqs` contains the FAQ entries.

Important facts:

- all rows have `dep_id = 0`
- language is driven by the numeric `lang` field
- there is no dedicated legacy FAQ category table

The FAQ import must not invent a category taxonomy.

### Complaint Source

Complaint data is split across:

- `jx_complaint_cats`
- `jx_complaints`

The legacy complaint rows contain free-text question and answer pairs, not a rich workflow model.

### Student Source

Student-related legacy rows live in:

- `jx_graduated_students`
- `jx_good_students`

Important facts:

- both sources contain names, grades, year data, and a photo reference
- both sources use a numeric `department_id` that behaves like a legacy faculty code, not a verified modern department foreign key
- both sources also use `section_id`, but there is no trustworthy one-to-one mapping from those values into the new `departments` table

Committed mapping artifact:

- `config/legacy_student_taxonomy.php`

Verified faculty code mapping:

- `2 => medicine`
- `3 => dentistry`
- `4 => pharmacy`
- `5 => computer-and-informatics-engineering`
- `6 => petroleum-engineering`
- `7 => administrative-sciences`

`section_id` values `1` and `2` must be preserved as legacy metadata and must not be forced into `departments`.

### Country Source

`jx_countries` only contains names.

The new `countries` table requires a non-null unique `code`, and legacy data does not provide ISO codes.

That means country import is only correct if a curated lookup map is committed alongside the seeder.

### Student Schema Gap

Student names are now expected to live in:

- `alumni_translations.full_name`
- `honor_student_translations.full_name`

The remaining blocker for full student normalization is not name storage. It is the unresolved mapping of legacy `section_id` values into the new `departments` structure.

## Seeder Backlog

## Ready Now

### `ImportLegacyFacultiesSeeder`

Source:

- `jx_categories`

Target:

- `faculties`
- `faculty_translations`

Rules:

- read only rows where `parent = 19`
- include only verified faculty rows
- exclude `293` and `3312`
- derive `slug` from English name when present, otherwise Arabic name
- map `category_order -> sort_order`
- map `is_visible -> is_enabled`
- map `ar_name/en_name -> translation name`
- map `ar_brief/en_brief -> short_description`
- map sanitized `ar_data/en_data -> description`
- preserve other locales in `legacy_record_snapshots`

### `ImportLegacyFacultyMembersSeeder`

Source:

- `jx_councils`
- `jx_councils1`

Target:

- `faculty_members`
- `faculty_member_translations`
- `media_assets`

Rules:

- union rows from both tables
- deduplicate by normalized Arabic or English name plus email when available
- import photo and CV files into `media_assets`
- map `email`, `phone/mobile`, visibility, and ordering fields
- map `ar_name/en_name -> full_name`
- map `ar_position/en_position -> position`
- map `ar_specialization/en_specialization -> specializations`
- map `ar_brief/ar_data` and `en_brief/en_data` into `bio`
- leave `faculty_id` and `department_id` null unless a committed legacy code map exists
- if a legacy faculty code is present but unresolved, preserve it in snapshot metadata

### `ImportLegacyResearchSeeder`

Source:

- `jx_member_categories`
- `jx_member_items`

Target:

- `research_publications`
- `research_publication_translations`
- `research_files`
- `media_assets`

Rules:

- treat publication-like rows in `jx_member_categories` as the primary research entity
- use `member_category_order -> sort_order`
- use `is_visible -> is_enabled`
- use `start_date` or `post_date` when available for `published_at`
- map `ar_name/en_name -> title`
- map `ar_brief/en_brief -> excerpt`
- map sanitized `ar_data/en_data -> abstract`
- map `ar_keywords/en_keywords` into snapshot metadata if there is no direct product field
- import `en_file` and `ar_file` from `jx_member_items` as related `research_files`
- leave `faculty_member_id` null unless authors can be matched with high confidence
- preserve unsupported locales and ambiguous hierarchy metadata in snapshots

### `ImportLegacyFaqsSeeder`

Source:

- `jx_faqs`

Target:

- `faqs`
- `faq_translations`

Rules:

- do not fabricate FAQ categories from `subject`
- keep `faq_category_id = null` unless a deliberate category strategy is introduced later
- map `faq_order -> sort_order`
- map `is_visible -> is_enabled`
- treat rows with `lang = 1` as Arabic and `lang = 2` as English
- preserve rows in other locales to `legacy_record_snapshots`
- map `question` and `answer` into sanitized translation fields
- use `subject` as a keyword or metadata hint, not as a category
- skip rows that have no usable question or answer after cleaning

### `ImportLegacyComplaintCategoriesSeeder`

Source:

- `jx_complaint_cats`

Target:

- `complaint_categories`
- `complaint_category_translations`

Rules:

- derive deterministic slugs from translated names
- map `is_visible -> is_enabled`
- resolve `assigned_to_user_id` by matching `jx_complaint_cats.email` to imported `users.email`
- map `ar_name/en_name -> translation name`
- leave `assigned_to_user_id` null when no matching imported user exists

### `ImportLegacyComplaintsSeeder`

Source:

- `jx_complaints`

Target:

- `complaints`

Rules:

- create deterministic `ticket_number` values such as `LEGACY-COMP-{id}`
- resolve `complaint_category_id` from successful category imports
- map `first_name + last_name -> submitter_name`
- map `email` and `phone`
- derive `subject` from a stripped and shortened version of `question`
- map sanitized `question -> description`
- map sanitized `answer -> resolution`
- set `status = 'resolved'` when an answer exists, otherwise `status = 'open'`
- set `priority = 'normal'` unless a verified rule is introduced later
- use `post_date` as `created_at`
- leave user foreign keys null unless there is an explicit trusted match

### `ImportLegacyCareerLinksSeeder`

Source:

- `jx_job_sites`

Target:

- `career_links`
- `career_link_translations`
- optional `media_assets` for logos if needed later

Rules:

- map `url -> url`
- set `is_external = true`
- map `record_order -> sort_order`
- map `is_visible -> is_enabled`
- map `ar_name/en_name -> title`
- map sanitized `ar_data/en_data -> description`
- preserve other locales in snapshots instead of importing them into product tables

## Ready After Curated Lookup Is Committed

### `ImportLegacyCountriesSeeder`

Source:

- `jx_countries`

Target:

- `countries`
- `country_translations`

Prerequisite:

- commit a vetted lookup map from legacy country names to ISO `code` and optional `code3`

Rules:

- never generate fake two-character country codes on the fly
- reject or snapshot any country name that does not resolve through the committed lookup
- map `ar_name/en_name -> translation name`

### `ImportLegacyCitiesSeeder`

Source:

- `jx_cities`

Target:

- `cities`
- `city_translations`

Prerequisite:

- countries must already be imported through the vetted ISO map

Rules:

- resolve `country_id` through successful country import logs
- map `is_visible -> is_enabled`
- map `ar_name/en_name -> translation name`

## Ready After Faculties Import

### `ImportLegacyAlumniSeeder`

Ready after `ImportLegacyFacultiesSeeder` because the faculty-code mapping is now committed.

Rules:

- resolve `faculty_id` through `config('legacy_student_taxonomy.faculty_code_map')`
- do not map legacy `section_id` into `departments`
- keep target `department_id = null` until a verified department dictionary exists
- write student names into `alumni_translations`
- preserve raw `department_id` and `section_id` in migration metadata or snapshots

### `ImportLegacyHonorStudentsSeeder`

Ready after `ImportLegacyFacultiesSeeder` because the faculty-code mapping is now committed.

Rules:

- resolve `faculty_id` through `config('legacy_student_taxonomy.faculty_code_map')`
- do not map legacy `section_id` into `departments`
- keep target `department_id = null` until a verified department dictionary exists
- write student names into `honor_student_translations`
- preserve raw `department_id` and `section_id` in migration metadata or snapshots

## Blocked Until Schema or Mapping Work Is Done

### `ImportLegacyDepartmentsSeeder`

Blocked because there is no verified legacy source that cleanly matches the new `departments` table.

- `jx_member_categories` is mixed research and course content
- `jx_categories` does not expose a reliable department hierarchy across all faculties

Do not implement this seeder until a source mapping document is committed.

### `ImportLegacyCouncilsSeeder`

Blocked because the legacy source only exposes numeric `service_type` groupings and person rows.

The new schema requires real council entities with names.

Do not synthesize councils such as `legacy-council-7` unless the project explicitly accepts that product shape.

Correct prerequisite:

- commit a service-type-to-council mapping table

Once that exists, `councils` can be created first and `council_members` can point to previously imported `faculty_members`.

## Execution Order

Run only seeders that are actually ready.

### Batch 1

- `ImportLegacyFacultiesSeeder`

### Batch 2

- `ImportLegacyFacultyMembersSeeder`
- `ImportLegacyResearchSeeder`
- `ImportLegacyAlumniSeeder`
- `ImportLegacyHonorStudentsSeeder`

### Batch 3

- `ImportLegacyFaqsSeeder`
- `ImportLegacyComplaintCategoriesSeeder`
- `ImportLegacyComplaintsSeeder`
- `ImportLegacyCareerLinksSeeder`

### Batch 4

- `ImportLegacyCountriesSeeder`
- `ImportLegacyCitiesSeeder`

Run Batch 4 only after the ISO lookup artifact exists.

### Deferred

- `ImportLegacyDepartmentsSeeder`
- `ImportLegacyCouncilsSeeder`

## Seeder Skeleton

Each new seeder should keep the existing pattern already used in the repository.

```php
class ImportLegacyExampleSeeder extends BaseLegacyImportSeeder
{
    public function run(): void
    {
        $module = 'example';
        $batch = $this->batchName($module);

        foreach ($this->legacyRows('jx_example') as $row) {
            $sourceId = $this->normalizedInteger($this->rowValue($row, 'id'));

            if ($this->alreadyImported('jx_example', $sourceId, 'target_table')) {
                continue;
            }

            // 1. clean and validate the row
            // 2. reject or snapshot ambiguous data
            // 3. write product rows
            // 4. write translations
            // 5. import related media if needed
            // 6. log success
        }
    }
}
```

## Verification After Each Seeder

After each run:

1. run `php artisan legacy-import:report {module} --details`
2. run `php artisan legacy-import:verify {module}`
3. inspect the imported target rows directly in MySQL
4. review `migration_rejections` and `legacy_record_snapshots`
5. document any new committed lookup maps before expanding coverage
