<?php

declare(strict_types=1);

namespace App\Services\Legacy;

use App\Contracts\Legacy\LegacyCleanedRowServiceInterface;
use App\Contracts\Legacy\LegacyPhaseSixPageImportServiceInterface;
use App\Contracts\Shared\SlugServiceInterface;
use App\DTOs\Legacy\LegacyCleanedRowDTO;
use App\DTOs\Legacy\LegacyPhaseSixPageImportResultDTO;
use App\Enums\PublicationStatus;
use App\Models\Legacy\LegacyContentMapping;
use App\Models\Legacy\LegacyReviewItem;
use App\Models\Page\Page;
use App\Models\Page\PageTranslation;
use App\Models\Shared\MigrationLog;
use App\Support\LegacyImport\OldDatabaseConnection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

final class LegacyPhaseSixPageImportService implements LegacyPhaseSixPageImportServiceInterface
{
    private const APPROVAL_TOKEN = 'phase6-pages';

    public function __construct(
        private readonly OldDatabaseConnection $oldDatabase,
        private readonly LegacyCleanedRowServiceInterface $cleanedRowService,
        private readonly SlugServiceInterface $slugService,
    ) {}

    public function import(bool $write = false, ?string $approval = null, ?string $batch = null): LegacyPhaseSixPageImportResultDTO
    {
        if ($write && $approval !== self::APPROVAL_TOKEN) {
            throw new InvalidArgumentException('Importing Phase 6 pages requires --approve='.self::APPROVAL_TOKEN.'.');
        }

        $batch = $batch !== null && trim($batch) !== '' ? trim($batch) : 'phase6-pages-'.now()->format('Ymd_His');
        $reviewItems = LegacyReviewItem::query()
            ->where('source_table', 'jx_site_static_pages')
            ->where('mapping_status', 'approved')
            ->where('review_status', 'mapping_already_approved')
            ->orderBy('source_id')
            ->get();
        $importableRows = 0;
        $importedRows = 0;
        $createdPages = 0;
        $createdTranslations = 0;
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

            $cleaned = $this->cleanedRowService->cleanRow('static_pages', 'jx_site_static_pages', $legacyRow);

            if (! $cleaned->canImportPublicly) {
                $skippedRows++;
                $skipReasonCounts['cleaning_blocked'] = ($skipReasonCounts['cleaning_blocked'] ?? 0) + 1;

                continue;
            }

            $importableRows++;

            if (! $write) {
                continue;
            }

            [$pageId, $translations] = $this->writePage($reviewItem, $cleaned, $batch);
            $importedRows++;
            $createdPages++;
            $createdTranslations += $translations;
            $this->markMappingImported($reviewItem, $pageId);
        }

        return new LegacyPhaseSixPageImportResultDTO(
            written: $write,
            batch: $batch,
            scannedRows: $reviewItems->count(),
            importableRows: $importableRows,
            importedRows: $importedRows,
            createdPages: $createdPages,
            createdTranslations: $createdTranslations,
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
            ->where('module', 'static_pages')
            ->where('source_table', 'jx_site_static_pages')
            ->where('source_id', $reviewItem->source_id)
            ->where('target_table', 'pages')
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
            $row = $this->oldDatabase->table('jx_site_static_pages')
                ->select(['id', 'ar_page_data', 'en_page_data', 'ar_comment', 'en_comment', 'ar_brief', 'en_brief'])
                ->where('id', $sourceId)
                ->first();
        } catch (Throwable) {
            return null;
        }

