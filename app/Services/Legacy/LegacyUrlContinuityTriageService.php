<?php

declare(strict_types=1);

namespace App\Services\Legacy;

use App\Contracts\Legacy\LegacyUrlContinuityTriageServiceInterface;
use App\DTOs\Legacy\LegacyUrlContinuityTriageResultDTO;
use App\Models\Legacy\LegacyContentMapping;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

final class LegacyUrlContinuityTriageService implements LegacyUrlContinuityTriageServiceInterface
{
    public function export(
        string $path,
        string $disk = 'local',
        string $directory = 'legacy-import-exports/url-continuity-triage',
    ): LegacyUrlContinuityTriageResultDTO {
        $path = trim($path);
        $directory = trim($directory, '/');
        $directory = $directory !== '' ? $directory : 'legacy-import-exports/url-continuity-triage';

        if ($path === '') {
            throw new InvalidArgumentException('URL continuity inventory CSV path is required.');
        }

        if (! Storage::disk($disk)->exists($path)) {
            throw new InvalidArgumentException('URL continuity inventory CSV was not found on the selected disk.');
        }

        $rows = $this->csvRows((string) Storage::disk($disk)->get($path));
        $warnings = [];
        $triageRows = [];

        foreach ($rows as $index => $row) {
            if (($row['status'] ?? '') === 'resolved_by_query_resolver') {
                continue;
            }

            $triageRows[] = $this->triageRow($row, $index + 2, $warnings);
        }

        $groupRows = $this->groupRows($triageRows);
        $triageCounts = collect($triageRows)->countBy('triage_status')->all();
        $handlerCounts = collect($triageRows)->countBy('handler_key')->all();
        $resolverCandidateRows = (int) ($triageCounts['resolver_candidate'] ?? 0);
        $blockedRows = count($triageRows) - $resolverCandidateRows;
        $stamp = now()->format('Ymd_His');
        $basePath = $directory.'/'.$stamp.'_url_continuity_triage';
        $paths = [
            $basePath.'.md',
            $basePath.'_groups.csv',
            $basePath.'_rows.csv',
            $basePath.'.json',
        ];

        Storage::disk($disk)->put($paths[0], $this->markdown(count($rows), count($triageRows), $triageCounts, $handlerCounts, $warnings));
        Storage::disk($disk)->put($paths[1], $this->csvPayload($groupRows, [
            'triage_status',
            'handler_key',
            'subsite',
            'candidate_source_tables',
            'rows',
            'sample_legacy_paths',
            'notes',
        ]));
        Storage::disk($disk)->put($paths[2], $this->csvPayload($triageRows, [
            'triage_status',
            'handler_key',
            'subsite',
            'legacy_path',
            'query_signature',
            'candidate_source_id',
            'candidate_source_tables',
            'mapping_available',
            'source_type',
            'module',
            'status',
            'notes',
        ]));
        Storage::disk($disk)->put($paths[3], $this->json([
            'generated_at' => now()->toIso8601String(),
            'source_path' => $path,
            'summary' => [
                'scanned_rows' => count($rows),
                'unresolved_rows' => count($triageRows),
                'resolver_candidate_rows' => $resolverCandidateRows,
                'blocked_rows' => $blockedRows,
                'triage_counts' => $triageCounts,
                'handler_counts' => $handlerCounts,
                'warnings' => array_values(array_unique($warnings)),
            ],
        ]));

        return new LegacyUrlContinuityTriageResultDTO(
            sourcePath: $path,
            disk: $disk,
            scannedRows: count($rows),
            unresolvedRows: count($triageRows),
            resolverCandidateRows: $resolverCandidateRows,
            blockedRows: $blockedRows,
            triageCounts: $triageCounts,
            handlerCounts: $handlerCounts,
            warnings: array_values(array_unique($warnings)),
            paths: $paths,
        );
    }

