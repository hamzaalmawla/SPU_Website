<?php

declare(strict_types=1);

namespace App\Services\Legacy;

use App\Contracts\Legacy\LegacyPublicStaffReviewPacketServiceInterface;
use App\DTOs\Legacy\LegacyPublicStaffReviewPacketResultDTO;
use App\Support\LegacyImport\OldDatabaseConnection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class LegacyPublicStaffReviewPacketService implements LegacyPublicStaffReviewPacketServiceInterface
{
    private const SOURCE_TABLE = 'jx_councils';

    /** @var array<int, array{semantic: string, action: string, module: string, faculty: ?string, prefix: string}> */
    private const CONTEXTS = [
        1 => ['semantic' => 'university_board', 'action' => 'central_council_review', 'module' => 'councils', 'faculty' => null, 'prefix' => ''],
        2 => ['semantic' => 'university_council', 'action' => 'central_council_review', 'module' => 'councils', 'faculty' => null, 'prefix' => ''],
        3 => ['semantic' => 'medicine_leadership', 'action' => 'faculty_profile_review', 'module' => 'faculty_members', 'faculty' => 'medicine', 'prefix' => '/med'],
        4 => ['semantic' => 'medicine_staff', 'action' => 'faculty_profile_review', 'module' => 'faculty_members', 'faculty' => 'medicine', 'prefix' => '/med'],
        5 => ['semantic' => 'dentistry_leadership', 'action' => 'faculty_profile_review', 'module' => 'faculty_members', 'faculty' => 'dentistry', 'prefix' => '/dent'],
        6 => ['semantic' => 'dentistry_staff', 'action' => 'faculty_profile_review', 'module' => 'faculty_members', 'faculty' => 'dentistry', 'prefix' => '/dent'],
        7 => ['semantic' => 'pharmacy_leadership', 'action' => 'faculty_profile_review', 'module' => 'faculty_members', 'faculty' => 'pharmacy', 'prefix' => '/pharm'],
        8 => ['semantic' => 'pharmacy_staff', 'action' => 'faculty_profile_review', 'module' => 'faculty_members', 'faculty' => 'pharmacy', 'prefix' => '/pharm'],
        9 => ['semantic' => 'artificial_intelligence_leadership', 'action' => 'faculty_profile_review', 'module' => 'faculty_members', 'faculty' => 'ai-engineering', 'prefix' => '/info'],
        10 => ['semantic' => 'artificial_intelligence_staff', 'action' => 'faculty_profile_review', 'module' => 'faculty_members', 'faculty' => 'ai-engineering', 'prefix' => '/info'],
        11 => ['semantic' => 'petroleum_leadership', 'action' => 'faculty_profile_review', 'module' => 'faculty_members', 'faculty' => 'petroleum', 'prefix' => '/petrol'],
        12 => ['semantic' => 'petroleum_staff', 'action' => 'faculty_profile_review', 'module' => 'faculty_members', 'faculty' => 'petroleum', 'prefix' => '/petrol'],
        13 => ['semantic' => 'business_administration_leadership', 'action' => 'faculty_profile_review', 'module' => 'faculty_members', 'faculty' => 'business', 'prefix' => '/admin'],
        14 => ['semantic' => 'business_administration_staff', 'action' => 'faculty_profile_review', 'module' => 'faculty_members', 'faculty' => 'business', 'prefix' => '/admin'],
    ];

    /** @var list<string> */
    private const CSV_HEADERS = [
        'source_table', 'source_id', 'parent_id', 'service_type', 'council_order', 'context_semantic',
        'candidate_target_module', 'candidate_faculty_slug', 'recommended_action', 'confidence', 'review_status',
        'blockers', 'approval_decision', 'approved_target', 'ar_name', 'en_name', 'ar_position', 'en_position',
        'ar_specialization', 'en_specialization', 'is_visible', 'is_link', 'url', 'phone', 'mobile', 'email',
        'email_classification', 'normalized_valid_email', 'profile_url_candidate', 'photo_present', 'photo_legacy_path',
        'cv_present', 'cv_legacy_path', 'ar_cv_present', 'ar_cv_legacy_path', 'academic_rank', 'ar_content_length',
        'en_content_length', 'has_parent', 'parent_exists', 'is_orphan', 'legacy_ar_url_candidate',
        'legacy_en_url_candidate', 'migration_log_success_count', 'migration_log_target_tables',
        'migration_log_target_ids', 'existing_target_mapping', 'current_email_match_count', 'current_email_match_ids',
        'councils1_valid_email_match_count', 'duplicate_source_email_count', 'duplicate_source_ar_name_count',
        'duplicate_source_en_name_count',
    ];

    public function __construct(private readonly OldDatabaseConnection $oldDatabase) {}

    public function export(array $services = [], string $disk = 'local', string $directory = 'legacy-import-exports/public-staff-review-packets'): LegacyPublicStaffReviewPacketResultDTO
    {
        $selectedServices = $this->validatedServices($services);
        $directory = trim($directory, '/') ?: 'legacy-import-exports/public-staff-review-packets';
        $warnings = [];
        $sourceRows = [];
        $allRows = [];

        if (! $this->oldDatabase->schema()->hasTable(self::SOURCE_TABLE)) {
            $warnings[] = 'Missing legacy source table [jx_councils].';
        } else {
            $allRows = $this->sourceQuery()->orderBy('id')->get()->all();
            $sourceRows = $this->sourceQuery()->whereIn('service_type', $selectedServices)
                ->orderBy('service_type')->orderBy('council_order')->orderBy('id')->get()->all();
        }

        $allIds = [];
        $emailCounts = [];
        $arNameCounts = [];
        $enNameCounts = [];
        foreach ($allRows as $row) {
            $allIds[(int) $row->id] = true;
            $email = $this->normalizedValidEmail($row->email ?? null);
            $arName = $this->normalizedName($row->ar_name ?? null);
            $enName = $this->normalizedName($row->en_name ?? null);
            $this->incrementNonBlank($emailCounts, $email);
            $this->incrementNonBlank($arNameCounts, $arName);
            $this->incrementNonBlank($enNameCounts, $enName);
        }

        $sourceIds = array_map(static fn (object $row): int => (int) $row->id, $sourceRows);
        $mappings = $this->migrationMappings($sourceIds);
        $currentEmailMatches = $this->currentEmailMatches();
        $councils1EmailCounts = $this->councils1EmailCounts($warnings);
        $facultySlugs = $this->facultySlugs();
        $rows = [];
        foreach ($sourceRows as $sourceRow) {
            $rows[] = $this->reviewRow(
                $sourceRow, $allIds, $emailCounts, $arNameCounts, $enNameCounts,
                $mappings, $currentEmailMatches, $councils1EmailCounts, $facultySlugs,
            );
        }

        $stamp = now()->format('Ymd_His');
        $basePath = $directory.'/'.$stamp;
        $paths = [];
        foreach ($selectedServices as $service) {
            $packetRows = array_values(array_filter($rows, static fn (array $row): bool => $row['service_type'] === $service));
            $path = $basePath.'/'.sprintf('service_%02d_%s.csv', $service, self::CONTEXTS[$service]['semantic']);
            Storage::disk($disk)->put($path, $this->csv($packetRows));
            $paths[] = $path;
        }

        $summary = $this->summary($selectedServices, $rows);
        $manifestPath = $basePath.'/manifest.json';
        $summaryPath = $basePath.'/summary.md';
        $paths[] = $manifestPath;
        $paths[] = $summaryPath;
        Storage::disk($disk)->put($manifestPath, (string) json_encode([
            'generated_at' => now()->toIso8601String(),
            'source_table' => self::SOURCE_TABLE,
            'read_only' => true,
            'content_fields_selected' => false,
            'approval_state' => 'pending_editorial_review',
            'summary' => $summary,
            'paths' => $paths,
            'warnings' => $warnings,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        Storage::disk($disk)->put($summaryPath, $this->markdown($summary, $paths, $warnings));

        return new LegacyPublicStaffReviewPacketResultDTO(
            disk: $disk,
            selectedServices: $selectedServices,
            sourceRows: $summary['source_rows'],
            outputRows: $summary['output_rows'],
            packetCount: $summary['packet_count'],
            hiddenRows: $summary['hidden_rows'],
            linkRows: $summary['link_rows'],
            orphanRows: $summary['orphan_rows'],
            mappedRows: $summary['mapped_rows'],
            duplicateIdentityRows: $summary['duplicate_identity_rows'],
            councils1OverlapRows: $summary['councils1_overlap_rows'],
            facultyCandidateRows: $summary['faculty_candidate_rows'],
            centralRows: $summary['central_rows'],
            emailClassificationCounts: $summary['email_classification_counts'],
            semanticCounts: $summary['semantic_counts'],
            serviceCounts: $summary['service_counts'],
            facultyCounts: $summary['faculty_counts'],
            blockerCounts: $summary['blocker_counts'],
            paths: $paths,
            warnings: $warnings,
        );
    }

    private function sourceQuery(): Builder
    {
        $length = $this->oldDatabase->connection()->getDriverName() === 'sqlite' ? 'LENGTH' : 'CHAR_LENGTH';

        return $this->oldDatabase->table(self::SOURCE_TABLE)->select([
            'id', 'parent', 'service_type', 'council_order', 'ar_name', 'en_name', 'is_visible', 'is_link',
            'url', 'photo', 'ar_position', 'en_position', 'ar_specialization', 'en_specialization', 'phone',
            'mobile', 'email', 'cv', 'ar_cv', 'academic_rank',
        ])->selectRaw($length.'(ar_data) as ar_content_length')->selectRaw($length.'(en_data) as en_content_length');
    }

    /** @param array<int, int|string> $services @return array<int, int> */
    private function validatedServices(array $services): array
    {
        if ($services === []) {
            return array_keys(self::CONTEXTS);
        }

        $selected = [];
        foreach ($services as $service) {
            if (filter_var($service, FILTER_VALIDATE_INT) === false || ! isset(self::CONTEXTS[(int) $service])) {
                throw new InvalidArgumentException('Unsupported service filter ['.(string) $service.']. Allowed values: 1-14.');
            }
            $selected[] = (int) $service;
        }
        $selected = array_values(array_unique($selected));
        sort($selected);

        return $selected;
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

    /** @return array<string, array<int, int>> */
    private function currentEmailMatches(): array
    {
        if (! Schema::hasTable('faculty_members')) {
            return [];
        }

        $matches = [];
        foreach (DB::table('faculty_members')->whereNotNull('email')->orderBy('id')->get(['id', 'email']) as $row) {
            $email = $this->normalizedValidEmail($row->email);
            if ($email !== null) {
                $matches[$email][] = (int) $row->id;
            }
        }

        return $matches;
    }

    /** @param array<int, string> $warnings @return array<string, int> */
    private function councils1EmailCounts(array &$warnings): array
    {
        if (! $this->oldDatabase->schema()->hasTable('jx_councils1')) {
            $warnings[] = 'Missing cross-source identity evidence table [jx_councils1].';

            return [];
        }

        $counts = [];
        foreach ($this->oldDatabase->table('jx_councils1')->orderBy('id')->pluck('email') as $value) {
            $this->incrementNonBlank($counts, $this->normalizedValidEmail($value));
        }

        return $counts;
    }

    /** @return array<string, true> */
    private function facultySlugs(): array
    {
        if (! Schema::hasTable('faculties')) {
            return [];
        }

        return array_fill_keys(DB::table('faculties')->pluck('slug')->map(static fn (mixed $slug): string => (string) $slug)->all(), true);
    }

    /**
     * @param  array<int, true>  $allIds
     * @param  array<string, int>  $emailCounts
     * @param  array<string, int>  $arNameCounts
     * @param  array<string, int>  $enNameCounts
     * @param  array<int, array<int, object>>  $mappings
     * @param  array<string, array<int, int>>  $currentEmailMatches
     * @param  array<string, int>  $councils1EmailCounts
     * @param  array<string, true>  $facultySlugs
     * @return array<string, mixed>
     */
    private function reviewRow(object $row, array $allIds, array $emailCounts, array $arNameCounts, array $enNameCounts, array $mappings, array $currentEmailMatches, array $councils1EmailCounts, array $facultySlugs): array
    {
        $id = (int) $row->id;
        $service = (int) $row->service_type;
        $context = self::CONTEXTS[$service];
        $parent = is_numeric($row->parent) ? (int) $row->parent : null;
        $hasParent = $parent !== null && $parent !== 0;
        $parentExists = ! $hasParent || isset($allIds[$parent]);
        $arName = $this->text($row->ar_name);
        $enName = $this->text($row->en_name);
        $normalizedEmail = $this->normalizedValidEmail($row->email);
        $emailClass = $this->emailClassification($row->email);
        $arNameCount = ($normalized = $this->normalizedName($arName)) !== null ? ($arNameCounts[$normalized] ?? 0) : 0;
        $enNameCount = ($normalized = $this->normalizedName($enName)) !== null ? ($enNameCounts[$normalized] ?? 0) : 0;
        $emailCount = $normalizedEmail !== null ? ($emailCounts[$normalizedEmail] ?? 0) : 0;
        $mappingRows = $mappings[$id] ?? [];
        $mapped = $mappingRows !== [];
        $councils1Count = $normalizedEmail !== null ? ($councils1EmailCounts[$normalizedEmail] ?? 0) : 0;
        $blockers = [];

        if ((int) $row->is_visible !== 1) {
            $blockers[] = 'hidden_source';
        }
        if ($this->truthy($row->is_link)) {
            $blockers[] = 'external_link';
        }
        if ($arName === null) {
            $blockers[] = 'missing_ar_name';
        }
        if ($enName === null) {
            $blockers[] = 'missing_en_name';
        }
        if ($this->underConstruction($arName) || $this->underConstruction($enName)) {
            $blockers[] = 'under_construction_translation';
        }
        if ($emailClass === 'invalid_email') {
            $blockers[] = 'invalid_email';
        }
        if ($emailClass === 'url_in_email_field') {
            $blockers[] = 'url_in_email_field';
        }
        if ($emailCount > 1) {
            $blockers[] = 'duplicate_source_email';
        }
        if ($arNameCount > 1 || $enNameCount > 1) {
            $blockers[] = 'duplicate_source_name';
        }
        if ($councils1Count > 0) {
            $blockers[] = 'councils1_identity_overlap';
        }
        if ($normalizedEmail !== null && ($currentEmailMatches[$normalizedEmail] ?? []) !== []) {
            $blockers[] = 'current_email_conflict';
        }
        if ($hasParent && ! $parentExists) {
            $blockers[] = 'orphan_parent';
        }
        if ($context['faculty'] !== null && ! isset($facultySlugs[$context['faculty']])) {
            $blockers[] = 'missing_faculty_target';
        }
        if ($mapped) {
            $blockers[] = 'existing_target_mapping';
        }
        if ($service <= 2) {
            $blockers[] = 'central_council_requires_separate_target';
        }

        return [
            'source_table' => self::SOURCE_TABLE, 'source_id' => $id, 'parent_id' => $parent,
            'service_type' => $service, 'council_order' => is_numeric($row->council_order) ? (int) $row->council_order : null,
            'context_semantic' => $context['semantic'], 'candidate_target_module' => $context['module'],
            'candidate_faculty_slug' => $context['faculty'], 'recommended_action' => $context['action'],
            'confidence' => $mapped ? 'high' : ($service <= 2 ? 'low' : 'medium'),
            'review_status' => $mapped ? 'mapped_reconciliation_review' : 'pending_editorial_review',
            'blockers' => implode('|', $blockers), 'approval_decision' => '', 'approved_target' => '',
            'ar_name' => $arName, 'en_name' => $enName, 'ar_position' => $this->text($row->ar_position),
            'en_position' => $this->text($row->en_position), 'ar_specialization' => $this->text($row->ar_specialization),
            'en_specialization' => $this->text($row->en_specialization), 'is_visible' => (int) $row->is_visible,
            'is_link' => $this->truthy($row->is_link) ? 1 : 0, 'url' => $this->text($row->url),
            'phone' => $this->text($row->phone), 'mobile' => $this->text($row->mobile), 'email' => $this->text($row->email),
            'email_classification' => $emailClass, 'normalized_valid_email' => $normalizedEmail,
            'profile_url_candidate' => $emailClass === 'url_in_email_field' ? $this->text($row->email) : null,
            'photo_present' => $this->text($row->photo) !== null ? 1 : 0, 'photo_legacy_path' => $this->text($row->photo),
            'cv_present' => $this->text($row->cv) !== null ? 1 : 0, 'cv_legacy_path' => $this->text($row->cv),
            'ar_cv_present' => $this->text($row->ar_cv) !== null ? 1 : 0, 'ar_cv_legacy_path' => $this->text($row->ar_cv),
            'academic_rank' => is_numeric($row->academic_rank) ? (int) $row->academic_rank : null,
            'ar_content_length' => (int) ($row->ar_content_length ?? 0), 'en_content_length' => (int) ($row->en_content_length ?? 0),
            'has_parent' => $hasParent ? 1 : 0, 'parent_exists' => $hasParent ? ($parentExists ? 1 : 0) : '',
            'is_orphan' => $hasParent && ! $parentExists ? 1 : 0,
            'legacy_ar_url_candidate' => $this->legacyUrl($context['prefix'], 1, $service, $id),
            'legacy_en_url_candidate' => $this->legacyUrl($context['prefix'], 2, $service, $id),
            'migration_log_success_count' => count($mappingRows), 'migration_log_target_tables' => $this->joinedValues($mappingRows, 'target_table'),
            'migration_log_target_ids' => $this->joinedValues($mappingRows, 'target_id'), 'existing_target_mapping' => $mapped ? 1 : 0,
            'current_email_match_count' => count($currentEmailMatches[$normalizedEmail ?? ''] ?? []),
            'current_email_match_ids' => implode('|', $currentEmailMatches[$normalizedEmail ?? ''] ?? []),
            'councils1_valid_email_match_count' => $councils1Count, 'duplicate_source_email_count' => $emailCount,
            'duplicate_source_ar_name_count' => $arNameCount, 'duplicate_source_en_name_count' => $enNameCount,
        ];
    }

    /** @param array<int, int> $services @param array<int, array<string, mixed>> $rows @return array<string, mixed> */
    private function summary(array $services, array $rows): array
    {
        return [
            'selected_services' => $services, 'source_rows' => count($rows), 'output_rows' => count($rows),
            'packet_count' => count($services), 'hidden_rows' => $this->countRows($rows, 'is_visible', 1, true),
            'link_rows' => $this->countRows($rows, 'is_link', 1), 'orphan_rows' => $this->countRows($rows, 'is_orphan', 1),
            'mapped_rows' => $this->countRows($rows, 'existing_target_mapping', 1),
            'valid_email_rows' => $this->countRows($rows, 'email_classification', 'valid_email'),
            'invalid_email_rows' => $this->countRows($rows, 'email_classification', 'invalid_email'),
            'url_email_rows' => $this->countRows($rows, 'email_classification', 'url_in_email_field'),
            'duplicate_identity_rows' => count(array_filter($rows, static fn (array $row): bool => $row['duplicate_source_email_count'] > 1 || $row['duplicate_source_ar_name_count'] > 1 || $row['duplicate_source_en_name_count'] > 1)),
            'councils1_overlap_rows' => count(array_filter($rows, static fn (array $row): bool => $row['councils1_valid_email_match_count'] > 0)),
            'faculty_candidate_rows' => count(array_filter($rows, static fn (array $row): bool => $row['candidate_target_module'] === 'faculty_members')),
            'central_rows' => count(array_filter($rows, static fn (array $row): bool => $row['candidate_target_module'] === 'councils')),
            'email_classification_counts' => $this->counts($rows, 'email_classification'),
            'semantic_counts' => $this->counts($rows, 'context_semantic'), 'service_counts' => $this->counts($rows, 'service_type'),
            'faculty_counts' => $this->counts($rows, 'candidate_faculty_slug', '(central)'), 'blocker_counts' => $this->blockerCounts($rows),
        ];
    }

    /** @param array<string, mixed> $summary @param array<int, string> $paths @param array<int, string> $warnings */
    private function markdown(array $summary, array $paths, array $warnings): string
    {
        $lines = [
            '# Legacy Public Staff Review Packets', '', '- Private editorial evidence: yes', '- Read only: yes',
            '- Import ready/approved: never', '- Source/output/packets: '.$summary['source_rows'].'/'.$summary['output_rows'].'/'.$summary['packet_count'],
            '- Hidden/link/orphan/mapped: '.$summary['hidden_rows'].'/'.$summary['link_rows'].'/'.$summary['orphan_rows'].'/'.$summary['mapped_rows'],
            '- Valid/invalid/URL emails: '.$summary['valid_email_rows'].'/'.$summary['invalid_email_rows'].'/'.$summary['url_email_rows'],
            '- Duplicate identity/councils1 overlap: '.$summary['duplicate_identity_rows'].'/'.$summary['councils1_overlap_rows'],
            '- Faculty candidates/central rows: '.$summary['faculty_candidate_rows'].'/'.$summary['central_rows'],
            '- Selected services: '.implode(', ', $summary['selected_services']),
        ];
        foreach ($paths as $path) {
            $lines[] = '- Path: '.$path;
        }
        foreach ($warnings as $warning) {
            $lines[] = '- Warning: '.$warning;
        }

        return implode("\n", $lines)."\n";
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

    private function legacyUrl(string $prefix, int $language, int $service, int $id): string
    {
        return $prefix.'/index.php?dir=councils&page=show&service='.$service.'&cat_id='.$id.'&lang='.$language;
    }

    private function emailClassification(mixed $value): string
    {
        $value = $this->text($value);
        if ($value === null) {
            return 'blank';
        }
        if (filter_var($value, FILTER_VALIDATE_EMAIL) !== false) {
            return 'valid_email';
        }
        if (filter_var($value, FILTER_VALIDATE_URL) !== false && in_array(strtolower((string) parse_url($value, PHP_URL_SCHEME)), ['http', 'https'], true)) {
            return 'url_in_email_field';
        }

        return 'invalid_email';
    }

    private function normalizedValidEmail(mixed $value): ?string
    {
        $value = $this->text($value);

        return $value !== null && filter_var($value, FILTER_VALIDATE_EMAIL) !== false ? Str::lower($value) : null;
    }

    private function normalizedName(mixed $value): ?string
    {
        $value = $this->text($value);
        if ($value === null) {
            return null;
        }

        return Str::lower((string) preg_replace('/\s+/u', ' ', strip_tags($value)));
    }

    private function text(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }
        $value = trim(html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return $value !== '' ? $value : null;
    }

    private function truthy(mixed $value): bool
    {
        return in_array($value, [1, '1', true, 'true'], true);
    }

    private function underConstruction(?string $value): bool
    {
        return $value !== null && in_array(Str::lower(trim($value)), ['under construction', 'under construction...'], true);
    }

    /** @param array<string, int> $counts */
    private function incrementNonBlank(array &$counts, ?string $value): void
    {
        if ($value !== null) {
            $counts[$value] = ($counts[$value] ?? 0) + 1;
        }
    }

    /** @param array<int, object> $rows */
    private function joinedValues(array $rows, string $field): string
    {
        $values = [];
        foreach ($rows as $row) {
            if (isset($row->{$field})) {
                $values[] = (string) $row->{$field};
            }
        }

        return implode('|', array_values(array_unique($values)));
    }

    /** @param array<int, array<string, mixed>> $rows @return array<string, int> */
    private function counts(array $rows, string $field, string $blank = '(blank)'): array
    {
        $counts = [];
        foreach ($rows as $row) {
            $key = $row[$field] === null || $row[$field] === '' ? $blank : (string) $row[$field];
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
    private function countRows(array $rows, string $field, mixed $value, bool $inverse = false): int
    {
        return count(array_filter($rows, static fn (array $row): bool => $inverse ? $row[$field] !== $value : $row[$field] === $value));
    }
}
