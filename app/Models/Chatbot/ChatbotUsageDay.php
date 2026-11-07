<?php

declare(strict_types=1);

namespace App\Models\Chatbot;

use Illuminate\Database\Eloquent\Model;

/**
 * Daily counters of the AI assistant: questions, voice, tokens and estimated
 * spend. No question or answer text and no audio is stored.
 */
class ChatbotUsageDay extends Model
{
    protected $fillable = [
        'day',
        'questions',
        'answered_from_cache',
        'without_sources',
        'failures',
        'blocked',
        'rate_limited',
        'paused',
        'input_tokens',
        'cached_input_tokens',
        'output_tokens',
        'embedding_tokens',
        'cost_micro_usd',
        'voice_transcriptions',
        'voice_seconds',
        'voice_spoken_answers',
        'voice_characters',
        'voice_failures',
    ];

    protected function casts(): array
    {
        return [
            'questions' => 'integer',
            'answered_from_cache' => 'integer',
            'without_sources' => 'integer',
            'failures' => 'integer',
            'blocked' => 'integer',
            'rate_limited' => 'integer',
            'paused' => 'integer',
            'input_tokens' => 'integer',
            'cached_input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'embedding_tokens' => 'integer',
            'cost_micro_usd' => 'integer',
            'voice_transcriptions' => 'integer',
            'voice_seconds' => 'integer',
            'voice_spoken_answers' => 'integer',
            'voice_characters' => 'integer',
            'voice_failures' => 'integer',
        ];
    }
}
