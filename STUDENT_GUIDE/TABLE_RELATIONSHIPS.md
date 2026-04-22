# 🗂️ Database Table Relationships

## Visual Guide to New Database Structure

---

## Overview

The new database has **34 tables** organized into **8 logical groups**:

1. **Foundation** (3 tables) - Core system data
2. **Configuration** (2 tables) - Site settings
3. **Content** (6 tables) - Pages, news, media
4. **University** (8 tables) - Faculty and councils
5. **Students** (4 tables) - Alumni and achievements
6. **Support** (8 tables) - FAQs, complaints, jobs
7. **Engagement** (2 tables) - Comments
8. **Audit** (1 table) - Migration logs

---

## 1. Foundation Tables

### Users (Admin System)
```
┌─────────────────┐
│     users       │
├─────────────────┤
│ id              │ PK
│ name            │
│ email           │ UNIQUE
│ password        │ (bcrypt)
│ role            │ (super_admin, editor, faculty_editor)
│ is_locked       │ (force password reset)
│ failed_attempts │
│ locked_at       │
│ timestamps      │
│ soft_deletes    │
└─────────────────┘
```

### Languages
```
┌─────────────────┐
│   languages     │
├─────────────────┤
│ id              │ PK
│ code            │ UNIQUE ('ar', 'en')
│ name            │
│ is_default      │
│ is_active       │
│ timestamps      │
└─────────────────┘
```

### Countries & Cities
```
┌─────────────────┐         ┌─────────────────┐
│   countries     │         │     cities      │
├─────────────────┤         ├─────────────────┤
│ id              │ PK ─┐   │ id              │ PK
│ name_ar         │     └──→│ country_id      │ FK
│ name_en         │         │ name_ar         │
│ code            │         │ name_en         │
│ timestamps      │         │ timestamps      │
└─────────────────┘         └─────────────────┘
```

---

## 2. Configuration Tables

### Settings (Site Configuration)
```
┌─────────────────────────┐
│       settings          │
├─────────────────────────┤
│ id                      │ PK
│ setting_key             │ UNIQUE
│ setting_value           │ (JSON or text)
│ setting_type            │ (string, json, boolean, etc.)
│ is_public               │ (visible to frontend)
│ description             │
│ timestamps              │
└─────────────────────────┘

Examples:
- site_name_ar
- site_name_en
- contact_email
- social_media_links (JSON)
- emergency_notice (JSON)
```

### Sites (Multi-site Support)
```
┌─────────────────────────┐
│         sites           │
├─────────────────────────┤
│ id                      │ PK
│ name_ar                 │
│ name_en                 │
│ domain                  │
│ is_active               │
│ timestamps              │
│ soft_deletes            │
└─────────────────────────┘

Examples:
- Main SPU site
- Faculty of Medicine
- Faculty of Dentistry
- Research Portal
```

---

## 3. Content Tables

### Content Items (News, Events, Pages)
```
┌─────────────────────────┐         ┌──────────────────────────────┐
│    content_items        │         │  content_item_translations   │
├─────────────────────────┤         ├──────────────────────────────┤
│ id                      │ PK ─┐   │ id                           │ PK
│ content_type            │     └──→│ content_item_id              │ FK
│ category_id             │ FK      │ locale                       │ ('ar', 'en')
│ author_id               │ FK      │ title                        │
│ featured_image_id       │ FK      │ content                      │
│ is_published            │         │ excerpt                      │
│ published_at            │         │ meta_description             │
│ view_count              │         │ timestamps                   │
│ timestamps              │         └──────────────────────────────┘
│ soft_deletes            │         UNIQUE(content_item_id, locale)
└─────────────────────────┘
         │
         │ FK
         ↓
┌─────────────────────────┐
│      categories         │
├─────────────────────────┤
│ id                      │ PK
│ parent_id               │ FK (self)
│ category_type           │ (news, events, pages)
│ slug                    │ UNIQUE
│ timestamps              │
│ soft_deletes            │
└─────────────────────────┘
         │
         │
         ↓
┌──────────────────────────────┐
│   category_translations      │
├──────────────────────────────┤
│ id                           │ PK
│ category_id                  │ FK
│ locale                       │
│ name                         │
│ description                  │
│ timestamps                   │
└──────────────────────────────┘
UNIQUE(category_id, locale)
```

