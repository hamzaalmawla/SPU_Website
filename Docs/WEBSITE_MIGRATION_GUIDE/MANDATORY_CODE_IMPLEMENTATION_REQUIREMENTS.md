# Mandatory Code Implementation Requirements
## Exact Technical Work The Student Must Build To Avoid Avoidable Ranking Loss

## Purpose

This document explains, in implementation detail, what the student must build in code before the new public SPU website can safely replace the old one.

This is the most important developer-facing migration document in the package.

It is not a general SEO checklist.

It is a build manual for the launch-critical code requirements that protect:

- Google rankings
- Bing visibility
- file and PDF discoverability
- multilingual clarity
- the broader institutional web footprint that affects Webometrics outcomes

## How To Use This Document

The student should use this document in three ways:

1. as a build-order guide
2. as a feature-completeness checklist
3. as a release blocker checklist

For every launch-critical feature, the student should ask:

- does this requirement now exist in code?
- does it work on representative real URLs?
- does it work for Arabic and English?
- does it preserve old public value?

If the answer is no, the feature is not ready.

## Critical Project Reality

The current Laravel repository is still foundation stage.

Today it does not yet prove:

- real public pages
- real sitemap output
- real canonical output
- real `hreflang` output
- real legacy redirect support
- real file continuity support

Therefore, this document describes what the student must build before public cutover is allowed.

## Governing Rules

These rules apply to every implementation decision.

### Architecture rules

- business logic must live in services
- controllers must stay thin
- controllers must not query models directly
- public service methods must not return raw Eloquent models
- use interfaces in `app/Contracts`
- use DTOs in `app/DTOs`

### Migration rules

- preserve old public value before polishing visuals
- preserve out-of-scope valuable content through an archive or continuity layer
- do not delete old public files or pages casually
- do not guess redirect destinations carelessly

### Search-signal rules

- use server-side permanent redirects for permanent moves
- output one canonical URL per public page
- output `hreflang` when localized alternatives exist
- expose only canonical URLs in long-term sitemaps
- keep important links crawlable with real `href`
- do not rely on `robots.txt` as a substitute for `noindex`

## Launch-Critical Implementation Matrix

| # | Requirement | Launch critical | Must be implemented by |
|---|---|---|---|
| 1 | real public rendering layer | yes | routing, service, view layers |
| 2 | legacy redirect and URL continuity layer | yes | routing, middleware or resolver, service, storage |
| 3 | canonical URL generation | yes | SEO service and public templates |
| 4 | AR/EN `hreflang` generation | yes | SEO service and public templates |
| 5 | sitemap index and child sitemaps | yes | routes, service, XML rendering |
| 6 | crawlable navigation and internal linking | yes | navigation service and templates |
| 7 | correct status-code handling | yes | controllers, middleware, route handlers |
| 8 | public file and PDF continuity | yes | storage, file routes, headers, redirects |
| 9 | staging, draft, and preview protection | yes | preview service, auth, robots controls |
| 10 | unresolved legacy request logging | yes | resolver, logging, storage |
| 11 | structured data on correct templates | important | SEO service and templates |
| 12 | migration-focused automated tests | yes | feature and integration tests |

## Build Order

The student should build in this order:

1. real public destinations
2. legacy continuity
3. canonical and locale signals
4. sitemaps
5. file continuity
6. draft and preview protection
7. unresolved-request logging
8. structured data
9. test coverage

Do not reverse this order by prioritizing visual polish first.

## 1. Real Public Rendering Layer

### Objective

Create real public destinations so that important old URLs can redirect to meaningful new pages.

### What must exist in code

The student must implement a public rendering layer for:

- homepage
- current-scope landing pages
- Arabic page routes
- English page routes
- shared public shell and navigation

### Where it belongs in this repository

- routes in `routes/web.php`
- public orchestration in controllers if controllers are introduced
- business logic in services behind `app/Contracts`
- DTO-driven public payloads in `app/DTOs`
- templates in `resources/views`

