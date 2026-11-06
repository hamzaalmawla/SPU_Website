<?php

declare(strict_types=1);

namespace App\DTOs\Chatbot;

/**
 * What one website sync did. `reachable` is false when no page could be read
 * (the site or the network was down); nothing is removed then.
 */
final readonly class ChatbotWebsiteSyncDTO
{
    public function __construct(
        public bool $reachable,
        public int $pages,
        public int $added,
        public int $updated,
        public int $unchanged,
        public int $removed,
        public int $failed,
    ) {}
}
