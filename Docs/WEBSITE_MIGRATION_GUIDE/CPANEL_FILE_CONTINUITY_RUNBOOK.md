# cPanel File Continuity Runbook

## Purpose

Preserve verified public PDFs, documents, and images when the SPU domain switches to Laravel. This procedure preserves static bytes only. It must not expose or execute the retired PHP/CMS application.

## 1. Record Hosting Facts Privately

Record these values outside Git:

- Current domain document root.
- Legacy public root containing the old static directories.
- Laravel release path and Laravel `public/` path.
- cPanel/PHP user and filesystem group.
- Whether cPanel permits changing the domain document root.
- Whether symbolic links are permitted and followed by Apache/LiteSpeed.
- Whether account-level Apache aliases are available.
- Backup path and SHA-256 for the legacy static tree.

Do not put credentials or absolute production paths in Git or support tickets.

## 2. Generate Read-Only Evidence

Run this on staging or cPanel against the real legacy public root:

```bash
php artisan legacy-import:file-continuity-probe /absolute/legacy/public/root \
  --target-root=/absolute/laravel/public
```

The command:

- reads only configured static trees;
- computes SHA-256, MIME type, and size by default;
- records URL-encoded paths;
- identifies case-collision groups;
- identifies identical and differing files already present at the Laravel public path;
- flags symlinks that resolve outside the supplied root;
- classifies executable/sensitive files as blocked;
- stores CSV and JSON under private Laravel storage;
- stores a root fingerprint instead of the absolute root.

Use `--no-checksum` only for a preliminary large-tree scan. The final launch evidence must include checksums.

Review every `manual_review` and `blocked_executable_or_sensitive` row. A blocked row must not be made executable or directly public during cutover.

## 3. Reconcile Database References

Set `OLD_PUBLIC_ROOT` to the verified root, clear cached configuration, and run:

```bash
php artisan config:clear
php artisan legacy-import:file-inventory --checksum
```

The command is a dry-run unless `--write` is supplied. If no configured root is readable, it reports references as `unverified`, not `missing`.

After reviewing the dry-run, persist reliable production/staging evidence:

```bash
php artisan legacy-import:file-inventory --checksum --write
php artisan continuity:export-file-inventory --format=csv --disk=local --dir=legacy-import-exports/file-continuity-inventory
```

Do not use old local `X:/` mount results as launch evidence.

## 4. Choose a Deployment Layout

Preferred layout: point the domain document root to Laravel `public/`, then place or link only approved static directories beneath that root at their original URL paths.

Example logical result:

```text
/path/to/laravel/public/index.php
/path/to/laravel/public/downloads/files/...
/path/to/laravel/public/images/...
/path/to/laravel/public/med/images/...
/path/to/laravel/public/pdf/...
```

Use one of these methods after staging proof:

1. Keep approved static directories physically beneath the final document root.
2. Create exact directory symlinks beneath Laravel `public/` when cPanel permits and Apache/LiteSpeed follows them.
3. Request exact Apache aliases from the host when neither placement nor symlinks are possible.

Do not alias the entire old document root. Do not link old controllers, admin code, backups, SQL dumps, configuration files, or private submission directories.

Do not replace an existing Laravel directory such as `public/images` with a legacy symlink. Merge only evidence-approved files. Keep an existing target when a differing collision is unresolved; identical collisions need no copy.

Laravel's `public/.htaccess` already serves existing files before the front controller and denies executable/sensitive extensions and dotfiles inside approved legacy static trees. Keep `Options -Indexes` enabled.

## 5. Staging Verification

For representative files from every populated path family, verify:

```bash
curl -I "https://staging.example/downloads/files/example.pdf"
curl -I -H "Range: bytes=0-99" "https://staging.example/downloads/files/example.pdf"
curl -I "https://staging.example/images/example.jpg"
curl -I "https://staging.example/med/images/example.jpg"
```

Required outcomes:

- Existing approved files return `200` directly from Apache/LiteSpeed.
- PDF range requests return `206` where supported.
- `Content-Type` and `Content-Length` are correct.
- URL-encoded spaces and non-ASCII filenames resolve.
- Directory requests do not list contents.
- `.php`, `.phtml`, `.phar`, dotfiles, backups, SQL, and configuration files return `404` and never execute.
- Missing file paths reach the normal Laravel `404` and unresolved-request log.
- Moved files redirect once to an approved canonical media URL.

Test case-sensitive paths on the Linux host. A local Windows test cannot prove Linux filename behavior.

## 6. Cutover and Rollback

Before cutover:

- retain the verified static-tree backup and checksum manifest;
- record every directory placement, link, or alias;
- verify the final domain document root;
- verify file owner/group and read-only web access;
- retain the prior document-root configuration for rollback.

After cutover, repeat the staging matrix on the production domain. Monitor unresolved requests for file extensions and restore only files supported by the checksum evidence. Never restore retired executable code to fix a static-file `404`.
