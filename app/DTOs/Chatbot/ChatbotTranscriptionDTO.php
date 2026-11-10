<?php

declare(strict_types=1);

namespace App\DTOs\Chatbot;

/**
 * The text of a spoken question, or why there is none.
 *
 * status: ok | empty | too_long | limited | refused | unavailable | busy | error.
 * Only "ok" carries text; the others carry a localized message.
 */
final readonly class ChatbotTranscriptionDTO
{
    public const STATUS_OK = 'ok';

    public const STATUS_EMPTY = 'empty';

    public const STATUS_TOO_LONG = 'too_long';

    public function __construct(
        public string $status,
        public string $text = '',
        public string $message = '',
    ) {}
}
