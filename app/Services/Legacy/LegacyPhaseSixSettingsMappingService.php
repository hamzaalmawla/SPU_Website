<?php

declare(strict_types=1);

namespace App\Services\Legacy;

use App\Contracts\Legacy\LegacyPhaseSixSettingsMappingServiceInterface;
use App\DTOs\Legacy\LegacyPhaseSixSettingsMappingResultDTO;
use App\Models\Legacy\LegacyReviewItem;
use App\Support\LegacyImport\OldDatabaseConnection;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class LegacyPhaseSixSettingsMappingService implements LegacyPhaseSixSettingsMappingServiceInterface
{
    /** @var array<string, array{target_group: string, target_key: string, target_locale: string, value_shape: string, type: string}> */
    private const SAFE_MAPPINGS = [
        'twitter_link' => ['target_group' => 'footer', 'target_key' => 'social_contact', 'target_locale' => 'ar|en', 'value_shape' => 'social_link:twitter', 'type' => 'url'],
        'facebook_link' => ['target_group' => 'footer', 'target_key' => 'social_contact', 'target_locale' => 'ar|en', 'value_shape' => 'social_link:facebook', 'type' => 'url'],
        'youtub_link' => ['target_group' => 'footer', 'target_key' => 'social_contact', 'target_locale' => 'ar|en', 'value_shape' => 'social_link:youtube', 'type' => 'url'],
        'youtube_link' => ['target_group' => 'footer', 'target_key' => 'social_contact', 'target_locale' => 'ar|en', 'value_shape' => 'social_link:youtube', 'type' => 'url'],
        'google_link' => ['target_group' => 'footer', 'target_key' => 'social_contact', 'target_locale' => 'ar|en', 'value_shape' => 'social_link:google', 'type' => 'url'],
        'telegram_link' => ['target_group' => 'footer', 'target_key' => 'social_contact', 'target_locale' => 'ar|en', 'value_shape' => 'social_link:telegram', 'type' => 'url'],
        'whatsapp_link' => ['target_group' => 'footer', 'target_key' => 'social_contact', 'target_locale' => 'ar|en', 'value_shape' => 'social_link:whatsapp', 'type' => 'url'],
        'pinterest_link' => ['target_group' => 'footer', 'target_key' => 'social_contact', 'target_locale' => 'ar|en', 'value_shape' => 'social_link:pinterest', 'type' => 'url'],
        'instagram_link' => ['target_group' => 'footer', 'target_key' => 'social_contact', 'target_locale' => 'ar|en', 'value_shape' => 'social_link:instagram', 'type' => 'url'],
        'student_gate_link' => ['target_group' => 'navigation', 'target_key' => 'student_portal_url', 'target_locale' => '', 'value_shape' => 'text_url', 'type' => 'url'],
        'employee_email_link' => ['target_group' => 'navigation', 'target_key' => 'staff_access_url', 'target_locale' => '', 'value_shape' => 'text_url', 'type' => 'url'],
        'registration_link' => ['target_group' => 'navigation', 'target_key' => 'apply_cta', 'target_locale' => 'ar|en', 'value_shape' => 'apply_cta_url', 'type' => 'url'],
        'complaint_email' => ['target_group' => 'footer', 'target_key' => 'contact_links', 'target_locale' => 'ar|en', 'value_shape' => 'contact_email:complaints', 'type' => 'email'],
        'seek_job_email' => ['target_group' => 'footer', 'target_key' => 'contact_links', 'target_locale' => 'ar|en', 'value_shape' => 'contact_email:careers', 'type' => 'email'],
    ];

    public function __construct(
        private readonly OldDatabaseConnection $oldDatabase,
    ) {}

    public function export(string $disk = 'local', string $directory = 'legacy-import-exports/phase6-settings'): LegacyPhaseSixSettingsMappingResultDTO
    {
        $directory = trim($directory, '/');
        $directory = $directory !== '' ? $directory : 'legacy-import-exports/phase6-settings';
        $reviewItems = LegacyReviewItem::query()
            ->whereIn('source_table', ['jx_config', 'jx_config1'])
            ->orderBy('source_table')
            ->orderBy('source_id')
            ->get();
        $legacyRows = $this->legacyRows($reviewItems->pluck('source_table')->unique()->values()->all());
        $rows = [];

        foreach ($reviewItems as $item) {
            $legacy = $legacyRows[(string) $item->source_table][(int) $item->source_id] ?? [];
            $rows[] = $this->row($item, $legacy);
        }

        $rows = $this->applyDuplicateStatuses($rows);
        $safeRows = array_values(array_filter($rows, fn (array $row): bool => $row['mapping_status_detail'] === 'safe_mapping'));
        $backlogRows = array_values(array_filter($rows, fn (array $row): bool => ! in_array($row['mapping_status_detail'], ['safe_mapping', 'duplicate_conflict', 'unsafe_value'], true)));
        $duplicateRows = array_values(array_filter($rows, fn (array $row): bool => $row['mapping_status_detail'] === 'duplicate_conflict'));
        $unsafeRows = array_values(array_filter($rows, fn (array $row): bool => $row['mapping_status_detail'] === 'unsafe_value'));
        $statusCounts = collect($rows)->countBy('mapping_status_detail')->all();
        $targetCounts = collect($safeRows)
            ->map(fn (array $row): string => $row['target_group'].'.'.$row['target_key'])
            ->countBy()
            ->all();
        $stamp = now()->format('Ymd_His');
        $basePath = $directory.'/'.$stamp.'_phase6_settings_mapping';
        $headers = [
            'mapping_status_detail',
            'reason',
            'source_table',
            'source_id',
            'legacy_name',
            'legacy_label',
            'legacy_value',
            'normalized_value',
            'target_group',
            'target_key',
            'target_locale',
            'value_shape',
            'review_status',
            'source_mapping_status',
            'classification',
            'file_dependency',
            'blocked_reasons',
        ];
        $paths = [
            $basePath.'.md',
            $basePath.'_safe_mappings.csv',
            $basePath.'_backlog.csv',
            $basePath.'_duplicates.csv',
            $basePath.'_unsafe.csv',
            $basePath.'.json',
        ];

        Storage::disk($disk)->put($paths[0], $this->markdown(count($rows), count($safeRows), count($backlogRows), count($duplicateRows), count($unsafeRows), $statusCounts, $targetCounts));
        Storage::disk($disk)->put($paths[1], $this->csvPayload($safeRows, $headers));
        Storage::disk($disk)->put($paths[2], $this->csvPayload($backlogRows, $headers));
        Storage::disk($disk)->put($paths[3], $this->csvPayload($duplicateRows, $headers));
        Storage::disk($disk)->put($paths[4], $this->csvPayload($unsafeRows, $headers));
        Storage::disk($disk)->put($paths[5], $this->json([
            'generated_at' => now()->toIso8601String(),
            'summary' => [
                'scanned_rows' => count($rows),
                'safe_mapping_rows' => count($safeRows),
                'backlog_rows' => count($backlogRows),
                'duplicate_conflict_rows' => count($duplicateRows),
                'unsafe_value_rows' => count($unsafeRows),
                'status_counts' => $statusCounts,
                'target_counts' => $targetCounts,
            ],
        ]));

        return new LegacyPhaseSixSettingsMappingResultDTO(
            disk: $disk,
            scannedRows: count($rows),
            safeMappingRows: count($safeRows),
            backlogRows: count($backlogRows),
            duplicateConflictRows: count($duplicateRows),
            unsafeValueRows: count($unsafeRows),
            statusCounts: $statusCounts,
            targetCounts: $targetCounts,
            paths: $paths,
        );
    }

    /** @param array<int, string> $sourceTables @return array<string, array<int, array<string, mixed>>> */
    private function legacyRows(array $sourceTables): array
    {
        $rows = [];

        foreach ($sourceTables as $sourceTable) {
            $sourceTable = (string) $sourceTable;

            if (! in_array($sourceTable, ['jx_config', 'jx_config1'], true)) {
                continue;
            }

            try {
                $rows[$sourceTable] = $this->oldDatabase->table($sourceTable)
                    ->select(['id', 'name', 'label', 'value'])
                    ->orderBy('id')
                    ->get()
                    ->mapWithKeys(static fn (object $row): array => [(int) $row->id => [
                        'name' => is_string($row->name ?? null) ? (string) $row->name : '',
                        'label' => is_string($row->label ?? null) ? (string) $row->label : '',
                        'value' => is_scalar($row->value ?? null) ? (string) $row->value : '',
                    ]])
                    ->all();
            } catch (Throwable) {
                $rows[$sourceTable] = [];
            }
        }

        return $rows;
    }

    /** @param array<string, mixed> $legacy @return array<string, mixed> */
    private function row(LegacyReviewItem $item, array $legacy): array
    {
        $legacyName = $this->legacyName($item, $legacy);
        $legacyValue = $this->normalizedText($legacy['value'] ?? null);
        $mapping = self::SAFE_MAPPINGS[$legacyName] ?? null;
        $targetGroup = is_array($mapping) ? $mapping['target_group'] : '';
        $targetKey = is_array($mapping) ? $mapping['target_key'] : '';
        $targetLocale = is_array($mapping) ? $mapping['target_locale'] : '';
        $valueShape = is_array($mapping) ? $mapping['value_shape'] : '';
        $status = 'unmapped_setting_backlog';
        $reason = 'No deliberate current settings mapping exists for this legacy key.';

        if ((string) $item->review_status !== 'review_candidate') {
            $status = 'blocked_review_status';
            $reason = 'The staged review item is not a clean Phase 6 review candidate.';
        } elseif ((string) $item->mapping_status !== 'proposed') {
            $status = 'already_approved_or_imported';
            $reason = 'The staged mapping is not proposed; it is excluded from new settings mapping review.';
        } elseif (is_array($item->blocked_reasons) && $item->blocked_reasons !== []) {
            $status = 'blocked_review_reasons';
            $reason = 'The staged review item still has blockers.';
        } elseif ($legacyName === '') {
            $status = 'missing_legacy_key';
            $reason = 'The legacy key could not be read from the source table.';
        } elseif ($mapping !== null && ! $this->valueIsSafe($legacyValue, $mapping['type'])) {
            $status = 'unsafe_value';
            $reason = 'The value does not validate for the expected target value type.';
        } elseif ($mapping !== null) {
            $status = 'safe_mapping';
            $reason = 'Legacy key has a deliberate current settings target and a valid value.';
        }

        return [
            'mapping_status_detail' => $status,
            'reason' => $reason,
            'source_table' => (string) $item->source_table,
            'source_id' => (int) $item->source_id,
            'legacy_name' => $legacyName,
            'legacy_label' => $this->normalizedText($legacy['label'] ?? null),
            'legacy_value' => $this->displayValue($legacyValue),
            'normalized_value' => $legacyValue,
            'target_group' => $targetGroup,
            'target_key' => $targetKey,
            'target_locale' => $targetLocale,
            'value_shape' => $valueShape,
            'review_status' => (string) $item->review_status,
            'source_mapping_status' => (string) $item->mapping_status,
            'classification' => (string) $item->classification,
            'file_dependency' => $item->file_dependency ?: 'none',
            'blocked_reasons' => implode('|', is_array($item->blocked_reasons) ? $item->blocked_reasons : []),
        ];
    }

    /** @param array<int, array<string, mixed>> $rows @return array<int, array<string, mixed>> */
    private function applyDuplicateStatuses(array $rows): array
    {
        $valuesByName = [];

        foreach ($rows as $row) {
            if (($row['mapping_status_detail'] ?? '') !== 'safe_mapping') {
                continue;
            }

            $name = (string) ($row['legacy_name'] ?? '');
            $value = (string) ($row['normalized_value'] ?? '');

            if ($name === '') {
                continue;
            }

            $valuesByName[$name][$value] = true;
        }

        foreach ($rows as $index => $row) {
            $name = (string) ($row['legacy_name'] ?? '');

            if (($row['mapping_status_detail'] ?? '') !== 'safe_mapping' || ! isset($valuesByName[$name]) || count($valuesByName[$name]) <= 1) {
                continue;
            }

            $rows[$index]['mapping_status_detail'] = 'duplicate_conflict';
            $rows[$index]['reason'] = 'The same legacy setting key appears with conflicting values and requires manual selection.';
        }

        return $rows;
    }

    /** @param array<string, mixed> $legacy */
    private function legacyName(LegacyReviewItem $item, array $legacy): string
    {
        $name = $this->normalizedText($legacy['name'] ?? null);

        if ($name !== '') {
            return $name;
        }

        $identity = (string) $item->source_identity;

        if (preg_match('/(?:^|\b)name=([^|]+)/', $identity, $matches) === 1) {
            return trim($matches[1]);
        }

        return '';
    }

    private function normalizedText(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    private function displayValue(string $value): string
    {
        return mb_strlen($value) > 500 ? mb_substr($value, 0, 500).'...' : $value;
    }

    private function valueIsSafe(string $value, string $type): bool
    {
        if ($value === '') {
            return false;
        }

        if ($type === 'email') {
            return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
        }

        if ($type === 'url') {
            return $this->isSafeUrl($value);
        }

        return false;
    }

    private function isSafeUrl(string $value): bool
    {
        if (str_starts_with($value, '/')) {
            return ! str_starts_with($value, '//');
        }

        $scheme = parse_url($value, PHP_URL_SCHEME);

        if (! is_string($scheme) || ! in_array(strtolower($scheme), ['http', 'https'], true)) {
            return false;
        }

        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    /** @param array<string, int> $statusCounts @param array<string, int> $targetCounts */
    private function markdown(int $scannedRows, int $safeRows, int $backlogRows, int $duplicateRows, int $unsafeRows, array $statusCounts, array $targetCounts): string
    {
        $lines = [
            '# Phase 6 Settings Mapping Report',
            '',
            '- Generated: '.now()->toIso8601String(),
            '- Scanned settings rows: '.$scannedRows,
            '- Safe mapping rows: '.$safeRows,
            '- Backlog rows: '.$backlogRows,
            '- Duplicate conflict rows: '.$duplicateRows,
            '- Unsafe value rows: '.$unsafeRows,
            '',
            '## Status Counts',
            '',
        ];
        $this->appendCounts($lines, $statusCounts);
        $lines[] = '';
        $lines[] = '## Safe Targets';
        $lines[] = '';
        $this->appendCounts($lines, $targetCounts);
        $lines[] = '';
        $lines[] = 'No settings are written by this report. A separate approval/import command is required before any target settings change.';

        return implode("\n", $lines)."\n";
    }

    /** @param array<int, string> $lines @param array<string, int> $counts */
    private function appendCounts(array &$lines, array $counts): void
    {
        if ($counts === []) {
            $lines[] = '- none';

            return;
        }

        foreach ($counts as $label => $count) {
            $lines[] = '- `'.$label.'`: '.$count;
        }
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
