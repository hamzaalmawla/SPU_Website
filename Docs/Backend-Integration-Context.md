# Backend Integration Context — SPU Website

**Date:** April 28, 2026  
**Branch:** PX08 (current HEAD)  
**Stack:** Laravel 12 · PHP 8.2 · MySQL 8 · Redis · Filament v3

---

## 1. How the App Is Structured

```
Controllers (thin)
    ↓ inject via interface
Services (all business logic)
    ↓ return
DTOs (final readonly, never raw Eloquent)
    ↓ passed to
Blade views (public) / Filament pages (admin)
```

- **Never** `new ServiceClass()` — always resolved from the container via interfaces.
- **Never** return Eloquent models from public service methods.
- All interfaces live in `app/Contracts/`, services in `app/Services/`, DTOs in `app/DTOs/`.

---

## 2. Public Routes

```
GET  /                          → redirect to /ar
GET  /sitemap.xml               → SitemapController@sitemap
GET  /robots.txt                → SitemapController@robots

GET  /{locale}                  → HomeController          (locale = ar|en)
GET  /{locale}/preview          → PreviewController       (token required)
POST /{locale}/contact          → PublicContactController (throttled)
GET  /{locale}/{slugPath}       → PageController          (slugPath = any depth)

GET  /admin/login               → AuthController@create
POST /admin/login               → AuthController@store    (throttled: admin-login)
POST /admin/auth/logout         → AuthController@destroy  (requires admin.auth)
```

### Middleware stack on public `/{locale}/*` routes
1. `RedirectContinuityMiddleware` — global, runs first; resolves legacy redirects, logs 404s
2. `locale` (`LocaleSetterMiddleware`) — sets `App::locale`, adds `Content-Language` + `X-Page-Direction` headers
3. `cache.public` (`CachePublicPages`) — HTML cache with Redis tags; bypassed for auth'd users, preview, non-GET

### Response headers added by middleware
| Header | Values |
|--------|--------|
| `Content-Language` | `ar` or `en` |
| `X-Page-Direction` | `rtl` or `ltr` |
| `X-Cache` | `HIT`, `MISS`, or `BYPASS` |

---

## 3. View Variables — Public Homepage (`GET /{locale}`)

View: `resources/views/public/home.blade.php` extends `layouts/public.blade.php`

| Variable | Type | Description |
|----------|------|-------------|
| `$locale` | `string` | `'ar'` or `'en'` |
| `$direction` | `string` | `'rtl'` or `'ltr'` |
| `$homepage` | `HomepageDTO` | Full homepage payload (sections array) |
| `$homepageFooterSection` | `HomepageSectionDTO\|null` | The `footer` section pulled out separately |
| `$navigation` | `NavigationPayloadDTO` | Full nav shell (header, footer, utility trees + settings) |
| `$settings` | `PublicSettingsDTO` | Global settings (CTA, emergency notice, footer, social, SEO defaults) |
| `$seo` | `PageSeoDTO` | SEO meta for the `<head>` |
| `$languageSwitch` | `LanguageSwitchLinkDTO[]` | AR/EN switcher links |
| `$isPreview` | `bool` | Always `false` on public route |

---

## 4. View Variables — Public Page (`GET /{locale}/{slugPath}`)

View: `resources/views/public/page.blade.php` extends `layouts/public.blade.php`

| Variable | Type | Description |
|----------|------|-------------|
| `$locale` | `string` | `'ar'` or `'en'` |
| `$direction` | `string` | `'rtl'` or `'ltr'` |
| `$navigation` | `NavigationPayloadDTO` | Same nav shell as homepage |
| `$settings` | `PublicSettingsDTO` | Same settings as homepage |
| `$seo` | `PageSeoDTO` | Page-specific SEO |
| `$breadcrumbs` | `BreadcrumbTrailDTO` | Breadcrumb trail for this page |
| `$languageSwitch` | `LanguageSwitchLinkDTO[]` | AR/EN switcher links |
| `$isPreview` | `bool` | Always `false` on public route |
| `$page` | `array` | Flattened page payload (see below) |

### `$page` array shape
```php
[
  'id'               => int,
  'slug'             => string,
  'template'         => string,
  'title'            => string,
  'navigationLabel'  => string|null,
  'headline'         => string|null,
  'subheadline'      => string|null,
  'hero'             => array|null,       // { title, summary, imageUrl, ... }
  'overviewCards'    => array,            // [ { title, summary }, ... ]
  'stats'            => array,            // [ { value, label }, ... ]
  'bodyBlocks'       => array,            // [ { type, content }, ... ]
  'body'             => string|null,      // fallback plain text body
  'excerpt'          => string|null,
  'cta'              => array|null,       // { label, url }
  'sidebar'          => array|null,       // { title, body }
]
```

