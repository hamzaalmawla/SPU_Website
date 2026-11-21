# Implementation Plan: Homepage Blade Integration

## Overview

Port the production-quality static Vite + Alpine frontend (`c:\Users\hamza\Spu-Website`) into
the Laravel Blade view layer. Work is entirely in the view layer and asset pipeline — no new
models, migrations, or service changes. All content comes from existing DTOs; no strings are
hardcoded in Blade.

---

## Tasks

- [x] 1. Copy static assets into the Laravel project
  - Copy font files from `c:\Users\hamza\Spu-Website\public\fonts\` to
    `c:\Users\hamza\SPU_Website\public\fonts\`
    (at minimum: `Hacen Tunisia Regular.ttf`, `Hacen Tunisia Bold Regular.ttf`)
  - Create directory `resources/css/frontend/`
  - Copy the following CSS files verbatim from `c:\Users\hamza\Spu-Website\src\styles\`
    into `resources/css/frontend/`:
    `foundation.css`, `layout.css`, `navigation.css`, `heroes.css`, `home-sections.css`,
    `stats.css`, `reveal.css`, `utilities.css`, `honor-slider.css`
  - Do NOT modify any copied CSS file
  - _Requirements: 13.1, 13.2_

- [x] 2. Configure the asset pipeline
  - [x] 2.1 Update `package.json` to add `alpinejs` and `dayjs` as dependencies
    - Add `"alpinejs": "^3.x"` and `"dayjs": "^1.x"` to `dependencies`
    - _Requirements: 13.1_

  - [x] 2.2 Verify `vite.config.js` includes `resources/css/app.css` and `resources/js/app.js`
    as entry points; add them if missing
    - _Requirements: 13.1_

  - [x] 2.3 Rewrite `resources/css/app.css` with the full import chain
    - Keep `@import 'tailwindcss'` and all `@source` directives
    - Add `@theme` block with design tokens:
      `--font-hacen`, `--color-spu-blue: #202759`, `--color-spu-red: #6f1616`,
      `--color-section: #EAF3FF40`
    - Add `@import` statements for each file in `resources/css/frontend/` in the order
      defined in the design doc
    - _Requirements: 13.1_

