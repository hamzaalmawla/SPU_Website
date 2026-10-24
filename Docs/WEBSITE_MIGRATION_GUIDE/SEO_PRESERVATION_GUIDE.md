# SEO Preservation Guide
## Google, Bing, And Webometrics Protection For The SPU Website Migration

## Executive Summary

The safe way to migrate `spu.edu.sy` is not to treat the project as a homepage redesign.

It must be treated as a continuity program for:

- URLs
- backlinks
- multilingual signals
- PDFs and academic files
- archive visibility
- research and profile discoverability
- institutional web presence

The current repository is not yet a full public replacement for the legacy site. It currently proves only a foundation-stage Laravel application with a single public route and placeholder services.

Therefore, the correct strategy is:

1. build the migration safety layer first
2. preserve out-of-scope public content through archive or legacy continuity routes
3. cut over only after technical, content, and monitoring gates are met

This guide is based on official references verified on `2026-04-21`. See [AUTHORITATIVE_REFERENCES.md](AUTHORITATIVE_REFERENCES.md).

## 1. Strategic Reality For SPU

### 1.1 The current Laravel app is not ready for full public replacement

The repository currently shows:

- `GET /` as the only proven public route
- the Laravel welcome page as the only proven public rendering
- placeholder implementations behind the service contracts
- no proven redirect system
- no proven sitemap layer
- no proven page rendering system
- no proven legacy URL continuity layer

That means a full public cutover today would create unnecessary search risk.

### 1.2 Current project scope matters

This sprint is focused on:

- homepage
- public navigation shell
- bilingual landing-page foundation
- admin and CMS foundation

It is not yet a complete rebuild of the old public estate.

That means the correct migration stance is:

- rebuild current-scope sections properly
- keep valuable out-of-scope legacy sections public through an archive or continuity layer
- do not let search engines or users fall into empty sections

### 1.3 What a responsible migration can and cannot promise

A responsible migration can:

- reduce ranking loss
- protect link equity
- speed re-crawling and re-association of signals
- preserve institutional discoverability

It cannot honestly promise:

- zero short-term fluctuation
- instant replacement of old URLs in search results
- ranking growth just because the frontend looks newer

## 2. Non-Negotiable Principles

1. Keep the main institutional domain if possible.
2. If a domain move is unavoidable, do not stack it with unnecessary layout and structure changes on the same day.
3. Every valuable old URL must have one best destination.
4. Use permanent redirects for permanent moves.
5. Keep those redirects live for at least one year.
6. Keep archives, profiles, and files public when they still have link or research value.
7. Treat Arabic and English pages as first-class public pages, not afterthoughts.
8. Put only canonical URLs in the long-term sitemap set.
9. Keep navigation crawlable with normal HTML links.
10. Monitor aggressively after launch.

## 3. The Main Ranking Risks

| Risk | Why it hurts | Engines affected |
|---|---|---|
| old URLs return `404` | link equity and user trust are lost | Google, Bing |
| old URLs all redirect to the homepage | intent mismatch, soft-404 risk, poor user experience | Google, Bing |
| PDFs and academic files disappear | external links break and scholarly discovery weakens | Google, Bing, Webometrics |
| important archive pages are replaced with thin placeholders | long-tail traffic and historical signals disappear | Google, Bing, Webometrics |
| canonical or `hreflang` output is wrong | duplicate handling and language targeting become unstable | Google, Bing |
| crawlable HTML links are missing | pages become harder to discover and re-crawl | Google, Bing |
| the domain footprint fragments | institutional authority gets diluted | Webometrics, Google, Bing |
| launch happens without monitoring | fixable errors remain live too long | Google, Bing |

## 4. Phase 1: Baseline And Asset Inventory

Before changing public URLs, export and document the current state.

### 4.1 Search and analytics baseline

Capture:

- Google Search Console performance and page data
- Bing Webmaster performance and indexing diagnostics
- analytics landing pages
- top backlinks and top referring domains
- branded and non-branded query performance
- the most visited legacy pages

### 4.2 URL inventory

Create a consolidated inventory from:

- the old database
- a crawl of the live legacy site
- embedded internal URLs found inside legacy HTML
- legacy file URLs and document links

Each row should be classified as:

- keep and rebuild
- keep via archive
- redirect to equivalent page
- intentionally retire

### 4.3 File and academic asset inventory

For universities this is critical.

Inventory at minimum:

- PDFs
- Word documents
- presentations
- research files
- faculty CVs and publications
- admission or policy documents that still have inbound links

Important:

- Webometrics no longer uses the old "Presence" metric
- preserving academic files still matters because they support visibility, citations, backlinks, and institutional trust

### 4.4 High-value page inventory

Build a shortlist of the pages that must not break:

- top organic landing pages
- top linked pages
- top linked files
- faculty and profile pages with external references
- research pages
- policy and admissions pages
- hospital or clinical pages if publicly linked

## 5. Phase 2: URL Architecture And Mapping

### 5.1 Keep the domain strategy conservative

The safest default is:

- keep `spu.edu.sy` as the main public domain
- keep Arabic and English on stable, predictable paths
- avoid introducing a separate main domain for English or departments

Official Webometrics best-practice guidance favors a single consistent institutional domain.

### 5.2 Recommended public URL shape

Use a consistent public pattern such as:

- `/ar/...`
- `/en/...`

Guidelines:

- lowercase
- descriptive slugs
- no meaningless query strings in canonicals
- no unnecessary file extensions
- stable URL patterns across content types

### 5.3 Preserve intent, not just "a page"

When mapping old URLs, the question is not:

"Can I send this somewhere?"

The question is:

"What is the best equivalent destination for the original search and user intent?"

Examples:

- an old faculty profile should go to the matching profile or archive profile
- an old PDF should go to the exact new document URL or a stable replacement file page
- an old research page should go to the matching research page or research archive, not the homepage

### 5.4 SPU-specific route conflict to resolve

The current findings show a historical public `/admin` context in the old estate.

That creates a launch blocker if the new CMS also wants `/admin`.

Recommendation:

- reserve legacy public behavior first
- move the new admin panel to a non-conflicting path if necessary

Do not silently overwrite a historically public path with a private control panel.

## 6. Phase 3: Redirect, Archive, And File Continuity

### 6.1 Redirect policy

Use:

- `301` for permanent public replacements
- `302` or `307` only for genuinely temporary situations
- `404` or `410` only when content is intentionally retired and has no useful equivalent

### 6.2 Redirect quality rules

Every redirect should be:

- single hop whenever possible
- intent-preserving
- loop-free
- tested
- logged

Avoid:

- blanket homepage redirects
- redirect chains
- redirecting every file URL to a generic downloads page

### 6.3 Legacy query-string support

The old SPU site uses important query-string patterns such as `index.php?...`.

Do not assume that only clean legacy paths matter.

The production site should either:

- resolve those patterns directly and redirect them safely, or
- maintain a legacy parser that can map them to canonical new URLs

### 6.4 Archive preservation

If a section is not rebuilt yet but still has public value:

- keep it public
- preserve indexability if appropriate
- give it a stable canonical URL

This is especially important for:

- research and publication pages
- faculty and profile pages
- alumni and honor archives
- historical institutional content

### 6.5 File preservation

For important public files:

- preserve the file if possible
- preserve or reconstruct a stable new file URL
- redirect the old file URL permanently to the new file URL

If a file cannot yet be restored:

- keep the content page live
- mark the file as unresolved internally
- prioritize recovery of files with backlinks or search traffic

Do not delete file references simply because the migration is inconvenient.

## 7. Phase 4: Indexation, Metadata, And Multilingual Signals

### 7.1 Sitemaps

Use a sitemap index and child sitemaps for:

- homepage and key landing pages
- pages
- profiles
- research or publication content
- documents and files where appropriate
- archive sections that remain public

Long-term rule:

- put only canonical public URLs in the ongoing sitemap set

Temporary migration note:

- keep a migration inventory of old URLs for testing and monitoring
- submit the new canonical sitemap set immediately after launch

### 7.2 Canonical rules

Every public page must have one clear canonical URL.

Use canonicals to control duplicates, not to replace proper redirects.

Do not use:

- `robots.txt` for canonicalization
- the URL removal tool for canonicalization

### 7.3 `hreflang` rules for AR and EN

Arabic and English pages should:

- each point to themselves
- each point to their alternate language version
- use fully-qualified absolute URLs
- keep canonical tags within the same locale version

Do not make Arabic canonicalize to English or vice versa unless the page truly has no localized equivalent.

### 7.4 `robots.txt` and `noindex`

Important operational rule:

- if you need `noindex` to remove a page from search, do not block that page in `robots.txt`

Preferred protection for staging:

- HTTP authentication or IP restriction
- plus explicit non-production handling where needed

Do not leave public staging environments indexable.

### 7.5 Titles, descriptions, and structured data

Minimum structured data priorities:

- Organization markup on the homepage and institutional pages
- Breadcrumb markup on section and detail pages
- content-type markup only when it genuinely matches the page

Important nuance:

- do not overinvest in FAQ rich-result expectations
- Google's official documentation now limits FAQ rich results to well-known authoritative government-focused or health-focused sites

Use structured data to clarify pages, not as decoration or a shortcut around weak content.

## 8. Phase 5: Rendering, Performance, And Internal Linking

### 8.1 Make the site crawlable

Critical navigation and content discovery should rely on:

- normal HTML links
- real `<a href="...">` elements

Do not rely on:

- JavaScript-only navigation patterns
- click handlers without proper link markup
- hidden or delayed core navigation that crawlers struggle to parse

### 8.2 Performance matters, but it is not the whole migration

Treat performance and page experience as a supporting layer.

They matter for:

- user experience
- crawl efficiency
- mobile usability
- competitive quality

They do not replace:

- URL continuity
- content equivalence
- redirects
- internal linking
- indexing control

### 8.3 Internal linking

Before launch:

- update internal links to point directly to canonical new URLs
- avoid linking internally to redirected old URLs
- ensure important pages are not orphaned
- expose key academic and institutional sections through crawlable navigation and contextual links

## 9. Phase 6: Webometrics Protection And Improvement

### 9.1 Use the current official methodology, not outdated folklore

As verified on `2026-04-21`, the official Webometrics methodology currently weights:

- Visibility: `50%`
- Transparency: `10%`
- Excellence: `40%`

The old "Presence" metric has been discontinued.

### 9.2 What that means for SPU

Webometrics is not asking SPU to publish random files for file count.

It rewards a stronger institutional web footprint around:

- external referring domains and web visibility
- researcher citation transparency
- excellent research output

Therefore, the migration must protect:

- institutional domain authority
- public discoverability of research and profile pages
- English accessibility of strategic research content
- persistent access to academic material
- stable institutional identity across the web

### 9.3 Practical Webometrics actions

Protect and improve:

- one strong institutional domain strategy
- faculty and researcher profile discoverability
- publication and research landing pages
- open, crawlable academic assets
- English versions of strategic pages and metadata
- internal linking between homepage, faculties, research, and profiles

Do not:

- split the university across multiple main domains
- publish thin placeholder pages for research and archive sections
- delete historical public content that still attracts links
- assume file count alone improves ranking

### 9.4 Why preserving PDFs and archives still matters

Even though file count is no longer a direct official Webometrics metric, these assets still matter because they can:

- attract backlinks
- support researcher discoverability
- reinforce institutional openness
- create entry points from search results
- preserve historical trust and citations

## 10. Phase 7: Cutover, Monitoring, And Recovery

### 10.1 Pre-launch freeze

Before cutover:

- freeze unnecessary IA changes
- freeze slug changes
- freeze non-essential copy rewrites on mapped pages
- complete redirect testing on high-value URLs

### 10.2 Search-engine readiness

Before launch:

- verify Search Console ownership
- verify Bing Webmaster Tools ownership
- prepare sitemap submission
- prepare top-URL inspection list
- prepare unresolved-request monitoring

### 10.3 Launch-day checks

Check immediately:

- homepage in AR and EN
- representative mapped legacy URLs
- representative file URLs
- canonical tags
- `hreflang`
- sitemap accessibility
- `robots.txt`
- server response codes
- analytics and log collection

### 10.4 First 72 hours

Watch:

- `404` spikes
- unresolved legacy signatures
- redirect misses
- crawl errors
- unexpected `noindex`
- broken file requests
- ranking of top brand queries
- top landing page health

### 10.5 First 30 days

Daily:

- review Search Console indexing signals
- review Bing diagnostics
- resolve the highest-hit unmapped URLs first
- fix broken document routes
- update redirect rules where the logs prove a need

### 10.6 Optional support for Bing and participating engines

Consider IndexNow for added, updated, and deleted URLs after launch.

Treat it as:

- a useful accelerator for Bing and other participating engines
- not a replacement for Google Search Console, sitemaps, or redirects

## 11. What Not To Do

Do not:

- replace the old site with a homepage-only build
- drop archive sections because they are out of current sprint scope
- route all unknown old URLs to the homepage
- block deindex targets in `robots.txt`
- rely on JavaScript-only navigation for critical pages
- move domain, IA, and content strategy all at once unless truly required
- assume search engines will infer the correct mappings without explicit signals

## 12. Acceptance Criteria

Do not approve cutover until all of the following are true:

- top legacy landing pages are mapped and tested
- top linked files are mapped and tested
- unresolved-request logging is live
- sitemap is live
- canonical and `hreflang` output are live
- internal links point to canonical URLs
- staging is not indexable
- the `/admin` path decision is resolved
- Search Console and Bing monitoring are active
- the rollback and triage process is documented

## 13. Where To Go Next

For implementation order, read [IMPLEMENTATION_BLUEPRINT.md](IMPLEMENTATION_BLUEPRINT.md).

For redirect architecture, read [SEARCH_CONTINUITY_BLUEPRINT.md](SEARCH_CONTINUITY_BLUEPRINT.md).

For release criteria, read [QA_AND_LAUNCH_SAFETY.md](QA_AND_LAUNCH_SAFETY.md).
