# Design Document — Homepage Blade Integration

## Overview

This feature ports the production-quality visual design from the static Vite + Alpine frontend
(`c:\Users\hamza\Spu-Website`) into the Laravel Blade view layer of the SPU website
(`c:\Users\hamza\SPU_Website`). The backend controller, service layer, DTOs, routes, and
middleware are already complete. The work is entirely in three files plus the asset pipeline:

| File | Role |
|---|---|
| `resources/views/layouts/public.blade.php` | HTML shell: `<head>`, header, footer, banners |
| `resources/views/public/home.blade.php` | Section loop (already minimal; minor guard additions) |
| `resources/views/public/partials/homepage-section.blade.php` | Per-section visual renderer |
| `resources/css/app.css` | CSS entry point — imports all frontend CSS |
| `resources/js/app.js` | JS entry point — registers Alpine, stores, components |
| `vite.config.js` / `package.json` | Build config additions (`alpinejs`, `dayjs`) |

The frontend is the visual source of truth. All Alpine interactions are compiled into
`resources/js/app.js`; no inline `x-data` strings with logic appear in Blade. All content
comes from DTOs — no hardcoded strings in any template.

---

## Architecture

### Rendering pipeline

```
GET /{locale}
  → LocaleSetterMiddleware (sets app locale, direction)
  → HomepageController::show()
      → HomepageService::buildHomepagePayload()   ← already done
          returns HomepageDTO { locale, direction, sections[] }
      → NavigationService::buildNavigationPayload() ← already done
      → view('public.home', [...])
          → layouts/public.blade.php              ← shell
              → @yield('content')
                  → public/home.blade.php         ← section loop
                      → @include('public.partials/homepage-section')
                          → @switch($section->key) ← visual renderer
```

### Data flow: Alpine store → Blade DTO

The frontend used Alpine stores populated from static JS data files. In Blade, the same data
comes from DTOs resolved by the service layer. The mapping is:

| Alpine store field | Blade DTO accessor |
|---|---|
| `$store.hero.images[]` | `$section->payload->content['images']` |
| `$store.hero.titleAr/En` | `$section->payload->title` (locale-resolved) |
| `$store.stats.items[].value` | `$stat->value` |
| `$store.stats.items[].labelAr/En` | `$stat->label` |
| `$store.faculties.items.list[]` | `$section->payload->items[]` |
| `$store.honorPanel.items[]` | `$section->payload->items[]` |
| `$store.news.items[]` | `$section->payload->articles[]` |
| `$store.research.items[]` | `$section->payload->researchItems[]` |
| `mockCalendarEvents[]` | `$section->payload->events[]` |
| `$store.healthcare.mainCard` | `$section->payload->items[0]` |
| `$store.healthcare.stats[]` | `$section->payload->stats[]` |
| `$store.navigation.menuItems[]` | `$navigation->header->items[]` |
| `$store.footer.identity` | `$homepageFooterSection->payload->content['brandBlock']` |

### RTL/LTR strategy

The frontend used `$store.app.currentLang === 'ar'` checks everywhere. In Blade:

- `dir` and `lang` on `<html>` come from `$direction` and `$locale` (controller-injected).
- Directional CSS is handled by Tailwind `rtl:` modifier classes (e.g. `rtl:rotate-180`,
  `rtl:flex-row-reverse`, `rtl:space-x-reverse`).
- No duplicate markup for direction. No locale branches in Blade.
- The `[dir='rtl']` CSS rules already present in the frontend CSS files handle the rest.

### Alpine interaction strategy

All Alpine logic lives in compiled JS, not inline Blade strings. The pattern is:

```html
{{-- Blade: minimal x-data referencing a named component --}}
<section x-data="heroSlider()">...</section>

{{-- JS: full component definition --}}
// resources/js/app.js
Alpine.data('heroSlider', createHeroSlider);
```

The one exception is the events section, which needs `window.spuEventsData` injected by Blade
so the calendar JS can read it without an extra HTTP request:

```blade
<script>window.spuEventsData = @json($section->payload->events);</script>
```

---

## Components and Interfaces

### Layout shell (`layouts/public.blade.php`)

