<?php

declare(strict_types=1);

namespace App\Models\Achievement;

use App\Models\Media\MediaAsset;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Achievement extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['image_media_id', 'legacy_image_path', 'status', 'published_at', 'is_public', 'pin_to_homepage', 'sort_order'];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'is_public' => 'boolean',
            'pin_to_homepage' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function translations(): HasMany
    {
        return $this->hasMany(AchievementTranslation::class)->orderBy('locale');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(AchievementCategory::class, 'achievement_category');
    }

    public function imageMedia(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'image_media_id');
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query
            ->where('status', 'published')
            ->where('is_public', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }
}
