<?php

declare(strict_types=1);

namespace App\Services\Legacy;

use App\Contracts\Legacy\LegacyMembersReviewPacketServiceInterface;
use App\DTOs\Legacy\LegacyMembersReviewPacketResultDTO;
use App\Support\LegacyImport\OldDatabaseConnection;
use DateTimeImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

final class LegacyMembersReviewPacketService implements LegacyMembersReviewPacketServiceInterface
{
    private const CATEGORY_TABLE = 'jx_member_categories';

    private const ITEM_TABLE = 'jx_member_items';

    private const TARGET_POLICY = 'unresolved_product_decision';

    /** @var array<int, array{category_semantic: string, item_semantic: string, action: string, blocker: string}> */
    private const SERVICES = [
        1 => [
            'category_semantic' => 'member_research_output',
            'item_semantic' => 'member_research_attachment',
            'action' => 'research_reconciliation_review',
            'blocker' => 'service_1_requires_publication_proof',
        ],
        2 => [
            'category_semantic' => 'member_teaching_material',
            'item_semantic' => 'member_teaching_attachment',
            'action' => 'teaching_archive_review',
            'blocker' => 'service_2_not_research_content',
        ],
    ];

    /** @var list<string> */
    private const CATEGORY_HEADERS = [
        'source_table', 'source_id', 'service_type', 'context_semantic', 'recommended_action', 'candidate_target_policy',
        'review_status', 'blockers', 'approval_decision', 'approved_target', 'parent_owner_id',
        'ar_name', 'ar_name_present', 'ar_name_length', 'en_name', 'en_name_present', 'en_name_length',
        'ar_brief_present', 'ar_brief_length', 'en_brief_present', 'en_brief_length', 'ar_content_length', 'en_content_length',
        'is_visible', 'is_link', 'url', 'photo_legacy_path', 'photo_present', 'start_date', 'start_date_valid',
        'end_date', 'end_date_valid', 'member_category_order', 'legacy_ar_category_url_candidate', 'legacy_en_category_url_candidate',
        'child_item_total', 'child_item_visible', 'child_item_accepted', 'child_item_archive', 'child_item_photo',
        'child_item_ar_file', 'child_item_en_file',
        'councils_exists', 'councils_id', 'councils_service_type', 'councils_ar_name', 'councils_en_name',
        'councils_email', 'councils_is_visible', 'councils_mapping_success_count', 'councils_mapping_target_tables', 'councils_mapping_target_ids',
        'councils1_exists', 'councils1_id', 'councils1_service_type', 'councils1_ar_name', 'councils1_en_name',
        'councils1_email', 'councils1_is_visible', 'councils1_mapping_success_count', 'councils1_mapping_target_tables', 'councils1_mapping_target_ids',
        'owner_evidence_status', 'owner_mapping_success_count', 'owner_mapped',
        'category_mapping_success_count', 'category_mapping_target_tables', 'category_mapping_target_ids',
        'research_publication_mapping_success_count', 'research_publication_target_ids', 'existing_target_mapping',
    ];

    /** @var list<string> */
    private const ITEM_HEADERS = [
        'source_table', 'source_id', 'member_category_id', 'service_type', 'category_exists', 'category_service_type',
        'category_service_match', 'context_semantic', 'recommended_action', 'candidate_target_policy', 'review_status',
        'blockers', 'approval_decision', 'approved_target', 'ar_name_length', 'en_name_length', 'ar_brief_length',
        'en_brief_length', 'ar_description_length', 'en_description_length', 'is_visible', 'is_accepted', 'is_archive',
        'is_main', 'member_item_order', 'item_date', 'video_link', 'photo_legacy_path', 'photo_present',
        'photo_duplicate_path_count', 'large_photo_legacy_path', 'large_photo_present', 'large_photo_duplicate_path_count',
        'ar_file_legacy_path', 'ar_file_present', 'ar_file_duplicate_path_count', 'en_file_legacy_path', 'en_file_present',
        'en_file_duplicate_path_count', 'parent_category_mapping_success_count', 'parent_category_mapping_target_tables',
        'parent_category_mapping_target_ids', 'parent_category_existing_target_mapping', 'item_mapping_success_count',
        'item_mapping_target_tables', 'item_mapping_target_ids', 'existing_target_mapping', 'legacy_url_status', 'legacy_url',
    ];

