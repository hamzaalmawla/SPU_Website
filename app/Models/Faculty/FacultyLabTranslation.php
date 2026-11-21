<?php

declare(strict_types=1);

namespace App\Models\Faculty;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacultyLabTranslation extends Model
{
    use HasFactory;

    protected $fillable = ['faculty_lab_id', 'locale', 'title', 'department', 'instructor', 'description'];

    public function lab(): BelongsTo
    {
        return $this->belongsTo(FacultyLab::class, 'faculty_lab_id');
    }
}
