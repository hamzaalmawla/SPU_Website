<?php

declare(strict_types=1);

namespace App\Services\Legacy;

use App\Contracts\Legacy\LegacyInternalLinkExtractionServiceInterface;
use App\DTOs\Legacy\LegacyInternalLinkExtractionResultDTO;
use App\Models\Shared\MigrationRejection;
use App\Support\LegacyImport\OldDatabaseConnection;
use Throwable;

final class LegacyInternalLinkExtractionService implements LegacyInternalLinkExtractionServiceInterface
{
    /** @var list<string> */
    private const INTERNAL_HOSTS = ['spu.edu.sy', 'www.spu.edu.sy', 'old.spu.edu.sy'];

    public function __construct(
        private readonly OldDatabaseConnection $oldDatabase,
    ) {}

    public function extract(string $module, bool $recordReviewRows = false, ?int $limit = null): LegacyInternalLinkExtractionResultDTO
    {
        $definitions = $this->definitionsForModule($module);

        if ($definitions === []) {
            return new LegacyInternalLinkExtractionResultDTO(
                module: $module,
                status: 'unknown_or_unconfigured_module',
                recordedReviewRows: $recordReviewRows,
                scannedRows: 0,
                scannedFields: 0,
                extractedLinks: 0,
                uniqueLinks: 0,
                recordedRows: 0,
                warnings: ['No internal link extraction fields are configured for this module.'],
                sampleLinks: [],
            );
        }

        $warnings = [];
        $rowKeys = [];
        $scannedFields = 0;
        $extractedLinks = 0;
        $recordedRows = 0;
        $uniqueLinks = [];

        foreach ($definitions as $definition) {
            $table = $definition['table'];
            $idColumn = $definition['id_column'];
            $columns = $definition['columns'];

            if (! $this->columnExists($table, $idColumn)) {
                $warnings[] = "Missing legacy ID column [{$table}.{$idColumn}].";

                continue;
            }

            $availableColumns = [];

            foreach ($columns as $column) {
                if (! $this->columnExists($table, $column)) {
                    $warnings[] = "Missing legacy internal link column [{$table}.{$column}].";

                    continue;
                }

                $availableColumns[] = $column;
            }

            if ($availableColumns === []) {
                continue;
            }

            $query = $this->oldDatabase->table($table)
                ->select(array_values(array_unique(array_merge([$idColumn], $availableColumns))))
                ->orderBy($idColumn);

            if ($limit !== null && $limit > 0) {
                $query->limit($limit);
            }

            foreach ($query->get() as $row) {
                $sourceId = $this->integerValue($row->{$idColumn} ?? null);
                $rowKeys[$table.':'.($sourceId ?? spl_object_id($row))] = true;

                foreach ($availableColumns as $column) {
                    $scannedFields++;
                    $links = $this->extractInternalLinks($row->{$column} ?? null);

                    foreach ($links as $link) {
                        $extractedLinks++;
                        $uniqueLinks[$link] = true;

                        if ($recordReviewRows) {
                            $recordedRows += $this->recordReviewRow($module, $table, $sourceId, $column, $link);
                        }
                    }
                }
            }
        }

        return new LegacyInternalLinkExtractionResultDTO(
            module: $module,
            status: $extractedLinks > 0 ? 'internal_links_found' : 'no_internal_links_found',
            recordedReviewRows: $recordReviewRows,
            scannedRows: count($rowKeys),
            scannedFields: $scannedFields,
            extractedLinks: $extractedLinks,
            uniqueLinks: count($uniqueLinks),
            recordedRows: $recordedRows,
            warnings: array_values(array_unique($warnings)),
            sampleLinks: array_slice(array_keys($uniqueLinks), 0, 10),
        );
    }

