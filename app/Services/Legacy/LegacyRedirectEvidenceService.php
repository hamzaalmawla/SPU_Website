<?php

declare(strict_types=1);

namespace App\Services\Legacy;

use App\Contracts\Legacy\LegacyRedirectEvidenceServiceInterface;
use App\DTOs\Legacy\LegacyRedirectEvidenceResultDTO;
use App\Models\Legacy\LegacyContentMapping;
use App\Models\Legacy\LegacyReviewItem;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

final class LegacyRedirectEvidenceService implements LegacyRedirectEvidenceServiceInterface
{
    public function export(
        string $generatedInventoryPath,
        string $triageRowsPath,
        string $disk = 'local',
        string $directory = 'legacy-import-exports/redirect-evidence',
    ): LegacyRedirectEvidenceResultDTO {
        $generatedInventoryPath = trim($generatedInventoryPath);
        $triageRowsPath = trim($triageRowsPath);
        $directory = trim($directory, '/');
        $directory = $directory !== '' ? $directory : 'legacy-import-exports/redirect-evidence';

        if ($generatedInventoryPath === '' || $triageRowsPath === '') {
            throw new InvalidArgumentException('Generated inventory path and triage rows path are required.');
        }

        if (! Storage::disk($disk)->exists($generatedInventoryPath)) {
            throw new InvalidArgumentException('Generated URL inventory CSV was not found on the selected disk.');
        }

        if (! Storage::disk($disk)->exists($triageRowsPath)) {
            throw new InvalidArgumentException('URL triage rows CSV was not found on the selected disk.');
        }

        $resolvedRows = $this->resolvedInventoryRows($this->csvRows((string) Storage::disk($disk)->get($generatedInventoryPath)));
        $triageEvidenceRows = $this->triageEvidenceRows($this->csvRows((string) Storage::disk($disk)->get($triageRowsPath)));
        $rows = array_merge($resolvedRows, $triageEvidenceRows);
        $previewRows = array_values(array_filter($rows, fn (array $row): bool => $row['target_url'] !== '' && $row['redirect_readiness'] === 'preview_ready'));
        $blockedRows = array_values(array_filter($rows, fn (array $row): bool => $row['redirect_readiness'] !== 'preview_ready'));
        $evidenceStatusCounts = collect($rows)->countBy('evidence_status')->all();
        $approvalStatusCounts = collect($rows)->countBy('approval_status')->all();
        $handlerCounts = collect($rows)->countBy('handler_key')->all();
        $blockerCounts = collect($rows)
            ->flatMap(fn (array $row): array => $row['blockers'] !== '' ? explode('|', (string) $row['blockers']) : [])
            ->filter()
            ->countBy()
            ->all();
        $stamp = now()->format('Ymd_His');
        $basePath = $directory.'/'.$stamp.'_redirect_evidence';
        $headers = [
            'redirect_readiness',
            'evidence_status',
            'approval_status',
            'approval_decision',
            'approved_by',
            'approval_notes',
            'blockers',
            'legacy_path',
            'normalized_path',
            'query_signature',
            'target_url',
            'status_code',
            'handler_key',
            'subsite',
            'locale',
            'source_table',
            'source_id',
            'mapping_status',
            'review_status',
            'classification',
            'target_module',
            'target_type',
            'target_id',
            'confidence',
            'source_type',
            'notes',
        ];
        $paths = [
            $basePath.'.md',
            $basePath.'_all.csv',
            $basePath.'_preview.csv',
            $basePath.'_blocked.csv',
            $basePath.'.json',
        ];

        Storage::disk($disk)->put($paths[0], $this->markdown(count($rows), count($previewRows), count($blockedRows), $evidenceStatusCounts, $approvalStatusCounts, $blockerCounts));
        Storage::disk($disk)->put($paths[1], $this->csvPayload($rows, $headers));
        Storage::disk($disk)->put($paths[2], $this->csvPayload($previewRows, $headers));
        Storage::disk($disk)->put($paths[3], $this->csvPayload($blockedRows, $headers));
        Storage::disk($disk)->put($paths[4], $this->json([
            'generated_at' => now()->toIso8601String(),
            'generated_inventory_path' => $generatedInventoryPath,
            'triage_rows_path' => $triageRowsPath,
            'summary' => [
                'scanned_rows' => count($rows),
                'redirect_preview_rows' => count($previewRows),
                'blocked_rows' => count($blockedRows),
                'evidence_status_counts' => $evidenceStatusCounts,
                'approval_status_counts' => $approvalStatusCounts,
                'handler_counts' => $handlerCounts,
                'blocker_counts' => $blockerCounts,
            ],
        ]));

        return new LegacyRedirectEvidenceResultDTO(
            generatedInventoryPath: $generatedInventoryPath,
            triageRowsPath: $triageRowsPath,
            disk: $disk,
            scannedRows: count($rows),
            redirectPreviewRows: count($previewRows),
            blockedRows: count($blockedRows),
            evidenceStatusCounts: $evidenceStatusCounts,
            approvalStatusCounts: $approvalStatusCounts,
            handlerCounts: $handlerCounts,
            blockerCounts: $blockerCounts,
            paths: $paths,
        );
    }

