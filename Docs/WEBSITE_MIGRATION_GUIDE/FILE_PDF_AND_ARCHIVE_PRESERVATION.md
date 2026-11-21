# File, PDF, And Archive Preservation

## Why This Document Exists

For university websites, public value often lives not only in HTML pages, but also in:

- PDFs
- Word documents
- spreadsheets
- presentations
- archived reports
- faculty and research attachments

If those disappear during migration, SPU can lose:

- backlinks
- search entry points
- researcher visibility
- institutional trust

## What Google Can Index

Google can index many text-based file formats, including:

- PDF
- DOC and DOCX
- XLS and XLSX
- PPT and PPTX
- CSV

That means file preservation is a real SEO concern, not a side issue.

## Preservation Rules

1. Inventory important files before cutover.
2. Preserve high-value file URLs where possible.
3. If file URLs must change, redirect them permanently.
4. Do not replace a file with a generic landing page unless there is no better option.
5. Keep files crawlable if you want them discoverable.
6. Protect sensitive documents properly before publication.

## File Inventory Priorities

Inventory first:

- files linked from top landing pages
- files with known backlinks
- policy and governance documents
- admissions and academic regulations
- faculty CVs and researcher materials
- archive documents that still receive traffic

## File Canonical Strategy

If the same content exists in both HTML and file form, choose a deliberate canonical approach.

Examples:

- if HTML is the primary public version, use a `rel="canonical"` HTTP header on the PDF pointing to the HTML page
- if the PDF itself is the primary public artifact, keep the PDF self-canonical by not pointing it away unnecessarily

This is a direct application of Google's canonical documentation for non-HTML documents.

## File Language Strategy

If SPU has Arabic and English versions of a file, language relationships should be explicit.

Google supports `hreflang` via HTTP `Link` headers for non-HTML files such as PDFs.

Operational recommendation:

- use mirrored AR and EN file URLs where possible
- keep filenames and URL patterns stable and predictable
- return `hreflang` headers on both versions if both versions are public

This recommendation is an inference from Google's support for `hreflang` on non-HTML files.

## Do Not Use robots.txt As A Hiding Mechanism

Important:

- blocking a PDF in `robots.txt` does not guarantee that the URL disappears from search
- the URL can still appear if linked elsewhere

If a document must not appear in search:

- remove it
- protect it
- or use a valid deindexing method

## Sensitive And Redacted Documents

If a document contains sensitive information:

- redact it properly before publishing
- do not rely on visual black boxes over text
- review metadata before public release

If a sensitive file was already exposed:

1. remove the live file
2. use Search Console Removals if urgent
3. republish the corrected file at a new clean URL
4. update internal links

## Archive Preservation

Historical content still matters if it has:

- inbound links
- search demand
- institutional reference value
- academic or public-service value

Archive content can remain public even if it is not fully rebuilt in the new CMS.

Better options:

- preserve it as an archive page or archive file route
- keep it accessible with a clear canonical URL

Worse option:

- deleting it because it is outside the current sprint scope

## Retirement Rules

Retire a file only when:

- it is truly obsolete
- there is no better public replacement
- the decision is documented
- the team accepts the visibility loss

## QA Checklist

- [ ] representative PDFs open successfully
- [ ] representative DOC, XLS, or PPT assets still resolve if publicly needed
- [ ] old file URLs redirect correctly where changed
- [ ] file MIME behavior is correct
- [ ] AR and EN versions are handled intentionally
- [ ] sensitive documents are reviewed for metadata and redaction

## SPU-Specific Reminder

Because the current repository is still foundation stage, missing file handling is a launch blocker if key legacy files remain part of the public institutional footprint.

Do not treat files as optional migration leftovers.
