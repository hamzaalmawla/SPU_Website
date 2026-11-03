<?php

declare(strict_types=1);

namespace App\Contracts\Chatbot;

use App\DTOs\Chatbot\ChatbotCompletionDTO;
use App\DTOs\Chatbot\ChatbotCompletionRequestDTO;
use App\DTOs\Chatbot\ChatbotEmbeddingResultDTO;
use App\Exceptions\ChatbotProviderException;

/**
 * The two OpenAI endpoints the assistant uses, behind bounded timeouts.
 *
 * @throws ChatbotProviderException on any provider failure
 */
interface OpenAiClientInterface
{
    /**
     * @param  list<string>  $inputs
     */
    public function embed(array $inputs, string $apiKey): ChatbotEmbeddingResultDTO;

    public function respond(ChatbotCompletionRequestDTO $request): ChatbotCompletionDTO;

    /**
     * OpenAI's free moderation check. True when the text is flagged as harmful.
     */
    public function moderate(string $input, string $apiKey): bool;
}
