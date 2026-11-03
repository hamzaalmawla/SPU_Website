<?php

declare(strict_types=1);

namespace App\Models\Chatbot;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One passage of a knowledge document and its packed float32 embedding.
 */
class ChatbotChunk extends Model
{
    protected $fillable = [
        'chatbot_document_id',
        'position',
        'page',
        'heading',
        'content',
        'content_normalized',
        'embedding',
    ];

    protected function casts(): array
    {
        return [
            'chatbot_document_id' => 'integer',
            'position' => 'integer',
            'page' => 'integer',
        ];
    }

    /** @return BelongsTo<ChatbotDocument, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(ChatbotDocument::class, 'chatbot_document_id');
    }
}