---

## 5. View Variables — Preview (`GET /{locale}/preview?token=...`)

Same view as homepage or page depending on `$preview->targetType`.  
Extra variables added:

| Variable | Type | Description |
|----------|------|-------------|
| `$isPreview` | `bool` | `true` |
| `$preview` | `PreviewDTO` | Token metadata (targetType, expiresAt, device) |

Token can be passed as:
- Query param `?token=...`
- Query param `?preview_token=...`
- Header `X-Preview-Token: ...`

---

## 6. Shared Layout Variables (`layouts/public.blade.php`)

Every public page receives these (used in `<head>` and nav shell):

```php
$locale       // string — html lang attribute
$direction    // string — html dir attribute (rtl|ltr)
$seo          // PageSeoDTO — title, meta, og, canonical, hreflang, robots
$navigation   // NavigationPayloadDTO — full nav shell
$languageSwitch // LanguageSwitchLinkDTO[]
$isPreview    // bool
$settings     // PublicSettingsDTO (available but not directly used in layout)
```

---

## 7. DTO Reference

### `HomepageDTO`
```php
locale: string
direction: string          // 'rtl' | 'ltr'
sections: HomepageSectionDTO[]
```

### `HomepageSectionDTO`
```php
id: int
key: string                // one of the 10 approved keys (see §10)
sortOrder: int
isEnabled: bool
payload: HomepageSectionDataDTO        // locale-resolved payload
arabicTranslation: HomepageSectionTranslationDTO
englishTranslation: HomepageSectionTranslationDTO
arabicPayload: HomepageSectionDataDTO|null
englishPayload: HomepageSectionDataDTO|null
```

### `HomepageSectionDataDTO`
```php
eyebrow: string|null
subtitle: string|null
badge: string|null
title: string|null
summary: string|null
body: string|null
videoUrl: string|null
imageUrl: string|null
backgroundImageUrl: string|null
primaryAction: NavigationActionDTO|null
secondaryAction: NavigationActionDTO|null
sectionAction: NavigationActionDTO|null
stats: HomepageStatItemDTO[]
featuredItems: HomepageFeatureItemDTO[]
articles: ArticleCardDTO[]
researchItems: ResearchCardDTO[]
events: EventCardDTO[]
footerColumns: FooterColumnDTO[]
contactLinks: ContactLinkDTO[]
socialLinks: SocialLinkDTO[]
items: array[]             // generic key-value items (used by faculties, highlights, services)
content: array             // freeform content bag (brandBlock, contactBlock, mapEmbed, etc.)
```

### `NavigationPayloadDTO`
```php
locale: string
direction: string
header: NavigationTreeDTO
footer: NavigationTreeDTO
utility: NavigationTreeDTO
languageSwitchLinks: LanguageSwitchLinkDTO[]
applyCta: NavigationActionDTO|null
studentPortalUrl: string|null
staffAccessUrl: string|null
emergencyNotice: EmergencyNoticeDTO
footerSettings: FooterSettingsDTO
socialContact: SocialContactSettingsDTO
```

### `NavigationTreeDTO`
```php
treeType: string           // 'header' | 'footer' | 'utility'
locale: string
direction: string
items: MenuItemDTO[]
```

### `MenuItemDTO`
```php
id: int
parentId: int|null
label: string
itemType: string
groupKey: string           // 'header' | 'footer' | 'utility'
targetType: string
locale: string|null
targetId: int|null
url: string|null
resolvedUrl: string|null   // use this for href
target: string|null        // '_blank' etc.
routeName: string|null
cssToken: string|null
icon: string|null
isActive: bool
sortOrder: int
depth: int                 // 0 = top-level, 1 = child (max depth = 2)
isEnabled: bool
isUtility: bool
openInNewTab: bool
children: MenuItemDTO[]
```

### `PageSeoDTO`
```php
locale: string
title: string
metaDescription: string|null
ogTitle: string|null
ogDescription: string|null
ogImage: string|null
canonicalUrl: string|null
hreflang: array            // [ { locale: string, url: string }, ... ]
robots: string|null        // e.g. 'index,follow'
```

