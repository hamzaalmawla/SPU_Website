<?php

declare(strict_types=1);

namespace App\Models\Faculty;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FacultyStudentProject extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'faculty_id',
        'legacy_source_id',
        'legacy_service_type',
        'slug',
        'image',
        'gallery_json',
        'documents_json',
        'sort_order',
        'is_enabled',
    ];

    protected function casts(): array
    {
        return [
            'legacy_source_id' => 'integer',
            'legacy_service_type' => 'integer',
            'gallery_json' => 'array',
            'documents_json' => 'array',
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
        return $this->hasMany(FacultyStudentProjectTranslation::class)->orderBy('locale');
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enabled', true);
    }
}
