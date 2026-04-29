# Launch Readiness Checklist

## 1. Routing

- [ ] `GET /` redirects to `/ar`
- [ ] `GET /ar` renders Arabic homepage from published data
- [ ] `GET /en` renders English homepage from published data
- [ ] `GET /{locale}/{slug}` resolves published landing pages
- [ ] `GET /{locale}/{slug}` returns 404 for draft/disabled/unpublished pages
- [ ] Admin routes (`/admin/*`) require authentication
- [ ] Filament panel accessible at `/admin`

## 2. Locale

- [ ] Default locale is `ar`
- [ ] Arabic pages render with `dir="rtl"`
- [ ] English pages render with `dir="ltr"`
- [ ] `Content-Language` header matches the active locale
- [ ] Language-switch URLs preserve page context across locales

## 3. SEO

- [ ] Canonical URLs are absolute and locale-correct on all public pages
- [ ] Hreflang tags are reciprocal (ar ↔ en) on pages with both translations
- [ ] Meta title, description, OG tags render from page-specific SEO data
- [ ] Fallback SEO defaults apply when page-specific data is missing
- [ ] Robots meta tag renders when a directive is set
- [ ] `GET /sitemap.xml` returns valid XML with only published pages
- [ ] `GET /robots.txt` returns environment-appropriate content
- [ ] Sitemap excludes draft, scheduled, disabled, admin, and preview URLs

## 4. Continuity

- [ ] Exact legacy redirect rules resolve with correct status code and destination
- [ ] Pattern-based redirect rules resolve with capture group substitution
- [ ] Exact rules take priority over pattern rules when both match
- [ ] No redirect loops exist (max 5 hops enforced)
- [ ] Unresolved legacy requests are logged with URL, method, type, referrer, locale
- [ ] `continuity:validate-redirects` reports no conflicts or duplicates
- [ ] Redirect continuity middleware skips `/admin`, `/livewire`, `/filament` prefixes

## 5. File/Media

- [ ] Mapped legacy file paths resolve to current delivery paths
- [ ] Unmapped file requests are logged structurally
- [ ] Media uploads validate type, size, and dimensions
- [ ] Existing SVG media assets reviewed before launch with `SELECT id, path FROM media_assets WHERE mime_type = 'image/svg+xml' AND deleted_at IS NULL;`
- [ ] Media metadata (title, alt text, caption) editable in AR/EN
- [ ] Soft-deleted media assets excluded from public queries

## 6. Admin

- [ ] Homepage editor shows all 11 fixed sections with AR/EN tabs
- [ ] Draft save, preview, publish, schedule, unpublish actions work
- [ ] Page resource supports CRUD with metadata, translations, SEO per locale
- [ ] Menu builder enforces max depth of 2
- [ ] Media library supports upload, search, filter, edit, delete
- [ ] Settings page shows grouped forms (utility nav, footer, emergency, contact, social, SEO)
- [ ] User management restricted to `super_admin` role
- [ ] Audit log viewer is read-only and filterable
- [ ] Role-based visibility: `super_admin` sees all, `editor` sees allowed areas, `faculty_editor` sees scoped areas

## 7. Cache

- [ ] Public page cache keys include locale
- [ ] Cache bypassed for authenticated users, admin routes, preview requests, non-GET
- [ ] Homepage publish invalidates homepage cache for all locales
- [ ] Page publish invalidates affected page cache
- [ ] Settings update invalidates affected cache groups
- [ ] Menu update invalidates navigation cache
- [ ] `X-Cache` header present on public responses (HIT/MISS/BYPASS)
- [ ] `cache:warm` command warms homepage, navigation, settings, and sitemap

## 8. Audit

- [ ] All admin write operations create audit log entries
- [ ] Audit entries record action, entity type, entity ID, user, timestamp, metadata
- [ ] No passwords, tokens, or credentials in audit metadata
- [ ] IP logging limited to auth-related events only

## 9. Staging Noindex

- [ ] Non-production `robots.txt` includes `Disallow: /` or noindex directive
- [ ] Production `robots.txt` allows indexing and references sitemap

## 10. Rollback Readiness

- [ ] Database snapshot taken before cutover
- [ ] Rollback procedure documented and tested
- [ ] Continuity rollback expectations defined
- [ ] Unresolved continuity spike monitoring in place
- [ ] `launch:validate` command passes against staging data
