# Requirements Document

## Introduction

This feature replaces the minimal skeleton Blade templates for the SPU public homepage with
production-quality, design-faithful views that fully consume the existing DTO-driven backend
payloads. The backend controller, service interfaces, DTOs, routes, middleware, and i18n keys
are already in place. The work is entirely in the view layer: `layouts/public.blade.php`,
`public/home.blade.php`, and the section partials under `public/partials/`.

The homepage is a fixed 10-section CMS page rendered at `GET /{locale}` (locale = `ar` | `en`).
All content is CMS-driven via `HomepageSectionDataDTO`; no content may be hardcoded in Blade.
The layout must support RTL (Arabic, default) and LTR (English) without duplication of markup.

---

## Glossary

- **Blade_Layout**: `resources/views/layouts/public.blade.php` — the shared HTML shell for all public pages.
- **Home_View**: `resources/views/public/home.blade.php` — the homepage content view.
- **Section_Partial**: `resources/views/public/partials/homepage-section.blade.php` — the per-section renderer.
- **HomepageDTO**: The top-level DTO passed as `$homepage`; contains `locale`, `direction`, and `sections[]`.
- **HomepageSectionDTO**: One section entry; exposes `key`, `isEnabled`, `sortOrder`, and `payload` (locale-resolved `HomepageSectionDataDTO`).
- **HomepageSectionDataDTO**: The locale-resolved payload for a section (fields: `title`, `subtitle`, `eyebrow`, `badge`, `summary`, `body`, `backgroundImageUrl`, `primaryAction`, `secondaryAction`, `sectionAction`, `stats[]`, `items[]`, `articles[]`, `researchItems[]`, `events[]`, `footerColumns[]`, `contactLinks[]`, `socialLinks[]`, `content[]`).
- **NavigationPayloadDTO**: Passed as `$navigation`; contains `header`, `footer`, `utility` trees, `applyCta`, `studentPortalUrl`, `staffAccessUrl`, `emergencyNotice`, `footerSettings`, `socialContact`.
- **PublicSettingsDTO**: Passed as `$settings`; contains `applyCta`, `emergencyNotice`, `footer`, `socialContact`, `studentPortalUrl`, `staffAccessUrl`.
- **PageSeoDTO**: Passed as `$seo`; contains `title`, `metaDescription`, `ogTitle`, `ogDescription`, `ogImage`, `canonicalUrl`, `hreflang[]`, `robots`.
- **LanguageSwitchLinkDTO**: One entry in `$languageSwitch`; contains `locale`, `label`, `url`, `isCurrent`.
- **Section_Key**: One of the 10 approved keys: `hero`, `hero_stats`, `academic_faculties`, `achievements_highlights`, `university_news`, `research_studies`, `events_activities`, `medical_facilities_services`, `bottom_stats`, `footer`.
- **RTL_Context**: When `$direction === 'rtl'` (Arabic locale).
- **LTR_Context**: When `$direction === 'ltr'` (English locale).
- **Preview_Banner**: The amber warning bar shown when `$isPreview === true`.
- **Emergency_Banner**: The red alert bar shown when `$navigation->emergencyNotice->isEnabled === true`.

---

## Requirements

### Requirement 1: Shared Layout Shell

**User Story:** As a site visitor, I want a consistent page shell with correct language direction,
SEO metadata, and navigation on every public page, so that the site feels coherent and is
accessible in both Arabic and English.

#### Acceptance Criteria

1. THE Blade_Layout SHALL set the `<html>` element's `lang` attribute to `$locale` and `dir`
   attribute to `$direction`.