### What behavior must exist

- a public request resolves to a real page payload
- Arabic and English requests can return different localized content
- the page can expose metadata and locale alternates
- the page can render in the navigation shell

### Minimum logic that must be present

- page lookup by slug and locale
- locale-aware route generation
- published-state filtering so drafts do not leak publicly
- not-found behavior for missing pages

### Edge cases to handle

- Arabic slug exists but English slug does not
- English page exists but Arabic page is still draft
- a page is unpublished after previously being public
- a page is scheduled for future publishing

### Things the student must never do

- leave only the Laravel welcome page
- hardcode CMS-managed public text into templates
- load public page data directly in controllers from Eloquent

### Done criteria

This requirement is done only when:

- representative AR and EN public pages exist
- those pages render through service-backed logic
- draft data does not leak
- not-found handling works predictably

## 2. Legacy Redirect And URL Continuity Layer

### Objective

Ensure old public URLs remain useful after migration.

### Why this is one of the most important requirements

For a site move with URL changes, redirects are one of the strongest technical signals that the old page has moved to a new location.

Without this layer, SPU risks losing:

- backlinks
- indexed historical URLs
- archive discoverability
- faculty and research page visibility
- document entry points

### What must exist in code

The student must build continuity support for:

- exact old URLs
- old `index.php` query-string URLs
- old subpath families such as faculty- or section-specific paths
- old file and PDF URLs
- unresolved old URLs that still need triage

### Where it belongs in this repository

- route layer in `routes/web.php`
- legacy resolver service behind an interface in `app/Contracts`
- implementation in `app/Services`
- persistence layer for exact redirect rows and unresolved hits
- optional middleware if route-first handling is not sufficient

### Recommended internal design

Use a layered resolution model:

1. exact redirect lookup
2. pattern-based legacy rules
3. legacy query parser and database-backed fallback
4. unresolved-request logging

### Exact logic the student must implement

- normalize old URLs so parameter order does not break matching
- support historical query-string shapes, not only clean paths
- resolve each old URL to one best destination
- redirect with permanent status if the move is permanent
- log unresolved requests for later triage

### Exact behavior the student must avoid

- sending many old URLs to the homepage
- ignoring old query-string URLs
- creating redirect chains such as old -> temporary page -> final page
- dropping difficult old content because it is outside sprint scope

### Data the student should preserve

- old source URL
- normalized legacy signature
- source section or entity type
- source locale if known
- target canonical URL
- redirect status
- notes for special cases

### Edge cases to handle

- old URL has inconsistent query parameter order
- old URL is linked with UTM parameters
- old URL points to a deleted section that now needs an archive destination
- old URL points to a file instead of HTML
- old URL points to Arabic content but the English version exists too

### Done criteria

This requirement is done only when:

- representative old HTML URLs redirect correctly
- representative old query-string URLs redirect correctly
- representative old file URLs redirect or resolve correctly
- unresolved old URLs are logged for investigation
- there are no known homepage fallback shortcuts for valuable content

## 3. Canonical URL Generation

### Objective

Tell search engines which public URL is the preferred version of each page.

### What must exist in code

Every public page must output:

- one canonical URL
- the canonical must match the intended public version
- the canonical must not include useless legacy parameters

### Where it belongs in this repository

- canonical generation logic in the SEO service layer
- canonical output in the public layout or page templates

### Exact logic the student must implement

- canonical URLs must be absolute
- canonical URLs must reflect the localized page, not another locale
- canonical URLs must not point to redirected URLs
- canonical URLs must not include `utm_` parameters or other tracking parameters

### Edge cases to handle

- same content accessible through both old and new URLs during transition
- alternate query parameters creating duplicate render paths
- page accessible from both a canonical slug and a preview token path
- old alias path still reachable temporarily

### What the student must never do

- leave canonical tags out
- point Arabic content to an English canonical unless no Arabic page exists
- use canonical tags to excuse broken redirect behavior

