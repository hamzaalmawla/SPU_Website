<?php

declare(strict_types=1);

namespace App\Models\Person;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FacultyMemberEducation extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'faculty_member_educations';

    protected $fillable = [
        'faculty_member_id',
        'sort_order',
        'is_enabled',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_enabled' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    public function facultyMember(): BelongsTo
    {
        return $this->belongsTo(FacultyMember::class);
    }

    public function translations(): HasMany
    {
        return $this->hasMany(FacultyMemberEducationTranslation::class, 'fme_id')->orderBy('locale');
    }

    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }
}
