<?php

declare(strict_types=1);

namespace App\DTOs\Chatbot;

final readonly class ChatbotKnowledgeStatsDTO
{
    public function __construct(
        public int $documents,
        public int $searchableDocuments,
        public int $failedDocuments,
        public int $staleDocuments,
        public int $passages,
        public int $characters,
    ) {}
}
