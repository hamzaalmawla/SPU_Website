# Academic Staff Intake Link: Plan

Status: proposal, nothing built yet. Decisions marked **(decide)** need product approval before work starts.

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

**(decide)** Recommended: approval for everything. Possible later relaxation: auto-apply edits to an existing profile once its email OTP (below) is verified, but always review new staff.

## Flow

```
/staff-intake/{token}   (token → one faculty, active, not expired or revoked)
  1. Teacher types a name (min 3 letters) ──► lookup, rate limited
  2. Suggestions: up to 5 matches in this faculty first, then others
     shown as: name (AR/EN), title, department, photo thumb. Nothing private.
  3a. "This is me" ──► if the profile has an email on file, send a 6-digit
       code to it; the code unlocks prefill of private fields (email, phone,
       office). Without an email on file, only public fields prefill.
  3b. "None of these is me" ──► blank form; the name typed is kept
  4. Submit ──► server re-runs the duplicate check (step "No duplicates")
  5. Request saved as pending; teacher sees a reference number.
     Reviewers of that faculty are notified.
  6. Reviewer: approve / approve as update of another profile / reject
     ──► ProfileAdminService writes; new staff start as Draft
```

## Data model

No new columns on existing tables. Two new tables:

**`staff_intake_links`**: `id`, `faculty_id` (FK), `token_hash` (sha256, unique; the raw token appears only once, when the link is created), `label`, `expires_at`, `revoked_at`, `max_submissions` (nullable), `submissions_count`, `created_by`, timestamps.

