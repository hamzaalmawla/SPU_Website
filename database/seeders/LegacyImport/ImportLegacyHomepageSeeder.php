<?php

declare(strict_types=1);

namespace Database\Seeders\LegacyImport;

use App\Models\HomepageSection;
use App\Models\HomepageSectionTranslation;
use App\Models\MediaAsset;

class ImportLegacyHomepageSeeder extends BaseLegacyImportSeeder
{
    public function run(): void
    {
        $this->importHomePhotos();
        $this->importLogos();
    }

    private function importHomePhotos(): void
    {
        $module = 'homepage';
        $batch = $this->batchName($module.'-photos');

        foreach ($this->legacyRows('jx_home_photos') as $row) {
            $sourceId = $this->normalizedInteger($this->rowValue($row, 'id'));

            if ($this->alreadyImported('jx_home_photos', $sourceId, 'media_assets')) {
                continue;
            }

            $path = $this->cleanedString($row, ['photo', 'image', 'img', 'path', 'file']);

            if ($path === null) {
                $this->logSkip($module, $batch, 'jx_home_photos', $sourceId, 'media_assets', 'Skipped homepage photo without a usable path.');

                continue;
            }

            $media = $this->importMediaAsset($path, $row, null);
            $sectionKey = $this->resolveHomepageSectionKey($this->cleanedString($row, ['section_key', 'section', 'block_name', 'position']));
            $locale = $this->normalizedLocale($row);

            if ($sectionKey !== null && $locale !== null) {
                $section = HomepageSection::query()->where('key', $sectionKey)->first();

                if ($section instanceof HomepageSection) {
                    $translation = HomepageSectionTranslation::query()->firstOrNew([
                        'section_id' => (int) $section->getKey(),
                        'locale' => $locale,
                    ]);
                    $payload = is_array($translation->payload_json) ? $translation->payload_json : [];
                    $legacyMedia = is_array($payload['legacyImportedMedia'] ?? null) ? $payload['legacyImportedMedia'] : [];
                    $legacyMedia[] = [
                        'media_asset_id' => (int) $media->getKey(),
                        'path' => $media->path,
                        'caption' => $this->cleanedString($row, ['caption', 'title', 'name']),
                    ];
                    $payload['legacyImportedMedia'] = $legacyMedia;
                    $translation->payload_json = $payload;
                    $translation->save();
                }
            }

            $this->migrationLogger()->log($module, $batch, 'jx_home_photos', $sourceId, 'media_assets', (int) $media->getKey(), 'success', 'Imported legacy homepage media row.', ['section_key' => $sectionKey, 'locale' => $locale]);
        }
    }

    private function importLogos(): void
    {
        $module = 'homepage';
        $batch = $this->batchName($module.'-logos');

        foreach ($this->legacyRows('jx_logos') as $row) {
            $sourceId = $this->normalizedInteger($this->rowValue($row, 'id'));

            if ($this->alreadyImported('jx_logos', $sourceId, 'media_assets')) {
                continue;
            }

            $path = $this->cleanedString($row, ['logo', 'image', 'img', 'path', 'file']);

            if ($path === null) {
                $this->logSkip($module, $batch, 'jx_logos', $sourceId, 'media_assets', 'Skipped logo row without a usable path.');

                continue;
            }

            $media = $this->importMediaAsset($path, $row, 'logo');

            $this->migrationLogger()->log($module, $batch, 'jx_logos', $sourceId, 'media_assets', (int) $media->getKey(), 'success', 'Imported legacy logo asset.', null);
        }
    }

    private function importMediaAsset(string $path, object $row, ?string $titleFallback): MediaAsset
    {
        $filename = basename($path);
        $directory = dirname($path);

        return MediaAsset::query()->updateOrCreate(
            ['disk' => 'legacy', 'path' => $path],
            [
                'directory' => $directory !== '.' ? $directory : null,
                'filename' => $filename,
                'original_name' => $this->cleanedString($row, ['original_name', 'title', 'name']) ?? $filename,
                'mime_type' => $this->guessMimeType($path),
                'extension' => strtolower((string) pathinfo($path, PATHINFO_EXTENSION)) ?: null,
                'size_bytes' => $this->normalizedInteger($this->rowValue($row, ['size', 'file_size'])) ?? 0,
                'width' => $this->normalizedInteger($this->rowValue($row, 'width')),
                'height' => $this->normalizedInteger($this->rowValue($row, 'height')),
                'alt_text_ar' => $this->cleanedString($row, ['alt_ar', 'alt', 'title_ar']),
                'alt_text_en' => $this->cleanedString($row, ['alt_en', 'title_en']),
                'caption_ar' => $this->cleanedString($row, ['caption_ar', 'caption']),
                'caption_en' => $this->cleanedString($row, ['caption_en']),
                'title_ar' => $this->cleanedString($row, ['title_ar', 'title']) ?? $titleFallback,
                'title_en' => $this->cleanedString($row, ['title_en']),
                'webp_path' => null,
                'srcset_json' => null,
                'uploaded_by' => null,
            ],
        );
    }

    private function resolveHomepageSectionKey(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = strtolower($value);

        return match ($normalized) {
            'hero', 'slider', 'banner' => 'hero',
            'hero_stats', 'top_stats' => 'hero_stats',
            'academic_faculties', 'faculties' => 'academic_faculties',
            'achievements', 'achievements_highlights' => 'achievements_highlights',
            'news', 'university_news' => 'university_news',
            'research', 'research_studies' => 'research_studies',
            'events', 'events_activities' => 'events_activities',
            'medical', 'medical_facilities_services' => 'medical_facilities_services',
            'bottom_stats' => 'bottom_stats',
            'footer' => 'footer',
            default => null,
        };
    }
}
