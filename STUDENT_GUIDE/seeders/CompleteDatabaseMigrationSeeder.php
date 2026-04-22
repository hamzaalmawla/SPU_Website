<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;

class CompleteDatabaseMigrationSeeder extends Seeder
{
    private $oldDb;
    private $migrationLogs = [];
    private $config;
    
    public function __construct()
    {
        $this->oldDb = DB::connection('old_spu');
        $this->config = config('old_database');
    }
    
    public function run(): void
    {
        $this->info('=== COMPLETE DATABASE MIGRATION STARTED ===');
        $this->info('Timestamp: ' . now()->toDateTimeString());
        
        try {
            // Run migrations in batches
            foreach ($this->config['migration_batches'] as $batchName => $tables) {
                $this->info("\n--- Starting Batch: {$batchName} ---");
                
                foreach ($tables as $table) {
                    $this->migrateTable($table, $batchName);
                }
                
                $this->info("--- Batch {$batchName} Complete ---\n");
            }
            
            $this->info('=== MIGRATION COMPLETED SUCCESSFULLY ===');
            $this->printSummary();
            
        } catch (\Exception $e) {
            $this->error('=== MIGRATION FAILED ===');
            $this->error('Error: ' . $e->getMessage());
            $this->error('File: ' . $e->getFile() . ':' . $e->getLine());
            $this->error('Trace: ' . $e->getTraceAsString());
        }
    }
    
    private function migrateTable(string $table, string $batch): void
    {
        $this->info("  Migrating: {$table}");
        
        $method = 'migrate' . Str::studly(str_replace('jx_', '', $table));
        
        if (method_exists($this, $method)) {
            $this->{$method}($batch);
        } else {
            $this->warn("  ⚠ No migration method found for {$table}");
        }
    }
    
    // ============================================
    // BATCH 1: FOUNDATION
    // ============================================
    
    private function migrateLanguages(string $batch): void
    {
        $oldLanguages = $this->oldDb->table('jx_languages')->get();
        
        foreach ($oldLanguages as $old) {
            // Implementation depends on old schema
            $this->log($batch, 'jx_languages', 'languages', $old->id ?? null, null, 'skipped', 'Schema analysis needed');
        }
    }
    
    private function migrateCountries(string $batch): void
    {
        $oldCountries = $this->oldDb->table('jx_countries')->get();
        
        foreach ($oldCountries as $old) {
            $this->log($batch, 'jx_countries', 'countries', $old->id ?? null, null, 'skipped', 'Schema analysis needed');
        }
    }
    
    private function migrateCities(string $batch): void
    {
        $oldCities = $this->oldDb->table('jx_cities')->get();
        
        foreach ($oldCities as $old) {
            $this->log($batch, 'jx_cities', 'cities', $old->id ?? null, null, 'skipped', 'Schema analysis needed');
        }
    }
    
    private function migrateAdmins(string $batch): void
    {
        $this->info('    → Migrating admin users...');
        
        $oldAdmins = $this->oldDb->table('jx_admins')->get();
        $migrated = 0;
        $skipped = 0;
        
        foreach ($oldAdmins as $oldAdmin) {
            // Skip if no email
            if (empty($oldAdmin->email)) {
                $this->log($batch, 'jx_admins', 'users', $oldAdmin->id, null, 'skipped', 'No email address');
                $skipped++;
                continue;
            }
            
            // Check if already exists
            if (DB::table('users')->where('email', $oldAdmin->email)->exists()) {
                $this->log($batch, 'jx_admins', 'users', $oldAdmin->id, null, 'skipped', 'Email already exists');
                $skipped++;
                continue;
            }
            
            try {
                // Create user
                $userId = DB::table('users')->insertGetId([
                    'name' => $oldAdmin->full_name ?? $oldAdmin->user_name,
                    'email' => $oldAdmin->email,
                    'password' => Hash::make(Str::random(32)), // Force reset
                    'locale' => $oldAdmin->lang === '0' ? 'ar' : 'en',
                    'is_locked' => true,
                    'created_at' => $oldAdmin->reg_date ? Carbon::parse($oldAdmin->reg_date) : now(),
                    'updated_at' => now(),
                ]);
                
                // Assign role
                $role = $oldAdmin->is_supervisor ? 'super_admin' : 'editor';
                DB::table('model_has_roles')->insert([
                    'role_id' => DB::table('roles')->where('name', $role)->value('id'),
                    'model_type' => 'App\\Models\\User',
                    'model_id' => $userId,
                ]);
                
                $this->log($batch, 'jx_admins', 'users', $oldAdmin->id, $userId, 'success', "Migrated as {$role}");
                $migrated++;
                
            } catch (\Exception $e) {
                $this->log($batch, 'jx_admins', 'users', $oldAdmin->id, null, 'failed', $e->getMessage());
                $skipped++;
            }
        }
        
        $this->info("    ✓ Migrated: {$migrated}, Skipped: {$skipped}");
    }
    
