<?php

declare(strict_types=1);

namespace App\Services\Legacy;

use App\Contracts\Legacy\LegacyCleanedRowServiceInterface;
use App\Contracts\Legacy\LegacyContentCleaningServiceInterface;
use App\DTOs\Legacy\LegacyCleanedRowDTO;
use App\DTOs\Legacy\LegacyCleaningDecisionDTO;

final class LegacyCleanedRowService implements LegacyCleanedRowServiceInterface
{
    /** @var array<int, string> */
    private const APPROVED_CLEANING_ACTIONS = [
        'auto_accept_sanitized_html',
        'auto_strip_inline_base64_image',
        'auto_accept_cleaned_formatting',
        'auto_approve_cleaned',
    ];

    public function __construct(
        private readonly LegacyContentCleaningServiceInterface $cleaningService,
    ) {}

    public function cleanRow(string $module, string $sourceTable, object|array $row, array $approvedActionsByField = []): LegacyCleanedRowDTO
    {
        $fields = $this->fieldsFor($module, $sourceTable);
        $values = [];
        $decisions = [];
        $blockedFields = [];
        $issueCounts = [];

        foreach ($fields as $field) {
            $column = $field['column'];
            $decision = $this->cleanField(
                type: $field['type'],
                value: $this->rowValue($row, $column),
                field: $column,
                required: $field['required'],
            );
            $approvedAction = $approvedActionsByField[$column] ?? null;
            $allowed = $decision->canImportPublicly || $this->approvedCleaningAction($approvedAction);

            if ($allowed) {
                $values[$column] = $decision->cleanedValue;
            } else {
                $blockedFields[] = $column;
            }

            foreach ($decision->issueCodes as $issueCode) {
                $issueCounts[$issueCode] = ($issueCounts[$issueCode] ?? 0) + 1;
            }

            $decisions[] = $this->decisionPayload($decision, $allowed, $approvedAction);
        }

        return new LegacyCleanedRowDTO(
            module: $module,
            sourceTable: $sourceTable,
            values: $values,
            decisions: $decisions,
            blockedFields: array_values(array_unique($blockedFields)),
            issueCounts: $issueCounts,
            canImportPublicly: $blockedFields === [],
        );
    }

    /** @return array<int, array{column: string, type: string, required: bool}> */
    private function fieldsFor(string $module, string $sourceTable): array
    {
        $configured = config('old_database.cleaning_inspection_fields.'.$module, []);

        if (! is_array($configured)) {
            return [];
        }

        return collect($configured)
            ->filter(fn (mixed $definition): bool => is_array($definition) && ($definition['table'] ?? null) === $sourceTable)
            ->flatMap(fn (array $definition): array => is_array($definition['fields'] ?? null) ? $definition['fields'] : [])
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

    private function rowValue(object|array $row, string $column): mixed
    {
        if (is_array($row)) {
            return $row[$column] ?? null;
        }

        return $row->{$column} ?? null;
    }

    private function approvedCleaningAction(?string $action): bool
    {
        return is_string($action) && in_array($action, self::APPROVED_CLEANING_ACTIONS, true);
    }

    /** @return array<string, mixed> */
    private function decisionPayload(LegacyCleaningDecisionDTO $decision, bool $allowed, ?string $approvedAction): array
    {
        return [
            'field' => $decision->field,
            'decision' => $decision->decision,
            'can_import_publicly' => $allowed,
            'approved_action' => $approvedAction,
            'issue_codes' => $decision->issueCodes,
            'messages' => $decision->messages,
            'original_preview' => $this->preview($decision->originalValue),
            'cleaned_preview' => $this->preview($decision->cleanedValue),
        ];
    }

    private function preview(?string $value): ?string
    {
        return $value === null ? null : mb_substr($value, 0, 500);
    }
}
