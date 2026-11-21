<?php

declare(strict_types=1);

namespace App\Contracts\Chatbot;

use App\DTOs\Chatbot\ChatbotEvaluationReportDTO;

/**
 * The golden-question evaluation: real questions with the facts their answer
 * must contain and the documents those facts come from, asked of the live
 * assistant exactly as a visitor would (as an admin test: no cache, no
 * limits). It measures retrieval (were the right documents given?) and
 * answers (were the facts said?), so every change to search, prompts or
 * models is judged by numbers before it is kept.
 */
interface ChatbotEvaluationServiceInterface
{
    /**
     * The golden questions (database/seeders/Data/chatbot/evaluation-questions.json).
     *
     * @return list<array{id: string, locale: string, question: string, sources: list<list<string>>, facts: list<list<string>>}>
     */
    public function cases(): array;

    /**
     * Ask every case and score it. `$settings` overrides chatbot config for
     * this run only (for example ['retrieval.rerank' => true]). The report is
     * saved under chatbot/evaluations on the knowledge disk.
     *
     * @param  list<array{id: string, locale: string, question: string, sources: list<list<string>>, facts: list<list<string>>}>  $cases
     * @param  array<string, bool|int|string>  $settings
     * @param  (callable(array<string, mixed>): void)|null  $progress  called after each case with its result
     */
    public function run(array $cases, string $label, array $settings = [], ?callable $progress = null): ChatbotEvaluationReportDTO;
}
