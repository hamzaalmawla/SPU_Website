# Faculty departments and specializations: plan

Status: the foundation is built (see "As built" below); the CMS manager fills the data. Sections 3 to 6 were the first proposal and are kept for the reasoning; where they differ, "As built" wins.

## As built (2026-10-24)

The page stays `/{locale}/faculties/{faculty}/departments`, with the same CMS target `facilities.<faculty>.departments` (draft, preview, publish, audit and cache as before). One payload, two lists:

- **Departments** (`translations.<locale>.items[]`, every faculty): `slug`, `title`, `summary`, `code`, plus
  - `kind`: `basic_sciences` | `academic` | `clinical` (default `academic`), shown as a badge;
  - `headMemberId`: a faculty profile id (never a name). The page shows the head's current name, photo and profile link; a hidden profile is left out;
  - `studyPlanDepartmentId` (now labelled "Specialization it teaches"): optional. A department without one gets no Study Plan button when the faculty has several programs. In a single-program faculty it keeps the faculty's plan as its link.
  - `degrees`, `tags`: kept for older rows, under Advanced.
- **Specializations** (`translations.<locale>.specializations[]`): `studyPlanDepartmentId` (required, a program of the published study plan), and optional `title`, `summary`, `degrees`, `tags`. An empty title or summary falls back to the program's name or summary in the plan; credits come from the plan.
  - **Empty list:** a plan with two or more programs (AI, Business) lists every program automatically. A single-program faculty shows no specializations block.
  - Validation: each program must exist in the published plan, appear once, and match in both languages.
- Public rendering: `FacultyStudyPlanLinkServiceInterface::specializationItems` builds the list (with the department that teaches each one), and `FacultyPageService` adds the heads. The view shows the stats, then Specializations (cards, each with its Study Plan link), then Academic Departments.
- Admin: the faculty page's Departments tab has the departments repeater (type, head picker grouped by this faculty or other faculties, code, summary; Advanced holds the specialization link, degree and keywords) and a Specializations repeater.
- `departments.kind` column (migration `2026_10_24_000001`) for the record fallback used before a faculty's departments are first published. That migration marks the old site's Basic Sciences rows (petroleum, construction, dentistry Life Sciences) and adds **AI's Basic Sciences department** (`ai-basic-sciences`, first in order). `faculty_structure.json` carries the same, and `faculty:import-structure` writes `kind`.
- **Business and AI links** (migration `2026_10_24_000002`, and `specializationId` per department in `faculty_structure.json`): each department records the program it teaches, in the departments page payload (`payload_json.departments[] = {slug, studyPlanDepartmentId}`), because the names are too different to match automatically.
  - Business: Human Resources → `hr`, Accounting → `accounting`, Business Administration → `management`, Marketing → `marketing`, Finance & Banking → `finance`. **MIS (`mis`) is a specialization with no department.** It still shows as a specialization, just without a "Taught by" line.
  - AI: each of the 6 departments → `se`, `ai`, `sec`, `ctrl`, `comm`, `med`. Basic Sciences → none.
  - A link an editor already set is kept.
- **Already-published departments pages** keep their published list until an editor adds Basic Sciences, kinds, heads and specializations, then publishes. Nothing is overwritten.

## 1. The problem

Each faculty page today has one "Departments" list (CMS target `facilities.<faculty>.departments`, with the `departments` table as a fallback). Every row gets a "Study Plan" button, so the page treats each department as something a student enrolls in. SPU actually has two different things:

| Concept | Arabic | What it is | Who has it |
|---|---|---|---|
| **Academic department** | القسم العلمي | A teaching unit with a head (رئيس القسم), staff and courses. Examples: Basic Sciences, Anatomy, Drilling Engineering. | Every faculty |
| **Specialization** | الاختصاص / التخصص | A program a student enrolls in and graduates from, with its own study plan. | Only some faculties (AI, Business). The others offer one degree. |

In some faculties the two overlap. In AI, each specialization is taught by the department with the same name, but AI also has a Basic Sciences department that no student graduates from. The old site called the page "الاختصاصات والأقسام" (Specializations and Sections), which already separates the two.

## 2. Research: what SPU actually has

Sources: the old site's "Specializations and Sections" pages (already extracted into `database/seeders/Data/legacy/faculty_structure.json`), the AI faculty council page, the AI study plan regulations PDF, the 2013 Business study plan page, and the approved study plans in `database/seeders/Data/study_plans/`.