    /** @param array<int, array<string, string>> $rows @return array<int, array<string, mixed>> */
    private function resolvedInventoryRows(array $rows): array
    {
        $evidenceRows = [];

        foreach ($rows as $row) {
            if (($row['status'] ?? '') !== 'resolved_by_query_resolver') {
                continue;
            }

            $evidenceRows[] = [
                'redirect_readiness' => 'preview_ready',
                'evidence_status' => 'resolver_ready',
                'approval_status' => 'runtime_resolver',
                'approval_decision' => '',
                'approved_by' => '',
                'approval_notes' => '',
                'blockers' => '',
                'legacy_path' => $row['legacy_path'] ?? '',
                'normalized_path' => $row['normalized_path'] ?? '',
                'query_signature' => $row['query_signature'] ?? '',
                'target_url' => $row['target_url'] ?? '',
                'status_code' => '301',
                'handler_key' => $row['handler_key'] ?? '',
                'subsite' => $row['subsite'] ?? '',
                'locale' => $row['locale'] ?? '',
                'source_table' => $row['source_table'] ?? '',
                'source_id' => $row['source_id'] ?? '',
                'mapping_status' => 'runtime_resolved',
                'review_status' => 'runtime_resolved',
                'classification' => '',
                'target_module' => $row['module'] ?? '',
                'target_type' => 'query_resolver',
                'target_id' => '',
                'confidence' => $row['confidence'] ?? 'high',
                'source_type' => $row['source_type'] ?? '',
                'notes' => $row['notes'] ?? 'Resolved by existing query resolver.',
            ];
        }

        return $evidenceRows;
    }

    /** @param array<int, array<string, string>> $rows @return array<int, array<string, mixed>> */
    private function triageEvidenceRows(array $rows): array
    {
        $evidenceRows = [];

        foreach ($rows as $row) {
            $triageStatus = trim($row['triage_status'] ?? '');
            $sourceId = is_numeric($row['candidate_source_id'] ?? null) ? (int) $row['candidate_source_id'] : null;
            $sourceTables = $this->listFromPipeString($row['candidate_source_tables'] ?? '');
            $mapping = $sourceId !== null ? $this->mapping($sourceTables, $sourceId) : null;
            $reviewItem = $sourceId !== null ? $this->reviewItem($sourceTables, $sourceId) : null;
            $blockers = $this->blockers($triageStatus, $mapping, $reviewItem);
            $evidenceStatus = $this->evidenceStatus($triageStatus, $mapping, $reviewItem, $blockers);

            $evidenceRows[] = [
                'redirect_readiness' => 'blocked',
                'evidence_status' => $evidenceStatus,
                'approval_status' => $this->approvalStatus($triageStatus, $mapping, $reviewItem),
                'approval_decision' => '',
                'approved_by' => '',
                'approval_notes' => '',
                'blockers' => implode('|', $blockers),
                'legacy_path' => $row['legacy_path'] ?? '',
                'normalized_path' => parse_url((string) ($row['legacy_path'] ?? ''), PHP_URL_PATH) ?: '',
                'query_signature' => $row['query_signature'] ?? '',
                'target_url' => '',
                'status_code' => '301',
                'handler_key' => $row['handler_key'] ?? '',
                'subsite' => $row['subsite'] ?? '',
                'locale' => '',
                'source_table' => $mapping?->source_table ?? implode('|', $sourceTables),
                'source_id' => $sourceId !== null ? (string) $sourceId : '',
                'mapping_status' => $mapping?->mapping_status ?? '',
                'review_status' => $reviewItem?->review_status ?? '',
                'classification' => $mapping?->classification ?? '',
                'target_module' => $mapping?->target_module ?? '',
                'target_type' => $mapping?->target_type ?? '',
                'target_id' => $mapping?->target_id !== null ? (string) $mapping->target_id : '',
                'confidence' => $mapping?->confidence ?? '',
                'source_type' => $row['source_type'] ?? '',
                'notes' => $this->evidenceNotes($triageStatus, $mapping, $reviewItem),
            ];
        }

        return $evidenceRows;
    }

