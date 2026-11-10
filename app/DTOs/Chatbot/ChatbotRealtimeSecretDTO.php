<?php

declare(strict_types=1);

namespace App\DTOs\Chatbot;

/**
 * A short-lived Realtime client secret minted by OpenAI.
 */
final readonly class ChatbotRealtimeSecretDTO
{
    public function __construct(
        public string $value,
        // Unix time after which it can no longer open a connection.
        public int $expiresAt,
    ) {}
}