    /** @param array<string, string> $row @param array<int, string> $warnings @return array<string, mixed> */
    private function triageRow(array $row, int $lineNumber, array &$warnings): array
    {
        $handlerKey = trim($row['handler_key'] ?? '');
        $status = trim($row['status'] ?? '');
        $requestType = trim($row['request_type'] ?? '');
        $params = $this->params($row['query_signature'] ?? '');
        $sourceId = $this->sourceId($params);
        $sourceTables = $this->candidateSourceTables($handlerKey);
        $mappingAvailable = $sourceId !== null && $sourceTables !== [] && $this->hasMapping($sourceTables, $sourceId);
        $triageStatus = $this->triageStatus($status, $requestType, $handlerKey, $sourceId, $sourceTables, $mappingAvailable);
        $notes = $this->notes($triageStatus, $handlerKey, $sourceId, $sourceTables, $mappingAvailable);

        if ($handlerKey === '' && $status !== 'unresolved_unknown_legacy_url') {
            $warnings[] = "Row {$lineNumber} has no handler key.";
        }

        return [
            'triage_status' => $triageStatus,
            'handler_key' => $handlerKey !== '' ? $handlerKey : '(none)',
            'subsite' => $row['subsite'] ?? '',
            'legacy_path' => $row['legacy_path'] ?? '',
            'query_signature' => $row['query_signature'] ?? '',
            'candidate_source_id' => $sourceId,
            'candidate_source_tables' => implode('|', $sourceTables),
            'mapping_available' => $mappingAvailable ? 'yes' : 'no',
            'source_type' => $row['source_type'] ?? '',
            'module' => $row['module'] ?? '',
            'status' => $status,
            'notes' => $notes,
        ];
    }

    /** @param array<int, array<string, mixed>> $rows @return array<int, array<string, mixed>> */
    private function groupRows(array $rows): array
    {
        return collect($rows)
            ->groupBy(fn (array $row): string => implode('|', [
                $row['triage_status'],
                $row['handler_key'],
                $row['subsite'],
                $row['candidate_source_tables'],
            ]))
            ->map(function ($group): array {
                $first = $group->first();

                return [
                    'triage_status' => $first['triage_status'],
                    'handler_key' => $first['handler_key'],
                    'subsite' => $first['subsite'],
                    'candidate_source_tables' => $first['candidate_source_tables'],
                    'rows' => $group->count(),
                    'sample_legacy_paths' => $group->pluck('legacy_path')->filter()->unique()->take(5)->implode(' | '),
                    'notes' => $first['notes'],
                ];
            })
            ->sortBy([['triage_status', 'asc'], ['rows', 'desc'], ['handler_key', 'asc']])
            ->values()
            ->all();
    }

    /** @return array<string, string> */
    private function params(string $querySignature): array
    {
        parse_str($querySignature, $parsed);
        $params = [];

        foreach ($parsed as $key => $value) {
            if (is_string($key) && is_scalar($value)) {
                $params[$key] = (string) $value;
            }
        }

        return $params;
    }

    /** @param array<string, string> $params */
    private function sourceId(array $params): ?int
    {
        foreach (['cat_id', 'id', 'item_id', 'static_page_id', 'page_id', 'act'] as $key) {
            $value = $params[$key] ?? null;

            if (is_numeric($value)) {
                return (int) $value;
            }
        }

        return null;
    }

    /** @return array<int, string> */
    private function candidateSourceTables(string $handlerKey): array
    {
        if (str_ends_with($handlerKey, ':councils:show')) {
            return ['jx_councils1', 'jx_councils'];
        }

        if (str_ends_with($handlerKey, ':items:show')) {
            return ['jx_categories'];
        }

        if (str_ends_with($handlerKey, ':member_items:list')) {
            return ['jx_member_items'];
        }

        if (str_contains($handlerKey, ':html:')) {
            return ['jx_site_static_pages'];
        }

        return [];
    }

    /** @param array<int, string> $sourceTables */
    private function hasMapping(array $sourceTables, int $sourceId): bool
    {
        return LegacyContentMapping::query()
            ->whereIn('source_table', $sourceTables)
            ->where('source_id', $sourceId)
            ->whereIn('mapping_status', ['proposed', 'approved'])
            ->exists();
    }

