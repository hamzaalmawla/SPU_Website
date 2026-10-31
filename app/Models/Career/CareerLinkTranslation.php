<?php

declare(strict_types=1);

namespace App\Models\Career;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CareerLinkTranslation extends Model
{
    use HasFactory;

    protected $fillable = ['career_link_id', 'locale', 'title', 'description'];

    public function careerLink(): BelongsTo
    {
        return $this->belongsTo(CareerLink::class);
    }
}
