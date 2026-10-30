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
- Course teachers: `courses[].teachers.{theory,practical} = {memberIds: [], enabled}` (faculty profile ids, never names; any number per part). Plans saved earlier hold one `memberId`, still read. Edited on the board's course panel (add/remove chips); the course page reads each name and photo from the public profile (`FacultyPageService::courseTeachers`) and skips a switched-off part. The older `instructor` field shows only when no teachers are set.

## Faculty projects
- Supervisors are faculty profiles: `facilities.<faculty>.projects` items carry `supervisorMemberIds` (picked per language in the admin; a language with none uses the other's). The project list and detail pages link each to its profile (`FacultyPageService::withProjectSupervisors`). The imported free-text `supervisor` shows only while no profile is picked, and its admin field only while it has text.

## Faculty final exams
- Every faculty has a `final-exams` page (`facilities.<faculty>.final-exams`, schedules with files or timetable rows). Live, only Medicine, AI, Petroleum and Business have a page row and card for it (from the old site); the others get it by default (`FacultyPageService::hasDefaultSubpage`), while a disabled row or hidden card keeps it off. Its card on the faculty and overview pages appears only once a schedule with a file or a row is published in that locale (`FacultyPageService::hasPublishedExamSchedule`); the page URL itself always opens, with an empty-state line until then.

## Faculty subpage intro body
- Every faculty subpage editor has Page intro › Body. It shows below the hero through `partials/subpage-body` (the shared hero, `study-plan` and `training`; `research` inline); the overview, `info-subpage` (clinical cases, final exams) and the master's program place it in their own content. Test: `FacultySubpageBodyTest`.

## Faculty master's program
- AI only (`config/faculty_subpages.php` restricts `master-program`): page `/{locale}/faculties/artificial-intelligence/master-program`, target `facilities.<faculty>.master-program`. Like final exams and council it is a default subpage in code (no page row; a disabled row or hidden card keeps it off), and its card comes first in the faculty's highlights (and the overview's pathways) unless an editor's card for it places it.
- Until a version is published, the page shows the reviewed curriculum in `database/seeders/Data/master_programs/<faculty>.json` (rebuilt by `build_artificial_intelligence.py` next to it); the admin editor starts from it too. `FacultyMasterProgramServiceInterface` reads it, builds the page (term and program totals are computed, prerequisites are course codes) and validates on publish (unique codes, known prerequisites, the same courses in both languages).
- Public view `partials/master-program.blade.php`; the semester board's lines and course dialogs are `resources/js/alpine/masterProgram.js` (reuses `planEdgePaths`).

## Search
- `App\Support\LenientSearch` is the matcher for list searches (faculty projects, student lists, alumni directory, research publications/projects/researchers): every word in any order, Arabic folding, no article or punctuation, near spellings against names and titles. Callers pass the item's text in both languages, so a search works on either site.
- Site search: each `search_documents` row also holds the other language's text and `alt_title_normalized` (rebuilt by `search:index` on every deploy); below 10 exact hits, near spellings of titles are added after them.
- `CachePublicPages` keys each route on its own query parameters; a list search must list `q` there or every search gets the first cached page.
- `FacultyStudyPlanEditorService` workspace methods (`buildWorkspace`, `mergeWorkspace`) belong to the removed per-term form and are unused by the app; `validationErrors` is still the validator.
- Tests are slow (Livewire pages take 5-10s each); run classes separately with `--filter`, and run long ones in the background.

