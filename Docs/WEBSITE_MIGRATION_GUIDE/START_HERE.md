# Start Here

## First Principle

Treat this as a search continuity project, not just a redesign project.

If the new site looks better but breaks old URLs, files, archives, and research discoverability, the migration is not successful.

## Four Truths You Must Keep In Mind

1. The old URL inventory is one of the most valuable migration assets.
2. A beautiful new interface does not replace redirects, canonicals, `hreflang`, and monitoring.
3. For a university, losing PDFs, researcher pages, or archive content can damage visibility far beyond the homepage.
4. The current Laravel repository is not yet safe for a full public cutover.

## What To Read In The First Hour

1. [AUTHORITATIVE_REFERENCES.md](AUTHORITATIVE_REFERENCES.md)
2. [STUDENT_EXECUTION_BRIEF.md](STUDENT_EXECUTION_BRIEF.md)
3. [OLD_SYSTEM_FINDINGS.md](OLD_SYSTEM_FINDINGS.md)
4. [NEW_SYSTEM_FINDINGS.md](NEW_SYSTEM_FINDINGS.md)
5. [SEO_PRESERVATION_GUIDE.md](SEO_PRESERVATION_GUIDE.md)
6. [IMPLEMENTATION_BLUEPRINT.md](IMPLEMENTATION_BLUEPRINT.md)
7. [QA_AND_LAUNCH_SAFETY.md](QA_AND_LAUNCH_SAFETY.md)

## Immediate Go Or No-Go Rule

Do not switch the public website to this Laravel application until all of the following are true:

- public content pages actually exist
- legacy URLs resolve safely
- high-value files and PDFs remain reachable
- sitemap and robots handling are live
- canonical and `hreflang` tags are correct
- Search Console and Bing Webmaster Tools are ready for monitoring

## The Three Questions This Package Answers

1. What does the old site contain, and what public value does it already have?
2. What does the current new codebase actually support today?
3. What must be built before cutover so SPU does not unnecessarily lose ranking signals?

## Current Project Guardrail

This repository is currently focused on:

- homepage
- public navigation shell
- bilingual landing-page foundation
- admin panel and CMS foundation

It is not yet a full replacement for all historical public sections.

That means out-of-scope content should be preserved through an archive or legacy continuity layer, not silently dropped.

## First Deliverables Expected From The Student

Before anyone talks about launch dates, produce these deliverables:

- a legacy URL inventory
- a high-value landing page inventory
- a PDF and file inventory
- a redirect mapping workbook
- a list of pages that must be rebuilt now
- a list of sections that must stay public through an archive layer
- a launch blockers list tied to this repository's actual state

## Most Important Mistakes To Avoid

- do not redirect everything to the homepage
- do not combine unnecessary domain, structure, and design changes on the same day
- do not delete legacy academic files because they are inconvenient
- do not launch with placeholder pages for valuable archive sections
- do not assume Google or Bing will "figure it out"

## Best Next Step

Read [STUDENT_EXECUTION_BRIEF.md](STUDENT_EXECUTION_BRIEF.md) next.

That file is the shortest professional brief you can hand to a student or junior implementer before work begins.
