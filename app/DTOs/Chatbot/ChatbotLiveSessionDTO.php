<?php

declare(strict_types=1);

namespace App\DTOs\Chatbot;

/**
 * A live transcription session for one visitor, or why there is none.
 *
 * status: ok | limited | refused | unavailable | busy | error. On ok, the
 * browser opens `url` with the short-lived `key` (it opens a transcription-
 * only session: no answers, no chat) and sends `session` as its first
 * `session.update`. On anything else it records and uploads instead.
 */
final readonly class ChatbotLiveSessionDTO
{
    public const STATUS_OK = 'ok';

    /**
     * @param  array<string, mixed>  $session
     */
    public function __construct(
        public string $status,
        public ?string $key = null,
        public ?int $expiresAt = null,
        public ?string $url = null,
        public array $session = [],
    ) {}
}
