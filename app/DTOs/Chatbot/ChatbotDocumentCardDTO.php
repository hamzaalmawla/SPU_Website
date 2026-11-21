<?php

declare(strict_types=1);

namespace App\DTOs\Chatbot;

/**
 * A document's key facts, written once when it is indexed.
 */
final readonly class ChatbotDocumentCardDTO
{
    public function __construct(
        public string $text,
        public string $model,
        public int $inputTokens,
        public int $outputTokens,
    ) {}
}
