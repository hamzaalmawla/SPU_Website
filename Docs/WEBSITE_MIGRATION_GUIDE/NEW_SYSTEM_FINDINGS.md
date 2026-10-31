# New System Findings

## Evidence Base

This document is based on direct inspection of:

- live `spuedu_new` database connectivity
- `php artisan migrate:status`
- direct table queries against the live new DB
- `routes/web.php`
- `resources/views/welcome.blade.php`
- `app/Providers/AppServiceProvider.php`
- current migrations in `database/migrations/`
- current legacy import config in `config/old_database.php`
- current seeders in `database/seeders/`

## What The New System Proves

### Live database status

- database connection is live
- all 12 migrations currently in the repository are marked as run
- 49 tables currently exist in `spuedu_new`
- content/domain tables are effectively empty
- only `migrations` contains rows (`12`)

### Current public route inventory

`routes/web.php` currently contains:

- `GET /` -> default Laravel welcome view

`bootstrap/app.php` also exposes:

- health endpoint at `/up`

No other public routes are currently proven by the codebase.

### Current public rendering state

- only one view file exists: `resources/views/welcome.blade.php`
- it is the default Laravel starter page
- no public page templates, homepage templates, archive templates, or legacy-resolution routes are implemented

### Current automated tests

- `tests/Feature/ExampleTest.php` only checks that `/` returns `200`
- no migration, redirect, URL-resolution, sitemap, multilingual, or file-preservation tests exist yet

## New Database Inventory

### Actual tables now present

The live DB currently contains:

- framework tables: `users`, `cache`, `jobs`, `sessions`, `password_reset_tokens`, `migrations`, `failed_jobs`, `job_batches`
- migration support table: `migration_logs`
- university/support tables from the existing migration set

### Content/domain tables currently present

| Group | Tables |
|---|---|
| faculty | `faculty_categories`, `faculty_category_translations`, `faculty_members`, `faculty_member_translations`, `faculty_publications`, `faculty_publication_translations` |
| councils | `councils`, `council_translations`, `council_members`, `council_member_translations`, `council_meetings`, `council_meeting_translations` |
| students | `honor_students`, `alumni`, `student_achievements` |
| faq | `faq_categories`, `faq_category_translations`, `faqs`, `faq_translations` |
| complaints | `complaint_categories`, `complaint_category_translations`, `complaints`, `complaint_responses`, `complaint_status_history` |
| jobs | `job_categories`, `job_category_translations`, `job_postings`, `job_posting_translations`, `job_applications`, `job_application_status_history` |
| comments | `comments`, `comment_reactions`, `comment_reports` |
| reference | `languages`, `language_translations`, `countries`, `country_translations`, `cities`, `city_translations` |

### Important current fact

All of those content tables currently have `0` rows.

That means the new DB is structurally migrated but not populated.

## Intended New Architecture In Code

The codebase proves intended support for:

- homepage sections
- homepage publishing
- pages
- preview
- menu trees
- navigation
- settings
- SEO metadata
- slugs
- public AR/EN switching

That intent is visible through:

- 13 service interfaces in `app/Contracts`
- 44 DTOs in `app/DTOs`

## Major Gap: Implementations Are Placeholders

`app/Providers/AppServiceProvider.php` binds 13 service interfaces to 13 placeholder services under `app/Services/Placeholders/`.

These placeholders throw `BadMethodCallException` instead of implementing functionality.

Example impact:

- page creation not implemented
- page publishing not implemented
- public page retrieval by slug not implemented
- preview not implemented
- menu operations not implemented
- SEO resolution not implemented

This means the codebase currently describes a target architecture but does not yet operate as that architecture.

## Major Gap: Schema Mismatch Between Intent And Actual Tables

### Config-driven target names that do not exist

`config/old_database.php` maps legacy tables into targets such as:

- `content_items`
- `categories`
- `pages`
- `archived_content`
- `homepage_media`
- `documents`
- `logos`
- `settings`
- `site_sections`
- `user_categories`
- `user_service_assignments`

Those target tables do not currently exist in the live new DB.

### Missing current-scope CMS tables

For the repository’s current scope, the live new DB is still missing the most important foundation tables:

- `pages`
- `page_translations`
- `page_seo_meta`
- `homepage_sections`
- `homepage_section_translations`
- `homepage_drafts`
- `preview_tokens` or equivalent
- `menus`
- `menu_items`
- `settings`
- `media_assets`
- redirect and legacy lookup tables

## Migration Seeder Status

`database/seeders/CompleteDatabaseMigrationSeeder.php` is not a finished import engine.

Proven issues:

- many branches log `Schema analysis needed`
- the admin migration writes to fields/tables not created by current migrations
- language mapping assumptions do not match the old DB language IDs cleanly
- current import config and current schema are not aligned

## Dangerous Conflicts And Risks

### 1. Legacy `/admin` path conflict

The old system proves `spu.edu.sy/admin` was a public legacy section.

The repository scope includes building an admin panel foundation.

Even though no admin route is currently implemented, future reuse of `/admin` would create a major legacy-path conflict.

### 2. Slug assumptions exceed current data reality

Some new migrations create `slug` columns, but the old DB does not provide slugs.

If the new site becomes slug-only without legacy query resolution, major discoverability loss is likely.

### 3. No current redirect architecture

No redirect tables, middleware, resolver, or catch-all legacy routing layer is currently implemented.

### 4. No current sitemap implementation

No sitemap routes or generators are currently proven in the codebase.

### 5. Public storage not linked

`php artisan about` reports `public/storage` is not linked.

That is a practical launch risk for file and media delivery.

## What The New System Strongly Suggests

- the project was intended to evolve into a bilingual CMS foundation
- the service layer design follows the repository’s architectural rules
- the current implementation stopped at schema/contracts/scaffolding rather than public-site completion

## What Cannot Yet Be Proven

- actual current admin panel implementation
- actual page rendering logic beyond `/`
- actual current SEO output
- actual current multilingual public switching
- any live redirect or sitemap behavior

Because those features are not implemented in the current codebase, they cannot be treated as working.

