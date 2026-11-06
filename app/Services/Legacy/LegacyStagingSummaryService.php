<?php

declare(strict_types=1);

namespace App\Services\Legacy;

use App\Contracts\Legacy\LegacyStagingSummaryServiceInterface;
use App\DTOs\Legacy\LegacyStagingSummaryResultDTO;
use App\Models\Legacy\LegacyReviewItem;
use Illuminate\Support\Facades\Storage;

final class LegacyStagingSummaryService implements LegacyStagingSummaryServiceInterface
{
    public function export(
        ?string $module = null,
        ?string $reviewStatus = null,
        int $sampleLimit = 5,
        string $disk = 'local',
        string $directory = 'legacy-import-exports/staging-summary',
    ): LegacyStagingSummaryResultDTO {
        $module = $this->normalizedFilter($module);
        $reviewStatus = $this->normalizedFilter($reviewStatus);
        $sampleLimit = max(1, min($sampleLimit, 50));
        $directory = trim($directory, '/');
        $directory = $directory !== '' ? $directory : 'legacy-import-exports/staging-summary';
        $items = LegacyReviewItem::query()
            ->when($module !== null, fn ($query) => $query->where('module', $module))
            ->when($reviewStatus !== null, fn ($query) => $query->where('review_status', $reviewStatus))
            ->orderBy('module')
            ->orderBy('source_table')
            ->orderBy('classification')
            ->orderBy('review_status')
            ->orderBy('source_id')
            ->get();
        $rows = $items->map(fn (LegacyReviewItem $item): array => $this->row($item))->all();
        $reviewStatusCounts = collect($rows)->countBy('review_status')->all();
        $classificationCounts = collect($rows)->countBy('classification')->all();
        $moduleCounts = collect($rows)->countBy('module')->all();
        $blockerCounts = collect($rows)
            ->flatMap(fn (array $row): array => $row['blocked_reasons'])
            ->filter()
            ->countBy()
            ->all();
        $groups = $this->groups($rows);
        $samples = $this->samples($rows, $sampleLimit);
        $stamp = now()->format('Ymd_His');
        $suffix = $this->suffix($module, $reviewStatus);
        $basePath = $directory.'/'.$stamp.'_staging_summary'.$suffix;
        $paths = [
            $basePath.'.md',
            $basePath.'_groups.csv',
            $basePath.'_samples.csv',
            $basePath.'.json',
        ];

        Storage::disk($disk)->put($paths[0], $this->markdown(
            module: $module,
            reviewStatus: $reviewStatus,
            totalRows: count($rows),
            sampleLimit: $sampleLimit,
            reviewStatusCounts: $reviewStatusCounts,
            classificationCounts: $classificationCounts,
            moduleCounts: $moduleCounts,
            blockerCounts: $blockerCounts,
            groups: $groups,
            samples: $samples,
        ));
        Storage::disk($disk)->put($paths[1], $this->csvPayload($groups, [
            'module',
            'source_table',
            'review_status',
            'classification',
            'file_dependency',
            'cleaning_status',
            'url_status',
            'blocked_reasons',
            'rows',
        ]));
        Storage::disk($disk)->put($paths[2], $this->csvPayload($samples, [
            'module',
            'source_table',
            'source_id',
            'legacy_key',
            'review_status',
            'classification',
            'target_module',
            'target_type',
            'confidence',
            'file_dependency',
            'cleaning_status',
            'url_status',
            'blocked_reasons',
            'source_identity',
            'source_url',
            'notes',
        ]));
        Storage::disk($disk)->put($paths[3], $this->json([
            'generated_at' => now()->toIso8601String(),
            'module' => $module,
            'review_status' => $reviewStatus,
            'summary' => [
                'total_rows' => count($rows),
                'sample_limit' => $sampleLimit,
                'review_status_counts' => $reviewStatusCounts,
                'classification_counts' => $classificationCounts,
                'module_counts' => $moduleCounts,
                'blocker_counts' => $blockerCounts,
            ],
            'groups' => $groups,
            'samples' => $samples,
        ]));

        return new LegacyStagingSummaryResultDTO(
            module: $module,
            reviewStatus: $reviewStatus,
            disk: $disk,
            totalRows: count($rows),
            sampleLimit: $sampleLimit,
            reviewStatusCounts: $reviewStatusCounts,
            classificationCounts: $classificationCounts,
            moduleCounts: $moduleCounts,
            blockerCounts: $blockerCounts,
            groups: $groups,
            samples: $samples,
            paths: $paths,
        );
    }

    /** @return array<string, mixed> */
    private function row(LegacyReviewItem $item): array
    {
        $blockedReasons = is_array($item->blocked_reasons) ? array_values(array_filter($item->blocked_reasons, 'is_string')) : [];

        return [
            'module' => (string) $item->module,
            'source_table' => (string) $item->source_table,
            'source_id' => $item->source_id,
            'legacy_key' => (string) $item->legacy_key,
            'review_status' => (string) $item->review_status,
            'classification' => (string) $item->classification,
            'target_module' => $item->target_module,
            'target_type' => $item->target_type,
            'confidence' => $item->confidence,
            'file_dependency' => $item->file_dependency ?: 'none',
            'cleaning_status' => (string) $item->cleaning_status,
            'url_status' => $item->url_status,
            'blocked_reasons' => $blockedReasons,
            'source_identity' => $item->source_identity,
            'source_url' => $item->source_url,
            'notes' => $item->notes,
        ];
    }

