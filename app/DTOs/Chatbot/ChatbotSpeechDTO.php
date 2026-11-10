<?php

declare(strict_types=1);

namespace App\DTOs\Chatbot;

/**
 * One spoken segment of a reply, or why it cannot be spoken here.
 *
 * status: ok | forbidden | invalid | limited | refused | unavailable | busy | error.
 * Every status but "ok" tells the browser to read the reply with its own
 * voice instead (except "forbidden" and "invalid", which it never retries).
 *
 * An ok reply carries either the whole file (`audio`) or, when streamed,
 * `chunks`: the audio as OpenAI makes it, to be sent on as it arrives.
 */
final readonly class ChatbotSpeechDTO
{
    public const STATUS_OK = 'ok';

    public const STATUS_FORBIDDEN = 'forbidden';

    public const STATUS_INVALID = 'invalid';

    public function __construct(
        public string $status,
        public ?string $audio = null,
        public string $mimeType = 'audio/mpeg',
        public int $segment = 0,
        public int $segments = 0,
        /** @var iterable<string>|null */
        public ?iterable $chunks = null,
    ) {}
}
