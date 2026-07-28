<?php

declare(strict_types=1);

namespace App\Services\Legacy;

use App\Contracts\Legacy\LegacyContentCleaningServiceInterface;
use App\Contracts\Legacy\LegacyFaqReviewPacketServiceInterface;
use App\DTOs\Legacy\LegacyFaqReviewPacketResultDTO;
use App\Services\Legacy\Concerns\HandlesPrivateReviewPackets;
use App\Support\LegacyImport\OldDatabaseConnection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

final class LegacyFaqReviewPacketService implements LegacyFaqReviewPacketServiceInterface
{
    use HandlesPrivateReviewPackets;

    private const SOURCE_TABLE = 'jx_faqs';

    private const PII_FIELDS = ['first_name', 'last_name', 'email', 'country', 'phone'];

    /** @var list<string> */
    private const CANDIDATE_HEADERS = [
        'source_table', 'source_id', 'locale', 'legacy_lang', 'recommended_action', 'candidate_target',
        'review_status', 'blockers', 'approval_decision', 'approved_target', 'question', 'answer', 'subject',
        'sort_order', 'post_date', 'is_visible', 'question_sha256', 'answer_sha256', 'question_length',
        'answer_length', 'source_question_length', 'source_answer_length', 'mapping_success_count',
        'mapping_target_ids', 'existing_target_mapping',
    ];

    /** @var list<string> */
    private const BACKLOG_HEADERS = [
        'source_table', 'source_id', 'legacy_lang', 'locale', 'sort_order', 'post_date', 'is_visible',
        'source_question_length', 'source_answer_length', 'disposition_reasons', 'existing_target_mapping',
        'mapping_success_count', 'mapping_target_ids',
    ];

    public function __construct(
        private readonly OldDatabaseConnection $oldDatabase,
        private readonly LegacyContentCleaningServiceInterface $cleaner,
    ) {}