Replaces the current skeleton with the full frontend header/footer design.

**Head section changes:**
- Replace `instrument-sans` Bunny Fonts link with the Hacen font `@font-face` declarations
  (served from `public/fonts/`; the frontend already has the TTF files).
- Keep `@vite` directive guarded by manifest/hot-file check (already correct).
- Add all required SEO meta tags (robots, og:locale, og:title, og:description, og:image,
  canonical, hreflang).

**Header:**
Replaces the current skeleton `<header>` with the full `site-nav-shell` markup from
`src/fragments/layout/header.html`, adapted for Blade:

- `x-data="{ openMenu: null, stickyNav: false, mobileNav: false }"` stays as inline Alpine
  (it is pure UI state, no business logic).
- Navigation items loop over `$navigation->header->items` (Blade `@foreach`) instead of
  `$store.navigation.menuItems` (Alpine `x-for`).
- Language switcher loops over `$languageSwitch` (Blade `@foreach`).
- Utility links loop over `$navigation->utility->items`.
- Apply CTA, student portal, staff access rendered conditionally from `$navigation`.
- Emergency notice and preview banners rendered above the header shell.

**Footer:**
Replaces the current skeleton `<footer>` with the full `src/fragments/layout/footer.html`
design, adapted for Blade:

- When `$homepageFooterSection` is not null: use payload path (brand block, footer columns,
  contact links, social links, legal links, copyright text, map embed).
- When null: fall back to `$navigation->footerSettings` + `$navigation->socialContact`.
- Social icons use Font Awesome classes from the frontend (`<i :class="social.icon">`
  becomes `<i class="{{ $link->icon ?? '' }}"></i>` or similar DTO field).

### Section partial (`public/partials/homepage-section.blade.php`)

The `@switch` block gains full visual markup per case. Summary of each case:

#### `@case('hero')`
Full `home-hero` section from `src/fragments/pages/home/hero.html`:
- Background image slider: images from `$section->payload->content['images']` rendered as
  `<div>` wrappers with `x-show="$store.heroSlider.currentIndex === {{ $loop->index }}"` and
  Alpine transition classes. The `heroSlider` Alpine store is registered in JS.
- Overlay and ambient divs (CSS-only, `aria-hidden`).
- Eyebrow pill, `<h1>` title, subtitle, summary/body, primary + secondary CTA buttons.
- All CSS classes from `heroes.css` preserved exactly.

#### `@case('hero_stats')` / `@case('bottom_stats')`
Full `stats-section` from `src/fragments/pages/home/stats.html`:
- `stats-shell__grid` with one `stats-card` per `$stat` in `$section->payload->stats`.
- Each card: icon badge (`$stat->svgPath` or fallback), value row (`stats-card-value` span
  with `data-value="{{ $stat->value }}"` for the counter animation), label, summary, accent
  line.
- The `statsCounter` Alpine component (registered in JS) uses `IntersectionObserver` to
  trigger `animateCounter()` on each `.stats-card-value[data-value]` element.

#### `@case('academic_faculties')`
Full faculties slider from `src/fragments/pages/home/faculties.html`:
- Left panel: dark blue card with section title and "view all" link from `sectionAction`.
- Right panel: horizontal scroll track (`x-ref="facultiesTrack"`) with one `faculty-card`
  per item in `$section->payload->items`.
- Each card: logo image (`$item['image']`), name (`$item['title']`), accent color badge
  (`$item['accent']`), metric (`$item['metric']`), learn-more link (`$item['action']`).
- Prev/next buttons call `slideFaculties(direction)` Alpine component registered in JS.

#### `@case('achievements_highlights')`
Full honor panel from `src/fragments/pages/home/honor-panel.html`:
- 3-panel mosaic layout driven by `honorPanel()` Alpine component (registered in JS).
- Items from `$section->payload->items`.
- Each item: image, badge (`$item['typeTag']`), title, summary, CTA link.
- Dot navigation, prev/next buttons.
- `honor-panel-shell`, `honor-panel-card`, `honor-panel-media`, `honor-panel-pill`,
  `honor-panel-cta` CSS classes preserved.

