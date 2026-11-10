<?php

declare(strict_types=1);

namespace App\Services\Legacy;

use App\Contracts\Legacy\LegacyStagingReviewServiceInterface;
use App\DTOs\Legacy\LegacyStagingReviewResultDTO;
use App\Models\Legacy\LegacyContentMapping;
use App\Models\Legacy\LegacyReviewItem;
use Illuminate\Support\Facades\Storage;

final class LegacyStagingReviewService implements LegacyStagingReviewServiceInterface
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

    public function build(
        ?string $module = null,
        bool $write = false,
        string $disk = 'local',
        string $directory = 'legacy-import-exports/staging-review',
    ): LegacyStagingReviewResultDTO {
        $module = $this->normalizedFilter($module);
        $directory = trim($directory, '/');
        $directory = $directory !== '' ? $directory : 'legacy-import-exports/staging-review';
        $createdRows = 0;
        $updatedRows = 0;
        $scannedMappings = 0;
        $reviewStatusCounts = [];
        $classificationCounts = [];
        $blockerCounts = [];
        $rows = [];

        LegacyContentMapping::query()
            ->when($module !== null, fn ($query) => $query->where('module', $module))
            ->orderBy('id')
            ->chunkById(1000, function ($mappings) use (
                $write,
                &$createdRows,
                &$updatedRows,
                &$scannedMappings,
                &$reviewStatusCounts,
                &$classificationCounts,
                &$blockerCounts,
                &$rows,
            ): void {
                foreach ($mappings as $mapping) {
                    $row = $this->reviewRow($mapping);
                    $scannedMappings++;
                    $this->increment($reviewStatusCounts, (string) $row['review_status']);
                    $this->increment($classificationCounts, (string) $row['classification']);

                    foreach ($row['blocked_reasons'] as $reason) {
                        $this->increment($blockerCounts, $reason);
                    }

                    if ($write) {
                        $result = $this->persistRow($row);
                        $createdRows += $result['created'];
                        $updatedRows += $result['updated'];
                    }

                    $rows[] = $row;
                }
            });

        $stamp = now()->format('Ymd_His');
        $suffix = $module !== null ? '_'.$this->filenamePart($module) : '';
        $basePath = $directory.'/'.$stamp.'_staging_review'.$suffix;
        $paths = [
            $basePath.'.md',
            $basePath.'.csv',
            $basePath.'.json',
        ];

        Storage::disk($disk)->put($paths[0], $this->markdown(
            module: $module,
            written: $write,
            scannedMappings: $scannedMappings,
            createdRows: $createdRows,
            updatedRows: $updatedRows,
            reviewStatusCounts: $reviewStatusCounts,
            classificationCounts: $classificationCounts,
            blockerCounts: $blockerCounts,
        ));
        Storage::disk($disk)->put($paths[1], $this->csvPayload($rows));
        Storage::disk($disk)->put($paths[2], $this->json([
            'generated_at' => now()->toIso8601String(),
            'module' => $module,
            'written' => $write,
            'summary' => [
                'scanned_mappings' => $scannedMappings,
                'staged_rows' => $scannedMappings,
                'created_rows' => $createdRows,
                'updated_rows' => $updatedRows,
                'review_status_counts' => $reviewStatusCounts,
                'classification_counts' => $classificationCounts,
                'blocker_counts' => $blockerCounts,
            ],
        ]));

        return new LegacyStagingReviewResultDTO(
            module: $module,
            disk: $disk,
            written: $write,
            scannedMappings: $scannedMappings,
            stagedRows: $scannedMappings,
            createdRows: $createdRows,
            updatedRows: $updatedRows,
            reviewStatusCounts: $reviewStatusCounts,
            classificationCounts: $classificationCounts,
            blockerCounts: $blockerCounts,
            paths: $paths,
        );
    }

    /** @return array<string, mixed> */
    private function reviewRow(LegacyContentMapping $mapping): array
    {
        $phase3Reasons = is_array($mapping->phase3_reasons) ? array_values(array_filter($mapping->phase3_reasons, 'is_string')) : [];
        $blockedReasons = $this->blockedReasons($mapping, $phase3Reasons);
        $reviewStatus = $this->reviewStatus($mapping, $phase3Reasons, $blockedReasons);

        return [
            'module' => (string) $mapping->module,
            'source_table' => (string) $mapping->source_table,
            'source_id' => $mapping->source_id,
            'legacy_key' => (string) $mapping->legacy_key,
            'classification' => (string) $mapping->classification,
            'mapping_status' => (string) $mapping->mapping_status,
            'review_status' => $reviewStatus,
            'target_module' => $mapping->target_module,
            'target_type' => $mapping->target_type,
            'confidence' => $mapping->confidence,
            'file_dependency' => $mapping->file_dependency,
            'phase3_reasons' => $phase3Reasons,
            'cleaning_status' => $this->cleaningStatus($phase3Reasons),
            'decision_plan_action' => $this->decisionPlanAction($phase3Reasons),
            'url_status' => $this->urlStatus($mapping),
            'blocked_reasons' => $blockedReasons,
            'source_identity' => $mapping->source_identity,
            'source_url' => $mapping->source_url,
            'source_date' => $mapping->source_date,
            'rule_key' => $mapping->rule_key,
            'notes' => $mapping->notes,
            'metadata' => array_merge(is_array($mapping->metadata) ? $mapping->metadata : [], [
                'source_mapping_id' => $mapping->id,
                'target_identifier' => $mapping->target_identifier,
                'target_table' => $mapping->target_table,
                'target_id' => $mapping->target_id,
            ]),
        ];
    }

    /** @param array<int, string> $phase3Reasons @return array<int, string> */
    private function blockedReasons(LegacyContentMapping $mapping, array $phase3Reasons): array
    {
        $reasons = [];
        $mappingStatus = (string) $mapping->mapping_status;
        $classification = (string) $mapping->classification;
        $fileDependency = (string) $mapping->file_dependency;

        if (! in_array($mappingStatus, ['proposed', 'approved'], true)) {
            $reasons[] = 'mapping_status_'.$mappingStatus;
        }

        if ($mappingStatus !== 'approved' && ! in_array($classification, self::LOW_RISK_BUCKETS, true)) {
            $reasons[] = 'not_low_risk_bucket';
        }

        if (! in_array($fileDependency, ['', 'none'], true)) {
            $reasons[] = 'file_dependency_'.$fileDependency;
        }

        if ($phase3Reasons !== [] && ! $this->decisionPlanReviewable($phase3Reasons)) {
            $reasons[] = 'phase3_findings_block_review';
        }

        return array_values(array_unique($reasons));
    }

    /** @param array<int, string> $phase3Reasons @param array<int, string> $blockedReasons */
    private function reviewStatus(LegacyContentMapping $mapping, array $phase3Reasons, array $blockedReasons): string
    {
        if ((string) $mapping->mapping_status === 'approved') {
            return 'mapping_already_approved';
        }

        if ($blockedReasons !== []) {
            return 'blocked';
        }

        if ($phase3Reasons === []) {
            return 'review_candidate';
        }

        return $this->decisionPlanReviewable($phase3Reasons) ? 'decision_plan_candidate' : 'blocked';
    }

    /** @param array<int, string> $phase3Reasons */
    private function cleaningStatus(array $phase3Reasons): string
    {
        if ($phase3Reasons === []) {
            return 'clean';
        }

        return $this->decisionPlanReviewable($phase3Reasons) ? 'decision_plan_required' : 'blocked_findings';
    }

    /** @param array<int, string> $phase3Reasons */
    private function decisionPlanAction(array $phase3Reasons): ?string
    {
        if ($phase3Reasons === []) {
            return null;
        }

        return $this->decisionPlanReviewable($phase3Reasons) ? 'apply_existing_cleaning_policy' : 'manual_review_required';
    }

    private function urlStatus(LegacyContentMapping $mapping): string
    {
        if (! is_string($mapping->source_url) || trim($mapping->source_url) === '') {
            return 'not_applicable';
        }

        if ((string) $mapping->classification === 'redirect_to_equivalent') {
            return 'needs_redirect_review';
        }

        return 'needs_continuity_review';
    }

    /** @param array<string, mixed> $row @return array{created: int, updated: int} */
    private function persistRow(array $row): array
    {
        $reviewItem = LegacyReviewItem::query()->firstOrNew([
            'module' => $row['module'],
            'source_table' => $row['source_table'],
            'legacy_key' => $row['legacy_key'],
        ]);
        $created = ! $reviewItem->exists;
        $reviewItem->fill($row);

        if (! $created && ! $reviewItem->isDirty()) {
            return ['created' => 0, 'updated' => 0];
        }

        $reviewItem->save();

        return ['created' => $created ? 1 : 0, 'updated' => $created ? 0 : 1];
    }

    /** @param array<string, int> $counts */
    private function increment(array &$counts, string $key): void
    {
        $counts[$key] = ($counts[$key] ?? 0) + 1;
    }

    /** @param array<int, string> $phase3Reasons */
    private function decisionPlanReviewable(array $phase3Reasons): bool
    {
        return $phase3Reasons !== [] && array_diff($phase3Reasons, self::DECISION_PLAN_REVIEW_REASONS) === [];
    }

    /** @param array<string, int> $reviewStatusCounts @param array<string, int> $classificationCounts @param array<string, int> $blockerCounts */
    private function markdown(
        ?string $module,
        bool $written,
        int $scannedMappings,
        int $createdRows,
        int $updatedRows,
        array $reviewStatusCounts,
        array $classificationCounts,
        array $blockerCounts,
    ): string {
        $lines = [
            '# Legacy Staging Review',
            '',
            '- Module: '.($module ?? 'all'),
            '- Generated: '.now()->toIso8601String(),
            '- Written to review table: '.($written ? 'yes' : 'no'),
            '- Scanned mappings: '.$scannedMappings,
            '- Staged review rows: '.$scannedMappings,
            '- Created rows: '.$createdRows,
            '- Updated rows: '.$updatedRows,
            '',
            '## Review Status',
            '',
        ];

        foreach ($reviewStatusCounts as $status => $count) {
            $lines[] = '- `'.$status.'`: '.$count;
        }

        $lines[] = '';
        $lines[] = '## Classification';
        $lines[] = '';

        foreach ($classificationCounts as $classification => $count) {
            $lines[] = '- `'.$classification.'`: '.$count;
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

    /** @param array<int, array<string, mixed>> $rows */
    private function csvPayload(array $rows): string
    {
        $headers = [
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
            'phase3_reasons',
            'cleaning_status',
            'decision_plan_action',
            'url_status',
            'blocked_reasons',
            'source_identity',
            'source_url',
            'source_date',
            'rule_key',
            'notes',
        ];
        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            return '';
        }

        fputcsv($stream, $headers);

        foreach ($rows as $row) {
            fputcsv($stream, array_map(function (string $header) use ($row): mixed {
                $value = $row[$header] ?? '';

                return is_array($value) ? implode('|', $value) : $value;
            }, $headers));
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
