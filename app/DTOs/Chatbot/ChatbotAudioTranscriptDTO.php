<?php

declare(strict_types=1);

namespace App\DTOs\Chatbot;

/**
 * What OpenAI heard in one recording.
 */
final readonly class ChatbotAudioTranscriptDTO
{
    /**
     * @param  list<string>  $languages  detected ISO-639-1 codes, when the model reports them
     */
    public function __construct(
        public string $text,
        public array $languages = [],
        // Billed audio length, when the model reports usage by duration.
        public ?float $seconds = null,
        public int $inputTokens = 0,
        public int $outputTokens = 0,
    ) {}
}
