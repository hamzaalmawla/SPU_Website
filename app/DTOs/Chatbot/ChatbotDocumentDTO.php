<?php

declare(strict_types=1);

namespace App\DTOs\Chatbot;

/**
 * A knowledge document as listed on the admin page.
 *
 * `needsReindex` is true when the passages were embedded with another model or
 * vector size than the current configuration, so they no longer match a
 * question's embedding.
 */
final readonly class ChatbotDocumentDTO
{
    public function __construct(
        public int $id,
        public string $title,
        public string $sourceType,
        public ?string $originalName,
        public ?string $extension,
        public int $sizeBytes,
        public string $status,
        public ?string $error,
        public bool $isActive,
        public int $chunkCount,
        public int $characterCount,
        public bool $needsReindex,
        public ?string $uploadedBy,
        public ?string $indexedAt,
        public string $createdAt,
    ) {}
}
