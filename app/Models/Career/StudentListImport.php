<?php

declare(strict_types=1);

namespace App\Models\Career;

use App\Models\Faculty\Faculty;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One imported faculty council decision (graduates or top students).
 *
 * @property array<string, ?int>|null $department_map specialization text => department id
 * @property array<string, array<string, mixed>>|null $previous_values row id => values before the import
 */
class StudentListImport extends Model
{
    protected $fillable = [
        'faculty_id',
        'kind',
        'original_filename',
        'decision_number',
        'academic_year',
        'semester',
        'created_count',
        'updated_count',
        'skipped_count',
        'department_map',
        'previous_values',
        'imported_by',
        'undone_at',
        'undone_by',
    ];

    protected function casts(): array
    {
        return [
            'created_count' => 'integer',
            'updated_count' => 'integer',
            'skipped_count' => 'integer',
            'department_map' => 'array',
            'previous_values' => 'array',
            'undone_at' => 'datetime',
        ];
    }

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }

    public function importer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function alumni(): HasMany
    {
        return $this->hasMany(Alumni::class);
    }

    public function honorStudents(): HasMany
    {
        return $this->hasMany(HonorStudent::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('undone_at');
    }
}
