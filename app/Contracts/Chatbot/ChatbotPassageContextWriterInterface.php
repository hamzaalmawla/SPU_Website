<?php

declare(strict_types=1);

namespace App\Contracts\Chatbot;

use App\DTOs\Chatbot\ChatbotPassageContextsDTO;
use App\Exceptions\ChatbotProviderException;

/**
 * Contextual retrieval (Anthropic, 2024; found the most accurate chunk
 * enrichment in "Reconstructing Context", 2025): for each passage of a
 * document, one or two sentences saying where it sits (which document,
 * article and subject), written once at index time and searched with the
 * passage. A clause such as "د-2- ... 50%" then carries "decree 448, article
 * 16, exemption for degree holders from the same faculty".
 */
interface ChatbotPassageContextWriterInterface
{
    /**
     * One context per passage, in order ('' where none was written). Only
     * the passages listed in `$only` are written (null: all); the others
     * are still read as the text around them. Never throws for an ordinary
     * failure: those passages go without.
     *
     * @param  list<array{heading: ?string, content: string}>  $passages
     * @param  list<int>|null  $only
     *
     * @throws ChatbotProviderException only when the key is rejected or the account has no credit
     */
    public function write(string $title, string $card, array $passages, string $apiKey, ?array $only = null): ChatbotPassageContextsDTO;
}
