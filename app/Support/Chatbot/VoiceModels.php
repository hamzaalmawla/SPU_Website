<?php

declare(strict_types=1);

namespace App\Support\Chatbot;

use Illuminate\Support\Carbon;

/**
 * Which OpenAI audio models the assistant's voice may use today.
 */
final class VoiceModels
{
    /**
     * The speech model, or null when none is configured or it has reached
     * its shutdown date (`chatbot.voice.speech.retires_on`): answers are then
     * read by the visitor's browser.
     */
    public static function speech(): ?string
    {
        $model = trim((string) config('chatbot.voice.speech.model', ''));
        if ($model === '') {
            return null;
        }

        $retiresOn = trim((string) config('chatbot.voice.speech.retires_on', ''));
        if ($retiresOn !== '') {
            try {
                if (! now()->lt(Carbon::parse($retiresOn, (string) config('app.timezone'))->startOfDay())) {
                    return null;
                }
            } catch (\Throwable) {
                // An unreadable date never switches the voice off.
            }
        }

        return $model;
    }

    /**
     * The transcription model first, then its fallback.
     *
     * @return list<string>
     */
    public static function transcription(): array
    {
        $models = [];
        foreach ([config('chatbot.voice.transcription.model'), config('chatbot.voice.transcription.fallback_model')] as $model) {
            $model = is_string($model) ? trim($model) : '';
            if ($model !== '' && ! in_array($model, $models, true)) {
                $models[] = $model;
            }
        }

        return $models;
    }
}
