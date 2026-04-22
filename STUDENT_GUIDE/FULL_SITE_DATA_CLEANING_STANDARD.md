# Full-Site Data Cleaning Standard

## Purpose

This document defines the professional cleanup standard for migrating data out of `spuedu_db.sql` into a future full-site SPU platform.

## Core Principle

Never treat raw legacy data as trusted application data.

Every record must move through this pipeline:

1. raw legacy import
2. staging normalization
3. validation
4. transformation
5. target import
6. audit log and reconciliation

## Rule 1. Keep The Raw Dump Immutable

- do not manually edit `spuedu_db.sql`
- keep it as the historical source file
- all cleanup should happen in staging tables, SQL transforms, or import services

## Rule 2. Normalize Text Before Business Mapping

Required cleanup:

- trim leading and trailing spaces
- collapse repeated internal whitespace where business-safe
- remove invisible Unicode direction marks where they pollute values
- decode HTML entities where needed for editorial review
- convert inconsistent line endings to one standard

Examples from the dump:

- invisible characters in emails in `dent_conf_temp`
- trailing spaces in names and contact values across multiple tables

## Rule 3. Convert Legacy Character Set Safely

Legacy observations:

- the dump uses `utf8mb3`
- content contains Arabic, HTML entities, and mixed markup

Target rule:

- all future tables should use `utf8mb4`
- normalize badly encoded values before publication

## Rule 4. Replace Sentinel Dates

Legacy placeholders such as these are not real dates:

- `0000-00-00`
- `1970-01-01` when used as a fake default

Target rule:

- convert fake dates to `NULL`
- keep only dates that have real business meaning

## Rule 5. Sanitize Rich HTML Aggressively

Remove or quarantine:

- Microsoft Office XML
- `MsoNormal` and Word-generated class noise
- hidden spans and toolbar fragments
- unsafe inline scripts or tracking fragments
- spam and injected promotional links
- unnecessary inline styling that harms CMS portability

Keep only:

- paragraphs
- headings
- lists
- tables when really needed
- links that are valid and trusted
- strong/emphasis and basic formatting

## Rule 6. Review Every External URL

Legacy problems include:

- many `http://` links
- outdated social platforms
- suspicious or irrelevant injected domains

Target rule:

1. reject malicious or unrelated domains
2. upgrade to `https://` when valid
3. flag dead or outdated URLs for editorial review
4. store normalized URLs only

## Rule 7. Email Fields Must Be Validated, Not Trusted

Required actions:

- trim spaces
- remove invisible characters
- lowercase where appropriate
- reject non-email strings
- separate mixed values such as `email + notes`

Quarantine examples:

- `Sarab.Abboud@siust.edu.sy Phone`
- malformed local examples like `moudar@yyyy.ccc`

## Rule 8. Media Must Be Extracted And Registered

Legacy media appears as:

- filename references
- inline base64 blobs
- logo/image URLs
- PDF and CV filenames in entity rows

Target rule:

1. extract files into managed storage
2. create a `media` record for each physical asset kept
3. record original path, original filename, mime type, checksum if possible
4. link media through usage tables instead of free-text filenames

## Rule 9. Language Strategy Must Be Enforced

Legacy languages observed:

- `ar`
- `en`
- `fr`
- `sp`
- `ge`

Target standard for current future planning:

- required: `ar`
- required: `en`
- optional later: additional locales only after product approval

Migration rule:

- migrate AR/EN into translation tables first
- park FR/SP/GE in review tables or export files if not approved for live use

## Rule 10. Split Curated Content From User-Submitted Content

Examples:

- `jx_faqs` may mix public FAQ entries and submitted questions
- `jx_complaints` mixes inquiries, complaints, and suggestions

Target rule:

- curated public content goes to publishable CMS tables
- user submissions go to operational support tables

## Rule 11. Deduplicate Before Import

Common duplicate patterns to review:

- same person with slightly different name spellings
- repeated publication records
- duplicated alumni rows
- overlapping settings between `jx_config` and `jx_config1`

Recommended dedup keys:

- normalized email
- normalized full name plus year plus department
- normalized title plus file reference for publications
- normalized setting key plus site code

## Rule 12. Security Artifacts Must Not Be Migrated Blindly

Do not carry forward as trusted state:

- MD5 or legacy password hashes
- activation codes
- old login flags
- raw permission numbers without interpretation

Target rule:

- new password reset flow
- new role and permission model
- new security audit trail

## Rule 13. Every Rejected Record Must Be Logged

For every skipped row, capture:

- source table
- source id
- rejection reason
- raw key values needed for debugging
- timestamp

Reasons should be controlled and reusable, for example:

- invalid email
- orphaned parent
- unsupported locale
- unsafe HTML
- unresolved target module
- duplicate conflicting record

## Rule 14. Create Editorial Review Queues

Manual review is required for:

- pages with Word HTML
- pages with spam links
- entries with missing titles
- records with empty content but attached files
- records tied to out-of-scope microsites
- records with ambiguous module mapping

## Rule 15. Use Reconciliation Reports Per Module

After each module migration, report:

- source row count
- valid row count
- migrated row count
- rejected row count
- duplicate-merged row count
- media extracted count
- untranslated or unsupported locale count

## Recommended Quarantine Buckets

Use separate quarantine outputs for:

- invalid contacts
- suspicious HTML or links
- unsupported locales
- unresolved taxonomy
- duplicate settings
- missing files
- unknown service-type records

## Minimum Quality Gate Before Publishing Any Module

- [ ] no raw Word XML remains in published rich text
- [ ] no fake dates are shown publicly
- [ ] no invalid emails are used operationally
- [ ] no suspicious or unrelated links remain
- [ ] no orphaned child records exist
- [ ] AR and EN translations are complete where required
- [ ] imported media references resolve correctly

## Final Advice

The quality of the full-site migration will be determined less by how fast rows are imported and more by how strict the staging and cleaning discipline is.

Clean import beats fast import.
