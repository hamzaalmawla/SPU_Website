# Launch Readiness Checklist

Current execution status is maintained in
`Docs/CURRENT_REMEDIATION_EXECUTION_CHECKLIST.md`. This checklist defines gates;
unchecked items remain unverified. No deployment or sign-off is implied by local
implementation or by historical evidence below.

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
- [ ] Every privileged production role has confirmed TOTP; enrollment, challenge, recovery, and logout escape paths are tested

## 7. Cache

Current decision: additional application/full-page caching optimization is
deferred. nginx private/full-page caching of dynamic Laravel responses must remain
disabled. Do not check cache items based on configuration inspection alone; verify
that sessions, CSRF, previews, drafts, forms, and personalized/admin responses are
never replayed by an edge cache.

- [ ] Public page cache keys include locale
- [ ] Cache bypassed for authenticated users, admin routes, preview requests, non-GET
- [ ] Homepage publish invalidates homepage cache for all locales
- [ ] Page publish invalidates affected page cache
- [ ] Settings update invalidates affected cache groups
- [ ] Menu update invalidates navigation cache
- [ ] `X-Cache` header present on public responses (HIT/MISS/BYPASS)
- [ ] `cache:warm` command warms homepage, navigation, settings, and sitemap
- [ ] nginx `fastcgi_cache`/`proxy_cache` is disabled or bypassed for all dynamic Laravel responses

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
- [ ] `MAIL_MAILER` uses a verified production transport and `MAIL_FROM_ADDRESS` is authorized by the mail provider
- [ ] `FORM_ADMIN_RECIPIENTS` is reviewed, or eligible admin/editor recipients are confirmed
- [ ] HR account is provisioned explicitly with `HrUserSeeder`, and its credentials are stored outside git
- [ ] A supervised queue worker processes form receipts, status updates, and staff notifications
- [ ] `failed_jobs` monitoring and queue retry procedures are tested
- [ ] Scheduler/queue cron uses `flock`; scheduler-output freshness and failed-job alerts are tested
- [ ] Daily rotating logs and retention match the production environment baseline
- [ ] Exact trusted portal hosts are reviewed and unapproved redirect destinations fail closed
- [ ] Release artifact SHA-256 manifest, lockfiles, Vite manifest, dependency audits, and deployed release identity are verified

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
| Admin auth, 2FA, or admin locale | `php artisan test tests/Feature/AdminAuthFlowTest.php` and `php artisan test tests/Feature/TwoFactorChallengeTest.php` | Login branding, locale switch, lockout/logout, and 2FA challenge assertions pass. |
| Middleware, provider, or route changes | `php artisan route:list` and `php artisan test tests/Feature/MiddlewarePipelineTest.php` | Laravel boots routes; middleware behavior tests pass. |
| Media upload or file continuity | `php artisan test --filter=Media` and `php artisan test tests/Feature/PX05/FileContinuityTest.php` | Upload and file continuity assertions pass. |
| Redirect or SEO continuity | `php artisan test tests/Feature/PX05/RedirectContinuityTest.php`, `php artisan test tests/Feature/PX07/RedirectValidationTest.php`, `php artisan test tests/Feature/PX05/SeoRenderingTest.php`, and `php artisan test tests/Feature/PX07/SeoValidationTest.php` | Redirect, conflict, SEO rendering, and SEO validation tests pass. |
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

Evidence date: 2026-06-16 (historical, superseded for current release status)

| Gate | Command | Result | Status Impact |
| --- | --- | --- | --- |
| Full regression suite | `php artisan test` | Passed: 3425 tests, 15526 assertions, 160.41s. | Automated full-suite gate may be marked complete for this evidence date. |
| Frontend production build | `npm run build` | Passed: Vite build completed in 1.50s with no unexpected warnings. Public app JS is 64.59 kB / 21.32 kB gzip. | Automated frontend-build gate may be marked complete for this evidence date. |
| Route boot | `php artisan route:list` | Passed: 67 routes listed. | Automated route/provider/controller boot gate may be marked complete for this evidence date. |
| Diff hygiene | `git diff --check` | Passed with no output. | Whitespace/conflict-marker gate may be marked complete for this evidence date. |

Evidence boundaries:

| Boundary | Status |
| --- | --- |
| Manual browser QA | Partially closed 2026-09-02. An automated rendered-page audit ran against the live site (`tests/browser/accessibility-audit.mjs`) and its findings were fixed; see the note below for exactly what that did and did not cover. Screen-reader QA and human sign-off remain unperformed — keep those unchecked. |
| Staging validation | Not completed by this evidence. Keep staging-only checks unchecked until run against staging data. |
| Rollback review | Not completed by this evidence. Keep rollback checks unchecked until reviewed/tested. |
| Product sign-off | Not completed by this evidence. Keep product sign-off unchecked until product/design approval. |
| Security and performance review | Automated tests support confidence but do not replace focused review. Keep review gates unchecked until reviewed. |

Rendered-page audit (2026-09-02). `tests/browser/accessibility-audit.mjs` drives
headless Chrome over the DevTools Protocol against 13 routes in both locales at
desktop and mobile widths, checking keyboard traversal, focus visibility, layout
overflow, computed contrast, reduced motion, console errors and failed requests.

What it found: the faculties hub declared `x-data` as an inline object literal,
which the Alpine CSP build cannot evaluate, so its gallery was inert and every
`/facilities` page threw two errors into the console; and three CMS faculty
accent colours failed WCAG AA as 11px text on white. Both fixed.

What it confirmed: 14 keyboard tab stops on every route with a visible focus
indicator on each, no horizontal overflow in RTL or LTR at either width, no
animation surviving `prefers-reduced-motion`, no failed requests, no image
missing an `alt` attribute, no control without an accessible name, and one `h1`
and a `<main>` landmark per page.

Contrast over background images, measured 2026-09-02. 33 elements sit on a
photograph, where computed style cannot answer. **30 are now measured** from
pixels: the element is photographed as rendered and again with its glyphs
transparent, the two differenced to find the letters, and the text colour
compared against the background under them. Judged on the fifth percentile,
since one window reflection under one letter is not what "hard to read" means.

Sampled over three rounds, because hero backgrounds move — the homepage rotates
its photograph every five seconds. The judgement is the worst round, since the
requirement has to hold on every slide.

Every page is gated on a control the method must reproduce before any of its
numbers are reported. Where a real element with resolvable contrast exists on
the page it is used, which is independent evidence; where none does, or where
the one that does disagrees, a swatch of known colours is placed on the hero
image itself and measured there. That proves the pixel path works under the same
conditions but is not a second opinion, and the output labels which was used.
`tests/browser/fixtures/` proves the check can still fail — it could not,
silently, until that fixture found it.

### What the audit was missing, found 3 September

The audit reported "No findings" on a page rendering an invisible glyph. Four
separate limits had to line up for that, and each one read as a pass:

1. **It looked at one screenful.** `elementsFromPoint` only sees the viewport,
   so the collector required elements to be inside it — and nothing scrolled.
   Every route was checked above the fold and nowhere else. It now sweeps down
   each page a screenful at a time, and the report states how far it reached.
2. **It skipped text shorter than three characters.** That reads as "skip the
   noise" and skipped the `+` in "5k+" — 36.8px, and carrying meaning.
3. **A disagreeing control discarded the whole page.** The synthetic swatch was
   built only where a page offered no organic candidate, so a candidate that
   disagreed by 3.4% threw away every pixel measurement on that route. It is now
   the fallback for disagreement too, which is where it is most useful: its
   expected value is known by construction, so it can say whether the method is
   unsound or merely that one candidate was poor.
4. **Classification depended on load timing.** A hero photograph that had not
   painted yet is not a background image to `elementsFromPoint`, so the same
   route reported two elements over an image on one run and none on the next.
   The sweep now waits for stylesheets, fonts and images before it starts.

A fifth was introduced by the fix and caught before it shipped: `scroll-behavior:
smooth` meant a scrolled element was photographed mid-flight, and the page moving
between the two captures turned the whole frame into apparent glyph pixels — the
synthetic control reported 6.26:1 for a swatch that is 11.72:1 by construction.
All scrolling is now instant and confirmed at rest before anything is
photographed.

`tests/browser/fixtures/` carries the below-the-fold single-glyph case, so this
one cannot come back silently either.

**What that changed, measured 3 September against v2.spu.edu.sy:** coverage went
from one screen to 3–19 screens per route; text on images from 33 elements found
and 30 measured to **79 found and 27 measured, with none discarded** — every page
gated where before nine were thrown away. Runtime 6 minutes.

