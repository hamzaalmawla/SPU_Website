# SQL And Technical Appendix

## Old DB Discovery Queries

```sql
SELECT table_name, table_rows
FROM information_schema.tables
WHERE table_schema = 'spuedu_db'
ORDER BY table_name;
```

```sql
SELECT table_name, column_name, data_type
FROM information_schema.columns
WHERE table_schema = 'spuedu_db'
  AND (
    column_name LIKE '%url%' OR
    column_name LIKE '%lang%' OR
    column_name LIKE '%file%' OR
    column_name LIKE '%photo%' OR
    column_name LIKE '%parent%' OR
    column_name LIKE '%keyword%'
  )
ORDER BY table_name, ordinal_position;
```

## Old DB URL Extraction Queries

```sql
SELECT 'jx_categories' AS source_table, id AS source_id, url
FROM jx_categories
WHERE COALESCE(url, '') <> ''
UNION ALL
SELECT 'jx_docs', id, url
FROM jx_docs
WHERE COALESCE(url, '') <> ''
UNION ALL
SELECT 'jx_member_categories', id, url
FROM jx_member_categories
WHERE COALESCE(url, '') <> '';
```

## Old DB File Inventory Queries

```sql
SELECT 'jx_items' AS source_table, id AS source_id, en_file AS legacy_file_name
FROM jx_items
WHERE COALESCE(en_file, '') <> ''
UNION ALL
SELECT 'jx_member_items', id, en_file
FROM jx_member_items
WHERE COALESCE(en_file, '') <> ''
UNION ALL
SELECT 'jx_councils', id, cv
FROM jx_councils
WHERE COALESCE(cv, '') <> ''
UNION ALL
SELECT 'jx_councils', id, ar_cv
FROM jx_councils
WHERE COALESCE(ar_cv, '') <> ''
UNION ALL
SELECT 'jx_councils1', id, cv
FROM jx_councils1
WHERE COALESCE(cv, '') <> '';
```

## Old DB Orphan Checks

```sql
SELECT i.id, i.category_id
FROM jx_items i
LEFT JOIN jx_categories c ON c.id = i.category_id
WHERE c.id IS NULL;
```

```sql
SELECT c.id, c.parent
FROM jx_categories c
LEFT JOIN jx_categories p ON p.id = c.parent
WHERE c.parent <> 0 AND p.id IS NULL;
```

## New DB Inventory Query

```sql
SHOW TABLES;
```

```sql
SELECT COUNT(*) FROM migrations;
SELECT COUNT(*) FROM faculty_categories;
SELECT COUNT(*) FROM councils;
SELECT COUNT(*) FROM faqs;
SELECT COUNT(*) FROM complaints;
```

## New DB Gap Verification Queries

Use these to confirm missing launch-critical tables:

```sql
SHOW TABLES LIKE 'pages';
SHOW TABLES LIKE 'page_translations';
SHOW TABLES LIKE 'page_seo_meta';
SHOW TABLES LIKE 'homepage_sections';
SHOW TABLES LIKE 'menus';
SHOW TABLES LIKE 'settings';
SHOW TABLES LIKE 'legacy_exact_redirects';
SHOW TABLES LIKE 'legacy_url_map';
SHOW TABLES LIKE 'legacy_file_inventory';
```

## Code Audit Checks

### Public route audit

Inspect:

- `routes/web.php`
- `bootstrap/app.php`

### Placeholder implementation audit

Inspect:

- `app/Providers/AppServiceProvider.php`
- `app/Services/Placeholders/*.php`

### Legacy import mismatch audit

Inspect:

- `config/old_database.php`
- `database/seeders/CompleteDatabaseMigrationSeeder.php`

### Frontend readiness audit

Inspect:

- `resources/views/`
- `tests/Feature/`

## Technical Facts Proven During This Audit

- live new DB table count: `49`
- live content row counts: `0` for all domain tables inspected
- service contracts: `13`
- placeholder service implementations: `13`
- DTO files: `44`
- public route file currently exposes only `/`

