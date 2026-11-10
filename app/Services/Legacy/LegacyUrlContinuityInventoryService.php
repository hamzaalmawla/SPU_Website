<?php

declare(strict_types=1);

namespace App\Services\Legacy;

use App\Contracts\Legacy\LegacyQueryResolverRegistryInterface;
use App\Contracts\Legacy\LegacyUrlContinuityInventoryServiceInterface;
use App\Contracts\Legacy\LegacyUrlNormalizerInterface;
use App\DTOs\Legacy\LegacyQueryResolutionDTO;
use App\DTOs\Legacy\LegacyUrlContinuityInventoryResultDTO;
use App\DTOs\Legacy\NormalizedLegacyUrlDTO;
use App\Models\Legacy\LegacyContentMapping;
use App\Models\Legacy\LegacyExactRedirect;
use App\Models\Legacy\LegacyFileInventory;
use App\Models\Page\UnresolvedLegacyRequest;
use App\Models\Shared\MigrationRejection;
use Illuminate\Support\Facades\Storage;

final class LegacyUrlContinuityInventoryService implements LegacyUrlContinuityInventoryServiceInterface
{
    public function __construct(
        private readonly LegacyUrlNormalizerInterface $normalizer,
        private readonly LegacyQueryResolverRegistryInterface $queryResolverRegistry,
    ) {}

    public function export(
        ?string $module = null,
        bool $includeFiles = true,
        string $disk = 'local',
        string $directory = 'legacy-import-exports/url-continuity',
    ): LegacyUrlContinuityInventoryResultDTO {
        $module = $this->normalizedFilter($module);
        $directory = trim($directory, '/');
        $directory = $directory !== '' ? $directory : 'legacy-import-exports/url-continuity';
        $rows = array_merge(
            $this->exactRedirectRows(),
            $this->internalLinkRows($module),
            $this->mappingRows($module),
            $this->unresolvedRequestRows(),
            $includeFiles ? $this->fileRows($module) : [],
        );

        usort($rows, static fn (array $left, array $right): int => [
            $left['status'],
            $left['source_type'],
            $left['module'],
            $left['legacy_path'],
        ] <=> [
            $right['status'],
            $right['source_type'],
            $right['module'],
            $right['legacy_path'],
        ]);

        $statusCounts = collect($rows)->countBy('status')->all();
        $sourceCounts = collect($rows)->countBy('source_type')->all();
        $resolvedRows = collect($rows)->filter(fn (array $row): bool => in_array($row['status'], [
            'persisted_exact_redirect',
            'resolved_by_query_resolver',
            'file_inventory_mapped',
        ], true))->count();
        $fileRows = collect($rows)->where('source_type', 'file_inventory')->count();
        $unresolvedRows = count($rows) - $resolvedRows;
        $stamp = now()->format('Ymd_His');
        $suffix = $module !== null ? '_'.$this->filenamePart($module) : '';
        $basePath = $directory.'/'.$stamp.'_url_continuity_inventory'.$suffix;
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

        Storage::disk($disk)->put($paths[0], $this->markdown($module, count($rows), $resolvedRows, $unresolvedRows, $fileRows, $statusCounts, $sourceCounts));
        Storage::disk($disk)->put($paths[1], $this->csvPayload($rows, $headers));
        Storage::disk($disk)->put($paths[2], $this->json([
            'generated_at' => now()->toIso8601String(),
            'module' => $module,
            'summary' => [
                'row_count' => count($rows),
                'resolved_rows' => $resolvedRows,
                'unresolved_rows' => $unresolvedRows,
                'file_rows' => $fileRows,
                'status_counts' => $statusCounts,
                'source_counts' => $sourceCounts,
            ],
        ]));

        return new LegacyUrlContinuityInventoryResultDTO(
            module: $module,
            disk: $disk,
            rowCount: count($rows),
            resolvedRows: $resolvedRows,
            unresolvedRows: $unresolvedRows,
            fileRows: $fileRows,
            statusCounts: $statusCounts,
            sourceCounts: $sourceCounts,
            paths: $paths,
        );
    }

