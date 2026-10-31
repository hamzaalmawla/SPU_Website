# QA And Launch Safety

## Purpose

This document defines the minimum professional standard for approving public cutover.

If these gates are not met, the safest decision is to delay launch.

## Go Or No-Go Gates

Do not approve cutover until all answers are "yes".

| Gate | Required answer |
|---|---|
| are the top legacy landing pages mapped and tested? | yes |
| are the top linked PDFs and files mapped and tested? | yes |
| does the site resolve representative legacy query-string URLs safely? | yes |
| is sitemap generation live? | yes |
| are canonical tags live on public pages? | yes |
| are Arabic and English `hreflang` annotations correct on representative pairs? | yes |
| is staging non-indexable? | yes |
| is unresolved-request logging live? | yes |
| are Search Console and Bing Webmaster Tools ready for monitoring? | yes |
| is the `/admin` path conflict resolved? | yes |

## Migration QA

Confirm:

- legacy inventories are complete enough for launch decisions
- known high-value entities are not missing from the mapping plan
- archive content kept public is classified deliberately
- intentional retirements are documented

## Redirect QA

Confirm:

- exact redirects work for representative high-value pages
- pattern redirects work for representative legacy shapes
- redirects do not chain unnecessarily
- redirects do not loop
- redirects preserve intent instead of sending everything to the homepage

## File And Document QA

Confirm:

- representative PDFs open successfully
- representative legacy file URLs resolve correctly
- file MIME handling is correct
- broken file requests are logged
- file destinations are stable and public

## Multilingual QA

Confirm:

- Arabic pages canonicalize to Arabic URLs
- English pages canonicalize to English URLs
- AR and EN alternates reference each other
- locale switching does not create duplicate canonical signals

## Public SEO QA

Confirm:

- sitemap index is accessible
- canonical tags are present
- `hreflang` is present where appropriate
- `robots.txt` is accessible and correct
- important pages are not accidentally `noindex`
- staging URLs are not exposed to search engines

## Launch-Day Smoke Tests

Test immediately after deployment:

- homepage in Arabic
- homepage in English
- representative current-scope landing pages
- representative old query-string URLs
- representative old file URLs
- sitemap index
- `robots.txt`
- a sample of canonical tags
- a sample of `hreflang` pairs

## First 24 Hours

Review at least hourly:

- `404` volume
- unresolved legacy requests
- redirect misses
- file request failures
- server `5xx` issues
- homepage and top landing page health

## First 7 Days

Review daily:

- Search Console coverage and page indexing signals
- Bing Webmaster diagnostics
- top brand queries
- top landing pages
- broken file patterns
- recurring unmapped legacy signatures

Resolve by priority:

1. high-traffic pages
2. high-backlink pages
3. key files and PDFs
4. multilingual canonical issues
5. lower-value archive misses

## Rollback Triggers

Prepare rollback or emergency intervention if any of these occur:

- widespread `5xx` responses on key public routes
- large numbers of high-value legacy URLs returning `404`
- homepage or key pages unexpectedly show `noindex`
- sitemap becomes inaccessible
- file and PDF failures affect major public assets

## Release Standard

The release is only professionally acceptable if:

- the team can explain how old public value is being preserved
- the team can prove representative URLs and files work
- the team can monitor the migration in near real time
- the team can fix misses quickly once real traffic reaches the new system
