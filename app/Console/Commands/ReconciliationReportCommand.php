<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Shared\ContinuityServiceInterface;
use App\Contracts\Seo\SeoMetadataServiceInterface;
use App\Models\Legacy\LegacyRecordSnapshot;
use App\Models\Shared\MigrationLog;
use App\Models\Shared\MigrationRejection;
use App\Models\Page\Page;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

final class ReconciliationReportCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'continuity:reconciliation-report
        {--format=json : Output format (json or csv)}
        {--disk=local : Storage disk for file export}
        {--dir=continuity-exports : Export directory}';

    /**
     * @var string
     */
    protected $description = 'Combined reconciliation report: URL inventory, redirect validation, file inventory, unresolved requests, and SEO gaps';

    public function __construct(
        private readonly ContinuityServiceInterface $continuityService,
        private readonly SeoMetadataServiceInterface $seoService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $format = (string) $this->option('format');
        $disk = (string) $this->option('disk');
        $dir = rtrim((string) $this->option('dir'), '/');

        $this->info('Generating reconciliation report...');
        $this->newLine();

        // 1. URL Inventory
        $this->line('→ Collecting URL inventory...');
        $urlInventory = $this->buildUrlInventory();

        // 2. Redirect Validation
        $this->line('→ Validating redirect rules...');
        $redirectValidation = $this->buildRedirectValidation();

        // 3. File Inventory
        $this->line('→ Collecting file inventory...');
        $fileInventory = $this->buildFileInventory();

        // 4. Unresolved Requests
        $this->line('→ Collecting unresolved requests...');
        $unresolvedRequests = $this->buildUnresolvedRequests();

        // 5. SEO Gaps
        $this->line('→ Validating SEO metadata...');
        $seoGaps = $this->buildSeoGaps();

        // 6. Ambiguous structures
        $this->line('→ Identifying ambiguous structures...');
        $ambiguous = $this->detectAmbiguousStructures($urlInventory, $fileInventory);

        $payload = [
            'generated_at' => now()->toIso8601String(),
            'summary' => [
                'url_inventory_total' => count($urlInventory),
                'redirect_issues' => count($redirectValidation),
                'file_inventory_total' => count($fileInventory),
                'unresolved_requests_total' => count($unresolvedRequests),
                'seo_gaps_total' => count($seoGaps),
                'ambiguous_structures' => count($ambiguous),
            ],
            'url_inventory' => $urlInventory,
            'redirect_validation' => $redirectValidation,
            'file_inventory' => $fileInventory,
            'unresolved_requests' => $unresolvedRequests,
            'seo_gaps' => $seoGaps,
            'ambiguous_structures' => $ambiguous,
        ];

        $this->newLine();
        $this->outputSummary($payload);

        $timestamp = now()->format('Ymd_His');
        $filename = "reconciliation_report_{$timestamp}";

        if ($format === 'csv') {
            $this->writeCsvSections($disk, $dir, $timestamp, $payload);
        } else {
            $this->writeJson($disk, "{$dir}/{$filename}.json", $payload);
        }

        $this->newLine();
        $this->info('Reconciliation report exported.');
        $this->line("Disk: {$disk}");
        $this->line("Directory: {$dir}");

        return self::SUCCESS;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildUrlInventory(): array
    {
        $items = [];

        foreach ($this->continuityService->getExactRedirects() as $rule) {
            $items[] = [
                'source_type' => 'exact_redirect',
                'legacy_path' => $rule->legacyPath,
                'expected_destination' => $rule->destinationUrl,
                'locale' => $rule->locale ?? '',
                'status' => $rule->isActive ? 'active' : 'inactive',
            ];
        }

        foreach ($this->continuityService->getPatternRules() as $rule) {
            $items[] = [
                'source_type' => 'pattern_rule',
                'legacy_path' => $rule->pattern,
                'expected_destination' => $rule->replacement,
                'locale' => '',
                'status' => $rule->isActive ? 'active' : 'inactive',
            ];
        }

        foreach (LegacyRecordSnapshot::query()->orderBy('id')->get() as $snapshot) {
            $legacyPath = $snapshot->legacy_key
                ?? (is_array($snapshot->payload_json) ? ($snapshot->payload_json['legacy_path'] ?? null) : null)
                ?? $snapshot->payload_text;

            if (! is_string($legacyPath) || trim($legacyPath) === '') {
                continue;
            }

            $items[] = [
                'source_type' => 'snapshot:'.$snapshot->module,
                'legacy_path' => $legacyPath,
                'expected_destination' => '',
                'locale' => $snapshot->locale ?? '',
                'status' => $snapshot->classification,
            ];
        }

        foreach (MigrationLog::query()->whereNotNull('metadata')->orderBy('id')->get() as $log) {
            $legacyPath = is_array($log->metadata) ? ($log->metadata['legacy_path'] ?? null) : null;

            if (! is_string($legacyPath) || trim($legacyPath) === '') {
                continue;
            }

            $items[] = [
                'source_type' => 'migration_log:'.$log->module,
                'legacy_path' => $legacyPath,
                'expected_destination' => is_string($log->metadata['destination_url'] ?? null) ? $log->metadata['destination_url'] : '',
                'locale' => is_string($log->metadata['locale'] ?? null) ? $log->metadata['locale'] : '',
                'status' => $log->status,
            ];
        }

        foreach (MigrationRejection::query()->whereNotNull('raw_summary')->orderBy('id')->get() as $rejection) {
            $legacyPath = is_array($rejection->raw_summary) ? ($rejection->raw_summary['legacy_path'] ?? null) : null;

            if (! is_string($legacyPath) || trim($legacyPath) === '') {
                continue;
            }

            $items[] = [
                'source_type' => 'rejection:'.$rejection->module,
                'legacy_path' => $legacyPath,
                'expected_destination' => '',
                'locale' => is_string($rejection->raw_summary['locale'] ?? null) ? $rejection->raw_summary['locale'] : '',
                'status' => $rejection->reason_code,
            ];
        }

        return $items;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function buildRedirectValidation(): array
    {
        $result = $this->continuityService->validateRedirectRules();
        $issues = [];

        foreach ($result->errors as $error) {
            foreach ($error->messages as $message) {
                $issues[] = [
                    'source' => $error->field,
                    'issue' => $message,
                ];
            }
        }

        return $issues;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildFileInventory(): array
    {
        return $this->continuityService->getFileInventory()->map(fn ($item): array => [
            'id' => $item->id,
            'legacy_path' => $item->legacyPath,
            'current_path' => $item->currentPath ?? '',
            'media_asset_id' => $item->mediaAssetId ?? '',
            'status' => $item->status,
        ])->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildUnresolvedRequests(): array
    {
        return $this->continuityService->getUnresolvedRequests()->map(fn ($r): array => [
            'url' => $r->url,
            'method' => $r->method,
            'request_type' => $r->requestType,
            'resolved_locale' => $r->resolvedLocale ?? '',
            'timestamp' => $r->timestamp,
        ])->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildSeoGaps(): array
    {
        $pages = Page::query()
            ->published()
            ->enabled()
            ->with(['seoMeta', 'translations'])
            ->get();

        $gaps = [];

        foreach ($pages as $page) {
            foreach (['ar', 'en'] as $locale) {
                $seoMeta = $page->seoMeta->firstWhere('locale', $locale);
                $issues = [];

                if ($seoMeta === null) {
                    $issues[] = 'missing_seo_record';
                } else {
                    if (empty($seoMeta->meta_title)) {
                        $issues[] = 'missing_meta_title';
                    }
                    if (empty($seoMeta->meta_description)) {
                        $issues[] = 'missing_meta_description';
                    }
                    if (empty($seoMeta->canonical_url)) {
                        $issues[] = 'missing_canonical_url';
                    }
                }

                if ($issues !== []) {
                    $gaps[] = [
                        'page_id' => (int) $page->getKey(),
                        'slug' => (string) $page->slug,
                        'locale' => $locale,
                        'issues' => implode(', ', $issues),
                    ];
                }
            }
        }

        return $gaps;
    }

    /**
     * Detect ambiguous or overlapping legacy structures requiring engineering review.
     *
     * @param  array<int, array<string, mixed>>  $urlInventory
     * @param  array<int, array<string, mixed>>  $fileInventory
     * @return array<int, array<string, string>>
     */
    private function detectAmbiguousStructures(array $urlInventory, array $fileInventory): array
    {
        $ambiguous = [];

        // Detect URL paths that appear in both redirect rules and file inventory
        $urlPaths = collect($urlInventory)->pluck('legacy_path')->map(fn (string $p): string => mb_strtolower($p))->all();
        $filePaths = collect($fileInventory)->pluck('legacy_path')->map(fn (string $p): string => mb_strtolower($p))->all();

        $overlapping = array_intersect($urlPaths, $filePaths);

        foreach ($overlapping as $path) {
            $ambiguous[] = [
                'type' => 'url_file_overlap',
                'path' => $path,
                'description' => 'Legacy path exists in both URL redirect rules and file inventory — requires engineering review',
            ];
        }

        // Detect inactive redirects that might still receive traffic
        $inactiveWithTraffic = collect($urlInventory)
            ->filter(fn (array $item): bool => $item['status'] === 'inactive');

        foreach ($inactiveWithTraffic as $item) {
            $ambiguous[] = [
                'type' => 'inactive_redirect',
                'path' => $item['legacy_path'],
                'description' => 'Inactive redirect rule — verify if this path still receives traffic',
            ];
        }

        return $ambiguous;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function outputSummary(array $payload): void
    {
        $this->info('Reconciliation Summary');
        $this->table(
            ['Section', 'Count'],
            [
                ['URL Inventory', $payload['summary']['url_inventory_total']],
                ['Redirect Issues', $payload['summary']['redirect_issues']],
                ['File Inventory', $payload['summary']['file_inventory_total']],
                ['Unresolved Requests', $payload['summary']['unresolved_requests_total']],
                ['SEO Gaps', $payload['summary']['seo_gaps_total']],
                ['Ambiguous Structures', $payload['summary']['ambiguous_structures']],
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function writeJson(string $disk, string $path, array $payload): void
    {
        Storage::disk($disk)->put(
            $path,
            (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function writeCsvSections(string $disk, string $dir, string $timestamp, array $payload): void
    {
        $prefix = "{$dir}/reconciliation_{$timestamp}";

        $this->writeCsv($disk, "{$prefix}_url_inventory.csv", $payload['url_inventory']);
        $this->writeCsv($disk, "{$prefix}_redirect_issues.csv", $payload['redirect_validation']);
        $this->writeCsv($disk, "{$prefix}_file_inventory.csv", $payload['file_inventory']);
        $this->writeCsv($disk, "{$prefix}_unresolved.csv", $payload['unresolved_requests']);
        $this->writeCsv($disk, "{$prefix}_seo_gaps.csv", $payload['seo_gaps']);
        $this->writeCsv($disk, "{$prefix}_ambiguous.csv", $payload['ambiguous_structures']);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function writeCsv(string $disk, string $path, array $rows): void
    {
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            return;
        }

        if ($rows !== []) {
            fputcsv($handle, array_keys($rows[0]));

            foreach ($rows as $row) {
                fputcsv($handle, array_map(
                    static fn (mixed $value): string => is_array($value) || is_object($value)
                        ? (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                        : (string) ($value ?? ''),
                    $row,
                ));
            }
        }

        rewind($handle);
        Storage::disk($disk)->put($path, (string) stream_get_contents($handle));
        fclose($handle);
    }
}