It also stopped reporting text nobody can see. A card caption held at
`opacity: 0` until hover read as white-on-white at 1:1, and `.sr-only` text
clipped to 1×1 read as black on brand red. Both are correct markup; both are now
skipped, because a report that cries wolf is a report that gets ignored.

**New, from the deeper sweep, all below the fold and all fixed here:** the
faculties-hub card numbers `03`, `04` and `07` were rendering the raw CMS accent
at 12px on white — 2.3:1, 2.26:1 and 3.48:1 against 4.5:1. `AccessibleColor` had
been applied to the card's other text use and not to this one. Both now take
`$accentText`; the tints and rules keep the raw accent, where it is fine.

### A second round, 4 September, after the first was deployed

Deploying the first round and re-running the audit found more, including one
defect the first round caused.

**Caused by the fix.** Defining `--color-spu-gold` woke a dormant class.
`public/news/index.blade.php` had carried `text-spu-gold` on its `h1` since
`cf1cef0`, and because the token did not exist Tailwind emitted no rule, so the
heading inherited white and measured 2.37:1. Adding the token made it gold and
**1.27:1** — worse than what it replaced. The class is removed; the heading is
white again, and the `.page-hero` scrim behind it remains the open design item
it already was.

That is worth stating plainly: adding a design token is not a safe no-op. Any
class name that was previously inert starts rendering the moment the token
backing it exists, in every template that already spelled it.

**The same defect, two more places.** A brand colour used as text where it does
not have the contrast for it:

| Element | Measured | Needs | Fix |
| --- | --- | --- | --- |
| Homepage faculty card CTA, 13px on white | 2.3:1 | 4.5:1 | darkened accent, as the hub |
| Medical-facilities stat `+`, 30px on navy | 1.2:1 | 3:1 | `text-spu-gold`, as the hub |
| Homepage stats card `+`, 20.8px on white | 2.26:1 | 3:1 | `--card-accent-text` |

**Two false positives the sweep introduced, and both are fixed in the checker
rather than worked around.** The homepage "choose your path" cards hold their
back face at `translateY(100%)` inside an overflow-hidden card: `visibility:
visible`, `opacity: 1`, a real rect, sitting entirely below the card over the
white page. Sampling its centre found white behind white text and reported 1:1
on a panel no reader has seen. The collector now skips text clipped out of its
own container, as it already skipped text at `opacity: 0` and `.sr-only` text
clipped to 1×1.

**Judged and left.** The events calendar renders adjacent-month day numbers in
`#d0d0d0` at 1.54:1. They are inactive padding cells in a month grid, not
content, and WCAG does not require contrast for inactive components. Recorded
here so the next reader knows it was decided rather than missed.

**And one hazard worth knowing about, found the hard way.** Adding a raw PHP
block to `academic-faculties.blade.php` took the whole homepage down with
`syntax error, unexpected token "endforeach"`. Blade extracts raw PHP blocks
before it compiles anything else, and its pattern runs from the first opener in
a file to the first closer — that file already opened a single-expression form
on line 7, so the new block closed *that* one and forty lines of markup between
them were emitted as raw PHP. Naming either directive inside a `{{-- --}}`
comment does the same thing, because the extraction runs before comments are
stripped.

The suite caught it — 54 failures — but not one of them said why, and the cause
came out of `storage/logs/laravel.log`. `BladeTemplatesCompileTest` now compiles
every template and names the file and the parse error in a single line. It was
checked against the real bug: reintroduce the block form and it fails, restore
the fix and it passes.

Fixed by this work: the homepage hero buttons, 2.98:1 to 9.53:1, via a
directional scrim under the text column.

**Open, and a design decision rather than a defect to fix quietly:**

| Element | Measured (5th pct, worst round) | Needs |
| --- | --- | --- |
| Homepage h1, AR and EN | 1.88–2.03:1, varying to 2.18:1 by slide | 3:1 |
| Homepage lead paragraph, EN | **1.28:1, varying to 12.01:1 by slide** | 4.5:1 |
| News page h1 and lead, AR and EN | 2.37:1, stable | 3:1 |
| Faculties hub h1, AR and EN | 2.72–2.86:1, stable | 3:1 |
| "Explore Campus Map" link, AR and EN | 1.85–1.92:1, stable | 4.5:1 |

