<?php

declare(strict_types=1);

namespace App\DTOs\Chatbot;

/**
 * One run of the golden questions (chatbot:evaluate).
 *
 * Each result: id, question, status, answer, ms, sourcesFound/sourcesTotal,
 * factsFound/factsTotal, missing facts, the passage titles given, and
 * whether the answer passed (every fact present).
 */
final readonly class ChatbotEvaluationReportDTO
{
    /**
     * @param  list<array{id: string, locale: string, question: string, status: string, answer: string, ms: int, sourcesFound: int, sourcesTotal: int, factsFound: int, factsTotal: int, missing: list<string>, titles: list<string>, passed: bool}>  $results
     * @param  array<string, bool|int|string>  $settings  what was switched on for this run
     */
    public function __construct(
        public string $label,
        public string $ranAt,
        public array $settings,
        public array $results,
        // Share of questions whose expected documents were all among the passages (0-1).
        public float $retrievalRecall,
        // Share of expected facts found in the answers (0-1).
        public float $factRecall,
        // Share of questions answered with every expected fact (0-1).
        public float $passRate,
        public int $averageMs,
        // Estimated model and embedding cost of the run (standard prices, USD).
        public float $costUsd,
        public ?string $savedAs = null,
    ) {}
}
