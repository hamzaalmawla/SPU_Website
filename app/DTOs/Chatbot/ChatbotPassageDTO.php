<?php

declare(strict_types=1);

namespace App\DTOs\Chatbot;

/**
 * A retrieved passage with its relevance score (0-1).
 */
final readonly class ChatbotPassageDTO
{
    public function __construct(
        public int $chunkId,
        public int $documentId,
        public string $documentTitle,
        public ?int $page,
        public ?string $heading,
        public string $content,
        public float $score,
    ) {}
}
