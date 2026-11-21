<?php

declare(strict_types=1);

namespace App\Models\Location;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CityTranslation extends Model
{
    use HasFactory;

    protected $fillable = ['city_id', 'locale', 'name'];

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }
}
