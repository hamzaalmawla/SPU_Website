<?php

declare(strict_types=1);

namespace Database\Seeders\LegacyImport;

use Illuminate\Support\Facades\DB;

class ImportLegacyFacultiesSeeder extends BaseLegacyImportSeeder
{
    public function run(): void
    {
        $module = 'faculties';

        if (! $this->shouldRunModule($module)) {
            return;
        }

        $batch = $this->batchName($module);
        $definitions = $this->legacyFacultyCatalog();

        $this->command?->info('Starting faculties import from legacy taxonomy: '.count($definitions).' rows defined.');

        $imported = 0;
        $skipped = 0;

        foreach ($definitions as $sourceId => $definition) {
            if ($this->alreadyImported('jx_member_categories', $sourceId, 'faculties')) {
                $skipped++;

                continue;
            }

            try {
                $facultyId = DB::transaction(function () use ($definition): int {
                    $timestamp = now();
                    $existingId = DB::table('faculties')->where('slug', $definition['slug'])->value('id');

                    if ($existingId !== null) {
                        $facultyId = (int) $existingId;

                        DB::table('faculties')->where('id', $facultyId)->update([
                            'sort_order' => $definition['sort_order'],
                            'is_enabled' => true,
                            'updated_at' => $timestamp,
                        ]);
                    } else {
                        $facultyId = DB::table('faculties')->insertGetId([
                            'slug' => $definition['slug'],
                            'sort_order' => $definition['sort_order'],
                            'is_enabled' => true,
                            'created_at' => $timestamp,
                            'updated_at' => $timestamp,
                        ]);
                    }

                    DB::table('faculty_translations')->updateOrInsert(
                        ['faculty_id' => $facultyId, 'locale' => 'ar'],
                        [
                            'name' => $definition['name_ar'],
                            'short_description' => null,
                            'description' => null,
                            'updated_at' => $timestamp,
                            'created_at' => $timestamp,
                        ],
                    );

                    DB::table('faculty_translations')->updateOrInsert(
                        ['faculty_id' => $facultyId, 'locale' => 'en'],
                        [
                            'name' => $definition['name_en'],
                            'short_description' => null,
                            'description' => null,
                            'updated_at' => $timestamp,
                            'created_at' => $timestamp,
                        ],
                    );

                    return $facultyId;
                });

                $this->migrationLogger()->log(
                    $module,
                    $batch,
                    'jx_member_categories',
                    $sourceId,
                    'faculties',
                    $facultyId,
                    'success',
                    'Imported faculty from legacy taxonomy.',
                    ['slug' => $definition['slug']],
                );
                $imported++;
            } catch (\Throwable $e) {
                $this->reject($module, 'jx_member_categories', $sourceId, 'unknown_mapping', $e->getMessage(), ['slug' => $definition['slug']]);
                $this->logSkip($module, $batch, 'jx_member_categories', $sourceId, 'faculties', 'Insert failed: '.$e->getMessage());
                $skipped++;
            }
        }

        $this->command?->info("Faculties import complete. Imported: {$imported}, Skipped: {$skipped}");
    }
}
