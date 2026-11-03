<?php

declare(strict_types=1);

namespace App\Contracts\Chatbot;

use App\DTOs\Chatbot\ChatbotCompletionDTO;
use App\DTOs\Chatbot\ChatbotCompletionRequestDTO;
use App\DTOs\Chatbot\ChatbotEmbeddingResultDTO;

/**
 * The two OpenAI endpoints the assistant uses, behind bounded timeouts.
 *
 * @throws \App\Exceptions\ChatbotProviderException on any provider failure
 */
interface OpenAiClientInterface
{
    /**
     * @param  list<string>  $inputs
     */
    public function embed(array $inputs, string $apiKey): ChatbotEmbeddingResultDTO;

    public function respond(ChatbotCompletionRequestDTO $request): ChatbotCompletionDTO;
}
