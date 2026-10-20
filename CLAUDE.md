# CLAUDE.md

@AGENTS.MD

The imported `AGENTS.MD` is the authoritative project rulebook (scope, architecture, multilingual, roles, definition of done). The notes below only add what it doesn't cover.

## Commands
- Tests: `php artisan test` (SQLite, so keep SQL portable). Quick: `php artisan test --exclude-group=property`. Layering: `php artisan test --filter=ArchitectureGuardTest`
- Static analysis: `vendor/bin/phpstan` (config `phpstan.neon`, baseline `phpstan-baseline.neon`); style: `vendor/bin/pint`
- Frontend: `npm run build`, `npm test`; dev loop: `composer dev`
- Local: set `CACHE_STORE=array` in `.env` (the `database` driver has no cache tags)

## Environment facts
- Production is MariaDB 10.11 with file cache, database queue and sessions. There is no Redis on the host.
- Deployed at `v2.spu.edu.sy`; not yet the primary domain. Never claim deployment, cutover or sign-off. Status lives in `Docs/CURRENT_REMEDIATION_EXECUTION_CHECKLIST.md`.
- `.env` is gitignored and holds secrets: don't read, print or commit it.
- Approved frontend reference (parity source): `C:\Users\hamza\Spu-Website\Spu-Website`

## Faculty study plans
- A plan is one JSON payload per faculty CMS target `facilities.<faculty>.study_plan` (`translations.ar|en.payload.plan.departments[].terms[].courses[]`), not relational tables.
- Admin editing is the visual board on each faculty admin page: `ManagesStudyPlanBoard` (Livewire actions) -> `FacultyStudyPlanBoardService` (pure payload edits) -> `CmsWorkflowService::saveDraft`. Board and form save independently; the form never carries departments.
- Bulk load from a curriculum: convert the source with a script in `database/seeders/Data/study_plans/`, then `php artisan faculty:import-study-plan <faculty> <file.json> --user=<id>` (dry run by default, `--write` saves an unpublished draft). It skips any plan editors have already saved or published (`--replace-edited` overrides, manual only); `cpanel-deploy.sh` runs it only when `study-plans.approved` is placed on the server (see `Docs/DEPLOYING.md`).
- Public graph: `resources/js/alpine/studyPlan.js` (`planEdgePaths` is shared with the admin board in `resources/js/filament/studyPlanBoard.js`). Layout is measured from the DOM, not computed from constants.
- `FacultyStudyPlanEditorService` workspace methods (`buildWorkspace`, `mergeWorkspace`) belong to the removed per-term form and are unused by the app; `validationErrors` is still the validator.
- Tests are slow (Livewire pages take 5-10s each); run classes separately with `--filter`, and run long ones in the background.

## Public forms
- Forms are built in the admin (Contact → Forms, `FormDefinitionResource`) and stored in `form_definitions` (fields in `schema_json`, optionally in steps). `FormDefinitionServiceInterface` is the only reader; the six original forms are inserted by the migration from `database/seeders/Data/forms/built-in-forms.json`.
- Pages pick a form with `App\Filament\Support\FormPicker` (event `formId`, admissions/jobs `applicationFormId`, suggestions `form.formId`). The page prints the schema via `public.forms.dynamic-form` (`formKey`) and the Alpine store reads it; there is no form list in the JS.
- A form in use (a page picks it, or it has submissions) can't be deleted, only disabled. Submissions go to the inbox set on the form.

## Staff intake links
- Per-faculty link (`/{locale}/staff-intake/{token}`) where academic staff find their profile by name and send corrections or a new profile. Plan and as-built notes: `Docs/STAFF_INTAKE_LINK_PLAN.md`.
- Nothing a teacher sends changes a profile: it is a `staff_intake_requests` row until a reviewer approves it, and approval goes through the About-entity CMS draft/publish workflow. Every open, claim and submit is logged in `staff_intake_access_logs`. Admin access uses the `staff_intake_links.*` and `staff_intake_requests.*` permissions.
- Name suggestions use `App\Support\PersonName::matchRank()`; keep `tests/Unit/PersonNameMatchingTest.php` green when touching name matching (the merge and research services share it).

## Graduates and top students import
- Each semester faculties send council decisions (.docx) listing graduates and top students. Editors import them from the faculty's Alumni / Honor List admin page ("Import from decision"): upload, review, confirm, and the rows are live. Plan and as-built notes: `Docs/STUDENT_LIST_IMPORT_PLAN.md`.
- `StudentListImportServiceInterface` does all reading and writing; the parsing is in `app/Support/StudentLists/`. Students are matched on university ID (`student_identifier`), so a re-import updates. Every import is a `student_list_imports` row that can be undone, newest first.
- Access is publish on that faculty page (`canManageTarget(..., 'publish')`), not a separate permission.
- Real decision files name real students: never commit them. Tests build look-alike files with `tests/Support/DecisionDocxFactory.php`.

## Where to look
- `Docs/ARCHITECTURE.md`, `Docs/BACKEND_RULES.md`, `Docs/STYLEGUIDE.md`, `Docs/WORKFLOW.md`: layering and conventions
- `Docs/FRONTEND_IMPORT_TRACKER.md`, `Docs/FRONTEND_ROUTE_PARITY_MATRIX.md`: parity status per route
- `Docs/DEPLOYING.md`, `Docs/LEGACY_IMPORT_RUNBOOK.md`: deploy and legacy content import
- `Docs/SESSION_HANDOFF_2026-08-22.md`: most recent written handoff
- Branch work happens on `dev`; PRs merge into it from `feat/*` and `fix/*` branches
