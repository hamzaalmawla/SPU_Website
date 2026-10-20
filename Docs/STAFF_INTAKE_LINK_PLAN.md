# Academic Staff Intake Link: Plan

Status: built on `dev` (2026-10-20), not deployed. Decisions agreed on 2026-10-20 are listed under "Decisions". Where the build differs from the plan below, "As built" says so.

## As built

- **Approval path**: approval does not call `ProfileAdminService` directly. It goes through the same CMS workflow as the admin profile form (`AboutEntityCmsService::prepareDraft` → `CmsWorkflowService::saveDraft` → `publish`). So an editor or super admin approval publishes at once. A faculty editor's approval (they cannot publish) leaves a draft for an editor. A profile the CMS will not publish yet (no position or bio in both languages) is also kept as a draft, and the reviewer is told why; since the biography became required this only happens to requests sent before that.
- **Permissions**: access follows the permission system (Administration › Roles). `staff_intake_links.view/create/update` covers making, rotating and revoking links; `staff_intake_requests.view/update` covers reviewing and approving. Migration `2026_10_21_000002` grants all five to the Editor and Faculty Editor roles. A user limited to faculties only sees and acts on their own faculties. Approval publishes only when the reviewer also holds `faculty_members.publish`.
- **Profiles from another faculty**: picking one starts a new profile in the link's faculty, prefilled from the other one (`related_faculty_member_id`). It is not linked by `person_id`: a linked member is rendered through its People profile, which has no appointment in the new faculty.
- **People-linked profiles**: when the claimed member has a `person_id`, the public page shows the People profile, so the review screen warns the reviewer to update that profile too.
- **Referrer**: the site-wide `Referrer-Policy: strict-origin-when-cross-origin` already keeps the link from other sites. The pages also add `<meta name="referrer" content="no-referrer">`.
- **Access log events**: opened, claimed, started_new, submitted, superseded. Searches are not logged one by one.
- **Required fields** (2026-10-20): both names, the biography in Arabic and in English (publishing needs a position or a biography in both languages, and a profile without one looks empty), email, the LinkedIn, ORCID, Scopus, ResearchGate and Google Scholar links, a photo and a CV (PDF). X / Twitter and the rest stay optional. A profile being updated may keep the photo and CV it already has; a new profile started from one in another faculty reuses that profile's media library photo and CV.
- **Not built yet**: the retention command (step 4) and a duplicate-report section for pending requests.
- **Name matching**: `PersonName::tokens()` now folds through `SearchTextNormalizer` (diacritics, ؤ, ئ), and `PersonName::matchRank()` is new. This makes `PeopleMergeService` match slightly more names. Run `people:merge` as a dry run on production data before the next real merge and compare its output with the previous run.

### Where the code is

- Public: `routes/web.php` (`public.staff-intake.*`, outside the page cache), `StaffIntakeController`, `StaffIntakeSubmitRequest`, `StaffIntakeHeaders`, views in `resources/views/public/staff-intake/`, script `resources/js/staffIntake.js`.
- Services: `app/Services/Staff/` (`StaffIntakeLinkService`, `StaffIntakeService`, `StaffIntakeReviewService`, `StaffIntakeFields`), contracts in `app/Contracts/Staff/`.
- Admin: Faculties → "Staff intake links" (`StaffIntakeLinkResource`, with its access log) and "Staff intake requests" (`StaffIntakeRequestResource`, review screen with the diff, the duplicates and approve/reject).
- Tests: `tests/Feature/StaffIntakeTest.php`, `tests/Unit/PersonNameMatchingTest.php`.

## Goal

One link per faculty, sent by the university to that faculty's academic staff. A teacher opens it and types their name:

- **Found**: the form suggests the matching profile. The teacher confirms it's them, the form fills in what we already hold, and they correct or add to it.
- **Not found**: the teacher fills in a blank form and a new staff member is created.

Hard requirements: no duplicate people, and no data that doesn't fit the existing profile model. Changes must appear on the public faculty pages, profile pages, search and research pages the same way an admin edit would. The link must be secure.

## Why this isn't a Forms-builder form

The dynamic forms (`form_definitions`, `DynamicFormSubmissionService`) store free-form answers in an inbox. This flow needs a name lookup, a prefill from live records, and a typed mapping onto `faculty_members` and its translations and educations. So it becomes its own small module that reuses the existing pieces:

