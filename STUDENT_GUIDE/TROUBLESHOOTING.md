# 🔧 Troubleshooting Guide

## Common Issues and Solutions

---

## 1. Database Connection Issues

### Problem: "Connection refused" or "Can't connect to MySQL server"

**Symptoms:**
```
SQLSTATE[HY000] [2002] Connection refused
```

**Causes:**
- MySQL server is not running
- Wrong host/port in .env

**Solutions:**

**On Linux:**
```bash
# Check if MySQL is running
sudo systemctl status mysql

# Start MySQL
sudo systemctl start mysql

# Enable auto-start
sudo systemctl enable mysql
```

**On Windows:**
```bash
# Check service status
sc query MySQL80

# Start service
net start MySQL80
```

**On macOS:**
```bash
# Start MySQL
brew services start mysql
```

**Verify connection:**
```bash
mysql -u root -p -h 127.0.0.1 -P 3306
```

---

### Problem: "Access denied for user 'root'@'localhost'"

**Symptoms:**
```
SQLSTATE[HY000] [1045] Access denied for user 'root'@'localhost' (using password: YES)
```

**Causes:**
- Wrong password in .env
- User doesn't have permissions

**Solutions:**

1. **Check .env file:**
```env
DB_USERNAME=root
DB_PASSWORD=your_actual_password  # Make sure this is correct!
```

2. **Reset MySQL root password:**
```bash
# Stop MySQL
sudo systemctl stop mysql

# Start in safe mode
sudo mysqld_safe --skip-grant-tables &

# Connect without password
mysql -u root

# Reset password
mysql> FLUSH PRIVILEGES;
mysql> ALTER USER 'root'@'localhost' IDENTIFIED BY 'new_password';
mysql> exit;

# Restart MySQL normally
sudo systemctl restart mysql
```

3. **Grant permissions:**
```sql
GRANT ALL PRIVILEGES ON *.* TO 'root'@'localhost' WITH GRANT OPTION;
FLUSH PRIVILEGES;
```

---

### Problem: "Unknown database 'spuedu_new'"

**Symptoms:**
```
SQLSTATE[HY000] [1049] Unknown database 'spuedu_new'
```

**Cause:**
- Database doesn't exist

**Solution:**
```bash
# Create the database
mysql -u root -p -e "CREATE DATABASE spuedu_new CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Verify
mysql -u root -p -e "SHOW DATABASES;"
```

---

## 2. Migration Issues

### Problem: "Table already exists"

**Symptoms:**
```
SQLSTATE[42S01]: Base table or view already exists: 1050 Table 'users' already exists
```

**Cause:**
- Migrations already ran

**Solutions:**

**Option 1: Continue from where you left off**
```bash
# Check which migrations ran
php artisan migrate:status

# Run remaining migrations
php artisan migrate
```

**Option 2: Start fresh (⚠️ DELETES ALL DATA)**
```bash
# Drop all tables and re-run
php artisan migrate:fresh
```

**Option 3: Rollback and retry**
```bash
# Rollback last batch
php artisan migrate:rollback

# Run again
php artisan migrate
```

---

### Problem: "Foreign key constraint fails"

**Symptoms:**
```
SQLSTATE[23000]: Integrity constraint violation: 1452 Cannot add or update a child row: a foreign key constraint fails
```

**Causes:**
- Parent record doesn't exist
- Wrong migration order
- Data inconsistency

**Solutions:**

1. **Check migration order:**
```bash
# Migrations should run in order:
# 1. Parent tables first (users, categories)
# 2. Child tables second (posts, comments)
```

2. **Check if parent exists:**
```sql
-- Example: If inserting post with category_id=5
SELECT * FROM categories WHERE id = 5;
-- If no result, that's the problem!
```

3. **Fix data:**
```php
// In seeder, check parent exists
$categoryId = DB::table('categories')->where('id', $old->category_id)->value('id');

if (!$categoryId) {
    $this->log('skipped', 'Category not found');
    continue;
}
```

---

### Problem: "Syntax error in migration"

**Symptoms:**
```
syntax error, unexpected token ")"
```

**Cause:**
- PHP syntax error in migration file