    /** @param array<int, string> $sourceTables */
    private function mapping(array $sourceTables, int $sourceId): ?LegacyContentMapping
    {
        if ($sourceTables === []) {
            return null;
        }

        $mapping = LegacyContentMapping::query()
            ->whereIn('source_table', $sourceTables)
            ->where('source_id', $sourceId)
            ->orderByRaw("CASE mapping_status WHEN 'approved' THEN 0 WHEN 'proposed' THEN 1 ELSE 2 END")
            ->orderBy('id')
            ->first();

        return $mapping instanceof LegacyContentMapping ? $mapping : null;
    }

    /** @param array<int, string> $sourceTables */
    private function reviewItem(array $sourceTables, int $sourceId): ?LegacyReviewItem
    {
        if ($sourceTables === []) {
            return null;
        }

        $reviewItem = LegacyReviewItem::query()
            ->whereIn('source_table', $sourceTables)
            ->where('source_id', $sourceId)
            ->orderByRaw("CASE review_status WHEN 'review_candidate' THEN 0 WHEN 'decision_plan_candidate' THEN 1 WHEN 'mapping_already_approved' THEN 2 WHEN 'blocked' THEN 3 ELSE 4 END")
            ->orderBy('id')
            ->first();

        return $reviewItem instanceof LegacyReviewItem ? $reviewItem : null;
    }

    /** @return array<int, string> */
    private function blockers(string $triageStatus, ?LegacyContentMapping $mapping, ?LegacyReviewItem $reviewItem): array
    {
        $blockers = [];

        if ($triageStatus === 'blocked_file_url') {
            $blockers[] = 'blocked_file_dependency';
        } elseif ($triageStatus === 'blocked_target_not_public') {
            $blockers[] = 'blocked_target_not_public';
        } elseif ($triageStatus === 'blocked_missing_target_module') {
            $blockers[] = 'blocked_missing_target_module';
        } elseif ($triageStatus === 'unknown_legacy_url') {
            $blockers[] = 'unknown_legacy_url';
        } elseif ($triageStatus === 'needs_phase4_mapping') {
            $blockers[] = 'needs_phase4_mapping';
        }

        if ($triageStatus !== 'blocked_target_not_public') {
            if (! $mapping instanceof LegacyContentMapping) {
                $blockers[] = 'missing_content_mapping';
            } elseif ((string) $mapping->mapping_status !== 'approved') {
                $blockers[] = 'blocked_unapproved_mapping';
            }
        }

        if ($reviewItem instanceof LegacyReviewItem) {
            foreach ($reviewItem->blocked_reasons ?? [] as $reason) {
                if ($reason === 'phase3_findings_block_review') {
                    $blockers[] = 'blocked_phase3_findings';
                } elseif (str_starts_with($reason, 'file_dependency_')) {
                    $blockers[] = 'blocked_file_dependency';
                } else {
                    $blockers[] = $reason;
                }
            }
        }

        if ($mapping instanceof LegacyContentMapping && $mapping->target_id === null) {
            $blockers[] = 'needs_imported_target';
        }

        return array_values(array_unique($blockers));
    }