The homepage row is the informative one. A spread from 1.28:1 to 12.01:1 on the
same element means **specific hero photographs are the problem, not the hero
design** — on some slides the same text is comfortably legible. That points at
replacing or darkening the offending images rather than reworking the component.

The stable rows are different: `/news` uses the shared `.page-hero` scrim, which
fades to nothing over 16rem and is simply too light for its photograph. The
faculties hub places its text over the panoramic gallery the design deliberately
keeps visible.

Deepening the homepage scrim further was tried and abandoned: it moved the
heading from 2.07:1 to only 2.44:1 and reaching 3:1 that way needs the wash near
opaque, which removes the photograph the hero exists to show. So the options are
darker or replaced images, a narrower text column that stays over the dark
region, or a scrim behind the text block alone. All change how the pages look,
which makes them a call for whoever owns the design.

Re-measure after any of them with `node tests/browser/accessibility-audit.mjs`.

### Regression introduced and fixed, 3–4 September

`5c6b539` changed the faculties-hub stat `+` from the hard-coded
`text-[#e2b864]` to the theme token `text-spu-red`. Reaching for a token instead
of a magic hex is the right instinct, and `--color-spu-red` (`#6f1616`) is
correct everywhere else it is used — it is a dark red for white cards, which is
where the publication and research headings use it. This one `+` is the
exception: it sits on a dark photograph, not a card.

| Element | Measured (5th pct) | Needs |
| --- | --- | --- |
| Faculties hub stat `+`, 36.8px, as shipped | **1.09:1** (median 1.20:1) | 3:1 |
| The same `+` after the fix, deployed | **3.32:1** (median 7.54:1) | 3:1 |
| The white digits beside it, same backdrop, as a control | 14.04:1 | 3:1 |

Measured on the live page with the two-capture method the audit uses, over
3,155 glyph pixels. The control on the same backdrop rules out a measurement
fault: the digits were fine, only the `+` was not. "5k+" rendered as "5k"
followed by an invisible mark, and the `+` carries meaning.

**The audit did not catch it,** for two reasons worth recording. The stat strip
sits below the first viewport, which is the only region contrast is measured in.
And on every run since the change the hub's organic control disagreed by 3.4%
(computed 11.65:1, pixels 11.26:1), so the page's pixel measurements were
discarded rather than reported — correct behaviour, but it means the two hub
rows in the table above are unrefreshed since 2 September.

**The fix keeps the token and corrects the value.** `--color-spu-gold`
(`#e2b864`) now exists alongside `--color-spu-red` in `foundation.css`, and the
hub uses `text-spu-gold` for the `+` and `bg-spu-gold` for the hero dot and rule
that already carried the same hex inline. Reverting to the arbitrary value would
have restored the pixels and left the next person with nothing to reach for; the
token is the accent for dark surfaces, and the comment beside it says so.

**Deployed and re-measured.** Deploy 29 carries the fix. The same glyph, same
method, same 36.8px, on the live page: 3.32:1 across the worst twentieth of its
pixels and 7.54:1 at the median, against the 3:1 it needs. The median matches
the value predicted from the backdrop luminance before the deploy, which is the
check that the two measurements are of the same thing.

**What it is not.** It drives a browser, not a screen reader. It cannot tell you
how NVDA or VoiceOver announces this site, which is what
`FRONTEND_ROUTE_PARITY_MATRIX.md` asks for, and it is not sign-off. It also
could not compute contrast for 33 text elements that sit on background images —
that needs a human eye, and is a gap rather than a pass. Routes outside the
audited 13, the admin panel, and forms under submission were not exercised.

Current remediation boundary (2026-08-21): `php artisan test` passed 4,240 tests
with 24,442 assertions, `npm test` passed 24 tests, `npm run build` passed, and
local Composer/npm dependency audits were clean. Accessibility changes still await
manual browser QA; sitemap and canonical host/proxy/front-controller fixes await
deployment and host verification; cPanel shell is disabled; OPcache, gzip, and
PHP-FPM changes remain host/root tasks. Additional caching optimization is deferred
and nginx private/full-page caching must remain disabled. These local results do not
constitute staging validation, deployment evidence, or launch approval.