- [x] 3. Build the JavaScript entry point (`resources/js/app.js`)
  - [x] 3.1 Create `resources/js/alpine/heroSlider.js`
    - Export `createHeroSlider()` factory function
    - Reads `window.spuHeroImages` array (injected by Blade)
    - Auto-advances `currentIndex` every 5 000 ms; no-op when array is empty
    - _Requirements: 3.1, 3.9_

  - [x] 3.2 Create `resources/js/alpine/statsCounter.js`
    - Export `createStatsCounter()` factory function
    - Uses `IntersectionObserver` (threshold 0.2) to trigger `animateCounter()` on each
      `[data-value]` element within the component root
    - Animates numeric value from 0 to `data-value` over ~1 500 ms
    - Guard: no-op when no `[data-value]` elements found
    - _Requirements: 4.1–4.5_

  - [x] 3.3 Create `resources/js/alpine/facultiesSlider.js`
    - Export `createFacultiesSlider()` factory function
    - Exposes `slideFaculties(direction)` — scrolls `$refs.facultiesTrack` by one card width
    - Exposes `activeFaculty` state for hover dimming
    - _Requirements: 5.1–5.11_

  - [x] 3.4 Create `resources/js/alpine/honorPanel.js`
    - Export `createHonorPanel()` factory function
    - Manages `activeIndex`, `startAuto()` / `stopAuto()` (6 000 ms interval),
      `next()`, `prev()`, `handleManual(action, val)`, `getPos(index)`
    - Reads items from `window.spuHonorItems` injected by Blade
    - _Requirements: 5.1–5.11_

  - [x] 3.5 Port `createResearchSlider()` from the frontend
    - Copy `c:\Users\hamza\Spu-Website\src\features\research-slider.js` to
      `resources/js/alpine/researchSlider.js`
    - Adjust import paths; no logic changes
    - _Requirements: 7.1–7.9_

  - [x] 3.6 Port `createCalendarApp()` from the frontend
    - Copy `c:\Users\hamza\Spu-Website\src\features\calendar.js` to
      `resources/js/alpine/calendarApp.js`
    - Replace `import { mockCalendarEvents }` fallback with an empty array fallback
      (`window.spuEventsData ?? []`) — no static data file dependency
    - Remove the `getAppStore()` / `$store.app.currentLang` references; replace with
      `document.documentElement.lang` for locale detection
    - _Requirements: 8.1–8.10_

  - [x] 3.7 Create `resources/js/alpine/mobileNav.js`
    - Export `createMobileNav()` factory function
    - Manages `openMenu`, `stickyNav`, `mobileNav` state
    - Handles `@scroll.window` sticky logic and `@keydown.escape` close
    - _Requirements: 1.10, 1.11_

  - [x] 3.8 Create `resources/js/alpine/scrollReveal.js`
    - Export `initRevealSections()` function
    - Uses `IntersectionObserver` to add `is-visible` class to `.reveal` elements
    - _Requirements: 13.4_

  - [x] 3.9 Rewrite `resources/js/app.js` to wire everything together
    - Import Alpine from `alpinejs`
    - Import all factory functions from the modules above
    - Register Alpine stores: `heroSlider`, `faculties`, `honorPanel`
    - Register Alpine components: `researchSlider`, `calendarApp`, `mobileNav`
    - Call `initRevealSections()` on `DOMContentLoaded`
    - Set `window.Alpine = Alpine` and call `Alpine.start()`
    - _Requirements: 13.1, 13.4_

- [x] 4. Checkpoint — verify build
  - Run `npm install` then `npm run build` and confirm it exits without errors
  - Confirm `public/build/manifest.json` is generated
  - Confirm font files exist at `public/fonts/Hacen Tunisia Regular.ttf`
  - Ask the user if any build errors need resolving before continuing

- [x] 5. Rewrite the layout shell (`resources/views/layouts/public.blade.php`)
  - [x] 5.1 Replace the `<head>` section
    - Remove the Bunny Fonts `instrument-sans` link
    - Add `<link rel="preconnect">` for Bunny Fonts CDN (kept for fallback stack)
    - Keep the `@vite` directive with its manifest/hot-file guard
    - Ensure all SEO meta tags are present and guarded:
      `<title>`, `robots`, `og:locale`, `og:title`, `og:description` (conditional),
      `og:image` (conditional), `canonical` (conditional), `hreflang` loop
    - _Requirements: 1.1–1.7, 11.1–11.6_

  - [x] 5.2 Replace the `<header>` with the full `site-nav-shell` design
    - Port markup from `c:\Users\hamza\Spu-Website\src\fragments\layout\header.html`
    - Use `x-data="mobileNav()"` (registered Alpine component) for the header shell
    - Replace `x-for` over `$store.navigation.menuItems` with Blade `@foreach` over
      `$navigation->header->items`; render `$item->label`, `$item->resolvedUrl`,
      `$item->isActive`, `$item->openInNewTab`, and child items
    - Replace language toggle button with Blade `@foreach` over `$languageSwitch`
    - Render utility links from `$navigation->utility->items`
    - Render Apply CTA, student portal link, staff access link conditionally
    - Wrap primary nav in `<nav aria-label="{{ __('public.primary_navigation') }}">`
    - Preserve all CSS classes: `site-nav-shell`, `site-nav-brand`, `site-nav-list`,
      `site-nav-item`, `site-nav-link`, `site-nav-dropdown`, `site-nav-mobile-panel`, etc.
    - Keep `@keydown.escape.window` and `@scroll.window` Alpine directives on the header
    - _Requirements: 1.8–1.14, 1.16, 10.1–10.6, 14.1_

  - [x] 5.3 Replace the `<footer>` with the full footer design
    - Port markup from `c:\Users\hamza\Spu-Website\src\fragments\layout\footer.html`
    - When `$homepageFooterSection` is not null: render brand block, footer columns,
      contact links, social links, legal links, copyright, map embed from payload
    - When null: fall back to `$navigation->footerSettings` + `$navigation->socialContact`
    - Social icons: render `<i class="{{ $link->icon ?? '' }}"></i>` from DTO field
    - Preserve all CSS classes from the frontend footer
    - Wrap in `<footer>` element
    - _Requirements: 1.15, 9.1–9.11, 14.3_

  - [x] 5.4 Wrap `@yield('content')` in `<main>` element
    - _Requirements: 1.17, 14.2_