#### `@case('university_news')`
Full news grid from `src/fragments/pages/home/news.html`:
- 4-column grid of `news-card` articles.
- Each card: image, category badge, date, title, excerpt, CTA link.
- Section header with title and "view all" link from `sectionAction`.
- `news-card`, `news-card-meta`, `news-card-category`, `news-card-date`, `news-card-footer`,
  `news-card-cta`, `news-card-arrow` CSS classes preserved.

#### `@case('research_studies')`
Full research slider from `src/fragments/pages/home/research.html`:
- Horizontal scroll track (`x-ref="researchTrack"`) with one `research-card` per item in
  `$section->payload->researchItems`.
- Each card: image, category tag, title, summary, "View Details" link.
- Prev/next buttons call `researchSlider().slide(direction)` Alpine component.
- `research-card`, `research-card__action`, `section-header`, `section-header__title`,
  `slider-nav-btn` CSS classes preserved.

#### `@case('events_activities')`
Full events + calendar from `src/fragments/pages/home/events.html`:
- Left panel: featured event card with image, type badge, date, title, description, link,
  dot navigation. Driven by `calendarApp()` Alpine component.
- Right panel: month calendar grid with day buttons, event dot indicators.
- `window.spuEventsData` injected via inline `<script>` tag from
  `$section->payload->events`.
- Calendar highlights sidebar rendered from
  `$section->payload->content['calendarHighlights']`.

#### `@case('medical_facilities_services')`
Full healthcare section from `src/fragments/pages/home/healthcare.html`:
- Main card (col-span-7): image, title, description, features list, CTA button.
  Data from `$section->payload->items[0]`.
- Two side cards (col-span-5): hospital card and dental card.
  Data from `$section->payload->items[1]` and `$section->payload->items[2]`.
- Stats bar: 4-column grid of animated counters from `$section->payload->stats`.
  Uses same `statsCounter` IntersectionObserver pattern.

### JavaScript modules (`resources/js/app.js`)

```
app.js
├── import Alpine from 'alpinejs'
├── import dayjs from 'dayjs'
├── Alpine.store('heroSlider', createHeroSlider())   ← auto-advance, crossfade
├── Alpine.store('statsCounter', ...)                ← IntersectionObserver trigger
├── Alpine.store('faculties', ...)                   ← activeFaculty hover state
├── Alpine.store('honorPanel', ...)                  ← activeIndex, startAuto/stopAuto
├── Alpine.store('news', ...)                        ← (minimal, data from Blade)
├── Alpine.store('research', ...)                    ← (minimal, data from Blade)
├── Alpine.store('healthcare', ...)                  ← startCounting()
├── Alpine.data('researchSlider', createResearchSlider)
├── Alpine.data('calendarApp', createCalendarApp)    ← reads window.spuEventsData
├── initRevealSections()                             ← IntersectionObserver for .reveal
└── window.Alpine = Alpine; Alpine.start()
```

Key differences from the frontend stores:
- Stores no longer hold content data (that comes from Blade/DTOs).
- `heroSlider` store reads images from `window.spuHeroImages` injected by the hero Blade
  partial (same pattern as `window.spuEventsData`).
- `statsCounter` is a utility that queries `[data-value]` elements on intersection.

### CSS entry point (`resources/css/app.css`)

```css
@import 'tailwindcss';

/* Blade source scanning */
@source '../../vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php';
@source '../../storage/framework/views/*.php';
@source '../**/*.blade.php';
@source '../**/*.js';

/* Design tokens and font */
@theme {
    --font-hacen: "HacenTunisia", "Segoe UI", "Tahoma", "Arial", sans-serif;
    --color-spu-blue: #202759;
    --color-spu-red:  #6f1616;
    --color-section:  #EAF3FF40;
}

/* Frontend CSS imports (order matches src/style.css) */
@import './frontend/foundation.css';
@import './frontend/layout.css';
@import './frontend/navigation.css';
@import './frontend/heroes.css';
@import './frontend/home-sections.css';
@import './frontend/stats.css';
@import './frontend/reveal.css';
@import './frontend/utilities.css';
```

The frontend CSS files are copied verbatim into `resources/css/frontend/` (or symlinked).
No modifications to the CSS files themselves — they are the source of truth.

