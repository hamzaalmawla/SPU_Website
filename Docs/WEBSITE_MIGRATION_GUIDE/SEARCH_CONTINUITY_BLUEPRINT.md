# Search Continuity Blueprint

## Goal

Protect search continuity across the migration by preserving:

- indexed URLs
- backlinks
- long-tail archives
- files and PDFs
- multilingual variants
- historical subpath behavior

## Core Design

Use a layered public continuity model:

1. exact redirect lookup
2. pattern-based legacy rules
3. database-backed legacy URL resolution
4. canonical public destination
5. unresolved-request logging and fast triage

This model is safer than relying only on hand-written web-server redirects.

## URL Resolution Order

### 1. Exact redirects first

Use exact rules for:

- old pages with known new equivalents
- old PDFs with recovered new equivalents
- renamed or relocated public pages

### 2. Pattern rules second

Use pattern rules for recurring legacy shapes such as:

- `index.php` list views
- known detail routes
- stable subpath families

### 3. Database fallback third

If the request still cannot be resolved:

- parse host, path, and normalized query string
- look up the legacy record metadata
- resolve to the canonical new or archive destination

### 4. Logging if unresolved

If nothing matches:

- return the correct public status
- log the unresolved request
- triage high-frequency misses quickly after launch

## Status Code Policy

| Situation | Preferred status |
|---|---|
| permanent replacement exists | `301` |
| temporary maintenance or short-lived test | `302` or `307` |
| content intentionally removed with no useful replacement | `410` or `404` |
| legacy archive remains public | `200` with self-canonical |

Avoid using a homepage redirect as a substitute for a real mapping decision.

## Query-String Strategy

The old SPU estate includes public query-string URLs.

Therefore:

- do not assume only clean paths matter
- normalize query parameter order
- preserve enough query context to identify the legacy entity
- remove legacy parameters from the canonical destination

The public canonical URL should be clean.
The resolver may still need the messy legacy query string to find the right target.

## Multilingual Strategy

The continuity layer must understand legacy language context.

Recommended handling:

- map known Arabic legacy values to the Arabic canonical destination
- map known English legacy values to the English canonical destination
- treat unsupported or unclear legacy locale values as a best-fit fallback, then log them

Never let multilingual handling collapse both locales into one canonical by accident.

## File And PDF Continuity

Files need the same seriousness as HTML pages.

Required behavior:

- old file URLs should remain reachable or redirect permanently to stable new file URLs
- important files should not disappear behind generic pages
- broken file requests should be logged and reviewed after launch

Important:

- preserving file access helps with backlinks, trust, and academic discoverability
- this is relevant for Google, Bing, and institutional visibility more broadly

## Sitemap Policy

Use:

- a sitemap index
- child sitemaps for major public content groups

Long-term rule:

- include only canonical public URLs in the ongoing sitemap set

Migration operations rule:

- keep a separate migration inventory of old URLs for testing, validation, and troubleshooting

## Canonical And Duplicate Control

Every destination produced by the continuity layer should lead to:

- one clear canonical URL
- no leftover legacy query parameters in the canonical
- correct locale-aware canonical behavior
- correct `hreflang` links where alternates exist

Do not use `robots.txt` or removal tools as a substitute for canonical decisions.

## Logging And Monitoring

Log unresolved legacy requests with at least:

- host
- path
- raw query string
- normalized query signature
- referer
- hit count
- first seen
- last seen

This data should drive the first 30 days of post-launch fixes.

## Why This Blueprint Matters

Search engines do not reward a migration because the new codebase feels modern.

They reward clear evidence that:

- the old public address space still makes sense
- signals from old URLs can be transferred
- users still reach the right content

That is what this blueprint is designed to preserve.
