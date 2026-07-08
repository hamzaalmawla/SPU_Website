<?php

declare(strict_types=1);

namespace App\Services\Legacy;

use App\Contracts\Legacy\LegacyQuarantineSummaryServiceInterface;
use App\DTOs\Legacy\LegacyQuarantineSummaryResultDTO;
use App\Models\Shared\MigrationRejection;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class LegacyQuarantineSummaryService implements LegacyQuarantineSummaryServiceInterface
{
    public function export(
        ?string $module = null,
        string $disk = 'local',
        string $directory = 'legacy-import-exports/quarantine-summary',
    ): LegacyQuarantineSummaryResultDTO {
        $module = $this->normalizedFilter($module);
        $directory = trim($directory, '/');
        $directory = $directory !== '' ? $directory : 'legacy-import-exports/quarantine-summary';

        $rejections = MigrationRejection::query()
            ->when($module !== null, fn ($query) => $query->where('module', $module))
            ->orderBy('module')
            ->orderBy('reason_code')
            ->orderBy('source_table')
            ->orderBy('source_id')
            ->orderBy('id')
            ->get(['id', 'module', 'source_table', 'source_id', 'reason_code', 'reason_message', 'raw_summary']);

        $rows = $rejections
            ->map(fn (MigrationRejection $rejection): array => $this->reviewRow($rejection))
            ->values()
            ->all();
        $summaryRows = $this->summaryRows($rows);
        $decisionRows = $this->decisionRows($rows);
        $stamp = now()->format('Ymd_His');
        $suffix = $module !== null ? '_'.$this->filenamePart($module) : '';
        $basePath = $directory.'/'.$stamp.'_quarantine_summary'.$suffix;
        $paths = [
            $basePath.'.md',
            $basePath.'_groups.csv',
            $basePath.'_needs_decision.csv',
        ];

        Storage::disk($disk)->put($paths[0], $this->markdown($module, $rows, $summaryRows, $decisionRows));
        Storage::disk($disk)->put($paths[1], $this->csvPayload($summaryRows, [
            'module',
            'reason_code',
            'field',
            'count',
            'suggested_action',
            'needs_user_decision',
            'meaning',
            'sample_source_ids',
            'sample_legacy_paths',
            'suggested_target_url',
            'sample_original_preview',
            'sample_cleaned_preview',
        ]));
        Storage::disk($disk)->put($paths[2], $this->csvPayload($decisionRows, [
            'module',
            'reason_code',
            'suggested_action',
            'decision_needed',
            'group_key',
            'affected_rows',
            'source_ids',
            'legacy_path',
            'suggested_target_url',
            'original_preview',
            'cleaned_preview',
            'notes',
        ]));

        return new LegacyQuarantineSummaryResultDTO(
            disk: $disk,
            module: $module,
            rowCount: count($rows),
            summaryGroupCount: count($summaryRows),
            needsDecisionGroupCount: count($decisionRows),
            paths: $paths,
        );
    }

    /** @return array<string, mixed> */
    private function reviewRow(MigrationRejection $rejection): array
    {
        $summary = is_array($rejection->raw_summary) ? $rejection->raw_summary : [];
        $classification = $this->classification($rejection->reason_code, $summary);

        return [
            'module' => $rejection->module,
            'source_table' => $rejection->source_table,
            'source_id' => $rejection->source_id,
            'reason_code' => $rejection->reason_code,
            'reason_message' => $rejection->reason_message,
            'field' => $this->stringSummaryValue($summary, 'field'),
            'legacy_path' => $this->stringSummaryValue($summary, 'legacy_path'),
            'suggested_target_url' => $classification['suggested_target_url'],
            'original_preview' => $this->preview($this->stringSummaryValue($summary, 'original_preview')),
            'cleaned_preview' => $this->preview($this->stringSummaryValue($summary, 'cleaned_preview')),
            'group_key' => $this->groupKey($rejection, $summary),
            'suggested_action' => $classification['suggested_action'],
            'needs_user_decision' => $classification['needs_user_decision'],
            'meaning' => $classification['meaning'],
            'decision_needed' => $classification['decision_needed'],
            'notes' => $classification['notes'],
        ];
    }

    /** @param array<int, array<string, mixed>> $rows @return array<int, array<string, mixed>> */
    private function summaryRows(array $rows): array
    {
        $groups = [];

        foreach ($rows as $row) {
            $key = implode('|', [$row['module'], $row['reason_code'], $row['field'] ?? '']);
            $groups[$key][] = $row;
        }

        return collect($groups)
            ->map(function (array $group): array {
                $first = $group[0];

                return [
                    'module' => $first['module'],
                    'reason_code' => $first['reason_code'],
                    'field' => $first['field'],
                    'count' => count($group),
                    'suggested_action' => $first['suggested_action'],
                    'needs_user_decision' => $first['needs_user_decision'] ? 'yes' : 'no',
                    'meaning' => $first['meaning'],
                    'sample_source_ids' => $this->sampleValues($group, 'source_id'),
                    'sample_legacy_paths' => $this->sampleValues($group, 'legacy_path'),
                    'suggested_target_url' => $this->sampleValues($group, 'suggested_target_url'),
                    'sample_original_preview' => $this->sampleValues($group, 'original_preview', 1),
                    'sample_cleaned_preview' => $this->sampleValues($group, 'cleaned_preview', 1),
                ];
            })
            ->sortBy([['needs_user_decision', 'desc'], ['module', 'asc'], ['reason_code', 'asc'], ['field', 'asc']])
            ->values()
            ->all();
    }

    /** @param array<int, array<string, mixed>> $rows @return array<int, array<string, mixed>> */
    private function decisionRows(array $rows): array
    {
        $groups = [];

        foreach ($rows as $row) {
            if (! $row['needs_user_decision']) {
                continue;
            }

            $groups[$row['module'].'|'.$row['reason_code'].'|'.$row['group_key']][] = $row;
        }

        return collect($groups)
            ->map(function (array $group): array {
                $first = $group[0];

                return [
                    'module' => $first['module'],
                    'reason_code' => $first['reason_code'],
                    'suggested_action' => $first['suggested_action'],
                    'decision_needed' => $first['decision_needed'],
                    'group_key' => $first['group_key'],
                    'affected_rows' => count($group),
                    'source_ids' => $this->sampleValues($group, 'source_id', 20),
                    'legacy_path' => $first['legacy_path'],
                    'suggested_target_url' => $first['suggested_target_url'],
                    'original_preview' => $this->sampleValues($group, 'original_preview', 1),
                    'cleaned_preview' => $this->sampleValues($group, 'cleaned_preview', 1),
                    'notes' => $first['notes'],
                ];
            })
            ->sortBy([['module', 'asc'], ['reason_code', 'asc'], ['group_key', 'asc']])
            ->values()
            ->all();
    }

    /** @param array<string, mixed> $summary @return array{suggested_action: string, needs_user_decision: bool, meaning: string, decision_needed: string, notes: string, suggested_target_url: ?string} */
    private function classification(string $reasonCode, array $summary): array
    {
        $issueCodes = $summary['issue_codes'] ?? [];
        $issueCodes = is_array($issueCodes) ? array_values(array_filter($issueCodes, 'is_string')) : [];

        if (in_array('base64_inline_image', $issueCodes, true) || $reasonCode === 'base64_inline_image') {
            return $this->automatic('auto_strip_inline_base64_image', 'Embedded image is stored inside old HTML.', 'Strip the embedded base64 image from public content and keep the row in media-extraction backlog.', 'No user decision needed; inline base64 blobs must not enter public CMS HTML.');
        }

        if (in_array('unsafe_html', $issueCodes, true) || $reasonCode === 'unsafe_html') {
            return $this->automatic('auto_accept_sanitized_html', 'Old HTML contained unsafe markup or unsafe links.', 'Use the sanitized HTML generated by the cleaner.', 'No user decision needed; unsafe HTML is removed before import.');
        }

        return match ($reasonCode) {
            'legacy_internal_link' => $this->legacyLinkClassification($summary),
            'duplicate_legacy_content' => $this->automatic('auto_pick_canonical_candidate', 'Multiple old records share the same duplicate key.', 'Pick a canonical record deterministically during import planning and skip/merge the rest.', 'Canonical scoring should prefer visible, accepted, newest, content-complete records; no spreadsheet decision needed.'),
            'invalid_email' => $this->automatic('auto_skip_invalid_contact_until_verified', 'A required old email value is invalid.', 'Skip the affected account/contact row until the identity can be verified from an authoritative source.', 'No user decision needed during migration; do not guess or repair identity data automatically.'),
            'orphaned_child' => $this->automatic('auto_skip_orphan_until_mapping_exists', 'A child record references a missing old parent/category.', 'Skip the orphan from public import unless a verified parent mapping appears later.', 'No user decision needed; importing orphaned records would create broken public content.'),
            'unsupported_locale' => $this->automatic('auto_skip_unsupported_locale', 'Old content has a locale outside AR/EN.', 'Skip unsupported locale content from this AR/EN foundation import.', 'Managed content supports only ar and en in this phase.'),
            'inline_formatting_cleaned', 'word_html_cleaned' => [
                'suggested_action' => 'auto_approve_cleaned',
                'needs_user_decision' => false,
                'meaning' => 'Only formatting/Word markup cleanup was detected.',
                'decision_needed' => 'No user decision needed unless the sample looks broken.',
                'notes' => 'Safe to accept in bulk after spot-checking samples.',
                'suggested_target_url' => null,
            ],
            default => $this->decision('manual_review', 'This reason needs review.', 'Review the sample and decide whether to import, clean, map, or skip.', 'No automatic rule exists for this reason yet.'),
        };
    }

    /** @param array<string, mixed> $summary @return array{suggested_action: string, needs_user_decision: bool, meaning: string, decision_needed: string, notes: string, suggested_target_url: ?string} */
    private function legacyLinkClassification(array $summary): array
    {
        $legacyPath = $this->stringSummaryValue($summary, 'legacy_path');
        $targetUrl = $legacyPath !== null ? $this->knownLegacyTargetUrl($legacyPath) : null;

        if ($targetUrl !== null) {
            $classification = $this->automatic('auto_redirect_candidate', 'Old content links to an obvious old SPU URL.', 'Use the suggested target URL as a redirect/content-link candidate.', 'Final redirect creation still stays gated and auditable.');
            $classification['suggested_target_url'] = $targetUrl;

            return $classification;
        }

        return $this->automatic('auto_leave_unresolved_for_continuity_phase', 'Old content links to another old SPU URL.', 'Keep the link in continuity backlog until a resolver can map it safely.', 'No user decision needed now; unresolved valuable URLs must not be guessed or redirected to the homepage.');
    }

    /** @return array{suggested_action: string, needs_user_decision: bool, meaning: string, decision_needed: string, notes: string, suggested_target_url: ?string} */
    private function automatic(string $suggestedAction, string $meaning, string $decisionNeeded, string $notes): array
    {
        return [
            'suggested_action' => $suggestedAction,
            'needs_user_decision' => false,
            'meaning' => $meaning,
            'decision_needed' => $decisionNeeded,
            'notes' => $notes,
            'suggested_target_url' => null,
        ];
    }

    /** @return array{suggested_action: string, needs_user_decision: bool, meaning: string, decision_needed: string, notes: string, suggested_target_url: ?string} */
    private function decision(string $suggestedAction, string $meaning, string $decisionNeeded, string $notes): array
    {
        return [
            'suggested_action' => $suggestedAction,
            'needs_user_decision' => true,
            'meaning' => $meaning,
            'decision_needed' => $decisionNeeded,
            'notes' => $notes,
            'suggested_target_url' => null,
        ];
    }

    private function knownLegacyTargetUrl(string $legacyPath): ?string
    {
        $parts = parse_url($legacyPath);

        if (! is_array($parts)) {
            return null;
        }

        $path = strtolower((string) ($parts['path'] ?? ''));

        if ($path !== '/index.php' && $path !== 'index.php') {
            return null;
        }

        parse_str((string) ($parts['query'] ?? ''), $query);
        $locale = match ((string) ($query['lang'] ?? '2')) {
            '1' => 'en',
            '2' => 'ar',
            default => null,
        };

        if ($locale === null) {
            return null;
        }

        $page = strtolower((string) ($query['page'] ?? ''));
        $dir = strtolower((string) ($query['dir'] ?? ''));

        if ($page === '' && $dir === '') {
            return '/'.$locale;
        }

        if ($page === 'contactus' && $dir === 'html') {
            return '/'.$locale.'/contact';
        }

        return null;
    }

    /** @param array<string, mixed> $summary */
    private function groupKey(MigrationRejection $rejection, array $summary): string
    {
        $legacyPath = $this->stringSummaryValue($summary, 'legacy_path');

        if ($legacyPath !== null) {
            return 'legacy_path:'.$legacyPath;
        }

        $duplicateKey = $this->stringSummaryValue($summary, 'duplicate_key');

        if ($duplicateKey !== null) {
            return 'duplicate_key:'.$duplicateKey;
        }

        $missingParentId = $this->stringSummaryValue($summary, 'missing_parent_id');

        if ($missingParentId !== null) {
            return 'missing_parent:'.$missingParentId;
        }

        return $rejection->source_table.':'.($rejection->source_id ?? 'unknown').':'.($this->stringSummaryValue($summary, 'field') ?? $rejection->reason_code);
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function markdown(?string $module, array $rows, array $summaryRows, array $decisionRows): string
    {
        $lines = [
            '# Legacy Quarantine Review Summary',
            '',
            '- Generated at: '.now()->toIso8601String(),
            '- Module: '.($module ?? 'all'),
            '- Raw review rows: '.count($rows),
            '- Grouped issue rows: '.count($summaryRows),
            '- Groups needing decisions: '.count($decisionRows),
            '',
            '## What You Need To Do',
            '',
            'Open the `_needs_decision.csv` file first. It is the only file intended for manual decisions.',
            '',
            'Use simple decisions such as `approve_cleaned`, `skip`, `choose_canonical`, `manual_redirect`, or `replace_media`.',
            '',
            '## Grouped Counts',
            '',
            '| Module | Reason | Field | Count | Suggested Action | Suggested Target | Needs You? |',
            '| --- | --- | --- | ---: | --- | --- | --- |',
        ];

        foreach ($summaryRows as $row) {
            $lines[] = '| '.$this->markdownCell($row['module']).' | '.$this->markdownCell($row['reason_code']).' | '.$this->markdownCell($row['field']).' | '.$row['count'].' | '.$this->markdownCell($row['suggested_action']).' | '.$this->markdownCell($row['suggested_target_url']).' | '.$this->markdownCell($row['needs_user_decision']).' |';
        }

        $lines[] = '';
        $lines[] = '## Decision Groups';
        $lines[] = '';

        foreach (array_slice($decisionRows, 0, 50) as $row) {
            $lines[] = '- '.$row['module'].' / '.$row['reason_code'].' / '.$row['suggested_action'].' / affected rows: '.$row['affected_rows'];
            $lines[] = '  Decision: '.$row['decision_needed'];
            $lines[] = '  Key: '.$row['group_key'];
        }

        if (count($decisionRows) > 50) {
            $lines[] = '- Additional decision groups are in the `_needs_decision.csv` file.';
        }

        return implode("\n", $lines)."\n";
    }

    /** @param array<int, array<string, mixed>> $rows @param array<int, string> $headers */
    private function csvPayload(array $rows, array $headers): string
    {
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            throw new RuntimeException('Unable to create quarantine summary CSV stream.');
        }

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

    /** @param array<int, array<string, mixed>> $rows */
    private function sampleValues(array $rows, string $key, int $limit = 5): string
    {
        return collect($rows)
            ->pluck($key)
            ->filter(fn (mixed $value): bool => is_scalar($value) && trim((string) $value) !== '')
            ->map(fn (mixed $value): string => (string) $value)
            ->unique()
            ->take($limit)
            ->implode(' | ');
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
        return preg_replace('/[^a-z0-9_-]+/i', '-', $value) ?: 'filter';
    }

    /** @param array<string, mixed> $summary */
    private function stringSummaryValue(array $summary, string $key): ?string
    {
        $value = $summary[$key] ?? null;

        return is_scalar($value) ? (string) $value : null;
    }

    private function preview(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return mb_substr(preg_replace('/\s+/u', ' ', trim($value)) ?? $value, 0, 240);
    }

    private function markdownCell(mixed $value): string
    {
        return str_replace('|', '\\|', (string) ($value ?? ''));
    }
}
