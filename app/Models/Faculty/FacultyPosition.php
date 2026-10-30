<?php

declare(strict_types=1);

namespace App\Models\Faculty;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FacultyPosition extends Model
{
    protected $fillable = ['slug', 'placement', 'sort_order'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    public function translations(): HasMany
    {
        return $this->hasMany(FacultyPositionTranslation::class)->orderBy('locale');
    }
}
