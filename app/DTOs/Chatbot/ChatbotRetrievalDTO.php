<?php

declare(strict_types=1);

namespace App\DTOs\Chatbot;

/**
 * Passages found for a question, best first, and the embedding tokens spent.
 */
final readonly class ChatbotRetrievalDTO
{
    /**
     * @param  list<ChatbotPassageDTO>  $passages
     */
    public function __construct(
        public array $passages,
        public int $embeddingTokens,
    ) {}
}