    public function __construct(private readonly OldDatabaseConnection $oldDatabase) {}

    public function export(array $services = [], string $disk = 'local', string $directory = 'legacy-import-exports/members-review-packets'): LegacyMembersReviewPacketResultDTO
    {
        // Validation deliberately precedes every storage operation.
        $selectedServices = $this->validatedServices($services);
        $directory = trim($directory, '/') ?: 'legacy-import-exports/members-review-packets';
        $warnings = [];

        $allCategories = $this->rows(self::CATEGORY_TABLE, $this->categoryColumns(), $this->categoryLengthColumns(), $warnings);
        $allItems = $this->rows(self::ITEM_TABLE, $this->itemColumns(), $this->itemLengthColumns(), $warnings);
        $categories = array_values(array_filter($allCategories, fn (object $row): bool => in_array($this->integer($row, 'service_type'), $selectedServices, true)));
        $items = array_values(array_filter($allItems, fn (object $row): bool => in_array($this->integer($row, 'service_type'), $selectedServices, true)));

        $categoryById = $this->keyById($allCategories);
        $itemsByCategory = $this->groupItems($allItems);
        $pathCounts = $this->pathCounts($allItems);
        $ownerIds = array_values(array_unique(array_filter(array_map(fn (object $row): ?int => $this->integer($row, 'parent'), $categories))));
        $owners = [
            'jx_councils' => $this->ownerRows('jx_councils', $ownerIds, $warnings),
            'jx_councils1' => $this->ownerRows('jx_councils1', $ownerIds, $warnings),
        ];
        $mappingIds = [
            self::CATEGORY_TABLE => array_keys($categoryById),
            self::ITEM_TABLE => array_map(fn (object $row): int => (int) $row->id, $allItems),
            'jx_councils' => $ownerIds,
            'jx_councils1' => $ownerIds,
        ];
        $mappings = $this->mappings($mappingIds);

        $categoryRows = array_map(fn (object $row): array => $this->categoryReviewRow($row, $itemsByCategory, $owners, $mappings), $categories);
        $itemRows = array_map(fn (object $row): array => $this->itemReviewRow($row, $categoryById, $pathCounts, $mappings), $items);

        $basePath = $directory.'/'.now()->format('Ymd_His');
        $paths = [];
        foreach ($selectedServices as $service) {
            $categoryPath = $basePath.'/service_'.$service.'_categories.csv';
            $itemPath = $basePath.'/service_'.$service.'_items.csv';
            Storage::disk($disk)->put($categoryPath, $this->csv(self::CATEGORY_HEADERS, $this->forService($categoryRows, $service)));
            Storage::disk($disk)->put($itemPath, $this->csv(self::ITEM_HEADERS, $this->forService($itemRows, $service)));
            $paths[] = $categoryPath;
            $paths[] = $itemPath;
        }

        $summary = $this->summary($selectedServices, $categoryRows, $itemRows);
        $manifestPath = $basePath.'/manifest.json';
        $summaryPath = $basePath.'/summary.md';
        $paths[] = $manifestPath;
        $paths[] = $summaryPath;
        Storage::disk($disk)->put($manifestPath, (string) json_encode([
            'generated_at' => now()->toIso8601String(),
            'source_tables' => [self::CATEGORY_TABLE, self::ITEM_TABLE],
            'read_only' => true,
            'import_supported' => false,
            'redirects_supported' => false,
            'parent_semantics' => 'staff_owner_identity',
            'target_policy' => self::TARGET_POLICY,
            'summary' => $summary,
            'paths' => $paths,
            'warnings' => $warnings,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        Storage::disk($disk)->put($summaryPath, $this->markdown($summary, $paths, $warnings));

        return new LegacyMembersReviewPacketResultDTO(
            disk: $disk,
            selectedServices: $selectedServices,
            categorySourceRows: $summary['category_source_rows'],
            categoryOutputRows: $summary['category_output_rows'],
            itemSourceRows: $summary['item_source_rows'],
            itemOutputRows: $summary['item_output_rows'],
            packetCount: $summary['packet_count'],
            visibleRows: $summary['visible_rows'],
            hiddenRows: $summary['hidden_rows'],
            ownerStatusCounts: $summary['owner_status_counts'],
            ownerMappedRows: $summary['owner_mapped_rows'],
            ownerUnmappedRows: $summary['owner_unmapped_rows'],
            categoryMappedRows: $summary['category_mapped_rows'],
            itemMappedRows: $summary['item_mapped_rows'],
            categoriesWithItems: $summary['categories_with_items'],
            categoriesWithoutItems: $summary['categories_without_items'],
            orphanItems: $summary['orphan_items'],
            serviceMismatchItems: $summary['service_mismatch_items'],
            totalFilePathReferences: $summary['total_file_path_references'],
            duplicateFileRows: $summary['duplicate_file_rows'],
            semanticCounts: $summary['semantic_counts'],
            actionCounts: $summary['action_counts'],
            blockerCounts: $summary['blocker_counts'],
            serviceCounts: $summary['service_counts'],
            paths: $paths,
            warnings: $warnings,
        );
    }

    /** @param array<int, int|string> $services @return array<int, int> */
    private function validatedServices(array $services): array
    {
        if ($services === []) {
            return [1, 2];
        }

        $selected = [];
        foreach ($services as $service) {
            if (filter_var($service, FILTER_VALIDATE_INT) === false || ! isset(self::SERVICES[(int) $service])) {
                throw new InvalidArgumentException('Unsupported service filter ['.(string) $service.']. Allowed values: 1, 2.');
            }
            $selected[] = (int) $service;
        }
        $selected = array_values(array_unique($selected));
        sort($selected);

        return $selected;
    }

    /** @return list<string> */
    private function categoryColumns(): array
    {
        return ['id', 'parent', 'service_type', 'ar_name', 'en_name', 'is_visible', 'is_link', 'url', 'photo', 'start_date', 'end_date', 'member_category_order'];
    }

    /** @return array<string, string> */
    private function categoryLengthColumns(): array
    {
        return ['ar_name' => 'ar_name_length', 'en_name' => 'en_name_length', 'ar_brief' => 'ar_brief_length', 'en_brief' => 'en_brief_length', 'ar_data' => 'ar_content_length', 'en_data' => 'en_content_length'];
    }

    /** @return list<string> */
    private function itemColumns(): array
    {
        return ['id', 'member_category_id', 'service_type', 'is_visible', 'is_accepted', 'is_archive', 'is_main', 'member_item_order', 'post_date', 'video_link', 'photo', 'large_photo', 'ar_file', 'en_file'];
    }

    /** @return array<string, string> */
    private function itemLengthColumns(): array
    {
        return ['ar_name' => 'ar_name_length', 'en_name' => 'en_name_length', 'ar_brief' => 'ar_brief_length', 'en_brief' => 'en_brief_length', 'ar_description' => 'ar_description_length', 'en_description' => 'en_description_length'];
    }

    /**
     * Select only scalar evidence and SQL-computed lengths; large text never enters PHP.
     *
     * @param  list<string>  $columns
     * @param  array<string, string>  $lengthColumns
     * @param  array<int, string>  $warnings
     * @return array<int, object>
     */
    private function rows(string $table, array $columns, array $lengthColumns, array &$warnings): array
    {
        if (! $this->oldDatabase->schema()->hasTable($table)) {
            $warnings[] = 'Missing legacy source table ['.$table.'].';

            return [];
        }

        $available = array_fill_keys($this->oldDatabase->schema()->getColumnListing($table), true);
        $query = $this->oldDatabase->table($table);
        foreach ($columns as $column) {
            isset($available[$column]) ? $query->addSelect($column) : $query->selectRaw('NULL as '.$column);
        }
        $lengthFunction = $this->oldDatabase->connection()->getDriverName() === 'sqlite' ? 'LENGTH' : 'CHAR_LENGTH';
        foreach ($lengthColumns as $column => $alias) {
            isset($available[$column]) ? $query->selectRaw($lengthFunction.'('.$column.') as '.$alias) : $query->selectRaw('0 as '.$alias);
        }

        return $query->orderBy('service_type')->orderBy('id')->get()->all();
    }

    /** @param array<int, int> $ids @param array<int, string> $warnings @return array<int, object> */
    private function ownerRows(string $table, array $ids, array &$warnings): array
    {
        if (! $this->oldDatabase->schema()->hasTable($table)) {
            $warnings[] = 'Missing owner evidence table ['.$table.'].';

            return [];
        }
        if ($ids === []) {
            return [];
        }

        $available = array_fill_keys($this->oldDatabase->schema()->getColumnListing($table), true);
        $query = $this->oldDatabase->table($table);
        foreach (['id', 'service_type', 'ar_name', 'en_name', 'email', 'is_visible'] as $column) {
            isset($available[$column]) ? $query->addSelect($column) : $query->selectRaw('NULL as '.$column);
        }

        return $query->whereIn('id', $ids)->orderBy('id')->get()->keyBy('id')->all();
    }

    /** @param array<string, array<int, int>> $idsByTable @return array<string, array<int, array<int, object>>> */
    private function mappings(array $idsByTable): array
    {
        $result = [];
        if (! Schema::hasTable('migration_logs')) {
            return $result;
        }
        foreach ($idsByTable as $table => $ids) {
            if ($ids === []) {
                continue;
            }
            $result[$table] = DB::table('migration_logs')->where('source_table', $table)->where('status', 'success')
                ->whereIn('source_id', $ids)->orderBy('id')->get(['source_id', 'target_table', 'target_id'])
                ->groupBy(fn (object $row): int => (int) $row->source_id)
                ->map(fn (Collection $rows): array => $rows->values()->all())->all();
        }

        return $result;
    }

    /** @param array<int, object> $rows @return array<int, object> */
    private function keyById(array $rows): array
    {
        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row->id] = $row;
        }

        return $result;
    }

    /** @param array<int, object> $items @return array<int, array<int, object>> */
    private function groupItems(array $items): array
    {
        $result = [];
        foreach ($items as $item) {
            $parent = $this->integer($item, 'member_category_id');
            if ($parent !== null) {
                $result[$parent][] = $item;
            }
        }

        return $result;
    }

    /** @param array<int, object> $items @return array<string, int> */
    private function pathCounts(array $items): array
    {
        $counts = [];
        foreach ($items as $item) {
            foreach (['photo', 'large_photo', 'ar_file', 'en_file'] as $column) {
                $path = $this->text($item->{$column} ?? null);
                if ($path !== null) {
                    $counts[$path] = ($counts[$path] ?? 0) + 1;
                }
            }
        }

        return $counts;
    }

    /**
     * @param  array<int, array<int, object>>  $itemsByCategory
     * @param  array<string, array<int, object>>  $owners
     * @param  array<string, array<int, array<int, object>>>  $mappings
     * @return array<string, mixed>
     */
    private function categoryReviewRow(object $row, array $itemsByCategory, array $owners, array $mappings): array
    {
        $id = (int) $row->id;
        $service = (int) $row->service_type;
        $ownerId = $this->integer($row, 'parent');
        $councils = $ownerId !== null ? ($owners['jx_councils'][$ownerId] ?? null) : null;
        $councils1 = $ownerId !== null ? ($owners['jx_councils1'][$ownerId] ?? null) : null;
        $ownerStatus = $councils !== null && $councils1 !== null ? 'both_sources' : ($councils !== null ? 'councils_only' : ($councils1 !== null ? 'councils1_only' : 'missing'));
        $councilsMappings = $ownerId !== null ? ($mappings['jx_councils'][$ownerId] ?? []) : [];
        $councils1Mappings = $ownerId !== null ? ($mappings['jx_councils1'][$ownerId] ?? []) : [];
        $categoryMappings = $mappings[self::CATEGORY_TABLE][$id] ?? [];
        $publicationMappings = array_values(array_filter($categoryMappings, fn (object $mapping): bool => (string) $mapping->target_table === 'research_publications'));
        $children = $itemsByCategory[$id] ?? [];
        $arName = $this->text($row->ar_name ?? null);
        $enName = $this->text($row->en_name ?? null);
        $blockers = [];
        if (! $this->truthy($row->is_visible ?? null)) {
            $blockers[] = 'hidden_source';
        }
        if ($this->truthy($row->is_link ?? null)) {
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
        if (! $this->validDate($row->start_date ?? null, true)) {
            $blockers[] = 'invalid_start_date';
        }
        if (! $this->validDate($row->end_date ?? null, true)) {
            $blockers[] = 'invalid_end_date';
        }
        if ($ownerStatus === 'missing') {
            $blockers[] = 'owner_not_found';
        }
        if ($ownerStatus === 'both_sources') {
            $blockers[] = 'owner_source_ambiguous';
        }
        if ($ownerStatus !== 'missing' && $councilsMappings === [] && $councils1Mappings === []) {
            $blockers[] = 'owner_unmapped';
        }
        if ($categoryMappings !== []) {
            $blockers[] = 'existing_target_mapping';
        }
        $blockers[] = self::SERVICES[$service]['blocker'];

        return [
            'source_table' => self::CATEGORY_TABLE, 'source_id' => $id, 'service_type' => $service,
            'context_semantic' => self::SERVICES[$service]['category_semantic'], 'recommended_action' => self::SERVICES[$service]['action'],
            'candidate_target_policy' => self::TARGET_POLICY, 'review_status' => $categoryMappings !== [] ? 'mapped_reconciliation_review' : 'pending_product_decision',
            'blockers' => implode('|', $blockers), 'approval_decision' => '', 'approved_target' => '', 'parent_owner_id' => $ownerId,
            'ar_name' => $arName, 'ar_name_present' => $arName !== null ? 1 : 0, 'ar_name_length' => (int) ($row->ar_name_length ?? 0),
            'en_name' => $enName, 'en_name_present' => $enName !== null ? 1 : 0, 'en_name_length' => (int) ($row->en_name_length ?? 0),
            'ar_brief_present' => (int) ($row->ar_brief_length ?? 0) > 0 ? 1 : 0, 'ar_brief_length' => (int) ($row->ar_brief_length ?? 0),
            'en_brief_present' => (int) ($row->en_brief_length ?? 0) > 0 ? 1 : 0, 'en_brief_length' => (int) ($row->en_brief_length ?? 0),
            'ar_content_length' => (int) ($row->ar_content_length ?? 0), 'en_content_length' => (int) ($row->en_content_length ?? 0),
            'is_visible' => $this->truthy($row->is_visible ?? null) ? 1 : 0, 'is_link' => $this->truthy($row->is_link ?? null) ? 1 : 0,
            'url' => $this->text($row->url ?? null), 'photo_legacy_path' => $this->text($row->photo ?? null), 'photo_present' => $this->text($row->photo ?? null) !== null ? 1 : 0,
            'start_date' => $this->text($row->start_date ?? null), 'start_date_valid' => $this->validDate($row->start_date ?? null, true) ? 1 : 0,
            'end_date' => $this->text($row->end_date ?? null), 'end_date_valid' => $this->validDate($row->end_date ?? null, true) ? 1 : 0,
            'member_category_order' => $this->integer($row, 'member_category_order'),
            'legacy_ar_category_url_candidate' => $this->categoryUrl(1, $service, $id), 'legacy_en_category_url_candidate' => $this->categoryUrl(2, $service, $id),
            'child_item_total' => count($children), 'child_item_visible' => $this->truthyCount($children, 'is_visible'),
            'child_item_accepted' => $this->truthyCount($children, 'is_accepted'), 'child_item_archive' => $this->truthyCount($children, 'is_archive'),
            'child_item_photo' => $this->presentCount($children, 'photo'), 'child_item_ar_file' => $this->presentCount($children, 'ar_file'),
            'child_item_en_file' => $this->presentCount($children, 'en_file'),
            ...$this->ownerEvidence('councils', $councils, $councilsMappings), ...$this->ownerEvidence('councils1', $councils1, $councils1Mappings),
            'owner_evidence_status' => $ownerStatus, 'owner_mapping_success_count' => count($councilsMappings) + count($councils1Mappings),
            'owner_mapped' => $councilsMappings !== [] || $councils1Mappings !== [] ? 1 : 0,
            ...$this->mappingEvidence('category_mapping', $categoryMappings),
            'research_publication_mapping_success_count' => count($publicationMappings),
            'research_publication_target_ids' => $this->mappingValues($publicationMappings, 'target_id'),
            'existing_target_mapping' => $categoryMappings !== [] ? 1 : 0,
        ];
    }

    /**
     * @param  array<int, object>  $categories
     * @param  array<string, int>  $pathCounts
     * @param  array<string, array<int, array<int, object>>>  $mappings
     * @return array<string, mixed>
     */
    private function itemReviewRow(object $row, array $categories, array $pathCounts, array $mappings): array
    {
        $id = (int) $row->id;
        $service = (int) $row->service_type;
        $parentId = $this->integer($row, 'member_category_id');
        $category = $parentId !== null ? ($categories[$parentId] ?? null) : null;
        $categoryService = $category !== null ? $this->integer($category, 'service_type') : null;
        $categoryMatch = $category !== null && $categoryService === $service;
        $parentMappings = $parentId !== null ? ($mappings[self::CATEGORY_TABLE][$parentId] ?? []) : [];
        $itemMappings = $mappings[self::ITEM_TABLE][$id] ?? [];
        $paths = [];
        foreach (['photo', 'large_photo', 'ar_file', 'en_file'] as $column) {
            $paths[$column] = $this->text($row->{$column} ?? null);
        }
        $duplicate = count(array_filter($paths, fn (?string $path): bool => $path !== null && ($pathCounts[$path] ?? 0) > 1)) > 0;
        $descriptionLength = (int) ($row->ar_description_length ?? 0) + (int) ($row->en_description_length ?? 0);
        $blockers = [];
        if ($category === null) {
            $blockers[] = 'missing_parent_category';
        }
        if ($category !== null && ! $categoryMatch) {
            $blockers[] = 'parent_service_mismatch';
        }
        if (! $this->truthy($row->is_visible ?? null)) {
            $blockers[] = 'hidden_source';
        }
        if (! $this->truthy($row->is_accepted ?? null)) {
            $blockers[] = 'not_accepted';
        }
        if ($this->truthy($row->is_archive ?? null)) {
            $blockers[] = 'archived_source';
        }
        if (count(array_filter($paths)) === 0 && $descriptionLength === 0) {
            $blockers[] = 'no_file_or_description';
        }
        if ($duplicate) {
            $blockers[] = 'duplicate_file_path';
        }
        if ($category !== null && $parentMappings === []) {
            $blockers[] = 'parent_category_unmapped';
        }
        if ($itemMappings !== []) {
            $blockers[] = 'existing_target_mapping';
        }
        $blockers[] = self::SERVICES[$service]['blocker'];

        $result = [
            'source_table' => self::ITEM_TABLE, 'source_id' => $id, 'member_category_id' => $parentId, 'service_type' => $service,
            'category_exists' => $category !== null ? 1 : 0, 'category_service_type' => $categoryService, 'category_service_match' => $categoryMatch ? 1 : 0,
            'context_semantic' => self::SERVICES[$service]['item_semantic'], 'recommended_action' => self::SERVICES[$service]['action'],
            'candidate_target_policy' => self::TARGET_POLICY, 'review_status' => $itemMappings !== [] ? 'mapped_reconciliation_review' : 'pending_product_decision',
            'blockers' => implode('|', $blockers), 'approval_decision' => '', 'approved_target' => '',
            'ar_name_length' => (int) ($row->ar_name_length ?? 0), 'en_name_length' => (int) ($row->en_name_length ?? 0),
            'ar_brief_length' => (int) ($row->ar_brief_length ?? 0), 'en_brief_length' => (int) ($row->en_brief_length ?? 0),
            'ar_description_length' => (int) ($row->ar_description_length ?? 0), 'en_description_length' => (int) ($row->en_description_length ?? 0),
            'is_visible' => $this->truthy($row->is_visible ?? null) ? 1 : 0, 'is_accepted' => $this->truthy($row->is_accepted ?? null) ? 1 : 0,
            'is_archive' => $this->truthy($row->is_archive ?? null) ? 1 : 0, 'is_main' => $this->truthy($row->is_main ?? null) ? 1 : 0,
            'member_item_order' => $this->integer($row, 'member_item_order'), 'item_date' => $this->text($row->post_date ?? null),
            'video_link' => $this->text($row->video_link ?? null),
        ];
        foreach ($paths as $column => $path) {
            $result[$column.'_legacy_path'] = $path;
            $result[$column.'_present'] = $path !== null ? 1 : 0;
            $result[$column.'_duplicate_path_count'] = $path !== null ? ($pathCounts[$path] ?? 0) : 0;
        }

        return [
            ...$result, ...$this->mappingEvidence('parent_category_mapping', $parentMappings),
            'parent_category_existing_target_mapping' => $parentMappings !== [] ? 1 : 0,
            ...$this->mappingEvidence('item_mapping', $itemMappings), 'existing_target_mapping' => $itemMappings !== [] ? 1 : 0,
            'legacy_url_status' => 'needs_runtime_evidence', 'legacy_url' => '',
        ];
    }

    /** @param array<int, object> $mappings @return array<string, mixed> */
    private function ownerEvidence(string $prefix, ?object $owner, array $mappings): array
    {
        return [
            $prefix.'_exists' => $owner !== null ? 1 : 0, $prefix.'_id' => $owner?->id,
            $prefix.'_service_type' => $owner?->service_type, $prefix.'_ar_name' => $this->text($owner?->ar_name),
            $prefix.'_en_name' => $this->text($owner?->en_name), $prefix.'_email' => $this->text($owner?->email),
            $prefix.'_is_visible' => $owner !== null ? ($this->truthy($owner->is_visible ?? null) ? 1 : 0) : '',
            $prefix.'_mapping_success_count' => count($mappings), $prefix.'_mapping_target_tables' => $this->mappingValues($mappings, 'target_table'),
            $prefix.'_mapping_target_ids' => $this->mappingValues($mappings, 'target_id'),
        ];
    }

    /** @param array<int, object> $mappings @return array<string, mixed> */
    private function mappingEvidence(string $prefix, array $mappings): array
    {
        return [
            $prefix.'_success_count' => count($mappings), $prefix.'_target_tables' => $this->mappingValues($mappings, 'target_table'),
            $prefix.'_target_ids' => $this->mappingValues($mappings, 'target_id'),
        ];
    }

    /** @param array<int, object> $mappings */
    private function mappingValues(array $mappings, string $field): string
    {
        $values = array_map(fn (object $mapping): string => (string) $mapping->{$field}, $mappings);

        return implode('|', array_values(array_unique($values)));
    }

    /** @param array<int, array<string, mixed>> $rows @return array<int, array<string, mixed>> */
    private function forService(array $rows, int $service): array
    {
        return array_values(array_filter($rows, fn (array $row): bool => $row['service_type'] === $service));
    }

    /** @param array<int, int> $services @param array<int, array<string, mixed>> $categories @param array<int, array<string, mixed>> $items @return array<string, mixed> */
    private function summary(array $services, array $categories, array $items): array
    {
        $all = [...$categories, ...$items];

        return [
            'selected_services' => $services, 'category_source_rows' => count($categories), 'category_output_rows' => count($categories),
            'item_source_rows' => count($items), 'item_output_rows' => count($items), 'packet_count' => count($services) * 2,
            'visible_rows' => $this->valueCount($all, 'is_visible', 1), 'hidden_rows' => $this->valueCount($all, 'is_visible', 0),
            'owner_status_counts' => $this->counts($categories, 'owner_evidence_status'),
            'owner_mapped_rows' => $this->valueCount($categories, 'owner_mapped', 1),
            'owner_unmapped_rows' => $this->valueCount($categories, 'owner_mapped', 0),
            'category_mapped_rows' => $this->valueCount($categories, 'existing_target_mapping', 1),
            'item_mapped_rows' => $this->valueCount($items, 'existing_target_mapping', 1),
            'categories_with_items' => count(array_filter($categories, fn (array $row): bool => $row['child_item_total'] > 0)),
            'categories_without_items' => $this->valueCount($categories, 'child_item_total', 0),
            'orphan_items' => $this->valueCount($items, 'category_exists', 0),
            'service_mismatch_items' => count(array_filter($items, fn (array $row): bool => $row['category_exists'] === 1 && $row['category_service_match'] === 0)),
            'total_file_path_references' => array_sum(array_map(fn (array $row): int => $row['photo_present'] + $row['large_photo_present'] + $row['ar_file_present'] + $row['en_file_present'], $items)),
            'duplicate_file_rows' => count(array_filter($items, fn (array $row): bool => str_contains($row['blockers'], 'duplicate_file_path'))),
            'semantic_counts' => $this->counts($all, 'context_semantic'), 'action_counts' => $this->counts($all, 'recommended_action'),
            'blocker_counts' => $this->blockerCounts($all), 'service_counts' => $this->counts($all, 'service_type'),
        ];
    }

    /** @param array<int, array<string, mixed>> $rows @return array<string, int> */
    private function counts(array $rows, string $field): array
    {
        $counts = [];
        foreach ($rows as $row) {
            $key = (string) $row[$field];
            $counts[$key] = ($counts[$key] ?? 0) + 1;
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
    private function valueCount(array $rows, string $field, mixed $value): int
    {
        return count(array_filter($rows, fn (array $row): bool => ($row[$field] ?? null) === $value));
    }

    /** @param list<string> $headers @param array<int, array<string, mixed>> $rows */
    private function csv(array $headers, array $rows): string
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
        $contents = stream_get_contents($stream);
        fclose($stream);

        return is_string($contents) ? $contents : '';
    }

    /** @param array<string, mixed> $summary @param array<int, string> $paths @param array<int, string> $warnings */
    private function markdown(array $summary, array $paths, array $warnings): string
    {
        $lines = [
            '# Legacy /members/ Reconciliation Evidence', '', '- Read only: yes', '- Import supported: no', '- Redirects supported: no',
            '- Parent semantics: staff owner identity (not hierarchy)', '- Target policy: '.self::TARGET_POLICY,
            '- Selected services: '.implode(', ', $summary['selected_services']),
            '- Category source/output: '.$summary['category_source_rows'].'/'.$summary['category_output_rows'],
            '- Item source/output: '.$summary['item_source_rows'].'/'.$summary['item_output_rows'],
            '- Packets: '.$summary['packet_count'], '- Visible/hidden rows: '.$summary['visible_rows'].'/'.$summary['hidden_rows'],
            '- Owner status counts: '.json_encode($summary['owner_status_counts'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            '- Owner mapped/unmapped: '.$summary['owner_mapped_rows'].'/'.$summary['owner_unmapped_rows'],
            '- Category/item mapped: '.$summary['category_mapped_rows'].'/'.$summary['item_mapped_rows'],
            '- Categories with/without items: '.$summary['categories_with_items'].'/'.$summary['categories_without_items'],
            '- Orphan/service mismatch items: '.$summary['orphan_items'].'/'.$summary['service_mismatch_items'],
            '- File references/duplicate rows: '.$summary['total_file_path_references'].'/'.$summary['duplicate_file_rows'],
            '- Semantic counts: '.json_encode($summary['semantic_counts'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            '- Action counts: '.json_encode($summary['action_counts'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            '- Blocker counts: '.json_encode($summary['blocker_counts'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            '- Service counts: '.json_encode($summary['service_counts'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
        foreach ($paths as $path) {
            $lines[] = '- Path: '.$path;
        }
        foreach ($warnings as $warning) {
            $lines[] = '- Warning: '.$warning;
        }

        return implode("\n", $lines)."\n";
    }

    private function categoryUrl(int $language, int $service, int $id): string
    {
        return '/members/index.php?page=show&ex=2&dir=items&lang='.$language.'&ser='.$service.'&cat_id='.$id;
    }

    private function text(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function integer(object $row, string $field): ?int
    {
        $value = $row->{$field} ?? null;

        return is_numeric($value) ? (int) $value : null;
    }

    private function truthy(mixed $value): bool
    {
        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }

    private function underConstruction(?string $value): bool
    {
        if ($value === null) {
            return false;
        }
        $value = mb_strtolower($value);

        return str_contains($value, 'under construction') || str_contains($value, 'قيد الإنشاء') || str_contains($value, 'قيد الانشاء');
    }

    private function validDate(mixed $value, bool $blankIsValid = false): bool
    {
        $value = $this->text($value);
        if ($value === null) {
            return $blankIsValid;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }

    /** @param array<int, object> $rows */
    private function truthyCount(array $rows, string $field): int
    {
        return count(array_filter($rows, fn (object $row): bool => $this->truthy($row->{$field} ?? null)));
    }

    /** @param array<int, object> $rows */
    private function presentCount(array $rows, string $field): int
    {
        return count(array_filter($rows, fn (object $row): bool => $this->text($row->{$field} ?? null) !== null));
    }
}
