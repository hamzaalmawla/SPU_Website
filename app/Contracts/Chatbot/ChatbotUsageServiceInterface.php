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
     * A refused question (off-topic, code, rule-bypass or harmful). Costs are
     * recorded separately when the model was asked before refusing.
     */
    public function recordBlocked(): bool;

    /** A question stopped by a visitor's fair-use limit. */
    public function recordRateLimited(): bool;

    /** A visitor or address paused for repeated misuse. */
    public function recordPaused(): bool;

    /**
     * Embedding tokens spent indexing documents.
     */
    public function recordIndexing(int $embeddingTokens): bool;

    /**
     * Model tokens spent outside answers: reading scanned pages of knowledge
     * files, rewriting questions for search.
     */
    public function recordModelUse(string $model, int $inputTokens, int $outputTokens): bool;

    /**
     * One recording transcribed (its billed or measured length in seconds).
     */
    public function recordTranscription(string $model, float $seconds): bool;

    /**
     * One segment of a reply read aloud; `$newAnswer` for a reply's first segment.
     */
    public function recordSpeech(string $model, int $characters, bool $newAnswer): bool;

    /** A transcription or speech call that failed. */
    public function recordVoiceFailure(): bool;

    public function questionsToday(): int;

    /**
     * Recordings transcribed plus replies read aloud today (the voice cap).
     */
    public function voiceRequestsToday(): int;

    public function summary(): ChatbotUsageSummaryDTO;
}
