<?php

declare(strict_types=1);

namespace App\Contracts\Chatbot;

use App\DTOs\Chatbot\ChatbotSettingsDTO;

/**
 * Stores the assistant's settings. The OpenAI key is encrypted at rest and is
 * returned only by apiKey(), for the server-side calls.
 */
interface ChatbotSettingsServiceInterface
{
    public function get(): ChatbotSettingsDTO;

    /**
     * The key for OpenAI calls: the one saved in the admin, else OPENAI_API_KEY.
     */
    public function apiKey(): ?string;

    /**
     * Save settings. A non-empty $newApiKey replaces the stored key;
     * $clearApiKey removes it. Other fields of $settings that describe the key
     * (hasApiKey, apiKeyHint) are ignored.
     */
    public function save(ChatbotSettingsDTO $settings, ?string $newApiKey, bool $clearApiKey, int $actorId): bool;

    /**
     * Changes whenever settings that affect answers change.
     */
    public function version(): string;
}
