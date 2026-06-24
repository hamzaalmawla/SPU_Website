<?php

declare(strict_types=1);

namespace App\Models\Faculty;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacultyStudentProjectTranslation extends Model
{
    use HasFactory;

    protected $fillable = ['faculty_student_project_id', 'locale', 'title', 'summary', 'tag', 'team', 'supervisor'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(FacultyStudentProject::class, 'faculty_student_project_id');
    }
}