### Media (Files, Images, Documents)
```
┌─────────────────────────┐
│        media            │
├─────────────────────────┤
│ id                      │ PK
│ original_filename       │
│ file_path               │
│ file_size               │
│ mime_type               │
│ media_type              │ (image, document, video)
│ locale                  │ ('ar', 'en', null)
│ alt_text                │
│ uploaded_by             │ FK → users
│ timestamps              │
│ soft_deletes            │
└─────────────────────────┘
```

### Pages (Static Pages)
```
┌─────────────────────────┐         ┌──────────────────────────────┐
│        pages            │         │     page_translations        │
├─────────────────────────┤         ├──────────────────────────────┤
│ id                      │ PK ─┐   │ id                           │ PK
│ slug                    │     └──→│ page_id                      │ FK
│ template                │         │ locale                       │
│ is_published            │         │ title                        │
│ published_at            │         │ content                      │
│ timestamps              │         │ meta_description             │
│ soft_deletes            │         │ timestamps                   │
└─────────────────────────┘         └──────────────────────────────┘
                                    UNIQUE(page_id, locale)
```

---

## 4. University Tables

### Faculty System
```
┌──────────────────────────┐
│   faculty_categories     │  (Departments/Faculties)
├──────────────────────────┤
│ id                       │ PK
│ parent_id                │ FK (self)
│ slug                     │ UNIQUE
│ display_order            │
│ timestamps               │
│ soft_deletes             │
└──────────────────────────┘
         │
         │
         ↓
┌──────────────────────────────────┐
│ faculty_category_translations    │
├──────────────────────────────────┤
│ id                               │ PK
│ faculty_category_id              │ FK
│ locale                           │
│ name                             │
│ description                      │
│ timestamps                       │
└──────────────────────────────────┘
UNIQUE(faculty_category_id, locale)
         │
         │
         ↓
┌──────────────────────────┐         ┌──────────────────────────────┐
│   faculty_members        │         │  faculty_member_translations │
├──────────────────────────┤         ├──────────────────────────────┤
│ id                       │ PK ─┐   │ id                           │ PK
│ faculty_category_id      │ FK  └──→│ faculty_member_id            │ FK
│ email                    │         │ locale                       │
│ phone                    │         │ name                         │
│ photo_id                 │ FK      │ title                        │
│ display_order            │         │ bio                          │
│ is_active                │         │ specialization               │
│ timestamps               │         │ timestamps                   │
│ soft_deletes             │         └──────────────────────────────┘
└──────────────────────────┘         UNIQUE(faculty_member_id, locale)
         │
         │
         ↓
┌──────────────────────────┐         ┌────────────────────────────────────┐
│ faculty_publications     │         │ faculty_publication_translations   │
├──────────────────────────┤         ├────────────────────────────────────┤
│ id                       │ PK ─┐   │ id                                 │ PK
│ faculty_member_id        │ FK  └──→│ faculty_publication_id             │ FK
│ publication_type         │         │ locale                             │
│ publication_date         │         │ title                              │
│ timestamps               │         │ abstract                           │
│ soft_deletes             │         │ authors                            │
└──────────────────────────┘         │ journal_name                       │
                                     │ timestamps                         │
                                     └────────────────────────────────────┘
                                     UNIQUE(faculty_publication_id, locale)
```

### Council System
```
┌──────────────────────────┐         ┌──────────────────────────────┐
│       councils           │         │    council_translations      │
├──────────────────────────┤         ├──────────────────────────────┤
│ id                       │ PK ─┐   │ id                           │ PK
│ council_type             │     └──→│ council_id                   │ FK
│ is_active                │         │ locale                       │
│ timestamps               │         │ name                         │
│ soft_deletes             │         │ description                  │
└──────────────────────────┘         │ timestamps                   │
         │                           └──────────────────────────────┘
         │                           UNIQUE(council_id, locale)
         ↓
┌──────────────────────────┐         ┌──────────────────────────────────┐
│   council_members        │         │  council_member_translations     │
├──────────────────────────┤         ├──────────────────────────────────┤
│ id                       │ PK ─┐   │ id                               │ PK
│ council_id               │ FK  └──→│ council_member_id                │ FK
│ email                    │         │ locale                           │
│ phone                    │         │ name                             │
│ photo_id                 │ FK      │ position                         │
│ display_order            │         │ bio                              │
│ timestamps               │         │ timestamps                       │
│ soft_deletes             │         └──────────────────────────────────┘
└──────────────────────────┘         UNIQUE(council_member_id, locale)
```