## Faculty departments and specializations
- The departments page (`facilities.<faculty>.departments`) lists academic departments only (`items[]`, every faculty): `type` specialization/section, `headMemberId` a profile id of this faculty's staff (checked by `FacultyLeadershipServiceInterface::departmentsValidationErrors`) with an optional per-locale `headTitle` (shown on this page and the council instead of the default) and `headOnCouncil` (default on, same in both languages), and `studyPlanDepartmentId` (a study plan program, `plan.departments[].id`; the study plan's "departments" are programs), required for a specialization and empty for a section. A section shows no Study Plan button. An item saved before `type` existed is a specialization when it links to a program (explicitly or by exact name). There is no specializations list on the page, and `kind` is gone.
- Business and AI departments carry `specializationId` (their study plan program) in `faculty_structure.json`; Business MIS has no department. Staff profiles keep `department_id` and may add an optional `specialization_id` (a program id, validated against the faculty's plan). Plan and as-built notes: `Docs/FACULTY_DEPARTMENTS_SPECIALIZATIONS_PLAN.md`.

## Faculty leadership and council
- Page `/{locale}/faculties/{faculty}/council`, target `facilities.<faculty>.council`, the same in both locales: `positions[] = {id, positionSlug, memberId}` and `includeDepartmentHeads`. Every row is a position from the shared list and a published profile of the faculty's own staff; exactly one dean. The admin edits it in one section below the language tabs (`council_positions`: position and profile, both required). Pages saved before the list (`type`, `title`, `onCouncil`, `councilMembers`) still read, and load in the editor with their position to pick.
- Positions list: `FacultyPositionServiceInterface` (`faculty_positions` + translations). Dean, vice dean and head of department are built in (code); a row with their slug only renames them. Editors add or edit positions from the council editor's dropdown (create/edit option); each has a placement, `leadership` (cards at the top) or `council` (members list); the dean always leads. Head of department is never picked: heads come from the departments page (`headTitle`, `headOnCouncil`).
- `FacultyLeadershipServiceInterface` builds the page (`council()`: dean, leadership cards, council member cards with every role, department head cards), validates both targets and, on publish (`publishTarget`), makes the published dean the faculty's dean row on About › Leadership (updating it, or adding one). Unpublished, the page shows the About dean and the heads. Like final exams, a faculty with no `council` page row or card has the page and card in code (`hasDefaultSubpage`); a disabled row or hidden card keeps it off. The faculty dean block reads `deanProfileSlug()`; PersonResource locks a council-managed dean's profile.
- Profiles and the members list show `memberPositions()`/`positionsOfMember()` (published council positions and the departments a person heads) instead of the typed `faculty_member_translations.position`, which stays as the academic rank. `faculty:match-positions` (dry run in every deploy log, write with `faculty-positions.approved`) moves typed positions onto council drafts. View `partials/council.blade.php`, styles `resources/css/frontend/faculty-council.css`. Plan and as-built notes: `Docs/FACULTY_LEADERSHIP_COUNCIL_PLAN.md`.

## Public forms
- Forms are built in the admin (Contact → Forms, `FormDefinitionResource`) and stored in `form_definitions` (fields in `schema_json`, optionally in steps). `FormDefinitionServiceInterface` is the only reader; the six original forms are inserted by the migration from `database/seeders/Data/forms/built-in-forms.json`.
- Pages pick a form with `App\Filament\Support\FormPicker` (event `formId`, admissions/jobs `applicationFormId`, suggestions `form.formId`). The page prints the schema via `public.forms.dynamic-form` (`formKey`) and the Alpine store reads it; there is no form list in the JS.
- A form in use (a page picks it, or it has submissions) can't be deleted, only disabled. Submissions go to the inbox set on the form.

## Staff intake links
- Per-faculty link (`/{locale}/staff-intake/{token}`) where academic staff find their profile by name and send corrections or a new profile. Plan and as-built notes: `Docs/STAFF_INTAKE_LINK_PLAN.md`.
- Nothing a teacher sends changes a profile: it is a `staff_intake_requests` row until a reviewer approves it, and approval goes through the About-entity CMS draft/publish workflow. Every open, claim and submit is logged in `staff_intake_access_logs`. Admin access uses the `staff_intake_links.*` and `staff_intake_requests.*` permissions.
- Name suggestions use `App\Support\PersonName::matchRank()`; keep `tests/Unit/PersonNameMatchingTest.php` green when touching name matching (the merge and research services share it).

## One profile per person (faculty, leadership, research)
- The faculty profile (`faculty_members`) is the one record of a person's name, photo, bio, education, contact and links, in AR and EN. A leadership row (`persons`: dean, rector, ...) points at it with `persons.faculty_member_id` and adds only the role, quote, category, order and visibility. A leader who belongs to no faculty has a profile with no faculty (only editors without a faculty limit can save one).
- Every page reads a leader's identity through `PersonIdentityServiceInterface` (`publicIdentities`, `profileMemberIds`, `personIdsForMember`). It still honours the older `faculty_members.person_id` link. Until `people:unify` has run, a field the profile leaves empty shows the row's old copy.
- The profile address is `/about/profile/{member-slug}`. A leadership slug, `?source=`, `/profile/person/...` and staff `-rN` researcher pages 301 to it (`ProfilePageService`). The sitemap and search list the profile, not the row.
- Research: a register row with `faculty_member_id` shows the profile's name (`ResearchPageService::researcherName`). Only external co-authors use `researchers.full_name`. The name-based staff suggestions (`ResearcherAdminServiceInterface::staffSuggestions`, the "Suggested" filter, `researchers:link-staff`) are how a researcher joins a profile, and they stay.
- Admin: the leadership editor (PersonResource) picks the profile and hides name, photo, bio, contact and education. Publishing it writes only role fields and leaves the row's old columns as they were (`AboutEntityCmsService::publishLeadershipRole`). There is no link or unlink action.
- `people:unify` (`PeopleUnifyServiceInterface`) ties existing rows to their profile. It never overwrites faculty-member data. Its dry run is in every deploy log, and the write runs only with `people-unify.approved` (see `Docs/DEPLOYING.md`). `people:merge` only merges duplicates now, including a leader's no-faculty profile into their faculty profile (the leadership row follows); its dry run is in every deploy log and the write needs `people-merge.approved`. Staff intake treats a no-faculty profile as the link faculty's own, so approving updates it. Plan: `Docs/PEOPLE_SINGLE_PROFILE_PLAN.md`.

## Graduates and top students import
- Each semester faculties send council decisions (.docx) listing graduates and top students. Editors import them from the faculty's Alumni / Honor List admin page ("Import from decision"): upload, review, confirm, and the rows are live. Plan and as-built notes: `Docs/STUDENT_LIST_IMPORT_PLAN.md`.
- `StudentListImportServiceInterface` does all reading and writing; the parsing is in `app/Support/StudentLists/`. Students are matched on university ID (`student_identifier`), so a re-import updates. Every import is a `student_list_imports` row that can be undone, newest first.
- Access is publish on that faculty page (`canManageTarget(..., 'publish')`), not a separate permission.
- Real decision files name real students: never commit them. Tests build look-alike files with `tests/Support/DecisionDocxFactory.php`.

## Events, announcements and news
- All are articles (`NewsArticleResource`). Events and Society's Events carry event details (`news_article_events`: sessions, speakers, registration) edited in the "Event details" tab; announcements can carry a related link button. Plan and as-built notes: `Docs/EVENTS_UNIFICATION_PLAN.md`.
- `NewsArticleEventCmsServiceInterface` normalizes, validates and writes the event part of the draft payload; `NewsArticleEventServiceInterface` is the public side (event DTO, registration state and seats, `.ics`, list and calendar entries, related link).
- Article registrations post through the dynamic form as source `news-articles` with the article ID. The old Events page list (`news.events`) is read alongside until `news:move-calendar-events` moves it.

## Where to look
- `Docs/ARCHITECTURE.md`, `Docs/BACKEND_RULES.md`, `Docs/STYLEGUIDE.md`, `Docs/WORKFLOW.md`: layering and conventions
- `Docs/FRONTEND_IMPORT_TRACKER.md`, `Docs/FRONTEND_ROUTE_PARITY_MATRIX.md`: parity status per route
- `Docs/DEPLOYING.md`, `Docs/LEGACY_IMPORT_RUNBOOK.md`: deploy and legacy content import
- `Docs/SESSION_HANDOFF_2026-08-22.md`: most recent written handoff
- Branch work happens on `dev`; PRs merge into it from `feat/*` and `fix/*` branches
