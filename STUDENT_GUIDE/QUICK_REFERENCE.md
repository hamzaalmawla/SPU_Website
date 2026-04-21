# 📋 Quick Reference - Database Migration Cheat Sheet

> Important:
> This cheat sheet contains draft migration commands and schema examples.
> Do not assume the package is a finished one-command migration.
> Read `README.md`, `DATABASE_CHANGE_PLAYBOOK.md`, and `LEGACY_SQL_AUDIT.md` first.
> Some commands use Bash syntax and should be translated for PowerShell on Windows.

## Essential Commands

### Setup
```bash
# Install dependencies
composer install

# Setup environment
cp .env.example .env
php artisan key:generate

# Create databases
mysql -u root -p -e "CREATE DATABASE spuedu_new;"
mysql -u root -p -e "CREATE DATABASE spuedu_old;"

# Run migrations
php artisan migrate

# Import old data
mysql -u root -p spuedu_old < spuedu_db.sql

# Run migration
php artisan db:seed --class=CompleteDatabaseMigrationSeeder
```

---

## Database Connections

### .env Configuration
```env
# New database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=spuedu_new
DB_USERNAME=root
DB_PASSWORD=your_password

# Old database
OLD_DB_HOST=127.0.0.1
OLD_DB_PORT=3306
OLD_DB_DATABASE=spuedu_old
OLD_DB_USERNAME=root
OLD_DB_PASSWORD=your_password
```

---

## Migration Batches

| Batch | Tables | Purpose |
|-------|--------|---------|
| 1 | languages, countries, users | Foundation |
| 2 | settings, sites | Configuration |
| 3 | categories, logos | Structure |
| 4 | content_items, pages, media | Content |
| 5 | faculty_members, councils | University |
| 6 | alumni, honor_students | Students |
| 7 | faqs, complaints, jobs | Support |
| 8 | comments | Engagement |

---

## Table Mappings

### Old → New

| Old Table | New Table | Notes |
|-----------|-----------|-------|
| jx_admins | users | Force password reset |
| jx_graduated_students | alumni | 5,255 records |
| jx_good_students | honor_students | 1,070 records |
| jx_members | faculty_members | Professors |
| jx_member_items | faculty_publications | Research |
| jx_member_categories | faculty_categories | Departments |
| jx_councils | councils | University councils |
| jx_councils1 | council_members | Council members |
| jx_faqs | faqs + faq_translations | Q&A system |
| jx_complaints | complaints | Ticket system |
| jx_job_sites | job_postings | Career portal |
| jx_items_comments | comments | User comments |
| jx_config + jx_config1 | settings | Merged |
| jx_docs + jx_logos | media | Unified |
| jx_languages | languages | AR/EN |
| jx_countries | countries | Reference |
| jx_cities | cities | Reference |

---

## Common SQL Queries

### Check Migration Progress
```sql
-- Summary by status
SELECT status, COUNT(*) as count
FROM migration_logs
GROUP BY status;

-- Recent migrations
SELECT batch_name, status, COUNT(*) as count
FROM migration_logs
GROUP BY batch_name, status
ORDER BY batch_name;

-- Failed migrations
SELECT source_table, source_id, message
FROM migration_logs
WHERE status = 'failed'
ORDER BY migrated_at DESC
LIMIT 20;
```

### Verify Data Counts
```sql
-- Compare old vs new
SELECT 'Old Alumni' as source, COUNT(*) as count FROM spuedu_old.jx_graduated_students
UNION ALL
SELECT 'New Alumni', COUNT(*) FROM spuedu_new.alumni;

-- Check translations
SELECT locale, COUNT(*) as count
FROM faq_translations
GROUP BY locale;

-- Check faculty
SELECT COUNT(*) as total_faculty FROM faculty_members;
SELECT COUNT(*) as total_publications FROM faculty_publications;
```

### Find Issues
```sql
-- Records without translations
SELECT f.id, f.created_at
FROM faqs f
LEFT JOIN faq_translations ft ON f.id = ft.faq_id
WHERE ft.id IS NULL;

-- Orphaned records (no parent)
SELECT c.id, c.created_at
FROM comments c
LEFT JOIN content_items ci ON c.commentable_id = ci.id
WHERE c.commentable_type = 'content_item' AND ci.id IS NULL;
```

---

## Laravel Artisan Commands

### Database
```bash
# Run migrations
php artisan migrate

# Rollback last migration
php artisan migrate:rollback

# Reset database
php artisan migrate:fresh

# Run specific seeder
php artisan db:seed --class=CompleteDatabaseMigrationSeeder

# Interactive console
php artisan tinker
```

### Cache
```bash
# Clear all cache
php artisan cache:clear

# Clear config cache
php artisan config:clear

# Clear route cache
php artisan route:clear
```

---

## PHP Tinker Commands

```php
// Start tinker
php artisan tinker

// Check counts
>>> DB::table('alumni')->count()
>>> DB::table('faculty_members')->count()
>>> DB::table('faqs')->count()

// Check specific record
>>> DB::table('alumni')->where('graduation_year', 2023)->first()

// Check translations
>>> DB::table('faq_translations')->where('locale', 'ar')->count()

// Check migration logs
>>> DB::table('migration_logs')->where('status', 'failed')->count()

// Test connection
>>> DB::connection('old_spu')->table('jx_items')->count()

// Exit
>>> exit
```

---

## Migration Seeder Structure

