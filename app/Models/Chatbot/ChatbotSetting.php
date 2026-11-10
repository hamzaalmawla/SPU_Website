<?php

declare(strict_types=1);

namespace App\Models\Chatbot;

use Illuminate\Database\Eloquent\Model;

/**
 * One AI assistant setting. The API key row holds an encrypted value.
 */
class ChatbotSetting extends Model
{
    protected $fillable = ['key', 'value', 'updated_by'];

    protected function casts(): array
    {
        return ['updated_by' => 'integer'];
    }
}
