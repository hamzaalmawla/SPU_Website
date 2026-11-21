# Student Coding Playbook
## How To Build The New SPU Website Without Causing Avoidable Ranking Loss

## Purpose

This document is for the student or junior developer who will write the code for the new SPU website.

It explains what to do while coding, what not to do, and how to make technical decisions that protect Google, Bing, and Webometrics outcomes during migration.

This is not a theory document.

It is a practical implementation guide for day-to-day coding decisions.

## The Mindset You Must Have

When you code this migration, do not think like a designer rebuilding pages from zero.

Think like a preservation engineer.

Your job is to protect:

- old URLs
- old files and PDFs
- search-engine understanding
- Arabic and English clarity
- institutional content that already has public value

If the new code looks modern but breaks those things, the code is not good enough.

## The First Rule

Never code as if the old website has no value.

The old website already contains:

- indexed pages
- backlinks
- faculty and research visibility
- archive value
- file and PDF entry points

Your code must respect that reality.

## What Matters Most While Coding

Every coding task should be checked against these questions:

1. Does this preserve or break old URL value?
2. Does this preserve or break Arabic and English clarity?
3. Does this preserve or break PDF and file access?
4. Does this create duplicate, weak, or thin pages?
5. Does this make it easier or harder for search engines to understand the new site?

If you cannot answer these questions, do not ship the code yet.

## Your Priority Order

Code in this order:

1. public foundation
2. migration continuity
3. metadata and indexing signals
4. monitoring and QA support
5. cosmetic improvements

This means:

- do not spend your best time on visual polish before redirect and continuity logic exist
- do not build fancy UI while old URLs still have nowhere safe to go

## What You Should Build First

### 1. Real public rendering

Before anything else, the new site must be able to render:

- homepage
- current-scope landing pages
- navigation shell
- public AR and EN variants

Do not assume one welcome page is enough.

### 2. Legacy continuity support

Before public cutover, there must be a safe way to handle:

- old exact URLs
- old query-string URLs
- old file URLs
- unresolved legacy requests

If old public URLs return `404`, you are not ready.

### 3. Public SEO signals

Before launch, the codebase must be able to output:

- canonical URLs
- `hreflang`
- sitemap data
- correct status codes
- clean crawlable links

### 4. Monitoring support

The code should help the team detect mistakes quickly:

- unresolved legacy requests
- broken file requests
- redirect misses
- unexpected response patterns

## How To Think About Each Feature

### Pages

When coding a page feature, ask:

- what old URL or old section does this replace?
- what is the Arabic URL?
- what is the English URL?
- what is the canonical URL?
- what is the alternate locale URL?
- what old URLs should redirect here?

If those answers do not exist, the feature is incomplete.

### Files And PDFs

When coding file handling, ask:

- is this file public?
- did an old version exist?
- does the old file URL still need to work?
- is there an Arabic version?
- is there an English version?
- should this file stay indexable?

Do not treat file handling as a side issue.

### Menus And Navigation

When coding navigation:

- use normal crawlable links
- use real `href` values
- do not depend on JavaScript-only navigation for critical discovery
- make sure important pages are reachable from internal links

### Arabic And English Support

When coding multilingual behavior:

- Arabic and English pages should each have their own correct URL
- Arabic should canonicalize to Arabic
- English should canonicalize to English
- each should reference the other with `hreflang`

Do not let one locale accidentally collapse into the other.

## Coding Rules For This Repository

These rules are not optional.

### Respect the architecture

- business logic belongs in services
- controllers stay thin
- public service methods do not return raw models
- dependencies should use interfaces in `app/Contracts`
- structured data belongs in a service or dedicated rendering layer, not scattered randomly across controllers

### Respect the project scope

This repository is still in foundation stage.

That means:

- do not pretend the whole old website is rebuilt
- do not delete old public value because the current sprint is smaller
- preserve out-of-scope content through archive or continuity support

### Respect migration reality

- if a section is not rebuilt, preserve it
- if a file is not yet remapped, track it and protect it
- if a redirect decision is unclear, do not guess carelessly

## What You Must Never Do

Do not:

- redirect everything to the homepage
- replace rich old sections with thin placeholders
- delete PDFs because they are inconvenient
- ship pages without canonicals or locale logic
- create crawl-blocking navigation
- block pages in `robots.txt` and expect `noindex` to work
- change domain, structure, and page logic all at once unless absolutely necessary

## The Quality Standard For Redirect Decisions

When you create redirect logic, use this standard:

### Good redirect

- old faculty profile -> matching new faculty profile
- old PDF -> matching new PDF or stable file landing page
- old research page -> equivalent research page or archive page

### Bad redirect

- old faculty profile -> homepage
- old PDF -> generic downloads page
- old research page -> unrelated landing page

The rule is simple:

redirect to the best equivalent destination, not the easiest destination.

## The Quality Standard For Metadata

Every public page should have:

- a clear title
- a clear meta description when appropriate
- one canonical URL
- correct locale logic
- internal links from relevant sections

The homepage and key templates should also support:

- Organization or WebSite identity markup where appropriate
- breadcrumb markup where appropriate

Do not add schema just because you can.
Add it only where the page type truly supports it.

## How To Work Safely On A Feature

For each feature, follow this sequence:

1. identify the old public behavior it affects
2. identify the new public behavior it should create
3. identify SEO risks
4. implement the smallest correct solution
5. test representative URLs
6. test Arabic and English behavior
7. test file behavior if files are involved
8. test canonical and status-code behavior

## Your Personal Checklist Before You Mark A Task Done

- does the feature preserve search value instead of only looking correct?
- does it respect the service-layer architecture?
- does it avoid raw model returns from public service interfaces?
- does it avoid hardcoded CMS content?
- does it preserve AR and EN logic correctly?
- does it preserve old URLs or document why not?
- does it preserve files or document why not?
- did you test representative real examples?

If the answer to any of these is no, the task is not done.

## Best Way To Handle Uncertainty

If you are unsure whether an old URL, file, or archive page still matters:

- assume it matters until proven otherwise
- do not delete it casually
- log it
- raise it for review

In migrations, cautious preservation is usually safer than aggressive cleanup.

## How To Use The Supporting Guides

Use these documents while coding:

- [SEO_PRESERVATION_GUIDE.md](SEO_PRESERVATION_GUIDE.md)
- [IMPLEMENTATION_BLUEPRINT.md](IMPLEMENTATION_BLUEPRINT.md)
- [SEARCH_CONTINUITY_BLUEPRINT.md](SEARCH_CONTINUITY_BLUEPRINT.md)
- [FILE_PDF_AND_ARCHIVE_PRESERVATION.md](FILE_PDF_AND_ARCHIVE_PRESERVATION.md)
- [STRUCTURED_DATA_AND_METADATA_MATRIX.md](STRUCTURED_DATA_AND_METADATA_MATRIX.md)
- [QA_AND_LAUNCH_SAFETY.md](QA_AND_LAUNCH_SAFETY.md)

## Final Advice To The Student

The professional goal is not to write a lot of code.

The professional goal is to write the right code in the right order so the university does not lose hard-earned visibility during migration.

That is what good migration engineering looks like.
