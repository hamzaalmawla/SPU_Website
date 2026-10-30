<?php

declare(strict_types=1);

namespace App\Models\Faculty;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacultyPositionTranslation extends Model
{
    protected $fillable = ['faculty_position_id', 'locale', 'name'];

    public function position(): BelongsTo
    {
        return $this->belongsTo(FacultyPosition::class, 'faculty_position_id');
    }
}