| Need | Existing piece reused |
| --- | --- |
| Name matching (titles, Arabic letter variants, missing middle name, Latin spellings) | `App\Support\PersonName::same()` / `likelySame()` |
| Writing a staff member (validation, faculty scope, slug, media, cache, audit) | `ProfileAdminServiceInterface::createFacultyMember` / `updateFacultyMember` |
| Staff spread over faculties / canonical person | `faculty_members.person_id` → `persons`, `PersonAppointment` |
| Duplicate review and merge | `PeopleDuplicateReportService`, `PeopleMergeService` |
| Rate limiting | `RateLimiter::for('public-form')` pattern in `AppServiceProvider` |

## Core rule: the link never writes live data

Every submission becomes a **staff update request** that waits for review. An editor approves it in the admin (a `faculty_editor` scoped to that faculty, or `editor` / `super_admin`), and only then is it applied through `ProfileAdminService`. This one rule handles most of the risk:

- A leaked link, or someone typing a colleague's name, can't change a public profile.
- Approval runs the same validation, scope checks, slug rules, cache invalidation and audit log as an admin edit, so other pages stay consistent.
- Duplicates are caught by a person before anything is created.

Agreed: approval for everything, both edits and new staff.

## Flow

```
/staff-intake/{token}   (token → one faculty, active, not expired or revoked)
  1. Teacher types a name (min 3 letters) ──► lookup, rate limited
  2. Suggestions: up to 5 matches in this faculty first, then others
     shown as: name (AR/EN), title, department, photo thumb.
  3a. "This is me" ──► every field we hold is filled in, contact details
       included; the teacher edits anything (no code check: the link is
       shared only inside the faculty's staff group)
  3b. "None of these is me" ──► blank form; the name typed is kept
  4. Submit ──► server re-runs the duplicate check (step "No duplicates")
  5. Request saved as pending; teacher sees a reference number.
     The user who generated the link is notified.
  6. Reviewer: approve / approve as update of another profile / reject
     ──► ProfileAdminService writes; new staff start as Draft
```

## Data model

No new columns on existing tables. Two new tables:

**`staff_intake_links`**: `id`, `faculty_id` (FK), `token_hash` (sha256, unique; the raw token appears only once, when the link is created), `label`, `expires_at`, `revoked_at`, `max_submissions` (nullable), `submissions_count`, `created_by`, timestamps.

