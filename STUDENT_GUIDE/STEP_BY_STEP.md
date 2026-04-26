# 🎯 Step-by-Step Migration Guide

## For Students Learning Database Migration

> Important:
> This file should be used as a draft workflow reference, not proof that the included seeders are fully implemented.
> Read `README.md`, `DATABASE_CHANGE_PLAYBOOK.md`, and `LEGACY_SQL_AUDIT.md` first.
> Some command blocks are Bash-style examples and may need PowerShell equivalents on Windows.

---

## 📋 Prerequisites

Before starting, make sure you have:
- [ ] PHP 8.2+ installed
- [ ] MySQL installed and running
- [ ] Composer installed
- [ ] Laravel project set up
- [ ] Old database SQL file (`spuedu_db.sql`)

---

## Step 1: Setup Laravel Environment (10 minutes)

### 1.1 Install Dependencies

```bash
# Navigate to project folder
cd SPU_Website-P01-Services

# Install Composer packages
composer install
```

**What this does:** Downloads all Laravel packages needed for the project.

### 1.2 Configure Environment

```bash
# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate
```

**What this does:** Creates your configuration file and security key.

### 1.3 Update Database Settings

Open `.env` file and update:

```env
# New database (where data will go)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=spuedu_new
DB_USERNAME=root
DB_PASSWORD=your_mysql_password

# Old database (where data comes from)
OLD_DB_HOST=127.0.0.1
OLD_DB_PORT=3306
OLD_DB_DATABASE=spuedu_old
OLD_DB_USERNAME=root
OLD_DB_PASSWORD=your_mysql_password
```

**Important:** Replace `your_mysql_password` with your actual MySQL password!

---

## Step 2: Create New Database Structure (5 minutes)

### 2.1 Create New Database

```bash
# Create the new database
mysql -u root -p -e "CREATE DATABASE spuedu_new CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

**What this does:** Creates an empty database for the new structure.

### 2.2 Run Migrations

```bash
# Create all new tables
php artisan migrate
```

**What this does:** Creates 34 new tables with proper structure:
- `users` (admin accounts)
- `faculty_members` (professors)
- `alumni` (graduated students)
- `faqs` (questions & answers)
- And 30 more tables...

### 2.3 Verify Tables Created

```bash
# Check tables
php artisan tinker
>>> DB::select('SHOW TABLES');
>>> exit
```

**Expected:** You should see 34 tables listed.

---

## Step 3: Import Old Database (10 minutes)

### 3.1 Create Old Database

```bash
# Create database for old data
mysql -u root -p -e "CREATE DATABASE spuedu_old CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

### 3.2 Import SQL File

```bash
# Import the old database (this takes 5-10 minutes)
mysql -u root -p spuedu_old < spuedu_db.sql
```

**What this does:** Imports all old data (197 MB, 30 tables, ~40,000 records).

**Note:** This will take several minutes. Be patient!

### 3.3 Verify Import

```bash
# Check old database
mysql -u root -p -e "USE spuedu_old; SHOW TABLES;"
mysql -u root -p -e "USE spuedu_old; SELECT COUNT(*) FROM jx_items;"
```

**Expected:** 
- 30 tables
- jx_items: ~22,168 rows

---

## Step 4: Run Data Migration (20-30 minutes)

### 4.1 Start Migration

```bash
# Run the complete migration
php artisan db:seed --class=CompleteDatabaseMigrationSeeder
```

**What this does:** Migrates all data from old database to new database in 8 batches:

1. **Batch 1:** Languages, countries, admin users
2. **Batch 2:** Settings, site configuration
3. **Batch 3:** Categories, logos
4. **Batch 4:** Content (news, pages, photos)
5. **Batch 5:** Faculty members, councils
6. **Batch 6:** Students (graduates, honor students)
7. **Batch 7:** FAQs, complaints, jobs
8. **Batch 8:** Comments, assignments

### 4.2 Monitor Progress

Open another terminal and watch:

```bash
# Watch migration progress
watch -n 2 'mysql -u root -p -e "SELECT status, COUNT(*) FROM spuedu_new.migration_logs GROUP BY status"'
```

**What you'll see:**
- `success`: Records migrated successfully
- `failed`: Records that had errors
- `skipped`: Records that were intentionally skipped

---

## Step 5: Verify Results (10 minutes)

### 5.1 Check Migration Summary

```bash
php artisan tinker
>>> DB::table('migration_logs')->select('status', DB::raw('COUNT(*) as count'))->groupBy('status')->get();
```

**Expected output:**
```
[
  { status: "success", count: 25000 },
  { status: "skipped", count: 5000 },
  { status: "failed", count: 50 }
]
```

### 5.2 Check Data Counts

```bash
php artisan tinker
>>> echo "Users: " . DB::table('users')->count();
>>> echo "Faculty: " . DB::table('faculty_members')->count();
>>> echo "Alumni: " . DB::table('alumni')->count();
>>> echo "FAQs: " . DB::table('faqs')->count();
>>> exit
```

**Expected:**
- Users: ~20
- Alumni: ~5,255
- FAQs: ~1,653

### 5.3 Check for Failures