---

## 5. Student Tables

### Alumni (Graduated Students)
```
┌──────────────────────────┐
│        alumni            │
├──────────────────────────┤
│ id                       │ PK
│ name                     │
│ faculty                  │
│ specialization           │
│ graduation_year          │ (2006-2024)
│ graduation_rank          │
│ gpa                      │
│ timestamps               │
│ soft_deletes             │
└──────────────────────────┘
INDEX(graduation_year)
INDEX(faculty)
```

### Honor Students
```
┌──────────────────────────┐
│    honor_students        │
├──────────────────────────┤
│ id                       │ PK
│ name                     │
│ faculty                  │
│ academic_year            │
│ gpa                      │
│ rank                     │
│ timestamps               │
│ soft_deletes             │
└──────────────────────────┘
INDEX(academic_year)
INDEX(faculty)
```

### Student Achievements
```
┌──────────────────────────┐         ┌────────────────────────────────────┐
│  student_achievements    │         │ student_achievement_translations   │
├──────────────────────────┤         ├────────────────────────────────────┤
│ id                       │ PK ─┐   │ id                                 │ PK
│ student_name             │     └──→│ student_achievement_id             │ FK
│ achievement_type         │         │ locale                             │
│ achievement_date         │         │ title                              │
│ timestamps               │         │ description                        │
│ soft_deletes             │         │ timestamps                         │
└──────────────────────────┘         └────────────────────────────────────┘
                                     UNIQUE(student_achievement_id, locale)
```

---

## 6. Support Tables

### FAQ System
```
┌──────────────────────────┐
│    faq_categories        │
├──────────────────────────┤
│ id                       │ PK
│ slug                     │ UNIQUE
│ display_order            │
│ timestamps               │
│ soft_deletes             │
└──────────────────────────┘
         │
         │
         ↓
┌──────────────────────────────────┐
│   faq_category_translations      │
├──────────────────────────────────┤
│ id                               │ PK
│ faq_category_id                  │ FK
│ locale                           │
│ name                             │
│ timestamps                       │
└──────────────────────────────────┘
UNIQUE(faq_category_id, locale)
         │
         │
         ↓
┌──────────────────────────┐         ┌──────────────────────────────┐
│         faqs             │         │     faq_translations         │
├──────────────────────────┤         ├──────────────────────────────┤
│ id                       │ PK ─┐   │ id                           │ PK
│ faq_category_id          │ FK  └──→│ faq_id                       │ FK
│ display_order            │         │ locale                       │
│ is_published             │         │ question                     │
│ view_count               │         │ answer                       │
│ timestamps               │         │ timestamps                   │
│ soft_deletes             │         └──────────────────────────────┘
└──────────────────────────┘         UNIQUE(faq_id, locale)
```

### Complaint System
```
┌──────────────────────────┐         ┌────────────────────────────────────┐
│  complaint_categories    │         │  complaint_category_translations   │
├──────────────────────────┤         ├────────────────────────────────────┤
│ id                       │ PK ─┐   │ id                                 │ PK
│ slug                     │     └──→│ complaint_category_id              │ FK
│ timestamps               │         │ locale                             │
│ soft_deletes             │         │ name                               │
└──────────────────────────┘         │ timestamps                         │
         │                           └────────────────────────────────────┘
         │                           UNIQUE(complaint_category_id, locale)
         ↓
┌──────────────────────────┐
│      complaints          │
├──────────────────────────┤
│ id                       │ PK
│ complaint_category_id    │ FK
│ submitter_name           │
│ submitter_email          │
│ submitter_phone          │
│ subject                  │
│ description              │
│ status                   │ (pending, in_progress, resolved, closed)
│ priority                 │ (low, medium, high)
│ assigned_to              │ FK → users
│ resolved_at              │
│ timestamps               │
│ soft_deletes             │
└──────────────────────────┘
INDEX(status)
INDEX(assigned_to)
         │
         │
         ↓
┌──────────────────────────┐
│  complaint_responses     │
├──────────────────────────┤
│ id                       │ PK
│ complaint_id             │ FK
│ responder_id             │ FK → users
│ response_text            │
│ is_internal              │ (visible to staff only)
│ timestamps               │
└──────────────────────────┘
```

