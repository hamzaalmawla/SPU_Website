<?php

declare(strict_types=1);

namespace App\DTOs\Chatbot;

/**
 * One image of a scanned page, ready to send to a vision model.
 */
final readonly class ChatbotScanImageDTO
{
    public function __construct(
        /** image/jpeg, image/png or image/webp */
        public string $mime,
        /** The raw image bytes (not base64). */
        public string $bytes,
    ) {}
}
