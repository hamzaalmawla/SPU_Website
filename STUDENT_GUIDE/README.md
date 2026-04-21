# SPU Database Migration - Student Guide

## Read This First

This package is best treated as a high-value analysis and training kit for the legacy SPU database, not as a finished one-command production migration.

The documentation below now does three different jobs:

- explain the legacy database and why it is difficult to migrate directly
- teach students how to design safe Laravel schema changes
- preserve migration code examples and patterns that are still useful for learning

## Current Status

| Area | Status | Notes |
|---|---|---|
| Legacy SQL analysis | Strong | `spuedu_db.sql` has now been audited in detail |
| Schema design examples | Useful | the migration files show modern Laravel table patterns |
| End-to-end legacy import | Not complete | the main seeder still contains many placeholder branches |
| Safe production execution | Not ready | current seeders and mappings still need real implementation work |

## Why This Package Is Not A Finished Migration Yet

There are several important gaps students must understand before they execute anything serious:

1. `database/seeders/CompleteDatabaseMigrationSeeder.php` still contains many `Schema analysis needed` placeholders.
2. `database/seeders/MigrateFacultySeeder.php` does not match the actual legacy column names in `spuedu_db.sql`.
3. `database/seeders/CompleteDatabaseMigrationSeeder.php` writes to `users.locale`, `users.is_locked`, `roles`, and `model_has_roles`, but those columns and tables are not created by the migrations currently present in this repository.
4. `config/old_database.php` maps legacy data into target tables such as `content_items`, `pages`, `settings`, `site_sections`, and `homepage_media`, but those target tables are not created by the current migration set.

Because of that, students should treat the included seeders as draft scaffolding and pattern references, not as a finished import engine.

## Current Project Scope

The repository instructions define the active scope as:

- homepage
- navigation shell
- admin panel / CMS foundation
- homepage draft / preview / publish workflow
- menu builder
- settings
- media library
- audit logging
- AR / EN support

The legacy SQL dump is much wider than that. It contains data for research, facilities, job listings, comments, multiple microsites, and five languages. Students should not assume all legacy tables belong in the current sprint.

The new full-site documents in this package are therefore planning and architecture material for a future broader implementation, not proof that the current repository should expand code scope immediately.

## Recommended Reading Order

1. Read this file completely.
2. Read `DATABASE_CHANGE_PLAYBOOK.md` before changing any table or column.
3. Read `LEGACY_SQL_AUDIT.md` before trusting values from `spuedu_db.sql`.
4. Read `FULL_SITE_MIGRATION_BLUEPRINT.md` if you need the future full-site architecture.
5. Read `FULL_SITE_LEGACY_MODULE_CATALOG.md` for table-by-table future mapping.
6. Read `FULL_SITE_DATA_CLEANING_STANDARD.md` for professional import cleanup rules.
7. Use `TABLE_RELATIONSHIPS.md` to understand modern schema patterns.
8. Use `STEP_BY_STEP.md` and `QUICK_REFERENCE.md` only as reference material after you understand the warnings above.

## Cross-Platform Note

Some older examples in this package use Bash-style commands such as `cp`, `cat`, and shell redirection.

If you are working on Windows:

- use PowerShell equivalents
- or copy files with your editor / file explorer
- do not assume every command block is directly runnable in the current environment

## Quick Summary Of The Legacy Database

The old database is valuable as input data, but weak as a design model.

Main legacy characteristics:

- 30 tables
- no DDL foreign keys
- generic tables such as `jx_items` and `jx_categories`
- wide per-language columns instead of explicit translation tables
- heavy HTML content contamination, including Word markup and inline images
- inconsistent settings sources such as `jx_config` and `jx_config1`

Read `LEGACY_SQL_AUDIT.md` for the full evidence and cleanup guidance.

## What Students Should Learn From This Package

Students should come away understanding:

- how to analyze a legacy schema before migrating it
- how to decide between adding a column and adding a new table
- how to design foreign keys and indexes intentionally
- how to normalize multilingual content into translation tables
- how to treat old data as untrusted input that must be validated and cleaned

## Where To Go Next

- Need schema-change guidance: `DATABASE_CHANGE_PLAYBOOK.md`
- Need legacy dump findings: `LEGACY_SQL_AUDIT.md`
- Need future full-site architecture: `FULL_SITE_MIGRATION_BLUEPRINT.md`
- Need legacy table-to-module mapping: `FULL_SITE_LEGACY_MODULE_CATALOG.md`
- Need staging and cleanup rules: `FULL_SITE_DATA_CLEANING_STANDARD.md`
- Need table design examples: `TABLE_RELATIONSHIPS.md`
- Need command reminders: `QUICK_REFERENCE.md`
- Need troubleshooting: `TROUBLESHOOTING.md`

---

## Reference Material Below

The remaining sections of this file retain useful migration concepts and code examples from the earlier package draft.

Use them for learning patterns, but do not assume they are fully validated against the current repository state.

---

## 📊 Key Tables Explained

### 1. Faculty System
```
faculty_categories (categories like Medicine, Dentistry)
  ↓
faculty_members (professors, doctors)
  ↓
faculty_publications (research, papers)
```

**Example:**
- Category: "Faculty of Medicine"
- Member: "Dr. Ahmad Hassan"
- Publication: "Research on COVID-19"

### 2. Students System
```
honor_students (top students by year)
alumni (graduated students 2006-2024)
student_achievements (awards, competitions)
```

**Example:**
- Honor Student: "Sara Ali, GPA 3.9, 2023"
- Alumni: "Mohammad Youssef, Medicine 2020"

### 3. Support Systems
```
faq_categories (topics)
  ↓
faqs (questions & answers in AR/EN)

complaint_categories (types)
  ↓
complaints (tickets with status tracking)

job_categories (departments)
  ↓
job_postings (open positions)
  ↓
job_applications (candidate submissions)
```

---

## 🔧 How Migrations Work

### Creating a Migration

```bash
php artisan make:migration create_example_table
```

### Migration Structure

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Create table
        Schema::create('example', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('is_active');
        });
        
        // Create translations table
        Schema::create('example_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('example_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 2); // 'ar' or 'en'
            $table->string('title');
            $table->text('content');
            $table->timestamps();
            
            $table->unique(['example_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('example_translations');
        Schema::dropIfExists('example');
    }
};
```

### Key Concepts

1. **Foreign Keys**: Link tables together
   ```php
   $table->foreignId('category_id')->constrained()->cascadeOnDelete();
   ```

2. **Indexes**: Speed up queries
   ```php
   $table->index('status');
   $table->index(['user_id', 'created_at']);
   ```

3. **Soft Deletes**: Keep deleted records
   ```php
   $table->softDeletes();
   ```

4. **Translations**: Support multiple languages
   ```php
   // Main table
   Schema::create('posts', function (Blueprint $table) {
       $table->id();
       $table->timestamps();
   });
   
   // Translation table
   Schema::create('post_translations', function (Blueprint $table) {
       $table->id();
       $table->foreignId('post_id')->constrained()->cascadeOnDelete();
       $table->string('locale', 2);
       $table->string('title');
       $table->text('content');
       $table->timestamps();
       
       $table->unique(['post_id', 'locale']);
   });
   ```

---

## 🔄 Data Migration Process

### 1. Connect to Old Database

```php
// In seeder
$oldDb = DB::connection('old_spu');
$oldRecords = $oldDb->table('jx_items')->get();
```

### 2. Transform Data

```php
foreach ($oldRecords as $old) {
    // Transform old structure to new
    $newRecord = [
        'title' => $old->name,
        'content' => $old->description,
        'is_active' => $old->visible == 1,
        'created_at' => $old->date_add,
        'updated_at' => now(),
    ];
    
    // Insert into new table
    DB::table('posts')->insert($newRecord);
}
```

### 3. Handle Translations

```php
// Create main record
$postId = DB::table('posts')->insertGetId([
    'is_active' => true,
    'created_at' => now(),
    'updated_at' => now(),
]);

