# Faculty leadership, council and department heads: plan

Status: built (2026-10-26), see "As built" below. Sections 1 to 10 were the proposal; where they differ, "As built" wins. Builds on `FACULTY_DEPARTMENTS_SPECIALIZATIONS_PLAN.md` ("As built") and `PEOPLE_SINGLE_PROFILE_PLAN.md` (one profile per person).

## As built (2026-10-26)

Answers to the open questions (product owner, 2026-10-26): council members are picked from the faculty's staff like the dean, and department heads join automatically; the Specialization / Section type **replaces** `kind`; the dean has **one source**; the council is a **separate page with a navigation card** in every faculty; departments show **no staff list** (only the council page lists people).

- **Departments** (`facilities.<faculty>.departments`):
  - `type`: `specialization` (needs `studyPlanDepartmentId`, shows the Study Plan button) or `section` (no study plan, no button). The badge reads اختصاص / Specialization or قسم / Section.
  - An item saved before `type` existed counts as a specialization when it links to a program, explicitly or by exact name, as the page already showed. The admin form opens it with that type and link, so saving keeps the button.
  - The head picker lists only this faculty's staff (the "Other faculties" group is gone). On readiness the server refuses a head from another faculty, a specialization without a program, a section with one, and a type or head that differs between Arabic and English.
  - Migration `2026_10_26_000001` drops `departments.kind`; `faculty_structure.json` and `faculty:import-structure` no longer carry it.
- **Council page** (`/{locale}/faculties/{faculty}/council`, target `facilities.<faculty>.council`, every faculty):
  - Payload per locale: `positions[] = {id, type: dean|vice_dean|custom, memberId, title, onCouncil}`, `councilMembers[] = {id, memberId, role}`, `includeDepartmentHeads`.
  - Admin: one bilingual "Faculty leadership and council" section under the language tabs. Rows are positions (type, staff member, AR/EN title, on council) and extra members (staff member, AR/EN role), plus the heads toggle. The staff dropdowns list only this faculty's academic staff, and one person may hold several positions. An empty title uses the default (عميد الكلية / Dean, نائب العميد / Vice Dean); a custom position needs one.
  - Readiness: exactly one dean; every person is this faculty's staff with a published profile (a hidden one would drop off the page and leave About › Leadership showing the dean row's old name); ids, types and people match across languages.
  - Public page: "Faculty Leadership" cards (dean, vice deans, other positions), then "Faculty Council Members". There is one row per person with all their roles (for example "Vice Dean for Academic Affairs · Council Secretary · Head, Department of Surgery"). The dean is always on the council and is its chair. A hidden profile is left out.
  - Before the page is published it shows the faculty's dean from About › Leadership and the heads of the published departments page; the editor starts from the same dean.
  - Every faculty has the page and its navigation card (last) without a data migration, which the deploy's data-migration gate would refuse: a faculty with no `council` page row and no card gets it in code (`FacultyPageService::hasDefaultSubpage`, shared with final exams). A disabled row or a hidden card keeps it off. `FacultyModuleSeeder` adds the row and card for fresh installs. It is registered in `CmsTargetRegistry`, `FacultyPageService`, `FacultySubpageCardService`, `SearchIndexService`, the subpage route and every faculty admin page.
- **Dean, one source**:
  - The faculty dean block takes the dean from the published council page; the typed name matching remains only as the fallback.
  - Publishing the council page points the faculty's dean row on About › Leadership at the published dean. That is the public row linked to this faculty's staff, else any dean row of the faculty. When the faculty has none, a published row is added with the role "عميد <faculty>" / "Dean of the <faculty>". Audit action: `faculty.council.dean_synced`.
  - Publishing also clears the About cache.
  - In People (PersonResource) a dean row's profile picker is locked, with a note, while the faculty's council page names the dean.
- **Not built**: a list of other departments per staff member. With no staff list on department pages it would show nowhere; `faculty_members.department_id` stays the main department.
- Code: `FacultyLeadershipServiceInterface` / `FacultyLeadershipService`, `FacultyCouncilDTO`. Tests: `FacultyCouncilTest`, `FacultyDepartmentsSpecializationsTest`.

## 1. Goal

Each faculty has:
- a **Dean** (العميد), exactly one;
- **Vice Deans** (نواب العميد) and any **custom positions** the admin adds (for example "Vice Dean for Academic Affairs", "Faculty Council Secretary");
- a **Faculty Council** (مجلس الكلية);
- **Departments** (الأقسام). Each has one **Head of Department** (رئيس القسم) and is either a **specialization** (it has a study plan) or a **section** (it has no study plan).

Everyone in these roles is one of **this faculty's academic staff profiles** (`faculty_members`), picked from a dropdown, never typed. Staff have no login.