- [x] 6. Rewrite the hero section case in the section partial
  - Replace the current `@case('hero')` block in
    `resources/views/public/partials/homepage-section.blade.php`
  - Port full markup from `c:\Users\hamza\Spu-Website\src\fragments\pages\home\hero.html`
  - Use `<section x-data="heroSlider()" class="home-hero ...">` (Alpine component)
  - Inject hero images via inline script: `<script>window.spuHeroImages = @json($section->payload->content['images'] ?? []);</script>`
  - Render background image `<div>` wrappers with `x-show` and Alpine transition classes
    using a Blade `@foreach` loop over `$section->payload->content['images'] ?? []`,
    with `$loop->index` for the index comparison
  - Render eyebrow, badge, `<h1>` title, subtitle, summary/body, primary CTA, secondary CTA
    — each guarded with `@if` / `@isset`
  - Preserve all CSS classes: `home-hero`, `home-hero__overlay`, `home-hero__ambient`,
    `home-hero__inner`, `home-hero__content`, `home-hero__eyebrow`, `home-hero__title`,
    `home-hero__summary`, `home-hero__actions`, `home-hero__primary-btn`,
    `home-hero__secondary-btn`
  - Add `rel="noreferrer"` when `target` is set on CTA anchors
  - _Requirements: 3.1–3.10, 14.4, 14.7_

- [x] 7. Rewrite the stats section cases in the section partial
  - Replace the current `@case('hero_stats')` / `@case('bottom_stats')` block
  - Port full markup from `c:\Users\hamza\Spu-Website\src\fragments\pages\home\stats.html`
  - Use `<section x-data="statsCounter()" class="stats-section ...">` (Alpine component)
  - Render `stats-shell__grid` only when `$section->payload->stats` is not empty
  - For each `$stat` in `$section->payload->stats`, render a `stats-card` with:
    - Icon badge: `<img src="{{ $stat->svgPath }}" ...>` (guarded)
    - Value span: `<span class="stats-card-value" data-value="{{ $stat->value }}">`
      with prefix/suffix spans guarded by `@if`
    - Label: `<p class="stats-card-label">{{ $stat->label }}</p>`
    - Helper text: `<p class="stats-card-summary">{{ $stat->helperText }}</p>` (guarded)
    - Accent line: `<span class="stats-card-line" aria-hidden="true"></span>`
    - Inline style for `--card-accent` from `$stat->accent` (guarded)
  - Preserve all CSS classes: `stats-section`, `stats-shell`, `stats-shell__grid`,
    `stats-card`, `stats-card__top`, `stats-icon-badge`, `stats-card__body`,
    `stats-card__value-row`, `stats-card-value`, `stats-card-plus`, `stats-card-label`,
    `stats-card-summary`, `stats-card-line`
  - _Requirements: 4.1–4.5, 14.4_

