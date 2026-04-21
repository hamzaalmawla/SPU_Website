# 🔍 SQL Verification Queries

## Essential queries to verify your migration was successful

---

## 1. Migration Summary

### Overall Status
```sql
-- Summary by status
SELECT 
    status,
    COUNT(*) as count,
    ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM migration_logs), 2) as percentage
FROM migration_logs
GROUP BY status
ORDER BY count DESC;
```

**Expected Result:**
```
+----------+-------+------------+
| status   | count | percentage |
+----------+-------+------------+
| success  | 25000 |      85.00 |
| skipped  |  4000 |      13.60 |
| failed   |   400 |       1.40 |
+----------+-------+------------+
```

### By Batch
```sql
-- Summary by batch
SELECT 
    batch_name,
    status,
    COUNT(*) as count
FROM migration_logs
GROUP BY batch_name, status
ORDER BY batch_name, status;
```

### Recent Activity
```sql
-- Last 20 migrations
SELECT 
    batch_name,
    source_table,
    target_table,
    status,
    message,
    migrated_at
FROM migration_logs
ORDER BY migrated_at DESC
LIMIT 20;
```

---

## 2. Data Count Verification

### Compare Old vs New
```sql
-- Alumni
SELECT 'Old Alumni' as source, COUNT(*) as count 
FROM spuedu_old.jx_graduated_students
UNION ALL
SELECT 'New Alumni', COUNT(*) 
FROM spuedu_new.alumni;

-- Honor Students
SELECT 'Old Honor Students' as source, COUNT(*) as count 
FROM spuedu_old.jx_good_students
UNION ALL
SELECT 'New Honor Students', COUNT(*) 
FROM spuedu_new.honor_students;

-- Faculty Members
SELECT 'Old Members' as source, COUNT(*) as count 
FROM spuedu_old.jx_members
UNION ALL
SELECT 'New Faculty', COUNT(*) 
FROM spuedu_new.faculty_members;

-- FAQs
SELECT 'Old FAQs' as source, COUNT(*) as count 
FROM spuedu_old.jx_faqs
UNION ALL
SELECT 'New FAQs', COUNT(*) 
FROM spuedu_new.faqs;
```

### All Tables Count
```sql
-- New database table counts
SELECT 'users' as table_name, COUNT(*) as count FROM users
UNION ALL SELECT 'alumni', COUNT(*) FROM alumni
UNION ALL SELECT 'honor_students', COUNT(*) FROM honor_students
UNION ALL SELECT 'faculty_members', COUNT(*) FROM faculty_members
UNION ALL SELECT 'faculty_publications', COUNT(*) FROM faculty_publications
UNION ALL SELECT 'faculty_categories', COUNT(*) FROM faculty_categories
UNION ALL SELECT 'councils', COUNT(*) FROM councils
UNION ALL SELECT 'council_members', COUNT(*) FROM council_members
UNION ALL SELECT 'faqs', COUNT(*) FROM faqs
UNION ALL SELECT 'faq_translations', COUNT(*) FROM faq_translations
UNION ALL SELECT 'complaints', COUNT(*) FROM complaints
UNION ALL SELECT 'job_postings', COUNT(*) FROM job_postings
UNION ALL SELECT 'comments', COUNT(*) FROM comments
UNION ALL SELECT 'settings', COUNT(*) FROM settings
UNION ALL SELECT 'media', COUNT(*) FROM media
ORDER BY count DESC;
```

---

## 3. Translation Verification

### Check Translation Coverage
```sql
-- FAQs with translations
SELECT 
    'Total FAQs' as metric,
    COUNT(*) as count
FROM faqs
UNION ALL
SELECT 
    'FAQs with Arabic',
    COUNT(DISTINCT faq_id)
FROM faq_translations
WHERE locale = 'ar'
UNION ALL
SELECT 
    'FAQs with English',
    COUNT(DISTINCT faq_id)
FROM faq_translations
WHERE locale = 'en'
UNION ALL
SELECT 
    'FAQs with both languages',
    COUNT(*)
FROM (
    SELECT faq_id
    FROM faq_translations
    GROUP BY faq_id
    HAVING COUNT(DISTINCT locale) = 2
) as both;
```

### Find Missing Translations
```sql
-- FAQs without Arabic translation
SELECT f.id, f.created_at
FROM faqs f
LEFT JOIN faq_translations ft ON f.id = ft.faq_id AND ft.locale = 'ar'
WHERE ft.id IS NULL;

-- FAQs without English translation
SELECT f.id, f.created_at
FROM faqs f
LEFT JOIN faq_translations ft ON f.id = ft.faq_id AND ft.locale = 'en'
WHERE ft.id IS NULL;
```

### Translation Distribution
```sql
-- Count by locale
SELECT 
    locale,
    COUNT(*) as count
FROM faq_translations
GROUP BY locale;
```

