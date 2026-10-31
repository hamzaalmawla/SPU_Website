# One profile per person: plan (awaiting approval)

Status: approved by the user on 2026-10-23 and built. The live data move (`people:unify --write`) waits for the user's approval of the v2 dry run.

## The problem today

A person's name, photo and bio are stored in up to four places, and editing one does not reach the others:

| Where | Table | Holds its own copy of | Shown on |
|---|---|---|---|
| Faculty profile | `faculty_members` + `faculty_member_translations` + `faculty_member_educations` | name, title, position, bio, specializations, photo, CV, email, phone, links, education | faculty Members page, `/about/profile/{slug}`, staff directory, research directory |
| Leadership profile | `persons` + `person_translations` + `person_educations` + `person_appointments` | name, role, title, position, bio, quote, photo, CV, ORCID, Scholar, education | About › Leadership, faculty dean block (`FacultyPageService`), `/about/profile/{slug}`, staff directory |
| Research register | `researchers` (`faculty_member_id` optional) | `full_name`, affiliation, ORCID/Scholar/Scopus, expertise | publication author line, `/research/researchers/{slug}-rN`, researcher directory |
| Council membership | `council_member_translations` | full name, position, bio | profile page council list |
| Event speaker | `news_article_event_speakers` (`faculty_member_id`/`person_id` or `name_ar`/`name_en`) | name when not linked | event pages |

The current fix is a link (`faculty_members.person_id`, `people:merge`, "Link to leadership profile"). Pages then pick fields from one record or the other (`ProfilePageService::withLinkedFacultyMember`, `AboutPageService::personImage`), but both records still hold a name. Research code reads `researchers.full_name` directly (`ResearchPageService::publicationResearchers`, `registeredResearcherItem`), and that is why the AI dean's paper still shows the old name.

## Proposed target model

**The faculty member record is the person.** It is the only record that holds name, photo, bio, title, education, CV, contact and links, in AR and EN. Every other table either points at it or holds only what belongs to the role.