```sql
-- Find failed migrations
SELECT source_table, source_id, message
FROM migration_logs
WHERE status = 'failed'
ORDER BY migrated_at DESC
LIMIT 20;
```

### 5.4 Verify Translations

```bash
php artisan tinker
>>> DB::table('faq_translations')->where('locale', 'ar')->count();
>>> DB::table('faq_translations')->where('locale', 'en')->count();
>>> exit
```

**Expected:** Both Arabic and English translations exist.

---

## Step 6: Post-Migration Tasks (Optional)

### 6.1 Copy Media Files

If you have access to the old server:

```bash
# Copy images and documents
rsync -avz old_server:/path/to/uploads/ storage/app/public/media/
```

Or if files are local:

```bash
cp -r /old/website/uploads/* storage/app/public/media/
```

### 6.2 Send Password Reset Emails

All admin users need to reset their passwords:

```bash
php artisan tinker
>>> $users = User::where('is_locked', true)->get();
>>> foreach ($users as $user) {
...     echo "Sending reset email to: {$user->email}\n";
...     // $user->sendPasswordResetNotification(Password::createToken($user));
... }
>>> exit
```

---

## 📊 Understanding the Results

### Migration Logs Table

Every migration operation is logged:

| Field | Description |
|-------|-------------|
| `batch_name` | Which batch (1-8) |
| `source_table` | Old table name (e.g., jx_items) |
| `target_table` | New table name (e.g., posts) |
| `source_id` | Old record ID |
| `target_id` | New record ID |
| `status` | success/failed/skipped |
| `message` | Error or info message |
| `migrated_at` | When it was migrated |

### Success Criteria

Migration is successful if:
- ✅ Success rate > 90%
- ✅ All critical tables migrated (users, faculty, alumni)
- ✅ Translations working (AR/EN)
- ✅ No broken foreign keys
- ✅ Data counts match expectations

---

## 🐛 Troubleshooting

### Problem 1: "Connection refused"

**Cause:** MySQL is not running

**Solution:**
```bash
# Start MySQL
sudo systemctl start mysql

# Or on Windows
net start MySQL80
```

### Problem 2: "Access denied for user"

**Cause:** Wrong password in .env

**Solution:** Update `.env` with correct MySQL password

### Problem 3: "Table not found"

**Cause:** Migrations not run

**Solution:**
```bash
php artisan migrate
```

### Problem 4: "Memory limit exceeded"

**Cause:** Large dataset

**Solution:**
```bash
# Increase PHP memory
php -d memory_limit=512M artisan db:seed --class=CompleteDatabaseMigrationSeeder
```

### Problem 5: "Foreign key constraint fails"

**Cause:** Parent record doesn't exist

**Solution:** Check migration order, ensure parent tables migrate first

---

## 📝 What You Learned

After completing this guide, you now know:

1. ✅ How to set up Laravel environment
2. ✅ How to create database structure with migrations
3. ✅ How to import old database
4. ✅ How to migrate data between databases
5. ✅ How to verify migration results
6. ✅ How to troubleshoot common issues

---

## 🎯 Practice Exercise

Try migrating a single table yourself:

### Exercise: Migrate FAQs Table

1. **Study the old table:**
   ```sql
   USE spuedu_old;
   DESCRIBE jx_faqs;
   SELECT * FROM jx_faqs LIMIT 5;
   ```

2. **Look at the new structure:**
   ```sql
   USE spuedu_new;
   DESCRIBE faqs;
   DESCRIBE faq_translations;
   ```

3. **Find the migration code:**
   - Open: `database/migrations/2026_04_15_000013_create_faqs_tables.php`
   - Study how tables are created

4. **Find the seeder code:**
   - Open: `database/seeders/CompleteDatabaseMigrationSeeder.php`
   - Find the `migrateFaqs()` method

5. **Understand the transformation:**
   - Old: Single table with language field
   - New: Main table + translations table
   - How are Arabic/English handled?

---

## 📚 Next Steps

1. **Study the code:**
   - Read migration files in `database/migrations/`
   - Read seeder files in `database/seeders/`

2. **Understand the patterns:**
   - How are translations handled?
   - How are foreign keys created?
   - How is data validated?

3. **Practice:**
   - Try modifying a migration
   - Create a new seeder for a custom table
   - Add validation rules

4. **Document:**
   - Write notes about what you learned
   - Create diagrams of table relationships
   - Document any issues you encountered

---

## ✅ Final Checklist

- [ ] Laravel environment set up
- [ ] New database created
- [ ] Migrations ran successfully
- [ ] Old database imported
- [ ] Data migration completed
- [ ] Results verified
- [ ] Migration logs reviewed
- [ ] Failures investigated (if any)
- [ ] Media files copied (if applicable)
- [ ] Password resets sent (if applicable)

---

## 🎉 Congratulations!

You've successfully completed a database migration!

**What you achieved:**
- Migrated 29 tables
- Transformed ~40,000 records
- Created proper database structure
- Handled multilingual content
- Logged all operations

**This is a valuable skill for:**
- Database administration
- System upgrades
- Data transformation
- Legacy system modernization

---

**Questions?** Review the main README.md or ask your instructor!

**Good job!** 🚀
