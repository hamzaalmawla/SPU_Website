# Faculty Project Migration

## Source

The authoritative source is the production cPanel dump generated on 2026-08-27.
It is intentionally excluded from Git because it is 207 MB and contains data
outside the public project records.

Legacy project parents are `jx_categories` rows using services `24`, `34`,
`44`, `54`, `64`, and `74`. Related PDFs and gallery images are `jx_items`
rows linked through `category_id`.

## Imported Inventory

| Faculty | Service | Total | Enabled | Retained disabled |
|---|---:|---:|---:|---:|
| Medicine | 24 | 91 | 82 | 9 |
| Dentistry | 34 | 8 | 8 | 0 |
| Pharmacy | 44 | 123 | 104 | 19 |
| Artificial Intelligence | 54 | 98 | 92 | 6 |
| Petroleum Engineering | 64 | 60 | 53 | 7 |
| Business Administration | 74 | 112 | 99 | 13 |
| **Total** | | **492** | **438** | **54** |

Construction Engineering has no project service in this dump.

English placeholder titles are replaced with the Arabic title. Meaningful
English titles are retained. Duplicate source records are preserved by legacy
ID because identically titled records can contain different project files.

Projects are ordered by descending legacy source ID, which is the reliable
creation sequence in this source and matches newest-to-oldest presentation.
Imported paths are retained as `downloads/files/...` and resolved through the
same legacy media bridge used by news, research, alumni, and staff. Production
serves them through the existing `/public/downloads` mount; the binary files are
not duplicated into the application repository or main media library.

Contributor names are populated only where the parent title/body explicitly
identifies a student or supervisor. The current dump provides reliable team
evidence for 12 projects and supervisor evidence for one project. Remaining
names may exist only inside PDFs and require a separate PDF/OCR review; the
importer does not guess them.

The verified import retained 529 reachable source references. Twenty-three legacy
file paths failed verification and are intentionally not rendered as download
controls. Projects and their other valid media remain available.

## Local Verification

```bash
php artisan legacy-import:faculty-projects "spuedu_db (1).sql" --json
php artisan migrate --path=database/migrations/2026_09_19_000001_enhance_faculty_student_projects_for_legacy_import.php --force
php artisan legacy-import:faculty-projects "spuedu_db (1).sql" --write --approve=faculty-projects-20260827 --enable-visible --verify-media --json
php artisan sitemap:generate
```

The import is idempotent. It updates records using `legacy_source_id`, retains
hidden source records with `is_enabled = false`, and refreshes both localized
translations on every approved run.

## Production Deployment

1. Deploy the application code and run `php artisan migrate --force`.
2. Confirm `php artisan storage:link` has been run and the public disk is writable.
3. Upload the dump outside the public web root.
4. Run the dry-run command and confirm `492` scanned/importable projects.
5. Run the approved write command with `--enable-visible --verify-media`.
6. Confirm `438` enabled and `54` disabled project records.
7. Confirm the command reports `0` unexpected skipped projects.
8. Regenerate the sitemap and caches.
9. Remove the uploaded production dump after verification.

Do not publish the 54 hidden source records without a separate editorial
decision. Do not create controls for the 23 unavailable files unless replacement
files are supplied.