---

## 4. Data Quality Checks

### Find Orphaned Records
```sql
-- Comments without parent
SELECT c.id, c.commentable_type, c.commentable_id
FROM comments c
LEFT JOIN content_items ci ON c.commentable_id = ci.id AND c.commentable_type = 'content_item'
WHERE c.commentable_type = 'content_item' AND ci.id IS NULL;

-- Faculty publications without member
SELECT fp.id, fp.faculty_member_id
FROM faculty_publications fp
LEFT JOIN faculty_members fm ON fp.faculty_member_id = fm.id
WHERE fm.id IS NULL;

-- Council members without council
SELECT cm.id, cm.council_id
FROM council_members cm
LEFT JOIN councils c ON cm.council_id = c.id
WHERE c.id IS NULL;
```

### Find Duplicate Records
```sql
-- Duplicate alumni by name and year
SELECT 
    name,
    graduation_year,
    COUNT(*) as count
FROM alumni
GROUP BY name, graduation_year
HAVING COUNT(*) > 1;

-- Duplicate faculty by email
SELECT 
    email,
    COUNT(*) as count
FROM faculty_members
WHERE email IS NOT NULL
GROUP BY email
HAVING COUNT(*) > 1;
```

### Find Invalid Data
```sql
-- Alumni with invalid graduation year
SELECT id, name, graduation_year
FROM alumni
WHERE graduation_year < 2000 OR graduation_year > 2026;

-- Faculty members without name
SELECT id, email, created_at
FROM faculty_members
WHERE name IS NULL OR name = '';

-- FAQs without translations
SELECT f.id, f.created_at
FROM faqs f
LEFT JOIN faq_translations ft ON f.id = ft.faq_id
WHERE ft.id IS NULL;
```

---

## 5. Migration Failures Analysis

### Failed Migrations by Table
```sql
-- Count failures by source table
SELECT 
    source_table,
    COUNT(*) as failed_count,
    GROUP_CONCAT(DISTINCT message SEPARATOR '; ') as error_messages
FROM migration_logs
WHERE status = 'failed'
GROUP BY source_table
ORDER BY failed_count DESC;
```

### Failed Migrations by Error Type
```sql
-- Group by error message
SELECT 
    message,
    COUNT(*) as count,
    GROUP_CONCAT(DISTINCT source_table) as affected_tables
FROM migration_logs
WHERE status = 'failed'
GROUP BY message
ORDER BY count DESC
LIMIT 20;
```

### Specific Failed Records
```sql
-- Get details of failed migrations
SELECT 
    source_table,
    source_id,
    target_table,
    message,
    migrated_at
FROM migration_logs
WHERE status = 'failed'
ORDER BY migrated_at DESC
LIMIT 50;
```

---

## 6. Skipped Records Analysis

### Skipped by Table
```sql
-- Count skipped by source table
SELECT 
    source_table,
    COUNT(*) as skipped_count,
    GROUP_CONCAT(DISTINCT message SEPARATOR '; ') as skip_reasons
FROM migration_logs
WHERE status = 'skipped'
GROUP BY source_table
ORDER BY skipped_count DESC;
```

### Skipped by Reason
```sql
-- Group by skip reason
SELECT 
    message as skip_reason,
    COUNT(*) as count
FROM migration_logs
WHERE status = 'skipped'
GROUP BY message
ORDER BY count DESC;
```

---

## 7. Foreign Key Integrity

### Check All Foreign Keys
```sql
-- List all foreign key constraints
SELECT 
    TABLE_NAME,
    COLUMN_NAME,
    CONSTRAINT_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = 'spuedu_new'
AND REFERENCED_TABLE_NAME IS NOT NULL
ORDER BY TABLE_NAME, COLUMN_NAME;
```

### Verify Foreign Key Integrity
```sql
-- Check if all foreign keys are valid
-- (This should return 0 rows if all FKs are valid)

-- Faculty publications -> faculty members
SELECT fp.id, fp.faculty_member_id
FROM faculty_publications fp
LEFT JOIN faculty_members fm ON fp.faculty_member_id = fm.id
WHERE fm.id IS NULL;

-- Council members -> councils
SELECT cm.id, cm.council_id
FROM council_members cm
LEFT JOIN councils c ON cm.council_id = c.id
WHERE c.id IS NULL;

-- Job applications -> job postings
SELECT ja.id, ja.job_posting_id
FROM job_applications ja
LEFT JOIN job_postings jp ON ja.job_posting_id = jp.id
WHERE jp.id IS NULL;
```

---

## 8. Date Range Verification

