<?php

declare(strict_types=1);

namespace App\DTOs\Chatbot;

/**
 * A request to read one segment of an assistant reply aloud.
 */
final readonly class ChatbotSpeechRequestDTO
{
    public function __construct(
        public string $locale,
        // The reply exactly as the assistant sent it.
        public string $text,
        // ConversationSigner::signSpeech of that text.
        public ?string $signature,
        // 0-based segment of the reply's spoken form (SpeechText::segments).
        public int $segment = 0,
        public ?string $clientKey = null,
        public ?string $visitorKey = null,
    ) {}
}
