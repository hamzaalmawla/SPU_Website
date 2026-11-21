# Graduates and Top Students Import: Plan

Status: built on `dev` (2026-10-20). Where the build differs from the plan below, "As built" says so.

## As built

- **Permissions**: no new permission component. Importing and undoing need publish on the faculty page itself (`AdminAccessServiceInterface::canManageTarget(..., 'publish')` on `facilities.<faculty>.alumni` or `.valedictorians`), the same check as the page's Publish button, so faculty and page limits apply. By default editors and super admins can import; faculty editors (view/update only) cannot. No data migration was needed.
- **Where the code is**: reader and parsers in `app/Support/StudentLists/` (`DocxTableReader`, `DecisionListParser`, `DepartmentMatcher`), service `app/Services/Career/StudentListImportService.php` behind `StudentListImportServiceInterface`, admin actions in `app/Filament/Concerns/ImportsStudentLists.php` (used by `ManagesFacultyHomepage`), views in `resources/views/filament/student-lists/`, labels in `lang/*/student_lists.php`, enums `GraduationGrade` and `AcademicSemester`.
- **Admin flow**: on a faculty's Alumni or Honor List page, "Import from decision (.docx)" uploads the file and opens "Review the import" (the preview table, academic year, semester, one department select per specialization). "Import history" lists past imports with Undo on the newest active one; undo is refused for an older import while a newer one is active.
- **Department memory**: `student_list_imports.department_map` keeps the departments the editor confirmed; the next import of that faculty reuses them before matching.
- **Published lists**: when an editor has published the page's own item list in the CMS, `FacultyPageService` adds imported students that are not in it, so imports always show.
- **Public**: alumni cards show the semester and the grade (AR/EN); honor cards show the semester, academic year, study year and department; the semester filter now filters (it was shown but had no effect) and the alumni page has one too; imported rows are ordered as the decision lists them (study year then rank; graduates by GPA). The `/alumni` directory shows the grade.
- **Uploads** are kept on the private disk under `student-list-imports/` only between upload and confirm; files older than a day are deleted when the next preview runs.
- **The sample decisions** of 2026-10-20 give 84 graduates and 25 top students (not 26). They are not in the repository; tests build files with the same layout and invented names (`tests/Support/DecisionDocxFactory.php`).
- **Tests**: `tests/Unit/StudentListParserTest.php`, `tests/Feature/StudentListImportTest.php`.

## Goal

Each semester every faculty council issues two decisions as Word files:

- **Graduates** (قرار ... خريجين), e.g. decision 5/14, Semester 2 2025/2026, Faculty of AI Engineering: 84 students.
- **Top students** (قرار ... اوائل), e.g. decision 12/14, same semester: 25 students in 13 blocks.

An admin uploads the file on the faculty's Alumni or Honor List page, checks a preview, confirms, and the students appear on the public faculty pages and the alumni directory. The format is the same every semester, so the parser targets it.

## Decisions (2026-10-20)

1. Upload accepts **.docx only**. PDF is not supported: scanned PDFs need OCR and misread names and numbers.
2. After the admin confirms the preview, the students are **live at once**. The preview is the review; every import is audited and can be undone in one step.
3. The public Alumni card shows the **grade in Arabic and English** (امتياز / Excellent ...). GPA is stored for admins, not shown.
4. Honor rows record the **semester**: academic year `2025/2026`, semester `second`, study year `5` and the department are separate fields.

## Source format

### Graduates file

One table. A two-row header (the GPA header spans "points" and "percent"), then one row per student:

| Column header | Example | Stored as |
| --- | --- | --- |
| `#` | 1 | ignored |
| `الرقم الجامعي` | 4200001 | `alumni.student_identifier` |
| `اسم الطالب` | سامر خالد الحلبي | `alumni_translations.full_name` (ar) |
| `الكلية` | كلية الهندسة | ignored: the faculty comes from the admin page. The file uses the faculty's older names. |
| `الاختصاص` | الهندسة المعلوماتية/هندسة أمن النظم والشبكات الحاسوبية | `alumni.department_id` via department matching |
| `المعدل النقطي` | 3.55 | `alumni.gpa_points` (new) |
| `المعدل المئوي%` | 91 | `alumni.gpa_percent` (new) |
| `التقدير` | امتياز | `alumni.grade` (new key: `excellent`, `very_good`, `good`, `pass`) |
| `الفصل الدراسي الذي أنهى فيه الطالب مقرراته` | الفصل الثاني 2025/2026 | `alumni.semester` = `second` (new), `alumni.academic_year` = `2025/2026` (new), `graduation_year` = 2026 |

Columns are found by header text, not position, so a reordered column still reads.

### Top students file

One table split into blocks. Each block has a title row spanning all columns, a header row (`ت`, `الرقم الجامعي`, `اسم الطالب`, `المعدل التنافسي`) and the ranked students:

- `السنة الأولى` / `الثانية` / `الثالثة` → study year 1–3, no department.
- `السنة الرابعة / هندسة البرمجيات ونظم المعلومات` → study year 4 plus the department after the slash.
- Empty spacer rows are skipped.

| Field | Stored as |
| --- | --- |
| Rank `ت` | `honor_students.sort_order` |
| University ID | `honor_students.student_identifier` |
| Name | `honor_student_translations.full_name` (ar) |
| Competitive GPA | `honor_students.gpa` |
| Block year | `honor_students.study_year` (new) |
| Block department | `honor_students.department_id` |
| Semester and academic year (from the decision text, editable in the preview) | `honor_students.semester` (new), `honor_students.academic_year` = `2025/2026` |