// Create translations
$translations = [
    [
        'post_id' => $postId,
        'locale' => 'ar',
        'title' => $old->title_ar,
        'content' => $old->content_ar,
        'created_at' => now(),
        'updated_at' => now(),
    ],
    [
        'post_id' => $postId,
        'locale' => 'en',
        'title' => $old->title_en,
        'content' => $old->content_en,
        'created_at' => now(),
        'updated_at' => now(),
    ],
];

DB::table('post_translations')->insert($translations);
```

### 4. Log Everything

```php
DB::table('migration_logs')->insert([
    'batch_name' => 'posts_migration',
    'source_table' => 'jx_items',
    'target_table' => 'posts',
    'source_id' => $old->id,
    'target_id' => $postId,
    'status' => 'success',
    'message' => 'Migrated successfully',
    'migrated_at' => now(),
    'created_at' => now(),
    'updated_at' => now(),
]);
```

---

## 🎓 Best Practices

### 1. Always Use Transactions

```php
DB::beginTransaction();

try {
    // Your migration code
    DB::commit();
} catch (\Exception $e) {
    DB::rollBack();
    throw $e;
}
```

### 2. Validate Before Insert

```php
// Skip invalid records
if (empty($old->name)) {
    $this->log('skipped', 'Empty name');
    continue;
}

// Validate email
if (!filter_var($old->email, FILTER_VALIDATE_EMAIL)) {
    $this->log('skipped', 'Invalid email');
    continue;
}
```

### 3. Batch Processing for Large Tables

```php
// Process in chunks of 100
$oldDb->table('jx_items')
    ->orderBy('id')
    ->chunk(100, function ($items) {
        foreach ($items as $item) {
            // Process item
        }
    });
```

### 4. Never Migrate Passwords As-Is

```php
// WRONG ❌
$user->password = $old->password; // MD5 hash

// RIGHT ✅
$user->password = Hash::make(Str::random(32)); // Force reset
$user->is_locked = true; // Require password reset
```

### 5. Preserve Relationships

```php
// First migrate parent
$categoryId = DB::table('categories')->insertGetId([...]);

