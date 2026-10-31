<?php

declare(strict_types=1);

namespace App\Services\Legacy;

use App\Contracts\Legacy\LegacyCleanedRowServiceInterface;
use App\Contracts\Legacy\LegacyPhaseSixMenuLinkImportServiceInterface;
use App\DTOs\Legacy\LegacyCleanedRowDTO;
use App\DTOs\Legacy\LegacyPhaseSixMenuLinkImportResultDTO;
use App\Models\Legacy\LegacyContentMapping;
use App\Models\Legacy\LegacyReviewItem;
use App\Models\Navigation\MenuItem;
use App\Models\Shared\MigrationLog;
use App\Support\LegacyImport\OldDatabaseConnection;
use App\Support\UrlSanitizer;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

final class LegacyPhaseSixMenuLinkImportService implements LegacyPhaseSixMenuLinkImportServiceInterface
{
    private const APPROVAL_TOKEN = 'phase6-menu-links';

    public function __construct(
        private readonly OldDatabaseConnection $oldDatabase,
        private readonly LegacyCleanedRowServiceInterface $cleanedRowService,
    ) {}

    public function import(bool $write = false, ?string $approval = null, ?string $batch = null): LegacyPhaseSixMenuLinkImportResultDTO
    {
        if ($write && $approval !== self::APPROVAL_TOKEN) {
            throw new InvalidArgumentException('Importing Phase 6 menu links requires --approve='.self::APPROVAL_TOKEN.'.');
        }

        $batch = $batch !== null && trim($batch) !== '' ? trim($batch) : 'phase6-menu-links-'.now()->format('Ymd_His');
        $reviewItems = LegacyReviewItem::query()
            ->where('source_table', 'jx_sites')
            ->where('mapping_status', 'approved')
            ->where('review_status', 'mapping_already_approved')
            ->orderBy('source_id')
            ->get();
        $importableRows = 0;
        $importedRows = 0;
        $createdMenuItems = 0;
        $skippedRows = 0;
        $skipReasonCounts = [];

        foreach ($reviewItems as $reviewItem) {
            $skipReason = $this->skipReasonBeforeImport($reviewItem);

            if ($skipReason !== null) {
                $skippedRows++;
                $skipReasonCounts[$skipReason] = ($skipReasonCounts[$skipReason] ?? 0) + 1;

                continue;
            }

            $legacyRow = $this->legacyRow((int) $reviewItem->source_id);

            if ($legacyRow === null) {
                $skippedRows++;
                $skipReasonCounts['missing_legacy_source_row'] = ($skipReasonCounts['missing_legacy_source_row'] ?? 0) + 1;

                continue;
            }

            $cleaned = $this->cleanedRowService->cleanRow('links', 'jx_sites', $legacyRow);

            if (! $cleaned->canImportPublicly) {
                $skippedRows++;
                $skipReasonCounts['cleaning_blocked'] = ($skipReasonCounts['cleaning_blocked'] ?? 0) + 1;

                continue;
            }

            $url = UrlSanitizer::sanitize($cleaned->values['url'] ?? null);

            if ($url === null) {
                $skippedRows++;
                $skipReasonCounts['invalid_url'] = ($skipReasonCounts['invalid_url'] ?? 0) + 1;

                continue;
            }

            $importableRows++;

            if (! $write) {
                continue;
            }

            [$targetId, $created] = $this->writeMenuItems($reviewItem, $cleaned, $url, $batch);
            $importedRows++;
            $createdMenuItems += $created;
            $this->markMappingImported($reviewItem, $targetId);
        }

        return new LegacyPhaseSixMenuLinkImportResultDTO(
            written: $write,
            batch: $batch,
            scannedRows: $reviewItems->count(),
            importableRows: $importableRows,
            importedRows: $importedRows,
            createdMenuItems: $createdMenuItems,
            skippedRows: $skippedRows,
            skipReasonCounts: $skipReasonCounts,
        );
    }