2. THE Blade_Layout SHALL render a `<title>` tag using `$seo->title`.
3. WHEN `$seo->metaDescription` is not null, THE Blade_Layout SHALL render a `<meta name="description">` tag with that value.
4. WHEN `$seo->canonicalUrl` is not null, THE Blade_Layout SHALL render a `<link rel="canonical">` tag with that value.
5. WHEN `$seo->hreflang` is not empty, THE Blade_Layout SHALL render one `<link rel="alternate" hreflang>` tag per entry.
6. WHEN `$seo->ogImage` is not null, THE Blade_Layout SHALL render an `<meta property="og:image">` tag with that value.
7. THE Blade_Layout SHALL load the compiled Vite assets (`resources/css/app.css`, `resources/js/app.js`) using the `@vite` directive only when the build manifest or hot file exists.
8. WHEN `$isPreview` is `true`, THE Blade_Layout SHALL render the Preview_Banner above all other content.
9. WHEN `$navigation->emergencyNotice->isEnabled` is `true`, THE Blade_Layout SHALL render the Emergency_Banner displaying `emergencyNotice->title` and, when present, `emergencyNotice->message`.
10. THE Blade_Layout SHALL render a `<header>` containing the site logo/name linked to `/{$locale}`, the utility navigation items from `$navigation->utility->items`, and the language switcher from `$languageSwitch`.
11. THE Blade_Layout SHALL render the primary navigation from `$navigation->header->items` inside the `<header>`.
12. WHEN `$navigation->applyCta` is not null, THE Blade_Layout SHALL render the Apply CTA button using `applyCta->label` and `applyCta->url`.
13. WHEN `$navigation->studentPortalUrl` is not null, THE Blade_Layout SHALL render a student portal link.
14. WHEN `$navigation->staffAccessUrl` is not null, THE Blade_Layout SHALL render a staff access link.
15. THE Blade_Layout SHALL render a `<footer>` that uses the `footer` Section_Key payload when `$homepageFooterSection` is not null, and falls back to `$navigation->footerSettings` and `$navigation->socialContact` otherwise.
16. WHILE `$direction` is `'rtl'`, THE Blade_Layout SHALL apply RTL-compatible layout classes (e.g. `dir="rtl"` on the root element and appropriate Tailwind RTL utilities) so that text and flex direction are correct.
17. THE Blade_Layout SHALL render the `<main>` content area via `@yield('content')`.

---

### Requirement 2: Homepage Section Loop

**User Story:** As a site visitor, I want to see all enabled homepage sections rendered in their
configured order, so that the page reflects the content published by the CMS.

#### Acceptance Criteria

1. THE Home_View SHALL iterate over `$homepage->sections` in their existing `sortOrder` sequence.
2. WHEN a section's `key` equals `'footer'`, THE Home_View SHALL skip that section in the main content loop.
3. WHEN a section's `isEnabled` is `false`, THE Home_View SHALL skip that section.
4. FOR EACH remaining section, THE Home_View SHALL delegate rendering to the Section_Partial, passing `$section` and `$locale`.
5. THE Home_View SHALL NOT contain any hardcoded content strings.

---

### Requirement 3: Hero Section

**User Story:** As a site visitor, I want to see a full-width hero section with the university's
headline, supporting text, and call-to-action buttons, so that I immediately understand the
institution's identity.

#### Acceptance Criteria

1. WHEN the Section_Partial receives a section with `key === 'hero'`, THE Section_Partial SHALL render a full-width hero block.
2. WHEN `$section->payload->eyebrow` is not null, THE Section_Partial SHALL render it as a visually distinct eyebrow label above the title.
3. WHEN `$section->payload->badge` is not null, THE Section_Partial SHALL render it as a badge element.
4. THE Section_Partial SHALL render `$section->payload->title` as the primary `<h1>` heading.
5. WHEN `$section->payload->subtitle` is not null, THE Section_Partial SHALL render it below the title.
6. WHEN `$section->payload->summary` is not null, THE Section_Partial SHALL render it as the body copy; WHEN `summary` is null and `body` is not null, THE Section_Partial SHALL render `body` instead.
7. WHEN `$section->payload->primaryAction` is not null, THE Section_Partial SHALL render a primary CTA link using `primaryAction->label` and `primaryAction->url`.
8. WHEN `$section->payload->secondaryAction` is not null, THE Section_Partial SHALL render a secondary CTA link using `secondaryAction->label` and `secondaryAction->url`.
9. WHEN `$section->payload->backgroundImageUrl` is not null, THE Section_Partial SHALL apply it as a CSS background image on the hero image panel.
10. WHEN a CTA action's `target` is not null, THE Section_Partial SHALL set the `target` attribute on the rendered anchor element.

---

### Requirement 4: Stats Sections (hero_stats and bottom_stats)

**User Story:** As a site visitor, I want to see key university statistics displayed as a grid of
metric cards, so that I can quickly grasp the institution's scale and achievements.

#### Acceptance Criteria