### Check Date Ranges
```sql
-- Alumni graduation years
SELECT 
    MIN(graduation_year) as earliest,
    MAX(graduation_year) as latest,
    COUNT(*) as total
FROM alumni;

-- Honor students by year
SELECT 
    academic_year,
    COUNT(*) as count
FROM honor_students
GROUP BY academic_year
ORDER BY academic_year DESC;

-- Content creation dates
SELECT 
    DATE_FORMAT(created_at, '%Y-%m') as month,
    COUNT(*) as count
FROM content_items
GROUP BY DATE_FORMAT(created_at, '%Y-%m')
ORDER BY month DESC
LIMIT 12;
```

---

## 9. User and Security Verification

### Check Admin Users
```sql
-- All admin users
SELECT 
    id,
    name,
    email,
    role,
    is_locked,
    created_at
FROM users
ORDER BY created_at;

-- Locked accounts (should be all)
SELECT 
    COUNT(*) as total_users,
    SUM(CASE WHEN is_locked = 1 THEN 1 ELSE 0 END) as locked_users,
    SUM(CASE WHEN is_locked = 0 THEN 1 ELSE 0 END) as unlocked_users
FROM users;
```

### Password Security Check
```sql
-- Verify no MD5 passwords migrated
-- (All passwords should be bcrypt hashes starting with $2y$)
SELECT 
    id,
    email,
    SUBSTRING(password, 1, 4) as hash_prefix,
    LENGTH(password) as hash_length
FROM users;

-- Expected: hash_prefix = '$2y$', hash_length = 60
```

---

## 10. Settings Verification

### Check Settings
```sql
-- All settings
SELECT 
    setting_key,
    setting_value,
    setting_type,
    is_public
FROM settings
ORDER BY setting_key;

-- Count by type
SELECT 
    setting_type,
    COUNT(*) as count
FROM settings
GROUP BY setting_type;
```

### Check for Duplicate Settings
```sql
-- Should return 0 rows
SELECT 
    setting_key,
    COUNT(*) as count
FROM settings
GROUP BY setting_key
HAVING COUNT(*) > 1;
```

---

## 11. Media Files Verification

### Media Statistics
```sql
-- Media by type
SELECT 
    media_type,
    COUNT(*) as count,
    SUM(file_size) as total_size_bytes,
    ROUND(SUM(file_size) / 1024 / 1024, 2) as total_size_mb
FROM media
GROUP BY media_type;

-- Media by locale
SELECT 
    locale,
    COUNT(*) as count
FROM media
GROUP BY locale;
```

### Find Missing Files
```sql
-- Media records without file path
SELECT id, original_filename, created_at
FROM media
WHERE file_path IS NULL OR file_path = '';
```

---

## 12. Performance Checks

### Table Sizes
```sql
-- Size of each table
SELECT 
    TABLE_NAME,
    TABLE_ROWS,
    ROUND(((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024), 2) as size_mb
FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_SCHEMA = 'spuedu_new'
ORDER BY (DATA_LENGTH + INDEX_LENGTH) DESC;
```

### Index Usage
```sql
-- List all indexes
SELECT 
    TABLE_NAME,
    INDEX_NAME,
    COLUMN_NAME,
    NON_UNIQUE
FROM INFORMATION_SCHEMA.STATISTICS
WHERE TABLE_SCHEMA = 'spuedu_new'
ORDER BY TABLE_NAME, INDEX_NAME;
```

---

## 13. Final Validation Checklist

Run this comprehensive check:

```sql
-- Comprehensive validation report
SELECT 'Migration Logs' as check_name, 
       CONCAT(COUNT(*), ' total records') as result
FROM migration_logs
UNION ALL
SELECT 'Successful Migrations', 
       CONCAT(COUNT(*), ' (', ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM migration_logs), 1), '%)') 
FROM migration_logs WHERE status = 'success'
UNION ALL
SELECT 'Failed Migrations', 
       CONCAT(COUNT(*), ' (', ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM migration_logs), 1), '%)') 
FROM migration_logs WHERE status = 'failed'
UNION ALL
SELECT 'Skipped Records', 
       CONCAT(COUNT(*), ' (', ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM migration_logs), 1), '%)') 
FROM migration_logs WHERE status = 'skipped'
UNION ALL
SELECT 'Total Users', CONCAT(COUNT(*), ' users') FROM users
UNION ALL
SELECT 'Locked Users', CONCAT(COUNT(*), ' users') FROM users WHERE is_locked = 1
UNION ALL
SELECT 'Alumni Records', CONCAT(COUNT(*), ' records') FROM alumni
UNION ALL
SELECT 'Honor Students', CONCAT(COUNT(*), ' records') FROM honor_students
UNION ALL
SELECT 'Faculty Members', CONCAT(COUNT(*), ' records') FROM faculty_members
UNION ALL
SELECT 'Faculty Publications', CONCAT(COUNT(*), ' records') FROM faculty_publications
UNION ALL
SELECT 'FAQs', CONCAT(COUNT(*), ' records') FROM faqs
UNION ALL
SELECT 'FAQ Translations', CONCAT(COUNT(*), ' records') FROM faq_translations
UNION ALL
SELECT 'Arabic Translations', CONCAT(COUNT(*), ' records') FROM faq_translations WHERE locale = 'ar'
UNION ALL
SELECT 'English Translations', CONCAT(COUNT(*), ' records') FROM faq_translations WHERE locale = 'en'
UNION ALL
SELECT 'Settings', CONCAT(COUNT(*), ' settings') FROM settings
UNION ALL
SELECT 'Media Files', CONCAT(COUNT(*), ' files') FROM media;
```