### Done criteria

- every representative public template outputs a correct canonical
- canonical values match the route actually intended for indexing
- duplicate parameterized versions do not leak into canonicals

## 4. AR/EN `hreflang` Generation

### Objective

Help search engines understand Arabic and English equivalents correctly.

### What must exist in code

For public page pairs where both localized versions exist, the student must output:

- Arabic self-reference
- English self-reference
- Arabic -> English alternate
- English -> Arabic alternate

### Where it belongs in this repository

- alternate-map generation in the SEO service
- tag rendering in the page layout or page head partial

### Exact logic the student must implement

- use fully-qualified absolute URLs
- output only valid alternates
- ensure reciprocal alternates
- keep the canonical within the same locale version
- optionally define `x-default` only if there is a deliberate non-locale default experience

### Edge cases to handle

- Arabic page exists but English page is unpublished
- English page exists but Arabic content is incomplete
- a localized page has no true equivalent, only a section landing page
- file documents have AR and EN variants

### What the student must never do

- output `hreflang` for a page pair that is not actually equivalent
- link Arabic directly to unrelated English content
- use relative URLs in `hreflang`
- forget reciprocal references

### Done criteria

- representative AR and EN page pairs output correct alternates
- single-locale pages do not output fake alternates
- localized public pages do not canonicalize across languages by mistake

## 5. Sitemap Index And Child Sitemaps

### Objective

Give search engines a clean, canonical inventory of public URLs that matter.

### What must exist in code

The student must build:

- one sitemap index endpoint
- child sitemap endpoints by content group or size
- XML output encoded correctly

### Where it belongs in this repository

- routes in `routes/web.php`
- generation logic in a dedicated sitemap service
- XML view or response builders for the sitemap documents

### Exact logic the student must implement

- use UTF-8 encoding
- use absolute URLs
- include only canonical public URLs
- split files if counts or size become too large
- expose the sitemap index from a stable public URL

### What should be included

- homepage
- public landing pages
- profile pages that remain public
- archive pages that remain public
- document/file pages if they are intended public canonicals

### What should not be included

- redirected old URLs
- draft pages
- preview URLs
- duplicate filter URLs
- non-canonical parameterized URLs

### Edge cases to handle

- same content group exceeds one sitemap file
- section is public in Arabic only for now
- file page is public but actual file should not be indexed directly

### Done criteria

- sitemap index is accessible
- child sitemap URLs are accessible
- URLs listed are canonical, absolute, and public
- representative newly launched pages are present in the correct sitemap

## 6. Crawlable Navigation And Internal Linking

### Objective

Make sure important public pages are discoverable through normal crawlable links.

### What must exist in code

The student must ensure critical navigation uses:

- HTML anchor tags
- real `href` attributes
- links to canonical URLs

### Where it belongs in this repository

- navigation service
- menu rendering templates
- homepage section templates
- footer and key internal-link modules

### Exact logic the student must implement

- menus render normal links
- homepage cards and CTAs link directly to destination pages
- section pages link to child or related pages
- language switches point to true localized equivalents

### Edge cases to handle

- a navigation item targets an external URL
- a page is public in one locale only
- a section is represented through archive preservation rather than rebuilt content

### What the student must never do

- depend on JavaScript-only click handlers for important public links
- make important pages reachable only by search or on-site filtering
- keep internal links pointing to old redirected URLs after launch

### Done criteria

- key destination pages are linked from crawlable templates
- navigation, footer, homepage, and language switch all point to intended canonicals
- no important page is orphaned

## 7. Correct Status-Code Handling

### Objective

Return the correct HTTP status for the actual public behavior.

### What must exist in code

The student must handle:

- permanent redirects
- temporary redirects
- live pages
- missing pages
- intentionally retired content

### Exact behavior required

- use `301` or `308` for permanent moves
- use `302` or `307` only when truly temporary
- use `404` or `410` only when there is no meaningful replacement
- do not return `200` for a page that should redirect