### `PublicSettingsDTO`
```php
locale: string
direction: string
applyCta: ApplyCtaSettingsDTO
emergencyNotice: EmergencyNoticeDTO
footer: FooterSettingsDTO
socialContact: SocialContactSettingsDTO
defaultSeo: PageSeoDTO
studentPortalUrl: string|null
staffAccessUrl: string|null
```

### `FooterSettingsDTO`
```php
locale: string
copyrightText: string
address: string|null
phone: string|null
email: string|null
brandTitle: string|null
brandSummary: string|null
logoUrl: string|null
mapEmbedUrl: string|null
legalLinks: NavigationActionDTO[]
```

### `EmergencyNoticeDTO`
```php
locale: string
isEnabled: bool
title: string|null
message: string|null
url: string|null
```

### `SocialContactSettingsDTO`
```php
locale: string
socialLinks: SocialLinkDTO[]    // { platform, url, isEnabled }
contactLinks: ContactLinkDTO[]  // { type, label, value }
```

### `ApplyCtaSettingsDTO`
```php
locale: string
label: string
url: string
target: string|null
isEnabled: bool
```

### `BreadcrumbTrailDTO`
```php
locale: string
items: BreadcrumbItemDTO[]   // { label, url|null, isCurrent }
```

### `LanguageSwitchLinkDTO`
```php
locale: string
label: string              // 'AR' or 'EN'
url: string
isCurrent: bool
```

### `NavigationActionDTO`
```php
label: string
url: string
target: string|null
```

### `HomepageStatItemDTO`
```php
value: string
label: string
description: string|null
icon: string|null
prefix: string|null
suffix: string|null
helperText: string|null
url: string|null
sortOrder: int|null
```

### `HomepageFeatureItemDTO`
```php
title: string
summary: string|null
imageUrl: string|null
url: string|null
tags: string[]
```

### `ArticleCardDTO`
```php
id: int
locale: string
title: string
slug: string
excerpt: string|null
imageUrl: string|null
publishedAt: string|null
url: string|null
categoryLabel: string|null
badgeTag: string|null
```

### `EventCardDTO`
```php
id: int
locale: string
title: string
slug: string
summary: string|null
startsAt: string|null
endsAt: string|null
location: string|null
url: string|null
imageUrl: string|null
timeLabel: string|null
```

### `ResearchCardDTO`
```php
id: int
locale: string
title: string
slug: string
summary: string|null
imageUrl: string|null
publishedAt: string|null
url: string|null
categoryLabel: string|null
authors: string[]
```

### `FooterColumnDTO`
```php
title: string
links: NavigationActionDTO[]
```

### `PreviewDTO`
```php
token: string
targetType: string         // 'homepage' | 'page'
targetId: int|null
locale: string
previewUrl: string
payload: PreviewPayloadDTO
expiresAt: string|null
device: string|null
```

---

## 8. Database Schema (Foundation Tables)

### `pages`
| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint PK | |
| `parent_id` | bigint FK→pages | nullable, nullOnDelete |
| `type` | string | |
| `template` | string | |
| `slug` | string UNIQUE | |
| `status` | string | `draft`, `published`, `scheduled` |
| `sort_order` | uint | |
| `is_enabled` | bool | |
| `show_in_breadcrumbs` | bool | |
| `show_in_nav` | bool | |
| `is_homepage_shell` | bool | |
| `publish_at` | timestamp | nullable, scheduled publish |
| `published_at` | timestamp | nullable |
| `created_by` | bigint FK→users | nullable |
| `updated_by` | bigint FK→users | nullable |
| `approved_by` | bigint FK→users | nullable |
| `content_json` | json | nullable, shell-level non-localized data |
| `timestamps` + `softDeletes` | | |

### `page_translations`
| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint PK | |
| `page_id` | bigint FK→pages | cascadeOnDelete |
| `locale` | string(5) | `ar` or `en` |
| `title` | string | |
| `navigation_label` | string | nullable |
| `headline` | string | nullable |
| `subheadline` | string | nullable |
| `hero_payload` | json | nullable |
| `overview_cards_payload` | json | nullable |
| `stats_payload` | json | nullable |
| `body_payload` | json | nullable — `{ blocks: [ { type, content } ] }` |
| `cta_payload` | json | nullable — `{ label, url }` |
| `sidebar_payload` | json | nullable — `{ title, body }` |
| `excerpt` | text | nullable |
| `body` | longText | nullable, plain text fallback |
| `raw_excerpt` | text | nullable |
| `meta_title_fallback` | string | nullable |
| UNIQUE | `(page_id, locale)` | |