1. WHEN the Section_Partial receives a section with `key === 'hero_stats'` or `key === 'bottom_stats'`, THE Section_Partial SHALL render a stats grid section.
2. THE Section_Partial SHALL render `$section->payload->title` as the section heading.
3. FOR EACH item in `$section->payload->stats`, THE Section_Partial SHALL render a card displaying `prefix + value + suffix` as the metric and `label` as the descriptor.
4. WHEN a stat item's `helperText` is not null, THE Section_Partial SHALL render it as supplementary text on the card.
5. WHEN `$section->payload->stats` is empty, THE Section_Partial SHALL render the section heading only, without an empty grid.

---

### Requirement 5: Feature Card Sections (academic_faculties, achievements_highlights, medical_facilities_services)

**User Story:** As a site visitor, I want to browse faculties, achievements, and medical services
as a grid of cards with titles, summaries, and optional actions, so that I can explore the
university's offerings.

#### Acceptance Criteria

1. WHEN the Section_Partial receives a section with `key` equal to `'academic_faculties'`, `'achievements_highlights'`, or `'medical_facilities_services'`, THE Section_Partial SHALL render a feature card grid section.
2. THE Section_Partial SHALL render `$section->payload->title` as the section heading.
3. WHEN `$section->payload->subtitle` is not null, THE Section_Partial SHALL render it below the heading.
4. WHEN `$section->payload->sectionAction` is not null, THE Section_Partial SHALL render a "view all" link using `sectionAction->label` and `sectionAction->url`.
5. FOR EACH item in `$section->payload->items`, THE Section_Partial SHALL render a card displaying `item['title']`.
6. WHEN an item contains a non-empty `summary` key, THE Section_Partial SHALL render it as card body text.
7. WHEN an item contains a non-empty `typeTag`, `type_tag`, or `accent` key, THE Section_Partial SHALL render it as a badge on the card.
8. WHEN an item contains a non-empty `metric` key, THE Section_Partial SHALL render it as a metric label on the card.
9. WHEN an item contains an `action` key that is an array with non-empty `label` and `url`, THE Section_Partial SHALL render a card-level action link.
10. WHEN `$section->payload->items` is empty and `$section->payload->featuredItems` is not empty, THE Section_Partial SHALL render `featuredItems` using `HomepageFeatureItemDTO->title` and `summary`.
11. WHEN `$section->payload->stats` is not empty, THE Section_Partial SHALL render a supplementary stats row below the card grid.

---

### Requirement 6: University News Section

**User Story:** As a site visitor, I want to see recent news articles as cards with titles,
excerpts, and dates, so that I can stay informed about university events and announcements.

#### Acceptance Criteria

1. WHEN the Section_Partial receives a section with `key === 'university_news'`, THE Section_Partial SHALL render a news card grid.
2. THE Section_Partial SHALL render `$section->payload->title` as the section heading.
3. WHEN `$section->payload->sectionAction` is not null, THE Section_Partial SHALL render a "view all news" link.
4. FOR EACH item in `$section->payload->articles`, THE Section_Partial SHALL render a card displaying `article->title`.
5. WHEN `article->excerpt` is not null, THE Section_Partial SHALL render it as card body text.
6. WHEN `article->publishedAt` is not null, THE Section_Partial SHALL render it as a date label.
7. WHEN `article->categoryLabel` is not null, THE Section_Partial SHALL render it as a category tag.
8. WHEN `article->badgeTag` is not null, THE Section_Partial SHALL render it as a badge.
9. WHEN `article->url` is not null, THE Section_Partial SHALL render a link to the article using `article->title` as the link label.
10. WHEN `$section->payload->articles` is empty, THE Section_Partial SHALL render the section heading only, without an empty grid.

---

### Requirement 7: Research Studies Section

**User Story:** As a site visitor, I want to see featured research items with authors and
publication dates, so that I can discover the university's academic output.

#### Acceptance Criteria

1. WHEN the Section_Partial receives a section with `key === 'research_studies'`, THE Section_Partial SHALL render a research card grid.
2. THE Section_Partial SHALL render `$section->payload->title` as the section heading.
3. WHEN `$section->payload->sectionAction` is not null, THE Section_Partial SHALL render a "view all research" link.
4. FOR EACH item in `$section->payload->researchItems`, THE Section_Partial SHALL render a card displaying `item->title`.
5. WHEN `item->summary` is not null, THE Section_Partial SHALL render it as card body text.
6. WHEN `item->publishedAt` is not null, THE Section_Partial SHALL render it as a date label.
7. WHEN `item->categoryLabel` is not null, THE Section_Partial SHALL render it as a category tag.
8. WHEN `item->authors` is not empty, THE Section_Partial SHALL render the author list as a delimited string on the card.
9. WHEN `item->url` is not null, THE Section_Partial SHALL render a link to the research item.

