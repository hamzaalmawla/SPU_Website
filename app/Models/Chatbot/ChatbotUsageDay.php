<?php

declare(strict_types=1);

namespace App\Models\Chatbot;

use Illuminate\Database\Eloquent\Model;

/**
 * Daily counters of the AI assistant: questions, tokens and estimated spend.
 * No question or answer text is stored.
 */
class ChatbotUsageDay extends Model
{
    protected $fillable = [
        'day',
        'questions',
        'answered_from_cache',
        'without_sources',
        'failures',
        'input_tokens',
        'cached_input_tokens',
        'output_tokens',
        'embedding_tokens',
        'cost_micro_usd',
    ];

    protected function casts(): array
    {
        return [
            'questions' => 'integer',
            'answered_from_cache' => 'integer',
            'without_sources' => 'integer',
            'failures' => 'integer',
            'input_tokens' => 'integer',
            'cached_input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'embedding_tokens' => 'integer',
            'cost_micro_usd' => 'integer',
        ];
    }
}