The `@font-face` declarations from `foundation.css` reference `/fonts/Hacen Tunisia Regular.ttf`
and `/fonts/Hacen Tunisia Bold Regular.ttf`. These font files must be present in `public/fonts/`.

---

## Data Models

No new database models or migrations are introduced. This feature is view-layer only.

### DTOs consumed (read-only)

All DTOs are already defined. The view layer consumes:

```
HomepageDTO
  ├── locale: string
  ├── direction: 'rtl' | 'ltr'
  └── sections: HomepageSectionDTO[]
        ├── key: string          (one of the 10 approved keys)
        ├── isEnabled: bool
        ├── sortOrder: int
        └── payload: HomepageSectionDataDTO
              ├── title: string
              ├── subtitle: ?string
              ├── eyebrow: ?string
              ├── badge: ?string
              ├── summary: ?string
              ├── body: ?string
              ├── backgroundImageUrl: ?string
              ├── primaryAction: ?ActionDTO { label, url, target }
              ├── secondaryAction: ?ActionDTO
              ├── sectionAction: ?ActionDTO
              ├── stats[]: StatItemDTO { value, prefix, suffix, label, helperText, svgPath, accent }
              ├── items[]: array (shape varies by section key)
              ├── articles[]: ArticleDTO { title, excerpt, publishedAt, categoryLabel, badgeTag, url, image }
              ├── researchItems[]: ResearchItemDTO { title, summary, publishedAt, categoryLabel, authors[], url, image }
              ├── events[]: EventDTO { title, summary, startsAt, timeLabel, location, url, image, type }
              ├── footerColumns[]: FooterColumnDTO { title, links[] }
              ├── contactLinks[]: ContactLinkDTO { label, value, icon }
              ├── socialLinks[]: SocialLinkDTO { platform, url, icon }
              └── content[]: array (free-form CMS content bag)

NavigationPayloadDTO
  ├── header: NavTreeDTO { items: NavItemDTO[] }
  ├── footer: NavTreeDTO
  ├── utility: NavTreeDTO
  ├── applyCta: ?ActionDTO
  ├── studentPortalUrl: ?string
  ├── staffAccessUrl: ?string
  ├── emergencyNotice: EmergencyNoticeDTO { isEnabled, title, message }
  ├── footerSettings: FooterSettingsDTO { logoUrl, brandTitle, brandSummary, address, phone, email, mapEmbedUrl, copyrightText, legalLinks[] }
  └── socialContact: SocialContactDTO { socialLinks[], contactLinks[] }

PageSeoDTO
  ├── title, metaDescription, ogTitle, ogDescription, ogImage
  ├── canonicalUrl, robots
  └── hreflang[]: { locale, url }

LanguageSwitchLinkDTO[]
  └── { locale, label, url, isCurrent }
```

### Window-injected data (Blade → JS bridge)

Two small inline scripts bridge Blade data to Alpine components that need it at init time:

```blade
{{-- In hero case --}}
<script>window.spuHeroImages = @json($section->payload->content['images'] ?? []);</script>

{{-- In events_activities case --}}
<script>window.spuEventsData = @json($section->payload->events);</script>
```

These are the only `<script>` tags permitted in partials (data injection only, no logic).

---

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions
of a system — essentially, a formal statement about what the system should do. Properties serve
as the bridge between human-readable specifications and machine-verifiable correctness
guarantees.*

### Property 1: HTML root attributes match injected locale and direction

*For any* `$locale` string and `$direction` string passed to the layout, the rendered `<html>`
element SHALL have `lang="{{ $locale }}"` and `dir="{{ $direction }}"` exactly.

**Validates: Requirements 1.1, 10.1**

---

### Property 2: SEO meta tags are complete and non-duplicated

*For any* `PageSeoDTO` with varying combinations of null and non-null optional fields, the
rendered `<head>` SHALL contain exactly one `<title>` tag, at most one
`<meta name="description">`, at most one `<link rel="canonical">`, at most one
`<meta property="og:image">`, and one `<link rel="alternate" hreflang>` per entry in
`$seo->hreflang`.