```php
class CompleteDatabaseMigrationSeeder extends Seeder
{
    public function run(): void
    {
        // Batch 1: Foundation
        $this->migrateLanguages();
        $this->migrateCountries();
        $this->migrateAdmins();
        
        // Batch 2: Configuration
        $this->migrateSettings();
        $this->migrateSites();
        
        // Batch 3: Structure
        $this->migrateCategories();
        $this->migrateLogos();
        
        // Batch 4: Content
        $this->migrateItems();
        $this->migratePages();
        $this->migratePhotos();
        $this->migrateDocs();
        
        // Batch 5: University
        $this->migrateFacultyCategories();
        $this->migrateFacultyMembers();
        $this->migrateFacultyPublications();
        $this->migrateCouncils();
        $this->migrateCouncilMembers();
        
        // Batch 6: Students
        $this->migrateGraduates();
        $this->migrateHonorStudents();
        
        // Batch 7: Support
        $this->migrateFaqs();
        $this->migrateComplaints();
        $this->migrateJobs();
        
        // Batch 8: Engagement
        $this->migrateComments();
    }
}
```

---

## Common Patterns

### Pattern 1: Simple Migration
```php
private function migrateSimple(): void
{
    $records = $this->oldDb->table('old_table')->get();
    
    foreach ($records as $old) {
        DB::table('new_table')->insert([
            'name' => $old->name,
            'created_at' => $old->date_add ?? now(),
            'updated_at' => now(),
        ]);
    }
}
```

### Pattern 2: With Translations
```php
private function migrateWithTranslations(): void
{
    $records = $this->oldDb->table('old_table')->get();
    
    foreach ($records as $old) {
        $id = DB::table('new_table')->insertGetId([
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        DB::table('new_table_translations')->insert([
            ['new_table_id' => $id, 'locale' => 'ar', 'name' => $old->name_ar],
            ['new_table_id' => $id, 'locale' => 'en', 'name' => $old->name_en],
        ]);
    }
}
```

### Pattern 3: With Validation
```php
private function migrateWithValidation(): void
{
    $records = $this->oldDb->table('old_table')->get();
    
    foreach ($records as $old) {
        // Skip invalid
        if (empty($old->name)) {
            $this->log('skipped', 'Empty name');
            continue;
        }
        
        // Insert valid
        DB::table('new_table')->insert([...]);
    }
}
```

---

## Data Types Reference

### Common Column Types
```php
$table->id();                          // Auto-increment primary key
$table->foreignId('user_id');          // Foreign key
$table->string('name', 255);           // VARCHAR(255)
$table->text('description');           // TEXT
$table->boolean('is_active');          // BOOLEAN
$table->integer('count');              // INTEGER
$table->decimal('price', 8, 2);        // DECIMAL(8,2)
$table->date('birth_date');            // DATE
$table->datetime('published_at');      // DATETIME
$table->timestamps();                  // created_at, updated_at
$table->softDeletes();                 // deleted_at
```

### Indexes
```php
$table->index('column_name');                    // Single column
$table->index(['col1', 'col2']);                 // Composite
$table->unique('email');                         // Unique constraint
$table->unique(['table_id', 'locale']);          // Composite unique
```

### Foreign Keys
```php
$table->foreignId('category_id')
    ->constrained()                    // References categories(id)
    ->cascadeOnDelete();               // Delete children when parent deleted
    
$table->foreignId('user_id')
    ->constrained('users')             // Explicit table name
    ->nullOnDelete();                  // Set NULL when parent deleted
```

---

## Troubleshooting Quick Fixes

### "Connection refused"
```bash
# Start MySQL
sudo systemctl start mysql
# or
net start MySQL80
```

### "Access denied"
```bash
# Check .env file has correct password
# Test connection
mysql -u root -p
```

### "Table not found"
```bash
# Run migrations first
php artisan migrate
```

### "Foreign key constraint fails"
```bash
# Check parent table exists
# Check parent record exists
# Migrate parent tables first
```

### "Memory limit exceeded"
```bash
# Increase PHP memory
php -d memory_limit=512M artisan db:seed
```

### "Duplicate entry"
```bash
# Check for existing records
# Use updateOrCreate instead of insert
# Add unique constraints
```

---

## File Locations

```
project/
├── config/
│   ├── database.php          # Database connections
│   └── old_database.php      # Migration config
├── database/
│   ├── migrations/           # Table structure
│   │   ├── 2026_04_15_000001_create_migration_log_table.php
│   │   ├── 2026_04_15_000010_create_faculty_tables.php
│   │   └── ... (9 files total)
│   └── seeders/              # Data migration
│       ├── CompleteDatabaseMigrationSeeder.php
│       └── MigrateFacultySeeder.php
├── .env                      # Environment config
└── spuedu_db.sql            # Old database dump
```

---

## Important Notes

### Security
- ⚠️ **NEVER** migrate passwords as-is
- ✅ Always force password reset
- ✅ Use bcrypt for new passwords
- ✅ Lock accounts until reset

### Data Quality
- ✅ Validate before insert
- ✅ Skip invalid records
- ✅ Log all operations
- ✅ Use transactions

### Performance
- ✅ Use chunk() for large tables
- ✅ Add indexes for queries
- ✅ Batch insert when possible
- ✅ Monitor memory usage

### Translations
- ✅ Default locale: 'ar'
- ✅ Support: 'ar', 'en'
- ✅ Separate translation tables
- ✅ Unique constraint on (id, locale)

---

## Success Criteria

Migration is successful when:
- ✅ Success rate > 90%
- ✅ All critical tables migrated
- ✅ Translations working
- ✅ No broken foreign keys
- ✅ Data counts match expectations

---

## Next Steps After Migration

1. ✅ Verify data counts
2. ✅ Check migration logs
3. ✅ Test translations
4. ✅ Copy media files
5. ✅ Send password resets
6. ✅ Test functionality
7. ✅ Get approval

---

**Keep this cheat sheet handy during migration!** 📋
