<?php

declare(strict_types=1);

namespace Database\Seeders\LegacyImport;

use App\Models\MediaAsset;
use App\Models\MenuItem;
use App\Models\MigrationLog;

class ImportLegacyLinksSeeder extends BaseLegacyImportSeeder
{
    public function run(): void
    {
        $this->importDocs();
        $this->importSites();
    }

    private function importDocs(): void
    {
        $module = 'links';
        $batch = $this->batchName($module.'-docs');

        foreach ($this->legacyRows('jx_docs') as $row) {
            $sourceId = $this->normalizedInteger($this->rowValue($row, 'id'));

            if ($this->alreadyImported('jx_docs', $sourceId, 'media_assets')) {
                continue;
            }

            $path = $this->cleanedString($row, ['file', 'path', 'url', 'doc', 'attachment']);

            if ($path === null) {
                $this->logSkip($module, $batch, 'jx_docs', $sourceId, 'media_assets', 'Skipped doc row without a path.');

                continue;
            }

            $media = MediaAsset::query()->updateOrCreate(
                ['disk' => 'legacy', 'path' => $path],
                [
                    'directory' => dirname($path) !== '.' ? dirname($path) : null,
                    'filename' => basename($path),
                    'original_name' => $this->cleanedString($row, ['title', 'name']) ?? basename($path),
                    'mime_type' => $this->guessMimeType($path),
                    'extension' => strtolower((string) pathinfo($path, PATHINFO_EXTENSION)) ?: null,
                    'size_bytes' => $this->normalizedInteger($this->rowValue($row, ['size', 'file_size'])) ?? 0,
                    'width' => null,
                    'height' => null,
                    'alt_text_ar' => null,
                    'alt_text_en' => null,
                    'caption_ar' => null,
                    'caption_en' => null,
                    'title_ar' => $this->cleanedString($row, ['title_ar', 'title']),
                    'title_en' => $this->cleanedString($row, ['title_en']),
                    'webp_path' => null,
                    'srcset_json' => null,
                    'uploaded_by' => null,
                ],
            );

            $this->migrationLogger()->log($module, $batch, 'jx_docs', $sourceId, 'media_assets', (int) $media->getKey(), 'success', 'Imported legacy document asset.', null);
        }
    }

    private function importSites(): void
    {
        $module = 'links';
        $batch = $this->batchName($module.'-sites');

        foreach ($this->legacyRows('jx_sites') as $row) {
            $sourceId = $this->normalizedInteger($this->rowValue($row, 'id'));
            $url = $this->cleanedString($row, ['url', 'link', 'path']);
            $labels = [
                'ar' => $this->cleanedString($row, 'ar_name'),
                'en' => $this->cleanedString($row, 'en_name'),
            ];

            if ($url === null || $this->availableLabels($labels) === []) {
                $this->logSkip($module, $batch, 'jx_sites', $sourceId, 'menu_items', 'Skipped site link without label or URL.');

                continue;
            }

            if ($this->htmlSanitizer()->hasUnsafeLinks('href="'.$url.'"')) {
                $this->reject($module, 'jx_sites', $sourceId, 'unsafe_html', 'Legacy link URL failed safety validation.', ['url' => $url]);
                $this->logSkip($module, $batch, 'jx_sites', $sourceId, 'menu_items', 'Skipped unsafe link URL.', ['url' => $url]);

                continue;
            }

            $group = match (strtolower((string) ($this->cleanedString($row, ['group_key', 'group', 'section', 'type']) ?? ''))) {
                'header', 'main', 'primary' => 'header',
                'utility', 'quick', 'top' => 'utility',
                default => 'footer',
            };

            foreach ($this->availableLabels($labels) as $locale => $label) {
                if ($this->siteLocaleAlreadyImported($sourceId, $locale)) {
                    continue;
                }

                $menuItem = MenuItem::query()->updateOrCreate(
                    ['type' => $group, 'locale' => $locale, 'label' => $label, 'url' => $url],
                    [
                        'parent_id' => null,
                        'target_kind' => 'url',
                        'target_id' => null,
                        'url' => $url,
                        'target' => $this->normalizedBoolean($this->rowValue($row, ['new_tab', 'open_in_new_tab']), false) ? '_blank' : null,
                        'route_name' => null,
                        'css_token' => null,
                        'icon' => null,
                        'group_key' => $group,
                        'is_enabled' => $this->normalizedBoolean($this->rowValue($row, 'is_visible'), true),
                        'is_utility' => $group === 'utility',
                        'open_in_new_tab' => $this->normalizedBoolean($this->rowValue($row, ['new_tab', 'open_in_new_tab']), false),
                        'sort_order' => $this->normalizedInteger($this->rowValue($row, ['record_order', 'sort_order', 'order'])) ?? 0,
                        'depth' => 0,
                    ],
                );

                $this->migrationLogger()->log($module, $batch, 'jx_sites', $sourceId, 'menu_items', (int) $menuItem->getKey(), 'success', 'Imported legacy link into menu items.', ['group' => $group, 'locale' => $locale]);
            }
        }
    }

    /**
     * @param  array<string, ?string>  $labels
     * @return array<string, string>
     */
    private function availableLabels(array $labels): array
    {
        return array_filter($labels, static fn (?string $label): bool => $label !== null && $label !== '');
    }

    private function siteLocaleAlreadyImported(?int $sourceId, string $locale): bool
    {
        if ($sourceId === null) {
            return false;
        }

        return MigrationLog::query()
            ->where('source_table', 'jx_sites')
            ->where('source_id', $sourceId)
            ->where('target_table', 'menu_items')
            ->where('status', 'success')
            ->where('metadata->locale', $locale)
            ->exists();
    }
}