### `page_seo_meta`
| Column | Type | Notes |
|--------|------|-------|
| `page_id` | bigint FK→pages | cascadeOnDelete |
| `locale` | string(5) | |
| `meta_title` | string | nullable |
| `meta_description` | text | nullable |
| `og_title` | string | nullable |
| `og_description` | text | nullable |
| `og_image_media_id` | bigint FK→media_assets | nullable |
| `og_image_url` | string | nullable |
| `canonical_url` | string | nullable |
| `robots` | string | nullable |
| `hreflang_payload` | json | nullable |
| UNIQUE | `(page_id, locale)` | |

### `homepage_sections`
| Column | Type | Notes |
|--------|------|-------|
| `key` | string UNIQUE | one of the 10 approved keys |
| `type` | string | |
| `sort_order` | uint | |
| `is_enabled` | bool | |
| `config_json` | json | nullable |

### `homepage_section_translations`
| Column | Type | Notes |
|--------|------|-------|
| `section_id` | bigint FK→homepage_sections | cascadeOnDelete |
| `locale` | string(5) | |
| `payload_json` | json | full `HomepageSectionDataDTO` serialized |
| UNIQUE | `(section_id, locale)` | |

### `homepage_drafts`
| Column | Type | Notes |
|--------|------|-------|
| `target_type` | string | default `'homepage'` |
| `target_id` | bigint | nullable |
| `payload_json` | json | draft snapshot |
| `status` | string | `draft`, `pending_review`, `scheduled`, `published` |
| `scheduled_at` | timestamp | nullable |
| `published_at` | timestamp | nullable |
| `created_by` / `updated_by` / `approved_by` | FK→users | |

### `page_drafts`
| Column | Type | Notes |
|--------|------|-------|
| `page_id` | bigint FK→pages | cascadeOnDelete |
| `payload_json` | json | draft snapshot |
| `status` | string | |
| `scheduled_at` | timestamp | nullable |
| `published_at` | timestamp | nullable |

### `menu_items`
| Column | Type | Notes |
|--------|------|-------|
| `parent_id` | bigint FK→menu_items | nullable, nullOnDelete |
| `type` | string | mirrors `group_key` |
| `label` | string | |
| `locale` | string(5) | nullable |
| `target_kind` | string | `page`, `url`, `route` |
| `target_id` | bigint | nullable |
| `url` | string | nullable |
| `target` | string | nullable (`_blank`) |
| `group_key` | string | `header`, `footer`, `utility` |
| `is_enabled` | bool | |
| `is_utility` | bool | |
| `open_in_new_tab` | bool | |
| `sort_order` | uint | |
| `depth` | uint | max 2 |
| `softDeletes` | | |

### `settings`
| Column | Type | Notes |
|--------|------|-------|
| `key` | string | |
| `group_key` | string | e.g. `utility_nav`, `footer`, `emergency_notice`, `contact`, `social`, `seo_defaults` |
| `type` | string | |
| `locale` | string(5) | `''` for non-localized, `'ar'`/`'en'` for localized |
| `value_json` | json | nullable |
| `value_text` | text | nullable |
| `is_public` | bool | |
| UNIQUE | `(group_key, key, locale)` | |

### `media_assets`
| Column | Type | Notes |
|--------|------|-------|
| `disk` | string | |
| `directory` | string | nullable |
| `filename` | string | |
| `original_name` | string | |
| `mime_type` | string | |
| `extension` | string | nullable |
| `size_bytes` | bigint | |
| `width` / `height` | uint | nullable |
| `alt_text_ar` / `alt_text_en` | string | nullable |
| `caption_ar` / `caption_en` | string | nullable |
| `title_ar` / `title_en` | string | nullable |
| `path` | string | storage path |
| `webp_path` | string | nullable |
| `srcset_json` | json | nullable |
| `uploaded_by` | bigint FK→users | nullable |
| `softDeletes` | | |

### `preview_tokens`
| Column | Type | Notes |
|--------|------|-------|
| `token` | string UNIQUE | |
| `target_type` | string | `homepage` or `page` |
| `target_id` | bigint | nullable |
| `locale` | string(5) | nullable |
| `device` | string | nullable |
| `issued_to_user_id` | bigint FK→users | nullable |
| `payload_json` | json | nullable |
| `expires_at` | timestamp | |

### `users` (extended)
| Column | Notes |
|--------|-------|
| `role_slug` | `super_admin`, `editor`, `faculty_editor` |
| `is_locked` | bool |
| `failed_login_attempts` | int |
| `last_login_at` | timestamp |
| `faculty_scope_slug` | nullable, scoped access for `faculty_editor` |

