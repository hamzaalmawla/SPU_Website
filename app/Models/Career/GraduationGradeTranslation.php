<?php

declare(strict_types=1);

namespace App\Models\Career;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GraduationGradeTranslation extends Model
{
    protected $fillable = ['graduation_grade_id', 'locale', 'name'];

    public function grade(): BelongsTo
    {
        return $this->belongsTo(GraduationGrade::class, 'graduation_grade_id');
    }
}