## 2. Decisions (from the product owner, 2026-10-26)

| # | Decision |
|---|---|
| 1 | The scope now includes the Faculties module. |
| 2 | English terms: Faculty, Department, Head of Department. |
| 3 | Admins can add custom positions besides Dean and Vice Dean. |
| 4 | Council members are picked like the dean: from this faculty's staff profiles. |
| 5 | No department council. A department has only a head. |
| 6 | A staff member can teach in more than one department of their faculty. |
| 7 | Any of the faculty's academic staff can be a head. One person can hold several positions (for example Vice Dean and Head of Department). |
| 8 | The head is optional when a department is created. |
| 9 | Only the current holder is stored, no history. |
| 10 | Staff and deans have no login. |
| 11 | Only academic staff can hold positions or sit on the council. |
| 12 | Department type: `specialization` (has a study plan) or `section` (no study plan). |

## 3. What exists today

| Piece | Where | Gap |
|---|---|---|
| Staff profiles | `faculty_members` (`faculty_id`, `department_id`, `specialization_id`) | Only one department per person. |
| Departments | `departments` rows, plus the CMS target `facilities.<faculty>.departments` (`items[]`: `slug`, `title`, `kind`, `headMemberId`, `studyPlanDepartmentId`, ...) | The head picker also lists **other faculties'** staff (`ProfileAdminService::teacherOptions`, group `other`). Nothing on the server checks that the head belongs to the faculty. The study plan link is optional on every department, and no type says which departments must have one. |
| Dean | Overview target `dean.{nameAr,nameEn,roleAr,roleEn,image,messageAr,messageEn}`, **typed text**. `FacultyPageService::deanProfileFor` matches the typed name to a `persons` row filed as `dean`. | No dropdown. Matching by name breaks when the name changes. |
| Vice deans, custom positions | None | Missing. |
| Faculty council | `councils` / `council_members` tables exist (university level, legacy import) | No council per faculty and no admin for one. |
| University leadership | `persons` (`category` dean / rector / ...), `person_appointments` | Used by About › Leadership. Out of scope here except the dean link (section 7). |

## 4. Data model

### 4.1 Positions and council: a new CMS target per faculty
To get draft, preview, publish, audit and cache through `CmsWorkflowService` as the departments page does, add a target **`facilities.<faculty>.leadership`** with a matching public subpage **`/{locale}/faculties/{faculty}/council`** (slug `council`, added to `FacultyPageService::SUBPAGE_SLUGS` and as a nav card).

Payload (`translations.<locale>`; the ids are shared by both locales the way departments pair items):

```jsonc
{
  "title": "...", "summary": "...", "heroImage": "...",
  "positions": [
    { "id": "pos-dean", "type": "dean",      "memberId": 123, "title": "العميد",               "onCouncil": true,  "councilRole": "chair" },
    { "id": "pos-x1",   "type": "vice_dean", "memberId": 456, "title": "نائب العميد للشؤون العلمية", "onCouncil": true },
    { "id": "pos-x2",   "type": "custom",    "memberId": 456, "title": "أمين سر مجلس الكلية",    "onCouncil": true,  "councilRole": "secretary" }
  ],
  "councilExtraMembers": [
    { "id": "cm-1", "memberId": 789, "role": "عضو" }
  ],
  "includeDepartmentHeads": true
}
```

- `type`: `dean` (exactly one, required before publishing), `vice_dean`, `custom`. Only `custom` needs a typed title; `dean` and `vice_dean` have a default title per locale that the admin can override.
- `memberId`: a `faculty_members` id **whose `faculty_id` is this faculty**. The same id can appear in several positions.
- **Council** = positions with `onCouncil` + **every department head** (from the departments target, when `includeDepartmentHeads`) + `councilExtraMembers`. A person listed twice appears once, with all their roles (for example "Vice Dean · Head of the Surgery Department"). The dean is the chair unless another `councilRole` is set.
- Current holder only: changing a position replaces `memberId`. The audit log keeps the old value.

Why not new tables: departments and heads already live in a CMS payload keyed by profile id. Keeping leadership in the same kind of payload means one workflow (draft, preview, schedule, publish), one cache key and no second source for heads.

### 4.2 Department type
On each departments target item, add **`type`: `specialization` | `section`** (default `section`).
- `specialization`: `studyPlanDepartmentId` is **required** and must be a program of the faculty's study plan. The page shows the Study Plan button.
- `section`: `studyPlanDepartmentId` is hidden in the form and cleared on save. No Study Plan button.
- One-off migration for existing payloads: an item with `studyPlanDepartmentId` becomes `specialization`, and the rest become `section`.
- The existing `kind` (Basic Sciences / academic / clinical badge) stays as it is unless you decide otherwise (question 2).

