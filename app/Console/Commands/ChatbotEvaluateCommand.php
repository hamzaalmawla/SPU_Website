<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Chatbot\ChatbotEvaluationServiceInterface;
use Illuminate\Console\Command;

/**
 * Asks the golden questions of the live assistant and prints how many it
 * got right (Docs/AI_ASSISTANT.md, "Evaluation"). --rerank runs the set with
 * LLM reranking on or off; several values (--rerank=off --rerank=on) run it
 * once per value so the two can be compared on the same knowledge.
 */
final class ChatbotEvaluateCommand extends Command
{
    protected $signature = 'chatbot:evaluate
        {--rerank=* : off or on (one run each); default: the configured setting}
        {--only=* : Run only these case ids}
        {--label=run : Name for the saved report}';

    protected $description = 'Ask the AI assistant the golden questions and score retrieval and answers.';

    public function __construct(
        private readonly ChatbotEvaluationServiceInterface $evaluation,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $cases = $this->evaluation->cases();
        $only = array_values(array_filter((array) $this->option('only'), 'is_string'));
        if ($only !== []) {
            $cases = array_values(array_filter($cases, static fn (array $case): bool => in_array($case['id'], $only, true)));
        }
        if ($cases === []) {
            $this->error('No evaluation questions to ask.');

            return self::INVALID;
        }

        $variants = array_values(array_filter((array) $this->option('rerank'), 'is_string'));
        $runs = $variants === [] ? [null] : $variants;
        $summaries = [];

        foreach ($runs as $variant) {
            $settings = $variant === null ? [] : ['retrieval.rerank' => $variant === 'on'];
            $label = (string) $this->option('label').($variant !== null ? '-rerank-'.$variant : '');
            $this->line('');
            $this->line('== '.$label.' ('.count($cases).' questions)');

            $report = $this->evaluation->run($cases, $label, $settings, function (array $result): void {
                $this->line(sprintf(
                    '%s %-34s sources %d/%d  facts %d/%d  %5d ms%s',
                    $result['passed'] ? 'PASS' : 'FAIL',
                    $result['id'],
                    $result['sourcesFound'],
                    $result['sourcesTotal'],
                    $result['factsFound'],
                    $result['factsTotal'],
                    $result['ms'],
                    $result['missing'] !== [] ? '  missing: '.implode(', ', $result['missing']) : ($result['status'] !== 'ok' ? '  status: '.$result['status'] : ''),
                ));
            });

            $summary = sprintf(
                '%s: answers right %d%% · facts found %d%% · right documents found %d%% · %d ms average · ~$%s',
                $label,
                (int) round($report->passRate * 100),
                (int) round($report->factRecall * 100),
                (int) round($report->retrievalRecall * 100),
                $report->averageMs,
                number_format($report->costUsd, 4),
            );
            $summaries[] = $summary;
            $this->line($summary.' (saved: '.$report->savedAs.')');
        }

        if (count($summaries) > 1) {
            $this->line('');
            $this->line('== Comparison');
            foreach ($summaries as $summary) {
                $this->line($summary);
            }
        }

        return self::SUCCESS;
    }
}
