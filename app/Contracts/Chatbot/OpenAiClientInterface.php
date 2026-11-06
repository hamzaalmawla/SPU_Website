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
     * Several Responses calls at once (one HTTP round trip's worth of time),
     * for work that splits into independent pieces such as the pages of a
     * scan. Each key gets its completion, or the failure for that call; one
     * failing call never fails the others.
     *
     * @param  array<string, ChatbotCompletionRequestDTO>  $requests
     * @return array<string, ChatbotCompletionDTO|ChatbotProviderException>
     */
    public function respondMany(array $requests): array;

    /**
     * OpenAI's free moderation check. True when the text is flagged as harmful.
     */
    public function moderate(string $input, string $apiKey): bool;
}
