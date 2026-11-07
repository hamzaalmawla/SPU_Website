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
     * @param  list<ChatbotPassageDTO>  $passages  the passages the answer was given (admin test box only)
     */
    public function __construct(
        public string $status,
        public string $answer,
        public array $sources = [],
        public bool $cached = false,
        public ?string $signature = null,
        // Why a question was refused (admin test box only; never sent to visitors).
        public ?string $reason = null,
        public array $passages = [],
        // The question as rewritten for search (admin test box only).
        public ?string $searchHint = null,
        // The conversation notes, sealed for the next question (ok answers only).
        public ?string $memory = null,
        // Proof that this server wrote `answer`, which lets it be read aloud.
        public ?string $speechSignature = null,
        /** @var array<string, int> milliseconds per stage (screen, search, model), for Server-Timing */
        public array $timings = [],
    ) {}

}
