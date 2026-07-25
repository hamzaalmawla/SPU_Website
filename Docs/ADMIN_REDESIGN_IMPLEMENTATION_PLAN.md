# Admin Redesign Implementation Plan

## Status

Draft for implementation on `ux/admin-redesign-foundation`.

## Implementation Progress

Completed in the first implementation slice:

- [x] Scheduled News articles are processed by the scheduled-content command.
- [x] Locked or no-longer-authorized approvers cannot release scheduled News or
  CMS content.
- [x] Saving a newer CMS draft preserves an existing scheduled release.
- [x] A due scheduled CMS release publishes without deleting the newer working
  draft.
- [x] Global Filament unsaved-navigation alerts are enabled.
- [x] The shared admin visual language, responsive workspace header, and
  task-navigation patterns are established.
- [x] News Articles use simplified sections, progressive disclosure, localized
  identity, AR/EN direction, and a focused publishing tab. Staff choose only
  News or Announcement; generic category management is no longer exposed.
- [x] Research uses task cards instead of a technical target selector, localized
  workflow actions, permission-aware publishing controls, and safe errors.
- [x] Faculty workspaces use URL-addressable task cards, dynamic faculty labels,
  localized workflow actions, safe errors, and permission-aware publishing.
- [x] Study-plan prerequisites use human course selectors while reverse graph
  data remains an internal derived concern.
- [x] The Job Board uses paired bilingual vacancy cards with shared status,
  dates, application settings, generated identity, and collapsed interface copy.
- [x] Job Board is a dedicated, permission-aware sidebar workspace rather than a
  target hidden inside the Campus Life selector.
- [x] Study-plan department and term navigation uses visible URL-addressable
  choices instead of form dropdowns. The editor hydrates only the selected term
  while preserving every other term when the draft is saved.
- [x] Faculty, facilities-hub, locale, and shared media-picker controls are
  localized in Arabic admin mode; technical slugs and linkages are hidden or
  moved under collapsed advanced settings.
- [x] Events is a dedicated sidebar workspace with paired Arabic/English event
  cards, shared scheduling and registration fields, inline category choices,
  generated identity, and publish-ready payload preservation.
- [x] Announcements is a dedicated sidebar workspace that links to the canonical
  announcement-filtered News Article records instead of duplicating content.
- [x] Announcement article languages are fixed bilingual records rather than a
  user-selectable locale dropdown; publishing and attachment types use visible
  inline choices.
- [x] CI runs frontend tests and a production frontend build before PHP tests.
- [x] Individual News Articles use versioned aggregate drafts, protected AR/EN
  previews, explicit publish/schedule/unpublish actions, and atomic revision
  promotion without changing an already-published article during normal edits.

Still required before the priority release is complete:

- [x] Introduce revision drafts for already-published News records so editing a
  live article never changes public content before explicit publication.
- [x] Replace the combined Events payload editor with a focused bilingual event
  workflow.
- [ ] Complete the study-plan migration by pairing Arabic and English course
  content and generating remaining term/course identifiers internally.
- [ ] Finish task-specific field localization and progressive disclosure inside
  each Research editor.

## Purpose

Redesign the administrative CMS so university staff can safely create, edit,
review, schedule, publish, and unpublish content without needing to understand
database fields, internal IDs, slugs, JSON payloads, or implementation details.

This is a workflow and UI/UX redesign. It is not a resource-by-resource label
translation exercise.

## Core Outcome

For every priority content type, a non-technical staff member must understand:

- where they are and what they are editing;
- whether the content is draft, scheduled, published, or unpublished;
- whether Arabic and English content are complete;
- what each action will do before they select it; and
- how to leave or change context without losing unsaved work.

## Priority Scope

The first release covers these staff workflows:

1. News publishing.
2. Announcements and events publishing.
3. Research publishing.
4. Job board publishing.
5. Faculty study-plan course editing.

The first release also establishes shared navigation, publishing, localization,
form, and feedback patterns required by those workflows.

## Explicitly Out Of Scope For The First Release

- Full redesign of all admin routes.
- About-domain editor consolidation.
- Full media-library redesign.
- User, role, audit-log, and two-factor settings redesign.
- Broad migrations of all legacy payloads into new entities.
- Replacing valid public-site URLs or existing public content without an
  approved migration plan.

## Current-State Facts To Address

- News records expose `scheduled_at`, but the scheduled-content command does
  not process News articles. A scheduled article will not become public.