    private function skipReasonBeforeImport(LegacyReviewItem $reviewItem): ?string
    {
        if ($reviewItem->source_id === null) {
            return 'missing_source_id';
        }

        if (MigrationLog::query()
            ->where('module', 'links')
            ->where('source_table', 'jx_sites')
            ->where('source_id', $reviewItem->source_id)
            ->where('target_table', 'menu_items')
            ->where('status', 'success')
            ->exists()) {
            return 'already_imported';
        }

        if (! in_array((string) $reviewItem->file_dependency, ['', 'none'], true)) {
            return 'blocked_file_dependency';
        }

        if (is_array($reviewItem->blocked_reasons) && $reviewItem->blocked_reasons !== []) {
            return 'review_blockers_present';
        }

        return null;
    }

    private function legacyRow(int $sourceId): ?object
    {
        try {
            $row = $this->oldDatabase->table('jx_sites')
                ->select(['id', 'url', 'ar_name', 'en_name'])
                ->where('id', $sourceId)
                ->first();
        } catch (Throwable) {
            return null;
        }

        return is_object($row) ? $row : null;
    }

    /** @return array{0: int, 1: int} */
    private function writeMenuItems(LegacyReviewItem $reviewItem, LegacyCleanedRowDTO $cleaned, string $url, string $batch): array
    {
        return DB::transaction(function () use ($reviewItem, $cleaned, $url, $batch): array {
            $createdItems = [];

            foreach (['ar', 'en'] as $locale) {
                $label = $this->label($cleaned, $locale, $url);
                $item = MenuItem::query()->create([
                    'parent_id' => null,
                    'type' => 'footer',
                    'label' => $label,
                    'locale' => $locale,
                    'target_kind' => 'url',
                    'target_id' => null,
                    'url' => $url,
                    'target' => '_blank',
                    'route_name' => null,
                    'css_token' => 'legacy-link',
                    'icon' => null,
                    'group_key' => 'footer',
                    'is_enabled' => false,
                    'is_utility' => false,
                    'open_in_new_tab' => true,
                    'sort_order' => $this->nextSortOrder('footer', $locale),
                    'depth' => 0,
                ]);
                $createdItems[] = $item;

                MigrationLog::query()->create([
                    'module' => 'links',
                    'batch_name' => $batch,
                    'source_table' => 'jx_sites',
                    'source_id' => $reviewItem->source_id,
                    'target_table' => 'menu_items',
                    'target_id' => (int) $item->getKey(),
                    'status' => 'success',
                    'message' => 'Imported Phase 6 legacy menu link as disabled footer menu item.',
                    'metadata' => [
                        'locale' => $locale,
                        'legacy_key' => $reviewItem->legacy_key,
                        'url' => $url,
                        'phase' => 'phase6',
                    ],
                ]);
            }

            return [(int) $createdItems[0]->getKey(), count($createdItems)];
        });
    }

    private function markMappingImported(LegacyReviewItem $reviewItem, int $targetId): void
    {
        LegacyContentMapping::query()
            ->where('source_table', 'jx_sites')
            ->where('source_id', $reviewItem->source_id)
            ->where('mapping_status', 'approved')
            ->update([
                'target_table' => 'menu_items',
                'target_id' => $targetId,
            ]);
    }

    private function label(LegacyCleanedRowDTO $cleaned, string $locale, string $url): string
    {
        $primary = $locale === 'ar' ? ($cleaned->values['ar_name'] ?? null) : ($cleaned->values['en_name'] ?? null);
        $fallback = $locale === 'ar' ? ($cleaned->values['en_name'] ?? null) : ($cleaned->values['ar_name'] ?? null);
        $label = is_string($primary) && trim($primary) !== '' ? trim($primary) : (is_string($fallback) && trim($fallback) !== '' ? trim($fallback) : $url);

        return mb_substr($label, 0, 255);
    }

    private function nextSortOrder(string $groupKey, string $locale): int
    {
        return ((int) MenuItem::query()
            ->where('group_key', $groupKey)
            ->where('locale', $locale)
            ->whereNull('parent_id')
            ->max('sort_order')) + 1;
    }
}
