# Launch Command Center Playbook

## Purpose

This playbook defines how SPU should operate the migration before, during, and after public cutover.

It exists because migrations often fail operationally, not only technically.

## The Command Center Principle

On launch week, one group must own cross-functional visibility across:

- engineering
- SEO
- analytics
- content
- infrastructure
- support

If each team monitors only its own dashboard, ranking losses and user-facing failures stay unresolved longer than necessary.

## Recommended Roles

| Role | Main responsibility |
|---|---|
| release owner | approves or delays cutover |
| engineering lead | production behavior, redirects, rendering, logs |
| SEO lead | Search Console, Bing Webmaster Tools, sitemap, canonical, `hreflang` |
| content lead | page equivalence, titles, metadata, multilingual accuracy |
| infrastructure lead | uptime, DNS, performance, rollback readiness |
| support lead | user-reported failures, missing pages, broken files |

## Migration Scenario Matrix

| Scenario | Main risk | Most important control |
|---|---|---|
| same domain, URL path changes | old URLs break | redirect mapping and monitoring |
| domain or hostname move | signal reassignment takes longer | redirects, Search Console setup, Bing Site Move, patience |
| hosting-only move with same URLs | crawl instability and deployment issues | infrastructure testing and crawl monitoring |
| same-day redesign plus CMS plus URL change | compounded failure risk | split changes or reduce scope |

Important:

- Google explicitly recommends changing one major thing at a time.
- This is one of the strongest reasons to keep the cutover conservative.

## T-14 To T-7 Days

Complete these actions before the launch window:

- freeze unnecessary URL and slug changes
- freeze template-level metadata changes unless they fix a real issue
- verify Google Search Console properties
- verify Bing Webmaster Tools properties
- prepare the final canonical sitemap set
- prepare the representative top-URL test pack
- prepare the representative file and PDF test pack
- confirm rollback and DNS responsibilities

## T-6 To T-1 Days

Run final readiness checks:

- validate redirects on representative old URLs
- validate Arabic and English canonical behavior
- validate `hreflang`
- validate homepage Organization and site-name signals
- validate breadcrumbs on section and detail pages
- validate representative file URLs
- validate staging is not indexable

## Launch-Day Sequence

### Before public switch

- confirm backups and rollback ownership
- confirm the final redirect rule set
- confirm sitemap index location
- confirm `robots.txt`
- confirm monitoring dashboards and log access

### Immediately after switch

Test:

- Arabic homepage
- English homepage
- representative core landing pages
- representative old query-string URLs
- representative old subpath URLs
- representative PDFs and files
- sitemap index
- `robots.txt`

### Search-engine actions

Google:

- submit the new canonical sitemap set in Search Console
- use URL Inspection for a small set of critical URLs

Bing:

- submit or confirm the sitemap in Bing Webmaster Tools
- use Bing URL Inspection for representative pages
- consider Bing Site Move and IndexNow if applicable

Operational note:

- for same-domain path-only moves, the main signals are redirects, sitemaps, and monitoring
- for domain or hostname moves, additional move tooling becomes more relevant

This is an operational inference from the official site-move documentation.

## First 72 Hours

Review at least hourly:

- `404` spikes
- redirect misses
- unresolved legacy signatures
- server `5xx` errors
- file and PDF failures
- homepage and top landing page response codes
- sitemap fetchability
- performance regressions on critical templates

## First 30 Days

Review daily:

- Google indexing and coverage signals
- Bing diagnostics
- highest-hit unresolved requests
- top landing page traffic stability
- multilingual canonical issues
- file and PDF access patterns

Prioritize fixes in this order:

1. high-traffic URLs
2. high-backlink URLs
3. high-value files and PDFs
4. multilingual errors
5. lower-value archive gaps

## Response Rules

### Trigger: many legacy URLs fail

Action:

- pause non-essential deployment changes
- restore or extend the redirect rule set
- retest representative URLs

### Trigger: canonical or `hreflang` errors appear widely

Action:

- fix template-level logic first
- revalidate on representative page pairs
- request recrawl for a small critical set

### Trigger: important files fail

Action:

- restore or remap the file
- correct the file redirect
- verify MIME and access behavior

### Trigger: staging or private content is indexed

Action:

- remove or protect the live exposure immediately
- use the Removals tool if urgent
- republish corrected content at a clean URL when needed

## Cutover Command Board

Track each issue with:

- issue type
- exact URL
- first detected time
- impact level
- current status
- owner
- verified fix time

Suggested status flow:

- detected
- assigned
- fix in progress
- deployed
- verified
- closed

## Best Practice Reminder

Google states that crawling and reprocessing moved URLs can take from days to weeks, and larger sites can take longer.

That means:

- do not panic at every early fluctuation
- but do act quickly on real technical errors

Patience is not the same as passivity.