        return is_object($row) ? $row : null;
    }

    /** @return array{0: int, 1: int} */
    private function writePage(LegacyReviewItem $reviewItem, LegacyCleanedRowDTO $cleaned, string $batch): array
    {
        return DB::transaction(function () use ($reviewItem, $cleaned, $batch): array {
            $title = $this->title($cleaned, 'en', (int) $reviewItem->source_id);
            $slug = $this->slugService->generate('legacy static '.$reviewItem->source_id.' '.$title, Page::class, 'en');
            $page = Page::query()->create([
                'parent_id' => null,
                'type' => 'landing',
                'template' => 'legacy-static-page',
                'slug' => $slug,
                'status' => PublicationStatus::Draft->value,
                'sort_order' => 0,
                'is_enabled' => false,
                'show_in_breadcrumbs' => true,
                'show_in_nav' => false,
                'is_homepage_shell' => false,
                'layout_key' => 'legacy_static_page',
                'builder_schema_version' => 1,
                'content_json' => [
                    'legacy_source_table' => 'jx_site_static_pages',
                    'legacy_source_id' => $reviewItem->source_id,
                    'legacy_key' => $reviewItem->legacy_key,
                    'phase' => 'phase6',
                ],
            ]);
            $translations = 0;

            foreach (['ar', 'en'] as $locale) {
                PageTranslation::query()->create([
                    'page_id' => (int) $page->getKey(),
                    'locale' => $locale,
                    'title' => $this->title($cleaned, $locale, (int) $reviewItem->source_id),
                    'navigation_label' => $this->title($cleaned, $locale, (int) $reviewItem->source_id),
                    'headline' => $this->title($cleaned, $locale, (int) $reviewItem->source_id),
                    'excerpt' => $this->excerpt($cleaned, $locale),
                    'raw_excerpt' => $this->excerpt($cleaned, $locale),
                    'body' => $this->body($cleaned, $locale),
                    'body_payload' => [
                        'source' => 'legacy_static_page',
                        'legacy_source_id' => $reviewItem->source_id,
                    ],
                    'meta_title_fallback' => $this->title($cleaned, $locale, (int) $reviewItem->source_id),
                ]);
                $translations++;
            }

            MigrationLog::query()->create([
                'module' => 'static_pages',
                'batch_name' => $batch,
                'source_table' => 'jx_site_static_pages',
                'source_id' => $reviewItem->source_id,
                'target_table' => 'pages',
                'target_id' => (int) $page->getKey(),
                'status' => 'success',
                'message' => 'Imported Phase 6 legacy static page as disabled draft page.',
                'metadata' => [
                    'legacy_key' => $reviewItem->legacy_key,
                    'slug' => $slug,
                    'phase' => 'phase6',
                    'translations' => ['ar', 'en'],
                ],
            ]);

            return [(int) $page->getKey(), $translations];
        });
    }

    private function markMappingImported(LegacyReviewItem $reviewItem, int $targetId): void
    {
        LegacyContentMapping::query()
            ->where('source_table', 'jx_site_static_pages')
            ->where('source_id', $reviewItem->source_id)
            ->where('mapping_status', 'approved')
            ->update([
                'target_table' => 'pages',
                'target_id' => $targetId,
            ]);
    }

    private function title(LegacyCleanedRowDTO $cleaned, string $locale, int $sourceId): string
    {
        $primary = $locale === 'ar' ? ($cleaned->values['ar_brief'] ?? null) : ($cleaned->values['en_brief'] ?? null);
        $secondary = $locale === 'ar' ? ($cleaned->values['ar_comment'] ?? null) : ($cleaned->values['en_comment'] ?? null);
        $fallback = $locale === 'ar' ? ($cleaned->values['en_brief'] ?? null) : ($cleaned->values['ar_brief'] ?? null);

        foreach ([$primary, $secondary, $fallback] as $value) {
            if (is_string($value) && trim($value) !== '') {
                return mb_substr(trim($value), 0, 255);
            }
        }

        return 'Legacy static page '.$sourceId;
    }

    private function excerpt(LegacyCleanedRowDTO $cleaned, string $locale): ?string
    {
        $value = $locale === 'ar' ? ($cleaned->values['ar_comment'] ?? null) : ($cleaned->values['en_comment'] ?? null);

        return is_string($value) && trim($value) !== '' ? mb_substr(trim($value), 0, 1000) : null;
    }

    private function body(LegacyCleanedRowDTO $cleaned, string $locale): ?string
    {
        $value = $locale === 'ar' ? ($cleaned->values['ar_page_data'] ?? null) : ($cleaned->values['en_page_data'] ?? null);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