### Job System
```
┌──────────────────────────┐         ┌──────────────────────────────┐
│    job_categories        │         │  job_category_translations   │
├──────────────────────────┤         ├──────────────────────────────┤
│ id                       │ PK ─┐   │ id                           │ PK
│ slug                     │     └──→│ job_category_id              │ FK
│ timestamps               │         │ locale                       │
│ soft_deletes             │         │ name                         │
└──────────────────────────┘         │ timestamps                   │
         │                           └──────────────────────────────┘
         │                           UNIQUE(job_category_id, locale)
         ↓
┌──────────────────────────┐         ┌──────────────────────────────┐
│     job_postings         │         │   job_posting_translations   │
├──────────────────────────┤         ├──────────────────────────────┤
│ id                       │ PK ─┐   │ id                           │ PK
│ job_category_id          │ FK  └──→│ job_posting_id               │ FK
│ employment_type          │         │ locale                       │
│ location                 │         │ title                        │
│ salary_range             │         │ description                  │
│ is_published             │         │ requirements                 │
│ published_at             │         │ timestamps                   │
│ expires_at               │         └──────────────────────────────┘
│ timestamps               │         UNIQUE(job_posting_id, locale)
│ soft_deletes             │
└──────────────────────────┘
INDEX(is_published, expires_at)
         │
         │
         ↓
┌──────────────────────────┐
│   job_applications       │
├──────────────────────────┤
│ id                       │ PK
│ job_posting_id           │ FK
│ applicant_name           │
│ applicant_email          │
│ applicant_phone          │
│ resume_file_id           │ FK → media
│ cover_letter             │
│ status                   │ (pending, reviewing, shortlisted, rejected, hired)
│ reviewed_by              │ FK → users
│ reviewed_at              │
│ timestamps               │
│ soft_deletes             │
└──────────────────────────┘
INDEX(status)
INDEX(job_posting_id, status)
```

---

## 7. Engagement Tables

### Comments (Polymorphic)
```
┌──────────────────────────┐
│       comments           │
├──────────────────────────┤
│ id                       │ PK
│ commentable_type         │ (content_item, faculty_publication, etc.)
│ commentable_id           │ (ID of parent record)
│ parent_id                │ FK (self, for replies)
│ author_name              │
│ author_email             │
│ content                  │
│ is_approved              │
│ approved_by              │ FK → users
│ approved_at              │
│ timestamps               │
│ soft_deletes             │
└──────────────────────────┘
INDEX(commentable_type, commentable_id)
INDEX(is_approved)

Example relationships:
- Comment on news article: commentable_type='content_item', commentable_id=123
- Comment on research: commentable_type='faculty_publication', commentable_id=456
- Reply to comment: parent_id=789
```

---

## 8. Audit Table

### Migration Logs
```
┌──────────────────────────┐
│    migration_logs        │
├──────────────────────────┤
│ id                       │ PK
│ batch_name               │ (batch_1, batch_2, etc.)
│ source_table             │ (jx_items, jx_graduated_students, etc.)
│ target_table             │ (content_items, alumni, etc.)
│ source_id                │ (ID in old database)
│ target_id                │ (ID in new database)
│ status                   │ (success, failed, skipped)
│ message                  │ (error or info message)
│ migrated_at              │
│ timestamps               │
└──────────────────────────┘
INDEX(batch_name)
INDEX(status)
INDEX(source_table, source_id)
INDEX(target_table, target_id)
```