### Artificial Intelligence Engineering: specializations + departments
- **Regulations PDF**: the faculty "consists of the following departments" (6, listed below). Credits split into faculty requirements (60 h) and "متطلبات القسم (التخصص)" (department/specialization requirements, 105 h). So each specialization is a department.
- **Faculty council** lists a **Basic Sciences department head (رئيس قسم العلوم الأساسية, د. ياسر نصر)** alongside the six specialization heads.
- **Our data is missing Basic Sciences.** `faculty_structure.json` lists only the 6.

| Department | Specialization (study plan id) |
|---|---|
| قسم العلوم الأساسية (Basic Sciences) | (none: teaches the common years) |
| هندسة البرمجيات ونظم المعلومات الذكية | `se` |
| هندسة الذكاء الاصطناعي وعلوم البيانات | `ai` |
| هندسة نظم أمن المعلومات الذكية | `sec` |
| هندسة الروبوت والنظم الذكية | `ctrl` |
| هندسة الاتصالات الذكية | `comm` |
| الهندسة الطبية الذكية والمعلوماتية الحيوية | `med` |

### Business Administration: specializations + departments, not one-to-one
- Current approved plan (`business-administration.json`), 6 specializations: إدارة الأعمال `management`, إدارة الموارد البشرية `hr`, التسويق `marketing`, المحاسبة `accounting`, التمويل والمصارف `finance`, نظم المعلومات الإدارية `mis`.
- Old site's sections page, 5 departments: الموارد البشرية، المحاسبة، إدارة الأعمال، التسويق، التمويل والمصارف.
- So **MIS has a specialization but no department** in our sources. (The 2013 plan had 6 different departments: marketing & logistics, HR, service operations, financial institutions, accounting & auditing, entrepreneurship. That plan is superseded.)
- I found no source for a Basic Sciences style department in Business. **Needs confirmation from the faculty.**

### Single-degree faculties: departments only
| Faculty | Departments (from the old site) | Basic sciences unit |
|---|---|---|
| Medicine (12) | Anatomy/Embryology/Genetics, Histology/Pathology/Forensic, Biology/Microbiology/Immunology, Chemistry/Molecular Biology, Physiology/Pharmacology, Radiology/Medical Physics, Internal Medicine, Surgery, Pediatrics, Obstetrics/Gynecology, Community Medicine, Medical Education | The first six are the basic medical sciences |
| Dentistry (10) | Life Sciences, Histology/Oral Pathology, Oral Medicine, Operative, Pediatric, Periodontology, Removable Prosth., Fixed Prosth., Orthodontics, Oral & Maxillofacial Surgery | قسم علوم الحياة (Life Sciences) |
| Pharmacy (6) | Pharm. Chemistry & Drug Control (PHCC), Pharmaceutics & Technology (PHPT), Pharmacology & Toxicology (PHPP), Pharmacognosy (PHPC), Biochemistry & Microbiology (PHBM), Analytical & Food Chemistry (PHAC) | spread over PHBM / PHAC |
| Petroleum (4) | **Basic Sciences**, Geology & Exploration, Drilling, Reservoir & Production | قسم العلوم الأساسية |
| Building & Construction (4) | **Basic Sciences**, Materials & Construction Technology, Architecture & Executive Design, Construction Engineering | قسم العلوم الأساسية |

Each of these has one study plan program (`medicine`, `pharmacy`, `petroleum`, `construction`; dentistry has no plan yet), so they show **no specializations list**.

## 3. Proposed model

Use **two lists** and do not store the same thing twice.

### 3.1 Academic departments: the existing `departments` table (relational)
It already exists and is already referenced by `faculty_members.department_id`, `alumni`, `honor_students` and `person_appointments`, so departments stay rows. One migration adds:

| Column | Type | Purpose |
|---|---|---|
| `kind` | string, default `academic` | `basic_sciences` / `academic` / `clinical`. Used for grouping and the "Basic Sciences" badge. |
| `code` | string null | e.g. `PHCC` (today it lives only in the CMS payload) |
| `head_member_id` | FK `faculty_members` null | The head, as a profile id (one profile per person, never a typed name) |
| `specialization_id` | string null | The study plan program id this department teaches (`se`, `hr`, ...), or null |