**Solution:**
```bash
# Check the file mentioned in error
# Common issues:
# - Missing semicolon
# - Extra comma
# - Unclosed bracket
# - Wrong method name

# Example of common error:
$table->string('name',);  # ❌ Extra comma
$table->string('name');   # ✅ Correct
```

---

## 3. Seeder Issues

### Problem: "Class 'CompleteDatabaseMigrationSeeder' not found"

**Symptoms:**
```
Target class [CompleteDatabaseMigrationSeeder] does not exist.
```

**Causes:**
- Seeder file doesn't exist
- Autoload not updated

**Solutions:**

1. **Check file exists:**
```bash
ls -la database/seeders/CompleteDatabaseMigrationSeeder.php
```

2. **Regenerate autoload:**
```bash
composer dump-autoload
```

3. **Check namespace:**
```php
// File should start with:
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class CompleteDatabaseMigrationSeeder extends Seeder
{
    // ...
}
```

---

### Problem: "Memory limit exceeded"

**Symptoms:**
```
Fatal error: Allowed memory size of 134217728 bytes exhausted
```

**Cause:**
- Processing too much data at once

**Solutions:**

**Option 1: Increase PHP memory limit**
```bash
php -d memory_limit=512M artisan db:seed --class=CompleteDatabaseMigrationSeeder
```

**Option 2: Use chunking in seeder**
```php
// Instead of:
$records = $this->oldDb->table('jx_items')->get();  // ❌ Loads all

// Use:
$this->oldDb->table('jx_items')->chunk(100, function ($records) {  // ✅ Loads 100 at a time
    foreach ($records as $record) {
        // Process
    }
});
```

**Option 3: Update php.ini**
```ini
memory_limit = 512M
```

---

### Problem: "Duplicate entry for key 'PRIMARY'"

**Symptoms:**
```
SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry '1' for key 'PRIMARY'
```

**Causes:**
- Trying to insert same ID twice
- Seeder ran multiple times

**Solutions:**

1. **Check if already migrated:**
```php
// Before inserting
$exists = DB::table('alumni')->where('id', $oldId)->exists();
if ($exists) {
    $this->log('skipped', 'Already migrated');
    continue;
}
```

2. **Use updateOrCreate:**
```php
// Instead of insert()
DB::table('alumni')->updateOrCreate(
    ['id' => $oldId],  // Match condition
    [                   // Data to insert/update
        'name' => $old->name,
        'year' => $old->year,
    ]
);
```

3. **Clear and re-run:**
```bash
# Delete all data from table
mysql -u root -p -e "TRUNCATE TABLE spuedu_new.alumni;"

# Run seeder again
php artisan db:seed --class=CompleteDatabaseMigrationSeeder
```

---

### Problem: "Call to undefined method"

**Symptoms:**
```
Call to undefined method Illuminate\Database\Query\Builder::with()
```

**Cause:**
- Using Eloquent method on Query Builder

**Solution:**
```php
// ❌ Wrong - Query Builder doesn't have with()
$records = DB::table('posts')->with('translations')->get();

// ✅ Correct - Use join or separate query
$records = DB::table('posts')->get();
foreach ($records as $post) {
    $translations = DB::table('post_translations')
        ->where('post_id', $post->id)
        ->get();
}
```

---

## 4. Data Issues

### Problem: "Invalid datetime format"

**Symptoms:**
```
SQLSTATE[22007]: Invalid datetime format: 1292 Incorrect datetime value: '0000-00-00 00:00:00'
```

**Cause:**
- Old database has invalid dates

**Solution:**
```php
// Validate and fix dates
$createdAt = $old->date_add;

if (!$createdAt || $createdAt == '0000-00-00 00:00:00') {
    $createdAt = now();  // Use current time
}

// Or check if valid
if (strtotime($createdAt) === false) {
    $createdAt = now();
}
```

---

### Problem: "Data truncated for column"

**Symptoms:**
```
SQLSTATE[22001]: String data, right truncated: 1406 Data too long for column 'name'
```

**Cause:**
- Data longer than column size

**Solutions:**

1. **Truncate data:**
```php
$name = substr($old->name, 0, 255);  // Limit to 255 chars
```

2. **Change column type:**
```php
// In migration
$table->text('name');  // Instead of string('name', 255)
```

