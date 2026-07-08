<?php

declare(strict_types=1);

namespace App\Services\Legacy;

use App\Contracts\Legacy\LegacyClassificationReportServiceInterface;
use App\DTOs\Legacy\LegacyClassificationReportResultDTO;
use App\Models\Shared\MigrationRejection;
use App\Support\LegacyImport\OldDatabaseConnection;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class LegacyClassificationReportService implements LegacyClassificationReportServiceInterface
{
    /** @var array<int, string> */
    private const BUCKETS = [
        'canonical_rebuild_now',
        'archive_now_remodel_later',
        'redirect_to_equivalent',
        'file_only_preserve',
        'quarantine',
        'retire_after_approval',
    ];

    public function __construct(
        private readonly OldDatabaseConnection $oldDatabase,
    ) {}

    public function export(
        ?string $module = null,
        ?int $limit = null,
        string $disk = 'local',
        string $directory = 'legacy-import-exports/classification',
    ): LegacyClassificationReportResultDTO {
        $module = $this->normalizedFilter($module);
        $directory = trim($directory, '/');
        $directory = $directory !== '' ? $directory : 'legacy-import-exports/classification';
        $rules = $this->configuredRules();

        if ($module !== null && ! array_key_exists($module, $rules)) {
            return new LegacyClassificationReportResultDTO(
                module: $module,
                status: 'unknown_or_unconfigured_module',
                disk: $disk,
                tableCount: 0,
                sourceRowCount: 0,
                classifiedRowCount: 0,
                unknownRowCount: 0,
                highRiskTableCount: 0,
                highRiskTablesCovered: 0,
                bucketCounts: [],
                warnings: ['Legacy classification module is not configured.'],
                paths: [],
            );
        }

        $selectedRules = $module !== null ? [$module => $rules[$module]] : $rules;
        $phaseThreeReasons = $this->phaseThreeReasonMap($module);
        $mappingRows = [];
        $summaryRows = [];
        $warnings = [];
        $tableCount = 0;
        $sourceRowCount = 0;
        $highRiskTableCount = 0;
        $highRiskTablesCovered = 0;

        foreach ($selectedRules as $moduleName => $moduleRules) {
            foreach ($moduleRules as $table => $rule) {
                if (! is_array($rule)) {
                    continue;
                }

                $tableCount++;
                $highRisk = filter_var($rule['high_risk'] ?? false, FILTER_VALIDATE_BOOL);
                $highRiskTableCount += $highRisk ? 1 : 0;
                $highRiskTablesCovered += $highRisk && $this->validBucket($rule['bucket'] ?? null) ? 1 : 0;

                $inspection = $this->inspectTable($moduleName, (string) $table, $rule, $phaseThreeReasons, $limit);
                $sourceRowCount += $inspection['source_row_count'];
                $warnings = array_merge($warnings, $inspection['warnings']);
                $mappingRows = array_merge($mappingRows, $inspection['mapping_rows']);
                $summaryRows = array_merge($summaryRows, $this->summaryRows($moduleName, (string) $table, $rule, $inspection));
            }
        }

        $bucketCounts = collect($mappingRows)->countBy('classification')->all();
        $unknownRowCount = (int) ($bucketCounts['quarantine'] ?? 0);
        $stamp = now()->format('Ymd_His');
        $suffix = $module !== null ? '_'.$this->filenamePart($module) : '';
        $basePath = $directory.'/'.$stamp.'_classification_report'.$suffix;
        $paths = [
            $basePath.'.md',
            $basePath.'_tables.csv',
            $basePath.'_mapping.csv',
            $basePath.'.json',
        ];

        Storage::disk($disk)->put($paths[0], $this->markdown($module, $summaryRows, $bucketCounts, $warnings));
        Storage::disk($disk)->put($paths[1], $this->csvPayload($summaryRows, [
            'module',
            'source_table',
            'source_rows',
            'scanned_rows',
            'classification',
            'classified_rows',
            'high_risk',
            'rule_key',
            'notes',
        ]));
        Storage::disk($disk)->put($paths[2], $this->csvPayload($mappingRows, [
            'module',
            'source_table',
            'source_id',
            'legacy_key',
            'classification',
            'target_module',
            'target_type',
            'confidence',
            'phase3_reasons',
            'file_dependency',
            'identity',
            'url',
            'date',
            'high_risk',
            'rule_key',
            'notes',
        ]));
        Storage::disk($disk)->put($paths[3], $this->json([
            'generated_at' => now()->toIso8601String(),
            'module' => $module,
            'summary' => [
                'table_count' => $tableCount,
                'source_row_count' => $sourceRowCount,
                'classified_row_count' => count($mappingRows),
                'unknown_row_count' => $unknownRowCount,
                'high_risk_table_count' => $highRiskTableCount,
                'high_risk_tables_covered' => $highRiskTablesCovered,
                'bucket_counts' => $bucketCounts,
                'warnings' => array_values(array_unique($warnings)),
            ],
            'tables' => $summaryRows,
        ]));

        return new LegacyClassificationReportResultDTO(
            module: $module,
            status: $warnings === [] ? 'classification_report_created' : 'classification_report_created_with_warnings',
            disk: $disk,
            tableCount: $tableCount,
            sourceRowCount: $sourceRowCount,
            classifiedRowCount: count($mappingRows),
            unknownRowCount: $unknownRowCount,
            highRiskTableCount: $highRiskTableCount,
            highRiskTablesCovered: $highRiskTablesCovered,
            bucketCounts: $bucketCounts,
            warnings: array_values(array_unique($warnings)),
            paths: $paths,
        );
    }

    /** @return array<string, array<string, array<string, mixed>>> */
    private function configuredRules(): array
    {
        $rules = config('old_database.classification_rules', []);

        return is_array($rules) ? $rules : [];
    }

    /** @param array<string, mixed> $rule @param array<string, array<int, array<int, string>>> $phaseThreeReasons @return array{source_row_count: int, mapping_rows: array<int, array<string, mixed>>, warnings: array<int, string>} */
    private function inspectTable(string $module, string $table, array $rule, array $phaseThreeReasons, ?int $limit): array
    {
        $warnings = [];
        $mappingRows = [];
        $idColumn = is_string($rule['id_column'] ?? null) ? $rule['id_column'] : 'id';

        try {
            $connection = $this->oldDatabase->connection();

            if (! $connection->getSchemaBuilder()->hasTable($table)) {
                return [
                    'source_row_count' => 0,
                    'mapping_rows' => [],
                    'warnings' => ["Missing legacy classification table [{$table}]."],
                ];
            }

            $columns = $connection->getSchemaBuilder()->getColumnListing($table);
            if (! in_array($idColumn, $columns, true)) {
                return [
                    'source_row_count' => 0,
                    'mapping_rows' => [],
                    'warnings' => ["Missing legacy classification id column [{$table}.{$idColumn}]."],
                ];
            }

            $sourceRowCount = (int) $this->oldDatabase->table($table)->count();
            $selectColumns = $this->selectColumns($rule, $columns, $idColumn, $table, $warnings);
            $query = $this->oldDatabase->table($table)->select($selectColumns)->orderBy($idColumn);

            if ($limit !== null) {
                $query->limit(max(1, $limit));
            }

            foreach ($query->get() as $row) {
                $sourceId = (int) ($row->{$idColumn} ?? 0);
                $mappingRows[] = $this->mappingRow($module, $table, $sourceId, $row, $rule, $phaseThreeReasons[$module][$table][$sourceId] ?? []);
            }

            return [
                'source_row_count' => $sourceRowCount,
                'mapping_rows' => $mappingRows,
                'warnings' => $warnings,
            ];
        } catch (Throwable $exception) {
            return [
                'source_row_count' => 0,
                'mapping_rows' => [],
                'warnings' => ["Could not inspect legacy classification table [{$table}]: {$exception->getMessage()}"],
            ];
        }
    }

    /** @param array<string, mixed> $rule @param array<int, string> $availableColumns @param array<int, string> $warnings @return array<int, string> */
    private function selectColumns(array $rule, array $availableColumns, string $idColumn, string $table, array &$warnings): array
    {
        $requested = [$idColumn];

        foreach (['identity_columns', 'file_columns', 'url_columns', 'date_columns'] as $key) {
            $columns = $rule[$key] ?? [];
            $columns = is_array($columns) ? array_values(array_filter($columns, 'is_string')) : [];

            foreach ($columns as $column) {
                if (in_array($column, $availableColumns, true)) {
                    $requested[] = $column;

                    continue;
                }

                $warnings[] = "Missing legacy classification column [{$table}.{$column}].";
            }
        }

        return array_values(array_unique($requested));
    }

    /** @param array<string, mixed> $rule @param array<int, string> $phaseThreeReasons @return array<string, mixed> */
    private function mappingRow(string $module, string $table, int $sourceId, object $row, array $rule, array $phaseThreeReasons): array
    {
        $bucket = $this->validBucket($rule['bucket'] ?? null) ? (string) $rule['bucket'] : 'quarantine';
        $ruleKey = is_string($rule['rule_key'] ?? null) ? $rule['rule_key'] : 'missing_rule_key';
        $notes = is_string($rule['notes'] ?? null) ? $rule['notes'] : 'No migration notes configured.';
        $identity = $this->firstScalarValue($row, $rule['identity_columns'] ?? []);
        $url = $this->firstScalarValue($row, $rule['url_columns'] ?? []);
        $date = $this->firstScalarValue($row, $rule['date_columns'] ?? []);

        return [
            'module' => $module,
            'source_table' => $table,
            'source_id' => $sourceId,
            'legacy_key' => $this->legacyKey($table, $sourceId, $identity, $url, $date, $ruleKey),
            'classification' => $bucket,
            'target_module' => $this->targetModule($module, $bucket),
            'target_type' => $this->targetType($bucket),
            'confidence' => $this->confidence($bucket, $phaseThreeReasons),
            'phase3_reasons' => implode('|', array_values(array_unique($phaseThreeReasons))),
            'file_dependency' => $this->fileDependency($row, $rule),
            'identity' => $identity,
            'url' => $url,
            'date' => $date,
            'high_risk' => filter_var($rule['high_risk'] ?? false, FILTER_VALIDATE_BOOL) ? 'yes' : 'no',
            'rule_key' => $ruleKey,
            'notes' => $notes,
        ];
    }

    /** @param array<string, mixed> $rule @param array{source_row_count: int, mapping_rows: array<int, array<string, mixed>>, warnings: array<int, string>} $inspection @return array<int, array<string, mixed>> */
    private function summaryRows(string $module, string $table, array $rule, array $inspection): array
    {
        $counts = collect($inspection['mapping_rows'])->countBy('classification')->all();
        $rows = [];

        foreach (self::BUCKETS as $bucket) {
            $count = (int) ($counts[$bucket] ?? 0);

            if ($count === 0) {
                continue;
            }

            $rows[] = [
                'module' => $module,
                'source_table' => $table,
                'source_rows' => $inspection['source_row_count'],
                'scanned_rows' => count($inspection['mapping_rows']),
                'classification' => $bucket,
                'classified_rows' => $count,
                'high_risk' => filter_var($rule['high_risk'] ?? false, FILTER_VALIDATE_BOOL) ? 'yes' : 'no',
                'rule_key' => is_string($rule['rule_key'] ?? null) ? $rule['rule_key'] : 'missing_rule_key',
                'notes' => is_string($rule['notes'] ?? null) ? $rule['notes'] : 'No migration notes configured.',
            ];
        }

        if ($rows === []) {
            $rows[] = [
                'module' => $module,
                'source_table' => $table,
                'source_rows' => $inspection['source_row_count'],
                'scanned_rows' => 0,
                'classification' => $this->validBucket($rule['bucket'] ?? null) ? (string) $rule['bucket'] : 'quarantine',
                'classified_rows' => 0,
                'high_risk' => filter_var($rule['high_risk'] ?? false, FILTER_VALIDATE_BOOL) ? 'yes' : 'no',
                'rule_key' => is_string($rule['rule_key'] ?? null) ? $rule['rule_key'] : 'missing_rule_key',
                'notes' => is_string($rule['notes'] ?? null) ? $rule['notes'] : 'No migration notes configured.',
            ];
        }

        return $rows;
    }

    /** @return array<string, array<string, array<int, array<int, string>>>> */
    private function phaseThreeReasonMap(?string $module): array
    {
        $rows = MigrationRejection::query()
            ->when($module !== null, fn ($query) => $query->where('module', $module))
            ->get(['module', 'source_table', 'source_id', 'reason_code']);
        $map = [];

        foreach ($rows as $row) {
            $sourceId = is_numeric($row->source_id) ? (int) $row->source_id : 0;
            if ($sourceId <= 0) {
                continue;
            }

            $map[$row->module][$row->source_table][$sourceId][] = $row->reason_code;
        }

        return $map;
    }

    /** @param array<string, mixed> $rule */
    private function fileDependency(object $row, array $rule): string
    {
        $fileColumns = $rule['file_columns'] ?? [];
        $fileColumns = is_array($fileColumns) ? array_values(array_filter($fileColumns, 'is_string')) : [];

        foreach ($fileColumns as $column) {
            $value = $row->{$column} ?? null;

            if (is_scalar($value) && trim((string) $value) !== '') {
                return $this->hasConfiguredLegacyFileRoot() ? 'requires_file_reconciliation' : 'missing_external_source_root';
            }
        }

        return 'none';
    }

    private function hasConfiguredLegacyFileRoot(): bool
    {
        $root = env('OLD_PUBLIC_ROOT');

        return is_string($root) && trim($root) !== '';
    }

    /** @param mixed $columns */
    private function firstScalarValue(object $row, mixed $columns): string
    {
        $columns = is_array($columns) ? array_values(array_filter($columns, 'is_string')) : [];

        foreach ($columns as $column) {
            $value = $row->{$column} ?? null;

            if (is_scalar($value) && trim((string) $value) !== '') {
                return $this->preview((string) $value);
            }
        }

        return '';
    }

    private function validBucket(mixed $bucket): bool
    {
        return is_string($bucket) && in_array($bucket, self::BUCKETS, true);
    }

    private function legacyKey(string $table, int $sourceId, string $identity, string $url, string $date, string $ruleKey): string
    {
        return $table.':'.$sourceId.':'.substr(hash('sha256', implode('|', [$identity, $url, $date, $ruleKey])), 0, 16);
    }

    private function targetModule(string $module, string $bucket): string
    {
        return match ($bucket) {
            'file_only_preserve' => 'media',
            'redirect_to_equivalent' => 'continuity',
            'retire_after_approval' => 'retired_legacy',
            'quarantine' => 'quarantine',
            default => $module,
        };
    }

    private function targetType(string $bucket): string
    {
        return match ($bucket) {
            'canonical_rebuild_now' => 'canonical_content_candidate',
            'archive_now_remodel_later' => 'archive_candidate',
            'redirect_to_equivalent' => 'redirect_candidate',
            'file_only_preserve' => 'legacy_file_candidate',
            'retire_after_approval' => 'retire_candidate',
            default => 'quarantine',
        };
    }

    /** @param array<int, string> $phaseThreeReasons */
    private function confidence(string $bucket, array $phaseThreeReasons): string
    {
        if ($bucket === 'quarantine' || $phaseThreeReasons !== []) {
            return 'low';
        }

        return in_array($bucket, ['canonical_rebuild_now', 'redirect_to_equivalent'], true) ? 'medium' : 'low';
    }

    /** @param array<int, array<string, mixed>> $summaryRows @param array<string, int> $bucketCounts @param array<int, string> $warnings */
    private function markdown(?string $module, array $summaryRows, array $bucketCounts, array $warnings): string
    {
        $lines = [
            '# Legacy Classification Report',
            '',
            '- Module: '.($module ?? 'all'),
            '- Generated: '.now()->toIso8601String(),
            '',
            '## Bucket Counts',
            '',
        ];

        foreach (self::BUCKETS as $bucket) {
            $lines[] = '- `'.$bucket.'`: '.(int) ($bucketCounts[$bucket] ?? 0);
        }

        $lines[] = '';
        $lines[] = '## Table Summary';
        $lines[] = '';
        $lines[] = '| Module | Table | Classification | Rows | High Risk |';
        $lines[] = '| --- | --- | --- | ---: | --- |';

        foreach ($summaryRows as $row) {
            $lines[] = '| '.$row['module'].' | `'.$row['source_table'].'` | `'.$row['classification'].'` | '.$row['classified_rows'].' | '.$row['high_risk'].' |';
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

    private function preview(string $value): string
    {
        return mb_substr(preg_replace('/\s+/u', ' ', trim(strip_tags($value))) ?? trim($value), 0, 180);
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
