<?php

declare(strict_types=1);

namespace App\Models\News;

use App\Models\Media\MediaAsset;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NewsArticleEvent extends Model
{
    protected $fillable = [
        'news_article_id',
        'kind',
        'requires_registration',
        'show_on_calendar',
        'form_key',
        'capacity',
        'prior_registrations',
        'registration_closes_at',
        'starts_at',
        'ends_at',
        'language',
        'has_certificate',
        'audience',
        'invitation_media_id',
        'map_url',
        'online_url',
        'contact_email',
        'contact_phone',
    ];

    protected function casts(): array
    {
        return [
            'requires_registration' => 'boolean',
            'show_on_calendar' => 'boolean',
            'capacity' => 'integer',
            'prior_registrations' => 'integer',
            'registration_closes_at' => 'datetime',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'has_certificate' => 'boolean',
            'audience' => 'array',
        ];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(NewsArticle::class, 'news_article_id');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(NewsArticleEventTranslation::class)->orderBy('locale');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(NewsArticleEventSession::class)->orderBy('sort_order')->orderBy('id');
    }

    /** Speakers of the whole event; session speakers hang off their session. */
    public function speakers(): HasMany
    {
        return $this->hasMany(NewsArticleEventSpeaker::class)
            ->whereNull('news_article_event_session_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function invitationMedia(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'invitation_media_id');
    }
}
