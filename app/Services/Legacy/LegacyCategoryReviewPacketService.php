<?php

declare(strict_types=1);

namespace App\Services\Legacy;

use App\Contracts\Legacy\LegacyCategoryReviewPacketServiceInterface;
use App\DTOs\Legacy\LegacyCategoryReviewPacketResultDTO;
use App\Support\LegacyImport\OldDatabaseConnection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

final class LegacyCategoryReviewPacketService implements LegacyCategoryReviewPacketServiceInterface
{
    private const SOURCE_TABLE = 'jx_categories';

    /** @var array<string, array<int, int>> */
    private const SUBSITE_SERVICES = [
        'root' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10],
        'admin' => [71, 72, 73, 74, 75, 76, 77, 78, 79],
    ];

    /** @var array<int, array{semantic: string, action: string, module: string, confidence: string}> */
    private const CONTEXTS = [
        1 => ['semantic' => 'main_navigation', 'action' => 'menu_mapping_review', 'module' => 'menu', 'confidence' => 'high'],
        2 => ['semantic' => 'secondary_navigation', 'action' => 'menu_mapping_review', 'module' => 'menu', 'confidence' => 'high'],
        3 => ['semantic' => 'news', 'action' => 'news_import_review', 'module' => 'news', 'confidence' => 'medium'],
        4 => ['semantic' => 'announcements', 'action' => 'announcement_import_review', 'module' => 'news', 'confidence' => 'medium'],
        5 => ['semantic' => 'events_cooperation', 'action' => 'events_module_review', 'module' => 'events', 'confidence' => 'medium'],
        6 => ['semantic' => 'general_content', 'action' => 'page_module_review', 'module' => 'pages', 'confidence' => 'medium'],
        7 => ['semantic' => 'research_statistics', 'action' => 'research_module_review', 'module' => 'research', 'confidence' => 'medium'],
        8 => ['semantic' => 'gallery', 'action' => 'gallery_module_review', 'module' => 'gallery', 'confidence' => 'medium'],
        9 => ['semantic' => 'jobs_publications', 'action' => 'typed_module_review', 'module' => 'typed_content', 'confidence' => 'medium'],
        10 => ['semantic' => 'unknown_extension', 'action' => 'unknown_service_review', 'module' => 'unknown', 'confidence' => 'low'],
        71 => ['semantic' => 'main_navigation', 'action' => 'menu_mapping_review', 'module' => 'menu', 'confidence' => 'high'],
        72 => ['semantic' => 'secondary_navigation', 'action' => 'menu_mapping_review', 'module' => 'menu', 'confidence' => 'high'],
        73 => ['semantic' => 'faculty_news', 'action' => 'faculty_news_review', 'module' => 'news', 'confidence' => 'medium'],
        74 => ['semantic' => 'faculty_research_projects', 'action' => 'faculty_research_review', 'module' => 'faculty_research', 'confidence' => 'medium'],
        75 => ['semantic' => 'events_cooperation', 'action' => 'events_module_review', 'module' => 'events', 'confidence' => 'medium'],
        76 => ['semantic' => 'general_content', 'action' => 'faculty_page_review', 'module' => 'faculty_pages', 'confidence' => 'medium'],
        77 => ['semantic' => 'research_statistics', 'action' => 'faculty_research_review', 'module' => 'faculty_research', 'confidence' => 'medium'],
        78 => ['semantic' => 'gallery', 'action' => 'gallery_module_review', 'module' => 'gallery', 'confidence' => 'medium'],
        79 => ['semantic' => 'jobs_publications', 'action' => 'typed_module_review', 'module' => 'typed_content', 'confidence' => 'medium'],
    ];

    /** @var array<int, string> */
    private const CSV_HEADERS = [
        'source_table', 'source_id', 'subsite', 'service_type', 'category_order', 'parent_id',
        'context_semantic', 'candidate_target_module', 'recommended_action', 'confidence',
        'review_status', 'blockers', 'approval_decision', 'approved_target', 'ar_name', 'en_name',
        'is_visible', 'is_link', 'url', 'photo', 'start_date', 'end_date', 'ar_content_present',
        'ar_content_length', 'en_content_present', 'en_content_length', 'child_total_count',
        'child_visible_count', 'child_photo_count', 'child_ar_file_count', 'child_en_file_count',
        'has_parent', 'parent_exists', 'is_orphan', 'legacy_ar_url_candidate', 'legacy_en_url_candidate',
        'migration_log_success_count', 'migration_log_target_tables', 'migration_log_target_ids',
        'news_article_match_count', 'news_article_ids', 'news_article_slugs', 'news_article_statuses',
        'existing_target_mapping',
    ];

    public function __construct(private readonly OldDatabaseConnection $oldDatabase) {}

    public function export(
        array $subsites = [],
        array $services = [],
        string $disk = 'local',
        string $directory = 'legacy-import-exports/category-review-packets',
    ): LegacyCategoryReviewPacketResultDTO {
        [$selectedSubsites, $selectedServices] = $this->validatedSelection($subsites, $services);
        $directory = trim($directory, '/');
        $directory = $directory !== '' ? $directory : 'legacy-import-exports/category-review-packets';
        $warnings = [];
        $sourceRows = [];
        $allIds = [];

        $schema = $this->oldDatabase->schema();

        if (! $schema->hasTable(self::SOURCE_TABLE)) {
            $warnings[] = 'Missing legacy source table [jx_categories].';
        } else {
            $allIds = $this->oldDatabase->table(self::SOURCE_TABLE)->orderBy('id')->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();
            $lengthFunction = $this->oldDatabase->connection()->getDriverName() === 'sqlite' ? 'LENGTH' : 'CHAR_LENGTH';
            $sourceRows = $this->oldDatabase->table(self::SOURCE_TABLE)
                ->select([
                    'id', 'parent', 'service_type', 'category_order', 'ar_name', 'en_name', 'is_visible',
                    'is_link', 'url', 'photo', 'start_date', 'end_date',
                ])
                ->selectRaw($lengthFunction.'(ar_data) as ar_content_length')
                ->selectRaw($lengthFunction.'(en_data) as en_content_length')
                ->whereIn('service_type', $selectedServices)
                ->orderBy('service_type')
                ->orderBy('category_order')
                ->orderBy('id')
                ->get()
                ->all();
        }

        $sourceIds = array_map(static fn (object $row): int => (int) $row->id, $sourceRows);
        $children = $this->childEvidence($sourceIds, $warnings);
        $migrationMappings = $this->migrationMappings($sourceIds);
        $newsMappings = $this->newsMappings($sourceIds);
        $allIdSet = array_fill_keys($allIds, true);
        $rows = [];

        foreach ($sourceRows as $sourceRow) {
            $rows[] = $this->reviewRow($sourceRow, $allIdSet, $children, $migrationMappings, $newsMappings);
        }

        $stamp = now()->format('Ymd_His');
        $basePath = $directory.'/'.$stamp;
        $paths = [];

        foreach ($selectedServices as $service) {
            $subsite = $this->subsiteForService($service);
            $packetRows = array_values(array_filter($rows, static fn (array $row): bool => $row['service_type'] === $service));
            $path = $basePath.'/'.sprintf('%s_service_%02d.csv', $subsite, $service);
            Storage::disk($disk)->put($path, $this->csv($packetRows));
            $paths[] = $path;
        }

        $summary = $this->summary($selectedSubsites, $selectedServices, $sourceRows, $rows, count($selectedServices));
        $manifestPath = $basePath.'/manifest.json';
        $summaryPath = $basePath.'/summary.md';
        $paths[] = $manifestPath;
        $paths[] = $summaryPath;
        Storage::disk($disk)->put($manifestPath, (string) json_encode([
            'generated_at' => now()->toIso8601String(),
            'source_table' => self::SOURCE_TABLE,
            'read_only' => true,
            'content_fields_selected' => false,
            'summary' => $summary,
            'paths' => $paths,
            'warnings' => $warnings,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        Storage::disk($disk)->put($summaryPath, $this->markdown($summary, $warnings));

        return new LegacyCategoryReviewPacketResultDTO(
            disk: $disk,
            selectedSubsites: $selectedSubsites,
            selectedServices: $selectedServices,
            sourceRows: $summary['source_rows'],
            outputRows: $summary['output_rows'],
            packetCount: $summary['packet_count'],
            hiddenRows: $summary['hidden_rows'],
            linkRows: $summary['link_rows'],
            orphanRows: $summary['orphan_rows'],
            mappedRows: $summary['mapped_rows'],
            actionCounts: $summary['action_counts'],
            semanticCounts: $summary['semantic_counts'],
            subsiteCounts: $summary['subsite_counts'],
            serviceCounts: $summary['service_counts'],
            blockerCounts: $summary['blocker_counts'],
            paths: $paths,
            warnings: $warnings,
        );
    }

    /** @param array<int, string> $subsites @param array<int, int|string> $services @return array{array<int, string>, array<int, int>} */
    private function validatedSelection(array $subsites, array $services): array
    {
        $selectedSubsites = $subsites === [] ? ['root', 'admin'] : array_values(array_unique(array_map(static fn (string $value): string => strtolower(trim($value)), $subsites)));

        foreach ($selectedSubsites as $subsite) {
            if (! array_key_exists($subsite, self::SUBSITE_SERVICES)) {
                throw new InvalidArgumentException('Unsupported subsite ['.$subsite.']. Allowed values: root, admin.');
            }
        }

        $allowedServices = [];

        foreach ($selectedSubsites as $subsite) {
            $allowedServices = [...$allowedServices, ...self::SUBSITE_SERVICES[$subsite]];
        }

        if ($services === []) {
            $selectedServices = $allowedServices;
        } else {
            $selectedServices = [];

            foreach ($services as $service) {
                if (filter_var($service, FILTER_VALIDATE_INT) === false) {
                    throw new InvalidArgumentException('Service filter ['.(string) $service.'] must be an integer.');
                }

                $selectedServices[] = (int) $service;
            }

            $selectedServices = array_values(array_unique($selectedServices));
            $unsupported = array_values(array_diff($selectedServices, $allowedServices));

            if ($unsupported !== []) {
                throw new InvalidArgumentException('Out-of-scope service filter(s) for selected subsites: '.implode(', ', $unsupported).'.');
            }
        }

        sort($selectedServices);

        return [$selectedSubsites, $selectedServices];
    }

    /** @param array<int, int> $sourceIds @param array<int, string> $warnings @return array<int, object> */
    private function childEvidence(array $sourceIds, array &$warnings): array
    {
        if ($sourceIds === [] || ! $this->oldDatabase->schema()->hasTable('jx_items')) {
            if ($sourceIds !== [] && ! $this->oldDatabase->schema()->hasTable('jx_items')) {
                $warnings[] = 'Missing legacy child evidence table [jx_items].';
            }

            return [];
        }

        return $this->oldDatabase->table('jx_items')
            ->select('category_id')
            ->selectRaw('COUNT(*) as total_count')
            ->selectRaw('SUM(CASE WHEN is_visible = 1 THEN 1 ELSE 0 END) as visible_count')
            ->selectRaw("SUM(CASE WHEN photo IS NOT NULL AND TRIM(photo) <> '' THEN 1 ELSE 0 END) as photo_count")
            ->selectRaw("SUM(CASE WHEN ar_file IS NOT NULL AND TRIM(ar_file) <> '' THEN 1 ELSE 0 END) as ar_file_count")
            ->selectRaw("SUM(CASE WHEN en_file IS NOT NULL AND TRIM(en_file) <> '' THEN 1 ELSE 0 END) as en_file_count")
            ->whereIn('category_id', $sourceIds)
            ->groupBy('category_id')
            ->get()
            ->keyBy(static fn (object $row): int => (int) $row->category_id)
            ->all();
    }

    /** @param array<int, int> $sourceIds @return array<int, array<int, object>> */
    private function migrationMappings(array $sourceIds): array
    {
        if ($sourceIds === [] || ! Schema::hasTable('migration_logs')) {
            return [];
        }

        return DB::table('migration_logs')->where('source_table', self::SOURCE_TABLE)->where('status', 'success')
            ->whereIn('source_id', $sourceIds)->orderBy('id')->get(['source_id', 'target_table', 'target_id'])
            ->groupBy(static fn (object $row): int => (int) $row->source_id)
            ->map(static fn (Collection $rows): array => $rows->values()->all())->all();
    }

    /** @param array<int, int> $sourceIds @return array<int, array<int, object>> */
    private function newsMappings(array $sourceIds): array
    {
        if ($sourceIds === [] || ! Schema::hasTable('news_articles')) {
            return [];
        }

        return DB::table('news_articles')->where('legacy_source_table', self::SOURCE_TABLE)
            ->whereIn('legacy_source_id', $sourceIds)->orderBy('id')->get(['id', 'legacy_source_id', 'slug', 'status'])
            ->groupBy(static fn (object $row): int => (int) $row->legacy_source_id)
            ->map(static fn (Collection $rows): array => $rows->values()->all())->all();
    }

    /**
     * @param  array<int, true>  $allIds
     * @param  array<int, object>  $children
     * @param  array<int, array<int, object>>  $migrationMappings
     * @param  array<int, array<int, object>>  $newsMappings
     * @return array<string, mixed>
     */
    private function reviewRow(object $row, array $allIds, array $children, array $migrationMappings, array $newsMappings): array
    {
        $id = (int) $row->id;
        $service = (int) $row->service_type;
        $context = self::CONTEXTS[$service];
        $subsite = $this->subsiteForService($service);
        $parent = is_numeric($row->parent) ? (int) $row->parent : null;
        $hasParent = $parent !== null && $parent !== 0;
        $parentExists = ! $hasParent || isset($allIds[$parent]);
        $child = $children[$id] ?? null;
        $childTotal = (int) ($child->total_count ?? 0);
        $arLength = (int) ($row->ar_content_length ?? 0);
        $enLength = (int) ($row->en_content_length ?? 0);
        $arName = $this->text($row->ar_name);
        $enName = $this->text($row->en_name);
        $migrationRows = $migrationMappings[$id] ?? [];
        $newsRows = $newsMappings[$id] ?? [];
        $mapped = $migrationRows !== [] || $newsRows !== [];
        $blockers = [];

        if ((int) $row->is_visible !== 1) {
            $blockers[] = 'hidden_source';
        }
        if ($this->truthy($row->is_link)) {
            $blockers[] = 'external_link';
        }
        if ($arName === null) {
            $blockers[] = 'missing_ar_title';
        }
        if ($enName === null) {
            $blockers[] = 'missing_en_title';
        }
        if ($this->underConstruction($arName) || $this->underConstruction($enName)) {
            $blockers[] = 'under_construction_translation';
        }
        if ($arLength === 0 && $enLength === 0 && $childTotal === 0) {
            $blockers[] = 'empty_content_and_children';
        }
        if ($hasParent && ! $parentExists) {
            $blockers[] = 'orphan_parent';
        }
        if (! $this->validLegacyDate($this->text($row->start_date))) {
            $blockers[] = 'invalid_legacy_start_date';
        }
        if (! $this->validLegacyDate($this->text($row->end_date))) {
            $blockers[] = 'invalid_legacy_end_date';
        }
        if ($mapped) {
            $blockers[] = 'existing_target_mapping';
        }

        return [
            'source_table' => self::SOURCE_TABLE, 'source_id' => $id, 'subsite' => $subsite,
            'service_type' => $service, 'category_order' => is_numeric($row->category_order) ? (int) $row->category_order : null,
            'parent_id' => $parent, 'context_semantic' => $context['semantic'],
            'candidate_target_module' => $context['module'], 'recommended_action' => $context['action'],
            'confidence' => $context['confidence'], 'review_status' => $mapped ? 'mapped_reconciliation_review' : 'pending_editorial_review',
            'blockers' => implode('|', $blockers), 'approval_decision' => '', 'approved_target' => '',
            'ar_name' => $arName, 'en_name' => $enName, 'is_visible' => (int) $row->is_visible,
            'is_link' => $this->truthy($row->is_link) ? 1 : 0, 'url' => $this->text($row->url),
            'photo' => $this->text($row->photo), 'start_date' => $this->text($row->start_date),
            'end_date' => $this->text($row->end_date), 'ar_content_present' => $arLength > 0 ? 1 : 0,
            'ar_content_length' => $arLength, 'en_content_present' => $enLength > 0 ? 1 : 0,
            'en_content_length' => $enLength, 'child_total_count' => $childTotal,
            'child_visible_count' => (int) ($child->visible_count ?? 0), 'child_photo_count' => (int) ($child->photo_count ?? 0),
            'child_ar_file_count' => (int) ($child->ar_file_count ?? 0), 'child_en_file_count' => (int) ($child->en_file_count ?? 0),
            'has_parent' => $hasParent ? 1 : 0, 'parent_exists' => $hasParent ? ($parentExists ? 1 : 0) : '',
            'is_orphan' => $hasParent && ! $parentExists ? 1 : 0,
            'legacy_ar_url_candidate' => $this->legacyUrl($subsite, 1, $service, $id),
            'legacy_en_url_candidate' => $this->legacyUrl($subsite, 2, $service, $id),
            'migration_log_success_count' => count($migrationRows),
            'migration_log_target_tables' => $this->joinedValues($migrationRows, 'target_table'),
            'migration_log_target_ids' => $this->joinedValues($migrationRows, 'target_id'),
            'news_article_match_count' => count($newsRows), 'news_article_ids' => $this->joinedValues($newsRows, 'id'),
            'news_article_slugs' => $this->joinedValues($newsRows, 'slug'),
            'news_article_statuses' => $this->joinedValues($newsRows, 'status'), 'existing_target_mapping' => $mapped ? 1 : 0,
        ];
    }

    /** @param array<int, object> $sourceRows @param array<int, array<string, mixed>> $rows @return array<string, mixed> */
    private function summary(array $subsites, array $services, array $sourceRows, array $rows, int $packetCount): array
    {
        return [
            'selected_subsites' => $subsites, 'selected_services' => $services, 'source_rows' => count($sourceRows),
            'output_rows' => count($rows), 'packet_count' => $packetCount,
            'hidden_rows' => count(array_filter($rows, static fn (array $row): bool => $row['is_visible'] !== 1)),
            'link_rows' => count(array_filter($rows, static fn (array $row): bool => $row['is_link'] === 1)),
            'orphan_rows' => count(array_filter($rows, static fn (array $row): bool => $row['is_orphan'] === 1)),
            'mapped_rows' => count(array_filter($rows, static fn (array $row): bool => $row['existing_target_mapping'] === 1)),
            'action_counts' => $this->counts($rows, 'recommended_action'), 'semantic_counts' => $this->counts($rows, 'context_semantic'),
            'subsite_counts' => $this->counts($rows, 'subsite'), 'service_counts' => $this->counts($rows, 'service_type'),
            'blocker_counts' => $this->blockerCounts($rows),
        ];
    }

    /** @param array<string, mixed> $summary @param array<int, string> $warnings */
    private function markdown(array $summary, array $warnings): string
    {
        $lines = ['# Legacy Category Review Packets', '', '- Read only: yes', '- Source rows: '.$summary['source_rows'], '- Output rows: '.$summary['output_rows'], '- Packets: '.$summary['packet_count'], '- Hidden/link/orphan/mapped: '.$summary['hidden_rows'].'/'.$summary['link_rows'].'/'.$summary['orphan_rows'].'/'.$summary['mapped_rows'], '- Selected subsites: '.implode(', ', $summary['selected_subsites']), '- Selected services: '.implode(', ', $summary['selected_services'])];

        foreach ($warnings as $warning) {
            $lines[] = '- Warning: '.$warning;
        }

        return implode("\n", $lines)."\n";
    }

    /** @param array<int, array<string, mixed>> $rows @return array<string, int> */
    private function counts(array $rows, string $field): array
    {
        $counts = [];
        foreach ($rows as $row) {
            $counts[(string) $row[$field]] = ($counts[(string) $row[$field]] ?? 0) + 1;
        }
        ksort($counts);

        return $counts;
    }

    /** @param array<int, array<string, mixed>> $rows @return array<string, int> */
    private function blockerCounts(array $rows): array
    {
        $counts = [];
        foreach ($rows as $row) {
            foreach (array_filter(explode('|', (string) $row['blockers'])) as $blocker) {
                $counts[$blocker] = ($counts[$blocker] ?? 0) + 1;
            }
        }
        ksort($counts);

        return $counts;
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function csv(array $rows): string
    {
        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            return '';
        }
        fputcsv($stream, self::CSV_HEADERS);
        foreach ($rows as $row) {
            fputcsv($stream, array_map(static fn (string $header): mixed => $row[$header] ?? '', self::CSV_HEADERS));
        }
        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);

        return is_string($contents) ? $contents : '';
    }

    private function subsiteForService(int $service): string
    {
        return $service <= 10 ? 'root' : 'admin';
    }

    private function legacyUrl(string $subsite, int $language, int $service, int $id): string
    {
        $prefix = $subsite === 'root' ? '' : '/admin';

        return $prefix.'/index.php?page=show&ex=2&dir=items&lang='.$language.'&ser='.$service.'&cat_id='.$id;
    }

    /** @param array<int, object> $rows */
    private function joinedValues(array $rows, string $field): string
    {
        $values = [];
        foreach ($rows as $row) {
            if (isset($row->{$field}) && trim((string) $row->{$field}) !== '') {
                $values[] = (string) $row->{$field};
            }
        }

        return implode('|', array_values(array_unique($values)));
    }

    private function text(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function truthy(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1';
    }

    private function underConstruction(?string $value): bool
    {
        return $value !== null && strtolower(trim($value)) === 'under construction';
    }

    private function validLegacyDate(?string $value): bool
    {
        if ($value === null) {
            return true;
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:[ T].*)?$/', $value, $matches) !== 1) {
            return false;
        }

        return checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1]) && strtotime($value) !== false;
    }
}