### Edge cases to handle

- old URL temporarily mapped during migration rehearsal
- legacy URL unresolved at launch
- file moved permanently versus file removed entirely
- preview route should not behave like a public page

### Done criteria

- representative redirects return permanent status when appropriate
- intentionally missing content returns the correct non-200 behavior
- no known soft-homepage patterns exist for important old URLs

## 8. Public File And PDF Continuity

### Objective

Preserve public files and documents that still matter.

### Why this is especially important for SPU

University visibility often depends on more than HTML pages. It also depends on:

- policy PDFs
- admissions documents
- research files
- faculty CVs
- archive documents

### What must exist in code

The student must implement:

- stable public file delivery
- stable new file URLs or file landing pages
- redirect support from old file URLs
- correct file response headers
- optional indexing controls for non-HTML files where needed

### Where it belongs in this repository

- media or file service layer
- public file routes or signed stable storage URLs
- optional header logic for `X-Robots-Tag`

### Exact logic the student must implement

- old important file URLs should redirect to the best new file destination
- file responses should return the correct MIME type
- AR and EN file relationships should be intentional
- sensitive files should not be exposed publicly

### Edge cases to handle

- file exists but filename changes
- HTML landing page exists for the document and may be the canonical experience
- AR and EN files exist for the same document
- file should stay public but not be indexed directly

### What the student must never do

- redirect old files to the homepage
- break download links silently
- assume files do not matter to search visibility
- block files in `robots.txt` while expecting deindexing to occur

### Done criteria

- representative important files open successfully
- representative old file URLs map correctly
- file headers and MIME behavior are correct
- sensitive files are not accidentally public

## 9. Staging, Draft, And Preview Protection

### Objective

Prevent non-public environments and draft content from leaking into search.

### What must exist in code

The student must protect:

- staging environments
- draft pages
- preview flows
- unpublished localized pages

### Exact logic the student must implement

- preview routes must be tokenized or protected
- draft content must not render on public URLs
- staging should be access-restricted whenever possible
- page-level or header-level `noindex` should be used only in accessible non-public scenarios

### Edge cases to handle

- preview page uses a public-like route shape
- staging site becomes publicly accessible accidentally
- published Arabic page exists while English remains draft

### What the student must never do

- rely on `robots.txt` alone to hide sensitive or preview content
- allow drafts to leak through the same code path as published content
- make preview tokens predictable

### Done criteria

- draft content cannot be discovered through public routes
- preview access is controlled
- staging is not indexable in practice

## 10. Unresolved Legacy Request Logging

### Objective

Detect migration misses before they damage rankings for too long.

### What must exist in code

If an old URL cannot be mapped, the code must log it for review.

### Where it belongs in this repository

- legacy resolver service
- persistence table or structured log sink
- admin or internal reporting workflow later if needed

### Exact data the student should capture

- host
- path
- raw query string
- normalized legacy signature
- referer if available
- first seen
- last seen
- hit count

### Exact logic the student must implement

- normalize requests before storing them
- deduplicate repeated hits on the same legacy signature
- make high-hit misses easy to review

### Edge cases to handle

- same missing URL hit with different tracking parameters
- same path but different locale intention
- bots and users hitting the same broken URL

### Done criteria

- unresolved legacy requests are visible to the team
- repeated misses do not disappear silently
- the team can prioritize fixes by frequency and value

## 11. Structured Data On Correct Templates

### Objective

Add markup that clarifies the site, not markup that decorates it.

### What must exist in code

The student should support:

- `WebSite` on the homepage where appropriate
- `Organization` on the homepage
- `BreadcrumbList` on section and detail templates
- `ProfilePage` where a page is genuinely a faculty, staff, or author profile

### Where it belongs in this repository

- SEO metadata or structured-data service
- template partials for page-type-specific head output

### Exact logic the student must implement

