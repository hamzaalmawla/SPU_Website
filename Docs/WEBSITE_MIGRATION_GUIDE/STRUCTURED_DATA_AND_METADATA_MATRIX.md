# Structured Data And Metadata Matrix

## Purpose

This document defines the safest structured-data and metadata scope for SPU's migration.

The goal is not to add every possible markup type.

The goal is to add the markup that is:

- accurate
- maintainable
- useful to users and search engines
- appropriate for the actual page type

## Global Rules

1. Prefer JSON-LD where possible.
2. Mark up only what is actually visible and true on the page.
3. Do not mark up misleading or hidden content.
4. Validate with Rich Results Test and inspect real URLs after deployment.
5. Keep structured-data changes aligned with template ownership so they remain stable after launch.

## Core Metadata Every Public Page Should Handle

| Signal | Why it matters |
|---|---|
| title | primary page labeling in search |
| meta description | snippet influence and click clarity |
| canonical | duplicate and preferred-URL control |
| `hreflang` | AR/EN language targeting |
| robots directives | indexing control where needed |
| Open Graph and social metadata | better link previews and consistency |

## Recommended Structured Data By Page Type

### Homepage

Recommended:

- `WebSite` for site name handling
- `Organization` for institutional identity

Why:

- Google uses homepage signals and `WebSite` structured data for site name preference
- Organization markup helps clarify institutional identity

### Section And Detail Pages

Recommended:

- `BreadcrumbList`

Why:

- helps clarify hierarchy
- should reflect a normal user path, not just the raw URL structure

### Article-Like Pages

Use only when the page is genuinely article content.

Recommended when applicable:

- `Article`
- `NewsArticle`
- `BlogPosting`

Important:

- list all displayed authors in markup
- use `author.url` or `sameAs`
- if the author page is internal, Google recommends marking it up as a profile page

### Profile Pages

For faculty, staff, researcher, or author pages, evaluate:

- `ProfilePage`

This can be a strong fit where the page's main purpose is a single person or organization affiliated with the site.

Valid use cases in Google's documentation include employee pages on a company website.

Operational inference:

- this can be useful for SPU faculty or author profiles if the page is truly profile-centered

### FAQ Pages

Use with caution.

Important:

- Google currently limits FAQ rich results significantly
- do not build FAQ markup with the expectation that it will necessarily surface as a rich result

Use FAQ markup only when the page is genuinely a FAQ page and the markup is maintained correctly.

## Structured Data Matrix

| Page type | Markup | Priority | Notes |
|---|---|---|---|
| homepage | `WebSite` | critical | site name preference |
| homepage | `Organization` | critical | institutional identity |
| section page | `BreadcrumbList` | high | keep hierarchy user-centered |
| detail page | `BreadcrumbList` | high | helps hierarchy and clarity |
| article-like page | `Article` or subtype | medium-high | only when truly article-like |
| faculty or author profile | `ProfilePage` | medium-high | strong fit if page is mainly about one person |
| FAQ page | `FAQPage` | low-medium | use carefully due to current feature limits |

## Metadata Rules For AR And EN

- each locale page should canonicalize to itself
- AR and EN variants should reference each other with `hreflang`
- if a PDF has language variants, use HTTP headers for alternates
- avoid canonicalizing Arabic content to English or vice versa unless no true localized equivalent exists

## Site Name Rules

Google supports one site name per domain or subdomain, not per subdirectory.

That means:

- SPU should keep the main institutional site name consistent at the root
- do not expect `/en` or another subdirectory to carry an independent site name

## Operational Checklist

- [ ] homepage has `WebSite` markup
- [ ] homepage has `Organization` markup
- [ ] representative section pages have breadcrumbs
- [ ] representative detail pages have breadcrumbs
- [ ] article templates use author fields correctly if article markup is used
- [ ] profile templates are evaluated for `ProfilePage` where appropriate
- [ ] structured data matches visible content
- [ ] sample URLs pass Rich Results Test without critical issues

## What To Avoid

- adding markup just because a plugin offers it
- marking up pages whose content type does not match the schema
- using multiple conflicting structured-data strategies for the same template
- expecting structured data to compensate for weak page quality or broken URLs
