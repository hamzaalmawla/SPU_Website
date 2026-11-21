<?php

declare(strict_types=1);

namespace Database\Seeders\LegacyImport;

use Illuminate\Support\Facades\DB;

class ImportLegacyCountriesSeeder extends BaseLegacyImportSeeder
{
    public function run(): void
    {
        $module = 'countries';

        if (! $this->shouldRunModule($module)) {
            return;
        }

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

            $code = $this->legacyCountryCode($sourceId);

            if ($code === null) {
                $this->snapshotLegacyRow(
                    $module,
                    $batch,
                    'jx_countries',
                    $sourceId,
                    null,
                    'non_country_aggregate',
                    null,
                    ['en_name' => $nameEn, 'ar_name' => $nameAr],
                );
                $this->reject($module, 'jx_countries', $sourceId, 'unknown_mapping', 'Legacy country row could not be mapped to a curated ISO country code.', [
                    'en_name' => $nameEn,
                    'ar_name' => $nameAr,
                ]);
                $this->logSkip($module, $batch, 'jx_countries', $sourceId, 'countries', 'Skipped non-country or unmapped country row.');
                $skipped++;

                continue;
            }

            if (DB::table('countries')->where('code', $code)->exists()) {
                $this->reject($module, 'jx_countries', $sourceId, 'duplicate_conflict', "Country code '{$code}' already exists.", ['code' => $code]);
                $this->logSkip($module, $batch, 'jx_countries', $sourceId, 'countries', 'Duplicate curated country code.');
                $skipped++;

                continue;
            }

            $sortOrder = $this->normalizedInteger($this->rowValue($row, ['order', 'sort_order', 'record_order'])) ?? ($sourceId ?? 0);

            try {
                $countryId = DB::table('countries')->insertGetId([
                    'code' => $code,
                    'code3' => null,
                    'phone_code' => null,
                    'currency_code' => null,
                    'is_enabled' => true,
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

    private function legacyCountryCode(?int $sourceId): ?string
    {
        return match ($sourceId) {
            1 => 'SY',
            2 => 'PS',
            3 => 'LB',
            4 => 'JO',
            5 => 'IQ',
            6 => 'AE',
            7 => 'BH',
            8 => 'DZ',
            9 => 'SA',
            10 => 'SD',
            11 => 'EG',
            12 => 'MA',
            13 => 'QA',
            14 => 'LY',
            15 => 'KW',
            16 => 'YE',
            17 => 'OM',
            18 => 'DJ',
            19 => 'SO',
            20 => 'TN',
            21 => 'MR',
            22 => 'KM',
            26 => 'FR',
            27 => 'AF',
            28 => 'US',
            29 => 'IR',
            30 => 'IT',
            31 => 'GB',
            32 => 'RO',
            33 => 'CH',
            34 => 'DE',
            35 => 'GR',
            36 => 'RU',
            37 => 'ES',
            38 => 'TR',
            39 => 'CY',
            40 => 'JP',
            41 => 'TW',
            42 => 'KR',
            43 => 'CN',
            44 => 'SG',
            45 => 'HK',
            46 => 'IN',
            47 => 'PK',
            48 => 'BD',
            49 => 'BG',
            50 => 'HU',
            51 => 'CZ',
            52 => 'FI',
            53 => 'AT',
            54 => 'BR',
            55 => 'SE',
            57 => 'PL',
            58 => 'PT',
            59 => 'KP',
            63 => 'SI',
            64 => 'LK',
            65 => 'AR',
            66 => 'MT',
            68 => 'CR',
            69 => 'EC',
            96 => 'AU',
            98 => 'DK',
            99 => 'NL',
            101 => 'TH',
            102 => 'CA',
            103 => 'BE',
            104 => 'ID',
            105 => 'NO',
            106 => 'MY',
            108 => 'AL',
            110 => 'NZ',
            111 => 'SK',
            112 => 'UA',
            113 => 'NG',
            114 => 'ZA',
            115 => 'BY',
            116 => 'MX',
            119 => 'BA',
            120 => 'UG',
            121 => 'PH',
            125 => 'CI',
            126 => 'PE',
            127 => 'IE',
            130 => 'TZ',
            131 => 'CU',
            133 => 'SN',
            134 => 'CL',
            135 => 'ET',
            136 => 'CM',
            137 => 'EE',
            default => null,
        };
    }
}