**Validates: Requirements 1.2, 1.3, 1.4, 1.5, 1.6, 11.1, 11.2, 11.3, 11.4, 11.5, 11.6**

---

### Property 3: Section loop renders only enabled non-footer sections in sortOrder

*For any* array of `HomepageSectionDTO` objects with varying `isEnabled`, `key`, and
`sortOrder` values, the rendered homepage content SHALL contain markup for exactly the sections
where `isEnabled === true` AND `key !== 'footer'`, in ascending `sortOrder` order.

**Validates: Requirements 2.1, 2.2, 2.3**

---

### Property 4: Hero payload fields appear in output iff non-null

*For any* hero `HomepageSectionDataDTO` with varying combinations of null and non-null fields
(`eyebrow`, `badge`, `title`, `subtitle`, `summary`, `body`, `primaryAction`,
`secondaryAction`, `backgroundImageUrl`), the rendered hero section SHALL contain markup for
exactly the non-null fields and SHALL NOT emit empty placeholder elements for null fields.

**Validates: Requirements 3.1 – 3.10**

---

### Property 5: Stats cards render one card per stat item with correct value and label

*For any* stats payload with N stat items (N ≥ 0), the rendered stats section SHALL contain
exactly N stat cards. Each card SHALL include the stat's `value` (with `prefix` and `suffix`
when non-null) and `label`. When N = 0, no card grid element SHALL be rendered.

**Validates: Requirements 4.1 – 4.5**

---

### Property 6: Feature card sections render one card per item with correct fields

*For any* feature-card section (`academic_faculties`, `achievements_highlights`,
`medical_facilities_services`) payload with N items (N ≥ 0), the rendered section SHALL
contain exactly N `<article>` cards. Each card SHALL display `item['title']`. Optional fields
(`summary`, `typeTag`/`accent`, `metric`, `action`) SHALL appear in the card output if and
only if they are non-empty in the item array.

**Validates: Requirements 5.1 – 5.11**

---

### Property 7: News cards render one card per article with correct fields

*For any* `university_news` payload with N articles (N ≥ 0), the rendered section SHALL
contain exactly N `<article>` cards. Each card SHALL display `article->title`. Optional fields
(`excerpt`, `publishedAt`, `categoryLabel`, `badgeTag`, `url`) SHALL appear if and only if
non-null. When N = 0, no card grid SHALL be rendered.

**Validates: Requirements 6.1 – 6.10**

---

### Property 8: Research cards render one card per item with correct fields

*For any* `research_studies` payload with N research items (N ≥ 0), the rendered section
SHALL contain exactly N `<article>` cards. Each card SHALL display `item->title`. Optional
fields (`summary`, `publishedAt`, `categoryLabel`, `authors`, `url`) SHALL appear if and only
if non-null/non-empty.

**Validates: Requirements 7.1 – 7.9**

---

### Property 9: Event cards render one card per event; calendar highlights render one entry per highlight

*For any* `events_activities` payload with N events and M calendar highlights, the rendered
section SHALL contain exactly N event `<article>` cards and exactly M highlight entries in the
sidebar. When M = 0, the sidebar container SHALL still be rendered but contain no highlight
entries.

**Validates: Requirements 8.1 – 8.10**

---

### Property 10: Accessibility landmarks are present in every rendered layout

*For any* combination of layout inputs, the rendered HTML SHALL contain: a `<nav>` element
with a non-empty `aria-label` attribute, a `<main>` element, a `<footer>` element, an `<h1>`
in the hero section, and `<h2>` headings in all other section types. Every `<a target="_blank">`
SHALL have `rel="noreferrer"`.

**Validates: Requirements 14.1 – 14.7**

---

## Error Handling

### Missing or null payload fields

All optional DTO fields are guarded with `@if` / `@isset` / null-coalescing operators in
Blade. No `{{ }}` echo of a potentially null value without a fallback.

Pattern:
```blade
@if ($section->payload->eyebrow)
    <p class="home-hero__eyebrow">{{ $section->payload->eyebrow }}</p>
@endif
```

### Empty collections

Empty arrays (`stats`, `items`, `articles`, `researchItems`, `events`) produce no grid
markup. The section heading is always rendered regardless.

