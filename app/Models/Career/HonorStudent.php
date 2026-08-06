<?php

declare(strict_types=1);

namespace App\Models\Career;

use App\Models\Faculty\Department;
use App\Models\Faculty\Faculty;
use App\Models\Media\MediaAsset;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class HonorStudent extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'student_identifier',
        'faculty_id',
        'department_id',
        'academic_year',
        'gpa',
        'photo_media_id',
        'legacy_photo_path',
        'sort_order',
        'is_enabled',
    ];

    protected function casts(): array
    {
        return [
            'gpa' => 'decimal:2',
            'sort_order' => 'integer',
            'is_enabled' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function photoMedia(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'photo_media_id');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(HonorStudentTranslation::class)->orderBy('locale');
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enabled', true);
    }
}
