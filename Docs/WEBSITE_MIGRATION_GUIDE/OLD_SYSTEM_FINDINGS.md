# Old System Findings

## Evidence Base

This document is based on direct inspection of:

- `spuedu_db.sql`
- extracted row counts and schema parsing from the dump
- stored URL values in legacy rows
- embedded old URLs found inside stored HTML content
- legacy config values that reference public subpaths

## What The Old DB Proves

### Structural facts

- 30 tables
- 0 views
- 0 declared foreign keys
- 0 declared constraints
- mostly `InnoDB`
- one `MyISAM` temp table: `dent_conf_temp`

### Language facts

`jx_languages` proves the old system knew these language symbols:

| ID | Symbol | Description | Default |
|---:|---|---|---|
| 1 | `ar` | Arabic | yes |
| 2 | `en` | English | no |
| 3 | `fr` | Francais | no |
| 6 | `sp` | Spanish | no |
| 7 | `ge` | German | no |

### Legacy public subpaths proven by config

`jx_config1` proves the old site used these public path contexts:

- root: `spu.edu.sy`
- `spu.edu.sy/med`
- `spu.edu.sy/dent`
- `spu.edu.sy/pharm`
- `spu.edu.sy/info`
- `spu.edu.sy/petrol`
- `spu.edu.sy/admin`
- `spu.edu.sy/research`
- `spu.edu.sy/hospital`
- `spu.edu.sy/alumni`
- `spu.edu.sy/clubs`
- `spu.edu.sy/members`

This is migration-critical.

## Legacy Entity Inventory

### Table classification

| Table | Rows | Likely role | Migration significance |
|---|---:|---|---|
| `jx_categories` | 4,944 | core public content tree with multilingual body, keywords, parent links, optional URLs | critical |
| `jx_items` | 22,323 | child assets and attachments for category records | critical |
| `jx_member_categories` | 349 | member/publication/research/course-like content tree | critical |
| `jx_member_items` | 429 | child files/attachments for member categories | critical |
| `jx_councils` | 648 | staff/faculty/person profiles | critical |
| `jx_councils1` | 397 | parallel legacy profile structure | critical |
| `jx_docs` | 45 | menu/static/link records with row-level language | high |
| `jx_site_static_pages` | 21 | static HTML pages | high |
| `jx_good_students` | 1,070 | honor/top students | medium-high |
| `jx_graduated_students` | 5,246 | alumni/graduates archive | high |
| `jx_home_photos` | 46 | homepage imagery | medium |
| `jx_faqs` | 1,553 | FAQs / public Q&A | medium |
| `jx_complaints` | 8 | suggestion/complaint style public content | low-medium |
| `jx_sites` | 20 | public external links | low-medium |
| `jx_logos` | 8 | logos/partner links | low-medium |
| `jx_job_sites` | 3 | job/external employment links | low |
| `jx_config` | 295 | site settings | critical support |
| `jx_config1` | 180 | alternate/newer site settings | critical support |
| `jx_languages` | 5 | languages | critical support |
| `jx_admins` | 20 | old admins | support |
| `jx_admins_services` | 213 | old permissions matrix | support |
| `jx_admin_category` | 0 | old link table | support |
| `jx_archive` | 1 | weak archive marker | low |
| `jx_items_comments` | 3 | public comments | low |
| `jx_complaint_cats` | 3 | complaint categories | low |
| `jx_countries` | 107 | lookup | support |
| `jx_cities` | 15 | lookup | support |
| `jx_members` | 0 | empty legacy member table | low |
| `jx_activation_codes` | 0 | old auth/support | low |
| `dent_conf_temp` | 415 | temp or event/registration leftovers | low |

## Old Content Model Observations

### Strong finding: `jx_categories` is not just taxonomy

The legacy name suggests categories, but the data pattern proves something broader:

- 4,019 rows contain body HTML in one or more `*_data` fields
- 3,749 rows have child `jx_items`
- 4,941 rows contain keyword data
- only 227 rows are explicit link rows (`is_link=1`)

This strongly suggests `jx_categories` is the main public content node table for many modules.

### Strong finding: `jx_items` is mostly child media/file support

- 15,962 rows have `photo`
- 6,000 rows have `en_file`
- only 39 rows have `video_link`
- `post_date` is effectively unused
- `added_date` is populated widely

This suggests many public legacy pages were category records with item attachments, not standalone item pages in the modern sense.

### Strong finding: member/research content follows a similar pattern

`jx_member_categories` plus `jx_member_items` looks like a second parallel content system for:

- publications
- research outputs
- course files
- member-linked resources

## Old URL Pattern Report