### Legacy / Continuity Tables
| Table | Purpose |
|-------|---------|
| `legacy_exact_redirects` | Exact path → destination redirect rules |
| `legacy_pattern_rules` | Regex pattern redirect rules with priority |
| `unresolved_legacy_requests` | Append-only 404 log |
| `legacy_file_inventory` | Legacy file path → current media asset mapping |

---

## 9. Homepage Section Keys & What Each Renders

| Key | Section Type | Key Payload Fields |
|-----|--------------|--------------------|
| `hero` | Full-width hero | `eyebrow`, `badge`, `title`, `subtitle`, `summary`, `body`, `backgroundImageUrl`, `primaryAction`, `secondaryAction` |
| `hero_stats` | Stats grid | `title`, `stats[]` (value, label, prefix, suffix, helperText) |
| `academic_faculties` | Feature cards | `title`, `subtitle`, `sectionAction`, `items[]` (title, summary, typeTag, metric, action) |
| `achievements_highlights` | Feature cards | same as academic_faculties |
| `university_news` | Article cards | `title`, `sectionAction`, `articles[]` (ArticleCardDTO) |
| `research_studies` | Research cards | `title`, `sectionAction`, `researchItems[]` (ResearchCardDTO) |
| `events_activities` | Events + sidebar | `title`, `events[]` (EventCardDTO), `content.calendarHighlights[]` |
| `medical_facilities_services` | Feature cards | same as academic_faculties |
| `bottom_stats` | Stats grid | same as hero_stats |
| `footer` | Footer block | `content.brandBlock`, `content.contactBlock`, `content.mapEmbed`, `content.legalLinks[]`, `footerColumns[]`, `contactLinks[]`, `socialLinks[]`, `content.copyrightText` |

The `footer` section is **excluded from the main content loop** and rendered separately in the layout footer.

---

## 10. Contact Form Endpoint

```
POST /{locale}/contact
Content-Type: application/json
```

**Request body:**
```json
{ "email": "user@example.com" }
```

**Validation:** `email` required, RFC-valid, max 255 chars.  
**Rate limit:** `throttle:public-form`  
**Response (200):**
```json
{ "submitted": true, "locale": "ar", "email": "user@example.com" }
```

> Note: The current implementation is a stub — it returns the submitted data but does not persist or send anything. Full CRM integration is out of scope for this phase.

---

## 11. Admin Panel

**URL:** `/admin`  
**Auth:** Session-based, separate guard. Login at `/admin/login`.  
**Seed credentials:** configured through `ADMIN_EMAIL` and `ADMIN_PASSWORD`; placeholder/default passwords are local-development only.

### Filament Resources
| Resource | URL | Roles |
|----------|-----|-------|
| Pages | `/admin/pages` | super_admin, editor |
| Media Assets | `/admin/media-assets` | super_admin, editor, faculty_editor |
| Users | `/admin/users` | super_admin only |
| Audit Logs | `/admin/audit-logs` | super_admin only |

### Filament Custom Pages
| Page | URL | Roles |
|------|-----|-------|
| Manage Homepage | `/admin/manage-homepage` | super_admin, editor |
| Manage Menu | `/admin/manage-menu` | super_admin, editor |
| Manage Settings | `/admin/manage-settings` | super_admin, editor |

### Role Matrix
| Feature | super_admin | editor | faculty_editor |
|---------|-------------|--------|----------------|
| Homepage | ✅ | ✅ | ❌ |
| Pages | ✅ | ✅ | scoped only |
| Menu | ✅ | ✅ | ❌ |
| Settings | ✅ | ✅ | ❌ |
| Media | ✅ | ✅ | scoped only |
| Users | ✅ | ❌ | ❌ |
| Audit Logs | ✅ | ❌ | ❌ |

---

## 12. Cache Behaviour

- Public pages cached with Redis tags: `['public-pages', 'public-shell', 'public-shell:{locale}']`
- Cache TTL: `config('cache.public_page_ttl', 300)` seconds (default 5 min)
- Cache key: `public_pages:sha1(locale|path|queryString)`
- **Bypassed for:** authenticated users, admin routes, preview routes, non-GET requests
- **Invalidated on:** homepage publish, page publish/unpublish, settings update
- **Warm with:** `php artisan cache:warm`
- **Requires:** `CACHE_STORE=redis` (or `array` for local dev — database driver does NOT support tags)

---

## 13. SEO / Sitemap

