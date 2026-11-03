<?php

declare(strict_types=1);

namespace App\DTOs\Chatbot;

/**
 * One published page of the website, as plain text with Markdown headings.
 */
final readonly class ChatbotWebPageDTO
{
    public function __construct(
        public string $path,
        public string $locale,
        public string $title,
        public string $text,
    ) {}
}
