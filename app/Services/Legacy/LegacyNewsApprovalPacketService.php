<?php

declare(strict_types=1);

namespace App\Services\Legacy;

use App\Contracts\Legacy\LegacyNewsApprovalPacketServiceInterface;
use App\DTOs\Legacy\LegacyNewsApprovalPacketResultDTO;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class LegacyNewsApprovalPacketService implements LegacyNewsApprovalPacketServiceInterface
{
    /** @var list<string> */
    private const REQUIRED_HEADERS = [
        'source_table', 'source_id', 'subsite', 'service_type', 'context_semantic', 'recommended_action',
        'blockers', 'approval_decision', 'approved_target', 'ar_name', 'en_name', 'is_visible', 'is_link',
        'ar_content_length', 'en_content_length', 'child_total_count', 'is_orphan', 'existing_target_mapping',
    ];

    /** @var list<string> */
    private const HARD_BLOCKERS = [
        'hidden_source', 'external_link', 'under_construction_translation', 'empty_content_and_children',
        'orphan_parent', 'existing_target_mapping',
    ];

    public function build(
        array $inputs,
        string $approvedBy,
        string $disk = 'local',
        string $directory = 'legacy-import-exports/news-approval-packets',
        bool $allowArabicFallback = false,
    ): LegacyNewsApprovalPacketResultDTO {
        $approvedBy = trim($approvedBy);

        if ($approvedBy === '' || mb_strlen($approvedBy) > 255) {
            throw new InvalidArgumentException('A concise approved-by identity is required.');
        }

        $inputs = array_values(array_unique(array_filter(array_map('trim', $inputs))));

        if ($inputs === []) {
            throw new InvalidArgumentException('At least one category review packet CSV is required.');
        }

        $rows = [];
        $headers = null;
        $checksums = [];

        foreach ($inputs as $input) {
            if (! Storage::disk($disk)->exists($input)) {
                throw new InvalidArgumentException('Category review packet ['.$input.'] was not found on disk ['.$disk.'].');
            }

            $payload = Storage::disk($disk)->get($input);
            [$inputHeaders, $inputRows] = $this->csvRows($payload);
            $headers ??= $inputHeaders;

            if ($headers !== $inputHeaders) {
                throw new InvalidArgumentException('Category review packets must have identical headers.');
            }

            $rows = [...$rows, ...$inputRows];
            $checksums[$input] = hash('sha256', $payload);
        }

        $sourceCounts = [];
        $titleCounts = [];

        foreach ($rows as $row) {
            $sourceKey = $row['source_table'].':'.$row['source_id'];
            $sourceCounts[$sourceKey] = ($sourceCounts[$sourceKey] ?? 0) + 1;

            if ($this->baseReasons($row, $allowArabicFallback) === []) {
                foreach (['ar_name', 'en_name'] as $titleField) {
                    $title = $this->normalizedTitle($row[$titleField]);

                    if ($title !== null) {
                        $key = $row['service_type'].'|'.$titleField.'|'.$title;
                        $titleCounts[$key] = ($titleCounts[$key] ?? 0) + 1;
                    }
                }
            }
        }

        $approved = [];
        $rejected = [];
        $rejectionCounts = [];
        $serviceCounts = [];

        foreach ($rows as $row) {
            $reasons = $this->baseReasons($row, $allowArabicFallback);
            $sourceKey = $row['source_table'].':'.$row['source_id'];

            if (($sourceCounts[$sourceKey] ?? 0) > 1) {
                $reasons[] = 'duplicate_source_id';
            }

            foreach (['ar_name', 'en_name'] as $titleField) {
                $title = $this->normalizedTitle($row[$titleField]);

                if ($title !== null && ($titleCounts[$row['service_type'].'|'.$titleField.'|'.$title] ?? 0) > 1) {
                    $reasons[] = 'duplicate_'.$titleField;
                }
            }

            $reasons = array_values(array_unique($reasons));

            if ($reasons !== []) {
                foreach ($reasons as $reason) {
                    $rejectionCounts[$reason] = ($rejectionCounts[$reason] ?? 0) + 1;
                }

                $row['safe_review_reasons'] = implode('|', $reasons);
                $rejected[] = $row;

                continue;
            }

            $row['approval_decision'] = 'import';
            $row['approved_target'] = 'news';
            $row['approved_by'] = $approvedBy;
            $row['approval_basis'] = $allowArabicFallback
                ? 'visible_supported_non_link_non_duplicate_disabled_draft_arabic_fallback_approved'
                : 'visible_supported_non_link_non_placeholder_non_duplicate_disabled_draft';
            $approved[] = $row;
            $service = (int) $row['service_type'];
            $serviceCounts[$service] = ($serviceCounts[$service] ?? 0) + 1;
        }

        ksort($serviceCounts);
        ksort($rejectionCounts);
        $directory = trim($directory, '/') ?: 'legacy-import-exports/news-approval-packets';
        $base = $this->uniqueBase($directory, $disk);
        $paths = [$base.'/approved_news.csv', $base.'/rejected_news.csv', $base.'/manifest.json'];
        $approvedHeaders = [...$headers, 'approved_by', 'approval_basis'];
        $rejectedHeaders = [...$headers, 'safe_review_reasons'];

        Storage::disk($disk)->put($paths[0], $this->csv($approvedHeaders, $approved));
        Storage::disk($disk)->put($paths[1], $this->csv($rejectedHeaders, $rejected));
        Storage::disk($disk)->put($paths[2], (string) json_encode([
            'generated_at' => now()->toIso8601String(),
            'private_evidence' => true,
            'writes_content' => false,
            'approved_by' => $approvedBy,
            'allow_arabic_fallback' => $allowArabicFallback,
            'source_packet_sha256' => $checksums,
            'criteria' => [
                'root services 3 and 4 only', 'visible source', 'not an external link',
                'at least one non-placeholder AR/EN title', 'content or child evidence present',
                $allowArabicFallback
                    ? 'English Under Construction placeholders are omitted and Arabic source content is the approved display fallback'
                    : 'placeholder translations are rejected',
                'no orphan or existing mapping', 'no duplicate source or same-service localized title',
                'invalid legacy dates may be normalized to null because imports remain disabled drafts',
            ],
            'summary' => [
                'scanned_rows' => count($rows), 'approved_rows' => count($approved), 'rejected_rows' => count($rejected),
                'service_counts' => $serviceCounts, 'rejection_counts' => $rejectionCounts,
            ],
            'paths' => $paths,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return new LegacyNewsApprovalPacketResultDTO(
            disk: $disk,
            scannedRows: count($rows),
            approvedRows: count($approved),
            rejectedRows: count($rejected),
            serviceCounts: $serviceCounts,
            rejectionCounts: $rejectionCounts,
            paths: $paths,
        );
    }

    /** @param array<string, string> $row @return list<string> */
    private function baseReasons(array $row, bool $allowArabicFallback): array
    {
        $reasons = [];

        if ($row['source_table'] !== 'jx_categories' || $row['subsite'] !== 'root'
            || ! ctype_digit($row['source_id']) || (int) $row['source_id'] < 1
            || ! in_array($row['service_type'], ['3', '4'], true)) {
            $reasons[] = 'invalid_news_context';
        }

        if ($row['is_visible'] !== '1') {
            $reasons[] = 'hidden_source';
        }

        if ($row['is_link'] === '1') {
            $reasons[] = 'external_link';
        }

        if ($this->normalizedTitle($row['ar_name']) === null && $this->normalizedTitle($row['en_name']) === null) {
            $reasons[] = 'missing_supported_title';
        }

        if ((int) $row['ar_content_length'] < 1 && (int) $row['en_content_length'] < 1 && (int) $row['child_total_count'] < 1) {
            $reasons[] = 'empty_content_and_children';
        }

        if ($row['is_orphan'] === '1') {
            $reasons[] = 'orphan_parent';
        }

        if ($row['existing_target_mapping'] === '1') {
            $reasons[] = 'existing_target_mapping';
        }

        $packetBlockers = array_filter(explode('|', $row['blockers']));

        foreach (self::HARD_BLOCKERS as $blocker) {
            if ($blocker === 'under_construction_translation' && $allowArabicFallback && $this->normalizedTitle($row['ar_name']) !== null) {
                continue;
            }
            if (in_array($blocker, $packetBlockers, true)) {
                $reasons[] = $blocker;
            }
        }

        return array_values(array_unique($reasons));
    }

    private function normalizedTitle(string $value): ?string
    {
        $value = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $value) ?? $value));

        if ($value === '' || $value === 'under construction') {
            return null;
        }

        return $value;
    }

    private function uniqueBase(string $directory, string $disk): string
    {
        $base = $directory.'/'.now()->format('Ymd_His').'_'.Str::lower(Str::random(8));
        $candidate = $base;
        $suffix = 1;
        while (Storage::disk($disk)->exists($candidate.'/manifest.json')) {
            $candidate = $base.'_'.sprintf('%02d', $suffix++);
        }

        return $candidate;
    }

    /** @return array{0: list<string>, 1: list<array<string, string>>} */
    private function csvRows(string $payload): array
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $payload);
        rewind($stream);
        $headers = fgetcsv($stream);

        if (! is_array($headers)) {
            fclose($stream);
            throw new InvalidArgumentException('Category review packet is empty.');
        }

        $headers = array_map(static fn (mixed $header): string => trim((string) $header), $headers);
        $headers[0] = ltrim($headers[0] ?? '', "\xEF\xBB\xBF");
        $missing = array_diff(self::REQUIRED_HEADERS, $headers);

        if ($missing !== [] || count($headers) !== count(array_unique($headers))) {
            fclose($stream);
            throw new InvalidArgumentException('Invalid category review packet headers.'.($missing !== [] ? ' Missing: '.implode(', ', $missing).'.' : ''));
        }

        $rows = [];

        while (($values = fgetcsv($stream)) !== false) {
            if ($values === [] || $values === [null]) {
                continue;
            }

            if (count($values) !== count($headers)) {
                fclose($stream);
                throw new InvalidArgumentException('Malformed category review packet row.');
            }

            $rows[] = array_combine($headers, array_map(static fn (mixed $value): string => trim((string) $value), $values));
        }

        fclose($stream);

        return [$headers, $rows];
    }

    /** @param list<string> $headers @param list<array<string, string>> $rows */
    private function csv(array $headers, array $rows): string
    {
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, $headers);

        foreach ($rows as $row) {
            fputcsv($stream, array_map(static fn (string $header): string => $row[$header] ?? '', $headers));
        }

        rewind($stream);
        $payload = stream_get_contents($stream);
        fclose($stream);

        return is_string($payload) ? $payload : '';
    }
}
