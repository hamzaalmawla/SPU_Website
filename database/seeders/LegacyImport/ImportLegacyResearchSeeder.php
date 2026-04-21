<?php

declare(strict_types=1);

namespace Database\Seeders\LegacyImport;

use App\Models\MediaAsset;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ImportLegacyResearchSeeder extends BaseLegacyImportSeeder
{
    public function run(): void
    {
        $module = 'research';
        $batch = $this->batchName($module);
        $allCategories = $this->legacyRows('jx_member_categories');

        if ($allCategories->isEmpty()) {
            $this->command?->warn('No rows found in jx_member_categories.');

            return;
        }

        /** @var Collection<int, object> $publicationRows */
        $publicationRows = $allCategories->filter(
            fn (object $row): bool => $this->normalizedInteger($this->rowValue($row, 'service_type')) === 1
        )->values();

        if ($publicationRows->isEmpty()) {
            $this->command?->warn('No research publication rows found in jx_member_categories.');

            return;
        }

        $categoriesById = $allCategories->keyBy(fn (object $row): int|string|null => $this->rowValue($row, 'id'));

        $this->command?->info("Starting research publications import: {$publicationRows->count()} publication rows found.");

        $imported = 0;
        $skipped = 0;

        foreach ($publicationRows as $row) {
            $sourceId = $this->normalizedInteger($this->rowValue($row, 'id'));

            if ($this->alreadyImported('jx_member_categories', $sourceId, 'research_publications')) {
                $skipped++;

                continue;
            }

            $titleAr = $this->cleanedString($row, ['ar_name', 'title_ar', 'name_ar']);
            $titleEn = $this->cleanedString($row, ['en_name', 'title_en', 'name_en']);

            if (($titleAr === null || $titleAr === '') && ($titleEn === null || $titleEn === '')) {
                $this->reject($module, 'jx_member_categories', $sourceId, 'unknown_mapping', 'Research publication has no usable title.');
                $this->logSkip($module, $batch, 'jx_member_categories', $sourceId, 'research_publications', 'No title found.');
                $skipped++;

                continue;
            }

            $legacyParentId = $this->normalizedInteger($this->rowValue($row, 'parent'));
            $parentRow = $legacyParentId !== null ? $categoriesById->get($legacyParentId) : null;
            $categoryKey = $this->deriveCategoryKey($row, $parentRow, $legacyParentId);
            $publishedAt = $this->dateNormalizer()->normalize($this->rowValue($row, ['post_date', 'start_date', 'created_at', 'added_date']));
            $sortOrder = $this->normalizedInteger($this->rowValue($row, ['member_category_order', 'sort_order', 'record_order'])) ?? 0;
            $isEnabled = $this->normalizedBoolean($this->rowValue($row, ['is_visible', 'is_active', 'active', 'is_enabled']), true);

            try {
                $publicationId = DB::table('research_publications')->insertGetId([
                    'faculty_member_id' => null,
                    'category_key' => $categoryKey,
                    'published_at' => $publishedAt?->toDateString(),
                    'external_url' => $this->cleanedString($row, 'url'),
                    'file_media_id' => null,
                    'sort_order' => $sortOrder,
                    'is_enabled' => $isEnabled,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $translations = [];

                if ($titleAr !== null && $titleAr !== '') {
                    $translations[] = [
                        'research_publication_id' => $publicationId,
                        'locale' => 'ar',
                        'title' => $titleAr,
                        'excerpt' => $this->cleanedString($row, ['ar_brief', 'ar_short_description']),
                        'abstract' => $this->htmlSanitizer()->sanitize((string) $this->rowValue($row, ['ar_data', 'ar_description'], '')) ?: null,
                        'publisher' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                if ($titleEn !== null && $titleEn !== '') {
                    $translations[] = [
                        'research_publication_id' => $publicationId,
                        'locale' => 'en',
                        'title' => $titleEn,
                        'excerpt' => $this->cleanedString($row, ['en_brief', 'en_short_description']),
                        'abstract' => $this->htmlSanitizer()->sanitize((string) $this->rowValue($row, ['en_data', 'en_description'], '')) ?: null,
                        'publisher' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                if ($translations !== []) {
                    DB::table('research_publication_translations')->insert($translations);
                }

                $this->migrationLogger()->log(
                    $module,
                    $batch,
                    'jx_member_categories',
                    $sourceId,
                    'research_publications',
                    $publicationId,
                    'success',
                    'Imported research publication.',
                    ['category_key' => $categoryKey, 'legacy_parent_id' => $legacyParentId],
                );
                $imported++;
            } catch (\Throwable $e) {
                $this->reject($module, 'jx_member_categories', $sourceId, 'unknown_mapping', $e->getMessage());
                $this->logSkip($module, $batch, 'jx_member_categories', $sourceId, 'research_publications', 'Insert failed: '.$e->getMessage());
                $skipped++;
            }
        }

        $this->command?->info("Research publications import complete. Imported: {$imported}, Skipped: {$skipped}");

        $this->importResearchFiles($module, $batch);
    }

    private function importResearchFiles(string $module, string $batch): void
    {
        $rows = $this->legacyRows('jx_member_items');

        if ($rows->isEmpty()) {
            $this->command?->warn('No rows found in jx_member_items.');

            return;
        }

        $this->command?->info("Starting research file import: {$rows->count()} rows found.");

        $imported = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $sourceId = $this->normalizedInteger($this->rowValue($row, 'id'));

            if ($this->alreadyImported('jx_member_items', $sourceId, 'research_files')) {
                $skipped++;

                continue;
            }

            $legacyPublicationId = $this->normalizedInteger($this->rowValue($row, ['member_category_id', 'category_id', 'cat_id']));
            $publicationId = $legacyPublicationId !== null
                ? $this->targetIdResolver()->resolve('jx_member_categories', $legacyPublicationId, 'research_publications')
                : null;

            if ($publicationId === null) {
                $this->logSkip($module, $batch, 'jx_member_items', $sourceId, 'research_files', 'Missing parent research publication.', ['member_category_id' => $legacyPublicationId]);
                $skipped++;

                continue;
            }

            $paths = array_values(array_unique(array_filter([
                $this->cleanedString($row, 'en_file'),
                $this->cleanedString($row, 'ar_file'),
            ])));

            if ($paths === []) {
                $this->logSkip($module, $batch, 'jx_member_items', $sourceId, 'research_files', 'No file path found.');
                $skipped++;

                continue;
            }

            try {
                $firstMediaId = null;

                foreach ($paths as $index => $path) {
                    $media = MediaAsset::query()->updateOrCreate(
                        ['disk' => 'legacy', 'path' => $path],
                        [
                            'directory' => dirname($path) !== '.' ? dirname($path) : null,
                            'filename' => basename($path),
                            'original_name' => basename($path),
                            'mime_type' => $this->guessMimeType($path),
                            'extension' => strtolower((string) pathinfo($path, PATHINFO_EXTENSION)) ?: null,
                            'size_bytes' => 0,
                            'width' => null,
                            'height' => null,
                            'alt_text_ar' => null,
                            'alt_text_en' => null,
                            'caption_ar' => null,
                            'caption_en' => null,
                            'title_ar' => null,
                            'title_en' => null,
                            'webp_path' => null,
                            'srcset_json' => null,
                            'uploaded_by' => null,
                        ],
                    );

                    $mediaId = (int) $media->getKey();

                    if ($firstMediaId === null) {
                        $firstMediaId = $mediaId;
                    }

                    if (! DB::table('research_files')
                        ->where('research_publication_id', $publicationId)
                        ->where('media_asset_id', $mediaId)
                        ->exists()) {
                        DB::table('research_files')->insert([
                            'research_publication_id' => $publicationId,
                            'media_asset_id' => $mediaId,
                            'label' => count($paths) > 1 ? 'legacy-file-'.($index + 1) : null,
                            'sort_order' => $index,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }

                if ($firstMediaId !== null) {
                    DB::table('research_publications')
                        ->where('id', $publicationId)
                        ->whereNull('file_media_id')
                        ->update(['file_media_id' => $firstMediaId, 'updated_at' => now()]);
                }

                $this->migrationLogger()->log(
                    $module,
                    $batch,
                    'jx_member_items',
                    $sourceId,
                    'research_files',
                    $publicationId,
                    'success',
                    'Imported research file attachments.',
                    ['member_category_id' => $legacyPublicationId, 'attachment_count' => count($paths)],
                );
                $imported++;
            } catch (\Throwable $e) {
                $this->reject($module, 'jx_member_items', $sourceId, 'unknown_mapping', $e->getMessage());
                $this->logSkip($module, $batch, 'jx_member_items', $sourceId, 'research_files', 'Insert failed: '.$e->getMessage());
                $skipped++;
            }
        }

        $this->command?->info("Research files import complete. Imported: {$imported}, Skipped: {$skipped}");
    }

    private function deriveCategoryKey(object $row, ?object $parentRow, ?int $legacyParentId): ?string
    {
        $source = $parentRow !== null
            ? ($this->cleanedString($parentRow, ['en_name', 'ar_name']) ?? 'legacy-category-'.$legacyParentId)
            : ($this->cleanedString($row, ['type', 'category', 'publication_type']) ?? ($legacyParentId !== null ? 'legacy-category-'.$legacyParentId : null));

        if ($source === null || $source === '') {
            return null;
        }

        $slug = $this->slugFrom(['value' => $source], 'value', $source);

        return $slug !== '' ? $slug : null;
    }
}
