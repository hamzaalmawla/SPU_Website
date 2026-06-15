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

## 10. Production Environment

- [ ] Production env follows `Docs/production-env-baseline.md`
- [ ] `.env` and `.env.production` are ignored and untracked
- [ ] `APP_ENV=production`, `APP_DEBUG=false`, and `APP_URL` is canonical HTTPS
- [ ] `APP_KEY`, `DB_PASSWORD`, and `ADMIN_PASSWORD` are unique production secrets stored outside git
- [ ] Application DB user is least-privileged and is not `root`
- [ ] Session cookies use `SESSION_SECURE_COOKIE=true`, `SESSION_HTTP_ONLY=true`, and `SESSION_SAME_SITE=lax` or stricter

## 11. Rollback Readiness

- [ ] Database snapshot taken before cutover
- [ ] Rollback procedure documented and tested
- [ ] Continuity rollback expectations defined
- [ ] Unresolved continuity spike monitoring in place
- [ ] `launch:validate` command passes against staging data

## 12. Validation Command Matrix

Use this matrix to select the minimum correct verification set for each change type. Release-critical changes should run the full release-candidate gate even when targeted checks pass.

### 12.1 Quick Local Gates

| Change Type | Required Commands | Completion Evidence |
| --- | --- | --- |
| Documentation only | `git diff --check` | No whitespace or conflict-marker errors. |
| Public Blade only | `php artisan test tests/Feature/HomepageBlade` and `php artisan test tests/Feature/PublicRuntimeTest.php` | Homepage/public render assertions pass. |
| Vite, CSS, or JavaScript | `npm run build` and `php artisan test tests/Feature/PublicRuntimeTest.php tests/Feature/HomepageBlade` | Build exits 0 with no unexpected warnings; public render assertions pass. |
| Page publish workflow | `php artisan test --filter=PageService` and `php artisan test tests/Feature/Integration/PageServiceIntegrationTest.php` | Valid and invalid publish paths pass. |
| Homepage CMS workflow | `php artisan test tests/Feature/HomepageCmsWorkflowTest.php` | Draft, preview, publish, schedule, unpublish, cache, and audit assertions pass. |
| Admin or Filament boundary | `php artisan test tests/Feature/ArchitectureGuardTest.php` and `php artisan test tests/Feature/PX06` | Architecture guard and role visibility/resource tests pass. |
| Middleware, provider, or route changes | `php artisan route:list` and `php artisan test tests/Feature/MiddlewarePipelineTest.php` | Laravel boots routes; middleware behavior tests pass. |
| Media upload or file continuity | `php artisan test --filter=Media` and `php artisan test tests/Feature/PX05/FileContinuityTest.php` | Upload and file continuity assertions pass. |
| Redirect or SEO continuity | `php artisan test tests/Feature/PX05/RedirectContinuityTest.php`, `php artisan test tests/Feature/PX07/RedirectValidationTest.php`, and `php artisan test tests/Feature/PX07/SeoValidationTest.php` | Redirect, conflict, and SEO validation tests pass. |
| Security-sensitive change | Relevant targeted tests plus `php artisan test` | Targeted behavior and full regression suite pass. |

### 12.2 Release-Candidate Gate

Run this full gate before approving a launch candidate or merging release-critical work.

```bash
php artisan test
npm run build
php artisan route:list
php artisan config:cache
php artisan optimize:clear
```

Conditional staging gate:

```bash
php artisan launch:validate --environment=production
php artisan cache:warm --include-sitemap
```

Only run the conditional staging gate against an environment with representative data and safe production-like configuration.

### 12.3 Manual Browser Gate

Manual QA is required before public launch even when automated tests pass.

| Page | Viewport | Required Checks |
| --- | --- | --- |
| `/ar` | Mobile | RTL layout, mobile menu, hero, sections, sliders, events, footer. |
| `/ar` | Desktop | Navigation, hero LCP, sliders, reveal animations, footer, no console errors. |
| `/en` | Mobile | LTR layout, mobile menu, hero, sections, sliders, events, footer. |
| `/en` | Desktop | Navigation, hero LCP, sliders, reveal animations, footer, no console errors. |
| Public landing page | Mobile and desktop | Generic shell works without homepage-only JavaScript errors. |
| `/admin/login` | Mobile and desktop | Bilingual brand, login form, locale switcher, no console errors. |

### 12.4 Evidence Rules

| Evidence Type | Rule |
| --- | --- |
| Automated command | Record command, pass/fail result, date, and any warning notes. |
| Browser QA | Record page, viewport, browser, result, and any screenshots or Lighthouse summary if available. |
| Staging-only check | Do not mark complete from local results. Staging evidence must come from staging. |
| Deferred scope | Record the decision gate and owner instead of marking complete. |

## 13. Latest Automated Release Evidence

Evidence date: 2026-06-15

| Gate | Command | Result | Status Impact |
| --- | --- | --- | --- |
| Full regression suite | `php artisan test` | Passed: 3395 tests, 15259 assertions, 177.89s. | Automated full-suite gate may be marked complete for this evidence date. |
| Frontend production build | `npm run build` | Passed: Vite build completed in 1.81s with no unexpected warnings. | Automated frontend-build gate may be marked complete for this evidence date. |
| Route boot | `php artisan route:list` | Passed: 67 routes listed. | Automated route/provider/controller boot gate may be marked complete for this evidence date. |

Evidence boundaries:

| Boundary | Status |
| --- | --- |
| Manual browser QA | Not completed by this evidence. Keep manual browser checks unchecked until performed. |
| Staging validation | Not completed by this evidence. Keep staging-only checks unchecked until run against staging data. |
| Rollback review | Not completed by this evidence. Keep rollback checks unchecked until reviewed/tested. |
| Product sign-off | Not completed by this evidence. Keep product sign-off unchecked until product/design approval. |
| Security and performance review | Automated tests support confidence but do not replace focused review. Keep review gates unchecked until reviewed. |