**`staff_intake_requests`**: `id`, `reference` (unique, short public code), `staff_intake_link_id`, `faculty_id`, `type` (`update` | `create`), `faculty_member_id` (nullable FK; the profile the teacher claimed), `payload` (JSON shaped exactly as `FacultyMemberDataDTO`: translations ar/en, educations, department, contact), `original_snapshot` (JSON of the profile at prefill time, for the reviewer's diff), `duplicate_candidates` (JSON of IDs found at submit), `status` (`pending` | `approved` | `rejected` | `superseded`), `reviewed_by`, `reviewed_at`, `review_note`, `applied_faculty_member_id`, `submitter_ip_hash`, timestamps. Uploaded photo/CV are stored on the private disk and referenced in the payload until approval.

**`staff_intake_access_logs`** (who entered and who edited, kept even when a request is superseded or rejected): `id`, `staff_intake_link_id`, `staff_intake_request_id` (nullable), `faculty_member_id` (nullable), `event` (`opened` | `searched` | `claimed` | `started_new` | `submitted` | `superseded`), `submitter_name` (the name typed or claimed), `changed_fields` (JSON list of field keys changed versus the snapshot, on `submitted`), `ip_address`, `user_agent` (truncated), `created_at`. Search events store the query length and result count, not every keystroke.

## Form fields (mapped to existing columns only)

| Field | Target |
| --- | --- |
| Full name AR / EN (EN required for a new member) | `faculty_member_translations.full_name` |
| Academic title AR / EN | `faculty_member_translations.title` |
| Position AR / EN | `faculty_member_translations.position` |
| Bio AR / EN | `faculty_member_translations.bio` |
| Specializations AR / EN (tags) | `faculty_member_translations.specializations` |
| Department (select, this faculty's departments only) | `faculty_members.department_id` |
| Email, phone, office | `faculty_members.email/phone/office_location` |
| Social / ORCID / Scholar links (URL-validated) | `faculty_members.social_links` |
| Education rows (degree, institution, field, years) AR / EN | `faculty_member_educations` + translations |
| Photo (jpg/png/webp, ≤ 2 MB) and CV (pdf, ≤ 5 MB) | media library on approval → `photo_media_id` / `cv_media_id` |

Not in the form: faculty (fixed by the link), slug, sort order, publication status, council membership and research publications. Those stay with editors and the research office.

The teacher fills in all of these themselves, in both Arabic and English; English full name is required for a new member, the rest may be left empty.

## Name suggestions: Arabic and English, spelling-tolerant

The teacher can type in Arabic or English, and suggestions appear as they type (from the 3rd letter). Each suggestion shows **both** the Arabic and English name, so the teacher recognises themselves whichever language they typed in. Matching runs against both the AR and EN `full_name` of every member, using `App\Support\PersonName`, extended for this:

| Variation | Example | Handled by |
| --- | --- | --- |
| Hamza / alef forms | أحمد = احمد = إحمد = آحمد | `tokens()` (already) |
| Taa marbuta, alef maqsura | فاطمة = فاطمه, على = علي | `tokens()` (already) |
| Tatweel | محـمد = محمد | `tokens()` (already) |
| Diacritics (tashkeel) | مُحَمَّد = محمد | **to add**: today the marks split the word and the match fails (verified) |
| Hamza on waw / yaa | مؤمن = مومن, هانئ = هاني | **to add** to `tokens()` |
| Joined / split names | عبد الله = عبدالله | `same()` (already) |
| Upper / lower case | AHMAD = Ahmad = ahmad | `tokens()` (already) |
| Latin spelling variants | Ahmad = Ahmed, Mohammad = Muhammad = Mohamed, Khaled = Khalid, Abdullah = Abdallah, Hasan = Hassan | `likelySame()` consonant outline (already) |
| Titles | د. / Dr. / Prof. / المهندس | ignored (already) |
| Missing middle name | Ayman Ali = Ayman Mohammad Ali | `same()` (already) |
| Partial typing | "Ahm", "احم", "Mohamed Kh" | **to add**: `PersonName::startsLike()`, prefix match on the normalised words and on their consonant outlines |

Ranking: exact name, then `same()`, then `likelySame()`, then prefix-only. Members of the link's faculty come before other faculties. At most 5 are shown. The diacritics and hamza fixes also make the existing duplicate report and merge more accurate. Before the change, run `PeopleMergeService` in dry-run mode and compare its output, so the fix doesn't silently change what gets merged.

Typing English when a member only has an Arabic name (or the other way round) can't be matched reliably by spelling rules. Such members are listed in the admin ("missing EN name") so editors can fill them in before the link goes out.

## No duplicates, no mismatches

1. **Lookup** compares the typed name with `PersonName::same()` (strong) and `likelySame()` (weak) against every live `faculty_members` translation in all faculties, against `persons`, and against pending requests. It needs at least two name parts to suggest.
2. **Claiming** a suggestion sends back a signed, single-use reference (not a raw ID) that the server resolves. A member in another faculty becomes a `create` request in this faculty, pre-linked to the same `person_id`. That's one person teaching in two faculties, the case `PeopleMergeService` already leaves alone.
3. **"None of these is me"** is checked again on submit. If there's a strong match in this faculty, or the same email or phone as an existing member, the teacher is asked to confirm, and the request is flagged `possible duplicate` with the candidates stored.
4. **One open request per profile.** A second submission for the same member, or the same unclaimed name with the same email, replaces the pending one (`superseded`). It's done inside a transaction that locks the member row, so double-clicks and two teachers can't fork it. (MariaDB has no partial unique index, so this is enforced in the service.)
5. **Review screen** shows a field-by-field diff against the live profile, plus the duplicate candidates with "apply to this profile instead" and "create new anyway (reason required)".
6. **Applying** goes only through `ProfileAdminService`, which reruns validation, faculty-scope checks, unique-slug creation and cache invalidation and writes the audit log. New members are created as `Draft`, `is_enabled = true`, and become public when an editor publishes, the same as admin-created ones. The person link uses the existing canonical-person path, not a parallel one.
7. **Afterwards**: `PeopleDuplicateReportService` gets a section for pending intake requests, so anything that slipped through shows up in the existing report.

## Security

- **Link**: random 40-character token, only its hash stored. Scoped to one faculty, revocable. Whoever generates it (faculty editor for their own faculty, or super admin) chooses its expiry date and an optional submission cap. They can rotate it with one click (the old link stops working). Unknown, expired or revoked tokens return the same 404.
- **Not public-indexed**: `noindex, nofollow` meta and `X-Robots-Tag` header, excluded from the sitemap and search, `Referrer-Policy: no-referrer` so the token doesn't leak, bypassed in `CachePublicPages` (`Cache-Control: no-store`).
- **Lookup**: suggestions show only name, title, department and photo, at most 5, for live members. Throttled with a new `staff-intake-lookup` limiter (by IP and token, e.g. 30/min). The full record, contact details included, loads only after "This is me" (accepted risk: the link goes only to the faculty's staff group; every claim is logged, see below).
- **Submit**: CSRF, `throttle:public-form`, a honeypot field and a minimum fill time. A FormRequest validates every field (lengths, URL scheme `https?` only, locale keys `ar`/`en` only, department belongs to the link's faculty). Bio is plain text, stripped of HTML.
- **Uploads**: MIME sniffed (not just extension), size limits, images re-encoded, stored on a non-public disk with random names, moved into the media library only on approval.
- **Authorization**: a new `StaffIntakeRequestPolicy` and `StaffIntakeLinkPolicy`. A `faculty_editor` sees and reviews only their own faculty (`faculty_scope_slug`). A `faculty_editor` creates links for their own faculty only; `super_admin` (and `editor`) for any faculty.
- **Access and edit log**: every open, claim, new-entry start and submit is written to `staff_intake_access_logs` with the name used, the profile claimed, which fields changed, IP and browser. The admin shows it per link and per staff member ("who entered, who edited what, when"), visible to the link's creator, the faculty's editors and super admins. If someone claims a colleague's profile, the log shows it and the reviewer can reject the request.
- **Audit**: link created/rotated/revoked and request approved/rejected go through `AuditServiceInterface`.
- **Retention (decide)**: delete the payloads and uploads of rejected and superseded requests after 90 days (scheduled command).

## Code layout (follows ARCHITECTURE.md)

- `app/Contracts/Staff/StaffIntakeLinkServiceInterface.php`, `StaffIntakeServiceInterface.php`, `StaffIntakeReviewServiceInterface.php`
- `app/Services/Staff/*` (all logic); `StaffIntakeService` uses `ProfileAdminServiceInterface` and never writes `faculty_members` itself
- `app/DTOs/Staff/StaffIntakeLinkDTO`, `StaffMatchSuggestionDTO`, `StaffIntakeRequestDTO` (`final readonly`)
- `app/Models/Staff/StaffIntakeLink.php`, `StaffIntakeRequest.php`
- `app/Http/Controllers/Public/StaffIntakeController.php` (thin: show, lookup, claim, submit), `app/Http/Requests/StaffIntake*Request.php`
- Routes under the localized public group: `GET /staff-intake/{token}`, `POST …/lookup`, `POST …/claim`, `POST …/submit`, each throttled
- View `resources/views/public/staff-intake/*.blade.php` plus an Alpine component for the lookup and the education repeater, AR (RTL) and EN (LTR), keyboard-accessible combobox (`role="combobox"`, arrow keys, Esc)
- Filament: `StaffIntakeLinkResource` (create, copy link once, rotate, revoke, stats) and `StaffIntakeRequestResource` (queue, diff view, approve/reject actions, faculty-scoped) under the existing People navigation group
- Notification to the link's creator on each new request (database queue, existing mail setup)
- Translations in `lang/ar` and `lang/en`, with no hardcoded strings

## Tests

- Feature: token states (valid, expired, revoked, rotated, cap reached, other faculty) and 404 parity. Suggestions carry only name/title/department/photo and are throttled; claim returns the full record. Every step writes the expected access-log row with changed fields. Submit validation, including a department from another faculty being rejected. Honeypot.
- Name matching: unit tests for every row of the variation table above, in both directions, including partial typing and ranking.
- Duplicates: strong and weak matches, Arabic variants, "not me" with a strong match flags the request, a second submission supersedes the first, a member in another faculty is linked by `person_id`, a concurrent submit doesn't create two requests.
- Review: a faculty editor can't see another faculty's requests. Approve-update changes only the claimed member. Approve-create gives a Draft with a unique slug and nothing public until published. Cache invalidated, audit written.
- `ArchitectureGuardTest` stays green (no Eloquent in controllers).

## Delivery slices

1. Tables, models, link service and admin link resource (create, rotate, revoke).
2. `PersonName` extensions (diacritics, hamza on waw/yaa, prefix match) with tests; public page, lookup, claim, submit, access log, all security controls; requests saved.
3. Review queue, diff, approve/reject through `ProfileAdminService`, notifications.
4. Duplicate report section, retention command, CMS user guide section, `FRONTEND_IMPORT_TRACKER.md` entry.

## Decisions

1. Every submission (edits and new staff) waits for approval before anything public changes.
2. No email code. "This is me" fills in everything we hold; the link is shared only with the faculty's staff group, and the access log records who entered and what they changed.
3. The teacher fills in all their data (every field in the table), in both languages.
4. Expiry and submission cap are chosen by whoever generates the link.
5. Links are generated by a faculty editor (own faculty) or a super admin, and that user is notified of new requests.