AR/EN name and description stay in `department_translations`. Draft, preview and publish go through `CmsWorkflowService` as today. The CMS target `facilities.<faculty>.departments` keeps its key, but its items become the department rows: published payload → rows, the same way About entities publish.

### 3.2 Specializations: read from the study plan
The study plan payload already has `plan.departments[]` with `id`, `nameAr/En`, `summaryAr/En` and `totalCredits`, so **that is the specialization list**. No new table. A faculty "has specializations" when its plan has more than one program.
- The board edits names and summaries. Optional presentation fields (degree title, career outcomes, tags, image) go in a small `specializations` block in the departments target payload, keyed by plan id, the way `studyPlanDepartmentId` is keyed today.
- `FacultyStudyPlanLinkService::effectiveDepartments` (private today) already resolves those ids. It becomes a public method on its interface and is reused.

(Optional later: rename `plan.departments` to `plan.programs` in code. Not needed now and it would touch the board, the importer and the graph JS.)

### 3.3 Services
- `FacultyDepartmentServiceInterface` (public): `departments(faculty, locale): Collection<FacultyDepartmentDTO>` (name, kind, code, summary, head `{name, photo, profileUrl}` via the public profile, specialization link, member count) and `specializations(faculty, locale): Collection<FacultySpecializationDTO>` (name, summary, credits, studyPlanUrl `?department=<id>`, teaching department).
- `FacultyDepartmentCmsServiceInterface`: normalizes and validates the draft (unique slugs, head must be a member of this faculty, `specialization_id` must exist in the plan) and publishes it to rows.
- `FacultyPageService::departmentItems` reads through the public service instead of the model.

## 4. Public page

Keep the URL `/{locale}/faculties/{faculty}/departments` and retitle it **"الاختصاصات والأقسام / Specializations & Departments"** for faculties with specializations, and **"الأقسام العلمية / Academic Departments"** for the rest.

1. **Specializations** (only when there are 2 or more): cards with name, summary, credits and the **Study Plan** button, deep-linked to that program.
2. **Academic departments**: Basic Sciences first, then the rest by sort order. Each row shows code, name, description and head (photo + link to the profile), plus "Teaches the X specialization" when linked. No Study Plan button on a department that has no specialization.
3. Stats strip: number of departments, number of specializations (if any), years, degree.

AR/EN, RTL/LTR, and an anchor per row (`#basic-sciences`) for deep links.

## 5. Admin

The faculty admin page's Departments tab becomes two sections:
- **Departments**: a repeater with name AR/EN, kind, code, description AR/EN, a head picker (faculty members of this faculty), an optional specialization select (options from the plan), and enabled/order.
- **Specializations**: read-only names from the study plan ("edit on the study plan board") plus the optional presentation fields per program.

Access stays as it is today: `canManageTarget(..., 'update'|'publish')` on the faculty target, with faculty limits applied.

The Faculty members admin gets a department select fed by the same rows (the `department_id` column already exists).

## 6. Data load
- Extend `faculty_structure.json` with `kind`, `code`, `specializationId` per department, and **add AI Basic Sciences**. `faculty:import-structure` already syncs departments by slug, so it only needs the new fields. Dry run by default.
- Department heads are matched to faculty profiles by an editor in the admin (not auto-filled from names).
- Existing published `departments` CMS payloads: one-off migration carries `code` and `studyPlanDepartmentId` into the new columns. `degrees` and `tags` move to the specialization block when the row is linked to a program, otherwise they are dropped and listed in the deploy log.

## 7. Open questions for the faculty/product owner
1. **Business**: is there a department for MIS (نظم المعلومات الإدارية)? Is there a Basic Sciences or quantitative department?
2. **AI**: confirm the Basic Sciences department's English name and description.
3. **Medicine / Dentistry / Pharmacy**: should the basic-science departments be flagged `basic_sciences` (grouped under a "Basic Sciences" heading) or just listed in order?
4. Should a department show its staff list (from `faculty_members.department_id`) on the page, or only its head?

## 8. Tests (when built)
- Unit: payload normalization and validation (head outside the faculty, unknown specialization id).
- Feature: the AI page shows 6 specializations + 7 departments with Basic Sciences first; the Medicine page shows no specializations block; a draft never shows publicly; AR and EN both render; Study Plan links carry `?department=`.
- `ArchitectureGuardTest` stays green (no Eloquent in public code).
