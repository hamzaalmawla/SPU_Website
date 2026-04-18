# Legacy Import Runbook

## Scope

These import seeders are manual only.

They do not run from `DatabaseSeeder`.

Current safe modules:

- `ImportLegacyAdminsSeeder`
- `ImportLegacySettingsSeeder`
- `ImportLegacyStaticPagesSeeder`
- `ImportLegacyHomepageSeeder`
- `ImportLegacyLinksSeeder`

Broad legacy import is intentionally not enabled.

For the verified backlog and module-by-module implementation rules, see `Docs/LEGACY_IMPORT_IMPLEMENTATION_PLAN.md`.

## Configure Legacy Connection

Set the old database connection values in local environment only.

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
```

## Run Manual Imports

Run one module at a time.

```bash
php artisan db:seed --class="Database\\Seeders\\LegacyImport\\ImportLegacyAdminsSeeder"
php artisan db:seed --class="Database\\Seeders\\LegacyImport\\ImportLegacySettingsSeeder"
php artisan db:seed --class="Database\\Seeders\\LegacyImport\\ImportLegacyStaticPagesSeeder"
php artisan db:seed --class="Database\\Seeders\\LegacyImport\\ImportLegacyHomepageSeeder"
php artisan db:seed --class="Database\\Seeders\\LegacyImport\\ImportLegacyLinksSeeder"
```

## Reporting

Summary report:

```bash
php artisan legacy-import:report
php artisan legacy-import:report settings
php artisan legacy-import:report --details
```

Verification summary:

```bash
php artisan legacy-import:verify
php artisan legacy-import:verify static_pages
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

## Safety Rules

- do not add manual legacy import seeders to `DatabaseSeeder`
- run only one module at a time
- review `migration_logs` and `migration_rejections` after each run
- do not treat skipped rows as failures until mapping is reviewed
- do not introduce generic product tables for legacy parity

## Reconciliation Checklist

After each manual import:

1. run `php artisan legacy-import:report <module> --details`
2. run `php artisan legacy-import:verify <module>`
3. inspect imported target rows directly in MySQL
4. compare rejection reasons against the source data
5. record any new mapping decisions before expanding import coverage
