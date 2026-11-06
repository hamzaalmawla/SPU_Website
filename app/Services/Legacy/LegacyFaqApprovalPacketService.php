<?php

declare(strict_types=1);

namespace App\Services\Legacy;

use App\Contracts\Legacy\LegacyFaqApprovalPacketServiceInterface;
use App\DTOs\Legacy\LegacyFaqApprovalPacketResultDTO;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class LegacyFaqApprovalPacketService implements LegacyFaqApprovalPacketServiceInterface
{
    /** @var list<string> */
    private const REQUIRED_HEADERS = [
        'source_table', 'source_id', 'locale', 'legacy_lang', 'candidate_target', 'blockers',
        'question_sha256', 'answer_sha256', 'approval_decision', 'approved_target',
    ];

    /** @var list<string> */
    private const APPROVED_HEADERS = [
        'source_table', 'source_id', 'locale', 'legacy_lang', 'approval_decision', 'approved_target',
        'question_sha256', 'answer_sha256', 'approved_by', 'approval_basis',
    ];

    public function build(string $input, string $approvedBy, string $disk = 'local', string $directory = 'legacy-import-exports/faq-approval-packets'): LegacyFaqApprovalPacketResultDTO
    {
        $input = trim($input);
        $approvedBy = trim($approvedBy);
        if ($input === '' || ! Storage::disk($disk)->exists($input)) {
            throw new InvalidArgumentException('A valid private FAQ candidate CSV is required.');
        }
        if ($approvedBy === '') {
            throw new InvalidArgumentException('An approved-by identity is required.');
        }

        $payload = Storage::disk($disk)->get($input);
        [$headers, $rows] = $this->readCsv($payload);
        $idCounts = [];
        foreach ($rows as $row) {
            $idCounts[$row['source_id']] = ($idCounts[$row['source_id']] ?? 0) + 1;
        }

        $approved = [];
        $rejected = [];
        $localeCounts = [];
        $rejectionCounts = [];
        foreach ($rows as $row) {
            $reasons = array_values(array_filter(explode('|', $row['blockers'])));
            $locale = $row['locale'];
            $lang = ctype_digit($row['legacy_lang']) ? (int) $row['legacy_lang'] : 0;
            if ($row['source_table'] !== 'jx_faqs' || $row['candidate_target'] !== 'faqs') {
                $reasons[] = 'invalid_faq_context';
            }
            if (! in_array($locale, ['ar', 'en'], true) || ($locale === 'ar' ? 1 : 2) !== $lang) {
                $reasons[] = 'invalid_locale_mapping';
            }
            if (! ctype_digit($row['source_id']) || (int) $row['source_id'] < 1) {
                $reasons[] = 'invalid_source_id';
            }
            if (($idCounts[$row['source_id']] ?? 0) > 1) {
                $reasons[] = 'duplicate_source_id';
            }
            if (! preg_match('/^[a-f0-9]{64}$/', $row['question_sha256']) || ! preg_match('/^[a-f0-9]{64}$/', $row['answer_sha256'])) {
                $reasons[] = 'invalid_content_hash';
            }
            $reasons = array_values(array_unique($reasons));
            if ($reasons !== []) {
                foreach ($reasons as $reason) {
                    $rejectionCounts[$reason] = ($rejectionCounts[$reason] ?? 0) + 1;
                }
                $rejected[] = [
                    'source_table' => $row['source_table'], 'source_id' => $row['source_id'], 'locale' => $locale,
                    'legacy_lang' => $row['legacy_lang'], 'question_sha256' => $row['question_sha256'],
                    'answer_sha256' => $row['answer_sha256'], 'rejection_reasons' => implode('|', $reasons),
                ];

                continue;
            }

            $approved[] = [
                'source_table' => 'jx_faqs', 'source_id' => $row['source_id'], 'locale' => $locale,
                'legacy_lang' => $row['legacy_lang'], 'approval_decision' => 'import', 'approved_target' => 'faqs',
                'question_sha256' => $row['question_sha256'], 'answer_sha256' => $row['answer_sha256'],
                'approved_by' => $approvedBy, 'approval_basis' => 'visible_answered_unique_supported_locale_no_review_blockers_disabled_only',
            ];
            $localeCounts[$locale] = ($localeCounts[$locale] ?? 0) + 1;
        }
        ksort($localeCounts);
        ksort($rejectionCounts);

        $directory = trim($directory, '/') ?: 'legacy-import-exports/faq-approval-packets';
        $base = $this->uniqueBase($directory, $disk);
        $paths = [$base.'/approved_faqs.csv', $base.'/rejected_faqs.csv', $base.'/manifest.json'];
        Storage::disk($disk)->put($paths[0], $this->csv(self::APPROVED_HEADERS, $approved));
        Storage::disk($disk)->put($paths[1], $this->csv(['source_table', 'source_id', 'locale', 'legacy_lang', 'question_sha256', 'answer_sha256', 'rejection_reasons'], $rejected));
        Storage::disk($disk)->put($paths[2], (string) json_encode([
            'generated_at' => now()->toIso8601String(), 'private_evidence' => true, 'writes_content' => false,
            'approved_by' => $approvedBy, 'source_packet' => $input, 'source_packet_sha256' => hash('sha256', $payload),
            'approved_fields_exclude_content_and_submitter_pii' => true,
            'summary' => ['scanned_rows' => count($rows), 'approved_rows' => count($approved), 'rejected_rows' => count($rejected), 'locale_counts' => $localeCounts, 'rejection_counts' => $rejectionCounts],
            'paths' => $paths,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return new LegacyFaqApprovalPacketResultDTO(count($rows), count($approved), count($rejected), $localeCounts, $rejectionCounts, $paths);
    }

    /** @return array{list<string>, list<array<string, string>>} */
    private function readCsv(string $payload): array
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $payload);
        rewind($stream);
        $headers = fgetcsv($stream);
        if (! is_array($headers)) {
            throw new InvalidArgumentException('FAQ candidate CSV is empty.');
        }
        $headers = array_map(static fn (mixed $value): string => trim((string) $value), $headers);
        $headers[0] = ltrim($headers[0] ?? '', "\xEF\xBB\xBF");
        $missing = array_diff(self::REQUIRED_HEADERS, $headers);
        if ($missing !== [] || count($headers) !== count(array_unique($headers))) {
            throw new InvalidArgumentException('Invalid FAQ candidate headers.'.($missing !== [] ? ' Missing: '.implode(', ', $missing).'.' : ''));
        }
        $rows = [];
        while (($values = fgetcsv($stream)) !== false) {
            if ($values === [] || $values === [null]) {
                continue;
            }
            if (count($values) !== count($headers)) {
                throw new InvalidArgumentException('Malformed FAQ candidate row.');
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
}
