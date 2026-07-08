<?php

declare(strict_types=1);

namespace App\Services\Legacy;

use App\Contracts\Legacy\LegacyQuarantineExportServiceInterface;
use App\DTOs\Legacy\LegacyQuarantineExportResultDTO;
use App\Models\Shared\MigrationRejection;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;

final class LegacyQuarantineExportService implements LegacyQuarantineExportServiceInterface
{
    public function export(
        ?string $module = null,
        ?string $reasonCode = null,
        string $format = 'csv',
        string $disk = 'local',
        string $directory = 'legacy-import-exports/quarantine',
    ): LegacyQuarantineExportResultDTO {
        $format = strtolower(trim($format));

        if (! in_array($format, ['csv', 'json'], true)) {
            throw new InvalidArgumentException('Quarantine export format must be csv or json.');
        }

        $module = $this->normalizedFilter($module);
        $reasonCode = $this->normalizedFilter($reasonCode);
        $directory = trim($directory, '/');
        $directory = $directory !== '' ? $directory : 'legacy-import-exports/quarantine';

        $rejections = MigrationRejection::query()
            ->when($module !== null, fn ($query) => $query->where('module', $module))
            ->when($reasonCode !== null, fn ($query) => $query->where('reason_code', $reasonCode))
            ->orderBy('module')
            ->orderBy('source_table')
            ->orderBy('source_id')
            ->orderBy('reason_code')
            ->orderBy('id')
            ->get(['id', 'module', 'source_table', 'source_id', 'reason_code', 'reason_message', 'raw_summary', 'created_at']);

        $rows = $rejections
            ->map(fn (MigrationRejection $rejection): array => $this->exportRow($rejection))
            ->values()
            ->all();

        $path = $directory.'/'.now()->format('Ymd_His').$this->filenameSuffix($module, $reasonCode).'.'.$format;

        Storage::disk($disk)->put(
            $path,
            $format === 'json'
                ? $this->jsonPayload($rows, $module, $reasonCode)
                : $this->csvPayload($rows),
        );

        return new LegacyQuarantineExportResultDTO(
            disk: $disk,
            path: $path,
            format: $format,
            module: $module,
            reasonCode: $reasonCode,
            rowCount: count($rows),
            moduleCounts: $rejections->groupBy('module')->map->count()->all(),
            reasonCounts: $rejections->groupBy('reason_code')->map->count()->all(),
        );
    }

    /** @return array<string, mixed> */
    private function exportRow(MigrationRejection $rejection): array
    {
        $summary = is_array($rejection->raw_summary) ? $rejection->raw_summary : [];

        return [
            'id' => $rejection->id,
            'created_at' => $rejection->created_at?->toIso8601String(),
            'module' => $rejection->module,
            'source_table' => $rejection->source_table,
            'source_id' => $rejection->source_id,
            'reason_code' => $rejection->reason_code,
            'reason_message' => $rejection->reason_message,
            'field' => $this->stringSummaryValue($summary, 'field'),
            'decision' => $this->stringSummaryValue($summary, 'decision'),
            'issue_codes' => $this->encodedSummaryValue($summary, 'issue_codes'),
            'review_type' => $this->stringSummaryValue($summary, 'review_type'),
            'legacy_path' => $this->stringSummaryValue($summary, 'legacy_path'),
            'original_preview' => $this->stringSummaryValue($summary, 'original_preview'),
            'cleaned_preview' => $this->stringSummaryValue($summary, 'cleaned_preview'),
            'raw_summary_json' => $summary !== [] ? $this->encode($summary) : null,
        ];
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function jsonPayload(array $rows, ?string $module, ?string $reasonCode): string
    {
        return $this->encode([
            'summary' => [
                'generated_at' => now()->toIso8601String(),
                'environment' => app()->environment(),
                'module' => $module,
                'reason_code' => $reasonCode,
                'row_count' => count($rows),
            ],
            'rows' => $rows,
        ], pretty: true);
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function csvPayload(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            throw new RuntimeException('Unable to create quarantine CSV stream.');
        }

        $headers = [
            'id',
            'created_at',
            'module',
            'source_table',
            'source_id',
            'reason_code',
            'reason_message',
            'field',
            'decision',
            'issue_codes',
            'review_type',
            'legacy_path',
            'original_preview',
            'cleaned_preview',
            'raw_summary_json',
        ];

        fputcsv($handle, $headers);

        foreach ($rows as $row) {
            fputcsv($handle, array_map(
                static fn (mixed $value): string => is_scalar($value) ? (string) $value : '',
                array_replace(array_fill_keys($headers, null), $row),
            ));
        }

        rewind($handle);
        $contents = stream_get_contents($handle);
        fclose($handle);

        return $contents !== false ? $contents : '';
    }

    private function normalizedFilter(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    private function filenameSuffix(?string $module, ?string $reasonCode): string
    {
        $parts = array_filter([$module, $reasonCode], static fn (?string $value): bool => $value !== null);

        if ($parts === []) {
            return '_quarantine_review';
        }

        return '_quarantine_review_'.implode('_', array_map(
            static fn (string $value): string => preg_replace('/[^a-z0-9_-]+/i', '-', $value) ?: 'filter',
            $parts,
        ));
    }

    /** @param array<string, mixed> $summary */
    private function stringSummaryValue(array $summary, string $key): ?string
    {
        $value = $summary[$key] ?? null;

        return is_scalar($value) ? (string) $value : null;
    }

    /** @param array<string, mixed> $summary */
    private function encodedSummaryValue(array $summary, string $key): ?string
    {
        $value = $summary[$key] ?? null;

        if ($value === null || is_scalar($value)) {
            return $value !== null ? (string) $value : null;
        }

        return $this->encode($value);
    }

    private function encode(mixed $value, bool $pretty = false): string
    {
        return (string) json_encode(
            $value,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | ($pretty ? JSON_PRETTY_PRINT : 0),
        );
    }
}
