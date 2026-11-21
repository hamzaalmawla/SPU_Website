<?php

declare(strict_types=1);

namespace App\Services\Chatbot;

use App\Contracts\Chatbot\ChatbotEvaluationServiceInterface;
use App\Contracts\Chatbot\ChatbotServiceInterface;
use App\Contracts\Chatbot\ChatbotUsageServiceInterface;
use App\DTOs\Chatbot\ChatbotEvaluationReportDTO;
use App\DTOs\Chatbot\ChatbotPassageDTO;
use App\DTOs\Chatbot\ChatbotQuestionDTO;
use App\Support\Chatbot\EvaluationScorer;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Runs the golden questions against the live knowledge, the way the admin
 * "Try it" box asks: the whole pipeline (screen, rewrite, search, answer,
 * guards), no answer cache and no visitor limits. Scores are deterministic
 * string checks (EvaluationScorer), so two runs differ only where the
 * assistant does.
 *
 * Cost is read from the usage counters before and after the run, so it
 * also counts any visitor who asked at the same time (small at night).
 */
final class ChatbotEvaluationService implements ChatbotEvaluationServiceInterface
{
    public function __construct(
        private readonly ChatbotServiceInterface $assistant,
        private readonly ChatbotUsageServiceInterface $usage,
    ) {}

    public function cases(): array
    {
        $path = database_path('seeders/Data/chatbot/evaluation-questions.json');
        $data = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
        if (! is_array($data) || ! is_array($data['cases'] ?? null)) {
            throw new InvalidArgumentException('The evaluation questions could not be read.');
        }

        $cases = [];
        foreach ($data['cases'] as $case) {
            if (! is_array($case) || ! is_string($case['id'] ?? null) || ! is_string($case['question'] ?? null)) {
                continue;
            }
            $cases[] = [
                'id' => $case['id'],
                'locale' => ($case['locale'] ?? 'ar') === 'en' ? 'en' : 'ar',
                'question' => $case['question'],
                'sources' => self::groups($case['sources'] ?? []),
                'facts' => self::groups($case['facts'] ?? []),
            ];
        }

        return $cases;
    }

    public function run(array $cases, string $label, array $settings = [], ?callable $progress = null): ChatbotEvaluationReportDTO
    {
        $previous = [];
        foreach ($settings as $key => $value) {
            $previous['chatbot.'.$key] = config('chatbot.'.$key);
            config(['chatbot.'.$key => $value]);
        }

        $costBefore = $this->usage->summary()->costTodayUsd;
        $results = [];

        try {
            foreach ($cases as $case) {
                $started = hrtime(true);
                $reply = $this->assistant->ask(new ChatbotQuestionDTO(
                    locale: $case['locale'],
                    message: $case['question'],
                    useCache: false,
                    adminTest: true,
                ));
                $ms = intdiv(hrtime(true) - $started, 1_000_000);

                $titles = array_values(array_unique(array_map(static fn (ChatbotPassageDTO $passage): string => $passage->documentTitle, $reply->passages)));
                $sources = EvaluationScorer::sources($titles, $case['sources']);
                $facts = EvaluationScorer::facts($reply->status === 'ok' ? $reply->answer : '', $case['facts']);

                $result = [
                    'id' => $case['id'],
                    'locale' => $case['locale'],
                    'question' => $case['question'],
                    'status' => $reply->status,
                    'answer' => $reply->answer,
                    'ms' => $ms,
                    'sourcesFound' => $sources['found'],
                    'sourcesTotal' => $sources['total'],
                    'factsFound' => $facts['found'],
                    'factsTotal' => $facts['total'],
                    'missing' => $facts['missing'],
                    'titles' => $titles,
                    'passed' => $reply->status === 'ok' && $facts['found'] === $facts['total'],
                ];
                $results[] = $result;
                if ($progress !== null) {
                    $progress($result);
                }
            }
        } finally {
            config($previous);
        }

        $count = max(1, count($results));
        $factsTotal = max(1, array_sum(array_column($results, 'factsTotal')));
        $report = new ChatbotEvaluationReportDTO(
            label: $label,
            ranAt: now()->toIso8601String(),
            settings: $settings,
            results: $results,
            retrievalRecall: round(count(array_filter($results, static fn (array $r): bool => $r['sourcesFound'] === $r['sourcesTotal'])) / $count, 3),
            factRecall: round(array_sum(array_column($results, 'factsFound')) / $factsTotal, 3),
            passRate: round(count(array_filter($results, static fn (array $r): bool => $r['passed'])) / $count, 3),
            averageMs: intdiv(array_sum(array_column($results, 'ms')), $count),
            costUsd: round(max(0.0, $this->usage->summary()->costTodayUsd - $costBefore), 4),
        );

        return $this->save($report);
    }

    private function save(ChatbotEvaluationReportDTO $report): ChatbotEvaluationReportDTO
    {
        $path = 'chatbot/evaluations/'.now()->format('Y-m-d-His').'-'.preg_replace('/[^a-z0-9-]+/', '-', strtolower($report->label)).'.json';
        Storage::disk((string) config('chatbot.knowledge.disk', 'local'))->put($path, (string) json_encode([
            'label' => $report->label,
            'ranAt' => $report->ranAt,
            'settings' => $report->settings,
            'retrievalRecall' => $report->retrievalRecall,
            'factRecall' => $report->factRecall,
            'passRate' => $report->passRate,
            'averageMs' => $report->averageMs,
            'costUsd' => $report->costUsd,
            'results' => $report->results,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));

        return new ChatbotEvaluationReportDTO(
            $report->label,
            $report->ranAt,
            $report->settings,
            $report->results,
            $report->retrievalRecall,
            $report->factRecall,
            $report->passRate,
            $report->averageMs,
            $report->costUsd,
            $path,
        );
    }

    /**
     * @return list<list<string>>
     */
    private static function groups(mixed $groups): array
    {
        $clean = [];
        foreach ((array) $groups as $group) {
            $items = array_values(array_filter((array) $group, static fn (mixed $item): bool => is_string($item) && trim($item) !== ''));
            if ($items !== []) {
                $clean[] = $items;
            }
        }

        return $clean;
    }
}