---

## 14. Export Results

### Export to CSV
```bash
# Export migration summary
mysql -u root -p spuedu_new -e "
SELECT status, COUNT(*) as count 
FROM migration_logs 
GROUP BY status
" > migration_summary.csv

# Export failed migrations
mysql -u root -p spuedu_new -e "
SELECT source_table, source_id, message 
FROM migration_logs 
WHERE status = 'failed'
" > failed_migrations.csv

# Export data counts
mysql -u root -p spuedu_new -e "
SELECT 'alumni' as table_name, COUNT(*) as count FROM alumni
UNION ALL SELECT 'faculty_members', COUNT(*) FROM faculty_members
UNION ALL SELECT 'faqs', COUNT(*) FROM faqs
" > table_counts.csv
```

---

## 15. Success Criteria

Your migration is successful if:

✅ **Success Rate > 90%**
```sql
SELECT 
    ROUND(
        (SELECT COUNT(*) FROM migration_logs WHERE status = 'success') * 100.0 / 
        (SELECT COUNT(*) FROM migration_logs),
        2
    ) as success_rate;
-- Should be > 90.00
```

✅ **All Critical Tables Have Data**
```sql
SELECT 
    (SELECT COUNT(*) FROM users) > 0 as has_users,
    (SELECT COUNT(*) FROM alumni) > 5000 as has_alumni,
    (SELECT COUNT(*) FROM faculty_members) > 0 as has_faculty,
    (SELECT COUNT(*) FROM faqs) > 1000 as has_faqs;
-- All should be 1 (true)
```

✅ **No Orphaned Records**
```sql
-- Should return 0
SELECT COUNT(*) as orphaned_publications
FROM faculty_publications fp
LEFT JOIN faculty_members fm ON fp.faculty_member_id = fm.id
WHERE fm.id IS NULL;
```

✅ **Translations Working**
```sql
-- Should be close to 1:2 ratio (1 FAQ = 2 translations)
SELECT 
    (SELECT COUNT(*) FROM faqs) as total_faqs,
    (SELECT COUNT(*) FROM faq_translations) as total_translations,
    ROUND((SELECT COUNT(*) FROM faq_translations) / (SELECT COUNT(*) FROM faqs), 2) as ratio;
-- Ratio should be close to 2.00
```

✅ **All Users Locked**
```sql
-- Should return 0
SELECT COUNT(*) as unlocked_users
FROM users
WHERE is_locked = 0;
```

---

## Quick Verification Script

Save this as `verify_migration.sql` and run it:

```sql
-- Quick Migration Verification Script
-- Run: mysql -u root -p spuedu_new < verify_migration.sql

USE spuedu_new;

SELECT '=== MIGRATION SUMMARY ===' as '';
SELECT status, COUNT(*) as count, 
       CONCAT(ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM migration_logs), 1), '%') as percentage
FROM migration_logs GROUP BY status;

SELECT '=== DATA COUNTS ===' as '';
SELECT 'users' as table_name, COUNT(*) as count FROM users
UNION ALL SELECT 'alumni', COUNT(*) FROM alumni
UNION ALL SELECT 'honor_students', COUNT(*) FROM honor_students
UNION ALL SELECT 'faculty_members', COUNT(*) FROM faculty_members
UNION ALL SELECT 'faqs', COUNT(*) FROM faqs
UNION ALL SELECT 'settings', COUNT(*) FROM settings;

SELECT '=== TRANSLATION CHECK ===' as '';
SELECT locale, COUNT(*) as count FROM faq_translations GROUP BY locale;

SELECT '=== SECURITY CHECK ===' as '';
SELECT 
    COUNT(*) as total_users,
    SUM(is_locked) as locked_users,
    COUNT(*) - SUM(is_locked) as unlocked_users
FROM users;

SELECT '=== TOP FAILURES ===' as '';
SELECT message, COUNT(*) as count 
FROM migration_logs 
WHERE status = 'failed' 
GROUP BY message 
ORDER BY count DESC 
LIMIT 5;

SELECT '=== VERIFICATION COMPLETE ===' as '';
```

Run it:
```bash
mysql -u root -p spuedu_new < verify_migration.sql
```

---

**Use these queries to verify your migration was successful!** ✅