    private function migrateAdminCategory(string $batch): void
    {
        // Implementation depends on schema
        $this->warn('    ⚠ Admin categories migration pending schema analysis');
    }
    
    // ============================================
    // BATCH 2: CONFIGURATION
    // ============================================
    
    private function migrateConfig(string $batch): void
    {
        $this->info('    → Migrating config settings...');
        
        $oldConfigs = $this->oldDb->table('jx_config')->get();
        
        foreach ($oldConfigs as $old) {
            // Implementation depends on schema
            $this->log($batch, 'jx_config', 'settings', $old->id ?? null, null, 'skipped', 'Schema analysis needed');
        }
    }
    
    private function migrateConfig1(string $batch): void
    {
        $this->info('    → Migrating config1 settings...');
        
        $oldConfigs = $this->oldDb->table('jx_config1')->get();
        
        foreach ($oldConfigs as $old) {
            $this->log($batch, 'jx_config1', 'settings', $old->id ?? null, null, 'skipped', 'Schema analysis needed');
        }
    }
    
    private function migrateSites(string $batch): void
    {
        $oldSites = $this->oldDb->table('jx_sites')->get();
        
        foreach ($oldSites as $old) {
            $this->log($batch, 'jx_sites', 'site_sections', $old->id ?? null, null, 'skipped', 'Schema analysis needed');
        }
    }
    
    // ============================================
    // BATCH 3: CONTENT STRUCTURE
    // ============================================
    
    private function migrateCategories(string $batch): void
    {
        $this->info('    → Migrating categories...');
        
        $oldCategories = $this->oldDb->table('jx_categories')->get();
        
        foreach ($oldCategories as $old) {
            $this->log($batch, 'jx_categories', 'categories', $old->id ?? null, null, 'skipped', 'Schema analysis needed');
        }
    }
    
    private function migrateLogos(string $batch): void
    {
        $this->info('    → Migrating logos...');
        
        $oldLogos = $this->oldDb->table('jx_logos')->get();
        
        foreach ($oldLogos as $old) {
            $this->log($batch, 'jx_logos', 'media', $old->id ?? null, null, 'skipped', 'Schema analysis needed');
        }
    }
    
    // ============================================
    // BATCH 4: CONTENT
    // ============================================
    
    private function migrateItems(string $batch): void
    {
        $this->info('    → Migrating content items...');
        
        $oldItems = $this->oldDb->table('jx_items')->get();
        
        foreach ($oldItems as $old) {
            $this->log($batch, 'jx_items', 'content_items', $old->id ?? null, null, 'skipped', 'Schema analysis needed');
        }
    }
    
    private function migrateSiteStaticPages(string $batch): void
    {
        $this->info('    → Migrating static pages...');
        
        $oldPages = $this->oldDb->table('jx_site_static_pages')->get();
        
        foreach ($oldPages as $old) {
            $this->log($batch, 'jx_site_static_pages', 'pages', $old->id ?? null, null, 'skipped', 'Schema analysis needed');
        }
    }
    