- **Faculty profile** (`faculty_members`): unchanged as the identity. `faculty_id` stays nullable: a leader with no faculty (rector, vice presidents, directors) gets a profile with no faculty. It shows on `/about/profile/{slug}` and in the staff directory but not on any faculty's Members page.
- **Leadership role** (`persons` rows become role rows): keeps `category` (rector, vice president, dean, council, director), `faculty_scope_slug`, `sort_order`, `is_enabled`, publish state, the old `slug` (only for redirects), and per locale the role label and the quote/message. It gets a required `faculty_member_id`. Its own name, photo, bio, education and links stop being read anywhere. The columns stay in the database for one release as a fallback, then a later migration drops them.
- **Researcher** (`researchers`): a register entry is either a staff member (`faculty_member_id` set: name, photo and expertise come from the profile, the row's `full_name` is not shown) or an external co-author (no profile: `full_name` is the only name). The paper's author picker offers "staff profile" or "external name".
- **Council membership and event speakers**: point at the faculty member. Their own name fields are used only for external people.
- `research_publications.person_id`, `council_members.person_id`, `news_article_event_speakers.person_id`, `faculty_members.person_id` and `person_appointments` are folded in and stop being written.

Result: one edit to a faculty profile (admin editor, or an approved staff intake request) updates the faculty page, the leadership page, the dean block, the research directory and every paper's author line.

## Two guarantees (from the user, 2026-10-23)

1. **Researcher suggestions stay.** The research register keeps its name-based suggestions exactly as they work today (`ResearcherAdminServiceInterface::staffSuggestions`, the "Suggested" filter, the hint on the researcher form, `linkToStaff`, and the exact-match batch `linkClearMatches`). When a researcher with papers gets a faculty profile, the register suggests it from the name (`PersonName::same` / `likelySame`), and an editor confirms it. This is how a researcher joins the one profile. After the change it also suggests leaders, because they become profiles too.
2. **No faculty-member data is lost.** `people:unify` never overwrites or deletes any faculty-member value: it only fills fields that are empty. It doesn't touch a faculty member that has an unpublished draft (it lists them instead), so editors' in-progress work is never mixed in. Before writing, it saves a JSON snapshot of every row it will touch to `storage/app/private/people-unify/` and logs one audit entry per change. No column is dropped in this change.

## Admin editing

- **Profiles** (today's Faculty Members editor) is the one place to edit a person. Profiles without a faculty are editable by users with no faculty limit.
- **Leadership** (today's People editor) edits only role, order, quote and visibility. The person is a searchable profile picker, with "Create profile" for someone new (name AR/EN and photo, saved as a faculty-member draft with no faculty) and an "Edit profile" link. There is no separate linking step: choosing the person is part of creating the role.
- **Researchers**: a register row for staff shows the profile's name read-only with "Edit profile"; only external rows have an editable name.
- Removed: "Link to leadership profile" / unlink, `PeopleMergeServiceInterface::linkFacultyMember` and `unlinkFacultyMember`, the `people:merge` linking pass.
- Staff intake approvals keep writing to the faculty member through the existing workflow; leadership and research now pick that up with no extra step.

## Draft / publish, cache, audit

- Profile drafts stay `entity.faculty-member.{id}`. Leadership drafts (`entity.person.{id}`) shrink to role fields.
- Publishing a profile also clears the leadership, faculty dean block, researcher directory and publication caches that show that person, and the audit log records it as before.

## Public URLs

- The profile address is the faculty member's slug: `/{locale}/about/profile/{member-slug}`.
- Old leadership slugs, `/about/profile/person/{slug}`, `?source=person|faculty-member`, legacy `?id=` / `?slug=`, and `/research/researchers/{slug}-rN` for staff researchers 301 to that address. External researchers keep their `-rN` page.
- Today, linked pairs publish under the leadership slug. After the change they publish under the member slug, and the leadership slug 301s to it (sitemap and hreflang follow).

## Moving existing data (needs your approval before it touches v2)

The schema change only adds `persons.faculty_member_id` (nullable), so it passes the deploy's data-migration gate. The data move is a separate command, `people:unify`, which is a dry run by default:

1. For each leadership row it picks the profile: an existing `faculty_members.person_id` link first, then a single exact name match (`PersonName`), otherwise "create a profile from this leadership record".
2. Field rule: **the faculty profile's value wins** (it is what editors and teachers have been updating), and empty profile fields are filled from the leadership record (photo, EN name, bio, education, CV, ORCID, Scholar). The dry run lists every field where the two differ, so you can check them first.
3. It repoints papers, council memberships and event speakers from `person_id` to the profile, and points staff researchers at their profile.
4. It skips any leadership row with an unpublished draft and lists it, so an in-progress edit is never overwritten.
5. It writes an audit entry per person. The old columns are kept, so the run can be undone.

On v2 it runs only through an approval file (`people-unify.approved`), like `person-photos`, after you have read the dry-run report from a deploy log. Locally there are 10 leadership rows (4 linked), 234 faculty members and 226 researchers (6 linked); the v2 numbers come from that first dry run.

Not in scope: two faculty-member records for the same teacher in two faculties. `people:duplicates` will keep listing them, and merging them would be a separate change.

## As built (2026-10-23)

- Identity: `PersonIdentityServiceInterface` gives the profile behind a leadership row (`persons.faculty_member_id`, or the older `faculty_members.person_id`) and its public name, photo and bio. It is used by the profile page, leadership cards, staff directory, faculty Members page, the faculty dean block, event speakers, sitemap and site search.
- Profile page: `ProfilePageService` resolves a faculty profile first. A leadership slug with a public profile redirects (301) there. The role, quote, category and council seats come from the leadership row, and everything else from the profile.
- Faculty dean block: when the dean resolves to a profile, the block shows the profile's name and photo. If the profile's name was edited and no longer matches the block's text, the one dean filed for that faculty is used.
- Research: paper author lines, the researcher directory and `-rN` pages use the profile's name for staff researchers. The research register keeps `full_name` as it was typed (shown in the admin, with a note), and the staff suggestions are unchanged.
- Admin: the leadership editor has a profile picker with "Edit profile" and "Create profile" links. Name, bio, photo, contact, social links and education are hidden once a profile is picked. Publishing writes only the role fields (`publishLeadershipRole`) and leaves the row's old identity columns untouched. The faculty profile editor lost the link and unlink actions, and has a "Leadership role" button when the person holds one. A profile may have no faculty, but only for editors without a faculty limit.
- Cache: publishing `entity.faculty-member.*` or `entity.person.*` also flushes `about`, `facilities` and `research` (`InvalidateCmsCache`).
- `people:unify`: the dry run is in every deploy log, and the write runs from `people-unify.approved`. A row that only probably matches a profile (`PersonName::likelySame`) or matches several is left for an editor, so no duplicate profile is made.
- Removed: `PeopleMergeServiceInterface::leadershipOptions`, `linkFacultyMember`, `unlinkFacultyMember`, and the link pass of `people:merge`.
- Left for later, after the write has run and been checked on v2: dropping the leadership rows' old identity columns.
