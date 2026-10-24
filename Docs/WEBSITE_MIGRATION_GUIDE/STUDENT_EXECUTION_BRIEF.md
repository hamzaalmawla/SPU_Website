# Student Execution Brief

## Mission

Migrate SPU's public web presence into the new platform without needlessly sacrificing discoverability, backlinks, research visibility, or institutional trust.

Your job is not only to "build a new site".

Your job is to protect what the university has already earned on the open web.

## What Success Means

Success means:

- valuable legacy URLs still resolve
- important PDFs and academic assets remain discoverable
- Arabic and English versions are clear to users and crawlers
- search engines receive strong technical signals during and after cutover
- problems are found and fixed before ranking loss compounds

## What You Must Not Promise

Do not promise:

- zero ranking fluctuation
- instant reindexing
- traffic growth from design changes alone
- that search engines will understand the migration without explicit help

## The Twelve Non-Negotiables

1. Keep the main domain unless a domain move is truly necessary.
2. If a domain move is unavoidable, do not combine it with unnecessary structural changes on the same day.
3. Map every valuable old URL to one best new or archive destination.
4. Never redirect everything to the homepage.
5. Keep permanent redirects live for at least one year.
6. Keep old and new search-engine properties verified and monitored.
7. Put only canonical URLs in the long-term sitemap set.
8. Output self-referencing and reciprocal `hreflang` for AR and EN pairs.
9. Do not block pages in `robots.txt` if you expect `noindex` to remove them from search.
10. Preserve high-value PDFs, researcher pages, archive pages, and faculty profiles.
11. Use crawlable HTML links with real `href` attributes.
12. Monitor logs, Search Console, and Bing daily after cutover.

## The Two Questions You Must Ask For Every Legacy URL

1. What is the best equivalent destination for this URL?
2. Should this stay public, move to an archive, or be intentionally retired?

If you cannot answer those questions confidently, the URL is not ready for launch.

## Execution Order

### Step 1: Understand The Real Starting Point

Read:

1. [OLD_SYSTEM_FINDINGS.md](OLD_SYSTEM_FINDINGS.md)
2. [NEW_SYSTEM_FINDINGS.md](NEW_SYSTEM_FINDINGS.md)
3. [SEO_PRESERVATION_GUIDE.md](SEO_PRESERVATION_GUIDE.md)

### Step 2: Build The Evidence Base

Produce:

- a full legacy URL inventory
- a list of top landing pages and top linked pages
- a list of files and PDFs that must remain public
- a list of subpaths and historical sections that still carry value

### Step 3: Classify The Legacy Estate

Every URL should land in one of four buckets:

- rebuild now
- archive now
- redirect to a true equivalent
- intentionally retire with a justified `404` or `410`

### Step 4: Build Continuity Before Cosmetics

Before visual polish, build:

- page-level redirect support
- legacy query-string resolution
- sitemap generation
- canonical and `hreflang` output
- monitoring and unresolved-request logging

### Step 5: Launch Conservatively

Launch only when:

- the top legacy URLs have been tested
- the top files have been tested
- search-engine verification is ready
- the rollback and triage process is defined

## The Most Important SPU-Specific Reality

This repository is still a foundation-stage project, not yet a full public replacement for the old site.

That means the safe strategy is:

- rebuild core current-scope pages properly
- preserve out-of-scope legacy content through an archive or continuity layer
- do not publish thin placeholders for historically valuable content

## Common Failure Patterns

Avoid these mistakes:

- launching with only a new homepage and no legacy continuity layer
- deleting old PDFs because they are hard to migrate
- forcing complex academic archives into oversimplified new templates
- using generic redirects for pages with very different intent
- leaving staging pages indexable
- assuming multilingual SEO ends with translated navigation labels

## The Professional Standard

A professional migration engineer does not ask only:

"Does the new page exist?"

A professional migration engineer asks:

- is the destination equivalent enough to deserve the old URL's signals?
- is the page indexable and canonicalized correctly?
- is the file still reachable?
- is the page linked internally?
- can Google and Bing understand the change quickly?
- does this decision preserve the university's long-term web footprint?

## Your Next Read

Continue with [HOW_THIS_PREVENTS_RANKING_DROPS.md](HOW_THIS_PREVENTS_RANKING_DROPS.md), then read the full [SEO_PRESERVATION_GUIDE.md](SEO_PRESERVATION_GUIDE.md).