---

### Requirement 8: Events and Activities Section

**User Story:** As a site visitor, I want to see upcoming events with dates and locations alongside
a calendar highlights sidebar, so that I can plan my attendance.

#### Acceptance Criteria

1. WHEN the Section_Partial receives a section with `key === 'events_activities'`, THE Section_Partial SHALL render an events section with a main event list and a sidebar.
2. THE Section_Partial SHALL render `$section->payload->title` as the section heading.
3. FOR EACH item in `$section->payload->events`, THE Section_Partial SHALL render a card displaying `event->title`.
4. WHEN `event->startsAt` is not null, THE Section_Partial SHALL render it as the event date.
5. WHEN `event->timeLabel` is not null, THE Section_Partial SHALL render it as a time descriptor.
6. WHEN `event->location` is not null, THE Section_Partial SHALL render it as the event location.
7. WHEN `event->summary` is not null, THE Section_Partial SHALL render it as card body text.
8. WHEN `event->url` is not null, THE Section_Partial SHALL render a link to the event detail page.
9. THE Section_Partial SHALL render a sidebar using `$section->payload->content['calendarHighlights']`; FOR EACH highlight entry, THE Section_Partial SHALL render `highlight['label']` and, when present, `highlight['date']`.
10. WHEN `$section->payload->content['calendarHighlights']` is empty or absent, THE Section_Partial SHALL render the sidebar container without highlight entries.

---

### Requirement 9: Footer Section

**User Story:** As a site visitor, I want a footer with the university's brand identity, navigation
links, contact details, and social links, so that I can find key information and navigate from
any page.

#### Acceptance Criteria

1. WHEN `$homepageFooterSection` is not null, THE Blade_Layout SHALL render the footer using `$homepageFooterSection->payload` (the `footer` Section_Key payload).
2. WHEN `$homepageFooterSection` is null, THE Blade_Layout SHALL render the footer using `$navigation->footerSettings` and `$navigation->socialContact` as the fallback source.
3. WHEN the footer payload's `content['brandBlock']['logoUrl']` is not null, THE Blade_Layout SHALL render the logo image with an appropriate `alt` attribute.
4. THE Blade_Layout SHALL render the brand title from `content['brandBlock']['title']` or fall back to `$seo->title`.
5. WHEN `content['brandBlock']['body']` is not null, THE Blade_Layout SHALL render it as the brand summary paragraph.
6. FOR EACH entry in `footerColumns`, THE Blade_Layout SHALL render a column with `column->title` and its `links[]`.
7. WHEN `contactLinks` is not empty, THE Blade_Layout SHALL render each contact link as `label: value`.
8. WHEN `socialLinks` is not empty, THE Blade_Layout SHALL render each social link as an anchor using `platform` as the label.
9. WHEN `content['legalLinks']` is not empty, THE Blade_Layout SHALL render each legal link as an anchor.
10. THE Blade_Layout SHALL render a copyright bar using `content['copyrightText']` or fall back to `$navigation->footerSettings->copyrightText`.
11. WHEN the fallback footer path is active and `$navigation->footerSettings->mapEmbedUrl` is not null, THE Blade_Layout SHALL render a campus map link.

---

### Requirement 10: Bilingual and Directional Rendering

**User Story:** As an Arabic-speaking visitor, I want the page to render in RTL with Arabic text,
and as an English-speaking visitor, I want LTR with English text, so that both audiences receive
a native reading experience.

#### Acceptance Criteria

