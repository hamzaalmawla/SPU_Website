<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacultyMemberTranslation extends Model
{
    use HasFactory;

    protected $fillable = [
        'faculty_member_id',
        'locale',
        'full_name',
        'title',
        'position',
        'bio',
        'specializations',
    ];

    protected function casts(): array
    {
        return ['specializations' => 'array'];
    }

    public function facultyMember(): BelongsTo
    {
        return $this->belongsTo(FacultyMember::class);
    }
}
