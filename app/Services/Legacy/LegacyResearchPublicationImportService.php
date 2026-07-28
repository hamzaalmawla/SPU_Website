<?php

declare(strict_types=1);

namespace App\Services\Legacy;

use App\Contracts\Legacy\LegacyCleanedRowServiceInterface;
use App\Contracts\Legacy\LegacyResearchPublicationImportServiceInterface;
use App\DTOs\Legacy\LegacyCleanedRowDTO;
use App\DTOs\Legacy\LegacyResearchPublicationImportResultDTO;
use App\Models\Research\ResearchPublication;
use App\Models\Research\ResearchPublicationTranslation;
use App\Models\Shared\MigrationLog;
use App\Support\LegacyImport\OldDatabaseConnection;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class LegacyResearchPublicationImportService implements LegacyResearchPublicationImportServiceInterface
{
    private const MODULE = 'research';

    private const SOURCE_TABLE = 'jx_member_categories';

    private const ATTACHMENT_TABLE = 'jx_member_items';

    private const TARGET_TABLE = 'research_publications';

    public function __construct(
        private readonly OldDatabaseConnection $oldDatabase,
        private readonly LegacyCleanedRowServiceInterface $cleanedRowService,
    ) {}

    public function import(bool $write = false, ?string $approval = null, ?string $batch = null, bool $enable = false, ?int $limit = null): LegacyResearchPublicationImportResultDTO
    {
        if ($write) {
            throw new InvalidArgumentException('jx_member_* import is blocked pending /members/ product and ownership reconciliation; approval tokens cannot enable writes.');
        }

        $batch = $batch !== null && trim($batch) !== '' ? trim($batch) : 'phase6-research-publications-'.now()->format('Ymd_His');
        $rows = $this->oldDatabase->table(self::SOURCE_TABLE)->orderBy('id')->get()->all();
        $publishedCandidateRows = 0;
        $importableRows = 0;
        $importedRows = 0;
        $skippedRows = 0;
        $attachmentReferenceRows = 0;
        $skipReasonCounts = [];
        $limit = $limit !== null ? max(1, $limit) : null;

        foreach ($rows as $row) {
            $sourceId = $this->integerValue($row, 'id');

            if ($sourceId === null) {
                $this->countSkip($skipReasonCounts, 'missing_source_id');
                $skippedRows++;

                continue;
            }

            if ($this->alreadyProcessed($sourceId)) {
                $this->countSkip($skipReasonCounts, 'already_processed');
                $skippedRows++;

                continue;
            }

            if ($this->integerValue($row, 'service_type') !== 1) {
                $this->countSkip($skipReasonCounts, 'deferred_non_publication_row');
                $skippedRows++;
                $this->writeSkip($write, $batch, $sourceId, 'Deferred non-publication research row.', [
                    'legacy_service_type' => $this->integerValue($row, 'service_type'),
                ]);

                continue;
            }

            if (! $this->visible($row)) {
                $this->countSkip($skipReasonCounts, 'not_published_on_old_site');
                $skippedRows++;
                $this->writeSkip($write, $batch, $sourceId, 'Skipped hidden legacy research publication row.');

                continue;
            }

            $publishedCandidateRows++;
            $cleaned = $this->cleanedRowService->cleanRow(self::MODULE, self::SOURCE_TABLE, $row, [
                'ar_data' => 'auto_accept_sanitized_html',
                'en_data' => 'auto_accept_sanitized_html',
                'url' => 'auto_approve_cleaned',
            ]);

            if (! $cleaned->canImportPublicly) {
                $this->countSkip($skipReasonCounts, 'cleaning_blocked');
                $skippedRows++;
                $this->writeSkip($write, $batch, $sourceId, 'Cleaning blocked this research publication row.', [
                    'blocked_fields' => $cleaned->blockedFields,
                ]);

                continue;
            }

            $titles = $this->titles($cleaned, $row);

            if ($titles === []) {
                $this->countSkip($skipReasonCounts, 'missing_title');
                $skippedRows++;
                $this->writeSkip($write, $batch, $sourceId, 'Skipped research publication row without AR/EN title.');

                continue;
            }

            $attachments = $this->attachmentReferences($sourceId);
            $attachmentReferenceRows += count($attachments);
            $importableRows++;

            if (! $write) {
                if ($limit !== null && $importableRows >= $limit) {
                    break;
                }

                continue;
            }

            $targetId = $this->writePublication($row, $cleaned, $titles, $enable);
            $this->writeSuccess($batch, $sourceId, $targetId, $row, $titles, $attachments, $enable);
            $importedRows++;

            if ($limit !== null && $importedRows >= $limit) {
                break;
            }

        }

        return new LegacyResearchPublicationImportResultDTO(
            written: $write,
            batch: $batch,
            enabledOnImport: $enable,
            scannedRows: count($rows),
            publishedCandidateRows: $publishedCandidateRows,
            importableRows: $importableRows,
            importedRows: $importedRows,
            skippedRows: $skippedRows,
            attachmentReferenceRows: $attachmentReferenceRows,
            skipReasonCounts: $skipReasonCounts,
        );
    }

    private function alreadyProcessed(int $sourceId): bool
    {
        return MigrationLog::query()
            ->where('module', self::MODULE)
            ->where('source_table', self::SOURCE_TABLE)
            ->where('source_id', $sourceId)
            ->where('target_table', self::TARGET_TABLE)
            ->whereIn('status', ['success', 'skipped'])
            ->exists();
    }

    /** @return array{ar?: string, en?: string} */
    private function titles(LegacyCleanedRowDTO $cleaned, object $row): array
    {
        $titles = [];
        $ar = $this->stringValue($cleaned->values['ar_name'] ?? $this->rawValue($row, 'ar_name'));
        $en = $this->stringValue($cleaned->values['en_name'] ?? $this->rawValue($row, 'en_name'));

        if ($ar !== null) {
            $titles['ar'] = $ar;
        }

        if ($en !== null) {
            $titles['en'] = $en;
        }

        return $titles;
    }

    /** @param array{ar?: string, en?: string} $titles */
    private function writePublication(object $row, LegacyCleanedRowDTO $cleaned, array $titles, bool $enable): int
    {
        return DB::transaction(function () use ($row, $cleaned, $titles, $enable): int {
            $publication = ResearchPublication::query()->create([
                'faculty_member_id' => null,
                'category_key' => null,
                'published_at' => $this->dateValue($row),
                'external_url' => $this->url($cleaned),
                'file_media_id' => null,
                'sort_order' => $this->integerValue($row, 'member_category_order') ?? $this->integerValue($row, 'id') ?? 0,
                'is_enabled' => $enable,
            ]);

            $this->writeTranslations($publication, $row, $cleaned, $titles);

            return (int) $publication->getKey();
        });
    }

    /** @param array{ar?: string, en?: string} $titles */
    private function writeTranslations(ResearchPublication $publication, object $row, LegacyCleanedRowDTO $cleaned, array $titles): void
    {
        $fallback = $titles['ar'] ?? $titles['en'] ?? null;

        if ($fallback === null) {
            return;
        }

        foreach (['ar', 'en'] as $locale) {
            $title = $titles[$locale] ?? $fallback;
            $briefKey = $locale.'_brief';
            $dataKey = $locale.'_data';

            ResearchPublicationTranslation::query()->create([
                'research_publication_id' => (int) $publication->getKey(),
                'locale' => $locale,
                'title' => $title,
                'excerpt' => $this->stringValue($this->rawValue($row, $briefKey)),
                'abstract' => $this->stringValue($cleaned->values[$dataKey] ?? $this->rawValue($row, $dataKey)),
                'publisher' => null,
            ]);
        }
    }

    /** @return list<array<string, mixed>> */
    private function attachmentReferences(int $sourceId): array
    {
        return $this->oldDatabase->table(self::ATTACHMENT_TABLE)
            ->where('member_category_id', $sourceId)
            ->where('service_type', 1)
            ->where('is_visible', 1)
            ->where('is_accepted', 1)
            ->orderBy('id')
            ->get()
            ->map(function (object $row): ?array {
                $paths = array_values(array_filter(array_unique([
                    $this->stringValue($this->rawValue($row, 'en_file')),
                    $this->stringValue($this->rawValue($row, 'ar_file')),
                    $this->stringValue($this->rawValue($row, 'photo')),
                ])));

                if ($paths === []) {
                    return null;
                }

                return [
                    'source_id' => $this->integerValue($row, 'id'),
                    'paths' => $paths,
                    'label_ar' => $this->stringValue($this->rawValue($row, 'ar_name')),
                    'label_en' => $this->stringValue($this->rawValue($row, 'en_name')),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function visible(object $row): bool
    {
        $value = $this->rawValue($row, 'is_visible');

        return $value === null || (string) $value === '1' || $value === 1 || $value === true;
    }

    private function dateValue(object $row): ?string
    {
        $value = $this->stringValue($this->rawValue($row, 'start_date'));

        if ($value === null || str_starts_with($value, '0000-00-00')) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function url(LegacyCleanedRowDTO $cleaned): ?string
    {
        $url = $this->stringValue($cleaned->values['url'] ?? null);

        return $url !== null && filter_var($url, FILTER_VALIDATE_URL) !== false ? $url : null;
    }

    private function integerValue(object $row, string $key): ?int
    {
        $value = $this->rawValue($row, $key);

        return is_numeric($value) ? (int) $value : null;
    }

    private function stringValue(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim(html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return $value !== '' ? $value : null;
    }

    private function rawValue(object $row, string $key): mixed
    {
        return property_exists($row, $key) ? $row->{$key} : null;
    }

    /** @param array<string, int> $skipReasonCounts */
    private function countSkip(array &$skipReasonCounts, string $reason): void
    {
        $skipReasonCounts[$reason] = ($skipReasonCounts[$reason] ?? 0) + 1;
    }

    /** @param array<string, mixed> $metadata */
    private function writeSkip(bool $write, string $batch, int $sourceId, string $message, array $metadata = []): void
    {
        if (! $write) {
            return;
        }

        MigrationLog::query()->create([
            'module' => self::MODULE,
            'batch_name' => $batch,
            'source_table' => self::SOURCE_TABLE,
            'source_id' => $sourceId,
            'target_table' => self::TARGET_TABLE,
            'target_id' => null,
            'status' => 'skipped',
            'message' => $message,
            'metadata' => $metadata + ['phase' => 'phase6', 'db_first' => true],
        ]);
    }

    /** @param array{ar?: string, en?: string} $titles @param list<array<string, mixed>> $attachments */
    private function writeSuccess(string $batch, int $sourceId, int $targetId, object $row, array $titles, array $attachments, bool $enable): void
    {
        MigrationLog::query()->create([
            'module' => self::MODULE,
            'batch_name' => $batch,
            'source_table' => self::SOURCE_TABLE,
            'source_id' => $sourceId,
            'target_table' => self::TARGET_TABLE,
            'target_id' => $targetId,
            'status' => 'success',
            'message' => 'Imported DB-first legacy research publication without media attachments.',
            'metadata' => [
                'phase' => 'phase6',
                'db_first' => true,
                'enabled_on_import' => $enable,
                'legacy_parent_id' => $this->integerValue($row, 'parent'),
                'legacy_service_type' => $this->integerValue($row, 'service_type'),
                'legacy_photo' => $this->stringValue($this->rawValue($row, 'photo')),
                'legacy_url' => $this->stringValue($this->rawValue($row, 'url')),
                'attachment_references' => $attachments,
                'locales' => array_keys($titles),
            ],
        ]);
    }
}
