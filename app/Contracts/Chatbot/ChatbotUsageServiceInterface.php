<?php

declare(strict_types=1);

namespace App\Contracts\Chatbot;

use App\DTOs\Chatbot\ChatbotUsageSummaryDTO;

/**
 * Daily usage counters and estimated spend. Stores numbers only, never the
 * text of a question or an answer.
 */
interface ChatbotUsageServiceInterface
{
    public function recordAnswer(string $model, int $inputTokens, int $cachedInputTokens, int $outputTokens, int $embeddingTokens, bool $withoutSources): bool;

    public function recordCachedAnswer(): bool;

    public function recordFailure(int $embeddingTokens = 0): bool;

    /**
     * Embedding tokens spent indexing documents.
     */
    public function recordIndexing(int $embeddingTokens): bool;

    public function questionsToday(): int;

    public function summary(): ChatbotUsageSummaryDTO;
}
