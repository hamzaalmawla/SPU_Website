<?php

declare(strict_types=1);

namespace App\Services\Legacy;

use App\Contracts\Legacy\LegacyCleanedRowServiceInterface;
use App\Contracts\Legacy\LegacyResearchMetadataExtractorInterface;
use App\Contracts\Legacy\LegacyResearchPublicationImportServiceInterface;
use App\DTOs\Legacy\LegacyCleanedRowDTO;
use App\DTOs\Legacy\LegacyResearchMetadataDTO;
use App\DTOs\Legacy\LegacyResearchPublicationImportResultDTO;
use App\Models\Person\FacultyMember;
use App\Models\Research\LegacyResearchFileReference;
use App\Models\Research\ResearchPublication;
use App\Models\Research\ResearchPublicationTranslation;
use App\Models\Shared\MigrationLog;
use App\Support\LegacyImport\OldDatabaseConnection;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class LegacyResearchPublicationImportService implements LegacyResearchPublicationImportServiceInterface
{
    private const MODULE = 'research';

    private const SOURCE_TABLE = 'jx_member_categories';

    private const ATTACHMENT_TABLE = 'jx_member_items';

    private const TARGET_TABLE = 'research_publications';

    private const APPROVAL_TOKEN = 'legacy-research-publications-import';

    public function __construct(
        private readonly OldDatabaseConnection $oldDatabase,
        private readonly LegacyCleanedRowServiceInterface $cleanedRowService,
        private readonly LegacyResearchMetadataExtractorInterface $metadataExtractor,
    ) {}

    public function import(bool $write = false, ?string $approval = null, ?string $batch = null, bool $enable = false, ?int $limit = null): LegacyResearchPublicationImportResultDTO
    {
        if ($write && $approval !== self::APPROVAL_TOKEN) {
            throw new InvalidArgumentException('Importing reviewed legacy research publications requires --approve='.self::APPROVAL_TOKEN.'.');
        }
        if ($write && $enable) {
            throw new InvalidArgumentException('Legacy research publications must be imported as disabled review records.');
        }

        $batch = $batch !== null && trim($batch) !== '' ? trim($batch) : 'phase6-research-publications-'.now()->format('Ymd_His');
        $rows = $this->oldDatabase->table(self::SOURCE_TABLE)->orderBy('id')->get()->all();
        $publishedCandidateRows = 0;
        $importableRows = 0;
        $importedRows = 0;
        $skippedRows = 0;
        $attachmentReferenceRows = 0;
        $skipReasonCounts = [];
        $coverage = [
            'authors' => 0,
            'citation' => 0,
            'publisher' => 0,
            'doi' => 0,
            'publication_year' => 0,
            'journal_rank' => 0,
            'keywords' => 0,
            'linked_owner' => 0,
            'duplicate_review' => 0,
        ];
        $limit = $limit !== null ? max(1, $limit) : null;
        $titleCounts = $this->titleCounts($rows);
        [$ownerSources, $ownerMappings] = $this->ownerEvidence($rows);

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
            $metadata = $this->metadataByLocale($row, $cleaned, $titles);
            $duplicateReview = $this->hasDuplicateTitle($titles, $titleCounts);
            $ownerId = $this->integerValue($row, 'parent');
            $ownerSource = $ownerSources[$ownerId ?? 0] ?? 'missing';
            $facultyMemberId = $ownerSource === 'jx_councils_only' ? ($ownerMappings[$ownerId ?? 0] ?? null) : null;
            $this->countCoverage($coverage, $metadata, $facultyMemberId, $duplicateReview);
            $attachmentReferenceRows += count($attachments);
            $importableRows++;

            if (! $write) {
                if ($limit !== null && $importableRows >= $limit) {
                    break;
                }

                continue;
            }

            $targetId = $this->writePublication(
                row: $row,
                cleaned: $cleaned,
                titles: $titles,
                metadata: $metadata,
                attachments: $attachments,
                facultyMemberId: $facultyMemberId,
                ownerSource: $ownerSource,
                duplicateReview: $duplicateReview,
            );
            $this->writeSuccess($batch, $sourceId, $targetId, $row, $titles, $metadata, $attachments, $facultyMemberId, $ownerSource, $duplicateReview);
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
            metadataCoverage: $coverage,
            skipReasonCounts: $skipReasonCounts,
        );
    }

    private function alreadyProcessed(int $sourceId): bool
    {
        if (ResearchPublication::query()
            ->where('legacy_source_table', self::SOURCE_TABLE)
            ->where('legacy_source_id', $sourceId)
            ->exists()) {
            return true;
        }

        return MigrationLog::query()
            ->where('module', self::MODULE)
            ->where('source_table', self::SOURCE_TABLE)
            ->where('source_id', $sourceId)
            ->where('target_table', self::TARGET_TABLE)
            ->where('status', 'skipped')
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

    /**
     * @param  array{ar?: string, en?: string}  $titles
     * @param  array<string, LegacyResearchMetadataDTO>  $metadata
     * @param  list<array<string, mixed>>  $attachments
     */
    private function writePublication(
        object $row,
        LegacyCleanedRowDTO $cleaned,
        array $titles,
        array $metadata,
        array $attachments,
        ?int $facultyMemberId,
        string $ownerSource,
        bool $duplicateReview,
    ): int {
        return DB::transaction(function () use ($row, $cleaned, $titles, $metadata, $attachments, $facultyMemberId, $ownerSource, $duplicateReview): int {
            $sourceId = $this->integerValue($row, 'id');
            $baseMetadata = $metadata['en'] ?? $metadata['ar'] ?? null;
            $publication = ResearchPublication::query()->create([
                'faculty_member_id' => $facultyMemberId,
                'category_key' => null,
                'published_at' => $this->dateValue($row),
                'publication_year' => $baseMetadata?->publicationYear,
                'doi' => $this->firstMetadataValue($metadata, 'doi'),
                'journal_rank' => $this->firstMetadataValue($metadata, 'journalRank'),
                'legacy_source_table' => self::SOURCE_TABLE,
                'legacy_source_id' => $sourceId,
                'legacy_owner_id' => $this->integerValue($row, 'parent'),
                'legacy_owner_source' => $ownerSource,
                'extraction_status' => $duplicateReview ? 'duplicate_review' : 'metadata_review',
                'external_url' => $this->url($cleaned),
                'file_media_id' => null,
                'sort_order' => $this->integerValue($row, 'member_category_order') ?? $this->integerValue($row, 'id') ?? 0,
                'is_enabled' => false,
            ]);

            $this->writeTranslations($publication, $row, $cleaned, $titles, $metadata);
            $this->writeAttachmentReferences($publication, $attachments);

            return (int) $publication->getKey();
        });
    }

    /** @param array{ar?: string, en?: string} $titles @param array<string, LegacyResearchMetadataDTO> $metadata */
    private function writeTranslations(ResearchPublication $publication, object $row, LegacyCleanedRowDTO $cleaned, array $titles, array $metadata): void
    {
        foreach ($titles as $locale => $title) {
            $briefKey = $locale.'_brief';
            $dataKey = $locale.'_data';
            $sourceBody = $this->stringValue($cleaned->values[$dataKey] ?? $this->rawValue($row, $dataKey));
            $extracted = $metadata[$locale] ?? null;
            $abstract = $extracted?->abstract ?? $sourceBody;
            $excerpt = $this->stringValue($this->rawValue($row, $briefKey))
                ?? ($abstract !== null ? Str::limit(trim(strip_tags($abstract)), 260) : null);

            ResearchPublicationTranslation::query()->create([
                'research_publication_id' => (int) $publication->getKey(),
                'locale' => $locale,
                'title' => $title,
                'authors' => $extracted?->authors,
                'excerpt' => $excerpt,
                'abstract' => $abstract,
                'publisher' => $extracted?->publisher,
                'citation' => $extracted?->citation,
                'keywords' => $extracted?->keywords ?? [],
            ]);
        }
    }

    /** @param list<array<string, mixed>> $attachments */
    private function writeAttachmentReferences(ResearchPublication $publication, array $attachments): void
    {
        foreach ($attachments as $index => $attachment) {
            foreach ((array) ($attachment['paths'] ?? []) as $path) {
                LegacyResearchFileReference::query()->create([
                    'research_publication_id' => (int) $publication->getKey(),
                    'legacy_source_table' => self::ATTACHMENT_TABLE,
                    'legacy_source_id' => (int) ($attachment['source_id'] ?? 0),
                    'legacy_path' => (string) $path,
                    'label_ar' => $attachment['label_ar'] ?? null,
                    'label_en' => $attachment['label_en'] ?? null,
                    'sort_order' => $index,
                    'status' => 'deferred',
                ]);
            }
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

    /** @param array{ar?: string, en?: string} $titles @return array<string, LegacyResearchMetadataDTO> */
    private function metadataByLocale(object $row, LegacyCleanedRowDTO $cleaned, array $titles): array
    {
        $metadata = [];
        foreach ($titles as $locale => $title) {
            $dataKey = $locale.'_data';
            $metadata[$locale] = $this->metadataExtractor->extract(
                $this->stringValue($cleaned->values[$dataKey] ?? $this->rawValue($row, $dataKey)),
                $this->stringValue($this->rawValue($row, $locale.'_keywords')),
                $title,
            );
        }

        return $metadata;
    }

    /** @param array<int, object> $rows @return array<string, int> */
    private function titleCounts(array $rows): array
    {
        $counts = [];
        foreach ($rows as $row) {
            if ($this->integerValue($row, 'service_type') !== 1 || ! $this->visible($row)) {
                continue;
            }
            foreach (['ar', 'en'] as $locale) {
                $title = $this->stringValue($this->rawValue($row, $locale.'_name'));
                if ($title === null) {
                    continue;
                }
                $key = $locale.'|'.$this->normalizedTitle($title);
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }
        }

        return $counts;
    }

    /** @param array{ar?: string, en?: string} $titles @param array<string, int> $titleCounts */
    private function hasDuplicateTitle(array $titles, array $titleCounts): bool
    {
        foreach ($titles as $locale => $title) {
            if (($titleCounts[$locale.'|'.$this->normalizedTitle($title)] ?? 0) > 1) {
                return true;
            }
        }

        return false;
    }

    private function normalizedTitle(string $title): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', strip_tags($title))));
    }

    /** @param array<int, object> $rows @return array{array<int, string>, array<int, int>} */
    private function ownerEvidence(array $rows): array
    {
        $ownerIds = array_values(array_unique(array_filter(array_map(
            fn (object $row): ?int => $this->integerValue($row, 'parent'),
            $rows,
        ))));
        $councils = $this->ownerIds('jx_councils', $ownerIds);
        $councils1 = $this->ownerIds('jx_councils1', $ownerIds);
        $sources = [];

        foreach ($ownerIds as $ownerId) {
            $sources[$ownerId] = isset($councils[$ownerId], $councils1[$ownerId])
                ? 'both_sources'
                : (isset($councils[$ownerId])
                    ? 'jx_councils_only'
                    : (isset($councils1[$ownerId]) ? 'jx_councils1_only' : 'missing'));
        }

        $mappedTargets = MigrationLog::query()
            ->where('source_table', 'jx_councils')
            ->where('target_table', 'faculty_members')
            ->where('status', 'success')
            ->whereIn('source_id', $ownerIds)
            ->pluck('target_id', 'source_id')
            ->map(fn (mixed $targetId): int => (int) $targetId)
            ->all();
        $validTargets = FacultyMember::query()->whereIn('id', array_values($mappedTargets))->pluck('id')->flip()->all();
        $mappings = [];

        foreach ($mappedTargets as $sourceId => $targetId) {
            if (isset($validTargets[$targetId])) {
                $mappings[(int) $sourceId] = $targetId;
            }
        }

        return [$sources, $mappings];
    }

    /** @param list<int> $ownerIds @return array<int, true> */
    private function ownerIds(string $table, array $ownerIds): array
    {
        if ($ownerIds === [] || ! $this->oldDatabase->schema()->hasTable($table)) {
            return [];
        }

        return $this->oldDatabase->table($table)
            ->whereIn('id', $ownerIds)
            ->pluck('id')
            ->mapWithKeys(static fn (mixed $id): array => [(int) $id => true])
            ->all();
    }

    /** @param array<string, int> $coverage @param array<string, LegacyResearchMetadataDTO> $metadata */
    private function countCoverage(array &$coverage, array $metadata, ?int $facultyMemberId, bool $duplicateReview): void
    {
        foreach (['authors', 'citation', 'publisher', 'doi', 'publicationYear', 'journalRank', 'keywords'] as $field) {
            $covered = collect($metadata)->contains(function (LegacyResearchMetadataDTO $item) use ($field): bool {
                $value = $item->{$field};

                return is_array($value) ? $value !== [] : $value !== null && $value !== '';
            });
            if ($covered) {
                $key = match ($field) {
                    'publicationYear' => 'publication_year',
                    'journalRank' => 'journal_rank',
                    default => $field,
                };
                $coverage[$key]++;
            }
        }
        if ($facultyMemberId !== null) {
            $coverage['linked_owner']++;
        }
        if ($duplicateReview) {
            $coverage['duplicate_review']++;
        }
    }

    /** @param array<string, LegacyResearchMetadataDTO> $metadata */
    private function firstMetadataValue(array $metadata, string $field): ?string
    {
        foreach (['en', 'ar'] as $locale) {
            $value = $metadata[$locale]?->{$field} ?? null;
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
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

    /**
     * @param  array{ar?: string, en?: string}  $titles
     * @param  array<string, LegacyResearchMetadataDTO>  $metadata
     * @param  list<array<string, mixed>>  $attachments
     */
    private function writeSuccess(
        string $batch,
        int $sourceId,
        int $targetId,
        object $row,
        array $titles,
        array $metadata,
        array $attachments,
        ?int $facultyMemberId,
        string $ownerSource,
        bool $duplicateReview,
    ): void {
        $metadataSummary = collect($metadata)->map(fn (LegacyResearchMetadataDTO $item): array => [
            'authors' => $item->authors !== null,
            'citation' => $item->citation !== null,
            'abstract' => $item->abstract !== null,
            'publisher' => $item->publisher !== null,
            'doi' => $item->doi,
            'publication_year' => $item->publicationYear,
            'journal_rank' => $item->journalRank,
            'keyword_count' => count($item->keywords),
            'evidence' => $item->evidence,
        ])->all();
        $sourceHash = hash('sha256', (string) json_encode([
            'id' => $sourceId,
            'parent' => $this->integerValue($row, 'parent'),
            'titles' => $titles,
            'ar_data' => $this->stringValue($this->rawValue($row, 'ar_data')),
            'en_data' => $this->stringValue($this->rawValue($row, 'en_data')),
            'ar_keywords' => $this->stringValue($this->rawValue($row, 'ar_keywords')),
            'en_keywords' => $this->stringValue($this->rawValue($row, 'en_keywords')),
            'attachments' => $attachments,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        MigrationLog::query()->create([
            'module' => self::MODULE,
            'batch_name' => $batch,
            'source_table' => self::SOURCE_TABLE,
            'source_id' => $sourceId,
            'target_table' => self::TARGET_TABLE,
            'target_id' => $targetId,
            'status' => 'success',
            'message' => 'Imported structured legacy research publication as a disabled review record.',
            'metadata' => [
                'phase' => 'phase6',
                'db_first' => true,
                'enabled_on_import' => false,
                'legacy_parent_id' => $this->integerValue($row, 'parent'),
                'legacy_owner_source' => $ownerSource,
                'faculty_member_id' => $facultyMemberId,
                'legacy_service_type' => $this->integerValue($row, 'service_type'),
                'legacy_photo' => $this->stringValue($this->rawValue($row, 'photo')),
                'legacy_url' => $this->stringValue($this->rawValue($row, 'url')),
                'attachment_references' => $attachments,
                'locales' => array_keys($titles),
                'metadata_coverage' => $metadataSummary,
                'duplicate_review' => $duplicateReview,
                'source_sha256' => $sourceHash,
            ],
        ]);
    }
}