---

## Key Relationships Summary

### One-to-Many Relationships
- `countries` → `cities`
- `categories` → `content_items`
- `users` → `content_items` (author)
- `faculty_categories` → `faculty_members`
- `faculty_members` → `faculty_publications`
- `councils` → `council_members`
- `faq_categories` → `faqs`
- `complaint_categories` → `complaints`
- `complaints` → `complaint_responses`
- `job_categories` → `job_postings`
- `job_postings` → `job_applications`

### One-to-Many (Translation Pattern)
- `content_items` → `content_item_translations`
- `categories` → `category_translations`
- `pages` → `page_translations`
- `faculty_categories` → `faculty_category_translations`
- `faculty_members` → `faculty_member_translations`
- `faculty_publications` → `faculty_publication_translations`
- `councils` → `council_translations`
- `council_members` → `council_member_translations`
- `faqs` → `faq_translations`
- `job_postings` → `job_posting_translations`

### Self-Referencing
- `categories` → `categories` (parent_id)
- `faculty_categories` → `faculty_categories` (parent_id)
- `comments` → `comments` (parent_id for replies)

### Polymorphic
- `comments` → any commentable entity (content_items, faculty_publications, etc.)

---

## Translation Pattern

All translatable content follows this pattern:

```
Main Table                Translation Table
┌─────────────┐          ┌──────────────────────┐
│ id          │ PK ─────→│ main_table_id        │ FK
│ (metadata)  │          │ locale               │ ('ar', 'en')
│ timestamps  │          │ (translatable fields)│
└─────────────┘          │ timestamps           │
                         └──────────────────────┘
                         UNIQUE(main_table_id, locale)
```

**Benefits:**
- Clean separation of metadata and content
- Easy to add new languages
- Efficient queries with joins
- Guaranteed one translation per locale

---

## Index Strategy

### Performance Indexes
- Foreign keys (automatic)
- Status fields (`is_published`, `is_active`, `status`)
- Date fields (`published_at`, `created_at`)
- Lookup fields (`slug`, `email`, `code`)
- Composite indexes for common queries

### Examples
```sql
-- Content queries
INDEX(is_published, published_at)

-- Job searches
INDEX(job_category_id, is_published, expires_at)

-- Complaint management
INDEX(status, assigned_to)

-- Alumni searches
INDEX(graduation_year, faculty)
```

---

## Data Flow Example

### Publishing a News Article

1. **Create content item**
   ```
   content_items (id=1, content_type='news', is_published=false)
   ```

2. **Add translations**
   ```
   content_item_translations (content_item_id=1, locale='ar', title='...', content='...')
   content_item_translations (content_item_id=1, locale='en', title='...', content='...')
   ```

3. **Upload featured image**
   ```
   media (id=5, media_type='image', file_path='...')
   content_items (id=1, featured_image_id=5)
   ```

4. **Publish**
   ```
   content_items (id=1, is_published=true, published_at=NOW())
   ```

5. **Users comment**
   ```
   comments (commentable_type='content_item', commentable_id=1, content='...')
   ```

---

## Migration Mapping

### Old → New Table Mapping

| Old Table | New Table(s) | Notes |
|-----------|--------------|-------|
| jx_admins | users | Force password reset |
| jx_graduated_students | alumni | 5,255 records |
| jx_good_students | honor_students | 1,070 records |
| jx_members | faculty_members + translations | Professors |
| jx_member_items | faculty_publications + translations | Research |
| jx_member_categories | faculty_categories + translations | Departments |
| jx_councils | councils + translations | University councils |
| jx_councils1 | council_members + translations | Council members |
| jx_faqs | faqs + faq_translations | Q&A system |
| jx_complaints | complaints | Ticket system |
| jx_job_sites | job_postings + translations | Career portal |
| jx_items_comments | comments | User comments |
| jx_config + jx_config1 | settings | Merged |
| jx_docs + jx_logos | media | Unified |
| jx_items | content_items + translations | By service_type |
| jx_categories | categories + translations | Taxonomy |

---

**Use this guide to understand how tables relate to each other!** 🗂️
