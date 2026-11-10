<?php

declare(strict_types=1);

namespace App\DTOs\Chatbot;

/**
 * The contexts written for a document's passages, and what they cost.
 */
final readonly class ChatbotPassageContextsDTO
{
    /**
     * @param  list<string>  $contexts  one per passage, '' where none was written
     */
    public function __construct(
        public array $contexts,
        public string $model,
        public int $inputTokens,
        public int $cachedInputTokens,
        public int $outputTokens,
    ) {}
}
