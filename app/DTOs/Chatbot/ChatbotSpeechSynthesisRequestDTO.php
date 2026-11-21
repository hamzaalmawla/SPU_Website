<?php

declare(strict_types=1);

namespace App\DTOs\Chatbot;

/**
 * One text-to-speech call to OpenAI.
 */
final readonly class ChatbotSpeechSynthesisRequestDTO
{
    public function __construct(
        public string $apiKey,
        public string $model,
        public string $voice,
        public string $input,
        public string $instructions = '',
        public string $format = 'mp3',
        public int $timeout = 30,
    ) {}
}
