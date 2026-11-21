<?php

declare(strict_types=1);

namespace App\DTOs\Chatbot;

final readonly class ChatbotCompletionRequestDTO
{
    /**
     * @param  list<array{role: 'user'|'assistant', content: string|list<array<string, mixed>>}>  $messages  content may be a list of parts (input_text, input_image)
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
        /** Seconds to wait for the reply; null uses chatbot.openai.timeout. */
        public ?int $timeout = null,
        /**
         * Sampling temperature; 0 makes the same input give the same output
         * (as far as the provider allows). Null sends none (reasoning models
         * that think take none).
         */
        public ?float $temperature = null,
    ) {}
}
