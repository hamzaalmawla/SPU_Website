<?php

declare(strict_types=1);

namespace Database\Seeders\LegacyImport;

use Illuminate\Support\Facades\DB;

class ImportLegacyCareerLinksSeeder extends BaseLegacyImportSeeder
{
    public function run(): void
    {
        $module = 'career_links';

        if (! $this->shouldRunModule($module)) {
            return;
        }

        $batch = $this->batchName($module);
        $rows = $this->legacyRows('jx_job_sites');

        if ($rows->isEmpty()) {
            $this->command?->warn('No rows found in jx_job_sites.');

            return;
        }

        $this->command?->info("Starting career links import: {$rows->count()} rows found.");

        $imported = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $sourceId = $this->normalizedInteger($this->rowValue($row, 'id'));

            if ($this->alreadyImported('jx_job_sites', $sourceId, 'career_links')) {
                $skipped++;

                continue;
            }

            $titleAr = $this->cleanedString($row, ['ar_name', 'name_ar', 'ar_title', 'title_ar', 'name']);
            $titleEn = $this->cleanedString($row, ['en_name', 'name_en', 'en_title', 'title_en', 'name']);

            if (($titleAr === null || $titleAr === '') && ($titleEn === null || $titleEn === '')) {
                $this->reject($module, 'jx_job_sites', $sourceId, 'unknown_mapping', 'Career link has no usable title.');
                $this->logSkip($module, $batch, 'jx_job_sites', $sourceId, 'career_links', 'No title found.');
                $skipped++;

                continue;
            }

            $url = $this->cleanedString($row, ['url', 'link', 'website', 'site_url']);

            if ($url === null || $url === '') {
                $this->reject($module, 'jx_job_sites', $sourceId, 'unknown_mapping', 'Career link has no usable URL.');
                $this->logSkip($module, $batch, 'jx_job_sites', $sourceId, 'career_links', 'No URL found.');
                $skipped++;

                continue;
            }

            if ($this->htmlSanitizer()->hasUnsafeLinks('href="'.$url.'"')) {
                $this->reject($module, 'jx_job_sites', $sourceId, 'unsafe_html', 'Legacy career link URL failed safety validation.', ['url' => $url]);
                $this->logSkip($module, $batch, 'jx_job_sites', $sourceId, 'career_links', 'Skipped unsafe career link URL.', ['url' => $url]);
                $skipped++;

                continue;
            }

            if (mb_strpos($url, 'http') !== 0) {
                $url = 'https://'.$url;
            }

            $isExternal = true;

            if ($url !== '#' && (mb_strpos($url, 'spu.edu.sy') !== false || mb_strpos($url, 'localhost') !== false)) {
                $isExternal = false;
            }

            $sortOrder = $this->normalizedInteger($this->rowValue($row, ['record_order', 'sort_order', 'order'])) ?? ($sourceId ?? 0);
            $isEnabled = $this->normalizedLegacyVisibility($row, true);

            try {
                $linkId = DB::table('career_links')->insertGetId([
                    'url' => $url,
                    'is_external' => $isExternal,
                    'sort_order' => $sortOrder,
                    'is_enabled' => $isEnabled,
                    'created_at' => $this->dateNormalizer()->normalize($this->rowValue($row, ['added_date', 'updated_date', 'created_at', 'date_added', 'reg_date']))?->toDateTimeString() ?? now()->toDateTimeString(),
                    'updated_at' => now(),
                ]);

                $translations = [];

                if ($titleAr !== null && $titleAr !== '') {
                    $translations[] = [
                        'career_link_id' => $linkId,
                        'locale' => 'ar',
                        'title' => $titleAr,
                        'description' => $this->htmlSanitizer()->sanitize(
                            (string) $this->rowValue($row, ['ar_data', 'ar_description', 'description_ar'], '')
                        ) ?: null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                if ($titleEn !== null && $titleEn !== '') {
                    $translations[] = [
                        'career_link_id' => $linkId,
                        'locale' => 'en',
                        'title' => $titleEn,
                        'description' => $this->htmlSanitizer()->sanitize(
                            (string) $this->rowValue($row, ['en_data', 'en_description', 'description_en'], '')
                        ) ?: null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                if ($translations !== []) {
                    DB::table('career_link_translations')->insert($translations);
                }

                $this->migrationLogger()->log(
                    $module, $batch, 'jx_job_sites', $sourceId, 'career_links', $linkId,
                    'success', 'Imported career link.', ['url' => $url],
                );
                $imported++;
            } catch (\Throwable $e) {
                $this->reject($module, 'jx_job_sites', $sourceId, 'unknown_mapping', $e->getMessage());
                $this->logSkip($module, $batch, 'jx_job_sites', $sourceId, 'career_links', 'Insert failed: '.$e->getMessage());
                $skipped++;
            }
        }

        $this->command?->info("Career links import complete. Imported: {$imported}, Skipped: {$skipped}");
    }
}
