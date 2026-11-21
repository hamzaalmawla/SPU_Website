<?php

declare(strict_types=1);

namespace App\Models\Location;

use App\Models\Career\Alumni;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'code3', 'phone_code', 'currency_code', 'is_enabled', 'sort_order'];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function translations(): HasMany
    {
        return $this->hasMany(CountryTranslation::class)->orderBy('locale');
    }

    public function cities(): HasMany
    {
        return $this->hasMany(City::class)->orderBy('sort_order');
    }

    public function alumni(): HasMany
    {
        return $this->hasMany(Alumni::class);
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enabled', true);
    }
}
