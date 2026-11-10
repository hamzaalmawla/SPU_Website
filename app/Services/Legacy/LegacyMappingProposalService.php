<?php

declare(strict_types=1);

namespace App\Services\Legacy;

use App\Contracts\Legacy\LegacyMappingProposalServiceInterface;
use App\DTOs\Legacy\LegacyMappingProposalImportResultDTO;
use App\Models\Legacy\LegacyContentMapping;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

final class LegacyMappingProposalService implements LegacyMappingProposalServiceInterface
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

    public function importFromClassificationCsv(
        string $path,
        bool $write = false,
        string $disk = 'local',
    ): LegacyMappingProposalImportResultDTO {
        $path = trim($path);

        if ($path === '') {
            throw new InvalidArgumentException('Classification mapping CSV path is required.');
        }

        if (! Storage::disk($disk)->exists($path)) {
            throw new InvalidArgumentException('Classification mapping CSV was not found on the selected disk.');
        }

        $rows = $this->csvRows((string) Storage::disk($disk)->get($path));
        $warnings = [];
        $createdRows = 0;
        $updatedRows = 0;
        $skippedRows = 0;
        $proposals = [];

        foreach ($rows as $index => $row) {
            $proposal = $this->proposalFromRow($row, $index + 2, $warnings);

            if ($proposal === null) {
                $skippedRows++;

                continue;
            }

            $proposals[] = $proposal;

            if (! $write) {
                continue;
            }

            $existing = LegacyContentMapping::query()
                ->where('module', $proposal['module'])
                ->where('source_table', $proposal['source_table'])
                ->where('legacy_key', $proposal['legacy_key'])
                ->first();

            if ($existing instanceof LegacyContentMapping && $existing->mapping_status === 'approved') {
                $skippedRows++;

                continue;
            }

            if ($existing instanceof LegacyContentMapping) {
                $existing->fill($proposal)->save();
                $updatedRows++;

                continue;
            }

            LegacyContentMapping::query()->create($proposal);
            $createdRows++;
        }

        $classificationCounts = collect($proposals)->countBy('classification')->all();
        $targetTypeCounts = collect($proposals)->countBy('target_type')->all();

        return new LegacyMappingProposalImportResultDTO(
            path: $path,
            disk: $disk,
            written: $write,
            scannedRows: count($rows),
            proposedRows: count($proposals),
            createdRows: $createdRows,
            updatedRows: $updatedRows,
            skippedRows: $skippedRows,
            classificationCounts: $classificationCounts,
            targetTypeCounts: $targetTypeCounts,
            warnings: array_values(array_unique($warnings)),
        );
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

    /** @param array<string, string> $row @param array<int, string> $warnings @return array<string, mixed>|null */
    private function proposalFromRow(array $row, int $lineNumber, array &$warnings): ?array
    {
        $module = trim($row['module'] ?? '');
        $sourceTable = trim($row['source_table'] ?? '');
        $sourceId = trim($row['source_id'] ?? '');

        if ($module === '' || $sourceTable === '') {
            $warnings[] = "Skipping classification row {$lineNumber}: module and source_table are required.";

            return null;
        }

        $classification = trim($row['classification'] ?? 'quarantine');

        if (! in_array($classification, self::BUCKETS, true)) {
            $warnings[] = "Classification row {$lineNumber} used an unknown bucket and was forced to quarantine.";
            $classification = 'quarantine';
        }

        $identity = trim($row['identity'] ?? '');
        $url = trim($row['url'] ?? '');
        $date = trim($row['date'] ?? '');
        $ruleKey = trim($row['rule_key'] ?? '');
        $legacyKey = trim($row['legacy_key'] ?? '');
        $legacyKey = $legacyKey !== '' ? $legacyKey : $this->legacyKey($sourceTable, $sourceId, $identity, $url, $date, $ruleKey);
        $phase3Reasons = $this->listFromPipeString($row['phase3_reasons'] ?? '');
        $targetModule = trim($row['target_module'] ?? '');
        $targetType = trim($row['target_type'] ?? '');
        $confidence = trim($row['confidence'] ?? '');

        return [
            'module' => $module,
            'source_table' => $sourceTable,
            'source_id' => is_numeric($sourceId) ? (int) $sourceId : null,
            'legacy_key' => $legacyKey,
            'classification' => $classification,
            'mapping_status' => 'proposed',
            'target_module' => $targetModule !== '' ? $targetModule : $this->targetModule($module, $classification),
            'target_type' => $targetType !== '' ? $targetType : $this->targetType($classification),
            'target_identifier' => $identity !== '' ? $identity : null,
            'target_table' => null,
            'target_id' => null,
            'confidence' => in_array($confidence, ['low', 'medium', 'high'], true) ? $confidence : $this->confidence($classification, $phase3Reasons),
            'file_dependency' => $this->nullableString($row['file_dependency'] ?? ''),
            'phase3_reasons' => $phase3Reasons,
            'source_identity' => $this->nullableString($identity),
            'source_url' => $this->nullableString($url),
            'source_date' => $this->nullableString($date),
            'rule_key' => $this->nullableString($ruleKey),
            'notes' => $this->nullableString($row['notes'] ?? ''),
            'metadata' => [
                'imported_from' => 'classification_mapping_csv',
                'high_risk' => trim($row['high_risk'] ?? '') === 'yes',
            ],
            'approved_at' => null,
            'approved_by' => null,
        ];
    }

    /** @return array<int, string> */
    private function listFromPipeString(string $value): array
    {
        if (trim($value) === '') {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('trim', explode('|', $value)), static fn (string $item): bool => $item !== '')));
    }

    private function legacyKey(string $sourceTable, string $sourceId, string $identity, string $url, string $date, string $ruleKey): string
    {
        return $sourceTable.':'.($sourceId !== '' ? $sourceId : 'unknown').':'.substr(hash('sha256', implode('|', [$identity, $url, $date, $ruleKey])), 0, 16);
    }

    private function targetModule(string $module, string $classification): string
    {
        return match ($classification) {
            'file_only_preserve' => 'media',
            'redirect_to_equivalent' => 'continuity',
            'retire_after_approval' => 'retired_legacy',
            'quarantine' => 'quarantine',
            default => $module,
        };
    }

    private function targetType(string $classification): string
    {
        return match ($classification) {
            'canonical_rebuild_now' => 'canonical_content_candidate',
            'archive_now_remodel_later' => 'archive_candidate',
            'redirect_to_equivalent' => 'redirect_candidate',
            'file_only_preserve' => 'legacy_file_candidate',
            'retire_after_approval' => 'retire_candidate',
            default => 'quarantine',
        };
    }

    /** @param array<int, string> $phase3Reasons */
    private function confidence(string $classification, array $phase3Reasons): string
    {
        if ($classification === 'quarantine' || $phase3Reasons !== []) {
            return 'low';
        }

        return in_array($classification, ['canonical_rebuild_now', 'redirect_to_equivalent'], true) ? 'medium' : 'low';
    }

    private function nullableString(string $value): ?string
    {
        $value = trim($value);

        return $value !== '' ? $value : null;
    }
}
