<?php

declare(strict_types=1);

namespace App\DTOs\Chatbot;

final readonly class ChatbotCompletionDTO
{
    public function __construct(
        public string $text,
        public int $inputTokens,
        public int $cachedInputTokens,
        public int $outputTokens,
        public bool $truncated = false,
    ) {}
}
