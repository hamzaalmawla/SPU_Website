<?php

declare(strict_types=1);

namespace App\Models\News;

use App\Models\Media\MediaAsset;
use App\Models\Person\FacultyMember;
use App\Models\Person\Person;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsArticleEventSpeaker extends Model
{
    protected $fillable = [
        'news_article_event_id',
        'news_article_event_session_id',
        'faculty_member_id',
        'person_id',
        'name_ar',
        'name_en',
        'title_ar',
        'title_en',
        'organization_ar',
        'organization_en',
        'bio_ar',
        'bio_en',
        'photo_media_id',
        'sort_order',
    ];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(NewsArticleEvent::class, 'news_article_event_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(NewsArticleEventSession::class, 'news_article_event_session_id');
    }

    public function facultyMember(): BelongsTo
    {
        return $this->belongsTo(FacultyMember::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function photoMedia(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'photo_media_id');
    }
}
