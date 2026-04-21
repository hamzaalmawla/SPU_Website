<?php

declare(strict_types=1);

namespace Database\Seeders\LegacyImport;

use Illuminate\Support\Facades\DB;

class ImportLegacyCouncilsSeeder extends BaseLegacyImportSeeder
{
    public function run(): void
    {
        $module = 'councils';
        $batch = $this->batchName($module);

        $this->importCouncils($module, $batch);
        $this->importCouncilMembers($module, $batch);
    }

    private function importCouncils(string $module, string $batch): void
    {
        $definitions = $this->legacyCouncilCatalog();

        $this->command?->info('Starting councils import from legacy taxonomy: '.count($definitions).' rows defined.');

        $imported = 0;
        $skipped = 0;

        foreach ($definitions as $definition) {
            $sourceId = $definition['source_id'];

            if ($this->alreadyImported('legacy_council_taxonomy', $sourceId, 'councils')) {
                $skipped++;

                continue;
            }

            try {
                $existingId = DB::table('councils')->where('slug', $definition['slug'])->value('id');

                if ($existingId !== null) {
                    $councilId = (int) $existingId;
                } else {
                    $councilId = DB::table('councils')->insertGetId([
                        'slug' => $definition['slug'],
                        'type' => $definition['type'],
                        'sort_order' => $definition['sort_order'],
                        'is_enabled' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    DB::table('council_translations')->insert([
                        [
                            'council_id' => $councilId,
                            'locale' => 'ar',
                            'name' => $definition['name_ar'],
                            'description' => null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ],
                        [
                            'council_id' => $councilId,
                            'locale' => 'en',
                            'name' => $definition['name_en'],
                            'description' => null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ],
                    ]);
                }

                $this->migrationLogger()->log(
                    $module,
                    $batch,
                    'legacy_council_taxonomy',
                    $sourceId,
                    'councils',
                    $councilId,
                    'success',
                    'Imported council from legacy taxonomy.',
                    ['slug' => $definition['slug']],
                );
                $imported++;
            } catch (\Throwable $e) {
                $this->reject($module, 'legacy_council_taxonomy', $sourceId, 'unknown_mapping', $e->getMessage(), ['slug' => $definition['slug']]);
                $this->logSkip($module, $batch, 'legacy_council_taxonomy', $sourceId, 'councils', 'Insert failed: '.$e->getMessage());
                $skipped++;
            }
        }

        $this->command?->info("Councils import complete. Imported: {$imported}, Skipped: {$skipped}");
    }

    private function importCouncilMembers(string $module, string $batch): void
    {
        $rows = $this->legacyRows('jx_councils1');

        if ($rows->isEmpty()) {
            $this->command?->warn('No rows found in jx_councils1.');

            return;
        }

        $this->command?->info("Starting council members import: {$rows->count()} rows found.");

        $imported = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $sourceId = $this->normalizedInteger($this->rowValue($row, 'id'));

            if ($this->alreadyImported('jx_councils1', $sourceId, 'council_members')) {
                $skipped++;

                continue;
            }

            $nameAr = $this->cleanedString($row, ['ar_name', 'name_ar', 'full_name_ar', 'full_name']);
            $nameEn = $this->cleanedString($row, ['en_name', 'name_en', 'full_name_en', 'full_name']);

            if (($nameAr === null || $nameAr === '') && ($nameEn === null || $nameEn === '')) {
                $this->reject($module, 'jx_councils1', $sourceId, 'unknown_mapping', 'Council member has no name.');
                $this->logSkip($module, $batch, 'jx_councils1', $sourceId, 'council_members', 'No name found.');
                $skipped++;

                continue;
            }

            $serviceType = $this->normalizedInteger($this->rowValue($row, 'service_type'));
            $councilId = $this->resolveLegacyCouncilIdByServiceType($serviceType);

            if ($councilId === null) {
                $this->logSkip(
                    $module,
                    $batch,
                    'jx_councils1',
                    $sourceId,
                    'council_members',
                    'Skipped row outside mapped council service types.',
                    ['service_type' => $serviceType],
                );
                $skipped++;

                continue;
            }

            $facultyMemberId = $this->targetIdResolver()->resolve('jx_councils1', $sourceId, 'faculty_members');
            $sortOrder = $this->normalizedInteger($this->rowValue($row, ['council_order', 'order', 'sort_order', 'record_order'])) ?? 0;
            $isEnabled = $this->normalizedBoolean($this->rowValue($row, ['is_visible', 'is_active', 'active', 'is_enabled']), true);

            try {
                $memberId = DB::table('council_members')->insertGetId([
                    'council_id' => $councilId,
                    'faculty_member_id' => $facultyMemberId,
                    'sort_order' => $sortOrder,
                    'is_enabled' => $isEnabled,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $translations = [];

                if ($nameAr !== null && $nameAr !== '') {
                    $translations[] = [
                        'council_member_id' => $memberId,
                        'locale' => 'ar',
                        'full_name' => $nameAr,
                        'position' => $this->cleanedString($row, ['ar_position', 'position_ar', 'ar_title', 'title_ar']),
                        'bio' => $this->htmlSanitizer()->sanitize(
                            (string) $this->rowValue($row, ['ar_data', 'ar_bio', 'ar_description'], '')
                        ) ?: null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                if ($nameEn !== null && $nameEn !== '') {
                    $translations[] = [
                        'council_member_id' => $memberId,
                        'locale' => 'en',
                        'full_name' => $nameEn,
                        'position' => $this->cleanedString($row, ['en_position', 'position_en', 'en_title', 'title_en']),
                        'bio' => $this->htmlSanitizer()->sanitize(
                            (string) $this->rowValue($row, ['en_data', 'en_bio', 'en_description'], '')
                        ) ?: null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                if ($translations !== []) {
                    DB::table('council_member_translations')->insert($translations);
                }

                $this->migrationLogger()->log(
                    $module,
                    $batch,
                    'jx_councils1',
                    $sourceId,
                    'council_members',
                    $memberId,
                    'success',
                    'Imported council member.',
                    ['council_id' => $councilId, 'service_type' => $serviceType],
                );
                $imported++;
            } catch (\Throwable $e) {
                $this->reject($module, 'jx_councils1', $sourceId, 'unknown_mapping', $e->getMessage());
                $this->logSkip($module, $batch, 'jx_councils1', $sourceId, 'council_members', 'Insert failed: '.$e->getMessage());
                $skipped++;
            }
        }

        $this->command?->info("Council members import complete. Imported: {$imported}, Skipped: {$skipped}");
    }
}
