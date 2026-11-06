<?php

declare(strict_types=1);

namespace App\Contracts\Chatbot;

use App\DTOs\Chatbot\ChatbotDocumentCardDTO;
use App\Exceptions\ChatbotProviderException;

/**
 * Writes a short "document card" for a long knowledge file: what it is and
 * its key facts (dates, fees, who it is for, how to apply), quoted from the
 * text, kept as one extra passage so those facts are found together with
 * the document's name.
 */
interface ChatbotDocumentCardWriterInterface
{
    /**
     * Null when the model gave nothing usable. Never throws for an ordinary
     * failure; the document is indexed without a card.
     *
     * @throws ChatbotProviderException only when the key is rejected or the account has no credit
     */
    public function write(string $title, string $text, string $apiKey): ?ChatbotDocumentCardDTO;
}
