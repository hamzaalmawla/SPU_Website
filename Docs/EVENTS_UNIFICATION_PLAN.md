# Events, Announcements and News: Unification Plan

Status: agreed with the product owner (2026-10-22), not yet built.

## Problem

Events live in three unrelated places:

| What | Stored in | Edited in |
|---|---|---|
| News, Announcements, Agreements, Society's Events | `news_articles` + category | News Articles editor |
| Calendar events with registration | JSON list in the CMS target `news.events` | Events page editor (`ManageEvents`) |
| Research conferences | JSON list in the CMS target `research.conferences` | Research editor |

An editor has to know which screen an event belongs to, display-only events and registration events never meet, and an announcement cannot point at either.

## Decisions

1. Everything is written in the **article editor**. The category decides what extra fields appear.
2. Categories:

   | Category | Registration | Calendar |
   |---|---|---|
   | News | - | no |
   | Announcement | - | no; can link to an event or conference |
   | Agreement | - | no (unchanged, plain article) |
   | Society's Events (service events the university does, display only) | never | when the editor ticks "Add to calendar" |
   | Events (type: Event / Conference / Seminar) | editor chooses yes or no | when the editor ticks "Add to calendar" |

3. An event has one or more **sessions** with separate dates (e.g. 24/10, 31/10, 2/11). **One registration covers all sessions.**
4. **Speakers** are optional, per event or per session. Each speaker is either:
   - **From the university**: chosen from a searchable dropdown of faculty member profiles, **grouped by faculty**. Name, photo and title are read live from the profile and the name links to it.
   - **From outside**: name, title, organisation, photo, short bio typed in (AR/EN).
5. **Research conferences stay under Research.** The research editor gets the same "Add to calendar" checkbox, and announcements can link to them.
6. **Faculty editors cannot create events.** Choosing Events or Society's Events requires the existing `events.update` permission, which the default `faculty_editor` role does not hold.
7. No waitlist, no confirmation emails. A full event shows "Full".
8. Taken from suggestions: Add to calendar (.ics), event language, certificate of attendance.

## Data model

New tables (all names to be checked against existing conventions during step 1):

- `news_article_events` (1:1 with `news_articles`)
  - `news_article_id`, `kind` (`event|conference|seminar|society`), `requires_registration`, `show_on_calendar`
  - `registration_form_key` (a `form_definitions` key, picked with `FormPicker`), `capacity` (nullable), `prior_registrations` (seats taken before the move, default 0), `registration_closes_at` (nullable; otherwise closes when the last session ends)
  - `language` (`ar|en|both`), `has_certificate`, `invitation_media_id`, `map_url`, `online_url`, `contact_email`, `contact_phone`
  - `audience` (JSON list: `students|staff|public|alumni`)
- `news_article_event_translations`: `locale`, `location`, `organizer`, `audience_note`, `certificate_note`, `results`
- `news_article_event_sessions`: `news_article_event_id`, `date`, `starts_at_time`, `ends_at_time` (nullable), `sort_order`
- `news_article_event_session_translations`: `locale`, `title` (nullable), `location` (nullable, falls back to the event's)
- `news_article_event_speakers`: `news_article_event_id`, `session_id` (nullable = whole event), `faculty_member_id` (nullable = outside speaker), `photo_media_id`, `sort_order`
- `news_article_event_speaker_translations`: `locale`, `name`, `title`, `organization`, `bio` (outside speakers only)

Announcement link, on `news_articles`: `related_kind` (`article|conference|url`, nullable), `related_article_id`, `related_conference_id`, `related_url`; and `cta_label` on `news_article_translations`.

Rules:
- Event start = first session, end = last session's end (or end of that day). After that the event is "past" and registration closes.
- Seats left = `capacity - prior_registrations - submissions for this event`. The editor no longer types a "registered" number.
- Society's Events keep sessions, location and calendar fields; registration fields are hidden and forced off.
- The announcement button text follows the target: "Register" while registration is open, "Event details" after; an editor-set label overrides.

## Admin

- Category radio unchanged in place; picking Events or Society's Events reveals an "Event details" tab: type, registration toggle, calendar checkbox, sessions repeater, location, audience, language, certificate, invitation image, contact, speakers repeater (radio: university / outside).
- Speaker dropdown: faculty members grouped by faculty, searchable in AR and EN (`PersonName::matchRank()`); a member linked to a leadership profile appears once.
- Announcement: "Related link" section, shown only for the Announcement category, all optional.
- Articles list: filter by event type, column for seats taken / capacity linking to the form submissions inbox.
- `ManageEvents` keeps the Events page texts (titles, labels) but loses the events list once step 5 runs.

## Public

- **Event with registration** (`/news/{slug}`): cover, type label, session timeline, location + map/online link, audience, language, certificate, seats left, Register button, speakers, invitation image (opens larger), Add to calendar, body; after the event: results and gallery.
- **Event without registration / Society's Events**: the normal article page plus an info box (sessions, location) and Add to calendar while upcoming.
- **Registration**: `/news/{slug}/register` using the existing dynamic form submission path, with the event context switched from the JSON id to the article.
- **Add to calendar**: `/news/{slug}/calendar.ics`, one file with every session.
- **Calendar** (`/news/events`): article events and Society's Events with the checkbox, plus research conferences with the checkbox; one entry per session day; filter by type.
- **Events list** (`/news/events-list`): Events category, upcoming and past.
- Homepage `events_activities` reads from the new source.
- AR/EN, RTL/LTR, keyboard access, SEO (Event structured data) on every page above.

## Moving existing data

- Published events in `news.events` become Events articles (type Event, registration on if they had a form, calendar on). Their `registered` value becomes `prior_registrations`.
- The bundled fallback events in `resources/data/news-events-content.json` are sample data and are **not** imported.
- `/news/events-list/{id}`, `/news/events-list/register?event={id}` and `/news/events-list/past` redirect (301) to the new pages.
- Existing submissions keep pointing at their event through a mapping recorded by the move.

## Steps (one PR into `dev` each)

1. Schema, models, DTOs, service contracts; permission gate; admin "Event details" tab with sessions and speakers. Tests.
2. Public event page, info box for display-only events, registration on articles, .ics. Tests.
3. Calendar and events list on the new source; research conference calendar checkbox; homepage section. Tests.
4. Announcement related link (admin + public button). Tests.
5. Move existing events, redirects, retire the old events list in `ManageEvents`; update `FRONTEND_IMPORT_TRACKER.md`, the parity matrix and the CMS user guide.

## Open assumption

Leadership profiles that are not linked to a faculty member have no faculty, so the speaker dropdown would not list them. If they should be choosable, they need their own group (for example "University leadership"). To confirm before step 1.
