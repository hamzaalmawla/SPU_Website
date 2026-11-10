<?php

declare(strict_types=1);

namespace App\Services\Chatbot;

use App\Contracts\Chatbot\ChatbotSettingsServiceInterface;
use App\Contracts\Shared\AuditServiceInterface;
use App\Contracts\Shared\CacheServiceInterface;
use App\DTOs\Chatbot\ChatbotSettingsDTO;
use App\Models\Chatbot\ChatbotSetting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Key/value settings of the AI assistant, with defaults from config and the
 * chatbot language files.
 *
 * The OpenAI key is stored with Laravel's encrypter (APP_KEY), is never put
 * in a DTO, and is never written to the audit log.
 */
final class ChatbotSettingsService implements ChatbotSettingsServiceInterface
{
    public const CACHE_KEY = 'chatbot.settings.v1';

    private const KEYS = [
        'enabled', 'model', 'api_key', 'assistant_name_ar', 'assistant_name_en', 'welcome_ar', 'welcome_en',
        'suggestions_ar', 'suggestions_en', 'extra_instructions', 'daily_question_limit', 'fallback_model',
        'visitor_hourly_limit', 'visitor_daily_limit', 'voice_enabled', 'voice_name', 'voice_daily_limit',
    ];

    public function __construct(
        private readonly CacheServiceInterface $cache,
        private readonly AuditServiceInterface $audit,
    ) {}

    public function get(): ChatbotSettingsDTO
    {
        $values = $this->values();
        $apiKey = $this->apiKey();

        return new ChatbotSettingsDTO(
            enabled: ($values['enabled'] ?? '1') === '1',
            model: $this->model($values['model'] ?? null),
            hasApiKey: $apiKey !== null,
            apiKeyHint: $apiKey !== null ? '…'.substr($apiKey, -4) : null,
            assistantNameAr: $this->text($values['assistant_name_ar'] ?? null, __('chatbot.defaults.name', [], 'ar')),
            assistantNameEn: $this->text($values['assistant_name_en'] ?? null, __('chatbot.defaults.name', [], 'en')),
            welcomeAr: $this->text($values['welcome_ar'] ?? null, __('chatbot.defaults.welcome', [], 'ar')),
            welcomeEn: $this->text($values['welcome_en'] ?? null, __('chatbot.defaults.welcome', [], 'en')),
            suggestionsAr: $this->suggestions($values['suggestions_ar'] ?? null, 'ar'),
            suggestionsEn: $this->suggestions($values['suggestions_en'] ?? null, 'en'),
            extraInstructions: (string) ($values['extra_instructions'] ?? ''),
            dailyQuestionLimit: max(0, (int) ($values['daily_question_limit'] ?? config('chatbot.limits.daily_requests', 3000))),
            updatedAt: $values['_updated_at'] ?? null,
            fallbackModel: $this->fallbackModel($values),
            visitorHourlyLimit: max(0, (int) ($values['visitor_hourly_limit'] ?? config('chatbot.fair_use.hourly', 20))),
            visitorDailyLimit: max(0, (int) ($values['visitor_daily_limit'] ?? config('chatbot.fair_use.daily', 40))),
            voiceEnabled: ($values['voice_enabled'] ?? '1') === '1',
            voiceName: $this->voice($values['voice_name'] ?? null),
            voiceDailyLimit: max(0, (int) ($values['voice_daily_limit'] ?? config('chatbot.voice.limits.daily_requests', 1500))),
        );
    }

    public function apiKey(): ?string
    {
        $stored = $this->values()['api_key'] ?? null;
        if (is_string($stored) && $stored !== '') {
            try {
                $key = trim(Crypt::decryptString($stored));
                if ($key !== '') {
                    return $key;
                }
            } catch (DecryptException) {
                // APP_KEY changed since the key was saved: fall through to the
                // environment key, and the admin page shows "no key".
            }
        }

        $fallback = config('chatbot.openai.api_key');

        return is_string($fallback) && trim($fallback) !== '' ? trim($fallback) : null;
    }