3. **Validate length:**
```php
if (strlen($old->name) > 255) {
    $this->log('skipped', 'Name too long');
    continue;
}
```

---

### Problem: "Incorrect string value: '\\xF0\\x9F...'"

**Symptoms:**
```
SQLSTATE[HY000]: General error: 1366 Incorrect string value: '\xF0\x9F\x98\x80' for column 'content'
```

**Cause:**
- Emoji or special characters, wrong charset

**Solutions:**

1. **Ensure utf8mb4:**
```sql
-- Check table charset
SHOW CREATE TABLE posts;

-- Change if needed
ALTER TABLE posts CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

2. **In migration:**
```php
Schema::create('posts', function (Blueprint $table) {
    $table->charset = 'utf8mb4';
    $table->collation = 'utf8mb4_unicode_ci';
    // ...
});
```

3. **Strip emojis if needed:**
```php
$content = preg_replace('/[\x{1F600}-\x{1F64F}]/u', '', $old->content);
```

---

## 5. Performance Issues

### Problem: "Seeder taking too long"

**Symptoms:**
- Seeder runs for hours
- No progress visible

**Solutions:**

1. **Add progress indicators:**
```php
$total = $this->oldDb->table('jx_items')->count();
$bar = $this->command->getOutput()->createProgressBar($total);

foreach ($records as $record) {
    // Process
    $bar->advance();
}

$bar->finish();
```

2. **Use batch inserts:**
```php
// Instead of inserting one by one
foreach ($records as $record) {
    DB::table('posts')->insert([...]);  // ❌ Slow
}

// Batch insert
$batch = [];
foreach ($records as $record) {
    $batch[] = [...];
    
    if (count($batch) >= 100) {
        DB::table('posts')->insert($batch);  // ✅ Fast
        $batch = [];
    }
}
if (!empty($batch)) {
    DB::table('posts')->insert($batch);
}
```

3. **Disable query log:**
```php
DB::connection()->disableQueryLog();
```

---

### Problem: "MySQL server has gone away"

**Symptoms:**
```
SQLSTATE[HY000]: General error: 2006 MySQL server has gone away
```

**Causes:**
- Query too large
- Connection timeout
- max_allowed_packet too small

**Solutions:**

1. **Increase MySQL settings:**
```sql
-- Check current value
SHOW VARIABLES LIKE 'max_allowed_packet';

-- Increase (in my.cnf or my.ini)
[mysqld]
max_allowed_packet=64M
wait_timeout=28800
```

2. **Reconnect in seeder:**
```php
// Every 1000 records
if ($count % 1000 == 0) {
    DB::reconnect();
}
```

3. **Use smaller batches:**
```php
// Reduce chunk size
->chunk(50, function ($records) {  // Instead of 1000
    // ...
});
```

---

## 6. Verification Issues

### Problem: "Data counts don't match"

**Symptoms:**
- Old DB: 5,255 graduates
- New DB: 4,800 graduates

**Causes:**
- Some records skipped
- Validation rules too strict
- Migration incomplete

**Solutions:**

1. **Check migration logs:**
```sql
SELECT status, COUNT(*) as count
FROM migration_logs
WHERE source_table = 'jx_graduated_students'
GROUP BY status;
```

2. **Find skipped records:**
```sql
SELECT source_id, message
FROM migration_logs
WHERE source_table = 'jx_graduated_students'
AND status = 'skipped';
```

3. **Review validation rules:**
```php
// Maybe too strict?
if (empty($old->name)) {  // ❌ Skips records with no name
    continue;
}

// More lenient?
$name = $old->name ?: 'Unknown';  // ✅ Use default
```

---

### Problem: "Translations missing"

**Symptoms:**
- Main records exist
- Translation records missing

**Cause:**
- Translation insert failed
- Empty translation data

**Solutions:**

1. **Check for orphans:**
```sql
SELECT f.id
FROM faqs f
LEFT JOIN faq_translations ft ON f.id = ft.faq_id
WHERE ft.id IS NULL;
```

2. **Add validation:**
```php
// Ensure at least one translation
if (empty($old->name_ar) && empty($old->name_en)) {
    $this->log('skipped', 'No translations');
    continue;
}
```

3. **Use default values:**
```php
$translations = [
    [
        'faq_id' => $faqId,
        'locale' => 'ar',
        'question' => $old->question_ar ?: 'لا يوجد',  // Default
        'answer' => $old->answer_ar ?: 'لا يوجد',
    ],
    // ...
];
```

---

## 7. Environment Issues

### Problem: "Composer dependencies not installed"

**Symptoms:**
```
Class 'Illuminate\Foundation\Application' not found
```

**Solution:**
```bash
# Install dependencies
composer install

