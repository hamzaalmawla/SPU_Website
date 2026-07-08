<?php

declare(strict_types=1);

namespace App\Services\Legacy;

use App\Contracts\Legacy\LegacyIntegrityInspectionServiceInterface;
use App\DTOs\Legacy\LegacyIntegrityInspectionResultDTO;
use App\Models\Shared\MigrationRejection;
use App\Support\LegacyImport\OldDatabaseConnection;
use Throwable;

final class LegacyIntegrityInspectionService implements LegacyIntegrityInspectionServiceInterface
{
    public function __construct(
        private readonly OldDatabaseConnection $oldDatabase,
    ) {}

    public function inspect(string $module, bool $recordQuarantine = false, ?int $limit = null): LegacyIntegrityInspectionResultDTO
    {
        $rules = $this->rulesForModule($module);

        if ($rules === []) {
            return new LegacyIntegrityInspectionResultDTO(
                module: $module,
                status: 'unknown_or_unconfigured_module',
                recordedQuarantine: $recordQuarantine,
                scannedRules: 0,
                duplicateGroups: 0,
                duplicateRows: 0,
                orphanRows: 0,
                blockedRows: 0,
                recordedRows: 0,
                issueCounts: [],
                warnings: ['No integrity inspection rules are configured for this module.'],
            );
        }

        $warnings = [];
        $issueCounts = [];
        $scannedRules = 0;
        $duplicateGroups = 0;
        $duplicateRows = 0;
        $orphanRows = 0;
        $recordedRows = 0;

        foreach ($rules['orphans'] as $rule) {
            $scannedRules++;
            $result = $this->inspectOrphanRule($module, $rule, $recordQuarantine, $limit, $warnings);
            $orphanRows += $result['blocked_rows'];
            $recordedRows += $result['recorded_rows'];
        }

        if ($orphanRows > 0) {
            $issueCounts['orphaned_child'] = $orphanRows;
        }

        foreach ($rules['duplicates'] as $rule) {
            $scannedRules++;
            $result = $this->inspectDuplicateRule($module, $rule, $recordQuarantine, $limit, $warnings);
            $duplicateGroups += $result['duplicate_groups'];
            $duplicateRows += $result['blocked_rows'];
            $recordedRows += $result['recorded_rows'];
        }

        if ($duplicateRows > 0) {
            $issueCounts['duplicate_legacy_content'] = $duplicateRows;
        }

        $blockedRows = $orphanRows + $duplicateRows;

        return new LegacyIntegrityInspectionResultDTO(
            module: $module,
            status: $blockedRows > 0 ? 'integrity_blockers_found' : 'integrity_passed',
            recordedQuarantine: $recordQuarantine,
            scannedRules: $scannedRules,
            duplicateGroups: $duplicateGroups,
            duplicateRows: $duplicateRows,
            orphanRows: $orphanRows,
            blockedRows: $blockedRows,
            recordedRows: $recordedRows,
            issueCounts: $issueCounts,
            warnings: array_values(array_unique($warnings)),
        );
    }

    /**
     * @return array{orphans: array<int, array<string, mixed>>, duplicates: array<int, array<string, mixed>>}
     */
    private function rulesForModule(string $module): array
    {
        $configured = config('old_database.integrity_inspection_rules.'.$module, []);

        if (! is_array($configured)) {
            return [];
        }

        $orphans = is_array($configured['orphans'] ?? null) ? array_values($configured['orphans']) : [];
        $duplicates = is_array($configured['duplicates'] ?? null) ? array_values($configured['duplicates']) : [];

        if ($orphans === [] && $duplicates === []) {
            return [];
        }

        return [
            'orphans' => $orphans,
            'duplicates' => $duplicates,
        ];
    }

    /**
     * @param array<string, mixed> $rule
     * @param array<int, string> $warnings
     * @return array{blocked_rows: int, recorded_rows: int}
     */
    private function inspectOrphanRule(string $module, array $rule, bool $recordQuarantine, ?int $limit, array &$warnings): array
    {
        $childTable = $this->stringValue($rule['child_table'] ?? null);
        $childIdColumn = $this->stringValue($rule['child_id_column'] ?? null) ?? 'id';
        $childParentColumn = $this->stringValue($rule['child_parent_column'] ?? null);
        $parentTable = $this->stringValue($rule['parent_table'] ?? null);
        $parentIdColumn = $this->stringValue($rule['parent_id_column'] ?? null) ?? 'id';

        if ($childTable === null || $childParentColumn === null || $parentTable === null) {
            $warnings[] = 'Invalid orphan integrity rule configuration.';

            return ['blocked_rows' => 0, 'recorded_rows' => 0];
        }

        foreach ([[$childTable, $childIdColumn], [$childTable, $childParentColumn], [$parentTable, $parentIdColumn]] as [$table, $column]) {
            if (! $this->columnExists($table, $column)) {
                $warnings[] = "Missing legacy integrity column [{$table}.{$column}].";

                return ['blocked_rows' => 0, 'recorded_rows' => 0];
            }
        }

        $parentIds = $this->oldDatabase->table($parentTable)
            ->select($parentIdColumn)
            ->get()
            ->map(fn (object $row): ?string => $this->normalizedKeyPart($row->{$parentIdColumn} ?? null))
            ->filter()
            ->flip()
            ->all();

        $query = $this->oldDatabase->table($childTable)
            ->select([$childIdColumn, $childParentColumn])
            ->whereNotNull($childParentColumn)
            ->orderBy($childIdColumn);

        if ($limit !== null && $limit > 0) {
            $query->limit($limit);
        }

        $blockedRows = 0;
        $recordedRows = 0;

        foreach ($query->get() as $row) {
            $parentId = $this->normalizedKeyPart($row->{$childParentColumn} ?? null);

            if ($parentId === null || isset($parentIds[$parentId])) {
                continue;
            }

            $blockedRows++;
            $sourceId = $this->integerValue($row->{$childIdColumn} ?? null);

            if ($recordQuarantine) {
                $recordedRows += $this->recordQuarantine(
                    module: $module,
                    sourceTable: $childTable,
                    sourceId: $sourceId,
                    reasonCode: 'orphaned_child',
                    reasonMessage: "{$childTable}.{$childParentColumn} references missing {$parentTable}.{$parentIdColumn} [{$parentId}].",
                    rawSummary: [
                        'rule' => 'orphan',
                        'child_parent_column' => $childParentColumn,
                        'missing_parent_table' => $parentTable,
                        'missing_parent_id' => $parentId,
                    ],
                );
            }
        }

        return ['blocked_rows' => $blockedRows, 'recorded_rows' => $recordedRows];
    }