### Unknown section keys

The `@switch` block has no `@default` case that emits visible markup. Unknown keys silently
produce no output, preventing layout breakage from future CMS keys.

### Missing font files

If `public/fonts/Hacen Tunisia Regular.ttf` is absent, the browser falls back to the
`font-family` stack: `"Segoe UI", "Tahoma", "Arial", sans-serif`. The page remains readable.

### Missing Vite manifest

The existing `@if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))` guard is preserved. No broken asset tags in production if the build hasn't run.

### JS component init with empty data

All Alpine components guard against empty arrays:
```js
// heroSlider: no-op if images array is empty
if (!this.images?.length) return;
// calendarApp: falls back to today's date if no events
const initialDate = this.rawEvents[0]?.dateKey ?? dayjs().format('YYYY-MM-DD');
```

---

## Testing Strategy

### Unit tests (PHPUnit / Pest)

Focus on specific rendering examples and edge cases:

- **Layout meta tags**: render layout with a fully-populated `PageSeoDTO` and assert all
  expected `<meta>` and `<link>` tags are present.
- **Preview banner**: `$isPreview = true` → banner present; `$isPreview = false` → absent.
- **Emergency notice**: `isEnabled = true` → banner present with title; `false` → absent.
- **Footer fallback**: `$homepageFooterSection = null` → fallback footer renders
  `$navigation->footerSettings->brandTitle`.
- **Section skip**: section with `isEnabled = false` → no markup for that section.
- **Footer key skip**: section with `key = 'footer'` → not in main content output.
- **Empty stats**: `stats = []` → no `<div class="stats-shell__grid">` in output.
- **Empty articles**: `articles = []` → no article cards in news section.
- **Null optional fields**: hero with all optional fields null → no eyebrow, badge, subtitle,
  summary, or CTA markup.

### Property-based tests (fast-check / Pest + PHPUnit)

The feature involves Blade template rendering — a pure function from DTO inputs to HTML
strings. Property-based testing is appropriate for the rendering rules.

**Library**: [fast-check](https://fast-check.io/) via a Node.js test runner for JS components,
and a PHP PBT library (e.g. [eris](https://github.com/giorgiosironi/eris)) for Blade rendering
tests.

**Minimum iterations**: 100 per property test.

**Tag format**: `Feature: homepage-blade-integration, Property {N}: {property_text}`

Each correctness property maps to one property-based test:

| Property | Test description |
|---|---|
| P1 | Generate random locale/direction pairs; assert html[lang] and html[dir] match |
| P2 | Generate random PageSeoDTO with varying null fields; assert correct meta tag presence/absence and no duplicates |
| P3 | Generate random section arrays with varying isEnabled/key/sortOrder; assert rendered order and filtering |
| P4 | Generate random hero payloads with varying null fields; assert field presence iff non-null |
| P5 | Generate random stats arrays (0–20 items); assert card count equals item count |
| P6 | Generate random items arrays for feature sections; assert card count and field presence |
| P7 | Generate random articles arrays; assert card count and field presence |
| P8 | Generate random researchItems arrays; assert card count and field presence |
| P9 | Generate random events + highlights arrays; assert card and highlight counts |
| P10 | Generate random layout inputs; assert semantic landmark presence |

### Integration tests

- **Full page render**: `GET /ar` and `GET /en` return HTTP 200 with correct `lang` and `dir`
  attributes.
- **RTL render**: Arabic locale response contains `dir="rtl"` on `<html>`.
- **LTR render**: English locale response contains `dir="ltr"` on `<html>`.
- **Preview token**: valid preview token renders preview banner; expired/missing token does not.
- **Vite assets**: `@vite` directive emits correct asset URLs when manifest exists.

### Smoke tests

- `npm run build` completes without errors.
- No inline `<style>` or `<script>` blocks (other than the two permitted data-injection
  scripts) exist in `homepage-section.blade.php`.
- Font files present at `public/fonts/Hacen Tunisia Regular.ttf` and
  `public/fonts/Hacen Tunisia Bold Regular.ttf`.
- `php artisan view:cache` completes without errors.
