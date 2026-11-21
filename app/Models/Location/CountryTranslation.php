<?php

declare(strict_types=1);

namespace App\Models\Location;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CountryTranslation extends Model
{
    use HasFactory;

    protected $fillable = ['country_id', 'locale', 'name', 'nationality'];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }
}
