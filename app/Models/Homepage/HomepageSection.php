<?php

declare(strict_types=1);

namespace App\Models\Homepage;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HomepageSection extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'type',
        'sort_order',
        'is_enabled',
        'schema_version',
        'config_json',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_enabled' => 'boolean',
            'schema_version' => 'integer',
            'config_json' => 'array',
        ];
    }

    public function translations(): HasMany
    {
        return $this->hasMany(HomepageSectionTranslation::class, 'section_id')->orderBy('locale');
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enabled', true);
    }
}
