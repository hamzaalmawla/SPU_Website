<?php

declare(strict_types=1);

namespace App\DTOs\Chatbot;

/**
 * A visitor asking to start live transcription (the microphone streamed to
 * OpenAI while they speak).
 */
final readonly class ChatbotLiveSessionRequestDTO
{
    public function __construct(
        public string $locale,
        public ?string $clientKey = null,
        public ?string $visitorKey = null,
    ) {}
}
