<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MigrateFacultySeeder extends Seeder
{
    private $oldDb;

    public function __construct()
    {
        $this->oldDb = DB::connection('old_spu');
    }

    public function run(): void
    {
        $this->info('=== MIGRATING FACULTY DATA ===');

        DB::beginTransaction();

        try {
            // Step 1: Migrate faculty categories
            $this->migrateFacultyCategories();

            // Step 2: Migrate faculty members
            $this->migrateFacultyMembers();

            // Step 3: Migrate faculty publications
            $this->migrateFacultyPublications();

            DB::commit();
            $this->info('=== FACULTY MIGRATION COMPLETE ===');

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Migration failed: '.$e->getMessage());
            throw $e;
        }
    }

    private function migrateFacultyCategories(): void
    {
        $this->info('Migrating faculty categories...');

        $oldCategories = $this->oldDb->table('jx_member_categories')->get();
        $migrated = 0;

        foreach ($oldCategories as $old) {
            try {
                // Create category
                $categoryId = DB::table('faculty_categories')->insertGetId([
                    'slug' => Str::slug($old->name ?? "category-{$old->id}"),
                    'order' => $old->order ?? 0,
                    'is_active' => $old->is_active ?? true,
                    'created_at' => $old->created_at ?? now(),
                    'updated_at' => now(),
                ]);

                // Create translations (AR and EN)
                $translations = [
                    [
                        'faculty_category_id' => $categoryId,
                        'locale' => 'ar',
                        'name' => $old->name_ar ?? $old->name ?? 'فئة',
                        'description' => $old->description_ar ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                    [
                        'faculty_category_id' => $categoryId,
                        'locale' => 'en',
                        'name' => $old->name_en ?? $old->name ?? 'Category',
                        'description' => $old->description_en ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                ];

                DB::table('faculty_category_translations')->insert($translations);

                $this->logMigration('jx_member_categories', 'faculty_categories', $old->id, $categoryId, 'success');
                $migrated++;

            } catch (\Exception $e) {
                $this->logMigration('jx_member_categories', 'faculty_categories', $old->id, null, 'failed', $e->getMessage());
            }
        }

        $this->info("✓ Migrated {$migrated} faculty categories");
    }

    private function migrateFacultyMembers(): void
    {
        $this->info('Migrating faculty members...');

        $oldMembers = $this->oldDb->table('jx_members')->get();
        $migrated = 0;

        foreach ($oldMembers as $old) {
            try {
                // Map old category to new
                $categoryId = null;
                if (! empty($old->category_id)) {
                    $categoryId = DB::table('migration_logs')
                        ->where('source_table', 'jx_member_categories')
                        ->where('source_id', $old->category_id)
                        ->where('status', 'success')
                        ->value('target_id');
                }

                // Create member
                $memberId = DB::table('faculty_members')->insertGetId([
                    'faculty_category_id' => $categoryId,
                    'email' => $old->email,
                    'phone' => $old->phone,
                    'office_location' => $old->office ?? null,
                    'photo_path' => $old->photo ? "faculty/{$old->photo}" : null,
                    'cv_path' => $old->cv ? "faculty/cv/{$old->cv}" : null,
                    'order' => $old->order ?? 0,
                    'is_active' => $old->is_active ?? true,
                    'is_featured' => $old->is_featured ?? false,
                    'created_at' => $old->created_at ?? now(),
                    'updated_at' => now(),
                ]);

                // Create translations
                $translations = [
                    [
                        'faculty_member_id' => $memberId,
                        'locale' => 'ar',
                        'full_name' => $old->full_name_ar ?? $old->full_name ?? 'عضو هيئة تدريس',
                        'title' => $old->title_ar ?? null,
                        'position' => $old->position_ar ?? null,
                        'bio' => $old->bio_ar ?? null,
                        'specializations' => $old->specializations_ar ?? null,
                        'education' => $old->education_ar ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                    [
                        'faculty_member_id' => $memberId,
                        'locale' => 'en',
                        'full_name' => $old->full_name_en ?? $old->full_name ?? 'Faculty Member',
                        'title' => $old->title_en ?? null,
                        'position' => $old->position_en ?? null,
                        'bio' => $old->bio_en ?? null,
                        'specializations' => $old->specializations_en ?? null,
                        'education' => $old->education_en ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                ];

                DB::table('faculty_member_translations')->insert($translations);

                $this->logMigration('jx_members', 'faculty_members', $old->id, $memberId, 'success');
                $migrated++;

            } catch (\Exception $e) {
                $this->logMigration('jx_members', 'faculty_members', $old->id, null, 'failed', $e->getMessage());
            }
        }

        $this->info("✓ Migrated {$migrated} faculty members");
    }

    private function migrateFacultyPublications(): void
    {
        $this->info('Migrating faculty publications...');

        $oldPublications = $this->oldDb->table('jx_member_items')->get();
        $migrated = 0;

        foreach ($oldPublications as $old) {
            try {
                // Map old member to new
                $memberId = DB::table('migration_logs')
                    ->where('source_table', 'jx_members')
                    ->where('source_id', $old->member_id)
                    ->where('status', 'success')
                    ->value('target_id');

                if (! $memberId) {
                    throw new \Exception('Member not found');
                }

                // Create publication
                $publicationId = DB::table('faculty_publications')->insertGetId([
                    'faculty_member_id' => $memberId,
                    'type' => $old->type ?? 'publication',
                    'published_date' => $old->published_date ? Carbon::parse($old->published_date) : null,
                    'url' => $old->url,
                    'file_path' => $old->file ? "faculty/publications/{$old->file}" : null,
                    'order' => $old->order ?? 0,
                    'created_at' => $old->created_at ?? now(),
                    'updated_at' => now(),
                ]);

                // Create translations
                $translations = [
                    [
                        'faculty_publication_id' => $publicationId,
                        'locale' => 'ar',
                        'title' => $old->title_ar ?? $old->title ?? 'منشور',
                        'description' => $old->description_ar ?? null,
                        'publisher' => $old->publisher_ar ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                    [
                        'faculty_publication_id' => $publicationId,
                        'locale' => 'en',
                        'title' => $old->title_en ?? $old->title ?? 'Publication',
                        'description' => $old->description_en ?? null,
                        'publisher' => $old->publisher_en ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                ];

                DB::table('faculty_publication_translations')->insert($translations);

                $this->logMigration('jx_member_items', 'faculty_publications', $old->id, $publicationId, 'success');
                $migrated++;

            } catch (\Exception $e) {
                $this->logMigration('jx_member_items', 'faculty_publications', $old->id, null, 'failed', $e->getMessage());
            }
        }

        $this->info("✓ Migrated {$migrated} faculty publications");
    }

    private function logMigration(string $sourceTable, string $targetTable, $sourceId, $targetId, string $status, ?string $message = null): void
    {
        DB::table('migration_logs')->insert([
            'batch_name' => 'faculty_migration',
            'source_table' => $sourceTable,
            'target_table' => $targetTable,
            'source_id' => $sourceId,
            'target_id' => $targetId,
            'status' => $status,
            'message' => $message,
            'migrated_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function info(string $message): void
    {
        echo "[INFO] {$message}\n";
    }

    private function error(string $message): void
    {
        echo "[ERROR] {$message}\n";
    }
}