- Saving or previewing a scheduled CMS target creates a new draft and can
  supersede the scheduled draft without a clear warning.
- Target, filter, locale, tab, and other context changes can replace Livewire
  form state without unsaved-change protection.
- Custom CMS pages expose large mixed forms and technical target selectors.
- Faculty study plans expose internal course IDs, prerequisite IDs, reverse
  graph IDs, profile slugs, and deeply nested repeaters to normal editors.
- News, announcements, events, research, and jobs currently use different
  persistence and publishing shapes. The staff UI must not expose those
  differences as a burden on the editor.

## Product Rules

### Publishing

- Saving a draft never changes public content.
- Only an explicit Publish action can make content public immediately.
- Scheduling is available only where the scheduler performs the required
  transition and a focused test proves it.
- Editing scheduled content must require an explicit choice: edit the scheduled
  version, create a new draft, or cancel the schedule and continue editing.
- Preview opens separately. If preview needs to persist the draft first, the
  action must be named `Save and preview` and explain that effect.
- Only authorized roles see publishing actions. Disabled or hidden actions must
  explain the applicable permission rule.
- Every publishable editor displays current public state, draft state, scheduled
  date and time, last editor, and language-completion status.

### Languages

- Arabic is the default admin locale and renders RTL.
- English renders LTR.
- Arabic and English content are edited in explicit, separately directed tabs.
- A fallback title may help identify legacy records, but it must never hide a
  missing translation. The UI displays a language-completeness warning.
- Publish requirements for bilingual content are explicit and shown before a
  user attempts publication.

### Form Simplicity

- Normal workflow fields appear first.
- Slugs, sort order, faculty scope, raw URLs, legacy paths, SEO, diagnostics,
  and implementation metadata are in collapsed Advanced settings or generated
  automatically.
- Raw IDs and reverse relationship fields are not staff-facing inputs.
- Every field has a plain-language label and concise helper text when needed.
- Forms use purpose-specific sections, not generic payload editors.

### Safety

- Changing a target, faculty, department, term, locale, filter, tab, route, or
  browser page with unsaved changes prompts Save draft, Discard, or Stay.
- Conflict recovery preserves the editor's local work and offers a clear next
  action. It must not only instruct the editor to reload.
- User-facing errors are localized and safe. Technical details are logged, not
  displayed to staff.

## Information Architecture

The redesigned sidebar is task-oriented:

| Area | Contents | Primary roles |
| --- | --- | --- |
| Home | Drafts, scheduled releases, incomplete translations, recent changes, inbox counts | All scoped roles |
| Content | Homepage, pages, News desk, Research, Faculties | Editors and scoped faculty editors |
| Media | Approved media and contextual asset selection | Editors and scoped faculty editors |
| Inbox | Contact messages and form submissions | Editors and administrators |
| Site settings | Menus, global settings, integrations | Editors and administrators |
| Administration | Users, roles, audit logs, security | Super administrators |
| My account | Profile and two-factor authentication | All roles |

Faculty management is one workspace. Super administrators and editors select a
faculty; a faculty editor is fixed to their authorized faculty. Existing URLs
may redirect to the unified workspace for continuity.

## Shared UI System

Every priority editor uses the same visual frame:

1. Workspace header: title, content type, public state, draft state, language
   completeness, last save, and editor.
2. Main tabs: content, Arabic, English, media, and Advanced where applicable.
3. Sticky action bar: Save draft, Save and preview, Publish now, Schedule,
   Cancel schedule, Unpublish, and More actions.
4. Plain-language confirmation dialogs for high-impact actions.
5. Localized table patterns: human title, status, owner, updated time,
   scheduled time, useful filters, empty states, and row actions.

Shared implementation units should be introduced before copying behavior into
individual resources:

- `EditorialStatusPanel`
- `PublicationWorkflowActions`
- `LocalizedEditorialTabs`
- `AdvancedEditorialSettings`
- `UnsavedChangesGuard`
- `EditorialIndexTable`
- `MediaAssetField`

Names may change during implementation, but the behavior must be shared rather
than copied into each Filament resource.

## Priority Workspace Designs

### News Desk

The News Desk provides clear entry points for News, Announcements, Events,
Scheduled content, and Drafts awaiting review.

The normal editor includes only:

- content type and category;
- Arabic title, summary, and body;
- English title, summary, and body;
- cover image;
- relevant event information when the type is Event; and
- the shared publishing panel.

It does not show raw status values, publication timestamps, scope slugs, sort
order, legacy paths, or SEO fields by default.

Before implementation, confirm whether News, Announcements, and Events share a
canonical record model or require separate event-specific storage. Do not fake
event registration, capacity, venue, or date semantics inside an unrelated page
payload.

### Research Workspace

Replace the broad technical target selector with task cards for:

- Research landing page;
- Publications;
- Projects;
- Centers;
- Conferences;
- Researchers and experts; and
- Policies and library content.

Each task opens a focused editor with only the fields required for that content
type and the shared publishing panel.

### Job Board Workspace

Present jobs as vacancies, not as anonymous page-payload items. The normal
editor includes:

- job title;
- hiring faculty or department;
- location and employment type;
- closing date;
- description and requirements;
- application method;
- Arabic and English content; and
- the shared publishing panel.

### Faculty Study Plan Workspace

The staff journey is:

1. Select faculty.
2. Select department.
3. Select academic year or term.
4. View courses in that term.
5. Add or edit one course.

The normal course editor includes course code, title, credits, requirement type,
required/elective state, prerequisites selected by course name and code,
instructor, description, optional lessons, and related documents.

The system derives reverse prerequisite relationships. Editors do not manage
course IDs, prerequisite IDs, reverse graph IDs, department IDs, or instructor
profile slugs.

## Two-Day Implementation Sequence

### Day 1: Safety And Shared Foundation

1. Confirm canonical storage and ownership for News, Announcements, Events,
   Research, Jobs, and Study Plans.
2. Fix or hide unsupported News scheduling before staff can select it.
3. Prevent scheduled CMS drafts from being silently superseded.
4. Reauthorize scheduled publication at execution time.
5. Implement central unsaved-change protection for priority editors.
6. Implement shared status/action UI, confirmation dialogs, and safe localized
   notifications.
7. Implement explicit Arabic and English tabs with RTL/LTR direction and
   language-completion indicators.
8. Apply the shared UI system to the News editor as the reference workflow.

### Day 2: Priority Staff Flows

1. Finish the News Desk workflow, including draft, preview, publish, schedule,
   cancel schedule, and unpublish.
2. Apply focused publishing UI to announcements and events after their
   canonical storage decision.
3. Replace the Research target-first experience with focused task entry points.
4. Create the job vacancy workflow.
5. Replace the study-plan giant nested editor with faculty, department, term,
   and course-focused editing.
6. Complete Arabic and English responsive QA for the priority screens.
7. Add targeted workflow and authorization tests.

## Acceptance Scenarios

The first release is not accepted until a non-technical staff user can:

1. Create a bilingual news item, save a draft, preview it, publish it, and see
   the result clearly.
2. Schedule a news item and verify it becomes public at the selected time.
3. Edit scheduled content without silently cancelling its schedule.
4. Create an announcement or event without raw IDs, slugs, or database status
   values.
5. Publish research content through a focused task-specific editor.
6. Create, publish, close, and unpublish a job vacancy safely.
7. Add or edit a course using human course and instructor selectors.
8. Work in Arabic and English without incorrect text direction or ambiguous
   translation state.
9. Navigate away from dirty content without accidental data loss.
10. See only actions permitted by the current role.

## Verification Requirements

- Focused PHPUnit and Livewire tests for each publishing transition.
- Tests for scheduled News publication, cancellation, role checks, and draft
  conflict behavior.
- Tests for unsaved-state protection where practical, followed by browser QA.
- Manual browser QA in Arabic RTL and English LTR on desktop and mobile.
- Keyboard checks for tabs, forms, dialogs, tables, filters, and media fields.
- No raw exception messages, inert controls, broken assets, or untranslated
  staff-facing labels.
- `./vendor/bin/pint --test`, scoped tests, relevant authorization tests, and
  `git diff --check` must pass before each commit.

## Working Rules

- Work only from `ux/admin-redesign-foundation`.
- Do not reuse the rejected Pilot 1 code as a pattern.
- Preserve valid service-layer, authorization, audit, cache, and public-route
  behavior unless a reviewed change is necessary to meet a product rule.
- Do not create migrations or alter persisted data without an explicit domain
  decision and migration plan.
- Keep commits small and independently testable.
- Do not claim the whole admin panel is redesigned after this priority release.
