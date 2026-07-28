# Professor Legacy Code Audit Prompt

Copy everything below into the OpenCode session that has access to the old production PHP code and old database schema/dump.

Do not run this prompt in the new Laravel repository unless the old source code is also available there.

---

```text
You are auditing the OLD production SPU website codebase to prepare a complete, evidence-backed migration and URL-continuity specification for a new Laravel website.

This is a READ-ONLY FORENSIC AUDIT.

Do not edit, delete, format, refactor, run migrations, run imports, alter the database, alter files, change configuration, or commit anything. Do not expose secrets. Do not print database passwords, API keys, `.env` values, private keys, SMTP credentials, tokens, or user password hashes. If a file contains secrets, report only its purpose and redacted key names.

Your job is to discover, prove, and document every important relationship among:

1. old public URLs
2. old PHP routing/dispatch logic
3. old database tables/columns/IDs/service types
4. old subsites and locale behavior
5. old file/media URL paths and filesystem behavior
6. old template/page/module rendering behavior
7. exact redirect requirements for a new Laravel site

The new site must preserve old public links correctly. It must redirect an old dynamic URL to the right modern page or equivalent page when evidence supports it, serve an old physical file when it still exists, archive valuable content when no modern module exists, return 410 only for intentional retirements, and return/log a real 404 for unknown URLs. It must NEVER use the homepage as a generic fallback.

The new Laravel project already has migration/continuity infrastructure, but we need the old code to provide definitive route and database semantics. Your output will be used to build deterministic importers, query resolvers, exact redirects, archive decisions, file continuity rules, and deployment configuration.

## Required Working Method

1. Explore the entire old codebase before drawing conclusions.
2. Search all PHP, templates, JavaScript, Apache/LiteSpeed config, `.htaccess`, SQL schema/dump, config files, menu builders, URL helpers, upload handlers, and language helpers.
3. Treat old code behavior as evidence. Never infer a mapping merely from a table/column name.
4. Every important conclusion must cite the exact relative file path and line number(s). For database conclusions, cite the exact SQL table/column and the PHP query/use site(s).
5. If something cannot be proven from code/schema, mark it `UNKNOWN` or `NEEDS_RUNTIME_EVIDENCE`. Do not guess.
6. Distinguish:
   - public dynamic URLs
   - public static files
   - authenticated/admin URLs
   - internal-only routes
   - dead/unreferenced code
   - external URLs
7. Preserve Arabic/English behavior. Document numeric language IDs, language symbols, locale defaults, and language switching exactly.
8. Do not merely provide a narrative. Produce the requested structured artifacts and final report.

## Scope Questions You Must Answer

### A. Application/Deployment Architecture

Determine:

- the main front controller(s), including all `index.php` files
- every subsite directory and its public purpose
- document-root assumptions
- all `.htaccess`, Apache, LiteSpeed, Nginx, or cPanel rewrite rules
- public static-file handling behavior
- upload/download directories and whether they are inside or outside document root
- any aliases, symlinks, virtual paths, download controllers, or PHP file streaming
- PHP/database connection selection by subsite
- whether old code references multiple database schemas/connections

Output a deployment/path map with:

| Public URL Prefix | Physical Directory/Handler | Dynamic or Static | Authentication | Evidence |
| --- | --- | --- | --- | --- |

### B. Complete Legacy Route Grammar

Find every public URL form generated, accepted, or linked by old code.

This includes, but is not limited to:

- `/index.php?...`
- `/med/index.php?...`
- `/dent/index.php?...`
- `/pharm/index.php?...`
- `/info/index.php?...`
- `/petrol/index.php?...`
- `/research/index.php?...`
- `/hospital/index.php?...`
- `/alumni/index.php?...`
- `/clubs/index.php?...`
- `/members/index.php?...`
- old `/admin/index.php?...` URLs that might actually be public historical pages
- `.html` aliases
- static document/image/download paths
- direct links emitted by templates, menus, database content, JavaScript, RSS, sitemap, or email templates

For every route family, document:

| Route Family ID | Example Legacy URL | Required Parameters | Optional Parameters | Parameter Meanings | Dispatcher File/Lines | Public? | Locale Rule | Source Table(s) | Source ID Column | Detail/List/Action | File/Page Result |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |

Include exact query parameter names and all aliases such as `id`, `cat_id`, `item_id`, `act`, `service`, `dir`, `page`, `ex`, `lang`, `site`, and any others found.

For each route family, state whether query parameter order matters in old code. If parameters are ignored or defaulted, document that behavior.

### C. Route Dispatch And URL Generation

Find both sides:

1. Where old URLs are generated.
2. Where requests are parsed/dispatched.

For every URL builder/helper/function/template pattern:

- quote the URL format exactly
- list source variables and their meaning
- identify source table/ID used in the URL
- identify locale/subsite behavior
- cite file/line evidence

For every dispatcher/router:

- trace request path + query to handler/template
- show validation/default behavior
- show how it selects a database table/query
- show what happens when a source ID is missing
- show whether it redirects, renders 404, renders homepage, or silently falls back

Create a `legacy_url_builders.csv` artifact with columns:

```text
builder_id,source_file,source_lines,url_template,subsite,dir,page,service,parameter_names,locale_input,source_table,source_id_column,public_status,evidence_notes
```

Create a `legacy_route_dispatch.csv` artifact with columns:

```text
route_family_id,path_pattern,required_query,optional_query,dispatcher_file,dispatcher_lines,handler_or_template,source_table,source_id_column,missing_id_behavior,locale_behavior,public_status,evidence_notes
```

### D. Full Database Ownership Map

Inventory all old database tables, but do not merely list them.

For every table, determine from code:

- owning module/subsite
- whether it is public content, private/admin data, configuration, relationship data, logs, file metadata, or dead/unknown
- public route families that read it
- write/admin code that manages it
- parent/child relationships
- locale columns and locale semantics
- visibility/publish columns and exact meanings
- date/order columns
- media/file columns
- primary key and important foreign keys
- target migration recommendation

Create `legacy_database_ownership.csv` with:

```text
table_name,primary_key,owning_module,subsite,record_kind,publicly_rendered,public_route_families,admin_write_paths,parent_tables,child_tables,locale_columns,visibility_columns,ordering_columns,date_columns,file_columns,important_columns,migration_recommendation,evidence
```

### E. `service_type` and Other Code Dictionaries

This is mandatory.

Find every place where `service_type`, `service_id`, `site_id`, `dir`, `page`, `ex`, language ID, or similar discriminator is compared, assigned, switched, mapped, or used in SQL.

Build a complete dictionary, not only the common values.

Create `legacy_service_type_dictionary.csv` with:

```text
discriminator_name,discriminator_value,meaning,module,subsite,source_table,public_route_family,target_template_or_handler,visibility_rule,locale_rule,evidence_file,evidence_lines,confidence,notes
```

If a discriminator value has different meaning depending on subsite/context, create separate rows. Never state that a value has one global meaning unless code proves it.

### F. `jx_categories` and `jx_items` Deep Analysis

The new project has `4,944` `jx_categories` rows. They must not be imported as generic pages.

For `jx_categories` and `jx_items`, determine:

- what each `service_type` means in each subsite/context
- whether the record is a page, news article, announcement, event, faculty content, project, department, research record, category/list container, or something else
- relationship between category and item records
- `category_id`, parent IDs, service types, display order, visibility, and date semantics
- which route URL is generated for category detail versus item detail
- which legacy records already correspond to migrated news/announcement records
- which groups can map to existing new modules
- which groups require archive/retirement rather than canonical import

Produce a separate `jx_categories_migration_matrix.csv` with:

```text
legacy_id,service_type,subsite_context,ar_name,en_name,visibility,legacy_url_pattern,record_semantic_type,child_table_relationship,current_new_module_candidate,current_new_route_candidate,recommended_outcome,required_importer_or_resolver,confidence,evidence,manual_review_reason
```

Do not invent current new route candidates. If the old code cannot tell the new route, leave it as `NEEDS_NEW_SITE_MAPPING`.

### G. Other High-Value Legacy Tables

Perform the same semantic analysis for at least:

- `jx_member_categories`
- `jx_member_items`
- `jx_councils`
- `jx_councils1`
- `jx_site_static_pages`
- `jx_docs`
- `jx_sites`
- `jx_home_photos`
- `jx_logos`
- `jx_config`
- `jx_config1`
- `jx_faqs`
- `jx_job_sites`
- `jx_graduated_students`
- `jx_good_students`
- `jx_countries`
- `jx_cities`
- complaint/admin tables, classified as private/public/retire only

For each, state exactly:

- old purpose
- public URL behavior
- target migration recommendation
- redirect behavior if not imported
- file/media behavior
- privacy concerns

### H. File and Media Continuity Map

Find all file/image/document paths generated or consumed by old code.

Identify:

- every file field in DB tables
- every URL prefix and physical directory mapping
- file serving code and download behavior
- whether files are direct web-server files or streamed through PHP
- public versus protected files
- filename transformations, timestamp prefixes, encoding, path normalization, and extension behavior
- image resizing/thumb/cache directories
- whether any URLs use `downloads/files`, `downloads/files2`, `cv_bank`, `images`, `med`, `research`, or other roots

Create `legacy_file_path_map.csv`:

```text
public_url_prefix,physical_path_expression,source_table,source_column,file_type,served_by,access_control,path_normalization,legacy_filename_behavior,known_examples,evidence_file,evidence_lines,cutover_requirement
```

Also produce an explicit list titled `MUST_PRESERVE_PUBLIC_FILE_PREFIXES`.

### I. Locale and Language Behavior

Document exactly:

- all old language IDs
- locale symbols/codes
- default language
- URL/query language parameter behavior
- language switching rules
- fallback behavior when translation is missing
- AR/EN and any unsupported locale records
- RTL/LTR behavior if controlled by server/templates

Create `legacy_locale_dictionary.csv`:

```text
old_language_id,old_symbol,locale_code,display_name,default_behavior,url_parameter_behavior,fallback_behavior,evidence
```

### J. Menus, Internal Links, Sitemap, and SEO Signals

Find:

- navigation/menu generation
- footer links
- sitemap generation or static sitemap files
- RSS feeds
- canonical/meta/robots behavior
- internal hardcoded links in templates/PHP/JS
- old `.html` aliases
- legacy URL links embedded in database HTML

Deliver:

- a list of all internal URL patterns emitted by old code
- a list of all public menu sources and their tables
- a list of static `.html` aliases
- SEO-critical old paths that should receive special redirect review

### K. Admin, Security, and Privacy Boundaries

Identify old routes that must NOT be redirected into new public/admin functionality:

- old admin login/actions
- user/admin profile pages
- private uploads
- complaint/support submissions
- password/reset endpoints
- internal tools
- API/webhook endpoints

For each, recommend one of:

- preserve only behind new authenticated system
- no redirect / 404
- intentional 410
- manually reviewed public equivalent

Never recommend redirecting old admin URLs to the new admin login unless there is an explicit product decision.

## Required Final Deliverables

Create a directory named `legacy-audit-output/` in the old-code workspace, unless there is an existing approved audit directory. Do not overwrite unrelated files.

Write these files:

1. `LEGACY_CODE_AND_CONTINUITY_AUDIT.md`
2. `legacy_url_builders.csv`
3. `legacy_route_dispatch.csv`
4. `legacy_database_ownership.csv`
5. `legacy_service_type_dictionary.csv`
6. `jx_categories_migration_matrix.csv`
7. `legacy_file_path_map.csv`
8. `legacy_locale_dictionary.csv`
9. `legacy_redirect_candidate_families.csv`
10. `legacy_unknowns_and_required_runtime_evidence.csv`
11. `legacy_public_url_smoke_test_matrix.csv`

### Required Columns for Redirect Candidate Families

`legacy_redirect_candidate_families.csv` must contain:

```text
route_family_id,legacy_example,normalization_rule,source_table,source_id_parameter,source_id_column,locale_rule,current_target_requirement,recommended_redirect_mechanism,redirect_status,confidence,blockers,evidence
```

Recommended redirect mechanism must be one of:

- `deterministic_query_resolver`
- `query_signature_exact_redirect`
- `path_exact_redirect`
- `path_pattern_redirect`
- `serve_existing_file`
- `file_inventory_redirect`
- `archive_route_required`
- `intentional_410`
- `unresolved_404_log`

### Required Smoke Test Matrix

`legacy_public_url_smoke_test_matrix.csv` must contain:

```text
priority,legacy_url,route_family_id,expected_old_behavior,proposed_new_behavior,expected_http_status,expected_destination_or_file,locale,source_table,source_id,evidence,notes
```

Include representative URLs for every public route family, every subsite, Arabic/English, reordered query parameters, missing IDs, disabled/hidden records, direct files, encoded file names, and `.html` aliases.

## Required Final Markdown Report Structure

The `LEGACY_CODE_AND_CONTINUITY_AUDIT.md` report must contain these sections in this exact order:

1. Executive Summary
2. Audit Scope and Evidence Sources
3. Old Application and Deployment Architecture
4. Complete Public Route Grammar
5. URL Dispatch and URL Generation Findings
6. Subsite Map
7. Locale/Language Map
8. Database Ownership Map
9. `service_type` and Discriminator Dictionary
10. `jx_categories` / `jx_items` Semantic Map
11. High-Value Table Findings
12. File and Media Path Continuity Map
13. Menus, Internal Links, Sitemap, and SEO Findings
14. Public/Private/Admin Boundary Decisions
15. Deterministic Redirect Families
16. Exact Redirect Exceptions Needed
17. Content Import Recommendations by Module
18. Archive Recommendations
19. Intentional Retirement/410 Recommendations
20. Unknowns, Ambiguities, and Required Runtime Evidence
21. Cutover Requirements for cPanel/Apache/LiteSpeed
22. Recommended Implementation Order for the New Laravel Site
23. Launch Smoke-Test Matrix Summary
24. Risks That Must Block Cutover
25. File/Line Evidence Appendix

## Required Quality Bar

- Do not say “probably”, “likely”, or “seems” without explicitly labeling it low confidence and explaining what evidence is missing.
- Do not omit a route family because it is inconvenient or old.
- Do not recommend broad table-to-table imports.
- Do not expose credentials or sensitive user data.
- Do not confuse physical file continuity with database migration.
- Do not confuse old public `/admin` route context with the new Laravel admin panel.
- Do not generate generic homepage redirects.
- Do not claim a redirect is safe unless the source semantics and target requirement are proven.
- Do not stop after giving a narrative. Write all artifacts.

## Final Chat Response Required

After writing the artifacts, respond with:

1. Paths to every generated artifact.
2. Count of discovered public route families.
3. Count of URL builders.
4. Count of service/discriminator mappings.
5. Count of legacy database tables with verified ownership.
6. Count of must-preserve file prefixes.
7. Count of deterministic redirect families.
8. Count of route families requiring exact/manual mapping.
9. Count of unknowns requiring runtime evidence.
10. The top 20 cutover blockers ordered by severity.
11. A concise list of the first engineering tasks the new Laravel project must implement.

Do not start coding the new Laravel project. This task is investigation, evidence collection, structured artifacts, and a complete migration/continuity specification only.
```
