<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AboutPage extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'slug',
        'template',
        'hero_image',
        'payload_json',
        'status',
        'publish_at',
        'published_at',
        'is_enabled',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'payload_json' => 'array',
            'publish_at' => 'datetime',
            'published_at' => 'datetime',
            'is_enabled' => 'boolean',
            'sort_order' => 'integer',
            'deleted_at' => 'datetime',
        ];
    }

    public function translations(): HasMany
    {
        return $this->hasMany(AboutPageTranslation::class)->orderBy('locale');
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query
            ->where('is_enabled', true)
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where(function (Builder $query): void {
                $query->whereNull('publish_at')->orWhere('publish_at', '<=', now());
            });
    }
}
