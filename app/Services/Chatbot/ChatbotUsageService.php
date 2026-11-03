<?php

declare(strict_types=1);

namespace App\Services\Chatbot;

use App\Contracts\Chatbot\ChatbotUsageServiceInterface;
use App\DTOs\Chatbot\ChatbotUsageSummaryDTO;
use App\Models\Chatbot\ChatbotUsageDay;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * One row per day of counters. Increments are single UPDATE statements, so
 * concurrent requests never lose a count. A failure to record usage never
 * fails the visitor's request.
 */
final class ChatbotUsageService implements ChatbotUsageServiceInterface
{
    public function recordAnswer(string $model, int $inputTokens, int $cachedInputTokens, int $outputTokens, int $embeddingTokens, bool $withoutSources): bool
    {
        return $this->increment([
            'questions' => 1,
            'without_sources' => $withoutSources ? 1 : 0,
            'input_tokens' => max(0, $inputTokens),
            'cached_input_tokens' => max(0, $cachedInputTokens),
            'output_tokens' => max(0, $outputTokens),
            'embedding_tokens' => max(0, $embeddingTokens),
            'cost_micro_usd' => $this->costMicroUsd($model, $inputTokens, $cachedInputTokens, $outputTokens) + $this->embeddingMicroUsd($embeddingTokens),
        ]);
    }

    public function recordCachedAnswer(): bool
    {
        return $this->increment(['questions' => 1, 'answered_from_cache' => 1]);
    }

    public function recordFailure(int $embeddingTokens = 0): bool
    {
        return $this->increment([
            'questions' => 1,
            'failures' => 1,
            'embedding_tokens' => max(0, $embeddingTokens),
            'cost_micro_usd' => $this->embeddingMicroUsd($embeddingTokens),
        ]);
    }

    public function recordBlocked(): bool
    {
        return $this->increment(['blocked' => 1]);
    }

    public function recordRateLimited(): bool
    {
        return $this->increment(['rate_limited' => 1]);
    }

    public function recordPaused(): bool
    {
        return $this->increment(['paused' => 1]);
    }

    public function recordIndexing(int $embeddingTokens): bool
    {
        return $this->increment([
            'embedding_tokens' => max(0, $embeddingTokens),
            'cost_micro_usd' => $this->embeddingMicroUsd($embeddingTokens),
        ]);
    }

    public function questionsToday(): int
    {
        return (int) ChatbotUsageDay::query()->where('day', $this->today())->value('questions');
    }

    public function summary(): ChatbotUsageSummaryDTO
    {
        $today = $this->today();
        $from = Carbon::parse($today)->subDays(29)->toDateString();
        $rows = ChatbotUsageDay::query()->where('day', '>=', $from)->orderBy('day')->get();

        $byDay = [];
        foreach ($rows as $row) {
            $byDay[substr((string) $row->day, 0, 10)] = $row;
        }
        $todayRow = $byDay[$today] ?? null;

        $days = [];
        for ($offset = 13; $offset >= 0; $offset--) {
            $day = Carbon::parse($today)->subDays($offset)->toDateString();
            $row = $byDay[$day] ?? null;
            $days[] = [
                'day' => $day,
                'questions' => (int) ($row?->questions ?? 0),
                'costUsd' => round(((int) ($row?->cost_micro_usd ?? 0)) / 1_000_000, 4),
            ];
        }

        return new ChatbotUsageSummaryDTO(
            questionsToday: (int) ($todayRow?->questions ?? 0),
            cacheHitsToday: (int) ($todayRow?->answered_from_cache ?? 0),
            withoutSourcesToday: (int) ($todayRow?->without_sources ?? 0),
            failuresToday: (int) ($todayRow?->failures ?? 0),
            blockedToday: (int) ($todayRow?->blocked ?? 0),
            rateLimitedToday: (int) ($todayRow?->rate_limited ?? 0),
            pausedToday: (int) ($todayRow?->paused ?? 0),
            costTodayUsd: round(((int) ($todayRow?->cost_micro_usd ?? 0)) / 1_000_000, 4),
            questions30Days: (int) $rows->sum('questions'),
            cost30DaysUsd: round(((int) $rows->sum('cost_micro_usd')) / 1_000_000, 4),
            tokens30Days: (int) ($rows->sum('input_tokens') + $rows->sum('output_tokens') + $rows->sum('embedding_tokens')),
            days: $days,
        );
    }

    /**
     * @param  array<string, int>  $amounts
     */
    private function increment(array $amounts): bool
    {
        $amounts = array_filter($amounts, static fn (int $amount): bool => $amount > 0);
        if ($amounts === []) {
            return true;
        }

        try {
            $day = $this->today();
            // insertOrIgnore: two first requests of a day cannot both create the row.
            ChatbotUsageDay::query()->insertOrIgnore(['day' => $day, 'created_at' => now(), 'updated_at' => now()]);

            $updates = ['updated_at' => now()];
            foreach ($amounts as $column => $amount) {
                $updates[$column] = DB::raw($this->wrap($column).' + '.(int) $amount);
            }

            return ChatbotUsageDay::query()->where('day', $day)->update($updates) > 0;
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    private function wrap(string $column): string
    {
        return DB::getQueryGrammar()->wrap($column);
    }

    private function costMicroUsd(string $model, int $input, int $cached, int $output): int
    {
        // Model ids contain dots (gpt-5.4-mini): no config dot path.
        $prices = (array) (((array) config('chatbot.models', []))[$model] ?? []);
        $cached = min(max(0, $cached), max(0, $input));
        $usd = (($input - $cached) * (float) ($prices['input'] ?? 0)
            + $cached * (float) ($prices['cached_input'] ?? ($prices['input'] ?? 0))
            + max(0, $output) * (float) ($prices['output'] ?? 0)) / 1_000_000;

        return (int) round($usd * 1_000_000);
    }

    private function embeddingMicroUsd(int $tokens): int
    {
        return (int) round(max(0, $tokens) * (float) config('chatbot.embedding.price_per_million', 0.02));
    }

    private function today(): string
    {
        return now()->toDateString();
    }
}
