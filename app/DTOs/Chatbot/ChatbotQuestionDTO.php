<?php

declare(strict_types=1);

namespace App\DTOs\Chatbot;

/**
 * A visitor's question with the turns before it, already validated.
 *
 * History comes from the visitor's browser and is untrusted: it only gives
 * follow-up questions their context.
 */
final readonly class ChatbotQuestionDTO
{
    /**
     * @param  list<array{role: 'user'|'assistant', content: string, signature?: ?string}>  $history
     */
    public function __construct(
        public string $locale,
        public string $message,
        public array $history = [],
        public bool $useCache = true,
        // An editor trying the assistant in the admin: answers even while it
        // is switched off or has no files, and skips the daily cap.
        public bool $adminTest = false,
        // A keyed hash of the visitor's address, for the repeat-misuse pause.
        public ?string $clientKey = null,
        // A keyed hash of the visitor's browser session, for fair-use limits.
        public ?string $visitorKey = null,
    ) {}
}
