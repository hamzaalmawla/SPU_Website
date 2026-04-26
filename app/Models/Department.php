<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Department extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['faculty_id', 'slug', 'sort_order', 'is_enabled'];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_enabled' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }

    public function translations(): HasMany
    {
        return $this->hasMany(DepartmentTranslation::class)->orderBy('locale');
    }

    public function members(): HasMany
    {
        return $this->hasMany(FacultyMember::class)->orderBy('sort_order');
    }

    public function alumni(): HasMany
    {
        return $this->hasMany(Alumni::class);
    }

    public function honorStudents(): HasMany
    {
        return $this->hasMany(HonorStudent::class)->orderBy('sort_order');
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enabled', true);
    }
}