The paragraph text gives the semester and year (`الفصل الثاني من العام الدراسي 2025/2026`). The preview shows them, and the admin can correct them before confirming.

## Department matching

The file says `هندسة البرمجيات ونظم المعلومات`; the department is `قسم هندسة البرمجيات ونظم المعلومات الذكية`. The importer normalises both sides with `SearchTextNormalizer`, drops the `الهندسة المعلوماتية/` prefix and words like `قسم`, `الذكية` and `و`, treats `الصنعي` and `الاصطناعي` as the same word, and picks the department with the best word overlap. When no department matches well enough, the preview asks the admin to choose one (or none) for that specialization. The choice applies to every row with that specialization.

## Re-imports and duplicates

- A student is matched on `faculty_id + student_identifier` (alumni) or `faculty_id + student_identifier + academic_year + semester` (honor students). Uploading the same file twice updates the same rows; nothing is duplicated.
- Existing legacy rows have no university ID. If a new graduate's name matches an existing alumni name in the same faculty (`PersonName::same()`), the preview flags it so the admin can link it instead of adding a second row.
- The preview lists rows it could not read (missing ID, missing name, unreadable GPA) and they are skipped.

## Undo

Each confirmed import is a `student_list_imports` row (faculty, kind, file name, decision number, academic year, semester, row counts, imported by, timestamps). Rows it creates or updates carry `student_list_import_id`. "Undo import" deletes the rows it created and restores the previous values of the rows it updated, which are kept in the import's `previous_values` JSON.

## Schema (one structural migration, no data rows)

- `student_list_imports`: `id`, `faculty_id`, `kind` (`alumni` | `honor_students`), `original_filename`, `decision_number`, `academic_year`, `semester`, `created_count`, `updated_count`, `skipped_count`, `previous_values` (JSON), `imported_by`, `undone_at`, `undone_by`, timestamps.
- `alumni`: add `gpa_points` (decimal 3,2), `gpa_percent` (decimal 5,2), `grade` (string 20), `academic_year` (string 20), `semester` (string 10), `student_list_import_id` (FK, null on delete). Index on `faculty_id, student_identifier`.
- `honor_students`: add `study_year` (tinyint), `semester` (string 10), `student_list_import_id` (FK). Index on `faculty_id, student_identifier`.

## Code layout

- `app/Contracts/Career/StudentListImportServiceInterface.php`: `preview(int $facultyId, string $kind, string $path, string $filename): StudentListImportPreviewDTO`, `import(..., array $departmentChoices, array $overrides, int $userId): StudentListImportResultDTO`, `undo(int $importId, int $userId): bool`, `history(int $facultyId, string $kind)`.
- `app/Services/Career/StudentListImportService.php` (matching, writes, audit, cache flush) and `app/Services/Career/DecisionDocxReader.php` (reads the docx tables with `ZipArchive` + `DOMDocument`; no new Composer package).
- `app/DTOs/Career/`: preview, row, result DTOs (`final readonly`).
- Filament: an "Import from decision (.docx)" header action on the faculty admin page when the active target is `alumni` or `valedictorians`, as a wizard: upload → preview table with warnings and department choices → confirm. An import history list with Undo.
- Public: `FacultyPageService::alumniItems()` adds the grade (AR/EN) and semester; `honorItems()` adds the semester, study year and department. The existing semester filter (`first` / `second`) starts filtering; today it is shown but has no effect. `AlumniDirectoryService` shows the grade too.
- Permissions: a new `student_lists` component in `PermissionCatalog` (`view`, `import`, `undo`) with AR/EN labels, granted by migration to the Editor and Faculty Editor roles. A user limited to faculties imports only for their own faculties.
- Translations: `lang/ar|en/student_list_import.php`; grade labels in both languages.

## Published CMS items

The faculty Alumni and Honor List pages read the database rows unless the target's published CMS payload has its own `items` list (an editor saved the list in the subpage editor). On 2026-10-20, v2 showed the 153 database honor rows for the AI faculty, so no override is active there. If a target has an `items` override when an import runs, the import also adds the new records to that list through `CmsWorkflowService` (draft, then publish), so they still appear.

## Security

- Upload: `.docx` extension and ZIP MIME check, 5 MB maximum, stored on the private disk and deleted after the preview is built or the import finishes. `word/document.xml` is parsed with external entities disabled (`LIBXML_NONET`, no DTD loading). The archive's uncompressed size is checked before reading to stop zip bombs.
- University IDs and GPAs are stored for admins only. The public pages show the name, department, year, semester, the grade (alumni) and the competitive GPA (honor list, as today).
- Every import and undo is written to the audit log.

## Tests

- Reader: both sample files (stored as test fixtures with the names replaced) give 84 graduates and 26 top students with the right fields, the two-row header, the year blocks, the spacer row, and a moved column.
- Department matching for every specialization in both files.
- Import: creates rows, a second import of the same file updates instead of duplicating, Undo restores, a faculty-limited user cannot import for another faculty, a user without `student_lists.import` cannot see the action.
- Public: the grade shows in AR and EN, the semester filter works, legacy rows without the new fields still render.
- `ArchitectureGuardTest` stays green.
