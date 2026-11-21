<?php

declare(strict_types=1);

namespace App\Models\Faculty;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FacultyPosition extends Model
{
    protected $fillable = ['faculty_id', 'slug', 'placement', 'sort_order'];

    protected function casts(): array
    {
        return ['faculty_id' => 'integer', 'sort_order' => 'integer'];
    }

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }

    public function translations(): HasMany
    {
        return $this->hasMany(FacultyPositionTranslation::class)->orderBy('locale');
    }
}
