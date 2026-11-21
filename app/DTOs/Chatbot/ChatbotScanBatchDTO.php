<?php

declare(strict_types=1);

namespace App\DTOs\Chatbot;

/**
 * What one round of reading scanned pages produced.
 */
final readonly class ChatbotScanBatchDTO
{
    /**
     * @param  array<int, string>  $texts  page number => transcribed text ('' for a page without text)
     * @param  list<int>  $failedPages  pages that could not be read this round (retried later)
     */
    public function __construct(
        public array $texts,
        public array $failedPages,
        public string $model,
        public int $inputTokens,
        public int $outputTokens,
    ) {}
}