- [x] 8. Rewrite the academic faculties section case in the section partial
  - Replace the `@case('academic_faculties')` block (currently merged with other feature cases)
  - Port full markup from `c:\Users\hamza\Spu-Website\src\fragments\pages\home\faculties.html`
  - Use `<section x-data="facultiesSlider()" class="...">` (Alpine component)
  - Left panel: dark blue card with `$section->payload->title` as `<h2>` and
    `sectionAction` as "view all" link (guarded)
  - Right panel: `x-ref="facultiesTrack"` scroll container
  - For each `$item` in `$section->payload->items`, render a `faculty-card` `<article>` with:
    - Logo: `<img src="{{ $item['image'] ?? '' }}" ...>` (guarded)
    - Name: `<h3>{{ $item['title'] ?? '' }}</h3>`
    - Accent color badge: `$item['accent']` (guarded)
    - Metric: `$item['metric']` (guarded)
    - Learn-more link: `$item['action']['url']` / `$item['action']['label']` (guarded)
  - Prev/next buttons with `@click="slideFaculties('left')"` / `@click="slideFaculties('right')"`
  - Preserve all CSS classes: `faculty-card`, `site-nav-shell`, etc.
  - _Requirements: 5.1–5.11, 14.4, 14.5_

- [x] 9. Rewrite the achievements highlights section case in the section partial
  - Replace the `@case('achievements_highlights')` block
  - Port full markup from `c:\Users\hamza\Spu-Website\src\fragments\pages\home\honor-panel.html`
  - Use `<section x-data="honorPanel()" class="...">` (Alpine component)
  - Inject items via inline script: `<script>window.spuHonorItems = @json($section->payload->items);</script>`
  - Section header: `<h2>{{ $section->payload->title }}</h2>`, eyebrow (guarded),
    prev/next buttons calling `handleManual('prev')` / `handleManual('next')`
  - 3-panel mosaic: `x-for` loop over `items` with `getPos(index)` class binding
  - Each panel: image, badge (`$item['typeTag']`), meta, title, summary, CTA link
    — all rendered via Alpine `x-text` / `:src` / `:href` bindings (data already in JS)
  - Dot navigation row
  - Preserve all CSS classes: `honor-panel-shell`, `honor-panel-card`, `honor-panel-media`,
    `honor-panel-pill`, `honor-panel-cta`
  - _Requirements: 5.1–5.11, 14.4, 14.5_

- [x] 10. Rewrite the university news section case in the section partial
  - Replace the `@case('university_news')` block
  - Port full markup from `c:\Users\hamza\Spu-Website\src\fragments\pages\home\news.html`
  - Section header: `<h2>{{ $section->payload->title }}</h2>` and `sectionAction` link (guarded)
  - 4-column grid; for each `$article` in `$section->payload->articles`, render a
    `<article class="news-card ...">` with:
    - Image: `<img src="{{ $article->image ?? '' }}" alt="{{ $article->title }}">` (guarded)
    - Category badge: `{{ $article->categoryLabel }}` (guarded)
    - Date: `{{ $article->publishedAt }}` (guarded)
    - Title: `<h3>{{ $article->title }}</h3>`
    - Excerpt: `{{ $article->excerpt }}` (guarded)
    - CTA link: `href="{{ $article->url }}"` (guarded); add `rel="noreferrer"` if `target` set
  - Render section heading only when `$section->payload->articles` is empty (no grid)
  - Preserve all CSS classes: `news-card`, `news-card-meta`, `news-card-category`,
    `news-card-date`, `news-card-footer`, `news-card-cta`, `news-card-arrow`
  - _Requirements: 6.1–6.10, 14.4, 14.5, 14.6, 14.7_

