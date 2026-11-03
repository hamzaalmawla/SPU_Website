<?php

declare(strict_types=1);

namespace App\DTOs\Chatbot;

/**
 * Editable AI assistant settings. The API key itself never leaves the
 * settings service: this carries only whether one is set and its last four
 * characters.
 */
final readonly class ChatbotSettingsDTO
{
    /**
     * @param  list<string>  $suggestionsAr
     * @param  list<string>  $suggestionsEn
     */
    public function __construct(
        public bool $enabled,
        public string $model,
        public bool $hasApiKey,
        public ?string $apiKeyHint,
        public string $assistantNameAr,
        public string $assistantNameEn,
        public string $welcomeAr,
        public string $welcomeEn,
        public array $suggestionsAr,
        public array $suggestionsEn,
        public string $extraInstructions,
        public int $dailyQuestionLimit,
        public ?string $updatedAt = null,
    ) {}
}
