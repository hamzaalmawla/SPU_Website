# Authoritative References

This migration package was reviewed against the following sources on `2026-04-21`.

Use these sources as the rules of record when generic SEO advice conflicts with official guidance.

## Google Search Central

- Site moves and migrations  
  <https://developers.google.com/search/docs/crawling-indexing/site-move-with-url-changes>  
  Use for URL moves, redirect retention, Search Console monitoring, and the recommendation to avoid stacking multiple major changes at once.

- Changing your hosting  
  <https://developers.google.com/search/docs/crawling-indexing/site-move-no-url-changes>  
  Use when infrastructure changes do not alter public URLs. Helpful for separating hosting moves from URL moves.

- Localized versions of your pages  
  <https://developers.google.com/search/docs/specialty/international/localized-versions>  
  Use for `hreflang`, reciprocal annotations, self-references, and fully-qualified alternate URLs.

- Build and submit a sitemap  
  <https://developers.google.com/search/docs/crawling-indexing/sitemaps/build-sitemap>  
  Use for sitemap generation, absolute URLs, Search Console submission, and the rule that sitemap submission is a hint, not a guarantee.

- Ask Google to recrawl your URLs  
  <https://developers.google.com/search/docs/crawling-indexing/ask-google-to-recrawl>  
  Use for the distinction between URL Inspection for a few URLs and sitemap submission for many URLs.

- Canonicalization guidance  
  <https://developers.google.com/search/docs/crawling-indexing/consolidate-duplicate-urls>  
  Use for canonical rules, duplicate control, and the warning not to use `robots.txt` or the URL removal tool for canonicalization.

- Block indexing with `noindex`  
  <https://developers.google.com/search/docs/crawling-indexing/block-indexing>  
  Use for the rule that `noindex` is ineffective when the page is blocked in `robots.txt`.

- SEO link best practices  
  <https://developers.google.com/search/docs/crawling-indexing/links-crawlable>  
  Use for crawlable navigation and the rule that Google reliably crawls normal `<a href="...">` links.

- File types indexable by Google  
  <https://developers.google.com/search/docs/crawling-indexing/indexable-file-types>  
  Use to confirm that PDFs and many office-document formats are indexable and therefore migration-critical.

- Understanding page experience  
  <https://developers.google.com/search/docs/appearance/page-experience>  
  Use as supporting guidance for performance and user experience. Treat this as an important supporting signal, not a substitute for content, URLs, and architecture.

- Core Web Vitals  
  <https://developers.google.com/search/docs/appearance/core-web-vitals>  
  Use for current user-experience thresholds and measurement framing.

- Site names in Google Search  
  <https://developers.google.com/search/docs/appearance/site-names>  
  Use for homepage-level `WebSite` markup, site-name consistency, and the rule that Google supports one site name per domain or subdomain rather than per subdirectory.

- Organization structured data  
  <https://developers.google.com/search/docs/appearance/structured-data/organization>  
  Use for organization identity markup on the homepage and key institutional pages.

- Breadcrumb structured data  
  <https://developers.google.com/search/docs/appearance/structured-data/breadcrumb>  
  Use for breadcrumb markup on section and detail pages.

- Article structured data  
  <https://developers.google.com/search/docs/appearance/structured-data/article>  
  Use for author markup, publication dates, and article-page markup when pages are truly article-like.

- Profile page structured data  
  <https://developers.google.com/search/docs/appearance/structured-data/profile-page>  
  Use for faculty, staff, author, or researcher profile-style pages when a page's primary focus is a single affiliated person or organization.

- FAQ structured data  
  <https://developers.google.com/search/docs/appearance/structured-data/faqpage>  
  Use mainly to understand current limitations: FAQ rich results are now restricted to well-known, authoritative government-focused or health-focused sites.

- General structured data guidelines  
  <https://developers.google.com/search/docs/appearance/structured-data/sd-policies>  
  Use for the rule that structured data must match visible, truthful page content and must not be misleading.

- Intro to structured data  
  <https://developers.google.com/search/docs/appearance/structured-data/intro-structured-data>  
  Use for Google's recommendation to prefer JSON-LD when possible.

- Keep redacted information out of Google  
  <https://developers.google.com/search/docs/crawling-indexing/keep-redacted-information-out>  
  Use for handling accidentally published or improperly redacted documents and emergency removals.

## Bing And Related Official Sources

- Bing Webmaster Tools URL Inspection Tool  
  <https://blogs.bing.com/webmaster/september-2020/Introducing-the-Bing-Webmaster-Tools-URL-Inspection-Tool>  
  Use for page-level crawl, indexing, SEO, and markup diagnostics in Bing.

- Website Migration with Bing  
  <https://blogs.bing.com/webmaster/december-2020/Website-Migration-with-Bing>  
  Use for Bing's migration workflow, redirect retention expectations, monitoring, and Site Move tooling.

- Bing Webmaster Tools help  
  <https://support.microsoft.com/en-us/bing/help-with-bing-webmaster-tools>  
  Use for site verification, sitemaps, and platform support.

- Start using Bing Webmaster Tools to improve your site visibility  
  <https://blogs.bing.com/webmaster/June-2025/Start-Using-Bing-Webmaster-Tools-to-Improve-Your-Site-Visibility>  
  Use for current setup guidance, site scans, sitemap submission, and IndexNow integration.

## IndexNow

- IndexNow home  
  <https://www.indexnow.org/index>  
  Use for the protocol purpose and supported engines.

- IndexNow FAQ  
  <https://www.indexnow.org/faq>  
  Use for implementation details, URL submission, batching, and update/delete notifications.

Important:

- IndexNow helps Bing and other participating engines.
- It does not replace Google Search Console.

## Webometrics

- Webometrics methodology  
  <https://www.webometrics.org/methodology>  
  Verified on `2026-04-21`. The official methodology currently weights:
  - Visibility: `50%`
  - Transparency: `10%`
  - Excellence: `40%`

- Webometrics best practices  
  <https://www.webometrics.org/best-practices>  
  Use for domain strategy, open access, multilingual visibility, content preservation, and digital institutional presence.

- Webometrics objectives  
  <https://www.webometrics.org/objectives>  
  Use for the broader institutional purpose behind the ranking.

Important:

- the old "Presence" metric is no longer part of the official methodology
- preserving PDFs and archives still matters indirectly because they support discoverability, backlinks, citations, and institutional trust

## How To Use This Reference Set

- If Google Search Central and a blog disagree, follow Google Search Central for Google-facing behavior.
- If Bing Webmaster or IndexNow official guidance and a forum disagree, follow the official source.
- If legacy folklore about Webometrics conflicts with the current official methodology, follow the current official methodology.
- Use third-party tools such as Screaming Frog, Ahrefs, Semrush, or similar for measurement and workflow support, not as the source of truth for search-engine rules.
