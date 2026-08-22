# SPU Website Migration Guide

> Historical context notice (2026-08-21): the foundation-stage description below
> records the repository state when this guide was authored and is retained as
> migration evidence. It is not the current implementation status. Current
> remediation execution is tracked in
> `../CURRENT_REMEDIATION_EXECUTION_CHECKLIST.md`; no deployment or sign-off is claimed.

## Purpose

This package explains how to migrate `spu.edu.sy` from the legacy platform to the new Laravel platform without unnecessarily sacrificing discoverability in Google, Bing, or Webometrics.

It was reviewed against official search and ranking guidance on `2026-04-21`. See [AUTHORITATIVE_REFERENCES.md](AUTHORITATIVE_REFERENCES.md).

## Critical Current Reality

The current Laravel repository is still in foundation stage.

What the codebase currently proves:

- the only proven public route is `GET /`
- the only proven public view is the default Laravel welcome page
- core service contracts are bound to placeholder implementations
- no public redirect resolver, sitemap layer, page rendering layer, or legacy URL continuity layer is implemented yet

That means the immediate objective is not "launch the new design quickly".

The immediate objective is "build a migration-safe public foundation, then cut over".

## What Success Looks Like

This package is successful only if the final migration:

- preserves high-value legacy URLs with exact or best-fit permanent redirects
- keeps Arabic and English content crawlable and correctly annotated
- preserves important files, PDFs, researcher pages, and archival pages
- avoids breaking existing backlinks and institutional references
- keeps search engines informed through valid sitemap, canonical, and monitoring signals
- protects the university's broader web footprint, not only the homepage

Important:

- a responsible migration plan can reduce ranking loss and speed recovery
- no honest migration guide can guarantee zero fluctuation in rankings or traffic

## Why This Matters For SPU

For a university website, migration risk is larger than for a simple marketing site.

SPU's public visibility depends on:

- indexed academic and institutional pages
- faculty and profile pages
- PDFs and downloadable academic assets
- historical archives
- multilingual discoverability
- stable backlinks from external domains

That affects Google and Bing directly, and it also affects the institutional web presence signals that support Webometrics performance.

## What This Package Contains

Core guidance:

- [START_HERE.md](START_HERE.md)
- [STUDENT_EXECUTION_BRIEF.md](STUDENT_EXECUTION_BRIEF.md)
- [STUDENT_CODING_PLAYBOOK.md](STUDENT_CODING_PLAYBOOK.md)
- [MANDATORY_CODE_IMPLEMENTATION_REQUIREMENTS.md](MANDATORY_CODE_IMPLEMENTATION_REQUIREMENTS.md)
- [SEO_PRESERVATION_GUIDE.md](SEO_PRESERVATION_GUIDE.md)
- [IMPLEMENTATION_BLUEPRINT.md](IMPLEMENTATION_BLUEPRINT.md)
- [QA_AND_LAUNCH_SAFETY.md](QA_AND_LAUNCH_SAFETY.md)
- [QUICK_REFERENCE.md](QUICK_REFERENCE.md)
- [LAUNCH_COMMAND_CENTER_PLAYBOOK.md](LAUNCH_COMMAND_CENTER_PLAYBOOK.md)
- [RISK_REGISTER_AND_DECISION_LOG.md](RISK_REGISTER_AND_DECISION_LOG.md)
- [FILE_PDF_AND_ARCHIVE_PRESERVATION.md](FILE_PDF_AND_ARCHIVE_PRESERVATION.md)
- [STRUCTURED_DATA_AND_METADATA_MATRIX.md](STRUCTURED_DATA_AND_METADATA_MATRIX.md)

Project evidence and architecture:

- [OLD_SYSTEM_FINDINGS.md](OLD_SYSTEM_FINDINGS.md)
- [NEW_SYSTEM_FINDINGS.md](NEW_SYSTEM_FINDINGS.md)
- [LEGACY_TO_NEW_MAPPING.md](LEGACY_TO_NEW_MAPPING.md)
- [SEARCH_CONTINUITY_BLUEPRINT.md](SEARCH_CONTINUITY_BLUEPRINT.md)
- [SQL_AND_TECHNICAL_APPENDIX.md](SQL_AND_TECHNICAL_APPENDIX.md)