    /** @param array<int, string> $blockers */
    private function evidenceStatus(string $triageStatus, ?LegacyContentMapping $mapping, ?LegacyReviewItem $reviewItem, array $blockers): string
    {
        if (in_array('blocked_file_dependency', $blockers, true)) {
            return 'blocked_file_dependency';
        }

        if (in_array('blocked_phase3_findings', $blockers, true)) {
            return 'blocked_phase3_findings';
        }

        if (in_array('blocked_target_not_public', $blockers, true)) {
            return 'blocked_target_not_public';
        }

        if ($triageStatus !== 'resolver_candidate') {
            return $triageStatus !== '' ? $triageStatus : 'continuity_backlog';
        }

        if (! $mapping instanceof LegacyContentMapping) {
            return 'needs_phase4_mapping';
        }

        if ($mapping->target_id === null) {
            return 'needs_imported_target';
        }

        if (! $reviewItem instanceof LegacyReviewItem || ! in_array((string) $reviewItem->review_status, ['review_candidate', 'decision_plan_candidate', 'mapping_already_approved'], true)) {
            return 'blocked_review_status';
        }

        return 'blocked_unapproved_mapping';
    }

    private function approvalStatus(string $triageStatus, ?LegacyContentMapping $mapping, ?LegacyReviewItem $reviewItem): string
    {
        if ($triageStatus === 'blocked_target_not_public') {
            return 'target_private_review';
        }

        if (! $mapping instanceof LegacyContentMapping) {
            return 'missing_mapping';
        }

        if ((string) $mapping->mapping_status === 'approved') {
            return 'mapping_approved';
        }

        return $reviewItem instanceof LegacyReviewItem ? (string) $reviewItem->review_status : 'mapping_proposed';
    }

    private function evidenceNotes(string $triageStatus, ?LegacyContentMapping $mapping, ?LegacyReviewItem $reviewItem): string
    {
        if ($triageStatus !== 'resolver_candidate') {
            return 'Triage status remains unresolved: '.$triageStatus.'.';
        }

        if (! $mapping instanceof LegacyContentMapping) {
            return 'Resolver candidate has no Phase 4 content mapping.';
        }

        if ($mapping->target_id === null) {
            return 'Resolver candidate has mapping evidence, but target content has not been imported/assigned yet.';
        }

        if (! $reviewItem instanceof LegacyReviewItem) {
            return 'Resolver candidate has target mapping but no staging review item.';
        }

        return 'Resolver candidate still requires approval before final redirect persistence.';
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

    /** @return array<int, string> */
    private function listFromPipeString(string $value): array
    {
        if (trim($value) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode('|', $value)), static fn (string $item): bool => $item !== ''));
    }

    /** @param array<string, int> $evidenceStatusCounts @param array<string, int> $approvalStatusCounts @param array<string, int> $blockerCounts */
    private function markdown(int $scannedRows, int $previewRows, int $blockedRows, array $evidenceStatusCounts, array $approvalStatusCounts, array $blockerCounts): string
    {
        $lines = [
            '# Legacy Redirect Evidence',
            '',
            '- Generated: '.now()->toIso8601String(),
            '- Scanned evidence rows: '.$scannedRows,
            '- Redirect preview rows: '.$previewRows,
            '- Blocked/backlog rows: '.$blockedRows,
            '',
            '## Evidence Status',
            '',
        ];

        $this->appendCounts($lines, $evidenceStatusCounts);
        $lines[] = '';
        $lines[] = '## Approval Status';
        $lines[] = '';
        $this->appendCounts($lines, $approvalStatusCounts);
        $lines[] = '';
        $lines[] = '## Blockers';
        $lines[] = '';
        $this->appendCounts($lines, $blockerCounts);

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
