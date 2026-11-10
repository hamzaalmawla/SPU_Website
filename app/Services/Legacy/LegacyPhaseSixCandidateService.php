<?php

declare(strict_types=1);

namespace App\Services\Legacy;

use App\Contracts\Legacy\LegacyPhaseSixCandidateServiceInterface;
use App\DTOs\Legacy\LegacyPhaseSixCandidateResultDTO;
use App\Models\Legacy\LegacyReviewItem;
use Illuminate\Support\Facades\Storage;

final class LegacyPhaseSixCandidateService implements LegacyPhaseSixCandidateServiceInterface
{
    /** @var array<string, string> */
    private const LANES = [
        'jx_config' => 'settings',
        'jx_config1' => 'settings',
        'jx_home_photos' => 'homepage',
        'jx_logos' => 'homepage',
        'jx_sites' => 'menu_links',
        'jx_docs' => 'documents_and_links',
        'jx_site_static_pages' => 'pages',
        'jx_categories' => 'selected_core_pages',
    ];

    public function export(
        ?string $lane = null,
        string $disk = 'local',
        string $directory = 'legacy-import-exports/phase6-candidates',
    ): LegacyPhaseSixCandidateResultDTO {
        $lane = $this->normalizedFilter($lane);
        $directory = trim($directory, '/');
        $directory = $directory !== '' ? $directory : 'legacy-import-exports/phase6-candidates';
        $rows = LegacyReviewItem::query()
            ->whereIn('source_table', array_keys(self::LANES))
            ->orderBy('source_table')
            ->orderBy('source_id')
            ->get()
            ->map(fn (LegacyReviewItem $item): array => $this->row($item))
            ->filter(fn (array $row): bool => $lane === null || $row['phase6_lane'] === $lane)
            ->values()
            ->all();
        $approvalRows = array_values(array_filter($rows, fn (array $row): bool => $row['candidate_status'] === 'approval_candidate'));
        $importReadyRows = array_values(array_filter($rows, fn (array $row): bool => $row['candidate_status'] === 'import_ready'));
        $blockedRows = array_values(array_filter($rows, fn (array $row): bool => ! in_array($row['candidate_status'], ['approval_candidate', 'import_ready'], true)));
        $laneCounts = collect($rows)->countBy('phase6_lane')->all();
        $candidateStatusCounts = collect($rows)->countBy('candidate_status')->all();
        $blockerCounts = collect($rows)
            ->flatMap(fn (array $row): array => $row['blockers'] !== '' ? explode('|', (string) $row['blockers']) : [])
            ->filter()
            ->countBy()
            ->all();
        $stamp = now()->format('Ymd_His');
        $suffix = $lane !== null ? '_'.$this->filenamePart($lane) : '';
        $basePath = $directory.'/'.$stamp.'_phase6_candidates'.$suffix;
        $headers = [
            'candidate_status',
            'phase6_lane',
            'blockers',
            'module',
            'source_table',
            'source_id',
            'legacy_key',
            'classification',
            'mapping_status',
            'review_status',
            'target_module',
            'target_type',
            'confidence',
            'file_dependency',
            'cleaning_status',
            'url_status',
            'source_identity',
            'source_url',
            'notes',
        ];
        $paths = [
            $basePath.'.md',
            $basePath.'_approval_candidates.csv',
            $basePath.'_import_ready.csv',
            $basePath.'_blocked.csv',
            $basePath.'.json',
        ];

        Storage::disk($disk)->put($paths[0], $this->markdown(count($rows), count($approvalRows), count($importReadyRows), count($blockedRows), $laneCounts, $candidateStatusCounts, $blockerCounts));
        Storage::disk($disk)->put($paths[1], $this->csvPayload($approvalRows, $headers));
        Storage::disk($disk)->put($paths[2], $this->csvPayload($importReadyRows, $headers));
        Storage::disk($disk)->put($paths[3], $this->csvPayload($blockedRows, $headers));
        Storage::disk($disk)->put($paths[4], $this->json([
            'generated_at' => now()->toIso8601String(),
            'lane' => $lane,
            'summary' => [
                'scanned_rows' => count($rows),
                'approval_candidate_rows' => count($approvalRows),
                'import_ready_rows' => count($importReadyRows),
                'blocked_rows' => count($blockedRows),
                'lane_counts' => $laneCounts,
                'candidate_status_counts' => $candidateStatusCounts,
                'blocker_counts' => $blockerCounts,
            ],
        ]));

        return new LegacyPhaseSixCandidateResultDTO(
            lane: $lane,
            disk: $disk,
            scannedRows: count($rows),
            approvalCandidateRows: count($approvalRows),
            importReadyRows: count($importReadyRows),
            blockedRows: count($blockedRows),
            laneCounts: $laneCounts,
            candidateStatusCounts: $candidateStatusCounts,
            blockerCounts: $blockerCounts,
            paths: $paths,
        );
    }