    /**
     * @param array<string, mixed> $rule
     * @param array<int, string> $warnings
     * @return array{duplicate_groups: int, blocked_rows: int, recorded_rows: int}
     */
    private function inspectDuplicateRule(string $module, array $rule, bool $recordQuarantine, ?int $limit, array &$warnings): array
    {
        $table = $this->stringValue($rule['table'] ?? null);
        $idColumn = $this->stringValue($rule['id_column'] ?? null) ?? 'id';
        $columns = is_array($rule['columns'] ?? null) ? array_values(array_filter($rule['columns'], 'is_string')) : [];
        $ignoredValues = $this->normalizedIgnoredValues($rule['ignored_values'] ?? []);

        if ($table === null || $columns === []) {
            $warnings[] = 'Invalid duplicate integrity rule configuration.';

            return ['duplicate_groups' => 0, 'blocked_rows' => 0, 'recorded_rows' => 0];
        }

        foreach (array_merge([$idColumn], $columns) as $column) {
            if (! $this->columnExists($table, $column)) {
                $warnings[] = "Missing legacy integrity column [{$table}.{$column}].";

                return ['duplicate_groups' => 0, 'blocked_rows' => 0, 'recorded_rows' => 0];
            }
        }

        $query = $this->oldDatabase->table($table)
            ->select(array_values(array_unique(array_merge([$idColumn], $columns))))
            ->orderBy($idColumn);

        if ($limit !== null && $limit > 0) {
            $query->limit($limit);
        }

        $groups = [];

        foreach ($query->get() as $row) {
            $parts = [];

            foreach ($columns as $column) {
                $part = $this->normalizedKeyPart($row->{$column} ?? null);

                if ($part === null) {
                    continue 2;
                }

                if (isset($ignoredValues[$part])) {
                    continue 2;
                }

                $parts[] = $part;
            }

            $key = implode('|', $parts);
            $groups[$key][] = [
                'source_id' => $this->integerValue($row->{$idColumn} ?? null),
                'key' => $key,
            ];
        }

        $duplicateGroups = 0;
        $blockedRows = 0;
        $recordedRows = 0;

        foreach ($groups as $key => $rows) {
            if (count($rows) < 2) {
                continue;
            }

            $duplicateGroups++;
            $blockedRows += count($rows);

            if (! $recordQuarantine) {
                continue;
            }

            foreach ($rows as $row) {
                $recordedRows += $this->recordQuarantine(
                    module: $module,
                    sourceTable: $table,
                    sourceId: $row['source_id'],
                    reasonCode: 'duplicate_legacy_content',
                    reasonMessage: $table.' duplicate key ['.implode(', ', $columns).'] = '.$key.'.',
                    rawSummary: [
                        'rule' => 'duplicate',
                        'columns' => $columns,
                        'duplicate_key' => $key,
                    ],
                );
            }
        }

        return ['duplicate_groups' => $duplicateGroups, 'blocked_rows' => $blockedRows, 'recorded_rows' => $recordedRows];
    }

    private function columnExists(string $table, string $column): bool
    {
        try {
            return $this->oldDatabase->connection()->getSchemaBuilder()->hasColumn($table, $column);
        } catch (Throwable) {
            return false;
        }
    }

    /** @param array<string, mixed> $rawSummary */
    private function recordQuarantine(string $module, string $sourceTable, ?int $sourceId, string $reasonCode, string $reasonMessage, array $rawSummary): int
    {
        $exists = MigrationRejection::query()
            ->where('module', $module)
            ->where('source_table', $sourceTable)
            ->where('source_id', $sourceId)
            ->where('reason_code', $reasonCode)
            ->where('reason_message', $reasonMessage)
            ->exists();

        if ($exists) {
            return 0;
        }

        MigrationRejection::query()->create([
            'module' => $module,
            'source_table' => $sourceTable,
            'source_id' => $sourceId,
            'reason_code' => $reasonCode,
            'reason_message' => $reasonMessage,
            'raw_summary' => $rawSummary,
        ]);

        return 1;
    }

    private function stringValue(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function normalizedKeyPart(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $normalized = mb_strtolower(trim((string) $value));
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        return $normalized !== '' ? $normalized : null;
    }

    /** @return array<string, true> */
    private function normalizedIgnoredValues(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        $normalized = [];

        foreach ($values as $value) {
            $part = $this->normalizedKeyPart($value);

            if ($part !== null) {
                $normalized[$part] = true;
            }
        }

        return $normalized;
    }

    private function integerValue(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
