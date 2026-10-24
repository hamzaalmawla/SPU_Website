# Implementation Blueprint

## Objective

Turn this repository from a foundation-stage Laravel application into a migration-safe public platform for SPU's current-scope launch.

This is not a request to rebuild the entire old website immediately.

It is a request to build enough public foundation and continuity support so the university does not lose hard-earned visibility during transition.

## Current Launch Blockers

| Blocker | Why it matters | Required action before public cutover |
|---|---|---|
| only `GET /` is publicly proven | legacy destinations do not yet exist | implement real public page and section routes |
| placeholder services back the contracts | business behavior is not implemented | replace placeholders with real service implementations |
| no proven page, menu, settings, or homepage persistence | CMS foundation is incomplete | build current-scope storage and service flows |
| no redirect or legacy resolution layer | old URLs will fail | implement continuity tables and resolver services |
| no sitemap, canonical, or `hreflang` layer | migration signals will be weak | implement public SEO output |
| historical `/admin` public context conflicts with CMS expectations | old public value could be overwritten | resolve admin path strategy before launch |

## Implementation Order

### Phase 0: Freeze unsafe assumptions

Before writing migration code:

- do not assume the current project can replace the full old site
- do not plan a full public cutover around the new homepage alone
- do not remove archive visibility requirements because they are out of scope for this sprint

### Phase 1: Build the minimum public foundation

Implement the current-scope public foundation:

- homepage rendering and publish flow
- page rendering for current-scope landing pages
- menu and navigation persistence
- settings persistence for public rendering
- media handling needed for homepage and landing pages

This work should align with the repository architecture rules:

- business logic in services only
- controllers remain thin
- public service methods return DTOs or structured view payloads, not raw models

### Phase 2: Build the continuity layer

Before cutover, add a dedicated continuity layer for legacy public access.

Minimum components:

- exact redirect store
- legacy pattern rules
- legacy URL map for fallback resolution
- unresolved request logging
- legacy file inventory

These can be implemented as dedicated persistence and service components without violating the current service-layer architecture.

### Phase 3: Implement the public SEO layer

Minimum SEO-facing capabilities:

- canonical URL generation
- AR and EN `hreflang`
- sitemap index and child sitemaps
- `robots.txt`
- Organization and Breadcrumb structured data where applicable

Existing contracts that should become real:

- `SeoMetadataServiceInterface`
- `NavigationServiceInterface`
- `PageServiceInterface`
- `PreviewServiceInterface`
- `HomepagePublishingServiceInterface`

Likely additional contracts needed for a clean implementation:

- a legacy URL resolver contract
- a sitemap generation contract
- a redirect management contract

### Phase 4: Solve the admin path conflict

The findings indicate that a historical public `/admin` context existed in the old estate.

Decide before launch:

- either preserve public legacy behavior on `/admin`
- or move the new control panel to a non-conflicting path such as `/cms` or another agreed internal route

Do not discover this conflict after cutover.

### Phase 5: Prepare migration tooling

Create operational tooling for:

- legacy URL extraction
- redirect import
- unresolved-request review
- file recovery and file mapping
- smoke-test URL lists

This work can live behind command classes, jobs, and services without pushing logic into controllers.

### Phase 6: Add tests that match migration risk

Minimum automated test coverage before launch:

- representative redirect tests
- canonical output tests
- `hreflang` output tests
- sitemap generation tests
- public file access tests
- legacy query-string resolution tests

The repository currently does not yet prove these behaviors. They must be added deliberately.

## Minimum Feature Set Before Public Cutover

The site is not ready for public replacement until all of the following are real:

- pages can render actual public content
- homepage can publish and render actual public content
- menus and settings drive the public shell
- legacy URLs resolve or redirect safely
- high-value files and PDFs remain reachable
- sitemap and canonical output are live
- Arabic and English alternates are correct
- unresolved-request logging is live

## Scope-Aware Content Strategy

Because the repository is only in current-scope foundation stage, use two tracks:

1. canonical rebuild now for current-scope public pages
2. archive or legacy preservation now for out-of-scope but valuable content

Do not force all historical sections into a partial rebuild just to say everything moved.

## Suggested Ownership Model

Content foundation work:

- homepage, pages, navigation, settings, media

Continuity work:

- redirects, legacy URL parsing, file inventory, unresolved logging

SEO presentation work:

- canonical, `hreflang`, sitemap, structured data, robots

Migration ops work:

- inventories, imports, monitoring, smoke testing

This reduces the chance that migration work is hidden inside unrelated feature tickets.

## Definition Of Done For This Blueprint

This blueprint is satisfied only when:

- placeholder services are replaced where launch-critical behavior is required
- continuity behavior exists in the service layer
- public cutover no longer depends on guesswork about legacy URLs
- the release team can prove the launch gates in [QA_AND_LAUNCH_SAFETY.md](QA_AND_LAUNCH_SAFETY.md)