    /** @return array<int, array<string, mixed>> */
    private function exactRedirectRows(): array
    {
        return LegacyExactRedirect::query()
            ->orderBy('id')
            ->get()
            ->map(function (LegacyExactRedirect $redirect): array {
                $legacyPath = (string) $redirect->legacy_path;

                if (is_string($redirect->query_signature) && $redirect->query_signature !== '') {
                    $legacyPath .= '?'.$redirect->query_signature;
                }

                $normalized = $this->normalizeLegacyPath($legacyPath);

                return $this->baseRow(
                    sourceType: 'exact_redirect',
                    module: 'continuity',
                    sourceTable: 'legacy_exact_redirects',
                    sourceId: (int) $redirect->getKey(),
                    legacyPath: $legacyPath,
                    normalized: $normalized,
                    targetUrl: (string) $redirect->destination_url,
                    status: (bool) $redirect->is_active ? 'persisted_exact_redirect' : 'inactive_exact_redirect',
                    confidence: 'high',
                    notes: (string) ($redirect->notes ?? 'Existing exact redirect rule.'),
                );
            })
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function internalLinkRows(?string $module): array
    {
        return MigrationRejection::query()
            ->when($module !== null, fn ($query) => $query->where('module', $module))
            ->where('reason_code', 'legacy_internal_link')
            ->orderBy('module')
            ->orderBy('source_table')
            ->orderBy('source_id')
            ->get(['module', 'source_table', 'source_id', 'raw_summary'])
            ->map(function (MigrationRejection $rejection): ?array {
                $summary = $rejection->raw_summary ?? [];
                $legacyPath = is_string($summary['legacy_path'] ?? null) ? trim($summary['legacy_path']) : '';

                if ($legacyPath === '') {
                    return null;
                }

                return $this->resolvedRow(
                    sourceType: 'internal_link_review',
                    module: (string) $rejection->module,
                    sourceTable: (string) $rejection->source_table,
                    sourceId: is_numeric($rejection->source_id) ? (int) $rejection->source_id : null,
                    legacyPath: $legacyPath,
                    defaultNotes: 'Extracted from Phase 3 internal-link review rows.',
                );
            })
            ->filter()
            ->values()
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function mappingRows(?string $module): array
    {
        return LegacyContentMapping::query()
            ->when($module !== null, fn ($query) => $query->where('module', $module))
            ->where('classification', 'redirect_to_equivalent')
            ->whereNotNull('source_url')
            ->orderBy('module')
            ->orderBy('source_table')
            ->orderBy('source_id')
            ->get()
            ->map(function (LegacyContentMapping $mapping): array {
                $legacyPath = (string) $mapping->source_url;

                return $this->resolvedRow(
                    sourceType: 'mapping_redirect_candidate',
                    module: (string) $mapping->module,
                    sourceTable: (string) $mapping->source_table,
                    sourceId: $mapping->source_id !== null ? (int) $mapping->source_id : null,
                    legacyPath: $legacyPath,
                    defaultNotes: 'Proposed Phase 4 redirect/content-link mapping. Not persisted as a redirect.',
                );
            })
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function unresolvedRequestRows(): array
    {
        return UnresolvedLegacyRequest::query()
            ->orderByDesc('last_seen_at')
            ->limit(5000)
            ->get()
            ->map(function (UnresolvedLegacyRequest $request): array {
                $legacyPath = (string) $request->url;

                return $this->resolvedRow(
                    sourceType: 'unresolved_request_log',
                    module: 'continuity',
                    sourceTable: 'unresolved_legacy_requests',
                    sourceId: (int) $request->getKey(),
                    legacyPath: $legacyPath,
                    defaultNotes: 'Observed unresolved request; retained for triage.',
                );
            })
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function fileRows(?string $module): array
    {
        $sourceTables = $module !== null ? $this->moduleSourceTables($module) : [];

        return LegacyFileInventory::query()
            ->when($module !== null, fn ($query) => $query->whereIn('source_table', $sourceTables))
            ->orderBy('legacy_path')
            ->get()
            ->map(function (LegacyFileInventory $file): array {
                $legacyPath = (string) $file->legacy_path;
                $normalized = $this->normalizeLegacyPath($legacyPath);
                $status = match ((string) $file->status) {
                    'mapped' => 'file_inventory_mapped',
                    'missing' => 'file_inventory_missing_source',
                    default => 'file_inventory_unmapped',
                };

                return $this->baseRow(
                    sourceType: 'file_inventory',
                    module: 'media',
                    sourceTable: (string) $file->source_table,
                    sourceId: $file->source_id !== null ? (int) $file->source_id : null,
                    legacyPath: $legacyPath,
                    normalized: $normalized,
                    targetUrl: $file->current_path,
                    status: $status,
                    confidence: $status === 'file_inventory_mapped' ? 'high' : 'low',
                    notes: $status === 'file_inventory_missing_source'
                        ? 'Legacy database references this file, but OLD_PUBLIC_ROOT is unavailable or file bytes are missing.'
                        : 'Legacy file inventory row.',
                );
            })
            ->all();
    }

    /** @return array<int, string> */
    private function moduleSourceTables(string $module): array
    {
        $tables = config('old_database.modules.'.$module.'.source_tables', []);

        return is_array($tables) ? array_values(array_filter($tables, 'is_string')) : [];
    }

    private function resolvedRow(string $sourceType, string $module, string $sourceTable, ?int $sourceId, string $legacyPath, string $defaultNotes): array
    {
        $normalized = $this->normalizeLegacyPath($legacyPath);
        $resolution = $this->resolveNormalized($normalized);

        if ($resolution instanceof LegacyQueryResolutionDTO) {
            return $this->baseRow(
                sourceType: $sourceType,
                module: $module,
                sourceTable: $sourceTable,
                sourceId: $sourceId,
                legacyPath: $legacyPath,
                normalized: $normalized,
                targetUrl: $resolution->targetUrl,
                status: 'resolved_by_query_resolver',
                confidence: $resolution->confidence,
                notes: $resolution->notes,
            );
        }

        return $this->baseRow(
            sourceType: $sourceType,
            module: $module,
            sourceTable: $sourceTable,
            sourceId: $sourceId,
            legacyPath: $legacyPath,
            normalized: $normalized,
            targetUrl: null,
            status: $normalized->requestType === 'unknown' ? 'unresolved_unknown_legacy_url' : 'unresolved_for_continuity_phase',
            confidence: 'low',
            notes: $defaultNotes.' No safe target is currently available; do not redirect to homepage.',
        );
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

    /** @return array<string, mixed> */
    private function baseRow(
        string $sourceType,
        string $module,
        string $sourceTable,
        ?int $sourceId,
        string $legacyPath,
        NormalizedLegacyUrlDTO $normalized,
        ?string $targetUrl,
        string $status,
        string $confidence,
        string $notes,
    ): array {
        return [
            'source_type' => $sourceType,
            'module' => $module,
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
            'target_url' => $targetUrl,
            'status' => $status,
            'confidence' => $confidence,
            'notes' => $notes,
        ];
    }

    private function querySignature(NormalizedLegacyUrlDTO $normalized): string
    {
        $params = $normalized->params;
        ksort($params);

        return http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /** @param array<string, int> $statusCounts @param array<string, int> $sourceCounts */
    private function markdown(?string $module, int $rowCount, int $resolvedRows, int $unresolvedRows, int $fileRows, array $statusCounts, array $sourceCounts): string
    {
        $lines = [
            '# Legacy URL Continuity Inventory',
            '',
            '- Module: '.($module ?? 'all'),
            '- Generated: '.now()->toIso8601String(),
            '- Rows: '.$rowCount,
            '- Resolved rows: '.$resolvedRows,
            '- Unresolved/backlog rows: '.$unresolvedRows,
            '- File rows: '.$fileRows,
            '',
            '## Status Counts',
            '',
        ];

        foreach ($statusCounts as $status => $count) {
            $lines[] = '- `'.$status.'`: '.$count;
        }

        $lines[] = '';
        $lines[] = '## Source Counts';
        $lines[] = '';

        foreach ($sourceCounts as $source => $count) {
            $lines[] = '- `'.$source.'`: '.$count;
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
        $value = preg_replace('/[^A-Za-z0-9_-]+/', '_', $value) ?? 'module';

        return trim($value, '_') !== '' ? trim($value, '_') : 'module';
    }
}
