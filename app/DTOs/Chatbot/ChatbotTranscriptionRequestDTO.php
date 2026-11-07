<?php

declare(strict_types=1);

namespace App\DTOs\Chatbot;

/**
 * A visitor's recorded question, already validated (size and type).
 */
final readonly class ChatbotTranscriptionRequestDTO
{
    public function __construct(
        public string $locale,
        // The uploaded file's temporary path; it is never kept.
        public string $path,
        public string $mimeType,
        public int $sizeBytes,
        // Recording length the browser measured (an estimate; used for cost figures).
        public int $durationMs,
        public ?string $clientKey = null,
        public ?string $visitorKey = null,
    ) {}
}
