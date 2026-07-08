<?php

declare(strict_types=1);

namespace App\Services\Legacy;

use App\Contracts\Legacy\LegacyCleaningInspectionServiceInterface;
use App\Contracts\Legacy\LegacyContentCleaningServiceInterface;
use App\DTOs\Legacy\LegacyCleaningDecisionDTO;
use App\DTOs\Legacy\LegacyCleaningInspectionResultDTO;
use App\Models\Shared\MigrationRejection;
use App\Support\LegacyImport\OldDatabaseConnection;
use Throwable;

final class LegacyCleaningInspectionService implements LegacyCleaningInspectionServiceInterface
{
    public function __construct(
        private readonly OldDatabaseConnection $oldDatabase,
        private readonly LegacyContentCleaningServiceInterface $cleaningService,
    ) {}

    public function inspect(string $module, bool $recordQuarantine = false, ?int $limit = null): LegacyCleaningInspectionResultDTO
    {
        $definitions = $this->definitionsForModule($module);

        if ($definitions === []) {
            return new LegacyCleaningInspectionResultDTO(
                module: $module,
                status: 'unknown_or_unconfigured_module',
                recordedQuarantine: $recordQuarantine,
                scannedRows: 0,
                scannedFields: 0,
                publiclyImportableFields: 0,
                blockedFields: 0,
                recordedRows: 0,
                decisionCounts: [],
                issueCounts: [],
                warnings: ['No cleaning inspection fields are configured for this module.'],
            );
        }

        $scannedRowKeys = [];
        $scannedFields = 0;
        $publiclyImportableFields = 0;
        $blockedFields = 0;
        $recordedRows = 0;
        $decisionCounts = [];
        $issueCounts = [];
        $warnings = [];

        foreach ($definitions as $definition) {
            $table = $definition['table'];
            $idColumn = $definition['id_column'];
            $fields = $definition['fields'];

            if (! $this->tableExists($table)) {
                $warnings[] = "Missing legacy table [{$table}].";

                continue;
            }

            if (! $this->columnExists($table, $idColumn)) {
                $warnings[] = "Missing legacy ID column [{$table}.{$idColumn}].";

                continue;
            }

            $availableFields = [];

            foreach ($fields as $field) {
                if (! $this->columnExists($table, $field['column'])) {
                    $warnings[] = "Missing legacy cleaning column [{$table}.{$field['column']}].";

                    continue;
                }

                $availableFields[] = $field;
            }

            if ($availableFields === []) {
                continue;
            }

            $columns = array_values(array_unique(array_merge([$idColumn], array_column($availableFields, 'column'))));
            $query = $this->oldDatabase->table($table)->select($columns)->orderBy($idColumn);

            if ($limit !== null && $limit > 0) {
                $query->limit($limit);
            }

            foreach ($query->get() as $row) {
                $sourceId = $this->integerValue($row->{$idColumn} ?? null);
                $scannedRowKeys[$table.':'.($sourceId ?? spl_object_id($row))] = true;

                foreach ($availableFields as $field) {
                    $decision = $this->cleanField(
                        type: $field['type'],
                        value: $row->{$field['column']} ?? null,
                        field: $field['column'],
                        required: $field['required'],
                    );

                    $scannedFields++;
                    $decisionCounts[$decision->decision] = ($decisionCounts[$decision->decision] ?? 0) + 1;

                    foreach ($decision->issueCodes as $issueCode) {
                        $issueCounts[$issueCode] = ($issueCounts[$issueCode] ?? 0) + 1;
                    }

                    if ($decision->canImportPublicly) {
                        $publiclyImportableFields++;

                        continue;
                    }

                    $blockedFields++;

                    if ($recordQuarantine) {
                        $recordedRows += $this->recordQuarantine($module, $table, $sourceId, $decision);
                    }
                }
            }
        }

        return new LegacyCleaningInspectionResultDTO(
            module: $module,
            status: $blockedFields > 0 ? 'quarantine_required' : 'cleaning_passed',
            recordedQuarantine: $recordQuarantine,
            scannedRows: count($scannedRowKeys),
            scannedFields: $scannedFields,
            publiclyImportableFields: $publiclyImportableFields,
            blockedFields: $blockedFields,
            recordedRows: $recordedRows,
            decisionCounts: $decisionCounts,
            issueCounts: $issueCounts,
            warnings: array_values(array_unique($warnings)),
        );
    }

    /**
     * @return array<int, array{table: string, id_column: string, fields: array<int, array{column: string, type: string, required: bool}>}>
     */
    private function definitionsForModule(string $module): array
    {
        $configured = config('old_database.cleaning_inspection_fields.'.$module, []);

        if (! is_array($configured)) {
            return [];
        }

        return collect($configured)
            ->map(function (mixed $definition): ?array {
                if (! is_array($definition) || ! is_string($definition['table'] ?? null)) {
                    return null;
                }

                $fields = collect(is_array($definition['fields'] ?? null) ? $definition['fields'] : [])
                    ->map(function (mixed $field): ?array {
                        if (! is_array($field) || ! is_string($field['column'] ?? null)) {
                            return null;
                        }

                        return [
                            'column' => $field['column'],
                            'type' => is_string($field['type'] ?? null) ? $field['type'] : 'text',
                            'required' => filter_var($field['required'] ?? false, FILTER_VALIDATE_BOOL),
                        ];
                    })
                    ->filter()
                    ->values()
                    ->all();

                return [
                    'table' => $definition['table'],
                    'id_column' => is_string($definition['id_column'] ?? null) ? $definition['id_column'] : 'id',
                    'fields' => $fields,
                ];
            })
            ->filter(fn (?array $definition): bool => $definition !== null && $definition['fields'] !== [])
            ->values()
            ->all();
    }

    private function tableExists(string $table): bool
    {
        try {
            return $this->oldDatabase->connection()->getSchemaBuilder()->hasTable($table);
        } catch (Throwable) {
            return false;
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        try {
            return $this->oldDatabase->connection()->getSchemaBuilder()->hasColumn($table, $column);
        } catch (Throwable) {
            return false;
        }
    }

    private function cleanField(string $type, mixed $value, string $field, bool $required): LegacyCleaningDecisionDTO
    {
        $stringValue = is_scalar($value) ? (string) $value : null;

        return match ($type) {
            'html' => $this->cleaningService->cleanHtml($stringValue, $field, $required),
            'email' => $this->cleaningService->cleanEmail($stringValue, $field, $required),
            'date' => $this->cleaningService->cleanDate($value, $field),
            'locale' => $this->cleaningService->cleanLocale($stringValue, $field),
            'url' => $this->cleaningService->cleanUrl($stringValue, $field, $required),
            default => $this->cleaningService->cleanText($stringValue, $field, $required),
        };
    }

    private function recordQuarantine(string $module, string $sourceTable, ?int $sourceId, LegacyCleaningDecisionDTO $decision): int
    {
        $reasonCode = $decision->issueCodes[0] ?? $decision->decision;
        $reasonMessage = $decision->field.': '.($decision->messages[0] ?? 'Legacy value requires cleaning review.');

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
            'raw_summary' => [
                'field' => $decision->field,
                'decision' => $decision->decision,
                'issue_codes' => $decision->issueCodes,
                'messages' => $decision->messages,
                'original_preview' => $this->preview($decision->originalValue),
                'cleaned_preview' => $this->preview($decision->cleanedValue),
            ],
        ]);

        return 1;
    }

    private function integerValue(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function preview(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return mb_substr($value, 0, 500);
    }
}
