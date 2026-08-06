<?php

declare(strict_types=1);

namespace App\Models\News;

use App\Models\Media\MediaAsset;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class NewsArticle extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'news_category_id',
        'cover_media_id',
        'slug',
        'status',
        'published_at',
        'scheduled_at',
        'is_enabled',
        'is_featured',
        'sort_order',
        'faculty_scope_slug',
        'legacy_source_table',
        'legacy_source_id',
        'legacy_service_type',
        'legacy_url',
        'legacy_cover_path',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'scheduled_at' => 'datetime',
            'is_enabled' => 'boolean',
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
            'legacy_source_id' => 'integer',
            'legacy_service_type' => 'integer',
            'deleted_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(NewsCategory::class, 'news_category_id');
    }

    public function coverMedia(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'cover_media_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(NewsArticleTranslation::class)->orderBy('locale');
    }

    public function seoMeta(): HasMany
    {
        return $this->hasMany(NewsArticleSeoMeta::class)->orderBy('locale');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(NewsArticleAttachment::class)->orderBy('sort_order');
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query
            ->where('status', 'published')
            ->where('is_enabled', true)
            ->where(function (Builder $builder): void {
                $builder->whereNull('published_at')->orWhere('published_at', '<=', now());
            });
    }
}
