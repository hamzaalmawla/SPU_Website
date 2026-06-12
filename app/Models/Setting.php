<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    public const GROUP_KEYS = [
        'navigation',
        'public_shell',
        'footer',
        'contact_page',
        'e_services_page',
        'seo',
    ];

    /**
     * group_key is the current settings namespace boundary used by the service layer.
     * New groups should be introduced deliberately rather than inferred ad hoc by later phases.
     */

    /**
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'group_key',
        'type',
        'locale',
        'value_json',
        'value_text',
        'is_public',
    ];

    protected function casts(): array
    {
        return [
            'value_json' => 'array',
            'is_public' => 'boolean',
        ];
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    public function scopeForGroup(Builder $query, string $groupKey): Builder
    {
        return $query->where('group_key', $groupKey);
    }

    public function scopeForLocale(Builder $query, ?string $locale): Builder
    {
        return $query->where('locale', $locale ?? '');
    }
}
