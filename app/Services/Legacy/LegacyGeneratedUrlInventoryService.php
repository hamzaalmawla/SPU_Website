<?php

declare(strict_types=1);

namespace App\Services\Legacy;

use App\Contracts\Legacy\LegacyGeneratedUrlInventoryServiceInterface;
use App\Contracts\Legacy\LegacyQueryResolverRegistryInterface;
use App\Contracts\Legacy\LegacyUrlNormalizerInterface;
use App\DTOs\Legacy\LegacyGeneratedUrlInventoryResultDTO;
use App\DTOs\Legacy\LegacyQueryResolutionDTO;
use App\DTOs\Legacy\NormalizedLegacyUrlDTO;
use App\Support\LegacyImport\OldDatabaseConnection;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class LegacyGeneratedUrlInventoryService implements LegacyGeneratedUrlInventoryServiceInterface
{
    /** @var array<int, string> */
    private const TABLES = [
        'jx_categories',
        'jx_items',
        'jx_member_categories',
        'jx_member_items',
        'jx_councils',
        'jx_councils1',
        'jx_site_static_pages',
        'jx_docs',
        'jx_sites',
    ];

    /** @var array<string, array<int, string>> */
    private const EXPLICIT_URL_COLUMNS = [
        'jx_categories' => ['url'],
        'jx_member_categories' => ['url'],
        'jx_councils' => ['url'],
        'jx_councils1' => ['url'],
        'jx_docs' => ['url', 'file', 'download_file'],
        'jx_sites' => ['url'],
    ];

    public function __construct(
        private readonly OldDatabaseConnection $oldDatabase,
        private readonly LegacyUrlNormalizerInterface $normalizer,
        private readonly LegacyQueryResolverRegistryInterface $queryResolverRegistry,
    ) {}

    public function export(
        ?string $table = null,
        ?int $limit = null,
        string $disk = 'local',
        string $directory = 'legacy-import-exports/generated-url-inventory',
    ): LegacyGeneratedUrlInventoryResultDTO {
        $table = $this->normalizedFilter($table);
        $directory = trim($directory, '/');
        $directory = $directory !== '' ? $directory : 'legacy-import-exports/generated-url-inventory';
        $tables = $table !== null ? [$table] : self::TABLES;
        $rows = [];
        $warnings = [];
        $sourceRows = 0;

        foreach ($tables as $sourceTable) {
            if (! in_array($sourceTable, self::TABLES, true)) {
                $warnings[] = "Generated URL inventory table [{$sourceTable}] is not configured.";

                continue;
            }

            $inspection = $this->tableRows($sourceTable, $limit);
            $sourceRows += $inspection['source_rows'];
            $warnings = array_merge($warnings, $inspection['warnings']);

            foreach ($inspection['rows'] as $legacyRow) {
                $rows = array_merge($rows, $this->generatedRowsForLegacyRow($sourceTable, $legacyRow));
            }
        }

        $rows = $this->dedupeRows($rows);
        usort($rows, static fn (array $left, array $right): int => [
            $left['source_table'],
            $left['source_id'] ?? 0,
            $left['legacy_path'],
        ] <=> [
            $right['source_table'],
            $right['source_id'] ?? 0,
            $right['legacy_path'],
        ]);
        $statusCounts = collect($rows)->countBy('status')->all();
        $sourceCounts = collect($rows)->countBy('source_type')->all();
        $resolvedRows = (int) ($statusCounts['resolved_by_query_resolver'] ?? 0);
        $unresolvedRows = count($rows) - $resolvedRows;
        $stamp = now()->format('Ymd_His');
        $suffix = $table !== null ? '_'.$this->filenamePart($table) : '';
        $basePath = $directory.'/'.$stamp.'_generated_url_inventory'.$suffix;
        $headers = [
            'source_type',
            'module',
            'source_table',
            'source_id',
            'legacy_path',
            'normalized_path',
            'query_signature',
            'handler_key',
            'request_type',
            'subsite',
            'old_site_id',
            'locale',
            'old_language_id',
            'target_url',
            'status',
            'confidence',
            'notes',
        ];
        $paths = [
            $basePath.'.md',
            $basePath.'.csv',
            $basePath.'.json',
        ];

        Storage::disk($disk)->put($paths[0], $this->markdown($table, $sourceRows, count($rows), $resolvedRows, $unresolvedRows, $sourceCounts, $statusCounts, $warnings));
        Storage::disk($disk)->put($paths[1], $this->csvPayload($rows, $headers));
        Storage::disk($disk)->put($paths[2], $this->json([
            'generated_at' => now()->toIso8601String(),
            'table' => $table,
            'summary' => [
                'source_rows' => $sourceRows,
                'generated_rows' => count($rows),
                'resolved_rows' => $resolvedRows,
                'unresolved_rows' => $unresolvedRows,
                'source_counts' => $sourceCounts,
                'status_counts' => $statusCounts,
                'warnings' => array_values(array_unique($warnings)),
            ],
        ]));

        return new LegacyGeneratedUrlInventoryResultDTO(
            table: $table,
            disk: $disk,
            sourceRows: $sourceRows,
            generatedRows: count($rows),
            resolvedRows: $resolvedRows,
            unresolvedRows: $unresolvedRows,
            sourceCounts: $sourceCounts,
            statusCounts: $statusCounts,
            warnings: array_values(array_unique($warnings)),
            paths: $paths,
        );
    }

    /** @return array{source_rows: int, rows: array<int, object>, warnings: array<int, string>} */
    private function tableRows(string $table, ?int $limit): array
    {
        try {
            $schema = $this->oldDatabase->schema();

            if (! $schema->hasTable($table)) {
                return ['source_rows' => 0, 'rows' => [], 'warnings' => ["Missing legacy URL source table [{$table}]."]];
            }

            $columns = $schema->getColumnListing($table);

            if (! in_array('id', $columns, true)) {
                return ['source_rows' => 0, 'rows' => [], 'warnings' => ["Missing legacy URL source id column [{$table}.id]."]];
            }

            $query = $this->oldDatabase->table($table)
                ->select($this->selectColumns($table, $columns))
                ->orderBy('id');

            if ($limit !== null) {
                $query->limit(max(1, $limit));
            }

            return [
                'source_rows' => (int) $this->oldDatabase->table($table)->count(),
                'rows' => $query->get()->all(),
                'warnings' => [],
            ];
        } catch (Throwable $exception) {
            return ['source_rows' => 0, 'rows' => [], 'warnings' => ["Could not inspect legacy URL source table [{$table}]: {$exception->getMessage()}"]];
        }
    }

    /** @param array<int, string> $availableColumns @return array<int, string> */
    private function selectColumns(string $table, array $availableColumns): array
    {
        $wanted = ['id'];
        $wanted = array_merge($wanted, self::EXPLICIT_URL_COLUMNS[$table] ?? []);

        if (in_array($table, ['jx_categories', 'jx_member_categories', 'jx_councils', 'jx_councils1'], true)) {
            $wanted = array_merge($wanted, ['service_type', 'ar_name', 'en_name', 'ar_data', 'en_data', 'ar_description', 'en_description', 'ar_brief', 'en_brief']);
        }

        if ($table === 'jx_site_static_pages') {
            $wanted = array_merge($wanted, ['ar_page_data', 'en_page_data', 'ar_brief', 'en_brief']);
        }

        return array_values(array_unique(array_filter($wanted, fn (string $column): bool => in_array($column, $availableColumns, true))));
    }

    /** @return array<int, array<string, mixed>> */
    private function generatedRowsForLegacyRow(string $table, object $row): array
    {
        $rows = [];
        $sourceId = (int) ($row->id ?? 0);

        foreach ($this->explicitUrls($table, $row) as $url) {
            $rows[] = $this->inventoryRow(
                sourceType: 'generated_explicit_url',
                sourceTable: $table,
                sourceId: $sourceId,
                legacyPath: $url,
                confidence: 'high',
                notes: 'Generated from explicit legacy URL/file column.',
            );
        }

        foreach ($this->routerUrls($table, $row) as $url) {
            $rows[] = $this->inventoryRow(
                sourceType: 'generated_router_url',
                sourceTable: $table,
                sourceId: $sourceId,
                legacyPath: $url,
                confidence: $this->routerConfidence($table),
                notes: 'Generated from evidence-backed legacy router pattern.',
            );
        }

        return $rows;
    }

    /** @return array<int, string> */
    private function explicitUrls(string $table, object $row): array
    {
        $urls = [];

        foreach (self::EXPLICIT_URL_COLUMNS[$table] ?? [] as $column) {
            $value = $row->{$column} ?? null;

            if (! is_scalar($value)) {
                continue;
            }

            $url = trim((string) $value);

            if ($url === '' || ! $this->looksLikeLegacyUrl($url)) {
                continue;
            }

            $urls[] = $url;
        }

        return array_values(array_unique($urls));
    }

    /** @return array<int, string> */
    private function routerUrls(string $table, object $row): array
    {
        return match ($table) {
            'jx_categories' => $this->categoryRouterUrls($row),
            'jx_member_categories' => $this->memberCategoryRouterUrls($row),
            'jx_councils' => $this->councilRouterUrls($row),
            'jx_councils1' => [],
            'jx_site_static_pages' => $this->staticPageRouterUrls($row),
            default => [],
        };
    }

    /** @return array<int, string> */
    private function categoryRouterUrls(object $row): array
    {
        $serviceType = $this->intValue($row->service_type ?? null);
        $id = $this->intValue($row->id ?? null);

        if ($id === null || $serviceType === null) {
            return [];
        }

        $path = $this->categorySubsitePath($serviceType);

        if ($path === null) {
            return [];
        }

        return collect($this->localesForRow($row))->map(
            fn (int $lang): string => $path.'/index.php?page=show&ex=2&dir=items&lang='.$lang.'&ser='.$serviceType.'&cat_id='.$id
        )->all();
    }

    private function categorySubsitePath(int $serviceType): ?string
    {
        return match (true) {
            $serviceType >= 1 && $serviceType <= 10 => '',
            $serviceType >= 21 && $serviceType <= 29 => '/med',
            $serviceType >= 31 && $serviceType <= 39 => '/dent',
            $serviceType >= 41 && $serviceType <= 49 => '/pharm',
            $serviceType >= 51 && $serviceType <= 59 => '/info',
            $serviceType >= 61 && $serviceType <= 69 => '/petrol',
            $serviceType >= 71 && $serviceType <= 79 => '/admin',
            $serviceType >= 81 && $serviceType <= 89 => '/research',
            $serviceType >= 91 && $serviceType <= 99 => '/hospital',
            $serviceType >= 101 && $serviceType <= 109 => '/dent_clinic',
            $serviceType >= 111 && $serviceType <= 119 => '/alumni',
            $serviceType >= 121 && $serviceType <= 129 => '/clubs',
            default => null,
        };
    }

    /** @return array<int, string> */
    private function memberCategoryRouterUrls(object $row): array
    {
        $serviceType = $this->intValue($row->service_type ?? null);
        $id = $this->intValue($row->id ?? null);

        if ($id === null || $serviceType === null) {
            return [];
        }

        return collect($this->localesForRow($row))->map(
            fn (int $lang): string => '/members/index.php?page=show&ex=2&dir=items&lang='.$lang.'&ser='.$serviceType.'&cat_id='.$id
        )->all();
    }

    /** @return array<int, string> */
    private function councilRouterUrls(object $row): array
    {
        $serviceType = $this->intValue($row->service_type ?? null);
        $id = $this->intValue($row->id ?? null);

        if ($id === null || $serviceType === null) {
            return [];
        }

        $path = match (true) {
            $serviceType >= 1 && $serviceType <= 2 => '',
            $serviceType >= 3 && $serviceType <= 4 => '/med',
            $serviceType >= 5 && $serviceType <= 6 => '/dent',
            $serviceType >= 7 && $serviceType <= 8 => '/pharm',
            $serviceType >= 9 && $serviceType <= 10 => '/info',
            $serviceType >= 11 && $serviceType <= 12 => '/petrol',
            $serviceType >= 13 && $serviceType <= 14 => '/admin',
            default => null,
        };

        if ($path === null) {
            return [];
        }

        return collect($this->localesForRow($row))->map(
            fn (int $lang): string => $path.'/index.php?page=show&ex=2&dir=councils&lang='.$lang.'&service='.$serviceType.'&cat_id='.$id
        )->all();
    }

    /** @return array<int, string> */
    private function staticPageRouterUrls(object $row): array
    {
        $id = $this->intValue($row->id ?? null);

        if ($id === null) {
            return [];
        }

        return collect($this->localesForRow($row))->map(
            fn (int $lang): string => '/index.php?page=show&dir=items&lang='.$lang.'&item_id='.$id
        )->all();
    }

    /** @return array<int, int> */
    private function localesForRow(object $row): array
    {
        $locales = [];

        foreach ([1 => ['ar_name', 'ar_data', 'ar_description', 'ar_brief', 'ar_page_data'], 2 => ['en_name', 'en_data', 'en_description', 'en_brief', 'en_page_data']] as $lang => $columns) {
            foreach ($columns as $column) {
                $value = $row->{$column} ?? null;

                if (is_scalar($value) && trim((string) $value) !== '') {
                    $locales[] = $lang;

                    break;
                }
            }
        }

        return $locales !== [] ? array_values(array_unique($locales)) : [1];
    }

    private function inventoryRow(string $sourceType, string $sourceTable, int $sourceId, string $legacyPath, string $confidence, string $notes): array
    {
        $normalized = $this->normalizeLegacyPath($legacyPath);
        $resolution = $this->resolveNormalized($normalized);
        $status = $resolution instanceof LegacyQueryResolutionDTO ? 'resolved_by_query_resolver' : 'unresolved_for_continuity_phase';

        return [
            'source_type' => $sourceType,
            'module' => 'generated',
            'source_table' => $sourceTable,
            'source_id' => $sourceId,
            'legacy_path' => $legacyPath,
            'normalized_path' => $normalized->path,
            'query_signature' => $this->querySignature($normalized),
            'handler_key' => $normalized->handlerKey,
            'request_type' => $normalized->requestType,
            'subsite' => $normalized->subsite->key,
            'old_site_id' => $normalized->subsite->siteId,
            'locale' => $normalized->language->locale,
            'old_language_id' => $normalized->language->oldLanguageId,
            'target_url' => $resolution?->targetUrl,
            'status' => $status,
            'confidence' => $resolution?->confidence ?? $confidence,
            'notes' => $resolution?->notes ?? $notes.' Target remains unresolved; do not redirect to homepage.',
        ];
    }

    private function normalizeLegacyPath(string $legacyPath): NormalizedLegacyUrlDTO
    {
        $parts = parse_url($legacyPath);
        $path = is_array($parts) && is_string($parts['path'] ?? null) ? $parts['path'] : $legacyPath;
        $query = is_array($parts) && is_string($parts['query'] ?? null) ? $parts['query'] : null;

        return $this->normalizer->normalize($path, $query);
    }

    private function resolveNormalized(NormalizedLegacyUrlDTO $normalized): ?LegacyQueryResolutionDTO
    {
        if ($normalized->queryString === null) {
            return null;
        }

        return $this->queryResolverRegistry->resolve($normalized);
    }

    private function querySignature(NormalizedLegacyUrlDTO $normalized): string
    {
        $params = $normalized->params;
        ksort($params);

        return http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    private function looksLikeLegacyUrl(string $url): bool
    {
        return str_contains($url, 'index.php')
            || str_starts_with($url, '/downloads/')
            || str_starts_with($url, '/images/')
            || str_starts_with($url, 'downloads/')
            || str_starts_with($url, 'images/');
    }

    private function intValue(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function routerConfidence(string $table): string
    {
        return match ($table) {
            'jx_councils', 'jx_councils1' => 'high',
            'jx_categories', 'jx_site_static_pages' => 'medium',
            default => 'low',
        };
    }

    /** @param array<int, array<string, mixed>> $rows @return array<int, array<string, mixed>> */
    private function dedupeRows(array $rows): array
    {
        $seen = [];
        $deduped = [];

        foreach ($rows as $row) {
            $key = implode('|', [(string) $row['source_table'], (string) $row['source_id'], (string) $row['legacy_path']]);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $deduped[] = $row;
        }

        return $deduped;
    }

    /** @param array<string, int> $sourceCounts @param array<string, int> $statusCounts @param array<int, string> $warnings */
    private function markdown(?string $table, int $sourceRows, int $generatedRows, int $resolvedRows, int $unresolvedRows, array $sourceCounts, array $statusCounts, array $warnings): string
    {
        $lines = [
            '# Generated Legacy URL Inventory',
            '',
            '- Table: '.($table ?? 'all'),
            '- Generated: '.now()->toIso8601String(),
            '- Source rows: '.$sourceRows,
            '- Generated URL rows: '.$generatedRows,
            '- Resolved rows: '.$resolvedRows,
            '- Unresolved/backlog rows: '.$unresolvedRows,
            '',
            '## Source Counts',
            '',
        ];

        foreach ($sourceCounts as $source => $count) {
            $lines[] = '- `'.$source.'`: '.$count;
        }

        $lines[] = '';
        $lines[] = '## Status Counts';
        $lines[] = '';

        foreach ($statusCounts as $status => $count) {
            $lines[] = '- `'.$status.'`: '.$count;
        }

        if ($warnings !== []) {
            $lines[] = '';
            $lines[] = '## Warnings';
            $lines[] = '';

            foreach (array_values(array_unique($warnings)) as $warning) {
                $lines[] = '- '.$warning;
            }
        }

        return implode("\n", $lines)."\n";
    }

    /** @param array<int, array<string, mixed>> $rows @param array<int, string> $headers */
    private function csvPayload(array $rows, array $headers): string
    {
        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            return '';
        }

        fputcsv($stream, $headers);

        foreach ($rows as $row) {
            fputcsv($stream, array_map(fn (string $header): mixed => $row[$header] ?? '', $headers));
        }

        rewind($stream);
        $payload = stream_get_contents($stream);
        fclose($stream);

        return is_string($payload) ? $payload : '';
    }

    /** @param array<string, mixed> $payload */
    private function json(array $payload): string
    {
        return (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function normalizedFilter(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    private function filenamePart(string $value): string
    {
        $value = preg_replace('/[^A-Za-z0-9_-]+/', '_', $value) ?? 'table';

        return trim($value, '_') !== '' ? trim($value, '_') : 'table';
    }
}
