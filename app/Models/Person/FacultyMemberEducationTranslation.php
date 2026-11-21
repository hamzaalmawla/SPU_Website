<?php

declare(strict_types=1);

namespace App\Models\Person;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacultyMemberEducationTranslation extends Model
{
    use HasFactory;

    protected $table = 'faculty_member_education_translations';

    protected $fillable = [
        'fme_id',
        'locale',
        'degree',
        'institution',
        'field_of_study',
        'year_start',
        'year_end',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'year_start' => 'integer',
            'year_end' => 'integer',
        ];
    }

    public function education(): BelongsTo
    {
        return $this->belongsTo(FacultyMemberEducation::class, 'fme_id');
    }
}
