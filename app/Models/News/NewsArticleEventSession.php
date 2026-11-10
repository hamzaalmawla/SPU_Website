<?php

declare(strict_types=1);

namespace App\Models\News;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NewsArticleEventSession extends Model
{
    protected $fillable = [
        'news_article_event_id',
        'date',
        'starts_at',
        'ends_at',
        'title_ar',
        'title_en',
        'location_ar',
        'location_en',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'sort_order' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(NewsArticleEvent::class, 'news_article_event_id');
    }

    public function speakers(): HasMany
    {
        return $this->hasMany(NewsArticleEventSpeaker::class)->orderBy('sort_order')->orderBy('id');
    }
}
