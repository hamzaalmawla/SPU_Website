<?php

declare(strict_types=1);

namespace Database\Seeders\LegacyImport;

use Illuminate\Support\Facades\DB;

class ImportLegacyCitiesSeeder extends BaseLegacyImportSeeder
{
    public function run(): void
    {
        $module = 'cities';
        $batch = $this->batchName($module);
        $rows = $this->legacyRows('jx_cities');

        if ($rows->isEmpty()) {
            $this->command?->warn('No rows found in jx_cities.');

            return;
        }

        $this->command?->info("Starting cities import: {$rows->count()} rows found.");

        $imported = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $sourceId = $this->normalizedInteger($this->rowValue($row, 'id'));

            if ($this->alreadyImported('jx_cities', $sourceId, 'cities')) {
                $skipped++;

                continue;
            }

            $nameAr = $this->cleanedString($row, ['ar_name', 'name_ar', 'name']);
            $nameEn = $this->cleanedString($row, ['en_name', 'name_en', 'name']);

            if (($nameAr === null || $nameAr === '') && ($nameEn === null || $nameEn === '')) {
                $this->reject($module, 'jx_cities', $sourceId, 'unknown_mapping', 'City row has no usable name.', [
                    'ar_name' => $this->rowValue($row, ['ar_name', 'name_ar']),
                    'en_name' => $this->rowValue($row, ['en_name', 'name_en']),
                ]);
                $this->logSkip($module, $batch, 'jx_cities', $sourceId, 'cities', 'Skipped city with no name.');
                $skipped++;

                continue;
            }

            $legacyCountryId = $this->normalizedInteger($this->rowValue($row, ['country_id', 'jx_country_id']));
            $countryId = null;

            if ($legacyCountryId !== null) {
                $countryId = $this->targetIdResolver()->resolve('jx_countries', $legacyCountryId, 'countries');
            }

            if ($countryId === null) {
                $this->reject($module, 'jx_cities', $sourceId, 'missing_parent', 'Could not resolve parent country.', [
                    'legacy_country_id' => $legacyCountryId,
                ]);
                $this->logSkip($module, $batch, 'jx_cities', $sourceId, 'cities', 'Missing parent country.');
                $skipped++;

                continue;
            }

            $code = $this->cleanedString($row, ['code', 'city_code']);
            $sortOrder = $this->normalizedInteger($this->rowValue($row, ['order', 'sort_order', 'record_order'])) ?? ($sourceId ?? 0);
            $isEnabled = $this->normalizedLegacyVisibility($row, true);

            try {
                $cityId = DB::table('cities')->insertGetId([
                    'country_id' => $countryId,
                    'code' => $code,
                    'latitude' => null,
                    'longitude' => null,
                    'is_enabled' => $isEnabled,
                    'sort_order' => $sortOrder,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $translations = [];

                if ($nameAr !== null && $nameAr !== '') {
                    $translations[] = [
                        'city_id' => $cityId,
                        'locale' => 'ar',
                        'name' => $nameAr,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                if ($nameEn !== null && $nameEn !== '') {
                    $translations[] = [
                        'city_id' => $cityId,
                        'locale' => 'en',
                        'name' => $nameEn,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                if ($translations !== []) {
                    DB::table('city_translations')->insert($translations);
                }

                $this->migrationLogger()->log(
                    $module,
                    $batch,
                    'jx_cities',
                    $sourceId,
                    'cities',
                    $cityId,
                    'success',
                    'Imported city with translations.',
                    ['name_ar' => $nameAr, 'name_en' => $nameEn, 'country_id' => $countryId],
                );
                $imported++;
            } catch (\Throwable $e) {
                $this->reject($module, 'jx_cities', $sourceId, 'unknown_mapping', $e->getMessage());
                $this->logSkip($module, $batch, 'jx_cities', $sourceId, 'cities', 'Insert failed: '.$e->getMessage());
                $skipped++;
            }
        }

        $this->command?->info("Cities import complete. Imported: {$imported}, Skipped: {$skipped}");
    }
}