- `GET /sitemap.xml` — XML sitemap of all published pages with hreflang alternates
- `GET /robots.txt` — `Allow: /` in production, `Disallow: /` in all other environments
- Canonical URLs and hreflang are resolved by `SeoMetadataService`
- Each page has per-locale SEO rows in `page_seo_meta`

---

## 14. Redirect Continuity

`RedirectContinuityMiddleware` runs globally (before locale middleware):
1. Checks `legacy_exact_redirects` for exact path match
2. Falls back to `legacy_pattern_rules` (regex, ordered by priority)
3. Loop detection: max 5 hops
4. File paths (with extension) also checked against `legacy_file_inventory`
5. Unresolved 404s logged to `unresolved_legacy_requests` in `terminate()`

Skipped for: `/admin/*`, `/livewire/*`, `/filament/*`

---

## 15. Locale & Direction Rules

- Default locale: `ar` (RTL)
- Secondary locale: `en` (LTR)
- Root `/` redirects to `/ar`
- Locale is set from the URL prefix `/{locale}/`
- Admin panel is forced to `en` locale via `AdminLocaleMiddleware`
- All CMS content has explicit AR and EN translation rows — no fallback chaining

---

## 16. i18n Translation Keys Used in Views

Views use `__('public.xxx')` keys. These need to exist in `lang/ar.json` and `lang/en.json`.

Keys referenced in Blade templates:
```
public.preview_mode
public.expires
public.student_portal
public.staff_access
public.quick_links
public.contact_heading
public.campus_map
public.navigation_heading
public.connect_heading
public.call_to_action
public.page_shell
public.event_highlights
public.hero_stats          (section label)
public.bottom_stats        (section label)
public.section_labels.academic_faculties
public.section_labels.achievements_highlights
public.section_labels.university_news
public.section_labels.research_studies
public.section_labels.events_activities
public.section_labels.medical_facilities_services
```

---

## 17. Service Interfaces Quick Reference

| Interface | Key Methods |
|-----------|-------------|
| `HomepageSectionServiceInterface` | `getPublicHomepage(locale)`, `getSections()`, `getSectionByKey(key)`, `updateSection(key, payload, locale)`, `toggleSection(key, enabled)` |
| `NavigationServiceInterface` | `getFullNavigationPayload(locale, currentPath?)`, `getHeaderNavigation(locale)`, `getFooterNavigation(locale)`, `getUtilityNavigation(locale)` |
| `PageServiceInterface` | `getPublicPageBySlug(slug, locale)`, `getAdminEditorPayload(pageId)`, `publish(pageId, userId)`, `unpublish(pageId, userId)`, `buildBreadcrumbPayload(pageId, locale)`, `buildPreviewPayload(pageId, locale)` |
| `SettingsServiceInterface` | `getPublicSettings(locale)`, `getGroup(group, locale?)`, `updateGroup(dto, userId?)`, `getEmergencyNotice(locale)`, `getFooterSettings(locale)` |
| `SeoMetadataServiceInterface` | `buildForPage(pageId, locale)`, `buildFallback(locale, context)`, `resolveCanonical(path, locale)`, `resolveHreflang(localePathMap)` |
| `PreviewServiceInterface` | `createToken(targetType, targetId, locale, userId)`, `resolveToken(token, locale?)`, `validateToken(token)`, `invalidateToken(token)` |
| `MediaServiceInterface` | `upload(payload)`, `delete(mediaId)`, `updateMetadata(mediaId, metadata)`, `list(filters)` |
| `MenuServiceInterface` | `getHeaderTree(locale)`, `getFooterTree(locale)`, `getUtilityTree(locale)`, `createItem(payload)`, `updateItem(id, payload)`, `reorderTree(treeType, tree)` |
| `ContinuityServiceInterface` | `resolveRedirect(path, queryString?)`, `resolveFileContinuity(path)`, `logUnresolved(dto)` |
| `SitemapServiceInterface` | `renderXml()` |

---

## 18. Quick Start

```bash
git checkout PX08
composer install
cp .env.example .env
# Set DB credentials, then:
# CACHE_STORE=array  (or redis if available)
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve
```

- Public site: `http://localhost:8000/ar`
- Admin panel: `http://localhost:8000/admin`
- Admin login: use `ADMIN_EMAIL` and `ADMIN_PASSWORD` from `.env`
- Run tests: `php artisan test --exclude-group=property`
- Launch check: `php artisan launch:validate`
- Cache warm: `php artisan cache:warm`
