# Database Change Playbook

## Purpose

Use this document when you need to change the database schema in the SPU project.

This is the authoritative workflow for students who want to:

- add a new table
- add, rename, or remove a column
- create a foreign key
- add an index or unique constraint
- change data types safely
- explain why a change is needed before writing code

## Non-Negotiable Rules

1. Never edit the production database manually as your main solution.
2. Make schema changes through Laravel migrations.
3. Keep project scope in mind: the current repository is focused on homepage and admin foundation work.
4. Default locale is `ar`; secondary locale is `en`.
5. Prefer explicit translation tables over legacy-style `ar_name`, `en_name`, `fr_name`, `sp_name`, `ge_name` columns.
6. Business logic belongs in services, not controllers and not models.
7. A schema change is not complete until the related DTOs, contracts, services, validation, and tests are updated.

## First Question: Do You Really Need a Schema Change?

Before writing a migration, answer these questions:

1. What business problem are you solving?
2. Which feature needs the data?
3. Is this data temporary, derived, or permanent?
4. Can an existing table safely hold it?
5. Is the change inside the current project scope?
6. Will the change affect import logic from `spuedu_db.sql`?

If you cannot explain the "why" in 2 to 3 sentences, the change is probably not ready.

## Decision Matrix

| If you need to store... | Prefer | Why |
|---|---|---|
| one extra property of the same entity | a new column | simplest and most readable |
| repeating child records per parent | a new child table | avoids duplication and keeps data normalized |
| AR and EN content | a translation table | matches project multilingual rules |
| many-to-many relationships | a pivot table | correct relational design |
| searchable uniqueness | a unique index | enforces business rules in the database |
| faster filtering or sorting | an index | improves performance |

## When To Add a Column

Add a column when all of these are true:

- the value belongs to the same entity
- it is one value per row, not a repeating list
- it does not deserve its own lifecycle
- it does not need its own translations or child records

Good examples:

- add `phone` to a person-like table
- add `is_active` to a settings-like table
- add `published_at` to a publishable table

Bad examples:

- adding `ar_title` and `en_title` to every table instead of using a translation table
- adding comma-separated lists into one text column
- adding ten nullable columns for a sub-feature that really needs a child table

## When To Add a New Table

Add a table when at least one of these is true:

- the data is a separate entity
- the parent can have many of them
- the records need their own timestamps or status
- the records need translations
- the records need permissions, audit history, or attachments

Good examples:

- `faq_categories` and `faqs`
- `faculty_members` and `faculty_member_translations`
- `countries` and `country_translations`

## Column Design Rules

Choose the smallest correct type.

Common choices:

- identifiers: `id()`, `foreignId()`
- short labels: `string()`
- long formatted content: `text()` or `longText()`
- yes/no values: `boolean()`
- dates only: `date()`
- date and time: `dateTime()` or `timestamp()`
- precise numeric values: `decimal(precision, scale)`

Avoid these common mistakes:

- using `string()` for large rich text
- using `text()` where uniqueness is required
- storing numbers in strings unless the value is not truly numeric
- storing multiple meanings in one column

## Nullability, Defaults, and Backfills

For existing tables, the safest pattern is usually:

1. add the new column as nullable or with a safe default
2. backfill existing rows
3. make it required in a follow-up migration only if needed

Why:

- reduces deployment risk
- avoids breaking existing rows
- keeps rollback simpler

## Foreign Keys

Use a foreign key when a child row must reference a valid parent row.

Good reasons to use a foreign key:

- you want referential integrity
- you never want orphaned child rows
- the relationship is a normal part of the domain model

Use the delete rule intentionally:

- `cascadeOnDelete()` when children should disappear with the parent
- `nullOnDelete()` when the child may survive without the parent
- `restrictOnDelete()` when deletion must be blocked while children exist

Example:

```php
Schema::create('example_parents', function (Blueprint $table) {
    $table->id();
    $table->timestamps();
});

Schema::create('example_children', function (Blueprint $table) {
    $table->id();
    $table->foreignId('example_parent_id')
        ->constrained('example_parents')
        ->cascadeOnDelete();
    $table->timestamps();
});
```

Do not add a foreign key just because two columns happen to share similar values.

## Indexes

Indexes are for query speed and rule enforcement.

Use them on:

- foreign keys
- slugs
- emails
- status flags used in filters
- date columns used for ordering or range queries
- composite filter patterns used often together

Examples:

```php
$table->index('status');
$table->index(['is_active', 'published_at']);
$table->unique('slug');
$table->unique(['entity_id', 'locale']);
```

Index rules:

1. Do not add indexes blindly to every column.
2. Put the most selective or most commonly filtered columns first in composite indexes.
3. Every unique business rule should be protected in the database if possible.
4. Every foreign key should normally have an index.

## Translation Table Pattern

The project should not copy the legacy multi-language-wide-table design.

Preferred pattern:

```php
Schema::create('example_entities', function (Blueprint $table) {
    $table->id();
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});

Schema::create('example_entity_translations', function (Blueprint $table) {
    $table->id();
    $table->foreignId('example_entity_id')
        ->constrained('example_entities')
        ->cascadeOnDelete();
    $table->string('locale', 2);
    $table->string('title');
    $table->text('content')->nullable();
    $table->timestamps();

    $table->unique(['example_entity_id', 'locale']);
});
```

Why this is better than legacy per-language columns:

- only supported locales are stored
- easier to validate completeness
- easier to join and query cleanly
- easier to add or remove locales later

## Safe Workflow For Any Schema Change

1. Write the requirement.
2. Decide whether the change is a column, table, translation table, or pivot table.
3. Identify related services, DTOs, contracts, requests, policies, and admin forms.
4. Create the migration.
5. If old data exists, write a backfill or import strategy.
6. Add indexes and foreign keys intentionally.
7. Run migrations locally.
8. Verify reads and writes.
9. Update documentation.

## Example: Add a Column Safely

Command:

```bash
php artisan make:migration add_office_hours_to_example_table --table=example_table
```

Migration pattern:

```php
Schema::table('example_table', function (Blueprint $table) {
    $table->string('office_hours')->nullable()->after('phone');
});
```

Follow-up questions:

- Is this translatable?
- Should it be searchable?
- Does it need validation?
- Does it belong in a translation table instead?

## Example: Add a New Table Safely

Command:

```bash
php artisan make:migration create_example_records_table
```

Migration pattern:

```php
Schema::create('example_records', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('slug')->unique();
    $table->boolean('is_active')->default(true);
    $table->timestamps();

    $table->index(['is_active', 'created_at']);
});
```

## Example: Rename or Drop Carefully

These operations are riskier than adding columns.

Before renaming or dropping anything:

1. search the codebase for every usage
2. confirm there is no import dependency from the legacy SQL
3. confirm the column is not used in views, seeders, validation, or reports
4. plan the rollback path

If data is already in use, prefer a staged migration:

1. add the new column
2. backfill data
3. switch code to the new column
4. remove the old column later

## Checklist Before Creating a Foreign Key

- parent table already exists
- parent key is stable
- child rows should never point to invalid parents
- delete behavior is decided
- child column is indexed
- import order is understood

## Checklist Before Adding an Index

- the column is part of a real query pattern
- the query is frequent enough to justify the index
- the index order matches the query filter and sort order
- the index will not duplicate an existing index needlessly

## What To Update After The Migration

Schema changes are only one part of the work.

Also review:

- form requests
- DTOs
- contracts in `app/Contracts`
- services in `app/Services`
- Filament resources or admin forms
- seeders and import logic
- verification SQL
- tests

## Common Student Mistakes

1. Editing tables directly in phpMyAdmin and forgetting the migration.
2. Adding AR and EN columns everywhere instead of using translation tables.
3. Creating foreign keys without deciding delete behavior.
4. Adding indexes without knowing the query pattern.
5. Trusting legacy values without validation.
6. Dropping old columns before data is backfilled.
7. Forgetting to update services and DTOs after schema changes.

## Review Checklist

Use this before marking a schema change complete:

- [ ] The business reason is documented.
- [ ] The change is inside current project scope.
- [ ] The migration has a clear name.
- [ ] Column types are appropriate.
- [ ] Nullability and defaults are intentional.
- [ ] Foreign keys are correct.
- [ ] Indexes are intentional.
- [ ] Legacy import impact is understood.
- [ ] Related services, DTOs, and validation were updated.
- [ ] The migration was tested locally.

## Final Advice

Good database design is not about adding more tables or more columns.
It is about making the data model explain the business clearly, safely, and with as little ambiguity as possible.
