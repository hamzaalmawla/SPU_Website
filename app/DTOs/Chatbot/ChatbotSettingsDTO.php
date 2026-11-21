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
        // Asked when the main model fails; null for none.
        public ?string $fallbackModel = null,
        // Fair use per visitor (browser session); 0 means no limit.
        public int $visitorHourlyLimit = 20,
        public int $visitorDailyLimit = 40,
        // Voice: spoken questions and answers read aloud.
        public bool $voiceEnabled = true,
        public string $voiceName = 'cedar',
        // Recordings transcribed plus answers read aloud per day, whole site; 0 means no limit.
        public int $voiceDailyLimit = 1500,
    ) {}
}
