<?php

declare(strict_types=1);

namespace Database\Seeders\LegacyImport;

use Illuminate\Support\Facades\DB;

class ImportLegacyCountriesSeeder extends BaseLegacyImportSeeder
{
    public function run(): void
    {
        $module = 'countries';
        $batch = $this->batchName($module);
        $rows = $this->legacyRows('jx_countries');

        if ($rows->isEmpty()) {
            $this->command?->warn('No rows found in jx_countries.');

            return;
        }

        $this->command?->info("Starting countries import: {$rows->count()} rows found.");

        $imported = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $sourceId = $this->normalizedInteger($this->rowValue($row, 'id'));

            if ($this->alreadyImported('jx_countries', $sourceId, 'countries')) {
                $skipped++;

                continue;
            }

            $nameAr = $this->cleanedString($row, ['ar_name', 'name_ar', 'name']);
            $nameEn = $this->cleanedString($row, ['en_name', 'name_en', 'name']);

            if (($nameAr === null || $nameAr === '') && ($nameEn === null || $nameEn === '')) {
                $this->reject($module, 'jx_countries', $sourceId, 'unknown_mapping', 'Country row has no usable name in either locale.', [
                    'ar_name' => $this->rowValue($row, ['ar_name', 'name_ar']),
                    'en_name' => $this->rowValue($row, ['en_name', 'name_en']),
                ]);
                $this->logSkip($module, $batch, 'jx_countries', $sourceId, 'countries', 'Skipped country with no name.');
                $skipped++;

                continue;
            }

            $code = $this->cleanedString($row, ['code', 'country_code', 'iso_code']);
            $code3 = $this->cleanedString($row, ['code3', 'iso3', 'country_code3']);
            $phoneCode = $this->cleanedString($row, ['phone_code', 'phone', 'dial_code']);
            $sortOrder = $this->normalizedInteger($this->rowValue($row, ['order', 'sort_order', 'record_order'])) ?? 0;
            $isEnabled = $this->normalizedBoolean($this->rowValue($row, ['is_active', 'active', 'is_enabled']), true);

            if ($code !== null && mb_strlen($code) > 2) {
                $code = mb_substr($code, 0, 2);
            }

            if ($code !== null && DB::table('countries')->where('code', $code)->exists()) {
                $this->reject($module, 'jx_countries', $sourceId, 'duplicate_conflict', "Country code '{$code}' already exists.", ['code' => $code]);
                $this->logSkip($module, $batch, 'jx_countries', $sourceId, 'countries', 'Duplicate country code.');
                $skipped++;

                continue;
            }

            if ($code === null || $code === '') {
                $baseId = $sourceId ?? rand(1, 1295);
                $suffix = 0;

                do {
                    $candidate = strtoupper(str_pad(base_convert((string) ($baseId + $suffix), 10, 36), 2, '0', STR_PAD_LEFT));
                    $candidateCode = mb_substr($candidate, -2, 2);
                    $suffix++;
                } while (DB::table('countries')->where('code', $candidateCode)->exists());

                $code = $candidateCode;
            }

            try {
                $countryId = DB::table('countries')->insertGetId([
                    'code' => $code,
                    'code3' => $code3,
                    'phone_code' => $phoneCode,
                    'currency_code' => $this->cleanedString($row, ['currency_code', 'currency']),
                    'is_enabled' => $isEnabled,
                    'sort_order' => $sortOrder,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $translations = [];

                if ($nameAr !== null && $nameAr !== '') {
                    $translations[] = [
                        'country_id' => $countryId,
                        'locale' => 'ar',
                        'name' => $nameAr,
                        'nationality' => $this->cleanedString($row, ['ar_nationality', 'nationality_ar']),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                if ($nameEn !== null && $nameEn !== '') {
                    $translations[] = [
                        'country_id' => $countryId,
                        'locale' => 'en',
                        'name' => $nameEn,
                        'nationality' => $this->cleanedString($row, ['en_nationality', 'nationality_en']),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                if ($translations !== []) {
                    DB::table('country_translations')->insert($translations);
                }

                $this->migrationLogger()->log(
                    $module,
                    $batch,
                    'jx_countries',
                    $sourceId,
                    'countries',
                    $countryId,
                    'success',
                    'Imported country with translations.',
                    ['code' => $code, 'name_ar' => $nameAr, 'name_en' => $nameEn],
                );
                $imported++;
            } catch (\Throwable $e) {
                $this->reject($module, 'jx_countries', $sourceId, 'unknown_mapping', $e->getMessage(), [
                    'code' => $code,
                ]);
                $this->logSkip($module, $batch, 'jx_countries', $sourceId, 'countries', 'Insert failed: '.$e->getMessage());
                $skipped++;
            }
        }

        $this->command?->info("Countries import complete. Imported: {$imported}, Skipped: {$skipped}");
    }
}
