<?php

declare(strict_types=1);

namespace App\DTOs\Chatbot;

/**
 * Usage figures for the admin page. Costs are estimates in US dollars from
 * the prices in config/chatbot.php.
 */
final readonly class ChatbotUsageSummaryDTO
{
    /**
     * @param  list<array{day: string, questions: int, costUsd: float}>  $days  last 14 days, oldest first
     */
    public function __construct(
        public int $questionsToday,
        public int $cacheHitsToday,
        public int $withoutSourcesToday,
        public int $failuresToday,
        public int $blockedToday,
        public int $rateLimitedToday,
        public int $pausedToday,
        public float $costTodayUsd,
        public int $questions30Days,
        public float $cost30DaysUsd,
        public int $tokens30Days,
        public array $days,
        // Voice today: recordings transcribed (and their minutes), replies read aloud, failed calls.
        public int $voiceTranscriptionsToday = 0,
        public int $voiceSpokenAnswersToday = 0,
        public float $voiceMinutesToday = 0.0,
        public int $voiceFailuresToday = 0,
    ) {}
}
