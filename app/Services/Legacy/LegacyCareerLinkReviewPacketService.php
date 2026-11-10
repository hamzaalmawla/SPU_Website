<?php

declare(strict_types=1);

namespace App\Services\Legacy;

use App\Contracts\Legacy\LegacyCareerLinkReviewPacketServiceInterface;
use App\Contracts\Legacy\LegacyContentCleaningServiceInterface;
use App\DTOs\Legacy\LegacyCareerLinkReviewPacketResultDTO;
use App\Services\Legacy\Concerns\HandlesPrivateReviewPackets;
use App\Support\LegacyImport\OldDatabaseConnection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

final class LegacyCareerLinkReviewPacketService implements LegacyCareerLinkReviewPacketServiceInterface
{
    use HandlesPrivateReviewPackets;

    private const SOURCE_TABLE = 'jx_job_sites';

    /** @var list<string> */
    private const HEADERS = [
        'source_table', 'source_id', 'ar_name', 'en_name', 'url_classification', 'safe_url', 'photo_legacy_path',
        'is_visible', 'sort_order', 'ar_description', 'en_description', 'ar_content_length', 'en_content_length',
        'recommended_action', 'candidate_target', 'review_status', 'blockers', 'approval_decision', 'approved_target',
        'mapping_success_count', 'mapping_target_ids', 'existing_target_mapping',
    ];

    public function __construct(
        private readonly OldDatabaseConnection $oldDatabase,
        private readonly LegacyContentCleaningServiceInterface $cleaner,
    ) {}

