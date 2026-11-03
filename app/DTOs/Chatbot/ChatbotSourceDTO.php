<?php

declare(strict_types=1);

namespace App\DTOs\Chatbot;

/**
 * A knowledge document an answer drew on, as shown under the answer.
 */
final readonly class ChatbotSourceDTO
{
    /**
     * @param  list<int>  $pages
     */
    public function __construct(
        public string $title,
        public array $pages = [],
    ) {}
}