### Column-level facts

No old table contains columns named:

- `slug`
- `alias`
- `path`
- `uri`
- `permalink`
- `route`
- `canonical`

Explicit URL columns only exist in:

- `jx_categories.url`
- `jx_docs.url`
- `jx_councils.url`
- `jx_councils1.url`
- `jx_member_categories.url`
- `jx_home_photos.url`
- `jx_sites.url`
- `jx_logos.url`
- `jx_job_sites.url`

### Stored URL evidence

- 233 explicit URL values found in URL fields
- 169 of those are internal-style legacy URLs
- 64 are external links

### Legacy public URL matrix

| Content/function | Likely pattern | Evidence | Confidence |
|---|---|---|---|
| home | `index.php?lang={n}` or `index.php?mylang` | stored in `jx_categories`, `jx_docs` | high |
| contact | `index.php?dir=html&ex=1&page=contactus&lang={n}` | stored in `jx_categories`, `jx_docs` | high |
| FAQ | `index.php?page=faqs&ex=2&dir=faqs...` | stored URL values and embedded links | high |
| list pages | `index.php?page=list&ex=2&dir=items&service={n}` | stored URL values | high |
| photo lists | `index.php?page=list&ex=2&dir=photos&service={n}` | stored URL values | high |
| honor list | `index.php?page=list&ex=2&dir=good_students...` | stored URL values | high |
| graduates | `index.php?page=list&ex=2&dir=graduated_students&d={n}` | stored URL values | high |
| staff member detail | `/members/index.php?page=show&dir=councils&service={n}&council_id={id}...` | 153 embedded URLs | high |
| faculty/item detail | `/{subsite}/index.php?page=show&dir=items&cat_id={id}&ser={n}&lang={n}` | embedded URLs inside stored HTML | medium-high |
| faculty/staff detail | `/{subsite}/index.php?page=show&dir=councils&cat_id={id}|council_id={id}&service={n}&lang={n}` | embedded URLs inside stored HTML | medium |

### Important implication

The safest legacy preservation strategy is not slug reconstruction.

It is query-aware resolution.

## Old File / Document Inventory

### File-bearing fields

| Table | Field | Presence |
|---|---|---:|
| `jx_items` | `en_file` | 6,000 |
| `jx_member_items` | `en_file` | 422 |
| `jx_councils` | `cv`, `ar_cv` | 174 |
| `jx_councils1` | `cv` | 307 |
| `jx_items` | `photo` | 15,962 |
| `jx_categories` | `photo` | 2,477 |
| `jx_home_photos` | `photo` | 43 |

### File type observations

Main file-bearing extensions found:

- PDF
- DOC / DOCX
- PPT / PPTX
- JPG / PNG / JPEG

### Critical limitation

The DB stores filenames, not reliable public file paths.

That means:

- file inventory can be reconstructed
- exact file URLs cannot be reconstructed with full certainty from the DB alone
- physical legacy files remain a separate recovery problem

## Legacy Structures That Must Be Preserved

### Critical fields

- legacy source table name
- old row id
- parent id
- service/module code
- explicit URL if present
- host/subsite prefix
- row-level language if present
- translated titles and body HTML
- file names and media filenames
- visibility flags
- added and updated timestamps

### Critical content groups

- faculty/subsite landing structures
- category/body pages
- attachments and PDFs
- staff profiles and CVs
- graduates and honor lists
- research/publication-like content
- old static pages

## Legacy Duplicates, Orphans, and Conflicts

### Proven orphan relationships

- `jx_items.category_id` has 2 missing parent references
- `jx_categories.parent` has 21 missing parent references
- `jx_member_items.member_category_id` has 3 missing parent references
- `jx_member_categories.parent` has 31 missing parent references

### Proven duplicate-era structures

#### `jx_config` vs `jx_config1`

- 180 shared keys
- 22 conflicting values
- `jx_config1` contains public subsite/domain evidence

#### `jx_councils` vs `jx_councils1`

- overlap on 243 IDs only
- major differences in visibility, names, photos, CVs, and rank values

This means they cannot be blindly merged without reconciliation logic.

## What Is Strongly Suggested But Not Fully Proven

- legacy public detail pages were ID-driven, not slug-driven
- some faculty/public modules probably used the subsite prefix as part of their information architecture
- the old site likely had rewrite or template logic beyond what is stored in DB values

## What Cannot Be Known From The Old DB Alone

- exact rewrite rules
- exact canonical URL rules
- exact upload directory structure
- exact controller logic around `service`, `ser`, `act`, and `ex`
- exact template-level breadcrumbs and pagination behavior

