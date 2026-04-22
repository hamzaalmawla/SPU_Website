<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MenuItem extends Model
{
    use HasFactory, SoftDeletes;

    public const GROUP_KEYS = [
        'header',
        'footer',
        'utility',
    ];

    /**
     * group_key is the current top-level menu registry because this foundation has no menus table yet.
     * New public menu areas should not be invented ad hoc outside an explicit phase decision.
     */

    /**
     * @var list<string>
     */
    protected $fillable = [
        'parent_id',
        'type',
        'label',
        'locale',
        'target_kind',
        'target_id',
        'url',
        'target',
        'route_name',
        'css_token',
        'icon',
        'group_key',
        'is_enabled',
        'is_utility',
        'open_in_new_tab',
        'sort_order',
        'depth',
    ];

    protected function casts(): array
    {
        return [
            'target_id' => 'integer',
            'is_enabled' => 'boolean',
            'is_utility' => 'boolean',
            'open_in_new_tab' => 'boolean',
            'sort_order' => 'integer',
            'depth' => 'integer',
            'deleted_at' => 'datetime',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function pageTarget(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'target_id');
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enabled', true);
    }

    public function scopeForLocale(Builder $query, ?string $locale): Builder
    {
        return $query->where('locale', $locale);
    }

    public function scopeForType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }
}