    /** @return array<string, mixed> */
    private function row(LegacyReviewItem $item): array
    {
        $lane = self::LANES[(string) $item->source_table] ?? 'unknown';
        $blockers = $this->blockers($item, $lane);
        $status = $this->candidateStatus($item, $blockers);

        return [
            'candidate_status' => $status,
            'phase6_lane' => $lane,
            'blockers' => implode('|', $blockers),
            'module' => (string) $item->module,
            'source_table' => (string) $item->source_table,
            'source_id' => $item->source_id,
            'legacy_key' => (string) $item->legacy_key,
            'classification' => (string) $item->classification,
            'mapping_status' => (string) $item->mapping_status,
            'review_status' => (string) $item->review_status,
            'target_module' => $item->target_module,
            'target_type' => $item->target_type,
            'confidence' => $item->confidence,
            'file_dependency' => $item->file_dependency ?: 'none',
            'cleaning_status' => (string) $item->cleaning_status,
            'url_status' => $item->url_status,
            'source_identity' => $item->source_identity,
            'source_url' => $item->source_url,
            'notes' => $item->notes,
        ];
    }

    /** @return array<int, string> */
    private function blockers(LegacyReviewItem $item, string $lane): array
    {
        $blockers = [];

        if ((string) $item->review_status === 'blocked') {
            $blockers[] = 'blocked_review_status';
        }

        if (! in_array((string) $item->review_status, ['review_candidate', 'decision_plan_candidate', 'mapping_already_approved'], true)) {
            $blockers[] = 'not_reviewed_for_phase6';
        }

        if ((string) $item->mapping_status !== 'approved') {
            $blockers[] = 'approval_required';
        }

        if (! in_array((string) $item->file_dependency, ['', 'none'], true)) {
            $blockers[] = 'blocked_file_dependency';
        }

        foreach ($item->blocked_reasons ?? [] as $reason) {
            $blockers[] = str_starts_with($reason, 'file_dependency_') ? 'blocked_file_dependency' : $reason;
        }

        if ($lane === 'selected_core_pages') {
            $blockers[] = 'requires_explicit_core_page_selection';
        }

        if ($lane === 'documents_and_links' && (string) $item->classification === 'file_only_preserve') {
            $blockers[] = 'blocked_file_dependency';
        }

        return array_values(array_unique($blockers));
    }

    /** @param array<int, string> $blockers */
    private function candidateStatus(LegacyReviewItem $item, array $blockers): string
    {
        if ((string) $item->mapping_status === 'approved' && $blockers === []) {
            return 'import_ready';
        }

        if ($blockers === ['approval_required']) {
            return 'approval_candidate';
        }

        return 'blocked';
    }

    /** @param array<string, int> $laneCounts @param array<string, int> $candidateStatusCounts @param array<string, int> $blockerCounts */
    private function markdown(int $scannedRows, int $approvalRows, int $importReadyRows, int $blockedRows, array $laneCounts, array $candidateStatusCounts, array $blockerCounts): string
    {
        $lines = [
            '# Phase 6 Current-Scope Candidates',
            '',
            '- Generated: '.now()->toIso8601String(),
            '- Scanned current-scope rows: '.$scannedRows,
            '- Approval candidates: '.$approvalRows,
            '- Import-ready rows: '.$importReadyRows,
            '- Blocked rows: '.$blockedRows,
            '',
            '## Lanes',
            '',
        ];

        $this->appendCounts($lines, $laneCounts);
        $lines[] = '';
        $lines[] = '## Candidate Status';
        $lines[] = '';
        $this->appendCounts($lines, $candidateStatusCounts);
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
        $value = preg_replace('/[^A-Za-z0-9_-]+/', '_', $value) ?? 'lane';

        return trim($value, '_') !== '' ? trim($value, '_') : 'lane';
    }
}
