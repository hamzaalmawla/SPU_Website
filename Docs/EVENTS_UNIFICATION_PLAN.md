# Events, Announcements and News: Unification Plan

Status: agreed with the product owner (2026-10-22). Steps 1-3 built (see below); steps 4-5 not started.

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
   - **From the university**: chosen from a searchable dropdown: a **University leadership** group first, then faculty member profiles **grouped by faculty**. Name, photo and title are read live from the profile and the name links to it. A leadership profile linked to a faculty member is listed once, as the member.
   - **From outside**: name, title, organisation, photo, short bio typed in (AR/EN).
5. **Research conferences stay under Research.** The research editor gets the same "Add to calendar" checkbox, and announcements can link to them.
6. **Faculty editors cannot create events.** Choosing Events or Society's Events requires the existing `events.update` permission, which the default `faculty_editor` role does not hold.
7. No waitlist, no confirmation emails. A full event shows "Full".
8. Taken from suggestions: Add to calendar (.ics), event language, certificate of attendance.

## Data model

New tables (migration `2026_10_22_000003_create_news_article_events_tables.php`, which also adds the `events` category with type `event`):

- `news_article_events` (1:1 with `news_articles`)
  - `news_article_id`, `kind` (`event|conference|seminar|society`), `requires_registration`, `show_on_calendar`
  - `form_key` (a `form_definitions` key, picked with `FormPicker`), `capacity` (nullable), `prior_registrations` (seats taken outside the site, default 0), `registration_closes_at` (nullable; otherwise closes when the last session ends)
  - `starts_at`, `ends_at`: first session start and last session end in UTC, for calendar and listing queries
  - `language` (`ar|en|both`), `has_certificate`, `invitation_media_id`, `map_url`, `online_url`, `contact_email`, `contact_phone`
  - `audience` (JSON list: `students|staff|public|alumni`)
- `news_article_event_translations`: `locale`, `location`, `organizer`, `audience_note`, `certificate_note`, `results`
- `news_article_event_sessions`: `news_article_event_id`, `date`, `starts_at`, `ends_at` (nullable), `title_ar|en`, `location_ar|en` (falls back to the event's), `sort_order`. Times are what the editor typed, in the admin time zone; rows are stored in date order.
- `news_article_event_speakers`: `news_article_event_id`, `news_article_event_session_id` (nullable = whole event), `faculty_member_id` or `person_id` (both null = outside speaker), `name|title|organization|bio_ar|en` and `photo_media_id` (outside speakers only), `sort_order`

Session and speaker text uses `_ar`/`_en` columns, like `news_article_attachments` labels; event-level text uses a translations table, like the article.

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

## Step 1 as built

- `NewsArticleEventCmsServiceInterface` / `NewsArticleEventCmsService`: normalizes the `event` part of the article payload, validates it on publish, writes it on publish (`persist`), reads it back, and builds the speaker dropdown.
- `NewsArticleCmsService` carries `event` in the payload, merges its errors into publish validation, persists it inside the publish transaction, and requires `events.update` whenever the old or new category is Events or Society's Events.
- `NewsArticleResource`: "Event details" tab (visible for Events and Society's Events) with type, registration, calendar, language, certificate, sessions (each with optional speakers), place, audience, registration form and seats, event speakers, invitation and contact, and after-event results. The Events and Society's Events choices are hidden from users without `events.update`.
- `FormDefinitionService::usage` counts event articles, so a form picked by an event cannot be deleted.
- Tests: `tests/Feature/NewsArticleEventCmsTest.php`.

## Step 2 as built

- `NewsArticleEventServiceInterface` / `NewsArticleEventService`: builds `NewsArticleEventDTO` (sessions, speakers, registration state `none|open|full|closed`, seats left) from the published tables, or from a draft payload for the preview (which never offers registration). It also counts registrations and writes the `.ics` file.
- A university speaker is read from the profile at render time (name, title, photo, link); a hidden or deleted profile drops the speaker. A member linked to a leadership profile links to the leadership address.
- `NewsArticleDTO::$event` carries the event on the article page (`NewsService::mapArticle`, detail pages only) and in the preview (`NewsArticleCmsService::buildPreview`).
- Article page: event summary above the cover (sessions, place, map/online link, audience, organizer, language, certificate, Register / full / closed, seats left, Add to calendar) and details below the body (speakers, invitation, contact, results once the event is over). Event JSON-LD (`StructuredDataServiceInterface::newsEvent`) with one sub-event per session.
- `GET /{locale}/news/{article}/register` (`public.news.register`, not page-cached): the dynamic form with source `news-articles` and the article ID as the event ID. `DynamicFormSubmissionService` refuses it when the event is full, closed, past, or the form differs, and one email registers once. Seats left = capacity − prior registrations − site registrations.
- `GET /{locale}/news/{article}/calendar.ics`: one VEVENT per session in UTC; 404 once the event is over.
- The article page is page-cached for up to 5 minutes, so it can show Register shortly after the last seat goes; the registration page and the submission check are always live.
- Tests: `tests/Feature/NewsArticleEventPublicTest.php`.

## Step 3 as built

- `NewsArticleEventServiceInterface::listingEvents()` (Events category, one entry per event) and `calendarEntries()` (Events and Society's Events with "Add to calendar", one entry per session) return `NewsEventDTO`, so the existing list, calendar and homepage views render them unchanged. Both are cached with the news tag for 5 minutes and cleared on publish.
- `NewsService::listNewsEvents()` (events list, news index, homepage `events_activities`) returns the Events page list followed by event articles. `findNewsEvent()` stays on the page list only, so an article event can only register as `news-articles`.
- Calendar (`getNewsEventCalendar`): page list events, marked article sessions, and research conferences marked for the calendar; a type filter (Event, Conference, Seminar, Society's Event, Research conference) shows only the types present. Filtered and month views are `noindex,follow`.
- Events list category filter: the page's own categories plus the types of published event articles.
- Research conferences (`research.conferences`, upcoming and past, each language): "Add to calendar", start date and end date. `ResearchPageServiceInterface::calendarConferences()` places them on each day (at most 31). The text date stays what the conference page shows.
- Tests: `tests/Feature/NewsEventsCalendarArticlesTest.php`.
