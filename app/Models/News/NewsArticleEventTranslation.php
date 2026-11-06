<?php

declare(strict_types=1);

namespace App\Models\News;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsArticleEventTranslation extends Model
{
    protected $fillable = [
        'news_article_event_id',
        'locale',
        'location',
        'organizer',
        'audience_note',
        'certificate_note',
        'results',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(NewsArticleEvent::class, 'news_article_event_id');
    }
}
