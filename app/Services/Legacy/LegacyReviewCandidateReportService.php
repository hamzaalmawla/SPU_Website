<?php

declare(strict_types=1);

namespace App\Services\Legacy;

use App\Contracts\Legacy\LegacyReviewCandidateReportServiceInterface;
use App\DTOs\Legacy\LegacyReviewCandidateReportResultDTO;
use App\Models\Legacy\LegacyContentMapping;
use Illuminate\Support\Facades\Storage;

final class LegacyReviewCandidateReportService implements LegacyReviewCandidateReportServiceInterface
{
    /** @var array<int, string> */
    private const LOW_RISK_BUCKETS = [
        'redirect_to_equivalent',
        'archive_now_remodel_later',
        'retire_after_approval',
    ];

    /** @var array<int, string> */
    private const DECISION_PLAN_REVIEW_REASONS = [
        'unsafe_html',
        'base64_inline_image',
        'inline_formatting_cleaned',
        'word_html_cleaned',
        'legacy_internal_link',
    ];

    public function export(
        ?string $module = null,
        string $disk = 'local',
        string $directory = 'legacy-import-exports/review-candidates',
    ): LegacyReviewCandidateReportResultDTO {
        $module = $this->normalizedFilter($module);
        $directory = trim($directory, '/');
        $directory = $directory !== '' ? $directory : 'legacy-import-exports/review-candidates';
        $rows = LegacyContentMapping::query()
            ->when($module !== null, fn ($query) => $query->where('module', $module))
            ->where('mapping_status', 'proposed')
            ->orderBy('module')
            ->orderBy('classification')
            ->orderBy('source_table')
            ->orderBy('source_id')
            ->get();
        $safeCandidates = [];
        $decisionPlanCandidates = [];
        $blocked = [];

        foreach ($rows as $mapping) {
            $row = $this->reviewRow($mapping);

            match ($row['review_status']) {
                'safe_candidate' => $safeCandidates[] = $row,
                'decision_plan_candidate' => $decisionPlanCandidates[] = $row,
                default => $blocked[] = $row,
            };
        }

        $allRows = array_merge($safeCandidates, $decisionPlanCandidates, $blocked);
        $statusCounts = collect($allRows)->countBy('review_status')->all();
        $blockerCounts = collect($blocked)
            ->flatMap(fn (array $row): array => explode('|', (string) $row['block_reasons']))
            ->filter()
            ->countBy()
            ->all();
        $stamp = now()->format('Ymd_His');
        $suffix = $module !== null ? '_'.$this->filenamePart($module) : '';
        $basePath = $directory.'/'.$stamp.'_review_candidates'.$suffix;
        $headers = [
            'review_status',
            'block_reasons',
            'module',
            'source_table',
            'source_id',
            'legacy_key',
            'classification',
            'target_module',
            'target_type',
            'confidence',
            'file_dependency',
            'phase3_reasons',
            'source_identity',
            'source_url',
            'rule_key',
            'notes',
        ];
        $paths = [
            $basePath.'.md',
            $basePath.'_safe_candidates.csv',
            $basePath.'_decision_plan_candidates.csv',
            $basePath.'_blocked.csv',
            $basePath.'.json',
        ];

        Storage::disk($disk)->put($paths[0], $this->markdown($module, count($allRows), $statusCounts, $blockerCounts));
        Storage::disk($disk)->put($paths[1], $this->csvPayload($safeCandidates, $headers));
        Storage::disk($disk)->put($paths[2], $this->csvPayload($decisionPlanCandidates, $headers));
        Storage::disk($disk)->put($paths[3], $this->csvPayload($blocked, $headers));
        Storage::disk($disk)->put($paths[4], $this->json([
            'generated_at' => now()->toIso8601String(),
            'module' => $module,
            'summary' => [
                'scanned_rows' => count($allRows),
                'safe_candidate_rows' => count($safeCandidates),
                'decision_plan_candidate_rows' => count($decisionPlanCandidates),
                'blocked_rows' => count($blocked),
                'status_counts' => $statusCounts,
                'blocker_counts' => $blockerCounts,
            ],
        ]));

        return new LegacyReviewCandidateReportResultDTO(
            module: $module,
            disk: $disk,
            scannedRows: count($allRows),
            safeCandidateRows: count($safeCandidates),
            decisionPlanCandidateRows: count($decisionPlanCandidates),
            blockedRows: count($blocked),
            statusCounts: $statusCounts,
            blockerCounts: $blockerCounts,
            paths: $paths,
        );
    }