**`staff_intake_requests`**: `id`, `reference` (unique, short public code), `staff_intake_link_id`, `faculty_id`, `type` (`update` | `create`), `faculty_member_id` (nullable FK; the profile the teacher claimed), `identity_verified_at` (when the OTP was used), `payload` (JSON shaped exactly as `FacultyMemberDataDTO`: translations ar/en, educations, department, contact), `original_snapshot` (JSON of the profile at prefill time, for the reviewer's diff), `duplicate_candidates` (JSON of IDs found at submit), `status` (`pending` | `approved` | `rejected` | `superseded`), `reviewed_by`, `reviewed_at`, `review_note`, `applied_faculty_member_id`, `submitter_ip_hash`, timestamps. Uploaded photo/CV are stored on the private disk and referenced in the payload until approval.

OTP codes: hashed in the cache (`array`/file store works) with a 10-minute TTL and 5 attempts. No table.

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

**(decide)** Confirm the field list, and whether the teacher must fill both AR and EN or editors translate.

## No duplicates, no mismatches

1. **Lookup** compares the typed name with `PersonName::same()` (strong) and `likelySame()` (weak) against every live `faculty_members` translation in all faculties, against `persons`, and against pending requests. It needs at least two name parts to suggest.
2. **Claiming** a suggestion sends back a signed, single-use reference (not a raw ID) that the server resolves. A member in another faculty becomes a `create` request in this faculty, pre-linked to the same `person_id`. That's one person teaching in two faculties, the case `PeopleMergeService` already leaves alone.
3. **"None of these is me"** is checked again on submit. If there's a strong match in this faculty, or the same email or phone as an existing member, the teacher is asked to confirm, and the request is flagged `possible duplicate` with the candidates stored.
4. **One open request per profile.** A second submission for the same member, or the same unclaimed name with the same email, replaces the pending one (`superseded`). It's done inside a transaction that locks the member row, so double-clicks and two teachers can't fork it. (MariaDB has no partial unique index, so this is enforced in the service.)
5. **Review screen** shows a field-by-field diff against the live profile, plus the duplicate candidates with "apply to this profile instead" and "create new anyway (reason required)".
6. **Applying** goes only through `ProfileAdminService`, which reruns validation, faculty-scope checks, unique-slug creation and cache invalidation and writes the audit log. New members are created as `Draft`, `is_enabled = true`, and become public when an editor publishes, the same as admin-created ones. The person link uses the existing canonical-person path, not a parallel one.
7. **Afterwards**: `PeopleDuplicateReportService` gets a section for pending intake requests, so anything that slipped through shows up in the existing report.

## Security

- **Link**: random 40-character token, only its hash stored. Scoped to one faculty, expiry date, revocable, optional submission cap. Admins can rotate it with one click (the old link stops working). Unknown, expired or revoked tokens return the same 404.
- **Not public-indexed**: `noindex, nofollow` meta and `X-Robots-Tag` header, excluded from the sitemap and search, `Referrer-Policy: no-referrer` so the token doesn't leak, bypassed in `CachePublicPages` (`Cache-Control: no-store`).
- **No data harvesting**: the lookup returns only fields already public on the profile page, for at most 5 results, and only for published members. It's throttled with a new `staff-intake-lookup` limiter (by IP and token, e.g. 30/min). Private fields prefill only after the email OTP. Phone and email are never echoed in suggestions, and the OTP target is shown masked (`h***@spu.edu.sy`).
- **Submit**: CSRF, `throttle:public-form`, a honeypot field and a minimum fill time. A FormRequest validates every field (lengths, URL scheme `https?` only, locale keys `ar`/`en` only, department belongs to the link's faculty). Bio is plain text, stripped of HTML.
- **Uploads**: MIME sniffed (not just extension), size limits, images re-encoded, stored on a non-public disk with random names, moved into the media library only on approval.
- **Authorization**: a new `StaffIntakeRequestPolicy` and `StaffIntakeLinkPolicy`. A `faculty_editor` sees and reviews only their own faculty (`faculty_scope_slug`). Only `editor` / `super_admin` create links.
- **Audit**: link created/rotated/revoked, request submitted, OTP verified, request approved/rejected, all through `AuditServiceInterface`. The IP is stored hashed.
- **Retention (decide)**: delete the payloads and uploads of rejected and superseded requests after 90 days (scheduled command).

## Code layout (follows ARCHITECTURE.md)

- `app/Contracts/Staff/StaffIntakeLinkServiceInterface.php`, `StaffIntakeServiceInterface.php`, `StaffIntakeReviewServiceInterface.php`
- `app/Services/Staff/*` (all logic); `StaffIntakeService` uses `ProfileAdminServiceInterface` and never writes `faculty_members` itself
- `app/DTOs/Staff/StaffIntakeLinkDTO`, `StaffMatchSuggestionDTO`, `StaffIntakeRequestDTO` (`final readonly`)
- `app/Models/Staff/StaffIntakeLink.php`, `StaffIntakeRequest.php`
- `app/Http/Controllers/Public/StaffIntakeController.php` (thin: show, lookup, claim, verify-otp, submit), `app/Http/Requests/StaffIntake*Request.php`
- Routes under the localized public group: `GET /staff-intake/{token}`, `POST …/lookup`, `POST …/claim`, `POST …/verify`, `POST …/submit`, each throttled
- View `resources/views/public/staff-intake/*.blade.php` plus an Alpine component for the lookup and the education repeater, AR (RTL) and EN (LTR), keyboard-accessible combobox (`role="combobox"`, arrow keys, Esc)
- Filament: `StaffIntakeLinkResource` (create, copy link once, rotate, revoke, stats) and `StaffIntakeRequestResource` (queue, diff view, approve/reject actions, faculty-scoped) under the existing People navigation group
- Notification to the faculty's reviewers on new requests (database queue, existing mail setup)
- Translations in `lang/ar` and `lang/en`, with no hardcoded strings

## Tests

- Feature: token states (valid, expired, revoked, rotated, other faculty) and 404 parity. Lookup returns only public fields and is throttled. OTP gate for private fields. Submit validation, including a department from another faculty being rejected. Honeypot.
- Duplicates: strong and weak matches, Arabic variants, "not me" with a strong match flags the request, a second submission supersedes the first, a member in another faculty is linked by `person_id`, a concurrent submit doesn't create two requests.
- Review: a faculty editor can't see another faculty's requests. Approve-update changes only the claimed member. Approve-create gives a Draft with a unique slug and nothing public until published. Cache invalidated, audit written.
- `ArchitectureGuardTest` stays green (no Eloquent in controllers).

## Delivery slices

1. Tables, models, link service and admin link resource (create, rotate, revoke).
2. Public page, lookup, claim, OTP, submit, all security controls; requests saved.
3. Review queue, diff, approve/reject through `ProfileAdminService`, notifications.
4. Duplicate report section, retention command, CMS user guide section, `FRONTEND_IMPORT_TRACKER.md` entry.

## Open decisions

1. Approval for every submission (recommended), or auto-apply verified edits?
2. Email OTP before private-field prefill: acceptable, given some profiles may lack an email (those get public-field prefill only)?
3. Final field list, and whether both languages are required from the teacher.
4. Link lifetime default (suggested 30 days) and whether a submission cap is wanted.
5. Who receives new-request notifications: faculty editors of that faculty, a central editor, or both?
