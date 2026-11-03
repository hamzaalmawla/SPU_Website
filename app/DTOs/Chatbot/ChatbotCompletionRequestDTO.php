<?php

declare(strict_types=1);

namespace App\DTOs\Chatbot;

final readonly class ChatbotCompletionRequestDTO
{
    /**
     * @param  list<array{role: 'user'|'assistant', content: string}>  $messages
     */
    public function __construct(
        public string $apiKey,
        public string $model,
        public string $instructions,
        public array $messages,
        public int $maxOutputTokens,
        public ?string $reasoningEffort = null,
        public ?string $cacheKey = null,
        /** @var array<string, mixed>|null JSON schema the reply must follow (structured output). */
        public ?array $jsonSchema = null,
    ) {}
}