    /** @return array<string, mixed> */
    private function reviewRow(LegacyContentMapping $mapping): array
    {
        $phase3Reasons = is_array($mapping->phase3_reasons) ? array_values(array_filter($mapping->phase3_reasons, 'is_string')) : [];
        $blockReasons = $this->blockReasons($mapping, $phase3Reasons);
        $reviewStatus = $this->reviewStatus($mapping, $phase3Reasons, $blockReasons);

        return [
            'review_status' => $reviewStatus,
            'block_reasons' => implode('|', $blockReasons),
            'module' => $mapping->module,
            'source_table' => $mapping->source_table,
            'source_id' => $mapping->source_id,
            'legacy_key' => $mapping->legacy_key,
            'classification' => $mapping->classification,
            'target_module' => $mapping->target_module,
            'target_type' => $mapping->target_type,
            'confidence' => $mapping->confidence,
            'file_dependency' => $mapping->file_dependency,
            'phase3_reasons' => implode('|', $phase3Reasons),
            'source_identity' => $mapping->source_identity,
            'source_url' => $mapping->source_url,
            'rule_key' => $mapping->rule_key,
            'notes' => $mapping->notes,
        ];
    }

    /** @param array<int, string> $phase3Reasons @return array<int, string> */
    private function blockReasons(LegacyContentMapping $mapping, array $phase3Reasons): array
    {
        $reasons = [];

        if (! in_array((string) $mapping->classification, self::LOW_RISK_BUCKETS, true)) {
            $reasons[] = 'not_low_risk_bucket';
        }

        if (! in_array((string) $mapping->file_dependency, ['', 'none'], true)) {
            $reasons[] = 'file_dependency_'.$mapping->file_dependency;
        }

        if ($phase3Reasons !== [] && ! $this->decisionPlanReviewable($phase3Reasons)) {
            $reasons[] = 'phase3_findings_block_review';
        }

        return array_values(array_unique($reasons));
    }

    /** @param array<int, string> $phase3Reasons @param array<int, string> $blockReasons */
    private function reviewStatus(LegacyContentMapping $mapping, array $phase3Reasons, array $blockReasons): string
    {
        if ($blockReasons !== []) {
            return 'blocked';
        }

        if (! in_array((string) $mapping->classification, self::LOW_RISK_BUCKETS, true)) {
            return 'blocked';
        }

        if ($phase3Reasons === []) {
            return 'safe_candidate';
        }

        return $this->decisionPlanReviewable($phase3Reasons) ? 'decision_plan_candidate' : 'blocked';
    }

    /** @param array<int, string> $phase3Reasons */
    private function decisionPlanReviewable(array $phase3Reasons): bool
    {
        return $phase3Reasons !== [] && array_diff($phase3Reasons, self::DECISION_PLAN_REVIEW_REASONS) === [];
    }

    /** @param array<string, int> $statusCounts @param array<string, int> $blockerCounts */
    private function markdown(?string $module, int $scannedRows, array $statusCounts, array $blockerCounts): string
    {
        $lines = [
            '# Legacy Review Candidate Report',
            '',
            '- Module: '.($module ?? 'all'),
            '- Generated: '.now()->toIso8601String(),
            '- Scanned proposed mappings: '.$scannedRows,
            '',
            '## Review Status',
            '',
        ];

        foreach ($statusCounts as $status => $count) {
            $lines[] = '- `'.$status.'`: '.$count;
        }

        $lines[] = '';
        $lines[] = '## Blockers';
        $lines[] = '';

        if ($blockerCounts === []) {
            $lines[] = '- none';
        }

        foreach ($blockerCounts as $blocker => $count) {
            $lines[] = '- `'.$blocker.'`: '.$count;
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
