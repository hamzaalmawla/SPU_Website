<?php

declare(strict_types=1);

namespace App\DTOs\Chatbot;

/**
 * One speech-to-text call to OpenAI.
 */
final readonly class ChatbotTranscriptionCallDTO
{
    /**
     * @param  list<string>  $languages  ISO-639-1 hints (only for models that take them)
     * @param  list<string>  $keywords  words the speaker is likely to say
     */
    public function __construct(
        public string $apiKey,
        public string $model,
        public string $path,
        public string $filename,
        public string $mimeType,
        public string $prompt = '',
        public array $languages = [],
        public array $keywords = [],
        public int $timeout = 30,
    ) {}
}