    public function export(string $disk = 'local', string $directory = 'legacy-import-exports/career-link-review-packets'): LegacyCareerLinkReviewPacketResultDTO
    {
        $directory = trim($directory, '/') ?: 'legacy-import-exports/career-link-review-packets';
        $warnings = [];
        $sourceRows = collect();
        if (! $this->oldDatabase->schema()->hasTable(self::SOURCE_TABLE)) {
            $warnings[] = 'Missing legacy source table [jx_job_sites].';
        } else {
            $length = $this->oldDatabase->connection()->getDriverName() === 'sqlite' ? 'LENGTH' : 'CHAR_LENGTH';
            $sourceRows = $this->oldDatabase->table(self::SOURCE_TABLE)
                ->select(['id', 'ar_name', 'en_name', 'url', 'photo', 'is_visible', 'record_order', 'ar_data', 'en_data'])
                ->selectRaw($length.'(ar_data) as ar_content_length')->selectRaw($length.'(en_data) as en_content_length')
                ->orderBy('id')->get();
        }
        $mappings = $this->mappings($sourceRows->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all());
        $urlCounts = [];
        foreach ($sourceRows as $row) {
            $safe = $this->safeUrl($row->url);
            if ($safe !== null) {
                $key = mb_strtolower($safe);
                $urlCounts[$key] = ($urlCounts[$key] ?? 0) + 1;
            }
        }

        $rows = [];
        $blockerCounts = [];
        $candidateRows = 0;
        foreach ($sourceRows as $row) {
            $safe = $this->safeUrl($row->url);
            $arName = $this->plainText($this->cleaner, $row->ar_name);
            $enName = $this->plainText($this->cleaner, $row->en_name);
            $mapping = $mappings[(int) $row->id] ?? [];
            $blockers = [];
            if ($safe === null) {
                $blockers[] = 'invalid_or_unsafe_url';
            }
            if ((int) $row->is_visible !== 1) {
                $blockers[] = 'hidden_source';
            }
            if ($arName === null && $enName === null) {
                $blockers[] = 'missing_both_titles';
            }
            if ($safe !== null && ($urlCounts[mb_strtolower($safe)] ?? 0) > 1) {
                $blockers[] = 'duplicate_url';
            }
            if ($mapping !== []) {
                $blockers[] = 'existing_target_mapping';
            }
            $this->incrementReviewCounts($blockerCounts, $blockers);
            $candidateRows += $blockers === [] ? 1 : 0;
            $rows[] = [
                'source_table' => self::SOURCE_TABLE, 'source_id' => (int) $row->id, 'ar_name' => $arName, 'en_name' => $enName,
                'url_classification' => $safe === null ? 'invalid_or_unsafe' : 'external_http_https', 'safe_url' => $safe,
                'photo_legacy_path' => $this->scalarText($row->photo), 'is_visible' => (int) $row->is_visible,
                'sort_order' => is_numeric($row->record_order) ? max(0, (int) $row->record_order) : (int) $row->id,
                'ar_description' => $this->plainText($this->cleaner, $row->ar_data), 'en_description' => $this->plainText($this->cleaner, $row->en_data),
                'ar_content_length' => (int) ($row->ar_content_length ?? 0), 'en_content_length' => (int) ($row->en_content_length ?? 0),
                'recommended_action' => 'external_career_link_review', 'candidate_target' => 'career_links',
                'review_status' => $mapping !== [] ? 'mapped_reconciliation_review' : 'pending_editorial_review',
                'blockers' => implode('|', $blockers), 'approval_decision' => '', 'approved_target' => '',
                'mapping_success_count' => count($mapping), 'mapping_target_ids' => implode('|', array_column($mapping, 'target_id')),
                'existing_target_mapping' => $mapping !== [] ? 1 : 0,
            ];
        }
        ksort($blockerCounts);
        $base = $directory.'/'.now()->format('Ymd_His');
        $paths = [$base.'/career_link_candidates.csv', $base.'/manifest.json', $base.'/summary.md'];
        Storage::disk($disk)->put($paths[0], $this->reviewCsv(self::HEADERS, $rows));
        $summary = ['total_rows' => $sourceRows->count(), 'candidate_rows' => $candidateRows, 'blocker_counts' => $blockerCounts];
        Storage::disk($disk)->put($paths[1], (string) json_encode([
            'generated_at' => now()->toIso8601String(), 'source_table' => self::SOURCE_TABLE, 'private_evidence' => true,
            'read_only' => true, 'public_feature_complete' => false, 'media_imported' => false,
            'summary' => $summary, 'paths' => $paths, 'warnings' => $warnings,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $lines = ['# Legacy Career Link Review Packet', '', '- Private evidence: yes', '- Public feature completion: no',
            '- Total/candidate: '.$sourceRows->count().'/'.$candidateRows, '- Blockers: '.json_encode($blockerCounts, JSON_UNESCAPED_UNICODE)];
        foreach ($paths as $path) {
            $lines[] = '- Path: '.$path;
        }
        foreach ($warnings as $warning) {
            $lines[] = '- Warning: '.$warning;
        }
        Storage::disk($disk)->put($paths[2], implode("\n", $lines)."\n");

        return new LegacyCareerLinkReviewPacketResultDTO($disk, $sourceRows->count(), $candidateRows, $blockerCounts, $paths, $warnings);
    }

    private function safeUrl(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }
        $url = trim((string) $value);
        $scheme = mb_strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true) && filter_var($url, FILTER_VALIDATE_URL) !== false ? $url : null;
    }

    private function scalarText(mixed $value): ?string
    {
        return is_scalar($value) && trim((string) $value) !== '' ? trim((string) $value) : null;
    }

    /** @param list<int> $ids @return array<int, list<array{target_id: int}>> */
    private function mappings(array $ids): array
    {
        if ($ids === [] || ! Schema::hasTable('migration_logs')) {
            return [];
        }

        return DB::table('migration_logs')->where('source_table', self::SOURCE_TABLE)->where('status', 'success')->whereIn('source_id', $ids)
            ->get(['source_id', 'target_id'])->groupBy('source_id')->map(fn ($items): array => $items->map(fn (object $row): array => ['target_id' => (int) $row->target_id])->all())->all();
    }
}
