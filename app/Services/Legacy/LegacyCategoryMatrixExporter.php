<?php

declare(strict_types=1);

namespace App\Services\Legacy;

use App\Contracts\Legacy\LegacyCategoryMatrixExporterInterface;
use App\DTOs\Legacy\LegacyCategoryMatrixExportResultDTO;
use App\Support\LegacyImport\OldDatabaseConnection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class LegacyCategoryMatrixExporter implements LegacyCategoryMatrixExporterInterface
{
    private const SOURCE_TABLE = 'jx_categories';

    /** @var array<int, string> */
    private const SOURCE_COLUMNS = [
        'id', 'parent', 'service_type', 'category_order', 'ar_name', 'en_name',
        'is_visible', 'is_link', 'url', 'photo', 'start_date', 'end_date',
    ];

    /** @var array<int, string> */
    private const CSV_HEADERS = [
        'source_table', 'id', 'parent', 'service_type', 'service_suffix', 'service_semantic',
        'subsite', 'site_id', 'category_order', 'ar_name', 'en_name', 'is_visible', 'is_hidden',
        'is_link', 'external_link_review', 'url', 'photo', 'photo_dependency', 'start_date',
        'end_date', 'has_parent', 'parent_exists', 'is_orphan', 'legacy_ar_url_candidate',
        'legacy_en_url_candidate', 'migration_log_success_count', 'migration_log_target_tables',
        'migration_log_target_ids', 'news_article_match_count', 'news_article_ids',
        'news_article_slugs', 'news_article_statuses', 'is_mapped', 'decision_status',
    ];

    public function __construct(
        private readonly OldDatabaseConnection $oldDatabase,
    ) {}

    public function export(
        string $disk = 'local',
        string $directory = 'legacy-import-exports/category-matrix',
    ): LegacyCategoryMatrixExportResultDTO {
        $directory = trim($directory, '/');
        $directory = $directory !== '' ? $directory : 'legacy-import-exports/category-matrix';
        $warnings = [];
        $sourceRows = [];

        try {
            $schema = $this->oldDatabase->schema();

            if (! $schema->hasTable(self::SOURCE_TABLE)) {
                $warnings[] = 'Missing legacy source table [jx_categories].';
            } else {
                $available = $schema->getColumnListing(self::SOURCE_TABLE);
                $selected = array_values(array_intersect(self::SOURCE_COLUMNS, $available));

                if (! in_array('id', $selected, true)) {
                    $warnings[] = 'Missing required legacy source column [jx_categories.id].';
                } else {
                    $missing = array_values(array_diff(self::SOURCE_COLUMNS, $selected));

                    if ($missing !== []) {
                        $warnings[] = 'Missing legacy metadata columns: '.implode(', ', $missing).'.';
                    }

                    $sourceRows = $this->oldDatabase->table(self::SOURCE_TABLE)
                        ->select($selected)
                        ->orderBy('id')
                        ->get()
                        ->all();
                }
            }
        } catch (Throwable $exception) {
            $warnings[] = 'Could not read legacy category metadata: '.$exception->getMessage();
        }

        $sourceIds = [];

        foreach ($sourceRows as $sourceRow) {
            $sourceIds[(int) $sourceRow->id] = true;
        }

        $migrationMappings = $this->migrationMappings(array_keys($sourceIds));
        $newsMappings = $this->newsMappings(array_keys($sourceIds));
        $rows = [];

        foreach ($sourceRows as $sourceRow) {
            $rows[] = $this->matrixRow($sourceRow, $sourceIds, $migrationMappings, $newsMappings);
        }

        $serviceCounts = $this->counts($rows, 'service_semantic');
        $subsiteCounts = $this->counts($rows, 'subsite', 'unknown');
        $knownSubsiteRows = count(array_filter($rows, static fn (array $row): bool => $row['subsite'] !== null));
        $hiddenRows = count(array_filter($rows, static fn (array $row): bool => $row['is_hidden'] === 1));
        $linkReviewRows = count(array_filter($rows, static fn (array $row): bool => $row['external_link_review'] === 1));
        $orphanRows = count(array_filter($rows, static fn (array $row): bool => $row['is_orphan'] === 1));
        $mappedRows = count(array_filter($rows, static fn (array $row): bool => $row['is_mapped'] === 1));
        $stamp = now()->format('Ymd_His');
        $basePath = $directory.'/'.$stamp.'_jx_categories_matrix';
        $paths = [$basePath.'.csv', $basePath.'.json'];
        $summary = [
            'source_rows' => count($sourceRows),
            'output_rows' => count($rows),
            'known_subsite_rows' => $knownSubsiteRows,
            'unknown_subsite_rows' => count($rows) - $knownSubsiteRows,
            'hidden_rows' => $hiddenRows,
            'link_review_rows' => $linkReviewRows,
            'orphan_rows' => $orphanRows,
            'mapped_rows' => $mappedRows,
            'service_counts' => $serviceCounts,
            'subsite_counts' => $subsiteCounts,
        ];

        Storage::disk($disk)->put($paths[0], $this->csv($rows));
        Storage::disk($disk)->put($paths[1], (string) json_encode([
            'generated_at' => now()->toIso8601String(),
            'source_table' => self::SOURCE_TABLE,
            'read_only' => true,
            'selected_source_columns' => self::SOURCE_COLUMNS,
            'summary' => $summary,
            'warnings' => $warnings,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return new LegacyCategoryMatrixExportResultDTO(
            disk: $disk,
            sourceRows: count($sourceRows),
            outputRows: count($rows),
            knownSubsiteRows: $knownSubsiteRows,
            unknownSubsiteRows: count($rows) - $knownSubsiteRows,
            hiddenRows: $hiddenRows,
            linkReviewRows: $linkReviewRows,
            orphanRows: $orphanRows,
            mappedRows: $mappedRows,
            serviceCounts: $serviceCounts,
            subsiteCounts: $subsiteCounts,
            paths: $paths,
            warnings: $warnings,
        );
    }

    /** @param array<int, int> $sourceIds @return array<int, array<int, object>> */
    private function migrationMappings(array $sourceIds): array
    {
        if ($sourceIds === [] || ! Schema::hasTable('migration_logs')) {
            return [];
        }

        return DB::table('migration_logs')
            ->where('source_table', self::SOURCE_TABLE)
            ->where('status', 'success')
            ->whereIn('source_id', $sourceIds)
            ->orderBy('id')
            ->get(['source_id', 'target_table', 'target_id'])
            ->groupBy(static fn (object $row): int => (int) $row->source_id)
            ->map(static fn (Collection $rows): array => $rows->values()->all())
            ->all();
    }

    /** @param array<int, int> $sourceIds @return array<int, array<int, object>> */
    private function newsMappings(array $sourceIds): array
    {
        if ($sourceIds === [] || ! Schema::hasTable('news_articles')) {
            return [];
        }

        return DB::table('news_articles')
            ->where('legacy_source_table', self::SOURCE_TABLE)
            ->whereIn('legacy_source_id', $sourceIds)
            ->orderBy('id')
            ->get(['id', 'legacy_source_id', 'slug', 'status'])
            ->groupBy(static fn (object $row): int => (int) $row->legacy_source_id)
            ->map(static fn (Collection $rows): array => $rows->values()->all())
            ->all();
    }

    /**
     * @param  array<int, true>  $sourceIds
     * @param  array<int, array<int, object>>  $migrationMappings
     * @param  array<int, array<int, object>>  $newsMappings
     * @return array<string, mixed>
     */
    private function matrixRow(object $row, array $sourceIds, array $migrationMappings, array $newsMappings): array
    {
        $id = (int) $row->id;
        $serviceType = $this->integer($row->service_type ?? null);
        $suffix = $serviceType !== null ? $serviceType % 10 : null;
        [$subsite, $siteId] = $this->subsite($serviceType);
        $parent = $this->integer($row->parent ?? null);
        $hasParent = $parent !== null && $parent !== 0;
        $parentExists = $hasParent ? isset($sourceIds[$parent]) : null;
        $visibility = $this->integer($row->is_visible ?? null);
        $visible = $visibility === 1;
        $hidden = $visibility !== null && ! $visible;
        $linkReview = $this->truthy($row->is_link ?? null);
        $photo = $this->text($row->photo ?? null);
        $migrationRows = $migrationMappings[$id] ?? [];
        $newsRows = $newsMappings[$id] ?? [];
        $semantic = $this->semantic($serviceType, $suffix);
        $mapped = $migrationRows !== [] || $newsRows !== [];

        return [
            'source_table' => self::SOURCE_TABLE,
            'id' => $id,
            'parent' => $parent,
            'service_type' => $serviceType,
            'service_suffix' => $suffix,
            'service_semantic' => $semantic,
            'subsite' => $subsite,
            'site_id' => $siteId,
            'category_order' => $this->integer($row->category_order ?? null),
            'ar_name' => $this->text($row->ar_name ?? null),
            'en_name' => $this->text($row->en_name ?? null),
            'is_visible' => $visibility,
            'is_hidden' => $hidden ? 1 : 0,
            'is_link' => $this->truthy($row->is_link ?? null) ? 1 : 0,
            'external_link_review' => $linkReview ? 1 : 0,
            'url' => $this->text($row->url ?? null),
            'photo' => $photo,
            'photo_dependency' => $photo !== null ? 1 : 0,
            'start_date' => $this->text($row->start_date ?? null),
            'end_date' => $this->text($row->end_date ?? null),
            'has_parent' => $hasParent ? 1 : 0,
            'parent_exists' => $parentExists === null ? null : ($parentExists ? 1 : 0),
            'is_orphan' => $hasParent && ! $parentExists ? 1 : 0,
            'legacy_ar_url_candidate' => $subsite !== null ? $this->legacyUrl($subsite, 1, $serviceType, $id) : null,
            'legacy_en_url_candidate' => $subsite !== null ? $this->legacyUrl($subsite, 2, $serviceType, $id) : null,
            'migration_log_success_count' => count($migrationRows),
            'migration_log_target_tables' => $this->joinedValues($migrationRows, 'target_table'),
            'migration_log_target_ids' => $this->joinedValues($migrationRows, 'target_id'),
            'news_article_match_count' => count($newsRows),
            'news_article_ids' => $this->joinedValues($newsRows, 'id'),
            'news_article_slugs' => $this->joinedValues($newsRows, 'slug'),
            'news_article_statuses' => $this->joinedValues($newsRows, 'status'),
            'is_mapped' => $mapped ? 1 : 0,
            'decision_status' => $subsite === null || $semantic === 'unknown'
                ? 'unknown_service_review'
                : ($linkReview ? 'external_link_review' : ($hidden ? 'hidden_review' : 'contextual_review')),
        ];
    }

    /** @return array{0: ?string, 1: ?int} */
    private function subsite(?int $serviceType): array
    {
        return match (true) {
            $serviceType !== null && $serviceType >= 1 && $serviceType <= 10 => ['root', 0],
            $serviceType !== null && $serviceType >= 21 && $serviceType <= 29 => ['med', 2],
            $serviceType !== null && $serviceType >= 31 && $serviceType <= 39 => ['dent', 3],
            $serviceType !== null && $serviceType >= 41 && $serviceType <= 49 => ['pharm', 4],
            $serviceType !== null && $serviceType >= 51 && $serviceType <= 59 => ['info', 5],
            $serviceType !== null && $serviceType >= 61 && $serviceType <= 69 => ['petrol', 6],
            $serviceType !== null && $serviceType >= 71 && $serviceType <= 79 => ['admin', 7],
            $serviceType !== null && $serviceType >= 81 && $serviceType <= 89 => ['research', 8],
            $serviceType !== null && $serviceType >= 91 && $serviceType <= 99 => ['hospital', 9],
            $serviceType !== null && $serviceType >= 101 && $serviceType <= 109 => ['dent_clinic', 10],
            $serviceType !== null && $serviceType >= 111 && $serviceType <= 119 => ['alumni', 11],
            $serviceType !== null && $serviceType >= 121 && $serviceType <= 129 => ['clubs', 12],
            default => [null, null],
        };
    }

    private function semantic(?int $serviceType, ?int $suffix): string
    {
        if ($serviceType === 4) {
            return 'announcements';
        }

        if ($serviceType === 73) {
            return 'faculty_news';
        }

        if ($serviceType === 74) {
            return 'faculty_research_projects';
        }

        return match ($suffix) {
            1 => 'main_navigation',
            2 => 'secondary_navigation',
            3 => 'news_announcements',
            4 => 'projects_success',
            5 => 'events_cooperation',
            6 => 'general_content',
            7 => 'research_statistics',
            8 => 'gallery',
            9 => 'jobs_publications',
            default => 'unknown',
        };
    }

    private function legacyUrl(string $subsite, int $language, ?int $serviceType, int $id): string
    {
        $prefix = $subsite === 'root' ? '' : '/'.$subsite;

        return $prefix.'/index.php?page=show&ex=2&dir=items&lang='.$language.'&ser='.$serviceType.'&cat_id='.$id;
    }

    /** @param array<int, object> $rows */
    private function joinedValues(array $rows, string $field): ?string
    {
        $values = [];

        foreach ($rows as $row) {
            $value = $row->{$field} ?? null;

            if ($value !== null && trim((string) $value) !== '') {
                $values[] = (string) $value;
            }
        }

        $values = array_values(array_unique($values));

        return $values === [] ? null : implode('|', $values);
    }

    /** @param array<int, array<string, mixed>> $rows @return array<string, int> */
    private function counts(array $rows, string $field, ?string $nullKey = null): array
    {
        $counts = [];

        foreach ($rows as $row) {
            $key = $row[$field] ?? $nullKey;

            if ($key === null) {
                continue;
            }

            $counts[(string) $key] = ($counts[(string) $key] ?? 0) + 1;
        }

        ksort($counts);

        return $counts;
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function csv(array $rows): string
    {
        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            return '';
        }

        fputcsv($stream, self::CSV_HEADERS);

        foreach ($rows as $row) {
            fputcsv($stream, array_map(static fn (string $header): mixed => $row[$header] ?? '', self::CSV_HEADERS));
        }

        rewind($stream);
        $payload = stream_get_contents($stream);
        fclose($stream);

        return is_string($payload) ? $payload : '';
    }

    private function integer(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function truthy(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1';
    }

    private function text(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
