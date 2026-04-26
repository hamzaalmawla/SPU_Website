# Full-Site Migration Blueprint

## Purpose

This document defines a future-state migration blueprint for the full SPU public website, based on the legacy `spuedu_db.sql` dump.

It is intentionally broader than the current repository code scope.

Use it when you need a professional answer to these questions:

- how should the old 30-table dump be turned into a modern university platform?
- what modules should exist in the future system?
- what order should implementation happen in?
- what dependencies and risks must be handled before coding?

## Executive Summary

The legacy database is not a clean domain model.
It is a content-heavy operational dump that mixes:

- website settings
- multilingual content
- faculty and governance directories
- research and publication content
- student-facing FAQs and complaints
- alumni and honor-student data
- homepage media
- external resource links

The correct full-site migration strategy is not table-for-table cloning.
It is domain decomposition.

The future platform should be split into explicit modules with AR/EN support, modern foreign keys, service-layer business logic, media normalization, revision/publish workflows, and a staging-based import pipeline.

## Migration Goals

1. Preserve valuable historical content.
2. Drop or quarantine legacy noise, spam, and invalid records.
3. Convert generic content structures into explicit domain modules.
4. Reduce language sprawl from legacy five-language storage to the approved target model.
5. Enforce data integrity the legacy database never enforced.
6. Make every public content area manageable through a proper CMS/admin interface.

## Full-Site Future Modules

### 1. Identity and Access

Responsibilities:

- admin users
- roles and permissions
- login security
- account locking and password reset

Legacy sources:

- `jx_admins`
- `jx_activation_codes`
- `jx_admins_services`
- `jx_admin_category`

Future tables:

- `users`
- `roles`
- `permissions`
- `user_role_assignments`
- `user_scope_assignments`
- `failed_login_attempts`

### 2. Global References

Responsibilities:

- locales
- countries
- cities
- common lookup records

Legacy sources:

- `jx_languages`
- `jx_countries`
- `jx_cities`

Future tables:

- `languages`
- `language_translations`
- `countries`
- `country_translations`
- `cities`
- `city_translations`

### 3. Settings and Site Configuration

Responsibilities:

- site identity
- contact data
- social links
- feature flags
- microsite and legacy setting consolidation

Legacy sources:

- `jx_config`
- `jx_config1`

Future tables:

- `settings`
- `setting_groups`
- `setting_revisions`

### 4. Navigation and Information Architecture

Responsibilities:

- main menus
- footer menus
- resource link groups
- hierarchical navigation nodes

Legacy sources:

- `jx_docs`
- `jx_categories`
- `jx_sites`

Future tables:

- `menus`
- `menu_items`
- `menu_item_translations`
- `link_groups`
- `link_items`
- `link_item_translations`

### 5. Pages and CMS Content

Responsibilities:

- homepage
- static pages
- landing pages
- section-based layouts
- SEO metadata

Legacy sources:

- `jx_site_static_pages`
- `jx_home_photos`
- part of `jx_categories`
- part of `jx_items`

Future tables:

- `pages`
- `page_translations`
- `page_seo_meta`
- `page_sections`
- `page_section_translations`
- `homepage_sections`
- `homepage_section_translations`

### 6. News and Announcements

Responsibilities:

- university news
- announcements
- public updates
- press items

Legacy sources:

- part of `jx_items`
- part of `jx_categories`
- `jx_items_comments` optionally

Future tables:

- `news_articles`
- `news_article_translations`
- `news_categories`
- `news_category_translations`

### 7. Events and Activities

Responsibilities:

- university events
- student activities
- schedules
- campaigns and activities

Legacy sources:

- part of `jx_items`
- part of `jx_categories`

Future tables:

- `events`
- `event_translations`
- `event_categories`
- `event_category_translations`

### 8. Research and Publications

Responsibilities:

- research publications
- scientific output
- research categories
- author and faculty linkage

Legacy sources:

- `jx_member_categories`
- `jx_member_items`
- part of `jx_items`
- part of `jx_categories`

Future tables:

- `research_publications`
- `research_publication_translations`
- `research_categories`
- `research_category_translations`
- `research_files`
- `research_authors`
- `research_author_publication`

### 9. Faculties and Academic Units

Responsibilities:

- faculties
- departments
- faculty contact pages
- directory entries
- academic structure

Legacy sources:

- `jx_members`
- `jx_councils`
- part of `jx_categories`
- part of `jx_site_static_pages`

Future tables:

- `faculties`
- `faculty_translations`
- `departments`
- `department_translations`
- `faculty_members`
- `faculty_member_translations`

### 10. Governance and Councils

Responsibilities:

- board of trustees
- university council
- faculty councils
- council leadership and members

Legacy sources:

- `jx_councils`
- `jx_councils1`

Future tables:

- `councils`
- `council_translations`
- `council_members`
- `council_member_translations`
- `council_memberships`

### 11. Students and Alumni

Responsibilities:

- honor students
- alumni records
- student guide references
- student informational content

Legacy sources:

- `jx_good_students`
- `jx_graduated_students`
- part of `jx_items`
- part of `jx_categories`

Future tables:

- `honor_students`
- `honor_student_translations`
- `alumni`
- `alumni_profiles`
- `student_resources`
- `student_resource_translations`

### 12. FAQ and Support

Responsibilities:

- frequently asked questions
- help content
- complaints and suggestion inbox
- support routing

Legacy sources:

- `jx_faqs`
- `jx_complaints`
- `jx_complaint_cats`

Future tables:

- `faq_categories`
- `faq_category_translations`
- `faqs`
- `faq_translations`
- `complaint_categories`
- `complaint_category_translations`
- `complaints`

### 13. Careers and Opportunities

Responsibilities:

- internal openings
- external career resources
- employer links

Legacy sources:

- `jx_job_sites`
- part of `jx_items`

Future tables:

- `career_links`
- `career_link_translations`
- `job_postings`
- `job_posting_translations`
- `job_applications`

### 14. Media and Documents

Responsibilities:

- images
- PDFs and files
- logos
- attached media usage tracking

Legacy sources:

- `jx_docs`
- `jx_logos`
- `jx_home_photos`
- file fields inside `jx_items`, `jx_member_items`, `jx_councils`, `jx_councils1`

Future tables:

- `media`
- `media_folders`
- `media_usages`
- `documents`
- `document_translations`

### 15. Comments and Engagement

Responsibilities:

- moderated public comments
- future engagement workflows

Legacy sources:

- `jx_items_comments`

Future tables:

- `comments`
- `comment_reports`

### 16. Archive, Audit, and Staging

Responsibilities:

- legacy import logs
- rejected records
- quarantine buckets
- change history

Legacy sources:

- `jx_archive`
- `dent_conf_temp`

Future tables:

- `migration_logs`
- `migration_rejections`
- `legacy_record_snapshots`
- `content_archives`

## Critical Legacy Findings That Shape The Future Design

1. Legacy generic tables must be decomposed.
Line of thinking:

- `jx_items` is not a clean entity
- `jx_categories` is not just taxonomy
- `jx_docs` is not just documents

2. Some legacy names are misleading.

- `jx_member_categories` behaves more like member-related publication/content records than simple categories
- `jx_member_items` behaves more like child attachments or publication file records
- `jx_job_sites` is a career links dataset, not a full application system

3. A staging layer is mandatory.

- the raw dump should never be imported directly into production tables
- content, media, dates, and links need cleanup first

4. Future full-site implementation should use AR/EN only unless a future business decision expands locales.

## Recommended Implementation Phases

### Phase 0. Discovery and Staging

- import raw dump into isolated legacy database
- clean and classify records
- create mapping sheets
- define service-type interpretation for `jx_items` and related tables

### Phase 1. Foundation

- identity and access
- languages, countries, cities
- settings
- media library
- menus and links

### Phase 2. CMS Core

- pages
- homepage
- SEO metadata
- publish workflow

### Phase 3. Public Content Streams

- news
- events
- announcements
- student resources

### Phase 4. Academic and Governance Modules

- faculties
- departments
- faculty directory
- councils and governance

### Phase 5. Research and Publications

- research categories
- publications
- authors and files

### Phase 6. Student and Community Modules

- alumni
- honor students
- FAQs
- complaints
- careers

### Phase 7. Engagement and Hardening

- comments
- moderation
- archive conversion
- cleanup of legacy-only modules

## Cross-Module Dependencies

- `languages` must exist before translation tables
- `media` should exist before rich content import
- `users` and `roles` should exist before admin import
- `faculties` should exist before faculty members and student records tied to faculties
- `pages` and `menus` should exist before navigation publishing
- `research_authors` should exist before publication-author links

## Migration Non-Negotiables

1. Keep the raw SQL dump immutable.
2. Perform cleaning in staging.
3. Track every transformed and rejected row.
4. Do not preserve insecure authentication artifacts.
5. Do not carry legacy five-language column design into the future schema.
6. Do not recreate generic catch-all tables if a clearer module exists.

## Professional Cutover Strategy

1. Build target schema first.
2. Import and validate in staging.
3. Produce reconciliation reports by module.
4. Run editorial review on high-risk public content.
5. Freeze legacy writes during final cutover.
6. Re-run delta import if required.
7. Publish per module, not as one blind global switch.

## Final Recommendation

For a full SPU website migration, the correct product shape is a modular platform with explicit domains, not a one-pass SQL transplant.

Use this blueprint as the design baseline before writing any full-site migration code.
