<?php

declare(strict_types=1);

namespace App\DTOs\Chatbot;

/**
 * What the assistant remembers of a conversation beyond its recent turns:
 * the notes the model keeps, and how many exchanges they cover.
 */
final readonly class ChatbotConversationMemoryDTO
{
    public function __construct(
        public string $notes,
        public int $exchanges,
    ) {}
}