1. THE Blade_Layout SHALL derive text direction exclusively from `$direction` (passed by the controller from `$homepage->direction`) and SHALL NOT hardcode any direction value.
2. WHEN `$direction` is `'rtl'`, THE Blade_Layout SHALL apply Tailwind RTL modifier classes (e.g. `rtl:space-x-reverse`, `rtl:flex-row-reverse`) where flex or spacing direction matters.
3. THE Section_Partial SHALL NOT contain any locale-conditional content branches; all locale resolution SHALL have been performed by the service layer before the DTO reaches the view.
4. THE Blade_Layout SHALL set the `Content-Language` response header indirectly via the `LocaleSetterMiddleware` already in the middleware stack; no header manipulation SHALL occur in Blade templates.
5. WHEN `$locale` is `'ar'`, THE Blade_Layout SHALL use `__('public.*')` translation keys for all UI chrome labels (navigation headings, portal links, section labels, etc.).
6. WHEN `$locale` is `'en'`, THE Blade_Layout SHALL use the same `__('public.*')` translation keys, resolved to English by Laravel's locale system.

---

### Requirement 11: SEO and Structured Metadata

**User Story:** As a search engine crawler, I want accurate meta tags, canonical URLs, and
hreflang alternates on every page, so that the site is indexed correctly in both Arabic and
English.

#### Acceptance Criteria

1. THE Blade_Layout SHALL render `<meta name="robots">` using `$seo->robots`, defaulting to `'index,follow'` when null.
2. THE Blade_Layout SHALL render `<meta property="og:locale">` using `$locale`.
3. THE Blade_Layout SHALL render `<meta property="og:title">` using `$seo->ogTitle` when not null, falling back to `$seo->title`.
4. WHEN `$seo->ogDescription` is not null, THE Blade_Layout SHALL render `<meta property="og:description">`.
5. THE Blade_Layout SHALL render all hreflang alternate links from `$seo->hreflang` as `<link rel="alternate" hreflang>` elements.
6. THE Blade_Layout SHALL NOT render duplicate `<title>` or `<meta name="description">` tags.

---

### Requirement 12: Preview Mode

**User Story:** As a content editor, I want to see a clearly labelled preview banner when
accessing the homepage via a preview token, so that I know I am viewing unpublished content.

#### Acceptance Criteria

1. WHEN `$isPreview` is `true`, THE Blade_Layout SHALL render the Preview_Banner as the topmost visible element on the page.
2. THE Preview_Banner SHALL display the `__('public.preview_mode')` translation string.
3. WHEN `$preview` is set and `$preview->targetType` is not null, THE Preview_Banner SHALL display the target type label.
4. WHEN `$preview->expiresAt` is not null, THE Preview_Banner SHALL display the expiry time using `__('public.expires', ['time' => $preview->expiresAt])`.
5. WHEN `$isPreview` is `false`, THE Blade_Layout SHALL NOT render any preview banner markup.

---

### Requirement 13: Asset Pipeline and Performance

**User Story:** As a site visitor, I want pages to load quickly with correctly compiled CSS and
JavaScript, so that the visual design and interactivity work as expected.

#### Acceptance Criteria

1. THE Blade_Layout SHALL load CSS and JS exclusively via the `@vite` directive; no `<link>` or `<script>` tags for app assets SHALL be hardcoded.
2. THE Blade_Layout SHALL include a `<link rel="preconnect">` for the Bunny Fonts CDN before the font stylesheet link.
3. WHEN neither `public/build/manifest.json` nor the Vite hot file exists, THE Blade_Layout SHALL NOT emit broken asset tags.
4. THE Section_Partial SHALL NOT inline any `<style>` blocks or `<script>` blocks; all styling SHALL be via Tailwind utility classes and all interactivity via compiled JS.

---

### Requirement 14: Accessibility

**User Story:** As a visitor using assistive technology, I want semantic HTML landmarks and
descriptive labels on interactive elements, so that I can navigate the page with a screen reader.

#### Acceptance Criteria

1. THE Blade_Layout SHALL wrap the primary navigation in a `<nav>` element with an `aria-label` attribute.
2. THE Blade_Layout SHALL wrap the main content in a `<main>` element.
3. THE Blade_Layout SHALL wrap the footer content in a `<footer>` element.
4. THE Section_Partial SHALL use `<h1>` for the hero section title and `<h2>` for all other section headings.
5. THE Section_Partial SHALL use `<article>` elements for individual cards (news, research, events, faculty, feature items).
6. WHEN an image is rendered (hero background, footer logo), THE Blade_Layout or Section_Partial SHALL provide a non-empty `alt` attribute.
7. WHEN a link opens in a new tab (`target="_blank"`), THE Blade_Layout or Section_Partial SHALL add `rel="noreferrer"` to that anchor.
