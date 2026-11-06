<?php

declare(strict_types=1);

namespace App\Models\Chatbot;

use App\Models\User\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A file (or typed text) the AI assistant answers from.
 *
 * Written only by App\Services\Chatbot\ChatbotKnowledgeService.
 */
class ChatbotDocument extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_READY = 'ready';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'title',
        'source_type',
        'source_url',
        'source_key',
        'locale',
        'original_name',
        'storage_path',
        'extension',
        'size_bytes',
        'checksum',
        'status',
        'error',
        'is_active',
        'chunk_count',
        'character_count',
        'embedding_tokens',
        'embedding_model',
        'embedding_dimensions',
        'scan_pages_total',
        'scan_pages_read',
        'uploaded_by',
        'indexed_at',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'is_active' => 'boolean',
            'chunk_count' => 'integer',
            'character_count' => 'integer',
            'embedding_tokens' => 'integer',
            'embedding_dimensions' => 'integer',
            'scan_pages_total' => 'integer',
            'scan_pages_read' => 'integer',
            'uploaded_by' => 'integer',
            'indexed_at' => 'datetime',
        ];
    }

    /** @return HasMany<ChatbotChunk, $this> */
    public function chunks(): HasMany
    {
        return $this->hasMany(ChatbotChunk::class);
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Documents whose passages answer questions.
     *
     * @param  Builder<ChatbotDocument>  $query
     * @return Builder<ChatbotDocument>
     */
    public function scopeSearchable(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_READY)->where('is_active', true);
    }
}
