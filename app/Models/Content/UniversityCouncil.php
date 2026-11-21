<?php

declare(strict_types=1);

namespace App\Models\Content;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One council under About › Councils. Its page content lives in the CMS
 * (target entity.council.{id}); the row holds only its address, order and
 * whether it is shown.
 */
class UniversityCouncil extends Model
{
    protected $fillable = ['slug', 'sort_order', 'is_enabled', 'former_path'];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_enabled' => 'boolean',
        ];
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enabled', true);
    }
}
