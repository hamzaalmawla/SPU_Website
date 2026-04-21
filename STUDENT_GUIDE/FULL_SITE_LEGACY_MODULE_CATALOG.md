# Full-Site Legacy Module Catalog

## Purpose

This file maps every legacy table in `spuedu_db.sql` to its most likely future module and recommended migration treatment.

## Key Reading Rule

Legacy table names are not always truthful.

Some of the most misleading examples:

- `jx_member_categories`
- `jx_member_items`
- `jx_docs`
- `jx_job_sites`

Always verify actual row content before final implementation.

## Table Catalog

| Legacy table | Observed role | Future module | Proposed target tables | Recommended treatment | Confidence |
|---|---|---|---|---|---|
| `dent_conf_temp` | temporary conference or form registrations | staging / quarantine | `migration_rejections`, optional `event_registrations_staging` | exclude from core migration unless business owner confirms value | high |
| `jx_activation_codes` | activation / verification records | identity and access | none or `password_reset_tokens` equivalent | do not migrate as business data; regenerate in new system | high |
| `jx_admins` | admin accounts with legacy permissions | identity and access | `users`, `user_role_assignments` | migrate users only after forced password reset design | high |
| `jx_admins_services` | module/service permission matrix | identity and access | `permissions`, `user_scope_assignments` | convert numeric service rules into explicit permission model | medium |
| `jx_admin_category` | admin scoping by category and service | identity and access | `user_scope_assignments` | preserve only if scoped editorial ownership is still needed | medium |
| `jx_archive` | archived item references | archive and audit | `content_archives`, `migration_logs` | preserve as metadata, not as a direct public content table | high |
| `jx_categories` | generic multilingual categories, pages, section nodes, links | taxonomy / navigation / CMS | `news_categories`, `event_categories`, `page_sections`, `menu_items`, other explicit category tables | split by actual usage and service context | medium |
| `jx_cities` | reference cities | global references | `cities`, `city_translations` | clean names and attach to countries with FKs | high |
| `jx_complaints` | suggestion / complaint / inquiry inbox | FAQ and support | `complaints` | sanitize HTML, validate contact fields, route via category | high |
| `jx_complaint_cats` | complaint routing categories | FAQ and support | `complaint_categories`, `complaint_category_translations` | migrate after email/routing review | high |
| `jx_config` | site and microsite settings | settings and site configuration | `settings`, `setting_groups` | reconcile with `jx_config1`, not direct merge | high |
| `jx_config1` | secondary or duplicate settings store | settings and site configuration | `settings`, `setting_groups` | compare key-by-key against `jx_config` before migration | high |
| `jx_councils` | governance records, council entities, leadership profiles | governance and councils | `councils`, `council_translations`, `council_members`, `council_member_translations` | split entity rows from person/profile rows | medium |
| `jx_councils1` | council member / faculty leadership directory | governance and councils | `council_members`, `council_member_translations`, `council_memberships` | map to member profiles with parent council relation | high |
| `jx_countries` | reference countries | global references | `countries`, `country_translations` | normalize names and standardize ISO codes manually | high |
| `jx_docs` | document tree, menu-like links, attached files, navigation nodes | navigation / documents / resources | `documents`, `document_translations`, `menus`, `menu_items`, `link_groups`, `link_items` | classify by `is_link`, `file`, `parent`, `service` before import | medium |
| `jx_faqs` | FAQ entries and submitted questions | FAQ and support | `faqs`, `faq_translations`, optional `support_requests` | split curated FAQ content from submitted records | high |
| `jx_good_students` | honor student records | students and alumni | `honor_students`, optional `honor_student_translations` | map department and year carefully | high |
| `jx_graduated_students` | alumni/graduated student records | students and alumni | `alumni`, `alumni_profiles` | deduplicate and normalize graduation year / department fields | high |
| `jx_home_photos` | homepage and microsite image pools | pages and CMS / media | `homepage_sections`, `media`, `media_usages` | group by `site` and `record_order` during import | high |
| `jx_items` | generic content feed: news, events, notices, files, schedules, offers | news / events / research / student resources / CMS | explicit module tables per content stream | classify by `service_type`, `category_id`, file usage, and content patterns before migration | medium |
| `jx_items_comments` | public comments tied to generic items | comments and engagement | `comments` | optional future module; moderate after identity/spam review | high |
| `jx_job_sites` | external career or job resource links | careers and opportunities | `career_links`, `career_link_translations` | migrate as curated external resources, not as applications | high |
| `jx_languages` | legacy locale registry | global references | `languages`, `language_translations` | migrate `ar` and `en` first; review others separately | high |
| `jx_logos` | partner logos and linked brand blocks | media / external links | `media`, `link_items`, `partner_logos` | preserve logos only after URL/domain review | high |
| `jx_members` | generic people directory with contact data | faculties and academic units | `faculty_members`, `faculty_member_translations`, optional `people_profiles` | classify faculty vs generic public contacts | medium |
| `jx_member_categories` | despite name, behaves like member-related publication/content records | research and publications | `research_publications`, `research_publication_translations` | treat as publication-like parent records, not simple categories | medium |
| `jx_member_items` | child content or file records under member publication records | research and publications | `research_files`, `document_attachments`, optional `research_publication_assets` | attach to migrated parent publication/entity | medium |
| `jx_sites` | useful links / partner sites / external resources | navigation / resources | `link_groups`, `link_items`, `link_item_translations` | migrate after external URL review | high |
| `jx_site_static_pages` | hard-coded multilingual static pages by page ID | pages and CMS | `pages`, `page_translations`, `page_seo_meta` | convert each ID to a named page slug and clean HTML heavily | high |

## Highest-Risk Legacy Tables

These need the most careful interpretation before coding:

1. `jx_items`
2. `jx_categories`
3. `jx_docs`
4. `jx_member_categories`
5. `jx_councils`
6. `jx_config` and `jx_config1`

## Why These Tables Are High Risk

- they mix multiple business meanings
- they use weak or implicit relationships
- they contain multilingual wide columns
- they often store HTML, files, links, and metadata in the same row model

## Implementation Order Recommendation

Use this table order when building a full-site mapping spreadsheet:

1. `jx_languages`, `jx_countries`, `jx_cities`
2. `jx_admins`, `jx_admins_services`, `jx_admin_category`
3. `jx_config`, `jx_config1`
4. `jx_sites`, `jx_logos`, `jx_docs`
5. `jx_site_static_pages`, `jx_home_photos`
6. `jx_categories`, `jx_items`
7. `jx_members`, `jx_member_categories`, `jx_member_items`
8. `jx_councils`, `jx_councils1`
9. `jx_good_students`, `jx_graduated_students`
10. `jx_faqs`, `jx_complaints`, `jx_complaint_cats`, `jx_job_sites`, `jx_items_comments`
11. `jx_archive`, `dent_conf_temp`, `jx_activation_codes`

## Final Advice

If a legacy table needs a paragraph to explain what it really does, that is a strong signal that the future schema should be split into smaller, clearer entities.