- [x] 11. Rewrite the research studies section case in the section partial
  - Replace the `@case('research_studies')` block
  - Port full markup from `c:\Users\hamza\Spu-Website\src\fragments\pages\home\research.html`
  - Use `<section x-data="researchSlider()" class="...">` (Alpine component)
  - Section header: `<h2>{{ $section->payload->title }}</h2>`, `sectionAction` link (guarded),
    prev/next buttons calling `slide('left')` / `slide('right')`
  - `x-ref="researchTrack"` scroll container
  - For each `$item` in `$section->payload->researchItems`, render a
    `<article class="research-card ...">` with:
    - Image (guarded), category tag (`$item->categoryLabel`, guarded)
    - Title: `<h3>{{ $item->title }}</h3>`
    - Summary (guarded), authors list (guarded, `implode(' • ', $item->authors)`)
    - CTA link (`$item->url`, guarded) with `research-card__action` class
  - Render section heading only when `researchItems` is empty
  - Preserve all CSS classes: `research-card`, `research-card__action`, `section-header`,
    `section-header__title`, `slider-nav-btn`
  - _Requirements: 7.1–7.9, 14.4, 14.5, 14.7_

- [x] 12. Rewrite the events and activities section case in the section partial
  - Replace the `@case('events_activities')` block
  - Port full markup from `c:\Users\hamza\Spu-Website\src\fragments\pages\home\events.html`
  - Inject events data: `<script>window.spuEventsData = @json($section->payload->events);</script>`
  - Use `<section x-data="calendarApp()" x-init="startCarousel()" class="...">` (Alpine component)
  - Left panel: featured event card driven entirely by Alpine (`selectedEvent`, transitions,
    dot navigation, `startCarousel` / `stopCarousel` on hover)
  - Right panel: month calendar grid driven by Alpine (`calendarDays`, `viewDate`,
    `prevMonth()`, `nextMonth()`, `selectDate()`)
  - Section heading `<h2>{{ $section->payload->title }}</h2>` rendered above the grid
  - Calendar highlights sidebar: `@foreach ($section->payload->content['calendarHighlights'] ?? [] as $highlight)`
    renders `highlight['label']` and `highlight['date']` (guarded); sidebar container always rendered
  - _Requirements: 8.1–8.10, 14.4_

- [x] 13. Rewrite the medical facilities section case in the section partial
  - Replace the `@case('medical_facilities_services')` block
  - Port full markup from `c:\Users\hamza\Spu-Website\src\fragments\pages\home\healthcare.html`
  - Use `<section x-data="statsCounter()" class="...">` for the stats bar counter trigger
  - Main card (col-span-7): image, title, description, features list, CTA button
    — data from `$section->payload->items[0]` (guarded with `@isset`)
  - Hospital card and dental card (col-span-5): data from `items[1]` and `items[2]` (guarded)
  - Stats bar: 4-column grid; for each `$stat` in `$section->payload->stats`, render
    value span with `data-value="{{ $stat->value }}"`, unit, suffix, label
  - Preserve all CSS classes from the healthcare section
  - _Requirements: 5.1–5.11, 14.4, 14.5_

- [x] 14. Checkpoint — visual and functional review
  - Ensure all tests pass, ask the user if questions arise.
  - Confirm `php artisan view:cache` completes without errors
  - Confirm `GET /ar` and `GET /en` return HTTP 200 with correct `lang` and `dir` attributes
  - Confirm no inline `<style>` or `<script>` blocks exist in `homepage-section.blade.php`
    other than the two permitted data-injection scripts

