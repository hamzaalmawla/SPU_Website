<?php

declare(strict_types=1);

namespace App\Models\Search;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One row of the derived public-search index.
 *
 * Rows are written only by App\Services\Search\SearchIndexService. Nothing here
 * decides what is public: the service applies each domain's own publication
 * gate before a document is ever created, so anything present in this table is
 * already cleared for public display.
 */
class SearchDocument extends Model
{
    protected $fillable = [
        'searchable_type',
        'searchable_id',
        'type',
        'locale',
        'title',
        'title_normalized',
        'summary',
        'body_normalized',
        'url',
        'meta',
        'published_at',
        'weight',
    ];

    protected function casts(): array
    {
        return [
            'searchable_id' => 'integer',
            'published_at' => 'datetime',
            'weight' => 'integer',
        ];
    }

    public function scopeForLocale(Builder $query, string $locale): Builder
    {
        return $query->where('locale', $locale);
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }
}
