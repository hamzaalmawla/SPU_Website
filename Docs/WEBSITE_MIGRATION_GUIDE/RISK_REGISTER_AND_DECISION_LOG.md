# Risk Register And Decision Log

## Purpose

This document gives SPU a working risk register for the migration.

Use it as a live management tool, not as an archive.

## How To Use It

For each risk:

- assign an owner
- track current status
- document the mitigation
- record the final launch decision

## High-Priority Risks

| Risk | Severity | Why it matters | Early warning signal | Primary mitigation |
|---|---|---|---|---|
| full cutover before public foundation is built | critical | the new site cannot safely replace the old one yet | homepage exists but legacy destinations do not | delay cutover until current-scope public foundation and continuity layer exist |
| legacy URLs return `404` | critical | link equity and user journeys break | log spikes, user reports, crawl errors | redirect map plus fallback resolver |
| blanket homepage redirects | critical | intent mismatch and soft-404 risk | many old URLs land on one page | map to best equivalent or archive destination |
| file and PDF loss | critical | academic assets and backlinks disappear | broken downloads, search loss on documents | file inventory, recovery, redirects |
| `/admin` path conflict | critical | public legacy path and CMS path may collide | inconsistent route behavior | resolve path strategy before launch |
| multilingual canonical errors | high | AR and EN signals become unstable | wrong canonical or missing alternates | template-level canonical and `hreflang` QA |
| staging or draft indexing | high | duplicate and low-quality content leaks into search | staging URLs appear in crawl or index reports | authentication, non-production controls, QA |
| archive deletion because of sprint scope | high | long-tail visibility collapses | missing archive destinations | keep archive or continuity layer public |
| structured-data mismatch | medium-high | markup becomes misleading or ignored | Rich Results Test errors, markup warnings | use only accurate page-type markup |
| fragmented domain strategy | high | institutional visibility weakens | multiple central domains or inconsistent host use | keep one strong main institutional domain strategy |

## Search-Engine-Specific Risks

### Google-facing

- same-day domain, CMS, and layout changes increase migration risk
- inaccurate sitemap `lastmod` values reduce trust
- blocking pages in `robots.txt` while expecting `noindex` to work creates deindexing failures

### Bing-facing

- no Bing verification means no inspection or site-move support
- no sitemap or IndexNow support slows Bing-side discovery and updates

### Webometrics-facing

- multiple central domains can weaken visibility signals
- losing research and archive visibility can reduce institutional footprint
- weak researcher profile discoverability can reduce transparency support

## SPU-Specific Project Risks

| Decision area | Current reality | Required decision |
|---|---|---|
| full site replacement | repository is still foundation stage | do not claim full replacement yet |
| legacy content outside sprint scope | still likely valuable publicly | preserve via archive or continuity layer |
| page system readiness | not yet publicly proven | implement before cutover |
| redirect readiness | not yet publicly proven | implement before cutover |
| sitemap readiness | not yet publicly proven | implement before cutover |

## Decision Log Template

Use this for each major decision.

### Decision

Example:

- keep old archive public
- move CMS path from `/admin` to another path
- retain PDF URLs for policy documents

### Reason

Record the exact reasoning:

- user need
- SEO need
- operational need
- architectural constraint

### Chosen approach

Document the final choice clearly.

### Alternatives rejected

List what was rejected and why.

### Owner

Name the owner.

### Date

Record the date in full.

## Minimum Decisions That Must Be Logged

- cutover date approval
- admin path strategy
- archive preservation strategy
- file preservation strategy
- domain and hostname strategy
- sitemap strategy
- multilingual canonical strategy
- rollback trigger threshold

## Final Reminder

Most migration damage does not happen because nobody knew the theory.

It happens because teams never wrote down the decisions, owners, and thresholds clearly enough to act fast under pressure.