    private function migrateHomePhotos(string $batch): void
    {
        $this->info('    → Migrating homepage photos...');
        
        $oldPhotos = $this->oldDb->table('jx_home_photos')->get();
        
        foreach ($oldPhotos as $old) {
            $this->log($batch, 'jx_home_photos', 'homepage_sections', $old->id ?? null, null, 'skipped', 'Schema analysis needed');
        }
    }
    
    private function migrateDocs(string $batch): void
    {
        $this->info('    → Migrating documents...');
        
        $oldDocs = $this->oldDb->table('jx_docs')->get();
        
        foreach ($oldDocs as $old) {
            $this->log($batch, 'jx_docs', 'media', $old->id ?? null, null, 'skipped', 'Schema analysis needed');
        }
    }
    
    private function migrateArchive(string $batch): void
    {
        $this->info('    → Migrating archived content...');
        
        $oldArchive = $this->oldDb->table('jx_archive')->get();
        
        foreach ($oldArchive as $old) {
            $this->log($batch, 'jx_archive', 'archived_content', $old->id ?? null, null, 'skipped', 'Schema analysis needed');
        }
    }
    
    // ============================================
    // BATCH 5: UNIVERSITY
    // ============================================
    
    private function migrateMemberCategories(string $batch): void
    {
        $this->info('    → Migrating faculty categories...');
        
        $oldCategories = $this->oldDb->table('jx_member_categories')->get();
        
        foreach ($oldCategories as $old) {
            $this->log($batch, 'jx_member_categories', 'faculty_categories', $old->id ?? null, null, 'skipped', 'Schema analysis needed');
        }
    }
    
    private function migrateMembers(string $batch): void
    {
        $this->info('    → Migrating faculty members...');
        
        $oldMembers = $this->oldDb->table('jx_members')->get();
        
        foreach ($oldMembers as $old) {
            $this->log($batch, 'jx_members', 'faculty_members', $old->id ?? null, null, 'skipped', 'Schema analysis needed');
        }
    }
    
    private function migrateMemberItems(string $batch): void
    {
        $this->info('    → Migrating faculty publications...');
        
        $oldItems = $this->oldDb->table('jx_member_items')->get();
        
        foreach ($oldItems as $old) {
            $this->log($batch, 'jx_member_items', 'faculty_publications', $old->id ?? null, null, 'skipped', 'Schema analysis needed');
        }
    }
    
    private function migrateCouncils(string $batch): void
    {
        $this->info('    → Migrating councils...');
        
        $oldCouncils = $this->oldDb->table('jx_councils')->get();
        
        foreach ($oldCouncils as $old) {
            $this->log($batch, 'jx_councils', 'councils', $old->id ?? null, null, 'skipped', 'Schema analysis needed');
        }
    }
    
    private function migrateCouncils1(string $batch): void
    {
        $this->info('    → Migrating council members...');
        
        $oldMembers = $this->oldDb->table('jx_councils1')->get();
        
        foreach ($oldMembers as $old) {
            $this->log($batch, 'jx_councils1', 'council_members', $old->id ?? null, null, 'skipped', 'Schema analysis needed');
        }
    }
    
    // ============================================
    // BATCH 6: STUDENTS
    // ============================================
    
    private function migrateGoodStudents(string $batch): void
    {
        $this->info('    → Migrating honor students...');
        
        $oldStudents = $this->oldDb->table('jx_good_students')->get();
        
        foreach ($oldStudents as $old) {
            $this->log($batch, 'jx_good_students', 'honor_students', $old->id ?? null, null, 'skipped', 'Schema analysis needed');
        }
    }
    
    private function migrateGraduatedStudents(string $batch): void
    {
        $this->info('    → Migrating alumni...');
        
        $oldStudents = $this->oldDb->table('jx_graduated_students')->get();
        
        foreach ($oldStudents as $old) {
            $this->log($batch, 'jx_graduated_students', 'alumni', $old->id ?? null, null, 'skipped', 'Schema analysis needed');
        }
    }
    