    public function save(ChatbotSettingsDTO $settings, ?string $newApiKey, bool $clearApiKey, int $actorId): bool
    {
        if (! array_key_exists($settings->model, (array) config('chatbot.models', []))) {
            throw new InvalidArgumentException('admin_chatbot.errors.unknown_model');
        }

        if ($settings->fallbackModel !== null && ! array_key_exists($settings->fallbackModel, (array) config('chatbot.models', []))) {
            throw new InvalidArgumentException('admin_chatbot.errors.unknown_model');
        }

        if (! in_array($settings->voiceName, $this->voices(), true)) {
            throw new InvalidArgumentException('admin_chatbot.errors.unknown_voice');
        }

        $newApiKey = $newApiKey !== null ? trim($newApiKey) : null;
        if ($newApiKey !== null && $newApiKey !== '' && preg_match('/^[A-Za-z0-9_\-]{20,300}$/', $newApiKey) !== 1) {
            throw new InvalidArgumentException('admin_chatbot.errors.invalid_api_key');
        }

        $values = [
            'enabled' => $settings->enabled ? '1' : '0',
            'model' => $settings->model,
            'assistant_name_ar' => mb_substr(trim($settings->assistantNameAr), 0, 60),
            'assistant_name_en' => mb_substr(trim($settings->assistantNameEn), 0, 60),
            'welcome_ar' => mb_substr(trim($settings->welcomeAr), 0, 600),
            'welcome_en' => mb_substr(trim($settings->welcomeEn), 0, 600),
            'suggestions_ar' => json_encode($this->cleanSuggestions($settings->suggestionsAr), JSON_UNESCAPED_UNICODE),
            'suggestions_en' => json_encode($this->cleanSuggestions($settings->suggestionsEn), JSON_UNESCAPED_UNICODE),
            'extra_instructions' => mb_substr(trim($settings->extraInstructions), 0, 2000),
            'daily_question_limit' => (string) max(0, min(1_000_000, $settings->dailyQuestionLimit)),
            // "none" is stored explicitly so the config default does not come back.
            'fallback_model' => $settings->fallbackModel !== null && $settings->fallbackModel !== $settings->model ? $settings->fallbackModel : 'none',
            'visitor_hourly_limit' => (string) max(0, min(1000, $settings->visitorHourlyLimit)),
            'visitor_daily_limit' => (string) max(0, min(5000, $settings->visitorDailyLimit)),
            'voice_enabled' => $settings->voiceEnabled ? '1' : '0',
            'voice_name' => $settings->voiceName,
            'voice_daily_limit' => (string) max(0, min(1_000_000, $settings->voiceDailyLimit)),
        ];

        $keyChange = null;
        if ($clearApiKey) {
            $values['api_key'] = null;
            $keyChange = 'removed';
        } elseif ($newApiKey !== null && $newApiKey !== '') {
            $values['api_key'] = Crypt::encryptString($newApiKey);
            $keyChange = 'replaced';
        }

        $before = $this->get();

        // 0: an unattended run (the deploy's key import) with no editor behind it.
        $actor = $actorId > 0 ? $actorId : null;

        DB::transaction(function () use ($values, $actor): void {
            foreach ($values as $key => $value) {
                ChatbotSetting::query()->updateOrCreate(['key' => $key], ['value' => $value, 'updated_by' => $actor]);
            }
        });

        $this->cache->forget(self::CACHE_KEY);
        $after = $this->get();

        $this->audit->log('chatbot.settings.updated', $actor, 'chatbot_settings', null, [
            'enabled' => $after->enabled,
            'model' => $after->model,
            'fallback_model' => $after->fallbackModel,
            'daily_question_limit' => $after->dailyQuestionLimit,
            'visitor_hourly_limit' => $after->visitorHourlyLimit,
            'visitor_daily_limit' => $after->visitorDailyLimit,
            'voice_enabled' => $after->voiceEnabled,
            'voice_name' => $after->voiceName,
            'voice_daily_limit' => $after->voiceDailyLimit,
            'api_key' => $keyChange ?? 'unchanged',
        ]);

        // The voice controls are part of the page HTML too.
        ChatbotCaches::widgetChanged($this->cache, $before->enabled !== $after->enabled || $before->hasApiKey !== $after->hasApiKey || $before->voiceEnabled !== $after->voiceEnabled);

        return true;
    }

    public function version(): string
    {
        return substr(sha1(serialize(array_diff_key($this->values(), ['api_key' => true]))), 0, 12);
    }

    /**
     * @return array<string, ?string>
     */
    private function values(): array
    {
        /** @var array<string, ?string> $values */
        $values = $this->cache->remember(self::CACHE_KEY, function (): array {
            $rows = ChatbotSetting::query()->whereIn('key', self::KEYS)->get(['key', 'value', 'updated_at']);
            $values = [];
            foreach ($rows as $row) {
                $values[(string) $row->key] = $row->value !== null ? (string) $row->value : null;
            }
            $latest = $rows->max('updated_at');
            $values['_updated_at'] = $latest !== null ? (string) $latest : null;

            return $values;
        }, 86400);

        return is_array($values) ? $values : [];
    }

    private function model(?string $model): string
    {
        $models = (array) config('chatbot.models', []);

        return is_string($model) && array_key_exists($model, $models) ? $model : (string) config('chatbot.default_model', 'gpt-5-nano');
    }

    /**
     * @param  array<string, ?string>  $values
     */
    private function fallbackModel(array $values): ?string
    {
        $stored = $values['fallback_model'] ?? null;
        $candidate = $stored ?? (string) config('chatbot.fallback_model', '');
        $models = (array) config('chatbot.models', []);

        return $candidate !== 'none' && $candidate !== '' && array_key_exists($candidate, $models) && $candidate !== $this->model($values['model'] ?? null)
            ? $candidate
            : null;
    }

    private function voice(?string $voice): string
    {
        $default = (string) config('chatbot.voice.speech.default_voice', 'cedar');

        return is_string($voice) && in_array($voice, $this->voices(), true) ? $voice : $default;
    }

    /** @return list<string> */
    private function voices(): array
    {
        return array_values(array_filter((array) config('chatbot.voice.speech.voices', []), 'is_string'));
    }

    private function text(?string $value, mixed $default): string
    {
        return is_string($value) && trim($value) !== '' ? $value : (string) $default;
    }

    /** @return list<string> */
    private function suggestions(?string $json, string $locale): array
    {
        $decoded = is_string($json) ? json_decode($json, true) : null;
        if (is_array($decoded)) {
            return $this->cleanSuggestions($decoded);
        }

        $defaults = __('chatbot.defaults.suggestions', [], $locale);

        return is_array($defaults) ? $this->cleanSuggestions($defaults) : [];
    }

    /**
     * @param  array<mixed>  $items
     * @return list<string>
     */
    private function cleanSuggestions(array $items): array
    {
        $clean = [];
        foreach ($items as $item) {
            $item = is_string($item) ? trim($item) : '';
            if ($item !== '' && ! in_array($item, $clean, true)) {
                $clean[] = mb_substr($item, 0, 120);
            }
        }

        return array_slice($clean, 0, 6);
    }
}
