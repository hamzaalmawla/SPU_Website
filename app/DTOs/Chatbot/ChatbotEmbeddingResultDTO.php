<?php

declare(strict_types=1);

namespace App\DTOs\Chatbot;

final readonly class ChatbotEmbeddingResultDTO
{
    /**
     * @param  list<list<float>>  $vectors  one per input, in input order
     */
    public function __construct(
        public array $vectors,
        public int $tokens,
    ) {}
}