- markup must match visible content
- markup must match the real page type
- markup must remain consistent across templates

### Edge cases to handle

- page looks like a profile but is actually a list page
- page contains breadcrumbs visually but not in a stable hierarchy
- article-like page includes multiple authors

### What the student must never do

- apply the same schema blindly to every page
- use schema to compensate for weak content or broken routing
- output misleading fields just because the schema allows them

### Done criteria

- homepage identity markup is present and accurate
- representative section pages output accurate breadcrumbs
- profile markup is used only where the page is truly profile-centered

## 12. Migration-Focused Automated Tests

### Objective

Protect launch-critical migration behavior from regression.

### What must exist in code

The student must add tests for:

- representative old HTML redirects
- representative old query-string redirects
- representative file redirects
- canonical output
- `hreflang` output
- sitemap endpoints
- public AR and EN rendering
- preview and draft protection

### Where it belongs in this repository

- feature tests in `tests/Feature`
- unit tests only where isolated service behavior truly benefits from it

### Exact testing behavior required

- test actual response status codes
- test rendered head tags
- test representative localized page pairs
- test unresolved legacy behavior if applicable
- test that drafts are not public

### Edge cases to cover

- Arabic exists, English missing
- file moved permanently
- old URL with parameters in different order
- canonical tag excludes tracking parameters
- sitemap excludes draft or redirected URLs

### What the student must never do

- rely only on manual clicking
- leave redirect logic untested
- assume head tags are correct because the template "looks fine"

### Done criteria

- migration-critical behaviors are covered by automated tests
- regressions would be caught before release

## Exact Requirements By Repository Area

### `routes/web.php`

Must eventually contain:

- real public routes
- public localized routes
- sitemap routes
- legacy continuity entry points

### `app/Contracts`

Must eventually contain interfaces for:

- page retrieval
- SEO metadata resolution
- preview protection
- navigation resolution
- legacy continuity or redirect resolution
- sitemap generation

### `app/Services`

Must eventually contain real implementations for:

- page rendering support
- SEO metadata
- canonical and `hreflang`
- redirect and legacy parsing
- sitemap generation
- file continuity

### `resources/views`

Must eventually output:

- correct public page rendering
- canonical tags
- `hreflang`
- structured data where appropriate
- crawlable navigation links

### storage / database layer

Must eventually support:

- redirect persistence
- unresolved legacy request logging
- file continuity metadata

### `tests/Feature`

Must eventually prove:

- redirects work
- localized pages render correctly
- sitemap works
- head metadata is correct
- previews and drafts are protected

## Anti-Patterns The Student Must Explicitly Avoid

Do not:

- redirect most old URLs to the homepage
- treat PDFs as optional leftovers
- ship pages with missing canonicals
- generate `hreflang` without reciprocal pairs
- keep important links JavaScript-only
- expose staging publicly
- list redirected URLs in long-term sitemaps
- return `200` on soft placeholder pages for valuable old content

## Definition Of Done

The student is not done when a page "looks good."

The student is done only when all of the following are true:

- the destination exists
- the old public value has a safe destination
- the page returns the correct status
- the page has the correct canonical
- the page has the correct locale behavior
- important files still work
- the page is discoverable by real links
- the behavior is covered by tests

## Final Instruction To The Student

If you must choose between:

- shipping faster
- or preserving old public value more carefully

choose preservation unless the team has explicitly accepted the risk.

That is the correct professional behavior for a migration engineer.

## Source Basis

This document is grounded in the official-source set listed in [AUTHORITATIVE_REFERENCES.md](AUTHORITATIVE_REFERENCES.md), especially:

- Google site moves and redirects
- Google sitemap guidance
- Google localized-pages and `hreflang` guidance
- Google crawlable-link guidance
- Google `noindex` and `X-Robots-Tag` guidance
- Google site-name and structured-data guidance
- Bing migration and Bing Webmaster Tools guidance
- IndexNow usage guidance for participating engines