// Then migrate children with foreign key
DB::table('posts')->insert([
    'category_id' => $categoryId, // Link to parent
    ...
]);
```

---

## 📝 Common Patterns

### Pattern 1: Simple Table Migration

```php
private function migrateSimpleTable(): void
{
    $oldRecords = $this->oldDb->table('old_table')->get();
    
    foreach ($oldRecords as $old) {
        DB::table('new_table')->insert([
            'name' => $old->name,
            'value' => $old->value,
            'created_at' => $old->date_add ?? now(),
            'updated_at' => now(),
        ]);
    }
}
```

### Pattern 2: Table with Translations

```php
private function migrateWithTranslations(): void
{
    $oldRecords = $this->oldDb->table('old_table')->get();
    
    foreach ($oldRecords as $old) {
        // Main record
        $id = DB::table('new_table')->insertGetId([
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        // Translations
        DB::table('new_table_translations')->insert([
            [
                'new_table_id' => $id,
                'locale' => 'ar',
                'name' => $old->name_ar,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'new_table_id' => $id,
                'locale' => 'en',
                'name' => $old->name_en,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
```

### Pattern 3: Hierarchical Data (Parent-Child)

```php
private function migrateHierarchy(): void
{
    // First migrate parents
    $parents = $this->oldDb->table('old_table')
        ->whereNull('parent_id')
        ->get();
    
    foreach ($parents as $parent) {
        $parentId = DB::table('new_table')->insertGetId([...]);
        
        // Then migrate children
        $children = $this->oldDb->table('old_table')
            ->where('parent_id', $parent->id)
            ->get();
        
        foreach ($children as $child) {
            DB::table('new_table')->insert([
                'parent_id' => $parentId,
                ...
            ]);
        }
    }
}
```

---

## 🔍 Debugging Tips

### Check Migration Logs

```sql
-- See migration summary
SELECT status, COUNT(*) as count
FROM migration_logs
GROUP BY status;

-- Find failures
SELECT source_table, source_id, message
FROM migration_logs
WHERE status = 'failed'
ORDER BY migrated_at DESC
LIMIT 20;

-- Check specific table
SELECT *
FROM migration_logs
WHERE source_table = 'jx_items'
AND status = 'failed';
```

### Verify Data Counts

```php
// Compare old vs new
$oldCount = DB::connection('old_spu')->table('jx_items')->count();
$newCount = DB::table('posts')->count();
echo "Old: {$oldCount}, New: {$newCount}";
```

### Test Queries

```bash
php artisan tinker
>>> DB::table('faculty_members')->count()
>>> DB::table('alumni')->where('graduation_year', 2023)->count()
>>> DB::table('faqs')->with('translations')->first()
```

---

## 📚 Learning Resources

### Laravel Documentation
- Migrations: https://laravel.com/docs/migrations
- Database: https://laravel.com/docs/database
- Seeding: https://laravel.com/docs/seeding

### SQL Best Practices
- Use indexes for frequently queried columns
- Always define foreign keys
- Use appropriate data types
- Normalize data (avoid duplication)

### Migration Best Practices
- One migration per logical change
- Always provide `down()` method
- Test migrations on copy of production data
- Use transactions for data integrity

---

## ✅ Checklist for Students

### Understanding
- [ ] I understand what migrations are
- [ ] I know how to create a migration
- [ ] I understand foreign keys
- [ ] I know how to handle translations
- [ ] I understand the old database structure

### Practical Skills
- [ ] I can create a new migration
- [ ] I can write a seeder
- [ ] I can connect to multiple databases
- [ ] I can transform data between structures
- [ ] I can debug migration issues

### Project Specific
- [ ] I understand the SPU database structure
- [ ] I know which tables to migrate
- [ ] I understand the security requirements
- [ ] I can run the complete migration
- [ ] I can verify the results

---

## 🎯 Exercise for Students

### Task: Migrate a Simple Table

1. **Analyze old table:**
   ```sql
   DESCRIBE jx_faqs;
   SELECT * FROM jx_faqs LIMIT 5;
   ```

2. **Design new structure:**
   - Main table: `faqs`
   - Translation table: `faq_translations`
   - Fields: question, answer (in AR/EN)

3. **Create migration:**
   ```bash
   php artisan make:migration create_faqs_tables
   ```

4. **Write seeder:**
   ```bash
   php artisan make:seeder MigrateFaqsSeeder
   ```

5. **Test:**
   ```bash
   php artisan db:seed --class=MigrateFaqsSeeder
   ```

6. **Verify:**
   ```sql
   SELECT COUNT(*) FROM faqs;
   SELECT * FROM faq_translations WHERE locale = 'ar' LIMIT 5;
   ```

---

## 📞 Need Help?

### Common Issues

**Issue:** "Table not found"
**Solution:** Run `php artisan migrate` first

**Issue:** "Foreign key constraint fails"
**Solution:** Migrate parent tables before children

**Issue:** "Duplicate entry"
**Solution:** Check for existing records before insert

**Issue:** "Memory limit exceeded"
**Solution:** Use chunk() for large tables

---

## 🎉 Summary

You've learned:
- ✅ How to design database structure
- ✅ How to create Laravel migrations
- ✅ How to migrate data between databases
- ✅ How to handle translations
- ✅ How to debug and verify migrations

**Next Steps:**
1. Study the existing migrations in `database/migrations/`
2. Review the seeders in `database/seeders/`
3. Practice with small tables first
4. Run the complete migration
5. Verify all data migrated correctly

---

**Good luck with your database migration!** 🚀