# If composer.lock conflicts
composer update
```

---

### Problem: "APP_KEY not set"

**Symptoms:**
```
No application encryption key has been specified.
```

**Solution:**
```bash
# Generate key
php artisan key:generate

# Verify in .env
cat .env | grep APP_KEY
```

---

### Problem: "Storage not writable"

**Symptoms:**
```
file_put_contents(/path/to/storage/logs/laravel.log): Failed to open stream: Permission denied
```

**Solution:**
```bash
# Fix permissions
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# Or for development
chmod -R 777 storage bootstrap/cache
```

---

## 8. Debugging Tips

### Enable Query Logging
```php
// In seeder
DB::enableQueryLog();

// Your code

// See queries
dd(DB::getQueryLog());
```

### Add Debug Output
```php
// In seeder
$this->command->info("Processing {$record->id}");
$this->command->warn("Skipping invalid record");
$this->command->error("Failed to insert");
```

### Use Transactions for Testing
```php
DB::beginTransaction();

try {
    // Your migration code
    
    // Don't commit - just test
    DB::rollBack();
    
} catch (\Exception $e) {
    DB::rollBack();
    throw $e;
}
```

### Check Specific Record
```bash
php artisan tinker
>>> $old = DB::connection('old_spu')->table('jx_items')->find(123);
>>> dd($old);
```

---

## 9. Getting Help

### Check Laravel Logs
```bash
tail -f storage/logs/laravel.log
```

### Check MySQL Error Log
```bash
# Linux
tail -f /var/log/mysql/error.log

# Windows
# Check: C:\ProgramData\MySQL\MySQL Server 8.0\Data\*.err
```

### Run in Verbose Mode
```bash
php artisan db:seed --class=CompleteDatabaseMigrationSeeder -vvv
```

### Test Database Connection
```bash
php artisan tinker
>>> DB::connection()->getPdo();
>>> DB::connection('old_spu')->getPdo();
```

---

## 10. Prevention Tips

### Before Running Migration

✅ **Backup everything**
```bash
# Backup old database
mysqldump -u root -p spuedu_old > backup_old.sql

# Backup new database
mysqldump -u root -p spuedu_new > backup_new.sql
```

✅ **Test on small dataset first**
```php
// In seeder, limit records
$records = $this->oldDb->table('jx_items')->limit(10)->get();
```

✅ **Verify environment**
```bash
php -v          # PHP version
mysql --version # MySQL version
composer --version
```

✅ **Check disk space**
```bash
df -h
```

✅ **Use transactions**
```php
DB::beginTransaction();
try {
    // Migration code
    DB::commit();
} catch (\Exception $e) {
    DB::rollBack();
    throw $e;
}
```

---

## Quick Diagnostic Checklist

When something goes wrong, check:

- [ ] Is MySQL running?
- [ ] Are credentials in .env correct?
- [ ] Do databases exist?
- [ ] Did migrations run?
- [ ] Is old database imported?
- [ ] Are there error messages in logs?
- [ ] Is there enough disk space?
- [ ] Is there enough memory?
- [ ] Are file permissions correct?
- [ ] Is composer autoload updated?

---

## Emergency Recovery

### If Everything Breaks

1. **Stop the seeder** (Ctrl+C)

2. **Check what was migrated:**
```sql
SELECT batch_name, status, COUNT(*)
FROM migration_logs
GROUP BY batch_name, status;
```

3. **Rollback if needed:**
```bash
# Drop all tables
php artisan migrate:fresh

# Re-run migrations
php artisan migrate
```

4. **Start over from specific batch:**
```php
// In seeder, skip completed batches
if ($this->isBatchComplete('batch_1')) {
    $this->command->info('Batch 1 already complete, skipping...');
    return;
}
```

---

**Remember: Always backup before migrating!** 💾

**When in doubt, ask for help!** 🆘