    // ============================================
    // BATCH 7: SUPPORT
    // ============================================
    
    private function migrateFaqs(string $batch): void
    {
        $this->info('    → Migrating FAQs...');
        
        $oldFaqs = $this->oldDb->table('jx_faqs')->get();
        
        foreach ($oldFaqs as $old) {
            $this->log($batch, 'jx_faqs', 'faqs', $old->id ?? null, null, 'skipped', 'Schema analysis needed');
        }
    }
    
    private function migrateComplaintCats(string $batch): void
    {
        $this->info('    → Migrating complaint categories...');
        
        $oldCategories = $this->oldDb->table('jx_complaint_cats')->get();
        
        foreach ($oldCategories as $old) {
            $this->log($batch, 'jx_complaint_cats', 'complaint_categories', $old->id ?? null, null, 'skipped', 'Schema analysis needed');
        }
    }
    
    private function migrateComplaints(string $batch): void
    {
        $this->info('    → Migrating complaints...');
        
        $oldComplaints = $this->oldDb->table('jx_complaints')->get();
        
        foreach ($oldComplaints as $old) {
            $this->log($batch, 'jx_complaints', 'complaints', $old->id ?? null, null, 'skipped', 'Schema analysis needed');
        }
    }
    
    private function migrateJobSites(string $batch): void
    {
        $this->info('    → Migrating job postings...');
        
        $oldJobs = $this->oldDb->table('jx_job_sites')->get();
        
        foreach ($oldJobs as $old) {
            $this->log($batch, 'jx_job_sites', 'job_postings', $old->id ?? null, null, 'skipped', 'Schema analysis needed');
        }
    }
    
    // ============================================
    // BATCH 8: ENGAGEMENT
    // ============================================
    
    private function migrateItemsComments(string $batch): void
    {
        $this->info('    → Migrating comments...');
        
        $oldComments = $this->oldDb->table('jx_items_comments')->get();
        
        foreach ($oldComments as $old) {
            $this->log($batch, 'jx_items_comments', 'comments', $old->id ?? null, null, 'skipped', 'Schema analysis needed');
        }
    }
    
    private function migrateAdminsServices(string $batch): void
    {
        $this->info('    → Migrating admin service assignments...');
        
        $oldAssignments = $this->oldDb->table('jx_admins_services')->get();
        
        foreach ($oldAssignments as $old) {
            $this->log($batch, 'jx_admins_services', 'user_service_assignments', $old->id ?? null, null, 'skipped', 'Schema analysis needed');
        }
    }
    
    // ============================================
    // HELPER METHODS
    // ============================================
    
    private function log(string $batch, string $sourceTable, string $targetTable, $sourceId, $targetId, string $status, string $message): void
    {
        $this->migrationLogs[] = [
            'batch_name' => $batch,
            'source_table' => $sourceTable,
            'target_table' => $targetTable,
            'source_id' => $sourceId,
            'target_id' => $targetId,
            'status' => $status,
            'message' => $message,
            'migrated_at' => now(),
        ];
        
        // Insert to database every 100 records
        if (count($this->migrationLogs) >= 100) {
            $this->flushLogs();
        }
    }
    
    private function flushLogs(): void
    {
        if (!empty($this->migrationLogs)) {
            DB::table('migration_logs')->insert($this->migrationLogs);
            $this->migrationLogs = [];
        }
    }
    
    private function printSummary(): void
    {
        $this->flushLogs(); // Flush remaining logs
        
        $this->info("\n=== MIGRATION SUMMARY ===");
        
        $summary = DB::table('migration_logs')
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get();
        
        foreach ($summary as $stat) {
            $this->info("  {$stat->status}: {$stat->count}");
        }
        
        $this->info("\nDetailed logs saved to migration_logs table");
    }
    
    private function info(string $message): void
    {
        echo "[INFO] {$message}\n";
    }
    
    private function warn(string $message): void
    {
        echo "[WARN] {$message}\n";
    }
    
    private function error(string $message): void
    {
        echo "[ERROR] {$message}\n";
    }
}
