<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Partnership extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['slug', 'logo', 'website_url', 'signed_at', 'sort_order', 'is_enabled'];

    protected function casts(): array
    {
        return [
            'signed_at' => 'date',
            'sort_order' => 'integer',
            'is_enabled' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    public function translations(): HasMany
    {
        return $this->hasMany(PartnershipTranslation::class)->orderBy('locale');
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enabled', true);
    }
}