- [x] 15. Write PHPUnit / Pest view rendering tests
  - [x] 15.1 Test layout meta tags with a fully-populated `PageSeoDTO`
    - Assert `<title>`, `<meta name="description">`, `<link rel="canonical">`,
      `<meta property="og:image">`, and all hreflang links are present
    - Assert no duplicate `<title>` or `<meta name="description">` tags
    - _Requirements: 1.2–1.6, 11.1–11.6_

  - [x]* 15.2 Write property test for HTML root attributes (Property 1)
    - **Property 1: HTML root attributes match injected locale and direction**
    - Generate random `$locale` / `$direction` pairs; assert `html[lang]` and `html[dir]` match
    - **Validates: Requirements 1.1, 10.1**

  - [x]* 15.3 Write property test for SEO meta tag completeness (Property 2)
    - **Property 2: SEO meta tags are complete and non-duplicated**
    - Generate random `PageSeoDTO` with varying null fields; assert correct tag presence/absence
      and no duplicates
    - **Validates: Requirements 1.2–1.6, 11.1–11.6**

  - [x] 15.4 Test preview banner rendering
    - `$isPreview = true` → banner present with `__('public.preview_mode')` text
    - `$isPreview = false` → no banner markup
    - _Requirements: 12.1–12.5_

  - [x] 15.5 Test emergency notice rendering
    - `isEnabled = true` → banner present with title and message
    - `isEnabled = false` → absent
    - _Requirements: 1.9_

  - [x] 15.6 Test footer fallback path
    - `$homepageFooterSection = null` → fallback footer renders `$navigation->footerSettings->brandTitle`
    - _Requirements: 9.2_

  - [x]* 15.7 Write property test for section loop filtering and ordering (Property 3)
    - **Property 3: Section loop renders only enabled non-footer sections in sortOrder**
    - Generate random section arrays with varying `isEnabled`, `key`, `sortOrder`
    - Assert rendered order and filtering
    - **Validates: Requirements 2.1–2.3**

  - [x]* 15.8 Write property test for hero payload field presence (Property 4)
    - **Property 4: Hero payload fields appear in output iff non-null**
    - Generate random hero `HomepageSectionDataDTO` with varying null combinations
    - Assert non-null fields present, null fields absent
    - **Validates: Requirements 3.1–3.10**

  - [x]* 15.9 Write property test for stats card count (Property 5)
    - **Property 5: Stats cards render one card per stat item with correct value and label**
    - Generate random stats arrays (0–20 items); assert card count equals item count
    - **Validates: Requirements 4.1–4.5**

  - [x]* 15.10 Write property test for feature card sections (Property 6)
    - **Property 6: Feature card sections render one card per item with correct fields**
    - Generate random items arrays for `academic_faculties`, `achievements_highlights`,
      `medical_facilities_services`; assert card count and optional field presence
    - **Validates: Requirements 5.1–5.11**

  - [x]* 15.11 Write property test for news cards (Property 7)
    - **Property 7: News cards render one card per article with correct fields**
    - Generate random articles arrays (0–20); assert card count and field presence
    - **Validates: Requirements 6.1–6.10**

  - [x]* 15.12 Write property test for research cards (Property 8)
    - **Property 8: Research cards render one card per item with correct fields**
    - Generate random `researchItems` arrays; assert card count and field presence
    - **Validates: Requirements 7.1–7.9**

  - [x]* 15.13 Write property test for event cards and calendar highlights (Property 9)
    - **Property 9: Event cards render one card per event; calendar highlights render one entry per highlight**
    - Generate random events + highlights arrays; assert card and highlight counts
    - **Validates: Requirements 8.1–8.10**

  - [x]* 15.14 Write property test for accessibility landmarks (Property 10)
    - **Property 10: Accessibility landmarks are present in every rendered layout**
    - Generate random layout inputs; assert `<nav aria-label>`, `<main>`, `<footer>`,
      `<h1>` in hero, `<h2>` in other sections, `rel="noreferrer"` on `target="_blank"` links
    - **Validates: Requirements 14.1–14.7**

- [x] 16. Final checkpoint — full suite
  - Ensure all tests pass, ask the user if questions arise.

---

## Notes

- Tasks marked with `*` are optional and can be skipped for a faster MVP
- The two permitted inline `<script>` tags in partials are data-injection only:
  `window.spuHeroImages`, `window.spuHonorItems`, and `window.spuEventsData`
- All locale resolution is done by the service layer before DTOs reach Blade;
  no `$locale === 'ar'` branches appear in templates
- RTL is handled exclusively via Tailwind `rtl:` modifier classes and the `dir` attribute
  on `<html>` — no duplicate markup
- CSS files in `resources/css/frontend/` are copied verbatim and never modified
- Property tests use fast-check (JS) for Alpine component tests and eris/PHPUnit for Blade rendering tests
