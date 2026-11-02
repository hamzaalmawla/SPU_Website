# Centers and the Requirements Unit

Status: built on `dev` (November 2026). Not deployed or signed off; see
`CURRENT_REMEDIATION_EXECUTION_CHECKLIST.md` for release status.

## What it is

Centers is a top-level public section, like About, Admissions or Faculties.

| Page | Path | CMS target |
| --- | --- | --- |
| Centers page: every center as a card, and what the centers offer | `/{locale}/centers` | `centers.landing` |
| A center's page: intro tabs, gallery, manager, numbers, staff preview, cards to its pages | `/{locale}/centers/{center}` | `centers.{center}` |
| Staff | `/{locale}/centers/{center}/staff` | `centers.{center}.staff` |
| Required courses | `/{locale}/centers/{center}/required-courses` | `centers.{center}.courses` |
| Elective courses | `/{locale}/centers/{center}/elective-courses` | `centers.{center}.courses` |

The centers are listed in `config/centers.php`. The first, and so far only, is
the Requirements Unit (`requirements-unit`). A center is part of the
university, not a faculty: it has a manager instead of a dean, and no
projects, research or labs. Adding another center type ("template") is
planned later; until then every center uses the `requirements-unit` template.

## Content and editing

- Admin › Centers (`App\Filament\Pages\ManageCenters`) edits all four targets
  with the usual draft, preview, publish, schedule and unpublish. Access is the
  `centers.view|update|publish` permissions; the migration
  `2026_11_02_000002_grant_centers_permissions` gives them to the `editor` role.
- Until a target is published, the site shows its default content:
  `database/seeders/Data/centers/centers.json` (page texts) and
  `requirements-unit-courses.json` (the course list). The editor opens on the
  same content.
- Every field is editable: hero texts and image, the "what the centers offer"
  cards, extra center cards (for centers with a page elsewhere), the center's
  tabs, gallery, manager title and message, numbers, page cards, search
  engine texts, and each course.

## Courses

One list per center (`courses[]` at the payload root), shown on the required
or the elective page by its `required` flag. A course has: `id` (its anchor
`#course-{id}`), an optional unit `code`, Arabic and English title and
description, credits, theory and practical hours, prerequisites (other
courses of the list), `faculties[] = {faculty, code, credits?}` (each faculty
that includes the course and its code there; `credits` only when that faculty
counts it differently), theory and practical teachers (profile ids, each part
can be hidden), a specification PDF and lessons (lecture or lab, with a PDF).
Publishing checks ids, titles, numbers, prerequisites, faculties, that a
faculty code belongs to one course only, teacher profiles and file links.

The default list is built by
`database/seeders/Data/centers/build_requirements_unit.py` from the faculty
study plans' university requirements (`type: university`). The plans spell
the same subject differently and give it a different code in each faculty
(Arabic Language is REQU103 in Medicine, UNRE103 in Dentistry, UR-2-01 in AI),
so the script's reviewed catalog groups them into 13 courses (4 required,
9 elective). Pharmacy's "Elective University Requirement (1–3)" slots name no
subject and stay unmatched.

The course pages can be narrowed by faculty (`?faculty=`, listed in
`CachePublicPages`). Each faculty chip links to that faculty's study plan.

## Link from the faculties

A study plan course of type `university` carries `unitCourseUrl`, worked out
by `CenterCatalogServiceInterface::studyPlanCourseUrl`:

1. the unit course picked on the study plan board (`requirementsUnitCourseId`,
   the "Requirements Unit course" select shown for university courses), else
2. the unit course whose `faculties` list holds this faculty and this code, else
3. the unit's required or elective list (by the course's own `required`).

The public study plan's course dialog and the course page show it as
"See this course at the Requirements Unit"; the course list opens that
course's details and scrolls to it.

## Staff

Staff are ordinary faculty profiles (one profile per person) with no faculty
and `faculty_members.center_slug` set, picked under Faculty Members ›
Center (shown only when Faculty is empty, and only to editors without a
faculty limit). The staff page lists the published ones in display order;
the manager (`managerMemberId` on the center page, which must be one of them)
comes first and fills the manager block. Their profile page names the center.

## Not done here

- The header menu: add "Centers" in Menu Builder (the page picker offers the
  new pages). It was not inserted from code to avoid changing the live menu.
- Site search does not index the centers pages yet.
- Other center templates.