Teaching and presentation companions:

- [HOW_THIS_PREVENTS_RANKING_DROPS.md](HOW_THIS_PREVENTS_RANKING_DROPS.md)
- [ULTIMATE_SEO_MIGRATION_GUIDE.md](ULTIMATE_SEO_MIGRATION_GUIDE.md)
- [VISUAL_ROADMAP.md](VISUAL_ROADMAP.md)
- [RANKING_PROTECTION_VISUAL_GUIDE.md](RANKING_PROTECTION_VISUAL_GUIDE.md)

Sources:

- [AUTHORITATIVE_REFERENCES.md](AUTHORITATIVE_REFERENCES.md)

## Recommended Reading Order

For the student or delivery owner:

1. [START_HERE.md](START_HERE.md)
2. [STUDENT_EXECUTION_BRIEF.md](STUDENT_EXECUTION_BRIEF.md)
3. [STUDENT_CODING_PLAYBOOK.md](STUDENT_CODING_PLAYBOOK.md)
4. [MANDATORY_CODE_IMPLEMENTATION_REQUIREMENTS.md](MANDATORY_CODE_IMPLEMENTATION_REQUIREMENTS.md)
5. [SEO_PRESERVATION_GUIDE.md](SEO_PRESERVATION_GUIDE.md)
6. [LAUNCH_COMMAND_CENTER_PLAYBOOK.md](LAUNCH_COMMAND_CENTER_PLAYBOOK.md)
7. [RISK_REGISTER_AND_DECISION_LOG.md](RISK_REGISTER_AND_DECISION_LOG.md)
8. [OLD_SYSTEM_FINDINGS.md](OLD_SYSTEM_FINDINGS.md)
9. [NEW_SYSTEM_FINDINGS.md](NEW_SYSTEM_FINDINGS.md)
10. [IMPLEMENTATION_BLUEPRINT.md](IMPLEMENTATION_BLUEPRINT.md)
11. [QA_AND_LAUNCH_SAFETY.md](QA_AND_LAUNCH_SAFETY.md)

For technical SEO and migration planning:

1. [SEO_PRESERVATION_GUIDE.md](SEO_PRESERVATION_GUIDE.md)
2. [LEGACY_TO_NEW_MAPPING.md](LEGACY_TO_NEW_MAPPING.md)
3. [SEARCH_CONTINUITY_BLUEPRINT.md](SEARCH_CONTINUITY_BLUEPRINT.md)
4. [FILE_PDF_AND_ARCHIVE_PRESERVATION.md](FILE_PDF_AND_ARCHIVE_PRESERVATION.md)
5. [STRUCTURED_DATA_AND_METADATA_MATRIX.md](STRUCTURED_DATA_AND_METADATA_MATRIX.md)
6. [QA_AND_LAUNCH_SAFETY.md](QA_AND_LAUNCH_SAFETY.md)
7. [AUTHORITATIVE_REFERENCES.md](AUTHORITATIVE_REFERENCES.md)

For architecture and implementation work:

1. [NEW_SYSTEM_FINDINGS.md](NEW_SYSTEM_FINDINGS.md)
2. [IMPLEMENTATION_BLUEPRINT.md](IMPLEMENTATION_BLUEPRINT.md)
3. [SEARCH_CONTINUITY_BLUEPRINT.md](SEARCH_CONTINUITY_BLUEPRINT.md)
4. [FILE_PDF_AND_ARCHIVE_PRESERVATION.md](FILE_PDF_AND_ARCHIVE_PRESERVATION.md)
5. [STRUCTURED_DATA_AND_METADATA_MATRIX.md](STRUCTURED_DATA_AND_METADATA_MATRIX.md)
6. [SQL_AND_TECHNICAL_APPENDIX.md](SQL_AND_TECHNICAL_APPENDIX.md)

## The Most Important Message

Do not replace the old public site with this Laravel project until the migration safety layer exists.

Minimum launch gates:

- real public page rendering
- page-level redirect and legacy URL resolution
- sitemap generation
- canonical and `hreflang` output
- file and PDF preservation plan
- Search Console and Bing monitoring
- post-launch log triage process

If a section is not rebuilt yet, preserve it through an archive or legacy-resolution layer.

Do not replace valuable public content with thin placeholders.
