<?php

declare(strict_types=1);

namespace App\DTOs\Chatbot;

/**
 * What the public layout needs to render the assistant's launcher and panel.
 */
final readonly class ChatbotWidgetDTO
{
    /**
     * @param  list<string>  $suggestions
     */
    public function __construct(
        public bool $enabled,
        public string $locale,
        public string $assistantName,
        public string $welcome,
        public array $suggestions,
        public string $endpoint,
        public int $maxMessageLength,
        public ?string $contactPhone,
        public ?string $contactEmail,
    ) {}
}
