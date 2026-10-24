<?php

declare(strict_types=1);

namespace App\Services\Legacy;

use App\Contracts\Legacy\LegacyDecisionPlanServiceInterface;
use App\Contracts\Legacy\LegacyQueryRedirectResolverInterface;
use App\DTOs\Legacy\LegacyDecisionPlanResultDTO;
use App\Models\Shared\MigrationRejection;
use App\Support\LegacyImport\OldDatabaseConnection;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class LegacyDecisionPlanService implements LegacyDecisionPlanServiceInterface
{
    public function __construct(
        private readonly OldDatabaseConnection $oldDatabase,
        private readonly LegacyQueryRedirectResolverInterface $queryRedirectResolver,
    ) {}

    public function export(
        string $module,
        string $disk = 'local',
        string $directory = 'legacy-import-exports/decision-plans',
    ): LegacyDecisionPlanResultDTO {
        $module = trim($module);
        $directory = trim($directory, '/');
        $directory = $directory !== '' ? $directory : 'legacy-import-exports/decision-plans';
        $rejections = MigrationRejection::query()
            ->where('module', $module)
            ->orderBy('reason_code')
            ->orderBy('source_table')
            ->orderBy('source_id')
            ->orderBy('id')
            ->get(['id', 'module', 'source_table', 'source_id', 'reason_code', 'reason_message', 'raw_summary']);

        $decisions = [];
        $duplicateRows = [];

        foreach ($rejections as $rejection) {
            $summary = is_array($rejection->raw_summary) ? $rejection->raw_summary : [];

            if ($rejection->reason_code === 'duplicate_legacy_content') {
                $duplicateRows[] = [$rejection, $summary];

                continue;
            }

            $decisions[] = $this->decisionForRow($rejection, $summary);
        }

        foreach ($this->duplicateDecisions($duplicateRows) as $decision) {
            $decisions[] = $decision;
        }

        usort($decisions, static fn (array $left, array $right): int => [
            $left['module'],
            $left['source_table'],
            $left['source_id'] ?? 0,
            $left['reason_code'],
            $left['action'],
        ] <=> [
            $right['module'],
            $right['source_table'],
            $right['source_id'] ?? 0,
            $right['reason_code'],
            $right['action'],
        ]);

        $actionCounts = collect($decisions)->countBy('action')->all();
        $manualReviewCount = (int) ($actionCounts['manual_review'] ?? 0);
        $path = $directory.'/'.now()->format('Ymd_His').'_decision_plan_'.$this->filenamePart($module).'.json';

        Storage::disk($disk)->put($path, $this->json([
            'generated_at' => now()->toIso8601String(),
            'module' => $module,
            'summary' => [
                'decision_count' => count($decisions),
                'manual_review_count' => $manualReviewCount,
                'action_counts' => $actionCounts,
            ],
            'policies' => [
                'unsafe_html' => 'Use sanitized cleaned_preview from Phase 3 cleaner.',
                'base64_inline_image' => 'Strip inline base64 images from public HTML and keep source row traceable for later media extraction.',
                'legacy_internal_link' => 'Resolve exact/known targets when safe; otherwise keep in continuity backlog and do not guess homepage redirects.',
                'duplicate_legacy_content' => 'Choose canonical by score: visible, accepted, non-archived, content completeness, recency, then highest source ID.',
                'invalid_email' => 'Skip affected account/contact rows until identity data is verified; do not guess email repairs.',
                'orphaned_child' => 'Skip from public import until a verified parent mapping exists.',
                'unsupported_locale' => 'Skip unsupported locale content in AR/EN foundation phase.',
            ],
            'decisions' => $decisions,
        ]));

        return new LegacyDecisionPlanResultDTO(
            module: $module,
            disk: $disk,
            path: $path,
            decisionCount: count($decisions),
            manualReviewCount: $manualReviewCount,
            actionCounts: $actionCounts,
        );
    }

    /** @param array<string, mixed> $summary @return array<string, mixed> */
    private function decisionForRow(MigrationRejection $rejection, array $summary): array
    {
        $base = $this->baseDecision($rejection, $summary);
        $issueCodes = $summary['issue_codes'] ?? [];
        $issueCodes = is_array($issueCodes) ? array_values(array_filter($issueCodes, 'is_string')) : [];

        if ($rejection->reason_code === 'base64_inline_image' || in_array('base64_inline_image', $issueCodes, true)) {
            return array_merge($base, [
                'action' => 'auto_strip_inline_base64_image',
                'public_import_allowed' => true,
                'cleaning_policy' => 'use_cleaned_preview_without_inline_image',
                'notes' => 'Inline base64 images are removed from public HTML and preserved through source traceability.',
            ]);
        }

        if ($rejection->reason_code === 'unsafe_html' || in_array('unsafe_html', $issueCodes, true)) {
            return array_merge($base, [
                'action' => 'auto_accept_sanitized_html',
                'public_import_allowed' => true,
                'cleaning_policy' => 'use_cleaned_preview',
                'notes' => 'Unsafe HTML is sanitized before public import.',
            ]);
        }

        return match ($rejection->reason_code) {
            'legacy_internal_link' => $this->legacyLinkDecision($base),
            'invalid_email' => array_merge($base, [
                'action' => 'auto_skip_invalid_contact_until_verified',
                'public_import_allowed' => false,
                'notes' => 'Skipped until the required email/contact identity can be verified from an authoritative source.',
            ]),
            'orphaned_child' => array_merge($base, [
                'action' => 'auto_skip_orphan_until_mapping_exists',
                'public_import_allowed' => false,
                'notes' => 'Skipped because the parent mapping is missing.',
            ]),
            'unsupported_locale' => array_merge($base, [
                'action' => 'auto_skip_unsupported_locale',
                'public_import_allowed' => false,
                'notes' => 'Skipped because this phase supports only Arabic and English content.',
            ]),
            'inline_formatting_cleaned', 'word_html_cleaned' => array_merge($base, [
                'action' => 'auto_accept_cleaned_formatting',
                'public_import_allowed' => true,
                'cleaning_policy' => 'use_cleaned_preview',
                'notes' => 'Formatting-only cleanup is safe to accept.',
            ]),
            default => array_merge($base, [
                'action' => 'manual_review',
                'public_import_allowed' => false,
                'notes' => 'No automatic decision policy exists for this reason yet.',
            ]),
        };
    }

    /** @param array<string, mixed> $base @return array<string, mixed> */
    private function legacyLinkDecision(array $base): array
    {
        $legacyPath = is_string($base['legacy_path'] ?? null) ? $base['legacy_path'] : null;
        $targetUrl = $legacyPath !== null ? $this->resolvedTargetUrl($legacyPath) : null;

        if ($targetUrl !== null) {
            return array_merge($base, [
                'action' => 'auto_redirect_candidate',
                'public_import_allowed' => true,
                'target_url' => $targetUrl,
                'notes' => 'Resolved to a safe redirect/content-link candidate. Redirect persistence remains separately gated.',
            ]);
        }

        return array_merge($base, [
            'action' => 'auto_leave_unresolved_for_continuity_phase',
            'public_import_allowed' => true,
            'target_url' => null,
            'notes' => 'Left unresolved for continuity mapping. Do not redirect to homepage by default.',
        ]);
    }

    /** @param array<int, array{0: MigrationRejection, 1: array<string, mixed>}> $duplicateRows @return array<int, array<string, mixed>> */
    private function duplicateDecisions(array $duplicateRows): array
    {
        $groups = [];

        foreach ($duplicateRows as [$rejection, $summary]) {
            $duplicateKey = $this->stringSummaryValue($summary, 'duplicate_key') ?? 'source:'.$rejection->source_table.':'.$rejection->source_id;
            $groups[$rejection->source_table.'|'.$duplicateKey][] = [$rejection, $summary];
        }

        $decisions = [];

        foreach ($groups as $rows) {
            $canonicalId = $this->canonicalSourceId($rows);

            foreach ($rows as [$rejection, $summary]) {
                $sourceId = is_numeric($rejection->source_id) ? (int) $rejection->source_id : null;
                $isCanonical = $sourceId !== null && $sourceId === $canonicalId;

                $decisions[] = array_merge($this->baseDecision($rejection, $summary), [
                    'action' => $isCanonical ? 'auto_keep_canonical_duplicate' : 'auto_skip_duplicate',
                    'public_import_allowed' => $isCanonical,
                    'duplicate_key' => $this->stringSummaryValue($summary, 'duplicate_key'),
                    'canonical_source_id' => $canonicalId,
                    'duplicate_role' => $isCanonical ? 'canonical' : 'duplicate',
                    'notes' => $isCanonical
                        ? 'Selected as canonical duplicate candidate by deterministic scoring.'
                        : 'Skipped because another source row is the canonical duplicate candidate.',
                ]);
            }
        }

        return $decisions;
    }

    /** @param array<int, array{0: MigrationRejection, 1: array<string, mixed>}> $rows */
    private function canonicalSourceId(array $rows): ?int
    {
        $sourceTable = $rows[0][0]->source_table;
        $sourceIds = collect($rows)
            ->map(fn (array $row): ?int => is_numeric($row[0]->source_id) ? (int) $row[0]->source_id : null)
            ->filter()
            ->values()
            ->all();

        if ($sourceIds === []) {
            return null;
        }

        $legacyRows = $this->legacyRowsById($sourceTable, $sourceIds);
        $ranked = [];

        foreach ($sourceIds as $sourceId) {
            $legacyRow = $legacyRows[$sourceId] ?? null;
            $ranked[] = [
                'source_id' => $sourceId,
                'score' => $legacyRow !== null ? $this->canonicalScore($legacyRow) : 0.0,
            ];
        }

        usort($ranked, static fn (array $left, array $right): int => [$right['score'], $right['source_id']] <=> [$left['score'], $left['source_id']]);

        return (int) $ranked[0]['source_id'];
    }

    /** @param array<int, int> $sourceIds @return array<int, object> */
    private function legacyRowsById(string $sourceTable, array $sourceIds): array
    {
        try {
            return $this->oldDatabase->table($sourceTable)
                ->whereIn('id', $sourceIds)
                ->get()
                ->mapWithKeys(fn (object $row): array => [(int) ($row->id ?? 0) => $row])
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    private function canonicalScore(object $row): float
    {
        $score = 0.0;
        $score += $this->truthy($row->is_visible ?? null) ? 100000.0 : 0.0;
        $score += $this->truthy($row->is_accepted ?? null) ? 50000.0 : 0.0;
        $score += $this->truthy($row->is_archive ?? null) ? 0.0 : 10000.0;
        $score += min(9000, $this->contentLength($row));
        $score += $this->dateScore($row);

        return $score;
    }

    private function truthy(mixed $value): bool
    {
        return in_array($value, [1, '1', true, 'true', 'yes', 'on'], true);
    }

    private function contentLength(object $row): int
    {
        $length = 0;

        foreach (['ar_data', 'en_data', 'ar_description', 'en_description', 'ar_brief', 'en_brief', 'ar_name', 'en_name', 'name', 'description'] as $column) {
            $value = $row->{$column} ?? null;

            if (is_scalar($value)) {
                $length += mb_strlen(trim((string) $value));
            }
        }

        return $length;
    }

    private function dateScore(object $row): float
    {
        foreach (['updated_date', 'added_date', 'post_date', 'start_date'] as $column) {
            $value = $row->{$column} ?? null;

            if (! is_scalar($value) || trim((string) $value) === '') {
                continue;
            }

            try {
                return CarbonImmutable::parse((string) $value)->timestamp / 86400;
            } catch (Throwable) {
                continue;
            }
        }

        return 0.0;
    }

    /** @param array<string, mixed> $summary @return array<string, mixed> */
    private function baseDecision(MigrationRejection $rejection, array $summary): array
    {
        return [
            'module' => $rejection->module,
            'source_table' => $rejection->source_table,
            'source_id' => $rejection->source_id,
            'reason_code' => $rejection->reason_code,
            'field' => $this->stringSummaryValue($summary, 'field'),
            'legacy_path' => $this->stringSummaryValue($summary, 'legacy_path'),
            'cleaned_preview' => $this->preview($this->stringSummaryValue($summary, 'cleaned_preview')),
            'original_preview' => $this->preview($this->stringSummaryValue($summary, 'original_preview')),
            'raw_summary' => $summary,
        ];
    }

    private function resolvedTargetUrl(string $legacyPath): ?string
    {
        $parts = parse_url($legacyPath);

        if (! is_array($parts)) {
            return null;
        }

        $path = (string) ($parts['path'] ?? '');
        $query = (string) ($parts['query'] ?? '');
        $resolved = $this->queryRedirectResolver->resolve($path, $query !== '' ? $query : null);

        if ($resolved !== null) {
            return $resolved->destinationUrl;
        }

        return $this->knownLegacyTargetUrl($legacyPath);
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

        return mb_substr(preg_replace('/\s+/u', ' ', trim($value)) ?? $value, 0, 500);
    }

    /** @param array<string, mixed> $payload */
    private function json(array $payload): string
    {
        return (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function filenamePart(string $value): string
    {
        return preg_replace('/[^a-z0-9_-]+/i', '-', $value) ?: 'module';
    }
}