    /**
     * @return array<int, array{table: string, id_column: string, columns: array<int, string>}>
     */
    private function definitionsForModule(string $module): array
    {
        $configured = config('old_database.internal_link_extraction_fields.'.$module, []);

        if (! is_array($configured)) {
            return [];
        }

        return collect($configured)
            ->map(function (mixed $definition): ?array {
                if (! is_array($definition) || ! is_string($definition['table'] ?? null)) {
                    return null;
                }

                $columns = is_array($definition['columns'] ?? null) ? array_values(array_filter($definition['columns'], 'is_string')) : [];

                if ($columns === []) {
                    return null;
                }

                return [
                    'table' => $definition['table'],
                    'id_column' => is_string($definition['id_column'] ?? null) ? $definition['id_column'] : 'id',
                    'columns' => $columns,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function columnExists(string $table, string $column): bool
    {
        try {
            return $this->oldDatabase->schema()->hasColumn($table, $column);
        } catch (Throwable) {
            return false;
        }
    }

    /** @return array<int, string> */
    private function extractInternalLinks(mixed $value): array
    {
        if (! is_scalar($value)) {
            return [];
        }

        $content = html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $candidates = [];

        if ($this->looksLikeDirectLink($content)) {
            $candidates[] = trim($content);
        }

        preg_match_all('/\b(?:href|src|action)\s*=\s*(["\'])(.*?)\1/iu', $content, $quotedMatches, PREG_SET_ORDER);

        foreach ($quotedMatches as $match) {
            $candidates[] = trim((string) $match[2]);
        }

        preg_match_all('/\b(?:href|src|action)\s*=\s*([^\s>"\']+)/iu', $content, $unquotedMatches, PREG_SET_ORDER);

        foreach ($unquotedMatches as $match) {
            $candidates[] = trim((string) $match[1], " \t\n\r\0\x0B'\"");
        }

        $links = [];

        foreach ($candidates as $candidate) {
            $normalized = $this->normalizeInternalLink($candidate);

            if ($normalized !== null) {
                $links[$normalized] = true;
            }
        }

        return array_keys($links);
    }

    private function looksLikeDirectLink(string $content): bool
    {
        $value = trim($content);

        if ($value === '' || str_contains($value, '<')) {
            return false;
        }

        return preg_match('#^(?:https?://|/)?(?:[a-z0-9_-]+/)*index\.php\?#iu', $value) === 1
            || preg_match('#^(?:https?://[^/]+)?/?(?:downloads/files|images)/#iu', $value) === 1
            || str_starts_with($value, '/');
    }

    private function normalizeInternalLink(string $candidate): ?string
    {
        $candidate = trim($candidate);

        if ($candidate === '' || str_starts_with($candidate, '#')) {
            return null;
        }

        $lower = mb_strtolower(preg_replace('/\s+/', '', $candidate) ?? $candidate);

        if (str_starts_with($lower, 'javascript:') || str_starts_with($lower, 'vbscript:') || str_starts_with($lower, 'data:') || str_starts_with($lower, 'mailto:') || str_starts_with($lower, 'tel:')) {
            return null;
        }

        if (str_starts_with($candidate, '//')) {
            return null;
        }

        $parts = parse_url($candidate);

        if ($parts === false) {
            return null;
        }

        $host = is_string($parts['host'] ?? null) ? mb_strtolower($parts['host']) : null;

        if ($host !== null && ! in_array($host, self::INTERNAL_HOSTS, true)) {
            return null;
        }

        $path = is_string($parts['path'] ?? null) ? $parts['path'] : '';
        $query = is_string($parts['query'] ?? null) ? $parts['query'] : null;

        if ($path === '' && $query === null) {
            return null;
        }

        if ($host === null && ! str_starts_with($candidate, '/') && ! $this->isLegacyRelativePath($candidate)) {
            return null;
        }

        $path = '/'.ltrim(str_replace('\\', '/', $path), '/');
        $path = preg_replace('#/+#', '/', $path) ?? $path;

        if ($path === '/') {
            return $query !== null ? '/index.php?'.$query : null;
        }

        return $query !== null && $query !== '' ? $path.'?'.$query : $path;
    }

    private function isLegacyRelativePath(string $candidate): bool
    {
        return preg_match('#^(?:[a-z0-9_-]+/)*index\.php\?#iu', $candidate) === 1
            || preg_match('#^(?:downloads/files|images)/#iu', $candidate) === 1;
    }

    private function recordReviewRow(string $module, string $sourceTable, ?int $sourceId, string $column, string $legacyPath): int
    {
        $reasonMessage = "{$sourceTable}.{$column} contains legacy internal link [{$legacyPath}].";

        $exists = MigrationRejection::query()
            ->where('module', $module)
            ->where('source_table', $sourceTable)
            ->where('source_id', $sourceId)
            ->where('reason_code', 'legacy_internal_link')
            ->where('reason_message', $reasonMessage)
            ->exists();

        if ($exists) {
            return 0;
        }

        MigrationRejection::query()->create([
            'module' => $module,
            'source_table' => $sourceTable,
            'source_id' => $sourceId,
            'reason_code' => 'legacy_internal_link',
            'reason_message' => $reasonMessage,
            'raw_summary' => [
                'field' => $column,
                'legacy_path' => $legacyPath,
                'review_type' => 'internal_link_continuity',
            ],
        ]);

        return 1;
    }

    private function integerValue(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
