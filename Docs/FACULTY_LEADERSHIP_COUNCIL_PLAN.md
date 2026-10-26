# Faculty leadership, council and department heads: plan

Status: proposal (2026-10-26). Builds on `FACULTY_DEPARTMENTS_SPECIALIZATIONS_PLAN.md` ("As built") and `PEOPLE_SINGLE_PROFILE_PLAN.md` (one profile per person).

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