    /** @param array<int, string> $sourceTables */
    private function triageStatus(string $status, string $requestType, string $handlerKey, ?int $sourceId, array $sourceTables, bool $mappingAvailable): string
    {
        if ($requestType === 'legacy_media_file' || $handlerKey === 'legacy_media_file') {
            return 'blocked_file_url';
        }

        if ($status === 'unresolved_unknown_legacy_url' || $handlerKey === '') {
            return 'unknown_legacy_url';
        }

        if (str_contains($handlerKey, ':home') || str_contains($handlerKey, ':photos:')) {
            return 'blocked_missing_target_module';
        }

        if ($sourceId === null || $sourceTables === []) {
            return 'needs_phase4_mapping';
        }

        if (! $mappingAvailable) {
            return 'needs_phase4_mapping';
        }

        if ($this->handlerTargetBlocked($handlerKey)) {
            return 'blocked_missing_target_module';
        }

        return 'resolver_candidate';
    }

    private function handlerTargetBlocked(string $handlerKey): bool
    {
        return str_starts_with($handlerKey, 'admin:')
            || str_starts_with($handlerKey, 'members:')
            || str_contains($handlerKey, ':councils:show')
            || str_starts_with($handlerKey, 'petrol:')
            || str_starts_with($handlerKey, 'pharm:')
            || str_starts_with($handlerKey, 'med:')
            || str_starts_with($handlerKey, 'dent:')
            || str_starts_with($handlerKey, 'info:');
    }

    /** @param array<int, string> $sourceTables */
    private function notes(string $triageStatus, string $handlerKey, ?int $sourceId, array $sourceTables, bool $mappingAvailable): string
    {
        return match ($triageStatus) {
            'resolver_candidate' => 'Handler has parseable source ID and existing Phase 4 mapping evidence. Resolver can be designed, but redirects remain gated.',
            'needs_phase4_mapping' => 'No safe source mapping exists yet for handler/source ID. Keep in continuity backlog.',
            'blocked_missing_target_module' => 'Handler points to a module or subsite that is not production-ready in current scope.',
            'blocked_file_url' => 'File URL continuity is blocked until legacy file bytes or mapped file inventory are available.',
            'unknown_legacy_url' => 'URL shape is unknown; do not guess or redirect to homepage.',
            default => 'Unresolved continuity backlog row.',
        }.' Handler='.$handlerKey.' SourceId='.($sourceId !== null ? (string) $sourceId : 'none').' Tables='.($sourceTables !== [] ? implode('|', $sourceTables) : 'none').' Mapping='.($mappingAvailable ? 'yes' : 'no').'.';
    }

    /** @return array<int, array<string, string>> */
    private function csvRows(string $payload): array
    {
        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            return [];
        }

        fwrite($stream, $payload);
        rewind($stream);
        $headers = fgetcsv($stream);

        if (! is_array($headers)) {
            fclose($stream);

            return [];
        }

        $headers = array_map(static fn (mixed $header): string => trim((string) $header), $headers);
        $rows = [];

        while (($line = fgetcsv($stream)) !== false) {
            $row = [];

            foreach ($headers as $index => $header) {
                $row[$header] = (string) ($line[$index] ?? '');
            }

            $rows[] = $row;
        }

        fclose($stream);

        return $rows;
    }

    /** @param array<string, int> $triageCounts @param array<string, int> $handlerCounts @param array<int, string> $warnings */
    private function markdown(int $scannedRows, int $unresolvedRows, array $triageCounts, array $handlerCounts, array $warnings): string
    {
        $lines = [
            '# Legacy URL Continuity Triage',
            '',
            '- Generated: '.now()->toIso8601String(),
            '- Scanned inventory rows: '.$scannedRows,
            '- Unresolved rows triaged: '.$unresolvedRows,
            '',
            '## Triage Counts',
            '',
        ];

        foreach ($triageCounts as $status => $count) {
            $lines[] = '- `'.$status.'`: '.$count;
        }

        $lines[] = '';
        $lines[] = '## Top Handler Counts';
        $lines[] = '';

        foreach (collect($handlerCounts)->sortDesc()->take(20)->all() as $handler => $count) {
            $lines[] = '- `'.$handler.'`: '.$count;
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
}