    public function export(string $disk = 'local', string $directory = 'legacy-import-exports/faq-review-packets'): LegacyFaqReviewPacketResultDTO
    {
        $directory = trim($directory, '/') ?: 'legacy-import-exports/faq-review-packets';
        $warnings = [];
        $metadata = collect();
        if (! $this->oldDatabase->schema()->hasTable(self::SOURCE_TABLE)) {
            $warnings[] = 'Missing legacy source table [jx_faqs].';
        } else {
            $length = $this->oldDatabase->connection()->getDriverName() === 'sqlite' ? 'LENGTH' : 'CHAR_LENGTH';
            $metadata = $this->oldDatabase->table(self::SOURCE_TABLE)
                ->select(['id', 'subject', 'faq_order', 'post_date', 'is_visible', 'lang'])
                ->selectRaw($length.'(question) as question_length')
                ->selectRaw($length.'(answer) as answer_length')
                ->orderBy('id')->get();
        }

        $ids = $metadata->filter(fn (object $row): bool => $this->locale($row->lang) !== null
            && (int) $row->is_visible === 1 && (int) ($row->question_length ?? 0) > 0 && (int) ($row->answer_length ?? 0) > 0)
            ->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();
        $content = $ids === [] ? collect() : $this->oldDatabase->table(self::SOURCE_TABLE)
            ->whereIn('id', $ids)->get(['id', 'question', 'answer'])->keyBy('id');
        $mappings = $this->mappings($metadata->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all());

        $prepared = [];
        $questionCounts = [];
        foreach ($metadata as $row) {
            $locale = $this->locale($row->lang);
            $source = $content->get($row->id);
            $question = is_object($source) ? $this->plainText($this->cleaner, $source->question) : null;
            $answer = is_object($source) ? $this->plainText($this->cleaner, $source->answer) : null;
            $eligible = $locale !== null && (int) $row->is_visible === 1 && $question !== null && $answer !== null;
            if ($eligible) {
                $key = $locale.'|'.$this->normalizedReviewText($question);
                $questionCounts[$key] = ($questionCounts[$key] ?? 0) + 1;
            }
            $prepared[] = compact('row', 'locale', 'question', 'answer', 'eligible');
        }

        $candidates = [];
        $backlog = [];
        $reasonCounts = [];
        $blockerCounts = [];
        $localeCounts = [];
        $visible = 0;
        $answered = 0;
        foreach ($prepared as $item) {
            $row = $item['row'];
            $locale = $item['locale'];
            $visible += (int) $row->is_visible === 1 ? 1 : 0;
            $answered += (int) ($row->answer_length ?? 0) > 0 ? 1 : 0;
            $mapping = $mappings[(int) $row->id] ?? [];
            if (! $item['eligible']) {
                $reasons = [];
                $contentWasInspected = $locale !== null && (int) $row->is_visible === 1
                    && (int) ($row->question_length ?? 0) > 0 && (int) ($row->answer_length ?? 0) > 0;
                if ($locale === null) {
                    $reasons[] = 'unsupported_locale';
                }
                if ((int) $row->is_visible !== 1) {
                    $reasons[] = 'hidden_source';
                }
                if ((int) ($row->question_length ?? 0) === 0 || ($contentWasInspected && $item['question'] === null)) {
                    $reasons[] = 'missing_question';
                }
                if ((int) ($row->answer_length ?? 0) === 0 || ($contentWasInspected && $item['answer'] === null)) {
                    $reasons[] = 'missing_answer';
                }
                $this->incrementReviewCounts($reasonCounts, $reasons);
                $backlog[] = [
                    'source_table' => self::SOURCE_TABLE, 'source_id' => (int) $row->id, 'legacy_lang' => (int) $row->lang,
                    'locale' => $locale, 'sort_order' => is_numeric($row->faq_order) ? (int) $row->faq_order : null,
                    'post_date' => is_scalar($row->post_date) ? (string) $row->post_date : null, 'is_visible' => (int) $row->is_visible,
                    'source_question_length' => (int) ($row->question_length ?? 0), 'source_answer_length' => (int) ($row->answer_length ?? 0),
                    'disposition_reasons' => implode('|', $reasons), 'existing_target_mapping' => $mapping !== [] ? 1 : 0,
                    'mapping_success_count' => count($mapping), 'mapping_target_ids' => implode('|', array_column($mapping, 'target_id')),
                ];

                continue;
            }

            $question = $item['question'];
            $answer = $item['answer'];
            $blockers = [];
            if (($questionCounts[$locale.'|'.$this->normalizedReviewText($question)] ?? 0) > 1) {
                $blockers[] = 'duplicate_supported_question';
            }
            if ($this->containsContactPattern($question) || $this->containsContactPattern($answer)) {
                $blockers[] = 'content_contains_contact_pattern';
            }
            $date = $this->cleaner->cleanDate($row->post_date, 'post_date');
            if (! $date->canImportPublicly) {
                $blockers[] = 'invalid_post_date';
            }
            if ($mapping !== []) {
                $blockers[] = 'existing_target_mapping';
            }
            $this->incrementReviewCounts($blockerCounts, $blockers);
            $localeCounts[$locale] = ($localeCounts[$locale] ?? 0) + 1;
            $candidates[] = [
                'source_table' => self::SOURCE_TABLE, 'source_id' => (int) $row->id, 'locale' => $locale, 'legacy_lang' => (int) $row->lang,
                'recommended_action' => 'faq_import_review', 'candidate_target' => 'faqs',
                'review_status' => $mapping !== [] ? 'mapped_reconciliation_review' : 'pending_editorial_review',
                'blockers' => implode('|', $blockers), 'approval_decision' => '', 'approved_target' => '',
                'question' => $question, 'answer' => $answer, 'subject' => $this->plainText($this->cleaner, $row->subject),
                'sort_order' => is_numeric($row->faq_order) ? (int) $row->faq_order : (int) $row->id,
                'post_date' => $date->cleanedValue, 'is_visible' => (int) $row->is_visible,
                'question_sha256' => hash('sha256', $question), 'answer_sha256' => hash('sha256', $answer),
                'question_length' => mb_strlen($question), 'answer_length' => mb_strlen($answer),
                'source_question_length' => (int) $row->question_length, 'source_answer_length' => (int) $row->answer_length,
                'mapping_success_count' => count($mapping), 'mapping_target_ids' => implode('|', array_column($mapping, 'target_id')),
                'existing_target_mapping' => $mapping !== [] ? 1 : 0,
            ];
        }
        ksort($reasonCounts);
        ksort($blockerCounts);
        ksort($localeCounts);

        $base = $directory.'/'.now()->format('Ymd_His');
        $paths = [$base.'/faq_candidates.csv', $base.'/faq_backlog.csv', $base.'/manifest.json', $base.'/summary.md'];
        Storage::disk($disk)->put($paths[0], $this->reviewCsv(self::CANDIDATE_HEADERS, $candidates));
        Storage::disk($disk)->put($paths[1], $this->reviewCsv(self::BACKLOG_HEADERS, $backlog));
        $summary = [
            'total_rows' => $metadata->count(), 'candidate_rows' => count($candidates), 'backlog_rows' => count($backlog),
            'candidate_locale_counts' => $localeCounts, 'visible_rows' => $visible, 'answered_rows' => $answered,
            'duplicate_rows' => $blockerCounts['duplicate_supported_question'] ?? 0,
            'contact_pattern_rows' => $blockerCounts['content_contains_contact_pattern'] ?? 0,
            'mapped_rows' => $blockerCounts['existing_target_mapping'] ?? 0,
            'reason_counts' => $reasonCounts, 'blocker_counts' => $blockerCounts,
        ];
        Storage::disk($disk)->put($paths[2], (string) json_encode([
            'generated_at' => now()->toIso8601String(), 'source_table' => self::SOURCE_TABLE, 'private_evidence' => true,
            'read_only' => true, 'public_feature_complete' => false, 'pii_fields_excluded' => self::PII_FIELDS,
            'pii_values_exported' => false, 'summary' => $summary, 'paths' => $paths, 'warnings' => $warnings,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        Storage::disk($disk)->put($paths[3], $this->markdown($summary, $paths, $warnings));

        return new LegacyFaqReviewPacketResultDTO($disk, $metadata->count(), count($candidates), count($backlog), $reasonCounts, $blockerCounts, $paths, $warnings);
    }

    /** @param list<int> $ids @return array<int, list<array{target_id: int}>> */
    private function mappings(array $ids): array
    {
        if ($ids === [] || ! Schema::hasTable('migration_logs')) {
            return [];
        }

        return DB::table('migration_logs')->where('source_table', self::SOURCE_TABLE)->where('status', 'success')->whereIn('source_id', $ids)
            ->get(['source_id', 'target_id'])->groupBy('source_id')->map(fn ($rows): array => $rows->map(fn (object $row): array => ['target_id' => (int) $row->target_id])->all())->all();
    }

    private function locale(mixed $lang): ?string
    {
        return (int) $lang === 1 ? 'ar' : ((int) $lang === 2 ? 'en' : null);
    }

    /** @param array<string, mixed> $summary @param list<string> $paths @param list<string> $warnings */
    private function markdown(array $summary, array $paths, array $warnings): string
    {
        $lines = ['# Legacy FAQ Review Packet', '', '- Private evidence: yes', '- Public feature completion: no',
            '- Total/candidate/backlog: '.$summary['total_rows'].'/'.$summary['candidate_rows'].'/'.$summary['backlog_rows'],
            '- Visible/answered: '.$summary['visible_rows'].'/'.$summary['answered_rows'],
            '- Duplicate/contact/mapped: '.$summary['duplicate_rows'].'/'.$summary['contact_pattern_rows'].'/'.$summary['mapped_rows'],
            '- Reasons: '.json_encode($summary['reason_counts'], JSON_UNESCAPED_UNICODE),
            '- Blockers: '.json_encode($summary['blocker_counts'], JSON_UNESCAPED_UNICODE)];
        foreach ($paths as $path) {
            $lines[] = '- Path: '.$path;
        }
        foreach ($warnings as $warning) {
            $lines[] = '- Warning: '.$warning;
        }

        return implode("\n", $lines)."\n";
    }
}