### 4.3 Staff in several departments
- New pivot table **`department_faculty_member`** (`department_id`, `faculty_member_id`, unique pair, cascade on delete).
- `faculty_members.department_id` stays as the **main** department, so the staff intake link, filters and existing pages don't change. The pivot holds the **other** departments.
- Rule: every department in the pivot belongs to the profile's faculty.

## 5. Admin (faculty workspace)

- **New "Leadership & Council" tab** (target `facilities.<faculty>.leadership`):
  - Positions repeater: Type (Dean / Vice Dean / Custom), Staff member (**dropdown of this faculty's academic staff only**, searchable), Title AR/EN, On council, Council role. Each row can be reordered.
  - Council: an "Include department heads automatically" toggle, an extra members repeater (staff dropdown from this faculty, role AR/EN), and a read-only preview of the final council list.
- **Departments tab**:
  - The head picker lists **only this faculty's staff** (the "Other faculties" group is removed). Optional.
  - New Type select (Specialization / Section). The Study Plan select shows only for Specialization and is required there.
- **Staff profile admin** (`FacultyMemberResource`): new "Other departments" multi-select listing the profile's faculty's departments, beside the main department.

## 6. Services and rules (service layer, not the form)

- New `App\Contracts\Faculty\FacultyLeadershipServiceInterface` with a service and DTOs:
  - `staffOptions(string $facultyScope, int $userId): array<int, string>`: this faculty's staff only. It replaces `teacherOptions(...)['faculty']` for heads, positions and council.
  - `leadership(Faculty, locale): FacultyLeadershipDTO` (dean, positions, council members with merged roles). Public data only: a hidden profile is left out.
  - `validateLeadershipDraft(...)` and `validateDepartmentsDraft(...)`, called from the faculty draft save and publish:
    - every `memberId` / `headMemberId` belongs to this faculty;
    - exactly one `dean` on publish;
    - `specialization` needs a valid study plan program, and `section` has none;
    - department slugs are unique.
- `FacultyPageService`:
  - The overview dean block takes the dean from the leadership target (name, photo and link come from the profile). The dean **message** stays on the overview target.
  - The typed `dean.name*` / `image` fields become a fallback until a dean is published.
  - New `council` subpage payload.
- Moving a staff member to another faculty while they hold a position or head a department is **refused** with a message naming the role, so a published page never points at someone outside the faculty.
- Publishing leadership or departments clears the faculty page caches (overview, departments, council), as the departments target does today, and writes audit logs (`faculty.leadership.published`, `faculty.department_head.changed`).

## 7. Existing data

- **Dean:** for each faculty, preselect the `persons` row filed as `dean` for that faculty (through `persons.faculty_member_id`) as the leadership draft's dean. It is left as a **draft** for an editor to review and publish.
- **Heads from another faculty** in published departments pages: list them in a dry-run report command (`faculty:audit-leadership`). An editor replaces them before the page can be published again. Nothing is overwritten automatically.
- **Department type:** the migration in 4.2.

## 8. Tests

- Unit: the leadership draft validator (another faculty's staff is rejected, two deans are rejected, a publish with no dean is rejected, the same person can hold two positions); the department type rules; the council merge (one entry per person, with all roles).
- Feature: the head, position and council dropdowns show only this faculty's staff; the council page lists the dean, vice deans, heads and extra members in AR and EN; a draft is never shown publicly; the dean block follows a profile name change; moving a head to another faculty is refused; the pivot rejects a department from another faculty.
- `ArchitectureGuardTest` stays green.

## 9. Build order

1. Department type, head picker limited to the faculty, and server-side head validation (small, and immediately useful).
2. Pivot for other departments, plus the staff admin field.
3. Leadership target: validator, admin tab, public council page, and the dean block reading from it.
4. Data steps (section 7) and docs (`SPU_CMS_User_Guide.md`, `ARCHITECTURE.md` scope).

## 10. Open questions

1. **Dean "same logic":** is it right that council members are **picked from the faculty's staff dropdown**, as the dean will be, and that department heads join the council **automatically**?
2. **Department type vs kind:** keep the Basic Sciences / academic / clinical badge (`kind`) next to the new Specialization / Section type, or replace `kind` with the type?
3. **About › Leadership:** should a dean picked here also become the dean shown on About › Leadership (one source), or should About stay managed separately in People for now?
4. **Council page:** a separate faculty page (`/faculties/{faculty}/council`, with its own nav card), or a section on the faculty overview page?
5. **Other departments:** should a profile's public page and each department show staff from the "other departments" too, or should they be used only for filtering the staff list?
