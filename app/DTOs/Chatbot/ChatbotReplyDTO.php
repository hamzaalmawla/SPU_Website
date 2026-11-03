<?php

declare(strict_types=1);

namespace App\DTOs\Chatbot;

/**
 * The assistant's reply to one question.
 *
 * status: ok | refused | limited | unavailable | busy | error. Only "ok" carries a
 * model answer (and the signature that lets it come back as history); the
 * others carry a localized message for the visitor.
 */
final readonly class ChatbotReplyDTO
{
    public const STATUS_OK = 'ok';

    public const STATUS_REFUSED = 'refused';

    public const STATUS_LIMITED = 'limited';

    public const STATUS_UNAVAILABLE = 'unavailable';

    public const STATUS_BUSY = 'busy';

    public const STATUS_ERROR = 'error';

    /**
     * @param  list<ChatbotSourceDTO>  $sources
     */
    public function __construct(
        public string $status,
        public string $answer,
        public array $sources = [],
        public bool $cached = false,
        public ?string $signature = null,
        // Why a question was refused (admin test box only; never sent to visitors).
        public ?string $reason = null,
    ) {}
}