    /** @param array<int, array<string, mixed>> $rows @return array<int, array<string, mixed>> */
    private function groups(array $rows): array
    {
        $groups = [];

        foreach ($rows as $row) {
            $blockedReasons = implode('|', $row['blocked_reasons']);
            $key = implode('::', [
                $row['module'],
                $row['source_table'],
                $row['review_status'],
                $row['classification'],
                $row['file_dependency'],
                $row['cleaning_status'],
                $row['url_status'],
                $blockedReasons,
            ]);

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'module' => $row['module'],
                    'source_table' => $row['source_table'],
                    'review_status' => $row['review_status'],
                    'classification' => $row['classification'],
                    'file_dependency' => $row['file_dependency'],
                    'cleaning_status' => $row['cleaning_status'],
                    'url_status' => $row['url_status'],
                    'blocked_reasons' => $blockedReasons,
                    'rows' => 0,
                ];
            }

            $groups[$key]['rows']++;
        }

        $groups = array_values($groups);
        usort($groups, fn (array $left, array $right): int => [$left['module'], $left['review_status'], $left['classification'], $left['source_table']] <=> [$right['module'], $right['review_status'], $right['classification'], $right['source_table']]);

        return $groups;
    }

    /** @param array<int, array<string, mixed>> $rows @return array<int, array<string, mixed>> */
    private function samples(array $rows, int $sampleLimit): array
    {
        $samples = [];
        $counts = [];

        foreach ($rows as $row) {
            $key = implode('::', [$row['module'], $row['review_status'], $row['classification']]);
            $counts[$key] = ($counts[$key] ?? 0) + 1;

            if ($counts[$key] > $sampleLimit) {
                continue;
            }

            $row['blocked_reasons'] = implode('|', $row['blocked_reasons']);
            $samples[] = $row;
        }

        return $samples;
    }

    /** @param array<string, int> $reviewStatusCounts @param array<string, int> $classificationCounts @param array<string, int> $moduleCounts @param array<string, int> $blockerCounts @param array<int, array<string, mixed>> $groups @param array<int, array<string, mixed>> $samples */
    private function markdown(
        ?string $module,
        ?string $reviewStatus,
        int $totalRows,
        int $sampleLimit,
        array $reviewStatusCounts,
        array $classificationCounts,
        array $moduleCounts,
        array $blockerCounts,
        array $groups,
        array $samples,
    ): string {
        $lines = [
            '# Legacy Staging Summary',
            '',
            '- Module: '.($module ?? 'all'),
            '- Review status: '.($reviewStatus ?? 'all'),
            '- Generated: '.now()->toIso8601String(),
            '- Total staged rows: '.$totalRows,
            '- Sample limit per module/status/classification: '.$sampleLimit,
            '',
            '## Review Status',
            '',
        ];

        $this->appendCounts($lines, $reviewStatusCounts);
        $lines[] = '';
        $lines[] = '## Classification';
        $lines[] = '';
        $this->appendCounts($lines, $classificationCounts);
        $lines[] = '';
        $lines[] = '## Modules';
        $lines[] = '';
        $this->appendCounts($lines, $moduleCounts);
        $lines[] = '';
        $lines[] = '## Blockers';
        $lines[] = '';
        $this->appendCounts($lines, $blockerCounts);
        $lines[] = '';
        $lines[] = '## Largest Groups';
        $lines[] = '';

        foreach (array_slice($this->largestGroups($groups), 0, 25) as $group) {
            $lines[] = '- `'.$group['module'].'/'.$group['source_table'].'/'.$group['review_status'].'/'.$group['classification'].'`: '.$group['rows'];
        }

        if ($groups === []) {
            $lines[] = '- none';
        }

        $lines[] = '';
        $lines[] = '## Samples';
        $lines[] = '';

        foreach (array_slice($samples, 0, 50) as $sample) {
            $lines[] = '- `'.$sample['legacy_key'].'` '.$sample['review_status'].' '.$sample['classification'].' '.$sample['source_identity'];
        }

        if ($samples === []) {
            $lines[] = '- none';
        }

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

    /** @param array<int, array<string, mixed>> $groups @return array<int, array<string, mixed>> */
    private function largestGroups(array $groups): array
    {
        usort($groups, fn (array $left, array $right): int => $right['rows'] <=> $left['rows']);

        return $groups;
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

    private function suffix(?string $module, ?string $reviewStatus): string
    {
        $parts = array_values(array_filter([$module, $reviewStatus], 'is_string'));

        if ($parts === []) {
            return '';
        }

        $suffix = implode('_', array_map(fn (string $part): string => $this->filenamePart($part), $parts));

        return '_'.$suffix;
    }

    private function filenamePart(string $value): string
    {
        $value = preg_replace('/[^A-Za-z0-9_-]+/', '_', $value) ?? 'filter';

        return trim($value, '_') !== '' ? trim($value, '_') : 'filter';
    }
}
